<?
require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();

set_time_limit(4000);
ini_set("memory_limit", "512M");

$user_sessionID =  session_id();


// This should be a filename without a path.  It expects the file to be inside of the GetTempDirectory().
$mailingListFileName = WebUtil::GetInput("mailingListFileName", FILTER_SANITIZE_STRING_ONE_LINE);

// This defines the type of marketing compain that we will run.  It will match up to a prefix on a Saved Project Note. Example 'merchant' for "merchant_Electrician"
$marketingName = WebUtil::GetInput("marketingName", FILTER_SANITIZE_STRING_ONE_LINE);

// This is the UserID of the Account which holds Saved Projects to use for templates.
$savedProjectsUserID = WebUtil::GetInput("savedProjectsUserID", FILTER_SANITIZE_INT);

// Defines if we are sending out by "First Class" or "Standard"
$postageType = WebUtil::GetInput("postageType", FILTER_SANITIZE_STRING_ONE_LINE);

// What Product to use out of the user's saved projects.
$productID = WebUtil::GetInput("productID", FILTER_SANITIZE_INT);



// Define the positions of the column numbers for each data element inside of the MailingListFileName
$colNum_Company = 0;
$colNum_Attention = 1;
$colNum_Address1 = 2;
$colNum_Address2 = 3;
$colNum_City = 4;
$colNum_State = 5;
$colNum_Zip = 6;
$colNum_Zip4 = 7;
$colNum_SIC = 8;


if(!Product::checkIfProductIDexists($dbCmd, $productID)){
	WebUtil::WebmasterError("Error in Mailing Industries, the Product ID does not exist: " . $productID);
	throw new Exception("Error in Mailing Industries, the Product ID does not exist: " . $productID);
}



$userControlObj = new UserControl($dbCmd);

if(!$userControlObj->LoadUserByID($savedProjectsUserID, false)){
	$err = "The UserID specified for the Mailing List designs does not exist.";
	WebUtil::WebmasterError($err . "UserID: $savedProjectsUserID URL: " . $_SERVER['REQUEST_URI']);
	throw new Exception($err);
}


$domainIDofUser = $userControlObj->getDomainID();


if(Product::getDomainIDfromProductID($dbCmd, $productID) != $domainIDofUser){
	WebUtil::WebmasterError("Error in Mailing Industries, the Product ID Domain does not match the User Domain.");
	throw new Exception("Error in Mailing Industries, the Product ID Domain does not match the User Domain.");
}


$shippingChoicesObj = new ShippingChoices($domainIDofUser);
$defaultShippingChoiceID = $shippingChoicesObj->getDefaultShippingChoiceID();


$indMarkObj = new IndustryMarketing();

$fullPathToMailingList = Constants::GetTempDirectory() . "/" . $mailingListFileName;

if(!file_exists($fullPathToMailingList)){
	$err = "The filename for the MailingList does not exist within the Temp Directory: " . $mailingListFileName;
	WebUtil::WebmasterError($err);
	exit($err);
}


// This is the file that contains the Pattern Matching for converting company names into Industry Categories.
if(!$indMarkObj->loadKeywordList(Constants::GetAccountBase() . "/classes/Industry_Keywords.xls")){
	
	$problemsWithDateFile = $indMarkObj->getDataFileError();

	$err = "The Industry Keywords Data File could not be parsed because...\n" . $indMarkObj->getDataFileError();
	WebUtil::WebmasterError($err);
	exit($err);
}


if($postageType != "First Class" && $postageType != "Standard"){
	$err = "The Postage Type is incorrect for mailers_industries.php.\n" . $postageType;
	WebUtil::WebmasterError($err);
	exit($err);
}



$exc = new ExcelFileParser();

$res = $exc->ParseFromFile($fullPathToMailingList);



$errorMessage = "";

switch ($res) {
	case 0: break;
	case 1: $errorMessage = "Can't open file";
	case 2: $errorMessage = "File too small to be an Excel file";
	case 3: $errorMessage = "Error reading file header";
	case 4: $errorMessage = "Error reading file";
	case 5: $errorMessage = "This is not an Excel file or file stored in Excel < 5.0";
	case 6: $errorMessage = "File corrupted";
	case 7: $errorMessage = "No Excel data found in file";
	case 8: $errorMessage = "Unsupported file version";

	default:
		$errorMessage = "An unknown error has occured.";
}


if(!empty($errorMessage)){
	$err = "Error opening the Excel file for the Industry Mailing List: " . $errorMessage;
	WebUtil::WebmasterError($err);
	exit($err);
}

