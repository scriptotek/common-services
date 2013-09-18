<?php

//error_reporting(E_ALL);

function pageValid($url){

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.1) Gecko/20061204 Firefox/2.0.0.1");
	curl_exec($ch);

	// Return the results as an associative array.
	$info = curl_getinfo($ch);
	curl_close($ch);

	//Kall rekursivt dersom redirect
	if ($info["redirect_url"]!="") {
		if (pageValid($info["redirect_url"])) {
			return true; 
		}
		else {
			return false;
		}
	}
	//Generisk oppslagsfeil
	if (intval($info["http_code"])>=300){
		return false;
	}
	//Null størrelse
	if ($info["size_download"]=="0") {
		return false;
	}	

	return true;
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


function getRedirectUrl($url){

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_VERBOSE, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.1) Gecko/20061204 Firefox/2.0.0.1");
	curl_exec($ch);

	$info = curl_getinfo($ch);
	curl_close($ch);

	//print_r($info);

	if ($info["redirect_url"]!="")  return $info["redirect_url"];
	if ($info["url"]!="")  return $info["url"];
	return "";
}

$isbn=$_GET["isbn"];

$isbn=trim(str_replace("-", "",$isbn));
$isbn=strtoupper($isbn);

$url="http://covers.openlibrary.org/b/isbn/".$isbn."-L.jpg?default=false";

$redir=getRedirectUrl($url);
if ($redir) {
	$url=$redir;
}

if (pageValid($url)) return_json(array('url' => trim($url)));
else return_json(array('url' => null));

?>