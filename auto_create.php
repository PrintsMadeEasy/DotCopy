<?

require_once("library/Boot_Session.php");


$company = WebUtil::GetInput("company", FILTER_SANITIZE_STRING_ONE_LINE);

$dbCmd = new DbCmd();

$user_sessionID =  WebUtil::GetSessionID();

$offlineMarketingDataObj = new OfflineMarketingUrl();
$t = new Templatex();
$t->set_file("origPage", "auto_create-template.html");

$t->set_var("SESSION_ID", WebUtil::GetSessionID());

// For the editing tool
WebUtil::SetSessionVar("initialized", 1);

if(!$offlineMarketingDataObj->checkIfIpAddressHasPermission() || !$offlineMarketingDataObj->getIndustryName($company)){
	
	// Keep them from hacking.
	if(!$offlineMarketingDataObj->getIndustryName($company))
		$offlineMarketingDataObj->addIncorrectIpAccess();
	
	$t->set_var("PROJECT_IDS_ARR", "");
	
	// Print out the default page... no thumbnails will be shown
	//$t->discard_block("origPage", "NoCompanyFoundBL");
	$t->discard_block("origPage", "CompanyFoundButNoDesignsBL");
	$t->discard_block("origPage", "ProductThumbnailsBL");
	
	$t->pparse("OUT","origPage");
	exit();
}





$userSessionIDsArr = ShoppingCart::GetProjectSessionIDsInShoppingCart($dbCmd, $user_sessionID);


$mailingAddressObj = $offlineMarketingDataObj->getAddressObjFromLocatorString($company);
if(!$mailingAddressObj)
	throw new Exception("No mailing address object was found, even though a industry category was.");
	
	
$t->set_var("COMPANY_NAME", WebUtil::htmlOutput($mailingAddressObj->getCompanyName()));
$t->set_var("INDUSTRY_NAME", WebUtil::htmlOutput($offlineMarketingDataObj->getIndustryName($company)));


