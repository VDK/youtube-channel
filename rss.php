<?php
require_once __DIR__ . '/app/bootstrap.php';

$config = app_config();
$youtube = new YouTubeClient($config['youtube_api_key']);
$channelId = '';

try {
	if (isset($_GET['channel_id'])) {
		$channelId = clean_identifier($_GET['channel_id']);
	} elseif (isset($_GET['user'])) {
		$channelId = $youtube->channelIdForUsername($_GET['user']);
	} elseif (isset($_GET['handle'])) {
		$channelId = $youtube->channelIdForHandle($_GET['handle']);
	} elseif (isset($_GET['custom'])) {
		$channelId = $youtube->channelIdForCustomUrl($_GET['custom']);
	}
} catch (RuntimeException $e) {
	xml_response('<?xml version="1.0" encoding="UTF-8"?><error>' . e($e->getMessage()) . '</error>', 502);
}

if ($channelId === '') {
	xml_response('<?xml version="1.0" encoding="UTF-8"?><error>variable channel_id, user, handle, or custom not set</error>', 400);
}

$limit = null;

if (isset($_GET['limit'])) {
	$limitValue = strtolower(trim((string) $_GET['limit']));
	$limit = $limitValue === 'all' ? null : min(max((int) $limitValue, 1), 1000);
}

try {
	$channel = $youtube->channelById($channelId, 'snippet');
	$videos = $youtube->creativeCommonsVideos($channelId, '', $limit, null);
} catch (RuntimeException $e) {
	$status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 502;
	xml_response('<?xml version="1.0" encoding="UTF-8"?><error>' . e($e->getMessage()) . '</error>', $status);
}

$channelTitle = $channel['snippet']['title'] ?? $channelId;
$channelUrl = 'https://www.youtube.com/channel/' . rawurlencode($channelId);
$feedUrl = app_current_url();

$dom = new DOMDocument('1.0', 'UTF-8');
$dom->formatOutput = true;
$rss = $dom->createElement('rss');
$rss->setAttribute('version', '2.0');
$rss->setAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
$channelNode = $dom->createElement('channel');

append_text_node($dom, $channelNode, 'title', $channelTitle . ' - free-license videos');
append_text_node($dom, $channelNode, 'link', $channelUrl);
append_text_node($dom, $channelNode, 'description', 'Creative Commons licensed YouTube videos from ' . $channelTitle . '.');
append_text_node($dom, $channelNode, 'language', 'en');

$selfLink = $dom->createElement('atom:link');
$selfLink->setAttribute('href', $feedUrl);
$selfLink->setAttribute('rel', 'self');
$selfLink->setAttribute('type', 'application/rss+xml');
$channelNode->appendChild($selfLink);

foreach ($videos['foundVideos'] as $video) {
	$item = $dom->createElement('item');
	$videoUrl = 'https://www.youtube.com/watch?v=' . rawurlencode($video['id']);

	append_text_node($dom, $item, 'title', $video['title'] ?? $video['id']);
	append_text_node($dom, $item, 'link', $videoUrl);
	append_text_node($dom, $item, 'guid', $videoUrl);
	append_text_node($dom, $item, 'description', $video['description'] ?? '');

	if (!empty($video['publishedAt'])) {
		append_text_node($dom, $item, 'pubDate', date(DATE_RSS, strtotime($video['publishedAt'])));
	}

	$channelNode->appendChild($item);
}

$rss->appendChild($channelNode);
$dom->appendChild($rss);

xml_response($dom->saveXML());

function append_text_node(DOMDocument $dom, DOMNode $parent, $name, $value) {
	$node = $dom->createElement($name);
	$node->appendChild($dom->createTextNode((string) $value));
	$parent->appendChild($node);
}

function app_current_url() {
	$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
	$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
	$uri = $_SERVER['REQUEST_URI'] ?? '';

	return $scheme . '://' . $host . $uri;
}
