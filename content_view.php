<?

require_once("library/Boot_Session.php");






$dbCmd = new DbCmd();


// Set this variable.  The editing tool will check for it.  If we cant find it then the person might not have cookies enabled.
$HTTP_SESSION_VARS['initialized'] = 1;



$contentID = WebUtil::GetInput("contentID", FILTER_SANITIZE_STRING_ONE_LINE);

$contentType = WebUtil::GetInput("contentType", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);


if($contentType == "template"){
	$contentObj = new ContentTemplate($dbCmd);
	$contentObj->preferTemplateSize("big");
}
else if($contentType == "item"){
	$contentObj = new ContentItem($dbCmd);
}
else if($contentType == "category"){
	$contentObj = new ContentCategory($dbCmd);
}
else{
	throw new Exception("Illegal Content type Specified");
}



if(preg_match("/^\d+$/", $contentID)){
	
	// The only way you are permitted viewing a ContentID is when you belong to MEMBER.
	$notPermittedFlag = true;
	$passiveAuthObj = Authenticate::getPassiveAuthObject();
	if($passiveAuthObj->CheckIfBelongsToGroup("MEMBER"))
		$notPermittedFlag = false;

	if(!$contentObj->loadContentByID($contentID) || $notPermittedFlag){
		WebUtil::print404Error();
	}
	
}
else{

	
	// Do a 301 redirect in case someone has a Trailing Forward slash on the content title.
	// We don't want Duplicate content issues.  So http://www.domain.com/ci/Cheap+Business+Cards/ should get redirected to... http://www.domain.com/ci/Cheap+Business+Cards
	if(preg_match('!\/$!', $contentID) && strlen($contentID) > 1){
		$newUrlWithoutTrailingSlash = WebUtil::chopChar($_SERVER['REQUEST_URI']);
		header("Location: " . WebUtil::getFullyQualifiedDestinationURL($newUrlWithoutTrailingSlash), true, 301);
		exit;
	}

	if(!$contentObj->loadContentByTitle($contentID)){
		WebUtil::print404Error();
	}
	
	// Find out if there is a case sensitivity issue.
	// If so, do a 301 redirect content with case sensitivity.
	$actualTitle = $contentObj->getTitle();
	if($actualTitle != $contentID){
		$urlWithCaseProblem = WebUtil::chopChar($_SERVER['REQUEST_URI']);
		$fixedURL = preg_replace("/\\/[^\\/]*$/", "/", $urlWithCaseProblem) . urlencode($actualTitle);
		header("Location: " . WebUtil::getFullyQualifiedDestinationURL($fixedURL), true, 301);
		exit;
	}
	
	// Record to our Visitor Paths. Only put the Content Template titles into a "Sub Label"... because there are so many of them.
	if($contentType == "template"){
		VisitorPath::addRecord("Content Template", $contentID);
	}
	else if($contentType == "item"){
		VisitorPath::addRecord("Content Item: " . $contentID);
	}
	else if($contentType == "category"){
		VisitorPath::addRecord("Content Category: " . $contentID);
	}
	
	if(!$contentObj->checkIfActive() || !$contentObj->checkActiveParent()){
		WebUtil::print410Error();
	}
}

$t = new Templatex("","keep");

$t->set_file("origPage", "content-template.html");






// Build the HTML page
$contentHTML = "";



// If we are viewing a Content Template, then we want to have a link pointing back to our Content Item that holds it. and another link to customize the template.
if($contentType == "template"){

	$contentItemObj = new ContentItem($dbCmd);
	
	// Since $contentObj is a Content Template.... load up our New contentItemObj with the content Item ID stored in the Template object.
	$contentItemObj->loadContentByID($contentObj->getContentItemID());
	

	$contentHTML .= "<table width='100%' cellpadding='0' cellspacing='0'><tr><td><a class='BlueRedLinkLarge' href='" . $contentItemObj->getURLforContent() . "'>&lt; Back to <b>" . WebUtil::htmlOutput($contentItemObj->getTitle()) . "</b></a></td>\n<td align='right'><a class='BlueRedLinkLarge' href='" . htmlspecialchars($contentObj->getImageHyperlink()) . "'>Customize this Template!</a></td>\n</tr></table>\n<br/>\n";
}




