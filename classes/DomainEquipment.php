<?php


class DomainEquipment {
	

	// 23=Igen, 24=KodakDI
	public $printingPressesArr = array();

	
	
	public function __construct($domainID){
		
		if(!Domain::checkIfDomainIDexists($domainID))
			throw new Exception("Error in Domain Equipment Configuration. The Domain ID does not exist.");
			
		$domainKey = Domain::getDomainKeyFromID($domainID);
		
		
		// Right now DotCopyShop.com is handeling all of the Production.
		if($domainKey == "DotCopyShop.com"){
			
			$this->printingPressesArr = array(23, 24);
		}
		else{
			$this->printingPressesArr = array();
		}
		
	}
	
}

