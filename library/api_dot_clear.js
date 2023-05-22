// GET VERSION NUMBER FROM CVS when naming the compressed version

// Compress this script by going to...  http://www.creativyst.com/Prod/3/
// Then copy the output into api_dot.js

// A Global Array meant to hold instances of httpReqObj objects that may be re-used for Simultanaeous requests on Multiple Differen Objects.
var xmlReqsObjArr = new Array();



// A Class. Create instances of this object
// It will hold references to new XMLHttpRequest objects, as well as whether or not that object can be re-used
// We need to know the name of the CallBack function that will be used... otherwise we won't know which Class this Request was Made by.
// .... If Multiple Objects with different callback functions (classes) are using this global pool of connection ojbects, the XMLHttpRequest doesn't say where it came from.
// There is no way to guarantee which order the requests come back.
function httpReqObj(callBckFuncName)
{

	this.reqObjFree = false;
	this.reqObjParsing = false;
	this.xmlHttpObj = false;
	this.callBackFunctionName = callBckFuncName;


	if(window.XMLHttpRequest) 
	{
		
		try { 
			this.xmlHttpObj = new XMLHttpRequest(); 
		} 
	
		catch(e) 
		{ 
			this.xmlHttpObj = null; 
		}
	
	} 
	
	// Modern IE browsers now support new XMLHttpRequest() natively... so  no point in maintaing these version numbers any further.
	else if(window.ActiveXObject)
	{
	
		try { 
			this.xmlHttpObj = new ActiveXObject('Msxml2.XMLHTTP.3.0'); 
		} 
		catch(e) 
		{
			try { 
				this.xmlHttpObj = new ActiveXObject('Msxml2.XMLHTTP'); 
			} 
			catch(e) 
			{
				try { 
					this.xmlHttpObj = new ActiveXObject('Microsoft.XMLHTTP'); 
				} 
				catch(e) 
				{ 
					this.xmlHttpObj = null; 
				}
			} 
		}
	}

}





// This will return the next free xmlRequestObject
// Of it will create a new one if there aren't any free.
function getXmlHttpObj(callBckFuncName)
{

	for(var k=0; k<xmlReqsObjArr.length; k++)
	{
	
		// We can re-use an existing xmlHTTPobject.
		// First mark it as not Free before returning it.
		if(xmlReqsObjArr[k].reqObjFree){
			
			// We are going to re-use this connection object... so mark it as not available.
			xmlReqsObjArr[k].reqObjFree = false;
			
			// We may have a different callback function for this Request... compared to what we used it for last time.
			xmlReqsObjArr[k].callBackFunctionName = callBckFuncName;
			
			
			// Return the connection object from our Global Array by reference
			return xmlReqsObjArr[k].xmlHttpObj;
		}
	}
	
	
	
	// Return the connection object from our Global Array.... that we are just creating / inserting.
	// Javascript will return it By Reference.
	var newIndex = xmlReqsObjArr.length;
	xmlReqsObjArr[newIndex] = new httpReqObj(callBckFuncName);

	if(xmlReqsObjArr[newIndex].xmlHttpObj == null)
		alert("Can not create an XML HTTP connection. Your browser may be incompatible.");
	
	return xmlReqsObjArr[newIndex].xmlHttpObj;
}



























// ProjectLoader Class
// Use this to fetch the Project IDs
function ProjectLoader()
{

	this.cachedProjectObjectsArr = new Array();
	// A Parralell array to the cachedProjectObjectsArr that contains the Project IDs
	this.cachedProjectObjectsPointerArr = new Array();
	
	this.productLoader = new ProductLoader();
	
	// An array of events to subscribe upon loading complete.
	this.onLoadingCompleteEventsFunctionRefArr = new Array();
	this.onLoadingCompleteEventsObjectRefArr = new Array();
	
	this.onProjectRefreshCompleteEventsFunctionRefArr = new Array();
	this.onProjectRefreshCompleteEventsObjectRefArr = new Array();
	
	this.onLoaderErrorEventsFunctionRefArr = new Array();
	this.onLoaderErrorEventsObjectRefArr = new Array();
	
	this.projectRefreshLoader = null;
	this.projectIsBeingRefreshed = false;
	
	this.projectLoaderWasUsed = false;
		
		
	// Loading a Project requires us to load a Product Object, catch any errors downloading the Product definition.
	this.productLoader.attatchProductLoadingErrorEvent(this.catchProductLoadingError, this);
	
	this.productLoader.attachProductLoadedGlobalEvent(this.catchProductLoadedEvent, this)
		
	// Pass in a function name that will be called after the loading as completed.
	// It will wait for all Projects and all Product Objects to finish downlading before event is fired.
	// Example of how to subscribe to the event within the calling script...
	// projectLoaderObj.attachProjectsLoadedEvent(customFunc, this); function customFunc(){alert("Loading Complete");}	
	
	
	// Event handler for Errors.
	// Receiving function should take 2 parameters.   "errorCode" and "errorDescription"
	// Example of how to subscribe to the event within the calling script...
	// projectLoaderObj.attachLoaderErrorEvent(customFunc, this); function customFunc(errCode, errDesc){alert("Project loading ERROR: " + errCode + " - " + errDesc);}	
	
}



// Catch any errors while downloading Product Objects... and feed them into the Error Event for the Project Loader.
ProjectLoader.prototype.onProjectLoaderError_Private = function(statusCode, statusDesc)
{

	// First make sure that we have an Error Event handler defined for the Project Loader class.
	for(var i=0; i<this.onLoaderErrorEventsFunctionRefArr.length; i++){
		this.onLoaderErrorEventsFunctionRefArr[i].call(this.onLoaderErrorEventsObjectRefArr[i], statusCode, "Error Loading Project Object: " + statusDesc);
	}
}


ProjectLoader.prototype.catchProductLoadingError = function(statusCode, statusDesc)
{	
	this.onProjectLoaderError_Private(statusCode, "Product Loading Error: " + statusDesc);

}
ProjectLoader.prototype.catchProductLoadedEvent = function(productID)
{
	// Every time another Product has finished loading... it could be the last one.
	// This method will store a copy of the Product Objects inside of the Project Objects.
	// Once the last product in the Project List has been initialized... it will fire the "Complete" even for this project loader object.
	this.initProductObjsAndMaybeFireCompleteEvent_Private();

}





// Better off avoiding this method, like considering it private.
// If you will be thrashing around... loading Project ID's at random... many different ones and quickly (like mouse-overs on a very large list).  
// .. then is better to load 1 (or a few) projects at at time and use separate Project Loaders (keeping track in a local array) and avoid use this method (by using the built-in event model).
// Otherwise it is better to load all projects at once and wait for the "projectLoadedEvent" to fire.
ProjectLoader.prototype.checkIfProjectLoaded = function(projectID)
{

	if(this.getIndexOfProjectCacheArr(projectID) < 0)
		return false;
	else
		return true;

}

// Returns -1 if the Project ID hasn't been cached yet.
// Otherwise returns index number (zero based)
ProjectLoader.prototype.getIndexOfProjectCacheArr = function(projectID)
{

	for(var i=0; i<this.cachedProjectObjectsPointerArr.length; i++){
		if(this.cachedProjectObjectsPointerArr[i] == projectID)
			return i;
	}
	
	return -1;

}


ProjectLoader.prototype.getProjectObj = function(projectID)
{

	if(!this.checkIfProjectLoaded(projectID))
	{
		alert("Error in method getProjectObj. You can't call this method until the Object has finished loading.");
		return false;
	}
	
	return this.cachedProjectObjectsArr[this.getIndexOfProjectCacheArr(projectID)];

}


ProjectLoader.prototype.attachProjectsLoadedEvent = function(functionReference, objectReference)
{
	this.onLoadingCompleteEventsFunctionRefArr.push(functionReference);
	this.onLoadingCompleteEventsObjectRefArr.push(objectReference);

}

// The event will be fired and the Project ID will be sent as a parameter.
ProjectLoader.prototype.attachProjectRefreshedEvent = function(functionReference, objectReference)
{
	this.onProjectRefreshCompleteEventsFunctionRefArr.push(functionReference);
	this.onProjectRefreshCompleteEventsObjectRefArr.push(objectReference);
}

// The event will be fired when there is an error.
ProjectLoader.prototype.attachLoaderErrorEvent = function(functionReference, objectReference)
{
	this.onLoaderErrorEventsFunctionRefArr.push(functionReference);
	this.onLoaderErrorEventsObjectRefArr.push(objectReference);
}

// This is really a private method. Subscibe to Project Refresh event by calling attachProjectRefreshedEvent
ProjectLoader.prototype.projectRefreshFinished = function()
{

	this.projectIsBeingRefreshed = false;

	var projectIDsArr = this.projectRefreshLoader.getProjectIDsLoaded();
	var projectIdToRefresh = projectIDsArr[0];
	var refreshedProjectObj = this.projectRefreshLoader.getProjectObj(projectIdToRefresh);

	this.cachedProjectObjectsArr[this.getIndexOfProjectCacheArr(projectIdToRefresh)] = refreshedProjectObj;

	for(var i=0; i<this.onProjectRefreshCompleteEventsFunctionRefArr.length; i++)
	{
		this.onProjectRefreshCompleteEventsFunctionRefArr[i].call(this.onProjectRefreshCompleteEventsObjectRefArr[i], projectIdToRefresh);
	}
}


ProjectLoader.prototype.refreshProject = function(projectID)
{
	if(this.projectIsBeingRefreshed)
	{
		alert("Multiple Project Refreshes are not permitted");
		return;
	}
	this.projectIsBeingRefreshed = true;
	
	this.projectRefreshLoader = new ProjectLoader();
		
	if(this.getIndexOfProjectCacheArr(projectID) < 0)
		alert("Error: The Project ID can not be refreshed because it hasn't been loaded yet: " + projectID);
		
	var projectObjToRefresh = this.getProjectObj(projectID);
	this.projectRefreshLoader.attachProjectsLoadedEvent(this.projectRefreshFinished, this);

	var singleProjectArr = new Array();
	singleProjectArr.push(projectID);

	this.projectRefreshLoader.loadProjects(singleProjectArr, projectObjToRefresh.getProjectView());

}





// After you are done parsing the XML for the Project lists... then you need to load all of the Product Objects associated with the projects.
ProjectLoader.prototype.loadProductObjects = function()
{


	
	for(var i = 0; i< this.cachedProjectObjectsArr.length; i++)
	{

		this.productLoader.loadProductID(this.cachedProjectObjectsArr[i].productID);
	}
	
}



ProjectLoader.prototype.initProductObjsAndMaybeFireCompleteEvent_Private = function()
{
	var allProductInitialized = true;
	
	for(var i = 0; i < this.cachedProjectObjectsArr.length; i++)
	{
		var productIDofProject = this.cachedProjectObjectsArr[i].productID;
		
		if(this.productLoader.checkIfProductInitialized(productIDofProject))
		{
			// Since the Product is intialized... make sure that the project Object has a copy of the Product Object inside.
			if(this.cachedProjectObjectsArr[i].productObj == null)	
				this.cachedProjectObjectsArr[i].productObj = this.productLoader.getProductObj(productIDofProject);
		}
		else
		{
			allProductInitialized = false;
		}
			
	}	

	

	// Only Fire the Project Loader "Complete" event once every product has been loaded.
	if(allProductInitialized)
	{
		// Fire off events that that were subscribed to.
		for(var i=0; i<this.onLoadingCompleteEventsFunctionRefArr.length; i++)
		{
			this.onLoadingCompleteEventsFunctionRefArr[i].call(this.onLoadingCompleteEventsObjectRefArr[i]);
		}
	}

}




// Pass in an Array of Project IDs that you want to load.
// You can load multiple projects at 1 time. Make sure you do not try to retrieve any projects before they are done loading.
// Generally, when loading a batch of Project ID's, define an EventHandler for the "completion".. and wait for that.
ProjectLoader.prototype.loadProjects = function(projectArr, projectViewType)
{
	
	if(typeof projectArr != 'object')
	{
		alert("Error in method ProjectLoader.loadProjects. The parameter passed to this method must be an array.");
		return;
	}
	
	if(this.projectLoaderWasUsed){
		alert("Project Loaders may only be used once, but you can create many loader objects. It is possible to use this object to refresh a Project which has already been loaded.");
		return;
	}

	this.projectLoaderWasUsed = true;
	
	// Make a pipe-delimted list from the array.
	var projectListStr = "";
	
	for(var i=0; i<projectArr.length; i++)
	{	
		if(!projectArr[i].toString().match(/^\d+$/))
		{
			alert("Error in method ProjectLoader.loadProjects. One of the Array elements is not a number.");
			return;
		}

		if(projectListStr != "")
			projectListStr += "|";
		
		projectListStr += projectArr[i];
	}
	
	if(projectListStr == "")
	{
		alert("Error in method ProjectLoader.loadProjects. The Project List is empty.");
		return;
	}

	
	// So we can scope the callback.
	var instance = this;
	
	var dateObj = new Date();
	var apiURL = "./api_dot.php?api_version=1.1&command=get_project_list&project_id_list=" + projectListStr + "&view_type=" +  projectViewType + "&nocache=" + dateObj.getTime();

	// Get a free/new connection object by reference. Abort method call if an HTTP object cant be created. The method getXmlHttpObj will alert an error message too.
	var xmlHttpObjFromGlobalQueue = getXmlHttpObj("ProjectLoader");
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
		
			if(xmlReqsObjArr[t].reqObjFree || xmlReqsObjArr[t].reqObjParsing || xmlReqsObjArr[t].xmlHttpObj.readyState != 4 || xmlReqsObjArr[t].callBackFunctionName != "ProjectLoader")
				continue;
			
			// Find out if there was a server or 404 error, etc.
			if(xmlReqsObjArr[t].xmlHttpObj.status == "200"){
				xmlReqsObjArr[t].reqObjParsing = true;
				instance.private_parseXMLresponse(xmlReqsObjArr[t].xmlHttpObj.responseText);
			}
			else{
				instance.onProjectLoaderError_Private(xmlReqsObjArr[t].xmlHttpObj.status, xmlReqsObjArr[t].xmlHttpObj.statusText);
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
ProjectLoader.prototype.getProjectIDsLoaded = function(){
	return this.cachedProjectObjectsPointerArr;
}



ProjectLoader.prototype.private_parseXMLresponse = function(xmlhttpresponseText)
{
	// Find out if the document was not what we expected to receive.
	// If Not, try to get an Error message and sent that through an error event.
	if(!xmlhttpresponseText.match(/\<result\>OK\<\/result\>/))
	{
		// Look for an error message in the response, since we didn't get an "OK"
		var errorMessage = getErrorMessageFromAPIresponseXML(xmlhttpresponseText);

		if(errorMessage == "")
			this.onProjectLoaderError_Private("5101", "Unknown Error Parsing XML Document");
		else
			this.onProjectLoaderError_Private("5102", errorMessage);

		return;
	}
	

	
	var projectObj = null;
	var projectOrderedLinksIndex = null;

	var xmlLinesArr = xmlhttpresponseText.split("\n");

	for(var lineNo = 0; lineNo < xmlLinesArr.length; lineNo++)
	{
		// Every Time that we come accross an opening Project tag... we are going to start building a new Project Object
		// Every time we come across the closing tag, we are going to add it to our array.
		if(xmlLinesArr[lineNo].toString().match(/\<project\>/))
		{
			// Build a Product Object and fill it from data returned by the server.
			var projectObj = new Project();

			// Keep Counters so that when we run across sub-nodes we can keep building the array.
			var projectOrderedLinksIndex = -1;
			var projectSessionLinksIndex = -1;
			var templateProductLinksIndex = -1;
			var templateLinkIndex = -1;
		}
		
		
		else if(xmlLinesArr[lineNo].toString().match(/\<form_security_code\>/))
			projectObj.formSecurityCode = getXMLdata(xmlLinesArr[lineNo]);
		
		else if(xmlLinesArr[lineNo].toString().match(/\<project_view\>/))
			projectObj.projectView = getXMLdata(xmlLinesArr[lineNo]);
		
		else if(xmlLinesArr[lineNo].toString().match(/\<project_id\>/))
			projectObj.projectID = getXMLdata(xmlLinesArr[lineNo]);
	
		else if(xmlLinesArr[lineNo].toString().match(/\<date_modified\>/))
			projectObj.dateModifiedUnixTimestamp = getXMLdata(xmlLinesArr[lineNo]);

		else if(xmlLinesArr[lineNo].toString().match(/\<thumbnail_sc\>/))
			projectObj.thumbnailSC = getXMLdata(xmlLinesArr[lineNo]);

		else if(xmlLinesArr[lineNo].toString().match(/\<thumbnail_cache\>/))
			projectObj.thumbnailCache = getXMLdata(xmlLinesArr[lineNo]);

		else if(xmlLinesArr[lineNo].toString().match(/\<template_id\>/))
			projectObj.templateId = getXMLdata(xmlLinesArr[lineNo]);

		else if(xmlLinesArr[lineNo].toString().match(/\<template_area\>/))
			projectObj.templateArea = getXMLdata(xmlLinesArr[lineNo]);

		else if(xmlLinesArr[lineNo].toString().match(/\<product_id\>/))
			projectObj.productID = getXMLdata(xmlLinesArr[lineNo]);	
			
		else if(xmlLinesArr[lineNo].toString().match(/\<quantity\>/))
			projectObj.selectedOptionsObj.setQuantity(getXMLdata(xmlLinesArr[lineNo]));	
	
		else if(xmlLinesArr[lineNo].toString().match(/\<options\>/))
			projectObj.selectedOptionsObj.parseOptionsDescription(getXMLdata(xmlLinesArr[lineNo]));	
	
		else if(xmlLinesArr[lineNo].toString().match(/\<variable_data_status\>/))
			projectObj.variableDataStatus = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<variable_data_message\>/))
			projectObj.variableDataMessage = getXMLdata(xmlLinesArr[lineNo]);	
			
		else if(xmlLinesArr[lineNo].toString().match(/\<artwork_is_copied\>/))
			projectObj.artworkIsCopied = (getXMLdata(xmlLinesArr[lineNo]) == "yes" ? true : false);
	
		else if(xmlLinesArr[lineNo].toString().match(/\<artwork_is_transfered\>/))
			projectObj.artworkIsTransfered = (getXMLdata(xmlLinesArr[lineNo]) == "yes" ? true : false);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<project_in_cart\>/))
			projectObj.isInShoppingCart = (getXMLdata(xmlLinesArr[lineNo]) == "yes" ? true : false);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<project_saved_link\>/))
			projectObj.savedProjectLink = getXMLdata(xmlLinesArr[lineNo]);



		// Project Ordered specific nodes.
		else if(xmlLinesArr[lineNo].toString().match(/\<order_id\>/))
			projectObj.orderID = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<arrival_date\>/))
			projectObj.arrivalDateUnixTimestamp = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<project_discount\>/))
			projectObj.projectDiscount = getXMLdata(xmlLinesArr[lineNo]);

		else if(xmlLinesArr[lineNo].toString().match(/\<project_subtotal\>/))
			projectObj.subtotalFromDatabase = getXMLdata(xmlLinesArr[lineNo]);

		else if(xmlLinesArr[lineNo].toString().match(/\<project_tax\>/))
			projectObj.projectTax = getXMLdata(xmlLinesArr[lineNo]);

		else if(xmlLinesArr[lineNo].toString().match(/\<status_char\>/))
			projectObj.statusChar = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<status_description\>/))
			projectObj.statusDesc = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<can_edit_artwork_still\>/))
			projectObj.artworkCanStillBeEdited = (getXMLdata(xmlLinesArr[lineNo]) == "yes" ? true : false);


	
		// When we come across a new opening "project links" tag... increment our counter... because there may be more to come... and we will keep adding to the array
		else if(xmlLinesArr[lineNo].toString().match(/\<project_ordered_links\>/))
		{
			projectOrderedLinksIndex++;
			projectObj.orderedProjectLinks[projectOrderedLinksIndex] = getXMLdata(xmlLinesArr[lineNo]);
		}
		else if(xmlLinesArr[lineNo].toString().match(/\<project_session_links\>/))
		{
			projectSessionLinksIndex++;
			projectObj.sessionProjectLinks[projectSessionLinksIndex] = getXMLdata(xmlLinesArr[lineNo]);
		}
		else if(xmlLinesArr[lineNo].toString().match(/\<product_id_linked\>/))
		{
			templateProductLinksIndex++;
			projectObj.templateProductLinks[templateProductLinksIndex] = new TemplateLink();
	
			projectObj.templateProductLinks[templateProductLinksIndex].productID = getXMLdata(xmlLinesArr[lineNo]);
			templateLinkIndex = -1;
		}
		else if(xmlLinesArr[lineNo].toString().match(/\<template_link_id\>/))
		{
			templateLinkIndex++;
			projectObj.templateProductLinks[templateProductLinksIndex].templateIdsArr[templateLinkIndex] = getXMLdata(xmlLinesArr[lineNo]);
		}
		else if(xmlLinesArr[lineNo].toString().match(/\<template_link_area\>/))
		{
			projectObj.templateProductLinks[templateProductLinksIndex].templateAreasArr[templateLinkIndex] = getXMLdata(xmlLinesArr[lineNo]);
		}
	
		else if(xmlLinesArr[lineNo].toString().match(/\<template_link_preview_ids\>/))
		{
			projectObj.templateProductLinks[templateProductLinksIndex].templatePreviewIdsArr[templateLinkIndex] = getXMLdata(xmlLinesArr[lineNo]);
		}
		else if(xmlLinesArr[lineNo].toString().match(/\<template_link_side_names\>/))
		{
			projectObj.templateProductLinks[templateProductLinksIndex].templatePreviewSideNamesArr[templateLinkIndex] = getXMLdata(xmlLinesArr[lineNo]);
		}
		else if(xmlLinesArr[lineNo].toString().match(/\<template_link_preview_widths\>/))
		{
			projectObj.templateProductLinks[templateProductLinksIndex].templatePreviewWidthsArr[templateLinkIndex] = getXMLdata(xmlLinesArr[lineNo]);
		}
		else if(xmlLinesArr[lineNo].toString().match(/\<template_link_preview_heights\>/))
		{
			projectObj.templateProductLinks[templateProductLinksIndex].templatePreviewHeightsArr[templateLinkIndex] = getXMLdata(xmlLinesArr[lineNo]);
		}

	
		// When we come across our closing Project Tag, then we can add the Project Object to our array.
		else if(xmlLinesArr[lineNo].toString().match(/\<\/project\>/))
		{
		
			// Just do a little extra check to make sure that member variables were populated. Just pick one of them to check.
			// The Product ID is very important, since we will try to load the Product Object from this immediately after parsing the Project list.
			if(!projectObj.productID.match(/^\d+$/) || !projectObj.projectID.match(/^\d+$/))
			{
				this.onProjectLoaderError_Private("5108", "Project Definition incomplete.");
				return;
			}

			var projectIDint = parseInt(projectObj.projectID);
			
			// Add to the Paralell array of Cached Project Objects.
			var currentIndexOfCachedProjectsArr = this.cachedProjectObjectsPointerArr.length;
			
			this.cachedProjectObjectsPointerArr[currentIndexOfCachedProjectsArr] = projectIDint;
			this.cachedProjectObjectsArr[currentIndexOfCachedProjectsArr] = projectObj;

		}
	}
	


	// Now that parsing the Project List is complete, load all of the Product Objects and make sure they are loaded.
	// Once the Product Objects have been loaded... then this instance of the ProjectLoader will fire off a "completed" event.
	this.loadProductObjects();
	
	
}












