<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();
$dbCmd3 = new DbCmd();
$dbCmd4 = new DbCmd();


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("EDIT_PRODUCT"))
		throw new Exception("You don't have permission to make a copy of a Product.");



$productID = WebUtil::GetInput("productID", FILTER_SANITIZE_INT);
$domainIDforNewProduct = WebUtil::GetInput("domainIDforNewProduct", FILTER_SANITIZE_INT);
$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

$productObj = new Product($dbCmd, $productID, true);


if($action){
	
	WebUtil::checkFormSecurityCode();

	if($action == "makeCopy"){
		
		if(empty($domainIDforNewProduct))
			$domainIDforNewProduct = Domain::oneDomain();
			
		if(!$AuthObj->CheckIfUserCanViewDomainID($domainIDforNewProduct))
			throw new Exception("User does not have permission to copy the Product into this domain: $domainIDforNewProduct");
	
		
		
		// Copy the Main Product Table over.
		$dbCmd->Query("SELECT * FROM products WHERE ID=" . intval($productID));
		$productRow = $dbCmd->GetRow();
		
		unset($productRow["ID"]);
		
		unset($productRow["ThumbnailCopyIconJPG"]);
		unset($productRow["ThumbnailCopyFileSize"]);
		unset($productRow["ThumbnailBackJPG"]);
		unset($productRow["ThumbBackWidth"]);
		unset($productRow["ThumbBackHeight"]);
		unset($productRow["ThumbnailBackFileSize"]);
		
		
		
		$productRow["DomainID"] = $domainIDforNewProduct;
		$productRow["ProductStatus"] = "D"; // Product Status starts off inactive.
		$productRow["ProductTitleExt"] .= " - Copied";
		
		$newProductID = $dbCmd->InsertQuery("products", $productRow);
		
		
		
		
		
		// Copy over all Quantity Price Breaks associated with the Product.
		$dbCmd->Query("SELECT * FROM productquantitybreaks WHERE ProductID=" . intval($productID));
		while($quantityBreakRow = $dbCmd->GetRow()){
				
			unset($quantityBreakRow["ID"]);
			$quantityBreakRow["ProductID"] = $newProductID;
			
			$dbCmd2->InsertQuery("productquantitybreaks", $quantityBreakRow);
		}
			
		
		
		
		// Copy over all Product Options... the Children Choices... and then the Quantity Breaks under each choice.
		$dbCmd->Query("SELECT * FROM productoptions WHERE ProductID=" . intval($productID));
		while($optionRow = $dbCmd->GetRow()){
			
			$sourceOptionID = $optionRow["ID"];
			
			unset($optionRow["ID"]);
			$optionRow["ProductID"] = $newProductID;
			
			$newOptionID = $dbCmd2->InsertQuery("productoptions", $optionRow);
			
			// Now Copy over all of the Choices associated with the Option
			$dbCmd2->Query("SELECT * FROM productoptionchoices WHERE ProductOptionID=$sourceOptionID");
			while($choiceRow = $dbCmd2->GetRow()){
				
				$sourceChoiceID = $choiceRow["ID"];
				
				unset($choiceRow["ID"]);
				$choiceRow["ProductOptionID"] = $newOptionID;
				
				$newChoiceID = $dbCmd3->InsertQuery("productoptionchoices", $choiceRow);
				
				
				// Now copy over all of the Choice Quantity Breaks.
				$dbCmd3->Query("SELECT * FROM productchoicequantbrks WHERE ProductOptionChoiceID=$sourceChoiceID");
				while($quanBreakRow = $dbCmd3->GetRow()){
					
					unset($quanBreakRow["ID"]);
					$quanBreakRow["ProductOptionChoiceID"] = $newChoiceID;
					
					$dbCmd4->InsertQuery("productchoicequantbrks", $quanBreakRow);
				}
			}
		}
		
		
		PDFprofile::createProofProfileIfDoesNotExist($dbCmd, $newProductID);
		
		
		
	
		// Redirect to the main product setup page for the Product that was just added.
		header("Location: ./" . WebUtil::FilterURL("ad_product_setup.php?view=productMain&editProduct=" . $newProductID));
		exit;
	}	
	else{
		throw new Exception("Undefined Action");
	}
}







// ------------------------------ Build HTML  ----------------------------------




$t = new Templatex(".");

$t->set_file("origPage", "ad_product_copy-template.html");

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());


$t->set_var("PRODUCT_ID", $productID);
$t->set_var("FULL_PRODUCT_NAME", WebUtil::htmlOutput($productObj->getProductTitleWithExtention()));

$userDomainIDsArr = $AuthObj->getUserDomainsIDs();

// If the user doesn't have permissions to multiple domains, then take away the drop down menu to choose a domain.
if(sizeof($userDomainIDsArr) <= 1){
	$t->discard_block("origPage", "MultiDomainBL");
}
else{
	
	$domainDropDown = array("0"=>"Same Domain");
	
	foreach($userDomainIDsArr as $thisDomainID){
		if($thisDomainID == Domain::oneDomain())
			continue;
		
		$domainDropDown[$thisDomainID] = Domain::getDomainKeyFromID($thisDomainID);
	}
	
	
	$t->set_var("DOMAIN_DROP_DOWN", Widgets::buildSelect($domainDropDown, 0));
	$t->allowVariableToContainBrackets("DOMAIN_DROP_DOWN");
}


$t->pparse("OUT","origPage");




