<?php

class DomainPaths {

	private $domainID;
	private $domainKey;
	private $pdf_web_path;
	private $pdf_system_path;

	public function __construct($domainID){
		
		if(!Domain::checkIfDomainIDexists($domainID))
			throw new Exception("Error in Domain Path Configuration. The Domain ID does not exist.");
			
		$this->domainKey = Domain::getDomainKeyFromID($domainID);
		$this->domainID = $domainID;
		
		$websiteUrlForDomain = Domain::getWebsiteURLforDomainID($this->domainID);
		
		if(in_array($this->domainKey, array("PrintsMadeEasy.com", "MarketingEngine.biz", "DotCopyShop.com"))){
			$this->pdf_web_path = "http://" . $websiteUrlForDomain . "/previews"; 
			$this->pdf_system_path = Domain::getDomainSandboxPath($this->domainKey) . "/previews";
		}
		else{
			$this->pdf_web_path = "http://" . $websiteUrlForDomain . "/previews"; 
			$this->pdf_system_path = Domain::getDomainSandboxPath($this->domainKey) . "/previews";
		}
		
		// In case the Preview directory has not been within the sandbox yet.
		if(!file_exists($this->pdf_system_path)){
			WebUtil::WebmasterError(("Create the preview directory within the domain sandbox: " . $this->domainKey), "Domain Config");
		}


		
	}
	

	public function getPdfWebPath($secure = false){
		if($secure){
			return preg_replace("/^http:/", (Constants::GetServerSSL() . ":"), $this->pdf_web_path);
		}
		else{
			return $this->pdf_web_path;
		}
	}
	
	public function getPdfSystemPath(){
		return $this->pdf_system_path;
	}
	
	static function getPdfWebPathOfDomainInURL(){
		$domainPathObj = new DomainPaths(Domain::getDomainIDfromURL());
		return $domainPathObj->getPdfWebPath(WebUtil::checkIfInSecureMode());
	}
	
	static function getPdfSystemPathOfDomainInURL(){
		$domainPathObj = new DomainPaths(Domain::getDomainIDfromURL());
		return $domainPathObj->getPdfSystemPath();
	}
	

}

?>