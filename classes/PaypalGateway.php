<?


// This class does not communicate with the database in any way.  

class PaypalGateway {
	
	private $paypalObj;	
	private $token;
	private $payerId;
	private $thirdPartyInvoice;

	private $errorReason; //For containing a customized error message to be displyed to user
	private $transactionID;
	private $approvalCode;
	private $responseReasonText;
	private $responseReasonCode;
	private $transactionStatusChar;  //May be `A' accepted, `D' declined, or `E' error
	
	private $amount;
	private $freight;
	private $tax;
	private $transactionFee;
	

	###-- Constructor --###
	function PaypalGateway($domainId){
		
		if(!Domain::checkIfDomainIDexists($domainId))
			throw new Exception("Error in PaymentGateway constructor. The Domain ID does not exist.");
			
		// The live/sandbox decission is made in the paypalapipro class, so calls from other CS switch automatically too.
		$this->paypalObj = new PayPalApiPro($domainId);
		
		$this->transactionID = "";
		$this->responseReasonText = "";
		$this->responseReasonCode = "";
		$this->approvalCode = "";
		$this->errorReason = "";
		$this->transactionStatusChar = "E"; //Start off as an error, let it prove otherwise
		
		$this->amount = "";
		$this->freight = "";
		$this->tax = "";
		$this->transactionFee = "";
	}
	
	public function GetPaypalCommissionFee(){

		return $this->formatPrice($this->transactionFee);	
	}

	#---- Set / Get Properties ------#

	public function SetPaypalGatewayAmounts($amount, $tax, $freight){
	
		$this->amount 	= $this->formatPrice($amount);
		$this->freight 	= $this->formatPrice($freight);
		$this->tax 		= $this->formatPrice($tax);
	}

	public function SetTokenPayerID($token, $payerId){
	
		$this->token   = $token;
		$this->payerId = $payerId;
	}
	
	public function getThirdPartyInvoice() {
		
		return $this->thirdPartyInvoice;
	}

	private function createSecureRandomInvoiceId() {
		
		$orderIdCheck = "Start"; 
		$count        = 0;
		
		while(!empty($orderIdCheck)) {
			
			$thirdPartyInvoiceId = $this->paypalObj->randomInvoiceId();
			
			$orderIdCheck = Order::getOrderIdByThirdPartyInvoiceId($thirdPartyInvoiceId); // returns 0 if it doesn't exits, ends loop
			$count++; if($count>10){$orderIdCheck = "";} // prevent infinite loop.
		}

		return $thirdPartyInvoiceId;
	}
	
	
	##---- Begin Paypal API Calls ---- ##
	
	// Returns TRUE if the authorization succeeded, FALSE if failed --#
	
	public function AuthorizeOnly(){
		
		if(empty($this->token))
			throw new Exception("Paypal Token missing");
		
		if(empty($this->payerId))
			throw new Exception("Paypal PayerID missing");

		$this->thirdPartyInvoice  = $this->createSecureRandomInvoiceId();
		
		try{
			$authData = $this->paypalObj->authorizePayment($this->amount, $this->freight, $this->tax, $this->token, $this->payerId, $this->thirdPartyInvoice);
		}
		catch(SoapFault $fault){
			$this->errorReason = "We were not able to connect to the PayPal server.  Please let customer service know if this problem persists.";
			$this->responseReasonText = "Communication Error To Paypal: SoapFault: $fault->getMessage()";
			$this->transactionStatusChar = "E";
			WebUtil::WebmasterError("Possible Communication Error with Paypal? AuthorizeOnly() PayerID: $this->payerId , Amount: $this->amount , 3rdPartyInvoice: $this->thirdPartyInvoice Soap Error: " . $fault->getMessage() . " Soap Trace: " . $fault->getTraceAsString(), "Paypal Soap Fault");
			return false;
		}
		
		$this->transactionID  = $authData->transactionID;
		$this->transactionFee = $authData->feeAmount;
		
		
		if($authData->acknowledge == "Success" && empty($authData->errorCode)){
			
			// If we get a Success message back from Paypal... there should be a valid transaction ID that acompanies it.
			if(!PayPalApiPro::transactionIdIsValid($this->transactionID)){
				WebUtil::WebmasterError("The PayPal Transaction ID was empty with AuthorizeOnly.  PayerID: $this->payerId , Amount: $this->amount , 3rdPartyInvoice: $this->thirdPartyInvoice", "Paypal Empty Tranaction ID AuthorizeOnly");
				$this->transactionStatusChar = "E";
				return false;
			}
		
			$this->transactionStatusChar = "A";
			$this->errorReason = "";
			return true;
		}
		
		if($authData->errorCode == 10417) {
			$this->transactionStatusChar = "D";
			$this->errorReason    = "We were not able to authorize funds through your Paypal account. Try using one of our alternate payment methods.";  
			return false;
		} 
		
		
		$this->responseReasonText = "Paypal Error: " . $authData->errorMessage;
		$this->errorReason    = "Error Connecting to Paypal Server: " . $authData->errorMessageLong;  
		$this->transactionStatusChar = "E";
		return false;

	}
	

