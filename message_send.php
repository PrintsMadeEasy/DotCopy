<?

require_once("library/Boot_Session.php");


$messagetype = WebUtil::GetInput("messagetype", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$dbCmd = new DbCmd();


$domainObj = Domain::singleton();

//WebUtil::BreakOutOfSecureMode();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_general);
$UserID = $AuthObj->GetUserID();

$t = new Templatex();


$t->set_file("origPage", "message_send-template.html");


$personsName = UserControl::GetNameByUserID($dbCmd, $UserID);


if($messagetype == "general"){
	$t->discard_block("origPage", "emptyOrderBL");
	$t->discard_block("origPage", "NoOrdersYetBL");
	$t->discard_block("origPage", "OrderRelatedMessageBL");
	
}
else if($messagetype == "emptyOrders"){
	$t->discard_block("origPage", "emptyOrderBL");
	$t->discard_block("origPage", "GeneralMessageBL");
	$t->discard_block("origPage", "OrderRelatedMessageBL");
}
else if($messagetype == "orderRelated"){
	
	$t->discard_block("origPage", "emptyOrderBL");
	$t->discard_block("origPage", "GeneralMessageBL");
	$t->discard_block("origPage", "NoOrdersYetBL");
	
	$orderno = WebUtil::GetInput("orderno", FILTER_SANITIZE_STRING_ONE_LINE);
	
	$orderNumberOnly = 0;
	
	try{
		$orderNumberOnly = Order::getOrderIDfromOrderHash($orderno);
	}
	catch (Exception $e){
		
		WebUtil::PrintError("An error occured. Please contact Customer Service.");
	}
	
	$t->set_var("ORDER_ID", $orderNumberOnly);
	$t->set_var("ORDER_ID_HASHED", $orderno);
	
}
else{
	throw new Exception("Illegal Message Type");
}

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

$t->set_var("PERSON_NAME", htmlspecialchars($personsName));

VisitorPath::addRecord("Customer Service Message Construct");

$t->pparse("OUT","origPage");

?>
