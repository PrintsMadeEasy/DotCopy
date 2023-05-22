function ChatMessageLoader(chatThreadId)
{ this.messageObjectsArr = new Array(); this.acknowledgeMessageIds = new Array(); this.csrAssignedId = 0; this.csrAssignedPname = ""; this.csrAssignedPhotoId = 0; this.chatID = chatThreadId; this.maxMessageIdDownloaded = 0; this.chatStatus = ""; this.chatSubject = ""; this.chatType = ""; this.closedReason = ""; this.customerName = ""; this.customerID = 0; this.customersWaiting = 0; this.userIsCsr = false; this.estimatedWaitSecs = 0; this.allowPleaseWait = false; this.customerIsTyping = false; this.csrIsTyping = false; this.getMessagesInProcess = false; this.onCompleteEvntFuncs = new Array(); this.onCompleteEvntObjs = new Array(); this.onErrEvntFuncs = new Array(); this.onErrEvntObjs = new Array();}
ChatMessageLoader.prototype.setIsTyping = function(isTypingFlag)
{ if(this.userIsCsr){ this.csrIsTyping = isTypingFlag;}
else{ this.customerIsTyping = isTypingFlag;}
}
ChatMessageLoader.prototype.triggerError_Private = function(statusCode, statusDesc)
{ this.getMessagesInProcess = false; for(var i=0; i<this.onErrEvntFuncs.length; i++){ this.onErrEvntFuncs[i].call(this.onErrEvntObjs[i], statusCode, "Error Loading Chat Data: " + statusDesc);}
}
ChatMessageLoader.prototype.attachLoadedEvent = function(functionReference, objectReference)
{ this.onCompleteEvntFuncs.push(functionReference); this.onCompleteEvntObjs.push(objectReference);}
ChatMessageLoader.prototype.attachErrorEvent = function(functionReference, objectReference)
{ this.onErrEvntFuncs.push(functionReference); this.onErrEvntObjs.push(objectReference);}
ChatMessageLoader.prototype.fetchMessagesFromServer = function()
{ if(this.getMessagesInProcess)
return; this.getMessagesInProcess = true; if(this.messageObjectsArr.length != 0)
return; var receiptAcks = ""; for(var i=0; i<this.acknowledgeMessageIds.length; i++)
{ if(!this.acknowledgeMessageIds[i].toString().match(/^\d+$/))
{ alert("Error in method ChatMessageLoader.getMessages. One of the Array elements is not a number."); return;}
if(receiptAcks != "")
receiptAcks += "|"; receiptAcks += this.acknowledgeMessageIds[i];}
if(this.userIsCsr)
var isTypingParameter = this.csrIsTyping ? "true" : "false"; else
var isTypingParameter = this.customerIsTyping ? "true" : "false"; var instance = this; var dateObj = new Date(); var apiURL = "./api_chat.php?api_version=1.1&chat_id="+escape(this.chatID)+"&command=get_messages&is_typing="+isTypingParameter+"&lastMessageId=" + this.maxMessageIdDownloaded + "&message_acks=" + receiptAcks + "&nocache=" + dateObj.getTime(); var xmlHttpObjFromGlobalQueue = getXmlHttpObj("ChatLoader"); if(xmlHttpObjFromGlobalQueue == null)
return; xmlHttpObjFromGlobalQueue.open('GET', apiURL, true); xmlHttpObjFromGlobalQueue.onreadystatechange = function()
{ for(var t=0; t<xmlReqsObjArr.length; t++){ if(xmlReqsObjArr[t].reqObjFree || xmlReqsObjArr[t].reqObjParsing || xmlReqsObjArr[t].xmlHttpObj.readyState != 4 || xmlReqsObjArr[t].callBackFunctionName != "ChatLoader")
continue; if(xmlReqsObjArr[t].xmlHttpObj.status == "200"){ xmlReqsObjArr[t].reqObjParsing = true; instance.private_parseXMLresponse(xmlReqsObjArr[t].xmlHttpObj.responseText);}
else{ instance.triggerError_Private(xmlReqsObjArr[t].xmlHttpObj.status, xmlReqsObjArr[t].xmlHttpObj.statusText);}
xmlReqsObjArr[t].reqObjFree = true; xmlReqsObjArr[t].reqObjParsing = false;}
}
xmlHttpObjFromGlobalQueue.send(null);}
ChatMessageLoader.prototype.getMessageObjs = function(){ if(this.getMessagesInProcess){ alert("You can't call the method getMessageObjs until the communication has finished."); return new Array();}
for(var i=0; i<this.messageObjectsArr.length; i++){ this.acknowledgeMessageIds.push(this.messageObjectsArr[i].msgID); if(parseInt(this.messageObjectsArr[i].msgID) > this.maxMessageIdDownloaded){ this.maxMessageIdDownloaded = parseInt(this.messageObjectsArr[i].msgID);}
}
var returnArr = this.messageObjectsArr; this.messageObjectsArr = new Array(); return returnArr;}
ChatMessageLoader.prototype.private_parseXMLresponse = function(xmlhttpresponseText)
{ if(!xmlhttpresponseText.match(/\<result\>OK\<\/result\>/))
{ var errorMessage = getErrorMessageFromAPIresponseXML(xmlhttpresponseText); if(errorMessage == "")
this.triggerError_Private("5101", "Unknown Error Parsing XML Document"); else
this.triggerError_Private("5102", errorMessage); return;}
var messageObj = null; var messageCounterIndex = -1; var xmlLinesArr = xmlhttpresponseText.split("\n"); for(var lineNo = 0; lineNo < xmlLinesArr.length; lineNo++)
{ if(xmlLinesArr[lineNo].toString().match(/\<message\>/))
{ messageCounterIndex++; this.messageObjectsArr[messageCounterIndex] = new ChatMessage();}
else if(xmlLinesArr[lineNo].toString().match(/\<status\>/))
this.chatStatus = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<subject\>/))
this.chatSubject = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<chat_type\>/))
this.chatType = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<closed_reason\>/))
this.closedReason = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<customer_name\>/))
this.customerName = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<customer_id\>/))
this.customerID = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<estimated_wait\>/))
this.estimatedWaitSecs = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<is_csr\>/))
this.userIsCsr = (getXMLdata(xmlLinesArr[lineNo]) == "true" ? true : false); else if(xmlLinesArr[lineNo].toString().match(/\<csr_assigned_id\>/))
this.csrAssignedId = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<csr_assigned_p_name\>/))
this.csrAssignedPname = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<csr_assigned_photo\>/))
this.csrAssignedPhotoId = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<customers_waiting\>/))
this.customersWaiting = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<allow_please_wait\>/))
this.allowPleaseWait = (getXMLdata(xmlLinesArr[lineNo]) == "true" ? true : false); else if(xmlLinesArr[lineNo].toString().match(/\<customer_is_typing\>/))
this.customerIsTyping = (getXMLdata(xmlLinesArr[lineNo]) == "true" ? true : false); else if(xmlLinesArr[lineNo].toString().match(/\<csr_is_typing\>/))
this.csrIsTyping = (getXMLdata(xmlLinesArr[lineNo]) == "true" ? true : false); else if(xmlLinesArr[lineNo].toString().match(/\<message_id\>/))
this.messageObjectsArr[messageCounterIndex].msgID = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<csr_id\>/))
this.messageObjectsArr[messageCounterIndex].csrID = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<csr_name\>/))
this.messageObjectsArr[messageCounterIndex].csrName = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<csr_only\>/))
this.messageObjectsArr[messageCounterIndex].csrOnly = (getXMLdata(xmlLinesArr[lineNo]) == "true" ? true : false); else if(xmlLinesArr[lineNo].toString().match(/\<csr_p_name\>/))
this.messageObjectsArr[messageCounterIndex].csrPname = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<date\>/))
this.messageObjectsArr[messageCounterIndex].unixDateStamp = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<file_id\>/))
this.messageObjectsArr[messageCounterIndex].fileId = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<file_name\>/))
this.messageObjectsArr[messageCounterIndex].fileName = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<file_size\>/))
this.messageObjectsArr[messageCounterIndex].fileSize = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<message_text\>/))
this.messageObjectsArr[messageCounterIndex].messageText = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<person_type\>/))
this.messageObjectsArr[messageCounterIndex].personType = getXMLdata(xmlLinesArr[lineNo]);}
this.getMessagesInProcess = false; for(var i=0; i<this.onCompleteEvntFuncs.length; i++){ this.onCompleteEvntFuncs[i].call(this.onCompleteEvntObjs[i]);}
}
function ChatMessage()
{ this.msgID = 0; this.csrID = 0; this.customerID = 0; this.customerName = ""; this.csrName = ""; this.csrPname = ""; this.csrOnly = false; this.unixDateStamp = 0; this.fileId = 0; this.fileName = "unknown"; this.fileSize = 0; this.messageText = ""; this.personType = "";}
function ChatMessageSender(chatThreadId)
{ this.messageQueueArr = new Array(); this.messagePrivateArr = new Array(); this.chatID = chatThreadId; this.messageSendInProcess = false; this.onCompleteEvntFuncs = new Array(); this.onCompleteEvntObjs = new Array(); this.onErrEvntFuncs = new Array(); this.onErrEvntObjs = new Array();}
ChatMessageSender.prototype.triggerError_Private = function(statusCode, statusDesc)
{ for(var i=0; i<this.onErrEvntFuncs.length; i++){ this.onErrEvntFuncs[i].call(this.onErrEvntObjs[i], statusCode, "Error Sending Chat Data: " + statusDesc);}
this.messageSendInProcess = false;}
ChatMessageSender.prototype.attachLoadedEvent = function(functionReference, objectReference)
{ this.onCompleteEvntFuncs.push(functionReference); this.onCompleteEvntObjs.push(objectReference);}
ChatMessageSender.prototype.attachErrorEvent = function(functionReference, objectReference)
{ this.onErrEvntFuncs.push(functionReference); this.onErrEvntObjs.push(objectReference);}
ChatMessageSender.prototype.sendMessage = function(messageText, isPrivateFlag)
{ if(messageText == "" || messageText == null)
return; this.messageQueueArr.push(messageText); this.messagePrivateArr.push(isPrivateFlag ? "Y" : "N"); if(this.messageQueueArr.length == 1 || !this.messageSendInProcess)
this.transferMessageQueue_Private();}
ChatMessageSender.prototype.transferMessageQueue_Private = function()
{ if(this.messageQueueArr.length == 0)
return; if(this.messageSendInProcess){ return;}
this.messageSendInProcess = true; var messageToSend = this.messageQueueArr.shift(); var isPrivate = this.messagePrivateArr.shift(); var instance = this; var dateObj = new Date(); var apiURL = "./api_chat.php?api_version=1.1&chat_id="+escape(this.chatID)+"&command=send_message&message_text=" + escape(messageToSend) + "&private=" + escape(isPrivate) + "&nocache=" + dateObj.getTime(); var xmlHttpObjFromGlobalQueue = getXmlHttpObj("ChatSender"); if(xmlHttpObjFromGlobalQueue == null)
return; xmlHttpObjFromGlobalQueue.open('GET', apiURL, true); xmlHttpObjFromGlobalQueue.onreadystatechange = function()
{ for(var t=0; t<xmlReqsObjArr.length; t++){ if(xmlReqsObjArr[t].reqObjFree || xmlReqsObjArr[t].reqObjParsing || xmlReqsObjArr[t].xmlHttpObj.readyState != 4 || xmlReqsObjArr[t].callBackFunctionName != "ChatSender")
continue; if(xmlReqsObjArr[t].xmlHttpObj.status == "200"){ xmlReqsObjArr[t].reqObjParsing = true; instance.private_parseXMLresponse(xmlReqsObjArr[t].xmlHttpObj.responseText);}
else{ instance.triggerError_Private(xmlReqsObjArr[t].xmlHttpObj.status, xmlReqsObjArr[t].xmlHttpObj.statusText);}
xmlReqsObjArr[t].reqObjFree = true; xmlReqsObjArr[t].reqObjParsing = false;}
}
xmlHttpObjFromGlobalQueue.send(null);}
ChatMessageSender.prototype.private_parseXMLresponse = function(xmlhttpresponseText)
{ if(!xmlhttpresponseText.match(/\<result\>OK\<\/result\>/))
{ var errorMessage = getErrorMessageFromAPIresponseXML(xmlhttpresponseText); if(errorMessage == "")
this.triggerError_Private("5101", "Unknown Error Parsing XML Document"); else
this.triggerError_Private("5102", errorMessage); return;}
for(var i=0; i<this.onCompleteEvntFuncs.length; i++){ this.onCompleteEvntFuncs[i].call(this.onCompleteEvntObjs[i]);}
this.messageSendInProcess = false; this.transferMessageQueue_Private();}
function ChatTerminator(chatThreadId)
{ this.isCommunicatingFlag = false; this.chatID = chatThreadId; this.onCompleteEvntFuncs = new Array(); this.onCompleteEvntObjs = new Array(); this.onErrEvntFuncs = new Array(); this.onErrEvntObjs = new Array();}
ChatTerminator.prototype.triggerError_Private = function(statusCode, statusDesc)
{ for(var i=0; i<this.onErrEvntFuncs.length; i++){ this.onErrEvntFuncs[i].call(this.onErrEvntObjs[i], statusCode, "Error Sending Chat Data: " + statusDesc);}
this.isCommunicatingFlag = false;}
ChatTerminator.prototype.attachLoadedEvent = function(functionReference, objectReference)
{ this.onCompleteEvntFuncs.push(functionReference); this.onCompleteEvntObjs.push(objectReference);}
ChatTerminator.prototype.attachErrorEvent = function(functionReference, objectReference)
{ this.onErrEvntFuncs.push(functionReference); this.onErrEvntObjs.push(objectReference);}
ChatTerminator.prototype.closeChatThread = function(asyncFlag)
{ if (arguments.length == 0){ asyncFlag = true;}
else{ asyncFlag = Boolean(asyncFlag);}
if(this.isCommunicatingFlag){ return;}
this.isCommunicatingFlag = true; var instance = this; var dateObj = new Date(); var apiURL = "./api_chat.php?api_version=1.1&chat_id="+escape(this.chatID)+"&command=close_chat_thread&nocache=" + dateObj.getTime(); var xmlHttpObjFromGlobalQueue = getXmlHttpObj("ChatTerminatorCall"); if(xmlHttpObjFromGlobalQueue == null)
return; xmlHttpObjFromGlobalQueue.open('GET', apiURL, asyncFlag); if(asyncFlag){ xmlHttpObjFromGlobalQueue.onreadystatechange = function()
{ for(var t=0; t<xmlReqsObjArr.length; t++){ if(xmlReqsObjArr[t].reqObjFree || xmlReqsObjArr[t].reqObjParsing || xmlReqsObjArr[t].xmlHttpObj.readyState != 4 || xmlReqsObjArr[t].callBackFunctionName != "ChatTerminatorCall")
continue; if(xmlReqsObjArr[t].xmlHttpObj.status == "200"){ xmlReqsObjArr[t].reqObjParsing = true; instance.private_parseXMLresponse(xmlReqsObjArr[t].xmlHttpObj.responseText);}
else{ instance.triggerError_Private(xmlReqsObjArr[t].xmlHttpObj.status, xmlReqsObjArr[t].xmlHttpObj.statusText);}
xmlReqsObjArr[t].reqObjFree = true; xmlReqsObjArr[t].reqObjParsing = false;}
}
}
xmlHttpObjFromGlobalQueue.send(null);}
ChatTerminator.prototype.private_parseXMLresponse = function(xmlhttpresponseText)
{ if(!xmlhttpresponseText.match(/\<result\>OK\<\/result\>/))
{ var errorMessage = getErrorMessageFromAPIresponseXML(xmlhttpresponseText); if(errorMessage == "")
this.triggerError_Private("5101", "Unknown Error Parsing XML Document"); else
this.triggerError_Private("5102", errorMessage); return;}
this.isCommunicatingFlag = false; for(var i=0; i<this.onCompleteEvntFuncs.length; i++){ this.onCompleteEvntFuncs[i].call(this.onCompleteEvntObjs[i]);}
}
