<?

require_once("library/Boot_Session.php");

$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

if(Constants::GetDevelopmentServer() || $UserID == 2)
	phpinfo();
else
	throw new Exception("Yer not an admin.");


?>