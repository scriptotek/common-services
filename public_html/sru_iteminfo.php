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
        echo json_encode($obj);
        exit();
    }
}

$repos = array(
    'loc' => array(
        'proto' => 'sru',
        'ident' => 'loc',
        'url' => 'http://lx2.loc.gov:210/LCDB?',
        'schema' => 'marcxml',
        'permalink' => 'http://lccn.loc.gov/'
    ),
    'libris' => array(
        'proto' => 'sru',
        'ident' => 'libris',
        'url' => 'http://api.libris.kb.se/sru/libris?',
        'schema' => 'marcxml',
        'permalink' => 'http://libris.kb.se/bib/'
    ),
    'bibsys' => array(
        'proto' => 'sru',
        'ident' => 'bibsys',
        'url' => 'http://sru.bibsys.no/search/biblio?',
        'schema' => 'marcxchange',
        'permalink' => 'http://ask.bibsys.no/ask/action/show?kid=biblio&pid='
    ),
    'bl' => array(
        'proto' => 'z39.50',
        'ident' => 'bl',
        'url' => 'z3950cat.bl.uk:9909/ZBLACU', 
        'connection_options' => array(
            'user' => 'UNOSSL2405',
            'password' => 'M1TZaKK!',
            'charset' => 'UTF-8'
        ),
        'permalink' => 'http://primocat.bl.uk/F?func=direct&local_base=PRIMO&format=001&con_lng=prm&doc_number='
    ),
    'libis' => array(
        'proto' => 'z39.50',
        'ident' => 'libis',
        'url' => 'opac.libis.be:9991/opac01',
        'connection_options' => array(),
        'permalink' => ''
    ),
    'kb' => array(
        'proto' => 'z39.50',
        'ident' => 'kb',
        'url' => 'z3950.kb.dk:2100/kgl01',
        'connection_options' => array(),
        'permalink' => ''
    ),
    'kth' => array(
        'proto' => 'z39.50',
        'ident' => 'kth',
        'url' => 'innopac.lib.kth.se:210/innopac',
        'connection_options' => array(),
        'permalink' => '' /* Unlike the case with the British Library Primo installation, 
                             the KTH Primo installation links to single records using an 
                             identifier that is not present in the MARC record – or on the 
                             web – so how do we find it!?!? 
                             Example URL: http://innopac.lib.kth.se:2082/record=b1765313 */
    )
);

$ns = array(
    'srw' => 'http://www.loc.gov/zing/srw/',
    'marc' => 'http://www.loc.gov/MARC21/slim'
);

if (!isset($_GET['repo']) || !isset($repos[$_GET['repo']])) {
    usage();
}
$repo = $repos[$_GET['repo']];

if ($repo['ident'] == 'bibsys' && isset($_GET['objektid'])) {
    $qs = 'bs.objektid="' . addslashes($_GET['objektid']) . '"';
} else if ($repo['ident'] == 'bibsys' && isset($_GET['dokid'])) {
    $qs = 'bs.dokid="' . addslashes($_GET['dokid']) . '"';
} else if ($repo['ident'] == 'bibsys' && isset($_GET['isbn'])) {
    $qs = 'bs.isbn="' . addslashes($_GET['isbn']) . '"';
} else if ($repo['proto'] == 'z39.50' && isset($_GET['isbn'])) {
    $qs = 'isbn="' . addslashes($_GET['isbn']) . '"';
} else if ($repo['proto'] == 'z39.50' && isset($_GET['author']) && isset($_GET['title'])) {
    $qs = 'au="' . addslashes($_GET['author']) . '" and ti="' . addslashes($_GET['title']) . '"';
} else if (isset($_GET['isbn'])) {
    $qs = 'bath.isbn="' . addslashes($_GET['isbn']) . '"';
} else if (isset($_GET['author']) && isset($_GET['title'])) {
    $qs = 'dc.author="' . addslashes($_GET['author']) . '" AND dc.title="' . addslashes($_GET['title']) . '"';
} else {
    usage();
}

$output = array(
    'permalink' => $repo['permalink'],
    'subjects' => array(),
    'klass' => array()
);

if ($repo['proto'] == 'z39.50') {

    $c = yaz_connect($repo['url'], $repo['connection_options']);
    if (!$c) {
        die('Connection failed');
    }
    yaz_syntax($c, 'marc21');
    $fields = array(
        "ti"   => "1=4",   # Title
        "au"   => "1=1",   # Personal Name
        "isbn" => "1=7"    # ISBn
    );
    yaz_ccl_conf($c, $fields);

    if (!yaz_ccl_parse($c, $qs, $cclresult)) {
        die('ccl: ' . $cclresult);
    }
    $rpn = $cclresult['rpn'];
    yaz_search($c, 'rpn', $rpn);
    yaz_wait();
    $error = yaz_error($c);
    if (!empty($error)) {
        return_json(array('error' => $error, 'rpn' => $rpn, 'ccl' => $qs));
    }
    $hits = yaz_hits($c);
    if ($hits == 0) {
        return_json(array('error' => 'no hits'));
    }

    $output['numberOfRecords'] = $hits;

    for ($i = 1; $i <= $hits; $i++) {
        $rec = '<records>' . yaz_record($c, $i, "xml; charset=marc-8,utf-8") . '</records>';

        $output['raw'] = $rec;

        $xml = new CustomXMLElement($rec);
        $xml->registerXPathNamespaces($ns);

        foreach ($xml->xpath("marc:record") as $record) {
            marc_parser($record, $output);
        }
    }

} else {
    $qs = make_query($qs, 1, 1, $repo['schema']);
    $baseurl = $repo['url'];

    //print "$baseurl$qs";
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

    foreach ($xml->xpath("/srw:searchRetrieveResponse/srw:records/srw:record") as $record) {

        $rec_srw = $record->children($ns['srw']);
        $output['recordid'] = (string)$rec_srw->recordIdentifier;
        $output['subjects'] = array();
        $output['dewey'] = '';

        $v = $record->first('srw:recordData/marc:record/marc:datafield[@tag="082"]/marc:subfield[@code="a"]');
        if ($v !== false) {
            $output['dewey'] = str_replace('/', '', (string)$v);
        } else {
            $v = $record->first('srw:recordData/marc:record/marc:datafield[@tag="089"]/marc:subfield[@code="a"]');
            if ($v !== false) {
                $output['dewey'] = str_replace('/', '', (string)$v);
            }
        }

        $marc_rec = $record->first('srw:recordData/marc:record');

        marc_parser($marc_rec, $output);

    }
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
