
function CheckAll(){
	//Get a reference to all checkboxes within open orders.
	ChkBoxList = GetProjectCheckBoxList();

	for(var i=0; i < ChkBoxList.length; i++){
		ChkBoxList[i].checked = true;
		HltRow(ChkBoxList[i]);  //Highlight the row
	}	
}

function checkboxes_mark(){

	var OpenOrdersFormObj = document.forms["CheckBoxQuery"];

	//Collect Variables from the query form
	var MaxProjectID = OpenOrdersFormObj.highorder.value;
	var MinProjectID = OpenOrdersFormObj.loworder.value;
	var ShipChkBoxlist = OpenOrdersFormObj.shipmentmethod;
	var StatusChkBoxlist = OpenOrdersFormObj.statustype;
	var MaxQuantity = OpenOrdersFormObj.highquantity.value;
	var MinQuantity = OpenOrdersFormObj.lowquantity.value;


	var CheckProjectNoRangeFlag = false;  //Will tell is if we should bother checking within the following loop.  This saves a little processor time on long lists.
	var CheckQuantityRangeFlag = false;

		//See if we are limiting to a range of order numbers
	if(MaxProjectID != "" || MinProjectID != ""){

		CheckProjectNoRangeFlag = true;

		if(MaxProjectID == ""){
			MaxProjectID = 100000000; //Set it really high so that it will never touch.
		}
		if(MinProjectID == ""){
			MinProjectID = 0;
		}
		if(MaxProjectID.search(/^\d+$/) == -1 || MinProjectID.search(/^\d+$/) == -1){
			alert("Invalid Project Range");
			return;
		}
		if(parseInt(MinProjectID) > parseInt(MaxProjectID)){
			alert("Invalid Project Range");
			return;
		}
	}

	//See if we are limiting to a range of quantities
	if(MaxQuantity != "" || MinQuantity != ""){

		CheckQuantityRangeFlag = true;

		if(MaxQuantity == ""){
			MaxQuantity = 100000000; //Set it really high so that it will never touch.
		}
		if(MinQuantity == ""){
			MinQuantity = 0;
		}
		
		var MaxQuanStr = MaxQuantity.toString();
		var MinQuanStr = MinQuantity.toString();
		
		if(MaxQuanStr.search(/^\d+$/) == -1 || MinQuanStr.search(/^\d+$/) == -1){
			alert("Invalid Quantity.  Only Numbers are permitted.");
			return;
		}
		
		
		MinQuantity = parseInt(MinQuantity);
		MaxQuantity = parseInt(MaxQuantity);
		
		if(MinQuantity > MaxQuantity){
			alert("Invalid Quantity Range");
			return;
		}
	}


	//Get a reference to all checkboxes within open orders.
	ChkBoxList = GetProjectCheckBoxList();

	for(var i=0; i < ChkBoxList.length; i++){

		//Check and make sure this project number is within range
		if(CheckProjectNoRangeFlag){

			//Must be just a numeric project ID
			ProjectIDcheckbox = eval(ChkBoxList[i].value);

			if(ProjectIDcheckbox < MinProjectID || ProjectIDcheckbox > MaxProjectID){
				continue;
			}
		}

		//Check and make sure this project quantity is within range
		if(CheckQuantityRangeFlag){

			if(ProjectQuantitiesArr[i] < MinQuantity || ProjectQuantitiesArr[i] > MaxQuantity){
				continue;
			}
		}


		//Skip this loop the shipping method doesn't match a selected value within the query box.
		if(!GetShippingCheckBoxFlag(ShipChkBoxlist, i)){
			continue;
		}

		//Skip this loop if the Order status doesn't match a selected value within the query box.
		if(!GetProjectStatusCheckBoxFlag(StatusChkBoxlist, i)){
			continue;
		}

		//Skip this loop if the Product Options don't match this project
		if(!GetProjectOptionsCheckBoxFlag(i)){
			continue;
		}

		ChkBoxList[i].checked = true;
		HltRow(ChkBoxList[i]);  //Highlight the row
	}

}

//Will mark all of the status in the query box... ToggleType is boolean
function AllStatusCheck(ToggleType){

	CheckBoxObj = document.forms["CheckBoxQuery"].statustype;
	for(var i=0; i < CheckBoxObj.length; i++){
		if(ToggleType){
			CheckBoxObj[i].checked = true;
		}
		else{
			CheckBoxObj[i].checked = false;
		}
	}
}

