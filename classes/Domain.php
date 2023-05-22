<?

class Domain {
	
	private $_cookieTime;
	
	private $_dbCmd;
	
    private static $instance;
 	
    private static $_domainsArr = array();
	private static $_domainIDsArr = array();
    
	private static $_enforcedDomainID;
	
	private static $_oneDomainIDalreadyReturned;
	
	// So we can get access to the Domains that we "set" without having to wait for the next page to come back and re-send them. 
	private static $_cookieDomainsIDs;

	
	private static $selectedDomainIDsCache = array();
	
    // singleton
	private function Domain(){
		
		$DaysToRemember = 200;
		$this->_cookieTime = time()+60*60*24 * $DaysToRemember;		
		
		self::_initializeDomains();
		
		$this->_dbCmd = new DbCmd();	

	}
	
	// Setup the domain list.  Has to be static because some other methods Like Authentication require a domain object to be initialized... and the Domain Class relies on an Authenication Object to be initialized.
	// The key to the domain is what we will show throughout the backend as a label (upercase & lowercase)
	// Then define an array of synonmyms that the website may function under.
	static function _initializeDomains(){
	
		// Only initialize once.
		if(sizeof(self::$_domainsArr) > 0)
			return;
			

		self::$_domainsArr["PrintsMadeEasy.com"] = array("PrintsMadeEasy.com", "www.PrintsMadeEasy.com", "mail.PrintsMadeEasy.com", "s10.PrintsMadeEasy.com", "printsmadeeasy.com:80", "www.printsmadeeasy.com:80", "74.53.59.100");
		self::$_domainsArr["Postcards.com"] = array("www.Postcards.com", "mail.Postcards.com", "Postcards.com", "Postcards.com:80", "www.Postcards.com:80", "74.53.2.167");
		self::$_domainsArr["Bang4BuckPrinting.com"] = array("www.Bang4BuckPrinting.com");
		self::$_domainsArr["MarketingEngine.biz"] = array("www.MarketingEngine.biz");
		self::$_domainsArr["DotCopyShop.com"] = array("www.DotCopyShop.com");
		self::$_domainsArr["Letterhead.com"] = array("www.Letterhead.com", "Letterhead.com", "www.Letterhead.com:80", "Letterhead.com:80", "74.53.59.106");
		self::$_domainsArr["VinylBanners.com"] = array("www.VinylBanners.com", "VinylBanners.com", "www.VinylBanners.com:80", "VinylBanners.com:80", "74.53.59.108");
		self::$_domainsArr["RefrigeratorMagnets.com"] = array("www.RefrigeratorMagnets.com", "RefrigeratorMagnets.com", "www.RefrigeratorMagnets.com:80", "RefrigeratorMagnets.com:80", "74.53.59.111");
		self::$_domainsArr["CarMagnets.com"] = array("www.CarMagnets.com", "CarMagnets.com", "www.CarMagnets.com:80", "CarMagnets.com:80", "74.53.59.110");
		self::$_domainsArr["PostcardPrinting.com"] = array("www.PostcardPrinting.com", "PostcardPrinting.com", "www.PostcardPrinting.com:80", "PostcardPrinting.com:80", "74.53.59.113");
		self::$_domainsArr["BusinessCards24.com"] = array("www.BusinessCards24.com", "BusinessCards24.com", "www.BusinessCards24.com:80", "BusinessCards24.com:80", "74.53.59.115");
		self::$_domainsArr["ZEN_Development"] = array("74.53.59.116", "ZEN_Development", "www.Zen-Dev.com");
		self::$_domainsArr["BusinessHolidayCards.com"] = array("www.BusinessHolidayCards.com", "BusinessHolidayCards.com", "www.BusinessHolidayCards.com:80", "BusinessHolidayCards.com:80", "74.53.59.119");
		self::$_domainsArr["LuauInvitations.com"] = array("www.LuauInvitations.com", "LuauInvitations.com", "www.LuauInvitations.com:80", "LuauInvitations.com:80", "74.53.59.118");
		self::$_domainsArr["ThankYouCards.com"] = array("www.ThankYouCards.com", "ThankYouCards.com", "www.ThankYouCards.com:80", "ThankYouCards.com:80", "74.53.59.117");
		self::$_domainsArr["DotGraphics.net"] = array("www.DotGraphics.net", "DotGraphics.net", "www.DotGraphics.net:80", "DotGraphics.net:80", "74.53.2.163");
		self::$_domainsArr["SunAmerica.com"] = array("marketing.SunAmerica.com", "74.53.59.120", "SunAmerica.com", "www.SunAmerica.com");
		self::$_domainsArr["MyGymPrinting.com"] = array("printing.MyGym.com", "74.53.0.27", "www.MyGymPrinting.com", "MyGymPrinting.com");
		self::$_domainsArr["CalsonWagonlitPrinting.com"] = array("printing.CalsonWagonlit.com", "74.53.0.28", "www.CalsonWagonlitPrinting.com", "CalsonWagonlitPrinting.com");
		self::$_domainsArr["BusinessCards.co.uk"] = array("BusinessCards.co.uk", "74.53.0.28", "www.BusinessCards.co.uk");
		self::$_domainsArr["PowerBalancePrinting.com"] = array("PowerBalancePrinting.com", "74.53.2.160");
		self::$_domainsArr["FlyerPrinting.com"] = array("FlyerPrinting.com", "74.53.59.112");
		self::$_domainsArr["HolidayGreetingCards.com"] = array("HolidayGreetingCards.com", "74.53.59.114");
		self::$_domainsArr["ChristmasPhotoCards.com"] = array("ChristmasPhotoCards.com", "74.53.59.122");
		self::$_domainsArr["AmazingGrass.com"] = array("AmazingGrass.com", "74.53.59.101", "printing.amazinggrass.com");
		self::$_domainsArr["PosterPrinting.com"] = array("PosterPrinting.com", "74.53.59.123");
		self::$_domainsArr["HotLaptopSkin.com"] = array("HotLaptopSkin.com", "74.53.59.124");
		self::$_domainsArr["BridalShowerInvitations.com"] = array("BridalShowerInvitations.com", "74.53.2.161");
		self::$_domainsArr["HouseOfBluesPrinting.com"] = array("HouseOfBluesPrinting.com", "74.53.59.103");
		self::$_domainsArr["NNAServices.org"] = array("NNAServices.org", "74.53.2.162");
		self::$_domainsArr["ShakeysServices.com"] = array("ShakeysServices.com", "74.53.2.164");
		self::$_domainsArr["YourOnlinePrintingCompany.com"] = array("YourOnlinePrintingCompany.com", "74.53.2.166");
		
		self::$_domainIDsArr[1] = "PrintsMadeEasy.com";
		self::$_domainIDsArr[2] = "Postcards.com";
		self::$_domainIDsArr[3] = "Bang4BuckPrinting.com";
		self::$_domainIDsArr[4] = "MarketingEngine.biz";
		self::$_domainIDsArr[5] = "DotCopyShop.com";
		self::$_domainIDsArr[6] = "Letterhead.com";
		self::$_domainIDsArr[7] = "VinylBanners.com";
		self::$_domainIDsArr[8] = "RefrigeratorMagnets.com";
		self::$_domainIDsArr[9] = "PostcardPrinting.com";
		self::$_domainIDsArr[10] = "BusinessCards24.com";
		self::$_domainIDsArr[11] = "ZEN_Development";
		self::$_domainIDsArr[12] = "BusinessHolidayCards.com";
		self::$_domainIDsArr[13] = "LuauInvitations.com";
		self::$_domainIDsArr[14] = "ThankYouCards.com";
		self::$_domainIDsArr[15] = "DotGraphics.net";
		self::$_domainIDsArr[16] = "CarMagnets.com";
		self::$_domainIDsArr[17] = "SunAmerica.com";
		self::$_domainIDsArr[18] = "MyGymPrinting.com";
		self::$_domainIDsArr[19] = "CalsonWagonlitPrinting.com";
		self::$_domainIDsArr[20] = "BusinessCards.co.uk";
		self::$_domainIDsArr[21] = "PowerBalancePrinting.com";
		self::$_domainIDsArr[22] = "FlyerPrinting.com";
		self::$_domainIDsArr[23] = "HolidayGreetingCards.com";
		self::$_domainIDsArr[24] = "ChristmasPhotoCards.com";
		self::$_domainIDsArr[25] = "AmazingGrass.com";
		self::$_domainIDsArr[26] = "PosterPrinting.com";
		self::$_domainIDsArr[27] = "HotLaptopSkin.com";
		self::$_domainIDsArr[28] = "BridalShowerInvitations.com";
		self::$_domainIDsArr[29] = "HouseOfBluesPrinting.com";
		self::$_domainIDsArr[30] = "NNAServices.org";
		self::$_domainIDsArr[31] = "ShakeysServices.com";
		self::$_domainIDsArr[32] = "YourOnlinePrintingCompany.com";
		
		
		// On the development system it is only possible to view one Domain Sandbox at a time.  
		// Put the "localhost" parameter wherever you want to practice your development.
		if(Constants::GetDevelopmentServer()){
			self::$_domainsArr["BusinessCards24.com"][] = "localhost";
			self::$_domainsArr["BusinessCards24.com"][] = "127.0.0.1";
		}
		
	}
	
	
    // The singleton method
    /**
     * @return Domain
     */
    public static function singleton() 
    {
        if (!isset(self::$instance)) {
            $c = __CLASS__;
            self::$instance = new $c;
        }
         
        return self::$instance;
    }
    
