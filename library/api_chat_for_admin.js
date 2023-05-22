



// The "loaded event" will be called with 1 parameter (the Chat Subject).  
// That way you can set the drop down menu status after communication with the server has finished.
// The API will not let you call multiple API requests (if communcation is in process).
function ChatThreadPleaseWait(chatThreadId)
{
	
	this.isCommunicatingFlag = false;
	this.chatID = chatThreadId;
	
	// An array of events to subscribe upon loading complete.
	this.onCompleteEvntFuncs = new Array();
	this.onCompleteEvntObjs = new Array();
		
	this.onErrEvntFuncs = new Array();
	this.onErrEvntObjs = new Array();
}



// Catch any errors while downloading XML Objects... and feed them into the Error Event subscribed to from the public object.
ChatThreadPleaseWait.prototype.triggerError_Private = function(statusCode, statusDesc)
{
	// Call all the error events that have been subscribed to.
	for(var i=0; i<this.onErrEvntFuncs.length; i++){
		this.onErrEvntFuncs[i].call(this.onErrEvntObjs[i], statusCode, "Error Sending Chat Data: " + statusDesc);
	}
	
	this.isCommunicatingFlag = false;
}

ChatThreadPleaseWait.prototype.attachLoadedEvent = function(functionReference, objectReference)
{
	this.onCompleteEvntFuncs.push(functionReference);
	this.onCompleteEvntObjs.push(objectReference);

}

// The event will be fired when there is an error.
ChatThreadPleaseWait.prototype.attachErrorEvent = function(functionReference, objectReference)
{
	this.onErrEvntFuncs.push(functionReference);
	this.onErrEvntObjs.push(objectReference);
}


// Pass in a Subject character.  The server will throw an exception if it is incorrect.
ChatThreadPleaseWait.prototype.allowPleaseWaitMessages = function(allowPleaseWaitFlag)
{
		
	// We want to send one message at a time.
	// In case there is already communication going on just abort.
	// Whenever that communication has finished, it will call this method (a chain reaction).
	if(this.isCommunicatingFlag){
		return;
	}
	
	this.isCommunicatingFlag = true;
	
	var allowString = "false";
	if(allowPleaseWaitFlag)
		allowString = "true";
		

	
	// So we can scope the callback.
	var instance = this;
	
	var dateObj = new Date();
	var apiURL = "./api_chat.php?api_version=1.1&chat_id="+escape(this.chatID)+"&command=allow_please_wait&allowFlag=" + escape(allowString) + "&nocache=" + dateObj.getTime();
	
	// Get a free/new connection object by reference. Abort method call if an HTTP object cant be created. The method getXmlHttpObj will alert an error message too.
	var xmlHttpObjFromGlobalQueue = getXmlHttpObj("ChatThreadPleaseWait");
	if(xmlHttpObjFromGlobalQueue == null)
		return;
	
	
	xmlHttpObjFromGlobalQueue.open('GET', apiURL, true);

	// Micosoft does not like you setting the Ready State before calling "open".
	xmlHttpObjFromGlobalQueue.onreadystatechange = function() 
	{
		// Now the Callback happened from the XMLHttpRequest communication
		// We have to find out which one was used from our Global Array of connection pools.
		// There is no way to guarantee the order in which these are processed.
		// We are basically looking for the first connection object which is Not Free... Having a matching CallBack function "identifier string".
		for(var t=0; t<xmlReqsObjArr.length; t++){
		
			if(xmlReqsObjArr[t].reqObjFree || xmlReqsObjArr[t].reqObjParsing || xmlReqsObjArr[t].xmlHttpObj.readyState != 4 || xmlReqsObjArr[t].callBackFunctionName != "ChatThreadPleaseWait")
				continue;
			
			// Find out if there was a server or 404 error, etc.
			if(xmlReqsObjArr[t].xmlHttpObj.status == "200"){
				xmlReqsObjArr[t].reqObjParsing = true;
				instance.private_parseXMLresponse(xmlReqsObjArr[t].xmlHttpObj.responseText);
			}
			else{
				instance.triggerError_Private(xmlReqsObjArr[t].xmlHttpObj.status, xmlReqsObjArr[t].xmlHttpObj.statusText);
			}

			// Now that we are done dealing with our Error, or parsing the document... 
			// ... we can mark our XMLhttpRequest Object as free to be used by another connection.
			xmlReqsObjArr[t].reqObjFree = true;
			xmlReqsObjArr[t].reqObjParsing = false;
		}
	}
	
	// GET requests don't have any data to send in the body.
	xmlHttpObjFromGlobalQueue.send(null);
}



