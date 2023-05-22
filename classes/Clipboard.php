<?php

class Clipboard {


	static function GetTextAttributesDropDown($LayerObject){
	
		$colorCodes = ColorLib::GetRGBvalues($LayerObject->LayerDetailsObj->color,true);
		$colorHexCode = "#" . $colorCodes->red . $colorCodes->green . $colorCodes->blue;
	
		// Build a drop down to show all of the font properties
		$TextAttributes = array();
		$TextAttributes[""] = "-- Attributes --";
		$TextAttributes["x_coordinate:" . $LayerObject->x_coordinate] = "X Coordinate: " . $LayerObject->x_coordinate;
		$TextAttributes["y_coordinate:" . $LayerObject->y_coordinate] = "Y Coordinate: " . $LayerObject->y_coordinate;
		$TextAttributes["rotation:" . $LayerObject->rotation] = "Rotation: " . $LayerObject->rotation;
		$TextAttributes["color:" . strtoupper($colorHexCode)] = "Color: " . strtoupper($colorHexCode);
		$TextAttributes["font:" . $LayerObject->LayerDetailsObj->font] = "Font: " . $LayerObject->LayerDetailsObj->font;
		$TextAttributes["size:" . $LayerObject->LayerDetailsObj->size] = "Size: " . $LayerObject->LayerDetailsObj->size;
		$TextAttributes["align:" . $LayerObject->LayerDetailsObj->align] = "Alignment: " . $LayerObject->LayerDetailsObj->align;
		$TextAttributes["bold:" . $LayerObject->LayerDetailsObj->bold] = "Bold: " . $LayerObject->LayerDetailsObj->bold;
		$TextAttributes["italics:" . $LayerObject->LayerDetailsObj->italics] = "Italics: " . $LayerObject->LayerDetailsObj->italics;
		$TextAttributes["underline:" . $LayerObject->LayerDetailsObj->underline] = "Underline: " . $LayerObject->LayerDetailsObj->underline;
	
		return Widgets::buildSelect($TextAttributes, array());
	}
	static function GetImageAttributesDesc($DPI, $LayerObject, $PixelWidth, $PixelHeight){
	
		$dpi_Ratio = $DPI / 96;
	
	
		$ImageDescStr = "<u>Original Width:</u> ". $PixelWidth ."&nbsp;&nbsp;";
		$ImageDescStr .= "<u>Original Height:</u> ". $PixelHeight ."&nbsp;&nbsp;";
	
		$ImageDescStr .= "<u>New Width:</u> ". round($LayerObject->LayerDetailsObj->width * $dpi_Ratio) ."&nbsp;&nbsp;";
		$ImageDescStr .= "<u>New Height:</u> ". round($LayerObject->LayerDetailsObj->height * $dpi_Ratio) ."&nbsp;&nbsp;";
		$ImageDescStr .= "<u>Rotation:</u> ". $LayerObject->rotation ."&nbsp;&nbsp;"; 
		
		$ImageDescStr .= "<u>X Coord:</u> ". $LayerObject->x_coordinate ."&nbsp;&nbsp;";
		$ImageDescStr .= "<u>Y Coord:</u> ". $LayerObject->y_coordinate ."&nbsp;&nbsp;";
		
		return $ImageDescStr;
	
	}
	
	
	
	static function GetClipBoardArray(){
		
		global $HTTP_SESSION_VARS;
	
		if (!isset($HTTP_SESSION_VARS['ClipBoardArr'])){
			$RetArr = array();
		}
		else{
			$RetArr = unserialize($HTTP_SESSION_VARS['ClipBoardArr']);
			if(!$RetArr)
				throw new Exception("Can not unserialize:<br><br>" . $HTTP_SESSION_VARS['ClipBoardArr']);
		}
	
	
		return $RetArr;
	}
	
	// return true if the layer is a Text layer and it has a shadow associated with it.
	static function CheckIfLayerObjHasShadow($LayerObj){
	
		if($LayerObj->LayerType == "text"){
			if($LayerObj->LayerDetailsObj->shadow_level_link != "")
				return true;
		}
		return false;
	
	}
	
