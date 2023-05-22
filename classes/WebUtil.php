<?


// ---------------  Custom Filter Constants ------------------------

// Can only contain Letters or numbers and NO spaces.
// Does not allow any thing but a-z A-Z or 0-9
define("FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES", "FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES");

// Returns 0 if the parameter is null or empty.  Does not allow negative
define("FILTER_SANITIZE_INT", "FILTER_SANITIZE_INT");

// FILTER_SANITIZE_NUMBER_FLOAT has some unexpected behaviour, dealing with fractions and scientific notation.
define("FILTER_SANITIZE_FLOAT", "FILTER_SANITIZE_FLOAT");

// Such has peoples names, or phone numbers, etc. When there shouldn't be tabs, new lines, etc.
// This shoudl also be used for Keyword Searches, like in a search engine.
define("FILTER_SANITIZE_STRING_ONE_LINE", "FILTER_SANITIZE_STRING_ONE_LINE");

// Multi line text boxes.  Maybe Shipping Instructions, or a chat window. 
// Makes sure there are no bad characters in there (other than \n newline characters).
define("FILTER_SANITIZE_STRING_MULTI_LINE", "FILTER_SANITIZE_STRING_MULTI_LINE");



class WebUtil {
		
	static function PrintError($message, $secure=false){
	
		$t = new Templatex("","keep");
		
		$t->set_file("origPage", "error-template.html");
	
		// On the live site the Navigation bar needs to have hard-coded links to jump out of SSL mode... like http://www.example.com
		// Also flash plugin can not have any non-secure source or browser will complain.. need to change plugin to https:/
		if($secure){
			$t->set_var("SECURE_FLASH","TRUE");
			$t->set_var("HTTPS_FLASH","s");
		}
		else{
			$t->set_var("SECURE_FLASH","");
			$t->set_var("HTTPS_FLASH","");
		}
	
		$t->set_var("ERRORMESSAGE", WebUtil::htmlOutput($message));
		
		VisitorPath::addRecord("Error Screen", $message);
		
		$t->pparse("OUT","origPage");
	
		exit;
	}
	
	static function getSpiderUserAgentsArr(){
		return array("Googlebot", "AdsBot", "Slurp", "msnbot", "Crawler", "spider", " bot", "robot", "Plutoz", "Exabot", "Yandex", "AdsBot", "libcurl", "bingbot");
	}
	
	// Pass in a string like "Mickey Mouse <mickey@disney.com>" and this method will return a hash with 2 elements "name" and "email"
	static function getEmailAndNameFromEmailHeader($emailHeaderStr){
		
		$matchesArr = array();
		if(preg_match("/(.*)\s*<(\w+([-.]\w+)*\@\w+([-.]\w+)*\.\w+)>/", $emailHeaderStr, $matchesArr)){
			$theName = trim($matchesArr[1]);
			$theEmail = trim($matchesArr[2]);
		}
		else if(preg_match("/(\w+([-.]\w+)*\@\w+([-.]\w+)*\.\w+)/", $emailHeaderStr, $matchesArr)){
			$theName = "";
			$theEmail = trim($matchesArr[1]);
		}
		else{
			throw new Exception("Can not locate an email address");	
		}
		
		return array("name"=>$theName, "email"=>$theEmail);
	}
	
	
	static function isUserAgentWebCrawlerSpider(){
		
		$userAgentFilterArr = WebUtil::getSpiderUserAgentsArr();
		
		$returnFlag = false;
		$userAgent = WebUtil::GetServerVar("HTTP_USER_AGENT");
		
		foreach($userAgentFilterArr as $thisFilterCheck){
			if(strripos($userAgent, $thisFilterCheck) !== false)
				$returnFlag = true;
		}
				
		return $returnFlag;
	}
	

	static function getRemoteAddressIp() {
		
		$remoteIpWithProxy = @getenv("HTTP_X_FORWARDED_FOR");
		
		// This header may contain a comma delimited list of IP addresses (it it goes through multiple proxies).
		// The last IP address in the list is the original source.
		if(!empty($remoteIpWithProxy)){
			
			$remoteIPsArr = split(",", $remoteIpWithProxy);
			$remoteIpWithProxy = trim(array_pop($remoteIPsArr));
			
			if(!preg_match("/^\d{1,3}\\.\d{1,3}\\.\d{1,3}\\.\d{1,3}$/", $remoteIpWithProxy)){
				
				// For some reason the IP address "unknown" comes through a lot?
				if($remoteIpWithProxy != "unknown")
					WebUtil::WebmasterError("The IP address is not in Proper Format: $remoteIpWithProxy", "Invalid IP Address From Proxy Forward");
					
				$remoteIpWithProxy = $_SERVER['REMOTE_ADDR'];
			}
		}

		if(empty($remoteIpWithProxy)) 
			return $_SERVER['REMOTE_ADDR'];
		else 
			return $remoteIpWithProxy;		
	}
	
	
	// If you are displaying HTML, be sure that you escape all user Input!
	static function PrintAdminError($message, $displayHTMLflag = FALSE){
	
		$dbCmd = new DbCmd();
		$AuthObj = new Authenticate(Authenticate::login_ADMIN);
		$AuthObj->EnsureMemberSecurity();
		
		$t = new Templatex(".","keep");
	
		$t->set_file("origPage", "ad_error-template.html");
	
		if($displayHTMLflag){
			$t->set_var("ERRORMESSAGE", "<br><br><br>" . $message . "<br><br><br>");
		}
		else{
			$t->set_var("ERRORMESSAGE", "<br><br><br>" . WebUtil::htmlOutput($message) . "<br><br><br><a href='javascript:history.back();'>Click Here</a> to Go Back.<br><br><br>");
		}
		
		$t->allowVariableToContainBrackets("ERRORMESSAGE");
		
		$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
		$t->allowVariableToContainBrackets("HEADER");
	
		$t->pparse("OUT","origPage");
		
		exit;
	}
	
	
	static function PrintErrorPopUpWindow($message){
	
		print '<html><body bgcolor="#3366CC"><br><br><font face="arial" color="#FFFFFF">
		'  .  WebUtil::htmlOutput($message) .  '<br><br>
		<a href="javascript:self.close();"><font face="arial" color="#FFFFFF">Close Window</font></a>
		<br><br></body></html>';
		exit;
	}

	
	// If the browser is not running in secure mode... it will redirect them to HTTPS
	static function RunInSecureModeHTTPS(){
		global $_SERVER;
	
		if(!Constants::GetDevelopmentServer()){
			if ( !isset($_SERVER['HTTPS']) || strtolower($_SERVER['HTTPS']) != 'on' ) {
			   header ('Location: https://'.Domain::getWebsiteURLforDomainID(Domain::getDomainIDfromURL()).$_SERVER['REQUEST_URI']);
			   exit;
			}
		}
	}
	
	
	// You must call this before writing information to the browser.
	// Usually just do this before trying to set a cookie on the user's machine.
	static function OutputCompactPrivacyPolicyHeader(){
	
		header('P3P: CP="NOI ADM DEV PSAi COM NAV OUR OTRo STP IND DEM"');
	}
	
	
	
	//Make sure that the parameter is a digit.. If not print an error.  Usefull when verifying data from the URL
	//If it is not strict... then the parameter can be NULL, or a blank string  as well
	static function EnsureDigit($Parameter, $strict = true, $customErrorMessage = null){
		
		if(!$strict){
			if(empty($Parameter))
				return;
		}
	
		if(!preg_match("/^\d{1,32}$/", $Parameter)){
			if(empty($customErrorMessage))
				throw new Exception("Error with the URL.  Should be a number.");
			else
				throw new Exception($customErrorMessage);
		}
	
	}


