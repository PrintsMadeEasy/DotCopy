<?
// This class is meant to abstract the different payment gateways that we might use.
// For example, for Normal users we may be paying by credit card through Authorize.net, but for Corporate users have a credit limit with our company
// Both forms of payment need to be authorized before letting a transaction go through.
// The inteface to this class works the same for all payment types.  If figures out how to handle the request based upon the User or Order data.
class Payments {


	private $_dbCmd;
	private $_ErrorReason; //HTML complete sentence for displaying error messages to the user
	private $_StatusChar;  //May be `A' accepted, `D' declined, or `E' error

	private $_BillingType; // Will be 'C'orporate or 'N'ormal or 'P'aypal     Normal means authorize a credit card and Corporate means do invoicing

	private $_TotalCharge;
	private $_FreightCharge;
	private $_TaxCharge;
	
	private $_TranactionDataSetFlag;  // Bool
	private $_UserLoadedFlag;  // Bool

	private $_ChargeType; //Single character that we track in our "charges" table by..  `A'=Authorization, `C'=Capture, `B'=Both authorize & capture `R'=refund, `Z'=Zero balance.. does not need an authorization 
	private $_PaymentGatewayObj; // Object for communicating with Authorize.net
	private $_UserControlObj;  // Object that has access to all User data
	private $_PaymentInvoiceObj;  // Object for seeking Credit limit approvals for corporate invoicing
	
	private $_GatewayResponseReasonCode; //May be an integer ranging between 1 and 160 containing the reasons for a declined card
	private $_GatewayResponseReasonText; //A text explanation of the ResponseReasonCode
	private $_GatewayTransactionID; // The resulting Transaction ID that comes from the payment gateway after a charge has been issued
	private $_GatewayApprovalCode; // We are not using the property at the moment.  We are just recording it in our DB in case Authorize.net ever needs it for something.
	
	private $paypalToken;
	private $paypalPayerId;
	private $thirdPartyInvoice;
	
	private $linked_OrderID;
	private $linked_LoyaltyID;
	
	


	
	###-- Constructor --###
	function Payments($domainID){
		
		if(!Domain::checkIfDomainIDexists($domainID))
			throw new Exception("Error in Payments constructor. The DomainID doesn't exist: $domainID");

		$this->_dbCmd = new DbCmd();
		$dbCmd2 = new DbCmd();
		$this->_UserControlObj = new UserControl($dbCmd2);
		$this->_PaymentGatewayObj = new PaymentGateway($domainID);
		$this->_PaypalGatewayObj  = new PaypalGateway($domainID);
		$this->_PaymentInvoiceObj = new PaymentInvoice();
		
		$this->_TranactionDataSetFlag = false;
		$this->_TotalCharge = "";
		$this->_ChargeType = "";
		$this->_BillingType = "";
		
		$this->_UserLoadedFlag = false;
		
		$this->_ClearResponses();
	}
	
	function setOrderIdLink($x){
		WebUtil::EnsureDigit($x, true, "Order Numbers must be numberic.");
		$this->linked_OrderID = $x;
	}
	function setLoyaltyIdLink($x){
		WebUtil::EnsureDigit($x, true, "Loyalty Charge ID's must be numberic.");
		$this->linked_LoyaltyID = $x;
	}

	function setBillingTypeOverride($paymentType) {
		if(!in_array($paymentType, array("P", "C", "N")))
			throw new Exception("Illegal Payment Type");
			
		$this->_BillingType = $paymentType;	
	}
	
	public function getThirdPartyInvoice() {
		return $this->thirdPartyInvoice;
	}
	
	public function setPaypalTokenPayerID($token, $payerId){
	
		$this->paypalToken   = $token;
		$this->paypalPayerId = $payerId;
	}
	
	function getBillingType(){
		if(empty($this->_BillingType))
			throw new Exception("The billing type has not been loaded");
		return $this->_BillingType;
	}
	
	function setCreditCardInfo($creditCardObj, $invoiceNumber = ""){
		if(get_class($creditCardObj) != "CreditCard")
			throw new Exception("The credit card object belongs to the wrong class");
			
		$this->_PaymentGatewayObj->setCreditCardInfo($creditCardObj);
		$this->_PaymentGatewayObj->setEmailAddress($this->_UserControlObj->getEmail());
		$this->_PaymentGatewayObj->setPhone($this->_UserControlObj->getPhone());
		$this->_PaymentGatewayObj->setInvoiceNumber($invoiceNumber);
		
		$this->_TranactionDataSetFlag = true;

	}
	
	// This will make a record... informing the system that funds are ready to be captured.
	// A cron job may run in the background to do the communication with the merchant
	// Doing it this way will prevent slow performance if an offline system if completing orders on the fly.
	function OrderNeedsPaymentCapture($OrderID){
	
		//Load billing information from the Database.
		$this->LoadBillingInfoByOrderID($OrderID);

		$this->SetTransactionAmounts("0.00", "0.00", "0.00");
		
		$this->_ClearResponses();
		
		$this->_ChargeType = "N";  //Set charge type to N ... for "needs catpure".
		$this->_StatusChar = "N";
		
		$this->_GatewayTransactionID = "0";
		$this->_GatewayApprovalCode = "0";
		$this->_GatewayResponseReasonCode = "";
		$this->_GatewayResponseReasonText = "";
		
		$this->setLoyaltyIdLink(0);
		$this->setOrderIdLink($OrderID);
		$this->CreateNewTransactionRecord(0);
	}

