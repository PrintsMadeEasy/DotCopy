<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();





//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_general);
$UserID = $AuthObj->GetUserID();

$domainID = Domain::oneDomain();

$t = new Templatex();


$t->set_file("origPage", "myaccount_sales_invite-template.html");


$SalesMaster = $AuthObj->CheckForPermission("SALES_MASTER");

// The Sales Master can see everyone.
if($SalesMaster)
	$SalesID = 0;
else
	$SalesID = $UserID;



$proxyuser = WebUtil::GetInput("proxyuser", FILTER_SANITIZE_INT);



// If specifying a proxy user... make sure that they have permissions to do so.
if(!empty($proxyuser)){
	if(!$AuthObj->CheckForPermission("SALES_PROXY_USER"))
		throw new Exception("Invalid Permissions for adding a proxy user.");
	else
		$SalesID = $proxyuser;
}



// Hide the Sales Block if they don't have permissions
$SalesRepObj = new SalesRep($dbCmd);

// In case of a Sales Master... we can't try and load the Sales Rep ID.
if(!empty($SalesID)){
	if(!$SalesRepObj->LoadSalesRep($SalesID) && !$SalesMaster)
		WebUtil::PrintError("You do not have permissions to view this.");
}

// Make sure that this sales rep has permissions to add Sales Reps
if(!$SalesMaster){
	if(!$SalesRepObj->CheckIfCanAddSubSalesReps())
		throw new Exception("You can not add sub-reps.");
}


// If the form as been submitted.
if(WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES) == "invite"){
	
	WebUtil::checkFormSecurityCode();

	$t->discard_block("origPage", "SumitFormBL");
	
	$emailAddress = WebUtil::GetInput("email", FILTER_SANITIZE_EMAIL);
	$percentage = WebUtil::GetInput("percentage", FILTER_SANITIZE_FLOAT);
	$subreps = WebUtil::GetInput("subreps", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
	
	if(!preg_match("/^\d+(\.\d{1,2})?$/", $percentage))
		throw new Exception("Percentage Value is not correct.");
	if(!WebUtil::ValidateEmail($emailAddress))
		throw new Exception("email address is invalid.");
	if($subreps != "Y" && $subreps != "N")
		throw new Exception("Invalid Sub Rep parameter");

	
	$HshCd = md5(time() . Constants::getGeneralSecuritySalt());
	
	$InsertQry["Email"] = $emailAddress;
	$InsertQry["Hashcode"] = $HshCd;
	$InsertQry["FromUserID"] = $SalesID;
	$InsertQry["CanAddSubReps"] = $subreps;
	$InsertQry["CommissionPercent"] = $percentage;
	
	$dbCmd->InsertQuery("salesrepinvitations", $InsertQry);
	
	$websiteUrlForDomain = Domain::getWebsiteURLforDomainID(Domain::oneDomain());
	
	$SalesInviteLink = "http://$websiteUrlForDomain/myaccount_sales_register.php?view=start&id=" . $HshCd;
	
	$EmailMessage = "Hello there,\n\nYou have been invited to join the " . Domain::getDomainKeyFromID($domainID) . " team of Sales Representatives.\n\nWe are on the cutting edge of development for e-commerce technology, specializing in Online Printing.  Our sales commission system is simple to use and you can begin using it immediately.  Visit the following address to get started ...\n\n$SalesInviteLink";

	
	$domainEmailConfigObj = new DomainEmails($domainID);


	if(!Constants::GetDevelopmentServer())
		WebUtil::SendEmail($domainEmailConfigObj->getEmailNameOfType(DomainEmails::SALES), $domainEmailConfigObj->getEmailAddressOfType(DomainEmails::SALES), "Future Sales Rep", $emailAddress, "Sales Rep. Invitation", $EmailMessage, false);
	
	$t->set_var("YOUR_RATE", "0");

}
else{

	$t->discard_block("origPage", "SuccessBL");


	$t->set_var("PROXYUSER", $proxyuser);


	if(!empty($proxyuser)){
		$t->set_var("YOUR_RATE", $SalesRepObj->getCommissionPercent());
		$t->set_var("REP_PERC", $SalesRepObj->getCommissionPercent());
	}
	else if($SalesMaster){
		$t->set_var("YOUR_RATE", "100");
		$t->set_var("REP_PERC", "20");
	}
	else{
		$t->set_var("YOUR_RATE", $SalesRepObj->getCommissionPercent());
		$t->set_var("REP_PERC", ($SalesRepObj->getCommissionPercent() - 1));
	}
	
	$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());
}

$t->pparse("OUT","origPage");


?>