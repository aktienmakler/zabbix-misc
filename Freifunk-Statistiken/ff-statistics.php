#!/usr/bin/php
<?php
/**
*       @author Matthias Drobny, Freifunk Gera-Greiz
*       @date 2018-08-14
*       @version 1.0
*       Script gets started through Zabbix item (Template) and send statistical data about the network back into Zabbix server.
*       Login and specific data are not transmitted and instead configured here.*/
date_default_timezone_set("Europe/Berlin");

//      Configuration
define("URL_GRAPH_JSON", "https://map.ffggrz.de/data/graph.json");
define("URL_NODE_JSON", "https://map.ffggrz.de/data/nodes.json");
define("ZABBIX_SENDER", "/usr/bin/zabbix_sender");
define("ZABBIX_HOST", "localhost");
define("TARGET_HOSTNAME", "www.freifunk-gera-greiz.de");
//      End of configuration


//      Get graph data
$c = json_decode(file_get_contents(URL_GRAPH_JSON));

//      Get node data
$c2 = json_decode(file_get_contents(URL_NODE_JSON));

//      Prepare node names
foreach($c2->nodes as $n) {
    $nodes[$n->nodeinfo->node_id] = $n->nodeinfo->hostname;
}

//      Create adjacency matrix
$adjMatrix = array();
foreach($c->batadv->links as $l) {
    if($l->type=="fastd" || $l->type=="tunnel" || $l->type=="l2tp") continue;
    $s = $c->batadv->nodes[$l->source]->node_id;
    $s = $l->source;
    $t = $c->batadv->nodes[$l->target]->node_id;
    $t = $l->target;
    $adjMatrix[$s][$t] = $adjMatrix[$t][$s] = 1;
}

//      Use Warshall algorith to create transitive hull
$cntNodes = count($c->batadv->nodes);
for($k=0; $k<$cntNodes; $k++) {
    for($i=0; $i<$cntNodes; $i++) {
        if(isset($adjMatrix[$i][$k]) && $adjMatrix[$i][$k]==1) {
            for($j=0; $j<$cntNodes; $j++) {
                if(isset($adjMatrix[$k][$j]) && $adjMatrix[$k][$j]==1) $adjMatrix[$i][$j]=1;
            }
        }
    }
}

//      Prepare result
$result = array("size"=>0, "nodes"=>array());
foreach($adjMatrix as $k => $v) {
    if($result["size"]<count($v)) {
    $keys = array_keys($v);
    sort($keys);
        $result["size"]         = count($v);
        $result["nodes"]        = $keys;
        $result["names"]        = array();
        foreach($keys as $n) {
            $result["names"][] = $nodes[$c->batadv->nodes[$n]->node_id];
        }
    }
}

unset($result["nodes"]);

//      Send result
$cmd = ZABBIX_SENDER." --zabbix-server ".ZABBIX_HOST." --host ".TARGET_HOSTNAME." --key ff.network.statistics.maxSubnet.size --value ".$result["size"];
$cmd = escapeshellcmd($cmd);
exec($cmd);

$cmd = ZABBIX_SENDER." --zabbix-server ".ZABBIX_HOST." --host ".TARGET_HOSTNAME." --key ff.network.statistics.maxSubnet.nodes --value \"".implode(",", $result["names"])."\"";
$cmd = escapeshellcmd($cmd);
exec($cmd);

//      Exit
echo 1;
?>
