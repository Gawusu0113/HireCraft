<?php
/** @var array $areas */
$title = 'Set up your profile';
?>
<div class="card" style="max-width:520px;margin:0 auto">
  <span class="eyebrow">One last step</span>
  <h2 style="margin-top:4px">Where should we look for artisans?</h2>
  <p class="muted" style="margin-top:6px">This helps us judge how close an artisan's service area is to you. You can skip this and set it later.</p>

  <form method="post" action="<?= e(url('/profile/setup')) ?>" class="stack" style="margin-top:20px">
    <?= csrf() ?>
    <div class="field">
      <label for="area_id">Your area</label>
      <select id="area_id" name="area_id">
        <option value="">— Not sure yet —</option>
        <?php foreach ($areas as $a): ?>
          <option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?><?= $a['district'] ? ' (' . e($a['district']) . ')' : '' ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="address_note">Address note <span class="muted" style="font-weight:400">(optional)</span></label>
      <input type="text" id="address_note" name="address_note" maxlength="255" placeholder="e.g. near Suame roundabout, blue gate">
    </div>
    <button class="btn primary lg" type="submit">Finish setup</button>
  </form>
</div>
