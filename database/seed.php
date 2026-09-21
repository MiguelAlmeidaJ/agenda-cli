<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Database;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

load_env(base_path('.env'));
date_default_timezone_set((string) env('APP_TIMEZONE', 'America/Sao_Paulo'));

$force = in_array('--force', $argv, true);
$environment = strtolower((string) env('APP_ENV', 'production'));
if ($environment === 'production' && !$force) {
    fwrite(STDERR, "Seed demo bloqueado em APP_ENV=production. Use --force apenas se este ambiente puder receber dados fictícios.\n");
    exit(1);
}

$pdo = Database::connection();
assertDemoSchema($pdo);

$zone = new DateTimeZone('America/Sao_Paulo');
$now = new DateTimeImmutable('now', $zone);
$today = $now->setTime(0, 0, 0);

$demoSlugs = ['studio-aurora', 'clinica-horizonte-demo', 'espaco-essenza-demo'];
$demoEmails = [
    'admin@agenda.local',
    'dono@agenda.local',
    'funcionario@agenda.local',
    'camila@agenda.local',
    'cliente@agenda.local',
    'cliente2@agenda.local',
    'cliente3@agenda.local',
    'dono.horizonte@agenda.local',
    'funcionario.horizonte@agenda.local',
    'dono.essenza@agenda.local',
    'funcionario.essenza@agenda.local',
];

$pdo->beginTransaction();

