<?


require_once("library/Boot_Session.php");


$keywords = WebUtil::GetInput("keywords", FILTER_SANITIZE_STRING_ONE_LINE);
$keywords_and = WebUtil::GetInput("keywords_and", FILTER_SANITIZE_STRING_ONE_LINE);
$categorytemplate = WebUtil::GetInput("categorytemplate", FILTER_SANITIZE_STRING_ONE_LINE);
$productIDview = WebUtil::GetInput("productIDview", FILTER_SANITIZE_INT); // Always show templates From the collection of the Product this ProductID... regardless of what Product the Project belongs to.
$offset = WebUtil::GetInput("offset", FILTER_SANITIZE_INT);
$ProductID = WebUtil::GetInput("productid", FILTER_SANITIZE_INT);
$resultsCount = WebUtil::GetInput("resultsCount", FILTER_SANITIZE_INT);
$templateNumber = WebUtil::GetInput("TemplateNumber", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$templateSwitchOnPaging = WebUtil::GetInput("TemplateSwitchOnPaging", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

// Fall back on an alternate Template Number variable.
if(empty($templateNumber))
	$templateNumber = WebUtil::GetInput("TemplateID", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);


// An error happened with spaces being eliminated from destination URL's at AdWords.
if(strtolower($keywords) == "beautyandhealth")
	$keywords = "beauty health";
else if(strtolower($keywords) == "gardeninglandscaping")
	$keywords = "gardening landscaping";
else if(strtolower($keywords) == "airforceandmilitary")
	$keywords = "airforce military";
else if(strtolower($keywords) == "writerscreenwriterauthor")
	$keywords = "writer screenwriter author";
else if(strtolower($keywords) == "computersales")
	$keywords = "computer";
else if(strtolower($keywords) == "computerrepair")
	$keywords = "computer";
else if(strtolower($keywords) == "hairdresser")
	$keywords = "hairdresser hair";
else if(strtolower($keywords) == "healthcare")
	$keywords = "healthcare medical";
else if(strtolower($keywords) == "healthcare")
	$keywords = "healthcare medical";
else if(strtolower($keywords) == "filmmaker")
	$keywords = "filmmaker films";
else if(strtolower($keywords) == "babysitting")
	$keywords = "babysitting childcare";
else if(strtolower($keywords) == "interiordesign")
	$keywords = "interior design";
else if(strtolower($keywords) == "scubadiving")
	$keywords = "scuba diving";
else if(strtolower($keywords) == "humanresources")
	$keywords = "human resources";
else if(strtolower($keywords) == "tailoralteration")
	$keywords = "tailor alteration";
else if(strtolower($keywords) == "waiterandwaitress")
	$keywords = "waiter waitress restaurant";


	
//WebUtil::BreakOutOfSecureMode();


// Category Tempalte can be Zero (which means default template category) ... or it can be NULL which means we don't want tempalte categories
// So, through the URL we had to use a string isntead of an int.  So we need to validate manually.
WebUtil::EnsureDigit($categorytemplate, false);

// We should have both a productIDview and a regular Product ID
// That way we can be creating a project for a certain Product... at the same time viewing the template collection for another Product
// Some Products can create multiple template previews.
if(empty($productIDview))
	$productIDview = $ProductID;
if(empty($ProductID))
	$ProductID = $productIDview;
	
if(empty($templateNumber))
	$templateNumber = "";


$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();

$user_sessionID =  WebUtil::GetSessionID();


// The website should still work in secure mode, but they may get little complaints from tracking software or flash
//WebUtil::BreakOutOfSecureMode();

// Set this variable.  We will check to make sure it gets set on the following page.  If we cant find it then the person might not have cookies enabled.
$HTTP_SESSION_VARS['initialized'] = 1;


$t = new Templatex();

$t->set_file("origPage", "templates-template.html");



$t->set_var("ARTWORK_CONTINUE_URL", "shoppingcart.php");
$t->set_var("ARTWORK_CONTINUE_URL_ENCODED", "shoppingcart.php");


if(empty($productIDview) || empty($ProductID))
	WebUtil::PrintError("Error with URL, the Product ID is missing.");


	
// Ensure Domain Permissions on both the Product ID view and the regular Product ID.
$domainIDofProductView = Product::getDomainIDfromProductID($dbCmd, $productIDview);
$domainIDofProductID = Product::getDomainIDfromProductID($dbCmd, $ProductID);

$passiveAuthObj = Authenticate::getPassiveAuthObject();

if(!$passiveAuthObj->CheckIfUserCanViewDomainID($domainIDofProductView) || !$passiveAuthObj->CheckIfUserCanViewDomainID($domainIDofProductID))
	WebUtil::PrintError("Error with URL, the Product ID is invalid.");

	
if($domainIDofProductID != Domain::getDomainIDfromURL() || $domainIDofProductView != Domain::getDomainIDfromURL())
	throw new Exception("The Product ID for browsing templates does not match the Domain ID in the URL.");


// Templates may be shared between multiple products
$productObj = Product::getProductObj($dbCmd, $ProductID);
$productIDforTemplates = $productObj->getProductIDforTemplates();




// We would always prefer to use the Preview Images from the ProductID view... but in case there aren't any template preview images with that productID...
// ... then we will have to default to the DefaultProductIDforTemplatePreviews.  
// A Product could create multiple Templates Previews for multiple Product IDs
$dbCmd->Query("SELECT COUNT(*) FROM artworkstemplatespreview WHERE ProductID=$productIDview");
$artworkTempaltePreviewCount = $dbCmd->GetValue();
if(empty($artworkTempaltePreviewCount))
	$productIDview =  $productObj->getdefaultProductIDforTemplatePreview();

	
// What are the name/value pairs AND URL  for all of the subsequent pages
// Save URL space if the Product ID of templates matches the product ID of the tempaltes.
if($productIDview == $ProductID)
	$NV_pairs_URL = "productid=$ProductID";
else
	$NV_pairs_URL = "productIDview=$productIDview&productid=$ProductID";

	
if(!empty($categorytemplate) || $categorytemplate === "0")
	$NV_pairs_URL .= "&categorytemplate=$categorytemplate";
if(!empty($keywords))
	$NV_pairs_URL .= "&keywords=" . urlencode($keywords);
if(!empty($keywords_and))
	$NV_pairs_URL .= "&keywords_and=" . urlencode($keywords_and);
if(!empty($resultsCount))
	$NV_pairs_URL .= "&resultsCount=$resultsCount";	
if(!empty($templateNumber))
	$NV_pairs_URL .= "&TemplateNumber=" . urlencode($templateNumber);
		
// TemplateSwitchOnPaging is sigficant... even if the value is left blank.
// Don't add this NV pair unless it exists in the URL
if(array_key_exists("TemplateSwitchOnPaging", $_REQUEST ))
	$NV_pairs_URL .= "&TemplateSwitchOnPaging=" . urlencode($templateSwitchOnPaging);	

$currentURL = Domain::getGalleryUrl(Domain::getDomainIDfromURL()) . "?" . $NV_pairs_URL;
if(!empty($offset))
	$currentURL .= "&offset=$offset";

	
$t->set_var("PRODUCT_TITLE", WebUtil::htmlOutput($productObj->getProductTitle()));
$t->set_var("PRODUCTNAME_WITH_SLASHES", addslashes($productObj->getProductTitle()));
$t->set_var("PRODUCTNAME_EXT_WITH_SLASHES", addslashes($productObj->getProductTitleExtension()));
$t->set_var("PRODUCTID", $ProductID);
$t->set_var("PRODUCTID_VIEW", $productIDview);
$t->set_var("RETURNURL_ENCODED", urlencode($currentURL));
$t->set_var("CURRENT_URL", $currentURL);
$t->set_var("TEMPLATE_NUMBER", WebUtil::htmlSpecialShort($templateNumber));



// Certain Products like Envelopes may allow you to switch between Preview Images of the Double-Sided version (funny shape) and the Single-Sided version (ordinary rectangle).
if($productObj->checkIfMultiPreviewImagesHaveMessagesToSwitchBetween()){

	$t->set_block("origPage","ProductPreviewImageSwitchingRadioButtonsBL","ProductPreviewImageSwitchingRadioButtonsBLout");

	$multiplePreviewProductIDsArr = $productObj->getMultipleTemplatePreviewsArr();
	
	foreach($multiplePreviewProductIDsArr as $thisMultiPreviewID){
	
		$t->set_var("PRODUCT_SWITCH_PRODUCT_ID", $thisMultiPreviewID);
		$t->set_var("PRODUCT_SWITCH_DESCRIPTION", WebUtil::htmlOutput($productObj->getMultiTemplatePreviewSwitchDesc($thisMultiPreviewID)));
		
		if($thisMultiPreviewID == $productIDview)
			$t->set_var("PRODUCT_SWITCH_SELECTED", "checked");
		else
			$t->set_var("PRODUCT_SWITCH_SELECTED", "");
			
	
		$t->parse("ProductPreviewImageSwitchingRadioButtonsBLout","ProductPreviewImageSwitchingRadioButtonsBL",true);
	}
}
else{
	$t->discard_block("origPage", "ProductPreviewImageSwitchingBL");
}



// If we haven't picked a Category Template or a Search Term yet... then start of with the Template Start Page
if(empty($keywords) && $categorytemplate !== "0" && empty($categorytemplate)){

	// So that if we switch back to the Category Tab (from Upload Artwork, etc)
	// Zero means to use the defatult template category.
	$t->set_var("CATEGORYTEMPLATE", "0");
	
	
	// So that the Nav bar is not defaulted to any tabs.
	$HTTP_SESSION_VARS['Template_NavView'] = "";
	
	// So our Flash box at the top knows to start out with the "Welcome/Begin" message.
	$HTTP_SESSION_VARS['Template_StarUp'] = "begin";
	
	VisitorPath::addRecord("Template StartPage", $ProductID);

	$t->set_var("LAYOUTS", "");
	$t->set_var("KEYWORDS", "");
	$t->set_var("KEYWORDS_AND", "");
	$t->set_var("RESULTS_COUNT", "");
	$t->set_var("RESULTS_COUNT", "");
	$t->set_var("TEMPLATE_IDS_ARR", "");
	$t->set_var("KEYWORDS_AND_ENCODED", "");
	$t->set_var("KEYWORDS_ENCODED", "");
	$t->set_var("TEMPLATE_PREVIEWS_JSON", "");
	$t->discard_block("origPage", "MultiPageBL");
	$t->discard_block("origPage", "SecondMultiPageBL");
	$t->set_var("TEMPLATE_VIEW", "template_category");
	

	$t->discard_block("origPage", "TemplateDesignsBL");
	
	
	// Close the session before printing out the HTML to release the session lock as soon as possible.
	// It may take a while for the client to finish downloading the HTML and close the connection.
	// We may get locks for the "Logon Check" and other Ajax requests.
	session_write_close();
		
	$t->pparse("OUT","origPage");
	exit;

	

}
else{

	// Hide the "Template Start" block since we are either searching or browsing by category.
	$t->discard_block("origPage", "StartPageBL");
}





// Erase the Afilliate Tracking code unless the user just landed into the template collection from a Banner Click
// We know that if they have a session established for longer than a couple of seconds... they couldn't have arrived at the template collection by going through the home page.
//$visitorPathObj = new VisitorPath();
//if(Constants::GetDevelopmentServer() || ($visitorPathObj->getSessionDuration($user_sessionID) > 2) || IPaccess::checkIfUserIpHasSomeAccess())
	$t->discard_block("origPage", "AfilliateTrackingCodes");





// Find out how we should display the flash box.  We are going to put parameters in the URL of the flash movie with the IMAGE_SEARCH var
// If there is not an IMAGE_SEARCH variable then the flash object will communicate with the server to get the proper view... In which case it will look for the session vars "Template_StarUp"

// By Setting these flash variables... it will determine how the Nav bar displays
// We need to send this information through Session Vars and XML because at the moment there is no other reliable way to send information to the Flash Object... at least on Macs and Safari browsers
// Although we could change the URL for the SWF file by inserting name value pairs... that would cause a new file to download for each unique combo.
if(!isset($HTTP_SESSION_VARS['ImageSearchCounter'])){
	$t->set_var(array("IMAGE_SEARCH"=>"first"));
	$HTTP_SESSION_VARS['Template_StarUp'] = "first";
	$HTTP_SESSION_VARS['ImageSearchCounter'] = 2;
}
else if($HTTP_SESSION_VARS['ImageSearchCounter'] > 1 && $HTTP_SESSION_VARS['ImageSearchCounter'] < 4){
	$t->set_var(array("IMAGE_SEARCH"=>"second"));
	$HTTP_SESSION_VARS['Template_StarUp'] = "second";
	$HTTP_SESSION_VARS['ImageSearchCounter']++;
}
else if($HTTP_SESSION_VARS['ImageSearchCounter'] == 4){
	$t->set_var(array("IMAGE_SEARCH"=>"google"));
	$HTTP_SESSION_VARS['Template_StarUp'] = "google";
	$HTTP_SESSION_VARS['ImageSearchCounter']++;
}
else if($HTTP_SESSION_VARS['ImageSearchCounter'] > 4){
	$t->set_var(array("IMAGE_SEARCH"=>"secondgoogle"));
	$HTTP_SESSION_VARS['Template_StarUp'] = "secondgoogle";
	$HTTP_SESSION_VARS['ImageSearchCounter']++;
}








// If we are seaching be keywords then we need to erase the Category Tabs Block --#
if(!empty($keywords)){

	$t->discard_block("origPage", "HideTabsBL");

	//Will let the Flash tabs know which button to highlight
	$HTTP_SESSION_VARS['Template_NavView'] = "engine";
}
else{
	// Otherwise we are searching by category 

	$dbCmd->Query("Select count(*) FROM templatecategories where ProductID=$productIDforTemplates");
	$TotalTemplateCategories = $dbCmd->GetValue();
	
	// Erase the block of HTML which says... "Search engine provides limited results"
	$t->discard_block("origPage", "LimitedDescBL");


	// Build the tabs for the Categories
	$TabsObj = new Navigation();

	$dbCmd->Query("SELECT CategoryName, CategoryID FROM templatecategories where ProductID=$productIDforTemplates ORDER BY IndexID ASC");

	$CategoryCounter = 0;
	while ($row = $dbCmd->GetRow()){

		$CategoryName = $row["CategoryName"];
		$CategoryTempID = $row["CategoryID"];
		
		// The Category name "BACK" is a special category name and should not be mixed in with the regular preview images.
		// That is where we store Backside Templates for Postcards (which are chosen independently of the front).  It could be used for other products as well.
		if(preg_match("/^BACK/", $CategoryName))
			continue;

		// If we are vieing this product but havent selected a specific category, then default to the fist category in the list.
		if($CategoryCounter == 0 && $categorytemplate === "0")
			$categorytemplate = $CategoryTempID;

		$CategoryCounter++;

		// Cut down on URL space if the Product ID's match.
		if($productIDview == $ProductID)
			$TabLink = "./".Domain::getGalleryUrl(Domain::getDomainIDfromURL())."?categorytemplate=$CategoryTempID&productid=$ProductID";
		else
			$TabLink = "./".Domain::getGalleryUrl(Domain::getDomainIDfromURL())."?categorytemplate=$CategoryTempID&productIDview=$productIDview&productid=$ProductID";
		
		

		$TabsObj->AddTab($CategoryTempID, $CategoryName, $TabLink);
	}

	$t->set_var("NAV_BAR_HTML", $TabsObj->GetTabsHTML($categorytemplate, "corner2"));
	$t->allowVariableToContainBrackets("NAV_BAR_HTML");

	


	// This will erase the Tabs up on top in case there is only 1 tab.. no point in showing it. 
	if($TotalTemplateCategories < 2){

		$t->discard_block("origPage", "HideTabsBL");

		// Since there is only one category.. Now is a good time to count how many preview exist
		// If there is only one preview template and only one template category... then there is no need to even show this page.  We could automatically forward them to step 3 
		$dbCmd->Query("SELECT COUNT(*) FROM artworkstemplates where ProductID=$productIDforTemplates");
		$artworkTemplateCount = $dbCmd->GetValue();

		if($artworkTemplateCount == 1){

			// We need to get the record id of the single template so we can tell the next page what template we are using.
			$dbCmd->Query("SELECT ArtworkID FROM artworkstemplates where ProductID=$productIDforTemplates");
			$TemplateIDforRedirect = $dbCmd->GetValue();

			$redirectionURL = "./product_loadtemplate.php?newtemplateid=$TemplateIDforRedirect&newtemplatetype=template_category&productIDview=$productIDview&productid=$ProductID&nocache=" . time();
			
			session_write_close();
			header("Location: " . WebUtil::FilterURL($redirectionURL), true, 302);
			exit;
		}
	}
	
	//Will let the Flash tabs know which button to highlight
	$HTTP_SESSION_VARS['Template_NavView'] = "category";
}




// Accept the parameter from the URL to control multip-paging... unless it is empty or invalid.
if(empty($resultsCount) || $resultsCount > 100)
	$NumberOfResultsToDisplay = 10;
else
	$NumberOfResultsToDisplay = $resultsCount;

	
$t->set_var("RESULTS_COUNT", $NumberOfResultsToDisplay);

// Figure out the page number that we are on.
if(empty($offset))
	$pageNumber = 1;
else
	$pageNumber = round($offset / $NumberOfResultsToDisplay) + 1;
	

//  Get a List of Tempalte ID's ... If they keywords are blank then we are showing templates by category 
if(empty($keywords)){

	$TemplateIDArr = array();

	// So if we switch back to browsing by Category... Zero means pick the default category.
	if($TotalTemplateCategories == 0)
		$categorytemplate = "0";
		
	VisitorPath::addRecord("Template Category", $ProductID . ":" . $pageNumber . ":" . $categorytemplate);

	// Get all preview images for the artwork templates belonging to the template category we are viewing.
	$dbCmd->Query("SELECT ArtworkID FROM artworkstemplates WHERE CategoryID=$categorytemplate AND ProductID=$productIDforTemplates ORDER BY IndexID ASC");

	while ($thisTemplateID = $dbCmd->GetValue())
		$TemplateIDArr[] = $thisTemplateID;
		
	if(!empty($TemplateIDArr))
		$t->discard_block("origPage", "NoTemplatesFoundBL");		

	$PreviewColumnName = "TemplateID";
	
	$t->set_var("TEMPLATE_VIEW", "template_category");
}
else{
	// This means that we are showing templates based off of search results.
	// We want to get a list of template ID's and gather the information for them.
	$TemplateIDArr = ArtworkTemplate::GetSearchResultsForTempaltes($dbCmd, $keywords, $keywords_and, $productIDforTemplates);
	
	$keywordsForLog = strtolower($keywords);
	if(!empty($keywords_and))
		$keywordsForLog .= "^" . strtolower($keywords_and);
	
	VisitorPath::addRecord("Template SearchEngine", $ProductID . ":" . $pageNumber . ":" . $keywordsForLog);
	
	if(!empty($TemplateIDArr))
		$t->discard_block("origPage", "NoTemplatesFoundBL");
	
	//Record what keywords the user searched on.... but we don't want to keep recording with they are browsing through multiple pages of results
	if(empty($offset)){
		
		// Don't log GoogleBot Visits.
		if(!WebUtil::isUserAgentWebCrawlerSpider())
			ArtworkTemplate::LogTemplateKeywordSearch($dbCmd, $keywordsForLog, $productIDforTemplates);
	}
	
	//So the flash object will know what keywords we searched on and how many matches it found 
	$HTTP_SESSION_VARS['Template_Kwds'] = $keywords;
	$HTTP_SESSION_VARS['Template_Matches'] = sizeof($TemplateIDArr);
	$HTTP_SESSION_VARS['Template_SearchOffset'] = $offset;
	
	$PreviewColumnName = "SearchEngineID";

	
	$t->set_var("TEMPLATE_VIEW", "template_searchengine");
}




// Now build a hash containing the information for the template layouts.
$LayoutsResults = array();
$LayoutCounter = 0;
$numberOfTemplates = 0;

$templateIDsInlucdedWithinLayoutResultsArr = array();

foreach($TemplateIDArr as $ThisTemplateID){

	$numberOfTemplates++;
	
	$sideNumber = 0;
	
	// Get the template preview deatils
	$query = "SELECT * FROM artworkstemplatespreview WHERE $PreviewColumnName = $ThisTemplateID AND ProductID='$productIDview' ORDER BY ID ASC";
	$dbCmd->Query($query);

	while($row = $dbCmd->GetRow()){

		// If there are multiple pages we want to skip over the results that are not included
		if($numberOfTemplates <= ($offset + $NumberOfResultsToDisplay) && $numberOfTemplates > $offset){

			// Find out how many template previews there are with each tempalte ID.
			// For example, on Single Sided cards there is only 1 preview.  On Double-sided cards there are 2 preview images.
			$dbCmd2->Query("SELECT COUNT(*) FROM artworkstemplatespreview 
							WHERE $PreviewColumnName = $ThisTemplateID AND ProductID='$productIDview'");
			$tempPreviewCount = $dbCmd2->GetValue();
			
			
			// We may have chosen only to display the 'F'irst side of template previews.  In which case, we will override the tempalte preview count to just one.
			if($productObj->getTemplatePreviewSidesDisplay() == "F"){
				$tempPreviewCount = 1;
				
				// If we are only displaying the template preview of the first side... and we have included a layout image for the Template ID already... skip the rest of the loop.
				if(in_array($ThisTemplateID, $templateIDsInlucdedWithinLayoutResultsArr))
					continue;
			}
			$templateIDsInlucdedWithinLayoutResultsArr[] = $ThisTemplateID;
			
			
			$LayoutsResults[$LayoutCounter]['PreviewID'] = $row["ID"];
			$LayoutsResults[$LayoutCounter]['SideName'] = $row["SideName"];
			$LayoutsResults[$LayoutCounter]['ImgWidth'] = $row["Width"];
			$LayoutsResults[$LayoutCounter]['ImgHeight'] = $row["Height"];
			$LayoutsResults[$LayoutCounter]['TempID'] = $ThisTemplateID;
			$LayoutsResults[$LayoutCounter]['TempPreviewCount'] = $tempPreviewCount;
			$LayoutsResults[$LayoutCounter]['SideNumber'] = $sideNumber;

			$LayoutCounter++;
			$sideNumber++;
		}
	}
}



$t->set_var("TEMPLATE_IDS_ARR", implode(",", $TemplateIDArr));
$t->set_var("TEMPLATE_PREVIEWS_JSON", json_encode($LayoutsResults));



// Set this block so that we can erase the multi-page navigation bar if there aren't multiple pages
$t->set_block("origPage","MultiPageBL","MultiPageBLout");
$t->set_block("origPage","SecondMultiPageBL","SecondMultiPageBLout");

// This means that we have multiple pages of search results
if($numberOfTemplates > $NumberOfResultsToDisplay){
	
	$stepDownDir = WebUtil::GetInput("StepDownDir", FILTER_SANITIZE_INT);
		
	if($stepDownDir > 0)
		$BaseURL = "/" . Domain::getGalleryUrl(Domain::getDomainIDfromURL());
	else 
		$BaseURL = "./" . Domain::getGalleryUrl(Domain::getDomainIDfromURL());
	

	// Get a the navigation of hyperlinks to all of the multiple pages
	$NavigateHTML = Navigation::GetNavigationForSearchResults($BaseURL, $numberOfTemplates, $NV_pairs_URL, $NumberOfResultsToDisplay, $offset);

	$t->set_var("NAVIGATE", $NavigateHTML);
	$t->allowVariableToContainBrackets("NAVIGATE");
	
	$t->parse("MultiPageBLout","MultiPageBL",true);
	$t->parse("SecondMultiPageBLout","SecondMultiPageBL",true);
}
else{
	$t->set_var("NAVIGATE", "");
	$t->set_var("MultiPageBLout", "");
	$t->set_var("SecondMultiPageBLout", "");
}


if($offset == "")
	$offset = 0;


$t->set_var("RESULT_DESC", WebUtil::htmlOutput($numberOfTemplates));
$t->set_var("OFFSET", WebUtil::htmlOutput($offset));
$t->set_var("CATEGORYTEMPLATE", WebUtil::htmlOutput($categorytemplate));


$ua = new UserAgent();

$tableWidth = 740;
$CellPadding = 10;
$BackBorderColor = "#3366CC";

$LayoutsHTML ="<table cellpadding='0' cellspacing='0' border='0' width='$tableWidth'>";
$LayoutsHTML .= "\n<tr><td colspan='3' bgcolor='$BackBorderColor'><img src='./images/transparent.gif' border='0' width='2' height='2'></td></tr>";


// Loop through the 2D hash that we just created and build the HTML
for($i=0; $i<sizeof($LayoutsResults); $i++){

	// Make sure that we are not on the last record.
	if(isset($LayoutsResults[$i+1])){

		// Look ahead in the results by one entry and see if the next entry is part of the same template.  If so it means this may be the back side or something.
		if($LayoutsResults[$i]['TempID'] == $LayoutsResults[$i+1]['TempID']){

			// Ok looks like this template has multiple sides to it.  We are going to enclose the whole table within a highlight block.
			// The table for displaying all of the sides comed from our function call.
			$LayoutsHTML .= "<tr><td align='center' class='Preview' colspan='3' onMouseOver=\"hl(this)\" onMouseOut=\"dl(this)\">";

			$LayoutsHTML .= "<img src='./images/transparent.gif' border='0' width='2' height='2'><br>";
			$LayoutsHTML .= GetMultipleSidePreview($LayoutsResults, $i, $tableWidth);
			$LayoutsHTML .= "<img src='./images/transparent.gif' border='0' width='2' height='$CellPadding'>";

			$LayoutsHTML .=  "</td></tr>";
			$LayoutsHTML .= "\n<tr><td colspan='3' bgcolor='$BackBorderColor'><img src='./images/transparent.gif' border='0' width='2' height='2'></td></tr>";

			// We need the loop to skip ahead depending on how many sides are in the particular template.  We subtract one because the loop we are on naturally increments by 1.
			$i += (GetNumberOfSidesInTemplate($LayoutsResults, $LayoutsResults[$i]['TempID']) -1 );

		}
		else{  
			// The preview image in front is a separate template then
			// Find out if both template previews can fit within half of the table width
			if(($LayoutsResults[$i]['ImgWidth']< $tableWidth/2) && ($LayoutsResults[$i+1]['ImgWidth']< $tableWidth/2)){

				$ShowBothPreviewsOnThisLine = true;

				// Now we want to look 2 preview images ahead.  Just because the current 2 will safely fit in this row doesnt mean we are going to put them here
				// If the second preview image is part of a series.. like front and back.. then we want to make sure that it starts on its own line.
				if(isset($LayoutsResults[$i+2])){
					if($LayoutsResults[$i+1]['TempID'] == $LayoutsResults[$i+2]['TempID']){
						$ShowBothPreviewsOnThisLine = false;

						$LayoutsHTML .= "<tr><td align='center' class='Preview' colspan='3' onMouseOver=\"hl(this)\" onMouseOut=\"dl(this)\">";

						$LayoutsHTML .= "<img src='./images/transparent.gif' border='0' width='2' height='$CellPadding'><br><table cellpadding='0' cellspacing='0' border='0'><tr><td><a href='javascript:LinkToStep3(". $LayoutsResults[$i]['TempID'] .")'>";
						$LayoutsHTML .= GetPreviewImageHTML($LayoutsResults[$i]['PreviewID'], $LayoutsResults[$i]['ImgWidth'], $LayoutsResults[$i]['ImgHeight'] );
						$LayoutsHTML .= "</a></td></tr></table><br><img src='./images/transparent.gif' border='0' width='2' height='$CellPadding'>";

			
						$LayoutsHTML .= "</td></tr>";
						$LayoutsHTML .= "\n<tr><td colspan='3' bgcolor='$BackBorderColor'><img src='./images/transparent.gif' border='0' width='2' height='2'></td></tr>";
					}

				}
				if($ShowBothPreviewsOnThisLine){
					$LayoutsHTML .= "\n<tr>";
					$LayoutsHTML .= "\n<td width='". ($tableWidth /2 - 1)  . "' align='center' class='Preview' onMouseOver=\"hl(this)\" onMouseOut=\"dl(this)\">";


					$LayoutsHTML .= "<img src='./images/transparent.gif' border='0' width='2' height='$CellPadding'><br><table cellpadding='0' cellspacing='0' border='0'><tr><td><a href='javascript:LinkToStep3(". $LayoutsResults[$i]['TempID'] .")'>";
					$LayoutsHTML .= GetPreviewImageHTML($LayoutsResults[$i]['PreviewID'], $LayoutsResults[$i]['ImgWidth'], $LayoutsResults[$i]['ImgHeight'] );
					$LayoutsHTML .= "</a></td></tr></table><br><img src='./images/transparent.gif' border='0' width='2' height='$CellPadding'>";


					$LayoutsHTML .= "</td>";
					$LayoutsHTML .= "\n<td width='2' bgcolor='$BackBorderColor'><img src='./images/transparent.gif' border='0' width='2' height='1'></td>";
					$LayoutsHTML .= "\n<td width='". ($tableWidth /2 - 1)  . "' align='center' class='Preview' onMouseOver=\"hl(this)\" onMouseOut=\"dl(this)\">";


					$LayoutsHTML .= "<img src='./images/transparent.gif' border='0' width='2' height='$CellPadding'><br><table cellpadding='0' cellspacing='0' border='0'><tr><td><a href='javascript:LinkToStep3(". $LayoutsResults[$i+1]['TempID'] .")'>";
					$LayoutsHTML .= GetPreviewImageHTML($LayoutsResults[$i+1]['PreviewID'], $LayoutsResults[$i+1]['ImgWidth'], $LayoutsResults[$i+1]['ImgHeight'] );
					$LayoutsHTML .= "</a></td></tr></table><br><img src='./images/transparent.gif' border='0' width='2' height='$CellPadding'>";


					$LayoutsHTML .= "</td>";
					$LayoutsHTML .= "\n</tr>";
					$LayoutsHTML .= "\n<tr><td colspan='3' bgcolor='$BackBorderColor'><img src='./images/transparent.gif' border='0' width='2' height='2'></td></tr>";

					// Make it skip the next loop since we already drew the next preview image in the loop iteration.
					$i += 1;
				}

			}
			
			else{
				// Otherwise it means this preview image will occupy its own line because it is so fat.
			
				$LayoutsHTML .= "<tr><td align='center' class='Preview' colspan='3' onMouseOver=\"hl(this)\" onMouseOut=\"dl(this)\">";


				$LayoutsHTML .= "<img src='./images/transparent.gif' border='0' width='2' height='$CellPadding'><br><table cellpadding='0' cellspacing='0' border='0'><tr><td><a href='javascript:LinkToStep3(". $LayoutsResults[$i]['TempID'] .")'>";
				$LayoutsHTML .= GetPreviewImageHTML($LayoutsResults[$i]['PreviewID'], $LayoutsResults[$i]['ImgWidth'], $LayoutsResults[$i]['ImgHeight'] );
				$LayoutsHTML .= "</a></td></tr></table><br><img src='./images/transparent.gif' border='0' width='2' height='$CellPadding'>";


				$LayoutsHTML .= "</td></tr>";
				$LayoutsHTML .= "\n<tr><td colspan='3' bgcolor='$BackBorderColor'><img src='./images/transparent.gif' border='0' width='2' height='2'></td></tr>";
			}
		}
	}
	else{


		// Since this is the last record it will occupy its own row. 

		$LayoutsHTML .= "<tr><td align='center' class='Preview' colspan='3' onMouseOver=\"hl(this)\" onMouseOut=\"dl(this)\">";


		$LayoutsHTML .= "<img src='./images/transparent.gif' border='0' width='2' height='$CellPadding'><br><table cellpadding='0' cellspacing='0' border='0'><tr><td><a href='javascript:LinkToStep3(". $LayoutsResults[$i]['TempID'] .")'>";
		$LayoutsHTML .= GetPreviewImageHTML($LayoutsResults[$i]['PreviewID'], $LayoutsResults[$i]['ImgWidth'], $LayoutsResults[$i]['ImgHeight'] );
		$LayoutsHTML .= "</a></td></tr></table><br><img src='./images/transparent.gif' border='0' width='2' height='$CellPadding'>";


		$LayoutsHTML .= "</td></tr>";
		$LayoutsHTML .= "\n<tr><td colspan='3' bgcolor='$BackBorderColor'><img src='./images/transparent.gif' border='0' width='2' height='2'></td></tr>";

	}

}

$LayoutsHTML .= '</table>';


$t->set_var("LAYOUTS", $LayoutsHTML);
$t->allowVariableToContainBrackets("LAYOUTS");

$t->set_var("KEYWORDS_ENCODED", urlencode($keywords));
$t->set_var("KEYWORDS", WebUtil::htmlOutput($keywords));
$t->set_var("KEYWORDS_AND_ENCODED", urlencode($keywords_and));
$t->set_var("KEYWORDS_AND", WebUtil::htmlOutput($keywords_and));


// Close the session before printing out the HTML to release the session lock as soon as possible.
// It may take a while for the client to finish downloading the HTML and close the connection.
// We may get locks for the "Logon Check" and other Ajax requests.
session_write_close();

$t->pparse("OUT","origPage");



function GetPreviewImageHTML($PreviewID, $imgWidth, $imgHeight){
	
	global $keywords;
	
	if(!empty($keywords))
		$ImageType = "template_searchengine";
	else
		$ImageType = "template_category";
	
	
	$websiteUrlForDomain = strtolower(Domain::getWebsiteURLforDomainID(Domain::oneDomain()));
		
	$ImagePeviewFileName = ThumbImages::GetTemplatePreviewName($PreviewID, $ImageType);
	if(WebUtil::checkIfInSecureMode())
		$http_secure = "https://";
	else
		$http_secure = "http://";
	
	// This is a really bad hack for PME... All of the new domains are using AJAX and CSS.
	$ProductID = WebUtil::GetInput("productid", FILTER_SANITIZE_INT);
	if($ProductID == 178)
		$borderWidth = 0;
	else
		$borderWidth = 1;
		
	$retHTML = "<img border='$borderWidth' style='border-color:#000000' src='" . $http_secure . "$websiteUrlForDomain/image_preview/" . $ImagePeviewFileName . "' width='" . $imgWidth . "' height='" . $imgHeight . "'>";
	return $retHTML ;
}

function GetMultipleSidePreview($LayoutsResults, $CurrentIndex, $tableWidth){


	$retHTML = "";

	// Loop through the 2D hash that we just created and build the HTML
	for($i=$CurrentIndex; $i<sizeof($LayoutsResults); $i++){

		// Make sure that we are not on the last record.
		if(isset($LayoutsResults[$i+1])){

			if($LayoutsResults[$i]['TempID'] == $LayoutsResults[$i+1]['TempID']){

				// Find out if both template previews can fit within half of the table width
				if(($LayoutsResults[$i]['ImgWidth']< $tableWidth/2) && ($LayoutsResults[$i+1]['ImgWidth']< $tableWidth/2)){

					$retHTML .= "\n<table width='100%' cellpadding='0' cellspacing='0' border='0'>";
					$retHTML .= "<tr><td width='50%' align='center' valign='top' class='body'><img src='./images/transparent.gif' border='0' width='2' height='8'><br>" . $LayoutsResults[$i]['SideName'] . "</td><td width='50%' align='center' class='body'><img src='./images/transparent.gif' border='0' width='2' height='8'><br>" . $LayoutsResults[$i+1]['SideName'] . "</td></tr>";
					$retHTML .= "\n<tr><td width='50%' align='center' valign='top' onClick='javascript:LinkToStep3(". $LayoutsResults[$i+1]['TempID'] .")'><a href='javascript:LinkToStep3(". $LayoutsResults[$i]['TempID'] .")'>" . GetPreviewImageHTML($LayoutsResults[$i]['PreviewID'], $LayoutsResults[$i]['ImgWidth'], $LayoutsResults[$i]['ImgHeight'] )  . "</a></td>";
					$retHTML .= "\n<td width='50%' align='center' valign='top' onClick='javascript:LinkToStep3(". $LayoutsResults[$i+1]['TempID'] .")'><a href='javascript:LinkToStep3(". $LayoutsResults[$i+1]['TempID'] .")'>" . GetPreviewImageHTML($LayoutsResults[$i+1]['PreviewID'], $LayoutsResults[$i+1]['ImgWidth'], $LayoutsResults[$i+1]['ImgHeight'] )  . "</a></td>";
					$retHTML .= "\n</tr></table>";

					// We just drew two images on the same row.  So we can skip ahead in the loop by 1 iteration
					// Before we skip ahead we need to make sure that if we do, that the SIDE is part of the same template.  So we have to actually look ahead by 2
					if(isset($LayoutsResults[$i+2])){

						// Make sure that the SIDE is part of the same template.. If so we can continue in the loop (but dont forget to skip ahead 1 iteration.
						if($LayoutsResults[$i+1]['TempID'] == $LayoutsResults[$i+2]['TempID'])
							$i += 1;
						else{	
							// The it means that the next SIDE we would draw is not part of the same template.  So get out of the function now.
							return $retHTML;
						}

					}
					else{	
						//then there is no point in continuing any futher because no more sides exist.
						return $retHTML;
					}


				}
				else{
					// since both images cant fit together on the same row we are only going to show one row at a time.
					
					$retHTML .= "\n<table width='100%' cellpadding='0' cellspacing='0' border='0'>";
					$retHTML .= "<tr><td align='center' class='body'><img src='./images/transparent.gif' border='0' width='2' height='8'><br>" . $LayoutsResults[$i]['SideName'] . "</td></tr>";
					$retHTML .= "\n<tr><td align='center' onClick='javascript:LinkToStep3(". $LayoutsResults[$i+1]['TempID'] .")'><a href='javascript:LinkToStep3(". $LayoutsResults[$i]['TempID'] .")'>" . GetPreviewImageHTML($LayoutsResults[$i]['PreviewID'], $LayoutsResults[$i]['ImgWidth'], $LayoutsResults[$i]['ImgHeight'] )  . "</a></td>";
					$retHTML .= "\n</tr></table>";

				}
			}
			else{
				// This means that this is the last side belonging to its template.  It has to go on its own line.

				// We  know that this is going to be the last side of multiple.  So we look backwards by 1 record to make sure we are still on the same template.
				if($LayoutsResults[$i]['TempID'] == $LayoutsResults[$i-1]['TempID']){
					$retHTML .= "\n<table width='100%' cellpadding='0' cellspacing='0' border='0'>";
					$retHTML .= "<tr><td align='center' class='body'><img src='./images/transparent.gif' border='0' width='2' height='8'><br>" . $LayoutsResults[$i]['SideName'] . "</td></tr>";
					$retHTML .= "\n<tr><td align='center' onClick='javascript:LinkToStep3(". $LayoutsResults[$i+1]['TempID'] .")'><a href='javascript:LinkToStep3(". $LayoutsResults[$i]['TempID'] .")'>" . GetPreviewImageHTML($LayoutsResults[$i]['PreviewID'], $LayoutsResults[$i]['ImgWidth'], $LayoutsResults[$i]['ImgHeight'] )  . "</a></td>";
					$retHTML .= "\n</tr></table>";
				}

				return $retHTML;
			}
		}
		else{

			// Since this is the last record it will ocupy its own row. As long as it matches the template we are viewing.
			// We compare it to the record before.
			if($LayoutsResults[$i]['TempID'] == $LayoutsResults[$i-1]['TempID']){

				$retHTML .= "\n<table width='100%' cellpadding='0' cellspacing='0' border='0'>";
				$retHTML .= "<tr><td align='center' class='body'><img src='./images/transparent.gif' border='0' width='2' height='8'><br>" . $LayoutsResults[$i]['SideName'] . "</td></tr>";
				$retHTML .= "\n<tr><td align='center' onClick='javascript:LinkToStep3(". $LayoutsResults[$i]['TempID'] .")'><a href='javascript:LinkToStep3(". $LayoutsResults[$i]['TempID'] .")'>" . GetPreviewImageHTML($LayoutsResults[$i]['PreviewID'], $LayoutsResults[$i]['ImgWidth'], $LayoutsResults[$i]['ImgHeight'] )  . "</a></td>";
				$retHTML .= "\n</tr></table>";

				return $retHTML;
			}
			else{

				// this entry didnt belong to the template so return the HTML that we have generated so far.
				return $retHTML;
			}
		}
	}

	return $retHTML;

}

function GetNumberOfSidesInTemplate($LayoutsResults, $TemplateID){

	$sideCounter = 0;

	// Loop through the 2D hash that we just created and build the HTML
	for($i=0; $i<sizeof($LayoutsResults); $i++){

		if($LayoutsResults[$i]['TempID'] == $TemplateID){

			$sideCounter++;
		}

	}
	return $sideCounter;
}


?>
