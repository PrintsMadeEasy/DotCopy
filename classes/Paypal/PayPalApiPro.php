<?php

class PayPalApiPro
{
	private  $username;
	private  $password;
	private  $signature;
	private  $environment;
	private  $wsdl;
	private  $serviceURL;

	public function __construct($domainID = null)
	{
		if(empty($domainID))
			$domainID = Domain::getDomainIDfromURL();
			
		srand(microtime()*1000000);
		
		if(Constants::GetDevelopmentServer()) {
			
			$this->username		= "sell_1258730873_biz_api1.asynx.ch";
			$this->password		= "1258730878";
			$this->signature	= "A0p8X4HLanPSK6Sz3sbj5H9hd4Y-AoynUdiX1s89ys323iPHqxJ1spDd";
			$this->environment 	= "Sandbox";
			$this->wsdl 		= "https://www.sandbox.paypal.com/wsdl/PayPalSvc.wsdl";
					
		} 
		else {
			$domainGatewaysObj = new DomainGateways($domainID);
			
			$this->username = $domainGatewaysObj->paypalAPI_LoginID;
			$this->password = $domainGatewaysObj->paypalAPI_LoginPassword;
			$this->signature = $domainGatewaysObj->paypalAPI_LoginSignature;
			$this->environment	= "Live";
			$this->wsdl 		= "https://www.paypal.com/wsdl/PayPalSvc.wsdl";			
		}	

		if($this->environment == "Live")
			$this->serviceURL = "https://api-3t.paypal.com/2.0/";
		else if($this->environment == "Sandbox")
			$this->serviceURL = "https://api.sandbox.paypal.com/2.0/";
		else
			throw new Exception("The Paypal environment is incorrect.");
		
	}


	public function randomPassword() {
	
		// No O/0/D   1/l/I/i  A/4  5/S  8/B  Z/2 6/G/C that could cause reading/typing errors. And not only hex numbers too. 39^8 combinations.
		$selectionKey = "VWajkNv9cefghxJHdRbFyz3EwP7MKstumLXTnrY";	

		$password = "";
		for($p=0; $p<8; $p++) 
			$password .= substr($selectionKey,intval(rand()%39),1);
			
		return $password;		
	}
	

	public function randomInvoiceId() {
	
		$selectionKey = "012345678901234567890";	
		$invoiceId    = "3D";
			
		for($p=0; $p<6; $p++) 
			$invoiceId .= substr($selectionKey,intval(rand()%20),1);
			
		return $invoiceId;		
	}
	
	static function transactionIdIsValid($transactionId) {

		if(strlen($transactionId) >= 17 || strlen($transactionId) <= 19)
			return true;
		else
			return false;
	}
	
	private function executeSOAPCall($call, $values)
	{
		
		$soap = new SoapClient($this->wsdl, array('soap_version' => SOAP_1_1, 'location' => $this->serviceURL, 'trace' => false));

		$soapHeader = new SoapHeader("urn:ebay:api:PayPalAPI", "RequesterCredentials", array(
			"Credentials" => new SoapVar(array(
				"Username" => $this->username,
				"Password" => $this->password,
				"Signature" =>$this->signature),
	 		SOAP_ENC_OBJECT, NULL, NULL, NULL, "urn:ebay:apis:eBLBaseComponents")));

		$values['Version'] = '62.0';
		$params = array($call.'Request'=>$values);
		$results = $soap->__soapCall($call, array($params), NULL, $soapHeader);

		//WebUtil::WebmasterError($soap->__getLastResponse(), "Paypal XML Response");		
		
		return $results;
	} 


	public function getAuthToken($amountUsd, $returnUrl, $cancelUrl) {

		$valArr = array("SetExpressCheckoutRequestDetails"=>array
		( 
			'OrderTotal' =>array("_" => $amountUsd, "currencyID" => 'USD'),
			'PaymentAction' => 'Authorization',
			'PaymentActionSpecified' => true,				
			'ReturnURL' =>$returnUrl,
			'CancelURL' =>$cancelUrl
		));
		
		$result = $this->executeSOAPCall("SetExpressCheckout", $valArr);
				
		$token = "";
		if(isset($result->Ack) && $result->Ack=="Success") 
			$token  = $result->Token;
	
		return $token;
	}


	public function getSaleToken($amountUsd, $returnUrl, $cancelUrl) {

		$valArr = array("SetExpressCheckoutRequestDetails"=>array( 'OrderTotal'=>array("_" => $amountUsd, "currencyID" => 'USD'),'ReturnURL' =>$returnUrl, 'CancelURL'=>$cancelUrl));
		
		$result = $this->executeSOAPCall("SetExpressCheckout", $valArr);
				
		$token = "";
		if(isset($result->Ack) && $result->Ack=="Success") 
			$token  = $result->Token;
	
		return $token;
	}

