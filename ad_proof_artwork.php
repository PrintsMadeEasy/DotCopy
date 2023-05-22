<?

require_once("library/Boot_Session.php");




$projectid = WebUtil::GetInput("projectid", FILTER_SANITIZE_INT);


$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();




//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("EDIT_ARTWORK"))
	WebUtil::PrintAdminError("Not Available");

	
	
ProjectBase::EnsurePrivilagesForProject($dbCmd, "ordered", $projectid);
$domainID = ProjectBase::getDomainIDofProjectRecord($dbCmd, "ordered", $projectid);
	
// Make sure that all Objects on this script are using the same Domain ID.
// This will also show the Logo of the Domain that was ordered through in the Nav Bar.
Domain::enforceTopDomainID($domainID);
	


$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, "ordered", $projectid);


// Only let them see the "proof" button if the Status of this project is New, On Hold, or has an Artwork Problem
$dbCmd->Query("SELECT Status, ProductID, FromTemplateID, FromTemplateArea FROM projectsordered WHERE ID=$projectid");
$row = $dbCmd->GetRow();

$ProjectStatus = $row["Status"];
$ProductID = $row["ProductID"];
$FromTemplateID = $row["FromTemplateID"];
$FromTemplateArea = $row["FromTemplateArea"];



$productObj = Product::getProductObj($dbCmd, $ProductID, true);




// If this artwork is intended for mailing... then people without permissions to proof such projects shouldn't see projects to do so.
if($productObj->hasMailingService() && !$AuthObj->CheckForPermission("MAILING_BATCHES_PROOF"))
	$mailBatchPrivilages = false;
else
	$mailBatchPrivilages = true;





