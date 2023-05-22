
function DecimalToHex(theNumber){

	_root.HexString = "";
	
	IterateNumbers(theNumber);

	return _root.HexString;
}
function IterateNumbers(CurrentNumber){

	IntegerAfterDivision = int(CurrentNumber/16);

	RemainderAfterDivision = CurrentNumber % 16;

	if(RemainderAfterDivision == 10){
			_root.HexString = "A" + _root.HexString;
	}
	else if(RemainderAfterDivision == 11){
			_root.HexString = "B" + _root.HexString;
	}
	if(RemainderAfterDivision == 12){
			_root.HexString = "C" + _root.HexString;
	}
	else if(RemainderAfterDivision == 13){
			_root.HexString = "D" + _root.HexString;
	}
	if(RemainderAfterDivision == 14){
			_root.HexString = "E" + _root.HexString;
	}
	else if(RemainderAfterDivision == 15){
			_root.HexString = "F" + _root.HexString;
	}
	else if(RemainderAfterDivision < 10){
			_root.HexString = RemainderAfterDivision + _root.HexString;
	}

	//Call the function again (recursive).
	if(IntegerAfterDivision > 0){
		IterateNumbers(IntegerAfterDivision);
	}
}


//This function will tranlate HTML entities back to there charactes like &apos into '
//The reason I didn't use my other search & replace function is that it is too slow.  This function is much quicker for the job that it needs to do.
function UnTranslateHTMLentities(NewText){

	NumberCharctersRemoved = 0;

	StringtLength = NewText.length;

	for(i=0;i<StringtLength;++i){		//Go through the entire String character by character.

		if(NewText.charAt(i) == "&"){  //Now we know that we have come across an HTML character code

			tempString = NewText.substring(0,i);

			//Find out which HTML character code it is.  Could be 1 of 3  <, >, &
			if(NewText.charAt(i) == "&" && NewText.charAt(i+1) == "l" && NewText.charAt(i+2) == "t" && NewText.charAt(i+3) == ";"){
				NumberCharctersRemoved = 4;
				tempString += "<";
			}
			else if(NewText.charAt(i) == "&" && NewText.charAt(i+1) == "g" && NewText.charAt(i+2) == "t" && NewText.charAt(i+3) == ";"){
				NumberCharctersRemoved = 4;
				tempString += ">"
			}
			else if(NewText.charAt(i) == "&" && NewText.charAt(i+1) == "a" && NewText.charAt(i+2) == "m" && NewText.charAt(i+3) == "p"  && NewText.charAt(i+4) == ";"){
				NumberCharctersRemoved = 5;
				tempString += "&"
			}
			else if(NewText.charAt(i) == "&" && NewText.charAt(i+1) == "a" && NewText.charAt(i+2) == "p" && NewText.charAt(i+3) == "o" && NewText.charAt(i+4) == "s"  && NewText.charAt(i+5) == ";"){
				NumberCharctersRemoved = 6;
				tempString += "'";
			}
			else if(NewText.charAt(i) == "&" && NewText.charAt(i+1) == "q" && NewText.charAt(i+2) == "u" && NewText.charAt(i+3) == "o" && NewText.charAt(i+4) == "t"  && NewText.charAt(i+5) == ";"){
				NumberCharctersRemoved = 6;
				tempString += "\"";
			}

			tempString += NewText.substring((i + NumberCharctersRemoved),StringtLength);
			NewText = tempString;
		}
	}

	return NewText;

}

//Convert special characters into their HTML character code
function TranslateHTMLentities(NewText){

	StringtLength = NewText.length;

	for(i=0;i<StringtLength;++i){		//Go through the entire String character by character.

		if(NewText.charAt(i) == "<" || NewText.charAt(i) == ">" || NewText.charAt(i) == "&" || NewText.charAt(i) == "\""){  //Then we have to Covert to HTML character codes.

			tempString = NewText.substring(0,i);

			if(NewText.charAt(i) == "<"){
				StringtLength += 3; //This accounts for the replacement HTML character code.
				tempString += "&lt;"
			}
			else if(NewText.charAt(i) == ">"){
				StringtLength += 3; 
				tempString += "&gt;"
			}
			else if(NewText.charAt(i) == "'"){
				StringtLength += 5; 
				tempString += "&apos;"
			}
			else if(NewText.charAt(i) == "&"){
				StringtLength += 4;
				tempString += "&amp;"
			}
			else if(NewText.charAt(i) == "\""){
				StringtLength += 5;
				tempString += "&quot;"
			}


			tempString += NewText.substring((i + 1),StringtLength);
			NewText = tempString;
		}

	}
	return NewText;
}


function CreateSideTabs(){

	//Used for attaching clips.  The number doesn't matter but we can't have two clips on the same Depth.
	DepthCounter = 500;

	//This will record the first Side Number which doesn't have a delete flag set.
	FirstTabFound = "";

	//Loop through the Global Array of the Side Properties and record how many active sides we have.. (that have not been deleted)
	SideCounter = 0;
	for(j=0; j < _root.sideProperties.length; j++){
		if(_root.sideProperties[j]["status"] == "OK"){
			SideCounter++;
		}
	}


	//Only create the tabs if there are more than one.  It doesn't make sense to just have one tab.
	if(SideCounter > 1){

		//Loop through the Global Array of the Side Properties and start attaching Movie Clips for each tab.
		for(j=0; j < _root.sideProperties.length; j++){

			//Find out if the Administrator set a flag on this Side to delete it.. We don't create the tab then.
			if(_root.sideProperties[j]["status"] == "OK"){

				//Record the first active side.
				if(FirstTabFound == ""){
					FirstTabFound = j;
				}

				attachMovie("SideTab", ("SideTab" + j),DepthCounter); 
				DepthCounter++;

				//Get a target for the clip we just attached.
				SideTabObj = eval("SideTab" + j);

				//Fill in the name for the tab.
				SideTabObj.SideName = _root.sideProperties[j]["description"];

				//Set a member variable with this Obj so that Tab Buttons have a parameter to pass.
				SideTabObj.SideNumber = j;


				//Position the Location of the tabs.  On the first Tab we create we hard code the coordinates.
				//The following tabs will offset by the width of the tabs.
				if(j == FirstTabFound){
					TabYCoord = 14;
					SideTabObj._x = 80;
					SideTabObj._y = TabYCoord;	
					LastTabCoordinate_x = SideTabObj._x;
				}
				else{
					SideTabObj._x = LastTabCoordinate_x + SideTabObj._width;
					SideTabObj._y = TabYCoord;
					LastTabCoordinate_x = SideTabObj._x;
				}
			}

		}
	}

	//This will record the first Side Number which doesn't have a delete flag set.
	FirstTabFound = "";

	//This creates tabs, just like above.  However these tabs are for the administrator access.
	for(j=0; j < _root.sideProperties.length; j++){

		//Find out if the Administrator set a flag on this Side to delete it.. We don't create the tab then.
		if(_root.sideProperties[j]["status"] == "OK"){

			//Record the first active side.
			if(FirstTabFound == ""){
				FirstTabFound = j;
			}


			_root.admin.tabmenu.attachMovie("AdminTab", ("AdminTab" + j),DepthCounter); 
			DepthCounter++;

			//Get a target for the clip we just attached.
			SideTabObj = eval("_root.admin.tabmenu.AdminTab" + j);

			//Fill in the name for the tab.
			SideTabObj.SideName = _root.sideProperties[j]["description"];

			//Set a member variable with this Obj.
			SideTabObj.SideNumber = j;


			//Position the Location of the tabs.  On the first Tab we create we hard code the coordinates.
			//The following tabs will offset by the height of the tabs.
			if(j == FirstTabFound){
				TabXCoord = 0;
				SideTabObj._x = TabXCoord;
				SideTabObj._y = 0;	
				LastTabCoordinate_y = SideTabObj._y;
			}
			else{
				SideTabObj._y = LastTabCoordinate_y + SideTabObj._height;
				SideTabObj._x = TabXCoord;
				LastTabCoordinate_y = SideTabObj._y;
			}
		}

	}



}



function RemoveSideTabs(){

	//This creates tabs, just like above.  However these tabs are for the administrator access.
	for(j=0; j < _root.sideProperties.length; j++){

		//Only remove the tab if it hasn't been deleted already.
		if(_root.sideProperties[j]["status"] == "OK"){

			//Remove the Admin Tabs
			SideTabObj = eval("_root.admin.tabmenu.AdminTab" + j);
			SideTabObj.removeMovieClip();

			//Remove the main tabs.
			SideTabObj = eval("SideTab" + j);
			SideTabObj.removeMovieClip();
		}
	}


}



//This function should be called (maybe on every "enterframe")  for code that needs to deal with scrolling of the Content window.  Limtits are set based on the width of the content and the Current Zoom level.
function Set_Slider_Limits(){

	//We need to know what the zoom ratio is becuase that will effect how far they are able to move the coordinates of the X/y window.
	//Remember that scaling an object does not change the coordinate system.
	_root.ZoomRatio = _root.CurrentZoom / 100;

	//Figure out where the Right Edge limit is.  It is kind of reversed.. Since when you scoll left the Content Window is actually moving twoards the right.
	//The variable _root.TotalScrollableArea_Width is set by the function "Display Side" in the Content window.  The information originally comes from the XML document.
	_root.MaxScroll_right = (_root.CenterPoint._x + _root.TotalScrollableArea_Width/2 * _root.ZoomRatio);
	_root.MaxScroll_left = (_root.CenterPoint._x - _root.TotalScrollableArea_Width/2 * _root.ZoomRatio);
	_root.MaxScroll_top = (_root.CenterPoint._y - _root.TotalScrollableArea_Height/2 * _root.ZoomRatio);
	_root.MaxScroll_bottom = (_root.CenterPoint._y + _root.TotalScrollableArea_Height/2 * _root.ZoomRatio);

}

//This will set the X and Y sliders in the proper position.  It should be called anytime we move the ContentWindow undertneath the mask.  Then the sliders will be set accordingly.
//The funciton within this window  called "Set_Slider_Limits" should be called prior to running this funtion.  The reason I didn't put the function call within this function is becuase I am leaving it up to the caller.   Sometimes they need to access the global varialbes such as _root.MaxScroll_right prior to running this function.  I don't want to do double processing.
function Position_Sliders(){

	//Position the X Slider
	lineLoc = _root.sliderX.line._x;
	linelength = _root.sliderX.line._width - _root.sliderX.handle._width;

	//Move the handle based on the zoom ratio, scollable width, and the position of the content window.
	_root.sliderX.handle._x = (((_root.MaxScroll_right - _root.ContentWindow._x )/_root.TotalScrollableArea_Width / _root.ZoomRatio)*linelength)+lineLoc + _root.sliderX.handle._width/2;

	//Position the Y Slider
	lineLoc = _root.sliderY.line._x;
	linelength = _root.sliderY.line._width - _root.sliderY.handle._width;

	//Move the handle based on the zoom ratio, scollable width, and the position of the content window.
	_root.sliderY.handle._x = (((_root.MaxScroll_bottom - _root.ContentWindow._y )/_root.TotalScrollableArea_Height / _root.ZoomRatio)*linelength)+lineLoc + _root.sliderY.handle._width/2;
}

