<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();


$user_sessionID =  WebUtil::GetSessionID();


// Thumbnails may take a long time to generate on very large artworks.  Give it 3 mintues.
set_time_limit(300);


// Collect all common input from URL
$command = WebUtil::GetInput("command", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$view = WebUtil::GetInput("view", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES, "shoppingcart");
$couponCodeMatches = WebUtil::GetInput("couponCodeMatches", FILTER_SANITIZE_STRING_ONE_LINE);

// Right now we are only using this API for the shopping cart.
if($view != "shoppingcart")
	_PrintXMLError("View Type is invalid.");


if($command == "Get_Project_List"){

	// Returns a List of projects in the in the user's area
	// Contains the Project ID, Product Title
	// The additional Product ID's and Full Titles are necessary with each Project Number because several Product ID's can share a Root Product ID.
	// Along with Each project it will say how many Artwork sides there are... that way if you know if an Artwork Transfer from the Backside of a Source is available.
	// It will Also Say how many sides are possible along with each Product.  We need to know this because it is not possible to transfer information to the back of Envelopes if there can not be a backside.

	
	$retXML = "<?xml version=\"1.0\" ?>\n<server_response>";
	$retXML .= "<result>OK</result>";
	
	$retXML .= "<project_list>";
	


	$dbCmd->Query("SELECT SC.ProjectRecord, PS.ProductID, PS.ArtworkFile, PS.Quantity  FROM projectssession AS PS
			INNER JOIN shoppingcart AS SC ON SC.ProjectRecord = PS.ID 
			WHERE SC.SID = '" . DbCmd::EscapeSQL($user_sessionID) . "' AND SC.DomainID=" . Domain::oneDomain());


	if($dbCmd->GetNumRows() == 0)
		_PrintXMLError("The shopping cart is empty.");


	while($row = $dbCmd->GetRow()){
		
		$ProjectRecord = $row["ProjectRecord"];
		$thisProductID = $row["ProductID"];
		$Quantity = $row["Quantity"];

		$artworkInfoObj = new ArtworkInformation($row["ArtworkFile"]);
		$numberOfArtworkSides = sizeof($artworkInfoObj->SideItemsArray);
		
		$productObj = new Product($dbCmd2, $thisProductID);

		$possibleArtworkSides = $productObj->getArtworkSidesCount();

		if($numberOfArtworkSides == 0)
			_PrintXMLError("Artwork with no Sides within API Call Get_Root_Product_IDs");

		$productObj = Product::getProductObj($dbCmd2, $thisProductID);

		$retXML .= "    <project projectRecord='" . $ProjectRecord . "'>
					<product_id>" . $thisProductID . "</product_id>
					<product_name>" . WebUtil::htmlOutput($productObj->getProductTitleWithExtention()) . "</product_name>
					<product_id_root>" . $productObj->getMainProductID() . "</product_id_root>
					<product_name_root>" . WebUtil::htmlOutput($productObj->getProductTitle()) . "</product_name_root>
					<project_quantity>" . $Quantity . "</project_quantity>
					<artwork_sides_count>" . $numberOfArtworkSides . "</artwork_sides_count>
					<artwork_sides_possible>" . $possibleArtworkSides . "</artwork_sides_possible>
				</project>
				";
	}
		

	$retXML .= "</project_list>";
	$retXML .= "</server_response>";
	
	
	header ("Content-Type: text/xml");
	_outputPragmaPublic();
	print $retXML;

}
else if($command == "Transfer_Layers_From_Side"){
	
	// This API does not return XML... it does a header redirect to a Return URL when completed.
	
	// This will allow you to transfer layers from the Side of one Artwork to the Side on a different Project.
	// You can choose to erase the layers on the target Project/Side ... or copy on top of the existing layers.
	// If the Target Side number does not exist... then it will create an additional side automatically.
	// If the Source Side Number does not exist, it will print an Error

	// "Transfer Type" can be a "CopyAll", "CopyText", "CopyGraphics" or a "ReplaceAll", "ReplaceText", "ReplaceGraphics".  A replace will get rid of all layers on the target side before transfer... and a "copy" will stack stuff on top
	// If the TargetSideNumber is "0" then you should expect a longer response because the Thumnail will need to be updated.
	// It is a good idea to always use the EyeCandy Page for calls to this API command.
	
	$SourceProjectID = WebUtil::GetInput("SourceProjectID", FILTER_SANITIZE_INT);
	$TargetProjectID = WebUtil::GetInput("TargetProjectID", FILTER_SANITIZE_INT);
	$returnurl = WebUtil::GetInput("returnurl", FILTER_SANITIZE_URL);
	
	
	// First thing is to set the application state back to General.
	// that way if they run into any weird errors or something... The flash application won't make them try to do something that it can't
	WebUtil::SetSessionVar("ArtworkTransferState", "General");

	
	// The Side Numbers should have been sent from a previous API call.
	$SourceSide = WebUtil::GetSessionVar("ArtworkTransferSourceSide");
	$TargetSide = WebUtil::GetSessionVar("ArtworkTransferTargetSide");
	
	$sourceCopyType = WebUtil::GetSessionVar("ArtworkTransferSourceCopyType");
	$targetTransferDesc = WebUtil::GetSessionVar("ArtworkTransferTargetTransferType");
	
	// Concatenate the Source Copy type and the Transfer Target Type into a String and uppercase the first letter... like "Copy" + "All";
	$TransferType = ucfirst(strtolower($targetTransferDesc)) . ucfirst(strtolower($sourceCopyType));
	
	
	if(empty($SourceSide) || empty($TargetSide))
		WebUtil::PrintError("Either the Source or the Target Side has not been set yet.");
	
	if(empty($SourceProjectID) || empty($TargetProjectID))
		WebUtil::PrintError("Either the source or the target Project ID has been left blank.");
	
	if(!in_array($TransferType, array("CopyAll", "CopyText", "CopyGraphics", "ReplaceAll", "ReplaceText", "ReplaceGraphics")))
		WebUtil::PrintError("Illegal Transfer Type in API Call Transfer_Layers_From_Side");
	

	// Convert the Side Descriptions, like "front" or "back" into Side Numbers... Side Numbers are Zero Based.
	
	if(strtoupper($SourceSide) == "FRONT")
		$SourceSideNumber = 0;
	else if(strtoupper($SourceSide) == "BACK")
		$SourceSideNumber = 1;
	else
		throw new Exception("Illegal Source Side");
	
	if(strtoupper($TargetSide) == "FRONT")
		$TargetSideNumber = 0;
	else if(strtoupper($TargetSide) == "BACK")
		$TargetSideNumber = 1;
	else
		throw new Exception("Illegal Target Side");
	
	
	
	ProjectBase::EnsurePrivilagesForProject($dbCmd, "session", $SourceProjectID);
	ProjectBase::EnsurePrivilagesForProject($dbCmd, "session", $TargetProjectID);
	
	$sourceProjectObj = ProjectBase::getProjectObjectByViewType($dbCmd, "session", $SourceProjectID);
	$targetProjectObj = ProjectBase::getProjectObjectByViewType($dbCmd, "session", $TargetProjectID);

	$sourceArtworkObj = new ArtworkInformation($sourceProjectObj->getArtworkFile());
	$targetArtworkObj = new ArtworkInformation($targetProjectObj->getArtworkFile());
	
	if(!isset($sourceArtworkObj->SideItemsArray[$SourceSideNumber])){
		WebUtil::SetSessionVar("ArtworkTransferState", "InvalidSourceArtwork");
		WebUtil::SetSessionVar("ArtworkTransferInvalidChoice", "You are trying to transfer a design from the backside of an artwork that does not have a backside.");
		header("Location: ". WebUtil::FilterURL($returnurl));
		exit;
	}
	
	

	// If the Target Side Number does not exist... then create a new side (the next highest one is usually the Backside).
	// Then the Target Side Number should be overridden with the side number that was just created.
	if(!isset($targetArtworkObj->SideItemsArray[$TargetSideNumber])){
	
		if(sizeof($targetArtworkObj->SideItemsArray) == 0)
			WebUtil::PrintError("Error in API call Transfer_Layers_From_Side.  Target Artwork does not exist.");

		// Envelopes (and possibly future products) do not have a backside to them.  So Print an error if we are trying to copy to the back.
		$productOptionsArr = $targetProjectObj->getProductOptionsArray();
		if(!array_key_exists("Style", $productOptionsArr)){

			WebUtil::SetSessionVar("ArtworkTransferState", "InvalidTargetArtwork");
			WebUtil::SetSessionVar("ArtworkTransferInvalidChoice", "You are trying to transfer artwork to the backside of a product that isn't capable of having a backside.");
	
			header("Location: ". WebUtil::FilterURL($returnurl));
			exit;
		}


		$highestSideNumber = sizeof($targetArtworkObj->SideItemsArray);
		
		// Resasign the new TargetSideNumber to be whatever the next highest Side Number is for the Artwork.
		$TargetSideNumber = $highestSideNumber;

		// Copy the information from the next highest Side Number
		$sideCopy = $targetArtworkObj->SideItemsArray[($TargetSideNumber - 1)];

		// This will erase the layers... images and text... we don't want to copy that on the back
		$sideCopy->layers = array();

		// Copy the side into artwork object
		$targetArtworkObj->SideItemsArray[$TargetSideNumber] = $sideCopy;

		// Name the second side Back (although this is a little crude).  Maybe in the future we could pull the side Name out of a function (based upon Product ID).
		// We are assumingn that it will be the Back side if they side number does not exist yet.
		$targetArtworkObj->SideItemsArray[$TargetSideNumber]->description = "Back";

		// Since the number of Sides just changed, we need to change the Project Options to match accordingly.
		$targetProjectObj->setArtworkFile($targetArtworkObj->GetXMLdoc());
		$targetProjectObj->changeProjectOptionsToMatchArtworkSides();
		$targetProjectObj->updateDatabase();

	}
	
	
	// If a layer is not "transferable" (based upon permissions) then we should not be able to copy it to another artwork
	foreach($sourceArtworkObj->SideItemsArray[$SourceSideNumber]->layers as $thisLayerObj){
		if($thisLayerObj->LayerDetailsObj->permissions->not_transferable)
			$sourceArtworkObj->RemoveLayerFromArtworkObj($SourceSideNumber, $thisLayerObj->level);
	}

	
	
	
	// Possibly Delete Text Layers on the Target if we are doing a replace.
	if($TransferType == "ReplaceAll" || $TransferType == "ReplaceText"){

		foreach($targetArtworkObj->SideItemsArray[$TargetSideNumber]->layers as $thisLayerObj){
			if($thisLayerObj->LayerType == "text"){
			
				// If the "deletion" property is set on the target artwork... then we should not allow it to be removed. 
				if($thisLayerObj->LayerDetailsObj->permissions->deletion_locked)
					continue;

				$targetArtworkObj->RemoveLayerFromArtworkObj($TargetSideNumber, $thisLayerObj->level);
			}
		}
	}
	
	// Possibly Delete Graphic Layers on the Target if we are doing a replace.
	if($TransferType == "ReplaceAll" || $TransferType == "ReplaceGraphics"){
		
		foreach($targetArtworkObj->SideItemsArray[$TargetSideNumber]->layers as $thisLayerObj){
			if($thisLayerObj->LayerType == "graphic"){
			
				// If the "deletion" property is set on the target artwork... then we should not allow it to be removed. 
				if($thisLayerObj->LayerDetailsObj->permissions->deletion_locked)
					continue;
			
				$targetArtworkObj->RemoveLayerFromArtworkObj($TargetSideNumber, $thisLayerObj->level);
			}
		}
	}
	
	


	// If we are only copying Text or Graphic Layers from the Source, then delete the ones that don't belong
	if($TransferType == "CopyText" || $TransferType == "ReplaceText"){
		
		foreach($sourceArtworkObj->SideItemsArray[$SourceSideNumber]->layers as $thisLayerObj){
			if($thisLayerObj->LayerType == "graphic")
				$sourceArtworkObj->RemoveLayerFromArtworkObj($SourceSideNumber, $thisLayerObj->level);
		}
	}
	else if($TransferType == "CopyGraphics" || $TransferType == "ReplaceGraphics"){
	
		foreach($sourceArtworkObj->SideItemsArray[$SourceSideNumber]->layers as $thisLayerObj){
			if($thisLayerObj->LayerType == "text")
				$sourceArtworkObj->RemoveLayerFromArtworkObj($SourceSideNumber, $thisLayerObj->level);
		}
	}
	
	
	// Copy the Side Number From our Source back into the Source Artwork (for the Matching Target Side Number).
	// When we do our Artwork Conversion it only takes 1 parameter for the Side Number to Transfer... so that is why they need to match.
	// We are just going to throw away the source Artwork Object after the transfer is done anyway.
	if($SourceSideNumber != $TargetSideNumber){
		
		$sourceSideObjectCopy = $sourceArtworkObj->SideItemsArray[$SourceSideNumber];
		$sourceArtworkObj->SideItemsArray[$TargetSideNumber] = $sourceSideObjectCopy;
		
		// we are doing a loop here to make sure that there are no gaps in between side numbers
		// If we are copying from Side #1 in the Source to Side #5 in the target... and the source only has one side
		// Then we don't want to have 3 empty sides on the source when we do the copy here.  It could get messed up when we get the Artwork XML back.
		for($i=0; $i<= $TargetSideNumber; $i++){
			if(!isset($sourceArtworkObj->SideItemsArray[$i]))
				$sourceArtworkObj->SideItemsArray[$i] = $sourceSideObjectCopy;
		}
	}
	
	
	
	// Use the Artwork Conversion Class to Transfer the Layers from our Source to our Target.
	$artworkConversionObj = new ArtworkConversion($dbCmd);
	$artworkConversionObj->setFromArtwork($sourceProjectObj->getProductID(), $sourceArtworkObj->GetXMLdoc());
	$artworkConversionObj->setToArtwork($targetProjectObj->getProductID(), $targetArtworkObj->GetXMLdoc());
	

	// A lot of times we want to remove the Backside during an Artwork conversion... don't do that here.
	$artworkConversionObj->removeBacksideFlag(false);
	

	// The Source Artwork may have had target Side Re-arranged to make sure it matches that Side that we plan to copy
	// Setting the target side number here will restrict the artwork transfer to just that side.
	$artworkConversionObj->setSideNumberToCopy($TargetSideNumber);
	
	// Transfer the Artwork side over and then save the Artwork back into the DB.	
	$targetProjectObj->setArtworkFile( $artworkConversionObj->getConvertedArtwork() );
	$targetProjectObj->updateDatabase();
	
	
	// In case this item was linked to a Saved Project.
	ProjectSaved::ClearSavedIDLinksByViewType($dbCmd, "session", $TargetProjectID);


	// We only need to generate a thumbnail if we are updating the First side (front) of the Artwork
	if($TargetSideNumber == 0){
		
		// Make sure that the same thumbnail is not generated more than once.
		// It is possible that the person could hit there back button many times repeatadely and cause lots of thumbnails to start loading at once, overloading the server
		if(ThumbImages::checkIfThumbnailCanBeUpdated($dbCmd, "session", $TargetProjectID)){
			ThumbImages::markThumbnailAsUpdating($dbCmd, "session", $TargetProjectID);
			ThumbImages::CreateThumnailImage($dbCmd, $TargetProjectID, "projectssession");
		}
	}

	VisitorPath::addRecord("Artwork Transfer Layers From Side");

	header("Location: ". WebUtil::FilterURL($returnurl));
	exit;

}
else if($command == "Create_New_Products_From_Artwork_Transfer"){
	
	// This API does not return XML... it does a header redirect to a Return URL when completed.
	
	// This will allow you to use the Source of 1 Project and transfer the Artwork to another Product.
	// The Target ProductID can be one or many.  Multiple Product ID's are separated by Pipe Symbols.
	// This will also create "Transfered Product Thumbnails" for the new Projects.  That does not take much time so there is no need to display an eyecandy page.
	
	$SourceProjectID = WebUtil::GetInput("SourceProjectID", FILTER_SANITIZE_INT);
	$TargetProductIDs = WebUtil::GetInput("TargetProductIDs", FILTER_SANITIZE_STRING_ONE_LINE);

	$returnurl = WebUtil::GetInput("returnurl", FILTER_SANITIZE_URL);
	
	
	// In case we run into errors or something... Make sure that the Flash application will start off from Scatch.
	WebUtil::SetSessionVar("ArtworkTransferState", "General");	
	
	if(empty($TargetProductIDs))
		WebUtil::PrintError("The target Product IDs are empty in API call Create_New_Products_From_Artwork_Transfer.");


	ProjectBase::EnsurePrivilagesForProject($dbCmd, "session", $SourceProjectID);

	$sourceProjectObj = ProjectBase::getProjectObjectByViewType($dbCmd, "session", $SourceProjectID);


	$targetActiveProductIDsArr = split("\|", $TargetProductIDs);
	
	foreach($targetActiveProductIDsArr as $thisProductID){
	
		if(empty($thisProductID))
			continue;
		
		if(!preg_match("/^\d+$/", $thisProductID))
			WebUtil::PrintError("The target Product ID is not numeric in API call Create_New_Products_From_Artwork_Transfer.");
	
		// Get a copy of this Project Object.  The copy has been converted Automatically, including the Artwork, etc.
		$convertedProjectObj = $sourceProjectObj->convertToProductID($thisProductID);
		
		// Mark the Project with an "Artwork Transfered" flag.
		$convertedProjectObj->setArtworkTransferedStatus(true);
		
		// Since we are making a copy, insert a brand new version into the DB
		$newProjectID = $convertedProjectObj->createNewProject($user_sessionID);
	
		ThumbImages::CreateThumnailImage($dbCmd, $newProjectID, "projectssession");

		// If the project is in the session then we need to add the item to the shopping cart.
		if($convertedProjectObj->getViewTypeOfProject() == "session"){
			// A message Char "T" stands for "Transfered"... so the shopping cart knows to display a message about the Artwork being transfered and needing fine tuning.
			$newShoppingCartID = $dbCmd->InsertQuery("shoppingcart", array("ProjectRecord"=>$newProjectID, "SID"=>$convertedProjectObj->getSessionID(), "DateLastModified"=>date("YmdHis"), "DomainID"=>Domain::oneDomain()));
		}
		else{
			throw new Exception("Illegal View Type in API call Create_New_Products_From_Artwork_Transfer");
		}
	
	}

	VisitorPath::addRecord("Artwork Transfer Create New Product");

	header("Location: ". WebUtil::FilterURL($returnurl));
	exit;

}
else if($command == "Set_Source_Project"){
	
	// This API does not return XML... it does a header redirect to a Return URL when completed.
	
	// This will allows you set the Source of 1 Project (saved in a Session Var)... used for a transfer somewhere else.
	// Also set the application state so that the Flash app will know that we are waiting for the Target Artwork.
	
	$SourceProjectID = WebUtil::GetInput("SourceProjectID", FILTER_SANITIZE_INT);
	
	if(!preg_match("/^\d+$/", $SourceProjectID))
		throw new Exception("Error with API Call Set_Source_Project.  Must be an integer.");

	WebUtil::SetSessionVar("ArtworkTransferSourceID", $SourceProjectID);
	WebUtil::SetSessionVar("ArtworkTransferState", "WaitingForTargetArtwork");

	// Make sure that they are not trying to select a Source Project that does not have a backside
	// ... if they are trying to transfer info from the back.
	$SourceSide = WebUtil::GetSessionVar("ArtworkTransferSourceSide");

	// Convert the Side Descriptions, like "front" or "back" into Side Numbers... Side Numbers are Zero Based.
	
	if(strtoupper($SourceSide) == "FRONT")
		$SourceSideNumber = 0;
	else if(strtoupper($SourceSide) == "BACK")
		$SourceSideNumber = 1;
	else
		throw new Exception("Illegal Source Side");

	ProjectBase::EnsurePrivilagesForProject($dbCmd, "session", $SourceProjectID);
	$sourceProjectObj = ProjectBase::getProjectObjectByViewType($dbCmd, "session", $SourceProjectID);
	$sourceArtworkObj = new ArtworkInformation($sourceProjectObj->getArtworkFile());
	
	if(!isset($sourceArtworkObj->SideItemsArray[$SourceSideNumber])){
		WebUtil::SetSessionVar("ArtworkTransferState", "InvalidSourceArtwork");
		WebUtil::SetSessionVar("ArtworkTransferInvalidChoice", "You are trying to transfer a design from the backside of an artwork that does not have a backside.");
	}

	$returnurl = WebUtil::GetInput("returnurl", FILTER_SANITIZE_URL);
	header("Location: ". WebUtil::FilterURL($returnurl));
	exit;

}
else if($command == "Get_Product_Defaults"){

	// Based upon Products in the users Shopping Cart (or wherever), this function will return the default
	// ... choices to use for "Source Artwork" Product ID... as well as the Product Destinations (in order of importance).
	// For example... if you just have a Business Card Design and a Postcard in your shopping cart... the default source is the business card
	// ... and the default destination would be a Postcards
	// This could also be extended further to analyze consumer buying patterns, etc.
	
	// Multi/Combo ProductIDs will be separated by Pipe Symbols if you want to add starter packages like ... Add "Envelopes and Letterhead" with one click
	// ... originally I had a way to support this until I moved all of the Product details into the database and it became harder to let the user pick how they want "packages" cross-sold
	// ... but for now I will leave the API alone because the first flash application I build supported multiple Destination Product ID's separated by pipe symbols... and the Product Database can later be enhanced.


	$rootProductIDsArr = _getRootProductIDsInShoppingCart($dbCmd, $user_sessionID);
	
	if(empty($rootProductIDsArr))
		_PrintXMLError("The shopping cart is empty.");
	
	
	// Now we want to organize the list of Product ID's so that the most popular Product is listed at the top.
	$rootProductsSortedByImportanceArr = array();
	
	foreach($rootProductIDsArr as $thisProductID){
		
		$productObj = new Product($dbCmd, $thisProductID);
		
		$rootProductsSortedByImportanceArr[$thisProductID] = $productObj->getProductImportance();
	}
	
	// Highest values will be on top.
	arsort($rootProductsSortedByImportanceArr);
	
	// Our default Product ID is the first one in the Array... which is the most popular.
	$defatultSourceProductID = key($rootProductsSortedByImportanceArr);
	
	

	// Build a list of Products which can be cross sold.
	// Just show all of the Cross-Sold Products from all of the Products in the Shopping Cart... even if they are not compatible.
	// If the user is stupid enough to transfer a tshirt to a business card... then that is there fault.  But more than likely a customer is only ordering one product.
	
	$allCompatibleProductsArr = array();
	
	// The list of compatible Products will be organized based upon what the Top product says is compatible.
	
	foreach($rootProductsSortedByImportanceArr as $thisProductID => $thisImportanceValue){
	
		$productObj = new Product($dbCmd, $thisProductID);
		
		$compatibleProductIDsArr = $productObj->getCompatibleProductIDsArr();
		
		// Make sure that we don't add any duplicates.
		foreach($compatibleProductIDsArr as $thisCompatibleProductID){
			
			if(!in_array($thisCompatibleProductID, $allCompatibleProductsArr))
				$allCompatibleProductsArr[] = $thisCompatibleProductID;
		}
	}
	
	

	$retXML = "<?xml version=\"1.0\" ?>\n<server_response>";
	$retXML .= "<result>OK</result>";
	$retXML .= "<default_source_product>" . $defatultSourceProductID . "</default_source_product>";
	
	$retXML .= "<product_destinations>"; 
	
	foreach($allCompatibleProductsArr as $thisCrossSellProductID)
		$retXML .= "<product_selection productIDs=\"" . $thisCrossSellProductID . "\">" . WebUtil::htmlOutput(Product::getRootProductName($dbCmd, $thisCrossSellProductID)) . "</product_selection>";
	
	$retXML .= "</product_destinations>"; 

	$retXML .= "</server_response>";
	
	header ("Content-Type: text/xml");
	_outputPragmaPublic();
	print $retXML;


}
else if($command == "Shopping_Cart_Subtotal"){
	
	// Will return the Subtotal for the shopping cart.
	// Also returns a CouponCode if the order is qualified to use it.
	// May return also return a Permanent Discount too.
	// This API call will only return a permanent Discount or a Coupon Code.  It will not return Both.
	// The system prefers to send back a coupon code over a permanent discount.

	$shoppingCartSubtotal = ShoppingCart::GetShopCartSubTotal($dbCmd, $user_sessionID, 0);

	$rootProductIDsArr = _getRootProductIDsInShoppingCart($dbCmd, $user_sessionID);
	
	if(empty($rootProductIDsArr))
		_PrintXMLError("The shopping cart is empty.");


	$couponCode = "";

	// Find out if the Application wants us to hunt for Coupon Code matches based upon the Product ID's in their shopping cart.
	// The application needs to pass in a "couponCodeMatches" name/value into this API request with a special nomenclature.
	// If it find a match (based upon Product ID requirments)... it will return the Summary of the Coupon as well as the percentage.
	// This approach gives the most flexibility for administrators in chaning descriptions and discount percentages from the coupon adminstration screen.
	// It will also be able to compare if the user has permanent discount which is greater than the Coupon
	if(!empty($couponCodeMatches)){
	
		// The Format of the couponCodeMatches name value/pair is as values.
		// Carrot Symbols ^ separate groups of CouponNames/ProductIDs
		// Product ID's are separated by Pipe Symbols
		// The Coupon Code Name is separated from its Product ID's with an Asteric.
		// The order of the grouping is important... as it will find the first matches.
		// Here is an example of a request
		
		// http://www.domain/api.php?command=Shopping_Cart_Subtotal&couponCodeMatches=Business%20Starter*73|84|80^Stationery*84|80
		
		// In this case... if the user has all 3 product ID's 73, 84, and 80 ... this API will try to load the "Business Starter" coupon.
		// .... if they don't have those 3... then it will see if they have products 84 and 80 in their shopping cart.  If they do the coupon "Stationery" will be loaded
		// The coupon Codes MUST exist in the system or the API will complain.
		
		$couponCodeGroupsArr = explode("^", $couponCodeMatches);
		
		if(sizeof($couponCodeGroupsArr) <= 1)
			_PrintXMLError("Problem with the couponCodeMatches name/value pair. A carrot symbol was not found.");
			
		foreach($couponCodeGroupsArr as $thisCouponCodeGroup){
		
			$couponAndProductPartsArr = explode("*", $thisCouponCodeGroup);
			
			if(sizeof($couponCodeGroupsArr) <= 1)
				_PrintXMLError("Problem with the couponCodeMatches name/value pair. An * sybmol was not found within one of the Groupings.");
				
			
			// The coupon name is the one before the *.
			$couponCodeNameOfGroup = $couponAndProductPartsArr[0];
			
			// Products come after the Astrick.
			$productIDsInCouponGroupArr = explode("|", $couponAndProductPartsArr[1]);
			
			
			$allProductsFoundFlag = true;
			$atLeastOneProductIDfound = false;
			
			foreach($productIDsInCouponGroupArr as $thisProductIDtoCheck){
			
				$thisProductIDtoCheck = trim($thisProductIDtoCheck);
				if(empty($thisProductIDtoCheck))
					continue;
					
				$atLeastOneProductIDfound = true;
				
				if(!in_array($thisProductIDtoCheck, $rootProductIDsArr))
					$allProductsFoundFlag = false;
			}
			
			
			// Use the first coupon that matches and break out of our checking routine.
			if($atLeastOneProductIDfound && $allProductsFoundFlag){
				$couponCode = trim($couponCodeNameOfGroup);
				break;
			}
		}
	}



	if(empty($couponCode)){
		$couponDescription = "";
	}
	else{
		$couponCodeObj = new Coupons($dbCmd);
		if(!$couponCodeObj->CheckIfCouponCodeExists($couponCode))
			_PrintXMLError("Error in API Command Shopping_Cart_Subtotal.  The Coupon Does not exist: " . $couponCode);
		
		$couponCodeObj->LoadCouponByCode($couponCode);
		$couponDescription = $couponCodeObj->GetSummaryOfCoupon();
	}

	// If the person is logged in, and there is not a coupon set from above... then check for a permanent discount
	$permanentDiscountPercent = "";
	$permanentDiscountName = "";
	
	if(empty($couponCode) && Authenticate::CheckIfVisitorLoggedIn($dbCmd)){

		$AuthObj = new Authenticate(Authenticate::login_general);
		$UserID = $AuthObj->GetUserID();
		
		$userControlObj = new UserControl($dbCmd);
		$userControlObj->LoadUserByID($UserID);
		
		if($userControlObj->checkForActiveDiscount()){
			$permanentDiscountPercent = round($userControlObj->getAffiliateDiscount() * 100);
			$permanentDiscountName = $userControlObj->getAffiliateName();
			
			// The permanent Discount can be altered by certain Product Options for which the Discount does not apply.
			$discDoesntApplyToThisAmnt = ProjectGroup::GetTotalFrmGrpPermDiscDoesntApply($dbCmd, $user_sessionID, "session");
			$permanentDiscountPercent = ShoppingCart::GetAdjustedPermanentDiscount($dbCmd, $shoppingCartSubtotal, $discDoesntApplyToThisAmnt, $permanentDiscountPercent);
			
		}
	}
	
	WebUtil::SetCookie("CouponDynamic", $couponCode);


	$retXML = "<?xml version=\"1.0\" ?>\n<server_response>";
	$retXML .= "<result>OK</result>";
	$retXML .= "<subtotal>" . number_format($shoppingCartSubtotal, 2) . "</subtotal>";
	$retXML .= "<coupon_code>" . $couponCode . "</coupon_code>";
	$retXML .= "<coupon_description>" . WebUtil::htmlOutput($couponDescription) . "</coupon_description>";
	$retXML .= "<permanent_discount_percent>" . $permanentDiscountPercent . "</permanent_discount_percent>";
	$retXML .= "<permanent_discount_name>" . WebUtil::htmlOutput($permanentDiscountName) . "</permanent_discount_name>";
	$retXML .= "</server_response>";
	
	header ("Content-Type: text/xml");
	_outputPragmaPublic();
	print $retXML;

}
else if($command == "Get_Current_State"){

	// The Flash application may be in a number of different states.  It could be in the middle of an operation (between page refreshes) that sets the Source and Target Project ID's
	// Or it could be the first time the application has loaded (based upon session variables)... so we know to play a Start-up animation or whatever.
	// This should be the first thing that the Flash application requests before it decides what to display to the user.
	// See the API command "Set_Current_State" for a list of all possible return values.
	// Default value is "First" if called for the first time.

	// An invalid source/target "application state" should always describe why it is invalid.
	$invalidArtworkChoice = WebUtil::GetSessionVar("ArtworkTransferInvalidChoice", "");
	
	$currentApplicationState = WebUtil::GetSessionVar("ArtworkTransferState", "First");

	// Once we have seen the application for the first time, change the application state to a general
	if($currentApplicationState == "First")
		WebUtil::SetSessionVar( "ArtworkTransferState", "General");
	
	
	// Doing a Copy, Delete, Or Change Option will tell us to do a QuickStart on the shopping cart.
	// This would have been set from a Shopping Cart action.  If we find that variable wipe it out (so it doesn't keep repeating)
	// Then set the ArtworkTransferState to a "QuickStart".
	if(WebUtil::GetSessionVar("ShoppingCartQuickStart")){
		$currentApplicationState = "QuickStart";
		WebUtil::SetSessionVar( "ShoppingCartQuickStart", "");
	}
	

	// If we have multiple items in the Shopping cart... and we want to use one of the artworks as the "Source" for an artwork operation and one for the "Target"
	// ... this variable will tell us which Project ID is for the Source.
	// Because we don't have reliable 2-way flash communication... We require the user to click on the Shopping Cart Thumbnail (in HTML) which will set a Sesssion Variable and reload the page.
	// This API call may or may not return a ProjectID... if a blank node is returned instead of a ProjectID then it means that it has not been set.
	$SourceID = WebUtil::GetSessionVar("ArtworkTransferSourceID");


	$retXML = "<?xml version=\"1.0\" ?>\n<server_response>";
	$retXML .= "<result>OK</result>";
	$retXML .= "<state>" . $currentApplicationState . "</state>";
	$retXML .= "<invalid_artwork_message>" . WebUtil::htmlOutput($invalidArtworkChoice) . "</invalid_artwork_message>";
	$retXML .= "<source_id>" . $SourceID . "</source_id>";
	$retXML .= "</server_response>";
	
	header ("Content-Type: text/xml");
	_outputPragmaPublic();
	print $retXML;
}
else if($command == "Set_Current_State"){

	// Similar to "Get_Current_State"... but it Sets the Session Variable instead.
	
	$legalStates = array("First", "General", "WaitingForTargetArtwork", "InvalidTargetArtwork", "InvalidSourceArtwork");
	
	$state = WebUtil::GetInput("state", FILTER_SANITIZE_STRING_ONE_LINE);
	
	if(!in_array($state, $legalStates))
		_PrintXMLError("Illegal State in the command Set_Current_State.");
		
	WebUtil::SetSessionVar("ArtworkTransferState", $state);

	$retXML = "<?xml version=\"1.0\" ?>\n<server_response>";
	$retXML .= "<result>OK</result>";
	$retXML .= "</server_response>";
	
	header ("Content-Type: text/xml");
	_outputPragmaPublic();
	print $retXML;
}
else if($command == "Set_Transfer_Options"){

	// The application will allow the user to select if they want to copy the Front of one artwork to the Back of another (let's say).
	// They can also say if they want to replace all text layers, or copy all graphics, etc.
	
	$legalSides = array("FRONT", "BACK");
	
	$SourceSide = WebUtil::GetInput("SourceSide", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
	$TargetSide = WebUtil::GetInput("TargetSide", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
	
	if(!in_array(strtoupper($SourceSide), $legalSides) || !in_array(strtoupper($TargetSide), $legalSides))
		_PrintXMLError("Illegal Side Desciption for either the source or the target.");
		
	WebUtil::SetSessionVar("ArtworkTransferSourceSide", $SourceSide);
	WebUtil::SetSessionVar("ArtworkTransferTargetSide", $TargetSide);


	$SourceCopyType = WebUtil::GetInput("SourceCopyType", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
	$TargetTransferType = WebUtil::GetInput("TargetTransferType", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
	
	if(!in_array(strtoupper($SourceCopyType), array("ALL", "TEXT", "GRAPHICS")))
		_PrintXMLError("Illegal Source Copy Type in API call Set_Transfer_Options.");
		
	if(!in_array(strtoupper($TargetTransferType), array("COPY", "REPLACE")))
		_PrintXMLError("Illegal TargetTransferType Type in API call Set_Transfer_Options.");
	
	WebUtil::SetSessionVar("ArtworkTransferSourceCopyType", $SourceCopyType);
	WebUtil::SetSessionVar("ArtworkTransferTargetTransferType", $TargetTransferType);



	$retXML = "<?xml version=\"1.0\" ?>\n<server_response>";
	$retXML .= "<result>OK</result>";
	$retXML .= "</server_response>";
	
	header ("Content-Type: text/xml");
	_outputPragmaPublic();
	print $retXML;
}
else if($command == "Clear_Source_Project_ID"){

	WebUtil::SetSessionVar("ArtworkTransferSourceID", "");

	// In case you want to back out of an Artwork transfer in the middle you can call this API to erase the Session Vars for Source and Target ProjectID's
	$retXML = "<?xml version=\"1.0\" ?>\n<server_response>";
	$retXML .= "<result>OK</result>";
	$retXML .= "</server_response>";
	
	header ("Content-Type: text/xml");
	_outputPragmaPublic();
	print $retXML;


}
else{
	_PrintXMLError("Invalid Command.");

}





#####---------------     Private Functions for this script   -----------------######


function _PrintXMLError($ErrorMessage){
	header ("Content-Type: text/xml");
	
	_outputPragmaPublic();
	
	$returnXML = "<?xml version=\"1.0\" ?>\n<server_response><result>ERROR</result><error_message>" . WebUtil::htmlOutput($ErrorMessage) . "</error_message></server_response>";
	print $returnXML;
	exit;
}


function _getRootProductIDsInShoppingCart(DbCmd $dbCmd, $user_sessionID){

	$allProductIDsArr = array();
	$rootProductIDsArr = array();
	
	$dbCmd->Query("SELECT DISTINCT ProductID FROM projectssession 
			INNER JOIN shoppingcart ON shoppingcart.ProjectRecord = projectssession.ID 
			WHERE shoppingcart.SID = '" . DbCmd::EscapeSQL($user_sessionID) . "' AND shoppingcart.DomainID=" . Domain::oneDomain());
	while($thisProductID = $dbCmd->GetValue())
		$allProductIDsArr[] = $thisProductID;
	
	foreach($allProductIDsArr as $thisProductID){
		$productObj = Product::getProductObj($dbCmd, $thisProductID);
		$rootProductIDsArr[] = $productObj->getMainProductID();
	}
	
	$rootProductIDsArr = array_unique($rootProductIDsArr);
	
	return $rootProductIDsArr;
}


function _outputPragmaPublic(){

	// It seems that when you hit session_start it will send a Pragma: NoCache in the header
	// When comminicating over HTTPS there is a bug with IE.  It can not get the flash documents after they have finished downloading because they have already expired
	// We can allow the file to be cached be sending this header.  When requesting the document you can put a ?noache=timestamp
	// This is the only way to get flash communication to work over HTTPS with session variables
	header("Pragma: public");
}


?>