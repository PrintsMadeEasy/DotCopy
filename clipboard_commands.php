<?

require_once("library/Boot_Session.php");





$dbCmd = new DbCmd();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_general);


$view = WebUtil::GetInput("view", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$projectid = WebUtil::GetInput("projectid", FILTER_SANITIZE_INT);
$sidenumber = WebUtil::GetInput("sidenumber", FILTER_SANITIZE_INT);


$t = new Templatex(".");


$t->set_file("origPage", "clipboard_commands.html");


$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());


ProjectBase::EnsurePrivilagesForProject($dbCmd, $view, $projectid);

$ProductID = ProjectBase::GetProductIDFromProjectRecord($dbCmd, $projectid, $view);

// Check if we have a complete artwork stored in our session that is ready to be imported
// The clipboard functions are only meant to work with layers... not complete artworks
if(Clipboard::CheckIfArtworkIsCopiedToSession()){

	$ArtworkCopy_JS = "true";
	
	// If the Artwork being imported has a Different Product ID then show a Flag.
	if(Clipboard::GetArtworkCopyProductIDFromSession() != $ProductID)
		$DifferentProductID_JS = "true";
	else
		$DifferentProductID_JS = "false";
}
else{
	$ArtworkCopy_JS = "false";
	$DifferentProductID_JS = "false";
}


$t->set_var(array(
	"PROJECTID"=>$projectid,
	"VIEW"=>$view,
	"DIFFERENTPRODUCTID"=>$DifferentProductID_JS,
	"SIDENUMBER"=>$sidenumber,
	"ARTWORKCOPY"=>$ArtworkCopy_JS
	));


$t->pparse("OUT","origPage");



?>