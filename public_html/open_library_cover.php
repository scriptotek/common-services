<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once('../vendor/autoload.php');
require_once('common.php');

function usage() {
    header('Content-type: text/plain;charset=UTF-8');
    print "Bruk: \n\n"
        . "\t" . 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'] ."?id=<number>\n\n"
        . "\tder id er et isbn-nr.\n\n"
		. "\tisbn som identifikator kan også brukes.\n\n"
        . "Eksempel:\n\n\t".'http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'] ."?id=9780393082876\n\n\t\teller\n\n"
		. "\t".'http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'] ."?isbn=9780393082876\n\n";
    exit(); 
}

function pageInValid($url){

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
		if (pageInValid($info["redirect_url"])) {
			return false; 
		}
		else {
			return $info["http_code"];
		}
	}
	//Generisk oppslagsfeil
	if (intval($info["http_code"])>=300){
		return "HTTP-ERROR: ".$info["http_code"];
	}
	//Null størrelse
	if ($info["size_download"]=="0") {
		return "Bildet har ingen størrelse";
	}	

	return false;
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

if (isset($_GET["id"])) $isbn=$_GET["id"];

elseif (isset($_GET["isbn"])) $isbn=$_GET["isbn"];

else {
    usage();
}

if (is_isbn($isbn)) {

	$isbn=trim(str_replace("-", "",$isbn));
	$isbn=strtoupper($isbn);

	$url="http://covers.openlibrary.org/b/isbn/".$isbn."-L.jpg?default=false";

	$redir=getRedirectUrl($url);
	if ($redir) {
		$url=$redir;
	}

	$error=pageInValid($url);

	if (!$error) return_json(array('url' => trim($url)));
	else {
		return_json(array('error' => $error));
	}
}

else {

	return_json(array('error' => 'Ikke gyldig isbn-nr!'));

}






?>
