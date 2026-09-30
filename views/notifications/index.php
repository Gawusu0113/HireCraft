<?php
/** @var array $notifications */
$title = 'Notifications';
$icons = [
    'match' => '🎯', 'application' => '🙋', 'quotation' => '💷', 'accepted' => '✅',
    'status' => '🛠️', 'review' => '⭐', 'message' => '💬', 'dispute' => '⚠️', 'report' => '🚩', 'verification' => '🛡️',
];
?>
<div class="stack" style="max-width:640px;margin:0 auto">
  <div>
    <span class="eyebrow">Notifications</span>
    <h2 style="margin-top:4px">Everything HireCraft has told you</h2>
  </div>

  <?php if (!$notifications): ?>
    <div class="card tint" style="text-align:center;padding:30px">
      <h3>No notifications yet</h3>
      <p class="muted small" style="margin-top:6px">New matches, applications, quotations and job updates will show up here.</p>
    </div>
  <?php else: ?>
    <div class="stack s8">
      <?php foreach ($notifications as $n): ?>
        <a class="card notif-row <?= $n['is_read'] ? '' : 'unread' ?>" style="text-decoration:none;color:inherit;display:flex;gap:12px;align-items:flex-start"
           href="<?= e($n['link'] ? url('/notifications/' . (int)$n['id']) : '#') ?>">
          <span style="font-size:20px"><?= $icons[$n['type']] ?? '🔔' ?></span>
          <span style="flex:1">
            <b style="display:block"><?= e($n['title']) ?></b>
            <?php if ($n['body']): ?><span class="muted small"><?= e($n['body']) ?></span><br><?php endif; ?>
            <span class="muted small"><?= e(timeAgo($n['created_at'])) ?></span>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<style>.notif-row.unread{background:var(--green-l)}</style>
