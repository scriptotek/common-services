<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

function file_get_contents2($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERAGENT, 'UBO Scriptotek Dalek');
    curl_setopt($ch, CURLOPT_HEADER, 0); // no headers in the output
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // return instead of output
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

function return_json($obj) {
    if (isset($_REQUEST['callback'])) {
        header('Content-type: application/javascript; charset=utf-8');
        echo $_REQUEST['callback'] . '(' . json_encode($obj) . ')';
        exit();
    } else {
        header('Access-Control-Allow-Origin: *');
        header('Content-type: application/json; charset=utf-8');
        echo json_encode($obj);
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
    if (uio_ip()) return true;
    if ($ip == ip2long('192.165.67.230') || $ip == ip2long('212.71.253.164') || $ip == ip2long('127.0.0.1')) return true;
    return false;
}