// ----------------------------------   Project Class ----------------------

function Project()
{

	this.projectID = 0;
	this.productID = 0;
	
	this.dateModifiedUnixTimestamp = 0;
	
	this.savedProjectLink = 0;
	this.newProjectSessionID = 0;
	this.orderedProjectLinks = new Array();
	this.sessionProjectLinks = new Array();
	
	this.templateId = 0;
	this.templateArea = "";
	
	this.templateProductLinks = new Array();
	
	this.updateThumbnailEventFunctions = new Array();
	this.updateThumbnailEventObjects = new Array();
	
	this.shoppingCartAddEventFunctions = new Array();
	this.shoppingCartAddEventObjects = new Array();

	this.shoppingCartRemoveEventFunctions = new Array();
	this.shoppingCartRemoveEventObjects = new Array();
	
	this.updateOptionsEventFunctions = new Array();
	this.updateOptionsEventObjects = new Array();
	
	this.productConvertEventFunctions = new Array();
	this.productConvertEventObjects = new Array();
	
	this.productTransferEventFunctions = new Array();
	this.productTransferEventObjects = new Array();
	
	this.thumbnailSC = "";
	this.thumbnailCache = "";
	
	this.variableDataStatus = "";
	this.variableDataMessage = "";
	
	this.artworkIsCopied = false;
	this.artworkIsTransfered = false;
	
	this.isInShoppingCart = false;
	
	this.artThumbNeedsUpdateFlag = false;

	this.projectView = "must override this variable";
	
	// This will contain a reference to the Product Object for this Product.
	this.productObj = null;
	
	// This will hold the Option/Choice selections and the quantity.
	this.selectedOptionsObj = new SelectedOptions();
	
	
	// The server issues this code when download Project Details. 
	// We have to send the code back to the API whenever we make a change to a project to prevent cross-site forgery requests.
	this.formSecurityCode = null;
	
	// Variables proprietory to Projects Ordered
	this.orderID = 0;
	this.subtotalFromDatabase = 0;
	this.projectDiscount = 0;
	this.projectTax = 0;
	this.arrivalDateUnixTimestamp = 0
	this.statusChar = "";
	this.statusDesc = "";
	this.artworkCanStillBeEdited = true;
	
	
	// The Event name to subscribe to so that you know if a Project succesfully saved it options to the database.
	// The event will take 3 parameters. 1) The Project ID that was updated... 2) A boolean value for success/failure... 3) An error message if the second paramater returns FALSE
	// Example of how to subscribe to the event within the calling script...
	// projectObj.attachUpdateProjectOptionsEvent(customFunc, this); function customFunc(projectID, successFlag, errMessage){ if(successFlag) alert("ProjectID" + projectID + " was updated OK."); else alert("ERROR: ProjectID" + projectID + " could not be updated: " + errMessage);}	

	// The Event name to subscribe to so that you know if a Projects' Thumbnail Image was successfully generated.
	// The event will take 3 parameters. 1) The Project ID that was updated... 2) A boolean value for success/failure... 3) An error message if the second paramater returns FALSE
	// Example of how to subscribe to the event within the calling script...
	// projectObj.attachThumbnailUpdateEvent(customFunc, this); function customFunc(projectID, successFlag, errMessage){ if(successFlag) alert("ProjectID" + projectID + " was updated OK."); else alert("ERROR: ProjectID" + projectID + " could not be updated: " + errMessage);}	

	// The Event name to subscribe to so that you know if a Product Convesion was successfull.
	// The event will take 3 parameters. 1) The Project ID that was updated... 2) A boolean value for success/failure... 3) An error message if the second paramater returns FALSE
	// Example of how to subscribe to the event within the calling script...
	// projectObj.attachProductConversionEvent(customFunc, this); function customFunc(projectID, successFlag, errMessage){ if(successFlag) alert("ProjectID" + projectID + " was updated OK."); else alert("ERROR: ProjectID" + projectID + " could not be updated: " + errMessage);}	

}


Project.prototype.getFormSecurityCode = function()
{
	return this.formSecurityCode;
}

Project.prototype.getProjectID = function()
{
	return this.projectID;
}

Project.prototype.getProjectView = function()
{
	return this.projectView;
}



Project.prototype.getProductID = function()
{
	return this.productID;
}

// Returns a Template ID if the user selected a template for this project (not uploaded artwork).  It must correspond with a template area.
Project.prototype.getTemplateID = function()
{
	return this.templateId;
}
Project.prototype.getTemplateArea = function()
{
	return this.templateArea;
}

// Returns an array of TemplateLink objects.  This is based off of the template the user selected for creating their design.  It may contain designs for multiple products which are matching.
Project.prototype.getTemplateProductLinks = function()
{
	return this.templateProductLinks;
}

// Returns a reference to a Product Object.
// As long as this Project has been retrieved from the ProjectLoader... then the Product Object will be avaialable as well.
Project.prototype.getProductObj = function()
{
	return this.productObj;
}


Project.prototype.getQuantity = function()
{
	return this.selectedOptionsObj.getSelectedQuantity();
}



Project.prototype.artworkThumbnailNeedsUpdate = function()
{
	return this.artThumbNeedsUpdateFlag;
}



// Sets the Choice for the Given Option Name.
Project.prototype.setOptionChoice = function(optName, choiceName)
{
	this.selectedOptionsObj.setOptionChoice(optName, choiceName);
}


Project.prototype.setQuantity = function(quantity)
{
	this.selectedOptionsObj.setQuantity(quantity);
}



// Returns an Array of Option Names on this Project.
// Make sure that the Option Names exist on the Product.  Just in case the Product Object cache is old or something.
// Pass in a value of True or False whether you want to hide Option Names that have a selected Choice which is currently hidden.
Project.prototype.getOptionNames = function(hideOptionsHidden)
{
	var optionsNamesArr = this.selectedOptionsObj.getOptionNames();
	
	var retArr = new Array();
	
	for(var i=0; i<optionsNamesArr.length; i++)
	{
		var choiceSelected = this.getChoiceSelected(optionsNamesArr[i]);

		
		// If the Product Object is cached (old) or something... just skip values that don't exist to avoid an error message.
		if(!this.productObj.checkIfOptionAndChoiceExist(optionsNamesArr[i], choiceSelected))
			continue;
		
		// Skip hidden choices... only if the parameter says so.
		if(hideOptionsHidden)
		{
			if(this.productObj.isChoiceIsHidden(optionsNamesArr[i], choiceSelected))
				continue;
		}

		
		retArr.push(optionsNamesArr[i]);
	}
	
	return retArr;
}


// Returns the Choice selected, Null if the Option Name does not exist.
Project.prototype.getChoiceSelected = function(optName)
{

	var selectedChoice =  this.selectedOptionsObj.getChoiceSelected(optName);
	if(selectedChoice == null)
		alert("Error in method Project.getChoiceSelected. The Option does not exist: " + optName);

	return selectedChoice;
}



// Parses the Options Description and returns a Selected Options Obj after parsing the Options Description
Project.prototype.getSelectedOptionsObj = function()
{
	return this.selectedOptionsObj;
}


Project.prototype.checkIfArtworkCopied = function()
{
	return this.artworkIsCopied;
}
Project.prototype.checkIfArtworkTransfered = function()
{
	return this.artworkIsTransfered;
}


// Returns TRUE if hte project is in the shopping cart.
// If this is a saved Project, it can return TRUE if there is an item in the shopping cart which is linked to the Saved Projet.
Project.prototype.checkIfInShoppingCart = function()
{
	return this.isInShoppingCart;
}


// Returns a Single character defining the Status of the Variable Data project (if it is a variable Data Product).
Project.prototype.getVariableDataStatus = function()
{
	return this.variableDataStatus;
}
// Returns a detailed description of the Variable Data Status.
Project.prototype.getVariableDataMessage = function()
{
	return this.variableDataMessage;
}


// Returns a Javascript Date Object representing the last date modified.
Project.prototype.getDateModified = function()
{
	return new Date(this.dateModifiedUnixTimestamp * 1000);
}

// Returns a string that can be used to append to a URL for caching the thumbnail image (since it is generated by PHP
Project.prototype.getThumbnailCache = function()
{
	return this.thumbnailCache;
}
// Returns a string used for the Thumbnail Security code parameter.
Project.prototype.getThumbnailSC = function()
{
	return this.thumbnailSC;
}

// This will go through the Product Object to get the Subtotal... based upon the current prices.
Project.prototype.getSubtotal = function()
{
	return this.productObj.getSubtotalOverride(this.selectedOptionsObj);
}

// Returns the amount of the project subtotal that discounts may not apply to.
Project.prototype.getSubtotalExemptFromDiscounts = function()
{
	return this.productObj.getSubtotalExemptFromDiscounts(this.selectedOptionsObj);
}






// If this is an Ordered project... or a Session (in the shopping cart)... it may be linked to a Saved Project ID.
// Returns 0 if there is no link.
Project.prototype.getSavedProjectLink = function()
{
	if(this.projectView != "ordered" && this.projectView != "session")
	{
		alert("Error in method Project.getSavedProjectLink. The Project View is not correct for calling this method.");
		return;
	}
	
	return this.savedProjectLink;
}





// --------------  methods propietory to Projects Ordered --------------

// Only works for Project Ordered types. 
// Gets Order ID associated with the Project.
Project.prototype.getOrderID = function()
{
	if(this.projectView != "ordered")
	{
		alert("Error in method Project.getOrderID. The Project View is not correct for calling this method.");
		return;
	}
	
	return this.orderID;
}


// Only works for Project Ordered types. 
// Gets the subtotal from the database. The prices of the Product may have changed since the order was placed.
Project.prototype.getSubtotalFromDatabase = function()
{
	if(this.projectView != "ordered")
	{
		alert("Error in method Project.getSubtotalFromDatabase. The Project View is not correct for calling this method.");
		return;
	}
	
	return this.subtotalFromDatabase;
}


// Only works for Project Ordered types. 
// Returns a float representing the Discount on the Project.  1 means 100% discount.  0.5 means 50% discount.
Project.prototype.getProjectDiscount = function()
{
	if(this.projectView != "ordered")
	{
		alert("Error in method Project.getProjectDiscount. The Project View is not correct for calling this method.");
		return;
	}
	
	return parseFloat(this.projectDiscount);
}

// Only works for Project Ordered types. 
// Returns a float representing the Tax on a project
Project.prototype.getProjectTax = function()
{
	if(this.projectView != "ordered")
	{
		alert("Error in method Project.getProjectTax. The Project View is not correct for calling this method.");
		return;
	}
	
	return parseFloat(this.projectTax);
}



// Only works for Project Ordered types.
// Returns a DateObject for the arrival date
Project.prototype.getArrivalDate = function()
{
	if(this.projectView != "ordered")
	{
		alert("Error in method Project.getArrivalDate. The Project View is not correct for calling this method.");
		return;
	}
	
	return new Date(this.arrivalDateUnixTimestamp);
}


// Only works for Project Ordered types. 
// Returns a status char like 'N'ew, 'P'roofed, prin'T'ed, 'B'oxed, etc.
Project.prototype.getStatusChar = function()
{
	if(this.projectView != "ordered")
	{
		alert("Error in method Project.getStatusChar. The Project View is not correct for calling this method.");
		return;
	}
	
	return this.statusChar;
}


// Only works for Project Ordered types. 
// Returns an description of the Status characters.
Project.prototype.getStatusDescription = function()
{
	if(this.projectView != "ordered")
	{
		alert("Error in method Project.getStatusDescription. The Project View is not correct for calling this method.");
		return;
	}
	
	return this.statusDesc;
}

// Only works for Project Ordered types. 
// Returns True or False depending on the status of the Job. Once a job is printed it can no longer be edited.
Project.prototype.checkIfArtworkCanStillBeEdited = function()
{
	if(this.projectView != "ordered")
	{
		alert("Error in method Project.checkIfArtworkCanStillBeEdited. The Project View is not correct for calling this method.");
		return;
	}
	
	return this.artworkCanStillBeEdited;
}