    // Works the same as getSingleDomainID() ... but it is a little easer to use in a static call sometimes.
    // Throws a ExceptionSingleDomainRequired if the user has multiple domains selected.
    public static function oneDomain(){
	
    	$domainObj = Domain::singleton();
    	return $domainObj->getSingleDomainID();
    }
    
    
    // Returns True or False depending on whether the Domain is configured to Downgrade Shipping.
    // In other words... the domain calls Shipping Methods their own names internally and uses the cheapest method/carrier to get it to the customer by the date promised.
    // Such as if someone pays for overnight shipping (and they live down the street) we can send it by Ground and it will get there on time.
 	// If the Domain does not downgrade shipping then the package use exactly whatever method the customer chose.
    // Pass in a Domain ID if you want to direct twoards that.
	// Otherwise it will get th domain based upon the URL you are looking at.
    function doesDomainDowngradeShipping($domainIDOverride = null){
    	
    	if(empty($domainIDOverride)){
			$domainName = self::getDomainKeyFromURL();
		}
		else{
			if(!self::checkIfDomainIDexists($domainIDOverride)){
				WebUtil::WebmasterError("DomainError: " . "Error in method getShippingCarriersForDomain.  The domain is not valid.");
				exit("Domain Error");
			}

			$domainName = self::getDomainKeyFromID($domainIDOverride);
		}
		
    	if(in_array($domainName, array("PrintsMadeEasy.com", "Postcards.com", "Letterhead.com")))
			return true;
		else
			return true;

    }
	
	
	// Returns an array of Shipping Carriers names.
	// Pass in a Domain name if you want to direct twoards that.
	// Otherwise it will get th domain based upon the URL you are looking at.
	// The Shipping Carrier listed at the top of the array is the "Primary Shipping Carrier"
	function getShippingCarriersForDomain($domainIDOverride = null){
		
		if(empty($domainIDOverride)){
			$domainName = self::getDomainKeyFromURL();
		}
		else{
			if(!self::checkIfDomainIDexists($domainIDOverride)){
				WebUtil::WebmasterError("DomainError: " . "Error in method getShippingCarriersForDomain.  The domain is not valid.");
				exit("Domain Error");
			}

			$domainName = self::getDomainKeyFromID($domainIDOverride);
		}
		
		if(in_array($domainName, array("PrintsMadeEasy.com", "Postcards.com", "Bang4BuckPrinting.com", "Letterhead.com", "MarketingEngine.biz"))){
			return array("UPS", "USPS");
		}
		else{
			return array("UPS", "USPS");
		}
	}
	
	
	
	
	
	
	// Enforcing a Domain also overwrites the Session TopDomainID
	// Useful on an "Order Screen" when you want to make sure all modules on the page are in agreement. 
	// Nobody can overwrite each other's enforcement during the execution of the script/thread.  Multiple modules can call this method, as long as the Domain ID's match.
	// This enforcement ends as soon as the HTML is sent to the client's browser.
	// It also makes sure that the person has permission to view the domain.
	// That means in just one method call you can both ensure Domain ID compliance between all modules and ensure Authenication privelages.
	// Best practice is to put this method call at the very top of the script before any other modules are created.
	static function enforceTopDomainID($domainID){

		if(!empty(self::$_enforcedDomainID) && self::$_enforcedDomainID != $domainID){
			WebUtil::WebmasterError("DomainError: " . "Conflict in enforceTopDomainID.");
			exit("Domain Error");
		}
		
		if(!empty(self::$_oneDomainIDalreadyReturned) && self::$_oneDomainIDalreadyReturned != $domainID){
			WebUtil::WebmasterError("DomainError: " . "Error in method enforceTopDomainID, the enforced Top Domain ID has already been compromised.");
			exit("Domain Error");
		}
			
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		if(!$passiveAuthObj->CheckIfUserCanViewDomainID($domainID)){
			WebUtil::WebmasterError("DomainError: " . "Error in method enforceTopDomainID.");
			exit("Domain Error");
		}
			
			
		self::$_enforcedDomainID = $domainID;
			
		WebUtil::SetSessionVar("topDomainID", intval($domainID));
		
		// Make sure that the domain is in the users selected list (if it is an admin)
		self::addDomainIDtoSelectedList($domainID);
	}
	
	
	
