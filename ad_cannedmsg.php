<?

require_once("library/Boot_Session.php");






$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);

$UserID = $AuthObj->GetUserID();



if(!$AuthObj->CheckForPermission("VIEW_CUSTOMER_SERVICE"))
	throw new Exception("Permission Denied");



$cannedmessagecommand = WebUtil::GetInput("cannedmessagecommand", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
if($cannedmessagecommand){
	
	WebUtil::checkFormSecurityCode();

	$categoryid = WebUtil::GetInput("categoryid", FILTER_SANITIZE_INT);
	$messageid = WebUtil::GetInput("messageid", FILTER_SANITIZE_INT);
	$categoryname = WebUtil::GetInput("categoryname", FILTER_SANITIZE_STRING_ONE_LINE);
	$moveto = WebUtil::GetInput("moveto", FILTER_SANITIZE_INT);
	$returnurl = WebUtil::GetInput("returnurl", FILTER_SANITIZE_URL);
	
	
	//Get the heiarchy of the folders
	$CategoryObj = new CategoryControl($dbCmd2, "cannedmsgcategories", "ID", "CategoryName", "PID");
	
	if($cannedmessagecommand == "ChangeCategoryName"){
	
		if(empty($categoryname))
			throw new Exception("Problem with Category Name");
			
		$CategoryObj->renameCategory($categoryname, $categoryid);
	}
	else if($cannedmessagecommand == "NewCategoryName"){
	
		if(empty($categoryname))
			throw new Exception("Problem with Category Name");

		$CategoryObj->insertCategory($categoryname, $categoryid);
	}
	
	else if($cannedmessagecommand == "MoveCategory"){

		if(!$CategoryObj->moveCategory($categoryid, $moveto))
			throw new Exception("Problem moving the category:<br><br> " . $CategoryObj->getErrorMessage() . "<br><br><a href='javascript:history.back();'>Click here</a> to go back.");
	
	}
	else if($cannedmessagecommand == "MoveMessage"){

		if(!$CategoryObj->categoryExists($moveto) && $moveto <> 0)
			throw new Exception("The category that you are moving to does not exist");
			
		$UpdateDBarr["CategoryID"] = $moveto;
		$dbCmd->UpdateQuery("cannedmsgdata", $UpdateDBarr, "ID = $messageid");
	}

	else if($cannedmessagecommand == "DeleteCategory"){
	
		//Before we delete the category... Make sure that there are no messages attached.
		$dbCmd->Query("SELECT COUNT(*) FROM cannedmsgdata WHERE CategoryID=$categoryid");
		if($dbCmd->GetValue() == 0)
			$CategoryObj->deleteCategory($categoryid);
	}
	else if($cannedmessagecommand == "DeleteMessage"){
		$dbCmd->Query("DELETE FROM cannedmsgdata WHERE ID=$messageid");
	}
	else
		throw new Exception("Problem with the canned message command. : $cannedmessagecommand");
	
	
	//Generate a new Tree
	$CategoryObj_new = new CategoryControl($dbCmd2, "cannedmsgcategories", "ID", "CategoryName", "PID");
	CannedMessages::GenerateTree_JSON($dbCmd, $CategoryObj_new);


	header("Location: " . WebUtil::FilterURL($returnurl));
}






$categoryid = WebUtil::GetInput("categoryid", FILTER_SANITIZE_INT);
$messageid = WebUtil::GetInput("messageid", FILTER_SANITIZE_INT);


$t = new Templatex(".");

$t->set_file("origPage", "ad_cannedmsg_frameset-template.html");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());


$t->set_var(array(
	"CATEGORYID"=>$categoryid,
	"MESSAGEID"=>$messageid
	));



$t->pparse("OUT","origPage");




?>