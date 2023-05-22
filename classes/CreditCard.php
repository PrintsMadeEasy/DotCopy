<?php

class CreditCard {
	
	private $cardType;
	private $cardNumber;
	private $monthExpiration;
	private $yearExpiration;
	private $billingName;
	private $billingCompany;
	private $billingAddress;
	private $billingAddressTwo;
	private $billingCity;
	private $billingState;
	private $billingZip;
	private $billingCountry;
	

	function setCardType($x){
		
		$xUpper = strtoupper($x);
		
		if(!in_array($xUpper, array("VISA", "MASTERCARD", "AMEX", "DISCOVER", "DINERS")))
			throw new Exception("Illegal Credit Card Type");
	
		$this->cardType = $x;
	}
	function setCardNumber($x){
		$this->cardNumber = $x;
	}
	function setMonthExpiration($x){
		$this->monthExpiration = $x;
	}
	function setYearExpiration($x){
		$this->yearExpiration = $x;
	}
	function setBillingName($x){
		$this->billingName = $x;
	}
	function setBillingCompany($x){
		$this->billingCompany = $x;
	}
	function setBillingAddress($x){
		$this->billingAddress = $x;
	}
	function setBillingAddressTwo($x){
		$this->billingAddressTwo = $x;
	}
	function setBillingCity($x){
		$this->billingCity = $x;
	}
	function setBillingState($x){
		$this->billingState = $x;
	}
	function setBillingZip($x){
		$this->billingZip = $x;
	}
	function setBillingCountry($x){
		$this->billingCountry = $x;
	}
	
	
	
	
	

	function getCardType(){
		return $this->cardType;
	}
	function getCardNumber(){
		return $this->cardNumber;
	}
	function getMonthExpiration(){
		return $this->monthExpiration;
	}
	function getYearExpiration(){
		return $this->yearExpiration;
	}
	// returns a number like 0110  (january, 2010)
	function getExpirationDate(){
		$month = $this->monthExpiration;
		if(strlen($month) == 1)
			$month = "0" . $month;
			
		$year = $this->yearExpiration;
		if(strlen($year) == 4)
			$year = substr($year, 2);
			
		return $month . $year;
	}
	
	function getAddressFull(){
		if(!empty($this->billingAddressTwo))
			return $this->billingAddress . " " . $this->billingAddressTwo;
		else
			return $this->billingAddress;
	}
	
	function getBillingName(){
		return $this->billingName;
	}
	function getBillingCompany(){
		return $this->billingCompany;
	}
	function getBillingAddress(){
		return $this->billingAddress;
	}
	function getBillingAddressTwo(){
		return $this->billingAddressTwo;
	}
	function getBillingCity(){
		return $this->billingCity;
	}
	function getBillingState(){
		return $this->billingState;
	}
	function getBillingZip(){
		return $this->billingZip;
	}
	function getBillingCountry(){
		return $this->billingCountry;
	}
	
	
}

