<?


require_once("library/Boot_Session.php");




$dbCmd = new DbCmd();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

if(!$AuthObj->CheckForPermission("VIEW_CUSTOMER_SERVICE"))
	throw new Exception("Permission Denied");



$cs_message_unique = WebUtil::GetInput("cs_message_unique", FILTER_SANITIZE_INT);
$command = WebUtil::GetInput("command", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$removefile = WebUtil::GetInput("removefile", FILTER_SANITIZE_STRING_ONE_LINE);




//The variable $cs_message_unique is a unqiue number, everytime somebody clicks on "Reply to customer".
//We keep a session variable (array) for keeping track of which attachments have been written to disk ... that applies to the "reply window
//That way when we are sending the message we can figure out what attachments should be sent with it.    It is a 2D array... the bottom dimension is the name of the file.
if (!isset($HTTP_SESSION_VARS['CSattachmentListArr']))
	$HTTP_SESSION_VARS['CSattachmentListArr'] = array();

if (!isset($HTTP_SESSION_VARS['CSattachmentListArr']["$cs_message_unique"]))
	$HTTP_SESSION_VARS['CSattachmentListArr']["$cs_message_unique"] = array();




$ErrorStr = "";


$t = new Templatex(".");


$t->set_file("origPage", "ad_cs_attachments-template.html");



if($command == "delete"){

	//If we are deleting the file... then just remove it from our session variable.  Let the cron actually delete the file.
	foreach($HTTP_SESSION_VARS['CSattachmentListArr']["$cs_message_unique"] as $thisKey => $ThisFileName){
		if($ThisFileName == $removefile){
			unset($HTTP_SESSION_VARS['CSattachmentListArr']["$cs_message_unique"][$thisKey]);
			break;
		}
	}


}
else if($command == "upload"){

	if(!array_key_exists("fileattach", $_FILES) || empty($_FILES["fileattach"]["size"])){
		$ErrorStr = "<br>You forgot to choose a file for uploading.<br><font size='-2'>Or the file may be over 2MB</font><br>&nbsp;";
	}
	else{

		//Make the name of the file attachment always start with a "CA_" timestamp... to ensure it is unique... The CA_ helps us clean the files with a cron
		$AttachmentName = "CA_" . substr(md5(time() . "extraSequreity..."), 0, 9) . "_" . FileUtil::CleanFileName($_FILES["fileattach"]["name"]);
		
		if(!FileUtil::CheckIfFileNameIsLegal($AttachmentName))
			$ErrorStr = "<br>This type of file may not be attached.<br>&nbsp;";
		else{
		
			$tmpFileName = $_FILES["fileattach"]["tmp_name"];
			
			// Open the file from POST data 
			$filedata = fread(fopen($tmpFileName, "r"), filesize($tmpFileName));
			
			$FileNameForDisk = Constants::GetFileAttachDirectory() . "/" . $AttachmentName;

			// Put image data into the temp file
			$fp = fopen($FileNameForDisk, "w");
			fwrite($fp, $filedata);
			fclose($fp);
			
			// record what file name has been attached to this CS 
			$HTTP_SESSION_VARS['CSattachmentListArr']["$cs_message_unique"][] = $AttachmentName;
			
		}


	}

}
else if($command != "new"){
	throw new Exception("Error with command: $command");
}



$t->set_block("origPage","RowBL","RowBLout");

$counter = 0;

foreach($HTTP_SESSION_VARS['CSattachmentListArr']["$cs_message_unique"] as $ThisFileName){

	$t->set_var(array(
		"FILE_NAME"=>$ThisFileName,
		"FILE_NAME_STRIPPED"=>CustService::StripPrefixFromCustomerServiceAttachment($ThisFileName),
		));

	$t->parse("RowBLout","RowBL",true);

	$counter++;
}




if($counter == 0){
	$t->set_block("origPage","EmptyRows","EmptyRowsout");
	$t->set_var(array("EmptyRowsout"=>"<br><br>&nbsp;&nbsp;&nbsp;<font class='SmallBody'>No attachments yet.</font><br><br>"));
	$AttachmentDesc = "Currently No Attachments";
}
else{
	$AttachmentDesc = $counter . " attachment" . LanguageBase::GetPluralSuffix($counter, "", "s");
}





$t->set_var("ATTACHMENT_DESCRIPTION", $AttachmentDesc);


$t->set_var("ERROR_STR", $ErrorStr);
$t->allowVariableToContainBrackets("ERROR_STR");

$t->set_var("CS_MESSAGE_UNIQUE", $cs_message_unique);


$t->pparse("OUT","origPage");


?>