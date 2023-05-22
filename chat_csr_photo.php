<?


require_once("library/Boot_Session.php");


$id = WebUtil::GetInput("id", FILTER_SANITIZE_NUMBER_INT);

$dbCmd = new DbCmd();

$dbCmd->Query("SELECT DomainID, IsPhotoUploaded, CsrPhoto, UNIX_TIMESTAMP(DateModified) as DateModified FROM chatcsrsetup WHERE ID=" . intval($id));

if($dbCmd->GetNumRows() == 0){
	ImageLib::outputTransparentGif();
	exit;
}

$row = $dbCmd->GetRow();

$passiveAuthObj = Authenticate::getPassiveAuthObject();

if(!$passiveAuthObj->CheckIfUserCanViewDomainID($row["DomainID"]) || $row["IsPhotoUploaded"] != "Y"){
	ImageLib::outputTransparentGif();
	exit;
}


header('Accept-Ranges: bytes');
header("Content-Length: ". strlen($row["CsrPhoto"]));
header("Connection: close");
header("Content-Type: image/jpeg");
header("Last-Modified: " . date("D, d M Y H:i:s", $row["DateModified"]) . " GMT");
header("Cache-Control: store, cache");


print $row["CsrPhoto"];






