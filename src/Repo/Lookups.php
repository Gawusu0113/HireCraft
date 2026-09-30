<?php
declare(strict_types=1);

namespace HireCraft\Repo;

use HireCraft\Support\Db;

/** Read-only reference data shared by several controllers (trades, skills, areas). */
final class Lookups
{
    /** @return array<int, array{id:int, name:string, slug:string}> */
    public static function categories(): array
    {
        return Db::all('SELECT id, name, slug FROM service_categories WHERE is_active = 1 ORDER BY name');
    }

    /** @return array<int, array{id:int, name:string, complexity_weight:int}> */
    public static function skillsForCategory(int $categoryId): array
    {
        return Db::all(
            'SELECT id, name, complexity_weight FROM skills WHERE category_id = ? AND is_active = 1 ORDER BY name',
            [$categoryId]
        );
    }

    /** @return array<int, array<int, array{id:int, name:string, complexity_weight:int}>> Skills grouped by category_id. */
    public static function skillsByCategory(): array
    {
        $rows = Db::all('SELECT id, category_id, name, complexity_weight FROM skills WHERE is_active = 1 ORDER BY name');
        $out = [];
        foreach ($rows as $row) {
            $out[(int)$row['category_id']][] = $row;
        }
        return $out;
    }

    /** @return array<int, array{id:int, name:string, district:?string}> */
    public static function areas(): array
    {
        return Db::all('SELECT id, name, district FROM areas ORDER BY name');
    }

    public static function categoryById(int $id): ?array
    {
        return Db::one('SELECT id, name, slug FROM service_categories WHERE id = ?', [$id]);
    }
}
