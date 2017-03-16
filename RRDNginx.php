<?php

// https://easyengine.io/tutorials/nginx/status-page/

$rrdFile = dirname(__FILE__) . "/test.rrd";
$graphPath = realpath(__DIR__ . '/../httpdocs/img');
$logPath = realpath(__DIR__ . '/../httpdocs');

$nginxStatsUrl = 'http://127.0.0.1/nginx_status';

if (!file_exists($rrdFile)) {
    echo "creating [$rrdFile]\n";

    if (!rrd_create($rrdFile, [
        "-s",60,
        "DS:requests:COUNTER:120:0:100000000",
        "DS:total:ABSOLUTE:120:0:60000",
        "DS:reading:ABSOLUTE:120:0:60000",
        "DS:writing:ABSOLUTE:120:0:60000",
        "DS:waiting:ABSOLUTE:120:0:60000",
        "RRA:AVERAGE:0.5:1:2880",
        "RRA:AVERAGE:0.5:30:672",
        "RRA:AVERAGE:0.5:120:732",
        "RRA:AVERAGE:0.5:720:1460"
    ])){
        echo 'Error creating ['. $rrdFile .']';
        exit(1);
    }
}

$requests = 0;
$total =  0;
$reading = 0;
$writing = 0;
$waiting = 0;


$stat = file_get_contents($nginxStatsUrl);
foreach (explode("\n", $stat) as $row) {

    if (preg_match('/^Active connections:\s+(\d+)/', $row, $matches) === 1) {
        $total = $matches[1];
        continue;
    }

    if (preg_match('/^Reading:\s+(\d+).*Writing:\s+(\d+).*Waiting:\s+(\d+)/', $row, $matches) === 1) {
        $reading = $matches[1];
        $writing = $matches[2];
        $waiting = $matches[3];
        continue;
    }

    if (preg_match('/^\s+(\d+)\s+(\d+)\s+(\d+)/', $row, $matches) === 1) {
        $requests = $matches[3];
        continue;
    }
}

echo "Total: ($total), Requests: ($requests), Reading: ($reading), Writing: ($writing), Waiting ($waiting)\n";

//
// insert values into rrd database
//
if (!rrd_update($rrdFile, [
    "-t",
    "requests:total:reading:writing:waiting",
    "N:$requests:$total:$reading:$writing:$waiting"
])){
    echo 'Error writing ['. $rrdFile .']';
    exit(1);
}

//
// Save graphs
//

function createGraphs($period, $graphPath, $rrdFile) {
    if(!rrd_graph($graphPath . '/requests_' . $period . '.png', [
        "-s","-1$period",
        "-t Requests on nginx ($period)",
        "--lazy",
        "-h", "150", "-w", "700",
        "-l 0",
        "-a", "PNG",
        "-v requests/sec",
        "--units-exponent=0",
        "DEF:requests=$rrdFile:requests:AVERAGE",
        "LINE2:requests#27AE60:Requests",
        "GPRINT:requests:MAX:  Max\\: %5.1lf",
        "GPRINT:requests:AVERAGE: Avg\\: %5.1lf",
        "GPRINT:requests:LAST: Current\\: %5.1lf req/sec",
        "HRULE:0#000000"
    ])) {
        echo 'Error writing requests graph for period '. $period  .' ['. $graphPath .']';
        exit(1);
    }

    if(!rrd_graph($graphPath . '/connections_' . $period . '.png', [
        "-s","-1$period",
        "-t Connections on nginx ($period)",
        "--lazy",
        "-h", "150", "-w", "700",
        "-l 0",
        "-a", "PNG",
        "-v requests/sec",
        "--units-exponent=0",
        "DEF:total=$rrdFile:total:AVERAGE",
        "DEF:reading=$rrdFile:reading:AVERAGE",
        "DEF:writing=$rrdFile:writing:AVERAGE",
        "DEF:waiting=$rrdFile:waiting:AVERAGE",

        "LINE2:total#27AE60:Total",
        "GPRINT:total:LAST:   Current\\: %5.1lf",
        "GPRINT:total:MIN:  Min\\: %5.1lf",
        "GPRINT:total:AVERAGE: Avg\\: %5.1lf",
        "GPRINT:total:MAX:  Max\\: %5.1lf\\n",

        "LINE2:reading#2C3E50:Reading",
        "GPRINT:reading:LAST: Current\\: %5.1lf",
        "GPRINT:reading:MIN:  Min\\: %5.1lf",
        "GPRINT:reading:AVERAGE: Avg\\: %5.1lf",
        "GPRINT:reading:MAX:  Max\\: %5.1lf\\n",

        "LINE2:writing#E84B3A:Writing",
        "GPRINT:writing:LAST: Current\\: %5.1lf",
        "GPRINT:writing:MIN:  Min\\: %5.1lf",
        "GPRINT:writing:AVERAGE: Avg\\: %5.1lf",
        "GPRINT:writing:MAX:  Max\\: %5.1lf\\n",

        "LINE2:waiting#F8C82D:Waiting",
        "GPRINT:waiting:LAST: Current\\: %5.1lf",
        "GPRINT:waiting:MIN:  Min\\: %5.1lf",
        "GPRINT:waiting:AVERAGE: Avg\\: %5.1lf",
        "GPRINT:waiting:MAX:  Max\\: %5.1lf\\n",

        "HRULE:0#000000"

    ])) {
        echo 'Error writing connections graph for period '. $period  .' ['. $graphPath .']';
        exit(1);
    }
}

createGraphs("hour", $graphPath, $rrdFile);
createGraphs("day", $graphPath, $rrdFile);
createGraphs("week", $graphPath, $rrdFile);
createGraphs("month", $graphPath, $rrdFile);
createGraphs("year", $graphPath, $rrdFile);

file_put_contents($logPath . '/timestamp.js', 'function getTimestamp(){ return '.json_encode(['date' => date('c')]).'; }');