// Only display the Page Title if their is not a Content Header HTML.  The reason is that the header should have a more customized page title.
if(!$contentObj->checkIfHeaderHTMLexists()){

	if($contentType == "template"){
		// For templates we want the content title to say "Business Card Template: Bla bla".
		// Otherwise the content title will be too weird and probably not friendly for the search engines.
		// Just previx the Content Items (parent) in front of the Content Tempalte title.
		$contentItemObj = new ContentItem($dbCmd);
		$contentItemObj->loadContentByID($contentObj->getContentItemID());
		
		$contentTitlePrefix = $contentItemObj->getTitle() . " Template: ";
	}
	else{
		$contentTitlePrefix = "";
	}

	
	$contentHTML .= "<h2>" . WebUtil::htmlOutput($contentTitlePrefix . $contentObj->getTitle()) . "</h2>\n\n\n";
}




// If an image is stored on this Content Item then it should appear at the top with some padding around it.
if($contentObj->checkIfImageStored()){

	$imageAlign = $contentObj->getImageAlign();
	$imageHyperlink = $contentObj->getImageHyperlink();
	
	if($imageAlign == "TR")
		$startImageTable = "<table cellpadding='4' cellspacing='0' align='right'><tr><td>";
	else if($imageAlign == "BR")
		$startImageTable = "<table cellpadding='4' cellspacing='0' align='right' valign='bottom'><tr><td>";
	else if($imageAlign == "BL")
		$startImageTable = "<table cellpadding='4' cellspacing='0' align='left' valign='bottom'><tr><td>";
	else
		$startImageTable = "<table cellpadding='4' cellspacing='0' align='left'><tr><td>";


	// Only Template Images have a border around them.
	if($contentType == "template")
		$borderStyle = " border='1' style='border-color:#000000' ";
	else
		$borderStyle = " border='0' ";


	$imageHTML = "<img " . $borderStyle . " src='". $contentObj->getURLforImage() . "' alt='' />";

	
	if(!empty($imageHyperlink))
		$imageHTML = "<a href='" . htmlspecialchars($imageHyperlink) . "'>" . $imageHTML . "</a>";
		
	
	$contentHTML .= $startImageTable . $imageHTML . "</td></tr></table>";
}





// Build Links with Heirchy
// If we are at the template level... then any links there take precedence over the Content Items links and above that... the Content Categories.
$linksArrFromContent = $contentObj->getLinksArr(true);
$linksArr = array();


// contains a list of subjects which are not meant to have links on it (within this content piece).

$contentPieceNoLinksDirectivesArr = array();

foreach($linksArrFromContent as $linkSubject => $linkURL){

	$linkDirectivesArr = $contentObj->getDirectivesFromLinkSubject($linkSubject);
	$linkSubjectOnly = $contentObj->getLinkSubjectWithoutDirectives($linkSubject);

	if(in_array("NOLINKS", $linkDirectivesArr)){
		$contentPieceNoLinksDirectivesArr[] = strtoupper($linkSubjectOnly);
		continue;
	}


	$linksArr[$linkSubjectOnly] = $linkURL;
}


if($contentType == "template"){

	// Get the content Item Above it.
	$contentItemObj = new ContentItem($dbCmd);
	$contentItemObj->loadContentByID($contentObj->getContentItemID());

	$contentItemLinksArr = $contentItemObj->getLinksArr(true, false, "NOLINKS");

	foreach($contentItemLinksArr as $linkSubject => $linkURL){

		$linkDirectivesArr = $contentItemObj->getDirectivesFromLinkSubject($linkSubject);
		$linkSubjectOnly = $contentItemObj->getLinkSubjectWithoutDirectives($linkSubject);

		// Find out if we are not supposed to bubble down the links into the Templates from Content Items
		if(in_array("NOTEMPLATES", $linkDirectivesArr))
			continue;

		if(in_array(strtoupper($linkSubjectOnly), $contentPieceNoLinksDirectivesArr))
			continue;	

		if(!in_array($linkSubjectOnly, array_keys($linksArr)))
			$linksArr[$linkSubjectOnly] = $linkURL;
	}

}

