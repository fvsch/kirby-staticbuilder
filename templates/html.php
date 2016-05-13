<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Kirby StaticBuilder</title>
	<style>
		body {
			padding: 40px 20px;
			max-width: 1160px;
			margin: 0 auto;
			font-family: sans-serif;
		}
		.pages {
			width: 100%;
			table-layout: fixed;
			border: solid 1px gray;
			border-collapse: collapse;
		}
		.pages th,
		.pages td {
			border: solid 1px #ccc;
			padding: 5px 10px;
		}
	</style>
</head>
<body>

<?php

function prettySize($bytes=null) {
	if ($bytes == null) return '';
	if ($bytes < 1000) return "($bytes&nbsp;B)";
	$rounded = round($bytes / 1000, 1);
	return "($rounded&nbsp;KB)";
}

?>

<?php if ($error): ?>
	<p class="error"><?php echo $error; ?></p>
<?php else: ?>

<?php if ($confirm == false): ?>
	<form method="post">
		<input type="hidden" name="confirm" value="1">
		<button type="submit">Yep, do it.</button>
	</form>
<?php endif ?>

<table class="pages">
	<thead>
		<tr>
			<th>Source</th>
			<th>Files</th>
			<th width="100">Size</th>
			<th width="100">Status</th>
		</tr>
	</thead>
	<tbody>
	<?php foreach($summary as $key=>$info): ?>
		<?php if ($info['done'] == false): ?>
			<tr class="pageinfo skipped <?php echo $info['type']; ?>">
				<td>
					<?php echo $info['name']; ?><br>
					<code><?php echo $key; ?></code>
				</td>
				<td>-</td>
				<td>-</td>
				<td>Skipped</td>
			</tr>
		<?php else: ?>
			<tr class="pageinfo <?php echo $info['type']; ?>">
				<td>
					<strong><?php echo $info['name']; ?></strong><br>
					<code><?php echo $key; ?></code>
				</td>
				<td>
					<code><?php echo $info['dest']; ?></code>
					<?php if (array_key_exists('files', $info)) {
						foreach ($info['files'] as $file) {
							echo "<br><code>$file</code>\n";
						}
					} ?>
				</td>
				<td><?php echo prettySize($info['size']); ?></td>
				<td><?php if ($info['type'] == 'page') echo 'Created'; else echo 'Copied'; ?></td>
			</tr>
		<?php endif ?>
	<?php endforeach ?>
	</tbody>
</table>

<?php endif ?>

</body>
</html>