// If you make any changes to the quantity or the options/choices, call this method to save to the database.
// Make sure to define an event handler to listen for the response from the server.
Project.prototype.updateOptionsAndQuantity = function()
{

	// So we can scope the callback.
	var instance = this;
	
	var dateObj = new Date();
	var apiURL = "./api_dot.php?api_version=1.1&form_sc="+ this.getFormSecurityCode() +"&command=update_project_options&project_number=" + this.getProjectID() + "&view_type=" +  this.getProjectView() + "&project_quantity=" + this.getQuantity() + "&project_options=" + escape(this.selectedOptionsObj.getOptionAndChoicesStr()) + "&nocache=" + dateObj.getTime();
	
	
	// Get a free/new connection object by reference. Abort method call if an HTTP object cant be created. The method getXmlHttpObj will alert an error message too.
	var xmlHttpObjFromGlobalQueue = getXmlHttpObj("ProjectOptionsUpdate");
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
		
			if(xmlReqsObjArr[t].reqObjFree || xmlReqsObjArr[t].reqObjParsing || xmlReqsObjArr[t].xmlHttpObj.readyState != 4 || xmlReqsObjArr[t].callBackFunctionName != "ProjectOptionsUpdate")
				continue;

			if(xmlReqsObjArr[t].xmlHttpObj.status == "200"){
				xmlReqsObjArr[t].reqObjParsing = true;
				instance.parseUpdateOptionsXMLresponse(xmlReqsObjArr[t].xmlHttpObj.responseText);
			}
			else{
				instance.updateOptionsErrorEvent_Private(xmlReqsObjArr[t].xmlHttpObj.status, xmlReqsObjArr[t].xmlHttpObj.statusText);
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

// If you make any changes to the quantity or the options/choices, call this method to save to the database.
// Make sure to define an event handler to listen for the response from the server.
Project.prototype.parseUpdateOptionsXMLresponse = function(xmlhttpresponseText)
{

	//alert(xmlhttpresponseText);

	
	// Find out if the document was not what we expected to receive.
	// If Not, try to get an Error message and send that through the error event.
	if(!xmlhttpresponseText.match(/\<result\>OK\<\/result\>/))
	{
		// Look for an error message in the response, since we didn't get an "OK"
		var errorMessage = getErrorMessageFromAPIresponseXML(xmlhttpresponseText);
		
		if(errorMessage == "")
			this.updateOptionsErrorEvent_Private("5401", "Unknown Error Parsing XML Document");
		else
			this.updateOptionsErrorEvent_Private("5402", errorMessage);

		return;
	}
	else{
	
		// The response of updating project options should also let us know if we need to update the thumbnail image.
		// Sometimes changing project options can have an affect on the artwork preview, such as Windows on an Envelope preview.
		if(xmlhttpresponseText.match(/\<update_thumbanil_image\>yes\<\/update_thumbanil_image\>/))
			this.artThumbNeedsUpdateFlag = true;
		else
			this.artThumbNeedsUpdateFlag = false;
	
	
		// See if the user has subscribed to the update options event.  We are returning TRUE ... to indicate success.
		for(var i=0; i<this.updateOptionsEventFunctions.length; i++){
			this.updateOptionsEventFunctions[i].call(this.updateOptionsEventObjects[i], this.getProjectID(), true, "");
		}

	}
	

}


Project.prototype.updateOptionsErrorEvent_Private = function(responseCode, responseError)
{

	// See if the user has subscribed to the updateOptions event.  We are just returning FALSE ... to indicate an error.
	for(var i=0; i<this.updateOptionsEventFunctions.length; i++){
		this.updateOptionsEventFunctions[i].call(this.updateOptionsEventObjects[i], this.getProjectID(), false, responseError);
	}

}



// It could take a while to update the thumbnail image.
// Make sure to define an event handler to listen for the response from the server. You should probably show the user some eye-candy while they are waiting.
Project.prototype.updateThumbnailImage = function()
{

	// So we can scope the callback.
	var instance = this;
	
	var dateObj = new Date();
	var apiURL = "./api_dot.php?api_version=1.1&form_sc="+ this.getFormSecurityCode() +"&command=update_artwork_thumbnail&project_number=" + this.getProjectID() + "&view_type=" +  this.getProjectView() + "&nocache=" + dateObj.getTime();
	
	
	// Get a free/new connection object by reference. Abort method call if an HTTP object cant be created. The method getXmlHttpObj will alert an error message too.
	var xmlHttpObjFromGlobalQueue = getXmlHttpObj("ProjectThumbnailUpdate");
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
		
			if(xmlReqsObjArr[t].reqObjFree || xmlReqsObjArr[t].reqObjParsing || xmlReqsObjArr[t].xmlHttpObj.readyState != 4 || xmlReqsObjArr[t].callBackFunctionName != "ProjectThumbnailUpdate")
				continue;

			if(xmlReqsObjArr[t].xmlHttpObj.status == "200"){
				 xmlReqsObjArr[t].reqObjParsing = true;
				instance.parseUpdateThumbnailImageXMLresponse(xmlReqsObjArr[t].xmlHttpObj.responseText);
			}
			else{
				instance.updateThumbnailImageErrorEvent_Private(xmlReqsObjArr[t].xmlHttpObj.status, xmlReqsObjArr[t].xmlHttpObj.statusText);
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

// If you make any changes to the quantity or the options/choices, call this method to save to the database.
// Make sure to define an event handler to listen for the response from the server.
Project.prototype.parseUpdateThumbnailImageXMLresponse = function(xmlhttpresponseText)
{

	//alert(xmlhttpresponseText);

	
	// Find out if the document was not what we expected to receive.
	// If Not, try to get an Error message and send that through the error event.
	if(!xmlhttpresponseText.match(/\<result\>OK\<\/result\>/))
	{
		// Look for an error message in the response, since we didn't get an "OK"
		var errorMessage = getErrorMessageFromAPIresponseXML(xmlhttpresponseText);
		
		if(errorMessage == "")
			this.updateThumbnailImageErrorEvent_Private("5501", "Unknown Error Parsing XML Document");
		else
			this.updateThumbnailImageErrorEvent_Private("5502", errorMessage);

		return;
	}
	else{
		// Now that the thumbnail was updated OK... let the Project Object know that it no longer needs to be updated.
		this.artThumbNeedsUpdateFlag = false;
	
		// See if the user has subscribed to the thumbnail update event.  We are just returning TRUE ... to indicate success.
		for(var i=0; i<this.updateThumbnailEventFunctions.length; i++){
			this.updateThumbnailEventFunctions[i].call(this.updateThumbnailEventObjects[i], this.getProjectID(), true, "");
		}

	}
	

}


Project.prototype.updateThumbnailImageErrorEvent_Private = function(responseCode, responseError)
{

	// See if the user has subscribed to the thumbnail update event.  Return false to indicate an error.
	for(var i=0; i<this.updateThumbnailEventFunctions.length; i++){
		this.updateThumbnailEventFunctions[i].call(this.updateThumbnailEventObjects[i], this.getProjectID(), false, responseError);
	}

}

// Attach a function and object reference that takes 3 parameters (projectID, errorFlag, errorText)
// If the error flag is false... then the udpate worked and there will be no errorText.
Project.prototype.attachThumbnailUpdateEvent = function(functionRef, objectRef){
	this.updateThumbnailEventFunctions.push(functionRef);
	this.updateThumbnailEventObjects.push(functionRef);
}

// Attach a function and object reference that takes 3 parameters (projectID, errorFlag, errorText)
// If the error flag is false... then the udpate worked and there will be no errorText.
Project.prototype.attachUpdateProjectOptionsEvent = function(functionRef, objectRef){
	this.updateOptionsEventFunctions.push(functionRef);
	this.updateOptionsEventObjects.push(functionRef);
}

// Attach a function and object reference that takes 3 parameters (projectID, errorFlag, errorText)
// If the error flag is false... then the udpate worked and there will be no errorText.
Project.prototype.attachProductConversionEvent = function(functionRef, objectRef){
	this.productConvertEventFunctions.push(functionRef);
	this.productConvertEventObjects.push(functionRef);
}







Project.prototype.addToShoppingCart = function()
{

	if(this.getProjectView() != "saved" && this.getProjectView() != "session" ){
		alert("Error in method addToShoppingCart. The ViewType can only be 'saved' or 'session'.");
		return;
	}

	this.newProjectSessionID = 0;

	// So we can scope the callback.
	var instance = this;
	
	var dateObj = new Date();
	var apiURL = "./api_dot.php?api_version=1.1&form_sc="+ this.getFormSecurityCode() +"&command=add_to_shoppingcart&project_number=" + this.getProjectID() + "&view_type=" +  this.getProjectView() + "&nocache=" + dateObj.getTime();
	
	
	// Get a free/new connection object by reference. Abort method call if an HTTP object cant be created. The method getXmlHttpObj will alert an error message too.
	var xmlHttpObjFromGlobalQueue = getXmlHttpObj("AddToCartReq");
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
		
			if(xmlReqsObjArr[t].reqObjFree || xmlReqsObjArr[t].reqObjParsing || xmlReqsObjArr[t].xmlHttpObj.readyState != 4 || xmlReqsObjArr[t].callBackFunctionName != "AddToCartReq")
				continue;

			if(xmlReqsObjArr[t].xmlHttpObj.status == "200"){
				xmlReqsObjArr[t].reqObjParsing = true;
				instance.parseAddToShoppingCartXMLresponse(xmlReqsObjArr[t].xmlHttpObj.responseText);
			}
			else{
				instance.addToShoppingCartErrorEvent_Private(xmlReqsObjArr[t].xmlHttpObj.status, xmlReqsObjArr[t].xmlHttpObj.statusText);
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

// If you make any changes to the quantity or the options/choices, call this method to save to the database.
// Make sure to define an event handler to listen for the response from the server.
Project.prototype.parseAddToShoppingCartXMLresponse = function(xmlhttpresponseText)
{
	//alert(xmlhttpresponseText);

	// Find out if the document was not what we expected to receive.
	// If Not, try to get an Error message and send that through the error event.
	if(!xmlhttpresponseText.match(/\<result\>OK\<\/result\>/))
	{
		// Look for an error message in the response, since we didn't get an "OK"
		var errorMessage = getErrorMessageFromAPIresponseXML(xmlhttpresponseText);
		
		if(errorMessage == "")
			this.addToShoppingCartErrorEvent_Private("5501", "Unknown Error Parsing XML Document");
		else
			this.addToShoppingCartErrorEvent_Private("5502", errorMessage);

		return;
	}
	else{
	
		var xmlLinesArr = xmlhttpresponseText.split("\n");
		
		for(var lineNo = 0; lineNo < xmlLinesArr.length; lineNo++)
		{

			if(xmlLinesArr[lineNo].toString().match(/\<project_session_id\>/))
			{
				this.newProjectSessionID = getXMLdata(xmlLinesArr[lineNo]);
				break;
			}
		}

		// See if the user has subscribed to the add to shopping cart event.  We are returning TRUE ... to indicate success.
		for(var i=0; i<this.shoppingCartAddEventFunctions.length; i++){
			this.shoppingCartAddEventFunctions[i].call(this.shoppingCartAddEventObjects[i], this.getProjectID(), true, "");
		}
	}

}


Project.prototype.addToShoppingCartErrorEvent_Private = function(responseCode, responseError)
{
	// See if the user has subscribed to the addToShoppingCart event.  We are returning TRUE ... to indicate success.
	for(var i=0; i<this.shoppingCartAddEventFunctions.length; i++){
		this.shoppingCartAddEventFunctions[i].call(this.shoppingCartAddEventObjects[i], this.getProjectID(), false, responseError);
	}

}

Project.prototype.attachAddToShoppingCartEvent = function(functionRef, objectRef){
	
	this.shoppingCartAddEventFunctions.push(functionRef);
	this.shoppingCartAddEventObjects.push(objectRef);

}






Project.prototype.removeFromShoppingCart = function()
{

	if(this.getProjectView() != "session" ){
		alert("Error in method removeFromShoppingCart. The ViewType can only be 'session'.");
		return;
	}


	// So we can scope the callback.
	var instance = this;
	
	var dateObj = new Date();
	var apiURL = "./api_dot.php?api_version=1.1&form_sc="+ this.getFormSecurityCode() +"&command=remove_from_shoppingcart&project_number=" + this.getProjectID() + "&view_type=" +  this.getProjectView() + "&nocache=" + dateObj.getTime();

	
	// Get a free/new connection object by reference. Abort method call if an HTTP object cant be created. The method getXmlHttpObj will alert an error message too.
	var xmlHttpObjFromGlobalQueue = getXmlHttpObj("RemoveFromCartReq");
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
		
			if(xmlReqsObjArr[t].reqObjFree || xmlReqsObjArr[t].reqObjParsing || xmlReqsObjArr[t].xmlHttpObj.readyState != 4 || xmlReqsObjArr[t].callBackFunctionName != "RemoveFromCartReq")
				continue;

			if(xmlReqsObjArr[t].xmlHttpObj.status == "200"){
				xmlReqsObjArr[t].reqObjParsing = true;
				instance.parseRemoveFromShoppingCartXMLresponse(xmlReqsObjArr[t].xmlHttpObj.responseText);
			}
			else{
				instance.removeFromShoppingCartErrorEvent_Private(xmlReqsObjArr[t].xmlHttpObj.status, xmlReqsObjArr[t].xmlHttpObj.statusText);
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


Project.prototype.parseRemoveFromShoppingCartXMLresponse = function(xmlhttpresponseText)
{

	// Find out if the document was not what we expected to receive.
	// If Not, try to get an Error message and send that through the error event.
	if(!xmlhttpresponseText.match(/\<result\>OK\<\/result\>/))
	{
		// Look for an error message in the response, since we didn't get an "OK"
		var errorMessage = getErrorMessageFromAPIresponseXML(xmlhttpresponseText);
		
		if(errorMessage == "")
			this.removeFromShoppingCartErrorEvent_Private("5501", "Unknown Error Parsing XML Document");
		else
			this.removeFromShoppingCartErrorEvent_Private("5502", errorMessage);

		return;
	}
	else{
	

		// See if the user has subscribed to the shopping cart event.  We are returning TRUE ... to indicate success.
		for(var i=0; i<this.shoppingCartRemoveEventFunctions.length; i++){
			this.shoppingCartRemoveEventFunctions[i].call(this.shoppingCartRemoveEventObjects[i], this.getProjectID(), true, "");
		}
	}

}


Project.prototype.removeFromShoppingCartErrorEvent_Private = function(responseCode, responseError)
{
	// See if the user has subscribed to the removeFromShoppingCart event.  We are returning TRUE ... to indicate success.
	for(var i=0; i<this.shoppingCartRemoveEventFunctions.length; i++){
		this.shoppingCartRemoveEventFunctions[i].call(this.shoppingCartRemoveEventObjects[i], this.getProjectID(), false, responseError);
	}

}

Project.prototype.attachRemoveFromShoppingCartEvent = function(functionRef, objectRef){
	
	this.shoppingCartRemoveEventFunctions.push(functionRef);
	this.shoppingCartRemoveEventObjects.push(objectRef);

}







// Pass in a Product ID that you want to convert Project into.
Project.prototype.convertToAnotherProductID = function(converToProductID)
{

	// So we can scope the callback.
	var instance = this;
	
	var dateObj = new Date();
	var apiURL = "./api_dot.php?api_version=1.1&form_sc="+ this.getFormSecurityCode() +"&command=convert_to_another_product&project_number=" + this.getProjectID() + "&view_type=" +  this.getProjectView() + "&conver_to_product_id=" + converToProductID + "&nocache=" + dateObj.getTime();
	
	
	// Get a free/new connection object by reference. Abort method call if an HTTP object cant be created. The method getXmlHttpObj will alert an error message too.
	var xmlHttpObjFromGlobalQueue = getXmlHttpObj("ProjectConvertProduct");
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
		
			if(xmlReqsObjArr[t].reqObjFree || xmlReqsObjArr[t].reqObjParsing || xmlReqsObjArr[t].xmlHttpObj.readyState != 4 || xmlReqsObjArr[t].callBackFunctionName != "ProjectConvertProduct")
				continue;

			if(xmlReqsObjArr[t].xmlHttpObj.status == "200"){
				xmlReqsObjArr[t].reqObjParsing = true;
				instance.parseConverToAnotherProductXMLresponse(xmlReqsObjArr[t].xmlHttpObj.responseText);
			}
			else{
				instance.parseConvertToAnotherProductErrorEvent_Private(xmlReqsObjArr[t].xmlHttpObj.status, xmlReqsObjArr[t].xmlHttpObj.statusText);
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


Project.prototype.parseConverToAnotherProductXMLresponse = function(xmlhttpresponseText)
{

	//alert(xmlhttpresponseText);

	
	// Find out if the document was not what we expected to receive.
	// If Not, try to get an Error message and send that through the error event.
	if(!xmlhttpresponseText.match(/\<result\>OK\<\/result\>/))
	{
		// Look for an error message in the response, since we didn't get an "OK"
		var errorMessage = getErrorMessageFromAPIresponseXML(xmlhttpresponseText);
		
		if(errorMessage == "")
			this.parseConvertToAnotherProductErrorEvent_Private("5601", "Unknown Error Parsing XML Document");
		else
			this.parseConvertToAnotherProductErrorEvent_Private("5602", errorMessage);

		return;
	}
	else{
	
		// The response of updating project options should also let us know if we need to update the thumbnail image.
		// Sometimes changing project options can have an affect on the artwork preview, such as Windows on an Envelope preview.
		if(xmlhttpresponseText.match(/\<update_thumbanil_image\>yes\<\/update_thumbanil_image\>/))
			this.artThumbNeedsUpdateFlag = true;
		else
			this.artThumbNeedsUpdateFlag = false;

		// See if the user has subscribed to the production conversion event.  We are returning TRUE ... to indicate success.
		for(var i=0; i<this.productConvertEventFunctions.length; i++){
			this.productConvertEventFunctions[i].call(this.productConvertEventObjects[i], this.getProjectID(), true, "");
		}

	}
	

}


Project.prototype.parseConvertToAnotherProductErrorEvent_Private = function(responseCode, responseError)
{
	// See if the user has subscribed to the production conversion event.  We are just returning FALSE ... to indicate an error.
	for(var i=0; i<this.productConvertEventFunctions.length; i++){
		this.productConvertEventFunctions[i].call(this.productConvertEventObjects[i], this.getProjectID(), false, responseError);
	}
}


// Convert this Project into a different product.  If you do not specify a templateID/Area... then the system will attempt to do an automatic artwork transfer
// This is a less desirable option because the images may not have sufficient DPI, or a similar aspect ratio.
// If a templateID/Area is provided, only the contents of matching text fields will be transfered.
Project.prototype.transferToProduct = function(newProductId, destinationView, newTemplateId, newTemplateArea)
{

	if(destinationView != "session" && destinationView != "saved")
	{
		alert("Illegal destination view for product transfer.");
		return;
	}

	// So we can scope the callback.
	var instance = this;
	
	var dateObj = new Date();
	var apiURL = "./api_dot.php?api_version=1.1&form_sc="+ this.getFormSecurityCode() +"&command=product_transfer&project_number=" + this.getProjectID() + "&view_type=" +  this.getProjectView() + "&transfer_to_product_id=" + newProductId + "&destination_project_view_type=" + destinationView + "&template_id=" + newTemplateId + "&template_area=" + newTemplateArea + "&nocache=" + dateObj.getTime();
	
	// Get a free/new connection object by reference. Abort method call if an HTTP object cant be created. The method getXmlHttpObj will alert an error message too.
	var xmlHttpObjFromGlobalQueue = getXmlHttpObj("ProductTransferReq");
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
		
			if(xmlReqsObjArr[t].reqObjFree || xmlReqsObjArr[t].reqObjParsing || xmlReqsObjArr[t].xmlHttpObj.readyState != 4 || xmlReqsObjArr[t].callBackFunctionName != "ProductTransferReq")
				continue;

			if(xmlReqsObjArr[t].xmlHttpObj.status == "200"){
				xmlReqsObjArr[t].reqObjParsing = true;
				instance.parseProductTransferXMLresponse(xmlReqsObjArr[t].xmlHttpObj.responseText);
			}
			else{
				instance.productTransferErrorEvent_Private(xmlReqsObjArr[t].xmlHttpObj.status, xmlReqsObjArr[t].xmlHttpObj.statusText);
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

// If you make any changes to the quantity or the options/choices, call this method to save to the database.
// Make sure to define an event handler to listen for the response from the server.
Project.prototype.parseProductTransferXMLresponse = function(xmlhttpresponseText)
{
	//alert(xmlhttpresponseText);
	
	var newProjectId = 0;
	var newProjectView = "";

	// Find out if the document was not what we expected to receive.
	// If Not, try to get an Error message and send that through the error event.
	if(!xmlhttpresponseText.match(/\<result\>OK\<\/result\>/))
	{
		// Look for an error message in the response, since we didn't get an "OK"
		var errorMessage = getErrorMessageFromAPIresponseXML(xmlhttpresponseText);
		
		if(errorMessage == "")
			this.productTransferErrorEvent_Private("5801", "Unknown Error Parsing XML Document");
		else
			this.productTransferErrorEvent_Private("5802", errorMessage);

		return;
	}
	else{
	
		var xmlLinesArr = xmlhttpresponseText.split("\n");
		
		for(var lineNo = 0; lineNo < xmlLinesArr.length; lineNo++)
		{
			if(xmlLinesArr[lineNo].toString().match(/\<new_project_id\>/))
			{
				newProjectId = getXMLdata(xmlLinesArr[lineNo]);
			}
			if(xmlLinesArr[lineNo].toString().match(/\<new_project_view\>/))
			{		
				newProjectView = getXMLdata(xmlLinesArr[lineNo]);
			}
		}

		if(newProjectId == 0 || newProjectView == ""){
			this.productTransferErrorEvent_Private("5803", "Unknown error transfering to a new product.");
			return;
		}

		// See if the user has subscribed to the add to shopping cart event.  We are returning TRUE ... to indicate success.
		for(var i=0; i<this.productTransferEventFunctions.length; i++){
			this.productTransferEventFunctions[i].call(this.productTransferEventObjects[i], this.getProjectID(), true, "", newProjectId, newProjectView);
		}
	}

}


Project.prototype.productTransferErrorEvent_Private = function(responseCode, responseError)
{
	// See if the user has subscribed to the addToShoppingCart event.  We are returning TRUE ... to indicate success.
	for(var i=0; i<this.productTransferEventFunctions.length; i++){
		this.productTransferEventFunctions[i].call(this.productTransferEventObjects[i], this.getProjectID(), false, responseError, "", "");
	}

}

// Attach a function and object reference that takes 5 parameters (projectID, errorFlag, errorText, newProjectId, newProjectArea)
// If the error flag is false... then the udpate worked and there will be no errorText.
Project.prototype.attachProductTransferCompleteEvent = function(functionRef, objectRef){
	
	this.productTransferEventFunctions.push(functionRef);
	this.productTransferEventObjects.push(objectRef);

}



















// --- A class which contains 1 product ID and 1 ore more templates.
function TemplateLink(){
	

	this.productID = 0;

	// Parallel arrays.  Every template must have a unique ID, and a template Area type.  
	// Every Product Link can have 1 or more templates associated with them.  This is useful to provide variations of the same design.
	this.templateIdsArr = new Array();
	this.templateAreasArr = new Array(); 
	this.templatePreviewIdsArr = new Array(); 
	this.templatePreviewSideNamesArr = new Array(); 
	this.templatePreviewWidthsArr = new Array(); 
	this.templatePreviewHeightsArr = new Array(); 
	
}

TemplateLink.prototype.getTemplateIds = function()
{
	return this.templateIdsArr;
}
TemplateLink.prototype.getTemplateAreaFromTemplateId = function(templateId){
	
	for(var i=0; i<this.templateIdsArr.length; i++){
		if(templateId == this.templateIdsArr[i]){
			return this.templateAreasArr[i];
		}
	}
	
	alert("Illegal Template id in getTemplateAreaFromTemplateId");
}

TemplateLink.prototype.getPreviewSideNamesFromTemplateId = function(templateId){
	
	for(var i=0; i<this.templateIdsArr.length; i++){
		if(templateId == this.templateIdsArr[i]){
			return this.templatePreviewSideNamesArr[i].split("\|");
		}
	}
	
	alert("Illegal Template id in getPreviewSideNamesFromTemplateId");
	return new Array();
}
TemplateLink.prototype.getPreviewIdsFromTemplateId = function(templateId){
	
	for(var i=0; i<this.templateIdsArr.length; i++){
		if(templateId == this.templateIdsArr[i]){
			return this.templatePreviewIdsArr[i].split("\|");
		}
	}
	
	alert("Illegal Template id in getPreviewIdsFromTemplateId");
	return new Array();
}
TemplateLink.prototype.getPreviewWidthsFromTemplateId = function(templateId){
	
	for(var i=0; i<this.templateIdsArr.length; i++){
		if(templateId == this.templateIdsArr[i]){
			return this.templatePreviewWidthsArr[i].split("\|");
		}
	}
	
	alert("Illegal Template id in getPreviewWidthsFromTemplateId");
	return new Array();
}
TemplateLink.prototype.getPreviewHeightsFromTemplateId = function(templateId){
	
	for(var i=0; i<this.templateIdsArr.length; i++){
		if(templateId == this.templateIdsArr[i]){
			return this.templatePreviewHeightsArr[i].split("\|");
		}
	}
	
	alert("Illegal Template id in getPreviewHeightsFromTemplateId");
	return new Array();
}




























// ProductLoader Class
// Use this to fetch new Product Objects.  It may download information from the server, or it may already have the Product in Cache.
function ProductLoader()
{

	this.productIDsQueued = new Array();
	this.cachedProductObjectsArr = new Array();
	this.cachedProductObjectsPointerArr = new Array();
	
	// The following 2 arrays are used if you call the method attachProductLoadedEvent
	this.productLoadingEventsFunctionRefArr = new Array();
	this.productLoadingEventsObjectRefArr = new Array();
	
	// A parallel array to let us know what Function References belong to what Product IDs
	this.productLoadingEventsProductsIDsArr = new Array(); 
	
	// A list of events that will be fired regardless of Product. The Product ID will be sent as a parameter.
	this.productLoadingEventsAllProductsFunctionsArr = new Array(); 
	this.productLoadingEventsAllProductsObjectsArr = new Array(); 
	
	// Subscribe Events to errors.
	this.productLoadingErrorEventsFunctionRefArr = new Array();
	this.productLoadingErrorEventsObjectRefArr = new Array();
	
	
	// To prevent multi threading issues if multiple Product definitions are downloading simultaneously.
	this.parsingInProgressFlag = false;
	
	// Will hold XML responses in a Queue, if there is another XML file being parsed.
	this.parsingXMLresponseQueue = new Array();
	
	this.includeVendorsFlag = false;
	
}

ProductLoader.prototype.includeVendors = function(boolFlag)
{
	if(boolFlag)
		this.includeVendorsFlag = true;
	else
		this.inincludeVendorsFlag = false;
}

// Pass in a product ID and a "function reference" into this function. 
// No need to call the method loadProductID() if you use this method, it will load it for you. 
// After the product has finished loading, it will call the function reference (with no parameters). 
ProductLoader.prototype.attachProductLoadedEvent = function(productID, functionReference, objectReference)
{

	// Parallel Arrays
	this.productLoadingEventsProductsIDsArr.push(productID);
	this.productLoadingEventsFunctionRefArr.push(functionReference);
	this.productLoadingEventsObjectRefArr.push(objectReference);
	
	
	// We may call the same Product IDs with differnt event attachment.
	// If the communication happens really quickly, then the the subsequent calls may not get fired after the XML gets parsed.
	// If the Product has already been downloaded and initialized... just fire the event attachment immediately.
	if(this.checkIfProductInitialized(productID))
	{
		this.privateMethod_ProductLoadingComplete(productID);
	}
	else{
		this.loadProductID(productID);
	}
	
}

ProductLoader.prototype.attachProductLoadedGlobalEvent = function(functionReference, objectReference)
{
	//alert(functionReference);
	this.productLoadingEventsAllProductsFunctionsArr.push(functionReference);
	this.productLoadingEventsAllProductsObjectsArr.push(objectReference);
}

ProductLoader.prototype.attatchProductLoadingErrorEvent = function(functionReference, objectReference)
{
	this.productLoadingErrorEventsFunctionRefArr.push(functionReference);
	this.productLoadingErrorEventsObjectRefArr.push(objectReference);	
}

// Subscript to this error event to get notified when a Product has been loaded from the Server and initialized.
ProductLoader.prototype.privateMethod_ProductLoadingComplete = function(productID)
{
	
	// Find out which indexes of function References are subscribed to this Product ID.
	// Call the function reference when we find a match.
	for(var i=0; i<this.productLoadingEventsProductsIDsArr.length; i++)
	{
		if(this.productLoadingEventsProductsIDsArr[i] == productID)
		{
			// Make sure that the same event is not called Twice.
			// For example... Let's say that we subscribe one event for "Business Cards" to displayPrice()... and another event for "Business Cards" to showOptionList()
			// If we try loading the same product ID in multiple places on the page (due to ASYNX)... we don't want it to call displayPrice() twice... and showOptionList() once.
			this.productLoadingEventsProductsIDsArr[i] = 0;
			this.productLoadingEventsFunctionRefArr[i].call(this.productLoadingEventsObjectRefArr[i]);
		}
	}
	
	for(var i=0; i<this.productLoadingEventsAllProductsFunctionsArr.length; i++)
	{
		this.productLoadingEventsAllProductsFunctionsArr[i].call(this.productLoadingEventsAllProductsObjectsArr[i], productID);
	}


}

ProductLoader.prototype.onProductLoadError_Private = function(statusCode, errorMessage)
{
	for(var i=0; i < this.productLoadingErrorEventsFunctionRefArr.length; i++){
		this.productLoadingErrorEventsFunctionRefArr[i].call(this.productLoadingErrorEventsObjectRefArr, statusCode, errorMessage);
	}
}
	


// Call this anytime you want to create a new Product Object
// You MUST check to see if the product has finished loading before atempting to fetch it.
ProductLoader.prototype.loadProductID = function(productID)
{

	// Don't try to repeatadly load a Product ID
	for(var i=0; i<this.productIDsQueued.length; i++)
	{
		if(this.productIDsQueued[i] == productID)
			return;
	}
	this.productIDsQueued.push(productID);	
	

	// This doesn't work if you go over 60 mintues.
	var numberOfMinutesToCacheResults = 15;
	
	var dateObj = new Date();

	var dateCache = dateObj.getYear() + "-" + dateObj.getMonth() + "-" +  dateObj.getDate() + "-" +  dateObj.getHours() + "-" + (Math.ceil(parseInt(dateObj.getMinutes()) / numberOfMinutesToCacheResults)) ;

	var apiURL = "./api_dot.php?api_version=1.1&command=get_product_definition&product_id=" + productID + "&nocache=" + dateCache;

	if(this.includeVendorsFlag)
		apiURL += "&includeVendors=true";
	
	// Get a free/new connection object by reference. Abort method call if an HTTP object cant be created. The method getXmlHttpObj will alert an error message too.
	var xmlHttpObjFromGlobalQueue = getXmlHttpObj("ProductLoader");
	if(xmlHttpObjFromGlobalQueue == null)
		return;
	
	
	// So we can scope the callback.
	var instance = this;
	
	xmlHttpObjFromGlobalQueue.open('GET', apiURL, true);

	// Micosoft does not like you setting the Ready State before calling "open".
	xmlHttpObjFromGlobalQueue.onreadystatechange = function() 
	{
	
		// Now the Callback happened from the XMLHttpRequest communication
		// We have to find out which one was used from our Global Array of connection pools.
		// There is no way to guarantee the order in which these are processed.
		// We are basically looking for the first connection object which is Not Free... Having a matching CallBack function "identifier string".
		for(var t=0; t<xmlReqsObjArr.length; t++){
		
			if(xmlReqsObjArr[t].reqObjFree || xmlReqsObjArr[t].reqObjParsing || xmlReqsObjArr[t].xmlHttpObj.readyState != 4 || xmlReqsObjArr[t].callBackFunctionName != "ProductLoader")
				continue;
					
			if(xmlReqsObjArr[t].xmlHttpObj.status == "200")
			{
				xmlReqsObjArr[t].reqObjParsing = true;
				instance.parseXMLresponse(xmlReqsObjArr[t].xmlHttpObj.responseText);
			}
			else
			{
				// This will call error events (if they are attached).
				instance.onProductLoadError_Private(xmlReqsObjArr[t].xmlHttpObj.status, xmlReqsObjArr[t].xmlHttpObj.statusText);
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






// After doing a lot of research I was going to use the SAX XML parsing library from http://xmljs.sourceforge.net/
// But I think it is going to overcomplicate things with so many extra Call-back functions... and extra JS to download.
// .... Plus they are very granular in terms of how they process the document leading to unecessary overhead.
// .... Plus there could be some incompatibility or multi-threading issues.
// Right now these API calls are not too complicated...so just extract the data using Regular expressions.
// It is important howerver, that we have line breaks between each tag in the XML... which must be ensured on the Server-Side
// ... in absence of Line Break assurances by the server... we could instead split line breaks on "ending tag" names like a regex for ....  new Regex(/\<\/\w+\>/);
ProductLoader.prototype.parseXMLresponse = function(xmlhttpresponseText)
{

	if(this.parsingInProgressFlag){
		this.parsingXMLresponseQueue.push(xmlhttpresponseText);
	}
	else{
		this.parsingInProgressFlag = true;
		this.private_parseXMLresponse(xmlhttpresponseText);
	}

	
}

ProductLoader.prototype.private_parseXMLresponse = function(xmlhttpresponseText)
{
	
	// Find out if the document was not what we expected to receive.
	// If Not, try to get an Error message and send that through the error event.
	if(!xmlhttpresponseText.match(/\<result\>OK\<\/result\>/))
	{
		// Look for an error message in the response, since we didn't get an "OK"
		var errorMessage = getErrorMessageFromAPIresponseXML(xmlhttpresponseText);
		
		if(errorMessage == ""){
			this.onProductLoadError_Private("5101", "Unknown Error Parsing XML Document");
		}
		else{
			this.onProductLoadError_Private("5102", errorMessage);
		}

		return;
	}
	
	
	// Build a Product Object and fill it from data returned by the server.
	var newProduct = new Product();

	// Keep Counters so that when we run across new Option/Choice tags we can keep building the array.
	var optionIndex = -1;
	var choiceIndex = -1;
	var productSwitchIndex = -1;

	var xmlLinesArr = xmlhttpresponseText.split("\n");

	for(var lineNo = 0; lineNo < xmlLinesArr.length; lineNo++)
	{
		if(xmlLinesArr[lineNo].toString().match(/\<product_id\>/))
			newProduct.productID = getXMLdata(xmlLinesArr[lineNo]);
		
		else if(xmlLinesArr[lineNo].toString().match(/\<product_title\>/))
			newProduct.productTitle = getXMLdata(xmlLinesArr[lineNo]);
		
		else if(xmlLinesArr[lineNo].toString().match(/\<product_title_ext\>/))
			newProduct.productTitleExt = getXMLdata(xmlLinesArr[lineNo]);
		
		else if(xmlLinesArr[lineNo].toString().match(/\<product_name\>/))
			newProduct.productName = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<artwork_sides_possible_count\>/))
			newProduct.numberOfArtworkSides = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<artwork_width_inches\>/))
			newProduct.artworkWidthInches = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<artwork_height_inches\>/))
			newProduct.artworkHeightInches = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<artwork_bleed_picas\>/))
			newProduct.artworkBleedPicas = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<artwork_is_editable\>/))
			newProduct.artworkIsEditable = (getXMLdata(xmlLinesArr[lineNo]) == "yes" ? true : false);

		else if(xmlLinesArr[lineNo].toString().match(/\<unit_weight\>/))
			newProduct.unitWeight = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<base_price\>/))
			newProduct.basePrice = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<initial_subtotal\>/))
			newProduct.initialSubtotal = getXMLdata(xmlLinesArr[lineNo]);

		else if(xmlLinesArr[lineNo].toString().match(/\<main_quantity_price_breaks_base\>/))
			newProduct.quantityBreaksBase = getXMLdata(xmlLinesArr[lineNo]);
		
		else if(xmlLinesArr[lineNo].toString().match(/\<main_quantity_price_breaks_subtotal\>/))
			newProduct.quantityBreaksSubtotal = getXMLdata(xmlLinesArr[lineNo]);	

		else if(xmlLinesArr[lineNo].toString().match(/\<thumbnail_width_approximate\>/))
			newProduct.thumbnailWidthApprox = getXMLdata(xmlLinesArr[lineNo]);
		
		else if(xmlLinesArr[lineNo].toString().match(/\<thumbnail_height_approximate\>/))
			newProduct.thumbnailHeightApprox = getXMLdata(xmlLinesArr[lineNo]);

		else if(xmlLinesArr[lineNo].toString().match(/\<variable_data\>/))
			newProduct.variableDataFlag = (getXMLdata(xmlLinesArr[lineNo]) == "yes" ? true : false);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<mailing_services\>/))
			newProduct.mailingServicesFlag = (getXMLdata(xmlLinesArr[lineNo]) == "yes" ? true : false);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<thumbnail_background_exists\>/))
			newProduct.thumbnailBackgroundFlag = (getXMLdata(xmlLinesArr[lineNo]) == "yes" ? true : false);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<thumbnail_copy_icon_exists\>/))
			newProduct.thumbnailCopyIconFlag = (getXMLdata(xmlLinesArr[lineNo]) == "yes" ? true : false);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<product_importance\>/))
			newProduct.productImportance = getXMLdata(xmlLinesArr[lineNo]);
		
		// Build an Array of Product ID's by splitting on pipe symbols.
		else if(xmlLinesArr[lineNo].toString().match(/\<compatible_product_ids\>/))
			newProduct.compatibleProductIDsArr = getXMLdata(xmlLinesArr[lineNo]).split("\|");
	
		// Multiple Vendor Names are separated by @ symbols.
		else if(xmlLinesArr[lineNo].toString().match(/\<vendor_names\>/))
			newProduct.vendorNamesArr = getXMLdata(xmlLinesArr[lineNo]).split("@");
	
		else if(xmlLinesArr[lineNo].toString().match(/\<vendor_base_prices\>/))
			newProduct.vendorBasePrices = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<vendor_initial_subtotals\>/))
			newProduct.vendorInitialSubtotals = getXMLdata(xmlLinesArr[lineNo]);
	
		else if(xmlLinesArr[lineNo].toString().match(/\<main_vendor_quantity_price_breaks_base\>/))
			newProduct.vendorQuantityBreaksBase = getXMLdata(xmlLinesArr[lineNo]);	
	
		else if(xmlLinesArr[lineNo].toString().match(/\<main_vendor_quantity_price_breaks_subtotal\>/))
			newProduct.vendorQuantityBreaksSubtotal = getXMLdata(xmlLinesArr[lineNo]);	

		
		
		// When we come across a new opening "product_switch" tag... increment our counter... because we can expect other tags to populate the object.
		else if(xmlLinesArr[lineNo].toString().match(/\<product_switch\>/))
		{
			productSwitchIndex++;
			newProduct.productSwitcher.projectSwitches[productSwitchIndex] = new ProductSwitchContainer();
		}		
		else if(xmlLinesArr[lineNo].toString().match(/\<product_id_target\>/))
			newProduct.productSwitcher.projectSwitches[productSwitchIndex].productIDtarget = parseInt(getXMLdata(xmlLinesArr[lineNo]));
			
		else if(xmlLinesArr[lineNo].toString().match(/\<product_title_target\>/))
			newProduct.productSwitcher.projectSwitches[productSwitchIndex].productName = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<product_switch_title\>/))
			newProduct.productSwitcher.projectSwitches[productSwitchIndex].switchTitle = getXMLdata(xmlLinesArr[lineNo]);

		else if(xmlLinesArr[lineNo].toString().match(/\<product_switch_link_subject\>/))
			newProduct.productSwitcher.projectSwitches[productSwitchIndex].linkSubject = getXMLdata(xmlLinesArr[lineNo]);

		else if(xmlLinesArr[lineNo].toString().match(/\<product_switch_description\>/))
			newProduct.productSwitcher.projectSwitches[productSwitchIndex].description = getXMLdata(xmlLinesArr[lineNo]);
		
		else if(xmlLinesArr[lineNo].toString().match(/\<grouped_to_product_id\>/))
			newProduct.productSwitcher.projectSwitches[productSwitchIndex].groupedToProductID = parseInt(getXMLdata(xmlLinesArr[lineNo]));
		
		else if(xmlLinesArr[lineNo].toString().match(/\<product_switch_description_is_html\>/))
			newProduct.productSwitcher.projectSwitches[productSwitchIndex].descriptionIsHTML = (getXMLdata(xmlLinesArr[lineNo]) == "yes" ? true : false);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<group_head\>/))
			newProduct.productSwitcher.projectSwitches[productSwitchIndex].isGroupHead = (getXMLdata(xmlLinesArr[lineNo]) == "yes" ? true : false);
		
		
		
		
		// When we come across a new opening "option" tag... increment our varialbe... we can expect more data to come.
		else if(xmlLinesArr[lineNo].toString().match(/\<option\>/))
		{
			optionIndex++;
			choiceIndex = -1; // We will have new choices with this Option... so reset the Choice counter.
			newProduct.options[optionIndex] = new ProductOption();
		}
			
		else if(xmlLinesArr[lineNo].toString().match(/\<option_name\>/))		
			newProduct.options[optionIndex].optionName = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<option_alias\>/))
			newProduct.options[optionIndex].optionAlias = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<option_description\>/))
			newProduct.options[optionIndex].optionDescription = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<option_description_is_html\>/))
			newProduct.options[optionIndex].optionDescriptionIsHTML = (getXMLdata(xmlLinesArr[lineNo]) == "yes" ? true : false);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<option_variable_image_controller\>/))
			newProduct.options[optionIndex].optionVariableImageController = (getXMLdata(xmlLinesArr[lineNo]) == "yes" ? true : false);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<option_affects_artwork_sides\>/))
			newProduct.options[optionIndex].affectsArtworkSidesFlag = (getXMLdata(xmlLinesArr[lineNo]) == "yes" ? true : false);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<option_is_admin\>/))
			newProduct.options[optionIndex].adminOptionFlag = (getXMLdata(xmlLinesArr[lineNo]) == "yes" ? true : false);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<option_discount_exempt\>/))
			newProduct.options[optionIndex].discountExempt = (getXMLdata(xmlLinesArr[lineNo]) == "yes" ? true : false);
			
			
		// When we come across a new opening "option" tag... increment our varialbe... we can expect more data to come.
		else if(xmlLinesArr[lineNo].toString().match(/\<choice\>/))
		{
			choiceIndex++;
			newProduct.options[optionIndex].choices[choiceIndex] = new OptionChoice();
		}
		
		else if(xmlLinesArr[lineNo].toString().match(/\<choice_name\>/))
			newProduct.options[optionIndex].choices[choiceIndex].choiceName = getXMLdata(xmlLinesArr[lineNo]);
		
		else if(xmlLinesArr[lineNo].toString().match(/\<choice_alias\>/))
			newProduct.options[optionIndex].choices[choiceIndex].choiceAlias = getXMLdata(xmlLinesArr[lineNo]);
		
		else if(xmlLinesArr[lineNo].toString().match(/\<choice_description\>/))
			newProduct.options[optionIndex].choices[choiceIndex].choiceDescription = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<choice_description_is_html\>/))
			newProduct.options[optionIndex].choices[choiceIndex].choiceDescriptionIsHTML = (getXMLdata(xmlLinesArr[lineNo]) == "yes" ? true : false);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<choice_is_hidden\>/))
			newProduct.options[optionIndex].choices[choiceIndex].choiceIsHiddenFlag = (getXMLdata(xmlLinesArr[lineNo]) == "yes" ? true : false);
	
		else if(xmlLinesArr[lineNo].toString().match(/\<choice_variable_images\>/))
			newProduct.options[optionIndex].choices[choiceIndex].choiceVariableImages = (getXMLdata(xmlLinesArr[lineNo]) == "yes" ? true : false);
	
		else if(xmlLinesArr[lineNo].toString().match(/\<change_artwork_sides\>/))
			newProduct.options[optionIndex].choices[choiceIndex].changeArtworkSides = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<choice_base_price_change\>/))
			newProduct.options[optionIndex].choices[choiceIndex].baseChange = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<choice_subtotal_change\>/))
			newProduct.options[optionIndex].choices[choiceIndex].subtotalChange = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<choice_base_weight_change\>/))
			newProduct.options[optionIndex].choices[choiceIndex].baseWeightChange = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<choice_project_weight_change\>/))
			newProduct.options[optionIndex].choices[choiceIndex].projectWeightChange = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<choice_quantity_price_breaks_base\>/))
			newProduct.options[optionIndex].choices[choiceIndex].quantityBreaksBase = getXMLdata(xmlLinesArr[lineNo]);

		else if(xmlLinesArr[lineNo].toString().match(/\<choice_quantity_price_breaks_subtotal\>/))
			newProduct.options[optionIndex].choices[choiceIndex].quantityBreaksSubtotal = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<choice_vendor_base_price_change\>/))
			newProduct.options[optionIndex].choices[choiceIndex].vendorBaseChanges = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<choice_vendor_subtotal_change\>/))
			newProduct.options[optionIndex].choices[choiceIndex].vendorSubtotalChanges = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<choice_vendor_quantity_price_breaks_base\>/))
			newProduct.options[optionIndex].choices[choiceIndex].vendorQuantityBreaksBase = getXMLdata(xmlLinesArr[lineNo]);
			
		else if(xmlLinesArr[lineNo].toString().match(/\<choice_vendor_quantity_price_breaks_subtotal\>/))
			newProduct.options[optionIndex].choices[choiceIndex].vendorQuantityBreaksSubtotal = getXMLdata(xmlLinesArr[lineNo]);
			
			
			
			
		// When we come across a "default" choice or the default quantity, add that to the selected choice Object
		else if(xmlLinesArr[lineNo].toString().match(/\<default_choice\>/) ){
			if(getXMLdata(xmlLinesArr[lineNo]) == "yes")
				newProduct.selectedOptionsObj.setOptionChoice(newProduct.options[optionIndex].optionName, newProduct.options[optionIndex].choices[choiceIndex].choiceName);
		}
			
		else if(xmlLinesArr[lineNo].toString().match(/\<default_quantity\>/))
			newProduct.selectedOptionsObj.setQuantity(getXMLdata(xmlLinesArr[lineNo]));
			
		
	}
	

	if(!newProduct.productID.match(/^\d+$/))
	{
		for(var i=0; i<this.productLoadingErrorEventsFunctionRefArr.length; i++){
			this.productLoadingErrorEventsFunctionRefArr[i].call(this.productLoadingErrorEventsObjectRefArr[i], "5103", "Product Definition Error");
		}
		return;
	}
	
	var productIDint = parseInt(newProduct.productID);
	var currentCachedProductsIndex = this.cachedProductObjectsPointerArr.length;
	
	// Add to the paralell array for the Cached Products.
	this.cachedProductObjectsPointerArr[currentCachedProductsIndex] = productIDint;
	this.cachedProductObjectsArr[currentCachedProductsIndex] = newProduct;
	
	this.privateMethod_ProductLoadingComplete(productIDint);
	
	
	// Now that we are done parsing one Product Object (thread safe).
	// Find out if there are any other XML files in the Queue.
	if(this.parsingXMLresponseQueue.length > 0){
		var nextXMLfileToParse = this.parsingXMLresponseQueue.pop();
		this.private_parseXMLresponse(nextXMLfileToParse);
	}
	
	this.parsingInProgressFlag = false;
	
}







