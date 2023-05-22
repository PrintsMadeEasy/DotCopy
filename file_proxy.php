<?

require_once("library/Boot_WithoutSession.php");


$fileName = WebUtil::GetInput("fileName", FILTER_SANITIZE_STRING_ONE_LINE);
$vars = WebUtil::GetInput("vars", FILTER_SANITIZE_STRING_ONE_LINE);


if(empty($fileName)){
	WebUtil::print404Error();
}


$domainKey = Domain::getDomainKeyFromURL();



// For SEO... put up some redirects while we switch postcards.com to the live site.
if($domainKey == "Postcards.com"){
	
	if($fileName == "Guerilla-Marketing.html"){
		header("HTTP/1.1 301 Moved Permanently");
		header( "Location: ./guerilla_marketing.html" ); 
		exit;
	}
	else if($fileName == "Real-Estate-Postcards.html"){
		header("HTTP/1.1 301 Moved Permanently");
		header( "Location: ./real_estate_postcards.html" ); 
		exit;
	}
	else if($fileName == "Direct-Mail-Tips.html"){
		header("HTTP/1.1 301 Moved Permanently");
		header( "Location: ./direct_mail_postcards.html" ); 
		exit;
	}
	else if($fileName == "Photo-Postcards.html"){
		header("HTTP/1.1 301 Moved Permanently");
		header( "Location: ./photo_postcards.html" ); 
		exit;
	}
	else if($fileName == "Custom-Postcards.html"){
		header("HTTP/1.1 301 Moved Permanently");
		header( "Location: ./custom_postcards.html" ); 
		exit;
	}
	else if($fileName == "Postcards-Online.html"){
		header("HTTP/1.1 301 Moved Permanently");
		header( "Location: ./postcards_online.html" ); 
		exit;
	}
	else if($fileName == "Cheap-Postcards.html"){
		header("HTTP/1.1 301 Moved Permanently");
		header( "Location: ./cheap_postcards.html" ); 
		exit;
	}
	else if($fileName == "Making-Postcards.html"){
		header("HTTP/1.1 301 Moved Permanently");
		header( "Location: ./making_postcards.html" ); 
		exit;
	}
	else if($fileName == "Personalized-Postcards.html"){
		header("HTTP/1.1 301 Moved Permanently");
		header( "Location: ./personalized_postcards.html" ); 
		exit;
	}
	
	else if($fileName == "Save-The-Date-Postcards.html"){
		header("HTTP/1.1 301 Moved Permanently");
		header( "Location: ./save_the_date_postcards.html" ); 
		exit;
	}
	
}




// We almost always figure out the Domain by looking at the URL.
// However, if the "Referer" (should come in the header if the browser permits) is set to the "SavedProjects.php" (or similar) file...
// Then we may be want to override the with the Domain Key in the UserID override.
// That way if an Admin is overriding the Saved Projects for a user... they are able to see all of the images/SWF files out of the sandbox... while still running on their master domain.
// Trying to get admins to switch the domains in their browser URL would require them to Login to that domain... a real pain if the admin is in charge of 100 domains.
if(isset($_SERVER["HTTP_REFERER"]) && !empty($_SERVER["HTTP_REFERER"])){
	$scriptName = array_shift(explode('?', basename($_SERVER["HTTP_REFERER"])));
	
	// Don't do this check if the user is trying to Proxy an HTML page.
	// We only want this switch to happen after someone has loaded the HTML or PHP contents... and then the components (images, SWF, etc.) are being proxied.
	if(in_array($scriptName, array("SavedProjects.php")) && !preg_match("/\.html?$/", $fileName)){

		WebUtil::InitializeSession();
		
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		if($passiveAuthObj->CheckIfLoggedIn()){

			//The user ID that we want to use for the Saved Project might belong to somebody else;
			$UserID = ProjectSaved::GetSavedProjectOverrideUserID($passiveAuthObj);
			
			if($UserID != $passiveAuthObj->GetUserID()){
				
				$domainIDofUser = UserControl::getDomainIDofUser($UserID);
				$domainKey = Domain::getDomainKeyFromID($domainIDofUser);
			}
		}
	}
}




