<?php
/** @var array $categories */
/** @var array $skillsByCategory */
/** @var array $areas */
$title = 'Post a job';
?>
<div class="stack" style="max-width:720px;margin:0 auto">
  <div>
    <span class="eyebrow">New job</span>
    <h2 style="margin-top:4px">Tell us what you need done</h2>
    <p class="muted" style="margin-top:6px">SmartMatch will rank suitable, verified artisans for you as soon as you post — with the reasoning shown for every score.</p>
  </div>

  <form method="post" action="<?= e(url('/jobs')) ?>" class="stack" id="job-form" enctype="multipart/form-data">
    <?= csrf() ?>

    <div class="card">
      <div class="section-h"><span class="idx">1</span><h3>The job</h3></div>
      <div class="stack" style="margin-top:14px">
        <div class="field">
          <label for="title">Short title</label>
          <input type="text" id="title" name="title" maxlength="150" required placeholder="e.g. Fix leaking kitchen pipe">
        </div>
        <div class="field">
          <label>Skills needed</label>
          <span class="hint">Pick skills from one trade, or from more than one if your job spans several trades (e.g. plumbing + tiling).</span>
          <?php foreach ($categories as $c): $skills = $skillsByCategory[$c['id']] ?? []; if (!$skills) continue; ?>
            <div class="stack s8" style="margin-top:6px">
              <b class="small"><?= e($c['name']) ?></b>
              <div class="row" style="gap:8px">
                <?php foreach ($skills as $s): ?>
                  <label class="chip"><input type="checkbox" name="skills[]" value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></label>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="field">
          <label for="description">Describe the job</label>
          <textarea id="description" name="description" minlength="20" required placeholder="What needs to be done? Include any details that would help an artisan quote accurately."></textarea>
          <span class="hint">At least 20 characters.</span>
        </div>
        <div class="field">
          <label for="photos">Photos <span class="muted" style="font-weight:400">(optional)</span></label>
          <input type="file" id="photos" name="photos[]" accept="image/png,image/jpeg,image/webp" multiple>
          <span class="hint">Up to 6 photos of the work needed (JPG, PNG or WebP, 5MB each) — helps artisans quote accurately.</span>
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
                <option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?><?= $a['district'] ? ' (' . e($a['district']) . ')' : '' ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="address_note">Address note <span class="muted" style="font-weight:400">(optional)</span></label>
            <input type="text" id="address_note" name="address_note" maxlength="255" placeholder="e.g. blue gate opposite the church">
          </div>
        </div>
        <div class="grid g2">
          <div class="field">
            <label for="preferred_date">Preferred date <span class="muted" style="font-weight:400">(optional)</span></label>
            <input type="date" id="preferred_date" name="preferred_date">
          </div>
          <div class="field">
            <label for="preferred_time">Preferred time</label>
            <select id="preferred_time" name="preferred_time">
              <option value="any">Any time</option>
              <option value="morning">Morning</option>
              <option value="afternoon">Afternoon</option>
              <option value="evening">Evening</option>
            </select>
          </div>
        </div>
        <div class="field">
          <label>How urgent is this?</label>
          <div class="row" style="gap:10px;flex-wrap:wrap">
            <label class="opt" style="flex:1;min-width:140px"><input type="radio" name="urgency" value="low"><span><b>Low</b><span class="muted small">Whenever is convenient</span></span></label>
            <label class="opt" style="flex:1;min-width:140px"><input type="radio" name="urgency" value="normal" checked><span><b>Normal</b><span class="muted small">Within the next couple of weeks</span></span></label>
            <label class="opt" style="flex:1;min-width:140px"><input type="radio" name="urgency" value="high"><span><b>High</b><span class="muted small">Within a day or two</span></span></label>
            <label class="opt urgent" style="flex:1;min-width:140px"><input type="radio" name="urgency" value="emergency"><span><b>🚨 Emergency</b><span class="muted small">Right now — water leak, power out, broken pipe</span></span></label>
          </div>
          <span class="hint">Emergency jobs are shown first to artisans who've marked themselves as available for emergency call-outs.</span>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="section-h"><span class="idx">3</span><h3>Budget</h3></div>
      <div class="stack" style="margin-top:14px">
        <div class="row" style="gap:10px">
          <label class="chip budget-type-opt"><input type="radio" name="budget_type" value="range" checked>I have a range</label>
          <label class="chip budget-type-opt"><input type="radio" name="budget_type" value="fixed">I have a fixed amount</label>
          <label class="chip budget-type-opt"><input type="radio" name="budget_type" value="unknown">I don't know the price</label>
        </div>
        <div id="budget-range" class="grid g2">
          <div class="field">
            <label for="budget_min">Minimum (GH¢)</label>
            <input type="number" id="budget_min" name="budget_min" min="0" step="1">
          </div>
          <div class="field">
            <label for="budget_max">Maximum (GH¢)</label>
            <input type="number" id="budget_max" name="budget_max" min="0" step="1">
          </div>
        </div>
        <div id="budget-fixed" class="field" style="display:none">
          <label for="fixed_price">Your budget (GH¢)</label>
          <input type="number" id="fixed_price" name="fixed_price" min="0" step="1">
        </div>
        <label class="opt">
          <input type="checkbox" name="is_negotiable" value="1">
          <span><b>My budget is negotiable</b><span class="muted small">Artisans priced a little above your budget may still be shown as a good match.</span></span>
        </label>
        <div class="field">
          <label for="special_requirements">Special requirements <span class="muted" style="font-weight:400">(optional)</span></label>
          <textarea id="special_requirements" name="special_requirements" placeholder="e.g. must bring own ladder, access only after 4pm"></textarea>
        </div>
        <label class="opt">
          <input type="checkbox" name="discoverable" value="1" checked>
          <span><b>Let artisans discover and apply to this job</b><span class="muted small">Besides the SmartMatch shortlist above, this also lists the job on artisans' "Find jobs" page so a wider pool can apply directly. Uncheck to keep it visible only to artisans you request a quotation from.</span></span>
        </label>
      </div>
    </div>

    <button class="btn primary lg" type="submit">Post job &amp; see matches</button>
  </form>
</div>

<script>
(function () {
  var radios = document.querySelectorAll('input[name=budget_type]');
  var range = document.getElementById('budget-range');
  var fixed = document.getElementById('budget-fixed');
  function sync() {
    var v = document.querySelector('input[name=budget_type]:checked').value;
    range.style.display = v === 'range' ? 'grid' : 'none';
    fixed.style.display = v === 'fixed' ? 'flex' : 'none';
  }
  radios.forEach(function (r) { r.addEventListener('change', sync); });
  sync();

  var params = new URLSearchParams(window.location.search);
  if (params.get('urgency') === 'emergency') {
    var em = document.querySelector('input[name=urgency][value=emergency]');
    if (em) em.checked = true;
  }
})();
</script>