// check to see if the Product has finished within this Project Loader Object.
ProductLoader.prototype.checkIfProductInitialized = function(productID)
{

	if(this.getIndexOfProductObjectsCache(productID) < 0)
		return false;
	else
		return true;
}

// Returns -1 if the productID has not been cached.
// Otherwise returns the index of the pararllel array for the Product Object cache.
ProductLoader.prototype.getIndexOfProductObjectsCache = function(productID)
{

	for(var i=0; i < this.cachedProductObjectsPointerArr.length; i++){	
		if(this.cachedProductObjectsPointerArr[i] == productID)
			return i;
	}
	
	return -1;
}

ProductLoader.prototype.getProductObj = function(productID)
{

	if(!this.checkIfProductInitialized(productID))
	{
		alert("Error in method getProductObj. You can't call this method until the Object has finished loading.");
		return false;
	}

	return this.cachedProductObjectsArr[this.getIndexOfProductObjectsCache(productID)];

}




















//------------------------------   Product Class -------------------------------------


// Product Class
// Do not call create new classes directly with this.
// Call the function getProductObj(ProdID)
function Product()
{
	this.productID = 0;
	this.productTitle = "Undefined Product Name";
	this.productTitleExt = "";
	this.productName = "Undefined Product Name";
	this.numberOfArtworkSides = 0;
	this.artworkWidthInches = 0;
	this.artworkHeightInches = 0;
	this.artworkBleedPicas = 0;
	this.artworkIsEditable = true;
	this.unitWeight = 0;
	this.basePrice = 0;
	this.initialSubtotal = 0;
	this.variableDataFlag = false;
	this.mailingServicesFlag = false;
	this.thumbnailBackgroundFlag = false;
	this.thumbnailCopyIconFlag = false;
	this.thumbnailWidthApprox = 0;
	this.thumbnailHeightApprox = 0;
	this.productImportance = 0;
	this.compatibleProductIDsArr = new Array();
	this.options = new Array();
	this.productSwitcher = new ProductSwitcher();
	
	// Multiple vendors are stored in a pararllel Array relative to price changes for the respective vendors.
	this.vendorNamesArr = new Array();
	
	// Prices for each vendor are separated by @ symbols as parrallel Array to Vendor Names.
	this.vendorBasePrices = "";
	this.vendorInitialSubtotals = "";
	
	// Quantity Breaks in the Format "20^0.20|100^0.40|500^0.60";
	this.quantityBreaksBase = "";
	this.quantityBreaksSubtotal = "";
	
	// Quantity Breaks in the Format "20^0.20@0.23@0.25|100^0.40@0.43@0.45|500^0.50@0.53@0.55";
	// Multiple vendors are separated by @ symbols.
	this.vendorQuantityBreaksBase = "";
	this.vendorQuantityBreaksSubtotal = "";
	
	// This will hold the default choices and quantity.
	// It will also be used to hold option/choice selection if someone wants to check prices without going through a Project Object.
	this.selectedOptionsObj = new SelectedOptions();
	
	// Cache the results of calculations we know won't change once they have been filled in.
	this.quantitySelectionArrCached = new Array();  
	
}


