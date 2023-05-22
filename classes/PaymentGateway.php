<?


// This is used for communicating with Authorize.net for Credit Card and other banking transactions.
// This class does not communicate with the database in any way.  
// If you are doing refunds or captures... you need to keep track of the corresponding transaction IDs in other code.
class PaymentGateway {


	private $_ErrorReason; //For containing a customized error message to be displyed to user
	private $_TransactionID;
	private $_ApprovalCode;
	private $_ResponseReasonText;
	private $_ResponseReasonCode;
	
	private $_CommissionTransactionBase;
	
	private $_PaymentGatewayHost;
	private $_TransactionStatusChar;  //May be `A' accepted, `D' declined, or `E' error
	
	private $x_Version;
	private $x_ADC_Delim_Data;
	private $x_ADC_URL;
	private $x_Login;
	private $x_Password;
	private $x_test_request;
	
	private $x_Amount;
	private $x_Card_Num;
	private $x_Exp_Date;
	private $x_First_Name;
	private $x_Last_Name;
	private $x_Address;
	private $x_City;
	private $x_State;
	private $x_Zip;
	private $x_Country;
	private $x_Email;
	private $x_Phone;
	private $x_Customer_IP;
	private $x_Freight;
	private $x_Tax;
	private $x_invoice_num;
	private $x_type;
	private $x_trans_id;  //Required for PRIOR_AUTH_CAPTURE  and CREDIT 
	
	private $domainID;


	###-- Constructor --###
	function PaymentGateway($domainID){
		
		if(!Domain::checkIfDomainIDexists($domainID))
			throw new Exception("Error in PaymentGateway constructor. The Domain ID does not exist.");
			
		$this->domainID = $domainID;
		
		// Setup some default values for communicating with the bank --#
		$this->x_Version = "3.1";
		$this->x_ADC_Delim_Data = "true";
		$this->x_ADC_URL = "false";
		$this->x_test_request = "FALSE";
		$this->_PaymentGatewayHost = "https://secure.authorize.net/gateway/transact.dll";
		
		//Whatever the going rate is with our merchant provider		
		$this->_CommissionTransactionBase = 0.25;
		
		$this->x_Customer_IP = WebUtil::getRemoteAddressIp();
		
		$this->_TransactionID = "";
		$this->_ResponseReasonText = "";
		$this->_ResponseReasonCode = "";
		$this->_ApprovalCode = "";
		$this->_ErrorReason = "";
		$this->_TransactionStatusChar = "E"; //Start off as an error, let it prove otherwise
		
		$this->x_Card_Num = "";
		$this->x_type = "";
		$this->x_Amount = "";
		$this->x_trans_id = "";
	}
	
	
	// Just hard code for now... whatever the going rate is with our Merchant Service Provider
	function GetCommisionPercentage(){
	
		$CardType = $this->GetCreditCardType();
		
		if($CardType == "M")
			return 0.0235;
		else if($CardType == "V")
			return 0.0235;
		else if($CardType == "D")
			return 0.0295;
		else if($CardType == "A")
			return 0.0385;
		else if($CardType == "J")
			return 0.0295;
		else if($CardType == "I")
			return 0.0295;
		else{
			//for unknown types
			return 0.03;
		}
		
	}


	// Set the login depending the transaction type and ID. 
	// In case you switch merchant providers, this will allow you to issue refunds ( possibily going to the previous merchant) and have new transactions go to the new merchants
	function SetLogin(){
		
		$domainGatewayObj = new DomainGateways($this->domainID);
	
		// The main or current merchant logi
		$this->x_Login = $domainGatewayObj->paymentGatewayLogin1;
		$this->x_Password = $domainGatewayObj->paymentGatewayPassword1;

		if($this->x_type == "PRIOR_AUTH_CAPTURE" || $this->x_type == "CREDIT"){
			
			// Look for a transaction IDs before we switched merchants...
			// Defining "previous merchant accounts" is optional. 
			// If a previous mercahnt has not been defined within "DomainGateway"... then the "Max TransactionID" will be zero and therefore the x_trans_id will not be "less than or equal to"
			if($this->x_trans_id <= $domainGatewayObj->paymentGateway2_MaxTransactionID){
					
				$this->x_Login = $domainGatewayObj->paymentGatewayLogin2;
				$this->x_Password = $domainGatewayObj->paymentGatewayPassword2;
					
				// If there is a Previous Merchant (3rd level) see, if the transaction ID is less than the 2nd level.
				if($this->x_trans_id <= $domainGatewayObj->paymentGateway3_MaxTransactionID){
							
					$this->x_Login = $domainGatewayObj->paymentGatewayLogin3;
					$this->x_Password = $domainGatewayObj->paymentGatewayPassword3;
							
					// If there is a Previous Merchant (4rth level) see, if the transaction ID is less than the 3rd level.
					if($this->x_trans_id <= $domainGatewayObj->paymentGateway4_MaxTransactionID){
						$this->x_Login = $domainGatewayObj->paymentGatewayLogin4;
						$this->x_Password = $domainGatewayObj->paymentGatewayPassword4;
					}
				}
			}
		}	
	}
	
	

