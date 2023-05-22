<?
// Rack Control is for a Specific Domain.
// Products go off of the Production Piggyback Product ID
// So if you are producing products for 100 different domains it is possible to only use 1 Rack 
// You just have to set all of the Production Piggyback Product ID's so that they point to the same domain.
// Spaces in the rack are occupied by an order number.  So you may have multiple different Products in the same rack location for an order, even if they don't all ship at the same time.

class RackControl {

	private $_domainID;
	private $_dbCmd;
	private $_rackCount;
	private $_rowCount;
	private $_columnCount;
	private $_domainIsConfigured;
	
	private $_rackTranslationArr = array();
	private $_rowTranslationArr = array();
	private $_columnTranslationArr = array();
	

	// The All orders and Project must have Products inside which have the "Production Piggy Back ID" belonging to the Domain passed into the constructor.
	function __construct(DbCmd $dbCmd, $domainID){

		$this->_dbCmd = $dbCmd;
		
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
	
		if(!$passiveAuthObj->CheckIfUserCanViewDomainID($domainID))
			$this->_ErrorMessage("Problem with Rack control. There is no rack available for this domain.");
		
		$this->_domainID = $domainID;		
		
		// Get the Rack(s) configuration for this Domain
		$this->_dbCmd->Query( "SELECT * FROM productionsetup WHERE DomainID=" . $this->_domainID );
		if($this->_dbCmd->GetNumRows() == 0){
			$this->_domainIsConfigured = false;
			return;
		}
		else{
			$this->_domainIsConfigured = true;
		}
		
		
			
		$ResultRow = $this->_dbCmd->GetRow();
		
		$this->_rackCount = $ResultRow["RacksAmount"];
		$this->_rowCount = $ResultRow["RowsPerRack"];	
		$this->_columnCount = $ResultRow["ColumnsPerRack"];
		
		
		if(!isset($ResultRow["RackTranslations"]))
			$ResultRow["RackTranslations"] = "";
		if(!isset($ResultRow["RowTranslations"]))
			$ResultRow["RowTranslations"] = "";
		if(!isset($ResultRow["ColumnTranslations"]))
			$ResultRow["ColumnTranslations"] = "";
		
		$this->_rackTranslationArr = $this->_ConvertPipesToArrayElements($ResultRow["RackTranslations"]);
		$this->_rowTranslationArr = $this->_ConvertPipesToArrayElements($ResultRow["RowTranslations"]);
		$this->_columnTranslationArr = $this->_ConvertPipesToArrayElements($ResultRow["ColumnTranslations"]);
		
	}
	
	// In case the user has forgot to save the paramters for the domain.
	function checkIfDomainIsConfiguredForRackControl(){
		return $this->_domainIsConfigured;
	}
	

	function PutProjectOnRack($Rack, $Row, $Column, $ProjectID){
	
		$this->_ensureDomainIsConfiguredForRackControl();
		
		$this->_ValidateDigit(array($Rack, $Row, $Column, $ProjectID));
		
		if($Rack > $this->_rackCount || $Rack <= 0)
			$this->_ErrorMessage("Rack #".$Rack."  does not exist");
		if($Row > $this->_rowCount || $Row <= 0)
			$this->_ErrorMessage("Row #".$Row."  does not exist");
		if($Column > $this->_columnCount || $Column <= 0)
			$this->_ErrorMessage("Column #".$Column."  does not exist");	
		
		// Make sure that the Project does not exist on the rack already
		if($this->CheckIfProjectsIsOnRack($ProjectID) <> 0)
			$this->_ErrorMessage("P".$ProjectID."  is already on a rack.");
		
		$this->ValidateProjectID($ProjectID);
		
		$ThisOrderID = Order::GetOrderIDfromProjectID($this->_dbCmd, $ProjectID);
		
		// Make sure that this slot is free... or if it is not free, that it belongs to the same order
		$OrderIDatPosition = $this->GetOrderIDbyPosition($Rack, $Row, $Column);
		
		if($OrderIDatPosition != $ThisOrderID && $OrderIDatPosition != 0)
			$this->_ErrorMessage("The space is already occupied by another project. Rack=$Rack Row=$Row Column=$Column");
			

		// Now we can insert the project into the DB
		$InsertArr["DomainID"] = $this->_domainID;
		$InsertArr["Rack"] = $Rack;
		$InsertArr["RowNum"] = $Row;
		$InsertArr["ColumnNum"] = $Column;
		$InsertArr["ProjectID"] = $ProjectID;
		$InsertArr["OrderID"] = $ThisOrderID;
		$this->_dbCmd->InsertQuery("productionracks", $InsertArr);
	}
	