Product.prototype.getProductTitle = function()
{
	return this.productTitle;
}
Product.prototype.getProductTitleExt = function()
{
	return this.productTitleExt;
}
Product.prototype.getProductName = function()
{
	return this.productName;
}
Product.prototype.getArtworkWidthInches = function()
{
	return this.artworkWidthInches;
}
Product.prototype.getArtworkHeightInches = function()
{
	return this.artworkHeightInches;
}
Product.prototype.getArtworkBleedPicas = function()
{
	return this.artworkBleedPicas;
}
Product.prototype.isArtworkEditable = function()
{
	return this.artworkIsEditable;
}
Product.prototype.getPossibleArtworkSides = function()
{
	return this.numberOfArtworkSides;
}
Product.prototype.getProductID = function()
{
	return this.productID;
}
Product.prototype.isVariableData = function()
{
	return this.variableDataFlag;
}

Product.prototype.hasMailingServices = function()
{
	return this.mailingServicesFlag;
}


Product.prototype.getThumbnailWidthApprox = function()
{
	return this.thumbnailWidthApprox;
}
Product.prototype.getThumbnailHeightApprox = function()
{
	return this.thumbnailHeightApprox;
}


// This is an indication of whether we should Write a Title above the artwork thumbnail.
// Otherwise the Thumbnail background should generally contain the Thumbnail image inside.
Product.prototype.hasThumnailBackground = function()
{
	return this.thumbnailBackgroundFlag;
}

// Let's us know if there is a JPEG image uploaded to indicate a "copy" was madeof a project.
Product.prototype.hasThumnailCopyIcon = function()
{
	return this.thumbnailCopyIconFlag;
}


Product.prototype.getProductImportance = function()
{
	return this.productImportance;
}


Product.prototype.getCompatibleProductIDsArr = function()
{
	return this.compatibleProductIDsArr;
}

Product.prototype.getProductSwitcherObj = function()
{
	return this.productSwitcher;
}


// Returns an array of Product Option Names
Product.prototype.getOptionNames = function()
{
	
	var retArr = new Array();
	
	for(var i=0; i<this.options.length; i++)
		retArr.push(this.options[i].optionName);

	return retArr;
}


// Returns true if there are no choices under the Option.
// This shouldn't happen, but an administrator could add an option... and then forget to add choices.
// Also return TRUE if the option name does not exist.
Product.prototype.checkIfOptionIsEmpty = function(optName)
{
	for(var i=0; i<this.options.length; i++)
	{
		if(optName == this.options[i].optionName)
		{
			if(this.options[i].choices.length == 0)
				return true;
			else
				return false;
		}
	}
	return true;
}

// Returns true if there is only 1 choice under the Option.
Product.prototype.checkIfOptionHasSingleChoice = function(optName)
{
	for(var i=0; i<this.options.length; i++)
	{
		if(optName == this.options[i].optionName)
		{
			if(this.options[i].choices.length == 1)
				return true;
			else
				return false;
		}
	}
	alert("Error in method checkIfOptionHasSingleChoice. The Option Name does not exist.");
}


// Returns true if hte Option Name and the Choice exist on this product.
Product.prototype.checkIfOptionNameExist = function(optName)
{
	for(var i=0; i<this.options.length; i++)
	{
		if(optName == this.options[i].optionName)
			return true;
	}
	return false;
}

// Returns true if hte Option Name and the Choice exist on this product.
Product.prototype.checkIfOptionAndChoiceExist = function(optName, chcName)
{
	for(var i=0; i<this.options.length; i++)
	{
		if(optName == this.options[i].optionName)
		{
			for(var x = 0; x < this.options[i].choices.length; x++)
			{
				if(chcName == this.options[i].choices[x].choiceName)
					return true;
			}
		}
	}
	return false;
}


// Gives you a Description of the Option Name.
// Make sure that the Option Name exists before calling this method.
Product.prototype.getOptionDescription = function(optName)
{
	for(var i=0; i<this.options.length; i++)
	{
		if(optName == this.options[i].optionName)
			return this.options[i].optionDescription
	}
	
	alert("Error in method Product.getOptionDescription.\nThe Option Names does not exist: " + optName);
}

// Provides an alias of Option Name.
// Make sure that the Option Name exists before calling this method.
Product.prototype.getOptionAlias = function(optName)
{
	for(var i=0; i<this.options.length; i++)
	{
		if(optName == this.options[i].optionName)
			return this.options[i].optionAlias
	}
	
	alert("Error in method Product.getOptionAlias.\nThe Option Names does not exist: " + optName);
}

// Returns True or False depending on whether the Option Description is in HTML format or not.
// Make sure that the Option Name exists before calling this method.
Product.prototype.isOptionDescriptionHTMLformat = function(optName)
{
	for(var i=0; i<this.options.length; i++)
	{
		if(optName == this.options[i].optionName)
			return this.options[i].optionDescriptionIsHTML
	}
	
	alert("Error in method Product.isOptionDescriptionHTMLformat.\nThe Option Names does not exist: " + optName);
}


// Returns True or False depending on whether the Option is changed by Administrators
// Make sure that the Option Name exists before calling this method.
Product.prototype.doesOptionAffectNumOfArtworkSides = function(optName)
{
	for(var i=0; i<this.options.length; i++)
	{
		if(optName == this.options[i].optionName)
			return this.options[i].affectsArtworkSidesFlag
	}
	
	alert("Error in method Product.doesOptionAffectNumOfArtworkSides.\nThe Option Names does not exist: " + optName);
}

// Returns True or False depending on whether the Option affects Variable Images
// Make sure that the Option Name exists before calling this method.
Product.prototype.doesOptionAffectVariableImages = function(optName)
{
	for(var i=0; i<this.options.length; i++)
	{
		if(optName == this.options[i].optionName)
			return this.options[i].optionVariableImageController;
	}
	
	alert("Error in method Product.doesOptionAffectVariableImages.\nThe Option Names does not exist: " + optName);
}

// Returns True or False depending on whether the Option is changed by Administrators
// Make sure that the Option Name exists before calling this method.
Product.prototype.isOptionForAdministrators = function(optName)
{
	for(var i=0; i<this.options.length; i++)
	{
		if(optName == this.options[i].optionName)
			return this.options[i].adminOptionFlag;
	}
	
	alert("Error in method Product.isOptionForAdministrators.\nThe Option Names does not exist: " + optName);
}

// Returns True or False depending on whether the Option is changed by Administrators
// Make sure that the Option Name exists before calling this method.
Product.prototype.isOptionDiscountExempt = function(optName)
{
	for(var i=0; i<this.options.length; i++)
	{
		if(optName == this.options[i].optionName)
			return this.options[i].discountExempt;
	}
	
	alert("Error in method Product.isOptionDiscountExempt.\nThe Option Names does not exist: " + optName);
}







// Returns an array of Choice Names belonging to the Given Option
Product.prototype.getChoiceNamesForOption = function(optName)
{
	for(var i=0; i<this.options.length; i++)
	{
		if(optName == this.options[i].optionName)
				return this.options[i].getChoiceNames();
	}
	
	alert("Error in method Product.getChoiceNamesForOption.\nThe Option Names does not exist: " + optName);
}



// Returns True or False depending on whether the Choice Description is in HTML format or not.
// Make sure that the Option Name AND the Choice name must exist before calling this method.
Product.prototype.isChoiceDescriptionHTMLformat = function(optName, chcName)
{
	for(var i=0; i<this.options.length; i++)
	{
		if(optName == this.options[i].optionName)
		{
			for(var x = 0; x < this.options[i].choices.length; x++)
			{
				if(chcName == this.options[i].choices[x].choiceName)
					return this.options[i].choices[x].choiceDescriptionIsHTML;
			}
		}
	}
	
	alert("Error in method Product.isChoiceDescriptionHTMLformat.\nThe Option Names does not exist: " + optName + ".\nOr the Choice Name does not exist: " + chcName);
}


// Returns the full description of the Choice, belonging to the given option.
Product.prototype.getChoiceDescription = function(optName, chcName)
{
	for(var i=0; i<this.options.length; i++)
	{
		if(optName == this.options[i].optionName)
		{
			for(var x = 0; x < this.options[i].choices.length; x++)
			{
				if(chcName == this.options[i].choices[x].choiceName)
					return this.options[i].choices[x].choiceDescription;
			}
		}
	}
	
	alert("Error in method Product.getChoiceDescription.\nThe Option Names does not exist: " + optName + ".\nOr the Choice Name does not exist: " + chcName);
}


// Returns the Choice Name Alias belonging to the given option.
Product.prototype.getChoiceAlias = function(optName, chcName)
{
	for(var i=0; i<this.options.length; i++)
	{
		if(optName == this.options[i].optionName)
		{
			for(var x = 0; x < this.options[i].choices.length; x++)
			{
				if(chcName == this.options[i].choices[x].choiceName)
					return this.options[i].choices[x].choiceAlias;
			}
		}
	}
	
	alert("Error in method Product.getChoiceAlias.\nThe Option Names does not exist: " + optName + ".\nOr the Choice Name does not exist: " + chcName);
}



// Returns True or False depending on whether the Choice Description is in HTML format or not.
// Make sure that the Option Name AND the Choice name must exist before calling this method.
Product.prototype.isChoiceIsHidden = function(optName, chcName)
{
	for(var i=0; i<this.options.length; i++)
	{
		if(optName == this.options[i].optionName)
		{
			for(var x = 0; x < this.options[i].choices.length; x++)
			{
				if(chcName == this.options[i].choices[x].choiceName)
					return this.options[i].choices[x].choiceIsHiddenFlag;
			}
		}
	}
	
	alert("Error in method Product.isChoiceIsHidden.\nThe Option Names does not exist: " + optName + ".\nOr the Choice Name does not exist: " + chcName);
}


// Returns a number that tells you how many Artwork Sides there will be with the choice selected.
Product.prototype.choiceChangesNumberOfArtworkSidesTo = function(optName, chcName)
{
	for(var i=0; i<this.options.length; i++)
	{
		if(optName == this.options[i].optionName)
		{
			for(var x = 0; x < this.options[i].choices.length; x++)
			{
				if(chcName == this.options[i].choices[x].choiceName)
					return this.options[i].choices[x].changeArtworkSides;
			}
		}
	}
	
	alert("Error in method Product.choiceChangesNumberOfArtworkSidesTo.\nThe Option Names does not exist: " + optName + ".\nOr the Choice Name does not exist: " + chcName);
}





// Returns an Object defining the Options and Selections
// By default this selects the first Choice in each Option.
// Returns a Copy each time... so you can store independent states within each Project
Product.prototype.getSelectedOptionsObj = function()
{
	var selOptionsObj = new SelectedOptions();
	
	// Select the First Quantity Choice
	var quanSelectArr = this.getQuantityChoicesArr();
	if(quanSelectArr.length != 0)
		selOptionsObj.setQuantity(quanSelectArr[0]);
	
	for(var i=0; i<this.options.length; i++)
	{
		// Don't include Options without any Choices.
		if(this.options[i].choices.length == 0)
			continue;
		
		selOptionsObj.setOptionChoice(this.options[i].optionName, this.options[i].choices[0].choiceName);
	}
	
	return selOptionsObj;
}






// Returns an array of Quantities that exist within the Root Quantity Price Breaks.
// If this is a Variable Data Product... or no Quantity Breaks have been defined, then this method will return an empty array.
Product.prototype.getQuantityChoicesArr = function()
{

	if(this.quantityBreaksSubtotal == "")
		return new Array();

	// Find out if we have already calculated the results for this Product.
	if(this.quantitySelectionArrCached.length != 0)
		return this.quantitySelectionArrCached;

	var retArr = new Array()	

	var breaksArr = new Array();
	breaksArr = this.quantityBreaksSubtotal.split("|");

	for(var breakCounter=0; breakCounter<breaksArr.length; breakCounter++)
	{

		var quanPriceArr = new Array();
		quanPriceArr = breaksArr[breakCounter].split("^");

		if(quanPriceArr.length != 2)
		{
			alert("Problem in Product.getQuantityChoicesArr the Price Modifiation String is not in the proper format.: " + this.quantityBreaksSubtotal);
			return new Array(0);
		}

		retArr[breakCounter] = quanPriceArr[0];
	}


	// Cache the Results.
	this.quantitySelectionArrCached = retArr;

	return retArr;
}


// Weight is returned with 2 decimal places.  It is up to you to "Ceil" that result to the next highest integer if you want.
// Gets the Subtotal for the Product.
// Will take a SelectedOptions parameter to use that quantity/option/choice selection.
// .. the Option/Choices (which can have an affect on weight).
// This is useful for Project Classes to get the Weight based upon settings stored in the Project.
Product.prototype.getTotalWeight = function(selectedOptionsObj)
{

	return this.getTotalWeightOverride(this.selectedOptionsObj);


}

// Weight is returned with 2 decimal places.  It is up to you to "Ceil" that result to the next highest integer if you want.
// Gets the Weight for the Product based upon the Quantity and Choice specified in the selectedOptionsObj object.
// The Product Object can hold Quantity and Option/Choice selections independently from Project Objects
Product.prototype.getTotalWeightOverride = function(selectedOptionsObj)
{

	if(!(selectedOptionsObj instanceof SelectedOptions))
	{
		alert("Error in Product.getTotalWeight. The parameter must be an Object of SelectedOptions");
		return;
	}

	var totalUnitWeight = parseFloat(this.unitWeight);
	var projectWeightChanges = 0;

	for(var i=0; i<this.options.length; i++)
	{
		// Skip Empty Options
		if(this.options[i].choices.length == 0)
			continue;
	
		var selectedChoiceName = selectedOptionsObj.getChoiceSelected(this.options[i].optionName);
		
		if(selectedChoiceName == null)
		{
			alert("Error in Product.Weight. The option Name was not found within the selection: " + this.options[i].optionName);
			return;
		}

		// Select the right choice... and make sure all of the other choices are not selected.
		for(var x=0; x<this.options[i].choices.length; x++)
		{
			if(this.options[i].choices[x].choiceName == selectedChoiceName)
			{
				totalUnitWeight += this.options[i].choices[x].getBaseWeightChange();
				projectWeightChanges += this.options[i].choices[x].getProjectWeightChange();
				break;
			}
		}
	}

	return RoundWithTwoDecimals(totalUnitWeight * selectedOptionsObj.getSelectedQuantity() + projectWeightChanges);
}



// Gets the Subtotal for the Product.
// The Product Object can hold Quantity and Option/Choice selections independently from Project Objects
Product.prototype.getSubtotal = function()
{

	return this.getSubtotalOverride(this.selectedOptionsObj);

}



Product.prototype.getSelectedQuantity = function()
{
	return this.selectedOptionsObj.getSelectedQuantity();
}


// Returns the Choice selected,...  Alerts an Error if the Option Name does not exist.
Product.prototype.getChoiceSelected = function(optName)
{
	var choiceSelected = this.selectedOptionsObj.getChoiceSelected(optName);
	
	if(choiceSelected == null){
		alert("Error in Product.getChoiceSelected(). The Option Name does not exist: " + optName)
		return null;
	}
	
	return choiceSelected;
}


