<?php

/**
 *  Holds a single order after the buyer adjusted/edited and confirmed the order
 */

class GoogleSingleOrder {
	
	public $orderNumber			= 0;
	public $buyerId				= 0;
	
	public $fulfillmentState	= "";
	public $financiaOrderState	= "";
	public $orderTimeStamp		= "";
	
	public $itemObjArr 			= array();
	
	public $marketingEmailAllowed= "";

	public $orderTotalAmount	= "";
	public $taxAmount			= "";
	public $adjustmentAmount	= ""; // Coupon
	
	public $couponCode			= ""; 
	public $couponAppliedAmount	= 0; 
	public $couponCalculatedAmount = 0; 
	public $couponMessage		= ""; 
		
	public $shippingTypeName	= "";
	public $shippingCost		= "";
	
	public $shippingCompanyName	= "";
    public $shippingContactName	= "";
    public $shippingEmail		= "";
    public $shippingPhone		= "";
    public $shippingFax			= "";
    public $shippingAddress1	= "";
    public $shippingAddress2	= "";
    public $shippingFirstName	= "";
    public $shippingLastName	= "";
    public $shippingCountryCode	= "";
    public $shippingCity		= "";
    public $shippingState		= "";
    public $shippingZIP			= "";
    
    public $billingCompanyName	= "";
    public $billingContactName	= "";
    public $billingEmail		= "";
    public $billingPhone		= "";
    public $billingFax			= "";
    public $billingAddress1		= "";
    public $billingAddress2		= "";
    public $billingFirstName	= "";
    public $billingLastName		= "";
    public $billingCountryCode	= "";
    public $billingCity			= "";
    public $billingState		= "";
    public $billingZIP			= "";	
}

?>