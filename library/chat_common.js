


var chatLoaderObj = new ChatMessageLoader(chatThreadId);
var chatSenderObj = new ChatMessageSender(chatThreadId);
var chatTerminatorObj = new ChatTerminator(chatThreadId);


var lastTypingTimeStamp = 0;

function keyUpOnMessageBox(){
	var jsDateObj = new Date();
	lastTypingTimeStamp = (jsDateObj.getTime() / 1000);
}
// Return false (that the user is not typing) if the message box is empty... or if it has been 15 seconds since they last punched the keyboard.
function isUserTyping(){
	var messageText = document.getElementById("newMessage").value;
	if(messageText == "")
		return false;
		
	var jsDateObj = new Date();
	var currentTimeStamp = (jsDateObj.getTime() / 1000);
	
	var secondsSinceLastTyping = currentTimeStamp - lastTypingTimeStamp;
	if(secondsSinceLastTyping < 15)
		return true;
	else
		return false;
}

function getFlashMovieObject(movieName)
{
  if (window.document[movieName]) 
  {
      return window.document[movieName];
  }
  if (navigator.appName.indexOf("Microsoft Internet")==-1)
  {
    if (document.embeds && document.embeds[movieName])
      return document.embeds[movieName]; 
  }
  else // if (navigator.appName.indexOf("Microsoft Internet")!=-1)
  {
    return document.getElementById(movieName);
  }
}

var totalMessagesDisplayed = 0;

function startNewChatSession(){
	document.location = "chat_session.php?action=create&chat_type=" + chatType;
}

function addHtmlToMessageWindow(htmlMessage){

	var messageWindowDiv = document.getElementById("messageWindow");
	messageWindowDiv.innerHTML += htmlMessage;
		
	// Scroll to bottom.
	messageWindowDiv.scrollTop = messageWindowDiv.scrollHeight;
}


function disableSendButton(){
	document.getElementById("sendButton").disabled = true;
	document.getElementById("newMessage").value = "";
	document.getElementById("newMessage").disabled = true;
	try{
		document.getElementById("sendButtonPrivate").disabled = true;
	}
	catch(e){}
}
function enableSendButton(){
	document.getElementById("sendButton").disabled = false;
	document.getElementById("newMessage").disabled = false;
	try{
		document.getElementById("sendButtonPrivate").disabled = false;
	}
	catch(e){}
}

function formatServerMessage(messageText){
	messageText = messageText.replace(/<br>/g, "\n");
	messageText = htmlize(messageText);
	messageText = messageText.replace(/\n/g, "<br />");
	
	//messageText = messageText.replace(/:\)/g, "[SMILEY]");
	//messageText = messageText.replace(/:\(/g, "[FROWN]");
	
	return messageText;	
}




// If there is an error polling the server we don't want to keep filling up the chat window with back-to-back error messages.
var duplicateLoaderErrorEvent = 0;

function chatLoaderErrorEvent(statusCode, errorMsg){
	if(duplicateLoaderErrorEvent > 0){
		return;
	}
	
	duplicateLoaderErrorEvent++;
	
	// Stop polling the server after too many consecutie errors or the Exception log could fill up too large.
	if(duplicateLoaderErrorEvent > 20){
		stopPolling();
	}
	
	var htmlMessage = "<span class='chatError'>" + formatServerMessage(errorMsg) + "</span>";
	document.getElementById("messageWindow").innerHTML += htmlMessage;
}


chatLoaderObj.attachLoadedEvent(chatLoaderCompleteEvent);
chatLoaderObj.attachErrorEvent(chatLoaderErrorEvent);


function chatSenderCompleteEvent(){
	
	// After a message has been sent... try to immediadetly download the message (so the user doesn't have to wait for the "polling"
	
	// Let the server know if the user is currently typing every time that we poll the server.
	chatLoaderObj.setIsTyping(isUserTyping());

	
	chatLoaderObj.fetchMessagesFromServer();
}
function chatSenderErrorEvent(statusCode, errorMsg){
	var htmlMessage = "<p><span class='chatError'>" + formatServerMessage(errorMsg) + "</span></p>";
	document.getElementById("messageWindow").innerHTML += htmlMessage;
}


var lastHtmlSource = "";
function displayCsrInfo(){

	var htmlSource = "";
	if(chatLoaderObj.csrAssignedId == 0){
		htmlSource = "Waiting to be assigned.";
	}
	else{
		htmlSource = "";
		if(chatLoaderObj.csrAssignedPhotoId != 0)
			htmlSource += "<img src='chat_csr_photo.php?id="+chatLoaderObj.csrAssignedPhotoId+"' /><br />";
		htmlSource += htmlize(chatLoaderObj.csrAssignedPname);
	}
	
	// Don't keep setting the same HTML source or IE will flicker.
	if(lastHtmlSource != htmlSource){
		document.getElementById("csrDescription").innerHTML = htmlSource;
		lastHtmlSource = htmlSource;
	}
}



chatSenderObj.attachLoadedEvent(chatSenderCompleteEvent);
chatSenderObj.attachErrorEvent(chatSenderErrorEvent);



function pollServer(){
	if(chatThreadId == "0")
		return;
	
	// Let the server know if the user is currently typing every time that we poll the server.
	chatLoaderObj.setIsTyping(isUserTyping());
	
	chatLoaderObj.fetchMessagesFromServer();
}
function stopPolling(){
	clearInterval(pollingInterval);

}

function SendIt(){
	var messageText = document.getElementById("newMessage").value;
	chatSenderObj.sendMessage(messageText, false);
	document.getElementById("newMessage").value = "";
}


var pollingInterval = setInterval("pollServer()", 1500);
