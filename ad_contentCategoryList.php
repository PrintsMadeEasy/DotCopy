<?

require_once("library/Boot_Session.php");







$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);

$UserID = $AuthObj->GetUserID();



if(!$AuthObj->CheckForPermission("EDIT_CONTENT"))
	throw new Exception("Permission Denied");




$t = new Templatex(".");

$t->set_file("origPage", "ad_contentCategoryList-template.html");

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

$contentCategoryObj = new ContentCategory($dbCmd2);


$t->set_block("origPage","CategoryBL","CategoryBLout");

$productIDarr = array();
$dbCmd->Query("SELECT DISTINCT ProductID FROM contentcategories WHERE DomainID=" . Domain::oneDomain());
while($row = $dbCmd->GetRow()){
	if(!empty($row["ProductID"]))
		$productIDarr[] = $row["ProductID"];
}


$totalContentPages = 0;


foreach($productIDarr as $thisProductID){

	$productName = Product::getFullProductName($dbCmd, $thisProductID);

	$t->set_var("PRODUCT_NAME", "<br><br>" . WebUtil::htmlOutput($productName));
	$t->allowVariableToContainBrackets("PRODUCT_NAME");

	$categoryLinksHTML = "";
	
	$totalContentPagesPerCategory = 0;

	// Now get a list of categories belonging to the Product ID.
	$dbCmd->Query("SELECT ID, Title, DescriptionBytes FROM contentcategories WHERE ProductID=$thisProductID ORDER BY Title ASC");
	
	while($row = $dbCmd->GetRow()){
	
		$categoryID = $row["ID"];
		$categoryTitle = $row["Title"];
		
		$descriptionKiloBytes = round($row["DescriptionBytes"] / 1024, 1);
		
		$contentCategoryObj->loadContentByID($categoryID);
		$contentItemCount = $contentCategoryObj->countOfContentItemsUnder();
		$contentTempalteCount = $contentCategoryObj->countOfContentTemplatesUnder();
		
		$contentItemsBytes = $contentCategoryObj->getBytesOfContentItemsUnder();
		$contentTemplatesBytes = $contentCategoryObj->getBytesOfContentTemplatesUnder();
		
		$contentItemsKiloBytes = round($contentItemsBytes / 1024);
		$contentTemplatesKiloBytes = round($contentTemplatesBytes / 1024);
		
		
		$totalContentPages += $contentTempalteCount + $contentItemCount + 1;
		$totalContentPagesPerCategory += $contentTempalteCount + $contentItemCount + 1;
		
		if($contentItemCount > 0)
			$averageKiloBytesPerItem = round($contentItemsKiloBytes / $contentItemCount, 1);
		else
			$averageKiloBytesPerItem = 0;
			
		if($contentTempalteCount > 0)
			$averageKiloBytesPerTempalte = round($contentTemplatesKiloBytes / $contentTempalteCount, 1);
		else
			$averageKiloBytesPerTempalte = 0;
			
		$activeIndicator = "";
		if(!$contentCategoryObj->checkIfActive())
			$activeIndicator = "<font class='SmallBody' style='color=\"#cc0000\"'><i>inactive - </i></font> ";
		
		$categoryLinksHTML .= $activeIndicator . "<a href='./ad_contentCategory.php?viewType=edit&editCategoryID=" . $categoryID . "' class='BlueRedLink'>" .  WebUtil::htmlOutput($categoryTitle) . "</a>&nbsp;&nbsp;&nbsp;&nbsp; $descriptionKiloBytes KB <br><b>Items:</b> $contentItemCount &nbsp;&nbsp;&nbsp;  <font class='ReallySmallBody'>Total: $contentItemsKiloBytes KB, Avg: $averageKiloBytesPerItem KB</font><br><b>Templates:</b> $contentTempalteCount &nbsp;&nbsp;&nbsp; <font class='ReallySmallBody'>Total: $contentTemplatesKiloBytes KB, Avg: $averageKiloBytesPerTempalte KB</font><br><img src='./images/transparent.gif' width='5' height='15'><br>";
	
	}
	
	$t->set_var("CONTENT_CATEGORIES", $categoryLinksHTML );
	$t->set_var("TOTAL_PAGES_CATEGORY", $totalContentPagesPerCategory );
	$t->allowVariableToContainBrackets("CONTENT_CATEGORIES");
	

	$t->parse("CategoryBLout","CategoryBL",true);
}






// Now find out what Content Categories (if any) are not associated to a Product ID


// Now get a list of categories belonging to the Product ID.
$dbCmd->Query("SELECT ID, Title, DescriptionBytes FROM contentcategories WHERE ProductID IS NULL AND DomainID=".Domain::oneDomain()." ORDER BY Title ASC");

if($dbCmd->GetNumRows() > 0){

	$categoryLinksHTML = "";
	
	$t->set_var("PRODUCT_NAME", "<br><br>Not Linked to a Product");
	$t->allowVariableToContainBrackets("PRODUCT_NAME");
	
	$totalContentPagesPerCategory = 0;

	while($row = $dbCmd->GetRow()){

		$categoryID = $row["ID"];
		$categoryTitle = $row["Title"];
	
		$descriptionKiloBytes = round($row["DescriptionBytes"] / 1024, 1);
		
		$contentCategoryObj->loadContentByID($categoryID);
		$contentItemCount = $contentCategoryObj->countOfContentItemsUnder();

		$totalContentPages += $contentItemCount + 1;
		$totalContentPagesPerCategory += $contentItemCount + 1;

		$contentItemsBytes = $contentCategoryObj->getBytesOfContentItemsUnder();
		
		$contentItemsKiloBytes = round($contentItemsBytes / 1024);
		
		if($contentItemCount > 0)
			$averageKiloBytesPerItem = round($contentItemsKiloBytes / $contentItemCount, 1);
		else
			$averageKiloBytesPerItem = 0;

		$activeIndicator = "";
		if(!$contentCategoryObj->checkIfActive())
			$activeIndicator = "<font class='SmallBody' style='color=\"#cc0000\"'><i>inactive - </i></font> ";
			
		$categoryLinksHTML .= $activeIndicator . "<a href='./ad_contentCategory.php?viewType=edit&editCategoryID=" . $categoryID . "' class='BlueRedLink'>" .  WebUtil::htmlOutput($categoryTitle) . "</a>&nbsp;&nbsp;&nbsp;&nbsp; $descriptionKiloBytes KB <br><b>Items:</b> $contentItemCount  &nbsp;&nbsp;&nbsp;<font class='ReallySmallBody'>Total: $contentItemsKiloBytes KB, Avg: $averageKiloBytesPerItem KB</font>) <br><img src='./images/transparent.gif' width='5' height='5'><br>";

		
	}

	$t->set_var("CONTENT_CATEGORIES", $categoryLinksHTML );
	$t->allowVariableToContainBrackets("CONTENT_CATEGORIES");
	
	$t->set_var("TOTAL_PAGES_CATEGORY", $totalContentPagesPerCategory );
	
	$t->parse("CategoryBLout","CategoryBL",true);
}

if($totalContentPages == 0)
	$t->set_var("CategoryBLout", "");


$t->set_var("TOTAL_PAGES", $totalContentPages );





$t->pparse("OUT","origPage");





?>