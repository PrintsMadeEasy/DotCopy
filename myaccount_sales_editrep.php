<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();




//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_general);
$UserID = $AuthObj->GetUserID();


$SalesCommissionsObj = new SalesCommissions($dbCmd, Domain::oneDomain());

$t = new Templatex();

$t->set_file("origPage", "myaccount_sales_editrep-template.html");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

$SalesMaster = $AuthObj->CheckForPermission("SALES_MASTER");

$SubRepID = WebUtil::GetInput("subrepid", FILTER_SANITIZE_INT);
$SubRepID = intval($SubRepID);
if(empty($SubRepID))
	throw new Exception("The SubRepID is missing.");

if($SalesMaster)
	$SalesID = 0;
else
	$SalesID = $UserID;



// Hide the Sales Block if they don't have permissions
$SalesRepObj = new SalesRep($dbCmd);
if(!$SalesRepObj->LoadSalesRep($UserID) && !$SalesMaster)
	throw new Exception("You do not have permissions to view this.");



$SubRepObj = new SalesRep($dbCmd);
if(!$SubRepObj->LoadSalesRep($SubRepID))
	throw new Exception("The Sub Rep is not valid.");

	
// Make sure that the SubRep belongs to the Sales Rep if they are not the sales master.	
if(!$SalesMaster)
	if($SubRepObj->getParentSalesRep() != $SalesID)
		throw new Exception("This Sub Rep does not belong to you.");


$ErrorMessage = "";

$Action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

