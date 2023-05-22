<?php

// ----- Dynamic Variables passed through Query String -----------
// If the we are passing a query string onto the HTML file and want Variables Substituted within the returned file.. the request may look like this...
// http://domain.com/myDynamicFile.html?vars=ProductID:23^UserID:3423
// In this example we would do 2 search and replaces within myDynamicFile.html before returning the data to the browser.  
// The HTML or JS file must contain curly brackets around the variable name... the URL should not have curly brackets around the variable names.
// Each name/value pair is separated with a Carrot symbol.  The Name is Separated from the value using a Colon.
// 1) Search for {VAR:ProductID} and Replace with "23"
// 2) Search for {VAR:UserID} and Replace with "3423".
// Unused Variables are removed.

// We want to process the URL variables first... but we don't want to do this process recursively.
// Also... if we do this all in the beggining.. it means that we can URL Varaibles can be embedded within a parameters.
// The example on the following line will use a parameter from a URL to feed an SSI parameter... which will in turn affect a {DATA} variable substition. 
// .... {PARAM:ProductID={VAR:Something}} 



// ----- Commands issued to the Server  -----------------------
// Enter commands using the following syntax.  Enter anywhere within the file and the server will sense it.
// All Commands sytax is removed from the file before retuning to the browser.
// An Error will be printed if the command is not recognized.
// For Example: Opening the following HTML file would force the user to a sign-in page. After successfully logging on, they would get redirected to the URL they were trying to go.
// <html>{COM:Authenticate}Now I can be sure that the user is logged in.</html>
// <html>{COM:Secure}Now I can be sure that the user is going over an https connection SSL... even if the user tries to view the page without the http"s" it will redirect them to the Secure version.</html>


// ----- Parameters Sent to the Server -------
// You can send parameters to the server for use with Commands
// For Example, you may want to tell the Authentication command what URL you want to go after successfully logging in.  If they are logged in, then they will be able to access the page immediately.
// There is no error checking to make sure that you entered the parameter name correctly. And most parameters are optional.
// Enter as many parameters as you want. The Name/Value pair is separated by an equals sign.
// No need to encode the data, since it will be viewed by the server and then removed before display.
// For development purposes you may want to keep commands within HTML comments... so while using dreamweaver, data not HTML encoded doesn't break the layout. For example.
// <html><!-- Hidden Text {COM:Authenticate} {PARAM:AfterLoginRedirectURL=https://www.something.com} -->  Now I can be sure that the user is logged in. If not, after logging in they will be redirected to something.com</html>
// Parameter names can not have spaces or special characters.  The Parameter values can have spaces and special characters (but they can not have a curly brackets). Parameters valus can not have newline characters either.


// ------ Data Variables -------------
// DATA Variables may also be used inside of HTML templates (processed by PHP)
// If you want to fetch data from the database (or from server-side variables) ... then you would enter database variables with a specific syntax.
// You might not have to use an Authenticate command like {COM:Authenticate} if you use a Data Variable which is protected.
// Since "UserID" requires a user to be logged in, if the user is not logged in, then the variable will be replaced with blank string.
// <html><h1>Some Examples</h1>{DATA:UserID}, {DATA:SessionID}, {DATA:UserEmail} {DATA:UserIsAdmin} {DATA:UserIsLoggedIn}</html>
// If the user is logged in... and a member of the backend this Variable will be substuted as "true" otherwise "false" {DATA:UserIsAdmin}
// If the Data Variable is not recognized the substitution will fail with an error.


// ------ Paramater Containers ---------------
// DATA Containers must have a "start" and an "end".  
// The containers may be used to encapsulate the parameters, which can be used to derive a data variable.
// For example, to get the Product name... you need to tell it what Product ID you want. 
// This will print out the Product Name of ID 73 & 78, separated by a line break.
/*
 * {CONTAINER:Start}
 * {PARAM:ProductID=73}
 * Product Name: {DATA:ProductName}
 * {CONTAINER:End}
 * 
 * <br/>
 * 
 * {CONTAINER:Start}
 * {PARAM:ProductID=78}
 * Product Name: {DATA:ProductName}
 * {CONTAINER:End}
 * 
 */



