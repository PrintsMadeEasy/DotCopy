<?

require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();


$userSessionID = WebUtil::GetSessionID();




$id = WebUtil::GetInput("id", FILTER_SANITIZE_INT);
$projecttype = WebUtil::GetInput("projecttype", FILTER_SANITIZE_STRING_ONE_LINE);
$sc = WebUtil::GetInput("sc", FILTER_SANITIZE_STRING_ONE_LINE);


//  Get the security check in the DB to see if they match --#
if($projecttype == "projectssession" || $projecttype == "session"){
	$dbCmd->Query("SELECT SID FROM projectssession WHERE ID=" . intval($id) . " AND DomainID=" . Domain::getDomainIDfromURL());
	
	// We are trying to depreciate all view types to "session", "ordered", and "saved".
	$projecttype = "projectssession";
}
else if($projecttype == "FirstShoppingCartItem"){
	
	// Get the first project out of the users shopping cart.
	$dbCmd->Query("SELECT ProjectRecord FROM shoppingcart WHERE SID=\"".$userSessionID."\" AND shoppingcart.DomainID=".Domain::getDomainIDfromURL()." order by shoppingcart.ID ASC LIMIT 1");
	
	// In case they don't have an item in their shopping cart, show a 1x1 transparent pixel
	if($dbCmd->GetNumRows() == 0){
		header("Content-Type: image/gif");
		header("Content-Length: 49");
		header("Cache-Control: no-cache, must-revalidate");
		header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past so that the browser always accesses the tracking script.
		session_write_close();
		
		print pack('H*', '47494638396101000100910000000000ffffffffffff00000021f90405140002002c00000000010001000002025401003b');
		exit;
	}
	
	$sc = $userSessionID;
	$id = $dbCmd->GetValue();
}
else if($projecttype == "projectssaved" || $projecttype == "saved"){
	

	// We are trying to depreciate all view types to "session", "ordered", and "saved".
	$projecttype = "projectssaved";
	
	$dbCmd->Query("Select UserID FROM projectssaved WHERE ID=$id");
}
else{
	throw new Exception("No project type was given");
}
$SecurityCheck = $dbCmd->GetValue();




// The Security check for saved projects is the MD5 of the UserID plus some Extra Salt to ensure it is unique (otherwise you could just do an MD5 revserse lookup on the number).
if($projecttype == "projectssaved")
	$SecurityCheck = md5($SecurityCheck . Constants::getGeneralSecuritySalt());

// If we are doing a security check by the person's first shopping cart tiem... make sure to change the Project Type going forward to "Project Session"
if($projecttype == "FirstShoppingCartItem"){
	$SecurityCheck = $userSessionID;
	$projecttype = "projectssession";
}
	
//  Make sure that a malicious user can't steel thumbnail photos that don't belong to them
if($SecurityCheck != $sc)
	throw new Exception("We have recorded your activity. You are not allowed to see this.");



// Will feth the binary data of the Thumbnail image (if a thumbnail exists) .... otherwise gets back a blank string.
$ThumbnailPhoto =& ThumbImages::GetProjectThumbnailImage($dbCmd, $projecttype, $id);

// Get the date the thumbnail was last updated
$DateLastModified = ThumbImages::GetProjectThumbnailLastUpdated($dbCmd, $projecttype, $id);




// Just Return a 1 pixel white Image if there is no thumbnail. That way it won't show up as a broken link in the browser for some reason.
if(empty($ThumbnailPhoto)){

	$fd = fopen (Constants::GetWebserverBase() . "/images/whitepixel.jpg", "r");
	$ThumbnailPhoto = fread ($fd, 5000000);
	fclose ($fd);
}

// Close the Session as soon as possible.  We already got the Session ID.
// Waiting for the user to finish downloading the data could cause lots of database locks.
session_write_close();




// Close the connection... because with lots of thumbnails on the page with persistent connections it can cause funny problems.
header('Accept-Ranges: bytes');
header("Content-Length: ". strlen($ThumbnailPhoto));
header("Connection: close");
header("Content-Type: image/jpeg");
header("Last-Modified: " . date("D, d M Y H:i:s", $DateLastModified) . " GMT");
header("Cache-Control: store, cache");


print $ThumbnailPhoto;


?>