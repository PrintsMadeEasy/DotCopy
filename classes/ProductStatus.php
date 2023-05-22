<?

// Allows you to override a Project Status for each individual product.
// You may extend the language that a cusotmer sees for the Status Title and optionally add a Status Description.
// For example... instead of "Printed" you may want it to say "Printing Complete" ... or use another language altogether.
class ProductStatus {

	private $_dbCmd;
	private $_productID;
	

	private $_statusTitlesArr = array();
	private $_statusDescArr   = array();
	
	private $_projectStatusHashArr;
	private $_projectStatusCharArr;
	
	
	// Load the Product Status Descriptions for this Product in the constructor.
	function ProductStatus(DbCmd $dbCmd,$productID) {

		$productID = intval($productID);

		$this->_dbCmd = $dbCmd;	
		$this->_productID = $productID;
		
		// Fill up an internal array of the Project Statuses so that we know what is permissable.
		$projectStatusArr = ProjectStatus::GetStatusDescriptionsHash();
		foreach($projectStatusArr as $thisStatusChar => $thisStatusDescHash)
			$this->_projectStatusHashArr[$thisStatusChar] = $thisStatusDescHash["DESC"];

		$this->_projectStatusCharArr = array_keys($this->_projectStatusHashArr);
		
		if(!Product::checkIfProductIDexists($dbCmd,$productID))
			throw new Exception("Error in method ProductStatus, Product ID does not exist.");
		
			
		$domainIDofProduct = Product::getDomainIDfromProductID($dbCmd, $productID);
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		if(!$passiveAuthObj->CheckIfUserCanViewDomainID($domainIDofProduct))
			throw new Exception("The Product Status doesn't exist within the domain.");
		
		// If we have saved the values for this Product... then load it. 
		// Otherwise, see if there is a default Profile saved... If so, load it but don't save to the DB.
		$dbCmd->Query("SELECT StatusChar,StatusTitle,StatusDesc FROM productstatus WHERE ProductID = " . $this->_productID);				
		if($dbCmd->GetNumRows() == 0){
		
			$defaultProductStatusProductID = ProductStatus::getDefaultProductStatusProductID($dbCmd, $domainIDofProduct);
			if($defaultProductStatusProductID)
				$this->copySettingsFromOtherProduct($defaultProductStatusProductID);
		}
		else{
			while($row = $dbCmd->GetRow()) {
				$this->_statusTitlesArr[$row["StatusChar"]] = $row["StatusTitle"];		
				$this->_statusDescArr[$row["StatusChar"]] = $row["StatusDesc"];		
			}
		}
	
				
	}
	

