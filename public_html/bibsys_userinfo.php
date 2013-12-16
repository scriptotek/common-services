<?php

//error_reporting(E_ALL);
//ini_set('display_errors', '1');

require_once('../vendor/autoload.php');
require_once('common.php');
use Danmichaelo\Ncip\NcipConnector,
	Danmichaelo\Ncip\NcipClient;


if (!uio_or_local_ip()) {
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

