<?php
//
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2003 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.02 of the PHP license,      |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Author: Chuck Hagenbuch <chuck@horde.org>                            |
// +----------------------------------------------------------------------+
//
// $Id: Mail.php,v 1.23 2011/02/23 23:18:21 brian_dot Exp $

//require_once 'classes_3rdparty/Pear/PEAR.php';

/**
 * PEAR's Mail:: interface. Defines the interface for implementing
 * mailers under the PEAR hierarchy, and provides supporting functions
 * useful in multiple mailer backends.
 *
 * @access public
 * @version $Revision: 1.23 $
 * @package Mail
 */


class Mail
{
    /**
     * Line terminator used for separating header lines.
     * @var string
     */
    public $sep = "\r\n";

    /**
     * Provides an interface for generating Mail:: objects of various
     * types
     *
     * @param string $driver The kind of Mail:: object to instantiate.
     * @param array  $params The parameters to pass to the Mail:: object.
     * @return object Mail a instance of the driver class or if fails a PEAR Error
     * @access public
     */
    function &factory($driver, $params = array())
    {
        $driver = strtolower($driver);
        $driverPath = '../classes_3rdparty/Pear/Mail/' . $driver . '.php';
        @include_once($driverPath);
        $class = 'Mail_' . $driver;
        if (class_exists($class)) {
            $mailer = new $class($params);
            return $mailer;
        } else {
            return PEAR::raiseError('Unable to find class for driver ' . $driver);
        }
    }

    /**
     * Implements Mail::send() function using php's built-in mail()
     * command.
     *
     * @param mixed $recipients Either a comma-seperated list of recipients
     *              (RFC822 compliant), or an array of recipients,
     *              each RFC822 valid. This may contain recipients not
     *              specified in the headers, for Bcc:, resending
     *              messages, etc.
     *
     * @param array $headers The array of headers to send with the mail, in an
     *              associative array, where the array key is the
     *              header name (ie, 'Subject'), and the array value
     *              is the header value (ie, 'test'). The header
     *              produced from those values would be 'Subject:
     *              test'.
     *
     * @param string $body The full text of the message body, including any
     *               Mime parts, etc.
     *
     * @return mixed Returns true on success, or a PEAR_Error
     *               containing a descriptive error message on
     *               failure.
     * @access public
     * @deprecated use Mail_mail::send instead
     */
    function send($recipients, $headers, $body, $additionalSendMailParameters = "")
    {
        // if we're passed an array of recipients, implode it.
        if(is_array($recipients)){
            $recipientsStr = implode(', ', $recipients);
            $recipientsArr = $recipients;
        }
        else {
			$recipientsStr = $recipients;
            $recipientsArr = split(",", $recipients);
        }
			
			
		// Extract the To Name/Email from the "From" header which may look something like "Mickey Mouse <mickey@disney.com>"
		$fromEmailPartsHash = WebUtil::getEmailAndNameFromEmailHeader($headers["From"]);
		$fromEmailAddress = $fromEmailPartsHash["email"];
		
    	if(array_key_exists("From", $headers)){
    		$fromEmailParts = split("@", $fromEmailAddress);
    		$fromEmailDomain = $fromEmailParts[1];
    		$emailDomainKey = Domain::getDomainKey($fromEmailDomain);
    	}
    	else {
    		$emailDomainKey = Domain::getDomainKeyFromURL();	
    	}
            
        // This block is the PEAR default.
        // If this domain is supposed to send email from the current server... then use PHP's internal mail() command (defined in PHP.ini)
        if(!Domain::isDomainRunThroughProxy(Domain::getDomainID($emailDomainKey))) {

    		// get the Subject out of the headers array so that we can
	    	// pass it as a seperate argument to mail().
    	  	$subject = '';
	        if (isset($headers['Subject'])) {
	            $subject = $headers['Subject'];
	            unset($headers['Subject']);
	        }
	        $this->_sanitizeHeaders($headers);
	
	        // flatten the headers out.
	        list(,$text_headers) = Mail::prepareHeaders($headers);
	        
	        return mail($recipientsStr, $subject, $body, $text_headers, $additionalSendMailParameters);
    	}

    	
		// ------ Since we are not mailing from our local server... prepare to send this email out through an external SMTP Server ------
		
    	$phpMailObj = new PHPMailer();

    	$domainId = Domain::getDomainID($emailDomainKey);
		$domainEmailsObj = new DomainEmails($domainId);

		$email_TYPE = $domainEmailsObj->getEmailTypeByEmailAddress($fromEmailAddress);

		$emailLoginLost = $domainEmailsObj->getHostOfType($email_TYPE);
		
		if(empty($emailLoginLost)){
			WebUtil::WebmasterError("The Email Host has not been defined for: $fromEmailAddress : $email_TYPE");
			throw new Exception("The Email Host has not been defined for: $fromEmailAddress : $email_TYPE");
		}
		
		$phpMailObj->Host = $emailLoginLost;  
    
		$phpMailObj->SMTPAuth = true;       
			
		if($phpMailObj->SMTPAuth){
			$phpMailObj->Username = $domainEmailsObj->getUserOfType($email_TYPE);      
			$phpMailObj->Password = $domainEmailsObj->getPassOfType($email_TYPE);	
		}
		
		$headers["To"] = $recipientsStr;
		
		// This is for the "Return Envelope"... not the "From Address"
		$phpMailObj->From = $domainEmailsObj->getEmailAddressOfType($email_TYPE);
		
    	foreach($recipientsArr as $thisRecipient) {
		
			$recipientEmailPartsHash   = WebUtil::getEmailAndNameFromEmailHeader($thisRecipient);
			$recipientEmailAddress     = $recipientEmailPartsHash["email"];
			$recipientEmailAddressName = $recipientEmailPartsHash["name"];

			$phpMailObj->AddAddress($recipientEmailAddress, $recipientEmailAddressName);				
		}
		
		// This is setting the RAW body... which may also include the Base 64 attachments, etc.  
		// By the time we are in this function, the Mime will have built the body.
		$phpMailObj->Body = $body;
    			
        list(,$text_headers) = Mail::prepareHeaders($headers);
 
		if($phpMailObj->SendEmailWithRawBody($text_headers)) 
			return true;
		else 
			return false;

    }
    
