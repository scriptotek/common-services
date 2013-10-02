<?php

ini_set('display_errors', 'On');
error_reporting(E_ALL);

require_once('../vendor/amstaff/simplehtmldom/lib/SimpleHtmlDom/SimpleHtmlDom.php'); # SimpleHtmlDom is not PSR-0

header('Access-Control-Allow-Origin: *');

function usage() {
	print "Bruk: \n\n    " . $_SERVER['PHP_SELF'] ."?number=<number>\n\nder <number> er et 8-sifret norsk telefonnummer. Eksempel:\n\n    " . $_SERVER['PHP_SELF'] ."?number=99887766";
	exit();
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


if (!isset($_GET['number'])) usage();

function curl_get($url) {
	$ch = curl_init($url);
	curl_setopt_array($ch, array(
		CURLOPT_HTTPHEADER => array(
		  //'Content-Type: application/json',
	  	  //'Accept: application/json',
		),
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_VERBOSE => true,
		CURLINFO_HEADER_OUT => true,
		CURLOPT_FOLLOWLOCATION => true,
	 	CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:19.0) Gecko/20100101 Firefox/19.0',
	));
	return curl_exec($ch);
}

$personname = '';
$number = intval($_GET['number']);
$source = '';
$urls = array(
	'http://1890.no/?query=' . $number,
	'http://1881.no/?query=' . $number
);
foreach ($urls as $url) {
	$html = SimpleHtmlDom\str_get_html(curl_get($url));
	$el = $html->find('span.privat', 0);
	if (is_object($el)) {
		$personname = trim($el->plaintext);
		$source = '1890';
		break;
	}
	$el = $html->find('div.listing', 0);
	if (is_object($el)) {
		$personname = trim($el->find('a', 0)->plaintext);
		$source = '1881';
		break;
	}
}


if (empty($personname)) {
    $res = array('number' => $number, 'personname' => 'unknown', 'source' => $source);
} else {
    $res = array('number' => $number, 'personname' => $personname, 'source' => $source);
}

return_json($res);
