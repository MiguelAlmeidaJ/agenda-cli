<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\View;
use App\Services\LoginThrottleService;
use App\Services\PasswordPolicy;
use PDOException;

final class AuthController
{
    public function login(): void
    {
        if (Auth::check()) {
            redirect('/painel');
        }
        View::render('auth/login', ['title' => 'Entrar']);
    }

    public function authenticate(): void
    {
        Csrf::validate($_POST['_csrf'] ?? null);
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $throttle = new LoginThrottleService();

        $retryAfter = $throttle->retryAfter($email, $ip);
        if ($retryAfter > 0) {
            $minutes = max(1, (int) ceil($retryAfter / 60));
            flash('error', 'Muitas tentativas de acesso. Tente novamente em cerca de ' . $minutes . ' minuto(s).');
            redirect('/login');
        }

        if ($email === '' || $password === '' || !Auth::attempt($email, $password)) {
            $throttle->recordFailure($email, $ip);
            flash('error', 'E-mail ou senha inválidos.');
            redirect('/login');
        }

        $throttle->clearEmail($email);
        $next = $_SESSION['after_login'] ?? null;
        unset($_SESSION['after_login']);
        if (is_string($next) && str_starts_with($next, '/') && !str_starts_with($next, '//')) {
            redirect($next);
        }
        redirect('/painel');
    }

    public function register(): void
    {
        if (Auth::check()) {
            redirect('/painel');
        }
        View::render('auth/register', ['title' => 'Criar conta']);
    }

    public function storeRegistration(): void
    {
        Csrf::validate($_POST['_csrf'] ?? null);
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');

        $passwordErrors = (new PasswordPolicy())->errors($password);
        if (strlen($name) < 3 || !filter_var($email, FILTER_VALIDATE_EMAIL) || $passwordErrors !== []) {
            flash('error', 'Preencha os dados corretamente. ' . ($passwordErrors[0] ?? 'Revise os dados informados.'));
            redirect('/cadastro');
        }

        try {
            $stmt = Database::connection()->prepare(
                'INSERT INTO users (name, email, password_hash, role) VALUES (:name, :email, :password, "client")'
            );
            $stmt->execute([
                'name' => $name,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
            ]);
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                flash('error', 'Já existe uma conta com esse e-mail.');
                redirect('/cadastro');
            }
            throw $exception;
        }

        Auth::attempt($email, $password);
        flash('success', 'Conta criada. Você já pode agendar.');
        redirect('/');
    }

    public function logout(): void
    {
        Csrf::validate($_POST['_csrf'] ?? null);
        Auth::logout();
        redirect('/');
    }
}
