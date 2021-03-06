<?php session_start();

include '../logger.php';
include '../connect.php';

include '../src/utils.php';

error_reporting(0);
use ReVival\utils;

// required headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

$raw_input = file_get_contents('php://input');
$json = json_decode($raw_input);
// request params
$start = (int)$json -> {'start'};
$end = (int)$json -> {'end'};
$norm = (int)$json -> {'norm'};
$ground_thruth = (bool)$json -> {'ground'};
$threshold = (float)$json -> {'threshold'};
$series_ids = $json -> {'series'};
$visible = $json -> {'visible'};
$table = Utils::getTableName($start, $end);
$reference = $json->{'reference'};

// has the cached series with drop values from /api/drop.php
$explore_object = clone $_SESSION['drop'];

//set up references

$visible = array();
$keeptrack = array();

foreach ($reference as $sid)
{
    $visObj = new stdClass();
    $visObj->{'id'} = $sid;
    $visObj->{'name'} = "ANY";
    $visObj->{'visible'} = true;
    $visible[] = $visObj;
    $keeptrack[] = $sid;
}

foreach ($explore_object->{"series"} as $rseries) {
    $sid = $rseries["id"];

    if (isset($rseries["ground"]) || count($reference) == 0)
    {
        if (!in_array($sid, $keeptrack))
        {
            $visObj = new stdClass();
            $visObj->{'id'} = $sid;
            $visObj->{'name'} = $rseries["name"];
            $visObj->{'visible'} = true;
            $visible[] = $visObj;
            $keeptrack[] = $sid;
        }
    }
}

//end: set up references

include '../algebra.php';

$use_udf = !((bool)$json -> {'udf'});
if ($use_udf)
{
    // no return value, does the same changed in-place
    recover_udf($conn, $explore_object, $threshold, $norm, $table, $visible, $start, $end);
}
else
{
    $recovered = recover_all($conn, $explore_object, $threshold, $norm, $table, $visible);

    foreach($explore_object->{'series'} as $key => &$serie) {
        $recov_points = $recovered -> {'series'}[$key]['recovered'];
        if ($recov_points !== NULL && $serie['ground'] !== NULL) {
            $serie['recovered'] = $recov_points;
        }
    }

    $explore_object -> {'runtime'} = $recovered -> {'runtime'};

    if (isset($recovered -> {'rmse'})) $explore_object -> {'rmse'} = $recovered -> {'rmse'};
    if (isset($recovered -> {'rmse_norm'})) $explore_object -> {'rmse_norm'} = $recovered -> {'rmse_norm'};
    if (isset($recovered -> {'mae'})) $explore_object -> {'mae'} = $recovered -> {'mae'};
    if (isset($recovered -> {'mae_norm'})) $explore_object -> {'mae_norm'} = $recovered -> {'mae_norm'};
}

http_response_code(200);
echo json_encode($explore_object);
monetdb_disconnect();

?>