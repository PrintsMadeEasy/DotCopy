<?

require_once("library/Boot_Session.php");

$t = new Templatex();

$httpRefererStr = WebUtil::GetServerVar('HTTP_REFERER');

// Built a list of keywords that we will search for within the HTTP Referrer that will tell us if it is a real user - versus a crawler.
$referrerWordsNoCrawler = array("business", "card", "prints", "google", "bing", "yahoo", "msn.com", "postcard", "easy");
$userIsCrawler = true;
foreach($referrerWordsNoCrawler as $thisPersonSearch){
	if(preg_match("/".preg_quote($thisPersonSearch)."/i", $httpRefererStr)){
		$userIsCrawler = false;
		break;
	}
}

if(!$userIsCrawler)
	WebUtil::SetCookie("VisitorIsCrawler", "no", 150);


// Show different HTML templates depending on whether the User is a real person, or a Google Bot.
if(WebUtil::GetCookie("VisitorIsCrawler") == "no" || !$userIsCrawler)
	$t->set_file("origPage", "index-template.html");
else
	$t->set_file("origPage", "index-crawler-template.html");

	
	
$testimonialObj = new CustomerTestimonials();
$topTestimonialIds = CustomerTestimonials::getTopTestimonials(0, 4);

$t->set_block("origPage","CommentBl","CommentBlout");

foreach($topTestimonialIds as $thisTestimonialID){

	$testimonialObj->loadTestimonialById($thisTestimonialID);
	
	$t->set_var("COMMENT_DETAIL", $testimonialObj->getTestimonial(true, true));
	$t->allowVariableToContainBrackets("COMMENT_DETAIL");
	
	$t->set_var("COMMENT_AUTHOR", WebUtil::htmlOutput($testimonialObj->getFirstName()));
	$t->set_var("COMMENT_LOCATION", WebUtil::htmlOutput($testimonialObj->getCity()));
	$t->set_var("COMMENT_ID", $thisTestimonialID);
	
	$t->parse("CommentBlout","CommentBl",true);
}
$t->pparse("OUT","origPage");
