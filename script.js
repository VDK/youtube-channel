var completed  = 0;

$(document).ready(function() {
    if (channelId != ''){
    	loadPage(channelId);
    }
});

function loadPage(channelId, pageToken = ''){
	$.ajax({
	  dataType: "json",
	  url: 'query.php',
	  data: {channelId, pageToken},
	  error: function(result){
	  	console.log(result);
	  },
	  success: function(result){
	  	window.result = result;
	  	completed++;
	  	$( "#progressbar" ).progressbar({ value: Math.round( completed/(result.totalResults/50) * 100) });
	  	$( "#totalResults" ).text(result.totalResults + ' video\'s gevonden');
	  	var video;
	  	var date;
	  	if (result.pageToken != false){
	  		loadPage(channelId, result.pageToken);
	  	}
	  	for (var i = result.foundVideos.length - 1; i >= 0; i--) {
	  		video = result.foundVideos[i];
	  		date  = new Date(video.publishedAt);
	  		$('#videos').append('<li id='+video.id+'><div class="ytd-grid-video ">'
				+'<a id="thumbnail" tabindex="-1" href="https://youtube.com/watch?v='+video.id+'">'
				+'<img id="img" class="style-scope yt-img-shadow" width="'+video.thumbnails.medium.width+'" src="'+video.thumbnails.medium.url+'">'
				+'<p>'+("0" + date.getDate()).slice(-2) +'-'+("0" + (date.getMonth() + 1)).slice(-2) +'-'+date.getFullYear() + '</p>'
				+'</div>'
				+'</a>'
				+'<h3 class="style-scope ytd-grid-video-renderer">'
				+'<a id="video-title" class="yt-simple-endpoint" href="https://youtube.com//watch?v='+video.id+'" title="'+video.title+'">'+video.title+'</a></h3>'
				+'<input type="text" size="35" value="https://youtube.com/watch?v='+video.id+'" onClick=\'this.setSelectionRange(0, this.value.length)\'/>'
				+'</div>'
				+'</li>');
	  		checkWikimediaCommons(video.id);
	  	}
	  }
	});


}

function checkWikimediaCommons(videoId){
	$.ajax({
	  dataType: "json",
	  url: 'published_check.php',
	  data: {videoId},
	  error: function(result){
	  	console.log(result);
	  },
	  success: function(result){
	  	if (result == true){
	  		$('#'+videoId).addClass('publishedOnCommons');
	  	}
	  }
	});
}