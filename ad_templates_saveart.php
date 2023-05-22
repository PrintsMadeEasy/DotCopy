<?

require_once("library/Boot_Session.php");


$templateid = WebUtil::GetInput("templateid", FILTER_SANITIZE_INT);
$editorview = WebUtil::GetInput("editorview", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$categorytemplate = WebUtil::GetInput("categorytemplate", FILTER_SANITIZE_INT);
$searchkeywords = WebUtil::GetInput("searchkeywords", FILTER_SANITIZE_STRING_ONE_LINE);
$closwindowafter = WebUtil::GetInput("closwindowafter", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$offset = WebUtil::GetInput("offset", FILTER_SANITIZE_INT);

set_time_limit(300);



$dbCmd = new DbCmd();


WebUtil::checkFormSecurityCode();


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!isset($HTTP_SESSION_VARS['draw_xml_document']))
	WebUtil::PrintAdminError("Your session may have expired.");


// Update the XML file within the table
// The session variable $HTTP_SESSION_VARS['draw_xml_document'] was set by the flash program before it gets to this screen.
ArtworkLib::SaveArtXMLfile($dbCmd, $editorview, $templateid, $HTTP_SESSION_VARS['draw_xml_document']);


// This will move any uploaded images from the "session" table into the "template" table
ArtworkLib::SaveImagesInSession($dbCmd, $editorview, $templateid, ImageLib::GetImagesTemplateTableName($dbCmd), ImageLib::GetVectorImagesTemplateTableName($dbCmd));

//Will create the thumbnail image and Preview image for customers
ThumbImages::CreateTemplatePreviewImages($dbCmd, $editorview, $templateid);


// Find the product ID belonging to the Template ID so that we can do a redirect.
$productid = ProjectBase::GetProductIDFromProjectRecord($dbCmd, $templateid, $editorview);


if(!empty($closwindowafter)){
	print "<html><script>self.close();</script></html>";
}
else{
	//Send them back to the page they were looking at before viewing the flash file
	header("Location: " . WebUtil::FilterURL("./ad_templates.php?productid=$productid&categorytemplate=$categorytemplate&templateview=$editorview&offset=$offset&searchkeywords=" . urlencode($searchkeywords)));
}

?>