	static function setTopDomainID($domainID){
		
		$domainID = intval($domainID);
		
		// Only throws an error if enforceDomainID is called before this latest method call... and if it is different.
		if(!empty(self::$_enforcedDomainID) && self::$_enforcedDomainID != $domainID){
			WebUtil::WebmasterError("DomainError: " . "Error in method setTopDomainID. The DomainID has already been enforced.");
			exit("Domain Error");
		}
		
		if(!empty(self::$_oneDomainIDalreadyReturned) && self::$_oneDomainIDalreadyReturned != $domainID){
			WebUtil::WebmasterError("DomainError: " . "Error in method setTopDomainID.  You are setting a new Top Domain ID.  A module earlier in this script/thread has already returned a different Domain ID.");
			exit("Domain Error");
		}
			
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		if(!$passiveAuthObj->CheckIfUserCanViewDomainID($domainID)){
			WebUtil::WebmasterError("DomainError: " . "Error in method setTopDomainID.");
			exit("Domain Error");
		}
			
		WebUtil::SetSessionVar("topDomainID", intval($domainID));
		
		self::$_oneDomainIDalreadyReturned = $domainID;
		
		// Make sure that the domain is in the users selected list (if it is an admin)
		self::addDomainIDtoSelectedList($domainID);
	}
	
	
	// This can be called on a page where there should be no bias twoards the data you are looking at (if there are multiple domains selected).
	// Such as the Home Screen, or a Month Report.
	static function removeTopDomainID(){
		
		// Only throws an error if enforceDomainID is called before this latest method call. 
		if(!empty(self::$_enforcedDomainID)){
			WebUtil::WebmasterError("DomainError: " . "Error in method removeTopDomainID. The DomainID has already been enforced.");
			exit("Domain Error");	
		}
			
		WebUtil::SetSessionVar("topDomainID", "");
	}
	
	
	// If the user has not selected the domainID yet (for their permanent cookie)
	// This will add it to their list of selections (if it has not already been selected).
	// If the user is not an Administrator... then it will not try to set a this cookie.
	static function addDomainIDtoSelectedList($domainID){
		
		if(!Domain::checkIfDomainIDexists($domainID)){
			WebUtil::WebmasterError("DomainError: " . "Error in method addDomainIDtoSelectedList");
			exit("Domain Error");	
		}
			
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		if(!$passiveAuthObj->CheckIfLoggedIn() || !$passiveAuthObj->CheckIfBelongsToGroup("MEMBER"))
			return;
		
		$domainObj = Domain::singleton();
		
		$selectedDomainIDs = $domainObj->getSelectedDomainIDs();
		$selectedDomainIDs[] = $domainID;

		$domainObj->setDomains($selectedDomainIDs);
	}
	
	static function removeDomainIDfromSelectedList($domainID){
		
		if(!Domain::checkIfDomainIDexists($domainID)){
			WebUtil::WebmasterError("DomainError: " . "Error in method removeDomainIDfromSelectedList");
			exit("Domain Error");
		}
		
		$domainObj = Domain::singleton();
		
		$selectedDomainIDs = $domainObj->getSelectedDomainIDs();
		
		WebUtil::array_delete($selectedDomainIDs, $domainID);

		$this->setDomains($selectedDomainIDs);
	}
	
	
	
	// Returns the Top Domain ID set in the person's session.
	// If the value has not been saved, then it will will return null.
	// It will make sure that the Domain ID is also part of the users "Selected Domains", or it will erase the TopDomainID and return NULL.
	// By making sure that the TopDomainID exist in the SelectedID list... that also means that they have Authenication privelages for it.
	// If there is not a TopDomainID set... and a person has multiple Domains selected... there is a chance that an ExceptionSingleDomainRequired will get thrown.
	function getTopDomainID(){
		
		$topDomainIDfromSession = WebUtil::GetSessionVar("topDomainID");
		
		if(empty($topDomainIDfromSession))
			return null;
			
		// If they don't have permission to view the TopDomainID, or they don't have that in their selected list, then erase.
		$selectedDomainIDs = $this->getSelectedDomainIDs();
		if(!in_array($topDomainIDfromSession, $selectedDomainIDs)){
			self::removeTopDomainID();
			return null;
		}
		
		return $topDomainIDfromSession;
	}
	
	
	