ChatThreadPleaseWait.prototype.private_parseXMLresponse = function(xmlhttpresponseText)
{
	// Find out if the document was not what we expected to receive.
	// If Not, try to get an Error message and sent that through an error event.
	if(!xmlhttpresponseText.match(/\<result\>OK\<\/result\>/))
	{
		// Look for an error message in the response, since we didn't get an "OK"
		var errorMessage = getErrorMessageFromAPIresponseXML(xmlhttpresponseText);

		if(errorMessage == "")
			this.triggerError_Private("5101", "Unknown Error Parsing XML Document");
		else
			this.triggerError_Private("5102", errorMessage);

		return;
	}
	
	
	// There is no data that we are expecting in the response, other then <result>OK</result>
	// Call all the "loaded" events that have been subscribed to.
	for(var i=0; i<this.onCompleteEvntFuncs.length; i++){
		this.onCompleteEvntFuncs[i].call(this.onCompleteEvntObjs[i]);
	}
	
	this.isCommunicatingFlag = false;
	
}























// The "loaded event" will be called with 1 parameter (the Chat Subject).  
// That way you can set the drop down menu status after communication with the server has finished.
// The API will not let you call multiple API requests (if communcation is in process).
function ChatSubjectChanger(chatThreadId)
{
	
	this.isCommunicatingFlag = false;
	this.chatID = chatThreadId;
	
	// An array of events to subscribe upon loading complete.
	this.onCompleteEvntFuncs = new Array();
	this.onCompleteEvntObjs = new Array();
		
	this.onErrEvntFuncs = new Array();
	this.onErrEvntObjs = new Array();
}



// Catch any errors while downloading XML Objects... and feed them into the Error Event subscribed to from the public object.
ChatSubjectChanger.prototype.triggerError_Private = function(statusCode, statusDesc)
{
	// Call all the error events that have been subscribed to.
	for(var i=0; i<this.onErrEvntFuncs.length; i++){
		this.onErrEvntFuncs[i].call(this.onErrEvntObjs[i], statusCode, "Error Sending Chat Data: " + statusDesc);
	}
	
	this.isCommunicatingFlag = false;
}

ChatSubjectChanger.prototype.attachLoadedEvent = function(functionReference, objectReference)
{
	this.onCompleteEvntFuncs.push(functionReference);
	this.onCompleteEvntObjs.push(objectReference);

}

// The event will be fired when there is an error.
ChatSubjectChanger.prototype.attachErrorEvent = function(functionReference, objectReference)
{
	this.onErrEvntFuncs.push(functionReference);
	this.onErrEvntObjs.push(objectReference);
}


// Pass in a Subject character.  The server will throw an exception if it is incorrect.
ChatSubjectChanger.prototype.changeSubject = function(subjectChar)
{
		
	// We want to send one message at a time.
	// In case there is already communication going on just abort.
	// Whenever that communication has finished, it will call this method (a chain reaction).
	if(this.isCommunicatingFlag){
		return;
	}
	
	this.isCommunicatingFlag = true;

	
	// So we can scope the callback.
	var instance = this;
	
	var dateObj = new Date();
	var apiURL = "./api_chat.php?api_version=1.1&chat_id="+escape(this.chatID)+"&command=change_subject&chatSubject=" + escape(subjectChar) + "&nocache=" + dateObj.getTime();
	
	// Get a free/new connection object by reference. Abort method call if an HTTP object cant be created. The method getXmlHttpObj will alert an error message too.
	var xmlHttpObjFromGlobalQueue = getXmlHttpObj("ChatSubjectChanger");
	if(xmlHttpObjFromGlobalQueue == null)
		return;
	
	
	xmlHttpObjFromGlobalQueue.open('GET', apiURL, true);

	// Micosoft does not like you setting the Ready State before calling "open".
	xmlHttpObjFromGlobalQueue.onreadystatechange = function() 
	{
		// Now the Callback happened from the XMLHttpRequest communication
		// We have to find out which one was used from our Global Array of connection pools.
		// There is no way to guarantee the order in which these are processed.
		// We are basically looking for the first connection object which is Not Free... Having a matching CallBack function "identifier string".
		for(var t=0; t<xmlReqsObjArr.length; t++){
		
			if(xmlReqsObjArr[t].reqObjFree || xmlReqsObjArr[t].reqObjParsing || xmlReqsObjArr[t].xmlHttpObj.readyState != 4 || xmlReqsObjArr[t].callBackFunctionName != "ChatSubjectChanger")
				continue;
			
			// Find out if there was a server or 404 error, etc.
			if(xmlReqsObjArr[t].xmlHttpObj.status == "200"){
				xmlReqsObjArr[t].reqObjParsing = true;
				instance.private_parseXMLresponse(xmlReqsObjArr[t].xmlHttpObj.responseText);
			}
			else{
				instance.triggerError_Private(xmlReqsObjArr[t].xmlHttpObj.status, xmlReqsObjArr[t].xmlHttpObj.statusText);
			}

			// Now that we are done dealing with our Error, or parsing the document... 
			// ... we can mark our XMLhttpRequest Object as free to be used by another connection.
			xmlReqsObjArr[t].reqObjFree = true;
			xmlReqsObjArr[t].reqObjParsing = false;
		}
	}
	
	// GET requests don't have any data to send in the body.
	xmlHttpObjFromGlobalQueue.send(null);
}



