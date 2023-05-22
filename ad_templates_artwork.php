<?

require_once("library/Boot_Session.php");

$templateid = WebUtil::GetInput("templateid", FILTER_SANITIZE_INT);
$productid = WebUtil::GetInput("productid", FILTER_SANITIZE_INT);
$offset = WebUtil::GetInput("offset", FILTER_SANITIZE_INT);
$copytemplate = WebUtil::GetInput("copytemplate", FILTER_SANITIZE_INT);
$categorytemplate = WebUtil::GetInput("categorytemplate", FILTER_SANITIZE_INT);
$productidforreturn = WebUtil::GetInput("productidforreturn", FILTER_SANITIZE_INT);
$editorview = WebUtil::GetInput("editorview", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$searchkeywords = WebUtil::GetInput("searchkeywords", FILTER_SANITIZE_STRING_ONE_LINE);
$closwindowafter = WebUtil::GetInput("closwindowafter", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

$offset = intval($offset);

$dbCmd = new DbCmd();




//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();





if(!empty($productid)){
	
	// 3rd paramter will authenticate domain privelages.
	$productObj = new Product($dbCmd, $productid, true);

	$productIDforTemplates = $productObj->getProductIDforTemplates();
	if($productIDforTemplates != $productid)
		throw new Exception("Error displaying template collection, the Product ID specified does not create its own templates.");
		
	Domain::enforceTopDomainID(Product::getDomainIDfromProductID($dbCmd, $productid));
}


$t = new Templatex(".");


$t->set_file("origPage", "ad_templates_artwork-template.html");

// Get the Header HTML ... If we are closing the window afterwards then we are probably in a pop-up.. in which case we shouldn't show them the header bar to navigate away.
if(!empty($closwindowafter))
	$t->set_var("HEADER", "");
else
	$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));

$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());


