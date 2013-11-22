<?php

require_once('../vendor/autoload.php');
require_once('../marcparser.php');
use Danmichaelo\QuiteSimpleXMLElement\QuiteSimpleXMLElement;


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
    'bnf' => array(
        'proto' => 'sru',
        'ident' => 'bnf',
        'url' => 'http://z3950.loc.gov:7090/voyager?',
        'schema' => 'marcxml',
        'permalink' => ''
    ),
    'dnb' => array(
        'proto' => 'sru',
        'ident' => 'dnb',
        'url' => 'http://services.dnb.de/sru/dnb?',
        'schema' => 'MARC21-xml',
        'permalink' => ''
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

function srulookup($repo, $qs, $ns, $output) {
    $qs = make_query($qs, 1, 1, $repo['schema']);
    $baseurl = $repo['url'];

    //print "$baseurl$qs";
    die("$baseurl$qs");
    $source = file_get_contents2("$baseurl$qs");

    $output['sru_url'] = "$baseurl$qs";

    if (empty($source)) {
        $output['error'] = "Got a completely empty response! Probably a connection problem";
        return_json($output);
    }

    $xml = new QuiteSimpleXMLElement($source);
    $xml->registerXPathNamespaces($ns);

    $diag = $xml->first('/srw:searchRetrieveResponse/srw:diagnostics');
    if ($diag !== false) {
        $output['error'] = $diag->text('d:diagnostic/d:message');
        return_json($output);
    }

    $output['numberOfRecords'] = (int)$xml->text('/srw:searchRetrieveResponse/srw:numberOfRecords');

    foreach ($xml->xpath("/srw:searchRetrieveResponse/srw:records/srw:record") as $record) {

        $output['recordid'] = $record->text('srw:recordIdentifier');
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

        $xml = new QuiteSimpleXMLElement($rec);
        $xml->registerXPathNamespaces($ns);

        foreach ($xml->xpath('marc:record') as $record) {
            marc_parser($record, $output);
        }
    }
    return $output;
}