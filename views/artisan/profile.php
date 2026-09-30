<?php
/** @var array $artisan */
/** @var array $skills */
/** @var array $serviceAreas */
/** @var array $items */
/** @var bool $isFavorite */
use HireCraft\Support\Auth;
$title = $artisan['business_name'] ?: $artisan['full_name'];
$tradeIcon = ['plumbing' => 'P', 'electrical' => 'E', 'carpentry' => 'C', 'masonry' => 'M', 'painting' => 'A'];
$verificationLabels = [
    'basic' => 'Basic', 'phone' => 'Phone verified', 'email' => 'Email verified',
    'identity' => 'ID verified', 'skill' => 'Skill verified', 'full' => 'Fully verified',
];
?>
<div class="stack">
  <div class="row between">
    <div class="row" style="gap:16px;align-items:flex-start">
      <span class="av lg <?= e($artisan['category_slug']) ?>">
        <?php if (!empty($artisan['avatar_path'])): ?>
          <img src="<?= e(uploadUrl($artisan['avatar_path'])) ?>" alt="">
        <?php else: ?>
          <?= e($tradeIcon[$artisan['category_slug']] ?? mb_substr($artisan['category_name'], 0, 1)) ?>
        <?php endif; ?>
      </span>
      <div>
        <span class="eyebrow"><?= e($artisan['category_name']) ?> · <?= e($artisan['area_name']) ?></span>
        <h2 style="margin-top:4px"><?= e($artisan['business_name'] ?: $artisan['full_name']) ?></h2>
        <p class="muted small" style="margin-top:4px"><?= e($artisan['full_name']) ?> · <?= (int)$artisan['years_experience'] ?> yrs experience · <?= e($verificationLabels[$artisan['verification_level']] ?? ucfirst($artisan['verification_level'])) ?></p>
      </div>
    </div>
    <div class="row" style="gap:8px">
      <?php if (Auth::role() === 'customer'): ?>
        <form method="post" action="<?= e(url('/artisans/' . (int)$artisan['user_id'] . ($isFavorite ? '/unfavorite' : '/favorite'))) ?>">
          <?= csrf() ?>
          <input type="hidden" name="back" value="<?= e(url('/artisans/' . (int)$artisan['user_id'])) ?>">
          <button class="btn <?= $isFavorite ? 'primary' : 'ghost' ?> sm" type="submit"><?= $isFavorite ? '★ Saved' : '☆ Save artisan' ?></button>
        </form>
      <?php endif; ?>
      <a class="btn ghost sm" href="<?= e(url('/report?user=' . (int)$artisan['user_id'])) ?>">🚩 Report</a>
    </div>
  </div>

  <div class="grid g3">
    <div class="card" style="text-align:center">
      <div class="kpi" style="align-items:center"><b><?= (int)$artisan['trust_score'] ?></b><span>Trust score</span></div>
    </div>
    <div class="card" style="text-align:center">
      <div class="kpi" style="align-items:center"><b><?= $artisan['rating_count'] > 0 ? number_format((float)$artisan['rating_mean'], 1) . ' ★' : '—' ?></b><span><?= (int)$artisan['rating_count'] ?> review<?= (int)$artisan['rating_count'] === 1 ? '' : 's' ?></span></div>
    </div>
    <div class="card" style="text-align:center">
      <div class="kpi" style="align-items:center"><b><?= (int)$artisan['completed_jobs'] ?></b><span>Jobs completed</span></div>
    </div>
  </div>

  <div class="grid g2">
    <div class="card">
      <h3>Skills</h3>
      <div class="row" style="margin-top:10px;gap:8px">
        <?php if (!$skills): ?><p class="muted small">Not listed.</p><?php endif; ?>
        <?php foreach ($skills as $s): ?><span class="chip static">✓ <?= e($s['name']) ?></span><?php endforeach; ?>
      </div>
    </div>
    <div class="card">
      <h3>Service areas</h3>
      <div class="row" style="margin-top:10px;gap:8px">
        <?php if (!$serviceAreas): ?><p class="muted small">Not listed.</p><?php endif; ?>
        <?php foreach ($serviceAreas as $a): ?><span class="chip static"><?= e($a['name']) ?></span><?php endforeach; ?>
      </div>
      <p class="muted small" style="margin-top:10px">Travel radius: <?= (int)$artisan['travel_radius_km'] ?> km · Emergency jobs: <?= $artisan['accepts_emergency'] ? 'Yes' : 'No' ?></p>
    </div>
  </div>

  <?php if ($artisan['bio']): ?>
    <div class="card">
      <h3>About</h3>
      <p class="small" style="margin-top:8px"><?= nl2br(e($artisan['bio'])) ?></p>
    </div>
  <?php endif; ?>

  <div>
    <h3>Portfolio</h3>
    <?php if (!$items): ?>
      <div class="card tint" style="text-align:center;padding:30px;margin-top:12px">
        <p class="muted small">This artisan hasn't added any portfolio photos yet.</p>
      </div>
    <?php else: ?>
      <div class="stack" style="margin-top:12px">
        <?php foreach ($items as $item): ?>
          <div class="card">
            <h3 style="font-size:16px"><?= e($item['title']) ?></h3>
            <?php if ($item['completed_on']): ?>
              <p class="muted small" style="margin-top:4px"><?= e(fmtDate($item['completed_on'])) ?></p>
            <?php endif; ?>
            <?php if ($item['description']): ?>
              <p class="small" style="margin-top:8px"><?= nl2br(e($item['description'])) ?></p>
            <?php endif; ?>
            <div class="photogrid" style="margin-top:12px">
              <?php foreach ($item['images'] as $img): ?>
                <figure>
                  <a href="<?= e(uploadUrl($img['image_path'])) ?>" target="_blank" rel="noopener">
                    <img src="<?= e(uploadUrl($img['image_path'])) ?>" alt="<?= e($item['title']) ?>" loading="lazy">
                  </a>
                </figure>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
