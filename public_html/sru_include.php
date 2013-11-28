<?php

require_once('../vendor/autoload.php');

use Danmichaelo\QuiteSimpleXMLElement\QuiteSimpleXMLElement,
    Danmichaelo\SimpleMarcParser\BibliographicParser;


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
        // BNF ser ut til å gi "Permanent system error" ved 'bath.isbn="isbn1" OR bath.isbn="isbn2"'.. merkelig
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

class SRUClient {

    public function __construct($url, $options)
    {
        $this->url = $url;

        $this->schema = isset($options['schema'])
            ? $options['schema']
            : 'marcxml';

        $this->namespaces = isset($options['namespaces'])
            ? $options['namespaces']
            : array(
                'srw' => 'http://www.loc.gov/zing/srw/',
                'marc' => 'http://www.loc.gov/MARC21/slim',
                'd' => 'http://www.loc.gov/zing/srw/diagnostic/'
            );

        $this->version = isset($options['version'])
            ? $options['version']
            : '1.1';

    }

    /**
     * Returns: QuiteSimpleXMLElement
     */
    private function query($query) {
        $this->last_url = $this->url . http_build_query($query);
        $response = file_get_contents2($this->last_url);

        if (empty($response)) {
            $this->error = 'empty_response';
            return null;
            // Got a completely empty response! Probably a connection problem
        }

        $xml = new QuiteSimpleXMLElement($response);
        $xml->registerXPathNamespaces($this->namespaces);
        return $xml;
    }

    /**
     * Carries out a srw:searchRetrieveResponse
     */
    public function search($cql, $start = 1, $count = 10) {

        $qs = array(
            'version' => $this->version,
            'operation' => 'searchRetrieve',
            'recordSchema' => $this->schema,
            'maximumRecords' => $count,
            'query' => $cql
        );

        if ($start != 1) {
            $qs['startRecord'] = 1; // BIBSYS SRU (more?) gives more understandable error messages if we don't provide startRecord unless necessary
        }

        $response = $this->query($qs);

        if (isset($this->error)) {
            return array(
                'error' => $this->error
            );
        }

        $diag = $response->first('/srw:searchRetrieveResponse/srw:diagnostics');
        if ($diag !== false) {
            return array(
                'error' => $diag->text('d:diagnostic/d:message')
            );
        }

        $output = array(
            'numberOfRecords' => (int)$response->text('/srw:searchRetrieveResponse/srw:numberOfRecords'),
            'records' => array()
        );

        $parser = new BibliographicParser;

        foreach ($response->xpath('/srw:searchRetrieveResponse/srw:records/srw:record') as $record) {

            $output['records'][] = $parser->parse($record->first('srw:recordData/marc:record'));

        }

        return $output;

    }

}

function srulookup($repo, $qs, $ns) {

    $sru = new SRUClient($repo['url'], $repo);

    $response = $sru->search($qs, 1, 1);

    $out = array(
        'sru_url' => $sru->last_url
    );

    if (isset($response['records']) && count($response['records']) != 0) {

        $rec = $response['records'][0];

        $out['recordid'] = $rec['id'];

        foreach ($rec as $key => $val) {
            $out[$key] = $val;
        }

        foreach ($rec['classifications'] as $cl) {
            if ($cl['system'] == 'dewey') {
                $out['dewey'] = $cl['number'];
                break;
            }
        }

        return $out;

    } else {

        // Add to out
        return $response;
    }

}


function z3950lookup($repo, $qs, $ns) {

    $output = array();

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
