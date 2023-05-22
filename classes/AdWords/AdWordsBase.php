<?php

class AdWordsBase {

	private $email			 = "";
	private $password		  = "";
	private $client_email	  = "";
	private $useragent		 = "";
	private $developer_token   = "";
	private $application_token = "";
	private $baseURL		   = "";
	private $returnedXML;
	private $faultString = "";
	private $faultCode = "";
	private $transnSuccess = false;
	private $parsingXMLforErrorsFlag = false;
	
	private $errorDetail;
	private $errorTrigger;
	private $errorIndex;
	private $errorField;
	private $errorCode;
	private $errorIsExemptable;
	
	private $errorObjArr = array();
	
	private $currentAdWordsErrorObj;
	
	private $clientEmailAddressesArr;
	
	protected $webServiceName;
	
	protected $showResponseText = false;
	
	protected $_parser;
	protected $_curTag;
	
	// In Google for USD ... 10000 micros (one cent).
	// http://code.google.com/apis/adwords/docs/developer/adwords_api_services.html#moneyunits
	const MICRO_CURRENCY_CONVERSION = 1000000;
	
	function __construct() {

		if(empty($this->webServiceName))
			throw new Exception("WebService not defined");
		/*	
		if(Constants::GetDevelopmentServer()){

			$this->email = 'PrintsMadeEasy@Gmail.com';
			$this->password = 'GarlicNaanBread';
			$this->client_email = '';
			$this->useragent = 'PME Dev Server AdwordsAPI';
			$this->developer_token = 'PrintsMadeEasy@Gmail.com++USD';
			$this->application_token = '';
			$this->baseURL = 'https://sandbox.google.com/api/adwords/v13';   // change to sandbox later

		}
		else {	
		*/	
			$this->email = 'PME@gazelleinteractive.com';
			$this->password = Constants::GetGoogleAdwordsPassword();
			$this->client_email = 'brian@printsmadeeasy.com';
			$this->useragent = 'PME AdwordsAPI';
			$this->developer_token = 'SUIvd1CNq8HOwaC0w57zRQ';
			$this->application_token = '5ZGs6ASuNc2NbwBDv3kUNg';
			$this->baseURL = 'https://adwords.google.com/api/adwords/v13';
		/*
		}
		*/
		
		// Put an account description along with every client email address.
		$this->clientEmailAddressesArr["brian@printsmadeeasy.com"] = "PME Pacific & Other";
		$this->clientEmailAddressesArr["brooke5@gazelleinteractive.com"] = "PME Small & Medium Cities";
		$this->clientEmailAddressesArr["brooke3@gazelleinteractive.com"] = "PME Eastern";
		$this->clientEmailAddressesArr["brooke2@gazelleinteractive.com"] = "PME Central & Mountain";
		$this->clientEmailAddressesArr["brooke4@gazelleinteractive.com"] = "PME All Country and Content Network";

	}
	
	// Returns a hash of Client Emails linked to the master AdWords account
	function getClientEmailAddresses(){
		return $this->clientEmailAddressesArr;
	}
	
	// Every Google Login may have multiple accounts (associated with email accounts). 
	// There is a maximum of 100 campaigns allowed per account... so that is why we need many of them.
	function setClientEmail($clientEmailAddress){
		$this->client_email = $clientEmailAddress;
	}
	
	static function convertGooglePriceToLocal($googleMicroUnitPrice){
		return round($googleMicroUnitPrice / self::MICRO_CURRENCY_CONVERSION, 2);
		
	}
	static function convertLocalPriceToGoogleMicroUnits($localPrice){
		if($localPrice === null)
			return null;
		return intval(round($localPrice * self::MICRO_CURRENCY_CONVERSION));
	}
		
	function getTransactionStatus() {
		
		return $this->transnSuccess;
	}
	
	function getLastErrorMessage() {
		
		return $this->faultString;
	}
	
	protected function convertUnixTimeStampToGoogleTimeStamp($unixTimeStamp){
		return date("Y-m-d", $unixTimeStamp);
	}
	