if($contentType == "template" || $contentType == "item"){


	// Get the "Content Category" Object up on top.
	$contentCategoryObj = new ContentCategory($dbCmd);
	$contentCategoryObj->loadContentByID($contentObj->getContentCategoryID());

	$contentCategoryLinksArr = $contentCategoryObj->getLinksArr(true, false, "NOLINKS");

	foreach($contentCategoryLinksArr as $linkSubject => $linkURL){

		$linkDirectivesArr = $contentCategoryObj->getDirectivesFromLinkSubject($linkSubject);
		$linkSubjectOnly = $contentCategoryObj->getLinkSubjectWithoutDirectives($linkSubject);

		if($contentType == "template" && in_array("NOTEMPLATES", $linkDirectivesArr))
			continue;

		if($contentType == "item" && in_array("NOITEMS", $linkDirectivesArr))
			continue;

		if(in_array(strtoupper($linkSubjectOnly), $contentPieceNoLinksDirectivesArr))
			continue;	

		if(!in_array($linkSubjectOnly, array_keys($linksArr)))
			$linksArr[$linkSubjectOnly] = $linkURL;
	}
}


$textDescription = $contentObj->getFormattedDescWithLinks($linksArr);

$contentHTML .=  wordwrap($textDescription, 150);


$contentHTML .= "<br/><br/>\n";



// Only "Content Items" show the Template and descriptions listed underneath
if($contentType == "item"){

	$contentTemplateIDs = ContentItem::GetContentTemplatesIDsWithin($dbCmd, $contentObj->getContentItemID());

	if(!empty($contentTemplateIDs)){

		$contentHTML .= "<br/>\n<table cellpadding='0' cellspacing='0' width='100%'><tr><td class='SmallBody' align='right'><font color='#993333'><b>Popular Templates</b></font></td>\n</tr>\n</table>\n<hr/>\n";


		$contentTemplateObj = new ContentTemplate($dbCmd);

		// Show thumbnail images for the templates.
		$contentTemplateObj->preferTemplateSize("small");

		// Loop through all of the Content ID's and create links to each Content Item
		foreach($contentTemplateIDs as $thisContentTemplateID){

			if(!$contentTemplateObj->loadContentByID($thisContentTemplateID))
				continue;

			$templateTitle = $contentTemplateObj->getTitle();

			// At one point Content Templates were not required to have templates.
			if(empty($templateTitle))
				continue;

			$templateHyperLink = $contentTemplateObj->getURLforContent();

			$templateTitle = "&nbsp;&nbsp;<a class='BlueRedLinkLarge' href='" . $templateHyperLink . "'><b>" . WebUtil::htmlOutput($templateTitle) . "</b></a>";


			$imageHtml = "<table cellpadding='5' align='left'><tr><td><a href='" . $templateHyperLink . "'><img border='1' style='border-color:#000000' src='". $contentTemplateObj->getURLforImage(false) . "' alt='' /></a></td></tr></table>";


			$contentHTML .= "<table width='100%'>\n<tr>\n<td class='SmallBody'>" . $templateTitle . "<br/>\n" . $imageHtml . "\n" . WebUtil::htmlOutput(wordwrap($contentTemplateObj->getShortDescription(), 120)) . "</td>\n</tr></table>";

		}
	}
}



$contentFooter = $contentObj->getFooter();

if(!empty($contentFooter)){

	$contentHTML .= "\n\n<br/><br/><hr/>\n";
	
	if($contentObj->footerIsHTMLformat()){
		$contentHTML .=  $contentFooter;
	}
	else{
		$contentHTML .=  WebUtil::htmlOutput($contentFooter);
	}
}



// If we are at PME... and the HTTP referrer is a search engine, then we want to put the PME home page SWF in the header in place of the header used in the content system.
$httpRefererStr = WebUtil::GetServerVar('HTTP_REFERER');

