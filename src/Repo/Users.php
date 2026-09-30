<?php
declare(strict_types=1);

namespace HireCraft\Repo;

use HireCraft\Support\Db;

final class Users
{
    public static function findByEmail(string $email): ?array
    {
        return Db::one('SELECT * FROM users WHERE email = ?', [$email]);
    }

    public static function findById(int $id): ?array
    {
        return Db::one('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public static function emailTaken(string $email): bool
    {
        return Db::scalar('SELECT COUNT(*) FROM users WHERE email = ?', [$email]) > 0;
    }

    public static function phoneTaken(string $phone): bool
    {
        return Db::scalar('SELECT COUNT(*) FROM users WHERE phone = ?', [$phone]) > 0;
    }

    public static function create(string $role, string $fullName, string $email, string $phone, string $passwordHash): int
    {
        return Db::insert(
            'INSERT INTO users (role, full_name, email, phone, password_hash, status) VALUES (?, ?, ?, ?, ?, "active")',
            [$role, $fullName, $email, $phone, $passwordHash]
        );
    }

    public static function touchLogin(int $id): void
    {
        Db::run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$id]);
    }

    public static function hasCustomerProfile(int $userId): bool
    {
        return Db::scalar('SELECT COUNT(*) FROM customer_profiles WHERE user_id = ?', [$userId]) > 0;
    }

    public static function hasArtisanProfile(int $userId): bool
    {
        return Db::scalar('SELECT COUNT(*) FROM artisan_profiles WHERE user_id = ?', [$userId]) > 0;
    }

    /**
     * A profile picture lives on a different table depending on role: the
     * customer/artisan profile row for those roles (set up after
     * registration), or users.avatar_path for admins (who have no separate
     * profile table). Returns null if the user has no avatar, or no profile
     * row yet (customer/artisan before they've completed setup).
     */
    public static function avatarPath(int $userId, string $role): ?string
    {
        return match ($role) {
            'customer' => Db::scalar('SELECT avatar_path FROM customer_profiles WHERE user_id = ?', [$userId]),
            'artisan' => Db::scalar('SELECT avatar_path FROM artisan_profiles WHERE user_id = ?', [$userId]),
            'admin' => Db::scalar('SELECT avatar_path FROM users WHERE id = ?', [$userId]),
            default => null,
        };
    }

    public static function setAvatarPath(int $userId, string $role, ?string $path): bool
    {
        $table = match ($role) {
            'customer' => 'customer_profiles',
            'artisan' => 'artisan_profiles',
            'admin' => 'users',
            default => null,
        };
        if ($table === null) {
            return false;
        }
        $idCol = $table === 'users' ? 'id' : 'user_id';
        Db::run("UPDATE $table SET avatar_path = ? WHERE $idCol = ?", [$path, $userId]);
        return true;
    }
}
