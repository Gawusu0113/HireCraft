<?php
/** @var array $job */
/** @var array $other */
/** @var array $messages */
/** @var int $me */
$title = 'Messages';
?>
<div class="stack" style="max-width:640px;margin:0 auto">
  <div class="row between">
    <div>
      <span class="eyebrow">Job-specific &amp; private</span>
      <h2 style="margin-top:4px">Message <?= e($other['full_name']) ?></h2>
      <p class="muted small" style="margin-top:4px">About: <a href="<?= e(url('/jobs/' . (int)$job['id'])) ?>"><?= e($job['title']) ?></a></p>
    </div>
    <a class="btn ghost sm" href="<?= e(url('/jobs/' . (int)$job['id'])) ?>">Back to job</a>
  </div>

  <div class="card">
    <?php if (!$messages): ?>
      <p class="muted small" style="text-align:center;padding:20px 0">No messages yet &mdash; say hello about the job.</p>
    <?php else: ?>
      <div class="stack s10">
        <?php foreach ($messages as $m): $mine = (int)$m['sender_id'] === $me; ?>
          <div class="msg <?= $mine ? 'mine' : '' ?>">
            <div class="bubble"><?= nl2br(e($m['body'])) ?></div>
            <span class="muted small"><?= e(fmtDateTime($m['created_at'])) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <form method="post" action="<?= e(url('/jobs/' . (int)$job['id'] . '/messages/' . (int)$other['id'])) ?>" class="row" style="gap:10px;align-items:flex-end">
    <?= csrf() ?>
    <div class="field" style="flex:1;margin:0">
      <textarea name="body" maxlength="1000" required placeholder="Write a message…" style="min-height:56px"></textarea>
    </div>
    <button class="btn primary" type="submit">Send</button>
  </form>
  <p class="muted small">Messages are private to you and <?= e($other['full_name']) ?>. Don't share payment card details or passwords here.</p>
</div>

<style>
.msg{display:flex;flex-direction:column;max-width:75%}
.msg .bubble{background:var(--surface2);border-radius:12px;padding:10px 13px;font-size:14.5px;line-height:1.4}
.msg .muted{margin-top:3px}
.msg.mine{align-self:flex-end;align-items:flex-end}
.msg.mine .bubble{background:var(--green-l);color:var(--ink)}
</style>
