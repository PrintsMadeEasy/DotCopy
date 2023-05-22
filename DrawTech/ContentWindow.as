onClipEvent (load) {
	
	//Update a global variable that tells out how big the boxes should be surrounding a highlighted layer
	//It changes depeding on the zoom factor.
	function CalculateBlackBoxSize(){
		_root.BlackBoxSize = 6 / (_root.CurrentZoom / 100);
	}

	//This is where we position the little black boxes around the container clip that we just resized
	function SetBoxes(HighlightBoxesObj7){

		CalculateBlackBoxSize();

		//Resize the boxes
		HighlightBoxesObj7.ScaleUpLeft._width = _root.BlackBoxSize;
		HighlightBoxesObj7.ScaleUpLeft._height = _root.BlackBoxSize;
		HighlightBoxesObj7.ScaleUpRight._width = _root.BlackBoxSize;
		HighlightBoxesObj7.ScaleUpRight._height = _root.BlackBoxSize;
		HighlightBoxesObj7.ScaleDownRight._width = _root.BlackBoxSize;
		HighlightBoxesObj7.ScaleDownRight._height = _root.BlackBoxSize;
		HighlightBoxesObj7.ScaleDownLeft._width = _root.BlackBoxSize;
		HighlightBoxesObj7.ScaleDownLeft._height = _root.BlackBoxSize;
		HighlightBoxesObj7.ScaleDown._width = _root.BlackBoxSize;
		HighlightBoxesObj7.ScaleDown._height = _root.BlackBoxSize;
		HighlightBoxesObj7.ScaleUp._width = _root.BlackBoxSize;
		HighlightBoxesObj7.ScaleUp._height = _root.BlackBoxSize;
		HighlightBoxesObj7.ScaleRight._width = _root.BlackBoxSize;
		HighlightBoxesObj7.ScaleRight._height = _root.BlackBoxSize;
		HighlightBoxesObj7.ScaleLeft._width = _root.BlackBoxSize;
		HighlightBoxesObj7.ScaleLeft._height = _root.BlackBoxSize;

		//Make sure to subtract the offset of the width of the box.  If the boxes fall outside of the movie clip then the overall size gets increased which really screws things up!!

		HighlightBoxesObj7.ScaleUpLeft._x = -(HighlightBoxesObj7.HighLightContainer._width/2) + _root.BlackBoxSize;
		HighlightBoxesObj7.ScaleUpLeft._y = -(HighlightBoxesObj7.HighLightContainer._height/2) + _root.BlackBoxSize;

		HighlightBoxesObj7.ScaleUpRight._x = (HighlightBoxesObj7.HighLightContainer._width/2) - _root.BlackBoxSize;
		HighlightBoxesObj7.ScaleUpRight._y = -(HighlightBoxesObj7.HighLightContainer._height/2) + _root.BlackBoxSize;

		HighlightBoxesObj7.ScaleDownRight._x = (HighlightBoxesObj7.HighLightContainer._width/2) - _root.BlackBoxSize;
		HighlightBoxesObj7.ScaleDownRight._y = (HighlightBoxesObj7.HighLightContainer._height/2) - _root.BlackBoxSize;

		HighlightBoxesObj7.ScaleDownLeft._x = -(HighlightBoxesObj7.HighLightContainer._width/2) + _root.BlackBoxSize;
		HighlightBoxesObj7.ScaleDownLeft._y = (HighlightBoxesObj7.HighLightContainer._height/2) - _root.BlackBoxSize;

		HighlightBoxesObj7.ScaleDown._x = 0;
		HighlightBoxesObj7.ScaleDown._y = (HighlightBoxesObj7.HighLightContainer._height/2) - _root.BlackBoxSize;

		HighlightBoxesObj7.ScaleUp._x = 0;
		HighlightBoxesObj7.ScaleUp._y = -(HighlightBoxesObj7.HighLightContainer._height/2) + _root.BlackBoxSize;

		HighlightBoxesObj7.ScaleRight._x = (HighlightBoxesObj7.HighLightContainer._width/2) - _root.BlackBoxSize;
		HighlightBoxesObj7.ScaleRight._y = 0;

		HighlightBoxesObj7.ScaleLeft._x = -(HighlightBoxesObj7.HighLightContainer._width/2) + _root.BlackBoxSize;
		HighlightBoxesObj7.ScaleLeft._y = 0;

	}

	//This function finds out how much the "Black Boxes" have resized and then adjusts the Image layer to match	
	function ResizeGraphic(HighlightBoxesObj8){


		//If the graphic is rotated by 90 degrees then the heigh/width parameters should be reversed
		if(LayerSelectedObj._rotation == 0 || LayerSelectedObj._rotation == 180 || LayerSelectedObj._rotation == -180){

			//Thre Graphic clip should be the same size as the Highlight clip minus one "black box" length on each side.
			//IMPORTANT --- ********** If this calculation is ever changed then you also need to modify the reverse calculation seen in the function "SetHighlightContainer"
			LayerSelectedObj.ImageClip._width = HighlightBoxesObj8.HighLightContainer._width - 2 * _root.BlackBoxSize;
			LayerSelectedObj.ImageClip._height = HighlightBoxesObj8.HighLightContainer._height - 2 * _root.BlackBoxSize;
		}
		else{
			LayerSelectedObj.ImageClip._width = HighlightBoxesObj8.HighLightContainer._height - 2 * _root.BlackBoxSize;
			LayerSelectedObj.ImageClip._height = HighlightBoxesObj8.HighLightContainer._width - 2 * _root.BlackBoxSize;
		}


		//Make sure the button exactly covers the image that was just downloaded. 
		//This is the same calculation seen in Initialize graphic function.
		LayerSelectedObj.HightLightButton._width = LayerSelectedObj.ImageClip._width; 
		LayerSelectedObj.HightLightButton._height = LayerSelectedObj.ImageClip._height;

		//Make sure that as we resize the graphic that it is continuously moved to the center of the layer.
		SetImageInCenterofLayer(LayerSelectedObj);
		
		
	}

	//The Image has a calculation spot of 0,0 at the UpperLeft cornver.  I can't change this becuase it is the method in which the server dynamically generates the images.
	//This function should be called everytime we re-size or initialize the graphic to make the appropriate calculations so that the image is always centered within the layer.
	function SetImageInCenterofLayer(LayerObj33){
		LayerObj33.ImageClip._x = -(LayerObj33.ImageClip._width/2);
		LayerObj33.ImageClip._y = -(LayerObj33.ImageClip._height/2);
	}

	//We need to resize the container Graphic within the black boxes so the black boxes have something to "hug" around.  We want the container dimensions to match the Image Layer.
	function SetHighlightContainer(HighlightBoxesObj2, GraphicObj5){

		//Update the global varialbe _root.BlackBoxSize 
		CalculateBlackBoxSize();

		//The calculation here is making sure that the Black boxes fall outside of the graphic by "1 box" length on each side.  
		//- **************** If this calculation is ever changed it needs to match the 'reverse' calculation in the function named "ResizeGraphic"
		HighlightBoxesObj2.HighLightContainer._width = 2 * _root.BlackBoxSize + GraphicObj5._width;  
		HighlightBoxesObj2.HighLightContainer._height = 2 * _root.BlackBoxSize + GraphicObj5._height;

		//Make the black boxes hug around the container that we just resized.
		SetBoxes(HighlightBoxesObj2);
	}


	//Returns true or false depending on wether the font is able to be italicised.
	function CheckIfFontHasItalics(FontName){
	
		var SearchArr = new Array("Eurasia", "Vibroce", "Commercial", "Optima", "Palatino", "Times", "Lydian", "Vectora");
	
		for(var i=0; i < SearchArr.length; i++ ){
			if(SearchArr[i] == FontName)
				return true;
		}
		return false;
	}
	function CheckIfFontHasBold(FontName){

		var SearchArr = new Array("Eurasia", "Vibroce", "Commercial", "Optima", "Palatino", "Present", "Snell", "Times", "Lydian", "Vectora");

		for(var i=0; i < SearchArr.length; i++ ){
			if(SearchArr[i] == FontName)
				return true;
		}
		return false;

	}
	function CheckIfFontHasBoldAndItalics(FontName){

		var SearchArr = new Array("Vibroce", "Commercial", "Optima", "Palatino", "Times", "Lydian", "Vectora");


		for(var i=0; i < SearchArr.length; i++ ){
			if(SearchArr[i] == FontName)
				return true;
		}
		return false;
	}
	
	//Call this before changing the atrributes of a font.
	//Pass in the attribute that you are trying to change the font to.  Returns True is possible... False if not
	//On top of returning false it will also display an error message
	function CheckIfFontAttributeIsPossible(NewAttribute){

		//Put the Current Text attributes of the layer into booleans
		if(LayerSelectedObj.TextItalic == "yes"){
			var TxtItalic = true;
		}
		else{
			var TxtItalic = false;
		}
		if(LayerSelectedObj.TextBold == "yes"){
			var TxtBold = true;
		}
		else{
			var TxtBold = false;
		}


		//Now check if what we are trying to change to is possible
		if(NewAttribute == "italics"){
		
			if(!CheckIfFontHasItalics(LayerSelectedObj.TextFont)){
				_root.TextButtons.FontAttributeWarning.ShowMessage("Sorry, this font does not have italic capabilities.");
				return false;
			}
			
			if(TxtBold && !CheckIfFontHasBoldAndItalics(LayerSelectedObj.TextFont)){
				_root.TextButtons.FontAttributeWarning.ShowMessage("This font can't have bold and italics turned on at the same time.");
				return false;
			}
			
		}
		else if(NewAttribute == "bold"){
		
			if(!CheckIfFontHasBold(LayerSelectedObj.TextFont)){
				_root.TextButtons.FontAttributeWarning.ShowMessage("Sorry, this font does not have bold capabilities.");
				return false;
			}
			
			if(TxtItalic && !CheckIfFontHasBoldAndItalics(LayerSelectedObj.TextFont)){
				_root.TextButtons.FontAttributeWarning.ShowMessage("This font can't have bold and italics turned on at the same time.");
				return false;
			}
		}
		else{
			trace("Error to function call CheckIfFontAttributeIsPossible");
		}
		
		return true;

	}
	


	//This function will store information into the member variables of this layer object using trigonmetric functions.
	//Pass in the x and y coordinates separately.  We don't want to use the coordinates built into the layer ojbect becuase some other positioning functions are using this function to base their calculations for a new x/y coordinate...  Weird things start to happen becuase of minor conflicts.
	//The parameter CopyNumber is used to write to different copies of rotation point... This is becuase some functions such as "PositionTextHighlight" are using this function on every frame... while others just want it set once... Example...  Click on the Rotate Button  Slider and hold.... small rounding of decimal errors can cause funny quirks... but very very slight.
	function CalculateTextBoxRotation(LayerObj21, CopyNumber){

		//The radius for the Circular path in which the Text Layer follows.
		//This is figured out by Pythagirus Therom a^2 + b^2 = c^2
		//Since the layer will travel around the circle encompaing the Highlight Button.  The radius can be the distance between the center of the highlight button and the Upper Left Hand corner of the TextBox
		//The Distance to the left edge will change depending  on the alignment.  The distance between the center point and the top is always the same.
		if(LayerObj21.TextAlignment == "Left"){
			HorizontalDistance = LayerObj21.HighLightButton._width/2;
		}
		else if(LayerObj21.TextAlignment == "Right"){
			HorizontalDistance = LayerObj21.TextBox._width - LayerObj21.HighLightButton._width/2;
		}
		else if(LayerObj21.TextAlignment == "Center"){
			HorizontalDistance = LayerObj21.TextBox._width/2;
		}

		LayerObj21.ThisRadius = Math.sqrt(Math.pow((HorizontalDistance),2) + Math.pow((LayerObj21.HighLightButton._height/2),2));


		//_root.LayerStartAngle will hold the angle of the line between the center of the highlight button and the upper-left hand corner of the Text Layer. 
		//We figure this angle out by taking the inverse tangent.  The formula is derived from ....   Tan(theta) = Opposite/Adjacent.  Don' forget to convert to degrees.  
		//We can figure out the Legs of the Triangle becuase The Highlight button has its registration point in the top-left-hand corner.  Add 1/2 of the width and height to find the size of the legs.
		LayerObj21.LayerStartAngle = Math.atan((LayerObj21.HighLightButton._y + LayerObj21.HighLightButton._height/2)/(LayerObj21.HighLightButton._x + LayerObj21.HighLightButton._width/2))/ (Math.PI/180);


		//These coordinates will hold the position of the Center of the Highlight Button RELATIVE to the Content Window.
		//In order to get the coordinates RELATIVE to the Content window we have to figure out where the Layer is currently located.  
		//Then add the Layer's Rotation angle to _root.LayerStartAngle in order to figure out what angle the Radius is pointing.
		//using the Radius, angle of the radius, and Layer start position we can figure out where the Center Pivot point is on the Content window.
		
		tempRotatePoint_x = LayerObj21._x + LayerObj21.ThisRadius * Math.cos((LayerObj21._rotation + LayerObj21.LayerStartAngle) * Math.PI/180);
		tempRotatePoint_y = LayerObj21._y + LayerObj21.ThisRadius * Math.sin((LayerObj21._rotation + LayerObj21.LayerStartAngle) * Math.PI/180);


		if(CopyNumber == 1){
			LayerObj21.RotationPoint_x = tempRotatePoint_x;
			LayerObj21.RotationPoint_y = tempRotatePoint_y;
		}
		else if(CopyNumber == 2){
			LayerObj21.RotationPoint_copy2_x = tempRotatePoint_x;
			LayerObj21.RotationPoint_copy2_y = tempRotatePoint_y;

		}
		else{
			trace("Invalid CopyNumber parameter in function CalculateTextBoxRotation")
		}

	}

	//This function will rotate whatever layer is currently selected.  It takes a value between 0 and 359.
	function RotateLayer(RotateDegree, RotateLayerObj){


		if(RotateLayerObj.LayerType == "graphic"){

			HighlightBoxesObj31 = _root.GetHighlightButtonObjbyReference(RotateLayerObj);

			//In case the user will resize the graphic this will make sure that the layer is perfectly flat.  Remember that roation also has decimal places.
			if(int(RotateDegree) == 0){
				RotateDegree = 0;
			}

			RotateLayerObj._rotation = RotateDegree;

			//This little piece of code will make sure that the only time someone can "resize" a graphic is when the graphic is level.  I couldn't figure out how to scale the graphic once it has been rotated.  I tried pretty hard and I don't think there is a good solution.
			if(int(RotateDegree) != 0 && int(RotateDegree) != -180 && int(RotateDegree) != 180 && int(RotateDegree) != 90 && int(RotateDegree) != -90){

				HighlightBoxesObj31.gotoAndStop(2);
			}
			else {
				HighlightBoxesObj31.gotoAndStop(1);
			}


			//make sure the black boxes resize every time we make a rotation change.
			SetHighlightContainer(HighlightBoxesObj31, RotateLayerObj);
		}

		else if(RotateLayerObj.LayerType == "text"){

			//This might seem a little confusing.  But I am just making a correction here.  Since we want the angle twoards the Upper-left hand corner and the angle is naturally pointing in the direction of "Quadrant 1"  .. I just add 180 degrees to get it to point into quadrent 4.
			//After I get the angle pointing twoards Quadrant 4 .. Just add the Rotation (in degrees) so that we know where to start the Layer path.
			PositioningAngle = RotateLayerObj.LayerStartAngle + 180 + RotateDegree;

			//We need to place the Layer into a new spot since the text box is HUGE and as we rotate the Text we want it to appear as if the given Text is the entire size of the layer.
			//We can figure out where to put it becuase we know the radius of the Path in which this layer will travel.  We also know the angle of the radius line.
			//Using the formula ...    y = r sin(theta)  ..  We can figure out an x,y position of a circle relative to the rotation point.    Don't forget to convert to Degrees.  
			//Then we just add the coordinates of the path on the Circle to the rotation point to get the Coordinates on the Content window.  That is where we place the layer. 
			RotateLayerObj._y = RotateLayerObj.ThisRadius * Math.sin(Math.PI/180 * PositioningAngle) + RotateLayerObj.RotationPoint_y;
			RotateLayerObj._x = RotateLayerObj.ThisRadius * Math.cos(Math.PI/180 * PositioningAngle) + RotateLayerObj.RotationPoint_x;


			//We have shifted the layer so now we can rotate it and it won't appear like the layer acutally moved.
			RotateLayerObj._rotation = RotateDegree;

			//make sure the black boxes resize every time we make a rotation change.
			PositionText_Highlight(RotateLayerObj);
			
			if(CheckIfTextLayerHasShadow(RotateLayerObj))
				RepositionShadowLayerAccordingToParent(RotateLayerObj);
		}


	}

	//This function is run from DragScript on the onClipEvent(enterframe) function
	//The purpose is to get Black Boxes (or text shadow) to follow the clip of whatever we are dragging.
	//The black boxes are separate movie clips from the Object we are dragging .. that is why this function is needed.
	function DragObject(LayerObj44){

		HighlightBoxesObj55 = _root.GetHighlightButtonObjbyReference(LayerObj44);
		
		//We have to do different calculations to get the black boxes to follow depending on wheter it is a graphic or text.
		if(LayerObj44.LayerType == "graphic"){

			HighlightBoxesObj55._x = LayerObj44._x;
			HighlightBoxesObj55._y = LayerObj44._y;

		}

		else if(LayerObj44.LayerType == "text"){

			//Stores information into the member variable of this object... 
			//Where the pivot point should be for rotation and also where the highlight button should be placed.
			_root.ContentWindow.CalculateTextBoxRotation(LayerObj44, 1);

			HighlightBoxesObj55._y = LayerObj44.RotationPoint_y;
			HighlightBoxesObj55._x = LayerObj44.RotationPoint_x;
			
			if(CheckIfTextLayerHasShadow(LayerObj44))
				RepositionShadowLayerAccordingToParent(LayerObj44);
			

		}
		else{
			trace("Invalid layer type in the function call DragObject")
		}

	}

	//This function will positions the Button and boxes around the given text Layer.. height,width,_y,_x
	//I am giving this Layer Object a different name ie. "LayerObj2" becuase I have already defined a LayerObj and I am not sure how Flash treats global variables vs. function parameters
	function PositionText_Highlight(LayerObj2){


		//We need to figure out the target for the Boxes that surround this text clip so that we can use it through the rest of this function and pass it off to another function called "SetBoxes"		
		ThisTextBoxesObj = _root.GetHighlightButtonObjbyReference(LayerObj2);

		//Position the X coordinates of the Highlight button depending on the alignment.
		if(LayerObj2.TextAlignment == "Left"){
			LayerObj2.HighLightButton._x = 0;
		}
		else if(LayerObj2.TextAlignment == "Right"){
			LayerObj2.HighLightButton._x = LayerObj2.TextBox._width - LayerObj2.MaxLineSize;
		}
		else if(LayerObj2.TextAlignment == "Center"){
			LayerObj2.HighLightButton._x = LayerObj2.TextBox._width/2 - LayerObj2.MaxLineSize/2;
		}


		//The size of the button should be based upon the max character length of the longest line in between line breaks X the pixel size of text.
		LayerObj2.HighLightButton._width = LayerObj2.MaxLineSize;
		LayerObj2.HighLightButton._height = LayerObj2.LineHeight * LayerObj2.TextSize * LayerObj2.SpacerPixels; 


		//Set the rotation of the highlight container back to 0 for a split second so that we can correctly resize the container to match the Highlight Button.
		ThisTextBoxesObj.HighLightContainer._rotation=0;

		//Make sure that the container is the same size as the button + a 10 pixel margin		
		ThisTextBoxesObj.HighLightContainer._width = LayerObj2.HighLightButton._width + 10;
		ThisTextBoxesObj.HighLightContainer._height = LayerObj2.HighLightButton._height + 10;

		//Change the rotation of the highlight container to match the rotation of the text layer.  The little black boxes will fall around the outside edges of the  conatiner AFTER it has been rotated.
		ThisTextBoxesObj.HighLightContainer._rotation= LayerObj2._rotation;


		//This function call will figure out the rotation point of the Hightlight Button so that we can position the boxes in the same place.
		//The function stores the coordinates in the Layer Objects's Member variables.
		CalculateTextBoxRotation(LayerObj2, 2);

		//Put the Highlight container in the same position as the Rotation point of the text layer. 
		//Notice we are using another copy of the rotation point...  They were set by calling the function CalculateTextBoxRotation above with the 2nd parameter
		ThisTextBoxesObj._y = LayerObj2.RotationPoint_copy2_y;
		ThisTextBoxesObj._x = LayerObj2.RotationPoint_copy2_x;


		//Make the Black boxes hug the Container.
		SetBoxes(ThisTextBoxesObj);
		

	}

	function ChangeTextColor(LayerObj30){
	
		//Define a color Object
		TextBoxColorObj = new Color(LayerObj30.TextBox);

		//If we are dealing with unlimited colors then the Color Member variable is a color Hex Code
		//Otherwise the Color Member variable is a color ID   ex.. (1-3).  Then we look up the hex code associated with the color ID.
		if(_root.NumberOfColors == 0){
			TextBoxColorObj.setRGB(LayerObj30.Color);
		}
		else{
			layerColorID = LayerObj30.Color;
			colorIDfound = false;
			lastColorID = 0;
	
			//Make sure that the Color ID exists in our Definitions Array.
			//If it does not exist, then change the color ID of the layer to a default.
			for(i=1; i <= _root.NumberOfColors; i++){

				thisColorID = _root.sideProperties[_root.SideSelected]["color_definitions"][i]["id"];
				
				lastColorID = thisColorID;

				if(thisColorID == layerColorID){
					colorIDfound = true;
					break;
				}

			}
			
			if(!colorIDfound){
				LayerObj30.Color = lastColorID;
			}
			
			//The color hexcode is stored in the 2D array that we created when initializing this side.
			TextBoxColorObj.setRGB(_root.ColorDefinitions[LayerObj30.Color]["hexcode"]);
		}
	}


	//Parameter1:  You simply need to pass in a Target to the text layer that we are updating
	//Parameter2:  The text that should be inserted.
	//The function will update the information in the text layer and take care off all of the formating/aligment ect.  
	//It assumes that the LayerObj contains all of the necessary member variables that should have been intiated at load time or after modifiation.
	//Many times we pass in the OldText value in Parameter 2 if we don't want to change the old information but we do want to update the text display.  Useful if we just changed the font size or <bold> parameter.
	function UpdateText(LayerObj, NewText){

		//Make sure they can't press the undo button until this update has finished processing
		_root.OKtoUndo = false;

		OldTextBoxWidth = LayerObj.TextBox._width;

		//Resize the Text layer.
		TextScale = GetFontScale(LayerObj.TextSize);
		LayerObj.TextBox._xscale = TextScale;
		LayerObj.TextBox._yscale = TextScale;


		//If our text is center or left aligned... and our font size has increased, this will take care of the repositioning.
		RePositionTextLayerForResizedFont(LayerObj, OldTextBoxWidth);


		//In Case there has been an alignment change, this will reposition the text layer for us.
		RePositionTextLayerAlignmentChange(LayerObj);

		
		//So next time we can detect an alignment change.
		LayerObj.PreviousAlignment = LayerObj.TextAlignment;


		//These are local variables to this function.  We will later put these into the LayerObj's memory space after we figure out what the values are.
		MaxCharacterLen = 1;
		LastLineBreakMatched = 0;
		CurrentLineLength = 0;
		HTMLcharacterCounter = 0;

		//We need to find the number of line breaks in the text so that we can position the button and highlight boxes correctly.
		//Also we need to find the maxiumum line size in between line breaks
 		StringtLength = NewText.length;
        	var LineBreakCounter =  1;
		for(i=0;i<StringtLength;++i){		//Go through the entire String character by character.
			if(NewText.charAt(i) == "\r"){  //Just found a line break
				LineBreakCounter++;
				CurrentLineLength = 0;
				LastLineBreakMatched = i;
				HTMLcharacterCounter = 0;
			}

			else if(NewText.charAt(i) == "&"){  //Find HTML codes. We need to calculate the length of HTML character codes becuase if someone enters the string "<<<<" .. it really has a string value of "&lt;&lt;&lt;&lt;".  Then it would throw off the size of the text highlight button.
				HTMLcharacterCounter++;
			}

			CurrentLineLength = i + 1 - LastLineBreakMatched; //These 3 lines of code will figure out the most amount of characters in between link breaks.

			if((CurrentLineLength - HTMLcharacterCounter * 3) > MaxCharacterLen ){ //The number of characters in the text box isn't necessarily the same amount of characters the user will see becuase of HTML character conversion.
				MaxCharacterLen = (CurrentLineLength - HTMLcharacterCounter * 3);
			}
		}

		//Update the LayerObj's member variable that contains the text information.
		LayerObj.MainTextHolder = NewText;

		//These variables will hold the HTML if unerline, italics
		TextUnderlineStart = "";   
		TextUnderlineEnd = "";


		if(LayerObj.TextUnderline == "yes"){
			TextUnderlineStart = "<U>";
			TextUnderlineEnd = "</U>";
		}


		//Update the text layer on the screen.  The dynamic box interprets HTML so we need to fill it with an HTML representation of this LayerObj's text properties.
		LayerObj.TextBox.text = "<P align=\""+ LayerObj.TextAlignment  + "\">" + TextUnderlineStart + NewText + TextUnderlineEnd + "</P>";

		LineHeight = LineBreakCounter;


		//Store these variables in the Layer Object.. So if we re-activate this layer we can grab the values out without re-parsing the data.
		LayerObj.MaxCharacterLen = MaxCharacterLen;
		LayerObj.MaxLineSize = GetMaxLineSize(MaxCharacterLen, LayerObj.TextSize);
		LayerObj.LineHeight = LineHeight;

		//Set the color for the font.
		ChangeTextColor(LayerObj);

		//Now that we have set a lot of properties for this LayerObj.. Forward the LayerObj onto the function that will set the Highlight Box around it.
		PositionText_Highlight(LayerObj);


		// If the text layer has a shadow, it should be updated as well.
		if(CheckIfTextLayerHasShadow(LayerObj)){

			shadowLayerObj = GetShadowTextLayerObj(LayerObj);
			
			shadowLayerObj.MainTextHolder = LayerObj.MainTextHolder;
			shadowLayerObj.TextBox.text = LayerObj.TextBox.text;
			shadowLayerObj.TextAlignment = LayerObj.TextAlignment;
			shadowLayerObj.PreviousAlignment = LayerObj.TextAlignment;
			
			RepositionShadowLayerAccordingToParent(LayerObj);
		
		}
		


		//Let Javascript know that the flash program is done updating.  This way it can feed us more messages from the queue
		//For macintosh, we do not have any quick edit fields
		if(_root.macuser != "yes"){
		
			//The fscommand is only working on windows right now
			if(_root.useragent == "MSIE"){
				if(!_root.TestMode)
					fscommand("TextUpdateCompleted", "");
			}
			else{
				if(!_root.TestMode)
					getURL("Javascript:drawtech_DoFSCommand('TextUpdateCompleted','')")
			}
		}
		
		_root.OKtoUndo = true;
	}
	
	//If there is a quick field in the HTML, it will update the text value from the Layer Object.
	function UpdateTextinHTML(LayerObj79){
	
		//If this TextLayer has a field name with it then it means there is an html box on the screen.  We need to update the field value with a new one that the user just typed in.
		//We use the field "field_order" to name the html boxes.
		//Don't send this update if we are in proof mode
		//Also don't send if the user is a Macitosh, since the quick edit fields don't work
		if(LayerObj79.field_name != "" && _root.UserType != "proof" && _root.macuser != "yes"){

			TextForUpdate = _root.UnTranslateHTMLentities(LayerObj79.MainTextHolder);

			//I am replacing cairage returns with a special code !br! becuase it seems I can't pass cairage returns to the javascript in this manner.
			var TextForJavascript = StringReplace(TextForUpdate, "\r", "!br!");

			//Escape single quotes so Javascript won't crash
			TextForJavascript = StringReplace(TextForJavascript, "'", "\\'");

			if(!_root.TestMode)
				getURL("JavaScript:UpdateFieldValuesFromFlash('" +  LayerObj79.field_order + "', '" + TextForJavascript + "')");
		}
	}
	
	//In case our text is right or center aligned  (and we have resized the font)... we need to know how much the text box grew.
	//Take a measurement of LayerObj89.TextBox._width BEFORE resizing the Text layer... and pass the old value into the 2nd parameter of this function.
	function RePositionTextLayerForResizedFont(LayerObj89, OldTextBoxWidth){
	
		TextBoxWidthChange = LayerObj89.TextBox._width - OldTextBoxWidth;


		if(LayerObj89.TextAlignment == "Center"){
			DistanceChange = -(TextBoxWidthChange/2);
		}
		else if(LayerObj89.TextAlignment == "Right"){
			DistanceChange = -(TextBoxWidthChange);
		}
		else{
			DistanceChange = 0;
		}

		LayerObj89._y = DistanceChange * Math.sin(Math.PI/180 * LayerObj89._rotation) + LayerObj89._y;
		LayerObj89._x = DistanceChange * Math.cos(Math.PI/180 * LayerObj89._rotation) + LayerObj89._x;
	
	}
	
	//We store the alignment of the text object in 2 variables.. PreviousAlignment  and the current alignment.
	function RePositionTextLayerAlignmentChange(LayerObj88){
	
  		if(LayerObj88.PreviousAlignment == "Left"){
			if(LayerObj88.TextAlignment == "Center"){
				DistanceChange =  -(LayerObj88.TextBox._width/2 - LayerObj88.HighLightButton._width/2);
			}
			else if(LayerObj88.TextAlignment == "Right"){
				DistanceChange =  -(LayerObj88.TextBox._width - LayerObj88.HighLightButton._width);
			}
			else{
				DistanceChange = 0;
			}
		}

		else if(LayerObj88.PreviousAlignment == "Center"){
			if(LayerObj88.TextAlignment == "Left"){
				DistanceChange =  LayerObj88.TextBox._width/2 - LayerObj88.HighLightButton._width/2;
			}
			else if(LayerObj88.TextAlignment == "Right"){
				DistanceChange =  -(LayerObj88.TextBox._width/2 - LayerObj88.HighLightButton._width/2);
			}
			else{
				DistanceChange = 0;
			}
		}

		else if(LayerObj88.PreviousAlignment == "Right"){
			if(LayerObj88.TextAlignment == "Center"){
				DistanceChange =  LayerObj88.TextBox._width/2 - LayerObj88.HighLightButton._width/2;
			}
			else if(LayerObj88.TextAlignment == "Left"){
				DistanceChange =  LayerObj88.TextBox._width - LayerObj88.HighLightButton._width;
			}
			else{
				DistanceChange = 0;
			}
		}
		else{
			DistanceChange = 0;
		}
		

		LayerObj88._y = DistanceChange * Math.sin(Math.PI/180 * LayerObj88._rotation) + LayerObj88._y;
		LayerObj88._x = DistanceChange * Math.cos(Math.PI/180 * LayerObj88._rotation) + LayerObj88._x;

	}

	//Record the new text size and Maximum line size into the member variable of the layer
	//This information is needed before we can call PositionText_Highlight
	function RecordTextLayerDimensions(LayerObj92, TextSize92){	
	
		LayerObj92.MaxLineSize = GetMaxLineSize(LayerObj92.MaxCharacterLen, TextSize92);
		LayerObj92.TextSize = TextSize92;
		
		if(CheckIfTextLayerHasShadow(LayerObj92))
			RepositionShadowLayerAccordingToParent(LayerObj92);
	}
	
	function GetMaxLineSize(MaxCharacterLen, FontSize){
	
		//MaxLineSize contains a pixel measurement of the longest line in between line breaks.  We will use this measurement later to figure out how big the Hightlight box should be.
		MaxLineSize = MaxCharacterLen * FontSize;

		//If there are a lot of characters then the width of the line doesn't equal the sum of Pixel Size of each character.  Becase of letters like "l"  .. We just take an aproximation here.
		//But if there is less then 6 characters don't do this calculation.  Otherwise it is too thin with some fonts.  This is a really really crude calculation but I can't think of an exact way to calculate. 
		if(MaxCharacterLen > 6){
			MaxLineSize = MaxLineSize/1.9;
		}
		
		return MaxLineSize;
	}

	function ScaleTextLayer(LayerObj99, FontSize99){

		OldTextBoxWidth99 = LayerObj99.TextBox._width;

	 	ScaleVal = GetFontScale(FontSize99);

		//Set Layer to where we are supposed to start from
		LayerObj99.TextBox._xscale = ScaleVal;
		LayerObj99.TextBox._yscale = ScaleVal;

		//If our text is center or left aligned... and our font size has increased, this will take care of the repositioning.
		RePositionTextLayerForResizedFont(LayerObj99, OldTextBoxWidth99);
		
		if(CheckIfTextLayerHasShadow(LayerObj99))
			RepositionShadowLayerAccordingToParent(LayerObj99);
	}


	//This function will change the font.  Pass in a reference to a target where we want the font change to occur along with the font name to change to.
	//LoadMovie will replace the current "TextBox" that conatins information WITHIN the text layer object.
	//the TextBox object that is getting replaced is a child of the LayerObj so we are not overwriting any data or member variables.  Just replacing the text box which contains the Vector Outlines for the font that was selected.
	//The TextBox has a onClipevent(load) function which will trigger an update of the TextDisplay... re-inserting the correct text information and text properties.. It calls the function 'UpdateText'.
	function ChangeFont(LayerObj3, FontName){
	
		// This is a way to depreciate old fonts.
		if(FontName == "Timeless")
			FontName = "Times";

		//This flag will let the program know that the font hasn't finished downloading.
		LayerObj3.TextBox.ExistenceChecker = false;
	
		LayerObj3.TextFont = FontName;
		
		// So it will remember the font... in case we are inserting any more text layers
		_root.LastTextTouched_Font = FontName;
		
		// In case any bold/italic properties are set... 
		// before changing the font we need to make sure the font has the capability
		var AttributeChangeForced = false;
		if(LayerObj3.TextBold == "yes" && LayerObj3.TextItalic == "yes"){
			if(!CheckIfFontHasBoldAndItalics(FontName)){
				LayerObj3.TextItalic = "no";
				LayerObj3.TextBold = "no";
				AttributeChangeForced = true;
			}
		}
		else if(LayerObj3.TextBold == "yes"){
			if(!CheckIfFontHasBold(FontName)){
				LayerObj3.TextBold = "no";
				AttributeChangeForced = true;
			}
		}
		else if(LayerObj3.TextItalic == "yes"){
			if(!CheckIfFontHasItalics(FontName)){
				LayerObj3.TextItalic = "no";
				AttributeChangeForced = true;
			}
		}
		
		// If we removed any bold/italic capabilities for the font... then reset the buttons on the UI
		if(AttributeChangeForced){
			_root.TextButtons.Bold.gotoAndPlay(1);
			_root.TextButtons.Italics.gotoAndPlay(1);
		}


		_root.DownloadCheck.MarkNewDownload();


		// The bold/italics are downloaded by appending "b" or "i" or "bi" to the end of the font name... but before the .swf or .ttf extention
		var FontBoldItalicStr = "";
		if(LayerObj3.TextBold == "yes")
			FontBoldItalicStr += "b";
		if(LayerObj3.TextItalic == "yes")
			FontBoldItalicStr += "i";


		//The name of the SWF that has the font outlines embeded are the same as the FontName we are changin to.
		loadMovie("fonts/" + FontName + FontBoldItalicStr + ".swf",LayerObj3.TextBox);
		
		//This MC makes sure that all of the fonts download within a time limit
		LayerObj3.DownloadErrorCheck.gotoAndPlay(3);
		
		if(CheckIfTextLayerHasShadow(LayerObj3)){
			ShadowLayerObj3 = GetShadowTextLayerObj(LayerObj3);
			ChangeFont(ShadowLayerObj3, FontName);
	
			RepositionShadowLayerAccordingToParent(LayerObj3);
		}

	}
	
	
	// Pass in a reference to a layer Graphic.

	// It will return True only if the layer is a Graphic and it has a Vector Image ID associated with it.
	function CheckIfLayerIsVectorGraphic(LayerReference34){
	
		if(LayerReference34.LayerType  == "graphic" ){
		
			if(LayerReference34.VectorImageId != "")
				return true;
		}
		
		return false;
	}
	

	//When a user clicks on a Graphic or text layer it invokes this function to activate. 
	//It does things like make the Black Boxes visible and initlize Font Properties buttons, ect.
	//We pass in a reference to a Target and also the layer type.. LayerType could be "text" or "graphic"
	function ActivateLayer(LayerReference){
	
		//Make sure that an "Undo" process isn't underway
		if(_root.OKtoUndo){
		
			// Before un-selecting the current layer (if one is selected).
			// We want to see if it is a Vector Based graphic so we know whetere to take down the warning graphic or not.
			if(LayerTypeSelected == "graphic" && CheckIfLayerIsVectorGraphic(LayerSelectedObj))
				var LastLayerWasVectorFlag = true;
			else
				var LastLayerWasVectorFlag = false;
		

			//Un-select any layers that are currently selected.  
			//This function call appears here and also on any buttons that wish to deselect active layers.  If no layers are active then it still doesn't hurt when we run this function.
			UnSelectCurrentLayer();

			//Set the global variables which will point or describe the current layer that is active
			LayerTypeSelected = LayerReference.LayerType;
			LayerSelectedObj = LayerReference;

			//The Target name for the Highlight layer with blaock boxes.
			HighlightBoxesObj = _root.GetHighlightButtonObjbyReference(LayerReference);
			HighlightBoxesObj._visible = true;



			//Keep track of the last layer that was activated so we don't have to run the shields MC if there no change.
			//LastLayerTypeActivated is "none" if no layer was selected before
			if(_root.LastLayerTypeActivated ==  "text" ||  _root.LastLayerTypeActivated ==  "graphic"){

				//If this is the case then the shields should have never been brought down.
				_root.DisableRotate.gotoAndStop(1);
				_root.DisableStacking.gotoAndStop(1);
			}
			else{
				//It means nothing that neither a graphic or text was selected before... so take down the shields
				//Enable slider.  Take the shields down so people can click on the controls..
				_root.DisableRotate.gotoAndPlay(12);
				_root.DisableStacking.gotoAndPlay(12);
			}


			//Show the Rotate Handle and set it to the right position... Make sure that there are no decimals
			_root.Rotate.SetHandle(int(LayerSelectedObj._rotation));

			//Show the degree symbol in the rotate bar.
			_root.Rotate.symbols._visible = true;
			

			//Different code has to execute depending on whether we are activating a graphic layer or a text one.
			if(LayerTypeSelected == "graphic"){

				//Resize the black boxes surrounding the layer.  We need to do this in case the zoom factor changed while the layer was unhighlighted.
				SetHighlightContainer(HighlightBoxesObj, LayerSelectedObj);

				// Show an indicator if this is a vector image.
				if(CheckIfLayerIsVectorGraphic(LayerReference)){
					
					// If the last layer was a Vector layer (and so is the new one)... then don't make a change to the warning flag.
					if(LastLayerWasVectorFlag)
						_root.vectorImageBtn.gotoAndStop("up");
					else
						_root.vectorImageBtn.gotoAndPlay("show");
				}

			}
			else if(LayerTypeSelected == "text"){

				//Set black boxes around text block
				PositionText_Highlight(LayerSelectedObj)

				//Set the state of the bold/italics/underline buttons for the text layer they just selected.
				_root.TextButtons.Bold.gotoAndPlay(1);
				_root.TextButtons.Italics.gotoAndPlay(1);
				_root.TextButtons.Underline.gotoAndPlay(1);

				//Set the proper alignment buttons.
				_root.TextAlign.gotoAndPlay(1);

				//Running this movie will pick up the correct text size out of the LayerSelectedObj and place it withing the Dynamic text box to show the user what the current font size it.
				_root.TextSize.gotoAndPlay(1);

				//Set the value in the "input box" of the font name for whatever the font name is inside LayerSelectedObj
				_root.SelectFont.fontname = LayerSelectedObj.TextFont;
				
				// So it will remember the font... in case we are inserting any more text layers
				_root.LastTextTouched_Font = LayerSelectedObj.TextFont;

				//Change the color of the indicator and make it visible.
				_root.ColorPicker._visible = true;


				//Keep track of the last layer that was activated so we  avoid playing redundant movie clips for the "shields/disable blue opaqe" buttons.
				if(_root.LastLayerTypeActivated ==  "text"){

					//If this is the case then the shields should have never been brought down.
					_root.DisableText.gotoAndStop(1);
				}
				else{
					//Enable text controls.  Take the shields down so people can click on the controls..
					_root.DisableText.gotoAndPlay(12);
				}


				//If NumberOfColors = 0 then it means that there are unlimitied colors.  If so then the color Hex code is stored directly in the object.  Otherwise we need to get the hex code from the color ID.
				if(_root.NumberOfColors == 0){
					_root.ColorPickerIndicator.setRGB(LayerSelectedObj.Color);
				}
				else{
					//The color hexcode is stored in the 2D array that we created when initializing this side.
					_root.ColorPickerIndicator.setRGB(_root.ColorDefinitions[LayerSelectedObj.Color]["hexcode"]);
				}

			}
		}
	}

	//This is used to unselect a layer (only if one is selected though).  If none are selected then running this function won't hurt anything.
	//It will basicaly get rid of the scale boxes and perform "locking" on the buttons up top.
	function UnSelectCurrentLayer(){


		//Make sure that an "Undo" process isn't underway
		if(_root.OKtoUndo){
		
			//Make sure the degree symbol in rotate isn't visible.
			_root.Rotate.symbols._visible = false;
			
			// If there was flag to indicate a Vector Image was clicked on... hide that.
			_root.vectorImageBtn.gotoAndStop("hide");

			//LayerTypeSelected is a global variable that describes the current layer that is selected.. choices here are "graphic", "text", or "none"
			if(LayerTypeSelected == "graphic"){
			
				//Find out the Highligh Boxes target associated with the active layer and hide it.				
				HighlightBoxesObj = _root.GetHighlightButtonObjbyReference(LayerSelectedObj);				
				
				HighlightBoxesObj._visible = false;

				//If we keep track of the last layer that was activated, we can avoid player redundant movie clips for the "shields/disable blue opaqe" buttons.
				_root.LastLayerTypeActivated = "graphic";

			}
			else if(LayerTypeSelected == "text"){

				//If we keep track of the last layer that was activated, we can avoid player redundant movie clips for the "shields/disable blue opaqe" buttons.
				_root.LastLayerTypeActivated = "text";

				//Find out the Highligh Boxes target associated with the active layer and hide it.
				HighlightBoxesObj = _root.GetHighlightButtonObjbyReference(LayerSelectedObj);
				HighlightBoxesObj._visible = false;

				//Close the Font drop down list if the user left it open.
				_root.SelectFont.gotoAndStop(1);

				//close the text size slider if it is open
				_root.TextSize.gotoAndStop(2);

				//Clear out the font name and size in the UI
				_root.SelectFont.fontname = "";
				_root.TextSize.fontsize = "";

				//Pop out any Text property buttons.
				_root.TextButtons.Bold.gotoAndPlay(1);
				_root.TextButtons.Italics.gotoAndPlay(1);
				_root.TextButtons.Underline.gotoAndPlay(1);

				//Pop all of the alignment buttons out
				_root.TextAlign.gotoAndPlay(1);

				//Hide the color picker.
				_root.ColorPicker._visible = false;

				//Disable the text field.  Put the shield back up So people can't click on any of the controls..
				_root.DisableText.gotoAndPlay(2);

				//Rewind the color picker movie clip.
				_root.ColorPicker.gotoAndStop(1);

				//This will close the Edit Text window in case it is open.
				_root.EditText.gotoAndStop(1);


				//This is only needed for the administrative program.
				//We need to check for existence first in case the side-edit window isn't open.
				if(_root.admin._currentframe == 2){
					_root.admin.inputboxes.field_name = "";
					_root.admin.inputboxes.permissions = "";
				}

			}
			else{
				//If we keep track of the last layer that was activated, we can avoid player redundant movie clips for the "shields/disable blue opaqe" buttons.
				_root.LastLayerTypeActivated = "none";
			}

			//We can tell nothing is selected now with this varialbe
			LayerTypeSelected = "none";


			//We don't want to put the shields up if they already are up.
			if(_root.LastLayerTypeActivated == "graphic" || _root.LastLayerTypeActivated == "text"){

				//Disable the roate slider.  Put the shield back up So people can't click on the rotate button..
				_root.DisableRotate.gotoAndPlay(2);
				_root.DisableStacking.gotoAndPlay(2);
			}

			//Hide the Rotation Handle and set the Text to NULL.
			_root.Rotate.RotateHandle._visible = false;
			_root.Rotate.RotationDegree = "";
		}
	}

	//This function is called by the onClipevent(load) whenever the graphic finished loading.  It passes in a reference to its own object.
	function InitializeGraphic(GraphicObject){

		//Make the buttons really small.  In a second we are going to rezie the button but we don't want it to interfere with the size of the graphic container if the Image is a really small one.
		GraphicObject.HightLightButton._width = 1;
		GraphicObject.HightLightButton._height = 1;

		//If these values are set to zero then it means that this image was just inserted by the user through uploading..
		if(GraphicObject.CurrentWidth == 0 && GraphicObject.CurrentHeight == 0){

			//If we increase the DPI for a Side... We don't want the appearance to look any different in our flash program or to the user.  We are just going to shrink the graphic according to the dpi.
			//96 dpi is what most monitors display their resolution at.  Therefore dividing that by the DPI of our Side will give us a ratio that it will roughly be printed at..
			//When we generate the JPEG image we will do the opposite... leave the graphic at its default size and then just increase the pixel dimensions of the total image and increase the font size according to the ratio.
			DPI_ScaleRatio = 96 / _root.sideProperties[_root.SideSelected]["dpi"];
			GraphicObject.ImageClip._xscale = 100 * DPI_ScaleRatio;
			GraphicObject.ImageClip._yscale = 100 * DPI_ScaleRatio;

			//Record the dimensions into this Layer Objects Member Varialbes..
			GraphicObject.CurrentWidth = GraphicObject.ImageClip._width;
			GraphicObject.CurrentHeight = GraphicObject.ImageClip._height;

			//This is for the Image dimensions restore button
			//Also we can tell if they are stretching the image after they upload (and losing DPI)
			GraphicObject.OriginalHeight = GraphicObject.ImageClip._height;
			GraphicObject.OriginalWidth = GraphicObject.ImageClip._width;
			
			_root.UNDO_InsertOrDeleteImage(GraphicObject, "insert");

		}
		else{
			//Resize the Image clip to whatever was stored in the objects member variables.  These dimensions came from when we loaded the XML doc.
			GraphicObject.ImageClip._width = GraphicObject.CurrentWidth;
			GraphicObject.ImageClip._height = GraphicObject.CurrentHeight;
		}


		//Make sure the button exactly covers the image that was just downloaded. 
		//This is the same calculation seen in Re-size graphic function and the "Restore Image".
		GraphicObject.HightLightButton._width = GraphicObject.ImageClip._width; 
		GraphicObject.HightLightButton._height = GraphicObject.ImageClip._height;

		//Find out the Highligh Boxes target associated with the active layer.
		ThisHighlightBoxesObj = _root.GetHighlightButtonObjbyReference(GraphicObject);

		//Make sure the black boxes will be centered exactly over the graphic.
		ThisHighlightBoxesObj._x = GraphicObject._x;
		ThisHighlightBoxesObj._y = GraphicObject._y;

		//If the rotation is 0 then we will allow them to scale the graphic.  Otherwise not.
		if(GraphicObject._rotation != 0 && GraphicObject._rotation != 90 && GraphicObject._rotation != -90 && GraphicObject._rotation != 180 && GraphicObject._rotation != -180){
			ThisHighlightBoxesObj.gotoAndStop(2);
		}
		else {
			ThisHighlightBoxesObj.gotoAndStop(1);
		}

		//Make sure the image gets centered exactly in the middle of the layer.
		SetImageInCenterofLayer(GraphicObject);

		//Make the black boxes hug around the image that we just inerted/resized.
		SetHighlightContainer(ThisHighlightBoxesObj, GraphicObject);

		//Now that the graphic has finished initialzing we can make it visible again.
		GraphicObject._visible = true;
		
		
		// If the image does not have permissions to be "selectable" then we want to hide the "button" MovieClip
		if(GraphicObject.permissions["not_selectable"])
			GraphicObject.HightLightButton._visible = false;

	}

	function GetMiddlePoint(){
		//We want the layer to appear in the middle of the screen no matter how they have zoomed or panned
		//This function converts these hard coded Stage coodinates to coordinates relative to the content.
		DefauldMiddlePoint = new Object(); 
 		DefauldMiddlePoint.x = _root.CenterPoint._x; 
 		DefauldMiddlePoint.y = _root.CenterPoint._y; 
 		globalToLocal(DefauldMiddlePoint); 

		return DefauldMiddlePoint;

	}

	//This function makes a new text layer that is just filled with default values. 
	//This function gets called fom the UI button "New Text Layer"
	//Returns the LayerNumber of the new Text Layer
	function MakeNewLayer_Text(InitialFontChosen){

		//Get the coodinates for the center point of the window.
		Point = new Object(); 
		Point = GetMiddlePoint();

		//The initial size of the font should be relative to the zoom
		InitialFontSize = Math.round(100/_root.CurrentZoom * 20); 


		_root.Layer_Text_DepthCounter++;

		//foo will give us the length to the array.  Since arrays are 0 based we can automaticaly increase the size by one when we use is for an index.
		foo = _root.sideProperties[_root.SideSelected]["Layers"].length;


		_root.sideProperties[_root.SideSelected]["Layers"][foo] = new Array;
		_root.sideProperties[_root.SideSelected]["Layers"][foo]["type"] = "text";
		_root.sideProperties[_root.SideSelected]["Layers"][foo]["level"] = _root.Layer_Text_DepthCounter;
		_root.sideProperties[_root.SideSelected]["Layers"][foo]["xcoordinate"] = Point.x;
		_root.sideProperties[_root.SideSelected]["Layers"][foo]["ycoordinate"] = Point.y;
		_root.sideProperties[_root.SideSelected]["Layers"][foo]["rotation"] = _root.GetDegreeBetween180(360 - this._rotation);  //Make sure that even if the Canvas is rotated that the text layer appears level

		_root.sideProperties[_root.SideSelected]["TextProperties"][foo] = new Array;
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["align"] = "Left";
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["italics"] = "no";
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["bold"] = "no";
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["underline"] = "no";
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["font"] = InitialFontChosen; 
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["field_name"] = "";
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["size"] = InitialFontSize;
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["field_order"] = "0";  // Zero means that we don't have a field order yet.  Field order is used to control the position of HTML inputs above the editing tool.  If it has a field_name then it has a field_order
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["shadow_level_link"] = "";  // Will contain the layer level of a text layer that is this text layers shadow.  If this layer does not have a shadow, then it will be blank
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["shadow_distance"] = "";
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["shadow_angle"] = "";
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["message"] = "New Text Layer\rDouble-click to edit.";


		//If we are dealing with unlimited colors then set the Color to a Hex Code of black.  Otherwise just set it to color 1.
		if(_root.NumberOfColors == 0){
			_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["color"] = 0x000000;
		}
		else{
			_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["color"] = 1;
		}



		// new layers do not have any permissions set.
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["permissions"] = new Array;
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["permissions"]["position_x_locked"] = false;
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["permissions"]["position_y_locked"] = false;
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["permissions"]["size_locked"] = false;
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["permissions"]["deletion_locked"] = false;
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["permissions"]["color_locked"] = false;
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["permissions"]["font_locked"] = false;
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["permissions"]["alignment_locked"] = false;
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["permissions"]["rotation_locked"] = false;
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["permissions"]["data_locked"] = false;
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["permissions"]["not_selectable"] = false;
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["permissions"]["not_transferable"] = false;
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["permissions"]["always_on_top"] = false;
		_root.sideProperties[_root.SideSelected]["TextProperties"][foo]["permissions"]["debug"] = "testing Make new Layer";
		

		//Add 1 becuase the Layers array is 0 based and the Layer Numbers are 1 based.
		MakeNewLayer(_root.SideSelected, (foo + 1));
	
		return (foo + 1);
	}


	function MakeNewLayer_Image(ImageID, VectorImageId){

		//Get the coodinates for the center point of the window.
		Point = new Object(); 
		Point = GetMiddlePoint();

		// Vector Layers are in a different stacking sequence (above) compared to regular graphics.
		if(VectorImageId != ""){
			_root.Layer_VectorGraphic_DepthCounter++;
			var depthForThisLayer = _root.Layer_VectorGraphic_DepthCounter;
		}
		else{
			_root.Layer_Graphic_DepthCounter++;
			var depthForThisLayer = _root.Layer_Graphic_DepthCounter;
		}
		
		
		

		//foo will give us the length to the array.  Since arrays are 0 based we can automaticaly increase the size by one when we use is for an index.
		foo = _root.sideProperties[_root.SideSelected]["Layers"].length;

		_root.sideProperties[_root.SideSelected]["Layers"][foo] = new Array;
		_root.sideProperties[_root.SideSelected]["Layers"][foo]["type"] = "graphic";
		_root.sideProperties[_root.SideSelected]["Layers"][foo]["level"] = depthForThisLayer;
		_root.sideProperties[_root.SideSelected]["Layers"][foo]["xcoordinate"] = Point.x;
		_root.sideProperties[_root.SideSelected]["Layers"][foo]["ycoordinate"] = Point.y;
		_root.sideProperties[_root.SideSelected]["Layers"][foo]["rotation"] = _root.GetDegreeBetween180((360 - this._rotation));  //This is in case the canvas has been rotated.  We always want the text to appear level.

		//Let the program know that this graphic has not been uploaded yet.
		_root.sideProperties[_root.SideSelected]["Layers"][foo]["newgraphic"] = "yes";

		_root.sideProperties[_root.SideSelected]["GraphicProperties"][foo] = new Array;

		//By setting the following properties to a value of 0.. I am setting a signal to this program to set the properties during graphic initialization.
		//Becuase the user can never resize the graphic to values of 0 this is save to do.
		_root.sideProperties[_root.SideSelected]["GraphicProperties"][foo]["height"] = 0;
		_root.sideProperties[_root.SideSelected]["GraphicProperties"][foo]["width"] = 0;
		_root.sideProperties[_root.SideSelected]["GraphicProperties"][foo]["originalheight"] = 0;
		_root.sideProperties[_root.SideSelected]["GraphicProperties"][foo]["originalwidth"] = 0;
		_root.sideProperties[_root.SideSelected]["GraphicProperties"][foo]["imageid"] = ImageID;
		_root.sideProperties[_root.SideSelected]["GraphicProperties"][foo]["VectorImageId"] = VectorImageId;
		
		
		
		// new layers do not have any permissions set.
		_root.sideProperties[_root.SideSelected]["GraphicProperties"][foo]["permissions"] = new Array;
		_root.sideProperties[_root.SideSelected]["GraphicProperties"][foo]["permissions"]["position_x_locked"] = false;
		_root.sideProperties[_root.SideSelected]["GraphicProperties"][foo]["permissions"]["position_y_locked"] = false;
		_root.sideProperties[_root.SideSelected]["GraphicProperties"][foo]["permissions"]["size_locked"] = false;
		_root.sideProperties[_root.SideSelected]["GraphicProperties"][foo]["permissions"]["deletion_locked"] = false;
		_root.sideProperties[_root.SideSelected]["GraphicProperties"][foo]["permissions"]["rotation_locked"] = false;
		_root.sideProperties[_root.SideSelected]["GraphicProperties"][foo]["permissions"]["not_selectable"] = false;
		_root.sideProperties[_root.SideSelected]["GraphicProperties"][foo]["permissions"]["not_transferable"] = false;
		_root.sideProperties[_root.SideSelected]["GraphicProperties"][foo]["permissions"]["always_on_top"] = false;


		//Add 1 becuase the Layers array is 0 based and the Layer Numbers are 1 based.
		MakeNewLayer(_root.SideSelected, (foo + 1));
	}

	//Function will make a new Text or Graphic Layer.  
	//This function relys that three arrays have already been created called "Layers", "TextProperties", and "Graphic Properties".  These are all 2 dimensional arrays.
	//The LayerNo that we pass into the function should correspond to the Array index within the three arrays I mentioned above.  We need to subract 1 becuase our arrays are Zero Based.
	function MakeNewLayer(SideNumer8, LayersNo){

		if(_root.sideProperties[SideNumer8]["Layers"][(LayersNo - 1)]["type"] == "text"){

			//We need to start a new new download for the "Downloading Icon" becuase when this clip finishes attaching it will mark that a new download was completed.
			//Even though this clip will attach almost instantly we still need to put it here to keep things balanced. 
			_root.DownloadCheck.MarkNewDownload();

			//The depth for the Text layer should come from whatever is stored in the Array.
			TextLayerDepth = _root.sideProperties[SideNumer8]["Layers"][(LayersNo - 1)]["level"];
			TextLayerDepthForAttachment = TextLayerDepth;
			
			// In case the layer has a permission set that requires it to always be on top... then we just add 1000 levels to it.  That will ensure that nothing gets moved on top.
			// The server-side object will always do the final security checks against this anyway.
			if(_root.sideProperties[SideNumer8]["TextProperties"][(LayersNo - 1)]["permissions"]["always_on_top"])
				TextLayerDepthForAttachment += 1000;
		

			//Attach the Text Layer.  
			attachMovie("LayerText", ("TextClipLayer" + LayersNo), TextLayerDepthForAttachment);

			//Make sure that _root.Layer_Text_DepthCounter always has a record of the highest depth for the layers.
			if( TextLayerDepth > _root.Layer_Text_DepthCounter){
				_root.Layer_Text_DepthCounter = TextLayerDepth;
			}
			else if( TextLayerDepth < _root.Layer_Text_DepthCounter_minimum){
				_root.Layer_Text_DepthCounter_minimum = TextLayerDepth;
			}

			
			//Attached the Highlight Boxes to this Layer
			_root.HighlightDepthCounter++;
			attachMovie("HighlightText", ("HighLightLayer" + LayersNo),_root.HighlightDepthCounter); 

			//Get a target reference to the new Movie Clips we just attached
			ThisLayerObj = eval("TextClipLayer" + LayersNo);
			HighlightBoxesObj = eval("HighLightLayer" + LayersNo);

			//Record the Depth level into a member variable of the Layer object.  I couldn't get MovieClip.getDepth() to work.. so I just keep track of the depth this way.
			ThisLayerObj.ClipDepth = TextLayerDepth;
			
			// The ClipDepth might change up or down (with layer stacking)
			// The original clipdepth will be able to give us reference to this layer object (from the original array _root.sideProperties)
			ThisLayerObj.OriginalClipDepth = TextLayerDepth;

			ThisLayerObj.LayerType = "text";
			
			//Make sure people can't see the boxes until they click on the layer.
			HighlightBoxesObj._visible = false;


			//Use these variable to make the code shorter below.
			ThisLayerRotation = _root.sideProperties[SideNumer8]["Layers"][(LayersNo - 1)]["rotation"];
			ThisLayerCoordinate_x = _root.sideProperties[SideNumer8]["Layers"][(LayersNo - 1)]["xcoordinate"];
			ThisLayerCoordinate_y = _root.sideProperties[SideNumer8]["Layers"][(LayersNo - 1)]["ycoordinate"];


			//The actuall coordinates used in the flash program are quite different than what is seen in the XML
			//It has to shift coodinates heavily when the aligment is changes and use trigonemetric function to account for rotation
			//We don't want to convert back and forth if we don't have to.  There can be small rounding errors.   If you save the file many times text may drift over time if the layer is right justified and rotated.
			//Set UseOriginalCoordinates to false whenever the aligment has been changed or the position of the layer has changed... that we will will know to recalculate the coordinates before saving
			ThisLayerObj.UseOriginalCoordinates = true;
			ThisLayerObj.OriginalCoordinate_X = ThisLayerCoordinate_x;
			ThisLayerObj.OriginalCoordinate_Y = ThisLayerCoordinate_y;

			//We need to tranlate the padding  that exists within Text Boxes See MovieClip: "Layer Text" Frame: 1  Layer: "Help" for a better explanation.
			//Our Scaling is relative to the default font size of 8
			TextScaleFactor =  _root.sideProperties[SideNumer8]["TextProperties"][(LayersNo - 1)]["size"] / 8;
			TextPadding = Math.round(2 * TextScaleFactor);


			//Find the vertical padding offset based on the angle.  This applies to all alignments
			//We also need to account for right and left aligned paddings in the equations below.
			//Add to x and subtract from y becuase our registration point begins in the top-left hand corner.
			PaddingShift_X = Math.sin(Math.PI/180 * ThisLayerRotation) * TextPadding;
			PaddingShift_Y = -(Math.cos(Math.PI/180 * ThisLayerRotation) * TextPadding);


			//The purpose of this if/else clause is to do a coordinate conversion for the text alignment.  See the explanation in the 'SaveState' function.  That is where we do the 'opposite conversion' and I wrote a better explanation there.
			if(_root.sideProperties[SideNumer8]["TextProperties"][(LayersNo - 1)]["align"] == "Center"){
				ThisLayerObj._x = PaddingShift_X - (ThisLayerObj.TextBox._width/2) * Math.cos(Math.PI/180 * ThisLayerRotation) + ThisLayerCoordinate_x;
				ThisLayerObj._y = PaddingShift_Y - (ThisLayerObj.TextBox._width/2) * Math.sin(Math.PI/180 * ThisLayerRotation) + ThisLayerCoordinate_y;
			}
			else if(_root.sideProperties[SideNumer8]["TextProperties"][(LayersNo - 1)]["align"] == "Right"){
				ThisLayerObj._x = PaddingShift_X - (ThisLayerObj.TextBox._width - TextPadding) * Math.cos(Math.PI/180 * ThisLayerRotation) + ThisLayerCoordinate_x;
				ThisLayerObj._y = PaddingShift_Y - (ThisLayerObj.TextBox._width - TextPadding) * Math.sin(Math.PI/180 * ThisLayerRotation) + ThisLayerCoordinate_y;
			}
			else{
				ThisLayerObj._x = PaddingShift_X - (TextPadding) * Math.cos(Math.PI/180 * ThisLayerRotation) + ThisLayerCoordinate_x; 
				ThisLayerObj._y = PaddingShift_Y - (TextPadding) * Math.sin(Math.PI/180 * ThisLayerRotation) + ThisLayerCoordinate_y;
			}


			//Assign the properties of this Layer into the Layer Object's member variables.  Alot of this information comes from the TextLayer 2 dimsional array.
			ThisLayerObj.LayerNumber = LayersNo;
			ThisLayerObj.SpacerPixels = 1.6;  //This takes into account that there is blank space in between the lines and we use this number to calculate for it.  It is a crude calculation though.
			ThisLayerObj.TextAlignment = _root.sideProperties[SideNumer8]["TextProperties"][(LayersNo - 1)]["align"];
			ThisLayerObj.TextItalic = _root.sideProperties[SideNumer8]["TextProperties"][(LayersNo - 1)]["italics"];
			

			ThisLayerObj.TextBold = _root.sideProperties[SideNumer8]["TextProperties"][(LayersNo - 1)]["bold"];
			ThisLayerObj.TextUnderline = _root.sideProperties[SideNumer8]["TextProperties"][(LayersNo - 1)]["underline"];
			ThisLayerObj.TextFont = _root.sideProperties[SideNumer8]["TextProperties"][(LayersNo - 1)]["font"];
			ThisLayerObj.TextSize = _root.sideProperties[SideNumer8]["TextProperties"][(LayersNo - 1)]["size"];
			ThisLayerObj.MainTextHolder = _root.sideProperties[SideNumer8]["TextProperties"][(LayersNo - 1)]["message"];
			ThisLayerObj.PreviousAlignment = _root.sideProperties[SideNumer8]["TextProperties"][(LayersNo - 1)]["align"]; //We use this value in the object to detect a change in alignment during an Text update.
			ThisLayerObj.Color = _root.sideProperties[SideNumer8]["TextProperties"][(LayersNo - 1)]["color"]; //The color value could either be an integer(corresponding to the color ID) or a hex number.
			ThisLayerObj.field_name = _root.sideProperties[SideNumer8]["TextProperties"][(LayersNo - 1)]["field_name"];
			ThisLayerObj.field_order = _root.sideProperties[SideNumer8]["TextProperties"][(LayersNo - 1)]["field_order"];
			ThisLayerObj.shadow_level_link = _root.sideProperties[SideNumer8]["TextProperties"][(LayersNo - 1)]["shadow_level_link"];
			ThisLayerObj.shadow_distance = _root.sideProperties[SideNumer8]["TextProperties"][(LayersNo - 1)]["shadow_distance"];
			ThisLayerObj.shadow_angle = _root.sideProperties[SideNumer8]["TextProperties"][(LayersNo - 1)]["shadow_angle"];
			ThisLayerObj.permissions = _root.sideProperties[SideNumer8]["TextProperties"][(LayersNo - 1)]["permissions"];



			//If this Text Layer has a field name then we want to assign a refernce to the layer object on the root so our Javascript can quickly locate the layer without searching through loops.
			if(ThisLayerObj.field_name != ""){
				FieldOrderNumber = _root.sideProperties[SideNumer8]["TextProperties"][(LayersNo - 1)]["field_order"];

				//The indexing for the array is based on the field order which is an integer.
				_root.FieldReferences[FieldOrderNumber] = ThisLayerObj;

			}
			

			//If this layer is being created from a "New Text Button" then no font has previously been defined as "NEW".
			//We don't want to have to download a new font from the server.
			if(_root.sideProperties[SideNumer8]["TextProperties"][(LayersNo - 1)]["font"] == "NEW"){

				// We may be remembering the last font that was touched, when we insert a new text layer
				// If not, the default font is Commercial.  The word "NEW" was just a place holder... so change it now.
				if(_root.LastTextTouched_Font != "")
					ThisLayerObj.TextFont = _root.LastTextTouched_Font;
				else
					ThisLayerObj.TextFont = "Commercial";

				//Make the new layer activated
				ActivateLayer(ThisLayerObj);

				//Get the coodinates for the center point of the window. 
				Point = new Object(); 
				Point = GetMiddlePoint();

				//Put the text box over the middle point
				ThisLayerObj._x = Point.x - ThisLayerObj.HighLightButton._width/2;
				ThisLayerObj._y = Point.y - ThisLayerObj.HighLightButton._height/2;

				//Sets member variables within the object... such as the width of the highlight button.. and intial angles. etc.
				CalculateTextBoxRotation(ThisLayerObj, 1);
				
				//Make sure that the new layer is level... even if the ContentLayer has been spun around
				RotateLayer(ThisLayerRotation, ThisLayerObj);
				
				//Since the layer has been activated... and the rotation was changed afterwards.. we need to put the handle into the right spot.
				_root.Rotate.SetHandle(ThisLayerObj._rotation);
				
				//Now record that we just made a new text layer.. in case the user wants to undo the command
				_root.UNDO_InsertOrDeleteText(ThisLayerObj, "insert")
			}
			else{ 	
				//Rotate the Layer.
				ThisLayerObj._rotation = ThisLayerRotation;
			}

			
			//The Text Movie Clip has an OnClipevent(load) that will triger UpdateText after that font has finished downloading.
			ChangeFont(ThisLayerObj, ThisLayerObj.TextFont);

		}

		else if(_root.sideProperties[SideNumer8]["Layers"][(LayersNo - 1)]["type"] == "graphic"){


			//Attach the Graphic Layer.  The depth for the Graphic layer should come from whatever is stored in the Array.
			GraphicLayerDepth = _root.sideProperties[SideNumer8]["Layers"][(LayersNo - 1)]["level"];
			GraphicLayerDepthForAttachment = GraphicLayerDepth;
			
			// In case the layer has a permission set that requires it to always be on top... then we just add 1000 levels to it.  That will ensure that nothing gets moved on top.
			// The server-side object will always do the final security checks against this anyway.
			if(_root.sideProperties[SideNumer8]["GraphicProperties"][(LayersNo - 1)]["permissions"]["always_on_top"])
				GraphicLayerDepthForAttachment += 1000;
			
			attachMovie("LayerGraphic", ("GraphicClipLayer" + LayersNo), GraphicLayerDepthForAttachment);

			//Make sure that _root.Layer_Graphic_DepthCounter always has a record of the highest depth for the layers.
			// And also keep track of what the minimum layer level is for each group.
			if(_root.sideProperties[SideNumer8]["GraphicProperties"][(LayersNo - 1)]["VectorImageId"] != ""){
			
				if( GraphicLayerDepth > _root.Layer_VectorGraphic_DepthCounter){
					_root.Layer_VectorGraphic_DepthCounter = GraphicLayerDepth;
				}
				else if( GraphicLayerDepth < _root.Layer_VectorGraphic_DepthCounter_minimum){
					_root.Layer_VectorGraphic_DepthCounter_minimum = GraphicLayerDepth;
				}
			}
			else{
				if( GraphicLayerDepth > _root.Layer_Graphic_DepthCounter){
					_root.Layer_Graphic_DepthCounter = GraphicLayerDepth;
				}
				else if( GraphicLayerDepth < _root.Layer_Graphic_DepthCounter_minimum){
					_root.Layer_Graphic_DepthCounter_minimum = GraphicLayerDepth;
				}
			}
			
			
			

			//Attach the highligh layer with the black boxes.
			_root.HighlightDepthCounter++;
			attachMovie("HighlightGraphic", ("HighLightLayer" + LayersNo),_root.HighlightDepthCounter); 

			//Get a target reference to the new Movie Clips we just attached
			ThisLayerObj = eval("GraphicClipLayer" + LayersNo);
			HighlightBoxesObj = eval("HighLightLayer" + LayersNo);

			//Make the layer invisible until it has finished intializing.
			ThisLayerObj._visible = false;

			//Record the Depth level into a member variable of the Layer object.  I couldn't get MovieClip.getDepth() to work.. so I just keep track of the depth this way.
			ThisLayerObj.ClipDepth = GraphicLayerDepth;

			ThisLayerObj.LayerType = "graphic";

			//Make sure people can't see the boxes until they click on the layer.
			HighlightBoxesObj._visible = false;

			//Position the Layer based on information coming from the Layers 2-dimensional array.
			ThisLayerObj._x = _root.sideProperties[SideNumer8]["Layers"][(LayersNo - 1)]["xcoordinate"];
			ThisLayerObj._y = _root.sideProperties[SideNumer8]["Layers"][(LayersNo - 1)]["ycoordinate"];

			//This is either a yes or no value.  Lets the program know if this image has been succesfully uploaded at some point or if it is brand new.
			//We need to know if it is new because in our DownloadErrorCheck moive script (Within the graphic layer).  It will perform different tests.
			ThisLayerObj.NewGraphic = _root.sideProperties[SideNumer8]["Layers"][(LayersNo - 1)]["newgraphic"];

			//Store information into the Layer Object's member variables.
			ThisLayerObj.LayerNumber = LayersNo;

			//Record the "last-saved" dimensions into this Layer Objects Member Varialbes..
			ThisLayerObj.CurrentWidth = _root.sideProperties[SideNumer8]["GraphicProperties"][(LayersNo - 1)]["width"];
			ThisLayerObj.CurrentHeight = _root.sideProperties[SideNumer8]["GraphicProperties"][(LayersNo - 1)]["height"];

			//Copy over a Hash of permissions this graphic
			ThisLayerObj.permissions = _root.sideProperties[SideNumer8]["GraphicProperties"][(LayersNo - 1)]["permissions"];

			//Record the orginal dimensions of the Graphic
			ThisLayerObj.OriginalHeight = _root.sideProperties[SideNumer8]["GraphicProperties"][(LayersNo - 1)]["originalheight"];
			ThisLayerObj.OriginalWidth = _root.sideProperties[SideNumer8]["GraphicProperties"][(LayersNo - 1)]["originalwidth"];


			//Rotate the Layer 
			RotateLayer(_root.sideProperties[SideNumer8]["Layers"][(LayersNo - 1)]["rotation"], ThisLayerObj);

			ThisLayerObj.ImageID = _root.sideProperties[SideNumer8]["GraphicProperties"][(LayersNo - 1)]["imageid"];
			ThisLayerObj.VectorImageId = _root.sideProperties[SideNumer8]["GraphicProperties"][(LayersNo - 1)]["VectorImageId"];
			

			//Load image off of server.
			_root.DownloadCheck.MarkNewDownload();

			//This script on the server is acutally gernating a flash file .  It gets the image for this file from a Binary column in the database.
			if(_root.TestMode){
				ImageDownloadURL = "http://localhost:82/draw_download_image_mc.php?imageid=" + ThisLayerObj.ImageID;
			}
			else{
				ImageDownloadURL = "ming/" + ThisLayerObj.ImageID + ".swf";
			}
			loadMovie(ImageDownloadURL,ThisLayerObj.ImageClip);


			ThisLayerObj.ImageType = "JPEG";
			ThisLayerObj.DownloadErrorCheck.gotoAndPlay(3);

		}
	}
	//Call whenever the aligment or coordinates change.  If we try to recalculate positions every time small rounding errors can occur every time the file is saved
	function LayerNeedsCoordinatesRecaclulated(LayerObj16){
		LayerObj16.UseOriginalCoordinates = false;
		
		// If this layer has a drop shadow... then the drop shadow needs it coordinates recalculates too
		if(CheckIfTextLayerHasShadow(LayerObj16)){
			shadowLayer16 = GetShadowTextLayerObj(LayerObj16);
			shadowLayer16.UseOriginalCoordinates = false;
		}
	
	}
	
	// Returns True of false depending on whether the text layer has a shadow associated with it
	function CheckIfTextLayerHasShadow(LayerObj87){

		// If all of the layers have not been created yet... then we don't want say that a layer has a shadow
		// The reason it may fail when we are creating the layers for the first time and a layer has been created... but its shadow has not been created yet.
		// This may happen when the parent layer tries to Update Text or change a font.
		// It is not necessary to have the shadow copy-cat exactly during creation time because it will get created on its own during creation time
		if(!_root.AllLayersAttached)
			return false;


		if(LayerObj87.shadow_level_link == "" || LayerObj87.shadow_level_link == null || LayerObj87.shadow_level_link == "null")
			return false;
		else
			return true;
	}
	
	function CheckIfTextLayerIsAShadowToAnotherLayer(LayerObj87){
	
		// We need to find out what is "level" value in the (possible shadow layer object) 
		// Get the original clip depth that was recorded at layer creation time.
		PossibleShadowLayerLevel = LayerObj87.OriginalClipDepth;

		for(thisLayerCounter=0; thisLayerCounter < _root.sideProperties[_root.SideSelected]["Layers"].length; thisLayerCounter++){

			PossibleParentLayerObj = eval("TextClipLayer" + (thisLayerCounter + 1));

			if(PossibleParentLayerObj.shadow_level_link == PossibleShadowLayerLevel){			
				return true;
			}
		}
		return false;
	}
	
	// Returns a layer object, (that is the shadow) that corresponds to the Text layer that is being passed into the function
	function GetShadowTextLayerObj(LayerObj87){
	
		if(!CheckIfTextLayerHasShadow(LayerObj87))
			trace("Error in function GetShadowTextLayer, a shadow does not exist on this layer");
	

		for(thisLayerCounter=0; thisLayerCounter < _root.sideProperties[_root.SideSelected]["Layers"].length; thisLayerCounter++){

			if(_root.sideProperties[_root.SideSelected]["Layers"][thisLayerCounter]["level"] == LayerObj87.shadow_level_link){
				shadowTextLayerObj = eval("TextClipLayer" + (thisLayerCounter + 1));
				return shadowTextLayerObj;
			}
		}

		
		trace("Error in function GetShadowTextLayer, a matching shadow layer was not found.");
		return false;
	}
	

	// Will rotate, change coordinates, size, etc. for the shadow layer, corresponding to the layer being passed in.
	// If the Shadow layer has a higer level than the parent.  It will move the shadow down
	// Does not hurt to call this method on a text layer that does not have a shadow
	function RepositionShadowLayerAccordingToParent(LayerObj88){

		if(LayerObj88.LayerType != "text")
			return false;
		if(!CheckIfTextLayerHasShadow(LayerObj88))
			return false;

		ShadowLayerObj = GetShadowTextLayerObj(LayerObj88);
		
		// Make the rotation match
		ShadowLayerObj._rotation = LayerObj88._rotation;
		
		// Make the size match.
		ShadowLayerObj.TextSize = LayerObj88.TextSize;
		ShadowLayerObj.TextBox._xscale = LayerObj88.TextBox._xscale;
		ShadowLayerObj.TextBox._yscale = LayerObj88.TextBox._yscale;
		
		// Reposition Text layer to match parent
		ShadowDistance = LayerObj88.shadow_distance;
		ShadowLayerObj._y = LayerObj88._y + ShadowDistance * Math.sin(Math.PI/180 * (LayerObj88._rotation + LayerObj88.shadow_angle));
		ShadowLayerObj._x = LayerObj88._x + ShadowDistance * Math.cos(Math.PI/180 * (LayerObj88._rotation + LayerObj88.shadow_angle));

		// if the clip depth of the shadow is greater than parent... then swap them
		if(ShadowLayerObj.ClipDepth > LayerObj88.ClipDepth){
			_root.Layer_Text_DepthCounter_minimum--;
			ShadowLayerObj.swapDepths(_root.Layer_Text_DepthCounter_minimum);
			
			//Record the Depth level into a member variable of the Layer object.  I couldn't get MovieClip.getDepth() to work.. so I just keep track of the depth this way.
			ShadowLayerObj.ClipDepth = _root.Layer_Text_DepthCounter_minimum;
		}
		
		LayerNeedsCoordinatesRecaclulated(ShadowLayerObj);
		
		// If they open the Shadow Properties for a certain Text layer.... this will make sure that
		// The properties become sticky in case they want to apply the same Shadow style to another text layer.
		_root.ShadowDefaultColor = ShadowLayerObj.Color;
		_root.ShadowDefaultAngle = LayerObj88.shadow_angle;
		_root.ShadowDefaultDepth = LayerObj88.shadow_distance;

	}
	
	

	function DeleteLayer(LayerObj4){


		if(_root.ContentWindow.LayerTypeSelected == "text"){
			//If this TextLayer has a field name with it then it means there is an html box on the screen.  We need let the javascript know that we are deleting that field.
			//We use the field "field_order" to name the html boxes.
			if(LayerObj4.field_name != ""){
				if(!_root.TestMode)
					getURL("JavaScript:DeleteField('" +  LayerObj4.field_order + "')");
			}
		}

		//Get the target MC for the black boxes.
		HighlightBoxesObj = _root.GetHighlightButtonObjbyReference(LayerObj4);

		//Get the Layer number out of the Layer object.
		DeleteObjectLayerNo = LayerObj4.LayerNumber;

		//Tell the Global Array "Layers" that we have deleted this layer.
		_root.sideProperties[_root.SideSelected]["Layers"][(DeleteObjectLayerNo - 1)]["type"] = "deleted";


		// If we are deleting a text layer and the layer had a shadow... then remove the shadow too.
		if(_root.ContentWindow.LayerTypeSelected == "text" && CheckIfTextLayerHasShadow(LayerObj4)){
		
			ShadowLayerObj4 = GetShadowTextLayerObj(LayerObj4);
		
			//Get the Layer number out of the Layer object.
			ShadowLayerNumber = ShadowLayerObj4.LayerNumber;

			//Tell the Global Array "Layers" that we have deleted this layer.
			_root.sideProperties[_root.SideSelected]["Layers"][(ShadowLayerNumber - 1)]["type"] = "deleted";
		
			ShadowLayerObj4.removeMovieClip();
		}	
	
		//Get Rid of the Moive Clips;
		LayerObj4.removeMovieClip();
		HighlightBoxesObj.removeMovieClip();	


	}
	function RemoveBoundries(){
		//The reason for doing the check of the eval is to ensure it can safely be called anytime.  EX.   if function is called before any boundaries have been drawn.

		if(eval("TopBoundry")){
			TopBoundry.removeMovieClip();
		}
		if(eval("BottomBoundry")){
			BottomBoundry.removeMovieClip();
		}
		if(eval("LeftBoundry")){
			LeftBoundry.removeMovieClip();
		}
		if(eval("RightBoundry")){
			RightBoundry.removeMovieClip();
		}

	}

	function DrawBoundries(){

		_root.DottedLineDepth = 60000;

		ContentWidth2 = _root.sideProperties[_root.SideSelected]["contentwidth"];
		ContentHeight2 = _root.sideProperties[_root.SideSelected]["contentheight"];


		Num_horiz_folds = _root.sideProperties[_root.SideSelected]["folds_horiz"];
		Num_vert_folds = _root.sideProperties[_root.SideSelected]["folds_vert"];


		_root.DottedLineDepth++;
		attachMovie("DottedLineHorizontal", "TopBoundry", _root.DottedLineDepth);
		_root.DottedLineDepth++;
		attachMovie("DottedLineHorizontal", "BottomBoundry", _root.DottedLineDepth);
		_root.DottedLineDepth++;
		attachMovie("DottedLineVertical", "LeftBoundry", _root.DottedLineDepth);
		_root.DottedLineDepth++;
		attachMovie("DottedLineVertical", "RightBoundry", _root.DottedLineDepth);

		//This accounts for the over-hanging edges of the boundary lines
		LineWidth = TopBoundry.DottedLine._width;

		//Set the X/Y coordinates of the boundary lines bases on the width of the content area.  The content area is defined in the  XML document.
		TopBoundry._x = -(ContentWidth2/2) - LineWidth / 2;
		TopBoundry._y = -(ContentHeight2/2);
		BottomBoundry._x = -(ContentWidth2/2) - LineWidth / 2;
		BottomBoundry._y = ContentHeight2/2;
		LeftBoundry._x = -(ContentWidth2/2);
		LeftBoundry._y = -(ContentHeight2/2) - LineWidth / 2;
		RightBoundry._x = ContentWidth2/2;
		RightBoundry._y = -(ContentHeight2/2) - LineWidth / 2;


		//This will give us a way to figure out what spacing the folding lines should be placed with regards to the content width.
		//We add 1 and then divide it into 1 so that for a piece with 2 fold, the folding line will fall at 33.3% ect. ect.
		//Take that ratio and multiply by the content width or height to reveal the spacing.
		HorizontalFoldSpacing = 1/(Num_vert_folds + 1) * ContentWidth2;
		verticalFoldSpacing = 1/(Num_horiz_folds + 1) * ContentHeight2;

		for(k=0; k<Num_horiz_folds; k++){
			_root.DottedLineDepth++;
			attachMovie("DottedLineHorizontal", "HorizFold" + k, _root.DottedLineDepth);

			FoldLineObj = eval("HorizFold" + k);
			FoldLineObj._y = TopBoundry._y + (k+1) * verticalFoldSpacing;
			FoldLineObj._x = TopBoundry._x;

			CreateBoundryWidth(ContentWidth2, "HorizFold" + k, "horizontal");
		}
		for(k=0; k<Num_vert_folds; k++){
			_root.DottedLineDepth++;
			attachMovie("DottedLineVertical", "VertFold" + k, _root.DottedLineDepth);

			FoldLineObj = eval("VertFold" + k);
			FoldLineObj._x = LeftBoundry._x + (k+1) * HorizontalFoldSpacing;
			FoldLineObj._y = LeftBoundry._y;

			CreateBoundryWidth(ContentHeight2, "VertFold" + k, "vertical");
		}


		//Set the widths of lines.  Parameter 1 is the length, Parameter 2 is the Name of the target.
		CreateBoundryWidth(ContentWidth2, "TopBoundry", "horizontal");
		CreateBoundryWidth(ContentWidth2, "BottomBoundry", "horizontal");
		CreateBoundryWidth(ContentHeight2, "LeftBoundry", "vertical");
		CreateBoundryWidth(ContentHeight2, "RightBoundry", "vertical");

	}
	function CreateBoundryWidth(TheWidth, BoundryName, TheDirection){

		BoundryObj = eval(BoundryName);

		//TopBoundry.DottedLine._width was abitrarily selected.  We just want the width of an individual dotted line element.
		Repetitions = TheWidth / TopBoundry.DottedLine._width + 1;

		for(i=0; i < Repetitions; i++){
			_root.DottedLineDepth++;
			BoundryObj.DottedLine.duplicateMovieClip(("DottedLine" + i), _root.DottedLineDepth);
			DottedLineObj = eval(BoundryName + ".DottedLine" + i);

			if(TheDirection == "horizontal"){
				DottedLineObj._x = BoundryObj.DottedLine._x + i * BoundryObj.DottedLine._width;
			}
			else if(TheDirection == "vertical"){
				DottedLineObj._y = BoundryObj.DottedLine._y + i * BoundryObj.DottedLine._height;
			}
		}

	}


	// _root.GuideMargin is set within the URL of loading this movie (within the HTML)  .swf?GuideMargin=4&something=blah
	// If it is set to 0 then don't display a guide
	function DisplayGuide(){

		var ThisGuideMargin = int(_root.GuideMargin);
		
		if(ThisGuideMargin == 0){
			return;
		}
		

		ContentWidth2 = _root.sideProperties[_root.SideSelected]["contentwidth"];
		ContentHeight2 = _root.sideProperties[_root.SideSelected]["contentheight"];


		//Get the first Guide MC. We may duplicate this many times
		_root.DottedLineDepth++;
		attachMovie("GuideBleed", "GuideMaster", _root.DottedLineDepth);


		//Find out the "natural" Width of the guide MC.
		//If the canvas area is really large, we don't want to stretch or distort the dotted guide marks too much
		//We can keep duplicating at its natural width to cover the area... and then just scale what is left of the remainder.
		var NaturalGuideWidth = GuideMaster._width;


		//Found out how many Horizontal and Vertical guide objects are needed to fill the canvas
		var HorizontalGuideLines = Math.ceil(ContentWidth2 / NaturalGuideWidth);
		var VerticalGuideLines = Math.ceil(ContentHeight2 / NaturalGuideWidth);


		//Find out what the width of each Horizontal or Vertical Guide needs to be in order to make up the remainder
		//The end points need to exactly touch each other
		//The bleed area has longer height and width than the safe area
		var HorizontalGuideWidth_Bleed = NaturalGuideWidth * (ContentWidth2 + ThisGuideMargin * 2) / (NaturalGuideWidth * HorizontalGuideLines);
		var VerticalGuideHeight_Bleed = NaturalGuideWidth * (ContentHeight2 + ThisGuideMargin * 2) / (NaturalGuideWidth * VerticalGuideLines);
		
		var HorizontalGuideWidth_Safe = NaturalGuideWidth * (ContentWidth2 - ThisGuideMargin * 2) / (NaturalGuideWidth * HorizontalGuideLines);
		var VerticalGuideHeight_Safe = NaturalGuideWidth * (ContentHeight2 - ThisGuideMargin * 2) / (NaturalGuideWidth * VerticalGuideLines);



		DrawBleedOrSafeLine("bleed_top", GuideMaster, (-(ContentWidth2/2) - ThisGuideMargin), (-(ContentHeight2/2) - ThisGuideMargin), HorizontalGuideWidth_Bleed, HorizontalGuideLines, false);
		DrawBleedOrSafeLine("bleed_bottom", GuideMaster, (-(ContentWidth2/2) - ThisGuideMargin), ((ContentHeight2/2) + ThisGuideMargin), HorizontalGuideWidth_Bleed, HorizontalGuideLines, false);
		DrawBleedOrSafeLine("bleed_left", GuideMaster, (-(ContentWidth2/2) - ThisGuideMargin), (-(ContentHeight2/2) - ThisGuideMargin), VerticalGuideHeight_Bleed, VerticalGuideLines, true);
		DrawBleedOrSafeLine("bleed_right", GuideMaster, ((ContentWidth2/2) + ThisGuideMargin), (-(ContentHeight2/2) - ThisGuideMargin), VerticalGuideHeight_Bleed, VerticalGuideLines, true);

		DrawBleedOrSafeLine("safe_top", GuideMaster, (-(ContentWidth2/2) + ThisGuideMargin), (-(ContentHeight2/2) + ThisGuideMargin), HorizontalGuideWidth_Safe, HorizontalGuideLines, false);
		DrawBleedOrSafeLine("safe_bottom", GuideMaster, (-(ContentWidth2/2) + ThisGuideMargin), ((ContentHeight2/2) - ThisGuideMargin), HorizontalGuideWidth_Safe, HorizontalGuideLines, false);
		DrawBleedOrSafeLine("safe_left", GuideMaster, (-(ContentWidth2/2) + ThisGuideMargin), (-(ContentHeight2/2) + ThisGuideMargin), VerticalGuideHeight_Safe, VerticalGuideLines, true);
		DrawBleedOrSafeLine("safe_right", GuideMaster, ((ContentWidth2/2) - ThisGuideMargin), (-(ContentHeight2/2) + ThisGuideMargin), VerticalGuideHeight_Safe, VerticalGuideLines, true);

		
		//Since we were just using this to duplicate... we can get rid of it.
		removeMovieClip( GuideMaster ); 

	}
	
	
	//Will replicate our MasterLineMC as many times as specified in the ReplicationNumber
	//If VerticalFlag then the object will be rotated by 90 degrees and we will increment the Y variable instead of X
	//LineDescription should be a string like "bleed_top"  or "safe_bottom"
	function DrawBleedOrSafeLine(LineDescription, MasterLineMC, StartX, StartY, LineWidth, ReplicationNumber, VerticalFlag){
	
		for(j=0; j<ReplicationNumber; j++){
		
			//Replicate off of the master
			_root.DottedLineDepth++;
			duplicateMovieClip (MasterLineMC, LineDescription + "-" + j, _root.DottedLineDepth);
			var ThiLineObj = eval(LineDescription + "-" + j);
			
			ThiLineObj._width = LineWidth;
			
			if(VerticalFlag){
				ThiLineObj._x = StartX;
				ThiLineObj._y = StartY + j * LineWidth;
				ThiLineObj._rotation = 90;
			}
			else{
				ThiLineObj._x = StartX + j * LineWidth;
				ThiLineObj._y = StartY;
			}
		}
	}



	function DisplaySide(SideNumber){

		//Save the status of the Side we are viewing.  If we are not viewing a side yet it still won't hurt to call this function.
		//Saves all text properties ect. back out to the global array.
		SaveState();


		//This will get ride of all of the layers,boundaries, and folds on the screen.  It doesn't hurt to call these functions if there aren't any yet.
		RemoveLayers();
		RemoveBoundries();
		RemoveFolds();

		// The Since we are displaying a new side... all of the layers will need to be attatched.
		_root.AllLayersAttached = false;

		//Highlight the tab that we selected
		_root.HighlightSideTab(SideNumber);

		//Now inform the Global varialbe that we are on a new side.
		_root.SideSelected = SideNumber;


		//Reset the Stage Area
		//------------------------------
		InitialZoom = _root.sideProperties[SideNumber]["initialzoom"];
		_xscale = InitialZoom;
		_yscale = InitialZoom;

		//Pass in a value to the zoom function.  Keep in mind that is formula directly offsets the formula used to calculate zoom in the function since zoom ranges between 10% and 220%
		ZoomFunctionReverse = (((InitialZoom - 10)/2) * 0.01);
		_root.Zoom.doSlide(ZoomFunctionReverse);
		_root.Zoom.ZoomHandle._x = _root.Zoom.line._width * ZoomFunctionReverse + Zoom.line._x;  //Set the handle in the correct spot.

		//Center the Content Window based on the dummy MC placed on the main stage.
		_x = _root.CenterPoint._x;
		_y = _root.CenterPoint._y;
		
		//Set the Cavnas rotation
		_root.ContentWindow._rotation = _root.sideProperties[SideNumber]["rotatecanvas"];
		
		//Make the mouse pointers spin with the canvas... For resizing a graphic... etc.
		_root.MousePointersRotated._rotation = _root.sideProperties[SideNumber]["rotatecanvas"];

		//Set the slider handles in the middle
		_root.sliderY.handle._x = (_root.sliderY.line._width/2) + _root.sliderY.line._x;
		_root.sliderX.handle._x = (_root.sliderX.line._width/2) + _root.sliderX.line._x;

		ThisSideContentWidth = _root.sideProperties[SideNumber]["contentwidth"];
		ThisSideContentHeight = _root.sideProperties[SideNumber]["contentheight"];

		//Set some global varialbes that we can use for determining maximum scrolling limits.  Set at 500% to the width of the actual content to give a nice buffer.
		_root.TotalScrollableArea_Width = ThisSideContentWidth * 5;
		_root.TotalScrollableArea_Height = ThisSideContentHeight * 5;

		//All 5 depth counters are used for the same puropose.  We need to keep track of a layer stacking...
		//This variables will contain the maximum and minimum values for all layers on the document.  We may "Brint to Front" or "Send to Back" and need to always go 1 higher or 1 lower.
		//Leave a huge margin in between because as people click on a layer it will be brought to the front and increment the layer counter
		//If the user clicked on "Bring to Front" 5,000 times then we could have a problem becuase it would erase another movie clip.  I don't think this will ever happen though.
		_root.Layer_Graphic_DepthCounter = 5000;
		_root.Layer_Graphic_DepthCounter_minimum = 5000;
		_root.Layer_VectorGraphic_DepthCounter = 9000;
		_root.Layer_VectorGraphic_DepthCounter_minimum = 9000;
		_root.Layer_Text_DepthCounter = 10000;
		_root.Layer_Text_DepthCounter_minimum = 10000;
		_root.HighlightDepthCounter = 20000;  

		//Hide the Rotation MC handle
		_root.Rotate.RotateHandle._visible = false;

		//Hide the color picker
		_root.ColorPicker._visible = false;

		//Record how many colors are allowed on this side.  A value of 0 means unlimited colors.
		_root.NumberOfColors = _root.sideProperties[SideNumber]["color_definitions"]["number_of_colors"];
		_root.ColorDefinitions = new Array();

		//Store the Color definitions into a 2d array.  The 1st key corresponds to the color ID.
		for(i=1; i <= _root.NumberOfColors; i++){

			//Remember that the color ID may not always match the number of the loop iteration we are on.
			ColorID = _root.sideProperties[SideNumber]["color_definitions"][i]["id"];

			//This is the array we will use for the rest of the processing on this side.  When we want to save the state of this side we will put the information back into _root.SideProperties
			_root.ColorDefinitions[ColorID] = new Array();
			_root.ColorDefinitions[ColorID]["hexcode"] = _root.sideProperties[SideNumber]["color_definitions"][i]["hexcode"];
		}

		//Posistion the background image.
		BackgroundImage._x = _root.sideProperties[SideNumber]["background_x"];
		BackgroundImage._y = _root.sideProperties[SideNumber]["background_y"];

		//Hide the background image Layer until it has finished downloading.
		BackgroundImage._visible = false;

		//The background image is set to 0 for blank templates.  Don't try to download an image.
		if(_root.sideProperties[SideNumber]["backgroundimage"] != 0){

			//Load the Image from the database.  The ID for the image comes from the XML doc.
			_root.DownloadCheck.MarkNewDownload();
			BackgroundImageDownloadURL = "ming/" + _root.sideProperties[SideNumber]["backgroundimage"] + ".swf";
			loadMovie(BackgroundImageDownloadURL, BackgroundImage.ImageClip);
		}
		else{
			// Since there is not a background image... just use the default Movie Clip (which is a white background)
			BackgroundImage.ImageClip._width = _root.sideProperties[SideNumber]["contentwidth"];
			BackgroundImage.ImageClip._height = _root.sideProperties[SideNumber]["contentheight"];
			
			BackgroundImage._x = -1 * (_root.sideProperties[SideNumber]["contentwidth"] / 2);
			BackgroundImage._y = -1 * (_root.sideProperties[SideNumber]["contentheight"] / 2);

			//Make the background layer visible since it doesn't require downloading.
			BackgroundImage._visible = true;
		}
		
		
		// Not all Artworks have a Marker Image
		// Marker Images are used for bleed/safe lines on irregular shaped canvas areas, such as double-sided envelopes
		MarkerImage._visible = false;
		if(_root.sideProperties[SideNumber]["has_marker_image"]){
			MarkerImage._x = _root.sideProperties[SideNumber]["marker_image_x"];
			MarkerImage._y = _root.sideProperties[SideNumber]["marker_image_y"];
			
			//Load the Image from the database.  The ID for the image comes from the XML doc.
			_root.DownloadCheck.MarkNewDownload();
			MarkerImageDownloadURL = "ming/" + _root.sideProperties[SideNumber]["marker_image_id"] + ".swf";
			loadMovie(MarkerImageDownloadURL, MarkerImage.ImageClip);
			
			// Make sure that the Marker Image is at the very top of everything.
			this.MarkerImage.swapDepths(200001);
		}
		

		//Make sure the Control Modules start off disabled.
		_root.DisableText.gotoAndPlay(2);
		_root.DisableRotate.gotoAndPlay(2);
		_root.DisableStacking.gotoAndPlay(2);


		//Clear out the Undo/Redo History
		_root.ClearUndoHistory();
		_root.ShowUndoRedoButtons();


		// Show the bleed/safe lines and boundaries...
		// but only if we have indicated to do so some the XML file
		// Sometimes we don't want to show the natural boundaries around the canvas... such as if we have a Marker Image for non-rectangular images.
		if(_root.sideProperties[SideNumber]["show_boundary"] != "no"){
			
			//Draw the dashes around the content area.
			DrawBoundries();
		}
		


		//Create the layers
		//---------------------------------
		for(j=1; j <= _root.sideProperties[SideNumber]["Layers"].length; j++){
 			_root.ContentWindow.MakeNewLayer(SideNumber,j);
		}



		//Let Javascript know that all layers have been attached.
		_root.NotifyLayersAttached.gotoAndPlay(2);
		

		if(_root.sideProperties[SideNumber]["show_boundary"] != "no"){
			// Bleed Safe Lines
			_root.ContentWindow.DisplayGuide();
		}


		// Now Download the Shapes that may go on top of the artwork
		// For example, an envelop may have a Rectangle box where the window is meant to be punched out.
		loadMovie(("draw_shapes_layer.php?sidenumber=" + SideNumber), this.shapesMC);
		this.shapesMC._x = -(_root.sideProperties[SideNumber]["contentwidth"] / 2);
		this.shapesMC._y = -(_root.sideProperties[SideNumber]["contentheight"] / 2);
		
		// Make sure that the shapes stay on top of everything else.
		this.shapesMC.swapDepths(200000);

		// In case there aren't any layers... we still need to let the browser know that all downloads are complete. This MC will call a javascript notification.
		if(_root.sideProperties[SideNumber]["Layers"].length == 0)
			_root.DownloadCheck.MarkDownloadComplete();


	}


	
	//Search and Replace function for any string.
	function StringReplace (origStr, searchStr, replaceStr){
		var tempStr ="";
		var startIndex =0;
		if (searchStr ==""){
			return origStr;
		}
		if (origStr.indexOf(searchStr) != -1){
			while ((searchIndex = origStr.indexOf(searchStr,startIndex)) != -1){
				tempStr +=origStr.substring(startIndex,searchIndex);
				tempStr +=replaceStr;
				startIndex =searchIndex +searchStr.length;
			}
			return tempStr +origStr.substring(startIndex);
		}
		else {
    		return origStr;
  		}
	}

	function RemoveFolds(){

		//We are blindly deleting these fold lines.  We figure there could be a maximum of 10.
		for(k=0; k< 10; k++){
			FoldLineObj = eval("_root.ContentWindow.HorizFold" + k);
			if(FoldLineObj){
				FoldLineObj.removeMovieClip();
			}

			FoldLineObj = eval("_root.ContentWindow.VertFold" + k);
			if(FoldLineObj){
				FoldLineObj.removeMovieClip();
			}
		}

	}

	//This function will remove all of the layers on the current side that we are viewing.
	function RemoveLayers(){

		//Only attempt to remove Layers if we have previously selected a side to view.
		if(_root.SideSelected != "none"){

			//Loop through all of the layers on this side and get rid of them.
			for(j=0; j < _root.sideProperties[_root.SideSelected]["Layers"].length; j++){

				HighlightBoxesObj22 = eval("HighLightLayer" + (j+1));
				HighlightBoxesObj22.removeMovieClip();

				if(_root.sideProperties[_root.SideSelected]["Layers"][j]["type"] == "text"){
					ThisLayerObj22 = eval("TextClipLayer" + (j+1));  //Add one to the layer number becuase naming is "1 based".
 				}
				else if(_root.sideProperties[_root.SideSelected]["Layers"][j]["type"] == "graphic"){
					ThisLayerObj22 = eval("GraphicClipLayer" + (j+1));  //Add one to the layer number becuase naming is "1 based".
 				}
				ThisLayerObj22.removeMovieClip();
			}
		}
	}

	//Will save all of the properties for all layers, text, graphics ect. for the current side we are viewing.
	//This function should be called anytime we switch sides or we are about to save the XML document out.
	function SaveState(){

		//Only attempt to save the status if we have already generated a view for the side.
		if(_root.SideSelected != "none"){

			//If any layers are selected then unselect them.
			UnSelectCurrentLayer();
			
			//Record the canvas rotation if this is an administrator 
			if(_root.UserType == "admin"){
				_root.sideProperties[_root.SideSelected]["rotatecanvas"] = this._rotation;
			}
			
			

			//Save the state of the colors.  Get the information out of the 2d array that we created when generating this side. 
			for(i=1; i <= _root.NumberOfColors; i++){
				ColorID = _root.sideProperties[_root.SideSelected]["color_definitions"][i]["id"];

				//It is possible for an administrator to add extra colors. 
				//The following statment will not be evaluated for entries in which extra colors have been added.  The admin changed the number of colors in  _root.sideProperties  .... not _root.NumberOfColors which is how this current loop is iterating.
				//After an administrator performs a save... the Function DisplaySide will automatically be called which re-initializes all color values and  _root.NumberOfColors based upon information in _root.sideProperties
				_root.sideProperties[_root.SideSelected]["color_definitions"][i]["hexcode"] = _root.ColorDefinitions[ColorID]["hexcode"];
					
			}

			//Loop through all of the layers on this side.
			for(j=0; j < _root.sideProperties[_root.SideSelected]["Layers"].length; j++){


				if(_root.sideProperties[_root.SideSelected]["Layers"][j]["type"] == "text"){
					ThisLayerObj22 = eval("TextClipLayer" + (j+1));  //Add one to the layer number becuase naming is "1 based".

					//Put all of the data back into the global variables.  We are getting the information from the current Layer Object we are looping on.
					_root.sideProperties[_root.SideSelected]["TextProperties"][j]["align"] = ThisLayerObj22.TextAlignment;
					_root.sideProperties[_root.SideSelected]["TextProperties"][j]["italics"] = ThisLayerObj22.TextItalic;
					_root.sideProperties[_root.SideSelected]["TextProperties"][j]["bold"] = ThisLayerObj22.TextBold;
					_root.sideProperties[_root.SideSelected]["TextProperties"][j]["underline"] = ThisLayerObj22.TextUnderline;
					_root.sideProperties[_root.SideSelected]["TextProperties"][j]["font"] = ThisLayerObj22.TextFont;
					_root.sideProperties[_root.SideSelected]["TextProperties"][j]["size"] = ThisLayerObj22.TextSize;
					_root.sideProperties[_root.SideSelected]["TextProperties"][j]["message"] = ThisLayerObj22.MainTextHolder;
					_root.sideProperties[_root.SideSelected]["TextProperties"][j]["color"] = ThisLayerObj22.Color;
					_root.sideProperties[_root.SideSelected]["TextProperties"][j]["field_name"] = ThisLayerObj22.field_name;

	
					//We only want the field order to be controlled by the administrator.
					//On Step 3 of the website the field_names will be displayed in the same order as the Text Layer stacking on the artwork template.
					if(_root.UserType == "admin"){
						_root.sideProperties[_root.SideSelected]["TextProperties"][j]["field_order"] = ThisLayerObj22.ClipDepth;
					}
					

					// In case the depths/levels have changed.  We need to update our link to the shadow
					if(CheckIfTextLayerHasShadow(ThisLayerObj22)){
						ShadowLayerObj = GetShadowTextLayerObj(ThisLayerObj22);
						_root.sideProperties[_root.SideSelected]["TextProperties"][j]["shadow_level_link"] = ShadowLayerObj.ClipDepth;
						
						// Because we are saving to our Side Properties array, we need to make sure that the OriginalClipDepth matches this array at all time.
						// On Proof mode, we may not be leaving this screen.
						ThisLayerObj22.shadow_level_link = ShadowLayerObj.ClipDepth;
						ShadowLayerObj.OriginalClipDepth = ShadowLayerObj.ClipDepth;
					}
					else{
						_root.sideProperties[_root.SideSelected]["TextProperties"][j]["shadow_level_link"] = "";
					}
					
					_root.sideProperties[_root.SideSelected]["TextProperties"][j]["shadow_distance"] = ThisLayerObj22.shadow_distance;
					_root.sideProperties[_root.SideSelected]["TextProperties"][j]["shadow_angle"] = ThisLayerObj22.shadow_angle;


					//Convert the X & Y coordinates.. Returns as an array
					ConertedCoordArr = GetTextLayerSaveCoordinates(ThisLayerObj22);
					SaveCoord_X = ConertedCoordArr[0];
					SaveCoord_Y = ConertedCoordArr[1];
					
					
					//Round to 3 decimal Places.  Text layers that are right/Left aligned at angles may have many decimals.
					_root.sideProperties[_root.SideSelected]["Layers"][j]["xcoordinate"] = Math.round(SaveCoord_X * 1000) / 1000;
					_root.sideProperties[_root.SideSelected]["Layers"][j]["ycoordinate"] = Math.round(SaveCoord_Y * 1000) / 1000;

 				}
				else if(_root.sideProperties[_root.SideSelected]["Layers"][j]["type"] == "graphic"){
					ThisLayerObj22 = eval("GraphicClipLayer" + (j+1));  //Add one to the layer number becuase naming is "1 based".

					_root.sideProperties[_root.SideSelected]["GraphicProperties"][j]["height"] = ThisLayerObj22.ImageClip._height;
					_root.sideProperties[_root.SideSelected]["GraphicProperties"][j]["width"] = ThisLayerObj22.ImageClip._width;

					_root.sideProperties[_root.SideSelected]["GraphicProperties"][j]["originalheight"] = ThisLayerObj22.OriginalHeight;
					_root.sideProperties[_root.SideSelected]["GraphicProperties"][j]["originalwidth"] = ThisLayerObj22.OriginalWidth; 

					_root.sideProperties[_root.SideSelected]["GraphicProperties"][j]["imageid"]  = ThisLayerObj22.ImageID;
					_root.sideProperties[_root.SideSelected]["GraphicProperties"][j]["VectorImageId"]  = ThisLayerObj22.VectorImageId;
					

					_root.sideProperties[_root.SideSelected]["Layers"][j]["xcoordinate"] = ThisLayerObj22._x;
					_root.sideProperties[_root.SideSelected]["Layers"][j]["ycoordinate"] = ThisLayerObj22._y;

					//This is not a new graphic anymore.  It has already been uploaded.
					_root.sideProperties[_root.SideSelected]["Layers"][j]["newgraphic"] = "no";

				}
				//Get rid of any decimal places in the layers's rotation.  They were only needed for exact placement of the rotation slider handle.
				_root.sideProperties[_root.SideSelected]["Layers"][j]["rotation"] = int(ThisLayerObj22._rotation);
				_root.sideProperties[_root.SideSelected]["Layers"][j]["level"] = ThisLayerObj22.ClipDepth;

			}
		}
	}

	
	
	
	//Returns an array with 2 elements.  1 the Xcoordinage, 2 the Y coordinate
	function GetTextLayerSaveCoordinates(LayerObj89){
	

		//This is a flag that is set to FALSE anytime a text layer is a moved, rotated, or has the alignment changed.
		//If the flag is true then we want to keep the original coordinates that came from the XML document.  Otherwise some precision will be lost converting back and forth
		if(LayerObj89.UseOriginalCoordinates){
			SaveCoord_X = LayerObj89.OriginalCoordinate_X;
			SaveCoord_Y = LayerObj89.OriginalCoordinate_Y;
		}
		else{

			//The purpose of these if clauses is to convert the coordinates.   Within this program the positioning point of the Text layer is always at the uppler left corner.
			//When we save the state of this artwork we want the X coordinates to be exactly in the center if the text is centered and to be at the top right corner if the text is right aligned.
			//This way when information goes back into the XML doucment the other programs do not need to calculate the width of the GIANT text box in any of their processes.  It is a good way to isolate the "Tricks" I needed to use in this flash program.
			//During the Layer creation it will convert coordinates back again.
			//The functionality of shifting the coordinates is basically the same as seen in the "update_text" function.  See the explanation there to gain an understanding of the Math.cos ect.. 

			//We also need to tranlate the padding  that exists within Text Boxes See MovieClip: "Layer Text" Frame: 1  Layer: "Help" for a better explanation.
			//Our Scaling is relative to the default font size of 8
			TextScaleFactor = LayerObj89.TextSize / 8;
			TextPadding = Math.round(2 * TextScaleFactor);

			//Find the vertical padding offset based on the angle.  This applies to all alignments
			//We also need to account for right and left aligned paddings in the equations below.
			//Add to Y and subtract from X becuase our registration point begins in the top-left hand corner.
			PaddingShift_X = -(Math.sin(Math.PI/180 * LayerObj89._rotation) * TextPadding);
			PaddingShift_Y = Math.cos(Math.PI/180 * LayerObj89._rotation) * TextPadding;

			if(LayerObj89.TextAlignment == "Center"){
				SaveCoord_X = PaddingShift_X + (LayerObj89.TextBox._width/2) * Math.cos(Math.PI/180 * LayerObj89._rotation) + LayerObj89._x;
				SaveCoord_Y = PaddingShift_Y + (LayerObj89.TextBox._width/2) * Math.sin(Math.PI/180 * LayerObj89._rotation) + LayerObj89._y;
			}
			else if(LayerObj89.TextAlignment == "Right"){
				SaveCoord_X = PaddingShift_X + (LayerObj89.TextBox._width - TextPadding) * Math.cos(Math.PI/180 * LayerObj89._rotation) + LayerObj89._x;
				SaveCoord_Y = PaddingShift_Y + (LayerObj89.TextBox._width - TextPadding) * Math.sin(Math.PI/180 * LayerObj89._rotation) + LayerObj89._y;
			}

			else{
				SaveCoord_X = PaddingShift_X + TextPadding * Math.cos(Math.PI/180 * LayerObj89._rotation) + LayerObj89._x;
				SaveCoord_Y = PaddingShift_Y + TextPadding * Math.sin(Math.PI/180 * LayerObj89._rotation) + LayerObj89._y;
			}
		}
		
		
		retArray = new Array();
		retArray[0] = SaveCoord_X;
		retArray[1] = SaveCoord_Y;
		
		return retArray;
	
	}
	
	//The default text size is 8.  So the scale of our layer is based from that.
	function GetFontScale(TheFontSize){
	
		return TheFontSize / 8 * 100;
	}
	
	
	//You should pass a graphic Layer into this function
	//It will try to see if the quality is being lost becuase the image is getting stretched to big.  
	//If the image is severely distorted then we don't care about warning the user becuase it may be a solid color block... or something that doesn't care about resoltuion
	//Only check on new graphic uploads.  We don't want to keep bugging them 
	function CheckForImageDPI(LayerObj79){
	
		//Only do this error checking on new graphics
		if(LayerObj79.NewGraphic == "no"){
			return;	
		}
		
		//We want to get a ratio of width vs. height on both the original dimensions and the the modified ones
		//If the ratios differ by 30% then we don't care about checking DPI
		OriginalDimRatio = LayerObj79.OriginalWidth / LayerObj79.OriginalHeight;
		NewDimRatio = LayerObj79.ImageClip._width / LayerObj79.ImageClip._height;
		
		//Now get a ratio between ratios
		var RR = OriginalDimRatio / NewDimRatio;
		
		//If the number equals 1 .. then it means they have not distorted the image.
		var ImageDistortion = 1 - RR;
		
		ImageDistortion = Math.abs(ImageDistortion * 100);
		
		if(ImageDistortion < 30){
		
			//Now get a ratio between the old with and new width... same with height... if either dimension has been stretched more than 30% then we will complain
			var WidthRatio = (LayerObj79.ImageClip._width - LayerObj79.OriginalWidth) / LayerObj79.OriginalWidth;
			var HeightRatio = (LayerObj79.ImageClip._height - LayerObj79.OriginalHeight) / LayerObj79.OriginalHeight;
			
			if(WidthRatio * 100 > 30  || HeightRatio * 100 > 30 ){
				_root.ErrorWarning.AlertMessage("This image may be stretched out too large.  If you need the image to appear at this size on the finished product then you may consider uploading a larger image (more pixels).  Submitting an order with a low resolution graphic may cause a delay.");	
			}
		}
	}
	

}

