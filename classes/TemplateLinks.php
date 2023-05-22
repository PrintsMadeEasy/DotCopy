<?


class TemplateLinks {

	private $dbCmd;
	
	const TEMPLATE_AREA_SEARCH_ENGINE = "S";
	const TEMPLATE_AREA_CATEGORY = "C";

	function __construct(){
		$this->dbCmd = new DbCmd();
	}
	
	// A lot of places in the code we refer to project types with long strings... such as "projectsession", "saved", "template_searchengine", etc. 
	// We are kind of running two different types of constants.
	// This just converts that string into a constant CHAR defined in this class.
	function getTemplateAreaFromViewType($viewType){
		
		if($viewType == "template_searchengine")
			return self::TEMPLATE_AREA_SEARCH_ENGINE;
		else if($viewType == "template_category")
			return self::TEMPLATE_AREA_CATEGORY;
		else
			throw new Exception("Illegal Template View Type");
	}
	
	function getViewTypeFromTemplateArea($templateArea){
		
		if($templateArea == self::TEMPLATE_AREA_CATEGORY)
			return "template_category";
		else if($templateArea == self::TEMPLATE_AREA_SEARCH_ENGINE)
			return "template_searchengine";
		else
			throw new Exception("Illegal template Area");
	}
	
	function removeAllLinksFromTemplate($templateArea, $templateID){
		
		$this->ensureTemplateAreaType($templateArea);
		
		$this->dbCmd->Query("DELETE FROM templatelinks 
							WHERE OneTemplateArea='".DbCmd::EscapeLikeQuery($templateArea)."' AND OneTemplateID=" . intval($templateID));
		$this->dbCmd->Query("DELETE FROM templatelinks 
							WHERE TwoTemplateArea='".DbCmd::EscapeLikeQuery($templateArea)."' AND TwoTemplateID=" . intval($templateID));
	}
	
	
	function removeLinkBetweenTemplates($templateArea_1, $templateID_1, $templateArea_2, $templateID_2){
		
		$this->ensureTemplateAreaType($templateArea_1);
		$this->ensureTemplateAreaType($templateArea_2);
		
		$this->dbCmd->Query("DELETE FROM templatelinks 
							WHERE OneTemplateArea='".DbCmd::EscapeLikeQuery($templateArea_1)."' AND OneTemplateID=" . intval($templateID_1) . " AND 
							TwoTemplateArea='".DbCmd::EscapeLikeQuery($templateArea_2)."' AND TwoTemplateID=" . intval($templateID_2));
		$this->dbCmd->Query("DELETE FROM templatelinks 
							WHERE OneTemplateArea='".DbCmd::EscapeLikeQuery($templateArea_2)."' AND OneTemplateID=" . intval($templateID_2) . " AND 
							TwoTemplateArea='".DbCmd::EscapeLikeQuery($templateArea_1)."' AND TwoTemplateID=" . intval($templateID_1));

	}
	
	function checkIfTemplatesAreLinked($templateArea_1, $templateID_1, $templateArea_2, $templateID_2){

		$this->ensureTemplateAreaType($templateArea_1);
		$this->ensureTemplateAreaType($templateArea_2);
		
		$this->dbCmd->Query("SELECT COUNT(*) FROM templatelinks 
							WHERE OneTemplateArea='".DbCmd::EscapeLikeQuery($templateArea_1)."' AND OneTemplateID=" . intval($templateID_1) . " AND 
							TwoTemplateArea='".DbCmd::EscapeLikeQuery($templateArea_2)."' AND TwoTemplateID=" . intval($templateID_2));
		if($this->dbCmd->GetValue() > 0)
			return true;
			
		$this->dbCmd->Query("SELECT COUNT(*) FROM templatelinks 
							WHERE OneTemplateArea='".DbCmd::EscapeLikeQuery($templateArea_2)."' AND OneTemplateID=" . intval($templateID_2) . " AND 
							TwoTemplateArea='".DbCmd::EscapeLikeQuery($templateArea_1)."' AND TwoTemplateID=" . intval($templateID_1));
		if($this->dbCmd->GetValue() > 0)
			return true;
			
		return false;
	}
	
	function linkTemplatesTogether($userID, $templateArea_1, $templateID_1, $templateArea_2, $templateID_2){
		
		if($this->checkIfTemplatesAreLinked($templateArea_1, $templateID_1, $templateArea_2, $templateID_2))
			return;
			
		// Don't let you link a template to itself.
		if($templateArea_1 == $templateArea_2 && $templateID_1 == $templateID_2)
			return;
			
		$oneProductID = $this->getProductIdOfTemplate($templateArea_1, $templateID_1);
		$twoProductID = $this->getProductIdOfTemplate($templateArea_2, $templateID_2);
		
		if(empty($oneProductID) || empty($twoProductID))
			throw new Exception("One of the Templates do not exist.");
			
		if(Product::getDomainIDfromProductID($this->dbCmd, $oneProductID) != Product::getDomainIDfromProductID($this->dbCmd, $oneProductID))
			throw new Exception("Can not link between templates of different domains.");
			
		$insertRow["OneTemplateID"] = $templateID_1;
		$insertRow["OneTemplateArea"] = $templateArea_1;
		$insertRow["OneProductID"] = $oneProductID;
		$insertRow["TwoTemplateID"] = $templateID_2;
		$insertRow["TwoTemplateArea"] = $templateArea_2;
		$insertRow["TwoProductID"] = $twoProductID;
		$insertRow["LinkByUserID"] = $userID;
		$insertRow["LinkDate"] = time();
		
		$this->dbCmd->InsertQuery("templatelinks", $insertRow);
	}
	
	// Returns an Array of Quick Edit text fields that are in common between both templates (regardless of sides)
	function getTextFieldsInCommon($templateArea_1, $templateID_1, $templateArea_2, $templateID_2){
		
		if(!$this->checkIfTemplatesAreLinked($templateArea_1, $templateID_1, $templateArea_2, $templateID_2))
			throw new Exception("Can not get Text fields in common if the templates have not been linked.");
			
		$oneProductID = $this->getProductIdOfTemplate($templateArea_1, $templateID_1);
		$twoProductID = $this->getProductIdOfTemplate($templateArea_2, $templateID_2);
		
		if(empty($oneProductID) || empty($twoProductID))
			throw new Exception("One of the Templates do not exist.");
			
		$artInfoObj_1 = new ArtworkInformation(ArtworkLib::GetArtXMLfile($this->dbCmd, $this->getViewTypeFromTemplateArea($templateArea_1), $templateID_1));
		$artInfoObj_2 = new ArtworkInformation(ArtworkLib::GetArtXMLfile($this->dbCmd, $this->getViewTypeFromTemplateArea($templateArea_2), $templateID_2));
		
		$quickEditFields_1 = array();
		for($i=0; $i<sizeof($artInfoObj_1->SideItemsArray); $i++){
			foreach ($artInfoObj_1->SideItemsArray[$i]->layers as $LayerObj) {
				
				if($LayerObj->LayerType != "text" || empty($LayerObj->LayerDetailsObj->field_name))
					continue;
		
				$quickEditFields_1[] = $LayerObj->LayerDetailsObj->field_name;
			}
		}
		
		$quickEditFields_2 = array();
		for($i=0; $i<sizeof($artInfoObj_2->SideItemsArray); $i++){
			foreach ($artInfoObj_2->SideItemsArray[$i]->layers as $LayerObj) {
				
				if($LayerObj->LayerType != "text" || empty($LayerObj->LayerDetailsObj->field_name))
					continue;
		
				$quickEditFields_2[] = $LayerObj->LayerDetailsObj->field_name;
			}
		}
		
		return array_unique(array_intersect($quickEditFields_1, $quickEditFields_2));
		
	}
	
	function getProductIdsLinkedToThis($templateArea, $templateID){
		
		$this->ensureTemplateAreaType($templateArea);
		
		$this->dbCmd->Query("SELECT DISTINCT TwoProductID FROM templatelinks 
							WHERE OneTemplateArea='".DbCmd::EscapeLikeQuery($templateArea)."' AND OneTemplateID=" . intval($templateID));
		$firstProductIDs = $this->dbCmd->GetValueArr();
		
		$this->dbCmd->Query("SELECT DISTINCT OneProductID FROM templatelinks 
							WHERE TwoTemplateArea='".DbCmd::EscapeLikeQuery($templateArea)."' AND TwoTemplateID=" . intval($templateID));
		$sendProductIDs = $this->dbCmd->GetValueArr();
		
		$allProductIDs = array_unique(array_merge($firstProductIDs, $sendProductIDs));
		return $allProductIDs;
	}
	
	function getNumberOfProductsLinkedToThis($templateArea, $templateID){
		
		return sizeof($this->getProductIdsLinkedToThis($templateArea, $templateID));
	}
	
	// Returns a hash... the Key is the Template ID and the Value is the Template Area.
	// The results will be grouped by Product IDs... since there can be multiple links to another product.
	// The groups of ProductID's will be sorted by their priority.
	// If you pass in a product ID, it will limit the results to that product ID only.
	function getTemplatesLinkedToThis($templateArea, $templateID, $limitProductId = null){
		
		$this->ensureTemplateAreaType($templateArea);
		
		$productIDsLinkedArr = $this->getProductIdsLinkedToThis($templateArea, $templateID);
		$productIDsLinkedArr = Product::sortProductIdsByImportance($productIDsLinkedArr);
		
		$returnArr = array();
		
		foreach($productIDsLinkedArr as $thisProductID){
			
			if(!empty($limitProductId)){
				if($limitProductId != $thisProductID)
					continue;
			}
			
			$this->dbCmd->Query("SELECT TwoTemplateArea, TwoTemplateID FROM templatelinks 
									WHERE OneTemplateArea='".DbCmd::EscapeLikeQuery($templateArea)."' AND OneTemplateID=" . intval($templateID) . " AND 
									TwoProductID = " . intval($thisProductID));
			while($row = $this->dbCmd->GetRow()){
				$returnArr[$row["TwoTemplateID"]] = $row["TwoTemplateArea"];
			}
			
			$this->dbCmd->Query("SELECT OneTemplateArea, OneTemplateID FROM templatelinks 
									WHERE TwoTemplateArea='".DbCmd::EscapeLikeQuery($templateArea)."' AND TwoTemplateID=" . intval($templateID) . " AND 
									OneProductID = " . intval($thisProductID));
			while($row = $this->dbCmd->GetRow()){
				$returnArr[$row["OneTemplateID"]] = $row["OneTemplateArea"];
			}
		}
		
		// Do a safety check to make sure all of the templates still exist.
		// In case of database corruption... there could links between non-existent templates.
		$filteredArr = array();
		foreach($returnArr as $thisTemplateID => $thisTempalteArea){
			$thisProductID = $this->getProductIdOfTemplate($thisTempalteArea, $thisTemplateID);
			if(empty($thisProductID)){
				WebUtil::WebmasterError("Their is a link between two templates... but the tempalte does not exist.  Maybe database corruption? Template Area: $thisTempalteArea Template ID: $thisTemplateID");
				continue;
			}
			
			$filteredArr[$thisTemplateID] = $thisTempalteArea;
		}
		
		return $filteredArr;
	}
	
	// Returns NULL if the Tempalte Does not exist.
	function getProductIdOfTemplate($templateArea, $templateID){
		
		if($templateArea == self::TEMPLATE_AREA_CATEGORY){
			$this->dbCmd->Query("SELECT ProductID FROM artworkstemplates WHERE ArtworkID=" . intval($templateID));
			$productID = $this->dbCmd->GetValue();
			return $productID;
		}
		else if($templateArea == self::TEMPLATE_AREA_SEARCH_ENGINE){
			$this->dbCmd->Query("SELECT ProductID FROM artworksearchengine WHERE ID=" . intval($templateID));
			$productID = $this->dbCmd->GetValue();
			return $productID;
		}
		else{
			throw new Exception("Illegal Template Area Value.");
		}
		
	}
	
	
	// Pass in 2 artwork objects and it will return the destination Artwork Obj.
	// This function will transfer the content from the source document onto the destination document.
	// It does this by finding matching field names and copying the content into it.
	// If the destination artwork object does not have any field names, then the message contents will be erased.
	static function transferContentToNewTemplate($sourceArtworkObj, $destArtworkObj){
		
		if(get_class($sourceArtworkObj) != "ArtworkInformation" || get_class($sourceArtworkObj) != "ArtworkInformation")
			throw new Exception("Illegal Object types");
		
		for($destSideCounter=0; $destSideCounter<sizeof($destArtworkObj->SideItemsArray); $destSideCounter++) {
			for($destLayerCntr=0; $destLayerCntr<sizeof($destArtworkObj->SideItemsArray[$destSideCounter]->layers); $destLayerCntr++){

				if($destArtworkObj->SideItemsArray[$destSideCounter]->layers[$destLayerCntr]->LayerType != "text")
					continue;
					
				// Never wipe out contents if the user can't change them either.
				if($destArtworkObj->SideItemsArray[$destSideCounter]->layers[$destLayerCntr]->LayerDetailsObj->permissions->not_selectable)
					continue;
				if($destArtworkObj->SideItemsArray[$destSideCounter]->layers[$destLayerCntr]->LayerDetailsObj->permissions->data_locked)
					continue;
					
				// The "layer level" is like a unique ID of the layer.
				$destinationLayerLevel = $destArtworkObj->SideItemsArray[$destSideCounter]->layers[$destLayerCntr]->level;
				
				// Don't wipe out Shadow Layer contents... wait for the original source layer.
				if($destArtworkObj->CheckIfTextLayerIsShadowToAnotherLayer($destSideCounter, $destinationLayerLevel))
					continue;
					
				// Wipe out the message contents.  Maybe it will get re-populated from a matching "Text Field" from the source artwork below.
				$destArtworkObj->SideItemsArray[$destSideCounter]->layers[$destLayerCntr]->LayerDetailsObj->message = "";
				$destArtworkObj->makeShadowTextMatchParent($destSideCounter, $destinationLayerLevel);
				
				// If there is not a field name on the text layer, then we can't transfer anything across.
				if(empty($destArtworkObj->SideItemsArray[$destSideCounter]->layers[$destLayerCntr]->LayerDetailsObj->field_name))
					continue;
		
				
					
				// Now we want to loop through orginal project artwork and find out if there is a matching field name (on all of the sides)
				// We are going to loop backwards over the sides to make sure that the front-side is used last (which is most important).
				for($originalSideCounter=(sizeof($sourceArtworkObj->SideItemsArray) - 1); $originalSideCounter >= 0; $originalSideCounter--) {
					for($origLayerCounter=0; $origLayerCounter<sizeof($sourceArtworkObj->SideItemsArray[$originalSideCounter]->layers); $origLayerCounter++){
		
						if($sourceArtworkObj->SideItemsArray[$originalSideCounter]->layers[$origLayerCounter]->LayerType != "text")
							continue;

						// Make sure that the field names match before trying to transfer.
						$destFieldName = strtoupper($destArtworkObj->SideItemsArray[$destSideCounter]->layers[$destLayerCntr]->LayerDetailsObj->field_name);
						$sourceFieldName = strtoupper($sourceArtworkObj->SideItemsArray[$originalSideCounter]->layers[$origLayerCounter]->LayerDetailsObj->field_name);
						if($destFieldName != $sourceFieldName)
							continue;
							
						$destArtworkObj->SideItemsArray[$destSideCounter]->layers[$destLayerCntr]->LayerDetailsObj->message = $sourceArtworkObj->SideItemsArray[$originalSideCounter]->layers[$origLayerCounter]->LayerDetailsObj->message;
						$destArtworkObj->SideItemsArray[$destSideCounter]->layers[$destLayerCntr]->LayerDetailsObj->font = $sourceArtworkObj->SideItemsArray[$originalSideCounter]->layers[$origLayerCounter]->LayerDetailsObj->font;
						
						// In case there this matching Text field has a shadow attached to it.
						$destArtworkObj->makeShadowTextMatchParent($destSideCounter, $destinationLayerLevel);
					}
				}
				
				
				
			}
		}
		
		return $destArtworkObj;
	}
	

	
	private function ensureTemplateAreaType($templateArea){
		
		if($templateArea != self::TEMPLATE_AREA_CATEGORY && $templateArea != self::TEMPLATE_AREA_SEARCH_ENGINE)
			throw new Exception("The template area is not correct.");
		
	}
}





