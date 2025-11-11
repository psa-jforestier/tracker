<?php

$v = file_get_contents("php://input");
$yearmonth = date('Y-m');
$client = '';
function logs($str)
{
	$fd = fopen(".logs.log", "a");
	fputs($fd, $str);
	fputs($fd, "\r\n");
	fclose($fd);
	error_log($str, 0);
}

function getUserIpAddr()
{ 
	$ip = array();
	if(!empty($_SERVER['HTTP_CLIENT_IP']))
	{ 		$ip[] = $_SERVER['HTTP_CLIENT_IP']; 	} 
	if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
	{ 		$ip[] = $_SERVER['HTTP_X_FORWARDED_FOR']; 	}
	if (!empty($_SERVER['REMOTE_ADDR']))
	{ 		$ip[] = $_SERVER['REMOTE_ADDR']; 	} 
	return implode(";", $ip);
}
$ip = getUserIpAddr();

logs(date('r'));
$method = $_SERVER['REQUEST_METHOD'];
$content = $_SERVER['CONTENT_TYPE'];
$uri = $_SERVER['REQUEST_URI'];
logs($uri);
logs("METHOD : ".$method." ".$content. " From $ip");
logs("SERVER :\n".print_r($_SERVER, true));
logs("RAW DATA :");
logs(print_r($v, true));
logs("POST+GET=REQUEST DATA :");
logs(print_r($_REQUEST, true));


$uri_elements = explode('/', $uri);
$deviceid = basename($uri_elements[6]);
if ($deviceid == ''){
	$deviceid = $_REQUEST['id'];
	if ($deviceid != '')
		$client = 'TRACCAR';
}
if ($deviceid == '')
{
	$overland = json_decode($v);
	$deviceid = $overland->locations[0]->properties->device_id;
	if ($deviceid != '')
		$client = 'OVERLAND';	// https://github.com/aaronpk/Overland-iOS#settings
	/*
	To simulate overland :
	 curl "http://box.forestier.xyz:8889/" --data '{"locations":[{"type":"Feature","geometry":{"type":"Point","coordinates":[2.2074531999999998,48.9724559]},"properties":{"speed":0,"battery_state":"unplugged","motion":["stationary"],"timestamp":"2025-11-07T17:55:35Z","horizontal_accuracy":2,"speed_accuracy":0.29999999999999999,"vertical_accuracy":8,"battery_level":0.45000000000000001,"wifi":"jejebox","course":-1,"device_id":"__IPHONEMAX","altitude":87,"course_accuracy":-1}}]}' --request POST --header "Content-Type: application/json"
	 **/
}
if ($deviceid == '')
{
	http_response_code('403');
	die('!!');
}
$points = array();

if ($method == 'POST' && $client == 'OVERLAND')
{	
	foreach($overland->locations as $p)
	{
		$lat = sprintf("%5.f", $p->geometry->coordinates[1]);
		$lon = sprintf("%5.f", $p->geometry->coordinates[0]);
		$alt = sprintf("%5.f", $p->properties->altitude);
		$deviceid = $p->properties->device_id;
		$bat = sprintf("%d", 100*$p->properties->battery_level);
		$speed = $p->properties->speed;
		$timestamp = strtotime($p->properties->timestamp);
		$wifi = $p->properties->wifi;
		$acc = $p->properties->horizontal_accuracy;
		$points[] = array(
			'deviceid'=>$deviceid,
			'lat'=>$lat,
			'lon'=>$lon,
			'alt'=>$alt,
			'bat'=>$bat,
			'timestamp'=>$timestamp, // epoch
			'dt'=>date(DATE_ATOM, $timestamp), // 2025-11-07T18:05:52+01:00
			'acc'=>$acc,
			// extra attributes not used
			'bat_state'=>$p->properties->battery_state,
			'wifi'=>$p->properties->wifi,
			'motion'=>@$p->properties->motion[0]
			
			
		);
	}
}
if ($method == 'GET' && $client == 'TRACCAR')
{
	// http://box.forestier.xyz:8889/index.php?id=185794&timestamp=1698414732&lat=48.97231&lon=2.2072233&speed=0.0&bearing=0.0&altitude=133.0&accuracy=100.0&batt=98.0
	$lat = sprintf("%.5f",$_REQUEST['lat']);
	$lon = sprintf("%.5f",$_REQUEST['lon']);
	$alt = sprintf("%.5f",$_REQUEST['alt'] . @$_REQUEST['altitude']);
	$acc = sprintf("%.1f",($_REQUEST['acc'] . @$_REQUEST['accuracy']));
	$bat = @$_REQUEST['bat'] . @$_REQUEST['batt'] ;
	$timestamp = $_REQUEST['timestamp'];
	$points = array();
	$points[] = array(
			'deviceid'=>$deviceid,
			'lat'=>$lat,
			'lon'=>$lon,
			'alt'=>$alt,
			'bat'=>sprintf("%d", $bat),
			'timestamp'=>$timestamp,
			'dt'=>date(DATE_ATOM, $timestamp),
			'acc'=>$acc
		);
}
if ($method == 'POST' && $content == 'application/x-www-form-urlencoded')
{
	$ua = $_REQUEST['useragent'];
	$bat = @$_REQUEST['bat'] . @$_REQUEST['batt'] ;
	$lon = sprintf("%.5f",$_REQUEST['lon']);
	$speed = $_REQUEST['speed'];
	$bearing = $_REQUEST['bearing'];
	$timestamp = $_REQUEST['timestamp'];
	$alt = sprintf("%.5f",$_REQUEST['alt'] . @$_REQUEST['altitude']);
	$lat = sprintf("%.5f",$_REQUEST['lat']);
	$sat = $_REQUEST['sat'];
	$acc = sprintf("%.1f",($_REQUEST['acc'] . @$_REQUEST['accuracy']));
	$points = array();
	$points[] = array(
			'deviceid'=>$deviceid,
			'lat'=>$lat,
			'lon'=>$lon,
			'alt'=>$alt,
			'bat'=>$bat,
			'timestamp'=>$timestamp,
			'dt'=>date(DATE_ATOM, $timestamp),
			'acc'=>$acc
		);
}



