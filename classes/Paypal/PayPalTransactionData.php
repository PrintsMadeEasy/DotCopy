<?php

// Used as a struct

class PayPalTransactionData
{
	public $token;

	public $timestamp;
	public $acknowledge;
	public $correlationId;
	public $version;
	public $build;

	public $transactionID;
	public $parentTransactionID;
	public $receiptID;
	public $transactionType;

	public $paymentType;
	public $paymentDate;

	public $grossAmount;
	public $feeAmount;
	public $taxAmount;

	public $currencyID;
	public $exchangeRate;

	public $paymentStatus;
	public $pendingReason;
	public $reasonCode;

	public $errorCode;
	public $errorMessage;
	public $errorMessageLong;
	public $severityCode;
}

?>