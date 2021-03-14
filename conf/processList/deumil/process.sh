#!/usr/bin/env bash

EXECPATH="/home/toniher/remote-work/mediawiki/wikidata-catabot"
INPATH="/home/toniher/Nextcloud/Documents/wikidata/deumil"
OUTPATH="/home/toniher/Nextcloud/Documents/wikidata/deumil/csv"
CONFFILE="/home/toniher/remote-work/mediawiki/10000.count.conf"
PATHWIKI="Viquiprojecte:Concursos/Els 10.000/Llista"

cd $EXECPATH

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
  php processList.php $CONFFILE $file 10000 > $OUTPATH/$outfile
  echo $outfile
  pagename=${mapfiles[${name/.txt/}]}
  php tableExportFromCSV.php $CONFFILE $OUTPATH/$outfile importa10000 "$PATHWIKI/$pagename"
  sleep 10
done