try {
    clearDemoData($pdo, $demoSlugs, $demoEmails);

    $users = [];
    $users['admin'] = createUser($pdo, 'Administrador Demo', 'admin@agenda.local', 'Admin@123', 'admin', '(32) 99990-0001', 'Administrador da plataforma de demonstração.');
    $users['owner'] = createUser($pdo, 'Mariana Oliveira', 'dono@agenda.local', 'Dono@123', 'owner', '(32) 99990-1001', 'Gestora do Studio Aurora.');
    $users['rafael'] = createUser($pdo, 'Rafael Santos', 'funcionario@agenda.local', 'Func@123', 'employee', '(32) 99990-1002', 'Cabeleireiro e especialista em tratamentos capilares.');
    $users['camila'] = createUser($pdo, 'Camila Nogueira', 'camila@agenda.local', 'Func@123', 'employee', '(32) 99990-1003', 'Especialista em manicure e design de sobrancelhas.');
    $users['client1'] = createUser($pdo, 'Cliente Demo', 'cliente@agenda.local', 'Cliente@123', 'client', '(32) 99990-2001', null);
    $users['client2'] = createUser($pdo, 'Beatriz Lima', 'cliente2@agenda.local', 'Cliente@123', 'client', '(32) 99990-2002', null);
    $users['client3'] = createUser($pdo, 'João Pedro Alves', 'cliente3@agenda.local', 'Cliente@123', 'client', '(32) 99990-2003', null);

    $users['owner2'] = createUser($pdo, 'Dr. Henrique Duarte', 'dono.horizonte@agenda.local', 'Dono@123', 'owner', '(32) 99990-3001', 'Responsável pela Clínica Horizonte.');
    $users['employee2'] = createUser($pdo, 'Paula Menezes', 'funcionario.horizonte@agenda.local', 'Func@123', 'employee', '(32) 99990-3002', 'Fisioterapeuta.');
    $users['owner3'] = createUser($pdo, 'Larissa Monteiro', 'dono.essenza@agenda.local', 'Dono@123', 'owner', '(24) 99990-4001', 'Gestora do Espaço Essenza.');
    $users['employee3'] = createUser($pdo, 'Renata Campos', 'funcionario.essenza@agenda.local', 'Func@123', 'employee', '(24) 99990-4002', 'Massoterapeuta e esteticista.');

    $studio = createEstablishment($pdo, [
        'owner' => $users['owner'],
        'name' => 'Studio Aurora',
        'slug' => 'studio-aurora',
        'description' => 'Beleza e autocuidado com atendimento personalizado, agenda organizada e ambiente acolhedor.',
        'logo' => '/assets/demo/studio-aurora-logo.svg',
        'cover' => '/assets/demo/studio-aurora-cover.svg',
        'phone' => '(32) 3333-1200',
        'email' => 'contato@studioaurora.local',
        'postal_code' => '36660-000',
        'street' => 'Rua das Flores',
        'number' => '120',
        'neighborhood' => 'Centro',
        'address_line' => 'Rua das Flores, 120 - Centro',
        'city' => 'Além Paraíba',
        'state' => 'MG',
        'latitude' => -21.8875000,
        'longitude' => -42.7045000,
    ]);
    $clinic = createEstablishment($pdo, [
        'owner' => $users['owner2'],
        'name' => 'Clínica Horizonte',
        'slug' => 'clinica-horizonte-demo',
        'description' => 'Fisioterapia, mobilidade e cuidado individualizado para rotina, esporte e reabilitação.',
        'logo' => '/assets/demo/clinica-horizonte-logo.svg',
        'cover' => '/assets/demo/clinica-horizonte-cover.svg',
        'phone' => '(32) 3333-2200',
        'email' => 'contato@clinicahorizonte.local',
        'postal_code' => '36010-000',
        'street' => 'Avenida Central',
        'number' => '450',
        'neighborhood' => 'Centro',
        'address_line' => 'Avenida Central, 450 - Centro',
        'city' => 'Juiz de Fora',
        'state' => 'MG',
        'latitude' => -21.7600000,
        'longitude' => -43.3490000,
    ]);
    $essenza = createEstablishment($pdo, [
        'owner' => $users['owner3'],
        'name' => 'Espaço Essenza',
        'slug' => 'espaco-essenza-demo',
        'description' => 'Massagens e estética para desacelerar, cuidar do corpo e renovar a rotina.',
        'logo' => '/assets/demo/espaco-essenza-logo.svg',
        'cover' => '/assets/demo/espaco-essenza-cover.svg',
        'phone' => '(24) 3333-3200',
        'email' => 'contato@essenza.local',
        'postal_code' => '25610-000',
        'street' => 'Rua Imperial',
        'number' => '88',
        'neighborhood' => 'Centro',
        'address_line' => 'Rua Imperial, 88 - Centro',
        'city' => 'Petrópolis',
        'state' => 'RJ',
        'latitude' => -22.5050000,
        'longitude' => -43.1780000,
    ]);

    addMembership($pdo, $studio, $users['owner'], 'owner');
    addMembership($pdo, $studio, $users['rafael'], 'employee');
    addMembership($pdo, $studio, $users['camila'], 'employee');
    addMembership($pdo, $clinic, $users['owner2'], 'owner');
    addMembership($pdo, $clinic, $users['employee2'], 'employee');
    addMembership($pdo, $essenza, $users['owner3'], 'owner');
    addMembership($pdo, $essenza, $users['employee3'], 'employee');

    $studioServices = [];
    $studioServices['corte'] = createService($pdo, $studio, 'Corte & finalização', 'Corte personalizado com lavagem e finalização.', '/assets/demo/service-hair.svg', 45, 72.00);
    $studioServices['escova'] = createService($pdo, $studio, 'Escova', 'Lavagem, proteção térmica e escova com acabamento.', '/assets/demo/service-hair.svg', 60, 85.00);
    $studioServices['hidratacao'] = createService($pdo, $studio, 'Hidratação premium', 'Tratamento intensivo com diagnóstico dos fios.', '/assets/demo/service-care.svg', 60, 110.00);
    $studioServices['manicure'] = createService($pdo, $studio, 'Manicure', 'Cuidado completo das unhas com esmaltação.', '/assets/demo/service-nails.svg', 50, 48.00);
    $studioServices['sobrancelha'] = createService($pdo, $studio, 'Design de sobrancelhas', 'Mapeamento e design personalizado.', '/assets/demo/service-brows.svg', 30, 42.00);

    foreach (['corte', 'escova', 'hidratacao'] as $key) {
        linkService($pdo, $users['rafael'], $studioServices[$key]);
        linkService($pdo, $users['owner'], $studioServices[$key]);
    }
    foreach (['manicure', 'sobrancelha'] as $key) {
        linkService($pdo, $users['camila'], $studioServices[$key]);
    }

    $clinicServices = [];
    $clinicServices['fisio'] = createService($pdo, $clinic, 'Fisioterapia individual', 'Sessão individual de fisioterapia e reabilitação.', '/assets/demo/service-clinic.svg', 50, 130.00);
    $clinicServices['pilates'] = createService($pdo, $clinic, 'Pilates individual', 'Sessão individual focada em mobilidade e fortalecimento.', '/assets/demo/service-clinic.svg', 50, 120.00);
    $clinicServices['avaliacao'] = createService($pdo, $clinic, 'Avaliação postural', 'Avaliação inicial com orientações e plano terapêutico.', '/assets/demo/service-clinic.svg', 60, 160.00);
    foreach ($clinicServices as $serviceId) {
        linkService($pdo, $users['employee2'], $serviceId);
        linkService($pdo, $users['owner2'], $serviceId);
    }

    $essenzaServices = [];
    $essenzaServices['massagem'] = createService($pdo, $essenza, 'Massagem relaxante', 'Sessão relaxante de corpo inteiro.', '/assets/demo/service-spa.svg', 60, 145.00);
    $essenzaServices['pele'] = createService($pdo, $essenza, 'Limpeza de pele', 'Higienização profunda e cuidados faciais.', '/assets/demo/service-spa.svg', 75, 175.00);
    $essenzaServices['drenagem'] = createService($pdo, $essenza, 'Drenagem linfática', 'Sessão manual de drenagem linfática.', '/assets/demo/service-spa.svg', 60, 150.00);
    foreach ($essenzaServices as $serviceId) {
        linkService($pdo, $users['employee3'], $serviceId);
        linkService($pdo, $users['owner3'], $serviceId);
    }

    seedBusinessHours($pdo, $studio, [
        1 => [['08:00', '12:00'], ['14:00', '19:00']],
        2 => [['08:00', '12:00'], ['14:00', '19:00']],
        3 => [['08:00', '12:00'], ['14:00', '19:00']],
        4 => [['08:00', '12:00'], ['14:00', '19:00']],
        5 => [['08:00', '12:00'], ['14:00', '19:00']],
        6 => [['08:00', '13:00']],
        7 => [],
    ]);
    seedBusinessHours($pdo, $clinic, [
        1 => [['07:00', '12:00'], ['13:00', '18:00']],
        2 => [['07:00', '12:00'], ['13:00', '18:00']],
        3 => [['07:00', '12:00'], ['13:00', '18:00']],
        4 => [['07:00', '12:00'], ['13:00', '18:00']],
        5 => [['07:00', '12:00'], ['13:00', '17:00']],
        6 => [],
        7 => [],
    ]);
    seedBusinessHours($pdo, $essenza, [
        1 => [],
        2 => [['10:00', '19:00']],
        3 => [['10:00', '19:00']],
        4 => [['10:00', '19:00']],
        5 => [['10:00', '20:00']],
        6 => [['09:00', '17:00']],
        7 => [],
    ]);

    seedProviderHours($pdo, $studio, $users['camila'], [
        1 => [['09:00', '12:00'], ['14:00', '18:00']],
        2 => [['09:00', '12:00'], ['14:00', '18:00']],
        3 => [['09:00', '12:00'], ['14:00', '18:00']],
        4 => [['09:00', '12:00'], ['14:00', '18:00']],
        5 => [['09:00', '12:00'], ['14:00', '18:00']],
        6 => [['09:00', '13:00']],
        7 => [],
    ]);

    seedBookingSettings($pdo, $studio, 60, 120, 15, 120, 120, 30);
    seedBookingSettings($pdo, $clinic, 120, 90, 10, 240, 240, 30);
    seedBookingSettings($pdo, $essenza, 180, 90, 15, 360, 360, 45);

    seedNotificationSettings($pdo, $studio, true);
    seedNotificationSettings($pdo, $clinic, false);
    seedNotificationSettings($pdo, $essenza, false);

    $customers = [];
    $customers[] = createCustomer($pdo, $studio, $users['client1'], 'Cliente Demo', 'cliente@agenda.local', '(32) 99990-2001', 'Prefere atendimento no período da tarde.');
    $customers[] = createCustomer($pdo, $studio, $users['client2'], 'Beatriz Lima', 'cliente2@agenda.local', '(32) 99990-2002', 'Cliente recorrente. Gosta de horários no início da manhã.');
    $customers[] = createCustomer($pdo, $studio, $users['client3'], 'João Pedro Alves', 'cliente3@agenda.local', '(32) 99990-2003', 'Contato preferencial por WhatsApp.');
    $fakeNames = [
        ['Ana Clara Ribeiro', 'ana.ribeiro@demo.local', '(32) 98881-1001'],
        ['Carolina Souza', 'carolina.souza@demo.local', '(32) 98881-1002'],
        ['Fernanda Martins', 'fernanda.martins@demo.local', '(32) 98881-1003'],
        ['Gabriel Moreira', 'gabriel.moreira@demo.local', '(32) 98881-1004'],
        ['Helena Costa', 'helena.costa@demo.local', '(32) 98881-1005'],
        ['Isabela Rocha', 'isabela.rocha@demo.local', '(32) 98881-1006'],
        ['Lucas Fernandes', 'lucas.fernandes@demo.local', '(32) 98881-1007'],
        ['Marcos Vinícius', 'marcos.vinicius@demo.local', '(32) 98881-1008'],
        ['Natália Azevedo', 'natalia.azevedo@demo.local', '(32) 98881-1009'],
        ['Patrícia Gomes', 'patricia.gomes@demo.local', '(32) 98881-1010'],
        ['Renan Teixeira', 'renan.teixeira@demo.local', '(32) 98881-1011'],
        ['Sofia Andrade', 'sofia.andrade@demo.local', '(32) 98881-1012'],
    ];
    foreach ($fakeNames as [$name, $email, $phone]) {
        $customers[] = createCustomer($pdo, $studio, null, $name, $email, $phone, null);
    }

    $clinicCustomer = createCustomer($pdo, $clinic, null, 'Marina Valente', 'marina.valente@demo.local', '(32) 97771-2001', null);
    $essenzaCustomer = createCustomer($pdo, $essenza, null, 'Lívia Barros', 'livia.barros@demo.local', '(24) 97771-3001', null);

    $specialDate = nextBusinessDay($today->modify('+12 days'));
    $pdo->prepare(
        'INSERT INTO special_hours (establishment_id,special_date,name,source,is_closed,opens_at,closes_at) '
        . 'VALUES (:establishment,:date,:name,"custom",1,NULL,NULL)'
    )->execute([
        'establishment' => $studio,
        'date' => $specialDate->format('Y-m-d'),
        'name' => 'Treinamento interno (demo)',
    ]);

    $blockedDate = nextBusinessDay($today->modify('+4 days'));
    $pdo->prepare(
        'INSERT INTO blocked_periods (establishment_id,employee_user_id,starts_at,ends_at,reason) '
        . 'VALUES (:establishment,:employee,:starts,:ends,:reason)'
    )->execute([
        'establishment' => $studio,
        'employee' => $users['rafael'],
        'starts' => $blockedDate->format('Y-m-d') . ' 16:00:00',
        'ends' => $blockedDate->format('Y-m-d') . ' 17:00:00',
        'reason' => 'Compromisso pessoal (demo)',
    ]);

    $vacationStart = nextBusinessDay($today->modify('+45 days'));
    $pdo->prepare(
        'INSERT INTO provider_absences '
        . '(establishment_id,user_id,kind,starts_on,ends_on,weekday,all_day,starts_at,ends_at,reason) '
        . 'VALUES (:establishment,:user,"date_range",:starts_on,:ends_on,NULL,1,NULL,NULL,:reason)'
    )->execute([
        'establishment' => $studio,
        'user' => $users['camila'],
        'starts_on' => $vacationStart->format('Y-m-d'),
        'ends_on' => $vacationStart->modify('+5 days')->format('Y-m-d'),
        'reason' => 'Férias (demo)',
    ]);
    $pdo->prepare(
        'INSERT INTO provider_absences '
        . '(establishment_id,user_id,kind,starts_on,ends_on,weekday,all_day,starts_at,ends_at,reason) '
        . 'VALUES (:establishment,:user,"weekly",:starts_on,NULL,2,0,"12:00:00","14:00:00",:reason)'
    )->execute([
        'establishment' => $studio,
        'user' => $users['rafael'],
        'starts_on' => $today->format('Y-m-d'),
        'reason' => 'Intervalo fixo de terça-feira (demo)',
    ]);

    $appointmentIds = [];
    $serviceCycle = array_values($studioServices);
    $providerByService = [
        $studioServices['corte'] => $users['rafael'],
        $studioServices['escova'] => $users['rafael'],
        $studioServices['hidratacao'] => $users['rafael'],
        $studioServices['manicure'] => $users['camila'],
        $studioServices['sobrancelha'] => $users['camila'],
    ];
    $priceByService = [
        $studioServices['corte'] => 72.00,
        $studioServices['escova'] => 85.00,
        $studioServices['hidratacao'] => 110.00,
        $studioServices['manicure'] => 48.00,
        $studioServices['sobrancelha'] => 42.00,
    ];
    $durationByService = [
        $studioServices['corte'] => 45,
        $studioServices['escova'] => 60,
        $studioServices['hidratacao'] => 60,
        $studioServices['manicure'] => 50,
        $studioServices['sobrancelha'] => 30,
    ];

    $historyMonths = 6;
    for ($monthOffset = $historyMonths - 1; $monthOffset >= 0; $monthOffset--) {
        $monthBase = $today->modify('first day of this month')->modify('-' . $monthOffset . ' months');
        for ($i = 0; $i < 8; $i++) {
            $day = nextBusinessDay($monthBase->modify('+' . (2 + ($i * 3)) . ' days'));
            if ($day > $today) {
                continue;
            }
            $serviceId = $serviceCycle[($i + $monthOffset) % count($serviceCycle)];
            $customerId = $customers[($i + ($monthOffset * 2)) % count($customers)];
            $providerId = $providerByService[$serviceId];
            $time = $i % 2 === 0 ? '09:00' : '14:30';
            $status = 'completed';
            if ($i === 6 && $monthOffset % 2 === 0) {
                $status = 'cancelled';
            } elseif ($i === 7 && $monthOffset % 3 === 0) {
                $status = 'no_show';
            }
            $appointmentIds[] = createAppointment(
                $pdo,
                $studio,
                $serviceId,
                $providerId,
                null,
                $customerId,
                $users['owner'],
                $day,
                $time,
                $durationByService[$serviceId],
                $status,
                $priceByService[$serviceId],
                'Atendimento histórico de demonstração.',
                $status === 'completed' ? 'confirmed' : 'pending'
            );
        }
    }

    $futurePlan = [
        [2, '09:00', 'corte', 0, 'confirmed', 'confirmed'],
        [2, '10:30', 'manicure', 1, 'confirmed', 'pending'],
        [3, '14:00', 'hidratacao', 2, 'confirmed', 'confirmed'],
        [4, '15:30', 'sobrancelha', 3, 'pending', 'pending'],
        [5, '09:00', 'escova', 4, 'confirmed', 'pending'],
        [7, '14:30', 'manicure', 5, 'confirmed', 'confirmed'],
        [8, '16:00', 'corte', 6, 'confirmed', 'pending'],
    ];
    $futureAppointmentIds = [];
    foreach ($futurePlan as [$offset, $time, $serviceKey, $customerIndex, $status, $attendance]) {
        $day = nextBusinessDay($today->modify('+' . $offset . ' days'));
        $serviceId = $studioServices[$serviceKey];
        $futureAppointmentIds[] = createAppointment(
            $pdo,
            $studio,
            $serviceId,
            $providerByService[$serviceId],
            null,
            $customers[$customerIndex],
            $users['owner'],
            $day,
            $time,
            $durationByService[$serviceId],
            $status,
            $priceByService[$serviceId],
            'Próximo atendimento de demonstração.',
            $attendance
        );
    }

    $seriesStart = nextWeekday($today->modify('+3 days'), 4);
    $seriesStmt = $pdo->prepare(
        'INSERT INTO appointment_series '
        . '(establishment_id,customer_id,service_id,employee_user_id,created_by_user_id,frequency,interval_weeks,occurrences_count,starts_on,starts_at) '
        . 'VALUES (:establishment,:customer,:service,:employee,:creator,"weekly",1,4,:starts_on,"15:00:00")'
    );
    $seriesStmt->execute([
        'establishment' => $studio,
        'customer' => $customers[0],
        'service' => $studioServices['hidratacao'],
        'employee' => $users['rafael'],
        'creator' => $users['owner'],
        'starts_on' => $seriesStart->format('Y-m-d'),
    ]);
    $seriesId = (int) $pdo->lastInsertId();
    for ($i = 0; $i < 4; $i++) {
        $day = $seriesStart->modify('+' . $i . ' weeks');
        createAppointment(
            $pdo,
            $studio,
            $studioServices['hidratacao'],
            $users['rafael'],
            $users['client1'],
            $customers[0],
            $users['owner'],
            $day,
            '15:00',
            60,
            'confirmed',
            110.00,
            'Série semanal de hidratação (demo).',
            $i === 0 ? 'confirmed' : 'pending',
            $seriesId,
            $i + 1
        );
    }

    createAppointment(
        $pdo,
        $clinic,
        $clinicServices['fisio'],
        $users['employee2'],
        null,
        $clinicCustomer,
        $users['owner2'],
        nextBusinessDay($today->modify('+3 days')),
        '09:00',
        50,
        'confirmed',
        130.00,
        'Agendamento de demonstração da Clínica Horizonte.',
        'pending'
    );
    createAppointment(
        $pdo,
        $essenza,
        $essenzaServices['massagem'],
        $users['employee3'],
        null,
        $essenzaCustomer,
        $users['owner3'],
        nextWeekday($today->modify('+2 days'), 5),
        '14:00',
        60,
        'confirmed',
        145.00,
        'Agendamento de demonstração do Espaço Essenza.',
        'pending'
    );

    $waitDate = nextBusinessDay($today->modify('+6 days'));
    $waitlistStmt = $pdo->prepare(
        'INSERT INTO waitlist_entries '
        . '(establishment_id,service_id,customer_id,preferred_employee_user_id,desired_date,time_period,status,notes) '
        . 'VALUES (:establishment,:service,:customer,:employee,:date,:period,:status,:notes)'
    );
    $waitlistStmt->execute([
        'establishment' => $studio,
        'service' => $studioServices['corte'],
        'customer' => $customers[7],
        'employee' => $users['rafael'],
        'date' => $waitDate->format('Y-m-d'),
        'period' => 'afternoon',
        'status' => 'waiting',
        'notes' => 'Aceita qualquer horário depois das 14h.',
    ]);
    $waitEntry1 = (int) $pdo->lastInsertId();

    $waitlistStmt->execute([
        'establishment' => $studio,
        'service' => $studioServices['manicure'],
        'customer' => $customers[4],
        'employee' => $users['camila'],
        'date' => nextBusinessDay($today->modify('+8 days'))->format('Y-m-d'),
        'period' => 'morning',
        'status' => 'notified',
        'notes' => 'Preferência pela manhã.',
    ]);
    $waitEntry2 = (int) $pdo->lastInsertId();

    $matchSlot = nextBusinessDay($today->modify('+8 days'))->format('Y-m-d') . ' 10:00:00';
    $pdo->prepare(
        'INSERT INTO waitlist_matches '
        . '(establishment_id,waitlist_entry_id,employee_user_id,slot_start,status,offer_token_hash,offer_expires_at) '
        . 'VALUES (:establishment,:entry,:employee,:slot,"notified",:token,:expires)'
    )->execute([
        'establishment' => $studio,
        'entry' => $waitEntry2,
        'employee' => $users['camila'],
        'slot' => $matchSlot,
        'token' => hash('sha256', 'demo-waitlist-offer'),
        'expires' => $today->modify('+1 hour')->format('Y-m-d H:i:s'),
    ]);

    seedNotificationOutbox($pdo, $studio, $customers, $futureAppointmentIds, $today);
    seedAppointmentEventsForDemo($pdo, $studio, $futureAppointmentIds, $users['owner']);

    $pdo->commit();

    echo "\nAgendaCli demo carregado com sucesso.\n";
    echo "Estabelecimento principal: Studio Aurora\n";
    echo "URL pública: " . rtrim((string) env('APP_URL', ''), '/') . "/estabelecimentos/studio-aurora\n\n";
    echo "Logins de teste:\n";
    echo "  Admin:       admin@agenda.local / Admin@123\n";
    echo "  Dono:        dono@agenda.local / Dono@123\n";
    echo "  Funcionário: funcionario@agenda.local / Func@123\n";
    echo "  Cliente:     cliente@agenda.local / Cliente@123\n\n";
    echo "O seed é idempotente para os slugs/e-mails demo: executar novamente recria somente a massa de demonstração.\n";
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Falha no seed demo: " . $exception->getMessage() . PHP_EOL);
    exit(1);
}