	// Capture funds against a previous authorization.  The TransactionID should match the ID from the authorization.
	function CaptureFunds($TransactionID){
	
		if($this->_BillingType != "P")
			$this->_ValidateDigit($TransactionID);
		
		if(($this->_BillingType == "P") && (!PayPalApiPro::transactionIdIsValid($TransactionID)))	
			throw new Exception("Invalid Paypal TransactionID :" . $TransactionID);
		
		$this->_ChargeType = "C";
	
		$this->_ClearResponses();
	
		$this->_EnsureTransactionInfoHasBeenSet();
		
		if($this->_BillingType == "N")
			$transResult = $this->_PaymentGatewayObj->PriorAuthCapture($TransactionID);
		else if($this->_BillingType == "C")
			$transResult = $this->_PaymentInvoiceObj->PriorAuthCapture($TransactionID);
		else if($this->_BillingType == "P")
			$transResult = $this->_PaypalGatewayObj->PriorAuthCapture($TransactionID);
		else
			throw new Exception("Error with Billing Type");		
		
		$this->_CollectPaymentResponses();

		return $transResult;
	}

	// Refund against a previous Capture... or against an Auth/Capture
	function RefundPayment($TransactionID){
	
		if($this->_BillingType != "P")
			$this->_ValidateDigit($TransactionID);
		
		if(($this->_BillingType == "P") && (!PayPalApiPro::transactionIdIsValid($TransactionID)))	
			throw new Exception("Invalid Paypal TransactionID :" . $TransactionID);
		
		$this->_ChargeType = "R";
	
		$this->_ClearResponses();
	
		$this->_EnsureTransactionInfoHasBeenSet();

		if($this->_BillingType == "N")
			$transResult = $this->_PaymentGatewayObj->Credit($TransactionID);
		else if($this->_BillingType == "C")
			$transResult = $this->_PaymentInvoiceObj->Credit($TransactionID);
		else if($this->_BillingType == "P")
			$transResult = $this->_PaypalGatewayObj->Credit($TransactionID);
		else
			throw new Exception("Error with Billing Type");
			
		$this->_CollectPaymentResponses();

		return $transResult;	
	}
	


	// Get an approval for funds... but don't collect the final payment yet.
	function AuthorizeFunds(){

		$this->_ClearResponses();
		
		$this->_EnsureTransactionInfoHasBeenSet();
		
		
		if($this->_checkForSingleOrderLimitError())
			return false;

	
		// First make sure that we are not trying to authorize $0.00 and contact Auth.net unessesarily .  
		// This could happen on a free reprint for example
		// Authorizing $0.00 is always a success
		if($this->_TotalCharge == 0){

			$this->_ChargeType = "Z";  //Stands for "Zero" authorization
			$this->_ErrorReason = "";
			$this->_StatusChar = "A";

			return true;
		}
		else{

			$this->_ChargeType = "A";

			if($this->_BillingType == "N"){
				$transResult = $this->_PaymentGatewayObj->AuthorizeOnly();
			}
			else if($this->_BillingType == "C"){
				$transResult = $this->_PaymentInvoiceObj->AuthorizeOnly();
			}
			else if($this->_BillingType == "P") {
				
				$this->_PaypalGatewayObj->SetTokenPayerID($this->paypalToken, $this->paypalPayerId);
				
				$transResult = $this->_PaypalGatewayObj->AuthorizeOnly();
				
				$this->thirdPartyInvoice = $this->_PaypalGatewayObj->getThirdPartyInvoice();
			}
			else{
				throw new Exception("Error with Billing Type");	
			}
			
			$this->_CollectPaymentResponses();
			return $transResult;
		}
	}
	
	// Authorize Funds and collect at the same time.
	function AuthorizeAndCapture(){
	
		$this->_ChargeType = "B";
	
		$this->_ClearResponses();
	
		$this->_EnsureTransactionInfoHasBeenSet();
		
		if($this->_checkForSingleOrderLimitError())
			return false;

		if($this->_BillingType == "N"){
			$transResult = $this->_PaymentGatewayObj->AuthorizeCapture();
		}
		else if($this->_BillingType == "C"){
			$transResult = $this->_PaymentInvoiceObj->AuthorizeCapture();
		}
		else if($this->_BillingType == "P") {
			
			$this->_PaypalGatewayObj->SetTokenPayerID($this->paypalToken, $this->paypalPayerId);
			
			$transResult = $this->_PaypalGatewayObj->AuthorizeCapture();
			
			$this->thirdPartyInvoice = $this->_PaypalGatewayObj->getThirdPartyInvoice();
		}	
		else{
			throw new Exception("Error with Billing Type");
		}
		
		$this->_CollectPaymentResponses();
		return $transResult;
	}	
	

	
	// You can either set the billing info by passing in a hash with user information
	// Or if you already have an OrderID... it is easier to set the billing info by calling the method "LoadBillingInfo"
	function SetBillingInfo($OrderInfoHash){
	
		if(!is_array($OrderInfoHash))
			throw new Exception("Transacrion data must be set with a hash.");

		$this->_CheckForEmptyBillingType();

		// Forward the Transaction data to the PaymentGateway, it is not needed for our Invoice system
		if($this->_BillingType == "N")
			$this->_PaymentGatewayObj->SetTransactionData($OrderInfoHash);
		
		$this->_TranactionDataSetFlag = true;
	}
	

	// This method must be called before a transaction is made.  It will load the User infomration so that we
	// can determine what type of billing to use.
	// You can manually force a Corporate Billing type...  this may be usesful if the customer does not have corporate billing
	// ... enabled on their account... but they are carrying forward a positive balance on their account.
	// In this case it would kind work like giving the customer control to momentarily switch their account to corporate billing to put the order through and use up some of their balance.
	function LoadCustomerByID($UserID, $forceCorporateBilling = false){
			
		$this->_UserControlObj->LoadUserByID($UserID, false);
			
		// This is probably unessecary most of the time.
		// The UserControlObect is passed by reference into this class and it is probably the same UserControlObj used by the PaymentInvoice class
		// However, we are better off loading customer data twice just to be safe.
		$this->_PaymentInvoiceObj->LoadCustomerByID($UserID);
		
		$this->_BillingType = $this->_UserControlObj->getBillingType();
		
		if($forceCorporateBilling)
			$this->_BillingType = "C";
		
		$this->_UserLoadedFlag = true;
	}


