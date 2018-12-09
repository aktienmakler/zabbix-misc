#!/usr/bin/php
<?php
/**
*       @author Matthias Drobny, Freifunk Gera-Greiz
*       @date 2018-12-03
*       @version 1.0
*/
date_default_timezone_set("Europe/Berlin");
$debug = TRUE;
require_once("zabbixapi.php");
global $ZabbixApi;
$places = array("STANDORT1","STANDORT2","STANDORT3");
define("FFNODE_TEMPLATE", [ID DES ZABBIX-TEMPLATES);

foreach($places as $p) {
        $massupdateHostids=array();
        $addons = array();
        $methodGet = "hostgroup.get";
        $paramsGet = array(
                "output"        => array("groupid", "name"),
                "search"        => array("name" => "*/".$p),
                "searchWildcardsEnabled"        => true,
                "searchByAny"   => true,
        );
        $resG = $ZabbixApi->get_zabbix_req($methodGet, $paramsGet, $addons);
        if(count($resG->result)==0) continue;
        $groupid = $resG->result[0]->groupid;

        $addons = array();
        $methodGet = "host.get";
        $paramsGet = array(
                "output"        => array("hostid", "name"),
                "search"        => array("name" => "*-".$p."-*"),
                "searchWildcardsEnabled"        => true,
                "searchByAny"   => true,
                "templateids"   => FFNODE_TEMPLATE,
                "selectGroups"  => "extend",
        );
        $resH = $ZabbixApi->get_zabbix_req($methodGet, $paramsGet, $addons);
        foreach($resH->result as $h) {
                $h->placeSet = FALSE;
                foreach($h->groups as $g) {
                        if($g->groupid==$groupid) {
                                $h->placeSet = $g->groupid; 
                                break;
                        }
                }
                if($h->placeSet) {break;}
                $h->groups[] = (object) array("groupid"=>$groupid);

                $paramsGet = array(
                        "hostid"        => $h->hostid,
                        "groups"        => $h->groups,
                );
                $resU = $ZabbixApi->get_zabbix_req("host.update", $paramsGet, array());
        }
}
?>
