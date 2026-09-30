<?php
/** @var array $artisans */
/** @var array $categories */
/** @var array $areas */
/** @var array $skillsByCategory */
/** @var array $filters */
use HireCraft\Support\Auth;
$title = 'Find an artisan';
$isAdmin = Auth::role() === 'admin';
$tradeIcon = ['plumbing' => 'P', 'electrical' => 'E', 'carpentry' => 'C', 'masonry' => 'M', 'painting' => 'A'];
$verificationLabels = [
    'basic' => 'Basic', 'phone' => 'Phone verified', 'email' => 'Email verified',
    'identity' => 'ID verified', 'skill' => 'Skill verified', 'full' => 'Fully verified',
];
$sortLabels = [
    'best' => 'Best match', 'trusted' => 'Most trusted', 'rated' => 'Highest rated', 'experienced' => 'Most experienced',
    'nearest' => 'Nearest', 'budget' => 'Budget compatible', 'available' => 'Available today',
];
extract($filters);
$skillIds = $skillIds ?? [];
?>
<div class="stack">
  <div>
    <span class="eyebrow"><?= $isAdmin ? 'Admin' : 'Customer' ?></span>
    <h2 style="margin-top:4px"><?= $isAdmin ? 'All artisans' : 'Find an artisan' ?></h2>
    <?php if ($isAdmin): ?>
      <p class="muted small" style="margin-top:6px">Every approved artisan on the platform, with the same filters customers use to browse. Click a card to open their full profile and portfolio.</p>
    <?php else: ?>
      <p class="muted small" style="margin-top:6px">Browse and filter verified artisans directly &mdash; or <a href="<?= e(url('/jobs/new')) ?>">post a job</a> for a ranked SmartMatch shortlist instead.</p>
    <?php endif; ?>
  </div>

  <form method="get" action="<?= e(url('/find-artisan')) ?>" class="card">
    <div class="grid g3">
      <div class="field">
        <label>Trade</label>
        <select name="category">
          <option value="">Any trade</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= $categoryId == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>Area</label>
        <select name="area">
          <option value="">Any area</option>
          <?php foreach ($areas as $a): ?>
            <option value="<?= (int)$a['id'] ?>" <?= $areaId == $a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?><?= $a['district'] ? ' (' . e($a['district']) . ')' : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>Sort by</label>
        <select name="sort">
          <?php foreach ($sortLabels as $val => $label): ?>
            <option value="<?= e($val) ?>" <?= $sort === $val ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <?php if ($categoryId && !empty($skillsByCategory[$categoryId])): ?>
      <div class="field">
        <label>Skills</label>
        <div class="row" style="gap:8px">
          <?php foreach ($skillsByCategory[$categoryId] as $s): ?>
            <label class="chip"><input type="checkbox" name="skills[]" value="<?= (int)$s['id'] ?>" <?= in_array((int)$s['id'], $skillIds, true) ? 'checked' : '' ?>><?= e($s['name']) ?></label>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="grid g3">
      <div class="field">
        <label>Min. trust score</label>
        <input type="number" name="min_trust" min="0" max="100" value="<?= $minTrust ?: '' ?>" placeholder="e.g. 70">
      </div>
      <div class="field">
        <label>Min. rating</label>
        <select name="min_rating">
          <option value="">Any</option>
          <?php foreach ([4.5, 4, 3.5, 3] as $r): ?>
            <option value="<?= $r ?>" <?= (float)$minRating === $r ? 'selected' : '' ?>><?= $r ?>+ ★</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>Verification level</label>
        <select name="verification">
          <option value="">Any</option>
          <?php foreach ($verificationLabels as $val => $label): ?>
            <option value="<?= e($val) ?>" <?= $verification === $val ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="grid g3">
      <div class="field">
        <label>Min. years experience</label>
        <input type="number" name="min_experience" min="0" value="<?= $minExperience ?: '' ?>">
      </div>
      <div class="field">
        <label>Your budget (GH¢) <span class="muted" style="font-weight:400">(optional)</span></label>
        <div class="row" style="gap:8px">
          <input type="number" name="budget_min" min="0" placeholder="Min" value="<?= e((string)$budgetMin) ?>">
          <input type="number" name="budget_max" min="0" placeholder="Max" value="<?= e((string)$budgetMax) ?>">
        </div>
      </div>
      <div class="field">
        <label>&nbsp;</label>
        <div class="row" style="gap:14px">
          <label class="opt" style="flex:1"><input type="checkbox" name="emergency" value="1" <?= $emergencyOnly ? 'checked' : '' ?>><span><b>Emergency-ready only</b></span></label>
        </div>
      </div>
    </div>
    <label class="opt" style="max-width:260px">
      <input type="checkbox" name="available_today" value="1" <?= $availableToday ? 'checked' : '' ?>>
      <span><b>Available today</b></span>
    </label>

    <button class="btn primary" type="submit" style="margin-top:14px">Search</button>
  </form>

  <p class="muted small"><?= count($artisans) ?> artisan<?= count($artisans) === 1 ? '' : 's' ?> found</p>

  <?php if (!$artisans): ?>
    <div class="card tint" style="text-align:center;padding:30px">
      <h3>No artisans match these filters</h3>
      <p class="muted small" style="margin-top:6px">Try widening your search &mdash; fewer filters, or a nearby area.</p>
    </div>
  <?php else: ?>
    <div class="grid g2">
      <?php foreach ($artisans as $a): ?>
        <div class="card">
          <div class="row" style="gap:12px;align-items:flex-start">
            <span class="av <?= e($a['category_slug']) ?>">
              <?php if (!empty($a['avatar_path'])): ?>
                <img src="<?= e(uploadUrl($a['avatar_path'])) ?>" alt="">
              <?php else: ?>
                <?= e($tradeIcon[$a['category_slug']] ?? mb_substr($a['category_name'], 0, 1)) ?>
              <?php endif; ?>
            </span>
            <div style="flex:1">
              <h3 style="font-size:16px"><a href="<?= e(url('/artisans/' . (int)$a['user_id'])) ?>" style="color:inherit;text-decoration:none"><?= e($a['business_name'] ?: $a['full_name']) ?></a></h3>
              <p class="muted small" style="margin-top:2px"><?= e($a['category_name']) ?> · <?= e($a['area_name']) ?> · <?= (int)$a['years_experience'] ?> yrs</p>
              <p class="muted small" style="margin-top:4px">Trust <?= (int)$a['trust_score'] ?>/100 · <?= $a['rating_count'] > 0 ? number_format((float)$a['rating_mean'], 1) . ' ★ (' . (int)$a['rating_count'] . ')' : 'No reviews yet' ?> · <?= e($verificationLabels[$a['verification_level']] ?? ucfirst($a['verification_level'])) ?></p>
              <?php if ($a['accepts_emergency']): ?><span class="pill gold" style="margin-top:6px;display:inline-block">🚨 Takes emergencies</span><?php endif; ?>
              <?php if (!empty($a['price_range'])): ?><p class="muted small" style="margin-top:6px">Typical range: <?= e(gh((float)$a['price_range'][0])) ?> &ndash; <?= e(gh((float)$a['price_range'][1])) ?></p><?php endif; ?>
            </div>
          </div>
          <a class="btn ghost sm" style="margin-top:12px" href="<?= e(url('/artisans/' . (int)$a['user_id'])) ?>">View profile</a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