	// We can load the customers data, necessary to make a transaction from  an existing order ID
	// This function will NOT load the amount, tax, or freight for the transaction.  You need to set those in another function --#
	// This will also Load the User information into the UserControlObject
	function LoadBillingInfoByOrderID($OrderID){
	
		$this->_ValidateDigit($OrderID);
		
		$this->_dbCmd->Query("SELECT * FROM orders WHERE ID=$OrderID");
		
		if($this->_dbCmd->GetNumRows() == 0)
			throw new Exception("Order ID: $OrderID is not valid.");
		
		$OrderRow = $this->_dbCmd->GetRow();
		
		$this->LoadCustomerByID($OrderRow["UserID"]);

		$CC_Exp = $OrderRow["MonthExpiration"] . $OrderRow["YearExpiration"];

		$FullNamePartsHash = UserControl::GetPartsFromFullName($OrderRow["BillingName"]);
		
		//Combine Address 1 and Address 2
		$BillingAddress = $OrderRow["BillingAddress"] . " " . $OrderRow["BillingAddressTwo"]; 
		
		$CustomerInfoHash["Card_Num"] = $OrderRow["CardNumber"];
		$CustomerInfoHash["Exp_Date"] = $CC_Exp;
		$CustomerInfoHash["First_Name"] = $FullNamePartsHash["First"];
		$CustomerInfoHash["Last_Name"] = $FullNamePartsHash["Last"];
		$CustomerInfoHash["Address"] = $BillingAddress;
		$CustomerInfoHash["City"] = $OrderRow["BillingCity"];
		$CustomerInfoHash["State"] = $OrderRow["BillingState"];
		$CustomerInfoHash["Zip"] = $OrderRow["BillingZip"];
		$CustomerInfoHash["Country"] = $OrderRow["BillingCountry"];
		$CustomerInfoHash["InvoiceNum"] = $OrderID;
		$CustomerInfoHash["Email"] = $this->_UserControlObj->getEmail();
		$CustomerInfoHash["Phone"] = $this->_UserControlObj->getPhone();

		// Need to Overwrite the billingtype from the users data
		// Once an order starts out using a certain billing type, it needs to continue that way
		// This includes, captures, refunds, and even re-prints
		$this->_BillingType = $OrderRow["BillingType"];
			
		$this->SetBillingInfo($CustomerInfoHash);
	}




	// Set the prices of the transaction
	// This could have been done as parameters directly to the Authorize() and Capture methods... 
	//  .... but sometimes you have to also deal with transaction ID's, I think this seems cleaner to set the amounts separately
	function SetTransactionAmounts($Amount, $Tax, $Freight){
	
		$Amount = $this->_FormatPrice($Amount);
		$Tax = $this->_FormatPrice($Tax);
		$Freight = $this->_FormatPrice($Freight);
		
		$this->_TotalCharge = $Amount;
		$this->_FreightCharge = $Freight;
		$this->_TaxCharge = $Tax;
		
		$this->_CheckForEmptyBillingType();
		
		if($this->_BillingType == "N")
			$this->_PaymentGatewayObj->SetPaymentGatewayAmounts($Amount, $Tax, $Freight);
		else if($this->_BillingType == "C")
			$this->_PaymentInvoiceObj->SetAuthorizationAmount($Amount);
		else if($this->_BillingType == "P")
			$this->_PaypalGatewayObj->SetPaypalGatewayAmounts($Amount, $Tax, $Freight);
		else
			throw new Exception("Error with Billing Type");
	}
	
	
	