// Gets the Subtotal for the Product.
// Will take a SelectedOptions parameter to use that quantity/option/choice selection.
// This is useful for Project Classes to get the Subtotal based upon a certain selection.
Product.prototype.getSubtotalOverride = function(selectedOptionsObj)
{

	if(!(selectedOptionsObj instanceof SelectedOptions))
	{
		alert("Error in Product.getSubtotal. The parameter must be an Object of SelectedOptions");
		return;
	}
	
	var totalBaseChanges = parseFloat(this.basePrice);
	var totalSubtotalChanges = parseFloat(this.initialSubtotal);


	// Add any Quantity Price Breaks into the root product.
	totalBaseChanges += getLastPriceModification(selectedOptionsObj.getSelectedQuantity(), this.quantityBreaksBase);
	totalSubtotalChanges += getLastPriceModification(selectedOptionsObj.getSelectedQuantity(), this.quantityBreaksSubtotal);


	for(var i=0; i<this.options.length; i++){
	
		// Skip Empty Options
		if(this.options[i].choices.length == 0)
			continue;

		var selectedChoiceName = selectedOptionsObj.getChoiceSelected(this.options[i].optionName);
		
		if(selectedChoiceName == null)
		{
			alert("Error in Product.getSubtotal. The option Name was not found within the selection: " + this.options[i].optionName);
			return;
		}

		// Select the right choice... and make sure all of the other choices are not selected.
		for(var x=0; x<this.options[i].choices.length; x++)
		{

			if(this.options[i].choices[x].choiceName == selectedChoiceName)
			{
				totalBaseChanges += this.options[i].choices[x].getBasePriceChange(selectedOptionsObj.getSelectedQuantity());
				totalSubtotalChanges += this.options[i].choices[x].getSubtotalPriceChange(selectedOptionsObj.getSelectedQuantity());
				break;
			}
		}
	}
	
	// Find out if any invalid options/choices were set.
	// We want to alert our developers that they made a mistake or that the Product Options in the DB have changed.
	var allOptionsAndChoicesFoundFlag = true;

	var optionNamesArr = selectedOptionsObj.getOptionNames();
	for(var i=0; i<optionNamesArr.length; i++){
		
		var thisOptionName = optionNamesArr[i];
		var thisChoiceName = selectedOptionsObj.getChoiceSelected(thisOptionName);
		
		var thisOptionAndChoiceFound = false;

		for(var x=0; x<this.options.length; x++){
			
			if(thisOptionAndChoiceFound)
				continue;
			
			if(this.options[x].optionName == thisOptionName)
			{
				for(var j=0; j<this.options[x].choices.length; j++)
				{
					if(this.options[x].choices[j].choiceName == thisChoiceName)
					{	
						thisOptionAndChoiceFound = true;
						break;
					}
				}
			}
		}
		
		if(!thisOptionAndChoiceFound)
			allOptionsAndChoicesFoundFlag = false;
	}
	
	// Rather than alert an Error, just return N/A
	if(!allOptionsAndChoicesFoundFlag)
		return "N/A";

	return RoundWithTwoDecimals(totalSubtotalChanges + totalBaseChanges * selectedOptionsObj.getSelectedQuantity());
}

// Some Product Options are exempt from discounts (such as postage)
// This will return the amount of the subtotal which is exempt from receiving discounts, based upon what option/choices have been selected.
Product.prototype.getSubtotalExemptFromDiscounts = function(selectedOptionsObj)
{
	
	if(!(selectedOptionsObj instanceof SelectedOptions))
	{
		alert("Error in Product.getSubtotalExemptFromDiscounts. The parameter must be an Object of SelectedOptions");
		return;
	}
	
	var returnAmnt = 0;
	
	for(var i=0; i<this.options.length; i++){
	
		// Skip Empty Options
		if(this.options[i].choices.length == 0)
			continue;
			
		// Skip Options, unless they are discount exempt.
		if(!this.options[i].discountExempt)
			continue;
			
		var selectedChoiceName = selectedOptionsObj.getChoiceSelected(this.options[i].optionName);
		
		if(selectedChoiceName == null)
		{
			alert("Error in Product.getSubtotalExemptFromDiscounts. The option Name was not found within the selection: " + this.options[i].optionName);
			return;
		}

		// Select the right choice... and make sure all of the other choices are not selected.
		for(var x=0; x<this.options[i].choices.length; x++)
		{

			if(this.options[i].choices[x].choiceName == selectedChoiceName)
			{
				returnAmnt += this.options[i].choices[x].getBasePriceChange(selectedOptionsObj.getSelectedQuantity());
				returnAmnt += this.options[i].choices[x].getSubtotalPriceChange(selectedOptionsObj.getSelectedQuantity());
				break;
			}
		}
	}
	
	return returnAmnt;
	
}




// Works similar to getSubtotalOverride, except that it fetched the Vendor Subtotal.
// Pass in a number 1 through 6
Product.prototype.getVendorTotal = function(selectedOptionsObj, vendorNumber)
{

	if(!(selectedOptionsObj instanceof SelectedOptions))
	{
		alert("Error in Product.getVendorTotal. The parameter must be an Object of SelectedOptions");
		return;
	}
	
	vendorNumber = parseInt(vendorNumber);
	if(vendorNumber < 1)
	{
		alert("Error in Product.getVendorTotal. The vendor number must start with 1.");
		return;
	}
	
	var vendorBasePricesArr = this.vendorBasePrices.split("@");
	if(vendorNumber > vendorBasePricesArr.length)
	{
		alert("Error in Product.getVendorTotal. The Vendor Number is out of Range for Base Prices.");
		return;
	}
	var vendorInitialSubtotalsArr = this.vendorInitialSubtotals.split("@");
	if(vendorNumber > vendorInitialSubtotalsArr.length)
	{
		alert("Error in Product.getVendorTotal. The Vendor Number is out of Range for Subtotals.");
		return;
	}
	
	var totalBaseChanges = parseFloat(vendorBasePricesArr[(vendorNumber - 1)]);
	var totalSubtotalChanges = parseFloat(vendorInitialSubtotalsArr[(vendorNumber - 1)]);

	// Add any Quantity Price Breaks into the root product.
	totalBaseChanges += getLastPriceModification(selectedOptionsObj.getSelectedQuantity(), this.vendorQuantityBreaksBase, vendorNumber);
	totalSubtotalChanges += getLastPriceModification(selectedOptionsObj.getSelectedQuantity(), this.vendorQuantityBreaksSubtotal, vendorNumber);


	for(var i=0; i<this.options.length; i++){
	
		// Skip Empty Options
		if(this.options[i].choices.length == 0)
			continue;

		var selectedChoiceName = selectedOptionsObj.getChoiceSelected(this.options[i].optionName);
		
		if(selectedChoiceName == null)
		{
			alert("Error in Product.getVendorTotal. The option Name was not found within the selection: " + this.options[i].optionName);
			return;
		}

		// Select the right choice... and make sure all of the other choices are not selected.
		for(var x=0; x<this.options[i].choices.length; x++)
		{

			if(this.options[i].choices[x].choiceName == selectedChoiceName)
			{
				totalBaseChanges += this.options[i].choices[x].getVendorBasePriceChange(selectedOptionsObj.getSelectedQuantity(), vendorNumber);
				totalSubtotalChanges += this.options[i].choices[x].getVendorSubtotalPriceChange(selectedOptionsObj.getSelectedQuantity(), vendorNumber);
				break;
			}
		}
	}
	


	return RoundWithTwoDecimals(totalSubtotalChanges + totalBaseChanges * selectedOptionsObj.getSelectedQuantity());
}


























// ------------  SelectedOptions Class -----------------------------------------





// This should be copied into Every Project so they may hold their Option/Choices/Quantity values Separately.
// Can also be used for calculating Prices before a Project has been created.
function SelectedOptions()
{

	// Holds an array of OptionAndSelection objects.
	this.optionsAndSelectionsArr = new Array();
	
	this.quantitySelected = 0;
}



SelectedOptions.prototype.getSelectedQuantity = function()
{
	return this.quantitySelected;
}
SelectedOptions.prototype.setQuantity = function(quantity)
{
	this.quantitySelected = parseInt(quantity);
}



// Returns an Array of Option Names
SelectedOptions.prototype.getOptionNames = function()
{
	var retArr = new Array();

	for(var i=0; i<this.optionsAndSelectionsArr.length; i++)
		retArr.push(this.optionsAndSelectionsArr[i].optionName);
	
	return retArr;
}


// Returns the Choice selected, Null if the Option Name does not exist.
SelectedOptions.prototype.getChoiceSelected = function(optName)
{

	for(var i=0; i< this.optionsAndSelectionsArr.length; i++)
	{
		if(this.optionsAndSelectionsArr[i].optionName == optName)
			return this.optionsAndSelectionsArr[i].choiceName
	}

	return null;
}

// Sets the Choice for the Given Option Name.
// If the Option Name does not currently exist... it will add the Option and Selected Choice
SelectedOptions.prototype.setOptionChoice = function(optName, choiceName)
{

	for(var i=0; i< this.optionsAndSelectionsArr.length; i++)
	{
	
		if(this.optionsAndSelectionsArr[i].optionName == optName)
		{	
			 this.optionsAndSelectionsArr[i].choiceName = choiceName;
			 return;
		}
	}
	
	var newOptSelectObj = new OptionAndSelection(optName, choiceName);

	// Create a new Option and Selected Choice
	this.optionsAndSelectionsArr.push(newOptSelectObj);
}

// Parse an Options Description String that is separated by commas and Dashes.
SelectedOptions.prototype.parseOptionsDescription = function(optDesc)
{

	// Clean the slate before parsing the Option Description.
	this.optionsAndSelectionsArr = new Array();

	// Commas separate each Option from one another.
	var optArr = optDesc.split(", ");
	
	for(var i=0; i< optArr.length; i++)
	{
		if(optArr[i] == "")
			continue;

		// Now extract the Option and Choice name
		// They are split on the First Dash... there could be remaining dashes in the Choice Name... but there are never dashes in the Option Name.
		var optChcPartsArr = optArr[i].split(" - ");
		
		if(optChcPartsArr.length < 2)
		{
			alert("Error parsing the Option/Choices. The format is invalid: " + optDesc);
			continue;
		}
		
		var thisOptionName = optChcPartsArr[0];
		var thisChoiceName = optChcPartsArr[1];
		
		// If there were dashes in the Choice Name... the glue the remaining pieces back together and put the dashes back in.
		for(var j=2; j<optChcPartsArr.length; j++)
			thisChoiceName += " - " + optChcPartsArr[j];
	
		
		// Save the Option/Choice Selection
		this.setOptionChoice(thisOptionName, thisChoiceName);
	}
}



// Works the opposite to parseOptionsDescription.
// Will take all of the Options & Selected Choiced and turn them into a string formated by commas and dashes
SelectedOptions.prototype.getOptionAndChoicesStr = function()
{
	var retStr = "";
	
	var optionNamesArr = this.getOptionNames();
	
	for(var i=0; i< optionNamesArr.length; i++)
	{
		if(retStr != "")
			retStr += ", ";
		
		retStr += optionNamesArr[i] + " - " + this.getChoiceSelected(optionNamesArr[i]);
	
	}
	
	return retStr;
}




// This is just a Container Object... since Javascript Hashes can't contain Spaces (like Option Names).
function OptionAndSelection(optName, selChoice)
{
	this.optionName = optName;
	this.choiceName = selChoice
}












// ----------------------   ProductSwitch Class --------------------------------
function ProductSwitcher() 
{
	this.projectSwitches = new Array();

}




// Returns an Array of Product IDs that exit for Product Switching. It doesn't matter if the Product is Grouped with another.
ProductSwitcher.prototype.getAllTargetProductIDs = function()
{	
	var retArr = new Array();
	
	// Make sure that we are not adding duplicates.
	for(var i=0; i<this.projectSwitches.length; i++)
		retArr.push(this.projectSwitches[i].productIDtarget);

	return retArr;
}

// Returns an array of all Products that are not in Groups.
// Returns an Empty Array if all Products are in a Group.
ProductSwitcher.prototype.getProductIDsNotInGroup = function()
{	
	var retArr = new Array();
	
	// Make sure that we are not adding duplicates.
	for(var i=0; i<this.projectSwitches.length; i++)
	{
		if(!this.projectSwitches[i].isGroupHead && this.projectSwitches[i].groupedToProductID == 0)
			retArr.push(this.projectSwitches[i].productIDtarget);
	}

	return retArr;
}



// If there are other Products with matching Product Switching Titles, then they are considered to be in the same group.
// Returns an Empty Array if there are no Groups for Product Switching.
// Even through all of the Product Switch Titles share the same group title... only one of the Product ID's is considered the Group Leader.
ProductSwitcher.prototype.getProductIDsForGroupsHeads = function()
{	
	var retArr = new Array();
	
	// Make sure that we are not adding duplicates.
	for(var i=0; i<this.projectSwitches.length; i++)
	{
		if(this.projectSwitches[i].isGroupHead)
			retArr.push(this.projectSwitches[i].productIDtarget);
	}

	return retArr;
}




// Returns an Array of ProductIDs within the Group ID that have their links (link subjects) that are supposed to show up in the group head.
ProductSwitcher.prototype.getProductIDsWithLinksInsideOfGroupHeads = function(groupHeadProductID)
{	
	var retArr = new Array();
	
	var productIDsInGroup = this.getProductsIDsInGroup(groupHeadProductID);
	
	// Make sure that we are not adding duplicates.
	for(var i=0; i<productIDsInGroup.length; i++)
	{
		if(this.checkIfLinkUsedElsewhere(productIDsInGroup[i]))
			retArr.push(productIDsInGroup[i]);
	}

	return retArr;
}

// Works kind of opposite to getProductIDsWithLinksInsideOfGroupHeads()
// Returns an Array of ProductIDs within the Group ID ... but that don't want the link showing inside of the Group Head Description
ProductSwitcher.prototype.getProductIDsWithoutLinksInsideOfGroupHeads = function(groupHeadProductID)
{	
	var retArr = new Array();
	
	var productIDsInGroup = this.getProductsIDsInGroup(groupHeadProductID);
	
	// Make sure that we are not adding duplicates.
	for(var i=0; i<productIDsInGroup.length; i++)
	{
		if(!this.checkIfLinkUsedElsewhere(productIDsInGroup[i]))
			retArr.push(productIDsInGroup[i]);
	}

	return retArr;
}




// Pass in the Product ID of the Group Head.
// Make sure that the Product ID does indeed belong to a group head or an Error will be alerted.
// The array returned will not contain the Product ID parent.
ProductSwitcher.prototype.getProductsIDsInGroup = function(groupHeadProductID)
{	
	var retArr = new Array();
	
	// Make sure that we are not adding duplicates.
	for(var i=0; i<this.projectSwitches.length; i++)
	{
		if(this.projectSwitches[i].groupedToProductID == groupHeadProductID)
			retArr.push(this.projectSwitches[i].productIDtarget);
	}
	
	if(retArr.length == 0)
	{
		alert("Error in method ProductSwitcher.getOtherProductsIDsInGroup. The Product ID of the Group head is not valid.");
		return;
	}

	return retArr;
}


// It is safe to call this method on all Product IDs (whether they are grouped or not) to know if you should create a link for Product Switching
// If there is no Description on the Product Switch... but there is a Link Subject ... AND this Product is Grouped to another Product Group Head... then it will return true.
// ... because it assumes that you want the Link subject to show up in the Group Head.
// But if there is a description for the Product (even if it belongs to a group head) ... then the link should stay by the description.
// If the product does not belong to another group (or if this product is the group head) ... then there is no choice but to make a link on this product entry.
// For example... if there is 1 heading that says "Switch to a Larger Postcard"... you may want to have many links within the description of that heading if there are multiple products (like 4x6, 5.5x8.5 etc).
ProductSwitcher.prototype.checkIfLinkUsedElsewhere = function(targetProdID)
{	
	var retArr = new Array();
	

	var targetSwitchContainerObj = null;
	
	for(var i=0; i<this.projectSwitches.length; i++)
	{
		// Find the ProductSwitchContainer
		if(this.projectSwitches[i].productIDtarget == targetProdID)
		{
			targetSwitchContainerObj = this.projectSwitches[i];
			break;
		}
	}
	
	if(targetSwitchContainerObj == null)
	{
		alert("Error in ProductSwitcher.checkIfLinkShouldBeUsedElsewhere.  The Product ID is not defined.");
		return;
	}
	
	// If this Product Has its own description. Then the link Subject should stay inside.
	if(this.projectSwitches[i].description != "")
		return false;

	// Make sure that this Product is not Grouped.. and it is not the group parent.
	if(parseInt(targetSwitchContainerObj.groupedToProductID) == 0 || targetSwitchContainerObj.isGroupHead)
		return false;
		
	return true;

}



// Pass in the Product ID relating to one of the Product Switches
// It will give you the Description Relating to that Product
// Returns a blank string if none defined.
ProductSwitcher.prototype.getDescription = function(targetProdID)
{	
	for(var i=0; i<this.projectSwitches.length; i++)
	{
		// Find the ProductSwitchContainer
		if(this.projectSwitches[i].productIDtarget == targetProdID)
			return this.projectSwitches[i].description;
	}
	

	alert("Error in method ProductSwitcher.getDescription. The Product ID was not found.");

}


// Does the same thing as getDescription() ... but it makes sure the text is HTML encoded (if it is not already).
ProductSwitcher.prototype.getDescriptionForHTML = function(targetProdID)
{	
	var descHTML = this.getDescription(targetProdID);
	
	if(this.isDescriptionInHTMLformat(targetProdID))
		return descHTML;
	else
		return htmlize(descHTML);

}


// Pass in the Product ID relating to one of the Product Switches... 
// ... return True or False whether the description is in HTML format.
ProductSwitcher.prototype.isDescriptionInHTMLformat = function(targetProdID)
{	
	for(var i=0; i<this.projectSwitches.length; i++)
	{
		// Find the ProductSwitchContainer
		if(this.projectSwitches[i].productIDtarget == targetProdID)
			return this.projectSwitches[i].descriptionIsHTML;
	}
	

	alert("Error in method ProductSwitcher.isDescriptionInHTMLformat. The Product ID was not found.");

}




// Pass in the Product ID relating to one of the Product Switches
// It will give you the Link Subject Relating to that Product
// Returns the Producct Name if there is no link subject (so it always returns something).
ProductSwitcher.prototype.getLinkSubject = function(targetProdID)
{	
	for(var i=0; i<this.projectSwitches.length; i++)
	{
		// Find the ProductSwitchContainer
		if(this.projectSwitches[i].productIDtarget == targetProdID)
		{
			if(this.projectSwitches[i].linkSubject == "")
				return this.getProductName(targetProdID);
			else
				return this.projectSwitches[i].linkSubject;
		}
	}
	

	alert("Error in method ProductSwitcher.getLinkSubject. The Product ID was not found.");
}

// Pass in the Product ID relating to one of the Product Switches for that Switch Title
// If 2 or more Products are in a Group... they will share the same Switch Title
ProductSwitcher.prototype.getSwitchTitle = function(targetProdID)
{	
	for(var i=0; i<this.projectSwitches.length; i++)
	{
		// Find the ProductSwitchContainer
		if(this.projectSwitches[i].productIDtarget == targetProdID)
			return this.projectSwitches[i].switchTitle;
	}
	

	alert("Error in method ProductSwitcher.getSwitchTitle. The Product ID was not found.");
}


// Pass in the Product ID relating to one of the Product Switches for that Product Name
ProductSwitcher.prototype.getProductName = function(targetProdID)
{	
	for(var i=0; i<this.projectSwitches.length; i++)
	{	
		// Find the ProductSwitchContainer
		if(this.projectSwitches[i].productIDtarget == targetProdID)
			return this.projectSwitches[i].productName;
	}
	

	alert("Error in method ProductSwitcher.getProductName. The Product ID was not found.");
}










// ----------------------   ProductSwitch Container --------------------------------
function ProductSwitchContainer() 
{
	this.productIDtarget = 0;
	this.productName = "Product Name Undefined";
	this.switchTitle = "Product Switch Title Undefined";
	this.linkSubject = "";
	this.description = "";
	this.descriptionIsHTML = false;
	this.isGroupHead = false;
	this.groupedToProductID = 0;
}
















// ----------------------   ProductOption Class --------------------------------
function ProductOption() 
{

	this.optionName = "Option Name Undefined";
	this.optionAlias = "Option Alias Undefined.";
	this.optionDescription = "Option Description Undefined.";
	this.optionDescriptionIsHTML = false;
	this.optionVariableImageController = false;
	this.discountExempt = false;
	this.affectsArtworkSidesFlag = false;
	this.adminOptionFlag = false;
	this.choices = new Array();
	

}

// Returns an Array of Choice Names within this options.
ProductOption.prototype.getChoiceNames = function()
{	
	var retArr = new Array();

	for(var i=0; i<this.choices.length; i++)
		retArr.push(this.choices[i].choiceName);

	return retArr;
}


// Returns a Choice Object matching the ChoiceName
// Return NULL if the choice name does not exist.
ProductOption.prototype.getChoiceObj = function(chcName)
{	
	var retArr = new Array();

	
	for(var choiceCounter = 0;  choiceCounter < this.choices.length; choiceCounter++)
	{
		if(chcName == this.choices[choiceCounter].choiceName)
			return choices[choiceCounter];
	}

	return null;
}