// ------ Loop Directives -------------
// LOOP Directives must have a "start" and an "end".  Information will be processed on the server after extracting the chunk in between the "start" and "end" areas.
// You must pass in Parameters (held between Parameter Containers) to control what kind of data you want subtituted inside of a loop.
// For example... Here is an example of how to build a row of active Product Names in the system.
// It is important that you do not place Container Directives out of heirarchial order from Loop Directives, or visa versa.
/*
 * <table>
 * 
 * {LOOP:Start}
 * {CONTAINER:Start}
 * {PARAM:ProductList=ActiveProducts}
 * {PARAM:SortBy=Alphabetically}
 * 
 * <tr>
 * <td>{DATA:ProductName} has a Product ID of: {DATA:ProductID}</td>
 * </tr>
 * 
 * {CONTAINER:End}
 * {LOOP:End}

 * </table>
 */

// You can also do loops within loops or have multiple "Parameter Containers" within a loop.
// If you want the price of a Product at a certain quantity... and you are looping through a list of quantities.
// We can use a "Parameter Container" to get the price for "glossy" versus "matte". 
/*
 * <table>
 * 
 * <tr>
 * <td>Quantity</td>
 * <td>Matte</td>
 * <td>Glossy</td>
 * </tr>
 * 
 * {CONTAINER:Start}
 * {PARAM:ProductID=73}
 * {PARAM:ProductLoop=Quantity}
 * {PARAM:MinQuantityBreak=100}
 * {PARAM:MaxQuantityBreak=1000000}
 * 
 * {LOOP:Start}
 * <tr>
 * <td>{DATA:ProductQuantity}</td>
 * 
 * {CONTAINER:Start}
 * {PARAM:ProductOption=Matte}
 * <td>${DATA:ProductPrice}</td>
 * {CONTAINER:End}
 * 
 * {CONTAINER:Start}
 * {PARAM:ProductOption=Glossy}
 * <td>${DATA:ProductPrice}</td>
 * {CONTAINER:End}
 * 
 * </tr>
 * {LOOP:End}
 * 
 * {CONTAINER:End}
 * 
 * </table>
 * 
 */


// Server-Side Loop Containers should be processed within a Node String before trying to replace the {DATA} variables outside of the loop containers.
class ServerSideLoopContainer {
	
	private $dataStr;
	private $loopContainersTextArr = array();
	private $loopContainersObjArr = array();
	private $parametersArr = array();
	
	// Server Side Loop Containers may not
	function __construct($dataNodeStr, $parametersHash){

		$this->dataStr = $dataNodeStr;
		$this->parametersArr = $parametersHash;
		
		$this->extractFirstLevelLoopContainers();
		
		// Use the Text from the Sub Nodes to create ServerSideLoopContainer Objects recursively.
		for($i=0; $i<sizeof($this->subContainersTextArr); $i++){
			$this->loopContainersObjArr[$i] = new ServerSideLoopContainer($this->loopContainersObjArr[$i], $this->parametersArr);
		}
		
	}
	
	// This will extract 1st Level Loop Containers within the data string.
	// The Loop Containers will be added to our array of Sub Container Objects.
	// What will be left over in our data string is place-holders.
	// The 2nd, 3rd, etc. level containers will be extracted recursively.
	function extractFirstLevelLoopContainers(){

		$extractionResult = ServerSideIncludes::extractFirstLevelNodes("LOOP", $this->dataStr);
		
		// Get a possible array of 1st-level Sub Containers
		$this->loopContainersTextArr = $extractionResult["Nodes"];
			
		// This will have the result of the text with all subcontainers removed (held in place by a temp variable).
		$this->dataStr =$extractionResult["Text"];
		
	}

}


// When processing a File (or HTML string) for "server side includes", there may be mutiple Parameter Containers, nested heirarchialy.
class ServerSideIncludeContainer {
	