if(count($exc->worksheet['name']) == 0){
	$err = "Error opening the Excel file for the Industry Mailing List: There are no worksheets within the Excel file.";

	WebUtil::WebmasterError($err);
	exit($err);
}


// We are only going to process the first worksheet (0);
$ws = $exc->worksheet['data'][0];

if( !is_array($ws) || !isset($ws['max_row']) || !isset($ws['max_col']) ){
	$err = "Error opening the Excel file for the Industry Mailing List: There is no information on the first Worksheet.";

	WebUtil::WebmasterError($err);
	exit($err);
}



// Process Excel File into memory
$lineItemsArr = array();

$rowCounter = -1;

$numberOfRows = $ws['max_row'];
$numberOfColumns = $ws['max_col'];

$timeOutCounter = 0;

 for( $i=0; $i <= $numberOfRows; $i++ ) {

	// Skip blank rows
	if(!isset($ws['cell'][$i]) || !is_array($ws['cell'][$i]) ) 
		continue;

	$rowCounter++;
	
	$timeOutCounter++;
	
	if($timeOutCounter > 1000){
		print ".                                                                                           ";
		$timeOutCounter = 0;
		Constants::FlushBufferOutput();
	}

	for( $j=0; $j<= $numberOfColumns; $j++ ) {

		// Check for an empty Cell
		if( !isset($ws['cell'][$i][$j]) ){
			$lineItemsArr[$rowCounter][$j] = "";
			continue;
		}

		$data = $ws['cell'][$i][$j];

		switch ($data['type']) {
			// string
			case 0:
				$ind = $data['data'];
				if( $exc->sst['unicode'][$ind] ){
					print "unicode skipped<br>";
					$s = "";
				}
				else{
					$s = $exc->sst['data'][$ind];
				}

				$lineItemsArr[$rowCounter][$j] = $s;

				break;
			// integer number
			case 1:
				$lineItemsArr[$rowCounter][$j] = $data['data'];
				break;
			// float number
			case 2:
				$lineItemsArr[$rowCounter][$j] = $data['data'];
				break;
			// date
			case 3:
				$ret = $data[data];//str_replace ( " 00:00:00", "", gmdate("d-m-Y H:i:s",$exc->xls2tstamp($data[data])) );
				$lineItemsArr[$rowCounter][$j] = $ret;
				break;
			case 4: //string
				break;
			case 5: //hlink	
				$lineItemsArr[$rowCounter][$j] = convertUnicodeString($data['data']);
				break;
			default:
				// An unknown Data Type in the file
				$lineItemsArr[$rowCounter][$j] = "";
				break;
		}



		// Remove extra white space/line breaks at the beggining/end data
		$lineItemsArr[$rowCounter][$j] = trim($lineItemsArr[$rowCounter][$j]);
	}
}

$headerLineError = "";

// We are going to check the header line.  This is to make sure that the format of the Excel File is what we are anticipating.
if(strtoupper($lineItemsArr[0][$colNum_Company]) != "COMPANY")
	$headerLineError .= "Error opening the Excel file for the Industry Mailing List: The header row on Colum1 should read 'Company'.\n";
if(strtoupper($lineItemsArr[0][$colNum_Attention]) != "FULLNAME")
	$headerLineError .= "Error opening the Excel file for the Industry Mailing List: The header row on Colum2 should read 'Fullname'.\n";
if(strtoupper($lineItemsArr[0][$colNum_Address1]) != "ADDRESS1")
	$headerLineError .= "Error opening the Excel file for the Industry Mailing List: The header row on Colum3 should read 'Address1'.\n";
if(strtoupper($lineItemsArr[0][$colNum_Address2]) != "ADDRESS2")
	$headerLineError .= "Error opening the Excel file for the Industry Mailing List: The header row on Colum4 should read 'Address2'.\n";
if(strtoupper($lineItemsArr[0][$colNum_City]) != "CITY")
	$headerLineError .= "Error opening the Excel file for the Industry Mailing List: The header row on Colum5 should read 'City'.\n";
if(strtoupper($lineItemsArr[0][$colNum_State]) != "STATE")
	$headerLineError .= "Error opening the Excel file for the Industry Mailing List: The header row on Colum6 should read 'State'.\n";
if(strtoupper($lineItemsArr[0][$colNum_Zip]) != "ZIP")
	$headerLineError .= "Error opening the Excel file for the Industry Mailing List: The header row on Colum7 should read 'Zip'.\n";
if(strtoupper($lineItemsArr[0][$colNum_Zip4]) != "ZIP4")
	$headerLineError .= "Error opening the Excel file for the Industry Mailing List: The header row on Colum8 should read 'Zip4'.\n";
