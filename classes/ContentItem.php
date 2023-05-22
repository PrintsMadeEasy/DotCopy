<?

class ContentItem extends ContentBase {

	private $_dbCmd;
	
	private $_contentItemLoadedFlag;
	private $_imageError;
	private $_dbArr = array();
	
	private $_imageLoadedIntoObjectFlag;
	private $_imageSetIntoObject;


	// Match our fields in the DB
	private $_ItemID;
	
	private $_Footer;
	private $_FooterFormat;
	private $_Image;
	private $_ImageHyperlink;
	private $_ImageAlign;

	private $_ContentCategoryID;

	
	###-- Constructor --###
	
	function ContentItem(DbCmd $dbCmd){

		$this->_dbCmd = $dbCmd;
		
		$this->_contentItemLoadedFlag = false;
		
		// Default Image alignment to the top.
		$this->_ImageAlign = "T";
		
		$this->_FooterFormat = "T";
		
		// To help with efficiency... the Image Bin Data is not loaded from the DB until it is requested from GetMethod.
		$this->_imageLoadedIntoObjectFlag = false;
		$this->_imageSetIntoObject = false;
	}
	
	// Returns NULL if the content category ID does not exist.
	static function getDomainIDofContentItem(DbCmd $dbCmd, $contentItemID){
		
		$dbCmd->Query("SELECT DomainID FROM contentitems WHERE ID=" . intval($contentItemID));
		return $dbCmd->GetValue();
	}
	
	// Static method to remove an Image from a ContentItem ID
	function RemoveImage(DbCmd $dbCmd, $contentItemID){
	
		$thisContentObj = new ContentItem($dbCmd);
		if(!$thisContentObj->checkIfContentIDexists($contentItemID))
			throw new Exception("Error removing the Image.  The content Item does not exist.");
	
		$dbCmd->UpdateQuery("contentitems", array("Image"=>null), "ID=$contentItemID");
	}


	// Static method remove the Content Item ID
	function RemoveContent(DbCmd $dbCmd, $contentItemID){
	
		$thisContentObj = new ContentItem($dbCmd);
		if(!$thisContentObj->checkIfContentIDexists($contentItemID))
			throw new Exception("Error removing the Content Item.  The content Item does not exist.");
	
		$dbCmd->Query("DELETE FROM contentitems WHERE DomainID=" . Domain::oneDomain() . " AND ID=$contentItemID");
	}
	
	
	// Static method (for speed) to get the content Title.
	function GetContentItemTitle(DbCmd $dbCmd, $contentItemID){
		
		if(!preg_match("/^\d+$/", $contentItemID))
			throw new Exception("Error in method");
		
		$dbCmd->Query("SELECT Title FROM contentitems WHERE DomainID=" . Domain::oneDomain() . " AND ID=$contentItemID");
	
		if($dbCmd->GetNumRows() == 0)
			return "Title Does Not Exist.";
		else
			return $dbCmd->GetValue();

	}
	
	
	// Static method to fetch all of the Content Template IDs within a particular content Item.
	// returns an empty array if there are none.
	function GetContentTemplatesIDsWithin(DbCmd $dbCmd, $contentItemID){
	
		$thisContentObj = new ContentItem($dbCmd);
		if(!$thisContentObj->checkIfContentIDexists($contentItemID))
			throw new Exception("Error fetching the content template ID's within the Content Item.  The content ItemID does not exist.");
	
	
		$retArr = array();
		
		$dbCmd->Query("SELECT ID FROM contenttemplates WHERE DomainID=" . Domain::oneDomain() . " AND ContentItemID=$contentItemID ORDER BY ID ASC");
		while($contentID = $dbCmd->GetValue())
			$retArr[] = $contentID;
		
		return $retArr;
	}
	
	
	
