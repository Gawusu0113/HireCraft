<?php
/** @var string|null $avatarPath */
use HireCraft\Support\Auth;
$title = 'Profile picture';
$initial = mb_strtoupper(mb_substr((string)Auth::name(), 0, 1));
?>
<div class="stack" style="max-width:480px;margin:0 auto">
  <div>
    <span class="eyebrow">Account</span>
    <h2 style="margin-top:4px">Profile picture</h2>
    <p class="muted small" style="margin-top:6px">Shown next to your name across HireCraft &mdash; in the top bar, and on your public profile if you're an artisan.</p>
  </div>

  <div class="card" style="text-align:center">
    <div class="av xl user" style="margin:0 auto">
      <?php if ($avatarPath): ?>
        <img src="<?= e(uploadUrl($avatarPath)) ?>" alt="Your profile picture">
      <?php else: ?>
        <?= e($initial) ?>
      <?php endif; ?>
    </div>

    <form method="post" action="<?= e(url('/profile/picture')) ?>" enctype="multipart/form-data" class="stack" style="margin-top:20px;text-align:left">
      <?= csrf() ?>
      <div class="field">
        <label for="avatar"><?= $avatarPath ? 'Replace photo' : 'Upload a photo' ?></label>
        <input type="file" id="avatar" name="avatar" accept="image/png,image/jpeg,image/webp" required>
        <span class="hint">JPG, PNG or WebP, up to 5MB. Square photos look best.</span>
      </div>
      <button class="btn primary" type="submit">Save photo</button>
    </form>

    <?php if ($avatarPath): ?>
      <form method="post" action="<?= e(url('/profile/picture/remove')) ?>" style="margin-top:10px">
        <?= csrf() ?>
        <button class="btn ghost sm" type="submit">Remove photo</button>
      </form>
    <?php endif; ?>
  </div>
</div>
