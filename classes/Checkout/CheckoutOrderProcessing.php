<?php

class CheckoutOrderProcessing {

	private $googleOrderNo;
	private $itemsShipped = array();
	private $googleRequestObj;
		
	function __construct() {
	
	// TODO Get values from Constants	
	//	if(Constants::GetDevelopmentServer()) {
			$merchantId  = "761791181223226";
			$merchantKey = "btJ-fAs7ZUAqTdlCXzIZRg";
			$serverType  = "sandbox";
			
	//	} else {
	//		$merchantId  = "";
	//		$merchantKey = "";
	//		$serverType  = "liveserver";
	//	}
	
		$this->googleRequestObj  = new GoogleRequest($merchantId, $merchantKey, $serverType, 'USD');
	}

	// TODO Check input vars like $this->googleOrderNo
	
	function setGoogleOrderNo($googleOrderNo) {
		
		$this->googleOrderNo = $googleOrderNo;
	}
	
	function addItem($itemId, $shippingCarrier = "", $trackingNo = "") {
	
	  	$tempItem = new GoogleShipItem($itemId);
	  	$tempItem->AddTrackingData($shippingCarrier,$trackingNo);
  		$this->itemsShipped[] = $tempItem;
	}
		
	function orderShippedItems() {
  
		$resonse = $this->googleRequestObj->SendShipItems($this->googleOrderNo, $this->itemsShipped);
		$this->itemsShipped = array();
		return $resonse;
  	}

	function orderCancelItems($reason = "", $comment = "") {
  
		$resonse = $this->googleRequestObj->SendCancelItems($this->googleOrderNo, $this->itemsShipped,$reason,$comment);
		$this->itemsShipped = array();
		return $resonse;
  	}
  	
  	function orderReturnedItems() {
  
		$resonse = $this->googleRequestObj->SendReturnItems($this->googleOrderNo, $this->itemsShipped);
		$this->itemsShipped = array();
		return $resonse;
  	}
  	
	function sendBuyerMessage($message) {
 
		return $this->googleRequestObj->SendBuyerMessage($this->googleOrderNo, $message);
  	}	
  	
  	function chargeFullOrder() {
  		
  		return $this->googleRequestObj->SendChargeOrder($this->googleOrderNo,'');
  	}
  	
  	function chargeOrderAmount($amount='') {
  		
  		return $this->googleRequestObj->SendChargeOrder($this->googleOrderNo,$amount);
  	}  	
  	
  	function archiveOrder() {
  		
  		return $this->googleRequestObj->SendArchiveOrder($this->googleOrderNo);
  	}
  	
  	
  	
}

?>