	function GetNewContentItemsCountByUser(DbCmd $dbCmd, $userID, $startTimeStamp, $endTimeStamp){
		
		$userID = intval($userID);
	
		$start_mysql_timestamp = date("YmdHis", $startTimeStamp);
		$end_mysql_timestamp  = date("YmdHis", $endTimeStamp);
		
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		
		$dbCmd->Query("SELECT COUNT(*) FROM contentitems WHERE 
				". DbHelper::getOrClauseFromArray("DomainID", $passiveAuthObj->getUserDomainsIDs()) . "
				AND CreatedByUserID = '$userID' AND CreatedOn BETWEEN '$start_mysql_timestamp' AND '$end_mysql_timestamp'");
		return $dbCmd->GetValue();
	}
	
	function GetEditContentItemsCountByUser(DbCmd $dbCmd, $userID, $startTimeStamp, $endTimeStamp){
		
		$userID = intval($userID);
	
		$start_mysql_timestamp = date("YmdHis", $startTimeStamp);
		$end_mysql_timestamp  = date("YmdHis", $endTimeStamp);
		
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		
		$dbCmd->Query("SELECT COUNT(*) FROM contentitems WHERE 
					". DbHelper::getOrClauseFromArray("DomainID", $passiveAuthObj->getUserDomainsIDs()) . "
					AND LastEditedByUserID = '$userID' AND LastEditedOn BETWEEN '$start_mysql_timestamp' AND '$end_mysql_timestamp'");
		return $dbCmd->GetValue();
	}
	
	
	
	// --------------------------  End Static Methods --------------------------
	
	
	// Returns True if it Loaded OK... False otherwise.
	function loadContentByTitle($contentTitle){
	
		$contentTitle = trim($contentTitle);
	
		if(!$this->checkIfContentTitleExists($contentTitle)){
			return false;
		}
		else{
			$contentTitle = $this->filterTitle($contentTitle);
			
			$this->_dbCmd->Query("SELECT ID FROM contentitems WHERE DomainID=" . Domain::oneDomain() . " AND Title = \"" . DbCmd::EscapeSQL($contentTitle) . "\"");
			$contentItemID = $this->_dbCmd->GetValue();
		
			return $this->loadContentByID($contentItemID);

		}
	}
	
	// Returns True if it loads OK...False Otherwise.
	function loadContentByID($contentID){
	
		$this->_EnsureDigit($contentID);
		
		$this->_dbCmd->Query("SELECT ID, Title, Description, MetaDescription, MetaTitle, DescriptionFormat, ImageHyperlink, ImageAlign, Footer, FooterFormat, ContentCategoryID, DescriptionBytes, Links, HeaderHTML, ActiveFlag, 
					CreatedByUserID, LastEditedByUserID, DomainID, UNIX_TIMESTAMP(CreatedOn) AS CreatedOn, UNIX_TIMESTAMP(LastEditedOn) AS LastEditedOn 
					FROM contentitems WHERE DomainID=" . Domain::oneDomain() . " AND ID=" . $contentID);
		
		if($this->_dbCmd->GetNumRows() == 0)
			return false;
			
		$row = $this->_dbCmd->GetRow();
		
		$this->_Title = $row["Title"];
		$this->_MetaTitle = $row["MetaTitle"];
		$this->_MetaDescription = $row["MetaDescription"];
		$this->_Description = $row["Description"];
		$this->_DescriptionFormat = $row["DescriptionFormat"];
		$this->_DescriptionBytes = $row["DescriptionBytes"];
		$this->_Footer = $row["Footer"];
		$this->_FooterFormat = $row["FooterFormat"];		
		$this->_ImageHyperlink = $row["ImageHyperlink"];
		$this->_ImageAlign = $row["ImageAlign"];
		$this->_CreatedByUserID = $row["CreatedByUserID"];
		$this->_CreatedOn = $row["CreatedOn"];
		$this->_LastEditedByUserID = $row["LastEditedByUserID"];
		$this->_LastEditedOn = $row["LastEditedOn"];
		$this->_ContentCategoryID = $row["ContentCategoryID"];
		$this->_ItemID = $row["ID"];
		$this->_Links = $row["Links"];
		$this->_DomainID = $row["DomainID"];
		$this->_HeaderHTML = $row["HeaderHTML"];
		$this->_ActiveFlag = $row["ActiveFlag"] == "N" ? false : true;
		
		$this->_contentItemLoadedFlag = true;
		
		return true;
	
	}
	
	
	function checkIfContentIDexists($contentID){
	
		$this->_EnsureDigit($contentID);
		
		$this->_dbCmd->Query("SELECT * FROM contentitems WHERE DomainID=" . Domain::oneDomain() . " AND ID=" . $contentID);
		
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
			
		
		$this->_dbCmd->Query("SELECT ID FROM contentitems WHERE DomainID=" . Domain::oneDomain() . " AND Title = \"" . DbCmd::EscapeSQL($contentTitle) . "\"");
		
		if($this->_dbCmd->GetNumRows() == 0)
			return 0;
		else
			return $this->_dbCmd->GetValue();
	}

	// Returns true if there are any Content Templates belonging to this Content Item.
	function checkForChildrenWithinContentItem(){
	
		if(!$this->_contentItemLoadedFlag)
			throw new Exception("Can not check for children within this content item until the object has been loaded.");
			
		$this->_dbCmd->Query("SELECT COUNT(*) FROM contenttemplates WHERE DomainID=" . Domain::oneDomain() . " AND ContentItemID=" . $this->_ItemID);
		
		if($this->_dbCmd->GetValue() == 0)
			return false;
		else
			return true;
	}
	

	
	// Make sure to set all of the member variables...
	// Call this method and it will return the new ContentID from the DB.
	// You can't load a ContentItemID and then call this method.
	// Pass in the UserID who is adding the content.
	function insertNewContentItem($userID){
	
		if($this->_contentItemLoadedFlag)
			throw new Exception("Error inserting new Content Item.  You can not add a new Item if the Object has already loaded an existing Content Item ID.");
					
		$this->_CreatedByUserID = $userID;
		$this->_CreatedOn = time();
		
		$this->_populateDBfields();
		
		if($this->checkIfContentTitleExists($this->_Title))
			throw new Exception("Can not create a new Content Item with this title because it is already being used in another Content Item.");

		// Domains can not be switched once they have been assigned to a content piece.
		$arrayToInsert = $this->_dbArr;
		$arrayToInsert["DomainID"] = Domain::oneDomain();
		
		$this->_ItemID = $this->_dbCmd->InsertQuery("contentitems", $arrayToInsert);
		
		$this->_contentItemLoadedFlag = true;
		
		return $this->_ItemID;
	
	}
	
	
	
	// Make sure to set all of the member variables...
	// Call this method and it will return the new ContentID from the DB.
	// You can't load a ContentItem and then call this method.
	// Pass in the UserID who is editing the content.
	function updateContentItem($userID){
	
		if(!$this->_contentItemLoadedFlag)
			throw new Exception("You can not update a Content Item unless one has already been loaded.");

		$this->_LastEditedByUserID = $userID;
		$this->_LastEditedOn = time();
					
		$this->_populateDBfields();
		
		$existingContentTitle = $this->checkIfContentTitleExists($this->_Title);
		
		if($existingContentTitle != 0 && $existingContentTitle != $this->_ItemID)
			throw new Exception("Can not Change the Title on this Content Item because it is already being used in another Content Item.");
		
		$this->_dbCmd->UpdateQuery("contentitems", $this->_dbArr, "ID=" . $this->_ItemID);
	
	}
	

	
	
	//-----------   GET Methods ---------------------------------------------------------------------------
	
	
	
	// Pass in True if  you wan the method to convert HTML entities... and also replace line breaks with "<br>"
	// If the message format is in HTML... then setting this flag to true will not have an affect.
	function getFooter($replaceLineBeaksWithHTMLflag = false){
		$this->_ensureContentLoaded();
		
		if($this->footerIsHTMLformat() || !$replaceLineBeaksWithHTMLflag){
			return $this->_Footer;
		}
		else{
		
			$retDesc = WebUtil::htmlOutput($this->_Footer);
			
			$retDesc = preg_replace("/\n/", "<br>\n", $retDesc);
			$retDesc = preg_replace("/\r/", "", $retDesc);
			
			return $retDesc;
		}
	}
	
	
	// This Content Item must belong to a content category... and that content category may or many not have a ProductID that it is linked to.
	// If it is not linked to a product then this method will return null
	function getProductIdLink(){
	
		$this->_ensureContentLoaded();
		
		$contentCategoryObj = new ContentCategory($this->_dbCmd);

		$contentCategoryObj->loadContentByID($this->_ContentCategoryID);
		
		return $contentCategoryObj->getProductID();
	
	}
	
	
	// Returns false if the category (parent) is not active.
	function checkActiveParent(){
		
		$this->_ensureContentLoaded();
		
		$contentCategoryObj = new ContentCategory($this->_dbCmd);

		$contentCategoryObj->loadContentByID($this->_ContentCategoryID);
		
		return $contentCategoryObj->checkIfActive();
	}
	

	
	function footerIsHTMLformat(){
		
		$this->_ensureContentLoaded();
		
		if($this->_FooterFormat == "H")
			return true;
		else if($this->_FooterFormat == "T")
			return false;
		else
			throw new Exception("Illegal description format for content Item: " . $this->_FooterFormat);
		
	}
	
	function getImageHyperlink(){
		$this->_ensureContentLoaded();
		return $this->_ImageHyperlink;
	}
	
	// Return the Content Title if the Meta Title hasn't been set yet.
	function getMetaTitle(){
		$this->_ensureContentLoaded();
		
		if(empty($this->_MetaTitle))
			return $this->_Title;
		else
			return $this->_MetaTitle;
	}
	
	function getMetaDescription(){
		$this->_ensureContentLoaded();
		
		return $this->_MetaDescription;
	}
	
	
	// Returns 1 or two characters describing the alignment of the Image.
	// "TL" = top left, "T" = top, "BL" = Bottom Left, "TC" = top center
	function getImageAlign(){
		$this->_ensureContentLoaded();
		return $this->_ImageAlign;
	}
	
	
	
	function getContentCategoryID(){
		$this->_ensureContentLoaded();
		return $this->_ContentCategoryID;
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
			$this->_dbCmd->Query("SELECT COUNT(*) FROM contentitems WHERE DomainID=" . Domain::oneDomain() . " AND Image IS NOT NULL AND ID=" . $this->_ItemID);
			
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
			$this->_dbCmd->Query("SELECT Image FROM contentitems WHERE DomainID=" . Domain::oneDomain() . " AND ID=" . $this->_ItemID);
			$this->_Image = $this->_dbCmd->GetValue();
			
			$this->_imageLoadedIntoObjectFlag = true;
			
			return $this->_Image;
		}
	}
	
	
	// Returns a URL for the given Image.

	// For Apache on the Unix server we want the actual URL to be masked through .htaccess file... so that the search engines doesn't know that the image is stored in the DB.
	// Make sure that there is an image stored for this Content Item ID or the method call will Fail

	function getURLforImage($doNotShowStaticFlag = false){
		
		if(!$this->checkIfImageStored())
			throw new Exception("Error in Method getURLforImage.  No image is stored with this object.");
		
		if($doNotShowStaticFlag || Constants::GetDevelopmentServer()){
			$urlForDownload = "content_image.php?contentType=item&id=" . $this->_ItemID . "&noache=" . $this->getDateLastModified();
		}
		else{
			$websiteUrlForDomain = Domain::getWebsiteURLforDomainID(Domain::oneDomain());
			$urlForDownload = "http://$websiteUrlForDomain/images/" . urlencode($this->_Title) . "_CI.jpg";
		}
		
		return $urlForDownload;
	
	}
	
	
	// Gets a URL that will bring up the given content piece
	// For Apache on the Unix server we want the actual URL to be masked through .htaccess file... so that the searching engines think it is a static HTML page. 
	function getURLforContent($doNotShowStaticFlag = false){
		
		$this->_ensureContentLoaded();
		
		$websiteUrlForDomain = Domain::getWebsiteURLforDomainID(Domain::oneDomain());
		
		if($doNotShowStaticFlag || Constants::GetDevelopmentServer()){
			$urlForDisplay = "content_view.php?contentType=item&contentID=" . urlencode($this->_ItemID) . "&noache=" . $this->getDateLastModified();
		}
		else{
			$urlForDisplay = "http://$websiteUrlForDomain/ci/" . urlencode($this->getTitle());
		}
		
		return $urlForDisplay;
	
	}	
	

	
	function getContentItemID(){
		
		$this->_ensureContentLoaded();
		
		return $this->_ItemID;
	}
	
	
	
	
	// Returns the number of content templates underneath all this content Item items... which are underneath this content category.
	function countOfContentTemplatesUnder(){
	
		$this->_ensureContentLoaded();
			
		$this->_dbCmd->Query("SELECT COUNT(*) FROM contenttemplates WHERE DomainID=" . Domain::oneDomain() . " AND ContentItemID=" . $this->_ItemID);
		return $this->_dbCmd->GetValue();

	}
	
	
	function getBytesOfContentTemplatesUnder(){
	
		$this->_ensureContentLoaded();
			
		$this->_dbCmd->Query("SELECT SUM(DescriptionBytes) FROM contenttemplates WHERE DomainID=" . Domain::oneDomain() . " AND ContentItemID=" . $this->_ItemID);
		return $this->_dbCmd->GetValue();
	}
	
	
	// ------------   SET Methods ------------------------------------------------------------------------

	
	function setContentCategoryID($x){
	
		$x = trim($x);
	
		if(!preg_match("/^\d+$/", $x))
			throw new Exception("Error setting the content ID.  It is not in the proper format.");

		$contentCategoryObj = new ContentCategory($this->_dbCmd);

		if(!$contentCategoryObj->checkIfContentIDexists($x))
			throw new Exception("Can not create a new Content Item because the Category ID is not defined or invalid.");

		$this->_ContentCategoryID = $x;
	
	}
	
	
	
	function setFooter($x){
		
		$x = trim($x);
		
		$this->_Footer = $x;
	}
	


	function setFooterFormat($x){
		
		$x = trim($x);
		
		if(strtoupper($x) == "HTML")
			$x = "H";
			
		if(strtoupper($x) == "TEXT")
			$x = "T";
	
		if(!in_array($x, array("H", "T")))
			throw new Exception("The footer format can only be HTML or Text.");
		
		$this->_FooterFormat = $x;
	}

	
	// Returns True or False depdending on wheither the Image was OK for upload.
	// If it returns false... be sure to check the function getImageError for an explanation
	// You can not set an image to Null here in order to remove it.  You have to call the method RemoveImage
	function setImage(&$binImageData){
	
		if(empty($binImageData))
			return true;
	
		// Start off with a default error message that will get overriden when we are sure there isn't an error.
		$this->_imageError = "An error message has not been defined while setting the Content Image.";

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
			return "The Image height/width has not been defined trying to upload a Content Image";
		
		}

		// 4000 pixels is more than enough to cover 13 inches at 300 DPI.
		if($DimHash["Width"] > 1500 || $DimHash["Height"] > 1500){
			unlink($tempImageName);
			return "The Image height/width for content images may not exeed 1500 pixels in either direction.";
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
	
	// Return the Content Title if the Meta Title hasn't been set yet.
	function setMetaTitle($x){
		$this->_MetaTitle = $x;
	}
	
	function setMetaDescription($x){
		$this->_MetaDescription = $x;
	}
	

	
	
	
	// ------------   PRIVATE Methods --------------------------------------------------------------------------
	
	function _ensureContentLoaded(){
	
		if(!$this->_contentItemLoadedFlag)
			throw new Exception("The Content Item must be loaded before calling this method.");
	}
	
	

	
		
	function _populateDBfields(){

		$errMsg = "";
		if(empty($this->_Title))
			$errMsg = "The Title was left blank: ";
		if(empty($this->_Description))
			$errMsg = "Missing Description: ";
		if(empty($this->_DescriptionFormat))
			$errMsg = "Missing Description Format: ";

		if(!empty($errMsg))
			throw new Exception("Error inserting new content Item.  Some fields are missing: " . $errMsg);
	

		$this->_dbArr = array();
		$this->_dbArr["Title"] = $this->_Title;
		$this->_dbArr["MetaTitle"] = $this->_MetaTitle;
		$this->_dbArr["MetaDescription"] = $this->_MetaDescription;
		$this->_dbArr["Description"] = $this->_Description;
		$this->_dbArr["DescriptionFormat"] = $this->_DescriptionFormat;
		$this->_dbArr["DescriptionBytes"] = $this->_DescriptionBytes;
		$this->_dbArr["Footer"] = $this->_Footer;
		$this->_dbArr["FooterFormat"] = $this->_FooterFormat;
		$this->_dbArr["ImageHyperlink"] = $this->_ImageHyperlink;
		$this->_dbArr["ImageAlign"] = $this->_ImageAlign;
		$this->_dbArr["ContentCategoryID"] = $this->_ContentCategoryID;
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