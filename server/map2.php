<?php

$deviceid = @$_GET['deviceid'];
$yearmonth = date('Y-m');

if ($deviceid == '')
	die('!?');

// Read last data point
$fd = fopen(basename($deviceid).'-'.$yearmonth.".csv", "r");
// read all each of the records and assign to $rec;
$TAIL = 400;
$i = 0;
$p = array();
$oldp = "";
$nbline = 0;
while ($rec = fgetcsv($fd, 1000, ";"))
{
	$newp = $rec[2].";".$rec[3];
	if ($newp != $oldp)
	{
		$p[$i % $TAIL] = array(
			'dt'=>$rec[0],
			'timestamp'=>$rec[1],
			'lat'=>$rec[2],
			'lon'=>$rec[3],
			'alt'=>$rec[4],
			'bat'=>$rec[5],
			'acc'=>$rec[6]
		);
		$i++;
		$oldp = $newp;
	}
}
$nbline = $i;
$points = array();
for($j=$i; $j <= $i+$TAIL - 1; $j++)
{
	$points[] = $p[$j % $TAIL];
}
$lastpoint = $points[count($points) - 1];
?>
<!--

-->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carte <?=$deviceid ?></title>
	<link rel="shortcut icon" href="data:image/x-icon;," type="image/x-icon"> 
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <style>
		html, body {
            height: 100%;
            margin: 0;
        }
        #map {
            height: 100%;
            width: 100%;
        }
        .blinking {
            animation: blinker 1s linear infinite;
        }
        .slow-blinking {
            animation: slow-blinker 2s linear infinite;
        }
        @keyframes blinker {
            50% { opacity: 0; }
        }
        @keyframes slow-blinker {
            50% { opacity: 0; }
        }
    </style>
