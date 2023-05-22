<?

require_once("library/Boot_Session.php");




$dbCmd = new DbCmd();

$SalesCommissionsObj = new SalesCommissions($dbCmd, Domain::oneDomain());




$t = new Templatex();


$t->set_file("origPage", "myaccount_sales_register-template.html");

$view = WebUtil::GetInput("view", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);


// By the final page is displayed, the Invitation has already been deleted from the Database
if($view == "final")
	$t->discard_block("origPage", "InvitationExpiredBL");
else{

	$InvitationID = WebUtil::GetInput("id", FILTER_SANITIZE_STRING_ONE_LINE);
	// make sure that the ID is a 32bit Hex value
	if(!preg_match("/^(\d|a|b|c|d|e|f){32}$/i", $InvitationID))
		WebUtil::PrintError("The ID is not in proper format.");
	
	$t->set_var("ID", $InvitationID);

	// Let's make sure that an invitation exists for the given ID
	$dbCmd->Query("SELECT * FROM salesrepinvitations WHERE Hashcode='" . DbCmd::EscapeSQL($InvitationID) . "'");
	if($dbCmd->GetNumRows() != 1){

		$t->discard_block("origPage", "WelcomeBL");
		$t->discard_block("origPage", "FinalBL");

		$t->pparse("OUT","origPage");
		exit;
	}
	else
		$t->discard_block("origPage", "InvitationExpiredBL");
		
	$InvitationRow = $dbCmd->GetRow();
}




if($view == "start"){
	$t->discard_block("origPage", "FinalBL");
	
	$t->set_var("PERCENT", $InvitationRow["CommissionPercent"]);
}
else if($view == "save"){

	//Make sure they are logged in.
	$AuthObj = new Authenticate(Authenticate::login_general);
	$UserID = $AuthObj->GetUserID();


	$SalesRepObj = new SalesRep($dbCmd);
	if($SalesRepObj->LoadSalesRep($UserID))
		WebUtil::PrintError("You have already been signed up as a Sales Rep. Click on the My Account tab to visit the Sales Commission System.");

	
	$SalesRepObj->setParentSalesRep($InvitationRow["FromUserID"]);
	$SalesRepObj->setCommissionPercent($InvitationRow["CommissionPercent"]);
	
	
	// Make sure that they are a Sales Rep of their own account
	$SalesRepObj->setSalesRepID($UserID);
	
	
	if($InvitationRow["CanAddSubReps"] == "Y")
		$SalesRepObj->setCanAddSubSalesReps(true);
	else
		$SalesRepObj->setCanAddSubSalesReps(false);
	
	$SalesRepObj->SaveSalesRep();
	
	SalesRep::RecordChangesToSalesRep($dbCmd, $UserID, $UserID, "Sales Rep has registered.\nStarting commission percentage is " . $InvitationRow["CommissionPercent"] . "%.\nCan add Sub-reps " . ($SalesRepObj->CheckIfCanAddSubSalesReps() ? "yes" : "no"));
	
	// We can get rid of invitation now because they are now officially a sales rep.
	$dbCmd->Query("DELETE FROM salesrepinvitations WHERE ID=" . $InvitationRow["ID"]);
	
	
	// Now we want to Create a Sales Coupon for them
	$couponsObj = new Coupons($dbCmd);
	$couponsObj->SetCouponCode($SalesRepObj->getDefaultSalesCouponCode());
	$couponsObj->SetCouponExpDate(0);
	$couponsObj->SetCouponMaxAmount("25.00");
	$couponsObj->SetCouponShippingDiscount(0);
	$couponsObj->SetCouponIsActive(1);
	$couponsObj->SetCouponName($SalesRepObj->getName()); // Make the name of the coupon the Sales person name.
	$couponsObj->SetCouponCategoryID($couponsObj->GetCategoryIDbyCategoryName("Sales"));
	$couponsObj->SetCouponNeedsActivation(false);
	$couponsObj->SetCouponWithinFirstOrders(1);
	$couponsObj->SetCouponDiscountPercent("25");
	$couponsObj->SetCouponComments("");
	$couponsObj->SetProofingAlert("");
	$couponsObj->SetSalesLink($SalesCommissionsObj, $AuthObj->GetUserID());
	$couponsObj->SetCouponCreatorUserID($AuthObj->GetUserID());
	$couponsObj->SaveNewCouponInDB();
	
	
	// Activate the SALESREP coupon for this person.
	// We give every sales person a few free orders to start them off.
	$couponsObj->ActivateCouponForUser("SALESREP", $AuthObj->GetUserID());
	
	// They are a new Sales Rep... so we need to verify their email address
	$SalesRepObj->SendEmailVerificationEmail();
	
	// Now that we have added the Sales Rep... redirect them to the confirmation page.
	header("Location: " . WebUtil::FilterURL("./myaccount_sales_register.php?view=final"));
	exit;

}
else if($view == "final"){

	$t->discard_block("origPage", "WelcomeBL");

	//Make sure they are logged in.
	$AuthObj = new Authenticate(Authenticate::login_general);
	$UserID = $AuthObj->GetUserID();

	$SalesRepObj = new SalesRep($dbCmd);
	if(!$SalesRepObj->LoadSalesRep($UserID))
		WebUtil::PrintError("A problem has occured. You do not appear to be a Sales Rep within the system.");

	
	$t->set_var("SALES_ADDRESS", $SalesRepObj->getAddressInHTML());
	$t->allowVariableToContainBrackets("SALES_ADDRESS");

}
else{
	WebUtil::PrintError("Illegal View Type");
}



$t->pparse("OUT","origPage");


?>