    /**
     * Adds a new Pop3 account to a Email Domain running our custom Proxy SMTP Server 
     */
     function addNewPop3Account($userName, $domainName, $password) {
    	
		$allowedChars = "abcdeflmnopqrstuwxyz012345678ABCDEFGLMNOPQRTSTUWXZ";
		$salt         = "*b[h+6]f,g%a)c@cb7#";	

		$domainName = strtolower($domainName);
		
		$randonString1 = "";
		for($s=0; $s<25; $s++) 
			$randonString1 .= substr($allowedChars,intval(rand()%50),1);
	
		$randonString2 = "";
		for($s=0; $s<25; $s++) 
			$randonString2 .= substr($allowedChars,intval(rand()%50),1);
			
		$randonString3 = "";
		for($s=0; $s<32; $s++) 
			$randonString3 .= substr($allowedChars,intval(rand()%50),1);
		
		$randonString4 = "";
		for($s=0; $s<32; $s++) 
			$randonString4 .= substr($allowedChars,intval(rand()%50),1);
		
		$randonString5 = "";
		for($s=0; $s<32; $s++) 
			$randonString5 .= substr($allowedChars,intval(rand()%50),1);
			
		$fromEmail = "mailmaster@" . $domainName;
		$ToEmail   = $userName . "@" . $domainName;
					
		if(!empty($password)) {
			
			$confirmString = md5($randonString1.$salt.$ToEmail);		
			$codedString = $randonString2 . "gjki" . $randonString1 . $randonString3 . "vk9e" . $confirmString . $randonString4 . "zb9f5". $password ."kxp7mj" . $randonString5;
			
			$phpMailObj = new PHPMailer();
					
			$phpMailObj->Host = "mail." . $domainName;  		
			$phpMailObj->From = $fromEmail;	
			$phpMailObj->AddAddress($ToEmail, "");
			$headers = "From: $fromEmail\nMIME-Version: 1.0\nTo: $ToEmail\nSubject: Welcome\n";
			
			if($phpMailObj->SmtpSend($headers,$codedString))
				return "OK";
			else 
				return "ERROR";
		}
		return "NOPASS";
    }
        
	function checkPop3Account($host, $username, $password) {
    	
		$phpMailObj = new PHPMailer();		
		return  $phpMailObj->CheckIfUserExists($host,$username,$password);
	 }

