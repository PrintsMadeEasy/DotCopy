<?


// Analyzes Product Options, their selections, as well selected quantities against the Product Information
// Stores the selections in this Object and makes sure selections are valid (if not, goes to defaults)
// Because this object is recording selections by the user, it is able to figure out the subtotal, weight, etc. based upon attributes in the Selected Option Choices and Quantity Ordered.
class ProjectInfo {

	private $_optionsSetFlag;

	private $_productID;
	
	private $_productObj; // A reference to a Product Option
	
	private $_quantitySet;
	
	private $_dbCmd;

	// They key to the array is the Product Options... like "Card Stock"
	// The value to the array is the selected choice... like "Glossy"
	private $_selectedOptionsArr = array();


	##--------------   Constructor  ------------------##
	function __construct(DbCmd $dbCmd, $productID, $authenticateProductforDomain = true){

		if(!preg_match("/^\d+$/", $productID))
			throw new Exception("Illegal product format in contstructor ProjectInfo");

		$this->_dbCmd = $dbCmd;

		$this->_quantitySet = null;
		$this->_optionsSetFlag = false;
		$this->_productID = $productID;
		$this->_productObj = Product::getProductObj($this->_dbCmd, $this->_productID, $authenticateProductforDomain);
		$this->_setDefaultOptions();
	}
	
	



	// This is a static method and it may be more efficient to get a Hash of product Options this way with raw info from the DB
	// Pass in the OptionsDescription Field from a Project Table
	// like "Card Stock - Glossy, Style - DoubleSided"
	// It will retrn a Hash with the Key as the Option Name and the Value is the Selected Choice
	static function getProductOptionHashFromDelimitedString($OptionDescription){

		// Every Option/choice combo is separated by a comma
		$OptionsArr = split(", ", $OptionDescription);

		$retArr = array();
		foreach($OptionsArr as $choice_option){

			// See if we find a space, dash, string.  That indicates a Option/Choice
			if(preg_match("/\s-\s/", $choice_option)){

				// Then divide the part in 2.. First is the Choice Name, Second part is the option
				// There could be a Dash within the Choice Name... so that is why we set the limit to 2 on the split.
				$OptionChoiceSplitArr = split(" - ", $choice_option, 2);

				// Put the parts into the return array
				$retArr[$OptionChoiceSplitArr[0]] = $OptionChoiceSplitArr[1];
			}
		}

		return $retArr;
	}

	
	// Returns a JSON format of the Product ID and Product Options for Javascript. 
	// For example:  "(Cardstock - Glossy, Style - Double - Sided)" It will break those into pieces and format them into Javascript code to interpret within multi-dimensional arrays 
	static function GetProductOptionsJavascriptArray($productID, $OptionsDescription, $checkBoxCounter){
	
	
		// Will contain every Option/choice separated by pipe symbols  EX: "CardStock|Glossy|Style|DoubleSided"
		$CommaDeliminatedOptionChoices_JS = "";
	
		// Split the string "(Card Stock - Glossy, Style - Double Sided)" into an associate hash using text scraping techniques.
		$prodOptionsHash = ProjectInfo::getProductOptionHashFromDelimitedString($OptionsDescription);
	
		foreach($prodOptionsHash as $ChoiceName => $OptionName){
			$CommaDeliminatedOptionChoices_JS .= "'" . $ChoiceName . ">" . $OptionName . "',";
		}
		if(!empty($CommaDeliminatedOptionChoices_JS)){
			$CommaDeliminatedOptionChoices_JS = substr($CommaDeliminatedOptionChoices_JS, 0, -1);  //Get rid of the trailing comma
		}
	
		// This creates a 3d array in javascript.  The second level is associative 
		return "Prod_Opt_Arr[" . $checkBoxCounter . "] = Array('" . $productID . "', Array(" . $CommaDeliminatedOptionChoices_JS . "));\n";
	
	}