	private $dataStr;
	private $subContainersTextArr = array();
	private $subContainersObjectsArr = array();
	private $parametersArr = array();
	
	
	// To create a new ServerSideIncludeContainer, we need the data within the node (a string, such as HTML Source Code).
	// We also need a hash containing all of the initial Name/Value pairs (defining the parameters)
	// Parameters are cascading... meaning that you will keep passing the new values into the Sub-Containers as they they are constructed further down the chain.
	// A Sub-Node can override one or more parameters from its parent.
	// For the root container (i.e. the original file) there will genrally not be an $intialParametersHash... just pass in an empty array.
	function __construct($dataNodeStr, array $intialParametersHash){
		
		$this->dataStr = $dataNodeStr;
		
		$this->extractFirstLevelSubContainers();

		// We will extract the Parameter values with all sub-containers removed... so that we aren't poluting our "current level" with sub-container parameters.
		// Then we will create new ServerSideIncludeContainer objects (passing our current level of parameters) in a recursive pattern.
		// Eventually there will be no more sub-containers and we can process any "Loop Directives" within the lowest-level container(s) and the recursion can begin walking back to the root.
		$this->parametersArr = $intialParametersHash;
		$this->extractNewParameters();
		
		// Use the Text from the Sub Containers to create ServerSideIncludeContainer Objects recursively.
		for($i=0; $i<sizeof($this->subContainersTextArr); $i++){
			$this->subContainersObjectsArr[$i] = new ServerSideIncludeContainer($this->subContainersTextArr[$i], $this->parametersArr);
		}
	}
	
	// This will process all of the Server Side Includes (and loops) on this contianer.
	// It will still have holes where the 1st-level sub-containers were extracted and replaced with a variable.
	private function processDataWithinThisContainer(){
		
		
	}
	
	
	// This will recursively go through all sub-containers underneath and process the data, replacing Container Variables within this current container object.
	public function getProcessedDataIncludingAllSubContainers (){
		
		$this->processDataWithinThisContainer();
		
		for($i=0; $i<sizeof($this->subContainersObjectsArr); $i++){
			// Recursive Method Call.
			$proccessedSubContainerStr = $this->subContainersObjectsArr[$i]->getProcessedDataIncludingAllSubContainers();
			
			// Replace 1st-Level containers in this object.
			$this->dataStr = preg_replace("\{CONTAINER:$i\}", $proccessedSubContainerStr, $this->dataStr);
		}
		
		return $this->dataStr;
	}
	
	// This will extract 1st-level SubContainers within the data string.
	// The Sub-Containers will be added to our array of Sub Container Objects.
	// What will be left over in our data string is place-holders.
	// The 2nd, 3rd, etc. level containers will have to be extracted recursively.
	private function extractFirstLevelSubContainers(){
		
		$extractionResult = ServerSideIncludes::extractFirstLevelNodes("CONTAINER", $this->dataStr);
		
		// Get a possible array of 1st-level Sub Containers
		$this->subContainersTextArr = $extractionResult["Nodes"];
			
		// This will have the result of the text with all subcontainers removed (held in place by a temp variable).
		$this->dataStr =$extractionResult["Text"];

	}
	
	// This will extract new Parameters from the string within our current node.
	// It could override one of the starting parameters bassed into the constructor of this Object.
	// ... or it could add new name/value pairs to our Parameter Hashes
	// By the time that you call this method... the Sub Containers should have already been extracted from the Data String, so we won't accidently grab parameters from the wrong level.
	private function extractNewParameters(){

		$paramsArr = array();
		$matches = array();
		if(preg_match_all("/\{PARAM:([^\n]+)\}/", $this->dataStr, $matches))
			$paramsArr = $matches[1];
		
		// Now we are going to turn the Parameters into a Hash, where the key is the name and the value is the value.
		// The full parameters string (extracted from the HTML) is separated from name/value by an equals sign, such as {PARAM:AfterLoginRedirectURL=https://www.something.com}
		foreach($paramsArr as $thisParamStr){
		
			// There could be other equals signs within the parameter value, so that is why we limit to 2. We split on the first equals sign that we find.
			$paramSpitArr = split("=", $thisParamStr, 2);
			
			// Skip on errors.
			if(sizeof($paramSpitArr) != 2)
				continue;
			
			$paramKey = strtoupper(trim($paramSpitArr[0]));
			$paramValue = trim($paramSpitArr[1]);
			
			// We can't have an empty key
			if(empty($paramKey))
				continue;
			
			$this->parametersArr[$paramKey] = $paramValue;
		}
	}
	
	
	
}


class ServerSideIncludes {

	
	
