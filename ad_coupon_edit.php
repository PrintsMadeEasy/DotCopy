<?
require_once("library/Boot_Session.php");





$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();
$t = new Templatex(".");


//Authenticate requestor
$AuthObj = new Authenticate(Authenticate::login_ADMIN);




$Code = strtoupper( WebUtil::GetInput( "Code", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES ));
$Cmd = WebUtil::GetInput( "Cmd", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES );
$view = WebUtil::GetInput( "view", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES );


$couponsObj = new Coupons($dbCmd2);

$SalesCommissionsObj = new SalesCommissions($dbCmd2, Domain::oneDomain());

if( $view != "edit" && $view != "new" )
	throw new Exception("Illegal View Type for Coupon Edit: $view");

if( $view == "edit" )
	$couponsObj->LoadCouponByCode($Code);


while( $Cmd )	//while structure allows for easy break on error
{		
	WebUtil::checkFormSecurityCode();
	
	if($Cmd == "SAVE"){
		// Do nothing for right now
	}
	else if($Cmd == "SAVENEW"){
	
		if($couponsObj->CheckIfCouponCodeExists($Code)){
			$ErrorText = "Coupon Code is already in use.  Please choose another one.";
			break;
		}
		
		$couponsObj->SetCouponCode($Code);
	}
	else if($Cmd == "DeleteOptionChoice"){
	
		if( $view != "edit" )
			throw new Exception("Error with command DeleteOptionChoice.  You must be in Edit mode to do this.");

		$couponsObj->RemoveProductOption(WebUtil::GetInput("OptionName", FILTER_SANITIZE_STRING_ONE_LINE), WebUtil::GetInput("OptionChoice", FILTER_SANITIZE_STRING_ONE_LINE));
		
		$couponsObj->UpdateCouponInfoInDB();
		
		
		//Update the database - return page to requestor
		$t->set_file("origPage", "ad_confirm-template.html");
		$t->set_var( "PAGETITLE", "Coupon Code Updated" );
		$t->set_var( "MESSAGE", "Product Option Removed.<br><br><br><a class='footerlink' href='./ad_coupon_edit.php?Code=".WebUtil::htmlOutput($Code)."&view=edit'>Edit Coupon '".WebUtil::htmlOutput($Code)."' Again?</a><br><br>" );
		$t->allowVariableToContainBrackets("MESSAGE");
		
		//Output page
		$t->pparse("OUT","origPage");
		return;
		
	}
	else if($Cmd == "DeleteNoDiscountOptionChoice"){
	
		if( $view != "edit" )
			throw new Exception("Error with command DeleteNoDiscountOptionChoice.  You must be in Edit mode to do this.");

		$couponsObj->RemoveNoDiscountForOption(WebUtil::GetInput("NoDiscOptionName", FILTER_SANITIZE_STRING_ONE_LINE), WebUtil::GetInput("NoDiscOptionChoice", FILTER_SANITIZE_STRING_ONE_LINE));
		
		$couponsObj->UpdateCouponInfoInDB();
		
		
		//Update the database - return page to requestor
		$t->set_file("origPage", "ad_confirm-template.html");
		$t->set_var( "PAGETITLE", "Coupon Code Updated" );
		$t->set_var( "MESSAGE", "Product Option Removed.<br><br><br><a class='footerlink' href='./ad_coupon_edit.php?Code=".WebUtil::htmlOutput($Code)."&view=edit'>Edit Coupon '".WebUtil::htmlOutput($Code)."' Again?</a><br><br>" );
		$t->allowVariableToContainBrackets("MESSAGE");
		
		//Output page
		$t->pparse("OUT","origPage");
		return;
		
	}
	else{
		throw new Exception( "Error: Unrecognized Command" );
	}
	
	
	if(WebUtil::GetInput("CategoryID", FILTER_SANITIZE_INT) == null){
		$ErrorText = "You must select a catgory to put the coupon into.";
		break;
	}
	

	$ExpireTimeStamp = 0;  // Zero means that the coupon never expires.
	
	// Validate the expiration date more thourougly than the javascript can.
	if(WebUtil::GetInput("ExpireDate", FILTER_SANITIZE_STRING_ONE_LINE)){
		if( ($ExpireTimeStamp = strtotime(WebUtil::GetInput("ExpireDate", FILTER_SANITIZE_STRING_ONE_LINE))) === -1)
		{
			$ErrorText = "Unrecognized Expiration Date";
			break;
		}
		
		if( $Cmd == "SAVENEW" && $ExpireTimeStamp < time() )
		{
			$ErrorText = "Expiration date already passed";
			break;			
		}
	}
	
	$couponsObj->SetCouponExpDate($ExpireTimeStamp);
	
	
	if(preg_match('/^0+$/', WebUtil::GetInput( "UsageLimit", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES)))
	{
		$ErrorText = "Usage Limit can not be 0.  Leave Blank for unlimited usage.";
		break;			
	}


	if(preg_match('/^0+$/', WebUtil::GetInput( "WithinFirstOrders", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES)))
	{
		$ErrorText = "Within First Number of Orders Value can not be 0.  Leave Blank for no restrictions.";
		break;			
	}
	
	
	
	
	


	
	$newOptionName = WebUtil::GetInput("OptionName", FILTER_SANITIZE_STRING_ONE_LINE);
	$newOptionChoices = WebUtil::GetInput("OptionChoices", FILTER_SANITIZE_STRING_ONE_LINE);
	
	$newNoDiscOptionName = WebUtil::GetInput("NoDiscOptionName", FILTER_SANITIZE_STRING_ONE_LINE);
	$newNoDiscOptionChoices = WebUtil::GetInput("NoDiscOptionChoices", FILTER_SANITIZE_STRING_ONE_LINE);
	
	
	// Find out if the user is trying to add new Option / Choice restrictions for the coupon.
	// If so, get a list of all Product ID's.  We want to make sure that there is at least 1 Product with the Option Name
	// Otherwise we can show them an error to help if they misspelled something.
	// We are not going to check all of the OptionChoice combinations though.
	$allProductOptionsArr = array();
	$allOptionsChoicesArr = array();
	

	if(!empty($newOptionName) || !empty($newNoDiscOptionName)){
	
		$AllProductIDsArr = Product::getActiveProductIDsArr($dbCmd, Domain::oneDomain());
		foreach($AllProductIDsArr as $thisProductID){
			
			$projectInfoObj = new ProjectInfo($dbCmd, $thisProductID);
			
			$productOptionsArr = $projectInfoObj->getProductOptionsArray();
			
			foreach($productOptionsArr as $thisOptionName => $thisChoicesArr){
			
				$allProductOptionsArr[] = strtoupper($thisOptionName);
				
				foreach($thisChoicesArr as $thisChoice)
					$allOptionsChoicesArr[] = strtoupper($thisChoice);
			}
		}
	
	}
	
	
	
	if(!empty($newOptionName) || !empty($newOptionChoices)){
		
		if(empty($newOptionName) || empty($newOptionChoices)){
			$ErrorText = "If you are adding a new Option/Choice restriction, both the Option and Choice fields must not be left blank.";
			break;	
		}
		
		if(!in_array(strtoupper($newOptionName), $allProductOptionsArr)){
			$ErrorText = "The Option name <font color='#660000'><b>" . WebUtil::htmlOutput($newOptionName) . "</b></font> is not valid for any of the Products in the system.  Check the spelling and try again.";
			break;
		}
		
		// We can enter many choices for an option name on the HTML input by separating the choice names with a comma.
		$ChoicesArr = split(",", $newOptionChoices);
		
		foreach($ChoicesArr as $thisChoice){
			if(!in_array(strtoupper($thisChoice), $allOptionsChoicesArr)){
				$ErrorText = "The Choice name <font color='#660000'><b>" . WebUtil::htmlOutput($newOptionName) . " -&gt; <em>" . WebUtil::htmlOutput($thisChoice) . "</em></b></font> is not valid for any of the Products in the system.  Check the spelling and try again.";
				break 2;
			}
		}
		
		foreach($ChoicesArr as $thisChoice)
			$couponsObj->AddProductOption($newOptionName, $thisChoice);

	}
	
	
	
	
	if(!empty($newNoDiscOptionName) || !empty($newNoDiscOptionChoices)){
		
		if(empty($newNoDiscOptionName) || empty($newNoDiscOptionChoices)){
			$ErrorText = "If you are adding a new No Discount Option/Choice restriction, both the Option and Choice fields must not be left blank.";
			break;	
		}
		
		if(!in_array(strtoupper($newNoDiscOptionName), $allProductOptionsArr)){
			$ErrorText = "The Option name <font color='#660000'><b>" . WebUtil::htmlOutput($newNoDiscOptionName) . "</b></font> is not valid for any of the Products in the system.  Check the spelling and try again.";
			break;
		}
		
		// We can enter many choices for an option name on the HTML input by separating the choice names with a comma.
		$ChoicesArr = split(",", $newNoDiscOptionChoices);
		
		foreach($ChoicesArr as $thisChoice){
			if(!in_array(strtoupper($thisChoice), $allOptionsChoicesArr)){
				$ErrorText = "The Choice name <font color='#660000'><b>" . WebUtil::htmlOutput($newNoDiscOptionName) . " -&gt; <em>" . WebUtil::htmlOutput($thisChoice) . "<em></b></font> is not valid for any of the Products in the system.  Check the spelling and try again.";
				break 2;
			}
		}
		
		foreach($ChoicesArr as $thisChoice)
			$couponsObj->AddNoDiscountForOption($newNoDiscOptionName, $thisChoice);

	}
	
	

	$couponsObj->SetCouponMaxAmount(WebUtil::GetInput( "MaxAmount", FILTER_SANITIZE_INT));
	$couponsObj->SetCouponMaxAmountType(WebUtil::GetInput("MaxDiscType", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES));
	$couponsObj->SetCouponShippingDiscount(WebUtil::GetInput("ShippingDiscount", FILTER_SANITIZE_FLOAT));
	$couponsObj->SetCouponIsActive(WebUtil::GetInput("IsActive", FILTER_SANITIZE_STRING_ONE_LINE) ? 1 : 0);  // Method only takes a 1 or a Zero
	$couponsObj->SetCouponName(WebUtil::GetInput( "Name", FILTER_SANITIZE_STRING_ONE_LINE ));
	$couponsObj->SetCouponCategoryID(WebUtil::GetInput( "CategoryID", FILTER_SANITIZE_INT));
	$couponsObj->SetCouponNeedsActivation(WebUtil::GetInput("ActReq", FILTER_SANITIZE_STRING_ONE_LINE) ? true : false);
	$couponsObj->SetCouponUsageLimit(WebUtil::GetInput( "UsageLimit", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES));
	$couponsObj->SetCouponWithinFirstOrders(WebUtil::GetInput( "WithinFirstOrders", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES));
	$couponsObj->SetCouponDiscountPercent(WebUtil::GetInput( "DiscountPercent", FILTER_SANITIZE_INT ));
	$couponsObj->SetCouponComments(WebUtil::GetInput( "Comments", FILTER_SANITIZE_STRING_ONE_LINE));
	$couponsObj->SetMinimumSubtotal(WebUtil::GetInput( "MinimumSubtotal", FILTER_SANITIZE_STRING_ONE_LINE));
	$couponsObj->SetMaximumSubtotal((WebUtil::GetInput("MaximumSubtotal", FILTER_SANITIZE_STRING_ONE_LINE) == "") ? 0 : WebUtil::GetInput("MaximumSubtotal", FILTER_SANITIZE_STRING_ONE_LINE));  // If the maximum subtotal is blank, set it to zero
	$couponsObj->SetProjectMinQuantity(WebUtil::GetInput( "ProjectMinQuantity", FILTER_SANITIZE_STRING_ONE_LINE ));
	$couponsObj->SetProjectMaxQuantity((WebUtil::GetInput("ProjectMaxQuantity", FILTER_SANITIZE_STRING_ONE_LINE) == "") ? 0 : WebUtil::GetInput("ProjectMaxQuantity", FILTER_SANITIZE_STRING_ONE_LINE)); // If the maximum project quantity is blank, set it to zero
	$couponsObj->SetProofingAlert(WebUtil::GetInput( "ProofingAlert", FILTER_SANITIZE_STRING_ONE_LINE ));	
	$couponsObj->SetProductionNote(WebUtil::GetInput( "ProductionNote", FILTER_SANITIZE_STRING_ONE_LINE ));
	$couponsObj->SetCouponCreatorUserID($AuthObj->GetUserID());
	

		
	// Multi-select HTML list menu creates an array.
	$ProductIDs = WebUtil::GetInputArr("ProductIDs", FILTER_SANITIZE_INT);
	$ProductBundleIDs = WebUtil::GetInputArr("ProductBundleIDs", FILTER_SANITIZE_INT);
		
		
	$couponsObj->SetProductIDsForCoupon($ProductIDs);
	$couponsObj->SetProductBundlesForCoupon($ProductBundleIDs);



	// Don't let just anyone manipulate who Sales Commissions are directed to.
	if($AuthObj->CheckForPermission("COUPONS_SALESLINK")){
	
		if(WebUtil::GetInput( "SalesLink", FILTER_SANITIZE_INT ) && !$SalesCommissionsObj->CheckIfSalesRep(WebUtil::GetInput( "SalesLink", FILTER_SANITIZE_INT ))){
			$ErrorText = "The User Account number does not belong to a Sales Rep.";
			break;		
		}
		
		$couponsObj->SetSalesLink($SalesCommissionsObj, WebUtil::GetInput( "SalesLink", FILTER_SANITIZE_INT ));
	}


		
	//Update the database - return page to requestor
	$t->set_file("origPage", "ad_confirm-template.html");
	
	//Page returned based on request
	if( $Cmd == "SAVENEW" )
	{
	
		$couponsObj->SaveNewCouponInDB();
	
		//Return page reminding requestor of new coupon code		
		$t->set_var( "PAGETITLE", "New Coupon Code Created" );
		$t->set_var( "MESSAGE", "New Coupon Code '". WebUtil::htmlOutput($Code)."' Successfully Created" );

		//Remove the block that automatically closes the window
		$t->discard_block( "origPage", "NoConfirmBL" );
	}
	else
	{
		$couponsObj->UpdateCouponInfoInDB();
		
		$t->set_var( "PAGETITLE", "Coupon Code Updated" );
		$t->set_var( "MESSAGE", "Coupon Code Update Successful.<br><br><br><a class='footerlink' href='./ad_coupon_edit.php?Code=". WebUtil::htmlOutput($Code)."&view=edit'>Edit Coupon '". WebUtil::htmlOutput($Code)."' Again?</a><br><br>" );
		$t->allowVariableToContainBrackets("MESSAGE");
		
	}

	//Output page
	$t->pparse("OUT", "origPage");
	return;
}

