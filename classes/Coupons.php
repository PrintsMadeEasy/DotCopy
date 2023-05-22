<?

class Coupons {


	private $_dbCmd;
	private $_couponid; 
	private $_userid;
	
	private $_couponDataLoaded;
	
	private $_cp_Code;
	private $_cp_Active;
	private $_cp_Name;
	private $_cp_CategoryID;
	private $_cp_DateCreated;
	private $_cp_ActivationRequired;
	private $_cp_UsageLimit;
	private $_cp_WithinFirstOrders;
	private $_cp_MaxAmount;
	private $_cp_MaxAmountType;
	private $_cp_ShippingDiscount;
	private $_cp_MinimumSubtotal;
	private $_cp_MaximumSubtotal;
	private $_cp_ExpDate;
	private $_cp_DiscountPercent;
	private $_cp_ProofingAlert;
	private $_cp_ProductionNote;
	private $_cp_CreateUserID;
	private $_cp_Comments;
	private $_cp_SalesLink;
	private $_cp_ProjectMinQuantity;
	private $_cp_DomainID;
	private $_cp_ProductOptionsArr = array();  // The key to the array is a UpperCase Product Option Name.  The value is an array of 1 or more choice Names.
	private $_cp_NoDiscountOnOptions = array();  // The key to the array is a UpperCase Product Option Name.  The value is an array of 1 or more choice Names.
	
	
	private $_projectGroupName;
	private $_projectGroupID;

	private $_totalDollarsDiscounted;
	private $_subotalOfProjectsWhichCouponDoesApply;
	private $_subotalOfProjectsWhichCouponDoesNotApply;
	private $_amntOfSubotalNotDiscountedBecauseOfOptions;

	private $_creditCardNum;
	
	private $_user_CouponActivation;
	private $_user_CouponUses;
	private $_user_NumberOfOrders;
	
	private $_dbCouponRowHash = array();
	private $_productIDarr = array();
	private $_productBundleArr = array();
	
	private $_projectsDiscountsArr = array();
	private $_projectDataLoaded;
	
	private $_domainID;


	###-- Constructor --###
	// If you don't specify a Domain ID, then it will just use the oneDomain feature.
	function Coupons(DbCmd $dbCmd, $domainID = NULL){
		
		if(empty($domainID)){
			$domainID = Domain::oneDomain();
		}
		else{
			if(!Domain::checkIfDomainIDexists($domainID))
				throw new Exception("Error with coupon contructor domain.");
		}
		$this->_domainID = $domainID;
		
		$this->_dbCmd = $dbCmd;
		
		$this->_userid = "";
		$this->_couponid = "";
		$this->_projectGroupName = "";
		$this->_projectGroupID = "";
		$this->_creditCardNum = "";
		
		$this->_user_CouponActivation = "";
		$this->_user_CouponUses = "";
		$this->_user_NumberOfOrders = "";
		
		$this->_projectDataLoaded = false;
		$this->_totalDollarsDiscounted = null;
		$this->_subotalOfProjectsWhichCouponDoesApply = 0;
		$this->_subotalOfProjectsWhichCouponDoesNotApply = 0;
		$this->_amntOfSubotalNotDiscountedBecauseOfOptions = 0;
		
		$this->InitializeFields();
	}
	
	
	// Returns a List of Coupon Categories.
	// The Key of the Hash is the ID in the DB.  The value of the hash is the coupon category name.
	static function getCouponCategoriesList(DbCmd $dbCmd)
	{
		$retArr = array();
		
		$dbCmd->Query( "SELECT ID, Name FROM couponcategories WHERE DomainID=" . Domain::oneDomain() . " ORDER BY Name ASC" );
		
		while($row = $dbCmd->GetRow())
			$retArr[$row["ID"]] = $row[ "Name" ];
		
		return $retArr;
	}
	
	// Set up some default values (in case we are adding a new coupon)
	function InitializeFields(){
	
		$this->_couponDataLoaded = false;
		
		$this->_cp_Code = "";
		$this->_cp_Active = 1;
		$this->_cp_Name = "";
		$this->_cp_CategoryID = 0;
		$this->_cp_CreateUserID = 0;
		$this->_cp_ActivationRequired = 0;
		$this->_cp_UsageLimit = "";
		$this->_cp_WithinFirstOrders = "";
		$this->_cp_ExpDate = "";
		$this->_cp_DiscountPercent = 20;
		$this->_cp_MaxAmount = 0;
		$this->_cp_MinimumSubtotal = 0;
		$this->_cp_MaximumSubtotal = 0;
		$this->_cp_ProjectMinQuantity = 0;
		$this->_cp_ProjectMaxQuantity = 0;
		$this->_cp_MaxAmountType = "O";  // 'O' for "order level" by default.
		$this->_cp_ShippingDiscount = 0;
		$this->_cp_Comments = "";
		$this->_cp_ProofingAlert = "";
		$this->_cp_ProductionNote = "";
		$this->_cp_SalesLink = 0;
		$this->_cp_NoDiscountOnOptions = array();
	}
	
	// returns Zero if the coupon code and Domain ID combo do not match. 
	static function getCouponIdFromCouponCode($domainID, $couponCode){
		
		if(!Domain::checkIfDomainIDexists($domainID))
			throw new Exception("The Domain ID does not exist.");
			
		$couponCode = trim($couponCode);
		$couponCode = DbCmd::EscapeLikeQuery($couponCode);
			
		$dbCmd = new DbCmd();
		$dbCmd->Query("SELECT ID FROM coupons WHERE DomainID=$domainID AND Code LIKE '$couponCode'");
		$couponID = $dbCmd->GetValue();
		
		if(empty($couponID))
			$couponID = 0;
		
		return $couponID;
	}
	
	// The Discount percent of the coupon is dependent on the amount of subtotal and/or the type of products they are ordering
	// If the coupon has a "MaxAmount" then the discount percent may be reduced so that it doesn't go over that amount
	// For example let's say that the discount on the couplon is 100%, with a max amount of $5.00.  If somebody orders something for $10, then this function will return 50%.  If somebody orders something for $5 then this function returns 100%
	// Also the coupon may be restricted by certain products.  If the order/shopping cart has some projects which the coupon is not good for, then it will adjust the overall discount so that discount is properly distributed over the entire subtotal
	function GetCouponDiscountPercentForSubtotal(){

		$theSubtotal = $this->GetSubtotalFromGroup();
		
		// The value from Widgets::GetDiscountPercentFormated may have some unecessary digits in it (just to be scientifically correct)
		// We can give them a clean discount (coming directly from the coupon) if there is no Max Amount on the coupon and it is not restricting to a certain product in the group
		if($this->_subotalOfProjectsWhichCouponDoesNotApply == 0 && $this->_amntOfSubotalNotDiscountedBecauseOfOptions == 0 && ($this->_cp_MaxAmount == 0 || $this->_subotalOfProjectsWhichCouponDoesApply <= $this->_cp_MaxAmount))
			return $this->_cp_DiscountPercent;	
		else
			return Widgets::GetDiscountPercentFormated($theSubtotal, $this->_totalDollarsDiscounted);
		
	}
	