// ---------------------- OptionChoice Class --------------------------------
function OptionChoice() 
{

	// Define Properties of this class.
	this.choiceName = "Choice Name Undefined";
	this.choiceAlias = "Choice Alias Undefined";
	this.choiceDescription = "Choice Description Undefined";
	this.choiceDescriptionIsHTML = false;
	this.choiceIsHiddenFlag = false;
	this.choiceVariableImages = false;
	this.changeArtworkSides = 0;
	this.baseChange = 0;
	this.subtotalChange = 0;
	this.baseWeightChange = 0;
	this.projectWeightChange = 0;
	
	// Prices for each vendor are separated by @ symbols as parrallel Array to Vendor Names.
	this.vendorBaseChanges = "";
	this.vendorSubtotalChanges = "";
	
	// Quantity Breaks in the Format "20^0.20|100^0.40|500^0.60";

	this.quantityBreaksBase = "";
	this.quantityBreaksSubtotal = "";
	
	// Multiple Vendors are separated by @ symbols as a parralell array to Product Vendor Names.
	// Quantity Breaks in the Format "20^0.20@0.23@0.25|100^0.40@0.43@0.45|500^0.50@0.53@0.55";
	this.vendorQuantityBreaksBase = "";
	this.vendorQuantityBreaksSubtotal = "";
	


}


// It will return the Base Price for the given choice
// Base Price Changes include the Regular Price Changes, in addition to any Quantity Price Breaks on the Option/Choice
OptionChoice.prototype.getBasePriceChange = function(quantityToCheck)
{
	return getLastPriceModification(quantityToCheck, this.quantityBreaksBase) + parseFloat(this.baseChange);
}
// It will return the Subtotal Change for the given choice based upon the Quantity 
OptionChoice.prototype.getSubtotalPriceChange = function(quantityToCheck)
{
	return getLastPriceModification(quantityToCheck, this.quantityBreaksSubtotal) + parseFloat(this.subtotalChange);
}


// It will return the Vendor Base Price for the given choice
// Base Price Changes include the Regular Price Changes, in addition to any Quantity Price Breaks on the Option/Choice
OptionChoice.prototype.getVendorBasePriceChange = function(quantityToCheck, vendorNumber)
{
	vendorNumber = parseInt(vendorNumber);
	
	var vendorBaseChangesArr = this.vendorBaseChanges.split("@");
	if(vendorNumber < 1 || vendorNumber > vendorBaseChangesArr.length)
	{
		alert("Error in method OptionChoice.getVendorBasePriceChange. The Vendor Number is out of range: " + vendorNumber);
		return null;
	}
	
	return getLastPriceModification(quantityToCheck, this.vendorQuantityBreaksBase, vendorNumber) + parseFloat(vendorBaseChangesArr[(vendorNumber -1)]);
}
// It will return the Subtotal Change for the given choice based upon the Quantity 
OptionChoice.prototype.getVendorSubtotalPriceChange = function(quantityToCheck, vendorNumber)
{
	vendorNumber = parseInt(vendorNumber);

	var vendorSubtotalChangesArr = this.vendorSubtotalChanges.split("@");
	if(vendorNumber < 1 || vendorNumber > vendorSubtotalChangesArr.length)
	{
		alert("Error in method OptionChoice.getVendorSubtotalPriceChange. The Vendor Number is out of range: " + vendorNumber);
		return null;
	}
	
	return getLastPriceModification(quantityToCheck, this.vendorQuantityBreaksSubtotal, vendorNumber) + parseFloat(vendorSubtotalChangesArr[(vendorNumber -1)]);
}

// Returns the weight change of the thoice
OptionChoice.prototype.getBaseWeightChange = function(){
	return parseFloat(this.baseWeightChange);
}

OptionChoice.prototype.getProjectWeightChange = function(){
	return parseFloat(this.projectWeightChange);
}
















// --------- This class fetches an list of Product ID's and Product Titles that are active in the system -------
function ProductCollection(){
	
	this.productIdsArr = new Array();
	this.productTitlesArr = new Array();
	this.productTitleExtArr = new Array();
	
	this.apiBaseBath = "./";
	
	this.prodCollectResponseEventsFunctionsArr = new Array();
	this.prodCollectResponseEventsObjectsArr = new Array();
	
	this.prodCollectCriticalErrorEventsFunctionsArr = new Array();
	this.prodCollectCriticalErrorEventsObjectsArr = new Array();
}

ProductCollection.prototype.setApiBasePath = function(pathStr)
{
	this.apiBaseBath = pathStr;
}

ProductCollection.prototype.attachProductCollectionResponseEvents = function(functionRef, objectRef)
{
	this.prodCollectResponseEventsFunctionsArr.push(functionRef);
	this.prodCollectResponseEventsObjectsArr.push(objectRef);
}

ProductCollection.prototype.attachProductCollectionCriticalErrorEvents = function(functionRef, objectRef)
{
	this.prodCollectCriticalErrorEventsFunctionsArr.push(functionRef);
	this.prodCollectCriticalErrorEventsObjectsArr.push(objectRef);
}


// Returns a list of Active Product IDs
// This must be called after the API communication has finished.
ProductCollection.prototype.getProductIdsArr = function()
{
	return this.productIdsArr;
}

ProductCollection.prototype.getProductTitleByProductID = function(productID)
{
	for(var i=0; i<this.productIdsArr.length; i++){
	
		if(this.productIdsArr[i] == productID){
			return this.productTitlesArr[i];
		}
	}
	return "";

}
ProductCollection.prototype.getProductTitleExtByProductID = function(productID)
{
	for(var i=0; i<this.productIdsArr.length; i++){
		if(this.productIdsArr[i] == productID){
			return this.productTitleExtArr[i];
		}
	}
	return "";
}
ProductCollection.prototype.getProductTitleWithExtByProductID = function(productID)
{
	if(this.getProductTitleExtByProductID(productID) == "")
		return this.getProductTitleByProductID(productID);
		
	return this.getProductTitleByProductID(productID) + " - " + this.getProductTitleExtByProductID(productID);
}




