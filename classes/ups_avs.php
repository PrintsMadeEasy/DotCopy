<?

// container to hold the parses results from UPS.
class UPS_ValidationResult {

	public $rank;
	public $quality;
	public $City;
	public $State;
	public $postalHigh;
	public $postalLow;


	function UPS_ValidationResult(){

		$this->rank = "";
		$this->quality = "";
		$this->City = "";
		$this->State = "";
		$this->postalHigh = "";
		$this->postalLow = "";
	}
}


class UPS_AV_response {

	private $parser;  //So this object has reference to the XPAT xml proccesor
	private $curTag;
	private $attributes;

	#-- Member Variables --#
	private $_XMLfile;
	private $_xpciVersion;
	private $_ResponseStatusDescription;
	private $_ResponseStatusCode;
	private $_ErrorSeverity;
	private $_ErrorCode;
	private $_ErrorDescription;
	private $_emptySuggestions;
	private $_forceGoodResponse;

	private $_CommunicationErrorFlag;

	//this will be a hash
	private $_ValidationResults = array();



	##--------------   Constructor  ------------------##
	// Set the $forceGoodResponse to TRUE you already know the result is good. (such as by caching the result)
	// If the $forceGoodResponse is TRUE you don't need an XML file, just send NULL and it will pretend as if the API came back good.
	function UPS_AV_response($XMLfile, $forceGoodResponse = false){

		$this->_XMLfile = $XMLfile;

		$this->_xpciVersion = "";
		$this->_ResponseStatusDescription = "";
		$this->_ResponseStatusCode = "";
		$this->_ErrorSeverity = "";
		$this->_ErrorCode = "";
		$this->_ErrorDescription = "";
		$this->_emptySuggestions = false;
		$this->_CommunicationErrorFlag = false;
		$this->_forceGoodResponse = $forceGoodResponse;
		

		if(!$forceGoodResponse){
			
			//An extra level of protection... in case the server is having an internal error and we are not getting back a well formed XML document
			if(!preg_match("/AddressValidationResponse/i", $this->_XMLfile)){
				$this->_CommunicationErrorFlag = true;
				$this->_ErrorDescription = "An unknown error has occured.  The AddressValidationResponse tag was not found.";
			}
			else{
				
				$this->parseXMLdoc();
				
				//See if UPS returned with no suggestions... this should be rare, but they say that it is possible.
				if(sizeof($this->_ValidationResults) == 0){
					$this->_emptySuggestions = true;
				}
				
				// UPS will return with a "Hard Error" if someone types in invalid data... which is badly programmed
				// Also many of the Error codes overlap
				// If we get an error, look to see if the error description says "contains invalid data" ... 
				// if so show erase any errors and just let an Empty list of suggestions be displayed to the user.
				if($this->GetErrorCode() != ""){
					if(preg_match("/contains invalid data/i", $this->_ErrorDescription)){
					
						$this->_ErrorSeverity = "";
						$this->_ErrorCode = "";
						$this->_ErrorDescription = "";
					}
					else if(preg_match("/No Address Candidate Found/i", $this->_ErrorDescription)){
					
						$this->_ErrorSeverity = "";
						$this->_ErrorCode = "";
						$this->_ErrorDescription = "";
					} 
				}
			}
		}

	}


	##-----------------   Methods  -------------------##
	function GetXPCIversion() {
		return $this->_xpciVersion;
	}
	function GetStatusDescription() {
		return $this->_ResponseStatusDescription;
	}
	function GetStatusCode() {
		return $this->_ResponseStatusCode;
	}
	function CheckIfAddressIsOK(){
		
		if($this->_forceGoodResponse)
			return true;
		
		if(!empty($this->_ValidationResults)){
			if($this->_ValidationResults[0]->quality == "1.0")
				return true;
		}
		
		return false;
	}
	function GetValidationResults() {
		
		if($this->_forceGoodResponse)
			throw new Exception("Error in method GetValidationResults. Response was overridden.");

		//We can't return a blank array
		if(sizeof($this->_ValidationResults) == 0){
			return array(new UPS_ValidationResult());
		}

		return $this->_ValidationResults;
	}
	function GetErrorCode() {
		return $this->_ErrorCode;
	}
	function GetErrorDescription() {
		return $this->_ErrorDescription;
	}
	function GetErrorSeverity() {
		return $this->_ErrorSeverity;
	}
	function EmptySuggestions() {
		return $this->_emptySuggestions;
	}
	function CheckIfCommunicationError() {
		return $this->_CommunicationErrorFlag;
	}