	// Takes in the Order number and new amounts that we want to re-authorize --#
	// Returns TRUE if the authorization succeeded, FALSE if failed --#
	// Will figure out if the previous authorization is equal to or more than the new order total... 
	// if it is less then it will go out an get a new authorization.. this costs money though.. so it only reauthorizes when it needs to.
	// Return true if the order was able to be reauthorized... false if not
	function AuthorizeNewAmountForOrder($OrderID, $NewGrandTotal, $TaxTotal, $FreightTotal){
	
		$this->_EnsureChargeIDlinkDoesntExistForOrder($OrderID);

		// We may need to capture funds across multiple authorizations.   We need to now how much we have authorized so far... so we can figure out the difference if needed.
		$TotalAuthAmounts = $this->_GetTotalAmountOfAuthorizations($OrderID, "GrandTotal");
		$TotalFreightAmounts = $this->_GetTotalAmountOfAuthorizations($OrderID, "FreightTotal");
		$TotalTaxAmounts = $this->_GetTotalAmountOfAuthorizations($OrderID, "TaxTotal");


		if(Math::checkIfFirstFloatParamIsLessOrEqual($NewGrandTotal, $TotalAuthAmounts)){
			return true;
		}
		else{
		
			// We Can't authorize new funds on a Paypal payment.
			if(Order::getOrderBillingType($OrderID) == "P") {
				$this->_ErrorReason = "This action would cause an increase to the Grand Total. Paypal doesn't allow additional authorizations.";
				return false;
			}
		
			//Find out the difference that we need to authorize on the card
			$GrandTotalDiff = $NewGrandTotal - $TotalAuthAmounts;
			
			//Figure out if any more freight needs to be added from our previous authorization
			if(Math::checkIfFirstFloatParamIsLessOrEqual($FreightTotal, $TotalFreightAmounts))
				$FreightDiff = 0.00;
			else
				$FreightDiff = $FreightTotal - $TotalFreightAmounts;

			//Figure out if any more TAX to be added from our previous authorization
			if(Math::checkIfFirstFloatParamIsLessOrEqual($TaxTotal, $TotalTaxAmounts))
				$TaxDiff = 0.00;
			else
				$TaxDiff = $TaxTotal - $TotalTaxAmounts;


			//Load billing information from the Database.
			$this->LoadBillingInfoByOrderID($OrderID);
			
			//We don't want commas inserted or Authorize.net will fail.   Ex:  $1,002.23 shoudl be $1002.23
			$GrandTotalDiff = $this->_FormatPrice($GrandTotalDiff);
			$TaxDiff = $this->_FormatPrice($TaxDiff);
			$FreightDiff = $this->_FormatPrice($FreightDiff);
			
			$this->SetTransactionAmounts($GrandTotalDiff, $TaxDiff, $FreightDiff);
			
			if($this->AuthorizeFunds()){
	
				$this->_CollectPaymentResponses();
			
				// A new authorization is not linked to another transaction
				$this->setLoyaltyIdLink(0);
				$this->setOrderIdLink($OrderID);
				$this->CreateNewTransactionRecord(0);
				return true;
			}
			else{
				return false;
			}
		}
	}
	
	
	// This function should be called when the order is completed and we are ready to collect the dough --#
	// There should not be any previous captures for the order at the time this function is called
	// Does not return anything --#
	function CaptureFundsForOrder($OrderID){

		$this->_EnsureChargeIDlinkDoesntExistForOrder($OrderID);
		
		// Find out the current charges of this order
		$GrandTotalofOrder = Order::GetGrandTotalOfOrder($this->_dbCmd, $OrderID);
		$TaxTotal = Order::GetTotalFromOrder($this->_dbCmd, $OrderID, "customertax");
		$FreightTotal = Order::GetCustomerShippingQuote($this->_dbCmd, $OrderID);
		
		// If a soft balance adjustment (with an Auth Only) is put on an order... it will not show up on the Invoice Grand Total.
		// Soft Balance adjustments can only be put on an order before the order is complete.  The pupose is to hide a "line item" on the invoice, but still charge the customer.
		$softBalanceAdjustments = BalanceAdjustment::getCustomerAdjustmentsTotalFromOrder_AuthOnly($this->_dbCmd, $OrderID);
		
		$totalAmountToCaptureFromOrder = $GrandTotalofOrder + $softBalanceAdjustments;
		
		$TotalAuthAmounts = $this->_GetTotalAmountOfAuthorizations($OrderID, "GrandTotal");

		// Check if the grand total is only greater by a couple of cents... in this cause a rounding error could have occured somewhere.
		// I have tried troubleshooting some of these issues but they have been difficult to solve... so just auto-correct it.
		if(Math::checkIfFirstFloatParamIsGreater($totalAmountToCaptureFromOrder, $TotalAuthAmounts)){
			
			if(Math::checkIfFirstFloatParamIsLessOrEqual(($totalAmountToCaptureFromOrder - $TotalAuthAmounts), 0.03))
				$totalAmountToCaptureFromOrder = $TotalAuthAmounts;
		}
		
		if(Math::checkIfFirstFloatParamIsGreater($totalAmountToCaptureFromOrder, $TotalAuthAmounts)){
			WebUtil::WebmasterError("Order: $OrderID is trying to capture more funds than were authorized.  Current Grand Total: \$" . $totalAmountToCaptureFromOrder . " Total Auths: \$" . $TotalAuthAmounts . ". Grand total was automatically adjusted so check and see if that is acceptable.  \n\nThis could happen if the quantity on a mailing order is reduced by a few units going into a higher 'quantity break' and increasing the total of the order, even though there is less overall quantity.");
			$totalAmountToCaptureFromOrder = $TotalAuthAmounts;
		}
		
		
		// There is no point in capturing funds unless the grand total is greater than 0
		if(Math::checkIfFirstFloatParamIsGreater($totalAmountToCaptureFromOrder, 0)){
		
			// Load billing information from the Database.
			$this->LoadBillingInfoByOrderID($OrderID);

			// We are going to try and put all of the freight and Tax onto the first authorization that we find.
			// If the first authorization is really small, we may not be able to fit the whole freight charge on it... so distribuite as much as possible for each auth.
			$FreightRemaining = $FreightTotal;
			$TaxRemaining = $TaxTotal;
			$GrandTotalRemaining = $totalAmountToCaptureFromOrder;

			// Let's find an authorization to go along with this order
			// We want to the transactionID from Authorize net so we can tell them what capture this belongs to
			// I have do do it in a loop below because I can use the operator '>=' on the amount field.  The field is text so it won't work 	
			$this->_dbCmd->Query("SELECT ChargeAmount, ID, TransactionID FROM charges WHERE ChargeType='A' AND OrderID=$OrderID ORDER BY ID ASC");
			
			// We don't want to loop... using the DB driver.  Instead populate an array that we can cycle through.  Otherwise it will get confused when the function calls below use the same driver
			$resultsArr = array();
			while($ChargesRow = $this->_dbCmd->GetRow())
				$resultsArr[] = array("ChargeAmount"=>$ChargesRow["ChargeAmount"], "ChargeID"=>$ChargesRow["ID"], "TransactionID"=>$ChargesRow["TransactionID"]);


			foreach($resultsArr as $ThisAuthResultArr){
			
				$ChargeAmount = $ThisAuthResultArr["ChargeAmount"];
				$ChargeID = $ThisAuthResultArr["ChargeID"];
				$TransactionID = $ThisAuthResultArr["TransactionID"];

				// With any capture... the amount of Tax and Freight can not exceed the total amount of the charge
				$FreightTaxBalance = $ChargeAmount;
				
				// Figure out how much total Taxes to capture on this particular authorization.
				$ThisTaxAmount = 0;
				if(Math::checkIfFirstFloatParamIsGreater($TaxRemaining, 0)){
					if(Math::checkIfFirstFloatParamIsLessOrEqual($TaxRemaining, $FreightTaxBalance))
						$ThisTaxAmount = $TaxRemaining;
					else
						$ThisTaxAmount = $FreightTaxBalance;

					$TaxRemaining -= $ThisTaxAmount;
					$FreightTaxBalance -= $ThisTaxAmount;
				}
				

				// Figure out how much total Freight Charges to capture on this particular authorization.
				$ThisFreightAmount = 0;
				if(Math::checkIfFirstFloatParamIsGreater($FreightRemaining, 0)){
					if(Math::checkIfFirstFloatParamIsLessOrEqual($FreightRemaining, $FreightTaxBalance))
						$ThisFreightAmount = $FreightRemaining;
					else
						$ThisFreightAmount = $FreightTaxBalance;
					
					$FreightRemaining -= $ThisFreightAmount;
					$FreightTaxBalance -= $ThisFreightAmount;
				}
				

				// Figure out how much total money to capture on this particular authorization.
				if(Math::checkIfFirstFloatParamIsLessOrEqual($GrandTotalRemaining, $ChargeAmount))
					$ThisCaptureAmount = $GrandTotalRemaining;
				else
					$ThisCaptureAmount = $ChargeAmount;

				$GrandTotalRemaining -= $ThisCaptureAmount;

	
				$ThisCaptureAmount = $this->_FormatPrice($ThisCaptureAmount);
				$ThisTaxAmount = $this->_FormatPrice($ThisTaxAmount);
				$ThisFreightAmount = $this->_FormatPrice($ThisFreightAmount);
	
				$this->SetTransactionAmounts($ThisCaptureAmount, $ThisTaxAmount, $ThisFreightAmount);

				if(!$this->CaptureFunds($TransactionID))
					WebUtil::WebmasterError("Order: $OrderID is not able to capture funds for transactionID: $TransactionID");
					
					
				$this->_CollectPaymentResponses();

				$this->setLoyaltyIdLink(0);
				$this->setOrderIdLink($OrderID);
				$this->CreateNewTransactionRecord($ChargeID);
				
				// Don't continue looping through authorizations if we have already captured the entire amount
				if(Math::checkIfFloatIsZero($GrandTotalRemaining))
					break;
			}

		}


		// In case this order previously had a charge status of "N" ... for "Needs capture".  We can remove it since the order has already been captured	
		// Even if a capture transaction failed for some reason... We record the error and consider the order captured.  We record and alert the errors separately
		$this->_dbCmd->Query("DELETE FROM charges WHERE ChargeType = 'N' AND OrderID = $OrderID");

	}
	
	
	// If a credit card transaction has an error... then calling this function will try the transaction again --#
	// Returns TRUE if the tranasction has already gone through... maybe the page needed to be refreshed before the user clicked on the command.  
	// Or will return TRUE if the transaction goes through to authorize net correctly.
	// Returns FALSE on another Decline or Error
	function RetryChargingCard($ChargeID){
	
		$this->_ValidateDigit($ChargeID);

		$this->_dbCmd->Query("SELECT * FROM charges WHERE ID=$ChargeID");
		$ChargesRow = $this->_dbCmd->GetRow();

		if($this->_dbCmd->GetNumRows() == 0){
			$ErrorMessage = "Order: " . $ChargesRow["OrderID"] . " is re-trying to capture funds that dont seem to be authorized.";
			WebUtil::WebmasterError($ErrorMessage);
			throw new Exception($ErrorMessage);
		}
		
		
		if($ChargesRow["ChargeIDlink"] == 0){
			$ErrorMessage = "Charge ID: $ChargeID is trying to retry a charge that does not have a charge ID link";
			WebUtil::WebmasterError($ErrorMessage);
			throw new Exception($ErrorMessage);
		}
		
		
		// Maybe somebody else beat them to retrying the charge.  Anyway.. let them know it is OK
		if($ChargesRow["Status"] == "A")
			return true;


		// This ChargeID must be linked to someting... because it is either a refund or a capture.. --#
		// Find out the transaction ID that it is linked to --#
		$ThisTransactionID = $this->_GetTransactionIDfromChargeID($ChargesRow["ChargeIDlink"]);

		
		// Keep the same charges as the last one that we tried
		$this->SetTransactionAmounts($ChargesRow["ChargeAmount"], $ChargesRow["TaxAmount"], $ChargesRow["FreightAmount"]);
		
		// If the error happened while doing a capture... then we want to re-capture
		if($ChargesRow["ChargeType"] == "C"){
			$transResult = $this->CaptureFunds($ThisTransactionID);
			$this->_CollectPaymentResponses();
		}
		else if($ChargesRow["ChargeType"] == "R"){
			$transResult = $this->RefundPayment($ThisTransactionID);
			$this->_CollectPaymentResponses();
		}
		else{
			$ErrorMessage = "A charge retry is being done on a Transcation other than a capture or a refund.";
			WebUtil::WebmasterError($ErrorMessage);
			throw new Exception($ErrorMessage);
		}
		

		$ChargesRow["ChargeRetries"]++;
		
		$UpdateRow = array();
		$UpdateRow["Status"] = $this->_StatusChar;
		$UpdateRow["StatusDate"] = date("YmdHis", time());
		$UpdateRow["TransactionID"] = $this->_GatewayTransactionID;
		$UpdateRow["ApprovalCode"] = $this->_GatewayApprovalCode;
		$UpdateRow["ChargeRetries"] = $ChargesRow["ChargeRetries"];
		$UpdateRow["ResponseReasonCode"] = $this->_GatewayResponseReasonCode;
		$UpdateRow["ResponseReasonText"] = $this->_GatewayResponseReasonText;
		
		$this->_dbCmd->UpdateQuery("charges", $UpdateRow, "ID=$ChargeID");

		return $transResult;
	}
	

	