function CreateXML(){


	NewXMLobj = new XML();
	NewXMLobj.xmlDecl = "<?xml version=\"1.0\" ?>";
	NewXMLobj.appendChild((new XML).createElement("content"));


	SideCounter = 0;

	for(SideCounter=0; SideCounter < _root.sideProperties.length; SideCounter++){

		//Make sure a delete flag wasn't set by the administrator for this side.
		if(_root.sideProperties[SideCounter]["status"] == "OK"){
		
			NewXMLobj.firstChild.appendChild((new XML).createElement("side"));

			NewXMLobj.firstChild.lastChild.appendChild((new XML).createElement("description"));
			NewXMLobj.firstChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["description"]));
			NewXMLobj.firstChild.lastChild.appendChild((new XML).createElement("initialzoom"));
			NewXMLobj.firstChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["initialzoom"]));
			NewXMLobj.firstChild.lastChild.appendChild((new XML).createElement("rotatecanvas"));
			NewXMLobj.firstChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["rotatecanvas"]));
			NewXMLobj.firstChild.lastChild.appendChild((new XML).createElement("contentwidth"));
			NewXMLobj.firstChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["contentwidth"]));
			NewXMLobj.firstChild.lastChild.appendChild((new XML).createElement("contentheight"));
			NewXMLobj.firstChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["contentheight"]));
			NewXMLobj.firstChild.lastChild.appendChild((new XML).createElement("backgroundimage"));
			NewXMLobj.firstChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["backgroundimage"]));
			NewXMLobj.firstChild.lastChild.appendChild((new XML).createElement("background_x"));
			NewXMLobj.firstChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["background_x"]));
			NewXMLobj.firstChild.lastChild.appendChild((new XML).createElement("background_y"));
			NewXMLobj.firstChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["background_y"]));
			NewXMLobj.firstChild.lastChild.appendChild((new XML).createElement("background_width"));
			NewXMLobj.firstChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["background_width"]));
			NewXMLobj.firstChild.lastChild.appendChild((new XML).createElement("background_height"));
			NewXMLobj.firstChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["background_height"]));
			NewXMLobj.firstChild.lastChild.appendChild((new XML).createElement("background_color"));
			NewXMLobj.firstChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["background_color"]));
			NewXMLobj.firstChild.lastChild.appendChild((new XML).createElement("scale"));
			NewXMLobj.firstChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["scale"]));
			NewXMLobj.firstChild.lastChild.appendChild((new XML).createElement("folds_horiz"));
			NewXMLobj.firstChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["folds_horiz"]));
			NewXMLobj.firstChild.lastChild.appendChild((new XML).createElement("folds_vert"));
			NewXMLobj.firstChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["folds_vert"]));
			NewXMLobj.firstChild.lastChild.appendChild((new XML).createElement("dpi"));
			NewXMLobj.firstChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["dpi"]));
			NewXMLobj.firstChild.lastChild.appendChild((new XML).createElement("show_boundary"));
			NewXMLobj.firstChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["show_boundary"]));
			NewXMLobj.firstChild.lastChild.appendChild((new XML).createElement("side_permissions"));
			NewXMLobj.firstChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["side_permissions"]));

			NewXMLobj.firstChild.lastChild.appendChild((new XML).createElement("color_definitions"));
			if(_root.sideProperties[SideCounter]["color_definitions"]["number_of_colors"] > 0 ){

				//The color array is 1 based.
				for(ColorDefinitionCounter=1; ColorDefinitionCounter <= _root.sideProperties[SideCounter]["color_definitions"]["number_of_colors"]; ColorDefinitionCounter++){

					NewXMLobj.firstChild.lastChild.lastChild.appendChild((new XML).createElement("color"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["color_definitions"][ColorDefinitionCounter]["hexcode"]));

					NewXMLobj.firstChild.lastChild.lastChild.lastChild.attributes.id = _root.sideProperties[SideCounter]["color_definitions"][ColorDefinitionCounter]["id"];
				}
			}
			
			
			NewXMLobj.firstChild.lastChild.appendChild((new XML).createElement("color_palette"));
			if(_root.sideProperties[SideCounter]["color_palette"]["number_of_palette_entries"] > 0 ){

				//The color array is 1 based.
				for(ColorPaletteCounter=1; ColorPaletteCounter <= _root.sideProperties[SideCounter]["color_palette"]["number_of_palette_entries"]; ColorPaletteCounter++){

					NewXMLobj.firstChild.lastChild.lastChild.appendChild((new XML).createElement("color"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["color_palette"][ColorPaletteCounter]["color_description"]));

					NewXMLobj.firstChild.lastChild.lastChild.lastChild.attributes.colorcode = _root.sideProperties[SideCounter]["color_palette"][ColorPaletteCounter]["hexcode"];
				}
			}
			
			
			
			
			

			// If we have a marker image then create the node for it.  Otherwise, do not include it in the XML file.
			if(_root.sideProperties[SideCounter]["has_marker_image"]){
				NewXMLobj.firstChild.lastChild.appendChild((new XML).createElement("marker_image"));
				NewXMLobj.firstChild.lastChild.lastChild.appendChild((new XML).createElement("imageid"));
				NewXMLobj.firstChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["marker_image_id"]));
				NewXMLobj.firstChild.lastChild.lastChild.appendChild((new XML).createElement("x_coordinate"));
				NewXMLobj.firstChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["marker_image_x"]));
				NewXMLobj.firstChild.lastChild.lastChild.appendChild((new XML).createElement("y_coordinate"));
				NewXMLobj.firstChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["marker_image_y"]));
				NewXMLobj.firstChild.lastChild.lastChild.appendChild((new XML).createElement("width"));
				NewXMLobj.firstChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["marker_image_width"]));
				NewXMLobj.firstChild.lastChild.lastChild.appendChild((new XML).createElement("height"));
				NewXMLobj.firstChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["marker_image_height"]));
			}

			// If we have a marker image then create the node for it.  Otherwise, do not include it in the XML file.
			if(_root.sideProperties[SideCounter]["has_mask_image"]){
				NewXMLobj.firstChild.lastChild.appendChild((new XML).createElement("mask_image"));
				NewXMLobj.firstChild.lastChild.lastChild.appendChild((new XML).createElement("imageid"));
				NewXMLobj.firstChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["mask_image_id"]));
				NewXMLobj.firstChild.lastChild.lastChild.appendChild((new XML).createElement("x_coordinate"));
				NewXMLobj.firstChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["mask_image_x"]));
				NewXMLobj.firstChild.lastChild.lastChild.appendChild((new XML).createElement("y_coordinate"));
				NewXMLobj.firstChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["mask_image_y"]));
				NewXMLobj.firstChild.lastChild.lastChild.appendChild((new XML).createElement("width"));
				NewXMLobj.firstChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["mask_image_width"]));
				NewXMLobj.firstChild.lastChild.lastChild.appendChild((new XML).createElement("height"));
				NewXMLobj.firstChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["mask_image_height"]));
			}


			for(LayerCounter=0; LayerCounter < _root.sideProperties[SideCounter]["Layers"].length; LayerCounter++){

				//Remember that a layer type of "none" means the layer was deleted. We don't want to record those layers back out to the XML documents.
				if(_root.sideProperties[SideCounter]["Layers"][LayerCounter]["type"] == "text" || _root.sideProperties[SideCounter]["Layers"][LayerCounter]["type"] == "graphic"){
					NewXMLobj.firstChild.lastChild.appendChild((new XML).createElement("layer"));

					NewXMLobj.firstChild.lastChild.lastChild.appendChild((new XML).createElement("level"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["Layers"][LayerCounter]["level"]));
					NewXMLobj.firstChild.lastChild.lastChild.appendChild((new XML).createElement("x_coordinate"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["Layers"][LayerCounter]["xcoordinate"]));
					NewXMLobj.firstChild.lastChild.lastChild.appendChild((new XML).createElement("y_coordinate"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["Layers"][LayerCounter]["ycoordinate"]));
					NewXMLobj.firstChild.lastChild.lastChild.appendChild((new XML).createElement("rotation"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["Layers"][LayerCounter]["rotation"]));

				}

				if(_root.sideProperties[SideCounter]["Layers"][LayerCounter]["type"] == "text"){

					NewXMLobj.firstChild.lastChild.lastChild.appendChild((new XML).createElement("text"));

					NewXMLobj.firstChild.lastChild.lastChild.lastChild.appendChild((new XML).createElement("font"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["font"]));

					NewXMLobj.firstChild.lastChild.lastChild.lastChild.appendChild((new XML).createElement("field_name"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["field_name"]));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.appendChild((new XML).createElement("size"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["size"]));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.appendChild((new XML).createElement("bold"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["bold"]));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.appendChild((new XML).createElement("italics"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["italics"]));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.appendChild((new XML).createElement("underline"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["underline"]));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.appendChild((new XML).createElement("field_order"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["field_order"]));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.appendChild((new XML).createElement("shadow_level_link"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["shadow_level_link"]));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.appendChild((new XML).createElement("shadow_distance"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["shadow_distance"]));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.appendChild((new XML).createElement("shadow_angle"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["shadow_angle"]));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.appendChild((new XML).createElement("align"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["align"]));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.appendChild((new XML).createElement("message"));
					
					//Before putting the text back into the XML doc make sure to replace line breaks with our special code !br! using the custom search & replace function
					//It will also translate the HTML character codes back to their original characters.  I need to do this  becuase the flash XML.send method will translate the characters back to their HTML codes.
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(UnTranslateHTMLentities(_root.ContentWindow.StringReplace(_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["message"], "\r", "!br!"))));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.appendChild((new XML).createElement("color"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["color"]));
				
					// Record the permission flags back into the XML
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.appendChild((new XML).createElement("permissions"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createElement("position_x_locked"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["permissions"]["position_x_locked"] ? "yes" : "no"));

					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createElement("position_y_locked"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["permissions"]["position_y_locked"] ? "yes" : "no"));
					
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createElement("size_locked"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["permissions"]["size_locked"] ? "yes" : "no"));
	
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createElement("deletion_locked"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["permissions"]["deletion_locked"] ? "yes" : "no"));
					
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createElement("color_locked"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["permissions"]["color_locked"] ? "yes" : "no"));

					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createElement("font_locked"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["permissions"]["font_locked"] ? "yes" : "no"));

					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createElement("alignment_locked"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["permissions"]["alignment_locked"] ? "yes" : "no"));

					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createElement("rotation_locked"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["permissions"]["rotation_locked"] ? "yes" : "no"));

					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createElement("data_locked"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["permissions"]["data_locked"] ? "yes" : "no"));

					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createElement("not_selectable"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["permissions"]["not_selectable"] ? "yes" : "no"));

					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createElement("not_transferable"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["permissions"]["not_transferable"] ? "yes" : "no"));


	
				}

				else if(_root.sideProperties[SideCounter]["Layers"][LayerCounter]["type"] == "graphic"){

					NewXMLobj.firstChild.lastChild.lastChild.appendChild((new XML).createElement("graphic"));

					NewXMLobj.firstChild.lastChild.lastChild.lastChild.appendChild((new XML).createElement("width"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["GraphicProperties"][LayerCounter]["width"]));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.appendChild((new XML).createElement("height"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["GraphicProperties"][LayerCounter]["height"]));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.appendChild((new XML).createElement("originalheight"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["GraphicProperties"][LayerCounter]["originalheight"]));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.appendChild((new XML).createElement("originalwidth"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["GraphicProperties"][LayerCounter]["originalwidth"]));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.appendChild((new XML).createElement("imageid"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["GraphicProperties"][LayerCounter]["imageid"]));
					
					// Make sure that if the VectorImageID does not exist... "null" is not put into the XML file.
					var VectorImageIDProperty = _root.sideProperties[SideCounter]["GraphicProperties"][LayerCounter]["VectorImageId"];
					var VectorImageIDforXML =  (VectorImageIDProperty != "" && VectorImageIDProperty != null) ? String(VectorImageIDProperty) : ""; 
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.appendChild((new XML).createElement("VectorImageId"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(VectorImageIDforXML));
					
					// Record the permission flags back into the XML
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.appendChild((new XML).createElement("permissions"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createElement("position_x_locked"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["GraphicProperties"][LayerCounter]["permissions"]["position_x_locked"] ? "yes" : "no"));

					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createElement("position_y_locked"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["GraphicProperties"][LayerCounter]["permissions"]["position_y_locked"] ? "yes" : "no"));

					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createElement("size_locked"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["GraphicProperties"][LayerCounter]["permissions"]["size_locked"] ? "yes" : "no"));
	
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createElement("deletion_locked"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["GraphicProperties"][LayerCounter]["permissions"]["deletion_locked"] ? "yes" : "no"));

					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createElement("rotation_locked"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["GraphicProperties"][LayerCounter]["permissions"]["rotation_locked"] ? "yes" : "no"));

					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createElement("not_selectable"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["GraphicProperties"][LayerCounter]["permissions"]["not_selectable"] ? "yes" : "no"));

					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createElement("not_transferable"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["GraphicProperties"][LayerCounter]["permissions"]["not_transferable"] ? "yes" : "no"));

					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createElement("always_on_top"));
					NewXMLobj.firstChild.lastChild.lastChild.lastChild.lastChild.lastChild.appendChild((new XML).createTextNode(_root.sideProperties[SideCounter]["GraphicProperties"][LayerCounter]["permissions"]["always_on_top"] ? "yes" : "no"));

				}

			}
			
		}

	}

}

