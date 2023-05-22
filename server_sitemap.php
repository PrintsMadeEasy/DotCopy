<?

require_once("library/Boot_Session.php");

$sandBoxPath = Domain::getDomainSandboxPath(Domain::getDomainKeyFromURL());

$searchReplaceRedirectedURLsArr = array();

print "<html><br><br>\n\nAbout to parse file<br>\n";

set_time_limit(50000);

// Rather than writing a spider... i used another simple spider to gather a unique list of URL's at the website.
// This website will generate a full list of URL's ... http://www.rapidsitemap.com/
// Then we just go through their list and update the URL's with proper dates and priorities.
$xmlList = file_get_contents($sandBoxPath . "/url_list.x");

$xmlArr = split("<url>", $xmlList);

print "Size: " . sizeof($xmlArr);

print "<br>";

$urlsArr = array();

foreach($xmlArr as $thisNodeStr){
	
	$matches = array();
	if(!preg_match("/<loc>(.*)<\/loc>/", $thisNodeStr, $matches))
		continue;
		
	$urlFromNode = $matches[1];
	
	//print htmlspecialchars($urlFromNode) . "<br>";
	
	if(preg_match("/templates\.php/i", $urlFromNode))
		continue;
	if(preg_match("/new_project\.php/i", $urlFromNode))
		continue;
	if(preg_match("/product_step2\.php/i", $urlFromNode))
		continue;
	if(preg_match("/myaccount\.php/i", $urlFromNode))
		continue;
	if(preg_match("/;/i", $urlFromNode))
		continue;

	
	$urlFromNode = preg_replace("/\/\.\//", "/", $urlFromNode);
	$urlFromNode = preg_replace("/\.com\/$/", ".com", $urlFromNode);
		
	$urlsArr[] = WebUtil::unhtmlentities($urlFromNode);
	
}

$urlsArr = array_unique($urlsArr);

$xmlSitemap = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xmlSitemap .= '<urlset xmlns="http://www.google.com/schemas/sitemap/0.84" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.google.com/schemas/sitemap/0.84 http://www.google.com/schemas/sitemap/0.84/sitemap.xsd">' . "\n";

