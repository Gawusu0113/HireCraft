<?php
/** @var array $job */
/** @var array $work */
$title = 'Leave a review';
$fields = [
    'overall' => 'Overall experience', 'quality' => 'Quality of work', 'professionalism' => 'Professionalism',
    'communication' => 'Communication', 'punctuality' => 'Punctuality',
];
?>
<div class="card" style="max-width:520px;margin:0 auto">
  <span class="eyebrow">Job complete</span>
  <h2 style="margin-top:4px">How did it go?</h2>
  <p class="muted" style="margin-top:6px">Your review updates this artisan's public rating and trust score.</p>

  <form method="post" action="<?= e(url('/jobs/' . (int)$job['id'] . '/review')) ?>" class="stack" style="margin-top:20px">
    <?= csrf() ?>
    <?php foreach ($fields as $key => $label): ?>
      <div class="field">
        <label for="<?= $key ?>"><?= e($label) ?></label>
        <select id="<?= $key ?>" name="<?= $key ?>" required>
          <option value="5">5 — Excellent</option>
          <option value="4">4 — Good</option>
          <option value="3">3 — Okay</option>
          <option value="2">2 — Below expectations</option>
          <option value="1">1 — Poor</option>
        </select>
      </div>
    <?php endforeach; ?>
    <div class="field">
      <label for="comment">Comment <span class="muted" style="font-weight:400">(optional)</span></label>
      <textarea id="comment" name="comment" maxlength="1000" placeholder="What went well, and what could improve?"></textarea>
    </div>
    <button class="btn primary lg" type="submit">Submit review</button>
  </form>
</div>
