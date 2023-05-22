<?

require_once("library/Boot_Session.php");


// ----------  Normaly actions going insde of the script that is displaying the page 				-----------
// ----------  However if the action is shared across many different pages then the action should go in here 	-----------



$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();


$user_sessionID =  WebUtil::GetSessionID();

$mysql_timestamp = date("YmdHis");


$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);


if($action == "changeto300dpi"){

	$ProjectID = WebUtil::GetInput("projectrecord", FILTER_SANITIZE_INT);
	$ViewType = WebUtil::GetInput("editorview", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
	
	ProjectBase::EnsurePrivilagesForProject($dbCmd, $ViewType, $ProjectID);

	$ArtworkXMLstring = ArtworkLib::GetArtXMLfile($dbCmd, $ViewType, $ProjectID);
	$ArtworkInfoObj = new ArtworkInformation($ArtworkXMLstring);

	for($i=0; $i<sizeof($ArtworkInfoObj->SideItemsArray); $i++)
		$ArtworkInfoObj->SideItemsArray[$i]->dpi = "300";
	
	ArtworkLib::SaveArtXMLfile($dbCmd, $ViewType, $ProjectID, $ArtworkInfoObj->GetXMLdoc());

	$ReturnURL = WebUtil::GetInput("returl", FILTER_SANITIZE_URL);
	
	VisitorPath::addRecord("Artwork DPI Switch");
	
	// Make sure they are not trying to change the DPI from the backside of the artwork.
	// If we redirected back to the backside... then it would load the temporary Artwork file from the session instead of pulling the artwork out of the DB
	$ReturnURL = preg_replace("/sidenumber=\d+/", "sidenumber=", $ReturnURL);

	session_write_close();
	header("Location: " . WebUtil::FilterURL($ReturnURL) . "&nocaching=" . time(), true, 302);
}
else if($action == "PaypalCheckout"){
	WebUtil::SetCookie("ShowPaypalButton", "yes", 1000);
	session_write_close();
	header("Location: shoppingcart.php", true, 302);
}
else{
	throw new Exception("No actions were passed.");
}



?>