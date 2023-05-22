<?php

class AdWordsTextAd {

	public $adGroupId;
	public $adType;
	public $description1;
	public $description2;
	public $destinationUrl;
	public $disapproved;
	public $displayUrl;
	public $exemptionRequest;
	public $headline;
	public $id;
	public $status;

	
	
	function getTextAdXml(){
		
		$retXml = "";
		$retXml .= "<adGroupId>" . $this->adGroupId . "</adGroupId>\n";
		$retXml .= "<adType>" . $this->adType . "</adType>\n";
		$retXml .= "<description1>" . AdWordsBase::encodeTextForXml($this->description1) . "</description1>\n";
		$retXml .= "<description2>" . AdWordsBase::encodeTextForXml($this->description2) . "</description2>\n";
		$retXml .= "<destinationUrl>" . AdWordsBase::encodeTextForXml($this->destinationUrl) . "</destinationUrl>\n";
		$retXml .= "<disapproved>" . $this->disapproved . "</disapproved>\n";
		$retXml .= "<displayUrl>" . AdWordsBase::encodeTextForXml($this->displayUrl) . "</displayUrl>\n";
		$retXml .= "<exemptionRequest>" . $this->exemptionRequest . "</exemptionRequest>\n";
		$retXml .= "<headline>" . AdWordsBase::encodeTextForXml($this->headline) . "</headline>\n";
		$retXml .= "<id>" . $this->id . "</id>\n";
		$retXml .= "<status>" . $this->status . "</status>\n";

		return $retXml;
	}
	

}







