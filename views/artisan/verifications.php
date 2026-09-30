<?php
/** @var array $profile */
/** @var array $submissions */
/** @var array $latestByType */
/** @var array $types */
/** @var array $typeLabels */
$title = 'Verifications';
$statusPill = ['pending' => 'gold', 'approved' => 'ok', 'rejected' => 'bad'];
?>
<div class="stack">
  <div>
    <span class="eyebrow">Artisan · Trust &amp; verification</span>
    <h2 style="margin-top:4px">Verifications</h2>
    <p class="muted small" style="margin-top:6px">Submit an identity document, a trade/skill certificate, or a reference letter to raise your trust score and stand out in customer searches. Current level: <b style="color:var(--ink)"><?= e(ucfirst($profile['verification_level'])) ?></b> &middot; Trust score <b style="color:var(--ink)"><?= (int)$profile['trust_score'] ?>/100</b>.</p>
  </div>

  <div class="grid g3">
    <?php foreach ($types as $t): ?>
      <?php $latest = $latestByType[$t] ?? null; ?>
      <div class="card">
        <h3 style="font-size:15px"><?= e(ucfirst($t)) ?></h3>
        <p class="muted small" style="margin-top:6px"><?= e($typeLabels[$t]) ?></p>
        <?php if ($latest): ?>
          <span class="pill <?= e($statusPill[$latest['status']] ?? 'mute') ?>" style="margin-top:10px;display:inline-block"><?= e(ucfirst($latest['status'])) ?></span>
          <?php if ($latest['status'] === 'rejected' && $latest['reject_reason']): ?>
            <p class="small muted" style="margin-top:6px">Reason: <?= e($latest['reject_reason']) ?></p>
          <?php endif; ?>
        <?php else: ?>
          <span class="pill mute" style="margin-top:10px;display:inline-block">Not submitted</span>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <h3>Submit a document</h3>
    <form method="post" action="<?= e(url('/verifications')) ?>" enctype="multipart/form-data" class="stack" style="margin-top:12px">
      <?= csrf() ?>
      <div class="grid g2">
        <div class="field">
          <label>Verification type</label>
          <select name="type" required>
            <?php foreach ($types as $t): ?>
              <option value="<?= e($t) ?>"><?= e(ucfirst($t)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>What is this document?</label>
          <input type="text" name="document_type" maxlength="60" required placeholder="e.g. Ghana Card, NVTI certificate">
        </div>
      </div>
      <div class="field">
        <label>Upload (JPG, PNG, WebP or PDF, max 5MB)</label>
        <input type="file" name="document" accept="image/jpeg,image/png,image/webp,application/pdf" required>
      </div>
      <button class="btn primary" type="submit" style="align-self:flex-start">Submit for review</button>
    </form>
  </div>

  <?php if ($submissions): ?>
    <div class="card">
      <h3>Submission history</h3>
      <div class="stack s10" style="margin-top:12px">
        <?php foreach ($submissions as $s): ?>
          <div class="row between">
            <div>
              <b style="font-size:14px"><?= e(ucfirst($s['type'])) ?></b>
              <p class="muted small" style="margin-top:2px"><?= e($s['document_type'] ?? '') ?> &middot; <?= e(fmtDateTime($s['submitted_at'])) ?></p>
            </div>
            <span class="pill <?= e($statusPill[$s['status']] ?? 'mute') ?>"><?= e(ucfirst($s['status'])) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</div>
