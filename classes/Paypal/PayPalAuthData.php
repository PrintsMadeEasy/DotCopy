<?php

// Used as a struct

class PayPalAuthData
{
	public $timestamp;
	public $acknowledge;
	public $correlationId;
	public $version;
	public $build;

	public $authorizationId;

	public $errorCode;
	public $errorMessage;
	public $errorMessageLong;
	public $severityCode;
}

?>