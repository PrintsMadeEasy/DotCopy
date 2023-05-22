<?php


function exceptionHandler(Exception $exception){
	
	try{
		// Just in case a session hasn't been initialized yet... It doesn't hurt to call it twice.
		WebUtil::InitializeSession();
		
		// If someone is trying to visit a Page that requires a Single Domain ID... this type of an execption may be thrown.
		// In that case print out a page that allows the user to select from a list of domains.
		if(get_class($exception) == "ExceptionSingleDomainRequired"){
			
			header("Location: " . WebUtil::FilterURL("./ad_selectTopDomain.php?retURL=" . urldecode($_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING'])));
			exit;
		}
		else if(get_class($exception) == "ExceptionPermissionDenied"){
			
			// It the the program doesn't catch the Exception, then we will print out an error.
			// Figure out if we should display a Front-end Error or an Administrative Error
			if(Domain::isUrlForTheFrontEnd())
				WebUtil::PrintError($exception->getMessage());
			else
				WebUtil::PrintAdminError($exception->getMessage());
		}
		else if(get_class($exception) == "Exception"){
			
			$passiveAuthObj = Authenticate::getPassiveAuthObject();
			
			// Log the Exception into the database.
			// Use the MD5 of the File, and Line Number where the exception was thrown.
			// That would keep someone from filling up our inbox if we have a cron mail us periodically of what errors happened (with a quanity of errors).
			// Don't include the "Trace" in the MD5 because a hacker may try thousands of different arguments with a brute force attack.
			$exceptionSignature = md5($exception->getFile() . $exception->getLine());
			
			
			$userIDofException = 0;
			if($passiveAuthObj->CheckIfLoggedIn())
				$userIDofException = $passiveAuthObj->GetUserID();
	
			$insertArr["EventSignature"] = $exceptionSignature;
			$insertArr["IPaddress"] = WebUtil::getRemoteAddressIp();
			$insertArr["Referer"] = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : "";
			$insertArr["UserAgent"] =  isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : "";
			$insertArr["Date"] = time();
			$insertArr["DomainID"] = Domain::getDomainIDfromURL();
			$insertArr["URL"] = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : "";
			$insertArr["EventObjSerialized"] = var_export($exception, true);
			$insertArr["EventObjTrace"] = $exception->__toString();
			$insertArr["UserID"] = $userIDofException;
			
			
			$dbCmd = new DbCmd();
			$dbCmd->InsertQuery("exceptionlog", $insertArr);
				
			
			// If we are logged in as the Webmaster. (One of the permitted User IDs).  Then we can print out the error message.
			// Otherwise, we don't want to give any clues to a hacker.  
			$webmasterUserIDs = array(2, 52204);
			
		
			
			$passiveAuthObj = Authenticate::getPassiveAuthObject();
			if($passiveAuthObj->CheckIfLoggedIn() && in_array($passiveAuthObj->GetUserID(), $webmasterUserIDs)){
					
				// I created a custome "zse" protocol on my windows machine (like http or https). 
				// The protocol launches an EXE program which parsed the URL and opens the file in my IDE.
				// Here is the code to add a custom protocol in windows from the Command Shell.
				/*
				 	reg add HKCR\zse /ve /d "URL:ZSE Protocol" 
					reg add HKCR\zse /v "URL Protocol" 
					reg add HKCR\zse\Shell\Open\Command /ve /d "C:\EclipseFileLauncher.exe \\\"%1\\\""
				 */
				
				
				
				// This is a consol application written in C#
				/*
					using System;
					namespace EclipseFileLauncher
					{
						class Class1
						{
							[STAThread]
							static int Main(string[] args)
							{
								// Test if input arguments were supplied:
								if (args.Length == 0)  
								{
									Console.WriteLine("A URL was not supplied to open a file within Zend Studio for Eclipse."); 
									Console.WriteLine("Usage: EclipseFileLauncher zse://myFile.php"); 
									return 1; 
								}
					
								string urlToOpen = args[0].ToString();
								//Console.WriteLine("The URL we are going to open is {0}", urlToOpen); 
					
								urlToOpen = urlToOpen.Replace("zse://", "C:\\");
								urlToOpen = urlToOpen.Replace("localhost/", "inet\\");
								urlToOpen = urlToOpen.Replace("/", "\\");
					
								//Console.WriteLine("Converted To: {0}", urlToOpen); 
								//Console.ReadLine();
					
								try
								{
									System.Diagnostics.Process.Start(urlToOpen);
								}
								catch
								{
									Console.WriteLine("The file can't be opened: {0}", urlToOpen); 
									Console.ReadLine();
								}
								return 0;
							}
						}
					}
				 */
				
				
				$fileLink = $exception->getFile();
				$fileLink = preg_replace("/c:\\\\/i", "zse://", $fileLink);
				$fileLink = preg_replace("/\\\\/", "/", $fileLink);
				
				print "<b>Exception:</b> " . $exception->getMessage() . "\n<br><br>\n";
				print "<b>File:</b> <a href='".$fileLink."'>" . $exception->getFile() . "</a>\n<br>\n";
				print "<b>Line #:</b> " . $exception->getLine() . "\n<br><br>\n";
				print "<b>Trace:</b><br>\n";
				
				
				$traceStringArr = split("#", $exception->getTraceAsString());
				foreach($traceStringArr as $thisTraceLine){
		
					$matches = array();
					if(preg_match("/(C:.*\.php)/i", $thisTraceLine, $matches)){
						
						$fileNameOfTrace = $matches[1];
						
						$fileLink = $fileNameOfTrace;
						$fileLink = preg_replace("/c:\\\\/i", "zse://", $fileLink);
						$fileLink = preg_replace("/\\\\/", "/", $fileLink);
						
						$thisTraceLine = preg_replace("/(C:.*\.php)/i", "<a href='$fileLink'>$fileNameOfTrace</a>", $thisTraceLine);
						print "#" . $thisTraceLine . "\n<br><br>\n";
					}
					else{
						print "#" . $thisTraceLine . "\n<br><br>\n";
					}
					
				}
				
				exit;
			}
			else{
				header("HTTP/1.0 400 Bad Request");
				exit;
			}
		}
		else{
			WebUtil::WebmasterError("There was an uncaught Exception: " . get_class($exception). " Message: " . $exception->getMessage() . " Trace: " . $exception->getTraceAsString());
			exit;
		}
	}
	catch(Exception $e){
		// Prevent a compiler warning
		if($e || !$e){
			header("HTTP/1.0 500 Internal Server Error");
			exit('<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
			<html><head>
			<title>500 Internal Server Error</title>
			</head><body>
			<h1>500 Internal Server Error</h1>
			</body></html>');
		}
		//exit("An Exception Occured within the Exception handler: " . $e->getMessage());
	}

}


set_exception_handler('exceptionHandler');
