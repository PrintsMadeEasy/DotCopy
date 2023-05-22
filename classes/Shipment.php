<?php


class Shipment {
	
	//Wipes out any previous shipments associted with this order
	//Creates shipment entries and link entry to all projects in order.
	//Creates shipment for each unique Product Group (separates BC�s, brochures etc.)
	// It will create shipments using the "Default Shipping Method Link" from the Shipping Choice ID.
	static function ShipmentCreateForOrder(DbCmd $dbCmd, DbCmd $dbCmd2, $orderID, $shippingChoiceID){
	
		if(!preg_match("/^\d+$/", $orderID))
			throw new Exception("Order ID was not received correctly in the fucntion call Shipment::ShipmentCreateForOrder");
	
		// Build a hash of Project/Product IDs assocaited with this order
		//Make sure that the projects have not already been completed or canceled
		$dbCmd->Query("Select ID, ProductID FROM projectsordered WHERE OrderID=" . intval($orderID) . " 
				AND Status!='C' AND Status!='F' ORDER BY ProductID");
	
		if($dbCmd->GetNumRows() == 0)
			throw new Exception("Error in function call Shipment::ShipmentCreateForOrder()");
	
		$ProjectIDhash = array();
	
		while ($row = $dbCmd->GetRow()){
			//Don't include any project(s) for shipment creation... which are linked to shipments that have already gone
			//Makes a 2D array with the ProjectID/Product ID
			if(!Shipment::CheckAnyShipmentsGoneContainingProject($dbCmd2, $row["ID"]))
				array_push($ProjectIDhash, array("ProjectID"=>$row["ID"], "ProdID"=>$row["ProductID"]));
	
		}
		
		
		$domainIDofOrder = Order::getDomainIDFromOrder($orderID);
		
		$shippingChoicesObj = new ShippingChoices($domainIDofOrder);
		
		$defaultShippingMethodCode = $shippingChoicesObj->getDefaultShippingMethodCode($shippingChoiceID);
	
	
		// Now loop through our 2D hash and remove any shipments associated with the ProjectIDs
		// This will ensure a clean slate so that we can create new shipments below --#
		foreach($ProjectIDhash as $detailHash)
			Shipment::ShipmentRemoveProject($dbCmd, $detailHash["ProjectID"]);
	
	
	
		$ProductIdentifier = 0;
		$CurrentShipmentID = 0;
	
		// Get all projects associated to this order
		foreach($ProjectIDhash as $detailHash){
	
			$projectID = $detailHash["ProjectID"];
			$prodID = $detailHash["ProdID"];
	
			// This check will separate group shipments by product ID.
			if($ProductIdentifier != $prodID){
	
				$ProductIdentifier = $prodID;
	
				// We are in a new product group now so create a shipment for it.
				$CurrentShipmentID = Shipment::ShipmentCreate($dbCmd, $projectID, 0, $defaultShippingMethodCode);
			}
			else{
				// There is already a shipment containing the same product as this one... Lets just add this project to that shipment.
				Shipment::ShipmentAddProject($dbCmd, $CurrentShipmentID, $projectID, 0);
			}
		}
		
		//Make sure that the package weights are set
		Shipment::UpdatePackageParametersForOrder($dbCmd, $orderID);
	
	}
	
	
	
	
	
	//Creates shipment entry and link entry to project.
	//Ensures that at least one project is associated with shipment
	//Specify a quantity of the project that the new shipment will have... or leave as 0 to use the full quantity of the project --#
	static function ShipmentCreate(DbCmd $dbCmd, $projectID, $quantity, $shipMethod){
	
		$insertArr["ShippingMethod"] = $shipMethod;
		$NewShipmentID = $dbCmd->InsertQuery("shipments", $insertArr);
		
		// Now that there is a new shipment... make sure there is an entry within the shipment link table --#
		Shipment::ShipmentAddProject($dbCmd, $NewShipmentID, $projectID, $quantity);
		return $NewShipmentID;
	}
	
	
	
	
	//Will Add a project to a shipment within the shipment linking table.
	//If the quantity is greater than the amount available in the project it will print an error
	//If the quantity is 0 then the amount used will be the full amount avaialbe to the project
	static function ShipmentAddProject(DbCmd $dbCmd, $shipmentID, $projectID, $quantity){
	
	
		$dbCmd->Query("SELECT Quantity FROM projectsordered WHERE ID=" . intval($projectID));
		$ProjectQuantity = $dbCmd->GetValue();
	
		if($quantity > $ProjectQuantity || $quantity < 0)
			throw new Exception("Error in function Shipment::ShipmentAddProject()... Illegal quantity was used");
		else if($quantity == 0)
			$quantityToUse = $ProjectQuantity;
		else
			$quantityToUse = $quantity;
	
	
		$insertArr["ProjectID"] = $projectID;
		$insertArr["ShipmentID"] = $shipmentID;
		$insertArr["Quantity"] = $quantityToUse;
	
	
		$dbCmd->InsertQuery("shipmentlink", $insertArr);
	}
	
	
	
	
	
	//Will get take a quantity from a particular ShipmentLinkID and move that quantity into a new shipment
	//If the quantity exceeds the amount that are in the shipment link then the function will print an error
	//If the quantity exactly matches the amount in the shipmentLink table then the new shipment will be created and the old shipmentlink will be removed.  If it is the last shipmentlink for the shipment, then the shipment will also be removed.
	//If the quantity is less than the amount in the shipment link table then a new shipment will be created with partial quantity of the project... so the project will be split among (at least) two shipments, but maybe more.
	//Will return the shipmentID of the new project
	//Will retain the Shipping Methods/arrival dates/ etc when copying
	static function ShipmentMoveQuantityToNewShipment(DbCmd $dbCmd, $Quantity, $shipmentLinkID){
	
		// Find out the existing Quantity for the ShipmentLink quantity we are changing
	
		$dbCmd->Query("SELECT shipmentlink.Quantity as Quantity, shipmentlink.ProjectID AS ProjectID, shipments.ShippingMethod AS ShippingMethod, shipments.ID AS ID FROM shipmentlink 
					INNER JOIN shipments ON shipmentlink.ShipmentID = shipments.ID 
					WHERE shipmentlink.ID=" . intval($shipmentLinkID));
		$row = $dbCmd->GetRow();
	
		$CurrentShipmentLinkQuantity = $row["Quantity"];
		$CurrentProjectID = $row["ProjectID"];
		$CurrentShippingMethod = $row["ShippingMethod"];
		$CurrentShipmentID = $row["ID"];
	
	
		// If the quantities match then we need to delete the old shipmentLink and possibly the shipment.
		if($CurrentShipmentLinkQuantity == $Quantity){
	
			// Find out how many other shipment links are using this shipment ID.
			$dbCmd->Query("SELECT count(*) FROM shipmentlink WHERE ShipmentID=" . $CurrentShipmentID);
			$ShipmentUsageCount = $dbCmd->GetValue();
	
	
			// If there is only 1 link using this shipment ID then it is obviosly the shipmentLink we are deleting.  So the shipment needs to be deleted too.
			if($ShipmentUsageCount == 1)
				$dbCmd->Query("DELETE FROM shipments WHERE ID=$CurrentShipmentID");
	
			$dbCmd->Query("DELETE FROM shipmentlink WHERE ID=$shipmentLinkID");
	
		}
		else if($CurrentShipmentLinkQuantity > $Quantity && $Quantity > 0){
	
			$NewQuantityForOldLink = $CurrentShipmentLinkQuantity  - $Quantity;
			
			$dbCmd->Query("UPDATE shipmentlink SET Quantity='$NewQuantityForOldLink' WHERE ID=" . $shipmentLinkID);
	
		}
		else if($CurrentShipmentLinkQuantity < $Quantity){
			throw new Exception("Error in function Shipment::ShipmentMoveQuantityToNewShipment()... Quantity is to large.");
		}
		else{
			throw new Exception("Error in function Shipment::ShipmentMoveQuantityToNewShipment()... Illegal quantity was used");
		}
	
		// We are in a new product group now so create a shipment for it.
		$NewShipmentID = Shipment::ShipmentCreate($dbCmd, $CurrentProjectID, $Quantity, $CurrentShippingMethod);
	
		// Get the order ID of this shipment Link
		$dbCmd->Query("SELECT OrderID FROM projectsordered 
						WHERE ID=$CurrentProjectID");;
		$ThisOrderID = $dbCmd->GetValue();
	
		// Make sure the package weights are updated
		Shipment::UpdatePackageParametersForOrder($dbCmd, $ThisOrderID);
	
		return $NewShipmentID;
	}
	
	
	// Returns NULL is the ShippingLink does't exist.
	static function getShipmentIDfromShippingLinkID(DbCmd $dbCmd, $shippingLinkID){
		
		$dbCmd->Query("SELECT ShipmentID FROM shipmentlink WHERE ID=" . intval($shippingLinkID));
		return $dbCmd->GetValue();
		
	}
	
	
	//Removes shipment links referencing project
	//Removes referenced shipment if no further projects are linked to it.
	static function ShipmentRemoveProject(DbCmd $dbCmd, $projectID){
	
			$ShipIDarr = array();
	
			// find all shipments that are associated with this project --#
			$dbCmd->Query("SELECT ShipmentID FROM shipmentlink WHERE ProjectID=" . intval($projectID));
	
			while($thisShipID = $dbCmd->GetValue())
				$ShipIDarr[] = $thisShipID;
			
	
			// Loop through all of the shipment IDs and find out if there are any other projects assocated with the shipments
			// If there are other projects then we can just delete the shipmentLink and leave the shipment alone
			// If there are no other projects associated with the shipment then both the shipment and shipmentLink must be deleted
			for($i=0; $i<sizeof($ShipIDarr); $i++){
	
				$dbCmd->Query("SELECT COUNT(DISTINCT ProjectID) FROM shipmentlink WHERE ShipmentID=" . $ShipIDarr[$i]);
				$TotalProjectWithShipment = $dbCmd->GetValue();
	
				// The variable '$TotalProjectWithShipment' must have at least Quantity 1 if it is in this loop
				// If there is more than one project with this ShipmentID when we should NOT delete the shipment
				if($TotalProjectWithShipment == 1)
					$dbCmd->Query("DELETE FROM shipments WHERE ID=" . $ShipIDarr[$i]);
			}
	
	
			// We have just deleted all shipments that are not being shared by any other projects above
			// Now we can delete all shipment links associated with this project --#
			$dbCmd->Query("DELETE FROM shipmentlink WHERE ProjectID=" . $projectID);
	}
	
	// There should never be a case where a shipment is combined so that it shares products from 2 different vendors 
	// This function returns an array of vendor IDs belonging to the shipment
	// One product may have many Vendors associated with it... like one to do the Printing, another to do the UV coating, etc.
	static function GetVendorIDsfromShipment(DbCmd $dbCmd, $ShipmentID){
	
		$dbCmd->Query("SELECT VendorID1, VendorID2, VendorID3, VendorID4, VendorID5, VendorID6
			FROM projectsordered
			INNER JOIN shipmentlink ON shipmentlink.ProjectID = projectsordered.ID
			WHERE shipmentlink.ShipmentID = " .intval($ShipmentID) . "
			LIMIT 1");
	
		$row = $dbCmd->GetRow();
		$retArr = array();
		
		foreach($row as $thisID){
			if($thisID)
				$retArr[] = $thisID;
		}
		
		if(empty($retArr))
			throw new Exception("Error in function call Shipment::GetVendorIDsfromShipment");
	
		return $retArr;
	}
	
	
	//Combine 2 shipments together.  Will get rid of the second shipment and transfer the shipment links into the first
	//Prints an error if one of the shipmentIDs do not exist.
	static function ShipmentCombine(DbCmd $dbCmd, $ShipmentIDkeep, $ShipmentIDdiscard){
	
		// Convert the Arrays into a String (by imploding)... so that we can compare for inequalities.
		$VendorID_keep = implode(",", Shipment::GetVendorIDsfromShipment($dbCmd, $ShipmentIDkeep));
		$VendorID_discard = implode(",", Shipment::GetVendorIDsfromShipment($dbCmd, $ShipmentIDdiscard));
		
		// There can not let people combine shipments with projects from different vendors
		if($VendorID_discard <> $VendorID_keep)
			WebUtil::PrintError("You cannot combine shipments with projects that belong to different vendors.");
	
	
		$dbCmd->Query("SELECT count(*) FROM shipmentlink WHERE ShipmentID=" . $ShipmentIDkeep);
		$ShipmentKeepCount = $dbCmd->GetValue();
	
		$dbCmd->Query("SELECT count(*) FROM shipmentlink WHERE ShipmentID=" . $ShipmentIDdiscard);
		$ShipmentDiscardCount = $dbCmd->GetValue();
	
		if($ShipmentKeepCount <= 0 || $ShipmentDiscardCount <= 0 )
			WebUtil::PrintError("Error in function Shipment::ShipmentCombine()... One or more of the shipment IDs do not exist.");
	
	
		$dbCmd->Query("UPDATE shipmentlink SET ShipmentID='$ShipmentIDkeep' WHERE ShipmentID=" . $ShipmentIDdiscard);
	
		$dbCmd->Query("DELETE FROM shipments WHERE ID=" . $ShipmentIDdiscard);
	
		// Now the problem is that there may be multiple shipmentLinks of the same project in this shipment... since we just did a merge
		$dbCmd->Query("SELECT DISTINCT ProjectID FROM shipmentlink WHERE ShipmentID=" . $ShipmentIDkeep);
	
		$ProjectIDhash = array();
	
		while ($thisProjectID = $dbCmd->GetValue())
			$ProjectIDhash[] = $thisProjectID;
	
	
		foreach($ProjectIDhash as $ThisProjectID){
	
			// See if there is more than 1 shipmentLink for a single Project within this shipument
			$dbCmd->Query("SELECT count(*) FROM shipmentlink WHERE ShipmentID=$ShipmentIDkeep AND ProjectID=$ThisProjectID");
			$countOfProjectsInShipment = $dbCmd->GetValue();
	
			if($countOfProjectsInShipment > 0){
	
				$dbCmd->Query("SELECT SUM(Quantity) FROM shipmentlink WHERE ShipmentID=$ShipmentIDkeep AND ProjectID=$ThisProjectID");
				$TotalProjectQuantity = $dbCmd->GetValue();
	
				// Get rid of all the old shipment links because we are about to create a single one below...
				$dbCmd->Query("DELETE FROM shipmentlink WHERE ShipmentID=$ShipmentIDkeep AND ProjectID=$ThisProjectID");
	
				Shipment::ShipmentAddProject($dbCmd, $ShipmentIDkeep, $ThisProjectID, $TotalProjectQuantity);
			}
		}
	
		// Get the order ID of this shipment Link
		$dbCmd->Query("SELECT OrderID FROM projectsordered WHERE ID=$ThisProjectID");
		$ThisOrderID = $dbCmd->GetValue();
	
		// Make sure the package weights are updated 
		Shipment::UpdatePackageParametersForOrder($dbCmd, $ThisOrderID);
	}
	
	
	
	
	
	static function ShipmentSetDateShipped(DbCmd $dbCmd, $shipmentID, $dateTimeStamp){
	
		// The parameter to this function should be a UNIX TIMESTAMP... make them into MySQL format.
		$mysql_timeFormat = date("YmdHis", $dateTimeStamp);
	
		$dbCmd->Query("UPDATE shipments SET DateShipped='$mysql_timeFormat' WHERE ID=" . intval($shipmentID));
	
	}
	
	
	
	
	
	static function ShipmentSetDateArrived(DbCmd $dbCmd, $shipmentID, $dateTimeStamp){
	
		// The parameter to this function should be a UNIX TIMESTAMP... make them into MySQL format.
		$mysql_timeFormat = date("YmdHis", $dateTimeStamp);
	
		$dbCmd->Query("UPDATE shipments SET DateArrived='$mysql_timeFormat' WHERE ID=" . intval($shipmentID));
	}
	
	
	
	
	
	
	static function ShipmentSetShippingMethod(DbCmd $dbCmd, $shipmentID, $shippingMethodCode){
	
		$dbCmd->Query("UPDATE shipments SET ShippingMethod='" . DbCmd::EscapeSQL($shippingMethodCode) . "' WHERE ID=" . intval($shipmentID));
	
	}
	
	static function getShippingMethodCodeOfShipment(DbCmd $dbCmd, $shipmentID){
	
		$dbCmd->Query("SELECT ShippingMethod FROM shipments WHERE ID=" . intval($shipmentID));
		
		if($dbCmd->GetNumRows() == 0)
			throw new Exception("Error in function call Shipment::getShippingMethodCodeOfShipment.  The shipment ID does not exist.");
		
		$shippingMethodCode = $dbCmd->GetValue();
		
		return $shippingMethodCode;
	}
	
	
	
	
	static function ShipmentSetTrackingNumber(DbCmd $dbCmd, $shipmentID, $trackingNum){
	
		$dbCmd->Query("UPDATE shipments SET TrackingNumber='" . DbCmd::EscapeSQL($trackingNum) . "' WHERE ID=" . intval($shipmentID));
	
	}
	
	
	static function SetShipmentCarrier(DbCmd $dbCmd, $shipmentID, $CarrierName){
		
		if(!in_array($CarrierName, array("UPS", "USPS", "UNKNOWN"))){
			$errorMsg = "Error in function Shipment::SetShipmentCarrier... The carrier name is illegal: $CarrierName ";
			WebUtil::WebmasterError($errorMsg);
			exit($errorMsg);
		}
		
		$dbCmd->Query("UPDATE shipments SET Carrier='" . DbCmd::EscapeSQL($CarrierName) . "' WHERE ID=" . intval($shipmentID));
	}
	
	
	
	
	
	// Manual setting of Shipment parameters
	static function ShipmentSetPackageWeight(DbCmd $dbCmd, $shipmentID, $weight){
	
		
		$dbCmd->Query("UPDATE shipments SET PackageWeight='". DbCmd::EscapeSQL($weight)."' WHERE ID=" . intval($shipmentID));
	}
	
	
	// Get a weight from a particular shipment ID... it can either be "ExactWeight", or a CustomerWeight which we round up... or a CarrierWeight which we round down.
	static function GetWeightFromShipment(DbCmd $dbCmd, $shipmentID, $weightType){
	
		if(!in_array($weightType, array("ExactWeight", "CustomerWeight", "CarrierWeight")))
			throw new Exception("Illegal Weight type in function Shipment::GetWeightFromShipment");
	
		$ShipmentWeight = 0;
	
		$ProdListArr = ProjectGroup::GetProductIDlistFromGroup($dbCmd, $shipmentID, "shipment");
	
		// Loop through all of the products within this shipment and get the total weight of each separate product.
		foreach($ProdListArr as $ThisProdID)
			$ShipmentWeight += ProjectGroup::GetDetailsOfProductInGroup($dbCmd, "ExactWeight", $shipmentID, $ThisProdID, "shipment");
	 
		return $ShipmentWeight;
	}
	
	
	
	
	// Updates type, weight, dimensions for all order shipments using info from all linked projects.
	static function UpdatePackageParametersForOrder(DbCmd $dbCmd, $orderID){
	
		$ShipIDArr = Shipment::GetShipmentsIDsWithinOrder($dbCmd, $orderID, 0, "");
		
		foreach($ShipIDArr as $ThisShipID){
	
			$ShipmentWeight = 0;
	
			$ProdListArr = ProjectGroup::GetProductIDlistFromGroup($dbCmd, $ThisShipID, "shipment");
	
			// Loop through all of the products within this shipment and get the total weight of each separate product.
			foreach($ProdListArr as $ThisProdID)
				$ShipmentWeight += ProjectGroup::GetDetailsOfProductInGroup($dbCmd, "CarrierWeight", $ThisShipID, $ThisProdID, "shipment");
			
			$dbCmd->Query("UPDATE shipments SET PackageWeight='$ShipmentWeight' WHERE ID=" . intval($ThisShipID));
		}
	}
	
	
	
	static function ShipmentSetFreightFees(DbCmd $dbCmd, $ShipmentID, $FreightFee){
	
		$dbCmd->Query("UPDATE shipments SET ShippingCost=\"" . $FreightFee .  "\" WHERE ID=" . intval($ShipmentID));
	
	}
	
	// Returns the order ID of the Shipment
	// Returns NULL if the shipment ID does not exist.
	static function GetOrderIDfromShipmentID(DbCmd $dbCmd, $ShipmentID){
	
		$dbCmd->Query("SELECT projectsordered.OrderID
				FROM projectsordered INNER JOIN shipmentlink ON shipmentlink.ProjectID = projectsordered.ID
				WHERE shipmentlink.ShipmentID=" . intval($ShipmentID) . " LIMIT 1");
	
	
		if($dbCmd->getNumRows() == 0)
			return null;
	
		return $dbCmd->GetValue();
	
	
	}
	
	
	
	
	// Returns an array of projects IDs and quantity
	static function ShipmentGetProjectInfo(DbCmd $dbCmd, $shipmentID){
	
	
		$dbCmd->Query("SELECT ProjectID, Quantity, ID FROM shipmentlink WHERE ShipmentID=" . intval($shipmentID));
	
		$retArr = array();
		
		while($row = $dbCmd->GetRow()){
			//Separate the project ID and quantity with a pipe symbol
			array_push($retArr, $row["ProjectID"] . "|" .  $row["Quantity"] . "|" .  $row["ID"]);
		}
	
		return $retArr;
	
	}
	
	
	
	
	
	static function GetInfoByShipmentID(DbCmd $dbCmd, $shipmentID){
	
		$dbCmd->Query("SELECT TrackingNumber, ShippingMethod, ShippingCost, ShippingRefund, PackageWeight, PackageType, PackageDimensions, Carrier, UNIX_TIMESTAMP(DateShipped) AS DateShipped, UNIX_TIMESTAMP(DateArrived) AS DateArrived 
				FROM shipments WHERE ID=" . intval($shipmentID));
	
		$row = $dbCmd->GetRow();
	
		return $row;
	}
	
	
	
	// Pass in a $ProductID or 0 if you don't care what product it is.
	// Pass in a $VendorRestriction if you want to restrict the list to that Vendor's UserID.
	static function GetShipmentsIDsWithinOrder(DbCmd $dbCmd, $orderID, $ProductID, $VendorRestriction, $includeCanceledProjects = false){
	
		$query = "SELECT DISTINCT shipmentlink.ShipmentID FROM shipmentlink ";
		$query .= "INNER JOIN projectsordered ON shipmentlink.ProjectID = projectsordered.ID ";
		$query .= "WHERE projectsordered.OrderID=" . intval($orderID);
		
		if($ProductID <> 0)
			$query .= " AND projectsordered.ProductID=" . intval($ProductID);
		
		if(!$includeCanceledProjects)
			$query .= " AND projectsordered.Status != 'C'";
			
		$dbCmd->Query($query);
		
		$domainIdOfOrder = Order::getDomainIDFromOrder($orderID);
		
		$ShipmentIDarr = array();
		
		while($thisShipLinkID = $dbCmd->GetValue())
			array_push($ShipmentIDarr, $thisShipLinkID);
		
		if(preg_match("/^\d+$/", $VendorRestriction)){

			// Since this person is a vendor... then the shipmentID list should be limited to them
			$retArr = array();
	
			foreach($ShipmentIDarr as $ThisShipmentID){

				$VendorIDArr = Shipment::GetVendorIDsfromShipment($dbCmd, $ThisShipmentID);

				if(in_array($VendorRestriction, $VendorIDArr)){
					array_push($retArr, $ThisShipmentID);
				}
				else{
					// If they aren't a Vendor... then check if the user has domain permissions.
					$passiveAuthOverrideObj = new Authenticate(Authenticate::login_OVERRIDE, $VendorRestriction);
					
					if($passiveAuthOverrideObj->CheckIfUserCanViewDomainID($domainIdOfOrder)){
						array_push($retArr, $ThisShipmentID);
					}
				}
			}
	
			return $retArr;
		}
		else{
			return $ShipmentIDarr;
		}
	}
	
	static function CheckIfAllShipmentsCompleteInOrder(DbCmd $dbCmd, $orderID){
		
		$orderID = intval($orderID);
	
		$shipmentIDsInOrder = self::GetShipmentsIDsWithinOrder($dbCmd, $orderID, 0, null, false);
		
		$allShipmentsGoneFlag = true;
		
		foreach($shipmentIDsInOrder as $thisShipmentID){
			if(!self::CheckIfShipmentHasGone($dbCmd, $thisShipmentID)){
				$allShipmentsGoneFlag = false;
				break;
			}
		}
		
		return $allShipmentsGoneFlag;
	}
	
	// This method will return 0 if there is at least one shipment which hasn't gone yet.
	// It will also return 0 if one of the shipments has a 0 freight charge.
	// That allows you to distrust the return value if one of the shipments was manually completed and we didn't get freight charges returned.
	static function GetFreightChargesFromOrderIfAllShipmentsValid(DbCmd $dbCmd, $orderID){
		
		$orderID = intval($orderID);
	
		$shipmentIDsInOrder = self::GetShipmentsIDsWithinOrder($dbCmd, $orderID, 0, null, false);
		
		$totalFreightCharges = 0;
		
		foreach($shipmentIDsInOrder as $thisShipmentID){
			
			if(!self::CheckIfShipmentHasGone($dbCmd, $thisShipmentID)){
				$totalFreightCharges = 0;
				break;
			}
			
			$dbCmd->Query("SELECT ShippingCost FROM shipments WHERE ID=" . intval($thisShipmentID));
			$thisShipmentFreightCharge = $dbCmd->GetValue();
			
			if(empty($thisShipmentFreightCharge)){
				$totalFreightCharges = 0;
				break;
			}
			
			$totalFreightCharges += $thisShipmentFreightCharge;
		}
		
		return $totalFreightCharges;
		
	}
	
	static function GetShipmentsIDsWithinProject(DbCmd $dbCmd, $projectID){
	
		$dbCmd->Query("SELECT DISTINCT ShipmentID FROM shipmentlink WHERE ProjectID=" . intval($projectID));
	
		$retArr = array();
	
		while($thisShipID = $dbCmd->GetValue())
			$retArr[] = $thisShipID;
	
		return $retArr;
	}
	
	
	
	static function GetTrackingLink($TrackingNumber, $carrier, $shortDescriptionFlag = false){
	
		if($shortDescriptionFlag)
			$trackingDesc = "Tracking";
		else
			$trackingDesc = $TrackingNumber;
			
		$TrackingNumber = WebUtil::htmlOutput($TrackingNumber);
		$trackingDesc = WebUtil::htmlOutput($trackingDesc);
		
		if($carrier == "USPS"){
			return "<a class='BlueRedLinkRecord' href='http://trkcnfrm1.smi.usps.com/PTSInternetWeb/InterLabelInquiry.do?origTrackNum=$TrackingNumber' target='top'>" . $trackingDesc . "</a>";
		}
		else if($carrier == "UPS"){
			return "<a class='BlueRedLinkRecord' href='http://wwwapps.ups.com/WebTracking/processInputRequest?HTMLVersion=5.0&sort_by=status&term_warn=yes&tracknums_displayed=5&TypeOfInquiryNumber=T&loc=en_US&InquiryNumber1=" . $TrackingNumber  . "&InquiryNumber2=&InquiryNumber3=&InquiryNumber4=&InquiryNumber5=&AgreeToTermsAndConditions=yes&track.x=28&track.y=4' target='top'>" . $trackingDesc . "</a>";
		}
		else{
			// WebUtil::WebmasterError("Error in method GetTrackingLink. The Carrier has not been defined yet: $carrier");
			return "";
		}
	}
	
	
	// Will return the number of shipments within an order (which have not been shipped yet)
	static function GetNumberOfUncompletedShipments(DbCmd $dbCmd, $orderID, &$AuthObj){
	
		$query = "SELECT DISTINCT shipments.ID FROM (shipmentlink ";
		$query .= "INNER JOIN projectsordered ON shipmentlink.ProjectID = projectsordered.ID) ";
		$query .= "INNER JOIN shipments ON shipmentlink.ShipmentID = shipments.ID ";
		$query .= "WHERE projectsordered.OrderID=$orderID AND shipments.TrackingNumber IS NULL";
		
		if($AuthObj->CheckIfBelongsToGroup("VENDOR"))
			$query .= " AND (" . Product::GetVendorIDLimiterSQL($AuthObj->GetUserID()) . ") ";
		
		$dbCmd->Query($query);
	
		return $dbCmd->GetNumRows();
	}
	
	// Returns true if the package has already been shipped 
	static function CheckIfShipmentHasGone(DbCmd $dbCmd, $ShipID){
	
		$dbCmd->Query("SELECT DateShipped FROM shipments WHERE ID=" . intval($ShipID));
		$shipped = $dbCmd->GetValue();
	
		if(empty($shipped))
			return false;
		else
			return true;
	}
	

	
	// Returns true if any shipment(s) attached to the project have gone out
	static function CheckAnyShipmentsGoneContainingProject(DbCmd $dbCmd, $projectid){
	
		$dbCmd->Query("SELECT shipments.ID FROM shipments 
				INNER JOIN shipmentlink ON shipmentlink.ShipmentID = shipments.ID
				WHERE shipments.DateShipped IS NOT NULL AND shipmentlink.ProjectID=" . intval($projectid));
	
		$NumShipments = $dbCmd->GetNumRows();
	
		if($NumShipments == 0)
			return false;
		else
			return true;
	}
	
	
	// Returns true if ALL of the shipments associated with the project have gone out
	static function CheckCompletedShipmentsForProject(DbCmd $dbCmd, $projectid){
	
	
		$dbCmd->Query("SELECT shipments.ID FROM shipments 
				INNER JOIN shipmentlink ON shipmentlink.ShipmentID = shipments.ID
				WHERE shipments.DateShipped IS NULL AND shipmentlink.ProjectID=" . intval($projectid));
		
		$NumShipments = $dbCmd->GetNumRows();
	
		if($NumShipments == 0)
			return true;
		else
			return false;
	}
	
	
	// If an order is complete... it should have Freight charges with it.  This will return a sum of all freight charges within an order.
	static function GetFreightChargesFromOrder(DbCmd $dbCmd, $orderID, $VendorRestriction){
	
		$query = "SELECT SUM(shipments.ShippingCost) FROM (shipmentlink ";
		$query .= "INNER JOIN projectsordered ON shipmentlink.ProjectID = projectsordered.ID) ";
		$query .= "INNER JOIN shipments ON shipmentlink.ShipmentID = shipments.ID ";
		$query .= "WHERE projectsordered.OrderID=" . intval($orderID) . " AND shipments.DateShipped IS NOT NULL";
	
		if(preg_match("/^\d+$/", $VendorRestriction))
			$query .= " AND (" . Product::GetVendorIDLimiterSQL($VendorRestriction) . ") ";
		
		$dbCmd->Query($query);
		
		$total = $dbCmd->GetValue();
	
		if(empty($total))
			return 0;
		else
			return $total;
	}
	
	// If an order is complete... it should have Package Weight uploaded with it.  This will return a sum of all Package weight within an order.
	static function GetPackageWeightsFromOrder(DbCmd $dbCmd, $orderID, $VendorRestriction){
	
		$query = "SELECT SUM(shipments.PackageWeight) FROM (shipmentlink ";
		$query .= "INNER JOIN projectsordered ON shipmentlink.ProjectID = projectsordered.ID) ";
		$query .= "INNER JOIN shipments ON shipmentlink.ShipmentID = shipments.ID ";
		$query .= "WHERE projectsordered.OrderID=" . intval($orderID) . " AND shipments.DateShipped IS NOT NULL";
	
		if(preg_match("/^\d+$/", $VendorRestriction))
			$query .= " AND (" . Product::GetVendorIDLimiterSQL($VendorRestriction) . ") ";
		
		$dbCmd->Query($query);
		
		$total = $dbCmd->GetValue();
	
		if(empty($total))
			return 0;
		else
			return $total;
	}
	
	

	
	
	
	// This function will tell us if there is a "Package Shipping Method" within the order that does not match what the customer selected on checkout.
	// That will tell us if our system automatically downgraded the shipping method or something.
	static function checkIfShippingMethodDoesntMatchDefaultInShippingChoice(DbCmd $dbCmd, $order_number){
		
		$shipmentIDsInOrderArr = Shipment::GetShipmentsIDsWithinOrder($dbCmd, $order_number, 0, "");
	
		$allShippingMethodCodesInOrder = array();
	
		foreach($shipmentIDsInOrderArr as $thisShipmentID)
			$allShippingMethodCodesInOrder[] = Shipment::getShippingMethodCodeOfShipment($dbCmd, $thisShipmentID);
	
		$allShippingMethodCodesInOrder = array_unique($allShippingMethodCodesInOrder);
		
		$shippingChoiceID = Order::getShippingChoiceIDfromOrder($dbCmd, $order_number);
		
		// Just in case no shipments have been created for this order yet.
		if(sizeof($allShippingMethodCodesInOrder) == 0)
			return false;
		
		// If there are different "package shipping methods" within the order then obviously they can't all match.
		// The order has 1 Shipping Choice for all of the projects.
		if(sizeof($allShippingMethodCodesInOrder) > 1)
			return true;
			
		// Get the Default Shipping Method Code of the Shipping Choice.
		$defaultShippingMethodCode = ShippingChoices::getStaticDefaultShippingMethodCode($shippingChoiceID);
		
		if(!in_array($defaultShippingMethodCode, $allShippingMethodCodesInOrder))
			return true;
		
		return false;
	}
	
	
	// Returns a unique list of Project ID's contained within a shipment.
	static function GetProjectIDsWithinShipment(DbCmd $dbCmd, $shipmentID){
	
		$retArr = array();
		
		$dbCmd->Query("SELECT DISTINCT ProjectID FROM shipmentlink WHERE ShipmentID=" . intval($shipmentID));
	
		while($projectID = $dbCmd->GetValue())
			$retArr[] = $projectID;
		
		return $retArr;
	}
	
}