function GetProjectOptionsCheckBoxFlag(CheckBoxPos22){

	var ProductIDSelected = document.forms['CheckBoxQuery'].productid.value;

	//Always return true it no product has been selected.
	if(ProductIDSelected == "0"){
		return true;
	}
	else if(Prod_Opt_Arr[CheckBoxPos22][0] != ProductIDSelected){
		//This Product should not be chedked then because it doesnt match the product ID from the query box
		return false;
	}
	else{

		var SelectedOption1 = GetSelectedListBoxValue("document.forms['CheckBoxQuery'].FirstOption");
		var SelectedOption2 = GetSelectedListBoxValue("document.forms['CheckBoxQuery'].SecondOption");
		var SelectedOption3 = GetSelectedListBoxValue("document.forms['CheckBoxQuery'].ThirdOption");
		
		if(document.forms['CheckBoxQuery'].FirstOption.options.length > 0)
			var ProductOptionName1 = document.forms['CheckBoxQuery'].FirstOption.options[0].text;
		else
			var ProductOptionName1 = "";
		
		if(document.forms['CheckBoxQuery'].SecondOption.options.length > 0)	
			var ProductOptionName2 = document.forms['CheckBoxQuery'].SecondOption.options[0].text;
		else
			var ProductOptionName2 = "";
		
		if(document.forms['CheckBoxQuery'].ThirdOption.options.length > 0)		
			var ProductOptionName3 = document.forms['CheckBoxQuery'].ThirdOption.options[0].text;
		else
			var ProductOptionName3 = "";
		

		//The option match is comprised of the Option Name... followed by the greater than symbol, then the selected choice.
		if(SelectedOption1 != ""){
			var ProductOptionName1 = document.forms['CheckBoxQuery'].FirstOption.options[0].text;
			if(!CheckIfisInArray(Prod_Opt_Arr[CheckBoxPos22][1], (ProductOptionName1 + ">" + SelectedOption1))){
				return false;
			}
		}
		if(SelectedOption2 != ""){
			if(!CheckIfisInArray(Prod_Opt_Arr[CheckBoxPos22][1], (ProductOptionName2 + ">" + SelectedOption2))){
				return false;
			}
		}
		if(SelectedOption3 != ""){
			if(!CheckIfisInArray(Prod_Opt_Arr[CheckBoxPos22][1], (ProductOptionName3 + ">" + SelectedOption3))){
				return false;
			}
		}
		return true;
	}
}

function CheckIfisInArray(Haystack, Needle){
	
	for(j=0; j < Haystack.length; j++){	
		if(Haystack[j] == Needle){
			return true;
		}
	}
	return false;
}


//Returns the value of the radio button that has been clicked on
function GetCheckedRadioValue(DomPath){

	var RadioObj = eval(DomPath)
	if (RadioObj){
		var i=0;

		for(i=0; i < (RadioObj.length); i++){
			if(RadioObj[i].checked == true){
				return RadioObj[i].value;
			}
		}
		return "";
	}
	else{
		alert("Error\n\nThe DOM path does not exist.\n\n" + DomPath);
	}
}
//Returns the value of the radio button that has been clicked on
function GetSelectedListBoxValue(DomPath){

	var listBoxObj = eval(DomPath)
	if (listBoxObj){
		var i=0;

		for(i=0; i < (listBoxObj.options.length); i++){
			if(listBoxObj.options[i].selected == true){
				return listBoxObj.options[i].value;
			}
		}
		return "";
	}
	else{
		alert("Error\n\nThe DOM path does not exist.\n\n" + DomPath);
	}

}



function GetShippingCheckBoxFlag(ShipmentMethodChkBoxlist, CheckBoxPosition){

		var ShippingIncludeFlag = false;


		// Get a list of all Shipping methods (based upon the checkboxes in HTML)
		var shippingSearchArr = new Array();
		for(var q=0; q<ShipmentMethodChkBoxlist.length; q++)
			shippingSearchArr.push(ShipmentMethodChkBoxlist[q].value);


		for(var r=0; r<shippingSearchArr.length; r++){

			if(ShippingIncludeFlag)
				break;

			if(FindOutIfCheckBoxValueIsClicked(ShipmentMethodChkBoxlist, shippingSearchArr[r])){
				if(ShippingMethodsArr[CheckBoxPosition] == shippingSearchArr[r])
					ShippingIncludeFlag = true;
			}

		}

		return ShippingIncludeFlag;
}

