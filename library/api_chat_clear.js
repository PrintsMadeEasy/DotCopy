// Compress this script by going to...  http://www.creativyst.com/Prod/3/
// Then copy the output into api_chat.js

// ----  Use this class to load messages from the server on an interval
// ----  You may also want to call the message loader immediately after a "ChatMessageSend" has completed
function ChatMessageLoader(chatThreadId)
{
	this.messageObjectsArr = new Array();
	this.acknowledgeMessageIds = new Array();
	
	this.csrAssignedId = 0;
	this.csrAssignedPname = "";
	this.csrAssignedPhotoId = 0;
	this.chatID = chatThreadId;
	this.maxMessageIdDownloaded = 0;
	this.chatStatus = "";
	this.chatSubject = "";
	this.chatType = "";
	this.closedReason = "";
	this.customerName = "";
	this.customerID = 0;
	this.customersWaiting = 0;
	this.userIsCsr = false;
	this.estimatedWaitSecs = 0;
	this.allowPleaseWait = false;
	this.customerIsTyping = false;
	this.csrIsTyping = false;
	
	this.getMessagesInProcess = false;
	
	// An array of events to subscribe upon loading complete.
	this.onCompleteEvntFuncs = new Array();
	this.onCompleteEvntObjs = new Array();
		
	this.onErrEvntFuncs = new Array();
	this.onErrEvntObjs = new Array();
}


ChatMessageLoader.prototype.setIsTyping = function(isTypingFlag)
{
	if(this.userIsCsr){
		this.csrIsTyping = isTypingFlag;
	}
	else{
		this.customerIsTyping = isTypingFlag;
	}
}



// Catch any errors while downloading XML Objects... and feed them into the Error Event subscribed to from the public object.
ChatMessageLoader.prototype.triggerError_Private = function(statusCode, statusDesc)
{
	this.getMessagesInProcess = false;
	
	// Call all the error events that have been subscribed to.
	for(var i=0; i<this.onErrEvntFuncs.length; i++){
		this.onErrEvntFuncs[i].call(this.onErrEvntObjs[i], statusCode, "Error Loading Chat Data: " + statusDesc);
	}
	
	
}

ChatMessageLoader.prototype.attachLoadedEvent = function(functionReference, objectReference)
{
	this.onCompleteEvntFuncs.push(functionReference);
	this.onCompleteEvntObjs.push(objectReference);

}

// The event will be fired when there is an error.
ChatMessageLoader.prototype.attachErrorEvent = function(functionReference, objectReference)
{
	this.onErrEvntFuncs.push(functionReference);
	this.onErrEvntObjs.push(objectReference);
}



