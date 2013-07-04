<?php

function file_get_contents2($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERAGENT, 'UBO Scriptotek Dalek');
    curl_setopt($ch, CURLOPT_HEADER, 0); // no headers in the output
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // return instead of output
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
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

function usage() {
    header('Content-type: text/plain;charset=UTF-8');
    print "Bruk: \n\n"
        . "    " . $_SERVER['PHP_SELF'] ."?objektid=<number>\n\n"
        . "    " . $_SERVER['PHP_SELF'] ."?dokid=<dokid>\n\n"
        . "    " . $_SERVER['PHP_SELF'] ."?isbn=<isbn>\n\n"
        . " der <objektid> eller <dokid> er et gyldig objektid eller dokid. "
        . "Eksempel:\n\n    " . $_SERVER['PHP_SELF'] ."?objektid=052073475\n\n"
        . "For å få resultatene i JSONP istedetfor JSON; bruk callback. Eksempel:\n\n    " . $_SERVER['PHP_SELF'] ."?objektid=052073475&callback=minfunksjon\n";
    exit(); 
}

if (isset($_GET['objektid'])) {
    $qs = make_query('bs.objektid="' . addslashes($_GET['objektid']) . '"');
} else if (isset($_GET['dokid'])) {
    $qs = make_query('bs.dokid="' . addslashes($_GET['dokid']) . '"');    
} else if (isset($_GET['isbn'])) {
    $qs = make_query('bs.isbn="' . addslashes($_GET['isbn']) . '"');    
} else {
    usage();
}

$baseurl = 'http://sru.bibsys.no/search/biblio?';
#$baseurl = 'http://utvikle-a.bibsys.no/search/biblio?';

$source = file_get_contents2("$baseurl$qs");

//print $source;

$ns = array(
    'srw' => 'http://www.loc.gov/zing/srw/',
    'marc' => 'info:lc/xmlns/marcxchange-v1'
);

$output = array('sru_url' => "$baseurl$qs");

//print $source;

$xml = new SimpleXMLElement($source);
$xml->registerXPathNamespace('srw', $ns['srw']);
$xml->registerXPathNamespace('marc', $ns['marc']);

$output['numberOfRecords'] = intval(current($xml->xpath('/srw:searchRetrieveResponse/srw:numberOfRecords')));

$diag = $xml->xpath('//srw:diagnostics');

if (count($diag) != 0) {
  return_json(array('error' => strval($diag[0]->diagnostic->message)));
}

foreach ($xml->xpath("/srw:searchRetrieveResponse/srw:records/srw:record") as $record) {
    $rec_srw = $record->children($ns['srw']);
    $output['objektid'] = strval($rec_srw->recordIdentifier);
    $output['keywords'] = array();
    $output['dewey'] = '';

    $v = $record->xpath('srw:recordData/marc:record/marc:datafield[@tag="082"][@ind1="0"]/marc:subfield[@code="a"]');
    if (count($v) > 0) {
        $output['dewey'] = str_replace('/', '', strval($v[0]));
    }

    $mr = $record->xpath('srw:recordData/marc:record');
    //print $mr[0]->asXML();
    $marc_rec = $mr[0]->xpath('marc:datafield');
    foreach ($marc_rec as $node) {
        $marcfield = intval($node->attributes()->tag);
        switch ($marcfield) {
            case 8:
                $output['form'] = strval(current($node->xpath('marc:subfield[@code="a"]')));
                break;
            case 10:
                $output['dokid'] = strval(current($node->xpath('marc:subfield[@code="a"]')));
                break;
            case 20:
                if (!isset($output['isbn'])) $output['isbn'] = array();
                $isbn = explode(' ', trim(strval(current($node->xpath('marc:subfield[@code="a"]')))));
                array_push($output['isbn'], $isbn[0]);
                break;
            case 82:
                if (!isset($output['klass'])) $output['klass'] = array();
                $klass = strval(current($node->xpath('marc:subfield[@code="a"]')));
                $klass = str_replace('/', '', $klass);
                foreach ($output['klass'] as $kitem) {
                    if (($kitem['kode'] == $klass) && ($kitem['system'] == 'dewey')) {
                        continue 3;
                    }
                }
                array_push($output['klass'], array('kode' => $klass, 'system' => 'dewey'));
                break;
            case 89:
                if (!isset($output['klass'])) $output['klass'] = array();
                $klass = strval(current($node->xpath('marc:subfield[@code="a"]')));
                $klass = str_replace('/', '', $klass);
                foreach ($output['klass'] as $kitem) {
                    if (($kitem['kode'] == $klass) && ($kitem['system'] == 'dewey')) {
                        continue 3;
                    }
                }
                array_push($output['klass'], array('kode' => $klass, 'system' => 'dewey'));
                break;

            case 100:
                $output['main_author'] = array(
                    'name' => strval(current($node->xpath('marc:subfield[@code="a"]')))
                );
                $output['main_author']['authority'] = strval(current($node->xpath('marc:subfield[@code="0"]')));
                break;
            case 245:
                $output['title'] = strval(current($node->xpath('marc:subfield[@code="a"]')));
                $output['subtitle'] = strval(current($node->xpath('marc:subfield[@code="b"]')));
                break;
            case 260:
                $output['publisher'] = strval(current($node->xpath('marc:subfield[@code="b"]')));
                $output['year'] = preg_replace('/[^0-9,]|,[0-9]*$/', '', current($node->xpath('marc:subfield[@code="c"]')));
                break;
            case 300:
                $output['pages'] = strval(current($node->xpath('marc:subfield[@code="a"]')));
                break;
            case 491:
                $output['series'] = strval(current($node->xpath('marc:subfield[@code="a"]')));
                break;
            case 650:
                  $system = strval(current($node->xpath('marc:subfield[@code="2"]')));
                  $emne = strval(current($node->xpath('marc:subfield[@code="a"]')));
                  $tmp = array('emne' => $emne, 'system' => $system);
                  array_push($output['keywords'], $tmp);
                break;
            case 700:
                $output['added_author'] = strval(current($node->xpath('marc:subfield[@code="a"]')));
                break;
        }
    }

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
