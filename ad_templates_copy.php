<?

require_once("library/Boot_Session.php");




$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();


set_time_limit(4000);

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

WebUtil::checkFormSecurityCode();


// an array of check boxes from HTML
$movethis = WebUtil::GetInputArr("movethis", FILTER_SANITIZE_INT);

$MoveToCategoryID = WebUtil::GetInput("categoryid", FILTER_SANITIZE_INT);
$templatecommand = WebUtil::GetInput("templatecommand", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$productid = WebUtil::GetInput("productid", FILTER_SANITIZE_INT);
$destination_product_id = WebUtil::GetInput("destination_product_id", FILTER_SANITIZE_INT);
$domainIdOfTransfer = WebUtil::GetInput("domainid", FILTER_SANITIZE_INT);
$ReturnURL = WebUtil::GetInput("returl", FILTER_SANITIZE_URL);


// The Search Engine and Category templates share common tables.  
// Adjust the columns names for SQL in variables to keep code below common.
if($templatecommand == "CopyCategoryToEngine"){
	$OldTemplateView = "template_category";

	$sourceTableName = "artworkstemplates";
	$sourceTableID = "ArtworkID";
	
	$NewTemplateView = "template_searchengine";
	$ArtworkTemplateColumn_active = "SearchEngineID";
	$ArtworkTemplateColumn_inactive = "TemplateID";
}

else if($templatecommand == "CopyEngineToCategory"){
	
	$sourceTableName = "artworksearchengine";
	$sourceTableID = "ID";
	
	$OldTemplateView = "template_searchengine";
	$NewTemplateView = "template_category";
	$ArtworkTemplateColumn_active = "TemplateID";
	$ArtworkTemplateColumn_inactive = "SearchEngineID";
}
else if($templatecommand == "FinishDomainTransfer"){
	
	if(!$AuthObj->CheckIfUserCanViewDomainID($domainIdOfTransfer))
		throw new Exception("User can not copy templates to this domain.");
	
	$templateIdsArr = split("\|", WebUtil::GetInput("tempalte_id_pipe_list", FILTER_SANITIZE_STRING_ONE_LINE));
	
	// Before doing the transfer... make sure that the user has permission to each of the Product ID's.
	$lastProductID = 0;
	foreach($templateIdsArr as $thisTemplateID){
		
		$productIDSource = ProjectBase::GetProductIDFromProjectRecord($dbCmd, $thisTemplateID, "template_searchengine");
		
		if(empty($lastProductID)){
			$lastProductID = $productIDSource;
		}
		else{
			if($lastProductID != $productIDSource) 
				throw new Exception("This Product ID does not match the last one.");
		}
	}
	
	$domainIdOfSourceProduct = Product::getDomainIDfromProductID($dbCmd, $lastProductID);
	if(!$AuthObj->CheckIfUserCanViewDomainID($domainIdOfSourceProduct))
		throw new Exception("User can not copy templates from the Domain of the source Products.");
		
	$domainIdOfDestProduct = Product::getDomainIDfromProductID($dbCmd, $destination_product_id);
	if(!$AuthObj->CheckIfUserCanViewDomainID($domainIdOfDestProduct))
		throw new Exception("User can not copy templates to the Domain of the dest Product.");	
		
	
	print "<html><body><u>Transfering Templates &amp; Generating Thumbnails</u><br><br><br>";
	
	
	$lastPercent = 0;
	
	// Now loop through all of Source Templates and copy them into the new domain.
	$counter = 0;
	foreach($templateIdsArr as $thisTemplateID){

		// Insert the Artwork into the new domain.
		$tempalteArt = ArtworkLib::GetArtXMLfile($dbCmd, "template_searchengine", $thisTemplateID);
		
		$templateArt["ArtFile"] = $tempalteArt;
		$templateArt["ProductID"] = $destination_product_id;
		$templateArt["Sort"] = "M";
		$newTemplateId = $dbCmd->InsertQuery("artworksearchengine", $templateArt);
		
		// Now copy over the keywords.
		$dbCmd->Query("SELECT TempKw FROM templatekeywords WHERE TemplateID=" . intval($thisTemplateID));
		$tempalteKeywordsArr = $dbCmd->GetValueArr();
		
		foreach($tempalteKeywordsArr as $thisTemplateKeyword){
			$keywordArr["TempKw"] = $thisTemplateKeyword;
			$keywordArr["TemplateID"] = $newTemplateId;
			$dbCmd->InsertQuery("templatekeywords", $keywordArr);
		}
		
		$counter++;
		
		$percent = round($counter / sizeof($templateIdsArr) * 100);
	
		if($lastPercent != $percent){
			$lastPercent = $percent;
			print $percent . "%<br>                                                                      \n";
			flush();
			sleep(1);
		}
		
		ThumbImages::CreateTemplatePreviewImages($dbCmd, "template_searchengine", $newTemplateId);
	}
		
	print '</body>';
	print "<script>document.location = '".addslashes($ReturnURL)."';</script>\n</html>";
	exit();
}
else if($templatecommand == "CopyEngineToDomain"){
	
	$t = new Templatex(".");
	
	$t->set_file("origPage", "ad_templates_copy-template.html");
	
	$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
	$t->allowVariableToContainBrackets("HEADER");
	
	$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());
	$t->set_var("DOMAIN_TRANSFER_KEY", Domain::getDomainKeyFromID($domainIdOfTransfer));
	$t->set_var("DOMAIN_TRANSFER_ID", $domainIdOfTransfer);
	
	$t->set_var("RETURN_URL", $ReturnURL);
	

	if(!$AuthObj->CheckIfUserCanViewDomainID($domainIdOfTransfer))
		throw new Exception("User can not copy templates to this domain.");
	
	
	if(empty($movethis)){
		WebUtil::PrintAdminError("You must select at least one template to copy.");
	}

	$lastProductID = 0;
	foreach($movethis as $thisTemplateID){
		
		$productIDSource = ProjectBase::GetProductIDFromProjectRecord($dbCmd, $thisTemplateID, "template_searchengine");
		
		if(empty($lastProductID)){
			$lastProductID = $productIDSource;
		}
		else{
			if($lastProductID != $productIDSource) 
				throw new Exception("This Product ID does not match the last one.");
		}
	}
	
	$t->set_var("TEMPLATE_COUNT", sizeof($movethis));
	$t->set_var("TEMPLATE_ID_PIPE_SEPARATED", implode("|", $movethis));
	
	$sourceProductObj = new Product($dbCmd, $lastProductID);
	
	$t->set_var("PRODUCT_WIDTH", $sourceProductObj->getArtworkCanvasWidth());
	$t->set_var("PRODUCT_HEIGHT", $sourceProductObj->getArtworkCanvasHeight());
	
	// Get a list of Product ID's on the destination Domain which have matching artwork dimensions.
	$productIDsofDestDomain = Product::getActiveProductIDsArr($dbCmd, $domainIdOfTransfer);
	
	$productIdsMatchingArtwork = array();

	foreach($productIDsofDestDomain as $thisProductId){
		$destProductObj = new Product($dbCmd, $thisProductId);
	
		// Make sure that you are transfering to a product which creates its own templates.
		if($destProductObj->getProductIDforTemplates() != $thisProductId)
			continue;

		if($destProductObj->getArtworkCanvasWidth() == $sourceProductObj->getArtworkCanvasWidth() && $destProductObj->getArtworkCanvasHeight() == $sourceProductObj->getArtworkCanvasHeight())
			$productIdsMatchingArtwork[] = $thisProductId;
	}
	
	if(empty($productIdsMatchingArtwork)){
		$t->discard_block("origPage", "SelectProductForTransferBL");
	}
	else{
		
		$t->discard_block("origPage", "NoProductsAvailableWithMatchingDim");
		
		$t->set_block("origPage","ProductBL", "ProductBLout");
		
		foreach($productIdsMatchingArtwork as $thisProductID){
			
			$t->set_var("PRODUCT_ID", $thisProductID);
			$t->set_var("PRODUCT_NAME", WebUtil::htmlOutput(Product::getFullProductName($dbCmd, $thisProductID)));
			
			$t->parse("ProductBLout","ProductBL",true);
		}
	}
	
	
	$t->pparse("OUT","origPage");
	exit();
}
else{
	throw new Exception("Error with template command");
}
	
	
if(!empty($MoveToCategoryID)){
	
	$dbCmd->Query("SELECT COUNT(*) FROM templatecategories INNER JOIN products ON templatecategories.ProductID = products.ID 
					WHERE templatecategories.CategoryID=".intval($MoveToCategoryID)." AND DomainID=" . Domain::oneDomain());
	if($dbCmd->GetValue() == 0)
		throw new Exception("Error with Moving Category Tempalte Category. It does not exist.");
}


foreach($movethis as $copy_template){
	

	// Make sure the user has domain permissions for the Template ID.
	$dbCmd->Query("SELECT ProductID FROM $sourceTableName WHERE $sourceTableID =" . intval($copy_template));
	$productIDofTemplateID = $dbCmd->GetValue();
	$domainIDofTemplate = Product::getDomainIDfromProductID($dbCmd, $productIDofTemplateID);
	
	if(!$AuthObj->CheckIfUserCanViewDomainID($domainIDofTemplate))
		throw new Exception("TemplateID does not exist while trying to move or copy it.");
		
		
		

	// Now copy the category template between the serach engine or category tables
	if($templatecommand == "CopyCategoryToEngine"){

		$dbCmd->Query("SELECT * FROM artworkstemplates WHERE ArtworkID=$copy_template");
		$NewTemplateArr = $dbCmd->GetRow();

		unset($NewTemplateArr["ArtworkID"]);
		unset($NewTemplateArr["CategoryID"]);
		unset($NewTemplateArr["IndexID"]);
		$NewTemplateArr["Sort"] = "M"; //Start off with a sort letter of "M" which should keep it centered

		$NewTemplateID = $dbCmd->InsertQuery( "artworksearchengine", $NewTemplateArr );

	}
	else if($templatecommand == "CopyEngineToCategory"){

		// Just make sure that the category is still there.. another user may be deleted it or something
		$dbCmd->Query("SELECT COUNT(*) FROM templatecategories WHERE CategoryID=$MoveToCategoryID");
		if(!$dbCmd->GetValue())
			throw new Exception("The category that you are trying to move to was not found");


		$dbCmd->Query("SELECT * FROM artworksearchengine WHERE ID=$copy_template");
		$NewTemplateArr = $dbCmd->GetRow();

		unset($NewTemplateArr["ID"]);
		unset($NewTemplateArr["Sort"]);
		$NewTemplateArr["CategoryID"] = $MoveToCategoryID;
		$NewTemplateArr["IndexID"] = 50;   // Set the default index to something high, so it will appear on the bottom of the stack.  The sequence will be re-organized if the Index ID's are ever changed.

		$NewTemplateID = $dbCmd->InsertQuery( "artworkstemplates", $NewTemplateArr );
	}
	else
		throw new Exception("Error with template command");


	// Now copy all of the previews over the the search engine
	$dbCmd->Query("SELECT * FROM artworkstemplatespreview WHERE $ArtworkTemplateColumn_inactive = $copy_template ORDER BY ID");
	while($row=$dbCmd->GetRow()){
		$OldPreviewID = $row["ID"];

		$NewPreviewArr = $row;
		unset($NewPreviewArr["ID"]);
		$NewPreviewArr[$ArtworkTemplateColumn_inactive]=0;
		$NewPreviewArr[$ArtworkTemplateColumn_active]=$NewTemplateID;
		$newPreviewID = $dbCmd2->InsertQuery( "artworkstemplatespreview", $NewPreviewArr );

		//We want to copy over the preview images
		$OldImagePeviewFileName = Constants::GetTempImageDirectory () . "/" . ThumbImages::GetTemplatePreviewName($OldPreviewID, $OldTemplateView);
		if(file_exists($OldImagePeviewFileName)){
			$NewImagePeviewFileName = Constants::GetTempImageDirectory () . "/" . ThumbImages::GetTemplatePreviewName($newPreviewID, $NewTemplateView);

			#-- Open the file off of fisk and copy it to another file name
			$fd = fopen ($OldImagePeviewFileName, "r");
			$OldPrevImg = fread ($fd, 5000000);
			fclose ($fd);
			$fp = fopen($NewImagePeviewFileName, "w");
			fwrite($fp, $OldPrevImg);
			fclose($fp);
		}
	}

	//Copy over the admin thumbnail image
	$OldThumbPeviewFileName = Constants::GetTempImageDirectory () . "/" . ThumbImages::GetTemplatePreviewFnameAdmin($dbCmd, $copy_template, $OldTemplateView);
	if(file_exists($OldThumbPeviewFileName)){
		$NewThumbPeviewFileName = Constants::GetTempImageDirectory () . "/" . ThumbImages::GetTemplatePreviewFnameAdmin($dbCmd, $NewTemplateID, $NewTemplateView);

		#-- Open the file off of fisk and copy it to another file name
		$fd = fopen ($OldThumbPeviewFileName, "r");
		$OldThmbImg = fread ($fd, 5000000);
		fclose ($fd);
		$fp = fopen($NewThumbPeviewFileName, "w");
		fwrite($fp, $OldThmbImg);
		fclose($fp);
	}
}



if($templatecommand == "CopyCategoryToEngine")
	header("Location: " . WebUtil::FilterURL("./ad_templates.php?productid=$productid&templateview=template_searchengine"));
else if($templatecommand == "CopyEngineToCategory")
	header("Location: " . WebUtil::FilterURL("./ad_templates.php?productid=$productid&templateview=template_category&categorytemplate=$MoveToCategoryID"));




?>