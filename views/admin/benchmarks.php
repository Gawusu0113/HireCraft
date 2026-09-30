<?php
/** @var array $rows */
$title = 'Price benchmarks';
?>
<div class="stack">
  <div>
    <span class="eyebrow">Admin</span>
    <h2 style="margin-top:4px">Price benchmarks</h2>
    <p class="muted small" style="margin-top:6px">These typical price ranges (25th percentile, median, 75th percentile) drive the budget-compatibility check customers see. Update them as real platform data accumulates.</p>
  </div>

  <form method="post" action="<?= e(url('/admin/benchmarks')) ?>">
    <?= csrf() ?>
    <div class="tw">
      <table>
        <thead><tr><th>Trade</th><th>Complexity</th><th>Observations</th><th>P25 (GH¢)</th><th>Median (GH¢)</th><th>P75 (GH¢)</th><th>Source</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <input type="hidden" name="id[]" value="<?= (int)$r['id'] ?>">
            <td><?= e($r['category_name']) ?></td>
            <td><?= e(ucfirst($r['complexity'])) ?></td>
            <td><input type="number" name="observations_<?= (int)$r['id'] ?>" min="0" value="<?= (int)$r['observations'] ?>" style="width:90px"></td>
            <td><input type="number" name="p25_<?= (int)$r['id'] ?>" min="0" step="1" value="<?= e((string)(float)$r['p25']) ?>" style="width:100px"></td>
            <td><input type="number" name="median_<?= (int)$r['id'] ?>" min="0" step="1" value="<?= e((string)(float)$r['median']) ?>" style="width:100px"></td>
            <td><input type="number" name="p75_<?= (int)$r['id'] ?>" min="0" step="1" value="<?= e((string)(float)$r['p75']) ?>" style="width:100px"></td>
            <td><span class="pill mute"><?= e($r['source']) ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <button class="btn primary" type="submit" style="margin-top:16px">Save benchmarks</button>
  </form>
</div>
