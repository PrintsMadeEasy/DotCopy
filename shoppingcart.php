<?
require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();


// Thumbnails may take a long time to generate on very large artworks.  Give it 3 mintues.
set_time_limit(180);


$user_sessionID =  WebUtil::GetSessionID();

//WebUtil::BreakOutOfSecureMode();


$message = WebUtil::GetInput("message", FILTER_SANITIZE_STRING_ONE_LINE);


$domainIDforShoppingCart = Domain::oneDomain();

$mysql_timestamp = date("YmdHis");




$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
if($action){

	if($action == "copyprojectssession"){

		$projectrecord = WebUtil::GetInput("projectrecord", FILTER_SANITIZE_INT);
		$returnurl = WebUtil::GetInput("returnurl", FILTER_SANITIZE_URL);
		

		if(!ProjectSession::CheckIfUserOwnsProjectSession($dbCmd, $projectrecord))
			WebUtil::PrintError("It appears this project is not available anymore. Your session may have expired.");

		$originalProjectObj = ProjectSession::getObjectByProjectID($dbCmd, $projectrecord); 
		
		$newProjectObj = new ProjectSession($dbCmd);
		$newProjectObj->copyProject($originalProjectObj);
		$newProjectID = $newProjectObj->createNewProject($user_sessionID);

		ThumbImages::MarkThumbnailForProjectAsCopy($dbCmd, "projectssession", $newProjectID);

		//Now Insert project into shopping cart
		$thisInsertID = $dbCmd->InsertQuery("shoppingcart", array("ProjectRecord"=>$newProjectID, "SID"=>$user_sessionID, "DateLastModified"=>$mysql_timestamp, "DomainID"=>$domainIDforShoppingCart));
		
		// So that our Shopping Cart starts up quickly with less animation.  Just doing a copy or delete shouldn't restart the animations.
		WebUtil::SetSessionVar( "ShoppingCartQuickStart", "yes");

		VisitorPath::addRecord("Shopping Cart Copy Project");
		
		header("Location: ". WebUtil::FilterURL($returnurl));
		exit;
	}
	else if($action == "removeshoppingcartitem"){

		$projectrecord = WebUtil::GetInput("projectrecord", FILTER_SANITIZE_INT);

		if(!ProjectSession::CheckIfUserOwnsProjectSession($dbCmd, $projectrecord))
			WebUtil::PrintError("It appears this project is not available anymore. Your session may have expired.");

		$dbCmd->Query("DELETE FROM shoppingcart WHERE ProjectRecord=$projectrecord AND DomainID=$domainIDforShoppingCart");

		// So that our Shopping Cart starts up quickly with less animation.  Just doing a copy or delete shouldn't restart the animations.
		WebUtil::SetSessionVar( "ShoppingCartQuickStart", "yes");

		VisitorPath::addRecord("Shopping Cart Delete Project");
		
		$templateID = WebUtil::GetInput("TemplateID", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
		
		if(!empty($templateID))
			header("Location: ./shoppingcart.php?TemplateID=$templateID");
		else 
			header("Location: ./shoppingcart.php");
			
		exit;
	}
	else if($action == "updatesessionprojectsthumbnail"){

		$projectrecord = WebUtil::GetInput("projectrecord", FILTER_SANITIZE_INT);
		$returnurl = WebUtil::GetInput("returnurl", FILTER_SANITIZE_URL);
		

		if(!ProjectSession::CheckIfUserOwnsProjectSession($dbCmd, $projectrecord))
			WebUtil::PrintError("It appears this project is not available anymore.  Your session may have expired.");

		// Make sure that the same thumbnail is not generated more than once.
		// It is possible that the person could hit there back button many times repeatadely and cause lots of thumbnails to start loading at once, overloading the server
		if(ThumbImages::checkIfThumbnailCanBeUpdated($dbCmd, "session", $projectrecord)){
			ThumbImages::markThumbnailAsUpdating($dbCmd, "session", $projectrecord);
			ThumbImages::CreateThumnailImage($dbCmd, $projectrecord, "projectssession");
		}
		


		
		
		// Find out if the domain Sandbox has included an ArtworkMessage template.  If not, don't redirect there.
		if(file_exists(Domain::getDomainSandboxPath() . "/artwork_message-template.html")){
			
			// Now that the artwork is updated we want to scan it to look and see if they haven't used the fonts within our editing tool
			// We may pass in an a parameter that does not want us to check... like if they are uploading their artwork from a single JPEG and not using the editor
			$ArtworkInfoObj = new ArtworkInformation(ArtworkLib::GetArtXMLfile($dbCmd, 'session', $projectrecord));
			
			if(!WebUtil::GetInput("IgnoreEmptyTextAlert", FILTER_SANITIZE_STRING_ONE_LINE) && $ArtworkInfoObj->checkForEmptyTextOnNonVectorArtwork()){
				header("Location: " . WebUtil::FilterURL("./artwork_message.php?message=notext&redirect=". urlencode($returnurl)));
				exit;
			}
		}



		header("Location: ". WebUtil::FilterURL($returnurl));
		exit;
	}

	else
		throw new Exception("Illegal action passed");
	
}









// Set this variable.  We will check to make sure it gets set on editor to ensure session is functioning
$HTTP_SESSION_VARS['initialized'] = 1;




$t = new Templatex();


// Get all of the information out of the shopping cart and the project table.
$dbCmd->Query("SELECT count(*) FROM projectssession INNER JOIN 
		shoppingcart ON shoppingcart.ProjectRecord = projectssession.ID 
		WHERE shoppingcart.SID=\"$user_sessionID\" AND shoppingcart.DomainID=$domainIDforShoppingCart");

$TotalProjectsInCart = $dbCmd->GetValue();

// Load the "Empty ShoppingCart" template if there is no items in the cart.
if($TotalProjectsInCart == 0){
	
	$t->set_file("origPage", "shoppingcart_empty-template.html");
	

	$t->set_var("CHECKOUT_COMMAND", '');
	
	VisitorPath::addRecord("Shopping Cart Empty");
	
	$t->pparse("OUT","origPage");
	
	exit;
}
else{

	$t->set_file("origPage", "shoppingcart-template.html");
}









// Try to find out what Promo Blocks have been included within the HTML source.
if (!$t->loadfile("origPage")) 
	throw new Exception("Can not load Shopping Cart file: " . $t->get_var("origPage"));
$templateHTMLsource = $t->get_var("origPage");

$promoBlockNamesArr = array();
if(preg_match_all("/<!--\s+BEGIN\s+(promo_\w+)\s+-->/i", $t->get_var("origPage"), $promoBlockNamesArr)){
	// preg_match_all puts the results of the RegEx into a 2D array.
	$promoBlockNamesArr = $promoBlockNamesArr[1];
}

$currentPromoCode = strtoupper(WebUtil::GetSessionVar("PromoSpecial", WebUtil::GetCookie("PromoSpecial")));


// For the following offer, we don't want it to show in case the user has viewed the Pricing Page or the Home Page Biz Calculator.
if($currentPromoCode == "OFFER9XA12"){
	
	if(VisitorPath::checkIfVisitorHasGoneThroughLabel("Pricing Page") || VisitorPath::checkIfVisitorHasGoneThroughLabel("Home: BizCards: PriceDisplay")){
		WebUtil::SetSessionVar("PromoSpecial", "");
		WebUtil::SetCookie("PromoSpecial", "");
		$currentPromoCode = "";
	}
}

// Now Remove any Promo Code block of HTML in the template for which the user does not have permission to view.
foreach($promoBlockNamesArr as $thisPromoCodeInTemplate){
	
	// In the Template, the promo code block is prefixed with "promo_".  That does not exist within the cookie.
	if(("promo_" . $currentPromoCode) != $thisPromoCodeInTemplate){
		$t->discard_block("origPage", $thisPromoCodeInTemplate);
	}
}



// Don't show the Paypal button unless a cookie is set.
if(WebUtil::GetCookie("ShowPaypalButton") == "yes")
	$t->discard_block("origPage","CheckoutWithoutPaypal");
else 
	$t->discard_block("origPage","PaypalButton");






// Nested Blocks within Shopping Cart Row
$t->set_block("origPage","WarningBL","WarningBLout");
$t->set_block("origPage","ArtworkTransferedBL","ArtworkTransferedBLout");
$t->set_block("origPage","ProductDoesHaveThumbnailBackgroundImageBL","ProductDoesHaveThumbnailBackgroundImageBLout");
$t->set_block("origPage","ProductHasNoThumbnailBackgroundImageBL","ProductHasNoThumbnailBackgroundImageBLout");
$t->set_block("origPage","ItemSeparateBL","ItemSeparateBLout");
// Nested Buttons Blocks within Shopping Cart Row
$t->set_block("origPage","ViewArtworkButtonBL","ViewArtworkButtonBLout");
$t->set_block("origPage","ViewArtworkWithDataMergeButtonBL","ViewArtworkWithDataMergeButtonBLout");
$t->set_block("origPage","ViewArtworkButtonWithMergeSmallDataBL","ViewArtworkButtonWithMergeSmallDataBLout"); // If there is a small variable data file then the user should just see a standard "PDF proof button", but displays with data merged always.
$t->set_block("origPage","ConfigVariableDataButtonBL","ConfigVariableDataButtonBLout");
$t->set_block("origPage","MakeCopyButtonBL","MakeCopyButtonBLout");
$t->set_block("origPage","SaveButtonBL","SaveButtonBLout");
$t->set_block("origPage","SavedAlreadyIconBL","SavedAlreadyIconBLout");

$t->set_block("origPage","CartItemsBL","CartItemsBLout");

$shoppingCartSubtotal = 0;
$itemCounter = 0;

$ThereIsSavedDesignInShoppingCart = false;

$customerSubtotalsArr = array();
$customerSubtotalsDiscNoApplyArr = array();
$projectIDsArr = array();
$productIDsArr = array();

if(!empty($user_sessionID)){


	$dbCmd->Query("SELECT projectssession.ID AS ProjectID, projectssession.CustomerSubtotal, projectssession.OrderDescription, 
			projectssession.OptionsDescription, projectssession.ProductID, projectssession.CustomerSubtotal, 
			UNIX_TIMESTAMP(projectssession.DateLastModified) AS DateModified FROM projectssession 
			INNER JOIN shoppingcart ON shoppingcart.ProjectRecord = projectssession.ID 
			WHERE shoppingcart.SID=\"$user_sessionID\" AND shoppingcart.DomainID=$domainIDforShoppingCart order by shoppingcart.ID DESC");


	while ($row = $dbCmd->GetRow()){

		$ThisProjectRecord = $row["ProjectID"];
		$CustomerSubtotal = $row["CustomerSubtotal"];
		$OrderDescription = $row["OrderDescription"];
		$OptionsDescription = $row["OptionsDescription"];
		$ProductID = $row["ProductID"];
		$CustomerSubtotal = number_format($row["CustomerSubtotal"], 2);
		$CustomerSubtotalNoCommas = number_format($row["CustomerSubtotal"], 2, '.', '');
		$DateLastModified = $row["DateModified"];
		$shoppingCartSubtotal += $row["CustomerSubtotal"];
		
		$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd2, "session", $ThisProjectRecord);
		
		$projectIDsArr[] = $ThisProjectRecord;
		$productIDsArr[] = $ProductID;
		$customerSubtotalsArr[] = "" . $CustomerSubtotalNoCommas;
		$customerSubtotalsDiscNoApplyArr[] = "" . number_format($projectObj->getAmountOfSubtotalThatIsDiscountExempt(), 2, '.', '');
		
		$prodObj = new Product($dbCmd2, $ProductID);

		$itemCounter++;

		$t->set_var("WarningBLout", "");


		$t->set_var(array(
					"PROJECTRECORD"=>$ThisProjectRecord,
					"PRODUCT_TITLE"=>WebUtil::htmlOutput($prodObj->getProductTitle()),
					"PRODUCT_TITLE_WITH_SLASHES"=>addslashes($prodObj->getProductTitle()),
					"PRODUCT_TITLE_EXT"=>WebUtil::htmlOutput($prodObj->getProductTitleExtension())
					));
		
		
		// Create a Hash about this Project that can be piped into a JSON encoded string.
		$projectOptionsHash = $projectObj->getOptionsAndSelectionsWithoutHiddenChoices();
		
		$optionNames_JsonArr = array_keys($projectOptionsHash);
		$optionChoices_JsonArr = array_values($projectOptionsHash);
		

		$t->set_var("PROJECT_QUANTITY", $projectObj->getQuantity());
		
		$t->set_var("PRODUCT_ID", $ProductID);
		
		
		if($projectObj->isVariableData())
			$t->set_var("PROJECT_VARIABLE_DATA_FLAG", "true");
		else
			$t->set_var("PROJECT_VARIABLE_DATA_FLAG", "false");

		
		if($prodObj->hasMailingService())
			$t->set_var("PROJECT_MAILING_FLAG", "true");
		else
			$t->set_var("PROJECT_MAILING_FLAG", "false");
			
			
		$t->set_var("PROJECT_OPTIONS_JSON", json_encode($optionNames_JsonArr));
		$t->set_var("PROJECT_CHOICES_JSON", json_encode($optionChoices_JsonArr));
		$t->set_var("QUANTITY_CHOICES_ARR", implode(",", $prodObj->getQuantityChoices()));

		
		
		// Figure out how many "non-hidden" choices there are for each of the options and store in a paralell array
		$optionChoiceCountsNotHiddenArr = array();
	
		foreach(array_keys($projectOptionsHash) as $thisOptionName){	
			$optionDetailObj = $prodObj->getOptionDetailObj($thisOptionName);
			$optionChoiceCountsNotHiddenArr[] = sizeof($optionDetailObj->getChoiceNamesArrNotHiddenArr());
		}
		
		$t->set_var("PROJECT_CHOICE_COUNTS_NOT_HIDDEN_JSON", implode(",", $optionChoiceCountsNotHiddenArr));
		
		
		
		
		
		// If their is a login error for the Variable Data project we want to automatically check every time the page loads.  If they are logged in it will recalculate the status
		if($projectObj->isVariableData() && $projectObj->getVariableDataStatus() == "L")
			VariableDataProcess::SetVariableDataStatusForProject($dbCmd2, $ThisProjectRecord, "session");


		// If the variable Data flags have any of these statuses... then it is an error.
		if(($projectObj->isVariableData()) && ($projectObj->getVariableDataStatus() == "D" || $projectObj->getVariableDataStatus() == "A" || $projectObj->getVariableDataStatus() == "L" || $projectObj->getVariableDataStatus() == "I"))
			$varialbeDataHasErrorsFlag = true;
		else
			$varialbeDataHasErrorsFlag = false;



		if(ProjectSaved::CheckIfProjectIsSaved($dbCmd2, $ThisProjectRecord))
			$ThereIsSavedDesignInShoppingCart = true;
	
			
		// Construct a URL that can be used to download a thumbnail for the project
		$ThumbnailImage = "./thumbnail_download.php?id=" . $ThisProjectRecord . "&projecttype=projectssession&sc=" . $user_sessionID . "&modified=" . $DateLastModified;



		// On variable data projects... it will read "Modify Artwork / Data".  On static products it is just Edit Artwork
		// It takes them to different places 
		if($projectObj->isVariableData()){
	
			$varDataConfigureLink = "./vardata_editdata.php?projectrecord=$ThisProjectRecord&editorview=projectssession&returnurl=shoppingcart.php&cancelurl=shoppingcart.php&continueurl=shoppingcart.php";
			
			$t->parse("ConfigVariableDataButtonBLout","ConfigVariableDataButtonBL", false);

			// We are only showing the Error color as red for now.  Ignore warnings
			if($varialbeDataHasErrorsFlag){
				$t->set_var("WARNINGMSG", WebUtil::htmlOutput($projectObj->getVariableDataMessage()));
				$t->set_var("WARNINGCOLOR", "#FFDDDD");
				$t->set_var("SEVERITY", "Variable Data Notice");
				
				$t->parse("WarningBLout","WarningBL",true);
			}
			
			$t->set_var("PROJECT_VARIABLE_DATA_CONFIGURE_LINK", $varDataConfigureLink);
		}
		else{
	
			$varDataConfigureLink = "";

			$t->set_var("EDIT_IMAGE", "editartwork");
			$t->set_var("EDIT_PROJECT_LINK", "edit_artwork.php");
			
			$t->set_var("ConfigVariableDataButtonBLout", "");
			
			$t->set_var("PROJECT_VARIABLE_DATA_CONFIGURE_LINK", "");
			
		}
		
		
		
		// If it is a Variable Data Project and the quantity is less than 1000, then the "View Artwork" button should include the "Merge Feature"... instead of having a separate button for each.
		if($projectObj->isVariableData() && $projectObj->getQuantity() > 1000){

			// However, if there is an error... don't show the Merge button.  Just show the View Artwork (single) button only.
			if($varialbeDataHasErrorsFlag){
				$t->set_var("ViewArtworkWithDataMergeButtonBLout", "");
			}
			else{
				// If this is a variable data project without any errors, then give them a link to view the PDF doc with data merge (in addition to a single PDF proof.
				$t->parse("ViewArtworkWithDataMergeButtonBLout","ViewArtworkWithDataMergeButtonBL", false);
			}
			
			$t->parse("ViewArtworkButtonBLout","ViewArtworkButtonBL", false);
			$t->set_var("ViewArtworkButtonWithMergeSmallDataBLout", "");	
		

		}
		else if($projectObj->isVariableData()){
		
			// If it is Variable Data under 1,000 qty, then never show the Merge option button.
			// The View Artwork button will be an Merge (as long as there are now errors)
			if($varialbeDataHasErrorsFlag){
				$t->parse("ViewArtworkButtonBLout","ViewArtworkButtonBL", false);
				$t->set_var("ViewArtworkButtonWithMergeSmallDataBLout", "");	
			}
			else{
				// If this is a variable data project without any errors, then give them a link to view the PDF doc with data merge.
				$t->parse("ViewArtworkButtonWithMergeSmallDataBLout","ViewArtworkButtonWithMergeSmallDataBL", false);
				$t->set_var("ViewArtworkButtonBLout", "");
			}
			
			// As long as it is under 1,000 quantity we will never show a button for the Variable Data with Link button.
			$t->set_var("ViewArtworkWithDataMergeButtonBLout", "");
		}
		else{
		
			// Just a normal project then.  No Variable Data here.
			$t->parse("ViewArtworkButtonBLout","ViewArtworkButtonBL", false);
			$t->set_var("ViewArtworkButtonWithMergeSmallDataBLout", "");	
			$t->set_var("ViewArtworkWithDataMergeButtonBLout", "");
		
		}



		$t->set_var(array(
					"IMAGE"=>$ThumbnailImage, 
					"PRICE"=>$CustomerSubtotal, 
					"INFO_TABLE"=>$projectObj->getProjectDescriptionTable("smallbody", $varDataConfigureLink)
					));
		
					
		$t->allowVariableToContainBrackets("INFO_TABLE");
		
					
		// If this product does not have a thumbnail background photo... then the thumbnail image may just be the artwork, without any border or any description.
		// So we are going to delete different block depending on whether there is a background thumbnail image or not.  This will give an HTML artist the most flexibility.
		if($prodObj->checkIfThumbnailBackSaved()){
			$t->parse("ProductDoesHaveThumbnailBackgroundImageBLout","ProductDoesHaveThumbnailBackgroundImageBL", false);
			$t->set_var("ProductHasNoThumbnailBackgroundImageBLout", "");
		}
		else{
			$t->parse("ProductHasNoThumbnailBackgroundImageBLout","ProductHasNoThumbnailBackgroundImageBL", false);
			$t->set_var("ProductDoesHaveThumbnailBackgroundImageBLout", "");
		}
		


		// If the Artwork has been transfered... then we should show the customer and indication... telling them to update their artwork.
		if($projectObj->checkIfArtworkTransfered()){
			
			$productObj = Product::getProductObj($dbCmd2, $ProductID);
		
			$t->set_var("PRODUCT_TRANSFER_ID", $ProductID);
			$t->set_var("PRODUCT_TRANSFER_NAME", WebUtil::htmlOutput( $productObj->getProductTitle() ));
			
			// Don't show some of the buttons if we just did an artwork transfer.  We don't want to confuse them if we don't have to.
			$t->set_var("MakeCopyButtonBLout", "");
			$t->set_var("SavedAlreadyIconBLout", "");
			$t->set_var("SaveButtonBLout", "");
			
			$t->parse("ArtworkTransferedBLout","ArtworkTransferedBL", false);
		}
		else{
			
			if(ProjectSaved::CheckIfProjectIsSaved($dbCmd2, $ThisProjectRecord)){
				$t->parse("SavedAlreadyIconBLout","SavedAlreadyIconBL", false);
				$t->set_var("SaveButtonBLout", "");
			}
			else{
				$t->parse("SaveButtonBLout","SaveButtonBL", false);
				$t->set_var("SavedAlreadyIconBLout", "");
			}

			$t->parse("MakeCopyButtonBLout","MakeCopyButtonBL", false);
			$t->set_var("ArtworkTransferedBLout", "");
		}

		
		// If we are not on the last item in the shopping cart... then show a separator (like a line)
		if($itemCounter < $TotalProjectsInCart)
			$t->parse("ItemSeparateBLout","ItemSeparateBL", false);
		else
			$t->set_var("ItemSeparateBLout", "");


		$t->parse("CartItemsBLout","CartItemsBL",true);

	}
	
	$t->set_var("PROJECT_SUBTOTALS_JSON", json_encode($customerSubtotalsArr));
	$t->set_var("PROJECT_SUBTOTALS_DISC_NO_APPLY_JSON", json_encode($customerSubtotalsDiscNoApplyArr));
	
}


