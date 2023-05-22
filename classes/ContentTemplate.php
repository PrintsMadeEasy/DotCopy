<?

class ContentTemplate extends ContentBase {

	private $_dbCmd;
	
	private $_contentTemplateItemLoadedFlag;
	private $_imageError;
	private $_dbArr = array();
	

	// Match our fields in the DB
	private $_ItemID;

	private $_ContentItemID;
	private $_TemplateID;
	
	private $_templateImageSize;

	
	###-- Constructor --###
	
	function ContentTemplate(DbCmd $dbCmd){

		$this->_dbCmd = $dbCmd;
		
		$this->_contentTemplateItemLoadedFlag = false;
		
		$this->_templateImageSize = "small";
		
	}
	
	// Returns NULL if the content category ID does not exist.
	static function getDomainIDofContentTemplate(DbCmd $dbCmd, $contentTemplateID){
		
		$dbCmd->Query("SELECT DomainID FROM contenttemplates WHERE ID=" . intval($contentTemplateID));
		return $dbCmd->GetValue();
	}

	// temporarily set your preference as whether you want a small thumbnail or a full preview image.
	// This affects latter function calls to getImage();
	function preferTemplateSize($templateSize){
	
		if(!in_array($templateSize, array("big", "small")))
			throw new Exception("Error in method preferenceTempalteSize... the image size choices are not valid");
			
		$this->_templateImageSize = $templateSize;
	}
	


	// Static method remove the Content Item ID
	static function RemoveContent(DbCmd $dbCmd, $contentTemplateItemID){
	
		$thisContentObj = new ContentTemplate($dbCmd);
		if(!$thisContentObj->checkIfContentIDexists($contentTemplateItemID))
			throw new Exception("Error removing the Content Item.  The content Item does not exist.");
	
		$dbCmd->Query("DELETE FROM contenttemplates WHERE ID=$contentTemplateItemID AND DomainID=" . Domain::oneDomain());
	}
	
	
	
	
	
