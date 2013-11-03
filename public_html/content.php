<?php

require_once('common.php');

function usage() {
    header('Content-type: text/plain; charset=utf-8');
	print "Bruk: \n\n    " . $_SERVER['PHP_SELF'] ."?isbn=<id>\n\nder <id> er et isbn-nummer. Eksempel:\n\n    " . $_SERVER['PHP_SELF'] ."?isbn=9780849322648\n\nFor å få resultatene i JSONP istedetfor JSON; bruk callback. Eksempel:\n\n    " . $_SERVER['PHP_SELF'] ."?isbn=9780849322648&callback=minfunksjon\n";
	exit();
}

if (!isset($_GET['isbn'])) usage();
$isbn = preg_replace('/[^0-9x]/i', '', $_GET['isbn']);
if (empty($isbn)) usage();

$url = 'http://content.bibsys.no/content/?isbn=' . $isbn;
$data = file_get_contents2($url);

libxml_use_internal_errors(true);

$dom = new DOMDocument();
$dom->loadHTML($data); //, LIBXML_NOERROR);
$h3s = $dom->getElementsByTagName('h3');
$thumb = $dom->getElementById('thumbnail');

$keys = array(
    'Beskrivelse fra forlaget (kort)' => 'short_desc',
    'Beskrivelse fra forlaget (lang)' => 'long_desc',
    'Publisher\'s description (brief)' => 'short_desc',
    'Publisher\'s description (full)' => 'long_desc',
    'Innholdsfortegnelse' => 'toc'
);
$json = array('source' => $url);
foreach ($h3s as $h3) {
    $title = $h3->nodeValue;
    $div = $h3->nextSibling->nextSibling;
    $body = $div->nodeValue;
    if (!empty($title) && !empty($body)) {
        if (array_key_exists($title, $keys)) {
            $json[$keys[$title]] = trim($body);
        }
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
