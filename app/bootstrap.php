<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/YouTubeClient.php';
require_once __DIR__ . '/CommonsClient.php';

function app_config() {
	$localConfigPath = __DIR__ . '/../config.php';
	$localConfig = file_exists($localConfigPath) ? require $localConfigPath : [];

	return [
		'youtube_api_key' => getenv('YOUTUBE_API_KEY') ?: ($localConfig['youtube_api_key'] ?? ''),
	];
}
