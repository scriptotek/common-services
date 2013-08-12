<?php

# Documentation: http://xisbn.worldcat.org/xisbnadmin/doc/api.htm#geteditions
#
$base_url = 'http://xisbn.worldcat.org/webservices/xid/isbn';

function usage() {
    header('Content-type: text/plain;charset=UTF-8');
    print "Bruk: \n\n"
        . "    " . $_SERVER['PHP_SELF'] ."?isbn=<isbn>&repo=<repo>\n\n"
        . " der <isbn> er et gyldig objektid eller dokid."
        . "Eksempel:\n\n    " . $_SERVER['PHP_SELF'] ."?isbn=0691140340\n\n"
        . "For å få resultatene i JSONP istedetfor JSON; bruk callback. Eksempel:\n\n    " . $_SERVER['PHP_SELF'] ."?isbn=0691140340&callback=minfunksjon\n";
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

function make_query($method = 'getEditions', $format = 'json', $fields = 'form,year,lang,ed,lccn,oclcnum,originalLang,publisher,url') {
    return http_build_query(array(
        'method' => $method,
        'format' => $format,
        'fl' => $fields
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

if (!isset($_GET['isbn'])) {
    usage();
}
$isbn = preg_replace('/[^0-9]/', '', $_GET['isbn']);

$url = $base_url . '/' . $isbn . '?' . make_query();

$source = json_decode(file_get_contents2($url));
$source->url = $url;

$formats = array(
    'AA' => 'audio',
    'BA' => 'book',
    'BB' => 'hardcover',
    'BC' => 'paperback',
    'DA' => 'digital',
    'FA' => 'film/transp.',
    'MA' => 'microform',
    'VA' => 'video'
);

if (isset($source->list) && is_array($source->list)) {
    foreach ($source->list as &$itm) {
        if (isset($itm->form) && is_array($itm->form)) {
            foreach ($itm->form as &$fmt) {
                $fmt = $formats[$fmt];
            }            
        }
    }

}

return_json($source);