// Now show animations if we recomend saving their project(s) or if their project was successfully saved
if($message == "saved")
	$t->discard_block("origPage", "RecommendSavingBL");
else if(!$ThereIsSavedDesignInShoppingCart)
	$t->discard_block("origPage", "DesignSavedBL");
else{
	$t->discard_block("origPage", "RecommendSavingBL");
	$t->discard_block("origPage", "DesignSavedBL");
}


// Our Background Image changes with the number of projects in the Shopping Cart.
// It stops once we get to 20 though.
if($TotalProjectsInCart > 20)
	$t->set_var("CART_BACKGROUND_COUNT", 20);
else
	$t->set_var("CART_BACKGROUND_COUNT", $TotalProjectsInCart);

	
	
$t->set_var("PROJECT_IDS_ARR", implode(",", $projectIDsArr));
$t->set_var("PRODUCT_IDS_ARR", implode(",", $productIDsArr));



$onlineCsrArr = ChatCSR::getCSRsOnline(Domain::getDomainIDfromURL(), array(ChatThread::TYPE_Support));
if(empty($onlineCsrArr))
	$t->set_var("CHAT_ONLINE", "false");
else 
	$t->set_var("CHAT_ONLINE", "true");


	

	
	
$passiveAuthObj = Authenticate::getPassiveAuthObject();

