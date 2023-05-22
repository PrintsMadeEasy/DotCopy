<?

require_once("library/Boot_Session.php");


$projectlist = WebUtil::GetInput("projectlist", FILTER_SANITIZE_STRING_ONE_LINE);
$orderid = WebUtil::GetInput("orderid", FILTER_SANITIZE_INT);
$viewtype = WebUtil::GetInput("viewtype", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);






$dbCmd = new DbCmd();


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_general);
$UserID = $AuthObj->GetUserID();
	

if($viewtype == "fulfillment" || $viewtype == "admin"){
	$AuthObj->EnsureMemberSecurity();
}
else if($viewtype == "customerservice"){
	if(empty($orderid))
		WebUtil::PrintError("The order ID is missing.");
}
else{
	WebUtil::PrintError("There is a problem with the URL.");
}




if(!empty($orderid)){
	
	$orderid = intval($orderid);
	
	if(!Order::CheckIfUserHasPermissionToSeeOrder($orderid))
		WebUtil::PrintError("The order ID does not exist.");

	if(!Order::CheckForActiveProjectWithinOrder($dbCmd, $orderid))
		WebUtil::PrintError("An invoice can not be generated for this order because it has been canceled.");
		
	VisitorPath::addRecord("Customer Receipt");
}



if(!empty($projectlist)){
		
	$AuthObj->EnsureMemberSecurity();
	
	// Make sure that the person is able to see all of the Projects in the list.
	$projectlistArr = array();
	$tempArr = explode("|", $projectlist);
	foreach($tempArr as $thisPID){
		$thisPID = trim($thisPID);
		if(empty($thisPID))
			continue;
		$projectlistArr[] = intval($thisPID);
	}
		

	if(sizeof($projectlistArr) > 200){
		WebUtil::WebmasterError("This person tried to generate more than 200 invoices at a single time: U$UserID : " . UserControl::GetNameByUserID($dbCmd, $UserID) . " : P" . implode(" P", $projectlistArr));
		WebUtil::PrintError("You are not allowed to generate more than 200 invoices at a time.");	
	}
	

	$dbCmd->Query("SELECT DISTINCT DomainID FROM projectsordered WHERE " . DbHelper::getOrClauseFromArray("ID", $projectlistArr));
	$allDomainIDsOfProjectsArr = $dbCmd->GetValueArr();
	
	$userDomainIDsArr = $AuthObj->getUserDomainsIDs();
	
	foreach($allDomainIDsOfProjectsArr as $thisDomainIDofProject){
		if(!in_array($thisDomainIDofProject, $allDomainIDsOfProjectsArr))
			WebUtil::PrintError("An invoice can not be generated because one of the ProjectIDs was incorrect.");
	}
	
	
	// Try to make sure that someone isn't trying to gernerate a bunch of invoices in the past to harvest the user database.
	// One invoice at a time is OK, from the Customer Service order History of the Admin order screen.
	
	$dbCmd->Query("SELECT DISTINCT OrderID FROM projectsordered WHERE " . DbHelper::getOrClauseFromArray("ID", $projectlistArr));
	$allOrderIDs = $dbCmd->GetValueArr();
	if(sizeof($allOrderIDs) > 2){
		
		$dbCmd->Query("SELECT COUNT(*) FROM orders WHERE " . DbHelper::getOrClauseFromArray("ID", $allOrderIDs) . " AND DateOrdered < DATE_ADD(NOW(), INTERVAL -30 DAY )");
		$oldInvoiceCount = $dbCmd->GetValue();
		
		if($oldInvoiceCount > 0){
			WebUtil::WebmasterError("This person tried to generate some old invoices: U$UserID : " . UserControl::GetNameByUserID($dbCmd, $UserID) . " : P" . implode(" P", $projectlistArr));
			WebUtil::PrintError("The Project IDs are incorrect with some of the invoices that you are trying to generate.");	
		}
	}
		
}
else{
	$projectlistArr = array();
}


if(empty($projectlistArr) && empty($orderid))
	WebUtil::PrintError("No projects were selected for invoice generation.");


	
	





// Make this script be able to run for at least 5 minutes
set_time_limit(300);



// Generate the invoice... which writes the PDF file to disk.  Then the function returns the file name.
$InvoiceFileName = Invoice::GenerateInvoices($dbCmd, $orderid, $projectlistArr, true);

// Redirect to the Temporary PDF document
// A cron job will need to delete the proofs every 2 hours or so to keep the disk from getting full
header("Location: " . DomainPaths::getPdfWebPathOfDomainInURL() . "/" . $InvoiceFileName);



?>