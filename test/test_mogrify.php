<?

require_once("library/Boot_Session.php");

echo  WebUtil::mysystem(Constants::GetPathToImageMagick() . "mogrify");

if(!system(Constants::GetPathToImageMagick() . "mogrify")){

	print "Big Problem";
}

WebUtil::WebmasterError("webmaster error");

print "done";


?>