	// Pass in a Project Info Object by reference and it will set options coming from the URL. 
	// Project Info Object is not returned because it is modified by rerference
	static function setProjectOptionsFromURL(DbCmd $dbCmd, ProjectInfo $projectInfoObj){
	
		$productObj = $projectInfoObj->getProductObj();
		
		$quantityParam = WebUtil::GetInput("quantity", FILTER_SANITIZE_INT);
		if(!empty($quantityParam)){
		
			if(!$productObj->checkIfQuantityIsLegal($quantityParam))
				WebUtil::PrintError("The quantity selection is not permitted");
	
			$projectInfoObj->setQuantity($quantityParam);
		}
	
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
	
		// Now loop through a possibility of 9 options that could come through the URL
		// If we get to any that are not defined, then break the loop
		for($i=1; $i<9; $i++){
			
			$urlOptionName = WebUtil::GetInput("nopt" . $i, FILTER_SANITIZE_STRING_ONE_LINE);
			$urlChoiceName = WebUtil::GetInput("opt" . $i, FILTER_SANITIZE_STRING_ONE_LINE);
			
			if(!$urlOptionName || !$urlChoiceName)
				break;
				
			$optionObj = $productObj->getOptionDetailObj($urlOptionName);
			
			// Make sure that the Option exists.
			if(empty($optionObj))
				continue;
				
			// If they are trying to change an administrative option... We need to make sure that they are logged on first... and that they have permissions.
			if($optionObj->adminOptionController && !$passiveAuthObj->CheckForPermission("ADMIN_OPTION_CHANGING"))
				continue;
			
			$projectInfoObj->setOptionChoice($urlOptionName, $urlChoiceName);

		}
	}

	
	
	
	##-----------------   Methods  -------------------##
	
	function initialized(){
		return $this->_optionsSetFlag && ($this->_quantitySet !== null);
	}



	#- Returns the ID of the product
	function getProductID() {
		return $this->_productID;
	}




	// These are just Pass-through methods into the Product Object
	function getVendorIDArr(){
		return $this->_productObj->getVendorIDArr();
	}
	function getVendorID($vendorNumber) {
		return $this->_productObj->getVendorID($vendorNumber);
	}


	// Returns an array with 6 elements.
	// array(0) is for Vendor #1, array(1) is for Vendor #2, etc.
	// If there is no vendor defined for a given number, then the value will be NULL
	function getVendorSubtotalsArr(){
		
		$returnArr = array();
		
		for($i=1; $i<=6; $i++)
			$returnArr[$i-1] = $this->getVendorSubTotal($i);

		return $returnArr;
		
	}
	
	
	// Returns a total amount for all vendors.
	function getVendorSubtotalsCombined(){
		
		$total = 0;
		
		for($i=1; $i<=6; $i++)
			$total += $this->getVendorSubTotal($i);

		return $total;
	}
	
	
	// Pass in an optional OptionName if you want to find out how much that particular Option added for the Particular Vendor Number.
	// In case that vendor isn't associated with an option then it will just return 0.
	function getVendorSubTotal($vendorNumber, $optionName = ""){
		return $this->_getSubtotal("vendor", $optionName, $vendorNumber);
	}





	// Returns main product title like 'Business Cards'
	function getProductTitle() {
		return $this->_productObj->getProductTitle();
	}

	// Returns string like '2 Color'
	function getProductExt() {
		return $this->_productObj->getProductTitleExtension();
	}

	function getProductTitleWithExt(){
		
		if($this->getProductExt() != "")
			return $this->getProductTitle() . " - " . $this->getProductExt();
		else
			return $this->getProductTitle();

	}

	// Returns a description including the quantity like '100 business cards'
	function getOrderDescription($quantityOverride="") {

		if(!empty($quantityOverride))
			$orderQuantity = $quantityOverride;
		else
			$orderQuantity = $this->getQuantity();

		$ProductName = $this->getProductTitle();

		if($orderQuantity <> 1){
			// If there is already an S at the end of the Product Title, or there is a comma in the title... then don't put an S at the end.
			// An Example of a Product Name with Commas may be something like "Envelopes, #10"
			if(substr($ProductName, -1, 1) != "s" && !preg_match("/,/", $ProductName))
				$ProductName = $ProductName . "s";
		}

		$retStr = $orderQuantity . " " . $ProductName;
		
		if($this->getProductExt() != "")
			$retStr .= " (" . $this->getProductExt() . ")";
		
		return $retStr;
	}



	// Returns a description of the product options separated by commas between each option name...
	// ... like "Card Stock - Glossy, Style - Single Sided)
	function getOptionsDescription() {
	
		$this->_ensureOptionsSet();

		$returnDesc = "";
		
		// Options choices find out 
		foreach ($this->_selectedOptionsArr as $optionName => $choiceName) 
			$returnDesc .= $optionName . " - " . $choiceName .", ";

		// Get rid of the trailing comma
		if(!empty($returnDesc))
			$returnDesc = substr($returnDesc, 0, -2);

		return $returnDesc;
	}
	
