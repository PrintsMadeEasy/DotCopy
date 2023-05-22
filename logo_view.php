<?

require_once("library/Boot_Session.php");

$domainID = Domain::getDomainIDfromURL();

$domainLogoObj = new DomainLogos(Domain::getDomainIDfromURL());


$jpegPhoto = $domainLogoObj->getPrintQualityMediumJpegBin();
$DateLastModified = $domainLogoObj->getPrintQualityMediumJpegDateModified();


// Close the Session as soon as possible.  We already got the Session ID.
// Waiting for the user to finish downloading the data could cause lots of database locks.
session_write_close();



// Close the connection... because with lots of thumbnails on the page with persistent connections it can cause funny problems.
header('Accept-Ranges: bytes');
header("Content-Length: ". strlen($jpegPhoto));
header("Connection: close");
header("Content-Type: image/jpeg");
header("Last-Modified: " . date("D, d M Y H:i:s", $DateLastModified) . " GMT");
header("Cache-Control: store, cache");


print $jpegPhoto;