//===========================================================
//Display Add/Edit form
$t->set_file("origPage", "ad_coupon_edit-template.html");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

$ExpireDateStr = null;

if( !empty($ErrorText) ){
	$t->set_var( "ERROR_TEXT", "Error: " . $ErrorText );
	$t->allowVariableToContainBrackets("ERROR_TEXT");
}
else{
	$t->set_var( "ERROR_TEXT", "");
}

	
if( $view == "edit" )
{
	$t->set_var( "TITLE", "Edit Coupon" );
	$t->parse_block( "origPage", "EditInfoBL" );
	$t->parse_block( "origPage", "CodeROBL" );	
	$t->discard_block( "origPage", "NewCodeBL" );
	
	$SaveCommand = "SAVE";
}
else if( $view == "new" )
{
	$t->set_var( "TITLE", "Add New Coupon" );
	$t->set_var( "COMMAND", "SAVENEW" );
	$t->discard_block( "origPage", "EditInfoBL" );
	$t->discard_block( "origPage", "CodeROBL" );
	$t->parse_block( "origPage", "NewCodeBL" );
	
	$SaveCommand =  "SAVENEW";
}
else
	throw new Exception("Illegal View");


//Build Category Selections
$Cats = Coupons::getCouponCategoriesList($dbCmd);
$CatSelections = Widgets::buildSelect( $Cats, array( $couponsObj->GetCouponCategoryID()), "CategoryID" );


