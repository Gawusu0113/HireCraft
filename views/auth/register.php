<?php
/** @var string $role */
$title = 'Create your account';
?>
<div class="card" style="max-width:560px;margin:0 auto">
  <h2>Create your account</h2>
  <p class="muted" style="margin-top:6px">Join HireCraft as a customer looking to hire, or as an artisan looking for work.</p>

  <form method="post" action="<?= e(url('/register')) ?>" class="stack" style="margin-top:20px" id="register-form">
    <?= csrf() ?>
    <div class="field">
      <label>I am a...</label>
      <div class="row" style="gap:10px">
        <label class="opt" style="flex:1">
          <input type="radio" name="role" value="customer" <?= $role === 'customer' ? 'checked' : '' ?>>
          <span><b>Customer</b><span class="muted small">I want to hire an artisan</span></span>
        </label>
        <label class="opt" style="flex:1">
          <input type="radio" name="role" value="artisan" <?= $role === 'artisan' ? 'checked' : '' ?>>
          <span><b>Artisan</b><span class="muted small">I offer skilled trade services</span></span>
        </label>
      </div>
    </div>

    <div class="field">
      <label for="full_name">Full name</label>
      <input type="text" id="full_name" name="full_name" value="<?= old('full_name') ?>" required maxlength="120" autocomplete="name">
    </div>

    <div class="grid g2">
      <div class="field">
        <label for="email">Email address</label>
        <input type="email" id="email" name="email" value="<?= old('email') ?>" required maxlength="190" autocomplete="email">
      </div>
      <div class="field">
        <label for="phone">Phone number</label>
        <input type="tel" id="phone" name="phone" value="<?= old('phone') ?>" required maxlength="20" placeholder="0244xxxxxx" autocomplete="tel">
      </div>
    </div>

    <div class="grid g2">
      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
        <span class="hint">At least 8 characters.</span>
      </div>
      <div class="field">
        <label for="password_confirm">Confirm password</label>
        <input type="password" id="password_confirm" name="password_confirm" required minlength="8" autocomplete="new-password">
      </div>
    </div>

    <button class="btn primary lg" type="submit">Create account</button>
    <p class="small muted" style="text-align:center">Already have an account? <a href="<?= e(url('/login')) ?>">Log in</a></p>
  </form>
</div>
