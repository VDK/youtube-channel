<?php

use Addwiki\Mediawiki\Api\Client\Action\Exception\UsageException;
use Addwiki\Mediawiki\Api\Client\Action\Request\ActionRequest;
use Addwiki\Mediawiki\Api\Client\MediaWiki;
use GuzzleHttp\Exception\GuzzleException;

class CommonsClient {
	private $api;

	public function __construct() {
		$this->api = MediaWiki::newFromEndpoint('https://commons.wikimedia.org/w/api.php')->action();
	}

	public function findVideoMatches($videoId) {
		$videoId = clean_identifier($videoId);
		$queries = [
			'intitle:webm insource:"' . $videoId . '"',
			'intitle:ogv insource:"' . $videoId . '"',
			'intitle:mp4 insource:"' . $videoId . '"',
		];
		$totalHits = 0;
		$results = [];

		try {
			foreach ($queries as $query) {
				$response = $this->api->request(ActionRequest::simplePost('query', [
					'list' => 'search',
					'srsearch' => $query,
					'srnamespace' => '6',
					'srlimit' => '5',
				]));

				$totalHits += (int) ($response['query']['searchinfo']['totalhits'] ?? 0);

				foreach ($response['query']['search'] ?? [] as $result) {
					$pageId = $result['pageid'] ?? null;
					$title = $result['title'] ?? '';

					if ($pageId !== null) {
						$results[$pageId] = [
							'title' => $title,
							'pageid' => $pageId,
						];
					}
				}
			}
		} catch (UsageException | GuzzleException $e) {
			throw new RuntimeException('Commons API request failed.', 0, $e);
		}

		return [
			'matched' => $totalHits > 0,
			'confidence' => $totalHits > 0 ? 'possible' : 'none',
			'totalHits' => $totalHits,
			'results' => array_values($results),
		];
	}

	public function hasVideo($videoId) {
		return $this->findVideoMatches($videoId)['matched'];
	}
}
