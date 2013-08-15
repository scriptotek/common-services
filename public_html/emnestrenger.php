<?php

function usage() {
    header('Content-type: text/plain; charset=utf-8');
	print "Bruk: \n\n    " . $_SERVER['PHP_SELF'] ."?ddc=<ddc>\n\nder <ddc> er et Dewey-nummer. Eksempel:\n\n    " . $_SERVER['PHP_SELF'] ."?ddc=530.1\n\nFor å få resultatene i JSONP istedetfor JSON; bruk callback. Eksempel:\n\n    " . $_SERVER['PHP_SELF'] ."?ddc=530.1&callback=minfunksjon\n";
	exit();	
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

if (!isset($_GET['ddc'])) usage();
$ddc = preg_replace('/[^0-9.]/i', '', $_GET['ddc']);
if (empty($ddc)) usage();

$url = 'http://wgate.bibsys.no/gate1/FIND?base=USVDEMNE&F0=' . $ddc . '&felt0=kl&type=S%F8k';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_USERAGENT, 'UBO ScriptotekLabs');
curl_setopt($ch, CURLOPT_HEADER, 0); // no headers in the output
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$data = trim(curl_exec($ch));
curl_close($ch);


libxml_use_internal_errors(true);
$dom = DOMDocument::loadHTML($data); //, LIBXML_NOERROR);
$pre = $dom->getElementsByTagName('pre')->item(0);
$lst = array();
if (isset($pre)) {
    foreach ($pre->childNodes as $child) {
        if ($child->nodeName == 'b') {
            $lst[] = strval($child->nodeValue);
        }
    }
}


return_json(array('code' => $ddc, 'strings' => $lst));
