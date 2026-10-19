<?php $title = 'Change password'; ?>
<div class="row between">
  <div>
    <span class="eyebrow">Account security</span>
    <h2 style="margin-top:4px">Change password</h2>
  </div>
  <a class="btn ghost" href="<?= e(url('/dashboard')) ?>">Back to dashboard</a>
</div>

<div class="card" style="max-width:520px;margin-top:22px">
  <form method="post" action="<?= e(url('/profile/password')) ?>" class="stack">
    <?= csrf() ?>
    <div class="field">
      <label for="current_password">Current password</label>
      <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
    </div>
    <div class="field">
      <label for="new_password">New password</label>
      <input type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password">
      <span class="hint">Use at least 8 characters.</span>
    </div>
    <div class="field">
      <label for="new_password_confirm">Confirm new password</label>
      <input type="password" id="new_password_confirm" name="new_password_confirm" required minlength="8" autocomplete="new-password">
    </div>
    <button class="btn primary" type="submit" style="align-self:flex-start">Update password</button>
  </form>
</div>