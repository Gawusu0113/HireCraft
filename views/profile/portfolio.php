<?php
/** @var array $items */
/** @var array $skills */
$title = 'My portfolio';
?>
<div class="stack">
  <div>
    <span class="eyebrow">Artisan</span>
    <h2 style="margin-top:4px">My portfolio</h2>
    <p class="muted small" style="margin-top:6px">Photos of finished work that customers see when comparing you to other artisans. Tagging a project with the skills it demonstrates also strengthens your "similar work" match score.</p>
  </div>

  <div class="card">
    <h3>Add a project</h3>
    <form method="post" action="<?= e(url('/profile/portfolio')) ?>" class="stack" style="margin-top:12px" enctype="multipart/form-data">
      <?= csrf() ?>
      <div class="grid g2">
        <div class="field">
          <label for="pf-title">Project title</label>
          <input type="text" id="pf-title" name="title" maxlength="120" required placeholder="e.g. Full bathroom re-plumb, East Legon">
        </div>
        <div class="field">
          <label for="pf-date">Completed on <span class="muted" style="font-weight:400">(optional)</span></label>
          <input type="date" id="pf-date" name="completed_on">
        </div>
      </div>
      <div class="field">
        <label for="pf-desc">Description <span class="muted" style="font-weight:400">(optional)</span></label>
        <textarea id="pf-desc" name="description" maxlength="500" placeholder="What did the job involve?"></textarea>
      </div>
      <div class="field">
        <label>Skills demonstrated <span class="muted" style="font-weight:400">(optional)</span></label>
        <div class="row" style="gap:8px">
          <?php foreach ($skills as $s): ?>
            <label class="chip"><input type="checkbox" name="skills[]" value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="field">
        <label for="pf-photos">Photos</label>
        <input type="file" id="pf-photos" name="photos[]" accept="image/png,image/jpeg,image/webp" multiple required>
        <span class="hint">Up to 6 photos (JPG, PNG or WebP, 5MB each).</span>
      </div>
      <div class="row">
        <button class="btn primary" type="submit">Add to portfolio</button>
      </div>
    </form>
  </div>

  <?php if (!$items): ?>
    <div class="card tint" style="text-align:center;padding:30px">
      <h3>No portfolio items yet</h3>
      <p class="muted small" style="margin-top:6px">Add your first finished-work photos above — it's one of the things customers compare artisans on.</p>
    </div>
  <?php else: ?>
    <?php foreach ($items as $item): ?>
      <div class="card">
        <div class="row between">
          <div>
            <h3><?= e($item['title']) ?></h3>
            <p class="muted small" style="margin-top:4px">
              <?= $item['completed_on'] ? e(fmtDate($item['completed_on'])) . ' · ' : '' ?>
              <?php foreach ($item['skills'] as $sk): ?><span class="chip static" style="margin-right:4px"><?= e($sk['name']) ?></span><?php endforeach; ?>
            </p>
          </div>
          <form method="post" action="<?= e(url('/profile/portfolio/' . (int)$item['id'] . '/delete')) ?>" onsubmit="return confirm('Remove this project from your portfolio?');">
            <?= csrf() ?>
            <button class="btn ghost sm" type="submit">Remove</button>
          </form>
        </div>
        <?php if ($item['description']): ?>
          <p class="small" style="margin-top:8px"><?= nl2br(e($item['description'])) ?></p>
        <?php endif; ?>
        <div class="photogrid" style="margin-top:12px">
          <?php foreach ($item['images'] as $img): ?>
            <figure>
              <a href="<?= e(uploadUrl($img['image_path'])) ?>" target="_blank" rel="noopener">
                <img src="<?= e(uploadUrl($img['image_path'])) ?>" alt="<?= e($item['title']) ?>" loading="lazy">
              </a>
            </figure>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
