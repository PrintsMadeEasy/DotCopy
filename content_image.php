<?

require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();




// Content Images can come from either Content Categories or Content Items.
$contentType = WebUtil::GetInput("contentType", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$contentid = WebUtil::GetInput("id", FILTER_SANITIZE_STRING_ONE_LINE);

$dateLastModifiedTimeStamp = "";


$binImageData = null;

if($contentType == "category"){

	$contentObj = new ContentCategory($dbCmd);
		
}
else if($contentType == "item"){

	$contentObj = new ContentItem($dbCmd);
	
}
else if($contentType == "templateImageBig"){

	$contentObj = new ContentTemplate($dbCmd);
	
	$contentObj->preferTemplateSize("big");


}
else if($contentType == "templateImageSmall"){

	$contentObj = new ContentTemplate($dbCmd);
	
	$contentObj->preferTemplateSize("small");

}
else{
	throw new Exception("Illegal content type.");
}



// If the unique ID is just digits... then assume it is the Unique ID from the Database.

if(preg_match("/^\d+$/", $contentid)){

	// The only way you are permitted viewing a ContentID is when you belong to MEMBER.
	$notPermittedFlag = true;
	$passiveAuthObj = Authenticate::getPassiveAuthObject();
	if($passiveAuthObj->CheckIfBelongsToGroup("MEMBER"))
		$notPermittedFlag = false;
		
	if(!$contentObj->loadContentByID($contentid) || $notPermittedFlag){
		WebUtil::print404Error();
	}
}
else{	

	if(!$contentObj->loadContentByTitle($contentid)){
		WebUtil::print404Error();
	}
	
	if(!$contentObj->checkIfActive() || !$contentObj->checkActiveParent()){
		WebUtil::print410Error();
	}
}

if(!$contentObj->checkIfImageStored()){
	WebUtil::print404Error();
}





$dateLastModifiedTimeStamp = $contentObj->getDateLastModified();


  // generate unique ID
$fileEtag = md5($dateLastModifiedTimeStamp);

    
$headers = getallheaders();
    
// if Browser sent a file ID, see if it matches the ID currently on the webserver.
if (isset($headers['If-None-Match']) && ereg($fileEtag, $headers['If-None-Match'])){
	
	header('HTTP/1.1 304 Not Modified');
	header('Date: '. gmdate('D, d M Y H:i:s') . ' GMT');
	header("ETag: \"" . $fileEtag . "\"");
	header("Connection: Keep-Alive");
	header("Keep-Alive: timeout=15, max=97");
	header("ETag: \"" . $fileEtag . "\"");

	/* Example of a Flash file being cached off of the Server
	HTTP/1.1 304 Not Modified
	Date: Thu, 08 Nov 2007 14:16:03 GMT
	Server: Apache/1.3.37 (Unix) mod_auth_passthrough/1.8 mod_log_bytes/1.2 mod_bwlimited/1.4 FrontPage/5.0.2.2635.SR1.2 mod_ssl/2.8.28 OpenSSL/0.9.7a
	Connection: Keep-Alive, Keep-Alive
	Keep-Alive: timeout=15, max=97
	ETag: "5d0081-879f-4629b4aa"
	*/

}
else if (isset($headers['If-Modified-Since']) && ereg(gmdate('D, d M Y H:i:s', $dateLastModifiedTimeStamp), $headers['If-Modified-Since'])){
	
	header('HTTP/1.1 304 Not Modified');
	header('Date: '. gmdate('D, d M Y H:i:s') . ' GMT');
	header("ETag: \"" . $fileEtag . "\"");
	header("Connection: Keep-Alive");
	header("Keep-Alive: timeout=15, max=97");
	header("ETag: \"" . $fileEtag . "\"");

	/* Example of a Flash file being cached off of the Server
	HTTP/1.1 304 Not Modified
	Date: Thu, 08 Nov 2007 14:16:03 GMT
	Server: Apache/1.3.37 (Unix) mod_auth_passthrough/1.8 mod_log_bytes/1.2 mod_bwlimited/1.4 FrontPage/5.0.2.2635.SR1.2 mod_ssl/2.8.28 OpenSSL/0.9.7a
	Connection: Keep-Alive, Keep-Alive
	Keep-Alive: timeout=15, max=97
	ETag: "5d0081-879f-4629b4aa"
	*/

}
else{

	$binImageData =& $contentObj->getImage();
	
	header('Date: '. gmdate('D, d M Y H:i:s') . ' GMT');
	header('Last-Modified: '. gmdate('D, d M Y H:i:s', $dateLastModifiedTimeStamp) . ' GMT');
	header("ETag: \"" . $fileEtag . "\"");
	header("Accept-Ranges: bytes");
	header("Content-Length: ". strlen($binImageData));
	header("Keep-Alive: timeout=15, max=97");
	header("Connection: Keep-Alive");
	header("Content-Type: image/jpeg");


	print $binImageData;
	
	
	/* Example of a PNG Image being downloaded from the server.
	HTTP/1.1 200 OK
	Date: Thu, 08 Nov 2007 14:13:10 GMT
	Server: Apache/1.3.37 (Unix) mod_auth_passthrough/1.8 mod_log_bytes/1.2 mod_bwlimited/1.4 FrontPage/5.0.2.2635.SR1.2 mod_ssl/2.8.28 OpenSSL/0.9.7a
	Last-Modified: Sat, 21 Apr 2007 02:56:42 GMT
	ETag: "5d04cb-191e-46297d6a"
	Accept-Ranges: bytes
	Content-Length: 6430
	Keep-Alive: timeout=15, max=98
	Connection: Keep-Alive
	Content-Type: image/png	
	*/

	/* Example of an HTML file being downloaded and chunked.   However, chunking mostly useful for dynamic content.  
	We are only proxying static files... so better to just use "Content-Length"
	HTTP/1.1 200 OK
	Date: Thu, 08 Nov 2007 14:13:09 GMT
	Server: Apache/1.3.37 (Unix) mod_auth_passthrough/1.8 mod_log_bytes/1.2 mod_bwlimited/1.4 FrontPage/5.0.2.2635.SR1.2 mod_ssl/2.8.28 OpenSSL/0.9.7a
	Expires: Thu, 19 Nov 1981 08:52:00 GMT
	Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0
	Pragma: no-cache
	Keep-Alive: timeout=15, max=100
	Connection: Keep-Alive
	Transfer-Encoding: chunked
	Content-Type: text/html
	*/
	
}



?>