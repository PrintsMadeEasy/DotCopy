<?


class PaypalAPI {

	private $_parser;  //So this object has reference to the XPAT xml proccesor
	private $_curTag;
	private $_nodeAttributes;
	private $_XMLfile;


	private $_PaypalTransactionType;
	private $_PaypalAPIurl;
	private $_ClientCertificate;
	
	
	// Common PayPal API fields
	// Paypal actually respons with 4 acknowlegements... Success, Failure, SucessWithWarning, FailureWithWarning
	// We are going to translate those 1 bool response _transnSuccess ... if we get any warnings then we will just fail critically and exit... once it is setup correctly it should never get warnings.
	private $_userName;
	private $_password;
	private $_signature;
	private $_errorMsg;
	private $_transnSuccess; // bool
	private $_transnTimestamp;
	private $_correlationID;
	
	
	// For Transaction Details
	// There are a ton of fields that we are ingnoring, such as... 
	// ... we don't need to gather Payer Info or Receiver Info because we already know that information from our own DB
	private $_transacrionID;
	private $_paymentStatus;
	private $_pendingReason;

	
	
	// vars for the Mass Payment response
	private $_emailSubject;
	private $_receiverEmail;
	private $_receiverAmount;
	private $_uniqueID;
	
	private $_domainID;




	##--------------   Constructor  ------------------##
	function PaypalAPI($domainID){
		
		if(!Domain::checkIfDomainIDexists($domainID))
			throw new Exception("Error with Domain in PaypalAPI");
						
		$this->_domainID = $domainID;
		
		if(Constants::GetDevelopmentServer()){
				
			$this->_userName = "sell_1258730873_biz_api1.asynx.ch";
			$this->_password = "1258730878";
			$this->_signature = "A0p8X4HLanPSK6Sz3sbj5H9hd4Y-AoynUdiX1s89ys323iPHqxJ1spDd";
			$this->_PaypalAPIurl = "https://api.sandbox.paypal.com/2.0/";
		}
		else{

			$domainGatewaysObj = new DomainGateways($domainID);
			
			$this->_userName = $domainGatewaysObj->paypalAPI_LoginID;
			$this->_password = $domainGatewaysObj->paypalAPI_LoginPassword;
			$this->_signature = $domainGatewaysObj->paypalAPI_LoginSignature;
			$this->_PaypalAPIurl = "https://api-3t.paypal.com/2.0/";
		}
		
		$this->_PaypalTransactionType = null;
		$this->_paymentStatus = "";
		$this->_pendingReason = "";
		
	}




	##-----------------  Action Methods  -------------------##
	// Pass in an object from the class 'PaypalMassPayList'
	// Returns True or False wheter the Mass Pay has gone through successfully
	// If false.... you can get the error message out of the method call to GetErrorMessage
	function MassPay(&$MassPayListObj) {
		$this->_PaypalTransactionType = "MASSPAY";
		
		$MassPayArray = $MassPayListObj->GetMassPayArray();
		
		$PostString = $this->_GetSoapRequestHeader();
		
		// As of 5/23/05 there is a bug with the PayPal API.  There is no way to send the UniqueID to Paypal and get it back with an Instant Payment Notification or the GetTransactionDetailsAPI
		// We are just going to find the payment by matching the Email Address to the Amount Instead.  There is a possibility 
		
		$PostString .= '<SOAP-ENV:Body>
		      <MassPayReq xmlns="urn:ebay:api:PayPalAPI">
			 <MassPayRequest xsi:type="ns:MassPayRequestType">
			    <Version xmlns="urn:ebay:apis:eBLBaseComponents" xsi:type="xsd:string">1.0</Version>
			    <EmailSubject xsi:type="xsd:string">Your payment is ready!  ('.Domain::getDomainKeyFromID($this->_domainID).' Sales Commission System)</EmailSubject>';
			    
			foreach($MassPayArray as $thisMassPayItem){
				$PostString .= '
				    <MassPayItem xsi:type="ns:MassPayRequestItemType">
					<ReceiverEmail xsi:type="ns:EmailAddressType">'.$thisMassPayItem["ReceiverEmail"].'</ReceiverEmail>
					<Amount currencyID="USD" xsl:type="cc:BasicAmountType">'.$thisMassPayItem["Amount"].'</Amount>
					<Note xsl:type="xsd:string">You have earned '. number_format($thisMassPayItem["Amount"], 2) .' this period for your '.Domain::getDomainKeyFromID($this->_domainID).' sales commissions.</Note>
				    </MassPayItem>
				 ';
			   }
		
		$PostString .= '
			 </MassPayRequest>
		      </MassPayReq>
		   </SOAP-ENV:Body>';


		$PostString .= $this->_GetSoapRequestFooter();

		$this->_FireTransaction($PostString);
		
		return $this->_transnSuccess;
		
	}
	
