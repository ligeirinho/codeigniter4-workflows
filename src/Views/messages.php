<?= $this->extend($layout) ?>
<?= $this->section('main') ?>

	<h2>Information</h2>

	<?php if (! empty($message)): ?>

	<div class="alert alert-success">
		<?= $message ?>
	</div>

	<?php endif; ?>
	<?php if (! empty($error)): ?>

	<div class="alert alert-danger">
		<?= $error ?>
	</div>

	<?php endif; ?>
	<?php if (! empty($errors)): ?>

	<ul class="alert alert-danger">

	<?php foreach ($errors as $error): ?>

		<li><?= $error ?></li>

	<?php endforeach; ?>

	</ul>

	<?php endif; ?>

	<p><?= anchor('', 'Back to home') ?></p>

<?= $this->endSection() ?>
