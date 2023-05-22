<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();




// Make this script be able to run for at least 4 hours for a large upload
set_time_limit(14400);


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_general);


//The user ID that we want to use for the Saved Project might belong to somebody else;
$theUserID = ProjectSaved::GetSavedProjectOverrideUserID($AuthObj);


// Make sure the path to the templates is coming from the Domain of the User. We may be looking at the saved projects from another domain in the URL.
if($theUserID != $AuthObj->GetUserID()){
	$domainIDofUser = UserControl::getDomainIDofUser($theUserID);
	Domain::enforceTopDomainID($domainIDofUser);
}


// If the Customer ID is alwasy coming in the URL.. but it should match the User ID of ther person whos is logged in
// So make sure they have admin rights for the image.
$customerUserID = WebUtil::GetInput("customerid", FILTER_SANITIZE_INT);

if($customerUserID != $theUserID){
	
	$AuthObj->EnsureMemberSecurity();
	if(!$AuthObj->CheckForPermission("EDIT_ARTWORK"))
		throw new Exception("Permission Denied");

	$theUserID = $customerUserID;
}
else{
	$theUserID = $customerUserID;
}

$UserControlObj = new UserControl($dbCmd);
if(!$UserControlObj->LoadUserByID($theUserID, TRUE))
	throw new Exception("The User ID does not exist.");



$variablesImagesObj = new VariableImages($dbCmd, $theUserID);


$fileCount = $_POST["FileCount"];


$rotationCounter = 0;

//process received files	
for($i=1;$i<=$fileCount;$i++)
{
	$imageName = "SourceFile_" . $i;
	$size=$_FILES[$imageName]['size']; 
	$fileName = $_FILES[$imageName]['name'];
	$tempName = $_FILES[$imageName]['tmp_name'];
	$mimeType = $_FILES[$imageName]['type'];


	
	if($size > 0 && $size < (1024 * 1024)) 
	{ 	

		
		// This will convert whatever image it is into a JPEG or a PNG
		// If the file is not correct, for whatever reason, then we will silently skip over the image without throwing an error.
		// Default to 200 dpi in case they are uploading Vector based files.  That is an acceptable print resolution.  We don't want variable images to be too large anyway.
		$imageConvertError = ImageLib::ConvertImageAfterUpload($tempName, 200);
		
		if($imageConvertError != "")
			continue;
		
		$newImageFormat = ImageLib::GetImageFormatFromFile($tempName);
		
		if($newImageFormat == "JPEG"){
			$mimeType = "image/pjpeg";
		}
		else if($newImageFormat == "PNG"){
			$mimeType = "image/x-png";
		}
		else{
			WebUtil::WebmasterError("Error in the script vardata_saveimages.php.  The file format did not convert correctly.");
			throw new Exception("The file format did not convert correctly.");
		}
	
		$imageDim = ImageLib::GetDimensionsFromImageFile($tempName);
		if(empty($imageDim["Width"]) || empty($imageDim["Height"])){
			WebUtil::WebmasterError("Error in the script vardata_saveimages.php. The Image Dimensions Failed.");
			throw new Exception("Error with Image Dimensions.");
		}
		
		
		// People may upload very large Images.  There is no point in storing them this way in our database
		// It will take too long to generate the PDF files when it has to resize them down.
		// There is no reason to have a variable image exceed 900 Pixels in Width or Height.   That is plenty to cover up half of a postcard at 200 DPI
		if($imageDim["Width"] > 900 || $imageDim["Height"] > 900){
		
			// Resize down (proportionally) to a max height or a max width of 900
			$resizeCommand = Constants::GetUpperLimitsShellCommand(80, 20) . Constants::GetPathToImageMagick() . "mogrify -quality 80 -geometry 900x900 " . $tempName;
			system($resizeCommand);
			
			// Get the new dimensions
			$imageDim = ImageLib::GetDimensionsFromImageFile($tempName);
		}
		
	
		$fd = fopen ($tempName, "r");
		$binaryData = fread ($fd, filesize ($tempName));
		fclose ($fd);

		$variablesImagesObj->insertImage($binaryData, $mimeType, $fileName, $imageDim["Width"], $imageDim["Height"]);
		
		unlink($tempName);
	}
	
	
	$rotationCounter++;
	
	if($rotationCounter > 100){
	
		// If someone were to upload A LOT of variable images at once... it could fill up our binary tables
		// before the cron has a chance to automatically rotate.  So we just do a check every 100 images or so to make sure it is not getting too full.
		TableRotations::PossiblyRotateBinaryTables($dbCmd, "variableimages");
		
		$rotationCounter = 0;
	}

}

print "done";


?>