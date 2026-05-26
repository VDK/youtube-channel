<?php
require_once __DIR__ . '/app/bootstrap.php';

$config = app_config();
$youtube = new YouTubeClient($config['youtube_api_key']);
$channelId = isset($_GET['channelId']) ? clean_identifier($_GET['channelId']) : '';
$pageToken = isset($_GET['pageToken']) ? clean_identifier($_GET['pageToken']) : '';

if ($channelId === '') {
	json_response(['error' => 'Missing channel id.'], 400);
}

try {
	json_response($youtube->creativeCommonsVideos($channelId, $pageToken));
} catch (RuntimeException $e) {
	$status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 502;
	json_response(['error' => $e->getMessage()], $status);
}
