<?

require_once("library/Boot_Session.php");

WebUtil::RunInSecureModeHTTPS();


$dbCmd = new DbCmd();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_general);
$UserID = $AuthObj->GetUserID();


// First find out if the user has any saved projects
$dbCmd->Query("SELECT COUNT(*) FROM projectssaved WHERE UserID=$UserID");
$SavedCount = $dbCmd->GetValue();


VisitorPath::addRecord("Reorder Button");

// If they have any saved projects then direct them to that area... otherwise direct them to their order history
if($SavedCount > 0){
	header("Location: ./SavedProjects.php", true, 302);
	exit;
}
else{
	header("Location: ./order_history.php", true, 302);
	exit;
}


?>