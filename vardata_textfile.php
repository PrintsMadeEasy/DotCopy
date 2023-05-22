<?

require_once("library/Boot_Session.php");

// Give it at most 30 minutes to upload a large data file
set_time_limit(1800);
ini_set("memory_limit", "512M"); 



$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();





$projectrecord = WebUtil::GetInput("projectrecord", FILTER_SANITIZE_INT);
$editorview = WebUtil::GetInput("editorview", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$tabview = WebUtil::GetInput("tabview", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$replacedata = WebUtil::GetInput("replacedata", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES, "Y");



$curentURL = "vardata_textfile.php?projectrecord=$projectrecord&editorview=$editorview&tabview=$tabview";

$user_sessionID =  WebUtil::GetSessionID();


ProjectBase::EnsurePrivilagesForProject($dbCmd, $editorview, $projectrecord);




$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, $editorview, $projectrecord);

// Make a copy before doing any changes (just in case it causes an increase to the total we can roll back changes.
$originalProjectObj = $projectObj->cloneThisProjectObject();


$artworkVarsObj = new ArtworkVarsMapping($projectObj->getArtworkFile(), $projectObj->getVariableDataArtworkConfig());


if($editorview == "customerservice"){
	if(!in_array($projectObj->getStatusChar(), array("N", "P", "W", "H")))
		WebUtil::PrintError("You can not edit your Data File after the product has been printed.");
}


// In case there is a change to the subtotal, we need to keep an original copy of the project to reset it
if($editorview == "customerservice" || $editorview == "proof")
	$projectObjOriginal = ProjectBase::getProjectObjectByViewType($dbCmd, "ordered", $projectrecord);


$t = new Templatex();

$t->set_file("origPage", "vardata_textfile-template.html");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());


$t->set_var("PROJECTRECORD", $projectrecord);
$t->set_var("VIEWTYPE", $editorview);
$t->set_var("TABVIEW", $tabview);

if($tabview == "text"){
	$t->discard_block("origPage", "ExcelFileBL");
}
else if($tabview == "excel"){
	$t->discard_block("origPage", "TextFileBL");
}
else if($tabview == "externalpage"){
	// Nothing for this view... we are just going to do a redirect.
}
else{
	throw new Exception("Illegal Tab View");
}
	
// If they are currently doing an append... then keep the radio button defaulted there.  They may have other files to append.
// Otherwise, defult to Replace Existing File.
if($replacedata == "Y")
	$t->set_var(array("REPLACE_CHECKED_NO"=>"", "REPLACE_CHECKED_YES"=>"checked"));
else if($replacedata == "N")
	$t->set_var(array("REPLACE_CHECKED_NO"=>"checked", "REPLACE_CHECKED_YES"=>""));
else
	throw new Exception("Error with Replace data flag when setting radio buttons.");


	
	
