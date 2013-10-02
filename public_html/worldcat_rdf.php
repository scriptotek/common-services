<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

#require_once "easyrdf-0.7.2/lib/EasyRdf.php";
#require_once "html_tag_helpers.php";

require_once('../vendor/autoload.php');
require_once('../vendor/easyrdf/easyrdf/lib/EasyRdf.php');


function usage() {
    header('Content-type: text/plain;charset=UTF-8');
    print "Bruk: \n\n    " . $_SERVER['PHP_SELF'] ."?oclc=<oclc> der <oclc> er et OCLC-nummer. Eksempel:\n\n    " . $_SERVER['PHP_SELF'] ."?oclc=755069473\n\nFor å få resultatene i JSONP istedetfor JSON; bruk callback. Eksempel:\n\n    " . $_SERVER['PHP_SELF'] ."?oclc=755069473&callback=minfunksjon\n";
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

if (isset($_GET['oclc'])) {
    $oclc = preg_replace("/[^0-9.]/", "", $_GET['oclc']);
} else {
    usage();
}

EasyRdf_Namespace::set('mads', 'http://www.loc.gov/mads/rdf/v1#');
EasyRdf_Namespace::set('umbel', 'http://umbel.org/umbel#');

$graph = EasyRdf_Graph::newAndLoad('http://www.worldcat.org/oclc/' . $oclc);

if (isset($_GET['debug'])) {
    header('Content-type: text/html; charset=utf-8');
    echo $graph->dump(true);
    exit();
}

$output = array('persons' => array());

$book = $graph->allOfType('schema:Book')[0];
$output['author'] = (string) $book->get('schema:author');
$output['description'] = (string) $book->get('schema:description');
$output['pages'] = (string) $book->get('schema:numberOfPages');
$output['title'] = (string) $book->get('schema:name');
$output['datePublished'] = (string) $book->get('schema:datePublished');

$output['subjects'] = array();

foreach ($book->allResources('schema:about') as $about) {
    if ($about->get('schema:name') && $about->get('mads:isIdentifiedByAuthority')) {
        $output['subjects'][] = array(
            'heading' => (string) $about->get('schema:name'),
            'uri' => (string) $about->get('mads:isIdentifiedByAuthority')
        );
    }
    if ($about->get('schema:name') && $about->get('mads:isIdentifiedByAuthority')) {
        $output['subjects'][] = array(
            'heading' => (string) $about->get('schema:name'),
            'uri' => (string) $about->get('mads:isIdentifiedByAuthority')
        );
    }
}

#$book->get('umbel:isLike');

$book = $graph->allOfType('schema:CreativeWork')[0];
$output['isbn'] = (string) $book->get('schema:isbn');

foreach ($graph->allOfType('schema:Person') as $person) {
    $output['persons'][] = array(
        'label' => (string) $person->get('rdfs:label'),
        'authority' => (string) $person->get('mads:isIdentifiedByAuthority'),
        'viaf' => (string) $person
    );
}

return_json($output);