	// Look through all captured payments within an order --#
	// A refund has to be applied against a previous capture... so we have to look at each capture an see how much balance is left (taking other refunds into account) --#
	// This refund could possibly be spread out over multiple captures  --#
	function RefundMoneyToOrder($refundAmount, $OrderID){
	
		$this->_ValidateDigit($OrderID);
	
		$refundAmount = $this->_FormatPrice($refundAmount);
	
		if(!$this->CheckIfRefundAmountIsAvailable($refundAmount, $OrderID))
			throw new Exception("The money being refunded to the order is greater than is available.");
	
		// Load billing information from the Database... dont
		$this->LoadBillingInfoByOrderID($OrderID);
		
		// Create an array or available captured charge ID's to avoid a sub-query
		$this->_dbCmd->Query("SELECT ID FROM charges WHERE (ChargeType='C' OR ChargeType='B') AND OrderID=$OrderID");
		
		// Looping through the DB driver could cause a conflict with other method calls below
		$chargeIDArr = array();
		while($ChgID = $this->_dbCmd->GetValue())
			$chargeIDArr[] = $ChgID;

		foreach($chargeIDArr as $ThisChargeID){
		
			// Keep refunding money (possibly over multiple captures) until there is nothing left
			if(Math::checkIfFirstFloatParamIsGreater($refundAmount, 0)){
			
				$availableAmountToRefund = $this->_GetAmountFromChargeID($ThisChargeID) - $this->_GetTotalAmountRefundedToChargeID($ThisChargeID);
				
				if(Math::checkIfFirstFloatParamIsGreater($availableAmountToRefund, 0)){
				
					// We would like to refund as much as possible
					if(Math::checkIfFirstFloatParamIsGreaterOrEqual($availableAmountToRefund, $refundAmount))
						$thisRefundAmount = $refundAmount;
					else
						$thisRefundAmount = $availableAmountToRefund;
				
					// No freight associated with refunds...  Tax should technically factored in... but it is a bit messy and the website is not recording tax yet for balance adjustments
					$this->SetTransactionAmounts($thisRefundAmount, 0, 0);
					
					$TransactionID = $this->_GetTransactionIDfromChargeID($ThisChargeID);
					
					// Fire the transaction to authorize net
					$this->RefundPayment($TransactionID);
					$this->_CollectPaymentResponses();

					$this->setLoyaltyIdLink(0);
					$this->setOrderIdLink($OrderID);
					$this->CreateNewTransactionRecord($ThisChargeID);
					
					$refundAmount -= $thisRefundAmount;
				}
			}
		}
	}


	
	// Create a record in the Database for the transaction that just took place --#
	// ChargeIDs link one transaction to another.  Ex.
	// A Refund is linked to a capture, A caputre is linked to a Auth... Auth's aren't linked to anything
	// If there is no ChargeIDLink then just put 0.
	function CreateNewTransactionRecord($ChargeIDlink){
	
		if(empty($this->linked_LoyaltyID) && empty($this->linked_OrderID))
			throw new Exception("There must be a loyalty ID or an Order ID set before you can create a transaction record.");

		if(!empty($this->linked_LoyaltyID) && !empty($this->linked_OrderID))
			throw new Exception("You can't have both a loyalty ID and an Order ID set.");

		// Convert null values into integers
		if(empty($this->linked_OrderID))
			$this->linked_OrderID = 0;
		if(empty($this->linked_LoyaltyID))
			$this->linked_LoyaltyID = 0;
			
		if($this->_ChargeType == "")
			throw new Exception("Charge Type was not set yet");
		
		// Webmaster Errors just send emails in the backround.  It will not abort the script.
		
		if(($this->_BillingType=="N") || ($this->_BillingType=="C") || ($this->_BillingType=="P")) {
			if(!preg_match("/^(\d|\w)+$/", $this->_GatewayTransactionID))
				WebUtil::WebmasterError("A Charge record is being created without a valid Transaction ID.  Order ID:" . $this->linked_OrderID . " Loyalty ID: " . $this->linked_LoyaltyID);
		}	
		else{
			throw new Exception("Error with Billing Type");
		}
		
			
		if(!preg_match("/^\d+$/", $ChargeIDlink))
			WebUtil::WebmasterError("A transaction record is being created without a valid ChargeIDlink.  Order ID:" . $this->linked_OrderID . " Loyalty ID: " . $this->linked_LoyaltyID);

		
		// We should never be recording an AUTH_ONLY or an AUTH_CAPTURE unless it has been accepted by the bank.. 
		// This is because an error screen should be shown to the user in all cases .. that includes admins! --#
		// Refunds and Captures are done silently in the background.. If they fail, we deffinetly want to record it here --#
		if($this->_StatusChar != 'A' && ($this->_ChargeType == "A" || $this->_ChargeType == "B"))
			throw new Exception("Transaction records for auth &amp; auth capture can only be recorded when accepted by bank first.");

		$mysql_timestamp = date("YmdHis", time());

		$InsertRow = array();
		$InsertRow[ "OrderID"] = $this->linked_OrderID;
		$InsertRow[ "LoyaltyChargeID"] = $this->linked_LoyaltyID;
		$InsertRow[ "ChargeAmount"] = $this->_TotalCharge;
		$InsertRow[ "TaxAmount"] = $this->_TaxCharge;
		$InsertRow[ "FreightAmount"] = $this->_FreightCharge;
		$InsertRow[ "ChargeType"] = $this->_ChargeType;
		$InsertRow[ "Status"] = $this->_StatusChar;
		$InsertRow[ "StatusDate"] = $mysql_timestamp;
		$InsertRow[ "ChargeRetries"] = 0;
		$InsertRow[ "ChargeIDlink"] = $ChargeIDlink;
		$InsertRow[ "TransactionID"] = $this->_GatewayTransactionID;
		$InsertRow[ "ApprovalCode"] = $this->_GatewayApprovalCode;
		$InsertRow[ "CommissionFee"] = $this->_GetCommissionFee();
		$InsertRow[ "ResponseReasonCode"] = $this->_GatewayResponseReasonCode;
		$InsertRow[ "ResponseReasonText"] = $this->_GatewayResponseReasonText;

		$this->_dbCmd->InsertQuery("charges",  $InsertRow);
	}
	
	
	
	
	#---------  Get Properties ------#

