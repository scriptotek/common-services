<?php

function usage() {
    header('Content-type: text/plain; charset=utf-8');
	print "Bruk: \n\n    " . $_SERVER['PHP_SELF'] ."?isbn=<id>\n\nder <id> er et isbn-nummer. Eksempel:\n\n    " . $_SERVER['PHP_SELF'] ."?isbn=9780849322648\n\nFor å få resultatene i JSONP istedetfor JSON; bruk callback. Eksempel:\n\n    " . $_SERVER['PHP_SELF'] ."?isbn=9780849322648&callback=minfunksjon\n";
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

if (!isset($_GET['isbn'])) usage();
$isbn = preg_replace('/[^0-9x]/i', '', $_GET['isbn']);
if (empty($isbn)) usage();

$url = 'http://content.bibsys.no/content/?isbn=' . $isbn;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_USERAGENT, 'UBO ScriptotekLabs');
curl_setopt($ch, CURLOPT_HEADER, 0); // no headers in the output

curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$data = trim(curl_exec($ch));
curl_close($ch);


libxml_use_internal_errors(true);
$dom = DOMDocument::loadHTML($data); //, LIBXML_NOERROR);
$h3s = $dom->getElementsByTagName('h3');
$thumb = $dom->getElementById('thumbnail');

$keys = array(
    'Beskrivelse fra forlaget (kort)' => 'short_desc',
    'Beskrivelse fra forlaget (lang)' => 'long_desc',
    'Innholdsfortegnelse' => 'toc'
);
$json = array();
foreach ($h3s as $h3) {
    $title = $h3->nodeValue;
    $div = $h3->nextSibling->nextSibling;
    $body = $div->nodeValue;
    if (!empty($title) && !empty($body)) {
        $json[$keys[$title]] = $body;
    }
}
if ($thumb) {
    $img = $thumb->getElementsByTagName('img')->item(0);
    if ($img) {
        $json['thumb'] = $img->getAttribute('src');
    }
}

return_json($json);

?>
