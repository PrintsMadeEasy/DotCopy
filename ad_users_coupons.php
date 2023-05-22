<?

require_once("library/Boot_Session.php");




$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


$addcouponcode = WebUtil::GetInput("addcouponcode", FILTER_SANITIZE_STRING_ONE_LINE);
$delcouponid = WebUtil::GetInput("delcouponid", FILTER_SANITIZE_INT);
$customer_id = WebUtil::GetInput("customer_id", FILTER_SANITIZE_INT);




$t = new Templatex(".");

$t->set_file("origPage", "ad_users_coupons-template.html");


$domainIDofUser = UserControl::getDomainIDofUser($customer_id);


if(!$AuthObj->CheckIfUserCanViewDomainID($domainIDofUser))
	throw new Exception("Error with coupon activation. User does not exist.");

	
$couponObj = new Coupons($dbCmd, $domainIDofUser);


if(preg_match("/^\d+$/",$delcouponid))
	$dbCmd->Query("DELETE FROM couponactivation WHERE CouponID=$delcouponid AND UserID=$customer_id");


if(!empty($addcouponcode)){
	
	WebUtil::checkFormSecurityCode();


	// We may be adding multiple coupons, separated by pipe symbols.
	$tempCouponArr = split(",", $addcouponcode);
	
	
	// Filter out empty spaces between commas
	$actCouponsArr = array();
	foreach($tempCouponArr as $thisCouponCode){
		
		$thisCouponCode = trim($thisCouponCode);
		
		if(!empty($thisCouponCode))
			$actCouponsArr[] = $thisCouponCode;
	}


	
	// Do Error checking on 1 or more Coupons.
	// If there are any errors then the script will exit... so no coupons get added.
	foreach($actCouponsArr as $activateThisCoupon){

		if(sizeof($actCouponsArr) > 1)
			$multipleDescStr = "<br><br>None of the coupons were activated.";
		else
			$multipleDescStr = "";
			


		if(!$couponObj->CheckIfCouponFormatIsOK($activateThisCoupon)){
			$t->set_block("origPage","ErrorBL","ErrorBLout");
			$t->set_var(array("ErrorBLout"=>"<div align='center'>Coupon code &quot;<i>$activateThisCoupon</i>&quot; has an incorrect format. $multipleDescStr<br><br><a href='javascript:history.back();'>Go Back.</a></div>"));
			$t->allowVariableToContainBrackets("ErrorBLout");
			$t->pparse("OUT","origPage");
			exit;
		}



		if(!$couponObj->CheckIfCouponCodeExists($activateThisCoupon)){
			$t->set_block("origPage","ErrorBL","ErrorBLout");
			$t->set_var(array("ErrorBLout"=>"<div align='center'>Coupon code &quot;<i>$activateThisCoupon</i>&quot; was not found. $multipleDescStr<br><br><a href='javascript:history.back();'>Go Back.</a></div>"));
			$t->allowVariableToContainBrackets("ErrorBLout");
			$t->pparse("OUT","origPage");
			exit;
		}
		
		$couponObj->LoadCouponByCode($activateThisCoupon);
		
		
		if(!$couponObj->GetCouponNeedsActivation()){
			$t->set_block("origPage","ErrorBL","ErrorBLout");
			$t->set_var(array("ErrorBLout"=>"<div align='center'>Coupon code &quot;<i>$activateThisCoupon</i>&quot; does not need user activation. $multipleDescStr<br><br><a href='javascript:history.back();'>Go Back.</a></div>"));
			$t->allowVariableToContainBrackets("ErrorBLout");
			$t->pparse("OUT","origPage");
			exit;
		}
	}


	// We can activate all coupons now because they have already been validated in the loop above.
	foreach($actCouponsArr as $activateThisCoupon){

		$couponObj->LoadCouponByCode($activateThisCoupon);
		$couponID = $couponObj->GetCouponID();

		// Don't activate duplicate coupons for the user. 
		$dbCmd->Query("SELECT COUNT(*) FROM  couponactivation WHERE CouponID=$couponID AND UserID=$customer_id");
		$couponActCount = $dbCmd->GetValue();
		if($couponActCount == 0){

			$insertArr["CouponID"] = $couponID;
			$insertArr["UserID"] = $customer_id;
			$dbCmd->InsertQuery("couponactivation",  $insertArr);
		}
	}

}



