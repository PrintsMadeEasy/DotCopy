<?

require_once("library/Boot_Session.php");




$projectrecord = WebUtil::GetInput("projectrecord", FILTER_SANITIZE_INT);
$artfileinfo = WebUtil::GetInput("artfileinfo", FILTER_UNSAFE_RAW);
$viewtype = WebUtil::GetInput("viewtype", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);



$dbCmd = new DbCmd();

$errormessage = "";


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_general);
$UserID = $AuthObj->GetUserID();


ProjectBase::EnsurePrivilagesForProject($dbCmd, $viewtype, $projectrecord);

// If the artfile is not NULL then it means that the form was previously submitted ... so record the XML file into the database
// Otherwise display them a form so that they can edit the XML file
if(!empty($artfileinfo)){

	// Since we added line breaks when creating the form we need to take them away now...  That way it will go back in the database the same way that it came out 
	$artfileinfo =  preg_replace("/\n/", "", $artfileinfo);

	if(in_array($viewtype, array("proof", "admin", "projectsordered")))
		ProjectHistory::RecordProjectHistory($dbCmd, $projectrecord, "Manual Edit", $UserID);

	// Update the XML file within the database
	ArtworkLib::SaveArtXMLfile($dbCmd, $viewtype, $projectrecord, $artfileinfo);

	print "<html><script>self.close();</script></html>";
}
else{


	$ArtFile = ArtworkLib::GetArtXMLfile($dbCmd, $viewtype, $projectrecord);

	// Put everything into html entities for the browser
	$ArtFile = WebUtil::htmlOutput($ArtFile);

	print "<html><body bgcolor='#3366CC'><form name='updatexml' action='".$_SERVER['PHP_SELF']."' method='post'><input type='hidden' name='form_sc' value='".WebUtil::getFormSecurityCode()."'><input type='hidden' name='form_sc' value='".WebUtil::getFormSecurityCode()."'>";
	print "<input type='hidden' name='projectrecord' value='$projectrecord'>";
	print "<input type='hidden' name='viewtype' value='$viewtype'>";
	print "<textarea name='artfileinfo' style='width:520px; height:530px; font-face:arial; font-size:16px; background-color:#FFFFF3'>";
	print $ArtFile;
	print "</textarea><br><img src='./images/transparent.gif' width='5' height='10'><br><table cellpadding='0' cellspacing='0' border='0' width='90%'><tr><td class='body' align='right'><input type='submit' name='save' value='Save'> <input type='button' onClick='self.close();' name='Cancel' value='Cancel'></td></tr></table>";
	print "</form></body></html>";
}


?>