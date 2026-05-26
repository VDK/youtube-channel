<?php

class YouTubeClient {
	private const REQUEST_DELAY_MICROSECONDS = 150000;
	private const MAX_PLAYLIST_PAGES_PER_REQUEST = 2;

	private $apiKey;

	public function __construct($apiKey) {
		$this->apiKey = $apiKey;
	}

	public function channelIdForUsername($username) {
		$json = $this->getJson('https://www.googleapis.com/youtube/v3/channels', [
			'part' => 'id',
			'forUsername' => clean_identifier($username),
		]);

		return $json['items'][0]['id'] ?? '';
	}

	public function channelIdForHandle($handle) {
		$json = $this->getJson('https://www.googleapis.com/youtube/v3/channels', [
			'part' => 'id',
			'forHandle' => clean_identifier($handle),
		]);

		return $json['items'][0]['id'] ?? '';
	}

	public function channelIdForCustomUrl($customUrl) {
		$handleChannelId = $this->channelIdForHandle($customUrl);

		if ($handleChannelId !== '') {
			return $handleChannelId;
		}

		$json = $this->getJson('https://www.googleapis.com/youtube/v3/search', [
			'part' => 'snippet',
			'type' => 'channel',
			'q' => clean_identifier($customUrl),
			'maxResults' => 5,
		]);

		$normalizedCustomUrl = strtolower(ltrim(clean_identifier($customUrl), '@'));

		foreach ($json['items'] ?? [] as $item) {
			$channelId = $item['snippet']['channelId'] ?? '';

			if ($channelId === '') {
				continue;
			}

			$channel = $this->channelById($channelId, 'snippet');
			$custom = strtolower(ltrim($channel['snippet']['customUrl'] ?? '', '@'));

			if ($custom === $normalizedCustomUrl) {
				return $channelId;
			}
		}

		return '';
	}

	public function creativeCommonsVideos($channelId, $pageToken = '', $limit = 50, $maxPlaylistPages = self::MAX_PLAYLIST_PAGES_PER_REQUEST) {
		$channelId = clean_identifier($channelId);
		$pageToken = clean_identifier($pageToken);
		$limit = $limit === null ? null : max(1, (int) $limit);

		$channel = $this->channelById($channelId, 'id,contentDetails');

		if (!$channel) {
			throw new RuntimeException('Channel does not exist or the ID is wrong.', 404);
		}

		$videos = [];
		$uploadsPlaylistId = $channel['contentDetails']['relatedPlaylists']['uploads'] ?? '';
		$nextPageToken = $pageToken;
		$scannedUploads = 0;
		$totalUploads = 0;
		$scannedPages = 0;

		if ($uploadsPlaylistId === '') {
			return [
				'pageToken' => false,
				'foundVideos' => [],
				'totalResults' => 0,
				'totalUploads' => 0,
				'scannedUploads' => 0,
				'hasMoreUploads' => false,
				'totalReportedVideos' => 0,
				'scannedApiVideos' => 0,
				'hasMoreApiPages' => false,
			];
		}

		do {
			$this->pauseBetweenRequests();
			$playlistPage = $this->getJson('https://www.googleapis.com/youtube/v3/playlistItems', [
				'part' => 'snippet,contentDetails',
				'playlistId' => $uploadsPlaylistId,
				'maxResults' => 50,
				'pageToken' => $nextPageToken,
			]);

			$scannedUploads += count($playlistPage['items'] ?? []);
			$totalUploads = $playlistPage['pageInfo']['totalResults'] ?? $totalUploads;
			$videoIds = [];

			foreach ($playlistPage['items'] ?? [] as $item) {
				$videoId = $item['contentDetails']['videoId'] ?? '';

				if ($videoId !== '') {
					$videoIds[] = $videoId;
				}
			}

			if ($videoIds) {
				$this->pauseBetweenRequests();
				$remaining = $limit === null ? null : $limit - count($videos);
				$videos = array_merge($videos, $this->creativeCommonsVideosByIds($videoIds, $remaining));
			}

			$nextPageToken = $playlistPage['nextPageToken'] ?? false;
			$scannedPages += 1;
		} while (
			($limit === null || count($videos) < $limit)
			&& $nextPageToken
			&& ($maxPlaylistPages === null || $scannedPages < $maxPlaylistPages)
		);

		return [
			'pageToken' => $nextPageToken,
			'foundVideos' => $videos,
			'totalResults' => $totalUploads ?: $scannedUploads,
			'totalUploads' => $totalUploads ?: $scannedUploads,
			'scannedUploads' => $scannedUploads,
			'hasMoreUploads' => $nextPageToken !== false,
			'totalReportedVideos' => $totalUploads ?: $scannedUploads,
			'scannedApiVideos' => $scannedUploads,
			'hasMoreApiPages' => $nextPageToken !== false,
		];
	}

