<?php

require_once('common.php');
require_once('sru_include.php');

function usage() {
    header('Content-type: text/plain; charset=utf-8');
    $me = $_SERVER['HTTPS'] ? 'https://' : 'http://' . $_SERVER['SERVER_NAME'] . $_SERVER['PHP_SELF'];
	print "Bruk: \n\n    " . $_SERVER['PHP_SELF'] ."?isbn=<id>\n\nder <id> er et isbn-nummer (med eller uten bindestreker). Eksempel:\n\n    http://" . $_SERVER['SERVER_NAME'] . $_SERVER['PHP_SELF'] ."?isbn=9780849322648\n\nVil du til Primo, legg på \"&primo\", eksempel:\n\n    http://" . $_SERVER['SERVER_NAME'] . $_SERVER['PHP_SELF'] ."?isbn=9780849322648&primo\n";
	exit();
}

if (!isset($_GET['isbn'])) usage();
$isbn = preg_replace('/[^0-9x]/i', '', $_GET['isbn']);
if (empty($isbn)) usage();

$output = array(
    'subjects' => array(),
    'klass' => array()
);

$repo = $repos['bibsys'];
$qs = 'bs.isbn="' . $isbn . '"';
$output = srulookup($repo, $qs, $ns, $output);

if (!isset($output['control_number'])) {
	print "Fant ikke ISBN-nummeret i BIBSYS-katalogen.\n";
	exit();
}
$objektid = $output['control_number'];

if (isset($_GET['primo'])) {
	header('Location: http://bibsys-primo.hosted.exlibrisgroup.com/primo_library/libweb/action/dlDisplay.do?docId=BIBSYS_ILS' . $objektid . '&vid=BIBSYS');
} else {
	header('Location: http://ask.bibsys.no/ask/action/show?pid=' . $objektid . '&kid=biblio');
}


exit();