function assertDemoSchema(PDO $pdo): void
{
    $requiredTables = [
        'provider_absences',
        'appointment_series',
        'appointment_attendance_tokens',
        'business_hour_ranges',
        'provider_hour_ranges',
        'waitlist_matches',
        'notification_outbox',
    ];

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=:table'
    );
    $missing = [];
    foreach ($requiredTables as $table) {
        $stmt->execute(['table' => $table]);
        if ((int) $stmt->fetchColumn() === 0) {
            $missing[] = $table;
        }
    }

    if ($missing !== []) {
        throw new RuntimeException(
            'Banco desatualizado. Aplique todas as migrations antes do seed. Tabelas ausentes: ' . implode(', ', $missing)
        );
    }
}

function clearDemoData(PDO $pdo, array $slugs, array $emails): void
{
    $placeholders = implode(',', array_fill(0, count($slugs), '?'));
    $stmt = $pdo->prepare("SELECT id FROM establishments WHERE slug IN ($placeholders)");
    $stmt->execute($slugs);
    $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    foreach ($ids as $id) {
        $tables = [
            'notification_outbox',
            'appointment_attendance_tokens',
            'appointment_events',
            'waitlist_matches',
            'waitlist_entries',
            'appointments',
            'appointment_series',
            'provider_hour_ranges',
            'provider_hours',
            'provider_absences',
            'blocked_periods',
            'special_hours',
            'business_hour_ranges',
            'business_hours',
            'notification_settings',
            'booking_settings',
        ];
        foreach ($tables as $table) {
            $column = in_array($table, ['appointment_attendance_tokens', 'appointment_events', 'waitlist_matches', 'waitlist_entries', 'appointments', 'appointment_series', 'provider_hour_ranges', 'provider_hours', 'provider_absences', 'blocked_periods', 'special_hours', 'business_hour_ranges', 'business_hours', 'notification_settings', 'booking_settings', 'notification_outbox'], true)
                ? 'establishment_id'
                : 'establishment_id';
            $pdo->prepare("DELETE FROM {$table} WHERE {$column}=?")->execute([$id]);
        }
        $pdo->prepare('DELETE es FROM employee_services es JOIN services s ON s.id=es.service_id WHERE s.establishment_id=?')->execute([$id]);
        $pdo->prepare('DELETE FROM services WHERE establishment_id=?')->execute([$id]);
        $pdo->prepare('DELETE FROM customers WHERE establishment_id=?')->execute([$id]);
        $pdo->prepare('DELETE FROM establishment_users WHERE establishment_id=?')->execute([$id]);
        $pdo->prepare('DELETE FROM establishments WHERE id=?')->execute([$id]);
    }

    if ($emails !== []) {
        $emailPlaceholders = implode(',', array_fill(0, count($emails), '?'));
        $pdo->prepare("DELETE FROM users WHERE email IN ($emailPlaceholders)")->execute($emails);
    }
}

