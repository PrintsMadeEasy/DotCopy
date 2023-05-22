<?

require_once("library/Boot_Session.php");


$passiveAuthObj = Authenticate::getPassiveAuthObject();
if($passiveAuthObj->CheckIfLoggedIn() && $passiveAuthObj->CheckIfUserIDisMember($passiveAuthObj->GetUserID()))
	$isCustomer = false;
else
	$isCustomer = true;


$dbCmd = new DbCmd();
$domainObj = Domain::singleton();

$chatID = WebUtil::GetInput("chat_id", FILTER_SANITIZE_INT);
$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$fileNote = WebUtil::GetInput("fileNote", FILTER_SANITIZE_STRING_ONE_LINE);

$chatOjb = new ChatThread();
	
$chatOjb->loadChatThreadById($chatID, true);

$t = new Templatex();
$t->set_file("origPage", "chat_fileupload-template.html");	

$t->set_var("CHAT_ID", $chatID);


if(!empty($action)){
	
	if($action == "upload"){
		
		$ErrorStr = "An unknown error occured.";
		
		if(!array_key_exists("fileattach", $_FILES) || empty($_FILES["fileattach"]["size"])){
			$ErrorStr = "You forgot to choose a file for uploading or the file may be over 30MB.";
		}
		else if($_FILES["fileattach"]["size"] > (30 * 1024 * 1024)){
			$ErrorStr = "You forgot to choose a file for uploading or the file may be over 30MB.";
		}
		else{
	
			//Make the name of the file attachment always start with a "CA_" timestamp... to ensure it is unique... The CA_ helps us clean the files with a cron
			$AttachmentName = FileUtil::CleanFileName($_FILES["fileattach"]["name"]);
			
			if(!FileUtil::CheckIfFileNameIsLegal($AttachmentName))
				$ErrorStr = "This type of file may not be attached.";
			else{
			
				$tmpFileName = $_FILES["fileattach"]["tmp_name"];
				
				// Open the file from POST data 
				$filedata = fread(fopen($tmpFileName, "r"), filesize($tmpFileName));
				
				if($isCustomer)
					$chatOjb->addAttachmentFromCustomer($filedata, $AttachmentName, $fileNote);
				else
					$chatOjb->addAttachmentFromCsr($filedata, $AttachmentName, $fileNote, $passiveAuthObj->GetUserID());
					
				// No error string is good news.
				$ErrorStr = "";
			}

		}
		
		
		// If there is no error string... then leave the "Success Block", delete the other 2.
		if(empty($ErrorStr)){
			$t->discard_block("origPage", "UploadFormBL");
			$t->discard_block("origPage", "ErrorBL");
			$t->pparse("OUT","origPage");
			exit;
		}
		else{
			$t->set_var("ERROR_MESSAGE", WebUtil::htmlOutput($ErrorStr));
			
			$t->discard_block("origPage", "UploadFormBL");
			$t->discard_block("origPage", "SuccessBL");
			$t->pparse("OUT","origPage");
			exit;
		}
	}
	else{
		throw new Exception("The Action is undefined.");
	}
}

$t->discard_block("origPage", "ErrorBL");
$t->discard_block("origPage", "SuccessBL");



$t->pparse("OUT","origPage");




