<?

require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();


$identify = WebUtil::GetInput("identify", FILTER_SANITIZE_STRING_ONE_LINE);
$dpi = WebUtil::GetInput("dpi", FILTER_SANITIZE_INT);


$user_sessionID =  WebUtil::GetSessionID();


// Allow 7 mintues for uploading.
set_time_limit(420);
ini_set("memory_limit", "512M");


// This should have been set right before the editor loaded. We use it to tell us where the image should be saved to. --#
// The session variable "editor_View" for uploading images has been depreciated from what it was meant for
// Now we will always upload images into the "imagessession" table... and possibly move them over when "saving" the artwork
// For now we will just use the variable to determine if the session has expired.
$editorView = WebUtil::GetSessionVar( "editor_View");

if(empty($editorView))
	WarningMessage("Your session may have expired due to inactivity.");

if(!isset($_FILES["imagefile"]["size"]) || empty($_FILES["imagefile"]["size"]))
	WarningMessage("You forgot to choose a file for uploading.");

if(filesize($_FILES["imagefile"]["tmp_name"]) <= 0)
	WarningMessage("The file type you attempted to upload appears to be empty.");


// Open the file from POST data 
$filedata = fread(fopen($_FILES["imagefile"]["tmp_name"], "r"), filesize($_FILES["imagefile"]["tmp_name"]));


if($_FILES["imagefile"]["size"] > 15728640)
	WarningMessage("The maximum file size you are permitted to upload is 15 Megabytes. You attempted to upload an image that was " . round($_FILES["imagefile"]["size"]/1024/1024,2) . " MB. Generally it is not necessary to upload an image this large.  Ensure that your DPI setting is not too high.  If necessary, break your image into pieces.");



// This will take the file data and insert the Image into the DB.  
// If the image is a PDF or EPS file, it will insert the Vector Image into the DB as well as a rasterized version.
// The function will return a hash with 2 possible Image ID's.. and a possible error message in case the conversion/insertion did not succeed.
$imageInsertHash = ImageLib::InsertRasterAndVectorImageIntoDB($dbCmd, "projectssession", $filedata, $_FILES["imagefile"]["name"], $dpi, $identify);


if(!empty($imageInsertHash["ErrorMsg"]))
	WarningMessage($imageInsertHash["ErrorMsg"]);


// Create a SWF file for the Raster Image.
ImageLib::WriteMINGswfToDisk($dbCmd, $imageInsertHash["RasterImageID"]);



VisitorPath::addRecord("Image Uploaded");


print "<html>\n<script>\n";

//In this case tell the SWF object the ID that was just uploaded
//Otherwise the SWF object needs to POLL the server frequently to find the ID
if($identify == "")
	print "window.opener.InsertImage(\"" . $imageInsertHash["RasterImageID"]. "\", \"" . $imageInsertHash["VectorImageID"]  ."\")";

print "\n self.close()";

print "</script>\n</html>\n";





// Will sending an error message back to the user and exit this script
function WarningMessage($message){

	global $identify;
	
	VisitorPath::addRecord("Image Upload Error", $message);
	
	// If this variable is set within the URL, then it means we are dealing with 1-way communication
	if($identify == ""){
		print "<html>\n<script>\nwindow.opener.WarningMessage('" . addslashes($message) . "');";
		print "\nself.close();\n\n</script>\n</html>";
	}
	else{
		global $dbCmd;
		global $user_sessionID;
		
		$insertArr["SID"] = $user_sessionID;
		$insertArr["UploadIdentify"] = $identify;
		$insertArr["ErrorMessage"] = $message;
		$dbCmd->InsertQuery("imageerrors",  $insertArr);
		
		// Close the pop up window... let the SWF object POLL the database frequently to find the error message.
		print "<html>\n<script>\n\nself.close();\n</script>\n</html>";
	}
	
	exit;
}



?>