	// Ensure that the methods have been called in proper sequence before firing off a transaction --#
	function CheckTransactionInfoHasBeenSet(){
	
		if($this->x_Card_Num == "")
			throw new Exception("Transaction Information has not been set yet.");
		
		if($this->x_Amount == "")
			throw new Exception("Amounts have not been set yet for the Transaction data.");
		
		if($this->x_type == "CREDIT" || $this->x_type == "PRIOR_AUTH_CAPTURE"){
			if(!preg_match("/^\d+$/", $this->x_trans_id))
				throw new Exception("A transaction ID is required for captures and credits.");
		}
	}
	
	function GetMerchantCommissionFee(){
	
		if($this->x_type == "CREDIT" || $this->x_type == "AUTH_CAPTURE"|| $this->x_type == "AUTH_ONLY"){
			$Fee = round(($this->GetCommisionPercentage() * $this->x_Amount),2);
			$Fee += $this->_CommissionTransactionBase;
		}
		else{
			$Fee = 0;
		}
		return $this->_FormatPrice($Fee);
	}



	// Returns the type of credit card used --#
	// M = mastercard  ---  V = Visa  ---   A = American Express  --- J = JCB --- D = Discover --- I = Diners  --- U = Unknown
	private function GetCreditCardType(){

		#--- These are the following rules for how to determine the brand of a credit card, based off of it's number 
		//	mastercard:		Must have a prefix of 51 to 55, and must be 16 digits in length. 
		//	Visa: 			Must have a prefix of 4, and must be either 13 or 16 digits in length. 
		//	American Express: 	Must have a prefix of 34 or 37, and must be 15 digits in length. 
		//	Diners Club:		Must have a prefix of 300 to 305, 36, or 38, and must be 14 digits in length. 
		//	Discover: 		Must have a prefix of 6011, and must be 16 digits in length. 
		//	JCB: 			Must have a prefix of 3, 1800, or 2131, and must be either 15 or 16 digits in length.
		

		$CardNumber = $this->x_Card_Num;

		if(strlen($CardNumber) == 16){
			if(preg_match("/^5[1-5]\d+$/", $CardNumber)){
				return "M";
			}
			else if(preg_match("/^4\d+$/", $CardNumber)){
				return "V";
			}
			else if(preg_match("/^(3|1800|2131)\d+$/", $CardNumber)){
				return "J";
			}
			else if(preg_match("/^6011\d+$/", $CardNumber)){
				return "D";
			}
		
		}
		else if(strlen($CardNumber) == 15){
			//Always check for American Express first... since it is more greedy
			if(preg_match("/^(34|37)\d+$/", $CardNumber)){
				return "A";
			}
			else if(preg_match("/^(3|1800|2131)\d+$/", $CardNumber)){
				return "J";
			}
		}
		else if(strlen($CardNumber) == 14){
			if(preg_match("/^(300|30[1-5]|36|38)\d+$/", $CardNumber)){
				return "I";
			}
		}	
		else if(strlen($CardNumber) == 13){
			if(preg_match("/^4\d+$/", $CardNumber)){
				return "V";
			}
		}
		
		// Well the Card type is unknown
		return "U";
	}
	



	#---- Set Properties ------#
	