//This function looks for the root tag "problem".  If it finds that tag then it will display an alert window with the message contained within.
function CheckXMLforErrors(XMLobj21){
	RootNode = new Array;

	RootNode = XMLobj21.childNodes;

	for(x=0; x < RootNode.length; ++x){
		if(RootNode[x].nodeName == "problem"){
			_root.ErrorWarning.Warn(String(RootNode[x].firstChild));
		}
	}
}


function ParseXML(){

	//Make sure there are no errors with this document.
	CheckXMLforErrors(_root.XMLobj);

	RootNode = new Array;
	SideNodes = new Array;
	LayerNodes = new Array;
	LayerPropertiesNodes = new Array;
	TextNodes = new Array;
	GraphicNodes = new Array;

	SideCounter = 0;

	RootNode = _root.XMLobj.childNodes;

	for(x=0; x < RootNode.length; ++x){
		if(RootNode[x].nodeName == "content"){
			StartingNode = RootNode[x];
		}
	}

	SideNodes = StartingNode.childNodes;

	for(j=0; j < SideNodes.length; ++j){

		if(SideNodes[j].nodeName == "side"){

			_root.sideProperties[SideCounter] = new Array;

			LayerCounter = 0;
			ColorDefinitionCounter = 0;
			ColorPaletteCounter = 0;

			_root.sideProperties[SideCounter]["Layers"] = new Array;
			_root.sideProperties[SideCounter]["TextProperties"] = new Array;
			_root.sideProperties[SideCounter]["GraphicProperties"] = new Array;
			_root.sideProperties[SideCounter]["color_definitions"] = new Array;
			_root.sideProperties[SideCounter]["color_palette"] = new Array;
			
			_root.sideProperties[SideCounter]["color_definitions"]["number_of_colors"] = 0;
			_root.sideProperties[SideCounter]["color_palette"]["number_of_palette_entries"] = 0;
			
			
			
			//I added this variable to the XML file recently... so it may be missing from a lot of XML templates....
			//Set the default value if the node isn't present within the XML file
			_root.sideProperties[SideCounter]["rotatecanvas"] = 0;
			// Same with this Field, it may be missing from many files, so default it to YES
			_root.sideProperties[SideCounter]["show_boundary"] = "yes";


			//When we delete a side (only done by the administrator) we just change this flag.  Then we don't need to need to re-organize the array.
			//The Upload XML function and Create Tabs are the two functions which check against this flag.
			_root.sideProperties[SideCounter]["status"] = "OK";

			LayerNodes = SideNodes[j].childNodes;

			for(z=0; z < LayerNodes.length; ++z){


				if(LayerNodes[z].nodeName == "description"){
					_root.sideProperties[SideCounter]["description"] = String(LayerNodes[z].firstChild);
				}
				else if(LayerNodes[z].nodeName == "initialzoom"){
					_root.sideProperties[SideCounter]["initialzoom"] = parseInt(String(LayerNodes[z].firstChild));
				}
				else if(LayerNodes[z].nodeName == "rotatecanvas"){
					_root.sideProperties[SideCounter]["rotatecanvas"] = parseInt(String(LayerNodes[z].firstChild));
				}
				else if(LayerNodes[z].nodeName == "contentwidth"){
					_root.sideProperties[SideCounter]["contentwidth"] = parseInt(String(LayerNodes[z].firstChild));
				}
				else if(LayerNodes[z].nodeName == "contentheight"){
					_root.sideProperties[SideCounter]["contentheight"] = parseInt(String(LayerNodes[z].firstChild));
				}
				else if(LayerNodes[z].nodeName == "backgroundimage"){
					_root.sideProperties[SideCounter]["backgroundimage"] = parseInt(String(LayerNodes[z].firstChild));
				}
				else if(LayerNodes[z].nodeName == "background_x"){
					_root.sideProperties[SideCounter]["background_x"] = parseInt(String(LayerNodes[z].firstChild));
				}
				else if(LayerNodes[z].nodeName == "background_y"){
					_root.sideProperties[SideCounter]["background_y"] = parseInt(String(LayerNodes[z].firstChild));
				}
				else if(LayerNodes[z].nodeName == "background_width"){
					_root.sideProperties[SideCounter]["background_width"] = parseInt(String(LayerNodes[z].firstChild));
				}
				else if(LayerNodes[z].nodeName == "background_height"){
					_root.sideProperties[SideCounter]["background_height"] = parseInt(String(LayerNodes[z].firstChild));
				}
				else if(LayerNodes[z].nodeName == "background_color"){
					_root.sideProperties[SideCounter]["background_color"] = parseInt(String(LayerNodes[z].firstChild));
				}
				else if(LayerNodes[z].nodeName == "scale"){
					_root.sideProperties[SideCounter]["scale"] = parseInt(String(LayerNodes[z].firstChild));
				}
				else if(LayerNodes[z].nodeName == "folds_horiz"){
					_root.sideProperties[SideCounter]["folds_horiz"] = parseInt(String(LayerNodes[z].firstChild));
				}
				else if(LayerNodes[z].nodeName == "folds_vert"){
					_root.sideProperties[SideCounter]["folds_vert"] = parseInt(String(LayerNodes[z].firstChild));
				}
				else if(LayerNodes[z].nodeName == "dpi"){
					_root.sideProperties[SideCounter]["dpi"] = parseInt(String(LayerNodes[z].firstChild));
				}
				else if(LayerNodes[z].nodeName == "show_boundary"){
					_root.sideProperties[SideCounter]["show_boundary"] = String(LayerNodes[z].firstChild);
				}
				else if(LayerNodes[z].nodeName == "side_permissions"){
					if(LayerNodes[z].firstChild == null){
						_root.sideProperties[SideCounter]["side_permissions"] = "";
					}
					else{
						_root.sideProperties[SideCounter]["side_permissions"] = String(LayerNodes[z].firstChild);
					}
				}
				else if(LayerNodes[z].nodeName == "color_definitions"){

					ColorNodes = LayerNodes[z].childNodes;

					for(q=0; q < ColorNodes.length; ++q){

						if(ColorNodes[q].nodeName == "color"){
							ColorDefinitionCounter++; //Make the array 1 based.
							_root.sideProperties[SideCounter]["color_definitions"][ColorDefinitionCounter] = new Array;
							_root.sideProperties[SideCounter]["color_definitions"][ColorDefinitionCounter]["hexcode"] = parseInt(String(ColorNodes[q].firstChild));
							_root.sideProperties[SideCounter]["color_definitions"][ColorDefinitionCounter]["id"] = parseInt(String(ColorNodes[q].attributes.id));						
						}

					}

					_root.sideProperties[SideCounter]["color_definitions"]["number_of_colors"] = ColorDefinitionCounter;

				}
				else if(LayerNodes[z].nodeName == "color_palette"){

					ColorNodes = LayerNodes[z].childNodes;

					for(q=0; q < ColorNodes.length; ++q){

						if(ColorNodes[q].nodeName == "color"){
							ColorPaletteCounter++; //Make the array 1 based.
							_root.sideProperties[SideCounter]["color_palette"][ColorPaletteCounter] = new Array;
							_root.sideProperties[SideCounter]["color_palette"][ColorPaletteCounter]["color_description"] = String(ColorNodes[q].firstChild);
							_root.sideProperties[SideCounter]["color_palette"][ColorPaletteCounter]["hexcode"] = parseInt(String(ColorNodes[q].attributes.colorcode));						
						}

					}

					_root.sideProperties[SideCounter]["color_palette"]["number_of_palette_entries"] = ColorPaletteCounter;

				}
				
				
				
				else if(LayerNodes[z].nodeName == "marker_image"){
					_root.sideProperties[SideCounter]["has_marker_image"] = true;
					
					MarkerImageNodes = LayerNodes[z].childNodes;

					for(q=0; q < MarkerImageNodes.length; ++q){
					
						if(MarkerImageNodes[q].nodeName == "imageid")
							_root.sideProperties[SideCounter]["marker_image_id"] = parseInt(String(MarkerImageNodes[q].firstChild));
						else if(MarkerImageNodes[q].nodeName == "x_coordinate")
							_root.sideProperties[SideCounter]["marker_image_x"] = parseInt(String(MarkerImageNodes[q].firstChild));
						else if(MarkerImageNodes[q].nodeName == "y_coordinate")
							_root.sideProperties[SideCounter]["marker_image_y"] = parseInt(String(MarkerImageNodes[q].firstChild));
						else if(MarkerImageNodes[q].nodeName == "width")
							_root.sideProperties[SideCounter]["marker_image_width"] = parseInt(String(MarkerImageNodes[q].firstChild));
						else if(MarkerImageNodes[q].nodeName == "height")
							_root.sideProperties[SideCounter]["marker_image_height"] = parseInt(String(MarkerImageNodes[q].firstChild));
					}
				}
				else if(LayerNodes[z].nodeName == "mask_image"){
					_root.sideProperties[SideCounter]["has_mask_image"] = true;
					
					MaskImageNodes = LayerNodes[z].childNodes;

					for(q=0; q < MaskImageNodes.length; ++q){
					
						if(MaskImageNodes[q].nodeName == "imageid")
							_root.sideProperties[SideCounter]["mask_image_id"] = parseInt(String(MaskImageNodes[q].firstChild));
						else if(MaskImageNodes[q].nodeName == "x_coordinate")
							_root.sideProperties[SideCounter]["mask_image_x"] = parseInt(String(MaskImageNodes[q].firstChild));
						else if(MaskImageNodes[q].nodeName == "y_coordinate")
							_root.sideProperties[SideCounter]["mask_image_y"] = parseInt(String(MaskImageNodes[q].firstChild));
						else if(MaskImageNodes[q].nodeName == "width")
							_root.sideProperties[SideCounter]["mask_image_width"] = parseInt(String(MaskImageNodes[q].firstChild));
						else if(MaskImageNodes[q].nodeName == "height")
							_root.sideProperties[SideCounter]["mask_image_height"] = parseInt(String(MaskImageNodes[q].firstChild));
					}
				}
				else if(LayerNodes[z].nodeName == "layer"){

					_root.sideProperties[SideCounter]["Layers"][LayerCounter] = new Array;


					LayerPropertiesNodes = LayerNodes[z].childNodes;

					for(q=0; q < LayerPropertiesNodes.length; ++q){

						if(LayerPropertiesNodes[q].nodeName == "x_coordinate"){
							_root.sideProperties[SideCounter]["Layers"][LayerCounter]["xcoordinate"] = parseFloat(String(LayerPropertiesNodes[q].firstChild));
						}
						else if(LayerPropertiesNodes[q].nodeName == "y_coordinate"){
							_root.sideProperties[SideCounter]["Layers"][LayerCounter]["ycoordinate"] = parseFloat(String(LayerPropertiesNodes[q].firstChild));
						}
						else if(LayerPropertiesNodes[q].nodeName == "level"){
							_root.sideProperties[SideCounter]["Layers"][LayerCounter]["level"] = parseInt(String(LayerPropertiesNodes[q].firstChild));
						}
						else if(LayerPropertiesNodes[q].nodeName == "rotation"){
							_root.sideProperties[SideCounter]["Layers"][LayerCounter]["rotation"] = parseInt(String(LayerPropertiesNodes[q].firstChild));
						}
						else if(LayerPropertiesNodes[q].nodeName == "text"){

							_root.sideProperties[SideCounter]["Layers"][LayerCounter]["type"] = "text";

							_root.sideProperties[SideCounter]["TextProperties"][LayerCounter] = new Array;
							
							
							// These parameters were added after the fact.  There are not elements within all artwork XML files.  Just initialize the values to null for now, and let them get overriden, it they exist (on newer files)
							_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["shadow_level_link"] = "";
							_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["shadow_distance"] = "";
							_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["shadow_angle"] = "";
							
							// Set all permissions to False until proven otherwise
							_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["permissions"] = new Array;
							_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["permissions"]["position_x_locked"] = false;
							_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["permissions"]["position_y_locked"] = false;
							_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["permissions"]["size_locked"] = false;
							_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["permissions"]["deletion_locked"] = false;
							_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["permissions"]["color_locked"] = false;
							_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["permissions"]["font_locked"] = false;
							_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["permissions"]["alignment_locked"] = false;
							_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["permissions"]["rotation_locked"] = false;
							_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["permissions"]["data_locked"] = false;
							_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["permissions"]["not_selectable"] = false;
							_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["permissions"]["not_transferable"] = false;
							
							_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["permissions"]["debug"] = "testing entered from XML";

							

							TextNodes = LayerPropertiesNodes[q].childNodes;

							for(w=0; w < TextNodes.length; ++w){

								if(TextNodes[w].nodeName == "font"){
									_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["font"] = String(TextNodes[w].firstChild);
								}
								else if(TextNodes[w].nodeName == "size"){
									_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["size"] = String(TextNodes[w].firstChild);
								}
								else if(TextNodes[w].nodeName == "field_name"){
									if(TextNodes[w].firstChild == null){
										_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["field_name"] = "";
									}
									else{
										_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["field_name"] = String(TextNodes[w].firstChild);
									}
								}
								else if(TextNodes[w].nodeName == "field_order"){
									_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["field_order"] = parseInt(String(TextNodes[w].firstChild));
								}
								else if(TextNodes[w].nodeName == "shadow_level_link"){
									if(TextNodes[w].firstChild == null)
										_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["shadow_level_link"] = "";
									else
										_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["shadow_level_link"] = parseInt(String(TextNodes[w].firstChild));
								}
								else if(TextNodes[w].nodeName == "shadow_distance"){
									if(TextNodes[w].firstChild == null)
										_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["shadow_distance"] = "";
									else
										_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["shadow_distance"] = parseInt(String(TextNodes[w].firstChild));
								}
								else if(TextNodes[w].nodeName == "shadow_angle"){
									if(TextNodes[w].firstChild == null)
										_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["shadow_angle"] = "";
									else
										_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["shadow_angle"] = parseInt(String(TextNodes[w].firstChild));
								}
								else if(TextNodes[w].nodeName == "permissions"){
									if(TextNodes[w].firstChild == null)
										continue;
									
									PermissionNodes = TextNodes[w].childNodes;

									for(p=0; p < PermissionNodes.length; ++p){
										
										permissionString = String(PermissionNodes[p].firstChild);
										permissionFlag = false;
										if(permissionString.toUpperCase() == "YES")
											permissionFlag = true;
									
										if(PermissionNodes[p].nodeName == "position_x_locked")
											_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["permissions"]["position_x_locked"] = permissionFlag;
										else if(PermissionNodes[p].nodeName == "position_y_locked")
											_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["permissions"]["position_y_locked"] = permissionFlag;
										else if(PermissionNodes[p].nodeName == "size_locked")
											_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["permissions"]["size_locked"] = permissionFlag;
										else if(PermissionNodes[p].nodeName == "deletion_locked")
											_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["permissions"]["deletion_locked"] = permissionFlag;
										else if(PermissionNodes[p].nodeName == "color_locked")
											_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["permissions"]["color_locked"] = permissionFlag;
										else if(PermissionNodes[p].nodeName == "font_locked")
											_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["permissions"]["font_locked"] = permissionFlag;
										else if(PermissionNodes[p].nodeName == "alignment_locked")
											_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["permissions"]["alignment_locked"] = permissionFlag;				
										else if(PermissionNodes[p].nodeName == "rotation_locked")
											_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["permissions"]["rotation_locked"] = permissionFlag;
										else if(PermissionNodes[p].nodeName == "data_locked")
											_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["permissions"]["data_locked"] = permissionFlag;
										else if(PermissionNodes[p].nodeName == "not_selectable")
											_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["permissions"]["not_selectable"] = permissionFlag;
										else if(PermissionNodes[p].nodeName == "not_transferable")
											_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["permissions"]["not_transferable"] = permissionFlag;																
									}
								}
								else if(TextNodes[w].nodeName == "bold"){
									_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["bold"] = String(TextNodes[w].firstChild);
								}
								else if(TextNodes[w].nodeName == "italics"){
									_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["italics"] = String(TextNodes[w].firstChild);
								}
								else if(TextNodes[w].nodeName == "underline"){
									_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["underline"] = String(TextNodes[w].firstChild);
								}
								else if(TextNodes[w].nodeName == "align"){
									_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["align"] = String(TextNodes[w].firstChild);
								}
								else if(TextNodes[w].nodeName == "color"){
									_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["color"] = parseInt(String(TextNodes[w].firstChild));
								}
								else if(TextNodes[w].nodeName == "message"){

									if(TextNodes[w].firstChild == null){
										_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["message"] = "";
									}
									else{
										//We are replacing !br! tags with Line breaks.
										//We put them back to !br! when we save the xml doc back out.  
										_root.sideProperties[SideCounter]["TextProperties"][LayerCounter]["message"] = _root.ContentWindow.StringReplace(String(TextNodes[w].firstChild), "!br!", "\r"); 
									}

								}
							}
						}
						else if(LayerPropertiesNodes[q].nodeName == "graphic"){

							_root.sideProperties[SideCounter]["Layers"][LayerCounter]["type"] = "graphic";

							//If this image is stored in the xml doc then we can assume that the image is proper becuase it was successfully uploaded through drawtech at some point.  Let the program know that it is not a new graphic.  
							_root.sideProperties[SideCounter]["Layers"][LayerCounter]["newgraphic"] = "no";

							_root.sideProperties[SideCounter]["GraphicProperties"][LayerCounter] = new Array;

							// Set all permissions to False until proven otherwise
							_root.sideProperties[SideCounter]["GraphicProperties"][LayerCounter]["permissions"] = new Array;
							_root.sideProperties[SideCounter]["GraphicProperties"][LayerCounter]["permissions"]["position_x_locked"] = false;
							_root.sideProperties[SideCounter]["GraphicProperties"][LayerCounter]["permissions"]["position_y_locked"] = false;
							_root.sideProperties[SideCounter]["GraphicProperties"][LayerCounter]["permissions"]["size_locked"] = false;
							_root.sideProperties[SideCounter]["GraphicProperties"][LayerCounter]["permissions"]["deletion_locked"] = false;
							_root.sideProperties[SideCounter]["GraphicProperties"][LayerCounter]["permissions"]["rotation_locked"] = false;
							_root.sideProperties[SideCounter]["GraphicProperties"][LayerCounter]["permissions"]["not_selectable"] = false;
							_root.sideProperties[SideCounter]["GraphicProperties"][LayerCounter]["permissions"]["not_transferable"] = false;
							_root.sideProperties[SideCounter]["GraphicProperties"][LayerCounter]["permissions"]["always_on_top"] = false;

							// This was added later... so it may not be specified in All XML files.
							_root.sideProperties[SideCounter]["GraphicProperties"][LayerCounter]["VectorImageId"] = "";

							GraphicNodes = LayerPropertiesNodes[q].childNodes;

							for(w=0; w < GraphicNodes.length; ++w){

								if(GraphicNodes[w].nodeName == "height"){
									_root.sideProperties[SideCounter]["GraphicProperties"][LayerCounter]["height"] = parseInt(String(GraphicNodes[w].firstChild));
								}
								else if(GraphicNodes[w].nodeName == "width"){
									_root.sideProperties[SideCounter]["GraphicProperties"][LayerCounter]["width"] = parseInt(String(GraphicNodes[w].firstChild));
								}
								else if(GraphicNodes[w].nodeName == "originalheight"){
									_root.sideProperties[SideCounter]["GraphicProperties"][LayerCounter]["originalheight"] = parseInt(String(GraphicNodes[w].firstChild));
								}
								else if(GraphicNodes[w].nodeName == "originalwidth"){
									_root.sideProperties[SideCounter]["GraphicProperties"][LayerCounter]["originalwidth"] = parseInt(String(GraphicNodes[w].firstChild));
								}
								else if(GraphicNodes[w].nodeName == "imageid"){
									_root.sideProperties[SideCounter]["GraphicProperties"][LayerCounter]["imageid"] = parseInt(String(GraphicNodes[w].firstChild));
								}
								else if(GraphicNodes[w].nodeName == "VectorImageId"){
									// The vector Image ID may not allways exist inside of a Graphic Node... so don't try to parse an integer out of null
									if(GraphicNodes[w].firstChild != null)
										_root.sideProperties[SideCounter]["GraphicProperties"][LayerCounter]["VectorImageId"] = String(GraphicNodes[w].firstChild);
								}
								else if(GraphicNodes[w].nodeName == "permissions"){
									if(GraphicNodes[w].firstChild == null)
										continue;
									
									PermissionNodes = GraphicNodes[w].childNodes;

									for(p=0; p < PermissionNodes.length; ++p){
										
										permissionString = String(PermissionNodes[p].firstChild);
										permissionFlag = false;
										if(permissionString.toUpperCase() == "YES")
											permissionFlag = true;
									
										if(PermissionNodes[p].nodeName == "position_x_locked")
											_root.sideProperties[SideCounter]["GraphicProperties"][LayerCounter]["permissions"]["position_x_locked"] = permissionFlag;
										else if(PermissionNodes[p].nodeName == "position_y_locked")
											_root.sideProperties[SideCounter]["GraphicProperties"][LayerCounter]["permissions"]["position_y_locked"] = permissionFlag;
										else if(PermissionNodes[p].nodeName == "size_locked")
											_root.sideProperties[SideCounter]["GraphicProperties"][LayerCounter]["permissions"]["size_locked"] = permissionFlag;
										else if(PermissionNodes[p].nodeName == "deletion_locked")
											_root.sideProperties[SideCounter]["GraphicProperties"][LayerCounter]["permissions"]["deletion_locked"] = permissionFlag;
										else if(PermissionNodes[p].nodeName == "rotation_locked")
											_root.sideProperties[SideCounter]["GraphicProperties"][LayerCounter]["permissions"]["rotation_locked"] = permissionFlag;
										else if(PermissionNodes[p].nodeName == "not_selectable")
											_root.sideProperties[SideCounter]["GraphicProperties"][LayerCounter]["permissions"]["not_selectable"] = permissionFlag;
										else if(PermissionNodes[p].nodeName == "not_transferable")
											_root.sideProperties[SideCounter]["GraphicProperties"][LayerCounter]["permissions"]["not_transferable"] = permissionFlag;
										else if(PermissionNodes[p].nodeName == "always_on_top")
											_root.sideProperties[SideCounter]["GraphicProperties"][LayerCounter]["permissions"]["always_on_top"] = permissionFlag;
															
									}
								}

							}

						}
					}
					LayerCounter++;
				}

			}
			SideCounter++;
		}

	}

	_root.DownloadItemsRemaining.gotoAndStop(16);

	_root.admin._visible = false;

	_root.CreateSideTabs();

	//make sure the Grid is Off by default.
	_root.Grid._visible = false;

	//Move the grid into the right position.
	_root.Grid._y = 200;


	if(_root.useragent == "MSIE"){
		if(!_root.TestMode)
			fscommand("XMLparsed", "");
	}
	else{
		if(!_root.TestMode)
			getURL("Javascript:drawtech_DoFSCommand('XMLparsed','')")
	}


	if(_root.TestMode){
		_root.ContentWindow.DisplaySide(0);
	}
	else{
	
		TheTime = new Date();
		NoCache = TheTime.getTime();
		
		//Request from the server what side number we are supposed to display.
		response_XMLobj = new XML ;
		response_XMLobj.onLoad = IntitializePickSide ;
		dummy_XMLobj = new XML ;
		dummy_XMLobj.sendAndLoad("draw_getsidenumber.php?nocache=" + NoCache, response_XMLobj);
	}


}