if(strtoupper($lineItemsArr[0][$colNum_SIC]) != "SIC")
	$headerLineError .= "Error opening the Excel file for the Industry Mailing List: The header row on Colum9 should read 'SIC'.\n";

if(!empty($headerLineError)){
	WebUtil::WebmasterError($headerLineError);
	exit($headerLineError);
}

$leftOverCsvFileArr = array();
$negativeMatchesCsvFileArr = array();
$leftOverCsvHeader = "COMPANY,FULLNAME,ADDRESS1,ADDRESS2,CITY,STATE,ZIP";


print "<br>";

$offlineMarketingDataObj = new OfflineMarketingUrl();

// Now we are going to parse through our internal Array and look for Industry Matches... when we find them we are going to fill up another array with the results
// If we can't find an industry match, append to the log file and skip the entry.
$nonMatchingErrorsLog = "";
$nonMatchingErrorsLogNoHTML = "";
$uniqueIndustryMatchesArr = array();

// This will be a 2D array containing all Address elements as well as the Industry Name.
$companyDetailsArr = array();
$timeOutCounter = 0;
$nonMatchCount = 0;

for($i=1; $i<sizeof($lineItemsArr); $i++){

	$thisCompanyName = $lineItemsArr[$i][$colNum_Company];
	$thisSIC = $lineItemsArr[$i][$colNum_SIC];
	
	if(empty($thisCompanyName) && empty($thisSIC))
		continue;
		
	$timeOutCounter++;
	
	if($timeOutCounter > 100){
		print "+                                                     ";
		$timeOutCounter = 0;
		Constants::FlushBufferOutput();
	}

	if($indMarkObj->checkCompanyDetails($thisCompanyName, $thisSIC)){
	
		$uniqueIndustryMatchesArr[] = $indMarkObj->getIndustryCategory();
	
		// Create a field to search by company name
		$companyNameSearch = $offlineMarketingDataObj->getUniqueLocatorString($lineItemsArr[$i][$colNum_Company]);
		
		$companyDetailsArr[] = array("Company"=>$lineItemsArr[$i][$colNum_Company],
						"Attention"=>$lineItemsArr[$i][$colNum_Attention],
						"Address1"=>$lineItemsArr[$i][$colNum_Address1],
						"Address2"=>$lineItemsArr[$i][$colNum_Address2],
						"City"=>$lineItemsArr[$i][$colNum_City],
						"State"=>$lineItemsArr[$i][$colNum_State],
						"Zip"=>$lineItemsArr[$i][$colNum_Zip],
						"SIC"=>$lineItemsArr[$i][$colNum_SIC],
						"Industry"=>$indMarkObj->getIndustryCategory(),
						"CompanySearch"=>$companyNameSearch 
					);


		// We want to email a CSV file with the left-overs.
		if(preg_match("/(innovative|WildcardCategory)/i", $indMarkObj->getIndustryCategory())){
			$leftOverCsvFileArr[]= FileUtil::csvEncodeLineFromArr(array($lineItemsArr[$i][$colNum_Company], 
																	$lineItemsArr[$i][$colNum_Attention], 
																	$lineItemsArr[$i][$colNum_Address1],
																	$lineItemsArr[$i][$colNum_Address2],
																	$lineItemsArr[$i][$colNum_City],
																	$lineItemsArr[$i][$colNum_State],
																	$lineItemsArr[$i][$colNum_Zip]
																	));
		}
					
		// Some times the category will come out with something like "WildcardCategoryDeleted" based upon a category name specified in Industry_Keywords.xls
		if(!preg_match("/(Deleted|Negative)/i", $indMarkObj->getIndustryCategory())){
		
			// Keep a separate record in MySQL that will be trimmed and indexed for speed.
			$newId = $dbCmd->InsertQuery("merchantmailers", array("CompanySearch"=>$companyNameSearch, 
														"Company"=>$lineItemsArr[$i][$colNum_Company], 
														"Attention"=>$lineItemsArr[$i][$colNum_Attention],
														"Address1"=>$lineItemsArr[$i][$colNum_Address1],
														"Address2"=>$lineItemsArr[$i][$colNum_Address2],
														"City"=>$lineItemsArr[$i][$colNum_City],
														"State"=>$lineItemsArr[$i][$colNum_State],
														"Zip"=>$lineItemsArr[$i][$colNum_Zip],
														"SicCode"=>$lineItemsArr[$i][$colNum_SIC],
														"IndustryName"=>$indMarkObj->getIndustryCategory(),
														"DateAdded"=>time()));
		}
		
		if(preg_match("/Negative/i", $indMarkObj->getIndustryCategory())){
			$negativeMatchesCsvFileArr[]= FileUtil::csvEncodeLineFromArr(array($lineItemsArr[$i][$colNum_Company], 
																	$lineItemsArr[$i][$colNum_Attention], 
																	$lineItemsArr[$i][$colNum_Address1],
																	$lineItemsArr[$i][$colNum_Address2],
																	$lineItemsArr[$i][$colNum_City],
																	$lineItemsArr[$i][$colNum_State],
																	$lineItemsArr[$i][$colNum_Zip]
																	));
		}
		
	}
	else{
		$nonMatchingErrorsLog .= $indMarkObj->getErrorMessage() . "<br>";
		$nonMatchingErrorsLogNoHTML .= $indMarkObj->getErrorMessage() . "\n\n";
		$nonMatchCount++;
	}

}


