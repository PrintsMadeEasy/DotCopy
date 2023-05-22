<?

require_once("library/Boot_Session.php");


$projectid = WebUtil::GetInput("projectid", FILTER_SANITIZE_INT);
$numbercolors = WebUtil::GetInput("numbercolors", FILTER_SANITIZE_INT);
$pixelswide = WebUtil::GetInput("pixelswide", FILTER_SANITIZE_INT);
$pixelshigh = WebUtil::GetInput("pixelshigh", FILTER_SANITIZE_INT);
$identify = WebUtil::GetInput("identify", FILTER_SANITIZE_STRING_ONE_LINE);
$bleedpixels = WebUtil::GetInput("bleedpixels", FILTER_SANITIZE_INT);
$editorview = WebUtil::GetInput("editorview", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$dpi = WebUtil::GetInput("dpi", FILTER_SANITIZE_INT);




$dbCmd = new DbCmd();


ProjectBase::EnsurePrivilagesForProject($dbCmd, $editorview, $projectid);


$t = new Templatex();


if($editorview != "template_searchengine" && $editorview != "template_category"){
	$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, $editorview, $projectid);
	
	
	// If the user is looking at a Saved Project, then check if there is a Saved Project Override.
	// Make sure the path to the templates is coming from the Domain of the User. 
	// We may be looking at the saved projects from another domain in the URL.
	if($projectObj->getViewTypeOfProject() == "saved"){
		
		//The user ID that we want to use for the Saved Project might belong to somebody else;
		$AuthObj = new Authenticate(Authenticate::login_general);
		$UserIDofOverride = ProjectSaved::GetSavedProjectOverrideUserID($AuthObj);
		
		if($UserIDofOverride != $AuthObj->GetUserID()){
			$domainIDofUser = UserControl::getDomainIDofUser($UserIDofOverride);
			Domain::enforceTopDomainID($domainIDofUser);
			$t->setSandboxPathByDomainID($domainIDofUser);
		}
	}
	
}


$pixelswide += $bleedpixels * 2; 
$pixelshigh += $bleedpixels * 2; 




$ClientInfo = new UserAgent();



$t->set_file("origPage", "upload_image-template.html");


// If the numbercolors parameter is 0 then this artwork is unlimited colors
// In which case we want to delete the information block about the limited colors and resize the window with a javascript command.

if($numbercolors == 0){
	$t->discard_block("origPage", "limitedColorBL");
}
else{
	if($numbercolors == 1)
		$t->set_var(array("COLORS"=>"1 color"));
	else
		$t->set_var(array("COLORS"=>$numbercolors . " colors"));
}



// If there is a marker Image on the artwork then we don't want to display a message about the Scrap Area.
$xmlDocument = ArtworkLib::GetArtXMLfile($dbCmd, $editorview, $projectid);
$ArtworkInfoObj = new ArtworkInformation($xmlDocument);
foreach($ArtworkInfoObj->SideItemsArray as $sideObj){
	if($sideObj->markerimage){
		$t->discard_block("origPage", "ScrapAreaBL");
		break;
	}
}


// If the DPI is already set at 300 DPI then don't give them the option to switch to 300
// Also if a project has variable images we must print the design at 200 DPI for performance reasons, don't give them an option to upgrade to 300dpi
if($editorview != "template_searchengine" && $editorview != "template_category"){
	if($dpi == "300" || $projectObj->hasVariableImages())
		$t->discard_block("origPage", "300DPI_BL");
}


$t->set_var("PROJECTID", $projectid);  //Not using this any more
$t->set_var("DPI", $dpi);
$t->set_var("PIXELS_HIGH", round($pixelshigh));
$t->set_var("PIXELS_WIDE", round($pixelswide));
$t->set_var("IDENTIFY", $identify);
$t->set_var("SCRAP_AREA", round($bleedpixels * 2));


$t->pparse("OUT","origPage");


?>