// This will keep people from trying to download template preview images from all of our domains, therefore learning all of the domain names.
// For example, we write "PrintsMadeEasy.com" on a lot of the PME templates, etc.
// Category templates begin with "preview_IDNUMBER"  and search engine previews begin with "se_IDNUMBER"
$matches = array();
if(preg_match("/((preview_)|(se_))(\d+)\.jpg/", $fileName, $matches)){
	
	if(!isset($matches[1]) || !isset($matches[4])){
		WebUtil::print404Error();
	}

	$templatePreviewID = intval($matches[4]);
	
	$dbCmd = new DbCmd();
	$dbCmd->Query("SELECT TemplateID, SearchEngineID FROM artworkstemplatespreview WHERE ID=$templatePreviewID");
	if($dbCmd->GetNumRows() == 0){
		WebUtil::print404Error();
	}
	
	$row = $dbCmd->GetRow();
	$templateCategoryID = $row["TemplateID"];
	$searchEngineID = $row["SearchEngineID"];

	
	if(empty($templateCategoryID) && empty($searchEngineID)){
		WebUtil::print404Error();
	}
	
	$productIDofTemplates = 0;
	
	// The Artwork Preview ID can be linked to either a Search Engine template, or a Category Template.
	if(!empty($templateCategoryID)){
		$dbCmd->Query("SELECT ProductID FROM artworkstemplates WHERE ArtworkID=$templateCategoryID");
		$productIDofTemplates = $dbCmd->GetValue();
	}
	else if(!empty($searchEngineID)){
		$dbCmd->Query("SELECT ProductID FROM artworksearchengine WHERE ID=$searchEngineID");
		$productIDofTemplates = $dbCmd->GetValue();
	}
	
	if(empty($productIDofTemplates) || !Product::checkIfProductIDexists($dbCmd, $productIDofTemplates)){
		WebUtil::print404Error();
	}
	
	$dominIDofProduct = Product::getDomainIDfromProductID($dbCmd, $productIDofTemplates);
	$domainIDfromURL = Domain::getDomainIDfromURL();
	
	if($domainIDfromURL != $dominIDofProduct){
		WebUtil::print404Error();
	}
}




// First Try to open the file from the Domain Sandbox
// If that doesn't work then we will try to open it from the current webserver
$fileNameOne = Domain::getDomainSandboxPath($domainKey) . "/" . $fileName;

$fileNameTwo = Constants::GetWebserverBase() . "/" . $fileName;


	
if(file_exists($fileNameOne)){
	$fileNameToOpen = $fileNameOne;
}
else if(file_exists($fileNameTwo)){
	$fileNameToOpen = $fileNameTwo;
}
else{
	WebUtil::print404Error();
}






// Contstruct the URL for the page that the user was trying to access.
// The current URL of this script is being masked by Apache Mod Rewrite
$websiteUrlForDomain = Domain::getWebsiteURLforDomainID(Domain::getDomainIDfromURL());
if(empty($_SERVER['HTTPS']))
	$currentURL = "http://$websiteUrlForDomain";
else
	$currentURL = "https://$websiteUrlForDomain";

$currentURL .= "/" . $fileName;

if(!empty($vars))
	$currentURL .= "?vars=" . urlencode($vars);








// We don't want to proxy everything.  There could be a security hole if a malicious user tried to proxy a PHP file. It wouldn't execute, instead our proxy would print the source.
// Define a list of legal file extensions, print a 404 error if the extension is not in our list.
$legalFileProxyExtensionsArr = array("GIF", "JPG", "JPEG", "HTM", "HTML", "STAGING", "SWF", "PNG", "CSS", "ICO", "FLV", "JS", "MPEG", "MPG", "ZIP", "PDF", "TXT", "CSV", "XLS", "GZ", "TAR", "XML", "PPT", "GG");


// Make sure there the file name has a period with 1 to 7 letters at the end.
$matches = array();
if(!preg_match("/\.((\w|\d){1,7})$/", $fileName, $matches)){
	WebUtil::print404Error();
}
else{
	$ext = strtoupper($matches[1]);
}

if(!in_array($ext, $legalFileProxyExtensionsArr)){
	WebUtil::print404Error();
}




// Get the mime type based upon the file extension. 3-4 letters after the period.
$mimeTypeOfFile = FileUtil::getMimeTypeByExtentionOffDisk($fileNameToOpen);


$sizeOfFile = filesize($fileNameToOpen);
$timeOfFile = filemtime($fileNameToOpen);

  // generate unique ID
$fileEtag = $timeOfFile . "-" . $sizeOfFile;

    
$headers = getallheaders();


// Define some triggers which will break caching (even if the page has not been modified).
$allowCachingFlag = true;

// Passing variables between pages should always cause the page to be regenerated, without caching.
if(!empty($vars))
	$allowCachingFlag = false;
	
	
// Make sure the browser isn't forcing the cache to be flushed.
if(isset($headers['Pragma']) && $headers['Pragma'] == "no-cache")
	$allowCachingFlag = false;
if(isset($headers['Cache-Control']) && $headers['Cache-Control'] == "no-cache")
	$allowCachingFlag = false;
	

