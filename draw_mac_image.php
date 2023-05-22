<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();


$user_sessionID =  WebUtil::GetSessionID();


$identify = WebUtil::GetInput("identify", FILTER_SANITIZE_STRING_ONE_LINE);


//Start off with an image ID blank.. let it prove otherwise
$NewImageID = "";
$NewVectorImageId = "";
$NewImageError = "";

// Lets first check and see if we can find an image successfully uploaded into the projectssession table
$dbCmd->Query("SELECT ID FROM imagessession WHERE UploadIdentify='$identify' AND SID='$user_sessionID'");
$ImageSessionID = $dbCmd->GetValue();

if($ImageSessionID){
	$dbCmd->Query("SELECT ID FROM imagepointer WHERE Record=$ImageSessionID AND TableName='imagessession'");
	$NewImageID = $dbCmd->GetValue();
}

$dbCmd->Query("SELECT ID FROM vectorimagessession WHERE UploadIdentify='$identify' AND SID='$user_sessionID'");
$VectorImageSessionID = $dbCmd->GetValue();

if($VectorImageSessionID){
	$dbCmd->Query("SELECT ID FROM vectorimagepointer WHERE Record=$VectorImageSessionID AND TableName='vectorimagessession'");
	$NewVectorImageId = $dbCmd->GetValue();
}


// If we have found an image ID.. then there are no errors
// If we dont have an image ID at this point... let's look and see if we can find an error
if(empty($NewImageID)){
	$dbCmd->Query("SELECT ErrorMessage FROM imageerrors WHERE UploadIdentify='$identify' AND SID='$user_sessionID'");
	$NewImageError = $dbCmd->GetValue();
}


header ("Content-Type: text/xml");


// It seems that when you hit session_start it will send a Pragma: NoCache in the header
// When comminicating over HTTPS there is a bug with IE.  It can not get the flash documents after they have finished downloading because they have already expired
// We can allow the file to be cached be sending this header.  When requesting the document you can put a ?noache=timestamp
// This is the only way to get flash communication to work over HTTPS with session variables
header("Pragma: public");

session_write_close();

// If no errors or no images.. then let them know they didn't follow instructions
if($NewImageID == "" && $NewImageError == ""){
	print "<?xml version=\"1.0\" ?>\n
		<response>
		<problem>An image is not available for importing.  Did you choose a file from your computer and wait for it to finish uploading?  If you continue to have problems please email your file to Customer Service and they can upload it for you.</problem>
		<newimageid></newimageid>
		<VectorImageId></VectorImageId>
		</response>";
}
else if($NewImageID != ""){
	
	// Well, this means a successul upload.. Let the app know which image ID is good --#

	print "<?xml version=\"1.0\" ?>\n
		<response>
		<problem></problem>
		<newimageid>$NewImageID</newimageid>
		<VectorImageId>$NewVectorImageId</VectorImageId>
		</response>
		";
}
else if($NewImageError != ""){
	
	// Am error occured --#
	
	$NewImageError = WebUtil::htmlOutput($NewImageError);

	print "<?xml version=\"1.0\" ?>\n
		<response>
		<problem>$NewImageError</problem>
		<newimageid></newimageid>
		<VectorImageId></VectorImageId>
		</response>
		";
}







?>
