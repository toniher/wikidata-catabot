#!/usr/bin/env bash

export DATE=$(date +%Y-%m-%dT%H:%M:%S)

EXECPATH="/home/toniher/remote-work/mediawiki/wikidata-catabot"
INPATH="/home/toniher/Nextcloud/Documents/wikidata/deumil"
OUTPATH="/home/toniher/Nextcloud/Documents/wikidata/deumil/csv/$DATE"
CONFFILE="/home/toniher/remote-work/mediawiki/10000.count.conf"
PATHWIKI="Viquiprojecte:Concursos/Els_10.000/Llista"
DBFILE="/home/toniher/Nextcloud/Documents/wikidata/deumil/db/update.db"

cd $EXECPATH
mkdir -p "${OUTPATH}"

declare -A mapfiles
mapfiles["antropologia.800"]="Antropologia"
mapfiles["arts.600"]="Arts"
mapfiles["biografies.2000"]="Biografies"
mapfiles["biologia.1100"]="Biologia"
mapfiles["filosofia.350"]="Filosofia"
mapfiles["fisica.1350"]="Física"
mapfiles["geografia.1000"]="Geografia"
mapfiles["historia.800"]="Història"
mapfiles["mates.300"]="Matemàtiques"
mapfiles["societat.900"]="Societat"
mapfiles["tecnologia.800"]="Tecnologia"


for file in "$INPATH"/*txt
do
  echo "$file"
  name=$(basename $file)
  echo $name
  outfile=${name/.txt/.csv}
  php processList.php $CONFFILE $file 10000 > "$OUTPATH/$outfile"
  echo $outfile
  pagename=${mapfiles[${name/.txt/}]}
  if [ -s "$OUTPATH/$outfile" ]; then 
	php tableExportFromCSV.php $CONFFILE $OUTPATH/$outfile importa10000 "$PATHWIKI/$pagename"
  	export pagename
  	perl -F"\t" -lane 'if ( $F[3] ) { print "$F[3]\t$F[11]\t$F[12]\t${ENV{\"DATE\"}}\t${ENV{\"pagename\"}}" }' "$OUTPATH/$outfile" > "$OUTPATH/$outfile.tmp"
  fi
  sleep 10
done

# Process SQLite
TEMPDIR="/tmp"
cat "$OUTPATH/*.tmp" > "$TEMPDIR/all.tmp"
cd "$EXECPATH/conf/processList/deumil"
php updateSQLite.php "$TEMPDIR/all.tmp" "$DBFILE" > /tmp/update10000SQL.log 2> /tmp/update10000SQL.err
cp "$DBFILE" "$OUTPATH/"
