<?php

class AdWordsAdGroup {

	public $id;
	public $campaignId;
	public $keywordContentMaxCpc;
	public $keywordMaxCpc;
	public $maxCpa;
	public $name;
	public $proxyKeywordMaxCpc;
	public $siteMaxCpc;
	public $siteMaxCpm;
	public $status;

	
	function getAdGroupXml(){
		
		$retXml = "";
		$retXml .= "<campaignId>" . $this->campaignId . "</campaignId>\n";
		$retXml .= "<id>" . $this->id . "</id>\n";
		$retXml .= "<keywordContentMaxCpc>" . AdWordsBase::convertLocalPriceToGoogleMicroUnits($this->keywordContentMaxCpc) . "</keywordContentMaxCpc>\n";
		$retXml .= "<keywordMaxCpc>" . AdWordsBase::convertLocalPriceToGoogleMicroUnits($this->keywordMaxCpc) . "</keywordMaxCpc>\n";
		$retXml .= "<maxCpa>" . AdWordsBase::convertLocalPriceToGoogleMicroUnits($this->maxCpa) . "</maxCpa>\n";
		$retXml .= "<name>" . $this->name . "</name>\n";
		$retXml .= "<proxyKeywordMaxCpc>" . AdWordsBase::convertLocalPriceToGoogleMicroUnits($this->proxyKeywordMaxCpc) . "</proxyKeywordMaxCpc>\n";
		$retXml .= "<siteMaxCpc>" . AdWordsBase::convertLocalPriceToGoogleMicroUnits($this->siteMaxCpc) . "</siteMaxCpc>\n";
		$retXml .= "<siteMaxCpm>" . AdWordsBase::convertLocalPriceToGoogleMicroUnits($this->siteMaxCpm) . "</siteMaxCpm>\n";
		$retXml .= "<status>" . $this->status . "</status>\n";
		
		return $retXml;
	}

}







