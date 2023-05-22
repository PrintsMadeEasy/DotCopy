<?

require_once("library/Boot_Session.php");





$dbCmd = new DbCmd();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);

$UserID = $AuthObj->GetUserID();




$newcategory = WebUtil::GetInput("newcategory", FILTER_SANITIZE_STRING_ONE_LINE);
$deletecategory = WebUtil::GetInput("deletecategory", FILTER_SANITIZE_STRING_ONE_LINE);


$t = new Templatex(".");


$t->set_file("origPage", "ad_coupons_addcategory-template.html");



if(preg_match("/^\d{1,11}$/",$deletecategory)){

	// First make sure that there are no exisiting coupons.
	$dbCmd->Query("SELECT COUNT(*) FROM coupons WHERE CategoryID=$deletecategory");
	$couponCount = $dbCmd->GetValue();
	
	

	// Don't let people delete if there are coupons associated with the category
	if($couponCount == 0){
		$dbCmd->Query("DELETE FROM couponcategories WHERE ID=".intval($deletecategory)." AND DomainID=" . Domain::oneDomain());
	}
	else{
		$t->set_block("origPage","ErrorBL","ErrorBLout");
		$t->set_var(array("ErrorBLout"=>"<div align='center'>Sorry, you can not delete a category <br>when there are still coupons<br> associated with it.<br><br><a href='javascript:history.back();'>Go Back.</a></div>"));
		$t->pparse("OUT","origPage");
		exit;
	}

}
if(!empty($newcategory)){
	
	if(strlen($newcategory) > 30)
		throw new Exception("Error with coupon category length.");
	
	$dbCmd->Query("SELECT COUNT(*) FROM couponcategories WHERE Name LIKE '".DbCmd::EscapeLikeQuery($newcategory)."' AND DomainID=" . Domain::oneDomain());
	$existingCategory = $dbCmd->GetValue();
	
	if($existingCategory != 0){
		$t->set_block("origPage","ErrorBL","ErrorBLout");
		$t->set_var(array("ErrorBLout"=>"<div align='center'>That Category Name already Exists.<br><br><a href='javascript:history.back();'>Go Back.</a></div>"));
		$t->pparse("OUT","origPage");
		exit;
	}
	
	// Now store the settings into the DB
	$dbCmd->InsertQuery("couponcategories", array("Name"=>$newcategory, "DomainID"=>Domain::oneDomain()));
}



$t->set_block("origPage","CategoryBL","CategoryBLout");

$counter = 0;


$dbCmd->Query("SELECT ID, Name FROM couponcategories WHERE DomainID=".Domain::oneDomain()." ORDER BY Name");
while($row = $dbCmd->GetRow()){


	$t->set_var(array(
			"CATEGORY"=>WebUtil::htmlOutput($row["Name"]), 
			"CATID"=>$row["ID"]));

	$t->parse("CategoryBLout","CategoryBL",true);

	$counter++;
}


if($counter == 0){
	$t->set_block("origPage","NoCats","NoCatsout");
	$t->set_var(array("NoCatsout"=>"<br>&nbsp;&nbsp;&nbsp;None yet."));
}


$t->pparse("OUT","origPage");


?>