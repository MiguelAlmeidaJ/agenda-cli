<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\TenantContext;
use App\Core\View;
use App\Services\GeocodingService;

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
            flash('error', 'Revise os dados de contato, endereço e fuso horário.');
            redirect('/admin/estabelecimentos/novo');
        }

        $location = $this->resolveLocation($data, null);
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
                . '(owner_user_id, name, slug, description, phone, email, postal_code, street, address_number, complement, neighborhood, address_line, city, state, latitude, longitude, geocoded_at, geocoding_provider, timezone, active) '
                . 'VALUES (:owner, :name, :slug, :description, :phone, :email, :postal_code, :street, :address_number, :complement, :neighborhood, :address, :city, :state, :latitude, :longitude, :geocoded_at, :geocoding_provider, :timezone, :active)'
            );
            $insert->execute([
                'owner' => $ownerId,
                'name' => $data['name'],
                'slug' => $slug,
                'description' => $data['description'],
                'phone' => $data['phone'],
                'email' => $data['email'],
                'postal_code' => $data['postal_code'],
                'street' => $data['street'],
                'address_number' => $data['address_number'],
                'complement' => $data['complement'],
                'neighborhood' => $data['neighborhood'],
                'address' => $data['address_line'],
                'city' => $data['city'],
                'state' => $data['state'],
                'latitude' => $location['latitude'],
                'longitude' => $location['longitude'],
                'geocoded_at' => $location['geocoded_at'],
                'geocoding_provider' => $location['provider'],
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
            $message = 'Estabelecimento criado. O dono já pode acessar o painel e concluir a configuração.';
            if ($location['attempted'] && $location['latitude'] === null) {
                $message .= ' O endereço foi salvo, mas o mapa ainda não pôde ser localizado automaticamente.';
            }
            flash('success', $message);
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
        $current = $this->findEstablishment($establishmentId);
        if (!$current) {
            $this->notFound();
            return;
        }

        $data = $this->establishmentData($_POST);
        if (!$this->validEstablishmentData($data)) {
            flash('error', 'Revise os dados do estabelecimento.');
            redirect('/admin/estabelecimentos/' . $establishmentId . '/editar');
        }

        $location = $this->resolveLocation($data, $current);
        $this->updateEstablishment($establishmentId, $data, $location, isset($_POST['active']) ? 1 : 0);
        $message = 'Estabelecimento atualizado.';
        if ($location['attempted'] && $location['latitude'] === null) {
            $message .= ' O endereço foi salvo, mas não conseguimos posicionar o mapa automaticamente.';
        }
        flash('success', $message);
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
        $current = $this->findEstablishment($establishmentId);
        if (!$current) {
            $this->notFound();
            return;
        }

        $data = $this->establishmentData($_POST);
        if (!$this->validEstablishmentData($data)) {
            flash('error', 'Revise os dados do estabelecimento. Para exibir o mapa, informe ao menos logradouro, cidade e UF.');
            redirect('/painel/estabelecimento');
        }

        $location = $this->resolveLocation($data, $current);
        $this->updateEstablishment($establishmentId, $data, $location, null);
        $message = 'Dados do estabelecimento atualizados.';
        if ($location['attempted'] && $location['latitude'] === null) {
            $message .= ' O endereço foi salvo, mas não conseguimos posicionar o mapa automaticamente.';
        }
        flash('success', $message);
        redirect('/painel/estabelecimento');
    }

    private function establishmentData(array $input): array
    {
        $postalCode = $this->normalizePostalCode((string) ($input['postal_code'] ?? ''));
        $street = $this->nullable($this->limit(trim((string) ($input['street'] ?? '')), 150));
        $number = $this->nullable($this->limit(trim((string) ($input['address_number'] ?? '')), 30));
        $complement = $this->nullable($this->limit(trim((string) ($input['complement'] ?? '')), 100));
        $neighborhood = $this->nullable($this->limit(trim((string) ($input['neighborhood'] ?? '')), 100));

        return [
            'name' => $this->limit(trim((string) ($input['name'] ?? '')), 150),
            'description' => $this->nullable($this->limit(trim((string) ($input['description'] ?? '')), 3000)),
            'phone' => $this->nullable($this->limit(trim((string) ($input['phone'] ?? '')), 30)),
            'email' => $this->nullable(strtolower($this->limit(trim((string) ($input['email'] ?? '')), 190))),
            'postal_code' => $postalCode,
            'street' => $street,
            'address_number' => $number,
            'complement' => $complement,
            'neighborhood' => $neighborhood,
            'address_line' => $this->buildAddressLine($street, $number, $complement, $neighborhood),
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
        if ($data['postal_code'] !== null && !preg_match('/^\d{5}-\d{3}$/', $data['postal_code'])) {
            return false;
        }
        if ($data['state'] !== null && !in_array($data['state'], self::STATES, true)) {
            return false;
        }
        if (!array_key_exists($data['timezone'], self::TIMEZONES)) {
            return false;
        }

        $hasAddress = $data['postal_code'] !== null
            || $data['street'] !== null
            || $data['address_number'] !== null
            || $data['complement'] !== null
            || $data['neighborhood'] !== null
            || $data['city'] !== null
            || $data['state'] !== null;

        if ($hasAddress && ($data['street'] === null || $data['city'] === null || $data['state'] === null)) {
            return false;
        }

        return true;
    }

    private function updateEstablishment(int $id, array $data, array $location, ?int $active): void
    {
        $sql = 'UPDATE establishments SET name = :name, description = :description, phone = :phone, email = :email, '
            . 'postal_code = :postal_code, street = :street, address_number = :address_number, complement = :complement, '
            . 'neighborhood = :neighborhood, address_line = :address, city = :city, state = :state, '
            . 'latitude = :latitude, longitude = :longitude, geocoded_at = :geocoded_at, geocoding_provider = :geocoding_provider, '
            . 'timezone = :timezone';
        $params = [
            'name' => $data['name'],
            'description' => $data['description'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'postal_code' => $data['postal_code'],
            'street' => $data['street'],
            'address_number' => $data['address_number'],
            'complement' => $data['complement'],
            'neighborhood' => $data['neighborhood'],
            'address' => $data['address_line'],
            'city' => $data['city'],
            'state' => $data['state'],
            'latitude' => $location['latitude'],
            'longitude' => $location['longitude'],
            'geocoded_at' => $location['geocoded_at'],
            'geocoding_provider' => $location['provider'],
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

    private function resolveLocation(array $data, ?array $current): array
    {
        if ($current !== null && !$this->addressChanged($data, $current)) {
            return [
                'latitude' => $current['latitude'] !== null ? (float) $current['latitude'] : null,
                'longitude' => $current['longitude'] !== null ? (float) $current['longitude'] : null,
                'geocoded_at' => $current['geocoded_at'] ?? null,
                'provider' => $current['geocoding_provider'] ?? null,
                'attempted' => false,
            ];
        }

        if ($data['street'] === null || $data['city'] === null || $data['state'] === null) {
            return [
                'latitude' => null,
                'longitude' => null,
                'geocoded_at' => null,
                'provider' => null,
                'attempted' => false,
            ];
        }

        try {
            $result = (new GeocodingService())->geocode($data);
        } catch (\Throwable) {
            $result = null;
        }

        return [
            'latitude' => $result['latitude'] ?? null,
            'longitude' => $result['longitude'] ?? null,
            'geocoded_at' => $result !== null ? date('Y-m-d H:i:s') : null,
            'provider' => $result['provider'] ?? null,
            'attempted' => true,
        ];
    }

    private function addressChanged(array $data, array $current): bool
    {
        foreach (['postal_code', 'street', 'address_number', 'neighborhood', 'city', 'state'] as $field) {
            $before = trim((string) ($current[$field] ?? ''));
            $after = trim((string) ($data[$field] ?? ''));
            if ($before !== $after) {
                return true;
            }
        }
        return false;
    }

    private function buildAddressLine(?string $street, ?string $number, ?string $complement, ?string $neighborhood): ?string
    {
        if ($street === null) {
            return null;
        }

        $line = $street;
        if ($number !== null) {
            $line .= ', ' . $number;
        }
        if ($complement !== null) {
            $line .= ' - ' . $complement;
        }
        if ($neighborhood !== null) {
            $line .= ' - ' . $neighborhood;
        }
        return $this->limit($line, 190);
    }

    private function normalizePostalCode(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if ($digits === '') {
            return null;
        }
        if (strlen($digits) !== 8) {
            return $this->limit(trim($value), 10);
        }
        return substr($digits, 0, 5) . '-' . substr($digits, 5, 3);
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