$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
if($action){
	
	WebUtil::checkFormSecurityCode();

	if($action == "savefile"){
	
		if($editorview == "customerservice" || $editorview == "proof"){
			if($projectObj->getStatusChar() == "F" || $projectObj->getStatusChar() == "C"){
				throw new Exception("You can not save a new data file on Finished or Canceled orders.");
			}
		}
		

		// We don't want people to change the data on a mailing batch because there could be a lot of money associated with Postage, etc.  There is not an easy way to issolate a Project once it has been grouped with others, printed, etc.
		if(in_array($editorview, ProjectBase::arrayProjectOrderedViewTypes()) && MailingBatch::checkIfProjectBatched($dbCmd, $projectrecord))
			WebUtil::PrintError("You can not modify data on a Project which has been already included within a Mailing Batch.");


		// Check the flag to see if we are Appending to the File or replacing it.
		if($replacedata == "N")
			$dataFile = $projectObj->getVariableDataFile() . "\n" . WebUtil::GetInput("datafile", FILTER_UNSAFE_RAW);
		else if($replacedata == "Y")
			$dataFile = WebUtil::GetInput("datafile", FILTER_UNSAFE_RAW);
		else
			throw new Exception("Error with the Replace Data flag.");
		

		$oldLineCount = $projectObj->getQuantity();
		
		$dataFileArr = array();
		
		// Find out how many rows the new file is.
		if(empty($dataFile)){
			$newLineCount = 0;
		}
		else{
			$dataFileArr = split("\n", $dataFile);
			$newLineCount = sizeof($dataFileArr);
		}
		

	
		// Figure out the max column count by looping through all lines
		$maxColumnSize = 0;
		for($i=0; $i < $newLineCount; $i++){	
			$colCntArr = split("\^", $dataFileArr[$i]);
			if(sizeof($colCntArr) > $maxColumnSize)
				$maxColumnSize = sizeof($colCntArr);
		}
		
		
		// Find out how many artwork variables have been mapped.  We need to have at least this many columns in our data file (even if there is no data).
		if($artworkVarsObj->getTotalColumns() > $maxColumnSize)
			$maxColumnSize = $artworkVarsObj->getTotalColumns();
		

		if($maxColumnSize > 200)
			WebUtil::PrintErrorPopUpWindow("You may not upload a data file with more than 200 columns.");

	
		// Filter the Data file
		$dataFile = "";
		for($i=0; $i < $newLineCount; $i++){
		
			$filteredLine = _filterVariableDataLine($dataFileArr[$i]);
			
			// Make sure that we have an equal number of columns throughout the data file.
			$colCntArr = split("\^", $filteredLine);
			$thisColumnSize = sizeof($colCntArr);
			
			if($thisColumnSize < $maxColumnSize){
				for($z = 0; $z < ($maxColumnSize - $thisColumnSize); $z++)
					$filteredLine .= "^";
			}
			
			// If we are on the first line... then record the first line into the Project Object.
			if($i==0)
				$projectObj->setVariableDataFirstLine($filteredLine);
			
			// This will skip blank rows.
			if(!preg_match("/^\^*$/", $filteredLine))
				$dataFile .= $filteredLine . "\n";
		}
		
		// I belive this is the most efficient way to get rid of the last line break
		// We never know what the last line may be since there could be a bunch of blank lines in front of behind.
		$dataFile = trim($dataFile);
		

		// The number of lines could have changed if blank lines where filtered out.
		if(empty($dataFile)){
			$newLineCount = 0;
		}
		else{
			$dataFileArr = array();
			$dataFileArr = split("\n", $dataFile);
			$newLineCount = sizeof($dataFileArr);
		}

		
		// Always save the file... even if there are parsing errors in the Data file
		if($newLineCount == 0)
			$projectObj->setVariableDataFirstLine("");
		$projectObj->setQuantity($newLineCount);
		$projectObj->setVariableDataFile($dataFile);
		$projectObj->updateDatabase();


		// We may need to charge the person more money if they made the quantity increase by uploading a larger file
		// Will exit and print an error if the new charges (if increased from a previous authorizations) are not able to go through.
		// In this case... any data file that they uploaded will not be saved.
		if($editorview == "customerservice" || $editorview == "proof"){
		
			$authorizeErrorMessage = Order::AuthorizeChangesToProjectOrderedObj($dbCmd, $dbCmd2, $originalProjectObj, $projectObj);

			// If the changes to this project are not authorized, it will return an error message, otherwise a blank string.
			// Will print an error message for pop-up window and will exit the script.
			if(!empty($authorizeErrorMessage))
				WebUtil::PrintErrorPopUpWindow($authorizeErrorMessage);
		}


		// Will change the Variable Data Status, if there are any errors or warnings
		VariableDataProcess::SetVariableDataStatusForProject($dbCmd, $projectrecord, $editorview);
		
		// Refreshes this object in case there were any errors
		$projectObj->loadByProjectID($projectrecord);



		// In case the it is Saved... this will update the Shoppingcart, etc.
		ProjectOrdered::ProjectChangedSoBreakOrCloneAssociates($dbCmd, $editorview, $projectrecord);



		if($editorview == "customerservice"){
			
			$AuthObj = new Authenticate(Authenticate::login_general);
			$UserID = $AuthObj->GetUserID();
			
			// We don't need to reset the status if "proofed"... 
			// just record that they made a change to the data file
			ProjectHistory::RecordProjectHistory($dbCmd, $projectrecord, "Customer saved a new Data File.<br>Old Line Count: $oldLineCount New Line Count: " . $projectObj->getQuantity(), $UserID);

		}
		else if($editorview == "proof"){

			$AuthObj = new Authenticate(Authenticate::login_ADMIN);
			$UserID = $AuthObj->GetUserID();
			
			// We don't need to reset the status if "proofed"... 
			// just record that they made a change to the data file
			ProjectHistory::RecordProjectHistory($dbCmd, $projectrecord, "A new Data File was Saved.<br>Old Line Count: $oldLineCount New Line Count: " . $projectObj->getQuantity(), $UserID);

		}

		
		
		// The Excel file gets parsed, then written back to a hidden input on a page
		// That page posts back to the upload Text file script.  We know this is happening
		// when the $action == "savefile" and the $tabview == "excel"
		// In which case we can assume there have been no errors (since the parsing was created by us)
		// Show the user a confirmation that the Excel file was successfully uploaded.
		if($tabview == "excel"){

			// Find out if there is already a file uploaded... if so then give them an option to overwrite the file or append		
			if($projectObj->getQuantity() == 0)
				$t->discard_block("origPage", "SecondFileUploadBL");

			$t->discard_block("origPage", "UploadErrorBL");
			$t->discard_block("origPage", "ExcelProcessingBL");
			
			$t->set_var("JS_COMMAND", ("window.opener.location = window.opener.location" . "+ '&reloadparent=true'"));
			$t->set_var("JS_ONLOAD", "");
			
			if($replacedata == "Y"){
				$t->set_var("LINECOUNT", "$newLineCount line" . LanguageBase::GetPluralSuffix($newLineCount, "", "s"));
				$t->set_var("APPENDED_MESSAGE", "");	
			}
			else if($replacedata == "N"){
				$numberOfLinesAppended = $newLineCount - $oldLineCount;
				$t->set_var("LINECOUNT", "$numberOfLinesAppended line" . LanguageBase::GetPluralSuffix($numberOfLinesAppended, "", "s"));
				$t->set_var("APPENDED_MESSAGE", " were appended.<br>There are a total of $newLineCount data rows now");	
				$t->allowVariableToContainBrackets("APPENDED_MESSAGE");
			}
			else
				throw new Exception("Error with the Replace data flag.");
			
			$t->pparse("OUT","origPage");
			exit;	
		}
		
		
		VisitorPath::addRecord("Variable Data Excel Raw Text Saved");
		
		
		// If we are posting data from our DHTML grid on the Flat page... then we will redirect back there after upload is complete.
		if($tabview == "externalpage"){
			
			$ReturnURL = WebUtil::GetInput("returl", FILTER_SANITIZE_URL);	
			header("Location: " . WebUtil::FilterURL($ReturnURL));
			exit;
		}




	}
	else if($action == "uploadexcel"){
		
		$error = "";
		$excel_file_path = ""; // Will contain the path to the temporary file


		$largeFileErrorMsg = "You forgot to choose a file for uploading.  <br><br>Or maybe your file size is greater than 4 megabytes?";

		if($_FILES['excelfile']['size'] == 0 ) 
			$error = $largeFileErrorMsg;

		if(!$error && $_FILES['excelfile'] )
			$excel_file_path = $_FILES['excelfile']['tmp_name'];
		
		if(!$error && $excel_file_path == '' ) 
			$error = $largeFileErrorMsg;
			
		if(!$error)
			$file_params = pathinfo($_FILES['excelfile']['name']);

		if(!$error && !isset($file_params["extension"]))
			$error = "An error occured uploading the file. The file extension could not be determined";
			
		// CSV files can handle larger file sizes.
		if(!$error && $_FILES['excelfile']['size'] > 2097152 && strtoupper($file_params["extension"]) == "XLS")
			$error = "The maximum file size that you can upload with an XLS extension is 2 Megabytes. You attempted to upload a file that was " . round($_FILES['excelfile']['size']/1024,0) . " KB. <br><br>As an alternative, 'Save As' a CSV file.  The exported data will be identical to your XLS file but the File Size will be much smaller.  On top of packing in more data per megabyte (using CSV files) you can also upload up to 3MB.";

		if(!$error && $_FILES['excelfile']['size'] > 8388608 )
			$error = "The maximum file size you can upload is 8 Megabytes. You attempted to upload a file that was " . round($_FILES['excelfile']['size']/1024,0) . " KB.";

		if(!$error && $_FILES['excelfile']['error'] != "")
			$error = "An error occured uploading the file: " .  $_FILES['excelfile']['error'];



			
		

		if(empty($error)){
		
			$varDataObj = new VariableData();
			
			if(strtoupper($file_params["extension"]) == "XLS"){
				if(!$varDataObj->loadDataByExcelFile($excel_file_path))
					$error = "The Excel file was unable to be processed.  The reason is...<br><br>" . WebUtil::htmlOutput($varDataObj->getErrorMessageLong());
			}
			else if(strtoupper($file_params["extension"]) == "CSV"){
				if(!$varDataObj->loadDataByCSVfile($excel_file_path))
					$error = "The CSV was unable to be processed.  The reason is...<br><br>" . WebUtil::htmlOutput($varDataObj->getErrorMessageLong());
			}
			else{
				$error = "You must upload a file with an <b>XLS</b> or <b>CSV</b> extension.  You uploaded: <b>" . WebUtil::htmlOutput(strtoupper($file_params["extension"])) . "</b>";
			}
			
		}

		


		if(!empty($error)){
			
			VisitorPath::addRecord("Variable Data Error Uploading Excel File");
			
			$t->set_var("JS_ONLOAD", "");
			$t->set_var("JS_COMMAND", "");
			$t->discard_block("origPage", "ExcelProcessingBL");
			$t->discard_block("origPage", "FileUploadedOKBL");
			$t->set_var("ERRORMSG", $error);
			$t->allowVariableToContainBrackets("ERRORMSG");
			$t->pparse("OUT","origPage");
			exit;
		
		}
		else{
			
			VisitorPath::addRecord("Variable Data Excel File Uploaded");
			
			$t->set_var("JS_COMMAND", "");
			
			// Variable Data files could take a long time to download if the file size is big (before we try re-uploading)
			// Just show them some white text so they don't wonder what is going on.
			if($_FILES['excelfile']['size'] > 1000000 || ($projectObj->getQuantity() > 5000 && $replacedata == "N"))
				print "<font face='arial' style='color:#FFFFFF; font-size:13px;'>&nbsp;&nbsp;&nbsp;&nbsp;This is a large Data File... Please be patient while it is processed.</font>";
		
			$t->discard_block("origPage", "UploadFormBL");
		
			$t->set_var("DATAFILE", WebUtil::htmlOutput($varDataObj->getVariableDataText()));
			$t->set_var("REPLACEDATA", $replacedata);
			
			$t->set_var("JS_ONLOAD", "SubmitProcessedXlsFile()");
		
			$t->pparse("OUT","origPage");
			exit;	
		}
	
	}
	else{
		throw new Exception("Undefined Action");
	}

}
	


	
	






