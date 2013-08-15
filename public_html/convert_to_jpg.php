<?php

$url = $_GET['url'];

if(!filter_var($url, FILTER_VALIDATE_URL)) {
  die("URL is not valid");
}

$tmp_file = tempnam('/data/labs/services/tmp/', 'img');

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_USERAGENT, 'UBO Scriptotek Dalek/0.1 (+http://biblionaut.net/)');
curl_setopt($ch, CURLOPT_HEADER, 0); // no headers in the output
curl_setopt($ch, CURLOPT_REFERER, 'http://ask.bibsys.no');
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$data = curl_exec($ch);         
curl_close($ch);
file_put_contents($tmp_file, $data);

function getMimeType($path)
{
    $a = getimagesize($path);
    $image_type = $a[2];
     
    if(in_array($image_type , array(IMAGETYPE_GIF , IMAGETYPE_JPEG ,IMAGETYPE_PNG , IMAGETYPE_BMP , IMAGETYPE_TIFF_II, IMAGETYPE_TIFF_MM)))
    {
        return image_type_to_mime_type($image_type);
    }
    return false;
}

// if it's not an image, we just delete it (it might be a 404 page for instance)
$mime = getMimeType($tmp_file);
if ($mime === false) {

    die("Appears not to be an image (gif, jpg, png, bmp or tiff)");

} else if ($mime !== 'image/jpeg') {

    $image = new Imagick("$tmp_file");
    $image->setImageFormat('jpg');
    $tmp_file2 = tempnam('/data/labs/services/tmp/', 'img');
    $image->writeImage("$tmp_file2");
    unlink("$tmp_file");
    $tmp_file = $tmp_file2;
}

$image_data = file_get_contents($tmp_file);
header('Content-Type: image/jpg');
print $image_data;

unlink("$tmp_file");