// Free up some memory because we will need it.  We have all of the company details in another array anyway.
unset($lineItemsArr);
unset($exc);

print "<br>";



$uniqueIndustryMatchesArr = array_unique($uniqueIndustryMatchesArr);




// Record information into the "orders table" and get the new Order ID.
$insertArr = array();
$insertArr["UserID"] = $userControlObj->getUserID();

$insertArr["BillingName"] = $userControlObj->getName();
$insertArr["BillingCompany"] = $userControlObj->getCompany();
$insertArr["BillingAddress"] = $userControlObj->getAddress();
$insertArr["BillingAddressTwo"] = $userControlObj->getAddressTwo();
$insertArr["BillingCity"] = $userControlObj->getCity();
$insertArr["BillingState"] = $userControlObj->getState();
$insertArr["BillingZip"] = $userControlObj->getZip();
$insertArr["BillingCountry"] = $userControlObj->getCountry();

$insertArr["ShippingName"] = $userControlObj->getName();
$insertArr["ShippingCompany"] = $userControlObj->getCompany();
$insertArr["ShippingAddress"] = $userControlObj->getAddress();
$insertArr["ShippingAddressTwo"] = $userControlObj->getAddressTwo();
$insertArr["ShippingCity"] = $userControlObj->getCity();
$insertArr["ShippingState"] = $userControlObj->getState();
$insertArr["ShippingZip"] = $userControlObj->getZip();
$insertArr["ShippingCountry"] = $userControlObj->getCountry();
$insertArr["ShippingResidentialFlag"] = "N";

$insertArr["ShippingChoiceID"] = $defaultShippingChoiceID;
$insertArr["ShippingTax"] = "0.00";  // Just set to $0 for now, a function call later will update this
$insertArr["ShippingQuote"] = "0.00";
$insertArr["InvoiceNote"] = "Merchant Mailers";
$insertArr["CardType"] = "Billed";
$insertArr["CardNumber"] = "111";
$insertArr["MonthExpiration"] = "111";
$insertArr["YearExpiration"] = "111";
$insertArr["DateOrdered"] = date("YmdHis");
$insertArr["Referral"] = "MerchantMailer";
$insertArr["BannerReferer"] = "";
$insertArr["IPaddress"] = "127.0.0.1";
$insertArr["CouponID"] = "0";
$insertArr["BillingType"] = "C";
$insertArr["DomainID"] = $domainIDofUser;


$order_number = $dbCmd->InsertQuery("orders",  $insertArr);


// Keep an array of all of the Project Order IDs we create automatically.
$projectOrderIDArr = array();


// Go through our list of Unique Industries and see if any of the designs don't exist within the Saved Projects for the UserID.
$designNotFoundArr = array();

$industryCountsLog = "";

$industryDesignCounter = 0;

$totalIndustryQuantities = 0;