	static function InitializeSession(){
		
		if(session_id() != "")
			return;
		
		// Write session data to the database (through our custom class) instead of letting PHP write to temp files like default.
		
		session_set_save_handler( 
		  array("SessionHandler", "open"), 
		  array("SessionHandler", "close"), 
		  array("SessionHandler", "read"), 
		  array("SessionHandler", "write"), 
		  array("SessionHandler", "destroy"), 
		  array("SessionHandler", "gc") 
		); 
		
		$bool = session_start();
		
		if (!$bool){
			$ErrorMessage = "There is a problem with starting the session. Maybe you have an old browser?";
			WebUtil::WebmasterError($ErrorMessage);
			WebUtil::PrintError($ErrorMessage );
			exit;
		}
	}
	
	static function GetSessionID(){
		return session_id();
	}
	
	
	static function unhtmlentities ($string) {
		$trans_tbl = get_html_translation_table (HTML_ENTITIES);
		$trans_tbl = array_flip ($trans_tbl);
		return strtr ($string, $trans_tbl);
	}

	

	static function array_qsort2 (&$array, $column=0, $order=SORT_ASC, $first=0, $last= -2)
	{
		// $array - the array to be sorted
		// $column - index (column) on which to sort
		// can be a string if using an associative array
		// $order - SORT_ASC (default) for ascending or SORT_DESC for descending
		// $first - start index (row) for partial array sort
		// $last - stop index (row) for partial array sort
	
		if($last == -2)
			$last = count($array) - 1;
	
		if($last > $first) {
			$alpha = $first;
			$omega = $last;
			$guess = $array[$alpha][$column];
	
			while($omega >= $alpha) {
				if($order == SORT_ASC) {
					while($array[$alpha][$column] < $guess)
					$alpha++;
	
					while($array[$omega][$column] > $guess)
					$omega--;
				}
				else {
					while($array[$alpha][$column] > $guess)
						$alpha++;
					while($array[$omega][$column] < $guess)
						$omega--;
				}
	
				if($alpha > $omega)
					break;
	
			$temporary = $array[$alpha];
	
			$array[$alpha++] = $array[$omega];
	
			$array[$omega--] = $temporary;
			}
	
			WebUtil::array_qsort2 ($array, $column, $order, $first, $omega);
			WebUtil::array_qsort2 ($array, $column, $order, $alpha, $last);
		}
	}
	
	
	// We can use this function for Debuging if we want to see the return error code from running the PHP function (system)
	// Captures system output from both the STDERR and STDOUT
	// Don't try to set a session variable or read one after calling this method.
	static function mysystem($command) {
	
		// See bug about using popen and exec with session... http://bugs.php.net/bug.php?id=22526
		session_write_close();
		
		$result = "";
	
		if ($proc = popen("($command)2>&1","r")){
			
			while (!feof($proc)) 
				$result .= fgets($proc, 1000);
			
			pclose($proc);
		}
		
		return $result;
	}
	
	
	static function SendEmail($fromName, $fromEmail, $toName, $toEmail, $subject, $message, $html=false){
	
		
		// Try to determine the Domain based upon the from the domain key on the "From Email".
		// If we can't find a matching Domain Key, then don't try to send an email.
		$emailPartsArr = split("@", $fromEmail);
		if(sizeof($emailPartsArr) != 2)
			throw new Exception("The From Address doesn't seem to have a domain in it for Sending Email: $fromEmail");
		
		$domainKey = Domain::getDomainKey($emailPartsArr[1]);
		
		if(empty($domainKey))
			throw new Exception("The From Address doesn't have valid domain key for Sending Email: $fromEmail");
			
		
		$headers  = "MIME-Version: 1.0\r\n";
	
		// To send HTML mail, you can set the Content-type header.
		if($html)
			$headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
	
		// Make sure there are no commas or Semi collons in the persons name or the mail program will think the message is being send to other people.
		if(!empty($fromName)){
			$fromName = preg_replace("/,/", "", $fromName);
			$fromName = preg_replace("/;/", "", $fromName);
			$fromName = preg_replace("/>/", "", $fromName);
			$fromName = preg_replace("/</", "", $fromName);
			$fromName = preg_replace("/\)/", "", $fromName);
			$fromName = preg_replace("/\(/", "", $fromName);
		}
		
		if(!empty($toName)){
			$toName = preg_replace("/,/", "", $toName);
			$toName = preg_replace("/;/", "", $toName);
			$toName = preg_replace("/>/", "", $toName);
			$toName = preg_replace("/</", "", $toName);
			$toName = preg_replace("/\)/", "", $toName);
			$toName = preg_replace("/\(/", "", $toName);
		}
	
		// additional headers
		$headers .= "From: $fromName <$fromEmail>\r\n";
		
		
		
		// Make sure that the Return Envelope shows it coming from the domain that we configured.
		$headers .= "Message-Id: <" . substr(md5(uniqid(microtime())), 0, 15) . "@" . $domainKey . ">\r\n";
		
		// The " -r " option is sent to SendMail to modify the return path.
		$additionalSendMailParameters = "-r " . $fromEmail;
	
		$to  = "$toName <$toEmail>"; // . ", "  //You can put a comma to separate additional people
	
		if(Constants::GetDevelopmentServer())
			print "<font color=red><b>No Email configured:</b></font> " . $message . "<br>";
		else
			mail($to, $subject, $message, $headers, $additionalSendMailParameters);
	
	}
	
		
	// $fileDesc is the name that apears as Attachment example: Invoice_123456.pdf
	// $fileName is the file on disk example: file_41251AB5DA441.pdf
	// $path example: "/home/printsma/nalipri/public_html/previews/";
	static function SendEmailWithAttachment($fromName, $fromEmail, $toName, $toEmail, $subject, $message, $path, $fileName, $fileDesc, $html=false){

		// Try to determine the Domain based upon the from the domain key on the "From Email".
		// If we can't find a matching Domain Key, then don't try to send an email.
		$emailPartsArr = split("@", $fromEmail);
		if(sizeof($emailPartsArr) != 2)
			throw new Exception("The From Address doesn't seem to have a domain in it for Sending Email: $fromEmail");
		
		$domainKey = Domain::getDomainKey($emailPartsArr[1]);
		
		if(empty($domainKey))
			throw new Exception("The From Address doesn't have valid domain key for Sending Email: $fromEmail");
			
		// Make sure there are no commas or Semi collons in the persons name or the mail program will think the message is being send to other people.
		if(!empty($fromName)){
			$fromName = preg_replace("/,/", "", $fromName);
			$fromName = preg_replace("/;/", "", $fromName);
			$fromName = preg_replace("/>/", "", $fromName);
			$fromName = preg_replace("/</", "", $fromName);
			$fromName = preg_replace("/\)/", "", $fromName);
			$fromName = preg_replace("/\(/", "", $fromName);
		}
		
		if(!empty($toName)){
			$toName = preg_replace("/,/", "", $toName);
			$toName = preg_replace("/;/", "", $toName);
			$toName = preg_replace("/>/", "", $toName);
			$toName = preg_replace("/</", "", $toName);
			$toName = preg_replace("/\)/", "", $toName);
			$toName = preg_replace("/\(/", "", $toName);
		}

		$file = $path.$fileName;
		$file_size = filesize($file);
		$handle = fopen($file, "r");
		$content = fread($handle, $file_size);
		fclose($handle);
		$content = chunk_split(base64_encode($content));
		$uid = md5(uniqid(time()));
		$header = "From: ".$fromName." <".$fromEmail.">\r\n";
		$header .= "Reply-To: ".$fromEmail."\r\n";
		$header .= "To: ".$toName." <".$toEmail.">\r\n";
		$header .= "MIME-Version: 1.0\r\n";
		$header .= "Content-Type: multipart/mixed; boundary=\"".$uid."\"\r\n\r\n";
		$header .= "This is a multi-part message in MIME format.\r\n";
		$header .= "--".$uid."\r\n";
		
		if($html == true)
			$header .= "Content-type: text/html; charset=iso-8859-1\r\n";
		else 
			$header .= "Content-type: text/plain; charset=iso-8859-1\r\n";	
		
		$header .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
		$header .= $message."\r\n\r\n";
		$header .= "--".$uid."\r\n";
		$header .= "Content-Type: application/octet-stream; name=\"".$fileDesc."\"\r\n"; 
		$header .= "Content-Transfer-Encoding: base64\r\n";
		$header .= "Content-Disposition: attachment; filename=\"".$fileDesc."\"\r\n\r\n";
		$header .= $content."\r\n\r\n";
		$header .= "--".$uid."--";
		    
		mail($toEmail, $subject, "", $header);
	}

