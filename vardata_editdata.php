<?

require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();




$projectrecord = WebUtil::GetInput("projectrecord", FILTER_SANITIZE_INT);
$editorview = WebUtil::GetInput("editorview", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$returnurl = WebUtil::GetInput("returnurl", FILTER_SANITIZE_URL);
$forwardDesc = WebUtil::GetInput("forwardDesc", FILTER_SANITIZE_STRING_ONE_LINE);

$returnurl = WebUtil::FilterURL($returnurl);

$curentURL = "vardata_editdata.php?projectrecord=" . $projectrecord . "&editorview=" . $editorview . "&returnurl=" . urlencode($returnurl) . "&forwardDesc=" . urlencode($forwardDesc);

$user_sessionID =  WebUtil::GetSessionID();


ProjectBase::EnsurePrivilagesForProject($dbCmd, $editorview, $projectrecord);


$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, $editorview, $projectrecord);

if(!$projectObj->isVariableData())
	WebUtil::PrintError("This is not a variable data project.");



if($editorview == "customerservice"){
	if(!in_array($projectObj->getStatusChar(), array("N", "P", "W", "H")))
		WebUtil::PrintError("You can not edit your Data File after the product has been printed.");
}

// If the quantity of this Product is greater than 1,000 then the Grid will be to large to load into memory.
if($projectObj->getQuantity() > 1000){

	$curentURL = preg_replace("/vardata_editdata/", "vardata_modify", $curentURL);

	header("Location: " . WebUtil::FilterURL($curentURL));
	exit;
}




$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
if($action){


	if($action == "VariableDataTips"){

		// This Name/Value pair tells us if we should hide or show the tips for variable data.
		// Default is to show the tips.  Set the parameter in a permanent cookie on the user's machine.
		$display = WebUtil::GetInput("display", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

		WebUtil::OutputCompactPrivacyPolicyHeader();
		$cookieTime = time()+60*60*24*90; // 3 months

		if($display == "show")
			setcookie ("VariableDataTips", "show", $cookieTime);
		else if($display == "hide")
			setcookie ("VariableDataTips", "hide", $cookieTime);
		else
			throw new Exception("Error setting the VariableData Tips preference.");

	}
	else
		throw new Exception("Undefined Action");


	// If there is Data File error... don't go through the extra work of parsing the Data file and error checking 
	if($projectObj->getVariableDataStatus() != "D"){
		VariableDataProcess::SetVariableDataStatusForProject($dbCmd, $projectrecord, $editorview);
		
		// Will refresh object in case the funciton call above changed anything
		$projectObj->loadByProjectID($projectrecord);
	}
		
	
	
	// In case the it is Saved... this will update the Shoppingcart, etc.
	ProjectOrdered::ProjectChangedSoBreakOrCloneAssociates($dbCmd, $editorview, $projectrecord);
	
	
	header("Location: " . WebUtil::FilterURL($returnurl));
	exit;

}




// If their is a login error for the Variable Data project we want to automatically check every time the page loads.  If they are logged in it will recalculate the status
if($projectObj->getVariableDataStatus() == "L")
	VariableDataProcess::SetVariableDataStatusForProject($dbCmd, $projectrecord, $editorview);


$t = new Templatex();

$t->set_file("origPage", "vardata_editdata-template.html");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());
	

$t->set_var("CONTINUE_LINK", $returnurl);
$t->set_var("CONTINUE_LINK_ENCODED", urlencode($returnurl));
$t->set_var("PROJECTRECORD", $projectrecord);
$t->set_var("VIEWTYPE", $editorview);
$t->set_var("FORWARDDESC_ENCODED", urlencode($forwardDesc));
$t->set_var("CURRENTURL_ENCODED", urlencode($curentURL));
$t->set_var("CONTINUE_URL_HTML", WebUtil::htmlOutput($returnurl));



$customerIDFromOrder = null;

if($editorview == "saved"){
	$t->set_var("CONTINUE_DESC", "Return to Saved Projects Without Saving");
	
	//The user ID that we want to use for the Saved Project might belong to somebody else;
	$AuthObj = new Authenticate(Authenticate::login_general);	

	$dateModified = $projectObj->getDateLastModified();
	
	if(sizeof(ShoppingCart::GetProjectSessionIDsInShoppingCartThatAreSaved($dbCmd, $projectrecord, $user_sessionID)) == 0)
		$t->discard_block("origPage", "SavedProjectIsInShoppingCartBL");
	
	$t->discard_block("origPage", "ShoppingCartDesignIsAlsoSaved");
	
}
else if($editorview == "projectssession" || $editorview == "session"){

	// We may override what the link says within the URL
	if($forwardDesc)
		$t->set_var("CONTINUE_DESC", $forwardDesc);
	else
		$t->set_var("CONTINUE_DESC", "Return to Shopping Cart Without Saving");
	

	$dateModified = $projectObj->getDateLastModified();
	
	if(!ProjectSaved::CheckIfProjectIsSaved($dbCmd, $projectrecord))
		$t->discard_block("origPage", "ShoppingCartDesignIsAlsoSaved");
	
	$t->discard_block("origPage", "SavedProjectIsInShoppingCartBL");

}
else if($editorview == "customerservice"){
	$t->set_var("CONTINUE_DESC", "Return to Order History Without Saving");
	
	$dateModified = "";

	
	$t->discard_block("origPage", "ShoppingCartDesignIsAlsoSaved");
	$t->discard_block("origPage", "SavedProjectIsInShoppingCartBL");
	$t->discard_block("origPage", "AddVariableImagesLinkBL");

}
else if($editorview == "proof"){
	$t->set_var("CONTINUE_DESC", "Close This Window");
	
	$dateModified = "";

	
	$t->discard_block("origPage", "ShoppingCartDesignIsAlsoSaved");
	$t->discard_block("origPage", "SavedProjectIsInShoppingCartBL");
	
	
	// Since we are in Proof mode... Figure out what the Customer ID is
	$customerIDFromOrder = Order::GetUserIDFromOrder($dbCmd, $projectObj->getOrderID());
	
	$t->discard_block("origPage", "AddVariableImagesLinkBL");

}
else{
	throw new Exception("Illegal View Type in vardata_modify.php");
}




// Show or Hide the Variable Data tips ... based upon a cookie that is set on their computer.  Default is to show the tips.
$displayTipsPref = WebUtil::GetCookie( "VariableDataTips", "hide" );
if($displayTipsPref == "hide")
	$t->discard_block("origPage", "DataTipsDisplayedBL");
else
	$t->discard_block("origPage", "DataTipsHiddenBL");


// So javascript can know if a PDF with merge is possible.  Only "G"ood or "W"arning
if($projectObj->getVariableDataStatus() == "W" || $projectObj->getVariableDataStatus() == "G")
	$t->set_var("ERROR_FLAG", "false");
else
	$t->set_var("ERROR_FLAG", "true");



VisitorPath::addRecord("Variable Data (Edit Manually)");

$t->pparse("OUT","origPage");




?>