foreach($uniqueIndustryMatchesArr as $thisIndustryName){


	$noteName = "GOOD " . $marketingName . "_" . $thisIndustryName;

	$dbCmd->Query("SELECT ID FROM projectssaved WHERE UserID=" . $savedProjectsUserID . " AND ProductID=$productID AND Notes LIKE \"%" . DbCmd::EscapeLikeQuery($noteName) . "%\" LIMIT 1");

	
	// If we don't have a template created for the industry... then add to a list that we will email.
	if($dbCmd->GetNumRows() == 0){
		$designNotFoundArr[] = $thisIndustryName;
		continue;
	}


	// Create a new Project Object Based Upon the Saved Project ID.
	$savedProjectTemplateID = $dbCmd->GetValue();
	
	$projectSavedObj = new ProjectSaved($dbCmd);
	$projectSavedObj->loadByProjectID($savedProjectTemplateID);
	
	
	// Now we are going to create the Variable Data File for this Saved Project.
	// Loop through our entire Data List looking for this Category.
	$variableDataFile = "";
	$quantityCount = 0;
	
	for($i=0; $i<sizeof($companyDetailsArr); $i++){
	
		if($companyDetailsArr[$i]["Industry"] == $thisIndustryName){
		
			if(!empty($variableDataFile))
				$variableDataFile .= "\n";
				
			// Perform formatting options on the Company Names
			// Company1 and Company2 can be formatted differently.
			$company1 = ucwords(strtolower($companyDetailsArr[$i]["Company"]));
			
			// We are droping the Company Name down from All Caps to first letter Caps.  So try to capitalize acronyms when possible.
			$company1 = preg_replace("/llc/i", "LLC", $company1);
			$company1 = preg_replace("/l\.l\.c./i", "LLC", $company1);
			$company1 = preg_replace("/inc\.?(\s|$)/i", "Inc.", $company1);
			$company1 = preg_replace("/llc/i", "Ltd", $company1);
			$company1 = preg_replace("/l\.t\.d\./i", "Ltd", $company1);
			$company1 = preg_replace("/m\.\d./i", "M.D.", $company1);
			$company1 = preg_replace("/\smd(\s|$)/i", "MD", $company1);
			$company1 = preg_replace("/\sm\.d\.(\s|$)/i", "M.D.", $company1);
			
			// The data company gives the full name... we are going to try to extract the First name out of the full name.
			$firstName = $companyDetailsArr[$i]["Attention"];
			if(!empty($firstName)){
				$nameParts = UserControl::GetPartsFromFullName($companyDetailsArr[$i]["Attention"]);
				$firstName = ucfirst(strtolower($nameParts["First"]));
			}
			
			$variableDataFile .= $firstName. "^" . $company1 . "^" . $company1 . "^";
			$variableDataFile .= $companyDetailsArr[$i]["Address1"] . "^" . $companyDetailsArr[$i]["Address2"] . "^";
			$variableDataFile .= $companyDetailsArr[$i]["City"] . "^" . $companyDetailsArr[$i]["State"] . "^"; 
			$variableDataFile .= $companyDetailsArr[$i]["Zip"] . "^";
			$variableDataFile .= $companyDetailsArr[$i]["CompanySearch"];
			
			$quantityCount++;
		}
	}
	
	// Only Increment the Industry Design Counter if there is at least one Company Name matched.
	if($quantityCount > 0)
		$industryDesignCounter++;
		
	
	$totalIndustryQuantities += $quantityCount;
	
	print "Industry: " . $thisIndustryName . " &nbsp;&nbsp;&nbsp;Count: " . $quantityCount . "<br>";
	$industryCountsLog .= "Industry: " . $thisIndustryName . " Count: " . $quantityCount . "\n";

	Constants::FlushBufferOutput();
	
	
	
	$oldArtworkConfig = $projectSavedObj->getVariableDataArtworkConfig();
	
	$oldArtworkMappingObj = new ArtworkVarsMapping($projectSavedObj->getArtworkFile(), $oldArtworkConfig);
	
	
	$projectSavedObj->setVariableDataFile($variableDataFile);
	
	
	
	$artworkMappings = "<?xml version=\"1.0\"?>
		<ArtworkMappings>
		<VarMapping position=\"1\">FirstName</VarMapping>
		<VarMapping position=\"2\">Company</VarMapping>
		<VarMapping position=\"3\">Company2</VarMapping>
		<VarMapping position=\"4\">StreetAddress</VarMapping>
		<VarMapping position=\"5\">StreetAddress2</VarMapping>
		<VarMapping position=\"6\">City</VarMapping>
		<VarMapping position=\"7\">State</VarMapping>
		<VarMapping position=\"8\">ZipCode</VarMapping>
		<VarMapping position=\"9\">CompanySearch</VarMapping>
		<VarMapping position=\"10\">11111postnet</VarMapping>
		<VarMapping position=\"11\">CIN</VarMapping>
		<DataChanges>
		<ChangedByVar VarName=\"StreetAddress2\"><Criteria>NOTBLANK</Criteria><AddDataBefore>!br!</AddDataBefore><AddDataAfter></AddDataAfter><RemoveDataBefore></RemoveDataBefore><RemoveDataAfter></RemoveDataAfter></ChangedByVar>
		<ChangedByVar VarName=\"FirstName\"><Criteria>NOTBLANK</Criteria><AddDataBefore></AddDataBefore><AddDataAfter>,</AddDataAfter><RemoveDataBefore></RemoveDataBefore><RemoveDataAfter></RemoveDataAfter></ChangedByVar>
		</DataChanges>
		<VariableSizeRestrictions>
		";
		
		
	// If the old Artwork Mappings had any variable with Size restrictions... then include that data within this new mapping file.
	if($oldArtworkMappingObj->checkIfVariableHasSizeRestriction("Company")){
		
		$company1sizeRestrictionObj = $oldArtworkMappingObj->getSizeRestrictionObjectForVariable("Company");
		
		$artworkMappings .= '<SizeRestriction VarName="Company">';
		$artworkMappings .= '<RestrictionType>' . $company1sizeRestrictionObj->getRestrictionType() . '</RestrictionType>' . "\n";
		$artworkMappings .= '<RestrictionLimit>' . $company1sizeRestrictionObj->getRestrictionLimit() . '</RestrictionLimit>' . "\n";
		$artworkMappings .= '<RestrictionAction>' . $company1sizeRestrictionObj->getRestrictionAction() . '</RestrictionAction>' . "\n";
		$artworkMappings .= '</SizeRestriction>';
	}
	
	if($oldArtworkMappingObj->checkIfVariableHasSizeRestriction("Company2")){
		
		$company2sizeRestrictionObj = $oldArtworkMappingObj->getSizeRestrictionObjectForVariable("Company2");
		
		$artworkMappings .= '<SizeRestriction VarName="Company2">';
		$artworkMappings .= '<RestrictionType>' . $company2sizeRestrictionObj->getRestrictionType() . '</RestrictionType>' . "\n";
		$artworkMappings .= '<RestrictionLimit>' . $company2sizeRestrictionObj->getRestrictionLimit() . '</RestrictionLimit>' . "\n";
		$artworkMappings .= '<RestrictionAction>' . $company2sizeRestrictionObj->getRestrictionAction() . '</RestrictionAction>' . "\n";
		$artworkMappings .= '</SizeRestriction>';
	}
	
	$artworkMappings .= "</VariableSizeRestrictions></ArtworkMappings>";
	
	
	
	// We want to give graphic artists the ability to put optional variable names in the Artwork (such as {FirstName})
	// The ArtworkVarsMapping is very strict about having unmapped variables within the Artwork... or too many mapped field names that don't exist within the artwork.
	// So if we are going to use the variable we need to make sure it exists in every single document (no matter what).  That may be undesireable, so we use the following trick.
	// Just put a variable names {FirstName} in one of the FieldNames... so our pattern mattching will be able to extract the Variable.  But the Field Name never gets printed (it is only for Quick Edit Fields).
	$projectSavedArtworkObj = new ArtworkInformation($projectSavedObj->getArtworkFile());
	
	// The BackSide is more guaranteed to have text layers (because of the Address Block).  So replace the field_name on the first text layer that we come across
	for($i=0; $i<sizeof($projectSavedArtworkObj->SideItemsArray[1]->layers); $i++){
	
		if($projectSavedArtworkObj->SideItemsArray[1]->layers[$i]->LayerType == "text"){
			$projectSavedArtworkObj->SideItemsArray[1]->layers[$i]->LayerDetailsObj->field_name = "{FirstName}";
			break;
		}
	}
	$projectSavedObj->setArtworkFile($projectSavedArtworkObj->GetXMLdoc(), false);
	
	
	
	
	$projectSavedObj->setVariableDataArtworkConfig($artworkMappings);
	$projectSavedObj->setVariableDataStatus("G");
	$projectSavedObj->setVariableDataMessage("");
	$projectSavedObj->setQuantity($quantityCount);
	
	
	$projectSessionObj = new ProjectSession($dbCmd);
	$projectSessionObj->copyProject($projectSavedObj);
	
	$projectOrderedObj = new ProjectOrdered($dbCmd);
	$projectOrderedObj->copyProject($projectSessionObj);
	$projectOrderedObj->setOrderID($order_number);	// Set the order number that we just created above.
	$projectOrderedObj->setFromTemplateArea("U"); // Let us know that we extracted the template from a User's Saved Project, matching the industry name.
	$projectOrderedObj->setFromTemplateID($savedProjectTemplateID);
	$projectOrderedObj->setNotesAdmin($thisIndustryName);  // By setting the industry name within the Admin Notes... we can query the database in the future for statisics
	$projectOrderedObj->setStatusChar("P");
	$projectOrderedObj->setOptionChoice("Postage Type", $postageType);
	$projectOrderedObj->searchReplaceArtworkBasedOnProductOptions();
	

	$newProjectOrderID = $projectOrderedObj->createNewProject();
	
	
	
	ProjectHistory::RecordProjectHistory($dbCmd, $newProjectOrderID, "P", $userControlObj->getUserID());
	
	
	// The artwork is ready for printing... set the bleed types to natural
	ProjectOrdered::SetBleedSettingsToNaturalOnProject($dbCmd, $newProjectOrderID);
	
	//$newProjectSessionID = $projectSessionObj->createNewProject($user_sessionID);
	
	$projectOrderIDArr[] = $newProjectOrderID;
	
	
	
	
	//$dbCmd->InsertQuery("shoppingcart", array("ProjectRecord"=>$newProjectSessionID, "SID"=>$user_sessionID, "DateLastModified"=>date("YmdHis")));
	
	unset($projectSavedObj);
	unset($projectSessionObj);
	unset($projectOrderedObj);
	 

}