	public function ValidateProjectID($ProjectID){
	
		$this->_ValidateDigit($ProjectID);
	
		$productIDofProject = ProjectBase::getProductIDquick($this->_dbCmd, "ordered", $ProjectID);
		$productionPiggyBackID  = Product::getProductionProductIDStatic($this->_dbCmd, $productIDofProject);
		$domainIDofProductionPiggyBack = Product::getDomainIDfromProductID($this->_dbCmd, $productionPiggyBackID);

		if( $domainIDofProductionPiggyBack != $this->_domainID){
			$this->_ErrorMessage("P".$ProjectID."  is a from a different Domain.");
		}
	}
	
	// This does not make sure that the Order ID is in the same domain... because the order could have a Project insdie that has a production piggyback pointing to another domain.
	// Intead it makes sure that they is at least one Product inside of the order in the within the Domain Pool of Production piggybacks.
	public function ValidateOrderID($orderID){
	
		$this->_ValidateDigit($orderID);
		$this->_dbCmd->Query( "SELECT DISTINCT ProductID FROM orders 
						INNER JOIN projectsordered ON projectsordered.OrderID = orders.ID WHERE orders.ID=" . $orderID );
		
		if( $this->_dbCmd->GetNumRows() == 0 )
			$this->_ErrorMessage("Order Number: ".$orderID."  does not exist.");
			
		$productIDsArr = $this->_dbCmd->GetValueArr();
		
		$domainIDFoundFlag = false;
		
		foreach($productIDsArr as $thisProductID){
			$productionProductID = Product::getProductionProductIDStatic($this->_dbCmd, $thisProductID);
			$domainIDofProductionPiggyBack = Product::getDomainIDfromProductID($this->_dbCmd, $productionProductID);
			
			if($domainIDofProductionPiggyBack == $this->_domainID){
				$domainIDFoundFlag = true;
				break;
			}
		}
			
		if(!$domainIDFoundFlag)
			$this->_ErrorMessage("Order Number: ".$orderID."  is a from a different Domain.");
	}
	
	// Makes sure that th 
	public function ValidateProductID($productID){
	
		$this->_ValidateDigit($productID);
		
		$productionProductID = Product::getProductionProductIDStatic($this->_dbCmd, $productID);
		
		$domainIDofProductionProductID = Product::getDomainIDfromProductID($this->_dbCmd, $productionProductID);
		
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		
		if(!$passiveAuthObj->CheckIfUserCanViewDomainID($domainIDofProductionProductID))
			$this->_ErrorMessage("The Product ID does not exist for Rack Control.");
			
		if($domainIDofProductionProductID != $this->_domainID)
			$this->_ErrorMessage("The Product ID has been swtiched into another domain for Rack Control.");
	
	}
	
	
	// If there is only 1 project in an order, then calling this function would always return true
	// However, if multiple projects are in this order... the function will only return true if the project coming through is the ONLY one that is NOT on the rack.
	function CheckIfProjectIsLastInOrderForProductGroup($ProjectID){

		$this->ValidateProjectID($ProjectID);
		
		$productIDofProject = ProjectBase::GetProductIDFromProjectRecord($this->_dbCmd, $ProjectID, "ordered");
		
		$orderID = Order::GetOrderIDfromProjectID($this->_dbCmd, $ProjectID);
		
		$allProductIDsInProductionPool = Product::getAllProductIDsSharedForProduction($this->_dbCmd, $productIDofProject);
		
		// Get a list of all ProjectIDs in this order that are in the same Production Pool for products.
		$this->_dbCmd->Query("SELECT ID FROM projectsordered WHERE OrderID=$orderID AND " . DbHelper::getOrClauseFromArray("ProductID", $allProductIDsInProductionPool));
		$projectIDsInSameProductionPool = $this->_dbCmd->GetValueArr();
		
		// Now find out how many projects are on the rack.... not counting the current one
		$this->_dbCmd->Query( "SELECT COUNT(*) FROM productionracks WHERE DomainID=" . $this->_domainID . "
					AND ProjectID !=  $ProjectID
					AND ".DbHelper::getOrClauseFromArray("ProjectID", $projectIDsInSameProductionPool)."
					AND OrderID=" . $orderID );
		$ProjectsLeftOnRack = $this->_dbCmd->GetValue();
		
		
		if(($ProjectsLeftOnRack + 1) == $this->GetTotalProjectsInOrderForProductGroup($orderID, $productIDofProject))
			return true;
		else
			return false;
	}
	
	// First find out how many projects exist in this order that have not been canceled or finished already
	function GetTotalProjectsInOrder($orderID){

		$this->ValidateOrderID($orderID);

		$this->_dbCmd->Query( "SELECT COUNT(*) FROM projectsordered WHERE Status != 'F' AND Status != 'C' 
					AND OrderID=" . intval($orderID) );
		return $this->_dbCmd->GetValue();
	}

	// First find out how many projects exist in this order that have not been canceled or finished already
	function GetTotalProjectsInOrderForProductGroup ($orderID, $productID){

		$this->ValidateOrderID($orderID);
		$this->ValidateProductID($productID);
		
		$allProductIDsInProductionPool = Product::getAllProductIDsSharedForProduction($this->_dbCmd, $productID);

		// Get a list of all ProjectIDs in this order that are in the same Production Pool for products.
		$this->_dbCmd->Query("SELECT COUNT(*) FROM projectsordered WHERE OrderID=$orderID  AND Status != 'F' AND Status != 'C' AND " . DbHelper::getOrClauseFromArray("ProductID", $allProductIDsInProductionPool));
		return  $this->_dbCmd->GetValue();

	}
	
	// Returns an array of project IDs in the same order having a matching ProductID
	function GetProjectIDsInOrderWithSameProduct($orderID, $productID){
		
		$this->ValidateOrderID($orderID);
		$this->ValidateProductID($productID);
	
		$allProductsSharingProductionProductID = Product::getAllProductIDsSharedForProduction($this->_dbCmd, $productID);
		
		$retArr = array();
		$this->_dbCmd->Query( "SELECT ID FROM projectsordered WHERE Status != 'F' AND Status != 'C' 
					AND OrderID=" . intval($orderID) . " AND " . DbHelper::getOrClauseFromArray("ProductID", $allProductsSharingProductionProductID) );
		while($ThisID = $this->_dbCmd->GetValue())
			$retArr[] = $ThisID;
		
		return $retArr;
	}


	// Find out how many boxes are needed for this project  
	// For example, if the Max Box Size is set to 500... and somebody orders 1200 cards, then "3" boxes would be returned from this function.
	// It doesn't matter what is stored on the Product Definition.  Only what is stored on the Production Piggy Back.
	function GetBoxesInProject($ProjectID){

		$this->ValidateProjectID($ProjectID);

		$this->_dbCmd->Query( "SELECT Quantity, ProductID FROM projectsordered WHERE ID=$ProjectID" );
		$row = $this->_dbCmd->GetRow();
		$Quantity = $row["Quantity"];
		$ProductID = $row["ProductID"];
		
		$productObj = Product::getProductObj($this->_dbCmd, $ProductID);
		$this->_maxBoxSize = $productObj->getMaxBoxSize();
		
		if(empty($this->_maxBoxSize))
			return 1;
		else
			return ceil($Quantity/ $this->_maxBoxSize);
	}

	// Find out how many total boxes are in this order
	// Pass in a Product ID, it will tell you how many total boxes there are within the Order with the same Product ID.
	function GetBoxesInOrderForProductGroup($orderID, $productID){
		
		$this->ValidateOrderID($orderID);
		$this->ValidateProductID($productID);
		
		$ProjectIDArr = $this->GetProjectIDsInOrderWithSameProduct($orderID, $productID);
		
		$TotalBoxes = 0;

		foreach($ProjectIDArr as $thisProjectID)
			$TotalBoxes += $this->GetBoxesInProject($thisProjectID);
		
		return $TotalBoxes;
	}
	
	function GetBoxesOnRackFromOrder($orderID){
		
		$this->ValidateOrderID($orderID);
		
		$ProjectIDArr = $this->GetProjectsOnRackFromOrder($orderID);
		
		$TotalBoxes = 0;
		
		foreach($ProjectIDArr as $thisProjectID)
			$TotalBoxes += $this->GetBoxesInProject($thisProjectID);
		
		return $TotalBoxes;
	}

	function GetBoxesOnRackFromOrderForProductGroup($orderID, $productID){

		$this->ValidateOrderID($orderID);
		$this->ValidateProductID($productID);
		
		$ProjectIDArr = $this->GetProjectsOnRackFromOrderForProductGroup($orderID, $productID);
		
		$TotalBoxes = 0;
		
		foreach($ProjectIDArr as $thisProjectID)
			$TotalBoxes += $this->GetBoxesInProject($thisProjectID);
		
		return $TotalBoxes;
	}
	

	// Returns the OrderID that is in the given position.
	// Returns 0 if empty
	function GetOrderIDbyPosition($Rack, $Row, $Column){
	
		$this->_ensureDomainIsConfiguredForRackControl();
	
		$this->_dbCmd->Query( "SELECT DISTINCT OrderID FROM productionracks 
					WHERE Rack=$Rack AND RowNum=$Row AND ColumnNum=$Column AND DomainID=" . $this->_domainID );

		if($this->_dbCmd->GetNumRows() == 0)
			return 0;
		else
			return $this->_dbCmd->GetValue();
	}


	// Will Check for an open slot on the rack
	// returns false if all spots are full
	function CheckForOpenSlot(){
	
		$this->_ensureDomainIsConfiguredForRackControl();
	
		// Basically, 1 order is allowed to fill each slot on a rack.
		$totalSlots = ($this->_rackCount * $this->_rowCount * $this->_columnCount);
		
		$this->_dbCmd->Query( "SELECT DISTINCT OrderID FROM productionracks WHERE DomainID=" . $this->_domainID );
		if($this->_dbCmd->GetNumRows() < $totalSlots)
			return true;
		else
			return false;
	}

	// Returns true if it finds the project on a rack, false otherwise.
	function CheckIfProjectsIsOnRack($ProjectID){
	
		$this->_ensureDomainIsConfiguredForRackControl();
	
		$this->ValidateProjectID($ProjectID);
	
		$this->_dbCmd->Query( "SELECT COUNT(*) FROM productionracks WHERE ProjectID=" . $ProjectID . " AND DomainID=" . $this->_domainID  );
		if($this->_dbCmd->GetValue() == 0)
			return false;
		else
			return true;
	}

	// Pass in a OrderID
	// Return True if another project from the same order already exists on the rack.
	// False if this is the first project of the order.
	function CheckForOrderOnRack($orderID){
	
		$this->_ensureDomainIsConfiguredForRackControl();

		$this->ValidateOrderID($orderID);

		$this->_dbCmd->Query( "SELECT COUNT(*) FROM productionracks WHERE OrderID=" . intval($orderID) . " AND DomainID=" . $this->_domainID  );
		if($this->_dbCmd->GetValue() == 0)
			return false;
		else
			return true;
	}
	
	
	// Similar to CheckForOrderOnRack... except narrows the search to the Production Product ID.
	// It doesn't matter if you pass in the Production Piggyback ProductID... It will Derive it.
	function CheckForOrderOnRackForProductGroup($orderID, $productID){
	
		$this->_ensureDomainIsConfiguredForRackControl();

		$this->ValidateProductID($productID);
		$this->ValidateOrderID($orderID);
		
		$projectIDsInSameProductionPool = $this->GetProjectIDsInOrderWithSameProduct($orderID, $productID);

		$this->_dbCmd->Query( "SELECT COUNT(*) FROM productionracks WHERE 
								OrderID=" . intval($orderID) . " AND DomainID=" . $this->_domainID . " 
								AND " . DbHelper::getOrClauseFromArray("ProjectID", $projectIDsInSameProductionPool) );
		if($this->_dbCmd->GetValue() == 0)
			return false;
		else
			return true;
	}

	// Pass in a project ID
	// Return The number or projects sitting on a rack within an order
	function GetCountOfProjectsOnRackFromOrder($orderID){

		$this->ValidateOrderID($orderID);
		
		$ProjectArr = $this->GetProjectsOnRackFromOrder($orderID);

		return sizeof($ProjectArr);
	}
	
	
	
	function GetCountOfProjectsOnRackFromOrderForProductGroup($orderID, $productID){

		$this->ValidateOrderID($orderID);
		$this->ValidateProductID($productID);
		
		$ProjectArr = $this->GetProjectsOnRackFromOrderForProductGroup($orderID, $productID);

		return sizeof($ProjectArr);
	}

	// Returns an array of ProjectID's that are on the rack
	function GetProjectsOnRackFromOrder($orderID){
	
		$this->_ensureDomainIsConfiguredForRackControl();

		$this->ValidateOrderID($orderID);

		$retArr = array();
		
		$this->_dbCmd->Query( "SELECT ProjectID FROM productionracks WHERE OrderID=" . intval($orderID) . " AND DomainID=" . $this->_domainID  );
		while($ThisID = $this->_dbCmd->GetValue())
			$retArr[] = $ThisID;
		
		return $retArr;
	}
	

	
	function GetProjectsOnRackFromOrderForProductGroup($orderID, $productID){
	
		$this->_ensureDomainIsConfiguredForRackControl();
		$this->ValidateProductID($productID);
		$this->ValidateOrderID($orderID);

		$projectIDsInSameProductionPool = $this->GetProjectIDsInOrderWithSameProduct($orderID, $productID);

		$retArr = array();
		
		if(!empty($projectIDsInSameProductionPool)){
			$this->_dbCmd->Query( "SELECT ProjectID FROM productionracks WHERE 
							OrderID=" . intval($orderID) . " AND DomainID=" . $this->_domainID . " AND 
							" . DbHelper::getOrClauseFromArray("ProjectID", $projectIDsInSameProductionPool)  );
		}

		$retArr = $this->_dbCmd->GetValueArr();
		
		return $retArr;
	}
	
	
	// Pass in an OrderID ID
	// Returns a hash with 6 keys... 
	// First 3 are the "Rack", "Row" and "Column" that the order is positioned at.
	// Second 3 are "RackTranslated", "RowTranslated" and "ColumnTranslated".  This is in case we want to tranlate ordinary numbers into something more disnctive.
	// If we can't find a translation for the Rack/row/Column ... then it returns a blank string instead
	// The calling program should  CheckForOrderOnRack  ... beforing calling this function, otherwise it could cause an error
	function GetPositionOfOrderOnRack($orderID){
	
		$this->_ensureDomainIsConfiguredForRackControl();

		$this->ValidateOrderID($orderID);

		$this->_dbCmd->Query( "SELECT * FROM productionracks WHERE OrderID=" . intval($orderID) . " AND DomainID=" . $this->_domainID . " LIMIT 1" );
		if($this->_dbCmd->GetNumRows() == 0)
			$this->_ErrorMessage("Error in method call to GetPositionOfOrderOnRack");
		
		$ResultRow = $this->_dbCmd->GetRow();

		
		return array(
			"Rack"=>$ResultRow["Rack"], 
			"Row"=>$ResultRow["RowNum"], 
			"Column"=>$ResultRow["ColumnNum"],
			"RackTranslate"=>$this->TranslateRack($ResultRow["Rack"]),
			"RowTranslate"=>$this->TranslateRow($ResultRow["RowNum"]),
			"ColumnTranslate"=>$this->TranslateColumn($ResultRow["ColumnNum"])
			);
	}
	
	// Translate the Rack, Rows, and Columns into somthing more meaningful... if we can
	// If if a row translation is not set... then just make the rack/row/column text the number
	function TranslateRack($RackNum){
		if(isset($this->_rackTranslationArr[$RackNum-1]) && !empty($this->_rackTranslationArr[$RackNum-1]))
			return $this->_rackTranslationArr[$RackNum -1];
		else
			return "RACK";
	}
	function TranslateRow($RowNum){
		if(isset($this->_rowTranslationArr[$RowNum-1]) && !empty($this->_rowTranslationArr[$RowNum-1]))
			return $this->_rowTranslationArr[$RowNum -1];
		else
			return $RowNum;
	}
	function TranslateColumn($ColumnNum){
		
		// If there isn't a column translation defined... make a default translation... like 1=A, 2=B, 3=C .. etc.  Kind of like Microsoft Excel
		if(isset($this->_columnTranslationArr[$ColumnNum-1]) && !empty($this->_columnTranslationArr[$ColumnNum-1])){
			return $this->_columnTranslationArr[$ColumnNum -1];
		}
		else{
			if($ColumnNum < 28)
				return chr(64 + $ColumnNum);
			else
				return $ColumnNum;
		}
	}
	
	// Will report an error if all slots are full.  The calling program should check for open slots before calling this method
	// Returns a hash with 3 keys.  "Rack", "Row", "Column"
	function GetNextSlotOpening(){
		
		// Loop through the Matrix of Racks/Rows/Columns ... looking for open slots
		for($i=1; $i<=$this->_rackCount; $i++){
		
			for($j=1; $j<=$this->_rowCount; $j++){

				for($x=1; $x<=$this->_columnCount; $x++){
				
					if($this->GetOrderIDbyPosition($i, $j, $x) == 0)
						return array("Rack"=>$i, "Row"=>$j, "Column"=>$x);

				}
			}
		}
		
		$this->_ErrorMessage("Calling function GetNextSlotOpening when there are no open slots");
		exit;
	}


	function RemoveProjectFromRack($ProjectID){
		$this->_ensureDomainIsConfiguredForRackControl();
		
		$this->ValidateProjectID($ProjectID);
		
		$this->_dbCmd->Query("DELETE FROM productionracks WHERE ProjectID=$ProjectID AND DomainID=" . $this->_domainID);
	}
	
	function RemoveOrderFromRackForProductGroup($orderID, $productID){
		
		$this->_ensureDomainIsConfiguredForRackControl();
		
		$this->ValidateOrderID($orderID);
		$this->ValidateProductID($productID);
		
		$projectIDsInSameProductionPool = $this->GetProjectIDsInOrderWithSameProduct($orderID, $productID);
		
		$this->_dbCmd->Query("DELETE FROM productionracks WHERE OrderID=$orderID AND DomainID=" . $this->_domainID . "
								AND " . DbHelper::getOrClauseFromArray("ProjectID", $projectIDsInSameProductionPool));
	}

	
	
	##---- Private Methods below   --##


	
	private function _ensureDomainIsConfiguredForRackControl(){
		if(!$this->_domainIsConfigured)
			$this->_ErrorMessage("This Domain has not been configured for Rack Control yet.");
	}
	
	
	private function _ErrorMessage($Msg){
		throw new Exception($Msg);
	}

	// Takes a digit or an array or digits
	private function _ValidateDigit($Num){
	
		if(!is_array($Num))
			$Num = array($Num);
	
		foreach($Num as $ThisNum)
			if(!preg_match("/^\d{1,11}$/", $ThisNum))
				$this->_ErrorMessage("Value is not a digit.");
	}


	// Takes a string delimited with pipe symbols and returns an array with each element in its own node
	private function _ConvertPipesToArrayElements($str){

		$retArr = "";
		$tempArray = split("\|", $str);

		foreach($tempArray as $ThisRow){
			if(!preg_match("/^\s*$/", $ThisRow))
				$retArr[] = trim($ThisRow);
		}

		return $retArr;
	}	




}



?>