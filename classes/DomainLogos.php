<?php

class DomainLogos {
	
	public $verySmallIcon;
	public $navBarIcon;
	public $printQualtityMediumJPG;
	

	
	public function __construct($domainID){
		
		if(!Domain::checkIfDomainIDexists($domainID))
			throw new Exception("Error in Domain Logo Configuration. The Domain ID does not exist.");
			
		$domainKey = Domain::getDomainKeyFromID($domainID);
		
		if($domainKey == "PrintsMadeEasy.com"){
			$this->verySmallIcon = "pme-vsi.png";
			$this->navBarIcon = "pme-nbi.png";
			$this->printQualtityMediumJPG = "pme-pqm.jpg";
		}
		else if($domainKey == "Postcards.com"){
			$this->verySmallIcon = "pcc-vsi.png";
			$this->navBarIcon = "pcc-nbi.png";
			$this->printQualtityMediumJPG = "pcc-pqm.jpg";
		}
		else if($domainKey == "Bang4BuckPrinting.com"){
			$this->verySmallIcon = "b4b-vsi.png";
			$this->navBarIcon = "b4b-nbi.png";
			$this->printQualtityMediumJPG = "b4b-pqm.jpg";
		}
		else if($domainKey == "MarketingEngine.biz"){
			$this->verySmallIcon = "meb-vsi.png";
			$this->navBarIcon = "meb-nbi.png";
			$this->printQualtityMediumJPG = "meb-pqm.jpg";
		}
		else if($domainKey == "DotCopyShop.com"){
			$this->verySmallIcon = "dcs-vsi.png";
			$this->navBarIcon = "dcs-nbi.png";
			$this->printQualtityMediumJPG = "dcs-nbi.jpg";
		}
		else if($domainKey == "Letterhead.com"){
			$this->verySmallIcon = "lhc-vsi.png";
			$this->navBarIcon = "lhc-nbi.png";
			$this->printQualtityMediumJPG = "lhc-pqm.jpg";
		}
		else if($domainKey == "PostcardPrinting.com"){
			$this->verySmallIcon = "postprint-vsi.png";
			$this->navBarIcon = "postprint-nbi.png";
			$this->printQualtityMediumJPG = "postprint-pqm.jpg";
		}
		else if($domainKey == "VinylBanners.com"){
			$this->verySmallIcon = "VinBanners-vsi.png";
			$this->navBarIcon = "VinBanners-nbi.png";
			$this->printQualtityMediumJPG = "VinBanners-pqm.jpg";
		}
		else if($domainKey == "RefrigeratorMagnets.com"){
			$this->verySmallIcon = "fridgeMags-vsi.png";
			$this->navBarIcon = "fridgeMags-nbi.png";
			$this->printQualtityMediumJPG = "fridgeMags-pqm.jpg";
		}
		else if($domainKey == "SunAmerica.com"){
			$this->verySmallIcon = "sunAmerica-vsi.png";
			$this->navBarIcon = "sunAmerica-nbi.png";
			$this->printQualtityMediumJPG = "sunAmerica-pqm.jpg";
		}
		else if($domainKey == "CarMagnets.com"){
			$this->verySmallIcon = "careMags-vsi.jpg";
			$this->navBarIcon = "careMags-nbi.jpg";
			$this->printQualtityMediumJPG = "careMags-pqm.jpg";
		}
		else if($domainKey == "BusinessCards24.com"){
			$this->verySmallIcon = "bizCrd24-vsi.png";
			$this->navBarIcon = "bizCrd24-nbi.png";
			$this->printQualtityMediumJPG = "bizCrd24-pqm.jpg";
		}
		else if($domainKey == "ZEN_Development"){
			$this->verySmallIcon = "zen-vsi.png";
			$this->navBarIcon = "zen-nbi.png";
			$this->printQualtityMediumJPG = "zen-pqm.jpg";
		}
		else if($domainKey == "BusinessHolidayCards.com"){
			$this->verySmallIcon = "BHC-vsi.png";
			$this->navBarIcon = "BHC-nbi.png";
			$this->printQualtityMediumJPG = "BHC-pqm.jpg";
		}
		else if($domainKey == "LuauInvitations.com"){
			$this->verySmallIcon = "Li-vsi.png";
			$this->navBarIcon = "Li-nbi.png";
			$this->printQualtityMediumJPG = "Li-pqm.jpg";
		}
		else if($domainKey == "ThankYouCards.com"){
			$this->verySmallIcon = "Ty-vsi.png";
			$this->navBarIcon = "Ty-nbi.png";
			$this->printQualtityMediumJPG = "Ty-pqm.jpg";
		}
		else if($domainKey == "DotGraphics.net"){
			$this->verySmallIcon = "dot-vsi.png";
			$this->navBarIcon = "dot-nbi.png";
			$this->printQualtityMediumJPG = "dot-pqm.jpg";
		}
		else if($domainKey == "MyGymPrinting.com"){
			$this->verySmallIcon = "mygym-vsi.png";
			$this->navBarIcon = "mygym-nbi.png";
			$this->printQualtityMediumJPG = "mygym-pqm.jpg";
		}
		else if($domainKey == "CalsonWagonlitPrinting.com"){
			$this->verySmallIcon = "carlson-vsi.png";
			$this->navBarIcon = "carlson-nbi.png";
			$this->printQualtityMediumJPG = "carlson-pqm.jpg";
		}
		else if($domainKey == "BusinessCards.co.uk"){
			$this->verySmallIcon = "bizCoUk-vsi.png";
			$this->navBarIcon = "bizCoUk-nbi.png";
			$this->printQualtityMediumJPG = "bizCoUk-pqm.jpg";
		}
		else if($domainKey == "PowerBalancePrinting.com"){
			$this->verySmallIcon = "powerB-vsi.png";
			$this->navBarIcon = "powerB-nbi.png";
			$this->printQualtityMediumJPG = "powerB-pqm.jpg";
		}
		else if($domainKey == "FlyerPrinting.com"){
			$this->verySmallIcon = "FlyerPrint-vsi.png";
			$this->navBarIcon = "FlyerPrint-nbi.png";
			$this->printQualtityMediumJPG = "FlyerPrint-pqm.jpg";
		}
		else if($domainKey == "HolidayGreetingCards.com"){
			$this->verySmallIcon = "HoldayGreeCrds-vsi.png";
			$this->navBarIcon = "HoldayGreeCrds-nbi.png";
			$this->printQualtityMediumJPG = "HoldayGreeCrds-pqm.jpg";
		}
		else if($domainKey == "ChristmasPhotoCards.com"){
			$this->verySmallIcon = "christPtcrd-vsi.png";
			$this->navBarIcon = "christPtcrd-nbi.png";
			$this->printQualtityMediumJPG = "christPtcrd-pqm.jpg";
		}
		else if($domainKey == "AmazingGrass.com"){
			$this->verySmallIcon = "AmznGrass-vsi.png";
			$this->navBarIcon = "AmznGrass-nbi.png";
			$this->printQualtityMediumJPG = "AmznGrass-pqm.jpg";
		}
		else if($domainKey == "PosterPrinting.com"){
			$this->verySmallIcon = "posterPrnt-vsi.png";
			$this->navBarIcon = "posterPrnt-nbi.png";
			$this->printQualtityMediumJPG = "posterPrnt-pqm.jpg";
		}
		else if($domainKey == "HotLaptopSkin.com"){
			$this->verySmallIcon = "HotLapt-vsi.png";
			$this->navBarIcon = "HotLapt-nbi.png";
			$this->printQualtityMediumJPG = "HotLapt-pqm.jpg";
		}
		else if($domainKey == "HouseOfBluesPrinting.com"){
			$this->verySmallIcon = "HouseBlues-vsi.png";
			$this->navBarIcon = "HouseBlues-nbi.png";
			$this->printQualtityMediumJPG = "HouseBlues-pqm.jpg";
		}
		else if($domainKey == "BridalShowerInvitations.com"){
			$this->verySmallIcon = "bridalSh-vsi.png";
			$this->navBarIcon = "bridalSh-nbi.png";
			$this->printQualtityMediumJPG = "bridalSh-pqm.jpg";
		}
		else if($domainKey == "NNAServices.org"){
			$this->verySmallIcon = "NNAServicesOrg-vsi.png";
			$this->navBarIcon = "NNAServicesOrg-nbi.png";
			$this->printQualtityMediumJPG = "NNAServicesOrg-pqm.jpg";
		}
		else if($domainKey == "ShakeysServices.com"){
			$this->verySmallIcon = "ShakeysServicesCom-vsi.png";
			$this->navBarIcon = "ShakeysServicesCom-nbi.png";
			$this->printQualtityMediumJPG = "ShakeysServicesCom-pqm.jpg";
		}
		else if($domainKey == "YourOnlinePrintingCompany.com"){
			$this->verySmallIcon = "OnlinePrintingCompany-vsi.png";
			$this->navBarIcon = "OnlinePrintingCompany-nbi.png";
			$this->printQualtityMediumJPG = "OnlinePrintingCompany-pqm.jpg";
		}
		else{
			WebUtil::WebmasterError("Error in class DomainLogos: " . $domainKey);
			throw new Exception("Error in Domain Logo Configuration");
		}
		
		$this->ensureJPEGforPrintIcon();
		
	}
	
	public function getPrintQualityMediumJpegDateModified(){
		$imagePath = Constants::GetWebserverBase() . "/domain_logos/" . $this->printQualtityMediumJPG;
		if(!file_exists($imagePath))
			throw new Exception("The domain logo does not exist");
			
		return filemtime($imagePath);
	}
	public function getPrintQualityMediumJpegBin(){
		$imagePath = Constants::GetWebserverBase() . "/domain_logos/" . $this->printQualtityMediumJPG;
		if(!file_exists($imagePath))
			throw new Exception("The domain logo does not exist");
			
		return file_get_contents($imagePath);
	}
	
	private function ensureJPEGforPrintIcon(){

		if(!preg_match("/jpg$/i", $this->printQualtityMediumJPG) && !preg_match("/jpeg$/i", $this->printQualtityMediumJPG))
			throw new Exception("Error setting up Domain Logo for Printing.");
	}
}

?>