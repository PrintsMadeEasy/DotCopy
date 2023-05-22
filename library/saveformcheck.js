
 //Pass in an Alert Message if we have already encountered an error before calling this function.  Pass in "" if there are no errors yet.
 //Pass in the form number that we should check.  The first form on the page is Form "0"
function save(AlertMessage, FormNum)
{
	//## Set this to 0 if you want to turn client Side Validation off throughout the site ##//
	var ClientSideValidation = 1;

	var ErrorsEncountered = 0;
	var imageObject = "";
	var HTMLerrorDescription = "";
	var DropDownLength = 0;
	var ValidationLength = 0;

	if (AlertMessage != "")
		ErrorsEncountered++;

	if(ClientSideValidation){

		if (!(typeof validationNameDropDown == 'undefined'))
			DropDownLength = validationNameDropDown.length;


		for(var i=0; i<DropDownLength; i++){

			var DropDownValue = eval("document.forms["+FormNum+"]." + validationNameDropDown[i] + ".value");

			/* Reset all of the red Error Images on the page to be transparent */
			imageObject = eval('document.images.' + "img" + validationNameDropDown[i])
			if(imageObject)						//Make sure we dont crash in case there is no image there.
				imageObject.src = "./images/transparent.gif";

			if(DropDownValue == validationValueDropDown[i]){
				++ErrorsEncountered;

				//Flip the Image in the HTML so that users see a Red arrow on the item that is messed up.
				imageObject = eval('document.images.' + "img" + validationNameDropDown[i])
				if(imageObject)
					imageObject.src = "./images/error_arrow.gif";

				AlertMessage += ErrorsEncountered + ") " + validationMessageDropDown[i] + "<br><br>";
			}
		}

		if (!(typeof validationName == 'undefined'))
			ValidationLength = validationName.length;

		for(var i=0; i<ValidationLength; i++){

			var TextFieldObj = eval("document.forms["+FormNum+"]." + validationName[i]);

			var TextFieldInfo = TextFieldObj.value

			//Trim all of the white spaces on the form.
			TextFieldInfo = TextFieldInfo.replace(/^\s+/, "");
			TextFieldInfo = TextFieldInfo.replace(/\s+$/, "");
			TextFieldObj.value = TextFieldInfo;

			/* Reset all of the red Error Images on the page to be transparent */
			imageObject = eval('document.images.' + "img" + validationName[i])
			if(imageObject)						//Make sure we dont crash in case there is no image there.
				imageObject.src = "./images/transparent.gif";

			if(TextFieldInfo.search(validationRegEx[i])== -1){
				++ErrorsEncountered;

				//Flip the Image in the HTML so that users see a Red arrow on the item that is messed up.
				imageObject = eval('document.images.' + "img" + validationName[i])
				if(imageObject)
					imageObject.src = "./images/error_arrow.gif";

				AlertMessage += ErrorsEncountered + ") " + validationMessage[i] + "<br><br>";
			}
		}
	}

	if (AlertMessage != ""){

		//convert <br> html characters to line breaks;
		AlertMessage = AlertMessage.replace(/<br>/g, "\n");
		AlertMessage = AlertMessage.replace(/&amp;/g, "&");

		AlertMessage = "Please fix the following errors before continuing...\n ====================================== \n\n" + AlertMessage;

		return AlertMessage;
	}

	else{

		eval("document.forms["+FormNum+"].submit()");

		return "";
	}


}


function CheckState(UserState){

	UserState = UserState.toUpperCase();
	if(UserState == "PR" || UserState == "GU" || UserState == "VI"){
		return true;
	}
	else{
		return CheckShippingState(UserState);
	}
}

function CheckShippingState(UserState){

	UserState = UserState.toUpperCase();
	if(UserState != "AL" && UserState != "AK" && UserState != "AS"
		&& UserState != "AZ" && UserState != "AR" && UserState != "CA" && UserState != "CO"
		&& UserState != "CT" && UserState != "DE" && UserState != "DC"
		&& UserState != "FL" && UserState != "GA" && UserState != "HI"
		&& UserState != "ID" && UserState != "IL" && UserState != "IN" && UserState != "IA"
		&& UserState != "KS" && UserState != "KY" && UserState != "LA" && UserState != "ME"
		&& UserState != "MD" && UserState != "MA" && UserState != "MI"
		&& UserState != "MN" && UserState != "MS" && UserState != "MO" && UserState != "MT"
		&& UserState != "NE" && UserState != "NV" && UserState != "NH" && UserState != "NJ"
		&& UserState != "NM" && UserState != "NY" && UserState != "NC" && UserState != "ND"
		&& UserState != "MP" && UserState != "OH" && UserState != "OK" && UserState != "OR"
		&& UserState != "PA" && UserState != "RI"
		&& UserState != "SC" && UserState != "SD" && UserState != "TN" && UserState != "TX"
		&& UserState != "UT" && UserState != "VT" && UserState != "VA"
		&& UserState != "WA" && UserState != "WV" && UserState != "WI" && UserState != "WY"){

		return false;
	}
	else{
		return true;
	}
}



/*  -- For Canada

UserState != "AB" && UserState != "BC" && UserState != "CA" && UserState != "MB"
		&& UserState != "NB" && UserState != "NF" && UserState != "NS" && UserState != "NT"
		&& UserState != "ON" && UserState != "PE" && UserState != "QC" && UserState != "SK"
		&& UserState != "YT" &&

*/