	public function authorizePayment($totalAmount, $shippingAmount, $taxAmount, $token, $payerID, $invoice) {
	
		//$itemAmount = $totalAmount - $shippingAmount - $taxAmount;
		
		// I found out that Paypal has some bugs.  They don't let us provide a Shipping Amount if the Item Amount is zero (FirstOneFree coupon).
		// They told me to leave both the shipping and item amounts blank and rely solely on the Total Amount.
		$itemAmount = "";
		$shippingAmount = "";
		$taxAmount = "";
		
		$valArr = array
		(
			'DoExpressCheckoutPaymentRequestDetails' => array
			(
				'Token'=>$token,
				'PayerID'=>$payerID,
				'PaymentAction' => 'Authorization',
				'PaymentActionSpecified' => true,
				
				'PaymentDetails' => array
				(
					'OrderTotal' => array("_" => 	$totalAmount , 	"currencyID" => 'USD'),
					'ItemTotal' => array("_" => 	$itemAmount, 	"currencyID" => 'USD'),
					'ShippingTotal' => array("_" => $shippingAmount,"currencyID" => 'USD'),
					//'HandlingTotal' => array("_" => $shippingAmount,"currencyID" => 'USD'),
					'TaxTotal' => array("_" => 		$taxAmount, 	"currencyID" => 'USD'),
					'InvoiceID'=>$invoice
				)
			)
		);

		$result = $this->executeSOAPCall("DoExpressCheckoutPayment", $valArr);
			
		$transactionData = new PayPalTransactionData();
		
		
		if(!isset($result->Ack) || empty($result->Ack)){
			$transactionData->acknowledge 		= "api_failure";
			$transactionData->errorCode 		= 50001;
			$transactionData->errorMessage 		= "Undefined Return authorizePayment";
			$transactionData->errorMessageLong 	= "Paypal API Error. No acknowledgment was received in method authorizePayment.";
			return $transactionData;
		}

		$transactionData->token 				= $token;
		
		$transactionData->acknowledge			= $result->Ack;
		$transactionData->timestamp				= $result->Timestamp;
		$transactionData->correlationId			= $result->CorrelationID;
		$transactionData->version 				= $result->Version;
		$transactionData->build 				= $result->Build;
		
		if(Constants::GetDevelopmentServer()) {
		
			$transactionData->transactionID 		= "8VT76897L1907171U";
			$transactionData->parentTransactionID 	= "";
			$transactionData->receiptID 			= "";
			$transactionData->transactionType 		= "express-checkout";
		
			$transactionData->paymentType 			= "instant";
			$transactionData->paymentDate 			= "2009-12-01T18:32:09Z";
		
			$transactionData->grossAmount 			= "125.20";
			$transactionData->feeAmount 			= "3.93";
			$transactionData->taxAmount				= "8.25";
		
			$transactionData->currencyID 			= "USD";
	
			$transactionData->exchangeRate  		= "";
		
			$transactionData->paymentStatus 		= "Pending";
			$transactionData->pendingReason 		= "authorization";
			$transactionData->reasonCode 			= "none";
				
		} 
		else {
			
			/* In case you need to analzye results from customers on the live site
			ob_start();
			var_dump($result);
			$a=ob_get_contents();
			ob_end_clean();
			WebUtil::WebmasterError($a, "Paypal Transaction Data. Fee Amount?");
			*/
			
			// If we don't get a Success... then don't attempt trying to get other payment details.
			if($transactionData->acknowledge != "Success") {
	
				$transactionData->errorCode 		= $result->Errors->ErrorCode;
				$transactionData->errorMessage 		= $result->Errors->ShortMessage;;
				$transactionData->errorMessageLong 	= $result->Errors->LongMessage;
				$transactionData->severityCode 		= $result->Errors->SeverityCode;
				
				return $transactionData;
			}
		

			
			$transactionData->transactionID 		= $result->DoExpressCheckoutPaymentResponseDetails->PaymentInfo->TransactionID;
			
			$transactionData->parentTransactionID 	= $result->DoExpressCheckoutPaymentResponseDetails->PaymentInfo->ParentTransactionID;
			$transactionData->receiptID 			= $result->DoExpressCheckoutPaymentResponseDetails->PaymentInfo->ReceiptID;
			$transactionData->transactionType 		= $result->DoExpressCheckoutPaymentResponseDetails->PaymentInfo->TransactionType;
		
			$transactionData->paymentType 			= $result->DoExpressCheckoutPaymentResponseDetails->PaymentInfo->PaymentType;
			$transactionData->paymentDate 			= $result->DoExpressCheckoutPaymentResponseDetails->PaymentInfo->PaymentDate;
		
			$transactionData->grossAmount 			= $result->DoExpressCheckoutPaymentResponseDetails->PaymentInfo->GrossAmount->_;
			
			
			// The Fee isn't present on Authorizations... just captures.
			if(!isset($result->DoExpressCheckoutPaymentResponseDetails->PaymentInfo->FeeAmount))
				$transactionData->feeAmount = 0;
			else
				$transactionData->feeAmount = $result->DoExpressCheckoutPaymentResponseDetails->PaymentInfo->FeeAmount->_;
			

			// For some reason the tax amount value is not always set.  Maybe it is from California, or internation paypal accounts?
			if(!isset($result->DoExpressCheckoutPaymentResponseDetails->PaymentInfo->TaxAmount))
				$transactionData->taxAmount	= 0;
			else
				$transactionData->taxAmount				= $result->DoExpressCheckoutPaymentResponseDetails->PaymentInfo->TaxAmount->_;
		
				
			$transactionData->currencyID 			= $result->DoExpressCheckoutPaymentResponseDetails->PaymentInfo->GrossAmount->currencyID;
	
			$transactionData->exchangeRate  		= $result->DoExpressCheckoutPaymentResponseDetails->PaymentInfo->ExchangeRate;
		
			$transactionData->paymentStatus 		= $result->DoExpressCheckoutPaymentResponseDetails->PaymentInfo->PaymentStatus;
			$transactionData->pendingReason 		= $result->DoExpressCheckoutPaymentResponseDetails->PaymentInfo->PendingReason;
			$transactionData->reasonCode 			= $result->DoExpressCheckoutPaymentResponseDetails->PaymentInfo->ReasonCode;
		}
	


		return $transactionData;
	}


