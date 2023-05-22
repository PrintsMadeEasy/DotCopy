	//This will make sure that pressing the Enter key always updated the total
	function keyDown(k)
	{
	   var keycode = document.all ? event.keyCode : k.which;
	   if ((""+keycode)=="13") {
	   	//We take the focus away from the input field that is being edited by focusing on the total field
	   	//This may cause the onChange event to fire for the input field (which in turn will update the toal if a change has been made)
		document.forms["options"].TheTotal.focus();
	   }

	}
	document.onkeydown = keyDown;
	if ( document.layers ) {
	 document.captureEvents(Event.KEYDOWN);
	}



	function UpdateTotal(){


		//Everytime we recalculate the total we reinitialize this variable to whatever the original base price was.
		NewBasePrice = BasePrice;
		SubtotalDifference = 0;  //Subtotal Difference is allways 0 until someone adds something though selecting an option

		//Go through all of the varaible options
		for(var i = 1; i <=8; i++){
			var OptionREF = eval("OptionsArray" + i);

			//Make sure the option exists
			if(OptionREF[0]){
				var CurrentOptionalSelectionPrice = GetPriceValuesFromOptionNumber(i);

				//Make first level price changes for the Option that is selected
				var FirstLevelPriceChanges = GetFirstLevelPriceChangesForOption(CurrentOptionalSelectionPrice);
				ChangeBaseAndSubtotalPrice(FirstLevelPriceChanges);

				//Make prices changes for any quantity breaks associated with this choice
				UpdatePriceForChoiceQuantityBreak(CurrentOptionalSelectionPrice);

			}
		}

		UpdatePricesForQuantityBreaks();

		DisplayTotal(RoundWithDecimals((GetQuantitySelected() * NewBasePrice) + SubtotalDifference));
	}

	//This function updates the NewBasePrice Global variable.  It takes in a paramter
	//like "0.03^3.02" .. It always assumes that the change to the base price is stored before the carrort "^" sign.. subtotal is after
	function ChangeBaseAndSubtotalPrice(PriceModification){

		PriceModificationArr = new Array;
		PriceModificationArr = PriceModification.split("^");

		NewBasePrice += eval(PriceModificationArr[0]);
		SubtotalDifference += eval(PriceModificationArr[1]);
	}


	//This function returns a price modification string like "0.03^3.02"
	//It expects to find the string within the 2nd field (deliminated by pipe symbols)
	//The parameter passed into this function may look like "something|0.03^3.02|bla bla|bla bla"
	//The 3rd and 4rth fields may contain deeper price modification levels for quantity... but lets worry about those in other function calls.
	function GetFirstLevelPriceChangesForOption(OptionParameters){
		OptionParametersArr = new Array;
		OptionParametersArr = OptionParameters.split("|");
		return OptionParametersArr[1];
	}

	//This works similar to GetFirstLevelPriceChangesForOption exept we are looking into the 3rd column instead
	//This field is further deliminated by commas... We are looking for a quantity greater or equal to the one that is currently selected.
	function UpdatePriceForChoiceQuantityBreak(OptionParameters){


		OptionParametersArr = new Array;
		OptionParametersArr = OptionParameters.split("|");

		var ChoiceQuantityBreaksStr = OptionParametersArr[2];

		if(ChoiceQuantityBreaksStr != ""){

			QuantityBreaksChoiceArr = new Array;
			QuantityBreaksChoiceArr = ChoiceQuantityBreaksStr.split(",");

			var baseQuantityChange = 0;
			var subQuantityChange = 0;

			//Now go through all of the Quantity Breaks and look for the highest amount which is less than or equal to the current quantity selected
			for(var j=0; j < QuantityBreaksChoiceArr.length; j++){

				var subBreaksArr = new Array;
				subBreaksArr = QuantityBreaksChoiceArr[j].split("^");

				//Field 1) amount   Field 2) Base Price   Field 3) Subotal change
				if(GetQuantitySelected() >= eval(subBreaksArr[0])){
					baseQuantityChange = eval(subBreaksArr[1]);
					subQuantityChange = eval(subBreaksArr[2]);
				}
				else{
					break;
				}
			}

			NewBasePrice += baseQuantityChange;
			SubtotalDifference += subQuantityChange;

		}
	}


	function RoundWithDecimals(n) {

  		var s = "" + Math.round(n * 100) / 100;
  		var i = s.indexOf('.');

  		if (i < 0){
  			return s + ".00";
  		}

  		var t = s.substring(0, i + 1) + s.substring(i + 1, i + 3);

  		if (i + 2 == s.length){
  			t += "0";
  		}

  		return t;
	}

	//Returns the element of the Options Array... for the choice that is selected
	function GetPriceValuesFromOptionNumber(OptionNumber){
			var OptionREF = eval("OptionsArray" + OptionNumber);
			var OptionIndex = OptionsArraySelected[OptionNumber];

			return OptionREF[OptionIndex];
	}
	function UpdatePricesForQuantityBreaks(){

			var baseQuantityChange = 0;
			var subQuantityChange = 0;

			//Go through all of the Quantity Breaks and look for the highest amount which is less than or equal to the current quantity selected
			for(var j=0; j < QuantityBreaksArr.length; j++){
				var QuantityBreaksComponentsArr = new Array;

				QuantityBreaksComponentsArr = QuantityBreaksArr[j].split("|");

				//Field 1) amount   Field 2) Base Price   Field 3) Subotal change
				if(GetQuantitySelected() >= eval(QuantityBreaksComponentsArr[0])){

					var subBreaksArr = new Array;
					subBreaksArr = QuantityBreaksComponentsArr[1].split("^");

					baseQuantityChange = eval(subBreaksArr[0]);
					subQuantityChange = eval(subBreaksArr[1]);
				}
				else{
					break;
				}
			}

			NewBasePrice += baseQuantityChange;
			SubtotalDifference += subQuantityChange;
	}





	//This function sets the global variables that are needed for "total" calculations
	function SetOptionValue(OptionName, ChoiceName, UpdateTheTotal){

		var OptionFound = false;
		var ChoiceFound = false;

		for(var i=1; i<9; i++){
			var OptionREF = eval("OptionsArray" + i);

			if(OptionREF[0]){

				//First index index in options array is always the Option Name
				if(OptionREF[0].toUpperCase() == OptionName.toUpperCase()){

					OptionFound = true;

					for(var j=1; j < OptionREF.length; j++){
						var ChoiceArr = OptionREF[j].split("|");
						if(ChoiceArr[0].toUpperCase() == ChoiceName.toUpperCase()){
							ChoiceFound = true;

							//Global variable for holding the currently selected value.
							OptionsArraySelected[i] = j;
							break;

						}
					}
				}
			}
		}

		if(!OptionFound){
			var ErrorMessage = "Option " + OptionName + " has an error for product " + TheProducdID +" . This error report was just sent to the webmaster.  Visit us tommorow and the problem will be fixed.";
			SendError(ErrorMessage)
		}
		else if(!ChoiceFound){
			var ErrorMessage = "Option " + OptionName + " has an error in choice " + ChoiceName + " for product " + TheProducdID +"  . This error report was just sent to the webmaster.  Visit us tommorow and the problem will be fixed.";
			SendError(ErrorMessage)
		}
		else {
			if(UpdateTheTotal){
				UpdateTotal();
			}
		}

	}

	function SendError(ErrMessage){
		ErrMessage = ErrMessage.replace(/\s+/, "+");
		document.location = "./error_webmaster.php?message=" + ErrMessage;
	}

	function SetQuantity(QuantityAmount, UpdateTheTotal){

		quantitySelected = QuantityAmount;

		if(UpdateTheTotal){
			UpdateTotal();
		}
	}


	function GetQuantitySelected(){

		return quantitySelected;
	}

	//Returns the name of the Choice that is selected for the given option
	function GetChoiceSelected(OptionNameParam){

		//Go through all of the varaible options
		for(var i = 1; i <=8; i++){
			var OptionREF = eval("OptionsArray" + i);

			//Make sure the option exists
			if(OptionREF[0] == OptionNameParam){

				var SelectedChoiceInfoStr = GetPriceValuesFromOptionNumber(i);

				//Get everything before the first Pipe Symbol... That should be the option that is currently seleced
				SelectedChoiceInfoArr = SelectedChoiceInfoStr.split("|");

				return SelectedChoiceInfoArr[0];
			}
		}
		alert("Problem in Function GetChoiceSelected... \n\ncan not find the matching option name for " + OptionNameParam)

	}




	function SetCheckedRadioValue(DomPath, NewValue){
		var RadioObj = eval(DomPath)
		if (RadioObj){
			var i=0;
			var found = false;
			for(i=0; i < (RadioObj.length); i++){
				if(NewValue.toUpperCase() == RadioObj[i].value.toUpperCase()){
					RadioObj[i].checked = true;
					found = true;
				}
				else{
					RadioObj[i].checked = false;
				}
			}
			if(!found){
				alert("Error\n\nThe value for the radio button " + NewValue + " could not be found");
			}
		}
		else{
			alert("Error\n\nThe DOM path does not exist.\n\n" + DomPath);
		}
	}


	//Builds the link with Name/Value Pairs and then transfers them to the URL specified in the parameter
	function UploadOptions(ActionURL){

		document.forms['SubmitOptions'].reset();  //Reset the form incase someone hit the back button.

		document.forms['SubmitOptions'].quantity.value = GetQuantitySelected();


		for(var i=1; i<9; i++){
			var OptionREF = eval("OptionsArray" + i);

			if(OptionREF[0]){
				ChoiceDetailsArr = OptionREF[OptionsArraySelected[i]].split("|");

				//For the choice name
				var SubmitObj = eval("document.forms['SubmitOptions'].opt" + i);
				SubmitObj.value = ChoiceDetailsArr[0];

				//For the option name
				SubmitObj = eval("document.forms['SubmitOptions'].nopt" + i);
				SubmitObj.value = OptionREF[0];
			}
		}

		document.forms['SubmitOptions'].action = ActionURL;
		document.forms['SubmitOptions'].submit();
	}


	function VariableImageSuggestion(){

		alert("Variable Images require you to correlate a unique image to an entry in your Data File.\nSome possible applications...\n\n1) A mortgage lender could gather a database of photographs of customers' homes.\n\n2) You could save JPEG maps of your customers' address (from MapQuest, etc) and print\n     the map-image next to each Address.\n\n3) An automotive company could gather a database of images related to the\n     customers' make/model of their cars.\n\n4) There are many more applications, be creative!");

	}