</head>
<body>
	Display <?= $TAIL ?> on <?=$nbline?> lines. <a href="livemap.php?deviceid=<?=$deviceid?>">Live</a><br/>
	<input type="button" value="<" onclick="return onClickPrevious();"/>
	<input style="font-size:10px" id="pininfo" type="text" readonly="true" size="35"/>
	<input type="button" value=">" onclick="return onClickNext();"/>
	<input type="hidden" value="" id="position_id"/> <br/>
	Distance : <input type="button" value="Set Start" onclick="return onClickSetStart()"/> <!-- <input style="font-size:10px" id="distance" type="text" readonly="true" size="10"/> -->
	<span style="font-size:12px" id="distance"> </span>
	<!-- <input type="button" value="Stop" onclick="return onClickSetStop()"/> -->
	
	<br/>
    <div id="map"></div>
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script>
	    document.addEventListener('keydown', function(event) {
            if (event.key === 'ArrowLeft') {
                onClickPrevious();
            } else if (event.key === 'ArrowRight') {
                onClickNext();
            }
        });
        
		var map = L.map('map').setView([0.505, -0.09], 13);

        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
			minZoom: 1,
			maxZoom: 30
        }).addTo(map);

        var data = [
		
<?php
				foreach($points as $i=>$p)
				{
					$op = ($i+1)*(1/count($points));
					if ($p !== NULL)
					{
						if ($p['lat'] != 0 && $p['lon'] != 0)
						{
							printf("{lat: %.5f, lng: %.5f, battery: %d, timestamp:%d, acc:%.1f},\n",
							  $p['lat'],
							  $p['lon'],
							  $p['bat'],
							  $p['timestamp'],
							  $p['acc']
							);
						}
					}
				}
?>
        ];

        var latlngs = data.map(function(point) {
            return [point.lat, point.lng];
        });
		
		var selectedPointIdx;

        function getColor(battery) {
            var r = Math.floor(255 * (1 - battery / 100));
            var g = Math.floor(255 * (battery / 100));
            var b = 0;
            return 'rgb(' + r + ',' + g + ',' + b + ')';
        }

        function getGradientColor(startColor, endColor, percent) {
            var start = startColor.match(/\d+/g).map(Number);
            var end = endColor.match(/\d+/g).map(Number);
            var r = Math.floor(start[0] + percent * (end[0] - start[0]));
            var g = Math.floor(start[1] + percent * (end[1] - start[1]));
            var b = Math.floor(start[2] + percent * (end[2] - start[2]));
            return 'rgb(' + r + ',' + g + ',' + b + ')';
        }
		
		function formatTimestamp(epoch) {
            var date = new Date(epoch * 1000);
            var day = ('0' + date.getDate()).slice(-2);
            var month = ('0' + (date.getMonth() + 1)).slice(-2);
            var year = date.getFullYear();
            var hours = ('0' + date.getHours()).slice(-2);
            var minutes = ('0' + date.getMinutes()).slice(-2);
            var seconds = ('0' + date.getSeconds()).slice(-2);
            return day + '/' + month + '/' + year + ' ' + hours + ':' + minutes + ':' + seconds;
        }

        var polylineOptions = { color: 'blue', weight: 5, opacity: 1 };
        var polyline = L.polyline([], polylineOptions).addTo(map);

        var segments = [];
        for (var i = 0; i < latlngs.length - 1; i++) {
            var startLatLng = latlngs[i];
            var endLatLng = latlngs[i + 1];
            var percent = i / (latlngs.length - 1);
            var color = getGradientColor('rgb(173, 216, 230)', 'rgb(0, 0, 139)', percent);
            var segment = L.polyline([startLatLng, endLatLng], {color: color, weight: 4, opacity: 1}).addTo(map);
            segments.push(segment);
        }

        var markers = [];
        data.forEach(function(point, index) {
            var color = getColor(point.battery);
            var marker = L.circleMarker([point.lat, point.lng], {
                radius: 8,
                fillColor: color,
                color: color,
                weight: 1,
                opacity: 1,
                fillOpacity: 0.8,
                className: index === 0 ? 'slow-blinking' : (index === data.length - 1 ? 'blinking' : '')
            }).addTo(map);

            //marker.bindPopup('Battery level: ' + point.battery + '%<br>Timestamp: ' + formatTimestamp(point.timestamp));
            markers.push(marker);

            marker.on('click', function() {
				/**
                
				**/
				onClickOnPin(point, index)
            });

            if (index == 0 || index === data.length - 1) {
                var highlightColor = index == 0 ? 'brown' : 'red';
                var markers_highlight = L.circleMarker([point.lat, point.lng], {
                    radius: 12,
                    fillColor: 'transparent',
                    color: highlightColor,
                    weight: 2,
                    opacity: 1,
                    fillOpacity: 0.8
                }).addTo(map);
                if (index == 0) marker_first = markers_highlight;
                if (index == data.length - 1) marker_last = markers_highlight;
            }
        });

        // Bring the first and last markers to the front
		marker_first.bringToFront();
		marker_last.bringToFront();
        markers[0].bringToFront();
        markers[markers.length - 1].bringToFront();

        map.fitBounds(L.latLngBounds(latlngs));
		
		function onClickOnPin(point, index)
		{
			segments.forEach(function(segment, i) {
				percent = i / (segments.length - 1);
				segment.setStyle({color: getGradientColor('rgb(173, 216, 230)', 'rgb(0, 0, 139)', percent)});
			});
			if (index > 0) {
				segments[index - 1].setStyle({color: 'brown'});
				segments[index - 1].bringToFront();
			}
			if (index < segments.length) {
				segments[index].setStyle({color: 'red'});
				segments[index].bringToFront();
			}
			console.log(index + " : ");
			console.log(point);
			document.getElementById('pininfo').value = formatTimestamp(point.timestamp) + ': Batt=' + point.battery + '% - Acc.=' + point.acc;
			document.getElementById('position_id').value = index;
			selectedPointIdx = index; // Make it available global for distance computation
			onClickSetStop();
		}
		
		function onClickPrevious()
		{
			current = Number(document.getElementById('position_id').value);
			if (current > 0)
				current = current - 1;
			console.log("move to "+current);
			document.getElementById('position_id').value = current;
			point = data[current];
			onClickOnPin(point, current);
			map.panTo(new L.LatLng(point.lat, point.lng));
			onClickSetStop();
		}
		
		function onClickNext()
		{
			current = Number(document.getElementById('position_id').value);
			if (current < data.length)
				current = current + 1;
			console.log("move to "+current);
			document.getElementById('position_id').value = current;
			point = data[current];
			onClickOnPin(point, current);
			map.panTo(new L.LatLng(point.lat, point.lng));
			onClickSetStop();
		}
    </script>
	
	<script language="javascript">
	var idx_start;
	var idx_stop;
	idx_start = idx_stop = -1;
	function onClickSetStart()
	{
		idx_start = selectedPointIdx;
		calculateDistance();
	}
	
	function onClickSetStop() 
	{
		idx_stop = selectedPointIdx;
		calculateDistance();
	}
	
	function calculateDistance()
	{
		if (idx_start == -1 || idx_stop == -1)
			return;
		console.log("Calculate dist from point " + idx_start + " to point " + idx_stop);
		const R = 6371e3; // Earth's radius in meters
		distance = 0.0;
		for (i=Math.min(idx_start, idx_stop); i<Math.max(idx_start, idx_stop); i++)
		{
			console.log(" point " + i + " : " + data[i]);
			distance += haversineDistance(data[i], data[i + 1]);
		}
		// Speed
		T = Math.abs(data[idx_stop].timestamp - data[idx_start].timestamp);
		if (T == 0)
			speed_kmh = 0;
		else
			speed_kmh = (distance/T) * 3.6;
		document.getElementById('distance').innerText  = distance.toLocaleString('fr-FR', { maximumFractionDigits: 0 }) 
				+ "m / " + speed_kmh.toLocaleString('fr-FR', { maximumFractionDigits: 1 })+" km/h"
				;

	}
	
	function haversineDistance(point1, point2) {
		const R = 6371e3; // Earth's radius in meters
		const lat1 = point1.lat * Math.PI / 180;
		const lat2 = point2.lat * Math.PI / 180;
		const deltaLat = (point2.lat - point1.lat) * Math.PI / 180;
		const deltaLng = (point2.lng - point1.lng) * Math.PI / 180;

		const a = Math.sin(deltaLat / 2) * Math.sin(deltaLat / 2) +
				  Math.cos(lat1) * Math.cos(lat2) *
				  Math.sin(deltaLng / 2) * Math.sin(deltaLng / 2);
		const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

		const distance = R * c; // Distance in meters
		return distance;
	}
	
	</script>
	<hr/>
	EN LIVE !!
	
	<div id="maplive"></div>

    <script>
        // Initialisation de la carte
        var map = L.map('map').setView([48.97222, 2.20555], 13);

        // Ajout de la couche OpenStreetMap
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);

        // Ajout du marqueur initial
        var marker = L.marker([48.97222, 2.20555]).addTo(map);

        // Fonction pour mettre à jour la position du marqueur
        function updateMarkerPosition() {
            fetch('https://example.com/position.json') // Remplacez par l'URL de votre flux JSON
                .then(response => response.json())
                .then(data => {
                    var lat = data.latitude;
                    var lon = data.longitude;
                    marker.setLatLng([lat, lon]);
                    map.setView([lat, lon], 13);
                })
                .catch(error => console.error('Erreur lors de la récupération des données:', error));
        }

        // Mise à jour de la position toutes les minutes
        setInterval(updateMarkerPosition, 60000);

        // Mise à jour initiale
        updateMarkerPosition();
    </script>
</body>
</html>
