<?

require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();

$SalesRepObj = new SalesRep($dbCmd);





// Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();



if(!$AuthObj->CheckForPermission("SALES_CONTROL"))
	throw new Exception("Permission Denied");


$SalesUserID = WebUtil::GetInput("salesuserid", FILTER_SANITIZE_INT);

if(!$SalesRepObj->LoadSalesRep( $SalesUserID ))
	throw new Exception("The Sales User ID is not a sales rep.");

$Action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
if(!empty($Action)){
	
	WebUtil::checkFormSecurityCode();
	
	if($Action == "save"){

		$SalesRepObj->setHaveReceivedW9(true);
		
		if(WebUtil::GetInput("W9Exempt", FILTER_SANITIZE_STRING_ONE_LINE))
			$SalesRepObj->setW9Exempt(true);
		else
			$SalesRepObj->setW9Exempt(false);
		
		$SalesRepObj->setW9Name(WebUtil::GetInput("W9Name", FILTER_SANITIZE_STRING_ONE_LINE));
		$SalesRepObj->setW9Company(WebUtil::GetInput("W9Company", FILTER_SANITIZE_STRING_ONE_LINE));
		$SalesRepObj->setW9Address(WebUtil::GetInput("W9Address", FILTER_SANITIZE_STRING_ONE_LINE));
		$SalesRepObj->setW9City(WebUtil::GetInput("W9City", FILTER_SANITIZE_STRING_ONE_LINE));
		$SalesRepObj->setW9State(WebUtil::GetInput("W9State", FILTER_SANITIZE_STRING_ONE_LINE));
		$SalesRepObj->setW9Zip(WebUtil::GetInput("W9Zip", FILTER_SANITIZE_STRING_ONE_LINE));
		$SalesRepObj->setW9TIN(WebUtil::GetInput("W9TIN", FILTER_SANITIZE_STRING_ONE_LINE));
		$SalesRepObj->setW9TINtype(WebUtil::GetInput("W9TINtype", FILTER_SANITIZE_STRING_ONE_LINE));
		$SalesRepObj->setW9BusinessType(WebUtil::GetInput("W9BusinessType", FILTER_SANITIZE_STRING_ONE_LINE));
		$SalesRepObj->setW9BusinessTypeOther(WebUtil::GetInput("W9BusinessTypeOther", FILTER_SANITIZE_STRING_ONE_LINE));
		$SalesRepObj->setW9AccountNumbers(WebUtil::GetInput("W9AccountNumbers", FILTER_SANITIZE_STRING_ONE_LINE));
		$SalesRepObj->setW9Coments(WebUtil::GetInput("W9Coments", FILTER_SANITIZE_STRING_ONE_LINE));
		$SalesRepObj->setW9DateSigned(strtotime(WebUtil::GetInput("W9DateSigned", FILTER_SANITIZE_STRING_ONE_LINE)));
		$SalesRepObj->setW9FiledByUserID($UserID);

		$SalesRepObj->SaveSalesRep();
		
		// If the user has any payments suspended, then we need to release them.
		$SalesCommissionsObj = new SalesCommissions($dbCmd, Domain::oneDomain());
		$SalesCommissionsObj->SetUser($SalesUserID);
		$SalesCommissionsObj->ReleaseSuspendedPayments($SalesUserID);
		
		SalesRep::RecordChangesToSalesRep($dbCmd, $SalesUserID, $UserID, "W-9 was received.");

		print "<html><script>window.opener.location = window.opener.location; self.close();</script></html>";
		exit;
	}
	else{
		throw new Exception("Illegal Action");
	}
}



$t = new Templatex(".");

$t->set_file("origPage", "ad_sales_w9-template.html");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

$t->set_var("SALES_USERID", $SalesUserID);
$t->set_var("SALESREP_NAME", WebUtil::htmlOutput($SalesRepObj->getName()));



$t->pparse("OUT","origPage");



?>