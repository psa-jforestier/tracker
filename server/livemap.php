<?php

$deviceid = @$_GET['deviceid'];
$yearmonth = date('Y-m');

if ($deviceid == '')
	die('!?');
$fmt = @$_REQUEST['fmt'];
if ($fmt === 'json')
{
	header('Content-Type: application/json');
	$data = array('latitude'=>0.0, 'longitude'=>0.0);
	echo json_encode($data);
	die();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Position de <?= $deviceid ?></title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <style>
	html, body {
            height: 100%;
            margin: 0;
        }
	#livemap {
		height: 99vh;
		width: 99vw;
		margin: auto;
	}
    </style>
</head>
<body>
    <input id="pininfo" type="text" readonly="true" size="40" value="Loading..."/> | 
	<a href="map2.php?deviceid=<?=$deviceid?>">Historique</a> | 
	<a href="#" onclick="javascript:findMe(); return true;">Me</a> | 
	<a href="#" onclick="javascript:goTo(); return true;">Go</a> | 
	<a href="#" onclick="javascript:updateMarkerPosition(); return true;">Update</a>
	<span id="myInfo"></span>
    <div id="livemap"></div>

    <script>
	if (!document.URL.toString().startsWith("https://"))
	{
		u = new URL(document.URL);
		document.location.href = "https://"+u.hostname+":4439/" + u.pathname+"?"+u.search;
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
// Fonction pour géolocaliser l'utilisateur et afficher un marqueur
		var myMarker;
		var myMarkerLine;
		function goTo() {
			url = 'https://www.google.com/maps/dir/?api=1&destination=' + livemarker.getLatLng().lat + '%2C' + livemarker.getLatLng().lng;
			window.open(url, '_blank').focus();
		}
        function findMe() {
            if (navigator.geolocation) {
				document.getElementById('myInfo').textContent = "Recherche position...";
                navigator.geolocation.getCurrentPosition(function(position) {
                    var lat = position.coords.latitude;
                    var lon = position.coords.longitude;
					myMarker = L.marker([lat,lon],
						{
							clickable: true,
							draggable: true,
							opacity: 0.5							
						}
					).addTo(livemap);
					myMarker.on('dragend', function(event){
						//MyMarkerLine
						//myMarkerLine.setLatLngs(myMarker.getLatLng(), livemarker.getLatLng()).addTo(livemap);
						livemap.removeLayer(myMarkerLine)
						myMarkerLine = L.polyline([myMarker.getLatLng(), livemarker.getLatLng()], {weight: 4, opacity: 1}).addTo(livemap);
						distance = haversineDistance(myMarker.getLatLng(), livemarker.getLatLng());
						document.getElementById('myInfo').textContent = distance.toLocaleString('fr-FR', { maximumFractionDigits: 0 });
					});
                    myMarker.setLatLng([lat, lon]);
					
					/**myMarker = L.circleMarker(
						[lat,lon],
						{
							clickable: true,
							draggable: true
						}
					).addTo(livemap);**/
					myMarker.setLatLng([lat, lon]);
					myMarkerLine = L.polyline([[lat,lon], livemarker.getLatLng()], {weight: 4, opacity: 1}).addTo(livemap);
					
                    livemap.panTo([lat, lon]);
					distance = haversineDistance(myMarker.getLatLng(), livemarker.getLatLng());
                    document.getElementById('myInfo').textContent = distance.toLocaleString('fr-FR', { maximumFractionDigits: 0 });
                }, function(error) {
                    alert('Erreur lors de la géolocalisation: ' + error.message);
                });
            } else {
                alert('La géolocalisation n\'est pas supportée par ce navigateur.');
            }
        }
        // Initialisation de la carte
        var livemap = L.map('livemap').setView([0,0], 13);

        // Ajout de la couche OpenStreetMap
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
			minZoom: 1,
			maxZoom: 30
        }).addTo(livemap);

        // Ajout du marqueur initial
        var livemarker = L.marker([0,0]).addTo(livemap);
		var oldMarkers = [];
		var oldLines = [];
		
        // Fonction pour mettre à jour la position du marqueur
        function updateMarkerPosition() {
			document.getElementById('pininfo').value = "Loading...";
            fetch('./<?=@$_REQUEST['deviceid']?>-last.json')
                .then(response => response.json())
                .then(data => {
                    var lat = data.latitude;
                    var lon = data.longitude;
					if (lat != livemarker.getLatLng().lat || lon != livemarker.getLatLng().lng)
					{
						// Marker has move
						var oldPos = livemarker.getLatLng();
						var grey = L.marker([oldPos.lat, oldPos.lng], {opacity: 0.5 });
						grey.addTo(livemap);
						oldMarkers.push(grey);
						if (oldMarkers.length > 1)
						{
							var line = L.polyline([[oldPos.lat, oldPos.lng], [lat, lon]], { color: 'blue', weight: 2, opacity: 0.5 }).addTo(livemap);
							oldLines.push(line);
						}
						// Place new marker
						livemarker.setLatLng([lat, lon]);
						//livemap.setView([lat, lon], 13);
						livemap.panTo([lat, lon]);
					}
					i = document.getElementById('pininfo');
					i.value =  formatTimestamp(data.timestamp) + ': Batt=' + data.batt + '% - Accuracy=' + data.acc;;
                })
                .catch(error => console.error('Erreur lors de la récupération des données:', error));
        }

        // Mise à jour de la position toutes les minutes
        setInterval(updateMarkerPosition, 60*1000);

        // Mise à jour initiale
        updateMarkerPosition();
		
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
    </script>
</body>
</html>