$counter = 0;
foreach($urlsArr as $thisURL){
	print htmlspecialchars($thisURL) . "<br>";
	flush();
	$xmlSitemap .= "<url>\n";
	$xmlSitemap .= "\t<loc>".htmlspecialchars($thisURL)."</loc>\n";
	
	$counter++;
	
	//if($counter > 35)
	//	continue;
	
	$returnXML = "";
	

	usleep(200000); // 1/5 a second.
	
	// Get the head of the URL so we can look for the "Last Modified" timestamp.
	$CurlCom = Constants::GetCurlCommand() . " --head $thisURL";
	$return_value = array();
	exec($CurlCom, $return_value);
	$return_value = implode("\n", $return_value);

	// $return_value[0] should contain the response from the Curl request
	if(!isset($return_value))
		throw new ExceptionCommunicationError("An error occured making a connection with Curl for AdWords Webservice. Possible Timeout?");

		
	// If the URL has moved, we want to Search Replace the new URL within our master list.
	if(preg_match("/301 Moved/", $return_value)){
		$matches = array();
		if(preg_match("/Location:\s(.*)\n/", $return_value, $matches)){
			print "<font color='red'>Moved to NEW URL: " . $matches[1] . "</font><br>";
			$searchReplaceRedirectedURLsArr[$thisURL] = $matches[1];
			flush();
		}
		
		$thisURL = $matches[1];
		
	}
	else if(!preg_match("/200 OK/", $return_value)){
		print "<b>Skipping ULR, not a 200: $thisURL </b><br>";
		flush();
		continue;
	}

	//print $return_value . "\n\n\n";

	$lastModifiedDate = null;
	$lastModifiedTimestamp = null;
	$matches = array();
	if(preg_match("/Last-Modified:\s(.*)\n/", $return_value, $matches)){
		$lastModifiedDate = $matches[1];
		$lastModifiedTimestamp = strtotime($lastModifiedDate);
	}
	else if(preg_match("/\.com$/", $thisURL)){
		
		// First prefer an Index Template processed by PHP.  That is important, so we will say it is updated every day.
		if (file_exists($sandBoxPath . "/index-template.html")) {
			$lastModifiedTimestamp = time();
		}
		else if (file_exists($sandBoxPath . "/index.html")) {
			$templateFileStamp = filemtime($sandBoxPath . "/index.html");
			if($templateFileStamp > $lastModifiedTimestamp)
				$lastModifiedTimestamp = $templateFileStamp;
		}
	}
	else{
		// If we can't get the "Last Modified" timestamp from the headers... try to get it from the file itself.
		$baseNameOfurl = basename($thisURL);
		$baseNameOfurl = preg_replace("/\?.*$/", "", $baseNameOfurl); // Get rid of Query String.
		
		if (file_exists($baseNameOfurl)) {
			$lastModifiedTimestamp = filemtime($baseNameOfurl);
		}
		
		// Find out if there is a correspending template in the SandBox that has a newer state.
		$possibleTemplate = preg_replace("/\.php/", "-template.html", $baseNameOfurl);
		if (file_exists($sandBoxPath . "/" . $possibleTemplate)) {

			$templateFileStamp = filemtime($sandBoxPath . "/" . $possibleTemplate);

			if($templateFileStamp > $lastModifiedTimestamp)
				$lastModifiedTimestamp = $templateFileStamp;
		}

	}
	
	// Make the domain name lower case.
	$thisURL = preg_replace("/".preg_quote(Domain::getDomainKeyFromURL())."/i", strtolower(Domain::getDomainKeyFromURL()), $thisURL);
	
	// If we have a really old date (or one not set in MySQL or something... just set it to blank.
	if($lastModifiedTimestamp < mktime(1,1,1,1,1,2000))
		$xmlSitemap .= "\t<lastmod></lastmod>" . "\n";
	else 
		$xmlSitemap .= "\t<lastmod>".date("Y-m-d", $lastModifiedTimestamp)."</lastmod>" . "\n";


	
	if(preg_match("/ci\/templates/", $thisURL)){
		$xmlSitemap .= "\t<changefreq>monthly</changefreq>\n";
		$xmlSitemap .= "\t<priority>0.3</priority>\n";
	}
	else if(preg_match("/\/ci\//", $thisURL)){
		$xmlSitemap .= "\t<changefreq>weekly</changefreq>\n";
		$xmlSitemap .= "\t<priority>0.6</priority>\n";
	}
	else if(preg_match("/privacy/", $thisURL) || preg_match("/terms/", $thisURL)){
		$xmlSitemap .= "\t<changefreq>monthly</changefreq>\n";
		$xmlSitemap .= "\t<priority>0.2</priority>\n";
	}
	else if(preg_match("/\.com$/", $thisURL)){
		$xmlSitemap .= "\t<changefreq>weekly</changefreq>\n";
		$xmlSitemap .= "\t<priority>1.0</priority>\n";
	}
	else{
		$xmlSitemap .= "\t<changefreq>weekly</changefreq>\n";
		$xmlSitemap .= "\t<priority>0.5</priority>\n";
	}
	$xmlSitemap .= "</url>" . "\n";
	
}

$xmlSitemap .= "</urlset>\n";

print "Size: " . sizeof($urlsArr) . "\n\n\n";

print $xmlSitemap;


file_put_contents($sandBoxPath . "/sitemap.xml", $xmlSitemap);


// If any of the URL's have moved... update our master URL list.
foreach($searchReplaceRedirectedURLsArr as $thisOldUrl => $thisNewURL){
	$xmlList = preg_replace("!".preg_quote($thisOldUrl)."!", $thisNewURL, $xmlList);
}


file_put_contents($sandBoxPath . "/url_list.x", $xmlList);