// Built a list of keywords that we will search for within the HTTP Referrer that will tell us if it is a real user - versus a crawler.
$referrerWordsNoCrawler = array("business", "google", "bing", "yahoo", "msn.com", "live.com", "ask.com");
$userIsCrawler = true;
foreach($referrerWordsNoCrawler as $thisPersonSearch){
	if(preg_match("/".preg_quote($thisPersonSearch)."/i", $httpRefererStr) && preg_match("/search/i", $httpRefererStr)){
		$userIsCrawler = false;
		break;
	}
}


if(Domain::getDomainKeyFromURL() == "PrintsMadeEasy.com" && !$userIsCrawler){
	$t->allowVariableToContainBrackets("CONTENT_HEADER_HTML");
	
	$pmeFlashHeader = '<object classid="clsid:d27cdb6e-ae6d-11cf-96b8-444553540000" codebase="https://fpdownload.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=7,0,0,0" width="760" height="510" id="HomePage" align="middle">
	<param name="allowScriptAccess" value="always" />
	<param name="movie" value="https://www.PrintsMadeEasy.com/HomePage.swf?BasePath=https%3A%2F%2Fwww.PrintsMadeEasy.com" /><param name="quality" value="best" /><param name="bgcolor" value="#ffffff" /><embed src="https://www.PrintsMadeEasy.com/HomePage.swf?BasePath=https%3A%2F%2Fwww.PrintsMadeEasy.com" quality="best" bgcolor="#ffffff" width="760" height="510" name="HomePage" align="middle" allowScriptAccess="always" type="application/x-shockwave-flash" pluginspage="http://www.macromedia.com/go/getflashplayer" />
	</object>
	';
	
	if($contentObj->getTitle() == "Free Business Cards"){
		$pmeFlashHeader = '<object classid="clsid:d27cdb6e-ae6d-11cf-96b8-444553540000" codebase="https://fpdownload.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=7,0,0,0" width="760" height="510" id="FreeBusinessCards" align="middle">
		<param name="allowScriptAccess" value="always" />
		<param name="movie" value="https://www.PrintsMadeEasy.com/FreeBusinessCards.swf?BasePath=https%3A%2F%2Fwww.PrintsMadeEasy.com" /><param name="quality" value="best" /><param name="bgcolor" value="#ffffff" /><embed src="https://www.PrintsMadeEasy.com/FreeBusinessCards.swf?BasePath=BasePath=https%3A%2F%2Fwww.PrintsMadeEasy.com" quality="best" bgcolor="#ffffff" width="760" height="510" name="FreeBusinessCards" align="middle" allowScriptAccess="always" type="application/x-shockwave-flash" pluginspage="https://www.macromedia.com/go/getflashplayer" />
		</object>
		<img src="/log.php?dest=blank&InitializeCoupon=FirstOneFree" width="1" height="1" alt="" />
		';
	}
	
	$pmeFlashHeader .= '
	<img src="./images/HomePage-BottomSeparator.png" width="760" height="26" /><br />
	
	<table width="100%" border="0" cellspacing="0" cellpadding="0" bgcolor="#e3e3e3" style="border:solid; border-width:1px; border-color:#ccccdd">
	  <tr>
	    <td width="33%" align="center"><img src="/images/CreditCartLogos.png" width="213" height="26" alt="Visa, Mastercard, Discover, and American Express" /></td>
	    <td width="22%" align="center"><img src="/images/Paypal-Accepted.jpg" width="60" height="38" alt="Paypal Accepted" /></td>
	    <td width="19%" align="center"><a href="http://www.la.bbb.org/Business-Report/Prints-Made-Easy-Inc-100033228" target="BBBwindow"><img src="/images/BBB-Member.png" width="40" height="42" alt="BBB Member" border="0" /></a></td>
	      <td width="26%" align="center"><img src="/images/ThawteLogo.png" width="97" height="30" alt="Secured by Thawte" border="0" /></a></td>
	
	  </tr>
	</table>
	<table border="0" cellpadding="0" cellspacing="0" width="760" style="background-image: url(http://www.PrintsMadeEasy.com/images_content/gradientBar.png); background-repeat: repeat;">
	<tr><td width="750" height="60" >&nbsp;</td></tr></table>
	
	<div id="templateSearch"></div>
	<h2 class="h2_3d" style="margin:0px; padding-top:0px;">The Ultimate Business Card Search Engine</h2>
	
	<div class="bodySection" style="position:relative">
	<p style=" margin-bottom:8px; margin-top:12px;">
	If you are searching for a particular design try using our business card search engine <br/>to browse through thousands of professional designs.</p>
	<form name="searchEngineForm" action="/templates.php" style="display:inline" method="get">
	<input type="hidden" name="productid" value="73" />
	<input type="text" name="keywords" id="keywordsBox" value="" style="width:220px; background-color:#FFFFcc; margin-right:7px; font-size:16px;" align="middle" />
	<input type="image" src="/images/searchTempates-u.png" onmouseover="this.src=\'/images/searchTempates-d.png\';" onmouseout="this.src=\'/images/searchTempates-u.png\'" align="middle" alt="Search for Business Cards" />
	</form>
	</div>
	<br><br>
	';
	
	if($contentObj->checkIfHeaderHTMLexists()){
		$pmeFlashHeader .= '
		<br><br><br><h2 class="h2_3d">' . WebUtil::htmlOutput($contentObj->getTitle()) . '</h2>
		<table border="0" cellpadding="0" cellspacing="0" width="760" style="background-image: url(http://www.PrintsMadeEasy.com/images_content/gradientBar.png); background-repeat: repeat;">
		<tr><td width="750" height="60" >&nbsp;</td></tr></table>
		';  
	}

	$t->set_var("CONTENT_HEADER_HTML", $pmeFlashHeader);
}
else if($contentObj->checkIfHeaderHTMLexists()){
	$t->set_var("CONTENT_HEADER_HTML", $contentObj->getHeaderHTMLwithLinks($linksArr));
}
else{
	$t->set_var("CONTENT_HEADER_HTML", "");
}