function createUser(PDO $pdo, string $name, string $email, string $password, string $role, ?string $phone, ?string $bio): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO users (name,email,password_hash,role,phone,city,state,bio,status) '
        . 'VALUES (:name,:email,:password,:role,:phone,"Além Paraíba","MG",:bio,"active")'
    );
    $stmt->execute([
        'name' => $name,
        'email' => strtolower($email),
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'role' => $role,
        'phone' => $phone,
        'bio' => $bio,
    ]);
    return (int) $pdo->lastInsertId();
}

function createEstablishment(PDO $pdo, array $data): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO establishments '
        . '(owner_user_id,name,slug,description,logo_url,cover_url,phone,email,postal_code,street,address_number,neighborhood,address_line,city,state,latitude,longitude,geocoded_at,geocoding_provider,timezone,active) '
        . 'VALUES (:owner,:name,:slug,:description,:logo,:cover,:phone,:email,:postal_code,:street,:number,:neighborhood,:address_line,:city,:state,:latitude,:longitude,:geocoded_at,"demo","America/Sao_Paulo",1)'
    );
    $stmt->execute([
        'owner' => $data['owner'],
        'name' => $data['name'],
        'slug' => $data['slug'],
        'description' => $data['description'],
        'logo' => $data['logo'],
        'cover' => $data['cover'],
        'phone' => $data['phone'],
        'email' => $data['email'],
        'postal_code' => $data['postal_code'],
        'street' => $data['street'],
        'number' => $data['number'],
        'neighborhood' => $data['neighborhood'],
        'address_line' => $data['address_line'],
        'city' => $data['city'],
        'state' => $data['state'],
        'latitude' => $data['latitude'],
        'longitude' => $data['longitude'],
        'geocoded_at' => date('Y-m-d H:i:s'),
    ]);
    return (int) $pdo->lastInsertId();
}

