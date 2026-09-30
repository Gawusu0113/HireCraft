<?php
/** @var int $activeVersion */
/** @var array $activeWeights */
/** @var array $candidate */
/** @var array|null $preview */
/** @var array $history */
/** @var array $components */
$title = 'Matching weights';
$labels = [
    'skill' => 'Skill match', 'location' => 'Location', 'budget' => 'Budget', 'availability' => 'Availability',
    'experience' => 'Experience', 'trust' => 'Trust', 'rating' => 'Rating', 'portfolio' => 'Portfolio',
];
$levelClass = static fn(string $l) => str_contains($l, 'HIGHLY') ? 'lvl-high' : (str_contains($l, 'RECOMMENDED') ? 'lvl-rec' : (str_contains($l, 'POSSIBLE') ? 'lvl-poss' : 'lvl-low'));
?>
<div class="stack">
  <div>
    <span class="eyebrow">Admin · Live configuration v<?= (int)$activeVersion ?></span>
    <h2 style="margin-top:4px">Matching weights</h2>
    <p class="muted small" style="margin-top:6px">Adjust the relative importance of each factor, preview the effect on a real job, then save it as the new live configuration. Weights don't need to add to 100 — they're normalised automatically.</p>
  </div>

  <div class="card">
    <form id="weights-form">
      <?= csrf() ?>
      <div class="grid g4">
        <?php foreach ($components as $c): ?>
          <div class="field">
            <label for="w_<?= $c ?>"><?= e($labels[$c]) ?></label>
            <input type="number" id="w_<?= $c ?>" name="w_<?= $c ?>" min="0" max="100" step="0.5" value="<?= e((string)($candidate[$c] ?? 0)) ?>">
          </div>
        <?php endforeach; ?>
      </div>
      <div class="field" style="margin-top:12px">
        <label for="note">Note <span class="muted" style="font-weight:400">(optional, shown in history)</span></label>
        <input type="text" id="note" name="note" maxlength="255" placeholder="e.g. increased trust weight after survey findings">
      </div>
      <div class="row" style="margin-top:14px;gap:8px">
        <button class="btn ghost" type="submit" formmethod="get" formaction="<?= e(url('/admin/weights')) ?>" name="preview" value="1">Preview re-ranking</button>
        <button class="btn primary" type="submit" formmethod="post" formaction="<?= e(url('/admin/weights/save')) ?>">Save as new configuration</button>
      </div>
    </form>
  </div>

  <?php if ($preview): ?>
    <?php if (!$preview['job']): ?>
      <div class="note">No suitable posted job to preview against yet — post a job first, then come back to preview weight changes.</div>
    <?php else: ?>
      <div class="card">
        <h3>Preview: "<?= e($preview['job']['title']) ?>"</h3>
        <p class="muted small" style="margin-top:6px">Top 5 ranked artisans under the live configuration (v<?= (int)$activeVersion ?>) vs. the weights above.</p>
        <div class="grid g2" style="margin-top:14px">
          <div>
            <b class="small">Currently live</b>
            <div class="tw" style="margin-top:8px">
              <table>
                <thead><tr><th>Artisan</th><th>Score</th><th>Level</th></tr></thead>
                <tbody>
                <?php foreach ($preview['before'] as $m): ?>
                  <tr><td><?= e($m['name']) ?></td><td class="num"><?= number_format($m['score'], 1) ?></td><td><span class="pill <?= e($levelClass($m['level'])) ?>"><?= e($m['level']) ?></span></td></tr>
                <?php endforeach; ?>
                <?php if (!$preview['before']): ?><tr><td colspan="3" class="muted">No matches</td></tr><?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
          <div>
            <b class="small">With your weights</b>
            <div class="tw" style="margin-top:8px">
              <table>
                <thead><tr><th>Artisan</th><th>Score</th><th>Level</th></tr></thead>
                <tbody>
                <?php foreach ($preview['after'] as $m): ?>
                  <tr><td><?= e($m['name']) ?></td><td class="num"><?= number_format($m['score'], 1) ?></td><td><span class="pill <?= e($levelClass($m['level'])) ?>"><?= e($m['level']) ?></span></td></tr>
                <?php endforeach; ?>
                <?php if (!$preview['after']): ?><tr><td colspan="3" class="muted">No matches</td></tr><?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <div class="card">
    <h3>Configuration history</h3>
    <div class="tw" style="margin-top:10px">
      <table>
        <thead><tr><th>Version</th><th>Status</th><th>Note</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($history as $h): ?>
          <tr>
            <td>v<?= (int)$h['config_version'] ?></td>
            <td><?= $h['is_active'] ? '<span class="pill ok">Live</span>' : '' ?></td>
            <td><?= e($h['note'] ?? '') ?></td>
            <td><?= e(fmtDate($h['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