if(!empty($userSessionIDsArr)){
	
	// If the user already has stuff in their shopping cart, then don't auto-create new projects.
	$t->discard_block("origPage", "NoCompanyFoundBL");
	$t->discard_block("origPage", "CompanyFoundButNoDesignsBL");
	//$t->discard_block("origPage", "ProductThumbnailsBL");
	
	
}
else{
	
	// Try to auto-create projects for the users and inject the company details into the Quick Edit fields
	
	// Get the top product importance for the domain.  Then we want to see if there is a matching keyword on that template.
	$dbCmd->Query("SELECT ID from products WHERE DomainID=" . Domain::oneDomain() . " AND ProductStatus='G' ORDER BY ProductImportance DESC LIMIT 1");
	$topProductID = $dbCmd->GetValue();

	if(empty($topProductID))
		throw new Exception("No Product exists for this domain");
		
	$industryTemplateIDs = ArtworkTemplate::GetSearchResultsForTempaltes($dbCmd, ("industry:" . $offlineMarketingDataObj->getIndustryName($company)), "", $topProductID);

	if(empty($industryTemplateIDs)){
		
		$t->set_var("PROJECT_IDS_ARR", implode(", ", $userSessionIDsArr));
		
		// Print out the default page... no thumbnails will be shown
		$t->discard_block("origPage", "NoCompanyFoundBL");
		//$t->discard_block("origPage", "CompanyFoundButNoDesignsBL");
		$t->discard_block("origPage", "ProductThumbnailsBL");
		
		$t->pparse("OUT","origPage");
		exit();
	}
	
	

	
	
	// Get the top template ID (sorted by popularity).  Use that to find out if there are any more template links
	$topTemplateID = current($industryTemplateIDs);
	
	$templateLinksObj = new TemplateLinks();
	$matchingProductIDsArr = $templateLinksObj->getProductIdsLinkedToThis(TemplateLinks::TEMPLATE_AREA_SEARCH_ENGINE, $topTemplateID);
	
	$matchingTemplates = $templateLinksObj->getTemplatesLinkedToThis(TemplateLinks::TEMPLATE_AREA_SEARCH_ENGINE, $topTemplateID);
	
	// Don't forget to include our source template.  Merge that into the front of the stack.
	$matchingTemplates[$topTemplateID] = TemplateLinks::TEMPLATE_AREA_SEARCH_ENGINE;
	
	$productIdsAlreadyIncludedArr = array();
	
	foreach($matchingTemplates as $thisTemplateID => $thisTemplateArea){

		$productIDofTemplateID = ProjectBase::GetProductIDFromProjectRecord($dbCmd, $thisTemplateID, TemplateLinks::getViewTypeFromTemplateArea($thisTemplateArea));
		
		// Don't let multiple projects of the same product be added to the cart.
		if(in_array($productIDofTemplateID, $productIdsAlreadyIncludedArr))
			continue;
		$productIdsAlreadyIncludedArr[] = $productIDofTemplateID;
		
		$artworkTemplateXML = ArtworkLib::GetArtXMLfile($dbCmd, TemplateLinks::getViewTypeFromTemplateArea($thisTemplateArea), $thisTemplateID);
		$artworkTemplateObj = new ArtworkInformation($artworkTemplateXML);
		
		// Go through all of the text layers looking for Quick edit fields that can have details from the Mailing Address object subsituted.
		for($sideNum=0; $sideNum < sizeof($artworkTemplateObj->SideItemsArray); $sideNum++){
			
			for($layerNum=0; $layerNum < sizeof($artworkTemplateObj->SideItemsArray[$sideNum]->layers); $layerNum++){
	
				// Skip Layers that may have already been removed.
				if($artworkTemplateObj->SideItemsArray[$sideNum]->layers[$layerNum]->LayerType != "text")
					continue;
					
				$addressForUser = $mailingAddressObj->getAddressOne();
				if($mailingAddressObj->getAddressTwo() != "")
					$addressForUser .= "!br!" . $mailingAddressObj->getAddressTwo();
					
				$addressForUser .= "!br!" . $mailingAddressObj->getCity() . ", " . $mailingAddressObj->getState();
				$addressForUser .= " " . $mailingAddressObj->getZipCode();

				$textChangedFlag = false;
				
				$fieldName = $artworkTemplateObj->SideItemsArray[$sideNum]->layers[$layerNum]->LayerDetailsObj->field_name;
				if($fieldName == "Company"){
					$artworkTemplateObj->SideItemsArray[$sideNum]->layers[$layerNum]->LayerDetailsObj->message = $mailingAddressObj->getCompanyName();
					$textChangedFlag = true;
				}
				else if($fieldName == "Name"){
					$artworkTemplateObj->SideItemsArray[$sideNum]->layers[$layerNum]->LayerDetailsObj->message = $mailingAddressObj->getAttention();
					$textChangedFlag = true;
				}
				else if($fieldName == "Address"){
					$artworkTemplateObj->SideItemsArray[$sideNum]->layers[$layerNum]->LayerDetailsObj->message = $addressForUser;
					$textChangedFlag = true;
				}
				else if($fieldName == "Phone"){
					$artworkTemplateObj->SideItemsArray[$sideNum]->layers[$layerNum]->LayerDetailsObj->message = $mailingAddressObj->getPhoneNumber();
					$textChangedFlag = true;
				}
				
				if($textChangedFlag)
					$artworkTemplateObj->makeShadowTextMatchParent($sideNum, $artworkTemplateObj->SideItemsArray[$sideNum]->layers[$layerNum]->level);

			
			}
		}
		
		// Now create a new project session and use the Artwork XML that has been filled in with company details.
		$newProjectSessionID = ProjectSession::CreateNewDefaultProjectSession($dbCmd, $productIDofTemplateID, $user_sessionID);
		$projectSessionObj = ProjectBase::getProjectObjectByViewType($dbCmd, "session", $newProjectSessionID);
		$projectSessionObj->setArtworkFile($artworkTemplateObj->GetXMLdoc(), false);
		$projectSessionObj->updateDatabase();

		ShoppingCart::InsertProjectSessionIntoShoppingCart($newProjectSessionID);
		
		
	}
	
	// Refresh this varaible... after we filled it up.
	$userSessionIDsArr = ShoppingCart::GetProjectSessionIDsInShoppingCart($dbCmd, $user_sessionID);
	
	
	$t->discard_block("origPage", "NoCompanyFoundBL");
	$t->discard_block("origPage", "CompanyFoundButNoDesignsBL");
	//$t->discard_block("origPage", "ProductThumbnailsBL");
	

	
	
	
}

$t->set_var("PROJECT_IDS_ARR", implode(", ", $userSessionIDsArr));

	
$t->set_block("origPage","ProjectBL","ProjectBLout");

foreach($userSessionIDsArr as $thisProjectID){

	$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, "session", $thisProjectID);
	$productObj = $projectObj->getProductObj();
	
	
	$t->set_var("PROJECT_ID", $thisProjectID);
	$t->set_var("PRODUCT_ID", $projectObj->getProductID());
	$t->set_var("PRODUCT_NAME", WebUtil::htmlOutput($productObj->getProductTitle()));
	
	$t->parse("ProjectBLout","ProjectBL",true);
	
}



$t->pparse("OUT","origPage");