ChatMessageLoader.prototype.fetchMessagesFromServer = function()
{
	
	// Don't poll the server unless the previous communication has finished.
	if(this.getMessagesInProcess)
		return;
		
	this.getMessagesInProcess = true;
		
	// Don't allow a new request for messages to happen until the last Message Objects have been fetched.
	if(this.messageObjectsArr.length != 0)
		return;
	
	// Make a pipe-delimted list from the array.
	var receiptAcks = "";
	
	for(var i=0; i<this.acknowledgeMessageIds.length; i++)
	{	
		if(!this.acknowledgeMessageIds[i].toString().match(/^\d+$/))
		{
			alert("Error in method ChatMessageLoader.getMessages. One of the Array elements is not a number.");
			return;
		}

		if(receiptAcks != "")
			receiptAcks += "|";
		
		receiptAcks += this.acknowledgeMessageIds[i];
	}
	
	
	// Every time we check for new messages we will let the server know if the user is typing a message
	if(this.userIsCsr)
		var isTypingParameter = this.csrIsTyping ? "true" : "false";
	else
		var isTypingParameter = this.customerIsTyping ? "true" : "false";

	
	// So we can scope the callback.
	var instance = this;
	
	var dateObj = new Date();
	var apiURL = "./api_chat.php?api_version=1.1&chat_id="+escape(this.chatID)+"&command=get_messages&is_typing="+isTypingParameter+"&lastMessageId=" + this.maxMessageIdDownloaded + "&message_acks=" +  receiptAcks + "&nocache=" + dateObj.getTime();

	// Get a free/new connection object by reference. Abort method call if an HTTP object cant be created. The method getXmlHttpObj will alert an error message too.
	var xmlHttpObjFromGlobalQueue = getXmlHttpObj("ChatLoader");
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
		
			if(xmlReqsObjArr[t].reqObjFree || xmlReqsObjArr[t].reqObjParsing || xmlReqsObjArr[t].xmlHttpObj.readyState != 4 || xmlReqsObjArr[t].callBackFunctionName != "ChatLoader")
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

// Returns an array of Project ID's that have been loaded.
ChatMessageLoader.prototype.getMessageObjs = function(){
	
	if(this.getMessagesInProcess){
		alert("You can't call the method getMessageObjs until the communication has finished.");
		return new Array();
	}
	
	for(var i=0; i<this.messageObjectsArr.length; i++){
		this.acknowledgeMessageIds.push(this.messageObjectsArr[i].msgID);
		
		// Record the highest message ID that we have received.  That way (on the next request) we will let the server not to send us messages that we have already received.
		if(parseInt(this.messageObjectsArr[i].msgID) > this.maxMessageIdDownloaded){
			this.maxMessageIdDownloaded = parseInt(this.messageObjectsArr[i].msgID);
		}
	}
	
	var returnArr = this.messageObjectsArr;
	
	// Wipe out the array so it is ready for the next request.
	this.messageObjectsArr = new Array();
	
	return returnArr;
}



ChatMessageLoader.prototype.private_parseXMLresponse = function(xmlhttpresponseText)
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
	

	
	var messageObj = null;
	var messageCounterIndex = -1;

	var xmlLinesArr = xmlhttpresponseText.split("\n");

	for(var lineNo = 0; lineNo < xmlLinesArr.length; lineNo++)
	{
		// Every Time that we come accross an opening Project tag... we are going to start building a new Project Object
		// Every time we come across the closing tag, we are going to add it to our array.
		if(xmlLinesArr[lineNo].toString().match(/\<message\>/))
		{
			// Keep Counters so that when we run across sub-nodes we can keep building the array.
			messageCounterIndex++;
			
			this.messageObjectsArr[messageCounterIndex] = new ChatMessage();
		}	
	
		else if(xmlLinesArr[lineNo].toString().match(/\<status\>/))
			this.chatStatus = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<subject\>/))
			this.chatSubject  = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<chat_type\>/))
			this.chatType = getXMLdata(xmlLinesArr[lineNo]);

		else if(xmlLinesArr[lineNo].toString().match(/\<closed_reason\>/))
			this.closedReason = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<customer_name\>/))
			this.customerName = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<customer_id\>/))
			this.customerID = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<estimated_wait\>/))
			this.estimatedWaitSecs = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<is_csr\>/))
			this.userIsCsr = (getXMLdata(xmlLinesArr[lineNo]) == "true" ? true : false);
		
		else if(xmlLinesArr[lineNo].toString().match(/\<csr_assigned_id\>/))
			this.csrAssignedId = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<csr_assigned_p_name\>/))
			this.csrAssignedPname = getXMLdata(xmlLinesArr[lineNo]);
	
		else if(xmlLinesArr[lineNo].toString().match(/\<csr_assigned_photo\>/))
			this.csrAssignedPhotoId = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<customers_waiting\>/))
			this.customersWaiting = getXMLdata(xmlLinesArr[lineNo]);

		else if(xmlLinesArr[lineNo].toString().match(/\<allow_please_wait\>/))
			this.allowPleaseWait = (getXMLdata(xmlLinesArr[lineNo]) == "true" ? true : false);

		else if(xmlLinesArr[lineNo].toString().match(/\<customer_is_typing\>/))
			this.customerIsTyping = (getXMLdata(xmlLinesArr[lineNo]) == "true" ? true : false);

		else if(xmlLinesArr[lineNo].toString().match(/\<csr_is_typing\>/))
			this.csrIsTyping = (getXMLdata(xmlLinesArr[lineNo]) == "true" ? true : false);
			

		
		// ------------ For the Message Objects ----------
		else if(xmlLinesArr[lineNo].toString().match(/\<message_id\>/))
			this.messageObjectsArr[messageCounterIndex].msgID = getXMLdata(xmlLinesArr[lineNo]);
		
		else if(xmlLinesArr[lineNo].toString().match(/\<csr_id\>/))
			this.messageObjectsArr[messageCounterIndex].csrID = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<csr_name\>/))
			this.messageObjectsArr[messageCounterIndex].csrName = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<csr_only\>/))
			this.messageObjectsArr[messageCounterIndex].csrOnly = (getXMLdata(xmlLinesArr[lineNo]) == "true" ? true : false);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<csr_p_name\>/))
			this.messageObjectsArr[messageCounterIndex].csrPname = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<date\>/))
			this.messageObjectsArr[messageCounterIndex].unixDateStamp = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<file_id\>/))
			this.messageObjectsArr[messageCounterIndex].fileId = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<file_name\>/))
			this.messageObjectsArr[messageCounterIndex].fileName = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<file_size\>/))
			this.messageObjectsArr[messageCounterIndex].fileSize = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<message_text\>/))
			this.messageObjectsArr[messageCounterIndex].messageText = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<person_type\>/))
			this.messageObjectsArr[messageCounterIndex].personType = getXMLdata(xmlLinesArr[lineNo]);
	
	}
	
	this.getMessagesInProcess = false;
	
	// Call all the "loaded" events that have been subscribed to.
	for(var i=0; i<this.onCompleteEvntFuncs.length; i++){
		this.onCompleteEvntFuncs[i].call(this.onCompleteEvntObjs[i]);
	}
	
	
	
}


