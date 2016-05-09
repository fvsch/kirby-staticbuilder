<?php
function prettySize($bytes=null) {
	if ($bytes == null) return '';
	if ($bytes < 1000) return "($bytes&nbsp;B)";
	$rounded = round($bytes / 1000, 1);
	return "($rounded&nbsp;KB)";
}
$showIgnored = c::get('plugin.staticbuilder.showignored', false);
?>

<?php if ($error): ?>
	<p class="error"><?php echo $message; ?></p>
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
			<th>Page</th>
			<th>Files</th>
		</tr>
	</thead>
	<tbody>
	<?php foreach($pageinfo as $info): ?>
		<?php if ($info['ignored']): ?>
			<?php if ($showIgnored): ?>
			<tr class="pageinfo skipped">
				<td>
					<?php echo $info['title']; ?><br>
					<code><?php echo $info['uri']; ?></code>
				</td>
				<td>
					-
				</td>
			</tr>
			<?php endif ?>
		<?php else: ?>
			<tr class="pageinfo">
				<td>
					<?php echo $info['title']; ?><br>
					<code><?php echo $info['uri']; ?></code>
				</td>
				<td>
					<p><?php echo $info['dest'] . '&nbsp;' . prettySize($info['bytes']); ?></p>
					<?php foreach ($info['files'] as $file): ?>
					<p><?php echo $file['dest'] . '&nbsp;' . prettySize($file['bytes']); ?></p>
					<?php endforeach ?>
				</td>
			</tr>
		<?php endif ?>
	<?php endforeach ?>
	</tbody>
</table>

<?php endif ?>
