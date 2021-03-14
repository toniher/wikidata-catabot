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

function getProcessWp( $wpapi, $process, $pagename ) {

  $outcome = [];

  switch( $process ){
    case "fullpagecount":
      $outcome = processFullPageCount( $wpapi, $pagename );
      break;
    default:
      $outcome = [];
  }

  return $outcome;

}

function processFullPageCount( $wpapi, $pagename ){

  $data = [];

  if ( $pagename ) {

    $params = [];
    $params["titles"] = $pagename;
  	$params["prop"] = "revisions";
  	$params["rvslots"] = "*";
  	$params["rvprop"] = "size|timestamp";
  	$params["formatversion"] = 2;

    $postRequest = new Mwapi\SimpleRequest( 'query', $params  );
  	$outcome = $wpapi->postRequest( $postRequest );

    $size = "";
    $timestamp = "";
    if ( $outcome ) {

      # var_dump( $outcome );

  		if ( array_key_exists( "query", $outcome ) ) {

  			if ( array_key_exists( "pages", $outcome["query"] ) ) {

  				$pagesQuery = $outcome["query"]["pages"];

  				if ( count( $pagesQuery ) > 0 ) {
  					$pageQuery = $pagesQuery[0];

  					if ( array_key_exists( "revisions", $pageQuery ) ) {

  						$revisions = $pageQuery["revisions"];

  						if ( count( $revisions ) > 0 ) {

  							$revision = $revisions[0];

  							if ( array_key_exists( "slots", $revision ) ) {

  								if ( array_key_exists( "main", $revision["slots"] ) ) {

  									if ( array_key_exists( "size", $revision["slots"]["main"] ) ) {

  										$size = $revision["slots"]["main"]["size"];

  									}
                    if ( array_key_exists( "timestamp", $revision["slots"]["main"] ) ) {

  										$timestamp = $revision["slots"]["main"]["timestamp"];

  									}

  								}

  							} else {

                  if ( array_key_exists( "size", $revision ) ) {

                    $size = $revision["size"];

                  }
                  if ( array_key_exists( "timestamp", $revision ) ) {

                    $timestamp = $revision["timestamp"];

                  }

                }
  						}
  					}
  				}

  			}
  		}
    }
    return( [$size, $timestamp] );

  } else {
    $i = 0;
    while ( $i < 2 ) {
      array_push( $data, "" );
      $i++;
    }
  }

  return $data;
}
