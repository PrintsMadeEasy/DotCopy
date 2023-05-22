// JavaScript Document



// The idea for the DHTML fly open menu is to make the user hover their mouse on the link for a little bit before it appears (to prevent flickering on mouse movement).
// It should also have a little delay before closing so that the user has time to position their cursor inside of the HTML menu before dissapearing.
var isMouseOnChatInfoDiv = false;
var isMouseOnChatInfoLink = false;

// We have to make sure that the "hide timeout" is slight more than the "show timeout"
// The reason is if your mouse goes over and leaves the link quick, there is a danger of both happening at the same time.  Leaving should be more powerful.
function chatStatusLinkLeave(){
	setTimeout("hideChatInfo()", 1000);
	isMouseOnChatInfoLink = false;
}
function chatStatusLinkHover(){
	isMouseOnChatInfoLink = true;
	setTimeout("showChatStatusWindow()", 900);
}

function showChatStatusWindow(){
	if(isMouseOnChatInfoLink)
		document.getElementById("chatInfoMenu").style.display = "block";
}

function chatInfoLeave(){
	isMouseOnChatInfoDiv = false;
	setTimeout("hideChatInfo()", 1000);
}
function chatInfoHover(){
	isMouseOnChatInfoDiv = true;
}
function hideChatInfo(){
	if(!isMouseOnChatInfoDiv && !isMouseOnChatInfoLink){
		document.getElementById("chatInfoMenu").style.display = "none";
	}
}




function setStatusOffline(){
	chatCsrStatusObj.changeStatus(false);
}
function setStatusOnline(){
	chatCsrStatusObj.changeStatus(true);
}



var popUpWindowRefArr = new Array();


function showChatThreadPopUp(threadID){
	var popUpDateObj = new Date();
	popUpWindowRefArr[threadID] = window.open(("ad_chat_thread.php?chat_id=" + threadID), ("chatThread" + threadID + popUpDateObj.getTime()), "height=760,width=715,directories=no,location=no,menubar=no,scrollbars=yes,status=yes,toolbar=no,resizable=yes");
	popUpWindowRefArr[threadID].focus();
}

function showChatPopUpWindows(){
	var delinquentChatIds = chatCsrStatusObj.getDelinquentChatThreads();
	var openChatThreadsWithoutResponseArr = chatCsrStatusObj.getChatThreadsWithoutResponses();
	
	// First open any new chat conversations for the CSR.
	for(var i=0; i<openChatThreadsWithoutResponseArr.length; i++){
		showChatThreadPopUp(openChatThreadsWithoutResponseArr[i]);
	}
	
	// Now re-open any chat Thread which hasn't been getting polled/pinged recently.
	for(var i=0; i<delinquentChatIds.length; i++){
		showChatThreadPopUp(delinquentChatIds[i]);
	}
}



// This function is called from the pop-up window on a quick interval.  So if we switch base pages it will still know what pop-up windows are open.
function chatWindowExistenceBroadcast(chatThreadID, windowObject){
	popUpWindowRefArr[chatThreadID] = windowObject;
}

function setStatusRadioButtons(){
	try{
		if(chatCsrStatusObj.csrIsOffline){
			document.getElementById("csrStatus_online").checked = false;
			document.getElementById("csrStatus_offline").checked = true;
		}
		else{
			document.getElementById("csrStatus_online").checked = true;
			document.getElementById("csrStatus_offline").checked = false;
			
		}
	}
	catch(e){
		
	}
	finally{
	}	
}
function setStatusLink(){
	try{
		var rollOverHtml = "onMouseOut='chatStatusLinkLeave();' onMouseOver='chatStatusLinkHover();' ";
		if(chatCsrStatusObj.csrIsOffline){
			document.getElementById("chatStatusLink").innerHTML = "<a href='javascript:setStatusOnline();' class='AdminTemplateLink' "+rollOverHtml+"><font color='#770000'>Chat is Off</font></a>";
		}
		else{
			document.getElementById("chatStatusLink").innerHTML = "<a href='javascript:setStatusOffline();' class='AdminTemplateLink' "+rollOverHtml+">Chat is On</a>";
		}
	}
	catch(e){
		
	}
	finally{
	}	
}

