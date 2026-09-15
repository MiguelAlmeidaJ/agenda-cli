<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\TenantContext;
use App\Core\View;

final class CustomerController
{
    public function index(): void
    {
        Auth::requireRole(['owner', 'employee']);
        $establishmentId = TenantContext::requireEstablishmentId();
        $pdo = Database::connection();
        $query = trim((string) ($_GET['q'] ?? ''));

        $sql = 'SELECT c.id, c.name, c.email, c.phone, c.user_id, c.created_at, '
            . 'COUNT(a.id) AS appointment_count, '
            . 'COALESCE(SUM(a.status = "completed"), 0) AS completed_count, '
            . 'MAX(CASE WHEN a.status = "completed" THEN a.starts_at END) AS last_visit, '
            . 'MIN(CASE WHEN a.status IN ("pending", "confirmed") AND a.starts_at >= NOW() THEN a.starts_at END) AS next_appointment '
            . 'FROM customers c '
            . 'LEFT JOIN appointments a ON a.customer_id = c.id AND a.establishment_id = c.establishment_id '
            . 'WHERE c.establishment_id = :establishment ';
        $params = ['establishment' => $establishmentId];

        if ($query !== '') {
            $sql .= 'AND (c.name LIKE :q_name OR c.email LIKE :q_email OR c.phone LIKE :q_phone) ';
            $like = '%' . $query . '%';
            $params['q_name'] = $like;
            $params['q_email'] = $like;
            $params['q_phone'] = $like;
        }

        $sql .= 'GROUP BY c.id ORDER BY '
            . 'CASE WHEN next_appointment IS NULL THEN 1 ELSE 0 END, next_appointment ASC, c.name ASC LIMIT 250';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        View::render('panel/customers', [
            'title' => 'Clientes',
            'customers' => $stmt->fetchAll(),
            'query' => $query,
        ]);
    }

