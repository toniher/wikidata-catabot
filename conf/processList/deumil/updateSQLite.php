<?php
require_once( __DIR__ . '/../../../vendor/autoload.php' );

use League\Csv\Reader;

if ( count( $argv ) > 1 ) {
	$csvfile = $argv[1];
}

if ( count( $argv ) > 2 ) {
	$dbfile = $argv[2];
}

$reader = Reader::createFromPath( $csvfile );

$reader->setOffset(0);
$reader->setDelimiter( "\t" );
$reader->setEnclosure( "\"" );

$results = $reader->fetch();

$count = 0;
$database = new SQLite3($dbfile);

foreach ( $results as $row ) {

  if ( ! empty( $row[0] ) && ! empty( $row[1] ) && ! empty( $row[2] ) && ! empty( $row[3] ) && ! empty( $row[4] ) ) {

    $statement = $database->prepare('select * from `modif` where page = :page and modified = :modified');
    $statement->bindValue(':page', $row[0]);
    $statement->bindValue(':modified', $row[2]);

    $results = $statement->execute();
    $rows = $results->fetchArray();

    if ( ! $rows ) {

      $statement = $database->prepare('insert into `modif` ( page, size, modified, batch, class ) values ( :page, :size, :modified, :batch, :class ) ');
      $statement->bindValue(':page', $row[0]);
      $statement->bindValue(':size', $row[1]);
      $statement->bindValue(':modified', $row[2]);
      $statement->bindValue(':batch', $row[3]);
      $statement->bindValue(':class', $row[4]);

      $results = $statement->execute();
      echo "* Inserted $row[0]\n";
    } else {
      echo "* Nothing $row[0]\n";
    }

  }

}
