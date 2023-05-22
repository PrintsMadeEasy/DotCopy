<?php

class CheckoutCalcCallback {

	private $googleResponse;
	private $googleRequest;
	private $xml_response;
	private $projectArr;
	private $uniqueSerialNumber;
	private $totalItemPrice;
	private $shippingPrice;
	private $totalCoupons;
	private $currentAdressId;
	private $currentAdressCountry;
	private $currentAdressCity;
	private $currentAdressRegion;
	private $currentAdressZIP;
	
	function __construct($googleRequestObj,$googleResponseObj,$xml_response) {
	
		$this->googleResponse = $googleResponseObj;
		$this->googleRequest  = $googleRequestObj;
		$this->xml_response   = $xml_response;	
	}

  	// CALLBACK EVENTS called while user is working on GoogleCheckout Screen ///////
		
	// This event with order data is fired right after a customer clicks to the GC "submit order" button.
	private function buyerConfirmedOrder(GoogleSingleOrder $newOrder) {
		
		// TODO compare the user confirmed order with submitted to GoogleCheckout
		
//		foreach ($newOrder->itemObjArr AS $itemObj) {
			
		 	// store or compare/check values of final order with PME database
			// $itemObj->name		     
			// $itemObj->description      
			// $itemObj->quantity 	       
			// $itemObj->unityPrice 	      
			// $itemObj->id 		       
			// $itemObj->privateId   			
//		}
		
		// Get values of $newOrder class/struct like:
		// $newOrder->couponCode
		// $newOrder->orderNumber
		
		// TODO With all data avaialble here, the final order can be completed. 
		// Set shipping type and amount
		// Set Coupon Code and value
		// Store Google Order number to order
		// Store final customer shiipng and billing address to PME
	    // etc. 
		
	}
	 	
	private function buyerCancelledWithin15Min() {

		// Event doenst work yet, I tried but couldnt get it to run

		/*
		$fp = fopen("check.txt", "w");
		fwrite($fp, "Cancel15min");
		fclose($fp);	
		*/
	}
	
	private function calcTaxWithPrice() {
		
		// TODO Detailed ZIP - Taxrate conversion.
		$stateTaxRate = 0;
		if($this->currentAdressZIP>=90000 &&  $this->currentAdressZIP<96200)
			$stateTaxRate = 8.0;
		
		$taxValue = ($this->totalItemPrice - $this->totalCoupons)/100*$stateTaxRate;
		
		return $taxValue;	
	}
	
	private function getShippingAmount($shippingName) {
		
		// TODO GetPrice for selected shipping option from central shipping source. This must be in sync with SENT shipping options.
		
		$price = 0;	     
		// Example for test       
		if($shippingName=="2nd Day Air")  $price = 10; 
		if($shippingName=="10 Day Truck") $price = 3; 
		if($shippingName=="5 Day Truck")  $price = 5; 
		
		return $price;
	}
	
	private function handleCouponDiscount($couponCode) {
	
	  	// TODO: Implement Calculation or call coupon function class here
	  	  	
		// $this->projectArr contains all Project IDs for every item of that order. With it we can link the anonymous callback to our system order and calc the coupon price !!
		
		// $this->uniqueSerialNumber identifies the UNIQUE CURRENTORDER, saving this Id can help to see if it is the same order that is edited by the user.
		
		// $this->totalItemPrice   /// $this->shippingPrice
		
		/*
		    Known Address Data for that Coupon would be, if we need it
		    $this->currentAdressId   // Unique address Id can be used to check if a cusomer already used a "first time coupon" or simular. GC handles CC check.
	        $this->currentAdressCountry 
	        $this->currentAdressCity
	        $this->currentAdressRegion
	        $this->currentAdressZIP
	       
		 */
		
		// TODO: Do something that only one Coupon can be entered. If a second is entered its invalid. Only remove first then ener new one can work.
		// Order Data callcback only reports the first and only coupon ! PME has only one coupon. It can be extended easy to allow multiple coupons if needed
		
	  	$value = 0;
	  	if($couponCode=="FREE100")    $value = 100.00;
	  	if($couponCode=="FREE25")     $value = 25.00;
	  	if($couponCode=="DUDE")       $value = 22.22;
	  	if($couponCode=="WHERES")     $value = 12.85;
	  	if($couponCode=="MYCORVETTE") $value = 16.85;

	  	if($couponCode=="DAD") $value = 36.56;
	
	  	return $value;
	}
	