$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
if(!empty($action)){

	WebUtil::checkFormSecurityCode();
	
	if($action == "originalartwork"){
	
		// We don't want people to change the artwork on a mailing batch because there could be a lot of money associated with Postage, etc.  There is not an easy way to issolate a Project once it has been grouped with others, printed, etc.
		if(MailingBatch::checkIfProjectBatched($dbCmd, $projectid))
			WebUtil::PrintAdminError("You can not change the artwork on a Project which has been already included within a Mailing Batch.  If you really need to change the artwork then cancel the Mailing Batch first.");

		// Get the changed artwork file so that we can store it temporarily before deleting it
		$dbCmd->Query("SELECT ArtworkFileModified FROM projectsordered WHERE ID=$projectid");

		// Store Artwork XML file in a session variable... The Key to the Session Var name is made up of the Project ID
		$HTTP_SESSION_VARS['ArtworkModified' . $projectid] = $dbCmd->GetValue();

		// Get rid of the changes that the administrator made to the artwork.
		// Don't filter the artwork because it won't let a blank value go in otherwise.
		ArtworkLib::SaveArtXMLfile($dbCmd, "proof", $projectid, "", false);

		ProjectBase::ChangeArtworkBasedOnProjectOptions($dbCmd, $projectid, "admin");
		
		ProjectHistory::RecordProjectHistory($dbCmd, $projectid, "Original Artwork", $UserID);

		header("Location: ./ad_proof_artwork.php?projectid=" . $projectid);
		exit;
	}
	else if($action == "reapplyartwork"){

		// We don't want people to change the artwork on a mailing batch because there could be a lot of money associated with Postage, etc.  There is not an easy way to issolate a Project once it has been grouped with others, printed, etc.
		if(MailingBatch::checkIfProjectBatched($dbCmd, $projectid))
			WebUtil::PrintAdminError("You can not change the artwork on a Project which has been already included within a Mailing Batch.  If you really need to change the artwork then cancel the Mailing Batch first.");


		if(isset($HTTP_SESSION_VARS['ArtworkModified' . $projectid])){

			// Put the admin changes back in the DB --#
			ArtworkLib::SaveArtXMLfile($dbCmd, "proof", $projectid, $HTTP_SESSION_VARS['ArtworkModified' . $projectid]);

			unset($HTTP_SESSION_VARS['ArtworkModified' . $projectid]);
		}


		ProjectBase::ChangeArtworkBasedOnProjectOptions($dbCmd, $projectid, "admin");
		
		ProjectHistory::RecordProjectHistory($dbCmd, $projectid, "Artwork Changes Re-Applied", $UserID);

		header("Location: ./ad_proof_artwork.php?projectid=" . $projectid);
		exit;
	}
	else if($action == "changeDPIsetting"){


		// We don't want people to change the artwork on a mailing batch because there could be a lot of money associated with Postage, etc.  There is not an easy way to issolate a Project once it has been grouped with others, printed, etc.
		if(MailingBatch::checkIfProjectBatched($dbCmd, $projectid))
			WebUtil::PrintAdminError("You can not change the artwork on a Project which has been already included within a Mailing Batch.  If you really need to change the artwork then cancel the Mailing Batch first.");


		$dpisetting = WebUtil::GetInput("dpisetting", FILTER_SANITIZE_INT);	

		ArtworkLib::ChangeArtworkDPI($dbCmd, "proof", $projectid, $dpisetting);

		//Copy over the Artwork to the corresponding SavedProject ID... if the link exists
		ProjectOrdered::CloneOrderForSavedProject($dbCmd, $projectid);

		header("Location: " . WebUtil::FilterURL(WebUtil::GetInput("returnurl", FILTER_SANITIZE_URL)));
		exit;
	
	}
	else if($action == "updatecustomernote"){
	
		$notesmsg = WebUtil::GetInput("notesmsg", FILTER_SANITIZE_STRING_ONE_LINE);
		$custid = WebUtil::GetInput("custid", FILTER_SANITIZE_INT);
		
		$dbCmd->UpdateQuery("users", array("CustomerNotes"=>$notesmsg), "ID=$custid");

		header("Location: ./" . WebUtil::FilterURL(WebUtil::GetInput("returnurl", FILTER_SANITIZE_URL)));
		exit;
	}
	else if($action == "markproofed"){
	
		// This is so that we can mark a project proofed within an HTML proofing page... without having to reload.
		// Javascript can do XML communication
		
		
		$errorFlag = false;
		$ErrorDescription = "";
		
		
		// Get the dimensions on the front (and possibly back sides) of the artwork.  Show a warning message if the dimensions don't match what was saved on the Product
		$artInfoObj = new ArtworkInformation($projectObj->getArtworkFile());
		$frontWidth = $artInfoObj->SideItemsArray[0]->contentwidth;
		$frontHeight = $artInfoObj->SideItemsArray[0]->contentheight;
		
		if(isset($artInfoObj->SideItemsArray[1])){
		
			$backWidth = $artInfoObj->SideItemsArray[1]->contentwidth;
			$backHeight = $artInfoObj->SideItemsArray[1]->contentheight;
			
			if($frontWidth != $backWidth || $frontHeight != $backHeight){
				$errorFlag = true;
				$ErrorDescription = "The dimensions on the Front of the artwork do not match the back.\n\nTo proof this artwork (without fixing) you must do it manually from the Project Screen.";
			}
		}
		
		
		$productInchesWide = $productObj->getArtworkCanvasWidth();
		$productInchesHigh = $productObj->getArtworkCanvasHeight();
		
		$artworkInchesWide = $frontWidth / 96;
		$artworkInchesHigh = $frontHeight / 96;
		
		
		if( abs(1 - ($productInchesWide / $artworkInchesWide)) > 0.01 ||  abs(1 - ($productInchesHigh / $artworkInchesHigh)) > 0.01){
			$errorFlag = true;
			$ErrorDescription = "The dimensions of this artwork do not match the product's PDF Profile (" . $productInchesWide . " inches x " . $productInchesHigh . " inches).\nMaybe an artwork mix-up occured?\n\nTo proof this artwork (without fixing) you must do it manually from the Project Screen.";
		}
		
		

		// Only let them update the DB through this function if the project is New, Proofed, On Hold, or has an Artwork Problem
		$ProjectStatus = ProjectOrdered::GetProjectStatus($dbCmd, $projectid);

		if($ProjectStatus != "N" && $ProjectStatus != "P" && $ProjectStatus != "H" && $ProjectStatus != "A" && $ProjectStatus != "W" && $ProjectStatus != "L"){
			$errorFlag = true;
			$ErrorDescription = "You can no longer change the status with this button.  \nThe status may have already been changed by someone else.";
		}
		else if(!ProjectOrdered::CheckIfBleedSettingsAreSaved($dbCmd, $projectid)){
			$errorFlag = true;
			$ErrorDescription = "You can not proof the artwork until the PDF settings are saved.";
		}
		else if($productObj->hasMailingService()){
		
			// If it has mailing services then make sure that all of the required variables are set in the artowrk
			$artworkVarsObj = new ArtworkVarsMapping($projectObj->getArtworkFile(), $projectObj->getVariableDataArtworkConfig());
			
			$mailBatchObj = new MailingBatch($dbCmd, $UserID);
			
			$mandatoryColumnNames = $mailBatchObj->getMandatoryColumnNames();
			
			foreach($mandatoryColumnNames as $thisMandatoryCol){
			
				$mailingCol = $mailBatchObj->getFieldMappingPositionOfVariable($artworkVarsObj, $thisMandatoryCol);
				
				if(empty($mailingCol)){
					$errorFlag = true;
					$ErrorDescription = "You can not proof this artwork because it will be mailed and one of the required address variables is missing.  \nVariable Name: " . $thisMandatoryCol;
					break;
				}
			}
			
			
			// Check 10 lines worth of data (if we have that Much and make sure that some of the critical columns for mailing are not left blank.
			// We prefer not to use the first line (Zero based)... because that may be a column header... but if we only have one line of data (which is extremely rare)... then just use that.
			// No sense if extensively checking the file... just do a rough check to make sure they didn't forget to map a variable.
			if(!$errorFlag){
				
				$variableDataObj = new VariableData();
				$variableDataObj->loadDataByTextFile($projectObj->getVariableDataFile());

				if($projectObj->getQuantity() <= 1){
					$startLineNumber = 0;
					$stopLineNumber = 1;
				}
				else if($projectObj->getQuantity() < 10){
					$startLineNumber = 1;
					$stopLineNumber = $projectObj->getQuantity();
				}
				else{
					$startLineNumber = 1;
					$stopLineNumber = 10;
				}
				
				
				$columnNamesWithTranslationsArr = $mailBatchObj->getAllColumnNamesWithTranslations();
				
				for($i = $startLineNumber; $i < $stopLineNumber; $i++){
				
					$addressPosition = $mailBatchObj->getFieldMappingPositionOfVariable($artworkVarsObj, "Address1");
					$cityPosition = $mailBatchObj->getFieldMappingPositionOfVariable($artworkVarsObj, "City");
					$statePosition = $mailBatchObj->getFieldMappingPositionOfVariable($artworkVarsObj, "State");
					$zipPosition = $mailBatchObj->getFieldMappingPositionOfVariable($artworkVarsObj, "Zip");
					
					
					$addressValue = $variableDataObj->getVariableElementByLineAndColumnNo($i, ($addressPosition - 1));
					$cityValue = $variableDataObj->getVariableElementByLineAndColumnNo($i, ($cityPosition - 1));
					$stateValue = $variableDataObj->getVariableElementByLineAndColumnNo($i, ($statePosition - 1));
					$zipValue = $variableDataObj->getVariableElementByLineAndColumnNo($i, ($zipPosition - 1));
					
					$dataValueError = "You can not proof this artwork because the __COLNAME__ column has a bad value on Line #" . strval($i + 1) .  " inside the data file.\nDid you forget to map a column name?  Maybe the data file has errors?";
					
					if(empty($addressValue)){
						$errorFlag = true;
						$ErrorDescription = preg_replace("/__COLNAME__/", "ADDRESS", $dataValueError);
						break;
					}
					if(strlen($cityValue) < 2){
						$errorFlag = true;
						$ErrorDescription = preg_replace("/__COLNAME__/", "CITY", $dataValueError);
						break;
					}
					if(strlen($stateValue) < 2){
						$errorFlag = true;
						$ErrorDescription = preg_replace("/__COLNAME__/", "STATE", $dataValueError);
						break;
					}
					if((strlen($zipValue) > 10) || (strlen($zipValue) < 5)){
						$errorFlag = true;
						$ErrorDescription = preg_replace("/__COLNAME__/", "ZIP", $dataValueError);
						break;
					}
				}
			}
		}
		
		
		if(!$errorFlag){
			ProjectOrdered::ChangeProjectStatus($dbCmd, "P", $projectid);

			ProjectHistory::RecordProjectHistory($dbCmd, $projectid, "P", $UserID);

			// If we are taking an order off of hold then we need to reset the Estimates Ship and Arrival dates
			if($ProjectStatus == "H")
				Order::ResetProductionAndShipmentDates($dbCmd, $projectid, "project", time());
		}

		header ("Content-Type: text/xml");
		// It seems that when you hit session_start it will send a Pragma: NoCache in the header
		// When comminicating over HTTPS there is a bug with IE.  It can not get the documents after they have finished downloading because they have already expired
		// We can allow the file to be cached be sending this header.  When requesting the document you can put a ?noache=timestamp
		header("Pragma: public");

		if(!$errorFlag){
			print "<?xml version=\"1.0\" ?>
				<response>
				<success>good</success>
				<description></description>
				</response>"; 
		}
		else{
			print "<?xml version=\"1.0\" ?>
				<response>
				<success>bad</success>
				<description>". $ErrorDescription ."</description>
				</response>"; 
		}
		exit;
	
	}
	else if($action == "savepdfsetting"){
	
		$errorFlag = false;
		$ErrorDescription = "";

		// Find out if a lock exists on this order from someone else... if not the function-call will set the lock
		$ArbResult = Order::ArtworkArbitration($dbCmd, Order::GetOrderIDfromProjectID($dbCmd, $projectid), $UserID, false);
		if($ArbResult <> 0){
			$errorFlag = true;
			$ErrorDescription = addslashes(UserControl::GetNameByUserID($dbCmd, $ArbResult)) . " is working on this order.";
		}
		else{
			$bleedparams = WebUtil::GetInput("bleedparams", FILTER_SANITIZE_STRING_ONE_LINE);
			PDFparameters::SavePDFsettingsForProjectOrdered($dbCmd, $projectid, $bleedparams);
		}

		header ("Content-Type: text/xml");
		// It seems that when you hit session_start it will send a Pragma: NoCache in the header
		// When comminicating over HTTPS there is a bug with IE.  It can not get the documents after they have finished downloading because they have already expired
		// We can allow the file to be cached be sending this header.  When requesting the document you can put a ?noache=timestamp
		header("Pragma: public");


		if(!$errorFlag){
			print "<?xml version=\"1.0\" ?>
				<response>
				<success>good</success>
				<description></description>
				</response>"; 
		}
		else{
			print "<?xml version=\"1.0\" ?>
				<response>
				<success>bad</success>
				<description>". $ErrorDescription ."</description>
				</response>"; 
		}
		exit;
	}
	else if($action == "overridelock"){
	
		$orderID = Order::GetOrderIDfromProjectID($dbCmd, $projectid);
		
		Order::ArtworkArbitration($dbCmd, $orderID, $UserID, false, true);

		header("Location: ./" . WebUtil::FilterURL(WebUtil::GetInput("returnurl", FILTER_SANITIZE_URL)));
		exit;
	}
	else
		throw new Exception("Undefined Action");


}