	function setCreditCardInfo($creditCardObj){
		
		if(get_class($creditCardObj) != "CreditCard"){
			throw new Exception("The credit card object belongs to the wrong class");
		}
	
		$FullNamePartsHash = UserControl::GetPartsFromFullName($creditCardObj->getBillingName());
		
		$this->x_Card_Num = $creditCardObj->getCardNumber();
		$this->x_Exp_Date = $creditCardObj->getExpirationDate();
		$this->x_First_Name = $FullNamePartsHash["First"];
		$this->x_Last_Name = $FullNamePartsHash["Last"];
		$this->x_Address = $creditCardObj->getAddressFull();
		$this->x_City = $creditCardObj->getBillingCity();
		$this->x_State = $creditCardObj->getBillingState();
		$this->x_Zip = $creditCardObj->getBillingZip();
		$this->x_Country = $creditCardObj->getBillingCountry();
	}
	
	function setEmailAddress($x){
		if(!WebUtil::ValidateEmail($x))
			throw new Exception("Invalid email");
		$this->x_Email = $x;
		
	}
	function setPhone($x){
		$this->x_Phone = $x;
	}
	
	function setInvoiceNumber($x){
		$this->x_invoice_num = $x;
	}
	
	// Takes in a hash of information that we want to use for the transaction... does error checking, returns nothing
	function SetTransactionData($OrderInfoHash){
	
		if(!isset($OrderInfoHash["Card_Num"]))
			throw new Exception("Card_Num was not sent");
		else
			$this->x_Card_Num = $OrderInfoHash["Card_Num"];

		if(!isset($OrderInfoHash["Exp_Date"]))
			throw new Exception("Exp_Date was not sent");
		else
			$this->x_Exp_Date = $OrderInfoHash["Exp_Date"];

		if(!isset($OrderInfoHash["First_Name"]))
			throw new Exception("First_Name was not sent");
		else
			$this->x_First_Name = $OrderInfoHash["First_Name"];

		if(!isset($OrderInfoHash["Last_Name"]))
			throw new Exception("Last_Name was not sent");
		else
			$this->x_Last_Name = $OrderInfoHash["Last_Name"];

		if(!isset($OrderInfoHash["Address"]))
			throw new Exception("Address was not sent");
		else
			$this->x_Address = $OrderInfoHash["Address"];

		if(!isset($OrderInfoHash["City"]))
			throw new Exception("City was not sent");
		else
			$this->x_City = $OrderInfoHash["City"];

		if(!isset($OrderInfoHash["State"]))
			throw new Exception("State was not sent");
		else
			$this->x_State = $OrderInfoHash["State"];

		if(!isset($OrderInfoHash["Zip"]))
			throw new Exception("Zip was not sent");
		else
			$this->x_Zip = $OrderInfoHash["Zip"];
	
		if(!isset($OrderInfoHash["Country"]))
			throw new Exception("Country was not sent");
		else
			$this->x_Country = $OrderInfoHash["Country"];

		if(!isset($OrderInfoHash["Email"]))
			throw new Exception("Email was not sent");
		else
			$this->x_Email = $OrderInfoHash["Email"];

		if(!isset($OrderInfoHash["Phone"]))
			throw new Exception("Phone was not sent"); 
		else
			$this->x_Phone = $OrderInfoHash["Phone"];

		
		// Order number is optional... For new authorizations we don't even have an order ID yet -#
		if(!isset($OrderInfoHash["InvoiceNum"]))
			$this->x_invoice_num = "";
		else
			$this->x_invoice_num = $OrderInfoHash["InvoiceNum"];

	}

	// Set the prices of the transaction --#
	function SetPaymentGatewayAmounts($Amount, $Tax, $Freight){
	
		$this->x_Amount = $this->_FormatPrice($Amount);
		$this->x_Freight = $this->_FormatPrice($Freight);
		$this->x_Tax = $this->_FormatPrice($Tax);
	}
	
	


	#-- If PHP 4.3 supported interfaces, I would probably enforce all PaymentGateways / Corporate Billing Classes to support the following methods
	

	##---- Begin Gateway Interface ---- ##
	
