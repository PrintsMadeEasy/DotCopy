<?php

interface ShippingMethodsInterface {


	// Returns an array of ALL Shipping methods with the Shipping Code as the Key.  Shipping Codes can have 1 or 2 characters.
	// Example: array("UG"=>"UPS Ground", "U1"=>"UPS 1 Day", "U1E"=>"UPS 1 Day Early", "P1C"=>"Postal Service 1st Class")
	// These Description are not what the customer will necessarily see... Admins will save their own Shipping Description in the database (maybe based upon another language).
	// These names will be used as a reference for the Admins.
	public function getShippingMethodsHash();
	
	// The name here is what the Shipping Carrier defines it as ... (exactly).
	// You can use an Internal Code for the Shipping Carrier if you want because it won't be seen by an administrator.  
	// It will be used to send out to ODBC databases or API calls for the carrier.
	public function getCarrierReference($shippignMethodCode);
	
	
	// Pass in shipping method description used by the shipping carrier, it will convert it back to our internal shipping method code.
	// Does the reverse of getCarrierReference.
	public function getShippingMethodCodeFromCarrierReference($shippingCarrierName, $shippingMethodCarrierReference);

	
	// Returns a Transit Time Object for all of the shipping methods and arrival times that are available to the destination.	
	// If "Satuday Delivery (early)" is not avaialable (maybe because it is residential) that would not be included in this list
	// 2nd Day air may not be offered by the Carrier either if it is your next door neighbor.
	// If we are shipping to Canada then the all of the "United States specific methods (like 1 day early)" won't be avaialable.
	// ... and if you are not in Canada, then "Canada Ground" would not be an option in this list.	
	// Does not filter out Saturday Deliveries. filter those yourself when you have a product with an estimated ship date.
	public function getTransitTimes(MailingAddress $shipFromAddressObj, MailingAddress $shipToAddressObj);
	
	// Returns an array of shipping codes.
	// For example, if TranistTimes says that 2nd day air is not available to your next door neighbor... you may want it included as a "possible selection" for shipping downgrading purposes.
	public function getAllShippingMethodsPossible(MailingAddress $shipFromAddressObj, MailingAddress $shipToAddressObj);


	
	// Retursn True if the Shipping Method can arrive on a Saturday... FALSE means Weekdays only.
	// This does not need an API call.  You should know this ahead of time.
	public function isSaturdayDelivery($shippingCode);


	// Returns True or False.  
	// It is up to an Admin on the Backend to decide what they are going to charge extra...
	//... or what the rely to the customer if a destination postal code is Rural.
	public function isDestinationRural(MailingAddress $addressObj);

	
	// Similar to isDestinationRural, but less severe.  It is almost like a semi-rural.  
	// For example, UPS charges slightly more for shipments to Puerto Rico with regular Ground Shipping. 
	// But they still charge less than Rural shipments, like to some parts of Alaska.
	// You could also have an extended distance with rural.
	public function isDestinationExtended(MailingAddress $shipFromAddressObj, MailingAddress $shipToAddressObj);
	
	
	// Returns TRUE or FALSE depnding on whether the Shipping Carrier finds that the address is valid.
	// If there is not an Address Verification System avaialbe... you can harcode the this Method to always return True.
	// Or you can implement a Shipping Carrier class and have it piggy back off of the another API. 
	// ... For Example, we could program the U.S. Postal Service Shiping Carrier Interface to use the UnitedParcelService API.
	// If it returns FALSE, we can get a list of alternate suggestings from getAddressSuggestions subsequently.
	public function isAddressValid(MailingAddress $addressObj);

	
	// You must have previoiusly called isAddressValid and had it return FALSE for this method to work (or it will fail with an error).
	// retursn an array of MailingAddressSuggestion objects.  The first element in the array is the most relavent.
	public function getAddressSuggestions();

}

?>