	####### ----- Private Methods Below ---------- ########


	#--------------------------  Methods below to parse the xml document ----------------------------------------#
	function parseXMLdoc(){

		##--- Define Event driven functions for XPAT processor. ----##
		$this->parser = xml_parser_create();
		xml_set_object($this->parser, $this);
		xml_set_element_handler($this->parser, "startElement", "endElement");
		xml_set_character_data_handler($this->parser, "characterData");

		##--- Parse the XMl document.  This will call our functions that we just set handlers for above during the processing. ----#
		if (!xml_parse($this->parser, $this->_XMLfile)) {
			die(sprintf("XML error: %s at line %d", xml_error_string(xml_get_error_code($this->parser)), xml_get_current_line_number($this->parser)));
		}

		xml_parser_free($this->parser);
	}

	function startElement($parser, $name, $attrs) {

		$this->attributes = $attrs;

		$this->curTag .= "^$name";
		
		//Create a new element to the array
		if($this->curTag == "^ADDRESSVALIDATIONRESPONSE^ADDRESSVALIDATIONRESULT")
			$this->_ValidationResults[sizeof($this->_ValidationResults)] = new UPS_ValidationResult();
	}

	function endElement($parser, $name) {

		$caret_pos = strrpos($this->curTag,'^');

		$this->curTag = substr($this->curTag, 0, $caret_pos);

	}