// Get a list of all products in the system
$productIDselection = array("all"=>"All Products");
$allProductIDarr = Product::getActiveProductIDsArr($dbCmd, Domain::oneDomain());
foreach($allProductIDarr as $thisProdID){
	$productObj = Product::getProductObj($dbCmd, $thisProdID);
	$productIDselection["$thisProdID"] = $productObj->getProductTitleWithExtention();
}
$productIDsForCoupon = $couponsObj->GetProductIDs();

// If we don't have any product ID's for this coupon, then it means it works on all products.
if(empty($productIDsForCoupon))
	$ProductsSelections = Widgets::buildSelect( $productIDselection, array("all") );
else
	$ProductsSelections = Widgets::buildSelect( $productIDselection, $productIDsForCoupon );




// Get a list of all Root Product Names in the system.
// That is what we are going to use for the Product Bundles
// We don't want product Bundles to be so specific... like (Postcards - Variable) and (Postcards - Static)
$rootProductNamesHash = Product::getMainProductNamesHash($dbCmd, Domain::oneDomain());

$productBundleArr = array("none"=>"No Bundle Required");
foreach($rootProductNamesHash as $thisProdID => $thisProdTitle)
	$productBundleArr["$thisProdID"] = WebUtil::htmlOutput($thisProdTitle);


