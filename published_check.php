<?php

require_once( __DIR__ . '/vendor/autoload.php' );
use Mediawiki\Api\SimpleRequest;
$api = new \Mediawiki\Api\MediawikiApi( 'https://commons.wikimedia.org/w/api.php' );
if(!isset($_GET['videoId'])){
  die;
}
$publishedOnCommons = false;
   try{
         $response = $api->postRequest( new SimpleRequest( 'query',  
                    array('list'    => 'search', 
                          'srsearch' => 'intitle:webm insource:'.$_GET['videoId'],
                          'srnamespace'=> '6' )
                           ) 
                    );

        if($response['query']['searchinfo']['totalhits'] > 0){
          $publishedOnCommons = true;
        }

    }
    catch ( UsageException $e ) {
        echo "The api returned an error!";
    }
echo json_encode( $publishedOnCommons  );
?>