if($Action == "save"){
	
	WebUtil::checkFormSecurityCode();

	// Collect some original values... that way we can see if anything changes... and then write it to the Sales Rep Change Log
	$original_Percentage = $SubRepObj->getCommissionPercent();
	$original_NewCustomerCommission = $SubRepObj->getNewCustomerCommission();
	$original_CanAddSubReps = $SubRepObj->CheckIfCanAddSubSalesReps();
	$original_MonthsExpires = $SubRepObj->getMonthsExpires();
	$original_PaymentSuspended = $SubRepObj->CheckIfPaymentsSuspended();
	$original_AccountDisabled = $SubRepObj->CheckIfAccountDisabled();
	$original_IsAnEmployee = $SubRepObj->getIsAnEmployee();
	
	
	$Percentage = WebUtil::GetInput("percentage", FILTER_SANITIZE_FLOAT);

	if(!$SubRepObj->CheckIfPercentageCanBeChanged($Percentage))
		$ErrorMessage = "You can not change the percentage because it conflicts with the parent or a child.";
	else
		$SubRepObj->setCommissionPercent($Percentage);

	if(WebUtil::GetInput("subreps", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES) == "yes")
		$SubRepObj->setCanAddSubSalesReps(true);
	else if(WebUtil::GetInput("subreps", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES) == "no")
		$SubRepObj->setCanAddSubSalesReps(false);
	else
		throw new Exception("Illegal subreps parameter");


	// The Sales master has more information on the form that needs to be saved.
	if($SalesMaster){

		$SubRepObj->setMonthsExpires(WebUtil::GetInput("MonthsExpires", FILTER_SANITIZE_INT));
		
		
		$SubRepObj->setNewCustomerCommission( WebUtil::GetInput("newcustomercommission", FILTER_SANITIZE_FLOAT) );


		if(WebUtil::GetInput("payments", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES) == "yes")
			$SubRepObj->setPaymentsSuspended(true);
		else if(WebUtil::GetInput("payments", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES) == "no")
			$SubRepObj->setPaymentsSuspended(false);
		else
			throw new Exception("Illegal payments parameter");


		if(WebUtil::GetInput("employee", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES) == "yes")
			$SubRepObj->setIsAnEmployee(true);
		else if(WebUtil::GetInput("employee", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES) == "no")
			$SubRepObj->setIsAnEmployee(false);
		else
			throw new Exception("Illegal employee parameter");	


		if(WebUtil::GetInput("AcntDisabled", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES) == "yes")
			$SubRepObj->setAccountDisabled(true);
		else if(WebUtil::GetInput("AcntDisabled", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES) == "no")
			$SubRepObj->setAccountDisabled(false);
		else
			throw new Exception("Illegal AcntDisabled parameter");	
			
		// If they are a Sales Master and the W-9 from the Sales Rep has been received... then we should be able to edit it.
		if($SubRepObj->getHaveReceivedW9()){
		
			if(WebUtil::GetInput("W9Exempt", FILTER_SANITIZE_STRING_ONE_LINE))
				$SubRepObj->setW9Exempt(true);
			else
				$SubRepObj->setW9Exempt(false);

			$SubRepObj->setW9Name(WebUtil::GetInput("W9Name", FILTER_SANITIZE_STRING_ONE_LINE));
			$SubRepObj->setW9Company(WebUtil::GetInput("W9Company", FILTER_SANITIZE_STRING_ONE_LINE));
			$SubRepObj->setW9Address(WebUtil::GetInput("W9Address", FILTER_SANITIZE_STRING_ONE_LINE));
			$SubRepObj->setW9City(WebUtil::GetInput("W9City", FILTER_SANITIZE_STRING_ONE_LINE));
			$SubRepObj->setW9State(WebUtil::GetInput("W9State", FILTER_SANITIZE_STRING_ONE_LINE));
			$SubRepObj->setW9Zip(WebUtil::GetInput("W9Zip", FILTER_SANITIZE_STRING_ONE_LINE));
			$SubRepObj->setW9TIN(WebUtil::GetInput("W9TIN", FILTER_SANITIZE_STRING_ONE_LINE));
			$SubRepObj->setW9TINtype(WebUtil::GetInput("W9TINtype", FILTER_SANITIZE_STRING_ONE_LINE));
			$SubRepObj->setW9BusinessType(WebUtil::GetInput("W9BusinessType", FILTER_SANITIZE_STRING_ONE_LINE));
			$SubRepObj->setW9BusinessTypeOther(WebUtil::GetInput("W9BusinessTypeOther", FILTER_SANITIZE_STRING_ONE_LINE));
			$SubRepObj->setW9AccountNumbers(WebUtil::GetInput("W9AccountNumbers", FILTER_SANITIZE_STRING_ONE_LINE));
			$SubRepObj->setW9Coments(WebUtil::GetInput("W9Coments", FILTER_SANITIZE_STRING_ONE_LINE));
			$SubRepObj->setW9DateSigned(strtotime(WebUtil::GetInput("W9DateSigned", FILTER_SANITIZE_STRING_ONE_LINE)));
			$SubRepObj->setW9FiledByUserID($UserID);
		
		}
		
	}
	
	// If there aren't any error messages and we have been able to save.... then we want to automaticaly close the pop-up window.
	if(empty($ErrorMessage)){
		$SubRepObj->SaveSalesRep();
		
		// If we have taken someone's Payments off of Suspension... then we will automatically release any past funds that were on suspension.
		$SalesCommissionsObj->ReleaseSuspendedPayments($SubRepID);
		
		

		// Write a description for any changes we detect... and then write to the error log
		$ChangeLogDesc = "";
		if($original_Percentage != $SubRepObj->getCommissionPercent())
			$ChangeLogDesc .= "Commission percent was changed to " . $SubRepObj->getCommissionPercent() . "%.\n";			
		if($original_NewCustomerCommission != $SubRepObj->getNewCustomerCommission())
			$ChangeLogDesc .= "New Customer Commission was changed to " . ($SubRepObj->getNewCustomerCommission() != "" ? ("\$" . WebUtil::htmlOutput($SubRepObj->getNewCustomerCommission())) : "nothing") . ".\n";			
		if($original_CanAddSubReps != $SubRepObj->CheckIfCanAddSubSalesReps())
			$ChangeLogDesc .= "Can add sub reps was changed to " . ($SubRepObj->CheckIfCanAddSubSalesReps() ? "yes" : "no") . "\n";
		if($original_MonthsExpires != $SubRepObj->getMonthsExpires())
			$ChangeLogDesc .= "Months Expires was changed to " . WebUtil::htmlOutput($SubRepObj->getMonthsExpires()) . ".\n";
		if($original_PaymentSuspended != $SubRepObj->CheckIfPaymentsSuspended())
			$ChangeLogDesc .= "Payments suspended was changed to " . ($SubRepObj->CheckIfPaymentsSuspended() ? "yes" : "no") . "\n";
		if($original_AccountDisabled != $SubRepObj->CheckIfAccountDisabled())
			$ChangeLogDesc .= "Account Disabled was changed to " . ($SubRepObj->CheckIfAccountDisabled() ? "yes" : "no") . "\n";
		if($original_IsAnEmployee != $SubRepObj->getIsAnEmployee())
			$ChangeLogDesc .= "Sales Rep is an Employee was changed to " . ($SubRepObj->getIsAnEmployee() ? "yes" : "no") . "\n";

		if(!empty($ChangeLogDesc))
			SalesRep::RecordChangesToSalesRep($dbCmd, $SubRepID, $UserID, $ChangeLogDesc);
		
		$ErrorMessage = "<script>window.opener.location = window.opener.location; self.close();</script>";	
	}
	else
		$ErrorMessage = $ErrorMessage . "<br><br>";
}