// Create a new Mailing Batch in the System
$mailBatchObj = new MailingBatch($dbCmd, $userControlObj->getUserID());
$mailBatchObj->createNewBatchInDB($projectOrderIDArr);


print "<br><br>A total of " . $industryDesignCounter . " industry designs were used yeilding a total quantity of " . $totalIndustryQuantities .".<br><br><br><br>";
$industryCountsLog .= "\n\nA total of " . $industryDesignCounter . " industry designs were used yeilding a total quantity of " . $totalIndustryQuantities .".\n\n\n\n\n";

print "The following Industry Names do not have matching templates. Count: " . sizeof($designNotFoundArr) . "<br>";
$industryCountsLog .= "The following Industry Names do not have matching templates. Count: " . sizeof($designNotFoundArr) . "\n-----------------------------------\n";

$totalQuantityMissed = 0;

foreach($designNotFoundArr as $thisIndustryName){
	

	print $thisIndustryName . " -- Data Count: ";
	$industryCountsLog .=  $thisIndustryName . " -- Data Count: ";
	
	$totalIndustryCountNoTemplatesFor = 0;
	
	for($i=0; $i<sizeof($companyDetailsArr); $i++){
	
		if($companyDetailsArr[$i]["Industry"] == $thisIndustryName){
			
			$totalIndustryCountNoTemplatesFor++;
		}
	}
	
	print $totalIndustryCountNoTemplatesFor . "<br>\n";
	$industryCountsLog .= $totalIndustryCountNoTemplatesFor . "\n";
	
	$totalQuantityMissed += $totalIndustryCountNoTemplatesFor;

}

