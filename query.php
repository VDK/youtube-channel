<?php
$key = 'AIzaSyBT5eh-KjxGdx-bSe8q7MsyMnLhu4bcOdc';
$nextPageToken = false;
$foundVideos = array();
if(!isset($_GET['channelId']) ){
	die;
}
$channelId = strip_tags($_GET['channelId']);
$pageToken = strip_tags($_GET['pageToken']);
$json =json_decode(file_get_contents('https://www.googleapis.com/youtube/v3/search?key='.$key.'&videoLicense=creativeCommon&type=video&part=snippet,id&order=date&maxResults=50&channelId='.$channelId.'&pageToken='.$pageToken), true);
if (isset($json['nextPageToken'])){
	$nextPageToken = $json['nextPageToken'];
}

//loop through found videos
foreach ($json['items'] as $item) {
	
	$foundVideos[] = array_merge(array('id'=> $item['id']['videoId']), $item['snippet']);
}

echo json_encode(array("pageToken" => $nextPageToken, "foundVideos" => $foundVideos, 'totalResults' => $json['pageInfo']['totalResults']));
?>