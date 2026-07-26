<?php

class YouTubeClient {
	private const REQUEST_DELAY_MICROSECONDS = 150000;
	private const MAX_PLAYLIST_PAGES_PER_REQUEST = 10;

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

		$channel = $this->channelById($channelId, 'id,contentDetails,statistics');

		if (!$channel) {
			throw new RuntimeException('Channel does not exist or the ID is wrong.', 404);
		}

		$totalVideos = (int) ($channel['statistics']['videoCount'] ?? 0);
		$knownById = [];
		$totalCc = null;

		// On the first call (no page token), use Search API to quickly find CC videos.
		// This gives us up to 50 CC results + the total CC count in one call.
		// If total CC <= 50 we're done immediately; otherwise we seed the result list
		// and skip license checks for known CC videos during the playlist scan.
		if ($pageToken === '') {
			$this->pauseBetweenRequests();
			$searchResult = $this->getJson('https://www.googleapis.com/youtube/v3/search', [
				'part' => 'snippet',
				'channelId' => $channelId,
				'videoLicense' => 'creativeCommon',
				'type' => 'video',
				'maxResults' => 50,
			]);

			$totalCc = (int) ($searchResult['pageInfo']['totalResults'] ?? 0);

			if ($totalCc === 0) {
				return [
					'pageToken' => false,
					'foundVideos' => [],
					'totalResults' => $totalVideos,
					'totalUploads' => $totalVideos,
					'scannedUploads' => $totalVideos,
					'hasMoreUploads' => false,
					'totalReportedVideos' => $totalVideos,
					'scannedApiVideos' => $totalVideos,
					'hasMoreApiPages' => false,
					'totalCc' => 0,
				];
			}

			foreach ($searchResult['items'] as $item) {
				$id = $item['id']['videoId'] ?? '';
				if ($id === '') {
					continue;
				}
				$knownById[$id] = array_merge(
					['id' => $id],
					$this->decodeSnippet($item['snippet'] ?? [])
				);
			}

			if ($totalCc <= count($knownById)) {
				// Search API found all CC videos — no playlist scan needed.
				return [
					'pageToken' => false,
					'foundVideos' => array_values($knownById),
					'totalResults' => $totalVideos,
					'totalUploads' => $totalVideos,
					'scannedUploads' => $totalVideos,
					'hasMoreUploads' => false,
					'totalReportedVideos' => $totalVideos,
					'scannedApiVideos' => $totalVideos,
					'hasMoreApiPages' => false,
					'totalCc' => $totalCc,
				];
			}

			// More CC videos exist beyond this batch — seed the result list and
			// bump the limit so Search API results don't cap playlist scanning.
			$limit = $limit === null ? null : $limit + count($knownById);
		}

		$videos = array_values($knownById);
		$uploadsPlaylistId = $channel['contentDetails']['relatedPlaylists']['uploads'] ?? '';
		$nextPageToken = $pageToken;
		$scannedUploads = count($knownById);
		$totalUploads = 0;
		$scannedPages = 0;

		if ($uploadsPlaylistId === '') {
			return [
				'pageToken' => false,
				'foundVideos' => $videos,
				'totalResults' => count($videos),
				'totalUploads' => count($videos),
				'scannedUploads' => count($videos),
				'hasMoreUploads' => false,
				'totalReportedVideos' => count($videos),
				'scannedApiVideos' => count($videos),
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
			$unknownIds = [];

			foreach ($playlistPage['items'] ?? [] as $item) {
				$videoId = $item['contentDetails']['videoId'] ?? '';

				if ($videoId === '') {
					continue;
				}

				if (isset($knownById[$videoId])) {
					// Already known as CC from the Search API — add directly.
					$videos[] = $knownById[$videoId];
					unset($knownById[$videoId]);
				} else {
					$unknownIds[] = $videoId;
				}
			}

			if ($unknownIds) {
				$this->pauseBetweenRequests();
				$remaining = $limit === null ? null : $limit - count($videos);
				$videos = array_merge($videos, $this->creativeCommonsVideosByIds($unknownIds, $remaining));
			}

			$nextPageToken = $playlistPage['nextPageToken'] ?? false;
			$scannedPages += 1;
		} while (
			($limit === null || count($videos) < $limit)
			&& $nextPageToken
			&& ($maxPlaylistPages === null || $scannedPages < $maxPlaylistPages)
		);

		// If we know the total CC count and have found everything, signal done
		// even if there are still playlist pages left to scan.
		$allFound = $totalCc !== null && count($videos) >= $totalCc;

		return [
			'pageToken' => $allFound ? false : $nextPageToken,
			'foundVideos' => $videos,
			'totalResults' => $totalUploads ?: $scannedUploads,
			'totalUploads' => $totalUploads ?: $scannedUploads,
			'scannedUploads' => $scannedUploads,
			'hasMoreUploads' => !$allFound && $nextPageToken !== false,
			'totalReportedVideos' => $totalUploads ?: $scannedUploads,
			'scannedApiVideos' => $scannedUploads,
			'hasMoreApiPages' => !$allFound && $nextPageToken !== false,
			'totalCc' => $totalCc,
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
