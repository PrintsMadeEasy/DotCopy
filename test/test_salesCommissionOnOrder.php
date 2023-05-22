<?php
require_once("library/Boot_Session.php");

function formatPrice($ThePrice){
		$FormatedPrice = number_format($ThePrice, 2, '.', '');
		
		if(!preg_match("/^((\d+\.\d+)|0)$/", $FormatedPrice))
			throw new Exception("A price format is not correct for the Sales Commission.  The amount is: " . $ThePrice);
			
		return $FormatedPrice;
	
}

$dbCmd = new DbCmd();


$OrderID = 	581521;

		WebUtil::EnsureDigit($OrderID);
	

		
		// At 40% discount for an order... the sales rep will get nothing.
		// 40% is set as the CAP right now... At 20% discount the sales person would get 1/2 of their normal commission, etc.
		$DiscountPercentCap = 40;
	/*
		// Make sure we are not trying to call this method twice on the same order.
		$dbCmd->Query("SELECT COUNT(*) FROM salescommissions WHERE OrderID=$OrderID");
		if($dbCmd->GetValue() > 0)
			exit("The method CreateSalesRepRecordsFromOrder can not be called twice for the same order.");
*/		
		// Find out if there is a Sales Rep Associated with the User That Placed the order.
		$dbCmd->Query("SELECT UserID FROM orders WHERE ID=$OrderID");
		if($dbCmd->GetNumRows() == 0)
			exit("The order ID $OrderID does not exist in the method call CreateSalesRepRecordsFromOrder");
		$CustomerID = $dbCmd->GetValue();
		
		$dbCmd->Query("SELECT SalesRep, UNIX_TIMESTAMP(SalesRepExpiration) as ExpireDate FROM users WHERE ID=$CustomerID");
		$CustomerRow = $dbCmd->GetRow();
/*
		// If there is no sales rep, then don't record anything
		if($CustomerRow["SalesRep"] == 0)
			return;
*/
		// A NULL expiration date in the DB means that it never expires.
		if(empty($CustomerRow["ExpireDate"]))
			$Expired = false;
		else{
			if($CustomerRow["ExpireDate"] < time())
				$Expired = true;
			else
				$Expired = false;
		}


		$SalesRepObj = new SalesRep($dbCmd);
		$SalesRepObj->LoadSalesRep($CustomerRow["SalesRep"]);

		// If we have disabled a Sales Rep for some reason... then we do not want to record any Sales Commissions... for anyone in the chain.
		if($SalesRepObj->CheckIfAccountDisabled())
			return;		
		
		$OrderSubtotal = Order::GetTotalFromOrder($dbCmd, $OrderID, "customersubtotal");
		$OrderDiscount = Order::GetTotalFromOrder($dbCmd, $OrderID, "customerdiscount");
		
		
	
		
		// Find out the total of Product Options that don't apply for sales reps.
		// Also, anytime a Project Total exceed a certain Limit... take that off of the Order Total.  
		$dbCmd->Query("SELECT ID FROM projectsordered WHERE OrderID=" . $OrderID);
		while($thisProjectID = $dbCmd->GetValue())
			$projectIDarr[] = $thisProjectID ;

		foreach($projectIDarr as $thisProjectID){

			$projectOrderedObj = ProjectOrdered::getObjectByProjectID($dbCmd, $thisProjectID);

			$projectSubtotal = $projectOrderedObj->getCustomerSubTotal() - round($projectOrderedObj->getCustomerSubTotal() * $projectOrderedObj->getCustomerDiscount(), 2);
			
			$subtractOptionsDontApply = $projectOrderedObj->getAmountOfSubtotalThatIsDiscountExempt();
			
			// Subtract the Options that aren't able to be commissioned.
			$OrderSubtotal -= $subtractOptionsDontApply;
			
			// After subtracting the options that commissions can't be earned on... find out if we are still over the MaxProject Limit... in which case don't let it exceed that amount.
			if(($projectSubtotal - $subtractOptionsDontApply) > 200)
				$OrderSubtotal -= ($projectSubtotal - $subtractOptionsDontApply) - 200;
		}

		
		// Make sure that the Discount doesn't exceed the order total.
		// If it does, then just make the discount 100%
		if($OrderDiscount > $OrderSubtotal)
			$OrderDiscount = $OrderSubtotal;
		
		
		$DiscountPercent = round($OrderDiscount / $OrderSubtotal * 100, 2);
	
		$PercentageOfNormalCommission = 0;
		if($DiscountPercent < $DiscountPercentCap)
			$PercentageOfNormalCommission = ($DiscountPercentCap - $DiscountPercent) / $DiscountPercentCap;
			
		$salesCommissionObj = new SalesCommissions($dbCmd, 1);
		
		// Get a Record of all Sales Reps and their percentages that are locked in.
		$SalesRepChainArr = $salesCommissionObj->GetLockedPercentageHeirarchy($CustomerID);

		foreach($SalesRepChainArr as $ThisSalesRepID => $SalesRepPercentage){
		
			$SalesRepObj->LoadSalesRep($ThisSalesRepID);

			// Before we record the Sales Commission... we need to figure out if we should put their account on Payment Suspension
			// This could happen if their Address has not been verified within a certain time period... or if they have made a certain amount of money and we haven't received the W9 yet
			//$SalesRepObj->PossiblySuspendPayments();
			
			
			$CommissionPercentAfterDiscount = round($SalesRepPercentage * $PercentageOfNormalCommission, 2);
			
			
			$commssionsEarned = formatPrice($CommissionPercentAfterDiscount / 100 * ($OrderSubtotal - $OrderDiscount));
			
			// Find out if we are supposed to pay a fixed amount for new customers.
			// This is not heirarchial though.  Only apply this to the Sales Rep who is associated directly to the order.
			// A fixed amount for New Customers overrides any commission percentage.  The commission percentage would only be executed on future orders.
			$newCustomerCommissionAmnt = $SalesRepObj->getNewCustomerCommission();
			if($ThisSalesRepID == $CustomerRow["SalesRep"] && !empty($newCustomerCommissionAmnt)){
	print "<h1>Error *".$SalesRepObj->getNewCustomerCommission()."*</h1>";		
				// Now make sure there are no sales Rep Records for this Sales Rep on any order ID's (belonging to the Same Customer) before this order ID.
				$dbCmd->Query("SELECT COUNT(*) FROM orders WHERE UserID=$CustomerID AND ID < $OrderID");
				$precedingOrderCount = $dbCmd->GetValue();
				
				if($precedingOrderCount == 0)
					$commssionsEarned = formatPrice($SalesRepObj->getNewCustomerCommission());
			}
			

		
			print "Order: $OrderID <br>";
			print "Subtotal After Discount: ".formatPrice($OrderSubtotal - $OrderDiscount)." <br>";
			print "CommissionPercentAfterDiscount: ".$CommissionPercentAfterDiscount." <br>";
			print "CommissionEarned: ".$commssionsEarned." <br>";
				
			// Now figure out if we should pay this Sales Rep... The commission may have expired, or payments to their account could be suspended.
			// If it is expired then we don't need to worry about recording a Suspended status because they won't get paid anyway.
			if($Expired)
				$CommissionStatus = "E";
			else if($SalesRepObj->CheckIfPaymentsSuspended())
				$CommissionStatus = "S";
			else
				$CommissionStatus = "G";
				
			print "Commission Status: ".$CommissionStatus." <br>";

			
		}