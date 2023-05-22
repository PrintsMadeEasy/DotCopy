<?

require_once("library/Boot_Session.php");



$categorytemplate = WebUtil::GetInput("categorytemplate", FILTER_SANITIZE_INT);
$productid = WebUtil::GetInput("productid", FILTER_SANITIZE_INT);
$movetemplate = WebUtil::GetInput("movetemplate", FILTER_SANITIZE_STRING_ONE_LINE);
$moveto = WebUtil::GetInput("moveto", FILTER_SANITIZE_INT);
$movecategorydown = WebUtil::GetInput("movecategorydown", FILTER_SANITIZE_INT);
$movecategoryup = WebUtil::GetInput("movecategoryup", FILTER_SANITIZE_INT);
$templateview = WebUtil::GetInput("templateview", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$templateid = WebUtil::GetInput("templateid", FILTER_SANITIZE_INT);
$searchkeywords = WebUtil::GetInput("searchkeywords", FILTER_SANITIZE_STRING_ONE_LINE);
$replaceterm = WebUtil::GetInput("replaceterm", FILTER_SANITIZE_STRING_ONE_LINE);
$searchtype = WebUtil::GetInput("searchtype", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$offset = WebUtil::GetInput("offset", FILTER_SANITIZE_INT);
$DateRangeStartMonth = WebUtil::GetInput("DateRangeStartMonth", FILTER_SANITIZE_INT);
$DateRangeStartDay = WebUtil::GetInput("DateRangeStartDay", FILTER_SANITIZE_INT);
$DateRangeStartYear = WebUtil::GetInput("DateRangeStartYear", FILTER_SANITIZE_INT);
$DateRangeEndMonth = WebUtil::GetInput("DateRangeEndMonth", FILTER_SANITIZE_INT);
$DateRangeEndDay = WebUtil::GetInput("DateRangeEndDay", FILTER_SANITIZE_INT);
$DateRangeEndYear = WebUtil::GetInput("DateRangeEndYear", FILTER_SANITIZE_INT);
$compare_requests = WebUtil::GetInput("compare_requests", FILTER_SANITIZE_INT);
$compare_matches_s = WebUtil::GetInput("compare_matches_s", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$compare_matches_b = WebUtil::GetInput("compare_matches_b", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$returl = WebUtil::GetInput("returl", FILTER_SANITIZE_URL);

// $templatecommand works the same as $action ... however we ran into a problem 
// because in Javascript .action is a keyword... so we couldn't use it on a hidden input field
$templatecommand = WebUtil::GetInput("templatecommand", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

$offset = intval($offset);

//Setup variable
$TemplateRequestsNumResultsToDisplay = 800;
$searchEngineResultsToDisplay = 200;



$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();

set_time_limit(4000);


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

if(!$AuthObj->CheckForPermission("MANAGE_TEMPLATES"))
	WebUtil::PrintAdminError("Not Available");

	
	
	
	
// ------------------   Domain Authentication for any parameter in the URL --------------------------
if(!empty($categorytemplate)){
	
	$dbCmd->Query("SELECT COUNT(*) FROM templatecategories INNER JOIN products ON templatecategories.ProductID = products.ID 
					WHERE templatecategories.CategoryID=".intval($categorytemplate)." AND DomainID=" . Domain::oneDomain());
	if($dbCmd->GetValue() == 0)
		throw new Exception("Error with Template Category ID. It does not exist.");
}

if(!empty($productid)){
	
	// 3rd paramter will authenticate domain privelages.
	$productObj = new Product($dbCmd, $productid, true);

	$productIDforTemplates = $productObj->getProductIDforTemplates();
	if($productIDforTemplates != $productid)
		throw new Exception("Error displaying template collection, the Product ID specified does not create its own templates.");

	$domainIDofProduct = Product::getDomainIDfromProductID($dbCmd, $productid);
		
	Domain::enforceTopDomainID($domainIDofProduct);
}


if(!empty($templateid)){

	
	// The template may be in the search engine... or it may be categorized
	if($templateview == "template_category" || !empty($movetemplate)){
		$TableName = "artworkstemplates";
		$ColumnQual = "ArtworkID";
	}
	else if($templateview == "template_searchengine" || $templatecommand == "template_changekeywords" || $templatecommand == "updatesearchenginesort"){
		$TableName = "artworksearchengine";
		$ColumnQual = "ID";
	}
	else{
		throw new Exception("Error with templateview view, cannot delete template.");
	}
	
	$dbCmd->Query("SELECT ProductID FROM $TableName WHERE $ColumnQual =$templateid");
	$productIDofTemplateID = $dbCmd->GetValue();
	$domainIDofTemplate = Product::getDomainIDfromProductID($dbCmd, $productIDofTemplateID);
	
	if(!$AuthObj->CheckIfUserCanViewDomainID($domainIDofTemplate))
		throw new Exception("TemplateID does not exist.");
}

// ----------------------  End Domain Authentication ------------------------










$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
if(!empty($action)){
	
	WebUtil::checkFormSecurityCode();
	
	
	if($action == "deletecategory"){

		$dbCmd->Query("SELECT COUNT(*) FROM artworkstemplates WHERE CategoryID=$categorytemplate");
		if($dbCmd->GetValue() != 0)
			throw new Exception("Can not delete template category because it is not empty.");
		
		$dbCmd->Query("DELETE FROM templatecategories WHERE templatecategories.CategoryID=$categorytemplate");

		header("Location: " . WebUtil::FilterURL("./ad_templates.php?productid=" . $productid . "&nocache=". time() . "&templateview=". $templateview));
		exit;
	}
	else if($action == "SearchReplaceArtwork"){
		
		if(!$AuthObj->CheckForPermission("SEARCH_REPLACE_ARTWORK_TEMP"))
			throw new Exception("User does not have permission to search/replace.");
			
		$dbCmd->Query("SELECT ID FROM artworksearchengine WHERE ArtFile LIKE \"%" . DbCmd::EscapeLikeQuery($searchkeywords) . "%\" AND ProductID=$productid ORDER BY Sort ASC");
		$TemplateIDsArr = $dbCmd->GetValueArr();
		
		
		print "<html><body><u>Searching and Replacing Templates &amp; Generating Thumbnails</u><br><br><br>";
	
		$counter = 0;
		$lastPercent = 0;
		foreach($TemplateIDsArr as $thisTemplateID){
			$artworkXml = ArtworkLib::GetArtXMLfile($dbCmd, "template_searchengine", $thisTemplateID);
			$artInfoObj = new ArtworkInformation($artworkXml);
			
			$searchTermFound = false;
			
			for($i=0; $i < sizeof($artInfoObj->SideItemsArray); $i++){
				
				for($j=0; $j < sizeof($artInfoObj->SideItemsArray[$i]->layers); $j++){
					
					if($artInfoObj->SideItemsArray[$i]->layers[$j]->LayerType != "text")
						continue;
						
					// Don't let Search and Replaces happen on empty search terms.
					// But, we want to allow thumbnails to be re-generated.
					if(empty($searchkeywords)){
						$searchTermFound = true;
					}
					else{
						if(preg_match("/".preg_quote($searchkeywords)."/i", $artInfoObj->SideItemsArray[$i]->layers[$j]->LayerDetailsObj->message)){
							$artInfoObj->SideItemsArray[$i]->layers[$j]->LayerDetailsObj->message = preg_replace("/".preg_quote($searchkeywords)."/i", $replaceterm, $artInfoObj->SideItemsArray[$i]->layers[$j]->LayerDetailsObj->message);
							$searchTermFound = true;
						}
					}
				}
			}
			
			// Only update the database and template previews if there is at least search term found.
			if($searchTermFound){
				ArtworkLib::SaveArtXMLfile($dbCmd, "template_searchengine", $thisTemplateID, $artInfoObj->GetXMLdoc(), true);
				ThumbImages::CreateTemplatePreviewImages($dbCmd, "template_searchengine", $thisTemplateID);
			}

			$counter++;
			
			$percent = round($counter / sizeof($TemplateIDsArr) * 100);
			if($lastPercent != $percent){
				$lastPercent = $percent;
				print $percent . "%<br>                                                                      \n";
				flush();
				sleep(1);
			}
		}
		
		print '</body>';
		print "<script>document.location = '".addslashes($returl)."';</script>\n</html>";
		exit();
	}
	else if($action == "addproductcategory"){

		$insertArr[ "CategoryName"] = WebUtil::GetInput("categoryname", FILTER_SANITIZE_STRING_ONE_LINE);
		$insertArr[ "ProductID"] = $productid;
		$CategoryID = $dbCmd->InsertQuery("templatecategories",  $insertArr);


		header("Location: " . WebUtil::FilterURL("./ad_templates.php?categorytemplate=" . $CategoryID . "&productid=" . $productid . "&nocache=". time() . "&templateview=". $templateview));
		exit;
	}
	else if($action == "deletetemplate"){
	
	
		// The template may be in the search engine... or it may be categorized
		if($templateview == "template_category"){
			$TableName = "artworkstemplates";
			$ColumnQual = "ArtworkID";
			$PreviewColumn = "TemplateID";
		}
		else if($templateview == "template_searchengine"){
			$TableName = "artworksearchengine";
			$ColumnQual = "ID";
			$PreviewColumn = "SearchEngineID";

			$dbCmd->Query("DELETE FROM templatekeywords WHERE TemplateID =$templateid");
		}
		else
			throw new Exception("Error with templateview view, cannot delete template.");



		// Get all of the preview ID's from the template and try to remove preview images and thumbnails from disk --#
		$dbCmd->Query("SELECT ID FROM artworkstemplatespreview WHERE $PreviewColumn=$templateid");

		while($PreviewID = $dbCmd->GetValue()){
			$ImagePeviewFileName = Constants::GetTempImageDirectory () . "/" . ThumbImages::GetTemplatePreviewName($PreviewID, $templateview);
			@unlink($ImagePeviewFileName);
		}

		$ThumbPeviewFileName = Constants::GetTempImageDirectory () . "/" . ThumbImages::GetTemplatePreviewFnameAdmin($dbCmd, $templateid, $templateview);
		@unlink($ThumbPeviewFileName);
		
		// In case there are any links to/from this template... make sure to delete the associations.
		$tempalteLinksObj = new TemplateLinks();
		$tempalteLinksObj->removeAllLinksFromTemplate($tempalteLinksObj->getTemplateAreaFromViewType($templateview), $templateid);

		// Delete Template
		$dbCmd->Query("DELETE FROM $TableName WHERE $ColumnQual =$templateid");

		// Get rid of any preview images associated with the template.
		$dbCmd->Query("DELETE FROM artworkstemplatespreview WHERE $PreviewColumn =$templateid");

		header("Location: " . WebUtil::FilterURL($returl));
		exit;
	
	}

	else{
		throw new Exception("Undefined Action");
	}
}


if(!empty($templatecommand)){
	
	WebUtil::checkFormSecurityCode();

	if($templatecommand == "movetemplatestocategory"){
			
		// Multi-select HTML list menu creates an array.
		$movethis = WebUtil::GetInputArr("movethis", FILTER_SANITIZE_INT);
		$movecategory = WebUtil::GetInput("movecategory", FILTER_SANITIZE_INT);

		foreach($movethis as $move_template){
			
			
			// Ensure existence and domain privelages on Template Category
			$dbCmd->Query("SELECT COUNT(*) FROM templatecategories INNER JOIN products ON templatecategories.ProductID = products.ID 
							WHERE templatecategories.CategoryID=".intval($movecategory)." AND DomainID=" . Domain::oneDomain());
			if($dbCmd->GetValue() == 0)
				throw new Exception("Error in command movetemplatestocategory. The category does not exist.");
				
				
			// Ensure existence and domain privelages on Template ID
			$dbCmd->Query("SELECT COUNT(*) FROM artworkstemplates INNER JOIN products ON artworkstemplates.ProductID = products.ID 
							WHERE artworkstemplates.ArtworkID=".intval($move_template)." AND DomainID=" . Domain::oneDomain());
			if($dbCmd->GetValue() == 0)
				throw new Exception("Error in command movetemplatestocategory. The Tempalte ID does not exist.");
			
				
			$dbCmd->Query("UPDATE artworkstemplates set CategoryID=$movecategory WHERE ArtworkID=$move_template");
		}

		header("Location: " . WebUtil::FilterURL($returl));
		exit;
	}
	else if($templatecommand == "addmultikeywords"){
		
		// Multi-select HTML list menu creates an array.
		$movethis = WebUtil::GetInputArr("movethis", FILTER_SANITIZE_INT);	

		$thekeywords = WebUtil::GetInput("thekeywords", FILTER_SANITIZE_STRING_ONE_LINE);

		foreach($movethis as $tempID){
			
			// Ensure existence and domain privelages on Template ID
			$dbCmd->Query("SELECT COUNT(*) FROM artworksearchengine INNER JOIN products ON artworksearchengine.ProductID = products.ID 
							WHERE artworksearchengine.ID=".intval($tempID)." AND DomainID=" . Domain::oneDomain());
			if($dbCmd->GetValue() == 0)
				throw new Exception("Error in command addmultikeywords. The Tempalte ID does not exist.");
				
			ArtworkTemplate::AddKeywordsToTemplate($dbCmd, $thekeywords, $tempID);
		}

		header("Location: " . WebUtil::FilterURL($returl));
		exit;
	}
	else if($templatecommand == "deletemultikeywords"){
		
		// Multi-select HTML list menu creates an array.
		$movethis = WebUtil::GetInputArr("movethis", FILTER_SANITIZE_INT);	

		$KewordArr = WebUtil::GetKeywordArr(WebUtil::GetInput("thekeywords", FILTER_SANITIZE_STRING_ONE_LINE));

		foreach($movethis as $tempID){
			
			// Ensure existence and domain privelages on Template ID
			$dbCmd->Query("SELECT COUNT(*) FROM artworksearchengine INNER JOIN products ON artworksearchengine.ProductID = products.ID 
							WHERE artworksearchengine.ID=".intval($tempID)." AND DomainID=" . Domain::oneDomain());
			if($dbCmd->GetValue() == 0)
				throw new Exception("Error in command addmultikeywords. The Tempalte ID does not exist.");
			
			foreach($KewordArr as $thisKeyword)
				$dbCmd->Query("DELETE FROM templatekeywords WHERE TemplateID=".intval($tempID)." AND TempKw LIKE '" . DbCmd::EscapeLikeQuery($thisKeyword)  . "'");
		}

		header("Location: " . WebUtil::FilterURL($returl));
		exit;
	}
	else if($templatecommand == "updatesearchenginesort"){
	
		
		$tempsort = strtoupper(WebUtil::GetInput("tempsort", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES));
		
		// Ensure existence and domain privelages on Template ID
		$dbCmd->Query("SELECT COUNT(*) FROM artworksearchengine INNER JOIN products ON artworksearchengine.ProductID = products.ID 
						WHERE artworksearchengine.ID=".intval($templateid)." AND DomainID=" . Domain::oneDomain());
		if($dbCmd->GetValue() == 0)
			throw new Exception("Error in command updatesearchenginesort. The Tempalte ID does not exist.");

		$dbCmd->Query("UPDATE artworksearchengine set Sort='".DbCmd::EscapeSQL($tempsort)."' WHERE ID=$templateid");

		header("Location: " . WebUtil::FilterURL($returl));
		exit;
	}
	else if($templatecommand == "template_changekeywords"){
	
		
		// Ensure existence and domain privelages on Template ID
		$dbCmd->Query("SELECT COUNT(*) FROM artworksearchengine INNER JOIN products ON artworksearchengine.ProductID = products.ID 
						WHERE artworksearchengine.ID=".intval($templateid)." AND DomainID=" . Domain::oneDomain());
		if($dbCmd->GetValue() == 0)
			throw new Exception("Error in command template_changekeywords. The Tempalte ID does not exist.");
	
		$dbCmd->Query("DELETE FROM templatekeywords WHERE TemplateID=$templateid");

		$keywords = WebUtil::GetInput("keywords", FILTER_SANITIZE_STRING_ONE_LINE);

		ArtworkTemplate::AddKeywordsToTemplate($dbCmd, $keywords, $templateid);

		header("Location: " . WebUtil::FilterURL($returl));
		exit;
	}
	else{
		throw new Exception("Undefined Tempalte Command");
	}
	

}





$t = new Templatex(".");


$t->set_file("origPage", "ad_templates-template.html");

$t->set_var("CATEGORYTEMPLATESELECTED", "");

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

$templateLinksObj = new TemplateLinks();

$t->set_var(array(
		"TEMPLATEVIEW"=>$templateview, 
		"PERIODISTIMEFRAME"=>"0"));

// Find out if we are viewing templates for a product
if(!empty($productid)){

	if($templateview != "template_category" && $templateview != "template_searchengine" && $templateview != "template_keywords" && $templateview != "template_requests")
		throw new Exception("Error With Template View");

	
	// This is our base URL for displaying this template catetgory.  We can tack on extra commands to it if we want to shift the order of indexing or something.
	$currentURL = "./ad_templates.php?productid=" . $productid . "&categorytemplate=" . $categorytemplate . "&templateview=" . $templateview . "&offset=" . $offset . "&searchkeywords=" . urlencode($searchkeywords);    
	$currentURLEncoded = urlencode($currentURL);


	// Build the tabs
	$baseTabUrl = "./ad_templates.php?productid=$productid&templateview=";
	$TabsObj = new Navigation();
	$TabsObj->AddTab("template_category", "Categories", ($baseTabUrl . "template_category"));
	$TabsObj->AddTab("template_searchengine", "Search Engine", ($baseTabUrl . "template_searchengine"));
	$TabsObj->AddTab("template_keywords", "Keyword List", ($baseTabUrl . "template_keywords"));
	$TabsObj->AddTab("template_requests", "User Requests", ($baseTabUrl . "template_requests"));
	$t->set_var("NAV_BAR_HTML", $TabsObj->GetTabsHTML($templateview));
	$t->allowVariableToContainBrackets("NAV_BAR_HTML");




	$t->set_var(array("PRODUCTID"=>$productid, "CURRENT_URL"=>$currentURL, "CURRENT_URL_ENCODED"=>$currentURLEncoded));


	// Erase the block for displaying the Products
	$t->set_block("origPage","categoryBL","categoryBLout");
	$t->set_var(array("categoryBLout"=>""));


	$productObj = Product::getProductObj($dbCmd, $productid);
	$t->set_var("PRODUCTNAME", htmlentities($productObj->getProductTitleWithExtention()));
	
	$t->set_var("OFFSET", $offset);


	if($templateview == "template_category"){

		// Erase the block used by the search engine and keyword list
		$t->set_block("origPage","SearchEngineBL","SearchEngineBLout");
		$t->set_var(array("SearchEngineBLout"=>""));
		$t->set_block("origPage","KeywordsBL","KeywordsBLout");
		$t->set_var(array("KeywordsBLout"=>""));
		$t->set_block("origPage","RequestsBL","RequestsBLout");
		$t->set_var(array("RequestsBLout"=>""));
		
		
		$t->set_var(array("SEARCH_WORDS"=>""));
		$t->set_var(array("SEARCH_WORDS_ENCODED"=>""));

		$t->set_block("origPage","templateDisplayBL","templateDisplayBLout");
		$t->set_block("origPage","NewTemplateButtonBL","NewTemplateButtonBLout");

		$categoryDropDown = "<select name='movecategory' class='SmallBody'>\n";
		
		
		// If they don't have permission to add categories then delete the field
		if(!$AuthObj->CheckForPermission("TEMPLATE_CREATE_CATEGORIES"))
			$t->discard_block("origPage","AddCategoryField");
		


		// Find out how many categories there are for this product so that we can use it within our indexing algorythm
		$dbCmd->Query("SELECT COUNT(*) FROM templatecategories where ProductID=$productid");
		$NumberOfCategories = $dbCmd->GetValue();


		// Find out if a category shift command was sent through the URL
		// If so we need to make some changes to the DB before we start generating this page
		if(!empty($movecategorydown) || !empty($movecategoryup)){

			// We want to loop through all of the templates and re-order them accordingly. ----#.
			$dbCmd->Query("SELECT CategoryID FROM templatecategories where ProductID=$productid ORDER BY IndexID ASC");

			$categoryCounter = 0;
			while ($CategoryID = $dbCmd->GetValue()){

				$categoryCounter++;

				if(!empty($movecategorydown)){
					
					$movecategorydown = intval($movecategorydown);
					
					if($categoryCounter == $movecategorydown)
						$newIndexID = $categoryCounter + 1;
					else if($categoryCounter == $movecategorydown + 1)
						$newIndexID = $categoryCounter - 1;
					else
						$newIndexID = $categoryCounter;
				}
				else if(!empty($movecategoryup)){
					
					$movecategoryup = intval($movecategoryup);
					
					if($categoryCounter == $movecategoryup - 1)
						$newIndexID = $categoryCounter + 1;
					else if($categoryCounter == $movecategoryup)
						$newIndexID = $categoryCounter - 1;
					else
						$newIndexID = $categoryCounter;
				}

				$dbCmd2->Query("UPDATE templatecategories set IndexID=$newIndexID where CategoryID=$CategoryID");
			}

		}


		// Get all of category names for the particular product we are looking at.
		$dbCmd->Query("SELECT CategoryName, CategoryID FROM templatecategories 
				WHERE ProductID=$productid ORDER BY IndexID ASC");

		$CategoryCounter = 0;
		while ($row = $dbCmd->GetRow()){
			$print_empty_cart_message = false;


			$CategoryName = $row["CategoryName"];
			$CategoryTempID = $row["CategoryID"];

			if($CategoryCounter == 0){

				// If we are vieing this product but havent selected a specific category, then default to the fist category in the list.
				if($categorytemplate == "")
					$categorytemplate = $CategoryTempID;
			}

			$CategoryCounter++;


			// Only show index buttons if there is more than one category
			if($NumberOfCategories > 1){
				if($CategoryCounter == 1){
					$DownCommand = "<a class='RedLink' href='" . WebUtil::htmlOutput($currentURL) . "&movecategorydown=" . $CategoryCounter ."'>&gt;</a>";
					$UpCommand = "";
				}
				else if($CategoryCounter == $NumberOfCategories){
					$DownCommand = "";
					$UpCommand = "<a class='RedLink' href='" . WebUtil::htmlOutput($currentURL) . "&movecategoryup=" . $CategoryCounter ."'>&lt;</a>";
				}
				else{
					$DownCommand = "<a class='RedLink' href='" . WebUtil::htmlOutput($currentURL) . "&movecategorydown=" . $CategoryCounter ."'>&gt;</a>";
					$UpCommand = "<a class='RedLink' href='" .  WebUtil::htmlOutput($currentURL) . "&movecategoryup=" . $CategoryCounter ."'>&lt;</a>";
				}
			}
			else {
					$DownCommand = "";
					$UpCommand = "";
			}

			if($categorytemplate == $CategoryTempID){
				$t->set_var(array("BGCOLOR"=>"#6699CC"));

				#-- Make the default be the category that we are viewing --#
				$categoryDropDown .= "<option value='$CategoryTempID' selected>".WebUtil::htmlOutput($CategoryName)."</option>\n";
			}
			else{
				$t->set_var(array("BGCOLOR"=>"#DDDDDD"));
				$categoryDropDown .= "<option value='$CategoryTempID'>".WebUtil::htmlOutput($CategoryName)."</option>\n";
			}

			$t->set_var(array("CATEGORYTEMPLATEID"=>$CategoryTempID));
			$t->set_var(array("TEMPLATECATEGORY"=>WebUtil::htmlOutput($CategoryName), "MOVECATUP"=>$UpCommand, "MOVECATDOWN"=>$DownCommand));
			$t->parse("templateDisplayBLout","templateDisplayBL",true);
			
			$t->allowVariableToContainBrackets("MOVECATUP");
			$t->allowVariableToContainBrackets("MOVECATDOWN");

		}

		$categoryDropDown .= "</select>";

		if($CategoryCounter == 0){
			$t->set_var(array("templateDisplayBLout"=>"<td>No Categories Defined Yet.</td>"));
			$t->set_var(array("NewTemplateButtonBLout"=>""));
		}
		else{

			$t->set_var(array("CATEGORYTEMPLATESELECTED"=>$categorytemplate));
			$t->parse("NewTemplateButtonBLout","NewTemplateButtonBL",true);

		}


		// This is necessary in case the templateID is NULL.  We don't want the SQL query to complain.
		if(!empty($categorytemplate))
			$categorytemplateIDforSQL = $categorytemplate;
		else
			$categorytemplateIDforSQL = 0;

		$t->set_block("origPage","artworkTemplateBL","artworkTemplateBLout");


		// Find out how many templates are in this cateogeory so that we can use it within our indexing algorythm
		$dbCmd->Query("SELECT COUNT(*) FROM artworkstemplates where ProductID=$productid AND CategoryID=$categorytemplateIDforSQL");
		$NumberOfTemplates = $dbCmd->GetValue();

		// Find out if a template shift command was sent through the URL
		// If so we need to make some changes to the DB before we start generating this page
		if(!empty($movetemplate)){
			
			$moveto = intval($moveto);
			if(empty($moveto))
				throw new Exception("Error trying to move the index ID of template.");

				
			// Ensure existence and domain privelages on Template ID
			$dbCmd->Query("SELECT COUNT(*) FROM artworkstemplates INNER JOIN products ON artworkstemplates.ProductID = products.ID 
							WHERE artworkstemplates.ArtworkID=".intval($templateid)." AND DomainID=" . Domain::oneDomain());
			if($dbCmd->GetValue() == 0)
				throw new Exception("Error in command movetemplatestocategory. The Tempalte ID does not exist.");

				
			// Make sure we are not trying to change the position of the template outside of range.
			$dbCmd->Query("SELECT CategoryID FROM artworkstemplates WHERE ArtworkID=$templateid");
			$categoryOfTemplate = $dbCmd->GetValue();
			
			$dbCmd->Query("SELECT COUNT(*) FROM artworkstemplates WHERE CategoryID=$categoryOfTemplate");
			$countOfTemplatesInCategory = $dbCmd->GetValue();
			
			if($moveto > $countOfTemplatesInCategory)
				throw new Exception("Error, the template is trying to be moved outside of range.");
			
				
			// Change the position of the Template that we are moving 
			$dbCmd->Query("UPDATE artworkstemplates set IndexID=$moveto where ArtworkID=$templateid");

			// We want to loop through all of the templates and re-order them accordingly.
			$dbCmd->Query("SELECT ArtworkID FROM artworkstemplates WHERE 
					ProductID=$productid AND CategoryID=$categorytemplateIDforSQL ORDER BY IndexID ASC");
			$templateCounter = 0;
			while ($ArtworkID = $dbCmd->GetValue()){

				// Skip updating the order of this template if it is the one to be moved.  It should have already been moved in a database call above
				if($ArtworkID <> $templateid){

					$templateCounter++;

					// If this is the case the remaining templates have a index 1 greater than the position we moved our target template to
					if($moveto <= $templateCounter)
						$query = "UPDATE artworkstemplates set IndexID=" . ($templateCounter + 1) . " where ArtworkID=$ArtworkID";
					else
						$query = "UPDATE artworkstemplates set IndexID=$templateCounter where ArtworkID=$ArtworkID";

					$dbCmd2->Query($query);
				}
			}

		}

		// Get all of the templates
		$dbCmd->Query("SELECT ArtworkID, ArtFile FROM artworkstemplates 
				WHERE ProductID=$productid AND CategoryID=$categorytemplateIDforSQL ORDER BY IndexID ASC");

		$templateCounter = 0;
		while ($row = $dbCmd->GetRow()){
			$print_empty_cart_message = false;

			$ArtworkID = $row["ArtworkID"];
			$Artfile = $row["ArtFile"];

			$templateCounter++;

			$ArtworkInfoObj = new ArtworkInformation($Artfile);

			$canvas_dimensions = "";

			for($i=0; $i< sizeof($ArtworkInfoObj->SideItemsArray); $i++)
				$canvas_dimensions .= round($ArtworkInfoObj->SideItemsArray[$i]->contentwidth /96,2) . "&quot; x ". round($ArtworkInfoObj->SideItemsArray[$i]->contentheight / 96, 2) .  "&quot;<br>";


			// Chop off the last 2 characters in the string.  Which is always a comma & space.
			$canvas_dimensions = substr($canvas_dimensions, 0, -2);

			// Only show index buttons if there is more than one template
			if($NumberOfTemplates > 1){

				// Make the drop down menu used to reorder the templates
				$dropDownHTML = "";
				for($i=1; $i<= $NumberOfTemplates; $i++){

					$dropDownValue = $ArtworkID . "|" . $i;

					if($templateCounter == $i)
						$dropDownHTML .= "<option value='$dropDownValue' selected>$i</option>\n";
					else
						$dropDownHTML .= "<option value='$dropDownValue'>$i</option>\n";
				}
			}
			else {
					$dropDownHTML = "";
					$UpCommand = "";
			}


			setOrderDescriptionTemplateVariable($t, $dbCmd2, "C", $ArtworkID);

			$ThumbnailPhoto = "./image_preview/" . ThumbImages::GetTemplatePreviewFnameAdmin($dbCmd2, $ArtworkID, "template_category");

			$t->set_var(array("TEMPID"=>$ArtworkID, "THUMB_PHOTO"=>$ThumbnailPhoto, "DIMESIONS"=>$canvas_dimensions, "ORDER_DROPDOWN"=>$dropDownHTML, "MOVECATEGORY"=>$categoryDropDown));
			$t->set_var("PROD_LINKS", $templateLinksObj->getNumberOfProductsLinkedToThis(TemplateLinks::TEMPLATE_AREA_CATEGORY, $ArtworkID));
			$t->allowVariableToContainBrackets("MOVECATEGORY");
			$t->allowVariableToContainBrackets("DIMESIONS");
			$t->allowVariableToContainBrackets("ORDER_DROPDOWN");
			
			$t->parse("artworkTemplateBLout","artworkTemplateBL",true);

		}

		$t->set_block("origPage","noResultsBL","noResultsBLout");
		if($templateCounter > 0){

			// As long as we have search results then display them in this block
			$t->parse("noResultsBLout","noResultsBL",true);
		}
		else{


			// We allow them to delete the template if there are no prodcuts left.
			if(!empty($categorytemplate))
				$theMessage = "No Templates Yet. <br><br><br>Click <a href='./ad_templates.php?form_sc=".WebUtil::getFormSecurityCode()."&action=deletecategory&categorytemplate=$categorytemplate&productid=$productid&templateview=$templateview'>here</a> to delete this category.";
			else
				$theMessage = "No Templates Yet.";

			// If there are no results then take out of block of HTML for displaying the templates and write the following message.
			$t->set_var("noResultsBLout", $theMessage);
		}
	}
	else if($templateview == "template_searchengine"){

		// If we do not have a search type specified in the URL then try to get the search type from our session.
		$searchtype = GetTimeDefaultValuesForSearch("TemplateSearchType", "searchtype", "broad", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
		
		// Remember the value for search type.. until it gets changed.
		$HTTP_SESSION_VARS['TemplateSearchType'] = $searchtype;
		
		$SearchSpecific = false;
		
		if($searchtype == "broad"){
			$t->set_var("SEARCHTYPE_BROAD","checked");
			$t->set_var("SEARCHTYPE_SPECIFIC","");
			$t->set_var("SEARCHTYPE_ARTWORK","");
			$t->discard_block("origPage", "SearchReplaceArtworkBl");
		}
		else if($searchtype == "specific"){
			$t->set_var("SEARCHTYPE_BROAD","");
			$t->set_var("SEARCHTYPE_SPECIFIC","checked");
			$t->set_var("SEARCHTYPE_ARTWORK","");
		 	$SearchSpecific = true;
		 	$t->discard_block("origPage", "SearchReplaceArtworkBl");
		}
		else if($searchtype == "artwork"){
			$t->set_var("SEARCHTYPE_BROAD","");
			$t->set_var("SEARCHTYPE_SPECIFIC","");
			$t->set_var("SEARCHTYPE_ARTWORK","checked");
			
			if(!$AuthObj->CheckForPermission("SEARCH_REPLACE_ARTWORK_TEMP"))
				$t->discard_block("origPage", "SearchReplaceArtworkBl");
		}
		else{
			throw new Exception("Error with Search Type");
		}
		
		


		// Clean up search words
		$searchkeywords = trim($searchkeywords);

		// If the user is searching by "artwork contents" override the default method of keyword searching.
		if($searchtype == "artwork"){
			$dbCmd->Query("SELECT ID FROM artworksearchengine WHERE ArtFile LIKE \"%" . DbCmd::EscapeLikeQuery($searchkeywords) . "%\" AND ProductID=$productid ORDER BY Sort ASC");
			$TemplateIDsArr = $dbCmd->GetValueArr();
		}
		else{
			$TemplateIDsArr = ArtworkTemplate::GetSearchResultsForTempaltes($dbCmd, $searchkeywords, "", $productid, $SearchSpecific);
		}

		$t->set_block("origPage","artworkSearchRowsBL","artworkSearchRowsBLout");
		
		$resultCounter = 0;
		foreach($TemplateIDsArr as $ThisTemplateID){

			// If we have many matches we only want to display those which are on the current page.  This is figured out by the offset parameter passed in the URL.
			if(($resultCounter >= $offset) && ($resultCounter < ($searchEngineResultsToDisplay + $offset))){

				$dbCmd->Query("SELECT Sort FROM artworksearchengine WHERE ID=$ThisTemplateID");
				$row = $dbCmd->GetRow();
				$TempSort = $row["Sort"];

				$dbCmd->Query("SELECT TempKw FROM templatekeywords 
						INNER JOIN artworksearchengine on templatekeywords.TemplateID = artworksearchengine.ID 
						WHERE artworksearchengine.ProductID=$productid 
						AND templatekeywords.TemplateID=$ThisTemplateID ORDER BY TempKw ASC");

				$keywordList = "";

				// Don't use the GetValue() method in case somone enters '0' for a keyword.
				while($thisRow = $dbCmd->GetRow()){
					$keywordList .= $thisRow["TempKw"] . " ";
				}

				$keywordList = trim($keywordList);

				
				setOrderDescriptionTemplateVariable($t, $dbCmd, "S", $ThisTemplateID);
				
				
				$ThumbnailPhoto = "./image_preview/" . ThumbImages::GetTemplatePreviewFnameAdmin($dbCmd, $ThisTemplateID, "template_searchengine");

				$t->set_var(array("KEYWORDS"=>$keywordList, 
						"TEMPID"=>$ThisTemplateID, 
						"SORT"=>$TempSort, 
						"THUMB_PHOTO"=>$ThumbnailPhoto,
						"PROD_LINKS"=>$templateLinksObj->getNumberOfProductsLinkedToThis(TemplateLinks::TEMPLATE_AREA_SEARCH_ENGINE, $ThisTemplateID)));

				$t->parse("artworkSearchRowsBLout","artworkSearchRowsBL",true);
			}
			
			$resultCounter++;
		
		}
		

		// This means that we have multiple pages of search results
		if($resultCounter > $searchEngineResultsToDisplay){

			// What are the name/value pairs AND URL  for all of the subsequent pages 
			$NV_pairs_URL = "productid=$productid&templateview=$templateview&searchtype=$searchtype&searchkeywords=" . urlencode($searchkeywords) . "&";
			$BaseURL = "./ad_templates.php";

			// Get a the navigation of hyperlinks to all of the multiple pages
			$NavigateHTML = Navigation::GetNavigationForSearchResults($BaseURL, $resultCounter, $NV_pairs_URL, $searchEngineResultsToDisplay, $offset);


			$t->set_var(array("NAVIGATE"=>$NavigateHTML, "RESULT_DESC"=>$resultCounter, "RESULTS_PER_PAGE"=>$searchEngineResultsToDisplay));
			$t->allowVariableToContainBrackets("NAVIGATE");
		}
		else{
			$t->set_var(array("NAVIGATE"=>"", "OFFSET"=>""));
			$t->discard_block("origPage","MultiPageSearchEngineBL");
			$t->discard_block("origPage","SecondMultiPageSearchEngineBL");
		}




		// If there are no Results for the keyword at all.
		if(sizeof($TemplateIDsArr) == 0){
		
			$t->set_block("origPage","noSearchEngineResultsBL","noSearchEngineResultsBLout");
			if($searchkeywords == "")
				$NoResultMessage = "<br><br><br><b>All templates have keywords associated with them.</b>";
			else
				$NoResultMessage = "<br><br><br><b>No Results</b>";

			
			$t->set_var("noSearchEngineResultsBLout", $NoResultMessage);
		}
		
		
		// If we are showing all of the templates WITHOUT keywords then there is now point in showing subQuery box below
		// Also make sure to check and make sure that there are some templates asscoiated wtih "no keyword".... Otherwise we may try to delete the same block of HTML twice
		if($searchkeywords == "" && sizeof($TemplateIDsArr) != 0){
			$t->set_block("origPage","SubSearchBL","SubSearchBLout");
			$t->set_var(array("SubSearchBLout"=>""));
		}
		
		
		// Get a list of all of the tempalte categories... in case we want to copy an artwork from the engine to the category selection
		$TemplateCatDropDown = array("0" => "Copy to Category");
		$dbCmd->Query("SELECT * FROM templatecategories WHERE ProductID=$productid ORDER BY IndexID ASC");		
		while($row = $dbCmd->GetRow())
			$TemplateCatDropDown[$row["CategoryID"]] = WebUtil::htmlOutput($row["CategoryName"]);
			
		$t->set_var("CATEGORY_DROPDOWN", Widgets::buildSelect($TemplateCatDropDown, array()));
		$t->allowVariableToContainBrackets("CATEGORY_DROPDOWN");

		
	
		// Erase the block used by the template by category block and Keyword list 
		$t->discard_block("origPage","TemplateCategoriesBL");
		$t->discard_block("origPage","KeywordsBL");
		$t->discard_block("origPage","RequestsBL");
		
		
		$t->set_var("SEARCH_WORDS", WebUtil::htmlOutput($searchkeywords));
		$t->set_var("SEARCH_WORDS_ENCODED", urlencode($searchkeywords));
		$t->set_var("NUMBER_TEMPLATES", sizeof($TemplateIDsArr));
		
	
	}
	else if($templateview == "template_keywords"){

		// Erase the block used by the search engine and category tempaltes
		$t->discard_block("origPage","SearchEngineBL");
		$t->discard_block("origPage","TemplateCategoriesBL");
		$t->discard_block("origPage","RequestsBL");

		// Don't include keywords that start with 'TC' or 'TS' because those are keywords put on templates automatically from Auto-Transfers on template linking.
		$dbCmd->Query("SELECT DISTINCT TempKw FROM templatekeywords AS tk 
				INNER JOIN artworksearchengine AS ae ON tk.TemplateID = ae.ID 
				WHERE ae.ProductID=$productid AND TempKw NOT LIKE 'TC%' AND TempKw NOT LIKE 'TS%' ORDER BY TempKw");

		$KeywordArr = array();

		// Don't use the GetValue() method in case somone enters '0' for a keyword.
		while($thisRow = $dbCmd->GetRow()){
			$KeywordArr[] .= strtolower($thisRow["TempKw"]);
		}
		

		//Since the HTML page as 4 columns... see how many should be in each row
		$maxRow = ceil(sizeof($KeywordArr)/6);
		
		$Col1HTML="";
		$Col2HTML="";
		$Col3HTML="";
		$Col4HTML="";
		$Col5HTML="";
		$Col6HTML="";

		
		$KeywordCounter=0;
		foreach($KeywordArr as $thisKeyword){
			$KeywordCounter++;
			
			$dbCmd->Query("SELECT COUNT(*) FROM templatekeywords AS tk 
					INNER JOIN artworksearchengine AS ae ON tk.TemplateID = ae.ID 
					WHERE ae.ProductID=$productid AND tk.TempKw LIKE \"" . DbCmd::EscapeLikeQuery($thisKeyword) . "\"");
			$TemplateCount = $dbCmd->GetValue();

			$TheLink = "<a href='./ad_templates.php?templateview=template_searchengine&productid=$productid&searchkeywords=" . urlencode(WebUtil::htmlOutput($thisKeyword)) . "' class='blueredlink' target='top'>".WebUtil::htmlOutput($thisKeyword)."</a> - <font class='ReallySmallBody'>$TemplateCount</font><br>";

			if($KeywordCounter > $maxRow *5)
				$Col6HTML.=$TheLink;
			else if($KeywordCounter > $maxRow *4)
				$Col5HTML.=$TheLink;
			else if($KeywordCounter > $maxRow *3)
				$Col4HTML.=$TheLink;
			else if($KeywordCounter > $maxRow *2)
				$Col3HTML.=$TheLink;
			else if($KeywordCounter > $maxRow)
				$Col2HTML.=$TheLink;
			else
				$Col1HTML.=$TheLink;

			
		}
		
		$t->set_var(array("COLUMN1"=>$Col1HTML, "COLUMN2"=>$Col2HTML, "COLUMN3"=>$Col3HTML, "COLUMN4"=>$Col4HTML, "COLUMN5"=>$Col5HTML, "COLUMN6"=>$Col6HTML));

		$t->allowVariableToContainBrackets("COLUMN1");
		$t->allowVariableToContainBrackets("COLUMN2");
		$t->allowVariableToContainBrackets("COLUMN3");
		$t->allowVariableToContainBrackets("COLUMN4");
		$t->allowVariableToContainBrackets("COLUMN5");
		$t->allowVariableToContainBrackets("COLUMN6");

	}
	else if($templateview == "template_requests"){
	

		// Erase the block used by the search engine and category tempaltes
		$t->discard_block("origPage","SearchEngineBL");
		$t->discard_block("origPage","TemplateCategoriesBL");
		$t->discard_block("origPage","KeywordsBL");
		

		$t->set_var("SEARCH_WORDS", WebUtil::htmlOutput($searchkeywords));


		$message = null;
		$date = getdate();


		// If we do not get criteria specified in the URL then try to get the information from our session.
		$PeriodType = GetTimeDefaultValuesForSearch("TemplatePeriod_type", "PeriodType", "TIMEFRAME", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
		$TimeFrameSel = GetTimeDefaultValuesForSearch("TemplatePeriod_TimeFrame", "TimeFrame", "TODAY", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
			
		$startday = GetTimeDefaultValuesForSearch("TemplatePeriod_startday", "DateRangeStartDay", "1", FILTER_SANITIZE_INT);
		$startmonth = GetTimeDefaultValuesForSearch("TemplatePeriod_startmonth", "DateRangeStartMonth", $date["mon"], FILTER_SANITIZE_INT);
		$startyear = GetTimeDefaultValuesForSearch("TemplatePeriod_startyear", "DateRangeStartYear",  $date["year"], FILTER_SANITIZE_INT);

		$endday = GetTimeDefaultValuesForSearch("TemplatePeriod_endday", "DateRangeEndDay", "1", FILTER_SANITIZE_INT);
		$endmonth = GetTimeDefaultValuesForSearch("TemplatePeriod_endmonth", "DateRangeEndMonth", $date["mon"], FILTER_SANITIZE_INT);
		$endyear = GetTimeDefaultValuesForSearch("TemplatePeriod_endyear", "DateRangeEndYear",  $date["year"], FILTER_SANITIZE_INT);

		// Make it sticky during the session
		$HTTP_SESSION_VARS['TemplatePeriod_type'] = $PeriodType;
		$HTTP_SESSION_VARS['TemplatePeriod_TimeFrame'] = $TimeFrameSel;
		$HTTP_SESSION_VARS['TemplatePeriod_startday'] = $startday;
		$HTTP_SESSION_VARS['TemplatePeriod_startmonth'] = $startmonth;
		$HTTP_SESSION_VARS['TemplatePeriod_startyear'] = $startyear;
		$HTTP_SESSION_VARS['TemplatePeriod_endday'] = $endday;
		$HTTP_SESSION_VARS['TemplatePeriod_endmonth'] = $endmonth;
		$HTTP_SESSION_VARS['TemplatePeriod_endyear'] = $endyear;
		
		$ReportPeriodIsDateRange = $PeriodType == "DATERANGE";


		// Format the dates that we want for MySQL for the date range
		if( $ReportPeriodIsDateRange )
		{
			$start_timestamp = mktime (0,0,0,$startmonth,$startday,$startyear);
			$end_timestamp = mktime (23,59,59,$endmonth,$endday,$endyear);

			if(  $start_timestamp >  $end_timestamp  )	
				$message = "Invalid Date Range Specified - Unable to Generate Report";
		}
		else
		{
			$ReportPeriod = Widgets::GetTimeFrame( $TimeFrameSel );
			$start_timestamp = $ReportPeriod[ "STARTDATE" ];
			$end_timestamp = $ReportPeriod[ "ENDDATE" ];
		}

		$start_mysql_timestamp = date("YmdHis", $start_timestamp);
		$end_mysql_timestamp  = date("YmdHis", $end_timestamp);

		if(floor(($end_timestamp - $start_timestamp) / 60 / 60 / 24 ) > 10){
			
		$PeriodType = GetTimeDefaultValuesForSearch("TemplatePeriod_type", "PeriodType", "TIMEFRAME", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
			WebUtil::SetSessionVar("TemplatePeriod_TimeFrame", "");
			WebUtil::SetSessionVar("TemplatePeriod_type", "");
			WebUtil::PrintAdminError("The Keyword Requests Report takes up a lot of processing power.  You can not run a report with a time range greater than 10 days.");
		}

		// Setup date range selections and and type
		$t->set_var( "PERIODTYPETIMEFRAME", $ReportPeriodIsDateRange ? null : "CHECKED" );
		$t->set_var( "PERIODISTIMEFRAME", $ReportPeriodIsDateRange ? "false" : "true" );
		$t->set_var(  "TIMEFRAMESELS", Widgets::BuildTimeFrameSelect( $TimeFrameSel ));
		$t->set_var( "PERIODTYPEDATERANGE", $ReportPeriodIsDateRange ? "CHECKED" : null );
		$t->set_var(  "DATERANGESELS", Widgets::BuildDateRangeSelect( $start_timestamp, $end_timestamp, "D" ));
		$t->set_var(  "MESSAGE", WebUtil::htmlOutput($message));
		

		$t->allowVariableToContainBrackets("TIMEFRAMESELS");
		$t->allowVariableToContainBrackets("DATERANGESELS");
		$t->allowVariableToContainBrackets("PERIODTYPEDATERANGE");


		// Now get the values used for comparing quantities
		// Create a drop down menu that is used for the Quantity limiters
		$QuantityLimiterArr = array(
			"gteq"=>"Greater Than or Equal",
			"equal"=>"Equal",
			"lteq"=>"Less Than or Equal"
			);

		$compare_requests_default = GetTimeDefaultValuesForSearch("Templates_compare_requests", "compare_requests", "gteq", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
		$compare_matches_s_default = GetTimeDefaultValuesForSearch("Templates_compare_matches_s", "compare_matches_s", "gteq", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
		$compare_matches_b_default = GetTimeDefaultValuesForSearch("Templates_compare_matches_b", "compare_matches_b", "gteq", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
		
		$numrequests = GetTimeDefaultValuesForSearch("Templates_compare_numrequests", "numrequests", "0", FILTER_SANITIZE_INT);	
		$nummatchess = GetTimeDefaultValuesForSearch("Templates_compare_nummatchess", "nummatchess", "0", FILTER_SANITIZE_INT);
		$nummatchesb = GetTimeDefaultValuesForSearch("Templates_compare_nummatchesb", "nummatchesb", "0", FILTER_SANITIZE_INT);
		

		// Make the selected choices sticky
		$HTTP_SESSION_VARS['Templates_compare_requests'] = $compare_requests_default;
		$HTTP_SESSION_VARS['Templates_compare_matches_s'] = $compare_matches_s_default;
		$HTTP_SESSION_VARS['Templates_compare_matches_b'] = $compare_matches_b_default;
		$HTTP_SESSION_VARS['Templates_compare_numrequests'] = $numrequests;
		$HTTP_SESSION_VARS['Templates_compare_nummatchess'] = $nummatchess;
		$HTTP_SESSION_VARS['Templates_compare_nummatchesb'] = $nummatchesb;
		
		$t->set_var("NUM_REQ", $numrequests);
		$t->set_var("NUM_MAT_S", $nummatchess);
		$t->set_var("NUM_MAT_B", $nummatchesb);

		$dropdown_requests = Widgets::buildSelect($QuantityLimiterArr, array($compare_requests_default));
		$t->set_var("OPTIONS_REQUESTS", $dropdown_requests);
		$t->allowVariableToContainBrackets("OPTIONS_REQUESTS");
		
		$dropdown_matches_s = Widgets::buildSelect($QuantityLimiterArr, array($compare_matches_s_default));
		$t->set_var("OPTIONS_MATCHES_S", $dropdown_matches_s);
		$t->allowVariableToContainBrackets("OPTIONS_MATCHES_S");

		$dropdown_matches_b = Widgets::buildSelect($QuantityLimiterArr, array($compare_matches_b_default));
		$t->set_var("OPTIONS_MATCHES_B", $dropdown_matches_b);
		$t->allowVariableToContainBrackets("OPTIONS_MATCHES_B");


		if( $message )
		{
			//Error occurred - discontinue report generation
			$t->set_block("origPage","TemplateRequestsNoResults","TemplateRequestsNoResultsout");
			$t->set_var("TemplateRequestsNoResultsout", "<font class='error'><br>Date Range is incorrect</font>");


			// Print Template
			$t->pparse("OUT","origPage");
			exit;
		}


		// Find out if we are also limiting by the keywords.
		// Put the keywords into OR string for SQL
		if(!empty($searchkeywords)){
			$SQLkeywords = " AND(";
			
			$KewordArr = WebUtil::GetKeywordArr($searchkeywords);
			
			foreach($KewordArr as $thisKeyword)
				$SQLkeywords .= " Keywords LIKE \"%" . DbCmd::EscapeLikeQuery($thisKeyword) . "%\" OR";

			// strip of the last 2 chars which should be "OR" 
			$SQLkeywords = substr($SQLkeywords, 0, -2);
			$SQLkeywords .= ") "; 
		
		}
		else{
			$SQLkeywords = "";
		}


		// Now Loop through all of the results for all phrases in this period
		$t->set_block("origPage","TemplateRequestsBL","TemplateRequestsBLout");

		// Get all of the searches logged for templates
		$dbCmd->Query("SELECT count(*) AS Count, Keywords FROM templaterequests 
				WHERE ProductID=$productid AND( Date BETWEEN $start_mysql_timestamp 
				AND $end_mysql_timestamp ) $SQLkeywords GROUP BY Keywords ORDER BY Count DESC");
		$resultCounter = 0;
		while($row = $dbCmd->GetRow()){
		
			$SearchCount = $row["Count"];
			$Phrase = $row["Keywords"];
			
			// There could be a secondary search term which is separated by a ^ symbol
			$multiPhrase = split("\^", $Phrase);
			$firstSearchTerm = $multiPhrase[0];
			$secondSearchTerm = "";
			
			if(isset($multiPhrase[1]))
				$secondSearchTerm = $multiPhrase[1];

			$TemplateIDs_broad = sizeof(ArtworkTemplate::GetSearchResultsForTempaltes($dbCmd2, $firstSearchTerm, $secondSearchTerm, $productid, false));
			$TemplateIDs_specific = sizeof(ArtworkTemplate::GetSearchResultsForTempaltes($dbCmd2, $firstSearchTerm, $secondSearchTerm, $productid, true));

			// Now we want to skip records that do not meet our quantity criteria
			if($compare_requests_default == "gteq"){
				if($SearchCount < $numrequests)
					continue;
			}
			else if($compare_requests_default == "lteq"){
				if($SearchCount > $numrequests)
					continue;
			}
			else if($compare_requests_default == "equal"){
				if($SearchCount != $numrequests)
					continue;
			}
			//---
			if($compare_matches_s_default == "gteq"){
				if($TemplateIDs_specific < $nummatchess)
					continue;
			}
			else if($compare_matches_s_default == "lteq"){
				if($TemplateIDs_specific > $nummatchess)
					continue;
			}
			else if($compare_matches_s_default == "equal"){
				if($TemplateIDs_specific != $nummatchess)
					continue;
			}
			//---
			if($compare_matches_b_default == "gteq"){
				if($TemplateIDs_broad < $nummatchesb)
					continue;
			}
			else if($compare_matches_b_default == "lteq"){
				if($TemplateIDs_broad > $nummatchesb)
					continue;
			}
			else if($compare_matches_b_default == "equal"){
				if($TemplateIDs_broad != $nummatchesb)
					continue;
			}
			//----------------------
			
			
			// If we have many matches we only want to display those which are on the current page.  This is figured out by the offset parameter passed in the URL.
			if(($resultCounter >= $offset) && ($resultCounter < ($TemplateRequestsNumResultsToDisplay + $offset))){

				$t->set_var(array(
					"PHRASE_HTML"=>WebUtil::htmlOutput($Phrase),
					"PHRASE_HTTP"=>urlencode($firstSearchTerm . " " . $secondSearchTerm),
					"REQUESTS"=>$SearchCount
					));


				$t->set_var(array(
					"MATCHES_B"=>$TemplateIDs_broad,
					"MATCHES_S"=>$TemplateIDs_specific
					));

				$t->parse("TemplateRequestsBLout","TemplateRequestsBL",true);
			}
			
			$resultCounter++;
		}
		
		
		
		// Find out the total number of requests during this period 
		$dbCmd->Query("SELECT count(*) FROM templaterequests WHERE 
				ProductID=$productid AND( Date 
				BETWEEN $start_mysql_timestamp AND $end_mysql_timestamp ) $SQLkeywords ");
				
		$t->set_var("SEARCH_NUMBER", $dbCmd->GetValue());
		
		
		// Set this block so that we can erase the multi-page navigation bar if there aren't multiple pages
		$t->set_block("origPage","MultiPageBL","MultiPageBLout");
		$t->set_block("origPage","SecondMultiPageBL","SecondMultiPageBLout");

		// This means that we have multiple pages of search results
		if($resultCounter > $TemplateRequestsNumResultsToDisplay){


			// What are the name/value pairs AND URL  for all of the subsequent pages 
			$NV_pairs_URL = "productid=$productid&templateview=$templateview&PeriodType=$PeriodType&DateRangeStartMonth=$DateRangeStartMonth&DateRangeStartDay=$DateRangeStartDay&DateRangeStartYear=$DateRangeStartYear&DateRangeEndMonth=$DateRangeEndMonth&DateRangeEndDay=$DateRangeEndDay&DateRangeEndYear=$DateRangeEndYear&compare_requests=$compare_requests&numrequests=$numrequests&compare_matches_s=$compare_matches_s&nummatchess=$nummatchess&compare_matches_b=$compare_matches_b&nummatchesb=$nummatchesb&";
			$BaseURL = "./ad_templates.php";

			// Get a the navigation of hyperlinks to all of the multiple pages
			$NavigateHTML = Navigation::GetNavigationForSearchResults($BaseURL, $resultCounter, $NV_pairs_URL, $TemplateRequestsNumResultsToDisplay, $offset);


			$t->set_var(array("NAVIGATE"=>$NavigateHTML, "RESULT_DESC"=>$resultCounter));
			$t->allowVariableToContainBrackets("NAVIGATE");
			
			$t->parse("MultiPageBLout","MultiPageBL",true);
			$t->parse("SecondMultiPageBLout","SecondMultiPageBL",true);
		}
		else{
			$t->set_var(array("NAVIGATE"=>"", "OFFSET"=>""));
			$t->set_var(array("MultiPageBLout"=>""));
			$t->set_var(array("SecondMultiPageBLout"=>""));
		}
		
		if($resultCounter == 0){
			$t->set_block("origPage","TemplateRequestsNoResults","TemplateRequestsNoResultsout");
			$t->set_var(array("TemplateRequestsNoResultsout"=>"<br><b>No Results</b>"));
		}
		
	
	}

}

// Ortherwise we are not looking at a product yet.  Let them pick a product 
else{


	$t->set_block("origPage","productBL","productBLout");

	$productIDarr = Product::getActiveProductIDsArr($dbCmd, Domain::oneDomain());
	foreach($productIDarr as $productID){

		$productObj = Product::getProductObj($dbCmd, $productID);
		
		// If the Product ID used for templates is not linked directly to the current product ID
		// Then we should even show a link for it
		if($productObj->getProductID() != $productObj->getProductIDforTemplates())
			continue;

		$t->set_var(array("PRODUCTID"=>$productID, 
				"PRODUCTNAME"=>htmlentities($productObj->getProductTitleWithExtention())));

		$t->parse("productBLout","productBL",true);
	}
	
	if(empty($productIDarr))
		$t->set_var("productBLout", "No products have been added to the system.");
	


	// Display the category Block of HTML
	$t->set_block("origPage","categoryBL","categoryBLout");
	$t->parse("categoryBLout","categoryBL",true);


	// Erase the block for product display
	$t->set_block("origPage","productDisplayBL","productDisplayBLout");
	$t->set_var("productDisplayBLout", "");


}


// Build a drop down for all of the domains that the user has permisison to. 
// Or discard the drop down menu if the user only has permission to one domain.
$userDomainIDsArr = $AuthObj->getUserDomainsIDs();
if(sizeof($userDomainIDsArr) > 1){
	$domainListMenu = array("0"=>"Copy to Another Domain");
	foreach($userDomainIDsArr as $thisDomainID){
		if($thisDomainID == Domain::oneDomain())
			continue;
		$domainListMenu[$thisDomainID] = Domain::getDomainKeyFromID($thisDomainID);
	}
	
	$t->set_var("DOMAIN_DROPDOWN", Widgets::buildSelect($domainListMenu, array("0")));
	$t->allowVariableToContainBrackets("DOMAIN_DROPDOWN");
}
else{
	$t->discard_block("origPage", "DomainDropDownBL");
}

// Don't give users the ability to initiate cross-domain copies unless they have permission to search/replace.  Otherwise there could be many menions of the domain name inside.
if(!$AuthObj->CheckForPermission("SEARCH_REPLACE_ARTWORK_TEMP"))
	$t->discard_block("origPage", "DomainDropDownBL");



$t->pparse("OUT","origPage");


function setOrderDescriptionTemplateVariable($t, $dbCmd, $templateArea, $templateID){
	
	// Because it can take a while to generate the tempaltes, there must be a parameter in the URL telling us to do the extra work.
	$showOrderCounts = WebUtil::GetInput("showOrderCounts", FILTER_SANITIZE_STRING_ONE_LINE);
	if($showOrderCounts != "yes"){
		$t->set_var("ORDER_DESC", "");
		return;
	}
	else{
		$t->discard_block("origPage", "ShowOrderCounts_CategoriesBL");
		$t->discard_block("origPage", "ShowOrderCounts_SearchEngineBL");
	}

	if($templateArea != "S" && $templateArea != "C")
		throw new Exception("The template area is invalid.");
	
	$templateID = intval($templateID);
	
	// See how many times this template has been ordered.
	$dbCmd->Query("SELECT COUNT(DISTINCT UserID) FROM projectsordered USE INDEX (projectsordered_FromTemplateID) 
				INNER JOIN orders ON orders.ID = projectsordered.OrderID 
				WHERE FromTemplateID=$templateID AND FromTemplateArea='$templateArea'");
	$templateCount = $dbCmd->GetValue();
	if(empty($templateCount)){
		$t->set_var("ORDER_DESC", "");
	}
	else{
		// Find out the date of the first time the tempalte was ordered.... that will allow us to figure out how many orders per month that it receives.
		$dbCmd->Query("SELECT UNIX_TIMESTAMP(DateOrdered) FROM projectsordered USE INDEX (projectsordered_FromTemplateID) 
				INNER JOIN orders ON orders.ID = projectsordered.OrderID 
				WHERE FromTemplateID=$templateID AND FromTemplateArea='$templateArea'
				ORDER BY orders.ID ASC LIMIT 1");
		$secondsSinceFirstOrder = time() - $dbCmd->GetValue();
		
		// prevent division by zero
		if(empty($secondsSinceFirstOrder))
			$secondsSinceFirstOrder = 1;
			
		$monthsFromFirstOrder = $secondsSinceFirstOrder / (60 * 60 * 24 * 30);

		$ordersPerMonthRatio =  round($templateCount / $monthsFromFirstOrder, 1);
		
		// Figure out a Font size based upon frequence ordered.
		if($ordersPerMonthRatio < 1){
			$ratioFontSize = "8px;";
			$ratioFontColor = "#333333;";
		}
		else if($ordersPerMonthRatio < 2){
			$ratioFontSize = "10px;";
			$ratioFontColor = "#663333;";
		}
		else if($ordersPerMonthRatio < 3){
			$ratioFontSize = "11px;";
			$ratioFontColor = "#993333;";
		}
		else if($ordersPerMonthRatio < 5){
			$ratioFontSize = "12px;";
			$ratioFontColor = "#aa3333;";
		}
		else if($ordersPerMonthRatio < 7){
			$ratioFontSize = "13px;";
			$ratioFontColor = "#bb3333;";
		}
		else if($ordersPerMonthRatio < 10){
			$ratioFontSize = "14px;";
			$ratioFontColor = "#cc3333;";
		}
		else if($ordersPerMonthRatio < 20){
			$ratioFontSize = "15px;";
			$ratioFontColor = "#dd3333;";
		}
		else if($ordersPerMonthRatio < 30){
			$ratioFontSize = "16px;";
			$ratioFontColor = "#ee3333;";
		}
		else {
			$ratioFontSize = "17px;";
			$ratioFontColor = "#ff3333;";
		}
		
		$t->set_var("ORDER_DESC", "<font style='font-size:$ratioFontSize ; color:$ratioFontColor'>" . number_format($templateCount) . " order" . LanguageBase::GetPluralSuffix($templateCount, "", "s") . " (" . $ordersPerMonthRatio . "/m)</font>" );
		$t->allowVariableToContainBrackets("ORDER_DESC");
	}
}

function BuildOrderDropDownList($totalNumber, $currentSelected, $t){

	$dropDownHTML = "";

	for($i=1; $i<= $totalNumber; $i++){
		if($currentSelected == $i)
			$dropDownHTML .= "<option value='' selected>$i</option>\n";
		else
			$dropDownHTML .= "<option value=''>$i</option>\n";
	}
}

function GetTimeDefaultValuesForSearch($SessionVarName, $URLvarName, $DefaultVal, $filterType){
		if(WebUtil::GetInput( $URLvarName, $filterType, "") == ""){
			if(WebUtil::GetSessionVar($SessionVarName, "") == ""){
				return $DefaultVal;
			}
			else{
				
				// Don't let "artwork" searches be the default (if it is not provided in the URL) and there are not any keywords
				if(WebUtil::GetSessionVar($SessionVarName) == "artwork" && WebUtil::GetInput("searchkeywords", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES) == "")
					return $DefaultVal;
					
				return WebUtil::GetSessionVar($SessionVarName);
			}
				
		}
		else{
			return WebUtil::GetInput( $URLvarName, $filterType);
		}
}

?>