function GetProjectStatusCheckBoxFlag(StatusChkBoxlist, CheckBoxPosition){

		var ProjectIncludeFlag = false;


		// Get a list of all Status Characters (based upon the checkboxes in HTML)
		var statusSearchArr = new Array();
		for(var q=0; q<StatusChkBoxlist.length; q++)
			statusSearchArr.push(StatusChkBoxlist[q].value);

		for(var b=0; b<statusSearchArr.length; b++)
			ProjectIncludeFlag = CheckOrderStatusForCheckbox(ProjectIncludeFlag, StatusChkBoxlist, CheckBoxPosition, statusSearchArr[b]);

		return ProjectIncludeFlag;
}
function CheckOrderStatusForCheckbox(ProjInclFlag, StatChkBoxlst, ChkBoxPos, StatusDescStr){
		//Only go to the trouble of checking in case it hasnt been set to true yet.  Once it is true it stays
		if(!ProjInclFlag){
			if(FindOutIfCheckBoxValueIsClicked(StatChkBoxlst, StatusDescStr)){
				if(ProjectStatusArr[ChkBoxPos] == StatusDescStr){
					return true;
				}
			}
		}

		//Otherise just return the same flag that came in.
		return ProjInclFlag;
}

//Returns true if the value in the check box list is checked.  Otherwise returns false
function FindOutIfCheckBoxValueIsClicked(CheckBoxObj, ChxBoxValue){
	for(var i=0; i < CheckBoxObj.length; i++){
		if(CheckBoxObj[i].checked == true){
			if(CheckBoxObj[i].value == ChxBoxValue){
				return true;
			}
		}
	}
	return false;
}

function checkboxes_clear(){

	ChkBoxList = GetProjectCheckBoxList();

	for(var i=0; i < ChkBoxList.length; i++){
		ChkBoxList[i].checked = false;

		HltRow(ChkBoxList[i]);  //Un Highlight the row
	}

}

function GetProjectNumbersFromCheckboxes(){

	var RetStr = "";

	ChkBoxList = GetProjectCheckBoxList();

	//Return the string with each project number separated by a pipe
	for(var i=0; i < ChkBoxList.length; i++){
		if(ChkBoxList[i].checked == true){
			RetStr += ChkBoxList[i].value + "|";
		}
	}

	return RetStr;
}

//Returns how many projects have been checked
function GetSelectedProjectCount(){

	var retVal = 0;
	ChkBoxList = GetProjectCheckBoxList();
	for(var i=0; i < ChkBoxList.length; i++){
		if(ChkBoxList[i].checked == true){
			retVal++;
		}
	}
	return retVal;
}

//We have a hidden input on the HTML to keep a single check box from not forming an array
function GetProjectCheckBoxList(){

	var retArray = new Array();
	ThisChkBoxList = document.forms["openorders"].chkbx;
	for(var i=0; i < ThisChkBoxList.length; i++){
		if(ThisChkBoxList[i].type == "checkbox"){
			retArray.push(ThisChkBoxList[i]);
		}
	}
	return retArray;
}


function emptyListBox( box ) {
	// Set each option to null thus removing it
	while ( box.options.length ) box.options[0] = null;
}

// This function assigns new drop down options to the given
// drop down box from the list of lists specified
function fillListBox( box, arr ) {

	// arr[0] holds the display text
	// arr[1] are the values
	for ( i = 0; i < arr[0].length; i++ ) {

		// Create a new drop down option with the
		// display text and value from arr
		option = new Option( arr[0][i], arr[1][i] );

		// Add to the end of the existing options
		box.options[box.length] = option;
	}

	// Preselect option 0
	box.selectedIndex=0;
}

//Will create the new pop up window
function NewBatchWindow(){

	document.forms["batchwindow"].projectlist.value = GetProjectNumbersFromCheckboxes();

	if(document.forms["batchwindow"].projectlist.value == ""){
		alert("No projects have been selected.")
		return false;
	}

	//Get a random number so that we can ensure a new pop up window always occurs
	var ran_number = Math.round(Math.random()*300);

	document.forms["batchwindow"].target = ran_number;
	document.forms["batchwindow"].submit();

}

