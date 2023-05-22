<?
class ShoppingCart {

	
	static function InsertProjectSessionIntoShoppingCart($projectrecord){
		
		$projectrecord = intval($projectrecord);
		
		$dbCmd = new DbCmd();
		if(!ProjectSession::CheckIfUserOwnsProjectSession($dbCmd, $projectrecord))
			throw new Exception("Can not add a project to a shopping cart if it doesn't exist.");
		
		$dbCmd->InsertQuery("shoppingcart", array("ProjectRecord"=>$projectrecord, "SID"=>WebUtil::GetSessionID(), "DateLastModified"=>time(), "DomainID"=>Domain::oneDomain()));
	}

	// Returns an array of Project session ID's that exist in the User's shopping cart.
	static function GetProjectSessionIDsInShoppingCart(DbCmd $dbCmd, $SessionID){
	
		$returnArr = array();
		$dbCmd->Query("SELECT PS.ID FROM projectssession AS PS INNER JOIN shoppingcart AS SC on SC.ProjectRecord = PS.ID  WHERE SC.SID='". DbCmd::EscapeLikeQuery($SessionID) . "' AND SC.DomainID=".Domain::getDomainIDfromURL());
		$returnArr = $dbCmd->GetValueArr();
	
		return $returnArr;
	}


	// Returns an array of Project session ID's that exist in the User's shopping cart, which are also saved
	static function GetProjectSessionIDsInShoppingCartThatAreSaved(DbCmd $dbCmd, $SavedProjectID, $SessionID){
	
		WebUtil::EnsureDigit($SavedProjectID);
	
		$ReturnArr = array();
		$dbCmd->Query("SELECT PS.ID FROM projectssession AS PS INNER JOIN shoppingcart AS SC on SC.ProjectRecord = PS.ID  WHERE PS.SavedID=$SavedProjectID AND SC.SID='". DbCmd::EscapeLikeQuery($SessionID) . "' AND SC.DomainID=".Domain::getDomainIDfromURL());
		while($x = $dbCmd->GetValue())
			$ReturnArr[] = $x;
	
		return $ReturnArr;
	}
	
	
	static function GetShopCartSubTotal(DbCmd $dbCmd, $user_sessionID, $discount){
	
		$subtotal = 0;
	
		$dbCmd->Query("SELECT projectssession.CustomerSubtotal FROM projectssession 
					INNER JOIN shoppingcart ON shoppingcart.ProjectRecord = projectssession.ID 
					WHERE shoppingcart.SID=\"". mysql_real_escape_string($user_sessionID) . "\" AND shoppingcart.DomainID=".Domain::getDomainIDfromURL()." order by shoppingcart.ID DESC");
	
		while ($custSubtotal = $dbCmd->GetValue())
			$subtotal += $custSubtotal - round($custSubtotal * $discount, 2);
		
		return number_format($subtotal, 2, '.', '');
	}
	
	
	// Important.  The Tax must be figured out by rounding the Tax off at each project level
	// Otherwise, if we get the tax of the whole subtotal, we may be off by a penny or two.
	// It has to match tax calculations elswhere that use this same method
	static function GetShopCartTax(DbCmd $dbCmd, $user_sessionID, $discount, $shippingState){
	
		$taxTotal = 0;
	
		$dbCmd->Query("SELECT projectssession.CustomerSubtotal FROM projectssession 
				INNER JOIN shoppingcart ON shoppingcart.ProjectRecord = projectssession.ID 
				WHERE shoppingcart.SID=\"$user_sessionID\" AND shoppingcart.DomainID=".Domain::getDomainIDfromURL()." order by shoppingcart.ID DESC");
	
		while ($ProjectSubtotal = $dbCmd->GetValue()){
	
			$subtotal = $ProjectSubtotal - round($ProjectSubtotal * $discount, 2);
		
			$taxTotal += round(Constants::GetSalesTaxConstant($shippingState) * $subtotal, 2);
		}
	
		return $taxTotal;
	}
	
	

	// Since the discount may not apply to certain product options...
	// This function will return an adjusted discount based upon the original subotal, the non-discounted portion, and the users original discount
	// Discount should be a number between 1 and 100.... but it can have decimals.
	static function GetAdjustedPermanentDiscount(DbCmd $dbCmd, $customerSubtotal, $discNoAppyTotal, $permDiscount){
	
		if($permDiscount == 0)
			return $permDiscount;
		
		if($permDiscount < 0 || $permDiscount > 100)
			throw new Exception("Error in function ShoppingCart::GetAdjustedPermanentDiscount.  The discount must be between 1 and 100.");
			
		
		if($discNoAppyTotal == 0)
			return $permDiscount;
		
		$subotalMinusNoApply = $customerSubtotal - $discNoAppyTotal;
		
	
		$dicountedDollars = round($permDiscount / 100 * $subotalMinusNoApply, 2);
		
		return Widgets::GetDiscountPercentFormated($customerSubtotal, $dicountedDollars);
	}
		

	// returns True if there is at least one product in the shopping cart that has mailing services.
	static function checkIfProductInShoppingCartHasMailingServices(){
		
		$dbCmd = new DbCmd();
		$productdIDsInShoppingCart = ProjectGroup::GetProductIDlistFromGroup($dbCmd, WebUtil::GetSessionID(), "shoppingcart");
		
		foreach($productdIDsInShoppingCart as $thisProductID){
			if(Product::checkIfProductHasMailingServices($dbCmd, $thisProductID))
				return true;
		}
		
		return false;		
	}

	// Returns TRUE is there is at least one product in the Shopping Cart that will be shipped instead of mailed.
	static function checkIfProductInShoppingCartWillBeShipped(){
		
		$dbCmd = new DbCmd();
		$productdIDsInShoppingCart = ProjectGroup::GetProductIDlistFromGroup($dbCmd, WebUtil::GetSessionID(), "shoppingcart");
		
		foreach($productdIDsInShoppingCart as $thisProductID){
			if(!Product::checkIfProductHasMailingServices($dbCmd, $thisProductID))
				return true;
		}
		
		return false;		
	}
}

?>