//This fucntion will be called after the response_XMLobj.onLoad call below
//We will get back an XML document from the server telling us what side to display for user.
//This is a little more messy than just getting the Side number from Javascript.. but I can't reliably get values from Javascript for all browsers on start up.
//Alternatively I could pass variables to the flash movie within the HTML for start up.. Such as mymovie.swf?name-value.
//The problem with that is that the browser would not cache this flash application. 
function IntitializePickSide(){
	
	var SideNumberToDisplay = "";

	RootNode = new Array;
	InfoNodes = new Array;

	RootNode = response_XMLobj.childNodes;
	
	for(x=0; x < RootNode.length; ++x){
		if(RootNode[x].nodeName == "response"){

			InfoNodes = RootNode[x].childNodes;

			for(j=0; j < InfoNodes.length; ++j){
				if(InfoNodes[j].nodeName == "side"){
					SideNumberToDisplay = String(InfoNodes[j].firstChild);
				}
				else if(InfoNodes[j].nodeName == "useragent"){
					useragent = String(InfoNodes[j].firstChild);
				}
			}
		}
	}

	//If we didn't find a side number then there is a problem.
	if(SideNumberToDisplay == ""){
		_root.ErrorWarning.Warn("There was a problem downloading information from the server. Make sure your computer is not hidden behind a proxy server.  You may want to start this project over again.  If the problem persists you can email webmaster@PrintsMadeEasy.com");
	}
	else{
		_root.ContentWindow.DisplaySide(parseInt(SideNumberToDisplay));
	}
}