print "<br><br>A total of " . $totalQuantityMissed . " companies were missed because we didn't have a design.";
$industryCountsLog .= "\nA total of " . $totalQuantityMissed . " companies were missed because we didn't have a design.";


print "<br><hr><br>";
$industryCountsLog .= "\n\n\n\n\n";

print $nonMatchCount . " companies could not be matched to an industry.<br>";
print $nonMatchingErrorsLog;

$industryCountsLog .= $nonMatchCount . " companies could not be matched to an industry.\n-----------------------------------\n";
$industryCountsLog .= $nonMatchingErrorsLogNoHTML;


$industryCountsLog .= "\n\n\n\n";




$mailSubject = "Mailer " . ucfirst($marketingName) . " Total: " . $totalIndustryQuantities . " Indst: " . $industryDesignCounter . " Missed: " . $totalQuantityMissed;


WebUtil::SendEmail("PrintsMadeEasy.com", "Mailers@PrintsMadeEasy.com", "Brian Piere", "Brian@PrintsMadeEasy.com", $mailSubject, $industryCountsLog);
WebUtil::SendEmail("PrintsMadeEasy.com", "Mailers@PrintsMadeEasy.com", "Brian Whiteman", "Brian@DotGraphics.net", $mailSubject, $industryCountsLog);
//WebUtil::SendEmail("PrintsMadeEasy.com", "Mailers@PrintsMadeEasy.com", "Bill Bench", "billbench@msn.com", $mailSubject, $industryCountsLog);




// Trim the merchant database to the last 6 months so that it is optimized for speed.
$dbCmd->Query("DELETE FROM merchantmailers WHERE DateAdded < DATE_ADD(NOW(), INTERVAL -60 DAY)");
$dbCmd->Query("OPTIMIZE TABLE merchantmailers");








// Shuffled the mailing recipients into a random order.
shuffle($leftOverCsvFileArr);

// Now send the an attachment with the left-overs.
// Make a Control Group which is 33% of the size.
$controlGroupCount = round(sizeof($leftOverCsvFileArr) / 3);
// IMS wants the full 100% now (no leftovers)
$controlGroupCount = 0;