	// For Tests, will be deleted later
	function MassPayTest() {
		$this->_PaypalTransactionType = "MASSPAY";
		
		$PostString = $this->_GetSoapRequestHeader();
				
		$PostString .= '<SOAP-ENV:Body>
		      <MassPayReq xmlns="urn:ebay:api:PayPalAPI">
			 <MassPayRequest xsi:type="ns:MassPayRequestType">
			    <Version xmlns="urn:ebay:apis:eBLBaseComponents" xsi:type="xsd:string">1.0</Version>
			    <EmailSubject xsi:type="xsd:string">Your payment is ready!  TEST Sales Commission System</EmailSubject>';
			    
				$PostString .= '
				    <MassPayItem xsi:type="ns:MassPayRequestItemType">
					<ReceiverEmail xsi:type="ns:EmailAddressType">Brian@PrintsMadeEasy.com</ReceiverEmail>
					<Amount currencyID="USD" xsl:type="cc:BasicAmountType">2.00</Amount>
					<Note xsl:type="xsd:string">You have earned 2.00 this period for your PMTest sales commissions.</Note>
				    </MassPayItem>
				 ';
		
		$PostString .= '
			 </MassPayRequest>
		      </MassPayReq>
		   </SOAP-ENV:Body>';


		$PostString .= $this->_GetSoapRequestFooter();

		$this->_FireTransaction($PostString);
		
		return $this->_transnSuccess;
		
	}
	
	
	function TransactionDetails($TransactionDID) {
		$this->_PaypalTransactionType = "DETAILS";
		
		$PostString = $this->_GetSoapRequestHeader();
		
		$PostString .= '<SOAP-ENV:Body>
			<GetTransactionDetailsReq xmlns="urn:ebay:api:PayPalAPI">
			<GetTransactionDetailsRequest
			xsi:type="ns:GetTransactionDetailsRequestType">
			<Version xmlns="urn:ebay:apis:eBLBaseComponents" xsi:type="xsd:string">1.0</Version>
			<TransactionID xsi:type="ebl:TransactionId">'.$TransactionDID.'</TransactionID>
			</GetTransactionDetailsRequest>
			</GetTransactionDetailsReq>
			</SOAP-ENV:Body>';

		$PostString .= $this->_GetSoapRequestFooter();

		$this->_FireTransaction($PostString);
		
		return $this->_transnSuccess;
		
	}
	

	function _FireTransaction($PostString){

		// Get rid of any line breaks and add slashes to quotes
		$PostString = preg_replace("/(\r|\n)/", "", $PostString);
		$PostString = preg_replace("/\s{2,}/", " ", $PostString); // get rid of extra white spaces
		$PostString = addslashes($PostString);

		$CurlCom = Constants::GetCurlCommand() . " -k -d \"$PostString\" $this->_PaypalAPIurl";
		
		$return_value = array();
		exec($CurlCom, $return_value);
		
		// $return_value[0] should contain the response from the Curl request
		if(!isset($return_value[0])){
			$this->_errorMsg = "An error occured making a connection with Curl for Mass Pay.  Possible Timeout?";
			$this->_transnSuccess = false;
			return;
		}

		$this->_errorMsg = "An unknown error occured to the PayPal API.";
		$this->_transnSuccess = false;

		// The paypal server should respond with an XML doc
		$this->_XMLfile = $return_value[0];
		$this->_ParseXMLdoc();
		
	}


	
	
	##-------- Get Methods --------##
	

	function GetErrorMessage(){
	
		if(empty($this->_PaypalTransactionType))
			throw new Exception("You must call an action method before calling GetErrorMessage");
			
		return $this->_errorMsg;
	}
	
	function GetTransactionTimeStamp(){
	
		if(empty($this->_PaypalTransactionType))
			throw new Exception("You must call an action method before calling GetTransactionTimeStamp");
			
		return $this->_transnTimestamp;
	}
	
	function GetTranDetailsPaymentStatus(){
		if($this->_PaypalTransactionType != "DETAILS")
			throw new Exception("The method may only be called after calling the TransactionDetails Method");
			
		return $this->_paymentStatus;
	}
	function GetTranDetailsPendingReason(){
		if($this->_PaypalTransactionType != "DETAILS")
			throw new Exception("The method may only be called after calling the TransactionDetails Method");
			
		return $this->_pendingReason;
	}



	####### ----- Private Methods Below ---------- ########
	
	

	
	function _GetSoapRequestHeader(){
		return '<?xml version="1.0" encoding="UTF-8"?>
		<SOAP-ENV:Envelope
		xmlns:xsi="http://www.w3.org/1999/XMLSchema-instance"
		xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/"
		xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"
		xmlns:xsd="http://www.w3.org/1999/XMLSchema"
		SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">
		<SOAP-ENV:Header>
		<RequesterCredentials
		xmlns="urn:ebay:api:PayPalAPI" SOAP-ENV:mustUnderstand="1">
		<Credentials xmlns="urn:ebay:apis:eBLBaseComponents">
		<Username>'. WebUtil::htmlOutput($this->_userName).'</Username>
		<Password>'. WebUtil::htmlOutput($this->_password) .'</Password>
		<Signature>'. WebUtil::htmlOutput($this->_signature) .'</Signature>
		</Credentials>
		</RequesterCredentials>
		</SOAP-ENV:Header>
		';
	}
	
