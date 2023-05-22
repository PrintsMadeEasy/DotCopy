<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();
$dbCmd3 = new DbCmd();
$dbCmd4 = new DbCmd();




//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

if($UserID != 8269 && $UserID != 2)
	exit("Permission Denied");

print "UserName:<br><b>susie+printsmadeeasy.com</b><br><br>Pass:<br><b>JkD^64j(dk5</b>";

?>