	// Some parts of the website rely on only 1 domain being selected.
	// If multiple domains are selected then this method will thow an ExceptionSingleDomainRequired.
	// In case you have multiple domains selected it will prefer not to throw an exception, by trying to infer what domain is applicable.
	// ...	If the file name of the PHP script (based on crude pattern match) doesn't look like an "admin page", then it will just choose the domain that you are viewing in your browser. (Authentication class does extra security checking to thwart hacking).
	// ...	If the user is not logged in, then it will return the domain that the user has in their browser.
	// ... 	If the user doesn't have admin privelages ("Belonging to the Authentication Group 'Member') then they will always be forced to use the domain in their browser.
	// There is also a way to "narrow" your domain selection ahead of time.  This is really what happens after after the ExceptionSingleDomainRequired.  It should show you a page where you specifiy what domain you want exactly.
	// ... For example, maybe a "CouponCollection" class requires you explicity choose a domain before showing a list. 
	// ... Calling "setTopDomainID" will will set a Session variable  (multiple Domains are stored in a permanent cookie). 
	// You can also call "enforceTopDomainID". that is more protective.  This is needed when you are viewing something as an "Order Screen", where module running in the thread must use that domain ID.  
	// .... this class will protect other modules from trying to "enforce" a different Domain ID (causing a conflict).  Also protects against other modules from trying to "setSessionDomainID" to something conflicting. 
	// If the URL of the PHP script looks like it is not Administrative... it will temporarily use the DomainID associated with the URL (without setting a cookie such as in setTopDomainID()).
	// ... If you do not want the PHP script temporarily overriding your "TopDomainID" session cookie... then you can enforceTopDomainID() on that script to be whatever you like. 
	function getSingleDomainID(){

		// Cache result for the Script/Thread excecution because there could be many calls throughout various modules.
		// This also ensures that every module on the script/thread is using the same DomainID.
		if(!empty(self::$_oneDomainIDalreadyReturned))
			return self::$_oneDomainIDalreadyReturned;
		
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		
		if(!$passiveAuthObj->CheckIfLoggedIn())
			return self::getDomainID(self::getDomainKeyFromURL());
		
		// If the HTML/PHP script looks like a filename belonging to the front-end (and the DomainID has not been enforced). This return the DomaindID that is showing of the URL in the browser.
		if(empty(self::$_enforcedDomainID) && self::isUrlForTheFrontEnd())
			return self::getDomainID(self::getDomainKeyFromURL());
			
		$selectedDomainIDsArr = $this->getSelectedDomainIDs();
		
		$returnDomainID = null;
		
		if(sizeof($selectedDomainIDsArr) > 1){
			
			// If the user has multiple Domains Selected, let's see if they have a TopDomainID set.
			// If not, we have to throw an exception.
			$topDomainID = $this->getTopDomainID();
			
			if(empty($topDomainID))
				throw new ExceptionSingleDomainRequired();
			else
				$returnDomainID = $topDomainID;
		}
		else{
			
			$domainID = current($selectedDomainIDsArr);
			
			// In case a person's authentication has changed (and they have selected a domain not in their browser URL), this won't show an error.
			// Instead it will just return the Domain ID that is in their browser URL.
			if(!$passiveAuthObj->CheckIfUserCanViewDomainID($domainID))
				$returnDomainID = self::getDomainID(self::getDomainKeyFromURL());
			else
				$returnDomainID = $domainID;
		}
		

		self::$_oneDomainIDalreadyReturned = $returnDomainID;
		return $returnDomainID;
	}
	
	
	
	// Returns an array of Domains that the user has selected to view.
	// It is possible that the user only has permission to view 1 single domain...
	// ... in which case this method will always return an array with only 1 element.
	// Authentication done within this method for the best security.
	// If the user is not logged in to the system... then it will just return the Domain ID out of the URL.
	function getSelectedDomainIDs(){
		
		// Cache results.  It can take a bit of work to authenticate all of the domains and this could be called many times in a script.
		if(!empty(self::$selectedDomainIDsCache))
			return self::$selectedDomainIDsCache;
		
		self::_initializeDomains();
		
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		
		// If the user is not logged in (or they are not a member in the system), just return Domain from the URL.
		if(!$passiveAuthObj->CheckIfLoggedIn() || !$passiveAuthObj->CheckIfBelongsToGroup("MEMBER"))
			return array(Domain::getDomainIDfromURL());
	
		$passiveAuthObj->EnsureLoggedIn();
		$UserID = $passiveAuthObj->GetUserID();
		
		$userDomainsArr = $passiveAuthObj->getUserDomainsKeys();
		
		if(empty($userDomainsArr)){
			$errMsg = "Error in method getSelectedDomainIDs. The User Does not have domains defined.";
			WebUtil::WebmasterError("DomainError: " . $errMsg . " UserID: " . $UserID);
			exit("Domain Error");
		}
		
		// Make sure that All of the available domains defined for this user actually exist.
		$allDomainKeysArr = array_keys(self::$_domainsArr);
		
		foreach($userDomainsArr as $thisUserDomKey){
			if(!in_array($thisUserDomKey, $allDomainKeysArr)){
			
				$errMsg = "Error in method getSelectedDomainIDs. User has defined a domain which doesn't exist.";
				WebUtil::WebmasterError($errMsg . " UserID: " . $UserID . " Domain: " . $thisUserDomKey);
				WebUtil::WebmasterError("DomainError: " . $errMsg . " UserID: " . $UserID);
				exit("Domain Error");
			}
		}
		
		
		// Default to the domain the User is currently viewing in their browser (if they don't have the cookie set yet).
		$defaultDomainID = self::getDomainID(self::getDomainKeyFromURL());
		
		// We may have called "setDomainIDs" during this script/thread execution and don't want to wait to get the values on the next page.
		if(empty(self::$_cookieDomainsIDs))
			$domainIDlistFromUserCookie = WebUtil::GetCookie("SelectedDomains", $defaultDomainID);
		else
			$domainIDlistFromUserCookie = self::$_cookieDomainsIDs;
	
		
		// The domain IDs in a string separated by pipe symbols.
		$userSelectedDomainIDsArr = explode("|", $domainIDlistFromUserCookie);
		
		if(empty($userSelectedDomainIDsArr)){
			WebUtil::WebmasterError("DomainError: " . "Error in method getSelectedDomainIDs, no values.");
			exit("Domain Error");
		}
/*
print "selected Domains: <br>";
var_dump($userSelectedDomainIDsArr);
print "<br><br>";

print "User Domains: <br>";
var_dump($userDomainsArr);
print "<br><br>";
*/		
		
		// Make sure that all Selected Domains are Valid
		foreach($userSelectedDomainIDsArr as $thisDomainIDSelected){

			if(!in_array(self::getDomainKeyFromID($thisDomainIDSelected), $userDomainsArr)){
				
				// Erase the cookie to keep a users's browser from getting stuck on an error message (maybe we removed the domain from the system).
				setcookie ("SelectedDomains", Domain::getDomainIDfromURL(), $this->_cookieTime);

				self::$_cookieDomainsIDs = NULL;
				
				$errMsg = "Error in method getSelectedDomainIDs. User Cookie has selected a domain which doesn't exist.";
				WebUtil::WebmasterError($errMsg . " UserID: " . $UserID . " Domain: " . self::getDomainKeyFromID($thisDomainIDSelected));
				
				WebUtil::WebmasterError("DomainError: " . "An Error occured.  Attempting to fix automatically.<br><br>OK, Try again.");
				throw new Exception("Domain Error");
			}
		}
		
		// Cache for next time.
		self::$selectedDomainIDsCache = $userSelectedDomainIDsArr;
		
		return $userSelectedDomainIDsArr;		
	}
	
	
	