$t->set_var("CONTENT", $contentHTML);
$t->allowVariableToContainBrackets("CONTENT");

if($contentType == "template"){
	
	// For templates we want the content title to say "Business Card Template: Bla bla".
	// Otherwise the content title will be too weird and probably not friendly for the search engines.
	// Just previx the Content Items (parent) in front of the Content Tempalte title.
	$contentItemObj = new ContentItem($dbCmd);
	$contentItemObj->loadContentByID($contentObj->getContentItemID());
	
	$contentTitlePrefix = $contentItemObj->getTitle() . " Template: ";

	$t->set_var("CONTENT_TITLE", WebUtil::htmlOutput($contentTitlePrefix . $contentObj->getTitle()));
	$t->set_var("META_DESCRIPTION", "");
}
else if($contentType == "item"){
	$t->set_var("CONTENT_TITLE", WebUtil::htmlOutput($contentObj->getMetaTitle()));
	$t->set_var("META_DESCRIPTION", WebUtil::htmlOutput($contentObj->getMetaDescription()));
}
else{
	$t->set_var("CONTENT_TITLE", WebUtil::htmlOutput($contentObj->getTitle()));
	$t->set_var("META_DESCRIPTION", "");
}


$t->set_var("CONTENT_DESC", WebUtil::htmlOutput($contentObj->getTitle()));


if($contentType == "item"){
	$t->set_var("KEYWORDS_DASHED", urlencode(preg_replace("/\s+/", "-", $contentObj->getTitle())));
}




$htmlTemplate = $t->finish($t->parse("OUT","origPage"));


$websiteUrlForDomain = Domain::getWebsiteURLforDomainID(Domain::oneDomain());

// Anywhere we find a "./" in the template... replace that with the absolute path of the website.
// There is no telling where this content page may be loaded (due to the .htaccess)
$htmlTemplate = preg_replace("/\.\//", "http://$websiteUrlForDomain/", $htmlTemplate);






header('Last-Modified: '. gmdate('D, d M Y H:i:s', $contentObj->getDateLastModified()) . ' GMT');

print $htmlTemplate;

?>