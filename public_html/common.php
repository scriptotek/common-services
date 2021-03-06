<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

function file_get_contents2($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERAGENT, 'UBO Scriptotek Dalek (+labs.biblionaut.net)');
    curl_setopt($ch, CURLOPT_HEADER, 0); // no headers in the output
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // return instead of output
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

function is_isbn($id) {
    // både dokid og objektid har lengde 9
    // todo: more sophisticated check
    if (strlen($id) == '13' || strlen($id) == '10') {
        return true;
    }
    return false;
}

function return_json($obj) {
    if (isset($_REQUEST['callback'])) {
        header('Content-type: application/javascript; charset=utf-8');
        echo $_REQUEST['callback'] . '(' . json_encode($obj) . ')';
        exit();
    } else {
        header('Access-Control-Allow-Origin: *');
        header('Content-type: application/json; charset=utf-8');
        if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
            echo json_encode($obj, JSON_PRETTY_PRINT);
        } else {
            echo json_encode($obj);
        }
        exit();
    }
}

function lookup_id($id) {

    //$url = 'http://adminwebservices.bibsys.no/objectIdService/getObjectId?id=' . $id;
    $url = 'http://adminwebservices.bibsys.no/objectIdService/getIds?id=' . $id;

    $ids = trim(file_get_contents2($url));

    $json = array();
    $keys = array(
        'objektId' => 'objektid',
        'dokumentId' => 'dokid',
        'hefteId' => 'heftid',
    );
    foreach (explode("\n", $ids) as $line) {
        list($key, $val) = explode(':', $line);
        $json[$keys[$key]] = trim($val);
    }

    return $json;
}

function uio_ip() {
    $ip  = ip2long($_SERVER['REMOTE_ADDR']);
    // https://www.uio.no/english/services/it/security/cert/about-cert/constituency.html
    if ($ip >= ip2long('193.157.108.0') && $ip <= ip2long('193.157.255.255')) return true;
    if ($ip >= ip2long('129.240.0.0') && $ip <= ip2long('129.240.255.255')) return true;
    if ($ip >= ip2long('158.36.184.0') && $ip <= ip2long('158.36.191.255')) return true;
    if ($ip >= ip2long('193.156.90.0') && $ip <= ip2long('193.156.90.255')) return true;
    if ($ip >= ip2long('193.156.120.0') && $ip <= ip2long('193.156.120.255')) return true;
    return false;
}

function uio_or_local_ip() {
    $ip  = ip2long($_SERVER['REMOTE_ADDR']);
    if (uio_ip()) return true;
    if ($ip == ip2long('192.165.67.230') || $ip == ip2long('212.71.253.164') || $ip == ip2long('127.0.0.1')) return true;
    return false;
}

$config = json_decode(file_get_contents(__DIR__ . '/../config.json'), true);


/*function handleCorsPreflight($allowGet = true, $allowPost = false) {
    // respond to preflights
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        // return only the headers and not the content
        // only allow CORS if we're doing a GET
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {

            if ($allowGet && $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] == 'GET') {
                header('Access-Control-Allow-Origin: *');
                header('Access-Control-Allow-Headers: X-Requested-With');
            }
            if ($allowPost && $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] == 'POST') {
                header('Access-Control-Allow-Origin: *');
                header('Access-Control-Allow-Headers: X-Requested-With');
            }
        }
        exit;
    }
}
*/
