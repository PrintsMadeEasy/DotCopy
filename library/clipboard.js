
function getXMLcomObj(){
	
	var xmlHttpObj = null;
	
	if(window.XMLHttpRequest) 
	{
		
		try { 
			xmlHttpObj = new XMLHttpRequest(); 
		} 
	
		catch(e) 
		{ 
			xmlHttpObj = null; 
		}
	
	} 
	
	// Modern IE browsers now support new XMLHttpRequest() natively... so  no point in maintaing these version numbers any further.
	else if(window.ActiveXObject)
	{
	
		try { 
			xmlHttpObj = new ActiveXObject('Msxml2.XMLHTTP.3.0'); 
		} 
		catch(e) 
		{
			try { 
				xmlHttpObj = new ActiveXObject('Msxml2.XMLHTTP'); 
			} 
			catch(e) 
			{
				try { 
					xmlHttpObj = new ActiveXObject('Microsoft.XMLHTTP'); 
				} 
				catch(e) 
				{ 
					xmlHttpObj = null; 
				}
			} 
		}
	}
	
	return xmlHttpObj;
}

var buttonObjGlobal = null;
function CopyToClipboard(ButtonObj, View, ProjectID){

	buttonObjGlobal = ButtonObj;
	
	dateObj = new Date();
	var NoCache = dateObj.getTime();

	var SaveLoc = "./clipboard_actions.php?command=copyall&projectid=" + ProjectID + "&view=" + View + "&nocache=" + NoCache;

	//Load the XML doc from the server... this will save information in the background
	var xmlDoc=getXMLcomObj();
	xmlDoc.open('GET', SaveLoc, true);


	// Micosoft does not like you setting the Ready State before calling "open".
	xmlDoc.onreadystatechange = function() 
	{
	
		if(xmlDoc.readyState != 4 )
			return;
				
		if(xmlDoc.status == "200")
		{
			pasrseClipboardResponse(xmlDoc.responseText);
		}
		else
		{
			alert("Error sending to the clipboard.");
		}
	}
	

	xmlDoc.send(null);
}


function pasrseClipboardResponse(responseText){
	

	var ServerResponse = "";
	var ErrorDescription = "";

	
	if(responseText.match(/<success>good<\/success>/)){
		buttonObjGlobal.value = "Copy OK";
	}
	else{
		alert("A communication error occured.");
	}


}