<?

require_once("library/Boot_Session.php");

$view = WebUtil::GetInput("view", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$imageid = WebUtil::GetInput("imageid", FILTER_SANITIZE_INT);


$dbCmd = new DbCmd();
$passiveAuthObj = Authenticate::getPassiveAuthObject();

if($view == "reseller_logo"){

	$passiveAuthObj->EnsureLoggedIn();
	$passiveAuthObj->EnsureMemberSecurity();
	
	// The Image ID is actually the Reseller ID
	$dbCmd->Query("SELECT resellers.LogoImage, users.DomainID FROM resellers 
			INNER JOIN users ON users.ID = resellers.UserID where users.ID=" . intval($imageid));
	
	if($dbCmd->GetNumRows() == 0)
		throw new Exception("The logo doesn't exist");
		
	$row = $dbCmd->GetRow();
		
	if(!$passiveAuthObj->CheckIfUserCanViewDomainID($row["DomainID"]))
		throw new Exception("The logo ID is invalid.");
	
	Header("Content-Type: image/pjpeg");
	echo $row["LogoImage"];
}
/*
else if($view == "template_image"){
	
	$dbCmd->Query("SELECT TableName FROM imagepointer where ID=" . intval($imageid));
	$tableName = $dbCmd->GetValue();

	if(!preg_match("/template/i", $tableName)){
		WebUtil::print404Error();
	}
		
	
	$imageHash = ImageLib::GetImageByID($dbCmd, $imageid);
	
	header('Accept-Ranges: bytes');
	header("Content-Length: ". strlen($imageHash["ImageData"]));
	header("Connection: close");
	header("Content-Type: " . $imageHash["ContentType"]);
	header("Last-Modified: " . date("D, d M Y H:i:s") . " GMT");
	header("Cache-Control: no-cache, must-revalidate");
	
	print $imageHash["ImageData"];
			
}
*/
else{
	throw new Exception("Illegal View Type for download Image");
}

?>