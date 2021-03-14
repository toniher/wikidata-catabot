<?php

require_once( __DIR__ . '/vendor/autoload.php' );
require_once( __DIR__ . '/lib/resolve.php' );
require_once( __DIR__ . '/lib/action.php' );
require_once( __DIR__ . '/lib/wpprocess.php' );

use \Mediawiki\Api as MwApi;
use \Wikibase\Api as WbApi;
use \Mediawiki\DataModel as MwDM;
use \Wikibase\DataModel as WbDM;

// Detect commandline args
$conffile = 'config.json';
$list = null;
$taskname = null; // If no task given, exit
$resolve = true; // If we allow wikipedia resolving


if ( count( $argv ) > 1 ) {
	$conffile = $argv[1];
}

if ( count( $argv ) > 2 ) {
	$list = $argv[2];
}

if ( count( $argv ) > 3 ) {
	$taskname = $argv[3];
}

// Detect if files
if ( ! file_exists( $conffile ) && ! file_exists( $list ) ) {
	die( "Files needed" );
}

$confjson = json_decode( file_get_contents( $conffile ), 1 );

$wikiconfig = null;
$wikidataconfig = null;

if ( array_key_exists( "wikipedia", $confjson ) ) {
	$wikiconfig = $confjson["wikipedia"];
}

if ( array_key_exists( "wikidata", $confjson ) ) {
	$wikidataconfig = $confjson["wikidata"];
}

if ( array_key_exists( "tasks", $confjson ) ) {
	$tasksConf = $confjson["tasks"];
}