function ClearAllProductOptions(){
	var OptionListBox1 = document.forms["CheckBoxQuery"].FirstOption;
	var OptionListBox2 = document.forms["CheckBoxQuery"].SecondOption;
	var OptionListBox3 = document.forms["CheckBoxQuery"].ThirdOption;

	//Clear out any of the old options
	emptyListBox(OptionListBox1 );
	emptyListBox(OptionListBox2 );
	emptyListBox(OptionListBox3 );
}






// Dynamically fill the Product Option boxes when the user switches Product IDs with an Ajax Call.
function ChangeProductOptions(ProductID) {
	


	var OptionListBox1 = document.forms["CheckBoxQuery"].FirstOption;
	var OptionListBox2 = document.forms["CheckBoxQuery"].SecondOption;
	var OptionListBox3 = document.forms["CheckBoxQuery"].ThirdOption;
	
	//Clear out any of the old options
	ClearAllProductOptions();

	// Load the Project using an Ajax Call
	var prL = new ProductLoader();
	function productLoadingErrorEvent() { alert("The Product Details could not be fetched from the server."); }
	function productLoadedEvent (productID){
		
		var productObj = prL.getProductObj(productID);
		
		var optionNamesArr = productObj.getOptionNames();
		
		//alert(optionNamesArr.length);
		for(var i=0; i<optionNamesArr.length; i++){
			
			var thisOptionName = optionNamesArr[i];
			var thisOptionAlias = productObj.getOptionAlias(thisOptionName);
			
			
			// The 2D array to hold our choices
			// Element 0 is the <option>Description</option>
			// Element 1 is the <option value="Value"></option>
			ProdOptionLists = new Array();
			ProdOptionLists[0] = new Array();
			ProdOptionLists[1] = new Array();
				
			// First Choice in the drop down is the Option Name
			ProdOptionLists[0].push(thisOptionAlias);
			ProdOptionLists[1].push("");
			
			
			// Get all of the Choices belonging to the Option.
			var choiceNamesArr = productObj.getChoiceNamesForOption(thisOptionName)
			
			for(var x=0; x<choiceNamesArr.length; x++){
				
				var thisChoiceName = choiceNamesArr[x];
				var thisChoiceAlias = productObj.getChoiceAlias(thisOptionName, thisChoiceName);
				
				ProdOptionLists[0].push(thisChoiceAlias);
				ProdOptionLists[1].push(thisChoiceAlias);
			}

			if(i==0)
				fillListBox( OptionListBox1,ProdOptionLists);
			else if(i==1)
				fillListBox( OptionListBox2,ProdOptionLists);
			else if(i==2)
				fillListBox( OptionListBox3,ProdOptionLists);
			
			// Don't list over 3 option drop downs because we haven't defined the HTML for them yet.
			if(i==3){
				break;
			}
		}
	}
	
	prL.attachProductLoadedGlobalEvent(productLoadedEvent, this); 
	prL.attatchProductLoadingErrorEvent(productLoadingErrorEvent, this); 
	

	prL.loadProductID(ProductID);

	
	// Remember the ProductID choice
	setJavascriptCookie("OpenOrderLastProductID", ProductID, 100);


}

function HltRow(checkbox) {
   if (document.getElementById) {
	var tr = eval("document.getElementById(\"TR" + checkbox.value + "\")");
   } 
   else {
	return;
   }
   
   if (tr.style) {
	if (checkbox.checked) {
		tr.style.backgroundColor = "#bbccdd";
	} 
	else {
		tr.style.backgroundColor = "#dddddd";
	}
   }
}

// row color should be like "#ddEEFF"
function changeRowColor(projectID, rowColorStr){

   if (document.getElementById) {
	var tr = eval("document.getElementById(\"TR" + projectID + "\")");
   } 
   else {
	return;
   }
   
   if (tr.style) {
	tr.style.backgroundColor = rowColorStr;
   }
}


function InitializeQueryForm(){
	setTimeout("GetProductIDsetting()",1000);
}

function GetProductIDsetting(){

	// Find out the last known setting of the ProductID.
	var lastProductID = getJavascriptCookie("OpenOrderLastProductID");	
	
	if(lastProductID != ""){
		document.forms['CheckBoxQuery'].productid.value = lastProductID;
		ChangeProductOptions(lastProductID);
		
	}
		
}








