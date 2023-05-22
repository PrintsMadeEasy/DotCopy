<?

require_once("library/Boot_Session.php");


$subject = WebUtil::GetInput("subject", FILTER_SANITIZE_STRING_ONE_LINE, "Subject Not Sent in Server Notification");

// Send out an email to the system administrator
WebUtil::SendEmail(Constants::GetMasterServerEmailName(), Constants::GetMasterServerEmailAddress(), Constants::GetAdminName(), Constants::GetAdminEmail(), $subject, date("F j, Y, g:i a"));

?>