	// Returns true if the clipboard ID belongs to a Text Layer and that text layer has a shadow associated to it
	static function CheckIfClipboardIDHasShadow($ClipBoardID){
	
		$clipboardArr = Clipboard::GetClipBoardArray();
		
		if(!isset($clipboardArr[$ClipBoardID]))
			return false;
			
		return Clipboard::CheckIfLayerObjHasShadow($clipboardArr[$ClipBoardID]["LayerObj"]);
	}
	
	// returns a clipboard ID of a Shadow Layer... belonging to the given Clipboard ID.
	// the function will fail if the clipboard ID does not exist or have a shadow... so make sure to check it with the function Clipboard::CheckIfClipboardIDHasShadow
	static function GetClipboardIDofShadow($ClipBoardID){
	
		if(!Clipboard::CheckIfClipboardIDHasShadow($ClipBoardID))
			throw new Exception("Error in function Clipboard::GetClipboardIDofShadow.  The Clipboard ID does not have a shadow");
			
		$clipboardArr = Clipboard::GetClipBoardArray();
		
		$shadowLevelLink = $clipboardArr[$ClipBoardID]["LayerObj"]->LayerDetailsObj->shadow_level_link;
		
		foreach($clipboardArr as $ThisClipBoardID => $clipboardItemObj){
			if($clipboardItemObj["LayerObj"]->LayerType == "text"){
			
				$mainLayerMessage = $clipboardArr[$ClipBoardID]["LayerObj"]->LayerDetailsObj->message;
			
				// It is possible (but rare) that muliple layers on the clipboard could share the same layer level
				// as an extra precaution... make sure the text inside the layers match.
				if($clipboardItemObj["LayerObj"]->level == $shadowLevelLink && $mainLayerMessage == $clipboardItemObj["LayerObj"]->LayerDetailsObj->message)
					return $ThisClipBoardID;
			}
		}
		
		throw new Exception("An error occured in the function Clipboard::GetClipboardIDofShadow.  A matching Shadow ID was not found.");
	}
	
	
	// returns true if the Clipboard ID is a shadow to another layer (on the clipboard).
	static function CheckIfClipboardIDisShadowToAnotherLayer($ClipBoardID){
	
		$clipboardArr = Clipboard::GetClipBoardArray();
	
		if(!isset($clipboardArr[$ClipBoardID]))
			throw new Exception("Error in function call Clipboard::CheckIfClipboardIDisShadowToAnotherLayer, the Clipboard ID does not exist.");
		
	
		// Only Text layers can be a shadow to another layer.
		if($clipboardArr[$ClipBoardID]["LayerObj"]->LayerType != "text")
			return false;
		
		$possibleShadowLevel = $clipboardArr[$ClipBoardID]["LayerObj"]->level;
	
		foreach($clipboardArr as $clipboardItemObj){
			if($clipboardItemObj["LayerObj"]->LayerType == "text"){
				
				// It is possible that muliple layers on the clipboard could share the same layer level
				// as an extra precaution... make sure the text inside the layers match.
				if($clipboardItemObj["LayerObj"]->LayerDetailsObj->shadow_level_link == $possibleShadowLevel){
					
					$possibleShadowMessage = $clipboardArr[$ClipBoardID]["LayerObj"]->LayerDetailsObj->message;
					
					if($possibleShadowMessage == $clipboardItemObj["LayerObj"]->LayerDetailsObj->message)
						return true;
				}
			}
		}
		return false;
	}
	
