<?

require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();


$gangRunID = WebUtil::GetInput("gangRunID", FILTER_SANITIZE_INT);
$fileDescType = WebUtil::GetInput("fileDescType", FILTER_SANITIZE_STRING_ONE_LINE);




//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


// Some funny things can happen with sending binary files to the browser if you don't close the session first.
// This is true as of PHP 4.3 & 5.2 ... read user comments at PHP regarding this function.
session_write_close();


$thumbPhoto = null;
$dateLastModified = time();


if(!empty($gangRunID)){
	
	$gangRunObj = new GangRun($dbCmd);
	$gangRunObj->loadGangRunID($gangRunID);
		
	$thumbPhoto = null;
		
	if($fileDescType == "Front")
		$thumbPhoto =& $gangRunObj->getFrontThumbnail();
	else if($fileDescType == "Back")
		$thumbPhoto =& $gangRunObj->getBackThumbnail();

	$dateLastModified = $gangRunObj->getLastUpdated();
}	




if(empty($thumbPhoto)){
	$defaultThumbGif = Constants::GetWebserverBase() . "/images/thumbnail_notAvailable.gif"; 
	$fd = fopen ($defaultThumbGif, "r");
	$thumbPhoto = fread ($fd, filesize ($defaultThumbGif));
	fclose ($fd);
}



header('Accept-Ranges: bytes');
header("Content-Length: ". strlen($thumbPhoto));
header("Connection: close");
header("Content-Type: image/jpeg");
header("Last-Modified: " . date("D, d M Y H:i:s", $dateLastModified) . " GMT");
header("Cache-Control: store, cache");

print $thumbPhoto;


?>