// If we are the Sales Master and the person has submitted the W9 ... we want to set a flag so that we know to Check the Form with Javascript
if($SalesMaster && $SubRepObj->getHaveReceivedW9())
	$t->set_var("W9_FORM_LOADED_JS", "true");
else
	$t->set_var("W9_FORM_LOADED_JS", "false"); 
			

$t->set_var("ERROR", $ErrorMessage);
$t->allowVariableToContainBrackets("ERROR");

$t->set_var("SALESREP_NAME", WebUtil::htmlOutput($SubRepObj->getName()));
$t->set_var("REP_PERC", $SubRepObj->getCommissionPercent());
$t->set_var("SUB_REP_ID", $SubRepID);



$t->set_var("MONTHS_EXP", $SubRepObj->getMonthsExpires());


$t->set_var("REP_NEWCUSTCOMM", $SubRepObj->getNewCustomerCommission());


if($SubRepObj->CheckIfCanAddSubSalesReps())
	$t->set_var(array("SUBREPS_NO"=>"", "SUBREPS_YES"=>"checked"));
else
	$t->set_var(array("SUBREPS_NO"=>"checked", "SUBREPS_YES"=>""));
	

if($SubRepObj->CheckIfPaymentsSuspended())
	$t->set_var(array("PAYMENTS_NO"=>"", "PAYMENTS_YES"=>"checked"));
else
	$t->set_var(array("PAYMENTS_NO"=>"checked", "PAYMENTS_YES"=>""));


if($SubRepObj->CheckIfAccountDisabled())
	$t->set_var(array("DISABLED_NO"=>"", "DISABLED_YES"=>"checked"));
else
	$t->set_var(array("DISABLED_NO"=>"checked", "DISABLED_YES"=>""));


if($SubRepObj->getIsAnEmployee())
	$t->set_var(array("EMPLOYEE_NO"=>"", "EMPLOYEE_YES"=>"checked"));
else
	$t->set_var(array("EMPLOYEE_NO"=>"checked", "EMPLOYEE_YES"=>""));
	

// If we have received the W9 Form from the Sales Rep then we should have more fields that we can edit.
if(!$SubRepObj->getHaveReceivedW9())
	$t->discard_block("origPage", "W9FormBL");