function HighlightSideTab(SelectedSideNumber){


	//Loop through all of the sides and Highlight the Side that the user just clicked on.
	for(j=0; j < _root.sideProperties.length; j++){

		//Get a target for the Tab Clip
		SideTabObj = eval("_root.SideTab" + j);

		//Make the Tab look 'highlighted' when we are on the side that they chose to view.
		if(j == SelectedSideNumber){
			SideTabObj.gotoAndStop(2);
			SideTabObj.TabBackground.gotoAndStop(7);
		}
		else{
			SideTabObj.gotoAndStop(1);
			SideTabObj.TabBackground.gotoAndStop(2);
		}
	}

}


function ShowNewTab(NewSideNumber){

	//Loop through all of the sides and Highlight the Side that the user just clicked on.
	for(j=0; j < _root.sideProperties.length; j++){

		//Make the Tab look 'highlighted' when we are on the side that they chose to view.
		if(j == NewSideNumber){

			//Get a target for the Tab Clip
			SideTabObj = eval("_root.SideTab" + j);

			SideTabObj.NewTab.gotoAndPlay(3);
		}
	}
}

//This function takes 2 parameters.  If you pass in 1 then don't pass in the other.
//1) The Number corresponding to the Side in the Global Array to remove
//2) The number of visible tabs counting from the left.  1st tab starts counting at 1.
//Function will find out which side the user should be directed to after the deletion.
function RemoveSide(SideToRemove, TabNumber){

	SideCounter = 0;

	//Loop through the Global Array of the Side Properties and record how many active sides we have.. (that have not been deleted)
	for(j=0; j < _root.sideProperties.length; j++){
		if(_root.sideProperties[j]["status"] == "OK"){
			SideCounter++;
		}
	}

	TabCounter = 0;

	if(TabNumber != ""){

		//Loop through the Global Array of the Side Properties and record how many active sides we have.. (that have not been deleted)
		for(j=0; j < _root.sideProperties.length; j++){
			if(_root.sideProperties[j]["status"] == "OK"){
				TabCounter++;

				if(TabCounter == TabNumber){
					SideToRemove = j;
					break;
				}
			}
		}

	}

	if(SideToRemove == ""){
		_root.ErrorWarning.Warn("There was an error trying to remove the side.");
		return false;
	}


	if(SideCounter == 1){
		_root.ErrorWarning.Warn("You are not allowed to remove the last side.")
		return false;
	}
	else{

		//This must be called before we set the Delete flag.  Tabs won't be removed if the delete flag has been set.
		_root.RemoveSideTabs();

		_root.sideProperties[SideToRemove]["status"] = "deleted";

		//Create a new set of tabs that won't show the one that was just deleted.
		_root.CreateSideTabs();


		//Loop through the Global Array of the Side Properties and find the first tab which has not been deleted
		//Since we just deleted a side we have to take the use to a new side which has not been removed.
		//Since Side[0] may have a delete flag set.. We can not trust sending the use to Side[0]
		SideToDisplay = "";
		for(j=0; j < _root.sideProperties.length; j++){

			if(_root.sideProperties[j]["status"] == "OK"){
				if(SideToDisplay == ""){
					SideToDisplay = j;
				}
			}
		}

		return SideToDisplay;

	}

}

//This funtion is called when a person clicks on a diagnol resize button.  It is kinda sloppy... but easier to set a global variable
//Figure out the Ratio  Width/Height.. So that when we scale diagonaly we can maintain proper Aspect Ratio.
//This is basically the Slope of the line.. Rise over run.
function GetImageAspectRatio(){

	//If the graphic is rotated by 90 degrees then the heigh/width parameters should be reversed
	if(_root.ContentWindow.LayerSelectedObj._rotation == 0 || _root.ContentWindow.LayerSelectedObj._rotation == 180 || _root.ContentWindow.LayerSelectedObj._rotation == -180){

		_root.ImageClipRatio = _root.ContentWindow.LayerSelectedObj.ImageClip._height / _root.ContentWindow.LayerSelectedObj.ImageClip._width;
	}
	else{
		_root.ImageClipRatio = _root.ContentWindow.LayerSelectedObj.ImageClip._width / _root.ContentWindow.LayerSelectedObj.ImageClip._height;

	}

}

function GetDegreeBetween180(OldAngle){

	NewAngle = OldAngle;

	if(OldAngle > 180){
		NewAngle = -360 + OldAngle;
	}
	else if(OldAngle < -180){
		NewAngle = 360 + NewAngleForContentWindow;
	}

	return Math.round(NewAngle);
}






//Every function call must contain enough information in it so that we can use the Undo/Redo buttons in both directions
//The user should be able to go back and forth and infinite amount of times.
//Whenver the user finishes doing a command such as... inserting an image... then the function UNDO_InsertImage should be called.



function UNDO_InsertOrDeleteImage(LayerObj, UndoType){

	CleanUndoTopofStack();

	len = _root.UndoInfo.length;

	_root.UndoInfo[len] = new Array;
	
	if(UndoType == "insert"){
		_root.UndoInfo[len]["type"] = "InsertImage";
	}
	else if(UndoType == "delete"){
		_root.UndoInfo[len]["type"] = "DeleteImage";
	}
	else{
		trace("Invalid UndoType parameter in function UNDO_InsertOrDeleteImage")
	}

	_root.UndoInfo[len]["LayerNumber"] = LayerObj.LayerNumber;
	_root.UndoInfo[len]["xcoordinate"] = LayerObj._x;
	_root.UndoInfo[len]["ycoordinate"] = LayerObj._y;
	_root.UndoInfo[len]["rotation"] = LayerObj._rotation;
	_root.UndoInfo[len]["Width"] = LayerObj.ImageClip._width;
	_root.UndoInfo[len]["Height"] = LayerObj.ImageClip._height;
	_root.UndoInfo[len]["ImageID"] = LayerObj.ImageID;
	_root.UndoInfo[len]["VectorImageId"] = LayerObj.VectorImageId;
	_root.UndoInfo[len]["OriginalWidth"] = LayerObj.OriginalWidth;
	_root.UndoInfo[len]["OriginalHeight"] = LayerObj.OriginalHeight;
	_root.UndoInfo[len]["level"] = LayerObj.ClipDepth;
	_root.UndoInfo[len]["permissions"] = LayerObj.permissions;
	
	_root.UndoPosition++;

	//In case the undo/redo buttons should change
	ShowUndoRedoButtons();

}

function UNDO_DeleteImage(LayerNo, ImageID, Height, Width, Xcoord, Ycoord){
	

}
function UNDO_MoveImage(LayerObj){


	//Find out if the layer has been moved.  They may have just clicked on the layer and let their mouse up.
	if(LayerObj.UNDO_X != LayerObj._x || LayerObj.UNDO_Y != LayerObj._y){

		CleanUndoTopofStack();

		len = _root.UndoInfo.length;

		_root.UndoInfo[len] = new Array;
		_root.UndoInfo[len]["type"] = "MoveImage";
		_root.UndoInfo[len]["LayerNumber"] = LayerObj.LayerNumber;
		_root.UndoInfo[len]["OldXcoord"] = LayerObj.UNDO_X;
		_root.UndoInfo[len]["OldYcoord"] = LayerObj.UNDO_Y;
		_root.UndoInfo[len]["NewXcoord"] = LayerObj._x;
		_root.UndoInfo[len]["NewYcoord"] = LayerObj._y;
		
		_root.UndoPosition++;
		
		//In case the undo/redo buttons should change
		ShowUndoRedoButtons();
		
		
		// If they don't have permissions to move the layer then roll back the movement
		if(LayerObj.permissions["position_x_locked"] || LayerObj.permissions["position_y_locked"]){

			if(LayerObj.permissions["deletion_locked"])
				mayDeleteMessage = "";
			else
				mayDeleteMessage = "  However it is possible to delete this image by hitting the Delete button on your keyboard.";



			_root.ErrorWarning.AlertMessage("This position of this image may not be changed." + mayDeleteMessage);
			UndoCommand();
			CleanUndoTopofStack();
			ShowUndoRedoButtons();
		}

	}	

}
function UNDO_ResizeImage(LayerObj){


	//Find out if the layer has been moved.  They may have just clicked on the button and let their mouse up.
	if(LayerObj.UNDO_Width != LayerObj.imageclip._width || LayerObj.UNDO_Height != LayerObj.imageclip._height){

		CleanUndoTopofStack();

		len = _root.UndoInfo.length;

		_root.UndoInfo[len] = new Array;
		_root.UndoInfo[len]["type"] = "ResizeImage";
		_root.UndoInfo[len]["LayerNumber"] = LayerObj.LayerNumber;
		_root.UndoInfo[len]["OldWidth"] = LayerObj.UNDO_Width;
		_root.UndoInfo[len]["OldHeight"] = LayerObj.UNDO_Height;
		_root.UndoInfo[len]["NewWidth"] = LayerObj.imageclip._width;
		_root.UndoInfo[len]["NewHeight"] = LayerObj.imageclip._height;
		
		_root.UndoPosition++;
		
		//In case the undo/redo buttons should change
		ShowUndoRedoButtons();
		
		//To figure out if the Image has been stretched too big
		_root.ContentWindow.CheckForImageDPI(LayerObj);
	}

}
function UNDO_RotateImage(LayerNo, OldRotation, NewRotation){
	
	if(OldRotation != NewRotation){

		CleanUndoTopofStack();

		len = _root.UndoInfo.length;

		_root.UndoInfo[len] = new Array;
		_root.UndoInfo[len]["type"] = "RotateImage";
		_root.UndoInfo[len]["LayerNumber"] = LayerNo;
		_root.UndoInfo[len]["OldRotation"] = OldRotation;
		_root.UndoInfo[len]["NewRotation"] = NewRotation;

		
		_root.UndoPosition++;
		
		//In case the undo/redo buttons should change
		ShowUndoRedoButtons();
	}

}
function UNDO_MoveText(LayerObj, TimePeriod){


	//Find out if the layer has been moved.  They may have just clicked on the layer and let their mouse up.
	if(LayerObj.UNDO_X != LayerObj._x || LayerObj.UNDO_Y != LayerObj._y){

		//So that we know before saving to recaluate coordinates.  Some preciscion is lost going back and forth.  Only do it if we know the coordinates are changing
		_root.ContentWindow.LayerNeedsCoordinatesRecaclulated(LayerObj);

		CleanUndoTopofStack();

		len = _root.UndoInfo.length;

		_root.UndoInfo[len] = new Array;
		_root.UndoInfo[len]["type"] = "MoveText";
		_root.UndoInfo[len]["LayerNumber"] = LayerObj.LayerNumber;
		_root.UndoInfo[len]["OldXcoord"] = LayerObj.UNDO_X;
		_root.UndoInfo[len]["OldYcoord"] = LayerObj.UNDO_Y;
		_root.UndoInfo[len]["NewXcoord"] = LayerObj._x;
		_root.UndoInfo[len]["NewYcoord"] = LayerObj._y;
		_root.UndoInfo[len]["TimePeriod"] = TimePeriod;
		
		_root.UndoPosition++;
		
		//In case the undo/redo buttons should change
		ShowUndoRedoButtons();
		
		// If they don't have permissions to move the layer then roll back the movement
		if(LayerObj.permissions["position_x_locked"] || LayerObj.permissions["position_y_locked"]){
		
			if(LayerObj.permissions["deletion_locked"])
				mayDeleteMessage = "";
			else
				mayDeleteMessage = "  However it is possible to delete this text layer by hitting the Delete button on your keyboard.";
			
			_root.ErrorWarning.AlertMessage("This position of this layer may not be changed." + mayDeleteMessage);
			UndoCommand();
			CleanUndoTopofStack();
			ShowUndoRedoButtons();
		}
	
	}
	
	



}
function UNDO_ChangeFont(LayerObj, OldFontName, NewFontName){

	//Find out if the layer has been moved.  They may have just clicked on the layer and let their mouse up.
	if(OldFontName != NewFontName){

		CleanUndoTopofStack();

		len = _root.UndoInfo.length;

		_root.UndoInfo[len] = new Array;
		_root.UndoInfo[len]["type"] = "ChangeFont";
		_root.UndoInfo[len]["LayerNumber"] = LayerObj.LayerNumber;
		_root.UndoInfo[len]["OldFontName"] = OldFontName;
		_root.UndoInfo[len]["NewFontName"] = NewFontName;
		
		_root.UndoPosition++;
		
		//In case the undo/redo buttons should change
		ShowUndoRedoButtons();
	}

}
function UNDO_ResizeFont(LayerObj){

	//Find out if the text has been resized.
	if(LayerObj.UNDO_TextSize != LayerObj.TextSize ){

		//So that we know before saving to recaluate coordinates.  Some preciscion is lost going back and forth.  Only do it if we know the coordinates are changing
		_root.ContentWindow.LayerNeedsCoordinatesRecaclulated(LayerObj);

		CleanUndoTopofStack();

		len = _root.UndoInfo.length;
		
	
		//The default text size is 8.  So our calculation is relative to that.
		StartTextScale = LayerObj.UNDO_TextSize / 8 * 100;
		EndTextScale = LayerObj.TextSize / 8 * 100;
	

		_root.UndoInfo[len] = new Array;
		_root.UndoInfo[len]["type"] = "ResizeFont";
		_root.UndoInfo[len]["LayerNumber"] = LayerObj.LayerNumber;
		
		_root.UndoInfo[len]["StartTextSize"] = LayerObj.UNDO_TextSize;
		_root.UndoInfo[len]["EndTextSize"] = LayerObj.TextSize;
		
		_root.UndoPosition++;
		
		//In case the undo/redo buttons should change
		ShowUndoRedoButtons();
	}	

}
function UNDO_UpdateText(LayerObj, OldTextInfo, NewTextInfo){

	//We are not going to check if the old string equals the new string... it might be to processor intensive????
	
	CleanUndoTopofStack();

	len = _root.UndoInfo.length;

	_root.UndoInfo[len] = new Array;
	_root.UndoInfo[len]["type"] = "UpdateText";
	_root.UndoInfo[len]["LayerNumber"] = LayerObj.LayerNumber;
	_root.UndoInfo[len]["OldTextInfo"] = OldTextInfo;
	_root.UndoInfo[len]["NewTextInfo"] = NewTextInfo;

	_root.UndoPosition++;

	//In case the undo/redo buttons should change
	ShowUndoRedoButtons();


}
function UNDO_InsertOrDeleteText(LayerObj, UndoType){

	CleanUndoTopofStack();

	len = _root.UndoInfo.length;

	_root.UndoInfo[len] = new Array;
	
	if(UndoType == "insert"){
		_root.UndoInfo[len]["type"] = "InsertText";
	}
	else if(UndoType == "delete"){
		_root.UndoInfo[len]["type"] = "DeleteText";
	}
	else{
		trace("Invalid UndoType parameter in function UNDO_InsertOrDeleteText")
	}



	//Convert the X & Y coordinates.. Returns as an array
	ConertedCoordArr = _root.ContentWindow.GetTextLayerSaveCoordinates(LayerObj);
	SaveCoord_X = ConertedCoordArr[0];
	SaveCoord_Y = ConertedCoordArr[1];


	_root.UndoInfo[len]["LayerNumber"] = LayerObj.LayerNumber;
	_root.UndoInfo[len]["xcoordinate"] = SaveCoord_X;
	_root.UndoInfo[len]["ycoordinate"] = SaveCoord_Y;
	_root.UndoInfo[len]["rotation"] = LayerObj._rotation;
	
	_root.UndoInfo[len]["align"] = LayerObj.TextAlignment;
	_root.UndoInfo[len]["color"] = LayerObj.Color;
	_root.UndoInfo[len]["italics"] = LayerObj.TextItalic;
	_root.UndoInfo[len]["bold"] = LayerObj.TextBold;
	_root.UndoInfo[len]["underline"] = LayerObj.TextUnderline;
	_root.UndoInfo[len]["color"] = LayerObj.Color;
	_root.UndoInfo[len]["font"] = LayerObj.TextFont;
	_root.UndoInfo[len]["size"] = LayerObj.TextSize;
	_root.UndoInfo[len]["field_order"] = "";
	_root.UndoInfo[len]["shadow_level_link"] = "";
	_root.UndoInfo[len]["shadow_distance"] = "";
	_root.UndoInfo[len]["shadow_angle"] = "";
	_root.UndoInfo[len]["message"] = LayerObj.MainTextHolder;
	_root.UndoInfo[len]["permissions"] = LayerObj.permissions;

	_root.UndoPosition++;

	//In case the undo/redo buttons should change
	ShowUndoRedoButtons();

}
function UNDO_ChangeTextColor(LayerObj, OldColorCode, NewColorCode){

	//Find out if the layer has been moved.  They may have just clicked on the layer and let their mouse up.
	if(OldColorCode != NewColorCode){

		CleanUndoTopofStack();

		len = _root.UndoInfo.length;

		_root.UndoInfo[len] = new Array;
		_root.UndoInfo[len]["type"] = "ChangeTextColor";
		_root.UndoInfo[len]["LayerNumber"] = LayerObj.LayerNumber;
		_root.UndoInfo[len]["OldColorCode"] = OldColorCode;
		_root.UndoInfo[len]["NewColorCode"] = NewColorCode;
		
		_root.UndoPosition++;
		
		//In case the undo/redo buttons should change
		ShowUndoRedoButtons();
	}

}

