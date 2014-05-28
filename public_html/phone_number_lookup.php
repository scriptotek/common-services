<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once('../vendor/autoload.php');
require_once('common.php');

// if (!uio_or_local_ip()) {
// 	return_json(array('error' => 'ip_not_whitelisted'));
// }

function usage() {
	print "Bruk: \n\n    " . $_SERVER['PHP_SELF'] ."?phone=<phone>\n\nder <phone> er et 8-sifret norsk telefonnummer. Eksempel:\n\n    " . $_SERVER['PHP_SELF'] ."?phone=99887766";
	exit();
}


if (!isset($_GET['phone'])) usage();

$phone  = $_GET['phone'];

$url = $config['easyconnect']['baseurl'] . http_build_query(array(
	'fq' => 'phone:' . $phone,
	'user' => $config['easyconnect']['user'],
	'pass' => $config['easyconnect']['pass']
));

$response = json_decode(file_get_contents2($url), true);
$source = 'easyconnect';

if ($response['response']['numFound'] != 0) {
	$p = $response['response']['docs'][0];
	$p['found'] = true;
	$p['source'] = 'easyconnect';
	return_json($p);
}

return_json(array(
	'found' => false
));
