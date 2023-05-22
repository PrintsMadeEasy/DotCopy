<?

// At certain quantity levels, or based upon Options the users selects, the price may be modified in 2 ways
// 1) The base price can be adjusted (+ or -). That gets multiplied by the Quantity to achieve the final price
// 2) The overall subtotal can be manipulated... let's say at a quantity over 2000 we will give them an even $10 off of the order, even if they order 2024
// The final price may be controlled by a combination of a base price manipulations and subtotal manipulations

class Product {

	private $_productID;
	private $_dbCmd;
	
	private $_productTitle;
	private $_productTitleExt;

	private $_productStatus;
	private $_artworkCanvasWidth;
	private $_artworkCanvasHeight;
	private $_artworkDPI;
	private $_artworkSidesCount;
	private $_artworkSidesDesc;
	private $_artworkBleedPicas;
	private $_artworkIsEditable;
	
	private $_artworkImageUploadEmbedMsg;
	private $_artworkCustomUploadTemplateURL;
	
	private $_artworkSweetSpotWidth;
	private $_artworkSweetSpotHeight;
	private $_artworkSweetSpotX;
	private $_artworkSweetSpotY;
	
	private $_templatePreviewScale;
	private $_templatePreviewSweetSpot;
	private $_templatePreviewSidesDisplay;
	
	private $_useTemplatesFromProductID;
	private $_productionPiggybackID;
	private $_parentProductID;
	private $_multipleTemplatePreviewsArr = array();
	private $_compatibleProductIDsArr = array();
	private $_productImportance;
	private $_defaultPreviewID;
	
	private $_productUnitWeight;
	
	private $_userIDofArtworkSetup;
	private $_reorderCardSavedNote;
	private $_projectInitSavedNote;
	
	private $_variableDataFlag;
	private $_mailingServicesFlag;
	
	private $_artThumbReorderScale;
	private $_artThumbReorderXcoord;
	private $_artThumbReorderYcoord;
	
	private $_templatePreviewMaskPNG;
	private $_templatePreviewMaskPNG_changedFlag;
	private $_thumbnailCopyIconJPG;
	private $_thumbnailCopyIconJPG_changedFlag;
	private $_thumbnailBackJPG;
	private $_thumbnailBackJPG_changedFlag;	
	private $_thumbnailBackWidth;
	private $_thumbnailBackHeight;	
	
	private $_thumbWidth;
	private $_thumbHeight;
	private $_thumbOverlayX;
	private $_thumbOverlayY;
	private $_thumbnailBackFileSize;
	private $_thumbnailCopyFileSize;
	
	private $tempPrevBackLandscapeJPG;
	private $tempPrevBackLandscapeJPG_changedFlag;	
	private $tempPrevBackLandscapeOverlayX;	
	private $tempPrevBackLandscapeOverlayY;	
	private $tempPrevBackLandscapeJpgWidth;	
	private $tempPrevBackLandscapeJpgHeight;	
	private $tempPrevBackLandscapeFileSize;	

	private $tempPrevBackPortraitJPG;
	private $tempPrevBackPortraitJPG_changedFlag;	
	private $tempPrevBackPortraitJpgWidth;
	private $tempPrevBackPortraitJpgHeight;	
	private $tempPrevBackPortraitOverlayX;	
	private $tempPrevBackPortraitOverlayY;	
	private $tempPrevBackPortraitFileSize;	
	
	private $_defaultProductionDays;
	private $_defaultCutOffHour;
	
	private $_custInitBase;
	private $_custInitSub;	
	
	private $_maxBoxSize;
	
	private $_domainID;

	private $_priceAndOptionsLoadedFlag;
	private $_productSwitchesLoadedFlag;

	private $_quantityPriceBreaksArr = array();
	private $_productOptionsArr = array();
	private $_promotionalCommandsArr = array();
	private $_productSwitchesArr = array();

	private $_defaultProjectOptionsArr_cached = array();

	 // A single product may have multiple vendors associated with it... for example... one to do the printing, another to do UV coating, etc.
	// Parrellel arrays are zero based.  There could be missing gaps in between sequences.  For example, Vendor #1 is element 0.  Vendor 5 is element 4
	private $_vendorIDarr = array();
	private $_vendorBasePricesArr = array();
	private $_vendorInitialSubtoalArr = array();

	
	// To avoid duplicate DB queries.
	private static $productionProductIDsCache = array();
	private static $productionIDsInSelDomainsWithPermOutsideCache = array();
	private static $activeProductIDsInUsersSelectedDomainsCache = array();
	private static $domainIDsofProductCache = array();
	private static $variableDataProductCache = array();
	private static $hideOptionsChoicesInListCache = array();
	private static $hideOptionsChoicesAllCache = array();


	// Maybe it is a little more overhead, and it may not work with "background process script"
	// But generally you should let the contructor authenticate the Product ID to make sure that the user has permission to see it (in context of the Domain)
	// That keeps people from Switching Shopping Carts... or 1 admin hacking into product settings for a domain that they don't have privelages for.
	function __construct(DbCmd $dbCmd, $productID, $authenticateProductforDomain = true){

		if(!Product::checkIfProductIDexists($dbCmd, $productID))
			throw new Exception("Error in Product Constructor.  The Product ID does not exist." . $productID);
			
		if($authenticateProductforDomain){
			
			$passiveAuthObj = Authenticate::getPassiveAuthObject();
			
			$domainIDofProduct = self::getDomainIDfromProductID($dbCmd, $productID);
			
			if(!$passiveAuthObj->CheckIfUserCanViewDomainID($domainIDofProduct))
				throw new Exception("Error with Authentication in Product constructor.");	
		}

		$this->_productID = $productID;
		
		$this->_dbCmd = $dbCmd;
		
		if(!$this->loadProductByID($productID))
			throw new Exception("Can not load the Product ID because it does not exist.");
		
		// Don't load extended product details until we are sure the user will be using the data.
		$this->_priceAndOptionsLoadedFlag = false;
		$this->_productSwitchesLoadedFlag = false;
		
	}
	
	
	
	
	
	
	
	
	
	// ------------------------  Begin Static Methods ---------------------------------------------
	
	
	
	// Because we can have multiple vendors (currently up to 6) and we don't know what column the vendorID will be stored in.
	// This function will return an SQL snippet that is useful for restricting a Vendor's permission from a particular project
	// Pass in a VendorID and this method will return something like "VendorID1=23 OR VendorID2=23 OR VendorID3=23 OR VendorID4=23 OR VendorID5=23 OR VendorID6=23"
	function GetVendorIDLimiterSQL($vendorID){
	
		WebUtil::EnsureDigit($vendorID);
		
		$retStr = "";
		for($i=1; $i<=6; $i++)
			$retStr .= "VendorID". $i . "=" . $vendorID . " OR ";
		
		// Get rid of the last OR
		$retStr = substr($retStr, 0, -4);
		
		return $retStr;
	}
	
	function GetVendorNameByUserID(DbCmd $dbCmd, $userID){
	
		WebUtil::EnsureDigit($userID);
	
		$dbCmd->Query("SELECT Company FROM users where ID=$userID");
		if($dbCmd->GetNumRows() == 0)
			$VendorName = "Undefined";
		else
			$VendorName = $dbCmd->GetValue();
			
		if(empty($VendorName))
			$VendorName = "Not Specified";
	
		return $VendorName;
	}

	
	
	
	// A Static method to load a product ID from the database and return an object.
	// Will exit with an error if the product ID does not exist... so make sure to check it first if you are not sure.
	/**
	 * @return Product
	 */
	static function getProductObj(DbCmd $dbCmd, $productID, $authenticateProductforDomain = true){
	
		$productObj = new Product($dbCmd, $productID, $authenticateProductforDomain);
		
		return $productObj;
	
	}
	
	// A Static method to create a new ProductID with default values in the DB.
	// The method will return the product ID.  You can then proceed to edit the DB by calling the constructor.
	static function createNewProduct(DbCmd $dbCmd){
		
		$prodDefaults = array();
	
		// Start out with a "N"ew product Status by default.  That way it won't show up in the "Inactive Product List".  Normally "G"ood and "D"isabled are the only status choices available.
		$prodDefaults["ProductStatus"] = "N";
		$prodDefaults["ArtworkSidesCount"] = 1;
		$prodDefaults["ArtworkDPI"] = 200;
		$prodDefaults["ArtworkBleedPicas"] = "4";
		$prodDefaults["ArtworkIsEditable"] = "Y";
		$prodDefaults["VariableDataFlag"] = "N";
		$prodDefaults["MailingServicesFlag"] = "N";
		$prodDefaults["ArtThumbReorderScale"] = "10";
		$prodDefaults["ArtThumbReorderXcoord"] = "0";
		$prodDefaults["ArtThumbReorderYcoord"] = "0";
		$prodDefaults["DefaultProductionDays"] = "2";
		$prodDefaults["DefaultCutOffHour"] = "0";
		$prodDefaults["MaxBoxSize"] = "0";
		$prodDefaults["ProductImportance"] = "50";   // Start out with a Medium importance, at 50%.
		$prodDefaults["ThumbnailBackFileSize"] = "0";
		$prodDefaults["ThumbnailCopyFileSize"] = "0";
		$prodDefaults["TemplatePreviewScale"] = "10";
		$prodDefaults["TemplatePreviewSweetSpot"] = "Y";
		$prodDefaults["TemplatePreviewSidesDisplay"] = "M";
		$prodDefaults["ThumbOverlayX"] = "0";
		$prodDefaults["ThumbOverlayY"] = "0";
		$prodDefaults["ThumbWidth"] = "100";
		$prodDefaults["ThumbHeight"] = "100";
		$prodDefaults["TempPrevBackLandscapeFileSize"] = "0";
		$prodDefaults["TempPrevBackLandscapeOverlayX"] = "0";
		$prodDefaults["TempPrevBackLandscapeOverlayY"] = "0";
		$prodDefaults["TempPrevBackPortraitOverlayX"] = "0";
		$prodDefaults["TempPrevBackPortraitOverlayY"] = "0";
		$prodDefaults["TempPrevBackPortraitFileSize"] = "0";
		
		$prodDefaults["DomainID"] = Domain::oneDomain();
		
		
		
		$prodDefaults["ArtworkImgUploadEmbedMsg"] = "Don't Forget to Remove All Guides/Lines Before Uploading!";
		

		$newProductID = $dbCmd->InsertQuery("products", $prodDefaults);
		
		return $newProductID;
	}
	
	
	// Static method returning the DomainID associated with the product.
	static function getDomainIDfromProductID(DbCmd $dbCmd, $productID){
		
		$productID = intval($productID);
		
		if(isset(self::$domainIDsofProductCache[$productID]))
			return self::$domainIDsofProductCache[$productID];
		
		$dbCmd->Query("SELECT DomainID FROM products WHERE ID=" . intval($productID));
		$domainID = $dbCmd->GetValue();
		
		if(empty($domainID))
			throw new Exception("Error in method getDomainIDfromProductID");

		self::$domainIDsofProductCache[$productID] = $domainID;
		
		return $domainID;
	}
	
	// Static method returning true or false if the ProductID exists and it is currently Active.
	static function checkIfProductIDisActive(DbCmd $dbCmd, $x){
		
		$x = intval($x);
		
		$dbCmd->Query("SELECT COUNT(*) FROM products WHERE ProductStatus='G' AND ID=" . $x);
		
		if($dbCmd->GetValue() == 0)
			return false;
		else
			return true;
	
	}
	
	// Static method returning true or false if the ProductID exists.
	static function checkIfProductIDexists(DbCmd $dbCmd, $x){
		
		$x = intval($x);
		
		$dbCmd->Query("SELECT COUNT(*) FROM products WHERE ID=" . $x);
		
		if($dbCmd->GetValue() == 0)
			return false;
		else
			return true;
	
	}
	
	// A static method for retrieving a list of Active Product ID's in the system
	static function getActiveProductIDsArr(DbCmd $dbCmd, $domainID){
		
		$domainID = intval($domainID);
		
		$retArr = array();
		$dbCmd->Query("SELECT ID FROM products WHERE ProductStatus='G' AND DomainID=$domainID ORDER BY ProductTitle, ProductTitleExt ASC");
	
		while($thisProdID = $dbCmd->GetValue())
			$retArr[] = $thisProdID;
		
		return $retArr;
	}
	
	
	// A static method for retrieving all Product ID's in the system ordered by Product Title.  Do not include 'N'ew products that haven't been saved yet.
	static function getAllProductIDsArr(DbCmd $dbCmd, $domainID){
		
		$domainID = intval($domainID);
		
		$retArr = array();
		$dbCmd->Query("SELECT ID FROM products WHERE (ProductStatus='G' || ProductStatus='D') AND DomainID=$domainID ORDER BY ProductTitle, ProductTitleExt ASC");
	
		while($thisProdID = $dbCmd->GetValue())
			$retArr[] = $thisProdID;
		
		return $retArr;
	}
	
	
	
	// A static method for retrieving a list of "Active" Parent Product ID's.
	static function getActiveMainProductIDsArr(DbCmd $dbCmd, $domainID){
		
		$domainID = intval($domainID);
		
		$parentProductIDarr = array();
		
		$dbCmd->Query("SELECT ID FROM products WHERE ProductStatus='G' AND DomainID=$domainID AND ParentProductID IS NULL ORDER BY ProductTitle, ProductTitleExt ASC");
		
		while($thisProductID = $dbCmd->GetValue())
			$parentProductIDarr[] = $thisProductID;
		
		return array_unique($parentProductIDarr);
	}
	
	
	
	// Static Method that return True if either one of the products Piggyback off of each other or if they piggyback off of the same Product.
	// Also returns True if the 2 product ID's match.
	static function checkIfProductsShareProduction(DbCmd $dbCmd, $productID1, $productID2){
	
		if($productID1 == $productID2)
			return true;
		
		$productObj1 = Product::getProductObj($dbCmd, $productID1);
		$pID1 = $productObj1->getProductionProductID();
		
		$productObj2 = Product::getProductObj($dbCmd, $productID2);
		$pID2 = $productObj2->getProductionProductID();
		
		if($pID1 == $pID2)
			return true;
		else
			return false;
	}
	
	// Static Method that return True if the products share the same template collection
	static function checkIfProductsShareTemplateCollection(DbCmd $dbCmd, $productID1, $productID2){
	
		if($productID1 == $productID2)
			return true;
		
		$productObj1 = Product::getProductObj($dbCmd, $productID1);
		$pID1 = $productObj1->getProductIDforTemplates();
		
		$productObj2 = Product::getProductObj($dbCmd, $productID2);
		$pID2 = $productObj2->getProductIDforTemplates();
		
		if($pID1 == $pID2)
			return true;
		else
			return false;
	}
	
	
	// Static Method that will tell if this Product has Mailing Services.
	static function checkIfProductHasMailingServices(DbCmd $dbCmd, $prodID){
		
		$productObj = Product::getProductObj($dbCmd, $prodID);
		
		return $productObj->hasMailingService();

	}
	



	// Static Method
	// Pass in a Production ID and it will return an array of ProductID's that are in the same production pool, including the ProductID passed into this method.
	// The Product ID passed into this method doesn't have to be a Production Product ID.
	static function getAllProductIDsSharedForProduction(DbCmd $dbCmd, $prodID){
		
		$producionProductID = Product::getProductionProductIDStatic($dbCmd, $prodID);
		
		$retArr = array($producionProductID);
		
		$dbCmd->Query("SELECT ID FROM products WHERE ProductStatus='G' AND ProductionPiggybackID='" . $producionProductID . "'");
		while($thisProdID = $dbCmd->GetValue())
			$retArr[] = $thisProdID;
		
		$retArr = array_unique($retArr);
		
		return $retArr;
	}
	
	
	// Returns empty array if this Product has a Production Piggy back set.
	// Returns an empty array if this Product defines its own Production routines, but nobody else is PiggyBacking here.
	// Otherwsie returns an array of all Product IDs that have a Production Piggyback pointing here.
	static function getProductIDsPiggyBackingToThisProduct(DbCmd $dbCmd, $productionProductID){
		
		$dbCmd->Query("SELECT ID FROM products WHERE ProductionPiggybackID=" . intval($productionProductID));
		return $dbCmd->GetValueArr();
	}
	
	
	// Static Method
	// Returns an array of Unique Production Product IDs within the users "Selected Domains"
	// The list will include Production Product IDs which the user may not have selected in their domains (or have permission)... but have a linked Production Piggyback.
	// ... Unless you pass in a FALSE flag... in which case if the user must have permission to view the Domain that the Production Piggy back is linked to.
	static function getAllProductionProductIDsInUsersSelectedDomains($withPermissionToSeeOutside = true){
		
		if($withPermissionToSeeOutside && !empty(self::$productionIDsInSelDomainsWithPermOutsideCache))
			return self::$productionIDsInSelDomainsWithPermOutsideCache;
		
		$dbCmd = new DbCmd();
		
		$allProductIDsArr = self::getActiveProductIDsInUsersSelectedDomains();
		
		$productionProductIDsArr = array();
		
		// We can't do this in a single Query because the user may have Products in their selected domains which use a Production Product ID outside of their domain.
		foreach($allProductIDsArr as $thisProductID)
			$productionProductIDsArr[] = Product::getProductionProductIDStatic($dbCmd, $thisProductID);
	
		$retArr = array_unique($productionProductIDsArr);
		
		// If the user passed in a FALSE flag, let's check out all of the domains of each Product... and make sure the user has the rights to the domain.
		if(!$withPermissionToSeeOutside){
			$passiveAuthObj = Authenticate::getPassiveAuthObject();
			
			$tempArr = array();
			foreach($retArr as $thisProductID){
				$domainIDofProduct = Product::getDomainIDfromProductID($dbCmd, $thisProductID);
				
				if($passiveAuthObj->CheckIfUserCanViewDomainID($domainIDofProduct))
					$tempArr[] = $thisProductID;
			}
			
			return $tempArr;
		}
		else{
			self::$productionIDsInSelDomainsWithPermOutsideCache = $retArr;
		
			return $retArr;
		}
	}
	
	
	// A static method for retrieving a list of Active Product ID's in the system
	static function getActiveProductIDsInUsersSelectedDomains(){
		
		if(!empty(self::$activeProductIDsInUsersSelectedDomainsCache))
			return self::$activeProductIDsInUsersSelectedDomainsCache;
		
		$dbCmd = new DbCmd();
		
		$domainObj = Domain::singleton();
		
		$dbCmd->Query("SELECT ID FROM products WHERE ProductStatus='G'
					AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()) . " ORDER BY DomainID ASC, ProductTitle ASC");
		
