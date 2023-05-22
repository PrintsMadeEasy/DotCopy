<?

require_once("library/Boot_Session.php");





$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);

$UserID = $AuthObj->GetUserID();



if(!$AuthObj->CheckForPermission("VIEW_CUSTOMER_SERVICE"))
	throw new Exception("Permission Denied");


//Either a Message ID or a category ID must come in the URL
$categoryid = WebUtil::GetInput("categoryid", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$messageid = WebUtil::GetInput("messageid", FILTER_SANITIZE_INT);
$changetree = WebUtil::GetInput("changetree", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

WebUtil::EnsureDigit($categoryid, false);

// If both parameters are null then we must be looking at the tree for the first time
// If so.. then we will need to syncronize the tree to the root folder
// First check to see if we have a cookie set that will help us remember whatever we selected last time.
//There must always be a category ID... but there may or may not be a message ID.
if($categoryid == "" && $messageid == ""){
	$changetree = "true";
	
	$categoryid = WebUtil::GetCookie( "CannedMsgLastCategory", "0" );
	$messageid = WebUtil::GetCookie( "CannedMsgLastMessage", "" );
}
else{

	$cookieTime = time()+60*60*24*90; // 3 months
	setcookie ("CannedMsgLastCategory", $categoryid, $cookieTime);
	setcookie ("CannedMsgLastMessage", $messageid, $cookieTime);
}



//If we hae a message ID then let's get the category ID from the DB to be exta safe.
//It may have been moved before somebody refreshed the page
if(!empty($messageid)){

	$dbCmd->Query("SELECT CategoryID FROM cannedmsgdata WHERE ID=$messageid");
	if($dbCmd->GetNumRows() == 0)
		throw new Exception("The message ID doesn't exist");
	
	$categoryid = $dbCmd->GetValue();
}



//Get a category object... used for manipulating multi-level trees.
$CategoryObj = new CategoryControl($dbCmd2, "cannedmsgcategories", "ID", "CategoryName", "PID");


$t = new Templatex(".");


$t->set_file("origPage", "ad_cannedmsg_body-template.html");


//Build a heirarchy list to show where we are in the tree
$AncestorHTML = "<a href='./ad_cannedmsg_body.php?categoryid=0&changetree=true' class='Heirarchy'>Main</a>";
$AncestorArr = $CategoryObj->getAncestors($categoryid, true);

$j=1;
foreach($AncestorArr as $ThisAncestor){
	$AncCatID = $ThisAncestor["ID"];
	$AncCatName = WebUtil::htmlOutput($ThisAncestor["CatName"]);
	
	//If we are not looking at a message, then the last item in a category does not need to be linked
	if(!empty($messageid) || $j < sizeof($AncestorArr))
		$AncestorHTML .= " <nobr><img src='./images/tree/arrow-right.gif' align='absmiddle'> <a href='./ad_cannedmsg_body.php?categoryid=$AncCatID&changetree=true' class='Heirarchy'>$AncCatName</a></nobr>";
	else
		$AncestorHTML .= " <img src='./images/tree/arrow-right.gif' align='absmiddle'> $AncCatName";
		
	$j++;
}




// The following if else block will determine if we are 1) Viewing a Message   2) Viewing a Category   3) Looking at the root folder
if(!empty($messageid)){

	$t->discard_block( "origPage", "CategoryBL");
	$t->discard_block( "origPage", "RootFolderBL");
	$t->discard_block( "origPage", "HideSubCategoryBL");
	
	
	$dbCmd->Query("SELECT * FROM cannedmsgdata WHERE ID=$messageid");
	$row = $dbCmd->GetRow();

	$t->set_var("MSG_TITLE", WebUtil::htmlOutput($row["MessageTitle"]));
	$t->set_var("MSG_DATA", preg_replace("/\n/", "<br>\n", WebUtil::htmlOutput($row["MessageData"])));
	$t->set_var("MSG_ID", WebUtil::htmlOutput($row["ID"]));
	$t->set_var("MESSAGE_VIEW", "message");
	$t->allowVariableToContainBrackets("MSG_DATA");
	
	
	
	
	//If we are looking at a Message then the last item in the ancestor list is the message title
	$AncestorHTML .= " <img src='./images/tree/arrow-right.gif' align='absmiddle'> " . WebUtil::htmlOutput($row["MessageTitle"]);


}
else if($categoryid == 0){

	// IF the category ID is on 0 then we are in the root of the folder.
	
	$t->discard_block( "origPage", "CategoryBL");
}
else{
	// Then we are viewing a category
	
	$t->discard_block( "origPage", "RootFolderBL");


	// Find out how many messages are contained within this category
	$dbCmd->Query("SELECT COUNT(*) FROM cannedmsgdata WHERE CategoryID=$categoryid");
	$MsgCount = $dbCmd->GetValue();

	// They should not be able to delete the folder if it is not empty
	if($MsgCount <> 0 || $CategoryObj->hasChildren($categoryid))
		$t->discard_block( "origPage", "Command_RemoveCategory_BL");

	
}


// If we are viewing a category, or the root folder... then show the message that are contained (if any)
if($messageid == ""){

	//Find out how many messages are contained within this category
	$dbCmd->Query("SELECT * FROM cannedmsgdata WHERE CategoryID=$categoryid ORDER BY MessageTitle ASC");
	$MsgCount = $dbCmd->GetNumRows();

	$t->set_block("origPage","MessageBL","MessageBLout");
	
	while($MsgRow = $dbCmd->GetRow()){

		$t->set_var("MSG_TITLE", WebUtil::htmlOutput($MsgRow["MessageTitle"]));
		$t->set_var("MSG_DATA", preg_replace("/\n/", "<br>\n", WebUtil::htmlOutput($MsgRow["MessageData"])));
		$t->set_var("MSG_ID", WebUtil::htmlOutput($MsgRow["ID"]));
		$t->set_var("MESSAGE_VIEW", "category");
		$t->allowVariableToContainBrackets("MSG_DATA");

		$t->parse("MessageBLout","MessageBL",true);
	}
	
	if(!$MsgCount)
		$t->set_var("MessageBLout", "");
}


// If we are viewing the root, or a folder.  Search for Sub Folders
$ChildCats = array();
if($messageid == ""){

	$ChildCats = $CategoryObj->getChildren($categoryid);
	
	$t->set_block("origPage","SubCategoryBL","SubCategoryBLout");
	
	foreach($ChildCats as $ThisCat){
		$t->set_var("SUB_CATNAME", WebUtil::htmlOutput($ThisCat["CatName"]));
		$t->set_var("SUB_CATID", $ThisCat["ID"]);
		
		$t->parse("SubCategoryBLout","SubCategoryBL",true);
	}
	
	if(!sizeof($ChildCats))
		$t->discard_block( "origPage", "HideSubCategoryBL");


}

// The root folder doesn't have a category name.
if($categoryid != 0){
	$dbCmd->Query("SELECT CategoryName FROM cannedmsgcategories WHERE ID=$categoryid");
	$CategoryName = $dbCmd->GetValue();
	
	$t->set_var("CATEGORYNAME", WebUtil::htmlOutput($CategoryName));
	$t->set_var("CATEGORYNAME_JS", addslashes(WebUtil::htmlOutput($CategoryName)));
	
	
	$categoryParentID = $CategoryObj->getParentID($categoryid);
	$t->set_var("CATEGRORYPARENTID", $categoryParentID);
		
	
}
else{
	// The parent of the Root folder is still the Root.
	$t->set_var("CATEGRORYPARENTID", "0");
	
	// Don't show the link allowing people to move up a level in the tree
	$t->discard_block( "origPage", "HideUpLevelLinkBL");
	
}



// If only a mesage is being displayed on the screen with no sub-folders... then we want to show a link to move up in the heirarchy.
if($categoryid == 0 || !empty($ChildCats) )
	$t->discard_block( "origPage", "HideUpLevelLinkForMessageNoCatBL");



//Change Tree is boolean... It tells us if we should syncronize the tree to match what is in the body.
if(!empty($changetree)){
	
	if($messageid == ""){
		$NodeType = "category";
		$NodeID = $categoryid;
	}
	else{
		$NodeType = "message";
		$NodeID = $messageid;
	}
	
	$t->set_var("NODE_TYPE", $NodeType);
	$t->set_var("NODE_ID", $NodeID);
	
	$t->set_var("CHANGETREE", "ChangeTreeState();");
}
else
	$t->set_var("CHANGETREE", "");


$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

$t->set_var("ANCESTORS", $AncestorHTML);
$t->allowVariableToContainBrackets("ANCESTORS");


$t->set_var(array(
	"CATEGORYID"=>$categoryid,
	"MESSAGEID"=>$messageid
	));



$t->pparse("OUT","origPage");



?>