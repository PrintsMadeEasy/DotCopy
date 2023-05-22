<?

require_once("library/Boot_Session.php");

$customerid = WebUtil::GetInput("customerid", FILTER_SANITIZE_INT);





$dbCmd = new DbCmd();


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


$customerDomainID = UserControl::getDomainIDofUser($customerid);
if(!$AuthObj->CheckIfUserCanViewDomainID($customerDomainID))
	throw new Exception("Error loading the Customer ID. The Customer ID may not exist.");


$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
if($action){
	
	WebUtil::checkFormSecurityCode();

	if($action == "customerdiscount"){
		
		$discountpercent = WebUtil::GetInput("discountpercent", FILTER_SANITIZE_INT);
		$monthexpiration = WebUtil::GetInput("monthexpiration", FILTER_SANITIZE_INT);
		$yearexpiration = WebUtil::GetInput("yearexpiration", FILTER_SANITIZE_INT);
		$affiliatename = WebUtil::GetInput("affiliatename", FILTER_SANITIZE_STRING_ONE_LINE);

		$AffiliateDiscount = number_format(($discountpercent / 100), 2, '.', '');

		$ExpirationTimeStamp = mktime(0 , 0 , 0, $monthexpiration, 1, $yearexpiration);

		//Convert to Mysql Timestamp
		$ExpirationTimeStamp = date("YmdHis", $ExpirationTimeStamp);

		$updateArr["AffiliateExpires"] = $ExpirationTimeStamp;
		$updateArr["AffiliateDiscount"] = $AffiliateDiscount;
		$updateArr["AffiliateName"] = $affiliatename;
		$dbCmd->UpdateQuery("users", $updateArr, "ID=$customerid");

		?>
		<html>
		<script>
		window.opener.location = window.opener.location;
		self.close();
		</script>
		</html>
		<?
	
		exit;
	}
	else{
		throw new Exception("Undefined action");
	}
}




$t = new Templatex(".");

$t->set_file("origPage", "ad_discount-template.html");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

$QSelect = "SELECT AffiliateName, AffiliateDiscount, UNIX_TIMESTAMP(AffiliateExpires) AS Time FROM users WHERE ID=" . $customerid;
$dbCmd->Query( $QSelect );
$UserInfoHash = $dbCmd->GetRow();

if(empty($UserInfoHash["AffiliateDiscount"]))
	$UserInfoHash["AffiliateDiscount"] = 0;
if(empty($UserInfoHash["AffiliateDiscount"]))
	$UserInfoHash["AffiliateDiscount"] = 0;



// Make Customer Discount Drop down
$DiscountArr = array();
for($i=0; $i<=100; $i++)
	$DiscountArr["$i"] = $i;

$CustomerDiscount = round(100 * $UserInfoHash["AffiliateDiscount"]);
$DiscountDropDownHTML = Widgets::buildSelect($DiscountArr, array($CustomerDiscount));




// Make the month drop down
$MonthHash = array();
$MonthHash["1"] = "Jan";
$MonthHash["2"] = "Feb";
$MonthHash["3"] = "Mar";
$MonthHash["4"] = "Apr";
$MonthHash["5"] = "May";
$MonthHash["6"] = "Jun";
$MonthHash["7"] = "Jul";
$MonthHash["8"] = "Aug";
$MonthHash["9"] = "Sep";
$MonthHash["10"] = "Oct";
$MonthHash["11"] = "Nov";
$MonthHash["12"] = "Dec";

$MonthDropDownHTML = Widgets::buildSelect($MonthHash, array(date("n", $UserInfoHash["Time"])));



// Make the year drop down
$YearHash = array();
for($i=2002; $i<=2020; $i++)
	$YearHash["$i"] = $i;



$YearDropDownHTML = Widgets::buildSelect($YearHash, array( date("Y", $UserInfoHash["Time"])));

$t->set_var("AF_NAME", WebUtil::htmlOutput($UserInfoHash["AffiliateName"]));
$t->set_var("DISCOUNT", $DiscountDropDownHTML);
$t->set_var("MONTHDROPDOWN", $MonthDropDownHTML);
$t->set_var("YEARDROPDOWN", $YearDropDownHTML);
$t->set_var("CUSTOMERID", $customerid);

$t->allowVariableToContainBrackets("DISCOUNT");
$t->allowVariableToContainBrackets("MONTHDROPDOWN");
$t->allowVariableToContainBrackets("YEARDROPDOWN");

$t->pparse("OUT","origPage");



?>