// Set this Var to something else if you want a Javascript command to fire when the page loads.
$t->set_var("JS_ONLOAD", "");




// Find out if there is already a file uploaded... if so then give them an option to overwrite the file or append		
if($tabview == "excel"){
	if($projectObj->getQuantity() == 0)
		$t->discard_block("origPage", "SecondFileUploadBL");
}



$t->set_var("DATA_FILE", WebUtil::htmlOutput($projectObj->getVariableDataFile()));


if($action == "savefile"){
	$t->set_var("JS_COMMAND", ("window.opener.location = window.opener.location" . "+ '&reloadparent=true'"));
}
else{
	$t->discard_block("origPage", "SavedMessageBL");
	$t->set_var("JS_COMMAND", "");
}


// No action on an Excel tab means there are no errors or successes to report
if(!$action && $tabview == "excel"){
	$t->discard_block("origPage", "FileUploadedOKBL");
	$t->discard_block("origPage", "UploadErrorBL");
	$t->discard_block("origPage", "ExcelProcessingBL");
}




// Don't show Empty Data Errors.  They can see it is empty themselves in the Text Area
if(!$projectObj->getVariableDataFirstLine() == ""){

	// If there is aData File Errors
	if($projectObj->getVariableDataStatus() == "D"){
		
		// We want to get the line number of the error on a longer description
		$varDataObj = new VariableData();
		$varDataObj->loadDataByTextFile($projectObj->getVariableDataFile());
	
		$t->set_var("ERRORMSG", $varDataObj->getErrorMessageLong());
		$t->set_var("LINE_ERROR", $varDataObj->getErrorLine());

	}
	else
		$t->discard_block("origPage", "DataFileErrorBL");


}
else{
	$t->discard_block("origPage", "DataFileErrorBL");
}
	
VisitorPath::addRecord("Variable Data File Upload Screen");

$t->pparse("OUT","origPage");



// Handle Tabs characters and do any Trimming
// Pass by reference, does not return anything.
function _filterVariableDataLine($x){

	if(strlen($x) > 100000)
		$x = "****** ERROR, A variable data line may not exceed 100,000 Bytes.  ******* ";

	$x = trim($x);

	// Get rid of new line characters.
	$x = preg_replace("/(\r|\n)/", "", $x);

	// Convert Tab characters into Spaces.
	$x = preg_replace("/\t/", " ", $x);

	// Get rid of spaces directly before or after column separators.
	$x = preg_replace("/(\^\s+)/", "^", $x);
	$x = preg_replace("/(\s+\^)/", "^", $x);
	
	// Get rid of angled quotes and long Dash symbols from Microsoft Office.
	$x = WebUtil::converMSwordBadCharacters($x);
	
	// Get rid of Invalid Characters.
	$x = iconv("ISO-8859-1", "UTF-8//IGNORE", $x); 
	$x = WebUtil::FilterData($x, FILTER_SANITIZE_STRING_ONE_LINE);
	

	
	return $x;
}




?>