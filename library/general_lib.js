

function RefreshParentWindow(){

	// Make sure that the parent window was not closed or something.
	if(typeof window.opener == 'undefined' && window.opener == null)
		return;

	var current_window_url = window.opener.location.toString();
	var new_window_url = "";

	dateObj = new Date();
	var NoCache = dateObj.getTime();

	if (current_window_url.match(/nocache=\d+/i)){
		new_window_url = current_window_url.replace(/nocache=\d+/i, ("nocache=" + NoCache));
	}
	else{
		if (!current_window_url.match(/\?/i))
			new_window_url = current_window_url + "?nocache=" + NoCache;
		else
			new_window_url = current_window_url + "&nocache=" + NoCache;
	}

	window.opener.location = new_window_url;

}


// Will encdoe a string to use with regular expression search terms.
// A lot of time the stuff you are searching FOR may be dynamic.
function encodeRE(s) 
{ 
	return s.replace(/([.*+?^${}()|[\]\/\\])/g, '\\$1');
} 

function htmlize(str)
{
        str = str.replace(/\&/g,"&amp;");
        str = str.replace(/\</g,"&lt;");
        str = str.replace(/\>/g,"&gt;");
        str = str.replace(/\"/g,"&quot;");
        return str;
}


function addslashes(str)
{
        str = str.replace(/\"/g,'\\\"');
        str = str.replace(/\'/g,"\\\'");
        return str;
}

function getUniqueArray(arrToBecomeUnique){

	var a = [], i;
	var l = arrToBecomeUnique.length;
	for( i=0; i<l; i++ ) {
		if( a.indexOf( arrToBecomeUnique[i], 0 ) < 0 ) { 
			a.push( arrToBecomeUnique[i] ); 
		}
	 }
	return a;
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

// This can be used like Add Commas, but with more functionality
// nStr: The number to be formatted, as a string or number. No validation is done, so don't input a formatted number. If inD is something other than a period, then nStr must be passed in as a string. 
// inD: The decimal character for the input, such as '.' for the number 100.2 
// outD: The decimal character for the output, such as ',' for the number 100,2 
// sep: The separator character for the output, such as ',' for the number 1,000.2 
// Examples
// addSeparatorsNF(43211234.56, '.', '.', ',')
// 43,211,234.56
// addSeparatorsNF('52093423.003', '.', ',', '.')
// 52.093.423,003
// addSeparatorsNF('584,567890', ',', '.', ',')
// 584.567890 
// addSeparatorsNF(-1.23e8, '.', '.', ',')
// -123,000,000

function addSeparatorsNF(nStr, inD, outD, sep)
{
	nStr += '';
	var dpos = nStr.indexOf(inD);
	var nStrEnd = '';
	if (dpos != -1) {
		nStrEnd = outD + nStr.substring(dpos + 1, nStr.length);
		nStr = nStr.substring(0, dpos);
	}
	var rgx = /(\d+)(\d{3})/;
	while (rgx.test(nStr)) {
		nStr = nStr.replace(rgx, '$1' + sep + '$2');
	}
	return nStr + nStrEnd;
}


function createCookie(name,value,days) {
	if (days) {
		var date = new Date();
		date.setTime(date.getTime()+(days*24*60*60*1000));
		var expires = "; expires="+date.toGMTString();
	}
	else var expires = "";
	document.cookie = name+"="+value+expires+"; path=/";
}

function readCookie(name) {
	var nameEQ = name + "=";
	var ca = document.cookie.split(';');
	for(var i=0;i < ca.length;i++) {
		var c = ca[i];
		while (c.charAt(0)==' ') c = c.substring(1,c.length);
		if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
	}
	return null;
}

function eraseCookie(name) {
	createCookie(name,"",-1);
}

function RoundWithDecimals(n) {
	var s = "" + Math.round(n * 100) / 100;
	var i = s.indexOf('.');
	if (i < 0)
		return s + ".00";
	var t = s.substring(0, i + 1) + s.substring(i + 1, i + 3);
	if (i + 2 == s.length)
		t += "0";
	return t;
}

function GetCheckedRadioValue(DomPath){
	
	var RadioObj = eval(DomPath);
	
	if(!RadioObj){
		alert("Error\n\nThe DOM path does not exist.\n\n" + DomPath);
	}

	if(typeof(RadioObj.length) == "undefined"){
		return RadioObj.value;
	}
	else{
		for(var i=0; i < (RadioObj.length); i++){
			if(RadioObj[i].checked == true)
				return RadioObj[i].value;
		}
	}
	
	return "";
}