	public function confirmPayment($totalAmount, $shippingAmount, $taxAmount, $token, $payerID, $invoice) {

		// https://www.x.com/docs/DOC-1260 > All Options

		// Items and shipping together ? Taxed on all
		//$itemAmount = $totalAmount - $shippingAmount - $taxAmount;
		
		// I found out that Paypal has some bugs.  They don't let us provide a Shipping Amount if the Item Amount is zero (FirstOneFree coupon).
		// They told me to leave both the shipping and item amounts blank and rely solely on the Total Amount.
		$itemAmount = "";
		$shippingAmount = "";
		$taxAmount = "";

		$valArr = array
		(
			'DoExpressCheckoutPaymentRequestDetails' => array
			(
				'Token'=>$token,
				'PayerID'=>$payerID,
				'PaymentAction' => 'Sale',
				'PaymentDetails' => array
				(
					'OrderTotal'=>array("_" => 		$totalAmount , 	"currencyID" => 'USD'),
					'ItemTotal'=>array("_" => 		$itemAmount, 	"currencyID" => 'USD'),
					'ShippingTotal'=>array("_" => 	$shippingAmount,"currencyID" => 'USD'),
					//'HandlingTotal' => array("_" => $shippingAmount,"currencyID" => 'USD'),
					'TaxTotal'=>array("_" => 		$taxAmount, 	"currencyID" => 'USD'),
					'InvoiceID'=>$invoice
				)
			)
		);

		$result = $this->executeSOAPCall("DoExpressCheckoutPayment", $valArr);
		
		$transactionData = new PayPalTransactionData();
		
		
		if(!isset($result->Ack) || empty($result->Ack)){
			$transactionData->acknowledge 		= "api_failure";
			$transactionData->errorCode 		= 50002;
			$transactionData->errorMessage 		= "Undefined Return confirmPayment";
			$transactionData->errorMessageLong 	= "Paypal API Error. No acknowledgment was received in method confirmPayment.";
			return $transactionData;
		}

		$transactionData->acknowledge			= $result->Ack;
		$transactionData->timestamp				= $result->Timestamp;
		$transactionData->correlationId			= $result->CorrelationID;
		$transactionData->version 				= $result->Version;
		$transactionData->build 				= $result->Build;

		$transactionData->token 				= $result->DoExpressCheckoutPaymentResponseDetails->Token;

		if(Constants::GetDevelopmentServer()) {
		
			$confirmData->transactionID 		= "4U612263LL1269234 (dummy)";
			$confirmData->parentTransactionID 	= "";
			$confirmData->receiptID 			= "";
			$confirmData->transactionType 		= "express-checkout";
		
			$confirmData->paymentType 			= "instant";
			$confirmData->paymentDate 			= "2009-12-01T18:32:09Z";
		
			$confirmData->grossAmount 			= "125.20";
			$confirmData->feeAmount 			= "3.93";
			$confirmData->taxAmount				= "8.25";
		
			$confirmData->currencyID 			= "USD";
	
			$confirmData->exchangeRate  		= "";
		
			$confirmData->paymentStatus 		= "Completed";
			$confirmData->pendingReason 		= "none";
			$confirmData->reasonCode 			= "none";

		} else {
		
			$transactionData->transactionID 		= $result->DoExpressCheckoutPaymentResponseDetails->PaymentInfo->TransactionID;
			$transactionData->parentTransactionID 	= $result->DoExpressCheckoutPaymentResponseDetails->PaymentInfo->ParentTransactionID;
			$transactionData->receiptID 			= $result->DoExpressCheckoutPaymentResponseDetails->PaymentInfo->ReceiptID;
			$transactionData->transactionType 		= $result->DoExpressCheckoutPaymentResponseDetails->PaymentInfo->TransactionType;
		
			$transactionData->paymentType 			= $result->DoExpressCheckoutPaymentResponseDetails->PaymentInfo->PaymentType;
			$transactionData->paymentDate 			= $result->DoExpressCheckoutPaymentResponseDetails->PaymentInfo->PaymentDate;
		
			$transactionData->grossAmount 			= $result->DoExpressCheckoutPaymentResponseDetails->PaymentInfo->GrossAmount->_;
			$transactionData->feeAmount 			= $result->DoExpressCheckoutPaymentResponseDetails->PaymentInfo->FeeAmount->_;
			$transactionData->taxAmount				= $result->DoExpressCheckoutPaymentResponseDetails->PaymentInfo->TaxAmount->_;
		
			$transactionData->currencyID 			= $result->DoExpressCheckoutPaymentResponseDetails->PaymentInfo->GrossAmount->currencyID;
	
			$transactionData->exchangeRate  		= $result->DoExpressCheckoutPaymentResponseDetails->PaymentInfo->ExchangeRate;
		
			$transactionData->paymentStatus 		= $result->DoExpressCheckoutPaymentResponseDetails->PaymentInfo->PaymentStatus;
			$transactionData->pendingReason 		= $result->DoExpressCheckoutPaymentResponseDetails->PaymentInfo->PendingReason;
			$transactionData->reasonCode 			= $result->DoExpressCheckoutPaymentResponseDetails->PaymentInfo->ReasonCode;
		}

		if($transactionData->acknowledge!="Success") {

			$transactionData->errorCode 		= $result->Errors->ErrorCode;
			$transactionData->errorMessage 		= $result->Errors->ShortMessage;;
			$transactionData->errorMessageLong 	= $result->Errors->LongMessage;
			$transactionData->severityCode 		= $result->Errors->SeverityCode;
		}

		return $transactionData;
	}


