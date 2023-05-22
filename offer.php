<?

require_once("library/Boot_Session.php");


$kw = WebUtil::GetInput("kw", FILTER_SANITIZE_STRING_ONE_LINE);
$offer = WebUtil::GetInput("offer", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

$t = new Templatex();


// The offer name (coming through the URL) should match up to a HTML File within the Sandbox.
if(!file_exists(Domain::getDomainSandboxPath() . "/offer_" . $offer . "-template.html"))
	throw new Exception("The offer does not exist: $offer");

$t->set_file("origPage", ("offer_" . $offer . "-template.html"));

$t->set_var("KW_ENCODED", urlencode($kw));

$t->pparse("OUT","origPage");

