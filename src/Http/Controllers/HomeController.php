<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Database;
use App\Core\View;

final class HomeController
{
    public function index(): void
    {
        $stmt = Database::connection()->query(
            'SELECT e.id, e.name, e.slug, e.description, e.city, e.state, '
            . 'COUNT(s.id) AS service_count, MIN(s.price) AS min_price '
            . 'FROM establishments e '
            . 'LEFT JOIN services s ON s.establishment_id = e.id AND s.active = 1 '
            . 'WHERE e.active = 1 GROUP BY e.id ORDER BY e.name'
        );

        View::render('home', [
            'title' => 'Encontre seu próximo horário',
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

        $employees = $pdo->prepare(
            'SELECT u.id, u.name, es.service_id FROM establishment_users eu '
            . 'JOIN users u ON u.id = eu.user_id '
            . 'JOIN employee_services es ON es.employee_user_id = u.id '
            . 'WHERE eu.establishment_id = :id AND eu.role = "employee" AND eu.active = 1 AND u.status = "active" '
            . 'ORDER BY u.name'
        );
        $employees->execute(['id' => $establishment['id']]);

        $employeesByService = [];
        foreach ($employees->fetchAll() as $employee) {
            $employeesByService[(int) $employee['service_id']][] = [
                'id' => (int) $employee['id'],
                'name' => $employee['name'],
            ];
        }

        View::render('establishment', [
            'title' => $establishment['name'],
            'establishment' => $establishment,
            'services' => $services->fetchAll(),
            'employeesByService' => $employeesByService,
        ]);
    }
}