	public function getExpressCheckoutDetails($token) {

		$valArr = array('Token'=>$token);	
		$result = $this->executeSOAPCall("GetExpressCheckoutDetails", $valArr);
		
		/* In case you need to analzye results from customers on the live site	
		ob_start();
		var_dump($result);
		$a=ob_get_contents();
		ob_end_clean();
		WebUtil::WebmasterError($a, "Paypal Results");
		*/
		
		$customerData = new PayPalCustomerData();

		if(!isset($result->Ack) || empty($result->Ack)){
			$customerData->acknowledge 		= "api_failure";
			$customerData->errorCode 		= 50003;
			$customerData->errorMessage 		= "Undefined Return getExpressCheckoutDetails";
			$customerData->errorMessageLong 	= "Paypal API Error. No acknowledgment was received in method getExpressCheckoutDetails.";
			return $customerData;
		}
		
		$customerData->acknowledge			= $result->Ack;
		$customerData->timestamp			= $result->Timestamp;
		$customerData->correlationId		= $result->CorrelationID;
		$customerData->version 				= $result->Version;
		$customerData->build 				= $result->Build;

		$customerData->token 				= $result->GetExpressCheckoutDetailsResponseDetails->Token;

		if(Constants::GetDevelopmentServer()) {
			
			$customerData->payerId 				= "ZANPN84NM7U3L";
			$customerData->payerEmail 			= "christ_1258729267_per@asynx.ch";
			$customerData->payerStatus 			= "verified";
			$customerData->payerSalutation 		= "";
			$customerData->payerFirstName 		= "Test";
			$customerData->payerMiddleName 		= "";
			$customerData->payerLastName 		= "User";
			$customerData->payerSuffix 			= "";
			$customerData->business 			= "";
			$customerData->payerCountryCode 	= "US";
			$customerData->businessName 		= "Test User";
			$customerData->businessStreet1 		= "1 Main Street";
			$customerData->businessStreet2 		= "";
			$customerData->businessCityName 	= "San Jose";
			$customerData->businessStateOrProvince = "CA";
			$customerData->businessZIP 			= "95131";
			$customerData->businessCountryCode 	= "US";
			$customerData->businessCountryName 	= "United States";
			$customerData->businessAddressOwner = "Paypal";
			$customerData->businessAddressStatus= "Confirmed";

		} else {

			$customerData->payerStatus 			= $result->GetExpressCheckoutDetailsResponseDetails->PayerInfo->PayerStatus;
	
			$customerData->payerSalutation 		= $result->GetExpressCheckoutDetailsResponseDetails->PayerInfo->PayerName->Salutation;
			$customerData->payerFirstName 		= $result->GetExpressCheckoutDetailsResponseDetails->PayerInfo->PayerName->FirstName;
			$customerData->payerMiddleName 		= $result->GetExpressCheckoutDetailsResponseDetails->PayerInfo->PayerName->MiddleName;
			$customerData->payerLastName 		= $result->GetExpressCheckoutDetailsResponseDetails->PayerInfo->PayerName->LastName;
			$customerData->payerSuffix 			= $result->GetExpressCheckoutDetailsResponseDetails->PayerInfo->PayerName->Suffix;
	
			$customerData->business 			= $result->GetExpressCheckoutDetailsResponseDetails->PayerInfo->PayerBusiness;
			$customerData->payerCountryCode 	= $result->GetExpressCheckoutDetailsResponseDetails->PayerInfo->PayerCountry;
			$customerData->payerEmail   		= $result->GetExpressCheckoutDetailsResponseDetails->PayerInfo->Payer;
	
			$customerData->businessName 		= $result->GetExpressCheckoutDetailsResponseDetails->PayerInfo->Address->Name;
			$customerData->businessStreet1 		= $result->GetExpressCheckoutDetailsResponseDetails->PayerInfo->Address->Street1;
			$customerData->businessStreet2 		= $result->GetExpressCheckoutDetailsResponseDetails->PayerInfo->Address->Street2;
			$customerData->businessCityName 	= $result->GetExpressCheckoutDetailsResponseDetails->PayerInfo->Address->CityName;
			$customerData->businessStateOrProvince = $result->GetExpressCheckoutDetailsResponseDetails->PayerInfo->Address->StateOrProvince;
			$customerData->businessZIP 			= $result->GetExpressCheckoutDetailsResponseDetails->PayerInfo->Address->PostalCode;
			
			$customerData->businessCountryCode 	= $result->GetExpressCheckoutDetailsResponseDetails->PayerInfo->Address->Country;
			$customerData->businessCountryName 	= $result->GetExpressCheckoutDetailsResponseDetails->PayerInfo->Address->CountryName;
			$customerData->businessAddressOwner = $result->GetExpressCheckoutDetailsResponseDetails->PayerInfo->Address->AddressOwner;
			$customerData->businessAddressStatus= $result->GetExpressCheckoutDetailsResponseDetails->PayerInfo->Address->AddressStatus;
		}

		if($customerData->acknowledge!="Success") {

			$customerData->errorCode 			= $result->Errors->ErrorCode;
			$customerData->errorMessage 		= $result->Errors->ShortMessage;;
			$customerData->errorMessageLong 	= $result->Errors->LongMessage;
			$customerData->severityCode 		= $result->Errors->SeverityCode;
		}

		return $customerData;
	}


