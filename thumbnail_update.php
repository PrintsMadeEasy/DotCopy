<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();

$windowtype = WebUtil::GetInput("windowtype", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$redirect = WebUtil::GetInput("redirect", FILTER_SANITIZE_URL);
$projectid = WebUtil::GetInput("projectid", FILTER_SANITIZE_INT);
$view = WebUtil::GetInput("view", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);



ProjectBase::EnsurePrivilagesForProject($dbCmd, $view, $projectid);


ThumbImages::CreateThumnailImage($dbCmd, $projectid, $view);


// If we are in a pop-up window, then we want to close the window after it is done with generating the thumbnail.
// Otherwise do a redirect.
if($windowtype == "popup"){

	print "<html>\n";
	print "<script type='text/javascript' src='./library/general_lib.js'></script>";
	print "<script>\n";
	print "RefreshParentWindow();\n";
	print "window.close();\n";
	print '</script></html>';
	

	exit;
}
else{
	session_write_close();
	header("Location: ". WebUtil::FilterURL($redirect), true, 301);
	exit;

}

?>