    /**
     * Sanitize an array of mail headers by removing any additional header
     * strings present in a legitimate header's value.  The goal of this
     * filter is to prevent mail injection attacks.
     *
     * @param array $headers The associative array of headers to sanitize.
     *
     * @access private
     */
    function _sanitizeHeaders(&$headers)
    {
        foreach ($headers as $key => $value) {
            $headers[$key] =
                preg_replace('=((<CR>|<LF>|0x0A/%0A|0x0D/%0D|\\n|\\r)\S).*=i',
                             null, $value);
        }
    }

    /**
     * Take an array of mail headers and return a string containing
     * text usable in sending a message.
     *
     * @param array $headers The array of headers to prepare, in an associative
     *              array, where the array key is the header name (ie,
     *              'Subject'), and the array value is the header
     *              value (ie, 'test'). The header produced from those
     *              values would be 'Subject: test'.
     *
     * @return mixed Returns false if it encounters a bad address,
     *               otherwise returns an array containing two
     *               elements: Any From: address found in the headers,
     *               and the plain text version of the headers.
     * @access private
     */
    function prepareHeaders($headers)
    {
        $lines = array();
		       
        $from = null;
        
        // Needed to avoid a compiler warning for unused variable??
        if(!empty($from))
        	$from = null;
    
        foreach ($headers as $key => $value) {
            if (strcasecmp($key, 'From') === 0) {
            	
            	// I am not really Sure why Pear went to all of this trouble. It looks like all this does is validate the From address.
            	// The ->mailbox was an undefined class member.
            	// Talk about over designing something !!!  Leave it to Pear for that.
            	/*
                include_once 'classes_3rdparty/Pear/Mail/RFC822.php';
                $parser = new Mail_RFC822();
                $addresses = $parser->parseAddressList($value, 'localhost', false);
                if (PEAR::isError($addresses)) {
                    return $addresses;
                }

                $from = $addresses[0]->mailbox . '@' . $addresses[0]->host;

                // Reject envelope From: addresses with spaces.
                if (strstr($from, ' ')) {
                    return false;
                }
				*/
            	
                $lines[] = $key . ': ' . $value;
				
            	
            } elseif (strcasecmp($key, 'Received') === 0) {
                $received = array();
                if (is_array($value)) {
                    foreach ($value as $line) {
                        $received[] = $key . ': ' . $line;
                    }
                }
                else {
                    $received[] = $key . ': ' . $value;
                }
                // Put Received: headers at the top.  Spam detectors often
                // flag messages with Received: headers after the Subject:
                // as spam.
                $lines = array_merge($received, $lines);
            } else {
                // If $value is an array (i.e., a list of addresses), convert
                // it to a comma-delimited string of its elements (addresses).
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }
                $lines[] = $key . ': ' . $value;
            }
        }

        return array($from, join($this->sep, $lines));
    }

    /**
     * Take a set of recipients and parse them, returning an array of
     * bare addresses (forward paths) that can be passed to sendmail
     * or an smtp server with the rcpt to: command.
     *
     * @param mixed Either a comma-seperated list of recipients
     *              (RFC822 compliant), or an array of recipients,
     *              each RFC822 valid.
     *
     * @return mixed An array of forward paths (bare addresses) or a PEAR_Error
     *               object if the address list could not be parsed.
     * @access private
     */
    function parseRecipients($recipients)
    {
        include_once 'classes_3rdparty/Pear/Mail/RFC822.php';

        // if we're passed an array, assume addresses are valid and
        // implode them before parsing.
        if (is_array($recipients)) {
            $recipients = implode(', ', $recipients);
        }

        // Parse recipients, leaving out all personal info. This is
        // for smtp recipients, etc. All relevant personal information
        // should already be in the headers.
        $addresses = Mail_RFC822::parseAddressList($recipients, 'localhost', false);

        // If parseAddressList() returned a PEAR_Error object, just return it.
        if (PEAR::isError($addresses)) {
            return $addresses;
        }

        $recipients = array();
        if (is_array($addresses)) {
            foreach ($addresses as $ob) {
                $recipients[] = $ob->mailbox . '@' . $ob->host;
            }
        }

        return $recipients;
    }

}


?>
