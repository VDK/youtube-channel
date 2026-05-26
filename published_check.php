<?php
require_once __DIR__ . '/app/bootstrap.php';

$videoId = isset($_GET['videoId']) ? clean_identifier($_GET['videoId']) : '';

if ($videoId === '') {
	json_response(['matched' => false, 'confidence' => 'none', 'totalHits' => 0, 'results' => []], 400);
}

$commons = new CommonsClient();

try {
	json_response($commons->findVideoMatches($videoId));
} catch (RuntimeException $e) {
	json_response(['matched' => false, 'confidence' => 'unknown', 'totalHits' => 0, 'results' => []], 502);
}
