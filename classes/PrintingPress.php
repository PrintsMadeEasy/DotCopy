<?

// This class is meant to organize multiple PDF profiles and plot them together on a single master PDF profile.
// 

class PrintingPress {


	private $printingPressName;
	private $printingPressType;
	private $printingPressID;

	

	##--------------   Constructor  ------------------##

	function PrintingPress($printingPressID){

		$this->_printingPressID = $printingPressID;
		
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		$domainIDarr = $passiveAuthObj->getUserDomainsIDs();
		
		$printingPressDefinitionsArr = PrintingPress::_printingPressDefinitions($domainIDarr);
		
		if(!isset($printingPressDefinitionsArr[$printingPressID]))
			throw new Exception("Illegal Printing Press ID in contructor for PrintingPress");
		
				
		$this->_printingPressName = $printingPressDefinitionsArr[$printingPressID]["PressName"];
		$this->_printingPressType = $printingPressDefinitionsArr[$printingPressID]["PressType"];
	}
	
	
	protected static function _printingPressDefinitions($dominIDsArr){
	
		if(!is_array($dominIDsArr))
			$dominIDsArr = array($dominIDsArr);
			
		$allPrintingPressIDsArr = array();
		
		foreach($dominIDsArr as $thisDomainID){
			if(!Domain::checkIfDomainIDexists($thisDomainID))
				throw new Exception("Error with Domain in _printingPressDefinitions");
				
			$domainEquipmentObj = new DomainEquipment($thisDomainID);
			
			$printingPressesInDomainArr = $domainEquipmentObj->printingPressesArr;
			
			
			
			$allPrintingPressIDsArr = array_merge($allPrintingPressIDsArr, $printingPressesInDomainArr);
		}
		
		$allPrintingPressIDsArr = array_unique($allPrintingPressIDsArr);

		
		$retArr = array();
		
		if(in_array(23, $allPrintingPressIDsArr)){
			$retArr["23"] = array();
			$retArr["23"]["PressName"] = "Xerox iGen3";
			$retArr["23"]["PressType"] = "D";
		}
		
		if(in_array(24, $allPrintingPressIDsArr)){
			$retArr["24"] = array();
			$retArr["24"]["PressName"] = "Kodak DI";
			$retArr["24"]["PressType"] = "DO";
		}
	
		return $retArr;
	}
	
	
	static function checkIfPrintingPressIDExists($pressID){
	
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		$domainIDarr = $passiveAuthObj->getUserDomainsIDs();
		
		$printingPressDefinitionsArr = PrintingPress::_printingPressDefinitions($domainIDarr);
		
		if(!isset($printingPressDefinitionsArr[$pressID]))
			return false;
		else
			return true;
	}
	
	
	// Returns a list of printing Presses.  The key is the ID and the value is the Printer Name.
	static function getPrintingPressList($dominIDsArr){

		$printingPressDefinitionsArr = PrintingPress::_printingPressDefinitions($dominIDsArr);
		
		$retArr = array();
	
		foreach($printingPressDefinitionsArr as $printerID => $printerDetail)
			$retArr[$printerID] = $printerDetail["PressName"];
		
		return $retArr;
	}
	
	
	static function getPrintingPressNameQuick($printerID){
	
		$printPressObj = new PrintingPress($printerID);
		
		return $printPressObj->getPrintingPressName();
	}
	

	static function getPrintingPressTypeDesc($pressType){
	
		if($pressType == "D")
			return "Digital";
		else if($pressType == "DO")
			return "Digital Offset";
		else if($pressType == "O")
			return "Offset";
		else
			throw new Exception("Error with printing press type in method call getPrintingPressTypeDesc().");
	}
	

	public function getPrintingPressName(){
	
		return $this->_printingPressName;
	}
	
	public function getPrintingPressType(){
	
		return $this->_printingPressType;
	}
	


}





?>