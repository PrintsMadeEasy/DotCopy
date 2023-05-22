<?

class ContentCategory extends ContentBase {

	private $_dbCmd;
	
	private $_contentCategoryLoadedFlag;
	private $_imageError;
	private $_dbArr = array();
	
	private $_imageLoadedIntoObjectFlag;
	private $_imageSetIntoObject;


	// Match our fields in the DB
	private $_CategoryID;

	private $_Image;
	private $_ImageHyperlink;
	private $_ImageAlign;
	private $_ProductID;
	private $_ParentHyperlink;
	private $_ParentLinkDesc;



	
	###-- Constructor --###
	
	function ContentCategory(DbCmd $dbCmd){

		$this->_dbCmd = $dbCmd;
		
		$this->_contentCategoryLoadedFlag = false;
		
		// Default Image alignment to the top.
		$this->_ImageAlign = "T";
		
		// To help with efficiency... the Image Bin Data is not loaded from the DB until it is requested from GetMethod.
		$this->_imageLoadedIntoObjectFlag = false;
		$this->_imageSetIntoObject = false;
	}
	
	// Returns NULL if the content category ID does not exist.
	static function getDomainIDofContentCategory(DbCmd $dbCmd, $categoryID){
		
		$dbCmd->Query("SELECT DomainID FROM contentcategories WHERE ID=" . intval($categoryID));
		return $dbCmd->GetValue();
	}
	
	
	// Static method to remove an Image from a ContentCategory ID
	static function RemoveImage(DbCmd $dbCmd, $categoryID){
	
		$thisContentObj = new ContentCategory($dbCmd);
		if(!$thisContentObj->checkIfContentIDexists($categoryID))
			throw new Exception("Error removing the Image.  The content Category does not exist.");
	
		$dbCmd->UpdateQuery("contentcategories", array("Image"=>null), "ID=$categoryID");
	}


	// Static method to remove the ContentCategory
	static function RemoveContent(DbCmd $dbCmd, $categoryID){
	
		$thisContentObj = new ContentCategory($dbCmd);
		if(!$thisContentObj->checkIfContentIDexists($categoryID))
			throw new Exception("Error removing the Content Category.  The content Category does not exist.");
			
		$contentIDsArr = ContentCategory::GetContentItemsWithinCategory($dbCmd, $categoryID);
		if(!empty($contentIDsArr))
			throw new Exception("Error removing the Content Category.  There can not be any Children within this category when attempting to delete.");
	
		$dbCmd->Query("DELETE FROM contentcategories WHERE ID=$categoryID");
	}
	
	

	// Static method to fetch all of the Content Item IDs within a particular category.
	// returns an empty array if there are none.
	static function GetContentItemsWithinCategory(DbCmd $dbCmd, $categoryID){
	
		$thisContentObj = new ContentCategory($dbCmd);
		if(!$thisContentObj->checkIfContentIDexists($categoryID))
			throw new Exception("Error fetching the content items within the Content Category.  The content Category does not exist.");
	
	
		$retArr = array();
		
		$dbCmd->Query("SELECT ID FROM contentitems WHERE ContentCategoryID=$categoryID ORDER BY Title ASC");
		while($contentID = $dbCmd->GetValue())
			$retArr[] = $contentID;
		
		return $retArr;
	}
	




	// A static method to get all content Categories.  The key to the Array is the Category ID
	static function getAllCategories(DbCmd $dbCmd){
		
		$retArr = array();
		
		$dbCmd->Query("SELECT ID, Title FROM contentcategories WHERE DomainID=" . Domain::oneDomain() . " ORDER BY Title ASC");
		
		while($row = $dbCmd->GetRow())
			$retArr[$row["ID"]] = $row["Title"];
		
		return $retArr;
	}
	