// This means it is a new template instead of editing an old one.
if(!empty($copytemplate) || !empty($productid)){


	// This means we are creating a new template 
	if(!empty($productid)){

		// Create a blank artwork for the template we are creating.
		$newXMLfile = '<?xml version="1.0"?>';
		$newXMLfile .= '<content><side><description>Side 0</description><initialzoom>100</initialzoom><rotatecanvas>0</rotatecanvas><contentwidth>300</contentwidth><contentheight>300</contentheight>';
		$newXMLfile .= '<folds_horiz>0</folds_horiz><folds_vert>0</folds_vert><scale>100</scale>';
		$newXMLfile .= '<backgroundimage>0</backgroundimage><background_x>-100</background_x><background_y>-100</background_y><background_width>100</background_width>';
		$newXMLfile .= '<background_height>100</background_height><background_color>0xFFFFFF</background_color><color_definitions/><dpi>300</dpi><side_permissions/></side></content>';


		
		$insertRow["ArtFile"] = $newXMLfile;
		$insertRow["ProductID"] = $productid;
		$insertRow["CategoryID"] = $categorytemplate;
		$insertRow["IndexID"] = 1;
		
		$templateid = $dbCmd->InsertQuery( "artworkstemplates", $insertRow );
	}

	// Otherwise they are copying a template.  Get the Artwork XML file out of the DB so we can make a copy of it.
	else{

		if($editorview == "template_category"){
			
			$dbCmd->Query( "SELECT ArtFile, ProductID, CategoryID, IndexID FROM artworkstemplates INNER JOIN products ON artworkstemplates.ProductID = products.ID 
							WHERE ArtworkID=" . intval($copytemplate) . " AND DomainID=" . Domain::oneDomain() );
			
			if($dbCmd->GetNumRows() == 0)
				throw new Exception("Error trying to Copy templates by Category, the template ID does not exist.");
			
			$row = $dbCmd->GetRow();

			$templateid = $dbCmd->InsertQuery( "artworkstemplates", $row );
		}
		else if($editorview == "template_searchengine"){
			
			$dbCmd->Query( "SELECT ArtFile, ProductID, Sort FROM artworksearchengine INNER JOIN products ON artworksearchengine.ProductID = products.ID  
							WHERE artworksearchengine.ID=" . intval($copytemplate) . " AND DomainID=" . Domain::oneDomain() );
			
			if($dbCmd->GetNumRows() == 0)
				throw new Exception("Error trying to Copy templates by Search Engine, the template ID does not exist.");
			
			$row = $dbCmd->GetRow();

			$templateid = $dbCmd->InsertQuery( "artworksearchengine", $row );
			
			// Copy the keywords over too
			$KeywordList="";
			$dbCmd->Query( "SELECT TempKw FROM templatekeywords WHERE TemplateID=$copytemplate" );
			while($Keyword=$dbCmd->GetValue())
				$KeywordList .= $Keyword . " ";
				
			ArtworkTemplate::AddKeywordsToTemplate($dbCmd, $KeywordList, $templateid);
			
		}
		else{
			throw new Exception("Error with Editor view, cannot copy.");
		}

	}


	// This will basically reload the page.. But now it will be able to fetch the new Template ID that we just inserted.
	// The reason I did this is incase someone keeps reloading the page after selecting "new template".. That would keep entereing a new template each time they reload the page.
	header("Location: " . WebUtil::FilterURL("./ad_templates_artwork.php?templateid=$templateid&categorytemplate=$categorytemplate&productidforreturn=$productidforreturn&editorview=$editorview&offset=$offset&searchkeywords=" . urlencode($searchkeywords)));
	exit;
}


$t->set_var("OFFSET", $offset);

// Keep a record of the visit to this page by the user.
// The identifier field is made up of the "EditorView:TemplateID" 
NavigationHistory::recordPageVisit($dbCmd, $UserID, "Template", ($editorview . ":" . $templateid));



// If people are running a version of Netscape older than 6.2 then we have to redirect them to a software update page
$ua = new UserAgent();

// If they are on a PC always make sure that there are no mac parameters...
// For each new parameter combination that is set in the SWF object.. it will require the browser to download the object again..
// This is only a worst case scenario.. so to it on machines which are not Windows based
if (preg_match("/Windows/i", $ua->platform) && preg_match("/MSIE/", $ua->browser)){
	$macState = "";
	$macUser = "";
}
else{
	$macUser = "yes";
	$macState = "save";
	
	$t->discard_block("origPage", "HideHTMLbuttonsBL");
}


$t->set_var("MACSTATE", $macState);
$t->set_var("MACUSER", $macUser);






// The Editing Tool is really really particular about not being able to refresh the page, etc.
// There is some kind of bug between IE and the Flash Player.  For some reason, reloading the page causes the "fscommand" not to work
// We have to be certain the the user is visiting a unique URL every time the page is loaded
WebUtil::EnsureGetURLdoesNotGetCached();


if(!preg_match("/^\d+$/", $templateid))
	throw new Exception("the tempalte ID is missing.");


// Set this Session variable right before we load the Flash file
// When the flash file requests the XML document it will know which one to get.
$HTTP_SESSION_VARS['editor_ArtworkID'] = $templateid;
$HTTP_SESSION_VARS['editor_View'] = $editorview;

// Used to let the flash program we are switchin sides... no need to download from server
// This var is not used with the templates so always set it to NULL.
$HTTP_SESSION_VARS['SwitchSides'] = "";



// This will store the side number that the person should be viewing on the server 
// The reason is that I can't get Flash to reliably detect javascript variables on all different browsers at startup
// It might be a little more messy but flash's "onLoad" method works relaibly with server varaibles.
// For administrators we don't have to reload the page as we switch sides.. So the side number here should always be initialized to 0
// 10-20-02.. I just found out that I can pass variables to the movie in the html like... "mymovie.swf?name=value" .. This is a better solution I believe.  But if it is not broken right now then I won't fix it.
$HTTP_SESSION_VARS['SideNumber'] = 0;

// Administrators shouldn't be using netscape.  This session var will tell the flash program to stop that annoying clicking should associated with getURL
$HTTP_SESSION_VARS['UserAgent'] = "MSIE";



$ThisProductID = ProjectBase::GetProductIDFromProjectRecord($dbCmd, $templateid, $editorview);

$productObj = new Product($dbCmd, $ThisProductID);
Domain::enforceTopDomainID(Product::getDomainIDfromProductID($dbCmd, $ThisProductID));


// Figure out how big the bleed and safe zones are.
$t->set_var("GUIDE_MARGIN", ImageLib::ConvertPicasToFlashUnits($productObj->getArtworkBleedPicas()));


// Make sure that we have all of the necessary SWF files on disk... generated by MING
$xmlDocument = ArtworkLib::GetArtXMLfile($dbCmd, $editorview, $templateid);


$ArtworkInfoObj = new ArtworkInformation($xmlDocument);
if(!isset($ArtworkInfoObj->SideItemsArray[0]))
	throw new Exception("Problem with with Artwork Image.  Side 0 is not defined.");
$t->set_var("ARTDPI", $ArtworkInfoObj->SideItemsArray[0]->dpi);




ArtworkLib::WriteSWFimagesToDisk($dbCmd, $xmlDocument);


$t->set_var("TEMPLATEID", $templateid);
$t->set_var("CATEGORYTEMPLATE", $categorytemplate);
$t->set_var("PRODUCTID", $productidforreturn);
$t->set_var("EDITOR_VIEW", $editorview);
$t->set_var("CLOSE_WINDOW_AFTER", $closwindowafter);
$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

$searchWordsEncoded = urlencode($searchkeywords);
$t->set_var("SEARCH_WORDS_ENCODED", $searchWordsEncoded);

$SaveTempalteURL = urlencode("ad_templates_saveart.php?templateid=$templateid&categorytemplate=$categorytemplate&editorview=$editorview&searchkeywords=$searchWordsEncoded&offset=$offset&closwindowafter=$closwindowafter&form_sc=" . WebUtil::getFormSecurityCode());
$t->set_var("TEMPLATE_SAVE_URL_ENCODED", $SaveTempalteURL);

$t->pparse("OUT","origPage");


?>