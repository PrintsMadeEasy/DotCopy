<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();




$messageID = WebUtil::GetInput("id", FILTER_SANITIZE_INT);


$t = new Templatex();

$t->set_file("origPage", "customer_service_hurry-template.html");


if(!preg_match("/^\d+$/", $messageID))
	WebUtil::PrintError("The message ID is not in the proper format.");

$messageID = intval($messageID);


$dbCmd->Query("SELECT * FROM csitems WHERE ID=$messageID");
if($dbCmd->GetNumRows() == 0)
	WebUtil::PrintError("The message ID does not exist.  If you continue to have problems, please submit a new inquiry to Customer Service.");

$csItemRow = $dbCmd->GetRow();


// Make sure that the CS item is still open
if($csItemRow["Status"] != "O" && $csItemRow["Status"] != "H")
	WebUtil::PrintError("It appears that this Customer Service matter is already closed.  
			It is possible that a reply was already sent and you haven't received it yet.  
			Please wait a few minutes and then check your email again.
			If you do not have a resolution to this matter soon, please submit a new inquiry to Customer Service.");



if($csItemRow["DomainID"] != Domain::getDomainIDfromURL()){
	WebUtil::WebmasterError("The Domain ID in Customer Service Hurry doesn't match up. MessageID: $messageID");
	WebUtil::PrintError("The Customer Service Item was not found.  Please submit a new inquiry to Customer Service for assistance.");
}
	

$topMostMessage = "";
$messagesArr = CustService::GetMessagesFromCSitem($dbCmd, $messageID);
if(isset($messagesArr[0]))
	$topMostMessage = $messagesArr[0]["Message"];



// Don't let them keep clicking on the link and un-assigning the message.  Someone may have already taken ownerhip
if($csItemRow["Ownership"] != 0 && !preg_match("/I would like it to be picked up by the next available Customer Service/", $topMostMessage)){

	$domainIDofCsItem = CustService::getDomainIDfromCSitem($dbCmd, $messageID);
	$domainEmailConfigObj = new DomainEmails($domainIDofCsItem);
	
	
	// Put a new message into the DB.... associated with the CSItemID
	$insertArr = array();
	$insertArr["FromUserID"] = 0;
	$insertArr["ToUserID"] = 0;
	$insertArr["csThreadID"] = $messageID;
	$insertArr["CustomerFlag"] = "Y";
	$insertArr["FromName"] = $csItemRow["CustomerName"];
	$insertArr["FromEmail"] = $csItemRow["CustomerEmail"];
	$insertArr["ToName"] = $domainEmailConfigObj->getEmailNameOfType(DomainEmails::CUSTSERV);
	$insertArr["ToEmail"] = $domainEmailConfigObj->getEmailAddressOfType(DomainEmails::CUSTSERV);
	$insertArr["Message"] = "Please speed up this Customer Service matter.  I would like it to be picked up by the next available Customer Service representative.";
	$insertArr["DateSent"] = date("YmdHis");
	$dbCmd->InsertQuery("csmessages",  $insertArr);



	// Un-Assign the CS Item
	$dbCmd->UpdateQuery("csitems",  array("Ownership"=>0), "ID=$messageID");
}


$t->set_var("MESSAGE_SUBECT", WebUtil::htmlOutput($csItemRow["Subject"]) . " -- msg[" . $messageID . "]");


$t->pparse("OUT","origPage");


?>