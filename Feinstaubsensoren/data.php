<?php
require_once("zabbixapi.php");
define("ZBX_TEMPLATE_ID_FEINSTAUBSENSOREN", 10814);
define("ZBX_GROUP_ID_FEINSTAUBSENSOREN", 11);

$raw = '{"esp8266id": "735947", "software_version": "NRZ-2017-099", "sensordatavalues":[{"value_type":"SDS_P1","value":"4.53"},{"value_type":"SDS_P2","value":"3.10"},{"value_type":"temperature","value":"9.50"},{"value_type":"humidity","value":"59.10"},{"value_type":"samples","value":"593591"},{"value_type":"min_micro","value":"240"},{"value_type":"max_micro","value":"27665"},{"value_type":"signal","value":"-71"}]}';
$raw = '{"esp8266id": "7332865", "software_version": "NRZ-2017-099", "sensordatavalues":[{"value_type":"SDS_P1","value":"4.87"},{"value_type":"SDS_P2","value":"4.20"},{"value_type":"temperature","value":"3.70"},{"value_type":"humidity","value":"70.10"},{"value_type":"samples","value":"625049"},{"value_type":"min_micro","value":"228"},{"value_type":"max_micro","value":"27427"},{"value_type":"signal","value":"-26"}]}';
$raw = '{"esp8266id": "9940677", "software_version": "NRZ-2017-099", "sensordatavalues":[{"value_type":"SDS_P1","value":"29.30"},{"value_type":"SDS_P2","value":"22.42"},{"value_type":"temperature","value":"23.80"},{"value_type":"humidity","value":"36.90"},{"value_type":"samples","value":"624615"},{"value_type":"min_micro","value":"225"},{"value_type":"max_micro","value":"23083"},{"value_type":"signal","value":"-38"}]}';

if(strcasecmp($_SERVER['REQUEST_METHOD'], 'POST') != 0){
	throw new Exception('Request method must be POST!');
}

//Make sure that the content type of the POST request has been set to application/json
$contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
if(strcasecmp($contentType, 'application/json') != 0){
	throw new Exception('Content type must be: application/json');
}
$raw = trim(file_get_contents("php://input"));

$decoded = json_decode($raw, true);

$ZabbixApi = new ZabbixApi();
/*
$method = "template.get";
$params = array(
#        "output"                => array("templateid"),
#	"output"		=> "extend",
#        "filter"                => array("host" => array("Template Feinstaubsensor (Bausatz)")),
);
$addons = array();
$result = $ZabbixApi->get_zabbix_req($method, $params, $addons);
print_r($result);
#print_r($result);

#print_r($ZabbixApi);
die();
*/

//If json_decode failed, the JSON is invalid.
if(!is_array($decoded)){
	throw new Exception('Received content contained invalid JSON!');
}

if(!($data = normalizeData($decoded))){
	throw new Exception('Data could not be normalized!');
}
/*
if($data['name']=="env.sensors.15351335") {
	$f=fopen("devel.log", "a+");
	fwrite($f, date("Y-m-d H:i:s")."\n");
	#fwrite($f, var_export($_SERVER['REMOTE_ADDR'], TRUE)."\n");
	fwrite($f, var_export($data, true)."\n");
	#fwrite($f, var_export($foo, true)."\n");
	fclose($f);
}
*/
if(!($id = getSensorID("env.sensors.".$data['name'], array("name"=>"Feinstaubsensor ".$data["name"], "template"=>$data["zabbix_template"], "group"=>$data["zabbix_group"])))) {
	throw new Exception('No sensor found!');
}

storeSensordata($id, $data);
$foo = setCurrentHostData($id, $data);
#	$f=fopen("devel.log", "a+");#
#	fwrite($f, date("Y-m-d H:i:s")."\n");
#	fwrite($f, var_export($foo, true)."\n");
#	fclose($f);


################################