	// -------------------------------  END Static Methods ----------------------------------
	
	
	
	
	// Returns True if it Loaded OK... False otherwise.
	function loadContentByTitle($categoryTitle){
	
		$categoryTitle = trim($categoryTitle);
	
		if(!$this->checkIfContentTitleExists($categoryTitle)){
			return false;
		}
		else{
			$categoryTitle = $this->filterTitle($categoryTitle);
			
			$this->_dbCmd->Query("SELECT ID FROM contentcategories WHERE DomainID=" . Domain::oneDomain() . " AND Title = \"" . DbCmd::EscapeSQL($categoryTitle) . "\"");
			$categoryID = $this->_dbCmd->GetValue();
			
			return $this->loadContentByID($categoryID);
		}
	}
	
	// Returns True if it loads OK...False Otherwise.
	function loadContentByID($contentID){
	
		$this->_EnsureDigit($contentID);
		
		$this->_dbCmd->Query("SELECT ID, Title, Description, DescriptionFormat, ImageHyperlink, ImageAlign, ProductID, DescriptionBytes, Links, HeaderHTML, ActiveFlag, 
					ParentHyperlink, ParentLinkDesc, CreatedByUserID, LastEditedByUserID, DomainID, UNIX_TIMESTAMP(CreatedOn) AS CreatedOn, UNIX_TIMESTAMP(LastEditedOn) AS LastEditedOn 
					FROM contentcategories WHERE DomainID=" . Domain::oneDomain() . " AND ID=" . $contentID);
		
		if($this->_dbCmd->GetNumRows() == 0)
			return false;
			
		$row = $this->_dbCmd->GetRow();
		
		$this->_Title = $row["Title"];
		$this->_Description = $row["Description"];
		$this->_DescriptionFormat = $row["DescriptionFormat"];
		$this->_DescriptionBytes = $row["DescriptionBytes"];
		$this->_ImageHyperlink = $row["ImageHyperlink"];
		$this->_ImageAlign = $row["ImageAlign"];
		$this->_ProductID = $row["ProductID"];
		$this->_ParentHyperlink = $row["ParentHyperlink"];
		$this->_ParentLinkDesc = $row["ParentLinkDesc"];
		$this->_CreatedByUserID = $row["CreatedByUserID"];
		$this->_CreatedOn = $row["CreatedOn"];
		$this->_LastEditedByUserID = $row["LastEditedByUserID"];
		$this->_LastEditedOn = $row["LastEditedOn"];
		$this->_Links = $row["Links"];
		$this->_DomainID = $row["DomainID"];
		$this->_CategoryID = $row["ID"];
		$this->_HeaderHTML = $row["HeaderHTML"];
		$this->_ActiveFlag = $row["ActiveFlag"] == "N" ? false : true;
		
		
		$this->_contentCategoryLoadedFlag = true;
		
		return true;
	
	}
	

	// Returns true if there are any Content Items belonging to this Category.
	function checkForChildrenWithinCategory(){
	
		if(!$this->_contentCategoryLoadedFlag)
			throw new Exception("Can not check for content items within this category until a category has been loaded.");
			
		$this->_dbCmd->Query("SELECT COUNT(*) FROM contentitems WHERE DomainID=" . Domain::oneDomain() . " AND ContentCategoryID=" . $this->_CategoryID);
		
		if($this->_dbCmd->GetValue() == 0)
			return false;
		else
			return true;
	}
	
	

	function checkIfContentIDexists($contentID){
	
		$this->_EnsureDigit($contentID);
		
		$this->_dbCmd->Query("SELECT COUNT(*) FROM contentcategories WHERE DomainID=" . Domain::oneDomain() . " AND ID=" . $contentID);
		
		if($this->_dbCmd->GetNumRows() == 0)
			return false;
		else
			return true;
		
	}
	
	// Returns 0 if the title does not exist.  Otherwise returns the content ID that the Content Title belongs to.
	function checkIfContentTitleExists($contentTitle){
	
		$contentTitle = trim($contentTitle);
		$contentTitle = $this->filterTitle($contentTitle);
		
		if(strlen($contentTitle) > 250)
			throw new Exception("error in method checkIfContentTitleExists.  The length can not be over 250 characters");
			
		
		$this->_dbCmd->Query("SELECT ID FROM contentcategories WHERE DomainID=" . Domain::oneDomain() . " AND Title = \"" . DbCmd::EscapeSQL($contentTitle) . "\"");
		
		if($this->_dbCmd->GetNumRows() == 0)
			return 0;
		else
			return $this->_dbCmd->GetValue();
	}
	
	
	// Make sure to set all of the member variables...
	// Call this method and it will return the new ContentID from the DB.
	// You can't load a ContentCategoryID and then call this method.
	// Pass in the UserID who is adding the content.
	function insertNewContentCategory($userID){
	
		if($this->_contentCategoryLoadedFlag)
			throw new Exception("Error inserting new Content Category.  You can not add a new category if the Object has already loaded an existing Content Category ID.");
					
		$this->_CreatedByUserID = $userID;
		$this->_CreatedOn = time();
		
		$this->_populateDBfields();
		
		if($this->checkIfContentTitleExists($this->_Title))
			throw new Exception("Can not create a new Category with this title because it is already being used in another Category.");

		// Domains can not be switched once they have been assigned to a content piece.
		$arrayToInsert = $this->_dbArr;
		$arrayToInsert["DomainID"] = Domain::oneDomain();
		
		$this->_CategoryID = $this->_dbCmd->InsertQuery("contentcategories", $arrayToInsert);
		
		$this->_contentCategoryLoadedFlag = true;
		
		return $this->_CategoryID;
	
	}
	
	
	
	// Make sure to set all of the member variables...
	// Call this method and it will return the new ContentID from the DB.
	// You can't load a ContentCategoryID and then call this method.
	// Pass in the UserID who is editing the content.
	function updateContentCategory($userID){
	
		if(!$this->_contentCategoryLoadedFlag)
			throw new Exception("You can not update a Content Category unless one has already been loaded.");

		$this->_LastEditedByUserID = $userID;
		$this->_LastEditedOn = time();
					
		$this->_populateDBfields();
		
		$existingContentTitle = $this->checkIfContentTitleExists($this->_Title);
		
		if($existingContentTitle != 0 && $existingContentTitle != $this->_CategoryID)
			throw new Exception("Can not Change the Title on this Content Category because it is already being used in another Category.");
		
		$this->_dbCmd->UpdateQuery("contentcategories", $this->_dbArr, "ID=" . $this->_CategoryID);
	
	}
	
	
	
	//-----------   GET Methods ---------------------------------------------------------------------------
	

	
	function getImageHyperlink(){
		$this->_ensureContentLoaded();
		return $this->_ImageHyperlink;
	}
	
	
	// Returns 1 or two characters describing the alignment of the Image.
	// "TL" = top left, "T" = top, "BL" = Bottom Left, "TC" = top center
	function getImageAlign(){
		$this->_ensureContentLoaded();
		return $this->_ImageAlign;
	}
	
	
	function getProductID(){
		$this->_ensureContentLoaded();
		return $this->_ProductID;
	}
	
	
	function getParentHyperlink(){
		$this->_ensureContentLoaded();
		return $this->_ParentHyperlink;
	}
	
	function getParentLinkDesc(){
		$this->_ensureContentLoaded();
		return $this->_ParentLinkDesc;
	}
	
	function getImageError(){
		return $this->_imageError;
	}
	
	// returns True if an image has been stored for the given Content Piece
	// It does so without loading the Image into memory.
	function checkIfImageStored(){
		$this->_ensureContentLoaded();
		
		if($this->_imageLoadedIntoObjectFlag){
			return !empty($this->_Image);
		}
		else{
			$this->_dbCmd->Query("SELECT COUNT(*) FROM contentcategories WHERE DomainID=" . Domain::oneDomain() . " AND Image IS NOT NULL AND ID=" . $this->_CategoryID);
			
			if($this->_dbCmd->GetValue() == 0)
				return false;
			else
				return true;
		}
		
	}
	
	
	// If the image hasn't been loaded... it will fetch it from the DB.
	function &getImage(){
	
		$this->_ensureContentLoaded();
	
		if($this->_imageLoadedIntoObjectFlag){
			return $this->_Image;
		}
		else{
			$this->_dbCmd->Query("SELECT Image FROM contentcategories WHERE DomainID=" . Domain::oneDomain() . " AND ID=" . $this->_CategoryID);
			$this->_Image = $this->_dbCmd->GetValue();
			
			$this->_imageLoadedIntoObjectFlag = true;
			
			return $this->_Image;
		}
	}
	
	
	// Returns a URL for the given Image.

	// For Apache on the Unix server we want the actual URL to be masked through .htaccess file... so that the search engines doesn't know that the image is stored in the DB.
	// Make sure that there is an image stored for this Content Category ID or the method call will Fail

	function getURLforImage($doNotShowStaticFlag = false){
		
		if(!$this->checkIfImageStored())
			throw new Exception("Error in Method getURLforImage.  No image is stored with this object.");
		
		if($doNotShowStaticFlag || Constants::GetDevelopmentServer()){
			$urlForDownload = "content_image.php?contentType=category&id=" . $this->_CategoryID . "&noache=" . $this->getDateLastModified();
		}
		else{
			$websiteUrlForDomain = Domain::getWebsiteURLforDomainID(Domain::oneDomain());
			$urlForDownload = "http://$websiteUrlForDomain/images/" . urlencode($this->_Title) . "_CC.jpg";
		}
		
		return $urlForDownload;
	
	}
	
	
	// Gets a URL that will bring up the given content piece
	// For Apache on the Unix server we want the actual URL to be masked through .htaccess file... so that the searching engines think it is a static HTML page. 
	function getURLforContent($doNotShowStaticFlag = false){
		
		$this->_ensureContentLoaded();
		
		$websiteUrlForDomain = Domain::getWebsiteURLforDomainID(Domain::oneDomain());
		
		if($doNotShowStaticFlag || Constants::GetDevelopmentServer()){
			$urlForDisplay = "content_view.php?contentType=category&contentID=" . urlencode($this->_CategoryID) . "&noache=" . $this->getDateLastModified();
		}
		else{
			$urlForDisplay = "http://$websiteUrlForDomain/cc/" . urlencode($this->getTitle());
		}
		
		return $urlForDisplay;
	
	}	
	
	
	
	// Returns the number of content items that have this Content Category as the parent.

	function countOfContentItemsUnder(){
	
		if(!$this->_contentCategoryLoadedFlag)
			throw new Exception("Can not check the count of content items within this category until a category has been loaded.");
			
		$this->_dbCmd->Query("SELECT COUNT(*) FROM contentitems WHERE DomainID=" . Domain::oneDomain() . " AND ContentCategoryID=" . $this->_CategoryID);
		
		return $this->_dbCmd->GetValue();
	}
	
	function getBytesOfContentItemsUnder(){
	
		$this->_ensureContentLoaded();
			
		$this->_dbCmd->Query("SELECT SUM(DescriptionBytes) FROM contentitems WHERE DomainID=" . Domain::oneDomain() . " AND ContentCategoryID=" . $this->_CategoryID);
		return $this->_dbCmd->GetValue();
	}
	
	// Returns the number of content templates underneath all of the content items... which are underneath this content category.
	function countOfContentTemplatesUnder(){
	
		if(!$this->_contentCategoryLoadedFlag)
			throw new Exception("Can not check the count of content items within this category until a category has been loaded.");
			
		
		$contentItemIDsArr = array();
		
		$this->_dbCmd->Query("SELECT ID FROM contentitems WHERE DomainID=" . Domain::oneDomain() . " AND ContentCategoryID=" . $this->_CategoryID);
		
		while($thisItemID = $this->_dbCmd->GetValue())
			$contentItemIDsArr[] = $thisItemID;
		
		
		$templateCount = 0;
		
		foreach($contentItemIDsArr as $thisContentItemID){
		
			$this->_dbCmd->Query("SELECT COUNT(*) FROM contenttemplates WHERE DomainID=" . Domain::oneDomain() . " AND ContentItemID=" . $thisContentItemID);
			$templateCount += $this->_dbCmd->GetValue();
		}
		
		return $templateCount;
	}
	
	
	// Returns the total number of Bytes of content templates  underneath all of the content items... which are underneath this content category.
	function getBytesOfContentTemplatesUnder(){
	
		if(!$this->_contentCategoryLoadedFlag)
			throw new Exception("Can not check the count of content items within this category until a category has been loaded.");
			
		
		$contentItemIDsArr = array();
		
		$this->_dbCmd->Query("SELECT ID FROM contentitems WHERE DomainID=" . Domain::oneDomain() . " AND ContentCategoryID=" . $this->_CategoryID);
		
		while($thisItemID = $this->_dbCmd->GetValue())
			$contentItemIDsArr[] = $thisItemID;
		
		
		$totalBytes = 0;
		
		foreach($contentItemIDsArr as $thisContentItemID){
		
			$this->_dbCmd->Query("SELECT SUM(DescriptionBytes) FROM contenttemplates WHERE DomainID=" . Domain::oneDomain() . " AND ContentItemID=" . $thisContentItemID);
			$totalBytes += $this->_dbCmd->GetValue();
		}
		
		return $totalBytes;
	}
	
	
	// Templates don't have footers... but just for Polymorphism sake... keep the method here.
	function getFooter(){
		return "";
	}
	function footerIsHTMLformat(){
		return false;
	}
	
	// The categories don't have any parents... so this method always return true;
	function checkActiveParent(){
		return true;
	}
	
	
	// ------------   SET Methods ------------------------------------------------------------------------

	
	
	
	// Returns True or False depdending on wheither the Image was OK for upload.
	// If it returns false... be sure to check the function getImageError for an explanation
	// You can not set an image to Null here in order to remove it.  You have to call the method RemoveImage
	function setImage(&$binImageData){
	
		if(empty($binImageData))
			return true;
	
		// Start off with a default error message that will get overriden when we are sure there isn't an error.
		$this->_imageError = "An error message has not been defined while setting the Content Category Image.";

		$this->_imageError = $this->checkImage($binImageData);
		
		if(!empty($this->_imageError))
			return false;
		
		// Make sure that we mark the image as "Loaded into the Object".
		$this->_imageLoadedIntoObjectFlag = true;
		
		// So our Poplate DB method knows that we have inserted an image.
		$this->_imageSetIntoObject = true;
		
		$this->_Image = $binImageData;
		
		return true;
			
	}
	
	// Returns NULL if there are no errors with the image... otherwise returns an Error message.
	function checkImage(&$binImageData){
	
		if(strlen($binImageData) > 1000000)
			return "The file size of the image can not be greater than 1MB.";
		
		// Write the Temporary Image to disk.
		$tempImageName = tempnam(Constants::GetTempDirectory(), "IMGCONT");

		// Make sure that we have permission to modify it with system comands as "nobody" webserver user 
		chmod ($tempImageName, 0666);

		// Put image data into the temp file 
		$fp = fopen($tempImageName, "w");
		fwrite($fp, $binImageData);
		fclose($fp);
		
		
		$ImageFormat = ImageLib::GetImageFormatFromFile($tempImageName);
		
		if($ImageFormat != "JPEG"){
			unlink($tempImageName);
			return "Only JPEG images may be uploaded.";
		}
		
		
		$DimHash = ImageLib::GetDimensionsFromImageFile($tempImageName);

		if(empty($DimHash["Width"]) || empty($DimHash["Height"])){
			unlink($tempImageName);
			return "The Image height/width has not been defined trying to upload a Content Category Image";
		
		}

		// 4000 pixels is more than enough to cover 13 inches at 300 DPI.
		if($DimHash["Width"] > 1500 || $DimHash["Height"] > 1500){
			unlink($tempImageName);
			return "The Image height/width for content category images may not exeed 1500 pixels in either direction.";
		}
		
		unlink($tempImageName);
		
		return null;
	}
	
	
	function setImageHyperlink($x){
		
		$x = trim($x);
	
		if(strlen($x) > 250)
			throw new Exception("Setting an Image Hyperlink can not have more than 250 characters.");
		
		$this->_ImageHyperlink = $x;
	}
	
	function setImageAlign($x){
		
		$x = trim($x);
	
		if(!in_array($x, array("T", "TL", "TC", "TR", "B", "BL", "BC", "BR")))
			throw new Exception("The Image alignment does not have a correct value.");
		
		$this->_ImageAlign = $x;
	}
	
	
	function setProductID($x){
		
		$x = trim($x);
	
		if(empty($x))
			$x = null;
			
		if(!preg_match("/^\d+$/", $x) && $x !== null)
			throw new Exception("Error setting the Product ID for the Content Category.  The Product ID is not in a proper format.");
		
		$this->_ProductID = $x;
	}
	
	
	function setParentHyperlink($x){
		
		$x = trim($x);
	
		if(strlen($x) > 250)
			throw new Exception("Setting the Parent Hyperlink can not have more than 250 characters.");
		
		$this->_ParentHyperlink = $x;
	}
	
	
	function setParentLinkDesc($x){
		
		$x = trim($x);
			
		if(strlen($x) > 250)
			throw new Exception("Setting a ParentLink Description can not have more than 250 characters.");
		
		$this->_ParentLinkDesc = $x;
	}
	
	
	
	// ------------   PRIVATE Methods --------------------------------------------------------------------------
	
	function _ensureContentLoaded(){
	
		if(!$this->_contentCategoryLoadedFlag)
			throw new Exception("The Content Category must be loaded before calling this method.");
	}
	
	
	
		
	function _populateDBfields(){

		$errMsg = "";
		if(empty($this->_Title))
			$errMsg = "Missing Title: ";
		if(empty($this->_Description))
			$errMsg = "Missing Description: ";
		if(empty($this->_DescriptionFormat))
			$errMsg = "Missing Description Format: ";

		if(!empty($errMsg))
			throw new Exception("Error inserting new content Category.  Some fields are missing: " . $errMsg);
	
		if(empty($this->_ParentLinkDesc) && !empty($this->_ParentHyperlink))
			throw new Exception("If you are setting the a Parent Hyperlink.... then you must also include a Parent Hyperlink description.");

		$this->_dbArr = array();
		$this->_dbArr["Title"] = $this->_Title;
		$this->_dbArr["Description"] = $this->_Description;
		$this->_dbArr["DescriptionFormat"] = $this->_DescriptionFormat;
		$this->_dbArr["DescriptionBytes"] = $this->_DescriptionBytes;
		$this->_dbArr["ImageHyperlink"] = $this->_ImageHyperlink;
		$this->_dbArr["ImageAlign"] = $this->_ImageAlign;
		$this->_dbArr["ProductID"] = $this->_ProductID;
		$this->_dbArr["ParentHyperlink"] = $this->_ParentHyperlink;
		$this->_dbArr["ParentLinkDesc"] = $this->_ParentLinkDesc;
		$this->_dbArr["CreatedByUserID"] = $this->_CreatedByUserID;
		$this->_dbArr["LastEditedByUserID"] = $this->_LastEditedByUserID;
		$this->_dbArr["CreatedOn"] = $this->_CreatedOn;
		$this->_dbArr["LastEditedOn"] = $this->_LastEditedOn;
		$this->_dbArr["Links"] = $this->_Links;
		$this->_dbArr["HeaderHTML"] = $this->_HeaderHTML;
		$this->_dbArr["ActiveFlag"] = $this->_ActiveFlag ? "Y" : "N";
		

		// Don't save the Image to the DB unless it has been set explicitly
		// You can not remove an image by setting the value you Null. You have to call the RemoveImage Method.
		if($this->_imageSetIntoObject)
			$this->_dbArr["Image"] = $this->_Image;

	}


}



?>