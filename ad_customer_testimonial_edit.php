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
$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

$testimonialObj = new CustomerTestimonials();

if($viewType == "edit"){
	$testimonialObj->loadTestimonialById($testimonialID);
}
else if($viewType != "new"){
	throw new Exception("Illegal View Type.");
}



if(!empty($action)){
	
	WebUtil::checkFormSecurityCode();

	if($action == "save"){

		$testimonialObj->setStatus(WebUtil::GetInput("status", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES));
		$testimonialObj->setTestimonial(WebUtil::GetInput("testimonial", FILTER_UNSAFE_RAW));
		$testimonialObj->setFirstName(WebUtil::GetInput("firstName", FILTER_SANITIZE_STRING_ONE_LINE));
		$testimonialObj->setCity(WebUtil::GetInput("city", FILTER_SANITIZE_STRING_ONE_LINE));
		$testimonialObj->setEmail(WebUtil::GetInput("email", FILTER_SANITIZE_EMAIL));
		
		if($viewType == "edit"){
			$testimonialObj->setDateLastEdited(time());
			$testimonialObj->setEditedByUserID($UserID);
			$testimonialObj->updateTestimonial();
		}
		else if($viewType == "new"){
			$testimonialObj->setDateCreated(time());
			$testimonialObj->setDateLastEdited(time());
			$testimonialObj->createNewRecord();
		}
		else{
			throw new Exception("Illegal View Type");
		}
		
		exit("<html><script>window.opener.location = window.opener.location; self.close();</script></html>");
	}
	else{
		throw new Exception("Illegal Action");
	}
}


$t = new Templatex(".");

$t->set_file("origPage", "ad_customer_testimonial_edit-template.html");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

$t->set_var("TESTIMONIAL", WebUtil::htmlOutput($testimonialObj->getTestimonial(false, false)));

$t->set_var("FIRST_NAME", WebUtil::htmlOutput($testimonialObj->getFirstName()));
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


$t->set_var("VIEW_TYPE", $viewType);


if($viewType == "edit"){
	$t->set_var("TITLE_TYPE", WebUtil::htmlOutput("Edit"));
	$t->set_var("TESTIMONIAL_ID", $testimonialID);
	
	$t->set_var("EMAIL", WebUtil::htmlOutput($testimonialObj->getEmail()));

}
else if($viewType == "new"){
	$t->set_var("TITLE_TYPE", WebUtil::htmlOutput("Create New"));
	$t->set_var("TESTIMONIAL_ID", 0);
	
	$domainEmailConfigObj = new DomainEmails(Domain::oneDomain());
	$t->set_var("EMAIL", WebUtil::htmlOutput($domainEmailConfigObj->getEmailAddressOfType(DomainEmails::CUSTSERV)));
}
else{
	throw new Exception("The View Type is invalid.");
}



$t->pparse("OUT","origPage");






