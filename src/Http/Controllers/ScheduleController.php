<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\TenantContext;
use App\Core\View;
use App\Services\BrazilHolidayService;
use App\Services\EstablishmentClock;
use App\Services\ScheduleRangeService;
use DateTimeImmutable;

final class ScheduleController
{
    public function index(): void
    {
        Auth::requireRole(['owner']);
        $establishmentId = TenantContext::requireEstablishmentId();
        $pdo = Database::connection();
        $clock = new EstablishmentClock();
        $now = $clock->now($pdo, $establishmentId);
        $today = $now->format('Y-m-d');

        $hoursStmt = $pdo->prepare(
            'SELECT * FROM business_hours WHERE establishment_id = :establishment ORDER BY weekday'
        );
        $hoursStmt->execute(['establishment' => $establishmentId]);
        $hours = [];
        foreach ($hoursStmt->fetchAll() as $row) {
            $hours[(int) $row['weekday']] = $row;
        }

        $hourRanges = [];
        try {
            $rangeStmt = $pdo->prepare(
                'SELECT weekday,opens_at,closes_at FROM business_hour_ranges '
                . 'WHERE establishment_id=:establishment ORDER BY weekday,sort_order,id'
            );
            $rangeStmt->execute(['establishment' => $establishmentId]);
            foreach ($rangeStmt->fetchAll() as $range) {
                $weekday = (int) $range['weekday'];
                $hourRanges[$weekday][] = [
                    'opens_at' => substr((string) $range['opens_at'], 0, 5),
                    'closes_at' => substr((string) $range['closes_at'], 0, 5),
                ];
            }
        } catch (\PDOException) {
            // Compatibilidade enquanto a migration ainda não foi aplicada.
        }
        foreach ($hours as $weekday => $row) {
            if (!isset($hourRanges[$weekday]) && (int) $row['is_closed'] !== 1 && $row['opens_at'] && $row['closes_at']) {
                $hourRanges[$weekday] = [[
                    'opens_at' => substr((string) $row['opens_at'], 0, 5),
                    'closes_at' => substr((string) $row['closes_at'], 0, 5),
                ]];
            }
        }

        $exceptionsStmt = $pdo->prepare(
            'SELECT * FROM special_hours WHERE establishment_id = :establishment '
            . 'AND special_date >= :date_start AND special_date <= :date_end '
            . 'ORDER BY special_date ASC'
        );
        $exceptionsStmt->execute([
            'establishment' => $establishmentId,
            'date_start' => $now->modify('-30 days')->format('Y-m-d'),
            'date_end' => $now->modify('+730 days')->format('Y-m-d'),
        ]);
        $exceptions = $exceptionsStmt->fetchAll();
        $exceptionsByDate = [];
        foreach ($exceptions as $exception) {
            $exceptionsByDate[(string) $exception['special_date']] = $exception;
        }

        $holidayService = new BrazilHolidayService();
        $currentYear = (int) $now->format('Y');
        $nationalHolidays = [];
        foreach ($holidayService->betweenYears($currentYear, $currentYear + 1) as $date => $name) {
            if ($date < $today) {
                continue;
            }
            $nationalHolidays[] = [
                'date' => $date,
                'name' => $name,
                'override' => $exceptionsByDate[$date] ?? null,
            ];
        }

        $specialDates = array_values(array_filter(
            $exceptions,
            static fn (array $row): bool => $holidayService->nameForDate((string) $row['special_date']) === null
        ));

        View::render('panel/hours', [
            'title' => 'Horários e datas especiais',
            'hours' => $hours,
            'hourRanges' => $hourRanges,
            'nationalHolidays' => $nationalHolidays,
            'specialDates' => $specialDates,
            'today' => $today,
            'tomorrow' => $now->modify('+1 day')->format('Y-m-d'),
        ]);
    }

