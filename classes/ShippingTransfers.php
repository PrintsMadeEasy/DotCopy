<?

class ShippingTransfers {
	
	private static $shippignMethodsObj;
	
	// This function will Update the Shipping Database with orders that are set to be exported 
	// This function can be called after every action that causes a change for a shipment going out... such as changing an Project Status to "Boxed" 
	// Then this function will dump all of the orders and details about orders going out for the day into the Shipping Database 
	// I made it in a different database because UPS worldship software opens an ODBC connection from the Windows Aplication to retrieve the Data for importing.
	// If I didnt separate the databases it could be a security risk 
	static function UpdateShippingExport(DbCmd $dbCmd){
	
		// Create another database connection to our shipping DB
		$dbConnectObj = new DbConnect( Constants::GetShippingDB_datasrc(), Constants::GetShippingDB_userid(), Constants::GetShippingDB_password(), Constants::GetShippingDB_hostname() );
		$shipDbCmd = new DbCmd($dbConnectObj);
		
		// Zero in the constructor means all shipping methods for all carriers in all domains.
		$shippingMethodsObj = new ShippingMethods(0);
		
		// Clear out the DB so that we can insert new records 
		$shipDbCmd->Query("DELETE FROM shippingexport");
		
		// This has will contain information from 2 separate queries.
		$ShippingInfoHash = array();
		
		
		// Don't export any shipments which the user does not have permission to see (based upon domain privelages).
		$authObj = new Authenticate(Authenticate::login_ADMIN);
		$userDomainIDs = $authObj->getUserDomainsIDs();
		
		$domainWhereClause = "";
		foreach ($userDomainIDs as $thisDomainID) {
			if(!empty($domainWhereClause))
				$domainWhereClause .= " OR ";
			$domainWhereClause .= " orders.DomainID=" . $thisDomainID;
		}
		$domainWhereClause = "(" . $domainWhereClause . ")";
	
		
		// Get all of the projects and corresponding shipments accociated with the open orders... we don't want to include any shipment ID that has already been shipped... so check againsted the DateShipped field
		$dbCmd->Query("SELECT shipments.ID AS ShipmentID, orders.ID AS OrderID, projectsordered.ID AS ProjectID, 
				orders.ShippingName, orders.ShippingCompany, orders.ShippingAddress, orders.ShippingAddressTwo, 
				orders.ShippingCity, orders.ShippingState, orders.ShippingZip, orders.ShippingCountry, 
				orders.ShippingResidentialFlag, projectsordered.Status, shipments.PackageWeight, 
				shipments.PackageType, shipments.PackageDimensions, shipments.ShippingMethod, orders.UserID
				FROM ((projectsordered USE INDEX (projectsordered_Status)
				INNER JOIN orders ON orders.ID = projectsordered.OrderID)
				INNER JOIN shipmentlink ON shipmentlink.ProjectID = projectsordered.ID)
				INNER JOIN shipments ON shipmentlink.ShipmentID = shipments.ID
				WHERE projectsordered.Status!='F' AND projectsordered.Status!='C'
				AND $domainWhereClause AND shipments.DateShipped IS NULL");
	
		while ($row = $dbCmd->GetRow()){
	
			$ShipmentID = $row["ShipmentID"];
			$ProjectStatus = $row["Status"];
	
			//The goal is to Export all shipments that have projects marked as "Boxed"
			//If a shipment contains 2 projects and 1 of the project is makred as "Printed" and the other is marked as "Boxed", then the shipment will not be exported
			//I couldn't think of a good way to use SQL to give me these results efficiently, so that is why I use the "ShipmentExportFlag" in the has below
	
			//The key to the hash should be string.. not an integer.. The reason is that PHP fill in the empty index between array elements for integers
			//By putting a "string" as the key we can filter out duplicate shipping IDs.
	
			if(!isset($ShippingInfoHash[$ShipmentID])){
				
				$ShippingInfoHash[$ShipmentID] = array();
	
				//The reference ID in UPS is just our main order - project id
				$ShippingInfoHash[$ShipmentID]["RefID"] = $row["OrderID"] . "-S" .  $ShipmentID;
	
				$ShippingInfoHash[$ShipmentID]["OrderID"] = $row["OrderID"];
				$ShippingInfoHash[$ShipmentID]["ShippingName"] = $row["ShippingName"];
				$ShippingInfoHash[$ShipmentID]["ShippingCompany"] = $row["ShippingCompany"];
				$ShippingInfoHash[$ShipmentID]["ShippingAddress"] = $row["ShippingAddress"];
				$ShippingInfoHash[$ShipmentID]["ShippingAddressTwo"] = $row["ShippingAddressTwo"];
				$ShippingInfoHash[$ShipmentID]["ShippingCity"] = $row["ShippingCity"];
				$ShippingInfoHash[$ShipmentID]["ShippingState"] = $row["ShippingState"];
				$ShippingInfoHash[$ShipmentID]["ShippingZip"] = $row["ShippingZip"];
				$ShippingInfoHash[$ShipmentID]["ShippingCountry"] = $row["ShippingCountry"];
				$ShippingInfoHash[$ShipmentID]["ResidentialIndicator"] = $row["ShippingResidentialFlag"];
				$ShippingInfoHash[$ShipmentID]["PackageWeight"] = $row["PackageWeight"];
				$ShippingInfoHash[$ShipmentID]["PackageType"] = $row["PackageType"];
				$ShippingInfoHash[$ShipmentID]["PackageDimensions"] = $row["PackageDimensions"];
				$ShippingInfoHash[$ShipmentID]["ShippingMethod"] = $row["ShippingMethod"];
				$ShippingInfoHash[$ShipmentID]["CustomerUserID"] = $row["UserID"];
				$ShippingInfoHash[$ShipmentID]["ShipmentExportFlag"] = true;
			}
	
			// Make sure that the project has a Boxed status... If not then set the Shipping Export Flag to false.
			// We want to make sure that every project in the shipment is boxed before exporting
			if(!$ShippingInfoHash[$ShipmentID]["ShipmentExportFlag"] || $ProjectStatus <> "B")
				$ShippingInfoHash[$ShipmentID]["ShipmentExportFlag"] = false;
		}
	
	

		foreach($ShippingInfoHash as $ThisShipmentID => $CurrentShipmentArr){
	
			// Skip records that are not flagged for exporting
			if (!$CurrentShipmentArr["ShipmentExportFlag"])
				continue;
	
			$ShippingMethod = $CurrentShipmentArr["ShippingMethod"];
			
			$orderID = Shipment::GetOrderIDfromShipmentID($dbCmd, $ThisShipmentID);
			
			$userID = $CurrentShipmentArr["CustomerUserID"];
			
			$dbCmd->Query("SELECT Phone FROM users WHERE ID=" . $userID);
			$customerPhone = $dbCmd->GetValue();
			
			// Start Off within an "Unknown" shipping method for the Post Office.  
			// If it becomes possible to downgrade for the Post Office then we can change it.
			$PostOfficeShippingMethod = "R";
			$UPS_ShippingMethod = "";

		
			// In case a Shipipng Carrier changed their "Available Destinations" after the order was placed... or the order is a Reprint with an invalid address...
			// ... we don't want to hang up the whole export process.  Just skip over invalid shipping addresses and let Customer Service figure out what happened.
			try{
				// If we are ahead of schedule with production (or the person is close by) then we may downgrade the shipping method to save money.
				// We can be sure that the shipment is leaving today if we are exporting the shipping methods.
				$adjMethodsList = ShippingTransfers::getAdjustedShippingMethods($dbCmd, $ThisShipmentID);
			}
			catch (ExceptionInvalidShippingAddress $e){
				WebUtil::WebmasterError("Could Not Export Shipments on Order: " . $CurrentShipmentArr["OrderID"] . " because the Address is Invalid. \n\nException: " . $e->getMessage());
				continue;
			}


			// Right now we only have 2 shipping carriers. So we are going to hard code the 2 shipping methods into the DB.
			foreach($adjMethodsList as $thisShippingMethodAdjusted){
				
				// Just in case the Class for the Shipping method was changed after the Database was updated, skip over that shipping method as a possibility.
				if(!$shippingMethodsObj->doesShippingCodeExist($thisShippingMethodAdjusted))
					continue;
				
				$carrierOfShippignMethod = $shippingMethodsObj->getShippingCarrierFromShippingCode($thisShippingMethodAdjusted);
						
				if($carrierOfShippignMethod == ShippingMethods::CARRIER_CODE_UPS)
					$UPS_ShippingMethod = $shippingMethodsObj->getCarrierReference($thisShippingMethodAdjusted);
				else if($carrierOfShippignMethod == ShippingMethods::CARRIER_CODE_POSTOFFICE)
					$PostOfficeShippingMethod = $shippingMethodsObj->getCarrierReference($thisShippingMethodAdjusted);
				else
					WebUtil::WebmasterError("Error Shipping Transfers. The Carrier Code has not been defined yet: $carrierOfShippignMethod");
				
			}

			
			
			// If The Shipping Choice was disabled after the order was placed
			// Then we might not have any Shipping Method Links available.
			if(!empty($adjMethodsList)){
				
				// The Adjusted Shipping Method is the one listed at the top of our Array (of Adjusted Shipping Methods).  It is our prefered choice.
				reset($adjMethodsList);
				$adjustedShipMethod = current($adjMethodsList);
	
			
				// Unless the shipping method has changed... we don't want to update the database.
				if($ShippingMethod != $adjustedShipMethod)
					Shipment::ShipmentSetShippingMethod($dbCmd, $ThisShipmentID, $adjustedShipMethod);
	
	
				// This will record what the system "thought" we should be using.  That will help us find human errors in the shop if the workers choose another method.
				Order::RecordShippingMethodHistory($dbCmd, $orderID, $adjustedShipMethod);
	
				// Saturday Shipments have another column in the DB to say if it has saturday delivery.
				// That may be used in conjunction with "1 day delivery" to give a Satuday delivery.
				if($shippingMethodsObj->isSaturdayDelivery($adjustedShipMethod))
					$saturdayDeliveryFlag = "Y"; 
				else
					$saturdayDeliveryFlag = "N"; 
			}
			else{
				$saturdayDeliveryFlag = "N";
			}
	
			
	
			// Just to make extra sure that a bad shipment isnt going out
			if($CurrentShipmentArr["ShippingAddress"] == "")
				throw new Exception("Can not export shippping details because the address was left blank on ::: " . $CurrentShipmentArr["RefID"]);
	
	
			if(!empty($CurrentShipmentArr["ShippingCompany"])){
				$nameOrComapny = $CurrentShipmentArr["ShippingCompany"];
				$attention = $CurrentShipmentArr["ShippingName"];
			}
			else{
				$nameOrComapny = $CurrentShipmentArr["ShippingName"];
				$attention = "";
			}
	

			

	
			$mysql_timestamp = date("YmdHis", time());
	
			// If the PackageDimension exists, then it should have each section separted by a hyphen 
			if(!empty($CurrentShipmentArr["PackageDimensions"])){
	
				$out = array();
				preg_match_all ("/(\d+)/", $CurrentShipmentArr["PackageDimensions"], $out);
	
				$packageLength = $out[1][0];
				$packageWidth = $out[1][1];
				$packageHeight = $out[1][2];
			}
			else{
				$packageLength = "";
				$packageWidth = "";
				$packageHeight = "";
			}
			
			$insertArr = array();
			
			$insertArr[ "ShipmentID"] = $ThisShipmentID;
	
			$insertArr[ "PaymentMethod"] = "Bill to Third Party";
			$insertArr[ "ShipmentMethod"] = $UPS_ShippingMethod;
			$insertArr[ "SaturdayDelivery"] = $saturdayDeliveryFlag;
			$insertArr[ "PackageType"] = "Package";   //Override the package type for now
			$insertArr[ "PackageWeight"] = $CurrentShipmentArr["PackageWeight"];
			$insertArr[ "PackageLength"] = $packageLength;
			$insertArr[ "PackageWidth"] = $packageWidth;
			$insertArr[ "PackageHeight"] = $packageHeight;
			$insertArr[ "ReferenceCode"] = $CurrentShipmentArr["RefID"];
			$insertArr[ "DateExported"] = $mysql_timestamp;
			$insertArr[ "Carrier"] = "UPS";
			$insertArr[ "PostOfficeShipMethod"] = $PostOfficeShippingMethod;
			
	
			$insertArr[ "Cstmr_NameOrCompany"] = $nameOrComapny;
			$insertArr[ "Cstmr_Attention"] = $attention;
			$insertArr[ "Cstmr_Address"] = $CurrentShipmentArr["ShippingAddress"];
			$insertArr[ "Cstmr_AddressTwo"] = $CurrentShipmentArr["ShippingAddressTwo"];
			$insertArr[ "Cstmr_City"] = $CurrentShipmentArr["ShippingCity"];
			$insertArr[ "Cstmr_State"] = $CurrentShipmentArr["ShippingState"];
			$insertArr[ "Cstmr_Zip"] = $CurrentShipmentArr["ShippingZip"];
			$insertArr[ "Cstmr_Country"] = $CurrentShipmentArr["ShippingCountry"];
			$insertArr[ "Cstmr_ResidentialIndicator"] = $CurrentShipmentArr["ResidentialIndicator"];
			$insertArr[ "Cstmr_Phone"] =  $customerPhone;
	
	
	
			//Get a new account type object, depending on the person that placed the order
			//There may be a special case where the User wants a different ShipFrom address
			$AccountTypeObj = new AccountType($CurrentShipmentArr["CustomerUserID"], $dbCmd);
			$ShipFromHash = $AccountTypeObj->GetShippingFromAdress();
	
			$insertArr[ "ShpFrm_NameOrCompany"] = $ShipFromHash["Company"];
			$insertArr[ "ShpFrm_Attention"] = $ShipFromHash["Attention"];
			$insertArr[ "ShpFrm_Address"] = $ShipFromHash["Address"];
			$insertArr[ "ShpFrm_AddressTwo"] = $ShipFromHash["AddressTwo"];
			$insertArr[ "ShpFrm_City"] = $ShipFromHash["City"];
			$insertArr[ "ShpFrm_State"] = $ShipFromHash["State"];
			$insertArr[ "ShpFrm_Zip"] = $ShipFromHash["Zip"];
			$insertArr[ "ShpFrm_Country"] = $ShipFromHash["Country"];
			$insertArr[ "ShpFrm_Phone"] = $ShipFromHash["Phone"];
			$insertArr[ "ShpFrm_UPS_AccountNumber"] = $ShipFromHash["UPS_AccountNumber"];
			$insertArr[ "ShpFrm_TaxID"] = $ShipFromHash["TaxID"];
			$insertArr[ "ShpFrm_TaxIDtype"] = $ShipFromHash["TaxIDtype"];
			$insertArr[ "ShpFrm_ResidentialIndicator"] = $ShipFromHash["ResidentialIndicator"];
			$insertArr[ "ShpFrm_OneLineWithPipes"] = $ShipFromHash["Company"] . "|" . $ShipFromHash["Attention"] . "|" . $ShipFromHash["Address"] . " " . $ShipFromHash["AddressTwo"] . "|" . $ShipFromHash["City"] . ", " . $ShipFromHash["State"] . "|" . $ShipFromHash["Zip"];
			
	
	
	
			# Temporary... put the shipment into the other export table while we are switching vendors
			#if(Order::GetProjectCountByVendor($dbCmd, 91256, $CurrentShipmentArr["OrderID"]) > 0){
			#	$insertArr[ "PaymentMethod"] = array( "","S");
			#	$insertArr[ "ShpFrm_UPS_AccountNumber"] = array( "Y23191","S");
			#	$shipDbCmd->InsertQuery("shippingexportTemporary",  $insertArr);
			#}
			#else{
				$shipDbCmd->InsertQuery("shippingexport",  $insertArr);
	
			#}
	
		}
	}
	
	
	
	
	
	
	
	
	// This will look into the shipping Import table .. This table should have been populated by UPS
	// Worldship though ODBC connection.  We want to look in the table and Pick out the reference Numbers
	// so that we can determine which shipments went out.  Then we finish the orders off within out system
	// And send confirmation emails out to the users.  When we are all done we need to clean out the shipping import table 
	static function GetShippingImport(DbCmd $dbCmd, DbCmd $dbCmd2, $UserID){
	
		// Create another database connection to our shipping DB
		$dbConnectObj = new DbConnect( Constants::GetShippingDB_datasrc(), Constants::GetShippingDB_userid(), Constants::GetShippingDB_password(), Constants::GetShippingDB_hostname() );
		$shipDbCmd = new DbCmd($dbConnectObj);
	
		// Make this script be able to run for at least 3 minutes 
		set_time_limit(500);
					
		// Zero in in the contructor means include all Shipping Methods for all Carriers on All Domains.
		$shippingMethodsObj = new ShippingMethods(0);
	
		$shipDbCmd->Query("SELECT ReferenceCode, Dinero, TrackingNumber, ShipmentID_FromUSPS, ShipmentMethodFromUSPS, UNIX_TIMESTAMP(LabelGenerated) as LabelGenerated 
					FROM shippingimport WHERE ((VoidIndicator=\"N\" AND ReferenceCode != \"\") OR ShipmentID_FromUSPS IS NOT NULL ) ORDER BY ID DESC");
	
		// Keep track of how many Shipments and Projects are completed.  We are just doing this to send out an end-of-day report by email.
		$ProjectIDsArr = array();
		$shipmentCount = 0;
	
		$LogFile = "";
	
		// We will fill up this has with records from the DB.  This way we can clear out old and duplicate records before looping through the hash and doing stuff 
		$ImportHash = array();
	
		while ($row = $shipDbCmd->GetRow()){
	
			$ReferenceCodeFromUPS = $row["ReferenceCode"];
			$Dinero = $row["Dinero"];
			$TrackingNumber = $row["TrackingNumber"];
			$ShipmentID_FromUSPS = trim($row["ShipmentID_FromUSPS"]);
			$labelGenerationTime = $row["LabelGenerated"];
			$ShipmentMethodFromUSPS = $row["ShipmentMethodFromUSPS"];
			
			
			$carrierName = "";
			$shippingMethodOverride = null;
	
			// The UPS Reference Code is in the format "234-S452"  MainOrderID - ShipmentID
			$matches = array();
			if(preg_match_all("/^(\d+)-S(\d+)$/", $ReferenceCodeFromUPS, $matches)){
				
				$carrierName = ShippingMethods::CARRIER_CODE_UPS;
				
				$shipmentID = $matches[2][0];
				
				// To avoid a compiler warning of the variable not being used???
				if(!empty($shipmentID))
					$shipmentID = $shipmentID;
				else
					$shipmentID = $shipmentID;
			}
			else if(preg_match("/^\d+$/", $ShipmentID_FromUSPS)){
				
				$carrierName = ShippingMethods::CARRIER_CODE_POSTOFFICE;
				
				// If the Shipping Import came from the USPS program... then we should have been notified of what shipping method was used.
				// If the user manually selected something other than First Class or Priority... then we will override the Shipping method with an "Error" status type.
				// Now convert the Shipping Carrier Method Reference into our internal Shipping Method code.
				$shippingMethodOverride = $shippingMethodsObj->getShippingMethodCodeFromCarrierReference($carrierName, $ShipmentMethodFromUSPS);
				
				$shipmentID = $ShipmentID_FromUSPS;
			}
			else{
				$carrierName = "UNKNOWN";
				$LogFile .= "Reference# $ReferenceCodeFromUPS is incomplete.\n<br>";
				
				$shipmentID = 0;
			}
			
			
			
			
			$mainorder_ID = Shipment::GetOrderIDfromShipmentID($dbCmd2, $shipmentID);
			
			if(empty($mainorder_ID))
				$LogFile .= "Shipment ID does not match up to an OrderID.  UPS Shipping Reference: $ReferenceCodeFromUPS  USPS Shipping Reference: # $ShipmentID_FromUSPS \n<br>";
	
			//  Make sure there are no duplicates. 
			if(!isset($ImportHash["$shipmentID"]) && !empty($mainorder_ID)){
				$ImportHash["$shipmentID"] = array(	"Tracking"=>$TrackingNumber, 
									"ShippingCost"=>$Dinero, 
									"ORDERID"=>$mainorder_ID,
									"CarrierName"=>$carrierName,
									"LabelGenerated"=>$labelGenerationTime,
									"ShipMethodOverride"=>$shippingMethodOverride
									);
			}
		}
	
		print "<b><u><font class='largebody'>Sending Confirmation Emails to Users</font></u></b><br><br>";
		flush();
		
		
		// Holds a 2d array of details on Shipping Downgrades.
		// the key is the order number.
		$degradedShippingMethods = array();
		
		
		$numShipmentsAttemptedCompleted = 0;
	
		// Loop through all of the shipment IDs and update the tracking numbers, shipping cost, and date shipped  
		foreach($ImportHash as $ShipmentID => $ShipmentInfoArr){
	
			if(empty($ShipmentID))
				continue;
							
			// Now we want figure out if the order had its shipping method degraded.
			$orderNum = Shipment::GetOrderIDfromShipmentID($dbCmd, $ShipmentID);
			
			if(!$orderNum){
				WebUtil::WebmasterError("Trying to Import Shipments on Shipment ID: $ShipmentID and no record was found.");
				continue;
			}
				
			$thisShipmentShouldBeCounted = false;
			
			$projectIDsInThisShipment = array();
	
	
			// Get a list of all ProjectID's within this current shipment
			// Check to make sure the projects have not already been completed...
			// Otherwise we would count shipments going out multiple times... Maybe they shipment was already completed earlier but the UPS log was uploaded twice.
			$dbCmd->Query("SELECT DISTINCT shipmentlink.ProjectID FROM shipmentlink 
						INNER JOIN projectsordered on shipmentlink.ProjectID = projectsordered.ID
						WHERE shipmentlink.ShipmentID=$ShipmentID AND projectsordered.Status!='F'");
			while($projectID = $dbCmd->GetValue()){
				if(!in_array($projectID, $ProjectIDsArr)){
					array_push($ProjectIDsArr, $projectID);
					array_push($projectIDsInThisShipment, $projectID);
				}
				
				// If there were projects shipped today (meaning they don't have a status of "Finished Yet"... then we want to increment the shipment counter.
				if(!$thisShipmentShouldBeCounted){
					$shipmentCount++;
					$thisShipmentShouldBeCounted = true;
				}
			}
			
			
			// The shipment type may have been changed on the client program
			// In which case it could have reported it to our import software... then we want to change the DB is that is the case.
			if($thisShipmentShouldBeCounted && !empty($ShipmentInfoArr["ShipMethodOverride"])){

				Shipment::ShipmentSetShippingMethod($dbCmd, $ShipmentID, $ShipmentInfoArr["ShipMethodOverride"]);
				
			}
			
			
			
			// If the shipment was sent by the United State Post Office... then it means their program was able to give us a timestamp of when the label was generated.
			if($thisShipmentShouldBeCounted && $ShipmentInfoArr["CarrierName"] == "USPS"){
				
				foreach($projectIDsInThisShipment as $thisProjectID){
					
					// I have to do a manual SQL insert here. 
					// The reason is that the normal function call is not able record project records in the past.
					$InsertRow["ProjectID"] = $thisProjectID;
					$InsertRow["Note"] = "USPS Label Generated";
					$InsertRow["UserID"] = $UserID;
					$InsertRow["Date"] = $ShipmentInfoArr["LabelGenerated"];
					$dbCmd->InsertQuery("projecthistory",  $InsertRow);
				}
				
			}
	
	
			if($thisShipmentShouldBeCounted && (Shipment::checkIfShippingMethodDoesntMatchDefaultInShippingChoice($dbCmd, $orderNum))){
				
				// Keep from notifying on the same order number multiple times.
				if(!isset($degradedShippingMethods[$orderNum])){
				
					$degradedShippingMethods[$orderNum] = array();
					
					// If we have many shipments within the same order then we don't want to try to esimate the profit for outgoing shipments.
					// The reason is that the Customer Shipping quote is for all Shipments in the order... 
					// we don't have to way to compare the customer price against the shipment.
					$ShipIDArr = Shipment::GetShipmentsIDsWithinOrder($dbCmd, $orderNum, 0, "");
					if(sizeof($ShipIDArr) == 1)
						$multipleShipFlag = false;
					else
						$multipleShipFlag = true;
						
		
					$custShipPrice = Order::GetCustomerShippingQuote($dbCmd, $orderNum);
					
					$degradedShippingMethods[$orderNum]["ShipmentMethod"] = Shipment::getShippingMethodCodeOfShipment($dbCmd, $ShipmentID);
					$degradedShippingMethods[$orderNum]["ShippingChoiceID"] = Order::getShippingChoiceIDfromOrder($dbCmd, $orderNum);
					$degradedShippingMethods[$orderNum]["OrderShipPrice"] = $custShipPrice;
					$degradedShippingMethods[$orderNum]["ShipmentCost"] = $ShipmentInfoArr["ShippingCost"];
					$degradedShippingMethods[$orderNum]["MultipleShipFlag"] = $multipleShipFlag;
					$degradedShippingMethods[$orderNum]["Carrier"] = $carrierName;
				}
			}
			
		
			$PaymentsObj = new Payments(Order::getDomainIDFromOrder($orderNum));
	
			ShippingTransfers::CompleteShipment($dbCmd, $PaymentsObj, $UserID, $ShipmentInfoArr["Tracking"], $ShipmentID, $ShipmentInfoArr["ShippingCost"], $ShipmentInfoArr["CarrierName"]);
	
			$numShipmentsAttemptedCompleted++;
	
	
	
			// Sometimes a delay when exporting a large list.. since we are charging the customer.  So, to keep the browser from breaking, I am printing out the email addresses right away and they flushing the webserver cache
			print "Order ID: " . $ShipmentInfoArr["ORDERID"];
			print "<br>";
			flush();
		}
		
		
	
		$shippingDowngradeMsg = "";
		$shippingProfit = 0;
		foreach($degradedShippingMethods as $thisOrderNum => $degardedShipHash){
		
			$defaultShippingMethodCode = ShippingChoices::getStaticDefaultShippingMethodCode($degardedShipHash["ShippingChoiceID"]);
			$defaultShippingMethodName = $shippingMethodsObj->getShippingMethodName($defaultShippingMethodCode, true);
			
			$changedShippingMethodName = $shippingMethodsObj->getShippingMethodName($degardedShipHash["ShipmentMethod"], true);
			
			$shippingDowngradeMsg .= "Order #" . $thisOrderNum; 
			$shippingDowngradeMsg .= "\n- Original: " . $defaultShippingMethodName;
			$shippingDowngradeMsg .= "\n- Changed: " . $changedShippingMethodName;
			
			if($degardedShipHash["MultipleShipFlag"]){
			
				$shippingDowngradeMsg .= "\n- Multiple Shipments in Order\n\n";
			}
			else{
				$approxProfit = $degardedShipHash["OrderShipPrice"] - $degardedShipHash["ShipmentCost"];
				
				$shippingProfit += $approxProfit;
				
				$shippingDowngradeMsg .= "\n- " . 'Cust. Ship: $' . number_format($degardedShipHash["OrderShipPrice"], 2);
				$shippingDowngradeMsg .= "\n- " . 'Freight Cost: $' . number_format($degardedShipHash["ShipmentCost"], 2);
				$shippingDowngradeMsg .= "\n- " . 'Approx. Profit: $' . number_format($approxProfit, 2) . "\n\n";
			}
		}
		
	
		if(!empty($shippingDowngradeMsg)){
		
			$tempMsg = "\n\nThe following orders had the Package Shipping Methods changed.  \n";
			$tempMsg .= "Profit is an approximate value because it does not take into consideration the UPS incentives or multiple shipments within an order.\n";
			$tempMsg .= "- - - - - - - - - - - - - - - - - - - - - - - - - - - - \n\n" . $shippingDowngradeMsg;
			$tempMsg .= "\n\n" . 'Approximate Total Profit: $' . number_format($shippingProfit, 2) . "\n\n";
			
			$shippingDowngradeMsg = $tempMsg;
		}
	
	
		
		$shipmentCompletedMessage = '$' . number_format($shippingProfit, 2) . " === " . sizeof($ProjectIDsArr) . " projects and " . $shipmentCount . " shipments complete. " . sizeof($degradedShippingMethods) . " shipments downgraded.";
			
	
		$emailContactsForReportsArr = Constants::getEmailContactsForServerReports();
		foreach($emailContactsForReportsArr as $thisEmailContact)
			WebUtil::SendEmail("Shipment Notify", Constants::GetMasterServerEmailAddress(), "", $thisEmailContact, $shipmentCompletedMessage, ($shipmentCompletedMessage . $shippingDowngradeMsg));	
	
	
		// Clear out the Shipping Import Table now.
		$shipDbCmd->Query("DELETE FROM shippingimport");
	
		return $LogFile;
	}
	
	// Send a shipping confirmation out to the user.  Pass in the variables and the message (with variable holders) to be substituted 
	static function SendUserEmailConfirmation($userName, $OrderNumber, $Tracking, $EmailAddress){
	
		if(!empty($Tracking))
			$Tracking = "Tracking Number: " . $Tracking . "\nTrack your shipment at  http://www.ups.com/tracking/tracking.html";
	
		$domainIDofOrder = Order::getDomainIDFromOrder($OrderNumber);
		$webisteURL = Domain::getWebsiteURLforDomainID($domainIDofOrder);
			
		$Subject = "Shipment Notification";
	
	
			$MainMessage = "Hello {NAME},
	
We wanted to let you know that a shipment went out today with {CARRIER}. 
{TRACKING}

Be aware that the tracking number may take up to 1 day before it registers into the carrier's system.

Depending on your area, UPS may require a signature before releasing the package.  We do not have any control over this.  Please leave a note on your door with special instructions if you do not plan on being in during the scheduled day of delivery.  

If you have any questions, please visit customer service at
http://{WEBSITE_URL}

Thanks,
Shipping Department

ORDER #{ORDERNO}";
	
	
		$MainMessage = preg_replace("/{NAME}/", $userName, $MainMessage);
		$MainMessage = preg_replace("/{CARRIER}/", "UPS", $MainMessage);
		$MainMessage = preg_replace("/{ORDERNO}/", Order::GetHashedOrderNo($OrderNumber), $MainMessage);
		$MainMessage = preg_replace("/{TRACKING}/", $Tracking, $MainMessage);
		$MainMessage = preg_replace("/{WEBSITE_URL}/", $webisteURL, $MainMessage);
	
		
		$domainEmailConfig = new DomainEmails($domainIDofOrder);
		
		WebUtil::SendEmail($domainEmailConfig->getEmailNameOfType(DomainEmails::CUSTSERV), $domainEmailConfig->getEmailAddressOfType(DomainEmails::CUSTSERV), "", $EmailAddress, $Subject, $MainMessage);
	
	}
	
	
	static function CompleteShipment(DbCmd $dbCmd, &$PaymentsObj, $UserID, $TrackingNumber, $ShipmentID, $shipmentCost, $CarrierName){
	
	
		$OrderID = Shipment::GetOrderIDfromShipmentID($dbCmd, $ShipmentID);
	
		if(!$OrderID){
			WebUtil::WebmasterError("Error in function ShippingTransfers::CompleteShipment.  Shipment ID $ShipmentID does not exist.");
			return;
		}
	
		//Don't allow duplicate completions of a shipment
		if(!Shipment::CheckIfShipmentHasGone($dbCmd, $ShipmentID)){
	
			// Update the Database with the tracking numbers and shipping cost and date 
			Shipment::ShipmentSetTrackingNumber($dbCmd, $ShipmentID, $TrackingNumber);
			Shipment::ShipmentSetDateShipped($dbCmd, $ShipmentID, time());
			Shipment::ShipmentSetFreightFees($dbCmd, $ShipmentID, $shipmentCost);
			Shipment::SetShipmentCarrier($dbCmd, $ShipmentID, $CarrierName);
	
			// Keep a list of every ProjectID that is asscoiated with the shipment 
			$ProjectIDsArr = array();
	
			// Get a list of all ProjectID's within this current shipment 
			// Check to make sure the projects have not already been completed... because we don't want a multiple UPS export to cause multiple payment captures 
			$dbCmd->Query("SELECT DISTINCT shipmentlink.ProjectID FROM shipmentlink 
						INNER JOIN projectsordered on shipmentlink.ProjectID = projectsordered.ID
						WHERE shipmentlink.ShipmentID=$ShipmentID AND projectsordered.Status!='F'");
	
			while($projectID = $dbCmd->GetValue()){
				if(!in_array($projectID, $ProjectIDsArr))
					array_push($ProjectIDsArr, $projectID);
			}
	
	
			// Now get the User Information so that we can send them a confirmation email
			$dbCmd->Query("SELECT users.Name AS Name, users.Email AS Email
				FROM users INNER JOIN orders on orders.UserID = users.ID
				WHERE orders.ID=" . $OrderID);
			$row = $dbCmd->GetRow();
			$userName = $row["Name"];
			$userEmail = $row["Email"];
			
			
			// Sometimes we downgrade people's shipping methods if the package goes out a day early.
			// If they "Customer Shipping Method" does not match the "Shipment Shipping Method" then we don't want to notify them with the tracking number.
			// Then the user would be able to see that we are shipping Ground to CA even though they paid for 1 day.
			$packageShippingMethodCode = Shipment::getShippingMethodCodeOfShipment($dbCmd, $ShipmentID);
			$custShippingChoiceID = Order::getShippingChoiceIDfromOrder($dbCmd, $OrderID);
			$defaultShippingMethodCode = ShippingChoices::getStaticDefaultShippingMethodCode($custShippingChoiceID);
	
			// Also don't send them a confirmation mail if we send something out with the Post Office.
			if($defaultShippingMethodCode == $packageShippingMethodCode)
				ShippingTransfers::SendUserEmailConfirmation($userName, $OrderID, $TrackingNumber, $userEmail);
		
		
			// Now that the shipments have been completed, loop through the Project ID's and see if the total order is completed
			// If they are then capture the funds from the credit card.
			foreach($ProjectIDsArr as $ThisProjectID){
	
				// Projects are only considered complete when all shipments associated with them have gone out... Protects if a project is split into multiple shipments 
				if(Shipment::CheckCompletedShipmentsForProject($dbCmd, $ThisProjectID)){
	
					ProjectOrdered::ChangeProjectStatus($dbCmd, "F", $ThisProjectID);
	
					//Keep a record of the change
					ProjectHistory::RecordProjectHistory($dbCmd, $ThisProjectID, "F", $UserID);
	
					//Since the project was just marked as finished, we may need chare the customer now.
					Order::OrderChangedCheckForPaymentCapture($dbCmd, $OrderID, $PaymentsObj);
				}
			}
			
		}
	
	}
	
	
	// return true or false depending on whether there are any shipments waiting to be imported from our Shipping Database
	static function CheckIfImportIsEmpty(){
	
		// Create another database connection to our shipping DB
		$dbConnectObj = new DbConnect( Constants::GetShippingDB_datasrc(), Constants::GetShippingDB_userid(), Constants::GetShippingDB_password(), Constants::GetShippingDB_hostname() );
		$shipDbCmd = new DbCmd($dbConnectObj);
	
		$shipDbCmd->Query("SELECT COUNT(*) FROM shippingimport");
	
		return $shipDbCmd->GetValue();
	
	}
	
	
	
	// Pass in a shipment ID.  This method will find out what projects are included within the shipment.
	// It will take the youngest guaranteed Ship date out of the bunch and compare it against today's date.
	// If we are ahead of schedule then this function may return a different shipping method (compared to what the customer picked, or the Default Shipping Method link).
	// For example if we are 2 days ahead of shedule then it may downgrade the shipping method from 3 day to ground... or maybe 1 day to 3 day.	
	// It will return an array with the best Method Shiping Method listed on top.
	// If the Domain does not downgrade shipping... of if the User has a setting which restricts downgrades, then this method will return the an array() containing only the Default Shipping Method Code linked to the Shipping Choice
	
	// A "Primary Shipping Carrier" is the carrier belonging to the "Default Shipping Method Link" (as configured in the backend).
	// The Primary Shipping Carrier will always be returned in the array.  If a substitution is not found (due to time) then the array will just return the Default Shipping Method Link)
	// There is only one Shipping Method code returned per Shipping Carrier.
	// If another Shipping Carrier offers a Shipping Method that can make it to the destination on time (and it is Sorted in FRONT of the Primary Carrier), then it will be returned on top.
	// The Primary Carrier will always return a Shipping Method Code (even if it listed at the bottom of the array).
	// If a Primary Carrier has a Shipping Method that can make it on time (and it is sorted in FRONT of a Seconary Carrier)... then this method will not return a Shipping Code for the seconary carrier (or 3rd, 4rth, etc).
	// If none of the Shipping Method Links can make it to the destination on time then this method will return the Default Shipping Method link.
	// If there are more than 1 Carriers that have Shipping Methods (sorted in FRONT) of of a Primary Carrier... then the top choice from each carrier will be listed on top of the primary.  There will be only one Shipping Method listed per Carrier.
	// This approach could give us different options for Downgrading shipping if one of the carrier's software is broken or another unknown event.
	static function getAdjustedShippingMethods(DbCmd $dbCmd, $ShipmentID){
	
		// Zero in the constructor means all shipping methods for all carriers in all domains.
		// Cache the Shipping Method object since this function may be called within a loop.
		if(empty(self::$shippignMethodsObj))
			self::$shippignMethodsObj = new ShippingMethods(0);
		
		$orderNumberFromShipID = Shipment::GetOrderIDfromShipmentID($dbCmd, $ShipmentID);
		
		if(!$orderNumberFromShipID){
			WebUtil::WebmasterError("Error in function ShippingTransfers::getAdjustedShippingMethods.  Shipment ID $ShipmentID does not exist.");
			throw new Exception("Error there is not an Order Number associated with the Shipment ID.");
		}
		
		$userID = Order::GetUserIDFromOrder($dbCmd, $orderNumberFromShipID);
		
		$userControlObj = new UserControl($dbCmd);
		$userControlObj->LoadUserByID($userID);
		
		$domainIDfromOrder = Order::getDomainIDFromOrder($orderNumberFromShipID);
		
		$domainObj = Domain::singleton();
		
		$shippingChoiceObjForOrder = ShippingChoices::getShippingChoiceObjectFromCache($domainIDfromOrder);

		$customerShippingChoiceID = Order::getShippingChoiceIDfromOrder($dbCmd, $orderNumberFromShipID);

		$defaultShippingMethodCodeForShippingChoice = $shippingChoiceObjForOrder->getDefaultShippingMethodCode($customerShippingChoiceID);
	
		// If the Shipping Choice became disabled after the order was placed then we can't return anything.
		if(empty($defaultShippingMethodCodeForShippingChoice))
			return array();
			
		// Just in case the Shipping Class was changed after the Shipping Choice was configured in the Database.
		if(!self::$shippignMethodsObj->doesShippingCodeExist($defaultShippingMethodCodeForShippingChoice))
			return array();
		
		$shippingCarrierOfDefaultMethod = self::$shippignMethodsObj->getShippingCarrierFromShippingCode($defaultShippingMethodCodeForShippingChoice);

		// If the Domain does not downgrade shipping (or the UserSetting restricts it)... then just return the Default Shipping Method code linked to the Shipping Choice ID
		if(!$domainObj->doesDomainDowngradeShipping($domainIDfromOrder) || $userControlObj->getDowngradeShipping() == "N")
			return array($defaultShippingMethodCodeForShippingChoice);
		
			
			
		$projectIDsWithinShipmentArr = Shipment::GetProjectIDsWithinShipment($dbCmd, $ShipmentID);
		
		// Loop through each ProjectID and find out what is the earliest ShipDate out of all of them.
		// Althrough... all of the Projects in the shipment should have the same exact Arrival Date.
		$earliestShipDateTimeStamp = 0;
		$earliestArrivalDate = 0;
		
		$productsInShipmentArr = array();
		
		foreach($projectIDsWithinShipmentArr as $thisProjectID){
		
			$dbCmd->Query("SELECT UNIX_TIMESTAMP(EstShipDate) AS ShipDate, UNIX_TIMESTAMP(EstArrivalDate) AS ArrivalDate, ProductID FROM projectsordered WHERE ID=$thisProjectID");
			$row = $dbCmd->GetRow();
			$projectShipDate = $row["ShipDate"];
			$projectArrivalDate = $row["ArrivalDate"];
			
			$productsInShipmentArr[] = $row["ProductID"];
			
			if(empty($earliestShipDateTimeStamp) || $projectShipDate < $earliestShipDateTimeStamp)
				$earliestShipDateTimeStamp = $projectShipDate;
				
			if(empty($earliestArrivalDate) || $projectArrivalDate < $earliestArrivalDate)
				$earliestArrivalDate = $projectArrivalDate;
		}
		
		

		
		
		
		// Equalize all dates using the mktime function using the 1st second of the 1st minute of the 1st hour in each day.
		// Then we can find out the difference in seconds between time stamps to figure out how many days separate them.
		$gauranteedShipdate = mktime (1,1,1,date("n", $earliestShipDateTimeStamp),date("j", $earliestShipDateTimeStamp), date("Y", $earliestShipDateTimeStamp));
		$todayDate = mktime (1,1,1,date("n"),date("j"),date("Y"));
		
		$secondsWithinDay = 60 * 60 * 24;
		
		
		// How many days are we ahead of Schedule.
		// Basically what day did we promise the customer that the Product would ship... and what is today's date.
		$daysAheadOfSchedule = intval(round(($gauranteedShipdate - $todayDate) / $secondsWithinDay));
	
		
		$eventScheduleObj = new EventScheduler();
		
		
		// Now for every day that we appear to be ahead of schedule...
		// Let us check for any delays with shipment such as Weekends, or Carier Holidays
		// We need to subtract those days from our "ahead of schedule days".
		// Start out checking the current day (just in case they try to generate UPS lables on Sunday, etc.)
		// We don't need to check the last day (or guaranteed ship date) for a delay because that should have been checked before.
		$numDelayDaysBetweenTodayAndShipDate = 0;
		for($i=0; $i < $daysAheadOfSchedule; $i++){
			
			$timestampToCheck = $todayDate + $i * $secondsWithinDay;
			
			$transitDelayFlag = $eventScheduleObj->checkIfDayHasEvent($timestampToCheck, $productsInShipmentArr, EventScheduler::DELAYS_TRANSIT);
			
			$satOrSunFlag = false;
			
			$dayNameToCheck = strtoupper(date("D", $timestampToCheck));
			if($dayNameToCheck == "SAT" || $dayNameToCheck == "SUN")
				$satOrSunFlag = true;
			
			if($transitDelayFlag || $satOrSunFlag)
				$numDelayDaysBetweenTodayAndShipDate++;	
		}
		
		
		
		$daysAheadOfSchedule -= $numDelayDaysBetweenTodayAndShipDate;
	
	
		// Set a Flag to tell us if this is a Saturday Delivery Method
		$customerSelectedSaturdayDelivery = $shippingChoiceObjForOrder->checkIfShippingChoiceHasSaturdayDelivery($customerShippingChoiceID);
	
		// Set a Flag to tell us if the Customer Selected Early delivery.
		// Basically, if the Arrival Date stored on the Project has a time of day associated with it, then we consider that an early delivery.
		$customerSelectedEarlyDelivery = (TimeEstimate::getArrivalHourFromTimestamp($earliestArrivalDate) == null) ? false : true;
	
	
	
		// If The shipment has an "Early Method" like 1 day early or 2 day early (or Saturday)...
		// Then we can't degrade the shipping method to a regular method as easily
		// If they have picked an early arrival or Have Saturday then we need to pad an extra day
		// If we have 1 day early and are 1 day ahead of schedule... this will bring down the "days ahead" down to zero...  
		// ... in which case we add the days in transit (of 1)... so that it will go out 1 day regular. (and they will have it 1 day early).
		if($customerSelectedEarlyDelivery || $customerSelectedSaturdayDelivery)
			$daysAheadOfSchedule -= 1;
			
	
	
		// The Days in Transit is what we relayed to the Customer... how long it would take in shipping (if we shipped out on time).
		// We may have over embelished this amount ... For example... Ground Shipping between Chatworth & Chatsworth is not really 5 days for ground.
		$daysInTransit = $shippingChoiceObjForOrder->getTransitDays($customerShippingChoiceID);
	

		
	
		// We want our shipping method to take whatever the original days in transit is... plus the amount of days that we are ahead of schedule.
		// If we are X days ahead of schedule then it means that we can pick a shipping method that takes X days longer to get there.
		$adjustedDaysInTransit = $daysInTransit + $daysAheadOfSchedule;
	
		

		

		
		
		// If We do not have any positive days to ship the package then it means we are deffinetly late.
		// We can't downgrade the Shipping Method.
		// Saturday and Early shipments would have artificially Made us 1 day behind schedule.
		if($adjustedDaysInTransit <= 0 )
			return array($defaultShippingMethodCodeForShippingChoice);

		
	
		// Get the To/From shipping addresses so we can get accurate transit times with our ShippingMethods Objects.
		$domainAddressObj = new DomainAddresses($domainIDfromOrder);
		$shipFromAddressObj = $domainAddressObj->getDefaultProductionFacilityAddressObj();
		$shipToAddressObj = Order::getShippingAddressObject($dbCmd, $orderNumberFromShipID);
	
	
		
		
		// For packages 1 pound and less we may be able to ship through the PostOffice.
		$exactPackageWeight = Shipment::GetWeightFromShipment($dbCmd, $ShipmentID, "ExactWeight");
		
		
		$retArr = array();
	
			
		// Now go through our list of linked shipping method
		// These are sorted in order of Importance.
		$shippingMethodLinkIDsArr = $shippingChoiceObjForOrder->getShippingMethodLinkIDs($customerShippingChoiceID);
		
		$transitTimesObj = self::$shippignMethodsObj->getTransitTimes($shipFromAddressObj, $shipToAddressObj);
		
		foreach($shippingMethodLinkIDsArr as $thisShippingMethodLinkID){
			
			$thisShippingMethodCode = $shippingChoiceObjForOrder->getShippingMethodCodeFromLinkID($thisShippingMethodLinkID);
			
			$transitDaysOnShippingMethod = $transitTimesObj->getTransitDays($thisShippingMethodCode);

			// The Shipping Method might not be available to the desitnation
			if(empty($transitDaysOnShippingMethod))
				continue;
				
				
			$shippingCarrierOfThisMethodCode = self::$shippignMethodsObj->getShippingCarrierFromShippingCode($thisShippingMethodCode);
			
			// Find out if the carrier already has a method in the return array.  If so, don't try to add any more.
			$carrierMethodAlreadyIncludedFlag = false;
			foreach($retArr as $alreadyIncludedMethod){
				
				$alreadyIncludedCarrier = self::$shippignMethodsObj->getShippingCarrierFromShippingCode($alreadyIncludedMethod);
				
				if($alreadyIncludedCarrier == $shippingCarrierOfThisMethodCode)
					$carrierMethodAlreadyIncludedFlag = true;
			}
			if($carrierMethodAlreadyIncludedFlag)
				continue;
				
			
				
			$minWeight = $shippingChoiceObjForOrder->getMinimumWeightForShippingMethodLink($thisShippingMethodLinkID);
			$maxWeight = $shippingChoiceObjForOrder->getMaximumWeightForShippingMethodLink($thisShippingMethodLinkID);
			
			// Skip this Shipping Method Code if it has a weight restriction... and if that weight restriction doesn't match 
			if(!empty($maxWeight)){
				if($minWeight > $exactPackageWeight || $maxWeight < $exactPackageWeight)
					continue;
			}
			
			
			
			
			
			// If there is an Address Type restriction for the Shipping Method Link... then skip this Shipping Method if it doesn't match the customer's shipping address type.
			$addressTypeRestriction = $shippingChoiceObjForOrder->getAddressTypeForShippingDowngradeLink($thisShippingMethodLinkID);

			if($shipToAddressObj->isResidential()){
				if($addressTypeRestriction == ShippingChoices::ADDRESS_TYPE_COMMERCIAL)
					continue;
			}
			else{
				if($addressTypeRestriction == ShippingChoices::ADDRESS_TYPE_RESIDENTIAL)
					continue;
			}
			
			
			
			
				
			
			// If the adjusted days in transit is greater than or equal to what this shipping method is capable of delivering, then let's add it to our return array.
			if($adjustedDaysInTransit >= $transitDaysOnShippingMethod){
				
				// If we have a match on the Primary carrier (for the first method)... then don't try to search for any more.
				if(empty($retArr) && $shippingCarrierOfDefaultMethod == $shippingCarrierOfThisMethodCode)
					return array($thisShippingMethodCode);

				
				$retArr[] = $thisShippingMethodCode;
			}
		}
		
		
		// If we could not find any shipping methods in our list to make it to the destination on time, then just return our default shipping method.
		if(empty($retArr))
			return array($defaultShippingMethodCodeForShippingChoice);

			
		// Make sure that our Primary Shipping carrier is found within the list to be returned.
		// If we do not have it, then add our Default Shipping Method to the bottom of the list.
		// We always need a Primary Shipping Carrier in the return list.
		
		$primaryShippingCarrierFound = false;
		foreach($retArr as $alreadyIncludedMethod){
				
			$alreadyIncludedCarrier = self::$shippignMethodsObj->getShippingCarrierFromShippingCode($alreadyIncludedMethod);
			
			if($alreadyIncludedCarrier == $shippingCarrierOfDefaultMethod)
				$primaryShippingCarrierFound = true;
		}
		
		if(!$primaryShippingCarrierFound)
			$retArr[] = $defaultShippingMethodCodeForShippingChoice;
			
		
		return $retArr;
		
	}

}


?>