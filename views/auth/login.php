<?php $title = 'Log in'; ?>
<div class="card" style="max-width:420px;margin:0 auto">
  <h2>Log in</h2>
  <p class="muted" style="margin-top:6px">Welcome back to HireCraft.</p>

  <form method="post" action="<?= e(url('/login')) ?>" class="stack" style="margin-top:20px">
    <?= csrf() ?>
    <div class="field">
      <label for="email">Email address</label>
      <input type="email" id="email" name="email" value="<?= old('email') ?>" required autocomplete="email" autofocus>
    </div>
    <div class="field">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required autocomplete="current-password">
    </div>
    <button class="btn primary lg" type="submit">Log in</button>
    <p class="small muted" style="text-align:center">New to HireCraft? <a href="<?= e(url('/register')) ?>">Create an account</a></p>
  </form>

  <div class="divider"></div>
  <p class="xs muted">Demo accounts (password <code>Passw0rd!</code>): <code>admin@hirecraft.test</code>, <code>customer@hirecraft.test</code>, <code>kwame.boateng@hirecraft.test</code>.</p>
</div>
