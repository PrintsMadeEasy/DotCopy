<?php

// Used as a struct

class PayPalRefundData
{
	public $timestamp;
	public $acknowledge;
	public $correlationId;
	public $version;
	public $build;

	public $refundTransactionId;
	public $netRefundAmount;
	public $feeRefundAmount;
	public $grossRefundAmount;
	public $netRefundCurrency;

	public $errorCode;
	public $errorMessage;
	public $errorMessageLong;
	public $severityCode;
}

?>