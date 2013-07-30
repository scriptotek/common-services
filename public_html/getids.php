<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

function usage() {
    header('Content-type: text/plain; charset=utf-8');
    $me = $_SERVER['HTTPS'] ? 'https://' : 'http://' . $_SERVER['SERVER_NAME'] . $_SERVER['PHP_SELF'];
	print "Bruk: \n\n    " . $me ."?id=<id>\n\nder <id> er et knyttid eller dokid. Eksempel:\n\n    " . $me ."?id=036051NA0\n\nFor å få resultatene i JSONP istedetfor JSON; bruk callback. Eksempel:\n\n    " . $me ."?id=036051NA0&callback=minfunksjon\n";
	exit();	
}

function file_get_contents2($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERAGENT, 'UBO Scriptotek Dalek');
    curl_setopt($ch, CURLOPT_HEADER, 0); // no headers in the output
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // return instead of output
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

function return_json($obj) {
    if (isset($_REQUEST['callback'])) {
        header('Content-type: application/javascript; charset=utf-8');
        echo $_REQUEST['callback'] . '(' . json_encode($obj) . ')';
        exit();
    } else {
        header('Content-type: application/json; charset=utf-8');
        echo json_encode($obj);
        exit();
    }
}

if (!isset($_GET['id'])) usage();
$knyttdokid = $_GET['id'];
if (empty($knyttdokid)) usage();

$url = 'http://adminwebservices.bibsys.no/objectIdService/getObjectId?id=' . $knyttdokid;
$url = 'http://adminwebservices.bibsys.no/objectIdService/getIds?id=' . $knyttdokid;

$ids = trim(file_get_contents2($url));

$json = array();
$keys = array(
    'objektId' => 'objektid',
    'dokumentId' => 'dokid',
    'hefteId' => 'heftid',
);
foreach (explode("\n", $ids) as $line) {
    list($key, $val) = explode(':', $line);
    $json[$keys[$key]] = trim($val);
}

return_json($json);

?>