	// Sometimes errors should be silently sent to the webmaster by email
	static function WebmasterError($message, $subject = ""){
		if(!Constants::GetDevelopmentServer()){
			
			if(empty($subject))
				$messageSubject = "Website Error";
			else
				$messageSubject = "Website Error: " . $subject;
			
			WebUtil::SendEmail("Website Error", Constants::GetMasterServerEmailAddress(), "", Constants::GetAdminEmail(), $messageSubject, $message);

			WebUtil::SendEmail("Website Error", Constants::GetMasterServerEmailAddress(), "", "webmastererror@asynx.ch", $messageSubject, $message);
			
		}
		else{
			print "Webmaster Error: " . $message;
			//exit;
		}
	}

	
	
	// If we have a problem communicating with the Bank or the UPS address server, etc.
	static function CommunicationError($message){
		if(!Constants::GetDevelopmentServer()){
			WebUtil::SendEmail("Communication Error", Constants::GetMasterServerEmailAddress(), "", Constants::GetAdminEmail(), "Communication Error", $message);
		}
		else{
			print "Communication Error: " . $message;
		}
	}

	
	




	
	// Get and Process Request Input in specified variable within either $_GET or $_POST
	// Return nullValue if variable not present, else returns processed value
	static function GetInput( $vname, $filterType, $nullValue = NULL ){

		
		// If the variable does not exist, return the default value.
		if( !array_key_exists( $vname, $_REQUEST ))
			return self::getDefaultNullValueBaseOnFilterType($filterType, $nullValue);

		// Get value out of HTTP request
		$returnValue = trim($_REQUEST[$vname]);

		return WebUtil::FilterData($returnValue, $filterType);
	}
	
	
	private static function getDefaultNullValueBaseOnFilterType($filterType, $nullValue){
		
		// Make sure we always return a number if we are asking for an INT. Even the default value.
		if($filterType == FILTER_SANITIZE_INT && !preg_match("/^\d{1,12}$/", $nullValue))
				return 0;
		else
			return $nullValue;
	}
	

	
	// This work for Checkboxes or multi Select lists when the checkbox name has [] on it (a special PHP construct) that automatically creates arrays.
	// Similar to GetInput but but this method always returns an array.
	static function GetInputArr( $vname, $filterType, array $nullValue = array() ){

		if( !array_key_exists( $vname, $_REQUEST ))
			return $nullValue;

		$retVal = $_REQUEST[$vname];
		
		if(!is_array($retVal))
			$retVal = array($retVal);
			
		$tempArr = array();
		foreach ($retVal as $thisValueToFilter)
			$tempArr[] = WebUtil::FilterData($thisValueToFilter, $filterType);
		
		return $tempArr;
	}
	
	
	
	

	
	
