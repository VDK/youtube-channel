<?php
if (isset($_GET['user'])){
	$user = strip_tags($_GET['user']);
	$var = 'user';
}
elseif(isset($_GET['channel_id'])){
	$user = strip_tags($_GET['channel_id']);
	$var = 'channel_id';
}
else{
	echo "variable user or channel_id not set"; die;
}

header('Content-type: application/xml');
$dom=new DOMDocument();
$dom->load('https://www.youtube.com/feeds/videos.xml?'.$var.'='.$user);



$root=$dom->documentElement; // This can differ (I am not sure, it can be only documentElement or documentElement->firstChild or only firstChild)

$nodesToDelete=array();
$markers=$root->getElementsByTagName('entry');

// Loop trough childNodes
foreach ($markers as $marker) {
	$ytid=$marker->getElementsByTagNameNS('*','videoId')->item(0)->textContent;
	$json =json_decode(file_get_contents('https://www.googleapis.com/youtube/v3/videos?key=AIzaSyBT5eh-KjxGdx-bSe8q7MsyMnLhu4bcOdc&part=status&id='.$ytid), true);

	if ($json['items'][0]['status']['license'] != "creativeCommon"){
		$nodesToDelete[]=$marker;
	}
}

// You delete the nodes
foreach ($nodesToDelete as $node) $node->parentNode->removeChild($node);

echo $dom->saveXML();
?>