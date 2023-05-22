<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();




$projectrecord = WebUtil::GetInput("projectid", FILTER_SANITIZE_INT);
$viewType = WebUtil::GetInput("view", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$vectorimageid = WebUtil::GetInput("vectorimageid", FILTER_SANITIZE_INT);
$fileView = WebUtil::GetInput("fileview", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

$user_sessionID =  WebUtil::GetSessionID();


ProjectBase::EnsurePrivilagesForProject($dbCmd, $viewType, $projectrecord);




$dbCmd->Query("SELECT Record, TableName FROM vectorimagepointer WHERE ID = " . $vectorimageid);
if($dbCmd->GetNumRows() == 0)
	throw new Exception("The vector image ID does not exist.");

$vectPointerRow = $dbCmd->GetRow();


if($vectPointerRow["TableName"] == "vectorimagessession"){

	$dbCmd->Query("SELECT SID FROM vectorimagessession WHERE ID = " . $vectPointerRow["Record"]);
	$imageSessionID = $dbCmd->GetValue();
	
	if($imageSessionID != $user_sessionID)
		throw new Exception("Permission Denied downloading the Vector Image Session ID.");

}
else{


	// Ensure that the Vector ID exists within the Artwork for the Project before we give the person permission to download.
	$artworkXML = ArtworkLib::GetArtXMLfile($dbCmd, $viewType, $projectrecord);

	if(!preg_match("/\<VectorImageId\>" . $vectorimageid . "\<\/VectorImageId\>/", $artworkXML))
		throw new Exception("The Image ID was not found within the artwork.");
}


if($fileView == "PDF"){

	$dbCmd->Query("SELECT PDFbinaryData FROM " . $vectPointerRow["TableName"] . " WHERE ID = " . $vectPointerRow["Record"]);
	$vectorImageRow = $dbCmd->GetRow();

	$vectorFileName = substr(md5($projectrecord . "filtered" . $vectorimageid), 0, 20) . ".pdf";
	$vectorFileData = $vectorImageRow["PDFbinaryData"];


}
else if($fileView == "ORIGINAL"){

	$dbCmd->Query("SELECT OrigFormatBinaryData, OrigFileSize, OrigFileType FROM " . $vectPointerRow["TableName"] . " WHERE ID = " . $vectPointerRow["Record"]);
	$vectorImageRow = $dbCmd->GetRow();

	$vectorFileName = substr(md5($projectrecord . "original" . $vectorimageid), 0, 20) . "." . ImageLib::getImageExtentsionByFileType($vectorImageRow["OrigFileType"]);
	$vectorFileData = $vectorImageRow["OrigFormatBinaryData"];
}
else{
	throw new Exception("Illegal file View in image_vector_download.php");
}





// Put file on disk
$fp = fopen(DomainPaths::getPdfSystemPathOfDomainInURL() . "/" . $vectorFileName, "w");
fwrite($fp, $vectorFileData);
fclose($fp);


$redirectURL = DomainPaths::getPdfWebPathOfDomainInURL() . "/" . $vectorFileName;


header("Location: ". WebUtil::FilterURL($redirectURL));
exit;

?>