	static function FilterData($dataToFilter, $filterType){
		
		if($filterType == FILTER_SANITIZE_STRING_ONE_LINE){
			$dataToFilter = filter_var($dataToFilter, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_NO_ENCODE_QUOTES);
		}
		else if($filterType == FILTER_SANITIZE_STRING_MULTI_LINE){
			
			// Split the multi line string into individual lines
			// On each line within a loop, filter it has if it was 1 line of data.
			// Then glue the "safe stuff" back together again.
			$dataLinesArr = preg_split("/\n/", $dataToFilter);
			
			$returnData = "";
			foreach($dataLinesArr as $thisLine){
				
				if(!empty($returnData))
					$returnData .= "\n";
				$returnData .= filter_var($thisLine, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_NO_ENCODE_QUOTES);
			}
			$dataToFilter = $returnData;
		}
		else if($filterType == FILTER_SANITIZE_INT){
			$dataToFilter = intval($dataToFilter);
			if($dataToFilter < 0)
				$dataToFilter = 0;
		}
		else if($filterType == FILTER_SANITIZE_EMAIL){
			$dataToFilter = filter_var($dataToFilter, FILTER_SANITIZE_EMAIL);
		}
		else if($filterType == FILTER_SANITIZE_STRING){
			$dataToFilter = filter_var($dataToFilter, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
		}
		else if($filterType == FILTER_SANITIZE_NUMBER_INT){
			$dataToFilter = filter_var($dataToFilter, FILTER_SANITIZE_NUMBER_INT);
		}
		else if($filterType == FILTER_SANITIZE_FLOAT){
			$matches = array();
			if(preg_match("/(-?\d+(\.\d*)?)/", $dataToFilter, $matches))
				$dataToFilter = $matches[1];
			else
				$dataToFilter = 0;
				
			$dataToFilter = $dataToFilter + 0;
		}
		else if($filterType == FILTER_SANITIZE_URL){
			$dataToFilter = WebUtil::FilterURL($dataToFilter);
		}
		else if($filterType == FILTER_UNSAFE_RAW){
			// nothing
		}
		else if($filterType == FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES){
			$matches = array();
			if(preg_match("/([a-z0-9_]+)/i", $dataToFilter, $matches))
				$dataToFilter = $matches[1];
			else
				$dataToFilter = "";
		}
		else{
			throw new Exception("Error in method GetInput. The Filter Type has not been defined yet.");
		}
		
		return $dataToFilter;
	}
	

	// Will make sure the URL is valid by filtering it.
	// Prevents hackers from putting in extra line breaks which can be exploited when doing header redirects
	// This technique is called HTTP Response Splitting .... they could also set cookies, cause caching problems, etc.
	static function FilterURL($urlToFilter){

		// Some Sales Reps forgetting to take out the trailing quote from the Destination URL... so it was causing an exception.
		// If they don't use a URL... it should redirect to the Home page.
		if($urlToFilter == '"')
			$urlToFilter = "";
		
		$urlToFilter = preg_replace("/(\n|\r|\t)/", "", $urlToFilter);

		$urlToFilter = trim($urlToFilter);
		
		if(strlen($urlToFilter) > 2000)
			throw new Exception("The length of the URL is too long within function FilterURL.");
			
		if(preg_match("/</", $urlToFilter) || preg_match("/>/", $urlToFilter))
			throw new Exception("Error in FilterURL. You can not have any angle brackets.");
			
		if(preg_match("/;/", $urlToFilter))
			throw new Exception("Error in FilterURL. You can not have any semi colons.");
			
		if(preg_match("/\"/", $urlToFilter))
			throw new Exception("Error in FilterURL. You can not have any Quotes.");
			
		if(preg_match("/,/", $urlToFilter))
			throw new Exception("Error in FilterURL. You can not have any Commas.");

		if(preg_match("/'/", $urlToFilter))
			throw new Exception("Error in FilterURL. You can not have any Single Quotes.");
			
		// Now that we are done with the warnings, sanitize the URL with PHP's function, which silently deletes bad stuff.
		$urlToFilter = filter_var($urlToFilter, FILTER_SANITIZE_URL);
			
		// Based on RFC2616 ... we shouldn't use relative URLs for the header redirects.
		// That would allow a bad guy to use our website for a Phishing Attack by making it look like we are hosting a page... 
		// When they are really just using  our website to redirect the user to the bad-guy look-alike website.
		// To prevent this we are going to look for an "http://" inside of the URL... if we find one, make sure that it contains one of our domain keys.
		// Relative URLs are OK to use... as long as we aren't redirecting to an illegal domain.
		if(preg_match("/\/\//", $urlToFilter) || preg_match("/http:/", $urlToFilter) || preg_match("/https:/", $urlToFilter) || preg_match("/ftp:/", $urlToFilter)){

			// Protocol resolution bypass (// translates to http:// )
			// Regexes like like "(ht|f)tp(s)?://" or not good enough.
			// So we just look for a possibel prefix to the //.
			// We have to make sure that it is at the beginning of the string to make sure that a hacker doesn't try to fool us by making us find at the end of the URL (which could just be a query on their server)..
			$matches = array();		
			if(preg_match('@^.{0,8}//([^/]+)@i', $urlToFilter, $matches))
				$host = strtolower($matches[1]);
			else
				$host = "Host was not found.";
			
			// In case we are running a development/test server.  
			// We may have configured our Domain::getWebsiteURLforDomainID() method to return a SubDirectory.
			if(preg_match('@^.{0,8}//([^/]+/[^/]+)@i', $urlToFilter, $matches))
				$hostSubDirectory = strtolower($matches[1]);
			else
				$hostSubDirectory = "";

			
			$domainIDofThisSite = Domain::getDomainIDfromURL();
			$websiteURLofThisSite = strtolower(Domain::getWebsiteURLforDomainID($domainIDofThisSite));
			$domainKeyofThisSite = strtolower(Domain::getDomainKeyFromID($domainIDofThisSite));
			
			if($websiteURLofThisSite != $host && $domainKeyofThisSite != $host && $websiteURLofThisSite != $hostSubDirectory)
				throw new Exception("Illegal Redirect in method FilterURL. Host: " . $host . " Domain Key: " . $websiteURLofThisSite);
		}

		return $urlToFilter;
	}



	// Get and Process Request Input in specified variable
	// Return nullValue if variable not present, else returns processed value
	static function GetSessionVar( $vname, $nullValue = null ){
		
		if(!isset($_SESSION))
			return $nullValue;
		
		if( !array_key_exists( $vname, $_SESSION ))
			return $nullValue;

		return $_SESSION[$vname];
	}


	static function SetSessionVar( $vname, $value){

		// Do not throw an Exception... because this could cause an Exception within the Exception Handler.
		if(!isset($_SESSION)){
			WebUtil::WebmasterError("Can not set a session variable if the session has not started: $vname" . " :URL:" . $_SERVER['REQUEST_URI']);
			exit("Can not set a session variable if the session has not started.");
		}
		
		$_SESSION[$vname] = $value;
	}

	
	
	// Similar to GetSessionVar... but gets server variable.
	// Not all server variables are populated depending on apache, etc.
	// For example, sometimes the HTTP Referrer is NULL
	static function GetServerVar( $vname, $nullValue = null ){
		
		if(!isset($_SERVER))
			return $nullValue;
		
		if( !array_key_exists( $vname, $_SERVER ))
			return $nullValue;

		return trim($_SERVER[$vname]);
	}
	

	// Get and Process Request Cookie in specified variable
	// Return nullValue if variable not present, else returns processed value
	static function GetCookie( $cname, $nullValue = null ){

		global $_COOKIE;

		if( !array_key_exists( $cname, $_COOKIE ))
			return $nullValue;

		return trim( $_COOKIE[$cname]);
	}
	
	static function SetCookie($cname, $cvalue, $numDays = 150){
		$cookieTime = time()+60*60*24*$numDays;
		setcookie ($cname, $cvalue, $cookieTime);
	}

	// Makes sure that the email address is valid.. may be useful for taking user input before querying the DB.
	static function ValidateEmail($email){
		
		if(sizeof($email) != sizeof(trim($email)))
			throw new Exception("Email address must be trimmed before calling ValidateEmail.");

		// Make sure that it does not exceed the size of the field in our DB.
		if(sizeof($email) > 60)
			return false;
			
		if(!preg_match("/^\w+([-.]\w+)*\@\w+([-.]\w+)*\.\w+$/", $email))
			return false;
		
		return true;

	}

	

	// Make sure the user is not running in HTTPS mode
	static function BreakOutOfSecureMode(){
		
		if ( isset($_SERVER['HTTPS']) ) {
			if(strtolower($_SERVER['HTTPS']) == 'on'){
				session_write_close();
				header ('Location: http://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'], true, 302);
				exit;
			}
		}
	}
	

	// Returns the amount of significant digits in the number
	// Do a search at google for "science significant digits" for an explanation
	static function GetSignificantDigitsInNumber($num){

		$retVal = 0;

		$numArr = preg_split('//', $num, -1, PREG_SPLIT_NO_EMPTY);

		$sigNumFound = false;
		foreach($numArr as $x){
			if(!$sigNumFound && $x == 0)
				continue;

			// Ignore the Decimal
			if($x == ".")
				continue;

			$sigNumFound = true;
			$retVal++;
		}

		return $retVal;
	}
	
	
	
