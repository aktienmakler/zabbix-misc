#!/usr/bin/php
<?php
/**
*       @author Matthias Drobny, Freifunk Gera-Greiz
*       @date 2018-08-23
*       @version 1.2
*       Change in parameters! Instead of an url to the raw.json file the domain part is used to get different raw-based files.
*       This change leads to a possible usage in Yannick instead being made only for hopglass-server.
*/
date_default_timezone_set("Europe/Berlin");

$args = $_SERVER[ 'argv' ];
if(count($args)<=2) {exit("too few arguments");}
array_shift($args);
$url = dirname(array_shift($args));

define("NODES_JSON", 		$url."/nodes.json");
define("NODELIST_JSON", 	$url."/nodelist.json");
define("GRAPH_JSON", 		$url."/graph.json");
define("MESHVIEWER_JSON", 	$url."/meshviewer.json");

$task = array_shift($args);     //      discovery, getValue

$debug = FALSE;

switch($task) {
	default:
	case "discovery":
		if(!($data=getData(NODES_JSON))) exit();	//	Exit on error 
		$discoveryRawJson = array();
		foreach($data->nodes as $n) {
			if(@isset($n->nodeinfo->node_id) && !empty($n->nodeinfo->node_id) && !empty($n->nodeinfo->hostname)) {
				$discoveryRawJson[] = array("{#NODEID}" => $n->nodeinfo->node_id, "{#NODENAME}" => $n->nodeinfo->hostname);
			}
		}
		$discoveryJson = array("data"=>$discoveryRawJson);
		echo json_encode($discoveryJson, JSON_UNESCAPED_UNICODE);
		exit();
	break;

	case "autocreate":
		require_once("zabbixapi.php");
		if(!($data=getData(NODES_JSON))) exit();	//	Exit on error 
		$nodeIds = $nodes = array();
		foreach($data->nodes as $n) {
			$nodeIds[] = $n->nodeinfo->node_id;
		}
		
		//      check existing hosts
		global $ZabbixApi;

		$addons = array();
		$methodGet = "host.get";
		$paramsGet = array(
			"output"	=> array("hostid", "host", "flags", "name"),
			"filter"	=> array("host" => $nodeIds),
		);
		$template_id = array_shift($args);
		$group_id = array_shift($args);
		//      alle bekannten Hosts holen
		$resultGet = $ZabbixApi->get_zabbix_req($methodGet, $paramsGet, $addons);

		//      Resultat umformatieren
		$tmpResultGet = array();
		for($i=0; $i<count($resultGet->result); $i++) {
			$tmpResultGet[$resultGet->result[$i]->host] = $resultGet->result[$i];
		}
		$resultGet->result = $tmpResultGet;

		foreach($data as $n) {
			$params = array();
			if(@isset($n->nodeinfo->node_id) && !empty($n->nodeinfo->node_id) && !empty($n->nodeinfo->hostname)) {
				//      Knoten existiert
				$newName = $n->nodeinfo->hostname." (".$n->nodeinfo->node_id.")";
				if(isset($resultGet->result[$n->nodeinfo->node_id])) {
					$oldName = $resultGet->result[$n->nodeinfo->node_id]->name;
					if($oldName==$newName) {
						//      keine Änderung im Namen
						continue;
					} else {
						//      Host aktualisieren
						$method = "host.update";
						$params["hostid"] = $resultGet->result[$n->nodeinfo->node_id]->hostid;
					}
				} else {
					//      host anlegen
					$method = "host.create";
					$params = array(
						"host"	  => NULL,
						"name"	  => NULL,
						"interfaces"    => array(array("type"=>1, "main"=>1, "useip"=>1, "ip"=>"127.0.0.1", "dns"=>"", "port"=>10050)),
						"templates"     => array(array("templateid"=>$template_id)),
						"groups"	=> array(array("groupid"=>$group_id)),
						"inventory_mode"=> 1
					);
					$params["interfaces"][0]["ip"]=$n->nodeinfo->network->addresses[0];
					$params["host"] = $n->nodeinfo->node_id;
				}
				$params["name"] = $newName;
				$apiRequest = $ZabbixApi->get_zabbix_req($method, $params, $addons);
			}
		}
	break;
	case "getAll":
	case "getAllNodesValues":
		if(!($data=getData(NODES_JSON))) exit();	//	Exit on error 
		$nodeIds = array();
		$tmpData = "";
		foreach($data->nodes as $n) {
			if(@!isset($n->nodeinfo->node_id) || empty($n->nodeinfo->node_id) || empty($n->nodeinfo->hostname)) {
				continue;
			}

			$foo = array();
			flatObj($foo, $n);
			foreach($foo as $attr => $v) {
				$v = cleanupEmptyValues($attr, $v);
				$tmpData .= prepareZabbixInput($n->nodeinfo->node_id, "ff.nodes.".$attr, $v);
			}
		}

		sendToZabbix($tmpData);
	break;
	case "countNodes":
		if(!($data=getData(NODES_JSON))) exit();	//	Exit on error 
		echo count($data->nodes);
	break;
	case "getClientsTotal":
	case "getClientsTotal":
	case "getClientsWifi":
	case "getClientsWifi24":
	case "getClientsWifi5":
	case "getClientsTotalCategorizedBevoelkerung":
	case "getClientsTotalCategorizedJugend":
	case "getClientsTotalCategorizedKultur":
	case "getClientsTotalCategorizedSozial":
	case "getClientsTotalCategorizedSport":
	case "getClientsTotalCategorizedTourismus":
		if(!($data=getData(NODES_JSON))) exit();	//	Exit on error 
		switch($task) {
			case "getClientsTotal":
				$key 		= NULL; #"total";
				$filter 	= NULL;
			break;
			case "getClientsWifi":
				$key 		= "wifi";
				$filter 	= NULL;
			break;
			case "getClientsWifi24":
				$key 		= "wifi24";
				$filter 	= NULL;
			break;
			case "getClientsWifi5":
				$key 		= "wifi5";
				$filter 	= NULL;
			break;
			case "getClientsTotalCategorizedBevoelkerung":
				$key 		= NULL; #"total";
				$filter 	= "bevoelkerung";
			break;
			case "getClientsTotalCategorizedJugend":
				$key 		= NULL; #"total";
				$filter 	= "jugend";
			break;
			case "getClientsTotalCategorizedKultur":
				$key 		= NULL; #"total";
				$filter 	= "kultur";
			break;
			case "getClientsTotalCategorizedSozial":
				$key 		= NULL; #"total";
				$filter 	= "sozial";
			break;
			case "getClientsTotalCategorizedSport":
				$key 		= NULL; #"total";
				$filter 	= "sport";
			break;
			case "getClientsTotalCategorizedTourismus":
				$key 		= NULL; #"total";
				$filter 	= "tourismus";
			break;
		}
		echo sumFieldsFiltered($data->nodes, array("statistics", "clients", $key), $filter);
	break;
}

