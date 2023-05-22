<?

require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("EDIT_PRODUCT"))
		throw new Exception("You don't have permission to edit a Product.");


$domainObj = Domain::singleton();

$editProduct = WebUtil::GetInput("editProduct", FILTER_SANITIZE_INT);
$view = WebUtil::GetInput("view", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);


// Make sure that the user doesn't switch domains after they have selected a Product ID.
// If we don't do this, it isn't harmful, but it could be confusing to the user.
if(!empty($editProduct)){
	
	$editProduct = intval($editProduct);
	
	$domainIDofProduct = Product::getDomainIDfromProductID($dbCmd, $editProduct);
	
	if(!$AuthObj->CheckIfUserCanViewDomainID($domainIDofProduct))
		throw new Exception("The Product belongs to another domain or it can't be viewed.");
		
	Domain::enforceTopDomainID($domainIDofProduct);
}




Domain::setTopDomainID(Domain::oneDomain());



$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
if($action){
	
	WebUtil::checkFormSecurityCode();

	if($action == "showInactiveProducts"){
	
		if(WebUtil::GetInput("answer", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES) == "yes")
			WebUtil::SetSessionVar("showInactiveProducts", "yes");
		else
			WebUtil::SetSessionVar("showInactiveProducts", "no");

		header("Location: ./" . WebUtil::FilterURL("ad_product_setup.php?"));
		exit;
	}
	else if($action == "moveProductOption"){
	
		if(WebUtil::GetInput("direction", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES) == "up")
			$shiftUp = true;
		else
			$shiftUp = false;
			
		$optionName = WebUtil::GetInput("optionName", FILTER_SANITIZE_STRING_ONE_LINE);
		
		Product::moveProductOptionSort($editProduct, $optionName, $shiftUp);

		header("Location: ./" . WebUtil::FilterURL("ad_product_setup.php?view=optionsPricing&editProduct=" . $editProduct . "&viewOption=" . urlencode($optionName)));
		exit;
	}
	else if($action == "moveProductOptionChoice"){
	
		if(WebUtil::GetInput("direction", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES) == "up")
			$shiftUp = true;
		else
			$shiftUp = false;
			
		$optionName = WebUtil::GetInput("optionName", FILTER_SANITIZE_STRING_ONE_LINE);
		$choiceName = WebUtil::GetInput("choiceName", FILTER_SANITIZE_STRING_ONE_LINE);
			
		Product::moveProductOptionChoiceSort($editProduct, $optionName, $choiceName, $shiftUp);

		header("Location: ./" . WebUtil::FilterURL("ad_product_setup.php?view=optionsPricing&editProduct=$editProduct&viewOption=" . urlencode($optionName) . "&viewChoice=" . urlencode($choiceName)));
		exit;
	}
	else if($action == "newProduct"){
	
		$newProductID = Product::createNewProduct($dbCmd);

		header("Location: ./" . WebUtil::FilterURL("ad_product_setup.php?view=productMain&editProduct=" . $newProductID));
		exit;
	}
	else if($action == "deleteThumbCopyIcon"){
	
		$productObj = new Product($dbCmd, $editProduct);
		$nullVal = null;
		$productObj->setThumbnailCopyIconJPG($nullVal);
		$productObj->updateProduct();

		header("Location: ./" . WebUtil::FilterURL("ad_product_setup.php?view=productMain&editProduct=" . $editProduct));
		exit;
	}
	else if($action == "deleteThumbBackgroundJPEG"){
	
		$productObj = new Product($dbCmd, $editProduct);
		$nullVal = null;
		$productObj->setThumbnailBackJPG($nullVal);
		$productObj->updateProduct();

		header("Location: ./" . WebUtil::FilterURL("ad_product_setup.php?view=productMain&editProduct=" . $editProduct));
		exit;
	}
	else if($action == "deleteTemplateBackgroundJpgLandscape"){
	
		$productObj = new Product($dbCmd, $editProduct);
		$nullVal = null;
		$productObj->setTempPrevBackLandscapeJPG($nullVal);
		$productObj->updateProduct();

		header("Location: ./" . WebUtil::FilterURL("ad_product_setup.php?view=productMain&editProduct=" . $editProduct));
		exit;
	}
	else if($action == "deleteTemplateBackgroundJpgPortrait"){
	
		$productObj = new Product($dbCmd, $editProduct);
		$nullVal = null;
		$productObj->setTempPrevBackPortraitJPG($nullVal);
		$productObj->updateProduct();

		header("Location: ./" . WebUtil::FilterURL("ad_product_setup.php?view=productMain&editProduct=" . $editProduct));
		exit;
	}
	else if($action == "deleteTemplatePreviewMask"){
	
		$productObj = new Product($dbCmd, $editProduct);
		$nullVal = null;
		$productObj->setTemplatePreviewMaskPNG($nullVal);
		$productObj->updateProduct();

		header("Location: ./" . WebUtil::FilterURL("ad_product_setup.php?view=productMain&editProduct=" . $editProduct));
		exit;
	}
	else if($action == "deleteVendor"){
		
		$productObj = new Product($dbCmd, $editProduct);

		$productObj->removeVendorID(WebUtil::GetInput("vendorID", FILTER_SANITIZE_INT));

		$productObj->updateProduct();

		header("Location: ./" . WebUtil::FilterURL("ad_product_setup.php?view=vendors&editProduct=" . $editProduct));
		exit;
	}



	else if($action == "setDefaultProductForStatusDescriptions"){
	
		
		ProductStatus::setDefaultProductID($dbCmd,$editProduct);

		header("Location: ./" . WebUtil::FilterURL("ad_product_setup.php?view=status&editProduct=" . $editProduct));
		exit;
	}
	else if($action == "deleteSettingsForProductStatusDescriptions"){
	
		
		ProductStatus::deleteSettingsForProductID($dbCmd,$editProduct);

		header("Location: ./" . WebUtil::FilterURL("ad_product_setup.php?view=status&editProduct=" . $editProduct));
		exit;
	}
	else if($action == "saveProductStatusForm"){
	
	
		$productStatusObj = new ProductStatus($dbCmd,$editProduct);
		
		// Loop through all of our Status Characters used for Projects
		// Try to get the overrided value for the Product from the URL.
		// The Title can't be blank... if it is, just ignore it.
		$projectStatusArr = array_keys(ProjectStatus::GetStatusDescriptionsHash());
		foreach($projectStatusArr as $thisStatusChar){

			$statusTitle = WebUtil::GetInput("statusTitle_" . $thisStatusChar, FILTER_SANITIZE_STRING_ONE_LINE);
			$statusDesc = WebUtil::GetInput("statusDesc_" . $thisStatusChar, FILTER_SANITIZE_STRING_ONE_LINE);

			if(empty($statusTitle))
				continue;
			
			$productStatusObj->setStatusTitle($thisStatusChar, $statusTitle);
			$productStatusObj->setStatusDesc($thisStatusChar, $statusDesc);

		}
		
		$productStatusObj->updateDatabase();

		header("Location: ./" . WebUtil::FilterURL("ad_product_setup.php?view=status&editProduct=" . $editProduct));
		exit;
	}


	else if($action == "changeOptionName"){
	
		if(!$AuthObj->CheckForPermission("CHANGE_PRODUCT_OPTION_NAMES"))
			throw new Exception("Permission Denied changing Production Option Name");
		
		$productObj = new Product($dbCmd, $editProduct);

		$oldOptionName = WebUtil::GetInput("oldOptionName", FILTER_SANITIZE_STRING_ONE_LINE);
		$newOptionName = WebUtil::GetInput("newOptionName", FILTER_SANITIZE_STRING_ONE_LINE);
		
		if(!$productObj->checkIfProductOptionExists($oldOptionName))
			WebUtil::PrintAdminError("You are trying to change to a new Option Name, but the old option name does not exist.");
		if($productObj->checkIfProductOptionExists($newOptionName))
			WebUtil::PrintAdminError("You are trying to change to a new Option Name that is already defined for this product.");

		Product::changeOptionName($editProduct, $oldOptionName, $newOptionName);

		header("Location: ./" . WebUtil::FilterURL("ad_product_setup.php?view=optionsPricing&editProduct=$editProduct&viewOption=" . urlencode($newOptionName)));
		exit;
	}
	
	else if($action == "changeOptionChoiceName"){
	
		if(!$AuthObj->CheckForPermission("CHANGE_PRODUCT_OPTION_NAMES"))
			throw new Exception("Permission Denied changing Production Option Choice Name");
		
		$productObj = new Product($dbCmd, $editProduct);

		$optionName = WebUtil::GetInput("optionName", FILTER_SANITIZE_STRING_ONE_LINE);
		$oldChoiceName = WebUtil::GetInput("oldChoiceName", FILTER_SANITIZE_STRING_ONE_LINE);
		$newChoiceName = WebUtil::GetInput("newChoiceName", FILTER_SANITIZE_STRING_ONE_LINE);
		
		if(!$productObj->checkIfProductOptionExists($optionName))
			WebUtil::PrintAdminError("You are trying to change to a new Choice Name, but the option name does not exist.");
		if(!$productObj->checkIfOptionChoiceExists($optionName, $oldChoiceName ))
			WebUtil::PrintAdminError("You are trying to change to a new Choice Name, but the old choice name does not exist.");
		if($productObj->checkIfOptionChoiceExists($optionName, $newChoiceName))
			WebUtil::PrintAdminError("You are trying to change to a new Choice Name that is already defined for this product option.");

		Product::changeOptionChoiceName($editProduct, $optionName, $oldChoiceName, $newChoiceName);

		header("Location: ./" . WebUtil::FilterURL("ad_product_setup.php?view=optionsPricing&editProduct=$editProduct&viewOption=" . urlencode($optionName) . "&viewChoice=" . urlencode($newChoiceName)));
		exit;
	}

	else if($action == "createNewOptionName"){
	
		$productObj = new Product($dbCmd, $editProduct);

		if($productObj->checkIfProductOptionExists(WebUtil::GetInput("newOptionName", FILTER_SANITIZE_STRING_ONE_LINE))){
			$newOptionError = "You are trying to add an Option Name that is already defined for this product.";
			WebUtil::PrintAdminError($newOptionError);
		}

		$productObj->addNewProductOption(WebUtil::GetInput("newOptionName", FILTER_SANITIZE_STRING_ONE_LINE));
		
		$productObj->updateProduct();

		header("Location: ./" . WebUtil::FilterURL("ad_product_setup.php?view=optionsPricing&editProduct=" . $editProduct));
		exit;
	}
	
	else if($action == "createNewChoiceName"){
	
		$productObj = new Product($dbCmd, $editProduct);
		
		$optionName = WebUtil::GetInput("optionName", FILTER_SANITIZE_STRING_ONE_LINE);
		$newChoiceName = WebUtil::GetInput("newChoiceName", FILTER_SANITIZE_STRING_ONE_LINE);
		
		if(!$productObj->checkIfProductOptionExists($optionName))
			throw new Exception("Error on Action createNewChoiceName. The option name does not exist.");

		if($productObj->checkIfOptionChoiceExists($optionName, $newChoiceName)){
			$newOptionError = "You are trying to add a Choice Name that is already defined for this Product Option.";
			WebUtil::PrintAdminError($newOptionError);
		}

		$productObj->addNewOptionChoice($optionName, $newChoiceName);
		
		$productObj->updateProduct();

		header("Location: ./" . WebUtil::FilterURL("ad_product_setup.php?view=optionsPricing&editProduct=" . $editProduct . "&viewOption=" . urlencode($optionName)));
		exit;
	}

	
	else if($action == "deleteOptionName"){
	
		$productObj = new Product($dbCmd, $editProduct);

		$productObj->removeProductOption(WebUtil::GetInput("optionName", FILTER_SANITIZE_STRING_ONE_LINE));
		
		$productObj->updateProduct();

		header("Location: ./" . WebUtil::FilterURL("ad_product_setup.php?view=optionsPricing&editProduct=" . $editProduct));
		exit;
	}
	else if($action == "deleteChoiceName"){
	
		$productObj = new Product($dbCmd, $editProduct);
		
		$optionName = WebUtil::GetInput("optionName", FILTER_SANITIZE_STRING_ONE_LINE);
		$choiceName = WebUtil::GetInput("choiceName", FILTER_SANITIZE_STRING_ONE_LINE);

		$productObj->removeOptionChoice($optionName , $choiceName);
		
		$productObj->updateProduct();

		header("Location: ./" . WebUtil::FilterURL("ad_product_setup.php?view=optionsPricing&editProduct=" . $editProduct . "&viewOption=" . urlencode($optionName)));
		exit;
	}
	else if($action == "updateOptionDescription"){
	
		$productObj = new Product($dbCmd, $editProduct);
		
		$optionDescription = WebUtil::GetInput("optionDescription", FILTER_UNSAFE_RAW);
		$optionDescriptionHTMLformat = WebUtil::GetInput("optionDescriptionHTMLformat", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
		$optionName = WebUtil::GetInput("optionName", FILTER_SANITIZE_STRING_ONE_LINE);
		$optionNameAlias = WebUtil::GetInput("optionNameAlias", FILTER_SANITIZE_STRING_ONE_LINE);

		$originalOptionNameAlias = $productObj->getOptionDetailObj($optionName)->optionNameAlias;
		
		$productObj->setOptionDescription($optionName, $optionDescription, ($optionDescriptionHTMLformat == "yes" ? true : false));
		
		$productObj->setOptionNameAlias($optionName, $optionNameAlias);
		
		$productObj->updateProduct();

		// Refresh all of the Option Aliases on the Open Orders if they do not match.
		if($optionNameAlias != $originalOptionNameAlias)
			ProjectOrdered::refreshOptionAliasesOnOpenOrders($editProduct);

		header("Location: ./" . WebUtil::FilterURL("ad_product_setup.php?view=optionsPricing&editProduct=" . $editProduct . "&viewOption=" . urlencode($optionName)));
		exit;
	}
	else if($action == "changeOptionConroller"){
	
		$productObj = new Product($dbCmd, $editProduct);
		
		$controllerType = WebUtil::GetInput("controllerType", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
		$optionName = WebUtil::GetInput("optionName", FILTER_SANITIZE_STRING_ONE_LINE);
		$flag = WebUtil::GetInput("flag", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
		
		if($controllerType == "artworkSides")
			$productObj->setOptionArtworkSideController($optionName, ($flag == "yes" ? true : false));
		else if($controllerType == "commonProduction")
			$productObj->setOptionsInCommonController($optionName, ($flag == "yes" ? true : false));
		else if($controllerType == "adminOption")
			$productObj->setOptionAdminController($optionName, ($flag == "yes" ? true : false));
		else if($controllerType == "variableImages")
			$productObj->setOptionVariableImageController($optionName, ($flag == "yes" ? true : false));
		else if($controllerType == "searchReplace")
			$productObj->setOptionArtSearchReplaceController($optionName, ($flag == "yes" ? true : false));
		else if($controllerType == "couponExempt")
			$productObj->setCouponDiscountExempt($optionName, ($flag == "yes" ? true : false));
		else
			throw new Exception("Illegal controller type called on action changeOptionConroller.");
			
			
		
		$productObj->updateProduct();

		header("Location: ./" . WebUtil::FilterURL("ad_product_setup.php?view=optionsPricing&editProduct=" . $editProduct . "&viewOption=" . urlencode($optionName)));
		exit;
	}
	else if($action == "deleteRootQuantityBreak"){
	
		$productObj = new Product($dbCmd, $editProduct);

		$productObj->removeQuantityBreak(WebUtil::GetInput("quantity", FILTER_SANITIZE_INT));
		
		$productObj->updateProduct();

		header("Location: ./" . WebUtil::FilterURL("ad_product_setup.php?view=quantityPricing&editProduct=" . $editProduct));
		exit;
	}
	
	else if($action == "addVendor"){
	
		$productObj = new Product($dbCmd, $editProduct);
		
		$vendorID = preg_replace("/u/i", "", WebUtil::GetInput("vendorID", FILTER_SANITIZE_STRING_ONE_LINE));
		$vendorID = intval($vendorID);
		
		$userControlObj = new UserControl($dbCmd);

		if(!$userControlObj->LoadUserByID($vendorID) && !Constants::GetDevelopmentServer()){
			$newVendorError = "You are trying to add a vendor ID that does not exist within the system.";
			WebUtil::PrintAdminError($newVendorError);
		}
	
		$existingVendorsArr = $productObj->getVendorIDArr();
		
		if(in_array($vendorID, $existingVendorsArr)){
			$newVendorError = "You are trying to add a vendor ID that is already defined for this product.";
			WebUtil::PrintAdminError($newVendorError);
		}
		
		$productObj->addNewVendor($vendorID);

		$productObj->updateProduct();


		header("Location: ./" . WebUtil::FilterURL("ad_product_setup.php?view=vendors&editProduct=" . $editProduct));
		exit;
	}
	else if($action == "updateRootPrices"){
	
		$productObj = new Product($dbCmd, $editProduct);

		$productObj->setBasePriceCustomer(WebUtil::GetInput("customerBasePrice", FILTER_SANITIZE_FLOAT));
		$productObj->setInitialSubtotalCustomer(WebUtil::GetInput("customerInitialSubtotal", FILTER_SANITIZE_FLOAT));
		
		for($i=1; $i<=6; $i++){
		
			if($productObj->getVendorID($i) == null)
				continue;

			$productObj->setVendorBasePrice($i, WebUtil::GetInput("vendor" . $i . "BasePrice", FILTER_SANITIZE_FLOAT));
			$productObj->setVendorInitialSubtotal($i, WebUtil::GetInput("vendor" . $i . "InitSubtotal", FILTER_SANITIZE_FLOAT));
		}

		$productObj->updateProduct();

		header("Location: ./" . WebUtil::FilterURL("ad_product_setup.php?view=quantityPricing&editProduct=" . $editProduct));
		exit;
	}
	else if($action == "addRootQuantityBreak"){
	
		$productObj = new Product($dbCmd, $editProduct);
		
		$quantityAmount = WebUtil::GetInput("quantityAmount", FILTER_SANITIZE_INT);
		
		if($productObj->checkIfQuantityBreakExists($quantityAmount)){
			$newVendorError = "You are trying to add a new Quantity Price break but that amount has already been added for this product. Maybe you should edit the existing one?";
			WebUtil::PrintAdminError($newVendorError);
		}
		
		$quanBreakObj = new QuantityPriceBreak($quantityAmount);

		$productObj->addNewQuantityBreak($quanBreakObj);

		$productObj->updateProduct();

		header("Location: ./" . WebUtil::FilterURL("ad_product_setup.php?view=quantityPricing&editProduct=" . $editProduct));
		exit;
	}
	else if($action == "UpdateChoiceQuantityBreak"){
	
		$productObj = new Product($dbCmd, $editProduct);
		
		$quantityBreak = WebUtil::GetInput("quantityBreak", FILTER_SANITIZE_INT);
		$optionName = WebUtil::GetInput("optionName", FILTER_SANITIZE_STRING_ONE_LINE);
		$choiceName = WebUtil::GetInput("choiceName", FILTER_SANITIZE_STRING_ONE_LINE);
		
		$vendorSubArr = array(WebUtil::GetInput("vendor1SubtotalChange", FILTER_SANITIZE_FLOAT), WebUtil::GetInput("vendor2SubtotalChange", FILTER_SANITIZE_FLOAT), WebUtil::GetInput("vendor3SubtotalChange", FILTER_SANITIZE_FLOAT), WebUtil::GetInput("vendor4SubtotalChange", FILTER_SANITIZE_FLOAT), WebUtil::GetInput("vendor5SubtotalChange", FILTER_SANITIZE_FLOAT), WebUtil::GetInput("vendor6SubtotalChange", FILTER_SANITIZE_FLOAT));
		$vendorBaseArr = array(WebUtil::GetInput("vendor1BasePriceChange", FILTER_SANITIZE_FLOAT), WebUtil::GetInput("vendor2BasePriceChange", FILTER_SANITIZE_FLOAT), WebUtil::GetInput("vendor3BasePriceChange", FILTER_SANITIZE_FLOAT), WebUtil::GetInput("vendor4BasePriceChange", FILTER_SANITIZE_FLOAT), WebUtil::GetInput("vendor5BasePriceChange", FILTER_SANITIZE_FLOAT), WebUtil::GetInput("vendor6BasePriceChange", FILTER_SANITIZE_FLOAT));


		$priceAdjustObj = new PriceAdjustments(WebUtil::GetInput("customerSubtotalChange", FILTER_SANITIZE_FLOAT), WebUtil::GetInput("customerBasePriceChange", FILTER_SANITIZE_FLOAT), $vendorSubArr, $vendorBaseArr);

		$productObj->updateOptionChoiceQuantityBreakPrices($optionName, $choiceName, $quantityBreak, $priceAdjustObj);

		$productObj->updateProduct();

		header("Location: ./" . WebUtil::FilterURL("ad_product_setup.php?view=optionsPricing&editProduct=" . $editProduct . "&viewOption=" . urlencode($optionName) . "&viewChoice=" . urlencode($choiceName)));
		exit;
	}
	
	
	else if($action == "addChoiceQuantityBreak"){
	
		$productObj = new Product($dbCmd, $editProduct);
		
		$optionName = WebUtil::GetInput("optionName", FILTER_SANITIZE_STRING_ONE_LINE);
		$choiceName = WebUtil::GetInput("choiceName", FILTER_SANITIZE_STRING_ONE_LINE);
		$quantityAmount = WebUtil::GetInput("quantityAmount", FILTER_SANITIZE_INT);
		
		if($productObj->checkIfQuantityBreakExistsOnOptionChoice($optionName, $choiceName, $quantityAmount)){
			$newVendorError = "You are trying to add a new Quantity Price break but that amount has already been added for this Option/Choice.  Maybe you should edit the existing one?";
			WebUtil::PrintAdminError($newVendorError);
		}
		
		$quanBreakObj = new QuantityPriceBreak($quantityAmount);

		$productObj->addNewQuantityBreakOnOptionChoice($optionName, $choiceName, $quanBreakObj);

		$productObj->updateProduct();

		header("Location: ./" . WebUtil::FilterURL("ad_product_setup.php?view=optionsPricing&editProduct=" . $editProduct . "&viewOption=" . urlencode($optionName) . "&viewChoice=" . urlencode($choiceName)));
		exit;
	}
	else if($action == "deleteChoiceQuantityBreak"){
	
		$productObj = new Product($dbCmd, $editProduct);
		
		$optionName = WebUtil::GetInput("optionName", FILTER_SANITIZE_STRING_ONE_LINE);
		$choiceName = WebUtil::GetInput("choiceName", FILTER_SANITIZE_STRING_ONE_LINE);
		$quantityAmount = WebUtil::GetInput("quantityAmount", FILTER_SANITIZE_INT);
		
		$productObj->removeQuantityBreakFromChoice($optionName, $choiceName, $quantityAmount);

		$productObj->updateProduct();

		header("Location: ./" . WebUtil::FilterURL("ad_product_setup.php?view=optionsPricing&editProduct=" . $editProduct . "&viewOption=" . urlencode($optionName) . "&viewChoice=" . urlencode($choiceName)));
		exit;
	}
	
	else if($action == "updateDefaultSchedule"){
	
		$productObj = new Product($dbCmd, $editProduct);
		
		$productObj->setDefaultProductionDays(WebUtil::GetInput("defaultProductionDays", FILTER_SANITIZE_INT));
		$productObj->setDefaultCutOffHour(WebUtil::GetInput("defaultCutoffHour", FILTER_SANITIZE_INT));

		$productObj->updateProduct();

		header("Location: ./" . WebUtil::FilterURL("ad_product_setup.php?view=schedule&editProduct=" . $editProduct));
		exit;
	}
	
	else if($action == "overrideProductionDays"){
	
		$productObj = new Product($dbCmd, $editProduct);
		
		$productObj->setProductionDaysOverride(WebUtil::GetInput("shippingMethod", FILTER_SANITIZE_INT), WebUtil::GetInput("overrideProductionDays", FILTER_SANITIZE_INT));

		header("Location: ./" . WebUtil::FilterURL("ad_product_setup.php?view=schedule&editProduct=" . $editProduct));
		exit;
	}
	else if($action == "overrideCutOffHour"){
	
		$productObj = new Product($dbCmd, $editProduct);
	
		$productObj->setProductionHourOverride(WebUtil::GetInput("shippingMethod", FILTER_SANITIZE_INT), WebUtil::GetInput("overrideCutoffHour", FILTER_SANITIZE_INT));

		header("Location: ./" . WebUtil::FilterURL("ad_product_setup.php?view=schedule&editProduct=" . $editProduct));
		exit;
	}
	else if($action == "deleteCutOffHourOverride"){
	
		$productObj = new Product($dbCmd, $editProduct);
	
		$productObj->setProductionHourOverride(WebUtil::GetInput("shippingID", FILTER_SANITIZE_INT), null);

		header("Location: ./" . WebUtil::FilterURL("ad_product_setup.php?view=schedule&editProduct=" . $editProduct));
		exit;
	}
	else if($action == "deleteProductionDaysOverride"){
	
		$productObj = new Product($dbCmd, $editProduct);
	
		$productObj->setProductionDaysOverride(WebUtil::GetInput("shippingID", FILTER_SANITIZE_INT), null);

		header("Location: ./" . WebUtil::FilterURL("ad_product_setup.php?view=schedule&editProduct=" . $editProduct));
		exit;
	}
	
	else if($action == "updateRootQuantityBreak"){
	
		$productObj = new Product($dbCmd, $editProduct);
		
		$quantityBreak = WebUtil::GetInput("quantityBreak", FILTER_SANITIZE_INT);
		$customerSubtotalChange = WebUtil::GetInput("customerSubtotalChange", FILTER_SANITIZE_FLOAT);
		$customerBasePriceChange = WebUtil::GetInput("customerBasePriceChange", FILTER_SANITIZE_FLOAT);
		
		$vendorSubArr = array(WebUtil::GetInput("vendor1SubtotalChange", FILTER_SANITIZE_FLOAT), WebUtil::GetInput("vendor2SubtotalChange", FILTER_SANITIZE_FLOAT), WebUtil::GetInput("vendor3SubtotalChange", FILTER_SANITIZE_FLOAT), WebUtil::GetInput("vendor4SubtotalChange", FILTER_SANITIZE_FLOAT), WebUtil::GetInput("vendor5SubtotalChange", FILTER_SANITIZE_FLOAT), WebUtil::GetInput("vendor6SubtotalChange", FILTER_SANITIZE_FLOAT));
		$vendorBaseArr = array(WebUtil::GetInput("vendor1BasePriceChange", FILTER_SANITIZE_FLOAT), WebUtil::GetInput("vendor2BasePriceChange", FILTER_SANITIZE_FLOAT), WebUtil::GetInput("vendor3BasePriceChange", FILTER_SANITIZE_FLOAT), WebUtil::GetInput("vendor4BasePriceChange", FILTER_SANITIZE_FLOAT), WebUtil::GetInput("vendor5BasePriceChange", FILTER_SANITIZE_FLOAT), WebUtil::GetInput("vendor6BasePriceChange", FILTER_SANITIZE_FLOAT));
		
		$priceAdjustObj = new PriceAdjustments($customerSubtotalChange, $customerBasePriceChange, $vendorSubArr, $vendorBaseArr);

		$productObj->updateQuantityBreakPrices($quantityBreak, $priceAdjustObj);

		$productObj->updateProduct();

		header("Location: ./" . WebUtil::FilterURL("ad_product_setup.php?view=quantityPricing&editProduct=" . $editProduct));
		exit;
	}
	
	else if($action == "updateChoice"){
	
		$productObj = new Product($dbCmd, $editProduct);
		
		$optionName = WebUtil::GetInput("optionName", FILTER_SANITIZE_STRING_ONE_LINE);
		$choiceName = WebUtil::GetInput("choiceName", FILTER_SANITIZE_STRING_ONE_LINE);
		$customerSubtotalChange = WebUtil::GetInput("customerSubtotalChange", FILTER_SANITIZE_FLOAT);
		$customerBasePriceChange = WebUtil::GetInput("customerBasePriceChange", FILTER_SANITIZE_FLOAT);
		$choiceBaseWeightChange = WebUtil::GetInput("choiceBaseWeightChange", FILTER_SANITIZE_FLOAT);
		$choiceProjectWeightChange = WebUtil::GetInput("choiceProjectWeightChange", FILTER_SANITIZE_FLOAT);
		$productionAlert = WebUtil::GetInput("extraProductionAlert", FILTER_SANITIZE_STRING_ONE_LINE);
		$choiceDescription = WebUtil::GetInput("choiceDescription", FILTER_UNSAFE_RAW);
		$choiceDescriptionHTMLformat = WebUtil::GetInput("choiceDescriptionHTMLformat", FILTER_SANITIZE_STRING_ONE_LINE);
		$choiceNameAlias = WebUtil::GetInput("choiceNameAlias", FILTER_SANITIZE_STRING_ONE_LINE);
		
		$originalChoiceNameAlias = $productObj->getChoiceDetailObj($optionName, $choiceName)->ChoiceNameAlias;
		
		$vendorSubArr = array(WebUtil::GetInput("vendor1SubtotalChange", FILTER_SANITIZE_FLOAT), WebUtil::GetInput("vendor2SubtotalChange", FILTER_SANITIZE_FLOAT), WebUtil::GetInput("vendor3SubtotalChange", FILTER_SANITIZE_FLOAT), WebUtil::GetInput("vendor4SubtotalChange", FILTER_SANITIZE_FLOAT), WebUtil::GetInput("vendor5SubtotalChange", FILTER_SANITIZE_FLOAT), WebUtil::GetInput("vendor6SubtotalChange", FILTER_SANITIZE_FLOAT));
		$vendorBaseArr = array(WebUtil::GetInput("vendor1BasePriceChange", FILTER_SANITIZE_FLOAT), WebUtil::GetInput("vendor2BasePriceChange", FILTER_SANITIZE_FLOAT), WebUtil::GetInput("vendor3BasePriceChange", FILTER_SANITIZE_FLOAT), WebUtil::GetInput("vendor4BasePriceChange", FILTER_SANITIZE_FLOAT), WebUtil::GetInput("vendor5BasePriceChange", FILTER_SANITIZE_FLOAT), WebUtil::GetInput("vendor6BasePriceChange", FILTER_SANITIZE_FLOAT));
		
		$priceAdjustObj = new PriceAdjustments($customerSubtotalChange, $customerBasePriceChange, $vendorSubArr, $vendorBaseArr);
	
		$productObj->updateOptionChoicePrices($optionName, $choiceName, $priceAdjustObj);
		
		$productObj->updateOptionChoiceDescription($optionName, $choiceName, $choiceDescription, ($choiceDescriptionHTMLformat == "yes" ? true : false));
		
		$productObj->updateOptionChoiceProductionAlert($optionName, $choiceName, $productionAlert);
		
		$productObj->updateOptionChoiceNameAlias($optionName, $choiceName, $choiceNameAlias);
		
		$productObj->setOptionChoiceWeightValues($optionName, $choiceName, $choiceBaseWeightChange, $choiceProjectWeightChange);
		
		
		// Clear out any existing Search/Replace terms...we are going to add them back in.
		// Just loop through 50 choices looking for non-empty Search fields (they would be cleared out if the user wanted to delete them)
		$productObj->clearOptionChoiceArtSearchReplaces($optionName, $choiceName);
		
		for($i=0; $i<50; $i++){
			$searchTerm = WebUtil::GetInput("searchFor" . $i, FILTER_UNSAFE_RAW);
			$replaceTerm = WebUtil::GetInput("replaceWith" . $i, FILTER_UNSAFE_RAW);
		
			if(!empty($searchTerm))
				$productObj->addOptionChoiceArtSearchReplace($optionName, $choiceName, $searchTerm, $replaceTerm);
		}
		
		
		// Don't try to update the Number of Artwork sides if we don't get a value.  There won't be a value if this Option is not an Artwork Side controller.  It would have left these input fields out on the HTML form.
		if(WebUtil::GetInput("numberOrArtworkSides", FILTER_SANITIZE_INT) != 0)
			$productObj->setOptionChoiceArtworkSideCount($optionName, $choiceName, WebUtil::GetInput("numberOrArtworkSides", FILTER_SANITIZE_INT));
		
		if(WebUtil::GetInput("variableImagesChoice", FILTER_SANITIZE_STRING_ONE_LINE) != null)
			$productObj->setOptionChoiceVariableImages($optionName, $choiceName, (WebUtil::GetInput("variableImagesChoice", FILTER_SANITIZE_STRING_ONE_LINE) == "yes" ? true : false));

		if(WebUtil::GetInput("hideOptionAll", FILTER_SANITIZE_STRING_ONE_LINE) != null)
			$productObj->setOptionChoiceHideAll($optionName, $choiceName, (WebUtil::GetInput("hideOptionAll", FILTER_SANITIZE_STRING_ONE_LINE) == "yes" ? true : false));

		if(WebUtil::GetInput("hideOptionInLists", FILTER_SANITIZE_STRING_ONE_LINE) != null)
			$productObj->setOptionChoiceHideInLists($optionName, $choiceName, (WebUtil::GetInput("hideOptionInLists", FILTER_SANITIZE_STRING_ONE_LINE) == "yes" ? true : false));

			

		$productObj->updateProduct();
		
		
		// Refresh all of the Option Aliases on the Open Orders if they do not match.
		if($originalChoiceNameAlias != $choiceNameAlias)
			ProjectOrdered::refreshOptionAliasesOnOpenOrders($editProduct);

		

		header("Location: ./" . WebUtil::FilterURL("ad_product_setup.php?view=optionsPricing&editProduct=" . $editProduct . "&viewOption=" . urlencode($optionName) . "&viewChoice=" . urlencode($choiceName)));
		exit;
	
	}
	else if($action == "saveMainProduct"){
		
		$productObj = new Product($dbCmd, $editProduct);
		
		$productObj->setProductTitle(WebUtil::GetInput("productTitle", FILTER_SANITIZE_STRING_ONE_LINE));
		$productObj->setProductTitleExtension(WebUtil::GetInput("productTitleExt", FILTER_SANITIZE_STRING_ONE_LINE));
		$productObj->setProductStatus(WebUtil::GetInput("productStatus", FILTER_SANITIZE_STRING_ONE_LINE));
		
		try{
			$productObj->setParentProductID(WebUtil::GetInput("parentProductID", FILTER_SANITIZE_INT));
		}
		catch(Exception $e){
			WebUtil::PrintAdminError($e->getMessage());
		}
		$productObj->setUseTemplatesFromProductID(WebUtil::GetInput("shareTemplatesFromProduct", FILTER_SANITIZE_INT));
		$productObj->setArtworkCanvasWidth(WebUtil::GetInput("artCanvasWidth", FILTER_SANITIZE_FLOAT));
		$productObj->setArtworkCanvasHeight(WebUtil::GetInput("artCanvasHeight", FILTER_SANITIZE_FLOAT));
		$productObj->setProductArtworkDPI(WebUtil::GetInput("artworkDPI", FILTER_SANITIZE_INT));
		$productObj->setArtworkBleedPicas(WebUtil::GetInput("artworkBleedPicas", FILTER_SANITIZE_FLOAT));
		$productObj->setArtworkImageUploadEmbedMsg(WebUtil::GetInput("artworkEmbedUploadMessage", FILTER_SANITIZE_STRING_ONE_LINE));
		$productObj->setArtworkCustomUploadTemplateURL(WebUtil::GetInput("artworkCustomUploadTemplateURL", FILTER_SANITIZE_STRING_ONE_LINE));
		$productObj->setProductImportance(WebUtil::GetInput("productImportance", FILTER_SANITIZE_INT));
		$productObj->setArtworkIsEditable(WebUtil::GetInput("artworkIsEditable", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES) == "N" ? false : true);
		
		$numberArtworkSides = WebUtil::GetInput("numberOrArtworkSides", FILTER_SANITIZE_INT);
		$productObj->setArtworkSidesCount($numberArtworkSides);
	
		// Based upon the number of Artwork Sides that this product has... look for Corresponding Side Descriptions.
		for($i=1; $i<=$numberArtworkSides; $i++)
			$productObj->setArtworkSideDescription($i, WebUtil::GetInput("sideDescription_" . $i, FILTER_SANITIZE_STRING_ONE_LINE));
		
		
		$productObj->setTemplatePreviewScale(WebUtil::GetInput("templatePreviewScale", FILTER_SANITIZE_FLOAT));
		
		
		// Show an error if the user is trying to switch the Production Piggy Back, when there are others pointing here.
		$productionPiggyBackID = WebUtil::GetInput("productionPiggyBack", FILTER_SANITIZE_INT);
		
		
		$productionPiggyBackingHereList = Product::getProductIDsPiggyBackingToThisProduct($dbCmd, $editProduct);
		
		if(!empty($productionPiggyBackID) && sizeof($productionPiggyBackingHereList) > 0){
			
			$productLinks = "";
			foreach($productionPiggyBackingHereList as $thisProductLink){
				$domainKeyOfPiggyBack = $domainObj->getDomainKeyFromID(Product::getDomainIDfromProductID($dbCmd, $thisProductLink));
				$productLinks .=   WebUtil::htmlOutput($domainKeyOfPiggyBack) . "&gt; " . WebUtil::htmlOutput(Product::getFullProductName($dbCmd, $thisProductLink)) . "<br>";
			}
			WebUtil::PrintAdminError("You are trying to change the Production Piggyback on this product but there are other products currently linked to this one.  You can't break the links so easily.  You will have to remove the Production Piggybacks from the other products if you wish to change the Production Piggyback on this product.<br><br><a href='javascript:history.back();'>&lt; Go back</a><br><br><br><br><u>Current Production Piggyback Links</u><br><br>$productLinks", TRUE);
		}
		
		
		$productObj->setProductionPiggybackID($productionPiggyBackID);
		
		
	
		// Multi-select HTML list menu creates an array.
		$compatibleProducts = WebUtil::GetInputArr("compatibleProducts", FILTER_SANITIZE_INT);
		$multipleTemplatePreviews = WebUtil::GetInputArr("multipleTemplatePreviews", FILTER_SANITIZE_INT);	
			
		$mutiplesTemplatesFlag = WebUtil::GetInput("multipleTemplates", FILTER_SANITIZE_STRING_ONE_LINE);

		$productObj->setCompatibleProductIDsArr($compatibleProducts);
		
		// Delete any Product Descriptions associated with switching between template previews.
		// We may be inserting new ones.
		$dbCmd->Query("DELETE FROM productmessages WHERE SourceProductID=" . $editProduct . " AND MultiTemplatePreviewSwitchDesc IS NOT NULL");
		
		if($mutiplesTemplatesFlag == "Y" && !empty($multipleTemplatePreviews)){
			$productObj->setMultipleTemplatePreviewsArr($multipleTemplatePreviews);
			
			$productObj->setDefaultPreviewID(WebUtil::GetInput("defaultTemplatePreview", FILTER_SANITIZE_INT));
			
			// Don't save the Product Preview Switching Descriptions to the Database unless a Description is provided for ALL of the Multi-Product ID's
			$allProductIDsWithDesc = true;
			foreach($multipleTemplatePreviews as $thisMultiTemplateID){

				$productDescription = WebUtil::GetInput("productPreviewSwitchDesc_" . $thisMultiTemplateID, FILTER_SANITIZE_STRING_ONE_LINE);
				
				if(empty($productDescription))
					$allProductIDsWithDesc = false;
			}
			
			// This product needs a description for itself
			$productDescription = WebUtil::GetInput("productPreviewSwitchDesc_" . $editProduct, FILTER_SANITIZE_STRING_ONE_LINE);
			if(empty($productDescription))
				$allProductIDsWithDesc = false;

			if($allProductIDsWithDesc){
				foreach($multipleTemplatePreviews as $thisMultiTemplateID){

					$productDescription = WebUtil::GetInput("productPreviewSwitchDesc_" . $thisMultiTemplateID, FILTER_SANITIZE_STRING_ONE_LINE);

					$dbCmd->InsertQuery("productmessages", array("SourceProductID"=>$editProduct, "TargetProductID"=>$thisMultiTemplateID, "MultiTemplatePreviewSwitchDesc"=>$productDescription));
				}
				
				// Insert the description for the product itself
				$productDescription = WebUtil::GetInput("productPreviewSwitchDesc_" . $editProduct, FILTER_SANITIZE_STRING_ONE_LINE);
				$dbCmd->InsertQuery("productmessages", array("SourceProductID"=>$editProduct, "TargetProductID"=>$editProduct, "MultiTemplatePreviewSwitchDesc"=>$productDescription));
			}


		}
		else{
			$productObj->setMultipleTemplatePreviewsArr(array());
			$productObj->setDefaultPreviewID(null);
		}
		
		
		
		
		
		// Multi-select HTML list menu creates an array.
		$productSwitching = WebUtil::GetInputArr("productSwitching", FILTER_SANITIZE_INT);

		
		// Clear out existin Product Switches, so that we can add new ones back in.
		$productObj->clearAllProductSwitches();
		
		foreach($productSwitching as $thisProductSwitchID){
		
			$productSwitchTitle = WebUtil::GetInput("productSwitchTitle_" . $thisProductSwitchID, FILTER_SANITIZE_STRING_ONE_LINE);
			$productSwitchLinkSubject = WebUtil::GetInput("productSwitchLinkSubject_" . $thisProductSwitchID, FILTER_SANITIZE_STRING_ONE_LINE);
			$productSwitchDescription = WebUtil::GetInput("productSwitchLinkDescription_" . $thisProductSwitchID, FILTER_UNSAFE_RAW);
			$productSwitchDescIsHTML = WebUtil::GetInput("productSwitchLinkDescIsHTML_" . $thisProductSwitchID, FILTER_SANITIZE_STRING_ONE_LINE) == "yes" ? true : false;
			
			
			// In case the user is defining new Product Swiches... we can't have a Null Title. So Just make the title the Product Name.
			if(empty($productSwitchTitle))
				$productSwitchTitle = "Switch to " . Product::getFullProductName($dbCmd, $thisProductSwitchID);
			if(empty($productSwitchLinkSubject) && empty($productSwitchDescription))
				$productSwitchLinkSubject = Product::getFullProductName($dbCmd, $thisProductSwitchID);
		
			$productObj->addProductSwitch($thisProductSwitchID, $productSwitchTitle, $productSwitchLinkSubject, $productSwitchDescription, $productSwitchDescIsHTML);
		}
		
		
		// Make sure that the artwork setup does not belong to another domain.
		$userIdArworkSetup = preg_replace("/u/i", "", WebUtil::GetInput("userIDartworkSetup", FILTER_SANITIZE_STRING_ONE_LINE));
		if(!empty($userIdArworkSetup)){
			$domainIDofUser = UserControl::getDomainIDofUser($userIdArworkSetup);
			if($domainIDofUser != Domain::oneDomain()){
				WebUtil::PrintAdminError("The UserID for artwork setup must exist within the domain of this product.");
			}
		}
		
		
		$productObj->setProductWeight(WebUtil::GetInput("productUnitWeight", FILTER_SANITIZE_FLOAT));
		$productObj->setUserIDofArtworkSetup(WebUtil::GetInput("userIDartworkSetup", FILTER_SANITIZE_STRING_ONE_LINE));
		$productObj->setReorderCardSavedNote(WebUtil::GetInput("reorderCardNote", FILTER_SANITIZE_STRING_ONE_LINE));
		$productObj->setProjectInitSavedNote(WebUtil::GetInput("projectInitSavedNote", FILTER_SANITIZE_STRING_ONE_LINE));
		$productObj->setThumbReorderScale(WebUtil::GetInput("thumbOnReorderScale", FILTER_SANITIZE_INT));
		$productObj->setThumbReorderPicasX(WebUtil::GetInput("thumbReorderX", FILTER_SANITIZE_INT));
		$productObj->setThumbReorderPicasY(WebUtil::GetInput("thumbReorderY", FILTER_SANITIZE_INT));
		
		$productObj->setTemplatePreviewSidesDisplay(WebUtil::GetInput("templatePreviewSides", FILTER_SANITIZE_STRING_ONE_LINE));
		
		$productObj->setTemplatePreviewSweetSpot((WebUtil::GetInput("templatePreviewSweetSpot", FILTER_SANITIZE_STRING_ONE_LINE) == "Y" ? true : false));
		
		$productObj->setVariableDataFlag((WebUtil::GetInput("variableData", FILTER_SANITIZE_STRING_ONE_LINE) == "Y" ? true : false));
		$productObj->setMailingServiceFlag((WebUtil::GetInput("mailingServices", FILTER_SANITIZE_STRING_ONE_LINE) == "Y" ? true : false));
		
		$productObj->setThumbWidth(WebUtil::GetInput("thumbOverlayWidth", FILTER_SANITIZE_INT));
		$productObj->setThumbHeight(WebUtil::GetInput("thumbOverlayHeight", FILTER_SANITIZE_INT));
		$productObj->setThumbOverlayX(WebUtil::GetInput("thumbOverlayX", FILTER_SANITIZE_INT));
		$productObj->setThumbOverlayY(WebUtil::GetInput("thumbOverlayY", FILTER_SANITIZE_INT));


		$productObj->setTempPrevBackLandscapeOverlayX(WebUtil::GetInput("tempPreviewBackLandscapeOverlayX", FILTER_SANITIZE_STRING_ONE_LINE));
		$productObj->setTempPrevBackLandscapeOverlayY(WebUtil::GetInput("tempPreviewBackLandscapeOverlayY", FILTER_SANITIZE_STRING_ONE_LINE));
		$productObj->setTempPrevBackPortraitOverlayX(WebUtil::GetInput("tempPreviewBackPortraitOverlayX", FILTER_SANITIZE_STRING_ONE_LINE));
		$productObj->setTempPrevBackPortraitOverlayY(WebUtil::GetInput("tempPreviewBackPortraitOverlayY", FILTER_SANITIZE_STRING_ONE_LINE));
		
		
		$productObj->setMaxBoxSize(WebUtil::GetInput("maxBoxSize", FILTER_SANITIZE_INT));
		
		if(WebUtil::GetInput("artworkSweetSpot", FILTER_SANITIZE_STRING_ONE_LINE) == "Y"){
			$productObj->setArtworkSweetSpotWidth(WebUtil::GetInput("sweetSpotWidth", FILTER_SANITIZE_FLOAT));
			$productObj->setArtworkSweetSpotHeight(WebUtil::GetInput("sweetSpotHeight", FILTER_SANITIZE_FLOAT));
			$productObj->setArtworkSweetSpotX(WebUtil::GetInput("sweetSpotX", FILTER_SANITIZE_FLOAT));
			$productObj->setArtworkSweetSpotY(WebUtil::GetInput("sweetSpotY", FILTER_SANITIZE_FLOAT));
		}
		else{
			$productObj->setArtworkSweetSpotWidth(null);
			$productObj->setArtworkSweetSpotHeight(null);
			$productObj->setArtworkSweetSpotX(null);
			$productObj->setArtworkSweetSpotY(null);
		}
		
		
		// Collect a list of Promo Commands and pass them into the Product object as an array.
		$productObj->setPromotionalCommandsArr(array(WebUtil::GetInput("promoCommand1", FILTER_SANITIZE_STRING_ONE_LINE), WebUtil::GetInput("promoCommand2", FILTER_SANITIZE_STRING_ONE_LINE), WebUtil::GetInput("promoCommand3", FILTER_SANITIZE_STRING_ONE_LINE), WebUtil::GetInput("promoCommand4", FILTER_SANITIZE_STRING_ONE_LINE)));
		
		
		
		// Find out if any images have been uploaded.
		if(isset($_FILES["thumbnailBackgroundImage"]["size"]) && !empty($_FILES["thumbnailBackgroundImage"]["size"]))
			$thumbBackgroundBinData = fread(fopen($_FILES["thumbnailBackgroundImage"]["tmp_name"], "r"), filesize($_FILES["thumbnailBackgroundImage"]["tmp_name"]));
		else
			$thumbBackgroundBinData = null;
		
		if(isset($_FILES["thumbnailCopyIcon"]["size"]) && !empty($_FILES["thumbnailCopyIcon"]["size"]))
			$thumbCopyIconBinData = fread(fopen($_FILES["thumbnailCopyIcon"]["tmp_name"], "r"), filesize($_FILES["thumbnailCopyIcon"]["tmp_name"]));
		else
			$thumbCopyIconBinData = null;
				
		if(isset($_FILES["templatePreviewMaskPNG"]["size"]) && !empty($_FILES["templatePreviewMaskPNG"]["size"]))
			$templateMaskBinData = fread(fopen($_FILES["templatePreviewMaskPNG"]["tmp_name"], "r"), filesize($_FILES["templatePreviewMaskPNG"]["tmp_name"]));
		else
			$templateMaskBinData = null;

		
		if(isset($_FILES["tempPrevBackLandscapeFile"]["size"]) && !empty($_FILES["tempPrevBackLandscapeFile"]["size"]))
			$tempPrevBackLandscapeBinData = fread(fopen($_FILES["tempPrevBackLandscapeFile"]["tmp_name"], "r"), filesize($_FILES["tempPrevBackLandscapeFile"]["tmp_name"]));
		else
			$tempPrevBackLandscapeBinData = null;
		
		if(isset($_FILES["tempPrevBackPortraitFile"]["size"]) && !empty($_FILES["tempPrevBackPortraitFile"]["size"]))
			$tempPrevBackPortraitBinData = fread(fopen($_FILES["tempPrevBackPortraitFile"]["tmp_name"], "r"), filesize($_FILES["tempPrevBackPortraitFile"]["tmp_name"]));
		else
			$tempPrevBackPortraitBinData = null;
			
			
		// Try and insert the image binary data into the Product Object.  These methods will return an error message if there is an error... false otherwise.
		// We don't want to exit on an error or it could make the Admin User lose their other form data.  If there is an error, just save what we can on the Product and print an error screen.
		$imageErrorsHTML = "";
		
		if($thumbBackgroundBinData != null){
			$imageErrorsHTML .= WebUtil::htmlOutput($productObj->setThumbnailBackJPG($thumbBackgroundBinData));
			if(!empty($imageErrorsHTML))
				$imageErrorsHTML .= "<br><br>";
		}
		
		if($thumbCopyIconBinData != null){
			$imageErrorsHTML .= WebUtil::htmlOutput($productObj->setThumbnailCopyIconJPG($thumbCopyIconBinData));
			if(!empty($imageErrorsHTML))
				$imageErrorsHTML .= "<br><br>";
		}
		
		if($templateMaskBinData != null){
			$imageErrorsHTML .= WebUtil::htmlOutput($productObj->setTemplatePreviewMaskPNG($templateMaskBinData));
			if(!empty($imageErrorsHTML))
				$imageErrorsHTML .= "<br><br>";
		}
		
		if($tempPrevBackLandscapeBinData != null){
			$imageErrorsHTML .= WebUtil::htmlOutput($productObj->setTempPrevBackLandscapeJPG($tempPrevBackLandscapeBinData));
			if(!empty($imageErrorsHTML))
				$imageErrorsHTML .= "<br><br>";
		}
		
		if($tempPrevBackPortraitBinData != null){
			$imageErrorsHTML .= WebUtil::htmlOutput($productObj->setTempPrevBackPortraitJPG($tempPrevBackPortraitBinData));
			if(!empty($imageErrorsHTML))
				$imageErrorsHTML .= "<br><br>";
		}

		$productObj->updateProduct();
		
		
		if(!empty($imageErrorsHTML)){
			$imageErrorsHTML = "The Product information has been saved but one or more images could not be uploaded due to the following reason(s).<hr><br><br>" . $imageErrorsHTML;
			$imageErrorsHTML .= "<br><br><a href='ad_product_setup.php?view=productMain&editProduct=" . $editProduct . "' class='BlueRedLink'>Click Here</a> to go back to the product screen.<br><br>";
			WebUtil::PrintAdminError($imageErrorsHTML, TRUE);
		}

		header("Location: ./" . WebUtil::FilterURL("ad_product_setup.php?view=productMain&editProduct=" . $editProduct));
		exit;
	}
	else{
		throw new Exception("Undefined Action");
	}
}










$t = new Templatex(".");

$t->set_file("origPage", "ad_product_setup-template.html");

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());


// An error message that will be printed then a user tries to click on one of the product tabs before the main product setup tab has been saved (for a new product).
$newProductStatusError = "This is a New Product.  You must save changes to the main Product Setup before accessing this tab.<br><br>";
$newProductStatusError .= "<a href='ad_product_setup.php?view=productMain&editProduct=" . $editProduct . "' class='BlueRedLink'>Click Here</a> to go back to the Product Setup screen.<br><br>";



// If we are editing a product, then show other Products in the domain that we can edit.
if(!empty($editProduct)){
	
	// Build a list of Product IDs (excluding the current one)
	$allProductsExceptThisOneArr = $productListArr = Product::getActiveProductIDsArr($dbCmd, Domain::oneDomain());
	WebUtil::array_delete($allProductsExceptThisOneArr, $editProduct);
	

	$productListArr = Product::getAllProductIDsArr($dbCmd, Domain::oneDomain());
	
	$editAnotherProductDropDown = array();
	
	// In case we have a "new Product" status or one that has been "disabled".
	if(!Product::checkIfProductIDisActive($dbCmd, $editProduct))
		$editAnotherProductDropDown[$editProduct] = "Choose an Existing Product to Edit";
	
	foreach($productListArr as $thisProdID){
		if(!Product::checkIfProductIDisActive($dbCmd, $thisProdID))
			continue;
		
		$editAnotherProductDropDown[$thisProdID] = Product::getFullProductName($dbCmd, $thisProdID);
	}
	
	$t->set_var("SELECT_ANOTHER_PRDODUCT_LIST", Widgets::buildSelect($editAnotherProductDropDown, $editProduct));
	$t->allowVariableToContainBrackets("SELECT_ANOTHER_PRDODUCT_LIST");
	
	$t->set_var("VIEW_TYPE", $view);
}



// If we are not editing a product... then show them links to select a product.
if(empty($editProduct)){

	$t->discard_block("origPage", "VendorSetupBL");
	$t->discard_block("origPage", "ProductStatusBL");
	$t->discard_block("origPage", "MainProductSetupBL");
	$t->discard_block("origPage", "ScheduleBL");
	$t->discard_block("origPage", "OptionPricingBL");
	$t->discard_block("origPage", "QuantitiesAndPricingBL");
	$t->discard_block("origPage", "SelectAnotherProductBL");

	$inactiveProducts = WebUtil::GetSessionVar("showInactiveProducts", "no");
	
	if($inactiveProducts == "yes"){
		$t->set_var("INACTIVE_PRODUCTS_YES", "checked");
		$t->set_var("INACTIVE_PRODUCTS_NO", "");
		
		$productListArr = Product::getAllProductIDsArr($dbCmd, Domain::oneDomain());
	}
	else{
		$t->set_var("INACTIVE_PRODUCTS_YES", "");
		$t->set_var("INACTIVE_PRODUCTS_NO", "checked");
		
		$productListArr = Product::getActiveProductIDsArr($dbCmd, Domain::oneDomain());
	}
	
	
	$t->set_block("origPage","ProductLinkBL","ProductLinkBLout");
	
	
	foreach($productListArr as $thisProductID){
	
		$productObj = new Product($dbCmd, $thisProductID);
		
		$t->set_var("PRODUCT_ID", $productObj->getProductID());
		
		$t->set_var("PRODUCT_NAME", WebUtil::htmlOutput($productObj->getProductTitleWithExtention()));
		$t->set_var("ARTWORK_DESC1", $productObj->getArtworkCanvasWidth() . "&quot; x " . WebUtil::htmlOutput($productObj->getArtworkCanvasHeight()) . "&quot;, " . $productObj->getArtworkDPI() . " DPI");
		$t->set_var("ARTWORK_DESC2", $productObj->getArtworkBleedPicas() . " Bleed Picas, " . $productObj->getArtworkSidesCount() . " Sides");
		$t->set_var("WEIGHT", $productObj->getProductUnitWeight());

		if(Product::checkIfProductHasMailingServices($dbCmd, $thisProductID))
			$t->set_var("VARIABLE_DATA_TYPE", "Mailing Services");
		else if(Product::checkIfProductHasVariableData($dbCmd, $thisProductID))
			$t->set_var("VARIABLE_DATA_TYPE", "Variable Data");
		else
			$t->set_var("VARIABLE_DATA_TYPE", "Static");
			

		$productObjOfProductionID = new Product($dbCmd, $productObj->getProductionProductID());
			
		if($productObj->getProductionProductID() == $thisProductID)
			$t->set_var("PRODUCTION", "Handles Production Itself");
		else if(Domain::oneDomain() != $productObjOfProductionID->getDomainID())
			$t->set_var("PRODUCTION", WebUtil::htmlOutput(Domain::getAbreviatedNameForDomainID($productObjOfProductionID->getDomainID())) . ">> " . WebUtil::htmlOutput($productObjOfProductionID->getProductTitleWithExtention()));
		else
			$t->set_var("PRODUCTION", WebUtil::htmlOutput($productObjOfProductionID->getProductTitleWithExtention()));
			
		$t->allowVariableToContainBrackets("PRODUCTION");
			
		
		if($productObj->getProductStatus() == "G")
			$t->set_var("INACTIVE", "");
		else
			$t->set_var("INACTIVE", "(Inactive)");
		
	
		$t->parse("ProductLinkBLout","ProductLinkBL",true);
	}
	
	
	if(empty($productListArr))
		$t->set_var("ProductLinkBLout", "No Products Yet");
	

}
else if($view == "productMain"){

	$t->discard_block("origPage", "ProductChooseBL");
	$t->discard_block("origPage", "VendorSetupBL");
	$t->discard_block("origPage", "ScheduleBL");
	$t->discard_block("origPage", "ProductStatusBL");
	$t->discard_block("origPage", "OptionPricingBL");
	$t->discard_block("origPage", "QuantitiesAndPricingBL");
	
	$productObj = new Product($dbCmd, $editProduct);
	

	$t->set_var("PRODUCTID", $editProduct);
	
	$t->set_var("FULL_PRODUCT_NAME", WebUtil::htmlOutput($productObj->getProductTitleWithExtention()));

	$t->set_var("PRODUCT_TITLE", WebUtil::htmlOutput($productObj->getProductTitle()));
	$t->set_var("PRODUCT_TITLE_EXT", WebUtil::htmlOutput($productObj->getProductTitleExtension()));
	
	if($productObj->getProductStatus() == "G")
		$t->set_var(array("PRODUCT_ACTIVE_YES"=> "checked",  "PRODUCT_ACTIVE_NO"=> ""));
	else
		$t->set_var(array("PRODUCT_ACTIVE_YES"=> "",  "PRODUCT_ACTIVE_NO"=> "checked"));
	
		
	if($productObj->getArtworkIsEditable())
		$t->set_var(array("ARTWORK_IS_EDITABLE_YES"=> "checked",  "ARTWORK_IS_EDITABLE_NO"=> ""));
	else
		$t->set_var(array("ARTWORK_IS_EDITABLE_YES"=> "",  "ARTWORK_IS_EDITABLE_NO"=> "checked"));
	
		
		
	

	
	// ------ Make a Drop down menu for the Parent Product ID.
	$parentProductDropDownArr = array("0"=>"This Product is the Parent");
	foreach($allProductsExceptThisOneArr as $thisProdID){
	
		$possibleProductObj = new Product($dbCmd, $thisProdID);
		
		// We can't link to a Parent which already has a parent (daisy chain).
		if($possibleProductObj->getParentProductID())
			continue;
	
		$parentProductDropDownArr[$thisProdID] = $possibleProductObj->getProductTitleWithExtention();
	}
	
	$t->set_var("PARENT_PRODUCTID_DROPDOWN", Widgets::buildSelect($parentProductDropDownArr, $productObj->getParentProductID()));
	$t->allowVariableToContainBrackets("PARENT_PRODUCTID_DROPDOWN");

	

	// ------ Make a Drop down menu for the Template Sharing
	$templateSharingDropDownArr = array("0"=>"This Product Creates its Own Templates");
	foreach($allProductsExceptThisOneArr as $thisProdID){
	
		$possibleProductObj = new Product($dbCmd, $thisProdID);
		
		// We can't link to a Parent which already has a parent (daisy chain).
		if($possibleProductObj->getUseTemplatesFromProductID())
			continue;
	
		$templateSharingDropDownArr[$thisProdID] = $possibleProductObj->getProductTitleWithExtention();
	}
	
	$t->set_var("TEMPLATE_SHARING_DROPDOWN", Widgets::buildSelect($templateSharingDropDownArr, $productObj->getUseTemplatesFromProductID()));
	$t->allowVariableToContainBrackets("TEMPLATE_SHARING_DROPDOWN");


	
	// Now we want to add to our list of Products for other domains that we have permission to see.
	// For example.  If we are running 2 domains... each selling "Business Cards".  There is no reason for a production operator to control 2 separate queues.
	
	$userDomainIDsArr = $AuthObj->getUserDomainsIDs();
	
	$currentDomainID = Product::getDomainIDfromProductID($dbCmd, $editProduct);
	$currentDomainKey = $domainObj->getDomainKeyFromID($currentDomainID);
	
	$productionPiggybackDropDownArr = array("0"=>"This Product Defines its Own Production Routines");
	foreach($allProductsExceptThisOneArr as $thisProductID){
		
		$possibleProductObj = new Product($dbCmd, $thisProductID);
		
		// We can't link to a Parent which already has a parent (daisy chain).
		if($possibleProductObj->getProductionPiggybackID())
			continue;
		
		// If the user has permission to see Multiple Domains... then prefix the Domain Name in front of the Product Name.
		$productName = "";
		if(sizeof($userDomainIDsArr) > 1)
			$productName = Domain::getAbreviatedNameForDomainID(Domain::getDomainID($currentDomainKey)) . " >> ";
			
		$productName .= $possibleProductObj->getProductTitleWithExtention();
		
		$productionPiggybackDropDownArr[$thisProductID] = $productName;
	}
	
	// Now list all of the other Products under other domains for which the user has permission to see.
	foreach($userDomainIDsArr as $thisUserDomainID){

		// Skip the Domain that we just included
		if($thisUserDomainID == $currentDomainID)
			continue;
			
		$domainProductIDs = Product::getActiveProductIDsArr($dbCmd, $thisUserDomainID);
		
		foreach($domainProductIDs as $thisProductID){
					
			$possibleProductObj = new Product($dbCmd, $thisProductID);
				
			// We can't link to a Parent which already has a parent (daisy chain).
			if($possibleProductObj->getProductionPiggybackID())
				continue;
				
			$productionPiggybackDropDownArr[$thisProductID] = Domain::getAbreviatedNameForDomainID($thisUserDomainID) . " >> " . $possibleProductObj->getProductTitleWithExtention();
		}
	}
	
	$currentProductionPiggyBackID = $productObj->getProductionPiggybackID();
	if(!Product::checkIfProductIDisActive($dbCmd, $currentProductionPiggyBackID))
		$currentProductionPiggyBackID = 0;

	// It is Possible that an administrator could setup a Production Piggy Back for someone else (that doesn't have permission to our domain).
	// In that case they wouldn't see the Product Choice within the Drop (if it was not already selected).
	// If so, we want to add that Product to our list.  If the user was to choose another value (and save), then they would not be able to switch back.
	if(!empty($currentProductionPiggyBackID) && !in_array($currentProductionPiggyBackID, array_keys($productionPiggybackDropDownArr))){
		
		$domainIDOfPiggyBack = Product::getDomainIDfromProductID($dbCmd, $currentProductionPiggyBackID);
		
		$productionPiggyBackProductObj = new Product($dbCmd, $currentProductionPiggyBackID, false);
		$productionPiggybackDropDownArr[$currentProductionPiggyBackID] = Domain::getAbreviatedNameForDomainID($domainIDOfPiggyBack) . " >> " . $productionPiggyBackProductObj->getProductTitleWithExtention();
	}
		

	
	$t->set_var("PRODUCTION_PIGGYBACK_DROPDOWN", Widgets::buildSelect($productionPiggybackDropDownArr, $productObj->getProductionPiggybackID()));
	$t->allowVariableToContainBrackets("PRODUCTION_PIGGYBACK_DROPDOWN");
	

	
	

	// ----- Make a Multi-Select that will says what other Products this Product can be switched into.
	$productSwitchingListMenu = array();
	foreach($allProductsExceptThisOneArr as $thisProdID)
		$productSwitchingListMenu[$thisProdID] = Product::getFullProductName($dbCmd, $thisProdID);

	$productSwitchesArr = $productObj->getProductSwitchIDs();
	$t->set_var("PRODUCT_SWITCHING_LIST", Widgets::buildSelect($productSwitchingListMenu, $productSwitchesArr));
	$t->allowVariableToContainBrackets("PRODUCT_SWITCHING_LIST");
	
	
	$t->set_block("origPage","ProductSwitchBL","ProductSwitchBLout");
	
	$lastProductSwitchTitle = "";
	foreach($productSwitchesArr as $thisProdSwitchID){
		
		$t->set_var("PRODUCT_NAME_SWITCH", WebUtil::htmlOutput(Product::getFullProductName($dbCmd, $thisProdSwitchID)));
		$t->set_var("PRODUCT_SWITCH_LINK_SUBJECT", WebUtil::htmlOutput($productObj->getProductSwitchLinkSubject($thisProdSwitchID)));
		$t->set_var("PRODUCT_SWITCH_DESC", WebUtil::htmlOutput($productObj->getProductSwitchDescription($thisProdSwitchID)));
		
		if($productObj->getProductSwitchDescriptionIsHTML($thisProdSwitchID))
			$t->set_var(array("PROD_SWITCH_CHECKED_YES"=>"checked", "PROD_SWITCH_CHECKED_NO"=> ""));
		else
			$t->set_var(array("PROD_SWITCH_CHECKED_YES"=>"", "PROD_SWITCH_CHECKED_NO"=>"checked"));

		
		$t->set_var("PROD_SWITCH_PRODID", $thisProdSwitchID);
		
	
		// If the last Product Switch Title was the same as this one... then indicate to the user that they are grouped together.
		if($lastProductSwitchTitle == $productObj->getProductSwitchTitle($thisProdSwitchID)){
		
			// Make the title a hidden input so that the user can't edit it.
			
			$productSwitchTitleHidden = "<font class='ReallySmallBody'><font color='#990000'>Grouped with Product Above <font color='#996666'><em>(Product Switch Title matches)</em></font></font></font>";
			$productSwitchTitleHidden .= "<input type='hidden' name='productSwitchTitle_".$thisProdSwitchID."' value='".WebUtil::htmlOutput($productObj->getProductSwitchTitle($thisProdSwitchID))."'>";
			$t->set_var("PRODUCT_SWITCH_TITLE", $productSwitchTitleHidden);
			$t->allowVariableToContainBrackets("PRODUCT_SWITCH_TITLE");
		}
		else{
		
			$productSwitchTitleVisible = "<input type=\"text\" name=\"productSwitchTitle_".$thisProdSwitchID."\" value=\"".WebUtil::htmlOutput($productObj->getProductSwitchTitle($thisProdSwitchID))."\" class=\"adminInput\" maxlength=\"200\" style=\"width:350px; background-color:#F3F3F3\">";
			$t->set_var("PRODUCT_SWITCH_TITLE", $productSwitchTitleVisible);
			$t->allowVariableToContainBrackets("PRODUCT_SWITCH_TITLE");
		}
		
		
		$lastProductSwitchTitle = $productObj->getProductSwitchTitle($thisProdSwitchID);
	
		$t->parse("ProductSwitchBLout","ProductSwitchBL",true);
	}
	

	
	if(empty($productSwitchesArr))
		$t->discard_block("origPage", "EmptyProductSwitchingBL");
		

	

	
	
	
	
	
	
	$t->set_var("CANVAS_WIDTH", $productObj->getArtworkCanvasWidth());
	$t->set_var("CANVAS_HEIGHT", $productObj->getArtworkCanvasHeight());
	$t->set_var("ART_DPI", $productObj->getArtworkDPI());
	$t->set_var("ART_BLEED", $productObj->getArtworkBleedPicas());
	$t->set_var("ART_EMBED_UPLOAD_MESSAGE", WebUtil::htmlOutput($productObj->getArtworkImageUploadEmbedMsg()));
	$t->set_var("ART_CUSTOM_UPLOAD_TEMPLATE_URL", WebUtil::htmlOutput($productObj->getArtworkCustomUploadTemplateURL()));
	
	
	// Display the number of sides and a description for each side
	$totalArtworkSides = $productObj->getArtworkSidesCount();
	
	$t->set_var("NUM_ARTWORK_SIDES", $totalArtworkSides);
	
	$t->set_block("origPage","ArtworkSidesDescBL","ArtworkSidesDescBLout");
	
	for($i=1; $i<=$totalArtworkSides; $i++){
	
		$t->set_var("SIDE_DESC", $productObj->getArtworkSideDescription($i));
		$t->set_var("SIDE_NUM", $i);
		
	
		$t->parse("ArtworkSidesDescBLout","ArtworkSidesDescBL",true);
	}
	
	
	
	$t->set_var("TEMPLATE_PREVIEW_SCALE", $productObj->getTemplatePreviewScale());
	
	$t->set_var("PRODUCT_UNIT_WEIGHT", $productObj->getProductUnitWeight());
	
	if($productObj->getUserIDofArtworkSetup() != null){
		$t->set_var("USERID_ARTWORK_SETUP", "U" . $productObj->getUserIDofArtworkSetup());
		
		// Check to see if the User ID exists... Otherwise we should show an error message.
		$userControlObj = new UserControl($dbCmd);
		
		
		if(!$userControlObj->LoadUserByID($productObj->getUserIDofArtworkSetup()))
			$t->set_var("USER_ID_ARTWORK_SETUP_MSG", "<br><font color='#aa0000'>Warning: The User ID does not exist.</font>");
		else
			$t->set_var("USER_ID_ARTWORK_SETUP_MSG", "");
			
		$t->allowVariableToContainBrackets("USER_ID_ARTWORK_SETUP_MSG");
	}
	else{
		$t->set_var("USERID_ARTWORK_SETUP", "");
		$t->set_var("USER_ID_ARTWORK_SETUP_MSG", "");
	}
	
	
	$t->set_var("THUMB_WIDTH", $productObj->getThumbWidth());
	$t->set_var("THUMB_HEIGHT", $productObj->getThumbHeight());
	$t->set_var("THUMB_OVERLAY_X", $productObj->getThumbOverlayX());
	$t->set_var("THUMB_OVERLAY_Y", $productObj->getThumbOverlayY());
	$t->set_var("TEMP_PREVIEW_BACK_LANDSCAPE_OVERLAY_X", $productObj->getTempPrevBackLandscapeOverlayX());
	$t->set_var("TEMP_PREVIEW_BACK_LANDSCAPE_OVERLAY_Y", $productObj->getTempPrevBackLandscapeOverlayY());
	$t->set_var("TEMP_PREVIEW_BACK_PORTRAIT_OVERLAY_X", $productObj->getTempPrevBackPortraitOverlayX());
	$t->set_var("TEMP_PREVIEW_BACK_PORTRAIT_OVERLAY_Y", $productObj->getTempPrevBackPortraitOverlayY());
	
	
	// Find out if the user changed the Thumbnail Width/Height so that it doesn't have the same aspect ratio as the artwork (within 1% error).
	// For new Products, start off with the Aspect Ratio forced on.
	if($productObj->getThumbHeight() == 0 || $productObj->getArtworkCanvasHeight() == 0){
		$t->set_var("FORCE_THUMBNAIL_ASPECT_RATIO_CHECKED", "checked");
	}
	else{
		
		$ratioOfThumb = round($productObj->getThumbWidth() / $productObj->getThumbHeight(), 4);
		$ratioOfArtwork = round($productObj->getArtworkCanvasWidth() / $productObj->getArtworkCanvasHeight(), 4);
		
		$marginOfDifference = abs($ratioOfThumb - $ratioOfArtwork);

		if($marginOfDifference > 0.01)
			$t->set_var("FORCE_THUMBNAIL_ASPECT_RATIO_CHECKED", "");
		else
			$t->set_var("FORCE_THUMBNAIL_ASPECT_RATIO_CHECKED", "checked");
	}
	
	
	$t->set_var("THUMB_REORDER_SCALE", $productObj->getScaleOfThumbnailImageOnReorderCard());
	$t->set_var("THUMB_REORDER_X", $productObj->getXcoordOfThumbnailImageOnReorderCard());
	$t->set_var("THUMB_REORDER_Y", $productObj->getYcoordOfThumbnailImageOnReorderCard());
	
	$t->set_var("MAX_BOX_SIZE", $productObj->getMaxBoxSize());
	
	$t->set_var("REORDERCARD_NOTE", WebUtil::htmlOutput($productObj->getReorderCardSavedNote()));
	$t->set_var("PROJECT_INIT_SAVED_NOTE", WebUtil::htmlOutput($productObj->getProjectInitSavedNote()));
	
	
	// Set radio buttons depending on whether we should show multiple template preview images.
	if($productObj->getTemplatePreviewSidesDisplay() == "M")
		$t->set_var(array("TEMPLATE_PREVIEW_MULTI_SIDES_YES"=>"checked", "TEMPLATE_PREVIEW_MULTI_SIDES_NO"=>""));
	else if($productObj->getTemplatePreviewSidesDisplay() == "F")
		$t->set_var(array("TEMPLATE_PREVIEW_MULTI_SIDES_YES"=>"", "TEMPLATE_PREVIEW_MULTI_SIDES_NO"=>"checked"));
	else 
		throw new Exception("Error with Template Preview Sides Display character.");
	
		
		
	// Set radio buttons depending on whether the Artwork Sweet spot is extracted for the pupose of tempalte preview images.
	if($productObj->getTemplatePreviewSweetSpot())
		$t->set_var(array("TEMPLATE_PREVIEW_SWEET_SPOT_YES"=>"checked", "TEMPLATE_PREVIEW_SWEET_SPOT_NO"=>""));
	else
		$t->set_var(array("TEMPLATE_PREVIEW_SWEET_SPOT_YES"=>"", "TEMPLATE_PREVIEW_SWEET_SPOT_NO"=>"checked"));
	
	
	// Product Importance is a number between 0 and 100.
	$productImportanceValues = array();
	for($j=0; $j<=100; $j++)
		$productImportanceValues[$j] = $j;
	$t->set_var("PRODUCT_IMPORTANCE_OPTIONS", Widgets::buildSelect($productImportanceValues, $productObj->getProductImportance()));
	$t->allowVariableToContainBrackets("PRODUCT_IMPORTANCE_OPTIONS");
	
	
	if($productObj->checkIfArtworkHasSweetSpot())
		$t->set_var(array("SWEETSPOT_YES"=> "checked",  "SWEETSPOT_NO"=> ""));
	else
		$t->set_var(array("SWEETSPOT_YES"=> "",  "SWEETSPOT_NO"=> "checked"));	
	
	$t->set_var("SWEETSPOT_WIDTH", $productObj->getArtworkSweetSpotWidth());
	$t->set_var("SWEETSPOT_HEIGHT", $productObj->getArtworkSweetSpotHeight());
	
	$t->set_var("SWEETSPOT_X", $productObj->getArtworkSweetSpotX());
	$t->set_var("SWEETSPOT_Y", $productObj->getArtworkSweetSpotY());
	


	if($productObj->isVariableData())
		$t->set_var(array("VARIABLEDATA_YES"=> "checked",  "VARIABLEDATA_NO"=> ""));
	else
		$t->set_var(array("VARIABLEDATA_YES"=> "",  "VARIABLEDATA_NO"=> "checked"));
		
		
	if($productObj->hasMailingService())
		$t->set_var(array("MAILING_SERVICES_YES"=> "checked",  "MAILING_SERVICES_NO"=> ""));
	else
		$t->set_var(array("MAILING_SERVICES_YES"=> "",  "MAILING_SERVICES_NO"=> "checked"));
		
	
	
	$multipleTemplatePreviewsIDArr = $productObj->getMultipleTemplatePreviewsArr();
	
	if(!$productObj->checkIfProductSavedMultiplePreviewImages()){
		
		$t->set_var(array("MULTIPLE_TEMPLATES_YES"=> "",  "MULTIPLE_TEMPLATES_NO"=> "checked"));
		
		// If there are not multiple previews...then hide the block of
		$t->discard_block("origPage", "ProductSwitchingDescBL");
		
	}
	else{
		$t->set_var(array("MULTIPLE_TEMPLATES_YES"=> "checked",  "MULTIPLE_TEMPLATES_NO"=> ""));
		
		$t->set_block("origPage","ProductSwitchNamesBL","ProductSwitchNamesBLout");
		
		foreach($multipleTemplatePreviewsIDArr as $thisMultiPreviewID){
		
			$t->set_var("PRODUCT_PREVIEW_SWITCH_DESC", WebUtil::htmlOutput($productObj->getMultiTemplatePreviewSwitchDesc($thisMultiPreviewID)));
			$t->set_var("PRODUCT_SWITCH_NAME", WebUtil::htmlOutput(Product::getFullProductName($dbCmd, $thisMultiPreviewID)));
			$t->set_var("PRODUCT_SWITCH_ID", $thisMultiPreviewID);
		
			$t->parse("ProductSwitchNamesBLout","ProductSwitchNamesBL",true);
		}
	}
	
	
	

	// Get a list of all Products which point to this one for Template Sharing.
	$productWithSharingPointingHereArr = $productObj->getProductsPointingToThisOneForTemplateSharing();
	
	// Make a drop down menu so the user can choose which products to use for generating multiple template previews.
	$multipleTemplatePreviewsList = array();
	
	// Make a drop down menu to show which Multi-Preview ID is selected as the default.
	$multiTemplatePreviewsDefaultList = array();
	
	foreach($productWithSharingPointingHereArr as $thisProdID){
	
		// Don't include this Product's own ID in the Multi-Select list.  It is assumed that this product always generates template preview images for itself.
		if($thisProdID == $editProduct)
			continue;
	
		$sharedProductObj = new Product($dbCmd, $thisProdID, false);
	
		$multipleTemplatePreviewsList[$thisProdID] = $sharedProductObj->getProductTitleWithExtention();
		
		// The list should only include products IDs that this Product is generating multiple Preview Images for.
		if(in_array($thisProdID, $multipleTemplatePreviewsIDArr))
			$multiTemplatePreviewsDefaultList[$thisProdID] = $sharedProductObj->getProductTitleWithExtention();
	}
	
	$t->set_var("MULTIPLE_TEMPLATE_PREVIEWS_LIST", Widgets::buildSelect($multipleTemplatePreviewsList, $multipleTemplatePreviewsIDArr));
	$t->allowVariableToContainBrackets("MULTIPLE_TEMPLATE_PREVIEWS_LIST");
	
	$defaultMultiPreviewSelect = array($editProduct=>"This Product's Own Templates");
	
	foreach($multiTemplatePreviewsDefaultList as $multiProdID => $multiProdDesc)
		$defaultMultiPreviewSelect[$multiProdID] = $multiProdDesc;

	// Make a drop down showing the default product ID for viewing out of the multiple template selection.
	$t->set_var("DEFAULT_TEMPLATE_PREVIEW", Widgets::buildSelect($defaultMultiPreviewSelect, $productObj->getdefaultProductIDforTemplatePreview()));
	$t->allowVariableToContainBrackets("DEFAULT_TEMPLATE_PREVIEW");

	

	// Build the Multi-Select List for Compatible Product IDs
	// We only want the list to include Root Product's... and do not include this Product Itself
	$compatibleProductsList = array();
	
	$rootProductIDsArr = Product::getActiveMainProductIDsArr($dbCmd, Domain::oneDomain());
	foreach($rootProductIDsArr as $thisRootProductID){
		if($thisRootProductID != $editProduct)
			$compatibleProductsList[$thisRootProductID] = Product::getRootProductName($dbCmd, $thisRootProductID);
	}
	
	$t->set_var("COMPATIBLE_PRODUCTS_OPTIONS", Widgets::buildSelect($compatibleProductsList, $productObj->getCompatibleProductIDsArr()));
	$t->allowVariableToContainBrackets("COMPATIBLE_PRODUCTS_OPTIONS");
	
	// Show error messages if there are not Saved Projects available.
	if($productObj->getReorderCardSavedNote() != null && !$productObj->checkIfReorderCardOnSavedProjectsAvailable())
		$t->set_var("REORDER_CARD_SETUP_MSG", "<br><font color='#aa0000'>Warning: A matching Project can not be found.<br>Double check the UserID for Artwork Setup and the Saved Project Note.</font>");
	else if($productObj->getReorderCardSavedNote() != null && !$productObj->checkIfReorderCardOnSavedProjectsMatchesProductID())
		$t->set_var("REORDER_CARD_SETUP_MSG", "<br><font color='#aa0000'>Warning: The Product ID belonging to the Re-Order Card in the Saved Projects does not match the Product ID that you are currently configuring.</font>");
	else
		$t->set_var("REORDER_CARD_SETUP_MSG", "");
		
	if($productObj->getProjectInitSavedNote() != null && !$productObj->checkIfProjectInitOnSavedProjectsAvailable())
		$t->set_var("PROJECT_INIT_SETUP_MSG", "<br><font color='#aa0000'>Warning: A matching Project can not be found.<br>Double check the UserID for Artwork Setup and the Saved Project Note.</font>");
	else if($productObj->getProjectInitSavedNote() != null && !$productObj->checkIfProjectInitOnSavedProjectsMatchesProductID())
		$t->set_var("PROJECT_INIT_SETUP_MSG", "<br><font color='#aa0000'>Warning: The Product ID belonging to the Artwork Setup in the Saved Projects does not match the Product ID that you are currently configuring.</font>");
	else
		$t->set_var("PROJECT_INIT_SETUP_MSG", "");
		
	$t->allowVariableToContainBrackets("REORDER_CARD_SETUP_MSG");
	$t->allowVariableToContainBrackets("PROJECT_INIT_SETUP_MSG");
	
	
	// ----------  If there are images uploaded, show them links to preview them.

		
	if($productObj->checkIfThumbnailCopyIconSaved())
		$t->set_var("CURRENT_THUMB_COPY_ICON", "<a class='BlueRedLink' href='javascript:PreviewThumbCopyIcon($editProduct);'>Preview Image</a>&nbsp;&nbsp;&nbsp;<a href='javascript:DeleteThumbCopyIcon($editProduct);'><font color='#CC000'>X</font></a>");
	else
		$t->set_var("CURRENT_THUMB_COPY_ICON", "<font class='ReallySmallBody'>No Image Uploaded Yet</font>");


	if($productObj->checkIfThumbnailBackSaved())
		$t->set_var("CURRENT_THUMB_BACK_IMG", "<a class='BlueRedLink' href='javascript:PreviewThumbBackground($editProduct);'>Preview Image</a>&nbsp;&nbsp;&nbsp;<a href='javascript:DeleteThumbBackground($editProduct);'><font color='#CC000'>X</font></a>");
	else
		$t->set_var("CURRENT_THUMB_BACK_IMG", "<font class='ReallySmallBody'>No Image Uploaded Yet</font>");

	if($productObj->checkIfTempPrevBackLandscapeJPGSaved())
		$t->set_var("CURRENT_TEMP_BACK_LANDSCAPE_IMG", "<a class='BlueRedLink' href='javascript:PreviewTemplateBackgroundLandscape($editProduct);'>Preview Image</a>&nbsp;&nbsp;&nbsp;<a href='javascript:DeleteTemplateBackgroundLandscape($editProduct);'><font color='#CC000'>X</font></a>");
	else
		$t->set_var("CURRENT_TEMP_BACK_LANDSCAPE_IMG", "<font class='ReallySmallBody'>No Image Uploaded Yet</font>");

	if($productObj->checkIfTempPrevBackPortraitJPGSaved())
		$t->set_var("CURRENT_TEMP_BACK_PORTRAIT_IMG", "<a class='BlueRedLink' href='javascript:PreviewTemplateBackgroundPortrait($editProduct);'>Preview Image</a>&nbsp;&nbsp;&nbsp;<a href='javascript:DeleteTemplateBackgroundPortrait($editProduct);'><font color='#CC000'>X</font></a>");
	else
		$t->set_var("CURRENT_TEMP_BACK_PORTRAIT_IMG", "<font class='ReallySmallBody'>No Image Uploaded Yet</font>");


	if($productObj->checkIfTemplatePreviewMaskSaved())
		$t->set_var("CURRENT_PREVIEW_MASK_IMG", "<a class='BlueRedLink' href='javascript:PreviewTemplatePreviewMask($editProduct);'>Preview Image</a>&nbsp;&nbsp;&nbsp;<a href='javascript:DeleteTemplatePreviewMask($editProduct);'><font color='#CC000'>X</font></a>");
	else
		$t->set_var("CURRENT_PREVIEW_MASK_IMG", "<font class='ReallySmallBody'>No Image Uploaded Yet</font>");

		
	$t->allowVariableToContainBrackets("CURRENT_THUMB_COPY_ICON");
	$t->allowVariableToContainBrackets("CURRENT_THUMB_BACK_IMG");
	$t->allowVariableToContainBrackets("CURRENT_PREVIEW_MASK_IMG");
	$t->allowVariableToContainBrackets("CURRENT_TEMP_BACK_LANDSCAPE_IMG");
	$t->allowVariableToContainBrackets("CURRENT_TEMP_BACK_PORTRAIT_IMG");
	
	
	// We may want to hide some HTML Menus depending upon the intial selections
	$js_initializeMainProductForm = "";
	
	if(empty($multipleTemplatePreviewsIDArr))
		$js_initializeMainProductForm .= "showMultipleTemplatePreviewsHTML(false);\n";
	if(!$productObj->checkIfArtworkHasSweetSpot())
		$js_initializeMainProductForm .= "showArtworkSweetSpotValues(false);\n";
	
	
	
	// There are 4 commands on the HTML form... if the value hasn't been set within the Product Object... then the input should be blank.
	$promotionalArr = $productObj->getPromotionalCommandsArr();
	for($promoCounter = 0; $promoCounter <4; $promoCounter++){
	
		if(!isset($promotionalArr[$promoCounter]))
			$promotionalCommandValue = "";
		else
			$promotionalCommandValue = $promotionalArr[$promoCounter];
		
		$t->set_var(("PROMO_COMMAND_" . ($promoCounter+1)), $promotionalCommandValue);
	}

	
	

	$t->set_var("JAVASCRIPT_INITIALIZE_MAIN_PRODUCT_FORM", $js_initializeMainProductForm);

	
}
else if($view == "optionsPricing"){


	$t->discard_block("origPage", "ProductChooseBL");
	$t->discard_block("origPage", "VendorSetupBL");
	$t->discard_block("origPage", "ScheduleBL");
	$t->discard_block("origPage", "ProductStatusBL");
	$t->discard_block("origPage", "MainProductSetupBL");
	$t->discard_block("origPage", "QuantitiesAndPricingBL");
	
	$productObj = new Product($dbCmd, $editProduct);
	
	if($productObj->getProductStatus() == "N")
		WebUtil::PrintAdminError($newProductStatusError, TRUE);

	$t->set_var("PRODUCTID", $editProduct);

	$t->set_var("FULL_PRODUCT_NAME", WebUtil::htmlOutput($productObj->getProductTitleWithExtention()));
	
	$optionsArr = $productObj->getProductOptionsArr();

	if(empty($optionsArr)){	
		
		$t->discard_block("origPage", "DeleteOptionBL");
		$t->discard_block("origPage", "NoOptionsYetBL");
		
		_printMainTabs($t, $view, $editProduct);
		
		$t->pparse("OUT","origPage");
		exit;
	}



	$t->discard_block("origPage", "EmptyOptionBL");

	$viewOption = strtoupper(WebUtil::GetInput("viewOption", FILTER_SANITIZE_STRING_ONE_LINE));

	// Build a List of Tabs for Option Names.
	$TabsObj = new Navigation();
	foreach($optionsArr as $thisOptionObj)
		$TabsObj->AddTab(strtoupper($thisOptionObj->optionName), $thisOptionObj->optionName, './ad_product_setup.php?view=optionsPricing&editProduct=' . $editProduct . "&viewOption=" . urlencode($thisOptionObj->optionName));
	$t->set_var("OPTIONS_NAV_BAR", $TabsObj->GetTabsHTML($viewOption));
	$t->allowVariableToContainBrackets("OPTIONS_NAV_BAR");


	if(empty($viewOption)){

		$t->set_block("origPage","NoOptionSelectedBL","NoOptionSelectedBLout");
		$t->set_var("NoOptionSelectedBLout", "<br>Click on a Tab above to edit that Option.");
		
		$t->discard_block("origPage", "DeleteOptionBL");
		
		_printMainTabs($t, $view, $editProduct);
		
		$t->pparse("OUT","origPage");
		exit;
	}

	
	// If we are currently looking at a product Option then take away the input field to add more product Options.
	$t->discard_block("origPage", "AddNewProductOptionBL");
		

	$optionNotFound = true;
	foreach($optionsArr as $thisOptionObj){
		if(strtoupper($thisOptionObj->optionName) == strtoupper($viewOption)){
			$optionNotFound = false;
			break;
		}
	}
	
	if($optionNotFound)
		throw new Exception("Error.  The Opion Name does not exist within the Options &amp; Prices tab.");


	$allOptionNamesArr = $productObj->getProductOptionNamesArr();
	
	// If we only have 1 product Option, then there is no point in allowing hte user to sort left or right.
	if(sizeof($allOptionNamesArr) == 1){
		$t->discard_block("origPage", "ShiftOptionUpBL");
		$t->discard_block("origPage", "ShiftOptionDownBL");
	}
	else if(strtoupper($allOptionNamesArr[0]) == $viewOption){
		$t->discard_block("origPage", "ShiftOptionUpBL");
	}
	else if(strtoupper(end($allOptionNamesArr)) == $viewOption){
		$t->discard_block("origPage", "ShiftOptionDownBL");
	}

	
	// Find out if the user has permission to change the Option Name.
	if(!$AuthObj->CheckForPermission("CHANGE_PRODUCT_OPTION_NAMES"))
		$t->discard_block("origPage", "ChangeOptionNameBL");

		
	$t->set_var("OPTION_NAME", WebUtil::htmlOutput($thisOptionObj->optionName));
	$t->set_var("OPTION_NAME_JS", addslashes($thisOptionObj->optionName));
	$t->set_var("OPTION_DESCRIPTION", WebUtil::htmlOutput($thisOptionObj->optionDescription));
	$t->set_var("OPTION_NAME_ALIAS", WebUtil::htmlOutput($thisOptionObj->optionNameAlias));
	$t->set_var("OPTION_NAME_ENCODED", urlencode($viewOption));



	// Set the Radio Buttons for the Format type of the Option Description
	if($thisOptionObj->optionDescriptionIsHTMLformat)
		$t->set_var(array("OPTION_DESCRIPTION_HTML_NO"=>"", "OPTION_DESCRIPTION_HTML_YES"=>"checked"));
	else
		$t->set_var(array("OPTION_DESCRIPTION_HTML_NO"=>"checked", "OPTION_DESCRIPTION_HTML_YES"=>""));
	


	// Delete the Variable Image Conroller Option if this Project does not support variable images.
	if(!$productObj->isVariableData())
		$t->discard_block("origPage", "ControllerVariableImagesBL");


	// There can only be one Option that controlls the number of artwork sides.
	// If there is another option besides this one with an artwork Controller ... then remove the radio button from the HTML.
	foreach($optionsArr as $optCheckObj){
		if($optCheckObj->artworkSidesController && strtoupper($optCheckObj->optionName) != strtoupper($viewOption)){
			$t->discard_block("origPage", "ControllerArtworkSidesBL");
			break;
		}
	}
	

	
	
	// Set the Radio Buttons for all of the Option Controllers
	if($thisOptionObj->artworkSidesController)
		$t->set_var(array("ARTWORK_CONTROLLER_NO"=>"", "ARTWORK_CONTROLLER_YES"=>"checked"));
	else
		$t->set_var(array("ARTWORK_CONTROLLER_NO"=>"checked", "ARTWORK_CONTROLLER_YES"=>""));
	
	if($thisOptionObj->optionInCommonForProduction)
		$t->set_var(array("PRODUCTION_INCOMMON_CONTROLLER_NO"=>"", "PRODUCTION_INCOMMON_CONTROLLER_YES"=>"checked"));
	else
		$t->set_var(array("PRODUCTION_INCOMMON_CONTROLLER_NO"=>"checked", "PRODUCTION_INCOMMON_CONTROLLER_YES"=>""));
	
	if($thisOptionObj->couponDiscountExempt)
		$t->set_var(array("COUPON_DISCOUNT_EXEMPT_NO"=>"", "COUPON_DISCOUNT_EXEMPT_YES"=>"checked"));
	else
		$t->set_var(array("COUPON_DISCOUNT_EXEMPT_NO"=>"checked", "COUPON_DISCOUNT_EXEMPT_YES"=>""));
		
	if($thisOptionObj->adminOptionController)
		$t->set_var(array("ADMIN_OPTION_CONTROLLER_NO"=>"", "ADMIN_OPTION_CONTROLLER_YES"=>"checked"));
	else
		$t->set_var(array("ADMIN_OPTION_CONTROLLER_NO"=>"checked", "ADMIN_OPTION_CONTROLLER_YES"=>""));
		
	if($thisOptionObj->variableImageController)
		$t->set_var(array("VARIABLE_IMAGE_CONROLLER_NO"=>"", "VARIABLE_IMAGE_CONROLLER_YES"=>"checked"));
	else
		$t->set_var(array("VARIABLE_IMAGE_CONROLLER_NO"=>"checked", "VARIABLE_IMAGE_CONROLLER_YES"=>""));	

	if($thisOptionObj->artworkSearchReplaceController)
		$t->set_var(array("ART_SEARCH_REPLACE_CONTROLLER_NO"=>"", "ART_SEARCH_REPLACE_CONTROLLER_YES"=>"checked"));
	else
		$t->set_var(array("ART_SEARCH_REPLACE_CONTROLLER_NO"=>"checked", "ART_SEARCH_REPLACE_CONTROLLER_YES"=>""));	



	$optionChoicesArr = $thisOptionObj->choices;	

	if(empty($optionChoicesArr)){
	
		$t->discard_block("origPage", "NoChoiceYetBL");
		
		_printMainTabs($t, $view, $editProduct);
		
		$t->pparse("OUT","origPage");
		exit;
	}
	else{
		$t->discard_block("origPage", "DeleteOptionBL");
	}
	
	
	

	$viewChoice = strtoupper(WebUtil::GetInput("viewChoice", FILTER_SANITIZE_STRING_ONE_LINE));
	

	// Build a List of Tabs for Choice Names.
	$TabsObj = new Navigation();
	foreach($optionChoicesArr as $thisChoiceObj)
		$TabsObj->AddTab(strtoupper($thisChoiceObj->ChoiceName), $thisChoiceObj->ChoiceName, './ad_product_setup.php?view=optionsPricing&editProduct=' . $editProduct . "&viewOption=" . urlencode($thisOptionObj->optionName) . "&viewChoice=" . urlencode($thisChoiceObj->ChoiceName));
	$t->set_var("CHOICES_NAV_BAR", $TabsObj->GetTabsHTML($viewChoice));
	$t->allowVariableToContainBrackets("CHOICES_NAV_BAR");


	if(empty($viewChoice)){

		$t->set_block("origPage","NoChoiceSelectedBL","NoChoiceSelectedBLout");
		$t->set_var("NoChoiceSelectedBLout", "<br>Click on a Tab above to edit that Choice.");
		
		
		_printMainTabs($t, $view, $editProduct);
		
		$t->pparse("OUT","origPage");
		exit;
	}

	
	// Find out if the user has permission to change the Option Name.
	if(!$AuthObj->CheckForPermission("CHANGE_PRODUCT_OPTION_NAMES"))
		$t->discard_block("origPage", "ChangeChoiceNameBL");

	
	$allChoicesNamesArr = $thisOptionObj->getChoiceNamesArr();
	
	// If we only have 1 product Option, then there is no point in allowing hte user to sort left or right.
	if(sizeof($allChoicesNamesArr) == 1){
		$t->discard_block("origPage", "ShiftChoiceUpBL");
		$t->discard_block("origPage", "ShiftChoiceDownBL");
	}
	else if(strtoupper($allChoicesNamesArr[0]) == $viewChoice){
		$t->discard_block("origPage", "ShiftChoiceUpBL");
	}
	else if(strtoupper(end($allChoicesNamesArr)) == $viewChoice){
		$t->discard_block("origPage", "ShiftChoiceDownBL");
	}
	
	
	
	// If we are currently looking at a Choice then take away the input field to add more product Options.
	$t->discard_block("origPage", "AddNewOptionChoiceBL");

	// If we are currently looking at a Choice then don't show the radio buttons for changing Conrollers on Product Options.
	$t->discard_block("origPage", "AllOptionControllersBL");

	

	$choiceNotFound = true;
	foreach($optionChoicesArr as $thisChoiceObj){
		if(strtoupper($thisChoiceObj->ChoiceName) == strtoupper($viewChoice)){
			$choiceNotFound = false;
			break;
		}
	}
	
	if($choiceNotFound)
		throw new Exception("Error.  The Choice Name does not exist for the given option.");


	$t->set_var("CHOICE_NAME_JS", addslashes($thisChoiceObj->ChoiceName));
	$t->set_var("CHOICE_NAME", WebUtil::htmlOutput($thisChoiceObj->ChoiceName));
	$t->set_var("CHOICE_NAME_ENCODED", urlencode($viewChoice));
	$t->set_var("CHOICE_DESCRIPTION", WebUtil::htmlOutput($thisChoiceObj->ChoiceDescription));
	$t->set_var("CHOICE_NAME_ALIAS", WebUtil::htmlOutput($thisChoiceObj->ChoiceNameAlias));

	
	// Set the Radio Buttons for the Format Type of the Choice Description
	if($thisChoiceObj->ChoiceDescriptionIsHTMLformat)
		$t->set_var(array("CHOICE_DESCRIPTION_HTML_NO"=>"", "CHOICE_DESCRIPTION_HTML_YES"=>"checked"));
	else
		$t->set_var(array("CHOICE_DESCRIPTION_HTML_NO"=>"checked", "CHOICE_DESCRIPTION_HTML_YES"=>""));
	
	
	
	// If the Option affects the number of artwork sides... then this choice will show how many sides to create/delete when this choice is selected.
	if($thisOptionObj->artworkSidesController)
		$t->set_var("CHOICE_ARTWORK_SIDES_NUM", $thisChoiceObj->artworkSideCount);
	else
		$t->discard_block("origPage", "ArtwrokSidesChoiceBL");
		
	// Let Javascript know how many sides total this Product Has.
	$t->set_var("TOTAL_ARTWORK_SIDES", $productObj->getArtworkSidesCount());
	

	
	// If this Option affects Variable Images... then this choice should have a flag saying whether to turn them off or on.
	if($thisOptionObj->variableImageController){
		if($thisChoiceObj->variableImageFlag)
			$t->set_var(array("VARIABLE_IMAGES_ON"=>"checked", "VARIABLE_IMAGES_OFF"=>""));
		else
			$t->set_var(array("VARIABLE_IMAGES_ON"=>"", "VARIABLE_IMAGES_OFF"=>"checked"));
	}
	else{
		$t->discard_block("origPage", "VariableImageChoiceBL");
	}
	
	
	
	

	if($thisChoiceObj->hideOptionAllFlag)
		$t->set_var(array("HIDE_OPTION_ALL_YES"=>"checked", "HIDE_OPTION_ALL_NO"=>""));
	else
		$t->set_var(array("HIDE_OPTION_ALL_YES"=>"", "HIDE_OPTION_ALL_NO"=>"checked"));


	if($thisChoiceObj->hideOptionInListsFlag)
		$t->set_var(array("HIDE_OPTION_LISTS_YES"=>"checked", "HIDE_OPTION_LISTS_NO"=>""));
	else
		$t->set_var(array("HIDE_OPTION_LISTS_YES"=>"", "HIDE_OPTION_LISTS_NO"=>"checked"));

		
		
	
	// If this Option has Artwork Search & Replace functionality then show all of the routines
	if($thisOptionObj->artworkSearchReplaceController){
	
		$searchAndReplaceArr = $thisChoiceObj->getSearchAndReplaceRoutines();
		
		$searchRoutineCounter = 0;
		
		$t->set_block("origPage","ExistingSearchReplaceRoutinesBL","ExistingSearchReplaceRoutinesBLout");
		
		foreach($searchAndReplaceArr as $thisSearchReplaceHash){
		
			$t->set_var("SEARCH_FOR", WebUtil::htmlOutput($thisSearchReplaceHash["Search"]));
			$t->set_var("REPLACE_WITH", WebUtil::htmlOutput($thisSearchReplaceHash["Replace"]));
			$t->set_var("SEARCH_REPLACE_NUM", $searchRoutineCounter);
		
			$t->parse("ExistingSearchReplaceRoutinesBLout","ExistingSearchReplaceRoutinesBL",true);
			
			$searchRoutineCounter++;
		}

		if($searchRoutineCounter == 0)
			$t->set_var("ExistingSearchReplaceRoutinesBLout", "<br>None Yet");
		
		// The Add New Search & Replace Routine is not really any different than the existing routines... it just has the "last number" meaning that it always gets added to the end.
		$t->set_var("SEARCH_REPLACE_NUM_LAST", $searchRoutineCounter);
	
	}
	else{
		$t->discard_block("origPage", "ArtworkSearchReplaceBL");
	}
	
	
	// Set the Pricing and Weight changes for when this choice is selected.
	$t->set_var("CUSTOMER_CHOICE_SUBTOTAL", $thisChoiceObj->PriceAdjustments->getCustomerSubtotalChange());
	$t->set_var("CUSTOMER_CHOICE_BASE_PRICE", $thisChoiceObj->PriceAdjustments->getCustomerBaseChange());
	$t->set_var("BASE_WEIGHT_CHANGE", $thisChoiceObj->BaseWeightChange);
	$t->set_var("PROJECT_WEIGHT_CHANGE", $thisChoiceObj->ProjectWeightChange);
	$t->set_var("PRODUCTION_ALERT", WebUtil::htmlOutput($thisChoiceObj->productionAlert));
	
	
	// Set the vendor prices for when this Choice is selected.
	for($i=1; $i<=6; $i++){
		if($productObj->getVendorID($i) == null){
			$t->set_var("VENDOR_" . $i . "_SUBTOTAL_CHANGE", "");
			$t->set_var("VENDOR_" . $i . "_BASE_PRICE_CHANGE", "");
			$t->set_var("VENDOR_" . $i . "_NAME", "No Vendor Yet");
			$t->set_var("DISABLED_VENDOR_" . $i, "disabled");
			
		}
		else{
			
			$vendorName = UserControl::GetCompanyByUserID($dbCmd, $productObj->getVendorID($i));
			if(empty($vendorName))
				$vendorName = "Vendor Needs Company Name";
			
		
			$t->set_var("VENDOR_" . $i . "_SUBTOTAL_CHANGE", $thisChoiceObj->PriceAdjustments->getVendorSubtotalChange($i));
			$t->set_var("VENDOR_" . $i . "_BASE_PRICE_CHANGE", $thisChoiceObj->PriceAdjustments->getVendorBaseChange($i));
			$t->set_var("VENDOR_" . $i . "_NAME", "<b><font class='SmallBody'><font color='#336699'>" . WebUtil::htmlOutput($vendorName) . "</font></font></b>");
			$t->set_var("DISABLED_VENDOR_" . $i, "");
			$t->allowVariableToContainBrackets("VENDOR_" . $i . "_NAME");
		}
	}
	
	
	
	
	$quantityBreaksArr = $productObj->getQuantityPriceBreaksArrOnOptionChoice($thisOptionObj->optionName, $thisChoiceObj->ChoiceName);

	
	$t->set_block("origPage","ChoiceQuantityPriceBreakDefinition","ChoiceQuantityPriceBreakDefinitionout");
	
	foreach($quantityBreaksArr as $thisQuanBreakObj){
	
		$t->set_var("CUSTOMER_SUBTOTAL_QUAN_CHOICE", $thisQuanBreakObj->PriceAdjustments->getCustomerSubtotalChange());
		$t->set_var("CUSTOMER_BASE_PRICE_QUAN_CHOICE", $thisQuanBreakObj->PriceAdjustments->getCustomerBaseChange());
	
		$t->set_var("QUANTITY_BREAK", $thisQuanBreakObj->amount);
		
		$t->set_var("QUANTITY_BREAK_FOMATTED", number_format($thisQuanBreakObj->amount, 0));
		
	
	
		for($i=1; $i<=6; $i++){
			if($productObj->getVendorID($i) == null){
				$t->set_var("VENDOR_" . $i . "_SUBTOTAL_CHANGE_QUAN", "");
				$t->set_var("VENDOR_" . $i . "_BASE_PRICE_CHANGE_QUAN", "");
				$t->set_var("VENDOR_" . $i . "_NAME", "No Vendor Yet");
				$t->set_var("DISABLED_VENDOR_" . $i, "disabled");
			}
			else{

				$vendorName = UserControl::GetCompanyByUserID($dbCmd, $productObj->getVendorID($i));
				if(empty($vendorName))
					$vendorName = "Vendor Needs Company Name";

				$t->set_var("VENDOR_" . $i . "_SUBTOTAL_CHANGE_QUAN", $thisQuanBreakObj->PriceAdjustments->getVendorSubtotalChange($i));
				$t->set_var("VENDOR_" . $i . "_BASE_PRICE_CHANGE_QUAN", $thisQuanBreakObj->PriceAdjustments->getVendorBaseChange($i));
				$t->set_var("VENDOR_" . $i . "_NAME", "<b><font class='SmallBody'><font color='#336699'>" . WebUtil::htmlOutput($vendorName) . "</font></font></b>");
				$t->set_var("DISABLED_VENDOR_" . $i, "");
				
				$t->allowVariableToContainBrackets("VENDOR_" . $i . "_NAME");
			}
		}
	
		$t->parse("ChoiceQuantityPriceBreakDefinitionout","ChoiceQuantityPriceBreakDefinition",true);
	}
	
	

	if(empty($quantityBreaksArr))
		$t->set_var("ChoiceQuantityPriceBreakDefinitionout", "");
	

	
}
else if($view == "quantityPricing"){

	$t->discard_block("origPage", "ProductChooseBL");
	$t->discard_block("origPage", "VendorSetupBL");
	$t->discard_block("origPage", "ScheduleBL");
	$t->discard_block("origPage", "ProductStatusBL");
	$t->discard_block("origPage", "MainProductSetupBL");
	$t->discard_block("origPage", "OptionPricingBL");
	
	$productObj = new Product($dbCmd, $editProduct);
	
	if($productObj->getProductStatus() == "N")
		WebUtil::PrintAdminError($newProductStatusError, TRUE);

	$t->set_var("PRODUCTID", $editProduct);

	$t->set_var("FULL_PRODUCT_NAME", WebUtil::htmlOutput($productObj->getProductTitleWithExtention()));
	
	$t->set_var("CUSTOMER_INITIAL_SUBTOTAL", $productObj->getInitialSubtotalCustomer());
	$t->set_var("CUSTOMER_BASE_PRICE", $productObj->getBasePriceCustomer());
	
	for($i=1; $i<=6; $i++){
		if($productObj->getVendorID($i) == null){
			$t->set_var("VENDOR_" . $i . "_INIT_SUBTOTAL", "");
			$t->set_var("VENDOR_" . $i . "_BASE_PRICE", "");
			$t->set_var("VENDOR_" . $i . "_NAME", "No Vendor Yet");
			$t->set_var("DISABLED_VENDOR_" . $i, "disabled");
			
		}
		else{
			
			$vendorName = UserControl::GetCompanyByUserID($dbCmd, $productObj->getVendorID($i));
			if(empty($vendorName))
				$vendorName = "Vendor Needs Company Name";
			
		
			$t->set_var("VENDOR_" . $i . "_INIT_SUBTOTAL", $productObj->getInitialSubtotalVendor($i));
			$t->set_var("VENDOR_" . $i . "_BASE_PRICE", $productObj->getBasePriceVendor($i));
			$t->set_var("VENDOR_" . $i . "_NAME", "<b><font class='SmallBody'><font color='#336699'>" . WebUtil::htmlOutput($vendorName) . "</font></font></b>");
			$t->set_var("DISABLED_VENDOR_" . $i, "");
			
			$t->allowVariableToContainBrackets("VENDOR_" . $i . "_NAME");
		}
	}
	
	
	
	$quantityBreaksArr = $productObj->getQuantityPriceBreaksArr();
	
	$t->set_block("origPage","QuantityPriceBreakDefinition","QuantityPriceBreakDefinitionout");
	
	foreach($quantityBreaksArr as $thisQuanBreakObj){
	
		$t->set_var("CUSTOMER_SUBTOTAL_QUAN", $thisQuanBreakObj->PriceAdjustments->getCustomerSubtotalChange());
		$t->set_var("CUSTOMER_BASE_PRICE_QUAN", $thisQuanBreakObj->PriceAdjustments->getCustomerBaseChange());
	
		$t->set_var("QUANTITY_BREAK", $thisQuanBreakObj->amount);
		
		$t->set_var("QUANTITY_BREAK_FOMATTED", number_format($thisQuanBreakObj->amount, 0));
		
	
	
		for($i=1; $i<=6; $i++){
			if($productObj->getVendorID($i) == null){
				$t->set_var("VENDOR_" . $i . "_SUBTOTAL_CHANGE", "");
				$t->set_var("VENDOR_" . $i . "_BASE_PRICE_CHANGE", "");
				$t->set_var("QUAN_VENDOR_" . $i . "_NAME", "No Vendor Yet");
				$t->set_var("DISABLED_VENDOR_" . $i, "disabled");
			}
			else{

				$vendorName = UserControl::GetCompanyByUserID($dbCmd, $productObj->getVendorID($i));
				if(empty($vendorName))
					$vendorName = "Vendor Needs Company Name";

				$t->set_var("VENDOR_" . $i . "_SUBTOTAL_CHANGE", $thisQuanBreakObj->PriceAdjustments->getVendorSubtotalChange($i));
				$t->set_var("VENDOR_" . $i . "_BASE_PRICE_CHANGE", $thisQuanBreakObj->PriceAdjustments->getVendorBaseChange($i));
				$t->set_var("QUAN_VENDOR_" . $i . "_NAME", "<b><font class='SmallBody'><font color='#336699'>" . WebUtil::htmlOutput($vendorName) . "</font></font></b>");
				$t->set_var("DISABLED_VENDOR_" . $i, "");
				
				$t->allowVariableToContainBrackets("QUAN_VENDOR_" . $i . "_NAME");
			}
		}
	
		$t->parse("QuantityPriceBreakDefinitionout","QuantityPriceBreakDefinition",true);
	}
	
	

	if(empty($quantityBreaksArr))
		$t->set_var("QuantityPriceBreakDefinitionout", "");
	
	
	
}
else if($view == "vendors"){

	$t->discard_block("origPage", "ProductChooseBL");
	$t->discard_block("origPage", "OptionPricingBL");
	$t->discard_block("origPage", "ScheduleBL");
	$t->discard_block("origPage", "ProductStatusBL");
	$t->discard_block("origPage", "MainProductSetupBL");
	$t->discard_block("origPage", "QuantitiesAndPricingBL");
	
	$productObj = new Product($dbCmd, $editProduct);
	
	if($productObj->getProductStatus() == "N")
		WebUtil::PrintAdminError($newProductStatusError, TRUE);

	$t->set_var("PRODUCTID", $editProduct);

	$t->set_var("FULL_PRODUCT_NAME", WebUtil::htmlOutput($productObj->getProductTitleWithExtention()));
	
	
	$t->set_block("origPage","VendorNameBL","VendorNameBLout");
	
	$vendorIDs = $productObj->getVendorIDArr();
	
	$noneFound = true;
	foreach($vendorIDs as $vendNum => $vendID){
	
		if(empty($vendID))
			continue;
	
		$companyName = UserControl::GetCompanyByUserID($dbCmd, $vendID);
		
		if(empty($companyName))
			$t->set_var("VENDOR_NAME", "Vendor is Missing a Company Name: U" . $vendID);
		else
			$t->set_var("VENDOR_NAME", WebUtil::htmlOutput($companyName));

		$t->set_var("VENDOR_ID", $vendID);

		$t->parse("VendorNameBLout","VendorNameBL",true);
		
		$noneFound = false;
	}
	
	if($noneFound)
		$t->set_var("VendorNameBLout", "No Vendors have been added for this product yet.");
	
	// Don't let the user add more than 6 vendors.
	if($productObj->getVendorCount() == 6 || !Product::checkIfProductPiggyBackIsWithinUsersDomain($editProduct)){
		$t->discard_block("origPage", "AddNewVendorBl");
	}
	
	if(Product::checkIfProductPiggyBackIsWithinUsersDomain($editProduct))
		$t->discard_block("origPage", "CantChangeVendorBL");
}




else if($view == "status"){

	$t->discard_block("origPage", "ProductChooseBL");
	$t->discard_block("origPage", "OptionPricingBL");
	$t->discard_block("origPage", "VendorSetupBL");
	$t->discard_block("origPage", "MainProductSetupBL");
	$t->discard_block("origPage", "QuantitiesAndPricingBL");
	$t->discard_block("origPage", "ScheduleBL");
	
	

	$productObj = new Product($dbCmd, $editProduct);
	
	if($productObj->getProductStatus() == "N")
		WebUtil::PrintAdminError($newProductStatusError, TRUE);


	$t->set_var("PRODUCTID", $editProduct);

	$t->set_var("FULL_PRODUCT_NAME", WebUtil::htmlOutput($productObj->getProductTitleWithExtention()));


	
	$defaultProductIDforStatusDesc = ProductStatus::getDefaultProductStatusProductID($dbCmd, Domain::oneDomain());

	// Find out if this is the default Product ID for Product Statuses.
	if($defaultProductIDforStatusDesc == $editProduct){
		$t->set_var(array("PRODUCT_STATUS_DEFAULT_YES"=>"checked", "PRODUCT_STATUS_DEFAULT_NO"=>""));
		$t->set_var("CURRENT_SETTINGS_FOR_PRODUCT_STATUS_DEFAULT", "yes");
	}
	else{
		$t->set_var(array("PRODUCT_STATUS_DEFAULT_YES"=>"", "PRODUCT_STATUS_DEFAULT_NO"=>"checked"));
		$t->set_var("CURRENT_SETTINGS_FOR_PRODUCT_STATUS_DEFAULT", "no");
	}
	


	$productStatusObj = new ProductStatus($dbCmd, $editProduct);
	
	
	// Don't show radio buttons to make this the default Product if it doesn't have any settings saved yet.
	if(!$productStatusObj->checkIfSettingsAreSavedForThisProduct())
		$t->discard_block("origPage", "DefaultProductStatusRadioButtonsBL");
	

	

	if(!empty($defaultProductIDforStatusDesc)){
		
		$t->set_var("DEFAULT_PRODUCT_NAME", WebUtil::htmlOutput(Product::getFullProductName($dbCmd, $defaultProductIDforStatusDesc)));
		$t->set_var("PRODUCT_ID_OF_DEFAULT", $defaultProductIDforStatusDesc);
	}
	
	
	// If this Product does not have its own settings saved, and there is no Default Product Yet, then we want to discard both blocks of info relating to "Status Linking".
	if(empty($defaultProductIDforStatusDesc) && !$productStatusObj->checkIfSettingsAreSavedForThisProduct()){
		$t->discard_block("origPage", "UsingTheDefaultStatusesBL");
		$t->discard_block("origPage", "SettingsHaveBeenSavedBL");
	}
	else{
		if($productStatusObj->checkIfSettingsAreSavedForThisProduct())
			$t->discard_block("origPage", "UsingTheDefaultStatusesBL");
		else
			$t->discard_block("origPage", "SettingsHaveBeenSavedBL");
	}


	
	
	$t->set_block("origPage","ProductStatusRowBL","ProductStatusRowBLout");
	
	// Loop through all of our Status Characters used for Projects
	// Try to get the overrided value for the Product.  If the Product Status hasn't been defined yet it will just return the internal Product Status and a blank description.
	$projectStatusArr = ProjectStatus::GetStatusDescriptionsHash();
	foreach($projectStatusArr as $thisStatusChar => $thisStatusDescHash){

		
		$t->set_var("STATUS_COLOR", $thisStatusDescHash["COLOR"]);
		$t->set_var("INTERNAL_STATUS", WebUtil::htmlOutput($thisStatusDescHash["DESC"]));
		$t->set_var("STATUS_TITLE", WebUtil::htmlOutput($productStatusObj->getStatusTitle($thisStatusChar)));
		$t->set_var("STATUS_DESC", WebUtil::htmlOutput($productStatusObj->getStatusDesc($thisStatusChar)));
		$t->set_var("STATUS_CHAR", $thisStatusChar);
		
		
		$t->parse("ProductStatusRowBLout","ProductStatusRowBL",true);
		
	}


}
else if($view == "schedule"){

	$t->discard_block("origPage", "ProductChooseBL");
	$t->discard_block("origPage", "OptionPricingBL");
	$t->discard_block("origPage", "VendorSetupBL");
	$t->discard_block("origPage", "MainProductSetupBL");
	$t->discard_block("origPage", "QuantitiesAndPricingBL");
	$t->discard_block("origPage", "ProductStatusBL");
	
	
	$productObj = new Product($dbCmd, $editProduct);
	
	if($productObj->getProductStatus() == "N")
		WebUtil::PrintAdminError($newProductStatusError, TRUE);

	$t->set_var("PRODUCTID", $editProduct);

	$t->set_var("FULL_PRODUCT_NAME", WebUtil::htmlOutput($productObj->getProductTitleWithExtention()));
	
	
	$t->set_var("DEFAULT_CUTOFF_HOUR", $productObj->getDefaultCutOffHour());
	$t->set_var("DEFAULT_PRODUCTION_DAYS", $productObj->getDefaultProductionDays());
	
	

	$shippingChoiceObj = new ShippingChoices();
	$allShippingChoicessHash = $shippingChoiceObj->getActiveShippingChoices();
	
	
	// Build a Drop down menu with Shipping Choices that have not already been overriden.
	$dropDownMenuShipMethodsProduction = array("default"=>"Shipping Methods");
	$dropDownMenuShipMethodsCutOffHour = array("default"=>"Shipping Methods");
	
	
	// Show existing Production Days Overrides
	$t->set_block("origPage","ProductionDaysOverrideBL","ProductionDaysOverrideBLout");
	
	$notFoundFlag = true;
	foreach($allShippingChoicessHash as $shippingChoiceID => $shippingChoiceName){
	
		if($productObj->getProductionDaysOverride($shippingChoiceID) != null){
			
			$t->set_var("PRODUCTION_DAYS_OVERRIDE_SHIPMETHOD", "<font color='" . $shippingChoiceObj->getColorCode($shippingChoiceID) . "'>" . WebUtil::htmlOutput($shippingChoiceName) . "</font>");
			$t->set_var("OVERRIDE_PRODUCTION_DAYS", $productObj->getProductionDaysOverride($shippingChoiceID));
			$t->set_var("SHIPPING_ID", $shippingChoiceID);
		
			$t->allowVariableToContainBrackets("PRODUCTION_DAYS_OVERRIDE_SHIPMETHOD");
			
			$t->parse("ProductionDaysOverrideBLout","ProductionDaysOverrideBL",true);
			
			$notFoundFlag = false;
		}
		else{
			$dropDownMenuShipMethodsProduction[$shippingChoiceID] = $shippingChoiceName;
		}
	}
	
	if($notFoundFlag)
		$t->set_var("ProductionDaysOverrideBLout", "<br>No Production Days overridden based on Shipping Methods yet.");
		
		
	// Show existing Production Hour Overrides
	$t->set_block("origPage","CutOffHourOverrideBL","CutOffHourOverrideBLout");
	
	$notFoundFlag = true;
	foreach($allShippingChoicessHash as $shippingChoiceID => $shippingChoiceName){
	
		if($productObj->getProductionHourOverride($shippingChoiceID) != null){
			
			$t->set_var("CUTOFF_HOUR_OVERRIDE_SHIPMETHOD", "<font color='" . $shippingChoiceObj->getColorCode($shippingChoiceID) . "'>" . WebUtil::htmlOutput($shippingChoiceName) . "</font>");
			$t->set_var("OVERRIDE_CUTOFF_HOUR", $productObj->getProductionHourOverride($shippingChoiceID));
			$t->set_var("SHIPPING_ID", $shippingChoiceID);
			
			$t->allowVariableToContainBrackets("CUTOFF_HOUR_OVERRIDE_SHIPMETHOD");
		
			$t->parse("CutOffHourOverrideBLout","CutOffHourOverrideBL",true);
			
			$notFoundFlag = false;
		}
		else{
			$dropDownMenuShipMethodsCutOffHour[$shippingChoiceID] = $shippingChoiceName;
		}
	}
	
	if($notFoundFlag)
		$t->set_var("CutOffHourOverrideBLout", "<br>No Cut-Off Hours overridden based on Shipping Methods yet.");
		
	
	$t->set_var("SHIPPING_METHODS_PRODUCTION_DAYS", Widgets::buildSelect($dropDownMenuShipMethodsProduction, array("default")));
	$t->set_var("SHIPPING_METHODS_CUTOFF_HOUR", Widgets::buildSelect($dropDownMenuShipMethodsCutOffHour, array("default")));
	
	$t->allowVariableToContainBrackets("SHIPPING_METHODS_PRODUCTION_DAYS");
	$t->allowVariableToContainBrackets("SHIPPING_METHODS_CUTOFF_HOUR");
	
	
	
	

	
}

else if($view == "imageThumbnailCopyIcon"){

	$productObj = new Product($dbCmd, $editProduct);
	
	$imageData =& $productObj->getThumbnailCopyIconJPG();
	
	if(empty($imageData))
		throw new Exception("Error in view imageThumbnailCopyIcon.  Preview is not possible.");
	
	$previewfileName = "previewThumbIcon_" . $editProduct . ".jpg";

	// Put on disk
	$fp = fopen(DomainPaths::getPdfSystemPathOfDomainInURL() . "/" . $previewfileName, "w");
	fwrite($fp, $imageData);
	fclose($fp);

	$fileDownloadLocation = WebUtil::FilterURL(DomainPaths::getPdfWebPathOfDomainInURL() . "/" . $previewfileName);
	
	header("Location: " . WebUtil::FilterURL($fileDownloadLocation));
	exit;
}
else if($view == "imageThumbnailBackgroundImage"){

	$productObj = new Product($dbCmd, $editProduct);
	
	$imageData =& $productObj->getThumbnailBackJPG();
	
	if(empty($imageData))
		throw new Exception("Error in view imageThumbnailBackgroundImage.  Preview is not possible.");
	
	$previewfileName = "previewThumbBack_" . $editProduct . ".jpg";

	// Put on disk
	$fp = fopen(DomainPaths::getPdfSystemPathOfDomainInURL() . "/" . $previewfileName, "w");
	fwrite($fp, $imageData);
	fclose($fp);

	$fileDownloadLocation = WebUtil::FilterURL(DomainPaths::getPdfWebPathOfDomainInURL() . "/" . $previewfileName);
	
	header("Location: " . WebUtil::FilterURL($fileDownloadLocation));
	exit;
}
else if($view == "imageTemplateBackgroundImageLandscape"){

	$productObj = new Product($dbCmd, $editProduct);
	
	$imageData =& $productObj->getTempPrevBackLandscapeJPG();
	
	if(empty($imageData))
		throw new Exception("Error in view imageTemplateBackgroundImageLandscape.  Preview is not possible.");
	
	$previewfileName = "previewTemplateBackLandscape_" . $editProduct . ".jpg";

	// Put on disk
	$fp = fopen(DomainPaths::getPdfSystemPathOfDomainInURL() . "/" . $previewfileName, "w");
	fwrite($fp, $imageData);
	fclose($fp);

	$fileDownloadLocation = WebUtil::FilterURL(DomainPaths::getPdfWebPathOfDomainInURL() . "/" . $previewfileName);
	
	header("Location: " . WebUtil::FilterURL($fileDownloadLocation));
	exit;
}
else if($view == "imageTemplateBackgroundImagePortrait"){

	$productObj = new Product($dbCmd, $editProduct);
	
	$imageData =& $productObj->getTempPrevBackPortraitJPG();
	
	if(empty($imageData))
		throw new Exception("Error in view imageTemplateBackgroundImagePortrait.  Preview is not possible.");
	
	$previewfileName = "previewTemplateBackPortrait_" . $editProduct . ".jpg";

	// Put on disk
	$fp = fopen(DomainPaths::getPdfSystemPathOfDomainInURL() . "/" . $previewfileName, "w");
	fwrite($fp, $imageData);
	fclose($fp);

	$fileDownloadLocation = WebUtil::FilterURL(DomainPaths::getPdfWebPathOfDomainInURL() . "/" . $previewfileName);
	
	header("Location: " . WebUtil::FilterURL($fileDownloadLocation));
	exit;
}
else if($view == "imageTemplatePreviewMask"){

	$productObj = new Product($dbCmd, $editProduct);
	
	$imageData =& $productObj->getTemplatePreviewMaskPNG();
	
	if(empty($imageData))
		throw new Exception("Error in view imageTemplatePreviewMask.  Preview is not possible.");
	
	$previewfileName = "previewTemplateMask_" . $editProduct . ".jpg";

	// Put on disk
	$fp = fopen(DomainPaths::getPdfSystemPathOfDomainInURL() . "/" . $previewfileName, "w");
	fwrite($fp, $imageData);
	fclose($fp);

	$fileDownloadLocation = WebUtil::FilterURL(DomainPaths::getPdfWebPathOfDomainInURL() . "/" . $previewfileName);
	
	header("Location: " . WebUtil::FilterURL($fileDownloadLocation));
	exit;
}
else{
	throw new Exception("Illegal View Type: $view");
}



_printMainTabs($t, $view, $editProduct);


$t->pparse("OUT","origPage");




function _printMainTabs(&$templateObj, $view, $editProduct){

	// Build the Main Product Tabs
	if(in_array($view, array("productMain", "optionsPricing", "vendors", "schedule", "quantityPricing", "status"))){

		// Build the tabs
		$mainTabsObj = new Navigation();

		// Call to a library function because other Controller Scripts are also calling building the same tabs.
		Widgets::buildMainTabsForProductSetupScreen($mainTabsObj, $editProduct);

		$templateObj->set_var("MAIN_PRODUCT_NAV_BAR", $mainTabsObj->GetTabsHTML($view));
		$templateObj->allowVariableToContainBrackets("MAIN_PRODUCT_NAV_BAR");
	}
	else{

		$templateObj->discard_block("origPage", "MainProductTabsBL");	
	}


}


?>