function ChatMessage()
{
	this.msgID = 0;
	this.csrID = 0;
	this.customerID = 0;
	this.customerName = "";
	this.csrName = "";
	this.csrPname = "";
	this.csrOnly = false;
	this.unixDateStamp = 0;
	this.fileId = 0;
	this.fileName = "unknown";
	this.fileSize = 0;
	this.messageText = "";
	this.personType = "";
}














function ChatMessageSender(chatThreadId)
{
	this.messageQueueArr = new Array();
	this.messagePrivateArr = new Array(); // A parallel array that says if the message is only visible for CSR's
	
	this.chatID = chatThreadId;
	this.messageSendInProcess = false;
	
	// An array of events to subscribe upon loading complete.
	this.onCompleteEvntFuncs = new Array();
	this.onCompleteEvntObjs = new Array();
		
	this.onErrEvntFuncs = new Array();
	this.onErrEvntObjs = new Array();
}



// Catch any errors while downloading XML Objects... and feed them into the Error Event subscribed to from the public object.
ChatMessageSender.prototype.triggerError_Private = function(statusCode, statusDesc)
{
	// Call all the error events that have been subscribed to.
	for(var i=0; i<this.onErrEvntFuncs.length; i++){
		this.onErrEvntFuncs[i].call(this.onErrEvntObjs[i], statusCode, "Error Sending Chat Data: " + statusDesc);
	}
	
	this.messageSendInProcess = false;
}

ChatMessageSender.prototype.attachLoadedEvent = function(functionReference, objectReference)
{
	this.onCompleteEvntFuncs.push(functionReference);
	this.onCompleteEvntObjs.push(objectReference);

}

// The event will be fired when there is an error.
ChatMessageSender.prototype.attachErrorEvent = function(functionReference, objectReference)
{
	this.onErrEvntFuncs.push(functionReference);
	this.onErrEvntObjs.push(objectReference);
}


ChatMessageSender.prototype.sendMessage = function(messageText, isPrivateFlag)
{
	
	if(messageText == "" || messageText == null)
		return;
		
	this.messageQueueArr.push(messageText);
	this.messagePrivateArr.push(isPrivateFlag ? "Y" : "N");
	
	// In case this is the first message in the queue... start the chain reaction.
	if(this.messageQueueArr.length == 1 || !this.messageSendInProcess)
		this.transferMessageQueue_Private();
}


