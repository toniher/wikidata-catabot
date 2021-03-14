<?php

use \Mediawiki\Api as MwApi;
use \Mediawiki\DataModel as MwDM;

/** WP Process functions **/

function initializeLogin( $container, $lang, $user, $passwd ) {

  $api = new MwApi\MediawikiApi( "https://".$lang.".wikipedia.org/w/api.php" );
  $api->login( new MwApi\ApiUser( $user, $passwd ) );

  $container[$lang] = $api;
  return $container;
}

function initializeFactory( $container, $api, $lang ) {

  $services = new MwApi\MediawikiFactory( $api[$lang] );

  $container[$lang] = $services;
  return $container;
}

function getProcessWp( $wpapi, $process, $pages ) {

  $outcome = [];

  switch( $process ){
    case "fullpagecount":
      $outcome = processFullPageCount( $wpapi, $pages );
      break;
    default:
      $outcome = [];
  }

  return $outcome;

}

function processFullPageCount( $wpapi, $pages ){

  $data = [];

  if ( $pages ) {

    $params = [];
    $params["titles"] = implode( "|", $pages );
  	$params["prop"] = "revisions";
  	$params["rvslots"] = "*";
  	$params["rvprop"] = "size|timestamp";
  	$params["formatversion"] = 2;

    $postRequest = new Mwapi\SimpleRequest( 'query', $params  );
  	$outcome = $wpapi->postRequest( $postRequest );

    $size = "";
    $timestamp = "";
    if ( $outcome ) {

  		if ( array_key_exists( "query", $outcome ) ) {

  			if ( array_key_exists( "pages", $outcome["query"] ) ) {

  				$pagesQuery = $outcome["query"]["pages"];

  				if ( count( $pagesQuery ) > 0 ) {

            foreach( $pagesQuery as $pageQuery ) {

              $title = null;
              if ( array_key_exists( "title", $pageQuery ) ) {
                $title = $pageQuery["title"];
              }


    					if ( array_key_exists( "revisions", $pageQuery ) ) {

    						$revisions = $pageQuery["revisions"];

    						if ( count( $revisions ) > 0 ) {

    							$revision = $revisions[0];

                  if ( array_key_exists( "size", $revision ) ) {

                    $size = $revision["size"];

                  }
                  if ( array_key_exists( "timestamp", $revision ) ) {

                    $timestamp = $revision["timestamp"];

                  }

                  if ( $title ) {

                    $data[$title] = [ $size, $timestamp ];
                    $size = "";
                    $timestamp = "";

                  }

    						}
    					}
    				}
          }
  			}
  		}
    }
  } else {

    $i = 0;
    while ( $i < 2 ) {
      array_push( $data, "" );
      $i++;
    }

  }

  return $data;
}
