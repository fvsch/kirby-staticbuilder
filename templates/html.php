<?php

$base = kirby()->urls()->index() . '/staticbuilder';

?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Kirby StaticBuilder</title>
	<link rel="stylesheet" href="<?php echo $base ?>/report.css">
</head>
<body>

<header>
	<div class="header-col header-col--main">
		<h1>Kirby StaticBuilder</h1>
		<p class="<?php echo $error ? 'error' : 'info' ?>">
		<?php
			if (isset($error) and $error != '') echo $error;
			else echo ($confirm ? 'Built' : 'Found') . ' ' . count($summary) . ' elements';
		?>
		</p>
	</div>
	<?php if ($mode == 'page'): ?>
		<div class="header-col header-col--side">
			<a class="header-btn" href="<?php echo $base ?>/site">List all pages</a>
		</div>
	<?php endif ?>
	<?php if ($mode == 'site' and !$confirm): ?>
		<form class="header-col header-col--side"
			  method="post" action="<?php echo $base ?>/site">
			<input type="hidden" name="confirm" value="1">
			<button class="header-btn" type="submit">Rebuild everything</button>
		</form>
	<?php endif ?>
</header>

<main>
<?php if (isset($errorDetails)): ?>
	<div class="error-msg">
		<?php if (isset($lastPage)): ?>
			<h2>
				Failed to build page:
				<?php echo $lastPage ? "<code>$lastPage</code>" : 'unknown'; ?>
			</h2>
		<?php endif ?>
		<blockquote>
			<?php echo $errorDetails ?>
		</blockquote>
	</div>
<?php endif ?>
<?php if (count($summary) > 0): ?>
	<?php if (isset($errorDetails)): ?>
	<p>
		The following pages and files were built without errors.<br>
		<strong>Important:</strong> the script was stopped, so the next pages in the queue were NOT built.
	</p>
	<?php endif ?>
	<table class="pages">
		<thead>
		<tr>
			<th class="shorter">Type</th>
			<th>Source</th>
			<th><?php echo $confirm ? 'Output' : 'Target'; ?></th>
			<th class="short">Status</th>
			<th class="short">Action</th>
		</tr>
		</thead>
		<tbody>
		<?php foreach($summary as $key=>$info): ?>
			<tr class="pageinfo <?php echo $info['type'] . ' ' . $info['status']; ?>">
				<td>
					<?php echo $info['type']; ?>
				</td>
				<td>
					<?php echo $info['name']; ?><br>
					<code><?php echo $key; ?></code><br>
				</td>
				<td>
					<code><?php echo $info['dest'] ?></code>
					<?php if (array_key_exists('files', $info)):
						foreach ($info['files'] as $file) {
							echo "<br><code>$file</code>\n"; }
					endif; ?>
				</td>
				<td><?php
					$status = $info['status'];
					$size   = $info['size'];
					echo $status;
					if ($status == 'generated' and is_int($size)) {
						$hrsize = f::niceSize($size);
						echo "<br>\n<code>$hrsize</code>";
					}
				?></td>
				<td><?php if ($info['type'] == 'page'): ?>
					<form method="post" action="<?php echo $base . '/page/' . $info['uri'] ?>">
						<input type="hidden" name="confirm" value="1">
						<button type="submit">Rebuild</button>
					</form>
				<? endif ?></td>
			</tr>
		<?php endforeach ?>
		</tbody>
	</table>
<?php endif ?>
<?php if (count($skipped) > 0): ?>
	<div class="skipped">
		<h2>These pages were skipped</h2>
		<p class="skipped-info">
			By default, folders with no text file are skipped. You can specify your own
			rules for which pages should be included in the static build by defining a
			callback for the <code>'plugin.staticbuilder.filter'</code> option.
		</p>
		<ul>
		<?php foreach ($skipped as $uri): ?>
			<li><code><?php echo $uri ?></code></li>
		<?php endforeach ?>
		</ul>
		</div>
<? endif ?>
</main>

</body>
</html>
