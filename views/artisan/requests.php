<?php
/** @var array $requests */
/** @var array $quoted */
$title = 'Job requests';
?>
<div class="stack">
  <div>
    <span class="eyebrow">Artisan</span>
    <h2 style="margin-top:4px">Job requests</h2>
    <p class="muted small" style="margin-top:6px">Customers who'd like a quotation from you.</p>
  </div>

  <?php if (!$requests): ?>
    <div class="card tint" style="text-align:center;padding:30px">
      <h3>No open requests</h3>
      <p class="muted small" style="margin-top:6px">When a customer requests a quotation from you, it'll show up here.</p>
    </div>
  <?php else: ?>
    <?php foreach ($requests as $r): ?>
      <div class="card">
        <div class="row between">
          <div>
            <h3><?= e($r['title']) ?></h3>
            <p class="muted small" style="margin-top:4px"><?= e($r['area_name']) ?> · <?= e(ucfirst($r['complexity'] ?? 'n/a')) ?> · Urgency: <?= e(ucfirst($r['urgency'])) ?> · from <?= e($r['customer_name']) ?></p>
          </div>
          <span class="pill mute"><?= e($r['origin'] === 'requested' ? 'Customer request' : 'Applied') ?></span>
        </div>
        <p class="small" style="margin-top:10px"><?= nl2br(e($r['description'])) ?></p>
        <?php if (!empty($r['photos'])): ?>
          <div class="photostrip" style="margin-top:10px">
            <?php foreach ($r['photos'] as $p): ?>
              <a href="<?= e(uploadUrl($p['image_path'])) ?>" target="_blank" rel="noopener">
                <img src="<?= e(uploadUrl($p['image_path'])) ?>" alt="<?= e($p['caption'] ?: 'Job photo') ?>" loading="lazy">
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <p class="small" style="margin-top:6px">Customer's budget:
          <b>
            <?php if ($r['budget_type'] === 'unknown'): ?>Not specified
            <?php elseif ($r['budget_type'] === 'fixed'): ?><?= e(gh((float)$r['budget_max'])) ?>
            <?php else: ?><?= e(gh((float)$r['budget_min'])) ?> – <?= e(gh((float)$r['budget_max'])) ?><?php endif; ?>
          </b>
        </p>

        <details class="why" style="margin-top:12px">
          <summary>Send a quotation</summary>
          <form method="post" action="<?= e(url('/artisan/requests/' . (int)$r['id'] . '/quote')) ?>" class="stack" style="margin-top:12px">
            <?= csrf() ?>
            <div class="grid g2">
              <div class="field"><label>Labour cost (GH¢)</label><input type="number" name="labour_cost" min="0" step="1" required></div>
              <div class="field"><label>Material cost (GH¢)</label><input type="number" name="material_cost" min="0" step="1" value="0"></div>
              <div class="field"><label>Transport (GH¢)</label><input type="number" name="transport_cost" min="0" step="1" value="0"></div>
              <div class="field"><label>Other charges (GH¢)</label><input type="number" name="additional_charges" min="0" step="1" value="0"></div>
            </div>
            <div class="field"><label>Estimated days to complete</label><input type="number" name="estimated_days" min="1" max="90"></div>
            <div class="field"><label>Notes <span class="muted" style="font-weight:400">(optional)</span></label><textarea name="notes" maxlength="500"></textarea></div>
            <div class="row" style="gap:8px">
              <button class="btn primary" type="submit">Send quotation</button>
            </div>
          </form>
        </details>
        <div class="row" style="margin-top:10px;gap:8px">
          <form method="post" action="<?= e(url('/artisan/requests/' . (int)$r['id'] . '/decline')) ?>"><?= csrf() ?>
            <button class="btn ghost sm" type="submit">Decline this request</button>
          </form>
          <a class="btn ghost sm" href="<?= e(url('/jobs/' . (int)$r['job_request_id'] . '/messages/' . (int)$r['customer_id'])) ?>">💬 Message <?= e($r['customer_name']) ?></a>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if ($quoted): ?>
    <h3 style="margin-top:10px">Your quotations</h3>
    <div class="tw">
      <table>
        <thead><tr><th>Job</th><th>Total</th><th>Days</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($quoted as $q): ?>
          <tr>
            <td><a href="<?= e(url('/jobs/' . (int)$q['job_id'])) ?>"><?= e($q['title']) ?></a></td>
            <td><?= e(gh((float)$q['total_cost'])) ?></td>
            <td><?= $q['estimated_days'] ? (int)$q['estimated_days'] : '—' ?></td>
            <td><span class="pill <?= $q['quote_status'] === 'accepted' ? 'ok' : ($q['quote_status'] === 'sent' ? 'gold' : 'bad') ?>"><?= e(ucfirst($q['quote_status'])) ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