	public function capturePartial($authorizationId, $partialAmount, $memo = "") {

		// NVP but simular https://cms.paypal.com/us/cgi-bin/?cmd=_render-content&content_ID=developer/e_howto_api_nvp_r_DoCapture

		$result = $this->executeSOAPCall("DoCapture", array('AuthorizationID'=>$authorizationId,'CompleteType'=>"NotComplete", 'Memo'=>$memo, 'Amount'=>array("_" => $partialAmount , "currencyID" => 'USD')));

		/* In case you need to analzye results from customers on the live site
		ob_start();
		var_dump($result);
		$a=ob_get_contents();
		ob_end_clean();
		WebUtil::WebmasterError($a, "Paypal Results");
		*/
		
		$transactionData = new PayPalTransactionData();

		if(!isset($result->Ack) || empty($result->Ack)){
			$transactionData->acknowledge 		= "api_failure";
			$transactionData->errorCode 		= 50004;
			$transactionData->errorMessage 		= "Undefined Return capturePartial";
			$transactionData->errorMessageLong 	= "Paypal API Error. No acknowledgment was received in method capturePartial.";
			return $transactionData;
		}
		
		$transactionData->acknowledge			= $result->Ack;
		$transactionData->timestamp				= $result->Timestamp;
		$transactionData->correlationId			= $result->CorrelationID;
		$transactionData->version 				= $result->Version;
		$transactionData->build 				= $result->Build;		
		
		if(Constants::GetDevelopmentServer()) {
		
			$transactionData->transactionID 		= "61E613853G12DUMMY";
			$transactionData->parentTransactionID 	= "5N2303827M70DUMMY";
			$transactionData->receiptID 			= "";
			$transactionData->transactionType 		= "express-checkout";
		
			$transactionData->paymentType 			= "instant";
			$transactionData->paymentDate 			= "2009-12-18T09:40:56Z";
		
			$transactionData->grossAmount 			= "220.47";
			$transactionData->feeAmount 			= "6.69";
			$transactionData->taxAmount				= "0.00";
		
			$transactionData->currencyID 			= "USD";
	
			$transactionData->exchangeRate  		= "";
		
			$transactionData->paymentStatus 		= "Completed";
			$transactionData->pendingReason 		= "none";
			$transactionData->reasonCode 			= "none";

		} 
		else {
			$transactionData->transactionType 		= $result->DoCaptureResponseDetails->PaymentInfo->TransactionType;
			$transactionData->paymentType 			= $result->DoCaptureResponseDetails->PaymentInfo->PaymentType;
			$transactionData->paymentStatus 		= $result->DoCaptureResponseDetails->PaymentInfo->PaymentStatus;
			$transactionData->pendingReason 		= $result->DoCaptureResponseDetails->PaymentInfo->PendingReason;
			$transactionData->reasonCode 			= $result->DoCaptureResponseDetails->PaymentInfo->ReasonCode;
		}
		
		
		
		// Some variables are not available within the SOAP object... depending on whether the tranaction is a success.
		if($transactionData->acknowledge == "Success") {
			$transactionData->transactionID 		= $result->DoCaptureResponseDetails->PaymentInfo->TransactionID;
			$transactionData->parentTransactionID 	= $result->DoCaptureResponseDetails->PaymentInfo->ParentTransactionID;
			$transactionData->receiptID 			= $result->DoCaptureResponseDetails->PaymentInfo->ReceiptID;
			$transactionData->paymentDate 			= $result->DoCaptureResponseDetails->PaymentInfo->PaymentDate;
			$transactionData->grossAmount 			= $result->DoCaptureResponseDetails->PaymentInfo->GrossAmount->_;
			$transactionData->currencyID 			= $result->DoCaptureResponseDetails->PaymentInfo->GrossAmount->currencyID;
			$transactionData->feeAmount 			= $result->DoCaptureResponseDetails->PaymentInfo->FeeAmount->_;
			$transactionData->taxAmount				= $result->DoCaptureResponseDetails->PaymentInfo->TaxAmount->_;
			$transactionData->exchangeRate  		= $result->DoCaptureResponseDetails->PaymentInfo->ExchangeRate;
		}
		else {
			$transactionData->errorCode 		= $result->Errors->ErrorCode;
			$transactionData->errorMessage 		= $result->Errors->ShortMessage;;
			$transactionData->errorMessageLong 	= $result->Errors->LongMessage;
			$transactionData->severityCode 		= $result->Errors->SeverityCode;
		}
		

		return $transactionData;
	}


