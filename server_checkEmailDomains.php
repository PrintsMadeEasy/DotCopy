<?

// Called by cron once a week.

require_once("library/Boot_Session.php");

$domainCheck = new EmailDomainCheck();
	$domainCheck->checkDomains();