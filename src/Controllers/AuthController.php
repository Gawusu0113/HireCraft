<?php
declare(strict_types=1);

namespace HireCraft\Controllers;

use HireCraft\Repo\Users;
use HireCraft\Support\Auth;
use HireCraft\Support\Response;
use HireCraft\Support\View;

/** Registration, login and logout (NFR-05: hashed passwords, CSRF, session regeneration). */
final class AuthController
{
    public function showRegister(): void
    {
        if (Auth::check()) {
            Response::redirect('/dashboard');
        }
        $role = $_GET['role'] ?? 'customer';
        $role = in_array($role, ['customer', 'artisan'], true) ? $role : 'customer';
        View::render('auth/register', ['role' => $role]);
    }

    public function register(): void
    {
        $role = $_POST['role'] ?? '';
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $email = trim(mb_strtolower((string)($_POST['email'] ?? '')));
        $phone = self::normalisePhone((string)($_POST['phone'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

        $_SESSION['_old'] = ['full_name' => $fullName, 'email' => $email, 'phone' => $phone];

        $errors = [];
        if (!in_array($role, ['customer', 'artisan'], true)) {
            $errors[] = 'Please choose whether you are a customer or an artisan.';
        }
        if ($fullName === '' || mb_strlen($fullName) > 120) {
            $errors[] = 'Please enter your full name.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
            $errors[] = 'Please enter a valid email address.';
        } elseif (Users::emailTaken($email)) {
            $errors[] = 'That email address is already registered. Try logging in instead.';
        }
        if ($phone === null) {
            $errors[] = 'Please enter a valid Ghanaian phone number, e.g. 024 123 4567.';
        } elseif (Users::phoneTaken($phone)) {
            $errors[] = 'That phone number is already registered.';
        }
        if (mb_strlen($password) < 8) {
            $errors[] = 'Your password must be at least 8 characters.';
        } elseif ($password !== $passwordConfirm) {
            $errors[] = 'Your passwords do not match.';
        }

        if ($errors) {
            foreach ($errors as $e) {
                Auth::flash('error', $e);
            }
            Response::redirect('/register?role=' . urlencode($role ?: 'customer'));
        }

        $userId = Users::create($role, $fullName, $email, $phone, password_hash($password, PASSWORD_DEFAULT));
        unset($_SESSION['_old']);

        Auth::login(['id' => $userId, 'role' => $role, 'full_name' => $fullName]);
        Auth::flash('success', 'Welcome to HireCraft, ' . $fullName . '! Let\'s finish setting up your profile.');
        Response::redirect('/profile/setup');
    }

    public function showLogin(): void
    {
        if (Auth::check()) {
            Response::redirect('/dashboard');
        }
        View::render('auth/login');
    }

    public function login(): void
    {
        $email = trim(mb_strtolower((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        $_SESSION['_old'] = ['email' => $email];

        $user = Users::findByEmail($email);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            Auth::flash('error', 'Incorrect email or password.');
            Response::redirect('/login');
        }
        if ($user['status'] === 'suspended') {
            Auth::flash('error', 'This account has been suspended. Contact support for help.');
            Response::redirect('/login');
        }

        unset($_SESSION['_old']);
        Users::touchLogin((int)$user['id']);
        Auth::login($user);

        $needsSetup = ($user['role'] === 'customer' && !Users::hasCustomerProfile((int)$user['id']))
            || ($user['role'] === 'artisan' && !Users::hasArtisanProfile((int)$user['id']));

        Auth::flash('success', 'Welcome back, ' . $user['full_name'] . '.');
        Response::redirect($needsSetup ? '/profile/setup' : '/dashboard');
    }

    public function logout(): void
    {
        Auth::logout();
        Response::redirect('/');
    }

    /** Accepts 024..., +233 24..., 233 24... and returns a normalised 0XXXXXXXXX form, or null if invalid. */
    private static function normalisePhone(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if (str_starts_with($digits, '233') && strlen($digits) === 12) {
            $digits = '0' . substr($digits, 3);
        }
        if (strlen($digits) === 9 && $digits[0] !== '0') {
            $digits = '0' . $digits;
        }
        if (strlen($digits) !== 10 || $digits[0] !== '0') {
            return null;
        }
        return $digits;
    }
}
