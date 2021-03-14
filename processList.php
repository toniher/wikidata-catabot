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
			$row_wiki = [];

			$article_main = [];

			array_push( $row, $wdid );
			# array_push( $row_wiki, formatWiki( $wdid, "d" ) );

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
					$article_main[$lang] = $pagename;

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

			# Process data here
			foreach ( $props["langs_main"] as $lang ) {

				if ( array_key_exists( $lang, $article_main ) ) {

					foreach( $props["processes"] as $prop ) {

						$outcome = getProcessWp( $wpapi[$lang], $prop, $article_main[$lang] );
						if ( is_array( $outcome ) ) {
							foreach( $outcome as $out ) {
								array_push( $row, $out );
							}
						}

					}

				} else {
					foreach( $props["processes"] as $prop ) {
						$outcome = getProcessWp( $wpapi[$lang], $prop, null );
						if ( is_array( $outcome ) ) {
							foreach( $outcome as $out ) {
								array_push( $row, $out );
							}
						}
					}
				}
			}

			echo implode( "\t", $row )."\n";

			sleep( 5 ); // Delay 5 seconds
		}
	}

}

# Logout from wikis
foreach ( $props["langs_main"] as $lang ) {
	$wpapi[$lang]->logout();
}

$api->logout();
