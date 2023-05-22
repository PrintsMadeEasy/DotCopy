<?
/*
require_once("../../../constants/Constants.php");
require_once("../../../classes/WebUtil.php");
require_once("../../../classes/Domain.php");
require_once("../../../classes/Template.inc");
require_once("../../../classes/Templatex.inc");
require_once("../../../classes/Widgets.php");
require_once("../../../classes/Authenticate.php");
require_once("../../../classes/DbCmd.php");
require_once("../../../classes/DbConnect.php");
*/




$user_sessionID =  WebUtil::GetSessionID();

WebUtil::BreakOutOfSecureMode();


$outputFileName1 = "glass.jpg";
$outputFileName2 = "pebbles.jpg";


$phrase = WebUtil::GetInput("phrase", FILTER_SANITIZE_STRING_ONE_LINE);
$phrase = preg_replace("/(\"|'|\n|\r)/", "", $phrase);



if(!empty($phrase)){

	if(strlen($phrase) > 20)
		$phrase = substr($phrase, 0, 20);

	$imageBackground1 = "StainedGlass.jpg";
	$textBackground1 = "tiledglass.jpg";


	$imageBackground2 = "PebblesGround.jpg";
	$textBackground2 = "pebblesText.jpg";
	

   $fp = fopen ("./search.log", "a");
   fwrite( $fp, $phrase . "\n");
   fclose ($fp);


	if(Constants::GetDevelopmentServer()){
		$currentDirectoryPath = Constants::GetWebserverBase() . "\\lab\\variable_imaging\\";
		$outputPath1 = Constants::GetTempImageDirectory() . "\\" . $outputFileName1;
		$outputPath2 = Constants::GetTempImageDirectory() . "\\" . $outputFileName2;
	}
	else{
		$currentDirectoryPath = Constants::GetWebserverBase() . "/lab/variable_imaging/";
		$outputPath1 = Constants::GetTempImageDirectory() . "/" . $outputFileName1;
		$outputPath2 = Constants::GetTempImageDirectory() . "/" . $outputFileName2;
	}

	$imageBackground1 = $currentDirectoryPath . $imageBackground1;
	$textBackground1 = $currentDirectoryPath . $textBackground1;

	$imageBackground2 = $currentDirectoryPath . $imageBackground2;
	$textBackground2 = $currentDirectoryPath . $textBackground2;


	$imageCommand1 = Constants::GetPathToImageMagick() . "convert -size 800x430 xc:black -border 0 -tile $imageBackground1 ";
	$imageCommand1 .= "-draw \"color 0,0 reset\" -tile $textBackground1 -gravity center -stroke \"#222222\" -strokewidth 5 ";
	$imageCommand1 .= "-font Baltar -pointsize 130 -annotate -30+150 \"" . $phrase . "\" $outputPath1";
	system($imageCommand1);


	$imageCommand2 = Constants::GetPathToImageMagick() . "convert -size 800x430 xc:black -border 0 -tile $imageBackground2 ";
	$imageCommand2 .= "-draw \"color 0,0 reset\" -tile $textBackground2 -gravity center ";
	$imageCommand2 .= " -font Happy -pointsize 150 -annotate +0-60 \"" . $phrase . "\" -shade 220x45 ";

	$imageCommand2 .= $outputPath2;


	system($imageCommand2);

}






$t = new Templatex(".","keep");

$t->set_file("origPage", "text-template.html");


if(empty($phrase)){

	$t->set_block("origPage","emptyPhrase","emptyPhraseout");
	$t->set_var("emptyPhraseout", "<br><br><br><br>Enter some text, then click on &quot;Redraw&quot;.");
	
	
}

$t->set_var("PHRASE", WebUtil::htmlOutput($phrase));

$websiteUrlForDomain = Domain::getWebsiteURLforDomainID(Domain::oneDomain());

$t->set_var("IMAGE1", "http://$websiteUrlForDomain/image_preview/" . $outputFileName1 . "?nocache=" . time());
$t->set_var("IMAGE2", "http://$websiteUrlForDomain//image_preview/" . $outputFileName2 . "?nocache=" . time());

$t->pparse("OUT","origPage");


?>