	// Works almost identical to getOptionsDescription(), except that it returns the Option and Choice aliases instead.
	function getOptionsAliasStr($hideInLongListsOptions_Flag = false) {
		
		$this->_ensureOptionsSet();

		$optionsToHideInLongLists = array();
		// Don't load this array unless it is necessary.
		if($hideInLongListsOptions_Flag)
			$optionsToHideInLongLists = Product::getArrayOfHiddenOptionChoicesForLists($this->_dbCmd, $this->_productID);
		
		$returnDesc = "";
		
		foreach (array_keys($this->_selectedOptionsArr) as $optionName){
			
			$skipOptionChoice = false;
			
			// Possibly Skip over option/choice combinations that are meant to be hidden in long lists
			if($hideInLongListsOptions_Flag){
				foreach($optionsToHideInLongLists as $thisOptionChoiceHash){
					if($this->getOptionObj($optionName)->optionNameAlias == $thisOptionChoiceHash['option'] && $this->getSelectedChoiceObj($optionName)->ChoiceNameAlias == $thisOptionChoiceHash['choice'])
						$skipOptionChoice = true;
				}
			}
			
			if($skipOptionChoice)
				continue;
			
			$returnDesc .= $this->getOptionObj($optionName)->optionNameAlias . " - " . $this->getSelectedChoiceObj($optionName)->ChoiceNameAlias .", ";
		}

		// Get rid of the trailing comma
		if(!empty($returnDesc))
			$returnDesc = substr($returnDesc, 0, -2);

		return $returnDesc;
	}
	
	// Returns an array... the key is the Option Name... the value is the Choice that is selected
	function getOptionsAndSelections(){
	
		$this->_ensureOptionsSet();
		
		return $this->_selectedOptionsArr;
	}
	
	// Returns an Array... the Key is the Option Name Alias ... and the value is the Option Value Alias
	function getOptionsAliasesAndSelections(){
	
		$this->_ensureOptionsSet();
		
		$returnArr = array();
		
		// Options choices find out 
		foreach (array_keys($this->_selectedOptionsArr) as $optionName) {
			$returnArr[$this->getOptionObj($optionName)->optionNameAlias] = $this->getSelectedChoiceObj($optionName)->ChoiceNameAlias;
		}
		
		
		return $returnArr;
	}



	// Returns an integer
	function getQuantity() {
	
		$this->_ensureOptionsSet();

		return $this->_quantitySet;
	}

	// returns NULL if the Option does not exist... otherwise returns the name of the selected choice
	function getSelectedChoice($optionName) {
	
		$this->_ensureOptionsSet();

		if(!array_key_exists($optionName, $this->_selectedOptionsArr))
			return null;
		
		return $this->_selectedOptionsArr[$optionName];
	}
	
	

	// Returns a Choice Object for the given Option.
	// returns NULL if the Option Does not exist.
	/**
	 * @param string $optionName
	 * @return OptionChoice
	 */
	function getSelectedChoiceObj($optionName) {
	
		$this->_ensureOptionsSet();


		$productOptionsArr = $this->_productObj->getProductOptionsArr();
		
		foreach($productOptionsArr as $productOptionObj){
		
			if($productOptionObj->optionName != $optionName)
				continue;
		
			$selectChoice = $this->getSelectedChoice($optionName);

			foreach($productOptionObj->choices as $choiceObj){

				if($choiceObj->ChoiceName == $selectChoice)
					return $choiceObj;
			}
		}

		return null;
	}


	// Returns the Option Object matching the option name.
	// If the option name does not exist in the product... this method will return NULL.
	/**
	 * @param string $optionName
	 * @return ProductOption
	 */
	function getOptionObj($optionName) {
	
		$this->_ensureOptionsSet();

		$productOptionsArr = $this->_productObj->getProductOptionsArr();
		
		foreach($productOptionsArr as $productOptionObj){
		
			if($productOptionObj->optionName == $optionName)
				return $productOptionObj;
		}

		return null;
	}



					


	/**
	 * Returns a Product Object for this ProjectInformation.
	 *
	 * @return Product
	 */
	function getProductObj(){
		
		return $this->_productObj;
	}


	function isVariableData(){
		return $this->_productObj->isVariableData();
	}

