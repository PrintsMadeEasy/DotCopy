<?
print "hello";

	$DownloadImageURL = "http://www.yahoo.com";
	$fd = fopen ($DownloadImageURL, "r");
	$ImageContents = fread ($fd, 5000000);
	fclose ($fd);
print $ImageContents;
?>