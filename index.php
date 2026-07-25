<?php
require_once __DIR__ . '/app/bootstrap.php';

$config = app_config();
$youtube = new YouTubeClient($config['youtube_api_key']);
$lang = app_language();
$i18n = translations($lang);
$channelId = '';
$channelTitle = '';
$channelLookup = null;
$error = '';

if (isset($_POST['url'])) {
	$parsedChannel = parse_youtube_channel_url($_POST['url']);

	if ($parsedChannel) {
		if ($parsedChannel['type'] === 'channel') {
			$channelId = $parsedChannel['value'];
		} else {
			$channelLookup = $parsedChannel;
		}
	} else {
		$error = tr('invalid_url');
	}
}

if (isset($_GET['username'])) {
	$channelLookup = ['type' => 'username', 'value' => clean_identifier($_GET['username'])];
} elseif (isset($_GET['handle'])) {
	$channelLookup = ['type' => 'handle', 'value' => clean_identifier($_GET['handle'])];
} elseif (isset($_GET['custom'])) {
	$channelLookup = ['type' => 'custom', 'value' => clean_identifier($_GET['custom'])];
} elseif (isset($_GET['channelId'])) {
	$channelId = clean_identifier($_GET['channelId']);
}

if ($channelLookup) {
	try {
		if ($channelLookup['type'] === 'handle') {
			$channelId = $youtube->channelIdForHandle($channelLookup['value']);
		} elseif ($channelLookup['type'] === 'username') {
			$channelId = $youtube->channelIdForUsername($channelLookup['value']);
		} else {
			$channelId = $youtube->channelIdForCustomUrl($channelLookup['value']);
		}

		if ($channelId === '') {
			$error = tr('channel_not_recognized');
		}
	} catch (RuntimeException $e) {
		$error = $e->getMessage();
	}
}

if ($channelId !== '') {
	try {
		$channel = $youtube->channelById($channelId, 'snippet');
		$channelTitle = $channel['snippet']['title'] ?? '';
	} catch (RuntimeException $e) {
		if ($error === '') {
			$error = $e->getMessage();
		}
	}
}

$escapedError = e($error);
$escapedChannelTitle = e($channelTitle);

function language_url($targetLang) {
	$params = $_GET;
	$params['lang'] = $targetLang;
	$query = http_build_query($params);

	return 'index.php' . ($query !== '' ? '?' . $query : '');
}

function language_label($lang) {
	$labels = [
		'en' => 'English',
		'nl' => 'Nederlands',
		'fr' => 'Français',
		'de' => 'Deutsch',
		'es' => 'Español',
	];

	return $labels[$lang] ?? $labels['en'];
}
?>
<!doctype html>
<html lang="<?php echo e($lang); ?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Free-license YouTube videos</title>
	<link rel="stylesheet" href="style.css?v=<?php echo filemtime(__DIR__ . '/style.css'); ?>">
	<script>
		window.channelId = <?php echo json_encode($channelId); ?>;
		window.channelTitle = <?php echo json_encode($channelTitle); ?>;
		window.i18n = <?php echo json_encode($i18n); ?>;
	</script>
		<script defer src="script.js?v=<?php echo filemtime(__DIR__ . '/script.js'); ?>"></script>
</head>
<body>
	<header class="app-header">
		<div>
			<p class="eyebrow"><?php echo e(tr('app_name')); ?></p>
			<h1><?php echo e(tr('headline')); ?></h1>
		</div>
		<div class="header-tools">
			<details class="language-switch">
				<summary>
					<svg class="language-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" aria-hidden="true">
						<path d="m17.835 19-1.248-3h-4.174l-1.248 3.001H9l4.577-11.005h1.846L20 19zm-4.59-5h2.51L14.5 10.982zM7.618 3H12v2H9.991a8.5 8.5 0 0 1-1.423 4.02q-.24.358-.528.707.634.367 1.379.711l.908.42-.839 1.816-.907-.42a18 18 0 0 1-2.026-1.09c-1.255.979-2.912 1.8-5.076 2.31l-.973.228-.458-1.946.973-.23c1.631-.383 2.885-.954 3.85-1.608C3.29 8.527 2.317 6.884 2.065 5H0V3h5.382l-.724-1.447 1.79-.895L7.617 3ZM4.094 5c.243 1.282.974 2.489 2.29 3.586A6.54 6.54 0 0 0 7.98 5z"/>
					</svg>
					<?php echo e(language_label($lang)); ?>
				</summary>
				<nav aria-label="Language">
					<?php foreach (['en', 'nl', 'fr', 'de', 'es'] as $languageCode): ?>
						<a href="<?php echo e(language_url($languageCode)); ?>" class="<?php echo $lang === $languageCode ? 'active' : ''; ?>" lang="<?php echo e($languageCode); ?>">
							<?php echo e(language_label($languageCode)); ?>
						</a>
					<?php endforeach; ?>
				</nav>
			</details>
		</div>
	</header>

	<main class="app-shell">
		<form class="search-form" method="post" target="_self">
			<label for="url"><?php echo e(tr('channel_url')); ?></label>
			<div class="search-row">
				<input type="text" id="url" placeholder="https://www.youtube.com/@handle" name="url" required>
				<button type="submit" id="submit"><?php echo e(tr('go')); ?></button>
			</div>
			<?php if ($escapedError !== ''): ?>
				<p class="error"><?php echo $escapedError; ?></p>
			<?php endif; ?>
		</form>

		<?php if ($channelId !== ''): ?>
			<section class="results-toolbar" aria-live="polite">
				<div>
					<p class="eyebrow"><?php echo $escapedChannelTitle !== '' ? e(tr('results_for', $channelTitle)) : e(tr('results')); ?></p>
					<h2 id="totalResults"><?php echo e(tr('loading_videos')); ?></h2>
				</div>
				<a class="rss-link" href="rss.php?channel_id=<?php echo urlencode($channelId); ?>&amp;limit=50">
					<svg class="rss-icon" viewBox="0 0 24 24" aria-hidden="true">
						<path d="M5 5.5v3a10.5 10.5 0 0 1 10.5 10.5h3A13.5 13.5 0 0 0 5 5.5Z"></path>
						<path d="M5 11v3a5 5 0 0 1 5 5h3a8 8 0 0 0-8-8Z"></path>
						<circle cx="6.5" cy="17.5" r="1.8"></circle>
					</svg>
					<span class="rss-label-full"><?php echo e(tr('rss_feed')); ?></span>
					<span class="rss-label-short">RSS</span>
				</a>
			</section>
			<div class="progress" aria-hidden="true">
				<div id="progressbar"></div>
			</div>
			<p id="resultsNote" class="results-note"></p>
		<?php endif; ?>

		<ul id="videos" class="video-grid"></ul>
		<div class="results-footer" id="resultsFooter" hidden>
			<div class="footer-progress" aria-hidden="true">
				<div id="footerProgressbar"></div>
			</div>
			<p id="footerResultsNote" class="footer-note"></p>
			<button id="loadMore" class="load-more" type="button" hidden><?php echo e(tr('load_more')); ?></button>
		</div>
	</main>
	<footer class="app-footer">
		<a href="https://github.com/VDK/youtube-channel/" rel="noopener noreferrer" target="_blank"><?php echo e(tr('source_code')); ?></a>
		<span aria-hidden="true">|</span>
		<span>GNU GPLv3</span>
		<span aria-hidden="true">|</span>
		<a href="https://veradekok.nl" rel="noopener noreferrer" target="_blank">Vera de Kok</a>
	</footer>
</body>
</html>