    public function store(): void
    {
        Auth::requireRole(['owner', 'employee']);
        Csrf::validate($_POST['_csrf'] ?? null);
        $establishmentId = TenantContext::requireEstablishmentId();

        $name = trim((string) ($_POST['name'] ?? ''));
        $email = $this->normalizeEmail((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));

        if ($name === '' || ($email === null && $phone === '')) {
            flash('error', 'Informe o nome e pelo menos um contato: telefone ou e-mail.');
            redirect('/painel/clientes');
        }
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Informe um e-mail válido.');
            redirect('/painel/clientes');
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            if ($email !== null) {
                $duplicate = $pdo->prepare('SELECT id FROM customers WHERE establishment_id = :establishment AND email = :email LIMIT 1 FOR UPDATE');
                $duplicate->execute(['establishment' => $establishmentId, 'email' => $email]);
                if ($duplicate->fetchColumn()) {
                    throw new \RuntimeException('Já existe um cliente com este e-mail neste estabelecimento.');
                }
            }

            $userId = $this->linkedClientUserId($pdo, $email);
            if ($userId !== null) {
                $linked = $pdo->prepare('SELECT id FROM customers WHERE establishment_id = :establishment AND user_id = :user LIMIT 1 FOR UPDATE');
                $linked->execute(['establishment' => $establishmentId, 'user' => $userId]);
                $existingCustomer = $linked->fetchColumn();
                if ($existingCustomer) {
                    throw new \RuntimeException('Esta conta de cliente já está cadastrada no estabelecimento.');
                }
            }

            $insert = $pdo->prepare(
                'INSERT INTO customers (establishment_id, user_id, name, email, phone, notes) '
                . 'VALUES (:establishment, :user, :name, :email, :phone, :notes)'
            );
            $insert->execute([
                'establishment' => $establishmentId,
                'user' => $userId,
                'name' => $name,
                'email' => $email,
                'phone' => $phone !== '' ? $phone : null,
                'notes' => $notes !== '' ? $notes : null,
            ]);
            $customerId = (int) $pdo->lastInsertId();
            $pdo->commit();

            flash('success', 'Cliente cadastrado.');
            redirect('/painel/clientes/' . $customerId);
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', $exception instanceof \RuntimeException ? $exception->getMessage() : 'Não foi possível cadastrar o cliente.');
            redirect('/painel/clientes');
        }
    }

    public function show(string $id): void
    {
        Auth::requireRole(['owner', 'employee']);
        $establishmentId = TenantContext::requireEstablishmentId();
        $customerId = (int) $id;
        $pdo = Database::connection();

        $customerStmt = $pdo->prepare(
            'SELECT c.*, u.status AS user_status FROM customers c '
            . 'LEFT JOIN users u ON u.id = c.user_id '
            . 'WHERE c.id = :customer AND c.establishment_id = :establishment LIMIT 1'
        );
        $customerStmt->execute(['customer' => $customerId, 'establishment' => $establishmentId]);
        $customer = $customerStmt->fetch();
        if (!$customer) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Cliente não encontrado']);
            return;
        }

        $statsStmt = $pdo->prepare(
            'SELECT COUNT(*) AS total, '
            . 'COALESCE(SUM(status = "completed"), 0) AS completed, '
            . 'COALESCE(SUM(CASE WHEN status = "completed" THEN price ELSE 0 END), 0) AS revenue, '
            . 'MAX(CASE WHEN status = "completed" THEN starts_at END) AS last_visit, '
            . 'MIN(CASE WHEN status IN ("pending", "confirmed") AND starts_at >= NOW() THEN starts_at END) AS next_appointment '
            . 'FROM appointments WHERE establishment_id = :establishment AND customer_id = :customer'
        );
        $statsStmt->execute(['establishment' => $establishmentId, 'customer' => $customerId]);
        $stats = $statsStmt->fetch() ?: [];

        $appointments = $pdo->prepare(
            'SELECT a.*, s.name AS service_name, u.name AS employee_name '
            . 'FROM appointments a JOIN services s ON s.id = a.service_id '
            . 'JOIN users u ON u.id = a.employee_user_id '
            . 'WHERE a.establishment_id = :establishment AND a.customer_id = :customer '
            . 'ORDER BY a.starts_at DESC LIMIT 100'
        );
        $appointments->execute(['establishment' => $establishmentId, 'customer' => $customerId]);

        View::render('panel/customer_show', [
            'title' => $customer['name'],
            'customer' => $customer,
            'stats' => $stats,
            'appointments' => $appointments->fetchAll(),
        ]);
    }

    public function update(string $id): void
    {
        Auth::requireRole(['owner', 'employee']);
        Csrf::validate($_POST['_csrf'] ?? null);
        $establishmentId = TenantContext::requireEstablishmentId();
        $customerId = (int) $id;

        $name = trim((string) ($_POST['name'] ?? ''));
        $email = $this->normalizeEmail((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));

        if ($name === '' || ($email === null && $phone === '')) {
            flash('error', 'Informe o nome e pelo menos um contato.');
            redirect('/painel/clientes/' . $customerId);
        }
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Informe um e-mail válido.');
            redirect('/painel/clientes/' . $customerId);
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $currentStmt = $pdo->prepare(
                'SELECT id, user_id FROM customers WHERE id = :customer AND establishment_id = :establishment LIMIT 1 FOR UPDATE'
            );
            $currentStmt->execute(['customer' => $customerId, 'establishment' => $establishmentId]);
            $current = $currentStmt->fetch();
            if (!$current) {
                throw new \RuntimeException('Cliente não encontrado.');
            }

            if ($email !== null) {
                $duplicate = $pdo->prepare(
                    'SELECT id FROM customers WHERE establishment_id = :establishment AND email = :email AND id <> :customer LIMIT 1'
                );
                $duplicate->execute(['establishment' => $establishmentId, 'email' => $email, 'customer' => $customerId]);
                if ($duplicate->fetchColumn()) {
                    throw new \RuntimeException('Outro cliente já usa este e-mail.');
                }
            }

            $userId = $current['user_id'] ? (int) $current['user_id'] : $this->linkedClientUserId($pdo, $email);
            if ($userId !== null && !$current['user_id']) {
                $linked = $pdo->prepare(
                    'SELECT id FROM customers WHERE establishment_id = :establishment AND user_id = :user AND id <> :customer LIMIT 1'
                );
                $linked->execute(['establishment' => $establishmentId, 'user' => $userId, 'customer' => $customerId]);
                if ($linked->fetchColumn()) {
                    $userId = null;
                }
            }

            $update = $pdo->prepare(
                'UPDATE customers SET user_id = :user, name = :name, email = :email, phone = :phone, notes = :notes '
                . 'WHERE id = :customer AND establishment_id = :establishment'
            );
            $update->execute([
                'user' => $userId,
                'name' => $name,
                'email' => $email,
                'phone' => $phone !== '' ? $phone : null,
                'notes' => $notes !== '' ? $notes : null,
                'customer' => $customerId,
                'establishment' => $establishmentId,
            ]);

            $pdo->commit();
            flash('success', 'Dados do cliente atualizados.');
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', $exception instanceof \RuntimeException ? $exception->getMessage() : 'Não foi possível atualizar o cliente.');
        }

        redirect('/painel/clientes/' . $customerId);
    }

    private function linkedClientUserId(\PDO $pdo, ?string $email): ?int
    {
        if ($email === null) {
            return null;
        }

        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND role = "client" AND status = "active" LIMIT 1');
        $stmt->execute(['email' => $email]);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }

    private function normalizeEmail(string $email): ?string
    {
        $email = strtolower(trim($email));
        return $email !== '' ? $email : null;
    }
}