// Messages will be sent to the server 1 at a time.
// Whenever the server finishes responding, the "finished event" will cause the next message within the queue to be sent.
ChatMessageSender.prototype.transferMessageQueue_Private = function()
{
	// Due to the asyncronous nature of this class (which uses timers).
	// ... we don't want to attemp communication on an empty queue.
	if(this.messageQueueArr.length == 0)
		return;
		
	// We want to send one message at a time.
	// In case there is already communication going on just abort.
	// Whenever that communication has finished, it will call this method (a chain reaction).
	if(this.messageSendInProcess){
		return;
	}
	
	this.messageSendInProcess = true;

	// Messages in the front of the array were added first... so they are next in line.		
	var messageToSend = this.messageQueueArr.shift();
	var isPrivate = this.messagePrivateArr.shift();

	
	// So we can scope the callback.
	var instance = this;
	
	var dateObj = new Date();
	var apiURL = "./api_chat.php?api_version=1.1&chat_id="+escape(this.chatID)+"&command=send_message&message_text=" + escape(messageToSend) + "&private=" +  escape(isPrivate) + "&nocache=" + dateObj.getTime();
	
	// Get a free/new connection object by reference. Abort method call if an HTTP object cant be created. The method getXmlHttpObj will alert an error message too.
	var xmlHttpObjFromGlobalQueue = getXmlHttpObj("ChatSender");
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
		
			if(xmlReqsObjArr[t].reqObjFree || xmlReqsObjArr[t].reqObjParsing || xmlReqsObjArr[t].xmlHttpObj.readyState != 4 || xmlReqsObjArr[t].callBackFunctionName != "ChatSender")
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



ChatMessageSender.prototype.private_parseXMLresponse = function(xmlhttpresponseText)
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
	
	this.messageSendInProcess = false;
	
	// This is a chain reaction.  If there is another message in the queue it will send it (one at a time).
	this.transferMessageQueue_Private();

}















// The "loaded event" will be called without any parameters  
// The "get_messages" poll will contain the CSR status. Make sure to use that in case the status in changed in another window... it will automatically update everything.
function ChatTerminator(chatThreadId)
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
ChatTerminator.prototype.triggerError_Private = function(statusCode, statusDesc)
{
	// Call all the error events that have been subscribed to.
	for(var i=0; i<this.onErrEvntFuncs.length; i++){
		this.onErrEvntFuncs[i].call(this.onErrEvntObjs[i], statusCode, "Error Sending Chat Data: " + statusDesc);
	}
	
	this.isCommunicatingFlag = false;
}

ChatTerminator.prototype.attachLoadedEvent = function(functionReference, objectReference)
{
	this.onCompleteEvntFuncs.push(functionReference);
	this.onCompleteEvntObjs.push(objectReference);

}

// The event will be fired when there is an error.
ChatTerminator.prototype.attachErrorEvent = function(functionReference, objectReference)
{
	this.onErrEvntFuncs.push(functionReference);
	this.onErrEvntObjs.push(objectReference);
}


ChatTerminator.prototype.closeChatThread = function(asyncFlag)
{
	
	// In case a parameter is not provided, then 
	if (arguments.length == 0){
		asyncFlag = true;
	}
	else{
		asyncFlag = Boolean(asyncFlag);	
	}

		
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
	var apiURL = "./api_chat.php?api_version=1.1&chat_id="+escape(this.chatID)+"&command=close_chat_thread&nocache=" + dateObj.getTime();
	
	// Get a free/new connection object by reference. Abort method call if an HTTP object cant be created. The method getXmlHttpObj will alert an error message too.
	var xmlHttpObjFromGlobalQueue = getXmlHttpObj("ChatTerminatorCall");
	if(xmlHttpObjFromGlobalQueue == null)
		return;
	
	
	xmlHttpObjFromGlobalQueue.open('GET', apiURL, asyncFlag);
	
	if(asyncFlag){

		// Micosoft does not like you setting the Ready State before calling "open".
		xmlHttpObjFromGlobalQueue.onreadystatechange = function() 
		{
			// Now the Callback happened from the XMLHttpRequest communication
			// We have to find out which one was used from our Global Array of connection pools.
			// There is no way to guarantee the order in which these are processed.
			// We are basically looking for the first connection object which is Not Free... Having a matching CallBack function "identifier string".
			for(var t=0; t<xmlReqsObjArr.length; t++){
			
				if(xmlReqsObjArr[t].reqObjFree || xmlReqsObjArr[t].reqObjParsing || xmlReqsObjArr[t].xmlHttpObj.readyState != 4 || xmlReqsObjArr[t].callBackFunctionName != "ChatTerminatorCall")
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
	}
	
	// GET requests don't have any data to send in the body.
	xmlHttpObjFromGlobalQueue.send(null);
}



ChatTerminator.prototype.private_parseXMLresponse = function(xmlhttpresponseText)
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

	
	this.isCommunicatingFlag = false;
	
	// There is no data that we are expecting in the response, other then <result>OK</result>
	// Call all the "loaded" events that have been subscribed to.
	for(var i=0; i<this.onCompleteEvntFuncs.length; i++){
		this.onCompleteEvntFuncs[i].call(this.onCompleteEvntObjs[i]);
	}
	
	
	
}









