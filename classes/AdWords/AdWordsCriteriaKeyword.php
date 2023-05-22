<?php

class AdWordsCriteriaKeyword {

	public $adGroupId;
	public $criterionType;
	public $destinationUrl;
	public $exemptionRequest;
	public $firstPageCpc;
	public $id;
	public $language;
	public $maxCpc;
	public $negative;
	public $paused;
	public $proxyMaxCpc;
	public $qualityScore;
	public $status;
	public $text;
	public $type;
	
	
	function getKeywordCriteriaXml(){
		
		$retXml = "";
		$retXml .= "<adGroupId>" . $this->adGroupId . "</adGroupId>\n";
		$retXml .= "<criterionType>" . $this->criterionType . "</criterionType>\n";
		$retXml .= "<destinationUrl>" . AdWordsBase::encodeTextForXml($this->destinationUrl) . "</destinationUrl>\n";
		$retXml .= "<exemptionRequest>" . $this->exemptionRequest . "</exemptionRequest>\n";
		$retXml .= "<firstPageCpc>" . AdWordsBase::convertLocalPriceToGoogleMicroUnits($this->firstPageCpc) . "</firstPageCpc>\n";
		$retXml .= "<id>" . $this->id . "</id>\n";
		$retXml .= "<language>" . $this->language . "</language>\n";
		$retXml .= "<maxCpc>" . AdWordsBase::convertLocalPriceToGoogleMicroUnits($this->maxCpc) . "</maxCpc>\n";
		$retXml .= "<negative>" . $this->negative . "</negative>\n";
		$retXml .= "<paused>" . $this->paused . "</paused>\n";
		$retXml .= "<proxyMaxCpc>" . AdWordsBase::convertLocalPriceToGoogleMicroUnits($this->proxyMaxCpc) . "</proxyMaxCpc>\n";
		$retXml .= "<qualityScore>" . $this->qualityScore . "</qualityScore>\n";
		$retXml .= "<status>" . $this->status . "</status>\n";
		$retXml .= "<text>" . AdWordsBase::encodeTextForXml($this->text) . "</text>\n";
		$retXml .= "<type>" . $this->type . "</type>\n";
		
		return $retXml;
	}

}







