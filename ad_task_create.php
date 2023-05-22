<?

require_once("library/Boot_Session.php");


$taskref = WebUtil::GetInput("taskref", FILTER_SANITIZE_INT);
$linkdesc = WebUtil::GetInput("linkdesc", FILTER_SANITIZE_STRING_ONE_LINE);
$refreshParent = WebUtil::GetInput("refreshParent", FILTER_SANITIZE_STRING_ONE_LINE);



$dbCmd = new DbCmd();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

$t = new Templatex(".");

$t->set_file("origPage", "ad_task_create-template.html");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());


$t->set_var(array("LINK_DESC"=>$linkdesc, 
			"REFID"=>$taskref,
			"REFRESH_PARENT"=>$refreshParent));


$taskForHTMLstart = " - <font class='ReallySmallBody'>Attached To:</font> <font class='SmallBody'><b>";
$taskForHTMLend ="</b></font>";

if($linkdesc == "user")

	$t->set_var("TASK_FOR", $taskForHTMLstart . WebUtil::htmlOutput(UserControl::GetCompanyOrNameByUserID($dbCmd, $taskref)) . $taskForHTMLend);
else if($linkdesc == "order")
	$t->set_var("TASK_FOR", $taskForHTMLstart . "Order #" . $taskref . $taskForHTMLend);
else if($linkdesc == "project")
	$t->set_var("TASK_FOR", $taskForHTMLstart . "P" . $taskref . $taskForHTMLend);
else if($linkdesc == "csitem")
	$t->set_var("TASK_FOR", $taskForHTMLstart . "CS Item: " . $taskref . $taskForHTMLend);
else if($linkdesc == "message")
	$t->set_var("TASK_FOR", $taskForHTMLstart . "Message ID: " . $taskref . $taskForHTMLend);
else
	$t->set_var("TASK_FOR", ""); 

$t->allowVariableToContainBrackets("TASK_FOR");










// If we are linking this task to an item... then lets show if there are any tasks already linked to this item.
if(!empty($linkdesc)){
			
	$taskCollectionObj = new TaskCollection();
	$taskCollectionObj->limitShowTasksBeforeReminder(true);
	$taskCollectionObj->limitUserID($UserID);
	$taskCollectionObj->limitAttachedToName($linkdesc);
	$taskCollectionObj->limitAttachedToID($taskref);
	$taskCollectionObj->limitUncompletedTasksOnly(true);
	$taskObjectsArr = $taskCollectionObj->getTaskObjectsArr();
	
	$tasksDisplayObj = new TasksDisplay($taskObjectsArr);
	$tasksDisplayObj->setTemplateFileName("tasksSmall-template.html");
	$tasksDisplayObj->setReturnURL("");
	$tasksDisplayObj->displayAsHTML($t,"EXISTING_TASKS");		
	
}
else{
	$t->set_var("EXISTING_TASKS", ""); 
}





$t->pparse("OUT","origPage");



?>