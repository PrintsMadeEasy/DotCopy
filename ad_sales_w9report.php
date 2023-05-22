<?

require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();

$SalesRepObj = new SalesRep($dbCmd);
$SalesCommissionsObj = new SalesCommissions($dbCmd, Domain::oneDomain());
$SalesPaymentsObj = new SalesCommissionPayments($dbCmd, $dbCmd2, Domain::oneDomain());





//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

if(!$AuthObj->CheckForPermission("SALES_MASTER"))
	throw new Exception("Permission Denied");

$year = WebUtil::GetInput("year", FILTER_SANITIZE_INT);

if(!$year)
	throw new Exception("The Year is a Required Parameter");



print "<font color='#990000'>Don't forget to View Source</font><br>\n\nSales Commission Report for the year of $year.\nThis report was generated on " . date("F j, Y, g:i a") . " \n\n";



print "--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------\n";
AddWhite("Amount", 10);
AddWhite("\$Amount", 12);
AddWhite("Name", 30);
AddWhite("Company", 50);
AddWhite("Address", 60);
AddWhite("City", 50);
AddWhite("State", 8);
AddWhite("Zip", 15);
AddWhite("TIN", 15);
AddWhite("TIN Type", 12);
AddWhite("Business Type", 20);
AddWhite("Business Type (other)", 25);
AddWhite("Exempt", 10);
AddWhite("Account Numbers", 25);
AddWhite("Date Signed", 20);
AddWhite("Filed By", 30);
AddWhite("Comments", 100);
print "\n";
print "--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------\n";







$SalesRepIDarr = array();
$dbCmd->Query("SELECT UserID FROM salesreps");
while($thisRepID = $dbCmd->GetValue())
	$SalesRepIDarr[] = $thisRepID;


foreach($SalesRepIDarr as $thisRepID){

	$YearlyCommissionTotal = $SalesPaymentsObj->GetPaymentTotalsWithinPeriodForUser($thisRepID, "ALL", $year);
	

	// We don't need to file Taxes if the Sales rep makes less than 600 dollars per year
	if($YearlyCommissionTotal < 600.00)
		continue;
	
	$SalesRepObj->LoadSalesRep($thisRepID);
	
	if($SalesRepObj->getIsAnEmployee())
		continue;

	AddWhite($YearlyCommissionTotal, 10);
	AddWhite('$' . $YearlyCommissionTotal, 12);
	AddWhite($SalesRepObj->getW9Name(), 30);
	AddWhite($SalesRepObj->getW9Company(), 50);
	AddWhite($SalesRepObj->getW9Address(), 60);
	AddWhite($SalesRepObj->getW9City(), 50);
	AddWhite($SalesRepObj->getW9State(), 8);
	AddWhite($SalesRepObj->getW9Zip(), 15);
	AddWhite($SalesRepObj->getW9TIN(), 15);
	
	// format the TIN Type
	if($SalesRepObj->getW9TINtype() == "S")
		AddWhite("SSN", 12);
	else if($SalesRepObj->getW9TINtype() == "E")
		AddWhite("EIN", 12);
	else
		throw new Exception("Illegal TIN Type: RepID: " . $thisRepID);

	// format the Business Type
	if($SalesRepObj->getW9BusinessType() == "I")
		AddWhite("Individual", 20);
	else if($SalesRepObj->getW9BusinessType() == "C")
		AddWhite("Corporation", 20);
	else if($SalesRepObj->getW9BusinessType() == "P")
		AddWhite("Partnership", 20);
	else if($SalesRepObj->getW9BusinessType() == "O")
		AddWhite("Other", 20);
	else
		throw new Exception("Illegal Business Type");

	AddWhite($SalesRepObj->getW9BusinessTypeOther(), 25);
	

	if($SalesRepObj->getW9Exempt())
		AddWhite("yes", 10);
	else
		AddWhite("no", 10);
	
	AddWhite($SalesRepObj->getW9AccountNumbers(), 25);
	AddWhite(date("n/j/y", $SalesRepObj->getW9DateSigned()), 20);
	AddWhite(UserControl::GetNameByUserID($dbCmd, $SalesRepObj->getW9FiledByUserID()), 30);
	AddWhite($SalesRepObj->getW9Coments(), 100);
	
	print "\n";


}




// Will take a string... if it does not meet the minimum characters then it will add additional white spaces
// It will also add 1 tab character --#
function AddWhite($str, $min_chars){

	if(strlen($str) < $min_chars){
		$str .= GetWhiteSpaces($min_chars - strlen($str)) . "\t";
	}
	print $str;

}
function GetWhiteSpaces($amount){
	$retStr = "";
	for($i=0; $i<$amount; $i++){
		$retStr .= " ";
	}
	return $retStr;
}





?>