function setStatusDesc(){
	var openThreadsDesc = "<br><br>";
	
	if(chatCsrStatusObj.chhatThreadLimit == 0){
		openThreadsDesc += "<b><u>Open Thread Limit</u></b><br>Zero ";
	}
	else {
		openThreadsDesc += "<b><u>Open Threads</u></b><br>" + chatCsrStatusObj.chatThreadsOpen + "/" + chatCsrStatusObj.chhatThreadLimit;
	}

	var overloadedDesc = "";
	if(chatCsrStatusObj.chatThreadsOpen > chatCsrStatusObj.chhatThreadLimit)
		overloadedDesc = " - <i>(Overloaded)</i>";
	
	var statusHtml = "";
	if(chatCsrStatusObj.csrIsAvailable)
		statusHtml = "<b><u>Your Status</u></b><br>Waiting for Chat Threads" + openThreadsDesc;
	else if(chatCsrStatusObj.csrIsOffline && chatCsrStatusObj.chhatThreadLimit == 0)
		statusHtml = "<b><u>Your Status</u></b><br>Offline" + openThreadsDesc + overloadedDesc;
	else if(chatCsrStatusObj.csrIsOffline && chatCsrStatusObj.csrIsFull)
		statusHtml = "<b><u>Your Status</u></b><br>Offline &amp; Busy" + openThreadsDesc + overloadedDesc;
	else if(chatCsrStatusObj.csrIsFull && chatCsrStatusObj.chhatThreadLimit == 0)
		statusHtml = "<b><u>Your Status</u></b><br>Online (napping) " + openThreadsDesc + overloadedDesc;
	else if(chatCsrStatusObj.csrIsFull)
		statusHtml = "<b><u>Your Status</u></b><br>Busy" + openThreadsDesc + overloadedDesc;
	else if(chatCsrStatusObj.csrIsOffline)
		statusHtml = "<b><u>Your Status:</u></b><br>Offline" + openThreadsDesc + overloadedDesc;
	else
		statusHtml = "";
		
	if(statusHtml != "")
		statusHtml += "<br><br>";
		
	var customersWaitingHtml = "<b><u>Customers Waiting</u></b><br>";
	if(chatCsrStatusObj.customersWaiting == 0)
		customersWaitingHtml += "None";
	else
		customersWaitingHtml += chatCsrStatusObj.customersWaiting;
		
	
	// For the DHTML fly-open menu on the nav bar.
	try{
		var chatInfoMenuHTML = "<font class='ReallySmallBody'><font color='#990000'>*</font> Click link to toggle status.</font><br><br>";
		chatInfoMenuHTML += statusHtml + "" + customersWaitingHtml + "<br><br>";
		chatInfoMenuHTML += "<a href='ad_chat_setup.php?' class='BlueRedLink'>More Chat Settings</a>";
		document.getElementById("chatInfoMenu").innerHTML = chatInfoMenuHTML;
	}
	catch(e){
	}
	finally{
	}
	
	
	// For the HTML which only exists on the Chat Setup page.
	try{
		document.getElementById("chatChatus").innerHTML = statusHtml;
	}
	catch(e){
	}
	finally{
	}
}

function chatCsrStatusFetchLoaded(){
	
	setStatusRadioButtons();
	setStatusLink();
	setStatusDesc();
	showChatPopUpWindows();
		
}
function chatCsrStatusFetchError(errorCode, errorMsg){

	try{
		document.getElementById("chatChatus").innerHTML = "Chat CSR Status Fetch Error: " + errorMsg;
		// make sure that the radio buttons don't show anything checked.
		document.getElementById("csrStatus_online").checked = false;
		document.getElementById("csrStatus_offline").checked = false;
	}
	catch(e){}
}


function chatCsrStatusChangeLoaded(){

	var dateObj = new Date();
	var dontCacheRequest = (dateObj.getTime() / 1000);

	// Immediately after changing the status, do an update to make sure that it "stuck".
	chatCsrStatusObj.fetchStatus((userIdForCaching + "" + dontCacheRequest), statusFetchSecondSnapTo);
}
function chatCsrStatusChangeError(errorCode, errorMsg){
	
	try{
		document.getElementById("chatChatus").innerHTML = "Chat CSR Status Change Error: " + errorMsg;
		// make sure that the radio buttons don't show anything checked.
		document.getElementById("csrStatus_online").checked = false;
		document.getElementById("csrStatus_offline").checked = false;
	}
	catch(e){
		alert("Chat CSR Status Change Error: " + errorMsg);
	}
}

var chatCsrStatusObj = new ChatCsrStatus();

chatCsrStatusObj.attachFetchLoadedEvent(chatCsrStatusFetchLoaded);
chatCsrStatusObj.attachFetchErrorEvent(chatCsrStatusFetchError);

chatCsrStatusObj.attachChangeLoadedEvent(chatCsrStatusChangeLoaded);
chatCsrStatusObj.attachChangeErrorEvent(chatCsrStatusChangeError);



var statusFetchInterval = null;
function startPollingStatus(){
	statusFetchInterval = setInterval("chatCsrStatusObj.fetchStatus('"+userIdForCaching+"', statusFetchSecondSnapTo)", (1000 * statusFetchSecondSnapTo));
	chatCsrStatusObj.fetchStatus(userIdForCaching, statusFetchSecondSnapTo);
}

// Don't start the polling the server until after the page loads.  "setInterval" fires immediately.
setTimeout(startPollingStatus, 1000);