	// Returns a 2D hash.  The key to the 1st level is the Option Name... the value is an array of Choices for that option
	function getProductOptionsArray() {
		
		$productOptionsArr = $this->_productObj->getProductOptionsArr();
		
		$retArr = array();

		foreach($productOptionsArr as $prodOptionObj){
			$retArr[$prodOptionObj->optionName] = array();
			
			foreach($prodOptionObj->choices as $choiceObj)
				$retArr[$prodOptionObj->optionName][] = $choiceObj->ChoiceName;
		}
		
		return $retArr;
	}


	
	// Pass in an "Option" if you want to know how much that option (and its selected choice) adds to the customer's subtotal.
	// Pass in a blank string if you don't care.  The Option name must exist on this Product or there will be an error.
	function getCustomerSubTotal($optionName = "") {
	
		return $this->_getSubtotal("customer", $optionName);
	}



	// The weight of a single unit... EX.. 1 Business Card based upon the options that are selected
	// Options may cause the weight to increase or decrease.  Example.  a "Lamination" choice for postcards may add a slight amount of weight to each postcard.
	function getUnitWeight() {
	
		$this->_ensureOptionsSet();

		$unitWeight = $this->_productObj->getProductUnitWeight();

		$productOptionsArr = $this->_productObj->getProductOptionsArr();
		foreach($productOptionsArr as $productOptionObj){
		
			$selectChoice = $this->getSelectedChoice($productOptionObj->optionName);
			
			// In case this Option does not have any choices.
			if(empty($selectChoice))
				continue;
			
			$found = false;
			foreach($productOptionObj->choices as $choiceObj){
				if($choiceObj->ChoiceName == $selectChoice){
					$found = true;
					$unitWeight += $choiceObj->BaseWeightChange;
				}
			}
			if(!$found)
				throw new Exception("There is a problem with getting weight in the method getUnitWeight");
		}

		return $unitWeight;
	}

	// Total weight of Project, accounting for quantity.
	// Certain Option Choices may cause the weight of an entire Project to increase or decrease (based on choices that are selected).
	// Precission is boolean, if true then it won't "ceil" up to the nearest pound.
	function getProjectWeight($precissionFlag) {

		$projectWeight = $this->getUnitWeight() * $this->getQuantity();

		// Now scan all of the options looking for weight changes.
		$productOptionsArr = $this->_productObj->getProductOptionsArr();
		foreach($productOptionsArr as $productOptionObj){
		
			$selectChoice = $this->getSelectedChoice($productOptionObj->optionName);
			
			// In case this Option does not have any choices.
			if(empty($selectChoice))
				continue;
			
			$found = false;
			foreach($productOptionObj->choices as $choiceObj){
				if($choiceObj->ChoiceName == $selectChoice){
					$found = true;
					$projectWeight += $choiceObj->ProjectWeightChange;
				}
			}
			if(!$found)
				throw new Exception("There is a problem with getting weight in the method getProjectWeight");
		}


		if($precissionFlag)
			return $projectWeight;
		else
			return ceil($projectWeight);
	}



	function setQuantity($amount) {

		if(!preg_match("/^\d+$/", $amount))
			throw new Exception("The quantity amount must be an integer. $amount");

		// If it is not variable data, we need to make sure that a quanity break is selected.  
		// It is possible that an administrator could have changed available values since the customer last saved their project to the database.
		// Select the closest one available, and round up to the next highest value if the difference is a middle.
		if(!$this->_productObj->isVariableData()){

			$quantityChoicesArr = $this->_productObj->getQuantityChoices();
			
			sort($quantityChoicesArr);
			
			if(!in_array($amount, $quantityChoicesArr)){
				
				$lowChoice = 0;
				$highChoice = 0;
				
				foreach($quantityChoicesArr as $thisQuantityChoice){
					
					if($thisQuantityChoice < $amount)
						$lowChoice = $thisQuantityChoice;
						
					if($thisQuantityChoice > $amount && empty($highChoice))
						$highChoice = $thisQuantityChoice;
				}
		
				// If we don't have a high choice, then we have no choice but to return select the Low Choice.
				if(empty($highChoice)){
					$this->_quantitySet = $lowChoice;
					return;
				}
				
				// In case there is not a HighChoice ...OR... a low choice, then it will set the quantity to Zero.
				if(empty($lowChoice)){
					$this->_quantitySet = $highChoice;
					return;
				}
				
				$lowChoiceDiff = $amount - $lowChoice;
				$highChoiceDiff = $highChoice - $amount;
				
				// If the High Choice difference is less ... OR ... the same distance from the previous amount selected...
				// then select the high choice.
				if($lowChoiceDiff < $highChoiceDiff){
					$this->_quantitySet = $lowChoice;
					return;
				}
				else{
					$this->_quantitySet = $highChoice;
					return;
				}
			}
		}

		$this->_quantitySet = $amount;		
	}


