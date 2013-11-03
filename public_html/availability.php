<?php

require_once('common.php');

function usage() {
    header('Content-type: text/plain; charset=utf-8');
	print "Bruk: \n\n    " . $_SERVER['PHP_SELF'] ."?id=<id>\n\nder <id> er et objektid eller dokid. Eksempel:\n\n    " . $_SERVER['PHP_SELF'] ."?id=11447981x\n\nFor å få resultatene i JSONP istedetfor JSON; bruk callback. Eksempel:\n\n    " . $_SERVER['PHP_SELF'] ."?id=11447981x&callback=minfunksjon\n";
	exit();
}

if (!isset($_GET['id'])) usage();
$id = preg_replace('/[^0-9x]/i', '', $_GET['id']);
if (empty($id)) usage();

$url = 'http://services.bibsys.no/services/json/availabilityService.jsp?id=' . $id . '&ts=' . time();
//$url = 'http://alfa-a.bibsys.no/services/json/availabilityService.jsp?id=' . $id;
$data = file_get_contents2($url);

header('Access-Control-Allow-Origin: *');
header('Content-type: application/json; charset=utf-8');
echo $data;
exit();