<?php

$main = [];
$ignored = [];

$base = explode('staticbuilder', thisUrl())[0] . 'staticbuilder';

foreach ($summary as $item) {
	if ($item['status'] == 'ignore') {
		$ignored[] = $item;
	}
	else $main[] = $item;
}

$mainCount = count($main);
$ignoredCount = count($ignored);


function statusText($status) {
	if ($status == 'uptodate') return 'Up to date';
	elseif ($status == 'outdated') return 'Outdated version';
	elseif ($status == 'missing') return 'Not generated';
	elseif ($status == 'generated') return 'Done';
	elseif ($status == 'done') return 'Done';
	return $status;
}

function showFiles($files) {
	$text = '';
	if ($files === 1) {
		$text = "<br><small>+&nbsp;1&nbsp;file</small>";
	}
	elseif (is_int($files) and $files > 1) {
		$text = "<br><small>+&nbsp;$files&nbsp;files</small>";
	}
	elseif (is_array($files)) {
		foreach ($files as $file) {
			$text .= "<br><code>$file</code>\n";
		}
	}
	return $text;
}

function makeRow($info, $baseUrl) {
	extract($info);
	if (!isset($files)) $files = '';
	$cols = [];
	$sourceKey = 'source type-' . $type;
	if ($type == 'page' and isset($title)) {
		$sourceHtml = "<a href=\"$baseUrl/$uri\">"
			. "$title<br><code>$source</code></a>";
	}
	else {
		$sourceHtml = "[$type]<br><code>$source</code>";
	}
	$cols[$sourceKey] = $sourceHtml;
	$cols['dest'] = "<code>$dest</code>" . showFiles($files);
	$cols['status'] = statusText($status);
	if (is_int($size)) $cols['status'] .= '<br>'.f::niceSize($size);
	$html = '';
	foreach ($cols as $key=>$content) {
		$html .= "<td class=\"$key\">$content</td>\n";
	}
	return "<tr class=\"$type $status\">\n$html</tr>\n";
}

function makeIgnoredRow($info) {
	extract($info);
	$cols = [];
	$cols['source type-' . $type] = "[$type] <code>$source</code>";
	$cols['reason'] = $info['reason'];
	$html = '';
	foreach ($cols as $key=>$content) {
		$html .= "<td class=\"$key\">$content</td>\n";
	}
	return "<tr class=\"$type $status\">\n$html</tr>\n";
}

?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Kirby StaticBuilder</title>
	<style><?php echo $styles; ?></style>
</head>
<body>

<header>
	<div class="header-col header-col--main">
		<h1>Kirby StaticBuilder</h1>
		<p class="<?php echo $error ? 'error' : 'info' ?>">
		<?php
			if (isset($error) and $error != '') echo $error;
			else {
				echo ($confirm ? 'Built' : 'Found') . ' ' . count($summary) . ' elements';
				if ($ignoredCount > 0) {
					echo " (<a href=\"#results\">$mainCount included</a>,";
					echo " <a href=\"#skipped\">$ignoredCount skipped</a>)";
				}
			}
		?>
		</p>
	</div>
	<div class="header-col header-col--side">
		<?php if ($mode == 'page'): ?>
			<a class="header-btn" href="<?php echo $base ?>">show all pages</a>
		<?php endif ?>
		<form method="post" action="">
			<input type="hidden" name="confirm" value="1">
			<button class="header-btn" type="submit">
				build <?= $mode == 'page' ? 'this page' : 'everything&thinsp;!' ?>
			</button>
		</form>
	</div>
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
<?php if ($mainCount > 0): ?>
	<?php if (isset($errorDetails)): ?>
	<p>
		The following pages and files were built without errors.<br>
		<strong>Important:</strong> the script was stopped, so the next pages in the queue were NOT built.
	</p>
	<?php endif ?>
	<table id="results" class="pages">
		<thead>
		<tr>
			<th>Source</th>
			<th><?php echo $confirm ? 'Output' : 'Output target'; ?></th>
			<th class="short">Status</th>
		</tr>
		</thead>
		<tbody>
		<?php foreach($main as $item) {
			echo makeRow($item, $base);
		} ?>
		</tbody>
	</table>
<?php endif ?>
<?php if ($ignoredCount > 0): ?>
	<h2 id="skipped">These pages or files were skipped</h2>
	<table class="pages">
		<thead>
		<tr>
			<th>Source</th>
			<th>Skipped because</th>
		</tr>
		</thead>
		<tbody>
		<?php foreach($ignored as $item) {
			echo makeIgnoredRow($item);
		} ?>
		</tbody>
	</table>
<?php endif ?>
</main>

</body>
</html>