	// Sets a cookie on the users browser for the domains he wants to view.
	// You can pass in 1 or many domains... but they must all be valid... and they are case sensitive.
	function setDomains($domainIDsArr){
		
		self::_initializeDomains();
		
		// Wipe our our Selected Domains cache since we are going to change what is selected.
		self::$selectedDomainIDsCache = array();
	
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		
		$passiveAuthObj->EnsureLoggedIn();
		$UserID = $passiveAuthObj->GetUserID();
		
		$domainIDsArr = array_unique($domainIDsArr);
	
		// Make sure that All of the available domains defined for this user actually exist.
		$allDomainIDs = array_keys(self::$_domainIDsArr);
		
		foreach($domainIDsArr as $thisUserDomID){
			if(!in_array($thisUserDomID, $allDomainIDs)){
				$errMsg = "Error in method setSelectedDomains. User is trying to record a domain which doesn't exist.";
				WebUtil::WebmasterError("DomainError: " . $errMsg . " UserID: " . $UserID . " Domain: " . self::getDomainKeyFromID($thisUserDomID));
				exit("Domain Error");
			}
		}
		
		$domainIDstr = implode("|", $domainIDsArr);
		
		self::$_cookieDomainsIDs = $domainIDstr;
	
		// In case the user only has one availabe... don't set a cookie on their machine or it would alert them to our technology.
		if(sizeof($passiveAuthObj->getUserDomainsIDs()) > 1 && $passiveAuthObj->CheckIfBelongsToGroup("MEMBER")){
			setcookie("SelectedDomains", $domainIDstr, $this->_cookieTime);
		}
	}
	
	
	
	
	


	
	
	// ---------------------  These Methods need to be static to avoid an infinite recursive problem. 
	// ---------------------  the Authenicate Class requires method from Domain, and the Domain contructor creates an Authenciation object.
	
	
	
