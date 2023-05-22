<?

require_once("library/Boot_Session.php");


$view = WebUtil::GetInput("view", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$saveartwork = WebUtil::GetInput("saveartwork", FILTER_SANITIZE_STRING_ONE_LINE);
$saveimport = WebUtil::GetInput("saveimport", FILTER_SANITIZE_STRING_ONE_LINE);
$artworkfile = WebUtil::GetInput("artworkfile", FILTER_UNSAFE_RAW);
$movevar = WebUtil::GetInput("movevar", FILTER_SANITIZE_STRING_ONE_LINE);
$importfile = WebUtil::GetInput("importfile", FILTER_SANITIZE_STRING);
$productID = WebUtil::GetInput("productID", FILTER_SANITIZE_INT);


$domainID = Domain::oneDomain();
$websiteURLofDomain = Domain::getWebsiteURLforDomainID($domainID);

$dbCmd = new DbCmd();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();



// We always save information from the forms into session variables.
// Output to the datbase only when we click on "Create Projects"
if(!isset($HTTP_SESSION_VARS['MassImportArtworkFile']))
	$HTTP_SESSION_VARS['MassImportArtworkFile'] = "";
if(!isset($HTTP_SESSION_VARS['MassImportFieldOrder']))
	$HTTP_SESSION_VARS['MassImportFieldOrder'] = "";
if(!isset($HTTP_SESSION_VARS['MassImportFieldData']))
	$HTTP_SESSION_VARS['MassImportFieldData'] = "";


// Save the information from the forms if they come in the URL
if(!empty($saveartwork))
	$HTTP_SESSION_VARS['MassImportArtworkFile'] = $artworkfile;
if(!empty($saveimport))
	$HTTP_SESSION_VARS['MassImportFieldData'] = $importfile;


$t = new Templatex(".");

$t->set_file("origPage", "ad_tools_massimport-template.html");

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

$t->set_var(array(
	"ARTWORK_FILE"=>WebUtil::htmlOutput($HTTP_SESSION_VARS['MassImportArtworkFile']), 
	"IMPORT_FILE"=>WebUtil::htmlOutput($HTTP_SESSION_VARS['MassImportFieldData'])
	));





// If we are viewing the Create Project Tab 
if($view == "create"){
	
	//Will contain the HTML for the result of clicking on the Create Tab
	$CreateProductMessage = "";
	$ErrorMessage = "";
	
	// Check to make that all of the session variables have something set in them
	if(strlen($HTTP_SESSION_VARS['MassImportArtworkFile']) == "")
		$ErrorMessage .= "<br><br>The Artwork file has not been saved yet.";
	if(strlen($HTTP_SESSION_VARS['MassImportFieldOrder']) == "")
		$ErrorMessage .= "<br><br>Field order has not been saved yet.";
	if(strlen($HTTP_SESSION_VARS['MassImportFieldData']) == "")
		$ErrorMessage .= "<br><br>Import data has not been saved yet.";
	
	// Now lets see if there is a difference in the variables from the Artwork file and the ones that we have recorded in the session
	$RecordedVariableNamesArr = split("\|", $HTTP_SESSION_VARS['MassImportFieldOrder']);
	$ArtworkVariableNamesArr = GetVariableArrFromArtFile($HTTP_SESSION_VARS['MassImportArtworkFile']);
	
	if(sizeof(array_diff($RecordedVariableNamesArr, $ArtworkVariableNamesArr)) > 0 || sizeof(array_diff($ArtworkVariableNamesArr, $RecordedVariableNamesArr)) > 0)
		$ErrorMessage .= "<br><br>The order of the variables has unsaved changes.";
	
	if(!empty($ErrorMessage)){
		$CreateProductMessage = "<b>Projects can not be generated yet.</b>";
		$CreateProductMessage .= $ErrorMessage . "<br><br><br><br><br><br><br><br>";
	}
	else{
		// Well, lets save to the database then, everything seems to be recorded within the session 
		
		$dbCmd = new DbCmd();
		
		//Now get a unique hash that is 15 characters long
		$RefHash = substr(md5(time()), 0, 15);

		$NewInsertID = $dbCmd->InsertQuery( "projectmassimport", array(
							"ReferenceID"=>$RefHash, 
							"ArtworkFile"=>$HTTP_SESSION_VARS['MassImportArtworkFile'],
							"FieldOrder"=>$HTTP_SESSION_VARS['MassImportFieldOrder'],
							"FieldData"=>$HTTP_SESSION_VARS['MassImportFieldData']
							));
			
		
		
		// Give the success message
		$TotalProjects = sizeof(split("\n", $HTTP_SESSION_VARS['MassImportFieldData']));
		
		$CreateProductMessage = "<font color='#990000'><b>Projects were created successfully</b></font> - Total Projects: " .  $TotalProjects . "<hr><br><br>";
		$CreateProductMessage .= "Open a new browser window and use the following link to select options and place the projects witin the shopping cart.<br>";
		$CreateProductMessage .= "Replace ***PRODUCTID*** with a proper Product ID.<br><br><font class='reallysmallbody'>";
		$CreateProductMessage .= "http://$websiteURLofDomain/logout.php?sessiononly=true&redirect=loadmassprojects.php%3Fprodid=***PRODUCTID***%26refid=" . $RefHash;
		$CreateProductMessage .= "</font><br><br><br><br>";
		
	}

	$t->set_var("CREATE_PROJECT_MESSAGE", $CreateProductMessage);
	$t->allowVariableToContainBrackets("CREATE_PROJECT_MESSAGE");
}






// If we are viewing the field order then need to scan the artfile and look for variables
if($view == "fieldorder"){

	//The field order may be recorded in a session variable.. Each field is separated by a pipe symbol
	//The first variable found is the 1st element, 2nd, 3rd, etc.
	if($HTTP_SESSION_VARS['MassImportFieldOrder'] != "")
		$RecordedVariableNamesArr = split("\|", $HTTP_SESSION_VARS['MassImportFieldOrder']);
	else
		$RecordedVariableNamesArr = array();
	
	//This will contain the variables found by scanning the Artwork file
	$ArtworkVariableNamesArr = GetVariableArrFromArtFile($HTTP_SESSION_VARS['MassImportArtworkFile']);


	if(sizeof(array_diff($RecordedVariableNamesArr, $ArtworkVariableNamesArr)) > 0 || sizeof(array_diff($ArtworkVariableNamesArr, $RecordedVariableNamesArr)) > 0){
	
		if(sizeof($ArtworkVariableNamesArr) == 0 || sizeof($ArtworkVariableNamesArr) == 1)
			$FieldOrderMessage = "";
		else 
			$FieldOrderMessage = "<font color='red'>Field order has been set to a default state.  Please review the order.</font><br><br>";
		
		//If the 2 don't match... then we want the recorded positions to be the ones that exist by default in the template
		$RecordedVariableNamesArr = $ArtworkVariableNamesArr;
	
		
		$HTTP_SESSION_VARS['MassImportFieldOrder'] = ConvertFieldOrderToString($RecordedVariableNamesArr);
	}
	else{
		$FieldOrderMessage = "";
	}







	// Lets see if we are trying to reorder the position of the variables.
	if(!empty($movevar)){
	
		// Loop throrugh the current recorded setting
		// We want to juggle things around so that the variable appears in position that we want to move it to
		$tempArray = Array();
		
		$counter = 1;
		foreach($RecordedVariableNamesArr as $ThisRecordedVar){

			//Skip over the variable that we are moving
			if($ThisRecordedVar == $movevar)
				continue;
			
			//As we loop through, see if we run into the position that the variable should be moved to
			if($counter == WebUtil::GetInput("moveto", FILTER_SANITIZE_INT))
				$tempArray[] = $movevar;
			
			$tempArray[] = $ThisRecordedVar;
			
			$counter++;
		}
		
		//If we are moving to the last element.. then tack it on to the end
		if(WebUtil::GetInput("moveto", FILTER_SANITIZE_INT) == sizeof($RecordedVariableNamesArr))
			$tempArray[] = $movevar;
		
		$RecordedVariableNamesArr = $tempArray;
		
		// Record the new settings into the session
		$HTTP_SESSION_VARS['MassImportFieldOrder'] = ConvertFieldOrderToString($RecordedVariableNamesArr);

	}




	//We may not need to show the Reorder table if there are no variables defined or if there is only 1
	if(sizeof($RecordedVariableNamesArr) == 0){
		$FieldOrderMessage .= "No variables were found within the Artwork file.<br><br><br><br><br><br><br><br>";

		$t->discard_block("origPage", "MultiVarsBL");
	}
	else if(sizeof($RecordedVariableNamesArr) == 1){
		$FieldOrderMessage .= "Only 1 variable was found within the Artwork file.  No need to adjust the field order. ";
		$FieldOrderMessage .= "<br><br>Variable Name: <b>" . WebUtil::htmlOutput($RecordedVariableNamesArr[0])  . "</b><br><br><br><br><br><br><br><br>";

		$t->discard_block("origPage", "MultiVarsBL");
	}
	else{
		$FieldOrderMessage .= "";

		$t->set_block("origPage","variableOrderBL","variableOrderBLout");

		$counter = 1;

		foreach($RecordedVariableNamesArr as $ThisVariableName){

			#-- Make the drop down list menu used to reorder variable names --#
			$dropDownHTML = "";
			for($i=1; $i<= sizeof($RecordedVariableNamesArr); $i++){

				$dropDownValue = WebUtil::htmlOutput($ThisVariableName) . "|" . $i;

				if($counter == $i){
					$dropDownHTML .= "<option value='$dropDownValue' selected>$i</option>\n";
				}
				else{
					$dropDownHTML .= "<option value='$dropDownValue'>$i</option>\n";
				}
			}

			$t->set_var(array(
					"VAR_NAME"=>WebUtil::htmlOutput($ThisVariableName),
					"DROP_DOWN_ORDER"=>$dropDownHTML

					));

			$t->allowVariableToContainBrackets("DROP_DOWN_ORDER");
					
			$counter++;

			$t->parse("variableOrderBLout","variableOrderBL",true);
		}
	}
		


	$t->set_var(array("FIELD_ORDER_MESSAGE"=>$FieldOrderMessage));
	$t->allowVariableToContainBrackets("FIELD_ORDER_MESSAGE");

}

// Will take the array.. and redece it to a string separated by a pipe symbols
function ConvertFieldOrderToString($TheArr){
	$returnStr = "";
	
	if($TheArr > 0){
		foreach($TheArr as $x)
			$returnStr .= $x . "|";
		
		//Trim off the last pipe
		$returnStr = substr($returnStr, 0, -1);
	}
	
	return $returnStr;
}

// Will return an array of variables that are found within the artwork file
function GetVariableArrFromArtFile($ArtworkFile){

	$ArtworkVariableNamesArr = Array();

	// Extract a unique list of variables from the Artwork file 
	$matches = array();
	if(preg_match_all("/{(\w+)}/", $ArtworkFile, $matches))
		$ArtworkVariableNamesArr = array_unique($matches[1]);
	
	return $ArtworkVariableNamesArr;
}



if($view == "fieldorder"){
	$t->discard_block("origPage", "ArtworkBL");
	$t->discard_block("origPage", "ImportBL");
	$t->discard_block("origPage", "CreateProjectsBL");
}
else if($view == "import"){
	$t->discard_block("origPage", "ArtworkBL");
	$t->discard_block("origPage", "FieldOrderBL");
	$t->discard_block("origPage", "CreateProjectsBL");
}
else if($view == "create"){
	$t->discard_block("origPage", "ArtworkBL");
	$t->discard_block("origPage", "FieldOrderBL");
	$t->discard_block("origPage", "ImportBL");
}
else{
	// Default is artwork
	$t->discard_block("origPage", "FieldOrderBL");
	$t->discard_block("origPage", "ImportBL");
	$t->discard_block("origPage", "CreateProjectsBL");
}


$t->pparse("OUT","origPage");



?>