<?php

require_once('common.php');

function usage() {
    header('Content-type: text/plain; charset=utf-8');
    $me = $_SERVER['HTTPS'] ? 'https://' : 'http://' . $_SERVER['SERVER_NAME'] . $_SERVER['PHP_SELF'];
	print "Bruk: \n\n    " . $me ."?id=<id>\n\nder <id> er et knyttid eller dokid. Eksempel:\n\n    " . $me ."?id=036051NA0\n\nFor å få resultatene i JSONP istedetfor JSON; bruk callback. Eksempel:\n\n    " . $me ."?id=036051NA0&callback=minfunksjon\n";
	exit();
}

if (!isset($_GET['id'])) usage();
$knyttdokid = $_GET['id'];
if (empty($knyttdokid)) usage();

return_json(lookup_id($knyttdokid));
