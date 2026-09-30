<?php
declare(strict_types=1);

use HireCraft\Support\Response;

/** HTML-escape for safe output (NFR-05: no unescaped user input in views). */
function e(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

/** Format a Ghana cedi amount the same way the reference engine does. */
function gh(float|int|null $n): string
{
    if ($n === null) {
        return 'GH¢0';
    }
    return 'GH¢' . number_format((float)$n, 0);
}

function asset(string $path): string
{
    return Response::url('/assets/' . ltrim($path, '/'));
}

/** URL for a file saved under public/uploads/ (job photos, portfolio photos). */
function uploadUrl(string $path): string
{
    return Response::url('/uploads/' . ltrim($path, '/'));
}

function url(string $path = ''): string
{
    return Response::url($path);
}

/** Repopulate a form field from the last failed submission (session old-input). */
function old(string $key, string $default = ''): string
{
    return e($_SESSION['_old'][$key] ?? $default);
}

function csrf(): string
{
    return HireCraft\Support\Auth::csrfField();
}

function fmtDate(?string $d): string
{
    if (!$d) return '';
    $t = strtotime($d);
    return $t ? date('j M Y', $t) : $d;
}

function fmtDateTime(?string $d): string
{
    if (!$d) return '';
    $t = strtotime($d);
    return $t ? date('j M, g:ia', $t) : $d;
}

/** Human "3h ago" / "2d ago" style relative time for notification lists. */
function timeAgo(?string $d): string
{
    if (!$d) return '';
    $t = strtotime($d);
    if (!$t) return $d;
    $diff = max(0, time() - $t);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('j M Y', $t);
}

function levelClass(string $level): string
{
    return match (true) {
        str_contains($level, 'HIGHLY') => 'lvl-high',
        str_contains($level, 'RECOMMENDED') => 'lvl-rec',
        str_contains($level, 'POSSIBLE') => 'lvl-poss',
        default => 'lvl-low',
    };
}
