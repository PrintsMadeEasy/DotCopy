<?php

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();


$shippingMethodsObj = new ShippingMethods(0);

// Give it at least 1 hour to generate a large artwork
set_time_limit(4000);
ini_set("memory_limit", "512M");


if(!Constants::GetDevelopmentServer()){
	if ( !isset($_SERVER['HTTPS']) || strtolower($_SERVER['HTTPS']) != 'on' )
	   _PrintXMLError("You must be running in Secure Mode (https) to use this API.");
}




// Collect all Input from URL
$UserName = WebUtil::GetInput("username", FILTER_SANITIZE_EMAIL);
$PassWord = WebUtil::GetInput("password", FILTER_SANITIZE_STRING_ONE_LINE);
$command = WebUtil::GetInput("command", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$status = strtoupper(WebUtil::GetInput("status", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES));
$OldStatus = strtoupper(WebUtil::GetInput("oldstatus", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES));
$ProjectID = WebUtil::GetInput("project_number", FILTER_SANITIZE_INT);
$OrderID = WebUtil::GetInput("order_number", FILTER_SANITIZE_INT);
$PDF_profile = WebUtil::GetInput("pdf_profile", FILTER_SANITIZE_STRING_ONE_LINE);
$ProductID = WebUtil::GetInput("productid", FILTER_SANITIZE_INT);
$ShipmentReference = WebUtil::GetInput("shipment_reference", FILTER_SANITIZE_STRING_ONE_LINE);
$ShipmentCost = WebUtil::GetInput("shipment_cost", FILTER_SANITIZE_FLOAT);
$ShipmentTracking = WebUtil::GetInput("shipment_tracking", FILTER_SANITIZE_STRING_ONE_LINE);
$ShipmentCarrier = WebUtil::GetInput("shipment_carrier", FILTER_SANITIZE_STRING_ONE_LINE);
$ProductionNote = WebUtil::GetInput("production_note", FILTER_SANITIZE_STRING_ONE_LINE);


// Even throug the Automation system using the API may handle Production for Multiple Domains,
// We still want to figure out the domain ID of the UserName/Passowrd for the URL.
// We can give that person multiple Domain Privilages.
$domainIDfromURL = Domain::getDomainIDfromURL();

// Method returns a string with an error message or with the letters "OK".
$LoginResult = Authenticate::CheckUserNamePass($dbCmd, $UserName, $PassWord, $domainIDfromURL);

if($LoginResult != "OK")
	_PrintXMLError($LoginResult);


	
$UserID = UserControl::GetUserIDByEmail($dbCmd, $UserName, $domainIDfromURL);

$AuthObj = new Authenticate(Authenticate::login_OVERRIDE, $UserID);
$AuthObj->SetGroupsThatUserBelongsTo($UserID);


if(!$AuthObj->CheckForPermission("PRODUCTION_API"))
	$LoginResult = "Permission not allowed for using this API";

$orderQryObj = new OrderQuery($dbCmd);
	
// Because this is an API call... we don't want to limit based upon cookies os selected domain IDs... it should show all Domains for User Authentication.
$orderQryObj->limitToOnlySelectedDomains(false);
	

// Set all Domains that the user is allows to see (this will affect the "order queries" and "project counts"
// We could also let the user pass in a Domain ID preference if they wanted.
$domainObj = Domain::singleton();
$domainObj->setDomains($AuthObj->getUserDomainsIDs());
	
	
if($command == "get_projects_by_status"){

	// First make sure that the status character is valid
	$StatusHash = ProjectStatus::GetStatusDescriptionsHash();
	
	if (!array_key_exists($status, $StatusHash)) {
		_PrintXMLError("Invalid Status");
	}
	else if($status == "F"){
		_PrintXMLError("This API will not work with completed orders.");
	}
	else if($status == "C"){
		_PrintXMLError("This API will not work with canceled orders.");
	}
	
	if(!$ProductID)
		_PrintXMLError("The Product ID is missing.");
		
	
	// If the user is a vendor... they may not be part of the Production Product ID
	// But the user may not be a Vendor... although they have domain Permissiosn on the Production Product ID.
	// ... so there are 2 different types of authentication.
	
	$productionProductID = Product::getProductionProductIDStatic($dbCmd, $ProductID);
	$domainOfProduction = Product::getDomainIDfromProductID($dbCmd, $productionProductID);
	
	// Find out the Production Product ID... and find out what Products Are linked to that
	// The Production Operator does not need permissions on all of the domains in order to fetch the status.
	$allProductsIDsLinkedToProductionID = Product::getAllProductIDsSharedForProduction($dbCmd, $ProductID);
	
	
	$productionProductIDAuthFlag = false;
	$vendorIDAuthFlag = false;
	
	if($AuthObj->CheckIfUserCanViewDomainID($domainOfProduction)){
		
		$productionProductIDAuthFlag = true;
	}
	else{
		$productObj = new Product($dbCmd, $ProductID, false);
		$vendorIDsArr = $productObj->getVendorIDArr();
		
		if(in_array($UserID, $vendorIDsArr))
			$vendorIDAuthFlag = true;
	}

	
	if($productionProductIDAuthFlag){
		$dbCmd->Query("SELECT ID FROM projectsordered WHERE Status='".DbCmd::EscapeSQL($status)."' AND ". DbHelper::getOrClauseFromArray("ProductID", $allProductsIDsLinkedToProductionID));	
	}
	else if($vendorIDAuthFlag){
		$dbCmd->Query("SELECT ID FROM projectsordered WHERE (" . Product::GetVendorIDLimiterSQL($UserID) . ") AND Status='".DbCmd::EscapeSQL($status) . "'");	
	}
	else{
		_PrintXMLError("Error with Domain Permissions.");
	}
	

	
	
	$retXML = "<?xml version=\"1.0\" ?>\n<server_response>";
	$retXML .= "<result>OK</result>";
	
	while($ThisProjectID = $dbCmd->GetValue())
		$retXML .= _XML_Build_ProjectNode($ThisProjectID, $dbCmd2);
	
	$retXML .= "</server_response>";
	
	header ("Content-Type: text/xml");
	print $retXML;

}
else if($command == "change_project_status"){

	// The API is trying to change the status... They must correctly send the old status... and then the status that they want to change it to.
	// If the Old status is not matching the current status.... then customer service may have put the project on hold or something like that.. in which case it will return an error 

	// If a status letter of "D" comes through... then we want to change the status to "proofed" but record "Defective" within the Status History
	$StatusHistoryOveride = "";

	// If we are changing to Defective then we want to automatically change the priority to "Urgent" so that we don't fall behind.
	$changePriority = "";

	if($status == "D"){
		$status = "P";
		$StatusHistoryOveride = "Defective Print Job, Retrying";
		$changePriority = "U";
	}

	// First make sure that the status character is valid
	$StatusHash = ProjectStatus::GetStatusDescriptionsHash();
	if (!array_key_exists($status, $StatusHash) || !array_key_exists($OldStatus, $StatusHash)) {
		_PrintXMLError("Invalid Status Character.  2 separate status characters must be passed in...'oldstatus' and 'status'");
	}
	
	if(!$ProjectID)
		_PrintXMLError("The Project ID is missing.");

	if($status == "F")
		_PrintXMLError("You can not change the status of a project to completed.  This is done by completing its shipment(s).");

	if($status == "C")
		_PrintXMLError("You can not cancel an order through the API.");


	_EnsurePermissionsOnProject($dbCmd, $ProjectID);
	
	$ProjectStatus = ProjectOrdered::GetProjectStatus($dbCmd, $ProjectID);

	if($ProjectStatus == "C")
		_PrintXMLError("This project has been canceled.  You can not un-cancel an order through the API.");

	if($ProjectStatus == "H")
		_PrintXMLError("This project has been put on hold.  You can not take an order off of hold through the API.");


	if($ProjectStatus != $OldStatus)
		_PrintXMLError("The status on P$ProjectID is different that what you anticipated.  The current status is " . ProjectStatus::GetProjectStatusDescription($ProjectStatus) . "." );
	

	// We don't want people to change the artwork on a mailing batch because there could be a lot of money associated with Postage, etc.  There is not an easy way to issolate a Project once it has been grouped with others, printed, etc.
	if(MailingBatch::checkIfProjectBatched($dbCmd, $ProjectID))
		_PrintXMLError("You can not change the status on a Project which has been already included within a Mailing Batch.  If you really need to change the status then cancel the Mailing Batch first.");


	
	$dbCmd->UpdateQuery( "projectsordered", array("Status"=>$status), "ID=$ProjectID" );
	
	

	// If the priority is not NULL, then update the project table with the new value that we want.
	if(!empty($changePriority))
		$dbCmd->UpdateQuery( "projectsordered", array("Priority"=>"U"), "ID=$ProjectID" );
		
	
	// We can override what is recorded in the status history if we want to... otherwise just show the status change
	if(empty($StatusHistoryOveride))
		ProjectHistory::RecordProjectHistory($dbCmd, $ProjectID, $status, $UserID);
	else
		ProjectHistory::RecordProjectHistory($dbCmd, $ProjectID, $StatusHistoryOveride, $UserID);

		
	// Take the Project off of the track if we are going from Boxed to Printed status.
	if($ProjectStatus == "B" && $status == "T"){
		try{	
			$ProductID = ProjectBase::GetProductIDFromProjectRecord($dbCmd, $ProjectID, "admin");
			$productionProductID = Product::getProductionProductIDStatic($dbCmd, $ProductID);
			$domainIDofProductionProductID = Product::getDomainIDfromProductID($dbCmd, $productionProductID);
			
			$RackControlObj = new RackControl($dbCmd, $domainIDofProductionProductID);
			
			if($RackControlObj->CheckIfProjectsIsOnRack($ProjectID)){
				$RackControlObj->RemoveProjectFromRack($ProjectID);
				ProjectHistory::RecordProjectHistory($dbCmd, $ProjectID, "Removed Project from rack space after going from Boxed to Printed.", $UserID);
			}
		}
		catch (Exception $e){
			_PrintXMLError($e->getMessage());
		}
	}
		
	
	$retXML = "<?xml version=\"1.0\" ?>\n<server_response><result>OK</result></server_response>";
	header ("Content-Type: text/xml");
	print $retXML;


}
else if($command == "returned_package"){

	$ReturnedPackagesObj = new ReturnedPackages($dbCmd2);
	
	_EnsurePermissionsOnProject($dbCmd, $ProjectID);
	
	if(!$ReturnedPackagesObj->MarkOrderAsReturned($ProjectID, $UserID))
		_PrintXMLError($ReturnedPackagesObj->GetErrorMessage());
		
	$retXML = "<?xml version=\"1.0\" ?>\n<server_response><result>OK</result></server_response>";
	header ("Content-Type: text/xml");
	print $retXML;

}
else if($command == "change_project_status_bulk"){

	// This is basically doing the same thing as change_project_status... but it is works off a Project List separated by Pipe Symbols
	// Because it is changing the status of many projects at once, it will not give the individual attention to Status Mismatches etc.
	// If the old status doesn't match the new one then it will silently skip it
	// It will return a pipe-delimited list of Project ID's that it was alowed to change... similar to the pipe-delimited list that is passed in... In a perfect world they should match.


	$ProjectList = WebUtil::GetInput("project_list", FILTER_SANITIZE_STRING_ONE_LINE);
	
	$ProjectListArr = $orderQryObj->GetProjectArrayFromProjectList($ProjectList);
	
	$ReturnProjectList = "";
	$ProjectCount = 0;
	
	foreach($ProjectListArr as $thisProjectID){

		// First make sure that the status character is valid
		$StatusHash = ProjectStatus::GetStatusDescriptionsHash();
		if (!array_key_exists($status, $StatusHash) || !array_key_exists($OldStatus, $StatusHash)) {
			_PrintXMLError("Invalid Status Character.  2 separate status characters must be passed in...'oldstatus' and 'status'");
		}

		// You can't complete or cancel order
		if($status == "F" || $status == "C")
			_PrintXMLError("You can not cancel or complate an order through the API.");

		// If the vendor doesn't have permissions to view the project, then just skip it without making an error
		if(!_CheckForVendorProjectInclusion($dbCmd, $thisProjectID, $UserID))
			continue;
			
		_EnsurePermissionsOnProject($dbCmd, $thisProjectID);

		$ProjectStatus = ProjectOrdered::GetProjectStatus($dbCmd, $thisProjectID);

		// If the order has been put on hold or canceled... then we want to skip it
		if($ProjectStatus == "C" || $ProjectStatus == "H" || $ProjectStatus == "F")
			continue;

		// If the status has changed to anything other than what they expected... then ignore it.
		if($ProjectStatus != $OldStatus)
			continue;

		$dbCmd->UpdateQuery( "projectsordered", array("Status"=>$status), "ID=$thisProjectID" );

		ProjectHistory::RecordProjectHistory($dbCmd, $thisProjectID, $status, $UserID);
		
		$ReturnProjectList .= $thisProjectID . "|";
		$ProjectCount++;
	}
	
	

	
	$retXML = "<?xml version=\"1.0\" ?>\n<server_response><result>OK</result>";
	$retXML .= "<project_list>" . $ReturnProjectList . "</project_list>";
	$retXML .= "<project_count>" . $ProjectCount . "</project_count>";
	$retXML .= "</server_response>";

	header ("Content-Type: text/xml");
	print $retXML;


}
else if($command == "set_production_note"){

	if(!$ProjectID)
		_PrintXMLError("The Project ID is missing.");

	_EnsurePermissionsOnProject($dbCmd, $ProjectID);
	
	if(strlen($ProductionNote) > 200)
		_PrintXMLError("The Production note is too long");
	
	$dbCmd->UpdateQuery( "projectsordered", array("NotesProduction"=>$ProductionNote), "ID=$ProjectID" );
	
	$retXML = "<?xml version=\"1.0\" ?>\n<server_response><result>OK</result></server_response>";
	header ("Content-Type: text/xml");
	print $retXML;
}
else if($command == "get_project_info"){

	if(!$ProjectID)
		_PrintXMLError("The Project ID is missing.");

	_EnsurePermissionsOnProject($dbCmd, $ProjectID);
	
	$orderID = Order::GetOrderIDfromProjectID($dbCmd, $ProjectID);

	$shippingInstructionsForXML = Order::getShippingInstructions($dbCmd, $orderID);

	$retXML = "<?xml version=\"1.0\" ?>\n
			<server_response>
			<result>OK</result>
			<shipping_instructions>". WebUtil::htmlOutput($shippingInstructionsForXML) ."</shipping_instructions>
			" . _XML_Build_ProjectNode($ProjectID, $dbCmd2) . "
			" . _XML_Build_RackControlNode($ProjectID, $dbCmd2) . "
			</server_response>";
	

	header ("Content-Type: text/xml");
	print $retXML;
	
}
else if($command == "get_invoice"){

	// We need either a product ID or an order ID, we don't need both.
	if(!$OrderID && !$ProjectID)
		_PrintXMLError("The Order ID and Project ID are missing.");
		
	// If we don't have an order ID, then we need to get it from the Project ID
	if($ProjectID){
		_EnsurePermissionsOnProject($dbCmd, $ProjectID);
		$OrderID = Order::GetOrderIDfromProjectID($dbCmd, $ProjectID);
	}
			
	_CheckForVendorPermissionsOnOrder($dbCmd, $OrderID, $UserID);
	
	if(WebUtil::GetInput("noborders", FILTER_SANITIZE_STRING_ONE_LINE) != NULL)
		$ShowInvoiceBorders = false;
	else
		$ShowInvoiceBorders = true;
		
	// Generate the invoice... which writes the PDF file to disk.  Then the function returns the file name.
	$InvoiceFileName = Invoice::GenerateInvoices($dbCmd, $OrderID, array(), $ShowInvoiceBorders);
	
	$InvoiceURL = DomainPaths::getPdfWebPathOfDomainInURL() . "/" . $InvoiceFileName;
	
	$retXML = "<?xml version=\"1.0\" ?>\n<server_response><result>OK</result><invoice_url>" . WebUtil::htmlOutput($InvoiceURL) . "</invoice_url></server_response>";
	header ("Content-Type: text/xml");
	print $retXML;

}
else if($command == "get_promo_artwork"){

	if(!$ProjectID)
		_PrintXMLError("The Project ID is missing.");

	_EnsurePermissionsOnProject($dbCmd, $ProjectID);
	
	$ProjectStatus = ProjectOrdered::GetProjectStatus($dbCmd, $ProjectID);

	if(!in_array($ProjectStatus, array("P", "T", "B", "Q", "F")))
		_PrintXMLError("The status of this project must be Proofed, Printed, Queued, Boxed, or Finished to generate a Promo Artwork.");
		
	$promoCommand = WebUtil::GetInput("promo_command", FILTER_SANITIZE_STRING_ONE_LINE);
	
	if(!ArtworkCrossell::CheckIfCrossSellCommandAvailable($promoCommand))
		_PrintXMLError("The Artwork Promo Command is not available: " . WebUtil::htmlOutput($promoCommand));
		
		
	// Do not print any promotional artwork if the Customer is a Reseller because they could be drop shipping.
	

	$dbCmd->Query("SELECT UserID FROM orders INNER JOIN projectsordered ON orders.ID = projectsordered.OrderID WHERE projectsordered.ID=$ProjectID");
	$userIDFromProject = $dbCmd->GetValue();

		
	$userObj = new UserControl($dbCmd);
	$userObj->LoadUserByID($userIDFromProject);
		
	if($userObj->getAccountType() == "R")
		_PrintXMLError("The Artwork Promo Command is not available because the customer is a Reseller: " . WebUtil::htmlOutput($promoCommand));
	
		
	$crossellPdfDoc =& ArtworkCrossell::GetPDFdocFromForCrossSelling($dbCmd, $promoCommand, $ProjectID);
	
	//Get a random filename
	$PDFfilename = "artPromo_" . md5(microtime() . $ProjectID) . ".pdf";

	// Put PDF on disk
	$fp = fopen(DomainPaths::getPdfSystemPathOfDomainInURL() . "/" . $PDFfilename, "w");
	fwrite($fp, $crossellPdfDoc);
	fclose($fp);

	$ArtworkURL = DomainPaths::getPdfWebPathOfDomainInURL() . "/" . $PDFfilename;

	$retXML = "<?xml version=\"1.0\" ?>\n<server_response><result>OK</result><promo_artwork_url>" . WebUtil::htmlOutput($ArtworkURL) . "</promo_artwork_url></server_response>";
	header ("Content-Type: text/xml");
	print $retXML;

}
else if($command == "get_artwork"){

	if(!$ProjectID)
		_PrintXMLError("The Project ID is missing.");

	_EnsurePermissionsOnProject($dbCmd, $ProjectID);
	
	
	$ProjectStatus = ProjectOrdered::GetProjectStatus($dbCmd, $ProjectID);
	
	if($ProjectStatus == "N" || $ProjectStatus == "C" || $ProjectStatus == "H")
		_PrintXMLError("The status on P$ProjectID has been changed.  The artwork is not available anymore.  The current status is " . ProjectStatus::GetProjectStatusDescription($ProjectStatus));

	$projectObj = ProjectOrdered::getObjectByProjectID($dbCmd, $ProjectID);

	// Parse the Artwork xml document and populate and Object will all the info we need
	$ArtworkXMLstring = ArtworkLib::GetArtXMLfile($dbCmd, "admin", $ProjectID);
	$ArtworkInfoObj = new ArtworkInformation($ArtworkXMLstring);


	// This array will contain a list of Objects used to generate a side of a PDF document...  EX:  There may be one for "Front" and one for "Back"
	//If this variable comes in the URL then it means that we want to overide the default profile.
	if(!empty($PDF_profile)){
	
		$ProductID = ProjectBase::GetProductIDFromProjectRecord($dbCmd, $ProjectID, "admin");
		$productionProductID = Product::getProductionProductIDStatic($dbCmd, $ProductID);
		$profileObj = new PDFprofile($dbCmd, $productionProductID);
	
		if(!$profileObj->checkIfPDFprofileExists($PDF_profile))
			_PrintXMLError("The PDF profile was not found");

		$PDFobjectsArr = PDFparameters::GetPDFparametersForProjectOrderedArr($dbCmd, $ProjectID, $PDF_profile);
	}
	else{
		$PDFobjectsArr = PDFparameters::GetPDFparametersForProjectOrderedArr($dbCmd, $ProjectID, "");
	}
	

	// This will generate the PDF document and return its contents
	$data = PdfDoc::GeneratePDFsheet($dbCmd, $PDFobjectsArr, $ArtworkInfoObj, "#FFFFFF", "", $projectObj->isVariableData());

	//Get a random filename
	$PDFfilename = "artwork_" . md5(microtime() . $ProjectID) . ".pdf";

	// Put PDF on disk
	$fp = fopen(DomainPaths::getPdfSystemPathOfDomainInURL() . "/" . $PDFfilename, "w");
	fwrite($fp, $data);
	fclose($fp);

	$ArtworkURL = DomainPaths::getPdfWebPathOfDomainInURL() . "/" . $PDFfilename;

	$retXML = "<?xml version=\"1.0\" ?>\n<server_response><result>OK</result><artwork_url>" . WebUtil::htmlOutput($ArtworkURL) . "</artwork_url></server_response>";
	header ("Content-Type: text/xml");
	print $retXML;

}
else if($command == "get_shipments_for_order"){

	// We need either a product ID or an order ID, we don't need both.
	// However, if we are using an orderID it must be acompanied with a parameter for the ProductID
	if(!$OrderID && !$ProjectID)
		_PrintXMLError("The Order ID and Project ID are missing.");

	// Get the order number and Product Number for the project.
	if($ProjectID){
		_EnsurePermissionsOnProject($dbCmd, $ProjectID);
		$ProductID = ProjectBase::GetProductIDFromProjectRecord($dbCmd, $ProjectID, "admin");
		$OrderID = Order::GetOrderIDfromProjectID($dbCmd, $ProjectID);
	}
		
	if(!$ProductID)
		_PrintXMLError("The Product ID is missing.");	
		
	_CheckForVendorPermissionsOnOrder($dbCmd, $OrderID, $UserID);
	
	
	// collect all of the shipping details.
	$dbCmd->Query( "SELECT * FROM orders WHERE ID=$OrderID" );
	$OrderInfo = $dbCmd->GetRow();
	
	if(!empty($OrderInfo["ShippingCompany"])){
		$shipTo_nameOrCompany = WebUtil::htmlOutput($OrderInfo["ShippingCompany"]);
		$shipTo_attention = WebUtil::htmlOutput($OrderInfo["ShippingName"]);
	}
	else{
		$shipTo_nameOrCompany = WebUtil::htmlOutput($OrderInfo["ShippingName"]);
		$shipTo_attention = "";
	}
	$shipTo_Address = WebUtil::htmlOutput($OrderInfo["ShippingAddress"]);
	$shipTo_AddressTwo = WebUtil::htmlOutput($OrderInfo["ShippingAddressTwo"]);
	$shipTo_City = WebUtil::htmlOutput($OrderInfo["ShippingCity"]);
	$shipTo_State = $OrderInfo["ShippingState"];
	$shipTo_Zip = $OrderInfo["ShippingZip"];
	$shipTo_Country = $OrderInfo["ShippingCountry"];
	$shipTo_ResidentialIndicator = $OrderInfo["ShippingResidentialFlag"];
	

	// Get a new account type object, depending on the person that placed the order
	// There may be a special case where the User wants a different ShipFrom address
	$AccountTypeObj = new AccountType($OrderInfo["UserID"], $dbCmd);
	$ShipFromHash = $AccountTypeObj->GetShippingFromAdress();

	$userObj = new UserControl($dbCmd);
	$userObj->LoadUserByID($UserID);
	
	// Get all shipment ID's in this order... belonging to this Vendor
	$shipmentIDarr = Shipment::GetShipmentsIDsWithinOrder($dbCmd, $OrderID, $ProductID, $UserID);
		
	$retXML = "<?xml version=\"1.0\" ?>\n<server_response>";
	$retXML .= "<result>OK</result>";
	
	foreach($shipmentIDarr as $ThisShipmentID){
	

		$ShipmentInfoHash = Shipment::GetInfoByShipmentID($dbCmd, $ThisShipmentID);
		
		$orderNumberFromShipID = Shipment::GetOrderIDfromShipmentID($dbCmd, $ThisShipmentID);
		
		if(!$orderNumberFromShipID){
			WebUtil::WebmasterError("Error in function Production API::get_shipments_for_order.  Shipment ID $ThisShipmentID does not exist.");
			throw new Exception("Error there is not an Order Number associated with the Shipment ID.");
		}
		
		$domainIDfromOrder = Order::getDomainIDFromOrder($orderNumberFromShipID);
		
		$shippingChoiceObjForOrder = ShippingChoices::getShippingChoiceObjectFromCache($domainIDfromOrder);
		
		$customerShippingChoiceID = Order::getShippingChoiceIDfromOrder($dbCmd, $orderNumberFromShipID);
		
		// It won't Downgrade Shipping if the Domain does not downgrade, or the if the User Settings restrict it.
		$adjMethodsList = ShippingTransfers::getAdjustedShippingMethods($dbCmd, $ThisShipmentID);

		
		// Make sure that the Shipping Choice did not become inactive after the Customer placed their order.
		if(!empty($adjMethodsList)){
			
			// The first Shipping Method in the list is the one we want to use.
			// Fint ouf if there is an alert message attached to the Top Shipping Method Code.
			reset($adjMethodsList);
			$topShippingMethodCode = current($adjMethodsList);
		
			$downGradedCarrier = $shippingMethodsObj->getShippingCarrierFromShippingCode($topShippingMethodCode);
			$downGradedMethod = $topShippingMethodCode;
			
			$shippingDowngradeMethodDesc = $shippingMethodsObj->getShippingMethodName($topShippingMethodCode);
			
			$shippingMethodAlert = $shippingChoiceObjForOrder->getAlertMessageForShippingMethod($customerShippingChoiceID, $topShippingMethodCode);
		}
		else{
			$downGradedMethod = "";
			$downGradedCarrier = "";
			$shippingMethodAlert = "((((((Unknown)))))";
		}

		
		$retXML .= "<shipment id=\"$ThisShipmentID\">"; 
		$retXML .= "\t<package_weight>" . $ShipmentInfoHash["PackageWeight"] .  "</package_weight>\n";
		$retXML .= "\t<package_type>" . $ShipmentInfoHash["PackageType"] .  "</package_type>\n";
		$retXML .= "\t<package_dimensions>" . $ShipmentInfoHash["PackageDimensions"] .  "</package_dimensions>\n";
		$retXML .= "\t<shipping_method_code>" . htmlspecialchars($downGradedMethod) .  "</shipping_method_code>\n";
		$retXML .= "\t<shipping_method_desc>" . htmlspecialchars($shippingDowngradeMethodDesc) .  "</shipping_method_desc>\n";
		$retXML .= "\t<shipping_carrier>" . htmlspecialchars($downGradedCarrier) .  "</shipping_carrier>\n";
		$retXML .= "\t<shipping_alert_message>" . htmlspecialchars($shippingMethodAlert) .  "</shipping_alert_message>\n";
		$retXML .= "\t<payment_method></payment_method>\n";
		$retXML .= "\t<reference_code>" . $OrderID . "-S" .  $ThisShipmentID . "</reference_code>\n";
		$retXML .= "\t<order_id>" . $OrderID  . "</order_id>\n";
		$retXML .= "\t<ship_country_code>" . htmlspecialchars($shipTo_Country)  . "</ship_country_code>\n";

		// Disable parts of the ShipTo part here.  The reason is that we are not really using anywhere yet.
		// The problem is that some customers enter illegal characters that are not able to be parsed within the XML document
		// It causes a problem when the client tries to parse the file... like for example � or �
		// We do need to know the Country Code within our Program PME link though.
		$retXML .= "\t<ship_to>";
		//$retXML .= "\t\t<name_or_company>$shipTo_nameOrCompany</name_or_company>\n";
		//$retXML .= "\t\t<attention>$shipTo_attention</attention>\n";
		//$retXML .= "\t\t<address>$shipTo_Address</address>\n";
		//$retXML .= "\t\t<address_two>$shipTo_AddressTwo</address_two>\n";
		//$retXML .= "\t\t<city>$shipTo_City</city>\n";
		//$retXML .= "\t\t<state>$shipTo_State</state>\n";
		//$retXML .= "\t\t<zip>$shipTo_Zip</zip>\n";
		//$retXML .= "\t\t<residential_indicator>$shipTo_ResidentialIndicator</residential_indicator>\n";
		$retXML .= "\t\t<country>$shipTo_Country</country>\n";
		$retXML .= "\t</ship_to>";
		

		$retXML .= "\t<ship_from>";
		$retXML .= "\t\t<name_or_company>" . WebUtil::htmlOutput($ShipFromHash["Company"]) . "</name_or_company>\n";
		$retXML .= "\t\t<attention>" . WebUtil::htmlOutput($ShipFromHash["Attention"]) . "</attention>\n";
		$retXML .= "\t\t<address>" . WebUtil::htmlOutput($ShipFromHash["Address"]) . "</address>\n";
		$retXML .= "\t\t<address_two>" . WebUtil::htmlOutput($ShipFromHash["AddressTwo"]) . "</address_two>\n";
		$retXML .= "\t\t<city>" . WebUtil::htmlOutput($ShipFromHash["City"]) . "</city>\n";
		$retXML .= "\t\t<state>" . $ShipFromHash["State"] . "</state>\n";
		$retXML .= "\t\t<zip>" . $ShipFromHash["Zip"] . "</zip>\n";
		$retXML .= "\t\t<country>" . WebUtil::htmlOutput($ShipFromHash["Country"]) . "</country>\n";
		$retXML .= "\t\t<phone>" . WebUtil::htmlOutput($ShipFromHash["Phone"]) . "</phone>\n";
		$retXML .= "\t\t<account_number>" . $ShipFromHash["UPS_AccountNumber"] . "</account_number>\n";
		$retXML .= "\t\t<tax_id>" . $ShipFromHash["TaxID"] . "</tax_id>\n";
		$retXML .= "\t\t<tax_id_type>" . $ShipFromHash["TaxIDtype"] . "</tax_id_type>\n";
		$retXML .= "\t\t<residential_indicator>" . $ShipFromHash["ResidentialIndicator"] . "</residential_indicator>\n";
		$retXML .= "\t</ship_from>";


		//Now there may be many projects within each shipment... we want display each project ID and their status/quantity
		$ShipmentProjectsArr = Shipment::ShipmentGetProjectInfo($dbCmd, $ThisShipmentID);
		
		foreach($ShipmentProjectsArr as $ThisProjectDetail){
		
			//Each project detail has info separated by pipe symbols... EX: ProjectID|Quantity|ShipmentLinkID
			$x = split("\|", $ThisProjectDetail);
			$thisProjectID = $x[0];
			$thisQuantity = $x[1];
			
			$retXML .= _XML_Build_ProjectNode($thisProjectID, $dbCmd2);
		}
		
		$retXML .= "</shipment>"; 
	}
	$retXML .= "</server_response>"; 
	
	header ("Content-Type: text/xml");

	print $retXML;
	
}
else if($command == "complete_shipment"){


	if(!$ShipmentReference)
		_PrintXMLError("The Shipment Reference ID is missing.");
		
	if(!empty($ShipmentCarrier))
		_PrintXMLError("The Shipment Carrier is missing.");
		
	if(!preg_match("/^((\d+\.\d+)|\d+)$/", $ShipmentCost))
		_PrintXMLError("The Shipment Cost is incorrect.  It must be numeric, and possibly contain a decimal point.");

	// The format of the Order Number variable should come in like "234-S452"  MainOrderID - ShipmentID
	$matches = array();
	if(preg_match_all("/^(\d+)-S(\d+)$/", $ShipmentReference, $matches)){

		$mainorder_ID = $matches[1][0];
		$Shipment_ID = $matches[2][0];
	}
	else{
		_PrintXMLError("The format of the shipment reference is incorrect.");
	}

	if(!$ShipmentTracking)
		_PrintXMLError("The shipment tracking number is missing.");

	_CheckForVendorPermissionsOnOrder($dbCmd, $mainorder_ID, $UserID);
	
	$dbCmd->Query("SELECT COUNT(*) FROM shipments WHERE ID=$Shipment_ID");
	$ShipmentCheck = $dbCmd->GetValue();
	
	if($ShipmentCheck == 0)
		_PrintXMLError("The shipment ID is incorrect.");
		
	$OrderIDFromShipmentID = Shipment::GetOrderIDfromShipmentID($dbCmd, $Shipment_ID);
	
	if($OrderIDFromShipmentID <> $mainorder_ID)
		_PrintXMLError("The order ID does not match the shipment ID.");
		

	$PaymentsObj = new Payments(Order::getDomainIDFromOrder($OrderIDFromShipmentID));
			
			
	ShippingTransfers::CompleteShipment($dbCmd, $PaymentsObj, $UserID, $ShipmentTracking, $Shipment_ID, $ShipmentCost, $ShipmentCarrier);
	
	$retXML = "<?xml version=\"1.0\" ?>\n<server_response><result>OK</result></server_response>";
	header ("Content-Type: text/xml");
	print $retXML;
}


else if($command == "remove_from_rack"){

	if(!$ProjectID)
		_PrintXMLError("The Project ID is missing.");

	_EnsurePermissionsOnProject($dbCmd, $ProjectID);

	
	try{	
		$ProductID = ProjectBase::GetProductIDFromProjectRecord($dbCmd, $ProjectID, "admin");
		
		$productionProductID = Product::getProductionProductIDStatic($dbCmd, $ProductID);
		$domainIDofProductionProductID = Product::getDomainIDfromProductID($dbCmd, $productionProductID);
		
		$RackControlObj = new RackControl($dbCmd2, $domainIDofProductionProductID);
		
		$RackControlObj->RemoveProjectFromRack($ProjectID);
		
	}
	catch (Exception $e){
		_PrintXMLError($e->getMessage());
	}
	
	ProjectHistory::RecordProjectHistory($dbCmd2, $ProjectID, "Removed From Rack", $UserID);
	
	$retXML = "<?xml version=\"1.0\" ?>\n<server_response><result>OK</result></server_response>";
	header ("Content-Type: text/xml");
	print $retXML;
	
}

else if($command == "production_scan"){

	if(!$ProjectID)
		_PrintXMLError("The Project ID is missing.");

	_EnsurePermissionsOnProject($dbCmd, $ProjectID);
	
	$ProductionCommand = "";
	
	$ProjectStatus = ProjectOrdered::GetProjectStatus($dbCmd, $ProjectID);	
	
	if($ProjectStatus <> "T")
		_PrintXMLError("You can not do a production scan unless the project has a status of Printed.  The status for this project is currently set to " . ProjectStatus::GetProjectStatusDescription($ProjectStatus));
	
	$ProductID = ProjectBase::GetProductIDFromProjectRecord($dbCmd, $ProjectID, "ordered");
	$orderID = Order::GetOrderIDfromProjectID($dbCmd, $ProjectID);
	
	
	$productionProductID = Product::getProductionProductIDStatic($dbCmd, $ProductID);
	$domainIDofProductionProductID = Product::getDomainIDfromProductID($dbCmd, $productionProductID);
	$productObj = new Product($dbCmd, $ProductID);
	
	// If the project is going to be put on the rack... append to the Project History after that status change.
	$rackSpaceProjectHistory = "";
	
	try{
		$RackControlObj = new RackControl($dbCmd2, $domainIDofProductionProductID);
		
		if($RackControlObj->CheckIfProjectsIsOnRack($ProjectID)){
			ProjectHistory::RecordProjectHistory($dbCmd2, $ProjectID, "Error with Production Scan. Project is marked as being on the rack already with a Printed status.", $UserID);
			_PrintXMLError("Some type of mixup has occured.  The status of this project is printed but it is also recorded as being on a rack.  You may need to clear the rack position for this order and start doing production scans again.");
		}
		
		// Set this Flag to TRUE if we want the order taken off of the rack after the message is sent back
		$rmOrderFrmRackFlag = false;
		
		// Find out if other projects from the same order are already on the rack.  This also indicates that there are multiple projects in this order
		if($RackControlObj->CheckForOrderOnRackForProductGroup($orderID, $ProductID)){
		
			$RackHash = $RackControlObj->GetPositionOfOrderOnRack($orderID);
			
			// If so, there are multiple projects on the rack... but the current project is the last one coming through, so it is ready to ship and we should gather the remainder from the rack
			if($RackControlObj->CheckIfProjectIsLastInOrderForProductGroup($ProjectID)){
				
				$rackSpaceProjectHistory = "Fetch other projects from Rack for shipment: " . $RackHash["RackTranslate"] . " " . $RackHash["RowTranslate"] . $RackHash["ColumnTranslate"];
				
				$ProductionCommand = "READY_TO_SHIP_COMBINED";
				$rmOrderFrmRackFlag = true;
			}
			else{
				// Records the position of this project to be the same as the order that is alredy on the rack
				$RackControlObj->PutProjectOnRack($RackHash["Rack"], $RackHash["Row"], $RackHash["Column"], $ProjectID);
				
				$rackSpaceProjectHistory = "Put on rack (existing): " . $RackHash["RackTranslate"] . " " . $RackHash["RowTranslate"] . $RackHash["ColumnTranslate"];
				
				$ProductionCommand = "PUT_ON_RACK";
			}
		}
		else if($RackControlObj->CheckIfProjectIsLastInOrderForProductGroup($ProjectID)){
		
			// If it is the last project in the order... and there are no other projects on the rack, then this must be a Single-project order
			// Otherwise, this is the first of many projects in the order, we need to set it on the rack.
			$ProductionCommand = "READY_TO_SHIP_SOLO";
		}
		else{
			if(!$RackControlObj->CheckForOpenSlot()){
				ProjectHistory::RecordProjectHistory($dbCmd, $ProjectID, "No spaces available on rack.", $UserID);
				_PrintXMLError("There is no more space available on the racks.  Plese put this project asside.  Hopefully more space will become available.");
			}
			
			// Record the Project as being on the next slot opening
			$RackHash = $RackControlObj->GetNextSlotOpening();
			$RackControlObj->PutProjectOnRack($RackHash["Rack"], $RackHash["Row"], $RackHash["Column"], $ProjectID);
			
			$RackHash = $RackControlObj->GetPositionOfOrderOnRack($orderID);
			$rackSpaceProjectHistory = "Put on rack (new): " . $RackHash["RackTranslate"] . " " . $RackHash["RowTranslate"] . $RackHash["ColumnTranslate"];
			
			$ProductionCommand = "PUT_ON_RACK";
		}
	}
	catch (Exception $e){
		_PrintXMLError($e->getMessage());
	}
	
	
	
	
	// Don't show shipping instructions until we have the last project (ready for shipment).
	$shippingInstructionsForXML = "";

	// Find out if we want to print any promotional material for this user
	// Basically we are just sending a command to the client software application in the wharehouse
	// It is up to that client program to accept the delegation and make further calls back to this API to complete the task.
	// Don't send promotional delegates is the Customer is a reseller.
	$promoCommandsArr = array();
	if($ProductionCommand == "READY_TO_SHIP_SOLO" || $ProductionCommand == "READY_TO_SHIP_COMBINED"){

		$dbCmd->Query("SELECT UserID FROM orders INNER JOIN projectsordered ON orders.ID = projectsordered.OrderID WHERE projectsordered.ID=$ProjectID");
		$userIDFromProject = $dbCmd->GetValue();
		
		// Do not print any promotional artwork if the Customer is a Reseller because they could be drop shipping.
		$userObj = new UserControl($dbCmd);
		$userObj->LoadUserByID($userIDFromProject);
		
		if($userObj->getAccountType() == "N")
			$promoCommandsArr = $productObj->getPromotionalCommandsArr();
			
		$shippingInstructionsForXML = Order::getShippingInstructions($dbCmd, $orderID);
	}



	// Change Status to Boxed.  
	// In case any errors occured above.  It would have aborted and not marked this project as boxed
	ProjectOrdered::ChangeProjectStatus($dbCmd, "B", $ProjectID);
	ProjectHistory::RecordProjectHistory($dbCmd, $ProjectID, "B", $UserID);
	
	if(!empty($rackSpaceProjectHistory)){
		ProjectHistory::RecordProjectHistory($dbCmd, $ProjectID, $rackSpaceProjectHistory, $UserID);
	}
	

	$retXML = "<?xml version=\"1.0\" ?>\n
			<server_response>
			<result>OK</result>
			<message>". $ProductionCommand ."</message>\n
			<shipping_instructions>". WebUtil::htmlOutput($shippingInstructionsForXML) ."</shipping_instructions>\n
			" . _XML_Build_ProjectNode($ProjectID, $dbCmd2) . "
			" . _XML_Build_RackControlNode($ProjectID, $dbCmd2);

	// show zero or many promo command delegates for this product.
	foreach($promoCommandsArr as $thisPromoCommand)
		$retXML .= "<promo_command_delegate>" . $thisPromoCommand . "</promo_command_delegate>\n";

	$retXML .= "</server_response>";

	try{
		if($rmOrderFrmRackFlag)
			$RackControlObj->RemoveOrderFromRackForProductGroup(Order::GetOrderIDfromProjectID($dbCmd, $ProjectID), $productionProductID);
	}
	catch(Exception $e){
		_PrintXMLError($e->getMessage());
	}
	
	header ("Content-Type: text/xml");
	print $retXML;

}
else if($command == "get_project_count"){

	// The URL sends through a pipe delimeted list of BatchID's.  We need to put it in the array
	// A batch list is just an arbitrary number that defines a group of parameters.  It doesn't reference anything in the system, the batchID is most useful to the client's code
	$BatchIDarr = array();
	
	$BatchList = WebUtil::GetInput("batch_id_list", FILTER_SANITIZE_STRING_ONE_LINE);
	
	// Validate that all BatchID's are digits
	$TempArr = split("\|", $BatchList);
	foreach($TempArr as $ThisID)
		if(preg_match("/^\d+$/", $ThisID))
			$BatchIDarr[] = $ThisID;
			
	if(sizeof($BatchIDarr) == 0)
		_PrintXMLError("No Batch IDs have been sent");
		
	
	// Make the cuttoff time near the end of today.. that way any time estimate will show up as due today.
	$TodayCuttofTime = mktime (23,59,59,date("n"),date("j"),date("Y")); 
	$VendorLimiter = $UserID;


	$retXML = "<?xml version=\"1.0\" ?>\n<server_response>\n<result>OK</result>";

		
	// Every Batch ID has a corresponding list of parameters that go with it telling us how to count the orders
	foreach($BatchIDarr as $ThisBatchID){
		
		$Status = WebUtil::GetInput("sts_" . $ThisBatchID, FILTER_SANITIZE_STRING_ONE_LINE);
		$ProductID = WebUtil::GetInput("pID_" . $ThisBatchID, FILTER_SANITIZE_STRING_ONE_LINE);
		$Options = WebUtil::GetInput("Opt_" . $ThisBatchID, FILTER_SANITIZE_STRING_ONE_LINE);
		$DueToday = WebUtil::GetInput("DT_" . $ThisBatchID, FILTER_SANITIZE_STRING_ONE_LINE);
		$shippingPriorityIntegers = WebUtil::GetInput("Shp_" . $ThisBatchID, FILTER_SANITIZE_STRING_ONE_LINE);
		$priority = WebUtil::GetInput("Pri_" . $ThisBatchID, FILTER_SANITIZE_STRING_ONE_LINE);
		$reprintIndicator = WebUtil::GetInput("Rep_" . $ThisBatchID, FILTER_SANITIZE_STRING_ONE_LINE);
		$projectHistorySearch = WebUtil::GetInput("His_" . $ThisBatchID, FILTER_SANITIZE_STRING_ONE_LINE);
		$MaxProjectQuantity = WebUtil::GetInput("mxPrQ_" . $ThisBatchID, FILTER_SANITIZE_INT);
		$MinProjectQuantity = WebUtil::GetInput("mnPrQ_" . $ThisBatchID, FILTER_SANITIZE_INT);
		
	
		if(!empty($reprintIndicator) && ($reprintIndicator != 'Y' && $reprintIndicator != 'N'))
			_PrintXMLError("If searching project lists by reprints, the value must either be Null, Y, or N.");

		if(!$Status || !$ProductID)
			_PrintXMLError("You are missing the status Character or the ProductID");
		
		
		// For production purposes, right now we are only worried about when it will print, we don't carry about the ship date
		$EstShipDate = "";
		
		if($DueToday == "yes")
			$EstPrintDate = $TodayCuttofTime;
		else
			$EstPrintDate = "";

		// We don't want to have separate batches for Variable Postcards vs. Static postcards.
		// They can all be batched up and worked on together, with regards to printing and other functions
		$productIDarr = Product::getAllProductIDsSharedForProduction($dbCmd, $ProductID);

		$OrderCount = $orderQryObj->GetOrderCount($VendorLimiter, $Status, $productIDarr, $shippingPriorityIntegers, "", "", "", $EstShipDate, $EstPrintDate, $Options, $priority, $reprintIndicator, $projectHistorySearch, $MinProjectQuantity, $MaxProjectQuantity);
		
		$retXML .= "<batch_count batchid=\"$ThisBatchID\">$OrderCount</batch_count>";
		
		
		
		// Now get a pipe delimeted list that matches our Order Count criteria.  The Status, Shipping Characters, etc. are stored in the object from the last call to "GetOrderCount";
		// Then turn the piped list into an array
		$pipeDelimitedProjectList = $orderQryObj->GetPipeDelimitedProjectIDlist(0, 0, 0);
		$projectListArr = $orderQryObj->GetProjectArrayFromProjectList($pipeDelimitedProjectList);
		
			
		// Find out the total number of impressions required to print the array of Project ID's
		$impressionsCount = PdfDoc::getPageCountsByProjectList($dbCmd, $projectListArr, $PDF_profile, "ImpressionCount");
		
		$retXML .= "<impressions_count batchid=\"$ThisBatchID\">$impressionsCount</impressions_count>";

	}

	
	$retXML .= "</server_response>";
	
	header ("Content-Type: text/xml");
	print $retXML;

}
// This method will return a list of project ID's separated by Pipe symbols matching the given order report details
// It will not return a list over the Project Cap... Otherwise there could be a problem with when the project list is sent back to the server through the URL... There is a 2056 byte limit through Internet explorer... Less than 200 Project ID's should be safe.
else if($command == "get_project_list"){

	$ProjectCap = WebUtil::GetInput("projectcap", FILTER_SANITIZE_INT, "200");
	$MaxPages = WebUtil::GetInput("maxpages", FILTER_SANITIZE_INT, "20000");

	// The Project Cap says how many project we can work with at a single time.
	// If you ask the API to return you a list of Project ID's waiting to be printed, it will not return more Project ID's than the project cap
	// The reason we can't go over 200 is because it could exceed the limit of data that is possible to send through a GET request in the URL.  I don't have a way to POST data yet through the client APPs.  GET is much easier to work with.
	// The client app may want to configure there own Project Cap.  Let's say they are generating a giant artwork file and it is failing on the printing press for some reason.  Changing the Project Cap would all them to break the Job into as many pieces as they need so they can figure out the file causing a problem.
	if(!preg_match("/^\d+$/", $ProjectCap) || $ProjectCap < 1 || $ProjectCap > 200)
		_PrintXMLError("The project cap must be a number between 1 and 200");

	// Works with the Project Cap.... it will cut off of the project list if the amount of pages needed for the batch goes over the limit	
	if(!preg_match("/^\d+$/", $MaxPages) || $MaxPages < 1 || $MaxPages > 1000000)
		_PrintXMLError("The max pages must be a number between 1 and 1,000,000");


	//Make the cuttoff time near the end of today.. that way any time estimate will show up as due today.
	$TodayCuttofTime = mktime (23,59,59,date("n"),date("j"),date("Y")); 
	$VendorLimiter = $UserID;

	$Status = WebUtil::GetInput("status", FILTER_SANITIZE_STRING_ONE_LINE);
	$ProductID = WebUtil::GetInput("productid", FILTER_SANITIZE_INT);
	$Options = WebUtil::GetInput("options", FILTER_SANITIZE_STRING_ONE_LINE);
	$DueToday = WebUtil::GetInput("duetoday", FILTER_SANITIZE_STRING_ONE_LINE);
	$priority = WebUtil::GetInput("priority", FILTER_SANITIZE_STRING_ONE_LINE);
	$shippingPriorityIntegers = WebUtil::GetInput("shippingmethods", FILTER_SANITIZE_STRING_ONE_LINE);
	$reprintIndicator = WebUtil::GetInput("reprint", FILTER_SANITIZE_STRING_ONE_LINE);
	$projectHistorySearch = WebUtil::GetInput("history", FILTER_SANITIZE_STRING_ONE_LINE);
	$MaxProjectQuantity = WebUtil::GetInput("maxProjectQuantity", FILTER_SANITIZE_INT);
	$MinProjectQuantity = WebUtil::GetInput("minProjectQuantity", FILTER_SANITIZE_INT);
	
	
	if(!empty($reprintIndicator) && ($reprintIndicator != 'Y' && $reprintIndicator != 'N'))
		_PrintXMLError("If searching project lists by reprints, the value must either be Null, Y, or N.");
	

	if(!$Status || !$ProductID)
		_PrintXMLError("You are missing the status Character or the ProductID");

	// For production purposes we only care about when we are supposed to print an item.  Shipping Date should flow naturally after it has been printed
	$EstShipDate = "";

	if($DueToday == "yes")
		$EstPrintDate = $TodayCuttofTime;
	else
		$EstPrintDate = "";
		
	$productionProductID = Product::getProductionProductIDStatic($dbCmd, $ProductID);
	$pdfProfileObj = new PDFprofile($dbCmd, $productionProductID);

	
	if(!$pdfProfileObj->checkIfPDFprofileExists($PDF_profile))
		_PrintXMLError("The PDF profile was not found");

	$pdfProfileObj->loadProfileByName($PDF_profile);

	// We don't want to have separate batches for Variable Postcards vs. Static postcards.
	// They can all be batched up and worked on together, with regards to printing and other functions
	$productIDarr = Product::getAllProductIDsSharedForProduction($dbCmd, $ProductID);


	// Get an Array of Project ID's that fall within our criteria.
	$orderQryObj->SetProperties($VendorLimiter, $Status, $productIDarr, $shippingPriorityIntegers, "", "", "", $EstShipDate, $EstPrintDate, $Options, $priority, $reprintIndicator, $projectHistorySearch, $MinProjectQuantity, $MaxProjectQuantity);
	$ProjectListStr = $orderQryObj->GetPipeDelimitedProjectIDlist($ProjectCap, $MaxPages, $pdfProfileObj->getQuantity());
	$projectIDarr = $orderQryObj->GetProjectArrayFromProjectList($ProjectListStr);


	// There is a bug that I have not been able to pin-point... It happens very rarely.  I thought that I had fixed it but it showed up yesterday after 2-3 months of calm.
	// Sometimes the Product Options will say Double-Sided when there is really a Single-sided Artwork.
	// Putting a Single-Sided artwork into a Double-Sided queue can throw off the interleaving and destroy an entire batch of printing
	// Just as an extra safety net... we are checking how many sides to the Artwork on each one of these Projects.
	// We will skip any projects that don't have the same number of artwork sides as the first project in the List.
	// If we ever see any projects sitting in the queue that never get picked up then we will know that this trap caught some little boogers.
	$numberOfArtworkSidesOnFirstArtwork = null;
	
	// Keep a pipe separated list of the Project ID's

	$filteredProjectIDlist = "";
	$filteredProjectIDcount = 0;
	

	// Calculate the total number of page impressions for the batch. 5 sheets of double-sided stock equals 10 impressions.
	$impressionsCount = 0;
	
	foreach($projectIDarr as $thisProjectID){

		$xmlString = ArtworkLib::GetArtXMLfile($dbCmd, "ordered", $thisProjectID);
		
		$countArr = array();
		preg_match_all ("/<side>/", $xmlString, $countArr);
		$numberOfArtworkSides = sizeof($countArr[0]);
		
		
		// This will make sure that the number of sides are always consistent within a batch.
		if(empty($numberOfArtworkSidesOnFirstArtwork))
			$numberOfArtworkSidesOnFirstArtwork = $numberOfArtworkSides;
		if($numberOfArtworkSides != $numberOfArtworkSidesOnFirstArtwork)
			continue;
		
		$filteredProjectIDlist .= $thisProjectID . "|";
		$filteredProjectIDcount++;


		// Number of impressions required for this Project.
		$impressionsCount += PdfDoc::getPageCountsByProjectList($dbCmd, array($thisProjectID), $PDF_profile, "ImpressionCount");
	
	}


	
	$retXML = "<?xml version=\"1.0\" ?>\n<server_response>\n<result>OK</result>";
	$retXML .= "<project_list>" . $filteredProjectIDlist . "</project_list>";
	$retXML .= "<project_count>" . $filteredProjectIDcount . "</project_count>";
	$retXML .= "<impressions_count>" . $impressionsCount . "</impressions_count>";
	$retXML .= "</server_response>";
	
	header ("Content-Type: text/xml");
	print $retXML;

}
else if($command == "generate_pdf_doc_from_projectlist"){


	// Gets a pipe deliminated list of Project ID's as well as a PDF profile.
	// We want to generate a PDF document containing all of the artwork for the projects into 1 document and send back the filename
	$ProjectList = WebUtil::GetInput("project_list", FILTER_SANITIZE_STRING_ONE_LINE);
	$FileNamePrefix = WebUtil::GetInput("filename_prefix", FILTER_SANITIZE_STRING_ONE_LINE);
	$PDF_profile = WebUtil::GetInput("pdf_profile_name", FILTER_SANITIZE_STRING_ONE_LINE);
	$ProductID = WebUtil::GetInput("productid", FILTER_SANITIZE_INT);
	
	if(!preg_match("/^\d+$/", $ProductID))
		_PrintXMLError("The Product ID is missing or incorrect: " . $ProductID);
		
	if(empty($ProjectList))
		_PrintXMLError("The project list is missing.");
	
	$ProjectListArr = $orderQryObj->GetProjectArrayFromProjectList($ProjectList);

	$productionProductID = Product::getProductionProductIDStatic($dbCmd, $ProductID);
	
	$profileObj = new PDFprofile($dbCmd, $productionProductID);
	if(!$profileObj->checkIfPDFprofileExists($PDF_profile))
		_PrintXMLError("The PDF profile was not found: " . WebUtil::htmlOutput($PDF_profile) . ": for Production Product ID:" . $productionProductID);
	
	header ("Content-Type: text/xml");
	
	// We are going to generate the PDF document and send the filename back through XML
	// It may take a while to generate the document so the XML file will constantly have the output buffer flushed after each project
	// Every time a project has been generated the function will send back the progress inside of an XML node.
	$retXML = "<?xml version=\"1.0\" ?>\n<server_response>";
	$retXML .= "<progress>";
	print $retXML;
	
	Constants::FlushBufferOutput();

	
	$BatchID = date("Y:m:d:H:i:s");
	
	
	// Find out if ther is an Urgent Status or Expedited Shipping mixed in the list.
	// Then we add a U- to the prefix of the filename.
	$urgentStatus = false;
	$expeditedShipping = false;
	
	foreach($ProjectListArr as $thisProjectID){

		_EnsurePermissionsOnProject($dbCmd, $thisProjectID);
	
		$dbCmd->Query("SELECT Priority, ShippingPriority FROM projectsordered WHERE ID=" . $thisProjectID);
		$row = $dbCmd->GetRow();
		$projectPriority = $row["Priority"];
		$shippingPriority = $row["ShippingPriority"];
		
		if($projectPriority == "U"){
			$urgentStatus = true;
			break;
		}
		
		if(in_array($shippingPriority, ShippingChoices::getExpeditedShippingPriorities()))
			$expeditedShipping = true;
	}
	
	
	// If there is a Project Inside with an Urgent Status... then prefix the Filename with U-
	// Otherwise if there is a Project Inside with expedited shipping prefix with E-
	// Otherwise just leave the filename prefix alone.
	if($urgentStatus && $expeditedShipping)
		$FileNamePrefix = "UE-" . $FileNamePrefix;
	else if($urgentStatus)
		$FileNamePrefix = "U-" . $FileNamePrefix;
	else if($expeditedShipping)
		$FileNamePrefix = "E-" . $FileNamePrefix;
	
	
	$PDF_filename = PdfDoc::GenerateSingleFilePDF($dbCmd, $ProjectListArr, $PDF_profile, $FileNamePrefix, $BatchID, "xml_progress");


	$DownloadURL = DomainPaths::getPdfWebPathOfDomainInURL() . "/" . $PDF_filename;
	
	$retXML = "";
	$retXML .= "</progress>";
	$retXML .= "<filename>" . $PDF_filename . "</filename>\n";
	$retXML .= "<download_url>" . $DownloadURL . "</download_url>\n";
	$retXML .= "<result>OK</result>";
	$retXML .= "</server_response>";
	
	print $retXML;

}
else{
	_PrintXMLError("Invalid Command.");

}





#####---------------     Private Functions for this script   -----------------######





// Will build a XML structure for specific project
function _XML_Build_ProjectNode($ProjectID, &$dbCmd){

	$projectObj = ProjectOrdered::getObjectByProjectID($dbCmd, $ProjectID);

	$dbCmd->Query("SELECT *, UNIX_TIMESTAMP(DateOrdered) AS TheDateOrdered, ShippingChoiceID FROM orders WHERE ID=" . $projectObj->getOrderID());
	$order_row = $dbCmd->GetRow();

	$productionNotes = WebUtil::htmlOutput($projectObj->getNotesProduction());
	
	// If there are any extra production notes... they should be displayed above the "manually entered" production notes.
	$extraProductionNotes = $projectObj->getExtraProductionAlert();
	
	if($extraProductionNotes != "")
		$productionNotes .= WebUtil::htmlOutput(" ****** Product Note: " . $extraProductionNotes);

	$userObj = new UserControl($dbCmd);
	$userObj->LoadUserByID($order_row["UserID"]);
	if($userObj->getAccountType() == "R")
		$productionNotes .= WebUtil::htmlOutput(" ############# Reseller #############: Make sure that the Inner / Outer Shipping Boxes (and possibly inserts) do not have any Company Logos or other Branding.");

		
	$shippingChoiceObj = new ShippingChoices($projectObj->getDomainID());
	$defaultShippingMethodCode = $shippingChoiceObj->getDefaultShippingMethodCode($order_row["ShippingChoiceID"]);
	
	$shippingMethodObj = new ShippingMethods($projectObj->getDomainID());
	$shippingMethodDesc = $shippingMethodObj->getShippingMethodName($defaultShippingMethodCode);

	$retXML = "";
	$retXML .= "<project id=\"" . $ProjectID . "\">";

	$retXML .= "\t<product_id>" . $projectObj->getProductID() . "</product_id>";
	$retXML .= "\t<quantity>" . $projectObj->getQuantity() . "</quantity>";
	$retXML .= "\t<status description=\"" . ProjectStatus::GetProjectStatusDescription($projectObj->getStatusChar()) . "\">" . $projectObj->getStatusChar() . "</status>";
	$retXML .= "\t<order_id>" . $projectObj->getOrderID() . "</order_id>";
	$retXML .= "\t<ship_country_code>" . $order_row["ShippingCountry"] . "</ship_country_code>";
	$retXML .= "\t<ship_carrier>" . htmlspecialchars($shippingMethodObj->getShippingCarrierFromShippingCode($defaultShippingMethodCode)) . "</ship_carrier>";
	$retXML .= "\t<ship_method>" . htmlspecialchars($defaultShippingMethodCode) . "</ship_method>";
	$retXML .= "\t<ship_method_description>" . htmlspecialchars($shippingMethodDesc) . "</ship_method_description>";
	$retXML .= "\t<ship_priority>" . htmlspecialchars($shippingChoiceObj->getPriorityDescription($shippingChoiceObj->getPriorityOfShippingChoice($order_row["ShippingChoiceID"]))) . "</ship_priority>";
	$retXML .= "\t<date_ordered>" . $order_row["TheDateOrdered"] . "</date_ordered>";
	$retXML .= "<notes>" . $productionNotes . "</notes>";
	$retXML .= "<order_description>" . WebUtil::htmlOutput($projectObj->getOrderDescription()) . "</order_description>";
	$retXML .= "<options_description>(" . WebUtil::htmlOutput($projectObj->getOptionsAliasStr()) . ")</options_description>";
	
	foreach($projectObj->getOptionsAndSelections() as $ThisOptionName => $ThisChoice){
		$optionNameAlias = $projectObj->getProductObj()->getOptionDetailObj($ThisOptionName)->optionNameAlias;
		$choiceNameAlias = $projectObj->getProductObj()->getChoiceDetailObj($ThisOptionName, $ThisChoice)->ChoiceNameAlias;
		$retXML .= "\t<option name=\"" . WebUtil::htmlOutput($optionNameAlias) . "\">" . WebUtil::htmlOutput($choiceNameAlias)  . "</option>";
	}

	$retXML .= "</project>";


	return $retXML;
}


function _XML_Build_RackControlNode($ProjectID, DbCmd $dbCmd){
	
	$ProjectID = intval($ProjectID);

	$productID = ProjectBase::getProductIDquick($dbCmd, "ordered", $ProjectID);
	
	$productionProductID = Product::getProductionProductIDStatic($dbCmd, $productID);
	$domainIDofProductionProduct = Product::getDomainIDfromProductID($dbCmd, $productionProductID);
	
	$orderID = Order::GetOrderIDfromProjectID($dbCmd, $ProjectID);
	
	try{
		
		$RackControlObj = new RackControl($dbCmd, $domainIDofProductionProduct);

		// Find out if this project is on the rack
		if($RackControlObj->CheckIfProjectsIsOnRack($ProjectID))
			$ProjectIsOnRack = "yes";
		else
			$ProjectIsOnRack = "no";

		// If there is nothing on the rack from this order... then show an empty location node
		if($RackControlObj->CheckForOrderOnRack($orderID)){

			$RackHash = $RackControlObj->GetPositionOfOrderOnRack($orderID);

			$LocationXML = "<location>
				<rack_number>". $RackHash["Rack"] . "</rack_number>
				<rack_text>" . WebUtil::htmlOutput($RackHash["RackTranslate"]) . "</rack_text>
				<row_number>". $RackHash["Row"] . "</row_number>
				<row_text>" . WebUtil::htmlOutput($RackHash["RowTranslate"]) . "</row_text>
				<column_number>". $RackHash["Column"] . "</column_number>
				<column_text>" . WebUtil::htmlOutput($RackHash["ColumnTranslate"]) . "</column_text>
				</location>";
		}
		else{
			// Just show an empty location node, since it is not on the rack anywhere
			$LocationXML = "<location></location>";
		}

		$retXML = "<rack_control>";
		$retXML .= "<projects_in_order_in_product_group>". $RackControlObj->GetTotalProjectsInOrderForProductGroup($orderID, $productID) ."</projects_in_order_in_product_group>";

		$retXML .= "<project_is_on_rack>". $ProjectIsOnRack ."</project_is_on_rack>";
		$retXML .= "<projects_on_rack_from_order_in_product_group>". $RackControlObj->GetCountOfProjectsOnRackFromOrderForProductGroup($orderID, $productID) ."</projects_on_rack_from_order_in_product_group>";

		$retXML .= "<boxes_on_rack_from_order_in_product_group>" . $RackControlObj->GetBoxesOnRackFromOrderForProductGroup($orderID, $productID) . "</boxes_on_rack_from_order_in_product_group>";

		$retXML .= "<boxes_for_project>" . $RackControlObj->GetBoxesInProject($ProjectID) . "</boxes_for_project>";

		$retXML .= "<boxes_for_order_in_product_group>" . $RackControlObj->GetBoxesInOrderForProductGroup($orderID, $productID) . "</boxes_for_order_in_product_group>";
	
		$retXML .= $LocationXML;
		$retXML .= "</rack_control>";
	}
	catch (Exception $e){
		_PrintXMLError($e->getMessage());
	}
	
	return $retXML;

}


// The User can either belong to the Domain or Production Piggyback... 
// or their UserID can be in the Vendor ID Group of the Project.
function _EnsurePermissionsOnProject($dbCmd, $ProjectID){
	
	$dbCmd->Query("SELECT COUNT(*) FROM projectsordered WHERE ID=" . intval($ProjectID));
	$projectCount = $dbCmd->GetValue();
	if($projectCount == 0)
		_PrintXMLError("This project is not available or the project number is wrong.");

	$productIDofProject = ProjectBase::getDomainIDofProjectRecord($dbCmd, "ordered", $ProjectID);
	$productionPiggyBack = Product::getProductionProductIDStatic($dbCmd, $productIDofProject);
	$domainIDofProductionPiggyBack = Product::getDomainIDfromProductID($dbCmd, $productionPiggyBack);

	$passiveAuthObj = Authenticate::getPassiveAuthObject();
	$userID = $passiveAuthObj->GetUserID();
	
	if(!$passiveAuthObj->CheckIfUserCanViewDomainID($domainIDofProductionPiggyBack) && !_CheckForVendorProjectInclusion($dbCmd, $ProjectID, $userID))
		_PrintXMLError("This project is not available or the project number is wrong.");
}
function _CheckForVendorProjectInclusion($dbCmd, $ProjectID, $VendorID){

	if(!preg_match("/^\d+$/", $ProjectID))
		_PrintXMLError("Illegal Project ID");

	// If the vendor doesn't have permissions to view the 
	$dbCmd->Query("SELECT COUNT(*) FROM projectsordered WHERE ID=$ProjectID AND (" . Product::GetVendorIDLimiterSQL($VendorID) . ")");
	if($dbCmd->GetValue() == 1)
		return true;
	else
		return false;
}

function _CheckForVendorPermissionsOnOrder($dbCmd, $OrderID, $VendorID){

	if(!preg_match("/^\d+$/", $OrderID))
		_PrintXMLError("Illegal Order ID");
		
	$passiveAuthObj = Authenticate::getPassiveAuthObject();
		
	// Find out all of the ProductIDs in the Orders... and convert them to Production Product ID's
	// If the User is able to view at least one of the Production Domain Product ID's... then they have permission to see the order.
	$dbCmd->Query("SELECT DISTINCT ProductID FROM projectsordered WHERE OrderID=" . intval($OrderID));
	$allProductIDsArr = $dbCmd->GetValueArr();
	foreach($allProductIDsArr as $thisProductID){
		
		$productionProductID = Product::getProductionProductIDStatic($dbCmd, $thisProductID);
		$domainIDofProductionID = Product::getDomainIDfromProductID($dbCmd, $productionProductID);
		
		// Skip the Rest of the authentication if the user has permission on at least one of the Products.
		if($passiveAuthObj->CheckIfUserCanViewDomainID($domainIDofProductionID))
			return;
	}

	// As an alternative to having permission to the Production Product ID Domain... If their User ID is included within one of the Product Vendor ID's... then that is OK too.
	$VendorProjectCount = Order::GetProjectCountByVendor($dbCmd, $VendorID, $OrderID);
	
	if($VendorProjectCount == 0)
		_PrintXMLError("This order is not available or the order number is wrong.");
}

function _PrintXMLError($ErrorMessage){
	header ("Content-Type: text/xml");
	$returnXML = "<?xml version=\"1.0\" ?>\n<server_response><result>ERROR</result><message>" . WebUtil::htmlOutput($ErrorMessage) . "</message></server_response>";
	print $returnXML;
	exit;
}

