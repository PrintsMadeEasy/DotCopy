<?

require_once("library/Boot_Session.php");




$dbCmd = new DbCmd();


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);

$UserID = $AuthObj->GetUserID();




$t = new Templatex(".");


$t->set_file("origPage", "ad_coupons_usage-template.html");



$t->set_block("origPage","UsageBL","UsageBLout");


$CouponCode = WebUtil::GetInput("coupon", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

if(empty($CouponCode))
	throw new Exception("Missing Coupon Code");

$t->set_var("COUPON", $CouponCode);


// Get the Coupon ID
$dbCmd->Query("SELECT ID FROM coupons WHERE Code LIKE '" . DbCmd::EscapeLikeQuery($CouponCode) . "' AND DomainID=" . Domain::oneDomain());
if($dbCmd->GetNumRows() == 0)
	throw new Exception("Coupon does not exist.");
$CouponID = $dbCmd->GetValue();


$dbCmd->Query("SELECT UNIX_TIMESTAMP(DateOrdered) AS Date, BillingCompany, BillingName, ID FROM orders WHERE CouponID=$CouponID ORDER BY ID DESC");


$counter = 0;

while($row=$dbCmd->GetRow()){

	$t->set_var(array("ORDER_NUM"=>$row["ID"]));
	$t->set_var(array("DATE"=>date("M j, Y", $row["Date"])));
	$t->set_var(array("NAME"=>WebUtil::htmlOutput($row["BillingName"])));
	$t->set_var(array("COMPANY"=>WebUtil::htmlOutput($row["BillingCompany"])));

	$t->parse("UsageBLout","UsageBL",true);

	$counter++;
}


if($counter == 0)
	$t->set_var(array("UsageBLout"=>"<tr><td class='body'>No Coupon Usage.</td></tr>"));


$t->pparse("OUT","origPage");



?>