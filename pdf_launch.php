<?

require_once("library/Boot_Session.php");

set_time_limit(5000);


// This variable is used to forward the user to any page that you want... through a GET url
$forward = WebUtil::GetInput("forward", FILTER_SANITIZE_URL);

// In Admin mode this variables will generate batches of PDF documents
$projectlist = WebUtil::GetInput("projectlist", FILTER_SANITIZE_STRING_ONE_LINE);
$pdf_type = WebUtil::GetInput("pdf_type", FILTER_SANITIZE_STRING_ONE_LINE);
$pdf_profile = WebUtil::GetInput("pdf_profile", FILTER_SANITIZE_STRING_ONE_LINE);
$formaction = WebUtil::GetInput("formaction", FILTER_SANITIZE_STRING_ONE_LINE);



$t = new Templatex();

$t->set_file("origPage", "pdf_launch-template.html");


// Figure out if we are going a GET or POST for the gernation of the PDF documents

if(!empty($pdf_type)){
	$t->discard_block("origPage", "getBL");
	
	$t->set_var(array(
		"PROJECTLIST"=>WebUtil::htmlOutput($projectlist),
		"PDFTYPE"=>WebUtil::htmlOutput($pdf_type),
		"PDFPROFILE"=>WebUtil::htmlOutput($pdf_profile),
		"FORMACTION"=>WebUtil::htmlOutput($formaction)
		));

}
else{

	$t->discard_block("origPage", "postBL");
	$t->discard_block("origPage", "postFormBL");
	
	$t->set_var(array("FORWARD"=>WebUtil::FilterURL($forward)));
}



// For now, I like the pyramid animation better... maybe we will add more in the future?
$t->set_var("RANDOM_ANIMATION", 2);


$t->pparse("OUT","origPage");



?>