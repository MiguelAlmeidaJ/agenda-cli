<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\TenantContext;
use App\Core\View;
use App\Services\BrazilHolidayService;
use DateTimeImmutable;

final class ScheduleController
{
    public function index(): void
    {
        Auth::requireRole(['owner']);
        $establishmentId = TenantContext::requireEstablishmentId();
        $pdo = Database::connection();

        $hoursStmt = $pdo->prepare(
            'SELECT * FROM business_hours WHERE establishment_id = :establishment ORDER BY weekday'
        );
        $hoursStmt->execute(['establishment' => $establishmentId]);
        $hours = [];
        foreach ($hoursStmt->fetchAll() as $row) {
            $hours[(int) $row['weekday']] = $row;
        }

        $exceptionsStmt = $pdo->prepare(
            'SELECT * FROM special_hours WHERE establishment_id = :establishment '
            . 'AND special_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) '
            . 'AND special_date <= DATE_ADD(CURDATE(), INTERVAL 730 DAY) '
            . 'ORDER BY special_date ASC'
        );
        $exceptionsStmt->execute(['establishment' => $establishmentId]);
        $exceptions = $exceptionsStmt->fetchAll();
        $exceptionsByDate = [];
        foreach ($exceptions as $exception) {
            $exceptionsByDate[(string) $exception['special_date']] = $exception;
        }

        $holidayService = new BrazilHolidayService();
        $currentYear = (int) date('Y');
        $today = date('Y-m-d');
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
            'nationalHolidays' => $nationalHolidays,
            'specialDates' => $specialDates,
            'today' => $today,
            'tomorrow' => (new DateTimeImmutable('tomorrow'))->format('Y-m-d'),
        ]);
    }

    public function storeWeeklyHours(): void
    {
        Auth::requireRole(['owner']);
        Csrf::validate($_POST['_csrf'] ?? null);
        $establishmentId = TenantContext::requireEstablishmentId();
        $pdo = Database::connection();
        $upsert = $pdo->prepare(
            'INSERT INTO business_hours (establishment_id, weekday, opens_at, closes_at, is_closed) '
            . 'VALUES (:establishment, :weekday, :opens, :closes, :closed) '
            . 'ON DUPLICATE KEY UPDATE opens_at = VALUES(opens_at), closes_at = VALUES(closes_at), is_closed = VALUES(is_closed)'
        );

        for ($weekday = 1; $weekday <= 7; $weekday++) {
            $closed = isset($_POST['closed'][$weekday]);
            $opens = trim((string) ($_POST['opens'][$weekday] ?? ''));
            $closes = trim((string) ($_POST['closes'][$weekday] ?? ''));

            if (!$closed && !$this->validTimeRange($opens, $closes)) {
                flash('error', 'Confira os horários de abertura e fechamento.');
                redirect('/painel/horarios');
            }

            $upsert->execute([
                'establishment' => $establishmentId,
                'weekday' => $weekday,
                'opens' => $closed ? null : $opens,
                'closes' => $closed ? null : $closes,
                'closed' => $closed ? 1 : 0,
            ]);
        }

        flash('success', 'Horário semanal atualizado.');
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
