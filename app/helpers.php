<?php

function clean_identifier($value) {
	return preg_replace('/[^a-zA-Z0-9_\-@.]/', '', trim((string) $value));
}

function e($value) {
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function json_response($payload, $status = 200) {
	http_response_code($status);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode($payload);
	exit;
}

function xml_response($payload, $status = 200) {
	http_response_code($status);
	header('Content-Type: application/xml; charset=utf-8');
	echo $payload;
	exit;
}

function parse_youtube_channel_url($value) {
	$url = trim((string) $value);
	$normalizedUrl = preg_match('~^https?://~i', $url) ? $url : 'https://' . $url;

	if (!filter_var($normalizedUrl, FILTER_VALIDATE_URL)) {
		return null;
	}

	$parts = parse_url($normalizedUrl);
	$host = strtolower($parts['host'] ?? '');
	$path = trim($parts['path'] ?? '', '/');
	$query = [];
	parse_str($parts['query'] ?? '', $query);

	if (!preg_match('~(^|\.)youtube\.com$~', $host) && $host !== 'youtu.be') {
		return null;
	}

	if (isset($query['channel_id'])) {
		return ['type' => 'channel', 'value' => clean_identifier($query['channel_id'])];
	}

	if ($path === '') {
		return null;
	}

	$segments = array_values(array_filter(explode('/', $path), 'strlen'));
	$first = $segments[0] ?? '';
	$second = $segments[1] ?? '';

	if ($first === 'channel' && $second !== '') {
		return ['type' => 'channel', 'value' => clean_identifier($second)];
	}

	if ($first === 'user' && $second !== '') {
		return ['type' => 'username', 'value' => clean_identifier($second)];
	}

	if ($first === 'c' && $second !== '') {
		return ['type' => 'custom', 'value' => clean_identifier($second)];
	}

	if (str_starts_with($first, '@')) {
		return ['type' => 'handle', 'value' => clean_identifier($first)];
	}

	return ['type' => 'custom', 'value' => clean_identifier($first)];
}
