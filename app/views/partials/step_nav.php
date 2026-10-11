<?php
?>
<nav class="step-nav">
<?php foreach (Report::STEPS as $key => $meta): ?>
<?php
  $class = 'step-nav__item';
  if ($key === $step) {
      $class .= ' is-current';
  } elseif (!empty($progress[$key])) {
      $class .= ' is-done';
  }
  $label = $meta['no'] . ' ' . $meta['label'];
  $canJump = $key !== $step && !empty($progress[$key]);
?>
<?php if ($canJump): ?>
  <a class="<?= $class ?>" href="/report/<?= (int) $report['id'] ?>/<?= h($key) ?>"><?= h($label) ?></a>
<?php else: ?>
  <span class="<?= $class ?>"><?= h($label) ?></span>
<?php endif; ?>
<?php endforeach; ?>
</nav>
