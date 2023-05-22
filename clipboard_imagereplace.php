<?

require_once("library/Boot_Session.php");



// Allow 7 mintues for uploading.
set_time_limit(420);
ini_set("memory_limit", "512M");

$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_general);
$UserID = $AuthObj->GetUserID();

$view = WebUtil::GetInput("view", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$projectid = WebUtil::GetInput("projectid", FILTER_SANITIZE_INT);
$sidenumber = WebUtil::GetInput("sidenumber", FILTER_SANITIZE_INT);
$returl = WebUtil::GetInput("returl", FILTER_SANITIZE_URL);
$layerid = WebUtil::GetInput("layerid", FILTER_SANITIZE_INT);
$saveimage = WebUtil::GetInput("saveimage", FILTER_SANITIZE_STRING_ONE_LINE);
$uploadtype = WebUtil::GetInput("uploadtype", FILTER_SANITIZE_STRING_ONE_LINE);

$returl = WebUtil::FilterURL($returl);

// There shoudl only be 1 layer ID... so clean off the pipe symbol that is used for separating multiple ID's
$layerid = preg_replace("/\|/", "", $layerid);



if($view != "proof" && $view != "saved" && $view != "template_category" && $view != "template_searchengine")
	throw new Exception("Problem with the view type in the URL");


ProjectBase::EnsurePrivilagesForProject($dbCmd, $view, $projectid);




$t = new Templatex(".");

$t->set_file("origPage", "clipboard_imagereplace.html");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

$ErrorMessage = "";

$t->set_var(array(
	"PROJECTID"=>$projectid,
	"VIEW"=>$view,
	"SIDENUMBER"=>$sidenumber,
	"LAYERID"=>$layerid,
	"ERROR"=>$ErrorMessage,
	"RETURL"=>$returl,
	"RETURL_ENCODED"=>urlencode($returl)
	));




if(!empty($saveimage)){

	if(!isset($_FILES["imagefile"]["size"]) || empty($_FILES["imagefile"]["size"]))
		WarningMessage("You forgot to choose a file for uploading.");

	if(filesize($_FILES["imagefile"]["tmp_name"]) <= 0)
		WarningMessage("The file type you attempted to upload appears to be empty.");


	// Open the file from POST data 
	$filedata = fread(fopen($_FILES["imagefile"]["tmp_name"], "r"), filesize($_FILES["imagefile"]["tmp_name"]));


	if($_FILES["imagefile"]["size"] > 6100000)
		WarningMessage("The maximum file size you are permitted to upload is 6 Megabytes. You attempted to upload an image that was " . round($_FILES["imagefile"]["size"]/1024/1024,2) . " MB. Generally it is not necessary to upload an image this large.  Ensure that your DPI setting is not too high.  If necessary, break your image into pieces.");




	$ArtworkXMLstring = ArtworkLib::GetArtXMLfile($dbCmd, $view, $projectid);
	$ArtworkInfoObj = new ArtworkInformation($ArtworkXMLstring);

	if(!preg_match("/^\d+$/", $sidenumber) || $sidenumber > sizeof($ArtworkInfoObj->SideItemsArray))
		throw new Exception("Problem with the Side Number");

	
	$artworkDPI = $ArtworkInfoObj->SideItemsArray[$sidenumber]->dpi;
	


	// This will take the file data and insert the Image into the DB.  
	// If the image is a PDF or EPS file, it will insert the Vector Image into the DB as well as a rasterized version.
	// The function will return a hash with 2 possible Image ID's.. and a possible error message in case the conversion/insertion did not succeed.
	$imageInsertHash = ImageLib::InsertRasterAndVectorImageIntoDB($dbCmd, "projectssession", $filedata, $_FILES["imagefile"]["name"], $artworkDPI, "");


	if(!empty($imageInsertHash["ErrorMsg"]))
		WarningMessage($imageInsertHash["ErrorMsg"]);



	// Now replace the image on the artwork document
	// Loop through all of the layers and replace the ImageID's
	foreach($ArtworkInfoObj->SideItemsArray[$sidenumber]->layers as $LayerCounter => $LayerObj){
		if($LayerObj->level == $layerid){

			// Replace the image ID with the new one
			$ArtworkInfoObj->SideItemsArray[$sidenumber]->layers[$LayerCounter]->LayerDetailsObj->imageid = $imageInsertHash["RasterImageID"];
			$ArtworkInfoObj->SideItemsArray[$sidenumber]->layers[$LayerCounter]->LayerDetailsObj->vector_image_id = $imageInsertHash["VectorImageID"];
		
			// If the upload type is "default" then we want to use the New Images default heigh/width, instead of the current layer properties
			if($uploadtype == "default"){
			
				$imageDimensions = ImageLib::GetDimensionsFromRasterImageID($dbCmd, $imageInsertHash["RasterImageID"]);
			
				// The artwork file stores dimensions at 96 dpi... we need to figure out the height and width based upon our DPI
				$ScaleRatio = 96 / $ArtworkInfoObj->SideItemsArray[$sidenumber]->dpi;

				$ArtworkInfoObj->SideItemsArray[$sidenumber]->layers[$LayerCounter]->LayerDetailsObj->width = round($imageDimensions["Width"] * $ScaleRatio, 5);
				$ArtworkInfoObj->SideItemsArray[$sidenumber]->layers[$LayerCounter]->LayerDetailsObj->height = round($imageDimensions["Height"] * $ScaleRatio, 5);
				$ArtworkInfoObj->SideItemsArray[$sidenumber]->layers[$LayerCounter]->LayerDetailsObj->originalwidth = round($imageDimensions["Width"] * $ScaleRatio, 5);
				$ArtworkInfoObj->SideItemsArray[$sidenumber]->layers[$LayerCounter]->LayerDetailsObj->originalheight = round($imageDimensions["Height"] * $ScaleRatio, 5);

			}
		
		}
	}
	// Record into the Project History if this is a project that has been ordered.
	if(in_array($view, array("proof", "admin", "projectsordered", "customerservice")))
		ProjectHistory::RecordProjectHistory($dbCmd, $projectid, "Clipboard: Image Replaced", $UserID);
	

	// Now Store the Artwork File back in the DB 
	ArtworkLib::SaveArtXMLfile($dbCmd, $view, $projectid, $ArtworkInfoObj->GetXMLdoc());

	ThumbImages::CreateThumnailImage($dbCmd, $projectid, $view);
	
	// If this is a Project ordered and it is linked to a "saved project"... then update the image on the saved project too.
	if($view == "proof" || $view == "projectsordered" || $view == "admin")
		ProjectOrdered::CloneOrderForSavedProject($dbCmd, $projectid);

	
	// Moves the replaced image from the "session" table into the "saved" table
	ArtworkLib::SaveImagesInSession($dbCmd, $view, $projectid, ImageLib::GetImagesSavedTableName($dbCmd), ImageLib::GetVectorImagesSavedTableName($dbCmd));
		
	// Now redirect them back to the clipboard and make the parent reload the page
	header("Location: " . WebUtil::FilterURL($returl) . "&reloadparent=true");
	exit;

}


$t->pparse("OUT","origPage");


function WarningMessage($TheMessage){
	global $t;
	
	$t->set_var("ERROR", $TheMessage);
	
	$t->pparse("OUT","origPage");
	
	exit;
}

?>