function addMembership(PDO $pdo, int $establishmentId, int $userId, string $role): void
{
    $pdo->prepare(
        'INSERT INTO establishment_users (establishment_id,user_id,role,active) VALUES (?,?,?,1)'
    )->execute([$establishmentId, $userId, $role]);
}

function createService(PDO $pdo, int $establishmentId, string $name, string $description, string $image, int $duration, float $price): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO services (establishment_id,name,description,image_url,duration_minutes,price,active) '
        . 'VALUES (:establishment,:name,:description,:image,:duration,:price,1)'
    );
    $stmt->execute([
        'establishment' => $establishmentId,
        'name' => $name,
        'description' => $description,
        'image' => $image,
        'duration' => $duration,
        'price' => number_format($price, 2, '.', ''),
    ]);
    return (int) $pdo->lastInsertId();
}

function linkService(PDO $pdo, int $employeeId, int $serviceId): void
{
    $pdo->prepare('INSERT INTO employee_services (employee_user_id,service_id) VALUES (?,?)')
        ->execute([$employeeId, $serviceId]);
}

function seedBusinessHours(PDO $pdo, int $establishmentId, array $schedule): void
{
    $hours = $pdo->prepare(
        'INSERT INTO business_hours (establishment_id,weekday,opens_at,closes_at,is_closed) VALUES (?,?,?,?,?)'
    );
    $range = $pdo->prepare(
        'INSERT INTO business_hour_ranges (establishment_id,weekday,opens_at,closes_at,sort_order) VALUES (?,?,?,?,?)'
    );

    for ($weekday = 1; $weekday <= 7; $weekday++) {
        $ranges = $schedule[$weekday] ?? [];
        $closed = $ranges === [];
        $hours->execute([
            $establishmentId,
            $weekday,
            $closed ? null : $ranges[0][0] . ':00',
            $closed ? null : $ranges[count($ranges) - 1][1] . ':00',
            $closed ? 1 : 0,
        ]);
        foreach ($ranges as $index => [$opens, $closes]) {
            $range->execute([$establishmentId, $weekday, $opens . ':00', $closes . ':00', $index]);
        }
    }
}

