var xmlReqsObjArr = new Array(); function httpReqObj(callBckFuncName)
{ this.reqObjFree = false; this.reqObjParsing = false; this.xmlHttpObj = false; this.callBackFunctionName = callBckFuncName; if(window.XMLHttpRequest)
{ try { this.xmlHttpObj = new XMLHttpRequest();}
catch(e)
{ this.xmlHttpObj = null;}
}
else if(window.ActiveXObject)
{ try { this.xmlHttpObj = new ActiveXObject('Msxml2.XMLHTTP.3.0');}
catch(e)
{ try { this.xmlHttpObj = new ActiveXObject('Msxml2.XMLHTTP');}
catch(e)
{ try { this.xmlHttpObj = new ActiveXObject('Microsoft.XMLHTTP');}
catch(e)
{ this.xmlHttpObj = null;}
}
}
}
}
function getXmlHttpObj(callBckFuncName)
{ for(var k=0; k<xmlReqsObjArr.length; k++)
{ if(xmlReqsObjArr[k].reqObjFree){ xmlReqsObjArr[k].reqObjFree = false; xmlReqsObjArr[k].callBackFunctionName = callBckFuncName; return xmlReqsObjArr[k].xmlHttpObj;}
}
var newIndex = xmlReqsObjArr.length; xmlReqsObjArr[newIndex] = new httpReqObj(callBckFuncName); if(xmlReqsObjArr[newIndex].xmlHttpObj == null)
alert("Can not create an XML HTTP connection. Your browser may be incompatible."); return xmlReqsObjArr[newIndex].xmlHttpObj;}
function ProjectLoader()
{ this.cachedProjectObjectsArr = new Array(); this.cachedProjectObjectsPointerArr = new Array(); this.productLoader = new ProductLoader(); this.onLoadingCompleteEventsFunctionRefArr = new Array(); this.onLoadingCompleteEventsObjectRefArr = new Array(); this.onProjectRefreshCompleteEventsFunctionRefArr = new Array(); this.onProjectRefreshCompleteEventsObjectRefArr = new Array(); this.onLoaderErrorEventsFunctionRefArr = new Array(); this.onLoaderErrorEventsObjectRefArr = new Array(); this.projectRefreshLoader = null; this.projectIsBeingRefreshed = false; this.projectLoaderWasUsed = false; this.productLoader.attatchProductLoadingErrorEvent(this.catchProductLoadingError, this); this.productLoader.attachProductLoadedGlobalEvent(this.catchProductLoadedEvent, this)
}
ProjectLoader.prototype.onProjectLoaderError_Private = function(statusCode, statusDesc)
{ for(var i=0; i<this.onLoaderErrorEventsFunctionRefArr.length; i++){ this.onLoaderErrorEventsFunctionRefArr[i].call(this.onLoaderErrorEventsObjectRefArr[i], statusCode, "Error Loading Project Object: " + statusDesc);}
}
ProjectLoader.prototype.catchProductLoadingError = function(statusCode, statusDesc)
{ this.onProjectLoaderError_Private(statusCode, "Product Loading Error: " + statusDesc);}
ProjectLoader.prototype.catchProductLoadedEvent = function(productID)
{ this.initProductObjsAndMaybeFireCompleteEvent_Private();}
ProjectLoader.prototype.checkIfProjectLoaded = function(projectID)
{ if(this.getIndexOfProjectCacheArr(projectID) < 0)
return false; else
return true;}
ProjectLoader.prototype.getIndexOfProjectCacheArr = function(projectID)
{ for(var i=0; i<this.cachedProjectObjectsPointerArr.length; i++){ if(this.cachedProjectObjectsPointerArr[i] == projectID)
return i;}
return -1;}
ProjectLoader.prototype.getProjectObj = function(projectID)
{ if(!this.checkIfProjectLoaded(projectID))
{ alert("Error in method getProjectObj. You can't call this method until the Object has finished loading."); return false;}
return this.cachedProjectObjectsArr[this.getIndexOfProjectCacheArr(projectID)];}
ProjectLoader.prototype.attachProjectsLoadedEvent = function(functionReference, objectReference)
{ this.onLoadingCompleteEventsFunctionRefArr.push(functionReference); this.onLoadingCompleteEventsObjectRefArr.push(objectReference);}
ProjectLoader.prototype.attachProjectRefreshedEvent = function(functionReference, objectReference)
{ this.onProjectRefreshCompleteEventsFunctionRefArr.push(functionReference); this.onProjectRefreshCompleteEventsObjectRefArr.push(objectReference);}
ProjectLoader.prototype.attachLoaderErrorEvent = function(functionReference, objectReference)
{ this.onLoaderErrorEventsFunctionRefArr.push(functionReference); this.onLoaderErrorEventsObjectRefArr.push(objectReference);}
ProjectLoader.prototype.projectRefreshFinished = function()
{ this.projectIsBeingRefreshed = false; var projectIDsArr = this.projectRefreshLoader.getProjectIDsLoaded(); var projectIdToRefresh = projectIDsArr[0]; var refreshedProjectObj = this.projectRefreshLoader.getProjectObj(projectIdToRefresh); this.cachedProjectObjectsArr[this.getIndexOfProjectCacheArr(projectIdToRefresh)] = refreshedProjectObj; for(var i=0; i<this.onProjectRefreshCompleteEventsFunctionRefArr.length; i++)
{ this.onProjectRefreshCompleteEventsFunctionRefArr[i].call(this.onProjectRefreshCompleteEventsObjectRefArr[i], projectIdToRefresh);}
}
ProjectLoader.prototype.refreshProject = function(projectID)
{ if(this.projectIsBeingRefreshed)
{ alert("Multiple Project Refreshes are not permitted"); return;}
this.projectIsBeingRefreshed = true; this.projectRefreshLoader = new ProjectLoader(); if(this.getIndexOfProjectCacheArr(projectID) < 0)
alert("Error: The Project ID can not be refreshed because it hasn't been loaded yet: " + projectID); var projectObjToRefresh = this.getProjectObj(projectID); this.projectRefreshLoader.attachProjectsLoadedEvent(this.projectRefreshFinished, this); var singleProjectArr = new Array(); singleProjectArr.push(projectID); this.projectRefreshLoader.loadProjects(singleProjectArr, projectObjToRefresh.getProjectView());}
ProjectLoader.prototype.loadProductObjects = function()
{ for(var i = 0; i< this.cachedProjectObjectsArr.length; i++)
{ this.productLoader.loadProductID(this.cachedProjectObjectsArr[i].productID);}
}
ProjectLoader.prototype.initProductObjsAndMaybeFireCompleteEvent_Private = function()
{ var allProductInitialized = true; for(var i = 0; i < this.cachedProjectObjectsArr.length; i++)
{ var productIDofProject = this.cachedProjectObjectsArr[i].productID; if(this.productLoader.checkIfProductInitialized(productIDofProject))
{ if(this.cachedProjectObjectsArr[i].productObj == null)
this.cachedProjectObjectsArr[i].productObj = this.productLoader.getProductObj(productIDofProject);}
else
{ allProductInitialized = false;}
}
if(allProductInitialized)
{ for(var i=0; i<this.onLoadingCompleteEventsFunctionRefArr.length; i++)
{ this.onLoadingCompleteEventsFunctionRefArr[i].call(this.onLoadingCompleteEventsObjectRefArr[i]);}
}
}
ProjectLoader.prototype.loadProjects = function(projectArr, projectViewType)
{ if(typeof projectArr != 'object')
{ alert("Error in method ProjectLoader.loadProjects. The parameter passed to this method must be an array."); return;}
if(this.projectLoaderWasUsed){ alert("Project Loaders may only be used once, but you can create many loader objects. It is possible to use this object to refresh a Project which has already been loaded."); return;}
this.projectLoaderWasUsed = true; var projectListStr = ""; for(var i=0; i<projectArr.length; i++)
{ if(!projectArr[i].toString().match(/^\d+$/))
{ alert("Error in method ProjectLoader.loadProjects. One of the Array elements is not a number."); return;}
if(projectListStr != "")
projectListStr += "|"; projectListStr += projectArr[i];}
if(projectListStr == "")
{ alert("Error in method ProjectLoader.loadProjects. The Project List is empty."); return;}
var instance = this; var dateObj = new Date(); var apiURL = "./api_dot.php?api_version=1.1&command=get_project_list&project_id_list=" + projectListStr + "&view_type=" + projectViewType + "&nocache=" + dateObj.getTime(); var xmlHttpObjFromGlobalQueue = getXmlHttpObj("ProjectLoader"); if(xmlHttpObjFromGlobalQueue == null)
return; xmlHttpObjFromGlobalQueue.open('GET', apiURL, true); xmlHttpObjFromGlobalQueue.onreadystatechange = function()
{ for(var t=0; t<xmlReqsObjArr.length; t++){ if(xmlReqsObjArr[t].reqObjFree || xmlReqsObjArr[t].reqObjParsing || xmlReqsObjArr[t].xmlHttpObj.readyState != 4 || xmlReqsObjArr[t].callBackFunctionName != "ProjectLoader")
continue; if(xmlReqsObjArr[t].xmlHttpObj.status == "200"){ xmlReqsObjArr[t].reqObjParsing = true; instance.private_parseXMLresponse(xmlReqsObjArr[t].xmlHttpObj.responseText);}
else{ instance.onProjectLoaderError_Private(xmlReqsObjArr[t].xmlHttpObj.status, xmlReqsObjArr[t].xmlHttpObj.statusText);}
xmlReqsObjArr[t].reqObjFree = true; xmlReqsObjArr[t].reqObjParsing = false;}
}
xmlHttpObjFromGlobalQueue.send(null);}
ProjectLoader.prototype.getProjectIDsLoaded = function(){ return this.cachedProjectObjectsPointerArr;}
ProjectLoader.prototype.private_parseXMLresponse = function(xmlhttpresponseText)
{ if(!xmlhttpresponseText.match(/\<result\>OK\<\/result\>/))
{ var errorMessage = getErrorMessageFromAPIresponseXML(xmlhttpresponseText); if(errorMessage == "")
this.onProjectLoaderError_Private("5101", "Unknown Error Parsing XML Document"); else
this.onProjectLoaderError_Private("5102", errorMessage); return;}
var projectObj = null; var projectOrderedLinksIndex = null; var xmlLinesArr = xmlhttpresponseText.split("\n"); for(var lineNo = 0; lineNo < xmlLinesArr.length; lineNo++)
{ if(xmlLinesArr[lineNo].toString().match(/\<project\>/))
{ var projectObj = new Project(); var projectOrderedLinksIndex = -1; var projectSessionLinksIndex = -1; var templateProductLinksIndex = -1; var templateLinkIndex = -1;}
else if(xmlLinesArr[lineNo].toString().match(/\<form_security_code\>/))
projectObj.formSecurityCode = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<project_view\>/))
projectObj.projectView = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<project_id\>/))
projectObj.projectID = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<date_modified\>/))
projectObj.dateModifiedUnixTimestamp = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<thumbnail_sc\>/))
projectObj.thumbnailSC = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<thumbnail_cache\>/))
projectObj.thumbnailCache = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<template_id\>/))
projectObj.templateId = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<template_area\>/))
projectObj.templateArea = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<product_id\>/))
projectObj.productID = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<quantity\>/))
projectObj.selectedOptionsObj.setQuantity(getXMLdata(xmlLinesArr[lineNo])); else if(xmlLinesArr[lineNo].toString().match(/\<options\>/))
projectObj.selectedOptionsObj.parseOptionsDescription(getXMLdata(xmlLinesArr[lineNo])); else if(xmlLinesArr[lineNo].toString().match(/\<variable_data_status\>/))
projectObj.variableDataStatus = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<variable_data_message\>/))
projectObj.variableDataMessage = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<artwork_is_copied\>/))
projectObj.artworkIsCopied = (getXMLdata(xmlLinesArr[lineNo]) == "yes" ? true : false); else if(xmlLinesArr[lineNo].toString().match(/\<artwork_is_transfered\>/))
projectObj.artworkIsTransfered = (getXMLdata(xmlLinesArr[lineNo]) == "yes" ? true : false); else if(xmlLinesArr[lineNo].toString().match(/\<project_in_cart\>/))
projectObj.isInShoppingCart = (getXMLdata(xmlLinesArr[lineNo]) == "yes" ? true : false); else if(xmlLinesArr[lineNo].toString().match(/\<project_saved_link\>/))
projectObj.savedProjectLink = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<order_id\>/))
projectObj.orderID = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<arrival_date\>/))
projectObj.arrivalDateUnixTimestamp = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<project_discount\>/))
projectObj.projectDiscount = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<project_subtotal\>/))
projectObj.subtotalFromDatabase = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<project_tax\>/))
projectObj.projectTax = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<status_char\>/))
projectObj.statusChar = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<status_description\>/))
projectObj.statusDesc = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<can_edit_artwork_still\>/))
projectObj.artworkCanStillBeEdited = (getXMLdata(xmlLinesArr[lineNo]) == "yes" ? true : false); else if(xmlLinesArr[lineNo].toString().match(/\<project_ordered_links\>/))
{ projectOrderedLinksIndex++; projectObj.orderedProjectLinks[projectOrderedLinksIndex] = getXMLdata(xmlLinesArr[lineNo]);}
else if(xmlLinesArr[lineNo].toString().match(/\<project_session_links\>/))
{ projectSessionLinksIndex++; projectObj.sessionProjectLinks[projectSessionLinksIndex] = getXMLdata(xmlLinesArr[lineNo]);}
else if(xmlLinesArr[lineNo].toString().match(/\<product_id_linked\>/))
{ templateProductLinksIndex++; projectObj.templateProductLinks[templateProductLinksIndex] = new TemplateLink(); projectObj.templateProductLinks[templateProductLinksIndex].productID = getXMLdata(xmlLinesArr[lineNo]); templateLinkIndex = -1;}
else if(xmlLinesArr[lineNo].toString().match(/\<template_link_id\>/))
{ templateLinkIndex++; projectObj.templateProductLinks[templateProductLinksIndex].templateIdsArr[templateLinkIndex] = getXMLdata(xmlLinesArr[lineNo]);}
else if(xmlLinesArr[lineNo].toString().match(/\<template_link_area\>/))
{ projectObj.templateProductLinks[templateProductLinksIndex].templateAreasArr[templateLinkIndex] = getXMLdata(xmlLinesArr[lineNo]);}
else if(xmlLinesArr[lineNo].toString().match(/\<template_link_preview_ids\>/))
{ projectObj.templateProductLinks[templateProductLinksIndex].templatePreviewIdsArr[templateLinkIndex] = getXMLdata(xmlLinesArr[lineNo]);}
else if(xmlLinesArr[lineNo].toString().match(/\<template_link_side_names\>/))
{ projectObj.templateProductLinks[templateProductLinksIndex].templatePreviewSideNamesArr[templateLinkIndex] = getXMLdata(xmlLinesArr[lineNo]);}
else if(xmlLinesArr[lineNo].toString().match(/\<template_link_preview_widths\>/))
{ projectObj.templateProductLinks[templateProductLinksIndex].templatePreviewWidthsArr[templateLinkIndex] = getXMLdata(xmlLinesArr[lineNo]);}
else if(xmlLinesArr[lineNo].toString().match(/\<template_link_preview_heights\>/))
{ projectObj.templateProductLinks[templateProductLinksIndex].templatePreviewHeightsArr[templateLinkIndex] = getXMLdata(xmlLinesArr[lineNo]);}
else if(xmlLinesArr[lineNo].toString().match(/\<\/project\>/))
{ if(!projectObj.productID.match(/^\d+$/) || !projectObj.projectID.match(/^\d+$/))
{ this.onProjectLoaderError_Private("5108", "Project Definition incomplete."); return;}
var projectIDint = parseInt(projectObj.projectID); var currentIndexOfCachedProjectsArr = this.cachedProjectObjectsPointerArr.length; this.cachedProjectObjectsPointerArr[currentIndexOfCachedProjectsArr] = projectIDint; this.cachedProjectObjectsArr[currentIndexOfCachedProjectsArr] = projectObj;}
}
this.loadProductObjects();}
function Project()
{ this.projectID = 0; this.productID = 0; this.dateModifiedUnixTimestamp = 0; this.savedProjectLink = 0; this.newProjectSessionID = 0; this.orderedProjectLinks = new Array(); this.sessionProjectLinks = new Array(); this.templateId = 0; this.templateArea = ""; this.templateProductLinks = new Array(); this.updateThumbnailEventFunctions = new Array(); this.updateThumbnailEventObjects = new Array(); this.shoppingCartAddEventFunctions = new Array(); this.shoppingCartAddEventObjects = new Array(); this.shoppingCartRemoveEventFunctions = new Array(); this.shoppingCartRemoveEventObjects = new Array(); this.updateOptionsEventFunctions = new Array(); this.updateOptionsEventObjects = new Array(); this.productConvertEventFunctions = new Array(); this.productConvertEventObjects = new Array(); this.productTransferEventFunctions = new Array(); this.productTransferEventObjects = new Array(); this.thumbnailSC = ""; this.thumbnailCache = ""; this.variableDataStatus = ""; this.variableDataMessage = ""; this.artworkIsCopied = false; this.artworkIsTransfered = false; this.isInShoppingCart = false; this.artThumbNeedsUpdateFlag = false; this.projectView = "must override this variable"; this.productObj = null; this.selectedOptionsObj = new SelectedOptions(); this.formSecurityCode = null; this.orderID = 0; this.subtotalFromDatabase = 0; this.projectDiscount = 0; this.projectTax = 0; this.arrivalDateUnixTimestamp = 0
this.statusChar = ""; this.statusDesc = ""; this.artworkCanStillBeEdited = true;}
Project.prototype.getFormSecurityCode = function()
{ return this.formSecurityCode;}
Project.prototype.getProjectID = function()
{ return this.projectID;}
Project.prototype.getProjectView = function()
{ return this.projectView;}
Project.prototype.getProductID = function()
{ return this.productID;}
Project.prototype.getTemplateID = function()
{ return this.templateId;}
Project.prototype.getTemplateArea = function()
{ return this.templateArea;}
Project.prototype.getTemplateProductLinks = function()
{ return this.templateProductLinks;}
Project.prototype.getProductObj = function()
{ return this.productObj;}
Project.prototype.getQuantity = function()
{ return this.selectedOptionsObj.getSelectedQuantity();}
Project.prototype.artworkThumbnailNeedsUpdate = function()
{ return this.artThumbNeedsUpdateFlag;}
Project.prototype.setOptionChoice = function(optName, choiceName)
{ this.selectedOptionsObj.setOptionChoice(optName, choiceName);}
Project.prototype.setQuantity = function(quantity)
{ this.selectedOptionsObj.setQuantity(quantity);}
Project.prototype.getOptionNames = function(hideOptionsHidden)
{ var optionsNamesArr = this.selectedOptionsObj.getOptionNames(); var retArr = new Array(); for(var i=0; i<optionsNamesArr.length; i++)
{ var choiceSelected = this.getChoiceSelected(optionsNamesArr[i]); if(!this.productObj.checkIfOptionAndChoiceExist(optionsNamesArr[i], choiceSelected))
continue; if(hideOptionsHidden)
{ if(this.productObj.isChoiceIsHidden(optionsNamesArr[i], choiceSelected))
continue;}
retArr.push(optionsNamesArr[i]);}
return retArr;}
Project.prototype.getChoiceSelected = function(optName)
{ var selectedChoice = this.selectedOptionsObj.getChoiceSelected(optName); if(selectedChoice == null)
alert("Error in method Project.getChoiceSelected. The Option does not exist: " + optName); return selectedChoice;}
Project.prototype.getSelectedOptionsObj = function()
{ return this.selectedOptionsObj;}
Project.prototype.checkIfArtworkCopied = function()
{ return this.artworkIsCopied;}
Project.prototype.checkIfArtworkTransfered = function()
{ return this.artworkIsTransfered;}
Project.prototype.checkIfInShoppingCart = function()
{ return this.isInShoppingCart;}
Project.prototype.getVariableDataStatus = function()
{ return this.variableDataStatus;}
Project.prototype.getVariableDataMessage = function()
{ return this.variableDataMessage;}
Project.prototype.getDateModified = function()
{ return new Date(this.dateModifiedUnixTimestamp * 1000);}
Project.prototype.getThumbnailCache = function()
{ return this.thumbnailCache;}
Project.prototype.getThumbnailSC = function()
{ return this.thumbnailSC;}
Project.prototype.getSubtotal = function()
{ return this.productObj.getSubtotalOverride(this.selectedOptionsObj);}
Project.prototype.getSubtotalExemptFromDiscounts = function()
{ return this.productObj.getSubtotalExemptFromDiscounts(this.selectedOptionsObj);}
Project.prototype.getSavedProjectLink = function()
{ if(this.projectView != "ordered" && this.projectView != "session")
{ alert("Error in method Project.getSavedProjectLink. The Project View is not correct for calling this method."); return;}
return this.savedProjectLink;}
Project.prototype.getOrderID = function()
{ if(this.projectView != "ordered")
{ alert("Error in method Project.getOrderID. The Project View is not correct for calling this method."); return;}
return this.orderID;}
Project.prototype.getSubtotalFromDatabase = function()
{ if(this.projectView != "ordered")
{ alert("Error in method Project.getSubtotalFromDatabase. The Project View is not correct for calling this method."); return;}
return this.subtotalFromDatabase;}
Project.prototype.getProjectDiscount = function()
{ if(this.projectView != "ordered")
{ alert("Error in method Project.getProjectDiscount. The Project View is not correct for calling this method."); return;}
return parseFloat(this.projectDiscount);}
Project.prototype.getProjectTax = function()
{ if(this.projectView != "ordered")
{ alert("Error in method Project.getProjectTax. The Project View is not correct for calling this method."); return;}
return parseFloat(this.projectTax);}
Project.prototype.getArrivalDate = function()
{ if(this.projectView != "ordered")
{ alert("Error in method Project.getArrivalDate. The Project View is not correct for calling this method."); return;}
return new Date(this.arrivalDateUnixTimestamp);}
Project.prototype.getStatusChar = function()
{ if(this.projectView != "ordered")
{ alert("Error in method Project.getStatusChar. The Project View is not correct for calling this method."); return;}
return this.statusChar;}
Project.prototype.getStatusDescription = function()
{ if(this.projectView != "ordered")
{ alert("Error in method Project.getStatusDescription. The Project View is not correct for calling this method."); return;}
return this.statusDesc;}
Project.prototype.checkIfArtworkCanStillBeEdited = function()
{ if(this.projectView != "ordered")
{ alert("Error in method Project.checkIfArtworkCanStillBeEdited. The Project View is not correct for calling this method."); return;}
return this.artworkCanStillBeEdited;}
Project.prototype.updateOptionsAndQuantity = function()
{ var instance = this; var dateObj = new Date(); var apiURL = "./api_dot.php?api_version=1.1&form_sc="+ this.getFormSecurityCode() +"&command=update_project_options&project_number=" + this.getProjectID() + "&view_type=" + this.getProjectView() + "&project_quantity=" + this.getQuantity() + "&project_options=" + escape(this.selectedOptionsObj.getOptionAndChoicesStr()) + "&nocache=" + dateObj.getTime(); var xmlHttpObjFromGlobalQueue = getXmlHttpObj("ProjectOptionsUpdate"); if(xmlHttpObjFromGlobalQueue == null)
return; xmlHttpObjFromGlobalQueue.open('GET', apiURL, true); xmlHttpObjFromGlobalQueue.onreadystatechange = function()
{ for(var t=0; t<xmlReqsObjArr.length; t++){ if(xmlReqsObjArr[t].reqObjFree || xmlReqsObjArr[t].reqObjParsing || xmlReqsObjArr[t].xmlHttpObj.readyState != 4 || xmlReqsObjArr[t].callBackFunctionName != "ProjectOptionsUpdate")
continue; if(xmlReqsObjArr[t].xmlHttpObj.status == "200"){ xmlReqsObjArr[t].reqObjParsing = true; instance.parseUpdateOptionsXMLresponse(xmlReqsObjArr[t].xmlHttpObj.responseText);}
else{ instance.updateOptionsErrorEvent_Private(xmlReqsObjArr[t].xmlHttpObj.status, xmlReqsObjArr[t].xmlHttpObj.statusText);}
xmlReqsObjArr[t].reqObjFree = true; xmlReqsObjArr[t].reqObjParsing = false;}
}
xmlHttpObjFromGlobalQueue.send(null);}
Project.prototype.parseUpdateOptionsXMLresponse = function(xmlhttpresponseText)
{ if(!xmlhttpresponseText.match(/\<result\>OK\<\/result\>/))
{ var errorMessage = getErrorMessageFromAPIresponseXML(xmlhttpresponseText); if(errorMessage == "")
this.updateOptionsErrorEvent_Private("5401", "Unknown Error Parsing XML Document"); else
this.updateOptionsErrorEvent_Private("5402", errorMessage); return;}
else{ if(xmlhttpresponseText.match(/\<update_thumbanil_image\>yes\<\/update_thumbanil_image\>/))
this.artThumbNeedsUpdateFlag = true; else
this.artThumbNeedsUpdateFlag = false; for(var i=0; i<this.updateOptionsEventFunctions.length; i++){ this.updateOptionsEventFunctions[i].call(this.updateOptionsEventObjects[i], this.getProjectID(), true, "");}
}
}
Project.prototype.updateOptionsErrorEvent_Private = function(responseCode, responseError)
{ for(var i=0; i<this.updateOptionsEventFunctions.length; i++){ this.updateOptionsEventFunctions[i].call(this.updateOptionsEventObjects[i], this.getProjectID(), false, responseError);}
}
Project.prototype.updateThumbnailImage = function()
{ var instance = this; var dateObj = new Date(); var apiURL = "./api_dot.php?api_version=1.1&form_sc="+ this.getFormSecurityCode() +"&command=update_artwork_thumbnail&project_number=" + this.getProjectID() + "&view_type=" + this.getProjectView() + "&nocache=" + dateObj.getTime(); var xmlHttpObjFromGlobalQueue = getXmlHttpObj("ProjectThumbnailUpdate"); if(xmlHttpObjFromGlobalQueue == null)
return; xmlHttpObjFromGlobalQueue.open('GET', apiURL, true); xmlHttpObjFromGlobalQueue.onreadystatechange = function()
{ for(var t=0; t<xmlReqsObjArr.length; t++){ if(xmlReqsObjArr[t].reqObjFree || xmlReqsObjArr[t].reqObjParsing || xmlReqsObjArr[t].xmlHttpObj.readyState != 4 || xmlReqsObjArr[t].callBackFunctionName != "ProjectThumbnailUpdate")
continue; if(xmlReqsObjArr[t].xmlHttpObj.status == "200"){ xmlReqsObjArr[t].reqObjParsing = true; instance.parseUpdateThumbnailImageXMLresponse(xmlReqsObjArr[t].xmlHttpObj.responseText);}
else{ instance.updateThumbnailImageErrorEvent_Private(xmlReqsObjArr[t].xmlHttpObj.status, xmlReqsObjArr[t].xmlHttpObj.statusText);}
xmlReqsObjArr[t].reqObjFree = true; xmlReqsObjArr[t].reqObjParsing = false;}
}
xmlHttpObjFromGlobalQueue.send(null);}
Project.prototype.parseUpdateThumbnailImageXMLresponse = function(xmlhttpresponseText)
{ if(!xmlhttpresponseText.match(/\<result\>OK\<\/result\>/))
{ var errorMessage = getErrorMessageFromAPIresponseXML(xmlhttpresponseText); if(errorMessage == "")
this.updateThumbnailImageErrorEvent_Private("5501", "Unknown Error Parsing XML Document"); else
this.updateThumbnailImageErrorEvent_Private("5502", errorMessage); return;}
else{ this.artThumbNeedsUpdateFlag = false; for(var i=0; i<this.updateThumbnailEventFunctions.length; i++){ this.updateThumbnailEventFunctions[i].call(this.updateThumbnailEventObjects[i], this.getProjectID(), true, "");}
}
}
Project.prototype.updateThumbnailImageErrorEvent_Private = function(responseCode, responseError)
{ for(var i=0; i<this.updateThumbnailEventFunctions.length; i++){ this.updateThumbnailEventFunctions[i].call(this.updateThumbnailEventObjects[i], this.getProjectID(), false, responseError);}
}
Project.prototype.attachThumbnailUpdateEvent = function(functionRef, objectRef){ this.updateThumbnailEventFunctions.push(functionRef); this.updateThumbnailEventObjects.push(functionRef);}
Project.prototype.attachUpdateProjectOptionsEvent = function(functionRef, objectRef){ this.updateOptionsEventFunctions.push(functionRef); this.updateOptionsEventObjects.push(functionRef);}
Project.prototype.attachProductConversionEvent = function(functionRef, objectRef){ this.productConvertEventFunctions.push(functionRef); this.productConvertEventObjects.push(functionRef);}
Project.prototype.addToShoppingCart = function()
{ if(this.getProjectView() != "saved" && this.getProjectView() != "session" ){ alert("Error in method addToShoppingCart. The ViewType can only be 'saved' or 'session'."); return;}
this.newProjectSessionID = 0; var instance = this; var dateObj = new Date(); var apiURL = "./api_dot.php?api_version=1.1&form_sc="+ this.getFormSecurityCode() +"&command=add_to_shoppingcart&project_number=" + this.getProjectID() + "&view_type=" + this.getProjectView() + "&nocache=" + dateObj.getTime(); var xmlHttpObjFromGlobalQueue = getXmlHttpObj("AddToCartReq"); if(xmlHttpObjFromGlobalQueue == null)
return; xmlHttpObjFromGlobalQueue.open('GET', apiURL, true); xmlHttpObjFromGlobalQueue.onreadystatechange = function()
{ for(var t=0; t<xmlReqsObjArr.length; t++){ if(xmlReqsObjArr[t].reqObjFree || xmlReqsObjArr[t].reqObjParsing || xmlReqsObjArr[t].xmlHttpObj.readyState != 4 || xmlReqsObjArr[t].callBackFunctionName != "AddToCartReq")
continue; if(xmlReqsObjArr[t].xmlHttpObj.status == "200"){ xmlReqsObjArr[t].reqObjParsing = true; instance.parseAddToShoppingCartXMLresponse(xmlReqsObjArr[t].xmlHttpObj.responseText);}
else{ instance.addToShoppingCartErrorEvent_Private(xmlReqsObjArr[t].xmlHttpObj.status, xmlReqsObjArr[t].xmlHttpObj.statusText);}
xmlReqsObjArr[t].reqObjFree = true; xmlReqsObjArr[t].reqObjParsing = false;}
}
xmlHttpObjFromGlobalQueue.send(null);}
Project.prototype.parseAddToShoppingCartXMLresponse = function(xmlhttpresponseText)
{ if(!xmlhttpresponseText.match(/\<result\>OK\<\/result\>/))
{ var errorMessage = getErrorMessageFromAPIresponseXML(xmlhttpresponseText); if(errorMessage == "")
this.addToShoppingCartErrorEvent_Private("5501", "Unknown Error Parsing XML Document"); else
this.addToShoppingCartErrorEvent_Private("5502", errorMessage); return;}
else{ var xmlLinesArr = xmlhttpresponseText.split("\n"); for(var lineNo = 0; lineNo < xmlLinesArr.length; lineNo++)
{ if(xmlLinesArr[lineNo].toString().match(/\<project_session_id\>/))
{ this.newProjectSessionID = getXMLdata(xmlLinesArr[lineNo]); break;}
}
for(var i=0; i<this.shoppingCartAddEventFunctions.length; i++){ this.shoppingCartAddEventFunctions[i].call(this.shoppingCartAddEventObjects[i], this.getProjectID(), true, "");}
}
}
Project.prototype.addToShoppingCartErrorEvent_Private = function(responseCode, responseError)
{ for(var i=0; i<this.shoppingCartAddEventFunctions.length; i++){ this.shoppingCartAddEventFunctions[i].call(this.shoppingCartAddEventObjects[i], this.getProjectID(), false, responseError);}
}
Project.prototype.attachAddToShoppingCartEvent = function(functionRef, objectRef){ this.shoppingCartAddEventFunctions.push(functionRef); this.shoppingCartAddEventObjects.push(objectRef);}
Project.prototype.removeFromShoppingCart = function()
{ if(this.getProjectView() != "session" ){ alert("Error in method removeFromShoppingCart. The ViewType can only be 'session'."); return;}
var instance = this; var dateObj = new Date(); var apiURL = "./api_dot.php?api_version=1.1&form_sc="+ this.getFormSecurityCode() +"&command=remove_from_shoppingcart&project_number=" + this.getProjectID() + "&view_type=" + this.getProjectView() + "&nocache=" + dateObj.getTime(); var xmlHttpObjFromGlobalQueue = getXmlHttpObj("RemoveFromCartReq"); if(xmlHttpObjFromGlobalQueue == null)
return; xmlHttpObjFromGlobalQueue.open('GET', apiURL, true); xmlHttpObjFromGlobalQueue.onreadystatechange = function()
{ for(var t=0; t<xmlReqsObjArr.length; t++){ if(xmlReqsObjArr[t].reqObjFree || xmlReqsObjArr[t].reqObjParsing || xmlReqsObjArr[t].xmlHttpObj.readyState != 4 || xmlReqsObjArr[t].callBackFunctionName != "RemoveFromCartReq")
continue; if(xmlReqsObjArr[t].xmlHttpObj.status == "200"){ xmlReqsObjArr[t].reqObjParsing = true; instance.parseRemoveFromShoppingCartXMLresponse(xmlReqsObjArr[t].xmlHttpObj.responseText);}
else{ instance.removeFromShoppingCartErrorEvent_Private(xmlReqsObjArr[t].xmlHttpObj.status, xmlReqsObjArr[t].xmlHttpObj.statusText);}
xmlReqsObjArr[t].reqObjFree = true; xmlReqsObjArr[t].reqObjParsing = false;}
}
xmlHttpObjFromGlobalQueue.send(null);}
Project.prototype.parseRemoveFromShoppingCartXMLresponse = function(xmlhttpresponseText)
{ if(!xmlhttpresponseText.match(/\<result\>OK\<\/result\>/))
{ var errorMessage = getErrorMessageFromAPIresponseXML(xmlhttpresponseText); if(errorMessage == "")
this.removeFromShoppingCartErrorEvent_Private("5501", "Unknown Error Parsing XML Document"); else
this.removeFromShoppingCartErrorEvent_Private("5502", errorMessage); return;}
else{ for(var i=0; i<this.shoppingCartRemoveEventFunctions.length; i++){ this.shoppingCartRemoveEventFunctions[i].call(this.shoppingCartRemoveEventObjects[i], this.getProjectID(), true, "");}
}
}
Project.prototype.removeFromShoppingCartErrorEvent_Private = function(responseCode, responseError)
{ for(var i=0; i<this.shoppingCartRemoveEventFunctions.length; i++){ this.shoppingCartRemoveEventFunctions[i].call(this.shoppingCartRemoveEventObjects[i], this.getProjectID(), false, responseError);}
}
Project.prototype.attachRemoveFromShoppingCartEvent = function(functionRef, objectRef){ this.shoppingCartRemoveEventFunctions.push(functionRef); this.shoppingCartRemoveEventObjects.push(objectRef);}
Project.prototype.convertToAnotherProductID = function(converToProductID)
{ var instance = this; var dateObj = new Date(); var apiURL = "./api_dot.php?api_version=1.1&form_sc="+ this.getFormSecurityCode() +"&command=convert_to_another_product&project_number=" + this.getProjectID() + "&view_type=" + this.getProjectView() + "&conver_to_product_id=" + converToProductID + "&nocache=" + dateObj.getTime(); var xmlHttpObjFromGlobalQueue = getXmlHttpObj("ProjectConvertProduct"); if(xmlHttpObjFromGlobalQueue == null)
return; xmlHttpObjFromGlobalQueue.open('GET', apiURL, true); xmlHttpObjFromGlobalQueue.onreadystatechange = function()
{ for(var t=0; t<xmlReqsObjArr.length; t++){ if(xmlReqsObjArr[t].reqObjFree || xmlReqsObjArr[t].reqObjParsing || xmlReqsObjArr[t].xmlHttpObj.readyState != 4 || xmlReqsObjArr[t].callBackFunctionName != "ProjectConvertProduct")
continue; if(xmlReqsObjArr[t].xmlHttpObj.status == "200"){ xmlReqsObjArr[t].reqObjParsing = true; instance.parseConverToAnotherProductXMLresponse(xmlReqsObjArr[t].xmlHttpObj.responseText);}
else{ instance.parseConvertToAnotherProductErrorEvent_Private(xmlReqsObjArr[t].xmlHttpObj.status, xmlReqsObjArr[t].xmlHttpObj.statusText);}
xmlReqsObjArr[t].reqObjFree = true; xmlReqsObjArr[t].reqObjParsing = false;}
}
xmlHttpObjFromGlobalQueue.send(null);}
Project.prototype.parseConverToAnotherProductXMLresponse = function(xmlhttpresponseText)
{ if(!xmlhttpresponseText.match(/\<result\>OK\<\/result\>/))
{ var errorMessage = getErrorMessageFromAPIresponseXML(xmlhttpresponseText); if(errorMessage == "")
this.parseConvertToAnotherProductErrorEvent_Private("5601", "Unknown Error Parsing XML Document"); else
this.parseConvertToAnotherProductErrorEvent_Private("5602", errorMessage); return;}
else{ if(xmlhttpresponseText.match(/\<update_thumbanil_image\>yes\<\/update_thumbanil_image\>/))
this.artThumbNeedsUpdateFlag = true; else
this.artThumbNeedsUpdateFlag = false; for(var i=0; i<this.productConvertEventFunctions.length; i++){ this.productConvertEventFunctions[i].call(this.productConvertEventObjects[i], this.getProjectID(), true, "");}
}
}
Project.prototype.parseConvertToAnotherProductErrorEvent_Private = function(responseCode, responseError)
{ for(var i=0; i<this.productConvertEventFunctions.length; i++){ this.productConvertEventFunctions[i].call(this.productConvertEventObjects[i], this.getProjectID(), false, responseError);}
}
Project.prototype.transferToProduct = function(newProductId, destinationView, newTemplateId, newTemplateArea)
{ if(destinationView != "session" && destinationView != "saved")
{ alert("Illegal destination view for product transfer."); return;}
var instance = this; var dateObj = new Date(); var apiURL = "./api_dot.php?api_version=1.1&form_sc="+ this.getFormSecurityCode() +"&command=product_transfer&project_number=" + this.getProjectID() + "&view_type=" + this.getProjectView() + "&transfer_to_product_id=" + newProductId + "&destination_project_view_type=" + destinationView + "&template_id=" + newTemplateId + "&template_area=" + newTemplateArea + "&nocache=" + dateObj.getTime(); var xmlHttpObjFromGlobalQueue = getXmlHttpObj("ProductTransferReq"); if(xmlHttpObjFromGlobalQueue == null)
return; xmlHttpObjFromGlobalQueue.open('GET', apiURL, true); xmlHttpObjFromGlobalQueue.onreadystatechange = function()
{ for(var t=0; t<xmlReqsObjArr.length; t++){ if(xmlReqsObjArr[t].reqObjFree || xmlReqsObjArr[t].reqObjParsing || xmlReqsObjArr[t].xmlHttpObj.readyState != 4 || xmlReqsObjArr[t].callBackFunctionName != "ProductTransferReq")
continue; if(xmlReqsObjArr[t].xmlHttpObj.status == "200"){ xmlReqsObjArr[t].reqObjParsing = true; instance.parseProductTransferXMLresponse(xmlReqsObjArr[t].xmlHttpObj.responseText);}
else{ instance.productTransferErrorEvent_Private(xmlReqsObjArr[t].xmlHttpObj.status, xmlReqsObjArr[t].xmlHttpObj.statusText);}
xmlReqsObjArr[t].reqObjFree = true; xmlReqsObjArr[t].reqObjParsing = false;}
}
xmlHttpObjFromGlobalQueue.send(null);}
Project.prototype.parseProductTransferXMLresponse = function(xmlhttpresponseText)
{ var newProjectId = 0; var newProjectView = ""; if(!xmlhttpresponseText.match(/\<result\>OK\<\/result\>/))
{ var errorMessage = getErrorMessageFromAPIresponseXML(xmlhttpresponseText); if(errorMessage == "")
this.productTransferErrorEvent_Private("5801", "Unknown Error Parsing XML Document"); else
this.productTransferErrorEvent_Private("5802", errorMessage); return;}
else{ var xmlLinesArr = xmlhttpresponseText.split("\n"); for(var lineNo = 0; lineNo < xmlLinesArr.length; lineNo++)
{ if(xmlLinesArr[lineNo].toString().match(/\<new_project_id\>/))
{ newProjectId = getXMLdata(xmlLinesArr[lineNo]);}
if(xmlLinesArr[lineNo].toString().match(/\<new_project_view\>/))
{ newProjectView = getXMLdata(xmlLinesArr[lineNo]);}
}
if(newProjectId == 0 || newProjectView == ""){ this.productTransferErrorEvent_Private("5803", "Unknown error transfering to a new product."); return;}
for(var i=0; i<this.productTransferEventFunctions.length; i++){ this.productTransferEventFunctions[i].call(this.productTransferEventObjects[i], this.getProjectID(), true, "", newProjectId, newProjectView);}
}
}
Project.prototype.productTransferErrorEvent_Private = function(responseCode, responseError)
{ for(var i=0; i<this.productTransferEventFunctions.length; i++){ this.productTransferEventFunctions[i].call(this.productTransferEventObjects[i], this.getProjectID(), false, responseError, "", "");}
}
Project.prototype.attachProductTransferCompleteEvent = function(functionRef, objectRef){ this.productTransferEventFunctions.push(functionRef); this.productTransferEventObjects.push(objectRef);}
function TemplateLink(){ this.productID = 0; this.templateIdsArr = new Array(); this.templateAreasArr = new Array(); this.templatePreviewIdsArr = new Array(); this.templatePreviewSideNamesArr = new Array(); this.templatePreviewWidthsArr = new Array(); this.templatePreviewHeightsArr = new Array();}
TemplateLink.prototype.getTemplateIds = function()
{ return this.templateIdsArr;}
TemplateLink.prototype.getTemplateAreaFromTemplateId = function(templateId){ for(var i=0; i<this.templateIdsArr.length; i++){ if(templateId == this.templateIdsArr[i]){ return this.templateAreasArr[i];}
}
alert("Illegal Template id in getTemplateAreaFromTemplateId");}
TemplateLink.prototype.getPreviewSideNamesFromTemplateId = function(templateId){ for(var i=0; i<this.templateIdsArr.length; i++){ if(templateId == this.templateIdsArr[i]){ return this.templatePreviewSideNamesArr[i].split("\|");}
}
alert("Illegal Template id in getPreviewSideNamesFromTemplateId"); return new Array();}
TemplateLink.prototype.getPreviewIdsFromTemplateId = function(templateId){ for(var i=0; i<this.templateIdsArr.length; i++){ if(templateId == this.templateIdsArr[i]){ return this.templatePreviewIdsArr[i].split("\|");}
}
alert("Illegal Template id in getPreviewIdsFromTemplateId"); return new Array();}
TemplateLink.prototype.getPreviewWidthsFromTemplateId = function(templateId){ for(var i=0; i<this.templateIdsArr.length; i++){ if(templateId == this.templateIdsArr[i]){ return this.templatePreviewWidthsArr[i].split("\|");}
}
alert("Illegal Template id in getPreviewWidthsFromTemplateId"); return new Array();}
TemplateLink.prototype.getPreviewHeightsFromTemplateId = function(templateId){ for(var i=0; i<this.templateIdsArr.length; i++){ if(templateId == this.templateIdsArr[i]){ return this.templatePreviewHeightsArr[i].split("\|");}
}
alert("Illegal Template id in getPreviewHeightsFromTemplateId"); return new Array();}
function ProductLoader()
{ this.productIDsQueued = new Array(); this.cachedProductObjectsArr = new Array(); this.cachedProductObjectsPointerArr = new Array(); this.productLoadingEventsFunctionRefArr = new Array(); this.productLoadingEventsObjectRefArr = new Array(); this.productLoadingEventsProductsIDsArr = new Array(); this.productLoadingEventsAllProductsFunctionsArr = new Array(); this.productLoadingEventsAllProductsObjectsArr = new Array(); this.productLoadingErrorEventsFunctionRefArr = new Array(); this.productLoadingErrorEventsObjectRefArr = new Array(); this.parsingInProgressFlag = false; this.parsingXMLresponseQueue = new Array(); this.includeVendorsFlag = false;}
ProductLoader.prototype.includeVendors = function(boolFlag)
{ if(boolFlag)
this.includeVendorsFlag = true; else
this.inincludeVendorsFlag = false;}
ProductLoader.prototype.attachProductLoadedEvent = function(productID, functionReference, objectReference)
{ this.productLoadingEventsProductsIDsArr.push(productID); this.productLoadingEventsFunctionRefArr.push(functionReference); this.productLoadingEventsObjectRefArr.push(objectReference); if(this.checkIfProductInitialized(productID))
{ this.privateMethod_ProductLoadingComplete(productID);}
else{ this.loadProductID(productID);}
}
ProductLoader.prototype.attachProductLoadedGlobalEvent = function(functionReference, objectReference)
{ this.productLoadingEventsAllProductsFunctionsArr.push(functionReference); this.productLoadingEventsAllProductsObjectsArr.push(objectReference);}
ProductLoader.prototype.attatchProductLoadingErrorEvent = function(functionReference, objectReference)
{ this.productLoadingErrorEventsFunctionRefArr.push(functionReference); this.productLoadingErrorEventsObjectRefArr.push(objectReference);}
ProductLoader.prototype.privateMethod_ProductLoadingComplete = function(productID)
{ for(var i=0; i<this.productLoadingEventsProductsIDsArr.length; i++)
{ if(this.productLoadingEventsProductsIDsArr[i] == productID)
{ this.productLoadingEventsProductsIDsArr[i] = 0; this.productLoadingEventsFunctionRefArr[i].call(this.productLoadingEventsObjectRefArr[i]);}
}
for(var i=0; i<this.productLoadingEventsAllProductsFunctionsArr.length; i++)
{ this.productLoadingEventsAllProductsFunctionsArr[i].call(this.productLoadingEventsAllProductsObjectsArr[i], productID);}
}
ProductLoader.prototype.onProductLoadError_Private = function(statusCode, errorMessage)
{ for(var i=0; i < this.productLoadingErrorEventsFunctionRefArr.length; i++){ this.productLoadingErrorEventsFunctionRefArr[i].call(this.productLoadingErrorEventsObjectRefArr, statusCode, errorMessage);}
}
ProductLoader.prototype.loadProductID = function(productID)
{ for(var i=0; i<this.productIDsQueued.length; i++)
{ if(this.productIDsQueued[i] == productID)
return;}
this.productIDsQueued.push(productID); var numberOfMinutesToCacheResults = 15; var dateObj = new Date(); var dateCache = dateObj.getYear() + "-" + dateObj.getMonth() + "-" + dateObj.getDate() + "-" + dateObj.getHours() + "-" + (Math.ceil(parseInt(dateObj.getMinutes()) / numberOfMinutesToCacheResults)) ; var apiURL = "./api_dot.php?api_version=1.1&command=get_product_definition&product_id=" + productID + "&nocache=" + dateCache; if(this.includeVendorsFlag)
apiURL += "&includeVendors=true"; var xmlHttpObjFromGlobalQueue = getXmlHttpObj("ProductLoader"); if(xmlHttpObjFromGlobalQueue == null)
return; var instance = this; xmlHttpObjFromGlobalQueue.open('GET', apiURL, true); xmlHttpObjFromGlobalQueue.onreadystatechange = function()
{ for(var t=0; t<xmlReqsObjArr.length; t++){ if(xmlReqsObjArr[t].reqObjFree || xmlReqsObjArr[t].reqObjParsing || xmlReqsObjArr[t].xmlHttpObj.readyState != 4 || xmlReqsObjArr[t].callBackFunctionName != "ProductLoader")
continue; if(xmlReqsObjArr[t].xmlHttpObj.status == "200")
{ xmlReqsObjArr[t].reqObjParsing = true; instance.parseXMLresponse(xmlReqsObjArr[t].xmlHttpObj.responseText);}
else
{ instance.onProductLoadError_Private(xmlReqsObjArr[t].xmlHttpObj.status, xmlReqsObjArr[t].xmlHttpObj.statusText);}
xmlReqsObjArr[t].reqObjFree = true; xmlReqsObjArr[t].reqObjParsing = false;}
}
xmlHttpObjFromGlobalQueue.send(null);}
ProductLoader.prototype.parseXMLresponse = function(xmlhttpresponseText)
{ if(this.parsingInProgressFlag){ this.parsingXMLresponseQueue.push(xmlhttpresponseText);}
else{ this.parsingInProgressFlag = true; this.private_parseXMLresponse(xmlhttpresponseText);}
}
ProductLoader.prototype.private_parseXMLresponse = function(xmlhttpresponseText)
{ if(!xmlhttpresponseText.match(/\<result\>OK\<\/result\>/))
{ var errorMessage = getErrorMessageFromAPIresponseXML(xmlhttpresponseText); if(errorMessage == ""){ this.onProductLoadError_Private("5101", "Unknown Error Parsing XML Document");}
else{ this.onProductLoadError_Private("5102", errorMessage);}
return;}
var newProduct = new Product(); var optionIndex = -1; var choiceIndex = -1; var productSwitchIndex = -1; var xmlLinesArr = xmlhttpresponseText.split("\n"); for(var lineNo = 0; lineNo < xmlLinesArr.length; lineNo++)
{ if(xmlLinesArr[lineNo].toString().match(/\<product_id\>/))
newProduct.productID = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<product_title\>/))
newProduct.productTitle = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<product_title_ext\>/))
newProduct.productTitleExt = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<product_name\>/))
newProduct.productName = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<artwork_sides_possible_count\>/))
newProduct.numberOfArtworkSides = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<artwork_width_inches\>/))
newProduct.artworkWidthInches = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<artwork_height_inches\>/))
newProduct.artworkHeightInches = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<artwork_bleed_picas\>/))
newProduct.artworkBleedPicas = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<artwork_is_editable\>/))
newProduct.artworkIsEditable = (getXMLdata(xmlLinesArr[lineNo]) == "yes" ? true : false); else if(xmlLinesArr[lineNo].toString().match(/\<unit_weight\>/))
newProduct.unitWeight = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<base_price\>/))
newProduct.basePrice = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<initial_subtotal\>/))
newProduct.initialSubtotal = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<main_quantity_price_breaks_base\>/))
newProduct.quantityBreaksBase = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<main_quantity_price_breaks_subtotal\>/))
newProduct.quantityBreaksSubtotal = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<thumbnail_width_approximate\>/))
newProduct.thumbnailWidthApprox = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<thumbnail_height_approximate\>/))
newProduct.thumbnailHeightApprox = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<variable_data\>/))
newProduct.variableDataFlag = (getXMLdata(xmlLinesArr[lineNo]) == "yes" ? true : false); else if(xmlLinesArr[lineNo].toString().match(/\<mailing_services\>/))
newProduct.mailingServicesFlag = (getXMLdata(xmlLinesArr[lineNo]) == "yes" ? true : false); else if(xmlLinesArr[lineNo].toString().match(/\<thumbnail_background_exists\>/))
newProduct.thumbnailBackgroundFlag = (getXMLdata(xmlLinesArr[lineNo]) == "yes" ? true : false); else if(xmlLinesArr[lineNo].toString().match(/\<thumbnail_copy_icon_exists\>/))
newProduct.thumbnailCopyIconFlag = (getXMLdata(xmlLinesArr[lineNo]) == "yes" ? true : false); else if(xmlLinesArr[lineNo].toString().match(/\<product_importance\>/))
newProduct.productImportance = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<compatible_product_ids\>/))
newProduct.compatibleProductIDsArr = getXMLdata(xmlLinesArr[lineNo]).split("\|"); else if(xmlLinesArr[lineNo].toString().match(/\<vendor_names\>/))
newProduct.vendorNamesArr = getXMLdata(xmlLinesArr[lineNo]).split("@"); else if(xmlLinesArr[lineNo].toString().match(/\<vendor_base_prices\>/))
newProduct.vendorBasePrices = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<vendor_initial_subtotals\>/))
newProduct.vendorInitialSubtotals = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<main_vendor_quantity_price_breaks_base\>/))
newProduct.vendorQuantityBreaksBase = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<main_vendor_quantity_price_breaks_subtotal\>/))
newProduct.vendorQuantityBreaksSubtotal = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<product_switch\>/))
{ productSwitchIndex++; newProduct.productSwitcher.projectSwitches[productSwitchIndex] = new ProductSwitchContainer();}
else if(xmlLinesArr[lineNo].toString().match(/\<product_id_target\>/))
newProduct.productSwitcher.projectSwitches[productSwitchIndex].productIDtarget = parseInt(getXMLdata(xmlLinesArr[lineNo])); else if(xmlLinesArr[lineNo].toString().match(/\<product_title_target\>/))
newProduct.productSwitcher.projectSwitches[productSwitchIndex].productName = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<product_switch_title\>/))
newProduct.productSwitcher.projectSwitches[productSwitchIndex].switchTitle = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<product_switch_link_subject\>/))
newProduct.productSwitcher.projectSwitches[productSwitchIndex].linkSubject = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<product_switch_description\>/))
newProduct.productSwitcher.projectSwitches[productSwitchIndex].description = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<grouped_to_product_id\>/))
newProduct.productSwitcher.projectSwitches[productSwitchIndex].groupedToProductID = parseInt(getXMLdata(xmlLinesArr[lineNo])); else if(xmlLinesArr[lineNo].toString().match(/\<product_switch_description_is_html\>/))
newProduct.productSwitcher.projectSwitches[productSwitchIndex].descriptionIsHTML = (getXMLdata(xmlLinesArr[lineNo]) == "yes" ? true : false); else if(xmlLinesArr[lineNo].toString().match(/\<group_head\>/))
newProduct.productSwitcher.projectSwitches[productSwitchIndex].isGroupHead = (getXMLdata(xmlLinesArr[lineNo]) == "yes" ? true : false); else if(xmlLinesArr[lineNo].toString().match(/\<option\>/))
{ optionIndex++; choiceIndex = -1; newProduct.options[optionIndex] = new ProductOption();}
else if(xmlLinesArr[lineNo].toString().match(/\<option_name\>/))
newProduct.options[optionIndex].optionName = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<option_alias\>/))
newProduct.options[optionIndex].optionAlias = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<option_description\>/))
newProduct.options[optionIndex].optionDescription = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<option_description_is_html\>/))
newProduct.options[optionIndex].optionDescriptionIsHTML = (getXMLdata(xmlLinesArr[lineNo]) == "yes" ? true : false); else if(xmlLinesArr[lineNo].toString().match(/\<option_variable_image_controller\>/))
newProduct.options[optionIndex].optionVariableImageController = (getXMLdata(xmlLinesArr[lineNo]) == "yes" ? true : false); else if(xmlLinesArr[lineNo].toString().match(/\<option_affects_artwork_sides\>/))
newProduct.options[optionIndex].affectsArtworkSidesFlag = (getXMLdata(xmlLinesArr[lineNo]) == "yes" ? true : false); else if(xmlLinesArr[lineNo].toString().match(/\<option_is_admin\>/))
newProduct.options[optionIndex].adminOptionFlag = (getXMLdata(xmlLinesArr[lineNo]) == "yes" ? true : false); else if(xmlLinesArr[lineNo].toString().match(/\<option_discount_exempt\>/))
newProduct.options[optionIndex].discountExempt = (getXMLdata(xmlLinesArr[lineNo]) == "yes" ? true : false); else if(xmlLinesArr[lineNo].toString().match(/\<choice\>/))
{ choiceIndex++; newProduct.options[optionIndex].choices[choiceIndex] = new OptionChoice();}
else if(xmlLinesArr[lineNo].toString().match(/\<choice_name\>/))
newProduct.options[optionIndex].choices[choiceIndex].choiceName = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<choice_alias\>/))
newProduct.options[optionIndex].choices[choiceIndex].choiceAlias = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<choice_description\>/))
newProduct.options[optionIndex].choices[choiceIndex].choiceDescription = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<choice_description_is_html\>/))
newProduct.options[optionIndex].choices[choiceIndex].choiceDescriptionIsHTML = (getXMLdata(xmlLinesArr[lineNo]) == "yes" ? true : false); else if(xmlLinesArr[lineNo].toString().match(/\<choice_is_hidden\>/))
newProduct.options[optionIndex].choices[choiceIndex].choiceIsHiddenFlag = (getXMLdata(xmlLinesArr[lineNo]) == "yes" ? true : false); else if(xmlLinesArr[lineNo].toString().match(/\<choice_variable_images\>/))
newProduct.options[optionIndex].choices[choiceIndex].choiceVariableImages = (getXMLdata(xmlLinesArr[lineNo]) == "yes" ? true : false); else if(xmlLinesArr[lineNo].toString().match(/\<change_artwork_sides\>/))
newProduct.options[optionIndex].choices[choiceIndex].changeArtworkSides = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<choice_base_price_change\>/))
newProduct.options[optionIndex].choices[choiceIndex].baseChange = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<choice_subtotal_change\>/))
newProduct.options[optionIndex].choices[choiceIndex].subtotalChange = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<choice_base_weight_change\>/))
newProduct.options[optionIndex].choices[choiceIndex].baseWeightChange = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<choice_project_weight_change\>/))
newProduct.options[optionIndex].choices[choiceIndex].projectWeightChange = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<choice_quantity_price_breaks_base\>/))
newProduct.options[optionIndex].choices[choiceIndex].quantityBreaksBase = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<choice_quantity_price_breaks_subtotal\>/))
newProduct.options[optionIndex].choices[choiceIndex].quantityBreaksSubtotal = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<choice_vendor_base_price_change\>/))
newProduct.options[optionIndex].choices[choiceIndex].vendorBaseChanges = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<choice_vendor_subtotal_change\>/))
newProduct.options[optionIndex].choices[choiceIndex].vendorSubtotalChanges = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<choice_vendor_quantity_price_breaks_base\>/))
newProduct.options[optionIndex].choices[choiceIndex].vendorQuantityBreaksBase = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<choice_vendor_quantity_price_breaks_subtotal\>/))
newProduct.options[optionIndex].choices[choiceIndex].vendorQuantityBreaksSubtotal = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<default_choice\>/) ){ if(getXMLdata(xmlLinesArr[lineNo]) == "yes")
newProduct.selectedOptionsObj.setOptionChoice(newProduct.options[optionIndex].optionName, newProduct.options[optionIndex].choices[choiceIndex].choiceName);}
else if(xmlLinesArr[lineNo].toString().match(/\<default_quantity\>/))
newProduct.selectedOptionsObj.setQuantity(getXMLdata(xmlLinesArr[lineNo]));}
if(!newProduct.productID.match(/^\d+$/))
{ for(var i=0; i<this.productLoadingErrorEventsFunctionRefArr.length; i++){ this.productLoadingErrorEventsFunctionRefArr[i].call(this.productLoadingErrorEventsObjectRefArr[i], "5103", "Product Definition Error");}
return;}
var productIDint = parseInt(newProduct.productID); var currentCachedProductsIndex = this.cachedProductObjectsPointerArr.length; this.cachedProductObjectsPointerArr[currentCachedProductsIndex] = productIDint; this.cachedProductObjectsArr[currentCachedProductsIndex] = newProduct; this.privateMethod_ProductLoadingComplete(productIDint); if(this.parsingXMLresponseQueue.length > 0){ var nextXMLfileToParse = this.parsingXMLresponseQueue.pop(); this.private_parseXMLresponse(nextXMLfileToParse);}
this.parsingInProgressFlag = false;}
ProductLoader.prototype.checkIfProductInitialized = function(productID)
{ if(this.getIndexOfProductObjectsCache(productID) < 0)
return false; else
return true;}
ProductLoader.prototype.getIndexOfProductObjectsCache = function(productID)
{ for(var i=0; i < this.cachedProductObjectsPointerArr.length; i++){ if(this.cachedProductObjectsPointerArr[i] == productID)
return i;}
return -1;}
ProductLoader.prototype.getProductObj = function(productID)
{ if(!this.checkIfProductInitialized(productID))
{ alert("Error in method getProductObj. You can't call this method until the Object has finished loading."); return false;}
return this.cachedProductObjectsArr[this.getIndexOfProductObjectsCache(productID)];}
function Product()
{ this.productID = 0; this.productTitle = "Undefined Product Name"; this.productTitleExt = ""; this.productName = "Undefined Product Name"; this.numberOfArtworkSides = 0; this.artworkWidthInches = 0; this.artworkHeightInches = 0; this.artworkBleedPicas = 0; this.artworkIsEditable = true; this.unitWeight = 0; this.basePrice = 0; this.initialSubtotal = 0; this.variableDataFlag = false; this.mailingServicesFlag = false; this.thumbnailBackgroundFlag = false; this.thumbnailCopyIconFlag = false; this.thumbnailWidthApprox = 0; this.thumbnailHeightApprox = 0; this.productImportance = 0; this.compatibleProductIDsArr = new Array(); this.options = new Array(); this.productSwitcher = new ProductSwitcher(); this.vendorNamesArr = new Array(); this.vendorBasePrices = ""; this.vendorInitialSubtotals = ""; this.quantityBreaksBase = ""; this.quantityBreaksSubtotal = ""; this.vendorQuantityBreaksBase = ""; this.vendorQuantityBreaksSubtotal = ""; this.selectedOptionsObj = new SelectedOptions(); this.quantitySelectionArrCached = new Array();}
Product.prototype.getProductTitle = function()
{ return this.productTitle;}
Product.prototype.getProductTitleExt = function()
{ return this.productTitleExt;}
Product.prototype.getProductName = function()
{ return this.productName;}
Product.prototype.getArtworkWidthInches = function()
{ return this.artworkWidthInches;}
Product.prototype.getArtworkHeightInches = function()
{ return this.artworkHeightInches;}
Product.prototype.getArtworkBleedPicas = function()
{ return this.artworkBleedPicas;}
Product.prototype.isArtworkEditable = function()
{ return this.artworkIsEditable;}
Product.prototype.getPossibleArtworkSides = function()
{ return this.numberOfArtworkSides;}
Product.prototype.getProductID = function()
{ return this.productID;}
Product.prototype.isVariableData = function()
{ return this.variableDataFlag;}
Product.prototype.hasMailingServices = function()
{ return this.mailingServicesFlag;}
Product.prototype.getThumbnailWidthApprox = function()
{ return this.thumbnailWidthApprox;}
Product.prototype.getThumbnailHeightApprox = function()
{ return this.thumbnailHeightApprox;}
Product.prototype.hasThumnailBackground = function()
{ return this.thumbnailBackgroundFlag;}
Product.prototype.hasThumnailCopyIcon = function()
{ return this.thumbnailCopyIconFlag;}
Product.prototype.getProductImportance = function()
{ return this.productImportance;}
Product.prototype.getCompatibleProductIDsArr = function()
{ return this.compatibleProductIDsArr;}
Product.prototype.getProductSwitcherObj = function()
{ return this.productSwitcher;}
Product.prototype.getOptionNames = function()
{ var retArr = new Array(); for(var i=0; i<this.options.length; i++)
retArr.push(this.options[i].optionName); return retArr;}
Product.prototype.checkIfOptionIsEmpty = function(optName)
{ for(var i=0; i<this.options.length; i++)
{ if(optName == this.options[i].optionName)
{ if(this.options[i].choices.length == 0)
return true; else
return false;}
}
return true;}
Product.prototype.checkIfOptionHasSingleChoice = function(optName)
{ for(var i=0; i<this.options.length; i++)
{ if(optName == this.options[i].optionName)
{ if(this.options[i].choices.length == 1)
return true; else
return false;}
}
alert("Error in method checkIfOptionHasSingleChoice. The Option Name does not exist.");}
Product.prototype.checkIfOptionNameExist = function(optName)
{ for(var i=0; i<this.options.length; i++)
{ if(optName == this.options[i].optionName)
return true;}
return false;}
Product.prototype.checkIfOptionAndChoiceExist = function(optName, chcName)
{ for(var i=0; i<this.options.length; i++)
{ if(optName == this.options[i].optionName)
{ for(var x = 0; x < this.options[i].choices.length; x++)
{ if(chcName == this.options[i].choices[x].choiceName)
return true;}
}
}
return false;}
Product.prototype.getOptionDescription = function(optName)
{ for(var i=0; i<this.options.length; i++)
{ if(optName == this.options[i].optionName)
return this.options[i].optionDescription
}
alert("Error in method Product.getOptionDescription.\nThe Option Names does not exist: " + optName);}
Product.prototype.getOptionAlias = function(optName)
{ for(var i=0; i<this.options.length; i++)
{ if(optName == this.options[i].optionName)
return this.options[i].optionAlias
}
alert("Error in method Product.getOptionAlias.\nThe Option Names does not exist: " + optName);}
Product.prototype.isOptionDescriptionHTMLformat = function(optName)
{ for(var i=0; i<this.options.length; i++)
{ if(optName == this.options[i].optionName)
return this.options[i].optionDescriptionIsHTML
}
alert("Error in method Product.isOptionDescriptionHTMLformat.\nThe Option Names does not exist: " + optName);}
Product.prototype.doesOptionAffectNumOfArtworkSides = function(optName)
{ for(var i=0; i<this.options.length; i++)
{ if(optName == this.options[i].optionName)
return this.options[i].affectsArtworkSidesFlag
}
alert("Error in method Product.doesOptionAffectNumOfArtworkSides.\nThe Option Names does not exist: " + optName);}
Product.prototype.doesOptionAffectVariableImages = function(optName)
{ for(var i=0; i<this.options.length; i++)
{ if(optName == this.options[i].optionName)
return this.options[i].optionVariableImageController;}
alert("Error in method Product.doesOptionAffectVariableImages.\nThe Option Names does not exist: " + optName);}
Product.prototype.isOptionForAdministrators = function(optName)
{ for(var i=0; i<this.options.length; i++)
{ if(optName == this.options[i].optionName)
return this.options[i].adminOptionFlag;}
alert("Error in method Product.isOptionForAdministrators.\nThe Option Names does not exist: " + optName);}
Product.prototype.isOptionDiscountExempt = function(optName)
{ for(var i=0; i<this.options.length; i++)
{ if(optName == this.options[i].optionName)
return this.options[i].discountExempt;}
alert("Error in method Product.isOptionDiscountExempt.\nThe Option Names does not exist: " + optName);}
Product.prototype.getChoiceNamesForOption = function(optName)
{ for(var i=0; i<this.options.length; i++)
{ if(optName == this.options[i].optionName)
return this.options[i].getChoiceNames();}
alert("Error in method Product.getChoiceNamesForOption.\nThe Option Names does not exist: " + optName);}
Product.prototype.isChoiceDescriptionHTMLformat = function(optName, chcName)
{ for(var i=0; i<this.options.length; i++)
{ if(optName == this.options[i].optionName)
{ for(var x = 0; x < this.options[i].choices.length; x++)
{ if(chcName == this.options[i].choices[x].choiceName)
return this.options[i].choices[x].choiceDescriptionIsHTML;}
}
}
alert("Error in method Product.isChoiceDescriptionHTMLformat.\nThe Option Names does not exist: " + optName + ".\nOr the Choice Name does not exist: " + chcName);}
Product.prototype.getChoiceDescription = function(optName, chcName)
{ for(var i=0; i<this.options.length; i++)
{ if(optName == this.options[i].optionName)
{ for(var x = 0; x < this.options[i].choices.length; x++)
{ if(chcName == this.options[i].choices[x].choiceName)
return this.options[i].choices[x].choiceDescription;}
}
}
alert("Error in method Product.getChoiceDescription.\nThe Option Names does not exist: " + optName + ".\nOr the Choice Name does not exist: " + chcName);}
Product.prototype.getChoiceAlias = function(optName, chcName)
{ for(var i=0; i<this.options.length; i++)
{ if(optName == this.options[i].optionName)
{ for(var x = 0; x < this.options[i].choices.length; x++)
{ if(chcName == this.options[i].choices[x].choiceName)
return this.options[i].choices[x].choiceAlias;}
}
}
alert("Error in method Product.getChoiceAlias.\nThe Option Names does not exist: " + optName + ".\nOr the Choice Name does not exist: " + chcName);}
Product.prototype.isChoiceIsHidden = function(optName, chcName)
{ for(var i=0; i<this.options.length; i++)
{ if(optName == this.options[i].optionName)
{ for(var x = 0; x < this.options[i].choices.length; x++)
{ if(chcName == this.options[i].choices[x].choiceName)
return this.options[i].choices[x].choiceIsHiddenFlag;}
}
}
alert("Error in method Product.isChoiceIsHidden.\nThe Option Names does not exist: " + optName + ".\nOr the Choice Name does not exist: " + chcName);}
Product.prototype.choiceChangesNumberOfArtworkSidesTo = function(optName, chcName)
{ for(var i=0; i<this.options.length; i++)
{ if(optName == this.options[i].optionName)
{ for(var x = 0; x < this.options[i].choices.length; x++)
{ if(chcName == this.options[i].choices[x].choiceName)
return this.options[i].choices[x].changeArtworkSides;}
}
}
alert("Error in method Product.choiceChangesNumberOfArtworkSidesTo.\nThe Option Names does not exist: " + optName + ".\nOr the Choice Name does not exist: " + chcName);}
Product.prototype.getSelectedOptionsObj = function()
{ var selOptionsObj = new SelectedOptions(); var quanSelectArr = this.getQuantityChoicesArr(); if(quanSelectArr.length != 0)
selOptionsObj.setQuantity(quanSelectArr[0]); for(var i=0; i<this.options.length; i++)
{ if(this.options[i].choices.length == 0)
continue; selOptionsObj.setOptionChoice(this.options[i].optionName, this.options[i].choices[0].choiceName);}
return selOptionsObj;}
Product.prototype.getQuantityChoicesArr = function()
{ if(this.quantityBreaksSubtotal == "")
return new Array(); if(this.quantitySelectionArrCached.length != 0)
return this.quantitySelectionArrCached; var retArr = new Array()
var breaksArr = new Array(); breaksArr = this.quantityBreaksSubtotal.split("|"); for(var breakCounter=0; breakCounter<breaksArr.length; breakCounter++)
{ var quanPriceArr = new Array(); quanPriceArr = breaksArr[breakCounter].split("^"); if(quanPriceArr.length != 2)
{ alert("Problem in Product.getQuantityChoicesArr the Price Modifiation String is not in the proper format.: " + this.quantityBreaksSubtotal); return new Array(0);}
retArr[breakCounter] = quanPriceArr[0];}
this.quantitySelectionArrCached = retArr; return retArr;}
Product.prototype.getTotalWeight = function(selectedOptionsObj)
{ return this.getTotalWeightOverride(this.selectedOptionsObj);}
Product.prototype.getTotalWeightOverride = function(selectedOptionsObj)
{ if(!(selectedOptionsObj instanceof SelectedOptions))
{ alert("Error in Product.getTotalWeight. The parameter must be an Object of SelectedOptions"); return;}
var totalUnitWeight = parseFloat(this.unitWeight); var projectWeightChanges = 0; for(var i=0; i<this.options.length; i++)
{ if(this.options[i].choices.length == 0)
continue; var selectedChoiceName = selectedOptionsObj.getChoiceSelected(this.options[i].optionName); if(selectedChoiceName == null)
{ alert("Error in Product.Weight. The option Name was not found within the selection: " + this.options[i].optionName); return;}
for(var x=0; x<this.options[i].choices.length; x++)
{ if(this.options[i].choices[x].choiceName == selectedChoiceName)
{ totalUnitWeight += this.options[i].choices[x].getBaseWeightChange(); projectWeightChanges += this.options[i].choices[x].getProjectWeightChange(); break;}
}
}
return RoundWithTwoDecimals(totalUnitWeight * selectedOptionsObj.getSelectedQuantity() + projectWeightChanges);}
Product.prototype.getSubtotal = function()
{ return this.getSubtotalOverride(this.selectedOptionsObj);}
Product.prototype.getSelectedQuantity = function()
{ return this.selectedOptionsObj.getSelectedQuantity();}
Product.prototype.getChoiceSelected = function(optName)
{ var choiceSelected = this.selectedOptionsObj.getChoiceSelected(optName); if(choiceSelected == null){ alert("Error in Product.getChoiceSelected(). The Option Name does not exist: " + optName)
return null;}
return choiceSelected;}
Product.prototype.getSubtotalOverride = function(selectedOptionsObj)
{ if(!(selectedOptionsObj instanceof SelectedOptions))
{ alert("Error in Product.getSubtotal. The parameter must be an Object of SelectedOptions"); return;}
var totalBaseChanges = parseFloat(this.basePrice); var totalSubtotalChanges = parseFloat(this.initialSubtotal); totalBaseChanges += getLastPriceModification(selectedOptionsObj.getSelectedQuantity(), this.quantityBreaksBase); totalSubtotalChanges += getLastPriceModification(selectedOptionsObj.getSelectedQuantity(), this.quantityBreaksSubtotal); for(var i=0; i<this.options.length; i++){ if(this.options[i].choices.length == 0)
continue; var selectedChoiceName = selectedOptionsObj.getChoiceSelected(this.options[i].optionName); if(selectedChoiceName == null)
{ alert("Error in Product.getSubtotal. The option Name was not found within the selection: " + this.options[i].optionName); return;}
for(var x=0; x<this.options[i].choices.length; x++)
{ if(this.options[i].choices[x].choiceName == selectedChoiceName)
{ totalBaseChanges += this.options[i].choices[x].getBasePriceChange(selectedOptionsObj.getSelectedQuantity()); totalSubtotalChanges += this.options[i].choices[x].getSubtotalPriceChange(selectedOptionsObj.getSelectedQuantity()); break;}
}
}
var allOptionsAndChoicesFoundFlag = true; var optionNamesArr = selectedOptionsObj.getOptionNames(); for(var i=0; i<optionNamesArr.length; i++){ var thisOptionName = optionNamesArr[i]; var thisChoiceName = selectedOptionsObj.getChoiceSelected(thisOptionName); var thisOptionAndChoiceFound = false; for(var x=0; x<this.options.length; x++){ if(thisOptionAndChoiceFound)
continue; if(this.options[x].optionName == thisOptionName)
{ for(var j=0; j<this.options[x].choices.length; j++)
{ if(this.options[x].choices[j].choiceName == thisChoiceName)
{ thisOptionAndChoiceFound = true; break;}
}
}
}
if(!thisOptionAndChoiceFound)
allOptionsAndChoicesFoundFlag = false;}
if(!allOptionsAndChoicesFoundFlag)
return "N/A"; return RoundWithTwoDecimals(totalSubtotalChanges + totalBaseChanges * selectedOptionsObj.getSelectedQuantity());}
Product.prototype.getSubtotalExemptFromDiscounts = function(selectedOptionsObj)
{ if(!(selectedOptionsObj instanceof SelectedOptions))
{ alert("Error in Product.getSubtotalExemptFromDiscounts. The parameter must be an Object of SelectedOptions"); return;}
var returnAmnt = 0; for(var i=0; i<this.options.length; i++){ if(this.options[i].choices.length == 0)
continue; if(!this.options[i].discountExempt)
continue; var selectedChoiceName = selectedOptionsObj.getChoiceSelected(this.options[i].optionName); if(selectedChoiceName == null)
{ alert("Error in Product.getSubtotalExemptFromDiscounts. The option Name was not found within the selection: " + this.options[i].optionName); return;}
for(var x=0; x<this.options[i].choices.length; x++)
{ if(this.options[i].choices[x].choiceName == selectedChoiceName)
{ returnAmnt += this.options[i].choices[x].getBasePriceChange(selectedOptionsObj.getSelectedQuantity()); returnAmnt += this.options[i].choices[x].getSubtotalPriceChange(selectedOptionsObj.getSelectedQuantity()); break;}
}
}
return returnAmnt;}
Product.prototype.getVendorTotal = function(selectedOptionsObj, vendorNumber)
{ if(!(selectedOptionsObj instanceof SelectedOptions))
{ alert("Error in Product.getVendorTotal. The parameter must be an Object of SelectedOptions"); return;}
vendorNumber = parseInt(vendorNumber); if(vendorNumber < 1)
{ alert("Error in Product.getVendorTotal. The vendor number must start with 1."); return;}
var vendorBasePricesArr = this.vendorBasePrices.split("@"); if(vendorNumber > vendorBasePricesArr.length)
{ alert("Error in Product.getVendorTotal. The Vendor Number is out of Range for Base Prices."); return;}
var vendorInitialSubtotalsArr = this.vendorInitialSubtotals.split("@"); if(vendorNumber > vendorInitialSubtotalsArr.length)
{ alert("Error in Product.getVendorTotal. The Vendor Number is out of Range for Subtotals."); return;}
var totalBaseChanges = parseFloat(vendorBasePricesArr[(vendorNumber - 1)]); var totalSubtotalChanges = parseFloat(vendorInitialSubtotalsArr[(vendorNumber - 1)]); totalBaseChanges += getLastPriceModification(selectedOptionsObj.getSelectedQuantity(), this.vendorQuantityBreaksBase, vendorNumber); totalSubtotalChanges += getLastPriceModification(selectedOptionsObj.getSelectedQuantity(), this.vendorQuantityBreaksSubtotal, vendorNumber); for(var i=0; i<this.options.length; i++){ if(this.options[i].choices.length == 0)
continue; var selectedChoiceName = selectedOptionsObj.getChoiceSelected(this.options[i].optionName); if(selectedChoiceName == null)
{ alert("Error in Product.getVendorTotal. The option Name was not found within the selection: " + this.options[i].optionName); return;}
for(var x=0; x<this.options[i].choices.length; x++)
{ if(this.options[i].choices[x].choiceName == selectedChoiceName)
{ totalBaseChanges += this.options[i].choices[x].getVendorBasePriceChange(selectedOptionsObj.getSelectedQuantity(), vendorNumber); totalSubtotalChanges += this.options[i].choices[x].getVendorSubtotalPriceChange(selectedOptionsObj.getSelectedQuantity(), vendorNumber); break;}
}
}
return RoundWithTwoDecimals(totalSubtotalChanges + totalBaseChanges * selectedOptionsObj.getSelectedQuantity());}
function SelectedOptions()
{ this.optionsAndSelectionsArr = new Array(); this.quantitySelected = 0;}
SelectedOptions.prototype.getSelectedQuantity = function()
{ return this.quantitySelected;}
SelectedOptions.prototype.setQuantity = function(quantity)
{ this.quantitySelected = parseInt(quantity);}
SelectedOptions.prototype.getOptionNames = function()
{ var retArr = new Array(); for(var i=0; i<this.optionsAndSelectionsArr.length; i++)
retArr.push(this.optionsAndSelectionsArr[i].optionName); return retArr;}
SelectedOptions.prototype.getChoiceSelected = function(optName)
{ for(var i=0; i< this.optionsAndSelectionsArr.length; i++)
{ if(this.optionsAndSelectionsArr[i].optionName == optName)
return this.optionsAndSelectionsArr[i].choiceName
}
return null;}
SelectedOptions.prototype.setOptionChoice = function(optName, choiceName)
{ for(var i=0; i< this.optionsAndSelectionsArr.length; i++)
{ if(this.optionsAndSelectionsArr[i].optionName == optName)
{ this.optionsAndSelectionsArr[i].choiceName = choiceName; return;}
}
var newOptSelectObj = new OptionAndSelection(optName, choiceName); this.optionsAndSelectionsArr.push(newOptSelectObj);}
SelectedOptions.prototype.parseOptionsDescription = function(optDesc)
{ this.optionsAndSelectionsArr = new Array(); var optArr = optDesc.split(", "); for(var i=0; i< optArr.length; i++)
{ if(optArr[i] == "")
continue; var optChcPartsArr = optArr[i].split(" - "); if(optChcPartsArr.length < 2)
{ alert("Error parsing the Option/Choices. The format is invalid: " + optDesc); continue;}
var thisOptionName = optChcPartsArr[0]; var thisChoiceName = optChcPartsArr[1]; for(var j=2; j<optChcPartsArr.length; j++)
thisChoiceName += " - " + optChcPartsArr[j]; this.setOptionChoice(thisOptionName, thisChoiceName);}
}
SelectedOptions.prototype.getOptionAndChoicesStr = function()
{ var retStr = ""; var optionNamesArr = this.getOptionNames(); for(var i=0; i< optionNamesArr.length; i++)
{ if(retStr != "")
retStr += ", "; retStr += optionNamesArr[i] + " - " + this.getChoiceSelected(optionNamesArr[i]);}
return retStr;}
function OptionAndSelection(optName, selChoice)
{ this.optionName = optName; this.choiceName = selChoice
}
function ProductSwitcher()
{ this.projectSwitches = new Array();}
ProductSwitcher.prototype.getAllTargetProductIDs = function()
{ var retArr = new Array(); for(var i=0; i<this.projectSwitches.length; i++)
retArr.push(this.projectSwitches[i].productIDtarget); return retArr;}
ProductSwitcher.prototype.getProductIDsNotInGroup = function()
{ var retArr = new Array(); for(var i=0; i<this.projectSwitches.length; i++)
{ if(!this.projectSwitches[i].isGroupHead && this.projectSwitches[i].groupedToProductID == 0)
retArr.push(this.projectSwitches[i].productIDtarget);}
return retArr;}
ProductSwitcher.prototype.getProductIDsForGroupsHeads = function()
{ var retArr = new Array(); for(var i=0; i<this.projectSwitches.length; i++)
{ if(this.projectSwitches[i].isGroupHead)
retArr.push(this.projectSwitches[i].productIDtarget);}
return retArr;}
ProductSwitcher.prototype.getProductIDsWithLinksInsideOfGroupHeads = function(groupHeadProductID)
{ var retArr = new Array(); var productIDsInGroup = this.getProductsIDsInGroup(groupHeadProductID); for(var i=0; i<productIDsInGroup.length; i++)
{ if(this.checkIfLinkUsedElsewhere(productIDsInGroup[i]))
retArr.push(productIDsInGroup[i]);}
return retArr;}
ProductSwitcher.prototype.getProductIDsWithoutLinksInsideOfGroupHeads = function(groupHeadProductID)
{ var retArr = new Array(); var productIDsInGroup = this.getProductsIDsInGroup(groupHeadProductID); for(var i=0; i<productIDsInGroup.length; i++)
{ if(!this.checkIfLinkUsedElsewhere(productIDsInGroup[i]))
retArr.push(productIDsInGroup[i]);}
return retArr;}
ProductSwitcher.prototype.getProductsIDsInGroup = function(groupHeadProductID)
{ var retArr = new Array(); for(var i=0; i<this.projectSwitches.length; i++)
{ if(this.projectSwitches[i].groupedToProductID == groupHeadProductID)
retArr.push(this.projectSwitches[i].productIDtarget);}
if(retArr.length == 0)
{ alert("Error in method ProductSwitcher.getOtherProductsIDsInGroup. The Product ID of the Group head is not valid."); return;}
return retArr;}
ProductSwitcher.prototype.checkIfLinkUsedElsewhere = function(targetProdID)
{ var retArr = new Array(); var targetSwitchContainerObj = null; for(var i=0; i<this.projectSwitches.length; i++)
{ if(this.projectSwitches[i].productIDtarget == targetProdID)
{ targetSwitchContainerObj = this.projectSwitches[i]; break;}
}
if(targetSwitchContainerObj == null)
{ alert("Error in ProductSwitcher.checkIfLinkShouldBeUsedElsewhere.  The Product ID is not defined."); return;}
if(this.projectSwitches[i].description != "")
return false; if(parseInt(targetSwitchContainerObj.groupedToProductID) == 0 || targetSwitchContainerObj.isGroupHead)
return false; return true;}
ProductSwitcher.prototype.getDescription = function(targetProdID)
{ for(var i=0; i<this.projectSwitches.length; i++)
{ if(this.projectSwitches[i].productIDtarget == targetProdID)
return this.projectSwitches[i].description;}
alert("Error in method ProductSwitcher.getDescription. The Product ID was not found.");}
ProductSwitcher.prototype.getDescriptionForHTML = function(targetProdID)
{ var descHTML = this.getDescription(targetProdID); if(this.isDescriptionInHTMLformat(targetProdID))
return descHTML; else
return htmlize(descHTML);}
ProductSwitcher.prototype.isDescriptionInHTMLformat = function(targetProdID)
{ for(var i=0; i<this.projectSwitches.length; i++)
{ if(this.projectSwitches[i].productIDtarget == targetProdID)
return this.projectSwitches[i].descriptionIsHTML;}
alert("Error in method ProductSwitcher.isDescriptionInHTMLformat. The Product ID was not found.");}
ProductSwitcher.prototype.getLinkSubject = function(targetProdID)
{ for(var i=0; i<this.projectSwitches.length; i++)
{ if(this.projectSwitches[i].productIDtarget == targetProdID)
{ if(this.projectSwitches[i].linkSubject == "")
return this.getProductName(targetProdID); else
return this.projectSwitches[i].linkSubject;}
}
alert("Error in method ProductSwitcher.getLinkSubject. The Product ID was not found.");}
ProductSwitcher.prototype.getSwitchTitle = function(targetProdID)
{ for(var i=0; i<this.projectSwitches.length; i++)
{ if(this.projectSwitches[i].productIDtarget == targetProdID)
return this.projectSwitches[i].switchTitle;}
alert("Error in method ProductSwitcher.getSwitchTitle. The Product ID was not found.");}
ProductSwitcher.prototype.getProductName = function(targetProdID)
{ for(var i=0; i<this.projectSwitches.length; i++)
{ if(this.projectSwitches[i].productIDtarget == targetProdID)
return this.projectSwitches[i].productName;}
alert("Error in method ProductSwitcher.getProductName. The Product ID was not found.");}
function ProductSwitchContainer()
{ this.productIDtarget = 0; this.productName = "Product Name Undefined"; this.switchTitle = "Product Switch Title Undefined"; this.linkSubject = ""; this.description = ""; this.descriptionIsHTML = false; this.isGroupHead = false; this.groupedToProductID = 0;}
function ProductOption()
{ this.optionName = "Option Name Undefined"; this.optionAlias = "Option Alias Undefined."; this.optionDescription = "Option Description Undefined."; this.optionDescriptionIsHTML = false; this.optionVariableImageController = false; this.discountExempt = false; this.affectsArtworkSidesFlag = false; this.adminOptionFlag = false; this.choices = new Array();}
ProductOption.prototype.getChoiceNames = function()
{ var retArr = new Array(); for(var i=0; i<this.choices.length; i++)
retArr.push(this.choices[i].choiceName); return retArr;}
ProductOption.prototype.getChoiceObj = function(chcName)
{ var retArr = new Array(); for(var choiceCounter = 0; choiceCounter < this.choices.length; choiceCounter++)
{ if(chcName == this.choices[choiceCounter].choiceName)
return choices[choiceCounter];}
return null;}
function OptionChoice()
{ this.choiceName = "Choice Name Undefined"; this.choiceAlias = "Choice Alias Undefined"; this.choiceDescription = "Choice Description Undefined"; this.choiceDescriptionIsHTML = false; this.choiceIsHiddenFlag = false; this.choiceVariableImages = false; this.changeArtworkSides = 0; this.baseChange = 0; this.subtotalChange = 0; this.baseWeightChange = 0; this.projectWeightChange = 0; this.vendorBaseChanges = ""; this.vendorSubtotalChanges = ""; this.quantityBreaksBase = ""; this.quantityBreaksSubtotal = ""; this.vendorQuantityBreaksBase = ""; this.vendorQuantityBreaksSubtotal = "";}
OptionChoice.prototype.getBasePriceChange = function(quantityToCheck)
{ return getLastPriceModification(quantityToCheck, this.quantityBreaksBase) + parseFloat(this.baseChange);}
OptionChoice.prototype.getSubtotalPriceChange = function(quantityToCheck)
{ return getLastPriceModification(quantityToCheck, this.quantityBreaksSubtotal) + parseFloat(this.subtotalChange);}
OptionChoice.prototype.getVendorBasePriceChange = function(quantityToCheck, vendorNumber)
{ vendorNumber = parseInt(vendorNumber); var vendorBaseChangesArr = this.vendorBaseChanges.split("@"); if(vendorNumber < 1 || vendorNumber > vendorBaseChangesArr.length)
{ alert("Error in method OptionChoice.getVendorBasePriceChange. The Vendor Number is out of range: " + vendorNumber); return null;}
return getLastPriceModification(quantityToCheck, this.vendorQuantityBreaksBase, vendorNumber) + parseFloat(vendorBaseChangesArr[(vendorNumber -1)]);}
OptionChoice.prototype.getVendorSubtotalPriceChange = function(quantityToCheck, vendorNumber)
{ vendorNumber = parseInt(vendorNumber); var vendorSubtotalChangesArr = this.vendorSubtotalChanges.split("@"); if(vendorNumber < 1 || vendorNumber > vendorSubtotalChangesArr.length)
{ alert("Error in method OptionChoice.getVendorSubtotalPriceChange. The Vendor Number is out of range: " + vendorNumber); return null;}
return getLastPriceModification(quantityToCheck, this.vendorQuantityBreaksSubtotal, vendorNumber) + parseFloat(vendorSubtotalChangesArr[(vendorNumber -1)]);}
OptionChoice.prototype.getBaseWeightChange = function(){ return parseFloat(this.baseWeightChange);}
OptionChoice.prototype.getProjectWeightChange = function(){ return parseFloat(this.projectWeightChange);}
function ProductCollection(){ this.productIdsArr = new Array(); this.productTitlesArr = new Array(); this.productTitleExtArr = new Array(); this.apiBaseBath = "./"; this.prodCollectResponseEventsFunctionsArr = new Array(); this.prodCollectResponseEventsObjectsArr = new Array(); this.prodCollectCriticalErrorEventsFunctionsArr = new Array(); this.prodCollectCriticalErrorEventsObjectsArr = new Array();}
ProductCollection.prototype.setApiBasePath = function(pathStr)
{ this.apiBaseBath = pathStr;}
ProductCollection.prototype.attachProductCollectionResponseEvents = function(functionRef, objectRef)
{ this.prodCollectResponseEventsFunctionsArr.push(functionRef); this.prodCollectResponseEventsObjectsArr.push(objectRef);}
ProductCollection.prototype.attachProductCollectionCriticalErrorEvents = function(functionRef, objectRef)
{ this.prodCollectCriticalErrorEventsFunctionsArr.push(functionRef); this.prodCollectCriticalErrorEventsObjectsArr.push(objectRef);}
ProductCollection.prototype.getProductIdsArr = function()
{ return this.productIdsArr;}
ProductCollection.prototype.getProductTitleByProductID = function(productID)
{ for(var i=0; i<this.productIdsArr.length; i++){ if(this.productIdsArr[i] == productID){ return this.productTitlesArr[i];}
}
return "";}
ProductCollection.prototype.getProductTitleExtByProductID = function(productID)
{ for(var i=0; i<this.productIdsArr.length; i++){ if(this.productIdsArr[i] == productID){ return this.productTitleExtArr[i];}
}
return "";}
ProductCollection.prototype.getProductTitleWithExtByProductID = function(productID)
{ if(this.getProductTitleExtByProductID(productID) == "")
return this.getProductTitleByProductID(productID); return this.getProductTitleByProductID(productID) + " - " + this.getProductTitleExtByProductID(productID);}
ProductCollection.prototype.fetchProductList = function(productTypes)
{ this.productIdsArr = new Array(); this.productTitlesArr = new Array(); this.productTitleExtArr = new Array(); var instance = this; var numberOfMinutesToCacheResults = 15; var dateObj = new Date(); var dateCache = dateObj.getYear() + "-" + dateObj.getMonth() + "-" + dateObj.getDate() + "-" + dateObj.getHours() + "-" + (Math.ceil(parseInt(dateObj.getMinutes()) / numberOfMinutesToCacheResults)) ; var apiURL = this.apiBaseBath + "api_dot.php"; var apiParameters = "api_version=1.1&command=get_product_list&product_types="+productTypes+"&nocache=" + dateCache; var xmlHttpObjFromGlobalQueue = getXmlHttpObj("ProductCollectionXML"); if(xmlHttpObjFromGlobalQueue == null)
return; xmlHttpObjFromGlobalQueue.open('GET', (apiURL + "?" + apiParameters), true); xmlHttpObjFromGlobalQueue.onreadystatechange = function()
{ for(var t=0; t<xmlReqsObjArr.length; t++){ if(xmlReqsObjArr[t].reqObjFree || xmlReqsObjArr[t].reqObjParsing || xmlReqsObjArr[t].xmlHttpObj.readyState != 4 || xmlReqsObjArr[t].callBackFunctionName != "ProductCollectionXML")
continue; if(xmlReqsObjArr[t].xmlHttpObj.status == "200"){ xmlReqsObjArr[t].reqObjParsing = true; instance.parseXMLresponse(xmlReqsObjArr[t].xmlHttpObj.responseText);}
else{ instance.errorEventProductCollection_private(xmlReqsObjArr[t].xmlHttpObj.status, xmlReqsObjArr[t].xmlHttpObj.statusText);}
xmlReqsObjArr[t].reqObjFree = true; xmlReqsObjArr[t].reqObjParsing = false;}
}
xmlHttpObjFromGlobalQueue.send(null);}
ProductCollection.prototype.parseXMLresponse = function(xmlhttpresponseText)
{ if(!xmlhttpresponseText.match(/\<result\>OK\<\/result\>/))
{ var errorMessage = getErrorMessageFromAPIresponseXML(xmlhttpresponseText); if(errorMessage == ""){ this.errorEventProductCollection_private("5101", "Unknown Error Parsing XML Document");}
else{ this.errorEventProductCollection_private("5102", errorMessage);}
return;}
else{ var xmlLinesArr = xmlhttpresponseText.split("\n"); var productCounter = -1; for(var lineNo = 0; lineNo < xmlLinesArr.length; lineNo++)
{ if(xmlLinesArr[lineNo].toString().match(/\<product\>/))
productCounter++; else if(xmlLinesArr[lineNo].toString().match(/\<product_id\>/))
this.productIdsArr[productCounter] = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<product_title\>/))
this.productTitlesArr[productCounter] = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<product_title_ext\>/))
this.productTitleExtArr[productCounter] = getXMLdata(xmlLinesArr[lineNo]);}
for(var i=0; i < this.prodCollectResponseEventsFunctionsArr.length; i++){ this.prodCollectResponseEventsFunctionsArr[i].call(this.prodCollectResponseEventsObjectsArr[i]);}
}
}
ProductCollection.prototype.errorEventProductCollection_private = function(responseCode, responseError)
{ for(var i=0; i < this.prodCollectCriticalErrorEventsFunctionsArr.length; i++){ this.prodCollectCriticalErrorEventsFunctionsArr[i].call(this.prodCollectCriticalErrorEventsObjectsArr[i], responseCode, responseError);}
}
function SignIn(){ this.loginName = ""; this.loginPassword = ""; this.formSecCode = ""; this.rememberPasswordString = "no"; this.signInResponseEventsFunctionsArr = new Array(); this.signInResponseEventsObjectsArr = new Array(); this.signInCriticalErrorEventsFunctionsArr = new Array(); this.signInCriticalErrorEventsObjectsArr = new Array();}
SignIn.prototype.attachSignInResponseEvents = function(functionRef, objectRef)
{ this.signInResponseEventsFunctionsArr.push(functionRef); this.signInResponseEventsObjectsArr.push(objectRef);}
SignIn.prototype.attachSignInCriticalErrorEvents = function(functionRef, objectRef)
{ this.signInCriticalErrorEventsFunctionsArr.push(functionRef); this.signInCriticalErrorEventsObjectsArr.push(objectRef);}
SignIn.prototype.setLogin = function(userName, passwd, formSecurityCode, rememberPasswordFlag)
{ this.loginName = userName; this.loginPassword = passwd; this.formSecCode = formSecurityCode; if(rememberPasswordFlag)
this.rememberPasswordString = "yes"; else
this.rememberPasswordString = "no";}
SignIn.prototype.fireRequest = function()
{ var instance = this; var dateObj = new Date(); var apiURL = "./signin_xml.php"; var apiParameters = "api_version=1.1&email="+ this.loginName +"&pw=" + this.loginPassword + "&form_sc=" + this.formSecCode + "&nocache=" + dateObj.getTime() + "&rememberpassword=" + this.rememberPasswordString; var xmlHttpObjFromGlobalQueue = getXmlHttpObj("SignInXML"); if(xmlHttpObjFromGlobalQueue == null)
return; xmlHttpObjFromGlobalQueue.open('GET', (apiURL + "?" + apiParameters), true); xmlHttpObjFromGlobalQueue.onreadystatechange = function()
{ for(var t=0; t<xmlReqsObjArr.length; t++){ if(xmlReqsObjArr[t].reqObjFree || xmlReqsObjArr[t].reqObjParsing || xmlReqsObjArr[t].xmlHttpObj.readyState != 4 || xmlReqsObjArr[t].callBackFunctionName != "SignInXML")
continue; if(xmlReqsObjArr[t].xmlHttpObj.status == "200"){ xmlReqsObjArr[t].reqObjParsing = true; instance.parseXMLresponse(xmlReqsObjArr[t].xmlHttpObj.responseText);}
else{ instance.errorEventSignIn_private(xmlReqsObjArr[t].xmlHttpObj.status, xmlReqsObjArr[t].xmlHttpObj.statusText);}
xmlReqsObjArr[t].reqObjFree = true; xmlReqsObjArr[t].reqObjParsing = false;}
}
xmlHttpObjFromGlobalQueue.send(null);}
SignIn.prototype.parseXMLresponse = function(xmlhttpresponseText)
{ if(!xmlhttpresponseText.match(/\<success\>good\<\/success\>/) && !xmlhttpresponseText.match(/\<success\>bad\<\/success\>/))
{ this.errorEventSignIn_private("5401", "No response from server."); return;}
else{ if(xmlhttpresponseText.match(/\<success\>good\<\/success\>/))
var userIsLoggedInFlag = true; else
var userIsLoggedInFlag = false; var errorDescription = ""; if(!userIsLoggedInFlag){ var matchArr = xmlhttpresponseText.match(/\<description\>([^\<]+)\<\/description\>/); if(matchArr != null && matchArr.length == 2)
errorDescription = matchArr[1];}
for(var i=0; i < this.signInResponseEventsFunctionsArr.length; i++){ this.signInResponseEventsFunctionsArr[i].call(this.signInResponseEventsObjectsArr[i], userIsLoggedInFlag, errorDescription);}
}
}
SignIn.prototype.errorEventSignIn_private = function(responseCode, responseError)
{ for(var i=0; i < this.signInCriticalErrorEventsFunctionsArr.length; i++){ this.signInCriticalErrorEventsFunctionsArr[i].call(this.signInCriticalErrorEventsObjectsArr[i], responseCode, responseError);}
}
function ProjectArtwork(){ this.artworkSidesArr = new Array(); this.currentSide_private = -1; this.lastColorNodeType = ""; this.projectArtworkResponseEventsFunctionsArr = new Array(); this.projectArtworkResponseEventsObjectsArr = new Array(); this.projectArtworkCriticalErrorEventsFunctionsArr = new Array(); this.projectArtworkCriticalErrorEventsObjectsArr = new Array();}
ProjectArtwork.prototype.attachProjectArtworkDownloadedEvent = function(functionRef, objectRef)
{ this.projectArtworkResponseEventsFunctionsArr.push(functionRef); this.projectArtworkResponseEventsObjectsArr.push(objectRef);}
ProjectArtwork.prototype.attachProjectArtworkCriticalErrorEvent = function(functionRef, objectRef)
{ this.projectArtworkCriticalErrorEventsFunctionsArr.push(functionRef); this.projectArtworkCriticalErrorEventsObjectsArr.push(objectRef);}
ProjectArtwork.prototype.downloadArtwork = function(projectID, projectView)
{ var instance = this; var dateObj = new Date(); var apiURL = "./api_dot.php"; var apiParameters = "api_version=1.1&command=get_project_artwork&project_number=" + projectID + "&view_type=" + projectView + "&nocache=" + dateObj.getTime(); var xmlHttpObjFromGlobalQueue = getXmlHttpObj("ProjectArtworkXML"); if(xmlHttpObjFromGlobalQueue == null)
return; xmlHttpObjFromGlobalQueue.open('GET', (apiURL + "?" + apiParameters), true); xmlHttpObjFromGlobalQueue.onreadystatechange = function()
{ for(var t=0; t<xmlReqsObjArr.length; t++){ if(xmlReqsObjArr[t].reqObjFree || xmlReqsObjArr[t].reqObjParsing || xmlReqsObjArr[t].xmlHttpObj.readyState != 4 || xmlReqsObjArr[t].callBackFunctionName != "ProjectArtworkXML")
continue; if(xmlReqsObjArr[t].xmlHttpObj.status == "200"){ xmlReqsObjArr[t].reqObjParsing = true; instance.parseXMLresponse(xmlReqsObjArr[t].xmlHttpObj.responseText);}
else{ instance.errorEventProjectArtwork_private(xmlReqsObjArr[t].xmlHttpObj.status, xmlReqsObjArr[t].xmlHttpObj.statusText);}
xmlReqsObjArr[t].reqObjFree = true; xmlReqsObjArr[t].reqObjParsing = false;}
}
xmlHttpObjFromGlobalQueue.send(null);}
ProjectArtwork.prototype.parseXMLresponse = function(xmlhttpresponseText)
{ if(!xmlhttpresponseText.match(/\<result\>OK\<\/result\>/))
{ var errorMessage = getErrorMessageFromAPIresponseXML(xmlhttpresponseText); if(errorMessage == ""){ this.errorEventProjectArtwork_private("5121", "Unknown Error Parsing XML Document");}
else{ this.errorEventProjectArtwork_private("5122", errorMessage);}
return;}
var xmlLinesArr = xmlhttpresponseText.split("\n"); for(var lineNo = 0; lineNo < xmlLinesArr.length; lineNo++)
{ if(xmlLinesArr[lineNo].toString().match(/\<side\>/)){ this.currentSide_private++; this.artworkSidesArr[this.currentSide_private] = new ArtworkSide();}
else if(xmlLinesArr[lineNo].toString().match(/\<description\>/)){ this.artworkSidesArr[this.currentSide_private].sideDescription = getXMLdata(xmlLinesArr[lineNo]);}
else if(xmlLinesArr[lineNo].toString().match(/\<initialzoom\>/)){ this.artworkSidesArr[this.currentSide_private].intialZoom = getXMLdata(xmlLinesArr[lineNo]);}
else if(xmlLinesArr[lineNo].toString().match(/\<rotatecanvas\>/)){ this.artworkSidesArr[this.currentSide_private].rotateCanvas = getXMLdata(xmlLinesArr[lineNo]);}
else if(xmlLinesArr[lineNo].toString().match(/\<contentwidth\>/)){ this.artworkSidesArr[this.currentSide_private].contentWidth = getXMLdata(xmlLinesArr[lineNo]);}
else if(xmlLinesArr[lineNo].toString().match(/\<contentheight\>/)){ this.artworkSidesArr[this.currentSide_private].contentHeight = getXMLdata(xmlLinesArr[lineNo]);}
else if(xmlLinesArr[lineNo].toString().match(/\<backgroundimage\>/)){ this.artworkSidesArr[this.currentSide_private].backgroundImage = getXMLdata(xmlLinesArr[lineNo]);}
else if(xmlLinesArr[lineNo].toString().match(/\<background_x\>/)){ this.artworkSidesArr[this.currentSide_private].backgroundX = getXMLdata(xmlLinesArr[lineNo]);}
else if(xmlLinesArr[lineNo].toString().match(/\<background_y\>/)){ this.artworkSidesArr[this.currentSide_private].backgroundY = getXMLdata(xmlLinesArr[lineNo]);}
else if(xmlLinesArr[lineNo].toString().match(/\<background_width\>/)){ this.artworkSidesArr[this.currentSide_private].backgroundWidth = getXMLdata(xmlLinesArr[lineNo]);}
else if(xmlLinesArr[lineNo].toString().match(/\<background_height\>/)){ this.artworkSidesArr[this.currentSide_private].backgroundHeight = getXMLdata(xmlLinesArr[lineNo]);}
else if(xmlLinesArr[lineNo].toString().match(/\<background_color\>/)){ this.artworkSidesArr[this.currentSide_private].backgroundColor = getXMLdata(xmlLinesArr[lineNo]);}
else if(xmlLinesArr[lineNo].toString().match(/\<folds_horiz\>/)){ this.artworkSidesArr[this.currentSide_private].backgroundFoldsH = getXMLdata(xmlLinesArr[lineNo]);}
else if(xmlLinesArr[lineNo].toString().match(/\<folds_vert\>/)){ this.artworkSidesArr[this.currentSide_private].backgroundFoldsV = getXMLdata(xmlLinesArr[lineNo]);}
else if(xmlLinesArr[lineNo].toString().match(/\<scale\>/)){ this.artworkSidesArr[this.currentSide_private].scale = getXMLdata(xmlLinesArr[lineNo]);}
else if(xmlLinesArr[lineNo].toString().match(/\<dpi\>/)){ this.artworkSidesArr[this.currentSide_private].dpi = getXMLdata(xmlLinesArr[lineNo]);}
else if(xmlLinesArr[lineNo].toString().match(/\<color_palette\>/)){ this.lastColorNodeType = "color_palette";}
else if(xmlLinesArr[lineNo].toString().match(/\<color_definitions\>/)){ this.lastColorNodeType = "color_definitions";}
else if(xmlLinesArr[lineNo].toString().match(/\<layer\>/)){ this.lastColorNodeType = "layer";}
else if(xmlLinesArr[lineNo].toString().match(/\<color/)){ if(this.lastColorNodeType == "color_palette"){ this.artworkSidesArr[this.currentSide_private].colorPaletteEntryCounter++; var colorPalletteCounter = this.artworkSidesArr[this.currentSide_private].colorPaletteEntryCounter; this.artworkSidesArr[this.currentSide_private].colorPaletteEntries[colorPalletteCounter] = new ArtworkColorPalette(); this.artworkSidesArr[this.currentSide_private].colorPaletteEntries[colorPalletteCounter].colorDescription = getXMLdata(xmlLinesArr[lineNo]); this.artworkSidesArr[this.currentSide_private].colorPaletteEntries[colorPalletteCounter].colorCode = getXMLattribute(xmlLinesArr[lineNo], "colorcode");}
else if(this.lastColorNodeType == "color_definitions"){ this.artworkSidesArr[this.currentSide_private].colorDefinitionCounter++; var colorDefCounter = this.artworkSidesArr[this.currentSide_private].colorDefinitionCounter; this.artworkSidesArr[this.currentSide_private].colorDefinitions[colorDefCounter] = new ArtworkColorDefinition(); this.artworkSidesArr[this.currentSide_private].colorDefinitions[colorDefCounter].colorCode = getXMLdata(xmlLinesArr[lineNo]); this.artworkSidesArr[this.currentSide_private].colorDefinitions[colorDefCounter].colorID = getXMLattribute(xmlLinesArr[lineNo], "id");}
}
}
for(var i=0; i < this.projectArtworkResponseEventsFunctionsArr.length; i++){ this.projectArtworkResponseEventsFunctionsArr[i].call(this.projectArtworkResponseEventsObjectsArr[i]);}
}
ProjectArtwork.prototype.errorEventProjectArtwork_private = function(responseCode, responseError)
{ for(var i=0; i < this.projectArtworkCriticalErrorEventsFunctionsArr.length; i++){ this.projectArtworkCriticalErrorEventsFunctionsArr[i].call(this.projectArtworkCriticalErrorEventsObjectsArr[i], responseCode, responseError);}
}
function ArtworkSide(){ this.sideDescription = ""; this.intialZoom = ""; this.rotateCanvas = ""; this.contentWidth = ""; this.contentHeight = ""; this.backgroundImage = ""; this.backgroundX = ""; this.backgroundY = ""; this.backgroundWidth = ""; this.backgroundHeight = ""; this.backgroundColor = ""; this.backgroundFoldsH = ""; this.backgroundFoldsV = ""; this.scale = ""; this.dpi = ""; this.colorPaletteEntryCounter = -1; this.colorDefinitionCounter = -1; this.colorDefinitions = new Array(); this.colorPaletteEntries = new Array();}
ArtworkSide.prototype.getColorDescriptionFromColorId = function(colorID)
{ for(var i=0; i < this.colorDefinitions.length; i++){ if(this.colorDefinitions[i].colorID == colorID){ if(this.colorPaletteEntries.length == 0)
return this.colorDefinitions[i].colorCode; for(var j=0; j < this.colorPaletteEntries.length; j++){ if(this.colorDefinitions[i].colorCode == this.colorPaletteEntries[j].colorCode){ return this.colorPaletteEntries[j].colorDescription;}
}
}
}
return null;}
ArtworkSide.prototype.getAllSelectedColorDefinitions = function()
{ var returnArr = new Array(); for(var i=0; i < this.colorDefinitions.length; i++){ returnArr.push(this.getColorDescriptionFromColorId(this.colorDefinitions[i].colorID));}
return returnArr;}
function ArtworkColorDefinition(){ this.colorID = ""; this.colorCode = "";}
function ArtworkColorPalette(){ this.colorDescription = ""; this.colorCode = "";}
function ShoppingCart(){ this.projectIdsArr = new Array(); this.productIdsArr = new Array(); this.quantitiesArr = new Array(); this.savedProjectLinksArr = new Array(); this.shopCartResponseEventsFunctionsArr = new Array(); this.shopCartResponseEventsObjectsArr = new Array(); this.shopCartCriticalErrorEventsFunctionsArr = new Array(); this.shopCartCriticalErrorEventsObjectsArr = new Array();}
ShoppingCart.prototype.attachShoppingCartResponseEvents = function(functionRef, objectRef)
{ this.shopCartResponseEventsFunctionsArr.push(functionRef); this.shopCartResponseEventsObjectsArr.push(objectRef);}
ShoppingCart.prototype.attachShoppingCartCriticalErrorEvents = function(functionRef, objectRef)
{ this.shopCartCriticalErrorEventsFunctionsArr.push(functionRef); this.shopCartCriticalErrorEventsObjectsArr.push(objectRef);}
ShoppingCart.prototype.getProjectSessionIdsArr = function()
{ return this.projectIdsArr;}
ShoppingCart.prototype.getProductIdsInCartArr = function()
{ var returnArr = new Array(); for(var i=0; i<this.productIdsArr.length; i++){ var found = false; for(j=0; j<returnArr.length; j++){ if(returnArr[j] == this.productIdsArr[i]){ found = true;}
}
if(!found){ returnArr.push(this.productIdsArr[i])
}
}
return returnArr;}
ShoppingCart.prototype.getProductId = function(projectSessionId)
{ for(var i=0; i<this.projectIdsArr.length; i++){ if(this.projectIdsArr[i] == projectSessionId)
return this.productIdsArr[i];}
alert("Project ID not found getProductId");}
ShoppingCart.prototype.getQuantity = function(projectSessionId)
{ for(var i=0; i<this.projectIdsArr.length; i++){ if(this.projectIdsArr[i] == projectSessionId)
return this.quantitiesArr[i];}
alert("Project ID not found getQuantity");}
ShoppingCart.prototype.getSavedProjectLink = function(projectSessionId)
{ for(var i=0; i<this.projectIdsArr.length; i++){ if(this.projectIdsArr[i] == projectSessionId)
return this.savedProjectLinksArr[i];}
alert("Project ID not found getSavedProjectLink");}
ShoppingCart.prototype.fetchList = function()
{ this.projectIdsArr = new Array(); this.productIdsArr = new Array(); this.quantitiesArr = new Array(); this.savedProjectLinksArr = new Array(); var instance = this; var numberOfMinutesToCacheResults = 15; var dateObj = new Date(); var apiURL = "./api_dot.php"; var apiParameters = "api_version=1.1&command=get_projects_in_shoppingcart&nocache=" + dateObj.getTime(); var xmlHttpObjFromGlobalQueue = getXmlHttpObj("ShoppingCartXML"); if(xmlHttpObjFromGlobalQueue == null)
return; xmlHttpObjFromGlobalQueue.open('GET', (apiURL + "?" + apiParameters), true); xmlHttpObjFromGlobalQueue.onreadystatechange = function()
{ for(var t=0; t<xmlReqsObjArr.length; t++){ if(xmlReqsObjArr[t].reqObjFree || xmlReqsObjArr[t].reqObjParsing || xmlReqsObjArr[t].xmlHttpObj.readyState != 4 || xmlReqsObjArr[t].callBackFunctionName != "ShoppingCartXML")
continue; if(xmlReqsObjArr[t].xmlHttpObj.status == "200"){ xmlReqsObjArr[t].reqObjParsing = true; instance.parseXMLresponse(xmlReqsObjArr[t].xmlHttpObj.responseText);}
else{ instance.errorEventShoppingCart_private(xmlReqsObjArr[t].xmlHttpObj.status, xmlReqsObjArr[t].xmlHttpObj.statusText);}
xmlReqsObjArr[t].reqObjFree = true; xmlReqsObjArr[t].reqObjParsing = false;}
}
xmlHttpObjFromGlobalQueue.send(null);}
ShoppingCart.prototype.parseXMLresponse = function(xmlhttpresponseText)
{ if(!xmlhttpresponseText.match(/\<result\>OK\<\/result\>/))
{ var errorMessage = getErrorMessageFromAPIresponseXML(xmlhttpresponseText); if(errorMessage == ""){ this.errorEventShoppingCart_private("5161", "Unknown Error Parsing XML Document");}
else{ this.errorEventShoppingCart_private("5162", errorMessage);}
return;}
else{ var xmlLinesArr = xmlhttpresponseText.split("\n"); var projectCounter = -1; for(var lineNo = 0; lineNo < xmlLinesArr.length; lineNo++)
{ if(xmlLinesArr[lineNo].toString().match(/\<project\>/))
projectCounter++; else if(xmlLinesArr[lineNo].toString().match(/\<project_id\>/))
this.projectIdsArr[projectCounter] = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<product_id\>/))
this.productIdsArr[projectCounter] = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<quantity\>/))
this.quantitiesArr[projectCounter] = getXMLdata(xmlLinesArr[lineNo]); else if(xmlLinesArr[lineNo].toString().match(/\<saved_project_link_id\>/))
this.savedProjectLinksArr[projectCounter] = getXMLdata(xmlLinesArr[lineNo]);}
for(var i=0; i < this.shopCartResponseEventsFunctionsArr.length; i++){ this.shopCartResponseEventsFunctionsArr[i].call(this.shopCartResponseEventsObjectsArr[i]);}
}
}
ShoppingCart.prototype.errorEventShoppingCart_private = function(responseCode, responseError)
{ for(var i=0; i < this.shopCartCriticalErrorEventsFunctionsArr.length; i++){ this.shopCartCriticalErrorEventsFunctionsArr[i].call(this.shopCartCriticalErrorEventsObjectsArr[i], responseCode, responseError);}
}
function unEscapeHTMLcharsInString(str)
{ var escAmpRegEx = /&amp;/g; var escLtRegEx = /&lt;/g; var escGtRegEx = /&gt;/g; var quotRegEx = /&quot;/g; var aposRegEx = /&apos;/g; var aposEnRegEx = /&#039;/g; str = str.replace(escAmpRegEx, "&"); str = str.replace(escLtRegEx, "<"); str = str.replace(escGtRegEx, ">"); str = str.replace(quotRegEx, "\""); str = str.replace(aposRegEx, "'"); str = str.replace(aposEnRegEx, "'"); return str;}
function getXMLdata(xmltag)
{ xmltag = xmltag.replace(/(\s|\t|\r|\n)*\<\w+(\s+\w+=\s*(\"|\')(\w|\s|\d)*(\"|\'))*\s*\>/, ""); xmltag = xmltag.replace(/\<\/\w+\>(\s|\t|\r|\n)*/, ""); return unEscapeHTMLcharsInString(xmltag);}
function getXMLattribute(xmltag, attributeName)
{ var regexPattern = encodeRE(attributeName) + "\\s*=\\s*(\"|\')((\\w|\\s|\\d)*)(\"|\').*\\>.*<\\/\\w+\\>"; var re = new RegExp(regexPattern,"i"); matchesArr = xmltag.match(regexPattern); if(matchesArr == null){ return null;}
return unEscapeHTMLcharsInString(matchesArr[2]);}
function getErrorMessageFromAPIresponseXML(responseXMLtext)
{ var errorMessage = ""; var xmlLinesArr = responseXMLtext.split("\n"); for(var lineNo = 0; lineNo < xmlLinesArr.length; lineNo++)
{ if(xmlLinesArr[lineNo].toString().match(/\<error_message\>/))
{ errorMessage = getXMLdata(xmlLinesArr[lineNo]); break;}
}
return errorMessage;}
function getLastPriceModification(quantityToCheck, quantityBreaksStr, vendorNumber)
{ if(vendorNumber != null)
{ vendorNumber = parseInt(vendorNumber); if(vendorNumber < 1)
{ alert("Problem in function getLastPriceModification. The vendor number has to be greater or equal to 1."); return;}
}
quantityToCheck = parseInt(quantityToCheck); if(quantityBreaksStr == "" || quantityToCheck==0)
return 0; var breaksArr = new Array(); breaksArr = quantityBreaksStr.split("|"); var lastMatchedAmount = 0; for(var breakCounter=0; breakCounter<breaksArr.length; breakCounter++)
{ var quanPriceArr = new Array(); quanPriceArr = breaksArr[breakCounter].split("^"); if(quanPriceArr.length != 2)
{ alert("Problem in function getLastPriceModification for the string: " + quantityBreaks); return;}
var quanChk = parseInt(quanPriceArr[0]); if(vendorNumber == null)
{ var quanPrcChng = parseFloat(quanPriceArr[1]);}
else{ var vendorPriceStringArr = quanPriceArr[1].split("@"); if(vendorNumber > vendorPriceStringArr.length)
{ alert("Problem in function getLastPriceModification Vendor #" + vendorNumber + " does not have a definition within Quantity Break definition: " + quantityBreaks); return;}
var quanPrcChng = parseFloat(vendorPriceStringArr[(vendorNumber -1)]);}
if(quanChk <= quantityToCheck)
lastMatchedAmount = quanPrcChng;}
return lastMatchedAmount;}
function RoundWithTwoDecimals(n) { var s = "" + Math.round(n * 100) / 100; var i = s.indexOf('.'); if (i < 0){ return s + ".00";}
var t = s.substring(0, i + 1) + s.substring(i + 1, i + 3); if (i + 2 == s.length){ t += "0";}
return t;}