	static function substituteDataVariablesInTemplate($fileContentsStr){
		
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		
		$dataArr = array();
		$matches = array();
		if(preg_match_all("/\{DATA:(\w+)\}/", $fileContentsStr, $matches))
			$dataArr = $matches[1];
			
		// Replace any data variables (may require access to the database)
		foreach($dataArr as $thisDataVarName){
			
			$thisDataVarName = strtoupper($thisDataVarName);
			
			if($thisDataVarName == "USERID"){
				if(!$passiveAuthObj->CheckIfLoggedIn())
					$fileContentsStr = preg_replace("/\{DATA:USERID\}/i", "", $fileContentsStr);
				else
					$fileContentsStr = preg_replace("/\{DATA:USERID\}/i", $passiveAuthObj->GetUserID(), $fileContentsStr);
			}
			else if($thisDataVarName == "REFERRALSOURCECATEGORY"){
				
				$userReferralSourceCategory = "Unknown";
				
				// The banner tracking code will tell us the most.
				$referalTrackingCode = WebUtil::GetSessionVar("ReferralSession", WebUtil::GetCookie("ReferralCookie"));
				$salesRepTrackingCode = WebUtil::GetSessionVar("SalesRepReferralSession", WebUtil::GetCookie("SalesRepReferralCookie"));
				$previousSessionID = WebUtil::GetCookie("PreviousSession");
				
				if(preg_match("/^(g-.*|yahoo-.*|gcn-.*|msn-.*|msn-.*|g-.*|overture.*|biz-.*)/", $referalTrackingCode))
					$userReferralSourceCategory = "PaidSearch";
				else if(preg_match("/^em-.*/", $referalTrackingCode))
					$userReferralSourceCategory = "Email";
				else if(!empty($salesRepTrackingCode))
					$userReferralSourceCategory = "SalesRep";
				else if(VisitorPath::checkIfVisitorHasGoneThroughLabel("Organic Link"))
					$userReferralSourceCategory = "Organic";
				else if(!empty($previousSessionID))
					$userReferralSourceCategory = "ReturnVisitor";
				
				$fileContentsStr = preg_replace("/\{DATA:REFERRALSOURCECATEGORY\}/i", $userReferralSourceCategory, $fileContentsStr);
				
			}
			else if($thisDataVarName == "BINGCASHBACKPRODUCTTRACKER"){

				// Find out if the CashBack cookie was set, then include the tracking pixel/iframe code.
				$affiliateSource = WebUtil::GetSessionVar("AffiliateSource", WebUtil::GetCookie("AffiliateSource"));
				
				$cashBackTracker = "";
				
				if($affiliateSource == "cashbackShopping"){
				
					if(Domain::getDomainIDfromURL() == Domain::getDomainID("PrintsMadeEasy.com")){
						$cashBackTracker = '<table width="760" border="0" cellspacing="0" cellpadding="0" style="border-width:1px; border-color:#0099CC; border-bottom-color:#003399; border-style:solid;"><tr><td bgcolor="#E1EAF0" align="center"><script language="javascript" type="text/javascript" src="http://www.bing.com/cashback/shopping/gleam/javascript.ashx?merchantId=aTVlQUZ6bkpuWVJ6L2JGalFXdlJsQT09Cg&type=2&enabledbgcolor=E1EAF0&bgcolor=E1EAF0"></script></td></tr></table>';
					}
					else if(Domain::getDomainIDfromURL() == Domain::getDomainID("Postcards.com")){
						$cashBackTracker = '<script language="javascript" type="text/javascript" src="http://www.bing.com/cashback/shopping/gleam/javascript.ashx?merchantId=Q0dRYldvbVg5bHhPa2pEZStHQklVdz09Cg&type=1&enabledbgcolor=FFFFFF&bgcolor=FFFFFF"></script>';
					}
				}
				
				$fileContentsStr = preg_replace("/\{DATA:BINGCASHBACKPRODUCTTRACKER\}/i", $cashBackTracker, $fileContentsStr);
			}
			else if($thisDataVarName == "USERISLOGGEDIN"){
				if($passiveAuthObj->CheckIfLoggedIn())
					$fileContentsStr = preg_replace("/\{DATA:USERISLOGGEDIN\}/i", "true", $fileContentsStr);
				else
					$fileContentsStr = preg_replace("/\{DATA:USERISLOGGEDIN\}/i", "false", $fileContentsStr);
			}
			else if($thisDataVarName == "USERISADMIN"){
				if($passiveAuthObj->CheckIfLoggedIn() && $passiveAuthObj->CheckIfBelongsToGroup("MEMBER"))
					$fileContentsStr = preg_replace("/\{DATA:USERISADMIN\}/i", "true", $fileContentsStr);
				else
					$fileContentsStr = preg_replace("/\{DATA:USERISADMIN\}/i", "false", $fileContentsStr);
			}
			else if($thisDataVarName == "SESSIONID"){
				$fileContentsStr = preg_replace("/\{DATA:SESSIONID\}/i", WebUtil::GetSessionID(), $fileContentsStr);
			}
			else if($thisDataVarName == "DOMAINKEY"){
				$fileContentsStr = preg_replace("/\{DATA:DOMAINKEY\}/i", Domain::getDomainKeyFromURL(), $fileContentsStr);
			}
			else if($thisDataVarName == "WEBSITEDOMAIN"){
				$fileContentsStr = preg_replace("/\{DATA:WEBSITEDOMAIN\}/i", Domain::getWebsiteURLforDomainID(Domain::getDomainIDfromURL()), $fileContentsStr);
			}
			else if($thisDataVarName == "CURRENTYEAR"){
				$fileContentsStr = preg_replace("/\{DATA:CURRENTYEAR\}/i", date("Y"), $fileContentsStr);
			}
			else if($thisDataVarName == "WEBSERVERBASEPATH"){
				
				if(!array_key_exists("HTTPS", $_SERVER) || !$_SERVER['HTTPS'])
					$serverBasePath = "http://";
				else
					$serverBasePath = "https://";
					
				$serverBasePath .= Domain::getWebsiteURLforDomainID(Domain::getDomainIDfromURL());
				$fileContentsStr = preg_replace("/\{DATA:WEBSERVERBASEPATH\}/i", $serverBasePath, $fileContentsStr);
			}
			else if($thisDataVarName == "WEBSERVERBASEPATHURLENCODED"){

				if(!array_key_exists("HTTPS", $_SERVER) || !$_SERVER['HTTPS'])
					$serverBasePath = "http://";
				else
					$serverBasePath = "https://";
					
				$serverBasePath .= Domain::getWebsiteURLforDomainID(Domain::getDomainIDfromURL());
				$serverBasePath = urlencode($serverBasePath);
				
				$fileContentsStr = preg_replace("/\{DATA:WEBSERVERBASEPATHURLENCODED\}/i", $serverBasePath, $fileContentsStr);
			}
			else if($thisDataVarName == "HTTPREFERERHEX"){
				
				// Converts Referer into a Hex Format ... This will keep bots from thinking we have a link pointing back to the page the user came from.
				$httpReferer = WebUtil::GetServerVar('HTTP_REFERER');
				$httpReferer = bin2hex($httpReferer); 
				
				$fileContentsStr = preg_replace("/\{DATA:HTTPREFERERHEX\}/i", $httpReferer, $fileContentsStr);
			}
			else{
				throw new Exception("Illegal Data Variable Directive: " . $thisDataVarName);
			}
		}
		
		return $fileContentsStr;
	}
	
	
	static function processServerSideIncludes($fileString){
		
		// Extract all of the Comands, Data Vars, and Parameters into local arrays.
		$commandsArr = array();
		$matches = array();
		if(preg_match_all("/\{COM:(\w+)\}/", $fileString, $matches))
			$commandsArr = $matches[1];
			
		// Make all of the Commands uppercase.
		for($i=0; $i<sizeof($commandsArr); $i++)
			$commandsArr[$i] = strtoupper($commandsArr[$i]);
			
		$paramsArr = array();
		if(preg_match_all("/\{PARAM:([^\n]+)\}/", $fileString, $matches))
			$paramsArr = $matches[1];

		
		// Now we are going to turn the Parameters into a Hash, where the key is the name and the value is the value.
		// The full parameters string (extracted from the HTML) is separated from name/value by an equals sign, such as {PARAM:AfterLoginRedirectURL=https://www.something.com}
		$tempParms = $paramsArr;
		foreach($paramsArr as $thisParamStr){
		
			// There could be other equals signs within the parameter value, so that is why we limit to 2. We split on the first equals sign that we find.
			$paramSpitArr = split("=", $thisParamStr, 2);
			
			// Skip on errors.
			if(sizeof($paramSpitArr) != 2)
				continue;
			
			$paramKey = strtoupper(trim($paramSpitArr[0]));
			$paramValue = trim($paramSpitArr[1]);
			
			// We can't have an empty key
			if(empty($paramKey))
				continue;
			
			$tempParms[$paramKey] = $paramValue;
		}
		
		// turn the numbered-indexed array into a hash.
		$paramsArr = $tempParms;
		
		
		
		
		
		// Print an error if there is a command that we don't recognize.
		$legalComandsArr = array("AUTHENTICATE", "SECURE", "REMOVEHTMLCOMMENTS");
		foreach($commandsArr as $thisCommand){
			if(!in_array($thisCommand, $legalComandsArr))
				throw new Exception("Illegal Command Directive: " . $thisCommand);
		}
		
		
		// Get the current URL of the page that is running
		$websiteUrlForDomain = Domain::getWebsiteURLforDomainID(Domain::getDomainIDfromURL());
		if(empty($_SERVER['HTTPS']))
			$currentURL = "http://$websiteUrlForDomain";
		else 
			$currentURL = "https://$websiteUrlForDomain";
			
		$currentURL .= $_SERVER['REQUEST_URI'];
	
		
		// If the user must be logged in, and they are not, then redirect them to the sign in page.
		if(in_array("AUTHENTICATE", $commandsArr)){
		
			$AuthObj = Authenticate::getPassiveAuthObject();
			
			if(!$AuthObj->CheckIfLoggedIn()){
			
				// Redirect them to the place they were trying to visit before getting the sign in page.
				// The curren URL of this script is being masked by Apache Mod Rewrite.
				$AuthObj->SetUpRedirectionURL($currentURL);
				
				// Find out if the Command Directive is telling us to go to a secure page after signing in.
				if(in_array("SECURE", $commandsArr))
					$AuthObj->RedirectToSecurePageAfterLogin(true);
					
				
				// Find out if the Parameter Directive is telling us to redirect to another page.
				if(array_key_exists("AFTERLOGINREDIRECTURL", $paramsArr))
					$AuthObj->SetUpRedirectionURL($paramsArr["AFTERLOGINREDIRECTURL"]);
			
				$AuthObj->RedirectUserToSignInPage();
			}
		}
		
		
		// Putting this command on the HTML source will remove any 1-line comments.
		if(in_array("REMOVEHTMLCOMMENTS", $commandsArr)){
			$fileString = preg_replace("/<!--(?!\s(BEGIN|END))[^\n]+-->/", "", $fileString);
		}
		
		
		// Find out if this page is required to be viewed over a secure connection. If so, we may need to redirect to the https protocoll.
		if(empty($_SERVER['HTTPS']) && in_array("SECURE", $commandsArr)){
			
			// Before we redirect, lets make sure that this server can support https
			if(preg_match("/https/i", Constants::GetServerSSL())){
			
				$currentURL = preg_replace("/^http:/", "https:", $currentURL);

				header("Location: " . WebUtil::FilterURL($currentURL));
				exit;
			}
		}
		
		

		$fileString = self::substituteDataVariablesInTemplate($fileString);
		$fileString = self::processUrlVariables($fileString);
		
		
		// Remove any variables that are left over.
		$fileString = preg_replace("/\{VAR:\w+\}/", "", $fileString);
		$fileString = preg_replace("/\{COM:\w+\}/", "", $fileString);
		$fileString = preg_replace("/\{DATA:\w+\}/", "", $fileString);
		$fileString = preg_replace("/\{CONTAINER:\w+\}/", "", $fileString);
		$fileString = preg_replace("/\{LOOP:\w+\}/", "", $fileString);
		$fileString = preg_replace("/\{PARAM:[^\n]+\}/", "", $fileString);
		
		return $fileString;
	}
	
	
	static function processUrlVariables($fileString){
		
		// Now look at all of the varaibles that were passed through the URL. Try to finding matching placeholders within the HTML file.
		$vars = WebUtil::GetInput("vars", FILTER_SANITIZE_STRING_ONE_LINE);
		
		$varsArr = split("\^", $vars);
		
		foreach($varsArr as $thisVar){
	
			// Separate the Name/Value pair
			$nameValueParts = split(":", $thisVar);
			
			// Skip on Errors
			if(sizeof($nameValueParts) != 2)
				continue;
			
			$namePart = $nameValueParts[0];
			$valuePart = $nameValueParts[1];
				
			// Make sure the Variable Name has valid characters and length.
			if(!preg_match("/^\w{1,50}$/", $namePart))
				continue;
		
					
		  	// For Security, make sure that malicious users don't try to pass data through the URL for cross-site scripting
		  	// If we had a page that took parameter and blindly substituted them (with no htmlspecialchars)...
		  	// ... then a user could use our website to inject redirects and malcious scripts.
			if(preg_match("/</", $valuePart) || preg_match("/>/", $valuePart))
		  		throw new Exception("The Template System can not replace Script Tags.");


			$fileString = preg_replace("/\{VAR:".$namePart."\}/", $valuePart, $fileString);
		}
		
		return $fileString;
		
	}

