<?

require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();
$dbCmd3 = new DbCmd();




print "<html>\nSearch Engine Move\n\n<br><br>";


$dbCmd->Query("SELECT DISTINCT artworksearchengine.ID FROM artworksearchengine 
					INNER JOIN templatekeywords ON artworksearchengine.ID = templatekeywords.TemplateID 
					WHERE ProductID=197 AND TempKw = 'letterhead.com'");
$templateIDarr = $dbCmd->GetValueArr();


//var_dump($templateIDarr);

foreach ($templateIDarr as $thisTemplateID){
	
	ThumbImages::CreateTemplatePreviewImages($dbCmd, "template_searchengine", $thisTemplateID);
	print $thisTemplateID . "<br>";
	flush();
	
}