function seedProviderHours(PDO $pdo, int $establishmentId, int $userId, array $schedule): void
{
    $hours = $pdo->prepare(
        'INSERT INTO provider_hours (establishment_id,user_id,weekday,opens_at,closes_at,is_off) VALUES (?,?,?,?,?,?)'
    );
    $range = $pdo->prepare(
        'INSERT INTO provider_hour_ranges (establishment_id,user_id,weekday,opens_at,closes_at,sort_order) VALUES (?,?,?,?,?,?)'
    );

    for ($weekday = 1; $weekday <= 7; $weekday++) {
        $ranges = $schedule[$weekday] ?? [];
        $off = $ranges === [];
        $hours->execute([
            $establishmentId,
            $userId,
            $weekday,
            $off ? null : $ranges[0][0] . ':00',
            $off ? null : $ranges[count($ranges) - 1][1] . ':00',
            $off ? 1 : 0,
        ]);
        foreach ($ranges as $index => [$opens, $closes]) {
            $range->execute([$establishmentId, $userId, $weekday, $opens . ':00', $closes . ':00', $index]);
        }
    }
}

function seedBookingSettings(PDO $pdo, int $establishmentId, int $minNotice, int $advanceDays, int $buffer, int $cancelNotice, int $rescheduleNotice, int $waitlistMinutes): void
{
    $pdo->prepare(
        'INSERT INTO booking_settings '
        . '(establishment_id,min_notice_minutes,max_advance_days,buffer_minutes,cancellation_notice_minutes,reschedule_notice_minutes,waitlist_offer_minutes,allow_waitlist,cancellation_policy) '
        . 'VALUES (?,?,?,?,?,?,?,1,?)'
    )->execute([
        $establishmentId,
        $minNotice,
        $advanceDays,
        $buffer,
        $cancelNotice,
        $rescheduleNotice,
        $waitlistMinutes,
        'Cancelamentos e reagendamentos online respeitam o prazo configurado.',
    ]);
}

