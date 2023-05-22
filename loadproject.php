<?

require_once("library/Boot_Session.php");


$savedprojectid = WebUtil::GetInput("savedprojectid", FILTER_SANITIZE_INT);	
$InitializeCoupon = WebUtil::GetInput("InitializeCoupon", FILTER_SANITIZE_STRING_ONE_LINE);
$projectorderid = WebUtil::GetInput("projectorderid", FILTER_SANITIZE_INT);
$usertemplate = WebUtil::GetInput("usertemplate", FILTER_SANITIZE_INT);   //If we want to load a tempalte from another persons saved Projects... this is the UserID that the template belongs to
$identify = WebUtil::GetInput("identify", FILTER_SANITIZE_STRING_ONE_LINE);  //If !empty($usertemplate) then it means $identify must match the description of one of the users saved projects
$location = WebUtil::GetInput("location", FILTER_SANITIZE_URL);  //This parameter will tell where to direct the user after loading the project
$keywords = WebUtil::GetInput("keywords", FILTER_SANITIZE_STRING_ONE_LINE);  //In case the location is a search engine... then these will be the keywords we are searching on.
$multisavedprojects = WebUtil::GetInput("multisavedprojects", FILTER_SANITIZE_STRING_ONE_LINE);  //Will be a pipe delimited string of project ID's



if (empty($savedprojectid) && empty($projectorderid) && empty($usertemplate) && empty($multisavedprojects))
	WebUtil::PrintError("There was an error with the URL.");



$dbCmd = new DbCmd();





$user_sessionID =  WebUtil::GetSessionID();


// If we are intializing a coupon... then make sure it sticks for the user when the hit the checkout screen
if(!empty($InitializeCoupon)){
	$CheckoutParamsObj = new CheckoutParameters();
	$CheckoutParamsObj->SetCouponCode($InitializeCoupon);
}


