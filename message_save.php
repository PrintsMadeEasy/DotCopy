<?

require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();



//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_general);
$UserID = $AuthObj->GetUserID();


$message = WebUtil::GetInput("message", FILTER_SANITIZE_STRING);
$subject = WebUtil::GetInput("subject", FILTER_SANITIZE_STRING);
$hashedOrderNumber = WebUtil::GetInput("orderno", FILTER_SANITIZE_STRING_ONE_LINE);
$returl = WebUtil::GetInput("returl", FILTER_SANITIZE_URL);


WebUtil::checkFormSecurityCode();


$orderID = 0;

if(!empty($hashedOrderNumber)){
	
	try{
		$orderID = Order::getOrderIDfromOrderHash($hashedOrderNumber);
	}
	catch (Exception $e){
		WebUtil::PrintError("An error occured. Please contact Customer Service.");
	}
}
	

$userControlObj = new UserControl($dbCmd);
$userControlObj->LoadUserByID($UserID);

$personsName = $userControlObj->getName();
$personsEmail = $userControlObj->getEmail();


// Create a default subject, if one hasn't been suplied
if(empty($subject)){
	
	// The subject of the email is the Order Num
	if(!empty($hashedOrderNumber))
		$subject = "Order #" . $hashedOrderNumber;
	else 
		$subject = "General Message";		
}



$infoHash["Subject"] = $subject;
$infoHash["Status"] = "O";
$infoHash["UserID"] = $UserID;
$infoHash["Ownership"] = 0;
$infoHash["OrderRef"] = $orderID;
$infoHash["DateCreated"] = time();
$infoHash["LastActivity"] = time();
$infoHash["CustomerName"] = $personsName;
$infoHash["CustomerEmail"] = $personsEmail;
$infoHash["DomainID"] = UserControl::getDomainIDofUser($UserID);

// Insert into the Database and the function will return what the new csThreadID is
$CS_ThreadID = CustService::NewCSitem($dbCmd, $infoHash);


// Now put the message into the DB.... associated with the CSItemID
$insertArr["FromUserID"] = $UserID;
$insertArr["ToUserID"] = 0;
$insertArr["csThreadID"] = $CS_ThreadID;
$insertArr["CustomerFlag"] = "Y";
$insertArr["FromName"] = $personsName;
$insertArr["FromEmail"] = $personsEmail;
$insertArr["ToName"] = "Customer Service";
$insertArr["ToEmail"] = "";
$insertArr["Message"] = $message;
$insertArr["DateSent"] = time();

$dbCmd->InsertQuery("csmessages",  $insertArr);


VisitorPath::addRecord("Customer Service Message Save");

header("Location: " . WebUtil::FilterURL($returl) . "&nocaching=" . time());