ChatSubjectChanger.prototype.private_parseXMLresponse = function(xmlhttpresponseText)
{
	// Find out if the document was not what we expected to receive.
	// If Not, try to get an Error message and sent that through an error event.
	if(!xmlhttpresponseText.match(/\<result\>OK\<\/result\>/))
	{
		// Look for an error message in the response, since we didn't get an "OK"
		var errorMessage = getErrorMessageFromAPIresponseXML(xmlhttpresponseText);

		if(errorMessage == "")
			this.triggerError_Private("5101", "Unknown Error Parsing XML Document");
		else
			this.triggerError_Private("5102", errorMessage);

		return;
	}
	
	var subjectMatches = xmlhttpresponseText.match(/\<subject\>(\w)\<\/subject\>/);
	if(!subjectMatches){
		this.triggerError_Private("5107", "Unknown Subject Type");
		return;
	}
	
	var subjectChar = subjectMatches[1];
	
	
	// There is no data that we are expecting in the response, other then <result>OK</result>
	// Call all the "loaded" events that have been subscribed to.
	for(var i=0; i<this.onCompleteEvntFuncs.length; i++){
		this.onCompleteEvntFuncs[i].call(this.onCompleteEvntObjs[i], subjectChar);
	}
	
	this.isCommunicatingFlag = false;
	
}
















// The "loaded event" will be called without any parameters  
// The "get_messages" poll will contain the CSR status. Make sure to use that in case the status in changed in another window... it will automatically update everything.
function ChatCsrStatus()
{
	
	var lastStatusChangeTimeStamp = 0;
	
	this.isCommunicatingFlag = false;
	
	this.activeChatThreadIds = "";
	this.delinquentChatThreadList = "";
	this.openChatThreadsWithoutResponseList = "";
	
	this.csrIsOffline = false;
	this.csrIsAvailable = false;
	this.csrIsFull = false;
	
	this.customersWaiting = 0;
	this.chatThreadsOpen = 0;
	this.chhatThreadLimit = 0;
	
	// An array of events to subscribe upon loading complete.
	this.onChangeCompleteEvntFuncs = new Array();
	this.onChangeCompleteEvntObjs = new Array();
		
	this.onFetchCompleteEvntFuncs = new Array();
	this.onFetchCompleteEvntObjs = new Array();
		
	this.onChangeErrEvntFuncs = new Array();
	this.onChangeErrEventsObjectRefArr = new Array();
	
	this.onFetchErrEvntFuncs = new Array();
	this.onFetchErrEventsObjectRefArr = new Array();
}



// Catch any errors while downloading XML Objects... and feed them into the Error Event subscribed to from the public object.
ChatCsrStatus.prototype.triggerChangeError_Private = function(statusCode, statusDesc)
{
	// Call all the error events that have been subscribed to.
	for(var i=0; i<this.onChangeErrEvntFuncs.length; i++){
		this.onChangeErrEvntFuncs[i].call(this.onChangeErrEventsObjectRefArr[i], statusCode, "Error Changing Chat Status: " + statusDesc);
	}
	
}
ChatCsrStatus.prototype.triggerFetchError_Private = function(statusCode, statusDesc)
{
	// Call all the error events that have been subscribed to.
	for(var i=0; i<this.onFetchErrEvntFuncs.length; i++){
		this.onFetchErrEvntFuncs[i].call(this.onFetchErrEventsObjectRefArr[i], statusCode, "Error Fetching Chat Status: " + statusDesc);
	}
	
	this.isCommunicatingFlag = false;
}