	private function creativeCommonsVideosByIds(array $videoIds, $limit) {
		if ($limit !== null && $limit <= 0) {
			return [];
		}

		$json = $this->getJson('https://www.googleapis.com/youtube/v3/videos', [
			'part' => 'snippet,status',
			'id' => implode(',', array_slice($videoIds, 0, 50)),
			'maxResults' => 50,
		]);

		$videos = [];

		foreach ($json['items'] ?? [] as $item) {
			if (($item['status']['license'] ?? '') !== 'creativeCommon') {
				continue;
			}

			$snippet = $item['snippet'] ?? [];
			$videos[] = array_merge(['id' => $item['id']], $this->decodeSnippet($snippet));

			if ($limit !== null && count($videos) >= $limit) {
				break;
			}
		}

		return $videos;
	}

	public function isCreativeCommonsVideo($videoId) {
		$json = $this->getJson('https://www.googleapis.com/youtube/v3/videos', [
			'part' => 'status',
			'id' => clean_identifier($videoId),
		]);

		return ($json['items'][0]['status']['license'] ?? '') === 'creativeCommon';
	}

	public function channelExists($channelId) {
		return $this->channelById($channelId) !== null;
	}

	public function channelById($channelId, $part = 'id') {
		$json = $this->getJson('https://www.googleapis.com/youtube/v3/channels', [
			'part' => $part,
			'id' => clean_identifier($channelId),
		]);

		$channel = $json['items'][0] ?? null;

		if (isset($channel['snippet'])) {
			$channel['snippet'] = $this->decodeSnippet($channel['snippet']);
		}

		return $channel;
	}

	public function feedXml($type, $identifier) {
		$feedUrl = 'https://www.youtube.com/feeds/videos.xml?' . http_build_query([
			$type => clean_identifier($identifier),
		]);

		$dom = new DOMDocument();

		if (!@$dom->load($feedUrl) || !$dom->documentElement) {
			throw new RuntimeException('Unable to load YouTube feed.');
		}

		return $dom;
	}

	private function getJson($url, array $params) {
		$params['key'] = $this->apiKey;
		$requestUrl = $url . '?' . http_build_query($params);
		$response = @file_get_contents($requestUrl);

		if ($response === false) {
			$this->pauseBetweenRequests(500000);
			$response = @file_get_contents($requestUrl);
		}

		$json = json_decode((string) $response, true);

		if (!is_array($json)) {
			throw new RuntimeException('YouTube API request failed.');
		}

		if (isset($json['error'])) {
			$message = $json['error']['message'] ?? 'YouTube API request failed.';
			throw new RuntimeException($message);
		}

		return $json;
	}

	private function pauseBetweenRequests($microseconds = self::REQUEST_DELAY_MICROSECONDS) {
		usleep($microseconds);
	}

	private function decodeSnippet(array $snippet) {
		foreach (['title', 'description', 'channelTitle'] as $field) {
			if (isset($snippet[$field])) {
				$snippet[$field] = html_entity_decode($snippet[$field], ENT_QUOTES | ENT_HTML5, 'UTF-8');
			}
		}

		return $snippet;
	}
}
