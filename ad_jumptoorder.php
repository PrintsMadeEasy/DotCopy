<?

require_once("library/Boot_Session.php");


$projectrecord = "";
$artworkrecord = "";
$mainOrderID = "";


$orderno = WebUtil::GetInput("orderno", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);	
$projectrecord = WebUtil::GetInput("projectorderid", FILTER_SANITIZE_INT);	
$command = WebUtil::GetInput("command", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);	

$domainObj = Domain::singleton();

$dbCmd = new DbCmd();



//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


$linkPage = "./ad_project.php";
$linkPageOrder = "./ad_order.php";
$linkProof = "./ad_proof_artwork.php";


$orderQryObj = new OrderQuery($dbCmd);
$limitToProductIDarr = $orderQryObj->getProductLimit();
$limitToProductQuery = "";
if(!empty($limitToProductIDarr )){
	
	$limitToProductQuery .= " AND (";
	
	foreach($limitToProductIDarr as $x)

		$limitToProductQuery .= " ProductID = $x OR";
		
	// Chop off the last "OR"
	$limitToProductQuery = substr($limitToProductQuery, 0, -2);
	
	$limitToProductQuery .= " ) ";
}

// Translate Paypal 3D... number into regular and then still check if it is valid.
if(preg_match("/^3d\d+$/i", $orderno)){
	$orderno = Order::getOrderIdByThirdPartyInvoiceId($orderno);
}

if(preg_match("/^a\d+$/i", $orderno)){
	$artworkrecord = substr($orderno, 1);
	$projectrecord = $artworkrecord;
}
else if(preg_match("/^p\d+$/i", $orderno)){
	$projectrecord = substr($orderno, 1);
}
else if(preg_match("/^\d+$/", $orderno)){
	$mainOrderID = $orderno;
}

$artworkrecord = intval($artworkrecord);
$projectrecord = intval($projectrecord);
$mainOrderID = intval($mainOrderID);

$domainWhereClause = DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs());
$projectDomainWhereClause = DbHelper::getOrClauseFromArray("PO.DomainID", $domainObj->getSelectedDomainIDs());


// Ensure integrity
if(!empty($projectrecord)){

	// Make sure that a record exists --#
	$dbCmd->Query("SELECT COUNT(*) from projectsordered where ID=" . intval($projectrecord) . " AND $domainWhereClause");
	if($dbCmd->GetValue() == 0){
		WebUtil::PrintAdminError("No project was found");
	}
}
else if(!empty($mainOrderID)){

	// Make sure that a record exists
	$dbCmd->Query("SELECT COUNT(*) FROM orders where ID=" . intval($mainOrderID) . " AND $domainWhereClause");
	if($dbCmd->GetValue() == 0){
		WebUtil::PrintAdminError("No order was found.");
	}
}
else{
	WebUtil::PrintAdminError("The Order Number was not received in the URL properly.");
}



