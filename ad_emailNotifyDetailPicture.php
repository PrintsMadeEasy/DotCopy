<?php

require_once("library/Boot_Session.php");

$dbCmd = new DbCmd();

$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$userID = $AuthObj->GetUserID();

if(!$AuthObj->CheckForPermission("EMAIL_NOTIFY_MESSAGE_EDIT"))
	throw new Exception("Permission Denied.");

$t = new Templatex(".");
$t->set_file("origPage", "ad_emailNotifyDetailPicture-template.html");

$view = WebUtil::GetInput("view", FILTER_SANITIZE_STRING_ONE_LINE);

if($view == "detail"){
	
	$pictureId = WebUtil::GetInput("pictureid",  FILTER_SANITIZE_INT);
	$t->set_var("PICTURELINK", EmailNotifyMessages::getSecuredPictureLink($pictureId));
	$t->set_var("PICTUREID", $pictureId);
}
else{
}

$t->pparse("OUT","origPage");
