#!/bin/sh

URL=$1
N=10000
C=50

cd $(dirname $0)
rm -rf results/*

benchmark () {
  NAME=$1
  RESOURCE=$2
  WEBP=$3
  METHOD=$4

  echo $NAME

  RESULT="results/$NAME.txt"
  echo "# webp: $WEBP method: $METHOD" > $RESULT

  if [ "$WEBP" = "webp" ]; then
    ACCEPT="*/*; image/webp"
    if [ "$METHOD" = "head" ]; then
      curl -I -H "Accept: $ACCEPT" "$URL/$RESOURCE" >> $RESULT
      ab -i -H "Accept: $ACCEPT" -n $N -c $C "$URL/$RESOURCE" >> $RESULT
    else
      curl -I -H "Accept: $ACCEPT" "$URL/$RESOURCE" >> $RESULT
      ab -H "Accept: $ACCEPT" -n $N -c $C "$URL/$RESOURCE" >> $RESULT
    fi
  else
    ACCEPT="*/*"
    if [ "$METHOD" = "head" ]; then
      curl -I -H "Accept: $ACCEPT" "$URL/$RESOURCE" >> $RESULT
      ab -i -H "Accept: $ACCEPT" -n $N -c $C "$URL/$RESOURCE" >> $RESULT
    else
      curl -I -H "Accept: $ACCEPT" "$URL/$RESOURCE" >> $RESULT
      ab -H "Accept: $ACCEPT" -n $N -c $C "$URL/$RESOURCE" >> $RESULT
    fi
  fi

  echo "Sleeping..."
  sleep 10
}

if [ "$URL" = "" ]; then
  echo "Usage: $0 'http://domain/path/to/php-webp-rewrite-image'"
  exit 1
fi

benchmark 'htaccess-webp-head' 'benchmark/htaccess/webp-newer/sample.png' webp head
benchmark 'php-webp-head' 'webp-newer/sample.png'  webp head

benchmark 'htaccess-webp-get' 'benchmark/htaccess/webp-newer/sample.png' webp get
benchmark 'php-webp-get' 'webp-newer/sample.png'  webp get

benchmark 'htaccess-nowebp-head' 'benchmark/htaccess/webp-newer/sample.png' nowebp head
benchmark 'php-nowebp-head' 'webp-newer/sample.png'  nowebp head

benchmark 'htaccess-nowebp-get' 'benchmark/htaccess/webp-newer/sample.png' nowebp get
benchmark 'php-nowebp-get' 'webp-newer/sample.png'  nowebp get
