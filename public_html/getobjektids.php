<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once('../vendor/autoload.php');
require_once('common.php');

use Danmichaelo\QuiteSimpleXMLElement\QuiteSimpleXMLElement;

function usage() {
    header('Content-type: text/plain; charset=utf-8');
    $me = $_SERVER['HTTPS'] ? 'https://' : 'http://' . $_SERVER['SERVER_NAME'] . $_SERVER['PHP_SELF'];
	print 'Bruk: '
        . "\n\n" 
        . '    ' . $me . '?isbn=<isbn>'
        . "\n\n"
        . 'der <isbn> er et isbn-nr. Eksempel:'
        . "\n\n"
        . '    ' . $me . '?isbn=9810223870'
        . "\n\n" 
        . 'For å få resultatene i JSONP istedetfor JSON; bruk callback. Eksempel:'
        . "\n\n"
        . '    ' . $me . "?isbn=9810223870&callback=minfunksjon"
        . "\n";
	exit();	
}

function make_query($cql, $start = 1, $count = 10) {
    return http_build_query(array(
        'version' => '1.2',
        'operation' => 'searchRetrieve',
        'recordSchema' => 'marcxchange', // "bibsysmarc" (ikke offisielt støttet) eller "marcxchange"
        'startRecord' =>  $start,
        'maximumRecords' => $count,
        'query' => $cql
    ));
}

if (!isset($_GET['isbn'])) usage();
$isbn = $_GET['isbn'];
if (empty($isbn)) usage();

$qs = make_query('bs.isbn="' . addslashes($isbn) . '"');

$baseurl = 'http://sru.bibsys.no/search/biblio?';
#$baseurl = 'http://utvikle-a.bibsys.no/search/biblio?';

$uri = $baseurl . $qs;
$source = file_get_contents2($uri);

//print $source;

$ns = array(
    'srw' => 'http://www.loc.gov/zing/srw/',
    'marc' => 'info:lc/xmlns/marcxchange-v1'
);

$json = array('isbn' => $isbn);

//print $source;

$xml = new QuiteSimpleXMLElement($source);
$xml->registerXPathNamespaces(array(
    'srw' => 'http://www.loc.gov/zing/srw/',
    'marc' => 'http://www.loc.gov/MARC21/slim',
    'd' => 'http://www.loc.gov/zing/srw/diagnostic/'
));

//$output['numberOfRecords'] = intval(current($xml->xpath('/srw:searchRetrieveResponse/srw:numberOfRecords')));

$diag = $xml->xpath('//srw:diagnostics');

if (count($diag) != 0) {
    return_json(array('error' => strval($diag[0]->diagnostic->message)));
}
$json['source'] = $uri;
$json['objects'] = array();
foreach ($xml->xpath("/srw:searchRetrieveResponse/srw:records/srw:record") as $record) {
    $obj = array();

    // Id (objektid)
    $obj['id'] = $record->text('srw:recordIdentifier');

    // Isbn
    $obj['isbn'] = $record->text('srw:recordData/marc:record/marc:datafield[@tag="020"]/marc:subfield[@code="a"]');

    // Is object an electronic resource or not?
    preg_match('/elektronisk ressurs/', 
               $record->text('srw:recordData/marc:record/marc:datafield[@tag="245"]/marc:subfield[@code="h"]'), 
               $matches);
    $obj['electronic'] = $matches ? true : false; 

    $json['objects'][] = $obj;
}
return_json($json);

?>
