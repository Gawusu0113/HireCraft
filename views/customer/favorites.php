<?php
/** @var array $favorites */
$title = 'My favorite artisans';
$tradeIcon = ['plumbing' => 'P', 'electrical' => 'E', 'carpentry' => 'C', 'masonry' => 'M', 'painting' => 'A'];
?>
<div class="stack">
  <div>
    <span class="eyebrow">Customer</span>
    <h2 style="margin-top:4px">My favorite artisans</h2>
    <p class="muted small" style="margin-top:6px">Artisans you've saved to hire again or keep shortlisted.</p>
  </div>

  <?php if (!$favorites): ?>
    <div class="card tint" style="text-align:center;padding:30px">
      <h3>No favorites yet</h3>
      <p class="muted small" style="margin-top:6px">Save an artisan from their profile page to see them here.</p>
    </div>
  <?php else: ?>
    <div class="grid g2">
      <?php foreach ($favorites as $f): ?>
        <div class="card">
          <div class="row" style="gap:12px;align-items:flex-start">
            <span class="av <?= e($f['category_slug']) ?>"><?= e($tradeIcon[$f['category_slug']] ?? mb_substr($f['category_name'], 0, 1)) ?></span>
            <div style="flex:1">
              <h3 style="font-size:16px"><a href="<?= e(url('/artisans/' . (int)$f['user_id'])) ?>" style="color:inherit;text-decoration:none"><?= e($f['business_name'] ?: $f['full_name']) ?></a></h3>
              <p class="muted small" style="margin-top:2px"><?= e($f['category_name']) ?> · <?= e($f['area_name']) ?></p>
              <p class="muted small" style="margin-top:4px">Trust <?= (int)$f['trust_score'] ?>/100 · <?= $f['rating_count'] > 0 ? number_format((float)$f['rating_mean'], 1) . ' ★' : 'No reviews yet' ?></p>
            </div>
          </div>
          <div class="row" style="margin-top:12px;gap:8px">
            <a class="btn ghost sm" href="<?= e(url('/artisans/' . (int)$f['user_id'])) ?>">View profile</a>
            <form method="post" action="<?= e(url('/artisans/' . (int)$f['user_id'] . '/unfavorite')) ?>">
              <?= csrf() ?>
              <input type="hidden" name="back" value="<?= e(url('/favorites')) ?>">
              <button class="btn ghost sm" type="submit">Remove</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