    public function storeWeeklyHours(): void
    {
        Auth::requireRole(['owner']);
        Csrf::validate($_POST['_csrf'] ?? null);
        $establishmentId = TenantContext::requireEstablishmentId();
        $pdo = Database::connection();
        $rangeService = new ScheduleRangeService();

        $upsert = $pdo->prepare(
            'INSERT INTO business_hours (establishment_id, weekday, opens_at, closes_at, is_closed) '
            . 'VALUES (:establishment, :weekday, :opens, :closes, :closed) '
            . 'ON DUPLICATE KEY UPDATE opens_at = VALUES(opens_at), closes_at = VALUES(closes_at), is_closed = VALUES(is_closed)'
        );
        $deleteRanges = $pdo->prepare(
            'DELETE FROM business_hour_ranges WHERE establishment_id=:establishment AND weekday=:weekday'
        );
        $insertRange = $pdo->prepare(
            'INSERT INTO business_hour_ranges (establishment_id,weekday,opens_at,closes_at,sort_order) '
            . 'VALUES (:establishment,:weekday,:opens,:closes,:sort_order)'
        );

        $pdo->beginTransaction();
        try {
            for ($weekday = 1; $weekday <= 7; $weekday++) {
                $closed = isset($_POST['closed'][$weekday]);
                $ranges = $closed ? [] : $rangeService->normalize((array) ($_POST['ranges'][$weekday] ?? []));

                if (!$closed && $ranges === []) {
                    throw new \RuntimeException('Adicione ao menos uma faixa de horário nos dias abertos.');
                }

                $deleteRanges->execute([
                    'establishment' => $establishmentId,
                    'weekday' => $weekday,
                ]);

                $upsert->execute([
                    'establishment' => $establishmentId,
                    'weekday' => $weekday,
                    'opens' => $closed ? null : $ranges[0]['opens_at'],
                    'closes' => $closed ? null : $ranges[count($ranges) - 1]['closes_at'],
                    'closed' => $closed ? 1 : 0,
                ]);

                foreach ($ranges as $index => $range) {
                    $insertRange->execute([
                        'establishment' => $establishmentId,
                        'weekday' => $weekday,
                        'opens' => $range['opens_at'],
                        'closes' => $range['closes_at'],
                        'sort_order' => $index,
                    ]);
                }
            }

            $pdo->commit();
            flash('success', 'Horário semanal atualizado.');
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', $exception instanceof \RuntimeException ? $exception->getMessage() : 'Não foi possível atualizar os horários.');
        }

        redirect('/painel/horarios');
    }

    public function storeSpecialHours(): void
    {
        Auth::requireRole(['owner']);
        Csrf::validate($_POST['_csrf'] ?? null);
        $establishmentId = TenantContext::requireEstablishmentId();

        $date = trim((string) ($_POST['date'] ?? ''));
        $name = trim((string) ($_POST['name'] ?? ''));
        $source = (string) ($_POST['source'] ?? 'custom');
        $mode = (string) ($_POST['mode'] ?? 'closed');
        $opens = trim((string) ($_POST['opens_at'] ?? ''));
        $closes = trim((string) ($_POST['closes_at'] ?? ''));

        if (!in_array($source, ['national', 'custom', 'emergency'], true)) {
            $source = 'custom';
        }

        $dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
            flash('error', 'Informe uma data válida.');
            redirect('/painel/horarios');
        }

        $holidayService = new BrazilHolidayService();
        $nationalName = $holidayService->nameForDate($date);
        if ($source === 'national' && $nationalName !== null) {
            $name = $nationalName;
        }
        if ($name === '') {
            $name = $source === 'emergency' ? 'Fechamento excepcional' : 'Data especial';
        }

        $closed = $mode !== 'open';
        if (!$closed && !$this->validTimeRange($opens, $closes)) {
            flash('error', 'Para trabalhar nessa data, informe um horário de abertura e fechamento válido.');
            redirect('/painel/horarios');
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO special_hours '
            . '(establishment_id, special_date, name, source, is_closed, opens_at, closes_at) '
            . 'VALUES (:establishment, :date, :name, :source, :closed, :opens, :closes) '
            . 'ON DUPLICATE KEY UPDATE name = VALUES(name), source = VALUES(source), '
            . 'is_closed = VALUES(is_closed), opens_at = VALUES(opens_at), closes_at = VALUES(closes_at)'
        );
        $stmt->execute([
            'establishment' => $establishmentId,
            'date' => $date,
            'name' => $name,
            'source' => $source,
            'closed' => $closed ? 1 : 0,
            'opens' => $closed ? null : $opens,
            'closes' => $closed ? null : $closes,
        ]);

        flash('success', $closed ? 'Data marcada como fechada.' : 'Funcionamento especial salvo.');
        redirect('/painel/horarios');
    }

    public function deleteSpecialHours(string $id): void
    {
        Auth::requireRole(['owner']);
        Csrf::validate($_POST['_csrf'] ?? null);
        $establishmentId = TenantContext::requireEstablishmentId();

        $stmt = Database::connection()->prepare(
            'DELETE FROM special_hours WHERE id = :id AND establishment_id = :establishment'
        );
        $stmt->execute([
            'id' => (int) $id,
            'establishment' => $establishmentId,
        ]);

        flash('success', 'Exceção removida. O horário padrão volta a valer para essa data.');
        redirect('/painel/horarios');
    }

    private function validTimeRange(string $opens, string $closes): bool
    {
        if (!preg_match('/^\d{2}:\d{2}$/', $opens) || !preg_match('/^\d{2}:\d{2}$/', $closes)) {
            return false;
        }
        return $opens < $closes;
    }
}
