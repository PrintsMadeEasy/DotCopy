<?php

// Used as a struct

class PayPalCustomerData
{
	public $token;

	public $payerEmail;
	public $payerStatus;
	public $payerSalutation;
	public $payerFirstName;
	public $payerMiddleName;
	public $payerLastName;
	public $payerSuffix;
	public $payerCountryCode;

	public $business;
	public $businessName;
	public $businessStreet1;
	public $businessStreet2;
	public $businessCityName;
	public $businessZIP;
	public $businessStateOrProvince;
	public $businessCountryCode;
	public $businessCountryName;
	public $businessAddressOwner;
	public $businessAddressStatus;

	public $timestamp;
	public $acknowledge;
	public $correlationId;
	public $version;
	public $build;

	public $errorCode;
	public $errorMessage;
	public $errorMessageLong;
	public $severityCode;
}

?>