$t->set_block("origPage","ActivationBL","ActivationBLout");

$counter = 0;


$dbCmd->Query("SELECT ca.CouponID, cp.Name, cp.Code 
		FROM couponactivation AS ca INNER JOIN 
		coupons AS cp ON cp.ID = ca.CouponID WHERE ca.UserID=$customer_id");

while($row = $dbCmd->GetRow()){
	$CouponID = $row["CouponID"];
	$CouponName = $row["Name"];
	$CouponCode = $row["Code"];

	$t->set_var(array("COUPON_CODE"=>WebUtil::htmlOutput($CouponCode), 
			"COUPON_NAME"=>WebUtil::htmlOutput($CouponName), 
			"CPID"=>$CouponID));

	$t->parse("ActivationBLout","ActivationBL",true);

	$counter++;
}


if($counter == 0){
	$t->set_block("origPage","NoCats","NoCatsout");
	$t->set_var("NoCatsout", "<br><br>&nbsp;&nbsp;&nbsp;<font class='SmallBody'>No coupons have been activated yet.</font><br><br>");
}

$t->set_var("CUST_ID", $customer_id);
$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());









// ----- Show a List of Coupons that have been used by the customer.

$t->set_block("origPage","CouponsUsedBL","CouponsUsedBLout");

$counter = 0;

$dbCmd->Query("SELECT DISTINCT cp.ID, cp.Name, cp.Code, cp.SalesLink 
		FROM orders INNER JOIN 
		coupons AS cp ON cp.ID = orders.CouponID WHERE orders.UserID=$customer_id AND orders.CouponID > 0");

while($row = $dbCmd->GetRow()){
	
	$CouponID = $row["ID"];
	$CouponName = $row["Name"];
	$CouponCode = $row["Code"];
	$salesLink = $row["SalesLink"];
	

	$couponObj = new Coupons($dbCmd2, $domainIDofUser);
	$couponObj->LoadCouponByID($CouponID);
	$couponCodeDesc = WebUtil::htmlOutput($couponObj->GetSummaryOfCoupon());
	
	if(!empty($CouponName))
		$couponCodeDesc .= "<br><font class='SmallBody'><i>" . WebUtil::htmlOutput($CouponName) . "</i></font>";
	
	if(!empty($salesLink))
		$couponCodeDesc .= "<br><font class='ReallySmallBody' style='color:#660033'>Sales Rep: U" . $salesLink . "</font>";

	$t->set_var("COUPON_CODE_USED", WebUtil::htmlOutput($CouponCode));
	$t->set_var("COUPON_CODE_USED_DESC", $couponCodeDesc);
	$t->allowVariableToContainBrackets("COUPON_CODE_USED_DESC");
	
	$dbCmd2->Query("SELECT COUNT(*) FROM orders WHERE UserID=$customer_id AND CouponID=$CouponID");
	$couponCount = $dbCmd2->GetValue();
	$t->set_var("COUPON_COUNT_USED", $couponCount);

	$t->parse("CouponsUsedBLout","CouponsUsedBL",true);

	$counter++;
}

if($counter == 0){
	$t->set_block("origPage","NoCouponsUsedBL","NoCouponsUsedBLout");
	$t->set_var("NoCouponsUsedBLout", "<br><br>&nbsp;&nbsp;&nbsp;<font class='SmallBody'>No coupons used.</font><br><br>");
}


$t->pparse("OUT","origPage");



?>