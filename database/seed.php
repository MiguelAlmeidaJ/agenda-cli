<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
load_env(base_path('.env'));

use App\Core\Database;

$pdo = Database::connection();
$pdo->beginTransaction();

try {
    $users = [
        ['Administrador', 'admin@agenda.local', 'Admin@123', 'admin'],
        ['Mariana Oliveira', 'dono@agenda.local', 'Dono@123', 'owner'],
        ['Rafael Santos', 'funcionario@agenda.local', 'Func@123', 'employee'],
        ['Cliente Demo', 'cliente@agenda.local', 'Cliente@123', 'client'],
    ];

    $ids = [];
    $upsertUser = $pdo->prepare(
        'INSERT INTO users (name, email, password_hash, role) VALUES (:name, :email, :password, :role) '
        . 'ON DUPLICATE KEY UPDATE name = VALUES(name), role = VALUES(role), status = "active"'
    );
    $findUser = $pdo->prepare('SELECT id FROM users WHERE email = :email');

    foreach ($users as [$name, $email, $password, $role]) {
        $upsertUser->execute([
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
        ]);
        $findUser->execute(['email' => $email]);
        $ids[$role] = (int) $findUser->fetchColumn();
    }

    $establishment = $pdo->prepare(
        'INSERT INTO establishments (owner_user_id, name, slug, description, phone, email, address_line, city, state) '
        . 'VALUES (:owner, :name, :slug, :description, :phone, :email, :address, :city, :state) '
        . 'ON DUPLICATE KEY UPDATE owner_user_id = VALUES(owner_user_id), name = VALUES(name), description = VALUES(description), active = 1'
    );
    $establishment->execute([
        'owner' => $ids['owner'],
        'name' => 'Studio Aurora',
        'slug' => 'studio-aurora',
        'description' => 'Atendimento com hora marcada, ambiente leve e serviços personalizados.',
        'phone' => '(32) 99999-0000',
        'email' => 'contato@studioaurora.local',
        'address' => 'Rua das Flores, 120',
        'city' => 'Além Paraíba',
        'state' => 'MG',
    ]);

    $findEstablishment = $pdo->prepare('SELECT id FROM establishments WHERE slug = :slug');
    $findEstablishment->execute(['slug' => 'studio-aurora']);
    $establishmentId = (int) $findEstablishment->fetchColumn();

    $membership = $pdo->prepare(
        'INSERT INTO establishment_users (establishment_id, user_id, role) VALUES (:establishment, :user, :role) '
        . 'ON DUPLICATE KEY UPDATE role = VALUES(role), active = 1'
    );
    $membership->execute(['establishment' => $establishmentId, 'user' => $ids['owner'], 'role' => 'owner']);
    $membership->execute(['establishment' => $establishmentId, 'user' => $ids['employee'], 'role' => 'employee']);

    $customer = $pdo->prepare(
        'INSERT INTO customers (establishment_id, user_id, name, email, phone, notes) '
        . 'VALUES (:establishment, :user, :name, :email, :phone, :notes) '
        . 'ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), name = VALUES(name), phone = VALUES(phone)'
    );
    $customer->execute([
        'establishment' => $establishmentId,
        'user' => $ids['client'],
        'name' => 'Cliente Demo',
        'email' => 'cliente@agenda.local',
        'phone' => '(32) 99999-1111',
        'notes' => 'Cliente de demonstração.',
    ]);

    $services = [
        ['Corte', 'Corte personalizado com finalização.', 45, 65.00],
        ['Escova', 'Lavagem e escova com acabamento.', 60, 80.00],
        ['Hidratação', 'Tratamento e hidratação dos fios.', 60, 95.00],
    ];

    $findService = $pdo->prepare('SELECT id FROM services WHERE establishment_id = :establishment AND name = :name');
    $insertService = $pdo->prepare(
        'INSERT INTO services (establishment_id, name, description, duration_minutes, price) '
        . 'VALUES (:establishment, :name, :description, :duration, :price)'
    );
    $linkService = $pdo->prepare('INSERT IGNORE INTO employee_services (employee_user_id, service_id) VALUES (:employee, :service)');

    foreach ($services as [$name, $description, $duration, $price]) {
        $findService->execute(['establishment' => $establishmentId, 'name' => $name]);
        $serviceId = (int) ($findService->fetchColumn() ?: 0);
        if ($serviceId === 0) {
            $insertService->execute([
                'establishment' => $establishmentId,
                'name' => $name,
                'description' => $description,
                'duration' => $duration,
                'price' => $price,
            ]);
            $serviceId = (int) $pdo->lastInsertId();
        }
        $linkService->execute(['employee' => $ids['employee'], 'service' => $serviceId]);
    }

    $hours = $pdo->prepare(
        'INSERT INTO business_hours (establishment_id, weekday, opens_at, closes_at, is_closed) '
        . 'VALUES (:establishment, :weekday, :opens, :closes, :closed) '
        . 'ON DUPLICATE KEY UPDATE opens_at = VALUES(opens_at), closes_at = VALUES(closes_at), is_closed = VALUES(is_closed)'
    );

    for ($weekday = 1; $weekday <= 7; $weekday++) {
        $closed = $weekday === 7 ? 1 : 0;
        $hours->execute([
            'establishment' => $establishmentId,
            'weekday' => $weekday,
            'opens' => $closed ? null : '09:00:00',
            'closes' => $closed ? null : ($weekday === 6 ? '14:00:00' : '18:00:00'),
            'closed' => $closed,
        ]);
    }

    $pdo->commit();
    echo "Seed concluído com sucesso.\n";
} catch (Throwable $exception) {
    $pdo->rollBack();
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
