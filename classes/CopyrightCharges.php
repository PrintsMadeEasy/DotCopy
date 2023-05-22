<?
class CopyrightCharges {

	
	private $_dbCmd;
	private $_orderNumber;
	private $_copyrightChargeAmount;
	private $_userControlObj;
	private $_userID;
	private $_projectIDsToChargeArr = array();
	private $_domainID;




	##--------------   Constructor  ------------------##

	function CopyrightCharges(DbCmd $dbCmd, $orderNumber){
	
		$this->_dbCmd = $dbCmd;
		
		$orderNumber = intval($orderNumber);
		
		$this->_orderNumber = $orderNumber;
		
		$this->_dbCmd->Query("SELECT UserID FROM orders WHERE ID=" . $orderNumber);
		
		if($this->_dbCmd->GetNumRows() == 0)
			throw new Exception("Error in CopyrightCharges constructor.  The OrderID is not valid: " . $orderNumber);
			
		$this->_domainID = Order::getDomainIDFromOrder($orderNumber);
			
		$this->_userID = $this->_dbCmd->GetValue();
			
		$this->_userControlObj = new UserControl($dbCmd);
		
		$this->_userControlObj->LoadUserByID($this->_userID, false);

		$this->_copyrightChargeAmount = self::getChargeAmountForDomain($this->_domainID);
	}
	
