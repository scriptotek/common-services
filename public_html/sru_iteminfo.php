<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once('../marcparser.php');
require_once('../customxmlelement/customxmlelement.php');

function usage($repos) {
    header('Content-type: text/plain;charset=UTF-8');
    print "Bruk: \n\n"
        . "    " . $_SERVER['PHP_SELF'] ."?objektid=<number>\n\n"
        . "    " . $_SERVER['PHP_SELF'] ."?dokid=<dokid>\n\n"
        . "    " . $_SERVER['PHP_SELF'] ."?isbn=<isbn>&repo=<repo>\n\n"
        . " der <objektid> eller <dokid> er et gyldig objektid eller dokid. <repo> kan ha en av verdiene 'bibsys', 'loc', 'libris' eller 'bl'."
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

function file_get_contents2($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
//    curl_setopt($ch, CURLOPT_USERAGENT, 'UBO Scriptotek Dalek/0.1 (+http://biblionaut.net/bibsys/)');
    curl_setopt($ch, CURLOPT_HEADER, 0); // no headers in the output
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // return instead of output
    $data = curl_exec($ch);
    curl_close($ch);
    if (empty($data)) {
        print $url;
        exit();
    }
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

function genchksum13($i){                     // function c, $i is the input

    for($a=$s=0;$a<12;)             // for loop x12 - both $a and $s equal 0
                                    // notice there is no incrementation and
                                    // no curly braces as there is just one
                                    // command to loop through

        $s+=$i[$a]*($a++%2?3:1);    // $s (sum) is being incremented by
                                    // $ath character of $i (auto-casted to
                                    // int) multiplied by 3 or 1, depending
                                    // wheter $a is even or not (%2 results
                                    // either 1 or 0, but 0 == FALSE)
                                    // $a is incremented here, using the
                                    // post-incrementation - which means that
                                    // it is incremented, but AFTER the value
                                    // is returned

    return$i.(10-$s%10)%10;         // returns $i with the check digit
                                    // attached - first it is %'d by 10,
                                    // then the result is subtracted from
                                    // 10 and finally %'d by 10 again (which
                                    // effectively just replaces 10 with 0)
                                    // % has higher priority than -, so there
                                    // are no parentheses around $s%10
}

function isbn10_to_13($isbn) {
    $isbn = trim($isbn);
    if(strlen($isbn) == 12){ // if number is UPC just add zero
        $isbn13 = '0'.$isbn;
    } else {
        $isbn2 = substr("978" . trim($isbn), 0, -1);
        $isbn13 = genchksum13($isbn2);
    }
    return ($isbn13);  
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
        'schema' => 'marc21',
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
        'schema' => 'marc21',
        'connection_options' => array(),
        'permalink' => ''
    ),
    'kb' => array(
        'proto' => 'z39.50',
        'ident' => 'kb',
        'url' => 'z3950.kb.dk:2100/kgl01',
        'schema' => 'marc21',
        'connection_options' => array(),
        'permalink' => ''
    ),
    'kth' => array(
        'proto' => 'z39.50',
        'ident' => 'kth',
        'url' => 'innopac.lib.kth.se:210/innopac',
        'schema' => 'marc21',
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
    'marc' => 'http://www.loc.gov/MARC21/slim',
    'd' => 'http://www.loc.gov/zing/srw/diagnostic/'
);

if (!isset($_GET['repo']) || !isset($repos[$_GET['repo']])) {
    usage($repos);
}
$repo = $repos[$_GET['repo']];

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

if ($repo['ident'] == 'bibsys' && isset($_GET['objektid'])) {
    $qs = 'bs.objektid="' . addslashes($_GET['objektid']) . '"';
} else if ($repo['ident'] == 'bibsys' && isset($_GET['dokid'])) {
    $qs = 'bs.dokid="' . addslashes($_GET['dokid']) . '"';
} else if ($repo['ident'] == 'bibsys' && isset($_GET['isbn'])) {
    $qs = implode(' OR ', array_map(function($nr) { return 'bs.isbn="' . $nr . '"'; }, $isbn));

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

$output = array(
    'permalink' => $repo['permalink'],
    'subjects' => array(),
    'klass' => array()
);

function z3950lookup($repo, $qs, $ns, $output) {

    if (!extension_loaded('yaz')) {
        $output['error'] = 'YAZ extension not loaded';
        return $output;
    }

    $c = yaz_connect($repo['url'], $repo['connection_options']);
    if (!$c) {
        $output['error'] = 'Connection to Z39.50 repo failed';
        return $output;
    }
    yaz_syntax($c, $repo['schema']);
    $fields = array(
        'ti'   => '1=4',   # Title
        'au'   => '1=1',   # Personal Name
        'isbn' => '1=7'    # ISBn
    );
    yaz_ccl_conf($c, $fields);

    if (!yaz_ccl_parse($c, $qs, $cclresult)) {
        $output['error'] = 'Failed to parse CCL: ' . $cclresult;
        return $output;
    }
    $rpn = $cclresult['rpn'];
    yaz_search($c, 'rpn', $rpn);
    yaz_wait();
    $error = yaz_error($c);
    if (!empty($error)) {
        $output['error'] = $error;
        $output['rpn'] = $rpn;
        $output['ccl'] = $qs;
        return $output;
    }
    $hits = yaz_hits($c);
    if ($hits == 0) {
        $output['error'] = 'no hits';
        return $output;
    }

    $output['numberOfRecords'] = $hits;

    for ($i = 1; $i <= $hits; $i++) {
        $rec = '<records>' . yaz_record($c, $i, 'xml; charset=marc-8,utf-8') . '</records>';

        $output['raw'] = $rec;

        $xml = new CustomXMLElement($rec);
        $xml->registerXPathNamespaces($ns);

        foreach ($xml->xpath('marc:record') as $record) {
            marc_parser($record, $output);
        }
    }
    return $output;
}

function srulookup($repo, $qs, $ns, $output) {
    $qs = make_query($qs, 1, 1, $repo['schema']);
    $baseurl = $repo['url'];

    //print "$baseurl$qs";
    $source = file_get_contents2("$baseurl$qs");

    $output['sru_url'] = "$baseurl$qs";

    if (empty($source)) {
        $output['error'] = "Got a completely empty response! Probably a connection problem";
        return_json($output);
    }

    $xml = new CustomXMLElement($source);
    $xml->registerXPathNamespaces($ns);

    $diag = $xml->first('/srw:searchRetrieveResponse/srw:diagnostics');
    if ($diag !== false) {
        $output['error'] = $diag->text('d:diagnostic/d:message');
        return_json($output);
    }

    $output['numberOfRecords'] = (int)$xml->text('/srw:searchRetrieveResponse/srw:numberOfRecords');

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
    return $output;
}


if ($repo['proto'] == 'z39.50') {

    $output = z3950lookup($repo, $qs, $ns, $output);

} else {

    $output = srulookup($repo, $qs, $ns, $output);

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