	// Static method to get the default Product ID
	// returns NULL if there is not a default saved yet.
	static function getDefaultProductStatusProductID(DbCmd $dbCmd, $domainID) {
		
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		if(!$passiveAuthObj->CheckIfUserCanViewDomainID($domainID))
			throw new Exception("Error in method getDefaultProductStatusProductID. The Domain ID is invalid.");
		
		$dbCmd->Query("SELECT ProductID FROM productstatus 
						INNER JOIN products ON productstatus.ProductID = products.ID  
						WHERE productstatus.IsDefault = 'Y' AND products.DomainID=$domainID LIMIT 1");
		return $dbCmd->GetValue();
	}
	
	// Static Method.  Sets the Product ID to be the default, and makes sure that all of the other Products are not the default.
	static function setDefaultProductID(DbCmd $dbCmd,$productID) {
			
		$productID = intval($productID);
		
		$productObj = new Product($dbCmd, $productID, true);
		
		$dbCmd->Query("update productstatus set IsDefault = 'N'");
		$dbCmd->Query("update productstatus set IsDefault = 'Y' WHERE ProductID = " . $productObj->getProductID());
	}
	
	// Static Method to delete any settings stored on this Product.
	static function deleteSettingsForProductID(DbCmd $dbCmd,$productID) {
			
		$productID = intval($productID);
		
		$productObj = new Product($dbCmd, $productID, true);
		
		$dbCmd->Query("DELETE FROM productstatus WHERE ProductID = " . $productObj->getProductID());
	}
	
	
	

	// Returns TRUE if this Product has its own settings saved.  FALSE if the settings are coming from the default Product (or from the root Status descriptions).
	function checkIfSettingsAreSavedForThisProduct(){
		
		$this->_dbCmd->Query("SELECT COUNT(*) FROM productstatus WHERE ProductID=" . $this->_productID);
		
		if($this->_dbCmd->GetValue() == 0)
			return false;
		else
			return true;
	}
	
	

	function setStatusTitle($statusChar, $statusTitle) {
	
		if(!in_array($statusChar,$this->_projectStatusCharArr))
			throw new Exception("Bad Status Character in Method ProductStatus::setStatusTitle");
		
		$statusTitle = trim($statusTitle);
		if(empty($statusTitle))
			throw new Exception("Error in method ProductStatus->setStatusTitle. The Status title can not be blank.");
	
		$this->_statusTitlesArr[$statusChar] = $statusTitle;
	}
	
	
	function setStatusDesc($statusChar, $statusDesc) {
	
		if(!in_array($statusChar,$this->_projectStatusCharArr))
			throw new Exception("Bad Status Character in Method ProductStatus::setStatusDesc");	
	

		$this->_statusDescArr[$statusChar] = $statusDesc;
	}
	
	
	function getStatusDesc($statusChar) {
		
		if(!array_key_exists($statusChar, $this->_statusDescArr)) 
			return "";
		else
			return $this->_statusDescArr[$statusChar];
	
	}
	
	
	// The would prefer to get the "Status Description".  
	//But if a description has not been defined, then it will return the Status Title.
	function getProductStatusDescriptionForCustomer($statusChar){
		
		if(!in_array($statusChar,$this->_projectStatusCharArr))
			throw new Exception("Bad Status Character in Method ProductStatus::getProductStatusDescriptionForCustomer");
			
		$statusDesc = $this->getStatusDesc($statusChar);
		
		if(empty($statusDesc))
			$statusDesc = $this->getStatusTitle($statusChar);
			
		return $statusDesc;
	}
	
	
	function getStatusTitle($statusChar) {
		
		if(!array_key_exists($statusChar, $this->_statusTitlesArr)) 
			return $this->_projectStatusHashArr[$statusChar];
		else
			return $this->_statusTitlesArr[$statusChar];
	}
	
	
	// Useful when adding new Products to the system... you can copy over settings from the Default product so the administrator doesn't have to reimport settings.
	function copySettingsFromOtherProduct($otherProductID){
	
		$otherProductStatusObj = new ProductStatus($this->_dbCmd, $otherProductID);
		
		foreach($this->_projectStatusCharArr as $projStatus){
			$this->setStatusTitle($projStatus, $otherProductStatusObj->getStatusTitle($projStatus));
			$this->setStatusDesc($projStatus, $otherProductStatusObj->getStatusDesc($projStatus));
		}
	}
	
	


	function updateDatabase(){
	
		// Loop through all of the Status Characters... and if we have defined a value, update the databse.
		foreach($this->_projectStatusCharArr as $projStatus){
				
			if(!array_key_exists($projStatus, $this->_statusTitlesArr) || !array_key_exists($projStatus, $this->_statusDescArr))
				continue;

			$statusTitle = $this->_statusTitlesArr[$projStatus];
			$statusDesc = $this->_statusDescArr[$projStatus];

			$whereClause = "ProductID=" . $this->_productID . " AND StatusChar='$projStatus'";

			$this->_dbCmd->Query("SELECT StatusChar FROM productstatus WHERE " . $whereClause);
			$checkedProjStatus = $this->_dbCmd->GetValue();


			$dbArr["StatusChar"]  = $projStatus;
			$dbArr["StatusDesc"]  = $statusDesc;
			$dbArr["StatusTitle"] = $statusTitle;
			$dbArr["ProductID"]   = $this->_productID;

			if(empty($checkedProjStatus)) 
				$this->_dbCmd->InsertQuery("productstatus", $dbArr);				
			else
				$this->_dbCmd->UpdateQuery("productstatus",$dbArr, $whereClause);	

		}
		
		// Find out if we have a default Product Set.
		// In case this is the first Product we have saved... then make it the default.
		$domainIDofProduct = Product::getDomainIDfromProductID($this->_dbCmd, $this->_productID);
		$defaultProductStatus = ProductStatus::getDefaultProductStatusProductID($this->_dbCmd, $domainIDofProduct);
		
		if(empty($defaultProductStatus))
			ProductStatus::setDefaultProductID($this->_dbCmd, $this->_productID);
	}
	


	
	



}

?>