var lastProjectHistoryProjectNum = null;
function showProjH(projectNum, showFlag){

	var spanName = "projHist" + projectNum;
	var divName = "DprojHist" + projectNum;

	if(showFlag){
		lastProjectHistoryProjectNum = projectNum;
		
		// Set time out so that we don't make a bunch of requests to the server scrolling over links quickly.
		setTimeout("fetchAndDisplayProjectHistory(" + projectNum + ")",300);
	}
	else{
		lastProjectHistoryProjectNum = null;
		
		document.all(spanName).style.visibility = "hidden";
		document.all(spanName).innerHTML = "";
		document.all(divName).style.zIndex = 2;
	}

}




function fetchAndDisplayProjectHistory(projectNum){

	// Make sure that another link wasn't rolled over before this one displayed.
	if(projectNum != lastProjectHistoryProjectNum)
		return;

	
	var spanName = "projHist" + projectNum;
	var divName = "DprojHist" + projectNum;
	
	var htmlTableStart = '<table cellpadding="3" cellspacing="0" width="450"><tr><td bgcolor="#bbbbbb"><table cellpadding="0" cellspacing="0" width="100%"><tr><td bgcolor="#773333"><table cellpadding="5" cellspacing="1" width="100%">';
	
	var htmlTableEnd = '</table></td></tr></table></td></tr></table>';
	



	//Load the XML doc from the server.
	var xmlDoc=getXmlReqObj();
	xmlDoc.open("GET", "./ad_actions.php?action=GetProjectHistoryXML&projectOrderedID=" + projectNum, false);
	xmlDoc.send(null);
	var xmlResponseTxt = xmlDoc.responseText;

	var ServerResponse = getXMLdataFromTagName(xmlResponseTxt, "success");
	var ErrorDescription = getXMLdataFromTagName(xmlResponseTxt, "errormessage");
	

	var statusNotes = new Array();
	var statusNames = new Array();
	var statusDates = new Array();

	var xmlLinesArr = xmlResponseTxt.split("\n");

	var eventCounter = -1;

	for(var lineNo = 0; lineNo < xmlLinesArr.length; lineNo++)
	{
	
 	
		if(xmlLinesArr[lineNo].match(/<event/)){	
			eventCounter++;
		}
		else if(xmlLinesArr[lineNo].match(/<desc/)){
			statusNotes[eventCounter] = getXMLdataFromTagName(xmlLinesArr[lineNo], "desc");
		}
		else if(xmlLinesArr[lineNo].match(/<person/)){
			statusNames[eventCounter] = getXMLdataFromTagName(xmlLinesArr[lineNo], "person");
		}
		else if(xmlLinesArr[lineNo].match(/<date/)){
			statusDates[eventCounter] = getXMLdataFromTagName(xmlLinesArr[lineNo], "date");
		}
	}



	if(ServerResponse == "good"){
	
		var htmlForWindow = htmlTableStart;
		
		for(var i=0; i<=eventCounter; i++){
		
			htmlForWindow += "<tr>";
			htmlForWindow += '<td bgcolor="#fff3f3" class="ReallySmallBody">' + statusNotes[i] + '</td>';
			htmlForWindow += '<td bgcolor="#fff3f3" class="ReallySmallBody" nowrap>' + statusDates[i] + '</td>';
			htmlForWindow += '<td bgcolor="#fff3f3" class="ReallySmallBody" nowrap>By: ' + statusNames[i] + '</td>';
			htmlForWindow += "</tr>";
		}
		
		htmlForWindow += htmlTableEnd;
		
		
		document.all(spanName).innerHTML = htmlForWindow;
		document.all(spanName).style.visibility = "visible";
		document.all(divName).style.zIndex = 6000;
	}
	else if(ServerResponse == "bad"){

		document.all(spanName).innerHTML = htmlTableStart + '<tr><td bgcolor="#ffffff" class="Body">' + ErrorDescription + '</td></tr>' + htmlTableEnd;
		document.all(spanName).style.visibility = "visible";
	}
	else{
		alert("Could not fetch Project History for an unknown reason.");
	}

	



}
































var lastArtworkPreviewProjectNum = null;