	public function voidOrder($authorizationId, $memo = "") {

		// NVP https://cms.paypal.com/us/cgi-bin/?cmd=_render-content&content_ID=developer/e_howto_api_soap_r_DoVoid

		$result = $this->executeSOAPCall("DoVoid", array('AuthorizationID'=>$authorizationId,'Memo'=>$memo,'CompleteType'=>"Complete",));

		$voidData = new PayPalAuthData();

		if(!isset($result->Ack) || empty($result->Ack)){
			$voidData->acknowledge 		= "api_failure";
			$voidData->errorCode 			= 50005;
			$voidData->errorMessage 		= "Undefined Return voidOrder";
			$voidData->errorMessageLong 	= "Paypal API Error. No acknowledgment was received in method voidOrder.";
			return $voidData;
		}
		
		$voidData->acknowledge			= $result->Ack;
		$voidData->timestamp			= $result->Timestamp;
		$voidData->correlationId		= $result->CorrelationID;
		$voidData->version 				= $result->Version;
		$voidData->build 				= $result->Build;

		$voidData->authorizationId 		= $result->AuthorizationID;

		if($voidData->acknowledge!="Success") {

			$voidData->errorCode 		= $result->Errors->ErrorCode;	
			$voidData->errorMessage 	= $result->Errors->ShortMessage;;
			$voidData->errorMessageLong = $result->Errors->LongMessage;
			$voidData->severityCode 	= $result->Errors->SeverityCode;
		}

		return $voidData;
	}

	
	public function reauthorization($authorizationId, $amount) {

		// NVP https://cms.paypal.com/us/cgi-bin/?cmd=_render-content&content_ID=developer/e_howto_api_soap_r_DoReauthorization
		// Reauthorization is only allowed 4-29 days after authorization and only once !

		$result = $this->executeSOAPCall("DoReauthorization", array('AuthorizationID'=>$authorizationId,'Amount'=>array("_" => $amount , "currencyID" => 'USD')));

		$reauthData = new PayPalAuthData();
		
		if(!isset($result->Ack) || empty($result->Ack)){
			$reauthData->acknowledge 		= "api_failure";
			$reauthData->errorCode 			= 50006;
			$reauthData->errorMessage 		= "Undefined Return reauthorization";
			$reauthData->errorMessageLong 	= "Paypal API Error. No acknowledgment was received in method reauthorization.";
			return $reauthData;
		}

		$reauthData->acknowledge		= $result->Ack;
		$reauthData->timestamp			= $result->Timestamp;
		$reauthData->correlationId		= $result->CorrelationID;
		$reauthData->version 			= $result->Version;
		$reauthData->build 				= $result->Build;
		$reauthData->authorizationId 	= $authorizationId;

		if($reauthData->acknowledge!="Success") {

			$reauthData->errorCode 		= $result->Errors->ErrorCode;	// 10609 -> too early, within 3 days.
			$reauthData->errorMessage 	= $result->Errors->ShortMessage;;
			$reauthData->errorMessageLong = $result->Errors->LongMessage;
			$reauthData->severityCode 	= $result->Errors->SeverityCode;
		}
		
		return $reauthData;
	}


