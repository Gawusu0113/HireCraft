<?php
/** @var string $content */
/** @var string|null $title */
use HireCraft\Repo\Users;
use HireCraft\Support\Auth;
use HireCraft\Support\Notifier;

$pageTitle = isset($title) && $title !== '' ? $title . ' · HireCraft' : 'HireCraft — Find the right artisan, with confidence';
$flashes = Auth::pullFlashes();
$role = Auth::role();
$unreadCount = Auth::check() ? Notifier::unreadCount((int)Auth::id()) : 0;
$recentNotifs = Auth::check() ? Notifier::recent((int)Auth::id(), 6) : [];
$myAvatarPath = Auth::check() ? Users::avatarPath((int)Auth::id(), (string)$role) : null;
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($pageTitle) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body>
<div class="kente"></div>
<header class="site-h">
  <div class="wrap">
    <a class="logo" href="<?= e(url('/')) ?>">
      <span class="mk">H</span>
      <span><span>HireCraft</span><small>Kumasi artisan matching</small></span>
    </a>
    <nav class="nav">
      <a class="navlink" href="<?= e(url('/')) ?>">Home</a>
      <?php if ($role === 'customer'): ?>
        <a class="navlink" href="<?= e(url('/dashboard')) ?>">My jobs</a>
        <a class="navlink" href="<?= e(url('/find-artisan')) ?>">Find an artisan</a>
        <a class="navlink" href="<?= e(url('/favorites')) ?>">Favorites</a>
      <?php elseif ($role === 'artisan'): ?>
        <a class="navlink" href="<?= e(url('/dashboard')) ?>">My profile</a>
        <a class="navlink" href="<?= e(url('/artisan/jobs')) ?>">Find jobs</a>
        <a class="navlink" href="<?= e(url('/artisan/requests')) ?>">Job requests</a>
        <a class="navlink" href="<?= e(url('/profile/portfolio')) ?>">Portfolio</a>
        <a class="navlink" href="<?= e(url('/verifications')) ?>">Verifications</a>
      <?php elseif ($role === 'admin'): ?>
        <a class="navlink" href="<?= e(url('/dashboard')) ?>">Admin</a>
        <a class="navlink" href="<?= e(url('/find-artisan')) ?>">Artisans</a>
        <a class="navlink" href="<?= e(url('/admin/jobs')) ?>">Jobs</a>
        <a class="navlink" href="<?= e(url('/admin/artisans')) ?>">Verification</a>
        <a class="navlink" href="<?= e(url('/admin/weights')) ?>">Weights</a>
        <a class="navlink" href="<?= e(url('/admin/benchmarks')) ?>">Benchmarks</a>
        <a class="navlink" href="<?= e(url('/admin/verifications')) ?>">Verifications</a>
        <a class="navlink" href="<?= e(url('/admin/reports')) ?>">Reports</a>
        <a class="navlink" href="<?= e(url('/admin/disputes')) ?>">Disputes</a>
        <a class="navlink" href="<?= e(url('/admin/evaluation')) ?>">Evaluation</a>
      <?php endif; ?>
    </nav>
    <?php if (Auth::check()): ?>
      <div class="row" style="gap:14px;align-items:center">
        <div class="bell-wrap">
          <a class="bell" href="<?= e(url('/notifications')) ?>" aria-label="Notifications">
            🔔<?php if ($unreadCount > 0): ?><span class="bell-badge"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></span><?php endif; ?>
          </a>
          <?php if ($recentNotifs): ?>
            <div class="bell-menu">
              <?php foreach ($recentNotifs as $n): ?>
                <a class="bell-item <?= $n['is_read'] ? '' : 'unread' ?>" href="<?= e($n['link'] ? url('/notifications/' . (int)$n['id']) : url('/notifications')) ?>">
                  <b><?= e($n['title']) ?></b>
                  <?php if ($n['body']): ?><span><?= e($n['body']) ?></span><?php endif; ?>
                </a>
              <?php endforeach; ?>
              <a class="bell-item" style="text-align:center;font-weight:600" href="<?= e(url('/notifications')) ?>">See all</a>
            </div>
          <?php endif; ?>
        </div>
        <div class="userchip">
          <a href="<?= e(url('/profile/picture')) ?>" class="av sm user" title="Profile picture" style="text-decoration:none">
            <?php if ($myAvatarPath): ?>
              <img src="<?= e(uploadUrl($myAvatarPath)) ?>" alt="">
            <?php else: ?>
              <?= e(mb_strtoupper(mb_substr((string)Auth::name(), 0, 1))) ?>
            <?php endif; ?>
          </a>
          <span><?= e((string)Auth::name()) ?></span>
          <form method="post" action="<?= e(url('/logout')) ?>" style="margin:0">
            <?= csrf() ?>
            <button class="btn ghost sm" type="submit">Log out</button>
          </form>
        </div>
      </div>
    <?php else: ?>
      <div class="row" style="gap:8px">
        <a class="btn ghost sm" href="<?= e(url('/login')) ?>">Log in</a>
        <a class="btn primary sm" href="<?= e(url('/register')) ?>">Get started</a>
      </div>
    <?php endif; ?>
  </div>
</header>
<main id="main">
  <div class="wrap">
    <?php if ($flashes): ?>
      <div class="flash-stack">
        <?php foreach ($flashes as $f): ?>
          <div class="flash <?= e($f['type']) ?>"><?= e($f['message']) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?= $content ?>
  </div>
</main>
<footer class="site-f">
  <div class="wrap">HireCraft · Explainable artisan matching for Kumasi · BSc IT final-year project · Sample/demo data</div>
</footer>
</body>
</html>