	// Returns 'A'accepted, 'D'eclined, or 'E'rror
	function GetTransactionStatus(){
		return $this->_StatusChar;
	}
	
	// Should be a full/multi-line sentence description of the errors in HTML. 
	function GetErrorReason(){
		return $this->_ErrorReason;
	}
	
	// If the transaction resulted in an error.
	// We want to know if we know the reason for the error and if it can be fixed by the customer
	// That is an indication if we need to notify a webmaster of an error.
	// Like a "Duplicate Transaction" or an "Expired Credit Card" will result in an error, but we don't need to tell the webmaster
	function CheckIfErrorIsExplainable(){
		
		if($this->_StatusChar != "E")
			throw new Exception("Can not call the function CheckIfErrorIsTemporaryAndExplainable unless the status is currently an error.");
	
		
		if(in_array($this->_GatewayResponseReasonCode, array(6, 7, 8, 9, 11, 37)))
			return true;
		else
			return false;
	}


	
	
	
	#---------  Private Methods below ------#


	// Returns how much money we have Captured on an order. --#
	// Does NOT factor in refunds --#
	function GetTotalAmountCapturedFromOrder($OrderID){
	
		$this->_ValidateDigit($OrderID);
			
		$this->_dbCmd->Query("SELECT SUM(ChargeAmount) FROM charges WHERE (ChargeType='C' OR ChargeType='B') AND OrderID=" . $OrderID);

		if($this->_dbCmd->GetNumRows() == 0)
			return 0;
		else
			return $this->_dbCmd->GetValue();
	}