	// Returns the filesystem path to the SandBox for the given domain.  Pass in NULL to use the domain out of the URL.
	// This is the directory were all of the HTML, Flash, images, and Javascripts will be for each domain.
	// PHP scripts are not allowed to be run within the sandbox.
	static function getDomainSandboxPath($domainKey = null){
	
		if(empty($domainKey))
			$domainKey = self::getDomainKeyFromURL();
		
		$domainKey = self::getDomainKey($domainKey);
		
		if(empty($domainKey)){
			WebUtil::WebmasterError("DomainError: " . "Error in method getDomainSandboxPath. The domain Key is invalid.");
			exit("Domain Error");
		}


		
		// For the live server... I am making the sandbox available under a different FTP account so that our new webmaster can push stuff live.
		if($domainKey == "Postcards.com" && !Constants::GetDevelopmentServer()){
			return "/home/pcftp/public_html";
		}
		if($domainKey == "Letterhead.com" && !Constants::GetDevelopmentServer()){
			return "/home/lettftp/public_html";
		}
		if($domainKey == "VinylBanners.com" && !Constants::GetDevelopmentServer()){
			return "/home/vinylftp/public_html";
		}
		if($domainKey == "RefrigeratorMagnets.com" && !Constants::GetDevelopmentServer()){
			return "/home/rfmagftp/public_html";
		}
		if($domainKey == "CarMagnets.com" && !Constants::GetDevelopmentServer()){
			return "/home/crmagftp/public_html";
		}
		if($domainKey == "PostcardPrinting.com" && !Constants::GetDevelopmentServer()){
			return "/home/pprntftp/public_html";
		}
		if($domainKey == "BusinessCards24.com" && !Constants::GetDevelopmentServer()){
			return "/home/biz24ftp/public_html";
		}
		if($domainKey == "ZEN_Development" && !Constants::GetDevelopmentServer()){
			return "/home/zenftp/public_html";
		}
		if($domainKey == "ThankYouCards.com" && !Constants::GetDevelopmentServer()){
			return "/home/thyoucds/public_html";
		}
		if($domainKey == "LuauInvitations.com" && !Constants::GetDevelopmentServer()){
			return "/home/luauinvt/public_html";
		}
		if($domainKey == "SunAmerica.com" && !Constants::GetDevelopmentServer()){
			return "/home/sunamrca/public_html";
		}
		if($domainKey == "BusinessHolidayCards.com" && !Constants::GetDevelopmentServer()){
			return "/home/bzholday/public_html";
		}
		if($domainKey == "DotGraphics.net" && !Constants::GetDevelopmentServer()){
			return "/home/dotgrphs/public_html";
		}
		if($domainKey == "MyGymPrinting.com" && !Constants::GetDevelopmentServer()){
			return "/home/mygym/public_html";
		}
		if($domainKey == "CalsonWagonlitPrinting.com" && !Constants::GetDevelopmentServer()){
			return "/home/carlson/public_html";
		}
		if($domainKey == "BusinessCards.co.uk" && !Constants::GetDevelopmentServer()){
			return "/home/bizcduk/public_html";
		}
		if($domainKey == "PowerBalancePrinting.com" && !Constants::GetDevelopmentServer()){
			return "/home/powerbal/public_html";
		}
		if($domainKey == "FlyerPrinting.com" && !Constants::GetDevelopmentServer()){
			return "/home/flyrprnt/public_html";
		}
		if($domainKey == "HolidayGreetingCards.com" && !Constants::GetDevelopmentServer()){
			return "/home/holdaygc/public_html";
		}
		if($domainKey == "ChristmasPhotoCards.com" && !Constants::GetDevelopmentServer()){
			return "/home/christpc/public_html";
		}
		if($domainKey == "AmazingGrass.com" && !Constants::GetDevelopmentServer()){
			return "/home/amazingg/public_html";
		}
		if($domainKey == "PosterPrinting.com" && !Constants::GetDevelopmentServer()){
			return "/home/posterpr/public_html";
		}
		if($domainKey == "HotLaptopSkin.com" && !Constants::GetDevelopmentServer()){
			return "/home/hotlapto/public_html";
		}
		if($domainKey == "BridalShowerInvitations.com" && !Constants::GetDevelopmentServer()){
			return "/home/bridalsh/public_html";
		}
		if($domainKey == "HouseOfBluesPrinting.com" && !Constants::GetDevelopmentServer()){
			return "/home/houseofb/public_html";
		}
		if($domainKey == "NNAServices.org" && !Constants::GetDevelopmentServer()){
			return "/home/nnaservi/public_html";
		}
		if($domainKey == "ShakeysServices.com" && !Constants::GetDevelopmentServer()){
			return "/home/shakeyss/public_html";
		}
		if($domainKey == "YourOnlinePrintingCompany.com" && !Constants::GetDevelopmentServer()){
			return "/home/youronln/public_html";
		}
		
		
		// We are just going to use the PME sandbox for Marketing Engine
		if($domainKey == "MarketingEngine.biz" || $domainKey == "DotCopyShop.com")
			return Constants::GetAccountBase() . "/PrintsMadeEasy.com";
		else
			return Constants::GetAccountBase() . "/" . $domainKey;
	}
	
	
	
	// Returns the domain that the user is currently looking at.
	static function getDomainKeyFromURL(){
		
		self::_initializeDomains();
		
		if(!array_key_exists("SERVER_NAME", $_SERVER)){
			$errMsg = "Error fetching Domain from the URL.";
			WebUtil::WebmasterError($errMsg . " Domain: ??? \$_SERVER global is not populated with info?");
			exit("Domain Error");
		}
		
		$domainName = self::getDomainKey($_SERVER['SERVER_NAME']);
		
		if(empty($domainName)){
			$errMsg = "Error in method getDomainFromURL.  The domain is not valid.";
			WebUtil::WebmasterError($errMsg . " Domain: " . $_SERVER['SERVER_NAME']);
			exit("Domain Error");
		}
		
		return $domainName;
	}
	
	static function getDomainIDfromURL(){
		return self::getDomainID(self::getDomainKeyFromURL());
	}
	
	

	// Returns True or False whether the PHP script being run on the Front end.
	// We can tell by looking at the the prefix "ad_home.php".
	// That doesn't mean that we religiously name all adminstrative pages like that... but large majority.
	static function isUrlForTheFrontEnd(){
		
		if(isset($_SERVER['PHP_SELF']) && (preg_match("/\/ad_/", $_SERVER['PHP_SELF']) || preg_match("/\/clipboard/", $_SERVER['PHP_SELF'])))
			return false;
		else
			return true;
		
	}
	
	
	
	// Pass in a string representing a domain name.  It will return the domain name Key.
	// Will return NULL if the domain name has not been defined.
	// The domain passed in here is not case sensitive.
	static function getDomainKey($domainStr){
	
		self::_initializeDomains();
		
		$domainStr = strtolower(trim($domainStr));
	
		$domainKeys = array_keys(self::$_domainsArr);
		
		foreach($domainKeys as $thisDomainKey){
			
			if($domainStr == strtolower($thisDomainKey))
				return $thisDomainKey;
			
			foreach(self::$_domainsArr[$thisDomainKey] as $thisDomainAlias){
			
				if($domainStr == strtolower($thisDomainAlias))
					return $thisDomainKey;
			}
		}
		
		return null;
	}
	
	// Returns an integer ID representing the Domain
	// By default it will return the ID of the domain being viewed.
	// It will exit with an error if the Domain does not exist.
	static function getDomainID($domainStr = NULL){
		
		self::_initializeDomains();

		if(empty($domainStr))
			$domainStr = self::getDomainKeyFromURL();
		
		$domainKey = self::getDomainKey($domainStr);
		
		if(empty($domainKey)){
			WebUtil::WebmasterError("DomainError: " . "Error in method getDomainID. Domain key does not exist.");
			exit("Domain Error");
		}

		
		foreach(self::$_domainIDsArr as $thisID => $thisDomainKey){
			
			if($thisDomainKey == $domainKey)
				return $thisID;
		}
		
		WebUtil::WebmasterError("DomainError: " . "Error in method getDomainID.  The Domain key has not been defined: $domainStr");
		exit("Domain Error");			
	}
	
