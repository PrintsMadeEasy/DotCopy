<?

require_once("library/Boot_Session.php");


$projectorderid = WebUtil::GetInput("projectorderid", FILTER_SANITIZE_INT);


$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();



//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

if(!$AuthObj->CheckForPermission("PROJECT_SCREEN"))
	WebUtil::PrintAdminError("Not Available");


	
ProjectBase::EnsurePrivilagesForProject($dbCmd, "ordered", $projectorderid);

$mainorder_ID = Order::GetOrderIDfromProjectID($dbCmd, $projectorderid);


if(!Order::CheckIfUserHasPermissionToSeeOrder($mainorder_ID))
	throw new Exception("This Project number does not exist.");

	
// Make sure that all Objects on this script are using the same Domain ID.
// This will also show the Logo of the Domain that was ordered through in the Nav Bar.
$domainID = Order::getDomainIDFromOrder($mainorder_ID);
Domain::enforceTopDomainID($domainID);
	



$projectObj = ProjectOrdered::getObjectByProjectID($dbCmd, $projectorderid);

$Status = $projectObj->getStatusChar();






$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
if($action){
	
	WebUtil::checkFormSecurityCode();

	if($action == "updatereprintreason"){
		
		$reprintreason = WebUtil::GetInput("reprintreason", FILTER_SANITIZE_STRING_ONE_LINE);		
		
		$projectObj->setRePrintReason($reprintreason);
		$projectObj->updateDatabaseWithRawData();

		header("Location: ./" . WebUtil::FilterURL(WebUtil::GetInput("returl", FILTER_SANITIZE_URL)));
		exit;
	}
	else{
		throw new Exception("Undefined Action");
	}


}





// If this person is a vendor then we want to restrict the projects to them.
if($AuthObj->CheckIfBelongsToGroup("VENDOR"))
	$VendorRestriction = $UserID;
else
	$VendorRestriction = "";


$CurrentURL = "./ad_project.php?projectorderid=" . $projectorderid;
$CurrentURLencoded = urlencode($CurrentURL);


// Keep a record of the visit to this page by the user.
NavigationHistory::recordPageVisit($dbCmd, $UserID, "Project", $projectorderid);


$t = new Templatex(".");

$t->set_file("origPage", "ad_project-template.html");


$t->set_var(array("CURRENTURL"=>$CurrentURL, "CURRENTURL_ENCODED"=>$CurrentURLencoded));

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());