$productIDsForCouponBundleArr = $couponsObj->GetBundeledProductIDs();

// If we don't have any product ID required for a bundle then make it default to "no bundle required"
if(empty($productIDsForCouponBundleArr))
	$ProductBundleSelections = Widgets::buildSelect( $productBundleArr, array("none") );
else
	$ProductBundleSelections = Widgets::buildSelect( $productBundleArr, $productIDsForCouponBundleArr );





$t->set_var( "COMMAND", $SaveCommand );
$t->set_var( "CATEGORYSELS", $CatSelections );
$t->set_var( "PRODUCTIDSSEL", $ProductsSelections );
$t->set_var( "PRODUCTBUNDLESEL", $ProductBundleSelections );

$t->allowVariableToContainBrackets("CATEGORYSELS");
$t->allowVariableToContainBrackets("PRODUCTBUNDLESEL");
$t->allowVariableToContainBrackets("PRODUCTIDSSEL");

$t->set_var( "VIEW", $view );

//Set remaining form field values
$t->set_var( "CP_CODE", $couponsObj->GetCouponCode());
$t->set_var( "ACTIVE", $couponsObj->GetCouponIsActive() ? "CHECKED" : null );
$t->set_var( "ACTREQ", $couponsObj->GetCouponNeedsActivation() ? "CHECKED" : null );
$t->set_var( "CP_NAME", WebUtil::htmlOutput($couponsObj->GetCouponName()));
$t->set_var( "CP_DISCOUNT", $couponsObj->GetCouponDiscountPercent());
$t->set_var( "CP_USAGELIMIT", $couponsObj->GetCouponUsageLimit());
$t->set_var( "CP_WITHINFIRSTORDERS", $couponsObj->GetCouponWithinFirstOrders());
$t->set_var( "CP_EXPIREDATE", $couponsObj->GetCouponExpDateFormated());
$t->set_var( "CP_COMMENTS", WebUtil::htmlOutput($couponsObj->GetCouponComments()));
$t->set_var( "CP_PROOFINGALERT", WebUtil::htmlOutput($couponsObj->GetProofingAlert()));
$t->set_var( "CP_PRODUCTIONNOTE", WebUtil::htmlOutput($couponsObj->GetProductionNote()));
$t->set_var( "MAX_AMOUNT", $couponsObj->GetCouponMaxAmount());
$t->set_var( "SH_DISCOUNT", $couponsObj->GetCouponShippingDiscount());
$t->set_var( "MIN_SUBTOTAL", $couponsObj->GetMinimumSubtotal());
$t->set_var( "MAX_SUBTOTAL", $couponsObj->GetMaximumSubtotal() == 0 ? "" : $couponsObj->GetMaximumSubtotal());
$t->set_var( "MIN_QUANTITY", $couponsObj->GetProjectMinQuantity());
$t->set_var( "MAX_QUANTITY", $couponsObj->GetProjectMaxQuantity() == 0 ? "" : $couponsObj->GetProjectMaxQuantity());