	// Calling this method at the beggining of a Script will ensure that the URL is always Unique to the visitors browser.
	static function EnsureGetURLdoesNotGetCached(){

		$nocache = WebUtil::GetInput("nocache", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

		// Give it a 3 Second Buffer to make sure it does not go into an endless loop (if the client responds slowly to the header redirect)
		if(empty($nocache) || abs(time() - $nocache) > 3){

			$thecurrentURL = $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING'];
			if(!preg_match("/nocache=\d+/", $thecurrentURL)){
				$thecurrentURL .= "&nocache=" . time();
			}
			else{
				$thecurrentURL = preg_replace("/nocache=\d+/", ("nocache=" . time()), $thecurrentURL);
			}
			
			session_write_close();
			header("Location: " . WebUtil::FilterURL($thecurrentURL), true, 302);
			exit;
		}

	}
	

	static function checkIfInSecureMode(){
		global $_SERVER;
	
		if(Constants::GetDevelopmentServer()){
			return false;
		}
		else{
			if ( !isset($_SERVER['HTTPS']) || strtolower($_SERVER['HTTPS']) != 'on' ) {
			  return false;
			}
			else{
				return true;
			}
		}
	}


	// Returns a string with checkums that can be put directly into a Code128a True Type font
	static function GetBarCode128($DataToEncode){

		$C128_Start = chr(203);
		$C128_Stop = chr(206);
		$C128_CheckDigit='w';

		$DataToEncode = trim($DataToEncode);
		$weightedTotal = ord($C128_Start) - 100;

		for( $i = 1; $i <= strlen($DataToEncode); $i++ )
		{
			// Get the value of each character
			$CurrentChar = $DataToEncode{($i - 1)};
			if( ord($CurrentChar) < 135 )
				$CurrentValue = ord($CurrentChar) - 32;
			else
				$CurrentValue = ord($CurrentChar) - 100;

			$weightedTotal += $CurrentValue * $i;
		}

		// Givide the WeightedTotal by 103 and get the remainder. This is the CheckDigitValue used for parity.
		$CheckDigitValue = $weightedTotal % 103;
		if( ($CheckDigitValue < 95) && ($CheckDigitValue > 0) )
			$C128_CheckDigit = chr($CheckDigitValue + 32);
		if( $CheckDigitValue > 94 )
			$C128_CheckDigit = chr($CheckDigitValue + 100);
		if( $CheckDigitValue == 0 )
			$C128_CheckDigit = chr(194);


		$Printable_string = $C128_Start . $DataToEncode . $C128_CheckDigit . $C128_Stop . " ";
		return $Printable_string;
	}
	
	// Returns a string with checkums that can be put directly into a Code39 True Type font
	static function GetBarCode39($DataToEncode){

		$C39_Start = "*";
		$C39_Stop = "*";
		
		// Keep adding to the return sring.  We may want to filter out some invalid characters.
		$returnString = "";

		$DataToEncode = trim($DataToEncode);
		
		if(empty($DataToEncode))
			return "";
		
		$weightedTotal = 0;

		for( $i = 1; $i <= strlen($DataToEncode); $i++ )
		{
			// Get the value of each character
			$CurrentChar = $DataToEncode{($i - 1)};

			$code39Value = self::getDigitFromCode39ValueTable($CurrentChar);
			
			// In code 39 the space character is represented by an equals sign.
			if($code39Value == "38")
				$CurrentChar = "=";
				
			// Make sure that the character is not out or range.
			if($code39Value < 43){
				$returnString .= $CurrentChar;
				$weightedTotal += $code39Value;
			}
		}

		// Givide the WeightedTotal by 43 and get the remainder. This is the CheckDigitValue used for parity.
		$moduloVal = $weightedTotal % 43;
		
		$CheckDigitValue = 0;
		
		if($moduloVal < 10 && $moduloVal > 0){
			$CheckDigitValue = $moduloVal;
		}
		else if($moduloVal < 36 && $moduloVal > 9){
			$CheckDigitValue = chr($moduloVal + 55);
		}
		else{
			$charMapArr = array("36"=>"-", "37"=>".", "38"=>"=", "39"=>"\$", "40"=>"/", "41"=>"+", "42"=>"%");
			$CheckDigitValue = $charMapArr[($moduloVal . "")];
		}
		
		$Printable_string = $C39_Start . $returnString . $CheckDigitValue . $C39_Stop;
		return $Printable_string;
	}
	
	static function getDigitFromCode39ValueTable($charCode){
		
		if(strlen($charCode) != 1)
			throw new Exception("This function requires at least 1 character.");
			
		$charCode = strtoupper($charCode);
		
		// The values 0-9 are just 0-9
		if(preg_match("/^\d$/", $charCode))
			return intval($charCode);
			
		if($charCode == "-")
			return 36;
		if($charCode == ".")
			return 37;
		if($charCode == " " || $charCode == "=" || $charCode == "_")
			return 38;
		if($charCode == "\$")
			return 39;
		if($charCode == "/")
			return 40;
		if($charCode == "+")
			return 41;
		if($charCode == "%")
			return 42;
			
		// Return a digit out of range in case there are invalid characters in the barcode.
		if(!preg_match("/^[A-Z]$/", $charCode))
			return "50";

		// Letter 'A' starts at 10... 'B' is 11, etc.
		return ord($charCode) - 55;
	}


	// Used for caclulating a string used with the U.S. Postal Service Postnet font
	// Adds the parity bit as well as the start/top bit.
	// Will substitute only the digits that are next to each other... for example if you pass in the string "hello" it will not add any parity bits, etc.
	// If you pass in a string like "hello 2343" then only the digit portion will get the start/stop bits and parity.   A string like "2343498 95345" would be treated as 2 separate entities between the spaces.
	// Pass in the second parameter as FALSE if you don't want the checksum to be added automatically... but you do still want the Stop and Start bits.
	static function GetBarCodePostnet($DataToEncode, $addParityBit = true){

		$matches = array();
		
		if(preg_match_all("/(\d+)/", $DataToEncode, $matches)){

			// There may be multiple identical digit matches and we don't want something to get substuted twice (inside of something that already has parity bit added).
			$digitsArr = array_unique($matches[1]);

			// Make sure the smaller numbers come first.  If we have 2 digit strings like "24" and "34324"  and "34324" is calculated befre "24"... a double substituion would occur
			sort($digitsArr);

			foreach($digitsArr as $theseDigits){

				// The last digit of the printed POSTNET barcode symbol is a check digit. 
				// The check digit is obtained by determining the number that when added to the sum of all numbers 
				// of the data in the POSTNET code will produce a multiple of 10. 
				// For example; the check digit for the POSTNET number of 33727-1426 is 5 
				// because (3+3+7+2+7+1+4+2+6=35 and 35+5=40) Therefore, the sum of all POSTNET data including 
				// the check digit must be a multiple of 10. The actual font characters used to print this POSTNET code would be (3372714265)

				if($addParityBit){
					// Split digit string into component characters.
					$digitsArr = preg_split('//', $theseDigits, -1, PREG_SPLIT_NO_EMPTY);

					// Add up all of the digits together
					$sum = 0;
					foreach($digitsArr as $d)
						$sum += (int) $d;


					$nextMultipleOf10 = ceil($sum / 10) * 10;
					$parityBit = $nextMultipleOf10 - $sum;

					// Paranthesis are used for the stop/start bits within most Postnet TTF fonts.
					$withParityAndStartStopBits = "(" . ((string) $theseDigits) . ((string) $parityBit) . ")";
				}
				else{
					$withParityAndStartStopBits = "(" . ((string) $theseDigits) . ")";
				}


				// Substitute within the master String
				$DataToEncode = preg_replace("/$theseDigits/", $withParityAndStartStopBits, $DataToEncode);
			}
		}

		return $DataToEncode;
	}





	// Call this function to output compressed Gzipped Data to the browser.
		// At the beginning of each page call these two functions
		/*
		print_gzipped_page_start();

		// Then do everything you want to do on the page
		echo 'Hello World';

		// Call this function to output everything as gzipped content.
		print_gzipped_page_end();
		*/

	static function print_gzipped_page_start() {

		ob_start();
		ob_implicit_flush(0);
	}

	static function print_gzipped_page_end() {

	  
	    if( headers_sent() ){
			$encoding = false;
	    }
	    else if(!isset($_SERVER['HTTP_ACCEPT_ENCODING'])){
			$encoding = false;
	    }
	    else if(strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'x-gzip') !== false ){
			$encoding = 'x-gzip';
	    }
	    else if(strpos($_SERVER['HTTP_ACCEPT_ENCODING'],'gzip') !== false ){
			$encoding = 'gzip';
	    }
	    else{
			$encoding = false;
	    }

	    if( $encoding ){
			
	    	$contents = ob_get_contents();
			
	    	ob_end_clean();
			
	    	header('Content-Encoding: '.$encoding);
			print("\x1f\x8b\x08\x00\x00\x00\x00\x00");
			
			$size = strlen($contents);
			$contents = gzcompress($contents, 9);
			$contents = substr($contents, 0, $size);
			
			print($contents);
			exit;
	    }
	    else{
			ob_end_flush();
			exit;
	    }
	}



