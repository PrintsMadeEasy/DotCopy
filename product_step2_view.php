<?


require_once("library/Boot_Session.php");


//These session variables should have been set before the Flash object requested info from this script.
$T_NavView = WebUtil::GetSessionVar("Template_NavView");
$T_StarUp = WebUtil::GetSessionVar("Template_StarUp");
$T_Kwds = WebUtil::GetSessionVar("Template_Kwds");
$T_Matches = WebUtil::GetSessionVar("Template_Matches");
$T_Offset = WebUtil::GetSessionVar("Template_SearchOffset");

// Close the session lock as soon as possible.
session_write_close();

// Certain Keywords may trigger a co-branding message to appear in the Flash header.
// Basically give our Flash APP an SWF file to attach.
if(strtolower($T_Kwds) == "rejection-hotline"){
	$swfOnTop = "TemplateBrand-RejectionHotline.swf";
	$T_StarUp = "Template_StarUp";
}
else{
	$swfOnTop = "";
}

// The keyword can be sticky... even if we switch to a different tab.
// Make sure that the only time the co-branding occurs ... is when it is on top of the search engine.
if(strtolower($T_NavView) != "engine"){
	$swfOnTop = "";
}


//Send back the XML file
$artworkXMLfile = "<?xml version=\"1.0\" ?>\n<response><navview>".WebUtil::htmlOutput($T_NavView)."</navview><startup>".WebUtil::htmlOutput($T_StarUp)."</startup><keywords>".WebUtil::htmlOutput($T_Kwds)."</keywords><matches>".WebUtil::htmlOutput($T_Matches)."</matches><searchoffset>".WebUtil::htmlOutput($T_Offset)."</searchoffset><SWFforTop>".WebUtil::htmlOutput($swfOnTop)."</SWFforTop></response>";
		


header ("Content-Type: text/xml");
print $artworkXMLfile;