$leftOverCsv66 = $leftOverCsvHeader . "\n";
$leftOverCsv33 = $leftOverCsvHeader . "\n";
$negativeCsv = $leftOverCsvHeader . "\n";
$thisCounter = 0;
foreach($leftOverCsvFileArr as $thisCsvLine){
	
	//if($thisCounter < $controlGroupCount)
	//	$leftOverCsv33 .= $thisCsvLine . "\n";
	//else 
		$leftOverCsv66 .= $thisCsvLine . "\n";
		
	$thisCounter++;
}

foreach($negativeMatchesCsvFileArr as $thisCsvLine){
	$negativeCsv .= $thisCsvLine . "\n";	
}




$leftOver33fileName = "Innovative_33percent_" . date("n-j-y") . ".csv";
$leftOver66fileName = "Innovative_66percent_" . date("n-j-y") . ".csv";
$negativeCsvFileName = "NegativeMatches_" . date("n-j-y") . ".csv";

$leftOver33fileNamePath = Constants::GetTempDirectory() . "/" . $leftOver33fileName;
$leftOver66fileNamePath = Constants::GetTempDirectory() . "/" . $leftOver66fileName;
$negativeCsvFileNamePath = Constants::GetTempDirectory() . "/" . $negativeCsvFileName;

file_put_contents($leftOver33fileNamePath, $leftOverCsv33);
file_put_contents($leftOver66fileNamePath, $leftOverCsv66);
file_put_contents($negativeCsvFileNamePath, $negativeCsv);


//Create a Mime message... it maybe a multi part if there are attachments
$MimeObj = new Mail_mime();
//$MimeObj->setTXTBody("This is the left-over Merchant Mailers addresses for Innovative Merchant Solutions. \n\nThe 66% file is meant to be run through the Winkjet (".number_format((sizeof($leftOverCsvFileArr) - $controlGroupCount), 0) ."). \nThe 33% file should be sent to IMS as a control group(".number_format($controlGroupCount, 0).").");
$MimeObj->setTXTBody("This is the left-over Merchant Mailers addresses for Innovative Merchant Solutions. \n\nNo control group is being used.  The full file should be run through the Winkjet (".number_format((sizeof($leftOverCsvFileArr) - $controlGroupCount), 0) ."). \nControl group(".number_format($controlGroupCount, 0).").");


if(file_exists($leftOver33fileNamePath)){
	if(!$MimeObj->addAttachment($leftOver33fileNamePath, 'application/octet-stream', $leftOver33fileName)){
		WebUtil::WebmasterError("The message was not sent.  There was a problem adding the attachment: " . $leftOver33fileName);
		exit;
	}
}
if(file_exists($leftOver66fileNamePath)){
	if(!$MimeObj->addAttachment($leftOver66fileNamePath, 'application/octet-stream', $leftOver66fileName)){
		WebUtil::WebmasterError("The message was not sent.  There was a problem adding the attachment: " . $leftOver66fileName);
		exit;
	}
}
if(file_exists($negativeCsvFileNamePath)){
	if(!$MimeObj->addAttachment($negativeCsvFileNamePath, 'application/octet-stream', $negativeCsvFileName)){
		WebUtil::WebmasterError("The message was not sent.  There was a problem adding the attachment: " . $negativeCsvFileName);
		exit;
	}
}


$subjectForEmail = "IMS Mailing List";

$MimeObj->setSubject($subjectForEmail);
$MimeObj->setFrom("PrintsMadeEasy.com <Mailers@PrintsMadeEasy.com>");

$hdrs = array(
	      'From'    => "PrintsMadeEasy.com <Mailers@PrintsMadeEasy.com>",
	      'Subject' => $subjectForEmail
	      );

$body = $MimeObj->get();
$hdrs = $MimeObj->headers($hdrs);


// Change the headers and return envelope information for the SendMail command.
// We don't want emails from different domains to look like they are coming from the same mail server.
$hdrs["Message-Id"] =  "<" . substr(md5(uniqid(microtime())), 0, 15) . "@" . Domain::getDomainKeyFromID(1) . ">";
$domainEmailConfigObj = new DomainEmails(1);
$additionalSendMailParameters = "-r " . $domainEmailConfigObj->getEmailAddressOfType(DomainEmails::CUSTSERV);

$mail = new Mail();
$mail->send("Brian Piere <brian@printsmadeeasy.com>", $hdrs, $body, $additionalSendMailParameters);
$mail->send("Brian Whiteman <B.Whiteman@PrintsMadeEasy.com>", $hdrs, $body, $additionalSendMailParameters);
$mail->send("Mike Huges <mikehughes@PrintsMadeEasy.com>", $hdrs, $body, $additionalSendMailParameters);


print "done";