function seedNotificationSettings(PDO $pdo, int $establishmentId, bool $manualEnabled): void
{
    $pdo->prepare(
        'INSERT INTO notification_settings '
        . '(establishment_id,whatsapp_enabled,provider,confirmation_enabled,cancellation_enabled,reminder_24h_enabled,reminder_2h_enabled,waitlist_enabled) '
        . 'VALUES (?,?,"manual",1,1,1,1,1)'
    )->execute([$establishmentId, $manualEnabled ? 1 : 0]);
}

function createCustomer(PDO $pdo, int $establishmentId, ?int $userId, string $name, string $email, string $phone, ?string $notes): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO customers (establishment_id,user_id,name,email,phone,notes) VALUES (?,?,?,?,?,?)'
    );
    $stmt->execute([$establishmentId, $userId, $name, $email, $phone, $notes]);
    return (int) $pdo->lastInsertId();
}

function createAppointment(
    PDO $pdo,
    int $establishmentId,
    int $serviceId,
    int $employeeId,
    ?int $clientUserId,
    int $customerId,
    int $creatorId,
    DateTimeImmutable $day,
    string $time,
    int $duration,
    string $status,
    float $price,
    ?string $notes,
    string $attendance = 'pending',
    ?int $seriesId = null,
    ?int $seriesPosition = null
): int {
    $start = new DateTimeImmutable($day->format('Y-m-d') . ' ' . $time . ':00', $day->getTimezone());
    $end = $start->add(new DateInterval('PT' . $duration . 'M'));
    $respondedAt = $attendance === 'confirmed' ? $start->modify('-1 day')->format('Y-m-d H:i:s') : null;

    $stmt = $pdo->prepare(
        'INSERT INTO appointments '
        . '(establishment_id,service_id,employee_user_id,client_user_id,customer_id,series_id,series_position,created_by_user_id,starts_at,ends_at,status,attendance_response,attendance_responded_at,price,notes) '
        . 'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([
        $establishmentId,
        $serviceId,
        $employeeId,
        $clientUserId,
        $customerId,
        $seriesId,
        $seriesPosition,
        $creatorId,
        $start->format('Y-m-d H:i:s'),
        $end->format('Y-m-d H:i:s'),
        $status,
        $attendance,
        $respondedAt,
        number_format($price, 2, '.', ''),
        $notes,
    ]);
    $id = (int) $pdo->lastInsertId();

    $pdo->prepare(
        'INSERT INTO appointment_events (appointment_id,establishment_id,user_id,event_type,to_status,details) '
        . 'VALUES (?,?,?,"created",?,?)'
    )->execute([
        $id,
        $establishmentId,
        $creatorId,
        $status,
        $seriesId !== null ? 'Ocorrência de série criada pelo seed demo.' : 'Agendamento criado pelo seed demo.',
    ]);

    if ($attendance === 'confirmed') {
        $pdo->prepare(
            'INSERT INTO appointment_events (appointment_id,establishment_id,user_id,event_type,details) '
            . 'VALUES (?,?,NULL,"attendance_confirmed","Presença confirmada no cenário de demonstração.")'
        )->execute([$id, $establishmentId]);
    }

    return $id;
}

