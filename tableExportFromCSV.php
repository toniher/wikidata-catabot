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
		switch( $taskname ) {
			case 'importa10000' :
				$row = processImporta10000( $row );
			default:
				$row = $row;
		}

		$string.= $si.implode( $ss, $row )."\n";
	}

}

$string.="|}\n";

if ( $string ) {

	echo $string, "\n";
	putPage( $wpapi, $string, $finalpage );

}

$wpapi->logout();


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

/** TODO to be moved for cleanliness **/
function processImporta10000( $row ) {
	$newRow = [];

	$wd = formatWiki( $row[0], "d" );
	$lang = $row[1];
	$desc = $row[2];
	$article = formatWiki( $row[3] ) ;
	$badge = detectBadge( $row[4] );

	$article_en = formatWiki( $row[5], "en", "en" ) ;
	$badge_en = detectBadge( $row[6] );
	$part_en = trim($article_en.$badge_en);

	$article_es = formatWiki( $row[7], "es", "es" ) ;
	$badge_es = detectBadge( $row[8] );
	$part_es = trim($article_es.$badge_es);

	$article_fr = formatWiki( $row[9], "fr", "fr" ) ;
	$badge_fr = detectBadge( $row[10] );
	$part_fr = trim($article_fr.$badge_fr);

	$interwiki = trim( implode( " ", [ $part_en, $part_es, $part_fr] ) );

	$size = $row[11];
	$timestamp = $row[12];

	$newRow = [ $wd, $lang, $desc, $article.$badge, $interwiki, $size, $timestamp ];
	return $newRow;

}

function formatWiki( $val, $prefix=null, $text=null ) {

	if ( $val ) {
		$link = $val;
		$show = $val;

		if ( $prefix ) {
			$link = ":".$prefix.":".$val;
		}

		if ( $text ) {
			$show = $text;
		}

		if ( $show == $val && ! $prefix ) {
			return "[[".$show."]]";
		} else {
			return "[[$link|$show]]";
		}
	} else {
		return "";
	}

}

function detectBadge( $val ) {
	if ( ! empty( $val ) ) {
		return " 🏅";
	} else {
		return "";
	}
}
