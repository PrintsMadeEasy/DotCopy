<?

require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();




//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();



$task = WebUtil::GetInput("task", FILTER_SANITIZE_STRING_ONE_LINE);
$priority = WebUtil::GetInput("priority", FILTER_SANITIZE_STRING_ONE_LINE);
$linkdesc = WebUtil::GetInput("linkdesc", FILTER_SANITIZE_STRING_ONE_LINE);
$taskref = WebUtil::GetInput("taskref", FILTER_SANITIZE_INT);
$refreshParent = WebUtil::GetInput("refreshParent", FILTER_SANITIZE_STRING_ONE_LINE);

$days = WebUtil::GetInput("days", FILTER_SANITIZE_INT);
$hours = WebUtil::GetInput("hours", FILTER_SANITIZE_INT);
$minutes = WebUtil::GetInput("minutes", FILTER_SANITIZE_INT);


WebUtil::checkFormSecurityCode();



$reminderDate = null;

if(!empty($days) || !empty($hours) || !empty($minutes)){

	$reminderDate = time();

	// If the Hours and mintues are empty... then we want to remind them first thing in the morning.
	if(empty($hours) && empty($minutes))
		$reminderDate = mktime(0, 0, 1, date('n'), date("j"), date('Y'));

	if(!empty($days))
		$reminderDate += $days * 60 * 60 * 24;

	if(!empty($hours))
		$reminderDate += $hours * 60 * 60;

	if(!empty($minutes))
		$reminderDate += $minutes * 60;	
}	



$newTask = new Task($dbCmd);

$newTask->setAttachment($linkdesc);
$newTask->setReferenceID($taskref);
$newTask->setCreatedByUserID($UserID);
$newTask->setAssignedToUserID($UserID);
$newTask->setDescription($task);
$newTask->setPriority(strtoupper($priority));
if($reminderDate)
	$newTask->setReminderDate($reminderDate);

$newTask->addNewTask();


$t = new Templatex(".");

$t->set_file("origPage", "ad_task_save-template.html");


if(!empty($refreshParent))
	$t->set_var("REFRESH_PARENT", "window.opener.location = window.opener.location;");
else
	$t->set_var("REFRESH_PARENT", "");
	


$t->pparse("OUT","origPage");


?>