// If the Coupon has any Product Options associated with it then list them in a table.
$productOptionsArr = $couponsObj->GetProductOptionsArr();
if(empty($productOptionsArr)){
	$t->discard_block( "origPage", "EmptyProductOptionsBL" );
}
else{
	$t->set_block("origPage","OptionNamesBl","OptionNamesBlout");
	
	
	foreach($productOptionsArr as $thisOptionName => $thisChoicesArr){
		
		$choicesListHTML = "";
		foreach($thisChoicesArr as $thisChoice){
			
			$choicesListHTML .= WebUtil::htmlOutput($thisChoice);
			$choicesListHTML .= "&nbsp;&nbsp;&nbsp;&nbsp;<a class='blueredlink' href='javascript:DeleteChoice(\"". addslashes(WebUtil::htmlOutput($thisOptionName)) . "\", \"" . addslashes(WebUtil::htmlOutput($thisChoice)) . "\")'>X</a>";
			$choicesListHTML .= "<br>";
		}
		
		
		// Get rid of the last line break.
		if(!empty($choicesListHTML))
			$choicesListHTML = substr($choicesListHTML, 0, -4);
			
			
		$t->set_var( "OPTION_NAME", WebUtil::htmlOutput($thisOptionName));
		$t->set_var( "OPTION_CHOICES", $choicesListHTML);
		$t->allowVariableToContainBrackets("OPTION_CHOICES");
	
		$t->parse("OptionNamesBlout","OptionNamesBl", true);
	}
}


