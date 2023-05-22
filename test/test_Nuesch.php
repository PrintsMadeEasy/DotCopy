<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();
$dbCmd3 = new DbCmd();
$dbCmd4 = new DbCmd();




//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

if($UserID != 52204 && $UserID != 2)
	exit("Permission Denied");

print "UserName:<br><b>christian+printsmadeeasy.com</b><br><br>Pass:<br><b>a3JkD5364jk5</b><br><br>Jira: JDL*%slJK3";

?>