	// Returns TRUE if the authorization succeeded, FALSE if failed --#
	function AuthorizeOnly(){
	
		// First make sure that we are not trying to authorize $0.00 and contact Auth.net unessesarily .  
		// The software calling this method should check and make sure they are 
		if($this->x_Amount == 0)
			throw new Exception("You can not AuthorizeOnly with a zero payment");

		$this->x_type = "AUTH_ONLY";
		$this->x_trans_id = "";

		return $this->_FireTransaction();

	}
	function AuthorizeCapture(){
	
		$this->x_type = "AUTH_CAPTURE";
		$this->x_trans_id = "";

		return $this->_FireTransaction();
	}
	function PriorAuthCapture($TransactionID){
	
		$this->x_type = "PRIOR_AUTH_CAPTURE";
		$this->x_trans_id = $TransactionID;

		return $this->_FireTransaction();
	}
	function Credit($TransactionID){
	
		$this->x_type = "CREDIT";
		$this->x_trans_id = $TransactionID;
		
		// There is a bug with Auth.net.  
		// If you put in a value for freight on a refund... the server hangs, even if it is 0.00.  It has to be NULL!
		$this->x_Freight = "";

		return $this->_FireTransaction();
	}	

	

	

	function GetTransactionID(){
		return $this->_TransactionID;
	}
	
	// Return 'A'accepted, 'D'eclined, or 'E'rror
	function GetTransactionStatus(){
		return $this->_TransactionStatusChar;
	}
	
	// Returns an HTML string, with complete sentence(s) ... ready to be returned directly to the user
	function GetErrorReason(){
		return $this->_ErrorReason;
	}
	
	function GetApprovalCode(){
		return $this->_ApprovalCode;
	}
	// A 1 character description of an error or declined reasoning.
	function GetResponseReasonCode(){
		return $this->_ResponseReasonCode;
	}
	// A short description of the error or declined reasoning
	function GetResponseReasonText(){
		return $this->_ResponseReasonText;
	}
	
	
	##---- END Gateway Interface ---- ##
	
	
	
	
	
	
	
	#---------  Private Methods below ------#


