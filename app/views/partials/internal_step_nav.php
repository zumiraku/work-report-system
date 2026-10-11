<?php
$id = (int) $report['id'];
?>
<nav class="step-nav step-nav--internal">
<?php foreach (InternalReport::STEPS as $key => $meta): ?>
<?php
  $class = 'step-nav__item';
  if ($key === $step) {
      $class .= ' is-current';
  } elseif (!empty($progress[$key])) {
      $class .= ' is-done';
  }
  $label   = $meta['no'] . ' ' . $meta['label'];
  $canJump = $key !== $step && !empty($progress[$key]);
?>
<?php if ($canJump): ?>
  <a class="<?= $class ?>" href="/report/<?= $id ?>/internal/<?= h($key) ?>"><?= h($label) ?></a>
<?php else: ?>
  <span class="<?= $class ?>"><?= h($label) ?></span>
<?php endif; ?>
<?php endforeach; ?>
  <span class="step-nav__item<?= $step === 'sales' ? ' is-current' : (!empty($progress['sales']) ? ' is-done' : '') ?>">6.備考（社内への報告事項等）</span>
</nav>