function normalizeData($raw) {
	$data= array();
	if(isset($raw['esp8266id'])) {
		$data['name'] = $raw['esp8266id'];
		$data['sensors'] = array();
		$data['zabbix_template'] 	= ZBX_TEMPLATE_ID_FEINSTAUBSENSOREN;
		$data['zabbix_group']		= ZBX_GROUP_ID_FEINSTAUBSENSOREN;
		foreach($raw['sensordatavalues'] as $v) {

			switch($v['value_type']) {
				case "SDS_P1":
					$key = 'particles_p10';
				break;
				case "SDS_P2":
					$key = 'particles_p25';
				break;
				case "temperature":
					$key = 'temperature.dht22';
				break;
				case "BME280_temperature":
					$key = 'temperature.bme280';
				break;
				case "humidity":
					$key = 'humidity.dht22';
				break;
				case "BME280_humidity":
					$key = 'humidity.bme280';
				break;
				case "signal":
					$key = "signal";
				break;
				case "BME280_pressure":
					$key = 'pressure.bme280';
				break;
				case "TSL2561_LUX":
					$key = 'lux';
				break;
				case "GPS_lat":
					$key = 'gps.lat';
					$location = !empty($v['value']);
				break;
				case "GPS_lon":
					$key = 'gps.lon';
					$location = !empty($v['value']);
				break;
				case "GPS_height":
					$key = 'gps.height';
					$location = !empty($v['value']);
				break;
				case "GPS_date":
					$key = 'gps.date';
				break;
				case "GPS_time":
					$key = 'gps.time';
				break;

				case "samples":
				case "min_micro":
				case "max_micro":
				default:
#					$key = 'samples';
#					$key = 'min_micro';
#					$key = 'max_micro';
					$key = FALSE;
				break;
			}
			if($key) {
				$data['sensors'][$key] = $v['value'];
			}
		}
	} else {
		return FALSE;
	}

	$data['ip'] = getClientIP();
	if(!$location) {
		$data['location'] = getRouterBasedPosition($data['ip']);
	} 

	return $data;
}

function getClientIP() {
	return isset($_SERVER['HTTP_X_REAL_IP']) ? $_SERVER['HTTP_X_REAL_IP'] : $_SERVER['REMOTE_ADDR'];
}

function getRouterBasedPosition($ip) {
	$c = file_get_contents("https://dev.ffggrz.de/ip2coords/?ip=".$ip."&output=json");
	if($c = json_decode($c, TRUE)) {
		return $c;
	} else {
		return false;
	}
}

function getSensorID($id, $data=NULL) {
	global $ZabbixApi;
	$method = "host.get";
	$params = array(
	        "output"                => array("hostid"),
	        "filter"                => array("host" => array($id)),
	);
	$addons = array();
	$result = $ZabbixApi->get_zabbix_req($method, $params, $addons);

	if(!isset($result->result[0]->hostid) && is_array($data)) {
		$id = addNewSensor($id, $data["name"], $data["template"], $data["group"]);
	} else {
		$id = $result->result[0]->hostid;
	}
	return $id;
}

/**
*	Der API-Benutzer muss Leserechte auf der Templategruppe haben und Schreibrechte auf der Sensorengruppe!
*/
function addNewSensor($sid, $name, $template, $group) {
	global $ZabbixApi;
	$method = "host.create";
	$params = array(
	        "host"		=> $sid,
		"name"		=> $name,
		"interfaces"	=> array(array("type"=>1, "main"=>1, "useip"=>1, "ip"=>"127.0.0.1", "dns"=>"", "port"=>10050)),
		"templates"	=> array(array("templateid"=>$template)),
		"groups"	=> array(array("groupid"=>$group)),
		"inventory_mode"=> 1
	);
	$addons = array();
	$result = $ZabbixApi->get_zabbix_req($method, $params, $addons);

	return isset($result->result[0]->hostid) ? $result->result[0]->hostid : FALSE;
}

function storeSensordata($sensorId, $data) {
#	global $ZabbixApi;
	$store = array();
	foreach($data['sensors'] as $type => $v) {
		if(empty($v)) continue;
		$store[] = "env.sensors.".$data['name']." env.sensors.".$type." ".$v;
	}

	$tmpfname = tempnam("/tmp", "FOO");
	$handle = fopen($tmpfname, "w");
	fwrite($handle, implode("\n", $store));
	fclose($handle);
$f=fopen("devel.log", "a+");
fwrite($f, date("Y-m-d H:i:s")."\n");
fwrite($f, implode("\n", $store)."\n");
fclose($f);
	$cmd = "/usr/bin/zabbix_sender -z localhost -p 10051 --input-file \"".$tmpfname."\"";
	$cmd = escapeshellcmd($cmd);
	exec($cmd, $out);
	unlink($tmpfname);

}

function setCurrentHostData($hostid, $data) {
	global $ZabbixApi;
	$method = "host.update";
	$params = array("hostid"=>$hostid);

	if(isset($data['ip'])) {
		$params["interfaces"] =	array(array("type"=>1, "main"=>1, "useip"=>1, "ip"=>$data['ip'], "dns"=>"", "port"=>10050));
	}

	if(isset($data['location']) && $data['location']) {
		if(isset($data['location']['lat']) && !empty($data['location']['lat'])) {
			$params["inventory"]["location_lat"] = $data['location']['lat'];
		}
		if(isset($data['location']['lng']) && !empty($data['location']['lng'])) {
			$params["inventory"]["location_lon"] = $data['location']['lng'];
		}
		#if(isset($data['location']['alt'])) {}
	}
$f=fopen("devel.log", "a+");
fwrite($f, date("Y-m-d H:i:s")."\n");
fwrite($f, implode("\n", $data)."\n");
fclose($f);

	$addons = array();
	return $ZabbixApi->get_zabbix_req($method, $params, $addons);
}

?>