	function _FireTransaction(){


		// It could take a while to communicate with the bank... Make sure the PHP script can run for at least 10 minutes.
		set_time_limit(600);

	
		$this->CheckTransactionInfoHasBeenSet();
		
		// For debugging... We can not communicate with A.net on the development PC --#
		if(Constants::GetDevelopmentServer()){
			$this->_TransactionID = "2342343";
			$this->_ResponseReasonText = "Card Was Declined";
			$this->_ResponseReasonCode = "0";
			$this->_ApprovalCode = "56456";
			$this->_TransactionStatusChar = "A"; 
			$this->_ErrorReason = "This is a development system and your credit card was declined because of a Duplicate Transaction.";
			
			//For testing.. if the transaction is a success or not
			return true;
		}
	
		$PostString = $this->_GetPostString();

		$this->_TransactionID = "";
		$this->_ApprovalCode = "";
		$this->_ResponseReasonText = "";
		$this->_ResponseReasonCode = "";
		$this->_TransactionStatusChar = "E"; //Start off as an error, let it prove otherwise
		$this->_TransactionID = "0";
		$this->_ErrorReason = "An unknown error has occured.  It is possible that the Payment server is down or unreachable";
		
		$return_value = "";
		
		// -m 60 lets us have up to 1 mintues to get a response back from the server.
		// -k means the server doesn't need a valid certificate to connect in SSL mode.
		// -d means that we are posting data.
		exec(Constants::GetCurlCommand() . " -m 60 -k -d \"$PostString\" $this->_PaymentGatewayHost", $return_value);

		if(!is_array($return_value) || !isset($return_value[0])){
			$this->_TransactionStatusChar = "E";
			$this->_ErrorReason = "An error occurred making a secure connection to our payment institution. The payment gateway, Authorize.net, may be experiencing difficulties. This problem has been reported to the webmaster.  Your credit card has not been charged.  You should save your project(s) and then try again later.  Visit your shopping cart to save your project(s).";
			return false;
		}

		// Split the return string from Authorize.net.  The components that are returned have many fields... we are only interested in the first 
		// 0) Response code '1'=approved '2'=Declined '3'=error
		// 1) used interally by A.net
		// 2) Response Reason Code
		// 3) Response Reason text
		// 4) Approval Code - 6 digit authorization or approval code
		// 5) Avs Result Code - Address Verification... Zip code mismatch, street address, etc
		// 6) Transaction ID - Identifies transaction in the system and is needed for voiding, crediting, or capturing at a later time
		$response_components = split(",",$return_value[0]);

		// Just a very loose check.  We are supposed to get back 68 fields from the server but I dont want to check that closely --#
		if(sizeof($response_components) < 60 || sizeof($response_components) > 80){
			$this->_TransactionStatusChar = "E";
			$this->_ErrorReason = "There was a problem communicating with the bank. Their server appears to be down or unreachable. This problem has been reported to the webmaster.  Your credit card has not been charged.  You may want to save your project and then try back in a little bit.";
			return false;
		}
		else{
			//This may contain some detailed information if the card is declined.. for successful transactions, it doesn't do much
			$ResponseNumber = $response_components[0];
			$this->_ResponseReasonCode = $response_components[2];
			$this->_ResponseReasonText = $response_components[3];
			$this->_ApprovalCode = $response_components[4];
			$AVScode = $response_components[5];
			$this->_TransactionID = ( $response_components[6] == "" ) ? "0" : $response_components[6] ;  //If there is no transaction ID... then we will mark it as a 0 for our records... this may happen on an error
		}



		// Check for errors in the response --#
		if($ResponseNumber == 3){
			$this->_TransactionStatusChar = "E";
			
			if($this->_ResponseReasonCode == 11)
				$this->_ErrorReason = "A duplicate transaction has been submitted.  This may happen if you try to submit identical orders within 3 minutes of each other.  Or your first transaction may have been declined and the payment gateway will not let you try again so quickly.  Your order has not gone through yet.  Please wait about 3 minutes and try again.";
			else if($this->_ResponseReasonCode == 6 || $this->_ResponseReasonCode == 37)
				$this->_ErrorReason = "The credit card number that you submitted appears to be invalid. Please double check the numbers.  If the problem persists then contact Customer Service.";
			else if($this->_ResponseReasonCode == 7)
				$this->_ErrorReason = "The credit card expiration date is invalid.  Double check the expiration date that you entered. If the problem persists, please contact Customer Service.";
			else if($this->_ResponseReasonCode == 8)
				$this->_ErrorReason = "The credit card appears to be expired.  Double check the expiration date that you entered.  If the problem persists, please contact Customer Service.";
			else if($this->_ResponseReasonCode == 9)
				$this->_ErrorReason = "The account number that you submitted appears to be invalid.  Please double check the numbers. If the problem persists then contact Customer Service.";
			else
				$this->_ErrorReason = "There was an error communicating with the bank. Their server appears to be down or unreachable  This problem has been reported to the webmaster.  Your credit card has not been charged.  You may want to save your project(s) and then try back in a little bit.";
				
			$this->_ErrorReason .= "Payment Gateway Description: " . $this->_ResponseReasonText;
			
			return false;
		}
		else if($ResponseNumber == 2){

			// It looks like the credit card was declined if the response component is 2 ---#
			// Let's look into the AVS results and see if we can get a better reason why the charge was declined
		
			// Start with the Status as declined... If we find any errors then we can overwrite to a value or 'E'
			$this->_TransactionStatusChar = "D";
		
			if($AVScode == "U" || $AVScode == "S"){
				$this->_ErrorReason = "We cannot compare the billing information that you entered on the website to the address on file with your credit card.  Your credit card may not support AVS, or Address Verification System.  Our bank uses this information to cut down on fraud.  You may need to try another credit card.  TransactionID: " . $this->_TransactionID;
			}
			else if($AVScode == "N" || $AVScode == "A" || $AVScode == "W" || $AVScode == "Z"){
				$this->_ErrorReason = "The billing address that you entered on the website does not match the address on file with your credit card. Our bank uses this information to cut down on fraud.  Please verify your billing address with a recent credit card statement. If the problem persists then contact customer service. TransactionID: " . $this->_TransactionID;
			}
			else if($AVScode == "G"){
				$this->_ErrorReason = "Your credit card must be issued by a United States bank. Please go back and use another credit card. TransactionID: " . $this->_TransactionID;
			}
			else if($AVScode == "E" || $AVScode == "B"){
				
				// Before I also inluded and AVS Code "R"... System 'R'rety down or unvailable ....  However I have removed it because Authorize.net should not fail on a Code R
				// The settings with Auth.net are setup to ignore a Code R and continue processing the order.  
				// It is possible for us to get a Code R and also have a declined card... In which case we need the error message fall through the the Generic "Your Card Was Declined" message.
				
				$this->_TransactionStatusChar = "E";
				$this->_ErrorReason = "An error occurred with the Payment Gateway comparing your billing addresses.  The system is either down or overloaded.  Code - $AVScode - This problem has been reported to the webmaster.  Your credit card has not been charged.  You should save your project(s) and then try again later.  Visit your shopping cart to save your project(s) if you have not already done so.  TransactionID: " . $this->_TransactionID;
			}
			else{
				// Well we could not find any information in the AVS to explain the reason why the card was declined
				// Let's make a defauled "declined" message
				$this->_ErrorReason = "Your credit card was declined. Please ensure that your name, address, and zip code match the billing address of the credit card. You may also try a different credit card if you can't get this one working. If the problem persists then contact customer service.  TransactionID: " . $this->_TransactionID;
			}
		
			return false;
		}
		else if($ResponseNumber == 1){
		
			// 1 means a successful transactions

			$this->_TransactionStatusChar = "A";  //Accepted
			$this->_ErrorReason = "";

			return true;
		}
		else{
			//This should never happen.. response code should always be a 1, 2, or 3
		
			$this->_TransactionStatusChar = "E";
			$this->_ErrorReason = "An unknown error occurred contacting the payment institution.  Their server appears to be down or unreachable. This problem has been reported to the webmaster.  Your credit card has not been charged yet. You may want to save your project and then try back in a little bit.";
			return false;
		}



	}
	
	
	// This will return the name/value pairs needed for an HTTP POST --#
	function _GetPostString(){
	
		if($this->x_type == "")
			throw new Exception("Transaction Type has not been set yet.");
		
		// Set the user name and password for the login to authorize.net
		$this->SetLogin();
	
		$NV_pairs = array();
	
		##--- Fill up this hash with the information about the order ---#
		$NV_pairs["x_Version"] = $this->x_Version;
		$NV_pairs["x_ADC_Delim_Data"] = $this->x_ADC_Delim_Data;
		$NV_pairs["x_ADC_URL"] = $this->x_ADC_URL;
		$NV_pairs["x_Login"] = $this->x_Login;
		$NV_pairs["x_Password"] = $this->x_Password;
		$NV_pairs["x_test_request"] = $this->x_test_request;
		#---------------------------------------
		$NV_pairs["x_Amount"] = $this->x_Amount;
		$NV_pairs["x_Card_Num"] = $this->x_Card_Num;
		$NV_pairs["x_Exp_Date"] = $this->x_Exp_Date;
		$NV_pairs["x_First_Name"] = $this->x_First_Name;
		$NV_pairs["x_Last_Name"] = $this->x_Last_Name;
		$NV_pairs["x_Address"] = $this->x_Address;
		$NV_pairs["x_City"] = $this->x_City;
		$NV_pairs["x_State"] = $this->x_State;
		$NV_pairs["x_Zip"] = $this->x_Zip;
		$NV_pairs["x_Country"] = $this->x_Country;
		$NV_pairs["x_Email"] = $this->x_Email;
		$NV_pairs["x_Phone"] = $this->x_Phone;
		$NV_pairs["x_Customer_IP"] = $this->x_Customer_IP;
		$NV_pairs["x_Freight"] = $this->x_Freight;
		$NV_pairs["x_Tax"] = $this->x_Tax;
		$NV_pairs["x_tax_exempt"] = "FALSE";
		$NV_pairs["x_type"] = $this->x_type;
		$NV_pairs["x_trans_id"] = $this->x_trans_id;
		$NV_pairs["x_invoice_num"] = $this->x_invoice_num;
		$NV_pairs["x_duty"] = "0.00";
		

		



		// Convert Hash Into a Name/value pairs for the POST --#
		$POST_string = "";
		foreach ($NV_pairs as $HashKey => $HashValue) {

			#-- Get rid of any commas within the values.  This could screw up the delimination of the return --#
			$HashValue = preg_replace("/,/", "", $HashValue);

			if($POST_string == "")
				$POST_string = $HashKey . "=" . urlencode($HashValue);
			else
				$POST_string .= "&" . $HashKey . "=" . urlencode($HashValue);
		}
		
		return $POST_string;
	}

	// Returns a decimal number without any commas in the thousands place
	// Also Ensures the number is in a correct format
	function _FormatPrice($ThePrice){
	
		$FormatedPrice = number_format($ThePrice, 2, '.', '');
		
		if(!preg_match("/^((\d+\.\d+)|0)$/", $FormatedPrice))
			throw new Exception("A price format is not correct.  The amount is: " . $ThePrice);
			
		return $FormatedPrice;
	}
}




?>