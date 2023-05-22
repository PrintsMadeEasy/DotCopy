<?

require_once("library/Boot_Session.php");





$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();
$dbCmd3 = new DbCmd();
$dbCmd4 = new DbCmd();


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);


if(!$AuthObj->CheckForPermission("EDIT_CONTENT"))
	throw new Exception("Permission Denied");




// --- This script is good for cycling through all of the content Categories and all grand children.... for updating the content.


throw new Exception("Needs an admin override.");


/*
$t = new Templatex();

$t->set_file("origPage", "ad_contentCategoryList-template.html");

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");


$contentCategoryObj = new ContentCategory($dbCmd2);
$contentItemObj = new ContentItem($dbCmd3);
$contentTemplateObj = new ContentTemplate($dbCmd4);


$t->set_block("origPage","CategoryBL","CategoryBLout");


$dbCmd->Query("SELECT ID FROM contentcategories");

while($row = $dbCmd->GetRow()){

	$contentCategoryObj->loadContentCategoryByID($row["ID"]);
	
	$contentCategoryObj->setDescription($contentCategoryObj->getDescription());
	
	$contentCategoryObj->updateContentCategory(2);
	
	$contentItemsIDarr = ContentCategory::GetContentItemsWithinCategory($dbCmd2, $row["ID"]);
	
	
	print "update ContentCategoryID: " . $row["ID"] . "<br>";
	
	foreach($contentItemsIDarr as $thisContentID){
	
		$contentItemObj->loadContentByID($thisContentID);
		
		$contentItemObj->setDescription($contentItemObj->getDescription());
		
		$contentItemObj->updateContentItem(2);
		
		
		$contentTemplateIDs = ContentItem::GetContentTemplatesIDsWithin($dbCmd3, $thisContentID);
		
		
		print "update ContentItemID: " . $thisContentID . "<br>";
		
		foreach($contentTemplateIDs as $thisTemplateID){
			
			
			$contentTemplateObj->loadContentByID($thisTemplateID);
			
			$contentTemplateObj->setDescription($contentTemplateObj->getDescription());

			$contentTemplateObj->updateContentTemplate(2);
			
			
			print "update ContentTemplateID: " . $thisTemplateID . "<br>";
		
		}
		
	
	}
}


print "done";


*/




?>