// Find out if we should grab the project from the Saved Project or from the Ordered Projects
if(!empty($savedprojectid)){

	ProjectBase::EnsurePrivilagesForProject($dbCmd, "saved", $savedprojectid);
	
	$ProjectObj = ProjectBase::getProjectObjectByViewType($dbCmd, "saved", $savedprojectid);
	
	// Copy the Saved Project data into the new Project Session
	$newProjectSessionObj = new ProjectSession($dbCmd);
	$newProjectSessionObj->copyProject($ProjectObj);
	$newProjectID = $newProjectSessionObj->createNewProject($user_sessionID);

	// Record information into the "shoppingcart table"
	$dbCmd->InsertQuery("shoppingcart",  array("SID"=>$user_sessionID, "ProjectRecord"=>$newProjectID, "DateLastModified"=>date("YmdHis"), "DomainID"=>Domain::oneDomain()));

	// Instead of generating a new thumbnail, let's just quickly copy it over, since it is the same
	ThumbImages::CopyProjectThumbnail($dbCmd, "projectssaved", $savedprojectid, "projectssession", $newProjectID);

	
	VisitorPath::addRecord("Project Loaded From Saved Project");
	
	session_write_close();
	header("Location: ./shoppingcart.php?nocache=" . time(), true, 302);
	exit;
}
else if(!empty($projectorderid)){

	ProjectBase::EnsurePrivilagesForProject($dbCmd, "ordered", $projectorderid);
	
	$ProjectObj = ProjectBase::getProjectObjectByViewType($dbCmd, "ordered", $projectorderid);

	// Copy the Ordered Project data into the new Project Session
	$newProjectSessionObj = new ProjectSession($dbCmd);
	$newProjectSessionObj->copyProject($ProjectObj);
	$newProjectID = $newProjectSessionObj->createNewProject($user_sessionID);
	
	// Record information into the "shoppingcart table"
	$dbCmd->InsertQuery("shoppingcart",  array("SID"=>$user_sessionID, "ProjectRecord"=>$newProjectID, "DateLastModified"=>date("YmdHis"), "DomainID"=>Domain::oneDomain()));

	$transferaddress = "./artwork_update.php?projectrecord=$newProjectID&viewtype=session&redirect=" . urlencode("shoppingcart.php?action=updatesessionprojectsthumbnail&projectrecord=$newProjectID&returnurl=shoppingcart.php&nocache=" . time());
	
	VisitorPath::addRecord("Project Loaded From Previous Order");
	
	session_write_close();
	header("Location: " . WebUtil::FilterURL($transferaddress), true, 302);
	exit;
	
}
else if(!empty($usertemplate)){

	// Load a Saved Project belonging to another user... based upon the Saved Project Notes.
	$ProjectObj = ProjectSaved::GetProjectObjFromSavedNotes($dbCmd, $usertemplate, $identify);
	
	// Copy the Saved Project data into the new Project Session
	$newProjectSessionObj = new ProjectSession($dbCmd);
	$newProjectSessionObj->copyProject($ProjectObj);

	// Record that this project was extracted from a user's saved projects
	$newProjectSessionObj->setFromTemplateArea("U");
	$newProjectSessionObj->setFromTemplateID($usertemplate);

	// Make sure that there is no SavedID link... because it did not come from their account	
	$newProjectSessionObj->setSavedID(0);

	$newProjectID = $newProjectSessionObj->createNewProject($user_sessionID);

	// Instead of generating a new thumbnail, let's just quickly copy it over, since it is the same
	ThumbImages::CopyProjectThumbnail($dbCmd, "projectssaved", $usertemplate, "projectssession", $newProjectID);


	// Set this session variable.  We will check to make sure it gets set on the following page.  If we cant find it then the person might not have cookies enabled.
	$HTTP_SESSION_VARS['initialized'] = 1;

	if($location == "choosetemplate")
		$transferaddress = "./templates.php?projectrecord=" . $newProjectID . "&productid=" . $newProjectSessionObj->getProductID();
	else if($location == "categories")
		$transferaddress = "./templates.php?projectrecord=" . $newProjectID . "&categorytemplate=0&productid=" . $newProjectSessionObj->getProductID();
	else if($location == "searchengine")
		$transferaddress = "./templates.php?projectrecord=" . $newProjectID . "&categorytemplate=0&keywords=" . urlencode($keywords) . "&productid=" . $newProjectSessionObj->getProductID();
	else
		$transferaddress = "./edit_artwork.php?editorview=projectssession&projectrecord=" . $newProjectID . "&nocache=" . time();

		
	VisitorPath::addRecord("Project Loaded From a User Template");
	
	session_write_close();
	header("Location: " . WebUtil::FilterURL($transferaddress), true, 302);
	exit;
}
else if(!empty($multisavedprojects)){

	//Make sure they are logged in.
	$AuthObj = new Authenticate(Authenticate::login_general);

	//The user ID that we want to use for the Saved Project might belong to somebody else;
	$UserID = ProjectSaved::GetSavedProjectOverrideUserID($AuthObj);

	//This var is a piple delimeted string of Saved project ID's.  We are going to load 
	$SavedProjectIDarr = split("\|", $multisavedprojects);
	
	foreach($SavedProjectIDarr as $ThisSavedProjectID){
	
		//Skip blank spaces etc.
		if(!preg_match("/^\d+$/", $ThisSavedProjectID))
			continue;
		
		if(!ProjectSaved::CheckIfUserOwnsSavedProject($dbCmd, $ThisSavedProjectID,$UserID))
			WebUtil::PrintError("On of the projects that you are trying to load is invalid.");


		$ProjectObj = ProjectBase::getProjectObjectByViewType($dbCmd, "saved", $ThisSavedProjectID);

		// Copy the Saved Project data into the new Project Session
		$newProjectSessionObj = new ProjectSession($dbCmd);
		$newProjectSessionObj->copyProject($ProjectObj);

		$newProjectID = $newProjectSessionObj->createNewProject($user_sessionID);

		// Instead of generating a new thumbnail, let's just quickly copy it over, since it is the same
		ThumbImages::CopyProjectThumbnail($dbCmd, "projectssaved", $ThisSavedProjectID, "projectssession", $newProjectID);

		// Record information into the "shoppingcart table"
		$dbCmd->InsertQuery("shoppingcart",  array("SID"=>$user_sessionID, "ProjectRecord"=>$newProjectID, "DateLastModified"=>date("YmdHis"), "DomainID"=>Domain::oneDomain()));
	}

	session_write_close();
	header("Location: ./shoppingcart.php?nocache=" . time(), true, 302);
	exit;
}
else{
	WebUtil::PrintError("Error with the URL.");
}





?>