	// Decide whether to display a copyright option to the customer
	// UserID is not required, but pass it in if you have it.
	static function displayCopyrightOptionForVisitor($userID = null){
		
		if(!Domain::getCopywriteChargeFlagForDomain(Domain::getDomainIDfromURL()))
			return false;
		
		$userID = intval($userID);
		$dbCmd = new DbCmd();
			
		// If a user has ever been enrolled in the past, make sure that they always have the option to re-enroll.
		if(!empty($userID)){
			
			// Don't allow the copyright program to customers with "corporate billing".
			$dbCmd->Query("SELECT BillingType FROM users WHERE ID=" . intval($userID));
			if($dbCmd->GetValue() != "N")
				return false;
	
			$dbCmd->Query("SELECT COUNT(*) FROM balanceadjustments INNER JOIN orders ON orders.ID = balanceadjustments.OrderID 
						WHERE orders.UserID=" . intval($userID) . " AND Description LIKE 'Copyright%'");
			if($dbCmd->GetValue() > 0)
				return true;

			$dbCmd->Query("SELECT LoyaltyProgram FROM users WHERE ID=" . intval($userID));
			if($dbCmd->GetValue() == "Y")
				return true;

		}
		
		if(IPaccess::checkIfUserIpHasSomeAccess())
			return true;
		
		$userIpAddress = WebUtil::getRemoteAddressIp();
		
		$maxMindObj = new MaxMind();
		if(!$maxMindObj->loadIPaddressForLocationDetails($userIpAddress))
			return false;
		
		if($maxMindObj->checkIfOpenProxy())
			return false;
			
		// Find out how many sessions have been establised at all domains (starting more than 3 days ago).
		// If the loyalty program turns out to greatly assist our customer retention, we don't want to reveal program details to a competitor.
		$daysBackToStartCountingVisits = 7;
		$dbCmd->Query("SELECT COUNT(*) FROM visitorsessiondetails USE INDEX(visitorsessiondetails_IPaddress)
						WHERE IPaddress = '" . DbCmd::EscapeLikeQuery($userIpAddress) . "'
						AND DateStarted < '" . DbCmd::FormatDBDateTime(time() - (60 * 60 * 24 * $daysBackToStartCountingVisits)) . "'");
		$totalSessionsByIP = $dbCmd->GetValue();
		
		if($totalSessionsByIP > 3)
			return false;	
			
		return true;
	}
	
	static function getChargeAmountForDomain($domainID){
		
		$domainKey = Domain::getDomainKeyFromID($domainID);
		
		if(in_array($domainKey, array("Postcards.com", "LuauInvitations.com")))
			return "25.00";
		else if(in_array($domainKey, array("PrintsMadeEasy.com")))
			return "39.95";
		else if(in_array($domainKey, array("BusinessCards24.com")))
			return "39.95";
		else
			return "19.95";
	}
	
	
	// Returns TRUE if there is at least one project in the User's shopping cart that we should charge for copyright permissions.
	// Takes into account Domain permissions, and everything else.
	static function checkForCopyrightChargeInShoppingCart(){
		
		$projectIDsToChargeFromCart = self::getProjectSessionIDsInShoppingCartToCharge();
		
		if(empty($projectIDsToChargeFromCart))
			return false;
		else
			return true;
	}
	

	// This is meant to look ahead and figure out if the user will be charged for copyright if they end up placing an order.
	// The user has to be logged in for this method to work.
	// It will make sure that the user does not get charged twice in they are placing a repeat order.
	// Returns an empty array if the user should not be charged.
	static function getProjectSessionIDsInShoppingCartToCharge(){
		
		// Find out if this domain does copyright charges.
		if(!Domain::getCopywriteChargeFlagForDomain(Domain::getDomainIDfromURL()))
			return array();
		
		$dbCmd = new DbCmd();
		$sessionID = WebUtil::GetSessionID();
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		$userID = $passiveAuthObj->GetUserID();
		
		$userControlObj = new UserControl($dbCmd);
		$userControlObj->LoadUserByID($userID);
		
		if($userControlObj->getCopyrightTemplates() == "N")
			return array();
		
		$dbCmd->Query("SELECT ProjectRecord FROM shoppingcart where SID=\"". $sessionID . "\" AND shoppingcart.DomainID=".Domain::getDomainIDfromURL());
		$projectIDsInShoppingCart = $dbCmd->GetValueArr();
			
		// Get a list of project IDs from the users's Shopping Cart that deserve copyright based upon the Image ID's and Template Area.
		$projectIDsThatDeserveArr = array();
		foreach($projectIDsInShoppingCart as $thisProjectID){
			if(self::checkIfProjectTemplateDeservesCopyrightCharge($thisProjectID, "session"))
				$projectIDsThatDeserveArr[] = $thisProjectID;
		}
		
			
		// Now Find out the Template IDs associated with each Project have ever aquired copyright protection in the past. 
		// If we have ever charged them copyright permissions for a template in the past, don't do it again.
		$projectSessionIDsToChargeArr = array();
		
		foreach($projectIDsThatDeserveArr as $thisProjectID){
			
			$orderIDchargedForCopyright = self::getOrderIDofProjectChargedForCopyright($thisProjectID, "session", $userID);
		
			if(!empty($orderIDchargedForCopyright))
				continue;
				
			$projectSessionIDsToChargeArr[] = $thisProjectID;
		}
		
		return $projectSessionIDsToChargeArr;
	}
	
	
	// Will check to see if the Artwork belonging to the ProjectID came from a template... and it will also make sure they they didn't delete all of the template images.
	// It does not take into account whether the Project has been purchased in the past.
	static function checkIfProjectTemplateDeservesCopyrightCharge($projectID, $viewType){
	
		$dbCmd = new DbCmd();
		
		$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, $viewType, $projectID, false);
		
		$templateFromArea = $projectObj->getFromTemplateArea();
		$templateFromID = $projectObj->getFromTemplateID();
		
		// The template must come from the Template Categories or the Template Search Engine
		if(!in_array($templateFromArea, array("C", "S")))
			return false;
		
		if(empty($templateFromID))
			return false;
			
		// Now make sure that they didn't delete all of the Images that were uploaded by the graphic artist who created the template.
		$artworkInfoObj = new ArtworkInformation($projectObj->getArtworkFile());
		
		// If there is a single image on either the front or the back that was uploaded by a graphic artist in the template area then this Project deserves copyright protection.
		for($i=0; $i<sizeof($artworkInfoObj->SideItemsArray); $i++){
		
			foreach($artworkInfoObj->SideItemsArray[$i]->layers as $thisLayerObj){
			
				if($thisLayerObj->LayerType != "graphic")
					continue;
				
				if(ImageLib::CheckIfImageBelongsToTemplateCollection($dbCmd, $thisLayerObj->LayerDetailsObj->imageid))
					return true;
			}
		}
		
		return false;
	}
	
	
	// If the User has paid for copyright charges on a project in the past.
	// This will return the Order ID that the Project Was in.
	// Returns NULL is the Template (belonging to the Project) was never charged for a copyright in the past.
	static function getOrderIDofProjectChargedForCopyright($projectID, $viewType, $userID){
		
		$dbCmd = new DbCmd();
		
		$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, $viewType, $projectID, false);
		
		$templateFromArea = $projectObj->getFromTemplateArea();
		$templateFromID = $projectObj->getFromTemplateID();
		
		$dbCmd->Query("SELECT OrderID FROM projectsordered INNER JOIN orders ON projectsordered.OrderID = orders.ID 
					WHERE orders.UserID=" . intval($userID) . "
					AND projectsordered.FromTemplateID=" . $templateFromID . " 
					AND projectsordered.FromTemplateArea='" . $templateFromArea . "'
					AND projectsordered.CopyrightPurchase='Y'");
					
		$orderID = $dbCmd->GetValue();
		
		return $orderID;
	}
	
	
	

	// Even if they have many unique templates in this order, it will charge them for only 1 copyright permission.
	// If an Authorization has already gone through for copyright charges on this order then it will return FALSE (it won't charge them twice).
	// If the user preferences of the user to not wish to purchase copyright protection then it will return FALSE
	// If they have already purchased copright permissions on all of the templates in the past then it will return FALSE
	// If the templates have all of the original template layers deleted in then it will return FALSE.
	function orderShouldBeChargedForCopyright(){
	
		// Make sure the user hasn't opted out of this program
		if($this->_userControlObj->getCopyrightTemplates() == "N")
			return false;
		
		// Find out if this domain does copyright charges.
		if(!Domain::getCopywriteChargeFlagForDomain($this->_domainID))
			return false;
			
		// We can not do this for Paypal customers
		if(Order::getOrderBillingType($this->_orderNumber) == "P")
			return false;
		
		// Find out if a copyright purchase has already been made for this order.
		$this->_dbCmd->Query("SELECT COUNT(*) FROM projectsordered WHERE OrderID=" . $this->_orderNumber . " AND CopyrightPurchase='Y'");
		$purchaseCountOnThisOrder = $this->_dbCmd->GetValue();
		
		if($purchaseCountOnThisOrder > 0)
			return false;
		
		
		$projectIDsToChargeArr = $this->getProjectIDsToChargeForThisOrder();
		
		if(empty($projectIDsToChargeArr))
			return false;
		else
			return true;
	}
	
	
	
	// This does Not take into account where the user has opted out of the Copyright program.
	// Returns an array of Project IDs that have not been associated with a copyright purchase before this order.
	// In case a project ID has a templateID associated with a ProjectID in the past (with a purchase)... that ProjectID will not be included in the list.
	// It will also check to see if the images belonging to the original template have all been deleted... if so it will not include that ProjectID in the list.
	// But with new Projects that get charged on this order.... even after the purchase... those ProjectID's will continue to be returned by this method (against the this order number) in the future.
	function getProjectIDsToChargeForThisOrder(){
	
		// In case this Method is called twice within the Object scope then just returned a Cached copy.
		if(!empty($this->_projectIDsToChargeArr))
			return $this->_projectIDsToChargeArr;
	
		// Get a list of all projects in this order.
		$projectIDsInThisOrderArr = $this->getProjectIDsInThisOrder();
			
		// Get a list of project IDs in this order that deserve copyright protection
		$projectIDsThatDeserveArr = array();
		foreach($projectIDsInThisOrderArr as $thisProjectID){
		
			if(self::checkIfProjectTemplateDeservesCopyrightCharge($thisProjectID, "ordered"))
				$projectIDsThatDeserveArr[] = $thisProjectID;
		}
		
		
		// Now Find out the Template IDs associated with each Project.  Find out if they have ever aquired copyright protection in the past. 
		// If so, don't include them
		$this->_projectIDsToChargeArr = array();
		
		foreach($projectIDsThatDeserveArr as $thisProjectID){
			
			$orderIDchargedForCopyright = self::getOrderIDofProjectChargedForCopyright($thisProjectID, "ordered", $this->_userID);
		
			// If the project has never been charged before... then we can charge it now.
			// Or if the Project to Charge is in the current order that is OK.  
			// This method will always return the Projects in this order that were supposed to be charged (after the order is complete).
			if(empty($orderIDchargedForCopyright) || $orderIDchargedForCopyright == $this->_orderNumber)
				$this->_projectIDsToChargeArr[] = $thisProjectID;
		
		}
		
		return $this->_projectIDsToChargeArr;
	}
	
	

	

	
	// Returns an array of Project IDs
	function getProjectIDsInThisOrder(){
		
		$projectIDsInThisOrderArr = array();
		$this->_dbCmd->Query("SELECT ID FROM projectsordered WHERE OrderID=" . $this->_orderNumber . " AND Status != 'C'");
		while($pid = $this->_dbCmd->GetValue())
			$projectIDsInThisOrderArr[] = $pid;
		
		return $projectIDsInThisOrderArr;
		
	}
	
	
	

	
	// Will charge the customers credit card and record in the database what Projects were charged.
	// It is OK to call this method even if the user shouldn't be charged.
	function possiblyChargeAndSendEmail(){
	
		// Prevent duplicate charges
		if(!$this->orderShouldBeChargedForCopyright())
			return;
			
		// Find out if this domain does copyright charges.
		if(!Domain::getCopywriteChargeFlagForDomain($this->_domainID))
			return;
			
		$balanceAdjustmentObj = new BalanceAdjustment($this->_orderNumber);
		
		// A User ID of Zero means the "system".
		if(!$balanceAdjustmentObj->chargeCustomerWithCapture($this->_copyrightChargeAmount, 0, "Copyright Permissions on Template")){
			WebUtil::WebmasterError("Error trying to capture Funds on a Template Copyright");
			return;
		}

		// Mark each Project in the database that was purchased
		$projectIDsPurchased = $this->getProjectIDsToChargeForThisOrder();
		
		$whereQuery = "";
		foreach($projectIDsPurchased as $thisProjectID){
		
			if(!empty($whereQuery))
				$whereQuery .= " OR ";
			$whereQuery .= "ID=" . $thisProjectID;
		}
		
		$this->_dbCmd->UpdateQuery("projectsordered", array("CopyrightPurchase"=>"Y"), $whereQuery);
		
		
		$this->sendEmailToUser();
	}
	
	
	// Call this method after putting the order through.  Will construct the email and send it off
	// You can call this method to repeatdly send the user an email.. even if they have already paid.
	// You can also force a ProjectID. If you do this... it will send a copyright for that Project... whether or not if deserves copyright or not.
	//	... If you force a Project ID... it must exist within the order.  This will not charge the customer.
	function sendEmailToUser($overrideSendName = null, $overrideSendEmail = null, $forecEmailForProjectID = null){
			
		// If you are forcing a Project ID... then the array of ProjectIDs to send the email for is just the single ProjectID.
		if(!empty($forecEmailForProjectID)){
			
		
			if(!in_array($forecEmailForProjectID, $this->getProjectIDsInThisOrder()))
				throw new Exception("Error in method Copyright:sendEmailToUser ... The Forced Project ID does not exist in the order.");
			
			$projectIDsPurchased = array($forecEmailForProjectID);
		}
		else{
			$projectIDsPurchased = $this->getProjectIDsToChargeForThisOrder();
		}
		
		
		
		
		if(empty($projectIDsPurchased))
			throw new Exception("Error in method sendEmailToUser.  There are no Projects in this Order that deserve a copyright purchase.");
			
		// Build a distinct list of ImageID's
		$artworkImageIDs = array();
		
		// Contains a path to temporary images on disk.
		$artworkImageFileNamesArr = array(); 
		
		// Build a list of Project IDs that are built from Unique Templates
		// For example, if someone orders 100 versions of the same Template, we don't want to send the person an email of images of all 100 versions.  We will just pick the first one in the list.
		$projectArtworksFromUniqueTemplatesArr = array();
		$templateIDsAlreadyIncludedArr = array();
		
		$uniqueGraphicsProjectIDlistArr = array();
		$frontSideSignaturesRecorded = array();
		
		foreach($projectIDsPurchased as $thisProjectPurchased){
		
			$projectObj = ProjectOrdered::getObjectByProjectID($this->_dbCmd, $thisProjectPurchased);
			
			$templateFromID = $projectObj->getFromTemplateID();
			
			// Make sure only the first project in a sequence of Template "Versions" gets included
			if(!in_array($templateFromID, $templateIDsAlreadyIncludedArr)){
				$templateIDsAlreadyIncludedArr[] = $templateFromID;
				$projectArtworksFromUniqueTemplatesArr[] = $thisProjectPurchased;
			}
			
			
			$imageIdsInThisArtworkArr = array();

			$artworkInfoObj = new ArtworkInformation($projectObj->getArtworkFile());
			
			// There should always be a Front to the Artwork
			if(!isset($artworkInfoObj->SideItemsArray[0]))
				continue;

			for($i=0; $i<sizeof($artworkInfoObj->SideItemsArray); $i++){
			
				$dpi_Ratio = $artworkInfoObj->SideItemsArray[$i]->dpi / 96;

				foreach($artworkInfoObj->SideItemsArray[$i]->layers as $thisLayerObj){

					if($thisLayerObj->LayerType != "graphic")
						continue;

					if(!in_array($thisLayerObj->LayerDetailsObj->imageid, $artworkImageIDs)){
						
						$artworkImageIDs[] = $thisLayerObj->LayerDetailsObj->imageid;
						
						$LayerWidth = round($thisLayerObj->LayerDetailsObj->width * $dpi_Ratio);
						$LayerHeight = round($thisLayerObj->LayerDetailsObj->height * $dpi_Ratio);
						
						// This function call will load the Image from the Database and return a temporary file name.
						$SingleImageTempFile = ImageLib::LoadImageByID($this->_dbCmd, $thisLayerObj->LayerDetailsObj->imageid, $LayerWidth, $LayerHeight, $thisLayerObj->rotation);
						
						// Apply an extention to the Image if we know what it is.
						$singleFileFormat = ImageLib::GetImageFormatFromFile($SingleImageTempFile);
						

						if($singleFileFormat == "PNG")
							$copySuffix = ".png";
						else if($singleFileFormat == "JPEG")
							$copySuffix = ".jpg";
						else
							$copySuffix = "";
							
						// Create a copy of the file that we loaded by reading it into memory and writing it back to disk.
						$imageCopyForUser = FileUtil::newtempnam (Constants::GetTempDirectory(), "/Image_", $copySuffix);

						// Open the Image Data 
						$fd = fopen ($SingleImageTempFile, "r");
						$BinaryData = fread ($fd, filesize ($SingleImageTempFile));
						fclose ($fd);

						// Make the Copy 
						$fp = fopen($imageCopyForUser, "w");
						fwrite($fp, $BinaryData);
						fclose($fp);
						
						$artworkImageFileNamesArr[] = $imageCopyForUser;


					}

					$imageIdsInThisArtworkArr[] = $thisLayerObj->LayerDetailsObj->imageid;
				}
			}
			
			
			// If somebody order 1 design with many names... we don't want to send them copies of each name.
			// However, if somebody order many different templates... then we want to send them a rasterized version for each one.
			// We can figure this out by taking the Front Side of each project in the order and deleting all Text layers
			// Then we can compare the MD5 value of each artwork to determine if they are unique.
			$imageIdsInThisArtworkArr = array_unique($imageIdsInThisArtworkArr);
			sort($imageIdsInThisArtworkArr);
			$graphicsOnlySignature = md5(serialize($imageIdsInThisArtworkArr));			
			
			if(!in_array($graphicsOnlySignature, $frontSideSignaturesRecorded))
				$uniqueGraphicsProjectIDlistArr[] = $thisProjectPurchased;
			
			$frontSideSignaturesRecorded[] = $graphicsOnlySignature;
		}
		
		
		$artworkImageIDs = array_unique($artworkImageIDs);
		
		
		// These will contain paths to temporary images on text.
		$fontSideArtworksWithTextArr = array();
		$fontSideArtworksNoTextArr = array();
		$backSideArtworksWithTextArr = array();
		$backSideArtworksNoTextArr = array();
		
		
		// If someone orders 100 templates with the same graphics... different names... this will only generate "Merged Version" for the first copy.
		foreach($projectArtworksFromUniqueTemplatesArr as $thisProjectArtworkID){
			
			$projectObj = ProjectOrdered::getObjectByProjectID($this->_dbCmd, $thisProjectArtworkID);
			
			$artworkInfoObj = new ArtworkInformation($projectObj->getArtworkFile());
			
			
			$maxImageWidth = 1000;
			$maxImageHeight = 1000;
			$imageQuality = 90;
			
			$theSideNumber = 0;
			
			$fontSideArtworksWithTextArr[] = ArtworkLib::GetArtworkImageWithText($this->_dbCmd, $theSideNumber, $artworkInfoObj, "N", 0, 0, false, false, array(), $maxImageWidth, $maxImageHeight, $imageQuality);
			
			// Sometimes people just use text and no graphics... don't send them a white image.
			$imageIDsOnFrontArr = $artworkInfoObj->getRasterImageIDsFromSide($theSideNumber);
			if(!empty($imageIDsOnFrontArr)){
				$artworkInfoObj->RemoveLayerTypeFromSide($theSideNumber, "text");

				$fontSideArtworksNoTextArr[] = ArtworkLib::GetArtworkImageWithText($this->_dbCmd, $theSideNumber, $artworkInfoObj, "N", 0, 0, false, false, array(), $maxImageWidth, $maxImageHeight, $imageQuality);
			}
			
		
			// Generate the Backside, if there is one.
			if(isset($artworkInfoObj->SideItemsArray[1])){
			
				$theSideNumber = 1;
			
				$backSideArtworksWithTextArr[] = ArtworkLib::GetArtworkImageWithText($this->_dbCmd, $theSideNumber, $artworkInfoObj, "N", 0, 0, false, false, array(), $maxImageWidth, $maxImageHeight, $imageQuality);

				// Sometimes people just use text and no graphics... don't send them a white image.
				$imageIDsOnBackArr = $artworkInfoObj->getRasterImageIDsFromSide($theSideNumber);
				if(!empty($imageIDsOnBackArr)){
					$artworkInfoObj->RemoveLayerTypeFromSide($theSideNumber, "text");

					$backSideArtworksNoTextArr[] = ArtworkLib::GetArtworkImageWithText($this->_dbCmd, $theSideNumber, $artworkInfoObj, "N", 0, 0, false, false, array(), $maxImageWidth, $maxImageHeight, $imageQuality);
				}
			}
		}
		
		
		
		$MimeObj = new Mail_mime();
		
		
		// Load the email template off of disk so we can substiute the variables.
		$emailTemplateName = Domain::getDomainSandboxPath(Domain::getDomainKeyFromID($this->_domainID)) . "/email_copyright.html"; 
		
		if(!file_exists($emailTemplateName)){
			WebUtil::WebmasterError("Error: The email template for Copyrights was not found.");
			return;
		}
		
		$fd = fopen ($emailTemplateName, "r");
		$templateHTML = fread ($fd, filesize ($emailTemplateName));
		fclose ($fd);

		
		$backgroundImageName = "background-purple-grad.jpg";
		$backgroundImagePath = Domain::getDomainSandboxPath(Domain::getDomainKeyFromID($this->_domainID)) . "/images/" . $backgroundImageName;
	

		if(file_exists($backgroundImagePath)){
			$MimeObj->addHTMLImage($backgroundImagePath, 'image/jpg');
			$inline_background_JPG = "cid:" . $MimeObj->getLastHTMLImageCid();
		}
		else{
			$inline_background_JPG = "";
		}
		
		

		// ----  Build a list of all Images with Text.
		$imagesWithTextHTML = "";
		
		foreach($fontSideArtworksWithTextArr as $thisArtFileName){
			$MimeObj->addHTMLImage($thisArtFileName, 'image/jpg');
			$inlineImage_FrontWithText_JPG = "cid:" . $MimeObj->getLastHTMLImageCid();
			$imagesWithTextHTML .= "<img src='" . $inlineImage_FrontWithText_JPG . "' border='1'><br><br>";
			@unlink($thisArtFileName);
		}
		
		
		foreach($backSideArtworksWithTextArr as $thisArtFileName){
			$MimeObj->addHTMLImage($thisArtFileName, 'image/jpg');
			$inlineImage_BackWithText_JPG = "cid:" . $MimeObj->getLastHTMLImageCid();
			$imagesWithTextHTML .= "<img src='" . $inlineImage_BackWithText_JPG . "' border='1'><br><br>";
			@unlink($thisArtFileName);
		}
		// ------------------------------------
		
		
		
		

		// ---- Build a list of all Images with NO Text.
		$imagesWithoutTextHTML = "";
		
		foreach($fontSideArtworksNoTextArr as $thisArtFileName){
			$MimeObj->addHTMLImage($thisArtFileName, 'image/jpg');
			$inlineImage_FrontNoText_JPG = "cid:" . $MimeObj->getLastHTMLImageCid();
			$imagesWithoutTextHTML .= "<img src='" . $inlineImage_FrontNoText_JPG . "' border='1'><br><br>";
			@unlink($thisArtFileName);
		}
		
		
		foreach($backSideArtworksNoTextArr as $thisArtFileName){
			$MimeObj->addHTMLImage($thisArtFileName, 'image/jpg');
			$inlineImage_BackNoText_JPG = "cid:" . $MimeObj->getLastHTMLImageCid();
			$imagesWithoutTextHTML .= "<img src='" . $inlineImage_BackNoText_JPG . "' border='1'><br><br>";
			@unlink($thisArtFileName);
		}
		// ------------------------------------



		
		// Now add all of the individual images for HTML
		$imagesLayeredHTML = "";
		
		foreach($artworkImageFileNamesArr as $thisArtFileName){
		
			if(ImageLib::GetImageFormatFromFile($thisArtFileName) == "PNG"){
				$imagesLayeredHTML .= "<font size='-1'><i>The following image name is in PNG format.  It may contain transparent areas that allow objects in the background to show through.<br></i></font><br>";
				
				$MimeObj->addHTMLImage($thisArtFileName, 'image/png');
				$lastLayer_PNG = "cid:" . $MimeObj->getLastHTMLImageCid();
				$imagesLayeredHTML .= "<img src='" . $lastLayer_PNG . "' border='1'><br><br>";
			}
			else{
				$MimeObj->addHTMLImage($thisArtFileName, 'image/jpg');
				$lastLayer_JPG = "cid:" . $MimeObj->getLastHTMLImageCid();
				$imagesLayeredHTML .= "<img src='" . $lastLayer_JPG . "' border='1'><br><br>";
			}
		

			@unlink($thisArtFileName);
		}

		
		
		
		
		// Substitute the Background Image
		$templateHTML = preg_replace("/{BACKGROUND_IMAGE}/", $inline_background_JPG, $templateHTML);
		
		// Substitute the Merged Layers with Text
		$templateHTML = preg_replace("/{IMAGES_WITH_TEXT}/", $imagesWithTextHTML, $templateHTML);
		
		// Substitute the Merged Layers without Text
		$templateHTML = preg_replace("/{IMAGES_NO_TEXT}/", $imagesWithoutTextHTML, $templateHTML);
		
		// Substitute all of the Individual Images
		$templateHTML = preg_replace("/{SEPARTED_LAYERS}/", $imagesLayeredHTML, $templateHTML);
		
		// The domain name for this customer
		$templateHTML = preg_replace("/{DOMAIN_KEY}/", Domain::getDomainKeyFromID($this->_domainID), $templateHTML);
		
		// Show the order number that this is relating to.
		$templateHTML = preg_replace("/{ORDERNO}/", Order::GetHashedOrderNo($this->_orderNumber), $templateHTML);
		
		
		$domainEmailConfigObj = new DomainEmails($this->_domainID);
		
		$MimeObj->setHTMLBody($templateHTML);
		$MimeObj->setSubject("Your Artwork [DO NOT REPLY]");
		$MimeObj->setFrom($domainEmailConfigObj->getEmailNameOfType(DomainEmails::ARTWORK) . "<" . $domainEmailConfigObj->getEmailAddressOfType(DomainEmails::ARTWORK) .">");

		$body = $MimeObj->get();
		$hdrs = $MimeObj->headers();


		
		// Change the headers and return envelope information for the SendMail command.
		// We don't want emails from different domains to look like they are coming from the same mail server.
		$hdrs["Message-Id"] =  "<" . substr(md5(uniqid(microtime())), 0, 15) . "@" . Domain::getDomainKeyFromID($this->_domainID) . ">";
		$additionalSendMailParameters = "-r " . $domainEmailConfigObj->getEmailAddressOfType(DomainEmails::ARTWORK);
		
		
		$mailObj = new Mail();
		
		$personName = $this->_userControlObj->getName();
		$userEmail = $this->_userControlObj->getEmail();
		
		
		// Outlook doesn't recognize it as an inline (but as attachemnt) if we have an @domain.com in the Content-ID: !!! mine.php generates cid@domian.com other mailers just cid
		$body = preg_replace("/%40" . preg_quote(Domain::getDomainKeyFromID($this->_domainID)) . "/i", "", $body);
		
		if(!empty($overrideSendName) || !empty($overrideSendEmail)){
		
			if(!WebUtil::ValidateEmail($overrideSendEmail))
				throw new Exception("Error with Email address in method CopyrightCharges->sendEmailToUser");
				
			
			$mailObj->send(($overrideSendName . " <$overrideSendEmail>"), $hdrs, $body, $additionalSendMailParameters);
		
		}
		else{
		
			$mailObj->send(($personName . " <$userEmail>"), $hdrs, $body);
			//$mailObj->send((Constants::GetAdminName() ." <".Constants::GetAdminEmail().">"), $hdrs, $body, $additionalSendMailParameters);

		}

		unset($MimeObj);
		unset($mailObj);
	
	}



}



?>