// The Editing Tool is really really particular about not being able to refresh the page, etc.
// There is some kind of bug between IE and the Flash Player.  For some reason, reloading the page causes the "fscommand" not to work
// We have to be certain the the user is visiting a unique URL every time the page is loaded
WebUtil::EnsureGetURLdoesNotGetCached();


// Keep a record of the visit to this page by the user.
NavigationHistory::recordPageVisit($dbCmd, $UserID, "ProofPage", $projectid);


$t = new Templatex(".");

$t->set_file("origPage", "ad_proof_artwork-template.html");

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());


if(!$projectObj->isVariableData()){
	$t->discard_block("origPage", "VariableDataLinkBL");
	$t->discard_block("origPage", "PDFmergeBL");
}








$domainObj = Domain::singleton();

$productIDdropDown = array("0" => "All Products");

$userDomainIDsArr = $domainObj->getSelectedDomainIDs();

foreach($userDomainIDsArr as $thisDomainID){
	
	// If we have more than one Domain, then list the domain in front of the Product.
	if(sizeof($userDomainIDsArr) > 1)
		$domainPrefix = $domainObj->getDomainKeyFromID($thisDomainID) . "> ";
	else
		$domainPrefix = "";
	
	$productNamesInDomain = Product::getFullProductNamesHash($dbCmd, $thisDomainID, false);
	
	foreach($productNamesInDomain as $productID => $productName)
		$productIDdropDown[$productID] = $domainPrefix . $productName;
}





