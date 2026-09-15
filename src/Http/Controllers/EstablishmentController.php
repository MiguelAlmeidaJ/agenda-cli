<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\TenantContext;
use App\Core\View;

final class EstablishmentController
{
    private const STATES = [
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS',
        'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC',
        'SP', 'SE', 'TO',
    ];

    private const TIMEZONES = [
        'America/Sao_Paulo' => 'Brasília / São Paulo',
        'America/Manaus' => 'Manaus',
        'America/Cuiaba' => 'Cuiabá',
        'America/Rio_Branco' => 'Rio Branco',
        'America/Noronha' => 'Fernando de Noronha',
    ];

    public function adminIndex(): void
    {
        Auth::requireRole(['admin']);
        $pdo = Database::connection();
        $rows = $pdo->query(
            'SELECT e.id, e.name, e.slug, e.city, e.state, e.active, e.created_at, '
            . 'u.name AS owner_name, u.email AS owner_email, '
            . '(SELECT COUNT(*) FROM services s WHERE s.establishment_id = e.id AND s.active = 1) AS service_count, '
            . '(SELECT COUNT(*) FROM establishment_users eu WHERE eu.establishment_id = e.id AND eu.role = "employee" AND eu.active = 1) AS employee_count '
            . 'FROM establishments e JOIN users u ON u.id = e.owner_user_id '
            . 'ORDER BY e.active DESC, e.name'
        )->fetchAll();

        View::render('admin/establishments', [
            'title' => 'Estabelecimentos',
            'establishments' => $rows,
        ]);
    }

    public function create(): void
    {
        Auth::requireRole(['admin']);
        $owners = Database::connection()->query(
            'SELECT u.id, u.name, u.email FROM users u '
            . 'LEFT JOIN establishments e ON e.owner_user_id = u.id '
            . 'WHERE u.role = "owner" AND u.status = "active" AND e.id IS NULL '
            . 'ORDER BY u.name'
        )->fetchAll();

        View::render('admin/establishment_form', [
            'title' => 'Novo estabelecimento',
            'mode' => 'create',
            'establishment' => null,
            'owner' => null,
            'owners' => $owners,
            'states' => self::STATES,
            'timezones' => self::TIMEZONES,
        ]);
    }