	// This will fail if the information regarding the group has not been set... such as the "shoppingcart" and the Users's session ID.
	function GetSubtotalFromGroup(){
	
		if(!$this->_projectDataLoaded)
			$this->_SetDiscountPercentagesForProjects();

		return ($this->_subotalOfProjectsWhichCouponDoesApply + $this->_subotalOfProjectsWhichCouponDoesNotApply);
	}
	
	
	// All coupon and user data must be set before calling this method
	// Pass in a project ID (belonging to the project group, set prior)
	// It will return a percent to discount that project for (up to 3 decimal places)... like 23.567%
	function GetDiscountPercentForProject($projectID){
	
		if(!$this->_projectDataLoaded)
			$this->_SetDiscountPercentagesForProjects();
		
		if(!isset($this->_projectsDiscountsArr[$projectID]))
			throw new Exception("Error in method GetDiscountPercentForProject.  The project ID does not exist.");
		
		return $this->_projectsDiscountsArr[$projectID];
		

	}
	
	
	// Discount is at a project level
	// this method will figure out the percentage that each project should be given
	// It takes into conideration the "MaxAmount" of the coupon too
	// Just sets member variables for the ojbect, does not return anything.
	function _SetDiscountPercentagesForProjects(){
	
		$this->_EnsureCouponDataIsLoaded();
		$this->_EnsureCouponDataIsSetWithUser();
		
		
		if($this->_projectGroupName == "shoppingcart"){
			$viewType = "projectssession";
			$tablename = "projectssession";
			
			$this->_dbCmd->Query("SELECT ProjectRecord FROM shoppingcart WHERE SID='" . $this->_projectGroupID . "' AND DomainID=".$this->_domainID);
		}
		else if($this->_projectGroupName == "order"){
			$viewType = "order";
			$tablename = "projectsordered";
			
			$this->_dbCmd->Query("SELECT ID FROM projectsordered WHERE OrderID=" . $this->_projectGroupID . " AND DomainID=" . $this->_domainID);
		}
		else
			throw new Exception("Illegal project group name in coupon function.");
		
		// Put a list of Project IDs from our order or Shopping Cart group into an array
		$projectIDlist = array();
		while($pid = $this->_dbCmd->GetValue())
			$projectIDlist[] = $pid;
		
		$projectsThatCouponApplyToArr = array();
		$projectsThatCouponDoNotApplyToArr = array();
		
		// Now create two separate lists based upon Product ID
		// One which the coupons are good for, the other which the coupon will not apply to.
		if(empty($this->_productIDarr)){
			$projectsThatCouponApplyToArr = $projectIDlist;
		}
		else{
			foreach($projectIDlist as $thisProjectID){
			
				$productID = ProjectBase::GetProductIDFromProjectRecord($this->_dbCmd, $thisProjectID, $viewType);
		
				if(in_array($productID, $this->_productIDarr))
					$projectsThatCouponApplyToArr[] = $thisProjectID;
				else
					$projectsThatCouponDoNotApplyToArr[] = $thisProjectID;
			}
		}
		
		
		// Find out if there are any maximum or minimum quantity requirements on Projects
		// If we find any quantities are out of range for the projects (that coupons apply to)... 
		// ... then take it out of the "applies to" array and move it over to the "does not apply" array.
		if($this->_cp_ProjectMinQuantity > 0 || $this->_cp_ProjectMaxQuantity > 0){
		
			// Filter out the temp array as we do our quantity checking.
			$temp_appliesToArr = $projectsThatCouponApplyToArr;
		
			foreach($projectsThatCouponApplyToArr as $thisProjectID){
			
				$this->_dbCmd->Query("SELECT Quantity FROM " . $tablename . " WHERE ID=" . $thisProjectID);
				$thisProjectQuantity = $this->_dbCmd->GetValue();
				
				if(($thisProjectQuantity < $this->_cp_ProjectMinQuantity) || ($this->_cp_ProjectMaxQuantity > 0 && $thisProjectQuantity > $this->_cp_ProjectMaxQuantity)){
				
					// Add to the array that the coupon "does not apply to".
					$projectsThatCouponDoNotApplyToArr[] = $thisProjectID;
					
					// Remove from the array that the coupon "does apply to".
					foreach($temp_appliesToArr as $thisKey => $thisValue){
						if($thisProjectID == $thisValue){
							unset($temp_appliesToArr[$thisKey]);
							break;
						}
					}
				}
			}
		
			// Assign the temp array back into its source.
			$projectsThatCouponApplyToArr = array();
			$projectsThatCouponApplyToArr = $temp_appliesToArr;
		}
		



		// Find out if there are any Product Option requirements for the coupon.
		// If we find any Projects that don't have matching Options to the coupon requirements... 
		// ... then take it out of the "applies to" array and move it over to the "does not apply" array.
		$couponProductOptionsArr = $this->GetProductOptionsArr(true);
		if(!empty($couponProductOptionsArr)){
		
			// Filter out the temp array as we do our quantity checking.
			$temp_appliesToArr = $projectsThatCouponApplyToArr;
		
			foreach($projectsThatCouponApplyToArr as $thisProjectID){
			
				$projectObj = ProjectBase::getProjectObjectByViewType($this->_dbCmd, $tablename, $thisProjectID);
				$projectOptionsArr = $projectObj->getOptionsAndSelections();
				
				foreach($couponProductOptionsArr as $couponOptionName => $couponOptionsChoicesArr){

					$optionChoiceFound = false;
					
					// go through all of the Option Name/Choice selection for our projects.
					// First find out if we have a matching Option Name.  If so see if the Choice selected in the Project exists within the List of available choices in the coupon.
					foreach($projectOptionsArr as $thisProjectOptionName => $thisProjectOptionChoice){
						
						if(strtoupper($thisProjectOptionName) == $couponOptionName){
							
							foreach($couponOptionsChoicesArr as $thisCouponOptionChoice){
								if(!(strpos(strtoupper($thisProjectOptionChoice), $thisCouponOptionChoice) === false)){
									$optionChoiceFound = true;
									break;
								}
							}
						}
					}
					

					if(!$optionChoiceFound){

						// Add to the array that the coupon "does not apply to".
						$projectsThatCouponDoNotApplyToArr[] = $thisProjectID;

						// Remove from the array that the coupon "does apply to".
						foreach($temp_appliesToArr as $thisKey => $thisValue){
							if($thisProjectID == $thisValue){
								unset($temp_appliesToArr[$thisKey]);
								break;
							}
						}
					}
				}
			}
		
			// Assign the temp array back into its source.
			$projectsThatCouponApplyToArr = array();
			$projectsThatCouponApplyToArr = $temp_appliesToArr;
		}
		



		
		// Now we want to get the subtotals of our 2 different project lists
		$this->_subotalOfProjectsWhichCouponDoesApply = 0;
		$this->_subotalOfProjectsWhichCouponDoesNotApply  = 0;
		$this->_amntOfSubotalNotDiscountedBecauseOfOptions = 0;

		// Build a Dynamic OR clause for SQL that will allow us to efficiently get the sum in a Single Query
		$SQLorClause_DoesApply = "";
		$SQLorClause_DoesNotApply = "";

		foreach($projectsThatCouponApplyToArr as $thisProjectID)
			$SQLorClause_DoesApply .= "ID=$thisProjectID OR ";
		foreach($projectsThatCouponDoNotApplyToArr as $thisProjectID)
			$SQLorClause_DoesNotApply .= "ID=$thisProjectID OR ";

		if(!empty($SQLorClause_DoesApply)){
			$SQLorClause_DoesApply = substr($SQLorClause_DoesApply, 0, -3);
			$this->_dbCmd->Query("SELECT SUM(CustomerSubtotal) AS CustSub 
						FROM $tablename WHERE ($SQLorClause_DoesApply) AND DomainID=" . $this->_domainID);
			$this->_subotalOfProjectsWhichCouponDoesApply = $this->_dbCmd->GetValue();
		}
		if(!empty($SQLorClause_DoesNotApply)){
			$SQLorClause_DoesNotApply = substr($SQLorClause_DoesNotApply, 0, -3);
			$this->_dbCmd->Query("SELECT SUM(CustomerSubtotal) AS CustSub 
						FROM $tablename WHERE ($SQLorClause_DoesNotApply) AND DomainID=" . $this->_domainID);
			$this->_subotalOfProjectsWhichCouponDoesNotApply  = $this->_dbCmd->GetValue();
		}
		

		// Now find out how much money from the subotal is not allowed to be discounted because it matches Product Option Discount restrictions.
		foreach($projectsThatCouponApplyToArr as $thisProjectID)
			$this->_amntOfSubotalNotDiscountedBecauseOfOptions += $this->getAmountFromProjNotDiscountedFromOptions($thisProjectID);
		
		
			
		$this->_projectsDiscountsArr = array();
		$this->_totalDollarsDiscounted = 0;
		

		// Find out if the Max Amount of the coupon will require us to reduce the percentage of the coupon discount
		// We may have a MaxAmount per 'O'rder or per 'P'roject.
		if($this->_cp_MaxAmountType == "O"){
		
			$adjustedCouponDiscount = $this->_cp_DiscountPercent;
			
			// In case Product Options are limiting us to what we are able to discount on the order...
			// Figure out what the percentage of the overall order is... considering the discount can't apply to part of the order total.
			if($this->_amntOfSubotalNotDiscountedBecauseOfOptions > 0){
			
				$amountAbleToBeDiscounted = $this->_subotalOfProjectsWhichCouponDoesApply - $this->_amntOfSubotalNotDiscountedBecauseOfOptions;
				$discountedDollarAmount = $amountAbleToBeDiscounted * $adjustedCouponDiscount / 100;
				
				$adjustedCouponDiscount = round($discountedDollarAmount / $this->_subotalOfProjectsWhichCouponDoesApply, 6) * 100;
			}
			

			// If there is a "max discount" for the entire order... then figure out how the percentage should be equally
			// ... divided between all projects within the order to bring us to the Total Max Discount.
			if($this->_cp_MaxAmount <> 0 && $this->_cp_MaxAmount < ($this->_subotalOfProjectsWhichCouponDoesApply * $adjustedCouponDiscount / 100))
				$adjustedCouponDiscount = round(($this->_cp_MaxAmount / $this->_subotalOfProjectsWhichCouponDoesApply * 100), 5);
				
			
			$this->_totalDollarsDiscounted = round(($this->_subotalOfProjectsWhichCouponDoesApply * $adjustedCouponDiscount / 100), 2);
		
			
			// Now store percentage into each Project Record
			foreach($projectsThatCouponApplyToArr as $thisProjectID)
				$this->_projectsDiscountsArr["$thisProjectID"] = $adjustedCouponDiscount;
			
		
		}
		else if($this->_cp_MaxAmountType == "P" || $this->_cp_MaxAmountType == "T"){
		
			// Project Level and Quantity Level coupons are pretty much the same thing.  The only difference is how the Max Amount affects the discount.
		
		
			foreach($projectsThatCouponApplyToArr as $thisProjectID){

				$this->_dbCmd->Query("SELECT CustomerSubtotal, Quantity FROM $tablename WHERE ID=$thisProjectID");
				$projRow = $this->_dbCmd->GetRow();
				$projSubtotal = $projRow["CustomerSubtotal"];
				$projQuantity = $projRow["Quantity"];


				$adjustedCouponDiscount = $this->_cp_DiscountPercent;


				// Basically it is the same thing as the Options Limitations at Project Level.
				// The only really difference is how the $this->_cp_MaxAmount affects the discount.
				if($this->_amntOfSubotalNotDiscountedBecauseOfOptions > 0){

					$amountAbleToBeDiscounted = $projSubtotal - $this->getAmountFromProjNotDiscountedFromOptions($thisProjectID);
					$discountedDollarAmount = $amountAbleToBeDiscounted * $adjustedCouponDiscount / 100;

					$adjustedCouponDiscount = round($discountedDollarAmount / $projSubtotal, 6) * 100;
				}


				// "T" is for quantity level and "P" is for Project Level.
				if($this->_cp_MaxAmountType == "T"){
					if($this->_cp_MaxAmount <> 0 && ($this->_cp_MaxAmount * $projQuantity) < ($projSubtotal * $adjustedCouponDiscount / 100))
						$adjustedCouponDiscount = round((($this->_cp_MaxAmount * $projQuantity) / $projSubtotal * 100), 5);
				}
				else if($this->_cp_MaxAmountType == "P"){
					if($this->_cp_MaxAmount <> 0 && $this->_cp_MaxAmount < ($projSubtotal * $adjustedCouponDiscount / 100))
						$adjustedCouponDiscount = round(($this->_cp_MaxAmount / $projSubtotal * 100), 5);
				}
				else{
					throw new Exception("Error in method _SetDiscountPercentagesForProjects... The max amount type is undefined in the conditional statement.");
				}
					

				$this->_projectsDiscountsArr["$thisProjectID"] = $adjustedCouponDiscount;

				$this->_totalDollarsDiscounted += round(($projSubtotal * $adjustedCouponDiscount / 100), 2);
			}
		}
		else{
			throw new Exception("Error in method _SetDiscountPercentagesForProjects... The max amount type is undefined.");
		}
		
		
		// For all of the Projects that the coupon does not apply to... set the discount to Zero.
		foreach($projectsThatCouponDoNotApplyToArr as $thisProjectID)
			$this->_projectsDiscountsArr["$thisProjectID"] = 0;		
		
		
		
		$this->_projectDataLoaded = true;
	
	}
	
	
	// Returns an amount for which can not be discounted, because of Product Options that are selected. 
	// Coupons choose individual Option/Choice combinations for which discounts do not apply.
	// A Product may also have a "Coupon Exemption" set on one of the Options. (in this case all choices are not discounted).
	// For example, If a coupon forbids a project from discounting "Postage"...then this method will turn the total of "Postage" for the projec.
	// If there are no Product Option restrictions (for discounts) on this coupon, then the method will return 0.
	function getAmountFromProjNotDiscountedFromOptions($projectID){
		
		if(empty($this->_projectGroupID) || empty($this->_projectGroupName))
			throw new Exception("The method getAmountFromProjNotDiscountedOpt must be called before calling _getDistinctProductIDsInGroup.");
		
		$this->_EnsureCouponDataIsSetWithUser();
		
		if($this->_projectGroupName == "shoppingcart")
			$viewType = "projectssession";
		else if($this->_projectGroupName == "order")
			$viewType = "order";
		else
			throw new Exception("Illegal project group name in coupon function getAmountFromProjNotDiscountedOpt.");
			
	
		$projectObj = ProjectBase::getProjectObjectByViewType($this->_dbCmd, $viewType, $projectID);
		
		$productObj = $projectObj->getProductObj();
		
		$projectOptionSelectedArr = $projectObj->getOptionsAndSelections();
		
		$couponProductNoDiscOptionsArr = $this->GetNoDiscountOnProductOptionsArr(true);

		
		$retAmount = 0;
		
		// Loop through all of the Product Options (and selections) for this Project and find out it it collides with one of the Options/Choice combinations specified on the coupon
		foreach($projectOptionSelectedArr as $thisOptionName => $thisChoiceSelected){
		
			// In case there is an Option Controller set which forbids coupon discounts.
			// Then add to our Return Amount and skip the rest of this loop.
			// We don't want to add double to the "non discounted amount" if there is also a matching Coupon Option/choice restriction.
			$productOptionObject = $productObj->getOptionObject($thisOptionName);
					
			if($productOptionObject->couponDiscountExempt){
				$retAmount += $projectObj->getCustomerProductOptionTotal($thisOptionName);
				continue;
			}
			
			$thisOptionNameUpperCase = strtoupper($thisOptionName);
			$thisChoiceSelected = strtoupper($thisChoiceSelected);
		
			if(!array_key_exists($thisOptionNameUpperCase, $couponProductNoDiscOptionsArr))
				continue;
	
			$choicesInCouponArr = $couponProductNoDiscOptionsArr[$thisOptionNameUpperCase];

			if(!in_array($thisChoiceSelected, $choicesInCouponArr))
				continue;
			
			$retAmount += $projectObj->getCustomerProductOptionTotal($thisOptionName);
		}
		
		return $retAmount;
	}
	
	

	function _EnsureCouponDataIsSetWithUser(){
		if($this->_couponid == "")
			$this->CouponError("Coupon ID must be set first before calling function.");
		if($this->_projectGroupName == "")
			$this->CouponError("A Group must be set before calling _EnsureCouponDataIsSetWithUser.");
		if($this->_userid == "")
			$this->CouponError("A User ID must be set before calling this function.");
	}
	function _EnsureCouponIDisSet(){
		if($this->_couponid == "")
			$this->CouponError("Coupon ID must be set first before calling function.");
	}
	
	function _EnsureCouponDataIsLoaded(){
		if(!$this->_couponDataLoaded)
			$this->CouponError("The coupon data must be loaded before calling function.");
	}
	
	// Will look for a matching category name... if it can't find one it will fail critically.  So make sure you know its good.
	function GetCategoryIDbyCategoryName($catName){
	
		$this->_dbCmd->Query("SELECT ID FROM couponcategories WHERE Name LIKE '".DbCmd::EscapeLikeQuery($catName)."' AND DomainID=" . $this->_domainID);
		if($this->_dbCmd->GetNumRows() == 0)
			throw new Exception("The coupon category being requested was not found: $catName.");
		return $this->_dbCmd->GetValue();
	}
	
	function CheckIfCategoryNameExists($catName){
	
		$this->_dbCmd->Query("SELECT COUNT(*) FROM couponcategories WHERE Name LIKE '".DbCmd::EscapeLikeQuery($catName)."' AND DomainID=" . $this->_domainID);
		
		if($this->_dbCmd->GetValue() == 0)
			return false;
		else
			return true;
	}
	
	// Will fail critically if the coupon code does not exist, so make sure you check it is valid first.
	// Also you must make sure that the coupon does require activation, othewise it will fail
	// It is harmless to call this method for a user that already has the coupon activated.
	function ActivateCouponForUser($couponCode, $UserID){
		
		
		
		$this->LoadCouponByCode($couponCode);
		
		$domainIDofUser = UserControl::getDomainIDofUser($UserID);
		
		if($domainIDofUser != $this->_cp_DomainID)
			throw new Exception("Error in method ActivateCouponForUser. The Domains don't match.");
		
		$this->SetUser($UserID);
		
		if(!$this->_cp_ActivationRequired)
			throw new Exception("You are trying to activate the coupon $couponCode for UserID: $UserID and the coupon does not require activation.");
	
		$this->_LoadUserDataForCoupon();
		
		// If it is already activated then don't do anything.
		if($this->_user_CouponActivation)
			return;
	
		$DBinsertArr["CouponID"] = $this->_couponid;
		$DBinsertArr["UserID"] = $UserID;
		$this->_dbCmd->InsertQuery("couponactivation", $DBinsertArr);
	
	}
	
	
	// Returns an array of integers.
	// Represents Coupon ID's in the Given Category ID
	// By default this method will not return Coupon ID's that are not active
	function GetCouponIDsInCategory($catID, $MustBeActive=true){
		
		if($MustBeActive)
			$ActiveClause = " AND IsActive=1";
		else
			$ActiveClause = "";

	
		$retArr = array();
		$this->_dbCmd->Query("SELECT ID FROM coupons WHERE DomainID=" . $this->_domainID . " AND CategoryID=$catID" . $ActiveClause);
		while($thisCouponID = $this->_dbCmd->GetValue() )
			$retArr[] = $thisCouponID;
			
		return $retArr;
	}
	
	
	
	// Will return an empty string if the coupon is OK to use for the customer... this might only work before the order is placed.
	// If it is not OK to use.  The return will be the error string stating why.
	// Pass in a Credit Card if you want the "UsageCheck" to include other accounts having a matching credit card number
	// ... this could happen if a person is trying to reuse a free coupon by signing up for different accounts
	function CheckIfCouponIsOKtoUse($CouponCode, $UserID, $projectGroupName, $projectGroupID, $CredCardNum = ""){

		$RetMessage = "";
		
		$CouponCode = trim($CouponCode);
		$CouponCode = preg_replace("/\s/", "", $CouponCode);
		
	
		// There was a mistake on a promotional mailer... just fixing it here.
		$CouponCode = preg_replace("/^(0|o)6(0|o)866(0|o)M$/i", "C2073M", $CouponCode);


		if(!$this->CheckIfCouponFormatIsOK($CouponCode)){
			$RetMessage = "The coupon has an improper format. Use only Letters, Numbers, and Hypens.";
			return $RetMessage;
		}


		if(!$this->CheckIfCouponCodeExists($CouponCode)){
			$RetMessage = "Sorry, the code is invalid.";
			return $RetMessage;
		}

		$this->SetUser($UserID);
		$this->SetProjectGroupForCoupon($projectGroupName, $projectGroupID);
		$this->SetCreditCardNum($CredCardNum);
		$this->LoadCouponByCode($CouponCode);
		
		$this->_SetDiscountPercentagesForProjects();

		$this->_LoadUserDataForCoupon();
		$this->_LoadCouponData();
		
		// Convert null values into numbers that are so far fetched that they will work with the equations below
		if($this->_cp_UsageLimit == "")
			$this->_cp_UsageLimit = 999999999;
		if($this->_cp_WithinFirstOrders == "")
			$this->_cp_WithinFirstOrders = 999999999;
		if($this->_cp_ExpDate == "")
			$this->_cp_ExpDate = mktime(1, 1, 1, 1, 1, 2036);
		
		
		

		if(!$this->_cp_Active)
			$RetMessage = "Sorry, this coupon is not available anymore.";
		else if($this->_user_NumberOfOrders >= $this->_cp_WithinFirstOrders)
			$RetMessage = "Sorry, this coupon can only be used " . (($this->_cp_WithinFirstOrders == 1) ? (" on your first order.") : ("within your first " . $this->_cp_WithinFirstOrders . " orders."));
		else if($this->_user_CouponUses >= $this->_cp_UsageLimit)
			$RetMessage = "Sorry, this coupon has exhausted its resources.";
		else if(time() >= $this->_cp_ExpDate)
			$RetMessage = "Sorry, this coupon is expired.";
		else if($this->_cp_ActivationRequired && !$this->_user_CouponActivation)
			$RetMessage = "This coupon has not been activated for your account.";
		else if($this->_CheckForLargerPermanentDiscountWithUser())
			$RetMessage = "Your permanent discount is greater than the coupon.";
		else if(!$this->_checkIfCouponIsOKforOrder())
			$RetMessage = "This coupon is not valid for any of the projects in your shopping cart.";
		else if(!$this->_checkIfCouponMeetsBundleRequirements())
			$RetMessage = "Additional product(s) in your Shopping Cart are required to use this coupon.";
		else if($this->GetSubtotalFromGroup() < $this->_cp_MinimumSubtotal)
			$RetMessage = 'A minimum subtotal of $' . $this->_cp_MinimumSubtotal . ' is required before this coupon may be used.';
		else if($this->_cp_MaximumSubtotal > 0 && $this->GetSubtotalFromGroup() > $this->_cp_MaximumSubtotal)
			$RetMessage = 'There is a maximum subtotal limit of $' . $this->_cp_MaximumSubtotal . ' with this coupon.';
		else if($this->_cp_SalesLink){
			// Find out if this customer has a SalesRep other than the Sales Rep associated with this coupon
			$this->_dbCmd->Query("SELECT COUNT(*) FROM users WHERE ID=" . $this->_userid . " AND SalesRep !=0 AND SalesRep !=" . $this->_cp_SalesLink);
			if($this->_dbCmd->GetValue() != 0)
				$RetMessage = "Sorry, this coupon conflicts with another coupon you have used before.";
				
			// Figure out if they already have a banner tracking cookie set from the Main Website.  
			// This would mean that the parent company pays Google for a click...
			// ... then that customer is about the checkout and they see a coupon code box... so they type in "example.com coupon"
			$possibleMainCompanyReferral = WebUtil::GetSessionVar("ReferralSession", WebUtil::GetCookie("ReferralCookie"));
			if(!empty($possibleMainCompanyReferral) && !preg_match("/^em\-/", $possibleMainCompanyReferral))
				$RetMessage = "Sorry, this coupon is expired.";
		}

		
		return $RetMessage;
	}
	
	function _checkIfCouponMeetsBundleRequirements(){
	
		$this->_EnsureCouponDataIsLoaded();
	
		// If there are no Bundle ProductID's then there are no restrictions
		if(empty($this->_productBundleArr))
			return true;
	
		$productIDsInGroupArr = $this->_getDistinctProductIDsInGroup();

		foreach($this->_productBundleArr as $thisBundleProductID){
		
			$bundleProductIDfound = false;
		
			// Now loop through all of the Product IDs in our group to make sure we have a product ID for it.
			foreach($productIDsInGroupArr as $thisProductIDfromGroup){
			
				$productObj = Product::getProductObj($this->_dbCmd, $thisProductIDfromGroup, false);
				
				$parentProductID = $productObj->getMainProductID();
			
				if($parentProductID == $thisBundleProductID){
					$bundleProductIDfound = true;
					break;
				}
			}
			
			if(!$bundleProductIDfound)
				return false;
		}

		
		return true;
	}
	

	// Returns a unique list of Product ID's in the shopping cart or order.
	function _getDistinctProductIDsInGroup(){

		if(empty($this->_projectGroupID) || empty($this->_projectGroupName))
			throw new Exception("The method SetProjectGroupForCoupon must be called before calling _getDistinctProductIDsInGroup.");
		

		if($this->_projectGroupName == "shoppingcart"){

			$this->_dbCmd->Query("SELECT DISTINCT projectssession.ProductID FROM projectssession INNER JOIN 
						shoppingcart ON projectssession.ID = shoppingcart.ProjectRecord 
						WHERE shoppingcart.SID='". $this->_projectGroupID . "' AND shoppingcart.DomainID=" . $this->_domainID);
		}
		else if($this->_projectGroupName == "order"){

			$this->_dbCmd->Query("SELECT DISTINCT projectsordered.ProductID FROM projectsordered INNER JOIN 
						orders ON orders.ID = projectsordered.OrderID 
						WHERE orders.ID=". $this->_projectGroupID . " AND DomainID=" . $this->_domainID);
		}
		else
			throw new Exception("Illegal group name in _getDistinctProductIDsInGroup()");
			

		$retArr = array();
		
		while($prodID = $this->_dbCmd->GetValue())
			$retArr[] = $prodID;
		
		if(empty($retArr))
			throw new Exception("Problem in method _getDistinctProductIDsInGroup... no product ID's were found.");
		
		return $retArr;
	}
	
	
	function _checkIfCouponIsOKforOrder(){
	
		if(!$this->_projectDataLoaded)
			$this->_SetDiscountPercentagesForProjects();
			
		// If there is no subotal for which the coupon applies to, then the coupon is no good.
		if($this->_subotalOfProjectsWhichCouponDoesApply == 0)
			return false;
		else
			return true;
	}


	
	function SetUser($userID){
		$this->_userid = $userID;
	}
	
	// Pass in a group name and identifier
	// Like "shoppingcart" or "order"
	// The identifier is the Session ID for a shopping cart ID and the Order Number for orders
	// It will make sure group/ID combo is valid, or will fail critically on error
	function SetProjectGroupForCoupon($groupName, $groupIdentifer){
	
		if($groupName == "shoppingcart"){
			// Make sure there is no SQL injection with the group identifier
			if(!preg_match("/^(\d|\w){10,50}$/", $groupIdentifer))
				throw new Exception("Problem with the group identifier field");
			$this->_dbCmd->Query("SELECT COUNT(*) FROM shoppingcart WHERE SID='$groupIdentifer' AND DomainID=".$this->_domainID);
			if($this->_dbCmd->GetNumRows() == 0)
				throw new Exception("This coupon can not be validated because the shopping cart is empty.");
		}
		else if($groupName == "order"){
			// Make sure there is no SQL injection with the group identifier
			WebUtil::EnsureDigit($groupIdentifer);
			
			$this->_dbCmd->Query("SELECT COUNT(*) FROM projectsordered WHERE OrderID=$groupIdentifer AND DomainID=" . $this->_domainID);
			if($this->_dbCmd->GetNumRows() == 0)
				throw new Exception("This coupon can not be validated because the orders is empty.");
		}
		else
			throw new Exception("Illegal group name for SetProjectGroupForCoupon()");
			
		
		
		$this->_projectGroupName = $groupName;
		$this->_projectGroupID = $groupIdentifer;

	}
	function SetCreditCardNum($Num){
		
		if(!preg_match("/^\d*$/", $Num))
			$this->CouponError("The CreditCard Num does not have the correct format: $Num");
		
		$this->_creditCardNum = $Num;
	}
	
	
	//You should already check if the coupon code exists before trying to set the object here.
	function LoadCouponByCode($couponCode){

		// Get rid of spaces within the Coupon Name.
		$couponCode = preg_replace("/(\s|\n|\r)/", "", $couponCode);
	
		if(!$this->CheckIfCouponFormatIsOK($couponCode))
			$this->CouponError("The coupon code is not in proper format.");
			
		if(!$this->CheckIfCouponCodeExists($couponCode))
			$this->CouponError("Coupon code does not exist.");
		
		$this->_dbCmd->Query("SELECT ID FROM coupons WHERE Code like\"". DbCmd::EscapeSQL($couponCode) . "\" AND DomainID=" . $this->_domainID);
		
		$this->_couponid = $this->_dbCmd->GetValue();
		
		$this->_LoadCouponData();
	}
	
	
	function LoadCouponByID($couponID){

		if(!$this->CheckIfCouponIDExists($couponID))
			$this->CouponError("Coupon ID does not exist.");

		$this->_couponid = $couponID;
		
		$this->_LoadCouponData();
	}
	
	// Does not validate coupon, just checks if the code exists or not
	function CheckIfCouponCodeExists($codeName){
	
		// Get rid of spaces within the Coupon Name.
		$codeName = preg_replace("/(\s|\n|\r)/", "", $codeName);
	
		$this->_dbCmd->Query("SELECT count(*) FROM coupons WHERE Code LIKE \"". DbCmd::EscapeLikeQuery($codeName) . "\" AND DomainID=" . $this->_domainID);
		
		if($this->_dbCmd->GetValue() == 0)
			return false;
		else
			return true;
	
	}
	// Does not validate coupone just checks if the ID exists or not
	function CheckIfCouponIDExists($codeID){
	
		$this->_dbCmd->Query("SELECT count(*) FROM coupons WHERE ID=".intval($codeID)." AND DomainID=" . $this->_domainID);
		
		if($this->_dbCmd->GetValue() == 0)
			return false;
		else
			return true;
	
	}
	
	// Will return true of false if the coupon format is OK to use.
	function CheckIfCouponFormatIsOK($codeStr){
		
		if(strlen($codeStr) > 20)
			return false;
			
		if(strlen($codeStr) < 2)
			return false;
		
		// Letters, numbers, hyphens... must begin with a letter (which can not be an X)
		return preg_match('/^([A-Z]|[1-9])[\dA-Z\-]+$/i', $codeStr) && !preg_match('/^X/i', $codeStr);
	
	}
	

	
	// Save New Coupon to the database... it will exit on error if the coupon already exists... 
	// ... so make sure to check the code does no exist first.
	function SaveNewCouponInDB(){
	
		$this->_PopulateHashForDB();
		
		if($this->CheckIfCouponCodeExists($this->_cp_Code))
			$this->CouponError("The coupon already exists... you can not add it as a new coupon.");
		
		// Add the Date Created field to the DB
		$this->_dbCouponRowHash["DateCreated"] = time();
		
		// Only set the DomainID when the coupon is added. You can change this value during a coupon "update"
		$this->_dbCouponRowHash["DomainID"] = $this->_domainID;
		
		$NewCouponID = $this->_dbCmd->InsertQuery("coupons", $this->_dbCouponRowHash);
		
		// Now show that we have a coupon properly loaded in memory for this object
		$this->_couponid = $NewCouponID;
		$this->_couponDataLoaded = true;

	}
	// Must have loaded a coupon prior to calling this method.
	function UpdateCouponInfoInDB(){
	
		$this->_EnsureCouponDataIsLoaded();
		$this->_PopulateHashForDB();
		
		// Unset the DateCreated field....  the field could be sticking around from a previous Insert
		unset($this->_dbCouponRowHash["DateCreated"]);
		
		// We should not be alowed to change the coupon code name after it has been inserted.
		unset($this->_dbCouponRowHash["Code"]);
		
		$this->_dbCmd->UpdateQuery("coupons", $this->_dbCouponRowHash, "ID=" . $this->_couponid . " AND DomainID=" . $this->_domainID);
	}
	
	// Sets an internal Array which is passed into our Insert Query Method for Inserting or Updating 
	// Populates everything execpt for the ID, which is the primary key and auto-increment field
	function _PopulateHashForDB(){
	
		// Make sure that the following fields have been set prior to calling this method.
		$this->_CheckForEmptyProperty("_cp_Code");
		$this->_CheckForEmptyProperty("_cp_CategoryID");
		$this->_CheckForEmptyProperty("_cp_CreateUserID");
		
		if(!preg_match("/^\d+$/", $this->_cp_DiscountPercent))
			throw new Exception("The coupon discount percent must be numeric.");

		if($this->_cp_ProjectMaxQuantity > 0 && $this->_cp_ProjectMaxQuantity < $this->_cp_ProjectMinQuantity)
			throw new Exception("You can not set the Maximum Project Quantity to less than the minimum.");

		if($this->_cp_MaximumSubtotal > 0 && $this->_cp_MaximumSubtotal < $this->_cp_MinimumSubtotal)
			throw new Exception("You can not set the Maximum Subtotal to less than the minimum.");

		$this->_dbCouponRowHash["Code"] = $this->_cp_Code;
		$this->_dbCouponRowHash["IsActive"] = $this->_cp_Active;
		$this->_dbCouponRowHash["Name"] = $this->_cp_Name;
		$this->_dbCouponRowHash["CategoryID"] = $this->_cp_CategoryID;
		$this->_dbCouponRowHash["ProductIDs"] = $this->_getPipeDelimitedStringFromArr($this->_productIDarr);
		$this->_dbCouponRowHash["ProductBundleIDs"] = $this->_getPipeDelimitedStringFromArr($this->_productBundleArr);
		$this->_dbCouponRowHash["CreateUserID"] = $this->_cp_CreateUserID;
		$this->_dbCouponRowHash["ActReq"] = $this->_cp_ActivationRequired;
		$this->_dbCouponRowHash["UsageLimit"] = $this->_cp_UsageLimit;
		$this->_dbCouponRowHash["WithinFirstOrders"] = $this->_cp_WithinFirstOrders;
		$this->_dbCouponRowHash["ExpireDate"] = $this->_cp_ExpDate ? date("YmdHis", $this->_cp_ExpDate) : NULL; // Mysql Timestamp
		$this->_dbCouponRowHash["DiscountPercent"] = $this->_cp_DiscountPercent;
		$this->_dbCouponRowHash["MaxAmount"] = $this->_cp_MaxAmount;
		$this->_dbCouponRowHash["MaxAmountType"] = $this->_cp_MaxAmountType;
		$this->_dbCouponRowHash["ShippingDiscount"] = $this->_cp_ShippingDiscount;
		$this->_dbCouponRowHash["MinimumSubtotal"] = $this->_cp_MinimumSubtotal;
		$this->_dbCouponRowHash["MaximumSubtotal"] = $this->_cp_MaximumSubtotal;
		$this->_dbCouponRowHash["ProjectMinQuantity"] = $this->_cp_ProjectMinQuantity;
		$this->_dbCouponRowHash["ProjectMaxQuantity"] = $this->_cp_ProjectMaxQuantity;
		$this->_dbCouponRowHash["Comments"] = $this->_cp_Comments;
		$this->_dbCouponRowHash["ProofingAlert"] = $this->_cp_ProofingAlert;
		$this->_dbCouponRowHash["ProductionNote"] = $this->_cp_ProductionNote;
		$this->_dbCouponRowHash["SalesLink"] = $this->_cp_SalesLink;
		
		
		// If the coupon has Product Options associated to it... then convert them to a String to store in the DB.
		// We want to Separate Option/Choice combos by a Pipe Symbol.  We want to separate the Option Name from the Option Choice by a Carret sybmol ^
		$productOptionStr = "";
		foreach($this->_cp_ProductOptionsArr as $thisOptionName => $optionChoicesArr){
			foreach($optionChoicesArr as $thisOptionChoice){

				// Only Separate Product Options with a Pipe if it is not the first choice.
				if(!empty($productOptionStr))
					$productOptionStr .= "|";

				$productOptionStr .= $thisOptionName . "^" . $thisOptionChoice;
			}
		}
		$this->_dbCouponRowHash["ProductOptions"] = $productOptionStr;
		
		


		$noDiscountOnOptionStr = "";
		foreach($this->_cp_NoDiscountOnOptions as $thisOptionName => $optionChoicesArr){
			foreach($optionChoicesArr as $thisOptionChoice){

				// Only Separate Product Options with a Pipe if it is not the first choice.
				if(!empty($noDiscountOnOptionStr))
					$noDiscountOnOptionStr .= "|";

				$noDiscountOnOptionStr .= $thisOptionName . "^" . $thisOptionChoice;
			}
		}
		$this->_dbCouponRowHash["NoDiscountOnOptions"] = $noDiscountOnOptionStr;
		
		
	
	}
	
	
	// Set member variables for coupon usage for a particular customer... requires that the Coupon ID and User ID are set before calling this funciton
	function _LoadUserDataForCoupon(){
		if($this->_couponid == "" || $this->_userid == "")
			$this->CouponError("Funtion _LoadUserDataForCoupon() cannot be called unless a coupon ID AND a User ID is set first.");
			
		//Find out if there is a user activation for the user.. with the coupon
		$this->_dbCmd->Query("SELECT count(*) FROM couponactivation WHERE CouponID=" . $this->_couponid . " AND UserID=" . $this->_userid);
		
		if($this->_dbCmd->GetValue() == 0)
			$this->_user_CouponActivation = false;
		else
			$this->_user_CouponActivation = true;
			
		
		// If we are also searching on Credit Card Numbers... to make sure people are not cheating the system
		// Do a check if the string length is less than 5... because with "corporate billing" we were putting in a fake credit card number like "111" to get it through the validation process
		if(empty($this->_creditCardNum) || strlen($this->_creditCardNum) < 5)
			$CreditCardClause = " AND UserID=" . $this->_userid;
		else
			$CreditCardClause = " AND (UserID=" . $this->_userid ." OR CardNumber='" . $this->_creditCardNum . "')";
		
		//Find out how many times the coupon has been used by the customer
		$this->_dbCmd->Query("SELECT count(*) FROM orders WHERE CouponID=" . $this->_couponid . $CreditCardClause);
		$this->_user_CouponUses = $this->_dbCmd->GetValue();
		

		//Find out how many times the user has placed an order... and try to catch them signing up for multiple account
		$this->_dbCmd->Query("SELECT count(*) FROM orders WHERE DomainID=" . UserControl::getDomainIDofUser($this->_userid) . " " . $CreditCardClause);
		$this->_user_NumberOfOrders = $this->_dbCmd->GetValue();		
	}


	// Set member variables within this object... for all of the various data fields asociated with this coupon
	// Does not fetch any "user specific" information
	function _LoadCouponData(){
	
		$this->_EnsureCouponIDisSet();	
	
		$this->_dbCmd->Query("SELECT * FROM coupons WHERE ID=" . $this->_couponid . " AND DomainID=" . $this->_domainID );
		$row = $this->_dbCmd->GetRow();

		if(!isset($row["Name"]))
			$row["Name"] = "";
		if(!isset($row["UsageLimit"]))
			$row["UsageLimit"] = "";
		if(!isset($row["WithinFirstOrders"]))
			$row["WithinFirstOrders"] = "";
		if(!isset($row["DateCreated"]))
			$row["DateCreated"] = "";
		if(!isset($row["ExpireDate"]))
			$row["ExpireDate"] = "";



		$this->_cp_Code = $row["Code"];
		$this->_cp_Active = $row["IsActive"];
		$this->_cp_Name = $row["Name"];
		$this->_cp_CategoryID = $row["CategoryID"];
		$this->_cp_ActivationRequired = $row["ActReq"];
		$this->_cp_UsageLimit = $row["UsageLimit"];
		$this->_cp_WithinFirstOrders = $row["WithinFirstOrders"];
		$this->_cp_DiscountPercent = $row["DiscountPercent"];
		$this->_cp_ExpDate = $row["ExpireDate"];
		$this->_cp_DateCreated = $row["DateCreated"];
		$this->_cp_MaxAmount = $row["MaxAmount"];
		$this->_cp_MaxAmountType = $row["MaxAmountType"];
		$this->_cp_ShippingDiscount = $row["ShippingDiscount"];
		$this->_cp_ProofingAlert = $row["ProofingAlert"];
		$this->_cp_ProductionNote = $row["ProductionNote"];		
		$this->_cp_SalesLink = $row["SalesLink"];
		$this->_cp_MinimumSubtotal = $row["MinimumSubtotal"];
		$this->_cp_MaximumSubtotal = $row["MaximumSubtotal"];
		$this->_cp_ProjectMinQuantity = $row["ProjectMinQuantity"];
		$this->_cp_ProjectMaxQuantity = $row["ProjectMaxQuantity"];
		$this->_cp_CreateUserID = $row["CreateUserID"];
		$this->_cp_Comments = $row["Comments"];
		$this->_cp_DomainID = $row["DomainID"];

		$this->_productIDarr = $this->_getDigitArrayFromPipeDelimetedString($row["ProductIDs"]);
		$this->_productBundleArr = $this->_getDigitArrayFromPipeDelimetedString($row["ProductBundleIDs"]);
		
		
		
		
		$this->_cp_ProductOptionsArr = array();
		
		if($row["ProductOptions"] != ""){
		
			$tempOptionsArr = split("\|", $row["ProductOptions"]);
			foreach($tempOptionsArr as $thisOptionNameChoice){

				$optionPartsArr = split("\^", $thisOptionNameChoice);
				
				if(sizeof($optionPartsArr) != 2)
					throw new Exception("Error Loading Coupon Data.  The Product Option did not have 2 elements. (Option Name, Option Choice).");
				
				$this->AddProductOption($optionPartsArr[0], $optionPartsArr[1]);
			}
		}
		
		
		
		
		$this->_cp_NoDiscountOnOptions = array();
		
		if($row["NoDiscountOnOptions"] != ""){
		
			$tempOptionsArr = split("\|", $row["NoDiscountOnOptions"]);
			foreach($tempOptionsArr as $thisOptionNameChoice){

				$optionPartsArr = split("\^", $thisOptionNameChoice);
				
				if(sizeof($optionPartsArr) != 2)
					throw new Exception("Error Loading Coupon Data.  The No Discount for Product Options Option did not have 2 elements. (Option Name, Option Choice).");
				
				$this->AddNoDiscountForOption($optionPartsArr[0], $optionPartsArr[1]);
			}
		}
		
		
		
		
		//Convert Mysql Times to Unix timestamps .. if the date is not NULL
		$this->_dbCmd->Query("SELECT UNIX_TIMESTAMP(DateCreated) AS DateCreated, UNIX_TIMESTAMP(ExpireDate) AS ExpireDate  FROM coupons WHERE ID=" . $this->_couponid );
		$SecondRow = $this->_dbCmd->GetRow();
		$this->_cp_DateCreated = $SecondRow["DateCreated"];
		if(!empty($row["ExpireDate"]))
			$this->_cp_ExpDate = $SecondRow["ExpireDate"];
		else
			$this->_cp_ExpDate = mktime(0, 0, 0, 12, 32, 2036);   // Because of the Unix Timestamp limitation... make the date far in the future. I have already cashed out by this point. <-- Best comment ever !
			
		
		$this->_couponDataLoaded = true;

	}
	
	// Pass in a string of product IDs separated by pipe symbols
	// It will parse the results into an array that will get used internally for checking if a coupon is valid, saving, etc.
	// Pass in an empty string if there is now product ID restrictions for the coupon
	function _loadProductIDsByString($productIDstr){
		
		$this->_productIDarr = array();
		
		$productIDsplitArr = split("\|", $productIDstr);
		foreach($productIDsplitArr as $thisProdID){
			if(preg_match("/^\d+$/", $thisProdID))
				$this->_productIDarr[] = $thisProdID;
		}
	}
	
	// Pass in an string of numbers separated by pipe symbols. 
	// Will return an array of the numbers.... an empty array on a blank string.
	// Silently skips over any that isn't a digit in between pipe symbols.
	function _getDigitArrayFromPipeDelimetedString($str){
	
		$retArr = array();
		
		$digitSplitArr = split("\|", $str);
		foreach($digitSplitArr as $thisDigit){
			if(preg_match("/^\d+$/", $thisDigit))
				$retArr[] = $thisDigit;
		}
		
		return $retArr;
	}
	

	// Returns a string with | Pipe symbols separating each element

	function _getPipeDelimitedStringFromArr($xArr){
	
		$retStr = "";
		
		foreach($xArr as $thisX)
			$retStr .= $thisX . "|";

		return $retStr;
	}
	

	// Will return true if the permanent discount with the user is greater than the coupon itself
	function _CheckForLargerPermanentDiscountWithUser(){
		
		$this->_EnsureCouponDataIsSetWithUser();
		
		
		// Find out if the user belongs to a loyalty program, and if so... if there is a discount.
		if(!empty($this->_userid)){
			$userControlObj = new UserControl($this->_dbCmd);
			$userControlObj->LoadUserByID($this->_userid, false);
			
			if($userControlObj->getLoyaltyProgram() == "Y"){
				$loyaltyObj = new LoyaltyProgram($this->_domainID);
				
				if($loyaltyObj->getLoyaltyDiscountSubtotalPercentage() > $this->GetCouponDiscountPercentForSubtotal())
					return true;
			}
		}
		
		
		$this->_dbCmd->Query("SELECT AffiliateDiscount, UNIX_TIMESTAMP(AffiliateExpires) AS Expires FROM users WHERE ID=" . $this->_userid);

		// In case the user ID doesn't exist.
		if($this->_dbCmd->GetNumRows() == 0)
			return false;
		
		$Row = $this->_dbCmd->GetRow();
		
		if($Row["AffiliateDiscount"] == "")
			$Row["AffiliateDiscount"] = 0;
		
		// Find out if the permanent discount for the user has expired.
		if(time() > $Row["Expires"] )
			return false;
		
		//in the users table discount percent is stored in decimal form
		$Row["AffiliateDiscount"] = $Row["AffiliateDiscount"] * 100;
		
		if($Row["AffiliateDiscount"] > $this->GetCouponDiscountPercentForSubtotal())
			return true;
		else
			return false;
	}
	
	function CouponError($ErrorMessage){
		throw new Exception($ErrorMessage);
	}
	
	// Pass in a property name... (the private variable).  It will throw an error if it is empty
	function _CheckForEmptyProperty($PropertyName){
		
		$Str = "";
		if(!eval('$Str = $this->' . $PropertyName . '; return true;'))
			$this->CouponError("Illegal property name in _CheckForEmptyProperty: " . $PropertyName);
			
		if(empty($Str))
			$this->CouponError("The property has not been set yet:" . $PropertyName );
	}
	
	
	//--------   GET and SET Methods for all of the properties of a Coupon  ----------- //
	//--------   Useful if we are Editing or Adding a new coupon to the database -------//
	
	
	function GetCouponID(){
		$this->_EnsureCouponIDisSet();
		return $this->_couponid;
	}
	function GetCouponCode(){
		return $this->_cp_Code;
	}
	function GetCouponIsActive(){
		return $this->_cp_Active;
	}
	function GetCouponName(){
		return $this->_cp_Name;
	}
	function GetCouponCategoryID(){
		return $this->_cp_CategoryID;
	}
	// Returns an array of product ID's that this coupon is good for
	// If the coupon is not restricted to a product, then the array is empty
	// If the array is not empty... then the user must have at least 1 product in the shopping cart that is contained in the array.
	function GetProductIDs(){
		return $this->_productIDarr;
	}
	
	
	// Similar to GetProductIDs... this method returns an array of ProductIDs
	// If the array is empty then it does not require a bundle of products to be in the shopping cart.
	// If it is not empty... then then each ProductID in the array must be in the shopping cart or the coupon won't work
	// This works off of Production Product ID's only.  For example... (Postcards - Static) and (Postcards - Variable) both work off of the same Production ProductID 
	function GetBundeledProductIDs(){
		return $this->_productBundleArr;
	}

	// returns True or false
	function GetCouponNeedsActivation(){
		
		// Convert from int to boolean
		return $this->_cp_ActivationRequired ? true : false;
	}	
	function GetCouponUsageLimit(){
		return $this->_cp_UsageLimit;
	}
	function GetCouponWithinFirstOrders(){
		return $this->_cp_WithinFirstOrders;
	}
	function GetCouponDiscountPercent(){
		return $this->_cp_DiscountPercent;
	}
	function GetCouponExpDate(){
		return $this->_cp_ExpDate;
	}
	function GetCouponExpDateFormated(){
		if(empty($this->_cp_ExpDate))
			return "";
		return strftime( "%m/%d/%Y", $this->_cp_ExpDate );
	}
	function GetCouponDateCreated(){;
		return $this->_cp_DateCreated;
	}
	function GetCouponMaxAmount(){
		return $this->_cp_MaxAmount;
	}
	
	// May return "order" or "project"
	function GetCouponMaxAmountType(){
		
		if($this->_cp_MaxAmountType == "O")
			return "order";
		else if($this->_cp_MaxAmountType == "P")
			return "project";
		else if($this->_cp_MaxAmountType == "T")
			return "quantity";
		else
			throw new Exception("Error in method GetCouponMaxAmountType.. The MaxAmountType characters is not defined.");
	}
	
	function GetMinimumSubtotal(){
		return $this->_cp_MinimumSubtotal;
	}
	function GetMaximumSubtotal(){
		return $this->_cp_MaximumSubtotal;
	}
	function GetProjectMinQuantity(){
		return $this->_cp_ProjectMinQuantity;
	}
	function GetProjectMaxQuantity(){
		return $this->_cp_ProjectMaxQuantity;
	}
	function GetCouponShippingDiscount(){
		return $this->_cp_ShippingDiscount;
	}
	function GetProofingAlert(){
		return $this->_cp_ProofingAlert;
	}
	function GetProductionNote(){
		return $this->_cp_ProductionNote;
	}	
	function GetSalesLink(){
		return $this->_cp_SalesLink;
	}
	function GetCouponComments(){
		return $this->_cp_Comments;
	}
	function GetCouponCreatorUserID(){
		return $this->_cp_CreateUserID;
	}
	
	function getDomainID(){
		return $this->_domainID;
	}
	
	// Returns an Array of Product Options that are required on a Project before a coupon can be used.
	// If there are no requirments then this method will return an empty array.
	// Otherwise, the Key to the Array will be the OptionName and value will be an array.
	// Inside of the second level array there may be 1 or more Product Options.
	// The Product Names and Product Options are all in Uppercase unless you pass in false.
	// If you don't want uppercase the method will capitalize the first letter of every item.
	function GetProductOptionsArr($uppercase = false){
		
		if($uppercase){
			return $this->_cp_ProductOptionsArr;
		}
		else{
			$retArr = array();
			
			foreach($this->_cp_ProductOptionsArr as $thisOptionName => $choicesArr){
				$thisOptionName = ucfirst(strtolower($thisOptionName));
				
				$retArr[$thisOptionName] = array();
				
				foreach($choicesArr as $thisChoice){
					$thisChoice = ucfirst(strtolower($thisChoice));
					$retArr[$thisOptionName][] = $thisChoice;
				
				}
			}
			
			return $retArr;
		}
	}
	
	
	



	// Returns an Array of Product Options that the discount can not be applied to.
	// For example... we may give someone 50% off of the order... but it does not apply to "Postage"
	// If there are no requirments then this method will return an empty array.
	// Otherwise, the Key to the Array will be the OptionName and value will be an array.
	// Inside of the second level array there may be 1 or more Product Options.
	// The Product Names and Product Options are all in Uppercase unless you pass in false.
	// If you don't want uppercase the method will capitalize the first letter of every item.
	function GetNoDiscountOnProductOptionsArr($uppercase = false){
		
		if($uppercase){
			return $this->_cp_NoDiscountOnOptions;
		}
		else{
			$retArr = array();
			
			foreach($this->_cp_NoDiscountOnOptions as $thisOptionName => $choicesArr){
			
				$thisOptionName = ucfirst(strtolower($thisOptionName));
				
				$retArr[$thisOptionName] = array();
				
				foreach($choicesArr as $thisChoice){
					$thisChoice = ucfirst(strtolower($thisChoice));
					$retArr[$thisOptionName][] = $thisChoice;
				
				}
			}
			
			return $retArr;
		}
	}
	
	
	
	
	
	
	
	

	// Will turn a sentence describing the coupon, and what it is good for
	// Will throw an error if the coupon is not valid or not set
	// EX: "This coupon is good for 25% off any product.  There is a maximum discount limit of $20.00.  $5.00 discount off shipping and handeling."
	function GetSummaryOfCoupon(){
	
		$this->_EnsureCouponDataIsLoaded();
		
		// The first sentence will contain
		$retStr = "";
		
		if($this->_cp_DiscountPercent != 0){
		
			// If the discount percentage is is 100% and there is a maximum discount... no need to say 100% off... just say how many dollars it will take off.
			if($this->_cp_DiscountPercent == 100 && $this->_cp_MaxAmount){
				$retStr .= "Good for \$" . $this->_cp_MaxAmount . " off";
				
				if($this->_cp_MaxAmountType == "P")
					$retStr .= " each project of ";
				else if($this->_cp_MaxAmountType == "T")
					$retStr .= " each quantity of ";
				else
					$retStr .= " the order. Can be used with ";
			}
			else{
				$retStr .= "Good for " . $this->_cp_DiscountPercent . "% off of ";
			}
			
			if(empty($this->_productIDarr)){
				$retStr .= "any product at ".Domain::getDomainKeyFromID($this->_domainID).".";
			}
			else{
				if(sizeof($this->_productIDarr) == 1){
					$productObj = Product::getProductObj($this->_dbCmd, $this->_productIDarr[0]);
					$retStr .= $productObj->getProductTitleWithExtention() . " only.";
				}
				else{
	
					// If there are multiple products it should say something like "business cards, postcards and envelopes"
					$productTitlesArr = array();
					for($i=0; $i< sizeof($this->_productIDarr); $i++){
					
						$productObj = Product::getProductObj($this->_dbCmd, $this->_productIDarr[$i]);

						$parentProductID = $productObj->getParentProductID();
						
						// List the root product names within the bundle.
						if($parentProductID)
							$productObj = Product::getProductObj($this->_dbCmd, $parentProductID);
						
						$productTitlesArr[] = $productObj->getProductTitle();
					}
					
					
					// If we have (Postcards - Static) and (Postcards - Variable) we don't want to list both of them... just "Postcards" is fine.
					$productTitlesArr = array_unique($productTitlesArr);
					
					$counter = 0;
					foreach($productTitlesArr as $thisProductTitle){
						
						if($counter == 0)
							$retStr .= " " . $thisProductTitle;
						else if($counter == (sizeof($productTitlesArr) - 1))
							$retStr .= ", and " . $thisProductTitle . ".";
						else
							$retStr .= ", " . $thisProductTitle;
						
						$counter++;
					}
				}
			}
			
			// Don't show them the maximum discount if the coupon percentage is 100%... that is because we would have already told them.
			if($this->_cp_MaxAmount && $this->_cp_DiscountPercent != 100){
				$retStr .= " There is a max discount limit of \$" . number_format($this->_cp_MaxAmount, 2);
			
				if($this->_cp_MaxAmountType == "P")
					$retStr .= " (per project).";
				else if($this->_cp_MaxAmountType == "T")
					$retStr .= " (per quantity).";
				else
					$retStr .= ".";
			}
			

			// Let them know if it only works on certain quantities.
			if($this->_cp_ProjectMinQuantity && !$this->_cp_ProjectMaxQuantity)
				$retStr .= " Minimum quantity per project required (" . $this->_cp_ProjectMinQuantity . ").";
			else if(!$this->_cp_ProjectMinQuantity && $this->_cp_ProjectMaxQuantity)
				$retStr .= " Maximum quantity per project allowed (" . $this->_cp_ProjectMaxQuantity . ").";
			else if($this->_cp_ProjectMinQuantity && $this->_cp_ProjectMaxQuantity && $this->_cp_ProjectMinQuantity == $this->_cp_ProjectMaxQuantity)
				$retStr .= " Only works with quantities of " . $this->_cp_ProjectMinQuantity . ".";
			else if($this->_cp_ProjectMinQuantity && $this->_cp_ProjectMaxQuantity)
				$retStr .= " Only works with quantities (" . $this->_cp_ProjectMinQuantity . " - " . $this->_cp_ProjectMaxQuantity . ").";

			if(!empty($this->_cp_ProductOptionsArr))
				$retStr .= " There are also Product Option requirements.";
				
			if(!empty($this->_cp_NoDiscountOnOptions))
				$retStr .= " Discount may not apply to certain Product Options.";
		}
		else{
			$retStr .= "This coupon does not apply any discounts to the subtotal.";
		}
		
		if($this->_cp_WithinFirstOrders == 1)
			$retStr .= " This can only be used on your first order.";
		else if(!empty($this->_cp_UsageLimit))
			$retStr .= " This coupon may only be used " . $this->_cp_UsageLimit . " " . LanguageBase::GetPluralSuffix($this->_cp_UsageLimit, "time", "times") . ".";
		
		if($this->_cp_ShippingDiscount)
			$retStr .= " Includes a \$" . number_format($this->_cp_ShippingDiscount, 2) . " discount for shipping.";
		else
			$retStr .= " Discount does not apply to Shipping and Handling.";

		if(!$this->_cp_DiscountPercent && !$this->_cp_ShippingDiscount)
			$retStr = "This coupon does not provide any discounts.";
		
	
		return $retStr;
	}


	// May return "Unlimited Usage" or "On first order only", etc.
	// Takes into accoutn the Usage Limit againse the WithinXOrders
	function GetUsageDescription(){
	
		$this->_EnsureCouponDataIsLoaded();
		

		if($this->GetCouponWithinFirstOrders() == "" && $this->GetCouponUsageLimit() == ""){
		
			$retStr = "Unlimited Usage";
		}
		else if($this->GetCouponWithinFirstOrders() != "" && $this->GetCouponUsageLimit() != "" ){
		
			// If there is both an Coupon Usage Limit and WithinXorders Limit... 
			// ... then there is no point with factoring in the Usage Limit if the WithinXorders limit less than or equal to the Usage Limit 
		
			if($this->GetCouponWithinFirstOrders() <= $this->GetCouponUsageLimit()){
				$retStr = $this->GetWithinFirstOrdersLimitDescription();
			}
			else{
				$retStr = $this->GetUsageLimitDescription() . ", ";
				$retStr .= strtolower($this->GetWithinFirstOrdersLimitDescription()) . ".";
			}
		}
		else if($this->GetCouponWithinFirstOrders() != ""){
		
			$retStr = $this->GetWithinFirstOrdersLimitDescription();
		
		}
		else if($this->GetCouponUsageLimit() != ""){
		
			$retStr = $this->GetUsageLimitDescription();
		}
		else{
		
			throw new Exception("Unknown error occured in method GetUsageDescription()");
		}
		
		return $retStr;
		
	}
	
	
	function GetUsageLimitDescription(){
	
		$this->_EnsureCouponDataIsLoaded();
		
		return "Limit " . $this->GetCouponUsageLimit() . (($this->GetCouponUsageLimit() == 1) ? (" time use") : (" uses"));
	}
	

	function GetWithinFirstOrdersLimitDescription(){
	
		$this->_EnsureCouponDataIsLoaded();
		
		if($this->GetCouponWithinFirstOrders() == 1)
			return "Use on first order only";
		else
			return "Must use within first " . $this->GetCouponWithinFirstOrders() . " orders";
	}



	// ----  Set Methods ----


	function SetCouponCode($x){
	
		$x = strtoupper($x);
		
		if(!$this->CheckIfCouponFormatIsOK($x))
			$this->CouponError("Error: Coupon Code is not in the proper format.<br><br>
						* Coupon Code can only contain letters/digits/hyphens<br>
						* Coupon Code must begin with a letter other than 'X', or numbers 1-9<br>
						* Coupon Code cannot exceed 20 characters.");
		$this->_cp_Code = $x;
	}
	
	
	function SetCouponIsActive($x){
		if($x != 0 && $x != 1)
			$this->CouponError("SetCouponIsActive must be a 1 or a 0");
		$this->_cp_Active = $x;
	}
	function SetCouponName($x){
		$x = trim($x);
		if(empty($x))
			$this->CouponError("The coupon name can not be left empty");
		$this->_cp_Name = $x;
	}
	function SetCouponCategoryID($x){
		if( !preg_match('/^\d+$/', $x))
			$this->CouponError("The Coupon Category ID must be an Integer");
			
		$this->_dbCmd->Query("SELECT COUNT(*) FROM couponcategories WHERE ID=$x AND DomainID=" . $this->_domainID);
		if($this->_dbCmd->GetValue() == 0)
			$this->CouponError("The CategoryID not exist, when calling SetCouponCategoryID");
			
		$this->_cp_CategoryID = $x;
	}
	function SetCouponNeedsActivation($x){
		if(!is_bool($x))
			$this->CouponError("SetCouponNeedsActivation must be boolean");
		
		// Convert from boolean to an integer for the DB.
		$this->_cp_ActivationRequired = ($x ? 1 : 0);
	}
	function SetCouponUsageLimit($x){
		if( !preg_match('/^[\d]*$/', $x) || preg_match('/^0+$/', $x))
			$this->CouponError("Usage Limit must be a positive number, blank string if usage is unlimited.");
		
		$this->_cp_UsageLimit = $x;
	}
	function SetCouponWithinFirstOrders($x){
		if( !preg_match('/^[\d]*$/', $x) || preg_match('/^0+$/', $x))
			$this->CouponError("The Within First Number of Orders value must be a positive number, blank string if usage is unlimited.");
		
		$this->_cp_WithinFirstOrders = $x;
	}
	function SetCouponDiscountPercent($x){
		if( !preg_match('/^[\d]+$/', $x)  || $x < 0 || $x > 100 )
			$this->CouponError("Discount Percent value is incorrect.");
		$this->_cp_DiscountPercent = $x;
	}
	function SetCouponExpDate($x){
		if( !preg_match('/^\d+$/', $x))
			$this->CouponError("Expiration Date must be a UnixTimeStamp, or Zero for never expires");
		$this->_cp_ExpDate = $x;
	}
	function SetCouponMaxAmount($x){
		if( !preg_match('/^(0|\d+|\d+\.\d{1,2})$/', $x) )
			$this->CouponError("Max amount must be an integer or decimal value.  For no Max Amount, enter 0.");
			
		if($x != 0)
			$x = number_format($x, 2, '.', '');
		else
			$x = 0; // Prevent 0.0
		
		$this->_cp_MaxAmount = $x;
	}
	function SetCouponMaxAmountType($x){
		
		if($x == "order")
			$this->_cp_MaxAmountType = "O";
		else if($x == "project")
			$this->_cp_MaxAmountType = "P";
		else if($x == "quantity")
			$this->_cp_MaxAmountType = "T";
		else
			throw new Exception("Error in method SetCouponMaxAmountType.  The Value must be 'order' or 'project'.");
	}
	
	function SetCouponShippingDiscount($x){
		if( !preg_match('/^(0|\d+|\d+\.\d{1,2})$/', $x) )
			$this->CouponError("Shipping Discount must be an integer or decimal value.  For no discount, enter 0.");
		
		if($x != 0)
			$x = number_format($x, 2, '.', '');
		else
			$x = 0; // Prevent 0.0
		
		$this->_cp_ShippingDiscount = $x;
	}
	function SetProofingAlert($x){
		if(strlen($x) > 200)
			$this->CouponError("The Proofing Alert is too big in size.  Must be 200 characters or less");
		$this->_cp_ProofingAlert = $x;
	}
	function SetProductionNote($x){
		if(strlen($x) > 200)
			$this->CouponError("The Production Note is too big in size.  Must be 200 characters or less");
		$this->_cp_ProductionNote = $x;
	}
	// Must also pass in a SalesCommission Object so that we can check of the UserID link is good or not.
	function SetSalesLink($SalesCommObj, $x){
		
		if($x){
			if(!$SalesCommObj->CheckIfSalesRep($x))
				$this->CouponError("Can not set the Sales Link because the User is not a Sales Rep.");
		}
		
		if(empty($x))  // If $x is passed in as NULL, make sure it is 0 for the database
			$x = 0;
		
		$this->_cp_SalesLink = $x;
	}
	function SetCouponComments($x){
		if(strlen($x) > 200)
			$this->CouponError("The Comments is too big in size.  Must be 200 characters or less");
		$this->_cp_Comments = $x;
	}
	
	
	function SetMinimumSubtotal($x){
	
		if( !preg_match('/^(0|\d+|\d+\.\d{1,2})$/', $x) )
			$this->CouponError("Minimum subtotal must be an integer or decimal value.  For no Minimum, enter 0.");

		if($x != 0)
			$x = number_format($x, 2, '.', '');
		else
			$x = 0; // Prevent 0.0

		$this->_cp_MinimumSubtotal = $x;
	}
	
	function SetMaximumSubtotal($x){
	
		if( !preg_match('/^(0|\d+|\d+\.\d{1,2})$/', $x) )
			$this->CouponError("Maximum subtotal must be an integer or decimal value.  For no Maximum, enter 0.");

		if($x != 0)
			$x = number_format($x, 2, '.', '');
		else
			$x = 0; // Prevent 0.0

		$this->_cp_MaximumSubtotal = $x;
	}
	
	function SetProjectMinQuantity($x){
	
		if( !preg_match('/^\d+$/', $x))
			$this->CouponError("The Project Minimum Quantity must be an Integer.  Use Zero for no minimum.");

		$this->_cp_ProjectMinQuantity = $x;
	}
	
	function SetProjectMaxQuantity($x){
	
		if( !preg_match('/^\d+$/', $x))
			$this->CouponError("The Project Maximum Quantity must be an Integer.  Use Zero for no maximum.");

		$this->_cp_ProjectMaxQuantity = $x;
	}
	
	function SetCouponCreatorUserID($x){
		if( !preg_match('/^\d+$/', $x))
			$this->CouponError("The Create User ID must be an Integer");
			
		$this->_dbCmd->Query("SELECT COUNT(*) FROM users WHERE ID=$x");
		if($this->_dbCmd->GetValue() == 0)
			$this->CouponError("The User Does not exist, when calling SetCouponCreatorUserID");
			
		$this->_cp_CreateUserID = $x;
	}
	

	// Pass in an array of product IDs that this coupon is good for
	function SetProductIDsForCoupon($productIDarr){
	
		if(!is_array($productIDarr))
			throw new Exception("Error in method SetProductIDsForCoupon... Parameter must be an array.");
	
		$prodIDstr = "";	
	
		// Put it into a string and then re-parse.
		// Maybe a little extra work, but then we can be sure data is all integers
		foreach($productIDarr as $thisProdID){
			if(preg_match("/^\d+$/", $thisProdID) && $thisProdID != "0")
				$prodIDstr .= $thisProdID . "|";
		}

		$this->_productIDarr = $this->_getDigitArrayFromPipeDelimetedString($prodIDstr);
	}

	// Pass in an array of Product ID's that must be in the User's shopping cart before a coupon can be used.
	function SetProductBundlesForCoupon($productIDarr){
	
		if(!is_array($productIDarr))
			throw new Exception("Error in method SetProductBundlesForCoupon... Parameter must be an array.");
			
		$prodIDstr = "";	
	
		// Put it into a string and then re-parse.
		// Maybe a little extra work, but then we can be sure data is all integers
		foreach($productIDarr as $thisProdID){
			if(preg_match("/^\d+$/", $thisProdID) && $thisProdID != "0")
				$prodIDstr .= $thisProdID . "|";
		}

		$this->_productBundleArr = $this->_getDigitArrayFromPipeDelimetedString($prodIDstr);
	}
	
	
	function AddProductOption($optionName, $optionChoice){
	
		$optionName = strtoupper(trim($optionName));
		$optionChoice = strtoupper(trim($optionChoice));
		
		if(empty($optionName) || empty($optionChoice))
			throw new Exception("Error in Coupon Method AddProductOption.  The Option Name and Choice may not be left blank.");
	
		if(!isset($this->_cp_ProductOptionsArr[$optionName]))
			$this->_cp_ProductOptionsArr[$optionName] = array();
		
		if(!in_array($optionChoice, $this->_cp_ProductOptionsArr[$optionName]))
			$this->_cp_ProductOptionsArr[$optionName][] = $optionChoice;
	
	}
	
	
	
	function AddNoDiscountForOption($optionName, $optionChoice){
	
		$optionName = strtoupper(trim($optionName));
		$optionChoice = strtoupper(trim($optionChoice));
		
		if(empty($optionName) || empty($optionChoice))
			throw new Exception("Error in Coupon Method AddNoDiscountForOption.  The Option Name and Choice may not be left blank.");
	
		if(!isset($this->_cp_NoDiscountOnOptions[$optionName]))
			$this->_cp_NoDiscountOnOptions[$optionName] = array();
		
		if(!in_array($optionChoice, $this->_cp_NoDiscountOnOptions[$optionName]))
			$this->_cp_NoDiscountOnOptions[$optionName][] = $optionChoice;
	
	}
	
	
	
	function RemoveProductOption($optionName, $optionChoice){
	
		$this->_EnsureCouponDataIsLoaded();
	
		$optionName = strtoupper(trim($optionName));
		$optionChoice = strtoupper(trim($optionChoice));

		if(empty($optionName) || empty($optionChoice))
			throw new Exception("Error in Coupon Method RemoveProductOption.  The Option Name and Choice may not be left blank.");
		
		
		// Take stuff out of our member variable Array "_cp_ProductOptionsArr" and copy it over to the Temp array 
		// (only if the Product Option Name/Choice is not the one we are trying to delete).
		$tempProductOptionsArr = array();
		
		foreach($this->_cp_ProductOptionsArr as $thisOptionName => $thisChoicesArr){
		
			$tempProductOptionsArr[$thisOptionName] = array();
			
			foreach($thisChoicesArr as $thisChoice){
				if(!($thisOptionName == $optionName && $thisChoice == $optionChoice))
					$tempProductOptionsArr[$thisOptionName][] = $thisChoice;
			}
			
		
		}
		
		// Now wipe out our member array for the Product Option array.
		// Copy the temp array back into it.  Just be careful not to copy the Option Name back in if the list of choices is now empty.
		$this->_cp_ProductOptionsArr = array();

		foreach($tempProductOptionsArr as $tempOptionName => $tempChoicesArr){
			if(!empty($tempChoicesArr))
				$this->_cp_ProductOptionsArr[$tempOptionName] = $tempChoicesArr;
		}

	}

	
	


	function RemoveNoDiscountForOption($optionName, $optionChoice){
	
		$this->_EnsureCouponDataIsLoaded();
	
		$optionName = strtoupper(trim($optionName));
		$optionChoice = strtoupper(trim($optionChoice));

		if(empty($optionName) || empty($optionChoice))
			throw new Exception("Error in Coupon Method RemoveNoDiscountForOption.  The Option Name and Choice may not be left blank.");
		
		
		// Take stuff out of our member variable Array "_cp_NoDiscountOnOptions" and copy it over to the Temp array 
		// (only if the Product Option Name/Choice is not the one we are trying to delete).
		$tempNoDiscOptionsArr = array();
		
		foreach($this->_cp_NoDiscountOnOptions as $thisOptionName => $thisChoicesArr){
		
			$tempNoDiscOptionsArr[$thisOptionName] = array();
			
			foreach($thisChoicesArr as $thisChoice){
				if(!($thisOptionName == $optionName && $thisChoice == $optionChoice))
					$tempNoDiscOptionsArr[$thisOptionName][] = $thisChoice;
			}
			
		
		}
		
		// Now wipe out our member array for the Product Option array.
		// Copy the temp array back into it.  Just be careful not to copy the Option Name back in if the list of choices is now empty.
		$this->_cp_NoDiscountOnOptions = array();

		foreach($tempNoDiscOptionsArr as $tempOptionName => $tempChoicesArr){
			if(!empty($tempChoicesArr))
				$this->_cp_NoDiscountOnOptions[$tempOptionName] = $tempChoicesArr;
		}

	}	
	


}