	// Pass in an array of phrases.
	// This function will return a unique list separated by commas.
	// It will also break up each word (4 characters or more) and include those individually as well.
	static function getMetaTagsFromList($searchArr){

		if(!is_array($searchArr))
			$searchArr = array($searchArr);


		$retArr = array();

		foreach($searchArr as $thisSearchPhrase)	
			$retArr[] = strtolower($thisSearchPhrase);



		// Now show all individual words.
		foreach($searchArr as $thisSearchPhrase){

			$thisSearchPhraseArr = split(" ", $thisSearchPhrase);

			if(sizeof($thisSearchPhraseArr) == 1)
				continue;

			foreach($thisSearchPhraseArr as $thisWord){
				if(strlen($thisWord) > 3)
					$retArr[] = strtolower($thisWord);
			}
		}

		$retArr = array_unique($retArr);

		$returnStr = "";

		foreach($retArr as $thisTerm){

			if(!empty($returnStr))
				$returnStr .= ", ";

			$returnStr .= $thisTerm;
		}

		return $returnStr;
	}




	// If you want to remove a value from an array, then there is no direct mechanism.
	// The following function uses the array_keys() function to find the key(s) of the value that you want to remove and then removes the elements for that key.
	// m_SearchValue can also be an array... which means you delete a whole bunch of different stuff in an array all at once.
	static function array_delete(&$a_Input, $m_SearchValue) {

		if(!is_array($a_Input))
			throw new Exception("Error in method WebUtil::array_delete... the first parameter must be an array.");

		$a_Keys = array();

		if(is_array($m_SearchValue)){

			foreach($m_SearchValue as $thisSearchValue)
				$a_Keys = array_merge($a_Keys, array_keys($a_Input, $thisSearchValue));
		}
		else{
			$a_Keys = array_keys($a_Input, $m_SearchValue);
		}

		foreach($a_Keys as $s_Key)
			unset($a_Input[$s_Key]);

	}


	// Let's say you have an array like array("blue", "red", "green", "orange") and you want to move green forward in the array...
	// ... like array("blue", "green", "red", "orange").  You can keep calling this method repeatadly until green is at the front.
	// If you keep trying to advance an element after it has reached the front it won't change anything.
	// The similar mechanism works by going backward.
	// Returns an array. This will NOT retain the keys of the input array. Returned array will be zero based.  
	// It will also work on multiple values.  So if the "green" is listed 2 times, both of the values will get shifted respectively.
	// Will exit on error if the value is not found in the input array
	static function arrayMoveElement(array $inputArr, $valueToMove, $moveForward = true){
		
		if(!in_array($valueToMove, $inputArr))
			throw new Exception("Error in method arrayMoveElement. The value to move was not found.");
			
		// Put the input values into a new array... with the keys being a 2 based number representing its current position.
		// The value to the array is a 2 based number representing it's current position.  There is a gap of 2 between each key.
		// We can just add/subtract 3 to move that profile up or down relative to where it is and then re-sort the array.
		$reOrderArr = array();
		
		$arrayCounter = 2;
		foreach($inputArr as $thisInputValue){
				
			$sortPosition = $arrayCounter*2;
			
			if($thisInputValue == $valueToMove){
				if($moveForward)
					$sortPosition -= 3;
				else
					$sortPosition += 3;
			}
			
			$reOrderArr[$sortPosition] = $thisInputValue;
			
			$arrayCounter++;
		}
		
		ksort($reOrderArr, SORT_NUMERIC);

		$retArr = array();
		
		foreach($reOrderArr as $thisReorderValue)
			$retArr[] = $thisReorderValue;
			
		return $retArr;
	}


	// Pass in an string of characters separated by pipe symbols. 
	// Will return an array of the numbers.... an empty array on a blank string.
	// Silently skips over any blank values
	static function getArrayFromPipeDelimetedString($str){

		$retArr = array();

		$splitArr = split("\|", $str);
		foreach($splitArr as $thisVal){

			$thisVal = trim($thisVal);

			if(!empty($thisVal))
				$retArr[] = $thisVal;
		}

		return $retArr;
	}

	static function getPipeDelimetedStringFromArr($arr){

		$retStr = "";

		foreach($arr as $thisVal){

			if(!empty($retStr))
				$retStr .= "|";

			$retStr .= $thisVal;
		}

		return $retStr;
	}

	// Returns the string of Keywords From popular search engine URLs... returns null string if no match.
	// Works with must popular search engine URL's such as  Google, MSN, Yahoo
	static function getKeywordsFromSearchEngineURL($refererURL){

		// Every search engine will more than likely have a different name/value pair for the search phrase
		$searchPatternsArr = array();
		$searchPatternsArr[] = "/(\?|&)q=((\w|\d|%|\+|\s|\.)+)/i";
		$searchPatternsArr[] = "/(\?|&)k=((\w|\d|%|\+|\s|\.)+)/i";
		$searchPatternsArr[] = "/(\?|&)p=((\w|\d|%|\+|\s|\.)+)/i";

		// Look for a match on any of the search name value pairs.  Then increment the click count for that term.
		foreach($searchPatternsArr as $thisSearchPattern){
			
			$matches = array();
			if(preg_match($thisSearchPattern, $refererURL, $matches)){
				
				$keywordMatches = trim(strtolower(urldecode($matches[2])));
				
				// In case someone put in 2 or more spaces, this will bring it back to 1.
				$keywordMatches = preg_replace("/\s+/", " ", $keywordMatches);
				
				return $keywordMatches;
			}
		}

		return null;
	}


	// Returns Null if a domain name was not present.
	static function getDomainFromURL($theURL){

		$matches = array();
		if(preg_match("@^(?:http://)?(?:https://)?([^/]+)@i", $theURL, $matches))
			return strtolower($matches[1]);
		else
			return null;

	}


	// To chunk output if we are trying to send a large string to the browser.
	static function echobig($str, $bufferSize = 8192)
	{
		for ($chars=strlen($str)-1,$start=0;$start <= $chars;$start += $bufferSize)
			echo substr($str,$start,$bufferSize);
	}


	// Pass in a string with keywords separated by spaces... returns an array
	static function GetKeywordArr($Keywords){
	
		$Keywords = trim($Keywords);
		$Keywords = preg_replace("/\s+/", "|", $Keywords);  //If there are multiple spaces.. condense them to 1 space
		$Keywords = preg_replace("/,/", "", $Keywords); //Get rid of commas
		$KewordArr = split("\|", $Keywords);
		
		$retArr = array();
		foreach($KewordArr as $x){
			if(!empty($x))
				$retArr[] = strtolower($x);
		}
		return $retArr;
	}
	