	// Returns total amount of how much money has been refunded to an order --#
	function GetTotalAmountRefundedToOrder($OrderID){
	
		$this->_ValidateDigit($OrderID);
		
		$this->_dbCmd->Query("SELECT SUM(ChargeAmount) FROM charges WHERE ChargeType='R' AND OrderID=" . $OrderID);

		if($this->_dbCmd->GetNumRows() == 0)
			return 0;
		else
			return $this->_dbCmd->GetValue();
	}
	
	// Pass in a desired amount that you would like to refund to an order --#
	// Returns TRUE if there is enough money already captured to accomodate the refund... FALSE if not --#
	// There may be many refunds and captures associated with an order... this function sums them all up --#
	function CheckIfRefundAmountIsAvailable($refundAmount, $OrderID){

		$OrderID = intval($OrderID);
		
		$refundAmount = WebUtil::FilterData($refundAmount, FILTER_SANITIZE_FLOAT);
		
		$totalBalanceLeft = $this->GetTotalAmountCapturedFromOrder($OrderID) - $this->GetTotalAmountRefundedToOrder($OrderID);
		
		if(Math::checkIfFirstFloatParamIsGreaterOrEqual($totalBalanceLeft, $refundAmount)){
			return true;
		}
		else{
			return false;
		}
	}
	

	// Returns the total amount of authorizations that we have on the order
	function _GetTotalAmountOfAuthorizations($OrderID, $AmountType){
	
		$this->_ValidateDigit($OrderID);

		if($AmountType == "GrandTotal")
			$FirstColumn = "ChargeAmount";
		else if($AmountType == "TaxTotal")
			$FirstColumn = "TaxAmount";
		else if($AmountType == "FreightTotal")
			$FirstColumn = "FreightAmount";
		else
			throw new Exception("Error with function call _GetTotalAmountOfAuthorizations()");

		$AuthTotals = 0;

		//We can't do this with a MAX(ChargeAmount) because the column type is a varchar... using a Float column type is a bit of a nightmare.		
		$this->_dbCmd->Query("SELECT $FirstColumn FROM charges WHERE ChargeType='A' AND OrderID=" . $OrderID);
		while($amount = $this->_dbCmd->GetValue())
			$AuthTotals += $amount;

		return $this->_FormatPrice($AuthTotals);
	}


	// Returns total amount of how much money has been refunded to a chargeID --#
	// Rember that refunds may only be applied to charges that have been captured --#
	function _GetTotalAmountRefundedToChargeID($ChargeID){
	
		$this->_ValidateDigit($ChargeID);
		
		$this->_dbCmd->Query("SELECT SUM(ChargeAmount) FROM charges WHERE ChargeType='R' AND ChargeIDlink=$ChargeID");

		if($this->_dbCmd->GetNumRows() == 0)
			return 0;
		else
			return $this->_dbCmd->GetValue();
	}
	

	function _GetAmountFromChargeID($ChargeID){
	
		$this->_ValidateDigit($ChargeID);
		
		$this->_dbCmd->Query("SELECT ChargeAmount FROM charges WHERE ID=$ChargeID");

		if($this->_dbCmd->GetNumRows() == 0)
			return 0;
		else
			return $this->_dbCmd->GetValue();
	}

