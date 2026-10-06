<?php
/** @var array $categories */
use HireCraft\Support\Auth;
$title = '';
$tradeIcon = ['plumbing' => 'P', 'electrical' => 'E', 'carpentry' => 'C', 'masonry' => 'M', 'painting' => 'A'];
$emergencyUrl = Auth::check() && Auth::role() === 'customer' ? url('/jobs/new?urgency=emergency') : url('/register?role=customer&next=emergency');
?>
<section class="hero">
  <div>
    <span class="eyebrow">Kumasi · Explainable artisan matching</span>
    <h1>Find the right artisan for your job — and see exactly why.</h1>
    <p class="lead">HireCraft ranks verified plumbers, electricians, carpenters, masons and painters against your job, your budget and your schedule, and shows the reasoning behind every match: no black box.</p>
    <div class="row" style="margin-top:22px">
      <a class="btn primary lg" href="<?= e(url('/register?role=customer')) ?>">Post a job</a>
      <a class="btn ghost lg" href="<?= e(url('/register?role=artisan')) ?>">Join as an artisan</a>
    </div>
    <div class="row" style="margin-top:20px;gap:22px">
      <div class="kpi"><b>13+</b><span>verified artisans (demo)</span></div>
      <div class="kpi"><b>16</b><span>areas in Kumasi</span></div>
      <div class="kpi"><b>8</b><span>weighted match factors</span></div>
    </div>
  </div>
  <div class="card">
    <h3>Why customers trust the ranking</h3>
    <ul class="stack s8" style="list-style:none;padding:0;margin-top:10px">
      <li>✓ Skill match, location, budget fit, availability, experience, trust, rating and portfolio — all shown, all scored</li>
      <li>✓ A budget check before you commit: is your figure realistic for this job?</li>
      <li>✓ A feasibility read: is there enough supply near you to get this done soon?</li>
    </ul>
  </div>
</section>

<section class="stack" style="margin-top:8px">
  <h2>Trades on HireCraft</h2>
  <div class="tradegrid">
    <?php foreach ($categories as $c): $slug = $c['slug']; ?>
      <div class="trade">
        <span class="av <?= e($slug) ?>"><?= e($tradeIcon[$slug] ?? mb_substr($c['name'], 0, 1)) ?></span>
        <b><?= e($c['name']) ?></b>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="grid g3" style="margin-top:36px">
  <div class="card">
    <div class="section-h"><span class="idx">1</span><h3>Describe the job</h3></div>
    <p class="muted small" style="margin-top:8px">Tell us the trade, the skills needed, your area, your budget and how urgent it is.</p>
  </div>
  <div class="card">
    <div class="section-h"><span class="idx">2</span><h3>See ranked matches</h3></div>
    <p class="muted small" style="margin-top:8px">SmartMatch scores every eligible artisan and explains each score in plain language.</p>
  </div>
  <div class="card">
    <div class="section-h"><span class="idx">3</span><h3>Hire with confidence</h3></div>
    <p class="muted small" style="margin-top:8px">Compare quotations, check trust scores and verified history, then hire and track the job.</p>
  </div>
</section>

<section class="card tint" style="margin-top:36px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
  <div>
    <h3>🚨 Water leak, power out, broken pipe?</h3>
    <p class="muted small" style="margin-top:6px">Post an emergency job and it's shown first to nearby artisans who take emergency call-outs.</p>
  </div>
  <a class="btn primary" href="<?= e($emergencyUrl) ?>">Request emergency service</a>
</section>