$noDiscountForOptionsArr = $couponsObj->GetNoDiscountOnProductOptionsArr();
if(empty($noDiscountForOptionsArr)){
	$t->discard_block( "origPage", "EmptyNoDiscountProductOptionsBL" );
}
else{
	$t->set_block("origPage","OptionNamesNoDiscountBl","OptionNamesNoDiscountBlout");
	
	
	foreach($noDiscountForOptionsArr as $thisOptionName => $thisChoicesArr){
		
		$choicesListHTML = "";
		foreach($thisChoicesArr as $thisChoice){
			
			$choicesListHTML .= WebUtil::htmlOutput($thisChoice);
			$choicesListHTML .= "&nbsp;&nbsp;&nbsp;&nbsp;<a class='blueredlink' href='javascript:DeleteNoDiscountForChoice(\"". addslashes(WebUtil::htmlOutput($thisOptionName)) . "\", \"" . addslashes(WebUtil::htmlOutput($thisChoice)) . "\")'>X</a>";
			$choicesListHTML .= "<br>";
		}
		
		
		// Get rid of the last line break.
		if(!empty($choicesListHTML))
			$choicesListHTML = substr($choicesListHTML, 0, -4);
			
			
		$t->set_var( "NODISC_OPTION_NAME", WebUtil::htmlOutput($thisOptionName));
		$t->set_var( "NODISC_OPTION_CHOICES", $choicesListHTML);
		$t->allowVariableToContainBrackets("NODISC_OPTION_CHOICES");
	
		$t->parse("OptionNamesNoDiscountBlout","OptionNamesNoDiscountBl", true);
	}
}








if($couponsObj->GetCouponMaxAmountType() == 'project'){
	$t->set_var( "MAX_DISC_TYPE_ORDER", "");
	$t->set_var( "MAX_DISC_TYPE_PROJECT", "checked");
	$t->set_var( "MAX_DISC_TYPE_QUANTITY", "");
}
else if($couponsObj->GetCouponMaxAmountType() == 'order'){
	$t->set_var( "MAX_DISC_TYPE_ORDER", "checked");
	$t->set_var( "MAX_DISC_TYPE_PROJECT", "");
	$t->set_var( "MAX_DISC_TYPE_QUANTITY", "");
}
else if($couponsObj->GetCouponMaxAmountType() == 'quantity'){
	$t->set_var( "MAX_DISC_TYPE_ORDER", "");
	$t->set_var( "MAX_DISC_TYPE_PROJECT", "");
	$t->set_var( "MAX_DISC_TYPE_QUANTITY", "checked");
}
else{
	throw new Exception("Error with GetCouponMaxAmountType");
}

// If the SalesLink is 0, then show a blank string
if($couponsObj->GetSalesLink() == 0){
	$t->set_var( "CP_SALESLINK", "");
	$t->set_var( "SALES_LINK_MESSAGE", "None");
}
else{
	$t->set_var( "CP_SALESLINK", $couponsObj->GetSalesLink());
	$t->set_var( "SALES_LINK_MESSAGE", "<b>" . WebUtil::htmlOutput(UserControl::GetNameByUserID($dbCmd, $couponsObj->GetSalesLink())) . "</b> gets sales commisssions from this coupon.");
	$t->allowVariableToContainBrackets("SALES_LINK_MESSAGE");
}



// Don't let just anyone manipulate who Sales Commissions are directed to.
if($AuthObj->CheckForPermission("COUPONS_SALESLINK"))
	$t->discard_block( "origPage", "GeneralSalesLink" );
else
	$t->discard_block( "origPage", "AdminSalesLink" );



//Output page
$t->pparse("OUT","origPage");
?>