function sumFieldsFiltered($data, $path, $filter=NULL) {
	$total = 0;
	foreach($data as $n) {
		if(@!isset($n->nodeinfo->node_id) || empty($n->nodeinfo->node_id) || empty($n->nodeinfo->hostname)) {
			continue;
		}
		if(is_null($filter) || getCustomCategoryFromNode($n->nodeinfo->node_id)==$filter) {
			$total += (int) getPropertyValueByPath($n, $path);
		} 
	}
	return $total;
}


function getPropertyValueByPath($obj, $path) {
	$r = 0;
	foreach($path as $p) {
		if (isset($obj->{$p})) {
			$r = $obj->{$p};
			$obj = $r;
		} elseif (is_null($p)) {
			break;
		} else {
			$r = 0;
			break;
		}
	}
	return $r;
}

function flatObj(&$orig, $a , $keys=array()) {
	foreach($a as $k => $v) {
		if(is_object($v)) {
			array_push($keys, $k);
			flatObj($orig, $v, $keys);
			array_pop($keys);
		} else if (is_array($v)) {
			foreach($v as $ak => $av) {
				if(is_object($av) || is_array($av)) {
					array_push($keys, $ak);
					flatObj($orig, $av, $keys);
					array_pop($keys);
				} else {
					$k2 = $keys;
					$k2[] = $ak;
					$orig[implode(".", $k2)] = $av;
				}
			}
	      } else {
			$k2 = $keys;
			$k2[] = $k;
			$orig[implode(".", $k2)] = $v;
		}
	}
}

