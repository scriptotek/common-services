<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once('../vendor/autoload.php');
require_once('common.php');

use Danmichaelo\QuiteSimpleXMLElement\QuiteSimpleXMLElement;
use Danmichaelo\SimpleMarcParser\HoldingsParser;

function usage() {
    header('Content-type: text/plain;charset=UTF-8');
    print "Bruk: \n\n"
        . "    " . $_SERVER['PHP_SELF'] ."?id=<number>\n\n"
        . " der <id> er et gyldig objektid, dokid eller isbn. Alternativt kan du angi ?objektid=, ?dokid= eller ?isbn= for å spesifisere."
        . "Eksempel:\n\n    " . $_SERVER['PHP_SELF'] ."?id=052073475\n\n"
        . "For å få resultatene i JSONP istedetfor JSON; bruk callback. Eksempel:\n\n    " . $_SERVER['PHP_SELF'] ."?objektid=052073475&callback=minfunksjon\n";
    exit(); 
}

function make_query($cql, $start = 1, $count = 1, $schema = 'marcxml') {
    return http_build_query(array(
        'version' => '1.1',
        'operation' => 'searchRetrieve',
        'recordSchema' => $schema, // "bibsysmarc" (ikke offisielt støttet) eller "marcxchange"
        //'startRecord' =>  $start,
        'maximumRecords' => $count,
        'query' => $cql
        ));
}

$repos = array(
    'bibsys' => array(
        'proto' => 'sru',
        'ident' => 'bibsys',
        'url' => 'http://sru.bibsys.no/search/biblioholdings?',
        'schema' => 'marcxchange',
        'permalink' => 'http://ask.bibsys.no/ask/action/show?kid=biblio&pid='
    )
);

$ns = array(
    'srw' => 'http://www.loc.gov/zing/srw/',
    'marc' => 'info:lc/xmlns/marcxchange-v1',
    'd' => 'http://www.loc.gov/zing/srw/diagnostic/'
);

$repo = $repos['bibsys'];

if (isset($_GET['id'])) {
    if (is_isbn($_GET['id'])) {
        $qs = 'bs.isbn="' . addslashes($_GET['id']) . '"';
    } else {
        $ids = lookup_id($_GET['id']);
        $qs = 'bs.objektid="' . $ids['objektid'] . '"';
    }
} else if (isset($_GET['objektid'])) {
    $qs = 'bs.objektid="' . addslashes($_GET['objektid']) . '"';
} else if (isset($_GET['dokid'])) {
    $qs = 'bs.dokid="' . addslashes($_GET['dokid']) . '"';
} else if (isset($_GET['isbn'])) {
    $qs = 'bs.isbn="' . addslashes($_GET['isbn']) . '"';
} else {
    usage();
}

$output = array('queried_item' => $ids, 'holdings' => array());

$qs = make_query($qs, 1, 1, $repo['schema']);
$baseurl = $repo['url'];
$source = file_get_contents2("$baseurl$qs");

$output['sru_url'] = "$baseurl$qs";

$xml = new QuiteSimpleXMLElement($source);
$xml->registerXPathNamespaces($ns);
$output['numberOfRecords'] = (int)$xml->text('/srw:searchRetrieveResponse/srw:numberOfRecords');

$holdingsParser = new HoldingsParser;

$diag = $xml->first('/srw:searchRetrieveResponse/srw:diagnostics');
if ($diag !== false) {
    $output['error'] = $diag->text('d:diagnostic/d:message');
    return_json($output);
}

if ($output['numberOfRecords'] > 0) {

    $record = $xml->first("/srw:searchRetrieveResponse/srw:records/srw:record/srw:recordData/metadata/marc:collection");
    $biblio = $record->first('marc:record[@type="Bibliographic"]');

    // Id (objektid)
    $output['recordid'] = $biblio->text('marc:controlfield[@tag="001"]');
    $output['keywords'] = array();
    $output['dewey'] = '';

    $v = $biblio->first('marc:datafield[@tag="082"]/marc:subfield[@code="a"]');
    if ($v !== false) {
        $output['dewey'] = str_replace('/', '', (string)$v);
    }

    $holdings = array();
    foreach ($record->xpath('marc:record[@type="Holdings"]') as $node) {

        $holdings[] = $holdingsParser->parse($node);

    }

    $output['holdings'] = $holdings;
}

return_json($output);

/*

$doc = new DOMDocument();
$doc->loadXML($source);
$xpath = new DOMXpath($doc);


$obj = array('records' => array());
$domelem = $xpath->query("/srw:searchRetrieveResponse/srw:numberOfRecords")->item(0);
$obj['numberOfRecords'] = intval($domelem->nodeValue);

$records = $xpath->query("/srw:searchRetrieveResponse/srw:records/srw:record");
foreach ($records as $record) {
  $work = array();
  $work['docid'] = $record->getElementsByTagNameNS($ns['srw'], 'recordIdentifier')->item(0)->nodeValue;
  array_push($obj['records'], $work);

}
*/

/*
if (!is_null($elements)) {
  foreach ($elements as $element) {
    echo "<br/>[". $element->nodeName. "]";

    $nodes = $element->childNodes;
    foreach ($nodes as $node) {
      echo $node->nodeValue. "\n";
    }
  }
}*/
