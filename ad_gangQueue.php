<?

require_once("library/Boot_Session.php");


// Don't make it look like we are favoring a particular Domain 
Domain::removeTopDomainID();

$domainObj = Domain::singleton();

// PDFs may take a long time to generate on very large artworks, especially with Variable Images. .
set_time_limit(3600);


$dbCmd = new DbCmd();


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();



$printingPressID = WebUtil::GetInput("printingPressID", FILTER_SANITIZE_INT);
$confirmationCode = WebUtil::GetInput("confirmationCode", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$confirmName = WebUtil::GetInput("confirmName", FILTER_SANITIZE_STRING_ONE_LINE, "Name Not Given");
$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);


if(!empty($action)){
	
	WebUtil::checkFormSecurityCode();
	
	if($action == "ChangePrintingPress"){

		WebUtil::SetSessionVar("PrintingPressID", $printingPressID);

		$DaysToRemember = 300;
		$cookieTime = time()+60*60*24 * $DaysToRemember;
		setcookie ("PrintingPressID", $printingPressID, $cookieTime);

	}
	else if($action == "confirmGangID"){
	
	
		// Remember the Persons Name
		WebUtil::SetSessionVar("GangRunConfirmName", $confirmName);

		$DaysToRemember = 300;
		$cookieTime = time()+60*60*24 * $DaysToRemember;
		setcookie ("GangRunConfirmName", $confirmName, $cookieTime);

	
	
		// They could be submitting the form without a confirmation code... just so that they can sumbit their name.
		if(empty($confirmationCode)){
			header("Location: ./ad_gangQueue.php?");
			exit;
		}
		
	
		if(!preg_match("/^G\d+$/i", $confirmationCode))
			WebUtil::PrintAdminError("The format of the Confirmation code is incorrect.");
		
		$gangRunID = preg_replace("/G/i", "", $confirmationCode);
		
		$gangObj = new GangRun($dbCmd);
		if(!$gangObj->loadGangRunID($gangRunID))
			WebUtil::PrintAdminError("The confirmation code does not exist.");
		
		$printingPressID = $gangObj->getPrintingPressID();
		
		// Make sure that they have permission to confirm a batch within their list of printers.
		$printingPressArr = PrintingPress::getPrintingPressList($AuthObj->getUserDomainsIDs());
		
		if(!in_array($printingPressID, array_keys($printingPressArr)))
			WebUtil::PrintAdminError("You do not have permission to confirm this Gang Run.");
	
	
		$confirmationError = $gangObj->confirmGangRun($confirmationCode, $confirmName, $UserID);
		if(!empty($confirmationError))
			WebUtil::PrintAdminError($confirmationError);
	
	}
	else{

		throw new Exception("Illegal action called.");
	}

	header("Location: ./ad_gangQueue.php?");
	exit;

	
}



$t = new Templatex(".");


$t->set_file("origPage", "ad_gangQueue-template.html");

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

// Try and find out printer out of the session or the cookie.
$printerViewID = WebUtil::GetSessionVar("PrintingPressID");



if(empty($printerViewID))
	$printerViewID = WebUtil::GetCookie("PrintingPressID");



$printerDropDown = array("0"=>"No Printer (Publicly Seen)");
$printingPressArr = PrintingPress::getPrintingPressList($domainObj->getSelectedDomainIDs());

foreach($printingPressArr as $pressID => $pressName)
	$printerDropDown["$pressID"] = $pressName;


	

// If the printer no longer exists, the don't have permission, or they haven't selected a printer yet.... then just select the first one from out Drow down array.
if(!isset($printingPressArr[$printerViewID]))
	$printerViewID = key($printerDropDown);



WebUtil::EnsureDigit($printerViewID, true, "The Printing Press ID must be numeric.");



$t->set_var("PRINTER_LIST", Widgets::buildSelect($printerDropDown, $printerViewID));
$t->allowVariableToContainBrackets("PRINTER_LIST");