function cleanupEmptyValues($attr, $v) {
	switch($attr) {
		case "firstseen":
		case "lastseen":
			$v = strtotime($v);
		break;
		case "nodeinfo.software.fastd.enabled":
		case "nodeinfo.software.autoupdater.enabled":
		case "nodeinfo.hardware.nproc":
	#       case "flags.gateway":
	#       case "flags.online":
			$v = ($v) ? 1 : 0;
		break;
	#       case "statistics.gateway":
	#       case "statistics.mesh_vpn.groups.backbone.peers.vpn1":
	#       case "statistics.mesh_vpn.groups.backbone.peers.vpn2":
	#       case "statistics.mesh_vpn.groups.backbone.peers.vpn3":
	#       case "statistics.mesh_vpn.groups.backbone.peers.gw1":
	#       case "statistics.mesh_vpn.groups.backbone.peers.gw2":
	#       case "statistics.mesh_vpn.groups.backbone.peers.gw3":
	#       case "statistics.mesh_vpn.groups.backbone.peers":
	#	       if(empty($v)) $v="none";
	#       break;
		default:
			if(is_null($v) || (empty($v) && ($v!=0))) {$v = -1;}
		break;
	}
	return $v;
}

function prepareZabbixInput($host, $key, $value, $timestamp=FALSE) {
	if(!empty($host) && !empty($key) && !empty($value)) {
		return $host." ".$key." ".$value."\n";
	} else {
		return NULL;
	}
}

function sendToZabbix($data) {
	global $debug;
	if($debug) {
		print_r($data);
		return TRUE;
	}
	$tmpfname = tempnam("/tmp", "ff-nodelist");
	$handle = fopen($tmpfname, "w");
	if(is_array($data)) {
		$data = implode("\n", $data);
	}
	fwrite($handle, $data);
	$cmd = "zabbix_sender --zabbix-server localhost --input-file ".$tmpfname;

	exec($cmd);
	copy ($tmpfname, "/tmp/foo");

	fclose($handle);
	unlink($tmpfname);
}

/**
*	Caching einbauen!
*/
function getData($type) {
	$data = array();
	switch($type) {
		case NODES_JSON:
			$data = json_decode(file_get_contents(NODES_JSON));
			if(!isset($data->nodes)) {
				$data->nodes = array();
			} elseif (is_object($data->nodes)) {
				if(count((array) $data->nodes)==0) {
					$data->nodes = array();
				} else {
					$data->nodes = (array) $data->nodes;
				}
			}
		break;
		case NODELIST_JSON:
		break;
		case GRAPH_JSON:
		break;
		case MESHVIEWER_JSON:
		break;
		default:
		return FALSE;
	}
	return $data;
}

function getCustomCategoryFromNode($nodeId) {
	$nodes = array(
"000000000000" => "bevoelkerung",
"111111111111" => "jugend",
"222222222222" => "kultur",
"333333333333" => "sozial",
"444444444444" => "sport",
"555555555555" => "tourismus",
	);
	return isset($nodes[$nodeId]) ? $nodes[$nodeId] : "bevoelkerung";
}

?>