	private function handleCouponDescription($couponCode) {
	  	
	  	// TODO: Implement functionality
	  	 return "Get coupon $$$ off";
	}
	  
	//////////////////////////////// LOW LEVEL PROCESSING /////////////////////////////////////////////////////////////////////////////////
	
	public function processResponse () {
	
	  list($root, $data) = $this->googleResponse->GetParsedXML($this->xml_response);
		
	  $this->projectArr = array();
	  $this->totalCoupons = 0;
	  
	  $this->uniqueSerialNumber  = $data[$root]['serial-number'];
	  
	  switch ($root) {
	  	
	    case "request-received": {
	      break;
	    }
	    case "error": {
	      break;
	    }
	    case "diagnosis": {
	      break;
	    }
	    case "checkout-redirect": {
	      break;
	    }

	    case "merchant-calculation-callback": {
	      
	      $merchant_calc = new GoogleMerchantCalculations('USD');
	      
		  $this->totalItemPrice = 0;
	
	      // Get Array with Project numbers
	      $items = $this->get_arr_result($data[$root]['shopping-cart']['items']['item']);    
	      foreach($items as $curr_item) {    

	      	   $this->projectArr[] = $curr_item['merchant-private-item-data']['VALUE'];
			   $this->totalItemPrice += $curr_item['unit-price']['VALUE'] * $curr_item['quantity']['VALUE'];
	      }		  	
	      
	      // Loop through the list of address ids from the callback
	      $addresses = $this->get_arr_result($data[$root]['calculate']['addresses']['anonymous-address']);
	      
	      foreach($addresses as $curr_address) {
	      	
	        $this->currentAdressId      = $curr_address['id'];
	        $this->currentAdressCountry = $curr_address['country-code']['VALUE'];
	        $this->currentAdressCity    = $curr_address['city']['VALUE'];
	        $this->currentAdressRegion  = $curr_address['region']['VALUE'];
	        $this->currentAdressZIP     = $curr_address['postal-code']['VALUE'];
	          
	        // Loop through each shipping method if merchant-calculated shipping
	        if(isset($data[$root]['calculate']['shipping'])) {
	          
	          $shipping = $this->get_arr_result($data[$root]['calculate']['shipping']['method']);
	          
	          foreach($shipping as $curr_ship) {
	          	
	            $this->shippingPrice = $this->getShippingAmount($curr_ship['name']);
	            
	            $shippable = "true"; // Modify this as required
	            
	            $merchant_result = new GoogleResult($this->currentAdressId);
	            $merchant_result->SetShippingDetails($curr_ship['name'], $this->shippingPrice, $shippable);
	            
	            // Coupons handling
	            // Note: Price is limited to full Product price, so if BC are $16.50 and $20 coupon then the coupon value is exactly $16.50 
				if(isset($data[$root]['calculate']['merchant-code-strings']['merchant-code-string'])) {
	            	
					$codes = $this->get_arr_result($data[$root]['calculate']['merchant-code-strings']['merchant-code-string']);

					// Limit to only one coupon per order
					$limitCoupons = 1;
					$couponCount  = 0;
					
					foreach($codes as $curr_code) {
						
						$discount = $this->handleCouponDiscount($curr_code['code']);
						$this->totalCoupons += $discount;
						
						$couponCount++;
						
						if( ($discount>0) && ($couponCount <= $limitCoupons)) {
		
							    $coupons = new GoogleCoupons("true", $curr_code['code'], $discount, $this->handleCouponDescription($curr_code['code']));
		                  		$merchant_result->AddCoupons($coupons);
		                  		
		        			} else {
		
		        				if($discount==0) $couponMessage               = "Only works with PME Checkout";
		        				if($couponCount>$limitCoupons) $couponMessage = "Only one coupon per order allowed";
		        				
		        				$coupons = new GoogleCoupons("false", $curr_code['code'], 0, $couponMessage);
		                  		$merchant_result->AddCoupons($coupons);
		        			}	   
						} 				
					}
				
					// Tax is calculated by Google, uncomment to implement PME Calc later
					if($data[$root]['calculate']['tax']['VALUE'] == "true") {
						
		                $amount  = $this->calcTaxWithPrice();
		                $merchant_result->SetTaxDetails($amount);
		            }
		
					$merchant_calc->AddResult($merchant_result);
	          	}
	          
	        } else {
	          
	         /*	
	         	
			  // Looks like we dont need that part, can be removed later

			  $merchant_result = new GoogleResult($this->currentAdressId);
	         
	          if($data[$root]['calculate']['tax']['VALUE'] == "true") {
	            //Compute tax for this address id and shipping type
	            $amount = 4.44; // Modify this to the actual tax value
	            $merchant_result->SetTaxDetails($amount);
	          }
			  
	          // Adds already entered coupons back to answer
	          $codes = $this->get_arr_result($data[$root]['calculate']['merchant-code-strings']['merchant-code-string']);
	          
	          foreach($codes as $curr_code) {
	            //Update this data as required to set whether the coupon is valid, the code and the amount
	            $coupons = new GoogleCoupons("true", $curr_code['code'], 5, "test2");
	            $merchant_result->AddCoupons($coupons);
	          }
	          $merchant_calc->AddResult($merchant_result);
	          
	          */
	        }
	      }
	      $this->googleResponse->ProcessMerchantCalculations($merchant_calc);
	      break;
	    }
	    
	    case "new-order-notification": {

	    	// if needed we can check for a successful calculation "true"
	    	// $calcSuccessful = $data[$root]['order-adjustment']['merchant-calculation-successful']['VALUE'];
		
	    	$newOrder = new GoogleSingleOrder();
	    	
	    	$shoppingCart = $this->get_arr_result($data[$root]['shopping-cart']['items']['item']);
	    	      
	    	foreach($shoppingCart as $item) {	
	    		
				$itemObj 				= new GoogleSingleItem();
				
				$itemObj->name 			= $item['item-name']['VALUE'];        
				$itemObj->description 	= $item['item-description']['VALUE'];        
				$itemObj->quantity 		= $item['quantity']['VALUE'];        
				$itemObj->unityPrice 	= $item['unit-price']['VALUE'];        
				$itemObj->id 			= $item['merchant-item-id']['VALUE'];        
				$itemObj->privateId 	= $item['merchant-private-item-data']['VALUE'];      
				  
				$newOrder->itemObjArr[] = $itemObj;
			}
		
			$newOrder->buyerId 		    	= $data[$root]['buyer-id']['VALUE'];
	    	$newOrder->orderNumber 			= $data[$root]['google-order-number']['VALUE'];
	    	$newOrder->orderTimeStamp 		= $data[$root]['timestamp']['VALUE'];
	    			    
	    	$newOrder->financiaOrderState	= $data[$root]['financial-order-state']['VALUE']; 
	    	$newOrder->fulfillmentState		= $data[$root]['fulfillment-order-state']['VALUE']; 
	    	
	    	$newOrder->orderTotalAmount		= $data[$root]['order-total']['VALUE']; 
	    	
	    	$newOrder->shippingTypeName 	= $data[$root]['order-adjustment']['shipping']['merchant-calculated-shipping-adjustment']['shipping-name']['VALUE'];
			$newOrder->shippingCost 		= $data[$root]['order-adjustment']['shipping']['merchant-calculated-shipping-adjustment']['shipping-cost']['VALUE'];
			
			$newOrder->taxAmount	    	= $data[$root]['order-adjustment']['total-tax']['VALUE'];
			$newOrder->adjustmentAmount		= $data[$root]['order-adjustment']['adjustment-total']['VALUE'];
			
			/* //Undefined index:  coupon-adjustment in <b>C:\inet\dot\classes\Checkout\CheckoutCalcCallback.php</b> on line <b>311</b><br />
			 * 
			 * // Coupons not in XML -> dont do this here
			 * 
	        $newOrder->couponCode			= $data[$root]['order-adjustment']['merchant-codes']['coupon-adjustment']['code']['VALUE']; 
			$newOrder->couponMessage		= $data[$root]['order-adjustment']['merchant-codes']['coupon-adjustment']['message']['VALUE']; 
	      	$newOrder->couponAppliedAmount	= $data[$root]['order-adjustment']['merchant-codes']['coupon-adjustment']['applied-amount']['VALUE']; 
			$newOrder->couponCalculatedAmount = $data[$root]['order-adjustment']['merchant-codes']['coupon-adjustment']['calculated-amount']['VALUE']; 
	    	*/
	    	
	    	$newOrder->marketingEmailAllowed = $data[$root]['buyer-marketing-preferences']['email-allowed']['VALUE']; 
	   
	        $newOrder->shippingCompanyName	= $data[$root]['buyer-shipping-address']['company-name']['VALUE'];
	        $newOrder->shippingContactName  = $data[$root]['buyer-shipping-address']['contact-name']['VALUE'];
	        $newOrder->shippingEmail  		= $data[$root]['buyer-shipping-address']['email']['VALUE'];
			$newOrder->shippingFirstName  	= $data[$root]['buyer-shipping-address']['structured-name']['first-name']['VALUE'];
			$newOrder->shippingLastName  	= $data[$root]['buyer-shipping-address']['structured-name']['last-name']['VALUE'];			        
			$newOrder->shippingPhone  		= $data[$root]['buyer-shipping-address']['phone']['VALUE'];
			$newOrder->shippingFax  		= $data[$root]['buyer-shipping-address']['fax']['VALUE'];
			$newOrder->shippingAddress1  	= $data[$root]['buyer-shipping-address']['address1']['VALUE'];
			$newOrder->shippingAddress2  	= $data[$root]['buyer-shipping-address']['address1']['VALUE'];
			$newOrder->shippingCountryCode  = $data[$root]['buyer-shipping-address']['country-code']['VALUE'];
			$newOrder->shippingCity  		= $data[$root]['buyer-shipping-address']['city']['VALUE'];
			$newOrder->shippingState  		= $data[$root]['buyer-shipping-address']['region']['VALUE'];
			$newOrder->shippingZIP 			= $data[$root]['buyer-shipping-address']['postal-code']['VALUE'];

 			$newOrder->billingCompanyName	= $data[$root]['buyer-billing-address']['company-name']['VALUE'];
	        $newOrder->billingContactName  	= $data[$root]['buyer-billing-address']['contact-name']['VALUE'];
	        $newOrder->billingEmail  		= $data[$root]['buyer-billing-address']['email']['VALUE'];
			$newOrder->billingFirstName  	= $data[$root]['buyer-billing-address']['structured-name']['first-name']['VALUE'];
			$newOrder->billingLastName  	= $data[$root]['buyer-billing-address']['structured-name']['last-name']['VALUE'];			        
			$newOrder->billingPhone  		= $data[$root]['buyer-billing-address']['phone']['VALUE'];
			$newOrder->billingFax  			= $data[$root]['buyer-billing-address']['fax']['VALUE'];
			$newOrder->billingAddress1  	= $data[$root]['buyer-billing-address']['address1']['VALUE'];
			$newOrder->billingAddress2  	= $data[$root]['buyer-billing-address']['address1']['VALUE'];
			$newOrder->billingCountryCode  	= $data[$root]['buyer-billing-address']['country-code']['VALUE'];
			$newOrder->billingCity  		= $data[$root]['buyer-billing-address']['city']['VALUE'];
			$newOrder->billingState  		= $data[$root]['buyer-billing-address']['region']['VALUE'];
			$newOrder->billingZIP			= $data[$root]['buyer-billing-address']['postal-code']['VALUE'];
				    	
	    	$this->buyerConfirmedOrder($newOrder);
	    	
	      	$this->googleResponse->SendAck();
	      	
	      break;
	    }
	    case "order-state-change-notification": {
	      $this->googleResponse->SendAck();
	      $new_financial_state = $data[$root]['new-financial-order-state']['VALUE'];
	      $new_fulfillment_order = $data[$root]['new-fulfillment-order-state']['VALUE'];
	
	      switch($new_financial_state) {
	        case 'REVIEWING': {
	
	        	break;
	        }
	        case 'CHARGEABLE': {
	          
	       // $this->googleRequest->SendProcessOrder($data[$root]['google-order-number']['VALUE']);
	       // $this->googleRequest->SendChargeOrder($data[$root]['google-order-number']['VALUE'],'');
	          	
	          break;
	        }
	        case 'CHARGING': {
	          
	        	break;
	        }
	        case 'CHARGED': {
	          
	        	break;
	        }
	        case 'PAYMENT_DECLINED': {
	          
	        	break;
	        }
	        case 'CANCELLED': {
	        	
	        	// TODO: No feedback from Google when I cancel withhin 15Minutes. Test again.
	        	$this->buyerCancelledWithin15Min();
	        	    	    	
	        	
	          break;
	        }
	        case 'CANCELLED_BY_GOOGLE': {
	        	
				// Not tested, Google doesnt cancel orders in sandbox	    
	        	$this->googleRequest->SendBuyerMessage($data[$root]['google-order-number']['VALUE'], "Sorry, your order is cancelled by Google", true);

	         break;
	        }
	        default:
	          break;
	      }
	
	      switch($new_fulfillment_order) {
	        case 'NEW': {
	          break;
	        }
	        case 'PROCESSING': {
	          break;
	        }
	        case 'DELIVERED': {
	          break;
	        }
	        case 'WILL_NOT_DELIVER': {
	          break;
	        }
	        default:
	          break;
	      }
	      break;
	    }
	    case "charge-amount-notification": {
	      
	      //$this->googleRequest->SendDeliverOrder($data[$root]['google-order-number']['VALUE'], <carrier>, <tracking-number>, <send-email>);
	      //$this->googleRequest->SendArchiveOrder($data[$root]['google-order-number']['VALUE'] );
	      
	      $this->googleResponse->SendAck();
	      
	      break;
	    }
	    case "chargeback-amount-notification": {
	      $this->googleResponse->SendAck();
	      break;
	    }
	    case "refund-amount-notification": {
	      $this->googleResponse->SendAck();
	      break;
	    }
	    case "risk-information-notification": {
	      $this->googleResponse->SendAck();
	      break;
	    }
	    default:
	      $this->googleResponse->SendBadRequestStatus("Invalid or not supported Message");
	      break;
	  }
	}
  
	private function get_arr_result($child_node) {
	    $result = array();
	    if(isset($child_node)) {
	      if($this->is_associative_array($child_node)) {
	        $result[] = $child_node;
	      }
	      else {
	        foreach($child_node as $curr_node){
	          $result[] = $curr_node;
			}
	      }
	    }
	    return $result;
	}

	// Returns true if a given variable represents an associative array
	private function is_associative_array( $var ) {
		return is_array( $var ) && !is_numeric( implode( '', array_keys( $var ) ) );
	}
}

?>