ChatCsrStatus.prototype.attachChangeLoadedEvent = function(functionReference, objectReference)
{
	this.onChangeCompleteEvntFuncs.push(functionReference);
	this.onChangeCompleteEvntObjs.push(objectReference);

}

// The event will be fired when there is an error.
ChatCsrStatus.prototype.attachChangeErrorEvent = function(functionReference, objectReference)
{
	this.onChangeErrEvntFuncs.push(functionReference);
	this.onChangeErrEventsObjectRefArr.push(objectReference);
}



ChatCsrStatus.prototype.attachFetchLoadedEvent = function(functionReference, objectReference)
{
	this.onFetchCompleteEvntFuncs.push(functionReference);
	this.onFetchCompleteEvntObjs.push(objectReference);

}

// The event will be fired when there is an error.
ChatCsrStatus.prototype.attachFetchErrorEvent = function(functionReference, objectReference)
{
	this.onFetchErrEvntFuncs.push(functionReference);
	this.onFetchErrEventsObjectRefArr.push(objectReference);
}



// Set parameter to TRUE to change status to "online"... FALSE to go "offline".
ChatCsrStatus.prototype.changeStatus = function(onlineFlag)
{
		
	var dateObj = new Date();
	
	// In case there are 2 browser windows open, we don't want to change the status and then a second later get a old-status that is cached (from the polling).
	this.lastStatusChangeTimeStamp = (dateObj.getTime() / 1000);
	
	if(onlineFlag)
		var apiCommand = "set_csr_status_online";
	else
		var apiCommand = "set_csr_status_offline";

	
	// So we can scope the callback.
	var instance = this;
	
	var dateObj = new Date();
	var apiURL = "./api_chat.php?api_version=1.1&command="+escape(apiCommand)+"&nocache=" + dateObj.getTime();
	
	// Get a free/new connection object by reference. Abort method call if an HTTP object cant be created. The method getXmlHttpObj will alert an error message too.
	var xmlHttpObjFromGlobalQueue = getXmlHttpObj("ChatCsrStatusChange");
	if(xmlHttpObjFromGlobalQueue == null)
		return;
	
	
	xmlHttpObjFromGlobalQueue.open('GET', apiURL, true);

	// Micosoft does not like you setting the Ready State before calling "open".
	xmlHttpObjFromGlobalQueue.onreadystatechange = function() 
	{
		// Now the Callback happened from the XMLHttpRequest communication
		// We have to find out which one was used from our Global Array of connection pools.
		// There is no way to guarantee the order in which these are processed.
		// We are basically looking for the first connection object which is Not Free... Having a matching CallBack function "identifier string".
		for(var t=0; t<xmlReqsObjArr.length; t++){
		
			if(xmlReqsObjArr[t].reqObjFree || xmlReqsObjArr[t].reqObjParsing || xmlReqsObjArr[t].xmlHttpObj.readyState != 4 || xmlReqsObjArr[t].callBackFunctionName != "ChatCsrStatusChange")
				continue;
			
			// Find out if there was a server or 404 error, etc.
			if(xmlReqsObjArr[t].xmlHttpObj.status == "200"){
				xmlReqsObjArr[t].reqObjParsing = true;
				instance.private_parseChangeXMLresponse(xmlReqsObjArr[t].xmlHttpObj.responseText);
			}
			else{
				instance.triggerChangeError_Private(xmlReqsObjArr[t].xmlHttpObj.status, xmlReqsObjArr[t].xmlHttpObj.statusText);
			}

			// Now that we are done dealing with our Error, or parsing the document... 
			// ... we can mark our XMLhttpRequest Object as free to be used by another connection.
			xmlReqsObjArr[t].reqObjFree = true;
			xmlReqsObjArr[t].reqObjParsing = false;
		}
	}
	
	// GET requests don't have any data to send in the body.
	xmlHttpObjFromGlobalQueue.send(null);
}




