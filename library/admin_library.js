
function getXMLdataFromTagName(xmlDataStr, xmlTagName)
{
	
	var regExObj = new RegExp("<"+xmlTagName+">(.*)</"+xmlTagName+">", "g");
	var returnMatch = xmlDataStr.match(regExObj);
	
	if(returnMatch){
		var returnStr = unHtmlChars(returnMatch[0]);
		
		// Start Tag and White Space
		returnStr = returnStr.replace(/^(\s|\t|\r|\n)*\<\w+(\s+\w+=\s*(\"|\')(\w|\s|\d)*(\"|\'))*\s*\>/, "");

		// End Tag and White Space
		returnStr = returnStr.replace(/\<\/\w+\>(\s|\t|\r|\n)*$/, "");
		
		return returnStr;
	}
	
	return "";

}

function unHtmlChars(str) 
{

	var escAmpRegEx = /&amp;/g;
	var escLtRegEx = /&lt;/g;
	var escGtRegEx = /&gt;/g;
	var quotRegEx = /&quot;/g;
	var aposRegEx = /&apos;/g;
	var aposEnRegEx = /&#039;/g;

	str = str.replace(escAmpRegEx, "&");
	str = str.replace(escLtRegEx, "<");
	str = str.replace(escGtRegEx, ">");
	str = str.replace(quotRegEx, "\"");
	str = str.replace(aposRegEx, "'");
	str = str.replace(aposEnRegEx, "'");

	return str;
}



function getXmlReqObj()
{

	var retXmlObj = null;


	if(window.XMLHttpRequest) 
	{
		
		try { 
			retXmlObj= new XMLHttpRequest(); 
		} 
	
		catch(e) 
		{ 
			retXmlObj= null; 
		}
	
	} 
	
	// Modern IE browsers now support new XMLHttpRequest() natively... so  no point in maintaing these version numbers any further.
	else if(window.ActiveXObject)
	{
	
		try { 
			retXmlObj= new ActiveXObject('Msxml2.XMLHTTP.3.0'); 
		} 
		catch(e) 
		{
			try { 
				retXmlObj= new ActiveXObject('Msxml2.XMLHTTP'); 
			} 
			catch(e) 
			{
				try { 
					retXmlObj= new ActiveXObject('Microsoft.XMLHTTP'); 
				} 
				catch(e) 
				{ 
					retXmlObj= null; 
				}
			} 
		}
	}
	
	return retXmlObj;

}

	
	
	function ShowMessageRevisions(messageID, refreshParent){
	
		if(refreshParent)
			var TheAddress = "./ad_message_show_revisions.php?refreshParent=true&messageid=" + messageID
		else
			var TheAddress = "./ad_message_show_revisions.php?messageid=" + messageID
			
		newWindow = window.open(TheAddress, "ShowMessageRevisions", "height=400,width=395,directories=no,location=no,menubar=no,scrollbars=yes,status=no,toolbar=no,resizable=no");
		newWindow.focus();
	}

	function AddMessageRevision(messageID, refreshParent){
	
		if(refreshParent)
			var TheAddress = "./ad_message_add_revision.php?refreshParent=true&messageid=" + messageID
		else
			var TheAddress = "./ad_message_add_revision.php?messageid=" + messageID
			
		newWindow = window.open(TheAddress, "AddMessageRevision", "height=350,width=395,directories=no,location=no,menubar=no,scrollbars=no,status=yes,toolbar=no,resizable=no");
		newWindow.focus();
	}

	function MakeNewTask(AttachedTo, RefID, refreshParent){
	
		if(refreshParent)
			var TheAddress = "./ad_task_create.php?refreshParent=true&linkdesc=" + AttachedTo + "&taskref=" + RefID
		else
			var TheAddress = "./ad_task_create.php?linkdesc=" + AttachedTo + "&taskref=" + RefID
			
		newWindow = window.open(TheAddress, "NewTask", "height=385,width=395,directories=no,location=no,menubar=no,scrollbars=yes,status=no,toolbar=no,resizable=no");
		newWindow.focus();
	}

	function SendInternalMessage(AttachedTo, RefID, doNotRefreshFlag){
	
		if(doNotRefreshFlag)
			var TheAddress = "./ad_message_create.php?doNotRefresh=true&attachedto=" + AttachedTo + "&refid=" + RefID;
		else
			var TheAddress = "./ad_message_create.php?attachedto=" + AttachedTo + "&refid=" + RefID;
			
		newWindow = window.open(TheAddress, "NewMessage", "height=640,width=460,directories=no,location=no,menubar=no,scrollbars=no,status=no,toolbar=no,resizable=no");
		newWindow.focus();
	}

	function OpenMessageAttachmentPopup(messageID, doNotRefreshFlag){
		
		if(doNotRefreshFlag)
			var TheAddress = "./ad_message_attachments.php?refreshParent=true&command=list&msgid=" + messageID
		else
			var TheAddress = "./ad_message_attachments.php?command=list&msgid=" + messageID
	
		newWindow = window.open(TheAddress, "MessageAttachment", "height=300,width=600,directories=no,location=no,menubar=no,scrollbars=yes,status=no,toolbar=no,resizable=yes");
		newWindow.focus();
	}

	function OpenEmailNotifyDetailPicturePopup(pictureID,width,height,doNotRefreshFlag){
		
		if(doNotRefreshFlag)
			var TheAddress = "./ad_emailNotifyDetailPicture.php?refreshParent=true&view=detail&pictureid=" + pictureID
		else
			var TheAddress = "./ad_emailNotifyDetailPicture.php?view=detail&pictureid=" + pictureID
	
		height += 20; width  += 20;
		 
		newWindow = window.open(TheAddress, "Picture_" + pictureID, "height=" + height + ",width=" + width + ",directories=no,location=no,menubar=no,scrollbars=yes,status=no,toolbar=no,resizable=no");
		newWindow.focus();
	}


	function ReplyToInternalMessage(ThreadID){
		var TheAddress = "./ad_message_reply.php?threadid=" + ThreadID;
		newWindow = window.open(TheAddress, "ReplyToMessage", "height=570,width=480,directories=no,location=no,menubar=no,scrollbars=yes,status=no,toolbar=no,resizable=no");
		newWindow.focus();
	}

	function AdminEditOptions(ProjectID, ProjectComplete, ReturnURLencoded){

		if(ProjectComplete){
			alert("You can't modify options when the project is canceled or completed.");
			return;
		}


		var TransferURL = "./edit_options.html?vars=ProjectView:ordered^ProjectID:" + ProjectID;
		newWindow22 = window.open(TransferURL, "editoptions", "height=550,width=470,directories=no,location=no,menubar=no,scrollbars=yes,status=yes,toolbar=no,resizable=yes");
		newWindow22.focus();
	}

	function ViewStatusHistory(OrderID, ProjectID){
		var StatusHistoryAddress = './ad_statushistory.php?projectid=' + ProjectID + '&orderno='+ OrderID + '%20-%20' + ProjectID;
		newWindow = window.open(StatusHistoryAddress, "StatusHistory", "height=320,width=430,directories=no,location=no,menubar=no,scrollbars=yes,status=no,toolbar=no,resizable=no");
		newWindow.moveTo((self.screenLeft + self.screen.width/4),(self.screenTop + self.screen.height/4));
		newWindow.focus();
	}

	function CannedMessages(){
		var CannedAddress = './ad_cannedmsg.php';
		newWindow = window.open(CannedAddress, "cannedmessages", "height=500,width=700,directories=no,location=no,menubar=no,scrollbars=yes,status=no,toolbar=no,resizable=yes");
		newWindow.focus();
	}

	//Returns the value of the radio button that has been clicked on
	function GetRadioButtonValue(DomPath){

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
			alert("Error\n\nThe DOM path does not exist in the function GetRadioButtonValue.\n\n" + DomPath);
		}
	}

	function SetRadioButtonValue(DomPath, NewValue){
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

	function SetDropDownListValue(DomPath, NewValue){
		var ListObj = eval(DomPath)
		if (ListObj){
			var i=0;
			var found = false;
			for(i=0; i < (ListObj.length); i++){
				if(NewValue.toUpperCase() == ListObj[i].value.toUpperCase()){
					ListObj[i].selected = true;
					found = true;
				}
				else{
					ListObj[i].selected = false;
				}
			}
			if(!found){
				alert("Error\n\nThe value for the Select List " + NewValue + " could not be found");
			}
		}
		else{
			alert("Error\n\nThe DOM path does not exist.\n\n" + DomPath);
		}
	}

	function CustomerMemo(CustomerID, refreshParent){
		
		var refreshParentParm = "";
		
		if(refreshParent)
			refreshParentParm = "yes";
			
		var custMemoURL = "./ad_customermemos.php?refreshParent=" + refreshParentParm + "&customerid=" + CustomerID;
		
		newWindow = window.open(custMemoURL, "memo", "height=420,width=600,directories=no,location=no,menubar=no,scrollbars=yes,status=yes,toolbar=no,resizable=yes");
		newWindow.focus();
	}

	function OrderInNewWindow(OrderID){
			
		var orderIDurl = "./ad_order.php?orderno=" + OrderID;
		
		newWindow = window.open(orderIDurl, "OrderID", "height=880,width=810,directories=no,location=no,menubar=no,scrollbars=yes,status=yes,toolbar=no,resizable=yes");
		newWindow.focus();
		newWindow.moveTo(0,0);
	}
	
	function ShowIPaddress(ipAddress){
		var ipAddressMap = 'http://lookupcrap.com/' + ipAddress;
		newWindow = window.open(ipAddressMap, "lookupcrap", "height=700,width=800,directories=no,location=no,menubar=no,scrollbars=yes,status=yes,toolbar=no,resizable=yes");

	}
	
	function setJavascriptCookie(cookieName,cookieValue,nDays){	

		 var today = new Date();
		 var expire = new Date();
		 if (nDays==null || nDays==0) 
		 	nDays=1;
		 expire.setTime(today.getTime() + 3600000*24*nDays);
		 document.cookie = cookieName+"="+escape(cookieValue)
				 + ";expires="+expire.toGMTString();

	}

	
	// Returns blank string if no cookie is found.
	function getJavascriptCookie(name){
		var search = name+"=";
		if(document.cookie.length > 0){
			var offset = document.cookie.indexOf(search);
			if(offset != -1){
				offset+= search.length;
				var end = document.cookie.indexOf(";",offset);
				if(end == -1)
					end = document.cookie.length;

				return document.cookie.substring(offset,end);
			}
		}
		return "";
	}
	
	







	var lastCustInfoUniqueID = null;
	var custInfoCurrentlyShowingFlag = false;
	function CustInf(uniqueRowID, customerID, showFlag){

		var spanName = "Scust" + uniqueRowID;
		var divName = "Dcust" + uniqueRowID



		if(showFlag){
			lastCustInfoUniqueID = uniqueRowID;

			// Set time out so that we don't make a bunch of requests to the server scrolling over links quickly.
			setTimeout("fetchAndDisplayUserData(" + uniqueRowID + ", " + customerID + ")",500);
		}
		else{
			
			// it can take a while for the browser to find a Div object by name... so don't move the zindexes just by rolling your mouse over before the window has had time to display.
			if(custInfoCurrentlyShowingFlag){

				document.all(spanName).style.visibility = "hidden";
				document.all(spanName).innerHTML = ""

				document.all(spanName).style.zIndex = 1;
				document.all(divName).style.zIndex = 2;
			}
			
			lastCustInfoUniqueID = null;
			custInfoCurrentlyShowingFlag = false;
		}

	}




	function fetchAndDisplayUserData(uniqueRowID, customerID){

		// Make sure that another link wasn't rolled over before this one displayed.
		if(uniqueRowID != lastCustInfoUniqueID){
			custInfoCurrentlyShowingFlag = false;
			return;
		}
		
		custInfoCurrentlyShowingFlag = true;

		var spanName = "Scust" + uniqueRowID;
		var divName = "Dcust" + uniqueRowID;

		var htmlTableStart = '<table cellpadding="3" cellspacing="0" width="350"><tr><td bgcolor="#bbbbbb"><table cellpadding="0" cellspacing="0" width="100%"><tr><td bgcolor="#773333"><table cellpadding="5" cellspacing="1" width="100%"><tr><td class="SmallBody" style="background-color:#FFFFFF; background-image: url(./images/admin/arrival_time_back.png);">';

		var htmlTableEnd = '</td></tr></table></td></tr></table></td></tr></table>';


		
		//Load the XML doc from the server.
		var xmlDoc=getXmlReqObj();
		xmlDoc.open("GET", "./ad_actions.php?action=GetCustomerData&customerUserID=" + customerID, false);
		xmlDoc.send(null);
		var xmlResponseTxt = xmlDoc.responseText;
		
		var successValue = getXMLdataFromTagName(xmlResponseTxt, "success");
		var statisticsStr = getXMLdataFromTagName(xmlResponseTxt, "statistics");


		if(successValue == "good"){

			var htmlForWindow = htmlTableStart;
			htmlForWindow += statisticsStr;
			htmlForWindow += htmlTableEnd;


			document.all(spanName).innerHTML = htmlForWindow;
			document.all(spanName).style.visibility = "visible";
			document.all(divName).style.zIndex = 8001;
		}
		else{
			alert("Could not fetch User Data for an unknown reason.");
		}


	}
	
	
	

	
	

	function TasksShowEarlyReminders(showEarlyFlag, retURL){

		if(showEarlyFlag)
			document.location = "./ad_actions.php?action=TasksShowBeforeReminder&showFlag=true&returl=" + escape(retURL);
		else
			document.location = "./ad_actions.php?action=TasksShowBeforeReminder&showFlag=false&returl=" + escape(retURL);

	}


	function ShowCSfromUser(userID, byDaysOld){

		if(byDaysOld == 0)
			byDaysOld = "";
		else
			byDaysOld = byDaysOld.toString();

		var csAddress = "./ad_cs_home.php?view=search&bydaysold=" + byDaysOld + "&bykeywords=U" + userID;

		newWindow = window.open(csAddress, "CustID", "height=880,width=810,directories=no,location=no,menubar=no,scrollbars=yes,status=yes,toolbar=no,resizable=yes");
		newWindow.focus();
		newWindow.moveTo(0,0);

	}


	function NewCSfromUser(userID){

		var csAddress = "./ad_actions.php?action=newcsitem&returl=" + escape("ad_cs_home.php?view=my") + "&customerID=" + userID;

		newWindow = window.open(csAddress, "CustID", "height=880,width=810,directories=no,location=no,menubar=no,scrollbars=yes,status=yes,toolbar=no,resizable=yes");
		newWindow.focus();
		newWindow.moveTo(0,0);

	}


	function Cust(userID){

		newWindow = window.open(("ad_users_orders.php?byuser=" + userID), "CustID", "height=880,width=810,directories=no,location=no,menubar=no,scrollbars=yes,status=yes,toolbar=no,resizable=yes");
		newWindow.focus();
		newWindow.moveTo(0,0);
	}
	function CustInfo(userID){

		newWindow = window.open(("ad_users_search.php?customerid=" + userID), "CustID", "height=880,width=810,directories=no,location=no,menubar=no,scrollbars=yes,status=yes,toolbar=no,resizable=yes");
		newWindow.focus();
		newWindow.moveTo(0,0);
	}
	
	function Order(orderNumber){

		newWindow = window.open(("ad_order.php?orderno=" + orderNumber), "CustID", "height=880,width=810,directories=no,location=no,menubar=no,scrollbars=yes,status=yes,toolbar=no,resizable=yes");
		newWindow.focus();
		newWindow.moveTo(0,0);
	}
	
	function Chat(chatThreadID){
		newWindow = window.open(("ad_chat_thread.php?chat_id=" + chatThreadID), "ChatView", "height=760,width=715,directories=no,location=no,menubar=no,scrollbars=yes,status=yes,toolbar=no,resizable=yes");
		newWindow.focus();
		newWindow.moveTo(0,0);
	}

	function SaveProj(webAddress, userID){ 
		newWindow = window.open(("https://" + webAddress + "/SavedProjects.php?action=overridesavedprojects&userid=" + userID), "CustID", "height=880,width=810,directories=no,location=no,menubar=no,scrollbars=yes,status=yes,toolbar=no,resizable=yes");
		newWindow.focus();
		newWindow.moveTo(0,0);
	}

	function UserInfo(userID){ 

		newWindow = window.open(("ad_users_search.php?customerid=" + userID), "CustID", "height=880,width=810,directories=no,location=no,menubar=no,scrollbars=yes,status=yes,toolbar=no,resizable=yes");
		newWindow.focus();
		newWindow.moveTo(0,0);
	}

	function goToOrderNumber(orderNumber){ 

		document.location = "./ad_order.php?orderno=" + orderNumber;
	}



	function MemoCounts(CustomerReactionChar, StartTimeStamp, EndTimeStamp){
		var deadAccountsURL = "./ad_marketing_userlist.php?starttime=" + StartTimeStamp + "&endtime=" + EndTimeStamp + "&ReportType=MemosType&cstReac=" + CustomerReactionChar;
		newWindow = window.open(deadAccountsURL, "deadAccounts", "height=620,width=270,directories=no,location=no,menubar=no,scrollbars=yes,status=yes,toolbar=no,resizable=yes");
		newWindow.focus();
	
	}




	var lastOrderUniqueRowID = null;
	var orderInfoCurrentlyShowingFlag = false;
	function OrdInf(uniqueRowID, orderID, showFlag){

		var spanName = "ordInf" + uniqueRowID;
		var divName = "Pnum" + uniqueRowID;

		if(showFlag){
			lastOrderUniqueRowID = uniqueRowID;

			// Set time out so that we don't make a bunch of requests to the server scrolling over links quickly.
			setTimeout("fetchAndDisplayOrderInfo(" + uniqueRowID + ", " + orderID + ")",500);

		}
		else{
			// it can take a while for the browser to find a Div object by name... so don't move the zindexes just by rolling your mouse over before the window has had time to display.
			if(orderInfoCurrentlyShowingFlag){
				document.all(spanName).style.visibility = "hidden";
				document.all(spanName).innerHTML = ""

				document.all(spanName).style.zIndex = 1500;
				document.all(divName).style.zIndex = 1501;
			}
		
			lastOrderUniqueRowID = null;
			
			orderInfoCurrentlyShowingFlag = false;

		}

	}



	function fetchAndDisplayOrderInfo(uniqueRowID, orderID){

		var spanName = "ordInf" + uniqueRowID;
		var divName = "Pnum" + uniqueRowID;

		// Make sure that another link wasn't rolled over before this one displayed.
		if(uniqueRowID != lastOrderUniqueRowID){
			
			document.all(spanName).style.zIndex = 1500;
			document.all(divName).style.zIndex = 1501;
			
			orderInfoCurrentlyShowingFlag = false;
			
			return;
		}
		
		orderInfoCurrentlyShowingFlag = true;


		var htmlTableStart = '<table cellpadding="3" cellspacing="0" width="380"><tr><td bgcolor="#bbbbbb"><table cellpadding="0" cellspacing="0" width="100%"><tr><td bgcolor="#337733"><table cellpadding="5" cellspacing="1" width="100%"><tr><td class="SmallBody" style="background-color:#FFFFFF; background-image: url(./images/background_order_summary.jpg);">';

		var htmlTableEnd = '</td></tr></table></td></tr></table></td></tr></table>';

		dateObj = new Date();
		var NoCache = dateObj.getTime();



		//Load the XML doc from the server.
		var xmlDoc=getXmlReqObj();
		xmlDoc.open("GET", "ad_order.php?action=GetOrderSummaryHTML&orderno=" + orderID + "&nocache=" + NoCache, false);
		xmlDoc.send(null);
		var xmlResponseTxt = xmlDoc.responseText;
		
		var ServerResponse = getXMLdataFromTagName(xmlResponseTxt, "success");
		var orderDataHTML = getXMLdataFromTagName(xmlResponseTxt, "details");
		var errorDescription = getXMLdataFromTagName(xmlResponseTxt, "error_description");


		if(ServerResponse == "good"){

			var htmlForWindow = htmlTableStart;
			htmlForWindow += orderDataHTML;
			htmlForWindow += htmlTableEnd;


			document.all(spanName).innerHTML = htmlForWindow;
			document.all(spanName).style.visibility = "visible";
			
			document.all(spanName).style.zIndex = 9001;
			document.all(divName).style.zIndex = 9002;

		}
		else{
			alert("Could not fetch Order Data:\n\n" + errorDescription);
		}


	}
	
	
	function hideTheToolTipContainer(divNameString){
		var innertDiv = document.getElementById(divNameString);
		innertDiv.style.position = "absolute";
		innertDiv.style.display = "none";
	}
	
	function showTheToolTip(divNameString){
	
		document.getElementById(divNameString).style.display = 'block';
		
	}

	function isInArray(array,value) {
		for (var i=0; i < array.length; i++) {
			if (array[i] == value)
				return true;
		}
		return false;
	}
	
	var scheduleIDshown = "";
	
	// increate a global variable  to ensure that the fly open layer is always on top
	var scheduleZindexCouter = 5;
	function showSchedule(eventTitleSig, showFlag){

		// First hide the existing schedule
		if(showFlag && scheduleIDshown != ""){
			showSchedule(scheduleIDshown, false);
			scheduleIDshown = "";
		}

		var spanname = "ScheduleSpan" + eventTitleSig;
		var divname = "ScheduleDiv" + eventTitleSig;
	
		if (showFlag) {
			scheduleZindexCouter++;
			scheduleZindexCouter++;
	
			document.all(spanname).style.visibility = "visible";
			document.all(spanname).style.zIndex = scheduleZindexCouter;
			document.all(divname).style.zIndex = scheduleZindexCouter + 2;
			
			
			var theHTML = "<table width='100%' cellpadding='2' cellspacing='0'><tr><td align='right'>";
			theHTML += "<a href='javascript:showSchedule(\""+eventTitleSig+"\", false)'><img border='0' src='./images/button-x-yellow-up.png' onMouseOut='this.src=\"./images/button-x-yellow-up.png\"' onMouseOver='this.src=\"./images/button-x-yellow-down.png\"'></a><img src='./images/transparent.gif' width='3' height='1'><br>";
			theHTML += "<iframe src=\"./ad_schedule_display.php?eventTitleSignature="+eventTitleSig+"\" height=\"220\" width=\"535\">Iframes must be suported by your browser for this to work.</iframe>";
			theHTML += "<br><a href='javascript:showSchedule(\""+eventTitleSig+"\", false)'><img border='0' src='./images/button-x-yellow-up.png' onMouseOut='this.src=\"./images/button-x-yellow-up.png\"' onMouseOver='this.src=\"./images/button-x-yellow-down.png\"'></a><img src='./images/transparent.gif' width='3' height='1'>";
			theHTML += "</td></tr></table>";
        
			document.all(spanname).innerHTML = theHTML;
			
			scheduleIDshown = eventTitleSig;
			
		}
		else{
			document.all(spanname).style.visibility = "hidden";
			document.all(spanname).innerHTML = "";
			
			scheduleIDshown = "";
	
		}

	}
	
	// Fixes the Year drop downs in case the date Cached is old.
	// Makes sure that the number of Years in the Drop down is appended up until the current date.
	// Pass in the List Menu Object into this function.
	function fixYearDropDownIfReportCached(yearDropDown){

		var dateObj = new Date();
		var currentYear = dateObj.getFullYear();
	
		var highestYearInDropDown = 2000;
		
		for(var i =0;  i < yearDropDown.options.length; i++){
			var thisYear = parseInt(yearDropDown.options[i].value);
			if(thisYear > highestYearInDropDown)
				highestYearInDropDown = thisYear;
		}
		

		for(var i=(highestYearInDropDown + 1); i<=currentYear; i++){
			
			var newYearOptionObject = new Option();
			newYearOptionObject.value = i;
			newYearOptionObject.text = i;
			yearDropDown.options.add(newYearOptionObject);
		}
	}
	
	function visitorChartSession(sessionID, conflateLabelDetails){
		
		if(conflateLabelDetails)
			conflateLabelDetails = "yes";
		else
			conflateLabelDetails = "no";
		
		var address = "ad_visitorPathsChart.php?chartType=single&sessionIDs=" + sessionID + "&conflateDetailedLabels=" + conflateLabelDetails;

		newWindow = window.open(address, "sessionGraph", "height=880,width=810,directories=no,location=no,menubar=no,scrollbars=yes,status=yes,toolbar=no,resizable=yes");
	}
	
	
	