//The TimePeriod parameter says how long it should take to snap back to place
function UNDO_RotateText(LayerObj, TimePeriod){
	
	
	//Find out if the layer has been moved.  They may have just clicked on the layer and let their mouse up.
	if(LayerObj.UNDO_Rotation != LayerObj._rotation){

		//So that we know before saving to recaluate coordinates.  Some preciscion is lost going back and forth.  Only do it if we know the coordinates are changing
		_root.ContentWindow.LayerNeedsCoordinatesRecaclulated(LayerObj);

		CleanUndoTopofStack();

		len = _root.UndoInfo.length;

		_root.UndoInfo[len] = new Array;
		_root.UndoInfo[len]["type"] = "RotateText";
		_root.UndoInfo[len]["LayerNumber"] = LayerObj.LayerNumber;
		_root.UndoInfo[len]["OldRotation"] = LayerObj.UNDO_Rotation;
		_root.UndoInfo[len]["NewRotation"] = LayerObj._rotation;
		_root.UndoInfo[len]["TimePeriod"] = TimePeriod;
		
		_root.UndoPosition++;
		
		//In case the undo/redo buttons should change
		ShowUndoRedoButtons();
	
	}
	


}
function UNDO_ChangeTextAlignment(LayerObj, OldAlignment, NewAlignment){

	//Find out if the layer has been moved.  They may have just clicked on the layer and let their mouse up.
	if(OldAlignment != NewAlignment){

		//So that we know before saving to recaluate coordinates.  Some preciscion is lost going back and forth.  Only do it if we know the coordinates are changing
		_root.ContentWindow.LayerNeedsCoordinatesRecaclulated(LayerObj);

		CleanUndoTopofStack();

		len = _root.UndoInfo.length;

		_root.UndoInfo[len] = new Array;
		_root.UndoInfo[len]["type"] = "ChangeTextAlignment";
		_root.UndoInfo[len]["LayerNumber"] = LayerObj.LayerNumber;
		_root.UndoInfo[len]["OldAlignment"] = OldAlignment;
		_root.UndoInfo[len]["NewAlignment"] = NewAlignment;
		
		_root.UndoPosition++;
		
		//In case the undo/redo buttons should change
		ShowUndoRedoButtons();
		
	}	

}


//If we delete a text layer and then reinsert it again... the LayerNo ID will be changed
//As a result all references to that layer number must also  be changed.
//This function will loop through the Undo/Redo Array and make the changes.
function ChangeUndoReferences(OldRef, NewRef){

	for(i=0; i<_root.UndoInfo.length; i++){
	
		if(_root.UndoInfo[i]["LayerNumber"] == OldRef){
			_root.UndoInfo[i]["LayerNumber"] = NewRef;
		}
	}

}



