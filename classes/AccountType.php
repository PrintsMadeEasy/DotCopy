<?


class AccountType {



	private $_ShippingFromAddress = array();
	private $_InvoiceAddress = array();
	private $_InvoiceMessage = array();
	private $_InvoiceLogoBinData;
	private $_InvoiceDisplayAmounts;
	private $_AccountUserID;
	
	private $_dbCmd;
	private $_ResellerObj;



	###-- Constructor --###
	function AccountType($CustomerID, &$dbCmd){


		if(!UserControl::CheckIfUserIDexists($dbCmd, $CustomerID))
			throw new Exception("Error in AccountType Constructor.");
		
		$this->_AccountUserID = $CustomerID;
		
		$this->_dbCmd = $dbCmd;
		
		
		$domainIDofCustomer = UserControl::getDomainIDofUser($CustomerID);
		$domainAddressConfigObj = new DomainAddresses($domainIDofCustomer);
		$domainLogoObj = new DomainLogos($domainIDofCustomer);
		
		$returnShippingAddressObj = $domainAddressConfigObj->getReturnShippingAddressObj();
		$billingAddressObj = $domainAddressConfigObj->getBillingDepartmentAddressObj();
		
		$websiteURL = Domain::getWebsiteURLforDomainID($domainIDofCustomer);

		//Set up all of the default values in the contructor... We can override the values based upon UserID
		$this->_ShippingFromAddress = array(
					"Company"=>$returnShippingAddressObj->getCompanyName(),
					"Attention"=>$returnShippingAddressObj->getAttention(),
					"Address"=>$returnShippingAddressObj->getAddressOne(),
					"AddressTwo"=>$returnShippingAddressObj->getAddressTwo(),
					"City"=>$returnShippingAddressObj->getCity(),
					"State"=>$returnShippingAddressObj->getState(),
					"Zip"=>$returnShippingAddressObj->getZipCode(),
					"Country"=>$returnShippingAddressObj->getCountryCode(),
					"Phone"=>$returnShippingAddressObj->getPhoneNumber(),
					"UPS_AccountNumber"=>$domainAddressConfigObj->upsAccountNumber,
					"TaxID"=>$domainAddressConfigObj->taxIDnumber,
					"TaxIDtype"=>$domainAddressConfigObj->taxIDType,
					"ResidentialIndicator"=>"N"
					);

		$this->_InvoiceAddress = array(
					"Line1"=>$billingAddressObj->getCompanyName(),
					"Line2"=>$billingAddressObj->getAddressOne() . " " . $billingAddressObj->getAddressTwo(),
					"Line3"=>$billingAddressObj->getCity() . ", " . $billingAddressObj->getState(),
					"Line4"=>$billingAddressObj->getZipCode()
					);

		$this->_InvoiceMessage = array(
					"Line1"=>"Thank you for your business!",
					"Line2"=>"If you have any questions about your order please visit",
					"Line3"=>"Customer Service at $websiteURL     " . $billingAddressObj->getPhoneNumber()
					);

		
		

		
		$this->_ResellerObj = new Reseller($this->_dbCmd);
		
		// Find out if the Customer is a reseller.  
		// If they are...  then overwrite the default details
		if($this->_ResellerObj->LoadReseller($CustomerID)){
		
			$this->_AccountUserID = $CustomerID;
			
			// Don't write down the reseller's Grand Total because they may not want their customers to know about it.
			$this->_InvoiceDisplayAmounts = false;
			
			
			//shipping label address
			$this->_ShippingFromAddress["Company"] = $this->_ResellerObj->getResellerCompany();
			$this->_ShippingFromAddress["Attention"] = $this->_ResellerObj->getResellerAttention();
			$this->_ShippingFromAddress["Address"] = $this->_ResellerObj->getResellerAddress();
			$this->_ShippingFromAddress["AddressTwo"] = $this->_ResellerObj->getResellerAddressTwo();
			$this->_ShippingFromAddress["City"] = $this->_ResellerObj->getResellerCity();
			$this->_ShippingFromAddress["State"] = $this->_ResellerObj->getResellerState();
			$this->_ShippingFromAddress["Zip"] = $this->_ResellerObj->getResellerZip();
			$this->_ShippingFromAddress["Country"] = $this->_ResellerObj->getResellerCountry();
			$this->_ShippingFromAddress["Phone"] = $this->_ResellerObj->getResellerPhone();

			//invoice address
			$this->_InvoiceAddress["Line1"] = $this->_ResellerObj->getResellerCompany();
			$this->_InvoiceAddress["Line2"] = $this->_ResellerObj->getResellerAddress() . " " . $this->_ResellerObj->getResellerAddressTwo();
			$this->_InvoiceAddress["Line3"] = $this->_ResellerObj->getResellerCity() . ", " . $this->_ResellerObj->getResellerState();
			$this->_InvoiceAddress["Line4"] = $this->_ResellerObj->getResellerZip();

			//invoice message
			$this->_InvoiceMessage["Line1"] = $this->_ResellerObj->getInvoiceMessage1();
			$this->_InvoiceMessage["Line2"] = $this->_ResellerObj->getInvoiceMessage2();
			$this->_InvoiceMessage["Line3"] = $this->_ResellerObj->getInvoiceMessage3();
			
			$this->_InvoiceLogoBinData = $this->_ResellerObj->getLogoImage();
		
		}
		else{
		
			//Wheter or not we should write the customer totals on the invoice
			$this->_InvoiceDisplayAmounts = true;
			
			
			// Open our default logo off of the disk.
			$InvoiceLogoPath = Constants::GetInvoiceLogoPath() . "/$domainLogoObj->printQualtityMediumJPG";

			if(!file_exists($InvoiceLogoPath))
				throw new Exception("Error, Logo could not be found for the invoice ... " . $InvoiceLogoPath);
			
			$fd = fopen ($InvoiceLogoPath, "r");
			$this->_InvoiceLogoBinData = fread ($fd, filesize ($InvoiceLogoPath));
			fclose ($fd);
		}
		



	}