	// Pass in a string like... " Banana Apple Peach Fruit" and it will return "apple banana fruit peach".  
	// Trims off white space, alphabetizes, and makes all lower case
	static function AlphabetizeKeywordSting($Keywords){
	
		$KewordArr = WebUtil::GetKeywordArr($Keywords);
		sort($KewordArr);
		
		$retStr = "";
		
	
		foreach($KewordArr as $thisKeyword){
			$retStr .= $thisKeyword . " ";
		}
		
		return trim($retStr);
	}
	
	
	// Removed dashes, spaces, and preceding "1"
	// If there are more than 10 digits (after removing the 1 prefix)... it will only return the first 10.
	static function FilterPhoneNumber($WholePhone){
	
		$FilteredPhone = preg_replace("/[^\d]/", "", $WholePhone);
		
		if(substr($FilteredPhone , 0, 1) == "1")
			$FilteredPhone = substr($FilteredPhone , 1);
			
		if(strlen($FilteredPhone) > 10)
			return substr($FilteredPhone , 0, 10);
		else
			return $FilteredPhone;
	
	
	}


	// Will remove any characters that are not a digit
	// Will only return the first 5 digits of the zip code.
	// In case they enter 91311-2342  it will return 91311
	// If they forget to enter a zip code... this function will just return 11111, which is better than nothing
	static function FilterUnitedStatesZipCode($zip){
	
		$zip = preg_replace("/[^\d]/", "", $zip);
		$zip = trim($zip);
		if(strlen($zip > 5))
			$zip = substr($zip, 0, 5);
		if(empty($zip))
			$zip = 11111;
		return $zip;
	}
		

	// Make sure that U.S. state codes are 2 letters and upper case
	static function CapitilizeUnitedState($StateName){
		
		$StateName = strtoupper($StateName);
		if(strlen($StateName) > 2)
			$StateName = substr($StateName, 0, 2);
		return $StateName;
	}
	
	
	// Returns a String with a pipe symbol separating each array value
	static function getPipeDelimatedListFromArray($ListArr){
		$retStr = "";
		foreach($ListArr as $ThisID){
			$retStr .= $ThisID . "|";
		}
		return $retStr;
	}
	
	
	private static $formSecurityCode;
	
	// This good method to prevent Cross-Site-Forgery-Request attacks.
	// Set's a unique security code in the users session and returns that code.  
	// You should put that code into a hidden input in an HTML format.
	// Call check checkFormSecurityCode() after the form is sumbitted, before saving to the Database.
	static function getFormSecurityCode(){

		// Only generate 1 unique form security code per script execution. 
		// Otherwise if it is called for a whole bunch of links in a loop it could set tons of session variables.  
		if(empty(self::$formSecurityCode))
			self::$formSecurityCode = md5(time() . uniqid() . Constants::getGeneralSecuritySalt());
		else
			return self::$formSecurityCode;
		
		// add to an array stored in the Session.
		$codesArr = WebUtil::GetSessionVar("FormSecurityCodes", array());
		
		$codesArr[] = self::$formSecurityCode;
	
		WebUtil::SetSessionVar("FormSecurityCodes", $codesArr);

		return self::$formSecurityCode;
	}
	