// If Browser sent a file ID, see if it matches the ID currently on the webserver.
// If we are sending in Dynamic Variables, then we shoudn't try to cache.
if (isset($headers['If-None-Match']) && ereg($fileEtag, $headers['If-None-Match']) && $allowCachingFlag){
	
	header('HTTP/1.1 304 Not Modified');
	header('Date: '. gmdate('D, d M Y H:i:s') . ' GMT');
	header("ETag: \"" . $fileEtag . "\"");
	header("Connection: Keep-Alive");
	header("Keep-Alive: timeout=15, max=97");
	header("ETag: \"" . $fileEtag . "\"");
	//if($ext != "SWF")
//		header("Expires: " . gmdate('D, d M Y H:i:s', time() + (60*20)) . " GMT"); // Make it not expire for 20 mintues to cut down on "304 requests"
	header("Cache-Control: private"); 
	header("Pragma: private");
	
	if(session_id() != "")
		session_write_close();
	

	/* Example of a Flash file being cached off of the Server.  Extracted with a Network Analyzer.
	HTTP/1.1 304 Not Modified
	Date: Thu, 08 Nov 2007 14:16:03 GMT
	Server: Apache/1.3.37 (Unix) mod_auth_passthrough/1.8 mod_log_bytes/1.2 mod_bwlimited/1.4 FrontPage/5.0.2.2635.SR1.2 mod_ssl/2.8.28 OpenSSL/0.9.7a
	Connection: Keep-Alive, Keep-Alive
	Keep-Alive: timeout=15, max=97
	ETag: "5d0081-879f-4629b4aa"
	*/

}
else if (isset($headers['If-Modified-Since']) && ereg(gmdate('D, d M Y H:i:s', $timeOfFile), $headers['If-Modified-Since']) && $allowCachingFlag){
		
	header('HTTP/1.1 304 Not Modified');
	header('Date: '. gmdate('D, d M Y H:i:s') . ' GMT');
	header("ETag: \"" . $fileEtag . "\"");
	header("Connection: Keep-Alive");
	header("Keep-Alive: timeout=15, max=97");
	header("ETag: \"" . $fileEtag . "\"");
//	if($ext != "SWF")
//		header("Expires: " . gmdate('D, d M Y H:i:s', time() + (60*20)) . " GMT"); // Make it not expire for 20 mintues to cut down on "304 requests"
	header("Cache-Control: private"); 
	header("Pragma: private");
	
	if(session_id() != "")
		session_write_close();
	 

	/* Example of a Flash file being cached off of the Server.  Extracted with a Network Analyzer.
	HTTP/1.1 304 Not Modified
	Date: Thu, 08 Nov 2007 14:16:03 GMT
	Server: Apache/1.3.37 (Unix) mod_auth_passthrough/1.8 mod_log_bytes/1.2 mod_bwlimited/1.4 FrontPage/5.0.2.2635.SR1.2 mod_ssl/2.8.28 OpenSSL/0.9.7a
	Connection: Keep-Alive, Keep-Alive
	Keep-Alive: timeout=15, max=97
	ETag: "5d0081-879f-4629b4aa"
	*/

}
else{


	

	
	/* Example of a PNG Image being downloaded from the server.  Extracted with a Network Analyzer.
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

	/* Example of an HTML file being downloaded and chunked.   However, chunking mostly useful for dynamic content.  Extracted with a Network Analyzer. 
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
	
	// build an array of partial file name matches that will cause our server to try and search for data vars.
	// We don't want to initialize session (causing database locking) and search/replace unless it is necessary. 
	// This array would find matches on things like project_options.js, options_control.js, etc.
	$shouldRunJsFileThroughSessionFlag = false;
	$javascriptFilesWithDataVarArr = array("options");
	
	if($ext == "JS"){
		foreach($javascriptFilesWithDataVarArr as $thisFilePatternMatch){
			if(preg_match("/".preg_quote($thisFilePatternMatch)."/", $fileNameToOpen)){
				$shouldRunJsFileThroughSessionFlag = true;
				break;
			}
		}
		
		// Keep people from viewing the JS source files before they obfuscate.
		// For Exampe: api_dot_clear.js should not be accessable from the live server... but api_dot.js is OK.
		if(preg_match("/_clear/i", $fileNameToOpen) && !Constants::GetDevelopmentServer()){
			WebUtil::print404Error();
		}
	}
	
	if(in_array($ext, array("HTM", "HTML", "STAGING")) || $shouldRunJsFileThroughSessionFlag){
		
		
		WebUtil::InitializeSession();
		
		// If the user has BING cashback, then the page can't be cached... the cookie could be expired.
		$affiliateSource = WebUtil::GetSessionVar("AffiliateSource", WebUtil::GetCookie("AffiliateSource"));
		if($affiliateSource == "cashbackShopping")
			$allowCachingFlag = false;
			
    
		
		$dataFile = fopen( $fileNameToOpen, "r" );

		if(!$dataFile){
			WebUtil::print404Error();
		}
		
		
		// Keep people from viewing our Source Templates
		if(preg_match("/\-template(_(\d|\w)*)?.html/i", $fileNameToOpen) && !Constants::GetDevelopmentServer()){
			WebUtil::print404Error();
		}

		
		$dynamicFlatFile = fread ($dataFile, filesize ($fileNameToOpen));
		fclose ($dataFile);
		
	
		// Server Side includes can include commands... such as {COM:Authenticate}
		// Variables can be extracted from URL's... like http://domain.com/myDynamicFile.html?vars=ProductID:23^UserID:3423  (separated by colon: and carrot^ symbols).
		// ... and thene variables will be replaced in file such as {VAR:ProductID}
		// Data variables like ... {DATA:SessionID}
		$dynamicFlatFile = ServerSideIncludes::processServerSideIncludes($dynamicFlatFile);
		
		if(preg_match("/\{DATA:(\w+)\}/", $dynamicFlatFile))
			$dataVariablesExist = true;
		else
			$dataVariablesExist = false;
			
		if(preg_match("/\{COM:(\w+)\}/", $dynamicFlatFile))
			$commandExists = true;
		else
			$commandExists = false;
		
		// Get the size of the file after the substitutions.
		$sizeOfFile = strlen($dynamicFlatFile);
		
	
		header('Date: '. gmdate('D, d M Y H:i:s') . ' GMT');
		
		// Since the Variable Substiution is Dynamic, make sure that the Date Modified is always the time that the file was requested.
		header('Last-Modified: '. gmdate('D, d M Y H:i:s', time()) . ' GMT');
		header("ETag: \"" . $fileEtag . "\"");
		header("Accept-Ranges: bytes");
		header("Content-Length: ".$sizeOfFile);
		header("Keep-Alive: timeout=15, max=97");
		header("Connection: Keep-Alive");
		header("Content-Type: " . $mimeTypeOfFile);
		
	
		// If there is no dynamic substitution within the file (and it is not a Flash File)... then make the file expire in 20 minutes.
		// We want to cut down on "304 requests"... even if the file has not changed.
		if($allowCachingFlag && !$commandExists && !$dataVariablesExist && $ext != "SWF"){
			header("Expires: " . gmdate('D, d M Y H:i:s', time() + (60*20)) . " GMT"); 
			header("Cache-Control: private"); 
			header("Pragma: private"); 
		}
		else{
			// If there are any Data Variables, Command Variables, or pased in Variables, then we can't cache the page (even with a 304 Request). 
			// So set the expiration to a date in the past.
			if(!$allowCachingFlag || $commandExists || $dataVariablesExist){
				header("Expires: " . gmdate('D, d M Y H:i:s', (time() - 50000)) . " GMT");
				header("Cache-Control: no-store"); 
				header("Pragma: private"); 
			}
		}

			
		if(session_id() != "")
			session_write_close();
	
		// Chunk the output in order to reduct TCP re-transmissions (because of errors).
		WebUtil::echobig($dynamicFlatFile);
	}
	else{
	
		$dataFile = fopen( $fileNameToOpen, "r" );

		if(!$dataFile){
			WebUtil::print404Error();
		}
		
		header('Date: '. gmdate('D, d M Y H:i:s') . ' GMT');
		header('Last-Modified: '. gmdate('D, d M Y H:i:s', $timeOfFile) . ' GMT');
		header("ETag: \"" . $fileEtag . "\"");
		header("Accept-Ranges: bytes");
		header("Content-Length: ".$sizeOfFile);
		header("Keep-Alive: timeout=15, max=97");
		header("Connection: Keep-Alive");
		header("Content-Type: " . $mimeTypeOfFile);
		if($allowCachingFlag && $ext != "SWF"){
			header("Cache-Control: max-age=" . (60*20));
			header("Expires: " . gmdate('D, d M Y H:i:s', time() + (60*20)) . " GMT"); // Make it not expire for 20 mintues to cut down on "304 requests"
		}
		else{
			header("Cache-Control: private"); 
			header("Pragma: private"); 
		}
	
		if(session_id() != "")
			session_write_close();
	
		while (!feof($dataFile)) {
			$buffer = fgets($dataFile, 8192);
			echo $buffer;
		}

		fclose($dataFile);
	}
}













?>
