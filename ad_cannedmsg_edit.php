<?
require_once("library/Boot_Session.php");





$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);

$UserID = $AuthObj->GetUserID();



if(!$AuthObj->CheckForPermission("VIEW_CUSTOMER_SERVICE"))
	throw new Exception("Permission Denied");


// Either a Message ID or a category ID must come in the URL
// There must always be a category ID... but there may or may not be a message ID.
$categoryid = WebUtil::GetInput("categoryid", FILTER_SANITIZE_INT);
$messageid = WebUtil::GetInput("messageid", FILTER_SANITIZE_INT);

// Can either be "category" or "message"  ... depending on if where they are editing the message from 
$view = WebUtil::GetInput("view", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

// This lets us know if we are saving
$savemessage = WebUtil::GetInput("savemessage", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);


if($view <> "category" && $view <> "message")
	throw new Exception("Invalid View type");

// If we hae a message ID then let's get the category ID from the DB to be exta safe.
// It may have been moved before somebody refreshed the page
if(!empty($messageid)){

	$dbCmd->Query("SELECT CategoryID FROM cannedmsgdata WHERE ID=$messageid");
	if($dbCmd->GetNumRows() == 0)
		throw new Exception("The message ID doesn't exist");
	
	$categoryid = $dbCmd->GetValue();
}






if(!empty($savemessage)){

	$message = WebUtil::GetInput("message", FILTER_UNSAFE_RAW);
	$thetitle = WebUtil::GetInput("thetitle", FILTER_SANITIZE_STRING_ONE_LINE);
	
	WebUtil::checkFormSecurityCode();
	
	$OldTitle = "";

	//  Will tell us if we are editing an existing message or adding a new one
	if(!empty($messageid)){

		// Get the Old title.  Only when the title changes is when we need to Update the tree structure
		$dbCmd->Query("SELECT MessageTitle FROM cannedmsgdata WHERE ID=$messageid");
		$OldTitle = $dbCmd->GetValue();


		$UpdateDBarr["MessageData"] = $message;
		$UpdateDBarr["MessageTitle"] = $thetitle;

		$dbCmd->UpdateQuery("cannedmsgdata", $UpdateDBarr, "ID = $messageid");
		
		if($view == "category")
			$ShowCategoryOnRefresh_JS = "true";
		else
			$ShowCategoryOnRefresh_JS = "false";
	}
	else{

		$InsertDBarr["MessageData"] = $message;
		$InsertDBarr["MessageTitle"] = $thetitle;
		$InsertDBarr["CategoryID"] = $categoryid;

		// Get a new message ID after inserting
		$messageid = $dbCmd->InsertQuery("cannedmsgdata", $InsertDBarr);
		
		$ShowCategoryOnRefresh_JS = "true";
	}
	
	if($OldTitle <> $thetitle ){
		//Generate a new Tree Structure
		$CategoryObj = new CategoryControl($dbCmd2, "cannedmsgcategories", "ID", "CategoryName", "PID");
		CannedMessages::GenerateTree_JSON($dbCmd, $CategoryObj);
		
		$TrueUpdate_JS = "true";
	}
	else{
		$TrueUpdate_JS = "false";
	}

	
	print "<html><script> window.opener.MessageSaved($categoryid, $messageid, $TrueUpdate_JS, $ShowCategoryOnRefresh_JS);  self.close(); </script></html>";
}
else{
	$t = new Templatex(".");
	
	$t->set_file("origPage", "ad_cannedmsg_edit-template.html");
	
	$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());


	//  Will tell us if we are editing an existing message or adding a new one
	if(!empty($messageid)){

		$dbCmd->Query("SELECT * FROM cannedmsgdata WHERE ID=$messageid");
		$row = $dbCmd->GetRow();

		$t->set_var("MSG_TITLE", WebUtil::htmlOutput($row["MessageTitle"]));
		$t->set_var("MSG_DATA", WebUtil::htmlOutput($row["MessageData"]));
		$t->allowVariableToContainBrackets("MSG_DATA");


	}
	else{
		// Then we are adding a new message

		$t->set_var("MSG_TITLE", "");
		$t->set_var("MSG_DATA", "");
	}


	$t->set_var(array(
		"CATEGORYID"=>$categoryid,
		"MESSAGEID"=>$messageid,
		"VIEW"=>$view
		));


	$t->pparse("OUT","origPage");

}



?>