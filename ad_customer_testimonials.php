<?

require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("TESTIMONIALS"))
	throw new Exception("Permission Denied");

$testimonialID = WebUtil::GetInput("testimonialID", FILTER_SANITIZE_INT);
$viewType = WebUtil::GetInput("viewType", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$searchStatus = WebUtil::GetInput("searchStatus", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES, CustomerTestimonials::STATUS_PENDING);
$searchText = WebUtil::GetInput("searchText", FILTER_SANITIZE_STRING_ONE_LINE);
$searchName = WebUtil::GetInput("searchName", FILTER_SANITIZE_STRING_ONE_LINE);
$offset = WebUtil::GetInput("offset", FILTER_SANITIZE_INT);
$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$newStatus = WebUtil::GetInput("newStatus", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);




if(!empty($action)){
	
	WebUtil::checkFormSecurityCode();
	
	if($action == "changeStatus"){
		$testimonialObj = new CustomerTestimonials();
		$testimonialObj->loadTestimonialById($testimonialID);
		$testimonialObj->setStatus($newStatus);
		$testimonialObj->setEditedByUserID($UserID);
		$testimonialObj->updateTestimonial();
		
		header("Location: ./ad_customer_testimonials.php?");
		exit;
	}
	else if($action == "moveToCs"){
		
		$testimonialObj = new CustomerTestimonials();
		$testimonialObj->loadTestimonialById($testimonialID);
		
		// Create a new CS Item
		$infoHash["Subject"] = "PrintsMadeEasy.com Question";
		$infoHash["Status"] = "O";
		$infoHash["UserID"] = 0;
		$infoHash["Ownership"] = 0;
		$infoHash["OrderRef"] = 0;
		$infoHash["DateCreated"] = time();
		$infoHash["LastActivity"] = time();
		$infoHash["CustomerName"] = $testimonialObj->getFirstName();
		$infoHash["CustomerEmail"] = $testimonialObj->getEmail();
		$infoHash["DomainID"] = Domain::oneDomain();
	
		// Insert into the Database and the function will return what the new csThreadID is
		$csthreadid = CustService::NewCSitem($dbCmd, $infoHash);
			
		// figure out out the Email Address and Name of customer service of the given domain.
		$domainOfCsItem = CustService::getDomainIDfromCSitem($dbCmd, $csthreadid);
		$domainEmailConfigObj = new DomainEmails($domainOfCsItem);
		$our_email = $domainEmailConfigObj->getEmailAddressOfType(DomainEmails::CUSTSERV);
		$our_name = $domainEmailConfigObj->getEmailNameOfType(DomainEmails::CUSTSERV);
			
		// Now put the message into the DB.... associated with the CSItemID
		$messageHash["FromUserID"] = 0;
		$messageHash["ToUserID"] = 0;
		$messageHash["csThreadID"] = $csthreadid;
		$messageHash["CustomerFlag"] = "Y";
		$messageHash["FromName"] = $testimonialObj->getFirstName();
		$messageHash["FromEmail"] = $testimonialObj->getEmail();
		$messageHash["ToName"] = $our_name;
		$messageHash["ToEmail"] = $our_email;
		$messageHash["Message"] = $testimonialObj->getTestimonial(false, false);
		$messageHash["DateSent"] = time();
		$dbCmd->InsertQuery("csmessages",  $messageHash);
		
		
		// Now delete the user Feedback.
		$testimonialObj->setStatus(CustomerTestimonials::STATUS_DELETED);
		$testimonialObj->setTestimonial($testimonialObj->getTestimonial(false, false) . "\n\n[Moved to Customer Service]");
		$testimonialObj->setEditedByUserID($UserID);
		$testimonialObj->updateTestimonial();
		
		header("Location: ./ad_customer_testimonials.php?");
		exit;
	}
	else{
		throw new Exception("Illegal Action");
	}
}


$t = new Templatex(".");

$t->set_file("origPage", "ad_customer_testimonials-template.html");

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

$t->set_var("SEARCH_NAME", WebUtil::htmlOutput($searchName));
$t->set_var("SEARCH_TEXT", WebUtil::htmlOutput($searchText));


// Build a drop down of avaiable status types for searching.
$statusSearchTypes = array();

$statusSearchTypes["ALL_BUT_DELETED"] =  "All But Deleted";
$statusSearchTypes[CustomerTestimonials::STATUS_PENDING] =  "Pending";
$statusSearchTypes[CustomerTestimonials::STATUS_APPROVED] =  "Approved";
$statusSearchTypes[CustomerTestimonials::STATUS_DELETED] =  "Deleted";

$t->set_var("SEARCH_STATUS_LIST", Widgets::buildSelect($statusSearchTypes, $searchStatus));
$t->allowVariableToContainBrackets("SEARCH_STATUS_LIST");







$query = "SELECT ID FROM customertestimonials WHERE DomainID=" . Domain::oneDomain();

if(!empty($searchName))
	$query .= " AND FirstName LIKE '%".DbCmd::EscapeLikeQuery($searchName)."%'";
if(!empty($searchText))
	$query .= " AND (TestimonialOriginal LIKE '%".DbCmd::EscapeLikeQuery($searchText)."%' OR TestimonialModified LIKE '%".DbCmd::EscapeLikeQuery($searchText)."%')";

	
if($searchStatus == "ALL_BUT_DELETED")
	$query .= " AND Status != '".CustomerTestimonials::STATUS_DELETED."'";
else if($searchStatus == CustomerTestimonials::STATUS_PENDING)
	$query .= " AND Status = '".CustomerTestimonials::STATUS_PENDING."'";
else if($searchStatus == CustomerTestimonials::STATUS_APPROVED)
	$query .= " AND Status = '".CustomerTestimonials::STATUS_APPROVED."'";
else if($searchStatus == CustomerTestimonials::STATUS_DELETED)
	$query .= " AND Status = '".CustomerTestimonials::STATUS_DELETED."'";
else 
	throw new Exception("Error with Status type.");

$query .= " ORDER BY ID DESC";

$dbCmd->Query($query);

$totalTestimonialsCount = $dbCmd->GetNumRows();

$numberOfResultsToDisplay = 50;
$resultCounter = 0;

$testimonialObj = new CustomerTestimonials();

$t->set_block("origPage","testimonialBL","testimonialBLout");

while($testimonialID = $dbCmd->GetValue()){

	if(($resultCounter >= $offset) && ($resultCounter < ($numberOfResultsToDisplay + $offset))){

		$t->set_var("TESTIMONIAL_ID", $testimonialID);

		$testimonialObj->loadTestimonialById($testimonialID);
		
		$t->set_var("TESTIMONIAL", $testimonialObj->getTestimonial(true, true));
		$t->allowVariableToContainBrackets("TESTIMONIAL");
		
		$t->set_var("CREATED_DATE", date("m/d/y", $testimonialObj->getDateCreated()));
		$t->set_var("EDITED_DATE", date("m/d/y", $testimonialObj->getDateLastEdited()));
		$t->set_var("CUTOMER_NAME", WebUtil::htmlOutput($testimonialObj->getFirstName()));
		$t->set_var("CITY", WebUtil::htmlOutput($testimonialObj->getCity()));
		
		if($testimonialObj->getEditedByUserID())
			$t->set_var("EDITED_BY", WebUtil::htmlOutput(UserControl::GetNameByUserID($dbCmd2, $testimonialObj->getEditedByUserID())));
		else
			$t->set_var("EDITED_BY", "");
		
		$currentStatusDropDown = array();
		$currentStatusDropDown[CustomerTestimonials::STATUS_PENDING] =  "Pending";
		$currentStatusDropDown[CustomerTestimonials::STATUS_APPROVED] =  "Approved";
		$currentStatusDropDown[CustomerTestimonials::STATUS_DELETED] =  "Deleted";

		$t->set_var("STATUS_LIST", Widgets::buildSelect($currentStatusDropDown, $testimonialObj->getStatus()));
		$t->allowVariableToContainBrackets("STATUS_LIST");
		
		$t->parse("testimonialBLout","testimonialBL",true);
		
	}

	$resultCounter++;
}



// This means that we have multiple pages of search results
if($resultCounter > $numberOfResultsToDisplay){
	
	// What are the name/value pairs AND URL  for all of the subsequent pages 
	$NV_pairs_URL = "searchText=".urlencode($searchText)."&searchName=".urlencode($searchName)."&searchStatus=$searchStatus&";
	$BaseURL = "./ad_customer_testimonials.php";

	// Get a the navigation of hyperlinks to all of the multiple pages 
	$NavigateHTML = Navigation::GetNavigationForSearchResults($BaseURL, $resultCounter, $NV_pairs_URL, $numberOfResultsToDisplay, $offset);

	$t->set_var(array("NAVIGATE"=>$NavigateHTML, "RESULT_DESC"=>$resultCounter, "OFFSET"=>$offset));
	$t->allowVariableToContainBrackets("NAVIGATE");
	
	$t->parse("MultiPageBLout","MultiPageBL",true);
	$t->parse("SecondMultiPageBLout","SecondMultiPageBL",true);
}
else{
	$t->set_var("NAVIGATE", "");
	$t->discard_block("origPage", "MultiPageBL");
	$t->discard_block("origPage", "SecondMultiPageBL");

}


if($totalTestimonialsCount == 0){
	$t->set_block("origPage","EmptyTestimonialsBL","EmptyTestimonialsBLout");
	$t->set_var("EmptyTestimonialsBLout", "No Testimonials Found");
}


$t->pparse("OUT","origPage");