function showArtPrev(projectNum, showFlag){

	if(showFlag){
		lastArtworkPreviewProjectNum = projectNum;
				
		// Set time out so that we don't make a bunch of requests to the server scrolling over links quickly.
		setTimeout("fetchAndDisplayArtPrev(" + projectNum + ", false)",400);
	}
	else{
		lastArtworkPreviewProjectNum = null;
	}
		

}

function hideArtworkPreview(projectNum){
		
	var spanName = "artwPreviewSpan" + projectNum;
	document.all(spanName).style.visibility = "hidden";
	document.all(spanName).innerHTML = "";
		

	// Because we might show previews in other places that don't have a row color they want changed.... so define this function on every page you need a row changed... leave the function empty if you don't want it changed.
	PossiblyChangeRowColorAfterHiding(projectNum);
	
	document.all("Pnum" + projectNum).style.zIndex = 1900;
}



var lastProjectID = null;

function fetchAndDisplayArtPrev(projectNum, forceUpdateFlag){

	// Make sure that another link wasn't rolled over before this one displayed.
	if(projectNum != lastArtworkPreviewProjectNum)
		return;

	
	var spanName = "";
	
	// Hide the last window (if it was open).
	if(lastProjectID != null){	
		hideArtworkPreview(lastProjectID);		
	}

	lastProjectID = projectNum;
	
	spanName = "artwPreviewSpan" + projectNum;
	var divName = "Pnum" + projectNum;
	
	
	// make the Y location of the Span fall exactly underneath the next line... some lines are thicker than others... so that is why we need to do this.
	var heightOfDiv = document.getElementById(divName).scrollHeight;
	document.getElementById(spanName).style.top = heightOfDiv + 6;


	
	
	var htmlTableStart = '<table cellpadding="0" cellspacing="0"><tr><td bgcolor="#333333"><table cellpadding="7" cellspacing="1"><tr><td bgcolor="#eeeeee" class="Body">';
	
	var htmlTableEnd = '</td></tr></table></td></tr></table>';
	
	// In case the server takes a while to respond... show them that something is happening.
	document.all(spanName).innerHTML = htmlTableStart + "<table cellpadding='10' cellspacing='0'><tr><td><font color='#990000'>Thinking...</font></td></tr></table>" + htmlTableEnd;
	document.all(spanName).style.visibility = "visible";
	document.all(divName).style.zIndex = 3000;
	document.all(spanName).style.zIndex = 4000;

	// Because we might show previews in other places that don't have a row color they want changed.... so define this function on every page you need a row changed... leave the function empty if you don't want it changed.
	PossiblyChangeRowColorWhileShowing(projectNum);
	

	document.all(spanName).style.filter = "";
	
	
	// Contacts the server with pipe delimeted file names... one file per image. 
	var artworkPreviewFileNames = getArtworkPreviewFileNames(projectNum, forceUpdateFlag);
	
	if(artworkPreviewFileNames == ""){
		// Make it try again in 2 seconds
		setTimeout("fetchAndDisplayArtPrev(" + projectNum + ", true)",1000);
		return;
	}
	

	if(artworkPreviewFileNames != ""){
	
		var previewImagesArr = artworkPreviewFileNames.split("\|");
		
		var imagesHTML = "";
		
		for(var i=0; i < previewImagesArr.length; i++){
		
			// If there is more than one preview image then put them in separate <td> columns
			if(imagesHTML != "")
				imagesHTML += "</td><td bgcolor='#eeeeee' class='body'>";
		
			imagesHTML += "<img onClick='hideArtworkPreview(" + projectNum + ")' border='0' src='." + previewImagesArr[i] + "'>";
		}
	
		document.all(spanName).innerHTML = htmlTableStart + imagesHTML + htmlTableEnd;
		
		// Make sure that the Artwork Preview table will sit on top of everything.  This is mainly affected by the Project History <span>
		document.all("Pnum" + projectNum).style.zIndex = 3000;
	}
	

}




