<?php
	
//Stores translated incoming tracking clicks like "/track.php/d=145" etc. to server_emailNotifyTracking.php?id=145

require_once("library/Boot_Session.php");

$emailTrackHistoryId = WebUtil::GetInput("id", FILTER_SANITIZE_INT);

if(Domain::getDomainIDfromURL() != EmailNotifyJob::getDomainIdOfHistoryId($emailTrackHistoryId))
	throw new Exception("DomainID doesn't match");

VisitorPath::addRecord("Email View", $emailTrackHistoryId);
	
EmailNotifyJob::registerTrackIdClick($emailTrackHistoryId);
	
?>