	public function refundPartialPayment($transactionId, $refundAmount, $memo= "") {

		// https://www.x.com/docs/DOC-1203

		$result = $this->executeSOAPCall("RefundTransaction", array('TransactionID'=>"$transactionId", 'RefundType' => "Partial", 'Memo' => $memo, 'Amount'=>array("_" => $refundAmount , "currencyID" => 'USD') )  );

		$refundData = new PayPalRefundData();

		if(!isset($result->Ack) || empty($result->Ack)){
			$refundData->acknowledge 		= "api_failure";
			$refundData->errorCode 			= 50008;
			$refundData->errorMessage 		= "Undefined Return refundPartialPayment";
			$refundData->errorMessageLong 	= "Paypal API Error. No acknowledgment was received in method refundPartialPayment.";
			return $refundData;
		}
		
		$refundData->acknowledge		= $result->Ack;
		$refundData->timestamp			= $result->Timestamp;
		$refundData->correlationId		= $result->CorrelationID;
		$refundData->version 			= $result->Version;
		$refundData->build 				= $result->Build;

		$refundData->refundTransactionId = $result->RefundTransactionID;

		$refundData->netRefundAmount	= $result->NetRefundAmount->_;
		$refundData->feeRefundAmount	= $result->FeeRefundAmount->_;
		$refundData->grossRefundAmount	= $result->GrossRefundAmount->_;

		$refundData->netRefundCurrency	= $result->NetRefundAmount->currencyID;

		if($refundData->acknowledge!="Success") {

			$refundData->errorCode 			= $result->Errors->ErrorCode;
			$refundData->errorMessage 		= $result->Errors->ShortMessage;;
			$refundData->errorMessageLong 	= $result->Errors->LongMessage;
			$refundData->severityCode 		= $result->Errors->SeverityCode;
		}
		
		return $refundData;
	}

	
	public function getTransactionDetails($transactionId) {

		$result = $this->executeSOAPCall("GetTransactionDetails", array('TransactionID'=>"$transactionId"));

		$allData = new PayPalAllData();
		
		if(!isset($result->Ack) || empty($result->Ack)){
			$allData->acknowledge 		= "api_failure";
			$allData->errorCode 		= 50009;
			$allData->errorMessage 		= "Undefined Return getTransactionDetails";
			$allData->errorMessageLong 	= "Paypal API Error. No acknowledgment was received in method getTransactionDetails.";
			return $allData;
		}

		$allData->acknowledge			= $result->Ack;
		$allData->timestamp				= $result->Timestamp;
		$allData->correlationId			= $result->CorrelationID;
		$allData->version 				= $result->Version;
		$allData->build 				= $result->Build;

		$allData->payerId 				= $result->PaymentTransactionDetails->PayerInfo->PayerID;
		$allData->payerStatus 			= $result->PaymentTransactionDetails->PayerInfo->PayerStatus;

		$allData->payerSalutation 		= $result->PaymentTransactionDetails->PayerInfo->PayerName->Salutation;
		$allData->payerFirstName 		= $result->PaymentTransactionDetails->PayerInfo->PayerName->FirstName;
		$allData->payerMiddleName 		= $result->PaymentTransactionDetails->PayerInfo->PayerName->MiddleName;
		$allData->payerLastName 		= $result->PaymentTransactionDetails->PayerInfo->PayerName->LastName;
		$allData->payerSuffix 			= $result->PaymentTransactionDetails->PayerInfo->PayerName->Suffix;

		$allData->business 				= $result->PaymentTransactionDetails->PayerInfo->PayerBusiness;
		$allData->payerCountryCode 		= $result->PaymentTransactionDetails->PayerInfo->PayerCountry;
		$allData->payerEmail   			= $result->PaymentTransactionDetails->PayerInfo->Payer;

		$allData->businessName 			= $result->PaymentTransactionDetails->PayerInfo->Address->Name;
		$allData->businessStreet1 		= $result->PaymentTransactionDetails->PayerInfo->Address->Street1;
		$allData->businessStreet2 		= $result->PaymentTransactionDetails->PayerInfo->Address->Street2;
		$allData->businessCityName 		= $result->PaymentTransactionDetails->PayerInfo->Address->CityName;
		$allData->businessStateOrProvince = $result->PaymentTransactionDetails->PayerInfo->Address->StateOrProvince;
		$allData->businessZIP 			= $result->PaymentTransactionDetails->PayerInfo->Address->PostalCode;
		
		$allData->businessCountryCode 	= $result->PaymentTransactionDetails->PayerInfo->Address->Country;
		$allData->businessCountryName 	= $result->PaymentTransactionDetails->PayerInfo->Address->CountryName;
		$allData->businessAddressOwner 	= $result->PaymentTransactionDetails->PayerInfo->Address->AddressOwner;
		$allData->businessAddressStatus	= $result->PaymentTransactionDetails->PayerInfo->Address->AddressStatus;

		$allData->transactionID 		= $result->PaymentTransactionDetails->PaymentInfo->TransactionID;
		$allData->parentTransactionID 	= $result->PaymentTransactionDetails->PaymentInfo->ParentTransactionID;
		$allData->receiptId 			= $result->PaymentTransactionDetails->PaymentInfo->ReceiptID;
		$allData->transactionType 		= $result->PaymentTransactionDetails->PaymentInfo->TransactionType;
		$allData->paymentType 			= $result->PaymentTransactionDetails->PaymentInfo->PaymentType;
		$allData->paymentDate 			= $result->PaymentTransactionDetails->PaymentInfo->PaymentDate;
	
		$allData->grossAmount 			= $result->PaymentTransactionDetails->PaymentInfo->GrossAmount->_;
		$allData->feeAmount 			= $result->PaymentTransactionDetails->PaymentInfo->FeeAmount->_;
		$allData->taxAmount				= $result->PaymentTransactionDetails->PaymentInfo->TaxAmount->_;
	
		$allData->currencyId 			= $result->PaymentTransactionDetails->PaymentInfo->GrossAmount->currencyID;

		$allData->exchangeRate  		= $result->PaymentTransactionDetails->PaymentInfo->ExchangeRate;
	
		$allData->paymentStatus 		= $result->PaymentTransactionDetails->PaymentInfo->PaymentStatus;
		$allData->pendingReason 		= $result->PaymentTransactionDetails->PaymentInfo->PendingReason;
		$allData->reasonCode 			= $result->PaymentTransactionDetails->PaymentInfo->ReasonCode;

		$allData->receiverBusiness 		= $result->PaymentTransactionDetails->ReceiverInfo->Business;
		$allData->receiverEmail 		= $result->PaymentTransactionDetails->ReceiverInfo->Receiver;
		$allData->receiverId 			= $result->PaymentTransactionDetails->ReceiverInfo->ReceiverID;
	
		$allData->invoiceId 			= $result->PaymentTransactionDetails->PaymentItemInfo->InvoiceID;
		$allData->customString 			= $result->PaymentTransactionDetails->PaymentItemInfo->Custom;
		$allData->memo 					= $result->PaymentTransactionDetails->PaymentItemInfo->Memo;
	
		return $allData;
	}
}

?>