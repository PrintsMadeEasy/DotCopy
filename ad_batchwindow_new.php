<?

require_once("library/Boot_Session.php");





$dbCmd = new DbCmd();


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);

$UserID = $AuthObj->GetUserID();




$timestamp = WebUtil::GetInput("timestamp", FILTER_SANITIZE_INT);
$batch_command = WebUtil::GetInput("batch_command", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$viewtype = WebUtil::GetInput("viewtype", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$priority = WebUtil::GetInput("priority", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$projectlist = WebUtil::GetInput("projectlist", FILTER_SANITIZE_STRING_ONE_LINE);


$userDomainIDsArr = $AuthObj->getUserDomainsIDs();

// Put javascript commands into this variable that we want to execute on load of the page--#
$javascriptStr = "";


// This will contain all of Project numbers that are in the batch.
// The listbox won't do anything, it is just a convienient way to display a whole list.
$ListBoxHTML = "<select onChange='jumpToProjectPage(this.value);'>\n<option value=''>Batch list</option><option value=''>--------------</option>";

// Will contain the string for the SQL where clause.  Each project ID listed between OR... Example..  WHERE projectsordered.ID = 234 OR projectsordered.ID = 235 OR projectsordered.ID = 236
$SQL_Project_list_where_clause = "";

$masterProjectListArr = split("\|", $projectlist);
$projectIDarr = array();
$uniqueProductIDsArr = array();

foreach($masterProjectListArr as $projectID){


	if(!preg_match("/^\d+$/", $projectID))
		continue;
		
	// Skip over projects which are not in their selected domain pool... To make sure they possibly figure out other Profile Names from another domain.
	// It is also a Security check.
	$domainIDofOrder = Order::getDomainIDFromOrder(Order::GetOrderIDfromProjectID($dbCmd, $projectID));
	if(!in_array($domainIDofOrder, $userDomainIDsArr))
		continue;
	
	$projectIDarr[] = $projectID;
	
	$SQL_Project_list_where_clause .= " projectsordered.ID=$projectID OR";

	$ListBoxHTML .= "<option value='$projectID'>P$projectID</option>\n";
	
	$uniqueProductIDsArr[] = ProjectBase::getProductIDquick($dbCmd, "ordered", $projectID);

}

$ListBoxHTML .= "</select>\n";


$uniqueProductIDsArr = array_unique($uniqueProductIDsArr);




// Now Build a list of PDF profiles Names that are in common for all of the Products.
// First start off with every single profile name for every single product.
// Then we will go through each product... and intersect each group of profile names... each time the list will keep decreasing.
$profileNamesInterectArr = array();

foreach($uniqueProductIDsArr as $thisProductID){
	$productionProductID = Product::getProductionProductIDStatic($dbCmd, $thisProductID);
	$profileObj = new PDFprofile($dbCmd, $productionProductID);
	$profileNamesInterectArr = array_merge($profileNamesInterectArr, $profileObj->getProfileNames());
}

$profileNamesInterectArr = array_unique($profileNamesInterectArr);

foreach($uniqueProductIDsArr as $thisProductID){
	$productionProductID = Product::getProductionProductIDStatic($dbCmd, $thisProductID);
	$profileObj = new PDFprofile($dbCmd, $productionProductID);
	$profileNamesInterectArr = array_intersect($profileNamesInterectArr, $profileObj->getProfileNames());
}



// Make the drop down list of PDF profile names... that are in commong with all of the Products..
$PDFviewOptionSelectHTML = "";
foreach($profileNamesInterectArr as $thisProfileName) 
	$PDFviewOptionSelectHTML .= "<option value='" . WebUtil::htmlOutput($thisProfileName) . "'>" . WebUtil::htmlOutput($thisProfileName) . "</option>\n";



// Make the Drop down List for Gang PDF profiles
$allProfileNamesArr = SuperPDFprofile::getAllProfileNames($dbCmd, $userDomainIDsArr);
$GangProfilesSelectHTML = "";
foreach($allProfileNamesArr as $profileID => $profileName) 
	$GangProfilesSelectHTML .= "<option value='$profileID'>".WebUtil::htmlOutput($profileName)."</option>\n";



// Now lets look and see if there is anything in the WHERE caluse
// If no projects exist then make the WHERE clause something ridiculous so that we can ensure no matches.
if($SQL_Project_list_where_clause == ""){
	$SQL_Project_list_where_clause = " WHERE projectsordered.ID=9000000000";
}
else{
	// Trim of the last 2 characters which is just the letters "OR"
	$SQL_Project_list_where_clause = " WHERE " . substr($SQL_Project_list_where_clause, 0, -2);
}





// Now perform batch commands ... which will change the Order Status
if(!empty($batch_command)){
	
	WebUtil::checkFormSecurityCode();

	foreach($projectIDarr as $CurrentProjectID){
	
		
		// Skip over projects which are not in their available domain pool
		$domainIDofOrder = Order::getDomainIDFromOrder(Order::GetOrderIDfromProjectID($dbCmd, $CurrentProjectID));
		if(!in_array($domainIDofOrder, $userDomainIDsArr))
			continue;
		
		$CurStatus = ProjectOrdered::GetProjectStatus($dbCmd, $CurrentProjectID);
	
		// We don't want people to change the status on a mailing batch because there could be a lot of money associated with Postage, etc.  There is not an easy way to issolate a Project once it has been grouped with others, printed, etc.
		// The only exception is when we mark a project a "printed".
		if(MailingBatch::checkIfProjectBatched($dbCmd, $CurrentProjectID) && ($batch_command != "T" || $CurStatus != "Y"))
			WebUtil::PrintErrorPopUpWindow("You can not change the status on P" . $CurrentProjectID . ".  It has already been included within a Mailing Batch.  If you really need to change the status then cancel the Mailing Batch first.");

		//If there project is already finished... or canceled... then we should not be able to change the status.
		if($CurStatus == "F" || $CurStatus == "C")
			continue;
			
		//Don't allow vendors to take orders off of hold.
		if($CurStatus == "H" && $AuthObj->CheckIfBelongsToGroup("VENDOR"))
			continue;


		//If we are using a batch command to change the list to boxed... then we must verify that the order has a printed status
		//If not, it is possible that the shop could change to "boxed" from "new" and really screw things up.
		if($batch_command == "B"){
			if($CurStatus <> "T")
				continue;
		}
		
		// You can't change something to Proofed/Offset Printing unless ad PDF has been generated
		if(($batch_command == "P" || $batch_command == "D") && !ProjectOrdered::CheckIfBleedSettingsAreSaved($dbCmd, $CurrentProjectID))
			continue;
		
		// You can't changed to proofed if there is a variable data Error
		if($batch_command == "P" && $CurStatus == "V")
			continue;
		
		ProjectOrdered::ChangeProjectStatus($dbCmd, $batch_command, $CurrentProjectID);
		
		ProjectHistory::RecordProjectHistory($dbCmd, $CurrentProjectID, $batch_command, $UserID);

	}



	$javascriptStr = "window.opener.location = window.opener.location;";
}


if(!empty($priority)){

	if($priority != "U" && $priority != "N")
		throw new Exception("Illegal priority");

	foreach($projectIDarr as $CurrentProjectID){
		$dbCmd->Query("UPDATE projectsordered SET Priority='$priority' WHERE ID=$CurrentProjectID");
	}

	$javascriptStr = "window.opener.location = window.opener.location;";

}



// Find out when this Batch window was created.
// If this is a new window then get the current time.  Otherwise the timestamp is coming in the URL
if($timestamp == "")
	$timestamp = time();



$t = new Templatex(".");


$t->set_file("origPage", "ad_batchwindow_new-template.html");

$t->set_var("PROJECTLIST", $projectlist);

$t->set_var("LISTBOX", $ListBoxHTML);
$t->allowVariableToContainBrackets("LISTBOX");


$t->set_var("PDFPROFILES", $PDFviewOptionSelectHTML);
$t->allowVariableToContainBrackets("PDFPROFILES");


$t->set_var("GANGPROFILES", $GangProfilesSelectHTML);
$t->allowVariableToContainBrackets("GANGPROFILES");

$t->set_var("PROJECTLIST", $projectlist);
$t->set_var("PROJECT_LIST_WHERE_CLAUSE", $SQL_Project_list_where_clause);
$t->set_var("JAVASCRIPT_STR", $javascriptStr);
$t->set_var("VIEWTYPE", $viewtype);
$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());



$t->set_var("USERID", $UserID);
$t->set_var("TIMESTAMP", $timestamp);


$BatchWindowCreated = date("M j, g:i a", $timestamp);


$t->set_var("TITLE", "<b>Window Created:</b> $BatchWindowCreated &nbsp;&nbsp;&nbsp;<b>Total Marked:</b> " .  sizeof($projectIDarr) . "&nbsp;&nbsp;" . $ListBoxHTML );
$t->allowVariableToContainBrackets("TITLE");


// For now, don't let vendors see any of the other contols becuase it might seem a bit confusing
// The multiple PDF docs and Label sheets have been depcrediated with API and other automation features
if($AuthObj->CheckIfBelongsToGroup("VENDOR")){

	$t->discard_block("origPage","adminBL");
	$t->discard_block("origPage","MiniLabelsBL");
	$t->discard_block("origPage","InvoicesBL");
}
else{
	$t->discard_block("origPage","fulfillmentBL");
}

// Customer Service shouldn't be able to generate files for the printing press
if(!$AuthObj->CheckForPermission("PDF_BATCH_GENERATION")){
	$t->discard_block("origPage", "SingleFilePDFGenerationBL");
	$t->discard_block("origPage","MiniLabelsBL");
	$t->discard_block("origPage","MultiplePDFbl");
	$t->discard_block("origPage","InvoicesBL");
	$t->discard_block("origPage","GangPDFGenerationBL");
}


if(!$AuthObj->CheckForPermission("MAILING_BATCHES_CREATE"))
	$t->discard_block("origPage", "MailingBatchBL");

if(!$AuthObj->CheckForPermission("ORDER_LIST_CSV"))
	$t->discard_block("origPage", "OrderListCsvBL");


	


$t->pparse("OUT","origPage");



?>