	// Looks in the user's $_REQUEST vars looking for variable called "form_sc"
	// If that variable is not found, an erorr message will be written.  
	// Then it makes sure that the user has a session variable set with a matching code or it will fail.
	static function checkFormSecurityCode(){
		
		$securityCodeInForm = WebUtil::GetInput("form_sc", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
		if(empty($securityCodeInForm))
			throw new ExceptionPermissionDenied("Error in method checkFormSecurityCode. It was not found within the Request.");
		
		$codesArr = WebUtil::GetSessionVar("FormSecurityCodes", array());
		
		if(!in_array($securityCodeInForm, $codesArr))
			throw new ExceptionPermissionDenied("The action you tried to perform could not be completed. Your browser window may have been sitting idle for too long.");
			
		
		// Don't let a users session file grow out of control.  
		// At the same time we want to give users the ability to do stuff in multiple windows and use Security Codes from within Iframes.
		if(sizeof($codesArr) > 70){
			$numToShift = sizeof($codesArr) - 70;
			for($i=0; $i<$numToShift; $i++)
				array_shift($codesArr);
				
			WebUtil::SetSessionVar("FormSecurityCodes", $codesArr);
		}
	}
	
	static function htmlOutput($str){
		
		// Read more at... http://ha.ckers.org/blog/20070327/htmlspecialchars-strikes-again/
		
		return htmlentities($str, ENT_QUOTES, 'ISO-8859-1');
	}
	
	// This filter is needed if you are going to put the data in an XML file.
	static function converMSwordBadCharacters($string){

		$search = array(chr(145), chr(146), chr(147), chr(148), chr(151));      
		$replace = array("'", "'", '"', '"', '-');
		return str_replace($search, $replace, $string);
	}
	
	// Removed 1 character from the end of the string (if it is not empty).
	static function chopChar($str){

		if(!empty($str))
			$str = substr($str, 0, -1);
		return $str;
	}
	
	// I was having some bugs after upgrading to PHP 5.
	// Mainly the issue seems to be a bug in htmlspecialchars dealing with special characters, or UTF-8 in Artwork XML files.
	// For example, no matter what I try ... htmlspecialchars("©", ENT_COMPAT, "ISO8859-15")   would output something like Â©  with the A in front???
	// So for now I am just using this on Artwork XML encoding.
	static function htmlSpecialShort($text){
		
		return str_replace(array("&", ">", "<", "\"", ), array("&amp;", "&gt;", "&lt;", "&quot;"), $text); 

	}
	
	
	// This will make sure that the Destination URL is being redirected to the fully qualified Domain name URL 
	// This is important for security reasons... based on RFC2616
	// It will figure out the destination URL based upon the Domain ID in the URL.
	static function getFullyQualifiedDestinationURL($destinationURL, $httpsMode = false){

		if(!is_bool($httpsMode))
			throw new Exception("Problem with HTTPS mode parameter");
		
		// Filter out possible Domains names in the Destination URL so we can do a more secure redirect with a fully qualified domain name. 
		// This may be a redundant search and replace.
		$destinationURL = preg_replace('@^(http(s)?:)?//[^/]+@i', "", $destinationURL);
	
		// In case they are linking to a root page like "/postcards.com" ... take out the first forward slash.
		$destinationURL = preg_replace('@^/+@', "", $destinationURL);
		
		if($httpsMode)
			$protocol =  "https";
		else 
			$protocol =  "http";
		
		$fullyQualifiedDomainURL = $protocol . "://".strtolower(Domain::getWebsiteURLforDomainID(Domain::getDomainIDfromURL()))."/";
		
		return WebUtil::FilterURL(($fullyQualifiedDomainURL . $destinationURL), true, 301);
	}
	
	
	// Use this function if you want to de-cluster an a list.  
	// For example, if you have a have a list of email addresses... you want to avoid having 2 Yahoo Email addresses next to each other.
	// You must pass in 2 arrays... The first array is the "control"... what you want to sort based upon.
	// The second array must have exactly the same elements (a parallel array).  It will use the control array to shuffle the positions of the second array (which can be multi dimensional.
	// This function will return the second array.
	// For the control array... strip off any prefix.  The clustering depends on the first characters within the string of each array element.  
	// For example... if you want to de-cluster an array of email addresses... the Control Array should be just the domain names (after the @ symbol)... The data array should be parallel, with the full email addresses.
	// Both the control array and the Data array must have numberical indexes (not a hash).
	static function arrayDecluster(array $controlArray, array $dataArray ){
		
		if(sizeof($controlArray) != sizeof($dataArray))
			throw new Exception("The control array and the data array must have identical lengths.");
			
		if(empty($controlArray))
			return $dataArray;
			
		$originalControlArray = unserialize(serialize($controlArray));
		
		// If we had a list of 100...
		// On the first iteration within the While() loop... the newSlotPosition would be 0,16,32,48,64,80,96 ... within each iteration of the For() loop
		// On the next iteration of the While () loop... the newSlotPosition would try to set a value an increments of 8, such as 0,8,16,24,32,etc.... within each iteration of the For() loop...
		// ... however half of the spots would be taken up (0,16,32,48,etc)... so in effect the second iteration of the while() loop would end up inserting at 8,24,40,56,etc.
		// The spacing itveral would then get cut in half again... down to 4(... then it would get cut in half again (to 2).
		// On the last iteration of the While() loop the spacing interval would be 1... so it will try to fill up every single position... within each iteration of the For() loop.
		
		$listLength = sizeof($controlArray);

		// Make lower case
		for($i=0; $i<sizeof($controlArray); $i++){
			$controlArray[$i] = strtolower($controlArray[$i]);
		}
		
		// Randomizing the array gives an extra bit of dispersement when we are trying to sort the array with highest clusters in the beginning.
		shuffle($controlArray);
			
		// The key is the value entry we want to sort, the value of the array if the number of times that it occurs.
		// This arsort() will put unique entries with the highest counts at the begining.
		$clusterCountsArr = array_count_values($controlArray);
		$clusterCountsArrCopy = array_count_values($controlArray);
		arsort($clusterCountsArr);
		
		// We want to sort the Input array with the highest clusters at the beginning of the array.
		$tempArr = array();
		foreach($clusterCountsArr as $thisUniqueInputName => $clusterCount){
			
			// Don't double add stuff to our temp array.  This uniqueInputName may have already been added to the tempArray because it had a matching cluster count to a previous array entry.
			if(in_array($thisUniqueInputName, $tempArr))
				continue;
			
			// We want to use the random order of input array entries (but only if they have the same cluster counts).
			$uniqueInputNamesWithSameClusterCount = array();
			foreach($clusterCountsArrCopy as $uniqueInputNameCopy => $clusterCountCopy){
				if($clusterCountCopy == $clusterCount)
					$uniqueInputNamesWithSameClusterCount[] = $uniqueInputNameCopy;
			}
			
			// Only add to the temp array if there is an array entry with a cluster count which mathes the current entry we are looping over.
			for($i=0; $i<sizeof($controlArray); $i++){
				if(in_array($controlArray[$i], $uniqueInputNamesWithSameClusterCount)){
					$tempArr[] = $controlArray[$i];
				}
			}
		}
		$controlArray = $tempArr;
		
		$sortedArray = array();
		
		// The spacing defaults to the square root of the size of the total length
		// That should give good breathing room, regardless of array size.
		// Make sure to do a ceil because we need the spacing to always be greater than or equal to 1.
		// We want the spacing interval to be an even number so that we can keep cutting in half... leaving equal gaps.
		$spacingInterval = ceil(sqrt($listLength));
		
		// Snap up to intervals which are exponents of 2 to maximize to symetry between spacing.
		// 2, 4, 8, 16, 32, etc.
		for($exponentOf2 = 1; $exponentOf2 < 20; $exponentOf2++){
			
			// If the array only has 1 or 2 elements then we can't space things out.
			if($spacingInterval < 1){
				$spacingInterval = 1;
				break;
			}
			
			if($spacingInterval < pow(2, $exponentOf2)){
				$spacingInterval = intval(round(pow(2, $exponentOf2)));
				break;
			}
		}
		
		$spotsFilledInSortedArr = 0;
		
		while(true){
			
			for($i=0; $i<$listLength; $i++){
		
				$newSlotPosition = intval($spacingInterval * $i);
				
				// Don't let the new slot position go out of bounds.
				if($newSlotPosition > ($listLength - 1)){
					break;
				}
				
				// Make sure that the spot hasn't been occupied yet.
				if(!array_key_exists($newSlotPosition, $sortedArray)){
					$sortedArray[$newSlotPosition] = $controlArray[$spotsFilledInSortedArr];
					$spotsFilledInSortedArr++;
				}
			}
			
			// Once we have filled up every spot, then we can break out of the infinite loop.
			if($spotsFilledInSortedArr == $listLength){
				
				// This shouldn't happen... just be safe, in case the input array is weird, like multi-dimentional, etc.
				if(sizeof($sortedArray) != sizeof($controlArray))
					throw new Exception("An error happened while trying to de-cluster.  The final array does not match the size of the input array.");
				
				break;
			}
			
			// By the time the spacing interval has reached 1, the For() loop should have checked every spot.
			if($spacingInterval == 1 && $spotsFilledInSortedArr != $listLength)
				throw new Exception("An error happened while trying to de-cluster.  All spaces were not filled for some reason.  Original Length: $listLength Spots Filled: $spotsFilledInSortedArr");
				
			// Keep decreasing the spacing interval by half until we get down to 1.
			// That will keep maximum padding between the initially sorted elements.
			$spacingInterval = round($spacingInterval / 2);
			
		}
				
		// The indexes (integers) need to be sorted to reflect the new positions within the array.
		// foreach loops and the array_reverse function do not use the sequence of organized by integers that we just created.
		ksort($sortedArray);
			
		// It is better to reverse the array.  More clumps are likely to occur near the beggining.
		// If we are trying to disperse a collection of elements... it is better to ramp up the pressure near the end to ensure best throughput in the beginning.
		$sortedArray = array_reverse($sortedArray);
				
		// Record the new positions of each array element.
		$newIndexPositions = array();
		
		for($i=0; $i<sizeof($originalControlArray); $i++){
			
			// Locate the first Occurence of the control element within the sorted array.
			for($j=0; $j<sizeof($sortedArray); $j++){
	
				if($originalControlArray[$i] == $sortedArray[$j] && !in_array($j, $newIndexPositions)){	
					$newIndexPositions[$i] = $j; 
					break;
				}
			}
		}
			
		// Now that we know how the positions shifted, we have to map those position changes to the Data Array.
		$dataArrayForReturn = array();
		
		for($i=0; $i<sizeof($newIndexPositions); $i++){
			$dataArrayForReturn[$newIndexPositions[$i]] = $dataArray[$i];
		}
		
		// Do a Key sort to make sure that a foreach() loop will run through the elements in sequently (just how we indexed them above).
		ksort($dataArrayForReturn);
			
		if(sizeof($dataArrayForReturn) != sizeof($dataArray))
			throw new Exception("An error happened trying to de-cluster the array. The return data array doesn't match the size of the original data array.");
						
		return $dataArrayForReturn;	
	}
	
	
	
	static function print404Error(){
	SessionHandler::checkDosAttack();
		header("HTTP/1.0 404 Not Found");
exit('<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
<html><head>
<title>404 Not Found</title>
</head><body>
<h1>Not Found</h1>
<p>The requested URL was not found on this server.</p>
</body></html>');

	}
	static function print410Error(){
		header("HTTP/1.0 410 Gone");
exit('<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
<html><head>
<title>410 Gone</title>
</head><body>
<h1>The webpage no longer exists</h1>
<p>The requested URL has been removed from this server.</p>
</body></html>');

	}

}

	
	
?>
