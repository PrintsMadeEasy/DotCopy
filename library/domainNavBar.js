
	// Used to Hide the the DHTML window.
	function hideTip(divNameString){
		var innertDiv = document.getElementById(divNameString);
		innertDiv.style.position = "absolute";
		innertDiv.style.display = "none";
	}
	
	
	
	// Makes DHTML window visible.
	function showTip(divNameString){
	
		setTimeout("displayTip('" + divNameString + "')", 400);

		
	}
	
	// Make Global Variable to tell if the user has rolled off of the Tip icon before the timeout
	var globalTipMouseAlreadyLeftFlag = false;
	
	function displayTip(divNameString){
		if(!globalTipMouseAlreadyLeftFlag)
			document.getElementById(divNameString).style.display = 'block';
	}
	
	
	// Only change the Source on the Iframe, in case someone opens and closes the Nav, for performance.
	// Also you don't want to run the URL if the user never opens the pop-up window.
	var windowHasAlreadyBeenOpened = false;
	
	
	// Toggles the Domain Selector window open and close with a smooth transition.
	function toggleDomainSelector(e){
	
		// In case something else ever gets linked to the same event.
		e.stop();
		
	
		if(!windowHasAlreadyBeenOpened){
			showDomainWindowEyeCandy();
			document.getElementById("domainSelectorIframe").src = "./ad_domainSelectPopup.php";
			windowHasAlreadyBeenOpened = true;
		}
	
		// Create Objects of the Mootools.net for the transitions.
		var domainSelecterDivContainer = $('div_domainOuter');
		var myVerticalSlide = new Fx.Slide('div_domainInner');
		
		// When Vertical Slide ends its transition, we check for its status
		myVerticalSlide.addEvent('complete', window.domainSlideEventFinished);
		
		
		if(!window.domainSelectorIsOpenFlag){
		
			document.getElementById("div_domainOuter").style.display = 'block';
			myVerticalSlide.slideIn();
			domainSelecterDivContainer.fade(1);
		}
		else{
			domainSelecterDivContainer.fade(0);
			myVerticalSlide.slideOut();
		}
	}
	

	// When the domain door has either finished openeing or closing this should get called.
	function domainSlideEventFinished() {
	
	
		if(window.domainSelectorIsOpenFlag){
			hideTip("div_domainOuter");
			window.domainSelectorIsOpenFlag = false;
		}
		else{
			window.domainSelectorIsOpenFlag = true;
		}
	}
	

	// Start off with All of our Tool Tips and the Domain Selector hidden.
	hideTip("helpDiv_searchForUsers");
	hideTip("div_domainOuter");
	
	var domainSelectorIsOpenFlag = false;

	// This runs one time, like the "onLoad" function.
	// We don't attach to the Click Events until the whole page has loaded.  That will keep people from doing any harm until the browser is ready.
	window.addEvent('domready', function() {
	
		// I think this is needed for the MooTools library?
		var status = {
			'true': 'open',
			'false': 'close'
		};
		
		document.getElementById("domainSelecterOuterShell").style.visibility = "visible";
		document.getElementById("tipNameOuterShell").style.visibility = "visible";
		
		document.getElementById("domainSelecterOuterShell").style.position = "relative";
		document.getElementById("tipNameOuterShell").style.position = "relative";
		
		var domainSelecterDivContainer = $('div_domainOuter');
		var myVerticalSlide = new Fx.Slide('div_domainInner');
		
		
		// Start off hidden and transparent
		domainSelecterDivContainer.fade(0);
		myVerticalSlide.hide();
		
		// Both clicking on the Domain Logo or the "Close Icon" will do the same thing, "toggle off"
		// If someone only has access to one domain then these buttons will not exist in the DOM.
		try{
			$('domainLink').addEvent('click', window.toggleDomainSelector);
			$('domainCloseIcon').addEvent('click', window.toggleDomainSelector);
			$('selectAllDomains').addEvent('click', window.selectAllDomainsInFrame);
			$('clearAllDomains').addEvent('click', window.clearAllDomainsInFrame);
			$('saveDomainIDs').addEvent('click', window.saveSelectedDomains);
		}
		catch(e){}
		
		
		
		// Not everyone can see the More Button... so make sure it exists before attaching an event to it.
		if(typeof(document.all.domainMoreIcon) != "undefined")
			$('domainMoreIcon').addEvent('click', window.showMoreDetailsOnDomain);

	});
	
	
	function ajaxRequest(url, vars, callbackFunction) {
	  var request =  new XMLHttpRequest();
	  request.open("POST", url, true);
	  request.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
	 
	  request.onreadystatechange = function() {
	    if (request.readyState == 4 && request.status == 200) {
	      if (request.responseText) {
	        callbackFunction(request.responseText);
	      }
	    }
	  };
	  request.send(vars);
	}

	
	function saveSelectedDomains(){

		var domainIDsArr = top.frames["domainSelectorIframe"].getSelectedDomainIDs();
		
		if(domainIDsArr.length == 0){
			alert("You must choose at least one Domain.");
			return;
		}
		
		// Build a pip-delimited list of all Domains that have been selected.
		var domainIDstr = "";
		for(var i=0; i<domainIDsArr.length; i++)
			domainIDstr += domainIDsArr[i] + "|";
			
		
		showDomainWindowEyeCandy();
		setTimeout('postDomainIDsToServer("' + domainIDstr + '")', 400);
			
	}
	
	function postDomainIDsToServer(domainIDstr){
		ajaxRequest("ad_actions.php", ("action=saveDomains&domainList=" + domainIDstr + "&returl=" + escape(document.location)), eventFinishedSavingDomainIDs);
	}
	
	function eventFinishedSavingDomainIDs(responseText){
		
		if(responseText != "OK")
			alert("Error saving Domain IDs");
		else
			window.location.reload();
	}
	
	// When someone clicks on the "More" button we want to change the URL inside of the iframe and expand the width of the selector window.
	function showMoreDetailsOnDomain(){
		
		showDomainWindowEyeCandy();
		
		$('domainMoreIcon').fade('out');
		
		// Make sure the eye candy stays long enough so that it doesn't look like a flicker.
		setTimeout('beginTransitionToMoreDetails()', 500);
		
	}
	
	function beginTransitionToMoreDetails(){
		
		// Pass in another parameter to the source of the iframe to make it show extended status.
		changeIframeURL("./ad_domainSelectPopup.php?view=extended");
		
		$('div_domainOuter').tween('width', '670px');

	}
	
	// Call this before loading a new URL in Iframe for domain selection.
	function showDomainWindowEyeCandy(){
		
		var htmlForEyecandy = "<html><body bgcolor='#D2D8DF'><div align='center'><img src='./images/thinking_blue.gif'>";

		htmlForEyecandy += '<br></div></body></html>';

		top.frames["domainSelectorIframe"].document.body.innerHTML = htmlForEyecandy;

	}
	
	
	// So that people can view the Front-end of the website by clicking on a link in the NavBar Window.
	function ShowDomainInNewWindow(domainURL){
		newWindow = window.open(("http://" + domainURL), "domainWindow", "height=800,width=860,directories=no,location=yes,menubar=yes,scrollbars=yes,status=yes,toolbar=yes,resizable=yes");
		newWindow.focus();
	}
	
	function changeDomainTimeFrame(timeFrame){
		showDomainWindowEyeCandy();
		setTimeout('changeIframeURL("./ad_domainSelectPopup.php?PeriodType=TIMEFRAME&view=extended&TimeFrame=' + timeFrame + '")', 400);
	}
	
	function changeDomainDateRange(startDay, startMonth, startYear, endDay, endMonth, endYear){
		showDomainWindowEyeCandy();
		setTimeout('changeIframeURL("./ad_domainSelectPopup.php?PeriodType=DATERANGE&view=extended&DateRangeStartDay=' + startDay + '&DateRangeStartMonth=' + startMonth + '&DateRangeStartYear=' + startYear + '&DateRangeEndDay=' + endDay + '&DateRangeEndMonth=' + endMonth + '&DateRangeEndYear=' + endYear + '")', 400);
	}
	
	function changeIframeURL(newURL){
		document.getElementById("domainSelectorIframe").src = newURL;
	}
	
	function selectAllDomainsInFrame(){
		top.frames["domainSelectorIframe"].checkAll();
		
	}
	function clearAllDomainsInFrame(){
		
		top.frames["domainSelectorIframe"].checkNone();
	}
	
	function displayNumberOfDomainsSelected(){
		
		var numberOfDomainsSelected = top.frames["domainSelectorIframe"].getNumberDomainsSelected();
		var numberOfDomainsTotal = top.frames["domainSelectorIframe"].getTotalNumberOfDomains();
	
		document.getElementById("domainsSelectedDescription").innerHTML = "<img align='absmiddle' src='./images/transparent.gif' border='0' width='20' height='5'>" + numberOfDomainsSelected + "/" + numberOfDomainsTotal;
	}
	
	function displayDomainTotals(){
		
		var totalsProjects = top.frames["domainSelectorIframe"].getTotalDomainTotals("projectCount");
		var totalsOrders = top.frames["domainSelectorIframe"].getTotalDomainTotals("orderCount");
		var totalsRevenue = top.frames["domainSelectorIframe"].getTotalDomainTotals("revenue");
		var totalsSubTprofit = top.frames["domainSelectorIframe"].getTotalDomainTotals("subTotalProfit");
		
		document.getElementById("domainsTotals").innerHTML = "Projects: " + totalsProjects + "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Orders: " + totalsOrders + "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Revenue: <font color='#0000aa' style='font-size:12px; font-weight:bold'>$" + addCommas(totalsRevenue) + "</font>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Profit: <font color='#004400' style='font-size:12px; font-weight:bold'>$" + addCommas(totalsSubTprofit) + "</font>";
		
	}

	function addCommas(nStr)
	{
		nStr += '';
		x = nStr.split('.');
		x1 = x[0];
		x2 = x.length > 1 ? '.' + x[1] : '';
		var rgx = /(\d+)(\d{3})/;
		while (rgx.test(x1)) {
			x1 = x1.replace(rgx, '$1' + ',' + '$2');
		}
		return x1 + x2;
	}