    public function store(): void
    {
        Auth::requireRole(['admin']);
        Csrf::validate($_POST['_csrf'] ?? null);

        $data = $this->establishmentData($_POST);
        $ownerEmail = strtolower($this->limit(trim((string) ($_POST['owner_email'] ?? '')), 190));
        $ownerName = $this->limit(trim((string) ($_POST['owner_name'] ?? '')), 120);
        $ownerPhone = $this->limit(trim((string) ($_POST['owner_phone'] ?? '')), 30);
        $ownerPassword = (string) ($_POST['owner_password'] ?? '');

        if ($data['name'] === '' || !filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Informe o nome do estabelecimento e um e-mail válido para o dono.');
            redirect('/admin/estabelecimentos/novo');
        }
        if (!$this->validEstablishmentData($data)) {
            flash('error', 'Revise os dados de contato, localização e fuso horário.');
            redirect('/admin/estabelecimentos/novo');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $findOwner = $pdo->prepare('SELECT id, name, role, status FROM users WHERE email = :email LIMIT 1 FOR UPDATE');
            $findOwner->execute(['email' => $ownerEmail]);
            $existingOwner = $findOwner->fetch();

            if ($existingOwner) {
                if (($existingOwner['role'] ?? null) !== 'owner') {
                    throw new \RuntimeException('Este e-mail já pertence a uma conta com outro perfil.');
                }
                if (($existingOwner['status'] ?? null) !== 'active') {
                    throw new \RuntimeException('A conta informada para o dono está inativa.');
                }
                $ownerId = (int) $existingOwner['id'];
            } else {
                if ($ownerName === '') {
                    throw new \RuntimeException('Informe o nome do dono para criar a nova conta.');
                }
                if (strlen($ownerPassword) < 8) {
                    throw new \RuntimeException('Defina uma senha inicial de pelo menos 8 caracteres para o novo dono.');
                }
                $insertOwner = $pdo->prepare(
                    'INSERT INTO users (name, email, password_hash, role, phone) '
                    . 'VALUES (:name, :email, :password, "owner", :phone)'
                );
                $insertOwner->execute([
                    'name' => $ownerName,
                    'email' => $ownerEmail,
                    'password' => password_hash($ownerPassword, PASSWORD_DEFAULT),
                    'phone' => $ownerPhone !== '' ? $ownerPhone : null,
                ]);
                $ownerId = (int) $pdo->lastInsertId();
            }

            $alreadyOwner = $pdo->prepare('SELECT id, name FROM establishments WHERE owner_user_id = :owner LIMIT 1');
            $alreadyOwner->execute(['owner' => $ownerId]);
            $owned = $alreadyOwner->fetch();
            if ($owned) {
                throw new \RuntimeException('Este dono já possui o estabelecimento "' . $owned['name'] . '". O suporte a múltiplas unidades ainda não está habilitado.');
            }

            $slug = $this->uniqueSlug($pdo, $data['name']);
            $insert = $pdo->prepare(
                'INSERT INTO establishments '
                . '(owner_user_id, name, slug, description, phone, email, address_line, city, state, timezone, active) '
                . 'VALUES (:owner, :name, :slug, :description, :phone, :email, :address, :city, :state, :timezone, :active)'
            );
            $insert->execute([
                'owner' => $ownerId,
                'name' => $data['name'],
                'slug' => $slug,
                'description' => $data['description'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'address' => $data['address_line'],
                'city' => $data['city'],
                'state' => $data['state'],
                'timezone' => $data['timezone'],
                'active' => isset($_POST['active']) ? 1 : 0,
            ]);
            $establishmentId = (int) $pdo->lastInsertId();

            $pdo->prepare(
                'INSERT INTO establishment_users (establishment_id, user_id, role, active) '
                . 'VALUES (:establishment, :owner, "owner", 1)'
            )->execute(['establishment' => $establishmentId, 'owner' => $ownerId]);

            $hours = $pdo->prepare(
                'INSERT INTO business_hours (establishment_id, weekday, opens_at, closes_at, is_closed) '
                . 'VALUES (:establishment, :weekday, NULL, NULL, 1)'
            );
            for ($weekday = 1; $weekday <= 7; $weekday++) {
                $hours->execute(['establishment' => $establishmentId, 'weekday' => $weekday]);
            }

            $pdo->prepare('INSERT INTO booking_settings (establishment_id) VALUES (:establishment)')
                ->execute(['establishment' => $establishmentId]);
            $pdo->prepare('INSERT INTO notification_settings (establishment_id) VALUES (:establishment)')
                ->execute(['establishment' => $establishmentId]);

            $pdo->commit();
            flash('success', 'Estabelecimento criado. O dono já pode acessar o painel e concluir a configuração.');
            redirect('/admin/estabelecimentos/' . $establishmentId . '/editar');
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', $exception instanceof \RuntimeException ? $exception->getMessage() : 'Não foi possível criar o estabelecimento.');
            redirect('/admin/estabelecimentos/novo');
        }
    }

    public function adminEdit(string $id): void
    {
        Auth::requireRole(['admin']);
        $establishment = $this->findEstablishment((int) $id);
        if (!$establishment) {
            $this->notFound();
            return;
        }

        View::render('admin/establishment_form', [
            'title' => 'Editar estabelecimento',
            'mode' => 'edit',
            'establishment' => $establishment,
            'owner' => [
                'name' => $establishment['owner_name'],
                'email' => $establishment['owner_email'],
            ],
            'owners' => [],
            'states' => self::STATES,
            'timezones' => self::TIMEZONES,
        ]);
    }

    public function adminUpdate(string $id): void
    {
        Auth::requireRole(['admin']);
        Csrf::validate($_POST['_csrf'] ?? null);
        $establishmentId = (int) $id;
        if (!$this->findEstablishment($establishmentId)) {
            $this->notFound();
            return;
        }

        $data = $this->establishmentData($_POST);
        if (!$this->validEstablishmentData($data)) {
            flash('error', 'Revise os dados do estabelecimento.');
            redirect('/admin/estabelecimentos/' . $establishmentId . '/editar');
        }

        $this->updateEstablishment($establishmentId, $data, isset($_POST['active']) ? 1 : 0);
        flash('success', 'Estabelecimento atualizado.');
        redirect('/admin/estabelecimentos/' . $establishmentId . '/editar');
    }

    public function ownerEdit(): void
    {
        Auth::requireRole(['owner']);
        $establishmentId = TenantContext::requireEstablishmentId();
        $establishment = $this->findEstablishment($establishmentId);
        if (!$establishment) {
            $this->notFound();
            return;
        }

        View::render('panel/establishment', [
            'title' => 'Meu estabelecimento',
            'establishment' => $establishment,
            'states' => self::STATES,
            'timezones' => self::TIMEZONES,
        ]);
    }

    public function ownerUpdate(): void
    {
        Auth::requireRole(['owner']);
        Csrf::validate($_POST['_csrf'] ?? null);
        $establishmentId = TenantContext::requireEstablishmentId();
        $data = $this->establishmentData($_POST);

        if (!$this->validEstablishmentData($data)) {
            flash('error', 'Revise os dados do estabelecimento.');
            redirect('/painel/estabelecimento');
        }

        $this->updateEstablishment($establishmentId, $data, null);
        flash('success', 'Dados do estabelecimento atualizados.');
        redirect('/painel/estabelecimento');
    }

    private function establishmentData(array $input): array
    {
        return [
            'name' => $this->limit(trim((string) ($input['name'] ?? '')), 150),
            'description' => $this->nullable($this->limit(trim((string) ($input['description'] ?? '')), 3000)),
            'phone' => $this->nullable($this->limit(trim((string) ($input['phone'] ?? '')), 30)),
            'email' => $this->nullable(strtolower($this->limit(trim((string) ($input['email'] ?? '')), 190))),
            'address_line' => $this->nullable($this->limit(trim((string) ($input['address_line'] ?? '')), 190)),
            'city' => $this->nullable($this->limit(trim((string) ($input['city'] ?? '')), 100)),
            'state' => $this->nullable(strtoupper(trim((string) ($input['state'] ?? '')))),
            'timezone' => trim((string) ($input['timezone'] ?? 'America/Sao_Paulo')),
        ];
    }

    private function validEstablishmentData(array $data): bool
    {
        if ($data['name'] === '') {
            return false;
        }
        if ($data['email'] !== null && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        if ($data['state'] !== null && !in_array($data['state'], self::STATES, true)) {
            return false;
        }
        return array_key_exists($data['timezone'], self::TIMEZONES);
    }

    private function updateEstablishment(int $id, array $data, ?int $active): void
    {
        $sql = 'UPDATE establishments SET name = :name, description = :description, phone = :phone, email = :email, '
            . 'address_line = :address, city = :city, state = :state, timezone = :timezone';
        $params = [
            'name' => $data['name'],
            'description' => $data['description'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'address' => $data['address_line'],
            'city' => $data['city'],
            'state' => $data['state'],
            'timezone' => $data['timezone'],
            'id' => $id,
        ];
        if ($active !== null) {
            $sql .= ', active = :active';
            $params['active'] = $active;
        }
        $sql .= ' WHERE id = :id';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    private function findEstablishment(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $stmt = Database::connection()->prepare(
            'SELECT e.*, u.name AS owner_name, u.email AS owner_email '
            . 'FROM establishments e JOIN users u ON u.id = e.owner_user_id '
            . 'WHERE e.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function uniqueSlug(\PDO $pdo, string $name): string
    {
        $source = trim($name);
        if (function_exists('iconv')) {
            $source = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $source) ?: $source;
        }
        $base = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $source));
        $base = trim($base, '-');
        if ($base === '') {
            $base = 'estabelecimento';
        }
        $base = substr($base, 0, 150);

        $check = $pdo->prepare('SELECT COUNT(*) FROM establishments WHERE slug = :slug');
        $slug = $base;
        $suffix = 2;
        while (true) {
            $check->execute(['slug' => $slug]);
            if ((int) $check->fetchColumn() === 0) {
                return $slug;
            }
            $slug = substr($base, 0, 155) . '-' . $suffix;
            $suffix++;
        }
    }

    private function limit(string $value, int $length): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $length);
        }
        return substr($value, 0, $length);
    }

    private function nullable(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    private function notFound(): void
    {
        http_response_code(404);
        View::render('errors/404', ['title' => 'Estabelecimento não encontrado']);
    }
}
