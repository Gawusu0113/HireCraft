<?php
/** @var array $job */
/** @var array $categories */
/** @var array $skillsByCategory */
/** @var array $areas */
/** @var array $skillIds */
/** @var array $photos */
$title = 'Edit job';
?>
<div class="stack" style="max-width:720px;margin:0 auto">
  <div>
    <span class="eyebrow">Edit job</span>
    <h2 style="margin-top:4px"><?= e($job['title']) ?></h2>
    <p class="muted" style="margin-top:6px">Changing the details re-runs SmartMatch, so your shortlist and budget/feasibility checks will refresh once you save.</p>
  </div>

  <form method="post" action="<?= e(url('/jobs/' . (int)$job['id'] . '/edit')) ?>" class="stack" id="job-form" enctype="multipart/form-data">
    <?= csrf() ?>

    <div class="card">
      <div class="section-h"><span class="idx">1</span><h3>The job</h3></div>
      <div class="stack" style="margin-top:14px">
        <div class="field">
          <label for="title">Short title</label>
          <input type="text" id="title" name="title" maxlength="150" required value="<?= e($job['title']) ?>">
        </div>
        <div class="field">
          <label>Skills needed</label>
          <span class="hint">Pick skills from one trade, or from more than one if your job spans several trades (e.g. plumbing + tiling).</span>
          <?php foreach ($categories as $c): $skills = $skillsByCategory[$c['id']] ?? []; if (!$skills) continue; ?>
            <div class="stack s8" style="margin-top:6px">
              <b class="small"><?= e($c['name']) ?></b>
              <div class="row" style="gap:8px">
                <?php foreach ($skills as $s): ?>
                  <label class="chip"><input type="checkbox" name="skills[]" value="<?= (int)$s['id'] ?>" <?= in_array((int)$s['id'], $skillIds, true) ? 'checked' : '' ?>><?= e($s['name']) ?></label>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="field">
          <label for="description">Describe the job</label>
          <textarea id="description" name="description" minlength="20" required><?= e($job['description']) ?></textarea>
          <span class="hint">At least 20 characters.</span>
        </div>

        <?php if ($photos): ?>
          <div class="field">
            <label>Current photos</label>
            <div class="photostrip" style="margin-top:6px">
              <?php foreach ($photos as $p): ?>
                <div style="position:relative">
                  <img src="<?= e(uploadUrl($p['image_path'])) ?>" alt="<?= e($p['caption'] ?: 'Job photo') ?>" loading="lazy">
                  <label class="chip" style="position:absolute;bottom:4px;left:4px;right:4px;justify-content:center;background:rgba(0,0,0,.55);color:#fff">
                    <input type="checkbox" name="remove_photos[]" value="<?= (int)$p['id'] ?>"> Remove
                  </label>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <div class="field">
          <label for="photos">Add more photos <span class="muted" style="font-weight:400">(optional)</span></label>
          <input type="file" id="photos" name="photos[]" accept="image/png,image/jpeg,image/webp" multiple>
          <span class="hint">Up to 6 photos in total (JPG, PNG or WebP, 5MB each).</span>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="section-h"><span class="idx">2</span><h3>Where &amp; when</h3></div>
      <div class="stack" style="margin-top:14px">
        <div class="grid g2">
          <div class="field">
            <label for="area_id">Area</label>
            <select id="area_id" name="area_id" required>
              <option value="">— Choose the job location —</option>
              <?php foreach ($areas as $a): ?>
                <option value="<?= (int)$a['id'] ?>" <?= (int)$job['area_id'] === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['name']) ?><?= $a['district'] ? ' (' . e($a['district']) . ')' : '' ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="address_note">Address note <span class="muted" style="font-weight:400">(optional)</span></label>
            <input type="text" id="address_note" name="address_note" maxlength="255" value="<?= e((string)$job['address_note']) ?>" placeholder="e.g. blue gate opposite the church">
          </div>
        </div>
        <div class="grid g2">
          <div class="field">
            <label for="preferred_date">Preferred date <span class="muted" style="font-weight:400">(optional)</span></label>
            <input type="date" id="preferred_date" name="preferred_date" value="<?= e((string)$job['preferred_date']) ?>">
          </div>
          <div class="field">
            <label for="preferred_time">Preferred time</label>
            <select id="preferred_time" name="preferred_time">
              <?php foreach (['any' => 'Any time', 'morning' => 'Morning', 'afternoon' => 'Afternoon', 'evening' => 'Evening'] as $val => $label): ?>
                <option value="<?= $val ?>" <?= $job['preferred_time'] === $val ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="field">
          <label>How urgent is this?</label>
          <div class="row" style="gap:10px;flex-wrap:wrap">
            <label class="opt" style="flex:1;min-width:140px"><input type="radio" name="urgency" value="low" <?= $job['urgency'] === 'low' ? 'checked' : '' ?>><span><b>Low</b><span class="muted small">Whenever is convenient</span></span></label>
            <label class="opt" style="flex:1;min-width:140px"><input type="radio" name="urgency" value="normal" <?= $job['urgency'] === 'normal' ? 'checked' : '' ?>><span><b>Normal</b><span class="muted small">Within the next couple of weeks</span></span></label>
            <label class="opt" style="flex:1;min-width:140px"><input type="radio" name="urgency" value="high" <?= $job['urgency'] === 'high' ? 'checked' : '' ?>><span><b>High</b><span class="muted small">Within a day or two</span></span></label>
            <label class="opt urgent" style="flex:1;min-width:140px"><input type="radio" name="urgency" value="emergency" <?= $job['urgency'] === 'emergency' ? 'checked' : '' ?>><span><b>🚨 Emergency</b><span class="muted small">Right now — water leak, power out, broken pipe</span></span></label>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="section-h"><span class="idx">3</span><h3>Budget</h3></div>
      <div class="stack" style="margin-top:14px">
        <div class="row" style="gap:10px">
          <label class="chip budget-type-opt"><input type="radio" name="budget_type" value="range" <?= $job['budget_type'] === 'range' ? 'checked' : '' ?>>I have a range</label>
          <label class="chip budget-type-opt"><input type="radio" name="budget_type" value="fixed" <?= $job['budget_type'] === 'fixed' ? 'checked' : '' ?>>I have a fixed amount</label>
          <label class="chip budget-type-opt"><input type="radio" name="budget_type" value="unknown" <?= $job['budget_type'] === 'unknown' ? 'checked' : '' ?>>I don't know the price</label>
        </div>
        <div id="budget-range" class="grid g2">
          <div class="field">
            <label for="budget_min">Minimum (GH¢)</label>
            <input type="number" id="budget_min" name="budget_min" min="0" step="1" value="<?= $job['budget_min'] !== null ? e((string)(float)$job['budget_min']) : '' ?>">
          </div>
          <div class="field">
            <label for="budget_max">Maximum (GH¢)</label>
            <input type="number" id="budget_max" name="budget_max" min="0" step="1" value="<?= $job['budget_type'] === 'range' && $job['budget_max'] !== null ? e((string)(float)$job['budget_max']) : '' ?>">
          </div>
        </div>
        <div id="budget-fixed" class="field" style="display:none">
          <label for="fixed_price">Your budget (GH¢)</label>
          <input type="number" id="fixed_price" name="fixed_price" min="0" step="1" value="<?= $job['budget_type'] === 'fixed' && $job['budget_max'] !== null ? e((string)(float)$job['budget_max']) : '' ?>">
        </div>
        <label class="opt">
          <input type="checkbox" name="is_negotiable" value="1" <?= $job['is_negotiable'] ? 'checked' : '' ?>>
          <span><b>My budget is negotiable</b><span class="muted small">Artisans priced a little above your budget may still be shown as a good match.</span></span>
        </label>
        <div class="field">
          <label for="special_requirements">Special requirements <span class="muted" style="font-weight:400">(optional)</span></label>
          <textarea id="special_requirements" name="special_requirements"><?= e((string)$job['special_requirements']) ?></textarea>
        </div>
        <label class="opt">
          <input type="checkbox" name="discoverable" value="1" <?= $job['visibility'] === 'public' ? 'checked' : '' ?>>
          <span><b>Let artisans discover and apply to this job</b><span class="muted small">Besides the SmartMatch shortlist, this also lists the job on artisans' "Find jobs" page so a wider pool can apply directly. Uncheck to keep it visible only to artisans you request a quotation from.</span></span>
        </label>
      </div>
    </div>

    <div class="row" style="gap:10px">
      <button class="btn primary lg" type="submit">Save changes &amp; refresh matches</button>
      <a class="btn ghost lg" href="<?= e(url('/jobs/' . (int)$job['id'])) ?>">Cancel</a>
    </div>
  </form>
</div>

<script>
(function () {
  var radios = document.querySelectorAll('input[name=budget_type]');
  var range = document.getElementById('budget-range');
  var fixed = document.getElementById('budget-fixed');
  function sync() {
    var checked = document.querySelector('input[name=budget_type]:checked');
    var v = checked ? checked.value : 'range';
    range.style.display = v === 'range' ? 'grid' : 'none';
    fixed.style.display = v === 'fixed' ? 'flex' : 'none';
  }
  radios.forEach(function (r) { r.addEventListener('change', sync); });
  sync();
})();
</script>