	function _GetSoapRequestFooter(){
	
		return '</SOAP-ENV:Envelope>';
		
	}
	


	#--------------------------  Methods below to parse the xml document ----------------------------------------#
	function _ParseXMLdoc(){

		##--- Define Event driven functions for XPAT processor. ----##
		$this->_parser = xml_parser_create();
		xml_set_object($this->_parser, $this);
		xml_set_element_handler($this->_parser , "startElement", "endElement");
		xml_set_character_data_handler($this->_parser , "characterData");

		##--- Parse the XMl document.  This will call our functions that we just set handlers for above during the processing. ----#
		if (!xml_parse($this->_parser , $this->_XMLfile))
			die(sprintf("XML error: %s at line %d", xml_error_string(xml_get_error_code($this->_parser )), xml_get_current_line_number($this->_parser )));

		xml_parser_free($this->_parser );
	}

	function startElement($_parser, $name, $attrs) {

		$this->_nodeAttributes = $attrs;

		$this->_curTag .= "^$name";
	}

	function endElement($_parser, $name) {

		$caret_pos = strrpos($this->_curTag,'^');

		$this->_curTag = substr($this->_curTag, 0, $caret_pos);
	}


	// Create all of possible TAG hierachies that can possibly be returned in the XML doc and populate appropriate member variables within this object.
	function characterData($_parser, $data) {

		WebUtil::htmlOutput($data);


		##-- Define all possible tag structures with a carrot separating each tag name. __##

		$MassPayAck_Key = "^SOAP-ENV:ENVELOPE^SOAP-ENV:BODY^MASSPAYRESPONSE^ACK";
		$MassPayTimestamp_Key = "^SOAP-ENV:ENVELOPE^SOAP-ENV:BODY^MASSPAYRESPONSE^TIMESTAMP";
		$MassPayError_Key = "^SOAP-ENV:ENVELOPE^SOAP-ENV:BODY^MASSPAYRESPONSE^ERRORS^LONGMESSAGE";



		$TransDetailsAck_Key = "^SOAP-ENV:ENVELOPE^SOAP-ENV:BODY^GETTRANSACTIONDETAILSRESPONSE^ACK";
		$TransDetailsTimestamp_Key = "^SOAP-ENV:ENVELOPE^SOAP-ENV:BODY^GETTRANSACTIONDETAILSRESPONSE^TIMESTAMP";
		$TransDetailsError_Key = "^SOAP-ENV:ENVELOPE^SOAP-ENV:BODY^GETTRANSACTIONDETAILSRESPONSE^ERRORS^LONGMESSAGE";		
		$TransDetailsPaymentStatus_Key = "^SOAP-ENV:ENVELOPE^SOAP-ENV:BODY^GETTRANSACTIONDETAILSRESPONSE^PAYMENTTRANSACTIONDETAILS^PAYMENTINFO^PAYMENTSTATUS";
		$TransDetailsPendingReason_Key = "^SOAP-ENV:ENVELOPE^SOAP-ENV:BODY^GETTRANSACTIONDETAILSRESPONSE^PAYMENTTRANSACTIONDETAILS^PAYMENTINFO^PENDINGREASON";
		

		if ($this->_curTag == $MassPayAck_Key || $this->_curTag == $TransDetailsAck_Key) {

			if(strtoupper($data) == "SUCCESS"){
				$this->_transnSuccess = true;
				$this->_errorMsg = "no errors";
			}
			else
				$this->_transnSuccess = false;
		}
		else if ($this->_curTag == $MassPayTimestamp_Key || $this->_curTag == $TransDetailsTimestamp_Key) {
			$this->_transnTimestamp = $this->_GetUnixTimeStampFromPaypalTimeStamp($data);
		}
		else if ($this->_curTag == $MassPayError_Key || $this->_curTag == $TransDetailsError_Key) {
			$this->_errorMsg = $data;
		}
		else if ($this->_curTag == $TransDetailsPaymentStatus_Key) {
			$this->_paymentStatus = $data;
		}
		else if ($this->_curTag == $TransDetailsPendingReason_Key) {
			$this->_pendingReason = $data;
		}


	}
	
	// Format From paypal 2005-05-25T22:07:31Z
	// Timestamp is in UTC/GMT time. 
	function _GetUnixTimeStampFromPaypalTimeStamp($PayPalTimeStamp){

		$matches = array();
		if(!preg_match_all("/^(\d+)-(\d+)-(\d+)T(\d+):(\d+):(\d+)/", $PayPalTimeStamp, $matches))
			return 0;

		$year = $matches[1][0];
		$month = $matches[2][0];
		$day = $matches[3][0];
		$hour = $matches[4][0];
		$mintue = $matches[5][0];
		$second = $matches[6][0];
		
		return gmmktime($hour, $mintue, $second, $month, $day, $year);
	}
}




?>
