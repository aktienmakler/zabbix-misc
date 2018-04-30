#!/usr/bin/php
<?php
/**
*	@author Matthias Drobny, Freifunk Gera-Greiz
*	@date 2016-07-08
*	@version 1.0
*/
date_default_timezone_set("Europe/Berlin");

$args = $_SERVER[ 'argv' ];


if(count($args)<=2) {exit("too few arguments");}
array_shift($args);
$rawDataSrc = array_shift($args);
$task = array_shift($args);	//	discovery, getValue

$debug = array_shift($args);
if((!$debug) || empty($debug)) {$debug = FALSE;} else {$debug = TRUE;}

$c = file_get_contents($rawDataSrc);
$data = json_decode($c);

if(!isset($data->nodes)) {
	$data->nodes = array();
} elseif (is_object($data->nodes)) {
	if(count((array) $data->nodes)==0) {
		$data->nodes = array();
	} else {
		$data->nodes = (array) $data->nodes;
	}
}

switch($task) {
	default:
	case "discovery":
		$discoveryRawJson = array();
		$nodeIds = array_keys((array) $data);
		foreach($nodeIds as $k) {
			$n = $data->$k;
			if(@isset($n->nodeinfo->node_id) && !empty($n->nodeinfo->node_id) && !empty($n->nodeinfo->hostname)) {
				$discoveryRawJson[] = array("{#NODEID}" => $n->nodeinfo->node_id, "{#NODENAME}" => $n->nodeinfo->hostname);
			}
		}
		$discoveryJson = array("data"=>$discoveryRawJson);
		echo json_encode($discoveryJson, JSON_UNESCAPED_UNICODE);
		exit();
	break;
	case "getAll":
	case "getAllNodesValues":
		$tmpData = "";
		$nodeIds = array_keys((array) $data);
		foreach($nodeIds as $k) {
			$n = $data->$k;
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
		echo count(array_keys((array) $data));
	break;
	case "getClientsTotal":
		echo sumFields($data, array("statistics", "clients", "total"));
	break;
	case "getClientsWifi":
		echo sumFields($data, array("statistics", "clients", "wifi"));
	break;
	case "getClientsWifi24":
		echo sumFields($data, array("statistics", "clients", "wifi24"));
	break;
	case "getClientsWifi5":
		echo sumFields($data, array("statistics", "clients", "wifi5"));
	break;
}

function sumFields($data, $path) {
	$total = 0;
	$nodeIds = array_keys((array) $data);
	foreach($nodeIds as $k) {
		$n = $data->$k;
		if(@!isset($n->nodeinfo->node_id) || empty($n->nodeinfo->node_id) || empty($n->nodeinfo->hostname)) {
			continue;
		}
		$total += (int) getPropertyValueByPath($n, $path);
	}
	return $total;
}

function getPropertyValueByPath($obj, $path) {
	$r = 0;
	foreach($path as $p) {
		if (isset($obj->{$p})) {
			$r = $obj->{$p};
			$obj = $r;
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
	#	case "flags.gateway":
	#	case "flags.online":
			$v = ($v) ? 1 : 0;
		break;
	#	case "statistics.gateway":
	#	case "statistics.mesh_vpn.groups.backbone.peers.vpn1":
	#	case "statistics.mesh_vpn.groups.backbone.peers.vpn2":
	#	case "statistics.mesh_vpn.groups.backbone.peers.vpn3":
	#	case "statistics.mesh_vpn.groups.backbone.peers.gw1":
	#	case "statistics.mesh_vpn.groups.backbone.peers.gw2":
	#	case "statistics.mesh_vpn.groups.backbone.peers.gw3":
	#	case "statistics.mesh_vpn.groups.backbone.peers":
	#		if(empty($v)) $v="none";
	#	break;
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

?>