// Now build a list of all jobs in the Queue belonging to this printer.
$printerViewID;

$gangRunIDs = array();
$dbCmd->Query("SELECT ID FROM gangruns WHERE PrintingPressID = $printerViewID AND GangRunStatus='P' ORDER BY ID ASC");
while($thisGang = $dbCmd->GetValue())
	$gangRunIDs[] = $thisGang;





$t->set_block("origPage","gangBlock","gangBlockOut");

foreach($gangRunIDs as $thisGangID){

	$t->set_var("GANGID", $thisGangID);
	
	
	$gangObj = new GangRun($dbCmd);
	$gangObj->loadGangRunID($thisGangID);
	
	
	$t->set_var("SHEET_COUNT", GangRun::translateSheetQuantity($gangObj->getSheetQuantity()));
	
	$t->set_var("MATERIAL_TYPE", WebUtil::htmlOutput($gangObj->getMaterialDesc()));
	
	if($gangObj->getDuplexFlag())
		$t->set_var("DESC", "Duplex");
	else
		$t->set_var("DESC", "Single");
	
	
	$batchNotes = $gangObj->getBatchNotes();
	
	if(!empty($gangObj))
		$t->set_var("NOTES", "&nbsp;<br><table cellpadding='3' border='1' cellspacing='0' width='100%'><tr><td bgcolor='#eeeeee'><font color='#999999' style='font-size:14px;'>Notes:</font> <font color='#bb0000' style='font-size:24px;'>"  . WebUtil::htmlOutput($batchNotes) . "</font></td></tr></table>");
	else
		$t->set_var("NOTES", "");
		
	$t->allowVariableToContainBrackets("NOTES");
	
	
	$t->set_var("SUPER_PROFILE_NAME", $gangObj->getSuperProfileObj()->getSuperPDFProfileName());
	
	$t->set_var("FRONT_PDF_NAME", $gangObj->getFileNameForFrontPDF());
	$t->set_var("FRONT_PDF_THUMB", "<table  border=\"1\" cellspacing=\"0\" cellpadding=\"0\"><tr><td><img  src='./ad_printerQueue_thumbnail.php?gangRunID=" . $thisGangID . "&fileDescType=Front'></td></tr></table>");
	$t->allowVariableToContainBrackets("FRONT_PDF_THUMB");
	
	if($gangObj->getDuplexFlag()){
		$t->set_var("BACK_PDF_NAME", $gangObj->getFileNameForBackPDF());
		$t->set_var("BACK_PDF_THUMB", "<table  border=\"1\" cellspacing=\"0\" cellpadding=\"0\"><tr><td><img  src='./ad_printerQueue_thumbnail.php?gangRunID=" . $thisGangID . "&fileDescType=Back'></td></tr></table>");
		$t->allowVariableToContainBrackets("BACK_PDF_THUMB");
	}
	else{
	
		$t->set_var("BACK_PDF_NAME", "");
		$t->set_var("BACK_PDF_THUMB", "");
	
	}
	
	
	$projectPipedList = WebUtil::getPipeDelimetedStringFromArr($gangObj->getProjectIDlist());
	
	$t->set_var("PROJECT_LIST", $projectPipedList);
	
	

	$t->parse("gangBlockOut","gangBlock",true);
}


if(empty($gangRunIDs)){

	$t->set_block("origPage","EmptyResults","EmptyResultsout");
	$t->set_var("EmptyResultsout", "<font class='LargeBody'><font color='#330066'><b>Printer Queue is Empty</b></font></font>");
}


// Primary get it from the Session... and use Cookie as a last resort.
$confirmationName = WebUtil::GetSessionVar("GangRunConfirmName", WebUtil::GetCookie("GangRunConfirmName"));

$t->set_var("CONFIRM_NAME", WebUtil::htmlOutput($confirmationName));
	


header("Connection: close");  // To keep the thumbnails from timing out.

$t->pparse("OUT","origPage");



?>