ChatCsrStatus.prototype.private_parseChangeXMLresponse = function(xmlhttpresponseText)
{
	
	// Find out if the document was not what we expected to receive.
	// If Not, try to get an Error message and sent that through an error event.
	if(!xmlhttpresponseText.match(/\<result\>OK\<\/result\>/))
	{
		// Look for an error message in the response, since we didn't get an "OK"
		var errorMessage = getErrorMessageFromAPIresponseXML(xmlhttpresponseText);

		if(errorMessage == "")
			this.triggerChangeError_Private("5101", "Unknown Error Parsing XML Document");
		else
			this.triggerChangeError_Private("5102", errorMessage);

		return;
	}


	// There is no data that we are expecting in the response, other then <result>OK</result>
	// Call all the "loaded" events that have been subscribed to.
	for(var i=0; i<this.onChangeCompleteEvntFuncs.length; i++){
		this.onChangeCompleteEvntFuncs[i].call(this.onChangeCompleteEvntObjs[i]);
	}
	
	
	
}

// -----------  For Fetching the CSR status
// The parameter snapToSeconds will cut down on API requests because the browser can cache multiple requests (if there are multiple browser windows open.
// For example pass in a parameter of "30" if you want the API URL to change once every 30 seconds.
ChatCsrStatus.prototype.fetchStatus = function(userID, snapToSeconds)
{
	
	snapToSeconds = parseInt(snapToSeconds);
		
	// We want to send one message at a time.
	if(this.isCommunicatingFlag){
		return;
	}
	
	this.isCommunicatingFlag = true;
	
	// So we can scope the callback.
	var instance = this;
	
	var dateObj = new Date();
	
	
	var currentTimeStamp = (dateObj.getTime() / 1000);
	var secondsSinceLastStatusChange = currentTimeStamp - this.lastStatusChangeTimeStamp;
	
	// If someone changed the status within 20 seconds, don't allow the URL to be cached.
	// There could be some problems if you change the status and then the server polls a second later and fetches the old status... then a second later it snaps to the new status.
	
	if(secondsSinceLastStatusChange < 20 && secondsSinceLastStatusChange >=0){
		var breakCacheStr = "&statusChangeCacheBreak=" +  dateObj.getTime() + "___" + secondsSinceLastStatusChange;
	}
	else{
		var breakCacheStr = "";
	}
	
	if(snapToSeconds < 0){
		alert("The Snap To seconds has to be a number greater than or equal to zero.");
	}
	if(snapToSeconds == 0){
		var cacheUrl = dateObj.getTime();
	}
	else{
		// Make a string snap to a specific time of the day (based upon the number of seconds between the snap-to value).
		// First see how many intervals there are in the day.
		var actualSecondCounterOfToday = (dateObj.getMinutes() * 60) + (dateObj.getHours() * 60 * 60) + (dateObj.getSeconds());
		var intervalsUntilNow = Math.ceil(actualSecondCounterOfToday / snapToSeconds);
		var cacheTimeStamp = + dateObj.getMonth() + "" + dateObj.getDate() + "" + dateObj.getYear() + "_" + intervalsUntilNow;
	}
	
	
	var apiURL = "./api_chat.php?api_version=1.1&command=get_csr_status&nocache=" + cacheTimeStamp + "_" + userID + breakCacheStr;
	
	// Get a free/new connection object by reference. Abort method call if an HTTP object cant be created. The method getXmlHttpObj will alert an error message too.
	var xmlHttpObjFromGlobalQueue = getXmlHttpObj("ChatCsrStatusFetch");
	if(xmlHttpObjFromGlobalQueue == null)
		return;
	
	
	xmlHttpObjFromGlobalQueue.open('GET', apiURL, true);

	// Micosoft does not like you setting the Ready State before calling "open".
	xmlHttpObjFromGlobalQueue.onreadystatechange = function() 
	{
		// Now the Callback happened from the XMLHttpRequest communication
		// We have to find out which one was used from our Global Array of connection pools.
		// There is no way to guarantee the order in which these are processed.
		// We are basically looking for the first connection object which is Not Free... Having a matching CallBack function "identifier string".
		for(var t=0; t<xmlReqsObjArr.length; t++){
		
			if(xmlReqsObjArr[t].reqObjFree || xmlReqsObjArr[t].reqObjParsing || xmlReqsObjArr[t].xmlHttpObj.readyState != 4 || xmlReqsObjArr[t].callBackFunctionName != "ChatCsrStatusFetch")
				continue;
			
			// Find out if there was a server or 404 error, etc.
			if(xmlReqsObjArr[t].xmlHttpObj.status == "200"){
				xmlReqsObjArr[t].reqObjParsing = true;
				instance.private_parseFetchXMLresponse(xmlReqsObjArr[t].xmlHttpObj.responseText);
			}
			else{
				instance.triggerFetchError_Private(xmlReqsObjArr[t].xmlHttpObj.status, xmlReqsObjArr[t].xmlHttpObj.statusText);
			}

			// Now that we are done dealing with our Error, or parsing the document... 
			// ... we can mark our XMLhttpRequest Object as free to be used by another connection.
			xmlReqsObjArr[t].reqObjFree = true;
			xmlReqsObjArr[t].reqObjParsing = false;
		}
	}
	
	// GET requests don't have any data to send in the body.
	xmlHttpObjFromGlobalQueue.send(null);
}