	function soapRequest($requestXML) {
			
		$this->transnSuccess = false;
	
		$this->faultCode = "";
		$this->errorObjArr = array();
		
		$PostString ='<?xml version="1.0" encoding="ISO-8859-1"?><SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/" xmlns:ns2563="http://tempuri.org">' . 
		'<SOAP-ENV:Header>' .
		'<email>' . $this->email . '</email>'.
		'<password>' . $this->password . '</password>' .
		'<clientEmail>' . $this->client_email . '</clientEmail>' .
		'<useragent>' . $this->useragent . '</useragent>' .
		'<developerToken>' . $this->developer_token . '</developerToken>' .
		'<applicationToken>' . $this->application_token . '</applicationToken>' . 
		'</SOAP-ENV:Header><SOAP-ENV:Body>' . $requestXML . '</SOAP-ENV:Body></SOAP-ENV:Envelope>';
			
		// Get rid of any line breaks and add slashes to quotes
		$PostString = preg_replace("/(\r|\n)/", "", $PostString);
		$PostString = preg_replace("/\s{2,}/", " ", $PostString); // get rid of extra white spaces
		$PostString = addslashes($PostString);

		$CurlCom = Constants::GetCurlCommand() . " -k --header \"SOAPAction: ''\" -d \"$PostString\" $this->baseURL/$this->webServiceName";

		$return_value = array();
		exec($CurlCom, $return_value);

		$return_value = implode("", $return_value);

		// $return_value[0] should contain the response from the Curl request
		if(!isset($return_value))
			throw new ExceptionCommunicationError("An error occured making a connection with Curl for AdWords Webservice. Possible Timeout?");

	
		$this->returnedXML = $return_value;
		
		if($this->showResponseText){
			print "<u>Response Text</u><hr>";
			var_dump($this->returnedXML);
			var_dump($return_value);
			print "<hr>";
	
		}		
			
		$this->transnSuccess = true;
		
		// Parse the XML doc looking for faults from the API.  It may reset the $this->transnSuccess variable.
		$this->parseXMLdoc("parseXMLStructureForErrors");
		
		// Only when we have a success flag after checking for errors can we use the inherited classes to extract the details.
		if($this->transnSuccess){
			$this->parseXMLdoc();
		}
		else if(!empty($this->errorObjArr)){
			
			// Error Details do not require us to throw a general Exception. 
			// We really need to create a new Exception Type for AdWordsException. 
			$errorDesc = "Adwords Error:<br><br><br>";
			foreach($this->errorObjArr as $errorObj)
				$errorDesc .= $errorObj->getHtmlErrorDesc() . "<br>";
			exit($errorDesc);
		}
		else{
			throw new Exception("An error occured with the AdWords API: Fault Code: $this->faultCode Fault Description: " . $this->getLastErrorMessage());	
		}
	}
	
	function parseXMLStructureForErrors($_parser, $data){


		// Define all possible tag structures with a carrot separating each tag name.
	
		$soapFaultCode = "^SOAPENV:ENVELOPE^SOAPENV:BODY^SOAPENV:FAULT^FAULTCODE";
		$soapFaultString = "^SOAPENV:ENVELOPE^SOAPENV:BODY^SOAPENV:FAULT^FAULTSTRING";
		
		$soapErrorIndex = "^SOAPENV:ENVELOPE^SOAPENV:BODY^SOAPENV:FAULT^DETAIL^FAULT^ERRORS^INDEX";
		$soapErrorField = "^SOAPENV:ENVELOPE^SOAPENV:BODY^SOAPENV:FAULT^DETAIL^FAULT^ERRORS^FIELD";
		$soapErrorTrigger = "^SOAPENV:ENVELOPE^SOAPENV:BODY^SOAPENV:FAULT^DETAIL^FAULT^ERRORS^TRIGGER";
		$soapErrorCode = "^SOAPENV:ENVELOPE^SOAPENV:BODY^SOAPENV:FAULT^DETAIL^FAULT^ERRORS^CODE";
		$soapErrorIsExemptable = "^SOAPENV:ENVELOPE^SOAPENV:BODY^SOAPENV:FAULT^DETAIL^FAULT^ERRORS^ISEXEMPTABLE";
		$soapErrorDetail = "^SOAPENV:ENVELOPE^SOAPENV:BODY^SOAPENV:FAULT^DETAIL^FAULT^ERRORS^DETAIL";
		
		

		// print $this->_curTag . '<br>';
	
		if ($this->_curTag == $soapFaultCode) {

			$this->faultCode = $data;
			$this->transnSuccess = false;

			$this->faultString = "Google Fault String: ";
		}
		else if ($this->_curTag == $soapFaultString) {
			$this->faultString .= $data;	
		}
		else if ($this->_curTag == $soapErrorIndex) {
			$this->currentAdWordsErrorObj->index = $data;	
		}
		else if ($this->_curTag == $soapErrorField) {
			$this->currentAdWordsErrorObj->field = $data;	
		}
		else if ($this->_curTag == $soapErrorTrigger) {
			$this->currentAdWordsErrorObj->trigger = $data;	
		}
		else if ($this->_curTag == $soapErrorCode) {
			$this->currentAdWordsErrorObj->code = $data;	
		}
		else if ($this->_curTag == $soapErrorIsExemptable) {
			$this->currentAdWordsErrorObj->isExemptable = $data;	
		}
		else if ($this->_curTag == $soapErrorDetail) {
			$this->currentAdWordsErrorObj->detail .= $data;	
		}

	}
	
		
	// The Default Data handler ""parseXMLStructure"" should be handeled by the "derived class".  
	// However, we may want a different handler to run through the XML doc first in the base class checking for errors.
	function parseXMLdoc($cDataHandler = "parseXMLStructure"){

		// Reset our Tag Heirarchy.
		$this->_curTag = "";
		
		// We don't want to fire events on Inherited Classes if we are parsing the XML structure for errors.
		// The Error Message Parsing happens in the Base Class.
		if($cDataHandler == "parseXMLStructureForErrors")
			$this->parsingXMLforErrorsFlag = true;
		else 	
			$this->parsingXMLforErrorsFlag = false;
		
		// Define Event driven callback functions for XPAT processor.
		$this->_parser = xml_parser_create();
		xml_set_object($this->_parser, $this);
		xml_set_element_handler($this->_parser , "startElement", "endElement");
		xml_set_character_data_handler($this->_parser , $cDataHandler);

		// Parse the XMl document.  This will call our functions that we just set handlers for above during the processing.
		if (!xml_parse($this->_parser , $this->returnedXML))
			die(sprintf("XML error: %s at line %d", xml_error_string(xml_get_error_code($this->_parser )), xml_get_current_line_number($this->_parser )));

		xml_parser_free($this->_parser );
	}

