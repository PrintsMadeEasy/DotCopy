<?php

// Used as a struct

class PayPalAllData
{

	public $timestamp;
	public $acknowledge;
	public $correlationId;
	public $version;
	public $build;

	public $payerId;
	public $transactionID;
	public $parentTransactionID;
	public $receiptId;
	public $transactionType;

	public $paymentType;
	public $paymentDate;

	public $grossAmount;
	public $feeAmount;
	public $taxAmount;

	public $currencyId;
	public $exchangeRate;

	public $paymentStatus;
	public $pendingReason;
	public $reasonCode;

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

	public $receiverBusiness;
	public $receiverEmail;
	public $receiverId;

	public $invoiceId;
	public $customString;
	public $memo;
}

?>