	// Will return the confirmation number from Authorize net... based on an internal ID from our charges table --#
	function _GetTransactionIDfromChargeID($ChargeID){
	
		$this->_ValidateDigit($ChargeID);
		
		$this->_dbCmd->Query("SELECT TransactionID FROM charges WHERE ID=$ChargeID");

		if($this->_dbCmd->GetNumRows() == 0)
			return 0;
		else
			return $this->_dbCmd->GetValue();
	}




	
	// Get the any codes, error messages, or Statuses that come from the gateway after a transaction has been submitted.
	function _CollectPaymentResponses(){
	
		$this->_CheckForEmptyBillingType();

		if($this->_BillingType == "N"){
			$this->_ErrorReason = $this->_PaymentGatewayObj->GetErrorReason();
			$this->_StatusChar = $this->_PaymentGatewayObj->GetTransactionStatus();
			$this->_GatewayTransactionID = $this->_PaymentGatewayObj->GetTransactionID();
			$this->_GatewayApprovalCode = $this->_PaymentGatewayObj->GetApprovalCode();
			$this->_GatewayResponseReasonCode = $this->_PaymentGatewayObj->GetResponseReasonCode();
			$this->_GatewayResponseReasonText = $this->_PaymentGatewayObj->GetResponseReasonText();
		}
		else if($this->_BillingType == "C"){
			$this->_ErrorReason = $this->_PaymentInvoiceObj->GetErrorReason();
			$this->_StatusChar = $this->_PaymentInvoiceObj->GetTransactionStatus();
			$this->_GatewayTransactionID = $this->_PaymentInvoiceObj->GetTransactionID();
			$this->_GatewayApprovalCode = $this->_PaymentInvoiceObj->GetApprovalCode();
			$this->_GatewayResponseReasonCode = $this->_PaymentInvoiceObj->GetResponseReasonCode();
			$this->_GatewayResponseReasonText = $this->_PaymentInvoiceObj->GetResponseReasonText();
		}
		else if($this->_BillingType == "P"){
			$this->_ErrorReason = $this->_PaypalGatewayObj->GetErrorReason();
			$this->_StatusChar = $this->_PaypalGatewayObj->GetTransactionStatus();
			$this->_GatewayTransactionID = $this->_PaypalGatewayObj->GetTransactionID();
			$this->_GatewayApprovalCode = $this->_PaypalGatewayObj->GetApprovalCode();
			$this->_GatewayResponseReasonCode = $this->_PaypalGatewayObj->GetResponseReasonCode();
			$this->_GatewayResponseReasonText = $this->_PaypalGatewayObj->GetResponseReasonText();
		}
		else{
			throw new Exception("Illegal billing type in method call _CollectPaymentResponses");
		}
	}
	

	// We should always clear out the results of the last transaction before callign a new one to avoid any data spilling over
	function _ClearResponses(){
	
		$this->_ErrorReason = "An unknown error has occured.";  // Start off as an error, let it proof otherwise
		$this->_StatusChar = "E";  
		$this->_GatewayTransactionID = "0";
		$this->_GatewayApprovalCode = "";
		$this->_GatewayResponseReasonCode = "";
		$this->_GatewayResponseReasonText = "";
	}

	function _EnsureTransactionInfoHasBeenSet(){
	
		if(!$this->_TranactionDataSetFlag)
			throw new Exception("Transaction Information has not been set yet.");
		
		if($this->_TotalCharge == "")
			throw new Exception("Amounts have not been set yet for the Transaction data.");
			
		if(!$this->_UserLoadedFlag)
			throw new Exception("A customer has not been loaded yet.");
	
	}


	function _GetCommissionFee(){
	
		$this->_CheckForEmptyBillingType();

		if($this->_BillingType == "N"){
			return $this->_PaymentGatewayObj->GetMerchantCommissionFee();
		}
		else if($this->_BillingType == "P"){
			return $this->_PaypalGatewayObj->GetPaypalCommissionFee();
		}
		else if($this->_BillingType == "C"){
			return "0.00";
		}
		else {
			throw new Exception("Error in method Payemnts->_GetCommissionFee. Billing type incorrect.");
		}

		
	}
	
	// Calling this method ensures that the billing Type has been loaded or the script will die.
	function _CheckForEmptyBillingType(){
	
		if(empty($this->_BillingType))
			throw new Exception("The BillingType from the User Data must be loaded before calling this method.");
	
	}

	// Returns a decimal number without any commas in the thousands place
	// Also Ensures the number is in a correct format
	function _FormatPrice($ThePrice){
	
		$FormatedPrice = number_format($ThePrice, 2, '.', '');
		
		if(!preg_match("/^((\d+\.\d+)|0)$/", $FormatedPrice)){
			$userDebug = "None";
			if($this->_UserLoadedFlag)
				$userDebug = $this->_UserControlObj->getUserID();
			WebUtil::WebmasterError("A problem with the price format occured in Payments.php.  The amount is $ThePrice and User ID is $userDebug");
			throw new Exception("A price format is not correct.  The amount is: " . $ThePrice);
		}
			
		return $FormatedPrice;
	}
	
	// There should never be the posibility of a charge ID link existing on an order if the funds have not been captured yet 
	// Call this method to be extra cautious in case of an unknown bug or future development errors.	
	function _EnsureChargeIDlinkDoesntExistForOrder($OrderID){
	
		$this->_ValidateDigit($OrderID);
	
		$this->_dbCmd->Query("SELECT COUNT(*) FROM charges WHERE ChargeIDlink != 0 AND ChargeType='C' AND OrderID = $OrderID");
		if($this->_dbCmd->GetValue() > 0){
			$ErrMsg = "You can not authorize a new amount for this order because a ChargeIDLink already exists against a capture for Order# $OrderID";
			WebUtil::WebmasterError($ErrMsg);
		}
	
	}

	// Should be called to validate OrderID's or something for communicating with the database.
	function _ValidateDigit($Num){
		if(!preg_match("/^\d+$/", $Num))
			throw new Exception("Value is not a digit. :" . $Num);
	}
	
	
	
	
	// Make sure that a Customer doesn't charge more than a fixed amount on a single order... which is set in their account.
	function _checkForSingleOrderLimitError(){
		
		$singleOrderLimit = $this->_UserControlObj->getSingleOrderLimit();
		
		// Corporate users don't have a single order limit.  They have a total credit limit instead... that is handled by our gateway object in this class.
		if(empty($singleOrderLimit))
			return false;
		
		if(Math::checkIfFirstFloatParamIsGreater($this->_TotalCharge, $singleOrderLimit)){
			$this->_StatusChar = "E";
			$this->_ErrorReason = "Changes to this order have required an additional authorization of " . $this->_TotalCharge . ". The single order limit is currently set at " . $this->_UserControlObj->getSingleOrderLimit() . ".  Contact customer service to increase this limit or modify your selection.";
			return true;
		}
		else{
			return false;
		}
	}

	
}






?>