	// This will extract 1st Level Nodes within the data string.
	// What will be left over in our data string is place-holders.
	// The 2nd, 3rd, etc. level containers may be held inside of the 1st level containers (to later be extracted recursively).
	// The nodeName may be "LOOP" or "CONTAINER"
	// Will return a Hash with 2 elements "Text", and "Nodes".
	// "Text" will have all of the 1st-level nodes removed.  The "Nodes" hash element will be array of the 1st level containers... or an empty array if no nodes are found.
	static function extractFirstLevelNodes($nodeName, $textData){

		if(!in_array($nodeName, array("LOOP", "CONTAINER")))
			throw new Exception("Error with Node Name");
		
		// We are going to Tokenize the start and end tags and count them numberically.
		// The numbers for the Start Tags will be numbered sequentially from start to end... regardless of heirarchy
		// The numbers for the start and end tags are separate from each other... so for highly nested containers, you may have the Start Tag count get very high within the Tokens before you hit the 1st End Tag.
		$startTagCounter = 0;
		while(preg_match("/\{$nodeName:Start\}/", $textData)){
			preg_replace("/\{$nodeName:Start\}/", "{" . $nodeName . ":Start:" . $startTagCounter . "}", $textData, 1);
			$startTagCounter++;
		}
			
		$endTagCounter = 0;
		while(preg_match("/\{$nodeName:End\}/", $textData)){
			preg_replace("/\{$nodeName:End\}/", "{" . $nodeName . ":End:" . $endTagCounter . "}", $textData, 1);
			$endTagCounter++;
		}
		
		// Let an HTML / Javascript person know when they messed up with their tag structure. 
		// This is an extented Exception... since they might not have permissions to see General Exceptions.
		if($startTagCounter != $endTagCounter){
			throw new ExceptionServerSideInclude("{$nodeName} Start and End tag counts do not match.");
		}
		
		
		
		// After tokenizing, we will be able to determine which Containers are at the 1st level.
		// We will extract 1st level containers (and remove all of the token numbers)
		// The first level data containers (which may contain Sub-containers) will be removed from our Data String (or HTML source) and replaced with a single-special variable.
		// Here is a visualization of the the counts (after the colon) and heirarchial strucutre represented in "tabs".
		// In this example, {START:0}->{END:4} and {START:5}->{END:5} are the only 1st level tags.
		/*
		{START:0}
			{START:1}
				{START:2}
				{END:0}
				{START:3}
				{END:1}
			{END:2}
			{START:4}
			{END:3}
		{END:4}
		{START:5}
		{END:5}
		 */
		
		// Now create an array for "start" positions and "end" positions.
		// The index of the arrays will represent the Token count... and the array value will represent the string positions of each tag.
		$starTagPositionsArr = array();
		for($i=0; $i<$startTagCounter; $i++){
			$starTagPositionsArr[$i] = strpos("{" . $nodeName . ":Start:" . $i . "}");
		}
		
		$endTagPositionsArr = array();
		for($i=0; $i<$endTagCounter; $i++){
			$endTagPositionsArr[$i] = strpos("{" . $nodeName . ":End:" . $i . "}");
		}
		
		// Now interleave the Start and End tags into a common array (based upon the string positions).
		// We will use the Stags (with tokenized numbers) as the key... and the string positions as the value... then we can sort.
		$integratedTagsByStringPosition = array();

		for($i=0; $i<$startTagCounter; $i++){
			$integratedTagsByStringPosition["{" . $nodeName . ":Start:" . $i . "}"] = $starTagPositionsArr[$i];
			$integratedTagsByStringPosition["{" . $nodeName . ":End:" . $i . "}"] = $endTagPositionsArr[$i];
		}
		
		asort($integratedTagsByStringPosition);
		
		
		// Record which Start/End tags (tokenized with numbers) represent the 1st level containers.
		// This are parrallel arrays.  Element 0 in both arrays represending a corresponding set of 1st level tags.  There can be multiple sets of first level tags.
		$firstLevelStartTags = array();
		$firstLevelEndTags = array();
		
		$startTagHeirarchyCount = 0;
		$endTagHeirarchyCount = 0;
		
		foreach(array_keys($integratedTagsByStringPosition) as $thisTagTokenized){
			
			$isStartTag = false;
			$isEndTag = false;
			
			if(preg_match("/\{$nodeName:Start/", $thisTagTokenized)){
				$startTagHeirarchyCount++;
				$isStartTag = true;
			}
			else if(preg_match("/\{$nodeName:End/", $thisTagTokenized)){
				$endTagHeirarchyCount++;
				$isEndTag = true;
			}
			else{
				throw new Exception("Error in Node Syntax");
			}
				
			// Let an HTML / Javascript person know when they messed up with their tag structure. 
			// This is an extented Exception... since they might not have permissions to see General Exceptions.
			if($endTagHeirarchyCount > $startTagHeirarchyCount)
				throw new ExceptionServerSideInclude("Hierarchal structure error with Start/End Tags: $thisTagTokenized");
				
			if($isStartTag && ($startTagHeirarchyCount - $endTagHeirarchyCount == 1))
				$firstLevelStartTags[] = $thisTagTokenized;
				
			if($isEndTag && ($startTagHeirarchyCount - $endTagHeirarchyCount == 0))
				$firstLevelEndTags[] = $thisTagTokenized;
		}
		
		$nodesArrayForReturn = array();
		
		// Extract all of the 1st Level containers... and leave behind a special variable.
		// The variable we leave behind will be similar in symantics... so we can replace the String Contents after processing of sub-containers returns.
		// We will just use "{CONTAINER:0}"... where the number is represented by the index of the firstLevelStart/End tags.
		for($i=0; $i<sizeof($firstLevelStartTags); $i++){
			
			$firstLevelRegEx = "/" . preg_quote($firstLevelStartTags[$i]) . "(.*)" . preg_quote($firstLevelEndTags[$i]) . "/";
			
			$firstLevelTextMatchArr = array();
			if(!preg_match($firstLevelRegEx, $textData, $firstLevelTextMatchArr))
				throw new Exception("Error extracting a first-level Node with the regular expression.");
			
			$firstLevelText = $firstLevelTextMatchArr[1];
			
			// We want to clean our our "Token Numbers" from the parameter containers.
			// Otherwise, it would confuse the Sub-Containers when we create objects out of them recursively.
			$firstLevelText = preg_replace("/\{$nodeName:Start:\d+\}/", "{" . $nodeName . ":Start}", $firstLevelText);
			$firstLevelText = preg_replace("/\{$nodeName:End:\d+\}/", "{" . $nodeName . ":End}", $firstLevelText);
			
			// Put the First Level Conainer Text Source into our array of Sub-Containers.
			$nodesArrayForReturn[$i] = $firstLevelText;
			
			//Now replace the first-level container with a variable.
			$textData = preg_replace($firstLevelRegEx, "{" . $nodeName . ":" . $i . "}", $textData);
		}
		
		return array("Text"=>$textData, "Nodes"=>$nodesArrayForReturn);
		
	}

	
}
