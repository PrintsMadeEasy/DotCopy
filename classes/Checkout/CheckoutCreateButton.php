<?php

class CheckoutCreateButton {
	
	private $merchantId  	= "";
	private $merchantKey 	= "";
	private $serverType  	= "";
	private $calculationUrl = "";
	
	function __construct() {
	
		if(Constants::GetDevelopmentServer()) {
			$this->merchantId  = "761791181223226";
			$this->merchantKey = "btJ-fAs7ZUAqTdlCXzIZRg";
			$this->serverType  = "sandbox";
			$this->calculationUrl = "http://www.asynx-planetarium.com/demo/server_googlecheckout_callback.php";
			
		} else {
			$this->merchantId  = "";
			$this->merchantKey = "";
			$this->serverType  = "liveserver";
			$this->calculationUrl = "http://www.printsmadeeasy.com/Callback/GoogleCheckout/server_googlecheckout_callback.php";
		}
	}

  	public function createOrderButton() {
   	
		$cart = new GoogleCart($this->merchantId, $this->merchantKey, $this->serverType); 

	    /*
			Here we have to query the order table for projects in the order and add it one by one to the button.    
	    	We have to assign an unique Id for every item, this will be used to mark the postion as shipped/canceled/returned later. 
			The project number is needed to link the new order finalized on the GC screen. The button just contains the items and shipping options.

			In the callback function the final new order is stored to our database together with detailed address, googleorder#, selected shipping and coupon.
			The programmed callback event is called when the buyer orders in GC, there the projectNumber is the reference to our system.
	    */
    
	    // TODO Loop trough all items in the order
	    
	    $uniqueItemId = 2000010; // Used to identify item shipped in ship line item, must be unique in order
	    $projectNumber = "P1555255"; // To link
	    $item = new GoogleItem("BUCA2GL10", "Bussinesscards Fall 500", 2, 26.45);
	    $item->SetMerchantPrivateItemData($projectNumber);
	    $item->SetMerchantItemId($uniqueItemId);
	    $cart->AddItem($item);
	
	    $uniqueItemId = 2000011; // Used to identify item shipped in ship line item, must be unique in order
	    $projectNumber = "P1555258";
	    $item = new GoogleItem("PMEBC5", "Bussinesscards glossy 1000", 1, 78.45);
	    $item->SetMerchantPrivateItemData($projectNumber);
	    $item->SetMerchantItemId($uniqueItemId);
	    $cart->AddItem($item);
	    
	    // To keep all in sync, $merchantTaxCalc changes paramter on all locations at once
	    $merchantTaxCalc = "true";
	    
	    $cart->SetMerchantCalculations(
	        $this->calculationUrl,	 	// merchant-calculations-url
	        $merchantTaxCalc, 			// merchant-calculated tax
	        "true", 					// accept-merchant-coupons
	        "false"); 					// accept-merchant-gift-certificates
	 
	    if($merchantTaxCalc=="true") {
	    	// Taxes are calculated in our callback routine
	    	$tax_rule = new GoogleDefaultTaxRule(0.0); 
	    	$cart->AddDefaultTaxRules($tax_rule);
	    } else {
	    	
	    	$tax_rule = new GoogleDefaultTaxRule(0.15);  // Change tax if needed
	    	$tax_rule->SetWorldArea(true);
	    	// No tax on shipping cost OR use   $tax_rule->shipping_taxed(true);
	    	$cart->AddDefaultTaxRules($tax_rule);
	    }
	
	    // Global setting for the order
	    $restriction = new GoogleShippingFilters();
	    $restriction->AddAllowedPostalArea("US");
	    $restriction->SetAllowUsPoBox(false);
	    
	    $address_filter = new GoogleShippingFilters();
	    $address_filter->AddAllowedPostalArea("US");
	    $address_filter->SetAllowUsPoBox(false);
	    
	    
	    // TODO: Take shipping price from central database, must be in sync with callback prices.
	    // This apears in the GC shipping list selection
	    // Loop all shipping options
	    
	    $ship = new GoogleMerchantCalculatedShipping("2nd Day Air", 10.00); // Shippping method // Default, fallback price
	    
	    $ship->AddShippingRestrictions($restriction);
	    $ship->AddAddressFilters($address_filter);
	    $cart->AddShipping($ship);
	  
	    $ship2 = new GoogleMerchantCalculatedShipping("10 Day Truck", 3.00); // Shippping method // Default, fallback price
		
	    $ship2->AddShippingRestrictions($restriction);
	    $ship2->AddAddressFilters($address_filter);
	    $cart->AddShipping($ship2);
	    
	    $ship3 = new GoogleMerchantCalculatedShipping("5 Day Truck", 5.00); // Shippping method // Default, fallback price
	    
	    $ship3->AddShippingRestrictions($restriction);
	    $ship3->AddAddressFilters($address_filter);
	    $cart->AddShipping($ship3);
	    
	  
	    $cart->AddRoundingPolicy("UP", "TOTAL");
	    
	    /*   
	      For U.S. merchants, Google Checkout uses banker's rounding as its default policy. Banker's rounding is the same as the traditional method of rounding numbers with one exception. In banker's rounding, if the number to be rounded is followed by a five and no additional nonzero digits, the number is rounded to the nearest even number. The following examples demonstrate the expected behavior in banker's rounding:
	
	          o 12.435 would be rounded up to 12.44. The number to be rounded (3) is rounded to the nearest even digit (4).
	          o 12.445 would be rounded down to 12.44. The number to be rounded (4) is not rounded because it is an even digit.
	          o 12.44501 would be rounded up to 12.45. The number to be rounded (4) is followed by a five and by additional nonzero digits.
	
	      For U.S. merchants, Google calculates the total amount of tax for all of the items in an order and then uses banker's rounding to round that amount to two decimal places.
	   
	      For U.K. merchants, Google Checkout uses the HALF_UP rounding mode. For this rounding mode, if the number being rounded is followed by a five and no additional nonzero digits, then that number is rounded up. All other numbers are rounded to the nearest digit. The following examples demonstrate the expected behavior for HALF_UP rounding:
	
	          o 12.434 would be rounded down to 12.43.
	          o 12.435 would be rounded up to 12.44.
	          o 12.445 would be rounded up to 12.45.
	          o 12.456 would be rounded up to 12.46.
	
	      For U.K. merchants, Google calculates tax for each item in the order and then uses HALF_UP rounding for each calculated amount.
	     
	     */
	   
	    // Return Button HTML
	    return $cart->CheckoutButtonCode("SMALL", true);
  	}
}

?>