if ( array_key_exists( "resolve", $confjson ) ) {
	$resolve = $confjson["resolve"];
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

$api = new MwApi\MediawikiApi( $wikidataconfig['url'] );
$api->login( new MwApi\ApiUser( $wikidataconfig['user'], $wikidataconfig['password'] ) );

$dataValueClasses = array(
    'unknown' => 'DataValues\UnknownValue',
    'string' => 'DataValues\StringValue',
    'boolean' => 'DataValues\BooleanValue',
    'number' => 'DataValues\NumberValue',
    'time' => 'DataValues\TimeValue',
    'globecoordinate' => 'DataValues\Geo\Values\GlobeCoordinateValue',
    'wikibase-entityid' => 'Wikibase\DataModel\Entity\EntityIdValue',
);

$wbFactory = new WbApi\WikibaseFactory(
    $api,
    new DataValues\Deserializers\DataValueDeserializer( $dataValueClasses ),
    new DataValues\Serializers\DataValueSerializer()
);

$wpapi = [];
// $wpFactory = [];

foreach ( $props["langs_main"] as $lang ) {
	$wpapi = initializeLogin( $wpapi, $lang, $wikidataconfig['user'], $wikidataconfig['password'] );
	// $wpFactory = initializeFactory( $wpFactory, $wpapi, $lang );

}

$listFile = new SplFileObject($list);

$table = [];
$article_main = [];
$wp_main = [];

foreach ( $props["langs_main"] as $lang ) {

	$article_main[$lang] = [];
	$wp_main[$lang] = [];
}

while (!$listFile->eof()) {

	$line = trim( $listFile->fgets() );

	$wdid = null;

	if ( substr( $line, 0, 1 ) === "#" ) {
		# Skip if # -> Handling errors, etc.

		continue;
	}

	if ( ! empty( $line ) ) {

		// Do we resolve WikiData from Wikipedia?
		if ( $resolve ) {
			$wdid = retrieveWikidataId( $line, $wikiconfig, $wikidataconfig );
		}

		if ( $wdid ) {

			# echo "ENTRY: ".$wdid."\n"; // Only considers id -> ACTION done via configuration

			$itemLookup = $wbFactory->newItemLookup();
			$termLookup = $wbFactory->newTermLookup();

			$itemId = new WbDM\Entity\ItemId( $wdid );
			$item = $itemLookup->getItemForId( $itemId );
			$labels = $item->getLabels();
			$descriptions = $item->getDescriptions();
			$sitelinks = $item->getSiteLinkList();

			# $enLabel = $termLookup->getLabel( $itemId, 'en' );
			#enDesc = $termLookup->getDescription( $itemId, 'en' );
			$row = [];

			array_push( $row, $wdid );

			foreach ( $props["langs_main"] as $lang ) {

				if ( array_key_exists( $lang, $labels ) ) {
					array_push( $row, $labels[$lang] );
				} else {
					array_push( $row, "" );
				}
				if ( array_key_exists( $lang, $descriptions ) ) {
					array_push( $row, $descriptions[$lang] );
				} else {
					array_push( $row, "" );
				}

				$langwiki = $lang."wiki";
				if ( $sitelinks->hasLinkWithSiteId( $langwiki ) ) {
					$pagename = $sitelinks->getBySiteId($langwiki)->getPageName();
					$badges = $sitelinks->getBySiteId($langwiki)->getBadges();
					array_push( $row, $pagename );
					array_push( $row, implode( ", ", $badges ) );
					$article_main[$lang][$wdid] = $pagename;

				} else {
					array_push( $row, "" );
					array_push( $row, "" );
				}


			}

			foreach ( $props["langs_article"] as $lang ) {

				$langwiki = $lang."wiki";
				if ( $sitelinks->hasLinkWithSiteId( $langwiki ) ) {
					$pagename = $sitelinks->getBySiteId($langwiki)->getPageName();
					$badges = $sitelinks->getBySiteId($langwiki)->getBadges();
					array_push( $row, $pagename );
					array_push( $row, implode( ", ", $badges ) );
				} else {
					array_push( $row, "" );
					array_push( $row, "" );
				}

			}

			$table[$wdid] = $row;

			sleep( 0.5 ); // Delay 0.5 seconds
		}
	}
}

#var_dump( $table );

# Process data here
foreach ( $props["langs_main"] as $lang ) {

	if ( array_key_exists( $lang, $article_main ) ) {

		$pages = array_values( $article_main[$lang] );

		foreach( $props["processes"] as $process ) {

			$store = [];
			$i = 0;
			foreach ( $pages as $page ) {

				array_push( $store, $page );

				$i++;
				if ( $i > 25 ) {
					$outcome = getProcessWp( $wpapi[$lang], $process, $store );
					sleep( 1 );
					$wp_main = addToWpProcess( $wp_main, $lang, $process, $outcome );
					// var_dump( $outcome );
					$store = [];
					$i = 0;
				}
			}

			if ( count( $store ) > 0 ) {
				$outcome = getProcessWp( $wpapi[$lang], $process, $store );
				$wp_main = addToWpProcess( $wp_main, $lang, $process, $outcome );
				// var_dump( $outcome );
			}
		}
	}
}

#var_dump( $wp_main );

# Print table
foreach ( $table as $wdid => $row ) {

	$extra = [];

	foreach ( $props["langs_main"] as $lang ) {

		foreach( $props["processes"] as $process ) {

			if ( array_key_exists( $wdid, $article_main[$lang]) ) {
				$page = $article_main[$lang][$wdid];
				if ( array_key_exists( $page, $wp_main[$lang][$process] ) ) {
					$extra = $wp_main[$lang][$process][$page];
				}
			} else {
				$extra = getProcessWp( $wpapi[$lang], $process, null );
			}

		}

		echo implode( "\t", array_merge( $row, $extra ) )."\n";

	}

}

# Logout from wikis
foreach ( $props["langs_main"] as $lang ) {
	$wpapi[$lang]->logout();
}

$api->logout();

function addToWpProcess( $container, $lang, $process, $outcome ) {
	if ( ! array_key_exists( $process, $container[$lang] ) ) {
		$container[$lang][$process] = [];
	}
	foreach ( $outcome as $key => $value ) {
		$container[$lang][$process][$key] = $value;
	}

	return $container;
}
