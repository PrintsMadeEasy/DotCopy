<?


class ShippingPrices {

	private $_shippingChoiceID;

	private $shippingChoiceObj;
	private $shippingMethodsObj;
	
	private $shipFromAddressObj;
	private $shipToAddressObj;

	private $_dbCmd;


	function __construct(){
		
		$this->shippingChoiceObj = new ShippingChoices();
		$this->shippingMethodsObj = new ShippingMethods();
		
		$this->_dbCmd = new DbCmd();
	}
	
	
	function setShippingAddress(MailingAddress $shipFromAddress, MailingAddress $shipToAddress){
		
		if(!$shipFromAddress->addressInitialized() || !$shipToAddress->addressInitialized())
			throw new Exception("Error in ShippingPrices::setShippingAddress. The address hasn't been initialized.");
	
		$this->shipFromAddressObj = $shipFromAddress;
		$this->shipToAddressObj = $shipToAddress;
	}
	
	
	// Use a "Project Info" object to calculate the weight of an object based upon quantity/options/choices.
	function getShippingPriceForCustomer($productID, $shippingChoiceID, $weight){
	
		if(empty($this->shipFromAddressObj) || empty($this->shipToAddressObj))
			throw new Exception("Error in method getShippingPriceForCustomer. The address has not been set.");
			
		if(Product::checkIfProductHasMailingServices($this->_dbCmd, $productID))
			return 0;
		
		$weight = ceil($weight);
			
		$basePrice = $this->shippingChoiceObj->getBasePrice($shippingChoiceID, $productID);
		
		if($this->shippingMethodsObj->isDestinationRural($this->shipToAddressObj))
			$basePrice += $this->shippingChoiceObj->getRuralFee($shippingChoiceID);
			
		if($this->shippingMethodsObj->isDestinationExtended($this->shipFromAddressObj, $this->shipToAddressObj))
			$basePrice += $this->shippingChoiceObj->getExtendedDistanceFee($shippingChoiceID);
			
		$pricePerPound = $this->shippingChoiceObj->getPricePerPound($shippingChoiceID, $weight);
		
		$retPrice = round(($pricePerPound * $weight + $basePrice), 2);
		$retPrice = number_format($retPrice, 2, ".", "");
		
		return $retPrice;
		
	}


	// Will return the amount that the customer is quoted to pay for shipping
	// Projects can either belong to an order or the shopping cart.  So the possible $GroupReference is an OrderID or users SessionID
	// Every group of products goes into their own shipments
	// Pass in a ProductID of 0 if you want a quote on the whole shipment.   A product ID will return the shipping total for that Product Only.
	static function getTotalShippingPriceForGroup($GroupReference, $GroupType, $shippingChoiceID, MailingAddress $shipToAddressObj){
		
		$dbCmd = new DbCmd();
		
		$productIDarr =  ProjectGroup::GetProductIDlistFromGroup($dbCmd, $GroupReference, $GroupType);
		
		$shippingPricesObj = new ShippingPrices();
		
		// The Shipping Price is always calculated from the Default Production Facility Shipping Address (from the domain).
		$domainAddressObj = new DomainAddresses(Domain::oneDomain());
		$shippingPricesObj->setShippingAddress($domainAddressObj->getDefaultProductionFacilityAddressObj(), $shipToAddressObj);

		$totalShippingQuote = 0;
	
		foreach($productIDarr as $thisProdID){
	
			$productWeightInGroup = ProjectGroup::GetDetailsOfProductInGroup($dbCmd, "CustomerWeight", $GroupReference, $thisProdID, $GroupType);
			
			$totalShippingQuote += $shippingPricesObj->getShippingPriceForCustomer($thisProdID, $shippingChoiceID, $productWeightInGroup);
		}
		
		$totalShippingQuote = number_format($totalShippingQuote, 2, '.', '');
		
		return $totalShippingQuote;
	}
	
	
}