	// Returns false if it cant find a match for either the option name or the choice
	// Otherwise records the selected choice and returns true
	function setOptionChoice($optionName, $choiceName) {

		
		// First make sure both the option
		$productOptionsArr  = $this->getProductOptionsArray();
		
		if(!array_key_exists($optionName, $productOptionsArr))
			return false;
		
		if(!in_array($choiceName, $productOptionsArr[$optionName]))
			return false;
			
		$this->_selectedOptionsArr[$optionName] = $choiceName;
		
		$this->_optionsSetFlag = true;
		
		return true;
	}
	
	// Pass in a string like "Card Stock - Glossy, Style - DoubleSided"
	function setOptionChoicesFromDelimitedString($optionStr){
	
		// Before we set the value from the option string... set the default values
		// That will make sure if a Project option string in the DB is old... we will still have a choice selected for every option
		// We will start off by selected the first choice.
		$productOptionsArr = $this->_productObj->getProductOptionsArr();
		foreach($productOptionsArr as $productOptionObj){
		
			// Break after the first choice
			foreach($productOptionObj->choices as $choiceObj){
				$this->setOptionChoice($productOptionObj->optionName, $choiceObj->ChoiceName);
				break;
			}
		}

		// Now it is time to overwrite the default values.
		$optionsHash = ProjectInfo::getProductOptionHashFromDelimitedString($optionStr);
	
		// A dash separates the Option Name from the Choice that is selected
		foreach($optionsHash as $thisOptionName => $choiceSelected)
			$this->setOptionChoice($thisOptionName, $choiceSelected);
		
		$this->_optionsSetFlag = true;
	}
	
	// Let this object know if we truly want to use the default values of the product.
	function intializeDefaultValues(){
		$this->_quantitySet = $this->_productObj->getDefaultQuantity();
		$this->_optionsSetFlag = true;
	}





	####### ----- Private Methods Below ---------- ########

	// Pre-select all Options to the first choice

	// All options should be set afterwards anyway... but incase some of the options or choices have changed or are missing, 
	// this will ensure every Option has a choice selected no matter what.
	private function _setDefaultOptions(){
		
		$optionsArr = $this->getProductOptionsArray();
		foreach($optionsArr as $optionName => $choicesArr){
			
			// It is possible that an admin could have created an Option without making any choices.  Although they should never do this.
			if(!isset($choicesArr[0]))
				continue;
				
			$this->setOptionChoice($optionName, $this->_productObj->getDefaultChoiceForOption($optionName));
		}
	
	}

	private function _ensureOptionsSet(){
		if(!$this->initialized())
			throw new Exception("The Object must be initialized before calling this method.");
	}