// If no command was sent.. then they are trying to jump directly to the order
if($command == ""){
	
	if(!empty($artworkrecord))
		header("Location: ". $linkProof . "?projectid=$projectrecord");
	else if(!empty($projectrecord))
		header("Location: ". $linkPage . "?projectorderid=$projectrecord");
	else if(!empty($mainOrderID))
		header("Location: ". $linkPageOrder . "?orderno=$mainOrderID");
	
	exit;
	
}
else{
	// Ensure integrity of the command name
	if($command != "nextproject" && $command != "previousproject" && $command != "nextorder" && $command != "previousorder" && $command != "nextproof" && $command != "previousproof" && $command != "nextartwork" && $command != "previousartwork"){
		WebUtil::PrintAdminError("The command is invalid.");
	}
	
	
	// Originally the query was doing a Less than or Greater clause againse the ID column (with a LIMIT 1).
	// However, as the database started to grow this query became very sluggish.  It apears the database isn't just looking for the first record that is LESS than or GREATER than.
	// .... Instead it is loading the entire Result set into memory and then just returning the first one.  To make things go quicker... I am doing a BETWEEN statement, so that there is a smaller result set in memory.
	

	// If they are a vendor then limit the projects to their UserID
	// Also limit vendors so that they do not see something which has not been proofed
	if($AuthObj->CheckIfBelongsToGroup("VENDOR")){	
		if($command == "nextproject")
			$query = "SELECT ID FROM projectsordered AS PO WHERE (ID BETWEEN " . ($projectrecord + 1) . " AND ". ($projectrecord + 100000) . ") AND (" . Product::GetVendorIDLimiterSQL($UserID) . ") AND Status!='N' $limitToProductQuery AND $projectDomainWhereClause ORDER BY ID ASC LIMIT 1";
		else if($command == "previousproject")
			$query = "SELECT ID FROM projectsordered AS PO  WHERE (ID BETWEEN " . ($projectrecord - 100000) . " AND " . ($projectrecord - 1) . ") AND (" . Product::GetVendorIDLimiterSQL($UserID) . ") AND Status!='N' $limitToProductQuery AND $projectDomainWhereClause ORDER BY ID DESC LIMIT 1";
		else if($command == "nextorder")
			$query = "SELECT DISTINCT orders.ID FROM orders INNER JOIN projectsordered AS PO  ON orders.ID = PO.OrderID WHERE (orders.ID BETWEEN " . ($mainOrderID + 1) . " AND " . ($mainOrderID + 100000) . ") AND (" . Product::GetVendorIDLimiterSQL($UserID) . ") AND PO.Status!='N' AND $projectDomainWhereClause ORDER BY orders.ID ASC LIMIT 1";
		else if($command == "previousorder")
			$query = "SELECT DISTINCT orders.ID FROM orders INNER JOIN projectsordered AS PO  ON orders.ID = PO.OrderID WHERE (orders.ID BETWEEN " . ($mainOrderID - 100000) . " AND " . ($mainOrderID - 1) . ") AND (" . Product::GetVendorIDLimiterSQL($UserID) . ") AND PO.Status!='N' AND $projectDomainWhereClause ORDER BY orders.ID DESC LIMIT 1";
	}
	else{
		if($command == "nextproject"){
			$query = "SELECT ID FROM projectsordered AS PO WHERE (ID BETWEEN " . ($projectrecord + 1) . " AND " . ($projectrecord + 100000) . ") $limitToProductQuery AND $projectDomainWhereClause ORDER BY ID ASC LIMIT 1";
		}
		else if($command == "nextproof"){
			// make sure that we are only looking at new projects --#
			$query = "SELECT ID FROM projectsordered AS PO WHERE (ID BETWEEN " . ($projectrecord + 1) . " AND " . ($projectrecord + 100000) . ") AND  Status='N' $limitToProductQuery AND $projectDomainWhereClause ORDER BY ID ASC LIMIT 1";
		}
		else if($command == "nextartwork"){
			// make sure that we are only looking at new projects --#
			$query = "SELECT ID FROM projectsordered AS PO WHERE (ID BETWEEN " . ($projectrecord + 1) . " AND " . ($projectrecord + 100000) . ") $limitToProductQuery AND $projectDomainWhereClause ORDER BY ID ASC LIMIT 1";
		}
		else if($command == "previousproject"){
			$query = "SELECT ID FROM projectsordered AS PO WHERE (ID BETWEEN " . ($projectrecord - 100000) . " AND " . ($projectrecord - 1) . ") $limitToProductQuery AND $projectDomainWhereClause ORDER BY ID DESC LIMIT 1";
		}
		else if($command == "previousproof"){
			// make sure that we are only looking at new projects --#
			$query = "SELECT ID FROM projectsordered AS PO WHERE (ID BETWEEN " . ($projectrecord - 100000) . " AND " . ($projectrecord - 1) . ") AND Status='N' $limitToProductQuery AND $projectDomainWhereClause ORDER BY ID DESC LIMIT 1";
		}
		else if($command == "previousartwork"){
			// make sure that we are only looking at new projects --#
			$query = "SELECT ID FROM projectsordered AS PO WHERE (ID BETWEEN " . ($projectrecord - 100000) . " AND " . ($projectrecord - 1) . ") $limitToProductQuery AND $projectDomainWhereClause ORDER BY ID DESC LIMIT 1";
		}
		else if($command == "nextorder"){
			$query = "SELECT ID FROM orders WHERE (ID BETWEEN " . ($mainOrderID + 1) . " AND " . ($mainOrderID + 100000) . ") AND $domainWhereClause ORDER BY ID ASC LIMIT 1";
		}
		else if($command == "previousorder"){
			$query = "SELECT ID FROM orders WHERE (ID BETWEEN " . ($mainOrderID - 100000) . " AND " . ($mainOrderID - 1) . ") AND $domainWhereClause ORDER BY ID DESC LIMIT 1";
		}
		
		
	}


	$dbCmd->Query($query);


	if($dbCmd->GetNumRows() == 0){
		WebUtil::PrintAdminError("That was the last item.");
	}

	$JumpProjectID = $dbCmd->GetValue();


	// Find out if we are jumping to an order, a project, or a proof
	if($command == "nextproject" || $command == "previousproject")
		header("Location: ". $linkPage . "?projectorderid=$JumpProjectID");
	else if($command == "nextproof" || $command == "previousproof" || $command == "nextartwork" || $command == "previousartwork")
		header("Location: ". $linkProof . "?projectid=$JumpProjectID");
	else if($command == "nextorder" || $command == "previousorder")
		header("Location: ". $linkPageOrder . "?orderno=$JumpProjectID");

}



?>