<?php

require_once( __DIR__ . '/vendor/autoload.php' );

use \Mediawiki\Api as MwApi;
use \Mediawiki\DataModel as MwDM;

use League\Csv\Reader;

// Detect commandline args
$conffile = 'config.json';
$csvfile = 'list.csv';
$taskname = null; // If no task given, exit
$finalpage = null;

if ( count( $argv ) > 1 ) {
	$conffile = $argv[1];
}

if ( count( $argv ) > 2 ) {
	$csvfile = $argv[2];
}

if ( count( $argv ) > 3 ) {
	$taskname = $argv[3];
}

if ( count( $argv ) > 4 ) {
	$finalpage = $argv[4];
}

// Detect if files
if ( ! file_exists( $conffile ) || ! file_exists( $csvfile ) || ! $finalpage ) {
	die( "Files needed" );
}

$confjson = json_decode( file_get_contents( $conffile ), 1 );
$wikiconfig = null;

if ( array_key_exists( "wikipedia", $confjson ) ) {
	$wikiconfig = $confjson["wikipedia"];
}

if ( array_key_exists( "tasks", $confjson ) ) {
	$tasksConf = $confjson["tasks"];
}

$tasks = array_keys( $tasksConf );
$props = null;

if ( count( $tasks ) < 1 ) {
	// No task, exit
	exit;
}

if ( ! $taskname ) {
	echo "No task specified!";
	exit;
} else {
	if ( in_array( $taskname, $tasks ) ) {
		$props = $tasksConf[ $taskname ];
	} else {
		// Some error here. Stop it
		exit;
	}
}

$wpapi = new MwApi\MediawikiApi( $wikiconfig['url'] );

$wpapi->login( new MwApi\ApiUser( $wikiconfig['user'], $wikiconfig['password'] ) );

$reader = Reader::createFromPath( $csvfile );

$reader->setOffset(0);
$reader->setDelimiter($props["delimiter"]);
$reader->setEnclosure($props["enclosure"]);

$results = $reader->fetch();
$string = "";
$header = "";

$results = $reader->fetch();
if ( count( $results ) > 0 ) {
	$string = "{| class=\"wikitable sortable\"\n";
}

if ( array_key_exists( "header", $props ) ) {
	$header = $props["header"];
	$si = "|-\n! ";
	$ss = " !! ";

	$string.= $si.implode( $ss, $header )."\n";
}


foreach ( $results as $row ) {

	$si = "|-\n| ";
	$ss = " || ";


	if ( count( $row ) > 0 ) {

		#if ( array_key_exists( "types", $props ) && $rowi > 0 ) {
		#	$row = processRow( $row, $props["types"] );
		#}

		$string.= $si.implode( $ss, $row )."\n";
	}

}

$string.="|}\n";

if ( $string ) {

	echo $string, "\n";
	// putPage( $wpapi, $string, $finalpage );

}

$wpapi->logout();

function processRow( $row, $types ) {

	$newRow = [];

	$c = 0;

	foreach ( $row as $el ) {

		if ( array_key_exists( $c, $types ) ) {
			if ( $types[$c] == "int" ) {

				if ( $el !== "" ) {
					$el = intval( $el );
				}
			}
		}

		$c++;

		array_push( $newRow, $el );
	}

	return $newRow;
}



function putPage( $wpapi, $contentTxt, $page ) {

	$params = array( "meta" => "tokens" );
	$getToken = new Mwapi\SimpleRequest( 'query', $params  );
	$outcome = $wpapi->postRequest( $getToken );
	$summary = "Actualitza Catabot";

	if ( array_key_exists( "query", $outcome ) ) {
		if ( array_key_exists( "tokens", $outcome["query"] ) ) {
			if ( array_key_exists( "csrftoken", $outcome["query"]["tokens"] ) ) {

				$token = $outcome["query"]["tokens"]["csrftoken"];
				$params = array( "title" => $page, "summary" => $summary, "text" => $contentTxt, "token" => $token );
				$sendText = new Mwapi\SimpleRequest( 'edit', $params  );
				$outcome = $wpapi->postRequest( $sendText );

			}
		}
	}

}