if ($method == 'POST' && $content == 'application/json' && $client != 'OVERLAND')
{

	
	$data = json_decode(stripslashes($v));
	$points = array();
	foreach($data->points as $p)
	{
		$ua = $p[7];
		$bat = $p[5];
		$lon = sprintf("%.5f",$p[1]);
		$speed = $p[8];
		$bearing = $p[6]; // ou 9 ?
		$timestamp = $p[2];
		$alt = sprintf("%.5f", $p[3]);
		$lat = sprintf("%.5f", $p[0]);
		$sat = $p[9]; // ou 6 ?
		$acc = sprintf("%.1f", $p[4]) + 0.0;
		$points[] = array(
			'deviceid'=>$deviceid,
			'lat'=>$lat,
			'lon'=>$lon,
			'alt'=>$alt,
			'bat'=>$bat,
			'timestamp'=>strtotime($timestamp), // Unix timestamp
			'dt'=>$timestamp, // datestring
			'acc'=>$acc
		);
	}
	
}


logs('RECEIVED POINTS:');
logs(print_r($points, true));

$fd = fopen(basename($deviceid).'-'.$yearmonth.".csv", 'a');
foreach($points as $p)
{
	fputs($fd, sprintf("%s;%d;%3.6f;%3.6f;%5.1f;%3d;%4.1f\r\n",
		$p['dt'],
		$p['timestamp'],
		$p['lat'],
		$p['lon'],
		$p['alt'],
		$p['bat'],
		$p['acc'])
	);
}
fclose($fd);

// Create the HTML file with the last point receveid
// only if accuracy is good
if ($acc < 500)
{
	$lastpoint = $points[count($points) - 1];

	$fd = fopen($deviceid.".html", 'w');
	fputs($fd, <<<EOT
	<html>
	<a href="map2.php?deviceid={$deviceid}">{$lastpoint['dt']} ; {$lat};{$lon} ; precision {$acc} ; battery {$bat}</a> 
	<a href="livemap.php?deviceid={$deviceid}">Live</a>
	<br/>
	<center>
	<iframe width="80%" height="80%" src="https://www.openstreetmap.org/export/embed.html?bbox={$lastpoint['lon']},{$lastpoint['lat']},{$lastpoint['lon']},{$lastpoint['lat']}&amp;layer=mapnik&amp;marker={$lastpoint['lat']},{$lastpoint['lon']}" style="border: 1px solid black"></iframe><br/><small><a href="https://www.openstreetmap.org/?mlat={$lastpoint['lat']}&amp;mlon={$lastpoint['lon']}#map=12">Afficher une carte plus grande</a></small>
	</center>
	</html>
	EOT);

	fclose($fd);
	
	$fd = fopen($deviceid."-last.json", 'w');
	fputs($fd, json_encode(
	  array(
	  'latitude'=>$lat,
	  'longitude'=>$lon,
	  'dt'=>$lastpoint['dt'],
	  'timestamp'=>$lastpoint['timestamp'],
	  'acc'=>$acc,
	  'batt'=>$bat
	  )
	));
	fclose($fd);
}
if ($client == 'OVERLAND')
{
	$res = ['result'=>'ok'];
}
else
{
	$res = [
				'done' => 1,
				'friends' => [],
			];
}
@header("Content-Type: application/json");
echo json_encode($res);