//Will take care of undoing a command
function UndoCommand(){

	var UndoPos = _root.UndoPosition - 1;


	if(_root.UndoInfo[UndoPos]["type"] == "RotateText"){
	
		LayerObj38 = GetLayerObjbyReference(_root.UndoInfo[UndoPos]["LayerNumber"], "text");
	
		//Update some varialbes within the Layer object so that we can calculate the RotionPoint to which the layer should spin around.
		_root.ContentWindow.CalculateTextBoxRotation(LayerObj38, 1);

		_root.SpinLayer.SpinIt(LayerObj38, _root.UndoInfo[UndoPos]["NewRotation"], _root.UndoInfo[UndoPos]["OldRotation"], _root.UndoInfo[UndoPos]["TimePeriod"]);
	
		_root.UndoPosition--;
	}

	else if(_root.UndoInfo[UndoPos]["type"] == "MoveText"){
	
		LayerObj38 = GetLayerObjbyReference(_root.UndoInfo[UndoPos]["LayerNumber"], "text");

		_root.MoveLayer.MoveIt(LayerObj38, _root.UndoInfo[UndoPos]["NewXcoord"], _root.UndoInfo[UndoPos]["NewYcoord"], _root.UndoInfo[UndoPos]["OldXcoord"], _root.UndoInfo[UndoPos]["OldYcoord"], _root.UndoInfo[UndoPos]["TimePeriod"]);

		_root.UndoPosition--;
	}
	else if(_root.UndoInfo[UndoPos]["type"] == "ChangeFont"){
	
		LayerObj38 = GetLayerObjbyReference(_root.UndoInfo[UndoPos]["LayerNumber"], "text");

		_root.ContentWindow.ChangeFont(LayerObj38, _root.UndoInfo[UndoPos]["OldFontName"]);

		_root.UndoPosition--;
	}
	else if(_root.UndoInfo[UndoPos]["type"] == "ResizeFont"){

		LayerObj38 = GetLayerObjbyReference(_root.UndoInfo[UndoPos]["LayerNumber"], "text");

		_root.ResizeFont.ResizeIt(LayerObj38, _root.UndoInfo[UndoPos]["EndTextSize"], _root.UndoInfo[UndoPos]["StartTextSize"], 5)

		_root.UndoPosition--;
	}
	else if(_root.UndoInfo[UndoPos]["type"] == "UpdateText"){
	
		LayerObj38 = GetLayerObjbyReference(_root.UndoInfo[UndoPos]["LayerNumber"], "text");

		_root.ContentWindow.UpdateText(LayerObj38, _root.UndoInfo[UndoPos]["OldTextInfo"]);

		//If there is a quick field in the HTML, it will update the text value from the Layer Object.
		_root.ContentWindow.UpdateTextinHTML(LayerObj38);

		_root.UndoPosition--;
	}
	else if(_root.UndoInfo[UndoPos]["type"] == "MoveImage"){
	
		LayerObj38 = GetLayerObjbyReference(_root.UndoInfo[UndoPos]["LayerNumber"], "graphic");

		_root.MoveLayer.MoveIt(LayerObj38, _root.UndoInfo[UndoPos]["NewXcoord"], _root.UndoInfo[UndoPos]["NewYcoord"], _root.UndoInfo[UndoPos]["OldXcoord"], _root.UndoInfo[UndoPos]["OldYcoord"], 5);

		_root.UndoPosition--;
	}
	else if(_root.UndoInfo[UndoPos]["type"] == "ResizeImage"){


		LayerObj38 = GetLayerObjbyReference(_root.UndoInfo[UndoPos]["LayerNumber"], "graphic");

		_root.ScaleLayer.ScaleIt(LayerObj38, _root.UndoInfo[UndoPos]["NewWidth"], _root.UndoInfo[UndoPos]["NewHeight"], _root.UndoInfo[UndoPos]["OldWidth"], _root.UndoInfo[UndoPos]["OldHeight"], 5);

		_root.UndoPosition--;
	}	
	else if(_root.UndoInfo[UndoPos]["type"] == "RotateImage"){
	
		LayerObj38 = GetLayerObjbyReference(_root.UndoInfo[UndoPos]["LayerNumber"], "graphic");

		_root.SpinLayer.SpinIt(LayerObj38, _root.UndoInfo[UndoPos]["NewRotation"], _root.UndoInfo[UndoPos]["OldRotation"], 5);

		_root.UndoPosition--;
	}
	else if(_root.UndoInfo[UndoPos]["type"] == "ChangeTextColor"){
	
		LayerObj38 = GetLayerObjbyReference(_root.UndoInfo[UndoPos]["LayerNumber"], "text");

		//Update the member varialbe within the Layer Ojbect.
		LayerObj38.Color = _root.UndoInfo[UndoPos]["OldColorCode"];

		//Force an update of the text color.
		_root.ContentWindow.ChangeTextColor(LayerObj38);

		_root.UndoPosition--;
	}
	else if(_root.UndoInfo[UndoPos]["type"] == "ChangeTextAlignment"){
	
		LayerObj38 = GetLayerObjbyReference(_root.UndoInfo[UndoPos]["LayerNumber"], "text");

		//Change the Text Properties of the layer.
		LayerObj38.TextAlignment = _root.UndoInfo[UndoPos]["OldAlignment"];

		//Run the function update text.  We are just passing in the current text value even though nothing in it has changed.
		_root.ContentWindow.UpdateText(LayerObj38,LayerObj38.MainTextHolder);
		
		//Make the layer fade in from 0 opacity to give a smoother look.
		_root.FadeIn.FadeInLayer(LayerObj38, 0, 100, 5);

		_root.UndoPosition--;
	}
	
	else if(_root.UndoInfo[UndoPos]["type"] == "InsertText"){
	
		LayerObj38 = GetLayerObjbyReference(_root.UndoInfo[UndoPos]["LayerNumber"], "text");

		//Get Rid of the Layer
		_root.ContentWindow.DeleteLayer(LayerObj38);
		

		_root.UndoPosition--;
	}
	else if(_root.UndoInfo[UndoPos]["type"] == "InsertImage"){
	
		LayerObj38 = GetLayerObjbyReference(_root.UndoInfo[UndoPos]["LayerNumber"], "graphic");

		//Get Rid of the Layer
		_root.ContentWindow.DeleteLayer(LayerObj38);
		

		_root.UndoPosition--;
	}
	else if(_root.UndoInfo[UndoPos]["type"] == "DeleteImage"){

		//foo will give us the length to the array.  Since arrays are 0 based we can automaticaly increase the size by one when we use is for an index.
		foo = _root.sideProperties[_root.SideSelected]["Layers"].length;

		_root.sideProperties[_root.SideSelected]["Layers"][foo] = new Array;
		_root.sideProperties[_root.SideSelected]["Layers"][foo]["type"] = "graphic";
		_root.sideProperties[_root.SideSelected]["Layers"][foo]["level"] = _root.UndoInfo[UndoPos]["level"];
		_root.sideProperties[_root.SideSelected]["Layers"][foo]["xcoordinate"] = _root.UndoInfo[UndoPos]["xcoordinate"];
		_root.sideProperties[_root.SideSelected]["Layers"][foo]["ycoordinate"] = _root.UndoInfo[UndoPos]["ycoordinate"];
		_root.sideProperties[_root.SideSelected]["Layers"][foo]["rotation"] = _root.UndoInfo[UndoPos]["rotation"];
		_root.sideProperties[_root.SideSelected]["Layers"][foo]["newgraphic"] = "no";


		_root.sideProperties[_root.SideSelected]["GraphicProperties"][foo] = new Array;
		_root.sideProperties[_root.SideSelected]["GraphicProperties"][foo]["height"] = _root.UndoInfo[UndoPos]["Height"];
		_root.sideProperties[_root.SideSelected]["GraphicProperties"][foo]["width"] = _root.UndoInfo[UndoPos]["Width"];
		_root.sideProperties[_root.SideSelected]["GraphicProperties"][foo]["originalheight"] = _root.UndoInfo[UndoPos]["OriginalHeight"];
		_root.sideProperties[_root.SideSelected]["GraphicProperties"][foo]["originalwidth"] = _root.UndoInfo[UndoPos]["OriginalWidth"];
		_root.sideProperties[_root.SideSelected]["GraphicProperties"][foo]["imageid"] = _root.UndoInfo[UndoPos]["ImageID"];
		_root.sideProperties[_root.SideSelected]["GraphicProperties"][foo]["VectorImageId"] = _root.UndoInfo[UndoPos]["VectorImageId"];
		_root.sideProperties[_root.SideSelected]["GraphicProperties"][foo]["permissions"] = _root.UndoInfo[UndoPos]["permissions"];



		//change all referenes to the new layer we are creating
		ChangeUndoReferences(_root.UndoInfo[UndoPos]["LayerNumber"], (foo + 1));

		//Add 1 becuase the Layers array is 0 based and the Layer Numbers are 1 based.
		_root.ContentWindow.MakeNewLayer(_root.SideSelected, (foo + 1));

		_root.UndoPosition--;
	}
	else if(_root.UndoInfo[UndoPos]["type"] == "DeleteText"){

		//foo will give us the length to the array.  Since arrays are 0 based we can automaticaly increase the size by one when we use is for an index.
		foo = _root.sideProperties[_root.SideSelected]["Layers"].length;
		
		_root.Layer_Text_DepthCounter++;

		_root.sideProperties[_root.SideSelected]["Layers"][foo] = new Array;
		_root.sideProperties[_root.SideSelected]["Layers"][foo]["type"] = "text";
		_root.sideProperties[_root.SideSelected]["Layers"][foo]["level"] = _root.Layer_Text_DepthCounter;
		_root.sideProperties[_root.SideSelected]["Layers"][foo]["xcoordinate"] = _root.UndoInfo[UndoPos]["xcoordinate"];
		_root.sideProperties[_root.SideSelected]["Layers"][foo]["ycoordinate"] = _root.UndoInfo[UndoPos]["ycoordinate"];
		_root.sideProperties[_root.SideSelected]["Layers"][foo]["rotation"] = _root.UndoInfo[UndoPos]["rotation"];

		_root.sideProperties[_root.SideSelected]["TextProperties"][foo] = new Array;
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["align"] = _root.UndoInfo[UndoPos]["align"];
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["color"] = _root.UndoInfo[UndoPos]["color"];
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["italics"] = _root.UndoInfo[UndoPos]["italics"];
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["bold"] = _root.UndoInfo[UndoPos]["bold"];
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["underline"] = _root.UndoInfo[UndoPos]["underline"];
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["font"] = _root.UndoInfo[UndoPos]["font"];
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["permissions"] = _root.UndoInfo[UndoPos]["permissions"];
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["field_name"] = "";
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["size"] = _root.UndoInfo[UndoPos]["size"];
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["field_order"] = _root.UndoInfo[UndoPos]["field_order"];
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["shadow_level_link"] = _root.UndoInfo[UndoPos]["shadow_level_link"];
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["shadow_distance"] = _root.UndoInfo[UndoPos]["shadow_distance"];
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["shadow_angle"] = _root.UndoInfo[UndoPos]["shadow_angle"];
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["message"] = _root.UndoInfo[UndoPos]["message"];

		//change all referenes to the new layer we are creating
		ChangeUndoReferences(_root.UndoInfo[UndoPos]["LayerNumber"], (foo + 1))

		//Add 1 becuase the Layers array is 0 based and the Layer Numbers are 1 based.
		_root.ContentWindow.MakeNewLayer(_root.SideSelected, (foo + 1));

		_root.UndoPosition--;
	}	
	else{
		trace("Undo command is undefined: " + _root.UndoInfo[UndoPos]["type"]);
	}


	//Let the buttons know that we are issuing an undo command
	_root.UndoRedoFlag = false;
	
	ShowUndoRedoButtons();

}


