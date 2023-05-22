<?

// I know this class is messy, it is remanents of an old function-based website.
// Reports should be coming out of respective modules to which the data belongs, such as ShoppingCart classes, etc.

class ProjectGroup {
	
	
	// Will return either how much a product weighs within a group, or what the quanity of the product, or how many projects there are of a particualr product within the group
	// If the product ID does not exisit within an order, then the weight or quantity returned is 0 
	// If there are no projects within an order then this will return zero for weight and quantity.
	// We want to calculate the weight of the order for the customer (which may be a bit higher than we reporting to the Shipping company...shhhhhh!.
	// ... that is why we offer ReportTypes for "CustomerWeight" and "CarrierWeight"
	static function GetDetailsOfProductInGroup(DbCmd $dbCmd, $reportType, $reference, $productID, $GroupType){
	
		$productID = intval($productID);
		$reference = DbCmd::EscapeSQL($reference);
		
		// Find out what table we will be using the the select Part of the query 
		if($GroupType == "shoppingcart")
			$SelectTable = "projectssession";
		else if($GroupType == "order")
			$SelectTable = "projectsordered";
		else if($GroupType == "shipment")
			$SelectTable = "projectsordered";
		else
			throw new Exception("Illegal Group Type in function ProjectGroup::GetDetailsOfProductInGroup: $GroupType");
	
	
		// No Construct the Select part of the query based upon our report type
		if($reportType == "quantity")
			$SelectPartOfQuery = "SELECT SUM($SelectTable.Quantity) ";
		else if($reportType == "projects")
			$SelectPartOfQuery = "SELECT DISTINCT $SelectTable.ID ";
		else if($reportType == "CustomerWeight" || $reportType == "CarrierWeight" || $reportType == "ExactWeight")
			$SelectPartOfQuery = "SELECT " . $SelectTable . ".ID ";
		else
			throw new Exception("Illegal ReportType in function call ProjectGroup::GetDetailsOfProductInGroup(), Section1");
	
		
		// Where we are collecting the info from
		if($GroupType == "order"){
			$QueryPart2 = " FROM projectsordered WHERE OrderID=$reference AND ProductID=$productID AND Status!='C'";
		}
		else if($GroupType == "shoppingcart"){
			$QueryPart2 = " FROM projectssession INNER JOIN shoppingcart ON projectssession.ID = shoppingcart.ProjectRecord ";
			$QueryPart2 .= "WHERE shoppingcart.SID='$reference' AND ProductID=$productID AND shoppingcart.DomainID=".Domain::getDomainIDfromURL();
		}
		else if($GroupType == "shipment"){
			$QueryPart2 = " FROM shipmentlink INNER JOIN projectsordered ON projectsordered.ID = shipmentlink.ProjectID ";
			$QueryPart2 .= " WHERE ShipmentID=$reference AND projectsordered.ProductID=$productID";
		}
		else{
			throw new Exception("Undefined group type in function ProjectGroup::GetDetailsOfProductInGroup");
		}
	
	
		$dbCmd->Query($SelectPartOfQuery . $QueryPart2);
		
	
		if($reportType == "quantity"){
			return $dbCmd->GetValue();
		}
		else if($reportType == "projects"){
			
			return $dbCmd->GetNumRows();
		}
		else if($reportType == "CustomerWeight" || $reportType == "CarrierWeight" || $reportType == "ExactWeight"){
		
			// If we are calculating the weight within a group...
			// Then we need to loop through every Project ID... create a project object and get each weight individually.
			
			$projectIDarr = array();
			while($projID = $dbCmd->GetValue())
				$projectIDarr[] = $projID;
			
			$totalWeight = 0;
		
			foreach($projectIDarr as $thisProjectID){
				$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, $SelectTable, $thisProjectID);
				$totalWeight += $projectObj->getWeight(true);
			}
			
			$returnWeight = 0;
			
			
			// Now figure out how to differeniate between what we tell our customers and the carriers about weight.
			if($reportType == "CustomerWeight"){
				$returnWeight = (int) ceil($totalWeight);
			}
			else if($reportType == "CarrierWeight"){
				$returnWeight = (int) round($totalWeight);
			}
			else if($reportType == "ExactWeight"){
				$returnWeight = round($totalWeight, 2);
			}
			else{
				throw new Exception("Illegal ReportType in function call ProjectGroup::GetDetailsOfProductInGroup(), Section2");
			}
			
		
			
	
			// In case a package weight 0.4 pounds we can't have the package weight be zero.
			// Comparing floats is never accurate... so we don't want to see if it is equal to Zero... safer to say less than 1.
			// Also we need to make sure there is some weight (before the round) ... or we could cause an empty order to have a weight of 1.
			if($reportType != "ExactWeight" && $returnWeight < 1 && $totalWeight > 0)
				$returnWeight = 1;
			
			
			// Now we are going to fudge the number so that the Customer isn't paying too much for shipping on small 1000 card orders (etc).
			// And this is also where we play with UPS's numbers.... I think that we can get away with 5 pound drops in the larger sizes... but obviously not on the small ones..
			if($reportType == "CustomerWeight"){
				
				// If a customer has a package between 3 and 8 pounds... drop them down 1 pound to save a dollar.
				// We are trying to charge a dollar more base price (for the little guys)... but don't want people ordering 1000 cards to pay $10 for ground shipping.
				if($returnWeight >= 3 && $returnWeight <= 7)
					$returnWeight -= 1;
				else if($returnWeight > 7 && $returnWeight <= 15)
					$returnWeight -= 2;
				else if($returnWeight > 15)
					$returnWeight -= 3;
			}
			else if($reportType == "CarrierWeight"){
				
				// For UPS I think we can get away with taking off more pounds.... the larger the shipment gets.
				
				if($returnWeight > 4 && $returnWeight <= 15)
					$returnWeight -= 1;
				if($returnWeight >= 16 && $returnWeight < 25)
					$returnWeight -= 2;
				else if($returnWeight >= 26 && $returnWeight < 35)
					$returnWeight -= 3;
				else if($returnWeight >= 36 && $returnWeight < 46)
					$returnWeight -= 4;
				else if($returnWeight >= 46)
					$returnWeight -= 5;
				
			}
			else if($reportType == "ExactWeight"){
			
				// Do nothing.
			}
			else{
				throw new Exception("Illegal ReportType in function call ProjectGroup::GetDetailsOfProductInGroup(), Section4");
			}
			
			
		
			return $returnWeight;
		}
		else{
			throw new Exception("Illegal ReportType in function call ProjectGroup::GetDetailsOfProductInGroup(), Section3");
		}
	
	}
	
	
	// Returns FALSE if there is even 1 project in the group which does not have "Mailing Service".
	// For example... 1 set of business cards... AND 5 sets of 4x6 mailed postcards.... would return FALSE.
	// ... if the set of business cards was removed from the Shopping Cart, then it would return TRUE.
	static function checkIfOnlyMailingJobsInGroup(DbCmd $dbCmd, $reference, $GroupType){
		
		$productIDarr =  ProjectGroup::GetProductIDlistFromGroup($dbCmd, $reference, $GroupType);
		
		foreach($productIDarr as $thisProdID){
			if(!Product::checkIfProductHasMailingServices($dbCmd, $thisProdID))
				return false;
		}
		
		return true;
	}
	
	
	// Returns a list of unique product ID's within a container... could be an order, shoppping cart, or a shipment
	static function GetProductIDlistFromGroup(DbCmd $dbCmd, $reference, $GroupType){
	
		$reference = DbCmd::EscapeSQL($reference);
		
		if($GroupType == "order"){
			$query = "SELECT DISTINCT ProductID FROM projectsordered WHERE OrderID='$reference' ";
			$query .= " AND Status!='C'";
		}
		else if($GroupType == "shoppingcart"){
			$query = "SELECT DISTINCT projectssession.ProductID FROM projectssession ";
			$query .= "INNER JOIN shoppingcart ON projectssession.ID = shoppingcart.ProjectRecord ";
			$query .= "WHERE shoppingcart.SID='$reference' AND shoppingcart.DomainID=".Domain::getDomainIDfromURL();
		}
		else if($GroupType == "shipment"){
			$query = "SELECT DISTINCT projectsordered.ProductID FROM projectsordered ";
			$query .= "INNER JOIN shipmentlink ON projectsordered.ID = shipmentlink.projectID ";
			$query .= "WHERE shipmentlink.ShipmentID='$reference'";
		}
		else{
			print "Undefined group type in function ProjectGroup::GetProductIDlistFromGroup";
			exit;
		}
		$dbCmd->Query($query);
	
		$retArr = array();
		while($thisProdID = $dbCmd->GetValue())
			$retArr[] = $thisProdID;
	
		return $retArr;
	}
	
	
	
	// Get the total from the shopping cart, or an order for which permanent discounts do not appply.
	// For not this is meant for people with a fixed permanent discount... they shouldn't get that discount on postage.
	static function GetTotalFrmGrpPermDiscDoesntApply(DbCmd $dbCmd, $reference, $viewType, $productLimiter = null){
		
		$productIDlimitSQL = "";
		if($productLimiter){
			WebUtil::EnsureDigit($productLimiter);
			$productIDlimitSQL = " AND ProductID='$productLimiter' ";
		}
		
		if($viewType == "ordered"){	
			WebUtil::EnsureDigit($reference);
			$query = "SELECT ID FROM projectsordered WHERE OrderID=$reference AND Status!='C'" . $productIDlimitSQL;
		}
		else if($viewType == "session"){
			$query = "SELECT DISTINCT ProjectRecord FROM shoppingcart WHERE SID='" . addslashes($reference) . "' AND DomainID=".Domain::getDomainIDfromURL() . $productIDlimitSQL;
		}
		else{
			print "Undefined group type in function ProjectGroup::GetTotalFrmGrpPermDiscDoesntApply";
			exit;
		}
		
		
		$dbCmd->Query($query);
		
		$projectIDArr = array();
	
		while($pid = $dbCmd->GetValue())
			$projectIDArr[] = $pid;
	
		$subtotalNotApply = 0;
		
		
		foreach($projectIDArr as $pid){
			$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, $viewType, $pid);
			
			$subtotalNotApply += $projectObj->getAmountOfSubtotalThatIsDiscountExempt();
		}
		
		return $subtotalNotApply;
		
	}

	

	
}


?>