else{




	$t->set_var("W9NAME", WebUtil::htmlOutput($SubRepObj->getW9Name()));
	$t->set_var("W9COMPANY", WebUtil::htmlOutput($SubRepObj->getW9Company()));
	$t->set_var("W9ADDRESS", WebUtil::htmlOutput($SubRepObj->getW9Address()));
	$t->set_var("W9CITY", WebUtil::htmlOutput($SubRepObj->getW9City()));
	$t->set_var("W9STATE", $SubRepObj->getW9State());
	$t->set_var("W9ZIP", $SubRepObj->getW9Zip());
	$t->set_var("W9TIN", $SubRepObj->getW9TIN());
	$t->set_var("W9BUSINESSTYPEOTHER", WebUtil::htmlOutput($SubRepObj->getW9BusinessTypeOther()));
	$t->set_var("W9ACCOUNTNUMBERS", WebUtil::htmlOutput($SubRepObj->getW9AccountNumbers()));
	$t->set_var("W9COMENTS", WebUtil::htmlOutput($SubRepObj->getW9Coments()));
	
	$t->set_var("W9DATESIGNED", date("n/j/y", $SubRepObj->getW9DateSigned()));

	if($SubRepObj->getW9TINtype() == "E")
		$t->set_var(array("W9TINTYPE_EIN"=>"checked", "W9TINTYPE_SSN"=>""));
	else if($SubRepObj->getW9TINtype() == "S")
		$t->set_var(array("W9TINTYPE_EIN"=>"", "W9TINTYPE_SSN"=>"checked"));
	else
		throw new Exception("Illegal TIN Type");
	

	if($SubRepObj->getW9Exempt())
		$t->set_var("W9TAXEXEMPT", "checked");
	else
		$t->set_var("W9TAXEXEMPT", "");


	if($SubRepObj->getW9BusinessType() == "I")
		$t->set_var(array("W9BUSTYPE_I"=>"checked", "W9BUSTYPE_C"=>"", "W9BUSTYPE_P"=>"", "W9BUSTYPE_O"=>""));
	else if($SubRepObj->getW9BusinessType() == "C")
		$t->set_var(array("W9BUSTYPE_I"=>"", "W9BUSTYPE_C"=>"checked", "W9BUSTYPE_P"=>"", "W9BUSTYPE_O"=>""));
	else if($SubRepObj->getW9BusinessType() == "P")
		$t->set_var(array("W9BUSTYPE_I"=>"", "W9BUSTYPE_C"=>"", "W9BUSTYPE_P"=>"checked", "W9BUSTYPE_O"=>""));
	else if($SubRepObj->getW9BusinessType() == "O")
		$t->set_var(array("W9BUSTYPE_I"=>"", "W9BUSTYPE_C"=>"", "W9BUSTYPE_P"=>"", "W9BUSTYPE_O"=>"checked"));
	else
		throw new Exception("Illegal Business Type");


	// Start the Other Field off as DISABLED if another business type has been chosen
	if($SubRepObj->getW9BusinessType() == "O")
		$t->set_var("BUSINESSOTHER_DISABLED", "");
	else
		$t->set_var("BUSINESSOTHER_DISABLED", "disabled");
		
}


// Get rid of extra controls if they don't have permission.
if(!$SalesMaster){
	$t->discard_block("origPage", "SalesMasterBL");
	$t->discard_block("origPage", "SalesMasterBL2");
}
else{
	$t->set_block("origPage","SalesMasterBL","SalesMasterBLout");
	$t->parse("SalesMasterBLout","SalesMasterBL",true);
	
	$t->set_block("origPage","SalesMasterBL2","SalesMasterBL2out");
	$t->parse("SalesMasterBL2out","SalesMasterBL2",true);
}



// Show the Change log (if any)
$dbCmd->Query("SELECT *, UNIX_TIMESTAMP(Date) AS UnixDate FROM salesrepchangelog WHERE SalesUserID=$SubRepID ORDER BY Date DESC");

$t->set_block("origPage","ChangeBL","ChangeBLout");
$changesFound = false;
while($row = $dbCmd->GetRow()){
	
	$changesFound = true;
	
	$changeText = WebUtil::htmlOutput($row["Description"]);
	$changeText = preg_replace("/\n/", "<br>", $changeText);
	$t->set_var("CHANGE_TEXT", $changeText);
	$t->allowVariableToContainBrackets("CHANGE_TEXT");

	$t->set_var("DATE_CHANGED", date("n/j/y", $row["UnixDate"]));
	
	$t->set_var("CHANGED_BY", WebUtil::htmlOutput(UserControl::GetNameByUserID($dbCmd2, $row["WhoChangedUserID"])));

	$t->parse("ChangeBLout","ChangeBL",true);
}

if(!$changesFound)
	$t->discard_block("origPage", "ChangeLogBL");


$t->pparse("OUT","origPage");

?>