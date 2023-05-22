<?

require_once("library/Boot_Session.php");




$dbCmd = new DbCmd();


$AuthObj = new Authenticate(Authenticate::login_ADMIN);

$UserID = $AuthObj->GetUserID();



$orderNumber = WebUtil::GetInput("orderNumber", FILTER_SANITIZE_INT);
$forceProjectID = WebUtil::GetInput("forceProjectID", FILTER_SANITIZE_INT, null);
$sendEmailToCustomerFlag = WebUtil::GetInput("sendEmailToCustomerFlag", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES, "yes");
$returl = WebUtil::GetInput("returl", FILTER_SANITIZE_URL);

if(!Order::CheckIfUserHasPermissionToSeeOrder($orderNumber))
	throw new Exception("The Order Number is not available.");


$CustomerServiceName = UserControl::GetNameByUserID($dbCmd, $UserID);
$CustomerServiceEmail = UserControl::GetEmailByUserID($dbCmd, $UserID);


// Send one email to the User
$copyrightChargesObj = new CopyrightCharges($dbCmd, $orderNumber);


if($sendEmailToCustomerFlag == "yes")

	$copyrightChargesObj->sendEmailToUser(null, null, $forceProjectID);

// Send a copy of the email to the CSR
$copyrightChargesObj->sendEmailToUser($CustomerServiceName, $CustomerServiceEmail, $forceProjectID);



header("Location: ". WebUtil::FilterURL($returl));
exit;


 
?>