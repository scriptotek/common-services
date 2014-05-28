<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once('common.php');

#require_once "easyrdf-0.7.2/lib/EasyRdf.php";
#require_once "html_tag_helpers.php";

require_once('../vendor/autoload.php');
require_once('../vendor/easyrdf/easyrdf/lib/EasyRdf.php');

function usage() {
    header('Content-type: text/plain;charset=UTF-8');
    print "Bruk: \n\n    " . $_SERVER['PHP_SELF'] ."?ddc=<ddc> der <ddc> er et Dewey-nummer. Eksempel:\n\n    " . $_SERVER['PHP_SELF'] ."?ddc=539.7258\n\nFor å få resultatene i JSONP istedetfor JSON; bruk callback. Eksempel:\n\n    " . $_SERVER['PHP_SELF'] ."?ddc=539.7258&callback=minfunksjon\n";
    exit();
}

if (isset($_GET['ddc'])) {
    $ddc = preg_replace("/[^0-9.]/", "", $_GET['ddc']);
} else {
    usage();
}

$graph = EasyRdf_Graph::newAndLoad("http://dewey.info/class/$ddc/about");

$uri_base = "http://dewey.info/class/$ddc/e23/";
$res = $graph->resource($uri_base);
$versions = $res->allResources('dc:hasVersion');

$urls = array();
foreach ($versions as $version) {
    $spl = explode('/', trim($version->getUri(), '/'));
    
    $urls[] = array_pop($spl);
}
sort($urls);
$last_date = array_pop($urls);

$uri = "$uri_base$last_date/";

$res = $graph->resource($uri);
$label = (string) $res->get('skos:prefLabel');
return_json(array('code' => $ddc, 'label' => $label));

