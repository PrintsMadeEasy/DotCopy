<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("EDIT_SHIPPING_CHOICES"))
		throw new Exception("Under Construction");


	
$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$view = WebUtil::GetInput("view", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$shippingChoiceID = WebUtil::GetInput("shippingChoiceID", FILTER_SANITIZE_INT);
$shippingMethodLinkID = WebUtil::GetInput("shippingMethodLinkID", FILTER_SANITIZE_INT);




// Prevent Cross domain hacking
if(!empty($shippingChoiceID)){
	$domainIDfromShippingChoice = ShippingChoices::getDomainIDofShippingChoiceID($shippingChoiceID);
	
	// Make sure that the user always sees the correct logo in the Nav Bar.
	Domain::enforceTopDomainID($domainIDfromShippingChoice);
	
	if(!$AuthObj->CheckIfUserCanViewDomainID($domainIDfromShippingChoice))
		throw new Exception("Error with Authentication on Shipping Choice.");
}
if(!empty($shippingMethodLinkID)){
	$domainIDfromShippingMethodLink = ShippingChoices::getDomainIDofShippingLinkID($shippingMethodLinkID);
	
	if(!$AuthObj->CheckIfUserCanViewDomainID($domainIDfromShippingMethodLink))
		throw new Exception("Error with Authentication on Shipping Method Link.");
}



$shippingChoiceObj = new ShippingChoices();
$shippingMethodsObj = new ShippingMethods();



if(empty($view))
	$view = "start";



if($action){
	
	WebUtil::checkFormSecurityCode();

	if($action == "SaveNewShippingChoice"){
		
		$newShippingChoiceName = WebUtil::GetInput("shippingChoiceName", FILTER_SANITIZE_STRING_ONE_LINE);
		$daysInTransit = WebUtil::GetInput("daysInTransit", FILTER_SANITIZE_INT);
		
		if($shippingChoiceObj->checkIfShippingChoiceNameExists($newShippingChoiceName))
			WebUtil::PrintAdminError("Can not add this Shipping Choice name because it already exists.");
		
		$newShippingChoiceID = $shippingChoiceObj->addNewShippingChoice($newShippingChoiceName, $daysInTransit);
		
		// Redirect to the Edit page for the new Shipping Choice we just created.
		header("Location: ./" . WebUtil::FilterURL("ad_shippingChoices.php?view=editShippingChoice&shippingChoiceID=" . $newShippingChoiceID));
		exit;

	}
	else if($action == "ChangeShippingChoiceName"){
		
		$newShippingChoiceName = WebUtil::GetInput("shippingChoiceName", FILTER_SANITIZE_STRING_ONE_LINE);
		
		if(!$shippingChoiceObj->setChoiceName($shippingChoiceID, $newShippingChoiceName))
			WebUtil::PrintAdminError("Can not change the name of this Shipping Choice because it is already taken.");

		$shippingChoiceObj->updateDatabase();

		// Redirect to the Edit page for the new Shipping Choice we just created.
		header("Location: ./" . WebUtil::FilterURL("ad_shippingChoices.php?view=editShippingChoice&shippingChoiceID=" . $shippingChoiceID));
		exit;

	}
	else if($action == "UpdateShippingChoice"){
		
		$shippingCode = WebUtil::GetInput("shippingChoiceName", FILTER_SANITIZE_STRING_ONE_LINE);
		$daysInTransit = WebUtil::GetInput("daysInTransit", FILTER_SANITIZE_INT);
		
		$shippingChoiceObj->setColorCode($shippingChoiceID, WebUtil::GetInput("colorCode", FILTER_SANITIZE_STRING_ONE_LINE));
		$shippingChoiceObj->setDaysInTransit($shippingChoiceID, WebUtil::GetInput("daysInTransit", FILTER_SANITIZE_INT));
		$shippingChoiceObj->setPriority($shippingChoiceID, WebUtil::GetInput("priority", FILTER_SANITIZE_STRING_ONE_LINE));
		$shippingChoiceObj->setPricePerPound($shippingChoiceID, WebUtil::GetInput("pricePerPound", FILTER_SANITIZE_FLOAT));
		$shippingChoiceObj->setInitialPrice($shippingChoiceID, WebUtil::GetInput("basePrice", FILTER_SANITIZE_FLOAT));
		$shippingChoiceObj->setRuralFee($shippingChoiceID, WebUtil::GetInput("ruralFee", FILTER_SANITIZE_FLOAT));
		$shippingChoiceObj->setExtendedDistanceFee($shippingChoiceID, WebUtil::GetInput("extendedFee", FILTER_SANITIZE_FLOAT));
		
		// Get all of the Product Overrides for this Shipping Choice. Then loop through them and listen for parameters beneath them in the POST
		$productIDarr = $shippingChoiceObj->getProductIDsThatOverrideBasePrice($shippingChoiceID);
		foreach ($productIDarr as $thisProductID) 
			$shippingChoiceObj->setBasePriceOverrideForProduct($shippingChoiceID, $thisProductID, WebUtil::GetInput("basePriceProduct_$thisProductID", FILTER_SANITIZE_FLOAT));
		
		// Quantity Breaks (price per pound)
		$weightValuesArr = $shippingChoiceObj->getWeightValuesForQuantityBreaks($shippingChoiceID);
		foreach ($weightValuesArr as $thisWeightVal) 
			$shippingChoiceObj->setPricePerPoundQuantityBreak($shippingChoiceID, $thisWeightVal, WebUtil::GetInput("quantityBreak_$thisWeightVal", FILTER_SANITIZE_FLOAT));

		
		$shippingMethodLinkISs = $shippingChoiceObj->getShippingMethodLinkIDs($shippingChoiceID);
		foreach($shippingMethodLinkISs as $thisShippingMethodLinkID){
			
			$shippingMethodCode = $shippingChoiceObj->getShippingMethodCodeFromLinkID($thisShippingMethodLinkID);
			
			// In case the Shipping Method class changed after we updated the DB, skip over inactive Shipping Methods.
			if(!$shippingMethodsObj->doesShippingCodeExist($shippingMethodCode))
				continue;
			
			// Only Shipping Downgrades can handle weight thresholds for a ShippingMethod
			if($shippingChoiceObj->isShippingDowngrade()){
				$shippingChoiceObj->setWeightThresholdsOnShippingMethodLink($thisShippingMethodLinkID, WebUtil::GetInput("shippingCodeMinWeight_$thisShippingMethodLinkID", FILTER_SANITIZE_FLOAT), WebUtil::GetInput("shippingCodeMaxWeight_$thisShippingMethodLinkID", FILTER_SANITIZE_FLOAT));
				$shippingChoiceObj->setAddressTypeForShippingDowngradeLink($thisShippingMethodLinkID, WebUtil::GetInput("shippingAddressType_$thisShippingMethodLinkID", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES));
			}
				
			
			$shippingChoiceObj->setAlertForShippingMethod($shippingChoiceID, $shippingMethodCode, WebUtil::GetInput("shippingCodeAlert_$thisShippingMethodLinkID", FILTER_SANITIZE_STRING_ONE_LINE));			
		}


		$shippingChoiceObj->updateDatabase();
	
		header("Location: ./" . WebUtil::FilterURL("ad_shippingChoices.php?view=editShippingChoice&shippingChoiceID=" . $shippingChoiceID));
		exit;

	}
	else if($action == "makeDefaultShippingChoice"){
		
		$shippingChoiceObj->setAsDefaultChoice($shippingChoiceID);
		$shippingChoiceObj->updateDatabase();
		
		header("Location: ./" . WebUtil::FilterURL("ad_shippingChoices.php?"));
		exit;

	}
	else if($action == "makeBasicShippingChoice"){
		
		$boolflag = WebUtil::GetInput("boolflag", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
		$shippingChoiceObj->setAsBasicChoice($shippingChoiceID, ($boolflag=="Y"?true:false));
		$shippingChoiceObj->updateDatabase();
		
		header("Location: ./" . WebUtil::FilterURL("ad_shippingChoices.php?"));
		exit;

	}
	
	else if($action == "createProductOverride"){
		
		// Set the initial Product Override Price to the base price.
		$intialPrice = $shippingChoiceObj->getBasePrice($shippingChoiceID);
		$shippingChoiceObj->setBasePriceOverrideForProduct($shippingChoiceID, WebUtil::GetInput("productID", FILTER_SANITIZE_INT), $intialPrice);
		$shippingChoiceObj->updateDatabase();
		
		header("Location: ./" . WebUtil::FilterURL("ad_shippingChoices.php?view=editShippingChoice&shippingChoiceID=" . $shippingChoiceID));
		exit;

	}
	else if($action == "deleteProductOverride"){
		
		$shippingChoiceObj->removeBasePriceOverrideForProduct($shippingChoiceID, WebUtil::GetInput("productID", FILTER_SANITIZE_INT));
		$shippingChoiceObj->updateDatabase();
		
		header("Location: ./" . WebUtil::FilterURL("ad_shippingChoices.php?view=editShippingChoice&shippingChoiceID=" . $shippingChoiceID));
		exit;

	}
	else if($action == "deleteQuantityBreak"){
		
		$shippingChoiceObj->removePricePerPoundQuantityBreak($shippingChoiceID, WebUtil::GetInput("weight", FILTER_SANITIZE_INT));
		$shippingChoiceObj->updateDatabase();
		
		header("Location: ./" . WebUtil::FilterURL("ad_shippingChoices.php?view=editShippingChoice&shippingChoiceID=" . $shippingChoiceID));
		exit;

	}
	else if($action == "CreateShippingMethodLink"){
		
		$shippingChoiceObj->linkShippingMethodToShippingChoice($shippingChoiceID, WebUtil::GetInput("shippingMethod", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES));
		$shippingChoiceObj->updateDatabase();
		
		header("Location: ./" . WebUtil::FilterURL("ad_shippingChoices.php?view=editShippingChoice&shippingChoiceID=" . $shippingChoiceID));
		exit;
	}
	else if($action == "RemoveShippingMethodLink"){
		
		$shippingChoiceObj->removeShippingMethodLink($shippingMethodLinkID);
		$shippingChoiceObj->updateDatabase();
		
		header("Location: ./" . WebUtil::FilterURL("ad_shippingChoices.php?view=editShippingChoice&shippingChoiceID=" . $shippingChoiceID));
		exit;
	}
	
	else if($action == "SetDefaultShippingMethodLink"){
		
		$shippingChoiceObj->setShippingDowngradeLinkDefault($shippingMethodLinkID);
		$shippingChoiceObj->updateDatabase();
		
		header("Location: ./" . WebUtil::FilterURL("ad_shippingChoices.php?view=editShippingChoice&shippingChoiceID=" . $shippingChoiceID));
		exit;
	}
	else if($action == "createQuantityBreak"){
		
		$weightValue = WebUtil::GetInput("weight", FILTER_SANITIZE_INT);
		
		$existingWeightValues = $shippingChoiceObj->getWeightValuesForQuantityBreaks($shippingChoiceID);
		
		// Don't overwrite a weight value
		if(!in_array($weightValue, $existingWeightValues)){
			
			// Set the initial price-per-pound to the base price-per-pound.
			$intialPrice = $shippingChoiceObj->getPricePerPound($shippingChoiceID);
			$shippingChoiceObj->setPricePerPoundQuantityBreak($shippingChoiceID, WebUtil::GetInput("weight", FILTER_SANITIZE_INT), $intialPrice);
			$shippingChoiceObj->updateDatabase();
		}
		header("Location: ./" . WebUtil::FilterURL("ad_shippingChoices.php?view=editShippingChoice&shippingChoiceID=" . $shippingChoiceID));
		exit;
	}

	else if($action == "MoveShippingChoiceUp"){
		
		ShippingChoices::moveShippingChoicePosition($shippingChoiceID, true, Domain::oneDomain());

		header("Location: ./" . WebUtil::FilterURL("ad_shippingChoices.php?view=start"));
		exit;
	}	
	else if($action == "MoveShippingChoiceDown"){
		
		ShippingChoices::moveShippingChoicePosition($shippingChoiceID, false, Domain::oneDomain());

		header("Location: ./" . WebUtil::FilterURL("ad_shippingChoices.php?view=start"));
		exit;
	}
	else if($action == "MoveLinkedShippingMethodUp"){
		
		ShippingChoices::moveLinkedShippingMethodPosition($shippingMethodLinkID, true, Domain::oneDomain());

		header("Location: ./" . WebUtil::FilterURL("ad_shippingChoices.php?view=editShippingChoice&shippingChoiceID=" . $shippingChoiceID));
		exit;
	}	
	else if($action == "MoveLinkedShippingMethodDown"){
		
		ShippingChoices::moveLinkedShippingMethodPosition($shippingMethodLinkID, false, Domain::oneDomain());

		header("Location: ./" . WebUtil::FilterURL("ad_shippingChoices.php?view=editShippingChoice&shippingChoiceID=" . $shippingChoiceID));
		exit;
	}	
	else{
		throw new Exception("Undefined Action");
	}
}







// ------------------------------ Build HTML  ----------------------------------



$t = new Templatex(".");

$t->set_file("origPage", "ad_shippingChoices-template.html");

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");


$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

// Build a Drop down menu showing all of the Shipping Choices
$editChoicesDropDown = array("listLabel"=>"Select a Shipping Choice to Edit", "mainMenu"=>"< Back to Shipping Choice List");

$allShippingChoicessArr = $shippingChoiceObj->getAllShippingChoices();

foreach ($allShippingChoicessArr as $shipID => $shippingChoiceName){
	
	if(!$shippingChoiceObj->isShippingChoiceActive($shipID))
		$shippingChoiceName .= " (inactive)";

	$editChoicesDropDown[$shipID] = $shippingChoiceName;
}
	
$t->set_var("SHIPPING_CHOICES_LIST", Widgets::buildSelect($editChoicesDropDown, "listLabel"));

$t->allowVariableToContainBrackets("SHIPPING_CHOICES_LIST");

if($view == "start"){

	$t->discard_block("origPage", "EditShippingChoiceBL");
	$t->discard_block("origPage", "NewShippingChoiceBL");
	
	
	$t->set_block("origPage","shippingChoiceBL","shippingChoiceBLout"); 

	// Only show the list of active shipping choices.  They can get access to the inactive ones within the drop down menu.
	$activeShippingChoicesArr = $shippingChoiceObj->getAllShippingChoices();
	
	// Extract Inner HTML blocks for the Arrow buttons out of the the Block we just extracted for the row.
	$t->set_block ( "shippingChoiceBL", "upLink", "upLinkout" );
	$t->set_block ( "shippingChoiceBL", "downLink", "downLinkout" );
	
	
	// Javascript Arrays initialized on the Shipping Choice Edit screen.
	$t->set_var("PRODUCT_OVERRIDES_ARR", "");
	$t->set_var("WEIGHT_QUANTITY_BREAKS_ARR", "");
	$t->set_var("SHIPPING_METHOD_CODES", "");
	
	
	$counter = 0;
	foreach($activeShippingChoicesArr as $thisShippingChoiceID => $thisShippingChoiceName) {
	
		$thisShippingChoiceName = WebUtil::htmlOutput($thisShippingChoiceName);
		$choiceColorCode = $shippingChoiceObj->getColorCode($thisShippingChoiceID);
		$thisShippingChoiceName = "<b><font color='$choiceColorCode'>$thisShippingChoiceName</font></b>";
		
		if(!$shippingChoiceObj->isShippingChoiceActive($thisShippingChoiceID))
			$thisShippingChoiceName .= " <font class='ReallySmallBody'>(inactive)</font>";
	
			
		
		$t->set_var("SHIPPING_CHOICE_NAME", $thisShippingChoiceName);
		$t->set_var("SHIPPING_CHOICE_ID", $thisShippingChoiceID);
		$t->allowVariableToContainBrackets("SHIPPING_CHOICE_NAME");
		
		$colorCode = $shippingChoiceObj->getColorCode($thisShippingChoiceID);
		
		
		
		// Show the Priority Level of selected on this shipping choice.
		$priority = $shippingChoiceObj->getPriorityDescription($shippingChoiceObj->getPriorityOfShippingChoice($thisShippingChoiceID));
		$t->set_var("PRIORITY", $priority);
		
		
		if($shippingChoiceObj->isDefaultShippingChoice($thisShippingChoiceID))
			$t->set_var("DEFAULT_IS_CHECKED", "checked");
		else
			$t->set_var("DEFAULT_IS_CHECKED", "");
			
			
		if($shippingChoiceObj->isShippingChoiceBasic($thisShippingChoiceID))
			$t->set_var("BASIC_IS_CHECKED", "checked");
		else
			$t->set_var("BASIC_IS_CHECKED", "");
		
		
			
		$t->set_var("TRANSIT_DAYS", $shippingChoiceObj->getTransitDays($thisShippingChoiceID));
		
		
		// Show an astrisk on the base price if it contains product overrides.
		$shippingBasePrice = '$' . number_format($shippingChoiceObj->getBasePrice($thisShippingChoiceID), 2);
		$productIDsOverride = $shippingChoiceObj->getProductIDsThatOverrideBasePrice($thisShippingChoiceID);
		if(!empty($productIDsOverride))
			$shippingBasePrice .= " <font color='#cc0000'>*</font>";
		
		$t->set_var("BASE_PRICE", $shippingBasePrice);
		$t->allowVariableToContainBrackets("BASE_PRICE");
		
		
		// Show an astrisk on the price per pound if it contains quantity breaks
		$pricePerPound =  '$' . number_format($shippingChoiceObj->getPricePerPound($thisShippingChoiceID), 2);
		$quantityBreaksArr = $shippingChoiceObj->getWeightValuesForQuantityBreaks($thisShippingChoiceID);
		if(!empty($quantityBreaksArr))
			$pricePerPound .= " <font color='#cc0000'>*</font>";
		
		$t->set_var("PRICE_POUND", $pricePerPound);
		$t->allowVariableToContainBrackets("PRICE_POUND");

	
		// Discard the inner blocks... for example.. if we are on the First record then discard the link for the button to go up.  
		// ... If we are on the last record don't show a down button.
		// Parse the nested block.  Make sure to set the 3rd parameter to FALSE to keep the block from growing inside of the loop.
		// Also, clear the output of the block we aren't using.
		if(sizeof($activeShippingChoicesArr) == 1){
			$t->set_var("upLinkout", "");
			$t->set_var("downLinkout", "");
		}
		else if ($counter == 0){
			$t->parse("downLinkout", "downLink", false );
			$t->set_var("upLinkout", "");
		}
		else if(($counter+1) == sizeof($activeShippingChoicesArr)){
			$t->parse ( "upLinkout", "upLink", false );
			$t->set_var("downLinkout", "");
		}
		else{
			$t->parse ( "upLinkout", "upLink", false );
			$t->parse("downLinkout", "downLink", false );
		}
		
		
		$t->parse("shippingChoiceBLout","shippingChoiceBL",true); 	
	
		$counter++;
	}
	
	if(empty($activeShippingChoicesArr))
		$t->discard_block("origPage", "ExistingShippingChoicesBL");
		

	// Only Show them the drop down for switching shipping choices when they are not on the start page.
	$t->discard_block("origPage", "ShippingChoicesListBL");
		
}
else if($view == "editShippingChoice"){

	$t->discard_block("origPage", "StartBL");
	$t->discard_block("origPage", "NewShippingChoiceBL");
	
	$t->set_var("SHIPPING_CHOICE_ID", $shippingChoiceID);

		
	$shippingChoiceName = WebUtil::htmlOutput($shippingChoiceObj->getShippingChoiceName($shippingChoiceID));
	$choiceColorCode = $shippingChoiceObj->getColorCode($shippingChoiceID);
	$shippingChoiceName = "<b><font color='$choiceColorCode'>$shippingChoiceName</font></b>";
	
	$t->set_var("SHIPPING_CHOICE_NAME", $shippingChoiceName);
	$t->allowVariableToContainBrackets("SHIPPING_CHOICE_NAME");
	
	$t->set_var("BASE_PRICE", number_format($shippingChoiceObj->getBasePrice($shippingChoiceID), 2));
	$t->set_var("PRICE_PER_POUND", number_format($shippingChoiceObj->getPricePerPound($shippingChoiceID), 2));
	
	$t->set_var("RURAL_FEE", number_format($shippingChoiceObj->getRuralFee($shippingChoiceID), 2));
	$t->set_var("EXTENDED_FEE", number_format($shippingChoiceObj->getExtendedDistanceFee($shippingChoiceID), 2));
	
	$t->set_var("TRANSIT_DAYS", $shippingChoiceObj->getTransitDays($shippingChoiceID));
	$t->set_var("COLOR_CODE", $shippingChoiceObj->getColorCode($shippingChoiceID));

	
	// Build a drop-down list of all products which do not already have a price override
	$allProductsArr = Product::getActiveProductIDsArr($dbCmd, Domain::oneDomain());
	$productOverridenAlready = $shippingChoiceObj->getProductIDsThatOverrideBasePrice($shippingChoiceID);
	$productOverrideList = array("choose"=>"Override Base Price");
	
	foreach($allProductsArr as $thisProductID){
		if(!in_array($thisProductID, $productOverridenAlready))
			$productOverrideList[$thisProductID] = Product::getFullProductName($dbCmd, $thisProductID);
	}
	
	$t->set_block("origPage","ProductOverrideBL","ProductOverrideBLout"); 

	foreach($productOverridenAlready as $thisProductOverrideID){
		
		$t->set_var("PRODUCT_NAME", WebUtil::htmlOutput(Product::getFullProductName($dbCmd, $thisProductOverrideID)));
		$t->set_var("BASE_PRICE_PRODUCT", $shippingChoiceObj->getBasePrice($shippingChoiceID, $thisProductOverrideID));
		$t->set_var("PRODUCT_ID", $thisProductOverrideID);

		$t->parse("ProductOverrideBLout","ProductOverrideBL",true); 	
	}
	
	if(empty($productOverridenAlready))
		$t->discard_block("origPage", "AllProductOverridesBL");
		
	$t->set_var("PRODUCT_OVERRIDE_LIST", Widgets::buildSelect($productOverrideList, "choose"));
	$t->allowVariableToContainBrackets("PRODUCT_OVERRIDE_LIST");
	
	// Set all of the Quantity Breaks
	$weightValuesArr = $shippingChoiceObj->getWeightValuesForQuantityBreaks($shippingChoiceID);

	$t->set_block("origPage","QuantityBreakBL","QuantityBreakBLout"); 
	foreach ($weightValuesArr as $weightVal) {
		
		$t->set_var("WEIGHT", $weightVal);
		$t->set_var("PRICE_PER_POUND_QUANTITY_BREAK", $shippingChoiceObj->getPricePerPound($shippingChoiceID, $weightVal));
		
		$t->parse("QuantityBreakBLout","QuantityBreakBL",true); 
	}
	
	if(empty($weightValuesArr))
		$t->discard_block("origPage", "AllQuantityBreaksBL");
	
	
	$t->set_var("PRIORITY_LIST", Widgets::buildSelect($shippingChoiceObj->getPriorityList(), $shippingChoiceObj->getPriorityOfShippingChoice($shippingChoiceID)));
	$t->allowVariableToContainBrackets("PRIORITY_LIST");
	
	// If the Domain is set for Shipping Downgrades, then let all of the shipipng methods linked to it.
	if($shippingChoiceObj->isShippingDowngrade()){
		
		// Get rid of the 1-to-1 shipping method linking section
		$t->discard_block("origPage", "Shipping1to1MapBL");
		
		$t->set_block("origPage","ShippingCodesBL","ShippingCodesBLout"); 
		
		// Extract Inner HTML blocks for the Arrow buttons out of the the Block we just extracted for the row.
		$t->set_block ( "ShippingCodesBL", "upLink", "upLinkout" );
		$t->set_block ( "ShippingCodesBL", "downLink", "downLinkout" );
		$t->set_block ( "ShippingCodesBL", "MixMaxWeightBL", "MixMaxWeightBLout" );
		
		
		$counter = 0;
		$shippingCodeLinkIDs = $shippingChoiceObj->getShippingMethodLinkIDs($shippingChoiceID);
		foreach ($shippingCodeLinkIDs as $shippingMethodLinkID) {
	
			$shippingCode = $shippingChoiceObj->getShippingMethodCodeFromLinkID($shippingMethodLinkID);
			
			// Just in case the Shipping Code was removed from the class after the DB was saved.
			if(!$shippingMethodsObj->doesShippingCodeExist($shippingCode))
				continue;
			
			
			$t->set_var("SHIPPING_METHOD_NAME", WebUtil::htmlOutput($shippingMethodsObj->getShippingMethodName($shippingCode, true)));
			$t->set_var("SHIPPING_METHOD_CODE", $shippingCode);
			$t->set_var("SHIPPING_METHOD_ALERT", WebUtil::htmlOutput($shippingChoiceObj->getAlertMessageForShippingMethod($shippingChoiceID, $shippingCode)));
			$t->set_var("SHIPPING_METHOD_LINK_ID", $shippingMethodLinkID);

			
			// Default Shipping Method Links can not choose to restrict based upon Address Type.  They must always be available.
			if($shippingChoiceObj->getDefaultShippingMethodLinkID($shippingChoiceID) == $shippingMethodLinkID)
				$addressTypesList = array("N"=>"Doesn't Matter");
			else
				$addressTypesList = array("N"=>"Doesn't Matter", "R"=>"Residential", "C"=>"Commercial");
				
			$t->set_var("SHIPPING_ADDRESS_TYPE_LIST", Widgets::buildSelect($addressTypesList, $shippingChoiceObj->getAddressTypeForShippingDowngradeLink($shippingMethodLinkID)));
			$t->allowVariableToContainBrackets("SHIPPING_ADDRESS_TYPE_LIST");
			
			
			// Look for Duplicate Shipping Method Codes linked to this Choice.
			// If we find any below... then we want to disable the "Alert" input box.  we can only have 1 alert per Shipping Method.
			$otherShippingCodeLinks = $shippingChoiceObj->getShippingMethodLinkIDs($shippingChoiceID);
			$secondCounter = 0;
			$anotherMatchingShippingCodeBelowFlag = false;
			foreach($otherShippingCodeLinks as $otherShippingCodeLinkID){
				
				if($secondCounter <= $counter){
					$secondCounter++;
					continue;
				}
				
				$shippingCodeUnderneath = $shippingChoiceObj->getShippingMethodCodeFromLinkID($otherShippingCodeLinkID);
				
				if($shippingCodeUnderneath == $shippingCode)
					$anotherMatchingShippingCodeBelowFlag = true;
				
				$secondCounter++;
			}
			
			if($anotherMatchingShippingCodeBelowFlag)
				$t->set_var("DISABLED_ALERT", 'disabled="disabled"');
			else
				$t->set_var("DISABLED_ALERT", '');
			
				
	
			
			
			$minWeight = $shippingChoiceObj->getMinimumWeightForShippingMethodLink($shippingMethodLinkID);
			$maxWeight = $shippingChoiceObj->getMaximumWeightForShippingMethodLink($shippingMethodLinkID);
			
			// Don't show 0's if they are both zero.
			if(empty($minWeight) && empty($maxWeight)){
				$minWeight = "";
				$maxWeight = "";
			}
			
			$t->set_var("SHIPPING_METHOD_MIN_WEIGHT", $minWeight);
			$t->set_var("SHIPPING_METHOD_MAX_WEIGHT", $maxWeight);
			
			
			if($shippingChoiceObj->getDefaultShippingMethodLinkID($shippingChoiceID) == $shippingMethodLinkID){
				$t->set_var("DEFAULT_IS_CHECKED", "checked");
				$t->set_var("MixMaxWeightBLout", "");
			}
			else{
				$t->set_var("DEFAULT_IS_CHECKED", "");
				$t->parse ( "MixMaxWeightBLout", "MixMaxWeightBL", false );
			}
			

			// Discard the inner blocks... for example.. if we are on the First record then discard the link for the button to go up.  
			// ... If we are on the last record don't show a down button.
			// Parse the nested block.  Make sure to set the 3rd parameter to FALSE to keep the block from growing inside of the loop.
			// Also, clear the output of the block we aren't using.
			if(sizeof($shippingCodeLinkIDs) == 1){
				$t->set_var("upLinkout", "");
				$t->set_var("downLinkout", "");
			}
			else if ($counter == 0){
				$t->parse("downLinkout", "downLink", false );
				$t->set_var("upLinkout", "");
			}
			else if(($counter+1) == sizeof($shippingCodeLinkIDs)){
				$t->parse ( "upLinkout", "upLink", false );
				$t->set_var("downLinkout", "");
			}
			else{
				$t->parse ( "upLinkout", "upLink", false );
				$t->parse("downLinkout", "downLink", false );
			}
					
			$counter++;
			
			$t->parse("ShippingCodesBLout","ShippingCodesBL",true); 
		}
		
		if(empty($shippingCodeLinkIDs))
			$t->discard_block("origPage", "NoExistingShippingLinksBL");
			
		
		// Build a Drop down menu with all of the Shipping Methods that have not been linked to this shipping choice yet.
		$listMenu = array("choose"=>"Link a Shipping Method to this Shipping Choice");
			
		$allShippingMethods = $shippingMethodsObj->getShippingMethodsHash();
		foreach(array_keys($allShippingMethods) as $shippingCode)
			$listMenu[$shippingCode] = $shippingMethodsObj->getShippingMethodName($shippingCode, true);
		
		$t->set_var("SHIPPING_METHOD_LINK_LIST", Widgets::buildSelect($listMenu, "choose"));
		$t->allowVariableToContainBrackets("SHIPPING_METHOD_LINK_LIST");
		
	}
	else{
		
		$t->discard_block("origPage", "ShippingDowngradingBL");
		

		$shippingCodeLinkIDs = $shippingChoiceObj->getShippingMethodLinkIDs($shippingChoiceID);
		
		// For Shipping 1-to-1 mapping, there will be a maximum of 1 linked shipping method.
		if(!empty($shippingCodeLinkIDs)) {
			
			$shippingMethodLinkID = current($shippingCodeLinkIDs);
			$shippingCode = $shippingChoiceObj->getShippingMethodCodeFromLinkID($shippingMethodLinkID);
			
			$t->set_var("SHIPPING_METHOD_NAME", WebUtil::htmlOutput($shippingMethodsObj->getShippingMethodName($shippingCode, true)));
			$t->set_var("SHIPPING_METHOD_CODE", $shippingCode);
			$t->set_var("SHIPPING_METHOD_ALERT", WebUtil::htmlOutput($shippingChoiceObj->getAlertMessageForShippingMethod($shippingChoiceID, $shippingCode)));
			$t->set_var("SHIPPING_METHOD_LINK_ID", $shippingMethodLinkID);
			
			$shippingMethodDropDownLabel = "Change Shipping Method Link";
		}
		else{
			$t->discard_block("origPage", "NoExistingShippingLinksBL");
			
			$shippingMethodDropDownLabel = "Link a Shipping Method to this Shipping Choice";
		}
			
			
		// Build a Drop down menu with all of the Shipping Methods that have not been linked to any other Shipping Choices
		$listMenu = array("choose"=>$shippingMethodDropDownLabel);
			
		$shippingCodesAreadyTakenArr = $shippingChoiceObj->getUniqueListOfAllShippingMethodsCodesAlreadyLinked();
		
		$allShippingMethods = $shippingMethodsObj->getShippingMethodsHash();
		foreach(array_keys($allShippingMethods) as $shippingCode){
			
			// It is not OK to have duplicated Shipping Codes on 1-to-1 shipping method linking.
			if(!in_array($shippingCode, $shippingCodesAreadyTakenArr))
				$listMenu[$shippingCode] = $shippingMethodsObj->getShippingMethodName($shippingCode, true);

		}
		
		$t->set_var("SHIPPING_METHOD_LINK_LIST", Widgets::buildSelect($listMenu, "choose"));
		$t->allowVariableToContainBrackets("SHIPPING_METHOD_LINK_LIST");
		
	}
	
	
	// Javascript Array
	$productOverriddenJS = implode("', '", $productOverridenAlready);
	if(!empty($productOverriddenJS))
		$productOverriddenJS = "'" . $productOverriddenJS . "'";
	$t->set_var("PRODUCT_OVERRIDES_ARR", $productOverriddenJS);
	
	// Javascript Array
	$weightValuesJS = implode("', '", $weightValuesArr);
	if(!empty($weightValuesJS))
		$weightValuesJS = "'" . $weightValuesJS . "'";
	$t->set_var("WEIGHT_QUANTITY_BREAKS_ARR", $weightValuesJS);
	
	// Javascript Array
	$shippingMethodCodesJS = implode("', '", $shippingCodeLinkIDs);
	if(!empty($shippingMethodCodesJS))
		$shippingMethodCodesJS = "'" . $shippingMethodCodesJS . "'";
	$t->set_var("SHIPPING_METHOD_CODES",$shippingMethodCodesJS);
	

}
else if($view == "newShippingChoice"){

	$t->discard_block("origPage", "StartBL");
	$t->discard_block("origPage", "EditShippingChoiceBL");

	$t->set_var("PRODUCT_OVERRIDES_ARR", "");
	$t->set_var("WEIGHT_QUANTITY_BREAKS_ARR", "");
	$t->set_var("SHIPPING_METHOD_CODES", "");
}
else{
	throw new Exception("Illegal View Type");
}









$t->pparse("OUT","origPage");




?>
