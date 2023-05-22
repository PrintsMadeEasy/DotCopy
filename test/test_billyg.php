<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();
$dbCmd3 = new DbCmd();
$dbCmd4 = new DbCmd();




//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

if($UserID != 117246 && $UserID != 2)
	exit("Permission Denied");

print "Dot Graphics Cpanel: &nbsp;&nbsp; InkPaperDotG<br>BW: T8342844728T <br>B24: 9a0b7f9c<br>PC.com UN: postcard PW: 4JUJUBEE\$";

?>