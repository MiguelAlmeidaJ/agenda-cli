<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Database;
use App\Core\View;

final class HomeController
{
    public function index(): void
    {
        View::render('home', [
            'title' => 'Agenda CLI - Agendamentos simples para o seu negócio',
        ]);
    }

    public function find(): void
    {
        $stmt = Database::connection()->query(
            'SELECT e.id, e.name, e.slug, e.description, e.city, e.state, '
            . 'COUNT(s.id) AS service_count, MIN(s.price) AS min_price '
            . 'FROM establishments e '
            . 'LEFT JOIN services s ON s.establishment_id = e.id AND s.active = 1 '
            . 'WHERE e.active = 1 GROUP BY e.id ORDER BY e.name'
        );

        View::render('find', [
            'title' => 'Encontre um serviço',
            'establishments' => $stmt->fetchAll(),
        ]);
    }

    public function establishment(string $slug): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM establishments WHERE slug = :slug AND active = 1 LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $establishment = $stmt->fetch();

        if (!$establishment) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Estabelecimento não encontrado']);
            return;
        }

        $services = $pdo->prepare('SELECT * FROM services WHERE establishment_id = :id AND active = 1 ORDER BY name');
        $services->execute(['id' => $establishment['id']]);
        $serviceRows = $services->fetchAll();

        $providers = $pdo->prepare(
            'SELECT u.id, u.name, es.service_id, '
            . 'CASE WHEN u.id = e.owner_user_id THEN "owner" ELSE "employee" END AS provider_role '
            . 'FROM employee_services es '
            . 'JOIN services s ON s.id = es.service_id '
            . 'JOIN establishments e ON e.id = s.establishment_id '
            . 'JOIN users u ON u.id = es.employee_user_id '
            . 'LEFT JOIN establishment_users eu ON eu.establishment_id = e.id AND eu.user_id = u.id '
            . 'WHERE e.id = :id AND s.active = 1 AND u.status = "active" '
            . 'AND (u.id = e.owner_user_id OR (eu.role = "employee" AND eu.active = 1)) '
            . 'ORDER BY CASE WHEN u.id = e.owner_user_id THEN 0 ELSE 1 END, u.name'
        );
        $providers->execute(['id' => $establishment['id']]);

        $providersByService = [];
        foreach ($providers->fetchAll() as $provider) {
            $providersByService[(int) $provider['service_id']][] = [
                'id' => (int) $provider['id'],
                'name' => $provider['name'],
                'role' => $provider['provider_role'],
            ];
        }

        View::render('establishment', [
            'title' => $establishment['name'],
            'establishment' => $establishment,
            'services' => $serviceRows,
            'employeesByService' => $providersByService,
        ]);
    }
}
