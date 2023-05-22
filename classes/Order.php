<?

class Order {


	// static methods
	
	
	// Will reset the Ship Date and Arrival Date of whatever item we want
	// $RefType is a string that can be "order" or "project". 
	// An order will set the time estimates for all projects in the order
	// A project will reset the dates for the project.... as well as any other projects in the order having the same ProductID
	// For the 4rth paramter, pass in a TimeStamp to re-calculate dates from that point.
	// The last parameter is a flag that tells us if we should keep the old ShipDate when figuring out the new Arrival Date
	// .... Setting this flag to TRUE will basically render the $FromTime variable Useless
	// .... It will not have an affect on the Print Date
	// .... This is useful for upgrading a shipping method after the order has been placed.  In this case it shouldn't have any effect on the shipment date, just the arrival date.
	// .... If we are running this function on a new order that does not have any Ship Dates defined yet... then setting this flag to true won't do anything.
	static function ResetProductionAndShipmentDates($dbCmd, $RefID, $RefType, $FromTime, $MaintainShipDate = false){
	
		// Holds a list of all Unique ProjectID's that we are reseting
		$ProjectIDarr = array();
		
		// Holds a list of all Unique Product ID's 
		$ProductIDarr = array();
	
		if($RefType == "order"){
			$OrderID = $RefID;
			$dbCmd->Query("SELECT ID, ProductID FROM projectsordered WHERE OrderID=$OrderID");
			while($row = $dbCmd->GetRow())
				$ProjectIDarr[] = $row;
				
			$dbCmd->Query("SELECT DISTINCT(ProductID) FROM projectsordered WHERE OrderID=$OrderID");
			while($thisProductID = $dbCmd->GetValue())
				$ProductIDarr[] = $thisProductID ;
		}
		else if($RefType == "project"){
			$OrderID = Order::GetOrderIDfromProjectID($dbCmd, $RefID);
			$ProductID = ProjectBase::GetProductIDFromProjectRecord($dbCmd, $RefID, "admin");
			
			$dbCmd->Query("SELECT ID, ProductID FROM projectsordered WHERE OrderID=$OrderID AND ProductID=$ProductID");
			while($row = $dbCmd->GetRow())
				$ProjectIDarr[] = $row;
			
			// Only 1 item in this array if our RefType == "project"
			$ProductIDarr[] = $ProductID;
		}
		else{
			throw new Exception("Illegal RefType in Order::ResetProductionAndShipmentDates");
		}
			
		if(!Order::CheckIfUserHasPermissionToSeeOrder($OrderID))
			throw new Exception("Can't reset shipping times because the order doesn't exist.");
			
		$domainIDofOrder = Order::getDomainIDFromOrder($OrderID);
		
		
		$domainAddressObjOfOrder = new DomainAddresses($domainIDofOrder);
		$shipFromAddressObj = $domainAddressObjOfOrder->getDefaultProductionFacilityAddressObj();

		$shipToAddressObj = Order::getShippingAddressObject($dbCmd, $OrderID);
			
		
		$dbCmd->Query("SELECT ShippingChoiceID FROM orders WHERE ID=$OrderID");
		$shippingChoiceID = $dbCmd->GetValue();
	
		if(!$shippingChoiceID)
			throw new Exception("Order $RefID was not found in Order::ResetProductionAndShipmentDates");
		
		// Build an array that has Ship Dates and Arrival dates for every separate Product ID
		$timeEstimateDatesArr = array();
	
		// Each ProductID should get their very own Estimated Ship & Arrival Date
		foreach($ProductIDarr as $ThisProductID){
		
			$timeEstObj = new TimeEstimate($ThisProductID);
			$timeEstObj->setShippingChoiceID($shippingChoiceID);
			
			// If we are going to maintain the Ship Date but then keep the Arrival Date... we need to figure out what the old ship date was for this Product
			// In case we are running this function on a new order that does not have any Ship Dates defined yet... 
			$useCurrentDatesFlag = false;
			if($MaintainShipDate){
				
				// Just look for the first Matching Product within the Order and extract the ShipDate
				// When we find it, then overwrite the Arrival Date with one based upon the
				foreach($ProjectIDarr as $ThisProjectArr){
					
					if($ThisProjectArr["ProductID"] != $ThisProductID)
						continue;
					
					$dbCmd->Query("SELECT UNIX_TIMESTAMP(EstShipDate) as ShipDate,  UNIX_TIMESTAMP(EstPrintDate) as PrintDate, UNIX_TIMESTAMP(CutOffTime) as CutOffTime FROM projectsordered WHERE ID=" . $ThisProjectArr["ID"]);	
					$currentDatesRow = $dbCmd->GetRow();
	
					// If the time stamp has not been defined then it would show up as a time way in the past... so don't use it.
					if(date("Y", $currentDatesRow["ShipDate"]) < 2004)
						break;
					
					$ShipDateArr = array("year" => date("Y", $currentDatesRow["ShipDate"]), "month" => date("n", $currentDatesRow["ShipDate"]), "day"=> date("j", $currentDatesRow["ShipDate"]));
					$PrintDateArr = array("year" => date("Y", $currentDatesRow["PrintDate"]), "month" => date("n", $currentDatesRow["PrintDate"]), "day"=> date("j", $currentDatesRow["PrintDate"]));
					
					$CutOffTime = $currentDatesRow["CutOffTime"];
					
					// Calculate new Arrival Dates... using the current Ship Date
					$shipDateTimestamp = $currentDatesRow["ShipDate"];
					
					$arrivalDateHash = $timeEstObj->estimateArrivalDate(date("Y", $shipDateTimestamp), date("n", $shipDateTimestamp), date("j", $shipDateTimestamp));					
					
					
					// We need to figure out if the Shipping Choice has a guaranteed Arrival Hour/Minute to the given destination.
					// In order to do that we have to figure out the Default Shipping Method linked to the Shipping Choice ID.
					$shippingChoiceObj = new ShippingChoices($domainIDofOrder);
					
					$shippingMethodCode = $shippingChoiceObj->getDefaultShippingMethodCode($shippingChoiceID);
					
					if(empty($shippingMethodCode))
						throw new Exception("Error in method ResetProductionAndShipmentDates, the Shipping Choice is empty.");

					$shippingMethodObj = new ShippingMethods($domainIDofOrder);
					
					$transitTimeObj = $shippingMethodObj->getTransitTimes($shipFromAddressObj, $shipToAddressObj);
					
					$arrivalHour = $transitTimeObj->getArrivalHour($shippingMethodCode);
					$arrivalMinute = $transitTimeObj->getArrivalMinute($shippingMethodCode, 0);

					if(empty($arrivalHour))
						$arrivalDateTimeStamp = mktime(0, 30, 0, $arrivalDateHash["month"], $arrivalDateHash["day"], $arrivalDateHash["year"]);
					else	
						$arrivalDateTimeStamp = mktime($arrivalHour, $arrivalMinute, 0, $arrivalDateHash["month"], $arrivalDateHash["day"], $arrivalDateHash["year"]);

					// Set a flag letting this function know that we were able to find an adequate match for the dates using the existing product ID
					$useCurrentDatesFlag = true;
				}
			}
			
			
			// If were were not able to use the Old Dates... then calculate new ones based upon our starting Time Stamp
			if(!$useCurrentDatesFlag){
				
				$ShipDateArr = $timeEstObj->estimateShipOrPrintDate("ship", $FromTime);
				$PrintDateArr = $timeEstObj->estimateShipOrPrintDate("print", $FromTime);
				$arrivalDateTimeStamp = $timeEstObj->getArrivalTimeStamp($FromTime, $shipFromAddressObj, $shipToAddressObj);
				$CutOffTime = $timeEstObj->getCutoffTimeStamp($FromTime);
			}


			
			// Fill the arrays with a Mysql time stamp.  Have it start at 11 PM just to make sure that we are away from the boundary
			// Since we keep the timestamp near the end of the day 11PM... it will be easy to compare timestamps on the current day/time to find out if it late, during the middle of the day. 
			$timeEstimateDatesArr["$ThisProductID"]["EstShipDate"] = date("YmdHis", mktime(23, 0, 0, $ShipDateArr["month"], $ShipDateArr["day"], $ShipDateArr["year"]));
			$timeEstimateDatesArr["$ThisProductID"]["EstArrivalDate"] = $arrivalDateTimeStamp;
			$timeEstimateDatesArr["$ThisProductID"]["EstPrintDate"] = date("YmdHis", mktime(23, 0, 0, $PrintDateArr["month"], $PrintDateArr["day"], $PrintDateArr["year"]));
			$timeEstimateDatesArr["$ThisProductID"]["CutOffTime"] = date("YmdHis", $CutOffTime);
		}
		
		// Update the TimeStamps for each project in the DB
		foreach($ProjectIDarr as $ThisProjectArr){
	
			// This new array will contain the fields EstShipDate & EstArrivalDate for updating the DB
			// It doesn't have to recalculate ShipDates for each individual project
			$UpdateDBArr = $timeEstimateDatesArr[$ThisProjectArr["ProductID"]];
	
			$dbCmd->UpdateQuery("projectsordered", $UpdateDBArr, "ID=" . $ThisProjectArr["ID"]);
		}
	
	
	}

	
	// Pass in 2 Project Ordered Objects.  The old one should be a clone of the new one (before any changes were set).
	// The Object should already contain any changes that is wished to be saved.
	// It does not matter if the Data for the Project was already saved to the DB or not.  We will save it again anyway just to be safe
	// If this function detects and increase in price (and it is not authorized for the Customer)... it will return an error message
	// ... in the case of an auth error, the Old Project object will be restored in this case and re-saved to the DB.
	// Otherwise it will return a blank string and Update the Database for the Project... Calculate new Tax amounts... new Weights (and possibily new shipping charges)
	static function AuthorizeChangesToProjectOrderedObj(DbCmd $dbCmd, DbCmd $dbCmd2, $oldProjectObj, $newProjectObj){
	
		if($oldProjectObj->getViewTypeOfProject() != "ordered" || $newProjectObj->getViewTypeOfProject() != "ordered")
			throw new Exception("Error in function Order::AuthorizeChangesToProjectOrderedObj.  The view type is incorrect for 1 of the Project Objects");
	
		if($oldProjectObj->getProjectID() != $newProjectObj->getProjectID())
			throw new Exception("Error in function Order::AuthorizeChangesToProjectOrderedObj.  The Project ID's must match between both project objects.");
	
	

		// Get the Shipping method, UserID, etc From the Order that the Project belongs to.
		$dbCmd->Query("SELECT orders.ShippingChoiceID, orders.UserID, orders.ID AS OrderID, orders.DomainID AS DomainID, UNIX_TIMESTAMP(DateOrdered) AS DateOrdered  FROM 
				projectsordered INNER JOIN orders ON projectsordered.OrderID=orders.ID 
				WHERE projectsordered.ID=" . $newProjectObj->getProjectID());
		$row = $dbCmd->GetRow();
		$OrderShippingChoiceID = $row["ShippingChoiceID"];
		$OrderID = $row["OrderID"];
		$DateOrdered = $row["DateOrdered"];
		$DomainIDofOrder = $row["DomainID"];
		
		

		$PaymentsObj = new Payments($DomainIDofOrder);
	
		
		
		
		// Figure out the percentage on the old Project and calculate how much of the Project "Subtotal" did not have a "Discount Exemption".
		// We are going to adjust the percntage on the new Project so that we are giving the same exact discount percentage against non-Discount-Exempt Product Options.
		if($newProjectObj->getCustomerDiscount() != 1){
		
			$oldCustomerSubtotal = $oldProjectObj->getCustomerSubTotal();
			$oldDiscountedSubtotal = $oldCustomerSubtotal - $oldProjectObj->getAmountOfSubtotalThatIsDiscountExempt();
			$oldDiscountPercentage = $oldProjectObj->getCustomerDiscount();
			$oldDiscountedDollarAmount = $oldDiscountPercentage * $oldCustomerSubtotal;
	
			
			// Try to estimate what the "Overall Customer Discount" would have been... assuming that it wasn't manipulated by the Discount Exemption.
			if($oldDiscountedSubtotal > 0)
				$customerDiscountIfExemptionDidntExist = $oldDiscountedDollarAmount / $oldDiscountedSubtotal;
			else
				$customerDiscountIfExemptionDidntExist = 0;
				
			// In case someone changed the percentages manually on the order... we would never want to go over 100% discount.
			if($customerDiscountIfExemptionDidntExist > 1)
				$customerDiscountIfExemptionDidntExist = 1;
				
			// Now take the theoretical "Customer Overall Discount" and apply that to the new Project Subtotal (that is not discount exempt)
			// Then figure out the overall percentage to be applied against the entire project.
			$newCustomerSubtotal = $newProjectObj->getCustomerSubTotal();
			$newSubtotalNotDiscountExempt = $newProjectObj->getCustomerSubTotal() - $newProjectObj->getAmountOfSubtotalThatIsDiscountExempt();
			$newDiscountedAmount = $customerDiscountIfExemptionDidntExist * $newSubtotalNotDiscountExempt;
			
			// Format for Significant Digits and store discount percentage in the new Project Object.
			$overallNewProjectDiscount = Widgets::GetDiscountPercentFormated($newCustomerSubtotal, $newDiscountedAmount) / 100;
			$newProjectObj->setCustomerDiscount($overallNewProjectDiscount);
		}


	
	
		// Update the Database for now... we may revert the changes to the Database using the Old Project Object if new charges are not able to go through.
		$newProjectObj->updateDatabase();
		
		$OldFreightTotal = Order::GetCustomerShippingQuote($dbCmd, $OrderID);
		
		// Since we may be making a change to the quantity, reset the shipments to their default..  That way it will update the shipment link table
		Shipment::ShipmentCreateForOrder($dbCmd, $dbCmd2, $OrderID, $OrderShippingChoiceID);
	
		// Now save the new quote for the customer shipping charges.. a change in quantity could affect the cost of shipping
		Order::UpdateShippingQuote($dbCmd, $OrderID);
		Order::UpdateSalesTaxForOrder($dbCmd, $OrderID);
		
		// Get the new totals of the order after the change to the options
		$GrandTotal = Order::GetGrandTotalOfOrder($dbCmd, $OrderID);
		$TaxTotal = Order::GetTotalFromOrder($dbCmd, $OrderID, "customertax");
		$FreightTotal = Order::GetCustomerShippingQuote($dbCmd, $OrderID);
		
		// If a soft balance adjustment (with an Auth Only) is put on an order... it will not show up on the Invoice Grand Total.
		// Soft Balance adjustments can only be put on an order before the order is complete.  The pupose is to hide a "line item" on the invoice, but still charge the customer.
		$softBalanceAdjustments = BalanceAdjustment::getCustomerAdjustmentsTotalFromOrder_AuthOnly($dbCmd, $OrderID);
		
		// Possibly authorize a larger charge on the person's credit card
		if(!$PaymentsObj->AuthorizeNewAmountForOrder($OrderID, ($GrandTotal + $softBalanceAdjustments), $TaxTotal, $FreightTotal)){
		
			// The authorization did NOT get approved.. so we need to roll back the changes and show and error to the user
			$oldProjectObj->updateDatabaseWithRawData();
	
			// Since we may be making a change to the quantity, reset the shipments to their default..  That way it will update the shipment link table --#
			Shipment::ShipmentCreateForOrder($dbCmd, $dbCmd2, $OrderID, $OrderShippingChoiceID);
	
			// Now save the new quote for the customer shipping charges
			Order::UpdateShippingQuote($dbCmd, $OrderID, $OldFreightTotal);
			Order::UpdateSalesTaxForOrder($dbCmd, $OrderID);
	
	
			$errorMessage = "The changes that you made to this project caused an increase in the Grand Total for the order.  ";
			$errorMessage .= "The new charges were not authorized.  The reason given from the bank is...<br><br>" . $PaymentsObj->GetErrorReason();
	
	
			return $errorMessage;
		}
		
		
	
		// The authorization went through OK then.
		
		// If the ProductID was changed between the Old and new Projects then Reset the Estimated Ship & Arrival Dates
		// Do it from the Time that the order was placed.
		if($oldProjectObj->getProductID() != $newProjectObj->getProductID())
			Order::ResetProductionAndShipmentDates($dbCmd, $newProjectObj->getProjectID(), "project", $DateOrdered);
		
		
		return "";
	}
	

	// Returns FALSE if the order does not exist, or if it is not within their pool of allowable domains.
	// If the user is not logged in it will also return false;
	static function CheckIfUserHasPermissionToSeeOrder($orderID){
		
		$orderID = intval($orderID);
		
		if(empty($orderID))
			return false;
		
		$dbCmd = new DbCmd();
		$dbCmd->Query("SELECT DomainID, UserID FROM orders WHERE ID=$orderID");
		
		if($dbCmd->GetNumRows() == 0)
			return false;

		$row = $dbCmd->GetRow();
		$domainID = $row["DomainID"];
		$customerUserID = $row["UserID"];

		$passiveAuthObj = Authenticate::getPassiveAuthObject();

		if(!$passiveAuthObj->CheckIfLoggedIn())
			return false;
	
		// They must either be a member in the backend... or the user ID attached to the order must be the UserID logged in.
		if($customerUserID != $passiveAuthObj->GetUserID() && !$passiveAuthObj->CheckIfBelongsToGroup("MEMBER"))
			return false;
	
		if(!$passiveAuthObj->CheckIfUserCanViewDomainID($domainID))
			return false;
					
		return true;
	}
	

	static function getOrderBillingType($OrderID){
		
		$dbCmd = new DbCmd();
		$dbCmd->Query("SELECT BillingType FROM orders WHERE ID=" . intval($OrderID));
		return $dbCmd->GetValue();
	}

	
	static function saveThirdPartyInvoiceToOrder($orderId, $thirdPartyInvoice){
		
		$dbCmd = new DbCmd();
		$dbCmd->UpdateQuery("orders", array("ThirdPartyInvoiceID"=>$thirdPartyInvoice), "ID=" . intval($orderId));
	}
		
	static function getOrderIdByThirdPartyInvoiceId($thirdPartyInvoiceID){
		
		if(preg_match("/^3d\d+$/i", $thirdPartyInvoiceID)) {
		
			$dbCmd = new DbCmd();
			$dbCmd->Query("SELECT ID FROM orders WHERE ThirdPartyInvoiceID='" . DbCmd::EscapeLikeQuery($thirdPartyInvoiceID) . "'");
			return $dbCmd->GetValue();
		} 
		else 
		{
			return 0;
		}
	}
	
	
	static function CheckIfUserOwnsOrder(DbCmd $dbCmd, $OrderID, $userID){

		WebUtil::EnsureDigit($OrderID);

		$dbCmd->Query("SELECT UserID FROM orders WHERE ID=" . intval($OrderID));
		$dbUserID = $dbCmd->GetValue();

		if($dbUserID == $userID)
			return true;
		else
			return false;
	}


	// The order must already be created in the DB before calling this method
	// It will record the shipping address of the order
	// The user ID is the person making the change... or it could be the customer ID if they are placing an order
	static function RecordShippingAddressHistory(DbCmd $dbCmd, $OrderID, $UserID){

		WebUtil::EnsureDigit($OrderID);
		WebUtil::EnsureDigit($UserID);

		$dbCmd->Query("SELECT ShippingName, ShippingCompany, ShippingAddress, ShippingAddressTwo, 
				ShippingCity, ShippingState, ShippingZip, ShippingCountry, ShippingResidentialFlag 
				FROM orders WHERE ID=$OrderID");

		if($dbCmd->GetNumRows() == 0)
			throw new Exception("Error in function call RecordShippingAddressHistory.  The order ID does not exist.");

		// Insert to Status history table.  The field names should match to the orders table.
		$row = $dbCmd->GetRow();
		unset($row["ID"]);

		$row["UserID"] = $UserID;
		$row["OrderID"] = $OrderID;
		$row["Date"] = date("YmdHis");

		$dbCmd->InsertQuery("shippingaddresshistory",  $row);
	}


	// The order must already be created in the DB before calling this method
	// It will record the shipping address of the order 
	// The user ID is the person making the change... or it could be the customer ID if they are placing an order
	static function RecordShippingChoiceHistory(DbCmd $dbCmd, $OrderID, $UserID){

		WebUtil::EnsureDigit($OrderID);
		WebUtil::EnsureDigit($UserID);

		$dbCmd->Query("SELECT ShippingChoiceID FROM orders WHERE ID=$OrderID");

		if($dbCmd->GetNumRows() == 0)
			throw new Exception("Error in function call RecordShippingChoiceHistory.  The order ID does not exist.");


		$Insertrow["ShippingChoiceID"] = $dbCmd->GetValue();
		$Insertrow["UserID"] = $UserID;
		$Insertrow["OrderID"] = $OrderID;
		$Insertrow["Date"] = date("YmdHis");

		$dbCmd->InsertQuery("shippingchoicehistory",  $Insertrow);
	}
	
	
	// If the domain has shipping dowgrades... this should store what Shipping Method was picked at the time of "Export Shipments"
	// That will allow us to troublshoot problems in downgrade algorythms and point out human error.
	static function RecordShippingMethodHistory(DbCmd $dbCmd, $OrderID, $shippingMethodCode){

		$dbCmd->Query("SELECT ShippingMethodCode FROM shippingmethodhistory USE INDEX (shippingmethodhistory_OrderID)
						WHERE OrderID=" . intval($OrderID) . " 
						AND Date > DATE_ADD(NOW(), INTERVAL -12 HOUR ) 
						ORDER BY ID DESC LIMIT 1");

		$lastShippingMethodCodeExport = $dbCmd->GetValue();
		
		// If the last shipping export in the history (within 12 hours) matches the current shipping downgrade... then don't add duplicates.
		if($lastShippingMethodCodeExport == $shippingMethodCode)
			return;

		$Insertrow["ShippingMethodCode"] = $shippingMethodCode;
		$Insertrow["OrderID"] = $OrderID;
		$Insertrow["Date"] = date("YmdHis");

		$dbCmd->InsertQuery("shippingmethodhistory",  $Insertrow);
	}
	
	

	// Updates all projects within an order with the Shipping Priority character (based upon the Shipping Method selected on the order).
	static function ChangeShippingPriorityOnProjects(DbCmd $dbCmd, $OrderID){

		WebUtil::EnsureDigit($OrderID, true, "Order Number must be a digit on function ChangeShippingPriorityOnProjects.");

		$orderShippingChoiceID = Order::getShippingChoiceIDfromOrder($dbCmd, $OrderID);
		
		$shippingChoiceObj = new ShippingChoices(Order::getDomainIDFromOrder($OrderID));
		$shippingPriorityCode = $shippingChoiceObj->getPriorityOfShippingChoice($orderShippingChoiceID);

		$dbCmd->UpdateQuery("projectsordered", array("ShippingPriority"=>$shippingPriorityCode), "OrderID=$OrderID");

	}

	/*
	 * @return MailingAddress
	 */
	static function getShippingAddressObject(DbCmd $dbCmd, $OrderID){
		
		WebUtil::EnsureDigit($OrderID, true, "Order Number must be a digit on function getShippingAddressObject.");
		
		$dbCmd->Query("SELECT ShippingName, ShippingCompany, ShippingAddress, ShippingAddressTwo, ShippingCity, ShippingState, ShippingZip, ShippingCountry, ShippingResidentialFlag, Phone 
						FROM orders INNER JOIN users ON users.ID = orders.UserID WHERE orders.ID=" . intval($OrderID));
		if($dbCmd->GetNumRows() == 0)
			throw new Exception("Error in method getShippingAddressObject");
		$r = $dbCmd->GetRow();
		
		$mailingAddressObj = new MailingAddress($r["ShippingName"],$r["ShippingCompany"],$r["ShippingAddress"], $r["ShippingAddressTwo"], $r["ShippingCity"], $r["ShippingState"], $r["ShippingZip"], $r["ShippingCountry"], ($r["ShippingResidentialFlag"]=="Y"?true:false),$r["Phone"]); 
	
		return $mailingAddressObj;
	}
	
	/*
	 * @return MailingAddress
	 */
	static function getBillingAddressObject(DbCmd $dbCmd, $OrderID){
		
		WebUtil::EnsureDigit($OrderID, true, "Order Number must be a digit on function getBillingAddressObject.");
		
		$dbCmd->Query("SELECT BillingName, BillingCompany, BillingAddress, BillingAddressTwo, BillingCity, BillingState, BillingZip, BillingCountry, ShippingResidentialFlag, Phone 
						FROM orders INNER JOIN users ON users.ID = orders.UserID WHERE orders.ID=" . intval($OrderID));
		if($dbCmd->GetNumRows() == 0)
			throw new Exception("Error in method getBillingAddressObject");
		$r = $dbCmd->GetRow();
		
		$mailingAddressObj = new MailingAddress($r["BillingName"],$r["BillingCompany"],$r["BillingAddress"], $r["BillingAddressTwo"], $r["BillingCity"], $r["BillingState"], $r["BillingZip"], $r["BillingCountry"], false ,$r["Phone"]); 
	
		return $mailingAddressObj;
	}
	
	
	static function getShippingInstructions(DbCmd $dbCmd, $OrderID){
		
		WebUtil::EnsureDigit($OrderID, true, "Order Number must be a digit on function getShippingInstructions.");
		
		$dbCmd->Query("SELECT ShippingInstructions FROM orders WHERE ID=" . intval($OrderID));
		if($dbCmd->GetNumRows() == 0)
			throw new Exception("Error in method getShippingInstructions");
		return $dbCmd->GetValue();
	}
	


	static function CalculateHashedOrderNo($mainorder_ID){

		//  Take the two first digits of the order id.  Add them together and convert the sum into a ascii letter --
		$no_1 = substr($mainorder_ID, 0, 1);
		$no_2 = substr($mainorder_ID, 1, 1);
		$sum = $no_1 + $no_2;

		// just some more gibberish (up to 3 numbers)
		$gibberish = substr(($mainorder_ID * 3), 1, 3);

		return  chr(64 + $sum) . $gibberish;
	}


	// This is just some trickery to prevent users from figuring out how many orders there are on the website
	static function GetHashedOrderNo($mainorder_ID){

		return  Order::CalculateHashedOrderNo($mainorder_ID) . "-" . $mainorder_ID;
	}


	static function GetOrderIDfromProjectID(DbCmd $dbCmd, $projectID){

		WebUtil::EnsureDigit($projectID);

		$dbCmd->Query("SELECT OrderID FROM projectsordered WHERE ID=$projectID");
		if($dbCmd->GetNumRows() == 0)
			throw new Exception("Error in function call GetOrderIDfromProjectID");
		return $dbCmd->GetValue();
	}

	static function getShippingChoiceIDfromOrder(DbCmd $dbCmd, $OrderID){

		WebUtil::EnsureDigit($OrderID);

		$dbCmd->Query("SELECT ShippingChoiceID FROM orders WHERE ID=$OrderID");
		if($dbCmd->GetNumRows() == 0)
			throw new Exception("Error in function call to getShippingChoiceIDfromOrder()");
		return $dbCmd->GetValue();
	}

	static function GetShippingChoiceIDFromProjectID(DbCmd $dbCmd, $projectID){

		WebUtil::EnsureDigit($projectID);

		$dbCmd->Query("SELECT ShippingChoiceID FROM orders INNER JOIN 
				projectsordered ON projectsordered.OrderID = orders.ID WHERE projectsordered.ID=$projectID LIMIT 1");
		if($dbCmd->GetNumRows() == 0)
			throw new Exception("Error in function call to GetShippingChoiceIDFromProjectID()");
		return $dbCmd->GetValue();
	}

	static function GetUserIDFromOrder(DbCmd $dbCmd, $OrderID){

		WebUtil::EnsureDigit($OrderID);

		$dbCmd->Query("SELECT UserID FROM orders WHERE ID=$OrderID");
		if($dbCmd->GetNumRows() == 0)
			throw new Exception("Error in function call to GetUserIDFromOrder()");
		return $dbCmd->GetValue();
	}

	static function GetUserIDFromProject(DbCmd $dbCmd, $ProjectID){

		WebUtil::EnsureDigit($ProjectID);

		$dbCmd->Query("SELECT UserID FROM orders JOIN projectsordered AS PO ON PO.OrderID = orders.ID WHERE PO.ID=$ProjectID");
		if($dbCmd->GetNumRows() == 0)
			throw new Exception("Error in function call to GetUserIDFromProject()");
		return $dbCmd->GetValue();
	}

	
	static function GetTrackingCodeReferral(DbCmd $dbCmd, $orderno){
		
		$dbCmd->Query("SELECT Referral FROM orders WHERE ID=" . intval($orderno));
		return $dbCmd->GetValue();
	}
	

	// You can get the following order totals from a given order.
	// customerdiscount, customersubtotal, vendorsubtotal, subtotaltax, shippingtax, customertax
	// Pass in a VendorID limiter if you want to limit the Vendor Subtotal to a particular vendor only.  There can be many vendors associated with a single project, let alone a single order.
	// Pass in a Product ID in order to limit the discount/vendorSub/customerSub/TaxSub to only those products.  It will not have an effect on The S&H or S&H Tax report totals.
	static function GetTotalFromOrder(DbCmd $dbCmd, $orderno, $ReportType, $vendorIDLimiter = null, $productLimiter = null){

		WebUtil::EnsureDigit($orderno);

		if($vendorIDLimiter && $ReportType != "vendorsubtotal")
			throw new Exception("Error in function call GetTotalFromOrder.  If you are passing in a VendorID restriction then the report type must be a 'vendorsubtotal'");

		$productIDlimitSQL = "";
		if($productLimiter){
			WebUtil::EnsureDigit($productLimiter);
			$productIDlimitSQL = " AND ProductID='$productLimiter' ";
		}

		// This is a bug in MYSQL... 
		// The select statment....     select ROUND(36.49 * 0.50, 2);   returns $18.24   and it should return $18.23
		// Mysql says some implementations of the C library round up .. and other down.  So for calculating the discount I need to use the round in PHP instead.
		if($ReportType == "customerdiscount"){	

			$query = "SELECT CustomerDiscount, CustomerSubtotal FROM projectsordered ";
			$query .= " WHERE Status !='C' " . $productIDlimitSQL;
			$query .= " AND OrderID=" . $orderno;

			$CustomerDiscount = 0;

			$dbCmd->Query($query);

			while($row=$dbCmd->GetRow())
				$CustomerDiscount += round($row["CustomerDiscount"] * $row["CustomerSubtotal"], 2);


			return number_format($CustomerDiscount, 2, '.', '');

		}
		else{
			$query = "SELECT SUM(CustomerSubtotal) AS CustSub, SUM(VendorSubtotal1) AS V1, SUM(VendorSubtotal2) AS V2, SUM(VendorSubtotal3) AS V3, SUM(VendorSubtotal4) AS V4, SUM(VendorSubtotal5) AS V5, SUM(VendorSubtotal6) AS V6, 
					SUM(CustomerTax) AS CusTax FROM projectsordered WHERE Status !='C' AND OrderID=" . $orderno . $productIDlimitSQL;

			$dbCmd->Query($query);
			$row=$dbCmd->GetRow();

			$CustomerSubtotal = number_format($row["CustSub"], 2, '.', '');
			$VendorSubtotal = number_format(($row["V1"] + $row["V2"] + $row["V3"] + $row["V4"] + $row["V5"] + $row["V6"]), 2, '.', '');
			$CustomerTax = number_format($row["CusTax"], 2, '.', '');

			if($ReportType == "customersubtotal"){
				return number_format($CustomerSubtotal, 2, '.', '');
			}
			else if($ReportType == "vendorsubtotal"){

				if($vendorIDLimiter){
					WebUtil::EnsureDigit($vendorIDLimiter);
					$VendorSubtotal = 0;

					// We have between 1 and 6 Vendors with each project.
					for($i=1; $i<=6; $i++){
						$dbCmd->Query("SELECT SUM(VendorSubtotal" . $i . ") FROM projectsordered WHERE VendorID". $i . "= $vendorIDLimiter AND Status !='C' AND OrderID=" . $orderno);
						$vendorPartial = $dbCmd->GetValue();
						$VendorSubtotal += $vendorPartial;
					}

					$VendorSubtotal = number_format($VendorSubtotal, 2, '.', '');
				}

				return number_format($VendorSubtotal, 2, '.', '');

			}
			else if($ReportType == "subtotaltax"){
				return number_format($CustomerTax, 2, '.', '');
			}
			else if($ReportType == "shippingtax"){
				$dbCmd->Query("SELECT ShippingTax FROM orders WHERE ID=$orderno");
				return number_format($dbCmd->GetValue(), 2, '.', '');
			}
			else if($ReportType == "customertax"){

				// We also need to get the tax for S&H charges in addition to the tax on the subtotal
				$dbCmd->Query("SELECT ShippingTax FROM orders WHERE ID=$orderno");
				$CustomerTax += $dbCmd->GetValue();			

				return number_format($CustomerTax, 2, '.', '');
			}
			else
				throw new Exception("Error in ReportType within function GetTotalFromOrder");
		}
	}
	


	// Get the Grand Total of an Order
	static function GetGrandTotalOfOrder(DbCmd $dbCmd, $mainOrderID){

		WebUtil::EnsureDigit($mainOrderID);

		// Get all project within the order that are not canceled 
		$dbCmd->Query("SELECT CustomerSubtotal, CustomerDiscount, CustomerTax FROM projectsordered WHERE OrderID=" . $mainOrderID . " AND Status!='C'");

		$GrandTotal = 0;
		$projectFound = false;

		while($row=$dbCmd->GetRow()){

			$projectFound = true;

			$CustomerSubtotal = $row["CustomerSubtotal"];
			$CustomerDiscount = $row["CustomerDiscount"];
			$CustomerTax = $row["CustomerTax"];

			$GrandTotal += $CustomerSubtotal - round($CustomerSubtotal * $CustomerDiscount, 2) + $CustomerTax;
		}

		// Don't add the S&H Charges unless there is an active project
		if($projectFound){
			//Now get the Customer Shipping Charges
			$dbCmd->Query("SELECT ShippingQuote FROM orders WHERE ID=" . $mainOrderID );
			$CustomerShipping = $dbCmd->GetValue();

			$GrandTotal += $CustomerShipping;

			// We also need to get the tax for S&H charges
			$dbCmd->Query("SELECT ShippingTax FROM orders WHERE ID=$mainOrderID");
			$ShippingTax = $dbCmd->GetValue();

			$GrandTotal += $ShippingTax;
		}

		return number_format($GrandTotal, 2, '.', '');
	}

	// should be called after an order is completed.  It will update the sales tax field within the DB based on the subtotal of the project
	static function UpdateSalesTaxForProject(DbCmd $dbCmd, $ProjectID){

		WebUtil::EnsureDigit($ProjectID);

		$dbCmd->Query("SELECT projectsordered.CustomerSubtotal as CustomerSubtotal, orders.ShippingState as ShippingState, projectsordered.CustomerDiscount as CustomerDiscount
					FROM projectsordered INNER JOIN orders on projectsordered.OrderID = orders.ID 
					WHERE projectsordered.ID=" . $ProjectID);

		if($dbCmd->GetNumRows() == 0)
			throw new Exception("No project found in function call UpdateSalesTaxForProject");

		$row = $dbCmd->GetRow();

		$CustomerSubtotal = $row["CustomerSubtotal"];
		$ShippingState = $row["ShippingState"];
		$CustomerDiscount = $row["CustomerDiscount"];

		if(empty($CustomerDiscount))
			$CustomerDiscount = 0;

		$CustomerTax = number_format(round((Constants::GetSalesTaxConstant($ShippingState) * ($CustomerSubtotal  -  round($CustomerSubtotal * $CustomerDiscount, 2))), 2), 2);

		$dbCmd->UpdateQuery("projectsordered", array("CustomerTax"=>$CustomerTax), "ID=$ProjectID");
	}
	
	static function getProjectIDsInOrder(DbCmd $dbCmd, $OrderID){

		WebUtil::EnsureDigit($OrderID);

		$ProjectIDArr = array();

		$dbCmd->Query("SELECT ID FROM projectsordered WHERE OrderID=" . $OrderID);	
		while($thisProjID = $dbCmd->GetValue())
			$ProjectIDArr[] = $thisProjID;

		return $ProjectIDArr;
	}
	
	// Returns a unique list of Vendor ID's in the order.
	static function getVendorIDsInOrder(DbCmd $dbCmd, $OrderID){
		
		WebUtil::EnsureDigit($OrderID);
		
		$allVendorIDs = array();
		
		$allProjectIDsInOrder = Order::getProjectIDsInOrder($dbCmd, $OrderID);
		foreach($allProjectIDsInOrder as $thisProjectID)
			$allVendorIDs = array_merge($allVendorIDs, ProjectOrdered::GetUniqueVendorIDsFromOrder($dbCmd, $thisProjectID));
		
		$allVendorIDs = array_unique($allVendorIDs);
		
		return $allVendorIDs;
	}

	static function UpdateSalesTaxForOrder(DbCmd $dbCmd, $OrderID){

		WebUtil::EnsureDigit($OrderID);

		$ProjectIDArr = array();

		$dbCmd->Query("SELECT ID FROM projectsordered WHERE OrderID=" . $OrderID);	
		while($thisProjID = $dbCmd->GetValue())
			$ProjectIDArr[] = $thisProjID;


		// Now loop through the array and update all of the individual sales tax entries
		foreach($ProjectIDArr as $ThisProductID)
			Order::UpdateSalesTaxForProject($dbCmd, $ThisProductID);

		// Calculate the TAX for S&H Charges
		$dbCmd->Query("SELECT ShippingQuote, ShippingState FROM orders WHERE ID=" . $OrderID);
		$row = $dbCmd->GetRow();

		$ShippingTax = number_format(round((Constants::GetSalesTaxConstant($row["ShippingState"]) * $row["ShippingQuote"]), 2), 2);

		$dbCmd->UpdateQuery("orders", array("ShippingTax"=>$ShippingTax), "ID=$OrderID");
	}


	static function GetCustomerShippingQuote(DbCmd $dbCmd, $orderno){

		WebUtil::EnsureDigit($orderno);

		$dbCmd->Query("SELECT ShippingQuote FROM orders WHERE ID=" . $orderno);

		if($dbCmd->GetNumRows() == 0)
			throw new Exception("No order found in function call GetCustomerShippingQuote()");

		return number_format($dbCmd->GetValue(), 2, '.', '');
	}



	// Returns a hash containing all of the project numbers and order descriptions for the given Main Order ID
	// Pass in "" for the vendor ID if you don't want to restrict.
	static function GetMultipleOrdersDesc(DbCmd $dbCmd, $mainorder_ID, $vendorID){

		$query = "SELECT ID, OrderDescription FROM projectsordered WHERE OrderID=$mainorder_ID";

		if(!empty($vendorID))
			$query .= " AND (" . Product::GetVendorIDLimiterSQL($vendorID) . ")";

		$dbCmd->Query($query);

		$MultipleOrdersArr = array();

		while($row = $dbCmd->GetRow())
			$MultipleOrdersArr[] = array("REF"=>$row["ID"], "DESC"=>$row["OrderDescription"]);

		return $MultipleOrdersArr;
	}


	static function ManuallyCompleteOrder(DbCmd $dbCmd, $projectrecord, $UserID){

		WebUtil::EnsureDigit($projectrecord, true);
		
		$OrderID = Order::GetOrderIDfromProjectID($dbCmd, $projectrecord);
		
		if(!Order::CheckIfUserHasPermissionToSeeOrder($OrderID))
			throw new Exception("Can't complete order because the order doesn't exist.");
				

		$PaymentsObj = new Payments(Order::getDomainIDFromOrder($OrderID));

		$UpdateArr["Status"] = "F";
		$dbCmd->UpdateQuery("projectsordered", $UpdateArr, "ID =" . $projectrecord);

		ProjectHistory::RecordProjectHistory($dbCmd, $projectrecord, "Manually Completed", $UserID);

		// Since the project was just marked as finished, we may need chare the customer now.
		Order::OrderChangedCheckForPaymentCapture($dbCmd, $OrderID, $PaymentsObj);
	}

	// When a project is completed, we will want to check and see if it is the last one in the order
	// If it is then we want to capture the funds for the order
	static function OrderChangedCheckForPaymentCapture(DbCmd $dbCmd, $OrderID, &$PaymentsObj){

		// Only capture funds when the order is not empty and there are no more un-finished projects inside 
		if(Order::CheckIfOrderComplete($dbCmd, $OrderID) && !Order::CheckForEmptyOrder($dbCmd, $OrderID) && Order::CheckForActiveProjectWithinOrder($dbCmd, $OrderID)){

			$PaymentsObj->OrderNeedsPaymentCapture($OrderID);

			// In case this customer has a Sales Rep... we need to pay out the Sales Commissions
			SalesCommissions::PossiblyGiveSalesCommissionsForOrder($dbCmd, $OrderID);

			// In case there are any Saved Projects linked to this order... it will clear the Customer Assistance charges on those
			// We don't want the customer to keep paying for a Photoshop touchup every time that they re-order.
			Order::ClearCustAssistChargesFromOrderID($dbCmd, $OrderID);
		}
	}


	// Builds the HTML for links to multiple orders.
	static function GetMultipleOrderLinks($MultipleOrdersArr, $MainOrderID, $projectorderid, $ViewType){

		$displayOrderLink = "ad_order.php";
		$displayProjectLink = "ad_project.php";
		$displayArtworkLink = "ad_proof_artwork.php";

		$multiOrdersLinks = "";

		$noCache = time();

		foreach ($MultipleOrdersArr as $MP_hash){

			if($MP_hash["REF"] <> $projectorderid){
				$OrderBoldStart = "";
				$OrderBoldEnd = "";
			}
			else{
				// Make the current order bold
				$OrderBoldStart = "<b>";
				$OrderBoldEnd = "</b>";
			}

			if($ViewType == "admin"){
				$multiOrdersLinks .= "<a class='BlueRedLink' href='./". $displayOrderLink . "?orderno=" . $MainOrderID . "&nocache=$noCache'>" . $MainOrderID . "</a> - ";
				$multiOrdersLinks .= "<a class='BlueRedLink' href='./". $displayProjectLink . "?projectorderid=" . $MP_hash["REF"] . "&nocache=$noCache'>P" . $MP_hash['REF'] . "</a>";
				$multiOrdersLinks .= " :: $OrderBoldStart " . $MP_hash['DESC'] . "$OrderBoldEnd<br>";
			}
			else if($ViewType == "proof"){
				$multiOrdersLinks .= "<a class='BlueRedLink' href='./". $displayArtworkLink . "?projectid=" . $MP_hash["REF"] . "&nocache=$noCache'>" . $OrderBoldStart . "A" . $MP_hash['REF'] . $OrderBoldEnd . "</a>";
				$multiOrdersLinks .= " :: $OrderBoldStart " . $MP_hash['DESC'] . "$OrderBoldEnd<br>";
			}
			else
				throw new Exception("Illegal ViewType in function call GetMultipleOrderLinks");

		}

		return $multiOrdersLinks;
	}

	// Will update the database with the amount the customer is supposed to pay for shipping...
	// By ommiting the 3rd parameter... it will calculate the shipping automatically...  taking package weights and shipping methods into consideration
	// If the shipping quote was previously $0.00 then we will not update the shipping quote... unless the priceOverride function is set.  This may be usefull for Free reprints, etc.
	// You can pass in a PriceOverride with the String "RECALCULATE" if you want to forcefully re-calculate the shipping method (even if it was previously $0.00).
	static function UpdateShippingQuote(DbCmd $dbCmd, $orderID, $PriceOverride = ""){

		WebUtil::EnsureDigit($orderID);
		
		if(!Order::CheckIfUserHasPermissionToSeeOrder($orderID))
			throw new Exception("Can't Update shipping quote because the order doesn't exist.");

		$ShippingQuote = Order::GetCustomerShippingQuote($dbCmd, $orderID);
		$shippingChoiceID = Order::getShippingChoiceIDfromOrder($dbCmd, $orderID);

		if($ShippingQuote == "0.00" && empty($PriceOverride))
			return;
	
		$shippingAddressObj = Order::getShippingAddressObject($dbCmd, $orderID);

		// Check the integrity of the Price Override figure.
		if(!empty($PriceOverride) && !preg_match("/^\d+(\.\d{1,2})?$/", $PriceOverride) && $PriceOverride != "RECALCULATE")
			throw new Exception("Error in Method UpdateShippingQuote. When overriding the Shipping Price, it must be a valid Float value or contain the string 'RECALCULATE'");

		if(!empty($PriceOverride)&& $PriceOverride != "RECALCULATE")
			$TotalQuote = $PriceOverride;
		else
			$TotalQuote = ShippingPrices::getTotalShippingPriceForGroup($orderID, "order", $shippingChoiceID, $shippingAddressObj);	

		$dbCmd->UpdateQuery("orders", array("ShippingQuote" => $TotalQuote), "ID=$orderID");
	}
	


	// Will return a Multi Dimensional hash containing product ID's, quantities and options for an order
	// Do a var_dump to find out the structure of the parts
	static function GetDescriptionOfLargeOrder($dbCmd, $OrderID){

		$OrderID = intval($OrderID);
		$retHash = array();

		$ProdIDArr = ProjectGroup::GetProductIDlistFromGroup($dbCmd, $OrderID, "order");

		foreach($ProdIDArr as $ThisProdID){
			$retHash["$ThisProdID"] = array();

			//Get a unique list of quantities for products in this order
			$dbCmd->Query("SELECT DISTINCT Quantity, OrderDescription FROM projectsordered 
					WHERE ProductID=$ThisProdID AND OrderID=$OrderID AND Status!='C'");

			while($row = $dbCmd->GetRow()){
				$Quan =  $row["Quantity"];
				$retHash["$ThisProdID"]["$Quan"] = array();
				$retHash["$ThisProdID"]["$Quan"]["OrderDescription"] = $row["OrderDescription"];

				$retHash["$ThisProdID"]["$Quan"]["OptionsDescription"] = array();
			}

			//Now loop through the Quantities
			foreach(array_keys($retHash["$ThisProdID"]) as $thisQuantity){

				// Now add an aray entry for every combination of options belonging to the quantity and ProductID		
				$dbCmd->Query("SELECT DISTINCT OptionsDescription FROM projectsordered 
						WHERE ProductID=$ThisProdID AND OrderID=$OrderID 
						AND Status!='C' AND Quantity=$thisQuantity");

				while($OptionsDescription = $dbCmd->GetValue())
					$retHash["$ThisProdID"]["$thisQuantity"]["OptionsDescription"][$OptionsDescription] = array("ProjectQuantity"=>0);  //Set Quantity to 0 Temporarily

				//To avoid doing a nested DB query... go through our hash of options and get Project Quantities
				foreach(array_keys($retHash["$ThisProdID"]["$thisQuantity"]["OptionsDescription"]) as $thisOptionsConfigStr){

					// Now get a count of projects belonging to the quantity, product ID, and Options configuration
					$dbCmd->Query("SELECT COUNT(*) FROM projectsordered 
							WHERE ProductID=$ThisProdID AND OrderID=$OrderID 
							AND Status!='C' AND Quantity=$thisQuantity 
							AND OptionsDescription='" . DbCmd::EscapeSQL($thisOptionsConfigStr) . "'");

					$totalProjects = $dbCmd->GetValue();

					$retHash["$ThisProdID"]["$thisQuantity"]["OptionsDescription"][$thisOptionsConfigStr]["ProjectQuantity"] = $totalProjects;
				}
			}
		}

		return $retHash;	
	}


	// Returns TRUE if all of the projects within an order have been completed... or canceled 
	// If the order is totally closed, then the function returns FALSE
	static function CheckIfOrderComplete(DbCmd $dbCmd, $orderID){

		$query = "SELECT COUNT(*) FROM projectsordered WHERE OrderID=$orderID ";

		//Add in any status values that we don't want to count
		$query .= " AND Status != 'F' AND Status != 'C'";

		$dbCmd->Query($query);

		if($dbCmd->GetValue() > 0)
			return false;
		else
			return true;

	}

	// Returns TRUE if the shipping Method for the order can still be changed 
	static function CheckIfOrderCanChangeShipping(DbCmd $dbCmd, $orderID){

		$query = "SELECT COUNT(*) FROM projectsordered WHERE OrderID=$orderID ";

		//Add in any status values that we don't want to count
		$query .= " AND Status != 'F' AND Status != 'C' AND Status != 'B'";

		$dbCmd->Query($query);

		if($dbCmd->GetValue() > 0)
			return true;
		else
			return false;
	}

	// Returns TRUE if this order is waiting to have the funds captured. 
	static function CheckIfOrderIsWaitingForCapture(DbCmd $dbCmd, $orderID){

		$dbCmd->Query("SELECT COUNT(*) FROM charges WHERE OrderID=$orderID AND ChargeType='N'");

		if($dbCmd->GetValue() == 0)
			return false;
		else
			return true;
	}

	// Returns TRUE if there is a still a project left within the order that has not been canceled
	// Returns FALSE if no project exists within the order, or they have all been canceled.
	static function CheckForActiveProjectWithinOrder(DbCmd $dbCmd, $orderID){

		$query = "SELECT COUNT(*) FROM projectsordered WHERE OrderID=$orderID ";

		//Add in any status values that we don't want to count
		$query .= " AND Status != 'C'";

		$dbCmd->Query($query);

		if($dbCmd->GetValue() > 0)
			return true;
		else
			return false;
	}


	// Returns TRUE if there are no projects associated with the order
	static function CheckForEmptyOrder(DbCmd $dbCmd, $orderID){

		$dbCmd->Query("SELECT COUNT(*) FROM projectsordered WHERE OrderID=$orderID");

		if($dbCmd->GetValue() == 0)
			return true;
		else
			return false;

	}


	// Will return true if a captured payment exists within an order... false if not
	// For example.  if a project has been canceled... we do not want to be able to reactivate it if payment has alredy been caputred for other projects
	static function CheckForCapturedPaymentWithinOrder(DbCmd $dbCmd, $orderID){

		$dbCmd->Query("SELECT COUNT(*) FROM charges WHERE OrderID=$orderID AND ChargeType='C'");

		if($dbCmd->GetValue() == 0)
			return false;
		else
			return true;

	}

	static function GetNumberOfCredtitCardErrors(DbCmd $dbCmd){
		
		$domainObj = Domain::singleton();
		$dbCmd->Query("SELECT charges.ID FROM charges USE INDEX (charges_Status) INNER JOIN orders on orders.ID = charges.OrderID 
						WHERE charges.Status='D' OR charges.Status='E' AND
						(" . DbHelper::getOrClauseFromArray("orders.DomainID", $domainObj->getSelectedDomainIDs()) . ")");
		$returnArr = $dbCmd->GetValueArr();
		return sizeof(array_unique($returnArr));
	}


	// When a project is completed we want to remove any Admin Charges (like "Customer Assistance") charges placed on a Saved Projects
	// That way when the customer re-orders they don't have to pay for the touch-up fees twice.
	// This works at an ORDER Level.
	static function ClearCustAssistChargesFromOrderID(DbCmd $dbCmd, $orderID){

		WebUtil::EnsureDigit($orderID);

		$projectIDarr = array();

		$dbCmd->Query("SELECT ID FROM projectsordered WHERE OrderID=$orderID");
		while($projectID = $dbCmd->GetValue())
			$projectIDarr[] = $projectID;


		foreach($projectIDarr as $thisProjectID){

			$SavedIDlink = ProjectOrdered::GetSavedIDLinkFromProjectOrdered($dbCmd, $thisProjectID);

			if($SavedIDlink != 0){

				$projectSavedObj = ProjectBase::getProjectObjectByViewType($dbCmd, "saved", $SavedIDlink);

				// Returns True if it was able to change it.  
				// No point in updating the DB unless there is a change.
				if($projectSavedObj->clearAdminOptionIfSet())
					$projectSavedObj->updateDatabase();
			}
		}
	}


	// If the Guaranteed Ship Date is today, or anytime in the past, the function will return true;
	// If this project has been canceled or finished, the function will always return false
	static function CheckIfProjectShouldShipToday(DbCmd $dbCmd, $projectrecord){

		WebUtil::EnsureDigit($projectrecord);

		$dbCmd->Query("SELECT UNIX_TIMESTAMP(EstShipDate) AS ShipDate, Status FROM projectsordered WHERE ID=$projectrecord");
		if($dbCmd->GetNumRows() == 0)
			throw new Exception("Problem in function CheckIfProjectShouldShipToday");
		$row = $dbCmd->GetRow();

		$shipDate = $row["ShipDate"];
		$projectStatus = $row["Status"];

		if($projectStatus == "C" || $projectStatus == "F")
			return false;

		// We are only interested in the "Day" it will ship... not the hour, minute, etc.
		$baseTimeStamp_Ship = mktime(1, 1, 1, date("n", $shipDate), date("j", $shipDate), date("Y", $shipDate));
		$baseTimeStamp_Now = mktime(1, 1, 1, date("n"), date("j"), date("Y"));

		if($baseTimeStamp_Ship <= $baseTimeStamp_Now)
			return true;
		else
			return false;

	}


	static function BuildTrackingNumbersForProject(DbCmd $dbCmd, $ProjectID, $shortDescriptionFlag = false){

		// Build a list of tracking number for all of the boxes that were shipped.
		$TrackingNumber = "";

		$ProjectShipmentIDArr = Shipment::GetShipmentsIDsWithinProject($dbCmd, $ProjectID);

		// Now collect information about tracking numbers
		foreach($ProjectShipmentIDArr as  $ThisShipmentID){
			$shipmentInfoHash = Shipment::GetInfoByShipmentID($dbCmd, $ThisShipmentID);

			if(!empty($shipmentInfoHash["TrackingNumber"])){

				if(!empty($TrackingNumber))
					$TrackingNumber .= "<br>";

				if(!empty($shipmentInfoHash["Carrier"])){
					if($shortDescriptionFlag)
						$TrackingNumber .= WebUtil::htmlOutput($shipmentInfoHash["Carrier"]) . " ";
					else
						$TrackingNumber .= "with " . WebUtil::htmlOutput($shipmentInfoHash["Carrier"]) . " ";
				}

				$TrackingNumber .= Shipment::GetTrackingLink($shipmentInfoHash["TrackingNumber"], $shipmentInfoHash["Carrier"], $shortDescriptionFlag);
			}
		}

		return $TrackingNumber;
	}

	static function BuildTrackingNumbersForOrder(DbCmd $dbCmd, $OrderID, $shortDescriptionFlag = false){

		$TrackingNumber = "";

		$OrderShipmentIDArr = Shipment::GetShipmentsIDsWithinOrder($dbCmd, $OrderID, 0, "");
		foreach($OrderShipmentIDArr as $ThisShipmentID){
			$shipmentInfoHash = Shipment::GetInfoByShipmentID($dbCmd, $ThisShipmentID);

			if(!empty($shipmentInfoHash["TrackingNumber"])){

				if(!empty($shipmentInfoHash["Carrier"]))
					$TrackingNumber .= "<br>with " . WebUtil::htmlOutput($shipmentInfoHash["Carrier"]) . " ";
				else
					$TrackingNumber .= "<br>";

				$TrackingNumber .= Shipment::GetTrackingLink($shipmentInfoHash["TrackingNumber"], $shipmentInfoHash["Carrier"], $shortDescriptionFlag);

			}
		}

		return $TrackingNumber;
	}


	// Returns True or False... depending on whether this order is a repeat customer  
	// If this order is a reprint, then it returns false.
	static function CheckIfOrderIsRepeat(DbCmd $dbCmd, $OrderID){
	
		$ThisUserID = Order::GetUserIDFromOrder($dbCmd, $OrderID);
	
		$dbCmd->Query("SELECT COUNT(*) FROM orders WHERE UserID=$ThisUserID");
		$TotalOrderCount = $dbCmd->GetValue();
		
		//If there is only 1 reprint within the order... then we consider the whole order to be a reprint.
		if($TotalOrderCount > 1 && Order::GetCountOfReprintsInOrder($dbCmd, $OrderID) == 0)
			return true;
		else
			return false;
	}

	// Tells us how many re-prints there are within the order  
	static function GetCountOfReprintsInOrder(DbCmd $dbCmd, $OrderID){
	
		$OrderID = intval($OrderID);
		
		$dbCmd->Query("SELECT COUNT(*) FROM projectsordered WHERE RePrintLink!=0 AND OrderID=" . $OrderID);
		return $dbCmd->GetValue();
	}
		
	
	// Tells us how many projects exist within an order for the vendor   
	static function GetProjectCountByVendor(DbCmd $dbCmd, $VendorID, $OrderID){
		
		$OrderID = intval($OrderID);
		
		$dbCmd->Query("SELECT COUNT(*) FROM projectsordered WHERE OrderID=$OrderID AND (" . Product::GetVendorIDLimiterSQL($VendorID) . ")");
		return $dbCmd->GetValue();
	}

	// Tells us how many projects a user has ordered.  Pass in a vendor ID if we want to limit the count to them. 
	static function GetProjectCountByUser(DbCmd $dbCmd, $CustomerID, $VendorRestriction){
		
		$CustomerID = intval($CustomerID);
		
		$query = "SELECT COUNT(*) FROM projectsordered INNER JOIN orders ON projectsordered.OrderID = orders.ID WHERE orders.UserID='$CustomerID'";
		if(!empty($VendorRestriction))
			$query .= " AND (" . Product::GetVendorIDLimiterSQL($VendorRestriction) . ")";
	
		$dbCmd->Query($query);
		return $dbCmd->GetValue();
	}
	
	
	// This function can aquire locks on an order.  Typically if an artwork is being edited by an admin... the same admin should finish editing all of the artworks for the order
	// Function returns an INT.. if it returns '0' it means that a lock does not exist on the artwork yet
	// Otherwise it means that a lock does exist, and the integer is the UserID of the person that owns it
	// If the 4rth parameters is set to true, then it will not attempt to aquire a lock,  false it will
	// The last parameter is options.  If $forceControl is true it will release the lock (if someone else owns it) and lock it for the current person
	static function ArtworkArbitration(DbCmd $dbCmd, $OrderID, $UserID, $viewOnly, $forceControl = false){
	
	
		// First thing we do is to clean any arbitration entries that are over 10 mintues old
		// Get the Date that the thread was started 
		$Ten_Minute_old_Timestamp = time() - 600;
		$mysql_timestamp = date("YmdHis", $Ten_Minute_old_Timestamp);
		$dbCmd->Query("DELETE FROM orderarbitration WHERE DateLastModified < $mysql_timestamp");
	
		// Now find out if there is another user that has this order 
		$dbCmd->Query("SELECT UserID FROM orderarbitration WHERE UserID != $UserID AND OrderID = $OrderID");
		$userIDresult = $dbCmd->GetValue();
		if(empty($userIDresult))
			$userIDresult = 0;
		
		if($forceControl)
			$dbCmd->Query("DELETE FROM orderarbitration WHERE OrderID = $OrderID");
		
		if((!$viewOnly && $userIDresult == 0) || $forceControl){
			// Granted there may be duplicate arbitration entries for each user on a particular order.  But the columns are indexed and they get cleaned out after 5 minutes
			$insertArr["OrderID"] = $OrderID;
			$insertArr["UserID"] = $UserID;
			$insertArr["DateLastModified"] = date("YmdHis");
			
			$dbCmd->InsertQuery("orderarbitration",  $insertArr);
		}
	
		return $userIDresult;
	}
	
	// Will return the OPTION tags for an HTML list menu that shows all project within an order 
	static function BuildDropDownForProjectLink(DbCmd $dbCmd, $orderno, $VendorRestriction, $SelectedProject){
	
		$query = "SELECT ID, OrderDescription, Status FROM projectsordered WHERE OrderID=" . intval($orderno);
	
		if(!empty($VendorRestriction))
			$query .= " AND (" . Product::GetVendorIDLimiterSQL($VendorRestriction) . ")";
	
		$dbCmd->Query($query);
	
		$ProjectCount = $dbCmd->GetNumRows();
		if($ProjectCount == 1)
			$DropDownDesc = " 1 Project";
		else
			$DropDownDesc = " " . $ProjectCount . " Projects";
	
	
		// Create an array that will be used to fill up the drop down list 
		$ProjectListArray = array();
	
		//Make the first choice in the array read how many projects are in this order.
		$ProjectListArray["first"] = $DropDownDesc;
	
		while($row = $dbCmd->GetRow()){
	
			//Make the choice read like ... 100 Business cards - Printed
			$ProjChoiceDesc = "P" . $row["ID"] . " - " . $row["OrderDescription"] . " - " . ProjectStatus::GetProjectStatusDescription($row["Status"]);
	
			//Add to the hash
			$ProjectListArray[$row["ID"]] = $ProjChoiceDesc;
		}
		return Widgets::buildSelect($ProjectListArray, array($SelectedProject));
	
	}
	
	
	static function getDomainIDFromOrder($orderID){
		
		$dbCmd = new DbCmd();
		
		$dbCmd->Query("SELECT DomainID FROM orders WHERE ID=" . intval($orderID));
		$domainID = $dbCmd->GetValue();
		
		if(empty($domainID))
			throw new Exception("Error in method getDomainIDFromOrder $orderID");
		
		return $domainID;
		
	}
	
	// Returns TRUE is there is at least one product in the Shopping Cart that will be shipped instead of mailed.
	static function checkIfProductInOrderWillBeShipped($orderID){
		
		$orderID = intval($orderID);
		
		$dbCmd = new DbCmd();
		$productdIDsInOrder = ProjectGroup::GetProductIDlistFromGroup($dbCmd, $orderID, "order");
		
		foreach($productdIDsInOrder as $thisProductID){
			if(!Product::checkIfProductHasMailingServices($dbCmd, $thisProductID))
				return true;
		}
		
		return false;		
	}
	
	// Pass in an order number like H3T-2343
	// Will throw an exception if the order number is not correct, doesn't match the hash, or the user does not have permission to see it.
	// Returns the OrderID without the Hash.
	static function getOrderIDfromOrderHash($orderHased){
		
		$orderNumberOnly = 0;
		
		$matches = array();
		
		if(!preg_match("/^\w+\-(\d+)$/", $orderHased, $matches)){
			throw new Exception("The Order Hash is not in proper format.");
		}
		else{
			
			$orderNumberOnly = $matches[1];
			
			if(!Order::CheckIfUserHasPermissionToSeeOrder($orderNumberOnly))
				throw new Exception("User does not have permission to see this Order ID");
			
			if(Order::GetHashedOrderNo($orderNumberOnly) != $orderHased)
				throw new Exception("Order ID and Hash to not match.");
		}
		return $orderNumberOnly;
	}
	
	static function checkIfOrderIDexists($orderID){
		
		$orderID = intval($orderID);
		
		$dbCmd = new DbCmd();
		
		$dbCmd->Query("SELECT COUNT(*) FROM orders WHERE ID=$orderID");
		if($dbCmd->GetValue() == "0")
			return false;
		else
			return true;
		
	}
}

?>