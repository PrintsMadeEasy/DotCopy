<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();


$AuthPassvObj = Authenticate::getPassiveAuthObject();	


// Give it at least 5 mintues to generate a large artwork
set_time_limit(300);



$user_sessionID =  WebUtil::GetSessionID();

$api_version = WebUtil::GetInput("api_version", FILTER_SANITIZE_STRING_ONE_LINE);
$command = WebUtil::GetInput("command", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$projectNumber = WebUtil::GetInput("project_number", FILTER_SANITIZE_INT);
$viewType = WebUtil::GetInput("view_type", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$productID = WebUtil::GetInput("product_id", FILTER_SANITIZE_INT);
$projectList = WebUtil::GetInput("project_id_list", FILTER_SANITIZE_STRING_ONE_LINE);  // Pipe-deliminated list of Project IDs
$projectQuantity = WebUtil::GetInput("project_quantity", FILTER_SANITIZE_INT);
$projectOptions = WebUtil::GetInput("project_options", FILTER_SANITIZE_STRING_ONE_LINE);
$converToProductID = WebUtil::GetInput("conver_to_product_id", FILTER_SANITIZE_INT);
$transferToProductID = WebUtil::GetInput("transfer_to_product_id", FILTER_SANITIZE_INT);
$includeVendors = WebUtil::GetInput("includeVendors", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$destinationProjectViewType = WebUtil::GetInput("destination_project_view_type", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$templateId = WebUtil::GetInput("template_id", FILTER_SANITIZE_INT);
$templateArea = WebUtil::GetInput("template_area", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);




function _PrintXMLError($ErrorMessage){
	session_write_close();
	header("Pragma: public");
	header ("Content-Type: text/xml");
	$errorXML = "<?xml version=\"1.0\" ?>\n<response>\n<api_version>1.1</api_version>\n<result>ERROR</result>\n<error_message>" . WebUtil::htmlOutput($ErrorMessage) . "</error_message>\n</response>";
	print $errorXML;
	exit;
}


// For session thumbnails... the thumbnail security code is just the SessionID.  For saved projects it is a digestive hash of the UserID and some salt.
function _getThumbnailSC(DbCmd $dbCmd, Authenticate $AuthPassvObj, $viewType){

	if($viewType == "session" || $viewType == "projectssession"){
		return WebUtil::GetSessionID();
	}
	else if($viewType == "saved" || $viewType == "ordered"){

		if(!$AuthPassvObj->CheckIfLoggedIn())
			_PrintXMLError("User is not logged in.");

		// The user ID that we want to use for the Saved Project might belong to somebody else (because they are overriding the Saved Project View).
		$UserID = ProjectSaved::GetSavedProjectOverrideUserID($AuthPassvObj);
		
		return md5($UserID . Constants::getGeneralSecuritySalt());
	}
	else{
		_PrintXMLError("View Type is illegal for the Project List.");
		return false; // avoid compiler error
	}
}

// For the thumbnail modified string... we can use a timestamp of the last date modified on Session Projects.
// On Saved Projects... it would be convienient to just use the "DateModified" instead of doing the whole md5 caclculation... but sometimes we save the artwork without updating the dateModified... like Clipboard... or the Saved Project link
function _getThumbnailModified(ProjectBase $projectObj){

	if($projectObj->getViewTypeOfProject() == "session" || $projectObj->getViewTypeOfProject() == "projectssession"){
		return $projectObj->getDateLastModified();
	}
	else if($projectObj->getViewTypeOfProject() == "saved" || $projectObj->getViewTypeOfProject() == "ordered"){
		return md5($projectObj->getArtworkFile());
	}
	else{
		_PrintXMLError("View Type is illegal for the Project List.");
		return false; // avoid compiler error
	}
}


function _authenticateProjectID(DbCmd $dbCmd, Authenticate $AuthPassvObj, $projectID, $viewType){


	if($viewType == "session" || $viewType == "projectssession"){
		if(!ProjectSession::CheckIfUserOwnsProjectSession($dbCmd, $projectID))
			_PrintXMLError("Project is not available anymore: " . $projectID);
	}
	else if($viewType == "saved" || $viewType == "projectssaved"){

		if(!$AuthPassvObj->CheckIfLoggedIn())
			_PrintXMLError("User is not logged in.");

		// The user ID that we want to use for the Saved Project might belong to somebody else (because they are overriding the Saved Project View).
		$UserID = ProjectSaved::GetSavedProjectOverrideUserID($AuthPassvObj);

		if(!ProjectSaved::CheckIfUserOwnsSavedProject($dbCmd, $projectID, $UserID))
			_PrintXMLError("Project is not available anymore: " . $projectID);
	}
	else if($viewType == "ordered"){

		if(!$AuthPassvObj->CheckIfLoggedIn())
			_PrintXMLError("User is not logged in.");

		if($AuthPassvObj->CheckIfUserIDisMember($AuthPassvObj->GetUserID())){
			
			// Make sure that the member/admin is able to view the domain
			$domainIDofProject = ProjectBase::getDomainIDofProjectRecord($dbCmd, "ordered", $projectID);
			if(!$AuthPassvObj->CheckIfUserCanViewDomainID($domainIDofProject))
				_PrintXMLError("The Project is trying to be loaded by an administrator but the project is not found: " . $projectID);
		}
		else{
			// For regular users, the UserID logged in must match the user ID in the database identical.
			if(!ProjectOrdered::CheckIfUserOwnsOrderedProject($dbCmd, $projectID, $AuthPassvObj->GetUserID()))
				_PrintXMLError("Project is not available anymore: " . $projectID);
		}
	}
	else{
		_PrintXMLError("View Type is illegal for the Project List.");
	}
	
	// Make sure that if any subsequent modules call Domain::oneDomain()... that they don't have to get redirected to domain selection window.
	Domain::enforceTopDomainID(ProjectBase::getDomainIDofProjectRecord($dbCmd, $viewType, $projectID));
}


if($api_version != "1.1"){
	_PrintXMLError("The API Version is invalid.");
}





header ("Content-Type: text/xml");

// It seems that when you hit session_start it will send a Pragma: NoCache in the header
// When comminicating over HTTPS there is a bug with IE.  It can not get the flash documents after they have finished downloading because they have already expired
// We can allow the file to be cached be sending this header.  When requesting the document you can put a ?noache=timestamp
// This is the only way to get flash communication to work over HTTPS with session variables
header("Pragma: public");



if($command == "get_project_list"){

	$projectArr = split("\|", $projectList);
	
	
	$responseProjectListXML = "<?xml version=\"1.0\" ?>
	<response>
	<result>OK</result>
	<api_version>1.1</api_version>
	<error_message></error_message>
	<projects>
	";
	
	
	
	foreach($projectArr as $thisProjectID){
	
		if(!preg_match("/\d+/", $thisProjectID))
			continue;

		// Make sure the user has permission to view this Project, otherwise it will print an XML failure.
		_authenticateProjectID($dbCmd, $AuthPassvObj, $thisProjectID, $viewType);
		
		$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, $viewType, $thisProjectID);
		
		$responseProjectListXML .= "<project>
				<project_view>".$viewType."</project_view>
				<project_id>".$thisProjectID."</project_id>
				<form_security_code>".WebUtil::getFormSecurityCode()."</form_security_code>
				<date_modified>".$projectObj->getDateLastModified()."</date_modified>
				<thumbnail_sc>"._getThumbnailSC($dbCmd, $AuthPassvObj, $viewType)."</thumbnail_sc>
				<thumbnail_cache>"._getThumbnailModified($projectObj)."</thumbnail_cache>
				<product_id>".$projectObj->getProductID()."</product_id>
				<quantity>".$projectObj->getQuantity()."</quantity>
				<options>".$projectObj->getOptionsDescription()."</options>
				<variable_data_status>".$projectObj->getVariableDataStatus()."</variable_data_status>
				<variable_data_message>".WebUtil::htmlOutput($projectObj->getVariableDataMessage())."</variable_data_message>
				<template_id>".$projectObj->getFromTemplateID()."</template_id>
				<template_area>".$projectObj->getFromTemplateArea()."</template_area>
				<artwork_is_copied>".($projectObj->checkIfArtworkCopied() ? "yes":"no")."</artwork_is_copied>
				<artwork_is_transfered>".($projectObj->checkIfArtworkTransfered() ? "yes":"no")."</artwork_is_transfered>
				";
		
		
				// Determine if the Project is in the shopping cart.
				$projectIsInCartFlag = false;
				if($viewType == "session"){
					$projectSesionIdsInCart = ShoppingCart::GetProjectSessionIDsInShoppingCart($dbCmd, $user_sessionID);
					if(in_array($thisProjectID, $projectSesionIdsInCart))
						$projectIsInCartFlag = true;
				}
				else if($viewType == "saved"){
					$projectSessionIDsThatAreSavedArr = ShoppingCart::GetProjectSessionIDsInShoppingCartThatAreSaved($dbCmd, $thisProjectID, $user_sessionID);
					if(!empty($projectSessionIDsThatAreSavedArr))
						$projectIsInCartFlag = true;
				}
				$responseProjectListXML .= "<project_in_cart>".($projectIsInCartFlag ? "yes":"no")."</project_in_cart>";
				
				
		
				// If the user selectd a template (versus uploading), find out if there are any templates linked to it.
				if($projectObj->getFromTemplateID() != 0 && in_array($projectObj->getFromTemplateArea(), array(TemplateLinks::TEMPLATE_AREA_CATEGORY, TemplateLinks::TEMPLATE_AREA_SEARCH_ENGINE))){
					$templateLinksObj = new TemplateLinks();
					
					$productsLinkedArr = $templateLinksObj->getProductIdsLinkedToThis($projectObj->getFromTemplateArea(), $projectObj->getFromTemplateID());
					if(!empty($productsLinkedArr)){
						
						$productsLinkedArr = Product::sortProductIdsByImportance($productsLinkedArr);
						
						$responseProjectListXML .= "<template_links>\n";
						foreach($productsLinkedArr as $thisProductIdLinked){
							$responseProjectListXML .= "<product>\n";
							$responseProjectListXML .= "<product_id_linked>$thisProductIdLinked</product_id_linked>\n";
							$templateLinkedHashArr = $templateLinksObj->getTemplatesLinkedToThis($projectObj->getFromTemplateArea(), $projectObj->getFromTemplateID(), $thisProductIdLinked);
							
							$linkedTemplateProductObj = new Product($dbCmd, $thisProductIdLinked, false);
							
							
							foreach($templateLinkedHashArr as $thisTemplateId => $thisTemplateArea){
								
								$sideNamesArr = array();
								$templatePreviewIdsArr = array();
								$templatePreviewWidthsArr = array();
								$templatePreviewHeightsArr = array();
								
								$previewColumnName = ($thisTemplateArea == TemplateLinks::TEMPLATE_AREA_SEARCH_ENGINE) ? "SearchEngineID" : "TemplateID";

								$dbCmd->Query("SELECT ID, Width, Height FROM artworkstemplatespreview WHERE $previewColumnName = $thisTemplateId ORDER BY ID ASC");
								
								$previewCounter = 0;
								while($row = $dbCmd->GetRow()){
									
									$previewCounter++;
									
									// We may have chosen only to display the 'F'irst side of template previews.  In which case, we will override the tempalte preview count to just one.
									if($previewCounter >= 2 && $linkedTemplateProductObj->getTemplatePreviewSidesDisplay() == "F")
										continue;
										
									$sideNamesArr[] = $linkedTemplateProductObj->getArtworkSideDescription($previewCounter);
									$templatePreviewIdsArr[] = $row["ID"];
									$templatePreviewWidthsArr[] = $row["Width"];
									$templatePreviewHeightsArr[] = $row["Height"];
								}
								
								
								$responseProjectListXML .= "<template>\n";
								$responseProjectListXML .= "<template_link_id>$thisTemplateId</template_link_id>\n";
								$responseProjectListXML .= "<template_link_area>$thisTemplateArea</template_link_area>\n";
								$responseProjectListXML .= "<template_link_preview_ids>".implode("|", $templatePreviewIdsArr)."</template_link_preview_ids>\n";
								$responseProjectListXML .= "<template_link_side_names>".htmlspecialchars(implode("|", $sideNamesArr))."</template_link_side_names>\n";
								$responseProjectListXML .= "<template_link_preview_widths>".htmlspecialchars(implode("|", $templatePreviewWidthsArr))."</template_link_preview_widths>\n";
								$responseProjectListXML .= "<template_link_preview_heights>".htmlspecialchars(implode("|", $templatePreviewHeightsArr))."</template_link_preview_heights>\n";
								$responseProjectListXML .= "</template>\n";
							}
							$responseProjectListXML .= "</product>\n";
						}
						$responseProjectListXML .= "</template_links>\n";
					}
				}
				
				// Show any links between this Project and a Saved Project.
				if($viewType == "session" || $viewType == "ordered")
					$responseProjectListXML .= "<project_saved_link>".$projectObj->getSavedID()."</project_saved_link>\n";


				// Saved Projects are the root.  They may have 0 to many relationships to orders placed, and items in the shopping cart.				
				if($viewType == "saved"){
				
					// If this is a Saved Project then we need to show 0 or Many Links to items in the user's shopping cart.
					$projectSessionIDsThatAreSavedArr = ShoppingCart::GetProjectSessionIDsInShoppingCartThatAreSaved($dbCmd, $thisProjectID, $user_sessionID);
					
					$responseProjectListXML .= "<project_session_links>\n";
					foreach($projectSessionIDsThatAreSavedArr as $thisSessionProjectID)
						$responseProjectListXML .= "<project_session_link>".$thisSessionProjectID."</project_session_link>\n";
					$responseProjectListXML .= "</project_session_links>\n";
					
					
					// If this is a Saved Project then we need to show 0 or Many Links to orders placed using this saved project. (not finished or canceled).
					$projectOrderedIDsThatLinkedArr = ProjectSaved::GetProjectOrderedIDsLinkedToSavedProject($dbCmd, $thisProjectID);
	
					$responseProjectListXML .= "<project_ordered_links>\n";
					foreach($projectOrderedIDsThatLinkedArr as $thisProjectOrderedID)
						$responseProjectListXML .= "<project_ordered_link>".$thisProjectOrderedID."</project_ordered_link>\n";
					$responseProjectListXML .= "</project_ordered_links>\n";
				}
				
				
				// Ordered Projects have more details associated with them.
				if($viewType == "ordered"){
				
					// You should use the Subtotal from the Databse for ordered projects instead of calculating through the Product Object, because prices could have changed since the order was placed.
					$responseProjectListXML .= "<order_id>" . WebUtil::htmlOutput(Order::GetHashedOrderNo($projectObj->getOrderID())) ."</order_id>
								<arrival_date>".$projectObj->getEstArrivalDate()."</arrival_date>
								<project_discount>".$projectObj->getCustomerDiscount()."</project_discount>
								<project_subtotal>".$projectObj->getCustomerSubtotal_DB()."</project_subtotal>
								<project_tax>".$projectObj->getCustomerTax()."</project_tax>
								<status_char>".$projectObj->getStatusChar()."</status_char>
								<status_description>".WebUtil::htmlOutput(ProjectStatus::GetProjectStatusDescription($projectObj->getStatusChar()))."</status_description>
								";
								
					if(in_array($projectObj->getStatusChar(), ProjectStatus::getStatusCharactersCanStillEditArtwork()))
						$responseProjectListXML .= "<can_edit_artwork_still>yes</can_edit_artwork_still>\n";
					else
						$responseProjectListXML .= "<can_edit_artwork_still>no</can_edit_artwork_still>\n";
				}
				
				
				
		
		$responseProjectListXML .= "</project>\n";
	}
	
	$responseProjectListXML .= "</projects>
				</response>
				";

	session_write_close();
	print $responseProjectListXML;
}
else if($command == "get_product_definition"){

	if(!Product::checkIfProductIDisActive($dbCmd, $productID))
		_PrintXMLError("Error Loading Product Definition. Product ID does not exist: " . $productID);
		

	// Find out if we want to include Vendor Names and pricing within the Product Defintion.
	$includeVendorsFlag = false;
	if(!empty($includeVendors)){
		$AuthPassvObj->EnsureMemberSecurity();
		$includeVendorsFlag = true;
	}
		
	// Make sure the Product is in the same Domain.
	$domainIDofProduct = Product::getDomainIDfromProductID($dbCmd, $productID);
	
	if(!$AuthPassvObj->CheckIfUserCanViewDomainID($domainIDofProduct))
		_PrintXMLError("Error Loading Product Definition in URL. Product ID does not exist: " . $productID);
		

	$productObj = new Product($dbCmd, $productID);
	
	
	// Let the javascript API control the Caching by changing the URL
	header('Date: '. gmdate('D, d M Y H:i:s') . ' GMT');
	header("Expires: " . gmdate("D, d M Y H:i:s", (time() + 60 * 60 * 7)) . " GMT");  // Tell the browser to cache for at least 7 days (if Javascript doesn't change the URL
	header("Cache-Control: store, cache");
	header("Pragma: public");
	
	
	print "<?xml version=\"1.0\" ?>
	<response>
	<result>OK</result>
	<api_version>1.1</api_version>
	<error_message></error_message>
	<product_definition>
	";

	print "
		<product_id>".$productObj->getProductID()."</product_id>
		<product_title>".htmlspecialchars($productObj->getProductTitle())."</product_title>
		<product_title_ext>".htmlspecialchars($productObj->getProductTitleExtension())."</product_title_ext>
		<product_name>".htmlspecialchars($productObj->getProductTitleWithExtention())."</product_name>
		<unit_weight>".$productObj->getProductUnitWeight()."</unit_weight>
		<base_price>".$productObj->getBasePriceCustomer()."</base_price>
		<initial_subtotal>".$productObj->getInitialSubtotalCustomer()."</initial_subtotal>
		<variable_data>".($productObj->isVariableData() ? "yes":"no")."</variable_data>
		<mailing_services>".($productObj->hasMailingService() ? "yes":"no")."</mailing_services>
		<artwork_width_inches>".$productObj->getArtworkCanvasWidth()."</artwork_width_inches>
		<artwork_height_inches>".$productObj->getArtworkCanvasHeight()."</artwork_height_inches>
		<artwork_bleed_picas>".$productObj->getArtworkBleedPicas()."</artwork_bleed_picas>
		<artwork_sides_possible_count>".$productObj->getArtworkSidesCount()."</artwork_sides_possible_count>
		<artwork_is_editable>".($productObj->getArtworkIsEditable() ? "yes" : "no")."</artwork_is_editable>
		<thumbnail_background_exists>".($productObj->checkIfThumbnailBackSaved() ? "yes":"no")."</thumbnail_background_exists>
		<thumbnail_copy_icon_exists>".($productObj->checkIfThumbnailCopyIconSaved() ? "yes":"no")."</thumbnail_copy_icon_exists>
		<product_importance>".$productObj->getProductImportance()."</product_importance>
		<compatible_product_ids>".implode("|", $productObj->getCompatibleProductIDsArr())."</compatible_product_ids>
		";
		
		
		// Try to approximate the Thumbnail size. We can't get perfect through... because older projects may have different thumbnail images stored in the tables.
		if($productObj->checkIfThumbnailBackSaved()){
			print "<thumbnail_width_approximate>".$productObj->getThumbBackgroundWidth()."</thumbnail_width_approximate>
				<thumbnail_height_approximate>".$productObj->getThumBackgroundbHeight()."</thumbnail_height_approximate>
			";
		}
		else{
			print "<thumbnail_width_approximate>".$productObj->getThumbWidth()."</thumbnail_width_approximate>
				<thumbnail_height_approximate>".$productObj->getThumbHeight()."</thumbnail_height_approximate>
			";
		}
		
		// List the Vendor Names and Intial Subtotals/Base Prices, separated by @ symbols.
		if($includeVendorsFlag){
			
			$vendorNamesStr = "";
			$vendorBasePricesStr = "";
			$vendorIntialSubtotalsPricesStr = "";
			
			for($i=1; $i<=6; $i++){
				
				if($productObj->getVendorID($i) == null)
					$vendorNamesStr .= "None@";
				else
					$vendorNamesStr .= WebUtil::htmlOutput(UserControl::GetCompanyByUserID($dbCmd, $productObj->getVendorID($i))) . "@";
					
				$vendorBasePricesStr .= $productObj->getBasePriceVendor($i) . "@";
				$vendorIntialSubtotalsPricesStr .= $productObj->getInitialSubtotalVendor($i) . "@";
			}
			
			print "<vendor_names>" . htmlspecialchars(WebUtil::chopChar($vendorNamesStr)) . "</vendor_names>\n";
			print "<vendor_initial_subtotals>" . WebUtil::chopChar($vendorIntialSubtotalsPricesStr) . "</vendor_initial_subtotals>\n";
			print "<vendor_base_prices>" . WebUtil::chopChar($vendorBasePricesStr) . "</vendor_base_prices>\n";
		}
		
		
		
		$quanBrkArr = $productObj->getQuantityPriceBreaksArr();
		$quanPriceBreaksBase = "";
		$quanPriceBreaksSubs = "";
		$vendorQuanPriceBreaksBase = "";
		$vendorQuanPriceBreaksSubs = "";
		
		foreach($quanBrkArr as $quanBreakObj){
		
			$quanPriceBreaksBase .= $quanBreakObj->amount . "^" . $quanBreakObj->PriceAdjustments->CustomerBaseChange . "|";
			$quanPriceBreaksSubs .= $quanBreakObj->amount . "^" . $quanBreakObj->PriceAdjustments->CustomerSubtotalChange . "|";
			
			// Include Vendor Quantity Price breaks the if the user has requestet (and are an admin).
			if($includeVendorsFlag){
				
				$vendorQuanPriceBreaksBase .= $quanBreakObj->amount . "^"; 
				$vendorQuanPriceBreaksSubs .= $quanBreakObj->amount . "^";
				
				// Separate the 6 vendor prices with an @ symbol.
				for($i=1; $i<=6; $i++){
					$vendorQuanPriceBreaksBase .= $quanBreakObj->PriceAdjustments->getVendorBaseChange($i) . "@";
					$vendorQuanPriceBreaksSubs .= $quanBreakObj->PriceAdjustments->getVendorSubtotalChange($i) . "@";
				}
				
				$vendorQuanPriceBreaksBase = WebUtil::chopChar($vendorQuanPriceBreaksBase);
				$vendorQuanPriceBreaksSubs = WebUtil::chopChar($vendorQuanPriceBreaksSubs);
				
				$vendorQuanPriceBreaksBase .= "|";
				$vendorQuanPriceBreaksSubs .= "|";
			}
		
		}
		
	print "<main_quantity_price_breaks_base>".WebUtil::chopChar($quanPriceBreaksBase)."</main_quantity_price_breaks_base>
		<main_quantity_price_breaks_subtotal>".WebUtil::chopChar($quanPriceBreaksSubs)."</main_quantity_price_breaks_subtotal>
		";
		
	if($includeVendorsFlag){
		print "<main_vendor_quantity_price_breaks_base>".WebUtil::chopChar($vendorQuanPriceBreaksBase)."</main_vendor_quantity_price_breaks_base>
			<main_vendor_quantity_price_breaks_subtotal>".WebUtil::chopChar($vendorQuanPriceBreaksSubs)."</main_vendor_quantity_price_breaks_subtotal>
			";
	}
	
	print"	<default_quantity>".$productObj->getDefaultQuantity()."</default_quantity>

		<product_switches>
		";
		
		$productSwitchesArr = $productObj->getProductSwitchIDs();
		
		$productSwitchXML = "";
		
		$lastProductSwitchTitle = "";
		$lastProductIDwithNewSwitchTitle = 0;
		
		foreach($productSwitchesArr as $thisProdSwitchID){

			$productSwitchXML .= "<product_switch>\n";
			$productSwitchXML .= "<product_id_target>" . $thisProdSwitchID  . "</product_id_target>\n";
			$productSwitchXML .= "<product_title_target>" . WebUtil::htmlOutput(Product::getFullProductName($dbCmd, $thisProdSwitchID))  . "</product_title_target>\n";
			$productSwitchXML .= "<product_switch_description>" . WebUtil::htmlOutput($productObj->getProductSwitchDescription($thisProdSwitchID))  . "</product_switch_description>\n";
			$productSwitchXML .= "<product_switch_description_is_html>" . ($productObj->getProductSwitchDescriptionIsHTML($thisProdSwitchID) ? "yes":"no")  . "</product_switch_description_is_html>\n";
			$productSwitchXML .= "<product_switch_title>" . WebUtil::htmlOutput($productObj->getProductSwitchTitle($thisProdSwitchID))  . "</product_switch_title>\n";
			$productSwitchXML .= "<product_switch_link_subject>" . WebUtil::htmlOutput($productObj->getProductSwitchLinkSubject($thisProdSwitchID))  . "</product_switch_link_subject>\n";

			if($lastProductSwitchTitle == $productObj->getProductSwitchTitle($thisProdSwitchID)){
				$productSwitchXML .= "<grouped_to_product_id>" . $lastProductIDwithNewSwitchTitle  . "</grouped_to_product_id>\n";
				
				// This can't be the group head since the title matches the one before it.  But the one before it could be the group head.
				$productSwitchXML .= "<group_head>no</group_head>\n";
			}
			else{
				// Since the Title is different from the last Title... then this is not grouped to anything above it.
				$productSwitchXML .= "<grouped_to_product_id>0</grouped_to_product_id>\n";
				
				
				
				// Since we are on a New Title... it is possible that we could be at the Group head
				// If this Product is in a Group... then it must be the Group head.
				if($productObj->isProductSwitchInGroup($thisProdSwitchID))
					$productSwitchXML .= "<group_head>yes</group_head>\n";
				else
					$productSwitchXML .= "<group_head>no</group_head>\n";
				
				$lastProductIDwithNewSwitchTitle = $thisProdSwitchID;
			}
			
			$lastProductSwitchTitle = $productObj->getProductSwitchTitle($thisProdSwitchID);
			
			$productSwitchXML .= "</product_switch>\n";
		}
		
	print $productSwitchXML;

		   
	print "	</product_switches>
		<options>
		";
		
		$productOptionsArr = $productObj->getProductOptionsArr();
		
		foreach($productOptionsArr as $thisOptionObj){
		
			print "<option>	
			<option_name>" . WebUtil::htmlOutput($thisOptionObj->optionName) . "</option_name>
			<option_alias>" . WebUtil::htmlOutput($thisOptionObj->optionNameAlias) . "</option_alias>
			<option_description>" . WebUtil::htmlOutput($thisOptionObj->optionDescription) . "</option_description>
			<option_description_is_html>" . ($thisOptionObj->optionDescriptionIsHTMLformat ? "yes":"no") . "</option_description_is_html>
			<option_affects_artwork_sides>" . ($thisOptionObj->artworkSidesController ? "yes":"no") . "</option_affects_artwork_sides>
			<option_is_admin>" . ($thisOptionObj->adminOptionController ? "yes":"no") . "</option_is_admin>
			<option_discount_exempt>" . ($thisOptionObj->couponDiscountExempt ? "yes":"no") . "</option_discount_exempt>
			<option_variable_image_controller>" . ($thisOptionObj->variableImageController ? "yes":"no") . "</option_variable_image_controller>
			<choices>
			";
			
			$defaultChoiceForThisOption = $productObj->getDefaultChoiceForOption($thisOptionObj->optionName);
			
			$optionChoicesArr = $thisOptionObj->choices;
			
			foreach($optionChoicesArr as $thisChoiceObj){
			
			
				print "<choice>
				<choice_name>" . WebUtil::htmlOutput($thisChoiceObj->ChoiceName) . "</choice_name>
				<choice_alias>" . WebUtil::htmlOutput($thisChoiceObj->ChoiceNameAlias) . "</choice_alias>
				<choice_description>" . WebUtil::htmlOutput($thisChoiceObj->ChoiceDescription) . "</choice_description>
				<choice_description_is_html>" . ($thisChoiceObj->ChoiceDescriptionIsHTMLformat ? "yes":"no") . "</choice_description_is_html>
				<choice_is_hidden>" . ($thisChoiceObj->hideOptionAllFlag ? "yes":"no") . "</choice_is_hidden>
				<change_artwork_sides>" . ($thisOptionObj->artworkSidesController ? $thisChoiceObj->artworkSideCount : "0") . "</change_artwork_sides>
				<choice_base_price_change>" . ($thisChoiceObj->PriceAdjustments->CustomerBaseChange) . "</choice_base_price_change>
				<choice_subtotal_change>" . ($thisChoiceObj->PriceAdjustments->CustomerSubtotalChange) . "</choice_subtotal_change>
				<choice_base_weight_change>" . ($thisChoiceObj->BaseWeightChange) . "</choice_base_weight_change>
				<choice_project_weight_change>" . ($thisChoiceObj->ProjectWeightChange) . "</choice_project_weight_change>
				<choice_variable_images>" . ($thisChoiceObj->variableImageFlag ? "yes":"no") . "</choice_variable_images>
				";
				
				
				// Include Vendor Quantity Price breaks the if the user has requestet (and are an admin).
				if($includeVendorsFlag){
					
					$vendorChoiceBasePricesStr = "";
					$vendorChoiceSubtotalsPricesStr = "";
					
					for($i=1; $i<=6; $i++){
						$vendorChoiceBasePricesStr .= $thisChoiceObj->PriceAdjustments->getVendorBaseChange($i) . "@";
						$vendorChoiceSubtotalsPricesStr .= $thisChoiceObj->PriceAdjustments->getVendorSubtotalChange($i) . "@";
					}
					
					print "<choice_vendor_subtotal_change>" . WebUtil::chopChar($vendorChoiceSubtotalsPricesStr) . "</choice_vendor_subtotal_change>\n";
					print "<choice_vendor_base_price_change>" . WebUtil::chopChar($vendorChoiceBasePricesStr) . "</choice_vendor_base_price_change>\n";
				}
				
				
				$choiceQuanBrkArr = $thisChoiceObj->QuantityPriceBreaksArr;
				$choiceQuanPriceBreaksBase = "";
				$choiceQuanPriceBreaksSubs = "";
				$vendorChoiceQuanPriceBreaksBase = "";
				$vendorChoiceQuanPriceBreaksSubs = "";

				foreach($choiceQuanBrkArr as $quanBreakObj){

					$choiceQuanPriceBreaksBase .= $quanBreakObj->amount . "^" . $quanBreakObj->PriceAdjustments->CustomerBaseChange . "|";
					$choiceQuanPriceBreaksSubs .= $quanBreakObj->amount . "^" . $quanBreakObj->PriceAdjustments->CustomerSubtotalChange . "|";

					// Include Vendor Quantity Price breaks the if the user has requestet (and are an admin).
					if($includeVendorsFlag){
						
						$vendorChoiceQuanPriceBreaksBase .= $quanBreakObj->amount . "^"; 
						$vendorChoiceQuanPriceBreaksSubs .= $quanBreakObj->amount . "^";
						
						// Separate the 6 vendor prices with an @ symbol.
						for($i=1; $i<=6; $i++){
							$vendorChoiceQuanPriceBreaksBase .= $quanBreakObj->PriceAdjustments->getVendorBaseChange($i) . "@";
							$vendorChoiceQuanPriceBreaksSubs .= $quanBreakObj->PriceAdjustments->getVendorSubtotalChange($i) . "@";
						}
						
						$vendorChoiceQuanPriceBreaksBase = WebUtil::chopChar($vendorChoiceQuanPriceBreaksBase);
						$vendorChoiceQuanPriceBreaksSubs = WebUtil::chopChar($vendorChoiceQuanPriceBreaksSubs);
						
						$vendorChoiceQuanPriceBreaksBase .= "|";
						$vendorChoiceQuanPriceBreaksSubs .= "|";
					}
					
				}
				
				print "<choice_quantity_price_breaks_base>".WebUtil::chopChar($choiceQuanPriceBreaksBase)."</choice_quantity_price_breaks_base>
					<choice_quantity_price_breaks_subtotal>".WebUtil::chopChar($choiceQuanPriceBreaksSubs)."</choice_quantity_price_breaks_subtotal>
					";
				
				if($includeVendorsFlag){
					print "<choice_vendor_quantity_price_breaks_base>".WebUtil::chopChar($vendorChoiceQuanPriceBreaksBase)."</choice_vendor_quantity_price_breaks_base>
						<choice_vendor_quantity_price_breaks_subtotal>".WebUtil::chopChar($vendorChoiceQuanPriceBreaksSubs)."</choice_vendor_quantity_price_breaks_subtotal>
						";
				}
				
				if($thisChoiceObj->ChoiceName == $defaultChoiceForThisOption)
					print "<default_choice>yes</default_choice>";
				else
					print "<default_choice>no</default_choice>";
					
				print "\n</choice>\n";
			}
		
			print "</choices>
			</option>
			";	
		
		}


	print "</options>
	</product_definition>
	</response>";
	
	session_write_close();
}
else if($command == "update_project_options"){

	// Try to Avoid Cross-Site Forgery Requests
	try{
		WebUtil::checkFormSecurityCode();
	}
	catch(ExceptionPermissionDenied $e){
		_PrintXMLError($e->getMessage());
	}

	// Make sure the user has permission to view this Project, otherwise it will print an XML failure.
	_authenticateProjectID($dbCmd, $AuthPassvObj, $projectNumber, $viewType);
	

	$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, $viewType, $projectNumber);
	
	// Make a copy before doing any changes (just in case it causes an increase to the total we can roll back changes.
	$originalProjectObj = $projectObj->cloneThisProjectObject();
	
	// Extract a Product Object out of the Project.
	$productObj = $projectObj->getProductObj();
	
	// Do not try to update the quantity if this is a variable data project.
	if(!$projectObj->isVariableData()){
		
		if(!$productObj->checkIfQuantityIsLegal($projectQuantity))
			_PrintXMLError("Illegal Quantity");
		
		$projectObj->setQuantity($projectQuantity);
	}

	
	// Get a name/value hash from the option
	$optionHash = ProjectInfo::getProductOptionHashFromDelimitedString($projectOptions);
	
	
	foreach($optionHash as $optionName => $choiceSelected){
	
		// Skip any Option/Choices that don't exist.
		if(!$productObj->checkIfOptionChoiceExists($optionName, $choiceSelected))
			continue;
			
		// Don't update the Choice if it is an Admin Options, and the user is not logged in as an admin.
		if($productObj->checkIfOptionIsForAdmins($optionName)){
		
			// They may be updating a Session project.
			if(!$AuthPassvObj->CheckIfLoggedIn())
				continue;
				
			if(!$AuthPassvObj->CheckForPermission("ADMIN_OPTION_CHANGING"))
				continue;
			
			if(!$AuthPassvObj->CheckIfBelongsToGroup("MEMBER"))
				continue;
		}
		
		$projectObj->setOptionChoice($optionName, $choiceSelected);
	
	}
	
	$projectObj->updateDatabase();

	if($viewType == "ordered"){

		if(in_array($projectObj->getStatusChar(), array("Q", "T", "B", "F", "C")))
			_PrintXMLError("Product Options may not be changed once the status becomes Queued, Printed, Boxed, Finished, or Canceled.");
		
		if(MailingBatch::checkIfProjectBatched($dbCmd, $projectNumber))
			_PrintXMLError("You can not change options on a Project which has been already included within a Mailing Batch.");
		
		$UserID = $AuthPassvObj->GetUserID();

		$authorizeErrorMessage = Order::AuthorizeChangesToProjectOrderedObj($dbCmd, $dbCmd2, $originalProjectObj, $projectObj);

		// If the changes to this project are not authorized, it will return an error message, otherwise a blank string.
		// Will print an error message and will exit the script.
		if(!empty($authorizeErrorMessage))
			_PrintXMLError($authorizeErrorMessage);


		// The new charges went through... Keep a record of the change
		$HistoryNote = "Old:<br>" . $projectObj->getOriginalOrderDescription() . "<br>" . $projectObj->getOriginalOptionsDescription() . "<br><br>";
		$HistoryNote .= "New:<br>" . $projectObj->getOrderDescription() . ($projectObj->getOptionsDescription() != "" ? "<br>(" : "")  . $projectObj->getOptionsDescription() . ($projectObj->getOptionsDescription() != "" ? ")" : "") . "<br><br>";
		ProjectHistory::RecordProjectHistory($dbCmd, $projectNumber, $HistoryNote, $UserID);
	}
	
	
	
	
	
	// This will run any product specific functions in case the artwork does not match the project options
	// It may add or delete a backside to the artwork for example... in case of double-sided single-sided
	ProjectBase::ChangeArtworkBasedOnProjectOptions($dbCmd, $projectNumber, $viewType);

	// Editing options on a Variable data project could affect the status... such as changing to and From Variable images.
	if($projectObj->isVariableData())
		VariableDataProcess::SetVariableDataStatusForProject($dbCmd, $projectNumber, $viewType);
		
	
	// Changing the product options may require us to re-generate a new thumbnail image
	// We need to send a message back to the program that updated the options that they need to regenerate the thubmnail image.
	if(ThumbImages::CheckIfProjectThumbnailNeedsUpdate($dbCmd, $originalProjectObj->getProjectInfoObject(), $projectObj->getProjectInfoObject()))
		$updateThumbImage = "yes";
	else
		$updateThumbImage = "no";
		
	VisitorPath::addRecord("Project Options Save", $productObj->getProductTitleWithExtention());
	
	session_write_close();
	
	print "<?xml version=\"1.0\" ?>
	<response>
	<result>OK</result>
	<api_version>1.1</api_version>
	<error_message></error_message>
	<update_thumbanil_image>".$updateThumbImage."</update_thumbanil_image>
	</response>";

}
else if($command == "convert_to_another_product"){

	// Try to Avoid Cross-Site Forgery Requests
	try{
		WebUtil::checkFormSecurityCode();
	}
	catch(ExceptionPermissionDenied $e){
		_PrintXMLError($e);
	}
	
	// Make sure the user has permission to view this Project, otherwise it will print an XML failure.
	_authenticateProjectID($dbCmd, $AuthPassvObj, $projectNumber, $viewType);
	
	$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, $viewType, $projectNumber);

	if(!Product::checkIfProductIDisActive($dbCmd, $converToProductID))
		_PrintXMLError("Can not convert to Product. Product ID does not exist or it is not active.");

	// Make sure the Product is in the same Domain.
	$domainIDofProduct = Product::getDomainIDfromProductID($dbCmd, $converToProductID);
	if(!$AuthPassvObj->CheckIfUserCanViewDomainID($domainIDofProduct))
		_PrintXMLError("Can not convert to Product. Product ID in the URL does not exist: " . $productID);
		

	// Get a copy of this Project Object.  The copy has been converted Automatically, including the Artwork, etc.
	$convertedProjectObj = $projectObj->convertToProductID($converToProductID);


	// Update the Database... The old Project is overwritten.
	$convertedProjectObj->updateDatabase();
	
	
	// In case this Project has variable data... changing the artwork could affect the configuration
	// Don't go through the extra work of parsing the Data file if there is a Data Error.  Changing the artwork won't solve that error
	// Also check if the data file is empty (0 quantity)... in which case we may want the system to initialize any unmapped fields..
	if($convertedProjectObj->isVariableData() && ($convertedProjectObj->getVariableDataStatus() != "D" || $convertedProjectObj->getQuantity() == 0))
		VariableDataProcess::SetVariableDataStatusForProject($dbCmd, $projectNumber, $viewType);


	// Change the Product ID on something which has already been ordered can cause an increase to the total
	// We may need to authorize new charges on the customer's credit cart and print an error if not authorized.
	if($convertedProjectObj->getViewTypeOfProject() == "ordered"){

		$AuthObj = new Authenticate(Authenticate::login_general);
		$UserID = $AuthObj->GetUserID();

		if(in_array($projectObj->getStatusChar(), array("Q", "T", "B", "F", "C")))
			_PrintXMLError("Products may not be converted once the status becomes Queued, Printed, Boxed, or Canceled.");

		// Switching Product ID's in Proof Mode requires us to Reset the Status back to New.
		$convertedProjectObj->setStatusChar("N");


		// If the changes to this project are not authorized, it will return an error message, otherwise a blank string.
		// Will also, Save project object to the database.
		$authorizeErrorMessage = Order::AuthorizeChangesToProjectOrderedObj($dbCmd, $dbCmd2, $projectObj, $convertedProjectObj);
		if(!empty($authorizeErrorMessage))
			_PrintXMLError($authorizeErrorMessage);

		// The new charges went through... Keep a record of the change
		$HistoryNote = "The product was changed from...<br>" . $projectObj->getProductTitleWithExt() . "<br>to...<br>" . $convertedProjectObj->getProductTitleWithExt() . "<br><br>";
		ProjectHistory::RecordProjectHistory($dbCmd, $projectNumber, $HistoryNote, $UserID);
		
		// Erase any PDF settings that were set for this particular project.
		PDFparameters::ClearPDFsettingsForProjectOrdered($dbCmd, $projectNumber);

		// Projects that are "new" could change to "Variable Data Error" status on ordered projects if there is no data file or whatever.
		if($convertedProjectObj->isVariableData())
			VariableDataProcess::SetVariableDataStatusForProject($dbCmd, $projectNumber, $viewType);

		
		// There is never a thumbnail for an ordered project.
		$updateThumbImage = "no";
	}
	else{
		$updateThumbImage = "yes";
	}

	
	// Since we are changing the Product ID... we can't change the product ID's on other projects linked to this one.
	// Break all Links from or to this saved project.
	ProjectSaved::ClearSavedIDLinksByViewType($dbCmd, $viewType, $projectNumber);

	VisitorPath::addRecord("Product Conversion", "From: " . $projectObj->getProductTitleWithExt() . " To: " . $convertedProjectObj->getProductTitleWithExt());

	
	session_write_close();
	
	print "<?xml version=\"1.0\" ?>
	<response>
	<result>OK</result>
	<api_version>1.1</api_version>
	<error_message></error_message>
	<update_thumbanil_image>".$updateThumbImage."</update_thumbanil_image>
	</response>";

}
else if($command == "update_artwork_thumbnail"){

	// Try to Avoid Cross-Site Forgery Requests
	try{
		WebUtil::checkFormSecurityCode();
	}
	catch(ExceptionPermissionDenied $e){
		_PrintXMLError($e);
	}

	// Make sure the user has permission to view this Project, otherwise it will print an XML failure.
	_authenticateProjectID($dbCmd, $AuthPassvObj, $projectNumber, $viewType);
	
	
	// Make sure that the same thumbnail is not generated more than once.
	// It is possible that the person could hit there back button many times repeatadely and cause lots of thumbnails to start loading at once, overloading the server
	if(ThumbImages::checkIfThumbnailCanBeUpdated($dbCmd, $viewType, $projectNumber)){
		ThumbImages::markThumbnailAsUpdating($dbCmd, $viewType, $projectNumber);
		ThumbImages::CreateThumnailImage($dbCmd, $projectNumber, $viewType);
	}
	
	session_write_close();
	
	print "<?xml version=\"1.0\" ?>
	<response>
	<result>OK</result>
	<api_version>1.1</api_version>
	<error_message></error_message>
	</response>";
}
else if($command == "get_product_list"){

	$product_types = WebUtil::GetInput("product_types", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
	
	if($product_types == "main")
		$productIDsArr = Product::getActiveMainProductIDsArr($dbCmd, Domain::getDomainIDfromURL());
	else if($product_types == "all")
		$productIDsArr = Product::getActiveProductIDsArr($dbCmd, Domain::getDomainIDfromURL());
	else
		_PrintXMLError("Illegal Product Types");
		
		
	
	session_write_close();
	
	print "<?xml version=\"1.0\" ?>
	<response>
	<result>OK</result>
	<api_version>1.1</api_version>
	<error_message></error_message>
	";
	
	foreach($productIDsArr as $thisProductId){
		print "<product>\n";
		print "<product_id>".htmlspecialchars($thisProductId)."</product_id>\n";
		print "<product_title>".htmlspecialchars(Product::getRootProductName($dbCmd, $thisProductId))."</product_title>\n";
		print "<product_title_ext>".htmlspecialchars(Product::getProductTitleExt($dbCmd, $thisProductId))."</product_title_ext>\n";
		print "</product>\n";
	}
	
	print "
	</response>";
}
else if($command == "get_promo_code"){

	/*
	// Show the Coupon for any organic results with "business cards"
	$bizCardOrganicClickFound = false;
	$subLabelsOfOrganicLinks = VisitorPath::getSubLabelsInSessionGoingThroughMainLabel("Organic Link");
	foreach($subLabelsOfOrganicLinks as $thisSubLabel){
		if(preg_match("/business\s*card/i", $thisSubLabel) || preg_match("/bussines\s*card/i", $thisSubLabel))
			$bizCardOrganicClickFound = true;
	}
	
	if($bizCardOrganicClickFound){
		WebUtil::SetSessionVar('PromoSpecial', "OFFER21XA");
		WebUtil::SetCookie('PromoSpecial', "OFFER21XA", 100);
	}
	*/
	
	$promoSpecialCode = WebUtil::GetSessionVar("PromoSpecial", WebUtil::GetCookie("PromoSpecial", ""));
	
	session_write_close();
	
	// See if there is a Session Variable... then a cookie ... that has been set with a Promo Code

	print "<?xml version=\"1.0\" ?>
	<response>
	<result>OK</result>
	<api_version>1.1</api_version>
	<error_message></error_message>
	<promo_code>" . htmlspecialchars($promoSpecialCode) . "</promo_code>
	</response>";
}
else if($command == "submit_email_for_newsletter"){
	
	$emailAddress = WebUtil::GetInput("emailAddress", FILTER_SANITIZE_STRING_ONE_LINE);
	
	if(!WebUtil::ValidateEmail($emailAddress)){
		VisitorPath::addRecord("Newsletter Email Error", $emailAddress);
		_PrintXMLError("Sorry, your email address is invalid.");
	}
		
	
		
	// Find out if there is a banner tracking code that we can correlate with the email submissin.
	$ReferalTracking = WebUtil::GetSessionVar("ReferralSession", WebUtil::GetCookie("ReferralCookie"));
		
	// Record email into DB.
	$dbCmd->InsertQuery("emailnewsletterrequest",  array("Email"=>$emailAddress, "BannerTrackingCode"=>$ReferalTracking, "DateSubmitted"=>date("YmdHis"), "DomainID"=>Domain::oneDomain(), "IPaddress"=>WebUtil::getRemoteAddressIp()));
   
	VisitorPath::addRecord("Newsletter Added", $emailAddress);
	
	session_write_close();
	
	print "<?xml version=\"1.0\" ?>
	<response>
	<result>OK</result>
	<api_version>1.1</api_version>
	<error_message></error_message>
	</response>";
}
else if($command == "add_to_shoppingcart"){

	// Try to Avoid Cross-Site Forgery Requests
	try{
		WebUtil::checkFormSecurityCode();
	}
	catch(ExceptionPermissionDenied $e){
		_PrintXMLError($e);
	}

	// Make sure the user has permission to view this Project, otherwise it will print an XML failure.
	_authenticateProjectID($dbCmd, $AuthPassvObj, $projectNumber, $viewType);

	$ProjectObj = ProjectBase::getProjectObjectByViewType($dbCmd, $viewType, $projectNumber);
	
	if($viewType == "saved"){
		
		// Copy the Saved Project data into the new Project Session
		$newProjectSessionObj = new ProjectSession($dbCmd);
		$newProjectSessionObj->copyProject($ProjectObj);
		$projectIDforCart = $newProjectSessionObj->createNewProject($user_sessionID);
	}
	else if($viewType == "session"){
		$projectIDforCart = $projectNumber;
	}
	else{
		_PrintXMLError("The View Type for adding a project to the shoppingcart can only be 'saved' or 'session'");
	}

	
	$dbCmd->Query("SELECT COUNT(*) FROM shoppingcart WHERE ProjectRecord=" . intval($projectIDforCart));
	$alreadyFound = $dbCmd->GetValue();
	
	if(!$alreadyFound){
		// Record information into the "shoppingcart table"
		$dbCmd->InsertQuery("shoppingcart",  array("SID"=>$user_sessionID, "ProjectRecord"=>$projectIDforCart, "DateLastModified"=>date("YmdHis"), "DomainID"=>Domain::oneDomain()));
	}
		
	if($viewType == "saved"){
		// Instead of generating a new thumbnail, let's just quickly copy it over, since it is the same
		ThumbImages::CopyProjectThumbnail($dbCmd, "projectssaved", $projectNumber, "projectssession", $projectIDforCart);
	}
		
	VisitorPath::addRecord("Add To Shopping Cart", $ProjectObj->getProductTitleWithExt());
	
	session_write_close();
	
	print "<?xml version=\"1.0\" ?>
	<response>
	<result>OK</result>
	<api_version>1.1</api_version>
	<error_message></error_message>
	<project_session_id>$projectIDforCart</project_session_id>
	</response>";
}
else if($command == "remove_from_shoppingcart"){

	// Try to Avoid Cross-Site Forgery Requests
	try{
		WebUtil::checkFormSecurityCode();
	}
	catch(ExceptionPermissionDenied $e){
		_PrintXMLError($e);
	}

	// Make sure the user has permission to view this Project, otherwise it will print an XML failure.
	_authenticateProjectID($dbCmd, $AuthPassvObj, $projectNumber, $viewType);

	$ProjectObj = ProjectBase::getProjectObjectByViewType($dbCmd, $viewType, $projectNumber);
	
	if($viewType != "session"){
		_PrintXMLError("The View Type for removing from shoppingcart can only be 'session'");
	}

	$dbCmd->Query("DELETE FROM shoppingcart WHERE ProjectRecord=" . intval($projectNumber) . " AND SID='" . DbCmd::EscapeLikeQuery($user_sessionID) . "'");
	
	VisitorPath::addRecord("Removed From Shopping Cart", $ProjectObj->getProductTitleWithExt());
	
	session_write_close();
	
	print "<?xml version=\"1.0\" ?>
	<response>
	<result>OK</result>
	<api_version>1.1</api_version>
	<error_message></error_message>
	</response>";
}
else if($command == "get_project_artwork"){


	// Make sure the user has permission to view this Project, otherwise it will print an XML failure.
	_authenticateProjectID($dbCmd, $AuthPassvObj, $projectNumber, $viewType);

	$ProjectObj = ProjectBase::getProjectObjectByViewType($dbCmd, $viewType, $projectNumber);
	
	$artInfoObj = new ArtworkInformation($ProjectObj->getArtworkFile());
	
	session_write_close();
	
	print "<?xml version=\"1.0\" ?>
	<response>
	<result>OK</result>
	<api_version>1.1</api_version>
	<error_message></error_message>";
	
	// Get the XML file, which should have a line break after each tag.
	$artworkXML = $artInfoObj->GetXMLdoc();
	
	// Remove the top XML tag
	$artworkLines = split("\n", $artworkXML);
	
	foreach($artworkLines as $thisArtworkLine){
		if(preg_match("/".preg_quote("<?xml version")."/", $thisArtworkLine))
			continue;
			
		print $thisArtworkLine . "\n";
	}
	
	print "</response>";
}
else if($command == "thumbnail_status"){

	// Try to Avoid Cross-Site Forgery Requests
	try{
		WebUtil::checkFormSecurityCode();
	}
	catch(ExceptionPermissionDenied $e){
		_PrintXMLError($e->getMessage());
	}

	// Make sure the user has permission to view this Project, otherwise it will print an XML failure.
	_authenticateProjectID($dbCmd, $AuthPassvObj, $projectNumber, $viewType);
	
	$returnStatus = "Unknown";

	if(ThumbImages::checkIfThumbnailCanBeUpdated($dbCmd, $viewType, $projectNumber)){
		$returnStatus = "NeedsUpdate";
	}
	else{
		$thumbnailObj = new ThumbImages();
		
		$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, $viewType, $projectNumber);
		$projectDate = $projectObj->getDateLastModified();
		$dateOfLastThumbnail = $thumbnailObj->GetProjectThumbnailLastUpdated($dbCmd, $viewType, $projectNumber);
		
		if(abs($projectDate - $dateOfLastThumbnail) > 100){
			$returnStatus = "Updating";
		}
		else{
			$returnStatus = "Ready";
		}
	}
	
	session_write_close();
	
	print "<?xml version=\"1.0\" ?>
	<response>
	<result>OK</result>
	<api_version>1.1</api_version>
	<error_message></error_message>
	<thumbnail_status>$returnStatus</thumbnail_status>
	</response>";
		
}
else if($command == "product_transfer"){

	// Try to Avoid Cross-Site Forgery Requests
	try{
		WebUtil::checkFormSecurityCode();
	}
	catch(ExceptionPermissionDenied $e){
		_PrintXMLError($e);
	}
	
	// Make sure the user has permission to view this Project, otherwise it will print an XML failure.
	_authenticateProjectID($dbCmd, $AuthPassvObj, $projectNumber, $viewType);
	
	$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, $viewType, $projectNumber);

	if(!Product::checkIfProductIDisActive($dbCmd, $transferToProductID))
		_PrintXMLError("Can not transfer to a Product ID that does not exist or it is not active.");

	// Make sure the Product is in the same Domain.
	$domainIDofProduct = Product::getDomainIDfromProductID($dbCmd, $transferToProductID);
	if(!$AuthPassvObj->CheckIfUserCanViewDomainID($domainIDofProduct))
		_PrintXMLError("Can not transfer to Product ID that does not exist: " . $productID);

		
	// This will copy from our source project into the destination Product ID.
	// The copy will automatically convert the Artwork, variable data, etc.
	$productTransferedObj = $projectObj->convertToProductID($transferToProductID);
	
	
	// Now change the automatic transfered Object into the ViewType that is specified.
	if($destinationProjectViewType == "session"){
		
		$newProjectObj = new ProjectSession($dbCmd);
		
		$newProjectObj->copyProject($productTransferedObj);
		$newProjectId = $newProjectObj->createNewProject(WebUtil::GetSessionID());
		$newProjectObj->loadByProjectID($newProjectId);
	}
	else if($destinationProjectViewType == "saved"){
		
		$newProjectObj = new ProjectSaved($dbCmd);
		$newProjectObj->copyProject($productTransferedObj);
		
		if(!$AuthPassvObj->CheckIfLoggedIn())
			_PrintXMLError("User must be logged in before transfering to Saved Project.");
			
		$newProjectId = $newProjectObj->createNewProject($AuthPassvObj->GetUserID());
		$newProjectObj->loadByProjectID($newProjectId);
	}
	else{
		_PrintXMLError("The destination project view type is invalid.");
	}
	
	
	unset($productTransferedObj);
	
	
	// If a specific template has been provided... we can do a better job converting the artwork.
	if(!empty($templateId)){
		
		$productIdDestinationTemplate = ProjectBase::GetProductIDFromProjectRecord($dbCmd, $templateId, TemplateLinks::getViewTypeFromTemplateArea($templateArea));
		
		if(Product::getDomainIDfromProductID($dbCmd, $productIdDestinationTemplate) != $projectObj->getDomainID())
			_PrintXMLError("The destination template ID is invalid.");
			
		
		$templateArtObj = new ArtworkInformation(ArtworkLib::GetArtXMLfile($dbCmd, TemplateLinks::getViewTypeFromTemplateArea($templateArea), $templateId, false));
		$originalArtworkObj = new ArtworkInformation($projectObj->getArtworkFile());
		
		// Transfer the contents of matching text layers (from the project artwork)... onto the destination template.
		$templateArtObj = TemplateLinks::transferContentToNewTemplate($originalArtworkObj, $templateArtObj);

		
		// Make sure that the aspect ratios match.
		// The template artwork could belong to a different Product ID than the source artwork.
		$artworkConversionObj = new ArtworkConversion($dbCmd);
		$artworkConversionObj->setFromArtwork(0, $templateArtObj->GetXMLdoc());
		$artworkConversionObj->setToArtwork($transferToProductID);
		
		$newProjectObj->setArtworkFile($artworkConversionObj->getConvertedArtwork());
		
		// Make sure that the Project knows that the "Template Source" is the template link.   We don't want to use the template ID/Area from the source project that we copied.
		$newProjectObj->setFromTemplateArea($templateArea);
		$newProjectObj->setFromTemplateID($templateId);
		
		$newProjectObj->updateDatabase();
	}
	
	

	
	// In case this Project has variable data... changing the artwork could affect the configuration
	// Don't go through the extra work of parsing the Data file if there is a Data Error.  Changing the artwork won't solve that error
	// Also check if the data file is empty (0 quantity)... in which case we may want the system to initialize any unmapped fields..
	if($newProjectObj->isVariableData() && ($newProjectObj->getVariableDataStatus() != "D" || $newProjectObj->getQuantity() == 0))
		VariableDataProcess::SetVariableDataStatusForProject($dbCmd, $projectNumber, $viewType);


	VisitorPath::addRecord("Product Transfer", "From: " . $projectObj->getProductTitleWithExt() . " To: " . $newProjectObj->getProductTitleWithExt());

	
	session_write_close();
	
	print "<?xml version=\"1.0\" ?>
	<response>
	<result>OK</result>
	<api_version>1.1</api_version>
	<error_message></error_message>
	<new_project_id>".$newProjectId."</new_project_id>
	<new_project_view>".$destinationProjectViewType."</new_project_view>
	</response>";

}
else if($command == "get_projects_in_shoppingcart"){

	$projectIDsArr = ShoppingCart::GetProjectSessionIDsInShoppingCart($dbCmd, $user_sessionID);
	
	print "<?xml version=\"1.0\" ?>
	<response>
	<result>OK</result>
	<api_version>1.1</api_version>
	<error_message></error_message>
	<cart_items>";
	foreach($projectIDsArr as $thisProjectID){
		$projectObj = ProjectSession::getObjectByProjectID($dbCmd, $thisProjectID);
		print "<project>\n";
		print "<project_id>" . $thisProjectID . "</project_id>\n";
		print "<product_id>" . $projectObj->getProductID() . "</product_id>\n";
		print "<quantity>" . $projectObj->getQuantity() . "</quantity>\n";
		print "<saved_project_link_id>" . intval($projectObj->getSavedID()) . "</saved_project_link_id>\n";
		print "</project>\n";
	}
	print "</cart_items>
	</response>";
	
	session_write_close();
}
else{

	_PrintXMLError("Invalid API command.");
}



	
?>