ChatCsrStatus.prototype.private_parseFetchXMLresponse = function(xmlhttpresponseText)
{

	// Find out if the document was not what we expected to receive.
	// If Not, try to get an Error message and sent that through an error event.
	if(!xmlhttpresponseText.match(/\<result\>OK\<\/result\>/))
	{
		// Look for an error message in the response, since we didn't get an "OK"
		var errorMessage = getErrorMessageFromAPIresponseXML(xmlhttpresponseText);

		if(errorMessage == "")
			this.triggerFetchError_Private("5301", "Unknown Error Parsing XML Document");
		else
			this.triggerFetchError_Private("5302", errorMessage);

		return;
	}

	var xmlLinesArr = xmlhttpresponseText.split("\n");

	for(var lineNo = 0; lineNo < xmlLinesArr.length; lineNo++)
	{
		if(xmlLinesArr[lineNo].toString().match(/\<csr_full\>/))
			this.csrIsFull = (getXMLdata(xmlLinesArr[lineNo]) == "true" ? true : false);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<csr_offline\>/))
			this.csrIsOffline = (getXMLdata(xmlLinesArr[lineNo]) == "true" ? true : false);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<csr_available\>/))
			this.csrIsAvailable = (getXMLdata(xmlLinesArr[lineNo]) == "true" ? true : false);
	
		else if(xmlLinesArr[lineNo].toString().match(/\<csr_open_threads\>/))
			this.chatThreadsOpen = (getXMLdata(xmlLinesArr[lineNo]));
			
		else if(xmlLinesArr[lineNo].toString().match(/\<csr_thread_limit\>/))
			this.chhatThreadLimit = (getXMLdata(xmlLinesArr[lineNo]));
	
		else if(xmlLinesArr[lineNo].toString().match(/\<open_chat_threads\>/))
			this.activeChatThreadIds = (getXMLdata(xmlLinesArr[lineNo]));

		else if(xmlLinesArr[lineNo].toString().match(/\<delinquent_chat_threads\>/))
			this.delinquentChatThreadList = (getXMLdata(xmlLinesArr[lineNo]));

		else if(xmlLinesArr[lineNo].toString().match(/\<open_threads_no_response\>/))
			this.openChatThreadsWithoutResponseList = (getXMLdata(xmlLinesArr[lineNo]));

		else if(xmlLinesArr[lineNo].toString().match(/\<customers_waiting\>/))
			this.customersWaiting = (getXMLdata(xmlLinesArr[lineNo]));
	
	}
	
	this.isCommunicatingFlag = false;
	
	// There is no data that we are expecting in the response, other then <result>OK</result>
	// Call all the "loaded" events that have been subscribed to.
	for(var i=0; i<this.onFetchCompleteEvntFuncs.length; i++){
		this.onFetchCompleteEvntFuncs[i].call(this.onFetchCompleteEvntObjs[i]);
	}
	
	
	
}

// Returns an array of active Chat threads by the CSR
ChatCsrStatus.prototype.getActiveChatThreads = function(){
	
	if(this.activeChatThreadIds == "")
		return new Array();
		
	return this.activeChatThreadIds.split("|");
	
}


// Returns an array of active Chat threads that haven't been "pinged" for a while.
// You should try to re-open these pop-up windows.
ChatCsrStatus.prototype.getDelinquentChatThreads = function(){
	
	if(this.delinquentChatThreadList == "")
		return new Array();
		
	return this.delinquentChatThreadList.split("|");
	
}


// This is a good indication to launch a pop-up window immediately
// However, the pop-up window still needs broadcast itself to the parent to prevent the pop-up from repeatadly opening (until the first response).
// After the first response, we rely on the delinquent algortythm to re-launch the pop-up windows.
ChatCsrStatus.prototype.getChatThreadsWithoutResponses = function(){
	
	if(this.openChatThreadsWithoutResponseList == "")
		return new Array();
		
	return this.openChatThreadsWithoutResponseList.split("|");
	
}



