//Will take care of redoing a command
function RedoCommand(){

	var RedoPos = _root.UndoPosition;

	if(_root.UndoInfo[RedoPos]["type"] == "RotateText"){
	
		LayerObj38 = GetLayerObjbyReference(_root.UndoInfo[RedoPos]["LayerNumber"], "text");

		//Update some varialbes within the Layer object so that we can calculate the RotionPoint to which the layer should spin around.
		_root.ContentWindow.CalculateTextBoxRotation(LayerObj38, 1);

		_root.SpinLayer.SpinIt(LayerObj38, _root.UndoInfo[RedoPos]["OldRotation"], _root.UndoInfo[RedoPos]["NewRotation"], _root.UndoInfo[RedoPos]["TimePeriod"]);
	
		_root.UndoPosition++;
	}

	else if(_root.UndoInfo[RedoPos]["type"] == "MoveText"){

		LayerObj38 = GetLayerObjbyReference(_root.UndoInfo[RedoPos]["LayerNumber"], "text");

		_root.MoveLayer.MoveIt(LayerObj38, _root.UndoInfo[RedoPos]["OldXcoord"], _root.UndoInfo[RedoPos]["OldYcoord"], _root.UndoInfo[RedoPos]["NewXcoord"], _root.UndoInfo[RedoPos]["NewYcoord"], _root.UndoInfo[RedoPos]["TimePeriod"])

		_root.UndoPosition++;
	}
	else if(_root.UndoInfo[RedoPos]["type"] == "ChangeFont"){
	
		LayerObj38 = GetLayerObjbyReference(_root.UndoInfo[RedoPos]["LayerNumber"], "text");

		_root.ContentWindow.ChangeFont(LayerObj38, _root.UndoInfo[RedoPos]["NewFontName"]);

		_root.UndoPosition++;
	}
	else if(_root.UndoInfo[RedoPos]["type"] == "ResizeFont"){

		LayerObj38 = GetLayerObjbyReference(_root.UndoInfo[RedoPos]["LayerNumber"], "text");

		_root.ResizeFont.ResizeIt(LayerObj38, _root.UndoInfo[RedoPos]["StartTextSize"], _root.UndoInfo[RedoPos]["EndTextSize"], 5)

		_root.UndoPosition++;
	}
	else if(_root.UndoInfo[RedoPos]["type"] == "UpdateText"){
	
		LayerObj38 = GetLayerObjbyReference(_root.UndoInfo[RedoPos]["LayerNumber"], "text");

		_root.ContentWindow.UpdateText(LayerObj38, _root.UndoInfo[RedoPos]["NewTextInfo"]);
		
		//If there is a quick field in the HTML, it will update the text value from the Layer Object.
		_root.ContentWindow.UpdateTextinHTML(LayerObj38);

		_root.UndoPosition++;
	}
	else if(_root.UndoInfo[RedoPos]["type"] == "MoveImage"){
	
		LayerObj38 = GetLayerObjbyReference(_root.UndoInfo[RedoPos]["LayerNumber"], "graphic");

		_root.MoveLayer.MoveIt(LayerObj38, _root.UndoInfo[RedoPos]["OldXcoord"], _root.UndoInfo[RedoPos]["OldYcoord"], _root.UndoInfo[RedoPos]["NewXcoord"], _root.UndoInfo[RedoPos]["NewYcoord"], 5)

		_root.UndoPosition++;
	}
	else if(_root.UndoInfo[RedoPos]["type"] == "ResizeImage"){
	
	
		LayerObj38 = GetLayerObjbyReference(_root.UndoInfo[RedoPos]["LayerNumber"], "graphic");

		_root.ScaleLayer.ScaleIt(LayerObj38, _root.UndoInfo[RedoPos]["OldWidth"], _root.UndoInfo[RedoPos]["OldHeight"], _root.UndoInfo[RedoPos]["NewWidth"], _root.UndoInfo[RedoPos]["NewHeight"], 5);

		_root.UndoPosition++;
	
	}
	else if(_root.UndoInfo[RedoPos]["type"] == "RotateImage"){
	
		LayerObj38 = GetLayerObjbyReference(_root.UndoInfo[RedoPos]["LayerNumber"], "graphic");

		_root.SpinLayer.SpinIt(LayerObj38, _root.UndoInfo[RedoPos]["OldRotation"], _root.UndoInfo[RedoPos]["NewRotation"], 5);

		_root.UndoPosition++;
	}
	else if(_root.UndoInfo[RedoPos]["type"] == "ChangeTextColor"){
	
		LayerObj38 = GetLayerObjbyReference(_root.UndoInfo[RedoPos]["LayerNumber"], "text");

		//Update the member varialbe within the Layer Ojbect.
		LayerObj38.Color = _root.UndoInfo[RedoPos]["NewColorCode"];

		//Force an update of the text color.
		_root.ContentWindow.ChangeTextColor(LayerObj38);

		_root.UndoPosition++;
	}
	else if(_root.UndoInfo[RedoPos]["type"] == "ChangeTextAlignment"){
	
		LayerObj38 = GetLayerObjbyReference(_root.UndoInfo[RedoPos]["LayerNumber"], "text");

		//Change the Text Properties of the layer.
		LayerObj38.TextAlignment = _root.UndoInfo[RedoPos]["NewAlignment"];

		//Run the function update text.  We are just passing in the current text value even though nothing in it has changed.
		_root.ContentWindow.UpdateText(LayerObj38,LayerObj38.MainTextHolder);

		//Make the layer fade in from 0 opacity to give a smoother look.
		_root.FadeIn.FadeInLayer(LayerObj38, 0, 100, 5);

		_root.UndoPosition++;
	}
	else if(_root.UndoInfo[RedoPos]["type"] == "DeleteText"){
	
		LayerObj38 = GetLayerObjbyReference(_root.UndoInfo[RedoPos]["LayerNumber"], "text");

		//Get Rid of the Layer
		_root.ContentWindow.DeleteLayer(LayerObj38);
		

		_root.UndoPosition++;
	}
	else if(_root.UndoInfo[RedoPos]["type"] == "DeleteImage"){
	
		LayerObj38 = GetLayerObjbyReference(_root.UndoInfo[RedoPos]["LayerNumber"], "graphic");

		//Get Rid of the Layer
		_root.ContentWindow.DeleteLayer(LayerObj38);
		

		_root.UndoPosition++;
	}	
	else if(_root.UndoInfo[RedoPos]["type"] == "InsertText"){
	
		//foo will give us the length to the array.  Since arrays are 0 based we can automaticaly increase the size by one when we use is for an index.
		foo = _root.sideProperties[_root.SideSelected]["Layers"].length;
		
		_root.Layer_Text_DepthCounter++;

		_root.sideProperties[_root.SideSelected]["Layers"][foo] = new Array;
		_root.sideProperties[_root.SideSelected]["Layers"][foo]["type"] = "text";
		_root.sideProperties[_root.SideSelected]["Layers"][foo]["level"] = _root.Layer_Text_DepthCounter;
		_root.sideProperties[_root.SideSelected]["Layers"][foo]["xcoordinate"] = _root.UndoInfo[RedoPos]["xcoordinate"];
		_root.sideProperties[_root.SideSelected]["Layers"][foo]["ycoordinate"] = _root.UndoInfo[RedoPos]["ycoordinate"];
		_root.sideProperties[_root.SideSelected]["Layers"][foo]["rotation"] = _root.UndoInfo[RedoPos]["rotation"];


		_root.sideProperties[_root.SideSelected]["TextProperties"][foo] = new Array;
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["align"] = _root.UndoInfo[RedoPos]["align"];
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["color"] = _root.UndoInfo[RedoPos]["color"];
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["italics"] = _root.UndoInfo[RedoPos]["italics"];
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["bold"] = _root.UndoInfo[RedoPos]["bold"];
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["underline"] = _root.UndoInfo[RedoPos]["underline"];
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["font"] = _root.UndoInfo[RedoPos]["font"];
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["permissions"] = "";
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["field_name"] = "";
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["size"] = _root.UndoInfo[RedoPos]["size"];
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["field_order"] = _root.UndoInfo[RedoPos]["field_order"];
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["shadow_level_link"] = _root.UndoInfo[RedoPos]["shadow_level_link"];
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["shadow_distance"] = _root.UndoInfo[RedoPos]["shadow_distance"];
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["shadow_angle"] = _root.UndoInfo[RedoPos]["shadow_angle"];
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["message"] = _root.UndoInfo[RedoPos]["message"];


		//change all referenes to the new layer we are creating
		ChangeUndoReferences(_root.UndoInfo[RedoPos]["LayerNumber"], (foo + 1))

		//Add 1 becuase the Layers array is 0 based and the Layer Numbers are 1 based.
		_root.ContentWindow.MakeNewLayer(_root.SideSelected, (foo + 1));

		_root.UndoPosition++;
		
	}
	else if(_root.UndoInfo[RedoPos]["type"] == "InsertImage"){

		//foo will give us the length to the array.  Since arrays are 0 based we can automaticaly increase the size by one when we use is for an index.
		foo = _root.sideProperties[_root.SideSelected]["Layers"].length;
		

		_root.sideProperties[_root.SideSelected]["Layers"][foo] = new Array;
		_root.sideProperties[_root.SideSelected]["Layers"][foo]["type"] = "graphic";
		_root.sideProperties[_root.SideSelected]["Layers"][foo]["level"] = _root.UndoInfo[RedoPos]["level"];
		_root.sideProperties[_root.SideSelected]["Layers"][foo]["xcoordinate"] = _root.UndoInfo[RedoPos]["xcoordinate"];
		_root.sideProperties[_root.SideSelected]["Layers"][foo]["ycoordinate"] = _root.UndoInfo[RedoPos]["ycoordinate"];
		_root.sideProperties[_root.SideSelected]["Layers"][foo]["rotation"] = _root.UndoInfo[RedoPos]["rotation"];
		_root.sideProperties[_root.SideSelected]["Layers"][foo]["newgraphic"] = "no";


		_root.sideProperties[_root.SideSelected]["GraphicProperties"][foo] = new Array;
		_root.sideProperties[_root.SideSelected]["GraphicProperties"][foo]["height"] = _root.UndoInfo[RedoPos]["Height"];
		_root.sideProperties[_root.SideSelected]["GraphicProperties"][foo]["width"] = _root.UndoInfo[RedoPos]["Width"];
		_root.sideProperties[_root.SideSelected]["GraphicProperties"][foo]["originalheight"] = _root.UndoInfo[RedoPos]["OriginalHeight"];
		_root.sideProperties[_root.SideSelected]["GraphicProperties"][foo]["originalwidth"] = _root.UndoInfo[RedoPos]["OriginalWidth"];
		_root.sideProperties[_root.SideSelected]["GraphicProperties"][foo]["imageid"] = _root.UndoInfo[RedoPos]["ImageID"];
		_root.sideProperties[_root.SideSelected]["GraphicProperties"][foo]["VectorImageId"] = _root.UndoInfo[RedoPos]["VectorImageId"];
		_root.sideProperties[_root.SideSelected]["GraphicProperties"][foo]["permissions"] = "";



		//change all referenes to the new layer we are creating
		ChangeUndoReferences(_root.UndoInfo[RedoPos]["LayerNumber"], (foo + 1));

		//Add 1 becuase the Layers array is 0 based and the Layer Numbers are 1 based.
		_root.ContentWindow.MakeNewLayer(_root.SideSelected, (foo + 1));

		_root.UndoPosition++;
	}
	else{
		trace("Redo command is undefined: " + _root.UndoInfo[RedoPos]["type"]);
	}

	//Let the buttons know that we are issuing a redo command
	_root.UndoRedoFlag = true;
	
	ShowUndoRedoButtons();

}



//Will Give us a layer object ... based off of a Layer Number
function GetLayerObjbyReference(LayerNumber, TheLayerType){

	if(TheLayerType == "text"){
		return eval("_root.ContentWindow.TextClipLayer" + LayerNumber);
	}
	else if(TheLayerType == "graphic"){
		return eval("_root.ContentWindow.GraphicClipLayer" + LayerNumber);
	}
	else{
		trace("Invalid parameter in the function call GetLayerObjbyReference");
	}
}

//Will Give us a Highlight Button object ... based off of a Layer Number
function GetHighlightButtonObjbyReference(LayerObj66){

	return eval("_root.ContentWindow.HighLightLayer" + LayerObj66.LayerNumber);

}


//This function should be called before a new undo command is recorded.
//Let's say that a user his the Undo Button twice and then rotates the text.  
//We need to pop those 2 records off of the stack since we are starting a new path in history
function CleanUndoTopofStack(){

	StackLen = _root.UndoInfo.length;

	//If our position is smaller than the size of the array then erase everything after our current position
	if(_root.UndoPosition < StackLen){
	
		for(i = _root.UndoPosition; i < StackLen; i++){		
			_root.UndoInfo.pop();
		}
	}


}

//We may need to call this upon an error, or when we switch sides, or when we saving... without reloading the page etc.
function ClearUndoHistory(){
	
	_root.UndoInfo = new Array;
	
	_root.UndoPosition = 0;
	
	 ShowUndoRedoButtons();
	
	
}


//This will make the Undo Buttons Fade In or fade out... depending up the UndoInfo array and the current UndoPosition
function ShowUndoRedoButtons(){

	if(_root.UndoPosition == 0 && _root.UndoInfo.length == 0){
	
		//there is no undo information so make sure there are no buttons.
		_root.UndoRedo.gotoAndStop("1");
		
		//Since there is no undo/redo information then make sure the first time flag is set to true.
		//This shouldn't really be needed... but in case of an error we may wipe out the arrays.. and this way the undo button can reset itself.
		_root.UndoFirstTimeFlag = true;
	
	}	
	else if(_root.UndoPosition == 1 && _root.UndoInfo.length == 1 && _root.UndoFirstTimeFlag){
	
		//Then it means that this is our first Undo Movement...
		//So let the Undo Button fade in
		_root.UndoRedo.gotoAndPlay("U-in");
		
		//Make sure the "undo" button won't fade in again.
		_root.UndoFirstTimeFlag = false;
		
		//If we are in proofing mode then we want to let the page know there are unsaved changes 
		if(_root.UserType == "proof"){
			fscommand("MarkUnsavedChanges", "");
		}
	
	}
	else if(_root.UndoPosition < _root.UndoInfo.length && _root.UndoPosition == 0){
	
		//Then it means that we have "undone" as much as possible... but redo commands are still recorded
		//So let the Undo Button fade out while the redo button remains
		_root.UndoRedo.gotoAndPlay("R-U-out");
		
		//If we are in proofing mode then we want to let the page know that all changes are still saved 
		if(_root.UserType == "proof"){
			fscommand("MarkChangesSaved", "");
		}
	
	}
	else if(_root.UndoPosition == 1 && _root.UndoRedoFlag && _root.UndoInfo.length != 1){
	
		//Then it means that the user was 1 Redo away from the begining of the history
		//So let the Undo Button fade in while the Redo buttons remain
		_root.UndoRedo.gotoAndPlay("R-U-in");


		//If we are in proofing mode then we want to let the page know there are unsaved changes 
		if(_root.UserType == "proof"){
			fscommand("MarkUnsavedChanges", "");
		}

	}
	else if(_root.UndoPosition == (_root.UndoInfo.length - 1) && !_root.UndoRedoFlag){
	
		//Then it means that we just "undid" 1 record
		//So let the Redo Button fade in
		_root.UndoRedo.gotoAndPlay("U-R-in");
	}
	else if(_root.UndoPosition == _root.UndoInfo.length && _root.UndoRedoFlag){
	
		//Then it means that the user was cyclying through the redo comands and hit the end of the road.
		//So let the Redo Button fade out while the Undo buttons remain
		_root.UndoRedo.gotoAndPlay("U-R-out");
		
		//If we are in proofing mode then we want to let the page know there are unsaved changes 
		if(_root.UserType == "proof"){
			fscommand("MarkUnsavedChanges", "");
		}
	}
	else if(_root.UndoPosition == _root.UndoInfo.length){
	
		//If it got to this point within the if/else clause and the position matches the size of the undo array..
		//So let the Undo button be the only one that shows.
		_root.UndoRedo.gotoAndStop("U-only");
		
		//If we are in proofing mode then we want to let the page know there are unsaved changes 
		if(_root.UserType == "proof"){
			fscommand("MarkUnsavedChanges", "");
		}
	}

	
	//By Default let's keep the flag set to false... only when redo command is issued we want to handle it above and then forget about it.
	_root.UndoRedoFlag = false;
	
}
//Depending on the rotation of the layer, etc, and 
//This is generally called right as the user presses on the black buttons to resize the image
function SetHeightWidthParametersForUndoImageResize(LayerObj68){

	//If the graphic is rotated by 90 degrees then the heigh/width parameters should be reversed
	if(LayerObj68._rotation == 0 || LayerObj68._rotation == 180 || LayerObj68._rotation == -180){

		LayerObj68.UNDO_Width = LayerObj68.imageclip._width;
		LayerObj68.UNDO_Height = LayerObj68.imageclip._height;
	}
	else{
		LayerObj68.UNDO_Width = LayerObj68.imageclip._width;
		LayerObj68.UNDO_Height = LayerObj68.imageclip._height;
	}


}