		self::$activeProductIDsInUsersSelectedDomainsCache = $dbCmd->GetValueArr();
		return self::$activeProductIDsInUsersSelectedDomainsCache;
		
	}
	
	
	
	// Static Method
	// Returns the Product ID used for production.  It may have a Production Piggy Back.
	// If it doesn't have a production piggy back then it will return it's own Product ID.
	static function getProductionProductIDStatic(DbCmd $dbCmd, $productID){
	
		$productID = intval($productID);
		
		if(isset(self::$productionProductIDsCache[$productID]))
			return self::$productionProductIDsCache[$productID];
		
		$dbCmd->Query("SELECT ProductionPiggybackID FROM products WHERE ID=" . intval($productID));
		if($dbCmd->GetNumRows() == 0)
			throw new Exception("Error in method getProductionProductIDStatic");
			
		$productionPiggyBack = $dbCmd->GetValue();
		
		if(empty($productionPiggyBack))
			$productionPiggyBack = $productID;
			
		self::$productionProductIDsCache[$productID] = $productionPiggyBack;
		
		return $productionPiggyBack;
	}
	

	
	// Static Method
	// The key to the hash is the Product ID, the value is the name of the product
	// Returns the Title with the Extension
	static function getFullProductNamesHash(DbCmd $dbCmd, $domainID, $includeAllProductsKeyFlag){
	
		$domainID = intval($domainID);
		
		if($includeAllProductsKeyFlag)
			$productIDarr["0"] = "All Products";
		else
			$productIDarr = array();
		
		
		$dbCmd->Query("SELECT ID, ProductTitle, ProductTitleExt FROM products WHERE ProductStatus='G' AND DomainID=$domainID ORDER BY ProductTitle ASC");
		
		while($row = $dbCmd->GetRow()){
			$productName = $row["ProductTitle"];
			
			if(!empty($row["ProductTitleExt"]))
				$productName .= " " . $row["ProductTitleExt"];
			
			$productIDarr[strval($row["ID"])] = $productName;
		}

		return $productIDarr;
	}
	
	// Static Method
	// Returns a list of active products that do not have a Parent Product ID
	// The key is the Product ID and the value is the Product Title (without the Title Extension).
	static function getMainProductNamesHash(DbCmd $dbCmd, $domainID){
	
		$domainID = intval($domainID);
		
		$dbCmd->Query("SELECT ID, ProductTitle FROM products WHERE ProductStatus='G' AND ParentProductID IS NULL AND DomainID=$domainID");
	
		$returnHash = array();
		
		while($row = $dbCmd->GetRow())
			$returnHash[strval($row["ID"])] = $row["ProductTitle"];

		return $returnHash;
	}
	
	// Based on ther Domain privileges, this will return all Product IDs that a user can see, Grouped by Domain
	// This returns all of the Product IDs, even if they are not Active.
	static function getAllProductIDsThatUserHasPermissionToSee(){
		
		$dbCmd = new DbCmd();
		
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		$domainIDs = $passiveAuthObj->getUserDomainsIDs();
		
		$retArr = array();
		foreach($domainIDs as $thisDomainID)
			$retArr = array_merge($retArr, Product::getActiveProductIDsArr($dbCmd, $thisDomainID));

		return $retArr;
	}
	
	
	// Static Method
	// Returns the Product Name.
	static function getFullProductName(DbCmd $dbCmd, $productID){
	
		$dbCmd->Query("SELECT ProductTitle, ProductTitleExt FROM products WHERE ID=" . intval($productID));
		if($dbCmd->GetNumRows() == 0)
			throw new Exception("Error in method getFullProductName. The Product ID does not exist.");
		
		$row = $dbCmd->GetRow();
		
		return $row["ProductTitle"] . " " . $row["ProductTitleExt"];
	}
	
	// Static Method
	// Returns the Product Name without Extention
	static function getRootProductName(DbCmd $dbCmd, $productID){
		
		$dbCmd->Query("SELECT ProductTitle FROM products WHERE ID=" . intval($productID));
		if($dbCmd->GetNumRows() == 0)
			throw new Exception("Error in method getRootProductName. The Product ID does not exist.");
		
		return $dbCmd->GetValue();
	}
	// Static Method
	// Returns the Product Ext... or an empty string.
	static function getProductTitleExt(DbCmd $dbCmd, $productID){
		
		$dbCmd->Query("SELECT ProductTitleExt FROM products WHERE ID=" . intval($productID));
		if($dbCmd->GetNumRows() == 0)
			throw new Exception("Error in method getProductTitleExt. The Product ID does not exist.");
		
		return $dbCmd->GetValue();
	}
	

	// This will tell if the user (currenty logged in) has domain permissions for the Production PiggyBack (that is linked to this Product ID)
	// The Production PiggyBack could get set on a Product (by a Super Admin).  In which case a normal Admin would not have permission to change Vendor Pricing, PDF profiles, etc.
	// Pass in the Source Product ID (not the Productd ID of the production PiggyBack).
	static function checkIfProductPiggyBackIsWithinUsersDomain($mainProductID){
		
		$dbCmd = new DbCmd();
		if(!Product::checkIfProductIDexists($dbCmd, $mainProductID))
			throw new Exception("Error in method getIfProductPiggyBackIsWithinUsersDomain with Product ID.");
			
		$mainProducutObj = new Product($dbCmd, $mainProductID);
		
		if(!in_array($mainProducutObj->getProductionProductID(), Product::getAllProductIDsThatUserHasPermissionToSee()))
			return false;
		else
			return true;
	}
	
	
	// Static Method
	// Returns TRUE if the Product has Variable Data.
	static function checkIfProductHasVariableData(DbCmd $dbCmd, $productID){
	
		if(isset(self::$variableDataProductCache[$productID]))
			return self::$variableDataProductCache[$productID];
			
		$dbCmd->Query("SELECT VariableDataFlag FROM products WHERE ID=" . intval($productID));
		if($dbCmd->GetNumRows() == 0)
			throw new Exception("Error in method checkIfProductIsVariableData. The Product ID does not exist.");
		
		if($dbCmd->GetValue() == "Y")
			self::$variableDataProductCache[$productID] = TRUE;
		else
			self::$variableDataProductCache[$productID] = FALSE;
			
		return self::$variableDataProductCache[$productID];
	}
	
	
	
	
	
	
	// Returns a 2D array.  The second dimension has a hash with 2 keys set "option" and "choice".
	// Let's you know which Option/Choice combos has a flag set to hide the Option/choice within order lists.
	// This filters out the Option and Choice Alias names.
	static function getArrayOfHiddenOptionChoicesForLists(DbCmd $dbCmd, $productID){
		
		if(!empty(self::$hideOptionsChoicesInListCache[$productID]))
			return self::$hideOptionsChoicesInListCache[$productID];
		
		$productID = intval($productID);
		
		$optionChoicesArr = array();
		
		$dbCmd->Query("SELECT OptionNameAlias, ChoiceNameAlias FROM 
				(productoptions INNER JOIN productoptionchoices ON productoptions.ID = productoptionchoices.ProductOptionID)
				INNER JOIN products ON products.ID = productoptions.ProductID
				WHERE products.ID = $productID AND HideOptionInLists ='Y'");
		
		while($row = $dbCmd->GetRow()){
			$optionChoicesArr[] = array("option"=>$row["OptionNameAlias"], "choice"=>$row["ChoiceNameAlias"]);
		}
			
		self::$hideOptionsChoicesInListCache[$productID] = $optionChoicesArr;
		return $optionChoicesArr;
	}
	
	// If the Option/Choice has a flag set to filter options on long lists this will fitler the Option Descriptions string stored in the DB.
	// $optionsDescriptionStr should be something like "Card Stock - Glossy, Style - Double Sided"
	static function filterOptionDescriptionForList(DbCmd $dbCmd, $productID, $optionsDescriptionStr){
		
		$hiddenOptionsChoicesArr = self::getArrayOfHiddenOptionChoicesForLists($dbCmd, $productID);

		foreach($hiddenOptionsChoicesArr as $optionChoiceHash){
			
			$thisOptionName = $optionChoiceHash["option"];
			$thisChoiceName = $optionChoiceHash["choice"];
			
			$optionsDescriptionStr = preg_replace("/".preg_quote($thisOptionName)." - ".preg_quote($thisChoiceName)."(, )?/", "", $optionsDescriptionStr);
		}
		
		// Get rid of possible trailing comma (in case the last option was removed)
		$optionsDescriptionStr = preg_replace("/,\s$/", "", $optionsDescriptionStr);
			
		return $optionsDescriptionStr;
	}
	
	
	
	
	
	
	// Returns a 2d array.  The second level is a hash with 2 elements... "option" and "choice".
	// Let's you know which Option/Choice combos has a flag set to hide the Option/choice within order lists.
	static function getArrayOfHiddenOptionChoicesForCustomer(DbCmd $dbCmd, $productID){
		
		if(!empty(self::$hideOptionsChoicesAllCache[$productID]))
			return self::$hideOptionsChoicesAllCache[$productID];
		
		$productID = intval($productID);
		$optionChoicesArr = array();
		
		$dbCmd->Query("SELECT OptionName, ChoiceName FROM 
				(productoptions INNER JOIN productoptionchoices ON productoptions.ID = productoptionchoices.ProductOptionID)
				INNER JOIN products ON products.ID = productoptions.ProductID
				WHERE products.ID = $productID AND HideOptionAll ='Y'");
		
		while($row = $dbCmd->GetRow()){
			$optionChoicesArr[] = array("option"=>$row["OptionName"], "choice"=>$row["ChoiceName"]);
		}
			
		self::$hideOptionsChoicesAllCache[$productID] = $optionChoicesArr;
		return $optionChoicesArr;
	}
	

	// If the Option/Choice has a flag set to filter options on long lists this will fitler the Option Descriptions string stored in the DB.
	// $optionsDescriptionStr should be something like "Card Stock - Glossy, Style - Double Sided"
	static function filterOptionDescriptionForCustomer(DbCmd $dbCmd, $productID, $optionsDescriptionStr){
		
		$hiddenOptionsChoicesArr = self::getArrayOfHiddenOptionChoicesForCustomer($dbCmd, $productID);
		
		foreach($hiddenOptionsChoicesArr as $thisOptionChoice){
			$thisOptionName = $thisOptionChoice["option"];
			$thisChoiceName = $thisOptionChoice["choice"];
			$optionsDescriptionStr = preg_replace("/".preg_quote($thisOptionName)." - ".preg_quote($thisChoiceName)."(, )?/", "", $optionsDescriptionStr);
		}
		
		// Get rid of possible trailing comma (in case the last option was removed)
		$optionsDescriptionStr = preg_replace("/,\s$/", "", $optionsDescriptionStr);
		
		return $optionsDescriptionStr;
	}
	
	
	// Will move the sorting sequence up or down relative to where it was compared to other Options within the Product.
	static function moveProductOptionSort($productID, $optionName, $moveForward = true){
		
		$dbCmd = new DbCmd();
		$productObj = new Product($dbCmd, $productID, true);

		$allProductOptionsArr = $productObj->getProductOptionNamesArr();

		if(!in_array($optionName, $allProductOptionsArr))
			throw new Exception("Error in method moveProductOptionSort. The Option Name does not exist.");
			
		$arrayShifted = WebUtil::arrayMoveElement($allProductOptionsArr, $optionName, $moveForward);
		
		$dbCmd = new DbCmd();
		for($newSortPos=0; $newSortPos<sizeof($arrayShifted); $newSortPos++)
			$dbCmd->UpdateQuery("productoptions", array("Sort"=>$newSortPos+1), ("OptionName=\"" . DbCmd::EscapeSQL($arrayShifted[$newSortPos]) . "\" AND ProductID=" . intval($productID)) );
	}
	
	// Will move the sorting sequence up or down relative to where it was compared to other Product Options.
	static function moveProductOptionChoiceSort($productID, $optionName, $choiceName, $moveForward = true){
		
		$dbCmd = new DbCmd();
		$productObj = new Product($dbCmd, $productID, true);
		
		if(!$productObj->checkIfOptionChoiceExists($optionName, $choiceName))
			throw new Exception("Error in method moveProductOptionChoiceSort, the Option/Choice doesn't exist.");
			
		$productOptionObj = $productObj->getOptionObject($optionName);
		
		$allProductChoicesArr = $productOptionObj->getChoiceNamesArr();
			
		$arrayShifted = WebUtil::arrayMoveElement($allProductChoicesArr, $choiceName, $moveForward);
		
		$dbCmd = new DbCmd();
		for($newSortPos=0; $newSortPos<sizeof($arrayShifted); $newSortPos++){
			$dbCmd->UpdateQuery("productoptionchoices", array("Sort"=>$newSortPos+1), ("ChoiceName=\"" . DbCmd::EscapeSQL($arrayShifted[$newSortPos]) . "\" AND ProductOptionID=" . intval($productOptionObj->optionID)) );
		}
	}
	
	
	// Pass in an array of Product ID's... it will return the array of Product ID's with the most "important" product ID's in the front.
	static function sortProductIdsByImportance(array $productIDs){
		
		if(empty($productIDs))
			return array();
		
		$dbCmd = new DbCmd();

		$dbCmd->Query("SELECT ID FROM products WHERE " . DbHelper::getOrClauseFromArray("ID", $productIDs) . " ORDER BY ProductImportance DESC");
		return $dbCmd->GetValueArr();
		
	}
	
	// --------------------------------  End Static Methods ---------------------------
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	function updateProduct(){
	
		if(empty($this->_productID))
			throw new Exception("Error updating Product: A Product ID has not been loaded yet.");	
		if(empty($this->_productTitle))
			throw new Exception("Error updating Product: Product title can not be left blank.");		
		if(empty($this->_artworkCanvasWidth))
			throw new Exception("Error updating Product: Artwork Canvas Width can not be left blank.");
		if(empty($this->_artworkCanvasHeight))
			throw new Exception("Error updating Product: Artwork Canvas Height can not be left blank.");
			
		if((empty($this->_artworkSweetSpotWidth) && !empty($this->_artworkSweetSpotHeight)) || (empty($this->_artworkSweetSpotHeight) && !empty($this->_artworkSweetSpotWidth)))
			throw new Exception("Error updating Product: If the Sweed Sport Width is empty... then the height must also be empty and visa versa.");

		if(!empty($this->_artworkImageUploadEmbedMsg) && !empty($this->_artworkCustomUploadTemplateURL))
			throw new Exception("Error updating Product: You can not have an Embeded Message for Artwork Upload and also have a URL to a custom Artwork Upload Template.");
		
		// Make sure that we load all of the prices an options because we will be update all of them, regardless if anything has changed or not.
		$this->_loadPricesAndOptions();
		$this->_loadProductSwitches();
		
		$updateArr = array();
		$updateArr["ProductTitle"] = $this->_productTitle;
		$updateArr["ProductTitleExt"] = $this->_productTitleExt;
		$updateArr["ProductStatus"] = $this->_productStatus;
		$updateArr["ArtworkCanvasWidth"] = $this->_artworkCanvasWidth;
		$updateArr["ArtworkCanvasHeight"] = $this->_artworkCanvasHeight;
		$updateArr["ArtworkDPI"] = $this->_artworkDPI;
		$updateArr["ArtworkImgUploadEmbedMsg"] = $this->_artworkImageUploadEmbedMsg;
		$updateArr["ArtworkCustomTemplateURL"] = $this->_artworkCustomUploadTemplateURL;
		$updateArr["ArtworkSidesCount"] = $this->_artworkSidesCount;
		$updateArr["ArtworkSidesDesc"] = $this->_artworkSidesDesc;
		$updateArr["ArtworkBleedPicas"] = $this->_artworkBleedPicas;
		$updateArr["ArtworkIsEditable"] = $this->_artworkIsEditable;
		$updateArr["ArtworkSweetSpotWidth"] = $this->_artworkSweetSpotWidth;
		$updateArr["ArtworkSweetSpotHeight"] = $this->_artworkSweetSpotHeight;
		$updateArr["ArtworkSweetSpotX"] = $this->_artworkSweetSpotX;
		$updateArr["ArtworkSweetSpotY"] = $this->_artworkSweetSpotY;
		$updateArr["TemplatePreviewScale"] = $this->_templatePreviewScale;
		$updateArr["TemplatePreviewSweetSpot"] = ($this->_templatePreviewSweetSpot ? "Y" : "N");
		$updateArr["TemplatePreviewSidesDisplay"] = $this->_templatePreviewSidesDisplay;
		$updateArr["UseTemplatesFromProductID"] = $this->_useTemplatesFromProductID;
		$updateArr["ProductImportance"] = $this->_productImportance;
		$updateArr["ProductionPiggybackID"] = $this->_productionPiggybackID;
		$updateArr["ParentProductID"] = $this->_parentProductID;
		$updateArr["DefaultMultiPreviewID"] = $this->_defaultPreviewID;
		$updateArr["ProductUnitWeight"] = $this->_productUnitWeight;
		$updateArr["UserIDofArtworkSetup"] = $this->_userIDofArtworkSetup;
		$updateArr["ReorderCardSavedNote"] = $this->_reorderCardSavedNote;
		$updateArr["ProjectInitSavedNote"] = $this->_projectInitSavedNote;
		$updateArr["VariableDataFlag"] = ($this->_variableDataFlag ? "Y" : "N");
		$updateArr["MailingServicesFlag"] = ($this->_mailingServicesFlag ? "Y" : "N");
		$updateArr["ArtThumbReorderScale"] = $this->_artThumbReorderScale;
		$updateArr["ArtThumbReorderXcoord"] = $this->_artThumbReorderXcoord;
		$updateArr["ArtThumbReorderYcoord"] = $this->_artThumbReorderYcoord;
		$updateArr["ThumbWidth"] = $this->_thumbWidth;
		$updateArr["ThumbHeight"] = $this->_thumbHeight;
		$updateArr["ThumbOverlayX"] = $this->_thumbOverlayX;
		$updateArr["ThumbOverlayY"] = $this->_thumbOverlayY;
		$updateArr["TempPrevBackLandscapeOverlayX"] = $this->tempPrevBackLandscapeOverlayX;
		$updateArr["TempPrevBackLandscapeOverlayY"] = $this->tempPrevBackLandscapeOverlayY;
		$updateArr["TempPrevBackPortraitOverlayX"] = $this->tempPrevBackPortraitOverlayX;
		$updateArr["TempPrevBackPortraitOverlayY"] = $this->tempPrevBackPortraitOverlayY;
		$updateArr["DefaultProductionDays"] = $this->_defaultProductionDays;
		$updateArr["DefaultCutOffHour"] = $this->_defaultCutOffHour;
		$updateArr["MaxBoxSize"] = $this->_maxBoxSize;
		$updateArr["CustInitBase"] = $this->_custInitBase;
		$updateArr["CustInitSub"] = $this->_custInitSub;
		$updateArr["PromotionalCommands"] = implode("^", $this->_promotionalCommandsArr);
		
		
		
		$canUpdateVendorPricing = Product::checkIfProductPiggyBackIsWithinUsersDomain($this->_productID);

		// Only the person in control of the Domain of the Production Piggyback can add new vendors or change pricing.
		if($canUpdateVendorPricing){
		
			// Record the Vendor IDs that have been defined for this product.
			for($i = 1; $i <= 6; $i++)
				$updateArr["VendorID" . $i] = $this->getVendorID($i);
			
			
			// Add Vendor Values to our Hash
			for($i=1; $i<=6; $i++){
				$updateArr["Vend".$i."InitSub"] = $this->getInitialSubtotalVendor($i);
				$updateArr["Vend".$i."InitBase"] = $this->getBasePriceVendor($i);
			}
		}
			
		
		$updateArr["MultipleTemplatePreviews"] = implode('|', $this->_multipleTemplatePreviewsArr);
		if(strlen($updateArr["MultipleTemplatePreviews"]) > 60)
			throw new Exception("Error saving this Product to the Database. Too many compatible Multiple Previews for Templates have been selected. The database needs to be widened you you can select less choices.");
		
		$updateArr["CompatibleProductIDs"] = implode('|', $this->_compatibleProductIDsArr);
		if(strlen($updateArr["CompatibleProductIDs"]) > 250)
			throw new Exception("Error saving this Product to the Database. Too many compatible products have been selected. The database needs to be widened you you can select less choices.");
	
	
	
	
		// Update the Data that we have so far.
		$this->_dbCmd->UpdateQuery("products", $updateArr, "ID=" . $this->_productID);
		

		
		
		
		// ----------   Update or Insert Quantity Breaks on this Product -------------
		foreach($this->_quantityPriceBreaksArr as $thisQuanBreakObj){
			
			$quanBreakValuesArr = array(	"Quantity"=>$thisQuanBreakObj->amount, "ProductID"=>$this->_productID, 
							"CustSub"=>$thisQuanBreakObj->PriceAdjustments->getCustomerSubtotalChange(), 
							"CustBase"=>$thisQuanBreakObj->PriceAdjustments->getCustomerBaseChange());
			
			
			if($canUpdateVendorPricing){
				
				// Add Vendor Values to our Hash
				for($i=1; $i<=6; $i++){
					
					// In case the vendor has been deleted, delete the corresponding values
					if($this->getVendorID($i) == null){
						$quanBreakValuesArr["Vend".$i."Sub"] = null;
						$quanBreakValuesArr["Vend".$i."Base"] = null;
					}
					else{
						$quanBreakValuesArr["Vend".$i."Sub"] = $thisQuanBreakObj->PriceAdjustments->getVendorSubtotalChange($i);
						$quanBreakValuesArr["Vend".$i."Base"] = $thisQuanBreakObj->PriceAdjustments->getVendorBaseChange($i);
					}
				}
			}
			
			
			// Find out if the quantity break already exists within the DB. 
			// If so update the values, otherwise insert a new record.
			$this->_dbCmd->Query("SELECT COUNT(*) FROM productquantitybreaks WHERE ProductID=" . $this->_productID . " AND Quantity=" . $thisQuanBreakObj->amount);
			$quanBreakCount = $this->_dbCmd->GetValue();
			
			if($quanBreakCount == 0)
				$this->_dbCmd->InsertQuery("productquantitybreaks", $quanBreakValuesArr);
			else
				$this->_dbCmd->UpdateQuery("productquantitybreaks", $quanBreakValuesArr, ("ProductID=" . $this->_productID . " AND Quantity=" . $thisQuanBreakObj->amount));
					
		}
		
		// Delete Quantity Breaks from the DB if they don't exist on this product anymore.
		// First get a list of all unique Quantity Amounts currently stored in the database.
		$dbQuantityAmountsArr = array();
		$this->_dbCmd->Query("SELECT Quantity FROM productquantitybreaks WHERE ProductID=" . $this->_productID);
		while($thisQuanAmount = $this->_dbCmd->GetValue())
			$dbQuantityAmountsArr[] = $thisQuanAmount;
		
		foreach($dbQuantityAmountsArr as $thisQuanAmount){
			if(!$this->checkIfQuantityBreakExists($thisQuanAmount))
				$this->_dbCmd->Query("DELETE FROM productquantitybreaks WHERE ProductID=" . $this->_productID . " AND Quantity=" . $thisQuanAmount);
		}
		

		
		// ----------   Update or Insert Product Switches -------------
		// Delete the existing product switches... and insert new ones.
		// Takes care of Edits and Addtions.
		$this->_dbCmd->Query("DELETE FROM productswitches WHERE ProductIDsource=" . $this->_productID);
		
		foreach($this->_productSwitchesArr as $targetProductID => $thisProductSwitchObj){
		
			$productSwitchValuesArr = array("SwitchTitle"=>$thisProductSwitchObj->switchTitle, 
							"ProductIDsource"=>$this->_productID, "ProductIDtarget"=>$targetProductID,
							"SwitchLinkSubject"=>$thisProductSwitchObj->switchLinkSubject,
							"SwitchDescription"=> $thisProductSwitchObj->switchDescription, 
							"SwitchDescriptionIsHTML"=> ($thisProductSwitchObj->switchDescriptionIsHTML ? "Y" : "N"));
							


			$this->_dbCmd->InsertQuery("productswitches", $productSwitchValuesArr);
		}
		
		
		
		
		// ----------   Update or Insert Product Options -------------
		foreach($this->_productOptionsArr as $thisOptionObj){
		
			$optionValuesArr = array(	"OptionName"=>$thisOptionObj->optionName, "ProductID"=>$this->_productID,
							"OptionNameAlias"=>$thisOptionObj->optionNameAlias,
							"OptionDescription"=>$thisOptionObj->optionDescription,
							"OptionDescriptionIsHTML"=> ($thisOptionObj->optionDescriptionIsHTMLformat ? "Y" : "N"), 
							"AdminOptionController"=> ($thisOptionObj->adminOptionController ? "Y" : "N"), 
							"ArtworkSidesController"=> ($thisOptionObj->artworkSidesController ? "Y" : "N"), 
							"ArtSearchReplaceController"=> ($thisOptionObj->artworkSearchReplaceController ? "Y" : "N"), 
							"CouponDiscountExempt"=> ($thisOptionObj->couponDiscountExempt ? "Y" : "N"), 
							"VariableImageController"=> ($thisOptionObj->variableImageController ? "Y" : "N"), 
							"OptionInCommonForProduction"=> ($thisOptionObj->optionInCommonForProduction ? "Y" : "N"));
							
		
			// Find out if the Option Name already exists within the DB for this porduct. 
			// If so update the values, otherwise insert a new record.
			$this->_dbCmd->Query("SELECT COUNT(*) FROM productoptions WHERE ProductID=" . $this->_productID . " AND OptionName LIKE '" . DbCmd::EscapeLikeQuery($thisOptionObj->optionName) . "'");
			$optionCount = $this->_dbCmd->GetValue();
			
			if($optionCount == 0)
				$this->_dbCmd->InsertQuery("productoptions", $optionValuesArr);
			else
				$this->_dbCmd->UpdateQuery("productoptions", $optionValuesArr, ("ProductID=" . $this->_productID . " AND OptionName LIKE '" . DbCmd::EscapeLikeQuery($thisOptionObj->optionName) . "'"));
		
		
			// Get the Option ID of the record that we just updated or inserted.  This is how we link to the choices.
			$this->_dbCmd->Query("SELECT ID FROM productoptions WHERE ProductID=" . $this->_productID . " AND OptionName LIKE '" . DbCmd::EscapeLikeQuery($thisOptionObj->optionName) . "'");
			$optionID = $this->_dbCmd->GetValue();
			
			
			// ----------   Update or Insert Choices linked to this Option -------------
			foreach($thisOptionObj->choices as $thisChoiceObj){			
				
			
				$choiceValuesArr = array(	"ProductOptionID"=>$optionID, "ChoiceName"=>$thisChoiceObj->ChoiceName, 
								"ChoiceDescription"=>$thisChoiceObj->ChoiceDescription,
								"ChoiceNameAlias"=>$thisChoiceObj->ChoiceNameAlias,
								"ChoiceDescriptionIsHTML"=>($thisChoiceObj->ChoiceDescriptionIsHTMLformat ? "Y" : "N"),
								"UnitWeightChange"=>$thisChoiceObj->BaseWeightChange, "ProjectWeightChange"=>$thisChoiceObj->ProjectWeightChange, 
								"ArtworkSideCount"=>$thisChoiceObj->artworkSideCount, "VariableImageFlag"=> ($thisChoiceObj->variableImageFlag ? "Y" : "N"),
								"HideOptionAll"=> $thisChoiceObj->hideOptionAllFlag ? "Y" : "N",
								"HideOptionInLists"=> $thisChoiceObj->hideOptionInListsFlag ? "Y" : "N",
								"ArtStringSearchFor"=>$thisChoiceObj->artworkStringSearchFor, "ArtStringReplaceWith"=>$thisChoiceObj->artworkStringReplaceWith,
								"CustSub"=>$thisChoiceObj->PriceAdjustments->getCustomerSubtotalChange(), 
								"CustBase"=>$thisChoiceObj->PriceAdjustments->getCustomerBaseChange(),
								"ProductionAlert"=>$thisChoiceObj->productionAlert
								);

								
				if($canUpdateVendorPricing){
								
					// Add Vendor Values to our Hash
					for($i=1; $i<=6; $i++){
					
						// In case the vendor has been deleted, delete the corresponding values
						if($this->getVendorID($i) == null){
							$choiceValuesArr["Vend".$i."Sub"] = null;
							$choiceValuesArr["Vend".$i."Base"] = null;
						}
						else{
							$choiceValuesArr["Vend".$i."Sub"] = $thisChoiceObj->PriceAdjustments->getVendorSubtotalChange($i);
							$choiceValuesArr["Vend".$i."Base"] = $thisChoiceObj->PriceAdjustments->getVendorBaseChange($i);
						}
					}
				}
			

				// Find out if the Choice Name already exists within the DB for this porduct. 
				// If so update the values, otherwise insert a new record.
				$this->_dbCmd->Query("SELECT COUNT(*) FROM productoptionchoices WHERE ProductOptionID=" . $optionID . " AND ChoiceName LIKE '" . DbCmd::EscapeLikeQuery($thisChoiceObj->ChoiceName) . "'");
				$choiceCount = $this->_dbCmd->GetValue();

				if($choiceCount == 0)
					$this->_dbCmd->InsertQuery("productoptionchoices", $choiceValuesArr);
				else
					$this->_dbCmd->UpdateQuery("productoptionchoices", $choiceValuesArr, ("ProductOptionID=" . $optionID . " AND ChoiceName LIKE '" . DbCmd::EscapeLikeQuery($thisChoiceObj->ChoiceName) . "'"));
			
			
				// Get the Choice ID of the record that we just updated or inserted.  This is how we link choices to their quantity price breaks.
				$this->_dbCmd->Query("SELECT ID FROM productoptionchoices WHERE ProductOptionID=" . $optionID . " AND ChoiceName LIKE '" . DbCmd::EscapeLikeQuery($thisChoiceObj->ChoiceName) . "'");
				$choiceID = $this->_dbCmd->GetValue();


				// ----------   Update or Insert Quantity Breaks Linked to this Option Choice -------------
				foreach($thisChoiceObj->QuantityPriceBreaksArr as $choiceQuanBreakObj){

					$choiceQuanBrkArr = array(	"Quantity"=>$choiceQuanBreakObj->amount, "ProductOptionChoiceID"=>$choiceID, 
									"CustSub"=>$choiceQuanBreakObj->PriceAdjustments->getCustomerSubtotalChange(), 
									"CustBase"=>$choiceQuanBreakObj->PriceAdjustments->getCustomerBaseChange());

					
					if($canUpdateVendorPricing){
					
						// Add Vendor Values to our Hash
						for($i=1; $i<=6; $i++){
						
							// In case the vendor has been deleted, delete the corresponding values
							if($this->getVendorID($i) == null){
								$choiceQuanBrkArr["Vend".$i."Sub"] = null;
								$choiceQuanBrkArr["Vend".$i."Base"] = null;
							}
							else{
								$choiceQuanBrkArr["Vend".$i."Sub"] = $choiceQuanBreakObj->PriceAdjustments->getVendorSubtotalChange($i);
								$choiceQuanBrkArr["Vend".$i."Base"] = $choiceQuanBreakObj->PriceAdjustments->getVendorBaseChange($i);
							}
						}
					}
					
					
					// Find out if the quantity break already exists within the DB. 
					// If so update the values, otherwise insert a new record.
					$this->_dbCmd->Query("SELECT COUNT(*) FROM productchoicequantbrks WHERE ProductOptionChoiceID=" . $choiceID . " AND Quantity=" . $choiceQuanBreakObj->amount);
					$choiceQuanBreakCount = $this->_dbCmd->GetValue();

					if($choiceQuanBreakCount == 0)
						$this->_dbCmd->InsertQuery("productchoicequantbrks", $choiceQuanBrkArr);
					else
						$this->_dbCmd->UpdateQuery("productchoicequantbrks", $choiceQuanBrkArr, ("ProductOptionChoiceID=" . $choiceID . " AND Quantity=" . $choiceQuanBreakObj->amount));

				}
				
				
				
				// Delete Quantity Breaks from the DB if they don't exist on this Option Choice anymore.
				// First get a list of all unique Quantity Amounts currently stored in the database.
				$dbChoiceQuanAmountsArr = array();
				$this->_dbCmd->Query("SELECT Quantity FROM productchoicequantbrks WHERE ProductOptionChoiceID=" . $choiceID);
				while($thisQuanAmount = $this->_dbCmd->GetValue())
					$dbChoiceQuanAmountsArr[] = $thisQuanAmount;

				foreach($dbChoiceQuanAmountsArr as $thisQuanAmount){
					if(!$this->checkIfQuantityBreakExistsOnOptionChoice($thisOptionObj->optionName, $thisChoiceObj->ChoiceName, $thisQuanAmount))
						$this->_dbCmd->Query("DELETE FROM productchoicequantbrks WHERE ProductOptionChoiceID=" . $choiceID . " AND Quantity=" . $thisQuanAmount);
				}
			
			}
			
			
			// Delete Option choices from the DB if they don't exist on this Option anymore.
			// First get a list of all unique Choice Names currently stored in the database.
			$dbChoiceNamesArr = array();
			$this->_dbCmd->Query("SELECT ChoiceName FROM productoptionchoices WHERE ProductOptionID=" . $optionID);
			while($thisChoiceName = $this->_dbCmd->GetValue())
				$dbChoiceNamesArr[] = $thisChoiceName;

			foreach($dbChoiceNamesArr as $thisChoiceName){
				if(!$this->checkIfOptionChoiceExists($thisOptionObj->optionName, $thisChoiceName))
					$this->_dbCmd->Query("DELETE FROM productoptionchoices WHERE ProductOptionID=" . $optionID . " AND ChoiceName  LIKE '" . DbCmd::EscapeLikeQuery($thisChoiceName) . "'");
			}
			
		}
		
		
		// Delete Option Names from the DB if they don't exist on this Product anymore.
		// First get a list of all unique Choice Names currently stored in the database.
		$dbOptionNamesArr = array();
		$this->_dbCmd->Query("SELECT OptionName FROM productoptions WHERE ProductID=" . $this->_productID);
		while($thisOptionName = $this->_dbCmd->GetValue())
			$dbOptionNamesArr[] = $thisOptionName;

		foreach($dbOptionNamesArr as $thisOptionName){
			if(!$this->checkIfProductOptionExists($thisOptionName))
				$this->_dbCmd->Query("DELETE FROM productoptions WHERE ProductID=" . $this->_productID . " AND OptionName  LIKE '" . DbCmd::EscapeLikeQuery($thisOptionName) . "'");
		}
		
		
		
		// Find out if any changes have been made to the Blob data. If so update the values to the DB.
		if($this->_templatePreviewMaskPNG_changedFlag)
			$this->_dbCmd->UpdateQuery("products", array("TemplatePreviewMaskPNG"=>$this->_templatePreviewMaskPNG), ("ID=" . $this->_productID));
		
		if($this->_thumbnailCopyIconJPG_changedFlag)
			$this->_dbCmd->UpdateQuery("products", array("ThumbnailCopyFileSize"=>$this->_thumbnailCopyFileSize, "ThumbnailCopyIconJPG"=>$this->_thumbnailCopyIconJPG), ("ID=" . $this->_productID));
			
		if($this->_thumbnailBackJPG_changedFlag)
			$this->_dbCmd->UpdateQuery("products", array("ThumbBackWidth"=>$this->_thumbnailBackWidth, "ThumbBackHeight"=>$this->_thumbnailBackHeight, "ThumbnailBackJPG"=>$this->_thumbnailBackJPG, "ThumbnailBackFileSize"=>$this->_thumbnailBackFileSize), ("ID=" . $this->_productID));
	
		if($this->tempPrevBackLandscapeJPG_changedFlag)
			$this->_dbCmd->UpdateQuery("products", array("TempPrevBackLandscapeJpgWidth"=>$this->tempPrevBackLandscapeJpgWidth, "TempPrevBackLandscapeJpgHeight"=>$this->tempPrevBackLandscapeJpgHeight, "TempPrevBackLandscapeJPG"=>$this->tempPrevBackLandscapeJPG, "TempPrevBackLandscapeFileSize"=>$this->tempPrevBackLandscapeFileSize), ("ID=" . $this->_productID));
	
		if($this->tempPrevBackPortraitJPG_changedFlag)
			$this->_dbCmd->UpdateQuery("products", array("TempPrevBackPortraitJpgWidth"=>$this->tempPrevBackPortraitJpgWidth, "TempPrevBackPortraitJpgHeight"=>$this->tempPrevBackPortraitJpgHeight, "TempPrevBackPortraitJPG"=>$this->tempPrevBackPortraitJPG, "TempPrevBackPortraitFileSize"=>$this->tempPrevBackPortraitFileSize), ("ID=" . $this->_productID));
	
		
		// Every Product Should have a "Proof" PDF Profile.  If we have saved a new Product to the Database... this will create a matching PDF Proof profile with default values.
		PDFprofile::createProofProfileIfDoesNotExist($this->_dbCmd, $this->_productID);
	}
	
	
	
	// Return false if the Product ID can not be loaded.
	function loadProductByID($prodID){
	
		$prodID = intval($prodID);
		
		// All though it is a bit painful, don't load * (everything) or it will might fetch blob data when we don't need it.
		$this->_dbCmd->Query("SELECT ProductTitle, ProductTitleExt, ProductStatus, 
					ArtworkCanvasWidth, ArtworkCanvasHeight, ArtworkDPI, ArtworkSidesCount, ArtworkSidesDesc, ArtworkBleedPicas, ArtworkIsEditable,
					ArtworkSweetSpotWidth, ArtworkSweetSpotHeight, ArtworkSweetSpotX, ArtworkSweetSpotY, ArtworkImgUploadEmbedMsg, ArtworkCustomTemplateURL,
					TemplatePreviewScale, TemplatePreviewSweetSpot, UseTemplatesFromProductID, ProductionPiggybackID, ParentProductID,
					MultipleTemplatePreviews, DefaultMultiPreviewID, ProductUnitWeight, PromotionalCommands,
					UserIDofArtworkSetup, ReorderCardSavedNote, ProjectInitSavedNote, ProductImportance, CompatibleProductIDs, 
					VariableDataFlag, MailingServicesFlag, ThumbnailBackFileSize, ThumbBackWidth, ThumbBackHeight, ThumbnailCopyFileSize,
					ArtThumbReorderScale, ArtThumbReorderXcoord, ArtThumbReorderYcoord, DomainID, TemplatePreviewSidesDisplay,
					TempPrevBackLandscapeJpgWidth, TempPrevBackLandscapeJpgHeight, TempPrevBackLandscapeOverlayX, TempPrevBackLandscapeOverlayY, TempPrevBackLandscapeFileSize,
					TempPrevBackPortraitJpgWidth, TempPrevBackPortraitJpgHeight, TempPrevBackPortraitOverlayX, TempPrevBackPortraitOverlayY, TempPrevBackPortraitFileSize,
					ThumbWidth, ThumbHeight, ThumbOverlayX, ThumbOverlayY,
					DefaultProductionDays, DefaultCutOffHour, MaxBoxSize,
					CustInitSub, CustInitBase,
					Vend1InitSub, Vend1InitBase,
					Vend2InitSub, Vend2InitBase,
					Vend3InitSub, Vend3InitBase,
					Vend4InitSub, Vend4InitBase,
					Vend5InitSub, Vend5InitBase,
					Vend6InitSub, Vend6InitBase,
					VendorID1, VendorID2, VendorID3, VendorID4, VendorID5, VendorID6
					FROM products WHERE ID=" . $prodID);
		
		if($this->_dbCmd->GetNumRows() == 0)
			return false;
		
		$row = $this->_dbCmd->GetRow();
		
		$this->_productTitle = $row["ProductTitle"];
		$this->_productTitleExt = $row["ProductTitleExt"];
		$this->_productStatus = $row["ProductStatus"];
		$this->_artworkCanvasWidth = $row["ArtworkCanvasWidth"];
		$this->_artworkCanvasHeight = $row["ArtworkCanvasHeight"];
		$this->_artworkDPI = $row["ArtworkDPI"];
		$this->_artworkImageUploadEmbedMsg = $row["ArtworkImgUploadEmbedMsg"];
		$this->_artworkCustomUploadTemplateURL = $row["ArtworkCustomTemplateURL"];
		$this->_artworkSidesCount = $row["ArtworkSidesCount"];
		$this->_artworkSidesDesc = $row["ArtworkSidesDesc"];
		$this->_artworkBleedPicas = $row["ArtworkBleedPicas"];
		$this->_artworkIsEditable = $row["ArtworkIsEditable"];
		$this->_artworkSweetSpotWidth = $row["ArtworkSweetSpotWidth"];
		$this->_artworkSweetSpotHeight = $row["ArtworkSweetSpotHeight"];
		$this->_artworkSweetSpotX = $row["ArtworkSweetSpotX"];
		$this->_artworkSweetSpotY = $row["ArtworkSweetSpotY"];
		$this->_templatePreviewScale = $row["TemplatePreviewScale"];
		$this->_templatePreviewSweetSpot = ($row["TemplatePreviewSweetSpot"] == "Y" ? true : false);
		$this->_templatePreviewSidesDisplay = $row["TemplatePreviewSidesDisplay"];
		$this->_useTemplatesFromProductID = $row["UseTemplatesFromProductID"];
		$this->_productImportance = $row["ProductImportance"];
		$this->_productionPiggybackID = $row["ProductionPiggybackID"];
		$this->_parentProductID = $row["ParentProductID"];
		$this->_defaultPreviewID = $row["DefaultMultiPreviewID"];
		$this->_productUnitWeight = $row["ProductUnitWeight"];
		$this->_userIDofArtworkSetup = $row["UserIDofArtworkSetup"];
		$this->_reorderCardSavedNote = $row["ReorderCardSavedNote"];
		$this->_projectInitSavedNote = $row["ProjectInitSavedNote"];
		$this->_variableDataFlag = ($row["VariableDataFlag"] == "Y" ? true : false);
		$this->_mailingServicesFlag = ($row["MailingServicesFlag"] == "Y" ? true : false);
		$this->_artThumbReorderScale = $row["ArtThumbReorderScale"];
		$this->_artThumbReorderXcoord = $row["ArtThumbReorderXcoord"];
		$this->_artThumbReorderYcoord = $row["ArtThumbReorderYcoord"];
		$this->_thumbWidth = $row["ThumbWidth"];
		$this->_thumbHeight = $row["ThumbHeight"];
		$this->_thumbnailBackWidth = $row["ThumbBackWidth"];
		$this->_thumbnailBackHeight = $row["ThumbBackHeight"];
		$this->_thumbOverlayX = $row["ThumbOverlayX"];
		$this->_thumbOverlayY = $row["ThumbOverlayY"];
		$this->tempPrevBackLandscapeJpgWidth = $row["TempPrevBackLandscapeJpgWidth"];
		$this->tempPrevBackLandscapeJpgHeight = $row["TempPrevBackLandscapeJpgHeight"];
		$this->tempPrevBackLandscapeOverlayX = $row["TempPrevBackLandscapeOverlayX"];
		$this->tempPrevBackLandscapeOverlayY = $row["TempPrevBackLandscapeOverlayY"];
		$this->tempPrevBackLandscapeFileSize = $row["TempPrevBackLandscapeFileSize"];
		$this->tempPrevBackPortraitJpgWidth = $row["TempPrevBackPortraitJpgWidth"];
		$this->tempPrevBackPortraitJpgHeight = $row["TempPrevBackPortraitJpgHeight"];
		$this->tempPrevBackPortraitOverlayX = $row["TempPrevBackPortraitOverlayX"];
		$this->tempPrevBackPortraitOverlayY = $row["TempPrevBackPortraitOverlayY"];
		$this->tempPrevBackPortraitFileSize = $row["TempPrevBackPortraitFileSize"];
		$this->_defaultProductionDays = $row["DefaultProductionDays"];
		$this->_defaultCutOffHour = $row["DefaultCutOffHour"];
		$this->_maxBoxSize = $row["MaxBoxSize"];
		$this->_thumbnailBackFileSize = $row["ThumbnailBackFileSize"];
		$this->_thumbnailCopyFileSize = $row["ThumbnailCopyFileSize"];
		$this->_domainID = $row["DomainID"];
		
		
		$this->_custInitBase = $row["CustInitBase"];
		$this->_custInitSub = $row["CustInitSub"];
	
		$this->_vendorBasePricesArr[0] = $row["Vend1InitBase"];
		$this->_vendorInitialSubtoalArr[0] = $row["Vend1InitSub"];
		$this->_vendorBasePricesArr[1] = $row["Vend2InitBase"];
		$this->_vendorInitialSubtoalArr[1] = $row["Vend2InitSub"];
		$this->_vendorBasePricesArr[2] = $row["Vend3InitBase"];
		$this->_vendorInitialSubtoalArr[2] = $row["Vend3InitSub"];
		$this->_vendorBasePricesArr[3] = $row["Vend4InitBase"];
		$this->_vendorInitialSubtoalArr[3] = $row["Vend4InitSub"];
		$this->_vendorBasePricesArr[4] = $row["Vend5InitBase"];
		$this->_vendorInitialSubtoalArr[4] = $row["Vend5InitSub"];
		$this->_vendorBasePricesArr[5] = $row["Vend6InitBase"];
		$this->_vendorInitialSubtoalArr[5] = $row["Vend6InitSub"];
		
		
		$this->_promotionalCommandsArr = explode("^", $row["PromotionalCommands"]);		


		// The DB holds multiple Product IDs in the database concatenated by pipe symbols. 
		$this->_multipleTemplatePreviewsArr = array();
		if(!empty($row["MultipleTemplatePreviews"]))
			$this->_multipleTemplatePreviewsArr = explode("|", $row["MultipleTemplatePreviews"]);
			
		$this->_compatibleProductIDsArr = array();
		if(!empty($row["CompatibleProductIDs"]))
			$this->_compatibleProductIDsArr = explode("|", $row["CompatibleProductIDs"]);
			
			
		
		$this->_vendorIDarr = array();
		
		// Vendor ID's Must be in order.  There may be gaps in between the indexes if Vendor 3 is defined but Vendor 2 is not.
		for($i = 1; $i <= 6; $i++){
			
			if(empty($row["VendorID" . $i]))
				$vendorID = null;
			else
				$vendorID = $row["VendorID" . $i];
			
			$this->_vendorIDarr[$i-1] = $vendorID;
		}
		
		$this->_templatePreviewMaskPNG_changedFlag = false;
		$this->_thumbnailCopyIconJPG_changedFlag = false;
		$this->_thumbnailBackJPG_changedFlag = false;
		$this->tempPrevBackLandscapeJPG_changedFlag = false;
		$this->tempPrevBackPortraitJPG_changedFlag = false;
		
		// Just in case Multiple ProductID's get loaded without destroying the object... we should erase the options to keep 1 product from showing options on another.
		$this->_priceAndOptionsLoadedFlag = false;
		$this->_quantityPriceBreaksArr = array();
		$this->_productOptionsArr = array();
		
		$this->_productSwitchesLoadedFlag = false;
		$this->_productSwitchesArr = array();
		
		
		return true;
	
	}
	
	
	
	// Will only load the prices and options from the Database one time... and only if we request it.
	function _loadProductSwitches(){
	
		if($this->_productSwitchesLoadedFlag)
			return;
		
		// Clear out the old switches so that you can repeatadly call this private method without worrying.
		$this->_productSwitchesArr = array();
		
		
		// Load All of the Quantity Price Breaks at the Root Level of the Product
		$this->_dbCmd->Query("SELECT * FROM productswitches WHERE ProductIDsource='" . $this->_productID . "' ORDER BY SwitchTitle ASC, ProductIDtarget ASC");
		while($row = $this->_dbCmd->GetRow()){
		
			$productSwitchObj = new ProductSwitch();
			
			$productSwitchObj->switchTitle = $row["SwitchTitle"];
			$productSwitchObj->switchLinkSubject = $row["SwitchLinkSubject"];
			$productSwitchObj->switchDescription = $row["SwitchDescription"];
			$productSwitchObj->switchDescriptionIsHTML = $row["SwitchDescriptionIsHTML"] == "Y" ? true : false;
		
			// The key is the Target Product ID. This also keeps out duplicates.
			$this->_productSwitchesArr[$row["ProductIDtarget"]] = $productSwitchObj;
		}
		
		$this->_productSwitchesLoadedFlag = true;
	}
	
	
	
	// Will only load the prices and options from the Database one time... and only if we request it.
	function _loadPricesAndOptions(){
	
		if($this->_priceAndOptionsLoadedFlag)
			return;
	
		
		// Clear out the old options so that you can repeatadly call this private method without worrying.
		$this->_quantityPriceBreaksArr = array();
		$this->_productOptionsArr = array();
		
		
		// Load All of the Quantity Price Breaks at the Root Level of the Product
		$this->_dbCmd->Query("SELECT * FROM productquantitybreaks WHERE ProductID='" . $this->_productID . "' ORDER BY Quantity ASC");
		$counter = 0;
		while($row = $this->_dbCmd->GetRow()){
		
			$this->_quantityPriceBreaksArr[$counter] = new QuantityPriceBreak($row["Quantity"]);
			
			
			$this->_quantityPriceBreaksArr[$counter]->PriceAdjustments = new PriceAdjustments($row["CustSub"], $row["CustBase"], 
					array($row["Vend1Sub"], $row["Vend2Sub"], $row["Vend3Sub"], $row["Vend4Sub"], $row["Vend5Sub"], $row["Vend6Sub"]), 
					array($row["Vend1Base"], $row["Vend2Base"], $row["Vend3Base"], $row["Vend4Base"], $row["Vend5Base"], $row["Vend6Base"]));
		
			$counter++;
		}
		
		
		
		// Put Product Options in a a temp array so that we can do a nested query to avoid creating 2 db objects.
		$tempOptionsArr = array();
		$this->_dbCmd->Query("SELECT * FROM productoptions WHERE ProductID='" . $this->_productID . "' ORDER BY Sort ASC");
		
		while($row = $this->_dbCmd->GetRow())
			$tempOptionsArr[] = $row;
		
		$optionCounter = 0;
		foreach($tempOptionsArr as $thisOptionRow){
		
			$this->_productOptionsArr[$optionCounter] = new ProductOption();
			$this->_productOptionsArr[$optionCounter]->optionID = $thisOptionRow["ID"];
			$this->_productOptionsArr[$optionCounter]->optionName = $thisOptionRow["OptionName"];
			$this->_productOptionsArr[$optionCounter]->optionNameAlias = $thisOptionRow["OptionNameAlias"] == NULL ? $thisOptionRow["OptionName"] : $thisOptionRow["OptionNameAlias"];  // Default to the actual Option Name if an alias is not provided
			$this->_productOptionsArr[$optionCounter]->optionDescription = $thisOptionRow["OptionDescription"];
			$this->_productOptionsArr[$optionCounter]->optionDescriptionIsHTMLformat = $thisOptionRow["OptionDescriptionIsHTML"] == "Y" ? true : false;
			$this->_productOptionsArr[$optionCounter]->adminOptionController = $thisOptionRow["AdminOptionController"] == "Y" ? true : false;
			$this->_productOptionsArr[$optionCounter]->artworkSidesController = $thisOptionRow["ArtworkSidesController"] == "Y" ? true : false;
			$this->_productOptionsArr[$optionCounter]->artworkSearchReplaceController = $thisOptionRow["ArtSearchReplaceController"] == "Y" ? true : false;
			$this->_productOptionsArr[$optionCounter]->couponDiscountExempt = $thisOptionRow["CouponDiscountExempt"] == "Y" ? true : false;
			$this->_productOptionsArr[$optionCounter]->variableImageController = $thisOptionRow["VariableImageController"] == "Y" ? true : false;
			$this->_productOptionsArr[$optionCounter]->optionInCommonForProduction = $thisOptionRow["OptionInCommonForProduction"] == "Y" ? true : false;
		



			// Put choices into a Temp Array to avoid doing a nested query
			$tempChoicesArr = array();
			$this->_dbCmd->Query("SELECT * FROM productoptionchoices WHERE ProductOptionID='" . $thisOptionRow["ID"] . "' ORDER BY Sort ASC");
			
			while($row = $this->_dbCmd->GetRow())
				$tempChoicesArr[] = $row;
				
			$choiceCounter = 0;	
			foreach($tempChoicesArr as $thisChoiceRow){
			
				$this->_productOptionsArr[$optionCounter]->choices[$choiceCounter] = new OptionChoice();
				$this->_productOptionsArr[$optionCounter]->choices[$choiceCounter]->ChoiceName = $thisChoiceRow["ChoiceName"];
				$this->_productOptionsArr[$optionCounter]->choices[$choiceCounter]->ChoiceNameAlias = $thisChoiceRow["ChoiceNameAlias"] == NULL ? $thisChoiceRow["ChoiceName"] : $thisChoiceRow["ChoiceNameAlias"]; // Default to the actual Choice Name if an alias is not provided.    
				$this->_productOptionsArr[$optionCounter]->choices[$choiceCounter]->BaseWeightChange = $thisChoiceRow["UnitWeightChange"];
				$this->_productOptionsArr[$optionCounter]->choices[$choiceCounter]->ProjectWeightChange = $thisChoiceRow["ProjectWeightChange"];
				$this->_productOptionsArr[$optionCounter]->choices[$choiceCounter]->artworkSideCount = $thisChoiceRow["ArtworkSideCount"];
				$this->_productOptionsArr[$optionCounter]->choices[$choiceCounter]->artworkStringSearchFor = $thisChoiceRow["ArtStringSearchFor"];
				$this->_productOptionsArr[$optionCounter]->choices[$choiceCounter]->artworkStringReplaceWith = $thisChoiceRow["ArtStringReplaceWith"];
				$this->_productOptionsArr[$optionCounter]->choices[$choiceCounter]->variableImageFlag = $thisChoiceRow["VariableImageFlag"] == "Y" ? true : false;
				$this->_productOptionsArr[$optionCounter]->choices[$choiceCounter]->hideOptionAllFlag = $thisChoiceRow["HideOptionAll"] == "Y" ? true : false;
				$this->_productOptionsArr[$optionCounter]->choices[$choiceCounter]->hideOptionInListsFlag = $thisChoiceRow["HideOptionInLists"] == "Y" ? true : false;
				$this->_productOptionsArr[$optionCounter]->choices[$choiceCounter]->productionAlert = $thisChoiceRow["ProductionAlert"];
				$this->_productOptionsArr[$optionCounter]->choices[$choiceCounter]->ChoiceDescription = $thisChoiceRow["ChoiceDescription"];
				$this->_productOptionsArr[$optionCounter]->choices[$choiceCounter]->ChoiceDescriptionIsHTMLformat = $thisChoiceRow["ChoiceDescriptionIsHTML"] == "Y" ? true : false;
				
				
				
			
			
			
				$this->_productOptionsArr[$optionCounter]->choices[$choiceCounter]->PriceAdjustments = new PriceAdjustments($thisChoiceRow["CustSub"], $thisChoiceRow["CustBase"], 
						array($thisChoiceRow["Vend1Sub"], $thisChoiceRow["Vend2Sub"], $thisChoiceRow["Vend3Sub"], $thisChoiceRow["Vend4Sub"], $thisChoiceRow["Vend5Sub"], $thisChoiceRow["Vend6Sub"]), 
						array($thisChoiceRow["Vend1Base"], $thisChoiceRow["Vend2Base"], $thisChoiceRow["Vend3Base"], $thisChoiceRow["Vend4Base"], $thisChoiceRow["Vend5Base"], $thisChoiceRow["Vend6Base"]));
			
				// Get any quantity price breaks linked to this choice.
				$this->_dbCmd->Query("SELECT * FROM productchoicequantbrks WHERE ProductOptionChoiceID='" . $thisChoiceRow["ID"] . "' ORDER BY Quantity ASC");
				
				$choiceQuanBrkCounter = 0;
				while($chcQuanBrkRow = $this->_dbCmd->GetRow()){
				
					
					$this->_productOptionsArr[$optionCounter]->choices[$choiceCounter]->QuantityPriceBreaksArr[$choiceQuanBrkCounter] = new QuantityPriceBreak($chcQuanBrkRow["Quantity"]);


					$this->_productOptionsArr[$optionCounter]->choices[$choiceCounter]->QuantityPriceBreaksArr[$choiceQuanBrkCounter]->PriceAdjustments = new PriceAdjustments($chcQuanBrkRow["CustSub"], $chcQuanBrkRow["CustBase"], 
							array($chcQuanBrkRow["Vend1Sub"], $chcQuanBrkRow["Vend2Sub"], $chcQuanBrkRow["Vend3Sub"], $chcQuanBrkRow["Vend4Sub"], $chcQuanBrkRow["Vend5Sub"], $chcQuanBrkRow["Vend6Sub"]), 
							array($chcQuanBrkRow["Vend1Base"], $chcQuanBrkRow["Vend2Base"], $chcQuanBrkRow["Vend3Base"], $chcQuanBrkRow["Vend4Base"], $chcQuanBrkRow["Vend5Base"], $chcQuanBrkRow["Vend6Base"]));

					$choiceQuanBrkCounter++;
				}
				
			
				$choiceCounter++;
			}
		
		
			$optionCounter++;
		}
		
			
		
		
		$this->_priceAndOptionsLoadedFlag = true;

	}

	
	// If this project is not variable data then this method will check to see if there is a quantity break defined for the product.
	// Returns true to false;
	function checkIfQuantityIsLegal($quantityToCheck){
	
		if($this->_variableDataFlag)
			return true;
		
		$allQuantitiesArr = $this->getQuantityChoices();
		
		if(!in_array($quantityToCheck, $allQuantitiesArr))
			return false;
		else
			return true;
	}
	
	

	// Returns a unique list of quantity amounts that have been defined for the product. (inside of Quantity Breaks)
	function getQuantityChoices(){
	
		$this->_loadPricesAndOptions();
		
		$quanArr = array();
		
		foreach($this->_quantityPriceBreaksArr as $thisQuanBreakObj)
			$quanArr[] = $thisQuanBreakObj->amount;
	
		return $quanArr;
	}
	
	
	
	function getProductID(){
	
		if(empty($this->_productID))
			throw new Exception("Error in method getProductID. Has not been intialized yet.");
		return $this->_productID;
	}
	
	
	function getProductTitle(){
		return $this->_productTitle;
	}
	

	// We may have Different Product ID's having the same title... but different extentions... like "Banner - 34 Feet" and "Banner - 25 Feet"
	function getProductTitleExtension(){
		return $this->_productTitleExt;
	}
	

	// If there is a product extenstion, then it will be returned (with a dash after the product name)
	// like... "Postcards - Regular"
	function getProductTitleWithExtention(){
		if(empty($this->_productTitleExt))
			return $this->_productTitle;
		else
			return $this->_productTitle . " " . $this->_productTitleExt;
	}
	
	// The vendor number passed into this method is 1 based.
	// If the vendor number has not been defined, this method will return null
	function getVendorID($vendorNum){
		
		if($vendorNum < 1 || $vendorNum > 6)
			throw new Exception("Error in the method Product method getVendorID.  The Vendor Number is out of range.");

		if(!isset($this->_vendorIDarr[$vendorNum - 1]) || empty($this->_vendorIDarr[$vendorNum - 1]))
			return null;
		else
			return $this->_vendorIDarr[$vendorNum - 1];
	}
	// Array is Zero Based with possible 6 elements.
	// There can be gaps in the array indexes if a Vendor #2 does not exist but Vendor #3 does.
	function getVendorIDArr(){
		return $this->_vendorIDarr;
	}
	

	// Returns how many vendors are currently defined on this product.

	function getVendorCount(){
		
		$count = 0;
		
		foreach($this->_vendorIDarr as $thisVendID){
			if(!empty($thisVendID))
				$count++;
		}
		
		return $count;
	
	}
	function getBasePriceCustomer(){
		if(empty($this->_custInitBase))
			return 0;
		else
			return $this->_custInitBase;
	}
	function getInitialSubtotalCustomer(){
		if(empty($this->_custInitSub))
			return 0;
		else
			return $this->_custInitSub;
	}
	
	
	
	// The vendor number passed into this method is 1 based.
	// If no base price has been defined for the vendor Number, then this method will just return 0;
	function getBasePriceVendor($vendorNum){
	
		if($vendorNum < 1 || $vendorNum > 6)
			throw new Exception("Error in the method Product method getBasePriceVendor.  The Vendor Number is out of range.");
			
		if(!isset($this->_vendorBasePricesArr[$vendorNum - 1]))
			return 0;
		else
			return $this->_vendorBasePricesArr[$vendorNum - 1];
	}
	
	// The vendor number passed into this method is 1 based.
	// If no base price has been defined for the vendor Number, then this method will just return 0;
	function getInitialSubtotalVendor($vendorNum){
	
		if($vendorNum < 1 || $vendorNum > 6)
			throw new Exception("Error in the method Product method getInitialSubtotalVendor.  The Vendor Number is out of range.");
			
		if(!isset($this->_vendorInitialSubtoalArr[$vendorNum - 1]))
			return 0;
		else
			return $this->_vendorInitialSubtoalArr[$vendorNum - 1];
	}
	
	function getProductUnitWeight(){
		return $this->_productUnitWeight;
	}
	function getQuantityPriceBreaksArr(){
	
		// Don't load the Pricing details from the DB unless we actually need to.
		$this->_loadPricesAndOptions();
	
		return $this->_quantityPriceBreaksArr;
	}
	
	
	// Returns an array of Quantity Price Breaks on the given Option/Choice name combo.
	// Make sure that the option name and choice name exist before calling this method.
	function getQuantityPriceBreaksArrOnOptionChoice($optionName, $choiceName){
	
		$optionIndex = $this->_getOptionNameIndex($optionName);
		
		if($optionIndex < 0)
			throw new Exception("Error in method getQuantityPriceBreaksArrOnOptionChoice.  The Option Name does not exist.");
		
		$choiceIndex = $this->_getOptionChoiceIndex($optionName, $choiceName);
		
		if($choiceIndex < 0)
			throw new Exception("Error in method getQuantityPriceBreaksArrOnOptionChoice.  The Choice Name does not exist.");
			
		return $this->_productOptionsArr[$optionIndex]->choices[$choiceIndex]->QuantityPriceBreaksArr;

	}
	// returns an array of Product Option Objects.
	function getProductOptionsArr(){
	
		// Don't load the Pricing details from the DB unless we actually need to.
		$this->_loadPricesAndOptions();
	
		return $this->_productOptionsArr;
	}
	
	// Returns an Array of Product Option Names on this product.
	// They Key is just 0 based.
	function getProductOptionNamesArr(){
		
		// Don't load the Pricing details from the DB unless we actually need to.
		$this->_loadPricesAndOptions();
		
		$retArr = array();
		foreach($this->_productOptionsArr as $optionObj)
			$retArr[] = $optionObj->optionName;
		return $retArr;
	}
	
	// Returns the ProductOption object with the Matching Option Name on this product.  Failes with an errof if it doesn't exit.
	/*
	 * @return ProductOption
	 */
	function getOptionObject($optionName){
		
		$optionIndex = $this->_getOptionNameIndex($optionName);
		
		if($optionIndex < 0)
			throw new Exception("Error in method getOptionObject.  The Option Name does not exist.");

		return $this->_productOptionsArr[$optionIndex];
	}
	
	
	
	function isVariableData(){
		if($this->_variableDataFlag == "Y")
			return true;
		else
			return false;
	}
	// If a project has mailing services then it must also be VariableData.
	function hasMailingService(){
		
		if($this->_mailingServicesFlag == "Y")
			return true;
		else
			return false;
	}
	

	// Returns the first Quantity Break that it comes across for this Product.
	// If it can not find a quantity break then it starts out with Zero.
	// Variable Data projects always start out with Zero.
	// If you want to override this default behavior... try setting up a saved Project to pull the default from... such as with the method ... getUserIDofArtworkSetup()
	function getDefaultQuantity(){
		
		if($this->_variableDataFlag)
			return 0;
			
		if($this->_productStatus != "G")
			return 0;
			
		$this->_dbCmd->Query("SELECT MIN(Quantity) FROM productquantitybreaks WHERE ProductID=" . $this->_productID);
		
		$minQuantity = $this->_dbCmd->GetValue();
		
		// The MIN SQL function will return NULL if there is not a value.(and there is always a row returned ... so getNumRows() doest work.
		if(empty($minQuantity))
			$minQuantity = 0;
			
			
		// Will return 0 if there is not a valid UserID of artwork setup With a matching Saved Project Note.
		$savedProjectID = $this->getSavedProjectIDofInitialize();
		$overrideQuantity = null;
		if(!empty($savedProjectID)){
			$this->_dbCmd->Query("SELECT Quantity FROM projectssaved WHERE ID=" . $savedProjectID);
			$overrideQuantity = $this->_dbCmd->GetValue();
		}
		
		if(!empty($overrideQuantity) && $this->checkIfQuantityIsLegal($overrideQuantity))
			return $overrideQuantity;
		else	
			return $minQuantity;
	}
	
	// Returns the default choice for an option name.
	// Setup the UserID of Saved Projects if you wan to override the default behavior
	// Otherwise it will return the First Choice in each Option.  
	// Return NULL if the Option Name does not exist or if there are no choices for the Option
	function getDefaultChoiceForOption($optionName){
		
		$optionIndex = $this->_getOptionNameIndex($optionName);
		
		if($optionIndex < 0)
			return null;
			
		if(sizeof($this->_productOptionsArr[$optionIndex]->choices) == 0)
			return null;
			
		// In case there are many options on a Product, no sense in querying the database for each option name.
		if(array_key_exists($optionName, $this->_defaultProjectOptionsArr_cached))
			return $this->_defaultProjectOptionsArr_cached[$optionName];
			
		// Will return 0 if there is not a valid UserID of artwork setup With a matching Saved Project Note.
		// Try to get the default options from the Saved Project.
		$savedProjectID = $this->getSavedProjectIDofInitialize();
		if(!empty($savedProjectID)){
			$this->_dbCmd->Query("SELECT OptionsDescription FROM projectssaved WHERE ID=" . $savedProjectID);
			
			// Store options/choices from the Saved Project init into our object for caching.
			$this->_defaultProjectOptionsArr_cached = ProjectInfo::getProductOptionHashFromDelimitedString($this->_dbCmd->GetValue());
			
			// Just because the Saved Project has certain options/choices saved doesn't mean that the choices are still valid.
			if(array_key_exists($optionName, $this->_defaultProjectOptionsArr_cached))
				return $this->_defaultProjectOptionsArr_cached[$optionName];
		}

		// If all else fails, return the first choice in the Option.
		return $this->_productOptionsArr[$optionIndex]->choices[0]->ChoiceName;		
	}
	
	function getArtworkDPI(){
		return $this->_artworkDPI;
	}
	
	function getArtworkImageUploadEmbedMsg(){
		return $this->_artworkImageUploadEmbedMsg;
	}
	function getArtworkCustomUploadTemplateURL(){
		return $this->_artworkCustomUploadTemplateURL;
	}
	
	
	// Value is in Inches
	function getArtworkCanvasWidth(){
		
		if(empty($this->_artworkCanvasWidth))
			return 0;
		return $this->_artworkCanvasWidth;
	}
	// Value is in Inches
	function getArtworkCanvasHeight(){

		if(empty($this->_artworkCanvasWidth))
			return 0;
		return $this->_artworkCanvasHeight;
	}
	
	function getArtworkSidesCount(){
		
		// This shouldn't happen... unless the product information hasn't been saved to the DB yet.
		if(empty($this->_artworkSidesCount))
			return 1;
			
		return $this->_artworkSidesCount;
	}
	
	// Side Number is 1 based.
	// Will return a string like Front or Back
	// If the Side Number has not been defined yet it will default to "Front" for side 1, "Back" for side 2, and then "Side X" for anything further.
	function getArtworkSideDescription($sideNum){
		
		$sideNum = intval($sideNum);
		
		$sideDescArr = explode("\|", $this->_artworkSidesDesc);
		
		if($sideNum < 1)
			throw new Exception("Error in method getArtworkSideDescription.  The Side number must be greater than or equal to 1");
			
		if($sideNum > $this->getArtworkSidesCount())
			return "Side Description Unknown";
		
		if($sideNum == 1){
			if(!isset($sideDescArr[0]) || empty($sideDescArr[0]))
				return "Front";
			else
				return $sideDescArr[0];
		}
		else if($sideNum == 2){
			if(!isset($sideDescArr[1]) || empty($sideDescArr[1]))
				return "Back";
			else
				return $sideDescArr[1];
		}
		
		if(!isset($sideDescArr[$sideNum - 1]) || empty($sideDescArr[$sideNum - 1]))
			return "Side " . $sideNum;
		else
			return $sideDescArr[$sideNum - 1];
	}
	

	
	function getArtworkBleedPicas(){
		return $this->_artworkBleedPicas;
	}
	
	// Returns true or false
	function getArtworkIsEditable(){
		return ($this->_artworkIsEditable == "Y") ? true : false;
	}
	
	// "G"ood   ... "D"isabled ... or "N"ew
	function getProductStatus(){
		return $this->_productStatus;
	}
	
	// Will be null if there is no user ID of artwork setup
	function getUserIDofArtworkSetup(){
		return $this->_userIDofArtworkSetup;
	}
	
	function getReorderCardSavedNote(){
		return $this->_reorderCardSavedNote;
	}
	
	function getProjectInitSavedNote(){
		return $this->_projectInitSavedNote;
	}
	
	// Returns the Width in pixels of the Thumbnail image users will see in their shopping cart (the overlay part).  If there is a background image for the thumbnail then it will be larger.
	function getThumbWidth(){
	
		return $this->_thumbWidth;
	}
	function getThumbHeight(){
	
		return $this->_thumbHeight;
	}
	
	
	// Returns the Width in pixels of the Thumbnail Background
	// If there is no thumbnail Background, then these value will return Zero
	function getThumbBackgroundWidth(){
		if(empty($this->_thumbnailBackWidth))
			return 0;
		return $this->_thumbnailBackWidth;
	}
	function getThumBackgroundbHeight(){
		if(empty($this->_thumbnailBackHeight))
			return 0;
		return $this->_thumbnailBackHeight;
	}
	

	// Returns the amount of the pixels to lay the thumbnail image on top of the thumbnail background (from the bottom left)
	function getThumbOverlayX(){
	
		return $this->_thumbOverlayX;
	}
	function getThumbOverlayY(){
	
		return $this->_thumbOverlayY;
	}
	
	
	
	// Returns the Width in pixels of the Template Preview Background JPG
	// If there is no thumbnail Background, then these value will return Zero
	function getTempPrevBackLandscapeJpgWidth(){
		if(empty($this->tempPrevBackLandscapeJpgWidth))
			return 0;
		return $this->tempPrevBackLandscapeJpgWidth;
	}
	function getTempPrevBackLandscapeJpgHeight(){
		if(empty($this->tempPrevBackLandscapeJpgHeight))
			return 0;
		return $this->tempPrevBackLandscapeJpgHeight;
	}
	
	function getTempPrevBackLandscapeOverlayX(){
		return $this->tempPrevBackLandscapeOverlayX;
	}
	function getTempPrevBackLandscapeOverlayY(){
		return $this->tempPrevBackLandscapeOverlayY;
	}
	
	function getTempPrevBackPortraitJpgWidth(){
		if(empty($this->tempPrevBackPortraitJpgWidth))
			return 0;
		return $this->tempPrevBackPortraitJpgWidth;
	}
	function getTempPrevBackPortraitJpgHeight(){
		if(empty($this->tempPrevBackPortraitJpgHeight))
			return 0;
		return $this->tempPrevBackPortraitJpgHeight;
	}
	
	function getTempPrevBackPortraitOverlayX(){
		return $this->tempPrevBackPortraitOverlayX;
	}
	function getTempPrevBackPortraitOverlayY(){
		return $this->tempPrevBackPortraitOverlayY;
	}
	
	
	
	
	function getMaxBoxSize(){
	
		return $this->_maxBoxSize;
	}
	function getDomainID(){
		return $this->_domainID;
	}
	
	function getArtworkSweetSpotWidth(){
		return $this->_artworkSweetSpotWidth;
	}
	function getArtworkSweetSpotHeight(){
		return $this->_artworkSweetSpotHeight;
	}
	
	// Width/Height/X/Y are all measured in Inches.
	// The X/Y coordinates are relative to the center point on the canvas.
	function getArtworkSweetSpotX(){
		return $this->_artworkSweetSpotX;
	}
	function getArtworkSweetSpotY(){
		return $this->_artworkSweetSpotY;
	}
	
	
	// Returns an empty array if no values.
	// Make sure that the Customer is not a Reseller before issueing these commands through an API.
	function getPromotionalCommandsArr(){
		return $this->_promotionalCommandsArr;
	}
	
	
	
	// Some Products Generate Multiple Preview Images per Template
	// For example, for Envelopes we only create Double-Sided Templates... The Single-Sided Preview is Automatically created
	// If there are not multiple... then it will just return an empty array
	// If there are multiple... this products own ProductID is included within the array.
	function getMultipleTemplatePreviewsArr(){
	
		// Before we return the array... Make sure that all of the products saved for the multiple template previews are pointing to this product for tempalte sharing.
		// It could be that the administrator stopped sharing on the other Products without updating this product.
		$returnArr = array();
		
		foreach($this->_multipleTemplatePreviewsArr as $thisProdID){
		
			$productObj = new Product($this->_dbCmd, $thisProdID);

			if($productObj->getProductIDforTemplates() != $this->_productID)
				continue;
			
			$returnArr[] = $thisProdID;
		}
		
		if(!empty($returnArr))
			$returnArr[] = $this->_productID;
		
		return $returnArr;
	}
	
	
	
	// Returns a list of Active Product IDs that this Product has deemed compatible.
	// The list will be returned in Order of Product Importance.
	function getCompatibleProductIDsArr(){
	
		// Before we return the array... Make sure that all of the products deemed compatible are still active in the system.
		// It could be that a product was de-activated after this Product chose it to be compatible.
		// Also, make sure that the Product ID is still a "Main Product ID"... meaning that it didn't change to have a parent.
		$returnArr = array();
		$mainProductIDArr = array();
		
		// Get a list of Active Products, sorted by importance... and selectively include the Products if they exist in our List of Compatible Products.
		$this->_dbCmd->Query("SELECT ID FROM products WHERE ProductStatus='G' AND ParentProductID IS NULL ORDER BY ProductImportance DESC, ProductTitle ASC, ProductTitleExt ASC");
		while($thisMainProdID = $this->_dbCmd->GetValue())
			$mainProductIDArr[] = $thisMainProdID;
		
		foreach($mainProductIDArr as $thisMainProdID){

			if(!Product::checkIfProductIDisActive($this->_dbCmd, $thisMainProdID))
				continue;
	
			if(!in_array($thisMainProdID, $this->_compatibleProductIDsArr))
				continue;
				
			$returnArr[] = $thisMainProdID;
		}
		
		return $returnArr;
	}
	
	
	


	// We may want to share a group of templates/functions between multiple products
	// For example Variable data Postcards should have the same choices as Static Postcards for tempaltes
	// In which case, both a Product Object for Variable & Static will return the same product ID 
	// If this Product does not have a link to another product then this method will return the product ID of itself.
	function getProductIDforTemplates(){
		
		if(empty($this->_useTemplatesFromProductID))
			return $this->_productID;
		else
			return $this->_useTemplatesFromProductID;
	}
	
	// Returns a number between 0 and 100
	function getProductImportance(){	
		return $this->_productImportance;
	}
	
	function getUseTemplatesFromProductID(){
		return $this->_useTemplatesFromProductID;
	}
	
	
	// Sometimes different products are very closely related and there is no point in defining separate production routines within the manufacturing plant.  
	// From a production standpoint, there may be no difference between a 5.5" x 8.5" Greeting Card and a large postcard.
	// In case the value for productionPiggybackID has not been set... then this method will return the name of the current product ID (itself)
	function getProductionProductID(){
	
		if(empty($this->_productionPiggybackID))
			return $this->_productID;
		else
			return $this->_productionPiggybackID;
	}
	
	
	// This does not return a value if the Production Piggyback does not link to another product.
	function getProductionPiggybackID(){
		return $this->_productionPiggybackID;
	}
	
	
	

	// Products can be closely related and in some circumstances there is no point in listing all of the derivatives.
	// For example, 4"x 6" Postcards, 5.5"x 8.5" Postcards, and 4"x 6" Variable Data Postcards you should have one main product ID within the group and the other 2 should point to it as their parent.
	// This information can be used on Coupon Bundles.  For example... if a coupon requires that Postcards and Business Cards get purchased together to receive a discount it shouldn't matter what flavor of postcards they are buying.
	// The main product ID may also be used for artwork transfers. If someone wants to convert there business card into a postcard there is no point in showing them every derivitive of postcards in the system.  After the transfer the user can refine the type of postcard that they want to order.

	function getMainProductID(){
	
		if(empty($this->_parentProductID))
			return $this->_productID;
		else
			return $this->_parentProductID;

	}
	
	// Get the parent product ID... returns 0 if no parent.
	function getParentProductID(){
		
		if(empty($this->_parentProductID))
			return 0;
		else
			return $this->_parentProductID;
	}

	

	
	
	// Returns the Thumanil Copy icon in binary format, by reference.
	// If an image does not exist then it will return null.
	function &getThumbnailCopyIconJPG(){
		
		// Don't try to load this binary image from the DB until this method is actually called to save wasted resources.
		$this->_dbCmd->Query("SELECT ThumbnailCopyIconJPG FROM products WHERE ID=" . $this->_productID);
		$jpg = $this->_dbCmd->GetValue();
		
		if(empty($jpg)){
			$emptyVar = null;
			return $emptyVar;
		}
		
		return $jpg;
	}


	// Returns the background image that the thumbnail preview will be layed on top of, by reference.
	// If an image does not exist then it will return null.
	function &getThumbnailBackJPG(){
		
		// Don't try to load this binary image from the DB until this method is actually called to save wasted resources.
		$this->_dbCmd->Query("SELECT ThumbnailBackJPG FROM products WHERE ID=" . $this->_productID);
		$jpg = $this->_dbCmd->GetValue();
		
		if(empty($jpg))
			return null;
		
		return $jpg;
	}
	
	// Returns the background image that the Template preview will be layed on top of, by reference.
	// If an image does not exist then it will return null.
	// Don't try to load this binary image from the DB until this method is actually called to save wasted resources.
	function getTempPrevBackLandscapeJPG(){
		$this->_dbCmd->Query("SELECT TempPrevBackLandscapeJPG FROM products WHERE ID=" . $this->_productID);
		$jpg = $this->_dbCmd->GetValue();
		
		if(empty($jpg))
			return null;
		
		return $jpg;
	}
	function getTempPrevBackPortraitJPG(){
		$this->_dbCmd->Query("SELECT TempPrevBackPortraitJPG FROM products WHERE ID=" . $this->_productID);
		$jpg = $this->_dbCmd->GetValue();
		
		if(empty($jpg))
			return null;
		
		return $jpg;
	}
	
	


	// When Generating a Template Preview Image for a product... we may want to lay a marker image on top of the design if the canvas area is not a rectangle.
	// This method will return Null (if image is not loaded), or it will return a patch to an image on the disk.
	function &getTemplatePreviewMaskPNG($returnTempFileOnDiskFlag = false){

		// Don't try to load this binary image from the DB until this method is actually called to save wasted resources.
		$this->_dbCmd->Query("SELECT TemplatePreviewMaskPNG FROM products WHERE ID=" . $this->_productID);
		$previewImg = $this->_dbCmd->GetValue();

		if(empty($previewImg)){
			$nullValue = null;
			return $nullValue; // Return by reference
		}
		
		if($returnTempFileOnDiskFlag){
			//Check and see if the image is already on the disk. We can see if the image has changed becuase the MD5 will be different.
			$pathToOverlayImage = Constants::GetTempDirectory() . "/PREVOVER_" . md5($previewImg);

			if(!file_exists($pathToOverlayImage)){
				$fp = fopen($pathToOverlayImage, "w");
				fwrite($fp, $previewImg);
				fclose($fp);
			}

			return $pathToOverlayImage;
		}
		else{
			return $previewImg;
		}
	}



	// How much should the "Template Preview" image be scaled down from the original artwork.
	// As an option you can pass in a category Name that the template belongs to (if it is in a category and not the search engine)
	// Scale is relative to 96 DPI... so 100% scale would be the size that users see artwork within the editing tool when the Zoom is set at 100%
	function getTemplatePreviewScale($categoryName = ""){
	
		// The Backside for postcard templates are a little larger than the others so people can see fields a bit easier.
		// BACK is a special keyword for template categories that general users will never see...so it doesn't matter about language, etc.   An administrator may define a template category called BACK.
		if(preg_match("/^BACK/i", $categoryName))
			return round($this->_templatePreviewScale * 0.15) + $this->_templatePreviewScale;
	
		return $this->_templatePreviewScale;
	}
	
	// Returns true or false whether template previews should be restricted to display the area within an artwork sweet spot.
	function getTemplatePreviewSweetSpot(){
		return $this->_templatePreviewSweetSpot;
	}
	
	// Returns a character... either 'M'ultiple sides ... or 'F'irst side only.
	function getTemplatePreviewSidesDisplay(){
		return $this->_templatePreviewSidesDisplay;
	}
	
	
	
	// Returns an array of of Product Options that must be selected (in common) to start up a batch for production.
	// For example, array("Card Stock", "Postage Type") would mean that all projects in a production run have to be grouped so they are "Glossy" and have "First class" postage.
	function getOptionsInCommonForProduction(){
	
		$retArr = array();
		
		$this->_dbCmd->Query("SELECT OptionNameAlias FROM productoptions WHERE OptionInCommonForProduction='Y' AND ProductID=" . $this->_productID);
		while($theOptionName = $this->_dbCmd->GetValue())
			$retArr[] = $theOptionName;
	
		return $retArr;
	}


	// It is Possible that a Template could have Previews generated for Multiple Product IDs
	// When the Template Preview Page loads for the first time... what Product ID should be displayed (by default for the Templates)
	// Sometimes the use may be able to change the type of ProductID view of the templates
	// For Example... Double Sided Envelopes create Template Previews for Both Single-Sided and the Full double-sided canvas.... by default, we only view the Single-sided first because they are smaller.   The user could choose to select the full Double-sided view if they wanted.
	// In case the multiple preview IDs are not saved then this method will just return the product ID of the current object (itself)
	function getdefaultProductIDforTemplatePreview(){
	
		$multiplePreviewsArr = $this->getMultipleTemplatePreviewsArr();
		
		if(empty($this->_defaultPreviewID) || empty($multiplePreviewsArr))
			return $this->getProductIDforTemplates();
		
		// Make sure that the default Preview ID is a product that is pointing to this one as Template Sharing.
		// Otherwise an administrator could have updated the product (to stop sharing) without modifying this setting.
		if(!in_array($this->_defaultPreviewID, $multiplePreviewsArr))
			return $this->getProductIDforTemplates();
		else
			return $this->_defaultPreviewID;
	}
	
	
	function getScaleOfThumbnailImageOnReorderCard(){
		return $this->_artThumbReorderScale;
	}
	function getXcoordOfThumbnailImageOnReorderCard(){
		return $this->_artThumbReorderXcoord;
	}
	function getYcoordOfThumbnailImageOnReorderCard(){
		return $this->_artThumbReorderYcoord;
	}						


	// Returns an array of ProductIDs that point to this Product for their Template Sharing
	function getProductsPointingToThisOneForTemplateSharing(){
	
		$retArr = array();
		
		$this->_dbCmd->Query("SELECT ID FROM products WHERE UseTemplatesFromProductID =" . $this->_productID);
		while($prodID = $this->_dbCmd->GetValue())
			$retArr[] = $prodID;
	
		return $retArr;	
	}


	// If there is not a saved Project with a matching note/UserID then this method will return 0.  Otherwsie the projectssaved ID.
	function getSavedProjectIDofReorderCard(){
	
		if(empty($this->_reorderCardSavedNote) || empty($this->_userIDofArtworkSetup))
			return 0;
		
		$this->_dbCmd->Query("SELECT ID FROM projectssaved WHERE UserID=" . intval($this->_userIDofArtworkSetup) . " AND Notes LIKE \"" . DbCmd::EscapeLikeQuery($this->_reorderCardSavedNote) . "\"");
		
		if($this->_dbCmd->GetNumRows() == 0)
			return 0;
		
		return $this->_dbCmd->GetValue();
	}

	// If there is not a saved Project with a matching note/UserID then this method will return 0.  Otherwsie the projectssaved ID.
	function getSavedProjectIDofInitialize(){
	
		if(empty($this->_projectInitSavedNote) || empty($this->_userIDofArtworkSetup))
			return 0;
			
		$this->_dbCmd->Query("SELECT ID FROM projectssaved WHERE UserID=" . intval($this->_userIDofArtworkSetup) . " AND Notes LIKE \"" . DbCmd::EscapeLikeQuery($this->_projectInitSavedNote) . "\"");
	
		if($this->_dbCmd->GetNumRows() == 0)
			return 0;
		
		return $this->_dbCmd->GetValue();
	}
	
	function getDefaultProductionDays(){
	
		return $this->_defaultProductionDays;
	
	}
	
	function getDefaultCutOffHour(){
	
		return $this->_defaultCutOffHour;
	
	}
	
	
	// Gets the number of production days for this product based upon the Shipping ID.
	// If there is not an override value for the Product/Shipping combo ... it will return NULL.

	function getProductionDaysOverride($shippingChoiceID){
		
		$this->_dbCmd->Query("SELECT ProductionDays FROM productproductiondays WHERE ProductID=" . $this->_productID . " AND ShippingChoiceID = \"" . intval($shippingChoiceID) . "\"");
		return $this->_dbCmd->GetValue();
	}
	

	// Gets the cut off hour for this product based upon the Shipping ID.
	// If there is not an override value for the Product/Shipping combo ... it will return NULL.
	function getProductionHourOverride($shippingChoiceID){
		
		$this->_dbCmd->Query("SELECT CutOffHour FROM productcutoffhour WHERE ProductID=" . $this->_productID . " AND ShippingChoiceID = \"" . intval($shippingChoiceID) . "\"");
		return $this->_dbCmd->GetValue();
	}
	
	// If there is not an Override Value for the shipping ID... then this method will return the Default Value for the product.
	function getProductionDays($shippingChoiceID){
	
		$overrideVal = $this->getProductionDaysOverride($shippingChoiceID);
		
		if(empty($overrideVal))
			return $this->getDefaultProductionDays();
		else
			return $overrideVal;
	
	}
	
	// If there is not an Override Value for the shipping ID... then this method will return the Default Value for the product.
	function getCutOffHour($shippingChoiceID){
	
		$overrideVal = $this->getProductionHourOverride($shippingChoiceID);
		
		if(empty($overrideVal))
			return $this->getDefaultCutOffHour();
		else
			return $overrideVal;
	
	}
	
	
	
	// This method will return an Artwork XML file.
	// If there is a matching User ID for Artwork setup with a saved Project Note, this method will just return whatever artwork file is stored there.
	// Otherwise, this method will build a blank artwork file and return that. It will do so using the artwork dimensions specified.
	// If this Product is set to have 2 sides, or 4 sides... this method will aways return an artwork with the maximum number of sides.
	// If the UserID of Artwork Setup has 1 side saved... but the product is capable or 2 sides... then it will create a default back side (by cloning the Front and deleting all layers).
	function getDefaultProductArtwork(){
	
		$artworkFileFromSavedArtworkSetup = null;
	
		// Will return 0 if there is not a valid UserID of artwork setup With a matching Saved Project Note.
		$savedProjectID = $this->getSavedProjectIDofInitialize();
		
		if(!empty($savedProjectID)){
			$this->_dbCmd->Query("SELECT ArtworkFile FROM projectssaved WHERE ID=" . $savedProjectID);
			$artworkFileFromSavedArtworkSetup = $this->_dbCmd->GetValue();
		}
		
		
		if(!empty($artworkFileFromSavedArtworkSetup)){	
			$artFile = $artworkFileFromSavedArtworkSetup;
		}
		else{
		
			// Figure out a good "default zoom" based upon the canvas width
			// You can always save a Project Init Saved Project and override this value.
			// Figure that a business cards at 3.5" width looks good at 150% zoom in the editing tool.
			// An 11" widht looks good at a 60% zoom in the editing tool.  So let's calculate a ratio between those 2 points.
			$cw = $this->getArtworkCanvasWidth();
			if($cw > 20)
				$defaultZoom = 30;
			else if($cw > 15)
				$defaultZoom = 40;
			else if($cw > 12)
				$defaultZoom = 50;
			else if($cw > 9)
				$defaultZoom = 60;
			else if($cw > 6)
				$defaultZoom = 90;
			else if($cw > 4)
				$defaultZoom = 100;
			else if($cw > 3.6)
				$defaultZoom = 120;
			else if($cw > 2.5)
				$defaultZoom = 150;
			else
				$defaultZoom = 200;
		
			$artFile = "<?xml version=\"1.0\" ?>
			<content>
			<side>
			<description>Side Name (to be changed)</description>
			<initialzoom>" . $defaultZoom . "</initialzoom>
			<rotatecanvas>0</rotatecanvas>
			<contentwidth>" . ($this->getArtworkCanvasWidth() * 96) . "</contentwidth>
			<contentheight>" . ($this->getArtworkCanvasHeight() * 96) . "</contentheight>
			<backgroundimage>0</backgroundimage>
			<background_x>0</background_x>
			<background_y>0</background_y>
			<background_width>0</background_width>
			<background_height>0</background_height>
			<background_color>16777215</background_color>
			<scale>100</scale>
			<folds_horiz>0</folds_horiz>
			<folds_vert>0</folds_vert>
			<dpi>" . $this->getArtworkDPI() . "</dpi>
			<show_boundary>yes</show_boundary>
			<side_permissions></side_permissions>
			<color_definitions>
			</color_definitions>
			</side>
			</content>
			";
		}
		
		$artInfoObj = new ArtworkInformation($artFile);

		// Now start cloning the First Side to all remaning sides (if there isn't a definition yet).
		// This cloning process can be overridden by creating sides on the Project Init Saved Project
		for($i=2; $i<= $this->getArtworkSidesCount(); $i++){
		
			if(!isset($artInfoObj->SideItemsArray[($i-1)])){
			
				// Make a "deep copy" of the front side to avoid Object Reference Problems.
				$frontSideCopy = unserialize(serialize($artInfoObj->SideItemsArray[0]));

				//This will erase the layers... images and text... we don't want to copy that to the cloned sides.
				$frontSideCopy->layers = array();
				
				$artInfoObj->SideItemsArray[($i-1)] = $frontSideCopy;
			}
			
			// Make sure the language is updated for that side number... such as Front / Back
			$artInfoObj->SideItemsArray[($i-1)]->description = $this->getArtworkSideDescription($i);
		}
		
		// Make sure Side 1 has the correct side name too
		$artInfoObj->SideItemsArray[0]->description = $this->getArtworkSideDescription(1);
		
		
		
		// In case there are more sides on the Project Init artwork than we have defined side for... get rid of the extra sides.
		// This could also happen if the Flash App adds too many sides.  The integrity is controlled by the peron's browser so we don't have 100% control over it.
		$sideDiff = sizeof($artInfoObj->SideItemsArray) - $this->getArtworkSidesCount();
		if($sideDiff > 0){
			for($i=0; $i < $sideDiff; $i++)
				array_pop($artInfoObj->SideItemsArray);
		}
		
		
		return $artInfoObj->GetXMLdoc();
	}
	
	
	// If this product is configured for generating Multiple Template Previews... the user may have the option (when browsing template) to switch between the different Preview Images for corresponding Product IDs
	// This message is saved in the Backend... and displayed to the user on the Front-end.
	// The ProductID must match one of the "multi template preview productID's" stored on this product.  If not, it will return a null string.
	function getMultiTemplatePreviewSwitchDesc($targetProductID){
	
		$multiplePreviewsArr = $this->getMultipleTemplatePreviewsArr();
		
		if(!in_array($targetProductID, $multiplePreviewsArr))
			return null;
		
		$targetProductID = intval($targetProductID);
		
		$this->_dbCmd->Query("SELECT MultiTemplatePreviewSwitchDesc FROM productmessages WHERE SourceProductID=" . $this->_productID . " AND TargetProductID=" . $targetProductID . " AND MultiTemplatePreviewSwitchDesc IS NOT NULL");
		
		return $this->_dbCmd->GetValue();	
	}
	
	
	
	// Get an Array of Product ID's that this Product is able to be switched into.
	function getProductSwitchIDs(){
	
		$this->_loadProductSwitches();
		
		$retArr = array();
		
		$activeProductIDs = Product::getActiveProductIDsArr($this->_dbCmd, $this->_domainID);
		
		$productSwitchTargesArr = array_keys($this->_productSwitchesArr);
		
		foreach($productSwitchTargesArr as $thisTargeProductID){
		
			// Make sure not to include Products that have been deactivated.
			if(!in_array($thisTargeProductID, $activeProductIDs))
				continue;
	
			$retArr[] = $thisTargeProductID;
		}
			
		return $retArr;
	}

	// ----  Get all of the Details from the Target of a Product Switch ID.----
	
	function getProductSwitchTitle($targetProdID){
	
		$this->_loadProductSwitches();
		
		if(!array_key_exists($targetProdID, $this->_productSwitchesArr))
			throw new Exception("Error in method getProductSwitchTitle. The ProductID Switch has not been defined on this product..");
		
		return $this->_productSwitchesArr[$targetProdID]->switchTitle;
	}
	function getProductSwitchLinkSubject($targetProdID){
	
		$this->_loadProductSwitches();
		
		if(!array_key_exists($targetProdID, $this->_productSwitchesArr))
			throw new Exception("Error in method getProductSwitchLinkSubject. The ProductID Switch has not been defined on this product..");
		
		return $this->_productSwitchesArr[$targetProdID]->switchLinkSubject;
	}
	function getProductSwitchDescription($targetProdID){
	
		$this->_loadProductSwitches();
		
		if(!array_key_exists($targetProdID, $this->_productSwitchesArr))
			throw new Exception("Error in method getProductSwitchDescription. The ProductID Switch has not been defined on this product..");
		
		return $this->_productSwitchesArr[$targetProdID]->switchDescription;
	}
	function getProductSwitchDescriptionIsHTML($targetProdID){
	
		$this->_loadProductSwitches();
		
		if(!array_key_exists($targetProdID, $this->_productSwitchesArr))
			throw new Exception("Error in method getProductSwitchDescriptionIsHTML. The ProductID Switch has not been defined on this product..");
		
		return $this->_productSwitchesArr[$targetProdID]->switchDescriptionIsHTML;
	}

	// Returns true or false whether or not this Product is grouped with another product (because the Product Switch Title Matches).
	function isProductSwitchInGroup($targetProdID){
	
		$this->_loadProductSwitches();
		
		$thisSwitchTitle = $this->getProductSwitchTitle($targetProdID);
		
		$matchingTitlecounter=0;
		
		foreach($this->_productSwitchesArr as $switchObj){
			if($switchObj->switchTitle == $thisSwitchTitle)
				$matchingTitlecounter++;
		}
		
		if($matchingTitlecounter > 1)
			return true;
		else
			return false;
	}



	// Returns the Option Object matching the option name.
	// If the option name does not exist in the product... this method will return NULL.
	/**
	 * @param string $optionName
	 * @return ProductOption
	 */
	function getOptionDetailObj($optionName) {
	
		$productOptionsArr = $this->getProductOptionsArr();
		
		foreach($productOptionsArr as $productOptionObj){
		
			if($productOptionObj->optionName == $optionName)
				return $productOptionObj;
		}

		return null;
	}


	// Returns a Choice Object for the given Option and Choice Name
	// returns NULL if the Option Does or if the Choice does not exist within the Option.
	/**
	 * @param string $optionName
	 * @param string $choiceName
	 * @return OptionChoice
	 */
	function getChoiceDetailObj($optionName, $choiceName) {
	
	
		$optionObj = $this->getOptionDetailObj($optionName);
		if($optionObj == NULL)
			return NULL;
			

		foreach($optionObj->choices as $choiceObj){
			if($choiceObj->ChoiceName == $choiceName)
				return $choiceObj;
		}

		return null;
	}

	// ----------------------  Set Methods -------------------------------------------

	
	function setProductTitle($x){
		$x = trim($x);
		
		if(strlen($x) > 70)
			throw new Exception("Error with product title, can not exceed 70 characters");
		
		if(empty($x))
			throw new Exception("Every product must have a title.  Can not be blank.");
		
		$this->_productTitle = $x;
	}
	
	function setProductTitleExtension($x){
		$x = trim($x);
		
		if(strlen($x) > 50)
			throw new Exception("Error with product title extension, can not exceed 50 characters");
		
		$this->_productTitleExt = $x;
	}
	
	
	// Set the Product Unit Weight in Pounds.  Such as the weight of a single business card.
	// It is also possible to set the Weight to Zero here and let Product Choices add weight to the product.
	function setProductWeight($x){
		
		$x = floatval($x);
		$x = strval($x);
		
		if(strlen($x) > 10)
			throw new Exception("Error with method setProductWeight.  Length may not exceed 10 characters (including the decimal).");
		
		$this->_productUnitWeight = $x;
	}
	
	// Width/Height/X/Y are all measured in Inches.
	// The X/Y coordinates are relative to the center point on the canvas.
	function setArtworkSweetSpotWidth($x){
		
		if(empty($x)){
			$this->_artworkSweetSpotWidth = null;
			return;
		}
		
		$x = floatval($x);
		$x = strval($x);
		
		if(strlen($x) > 10)
			throw new Exception("Error with method setArtworkSweetSpotWidth");
		
		$this->_artworkSweetSpotWidth = $x;
	}
	function setArtworkSweetSpotHeight($x){
		
		if(empty($x)){
			$this->_artworkSweetSpotHeight = null;
			return;
		}
		
		$x = floatval($x);
		$x = strval($x);
		
		if(strlen($x) > 10)
			throw new Exception("Error with method setArtworkSweetSpotWidth");
		
		$this->_artworkSweetSpotHeight = $x;
	}
	
	function setArtworkSweetSpotX($x){
		
		$x = floatval($x);
		$x = strval($x);
		
		if(strlen($x) > 10)
			throw new Exception("Error with method setArtworkSweetSpotX");
		
		$this->_artworkSweetSpotX = $x;
	}
	function setArtworkSweetSpotY($x){
		
		$x = floatval($x);
		$x = strval($x);
		
		if(strlen($x) > 10)
			throw new Exception("Error with method setArtworkSweetSpotY");
		
		$this->_artworkSweetSpotY = $x;
	}
	
	
	function setBasePriceCustomer($x){
		
		$x = floatval($x);
		$x = strval($x);
		
		if(strlen($x) > 10)
			throw new Exception("Error in method setBasePriceCustomer.  The number of characters can not exceed 10, including the decimal.");
		
		$this->_custInitBase = $x;
	}
	
	function setInitialSubtotalCustomer($x){
		
		$x = floatval($x);
		$x = strval($x);

		if(strlen($x) > 10)
			throw new Exception("Error in method setInitialSubtotalCustomer.  The number of characters can not exceed 10, including the decimal.");
	
		$this->_custInitSub = $x;
	}
	
	
	function setMaxBoxSize($x){
	
		$x = intval($x);
		
		if($x > 999999)
			throw new Exception("Error in method setMaxBoxSize.  The value can not be greater than or equal to 1,000,000.");
	
		$this->_maxBoxSize = $x;
	}
	
	
	function setVariableDataFlag($x){
		if(!is_bool($x))
			throw new Exception("Error in method setVariableDataFlag.  The value must be boolean");
		$this->_variableDataFlag = $x;
		
		// If there is not variable data, the there can't be mailing services either.
		if(!$x)
			$this->_mailingServicesFlag = false;
	}
	
	function setMailingServiceFlag($x){
		if(!is_bool($x))
			throw new Exception("Error in method setMailingServiceFlag.  The value must be boolean");
			
		if($x && !$this->_variableDataFlag)
			throw new Exception("Error in method setMailingServiceFlag. You can not make this product have mailing services unless the product supports variable data.");
			
		$this->_mailingServicesFlag = $x;
	}
	
	
	function setDefaultProductionDays($x){
	
		$x = intval($x);
		
		if($x > 99)
			throw new Exception("Error in method setDefaultProductionDays.  The value can not be greater than 99.");
	
		$this->_defaultProductionDays = $x;
	}
	
	function setDefaultCutOffHour($x){
	
		$x = intval($x);
		
		if($x > 23)
			throw new Exception("Error in method setDefaultCutOffHour.  The value must be between 0 and 23.");
	
		$this->_defaultCutOffHour = $x;
	}
	
	
	// Calling this method will immediately update the DB.
	// Set Number of Days to NULL to remove the entry
	function setProductionDaysOverride($shippingChoiceID, $numberOfDays){
		
		$shippingChoiceID = intval($shippingChoiceID);
	
		// If there is an existing override value, delete it before inserting the new value.
		$this->_dbCmd->Query("DELETE FROM productproductiondays WHERE ProductID=" . $this->_productID . " AND ShippingChoiceID = $shippingChoiceID");
	
		if(!empty($numberOfDays))
			$this->_dbCmd->InsertQuery("productproductiondays", array("ShippingChoiceID"=>$shippingChoiceID, "ProductID"=>$this->_productID, "ProductionDays"=>$numberOfDays));
	}
	
	// Calling this method will immediately update the DB.
	// Set Cut Off Hour to NULL to remove the entry
	function setProductionHourOverride($shippingChoiceID, $cutOffHour){
		
		$shippingChoiceID = intval($shippingChoiceID);
	
		// If there is an existing override value, delete it before inserting the new value.
		$this->_dbCmd->Query("DELETE FROM productcutoffhour WHERE ProductID=" . $this->_productID . " AND ShippingChoiceID = $shippingChoiceID");
	
		$cutOffHour = intval($cutOffHour);
		
		if($cutOffHour > 23)
			throw new Exception("Error in method setProductionHourOverride.  The value must be a number between 0 and 23.");
	
		if(!empty($cutOffHour))
			$this->_dbCmd->InsertQuery("productcutoffhour", array("ShippingChoiceID"=>$shippingChoiceID, "ProductID"=>$this->_productID, "CutOffHour"=>$cutOffHour));
	}
	
	
	function setThumbOverlayX($x){
	
		$x = intval($x);
		
		if($x > 700)
			throw new Exception("Error in method setThumbOverlayX, the value must be less than 700 pixels.");
	
		$this->_thumbOverlayX = $x;
	}
	
	function setThumbOverlayY($x){
	
		$x = intval($x);
		
		if($x > 700)
			throw new Exception("Error in method setThumbOverlayY, the value must be less than 700 pixels.");
	
		$this->_thumbOverlayY = $x;
	}
	
	function setThumbWidth($x){
	
		$x = intval($x);
		
		if(empty($x))
			throw new Exception("Error in method setThumbWidth, the value must be greater than zero." . $x);
		
		if($x > 700)
			throw new Exception("Error in method setThumbWidth, the value must be less than 700 pixels.");
	
		$this->_thumbWidth = $x;
	}

	function setThumbHeight($x){
	
		$x = intval($x);
		
		if(empty($x))
			throw new Exception("Error in method setThumbHeight, the value must be greater than zero.");
		
		if($x > 700)
			throw new Exception("Error in method setThumbHeight, the value must be less than 700 pixels.");
			
		$this->_thumbHeight = $x;
	}
	
	function setTempPrevBackLandscapeOverlayX($x){
		$x = intval($x);
		
		if($x > 900)
			throw new Exception("Error in method setTempPrevBackLandscapeOverlayX, the value must be less than 900 pixels.");
	
		$this->tempPrevBackLandscapeOverlayX = $x;
	}
	function setTempPrevBackLandscapeOverlayY($x){
		$x = intval($x);
		
		if($x > 900)
			throw new Exception("Error in method setTempPrevBackLandscapeOverlayY, the value must be less than 900 pixels.");
	
		$this->tempPrevBackLandscapeOverlayY = $x;
	}
	function setTempPrevBackPortraitOverlayX($x){
		$x = intval($x);
		
		if($x > 900)
			throw new Exception("Error in method setTempPrevBackPortraitOverlayX, the value must be less than 900 pixels.");
	
		$this->tempPrevBackPortraitOverlayX = $x;
	}
	function setTempPrevBackPortraitOverlayY($x){
		$x = intval($x);
		
		if($x > 900)
			throw new Exception("Error in method setTempPrevBackPortraitOverlayY, the value must be less than 900 pixels.");
	
		$this->tempPrevBackPortraitOverlayY = $x;
	}
	
	function setThumbReorderScale($x){

		$x = intval($x);
		
		if($x > 200)
			throw new Exception("Error in method setThumbReorderScale, the value must be less than 200.  Enter 0 if the thumbnail should not be printed.");
	
		$this->_artThumbReorderScale = $x;
	}
	
	function setThumbReorderPicasX($x){
	
		$x = intval($x);
		
		if($x > 999)
			throw new Exception("Error in method setThumbReorderPicasX, the value must be less than 5000 picas.");
	
		$this->_artThumbReorderXcoord = $x;
	}
	
	function setThumbReorderPicasY($x){
	
		$x = intval($x);
		
		if($x > 999)
			throw new Exception("Error in method setThumbReorderPicasY, the value must be less than 5000 pixels.");
	
		$this->_artThumbReorderYcoord = $x;
	}


	// Scale is relative to 96 DPI... so 100% scale would be the size that users see artwork within the editing tool when the Zoom is set at 100%
	function setTemplatePreviewScale($x){
	
		$x = floatval($x);
		
		if($x > 300)
			throw new Exception("Error in method setTemplatePreviewScale.  The scale can not exceed 300 percent.");
	
		if($x < 0.01)
			throw new Exception("Error in method setTemplatePreviewScale.  The scale percentage must be greater than zero.");
	
		$this->_templatePreviewScale = $x;
	}
	
	// Set to TRUE if the template preview images should be restricted to artwork sweet spot areas (if defined).
	function setTemplatePreviewSweetSpot($flag){
	
		if(!is_bool($flag))
			throw new Exception("Tempalte preview sweet spot must be boolean.");
		
		$this->_templatePreviewSweetSpot = $flag;
	}
	
	
	// If we want to show multiple template previews Sides to users while browsing the template collection.
	function setTemplatePreviewSidesDisplay($x){
		
		if($x != "M" && $x != "F")
			throw new Exception("The TemplatePreviewSides Display must be either 'M'ultiple or 'F'irst");
		
		$this->_templatePreviewSidesDisplay = $x;
	}


	// Sets the Thumbnail Copy Icon JPEG by reference
	// Returns NULL if there is not an error...
	// If there is an error, it will return an error message and set the image to NULL in the DB.
	// That will keep users from losing their form data on the event of an error.
	function setThumbnailCopyIconJPG(&$jpegBinData){
	
		if(strlen($jpegBinData) > 307200)
			return "Error uploading the Thumbnail Copy Icon. The image can not be greater than 300K.";
		
		if(!empty($jpegBinData)){
			$imageFormatCheck = ImageLib::GetImageFormatFromBinData($jpegBinData);
			if($imageFormatCheck != "JPEG")
				return "Error uploading the Thumbnail Copy Icon. The image does not appear to be in JPEG format.";
		}
		else{
			$jpegBinData = null;
		}
		
		$this->_thumbnailCopyIconJPG =& $jpegBinData;
		
		
		$this->_thumbnailCopyFileSize = strlen($jpegBinData);
		
		// Let this object know that we have received new data from the user.
		// We don't always load this binary data into memory during product initialization to cutdown on uncessary processing.
		$this->_thumbnailCopyIconJPG_changedFlag = true;
		
		return null;
	}



	// Sets the Thumbnail Background JPEG by reference
	// Returns NULL if there is not an error...
	// If there is an error, it will return an error message and set the image to NULL in the DB.
	// That will keep users from losing their form data on the event of an error.
	function setThumbnailBackJPG(&$jpegBinData){
	
		if(strlen($jpegBinData) > 307200)
			return "Error uploading the Thumbnail Background Image. The file size can not be greater than 300K.";
		
		if(!empty($jpegBinData)){
			$imageFormatCheck = ImageLib::GetImageFormatFromBinData($jpegBinData);
			if($imageFormatCheck != "JPEG")
				return "Error uploading the Thumbnail Background Image. The image does not appear to be in JPEG format.";
				
			$imageDimHash = ImageLib::GetDimensionsFromImageBinaryData($jpegBinData);
			$this->_thumbnailBackWidth = $imageDimHash["Width"];
			$this->_thumbnailBackHeight = $imageDimHash["Height"];
		}
		else{
			$jpegBinData = null;
			
			$this->_thumbnailBackWidth = 0;
			$this->_thumbnailBackHeight = 0;
		}
		
		$this->_thumbnailBackJPG =& $jpegBinData;
		
		$this->_thumbnailBackFileSize = strlen($jpegBinData);
		
		// Let this object know that we have received new data from the user.
		// We don't always load this binary data into memory during product initialization to cutdown on uncessary processing.
		$this->_thumbnailBackJPG_changedFlag = true;
		
		return null;
	}
	
	
	// Sets the Template Preview Background JPEG
	// Returns NULL if there is not an error...
	// If there is an error, it will return an error message and set the image to NULL in the DB.
	// That will keep users from losing their form data on the event of an error.
	function setTempPrevBackLandscapeJPG($jpegBinData){
	
		if(strlen($jpegBinData) > 307200)
			return "Error uploading the Thumbnail Background Image. The file size can not be greater than 300K.";
		
		if(!empty($jpegBinData)){
			$imageFormatCheck = ImageLib::GetImageFormatFromBinData($jpegBinData);
			if($imageFormatCheck != "JPEG")
				return "Error uploading the Template Background Image. The image does not appear to be in JPEG format.";
				
			$imageDimHash = ImageLib::GetDimensionsFromImageBinaryData($jpegBinData);
			$this->tempPrevBackLandscapeJpgWidth = $imageDimHash["Width"];
			$this->tempPrevBackLandscapeJpgHeight = $imageDimHash["Height"];
		}
		else{
			$jpegBinData = null;
			
			$this->tempPrevBackLandscapeJpgWidth = 0;
			$this->tempPrevBackLandscapeJpgHeight = 0;
		}
		
		$this->tempPrevBackLandscapeJPG =& $jpegBinData;
		
		$this->tempPrevBackLandscapeFileSize = strlen($jpegBinData);
		
		// Let this object know that we have received new data from the user.
		// We don't always load this binary data into memory during product initialization to cutdown on uncessary processing.
		$this->tempPrevBackLandscapeJPG_changedFlag = true;
		
		return null;
	}
	function setTempPrevBackPortraitJPG($jpegBinData){
	
		if(strlen($jpegBinData) > 307200)
			return "Error uploading the Thumbnail Background Image. The file size can not be greater than 300K.";
		
		if(!empty($jpegBinData)){
			$imageFormatCheck = ImageLib::GetImageFormatFromBinData($jpegBinData);
			if($imageFormatCheck != "JPEG")
				return "Error uploading the Template Background Image. The image does not appear to be in JPEG format.";
				
			$imageDimHash = ImageLib::GetDimensionsFromImageBinaryData($jpegBinData);
			$this->tempPrevBackPortraitJpgWidth = $imageDimHash["Width"];
			$this->tempPrevBackPortraitJpgHeight = $imageDimHash["Height"];
		}
		else{
			$jpegBinData = null;
			
			$this->tempPrevBackPortraitJpgWidth = 0;
			$this->tempPrevBackPortraitJpgHeight = 0;
		}
		
		$this->tempPrevBackPortraitJPG =& $jpegBinData;
		
		$this->tempPrevBackPortraitFileSize = strlen($jpegBinData);
		
		// Let this object know that we have received new data from the user.
		// We don't always load this binary data into memory during product initialization to cutdown on uncessary processing.
		$this->tempPrevBackPortraitJPG_changedFlag = true;
		
		return null;
	}



	// Sets the Thumbnail Background JPEG by reference
	// Returns NULL if there is not an error...
	// If there is an error, it will return an error message and set the image to NULL in the DB.
	// That will keep users from losing their form data on the event of an error.
	function setTemplatePreviewMaskPNG(&$pngBinaryData){
	
		if(strlen($pngBinaryData) > 1048576)
			return "Error uploading the Template Preview Mask. The file size can not be greater than 1 meg.";
		
		if(!empty($pngBinaryData)){
			$imageFormatCheck = ImageLib::GetImageFormatFromBinData($pngBinaryData);
			if($imageFormatCheck != "PNG")
				return "Error uploading the Template Preview Mask. The image does not appear to be in PNG format.";
		}
		else{
			$pngBinaryData = null;
		}

		$this->_templatePreviewMaskPNG =& $pngBinaryData;
		
		// Let this object know that we have received new data from the user.
		// We don't always load this binary data into memory during product initialization to cutdown on uncessary processing.
		$this->_templatePreviewMaskPNG_changedFlag = true;
		
		return null;
	}



	function setProjectInitSavedNote($x){
	
		if(strlen($x) > 200)
			throw new Exception("Error in method setProjectInitSavedNote.  The note can not be over 200 characters.");
			
		$this->_projectInitSavedNote = $x;
	}	

	function setReorderCardSavedNote($x){
	
		if(strlen($x) > 200)
			throw new Exception("Error in method setReorderCardSavedNote.  The note can not be over 200 characters.");
			
		$this->_reorderCardSavedNote = $x;
	}	
	
	
	function setUserIDofArtworkSetup($x){
	
		$x = preg_replace("/u/i", "", $x);
	
		$x = intval($x);
		
		if(!empty($x))			
			$this->_userIDofArtworkSetup = $x;
		else
			$this->_userIDofArtworkSetup = null;
	}

	
	function setArtworkCanvasWidth($x){
	
		$x = floatval($x);
		
		if(empty($x) || $x > 99)
			throw new Exception("Error in method setArtworkCanvasWidth.  The value must be greater than 0 inches and less than 100 inches. ");
		
		$this->_artworkCanvasWidth = $x;
	}
	function setArtworkCanvasHeight($x){
	
		$x = floatval($x);
		
		if(empty($x) || $x > 99)
			throw new Exception("Error in method setArtworkCanvasHeight.  The value must be greater than 0 inches and less than 100 inches. ");
		
		$this->_artworkCanvasHeight = $x;
	}
	

	function setArtworkSidesCount($x){
		
		$x = intval($x);
		
		if(empty($x) || $x > 99)
			throw new Exception("Error in method setArtworkSidesCount.  The number of sides must be greater than zero and less than 100." . $x);
		
		return $this->_artworkSidesCount = $x;
	}
	
	function setArtworkBleedPicas($x){
		
		$x = floatval($x);
		$x = strval($x);
		
		if(strlen($x) > 5)
			throw new Exception("Error in method setArtworkBleedPicas.  There must not be more than characters (including a decimal point).");
		
		return $this->_artworkBleedPicas = $x;
	}
	
	// Pass in TRUE or FALSE
	function setArtworkIsEditable($x){
		
		if(!is_bool($x))
			throw new Exception("Artwork is editable is not boolean.");
		
		return $this->_artworkIsEditable = ($x ? "Y" : "N");
	}
	
	
	function setProductArtworkDPI($x){
		
		$x = intval($x);
		
		if(empty($x) || $x > 1000)
			throw new Exception("Error in method setProductArtworkDPI.  The DPI must be greater than 1 and less than 1000.");
		
		return $this->_artworkDPI = $x;
	}
	
	
	function setArtworkImageUploadEmbedMsg($x){

		if(strlen($x) > 250)
			throw new Exception("Error in method setArtworkImageUploadEmbedMsg. The string length can not go over 250.");
		
		return $this->_artworkImageUploadEmbedMsg = $x;
	}
	
	function setArtworkCustomUploadTemplateURL($x){

		if(strlen($x) > 250)
			throw new Exception("Error in method setArtworkCustomUploadTemplateURL. The string length can not go over 250.");
		
		return $this->_artworkCustomUploadTemplateURL = $x;
	}

	
	// A Method for setting the Product Status.  We never actually delete products, just delete them.
	function setProductStatus($x){
	
		if(!in_array($x, array("G", "D")))
			throw new Exception("Error in method setProductStatus... the Status character is illegal.");
		
		$this->_productStatus = $x;
	}
	
	// If there are multiple template previews being generated for this product then you can set a default Product it for viewing
	// If this product is sharing templates from another product... then you can not set a value here.
	function setDefaultPreviewID($x){
	
		if(!empty($x)){
			if(!Product::checkIfProductIDexists($this->_dbCmd, $x))
				throw new Exception("Error in method setDefaultPreviewID.  The ProductID does not exist.");
		}
		
		if(empty($this->_multipleTemplatePreviewsArr) && !empty($x))
			throw new Exception("Error in method setDefaultPreviewID.  You can not set a default preview ID if this product does not generate multiple template previews.");

		
		if(!empty($this->_useTemplatesFromProductID) && !empty($x))
			throw new Exception("Error in method setDefaultPreviewID.  This product is sharing templates off of another product.  Therefore you can not have this product generating its own previews images.");
		
		$this->_defaultPreviewID = $x;
	}
	
	function setProductImportance($x){
	
		$x = intval($x);
		
		if($x < 0 || $x > 100)
			throw new Exception("Error in method setProductImportance. Must be a number between 0 and 100.");
			
		$this->_productImportance = $x;
	}
	
	// Pass in an array if you want this product to generate preview images on the behalf of other products.
	// If this product is sharing templates from another product... then you can not set a value here.
	function setMultipleTemplatePreviewsArr($arr){
	
		if(!is_array($arr))
			throw new Exception("Error in method setMultipleTemplatePreviewsArr.  Value must be an array.");
	
		if(!empty($arr)){
			foreach($arr as $thisArrVal){
				if(!Product::checkIfProductIDexists($this->_dbCmd, $thisArrVal))
					throw new Exception("Error in method setMultipleTemplatePreviewsArr.  The ProductID does not exist.");
			}
		}
		
		// Internally, make sure this products's own ProductID is not in the list.
		WebUtil::array_delete($arr, $this->_productID);
		
		// If there is not multiple template previews then wipe out any default Preview ID.
		if(empty($arr))
			$this->_defaultPreviewID = null;
			
		$this->_multipleTemplatePreviewsArr = $arr;
	}
	
	
	
	function setCompatibleProductIDsArr($arr){
	
		if(!is_array($arr))
			throw new Exception("Error in method setCompatibleProductIDsArr.  Value must be an array.");
	
		if(!empty($arr)){
			foreach($arr as $thisArrVal){
				if(!Product::checkIfProductIDisActive($this->_dbCmd, $thisArrVal))
					throw new Exception("Error in method setCompatibleProductIDsArr. You can not call a product Compatible if it is not active.");
			}
		}
		
		// Make sure that this products's own ProductID is not in the list.
		WebUtil::array_delete($arr, $this->_productID);
			
		$this->_compatibleProductIDsArr = $arr;
	
	}
	
	
	
	
	
	


	// Set an Array of Promotional Commands that will be relayed through the API when a product is ready for shipment.
	// You should check to make sure that the Customer is not a reseller before sending these.
	function setPromotionalCommandsArr($arr){
	
		if(!is_array($arr))
			throw new Exception("Error in method setMultipleTemplatePreviewsArr.  Value must be an array.");
	
		$filteredArr = array();
		
		if(!empty($arr)){
			foreach($arr as $thisArrVal){
				
				if(empty($thisArrVal))
					continue;
				
				if(!preg_match("/^\w{1,25}$/", $thisArrVal))
					throw new Exception("Error in method setMultisetPromotionalCommandsArrleTemplatePreviewsArr. The promotional Command is not in Correct Format.");
			
				$filteredArr[] = $thisArrVal;
			}
		}
		
		$this->_promotionalCommandsArr = $filteredArr;
	}
	
	

	

	function setUseTemplatesFromProductID($x){
	
		if(empty($x)){
			$this->_useTemplatesFromProductID = null;
			return;
		}
		
		$this->_dbCmd->Query("SELECT COUNT(*) FROM products WHERE UseTemplatesFromProductID=" . $this->_productID);
		$linkCountToThisProduct = $this->_dbCmd->GetValue();
		
		if(!empty($x) && !empty($linkCountToThisProduct))
			throw new Exception("Error in method setUseTemplatesFromProductID. You are trying to change the Template Link on this product but there are other product(s) currently linked to this one.  This would indirectly cause a Daisy Chained production link, which is not permitted");
		
		if($x == $this->_productID)
			throw new Exception("Error in method setUseTemplatesFromProductID.  You can not link a product's templates to itself.  Maybe you should try setting this value to null instead?");
		
		if(!Product::checkIfProductIDexists($this->_dbCmd, $x))
			throw new Exception("Error in method setUseTemplatesFromProductID.  The ProductID does not exist.");
			
		$linkedProductObj = Product::getProductObj($this->_dbCmd, $x);
		
		if($linkedProductObj->getUseTemplatesFromProductID() != null)
			throw new Exception("Error in method setUseTemplatesFromProductID.  You can not link to a Product which is already linked to another.  Daisy chaining production linking is not permitted.");
		
		if($linkedProductObj->getProductStatus() != "G")
			throw new Exception("Error in method setUseTemplatesFromProductID.  You can not link to another Product that is disabled.");
		
		$this->_useTemplatesFromProductID = $x;
	}


	function setProductionPiggybackID($x){
	
		if(empty($x)){
			$this->_productionPiggybackID = null;
			return;
		}
	
		$linkCountToThisProduct = sizeof(self::getProductIDsPiggyBackingToThisProduct($this->_dbCmd, $this->_productID));
		
		if(!empty($x) && !empty($linkCountToThisProduct))
			throw new Exception("Error in method setProductionPiggybackID. You are trying to change the ProductionPiggyBack on this product but there are other products currently linked to this one.  This would indirectly cause a Daisy Chained production link, which is not permitted.");
		
		if($x == $this->_productID)
			throw new Exception("Error in method setProductionPiggybackID.  You can not link a product's templates to itself.  Maybe you should try setting this value to null instead?");
		
		if(!Product::checkIfProductIDexists($this->_dbCmd, $x))
			throw new Exception("Error in method setProductionPiggybackID.  The ProductID does not exist.");
			

		// In case Authentication was changed for this user... or Production Piggyback was set by an administrator
		// ... then we don't want to Authenticate the Production Piggyback Product Object if the Product ID is already set.
		// If the user was to swtich Production Piggybacks (outside of their domain privelages)... then the restricted user could not re-save the cross-domain Production Piggyback.
		if($this->getProductionPiggybackID() == $x)
			$authenticateDomainOnProductObject = false;
		else
			$authenticateDomainOnProductObject = true;
			
		$linkedProductObj = Product::getProductObj($this->_dbCmd, $x, $authenticateDomainOnProductObject);
		
		if($linkedProductObj->getProductionPiggybackID() != null)
			throw new Exception("Error in method setProductionPiggybackID.  You can not link to Product which is already linked to another.  Daisy chaining production linking is not permitted.");
		
		if($linkedProductObj->getProductStatus() != "G")
			throw new Exception("Error in method setProductionPiggybackID.  You can not link to another Product that is disabled.");
		
		$this->_productionPiggybackID = $x;
	}
	
	
	function setParentProductID($x){
		
		if(empty($x)){
			$this->_parentProductID = null;
			return;
		}
		
		$this->_dbCmd->Query("SELECT COUNT(*) FROM products WHERE ParentProductID=" . $this->_productID);
		$linkCountToThisProduct = $this->_dbCmd->GetValue();
		
		if(!empty($x) && !empty($linkCountToThisProduct))
			throw new Exception("You are trying to change the ParentProductID on this product but there are other products currently linked to this one as the parent.  This would indirectly cause a Daisy Chained linking, which is not permitted");

		if($x == $this->_productID)
			throw new Exception("You can not set the parent of this product to itself.  Maybe you should try setting this value to null instead?");
		
		if(!Product::checkIfProductIDexists($this->_dbCmd, $x))
			throw new Exception("Error in method setParentProductID.  The ProductID does not exist.");
			
		$linkedProductObj = Product::getProductObj($this->_dbCmd, $x);
		
		if($linkedProductObj->getParentProductID() != null)
			throw new Exception("Error in method setParentProductID.  You can set a Parent Product ID on a product which has a Parent Product ID itself.  Daisy chain linking is not permitted.");
		
		if($linkedProductObj->getProductStatus() != "G")
			throw new Exception("Error in method setParentProductID.  You can not link to Parent ProductID that is disabled.");
		
		$this->_parentProductID = $x;
	
	}
	
	
	
	// Side Number is 1 based.
	// Set a string like Front or Back
	// Make sure to define the number of sides on this object before trying to set a description.
	function setArtworkSideDescription($sideNum, $sideDescription){
	
		$sideNum = intval($sideNum);
		$sideDescription = trim($sideDescription);
		
		// Do not let special HTML characters into the Side Description.
		$sideDescription = preg_replace("/&/", "", $sideDescription);
		$sideDescription = preg_replace("/</", "", $sideDescription);
		$sideDescription = preg_replace("/>/", "", $sideDescription);
		$sideDescription = preg_replace('/"/', "", $sideDescription);
		
		
		if(empty($sideDescription))
			$sideDescription = null;
	
		if($sideNum < 1)
			throw new Exception("Error in method setArtworkSideDescription.  The Side number must be greater than or equal to 1");
			
		if($sideNum > $this->getArtworkSidesCount())
			throw new Exception("Error in method setArtworkSideDescription.  You are trying to set the Side Description for a number that doesn't exist.");
			
		
		$newSideDescriptionArr = array();
		
		for($i=1; $i<=$sideNum; $i++){
		
			if($i == $sideNum)
				$newSideDescriptionArr[] = $sideDescription;
			else
				$newSideDescriptionArr[] = $this->getArtworkSideDescription($i);
		}
		
		// Update our private variable of Side Descriptions... which is a pipe delimited String.
		$this->_artworkSidesDesc = implode("\|", $newSideDescriptionArr);
	}
	
	
	
	
	// Deletes all of the Product Switches.  Usefull if you are editing the Product... before you save all of the values back in.
	// This keeps you from having to explicitly remove Products.
	function clearAllProductSwitches(){
	
		$this->_loadProductSwitches();
		
		$this->_productSwitchesArr = array();
	}


	function addProductSwitch($targetProdID, $switchTitle, $switchLinkSubject, $switchDescription, $switchDescriptionIsHTML){
	
		$this->_loadProductSwitches();
		
		if(!is_bool($switchDescriptionIsHTML))
			throw new Exception("Error in method addProductSwitch. The Description Format must be boolean.");
			
		if(empty($switchLinkSubject) && empty($switchDescription))
			throw new Exception("Error in method addProductSwitch. You may not have a blank LinkSubject and a blank Description. One or the other, or both, must have a value.");

		if(empty($switchTitle))
			throw new Exception("Error in method addProductSwitch. You must have a Product Switch Title.");

		$productSwitchObj = new ProductSwitch();

		$productSwitchObj->switchTitle = $switchTitle;
		$productSwitchObj->switchLinkSubject = $switchLinkSubject;
		$productSwitchObj->switchDescription = $switchDescription;
		$productSwitchObj->switchDescriptionIsHTML = $switchDescriptionIsHTML;

		// The "key" is the Target Product ID. This also keeps out duplicates.
		$this->_productSwitchesArr[intval($targetProdID)] = $productSwitchObj;

	}

	
	

	
	
	
	
	
	
	
	
	
	
	
	
	
	
        // --------------------  Checker Methods --------------------------------------------
	
	function checkIfProjectInitOnSavedProjectsAvailable(){
	
		$savedProjectID = $this->getSavedProjectIDofInitialize();
		
		if(empty($savedProjectID))
			return false;
		else
			return true;
	}
	
	// Make sure the thte Saved Project note exists before calling this method.
	// It will turn TRUE if the product ID in the Saved Project matches, otherwise returns FALSE.
	function checkIfProjectInitOnSavedProjectsMatchesProductID(){
	
		if(empty($this->_productID))
			throw new Exception("The Product ID must be loaded before calling this method.");
		
		if(!$this->checkIfProjectInitOnSavedProjectsAvailable())
			throw new Exception("The Project Init must exist before calling this method.");
		
		$savedProjectID = $this->getSavedProjectIDofInitialize();
		
		$this->_dbCmd->Query("SELECT ProductID FROM projectssaved WHERE ID=" . intval($savedProjectID));
		if($this->_dbCmd->GetValue() != $this->_productID)
			return false;
		else
			return true;
	}
	
	
	function checkIfReorderCardOnSavedProjectsAvailable(){
	
		$savedProjectID = $this->getSavedProjectIDofReorderCard();
		
		if(empty($savedProjectID))
			return false;
		else
			return true;
	}
	
	
	// Make sure the thte Saved Project note exists before calling this method.
	// It will turn TRUE if the product ID in the Saved Project matches, otherwise returns FALSE.
	function checkIfReorderCardOnSavedProjectsMatchesProductID(){
	
		if(empty($this->_productID))
			throw new Exception("The Product ID must be loaded before calling this method.");
		
		if(!$this->checkIfReorderCardOnSavedProjectsAvailable())
			throw new Exception("The Project Init must exist before calling this method.");
		
		$savedProjectID = $this->getSavedProjectIDofReorderCard();
		
		$this->_dbCmd->Query("SELECT ProductID FROM projectssaved WHERE ID=" . intval($savedProjectID));
		if($this->_dbCmd->GetValue() != $this->_productID)
			return false;
		else
			return true;
	}
	
	
	
	// For speed purposes... we are going to check the file size of the background thumbnail image.
	// We added an extra column in the DB for this particular image.  The database would be hammering the BLOB field a lot to find out the same information.
	function checkIfThumbnailBackSaved(){
		
		if(empty($this->_thumbnailBackFileSize))
			return false;
		else
			return true;
	}

	function checkIfTempPrevBackLandscapeJPGSaved(){
		if(empty($this->tempPrevBackLandscapeFileSize))
			return false;
		else
			return true;
	}
	function checkIfTempPrevBackPortraitJPGSaved(){
		if(empty($this->tempPrevBackPortraitFileSize))
			return false;
		else
			return true;
	}

	function checkIfThumbnailCopyIconSaved(){
		
		if(empty($this->_thumbnailCopyFileSize))
			return false;
		else
			return true;
	}
	


	function checkIfTemplatePreviewMaskSaved(){

		// Don't try to load this binary image from the DB until this method is actually called to save wasted resources.
		$this->_dbCmd->Query("SELECT TemplatePreviewMaskPNG FROM products WHERE ID=" . $this->_productID);
		$previewImg = $this->_dbCmd->GetValue();

		if(empty($previewImg))
			return false;
		else
			return true;
	}
		
	
	
	// Instead of having a field in the DB that will tell us this... we simply check if either value of height or width is 0.
	function checkIfArtworkHasSweetSpot(){
		
		if(empty($this->_artworkSweetSpotWidth) || empty($this->_artworkSweetSpotHeight))
			return false;
		else
			return true;
	}
	


	// Some Products will benefit from having a thumbnail image printed on a 
	function checkIfThumbnailImageShouldPrintOnReorderCard(){
		
		if(empty($this->_artThumbReorderScale))
			return false;
		else
			return true;
	}


	function checkIfProductSavedMultiplePreviewImages(){
	
		$mutliPreviewArr = $this->getMultipleTemplatePreviewsArr();
		
		if(empty($mutliPreviewArr))
			return false;
		else
			return true;
	}
	
	// If a message is presented to the user to switch between the different Preview Images (during the template selection phase)
	function checkIfMultiPreviewImagesHaveMessagesToSwitchBetween(){
	
		$this->_dbCmd->Query("SELECT COUNT(*) FROM productmessages WHERE SourceProductID=" . $this->_productID . " AND MultiTemplatePreviewSwitchDesc IS NOT NULL");
		
		if($this->_dbCmd->GetValue() == 0)
			return false;
		else
			return true;
	}

	

	// Returns true or false if the option name already exists
	function checkIfProductOptionExists($optionName){
	
		$optionIndex = $this->_getOptionNameIndex($optionName);
		
		if($optionIndex < 0)
			return false;
		else
			return true;
	}
	
	
	// Returns True or False depending on whether the quantity break exists for the given amount.
	// Make sure the option names and choices exist before calling this method.
	function checkIfQuantityBreakExistsOnOptionChoice($optionName, $choiceName, $quantityAmount){
	
		$optionIndex = $this->_getOptionNameIndex($optionName);
		
		if($optionIndex < 0)
			throw new Exception("Error in method checkIfQuantityBreakExistsOnOptionChoice.  The Option Name does not exist.");
		
		$choiceIndex = $this->_getOptionChoiceIndex($optionName, $choiceName);
		
		if($choiceIndex < 0)
			throw new Exception("Error in method checkIfQuantityBreakExistsOnOptionChoice.  The Choice Name does not exist.");
			
		foreach($this->_productOptionsArr[$optionIndex]->choices[$choiceIndex]->QuantityPriceBreaksArr as $thisQuanBreakObj){
			if($thisQuanBreakObj->amount == $quantityAmount)
				return true;
		}
		
		return false;
	}
	
	
	// returns true ro false depending on whether there is a quantity break with the current amount already on this product object.
	function checkIfQuantityBreakExists($quantityAmount){
	
		// Just in case the Option Names and Price Breaks have not been loaded from the DB yet.
		$this->_loadPricesAndOptions();
	
		foreach($this->_quantityPriceBreaksArr as $thisQuanBreakObj){
		
			if($thisQuanBreakObj->amount == $quantityAmount)
				return true;
		}
	
		return false;
	}
	
	
	// Returns true or false if the choice name exists on the given option
	// Also returns false if the Option name does noes not
	function checkIfOptionChoiceExists($optionName, $choiceName){
	
		$optionIndex = $this->_getOptionNameIndex($optionName);
		
		if($optionIndex < 0)
			return false;
		
		$choiceIndex = $this->_getOptionChoiceIndex($optionName, $choiceName);
		
		if($choiceIndex < 0)
			return false;
		else
			return true;
	}
	
	// Returns true or false if the choice name exists on the given option
	// Also returns false if the Option name does noes not
	function checkIfOptionNameExists($optionName){
	
		$optionIndex = $this->_getOptionNameIndex($optionName);
		
		if($optionIndex < 0)
			return false;
		else
			return true;
	}
	
	// Returns an inteter representing the array index to the position in the internal option array
	// If the Option Name is not found... the method will return -1
	function _getOptionNameIndex($optionName){
		
		// Just in case the Option Names have not been loaded from the DB yet.
		$this->_loadPricesAndOptions();
		
		$optionName = strtoupper(trim($optionName));
		
		for($i=0; $i<sizeof($this->_productOptionsArr); $i++){
			
			if(strtoupper($this->_productOptionsArr[$i]->optionName) == $optionName)
				return $i;
		}
		
		return -1;
	}
	
	// Will exist with an error if the Option Name does not exist... so be sure to check it.
	// Method will return the array index of the Choice Object on the given Option Name.
	function _getOptionChoiceIndex($optionName, $choiceName){
	
		$choiceName = strtoupper(trim($choiceName));
		
		$optionIndex = $this->_getOptionNameIndex($optionName);
		
		if($optionIndex < 0)
			throw new Exception("Error in method _getOptionChoiceIndex... The option name does not exist.");
		
		for($i=0; $i<sizeof($this->_productOptionsArr[$optionIndex]->choices); $i++){
			
			if(strtoupper($this->_productOptionsArr[$optionIndex]->choices[$i]->ChoiceName) == $choiceName)
				return $i;
		}
		
		return -1;
	
	}
	
	
	
	
	
	
	
	
	
	
	// --------------   Begin Setter Methods for Option Controllers ----------------------
	

	// Return True if the Option is for Administrators.
	function checkIfOptionIsForAdmins($optionName){
	
		if(!$this->checkIfProductOptionExists($optionName))
			throw new Exception("Error In method setOptionAdminController.  Option name does not currently exist.");
			
		return $this->_productOptionsArr[$this->_getOptionNameIndex($optionName)]->adminOptionController;
	}


	function setOptionAdminController($optionName, $flag){
	
		if(!$this->checkIfProductOptionExists($optionName))
			throw new Exception("Error In method setOptionAdminController.  Option name does not currently exist.");
			
		if(!is_bool($flag))
			throw new Exception("Error in method setOptionAdminController, Flag must be boolean ");
		
		$this->_productOptionsArr[$this->_getOptionNameIndex($optionName)]->adminOptionController = $flag;
	}
	
	
	// Make sure that there is not another Option with an artwork side controller before calling this method.
	function setOptionArtworkSideController($optionName, $flag){
	
		if(!$this->checkIfProductOptionExists($optionName))
			throw new Exception("Error In method setOptionArtworkSideController.  Option name does not currently exist.");
			
		if(!is_bool($flag))
			throw new Exception("Error in method setOptionArtworkSideController, Flag must be boolean ");
			
		if($flag){
			foreach($this->_productOptionsArr as $thisOptionObj){
				if($thisOptionObj->artworkSidesController && strtoupper($thisOptionObj->optionName) != strtoupper($optionName))
					throw new Exception("Error in method setOptionArtworkSideController. There is another option on this product that has set the Artwork Controller Flag to true.");
			}
		}
		
		$this->_productOptionsArr[$this->_getOptionNameIndex($optionName)]->artworkSidesController = $flag;
	}
	
	
	function setOptionArtSearchReplaceController($optionName, $flag){
	
		if(!$this->checkIfProductOptionExists($optionName))
			throw new Exception("Error In method setOptionArtSearchReplaceController.  Option name does not currently exist.");
			
		if(!is_bool($flag))
			throw new Exception("Error in method setOptionArtSearchReplaceController, Flag must be boolean ");
		
		$this->_productOptionsArr[$this->_getOptionNameIndex($optionName)]->artworkSearchReplaceController = $flag;
	}
	
	function setCouponDiscountExempt($optionName, $flag){
	
		if(!$this->checkIfProductOptionExists($optionName))
			throw new Exception("Error In method setCouponDiscountExempt.  Option name does not currently exist.");
			
		if(!is_bool($flag))
			throw new Exception("Error in method setCouponDiscountExempt, Flag must be boolean ");
		
		$this->_productOptionsArr[$this->_getOptionNameIndex($optionName)]->couponDiscountExempt = $flag;
	}
	
	
	
	
	function setOptionVariableImageController($optionName, $flag){
	
		if(!$this->checkIfProductOptionExists($optionName))
			throw new Exception("Error In method setOptionVariableImageController.  Option name does not currently exist.");
			
		if(!is_bool($flag))
			throw new Exception("Error in method setOptionVariableImageController, Flag must be boolean ");
			
		if($flag && !$this->_variableDataFlag)
			throw new Exception("Error in method setOptionVariableImageController. You can not make this option a Variable Image controller unless the product supports variable data.");
		
		$this->_productOptionsArr[$this->_getOptionNameIndex($optionName)]->variableImageController = $flag;
	}
	

	function setOptionsInCommonController($optionName, $flag){
	
		if(!$this->checkIfProductOptionExists($optionName))
			throw new Exception("Error In method setOptionsInCommonController.  Option name does not currently exist.");
			
		if(!is_bool($flag))
			throw new Exception("Error in method setOptionsInCommonController, Flag must be boolean ");
		
		$this->_productOptionsArr[$this->_getOptionNameIndex($optionName)]->optionInCommonForProduction = $flag;
	}
	
	
	
	function setOptionDescription($optionName, $optionDescription, $isHtmlFormat){
	
		if(!$this->checkIfProductOptionExists($optionName))
			throw new Exception("Error In method setOptionDescription.  Option name does not currently exist.");
			
		if(!is_bool($isHtmlFormat))
			throw new Exception("Error in method setOptionDescription, Format Flag must be boolean ");
		
		if(trim($optionDescription) == "")
			throw new Exception("Error in method setOptionDescription, The Option Description can not be left blank.");
		
		$this->_productOptionsArr[$this->_getOptionNameIndex($optionName)]->optionDescription = $optionDescription;
		$this->_productOptionsArr[$this->_getOptionNameIndex($optionName)]->optionDescriptionIsHTMLformat = $isHtmlFormat;
	}
	
	function setOptionNameAlias($optionName, $optionNameAlias){
	
		if(!$this->checkIfProductOptionExists($optionName))
			throw new Exception("Error In method setOptionNameAlias.  Option name does not currently exist.");
		
		$this->_productOptionsArr[$this->_getOptionNameIndex($optionName)]->optionNameAlias = trim($optionNameAlias);
	}
	
	
	
	// ------ Begin Methods for Adding New Options and Pricing --------------------
	
	
	// You can't have two Option Names that match or the method will cause an error.
	function addNewProductOption($optionName){
	
		$optionName = $this->filterOptionName($optionName);
		
		// Commas are not permited in the Option Name because we use them to delimit multiple option/choice selections in the DB on orders.
		// Dashes are not allowed either because they are a delimeter within the DB column.
		// Don't let Quotes or it can be a real pain with AJAX stuff.
		$optionName = preg_replace("/,/", "", $optionName);
		$optionName = preg_replace("/-/", "", $optionName);
		$optionName = preg_replace("/\"/", "", $optionName);
		$optionName = preg_replace("/'/", "", $optionName);
		
		if(empty($optionName))
			throw new Exception("Error In method addNewProductOption. The Option Name was left blank.");
	
		if($this->checkIfProductOptionExists($optionName))
			throw new Exception("Error In method addNewProductOption.  The Option name already exists.");
			
		$optionIndex = sizeof($this->_productOptionsArr);
		
		$this->_productOptionsArr[$optionIndex] = new ProductOption();
		$this->_productOptionsArr[$optionIndex]->optionName = $optionName;
		
		// Start off with the Option Description Matching the Optin Name... let the user change it later if needed.
		$this->_productOptionsArr[$optionIndex]->optionDescription = $optionName;
		$this->_productOptionsArr[$optionIndex]->optionDescriptionIsHTMLformat = false;
		
	
	}
	
	function filterOptionName($optionName){
		
		$optionName = trim($optionName);
		
		// Commas are not permited in the Option Name because we use them to delimit multiple option/choice selections in the DB on orders.
		// Dashes are not allowed either because they are a delimeter within the DB column.
		// Don't let Quotes or it can be a real pain with AJAX stuff.
		$optionName = preg_replace("/,/", "", $optionName);
		$optionName = preg_replace("/-/", "", $optionName);
		$optionName = preg_replace("/\"/", "", $optionName);
		$optionName = preg_replace("/'/", "", $optionName);

		return $optionName;
	}
	
	
	
	// You can't have two Option Names that match or the method will cause an error.
	function addNewOptionChoice($optionName, $choiceName){
	
		$optionName = trim($optionName);
		$choiceName = trim($choiceName);
		
		$choiceName = $this->filterOptionChoiceName($choiceName);
	
		if(empty($choiceName))
			throw new Exception("Error In method addNewOptionChoice. The Choice was left blank.");
	
		if(!$this->checkIfProductOptionExists($optionName))
			throw new Exception("Error In method addNewOptionChoice.  You are trying to add a new Choice, which must be linked to an OptionName... but the option does not currently exist.");
			
		if($this->checkIfOptionChoiceExists($optionName, $choiceName))
			throw new Exception("Error In method addNewOptionChoice.  The given choice name already exists.");
			
		
		$optionIndex = $this->_getOptionNameIndex($optionName);
		
		$choiceIndex = sizeof($this->_productOptionsArr[$optionIndex]->choices);

		$this->_productOptionsArr[$optionIndex]->choices[$choiceIndex] = new OptionChoice();
		$this->_productOptionsArr[$optionIndex]->choices[$choiceIndex]->ChoiceName = $choiceName;
		
		// Start out with the Choice Description Matching the Choice Name... let the user change it later if they want.
		$this->_productOptionsArr[$optionIndex]->choices[$choiceIndex]->ChoiceDescription = $choiceName;
		$this->_productOptionsArr[$optionIndex]->choices[$choiceIndex]->ChoiceDescriptionIsHTMLformat = false;

	}
	
	function filterOptionChoiceName($choiceName){
		
		$choiceName = trim($choiceName);
		
		// Commas are not permited in the Option Name because we use them to delimit multiple option/choice selections in the DB on orders.
		// Don't let Quotes or it can be a real pain with AJAX stuff.
		$choiceName = preg_replace("/,/", "", $choiceName);
		$choiceName = preg_replace("/\"/", "", $choiceName);
		$choiceName = preg_replace("/'/", "", $choiceName);
	
		return $choiceName;
	}
	
	
	

	// Pass in a quantity break object with a price adjustment object inside.
	// Make sure there isn't already a quantity break with the same quantity defined or it will show an error.
	function addNewQuantityBreak(QuantityPriceBreak $quanBrkObj){
		
		if(strtoupper(get_class($quanBrkObj->PriceAdjustments)) != strtoupper("PriceAdjustments"))
			throw new Exception("Error in method addNewQuantityBreak.  Price Adjustment object is not correct.");
			
		if($this->checkIfQuantityBreakExists($quanBrkObj->amount))
			throw new Exception("Error in method addNewQuantityBreak.  The quantity amount already exists");
		
		$quanityIndex = sizeof($this->_quantityPriceBreaksArr);
		
		$this->_quantityPriceBreaksArr[$quanityIndex] = $quanBrkObj;
		
	}
	
	
	
	// Pass in a quantity break object with a price adjustment object inside.
	// Make sure there isn't already a quantity break with the same quantity defined or it will show an error.
	// Make sure that the Option Names and Choice Name exists before calling this method.
	function addNewQuantityBreakOnOptionChoice($optionName, $choiceName, $quanBrkObj){
	
		if(strtoupper(get_class($quanBrkObj)) != strtoupper("QuantityPriceBreak"))
			throw new Exception("Error in method addNewQuantityBreakOnOptionChoice.  Quantity Break object is not correct.");
		
		if(strtoupper(get_class($quanBrkObj->PriceAdjustments)) != strtoupper("PriceAdjustments"))
			throw new Exception("Error in method addNewQuantityBreakOnOptionChoice.  Price Adjustment object is not correct.");

	
		$optionIndex = $this->_getOptionNameIndex($optionName);
		
		if($optionIndex < 0)
			throw new Exception("Error in method addNewQuantityBreakOnOptionChoice.  The Option Name does not exist.");
		
		$choiceIndex = $this->_getOptionChoiceIndex($optionName, $choiceName);
		
		if($choiceIndex < 0)
			throw new Exception("Error in method addNewQuantityBreakOnOptionChoice.  The Choice Name does not exist.");
	
		if($this->checkIfQuantityBreakExistsOnOptionChoice($optionName, $choiceName, $quanBrkObj->amount))
			throw new Exception("Error in method addNewQuantityBreakOnOptionChoice.  The quantity amount already exists");
		
		$this->_productOptionsArr[$optionIndex]->choices[$choiceIndex]->QuantityPriceBreaksArr[] = $quanBrkObj;
	}
	
	

	// Pass in a userID to add as a vendor.  If you try to add more than 6 this method will throw an error.
	// Will return the Vendor Number that was added.
	// It will pick the first open vendor number.  There could be gaps between active vendor ID's.  Returned Vendor ID's are 1 based up to 6 elements.
	function addNewVendor($vendorID){
		
		// If the User doesn't have permission, then don't let them remove a vendor.
		if(!Product::checkIfProductPiggyBackIsWithinUsersDomain($this->_productID))
			return 0;
	
		$existingVendorsArr = $this->getVendorIDArr();
		
		if(in_array($vendorID, $existingVendorsArr))
			throw new Exception("Error in method addNewVendor.  The vendor ID already exists on this product.");
			
		$currentVendorCount = $this->getVendorCount();
		if($currentVendorCount == 6)
			throw new Exception("Error in method addNewVendor.  There are already 6 vendors defined on this product.");
		
		for($i=1; $i<=6; $i++){
			
			$existingVendorID = $this->getVendorID($i);
			
			if(empty($existingVendorID))
				break;
		}
					
		$userControlObj = new UserControl($this->_dbCmd);

		if(!$userControlObj->LoadUserByID($vendorID) && !Constants::GetDevelopmentServer())
			throw new Exception("Error in method addNewVendor. The User does not exist.");

		$this->_vendorIDarr[$i-1] = $vendorID;
		return $i;

	}
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	// -------------------  Begin Methods for Updating Pricing and Options ------------------------------
	

	
	static function changeOptionName($productID, $oldOptionName, $newOptionName){
		
		$dbCmd = new DbCmd();
		$productObj = new Product($dbCmd, $productID, true);
		
		$newOptionName = $productObj->filterOptionName($newOptionName);
		
		if(empty($newOptionName))
			throw new Exception("Error in method changeOptionName. The new Option Name is empty");
		if(!$productObj->checkIfOptionNameExists($oldOptionName))
			throw new Exception("Error in method changeOptionName. The Old Option Name does not exist.");
		if($productObj->checkIfOptionNameExists($newOptionName))
			throw new Exception("Error in method changeOptionName. The new Option Name already exists.");
			
		$dbCmd->UpdateQuery("productoptions", array("OptionName"=>$newOptionName), "ProductID=$productID AND OptionName='" . DbCmd::EscapeSQL($oldOptionName) . "'");
	}
	
	static function changeOptionChoiceName($productID, $optionName, $oldChoiceName, $newChoiceName){
		
		$dbCmd = new DbCmd();
		$productObj = new Product($dbCmd, $productID, true);
	
		$newChoiceName = $productObj->filterOptionChoiceName($newChoiceName);
		
		if(empty($newChoiceName))
			throw new Exception("Error in method changeOptionChoiceName. The new Choice Name is empty");
		if(!$productObj->checkIfOptionNameExists($optionName))
			throw new Exception("Error in method changeOptionChoiceName. The Option Name does not exist.");
		if(!$productObj->checkIfOptionChoiceExists($optionName, $oldChoiceName))
			throw new Exception("Error in method changeOptionChoiceName. The old Choice Name does not exist.");
		if($productObj->checkIfOptionChoiceExists($optionName, $newChoiceName))
			throw new Exception("Error in method changeOptionChoiceName. The new Choice Name already exists.");
			
		$optionObj = $productObj->getOptionObject($optionName);
		$optionID = $optionObj->optionID;
			
		$dbCmd->UpdateQuery("productoptionchoices", array("ChoiceName"=>$newChoiceName), "ProductOptionID=$optionID AND ChoiceName='" . DbCmd::EscapeSQL($oldChoiceName) . "'");
	}
	
	// Pass in a Price Adustment Object.  Make sure that the Quantity Amount already exists before calling this method.
	function updateQuantityBreakPrices($quantityAmount, $priceAdjustObj){
	
		if(!$this->checkIfQuantityBreakExists($quantityAmount))
			throw new Exception("Error in method updatePriceQuantityBreak.  The Quantity break does not exist.");
	
		if(strtoupper(get_class($priceAdjustObj)) != strtoupper("PriceAdjustments"))
			throw new Exception("Error in method updatePriceQuantityBreak.  Price Adjustment object is not correct.");

		for($i=0; $i<sizeof($this->_quantityPriceBreaksArr); $i++){
			
			if($this->_quantityPriceBreaksArr[$i]->amount != $quantityAmount)
				continue;
	
			$this->_quantityPriceBreaksArr[$i]->PriceAdjustments = $priceAdjustObj;
		}
	}
	
	
	// Pass in a Price Adustment Object.  Make sure that the Option Name and Choice exist before calling this method.
	function updateOptionChoicePrices($optionName, $choiceName, $priceAdjustObj){
	
		$optionIndex = $this->_getOptionNameIndex($optionName);
		
		if($optionIndex < 0)
			throw new Exception("Error in method updateOptionChoicePrices.  The Option Name does not exist.");
		
		$choiceIndex = $this->_getOptionChoiceIndex($optionName, $choiceName);
		
		if($choiceIndex < 0)
			throw new Exception("Error in method updateOptionChoicePrices.  The Choice Name does not exist.");
	
		if(strtoupper(get_class($priceAdjustObj)) != strtoupper("PriceAdjustments"))
			throw new Exception("Error in method updateOptionChoicePrices.  Price Adjustment object is not correct.");

		$this->_productOptionsArr[$optionIndex]->choices[$choiceIndex]->PriceAdjustments = $priceAdjustObj;
	}
	
	
	// Pass in a String to notify Production Operators during the boxing stage when the given Choice is selected on the order.  Make sure that the Option Name and Choice exist before calling this method.
	function updateOptionChoiceDescription($optionName, $choiceName, $choiceDescription, $isHTMLformat){
	
		$optionIndex = $this->_getOptionNameIndex($optionName);
		
		if($optionIndex < 0)
			throw new Exception("Error in method updateOptionChoiceDescription.  The Option Name does not exist.");
		
		$choiceIndex = $this->_getOptionChoiceIndex($optionName, $choiceName);
		
		if($choiceIndex < 0)
			throw new Exception("Error in method updateOptionChoiceDescription.  The Choice Name does not exist.");

		if(!is_bool($isHTMLformat))
			throw new Exception("Error in method updateOptionChoiceDescription, Format Flag must be boolean ");
		
		$choiceDescription = trim($choiceDescription);
		if(empty($choiceDescription))
			throw new Exception("Error in method updateOptionChoiceDescription.  The Choice Description can not be left blank.");

		$this->_productOptionsArr[$optionIndex]->choices[$choiceIndex]->ChoiceDescription = $choiceDescription;
		$this->_productOptionsArr[$optionIndex]->choices[$choiceIndex]->ChoiceDescriptionIsHTMLformat = $isHTMLformat;
		
	}
	
	function updateOptionChoiceNameAlias($optionName, $choiceName, $choiceNameAlias){
	
		$optionIndex = $this->_getOptionNameIndex($optionName);
		
		if($optionIndex < 0)
			throw new Exception("Error in method updateOptionChoiceNameAlias.  The Option Name does not exist.");
		
		$choiceIndex = $this->_getOptionChoiceIndex($optionName, $choiceName);
		
		if($choiceIndex < 0)
			throw new Exception("Error in method updateOptionChoiceNameAlias.  The Choice Name does not exist.");
		
		$choiceNameAlias = trim($choiceNameAlias);

		$this->_productOptionsArr[$optionIndex]->choices[$choiceIndex]->ChoiceNameAlias = $choiceNameAlias;
	}
	
	
	// Pass in a String to notify Production Operators during the boxing stage when the given Choice is selected on the order.  Make sure that the Option Name and Choice exist before calling this method.
	function updateOptionChoiceProductionAlert($optionName, $choiceName, $productionAlert){
	
		$optionIndex = $this->_getOptionNameIndex($optionName);
		
		if($optionIndex < 0)
			throw new Exception("Error in method updateOptionChoiceProductionAlert.  The Option Name does not exist.");
		
		$choiceIndex = $this->_getOptionChoiceIndex($optionName, $choiceName);
		
		if($choiceIndex < 0)
			throw new Exception("Error in method updateOptionChoiceProductionAlert.  The Choice Name does not exist.");

		$this->_productOptionsArr[$optionIndex]->choices[$choiceIndex]->productionAlert = $productionAlert;
	}
	
	
	
	
	
	// Pass in a Price Adustment Object.  Make sure that the Option Name and Choice exist before calling this method.
	function updateOptionChoiceQuantityBreakPrices($optionName, $choiceName, $quantityAmount, $priceAdjustObj){
	
		$optionIndex = $this->_getOptionNameIndex($optionName);
		
		if($optionIndex < 0)
			throw new Exception("Error in method updateOptionChoiceQuantityBreakPrices.  The Option Name does not exist.");
		
		$choiceIndex = $this->_getOptionChoiceIndex($optionName, $choiceName);
		
		if($choiceIndex < 0)
			throw new Exception("Error in method updateOptionChoiceQuantityBreakPrices.  The Choice Name does not exist.");
	
		if(strtoupper(get_class($priceAdjustObj)) != strtoupper("PriceAdjustments"))
			throw new Exception("Error in method updateOptionChoiceQuantityBreakPrices.  Price Adjustment object is not correct.");

		if(!$this->checkIfQuantityBreakExistsOnOptionChoice($optionName, $choiceName, $quantityAmount))
			throw new Exception("Error in method updateOptionChoiceQuantityBreakPrices.  The quantity amount does not exist on this choice.");


		for($i=0; $i<sizeof($this->_productOptionsArr[$optionIndex]->choices[$choiceIndex]->QuantityPriceBreaksArr); $i++){
			
			if($this->_productOptionsArr[$optionIndex]->choices[$choiceIndex]->QuantityPriceBreaksArr[$i]->amount != $quantityAmount)
				continue;
	
			$this->_productOptionsArr[$optionIndex]->choices[$choiceIndex]->QuantityPriceBreaksArr[$i]->PriceAdjustments = $priceAdjustObj;
		}
		
	}

	

	// If this option has control performing Artwork Search and replaces... pass in a Search Term and a Replace Term for the given Option/Choice Combo
	// Make sure that the artworkSearchReplaceController has been set before calling this method.
	function addOptionChoiceArtSearchReplace($optionName, $choiceName, $artworkSearch, $artworkReplace){
		
		$optionIndex = $this->_getOptionNameIndex($optionName);
		
		if(empty($artworkSearch))
			throw new Exception("Error in method setOptionChoiceArtSearchReplace... You can not add a blank Artwork Search term for this choice.");
	
		if($optionIndex < 0)
			throw new Exception("Error in method artworkSearchReplaceController.  The Option Name does not exist.");
		
		$choiceIndex = $this->_getOptionChoiceIndex($optionName, $choiceName);
		
		if($choiceIndex < 0)
			throw new Exception("Error in method artworkSearchReplaceController.  The Choice Name does not exist.");
			
		if(!$this->_productOptionsArr[$optionIndex]->artworkSearchReplaceController)
			throw new Exception("Error in method artworkSearchReplaceController.  This option does not have an artwork search/replace controller.");
		
		
		$this->_productOptionsArr[$optionIndex]->choices[$choiceIndex]->addNewSearchAndReplaceRoutine($artworkSearch, $artworkReplace);
	}
	
	// This is usefull if you want to delete a search and replace routine.  Just clear out everything and add in the ones you want.
	function clearOptionChoiceArtSearchReplaces($optionName, $choiceName){
		
		$optionIndex = $this->_getOptionNameIndex($optionName);
	
		if($optionIndex < 0)
			throw new Exception("Error in method clearOptionChoiceArtSearchReplaces.  The Option Name does not exist.");
		
		$choiceIndex = $this->_getOptionChoiceIndex($optionName, $choiceName);
		
		if($choiceIndex < 0)
			throw new Exception("Error in method clearOptionChoiceArtSearchReplaces.  The Choice Name does not exist.");		
		
		$this->_productOptionsArr[$optionIndex]->choices[$choiceIndex]->clearExisitingSearchAndReplaceRoutines();	
	}


	
	// If this option has control over changing the number of sides on an artwork then you can specify the number of sides to be forced.
	// Make sure that the artworkSidesController has been set before calling this method.
	function setOptionChoiceArtworkSideCount($optionName, $choiceName, $artworkSideCount){
		
		$optionIndex = $this->_getOptionNameIndex($optionName);
		
		$artworkSideCount = intval($artworkSideCount);
		
		if($artworkSideCount < 1 || $artworkSideCount > $this->getArtworkSidesCount())
			throw new Exception("Error in method setOptionChoiceArtworkSideCount... The Side Count is out of range.");
	
		if($optionIndex < 0)
			throw new Exception("Error in method setOptionChoiceArtworkSideCount.  The Option Name does not exist.");
		
		$choiceIndex = $this->_getOptionChoiceIndex($optionName, $choiceName);
		
		if($choiceIndex < 0)
			throw new Exception("Error in method setOptionChoiceArtworkSideCount.  The Choice Name does not exist.");
			
		if(!$this->_productOptionsArr[$optionIndex]->artworkSidesController)
			throw new Exception("Error in method setOptionChoiceArtworkSideCount.  You can not set the Side Count on this Choice because the Option has not been defined as an artwork side controller.");
		
		
		$this->_productOptionsArr[$optionIndex]->choices[$choiceIndex]->artworkSideCount = $artworkSideCount;
	}
	
	
	
	// If this option is a "controller" for over changing Variable Images on or off... then you can make this choice be the on/off the switch.
	// Make sure that the controller has been set on the option before calling this method.
	function setOptionChoiceVariableImages($optionName, $choiceName, $varialbleFlag){
		
		$optionIndex = $this->_getOptionNameIndex($optionName);
		
		if(!is_bool($varialbleFlag))
			throw new Exception("Error in method setOptionChoiceVariableImages.  Variable Flag must be boolean.");
	
		if($optionIndex < 0)
			throw new Exception("Error in method setOptionChoiceVariableImages.  The Option Name does not exist.");
		
		$choiceIndex = $this->_getOptionChoiceIndex($optionName, $choiceName);
		
		if($choiceIndex < 0)
			throw new Exception("Error in method setOptionChoiceVariableImages.  The Choice Name does not exist.");
			
		if(!$this->_productOptionsArr[$optionIndex]->variableImageController)
			throw new Exception("Error in method setOptionChoiceVariableImages.  You can not set the variable image flag on this Choice because the Option has not been defined as a variable image controller.");
		
		$this->_productOptionsArr[$optionIndex]->choices[$choiceIndex]->variableImageFlag = $varialbleFlag;
		
	}
	
	
	
	
	// You can specifiy if you want this choice to stay hidden from the customer or if they are to See the Details of the Charge.
	function setOptionChoiceHideAll($optionName, $choiceName, $hideFlag){
		
		$optionIndex = $this->_getOptionNameIndex($optionName);
		
		if(!is_bool($hideFlag))
			throw new Exception("Error in method setOptionChoiceHideAll. Admin Option Flag must be boolean.");
	
		if($optionIndex < 0)
			throw new Exception("Error in method setOptionChoiceVariableImages.  The Option Name does not exist.");
		
		$choiceIndex = $this->_getOptionChoiceIndex($optionName, $choiceName);
		
		if($choiceIndex < 0)
			throw new Exception("Error in method setOptionChoiceHideAll.  The Choice Name does not exist.");
			
		$this->_productOptionsArr[$optionIndex]->choices[$choiceIndex]->hideOptionAllFlag = $hideFlag;
		
	}
	
	
	// You can hide the Option Choice from showing up in long lists, like Open Orders, or User history if it is not significant.
	function setOptionChoiceHideInLists($optionName, $choiceName, $hideFlag){
		
		$optionIndex = $this->_getOptionNameIndex($optionName);
		
		if(!is_bool($hideFlag))
			throw new Exception("Error in method setOptionChoiceHideInLists. Admin Option Flag must be boolean.");
	
		if($optionIndex < 0)
			throw new Exception("Error in method setOptionChoiceHideInLists.  The Option Name does not exist.");
		
		$choiceIndex = $this->_getOptionChoiceIndex($optionName, $choiceName);
		
		if($choiceIndex < 0)
			throw new Exception("Error in method setOptionChoiceHideInLists.  The Choice Name does not exist.");
			
		$this->_productOptionsArr[$optionIndex]->choices[$choiceIndex]->hideOptionInListsFlag = $hideFlag;
	}	
	
	
		

	
	function setOptionChoiceWeightValues($optionName, $choiceName, $baseWeightChange, $projectWeightChange){
		
		$optionIndex = $this->_getOptionNameIndex($optionName);
	
		if($optionIndex < 0)
			throw new Exception("Error in method setOptionChoiceWeightValues.  The Option Name does not exist.");
		
		$choiceIndex = $this->_getOptionChoiceIndex($optionName, $choiceName);
		
		if($choiceIndex < 0)
			throw new Exception("Error in method setOptionChoiceWeightValues.  The Choice Name does not exist.");
					
		$this->_productOptionsArr[$optionIndex]->choices[$choiceIndex]->BaseWeightChange = $baseWeightChange;
		$this->_productOptionsArr[$optionIndex]->choices[$choiceIndex]->ProjectWeightChange = $projectWeightChange;
	}
	
	
	// Set the base price for a certain vendor between 1 and 6
	function setVendorBasePrice($vendorNumber, $amount){
		
		$vendorNumber = intval($vendorNumber);
		$amount = strval(floatval($amount));
		
		if($vendorNumber < 1 || $vendorNumber > 6)
			throw new Exception("Error in method setVendorBasePrice: Vendor number out of range;");
			
		$this->_vendorBasePricesArr[($vendorNumber - 1)] = $amount;
	}
	
	// Set the base price for a certain vendor between 1 and 6
	function setVendorInitialSubtotal($vendorNumber, $amount){
		
		$vendorNumber = intval($vendorNumber);
		$amount = strval(floatval($amount));
		
		if($vendorNumber < 1 || $vendorNumber > 6)
			throw new Exception("Error in method setVendorInitialSubtotal: Vendor number out of range;");
			
		$this->_vendorInitialSubtoalArr[($vendorNumber - 1)] = $amount;
	}
	
	
	
	
	
	
	
	
	
	
	
	
	// ----------------------  Begin methods for removing Pricing and Options --------------------------------------
	
	
	// Removes the QuantityBreak object having the matching quantity.
	// Does not throw an error in the quantity amount does not exist.
	function removeQuantityBreak($quantityAmount){

		// Just in case the Option Names and Price Breaks have not been loaded from the DB yet.
		$this->_loadPricesAndOptions();

		$tempArr = array();
		
		foreach($this->_quantityPriceBreaksArr as $thisQuanBreakObj){
			
			if($thisQuanBreakObj->amount == $quantityAmount)
				continue;
	
			$tempArr[] = $thisQuanBreakObj;
		}
		
		$this->_quantityPriceBreaksArr = array();
		
		foreach($tempArr as $thisQuanBreakObj)
			$this->_quantityPriceBreaksArr[] = $thisQuanBreakObj;
	
	}
	
	
	// Removes a Product Options by name.
	// Does not throw an error in the quantity amount does not exist.
	function removeProductOption($optionName){

		$optionName = strtoupper(trim($optionName));
		
		// Just in case the Option Names have not been loaded from the DB yet.
		$this->_loadPricesAndOptions();
	
		$tempArr = array();
		
		foreach($this->_productOptionsArr as $thisProductOption){
			
			if(strtoupper($thisProductOption->optionName) == $optionName)
				continue;
	
			$tempArr[] = $thisProductOption;
		}
		
		$this->_productOptionsArr = array();
		
		foreach($tempArr as $thisProductOption)
			$this->_productOptionsArr[] = $thisProductOption;
	
	}
	
	
	// Removes the Choice object having the matching quantity.
	// Does not throw an error in the quantity amount does not exist.
	function removeOptionChoice($optionName, $choiceName){

		$optionName = strtoupper(trim($optionName));
		$choiceName = strtoupper(trim($choiceName));
		
		$optionIndex = $this->_getOptionNameIndex($optionName);
		
		if($optionIndex < 0)
			return;
	
		$tempArr = array();
		
		for($i=0; $i<sizeof($this->_productOptionsArr[$optionIndex]->choices); $i++){
			
			if(strtoupper($this->_productOptionsArr[$optionIndex]->choices[$i]->ChoiceName) == $choiceName)
				continue;
			
			$tempArr[] = $this->_productOptionsArr[$optionIndex]->choices[$i];
		}
		
		$this->_productOptionsArr[$optionIndex]->choices = array();
		
		foreach($tempArr as $thisOptionChoice)
			$this->_productOptionsArr[$optionIndex]->choices[] = $thisOptionChoice;
	}
	
	
	// Removes the QuantityBreak object having the matching quantity for the given Option/Choice combo.
	// Does not throw an error in the quantity amount does not exist.
	function removeQuantityBreakFromChoice($optionName, $choiceName, $quantityAmount){
	
		$optionIndex = $this->_getOptionNameIndex($optionName);
		
		if($optionIndex < 0)
			return;
		
		$choiceIndex = $this->_getOptionChoiceIndex($optionName, $choiceName);
		
		if($choiceIndex < 0)
			return;
			
		$tempArr = array();
		
		for($i=0; $i<sizeof($this->_productOptionsArr[$optionIndex]->choices[$choiceIndex]->QuantityPriceBreaksArr); $i++){
			
			if($this->_productOptionsArr[$optionIndex]->choices[$choiceIndex]->QuantityPriceBreaksArr[$i]->amount == $quantityAmount)
				continue;
			
			$tempArr[] = $this->_productOptionsArr[$optionIndex]->choices[$choiceIndex]->QuantityPriceBreaksArr[$i];
		}
		
		$this->_productOptionsArr[$optionIndex]->choices[$choiceIndex]->QuantityPriceBreaksArr = array();
		
		foreach($tempArr as $thisQuanBreakObj)
			$this->_productOptionsArr[$optionIndex]->choices[$choiceIndex]->QuantityPriceBreaksArr[] = $thisQuanBreakObj;
	}
	
	
	// Removes a vendor from the Product Object by the Vendor User ID
	// Does not throw an error if the vendor ID does not exist.
	function removeVendorID($vendorID){

		if(!self::checkIfProductPiggyBackIsWithinUsersDomain($this->_productID))
			return;
	
		$vendorID = intval($vendorID);
		
		for($i=1; $i<=6; $i++){
		
			if($this->getVendorID($i) == $vendorID){
				$this->_vendorIDarr[$i-1] = null;
				break;
			}
		}

	
	}


	
}















// ----------   Classes Used for Holding Pricing Information and Product Options --------------------
// There is not really a need to define these within another File because are usually just used by the Product Class 


class QuantityPriceBreak {
	public $amount;
	public $PriceAdjustments;

	// constructor
	function QuantityPriceBreak($x){
		if(!preg_match("/^\d+$/", $x))
			throw new Exception("The quantity break must be an integer: " . $x);
		$this->amount = $x;
		
		$this->PriceAdjustments = new PriceAdjustments();
	}
}

class PriceAdjustments {

	// Public Properties
	public $CustomerSubtotalChange;
	public $CustomerBaseChange;

	// Private variables 
	private $_vendorSubtotalChangesArr = array();
	private $_vendorBaseChangesArr = array();

	// Constructor (optional) that will allow you to set the values upon creation
	// If you only pass in an integer for the VendorSub, or the VendorBase then it will use those values for Vendor #1
	// You can also pass in an array of amounts.  The first element in the array will go to Vendor #1, the Second will go to Vendor #2, etc.
	function PriceAdjustments($custSub=0, $custBase=0, $vendSub=0, $vendBase=0){
		
		$custSub = strval(floatval($custSub));
		$custBase = strval(floatval($custBase));
		
		$this->CustomerSubtotalChange = $custSub;
		$this->CustomerBaseChange = $custBase;

		if(is_array($vendSub)){
			for($i=1; $i<= sizeof($vendSub); $i++)
				$this->setVendorSubtotalChange($i, $vendSub[$i-1]);
		}
		else{
			$this->setVendorSubtotalChange(1, $vendSub);
		}

		if(is_array($vendBase)){
			for($i=1; $i<= sizeof($vendBase); $i++)
				$this->setVendorBaseChange($i, $vendBase[$i-1]);
		}
		else{
			$this->setVendorBaseChange(1, $vendBase);
		}
	}

	// -------  Setter Methods --------
	// Vendor Number passed into this method are 1 based
	function setVendorSubtotalChange($vendorNumber, $amount){
		
		if($vendorNumber < 1 || $vendorNumber > 6)
			throw new Exception("Vendor number out of range in method call to setVendorSubtotalChange");
		
		$amount = strval(floatval($amount));
		
		$this->_vendorSubtotalChangesArr[$vendorNumber - 1] = $amount;
	}
	function setVendorBaseChange($vendorNumber, $amount){
		
		if($vendorNumber < 1 || $vendorNumber > 6)
			throw new Exception("Vendor number out of range in method call to setVendorSubtotalChange");
		
		$amount = strval(floatval($amount));
		
		$this->_vendorBaseChangesArr[$vendorNumber - 1] = $amount;
	}
	function setCustomerBaseChange($custBase){
	
		$custBase = strval(floatval($custBase));
	
		$this->CustomerBaseChange = $custBase;
	}
	function setCustomerSubtotalChange($custSub){
	
		$custSub = strval(floatval($custSub));
	
		$this->CustomerSubtotalChange = $custSub;
	}
	
	// -------  Getter Methods --------
	// Vendor Number passed into this method are 1 based
	// If a Subtotal change has been been defined for the vendor, this method will return Zero.
	function getVendorSubtotalChange($vendorNumber){
		if(!isset($this->_vendorSubtotalChangesArr[$vendorNumber - 1]))
			return 0;
		else
			return $this->_vendorSubtotalChangesArr[$vendorNumber - 1];
	}
	function getVendorBaseChange($vendorNumber){
		if(!isset($this->_vendorBaseChangesArr[$vendorNumber - 1]))
			return 0;
		else
			return $this->_vendorBaseChangesArr[$vendorNumber - 1];
	}
	
	function getCustomerSubtotalChange(){
		if(empty($this->CustomerSubtotalChange))
			return 0;
		return $this->CustomerSubtotalChange;
	}
	function getCustomerBaseChange(){
		if(empty($this->CustomerBaseChange))
			return 0;
		return $this->CustomerBaseChange;
	}
}



// Contains information on how to swtich to another Product.
class ProductSwitch {
	
	public $switchTitle;
	public $switchLinkSubject;
	public $switchDescription;
	public $switchDescriptionIsHTML;
}




// Product Option can be "Card Stock"
class ProductOption {
	
	public $optionID;
	public $optionName;
	public $optionNameAlias;
	public $optionDescription;
	public $optionDescriptionIsHTMLformat;

	public $adminOptionController;
	public $artworkSidesController;
	public $variableImageController;
	public $optionInCommonForProduction;
	public $artworkSearchReplaceController;
	public $couponDiscountExempt;
	

	// This should be an array of objects ... OptionChoice
	public $choices = array();
	
	// Constructor
	function ProductOption(){
	
		$this->adminOptionController = false;
		$this->artworkSidesController = false;
		$this->variableImageController = false;
		$this->optionInCommonForProduction = false;
		$this->artworkSearchReplaceController = false;
		$this->couponDiscountExempt = false;
		
		$this->choices = array();
		
	}
	
	// Returns an Array of Choices Names in a zero based array.
	function getChoiceNamesArr(){
		
		$retArr = array();
		foreach($this->choices as $choiceObj)
			$retArr[] = $choiceObj->ChoiceName;
			
		return $retArr;
	}
	
	function getChoiceNamesArrNotHiddenArr(){
		$retArr = array();
		foreach($this->choices as $choiceObj){
			if($choiceObj->hideOptionAllFlag)
				continue;
			$retArr[] = $choiceObj->ChoiceName;
		}
			
		return $retArr;	
		
	}
}


class OptionChoice {
	
	public $ChoiceName;
	public $ChoiceNameAlias;
	public $ChoiceDescription;
	public $ChoiceDescriptionIsHTMLformat;  // Boolean
	public $BaseWeightChange = 0;	// How much weight the option adds to an individual unit (such as 1 business card)
	public $ProjectWeightChange = 0;	// How much the option choice will affect the overal weight of the project (regardless of quantity)

	public $artworkSideCount;	 // If the Product Option artworkSidesController is set to True... then this will contain the number of artwork sides on the artwork.
	public $variableImageFlag;  // If the Product option variableImageController is set to True ... then this choice will tell us whether we should be turning the option on or off.
	
	public $hideOptionAllFlag;	// Will tell us if we should show the Option to the user.  Normally you chose this option when the Choice makes no difference to the total.
	public $hideOptionInListsFlag;	// Omit the Option/Choice if it is being displayed in a long list, like Open Orders, or User Order History.
	
	public $artworkStringSearchFor;	// If the option controller "artworkSearchReplaceController" is set to true... then these will contain the search/replace strings. 
	public $artworkStringReplaceWith;
	
	public $PriceAdjustments;	// Will contain a Price adjustments object.
	
	public $productionAlert;	// Will contain a String if having this Option/Choice selected should alert the production operator with a message.
	

	// This should be an array of objects ... QuantityPriceBreak
	public $QuantityPriceBreaksArr = array();
	
	
	// Constructor
	function OptionChoice(){
	
		$this->BaseWeightChange = 0;
		$this->ProjectWeightChange = 0;
		$this->artworkSideCount = 1;	// By default, start out with 1 side... just in case the "SideCount Option Controller" gets enabled later on, you don't want Zero side counts lingering. 
		$this->variableImageFlag = false;
		$this->hideOptionAllFlag = false; 
		$this->hideOptionInListsFlag = false; 
		$this->artworkStringSearchFor = null;
		$this->artworkStringReplaceWith = null;
		$this->productionAlert = null;
		
		$this->QuantityPriceBreaksArr = array();
		
		$this->PriceAdjustments = new PriceAdjustments();

	}
	

	
	// This Choice Selection may host a number of Search & Replace routines.
	// Internally they are separated by Double Pipe Sybmols.
	// This method will return an array of Hashes defining the Search and Replace Definitions.
	// The hash inside of each array element will have 2 keys.. "Search" and "Replace"
	function getSearchAndReplaceRoutines(){
	
		// The 2 variables have double pipe separations in parallel arrays.
		$searchForArr = explode("||", $this->artworkStringSearchFor);
		$replaceWithArr = explode("||", $this->artworkStringReplaceWith);
		
		$retArr = array();
		
		for($i=0; $i<sizeof($searchForArr); $i++){
			
			if(empty($searchForArr[$i]))
				continue;
			
			if(isset($replaceWithArr[$i]))
				$replaceTerm = $replaceWithArr[$i];
			else
				$replaceTerm = "";

			$retArr[] = array("Search"=>$searchForArr[$i], "Replace"=>$replaceTerm);
		}
		
		return $retArr;

	}
	
	function clearExisitingSearchAndReplaceRoutines(){
		$this->artworkStringSearchFor = null;
		$this->artworkStringReplaceWith = null;
	}
	
	function addNewSearchAndReplaceRoutine($searchFor, $replaceWith){
	
		if(empty($searchFor))
			return;
		
		if(!empty($this->artworkStringSearchFor)){
			$this->artworkStringSearchFor .= "||";
			$this->artworkStringReplaceWith .= "||";
		}
		
		$this->artworkStringSearchFor .= $searchFor;
		$this->artworkStringReplaceWith .= $replaceWith;

	}

}




?>