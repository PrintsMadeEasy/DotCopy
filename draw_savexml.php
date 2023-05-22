<?

require_once("library/Boot_Session.php");



$switchsides = WebUtil::GetInput("switchsides", FILTER_SANITIZE_STRING_ONE_LINE);	
$viewtype = WebUtil::GetInput("viewtype", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);	




// Store the XML document from the Artwork into a session variable.
// We only save it to the database when they place it into there shopping cart.


$errorFlag = false;


// After Ver. 4.0.6 some changes to $HTTP_RAW_POST_DATA were made.
// If the content type is known by PHP then this var is empty, otherwise you have the data in it.
// There is an option in php.ini  you should set.....   always_populate_raw_post_data = On
// Or you can confuse the content type before you upload the XML file which is what I did.
if(isset($GLOBALS['HTTP_RAW_POST_DATA']))
	$dataFileXML = $GLOBALS['HTTP_RAW_POST_DATA'];
else
	$dataFileXML = "";

if(empty($dataFileXML)){
	$ErrorDescription = "Artwork data is empty.";
	$errorFlag = true;
}
	


// encoding="ISO-8859-1"  was required for the Xpat processor after upgrading to PHP5 because of UTF-8 symbols like latin characters.
$dataFileXML = preg_replace("/^\s*<\?xml version=\"1\.0\"\s*\?>/", "<?xml version=\"1.0\" encoding=\"ISO-8859-1\" ?>", $dataFileXML);


if(strlen($dataFileXML) > 15777215){
	$ErrorDescription = "Artwork Data file too big";
	$errorFlag = true;
}



// Put the XML doucment into the session variables.  The XML document is placed into the POST body.  It does not give it name/value pairs.
// No need to translate htmlentities because it was already done by the Flash XML object

// If the switch sides variable was passed in the URL then it means we dont want to permanently record the artwork.
// Don't check if the value is empty because the side number could be "0" zero
if(preg_match("/^\d+$/", $switchsides)){

	if(!$errorFlag)
		$HTTP_SESSION_VARS['TempXMLholder'] = $dataFileXML;

}
else if($viewtype == "proof"){

	
	$dbCmd = new DbCmd();

	// Make sure they are logged in.
	$AuthObj = new Authenticate(Authenticate::login_ADMIN);
	$UserID = $AuthObj->GetUserID();

	// This session variable should have been sent when the proofing page was being generated
	// It will contain the project ID of the proof we are editing
	if(!isset($HTTP_SESSION_VARS['ProofProjectID'])){

		$errorFlag = true;
		$ErrorDescription = "Project Record was not found.";
	}
	else{

		$projectorderID = $HTTP_SESSION_VARS['ProofProjectID'];
		
		
		// This will ensure domain security.
		ProjectBase::EnsurePrivilagesForProject($dbCmd, $viewtype, $projectorderID);
		
		// Find out if a lock exists on this order from someone else... if not the function-call will set the lock
		$ArbResult = Order::ArtworkArbitration($dbCmd, Order::GetOrderIDfromProjectID($dbCmd, $projectorderID), $UserID, false);
		if(!empty($ArbResult)){
			$errorFlag = true;
			$ErrorDescription = addslashes(UserControl::GetNameByUserID($dbCmd, $ArbResult)) . " is working on this order.";
		}
		else if(MailingBatch::checkIfProjectBatched($dbCmd, $projectorderID)){
		
			// We don't want people to change the artwork on a mailing batch because there could be a lot of money associated with Postage, etc.  There is not an easy way to issolate a Project once it has been grouped with others, printed, etc.
			$errorFlag = true;
			$ErrorDescription = addslashes("You can not modify the artwork of a Project which has been already included within a Mailing Batch.  If you really need to change the artwork then cancel the Mailing Batch or issue a reprint.");
		}
		else{
			if(!$errorFlag){
				ArtworkLib::SaveArtXMLfile($dbCmd, $viewtype, $projectorderID, $dataFileXML);

				// In case they uploaded an image onto the Artwork... it will move it from the "session" table into the "saved" table
				ArtworkLib::SaveImagesInSession($dbCmd, $viewtype, $projectorderID, ImageLib::GetImagesSavedTableName($dbCmd), ImageLib::GetVectorImagesSavedTableName($dbCmd));

				$projectObj = $projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, "proof", $projectorderID);

				// If there is Data File... don't go through the extra work of parsing the Data file and error checking 
				// Saving a new artwork won't change anything
				if($projectObj->isVariableData() && $projectObj->getVariableDataStatus() != "D"){
					// Changing the Artwork could cause Variable Data Errors, if Variables become missing or something
					VariableDataProcess::SetVariableDataStatusForProject($dbCmd, $projectorderID, "proof");
				}
			}

		}
		

		// Some funny things can happen with the artwork file in the temp session variable
		// There are some rare cases where it accidently saves an artwork from a previous artwrok.
		// That could mess up production if they are printing a double-sided order with single-sided artwork... it would throw the interleaving off
		// This will ensure the artwork matches the product options for sure... even if the artwork is wrong
		ProjectBase::ChangeArtworkBasedOnProjectOptions($dbCmd, $projectorderID, $viewtype);

		//Copy over the Artwork to the corresponding SavedProject ID... if the link exists
		ProjectOrdered::CloneOrderForSavedProject($dbCmd, $projectorderID);
		
		
		// So we can generate some preview JPEGs in the background (because the artwork changed).
		ProjectOrdered::ProjectOrderedNeedsArtworkUpdate($dbCmd, $projectorderID);
		
		
		// Find out what the Last entry in the Project History is.
		// If there has not been another "Artwork Modified" entry within 10 minutes then record that into the history
		$dbCmd->Query("SELECT Note, UNIX_TIMESTAMP(Date) AS TimeStamp FROM projecthistory WHERE ProjectID=$projectorderID ORDER BY ID DESC LIMIT 1");
		$row = $dbCmd->GetRow();
		
		if(!preg_match("/Artwork Modified/i", $row["Note"]) || (time() - $row["TimeStamp"] > 600))
			ProjectHistory::RecordProjectHistory($dbCmd, $projectorderID, "Artwork Modified", $UserID);

	}

}
else{
	// this session variable is used when we upload our finished artwork for all sides.
	if(!$errorFlag)
		$HTTP_SESSION_VARS['draw_xml_document'] = $dataFileXML;

}

session_write_close();


header ("Content-Type: text/xml");


// It seems that when you hit session_start it will send a Pragma: NoCache in the header
// When comminicating over HTTPS there is a bug with IE.  It can not get the flash documents after they have finished downloading because they have already expired
// We can allow the file to be cached be sending this header.  When requesting the document you can put a ?noache=timestamp
// This is the only way to get flash communication to work over HTTPS with session variables
header("Pragma: public");


if(!$errorFlag){

	print "<?xml version=\"1.0\" ?>\n";
	print "<response>"; 
	print "<success>good</success>";
	print "<description></description>";
	print "</response>"; 

}
else{
	
	// Don't bother the webmaster with errors relating to order arbitration.
	if(!preg_match("/" . preg_quote("is working on this order") . "/", $ErrorDescription)){
		VisitorPath::addRecord("Edit Artwork XML Save Error", $ErrorDescription);
		WebUtil::WebmasterError("Problem With Artwork: $ErrorDescription" . "SessionID: " . WebUtil::GetSessionID() . " View: " . $viewtype);
	}
	
	print "<?xml version=\"1.0\" ?>\n";
	print "<response>"; 
	print "<success>bad</success>";
	print "<description>". $ErrorDescription ."</description>";
	print "</response>"; 

}


?>