// So they can select which product that want to contstrain proofing to.
$orderQryObj = new OrderQuery($dbCmd);
$limitToProductIDarr = $orderQryObj->getProductLimit();



// If there is no product limit... then in an array of "Zero" which will select the "All Products" Choice
if(empty($limitToProductIDarr))
	$productIDselected = array("0");
else
	$productIDselected = $limitToProductIDarr;

$t->set_var("PRODUCT_DROPDOWN", Widgets::buildSelect($productIDdropDown, $productIDselected));
$t->allowVariableToContainBrackets("PRODUCT_DROPDOWN");


$dbCmd->Query("SELECT orders.UserID, projectsordered.OrderDescription, projectsordered.OptionsDescription, 
		orders.ID AS OrderID, orders.ShippingChoiceID, projectsordered.NotesProduction, projectsordered.NotesAdmin 
		FROM projectsordered INNER JOIN orders on projectsordered.OrderID = orders.ID 
		WHERE projectsordered.ID=$projectid");
$row = $dbCmd->GetRow();

$CustomerID = $row["UserID"];
$OrderDescription = $row["OrderDescription"];
$OptionsDescription = $row["OptionsDescription"];
$orderno = $row["OrderID"];
$shippingChoiceID = $row["ShippingChoiceID"];
$NotesProduction  = $row["NotesProduction"];
$NotesAdmin  = $row["NotesAdmin"];



$thisOrderDesc = $OrderDescription . " - " . $OptionsDescription;

$t->set_var(array(
	"ORDER_DESCRIPTION"=>$thisOrderDesc, 
	"CUSTOMER_ID"=>$CustomerID,
	"CUSTOMER_PROJECTS"=>Order::GetProjectCountByUser($dbCmd, $CustomerID, "")));




$dbCmd->Query("SELECT CustomerNotes, Email, Name FROM users WHERE ID=$CustomerID");
$row=$dbCmd->GetRow();

$customerNotes = $row["CustomerNotes"];
$customerEmail = $row["Email"];
$customerName = $row["Name"];

$t->set_var(array("CUSTOMERNOTES"=>WebUtil::htmlOutput($customerNotes)));





// Find out if there is a tempory artwork file stored for this project's artwork
if(isset($HTTP_SESSION_VARS['ArtworkModified' . $projectid])){

	// Hide the button for applying the original artwork.... if this is a Mailing Order... otherwise they could destroy the batch.
	$t->set_block("origPage","OriginalArtworkButton","OriginalArtworkButtonout");
	$t->set_var("OriginalArtworkButtonout", "");


}
else {


	//Find out if the artwork file has an edit from the administrator --#
	$dbCmd->Query("SELECT ArtworkFileModified FROM projectsordered WHERE ID=$projectid");
	$ModifiedArtworkFile = $dbCmd->GetValue();
	
	if(empty($ModifiedArtworkFile) || !$mailBatchPrivilages){
		// No point in showing them the "original artwork" button if we are currently viewing the "original artwokr".
		$t->set_block("origPage","OriginalArtworkButton","OriginalArtworkButtonout");
		$t->set_var("OriginalArtworkButtonout", "");

	}
	

	// Hide the button for reapllying changes...  since no session variable has info
	$t->set_block("origPage","ReApplyChangesButton","ReApplyChangesButtonout");
	$t->set_var("ReApplyChangesButtonout", "");

}



if(($ProjectStatus != "N" && $ProjectStatus != "H" && $ProjectStatus != "A" && $ProjectStatus != "W" && $ProjectStatus != "L") || !$mailBatchPrivilages){
	$t->set_block("origPage","HideProofButton","HideProofButtonOut");
	
	if(!$mailBatchPrivilages)

		$t->set_var(array("HideProofButtonOut"=>"<font class='body'><font color='#990000'><b>Mailing Services</b></font></font>"));
	else
		$t->set_var(array("HideProofButtonOut"=>""));
}

if(($ProjectStatus != "N" && $ProjectStatus != "P") || !$mailBatchPrivilages){
	$t->set_block("origPage","HideHoldButton","HideHoldButtonout");
	$t->set_var(array("HideHoldButtonout"=>""));
}


// Even if they have permission to see the proof button for mailing batches... we want to give them a signal that this is a mailer... so pay more attention to it.
if($productObj->hasMailingService()){
	$t->set_var("PROOF_BUTTON_STYLE", "AdminButtonAlert");
}
else{
	$t->set_var("PROOF_BUTTON_STYLE", "AdminButton");
	$t->discard_block("origPage", "HideMailMaskPreviewButton");
}




// Find out how many projects in this order
$dbCmd->Query("SELECT count(*) FROM projectsordered WHERE OrderID=$orderno");
$ProjectCount = $dbCmd->GetValue();

if($ProjectCount == 1){
	$t->set_var("JS_MULTIPLE_PROJECTS", "false");
	$t->set_var("NOSETS", "1 project");
}
else{
	$t->set_var("JS_MULTIPLE_PROJECTS", "true");
	$t->set_var("NOSETS", "$ProjectCount projects");
}



//Figure out how big the bleed and safe zones are.
$t->set_var("GUIDE_MARGIN", ImageLib::ConvertPicasToFlashUnits($productObj->getArtworkBleedPicas()));



// Set this Session variable right before we load the Flash file
// When the flash file requests the XML document it will know which one to get.
$HTTP_SESSION_VARS['editor_ArtworkID'] = $projectid;
$HTTP_SESSION_VARS['editor_View'] = "proof";



// This will store the side number that the person should be viewing on the server
// For administrators we don't have to reload the page as we switch sides.. So the side number here should always be initialized to 0
$HTTP_SESSION_VARS['SideNumber'] = 0;


// This may have been set from a Saved Project if we switched sides... and then left the page.  Wipe it out just to be safe
$HTTP_SESSION_VARS['SwitchSides'] = "";
$HTTP_SESSION_VARS['TempXMLholder'] = "";


// Administrators shouldn't be using netscape.  This session var will tell the flash program to stop that annoying clicking should associated with getURL
$HTTP_SESSION_VARS['UserAgent'] = "MSIE";

// So when teh flash file saved the file it will know which project it is for
$HTTP_SESSION_VARS['ProofProjectID'] = $projectid;


$t->set_var(array("PROJECTORDERED"=>$projectid));
$t->set_var(array("MAIN_ORDERID"=>$orderno, "ORDERNO_HASHED"=>Order::GetHashedOrderNo($orderno)));


$ArtFile = ArtworkLib::GetArtXMLfile($dbCmd, "proof", $projectid);

// Parse the xml document and populate and Object will all the info we need
$ArtworkInfoObj = new ArtworkInformation($ArtFile);


if(!isset($ArtworkInfoObj->SideItemsArray[0]))
	throw new Exception("Problem with with Artwork Image.  Side 0 is not defined.");
$t->set_var("ARTDPI", $ArtworkInfoObj->SideItemsArray[0]->dpi);



// Make sure that we have all of the necessary SWF files on disk... generated by MING
ArtworkLib::WriteSWFimagesToDisk($dbCmd, $ArtFile);


// In case the Artwork should have a layer of shapes drawn on top of the Artwork... like an area on an envelope where the rectangle for a window will be punched out.
ProjectBase::SetShapesObjectsInSessionVar($dbCmd, $projectObj, $ArtworkInfoObj);

WebUtil::SetSessionVar("LastProofProjectID", $projectid);

$t->set_var(array("ARTWORK_MODIFY_MSG"=>"Back To Original Artwork"));



// We need to figure out if the Artwork has any Marker Images in it.
// If so, then it indicates an irregular shape (not a rectangle)
// in this case we don't want to give Tile and Stretch PDF selections.
$hasMarkerImageFlag = false;
foreach($ArtworkInfoObj->SideItemsArray as $SideObj){
	if($SideObj->markerimage){
		$hasMarkerImageFlag = true;
		break;
	}

}




if($hasMarkerImageFlag){
	$DropDownBleedArr = array("N"=>"Natural");
}
else{
	$DropDownBleedArr = array(
		"S"=>"Stretch",
		"T"=>"Tile",
		"N"=>"Natural",
		"V"=>"None"
		);
}



$t->set_block("origPage","SideBLock","SideBLockout");

// This array will contain a list of Objects used to generate a side of a PDF document...  EX:  There may be one for "Front" and one for "Back"
$PDFobjectsArr = PDFparameters::GetPDFparametersForProjectOrderedArr($dbCmd, $projectid, "Proof");

$counter = 0;
$FirstSideRotate = 0;

foreach($ArtworkInfoObj->SideItemsArray as $SideObj){


	$bleedSelected = $PDFobjectsArr[$counter]->getBleedtype();

	
	//We want to show a checkbox to flip artwork by 180 only if we are not on the 1st side
	if($counter > 0){
	

		//If the current side is rotated 180 degrees in comparison to the front side.. then show it as flipped.
		if(abs($PDFobjectsArr[$counter]->getRotatecanvas() - $FirstSideRotate) == 180)
			$FlipChecked = "checked";
		else
			$FlipChecked = "";

	
		
		$Flip180CheckBox = "<input type='checkbox' name='flip" . $counter . "' value='' onClick='ChangeBleedSetting();' $FlipChecked>";
	}
	else{

		$FirstSideRotate = $PDFobjectsArr[$counter]->getRotatecanvas();
			
		$Flip180CheckBox = " ";
	}


	$t->set_var(array(
			"BLEED_CHOICES"=>Widgets::buildSelect($DropDownBleedArr, array($bleedSelected)),
			"SIDE_NAME"=>$SideObj->description,
			"SIDENUMBER"=>$counter,
			"FLIP180"=>$Flip180CheckBox
			));
			
	$t->allowVariableToContainBrackets("BLEED_CHOICES");
	$t->allowVariableToContainBrackets("FLIP180");

	$counter++;

	$t->parse("SideBLockout","SideBLock",true);
}


// Find out if a lock exists on this order from someone else... The function-call will set a lock
$ArbResult = Order::ArtworkArbitration($dbCmd, Order::GetOrderIDfromProjectID($dbCmd, $projectid), $UserID, true);
if($ArbResult <> 0){
	//Set the name inside the arbitration message for whoever owns the order currently
	$t->set_var("ARB_NAME", WebUtil::htmlOutput(UserControl::GetNameByUserID($dbCmd, $ArbResult)));
}
else{
	//Hide the block for showing abritration notice
	$t->set_block("origPage","ArbitrationBL","ArbitrationBLout");
	$t->set_var("ArbitrationBLout", "");
}



$CurrentURL = "./ad_proof_artwork.php?projectid=$projectid";
$t->set_var("CURRENTURL", $CurrentURL);
$t->set_var("CURRENTURL_ENCODED", urlencode($CurrentURL));
$t->set_var("CURRENTURL_DOUBLE_ENCODED", urlencode(urlencode($CurrentURL))); // 1 encoding is needed to pass through an href="javascript:"



$CustomerShipping = ShippingChoices::getHtmlChoiceName($shippingChoiceID);
$t->set_var("SHIPPING_METHOD", "Ship: " . $CustomerShipping);
$t->allowVariableToContainBrackets("SHIPPING_METHOD");

$t->set_var("CURRENT_STATUS", ProjectStatus::GetProjectStatusDescription($ProjectStatus, true));
$t->allowVariableToContainBrackets("CURRENT_STATUS");

// We shouldn't allow them to change options if the project is canceled or completed.
if($ProjectStatus == "C" || $ProjectStatus == "F")
	$t->set_var("PROJECT_COMPLETE", "true");
else
	$t->set_var("PROJECT_COMPLETE", "false");



// If there are multiple projects in the order then show them a DHTML fly open menu haing links to the other projects
$MultipleOrdersArr = Order::GetMultipleOrdersDesc($dbCmd, $orderno, "");
// If there is only 1 project in the order... then there is no need to show a HTML menu
if(sizeof($MultipleOrdersArr) == 1){
	$t->discard_block("origPage", "HideMultiOrderWindowBL1");
	$t->discard_block("origPage", "HideMultiOrderWindowBL2");
}
else{
	if(sizeof($MultipleOrdersArr) > 10)
		$multiOrdersLinks = "More than 10 projects in this order.";
	else
		$multiOrdersLinks = Order::GetMultipleOrderLinks($MultipleOrdersArr, $orderno, $projectid, "proof");
		
	$t->set_var("ORDER_GROUP_LINKS", $multiOrdersLinks);
	$t->allowVariableToContainBrackets("ORDER_GROUP_LINKS");
}


//Shipping Address
$dbCmd->Query("SELECT * FROM orders WHERE ID=$orderno");
$OrderInfo = $dbCmd->GetRow();

if(!empty($OrderInfo["ShippingCompany"]))
	$Attention = $OrderInfo["ShippingCompany"] . "<br>Attn: " . WebUtil::htmlOutput($OrderInfo["ShippingName"]) . "<br>";
else
	$Attention = WebUtil::htmlOutput($OrderInfo["ShippingName"]) . "<br>";

$ShippingAddress = $Attention . " " . WebUtil::htmlOutput($OrderInfo["ShippingAddress"]) . " " . WebUtil::htmlOutput($OrderInfo["ShippingAddressTwo"]) . "<br>" . WebUtil::htmlOutput($OrderInfo["ShippingCity"]) . ", " . WebUtil::htmlOutput($OrderInfo["ShippingState"]) . " " . WebUtil::htmlOutput($OrderInfo["ShippingZip"]) . "<br>" . WebUtil::htmlOutput(Status::GetCountryByCode($OrderInfo["ShippingCountry"]));
$t->set_var("SHIPPING_INFO", $ShippingAddress);
$t->allowVariableToContainBrackets("SHIPPING_INFO");


#-- Gather the status history
$StatusHistory = "";
$StatusTimeStamp = 0;
$dbCmd->Query("SELECT *, UNIX_TIMESTAMP(Date) AS StatusDate FROM projecthistory WHERE ProjectID=$projectid ORDER BY ID ASC");
while($PHistRow = $dbCmd->GetRow()){
	$StatusTimeStamp = $PHistRow["StatusDate"];
	$status_history_date = date("M j, D g:i a", $StatusTimeStamp);
	$StatusHistory .= $PHistRow["Note"] . "  --  " .  WebUtil::htmlOutput(UserControl::GetNameByUserID($dbCmd2, $PHistRow["UserID"])) . "  --  $status_history_date " . "<br>- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -<br>";
}
$t->set_var("STATUS_HISTORY", $StatusHistory);
$t->allowVariableToContainBrackets("STATUS_HISTORY");


if($FromTemplateArea == "S")
	$TempalteEditLink = "<a href=\"javascript:EditTemplateForThisArtwork($FromTemplateID, 'template_searchengine');\" class='BlueRedLink'>Template</a>";
else if($FromTemplateArea == "C")
	$TempalteEditLink = "<a href=\"javascript:EditTemplateForThisArtwork($FromTemplateID, 'template_category');\" class='BlueRedLink'>Template</a>";
else
	$TempalteEditLink = "&nbsp;";

$t->set_var("EDIT_TEMPLATE", $TempalteEditLink);
$t->allowVariableToContainBrackets("EDIT_TEMPLATE");


$t->set_var("SAVED_PROJECTS", ProjectSaved::GetCountOfSavedProjects($dbCmd, $CustomerID));

$domainIDofUser = UserControl::getDomainIDofUser($CustomerID);

$t->set_var("DOMAIN_URL", Domain::getWebsiteURLforDomainID(Domain::getDomainIDfromURL()));



// If a link does not exist between this project and an order... then don't show the little link icon
// Otherwise show them a link to save the project for the user.
$SavedIDLink = ProjectOrdered::GetSavedIDLinkFromProjectOrdered($dbCmd, $projectid);
if($SavedIDLink == 0)
	$t->discard_block("origPage","SavedProjectLink");
else
	$t->discard_block("origPage","SaveNewProjectLink");





$t->set_var("MEMOS", CustomerMemos::getLinkDescription($dbCmd, $CustomerID, false));
$t->allowVariableToContainBrackets("MEMOS");



// Find out if there are any messages, if so then we want to change the background color to alert the administrator
if(!empty($NotesAdmin))
	$t->set_var("MESSAGECOLOR_ADMIN", "#FFDDDD");
else
	$t->set_var("MESSAGECOLOR_ADMIN", "#EEEEFF");

if(!empty($NotesProduction))
	$t->set_var("MESSAGECOLOR_PRODUCTION", "#FFDDDD");
else
	$t->set_var("MESSAGECOLOR_PRODUCTION", "#EEEEFF");

$t->set_var("ADMIN_MESSAGE", WebUtil::htmlOutput($NotesAdmin));
$t->set_var("PRODUCTION_MESSAGE", WebUtil::htmlOutput($NotesProduction));





// See if they can see customer service items 
if(!$AuthObj->CheckForPermission("VIEW_CUSTOMER_SERVICE")){
	$t->set_block("origPage","HideCustomerServiceBL","HideCustomerServiceBLout");
	$t->set_var("HideCustomerServiceBLout", "");
}
else{

	//Get a List of CSitems associated with this order
	$CSItemsArr = CustService::GetCSitemIDsInOrder($dbCmd, $orderno);

	CustService::ParseCSBlock($dbCmd, $t, $CSItemsArr, "csInquiriesBL", "#EEEEFF", $UserID, $CurrentURL);

	if(sizeof($CSItemsArr) == 0){
		$t->set_block("origPage","EmptyCSInquiriesBL","EmptyCSInquiriesBLout");
		$t->set_var(array("EmptyCSInquiriesBLout"=>""));
	}
}

if(($ProjectStatus == "N" || $ProjectStatus == "P" || $ProjectStatus == "L") && $mailBatchPrivilages)
	$WaitingForReplyLink = "<a href='javascript:WaitingForReply();' class='blueredlink'>Change Status to &quot;Waiting For Reply&quot;</a>";
else
	$WaitingForReplyLink = "";

if(($ProjectStatus == "N" || $ProjectStatus == "P" || $ProjectStatus == "A" || $ProjectStatus == "W" || $ProjectStatus == "H") && $mailBatchPrivilages)
	$ArtworkHelpLink = "<a href='javascript:ArtworkHelp();' class='blueredlink'>Change Status to &quot;Artwork Help&quot;</a><br>";
else
	$ArtworkHelpLink = "";

$t->set_var("WAITING_FOR_REPLY_LINK", $WaitingForReplyLink);
$t->set_var("ARTWORKHELP_LINK", $ArtworkHelpLink);

$t->allowVariableToContainBrackets("WAITING_FOR_REPLY_LINK");
$t->allowVariableToContainBrackets("ARTWORKHELP_LINK");



// Set the Radio Buttons for Switching Between DPI's
$DPIsetting = $ArtworkInfoObj->SideItemsArray[0]->dpi;

if($DPIsetting == 200 || $DPIsetting == 195){
	$t->set_var("DPI_200", "checked");
	$t->set_var("DPI_300", "");
}
else if($DPIsetting == 300){
	$t->set_var("DPI_200", "");
	$t->set_var("DPI_300", "checked");
}






// Find out if this Customer has any coupons associated with them that have Alerts
$CouponAlertsArr = array();
$dbCmd->Query("SELECT DISTINCT OD.CouponID FROM orders AS OD 
			INNER JOIN coupons AS CP ON OD.CouponID = CP.ID  
			WHERE OD.UserID=$CustomerID AND CP.ProofingAlert IS NOT NULL");
while($CpID = $dbCmd->GetValue()){
	$dbCmd2->Query("SELECT ProofingAlert, Code FROM coupons WHERE ID=$CpID");
	$thisRow = $dbCmd2->GetRow();
	$CouponAlertsArr[$thisRow["Code"]] = $thisRow["ProofingAlert"];
}

$alertStr = "";
foreach($CouponAlertsArr as $CpName => $CpMsg)
	$alertStr .= "Coupon Code: " . addslashes($CpName) . '\n' . addslashes(WebUtil::FilterData($CpMsg, FILTER_SANITIZE_STRING_ONE_LINE)) . '\n\n';

// We only want to show the warning for the coupon (if there is a message) one time to the user.   Don't keep bugging them.
if(!empty($alertStr)){
	if(!isset($HTTP_SESSION_VARS['CouponWarningHasBeenGiven' . $projectid]))
		$HTTP_SESSION_VARS['CouponWarningHasBeenGiven' . $projectid] = true;
	else 
		$alertStr = "";	
}

if(!empty($alertStr))	
	$t->set_var("COUPON_ALERT_JS", "alert(\"".$alertStr."\");");
else
	$t->set_var("COUPON_ALERT_JS", "");

$t->set_var("NOCACHE", time());



// Output compressed HTML
WebUtil::print_gzipped_page_start();

$t->pparse("OUT","origPage");

WebUtil::print_gzipped_page_end();






?>