function getArtworkPreviewFileNames(projectID, forceUpdateFlag){

	dateObj = new Date();
	var NoCache = dateObj.getTime();
	
	if(forceUpdateFlag)
		var forceUpdateParam = "&forceUpdate=true";
	else
		var forceUpdateParam = "";
		


	//Load the XML doc from the server.
	var xmlDoc=getXmlReqObj();
	xmlDoc.open("GET", "./ad_actions.php?action=GetArtworkPreviewImageNames&projectOrderedID=" + projectID + "&nocache=" + NoCache + forceUpdateParam, false);
	xmlDoc.send(null);
	var xmlResponseTxt = xmlDoc.responseText;

	var ServerResponse = getXMLdataFromTagName(xmlResponseTxt, "success");
	var ResponseString = getXMLdataFromTagName(xmlResponseTxt, "description");
	var ErrorDescription = getXMLdataFromTagName(xmlResponseTxt, "description");
	
	

	if(ServerResponse == "good"){
		return ResponseString;
	}
	else if(ServerResponse == "bad"){

		//Send a warning into the flash editor
		alert("Could not fetch Artwork Preview Images because...\n\n" + ErrorDescription);
	}
	else{
		alert("Could not fetch Artwork Preview Images for an unknown reason.");
	}
	
	return "";
}


function changeResultsPerPage(amount){
	document.location = "./ad_openorders.php?action=changeResultsPerPage&resultPerPage=" + amount;
}


var tempQueryFormHTML = null
function showQueryBox(displayFlag){

	// Shift the HTML out of the span the first time that this function gets called... and it put it into a globalVar
	if(tempQueryFormHTML == null){
		tempQueryFormHTML = document.all("queryFormHiddenLayer").innerHTML;
		document.all("queryFormHiddenLayer").innerHTML = "";
	}
	

	if(displayFlag){
		document.all("queryFormVisibleLayer").innerHTML = tempQueryFormHTML;
		document.all("queryFormShowLink").innerHTML = '<a href="javascript:showQueryBox(false);">Hide Query Form</a>';
	}
	else{
	
		// Record HTML from visible layer in case any changes where made to checkboxes, etc.
		tempQueryFormHTML = document.all("queryFormVisibleLayer").innerHTML;
		
		document.all("queryFormVisibleLayer").innerHTML = "&nbsp;"
		document.all("queryFormShowLink").innerHTML = '<a href="javascript:showQueryBox(true);">Show Query Form</a>';
		

	}

}




// --------------  Begin Code to have Artwork previews follow the mouse. -------------- //



var fadeOutXcounter = 0;
var originalXoffset = null;

function followMouse(evt) {

	// this is a little ugly... but our document.onMouseMove event stole the event away from our 3rd party DHTML drop down menu... just forward the event accross.
	if(typeof(dqm__handleMousemove) !== "undefined")
		dqm__handleMousemove(evt);

	if (!document.getElementById)
		return;
	

	if(lastProjectID == null)
		return;
		
	if(fadeOutXcounter != 0)
		return
		
	if (!evt)
		evt = window.event; 
	
	var divName = "Pnum" + lastProjectID;
	var spanName = "artwPreviewSpan" + lastProjectID;	
	var spanObj = document.getElementById(spanName).style; 
	
	if(evt.clientX < 70)
		fadeOutPreview(lastProjectID)
		
	
	var divXlocation = document.getElementById(divName).offsetLeft;
		
	// The first time we show the DHTML window we want to record the native position of the span... then after that it will always follow the mouse... that is to keep it from jumping when you first move over it.
	if(originalXoffset == null){
		
		var currentLeftPosition = spanObj.left;
		currentLeftPosition = parseInt(currentLeftPosition.replace("/px/", ""));
		originalXoffset = currentLeftPosition;
		
		// take into consideration how far away the mouse if from the Parent Div when it is first loaded.
		originalXoffset -= (evt.clientX - divXlocation);
		
	}
	
	var newSpanX =  parseInt(evt.clientX - divXlocation);

	spanObj.left = (newSpanX + originalXoffset) + 'px';

}


function fadeOutPreview(projectNum){

	var spanName = "artwPreviewSpan" + projectNum;
	var spanObj = document.getElementById(spanName);
	
	fadeOutXcounter += 50;
	
	var currentLeftPosition = spanObj.style.left;
	
	currentLeftPosition = parseInt(currentLeftPosition.replace("/px/", ""));
	
	spanObj.style.left = currentLeftPosition - fadeOutXcounter + 'px';
	
	spanObj.style.filter = "progid:DXImageTransform.Microsoft.MotionBlur(Strength=70,Direction=90)"
	
	if(fadeOutXcounter > 250){
		fadeOutXcounter = 0;
		hideArtworkPreview(projectNum);
	}
	else{
		setTimeout("fadeOutPreview("+ projectNum + ")", 50);
	}


}







