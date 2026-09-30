<?php
/** @var array $categories */
/** @var array $skillsByCategory */
/** @var array $areas */
/** @var array $weekdays */
$title = 'Set up your artisan profile';
?>
<div class="stack" style="max-width:720px;margin:0 auto">
  <div>
    <span class="eyebrow">One-time setup</span>
    <h2 style="margin-top:4px">Set up your artisan profile</h2>
    <p class="muted" style="margin-top:6px">This is what customers and SmartMatch see: your trade, skills, coverage area, schedule and typical prices. You can update it any time.</p>
  </div>

  <ul class="stepper">
    <li class="now">Trade &amp; basics</li>
    <li>Skills &amp; areas</li>
    <li>Availability</li>
    <li>Pricing</li>
  </ul>

  <form method="post" action="<?= e(url('/profile/setup')) ?>" class="stack" id="artisan-setup">
    <?= csrf() ?>

    <div class="card">
      <div class="section-h"><span class="idx">1</span><h3>Trade &amp; basics</h3></div>
      <div class="stack" style="margin-top:14px">
        <div class="field">
          <label>Your trade</label>
          <div class="tradegrid">
            <?php foreach ($categories as $c): ?>
              <label class="trade">
                <input type="radio" name="category_id" value="<?= (int)$c['id'] ?>" class="cat-radio" required>
                <span class="av <?= e($c['slug']) ?>"><?= e(mb_substr($c['name'], 0, 1)) ?></span>
                <b><?= e($c['name']) ?></b>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="grid g2">
          <div class="field">
            <label for="business_name">Business name <span class="muted" style="font-weight:400">(optional)</span></label>
            <input type="text" id="business_name" name="business_name" maxlength="120" placeholder="e.g. Boateng Plumbing Works">
          </div>
          <div class="field">
            <label for="years_experience">Years of experience</label>
            <input type="number" id="years_experience" name="years_experience" min="0" max="60" value="2" required>
          </div>
        </div>
        <div class="field">
          <label for="bio">Short bio <span class="muted" style="font-weight:400">(optional)</span></label>
          <textarea id="bio" name="bio" maxlength="600" placeholder="Tell customers about your experience and specialities."></textarea>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="section-h"><span class="idx">2</span><h3>Skills &amp; coverage area</h3></div>
      <div class="stack" style="margin-top:14px">
        <div class="field">
          <label>Skills you offer</label>
          <span class="hint">Choose your trade above first — matching skills will appear here.</span>
          <div id="skills-empty" class="note">Choose a trade to see its skills.</div>
          <?php foreach ($skillsByCategory as $catId => $skills): ?>
            <div class="row skill-group" data-category="<?= (int)$catId ?>" style="display:none;gap:8px">
              <?php foreach ($skills as $s): ?>
                <label class="chip">
                  <input type="checkbox" name="skills[]" value="<?= (int)$s['id'] ?>">
                  <?= e($s['name']) ?>
                </label>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="grid g2">
          <div class="field">
            <label for="base_area_id">Base area</label>
            <select id="base_area_id" name="base_area_id" required>
              <option value="">— Choose your area —</option>
              <?php foreach ($areas as $a): ?>
                <option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?><?= $a['district'] ? ' (' . e($a['district']) . ')' : '' ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="travel_radius_km">Travel radius (km)</label>
            <input type="number" id="travel_radius_km" name="travel_radius_km" min="1" max="50" value="8" required>
          </div>
        </div>
        <div class="field">
          <label>Other areas you also serve <span class="muted" style="font-weight:400">(optional)</span></label>
          <div class="row" style="gap:8px">
            <?php foreach ($areas as $a): ?>
              <label class="chip"><input type="checkbox" name="service_areas[]" value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?></label>
            <?php endforeach; ?>
          </div>
        </div>
        <label class="opt">
          <input type="checkbox" name="accepts_emergency" value="1">
          <span><b>I accept emergency/urgent jobs</b><span class="muted small">Urgent jobs give extra weight to location and availability when matching.</span></span>
        </label>
      </div>
    </div>

    <div class="card">
      <div class="section-h"><span class="idx">3</span><h3>Availability</h3></div>
      <div class="stack" style="margin-top:14px">
        <div class="field">
          <label>Days you usually work</label>
          <div class="row" style="gap:8px">
            <?php foreach ($weekdays as $i => $wd): ?>
              <label class="chip"><input type="checkbox" name="work_days[]" value="<?= $i ?>" <?= $i >= 1 && $i <= 5 ? 'checked' : '' ?>><?= e($wd) ?></label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="grid g2">
          <div class="field">
            <label for="start_time">Usual start time</label>
            <input type="time" id="start_time" name="start_time" value="08:00" required>
          </div>
          <div class="field">
            <label for="end_time">Usual end time</label>
            <input type="time" id="end_time" name="end_time" value="17:00" required>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="section-h"><span class="idx">4</span><h3>Typical pricing</h3></div>
      <p class="muted small" style="margin-top:6px">Your usual labour price range per job complexity. This powers the budget-compatibility check customers see before they hire.</p>
      <div class="stack" style="margin-top:12px">
        <?php foreach (['simple' => 'Simple jobs', 'medium' => 'Medium jobs', 'complex' => 'Complex jobs'] as $key => $label): ?>
          <div class="grid g2">
            <div class="field">
              <label for="price_min_<?= $key ?>"><?= e($label) ?> — minimum (GH¢)</label>
              <input type="number" id="price_min_<?= $key ?>" name="price_min_<?= $key ?>" min="0" step="1" required>
            </div>
            <div class="field">
              <label for="price_max_<?= $key ?>"><?= e($label) ?> — maximum (GH¢)</label>
              <input type="number" id="price_max_<?= $key ?>" name="price_max_<?= $key ?>" min="0" step="1" required>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="note">Your profile is reviewed by an admin before it appears in customer matches — this usually happens quickly.</div>
    <button class="btn primary lg" type="submit">Finish setup</button>
  </form>
</div>

<script>
(function () {
  var radios = document.querySelectorAll('.cat-radio');
  var groups = document.querySelectorAll('.skill-group');
  var empty = document.getElementById('skills-empty');
  function sync() {
    var chosen = document.querySelector('.cat-radio:checked');
    var catId = chosen ? chosen.value : null;
    groups.forEach(function (g) {
      var on = catId && g.dataset.category === catId;
      g.style.display = on ? 'flex' : 'none';
      if (!on) g.querySelectorAll('input[type=checkbox]').forEach(function (cb) { cb.checked = false; });
    });
    empty.style.display = catId ? 'none' : 'block';
  }
  radios.forEach(function (r) { r.addEventListener('change', sync); });
  sync();
})();
</script>
