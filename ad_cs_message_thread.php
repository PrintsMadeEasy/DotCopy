<?

require_once("library/Boot_Session.php");



$csitemid = WebUtil::GetInput("csitemid", FILTER_SANITIZE_INT);


$dbCmd = new DbCmd();

$domainObj = Domain::singleton();


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("VIEW_CUSTOMER_SERVICE"))
	WebUtil::PrintAdminError("Not Available");


if(!CustService::CheckIfCSitemExists($dbCmd, $csitemid))
	throw new Exception("Error CS item does not exist.");
	
	
// Get a hash containing the information of the message thread
$messageHash = CustService::GetMessagesFromCSitem($dbCmd, $csitemid);

$customerColor = "ddddFF";
$agentColor = "EEEEEE";


if(!preg_match("/^\w{6}$/", $customerColor))
	throw new Exception("Error with Customer Color");
if(!preg_match("/^\w{6}$/", $agentColor))
	throw new Exception("Error with Agent Color");

	
	
// To cut down on access to the server in all of the iframes, give each message thread a long expiration
// So when they Take control, and screen re-freshes, it doesn't go crazy hitting the server regenerating all of the messages (and causing large IP access errors).
// The source to the Iframe should have some type of Hash to make the URL unique if messages get added to the server.
header('Date: '. gmdate('D, d M Y H:i:s') . ' GMT');
header("Expires: " . gmdate("D, d M Y H:i:s", time() + 60 * 60 * 24 + 30) . " GMT"); 
header("Cache-Control: store, cache");
header("Pragma: public");

	
print "<html>
<head>
<title>Admin</title>
<LINK REL=stylesheet HREF='./library/stylesheet.css' TYPE='text/css'>
<body bgcolor='#FFFFFF' leftmargin='0' topmargin='0' marginwidth='0' marginheight='0'>
";

print CustService::FormatMessagesForCSItem($messageHash, ("#".$customerColor), ("#".$agentColor), true);
print "</body></html>";



?>