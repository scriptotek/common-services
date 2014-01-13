<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once('../vendor/autoload.php');
require_once('sru_include.php');
require_once('common.php');
use Danmichaelo\QuiteSimpleXMLElement\QuiteSimpleXMLElement;

function usage($repos) {
    header('Content-type: text/plain;charset=UTF-8');
    print "Bruk: \n\n"
        . "    " . $_SERVER['PHP_SELF'] ."?id=<number>\n\n"
        . "    " . $_SERVER['PHP_SELF'] ."?objektid=<number>\n\n"
        . "    " . $_SERVER['PHP_SELF'] ."?dokid=<dokid>\n\n"
        . "    " . $_SERVER['PHP_SELF'] ."?isbn=<isbn>&repo=<repo>\n\n"
        . " der <objektid> eller <dokid> er et gyldig objektid eller dokid. <id> kan være et knyttid, dokid eller objektid. <repo> kan ha en av verdiene 'bibsys', 'loc', 'libris' eller 'bl'."
        . "Eksempel:\n\n    " . $_SERVER['PHP_SELF'] ."?objektid=052073475\n\n"
        . "For å få resultatene i JSONP istedetfor JSON; bruk callback. Eksempel:\n\n    " . $_SERVER['PHP_SELF'] ."?objektid=052073475&callback=minfunksjon\n"
        . "\nTilgjengelige repoer:\n";
    foreach ($repos as $key => $repo) {
        print "- $key : ".$repo['url']." (" . $repo['proto'] . ")\n";
    }
    print "\n";
    print extension_loaded('yaz')
        ? 'YAZ er tilgjengelig på denne serveren'
        : 'Merk: z39.50-repoer kan ikke nås fra denne serveren fordi YAZ ikke er støttet';
    exit();
}

if (!isset($_GET['repo'])) {
    $repo = $repos['bibsys'];
} else if (!isset($repos[$_GET['repo']])) {
    usage($repos);
} else {
    $repo = $repos[$_GET['repo']];
}

if (isset($_GET['id']) && is_isbn($_GET['id'])) {
    $_GET['isbn'] = $_GET['id'];
    unset($_GET['id']);
}


if (isset($_GET['isbn'])) {
    $isbn = array($_GET['isbn']);
    if (strlen($isbn[0]) == 10) {
        $isbn[] = isbn10_to_13($isbn[0]);
    }
}

if (isset($_GET['author']) && isset($_GET['title'])) {
    if (empty($_GET['author'])) {
        return_json(array('error' => 'Ingen forfatter angitt'));
    }
    if (empty($_GET['title'])) {
        return_json(array('error' => 'Ingen tittel angitt'));
    }
}

$output = array();

if ($repo['ident'] == 'bibsys' && isset($_GET['id'])) {
    $ids = lookup_id($_GET['id']);
    $output['ids'] = $ids;
    $qs = 'bs.objektid="' . addslashes($ids['objektid']) . '"';
} else if ($repo['ident'] == 'bibsys' && isset($_GET['objektid'])) {
    $qs = 'bs.objektid="' . addslashes($_GET['objektid']) . '"';
} else if ($repo['ident'] == 'bibsys' && isset($_GET['dokid'])) {
    $qs = 'bs.dokid="' . addslashes($_GET['dokid']) . '"';
} else if ($repo['ident'] == 'bibsys' && isset($_GET['isbn'])) {
    $qs = implode(' OR ', array_map(function($nr) { return 'bs.isbn="' . $nr . '"'; }, $isbn));
} else if ($repo['ident'] == 'dnb' && isset($_GET['isbn'])) {
    $qs = implode(' OR ', array_map(function($nr) { return 'dnb.isbn="' . $nr . '"'; }, $isbn));

} else if ($repo['proto'] == 'z39.50' && isset($_GET['isbn'])) {
    $qs = 'isbn="' . addslashes($_GET['isbn']) . '"';
} else if ($repo['proto'] == 'z39.50' && isset($_GET['author']) && isset($_GET['title'])) {
    $qs = 'au="' . addslashes($_GET['author']) . '" and ti="' . addslashes($_GET['title']) . '"';

} else if (isset($_GET['isbn'])) {
    $qs = implode(' OR ', array_map(function($nr) { return 'bath.isbn="' . $nr . '"'; }, $isbn));
} else if (isset($_GET['author']) && isset($_GET['title'])) {
    $qs = 'dc.author="' . addslashes($_GET['author']) . '" AND dc.title="' . addslashes($_GET['title']) . '"';
} else {
    usage($repos);
}

$output['permalink'] = $repo['permalink'];

if ($repo['proto'] == 'z39.50') {

    $output = array_merge($output, z3950lookup($repo, $qs, $ns));

} else {

    $output = array_merge($output, srulookup($repo, $qs, $ns));

}
if ($repo['ident'] == 'loc' && isset($output['lccn'])) {
    $output['recordid'] = $output['lccn'];
} else if (isset($output['control_number'])) {
    $output['recordid'] = $output['control_number'];
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
