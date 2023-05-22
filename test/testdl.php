<?





print "testing";

$errstr = NULL;
$errno = NULL;

$fp = fsockopen ("www.yahoo.com", 80, $errno, $errstr, 30);
if (!$fp) {
    echo "$errstr ($errno)<br>\n";
} else {
    fputs ($fp, "GET / HTTP/1.0\r\nHost: www.yahoo.com\r\n\r\n");
    while (!feof($fp)) {
        echo fgets ($fp,128);
    }
    fclose ($fp);
}








/*  -------   -------------


#--- Now we want to generate a preview of the image so that we can save it into the database ---##
$DownloadImageURL = "http://www.yahoo.com";

print $DownloadImageURL;


	$fd = fopen ($DownloadImageURL, "r");
	$ImageContents = fread ($fd, 5000000);
	fclose ($fd);

print $ImageContents;

-----------------------------  */

?>