	static function getDomainKeyFromID($domainID){
		
		self::_initializeDomains();
		
		if(!array_key_exists($domainID, self::$_domainIDsArr)){
			WebUtil::WebmasterError("DomainError: " . "Error in method getDomainKeyFromID");
			throw new Exception("DomainError: " . "Error in method getDomainKeyFromID");
		}
			
		return self::$_domainIDsArr[$domainID];
	}
	
	static function checkIfDomainIDexists($domainID){
		
		self::_initializeDomains();
		
		if(!array_key_exists($domainID, self::$_domainIDsArr))
			return false;
		else
			return true;
	}
	
	static function getAllDomainIDs(){
		
		self::_initializeDomains();
		
		return array_keys(self::$_domainIDsArr);
	}
	
	// If the Key is "PrintsMadeEasy.com" you may want users to see a link witha  sub-domain like "www.PrintsMadeEasy.com" in links... or change the case to "www.printsmadeeasy.com"
	static function getWebsiteURLforDomainID($domainID){
		
		if(!self::checkIfDomainIDexists($domainID)){
			WebUtil::WebmasterError("DomainError: " . "Error in method getWebsiteURLforDomain");
			exit("Domain Error");	
		}
			
			
		// On the Development System all of the Domains are going to share the same path in the localhost.
		// Modify the "initializeDomains" method in this object to control which "SandBox" is picked up by the domain.
		// So you can only work on one Domain/Sandbox at time.
		if(Constants::GetDevelopmentServer())
			return "localhost/dot";
	
			
		$domainKey = self::getDomainKeyFromID($domainID);
		
		if($domainKey == "PrintsMadeEasy.com") {
			return "www.PrintsMadeEasy.com";
		}
		else if($domainKey == "Postcards.com"){
			return "www.Postcards.com";
		}
		else if($domainKey == "Letterhead.com"){
			return "www.Letterhead.com";
		}
		else if($domainKey == "VinylBanners.com"){
			return "www.vinylbanners.com";
		}
		else if($domainKey == "DotGraphics.net"){
			return "74.53.2.163";
		}
		else if($domainKey == "RefrigeratorMagnets.com"){
			return "www.refrigeratormagnets.com";
		}
		else if($domainKey == "CarMagnets.com"){
			return "CarMagnets.com";
		}
		else if($domainKey == "PostcardPrinting.com"){
			return "www.PostcardPrinting.com";
		}
		else if($domainKey == "BusinessCards24.com"){
			return "BusinessCards24.com";
		}
		else if($domainKey == "ZEN_Development"){
			return "74.53.59.116";
		}
		else if($domainKey == "ThankYouCards.com"){
			return "ThankYouCards.com";
		}
		else if($domainKey == "LuauInvitations.com"){
			return "LuauInvitations.com";
		}
		else if($domainKey == "SunAmerica.com"){
			return "74.53.59.120";
		}
		else if($domainKey == "MyGymPrinting.com"){
			return "74.53.0.27";
		}
		else if($domainKey == "CalsonWagonlitPrinting.com"){
			return "74.53.0.28";
		}
		else if($domainKey == "PowerBalancePrinting.com"){
			return "74.53.2.160";
		}
		else if($domainKey == "Bang4BuckPrinting.com"){
			return "74.53.59.120";
		}
		else if($domainKey == "DotCopyShop.com"){
			return "74.53.59.120";
		}
		else if($domainKey == "BusinessHolidayCards.com"){
			return "BusinessHolidayCards.com";
		}
		else if($domainKey == "MarketingEngine.biz"){
			return "74.53.59.120";
		}
		else if($domainKey == "BusinessCards.co.uk"){
			return "BusinessCards.co.uk";
		}
		else if($domainKey == "FlyerPrinting.com"){
			return "FlyerPrinting.com";
		}
		else if($domainKey == "HolidayGreetingCards.com"){
			return "HolidayGreetingCards.com";
		}
		else if($domainKey == "ChristmasPhotoCards.com"){
			return "ChristmasPhotoCards.com";
		}
		else if($domainKey == "AmazingGrass.com"){
			return "printing.amazinggrass.com";
		}
		else if($domainKey == "PosterPrinting.com"){
			return "PosterPrinting.com";
		}
		else if($domainKey == "HotLaptopSkin.com"){
			return "HotLaptopSkin.com";
		}
		else if($domainKey == "BridalShowerInvitations.com"){
			return "BridalShowerInvitations.com";
		}
		else if($domainKey == "HouseOfBluesPrinting.com"){
			return "houseofbluesprinting.com";
		}
		else if($domainKey == "NNAServices.org"){
			return "NNAServices.org";
		}
		else if($domainKey == "ShakeysServices.com"){
			return "ShakeysServices.com";
		}
		else if($domainKey == "YourOnlinePrintingCompany.com"){
			return "YourOnlinePrintingCompany.com";
		}
		else{
			WebUtil::WebmasterError("Error in method getWebsiteURLforDomainID: " . $domainID);
			exit("Domain Error");
		}
	}
	
	
	
	static function getIpAddressForDomainID($domainID){
		
		// Returns IP Address.
		
	}
	
	// Sometimes you may want to use PME instead of PrintsMadeEasy.com
	static function getAbreviatedNameForDomainID($domainID){
		
		if(!self::checkIfDomainIDexists($domainID)){
			WebUtil::WebmasterError("DomainError: " . "Error in method getAbreviatedNameForDomainID");
			exit("Domain Error");	
		}
			
		$domainKey = self::getDomainKeyFromID($domainID);
		
		if($domainKey == "PrintsMadeEasy.com"){
			return "PME";
		}
		else if($domainKey == "DotCopyShop.com"){
			return "DotCopy";
		}
		else{
			return $domainKey;
		}
	}
	
