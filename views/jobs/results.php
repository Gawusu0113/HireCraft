<?php
/** @var array $job */
/** @var array $requiredSkills */
/** @var array $photos */
/** @var array|null $budget */
/** @var array|null $feasibility */
/** @var array $groups */
/** @var bool $isMultiTrade */
/** @var bool $isEditable */
$title = $job['title'];

$assessmentInfo = [
    'high_budget' => ['label' => 'High budget', 'class' => 'ok'],
    'highly_competitive' => ['label' => 'Highly competitive', 'class' => 'ok'],
    'likely_sufficient' => ['label' => 'Likely sufficient', 'class' => 'ok'],
    'negotiation_recommended' => ['label' => 'Negotiation recommended', 'class' => 'warn'],
    'possibly_insufficient' => ['label' => 'Possibly insufficient', 'class' => 'bad'],
    'insufficient_data' => ['label' => 'Not enough data', 'class' => 'mute'],
];
$feasibilityInfo = [
    'high' => ['label' => 'High feasibility', 'class' => 'ok'],
    'medium' => ['label' => 'Medium feasibility', 'class' => 'warn'],
    'low' => ['label' => 'Low feasibility', 'class' => 'bad'],
    'requires_more_information' => ['label' => 'Needs more information', 'class' => 'mute'],
];
$levelInfo = [
    'highly_recommended' => ['label' => 'HIGHLY RECOMMENDED', 'lvl' => 'lvl-high'],
    'recommended' => ['label' => 'RECOMMENDED', 'lvl' => 'lvl-rec'],
    'possible_match' => ['label' => 'POSSIBLE MATCH', 'lvl' => 'lvl-poss'],
    'low_compatibility' => ['label' => 'LOW COMPATIBILITY', 'lvl' => 'lvl-low'],
];
$groupNames = array_keys($groups);
?>
<div class="stack">
  <div class="row between">
    <div>
      <span class="eyebrow"><?= e($job['category_name']) ?> · <?= e($job['area_name']) ?></span>
      <h2 style="margin-top:4px"><?= e($job['title']) ?></h2>
      <div class="row" style="margin-top:8px;gap:8px">
        <?php foreach ($requiredSkills as $s): ?><span class="chip static"><?= e($s['name']) ?></span><?php endforeach; ?>
      </div>
      <p class="muted small" style="margin-top:10px"><?= nl2br(e($job['description'])) ?></p>
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
    <div class="stack s8" style="align-items:flex-end">
      <a class="btn ghost sm" href="<?= e(url('/jobs/' . (int)$job['id'])) ?>">View quotations &amp; status</a>
      <?php if ($isEditable): ?>
        <a class="btn ghost sm" href="<?= e(url('/jobs/' . (int)$job['id'] . '/edit')) ?>">✏️ Edit job details</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if (array_sum(array_map('count', $groups)) > 1): ?>
  <div class="card" id="compare-bar" style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 16px">
    <span class="muted small" id="compare-hint">Tick 2&ndash;3 artisans below to compare them side by side.</span>
    <button type="button" id="compare-btn" class="btn primary sm" disabled data-url="<?= e(url('/jobs/' . (int)$job['id'] . '/compare')) ?>">Compare selected (0)</button>
  </div>
  <?php endif; ?>

  <?php if ($isMultiTrade): ?>
    <div class="note">This job needs more than one trade. We've split it and ranked artisans separately for each — you'll likely need to hire one artisan per trade, or split the job into milestones.</div>
  <?php endif; ?>

  <div class="grid g2">
    <?php if ($budget): $bi = $assessmentInfo[$budget['assessment']] ?? ['label' => $budget['assessment'], 'class' => 'mute']; ?>
      <div class="card">
        <div class="row between"><h3>Budget check</h3><span class="pill <?= e($bi['class']) ?>"><?= e($bi['label']) ?></span></div>
        <p class="small" style="margin-top:10px"><?= e($budget['reason']) ?></p>
        <?php if ($budget['benchmark_n'] > 0): ?>
          <p class="muted xs" style="margin-top:8px">Based on <?= (int)$budget['benchmark_n'] ?> similar jobs: typical range <?= e(gh((float)$budget['benchmark_p25'])) ?>–<?= e(gh((float)$budget['benchmark_p75'])) ?>, median <?= e(gh((float)$budget['benchmark_median'])) ?>. Confidence: <?= e(ucfirst($budget['confidence_level'])) ?>.</p>
        <?php endif; ?>
        <div class="note" style="margin-top:10px"><?= e($budget['recommended_action']) ?></div>
      </div>
    <?php endif; ?>
    <?php if ($feasibility): $fi = $feasibilityInfo[$feasibility['level']] ?? ['label' => $feasibility['level'], 'class' => 'mute']; ?>
      <div class="card">
        <div class="row between"><h3>Feasibility</h3><span class="pill <?= e($fi['class']) ?>"><?= e($fi['label']) ?></span></div>
        <ul class="exl" style="margin-top:10px">
          <?php foreach (json_decode($feasibility['reasons'], true) ?: [] as $r): ?>
            <li><b class="dot" style="background:var(--muted)"></b><span><?= e($r) ?></span></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
  </div>

  <?php if (count($groupNames) > 1): ?>
    <div class="tabs" id="trade-tabs">
      <?php foreach ($groupNames as $i => $name): ?>
        <a href="#" data-tab="<?= $i ?>" class="<?= $i === 0 ? 'current' : '' ?>"><?= e($name) ?> (<?= count($groups[$name]) ?>)</a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!$groupNames): ?>
    <div class="card tint" style="text-align:center;padding:30px">
      <h3>No suitable artisans found</h3>
      <p class="muted small" style="margin-top:6px">Try widening your budget or checking back once more artisans join.</p>
    </div>
  <?php endif; ?>

  <?php foreach ($groupNames as $gi => $name): $list = $groups[$name]; ?>
    <div class="stack trade-panel" data-panel="<?= $gi ?>" style="<?= $gi === 0 ? '' : 'display:none' ?>">
      <?php if (!$list): ?>
        <div class="card tint" style="text-align:center;padding:30px">
          <h3>No suitable artisans found<?= count($groupNames) > 1 ? ' for ' . e($name) : '' ?></h3>
          <p class="muted small" style="margin-top:6px">Try widening your budget or checking back once more artisans join.</p>
        </div>
      <?php endif; ?>
      <?php foreach ($list as $rank => $m):
        $lv = $levelInfo[$m['recommendation_level']] ?? ['label' => $m['recommendation_level'], 'lvl' => 'lvl-poss'];
        $explanation = $m['explanation'];
        $weights = json_decode($m['weights_snapshot'], true) ?: [];
        $breakdown = [
            'skill' => ['Skill match', (float)$m['skill_match_score']],
            'location' => ['Location', (float)$m['location_match_score']],
            'budget' => ['Budget', (float)$m['budget_match_score']],
            'availability' => ['Availability', (float)$m['availability_score']],
            'experience' => ['Experience', (float)$m['experience_score']],
            'trust' => ['Trust', (float)$m['trust_score']],
            'rating' => ['Rating', (float)$m['rating_score']],
            'portfolio' => ['Portfolio', (float)$m['portfolio_relevance_score']],
        ];
      ?>
        <div class="card acard <?= $rank === 0 ? 'top' : '' ?> <?= e($lv['lvl']) ?>">
          <div class="rk"><?= $rank === 0 ? '★' : '#' . ($rank + 1) ?></div>
          <div>
            <h3><a href="<?= e(url('/artisans/' . (int)$m['artisan_id'])) ?>" style="color:inherit;text-decoration:none"><?= e($m['business_name'] ?: $m['full_name']) ?></a></h3>
            <p class="muted small" style="margin-top:2px"><?= e($m['full_name']) ?> · <?= e($m['artisan_area']) ?> · <?= (int)$m['years_experience'] ?> yrs</p>
            <div class="facts" style="margin-top:8px">
              <span>Trust <b><?= (int)$m['artisan_trust_score'] ?>/100</b></span>
              <span>Rating <b><?= $m['rating_count'] > 0 ? number_format((float)$m['rating_mean'], 1) . ' (' . (int)$m['rating_count'] . ')' : 'No reviews yet' ?></b></span>
            </div>
            <label class="opt" style="margin-top:8px;padding:6px 10px">
              <input type="checkbox" class="cmp-check" value="<?= (int)$m['artisan_id'] ?>">
              <span class="small">Add to comparison</span>
            </label>
            <?php if ($m['capped_reason']): ?>
              <div class="cap">⚠ Capped: <?= e($m['capped_reason']) ?></div>
            <?php endif; ?>
            <details class="why">
              <summary>Why this match?</summary>
              <ul class="exl" style="margin-top:10px">
                <?php foreach ($explanation as $line): ?>
                  <li class="<?= e($line['status']) ?>"><b class="dot"></b><span><?= e($line['text']) ?></span></li>
                <?php endforeach; ?>
              </ul>
              <div class="stack s8" style="margin-top:12px">
                <?php foreach ($breakdown as $key => [$label, $pts]): $max = (float)($weights[$key] ?? 0); $pct = $max > 0 ? min(100, ($pts / $max) * 100) : 0; ?>
                  <div class="brk">
                    <span class="lbl"><?= e($label) ?></span>
                    <span class="meter"><i style="width:<?= round($pct, 1) ?>%"></i></span>
                    <span class="pts"><?= number_format($pts, 1) ?>/<?= number_format($max, 0) ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
            </details>
          </div>
          <div class="score">
            <div class="ring" style="--p:<?= (float)$m['overall_match_score'] ?>"><b><?= (int)round((float)$m['overall_match_score']) ?></b></div>
            <div class="pill" style="margin-top:8px;white-space:nowrap"><?= e($lv['label']) ?></div>
            <?php if (!in_array($job['status'], ['hired', 'in_progress', 'completed', 'reviewed'], true)): ?>
              <form method="post" action="<?= e(url('/jobs/' . (int)$job['id'] . '/request/' . (int)$m['artisan_id'])) ?>" style="margin-top:8px">
                <?= csrf() ?>
                <button class="btn ghost sm" type="submit">Request quotation</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

  <?php if ($groupNames): ?>
  <div class="card">
    <h3>Was this helpful?</h3>
    <p class="muted small" style="margin-top:4px">Your answers help evaluate how useful and understandable SmartMatch's recommendations are &mdash; used for this project's own evaluation, not shared with artisans.</p>
    <?php if ($feedback): ?>
      <div class="note" style="margin-top:10px">You already shared feedback on this job. Submitting again below will update it.</div>
    <?php endif; ?>
    <form method="post" action="<?= e(url('/jobs/' . (int)$job['id'] . '/feedback')) ?>" class="stack" style="margin-top:12px">
      <?= csrf() ?>
      <div class="field">
        <label>Were these recommendations helpful?</label>
        <div class="row" style="gap:8px">
          <?php foreach (['yes' => 'Yes', 'partially' => 'Partially', 'no' => 'No'] as $val => $label): ?>
            <label class="chip"><input type="radio" name="helpful" value="<?= e($val) ?>" <?= ($feedback['helpful'] ?? '') === $val ? 'checked' : '' ?> required><?= e($label) ?></label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="field">
        <label>How easy was it to understand <i>why</i> artisans were recommended? <span class="muted" style="font-weight:400">(1 = very confusing, 5 = very clear)</span></label>
        <div class="row" style="gap:8px">
          <?php for ($i = 1; $i <= 5; $i++): ?>
            <label class="chip"><input type="radio" name="understood_explanation" value="<?= $i ?>" <?= (int)($feedback['understood_explanation'] ?? 0) === $i ? 'checked' : '' ?>><?= $i ?></label>
          <?php endfor; ?>
        </div>
      </div>
      <div class="grid g2">
        <div class="field">
          <label for="fb-artisan">If you'd hire someone from this list, who? <span class="muted" style="font-weight:400">(optional)</span></label>
          <select id="fb-artisan" name="chosen_artisan_id">
            <option value="">&mdash; Not decided yet &mdash;</option>
            <?php foreach ($matches as $m): ?>
              <option value="<?= (int)$m['artisan_id'] ?>" <?= (int)($feedback['chosen_artisan_id'] ?? 0) === (int)$m['artisan_id'] ? 'selected' : '' ?>><?= e($m['business_name'] ?: $m['full_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="fb-reason">Main reason for that choice <span class="muted" style="font-weight:400">(optional)</span></label>
          <select id="fb-reason" name="reason_chosen">
            <option value="">&mdash; Not sure &mdash;</option>
            <?php foreach ([
              'best_match' => 'Best overall match', 'lowest_price' => 'Lowest price', 'highest_rating' => 'Highest rating',
              'most_trusted' => 'Most trusted/verified', 'most_experienced' => 'Most experienced',
              'recommended_by_hirecraft' => 'HireCraft recommended them first', 'other' => 'Other',
            ] as $val => $label): ?>
              <option value="<?= e($val) ?>" <?= ($feedback['reason_chosen'] ?? '') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="field">
        <label for="fb-comment">Anything else? <span class="muted" style="font-weight:400">(optional)</span></label>
        <textarea id="fb-comment" name="comment" maxlength="500" placeholder="What worked, what didn't, what you'd want to see..."><?= e($feedback['comment'] ?? '') ?></textarea>
      </div>
      <div class="row">
        <button class="btn primary sm" type="submit"><?= $feedback ? 'Update feedback' : 'Send feedback' ?></button>
      </div>
    </form>
  </div>
  <?php endif; ?>
</div>

<script>
(function () {
  var tabs = document.querySelectorAll('#trade-tabs a');
  var panels = document.querySelectorAll('.trade-panel');
  tabs.forEach(function (t) {
    t.addEventListener('click', function (e) {
      e.preventDefault();
      tabs.forEach(function (x) { x.classList.remove('current'); });
      t.classList.add('current');
      panels.forEach(function (p) { p.style.display = p.dataset.panel === t.dataset.tab ? '' : 'none'; });
    });
  });
})();
(function () {
  var checks = document.querySelectorAll('.cmp-check');
  var btn = document.getElementById('compare-btn');
  var hint = document.getElementById('compare-hint');
  if (!checks.length || !btn) return;
  var MAX = 3;

  function refresh() {
    var checked = Array.prototype.filter.call(checks, function (c) { return c.checked; });
    checks.forEach(function (c) { if (!c.checked) c.disabled = checked.length >= MAX; });
    btn.textContent = 'Compare selected (' + checked.length + ')';
    btn.disabled = checked.length < 2;
    hint.textContent = checked.length >= MAX
      ? 'Maximum of ' + MAX + ' artisans at a time.'
      : 'Tick 2–3 artisans below to compare them side by side.';
  }

  checks.forEach(function (c) { c.addEventListener('change', refresh); });
  btn.addEventListener('click', function () {
    var ids = Array.prototype.filter.call(checks, function (c) { return c.checked; }).map(function (c) { return c.value; });
    if (ids.length < 2) return;
    var qs = ids.map(function (id) { return 'artisans[]=' + encodeURIComponent(id); }).join('&');
    window.location.href = btn.dataset.url + '?' + qs;
  });
  refresh();
})();
</script>
