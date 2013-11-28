<?php

//error_reporting(E_ALL);
//ini_set('display_errors', '1');

require_once('../vendor/autoload.php');
use Danmichaelo\Ncip\NcipConnector,
	Danmichaelo\Ncip\NcipClient;


$ip  = ip2long($_SERVER['REMOTE_ADDR']);
// https://www.uio.no/english/services/it/security/cert/about-cert/constituency.html
if ($ip >= ip2long('193.157.108.0') && $ip <= ip2long('193.157.255.255')) {
} else if ($ip >= ip2long('129.240.0.0') && $ip <= ip2long('129.240.255.255')) {
} else if ($ip >= ip2long('158.36.184.0') && $ip <= ip2long('158.36.191.255')) {
} else if ($ip >= ip2long('193.156.90.0') && $ip <= ip2long('193.156.90.255')) {
} else if ($ip >= ip2long('193.156.120.0') && $ip <= ip2long('193.156.120.255')) {
} else if ($ip == ip2long('192.165.67.230') || $ip == ip2long('212.71.253.164') || $ip == ip2long('127.0.0.1')) { // biblionaut
} else {
    header("Content-Type: text/plain; charset=utf-8");
    print "Beklager, du ser ikke ut til å ha en IP innenfor UiOs område.";
    exit();
}

$conn = new NcipConnector(array(
	'url' => 'http://ncip.bibsys.no/ncip/NCIPResponder',
	'user_agent' => 'Realfagsbibliotekets maursluker/0.1'
));
$client = new NcipClient($conn, array(
	'agency_id' => 'k'
));

if (!isset($_REQUEST['ltid']) || empty($_REQUEST['ltid'])) {
	echo 'Bruk: ?ltid=...';
	exit;
}

// Hent data
$response = $client->lookupUser($_REQUEST['ltid']);

// Fjerner lånelisten fra responsen av personvernhensyn
unset($response->loanedItems);

header('Access-Control-Allow-Origin: *');
header('Content-type: application/json; charset=utf-8');
echo json_encode($response);