	function characterData($parser, $data) {

		WebUtil::htmlOutput($data);


		##-- Define all possible tag structures with a carrot separating each tag name. __##

		$CommunicationError_KEY = "^ADDRESSVALIDATIONRESPONSE^COMMUNICATIONERROR";
		$xpciVer_Key = "^ADDRESSVALIDATIONRESPONSE^RESPONSE^TRANSACTIONREFERENCE^XPCIVERSION";
		$RspCode_Key = "^ADDRESSVALIDATIONRESPONSE^RESPONSE^RESPONSESTATUSCODE";
		$RspDesc_Key = "^ADDRESSVALIDATIONRESPONSE^RESPONSE^RESPONSESTATUSDESCRIPTION";
		$AVR_rank_Key = "^ADDRESSVALIDATIONRESPONSE^ADDRESSVALIDATIONRESULT^RANK";
		$AVR_quality_Key = "^ADDRESSVALIDATIONRESPONSE^ADDRESSVALIDATIONRESULT^QUALITY";
		$AVR_addressCity_Key = "^ADDRESSVALIDATIONRESPONSE^ADDRESSVALIDATIONRESULT^ADDRESS^CITY";
		$AVR_addressState_Key = "^ADDRESSVALIDATIONRESPONSE^ADDRESSVALIDATIONRESULT^ADDRESS^STATEPROVINCECODE";
		$AVR_PostalLow_Key = "^ADDRESSVALIDATIONRESPONSE^ADDRESSVALIDATIONRESULT^POSTALCODELOWEND";
		$AVR_PostalHigh_Key = "^ADDRESSVALIDATIONRESPONSE^ADDRESSVALIDATIONRESULT^POSTALCODEHIGHEND";


		$ERR_Severity_Key = "^ADDRESSVALIDATIONRESPONSE^RESPONSE^ERROR^ERRORSEVERITY";
		$ERR_code_Key = "^ADDRESSVALIDATIONRESPONSE^RESPONSE^ERROR^ERRORCODE";
		$ERR_desc_Key = "^ADDRESSVALIDATIONRESPONSE^RESPONSE^ERROR^ERRORDESCRIPTION";



		// If we come across an CommunicationError key... then it was put in by our own code somewhere in the form of XML.  UPS does not provide this key in any of their XML docs.
		// Just set a flag in the communication object letting us know that there was an error... and then piggy back on the regular error description member.
		if ($this->curTag == $CommunicationError_KEY){
			$this->_CommunicationErrorFlag = true;
			$this->_ErrorDescription = $data;
		}
		else if ($this->curTag == $xpciVer_Key) 
			$this->_xpciVersion = $data;
		else if ($this->curTag == $RspCode_Key) 
			$this->_ResponseStatusCode = $data;
		else if ($this->curTag == $RspDesc_Key) 
			$this->_ResponseStatusDescription = $data;
		else if ($this->curTag == $AVR_rank_Key)
			$this->_ValidationResults[sizeof($this->_ValidationResults) - 1]->rank = $data;
		else if ($this->curTag == $AVR_quality_Key) 
			$this->_ValidationResults[sizeof($this->_ValidationResults) - 1]->quality = $data;
		else if ($this->curTag == $AVR_addressCity_Key)
			$this->_ValidationResults[sizeof($this->_ValidationResults) - 1]->City = $data;
		else if ($this->curTag == $AVR_addressState_Key)
			$this->_ValidationResults[sizeof($this->_ValidationResults) - 1]->State = $data;
		else if ($this->curTag == $AVR_PostalLow_Key)
			$this->_ValidationResults[sizeof($this->_ValidationResults) - 1]->postalLow = $data;
		else if ($this->curTag == $AVR_PostalHigh_Key)
			$this->_ValidationResults[sizeof($this->_ValidationResults) - 1]->postalHigh = $data;
		else if ($this->curTag == $ERR_Severity_Key)
			$this->_ErrorSeverity = $data;
		else if ($this->curTag == $ERR_code_Key)
			$this->_ErrorCode = $data;
		else if ($this->curTag == $ERR_desc_Key)
			$this->_ErrorDescription = $data;

	}
}

class UPS_AV_request {

	#-- Member Variables --#
	private $_city;
	private $_state;
	private $_postalcode;
	private $_CustomerContext;
	private $_XpciVersion;
	private $_AccessLicenseNumber;
	private $_UserId;
	private $_Password;

	private $_xmlfile1;



	##--------------   Constructor  ------------------##
	function UPS_AV_request($city, $state, $postalcode){
	
			// UPS will fail badly if we leave out the State abbrieviation or put in junk
			// Just default to random meaningless state abbrieviation if a well formed one is not provided
			if(!preg_match("/^\w{2}$/", $state))
				$state = "AA";
				
			$this->_city = $city;
			$this->_state = $state;
			$this->_postalcode = $postalcode;
			$this->_CustomerContext = "Customer Shipping Address";

			//Hard code for now
			$this->_XpciVersion = "1.0001";
			$this->_AccessLicenseNumber = "7B9DE9F4A38077D8";
			$this->_UserId = "bpiere";
			$this->_Password = "SDFSF34H";
	}

