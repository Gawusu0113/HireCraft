<?php
/** @var array $job */
/** @var array|null $work */
/** @var array $quotations */
/** @var array|null $review */
/** @var array $updates */
/** @var bool $isCustomer */
/** @var bool $isArtisan */
/** @var array $photos */
/** @var array $milestones */
/** @var bool $isEditable */
$title = $job['title'];
$milestoneStatusPill = ['pending' => 'mute', 'in_progress' => 'gold', 'completed' => 'ok'];
$statusSteps = ['scheduled' => 'Scheduled', 'in_progress' => 'In progress', 'completed' => 'Awaiting confirmation', 'confirmed' => 'Confirmed', 'cancelled' => 'Cancelled'];
$stepOrder = ['scheduled', 'in_progress', 'completed', 'confirmed'];
$currentIdx = $work ? array_search($work['status'], $stepOrder, true) : -1;
?>
<div class="stack">
  <div>
    <span class="eyebrow"><?= e($job['category_name'] ?? '') ?> · <?= e($job['area_name'] ?? '') ?></span>
    <h2 style="margin-top:4px"><?= e($job['title']) ?></h2>
    <p class="muted small" style="margin-top:6px"><?= nl2br(e($job['description'])) ?></p>
    <?php if ($photos): ?>
      <div class="photostrip" style="margin-top:12px">
        <?php foreach ($photos as $p): ?>
          <a href="<?= e(uploadUrl($p['image_path'])) ?>" target="_blank" rel="noopener">
            <img src="<?= e(uploadUrl($p['image_path'])) ?>" alt="<?= e($p['caption'] ?: 'Job photo') ?>" loading="lazy">
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($work): ?>
    <div class="card">
      <div class="row between">
        <h3>Job status</h3>
        <span class="pill <?= $work['status'] === 'cancelled' ? 'bad' : ($work['status'] === 'confirmed' ? 'ok' : 'gold') ?>"><?= e($statusSteps[$work['status']] ?? $work['status']) ?></span>
      </div>
      <ul class="stepper" style="margin-top:16px">
        <?php foreach ($stepOrder as $i => $s): ?>
          <li class="<?= $i < $currentIdx ? 'done' : ($i === $currentIdx ? 'now' : '') ?>"><?= e($statusSteps[$s]) ?></li>
        <?php endforeach; ?>
      </ul>
      <p class="muted small" style="margin-top:10px">
        Artisan: <b style="color:var(--ink)"><?= e($work['business_name'] ?: $work['artisan_name']) ?></b>
        · Agreed price: <b style="color:var(--ink)"><?= e(gh((float)$work['agreed_price'])) ?></b>
      </p>
      <div class="row" style="margin-top:10px;gap:8px">
        <a class="btn ghost sm" href="<?= e(url('/jobs/' . (int)$job['id'] . '/messages/' . ($isCustomer ? (int)$work['artisan_id'] : (int)$job['customer_id']))) ?>">💬 Message <?= $isCustomer ? e($work['business_name'] ?: $work['artisan_name']) : 'customer' ?></a>
        <a class="btn ghost sm" href="<?= e(url('/jobs/' . (int)$job['id'] . '/dispute')) ?>">⚠️ <?= $job['status'] === 'disputed' ? 'View dispute' : 'Report a problem' ?></a>
      </div>

      <?php if ($isArtisan && $work['status'] === 'scheduled'): ?>
        <form method="post" action="<?= e(url('/jobs/' . (int)$job['id'] . '/start')) ?>" style="margin-top:14px"><?= csrf() ?>
          <button class="btn primary" type="submit">Start job</button>
        </form>
      <?php elseif ($isArtisan && $work['status'] === 'in_progress'): ?>
        <form method="post" action="<?= e(url('/jobs/' . (int)$job['id'] . '/complete')) ?>" style="margin-top:14px"><?= csrf() ?>
          <button class="btn primary" type="submit">Mark job as complete</button>
        </form>
      <?php elseif ($isCustomer && $work['status'] === 'completed'): ?>
        <form method="post" action="<?= e(url('/jobs/' . (int)$job['id'] . '/confirm')) ?>" style="margin-top:14px"><?= csrf() ?>
          <button class="btn primary" type="submit">Confirm the work is done</button>
        </form>
      <?php elseif ($isCustomer && $work['status'] === 'confirmed' && !$review): ?>
        <a class="btn primary" style="margin-top:14px" href="<?= e(url('/jobs/' . (int)$job['id'] . '/review')) ?>">Leave a review</a>
      <?php endif; ?>

      <?php if ($updates): ?>
        <div class="divider"></div>
        <ul class="tl">
          <?php foreach ($updates as $u): ?>
            <li><b style="color:var(--ink)"><?= e($statusSteps[$u['status']] ?? $u['status']) ?></b> — <span class="muted small"><?= e($u['note'] ?? '') ?> · <?= e(fmtDate($u['created_at'])) ?></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <?php if ($milestones || $job['is_multi_trade'] || $job['complexity'] === 'complex'): ?>
      <div class="card">
        <div class="row between">
          <h3>Milestones</h3>
          <span class="muted small"><?= count($milestones) ?> milestone<?= count($milestones) === 1 ? '' : 's' ?></span>
        </div>
        <p class="muted small" style="margin-top:4px">Break a large job into stages with their own cost and date &mdash; useful for multi-trade or complex work.</p>
        <?php if ($milestones): ?>
          <div class="stack s10" style="margin-top:14px">
            <?php foreach ($milestones as $m): ?>
              <div class="row between" style="align-items:flex-start;gap:10px">
                <div>
                  <b style="font-size:14px"><?= e($m['title']) ?></b>
                  <?php if ($m['description']): ?><p class="small muted" style="margin-top:2px"><?= nl2br(e($m['description'])) ?></p><?php endif; ?>
                  <p class="muted small" style="margin-top:4px">
                    <?php if ($m['estimated_cost'] !== null): ?><?= e(gh((float)$m['estimated_cost'])) ?> · <?php endif; ?>
                    <?php if ($m['estimated_date']): ?><?= e(fmtDate($m['estimated_date'])) ?><?php else: ?>No date set<?php endif; ?>
                  </p>
                </div>
                <div style="text-align:right">
                  <span class="pill <?= e($milestoneStatusPill[$m['status']] ?? 'mute') ?>"><?= e(ucwords(str_replace('_', ' ', $m['status']))) ?></span>
                  <?php if ($m['status'] !== 'completed'): ?>
                    <form method="post" action="<?= e(url('/milestones/' . (int)$m['id'] . '/status')) ?>" style="margin-top:6px">
                      <?= csrf() ?>
                      <input type="hidden" name="status" value="<?= $m['status'] === 'pending' ? 'in_progress' : 'completed' ?>">
                      <button class="btn ghost sm" type="submit"><?= $m['status'] === 'pending' ? 'Start' : 'Mark complete' ?></button>
                    </form>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <details class="why" style="margin-top:14px">
          <summary>Add a milestone</summary>
          <form method="post" action="<?= e(url('/jobs/' . (int)$job['id'] . '/milestones')) ?>" class="stack" style="margin-top:10px">
            <?= csrf() ?>
            <div class="field"><label>Title</label><input type="text" name="title" maxlength="150" required placeholder="e.g. Electrical rough-in"></div>
            <div class="grid g2">
              <div class="field"><label>Estimated cost (GH¢) <span class="muted" style="font-weight:400">(optional)</span></label><input type="number" name="estimated_cost" min="0" step="1"></div>
              <div class="field"><label>Estimated date <span class="muted" style="font-weight:400">(optional)</span></label><input type="date" name="estimated_date"></div>
            </div>
            <div class="field"><label>Description <span class="muted" style="font-weight:400">(optional)</span></label><textarea name="description" maxlength="500"></textarea></div>
            <button class="btn primary sm" type="submit">Add milestone</button>
          </form>
        </details>
      </div>
    <?php endif; ?>

    <?php if ($review): ?>
      <div class="card">
        <h3><?= $isCustomer ? 'Your review' : "Customer's review" ?></h3>
        <p style="margin-top:8px">⭐ <?= (int)$review['overall'] ?>/5 overall</p>
        <?php if ($review['comment']): ?><p class="small muted" style="margin-top:6px"><?= nl2br(e($review['comment'])) ?></p><?php endif; ?>
      </div>
    <?php endif; ?>

  <?php elseif ($isCustomer): ?>
    <?php if ($isEditable): ?>
      <div class="row" style="margin-bottom:4px">
        <a class="btn ghost sm" href="<?= e(url('/jobs/' . (int)$job['id'] . '/edit')) ?>">✏️ Edit job details</a>
      </div>
    <?php endif; ?>
    <?php if (!$quotations): ?>
      <div class="card tint" style="text-align:center;padding:30px">
        <h3>No quotations yet</h3>
        <p class="muted small" style="margin-top:6px">Request a quotation from artisans on the <a href="<?= e(url('/jobs/' . (int)$job['id'] . '/results')) ?>">SmartMatch results</a> page.</p>
      </div>
    <?php else: ?>
      <h3>Quotations received</h3>
      <div class="stack">
        <?php foreach ($quotations as $q): ?>
          <div class="card">
            <div class="row between">
              <div>
                <h3><a href="<?= e(url('/artisans/' . (int)$q['artisan_id'])) ?>" style="color:inherit;text-decoration:none"><?= e($q['business_name'] ?: $q['artisan_name']) ?></a></h3>
                <p class="muted small" style="margin-top:4px">Trust <?= (int)$q['trust_score'] ?>/100 · Rating <?= $q['rating_count'] > 0 ? number_format((float)$q['rating_mean'], 1) : 'No reviews yet' ?></p>
              </div>
              <span class="pill <?= $q['status'] === 'sent' ? 'gold' : ($q['status'] === 'accepted' ? 'ok' : 'bad') ?>"><?= e(ucfirst($q['status'])) ?></span>
            </div>
            <div class="grid g3" style="margin-top:12px">
              <div class="small muted">Labour<br><b style="color:var(--ink)"><?= e(gh((float)$q['labour_cost'])) ?></b></div>
              <div class="small muted">Materials<br><b style="color:var(--ink)"><?= e(gh((float)$q['material_cost'])) ?></b></div>
              <div class="small muted">Transport + other<br><b style="color:var(--ink)"><?= e(gh((float)$q['transport_cost'] + (float)$q['additional_charges'])) ?></b></div>
            </div>
            <p style="margin-top:10px"><b>Total: <?= e(gh((float)$q['total_cost'])) ?></b><?= $q['estimated_days'] ? ' · ' . (int)$q['estimated_days'] . ' day' . ((int)$q['estimated_days'] > 1 ? 's' : '') : '' ?></p>
            <?php if ($q['notes']): ?><p class="small muted" style="margin-top:6px"><?= nl2br(e($q['notes'])) ?></p><?php endif; ?>
            <div class="row" style="margin-top:12px;gap:8px">
              <?php if ($q['status'] === 'sent'): ?>
                <form method="post" action="<?= e(url('/jobs/' . (int)$job['id'] . '/quotations/' . (int)$q['id'] . '/accept')) ?>"><?= csrf() ?>
                  <button class="btn primary sm" type="submit">Accept &amp; hire</button>
                </form>
                <form method="post" action="<?= e(url('/jobs/' . (int)$job['id'] . '/quotations/' . (int)$q['id'] . '/decline')) ?>"><?= csrf() ?>
                  <button class="btn ghost sm" type="submit">Decline</button>
                </form>
              <?php endif; ?>
              <a class="btn ghost sm" href="<?= e(url('/jobs/' . (int)$job['id'] . '/messages/' . (int)$q['artisan_id'])) ?>">💬 Message</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
