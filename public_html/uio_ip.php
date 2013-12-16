<?php

//error_reporting(E_ALL);
//ini_set('display_errors', '1');

require_once('../vendor/autoload.php');
require_once('common.php');

header('Access-Control-Allow-Origin: *');
header('Content-type: application/json; charset=utf-8');
echo json_encode(array(
	'uio_ip' => uio_ip()
));
