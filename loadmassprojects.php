<?


require_once("library/Boot_Session.php");

exit("Under Construction");

/*
$createprojects = WebUtil::GetInput("createprojects", FILTER_SANITIZE_STRING_ONE_LINE);
$prodid = WebUtil::GetInput("prodid", FILTER_SANITIZE_INT);
$refid = WebUtil::GetInput("refid", FILTER_SANITIZE_INT);
	

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if (!preg_match("/^\w+$/", $refid) || !preg_match("/^\d+$/", $prodid))
	WebUtil::PrintError("There was an error with the URL.");



$dbCmd = new DbCmd();


$user_sessionID =  WebUtil::GetSessionID();


// The shopping cart could take a while to generate on a mass import
set_time_limit(1000);


$t = new Templatex();


$t->set_file("origPage", "loadmassprojects-template.html");


$domainOfProduct = Product::getDomainIDfromProductID($dbCmd, $prodid);
if(!$AuthObj->CheckIfUserCanViewDomainID($domainOfProduct))
	throw new Exception("Error in Load Mass Projects. The Product ID is invalid.");


// This will also "Autenticate" the Product ID for the user's domain permissions.
$projInfoObj = new ProjectInfo($dbCmd, $prodid);
$projInfoObj->intializeDefaultValues();
$productObj = $projInfoObj->getProductObj();

// Find out if we are creating projects and inserting into shopping cart... or, if we are displaying the "Choose Options" fields --#
if(!empty($createprojects)){

	$dbCmd->Query("SELECT * FROM projectmassimport WHERE ReferenceID='".DbCmd::EscapeSQL($refid)."'");
	$MassImportRow = $dbCmd->GetRow();
	
	//These 2 arrays are paraellel
	$DataLinesArr = split("\n", $MassImportRow["FieldData"]);
	$FieldNamesArr = split("\|", $MassImportRow["FieldOrder"]);
	$ArtworkFile = $MassImportRow["ArtworkFile"];

	
	foreach($DataLinesArr as $ThisDataLine){

		//Clean out newline characters
		$ThisDataLine = preg_replace("/(\r|\n)/", "", $ThisDataLine);

		//Split the line into the individual variables... They are separated by double || symbols
		$DataVariablesArr= split("\|\|", $ThisDataLine);

		$ArtworkFileCopy = $ArtworkFile;

		#-- Replace the variables with the inport data for the Artwork XML tempalte --#
		for($i=0; $i<sizeof($FieldNamesArr); $i++){

			//Make sure the program doesn't crash if there are missing fields
			if(!isset($DataVariablesArr[$i]))
				$DataVariablesArr[$i] = "";


			$ArtworkFileCopy = preg_replace("/{" . $FieldNamesArr[$i] . "}/i", $DataVariablesArr[$i], $ArtworkFileCopy);
		}


		// Set all of the options from info from the url.  The ProjectInfoObj is sent by reference so we don't need to get a return from this function
		ProjectInfo::setProjectOptionsFromURL($dbCmd, $projInfoObj);

		$projectObj = new ProjectSession($dbCmd);
		$projectObj->setProjectInfoObject($projInfoObj);
		$projectObj->setArtworkFile($ArtworkFileCopy);
		$theProjectID =  $projectObj->createNewProject($user_sessionID);

		
		// Now create an entry in the shopping cart for the newly created project
		$dbCmd->InsertQuery("shoppingcart", array("ProjectRecord"=>$theProjectID, "SID"=>$user_sessionID, "DomainID"=>Domain::oneDomain()));
	}
	
	// Show them the temporary page... for updating shoppting cart with animation 
	// It will send them to the shopping cart when completed.
	$t = new Templatex();
	$t->set_file("origPage", "artwork_update-template.html");
	$t->set_var("REDIRECT", "shoppingcart.php");

	$t->pparse("OUT","origPage");
	
	exit;

}
else{


	// The file name for the product details is the name of productID.html.  This file is in the subdirectory "product_details"
	$filename = "./product_details/details_" . $prodid . ".html";

	if(!file_exists($filename))
		WebUtil::PrintError("The details for this product can not be retrieved. We are sorry for the inconvenience.");

	$fd = fopen ($filename, "r");
	$HTMLcontents = fread ($fd, filesize ($filename));
	fclose ($fd);


	// Grab a chunk of HTML from the Javascript specific functions
	$m = array();
	$reg = "/<!--\s+BEGIN ADMIN_JS_SCRIPT\s+-->(.*)\s*<!--\s+END ADMIN_JS_SCRIPT\s+-->/sm";
	preg_match_all($reg, $HTMLcontents, $m);
	$Admin_Javascript = $m[1][0];
	$t->set_var(array("ADMIN_JS_SCRIPT"=>$Admin_Javascript));

	// Grab a chunk of HTML from the product options for the Options and radio buttons etc.
	$reg = "/<!--\s+BEGIN InsideForm\s+-->(.*)\s*<!--\s+END InsideForm\s+-->/sm";
	preg_match_all($reg, $HTMLcontents, $m);
	$HTMLcontents = $m[1][0];



	$dbCmd->Query("SELECT FieldData FROM projectmassimport WHERE ReferenceID='$refid'");
	$FieldData = $dbCmd->GetValue();
	$TotalProjects = sizeof(split("\n", $FieldData));
	
	
	// Set the various Javascript values for calculating Pricing Info 
	$t->set_var("QUANTITY_BREAKS", GetQuantityBreaks_JS($productObj));
	$t->set_var("PRODUCT_TITLE", $productObj->getProductTitleExtension());
	$t->set_var("PRODUCTID", $productObj->getProductID());
	$t->set_var("BASE_PRICE", $productObj->getBasePriceCustomer());
	$t->set_var("VARIABLE_OPTIONS", GetVariableOptions_JS($productObj));
	$t->set_var("INIT", GetInitializeAllOptions_JS($projInfoObj));



	$t->set_var("PRODUCT_OPTIONS", $HTMLcontents);
	$t->set_var("TOTAL_PROJECTS", $TotalProjects);
	$t->set_var("REFID", $refid);
}



$t->pparse("OUT","origPage");

*/
?>