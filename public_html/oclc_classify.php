<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once('../vendor/autoload.php');

use Danmichaelo\QuiteSimpleXMLElement\QuiteSimpleXMLElement;

# Documentation: http://classify.oclc.org/classify2/api_docs/classify.html

$base_url = 'http://classify.oclc.org/classify2/Classify?';

function usage() {
    header('Content-type: text/plain;charset=UTF-8');
    print "Bruk: \n\n"
        . "    " . $_SERVER['PHP_SELF'] ."?isbn=<isbn>\n\n"
        . " der <isbn> er et gyldig objektid eller dokid."
        . "Eksempel:\n\n    " . $_SERVER['PHP_SELF'] ."?isbn=0691140340\n\n"
        . "For å få resultatene i JSONP istedetfor JSON; bruk callback. Eksempel:\n\n    " . $_SERVER['PHP_SELF'] ."?isbn=0691140340&callback=minfunksjon\n";
    exit();
}

function file_get_contents2($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERAGENT, 'UBO Scriptotek Dalek/0.1 (+http://labs.biblionaut.net/)');
    curl_setopt($ch, CURLOPT_HEADER, 0); // no headers in the output
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // return instead of output
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}


function make_query($isbn) {
    return http_build_query(array(
        'stdnbr' => $isbn,
        'summary' => false,
        'maxRecs' => 50
        ));
}

function return_json($obj) {
    if (isset($_REQUEST['callback'])) {
        header('Content-type: application/javascript; charset=utf-8');
        echo $_REQUEST['callback'] . '(' . json_encode($obj) . ')';
        exit();
    } else {
    	header('Access-Control-Allow-Origin: *');
        header('Content-type: application/json; charset=utf-8');
        echo json_encode($obj);
        exit();
    }
}

if (!isset($_GET['isbn'])) {
    usage();
}
$isbn = preg_replace('/[^0-9]/', '', $_GET['isbn']);

$url = $base_url . make_query($isbn);

$response = file_get_contents2($url);

$xml = new QuiteSimpleXMLElement($response);
$xml->registerXPathNamespaces(array(
	'c' => 'http://classify.oclc.org'
));



$status = (int) $xml->first('c:response')->el()->attributes()->code;
/*
 Response Codes
	0:	Success. Single-work summary response provided.
	2:	Success. Single-work detail response provided.
	4:	Success. Multi-work response provided.
	100:	No input. The method requires an input argument.
	101:	Invalid input. The standard number argument is invalid.
	102:	Not found. No data found for the input argument.
	200:	Unexpected error.
*/
if ($status === 2) {
	$res = parseSingleWorkDetailedResponse($xml);
	$res['url'] = $url;
	return_json($res);
} else {
	return_json(array(
		'success' => false,
		'status' => $status,
		'url' => $url,
	));
}

function parseSingleWorkDetailedResponse($xml) {

	$output = array(
		'success' => true,
		'work' => array(),
		'ddc' => array(),
		'subjects' => array(),
		'editions' => array()
	);


	$ddc = $xml->xpath('c:recommendations/c:ddc');

	$latest = $xml->first('c:recommendations/c:ddc/c:latestEdition');
	if ($latest) {
		$latest = $latest->el();
		$output['ddc']['latestEdition'] = array(
			'class' => (string) $latest['sfa'],
			'edition' => (int) $latest['sf2'],
			'holdings' => (int) $latest['holdings']
		);
	}

	$latest = $xml->first('c:recommendations/c:ddc/c:mostPopular');
	if ($latest) {
		$latest = $latest->el();
		$output['ddc']['mostPopular'] = array(
			'class' => (string) $latest['sfa'],
			'holdings' => (int) $latest['holdings']
		);
	}

	$latest = $xml->first('c:recommendations/c:ddc/c:mostRecent');
	if ($latest) {
		$latest = $latest->el();
		$output['ddc']['mostRecent'] = array(
			'class' => (string) $latest['sfa'],
			'holdings' => (int) $latest['holdings']
		);
	}

	foreach ($xml->xpath('c:recommendations/c:fast/c:headings/c:heading') as $heading) {
		$output['subjects'][] = trim((string) $heading);
	}

	foreach ($xml->xpath('c:editions/c:edition') as $edition) {
		$el = $edition->el();
		$output['editions'][] = array(
			'oclc' => (string) $el['oclc'],
			'lang' => (string) $el['language'],
		);
	}

	$work = $xml->first('c:work')->el();

	$output['work'] = array(
		'authors' => (string) $work['authors'],
		'title' => (string) $work['title'],
		'format' => (string) $work['format'],
		'holdings' => (int) $work['holdings'],
		'itemtype' => (string) $work['itemtype'],
		'oclc' => (string) $work
	);

	return $output;
}

