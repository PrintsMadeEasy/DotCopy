<?

require_once("library/Boot_Session.php");


// Don't make it look like we are favoring a particular Domain 
Domain::removeTopDomainID();

$dbCmd = new DbCmd();

$domainObj = Domain::singleton();

// Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

if(!$AuthObj->CheckForPermission("CHAT_SYSTEM"))
	WebUtil::PrintAdminError("This URL is not available.");

$chatCsrOjb = ChatCSR::singleton($UserID);
$chatOjb = new ChatThread();

$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
if($action){
	
	WebUtil::checkFormSecurityCode();

	if($action == "saveCsrSetup"){
		
		$penName = WebUtil::GetInput("penName", FILTER_SANITIZE_STRING_ONE_LINE);
		$greetingMsg = WebUtil::GetInput("greetingMsg", FILTER_SANITIZE_STRING_ONE_LINE);
		$signOffMsg = WebUtil::GetInput("signOffMsg", FILTER_SANITIZE_STRING_ONE_LINE);
		$domainID = WebUtil::GetInput("domainId", FILTER_SANITIZE_INT);
		$chatTypesArr = WebUtil::GetInputArr("chatTypes", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
		$newPleaseWaitMessage = WebUtil::GetInput("pleaseWaitMessage", FILTER_SANITIZE_STRING_ONE_LINE);
		
		$chatCsrOjb->setPenName($domainID, $penName);
		$chatCsrOjb->setGreetingMessage($domainID, $greetingMsg);
		$chatCsrOjb->setSignOffMessage($domainID, $signOffMsg);
		$chatCsrOjb->setChatTypes($domainID, $chatTypesArr);
		
		if(!empty($newPleaseWaitMessage)){
			$chatCsrOjb->addNewPleaseWaitMessage($domainID, $newPleaseWaitMessage);
		}

		if(array_key_exists("csrPhoto", $_FILES) && !empty($_FILES["csrPhoto"]["size"])){
			
			$csrPhotoBinData = fread(fopen($_FILES["csrPhoto"]["tmp_name"], "r"), filesize($_FILES["csrPhoto"]["tmp_name"]));
			$uploadError = $chatCsrOjb->setCsrPhoto($domainID, $csrPhotoBinData);

			if(!empty($uploadError)){
				WebUtil::PrintAdminError($uploadError);
			}
		}
		
		header("Location: ./ad_chat_setup.php?");
		exit;
	}
	else if($action == "changeOpenThreadLimit"){
		$chatCsrOjb->setChatThreadLimit(WebUtil::GetInput("threadLimit", FILTER_SANITIZE_NUMBER_INT));
		
		header("Location: ./ad_chat_setup.php?");
		exit;
	}
	else if($action == "deletePleaseWaitMsg"){
		$chatCsrOjb->deletePleaseWaitMessage(WebUtil::GetInput("pleaseWaitId", FILTER_SANITIZE_NUMBER_INT));
		
		header("Location: ./ad_chat_setup.php?");
		exit;
	}
	else if($action == "removeCsrThumbImage"){
		
		$domainID = WebUtil::GetInput("domainId", FILTER_SANITIZE_INT);
		$photoBlob = null;
		
		$chatCsrOjb->setCsrPhoto($domainID, $photoBlob);
		
		header("Location: ./ad_chat_setup.php?");
		exit;
	}
	else{
		throw new Exception("Action is not defined.");
	}
}


$t = new Templatex(".");

$t->set_file("origPage", "ad_chat_setup-template.html");

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

$t->set_block("origPage", "DomainRowBL", "DomainRowBLout");


$chatThreadLimitArr = array("0"=>"0", "1"=>"1", "2"=>"2", "3"=>"3", "4"=>"4", "5"=>"5", "6"=>"6", "7"=>"7", "8"=>"8", "9"=>"9", "10"=>"10");

$t->set_var("THREAD_LIMIT_SELECT", Widgets::buildSelect($chatThreadLimitArr, $chatCsrOjb->getChatThreadLimit()));
$t->allowVariableToContainBrackets("THREAD_LIMIT_SELECT");

$selectedDomainIDs = $domainObj->getSelectedDomainIDs();

foreach($selectedDomainIDs as $thisDomainID){

	// Only show the Domain Logo if the user has more than 1 domain selected.
	if(sizeof($selectedDomainIDs) > 1){
		$domainLogoObj = new DomainLogos($thisDomainID);
		$domainLogoImg = "<img alt='".Domain::getDomainKeyFromID($thisDomainID)."'   src='./domain_logos/$domainLogoObj->verySmallIcon' border='0'>";
	}
	else{
		$domainLogoImg = "";
	}
	
	$chatCsrOjb = ChatCSR::singleton($UserID);
	
	
	$pleaseWaitMessagesArr = $chatCsrOjb->getPleaseWaitMessages($thisDomainID);
	
	// If there are no "Please wait messages for the domain, add some default values"
	if(empty($pleaseWaitMessagesArr)){
		$chatCsrOjb->createDefaultPleaseWaitMessages($thisDomainID);
		$pleaseWaitMessagesArr = $chatCsrOjb->getPleaseWaitMessages($thisDomainID);
	}
	
	$pleaseWaitMessagesHTML = "";
	foreach($pleaseWaitMessagesArr as $pleaseWaitID => $thisPleaseWaitMsg){
		$pleaseWaitMessagesHTML .= "<a href='javascript:DeletePleaseWait($pleaseWaitID);'>X</a> " .  WebUtil::htmlOutput($thisPleaseWaitMsg) . "<br>";	
	}
	
	$t->set_var("PLEASE_WAIT_MESSAGES", $pleaseWaitMessagesHTML);
	
	$t->allowVariableToContainBrackets("PLEASE_WAIT_MESSAGES");
	
	$chatTypesCheckBoxesHtml = "";
	
	$allChatTypesArr = ChatThread::getChatTypesArr();
	$csrChatTypesArr = $chatCsrOjb->getChatTypes($thisDomainID);
	foreach($allChatTypesArr as $thisChatType){
		if(in_array($thisChatType, $csrChatTypesArr))
			$checkedStr = " checked='checked'";
		else 	
			$checkedStr = "";
			
		$chatTypesCheckBoxesHtml .= "<input type='checkbox' name='chatTypes[]' value='".$thisChatType."' $checkedStr /> ";
		$chatTypesCheckBoxesHtml .= ChatThread::getChatTypeDesc($thisChatType) . " <br />";
	}
	
	$t->set_var("CHAT_TYPES", $chatTypesCheckBoxesHtml);
	$t->allowVariableToContainBrackets("CHAT_TYPES");
	
	
	$t->set_var("DOMAIN_ID", $thisDomainID);
	$t->set_var("PEN_NAME", WebUtil::htmlOutput($chatCsrOjb->getPenName($thisDomainID)));
	$t->set_var("GREETING_MSG", WebUtil::htmlOutput($chatCsrOjb->getGreetingMessage($thisDomainID)));
	$t->set_var("SIGN_OFF_MSG", WebUtil::htmlOutput($chatCsrOjb->getSignOffMessage($thisDomainID)));
	
	
	$csrPhoto = $chatCsrOjb->getPhotoId($thisDomainID);
	
	if(empty($csrPhoto)){
		$t->set_var("EXISTING_PHOTO", "");
	}
	else{
		$t->set_var("EXISTING_PHOTO", "<br><img src='chat_csr_photo.php?id=$csrPhoto' style='border:solid; border-width:1px; border-color:#000000;' /> <a href='javascript:RemovePhoto($thisDomainID);'>X</a>");
	}
	
	$t->set_var("DOMAIN_LOGO", $domainLogoImg);
	$t->set_var("DOMAIN_NAME", WebUtil::htmlOutput(Domain::getDomainKeyFromID($thisDomainID)));
	
	$t->allowVariableToContainBrackets("DOMAIN_LOGO");
	$t->allowVariableToContainBrackets("EXISTING_PHOTO");
	
	$t->parse("DomainRowBLout", "DomainRowBL", true);
}




$t->pparse("OUT","origPage");