	function BuildAccessRequestXMLfile(){

		$this->_xmlfile = 	'<?xml version="1.0"?>
					<AccessRequest xml:lang="en-US">
					<AccessLicenseNumber>' . $this->_AccessLicenseNumber . '</AccessLicenseNumber>
					<UserId>' . $this->_UserId . '</UserId>
					<Password>' . $this->_Password . '</Password>
					</AccessRequest>
					';

		return $this->_xmlfile;

	}
	function BuildAddressValidationXMLfile(){

		$this->_xmlfile = 	'<?xml version="1.0"?>
							<AddressValidationRequest xml:lang="en-US">
  		 					<Request>
      						<TransactionReference>
         					<CustomerContext>' . WebUtil::htmlOutput($this->_CustomerContext) . '</CustomerContext>
         					<XpciVersion>' . $this->_XpciVersion . '</XpciVersion>
      						</TransactionReference>
      						<RequestAction>AV</RequestAction>
  				 			</Request>
   							<Address>
   							';
   	if(!empty($this->_city)){
      		$this->_xmlfile .= 	'<City>' . WebUtil::htmlOutput($this->_city) . '</City>';
      	}
    	if(!empty($this->_state)){
      		$this->_xmlfile .= 	'<StateProvinceCode>' . $this->_state . '</StateProvinceCode>';
      	}
   		if(!empty($this->_postalcode)){
      		$this->_xmlfile .= 	'<PostalCode>' . $this->_postalcode . '</PostalCode>';
      	}

      	$this->_xmlfile .= '</Address>
							</AddressValidationRequest>
							';

		return $this->_xmlfile;
	}
}


class UPS_AV {

	//This function will connect with the UPS server and return a UPS_AV_response object.
	//The XML file contains information on whether the address is valid
	static function ValidateShippingAddress($city, $state, $zip){

		$upsReqObj = new UPS_AV_request($city, $state, $zip);

		// UPS has Test and Live servers
		//if(Constants::GetDevelopmentServer())
			//$PaymentGatewayHost = "https://wwwcie.ups.com/ups.app/xml/AV";
		//else
			$PaymentGatewayHost = "https://www.ups.com/ups.app/xml/AV";


		$POST_string = $upsReqObj->BuildAccessRequestXMLfile() .  $upsReqObj->BuildAddressValidationXMLfile();
		$POST_string = addslashes($POST_string);
		$POST_string = preg_replace("/(\r|\n)/", "", $POST_string);

		//if(!Constants::GetDevelopmentServer()){

			$return_value = "";
			
			// -m 30 lets us have up to 30 seconds to get a response back from the server.
			// -k means the server doesn't need a valid certificate to connect in SSL mode.
			// -d means that we are posting data.
			exec(Constants::GetCurlCommand() . " -m 30 -k -d \"$POST_string\" $PaymentGatewayHost", $return_value);
			
			if(!is_array($return_value) || !isset($return_value[0])){
				$DisplayMsg = "Error Description:<br>Failed to make an SSL connection";

				return new UPS_TimeInTransit_response('<?xml version="1.0"?><AddressValidationResponse><CommunicationError>'. $DisplayMsg .'</CommunicationError></AddressValidationResponse>');
			}

			$retXML = implode("", $return_value);
	//	}
/*
		else{

			#-- This is just just for debuging on the development server --#

			$retXML = '<?xml version="1.0"?>
			<AddressValidationResponse>
			 <Response>
			 <TransactionReference>
			 <XpciVersion>1.0001</XpciVersion>
			 </TransactionReference>
			 <ResponseStatusCode>1</ResponseStatusCode>
			 <ResponseStatusDescription>Success</ResponseStatusDescription>
			 </Response>
			 <AddressValidationResult>
			 <Rank>1</Rank>
			 <Quality>1.0</Quality>
			 <Address>
				<City>TIMONIUM</City>
				<StateProvinceCode>MD</StateProvinceCode>
			 </Address>
			 <PostalCodeLowEnd>21093</PostalCodeLowEnd>
			 <PostalCodeHighEnd>21094</PostalCodeHighEnd>
			 </AddressValidationResult>
			</AddressValidationResponse>
			';
		}

*/

		return new UPS_AV_response($retXML);
	}
	
	function GetUPSerrorMessageForCustomer(){
		return "An error occured when trying to connect to the server for the United Parcel Service.  We must validate shipping addresses so that we can maintain an high level of efficiency.<br><br>You should save your project(s) if you have not done so already.  You can try again in a little while.<br><br>A message has automaticaly been sent to the webmaster reporting this problem.<br><br><a href='javascript:history.back()'>&lt; Go back<br><br><br><br><br></a>";
	}
}




?>