// Set the UserName and Password before calling this method.
ProductCollection.prototype.fetchProductList = function(productTypes)
{

	// Every time we contact the server, reset the product ID's
	this.productIdsArr = new Array();
	this.productTitlesArr = new Array();
	this.productTitleExtArr = new Array();

	// So we can scope the callback.
	var instance = this;

	// This doesn't work if you go over 60 mintues.
	var numberOfMinutesToCacheResults = 15;
	var dateObj = new Date();
	var dateCache = dateObj.getYear() + "-" + dateObj.getMonth() + "-" +  dateObj.getDate() + "-" +  dateObj.getHours() + "-" + (Math.ceil(parseInt(dateObj.getMinutes()) / numberOfMinutesToCacheResults)) ;

	var apiURL = this.apiBaseBath + "api_dot.php";
	var apiParameters = "api_version=1.1&command=get_product_list&product_types="+productTypes+"&nocache=" + dateCache;
	
	// Get a free/new connection object by reference. Abort method call if an HTTP object cant be created. The method getXmlHttpObj will alert an error message too.
	var xmlHttpObjFromGlobalQueue = getXmlHttpObj("ProductCollectionXML");
	if(xmlHttpObjFromGlobalQueue == null)
		return;
	
	xmlHttpObjFromGlobalQueue.open('GET', (apiURL + "?" + apiParameters), true);


	// Micosoft does not like you setting the Ready State before calling "open".
	xmlHttpObjFromGlobalQueue.onreadystatechange = function() 
	{

		// Now the Callback happened from the XMLHttpRequest communication
		// We have to find out which one was used from our Global Array of connection pools.
		// There is no way to guarantee the order in which these are processed.
		// We are basically looking for the first connection object which is Not Free... Having a matching CallBack function "identifier string".
		for(var t=0; t<xmlReqsObjArr.length; t++){
	
			if(xmlReqsObjArr[t].reqObjFree || xmlReqsObjArr[t].reqObjParsing || xmlReqsObjArr[t].xmlHttpObj.readyState != 4 || xmlReqsObjArr[t].callBackFunctionName != "ProductCollectionXML")
				continue;
			if(xmlReqsObjArr[t].xmlHttpObj.status == "200"){
				xmlReqsObjArr[t].reqObjParsing = true;
				instance.parseXMLresponse(xmlReqsObjArr[t].xmlHttpObj.responseText);
			}
			else{
				instance.errorEventProductCollection_private(xmlReqsObjArr[t].xmlHttpObj.status, xmlReqsObjArr[t].xmlHttpObj.statusText);
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

// If you make any changes to the quantity or the options/choices, call this method to save to the database.
// Make sure to define an event handler to listen for the response from the server.
ProductCollection.prototype.parseXMLresponse = function(xmlhttpresponseText)
{

	// Find out if the document was not what we expected to receive.
	// If Not, try to get an Error message and send that through the error event.
	if(!xmlhttpresponseText.match(/\<result\>OK\<\/result\>/))
	{
		// Look for an error message in the response, since we didn't get an "OK"
		var errorMessage = getErrorMessageFromAPIresponseXML(xmlhttpresponseText);
		
		if(errorMessage == ""){
			this.errorEventProductCollection_private("5101", "Unknown Error Parsing XML Document");
		}
		else{
			this.errorEventProductCollection_private("5102", errorMessage);
		}

		return;
	}
	else{
	
		var xmlLinesArr = xmlhttpresponseText.split("\n");
		var productCounter = -1;
		
		for(var lineNo = 0; lineNo < xmlLinesArr.length; lineNo++)
		{
			if(xmlLinesArr[lineNo].toString().match(/\<product\>/))
				productCounter++;

			else if(xmlLinesArr[lineNo].toString().match(/\<product_id\>/))
				this.productIdsArr[productCounter] = getXMLdata(xmlLinesArr[lineNo]);
			
			else if(xmlLinesArr[lineNo].toString().match(/\<product_title\>/))
				this.productTitlesArr[productCounter] = getXMLdata(xmlLinesArr[lineNo]);

			else if(xmlLinesArr[lineNo].toString().match(/\<product_title_ext\>/))
				this.productTitleExtArr[productCounter] = getXMLdata(xmlLinesArr[lineNo]);
		}
	
		// Fire all all of the "completed" events.
		for(var i=0; i < this.prodCollectResponseEventsFunctionsArr.length; i++){
			this.prodCollectResponseEventsFunctionsArr[i].call(this.prodCollectResponseEventsObjectsArr[i]);
		}
		

	}
	

}


ProductCollection.prototype.errorEventProductCollection_private = function(responseCode, responseError)
{

	// See if the user has subscribed to the critical error event.
	for(var i=0; i < this.prodCollectCriticalErrorEventsFunctionsArr.length; i++){
		this.prodCollectCriticalErrorEventsFunctionsArr[i].call(this.prodCollectCriticalErrorEventsObjectsArr[i], responseCode, responseError);
	}

}





















function SignIn(){
	
	this.loginName = "";
	this.loginPassword = "";
	this.formSecCode = "";
	this.rememberPasswordString = "no";
	
	this.signInResponseEventsFunctionsArr = new Array();
	this.signInResponseEventsObjectsArr = new Array();
	
	this.signInCriticalErrorEventsFunctionsArr = new Array();
	this.signInCriticalErrorEventsObjectsArr = new Array();
}

// The event that you attach should accept 2 parameters... eventFunction(flagYesNo, ifNoThenErrorMessage)
// If it returns TRUE there will be no error description... otherwise it will give a reason why the login didn't sucdeed.
SignIn.prototype.attachSignInResponseEvents = function(functionRef, objectRef)
{
	this.signInResponseEventsFunctionsArr.push(functionRef);
	this.signInResponseEventsObjectsArr.push(objectRef);
}

SignIn.prototype.attachSignInCriticalErrorEvents = function(functionRef, objectRef)
{
	this.signInCriticalErrorEventsFunctionsArr.push(functionRef);
	this.signInCriticalErrorEventsObjectsArr.push(objectRef);
}


// Set the UserName and Password before calling this method.
SignIn.prototype.setLogin = function(userName, passwd, formSecurityCode, rememberPasswordFlag)
{

	this.loginName = userName;
	this.loginPassword = passwd;
	this.formSecCode = formSecurityCode;
	
	if(rememberPasswordFlag)
		this.rememberPasswordString = "yes";
	else
		this.rememberPasswordString = "no";
}


// Set the UserName and Password before calling this method.
SignIn.prototype.fireRequest = function()
{

	// So we can scope the callback.
	var instance = this;
	
	var dateObj = new Date();
	var apiURL = "./signin_xml.php";
	var apiParameters = "api_version=1.1&email="+ this.loginName +"&pw=" + this.loginPassword + "&form_sc=" + this.formSecCode + "&nocache=" + dateObj.getTime() + "&rememberpassword=" + this.rememberPasswordString;
	
	// Get a free/new connection object by reference. Abort method call if an HTTP object cant be created. The method getXmlHttpObj will alert an error message too.
	var xmlHttpObjFromGlobalQueue = getXmlHttpObj("SignInXML");
	if(xmlHttpObjFromGlobalQueue == null)
		return;
	
	xmlHttpObjFromGlobalQueue.open('GET', (apiURL + "?" + apiParameters), true);


	// Micosoft does not like you setting the Ready State before calling "open".
	xmlHttpObjFromGlobalQueue.onreadystatechange = function() 
	{

		// Now the Callback happened from the XMLHttpRequest communication
		// We have to find out which one was used from our Global Array of connection pools.
		// There is no way to guarantee the order in which these are processed.
		// We are basically looking for the first connection object which is Not Free... Having a matching CallBack function "identifier string".
		for(var t=0; t<xmlReqsObjArr.length; t++){
	
			if(xmlReqsObjArr[t].reqObjFree || xmlReqsObjArr[t].reqObjParsing || xmlReqsObjArr[t].xmlHttpObj.readyState != 4 || xmlReqsObjArr[t].callBackFunctionName != "SignInXML")
				continue;
			if(xmlReqsObjArr[t].xmlHttpObj.status == "200"){
				xmlReqsObjArr[t].reqObjParsing = true;
				instance.parseXMLresponse(xmlReqsObjArr[t].xmlHttpObj.responseText);
			}
			else{
				instance.errorEventSignIn_private(xmlReqsObjArr[t].xmlHttpObj.status, xmlReqsObjArr[t].xmlHttpObj.statusText);
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

// If you make any changes to the quantity or the options/choices, call this method to save to the database.
// Make sure to define an event handler to listen for the response from the server.
SignIn.prototype.parseXMLresponse = function(xmlhttpresponseText)
{

	// Find out if the document was not what we expected to receive.
	// If Not, try to get an Error message and send that through the error event.
	if(!xmlhttpresponseText.match(/\<success\>good\<\/success\>/) && !xmlhttpresponseText.match(/\<success\>bad\<\/success\>/))
	{
		this.errorEventSignIn_private("5401", "No response from server.");

		return;
	}
	else{
	
		// The response of updating project options should also let us know if we need to update the thumbnail image.
		// Sometimes changing project options can have an affect on the artwork preview, such as Windows on an Envelope preview.
		if(xmlhttpresponseText.match(/\<success\>good\<\/success\>/))
			var userIsLoggedInFlag = true;
		else
			var userIsLoggedInFlag = false;
			
		
		var errorDescription = "";
		
		if(!userIsLoggedInFlag){
			
			var matchArr = xmlhttpresponseText.match(/\<description\>([^\<]+)\<\/description\>/);
			if(matchArr != null && matchArr.length == 2)
				errorDescription = matchArr[1];
		}
	
		// See if the user has subscribed to the sign-in event.
		// If it returns TRUE there will be no error description... otherwise it will give a reason why the login didn't sucdeed.
		for(var i=0; i < this.signInResponseEventsFunctionsArr.length; i++){
			this.signInResponseEventsFunctionsArr[i].call(this.signInResponseEventsObjectsArr[i], userIsLoggedInFlag, errorDescription);
		}
		

	}
	

}


SignIn.prototype.errorEventSignIn_private = function(responseCode, responseError)
{

	// See if the user has subscribed to the critical error event.
	for(var i=0; i < this.signInCriticalErrorEventsFunctionsArr.length; i++){
		this.signInCriticalErrorEventsFunctionsArr[i].call(this.signInCriticalErrorEventsObjectsArr[i], responseCode, responseError);
	}


}

















function ProjectArtwork(){
	
	// After the API returns, this will contain a 3D array of all color definitions and custom color palletes for each side. 
	this.artworkSidesArr = new Array();
	this.currentSide_private = -1;
	this.lastColorNodeType = "";
	
	this.projectArtworkResponseEventsFunctionsArr = new Array();
	this.projectArtworkResponseEventsObjectsArr = new Array();
	
	this.projectArtworkCriticalErrorEventsFunctionsArr = new Array();
	this.projectArtworkCriticalErrorEventsObjectsArr = new Array();
}

// The event that you attach does not take parameters)
ProjectArtwork.prototype.attachProjectArtworkDownloadedEvent = function(functionRef, objectRef)
{
	this.projectArtworkResponseEventsFunctionsArr.push(functionRef);
	this.projectArtworkResponseEventsObjectsArr.push(objectRef);
}

ProjectArtwork.prototype.attachProjectArtworkCriticalErrorEvent = function(functionRef, objectRef)
{
	this.projectArtworkCriticalErrorEventsFunctionsArr.push(functionRef);
	this.projectArtworkCriticalErrorEventsObjectsArr.push(objectRef);
}



// Set the UserName and Password before calling this method.
ProjectArtwork.prototype.downloadArtwork = function(projectID, projectView)
{

	// So we can scope the callback.
	var instance = this;
	
	var dateObj = new Date();
	var apiURL = "./api_dot.php";
	var apiParameters = "api_version=1.1&command=get_project_artwork&project_number=" + projectID + "&view_type=" +  projectView + "&nocache=" + dateObj.getTime();
	
	// Get a free/new connection object by reference. Abort method call if an HTTP object cant be created. The method getXmlHttpObj will alert an error message too.
	var xmlHttpObjFromGlobalQueue = getXmlHttpObj("ProjectArtworkXML");
	if(xmlHttpObjFromGlobalQueue == null)
		return;
	
	xmlHttpObjFromGlobalQueue.open('GET', (apiURL + "?" + apiParameters), true);


	// Micosoft does not like you setting the Ready State before calling "open".
	xmlHttpObjFromGlobalQueue.onreadystatechange = function() 
	{

		// Now the Callback happened from the XMLHttpRequest communication
		// We have to find out which one was used from our Global Array of connection pools.
		// There is no way to guarantee the order in which these are processed.
		// We are basically looking for the first connection object which is Not Free... Having a matching CallBack function "identifier string".
		for(var t=0; t<xmlReqsObjArr.length; t++){
	
			if(xmlReqsObjArr[t].reqObjFree || xmlReqsObjArr[t].reqObjParsing || xmlReqsObjArr[t].xmlHttpObj.readyState != 4 || xmlReqsObjArr[t].callBackFunctionName != "ProjectArtworkXML")
				continue;
			if(xmlReqsObjArr[t].xmlHttpObj.status == "200"){
				xmlReqsObjArr[t].reqObjParsing = true;
				instance.parseXMLresponse(xmlReqsObjArr[t].xmlHttpObj.responseText);
			}
			else{
				instance.errorEventProjectArtwork_private(xmlReqsObjArr[t].xmlHttpObj.status, xmlReqsObjArr[t].xmlHttpObj.statusText);
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

// If you make any changes to the quantity or the options/choices, call this method to save to the database.
// Make sure to define an event handler to listen for the response from the server.
ProjectArtwork.prototype.parseXMLresponse = function(xmlhttpresponseText)
{

	
	// Find out if the document was not what we expected to receive.
	// If Not, try to get an Error message and send that through the error event.
	if(!xmlhttpresponseText.match(/\<result\>OK\<\/result\>/))
	{
		// Look for an error message in the response, since we didn't get an "OK"
		var errorMessage = getErrorMessageFromAPIresponseXML(xmlhttpresponseText);
		
		if(errorMessage == ""){
			this.errorEventProjectArtwork_private("5121", "Unknown Error Parsing XML Document");
		}
		else{
			this.errorEventProjectArtwork_private("5122", errorMessage);
		}

		return;
	}

	var xmlLinesArr = xmlhttpresponseText.split("\n");

	for(var lineNo = 0; lineNo < xmlLinesArr.length; lineNo++)
	{
		if(xmlLinesArr[lineNo].toString().match(/\<side\>/)){
			this.currentSide_private++;
			this.artworkSidesArr[this.currentSide_private] = new ArtworkSide();
		}
		else if(xmlLinesArr[lineNo].toString().match(/\<description\>/)){
			this.artworkSidesArr[this.currentSide_private].sideDescription = getXMLdata(xmlLinesArr[lineNo]);
		}
		else if(xmlLinesArr[lineNo].toString().match(/\<initialzoom\>/)){
			this.artworkSidesArr[this.currentSide_private].intialZoom = getXMLdata(xmlLinesArr[lineNo]);
		}
		else if(xmlLinesArr[lineNo].toString().match(/\<rotatecanvas\>/)){
			this.artworkSidesArr[this.currentSide_private].rotateCanvas = getXMLdata(xmlLinesArr[lineNo]);
		}
		else if(xmlLinesArr[lineNo].toString().match(/\<contentwidth\>/)){
			this.artworkSidesArr[this.currentSide_private].contentWidth = getXMLdata(xmlLinesArr[lineNo]);
		}
		else if(xmlLinesArr[lineNo].toString().match(/\<contentheight\>/)){
			this.artworkSidesArr[this.currentSide_private].contentHeight = getXMLdata(xmlLinesArr[lineNo]);
		}
		else if(xmlLinesArr[lineNo].toString().match(/\<backgroundimage\>/)){
			this.artworkSidesArr[this.currentSide_private].backgroundImage = getXMLdata(xmlLinesArr[lineNo]);
		}
		else if(xmlLinesArr[lineNo].toString().match(/\<background_x\>/)){
			this.artworkSidesArr[this.currentSide_private].backgroundX = getXMLdata(xmlLinesArr[lineNo]);
		}
		else if(xmlLinesArr[lineNo].toString().match(/\<background_y\>/)){
			this.artworkSidesArr[this.currentSide_private].backgroundY = getXMLdata(xmlLinesArr[lineNo]);
		}
		else if(xmlLinesArr[lineNo].toString().match(/\<background_width\>/)){
			this.artworkSidesArr[this.currentSide_private].backgroundWidth = getXMLdata(xmlLinesArr[lineNo]);
		}
		else if(xmlLinesArr[lineNo].toString().match(/\<background_height\>/)){
			this.artworkSidesArr[this.currentSide_private].backgroundHeight = getXMLdata(xmlLinesArr[lineNo]);
		}
		else if(xmlLinesArr[lineNo].toString().match(/\<background_color\>/)){
			this.artworkSidesArr[this.currentSide_private].backgroundColor = getXMLdata(xmlLinesArr[lineNo]);
		}
		else if(xmlLinesArr[lineNo].toString().match(/\<folds_horiz\>/)){
			this.artworkSidesArr[this.currentSide_private].backgroundFoldsH = getXMLdata(xmlLinesArr[lineNo]);
		}
		else if(xmlLinesArr[lineNo].toString().match(/\<folds_vert\>/)){
			this.artworkSidesArr[this.currentSide_private].backgroundFoldsV = getXMLdata(xmlLinesArr[lineNo]);
		}
		else if(xmlLinesArr[lineNo].toString().match(/\<scale\>/)){
			this.artworkSidesArr[this.currentSide_private].scale = getXMLdata(xmlLinesArr[lineNo]);
		}
		else if(xmlLinesArr[lineNo].toString().match(/\<dpi\>/)){
			this.artworkSidesArr[this.currentSide_private].dpi = getXMLdata(xmlLinesArr[lineNo]);
		}
		else if(xmlLinesArr[lineNo].toString().match(/\<color_palette\>/)){
			this.lastColorNodeType = "color_palette";
		}
		else if(xmlLinesArr[lineNo].toString().match(/\<color_definitions\>/)){
			this.lastColorNodeType = "color_definitions";
		}
		else if(xmlLinesArr[lineNo].toString().match(/\<layer\>/)){
			this.lastColorNodeType = "layer";
		}
		else if(xmlLinesArr[lineNo].toString().match(/\<color/)){
	
			if(this.lastColorNodeType == "color_palette"){
				
				this.artworkSidesArr[this.currentSide_private].colorPaletteEntryCounter++;
				var colorPalletteCounter = this.artworkSidesArr[this.currentSide_private].colorPaletteEntryCounter;

				this.artworkSidesArr[this.currentSide_private].colorPaletteEntries[colorPalletteCounter] = new ArtworkColorPalette();
				this.artworkSidesArr[this.currentSide_private].colorPaletteEntries[colorPalletteCounter].colorDescription = getXMLdata(xmlLinesArr[lineNo]);
				this.artworkSidesArr[this.currentSide_private].colorPaletteEntries[colorPalletteCounter].colorCode = getXMLattribute(xmlLinesArr[lineNo], "colorcode");
			}
			else if(this.lastColorNodeType == "color_definitions"){
				
				this.artworkSidesArr[this.currentSide_private].colorDefinitionCounter++;
				var colorDefCounter = this.artworkSidesArr[this.currentSide_private].colorDefinitionCounter;
				
				this.artworkSidesArr[this.currentSide_private].colorDefinitions[colorDefCounter] = new ArtworkColorDefinition();
				this.artworkSidesArr[this.currentSide_private].colorDefinitions[colorDefCounter].colorCode = getXMLdata(xmlLinesArr[lineNo]);
				this.artworkSidesArr[this.currentSide_private].colorDefinitions[colorDefCounter].colorID = getXMLattribute(xmlLinesArr[lineNo], "id");
			}

			
		}
	}
			

	
	
			

	// See if the user has subscribed to the response event.
	// If it returns TRUE there will be no error description... otherwise it will give a reason why the API request didn't sucdeed.
	for(var i=0; i < this.projectArtworkResponseEventsFunctionsArr.length; i++){
		this.projectArtworkResponseEventsFunctionsArr[i].call(this.projectArtworkResponseEventsObjectsArr[i]);
	}
	

	

}


ProjectArtwork.prototype.errorEventProjectArtwork_private = function(responseCode, responseError)
{
	// See if the user has subscribed to the critical error event.
	for(var i=0; i < this.projectArtworkCriticalErrorEventsFunctionsArr.length; i++){
		this.projectArtworkCriticalErrorEventsFunctionsArr[i].call(this.projectArtworkCriticalErrorEventsObjectsArr[i], responseCode, responseError);
	}

}









/* This is the object which contains parameters for the artwork side.  Every artwork has 1 or more sides */
function ArtworkSide(){
	
	this.sideDescription = "";
	this.intialZoom = "";
	this.rotateCanvas = "";
	this.contentWidth = "";
	this.contentHeight = "";
	this.backgroundImage = "";
	this.backgroundX = "";
	this.backgroundY = "";
	this.backgroundWidth = "";
	this.backgroundHeight = "";
	this.backgroundColor = "";
	this.backgroundFoldsH = "";
	this.backgroundFoldsV = "";
	this.scale = "";
	this.dpi = "";
	
	this.colorPaletteEntryCounter = -1;
	this.colorDefinitionCounter = -1;
	
	this.colorDefinitions = new Array();
	this.colorPaletteEntries = new Array();
	
}

// If this artwork side has Color Definitions... then pass in a color definition ID and this method will return the description of that color.
// If there is a custom color pallette defined, then this method will return the human specified "color description".
// Otherwise, it will return the color code (in integer form)
// If the Color ID does not exist, then it will return NULL.
// If the color ID refers to a palette entry which does not exist, then it will also return NULL.
ArtworkSide.prototype.getColorDescriptionFromColorId = function(colorID)
{
	for(var i=0; i < this.colorDefinitions.length; i++){
		
		if(this.colorDefinitions[i].colorID == colorID){
			
			// If there are no color palette entries, then the ID is a intval color code (not a hex color code).
			if(this.colorPaletteEntries.length == 0)
				return this.colorDefinitions[i].colorCode;
			
			for(var j=0; j < this.colorPaletteEntries.length; j++){
			
				if(this.colorDefinitions[i].colorCode == this.colorPaletteEntries[j].colorCode){
					return this.colorPaletteEntries[j].colorDescription;
				}	
			}
		}
	}
	
	return null;
}

// Returns an array of color descriptions that have been selected on the side.
// If a "custom color palette" has been defined, then the values will be human specified descriptions
// Otherwise, it will be an array of intval color codes that have been selected.
// If this Artwork Side does not have a limited number of color definitions... then it will return an empty array.
ArtworkSide.prototype.getAllSelectedColorDefinitions = function()
{
	var returnArr = new Array();
	
	for(var i=0; i < this.colorDefinitions.length; i++){
		returnArr.push(this.getColorDescriptionFromColorId(this.colorDefinitions[i].colorID));
	}
	return returnArr;
	
}


function ArtworkColorDefinition(){
	this.colorID = "";
	this.colorCode = "";
}
function ArtworkColorPalette(){
	this.colorDescription = "";
	this.colorCode = "";
}

















// Returns a list of Project ID's in the Shopping cart.  If you want to Add/Remove projects from the shopping cart... use the Project Objects instead.
function ShoppingCart(){
	
	this.projectIdsArr = new Array();
	this.productIdsArr = new Array();
	this.quantitiesArr = new Array();
	this.savedProjectLinksArr = new Array();
	
	this.shopCartResponseEventsFunctionsArr = new Array();
	this.shopCartResponseEventsObjectsArr = new Array();
	
	this.shopCartCriticalErrorEventsFunctionsArr = new Array();
	this.shopCartCriticalErrorEventsObjectsArr = new Array();
}

ShoppingCart.prototype.attachShoppingCartResponseEvents = function(functionRef, objectRef)
{
	this.shopCartResponseEventsFunctionsArr.push(functionRef);
	this.shopCartResponseEventsObjectsArr.push(objectRef);
}

ShoppingCart.prototype.attachShoppingCartCriticalErrorEvents = function(functionRef, objectRef)
{
	this.shopCartCriticalErrorEventsFunctionsArr.push(functionRef);
	this.shopCartCriticalErrorEventsObjectsArr.push(objectRef);
}


// Returns a list of Project Session ID's
// This must be called after the API communication has finished.
ShoppingCart.prototype.getProjectSessionIdsArr = function()
{
	return this.projectIdsArr;
}

// Returns a unique list of Products in the user's shoppingcart
ShoppingCart.prototype.getProductIdsInCartArr = function()
{
	var returnArr = new Array();
	
	for(var i=0; i<this.productIdsArr.length; i++){
		var found = false;
		for(j=0; j<returnArr.length; j++){
			if(returnArr[j] == this.productIdsArr[i]){
				found = true;
			}
		}
		if(!found){
			returnArr.push(this.productIdsArr[i])
		}
	}
	return returnArr;
}

ShoppingCart.prototype.getProductId = function(projectSessionId)
{
	for(var i=0; i<this.projectIdsArr.length; i++){
		if(this.projectIdsArr[i] == projectSessionId)
			return this.productIdsArr[i];
		
	}
	alert("Project ID not found getProductId");
}
ShoppingCart.prototype.getQuantity = function(projectSessionId)
{
	for(var i=0; i<this.projectIdsArr.length; i++){
		if(this.projectIdsArr[i] == projectSessionId)
			return this.quantitiesArr[i];
		
	}
	alert("Project ID not found getQuantity");
}
ShoppingCart.prototype.getSavedProjectLink = function(projectSessionId)
{
	for(var i=0; i<this.projectIdsArr.length; i++){
		if(this.projectIdsArr[i] == projectSessionId)
			return this.savedProjectLinksArr[i];
		
	}
	alert("Project ID not found getSavedProjectLink");
}




// Set the UserName and Password before calling this method.
ShoppingCart.prototype.fetchList = function()
{

	// Every time we contact the server, reset the project session ID's
	// These are parellel arrays
	this.projectIdsArr = new Array();
	this.productIdsArr = new Array();
	this.quantitiesArr = new Array();
	this.savedProjectLinksArr = new Array();


	// So we can scope the callback.
	var instance = this;

	// This doesn't work if you go over 60 mintues.
	var numberOfMinutesToCacheResults = 15;
	var dateObj = new Date();

	var apiURL = "./api_dot.php";
	var apiParameters = "api_version=1.1&command=get_projects_in_shoppingcart&nocache=" + dateObj.getTime();
	
	// Get a free/new connection object by reference. Abort method call if an HTTP object cant be created. The method getXmlHttpObj will alert an error message too.
	var xmlHttpObjFromGlobalQueue = getXmlHttpObj("ShoppingCartXML");
	if(xmlHttpObjFromGlobalQueue == null)
		return;
	
	xmlHttpObjFromGlobalQueue.open('GET', (apiURL + "?" + apiParameters), true);


	// Micosoft does not like you setting the Ready State before calling "open".
	xmlHttpObjFromGlobalQueue.onreadystatechange = function() 
	{

		// Now the Callback happened from the XMLHttpRequest communication
		// We have to find out which one was used from our Global Array of connection pools.
		// There is no way to guarantee the order in which these are processed.
		// We are basically looking for the first connection object which is Not Free... Having a matching CallBack function "identifier string".
		for(var t=0; t<xmlReqsObjArr.length; t++){
	
			if(xmlReqsObjArr[t].reqObjFree || xmlReqsObjArr[t].reqObjParsing || xmlReqsObjArr[t].xmlHttpObj.readyState != 4 || xmlReqsObjArr[t].callBackFunctionName != "ShoppingCartXML")
				continue;
			if(xmlReqsObjArr[t].xmlHttpObj.status == "200"){
				xmlReqsObjArr[t].reqObjParsing = true;
				instance.parseXMLresponse(xmlReqsObjArr[t].xmlHttpObj.responseText);
			}
			else{
				instance.errorEventShoppingCart_private(xmlReqsObjArr[t].xmlHttpObj.status, xmlReqsObjArr[t].xmlHttpObj.statusText);
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

// If you make any changes to the quantity or the options/choices, call this method to save to the database.
// Make sure to define an event handler to listen for the response from the server.
ShoppingCart.prototype.parseXMLresponse = function(xmlhttpresponseText)
{

	// Find out if the document was not what we expected to receive.
	// If Not, try to get an Error message and send that through the error event.
	if(!xmlhttpresponseText.match(/\<result\>OK\<\/result\>/))
	{
		// Look for an error message in the response, since we didn't get an "OK"
		var errorMessage = getErrorMessageFromAPIresponseXML(xmlhttpresponseText);
		
		if(errorMessage == ""){
			this.errorEventShoppingCart_private("5161", "Unknown Error Parsing XML Document");
		}
		else{
			this.errorEventShoppingCart_private("5162", errorMessage);
		}

		return;
	}
	else{
	
		var xmlLinesArr = xmlhttpresponseText.split("\n");

		var projectCounter = -1;
		
		for(var lineNo = 0; lineNo < xmlLinesArr.length; lineNo++)
		{
			if(xmlLinesArr[lineNo].toString().match(/\<project\>/))
				projectCounter++;	
			else if(xmlLinesArr[lineNo].toString().match(/\<project_id\>/))
				this.projectIdsArr[projectCounter] = getXMLdata(xmlLinesArr[lineNo]);	
			else if(xmlLinesArr[lineNo].toString().match(/\<product_id\>/))
				this.productIdsArr[projectCounter] = getXMLdata(xmlLinesArr[lineNo]);
			else if(xmlLinesArr[lineNo].toString().match(/\<quantity\>/))
				this.quantitiesArr[projectCounter] = getXMLdata(xmlLinesArr[lineNo]);	
			else if(xmlLinesArr[lineNo].toString().match(/\<saved_project_link_id\>/))
				this.savedProjectLinksArr[projectCounter] = getXMLdata(xmlLinesArr[lineNo]);	
		}
	
		// Fire all all of the "completed" events.
		for(var i=0; i < this.shopCartResponseEventsFunctionsArr.length; i++){
			this.shopCartResponseEventsFunctionsArr[i].call(this.shopCartResponseEventsObjectsArr[i]);
		}
		

	}
	

}


ShoppingCart.prototype.errorEventShoppingCart_private = function(responseCode, responseError)
{

	// See if the user has subscribed to the critical error event.
	for(var i=0; i < this.shopCartCriticalErrorEventsFunctionsArr.length; i++){
		this.shopCartCriticalErrorEventsFunctionsArr[i].call(this.shopCartCriticalErrorEventsObjectsArr[i], responseCode, responseError);
	}

}































//----------------   Functions ---------------------------



function unEscapeHTMLcharsInString(str) 
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




// Because we are dealing with 1 tag per line it becomes easy to parse XML documents.
// Just pass in the tag (opening and closing) and it will get the data within it... and convert any HTML special characters.
// For example... pass in...   <names>Brian &amp; Jakey</names> ... .and it will return  "Brian & Jakey".
function getXMLdata(xmltag)
{

	// Start Tag and White Space
	xmltag = xmltag.replace(/(\s|\t|\r|\n)*\<\w+(\s+\w+=\s*(\"|\')(\w|\s|\d)*(\"|\'))*\s*\>/, "");
	
	// End Tag and White Space
	xmltag = xmltag.replace(/\<\/\w+\>(\s|\t|\r|\n)*/, "");
	
	return unEscapeHTMLcharsInString(xmltag);

}



// Because we are dealing with 1 tag per line it becomes easy to parse XML documents.
// Just pass in the tag (opening and closing) and it will get an attibute out of the <openTag> .. and convert any HTML special characters.
// For example... pass in...   <name dad='Brian' son='Jake'>XML Data/names>  with a second parameter of "dad" and this function will return "Brian"
// If the attibute does not exist, this function will return NULL.
function getXMLattribute(xmltag, attributeName)
{

	var regexPattern = encodeRE(attributeName) + "\\s*=\\s*(\"|\')((\\w|\\s|\\d)*)(\"|\').*\\>.*<\\/\\w+\\>";
	var re = new RegExp(regexPattern,"i"); 
	
	matchesArr = xmltag.match(regexPattern);
	
	if(matchesArr == null){
		return null;
	}

	return unEscapeHTMLcharsInString(matchesArr[2]);
 }





// All calls to our API follow have a similar format for response OK, and error message.
// This will look for an error message in the response, otherwise it will return a bank string.
function getErrorMessageFromAPIresponseXML(responseXMLtext)
{

	var errorMessage = "";

	var xmlLinesArr = responseXMLtext.split("\n");

	for(var lineNo = 0; lineNo < xmlLinesArr.length; lineNo++)
	{
		if(xmlLinesArr[lineNo].toString().match(/\<error_message\>/))
		{	
			errorMessage = getXMLdata(xmlLinesArr[lineNo]);
			break;
		}	
	}
	
	return errorMessage;
}



// Pass in a Quantity to get a Price Modification for.
// Expects to get a string in the Format like "20^0.10|100^0.20|500^0.30";
// Price Groups separated by Pipe Symbols... and the quantity Amount is separated from the Price change with carrot.
// Vendor Number is an Optional Parameter. Default is not to search for a vendor within the Price modification.
// ... If you want the Price modification of a certain vendor number, pass in a number greater than or equal to 1.
function getLastPriceModification(quantityToCheck, quantityBreaksStr, vendorNumber)
{
	if(vendorNumber != null)
	{
		vendorNumber = parseInt(vendorNumber);
		if(vendorNumber < 1)
		{
			alert("Problem in function getLastPriceModification. The vendor number has to be greater or equal to 1.");
			return;	
		}
	}
		
	quantityToCheck = parseInt(quantityToCheck);
	
	if(quantityBreaksStr == "" || quantityToCheck==0)
		return 0;
	
	var breaksArr = new Array();
	breaksArr = quantityBreaksStr.split("|");
		
	var lastMatchedAmount = 0;
	for(var breakCounter=0; breakCounter<breaksArr.length; breakCounter++)
	{
	
		var quanPriceArr = new Array();
		quanPriceArr = breaksArr[breakCounter].split("^");
				
		if(quanPriceArr.length != 2)
		{
			alert("Problem in function getLastPriceModification for the string: " + quantityBreaks);
			return;
		}
		
		var quanChk = parseInt(quanPriceArr[0]);
		
		// If we want a Vendor Price modification, then the string is futher split by @ symbols.
		if(vendorNumber == null)
		{
			var quanPrcChng = parseFloat(quanPriceArr[1]);
		}
		else{
			var vendorPriceStringArr = quanPriceArr[1].split("@");
			if(vendorNumber > vendorPriceStringArr.length)
			{
				alert("Problem in function getLastPriceModification Vendor #" + vendorNumber + " does not have a definition within Quantity Break definition: " + quantityBreaks);
				return;
			}
			
			var quanPrcChng = parseFloat(vendorPriceStringArr[(vendorNumber -1)]);
		}
		
		

		// Only use the last match.
		if(quanChk <= quantityToCheck)
			lastMatchedAmount = quanPrcChng;
	}
		
		
	return lastMatchedAmount;
}





function RoundWithTwoDecimals(n) {

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


