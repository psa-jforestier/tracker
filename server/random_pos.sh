#!/bin/bash
# create random point
# simulate the traccar client
# http://box.forestier.xyz:8889/index.php?id=185794&timestamp=1698414732&lat=48.97231&lon=2.2072233&speed=0.0&bearing=0.0&altitude=133.0&accuracy=100.0&batt=98.0

DEVICE="RAND"
LAT=48.97
LNG=2.20
ALT=100
RANDOMNESS=0.1
BAT=80
ACC=100
SPEED=0
BEARING=0

NOW=$(date +%s)
NEW_LAT=$(awk -v lat="$LAT" -v max="$RANDOMNESS" -v seed="$RANDOM" 'BEGIN { srand(seed); s = (rand() < 0.5) ? -1 : 1; printf "%.6f", lat + s * rand() * max }')
NEW_LNG=$(awk -v lat="$LNG" -v max="$RANDOMNESS" -v seed="$RANDOM" 'BEGIN { srand(seed); s = (rand() < 0.5) ? -1 : 1; printf "%.6f", lat + s * rand() * max }')

URL="http://box.forestier.xyz:8889/index.php?id=${DEVICE}&timestamp=${NOW}&lat=${NEW_LAT}&lon=${NEW_LNG}&speed=${SPEED}&bearing=${BEARING}&altitude=${ALT}&accuracy=${ACC}&batt=${BAT}"
echo $URL
curl "$URL"