	// Pass in an "Option" if you want to know how much that option (and its selected choice) ads to the customer's or vendor's total.
	// Pass in a blank string if you don't care.  The Option name must exist on this Product or there will be an error.
	private function _getSubtotal($subtotalType, $optionName, $vendorNumber = null){
		
		if($subtotalType == "vendor"){
			if(!in_array($vendorNumber, array(1, 2, 3, 4, 5, 6)))
				throw new Exception("If the Subotal type is a 'vendor', then it must be accompanied by a Vendor Number.");
			
			// If there is no Vendor Number defined for this product, then just return null
			if($this->getVendorID($vendorNumber) == null)
				return null;
		}
		else if($subtotalType == "customer"){
			if($vendorNumber)
				throw new Exception("If the Subotal type is a 'customer', then you should not be passing in a Vendor Number.");
		}
		else
			throw new Exception("Illegal Subtotal Type in method _getSubtotal");
		
		
		$optionName = trim($optionName);
		
		if(!empty($optionName)){
			$allOptionsArr = array_keys($this->getOptionsAndSelections());	
			
			if(!in_array($optionName, $allOptionsArr))
				throw new Exception("Error in method ProjectInfo -> _getSubtotal.  The Option name does not exist.");
		}
		
		
		$this->_ensureOptionsSet();

		$subtotalChanges = 0;
		$baseChanges = 0;
		
		// Every Product Has an Initial base price associated with it for the Customer and for the Vendor
		if($subtotalType == "customer"){
			$baseChanges = $this->_productObj->getBasePriceCustomer();
			$subtotalChanges = $this->_productObj->getInitialSubtotalCustomer();
		}
		else if($subtotalType == "vendor"){
			$baseChanges = $this->_productObj->getBasePriceVendor($vendorNumber);
			$subtotalChanges = $this->_productObj->getInitialSubtotalVendor($vendorNumber);
		}
		else{
			throw new Exception("Illegal Subtotal Type in method _getSubtotal");
		}
		
		
		
		// Go through all of the quanity Breaks... use highest quantity break that is less than or equal to the Quantity that is selected
		$quanPriceBreaksArr = $this->_productObj->getQuantityPriceBreaksArr();
		$LastSubtotalChange = 0;
		$LastBaseChange = 0;
		foreach($quanPriceBreaksArr as $thisQuanPriceBreakObj){
			if($thisQuanPriceBreakObj->amount <= $this->_quantitySet){
			
				if($subtotalType == "customer"){
					$LastSubtotalChange = $thisQuanPriceBreakObj->PriceAdjustments->CustomerSubtotalChange;
					$LastBaseChange = $thisQuanPriceBreakObj->PriceAdjustments->CustomerBaseChange;
				}
				else if($subtotalType == "vendor"){
					$LastSubtotalChange = $thisQuanPriceBreakObj->PriceAdjustments->getVendorSubtotalChange($vendorNumber);
					$LastBaseChange = $thisQuanPriceBreakObj->PriceAdjustments->getVendorBaseChange($vendorNumber);
				}
				else
					throw new Exception("Illegal Subtotal Type in method _getSubtotal");
			}
			else
				break;
		}
		
		$subtotalChanges += $LastSubtotalChange;
		$baseChanges += $LastBaseChange;
		

		// If we are only interested in getting the total value of a particular option... then it is OK to wipe out the work we did above.
		if(!empty($optionName)){
			$subtotalChanges = 0;
			$baseChanges = 0;
		}


		// Add up any changes to the base price or the subtotal by cycling through all product Options
		$productOptionsArr = $this->_productObj->getProductOptionsArr();

		foreach($productOptionsArr as $productOptionObj){
		
			// If we are limiting to a specific Product Option... then skip the once we are not interested in.
			if(!empty($optionName)){
				if($optionName != $productOptionObj->optionName)
					continue;
			}

		
			$selectChoice = $this->getSelectedChoice($productOptionObj->optionName);
			
			// This could happen if an administrator created an option but did not create any choices.  In this case, there will not be any price changes... so skip it.
			if($selectChoice == null)
				continue;
			
			$found = false;
			foreach($productOptionObj->choices as $choiceObj){

				if($choiceObj->ChoiceName == $selectChoice){
					$found = true;
					
					if($subtotalType == "customer"){
						$subtotalChanges += $choiceObj->PriceAdjustments->CustomerSubtotalChange;
						$baseChanges += $choiceObj->PriceAdjustments->CustomerBaseChange;
					}
					else if($subtotalType == "vendor"){
						$subtotalChanges += $choiceObj->PriceAdjustments->getVendorSubtotalChange($vendorNumber);
						$baseChanges += $choiceObj->PriceAdjustments->getVendorBaseChange($vendorNumber);
					}
					else
						throw new Exception("Illegal Subtotal Type in method _getSubtotal");


					// Now see if there are any Quantity Price Breaks within this Choice
					$LastSubtotalChange = 0;
					$LastBaseChange = 0;
					foreach($choiceObj->QuantityPriceBreaksArr as $thisQuanPriceBreakObj){
						if($thisQuanPriceBreakObj->amount <= $this->_quantitySet){
						
							if($subtotalType == "customer"){
								$LastSubtotalChange = $thisQuanPriceBreakObj->PriceAdjustments->CustomerSubtotalChange;
								$LastBaseChange = $thisQuanPriceBreakObj->PriceAdjustments->CustomerBaseChange;
							}
							else if($subtotalType == "vendor"){
								$LastSubtotalChange = $thisQuanPriceBreakObj->PriceAdjustments->getVendorSubtotalChange($vendorNumber);
								$LastBaseChange = $thisQuanPriceBreakObj->PriceAdjustments->getVendorBaseChange($vendorNumber);

							}
							else
								throw new Exception("Illegal Subtotal Type in method _getSubtotal");
						

						}
						else
							break;
					}
					
					$subtotalChanges += $LastSubtotalChange;
					$baseChanges += $LastBaseChange;
				}
			}
			if(!$found)
				throw new Exception("There is a problem with getting the total in the method _getSubtotal");
		}


		$subtotal = round(($this->getQuantity() *  $baseChanges + $subtotalChanges), 2);
		return number_format($subtotal, 2, '.', '');
	
	}


}




?>