	static function isDomainRunThroughProxy($domainID){
		
		if(!self::checkIfDomainIDexists($domainID)){
			WebUtil::WebmasterError("DomainError: " . "Error in method isDomainRunThroughProxy");
			exit("Domain Error");	
		}
		
		$domainKey = self::getDomainKeyFromID($domainID);
		
		$localHostDomainsArr = array();
		$localHostDomainsArr[] = "PrintsMadeEasy.com";
		$localHostDomainsArr[] = "DotCopyShop.com";
		$localHostDomainsArr[] = "Postcards.com";
		$localHostDomainsArr[] = "MarketingEngine.biz";
		$localHostDomainsArr[] = "MerchantMadeEasy.com";
		$localHostDomainsArr[] = "ZEN_Development";
		
		if(in_array($domainKey, $localHostDomainsArr))
			return false;
		else
			return true;
	}
	
	static function getFullCompanyNameForDomainID($domainID){
		
		if(!self::checkIfDomainIDexists($domainID)){
			WebUtil::WebmasterError("DomainError: " . "Error in method getFullCompanyNameForDomainID");
			exit("Domain Error");	
		}

		$domainKey = self::getDomainKeyFromID($domainID);
		
		if($domainKey == "PrintsMadeEasy.com"){
			return "Prints Made Easy, Inc.";
		}
		else{
			return $domainKey;
		}
	}
	
	
	static function getCompanyLinkDescription($domainID){
		
		if(!self::checkIfDomainIDexists($domainID)){
			WebUtil::WebmasterError("DomainError: " . "Error in method getCompanyLinkDescription");
			exit("Domain Error");	
		}

		$domainKey = self::getDomainKeyFromID($domainID);
		
		if($domainKey == "PrintsMadeEasy.com"){
			return "Business Cards and More at www.PrintsMadeEasy.com";
		}
		else if($domainKey == "Postcards.com"){
			return "Postcards and More at www.Postcards.com";
		}
		else if($domainKey == "Letterhead.com"){
			return "Quality Stationery at www.Letterhead.com";
		}
		else{
			return "Great Products at " . $domainKey;
		}
	}
	
	
	// Returns TRUE if we are going to charge the Customer for Copyright permissions on their artwork.
	static function getCopywriteChargeFlagForDomain($domainID){
		
		if(!self::checkIfDomainIDexists($domainID)){
			WebUtil::WebmasterError("DomainError: " . "Error in method getCopywriteChargeFlagForDomain");
			exit("Domain Error");
		}
			
		$domainKey = self::getDomainKeyFromID($domainID);
		
		if(in_array($domainKey, array("PrintsMadeEasy.com", "Postcards.com", "VinylBanners.com", "LuauInvitations.com", "BusinessCards24.com"))){
			return true;
		}
		else{
			return false;
		}
	}
	
	static function getDefaultCopyrightFlagForRegistration($domainID){
		
		if(!self::checkIfDomainIDexists($domainID)){
			WebUtil::WebmasterError("DomainError: " . "Error in method getDefaultCopyrightFlagForRegistration");
			exit("Domain Error");
		}
			
		$domainKey = self::getDomainKeyFromID($domainID);
		
		if(in_array($domainKey, array("PrintsMadeEasy.com", "Postcards.com", "VinylBanners.com", "LuauInvitations.com", "BusinessCards24.com"))){
			return true;
		}
		else{
			return true;
		}
	}
	
	static function getDefaultLoyaltyFlagForRegistration($domainID){
		
		if(!self::checkIfDomainIDexists($domainID)){
			WebUtil::WebmasterError("DomainError: " . "Error in method getDefaultCopyrightFlagForRegistration");
			exit("Domain Error");
		}
		
		if(!self::getLoyaltyDiscountFlag($domainID))
			return false;
			
		$domainKey = self::getDomainKeyFromID($domainID);
		
		if(in_array($domainKey, array("BusinessCards24.com"))){
			return true;
		}
		else{
			return true;
		}
	}
	
	
	
	static function getDefaultNewsletterFlagForRegistration($domainID){
		
		if(!self::checkIfDomainIDexists($domainID)){
			WebUtil::WebmasterError("DomainError: " . "Error in method getDefaultNewsletterFlagForRegistration");
			exit("Domain Error");
		}
			
		$domainKey = self::getDomainKeyFromID($domainID);
		
		if(in_array($domainKey, array("PrintsMadeEasy.com", "Postcards.com", "BusinessCards24.com", "VinylBanners.com", "LuauInvitations.com"))){
			return false;
		}
		else{
			return true;
		}
	}
	
	static function getGalleryUrl($domainID){
		if(!self::checkIfDomainIDexists($domainID)){
			WebUtil::WebmasterError("DomainError: " . "Error in method getDefaultNewsletterFlagForRegistration");
			exit("Domain Error");
		}
			$domainKey = self::getDomainKeyFromID($domainID);
		
		if(in_array($domainKey, array("BusinessCards24.com"))){
			return "gallery.cgi";
		}
		else if(in_array($domainKey, array("PostcardPrinting.com"))){
			return "artworkGallery.cgi";
		}
		else{
			return "templates.php";
		}
	}
	
	static function getLoyaltyDiscountFlag($domainID){
		
		if(!self::checkIfDomainIDexists($domainID)){
			WebUtil::WebmasterError("DomainError: " . "Error in method getLoyaltyDiscountFlag");
			exit("Domain Error");
		}
			
		$domainKey = self::getDomainKeyFromID($domainID);
		
		if(in_array($domainKey, array("BusinessCards24.com")))
			return true;

		return false;
	}
	
	// Returns a string explaining why the shipping was discounted (based upon the Domain)
	static function getLoyaltyShippingDiscountExplanation($domainID){
		
		if(!self::checkIfDomainIDexists($domainID)){
			WebUtil::WebmasterError("DomainError: " . "Error in method getLoyaltyShippingDiscountExplanation");
			exit("Domain Error");
		}
		
		$domainKey = self::getDomainKeyFromID($domainID);
		
		if(in_array($domainKey, array("BusinessCards24.com"))){
			return "Free Due to Super Shipping Membership";
		}
		return "";
		
	}

}









?>