	static function GetNewContentTemplatesCountByUser(DbCmd $dbCmd, $userID, $startTimeStamp, $endTimeStamp){
		
		$userID = intval($userID);
	
		$start_mysql_timestamp = date("YmdHis", $startTimeStamp);
		$end_mysql_timestamp  = date("YmdHis", $endTimeStamp);
		
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		
		$dbCmd->Query("SELECT COUNT(*) FROM contenttemplates WHERE CreatedByUserID = '$userID' AND 
				". DbHelper::getOrClauseFromArray("DomainID", $passiveAuthObj->getUserDomainsIDs()) . "
				 AND CreatedOn BETWEEN '$start_mysql_timestamp' AND '$end_mysql_timestamp'");
		return $dbCmd->GetValue();
	}
	
	static function GetEditContentTemplatesCountByUser(DbCmd $dbCmd, $userID, $startTimeStamp, $endTimeStamp){
		
		$userID = intval($userID);
	
		$start_mysql_timestamp = date("YmdHis", $startTimeStamp);
		$end_mysql_timestamp  = date("YmdHis", $endTimeStamp);
		
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		
		$dbCmd->Query("SELECT COUNT(*) FROM contenttemplates WHERE LastEditedByUserID = '$userID' AND 
					". DbHelper::getOrClauseFromArray("DomainID", $passiveAuthObj->getUserDomainsIDs()) . "
					 AND LastEditedOn BETWEEN '$start_mysql_timestamp' AND '$end_mysql_timestamp'");
		return $dbCmd->GetValue();
	}
	
	
	
	// ---------------  End Static Methods --------------------------
	
	// Returns True if it Loaded OK... False otherwise.
	function loadContentByTitle($contentTitle){
	
		$contentTitle = trim($contentTitle);
	
		if(!$this->checkIfContentTitleExists($contentTitle)){
			return false;
		}
		else{
			$contentTitle = $this->filterTitle($contentTitle);
			
			$this->_dbCmd->Query("SELECT ID FROM contenttemplates WHERE DomainID=" . Domain::oneDomain() . " AND Title = \"" . DbCmd::EscapeSQL($contentTitle) . "\"");
			$contentTemplateID = $this->_dbCmd->GetValue();
			
			return $this->loadContentByID($contentTemplateID);

		}
	}
	
	
	// Returns True if it loads OK...False Otherwise.
	function loadContentByID($contentID){
	
		$this->_EnsureDigit($contentID);
		
		$this->_dbCmd->Query("SELECT ID, Title, Description, DescriptionFormat, ContentItemID, TemplateID, DescriptionBytes, Links, HeaderHTML, ActiveFlag, 
					CreatedByUserID, LastEditedByUserID, DomainID, UNIX_TIMESTAMP(CreatedOn) AS CreatedOn, UNIX_TIMESTAMP(LastEditedOn) AS LastEditedOn 
					FROM contenttemplates WHERE DomainID=" . Domain::oneDomain() . " AND ID=" . $contentID);
		
		if($this->_dbCmd->GetNumRows() == 0)
			return false;
			
		$row = $this->_dbCmd->GetRow();
		
		$this->_Title = $row["Title"];
		$this->_Description = $row["Description"];
		$this->_DescriptionFormat = $row["DescriptionFormat"];
		$this->_DescriptionBytes = $row["DescriptionBytes"];
		$this->_CreatedByUserID = $row["CreatedByUserID"];
		$this->_CreatedOn = $row["CreatedOn"];
		$this->_Links = $row["Links"];
		$this->_LastEditedByUserID = $row["LastEditedByUserID"];
		$this->_LastEditedOn = $row["LastEditedOn"];
		$this->_ContentItemID = $row["ContentItemID"];
		$this->_TemplateID = $row["TemplateID"];
		$this->_ItemID = $row["ID"];
		$this->_DomainID = $row["DomainID"];
		$this->_HeaderHTML = $row["HeaderHTML"];
		$this->_ActiveFlag = $row["ActiveFlag"] == "N" ? false : true;
		
		$this->_dbCmd->Query("SELECT COUNT(*) FROM artworksearchengine WHERE ID=" . $this->_TemplateID);
		if($this->_dbCmd->GetValue() == 0)
			return false;
		

		$this->_contentTemplateItemLoadedFlag = true;
		
		return true;
	
	}
	
	
	function checkIfContentIDexists($contentID){
	
		$this->_EnsureDigit($contentID);
		
		$this->_dbCmd->Query("SELECT * FROM contenttemplates WHERE DomainID=" . Domain::oneDomain() . " AND ID=" . $contentID);
		
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
		
		$this->_dbCmd->Query("SELECT ID FROM contenttemplates WHERE DomainID=" . Domain::oneDomain() . "  AND Title = \"" . DbCmd::EscapeSQL($contentTitle) . "\"");
		
		if($this->_dbCmd->GetNumRows() == 0)
			return 0;
		else
			return $this->_dbCmd->GetValue();
	}
	
	
	// Make sure to set all of the member variables...
	// Call this method and it will return the new ContentID from the DB.
	// You can't load a ContentTemplateID and then call this method.
	// Pass in the UserID who is adding the content.
	function insertNewContentTemplate($userID){
	
		if($this->_contentTemplateItemLoadedFlag)
			throw new Exception("Error inserting new Content Item.  You can not add a new Item if the Object has already loaded an existing Content Item ID.");

		if($this->checkIfContentTitleExists($this->_Title))
			throw new Exception("Can not create a new Content Template with this title because it is already being used in another Content Template.");
					
		$this->_CreatedByUserID = $userID;
		$this->_CreatedOn = time();
		
		$this->_populateDBfields();
		
		// Domains can not be switched once they have been assigned to a content piece.
		$arrayToInsert = $this->_dbArr;
		$arrayToInsert["DomainID"] = Domain::oneDomain();

		$this->_ItemID = $this->_dbCmd->InsertQuery("contenttemplates", $arrayToInsert);
		
		$this->_contentTemplateItemLoadedFlag = true;
		
		return $this->_ItemID;
	
	}
	
	
	
	// Make sure to set all of the member variables...
	// Call this method and it will return the new ContentID from the DB.
	// You can't load a ContentTemplate and then call this method.
	// Pass in the UserID who is editing the content.
	function updateContentTemplate($userID){
	
		if(!$this->_contentTemplateItemLoadedFlag)
			throw new Exception("You can not update a Content Item unless one has already been loaded.");

		$this->_LastEditedByUserID = $userID;
		$this->_LastEditedOn = time();
					
		$this->_populateDBfields();
		
		$existingContentTitle = $this->checkIfContentTitleExists($this->_Title);
		
		if($existingContentTitle != 0 && $existingContentTitle != $this->_ItemID)
			throw new Exception("Can not Change the Title on this Content Template because it is already being used in another Content Template.");
		
		$this->_dbCmd->UpdateQuery("contenttemplates", $this->_dbArr, "ID=" . $this->_ItemID);
	
	}
	
	
	
	//-----------   GET Methods ---------------------------------------------------------------------------
	

	
	
	function getContentItemID(){
		$this->_ensureContentLoaded();
		return $this->_ContentItemID;
	}
	
	
	
	
	function getTemplateID(){
		$this->_ensureContentLoaded();
		return $this->_TemplateID;
	}
	
	
	// This Template is linked to a Content Category (which in turn has a Product ID associated to it).
	function getProductIdLink(){
		
		$this->_ensureContentLoaded();
		
		$contentItemObj = new ContentItem($this->_dbCmd);
		
		$contentItemObj->loadContentByID($this->getContentItemID());
		
		return $contentItemObj->getProductIdLink();
	
	}
	
	
	// Returns false if one of the parents (Category or Content Item) is not active.
	function checkActiveParent(){
		
		$this->_ensureContentLoaded();
		
		$contentItemObj = new ContentItem($this->_dbCmd);
		
		$contentItemObj->loadContentByID($this->getContentItemID());
		
		if(!$contentItemObj->checkIfActive() || !$contentItemObj->checkActiveParent())
			return false;
		else 
			return true;

	}
	
	
	function getImageHyperlink(){
		return $this->getURLtoProductOrder();
	}
	
	function getImageAlign(){
		return "TL";
	}
	
	// Gives a URL that will start the order process (based upon the ProductID that is linked to this Category for Template)
	function getURLtoProductOrder(){
		
		$websiteUrlForDomain = Domain::getWebsiteURLforDomainID(Domain::oneDomain());
		
		return "http://$websiteUrlForDomain/new_project.php?srchEngineTemplateID=" . $this->getTemplateID() . "&returnurl=" . urlencode($this->getURLforContent());	
	
	}
	


	function getURLforImage($doNotShowStaticFlag = false){
		
		$websiteUrlForDomain = Domain::getWebsiteURLforDomainID(Domain::oneDomain());
		
		if($this->_templateImageSize == "big"){
			if($doNotShowStaticFlag || Constants::GetDevelopmentServer()){
				$urlForDownload = "content_image.php?contentType=templateImageBig&id=" . $this->_ItemID . "&noache=" . $this->getDateLastModified();	
			}
			else{
				$urlForDownload = "http://$websiteUrlForDomain/images/" . urlencode($this->_Title) . "_CTB.jpg";
			}
		}
		else if($this->_templateImageSize == "small"){
			if($doNotShowStaticFlag || Constants::GetDevelopmentServer()){
				$urlForDownload = "content_image.php?contentType=templateImageSmall&id=" . $this->_ItemID . "&noache=" . $this->getDateLastModified();
			}
			else{
				$urlForDownload = "http://$websiteUrlForDomain/images/" . urlencode($this->_Title) . "_CTS.jpg";
			}
		}
		else{
			throw new Exception("Error in method getURLforImage... the Image Size is incorrect.");
		}
		
		return $urlForDownload;
	
	}


	
	
	// Gets a URL that will bring up the given content piece
	// For Apache on the Unix server we want the actual URL to be masked through .htaccess file... so that the searching engines think it is a static HTML page. 
	function getURLforContent($doNotShowStaticFlag = false){
		
		$this->_ensureContentLoaded();
		
		$websiteUrlForDomain = Domain::getWebsiteURLforDomainID(Domain::oneDomain());
		
		if($doNotShowStaticFlag || Constants::GetDevelopmentServer()){
			$urlForDisplay = "content_view.php?contentType=template&contentID=" . urlencode($this->_ItemID) . "&noache=" . $this->getDateLastModified();
		}
		else{
			$urlForDisplay = "http://$websiteUrlForDomain/ci/templates/" . urlencode($this->getTitle());
		}
		
		return $urlForDisplay;
	
	}	
	
	
	
	// returns True if the Template image exist on Disk.  Just a check to make sure it didn't get deleted somehow... although this should indicate an anomoly.
	function checkIfImageStored(){
		$this->_ensureContentLoaded();
		
		$templatePath = $this->_getTemplateImagePath();
		
		if(empty($templatePath))
			return false;
		else
			return true;
		
	}
	
	
	// Returns the Binary Data for the Image
	function &getImage(){
	
		$this->_ensureContentLoaded();
		
		$templatePath = $this->_getTemplateImagePath();
		
		if(empty($templatePath))
			return false;
		
		$fd = fopen ($templatePath, "r");
		$ImageData = fread ($fd, filesize ($templatePath));
		fclose ($fd);
		
		// To avoid a compiler warning of the variable not beeing used?
		if(empty($ImageData))
			return $ImageData;
		else
			return $ImageData;
	}
	
	
	// Templates don't have footers... but just for Polymorphism sake... keep the method here.
	function getFooter(){
		return "";
	}
	function footerIsHTMLformat(){
		return false;
	}
	
	
	function getContentCategoryID(){
		
		$this->_ensureContentLoaded();
		
		// Create a content Item ... which can be used to get Its CategoryID.  2 levels of heirarchy.
		$contentItemObj = new ContentItem($this->_dbCmd);
		
		$contentItemObj->loadContentByID($this->getContentItemID());
		
		return $contentItemObj->getContentCategoryID();
	}

	
	
	// ------------   SET Methods ------------------------------------------------------------------------


	
	function setContentItemID($x){
	
		$x = trim($x);
	
		if(!preg_match("/^\d+$/", $x))
			throw new Exception("Error setting the content ID.  It is not in the proper format.");

		$contentItemObj = new ContentItem($this->_dbCmd);

		if(!$contentItemObj->checkIfContentIDexists($x))
			throw new Exception("Can not create a new Tempalte Content Item because the Content ID is not defined or invalid.");

		$this->_ContentItemID = $x;
	
	}
	
	
	function setTemplateID($x){
	
		$x = trim($x);
	
		if(!preg_match("/^\d+$/", $x))
			throw new Exception("Error setting the Content Template ID.  It is not in the proper format.");

		$this->_TemplateID = $x;
	}
	

	

	
	

	
	
	// ------------   PRIVATE Methods --------------------------------------------------------------------------
	
	function _ensureContentLoaded(){
	
		if(!$this->_contentTemplateItemLoadedFlag)
			throw new Exception("The Content Item must be loaded before calling this method.");
	}
	
	
		
	function _populateDBfields(){

		$errMsg = "";
		
		if(empty($this->_Description))
			$errMsg = "Missing Description: ";
		if(empty($this->_DescriptionFormat))
			$errMsg = "Missing Description Format: ";
		if(empty($this->_TemplateID))
			$errMsg = "Missing Template ID: ";
		if(empty($this->_ContentItemID))
			$errMsg = "Missing Content Item ID: ";

		if(!empty($errMsg))
			throw new Exception("Error inserting new content Item.  Some fields are missing: " . $errMsg);
	

		$this->_dbArr = array();
		$this->_dbArr["Title"] = $this->_Title;
		$this->_dbArr["Description"] = $this->_Description;
		$this->_dbArr["DescriptionFormat"] = $this->_DescriptionFormat;
		$this->_dbArr["DescriptionBytes"] = $this->_DescriptionBytes;
		$this->_dbArr["Links"] = $this->_Links;
		$this->_dbArr["ContentItemID"] = $this->_ContentItemID;
		$this->_dbArr["TemplateID"] = $this->_TemplateID;
		$this->_dbArr["CreatedByUserID"] = $this->_CreatedByUserID;
		$this->_dbArr["LastEditedByUserID"] = $this->_LastEditedByUserID;
		$this->_dbArr["CreatedOn"] = $this->_CreatedOn;
		$this->_dbArr["LastEditedOn"] = $this->_LastEditedOn;
		$this->_dbArr["HeaderHTML"] = $this->_HeaderHTML;
		$this->_dbArr["ActiveFlag"] = $this->_ActiveFlag ? "Y" : "N";

	}
	
	
	// return Null if there is not an Image Preview saved on disk.
	function _getTemplateImagePath(){
	
		$this->_ensureContentLoaded();
	
		$this->_dbCmd->Query("SELECT artworkstemplatespreview.ID FROM artworkstemplatespreview INNER JOIN products ON products.ID = artworkstemplatespreview.ProductID 
								WHERE products.DomainID=" . Domain::oneDomain() . " AND SearchEngineID =" . $this->_TemplateID . " ORDER BY ID ASC LIMIT 1");

		// Just because a tempalte ID exists doesn't mean that the Preview images also do.
		if($this->_dbCmd->GetNumRows() == 0)
			return null;
		
		$templatePreviewID = $this->_dbCmd->GetValue();

		if($this->_templateImageSize == "big")
			$ImagePeviewFileName = ThumbImages::GetTemplatePreviewName($templatePreviewID, "template_searchengine");
		else if($this->_templateImageSize == "small")
			$ImagePeviewFileName = ThumbImages::GetTemplatePreviewFnameAdmin($this->_dbCmd, $this->_TemplateID, "template_searchengine");
		else
			throw new Exception("Illegal Image size in method _getTemplateImagePath");
		
		$imagePath = Constants::GetWebserverBase() . "/image_preview/" . $ImagePeviewFileName;
		
		if(!file_exists($imagePath))
			return null;
		else
			return $imagePath;


	}


}



?>