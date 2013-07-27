<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once('../marcparser.php');
require_once('../customxmlelement/customxmlelement.php');

function usage() {
    header('Content-type: text/plain;charset=UTF-8');
    print "Bruk: \n\n"
        . "    " . $_SERVER['PHP_SELF'] ."?objektid=<number>\n\n"
        . "    " . $_SERVER['PHP_SELF'] ."?dokid=<dokid>\n\n"
        . "    " . $_SERVER['PHP_SELF'] ."?isbn=<isbn>&repo=<repo>\n\n"
        . " der <objektid> eller <dokid> er et gyldig objektid eller dokid. <repo> kan ha en av verdiene 'bibsys', 'loc', 'libris' eller 'bl'."
        . "Eksempel:\n\n    " . $_SERVER['PHP_SELF'] ."?objektid=052073475\n\n"
        . "For å få resultatene i JSONP istedetfor JSON; bruk callback. Eksempel:\n\n    " . $_SERVER['PHP_SELF'] ."?objektid=052073475&callback=minfunksjon\n";
    exit(); 
}

function file_get_contents2($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERAGENT, 'UBO Scriptotek Dalek/0.1 (+http://biblionaut.net/bibsys/)');
    curl_setopt($ch, CURLOPT_HEADER, 0); // no headers in the output
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // return instead of output
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
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

function return_json($obj) {
    if (isset($_REQUEST['callback'])) {
        header('Content-type: application/javascript; charset=utf-8');
        echo $_REQUEST['callback'] . '(' . json_encode($obj) . ')';
        exit();
    } else {
        header('Content-type: application/json; charset=utf-8');
        if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
            echo json_encode($obj, JSON_PRETTY_PRINT);
        } else {
            echo json_encode($obj);            
        }
        exit();
    }
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
    'marc' => 'info:lc/xmlns/marcxchange-v1'
);

$repo = $repos['bibsys'];

if ($repo['ident'] == 'bibsys' && isset($_GET['objektid'])) {
    $qs = 'bs.objektid="' . addslashes($_GET['objektid']) . '"';
} else if ($repo['ident'] == 'bibsys' && isset($_GET['dokid'])) {
    $qs = 'bs.dokid="' . addslashes($_GET['dokid']) . '"';
} else if ($repo['ident'] == 'bibsys' && isset($_GET['isbn'])) {
    $qs = 'bs.isbn="' . addslashes($_GET['isbn']) . '"';
} else {
    usage();
}

$output = array();

$qs = make_query($qs, 1, 1, $repo['schema']);
$baseurl = $repo['url'];
$source = file_get_contents2("$baseurl$qs");

$output['sru_url'] = "$baseurl$qs";

$xml = new CustomXMLElement($source);
$xml->registerXPathNamespaces($ns);
$output['numberOfRecords'] = (int)(string)$xml->first('/srw:searchRetrieveResponse/srw:numberOfRecords');

$diag = $xml->first('//srw:diagnostics');
if ($diag !== false) {
    $output['error'] = strval($diag->el()->diagnostic->message);
    return_json($output);
}


$record = $xml->first("/srw:searchRetrieveResponse/srw:records/srw:record");

$rec_srw = $record->children($ns['srw']);
$output['recordid'] = (string)$rec_srw->recordIdentifier;
$output['keywords'] = array();
$output['dewey'] = '';

$v = $record->first('srw:recordData/marc:record/marc:datafield[@tag="082"][@ind1="0"]/marc:subfield[@code="a"]');
if ($v !== false) {
    $output['dewey'] = str_replace('/', '', (string)$v);
}

$marc_rec = $record->first('srw:recordData/metadata/marc:collection/marc:record[@type="Bibliographic"]');

$holdings = array();
foreach ($record->xpath('srw:recordData/metadata/marc:collection/marc:record[@type="Holdings"]') as $node) {
    $o = array();
    $o['dokid'] = $node->text('marc:controlfield[@tag="001"]');
    $o['a'] = $node->text('marc:datafield[@tag="852"]/marc:subfield[@code="a"]');
    $o['b'] = $node->text('marc:datafield[@tag="852"]/marc:subfield[@code="b"]');
    $o['c'] = $node->text('marc:datafield[@tag="852"]/marc:subfield[@code="c"]');
    $holdings[] = $o;
}

$output['holdings'] = $holdings;

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