$dbCmd->Query("SELECT users.Name, users.Email, users.Company, users.Address, users.AddressTwo, 
		users.City, users.State, users.Zip,UNIX_TIMESTAMP(orders.DateOrdered) AS DateOrdered, 
		orders.CardType, orders.CardNumber, orders.MonthExpiration, orders.YearExpiration, 
		users.Email, orders.ShippingChoiceID, orders.BillingName, orders.BillingCompany, 
		orders.BillingAddress, orders.BillingAddressTwo, orders.BillingCity, orders.BillingState, 
		orders.BillingZip, orders.BillingCountry, orders.ShippingName, orders.ShippingCompany, 
		orders.ShippingAddress, orders.ShippingAddressTwo, orders.ShippingCity,  orders.ShippingCountry, 
		orders.ShippingState, orders.ShippingZip, users.Phone, users.ID AS UsersID, users.Password 
		FROM users INNER JOIN orders on orders.UserID = users.ID where orders.ID=$mainorder_ID");

$row = $dbCmd->GetRow();


$personsName = $row["Name"];
$personsEmail = $row["Email"];
$companyName = $row["Company"];
$theirAddress = $row["Address"];
$theirAddress2 = $row["AddressTwo"];
$City = $row["City"];
$State = $row["State"];
$Zip = $row["Zip"];
$DateOrdered = $row["DateOrdered"];
$CardType = $row["CardType"];
$CardNumber = $row["CardNumber"];
$MonthExp = $row["MonthExpiration"];
$YearExp = $row["YearExpiration"];
$customer_email = $row["Email"];
$shippingChoiceID = $row["ShippingChoiceID"];
$billing_name = $row["BillingName"];
$billing_company = $row["BillingCompany"];
$billing_address = $row["BillingAddress"];
$billing_address2 = $row["BillingAddressTwo"];
$billing_city = $row["BillingCity"];
$billing_state = $row["BillingState"];
$billing_zip = $row["BillingZip"];
$billing_country = $row["BillingCountry"];
$ShippingName = $row["ShippingName"];
$ShippingCompany = $row["ShippingCompany"];
$ShippingAddress = $row["ShippingAddress"];
$ShippingAddress2 = $row["ShippingAddressTwo"];
$ShippingCity = $row["ShippingCity"];
$ShippingState = $row["ShippingState"];
$ShippingCountry = $row["ShippingCountry"];
$ShippingZip = $row["ShippingZip"];
$UsersPhone = $row["Phone"];
$CustomerID = $row["UsersID"];
$Password = $row["Password"];


$t->set_var(array("CUSTOMER_PROJECTS"=>Order::GetProjectCountByUser($dbCmd, $CustomerID, $VendorRestriction), 
			"CUSTOMER_ID"=>$CustomerID));



if(!empty($ShippingCompany))
	$ShippingDisplay = $ShippingCompany;
else
	$ShippingDisplay = $ShippingName;


$t->set_var(array("CUSTOMER_DISPLAY"=>WebUtil::htmlOutput($ShippingDisplay)));

$DateOrdered = "<b>" . date("D", $DateOrdered) . "</b> - " . date("M j, Y g:i a", $DateOrdered);
$t->set_var(array("DATEORDERED"=>$DateOrdered));
$t->allowVariableToContainBrackets("DATEORDERED");



// A vendor should not have permission to view another vendors order
if($AuthObj->CheckIfBelongsToGroup("VENDOR")){
	if(!in_array($UserID, $projectObj->getVendorID_DB_Arr()))
		WebUtil::PrintAdminError("This Project is not available.");
}



// Set the Ship and Arrival Dates

if(Order::CheckIfProjectShouldShipToday($dbCmd, $projectorderid))
	$dueTodayFlag = "<font class='SmallBody'><b><font color='#ff0000'>Due Today</font></b></font><br>";
else
	$dueTodayFlag = "";

$t->set_var(array(
	"DUETODAY"=>"$dueTodayFlag",
	"EST_SHIP_DATE"=>"<a href='javascript:ShowShedule()' class='BlueRedLink'>" . TimeEstimate::formatTimeStamp($projectObj->getEstShipDate()) . "</a>",
	"EST_ARRIV_DATE"=>"<a href='javascript:ShowShedule()' class='BlueRedLink'>" . TimeEstimate::formatTimeStamp($projectObj->getEstArrivalDate()) . "</a>",
	"SHIP_COUNTRY"=>WebUtil::htmlOutput($ShippingCountry),

	"SHIP_CITY"=>WebUtil::htmlOutput($ShippingCity),
	"SHIP_STATE"=>WebUtil::htmlOutput($ShippingState),
	"SHIP_METHOD"=>ShippingChoices::getHtmlChoiceName($shippingChoiceID)
	
	));


$t->allowVariableToContainBrackets("DUETODAY");
$t->allowVariableToContainBrackets("EST_SHIP_DATE");
$t->allowVariableToContainBrackets("EST_ARRIV_DATE");
$t->allowVariableToContainBrackets("SHIP_METHOD");

	
// Display the Status History for this project.
$t->set_block("origPage","StatusBL","StatusBLout");

$dbCmd->Query("SELECT Note, UNIX_TIMESTAMP(Date) AS Date, UserID FROM projecthistory 
			WHERE ProjectID=$projectorderid");				

while($row = $dbCmd->GetRow()){

	$t->set_var("STATUS",  $row["Note"]);
	$t->set_var("DATE", date("D, M j, Y g:i a", $row["Date"]));
	$t->set_var("NAME", UserControl::GetNameByUserID($dbCmd2, $row["UserID"]));
	$t->allowVariableToContainBrackets("STATUS");
	
	$t->parse("StatusBLout","StatusBL",true);
}

if($dbCmd->GetNumRows() == 0)
	$t->set_var(array("StatusBLout"=>"<tr><td class='body'>No Status History Yet.</td></tr>"));







// Find out how many projects in this order
$dbCmd->Query("SELECT count(*) FROM projectsordered WHERE OrderID=$mainorder_ID");
$ProjectCount = $dbCmd->GetValue();

if($ProjectCount == 1){
	$t->set_var("JS_MULTIPLE_PROJECTS", "false");
	$t->set_var("NOSETS", "1 project");
}
else{
	$t->set_var("JS_MULTIPLE_PROJECTS", "true");
	$t->set_var("NOSETS", "$ProjectCount projects");
}





$ArtFile = ArtworkLib::GetArtXMLfile($dbCmd, "admin", $projectorderid);
$ArtworkInfoObj = new ArtworkInformation($ArtFile);



$t->set_block("origPage","CanceledBL","CanceledBLout");
if($Status == "C"){
	$t->parse("CanceledBLout","CanceledBL",true);

	// The cancel button should be different depending on if we are doing a re-print
	if($projectObj->getRePrintLink() <> "0")
		$t->set_var(array("ACTIVELABEL"=>"Re-Activate Re-print"));
	else
		$t->set_var(array("ACTIVELABEL"=>"Re-Activate"));
}
else{
	$t->set_var(array("CanceledBLout"=>""));

	// The cancel button should be different depending on if we are doing a re-print
	if($projectObj->getRePrintLink() <> "0")
		$t->set_var(array("ACTIVELABEL"=>"Cancel Re-print"));
	else
		$t->set_var(array("ACTIVELABEL"=>"Cancel Order"));
}





// Get a hash back having all of the order numbers and descriptions
$MultipleOrdersArr = Order::GetMultipleOrdersDesc($dbCmd, $mainorder_ID, $VendorRestriction);

// When there are more than 15 projects don't show the links anymore, instead show an HTML drop down menu
if(sizeof($MultipleOrdersArr) > 1 && sizeof($MultipleOrdersArr) < 15){

	$multiOrdersLinks = Order::GetMultipleOrderLinks($MultipleOrdersArr, $mainorder_ID, $projectorderid, "admin");
	$t->set_var(array("MULTI_ORDERS_LINKS"=>$multiOrdersLinks));
	$t->allowVariableToContainBrackets("MULTI_ORDERS_LINKS");
	
	// Erase the block of HTML for an HTML list menu having a mass amount of projects.
	$t->set_block("origPage","HideDropDownProjectLink","HideDropDownProjectLinkout");
	$t->set_var(array("HideDropDownProjectLinkout"=>""));

}
else if(sizeof($MultipleOrdersArr) > 1){

	// Create the drop down list for the projects 
	$ProjectListDropDownHTML = Order::BuildDropDownForProjectLink($dbCmd, $mainorder_ID, $VendorRestriction, $projectorderid);
	$t->set_var("PROJECT_LIST", $ProjectListDropDownHTML);
	$t->allowVariableToContainBrackets("PROJECT_LIST");
	$t->set_var(array("MULTI_ORDERS_LINKS"=>""));
}
else{
	// Erase the block of HTML for displaying multip projects
	$t->set_block("origPage","multipleOrdersBL","multipleOrdersBLout");
	$t->set_var(array("multipleOrdersBLout"=>""));
}


if(ProjectOrdered::CheckIfBleedSettingsAreSaved($dbCmd, $projectorderid)){

	$productionProductID = Product::getProductionProductIDStatic($dbCmd, $projectObj->getProductID());
	$profileObj = new PDFprofile($dbCmd, $productionProductID);
	
	$pdfChoices = array();
	foreach($profileObj->getProfileNames() as $profileName)
		$pdfChoices[$profileName] = $profileName;
	
	$selectMenu = Widgets::buildSelect($pdfChoices, array(), "pdfprofile");
	
	$t->set_var("PDFPROFILE_DROPDOWN", $selectMenu);
	$t->allowVariableToContainBrackets("PDFPROFILE_DROPDOWN");
	
	$t->discard_block("origPage", "NoPDFbl");
}
else{
	$t->discard_block("origPage", "PDFprofileBL");
}


$t->set_var("PROJECT_RECORD", $projectorderid);


// Set up a flag to say when it is time to chop out a block of HTML.   You can't erase a block of HTML twice.. but you can set the flag twice
$RemoveCancelOrderBlock_flag = false;



if($Status == "F"){
	$t->set_block("origPage","HideStatusChangeBL","HideStatusChangeBLout");
	$t->set_var(array("HideStatusChangeBLout"=>"Completed"));
	
	// don't allow the user to cancel the order at this point..
	$RemoveCancelOrderBlock_flag = true;
	
	$t->discard_block("origPage", "HideManualCompleteBL");
	
}
else if($Status == "C"){
	// Don't allow them to change the status until the un-cancel
	$t->set_block("origPage","HideStatusChangeBL","HideStatusChangeBLout");
	$t->set_var(array("HideStatusChangeBLout"=>"Canceled"));
	
	// If the project is canceled then don't let them move the project into another order.
	$t->set_block("origPage","HideMoveProjectBL","HideMoveProjectBLout");
	$t->set_var(array("HideMoveProjectBLout"=>"&nbsp;"));
	
	$t->discard_block("origPage", "HideManualCompleteBL");
}



// If the project has been split into multiple shipments (not nessarily)...  If any 1 of the shipments have gone then they should not be able to move of cancel this project  
if(Shipment::CheckAnyShipmentsGoneContainingProject($dbCmd, $projectorderid)){


	$t->set_block("origPage","HideMoveProjectBL","HideMoveProjectBLout");
	$t->set_var(array("HideMoveProjectBLout"=>"&nbsp;"));
	
	$RemoveCancelOrderBlock_flag = true;
}


// Check if they have permissions to cancel or move the project
if(!$AuthObj->CheckForPermission("CANCEL_PROJECT"))
	$RemoveCancelOrderBlock_flag = true;


// Check if payment was captured for this order.  If it was then we should not be able to reopen the order
if(Order::CheckForCapturedPaymentWithinOrder($dbCmd, $mainorder_ID))
	$RemoveCancelOrderBlock_flag = true;



if($RemoveCancelOrderBlock_flag){
	$t->set_block("origPage","RemoveFromOrderBL","RemoveFromOrderBLout");
	$t->set_var(array("RemoveFromOrderBLout"=>"&nbsp;"));
	
}

$productID = $projectObj->getProductID();
$productObj = Product::getProductObj($dbCmd, $productID);

$t->set_var("PRODUCT_NAME_EXT", $productObj->getProductTitleWithExtention());


#-- Create an array of shipment methods that will be used to fill up the drop down list --#
$DropDownStatusArray = array();

$allStatusHash = ProjectStatus::GetStatusDescriptionsHash();

foreach($allStatusHash as $thisStatusChar => $statusHash){

	// Choose which options should not be selectable in the array.
	if(in_array($thisStatusChar, array("C", "F")))
		continue;
	
	if(!$productObj->hasMailingService() && in_array($thisStatusChar, array("G", "Y")))
		continue;

	if(!$projectObj->isVariableData() && in_array($thisStatusChar, array("V")))
		continue;

	$DropDownStatusArray[$thisStatusChar] = $statusHash["DESC"];
}



if($Status == "V"){
	$DropDownStatusArray["V"] = "Variable Data Error";
	$t->set_var("STATUS_DISABLED", "disabled");
}
else{
	$t->set_var("STATUS_DISABLED", "");
}
	

$t->set_var("CURRENT_STATUS", $Status);


$StatusDropDownMenu = Widgets::buildSelect($DropDownStatusArray, array($Status));
$t->set_var("STATUS_OPTIONS", $StatusDropDownMenu);
$t->allowVariableToContainBrackets("STATUS_OPTIONS");

$ReprintReasonDropDown = Widgets::buildSelect(Status::GetReprintReasonsHash(), array(""));
$t->set_var("REPRINT_REASON_CHOICES", $ReprintReasonDropDown);
$t->allowVariableToContainBrackets("REPRINT_REASON_CHOICES");

// Find out if there are any messages, if so then we want to change the background color to alert the administrator
if($projectObj->getNotesAdmin() != "")
	$t->set_var(array("MESSAGECOLOR_ADMIN"=>"#FFDDDD"));
else
	$t->set_var(array("MESSAGECOLOR_ADMIN"=>"#EEEEFF"));
if($projectObj->getNotesProduction() != "")
	$t->set_var(array("MESSAGECOLOR_PRODUCTION"=>"#FFDDDD"));
else
	$t->set_var(array("MESSAGECOLOR_PRODUCTION"=>"#EEEEFF"));




$t->set_var(array("PRODUCTTITLE"=>WebUtil::htmlOutput($projectObj->getProductTitleWithExt())));

$t->set_var(array("INFO_TABLE"=>$projectObj->getProjectDescriptionTable("body"), "ADMIN_MESSAGE"=>WebUtil::htmlOutput($projectObj->getNotesAdmin()), "PRODUCTION_MESSAGE"=>WebUtil::htmlOutput($projectObj->getNotesProduction())));

$t->set_var(array("ORDERNO_HASHED"=>Order::GetHashedOrderNo($mainorder_ID), "EMAIL"=>$personsEmail, "MAIN_ORDERID"=>$mainorder_ID));

$t->allowVariableToContainBrackets("INFO_TABLE");




// See if they have permissions to see admin messages
if(!$AuthObj->CheckForPermission("VIEW_ADMIN_MESSAGES")){
	$t->set_block("origPage","AdminMessageBL","AdminMessageBLout");
	$t->set_var("AdminMessageBLout", "");
}



// See if they have permissions to edit the product options 
// They also can not edit if the order is completed or canceled
if(!$AuthObj->CheckForPermission("EDIT_PRODUCT_OPTIONS") || $Status == "F" || $Status == "C"){
	$t->set_block("origPage","HideEditProductOptionsBL","HideEditProductOptionsBLout");
	$t->set_var("HideEditProductOptionsBLout", "");
}


// See if they have permissions proof artwork 
if(!$AuthObj->CheckForPermission("PROOF_ARTWORK")){
	$t->set_block("origPage","ProofArtworkBL","ProofArtworkBLout");
	$t->set_var("ProofArtworkBLout", "");
	
	$t->discard_block("origPage","ClipboardBL");
}

// See if they have permissions to manually edit the artwork
if(!$AuthObj->CheckForPermission("MANUAL_EDIT_ARTWORK")){
	$t->set_block("origPage","ManualEditBL","ManualEditBLout");
	$t->set_var("ManualEditBLout", "");
}



$taskCollectionObj = new TaskCollection();
$taskCollectionObj->limitShowTasksBeforeReminder(true);
$taskCollectionObj->limitUserID($UserID);
$taskCollectionObj->limitAttachedToName("project");
$taskCollectionObj->limitAttachedToID($projectorderid);
$taskCollectionObj->limitUncompletedTasksOnly(true);
$taskObjectsArr = $taskCollectionObj->getTaskObjectsArr();

$tasksDisplayObj = new TasksDisplay($taskObjectsArr);
$tasksDisplayObj->setTemplateFileName("tasks-template.html");
$tasksDisplayObj->setReturnURL($CurrentURL);
$tasksDisplayObj->displayAsHTML($t);		



// Display all of the Re-Print Orders  (if there are any)
$query = "SELECT projectsordered.ID AS ProjectID, projectsordered.CustomerSubtotal, orders.ShippingQuote, 
		UNIX_TIMESTAMP(orders.DateOrdered) AS DateOrdered, 
		projectsordered.RePrintReason, orders.ShippingChoiceID, projectsordered.Status, 
		projectsordered.CustomerDiscount FROM projectsordered 
		INNER JOIN orders ON projectsordered.OrderID = orders.ID 
		WHERE projectsordered.RePrintLink=$projectorderid ";

if(!empty($VendorRestriction))
	$query .= " AND (" . Product::GetVendorIDLimiterSQL($VendorRestriction) . ")";

$query .= " ORDER BY orders.DateOrdered DESC";

$dbCmd->Query($query);


$rowBackgroundColor=false;
$empty_reprints=true;

$t->set_block("origPage","UpdateReprintNoteBL","UpdateReprintNoteBLout");
$t->set_block("origPage","rePrintBl","rePrintBlout");

while ($row = $dbCmd->GetRow()){

	$empty_reprints = false;

	$rePrintID = $row["ProjectID"];
	$rePrint_C_Subtotal = $row["CustomerSubtotal"];
	$rePrint_C_Shipping = $row["ShippingQuote"];
	$rePrint_dateCreated = $row["DateOrdered"];
	$RePrintReason = $row["RePrintReason"];
	$rePrint_ShippingChoiceID = $row["ShippingChoiceID"];
	$rePrint_Status = $row["Status"];
	$rePrint_Discount = $row["CustomerDiscount"];
	$rePrint_dateCreated = date("M j, G:i", $rePrint_dateCreated);
	$rePrint_V_Subtotal = ProjectOrdered::GetVendorSubtotalFromProject($dbCmd2, $rePrintID, $VendorRestriction);

	// othewise give them a link to the reprint page
	$rePrintDesc = "<a class='blueredlink' href='./ad_project.php?projectorderid=$rePrintID'>P" . $rePrintID . "</a>";


	// show strike outs if the re=print order was canceled
	if($rePrint_Status == "C"){
		$t->set_var(array("S_START"=>"<s>"));
		$t->set_var(array("S_END"=>"</s>"));
	}
	else{
		$t->set_var(array("S_START"=>""));
		$t->set_var(array("S_END"=>""));
	}
	
	$t->allowVariableToContainBrackets("S_START");
	$t->allowVariableToContainBrackets("S_END");

	$rePrint_DiscDescription = ceil($rePrint_Discount * 100) . "%";

	$RePrintShipDesc = ShippingChoices::getHtmlChoiceName($rePrint_ShippingChoiceID);

	$t->set_var(array("ID_REPRINT"=>$rePrintID));
	$t->set_var(array("RP_DESC"=>$rePrintDesc));
	$t->allowVariableToContainBrackets("RP_DESC");
	$t->set_var(array("RP_CREATED"=>$rePrint_dateCreated));
	$t->set_var(array("RP_C_SHIP"=>$rePrint_C_Shipping));
	$t->set_var(array("RP_SHIP_M"=>$RePrintShipDesc));
	$t->set_var(array("RP_V_SUB"=>$rePrint_V_Subtotal));
	$t->set_var(array("REPRINT_NOTES"=>WebUtil::htmlOutput(Status::GetReprintReasonString($RePrintReason))));
	$t->set_var(array("RP_C_DISC"=>$rePrint_DiscDescription));
	$t->allowVariableToContainBrackets("RP_SHIP_M");



	// See if they have permission to update the reprint note
	if(!$AuthObj->CheckForPermission("UPDATE_REPRINT_NOTE")){

		//This will erase the block of HTML (which contains the button)
		$t->set_var("UpdateReprintNoteBLout", WebUtil::htmlOutput(Status::GetReprintReasonString($RePrintReason)));

	}
	else{
		//build the drop down for the choices
		$ReprintItemReasonDropDown = Widgets::buildSelect(Status::GetReprintReasonsHash(), array($RePrintReason));
		$t->set_var("REPRINT_ITEM_REASON_CHOICES", $ReprintItemReasonDropDown);
		$t->allowVariableToContainBrackets("REPRINT_ITEM_REASON_CHOICES");

		$t->parse("UpdateReprintNoteBLout","UpdateReprintNoteBL",false);
	}

	$t->parse("rePrintBlout","rePrintBl",true);

}
if($empty_reprints){
	$t->set_block("origPage","EmptyRePrintBL","EmptyRePrintBLout");
	$t->set_var(array("EmptyRePrintBLout"=>""));
}





// See if they have permission to create reprints 
if(!$AuthObj->CheckForPermission("CREATE_REPRINT")){
	$t->set_block("origPage","CreateReprintBL","CreateReprintBLout");
	$t->set_var("CreateReprintBLout", "");
}




// If this project is NOT a reprint, then delete the block that describes what the reprint is about
if($projectObj->getRePrintLink() == "0"){

	// This project is not a reprint... so hide the reprint message block
	$t->set_block("origPage","HideReprintMessageBL","HideReprintMessageBLout");
	$t->set_var(array("HideReprintMessageBLout"=>""));
}
else{
	// show them a message and tell them where this reprint came from
	$t->set_var(array(
		"REPRINT_FROM"=>$projectObj->getRePrintLink(),
		"REPRINT_NOTES"=>Status::GetReprintReasonString($projectObj->getRePrintReason())
		));
}



$t->set_block("origPage","MessageThreadBL","MessageThreadBLout");

// Extract Inner HTML blocks out of the Block we just extracted.
$t->set_block ( "MessageThreadBL", "CloseMessageLinkBL", "CloseMessageLinkBLout" );

$messageThreadCollectionObj = new MessageThreadCollection();	

$messageThreadCollectionObj->setUserID($UserID);
$messageThreadCollectionObj->setRefID($projectorderid);
$messageThreadCollectionObj->setAttachedTo("project");

$messageThreadCollectionObj->loadThreadCollection();

$messageThreadCollection = $messageThreadCollectionObj->getThreadCollection();

foreach ($messageThreadCollection as $messageThreadObj) {
	
	$messageThreadHTML = MessageDisplay::generateMessageThreadHTML($messageThreadObj);
			
	$t->set_var(array(
		"MESSAGE_BLOCK"=>$messageThreadHTML,
		"THREAD_SUB"=>WebUtil::htmlOutput($messageThreadObj->getSubject()),
		"THREADID"=>$messageThreadObj->getThreadID()
		));

	$t->allowVariableToContainBrackets("MESSAGE_BLOCK");
	
	// Discard the inner blocks (for the Close Message Link) if there are no unread messages.
	$unreadMessageIDs = $messageThreadObj->getUnReadMessageIDs();
	$t->set_var("UNREAD_MESSAGE_IDS", implode("|", $unreadMessageIDs));
	
	if(!empty($unreadMessageIDs))
		$t->parse("CloseMessageLinkBLout", "CloseMessageLinkBL", false );
	else
		$t->set_var("CloseMessageLinkBLout", "");		
		
	$t->parse("MessageThreadBLout","MessageThreadBL", true);	
}	

if(sizeof($messageThreadCollection) == 0)
	$t->set_var(array("MessageThreadBLout"=>"No messages with this Project item."));
		







$t->set_var(array("CUSTOMER_EMAIL"=>$customer_email));

$t->set_var(array("PROJECTID"=>$projectorderid, "ORDERID"=>$mainorder_ID));




$t->set_var(array("EMAIL_ENCODED"=>urlencode($personsEmail),"SHIPPINGNAME_ENCODED"=>urlencode($ShippingName)));

$t->set_var(array("ORDERNO_HEADER"=>"<a href='./ad_order.php?orderno=$mainorder_ID' class='BlueRedLink'>$mainorder_ID</a> -P$projectorderid"));
$t->allowVariableToContainBrackets("ORDERNO_HEADER");


// If the project is batched... then show an HTML table describing what else was in the batch.
if(MailingBatch::checkIfProjectBatched($dbCmd, $projectorderid) && $AuthObj->CheckForPermission("MAILING_BATCHES_VIEW")){

	$mailingBatchID  = MailingBatch::getBatchIDofProject($dbCmd, $projectorderid);

	$t->set_var("MAILING_BATCH", MailingBatch::getMailingBatchHTMLtable($dbCmd, $UserID, array($mailingBatchID), $AuthObj));
	$t->allowVariableToContainBrackets("MAILING_BATCH");
}
else{
	$t->set_var("MAILING_BATCH", "");
}





$t->pparse("OUT","origPage");



?>