	function startElement($_parser, $name, $attrs) {

		$this->_nodeAttributes = $attrs;

		// We don't use the Name Space ID in the tag.  It actually messes up our parsing algorythm.
		// We just rely on new "opening tags" to tell us when we are getting multiple objects in a list.
		$name = preg_replace("/NS\d+:/i", "", $name);
		
		$this->_curTag .= "^$name";
		
		// Don't fire the events for SubClasses if we are looking for errors in the base class.
		if($this->parsingXMLforErrorsFlag){
			
			$soapErrorOpeningTag = "^SOAPENV:ENVELOPE^SOAPENV:BODY^SOAPENV:FAULT^DETAIL^FAULT^ERRORS";
			
			// We may have an array of Error messages... if we come across a new error opening tag, create a new Error Object to hold details.
			if($this->_curTag == $soapErrorOpeningTag)
				$this->currentAdWordsErrorObj = new AdWordsError();
		}
		else{
			$this->eventStartXmlElement();
		}
	}

	function endElement($_parser, $name) {

		// We don't use the Name Space ID in the tag.  It actually messes up our parsing algorythm.
		// We just rely on new "opening tags" to tell us when we are getting multiple objects in a list.
		$name = preg_replace("/NS\d+:/i", "", $name);
		
		$caret_pos = strrpos($this->_curTag,'^');

		// Don't fire the events for SubClasses if we are looking for errors in the base class.
		if($this->parsingXMLforErrorsFlag){
			
			$soapErrorClosingTag = "^SOAPENV:ENVELOPE^SOAPENV:BODY^SOAPENV:FAULT^DETAIL^FAULT^ERRORS";
			
			// We may have an array of Error messages... if we come across an error closing tag, add it to our array.
			if($this->_curTag == $soapErrorClosingTag)
				$this->errorObjArr[] = $this->currentAdWordsErrorObj;
		}
		else{
			$this->eventEndXmlElement();
		}
		
		$this->_curTag = substr($this->_curTag, 0, $caret_pos);
	

	}
	
	function eventStartXmlElement(){
		exit("Must override this method from the Base Class");
	}
	function eventEndXmlElement(){
		exit("Must override this method from the Base Class");
	}
	function parseXMLStructure($_parser, $data) {
		exit("Must override this method from the Base Class");
	}
	
	static function encodeTextForXml($text){
		
		$text = htmlspecialchars($text);
		
		// Some extra characters that need to be encoded for the AdWords API.
		$text = preg_replace("/\\$/", "&#36;", $text);
		$text = preg_replace("/'/", "&#39;", $text);
		
		return $text;
		
	}
	
	
}



