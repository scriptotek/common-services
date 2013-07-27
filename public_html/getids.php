<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

function usage() {
    header('Content-type: text/plain; charset=utf-8');
	print "Bruk: \n\n    " . $_SERVER['PHP_SELF'] ."?id=<id>\n\nder <id> er et knyttid eller dokid, eller\n\n    " . $_SERVER['PHP_SELF'] ."?isbn=<isbn>\n\nder <isbn> er et isbn-nummer. Eksempel:\n\n    " . $_SERVER['PHP_SELF'] ."?id=036051NA0\n\nFor å få resultatene i JSONP istedetfor JSON; bruk callback. Eksempel:\n\n    " . $_SERVER['PHP_SELF'] ."?id=036051NA0&callback=minfunksjon\n";
	exit();	
}

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


if (isset($_GET['id'])) {

    $knyttdokid = $_GET['id'];
    if (empty($knyttdokid)) usage();

    $url = 'http://adminwebservices.bibsys.no/objectIdService/getObjectId?id=' . $knyttdokid;
    $url = 'http://adminwebservices.bibsys.no/objectIdService/getIds?id=' . $knyttdokid;

    $ids = trim(file_get_contents2($url));

    $json = array();
    $keys = array(
        'objektId' => 'objektid',
        'dokumentId' => 'dokid',
        'hefteId' => 'heftid',
    );
    foreach (explode("\n", $ids) as $line) {
        list($key, $val) = explode(':', $line);
        $json[$keys[$key]] = trim($val);
    }

    return_json($json);

} else if (isset($_GET['isbn'])) {

    $isbn = $_GET['isbn'];
    if (empty($isbn)) usage();

    $qs = make_query('bs.isbn="' . addslashes($isbn) . '"');

    $baseurl = 'http://sru.bibsys.no/search/biblio?';
    #$baseurl = 'http://utvikle-a.bibsys.no/search/biblio?';

    $source = file_get_contents2("$baseurl$qs");

    //print $source;

    $ns = array(
        'srw' => 'http://www.loc.gov/zing/srw/',
        'marc' => 'info:lc/xmlns/marcxchange-v1'
    );

    $json = array('isbn' => $isbn);

    //print $source;

    $xml = new SimpleXMLElement($source);
    $xml->registerXPathNamespace('srw', $ns['srw']);
    $xml->registerXPathNamespace('marc', $ns['marc']);

    //$output['numberOfRecords'] = intval(current($xml->xpath('/srw:searchRetrieveResponse/srw:numberOfRecords')));

    $diag = $xml->xpath('//srw:diagnostics');

    if (count($diag) != 0) {
        return_json(array('error' => strval($diag[0]->diagnostic->message)));
    }

    foreach ($xml->xpath("/srw:searchRetrieveResponse/srw:records/srw:record") as $record) {
        $rec_srw = $record->children($ns['srw']);
        $json['objektid'] = strval($rec_srw->recordIdentifier);
    }
    return_json($json);

} else {
    usage();
}

?>
