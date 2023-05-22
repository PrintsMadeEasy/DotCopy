<?php


class DomainGateways {
	

	// We need to have 2 Merchants available in case we are going to switch.
	// Refunds may go back one the 2nd gateway account... while new merchant charges go through the 1st gateway account.
	public $paymentGatewayLogin1;
	public $paymentGatewayLogin2;
	public $paymentGatewayLogin3;
	public $paymentGatewayLogin4;
	
	public $paymentGatewayPassword1;
	public $paymentGatewayPassword2;
	public $paymentGatewayPassword3;
	public $paymentGatewayPassword4;
	
	// Transactions IDs are incremental. Set the transaction ID in which all subsequent transactions start passing through to Payment Gateway #1
	// Refunds and Captures are both done against an original Transaction ID
	// LESS THAN or EQUAL TO to Payment Gateway 2
	public $paymentGateway2_MaxTransactionID;
	public $paymentGateway3_MaxTransactionID;
	public $paymentGateway4_MaxTransactionID;

	// Login details for the API itself
	public $paypalAPI_LoginID;
	public $paypalAPI_LoginPassword;
	public $paypalAPI_LoginSignature;
	
	
	public function __construct($domainID){
		
		if(!Domain::checkIfDomainIDexists($domainID))
			throw new Exception("Error in Domain Gateway Configuration. The Domain ID does not exist.");
			
		$domainKey = Domain::getDomainKeyFromID($domainID);
		
		// Intialize default values.  If a previous Merchant 2,3, or 4 is left null, then the preceding merchant will be used.   There must be a pimary merchant (1).
		$this->paymentGateway2_MaxTransactionID = 0;
		$this->paymentGatewayLogin2 = null;
		$this->paymentGatewayPassword2 = null;
		$this->paymentGateway3_MaxTransactionID = 0;
		$this->paymentGatewayLogin3 = null;
		$this->paymentGatewayPassword3 = null;
		$this->paymentGateway4_MaxTransactionID = 0;
		$this->paymentGatewayLogin4 = null;
		$this->paymentGatewayPassword4 = null;
		
		if($domainKey == "PrintsMadeEasy.com" || $domainKey == "LuauInvitations.com"){
			
			// Current Merchant
			$this->paymentGatewayLogin1 = "76fcRk5AV5A";
			$this->paymentGatewayPassword1 = "79KDf6535f4Cp788";
			
			// Previous Merchant
			$this->paymentGateway2_MaxTransactionID = 2665167231;
			$this->paymentGatewayLogin2 = "3hN2J2fu";
			$this->paymentGatewayPassword2 = "3Pb4t6t2UhF2yc46";
			
			// Paypal
			$this->paypalAPI_LoginID = "Brian_api1.PrintsMadeEasy.com";
			$this->paypalAPI_LoginPassword = "76ATNLPFFNTZPX3F";
			$this->paypalAPI_LoginSignature = "AGlB0Gdq4tIgHJf9HLw-4rIdU9KwARtmWx0OV1n7NdfDyGkYgrhlQ.aC";
		}
		else if($domainKey == "RefrigeratorMagnets.com"){
			
			// Current Merchant
			$this->paymentGatewayLogin1 = "7PsU68kEV7J";
			$this->paymentGatewayPassword1 = "7w6WUeD7qS6J85yy";
			
			// Previous Merchant
			$this->paymentGateway2_MaxTransactionID = 3034679533;
			$this->paymentGatewayLogin2 = "76fcRk5AV5A";
			$this->paymentGatewayPassword2 = "79KDf6535f4Cp788";
			
			// Paypal
			$this->paypalAPI_LoginID = "";
			$this->paypalAPI_LoginPassword = "";
			$this->paypalAPI_LoginSignature = "";
		}
		else if($domainKey == "PostcardPrinting.com"){
			
			// Current Merchant
			$this->paymentGatewayLogin1 = "3T6zT88cB";
			$this->paymentGatewayPassword1 = "3yk87LZj72C8Qb7v";
			
			// Previous Merchant
			$this->paymentGateway2_MaxTransactionID = 3320977494;
			$this->paymentGatewayLogin2 = "6qEQjW983";
			$this->paymentGatewayPassword2 = "385ALPEsuhz5R47m";
			
			// Paypal
			$this->paypalAPI_LoginID = "";
			$this->paypalAPI_LoginPassword = "";
			$this->paypalAPI_LoginSignature = "";
		}
		else if($domainKey == "BusinessCards24.com" || $domainKey == "BusinessCards.co.uk"){
			
			// Current Merchant
			$this->paymentGatewayLogin1 = "8jjb6A7LdR4a";
			$this->paymentGatewayPassword1 = "8Fapjaj43aQ75B7P";
			
			// Previous Merchant
			$this->paymentGateway2_MaxTransactionID = 3436398689;
			$this->paymentGatewayLogin2 = "76fcRk5AV5A";		// PrintsMadeEasy.com was only used for a couple of days.  Then we are switching back to the original B24 merchant.
			$this->paymentGatewayPassword2 = "79KDf6535f4Cp788";
			
			// Previous Merchant
			$this->paymentGateway3_MaxTransactionID = 3432295731;
			$this->paymentGatewayLogin3 = "8jjb6A7LdR4a";
			$this->paymentGatewayPassword3 = "8Fapjaj43aQ75B7P";
			
			// Paypal
			$this->paypalAPI_LoginID = "";
			$this->paypalAPI_LoginPassword = "";
			$this->paypalAPI_LoginSignature = "";
		}
		else if($domainKey == "Postcards.com" || $domainKey == "SunAmerica.com"){
			
			// Current Merchant
			$this->paymentGatewayLogin1 = "6qEQjW983";
			$this->paymentGatewayPassword1 = "385ALPEsuhz5R47m";
			
			// Previous Merchant
			$this->paymentGateway2_MaxTransactionID = 0;
			$this->paymentGatewayLogin2 = "6qEQjW983";
			$this->paymentGatewayPassword2 = "385ALPEsuhz5R47m";
			
			// Paypal
			$this->paypalAPI_LoginID = "";
			$this->paypalAPI_LoginPassword = "";
			$this->paypalAPI_LoginSignature = "";
		}
		else if($domainKey == "Letterhead.com"){
			
			// Current Merchant
			$this->paymentGatewayLogin1 = "4YbC43ZsTE";
			$this->paymentGatewayPassword1 = "488Zas8Kfe9BGR4x";
			
			// Previous Merchant
			$this->paymentGateway2_MaxTransactionID = 0;
			$this->paymentGatewayLogin2 = "4YbC43ZsTE";
			$this->paymentGatewayPassword2 = "488Zas8Kfe9BGR4x";
			
			// Paypal
			$this->paypalAPI_LoginID = "";
			$this->paypalAPI_LoginPassword = "";
			$this->paypalAPI_LoginSignature = "";
		}
		else if($domainKey == "VinylBanners.com"){
			
			// Current Merchant
			$this->paymentGatewayLogin1 = "8SSa35wK";
			$this->paymentGatewayPassword1 = "8yK37U2WukJ7qm59";
			
			// Previous Merchant
			$this->paymentGateway2_MaxTransactionID = 0;
			$this->paymentGatewayLogin2 = "8SSa35wK";
			$this->paymentGatewayPassword2 = "8yK37U2WukJ7qm59";
			
			// Paypal
			$this->paypalAPI_LoginID = "";
			$this->paypalAPI_LoginPassword = "";
			$this->paypalAPI_LoginSignature = "";
		}
		else if($domainKey == "ThankYouCards.com"){
			
			// Current Merchant
			$this->paymentGatewayLogin1 = "5Uj9E3HaS3";
			$this->paymentGatewayPassword1 = "533328vzHGKs9J9L";
			
			// Previous Merchant
			$this->paymentGateway2_MaxTransactionID = 0;
			$this->paymentGatewayLogin2 = "5Uj9E3HaS3";
			$this->paymentGatewayPassword2 = "533328vzHGKs9J9L";
			
			// Paypal
			$this->paypalAPI_LoginID = "";
			$this->paypalAPI_LoginPassword = "";
			$this->paypalAPI_LoginSignature = "";
		}
		else{
			// Current Merchant
			$this->paymentGatewayLogin1 = "NotConfiguredYet";
			$this->paymentGatewayPassword1 = "NotConfiguredYet";
			
			// Previous Merchant
			$this->paymentGateway2_MaxTransactionID = 0;
			$this->paymentGatewayLogin2 = "NotConfiguredYet";
			$this->paymentGatewayPassword2 = "NotConfiguredYet";
			
			// Paypal
			$this->paypalAPI_LoginID = "NotConfiguredYet";
			$this->paypalAPI_LoginPassword = "NotConfiguredYet";
			$this->paypalAPI_LoginSignature = "NotConfiguredYet";
		}
		
	}
	
}