function seedNotificationOutbox(PDO $pdo, int $establishmentId, array $customers, array $appointmentIds, DateTimeImmutable $today): void
{
    if ($appointmentIds === []) {
        return;
    }

    $rows = [
        ['appointment_confirmation', 'pending', 0, null],
        ['reminder_24h', 'pending', 1, null],
        ['reminder_2h', 'sent', 2, null],
        ['appointment_confirmation', 'failed', 3, 'Falha simulada do provedor manual.'],
        ['appointment_cancelled', 'cancelled', 4, null],
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO notification_outbox '
        . '(establishment_id,customer_id,appointment_id,event_type,recipient,message,status,scheduled_at,sent_at,attempts,last_error,dedupe_key) '
        . 'VALUES (:establishment,:customer,:appointment,:event,:recipient,:message,:status,:scheduled,:sent,:attempts,:error,:dedupe)'
    );

    foreach ($rows as $index => [$event, $status, $appointmentIndex, $error]) {
        if (!isset($appointmentIds[$appointmentIndex])) {
            continue;
        }
        $stmt->execute([
            'establishment' => $establishmentId,
            'customer' => $customers[$appointmentIndex % count($customers)],
            'appointment' => $appointmentIds[$appointmentIndex],
            'event' => $event,
            'recipient' => '5532999902' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
            'message' => '[DEMO] Mensagem de ' . $event . ' para testar a central de notificações.',
            'status' => $status,
            'scheduled' => $today->modify('+' . $index . ' hours')->format('Y-m-d H:i:s'),
            'sent' => $status === 'sent' ? $today->modify('-1 hour')->format('Y-m-d H:i:s') : null,
            'attempts' => $status === 'failed' ? 2 : ($status === 'sent' ? 1 : 0),
            'error' => $error,
            'dedupe' => 'demo:' . $event . ':' . $appointmentIds[$appointmentIndex],
        ]);
    }
}

function seedAppointmentEventsForDemo(PDO $pdo, int $establishmentId, array $appointmentIds, int $userId): void
{
    if (!isset($appointmentIds[0])) {
        return;
    }

    $pdo->prepare(
        'INSERT INTO appointment_events (appointment_id,establishment_id,user_id,event_type,details) '
        . 'VALUES (?,?,?,"rescheduled","Horário alterado no cenário de demonstração.")'
    )->execute([$appointmentIds[0], $establishmentId, $userId]);

    if (isset($appointmentIds[1])) {
        $pdo->prepare(
            'INSERT INTO appointment_events (appointment_id,establishment_id,user_id,event_type,details) '
            . 'VALUES (?,?,?,"attendance_confirmed","Cliente confirmou presença no cenário de demonstração.")'
        )->execute([$appointmentIds[1], $establishmentId, $userId]);
    }
}

function nextBusinessDay(DateTimeImmutable $date): DateTimeImmutable
{
    while ((int) $date->format('N') >= 6) {
        $date = $date->modify('+1 day');
    }
    return $date;
}

function nextWeekday(DateTimeImmutable $date, int $weekday): DateTimeImmutable
{
    while ((int) $date->format('N') !== $weekday) {
        $date = $date->modify('+1 day');
    }
    return $date;
}
