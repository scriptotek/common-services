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
