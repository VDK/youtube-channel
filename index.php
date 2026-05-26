<?php
$key = 'AIzaSyBT5eh-KjxGdx-bSe8q7MsyMnLhu4bcOdc';
$channelId 	= false;
$username 	= false;
$error = '';

if (isset($_POST['url'])){
	if(filter_var($_POST['url'], FILTER_VALIDATE_URL) 
	&& preg_match('/(https?:\/\/|)(www\.|)?youtube\.com\/(channel|user)\/([a-zA-Z0-9\-_]+)/', $_POST['url'], $matches)){
		if ($matches[3] == 'channel'){
			$channelId = $matches[4];
		}
		else{
			$username = $matches[4];
		}
	}
	else{
		$error = 'invalid input';
	}

}
if (isset($_GET['username'])){
	$username = strip_tags($_GET['username']);
}
elseif (isset($_GET['channelId'])){
	$channelId = strip_tags($_GET['channelId']);
}

if( $username ){
	$json =json_decode(file_get_contents('https://www.googleapis.com/youtube/v3/channels?key='.$key.'&part=id&forUsername='.$username), true);
	if (isset($json['items'][0]['id'])){
		$channelId = $json['items'][0]['id'];
	}
	else{
		$error = 'Username not recognized';
	}
}


?>

<html>
<head>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/jquery-ui.min.js"></script>
<link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/themes/smoothness/jquery-ui.css">
<link rel="stylesheet" href="style.css">
<script type="text/javascript" src="script.js"></script>
<title>All Free license videos</title>
<script type="text/javascript">
var channelId = '<?php echo $channelId;?>';
</script>
</head>
<body>
	<form class="form-wrapper"  method="POST"  target='_self'	>
	 <div style='color:red; clear:both;'><?php echo $error; ?></div>
   <div> <input type="text" id="url" placeholder="Channel URL" name='url' required>
    <input type="submit" class='button' value="go" id="submit"></div> 	
</form>
<div>
<?php if ($channelId){
	echo "<h1 id='totalResults'></h1>";
	echo "<div style='float:right'><a href='rss.php?channel_id=".$channelId."' style='font: bold 0.75em sans-serif; text-decoration: none;'>
<span style='color: #fff; background: #f60; padding: 0.2em 0.35em; margin-right: 0; border: solid 1px #f60; float: left;'>RSS</span>
<span style='color: #000; background: #fff; padding: 0.2em 0.35em; margin-left: 0; border: solid 1px #f60; float: left;'>free license videos</a></div>";
	echo "<div id='progressbar'></div>";
}
?>
<ul id='videos'>
	
</ul>


</div>
</body>

</html>