	static function AddToClipBoard($productID, $LayerObj){
		
		global $HTTP_SESSION_VARS;
		
		$NewArray = Clipboard::GetClipBoardArray();
		
		array_push($NewArray, array("LayerObj"=>$LayerObj, "ProductID"=>$productID));
	
		$HTTP_SESSION_VARS['ClipBoardArr'] = serialize($NewArray);
	
	}
	static function RemoveFromClipBoard($ClipBoardID){
	
		global $HTTP_SESSION_VARS;
		
		// Find out if the Clipboard ID we are removing has a Shadow associated to it.
		// If so, we need to remove the shadow as well
		$clipBoardShadowID = "";
	
		if(Clipboard::CheckIfClipboardIDHasShadow($ClipBoardID))
			$clipBoardShadowID = Clipboard::GetClipboardIDofShadow($ClipBoardID);
	
		$CurrentClipBoardArr = Clipboard::GetClipBoardArray();
		
		$NewArray = array();
		foreach($CurrentClipBoardArr as $ThisClipBoardID => $clipboardItemObj){
			if($ThisClipBoardID != $ClipBoardID && $ThisClipBoardID != $clipBoardShadowID)
				$NewArray["$ThisClipBoardID"] = $clipboardItemObj;
		}
		
		if(sizeof($NewArray) == 0)
			unset($HTTP_SESSION_VARS['ClipBoardArr']);
		else
			$HTTP_SESSION_VARS['ClipBoardArr'] = serialize($NewArray);
	
	}
	
	static function PurgeClipboard(){
	
		global $HTTP_SESSION_VARS;
		
		unset($HTTP_SESSION_VARS['ClipBoardArr']);
	}
	
	
	// Will copy the complete Artwork XML to the session
	// It should not allow us to copy complete artworks between different products
	static function CopyArtworkToSession($ArtworkXML, $ProductID, $view, $projectid){
	
		global $HTTP_SESSION_VARS;
	
		$HTTP_SESSION_VARS['ArtworkCopy'] = $ArtworkXML;
		$HTTP_SESSION_VARS['ArtworkCopyProductID'] = $ProductID;
		$HTTP_SESSION_VARS['ArtworkCopyView'] = $view;
		$HTTP_SESSION_VARS['ArtworkCopyProjectID'] = $projectid;
	}
	
	static function CheckIfArtworkIsCopiedToSession(){
	
		global $HTTP_SESSION_VARS;
	
		if(isset($HTTP_SESSION_VARS['ArtworkCopyProductID']) && !empty($HTTP_SESSION_VARS['ArtworkCopyProductID']))
			return true;
	
		return false;
	}
	static function ClearArtworkCopyFromSession(){
	
		global $HTTP_SESSION_VARS;
	
		if(isset($HTTP_SESSION_VARS['ArtworkCopy']))
			unset($HTTP_SESSION_VARS['ArtworkCopy']);
		if(isset($HTTP_SESSION_VARS['ArtworkCopyProductID']))
			unset($HTTP_SESSION_VARS['ArtworkCopyProductID']);
		if(isset($HTTP_SESSION_VARS['ArtworkCopyView']))
			unset($HTTP_SESSION_VARS['ArtworkCopyView']);
		if(isset($HTTP_SESSION_VARS['ArtworkCopyProjectID']))
			unset($HTTP_SESSION_VARS['ArtworkCopyProjectID']);
			
	}
	static function GetArtworkCopyProductIDFromSession(){
	
		global $HTTP_SESSION_VARS;
	
		if(isset($HTTP_SESSION_VARS['ArtworkCopyProductID']))
			return $HTTP_SESSION_VARS['ArtworkCopyProductID'];
		else
			throw new Exception("Error With getting the Artwork copy from the session.");
		
	}
	static function GetArtworkCopyFromSession(){
	
		global $HTTP_SESSION_VARS;
	
		if(isset($HTTP_SESSION_VARS['ArtworkCopy']))
			return $HTTP_SESSION_VARS['ArtworkCopy'];
		else
			throw new Exception("Error With getting the Artwork copy from the session.");
	}
	
	
	// If we copied an artwork from a SavedProject then this function call will return its ID
	// Otherwise it will return 0
	static function GetSavedProjectLinkForArtworkImport(){
	
		global $HTTP_SESSION_VARS;
	
		if(isset($HTTP_SESSION_VARS['ArtworkCopyView'])){
			if($HTTP_SESSION_VARS['ArtworkCopyView'] == "saved")
				return $HTTP_SESSION_VARS['ArtworkCopyProjectID'];
		}
		
		return 0;
	}

	
	
	
}


