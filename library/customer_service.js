

function SendCustomerWebMail(ThreadID){

	newWindow45 = window.open("./ad_cs_email.php?csthreadid=" + ThreadID, "customeremail", "height=590,width=700,directories=no,location=no,menubar=no,scrollbars=no,status=no,toolbar=no,resizable=no");
	newWindow45.focus();

}

function ReAssignCSitem(ThreadID){

	AddressURL = "./ad_cs_reassign.php?threadid=" + ThreadID;

	newWindow45 = window.open(AddressURL, "reassign", "height=125,width=375,directories=no,location=no,menubar=no,scrollbars=no,status=no,toolbar=no,resizable=no");
	newWindow45.focus();
}

function DisplayAttachment(FileName){
	AddressURL = "./customer_attachments/" + FileName;

	newWindow45 = window.open(AddressURL, "attachmentcustomer", "height=395,width=375,directories=no,location=no,menubar=no,scrollbars=yes,status=no,toolbar=no,resizable=yes");
	newWindow45.focus();
}

function CloseCSitem(csItemID, ReturnURL, FormSecurityCode){

	if(confirm('Are you sure you want to close this?')){
		var URL = "./ad_actions.php?action=csitem_close&csitemid=" + csItemID + "&returl=" + ReturnURL + "&form_sc=" + FormSecurityCode;
		document.location= URL;
	}
}

function CloseAsJunk(CSItemID, ReturnURL, FormSecurityCode){
	if(confirm("Are you sure that you want to permanently delete this message?")){
		document.location = "./ad_actions.php?returl=" + ReturnURL + "&action=closeasjunk&csitemid=" + CSItemID + "&form_sc=" + FormSecurityCode;
	}
}

function CSitemNeedsAssistance(CSItemID, ReturnURL, FormSecurityCode){

	if(confirm("This customer service item will be elevated to \"Needs Assistance\"")){
		document.location = "./ad_actions.php?returl=" + ReturnURL + "&action=csitem_needsassistance&csitemid=" + CSItemID + "&form_sc=" + FormSecurityCode;
	}
}



var CsItemZindexCouter = 5;


function showCSactivityPeriodsDHTML(CsItemNumber, divShow) {

	var spanname = "csActivityLayer" + CsItemNumber;
	var divname = "csActivityDiv" + CsItemNumber;

	if (divShow) {
		CsItemZindexCouter++;
		CsItemZindexCouter++;

		document.all(spanname).style.visibility = "visible";
		document.all(spanname).style.zIndex = CsItemZindexCouter;
		document.all(divname).style.zIndex = CsItemZindexCouter + 2;
	}
	else{
		document.all(spanname).style.visibility = "hidden";
	}

}