	public function AuthorizeCapture(){
		
		throw new Exception("This paypal method has not been implemented yet: AuthorizeCapture()");
	}
	
	
	// This is captures funds against a previous authorization and reauth. if "honor period" of 3 days passed bye already.
	public function PriorAuthCapture($TransactionID){
		
		try{
			$transactionData = $this->paypalObj->capturePartial($TransactionID, $this->amount);
		}
		catch(SoapFault $fault){
			$this->errorReason = "We were not able to connect to the PayPal server.  Please let customer service know if this problem persists.";
			$this->responseReasonText = "Communication Error To Paypal: SoapFault: $fault->getMessage()";
			$this->transactionStatusChar = "E";
			WebUtil::WebmasterError("Possible Communication Error with Paypal? PriorAuthCapture PayerID: $this->payerId , Amount: $this->amount , 3rdPartyInvoice: $this->thirdPartyInvoice Soap Error: " . $fault->getMessage() . $fault->getMessage() . " Soap Trace: " . $fault->getTraceAsString(), "Paypal Soap Fault");
			return false;
		}
		
		$this->transactionFee = $transactionData->feeAmount;
		$this->transactionID  = $transactionData->transactionID;
		
		if($transactionData->acknowledge == "Success" && empty($transactionData->errorCode)){
			
			// If we get a Success message back from Paypal... there should be a valid transaction ID that acompanies it.
			if(!PayPalApiPro::transactionIdIsValid($this->transactionID)){
				WebUtil::WebmasterError("The PayPal Transaction ID was empty with PriorAuthCapture.  Prior Auth Transaction ID: $TransactionID", "Paypal Empty Tranaction ID PriorAuthCapture");
				$this->transactionStatusChar = "E";
				return false;
			}
		
			$this->transactionStatusChar = "A";
			$this->errorReason = "";
			return true;
		}
		

		$this->responseReasonText = "Paypal Error: " . $transactionData->errorMessage;
		$this->errorReason    = "Paypal Error: " . $transactionData->errorMessageLong;  
		$this->transactionStatusChar = "E";
		return false;
	}
	
	public function Credit($TransactionID){
	
		$fullAmount = $this->amount + $this->freight + $this->tax;
		
		try{
			$refundData = $this->paypalObj->refundPartialPayment($TransactionID, $fullAmount); 
		}
		catch(SoapFault $fault){
			$this->errorReason = "We were not able to connect to the PayPal server.  Please let customer service know if this problem persists.";
			$this->responseReasonText = "Communication Error To Paypal: SoapFault: $fault->getMessage()";
			$this->transactionStatusChar = "E";
			WebUtil::WebmasterError("Possible Communication Error with Paypal? PriorAuthCapture - Credit: $this->payerId , Amount: $this->amount , 3rdPartyInvoice: $this->thirdPartyInvoice Soap Error: " . $fault->getMessage() . " Soap Trace: " . $fault->getTraceAsString(), "Paypal Soap Fault");
			return false;
		}

		$this->transactionFee = $refundData->feeAmount;
		$this->transactionID = $refundData->transactionID;
		
		
		if($refundData->acknowledge == "Success" && empty($refundData->errorCode)){
			
			// If we get a Success message back from Paypal... there should be a valid transaction ID that acompanies it.
			if(!PayPalApiPro::transactionIdIsValid($this->transactionID)){
				WebUtil::WebmasterError("The PayPal Transaction ID was empty with Credit.  Prior Capture Transaction ID: $TransactionID", "Paypal Empty Tranaction ID Credit");
				$this->transactionStatusChar = "E";
				return false;
			}
		
			$this->transactionStatusChar = "A";
			$this->errorReason = "";
			return true;
		}
		
		$this->responseReasonText = "Paypal Error: " . $refundData->errorMessage;
		$this->errorReason   = "Paypal Error: " . $refundData->errorMessageLong;  
		$this->transactionStatusChar = "E";
		return false;
	}	

	public function GetTransactionID(){
		return $this->transactionID;
	}
	
	// Return 'A'accepted, 'D'eclined, or 'E'rror
	public function GetTransactionStatus(){
		return $this->transactionStatusChar;
	}
	
	// Returns an HTML string, with complete sentence(s) ... ready to be returned directly to the user
	public function GetErrorReason(){
		return $this->errorReason;
	}
	
	public function GetApprovalCode(){
		return $this->approvalCode;
	}
	// A 1 character description of an error or declined reasoning.
	public function GetResponseReasonCode(){
		return $this->responseReasonCode;
	}
	// A short description of the error or declined reasoning
	public function GetResponseReasonText(){
		return $this->responseReasonText;
	}
	
	// Returns a decimal number without any commas in the thousands place
	// Also Ensures the number is in a correct format
	private function formatPrice($ThePrice){
	
		$FormatedPrice = number_format($ThePrice, 2, '.', '');
		
		if(!preg_match("/^((\d+\.\d+)|0)$/", $FormatedPrice))
			throw new Exception("A price format is not correct.  The amount is: " . $ThePrice);
			
		return $FormatedPrice;
	}

}
?>