if($passiveAuthObj->CheckIfLoggedIn()){
	
	$loyaltyObj = new LoyaltyProgram(Domain::getDomainIDfromURL());
	
	$UserControlObj = new UserControl($dbCmd);
	$UserControlObj->LoadUserByID($passiveAuthObj->GetUserID());
	
	if($UserControlObj->getLoyaltyProgram() == "Y"){
		$t->set_var("LOYALTY_SUBTOTAL_DISCOUNT", $loyaltyObj->getLoyaltyDiscountSubtotalPercentage());
		
		// Just come up with an arbitrary weight to see if there is a shipping discount.
		if($loyaltyObj->getLoyaltyDiscountShipping(99) > 0)
			$t->set_var("LOYALTY_SHIPPING_DISCOUNT_FLAG", "true");
		else 
			$t->set_var("LOYALTY_SHIPPING_DISCOUNT_FLAG", "false");
	}
	else{
		$t->set_var("LOYALTY_SUBTOTAL_DISCOUNT", "0");
		$t->set_var("LOYALTY_SHIPPING_DISCOUNT_FLAG", "false");
	}
}
else{
	$t->set_var("LOYALTY_SUBTOTAL_DISCOUNT", "0");
	$t->set_var("LOYALTY_SHIPPING_DISCOUNT_FLAG", "false");
}




// The variable CHECKOUT_COMMAND is sent to the flash file so that knows if it should to to a secure site or not for the checkout
$websiteUrlForDomain = Domain::getWebsiteURLforDomainID(Domain::oneDomain());
$t->set_var("CHECKOUT_COMMAND", Constants::GetServerSSL() . "://$websiteUrlForDomain");


// Show the subtotal of the order for affilates. We don't want to include Postage and things.
$discDoesntApplyToThisAmnt = ProjectGroup::GetTotalFrmGrpPermDiscDoesntApply($dbCmd, $user_sessionID, "session");
$subTotalForAffiliates = $shoppingCartSubtotal - $discDoesntApplyToThisAmnt;

$t->set_var("ORDER_SUBTOTAL_FOR_AFFILIATES", WebUtil::htmlOutput($subTotalForAffiliates));


$checkoutParamsObj = new CheckoutParameters();
$t->set_var("CHECKOUT_COUPON", WebUtil::htmlOutput(strtoupper($checkoutParamsObj->GetCouponCode())));


VisitorPath::addRecord("Shopping Cart");

// Because the Thumbnail Images come from a PHP script, we want to release the session lock as soon as possible (before the User get the HTML to download thumbnails).
session_write_close();

$t->pparse("OUT","origPage");




?>