<?

require_once("library/Boot_Session.php");

$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$view = WebUtil::GetInput("view", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$offset = WebUtil::GetInput("offset", FILTER_SANITIZE_NUMBER_INT);

$testimonialObj = new CustomerTestimonials();


if(!empty($action)){
	
	if($action == "comment_post"){

		$testimonialObj->setStatus(CustomerTestimonials::STATUS_PENDING);
		$testimonialObj->setTestimonial(WebUtil::GetInput("comment", FILTER_UNSAFE_RAW));
		$testimonialObj->setFirstName(WebUtil::GetInput("author", FILTER_SANITIZE_STRING_ONE_LINE));
		$testimonialObj->setEmail(WebUtil::GetInput("email", FILTER_SANITIZE_EMAIL));
		$testimonialObj->setCity(WebUtil::GetInput("url", FILTER_SANITIZE_STRING_ONE_LINE));
		$testimonialObj->setDateCreated(time());
		$testimonialObj->setDateLastEdited(time());
		
		// The URL is not labeled as a URL on the front-end.  
		// If we find http in there then we know it is a robot.
		if(!preg_match("/^http:/i", WebUtil::GetInput("url", FILTER_SANITIZE_STRING_ONE_LINE)))
			$testimonialObj->createNewRecord();

		header("Location: " . WebUtil::FilterURL($_SERVER['PHP_SELF'] . "?view=thanks"));
		exit;
	}
	else{
		throw new Exception("Illegal Action");
	}
}

$numberOfResultsToDisplay = 50;
$numberOfResultsOnHomePage = 4;

// Make sure that someone can't manipulate our offset in order to create duplicate content issues with google.
if(!in_array($view, array("thanks"))){
	if(($offset - $numberOfResultsOnHomePage) % $numberOfResultsToDisplay != 0){
		throw new Exception("Error with Testimonials Offset: $offset");
	}
}


$t = new Templatex();

$t->set_file("origPage", "user_comments-template.html");


if($view == "thanks"){
	$t->discard_block("origPage", "CommentsListBL");
	$t->discard_block("origPage", "NoResultsBL");
	$t->pparse("OUT","origPage");
	exit;
}
else{
	$t->discard_block("origPage", "PostedReplyBL");
}



$topTestimonialIds = CustomerTestimonials::getTopTestimonials($offset, $numberOfResultsToDisplay);

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

if(empty($topTestimonialIds)){
	$t->discard_block("origPage", "CommentsListBL");
	$t->discard_block("origPage", "PostedReplyBL");
}
else{
	$t->discard_block("origPage", "NoResultsBL");
}

// Don't show a more comments if we haven't saturdated our full list of results on this page.
if(sizeof($topTestimonialIds) < $numberOfResultsToDisplay){
	$t->discard_block("origPage", "MoreCommentsLinkBL");
}

$t->set_var("NEXT_OFFSET", ($offset + $numberOfResultsToDisplay));
$t->set_var("OFFSET", $offset);


$t->pparse("OUT","origPage");