	#########   Public Functions - below #################


	function GetAccountUserID(){
		return $this->_AccountUserID;
	}

	function GetShippingFromAdress(){
		return $this->_ShippingFromAddress;
	}

	function GetInvoiceAddress(){
			return $this->_InvoiceAddress;
	}

	function GetInvoiceMessage(){
			return $this->_InvoiceMessage;
	}
	function GetInvoiceLogoBinaryData(){
			return $this->_InvoiceLogoBinData;
	}
	
	function DisplayInvoiceAmounts(){
		return $this->_InvoiceDisplayAmounts;
	}


	// If the customer is a reseller, we will look in their Saved Projects for a re-order design (matching the same product)
	// If the user is not a reseller... then use the default $this->AccountUserID  (which is currently My personal Account);
	// If no reorder card is found for either a reseller, or general customer then the method will return an Artwork Object with no layers
	function &GetArtworkObjectForReorder($productIDforReorderCard){

		$productObj = new Product($this->_dbCmd, $productIDforReorderCard);


		// First find out if the Account UserID is a Reseller.  If so, look for a "ReOrderCard" in his saved projects.
		if(UserControl::CheckIfUserIsReseller($this->_dbCmd, $this->_AccountUserID)){
			// Look for an artwork matching the Project Note "ReorderCard" in the Saved projects.
			// If we can't find a match then we need to make a blank white PDF document in its place
			$this->_dbCmd->Query("SELECT ArtworkFile FROM projectssaved WHERE UserID=". $this->_AccountUserID . " AND ProductID=$productIDforReorderCard AND Notes LIKE 'ReorderCard'");
			if($this->_dbCmd->GetNumRows() > 0){	
				$ArtworkFile = $this->_dbCmd->GetValue();
				$ArtInfoObj = new ArtworkInformation($ArtworkFile);
			}
			else{

				// Otherwise we need to get the default blank template based upon the product ID... and just make sure that it is completely white/blank.
				$ArtInfoObj = new ArtworkInformation($productObj->getDefaultProductArtwork());	

				// This will wipe clean all of the layers... that will leave us with a blank white template
				$ArtInfoObj->SideItemsArray[0]->layers = array();
			}
		}
		else{

			$savedProjectIDreorder = $productObj->getSavedProjectIDofReorderCard();
			
			// If a Re-order card hasn't been identified for this Product in the "UserID of Artwork Setup"... then create a blank white document based upon the default artwork.
			if(empty($savedProjectIDreorder)){
				
				$ArtInfoObj = new ArtworkInformation($productObj->getDefaultProductArtwork());
				
				// This will wipe clean all of the layers... that will leave us with a blank white template
				$ArtInfoObj->SideItemsArray[0]->layers = array();
			
			}
			else{
			
				$projectSavedObj = ProjectSaved::getObjectByProjectID($this->_dbCmd, $savedProjectIDreorder);
			
				$ArtInfoObj = new ArtworkInformation($projectSavedObj->getArtworkFile());
			}	
		}


		return $ArtInfoObj;
	}

}






?>
