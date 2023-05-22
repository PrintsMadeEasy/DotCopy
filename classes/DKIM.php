<?php

/*
How to add DKIM to new domains:

OpenSSL must be installed, it's included in Apache.

Create public and private key, one way to do it (Yahoo ONLY accepts 1024 keys):

CMD:
cd C:\Program Files\Apache Software Foundation\Apache2.2\bin
openssl genrsa -out privdomainkey.priv 1024
openssl rsa -in privdomainkey.priv -out publickey.pub -pubout -outform PEM

Then copy & paste private keys (privdomainkey.priv) to the new $this->privateKey including the BEGIN and END lines.


The public key (publickey.pub) must be published on DNS:

Example: publickey.pub

-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCybYjF5PLz19V
jS1+LzkDdlraNa8vKf6zN5pQ8xTyUt6TJ2+Hf4FDAcJ63tqqjr/
FIO/RE2iHQTnRVoVbXS0RejPEOcX4Lj513OUUWVmjEODQiBVbIC
qCZlDdtUqeI8CJaN8W9OIgHTg6/SpZLKGx77Jvmd2VHZqfLNWvB
Qs6XqwIDAQAB
-----END PUBLIC KEY-----
  
Create a new string like this:

k=rsa; p=MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCybYjF5PLz19VjS1+LzkDdlraNa8vKf6zN5pQ8xTyUt6TJ2+Hf4FDAcJ63tqqjr/FIO/RE2iHQTnRVoVbXS0RejPEOcX4Lj513OUUWVmjEODQiBVbICqCZlDdtUqeI8CJaN8W9OIgHTg6/SpZLKGx77Jvmd2VHZqfLNWvBQs6XqwIDAQAB

Add the string as TXT to the domains DNS (HOSTING COMPANY):

Name = "Selector"._domainkey -> pme._domainkey
Type = select TXT
Data = k=rsa; p=MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCybYjF5PLz19VjS1+LzkDdlraNa8vKf6zN5pQ8xTyUt6TJ2+Hf4FDAcJ63tqqjr/FIO/RE2iHQTnRVoVbXS0RejPEOcX4Lj513OUUWVmjEODQiBVbICqCZlDdtUqeI8CJaN8W9OIgHTg6/SpZLKGx77Jvmd2VHZqfLNWvBQs6XqwIDAQAB

It may be possible to use the same public and private key for all domains, but having different keys may be the better solution.

To test DKIM send an email to a Yahoo.com email account and check the headers, look for "dkim=pass (ok)".
Or send an email to a DKIM reflector email, it returns the email to the sender address with a DKIM report attached: autorespond+dkim@dk.elandsys.com
http://testing.dkim.org/reflector.html // Offline Nov 2010


DKIM DNS TXT entry check tool: http://dkimcore.org/c/keycheck
  
-----> OpenSSL MUST be activated in PHP.ini <-----

This Class adds DKIM to an email message. Example:

$dkimObj = new DKIM(1);
$headers = $headers . $dkimObj->addDkimToHeaders($headers,$subject,$body);

$headers=str_replace("\r\n","\n",$headers) ; 
$headers=str_replace("\"","",$headers) ; 

mail($to,$subject,$body,$headers,"-f $sender");

*/

class DKIM {
		
	private $privateKey;	
	private $dkimDomain;
	private $dkimSelector;	
	private $dkimDefaultIdentity;
	
	function __construct($domainId) {
		
		// Depending on $domainId load init values. This part can be moved to a central constants file
	
		if($domainId == 1) {
		
		$this->dkimDomain = "nuesch-balgach.com" ; 
		$this->dkimDefaultIdentity = "@nuesch-balgach.com"; // Optional: Default identity 
		$this->dkimSelector = "test"; 
		
		// Don't move key data with tab!
		$this->privateKey = 
"-----BEGIN RSA PRIVATE KEY-----
MIICXQIBAAKBgQCybYjF5PLz19VjS1+LzkDdlraNa8vKf6zN5pQ8xTyUt6TJ2+Hf
4FDAcJ63tqqjr/FIO/RE2iHQTnRVoVbXS0RejPEOcX4Lj513OUUWVmjEODQiBVbI
CqCZlDdtUqeI8CJaN8W9OIgHTg6/SpZLKGx77Jvmd2VHZqfLNWvBQs6XqwIDAQAB
AoGAfQ4SN4kg0tDqSV6xh742blhMeFAeFD5p8iHysakXrbAMukH3TL7eOhJ025QW
gwU0mgkTShKMcoAaP04GHH0vW91A3EJ9wi2PLTzBoHEI0JlVISQOTeFPibKhk5H+
SOsCzFELt72CGXj0z5X+UAetHacKAhFdZ59+dC33SbRdEMkCQQDjv9H5roZCiPcD
WQ6wBck8a3Q1ltrDvs0OH07Tt2bD8gRQFLORnBdCjpMPpL9kIr7emu4Qk/r4Hg4s
oI53LyPHAkEAyI+DCouYQRWT3j+lQS3UXXVG5K5BEYxne98GVnzQ7EI39G6X9ddI
cughEWUXm3wenuJwYd8TbxEsGl6Tcc3k/QJBAL97npIbfzxfpbcF4Ih0RO5stcb8
r6/WMteF0SPGVju2tpOR5CwvnYrTDqgfbt9FK09D2ZbMpDyKIIa68y0X0C8CQQDB
Z9uHbMx7XvKEbT3QWACly3V9CylGYe5dPtoexyi13LmW5pt2AJAl9wIEg0c7snrY
3yZeyz8zaQzttOxc35+FAkA0vHxmop0LjdO8tZYLgmesZ8U2BebNpeoIk9MfauEQ
wfWgAX/xlqCXxxP/nH03ZqCfhqIuH44DhrljuomshL4X
-----END RSA PRIVATE KEY-----";
			
		}		
	}
	
	private function quotedPrintable($txt) {
	  
	    $line="";
	    for ($i=0;$i<strlen($txt);$i++) {
			$ord=ord($txt[$i]) ;
	        if ( ((0x21 <= $ord) && ($ord <= 0x3A))
				|| $ord == 0x3C
				|| ((0x3E <= $ord) && ($ord <= 0x7E)) )
	            $line.=$txt[$i];
	        else
	            $line.="=".sprintf("%02X",$ord);
	    }
	    return $line;
	}
	
	private function signDkim($s) {
		$signature = "";
		if (openssl_sign($s, $signature, $this->privateKey ))
			return base64_encode($signature);
		else
			die("Cannot sign") ;
	}
	
	// In case we like to switch to "simple"
	private function simpleHeaderCanonicalization($s) {
		return $s ;
	}
	
	private function relaxedHeaderCanonicalization($s) {
		// First unfold lines
		$s = preg_replace("/\r\n\s+/"," ",$s) ;
		// Explode headers & lowercase the heading
		$lines = explode("\r\n",$s) ;
		foreach ($lines as $key=>$line) {
			list($heading,$value)=explode(":",$line,2) ;
			$heading=strtolower($heading) ;
			$value=preg_replace("/\s+/"," ",$value) ; 
			$lines[$key]=$heading.":".trim($value) ; 
		}
		// Implode it again
		$s = implode("\r\n",$lines) ;
		return $s ;
	}
	
	private function simpleBodyCanonicalization($body) {
		if ($body == '') return "\r\n" ;
		// Just in case the body comes from Windows, replace all \r\n by the Unix \n
		$body=str_replace("\r\n","\n",$body) ;
		// Replace all \n by \r\n
		$body=str_replace("\n","\r\n",$body) ;
		while (substr($body,strlen($body)-4,4) == "\r\n\r\n")
			$body=substr($body,0,strlen($body)-2) ;
		return $body ;
	}
	
	public function addDkimToHeaders($headers_line,$subject,$body) {
			
		$algorithm		= "rsa-sha1"; // Signature & hash algorithms
		$canonicalization="relaxed/simple"; // Canonicalization of header/body
		$queryMethod	= "dns/txt"; // Query method
		$timestamp		= time() ; 
		$subject_header	= "Subject: $subject" ;
	
		$headers = explode("\r\n",$headers_line) ;
		foreach($headers as $header)
			if (strpos($header,'From:') === 0)
				$from_header=$header ;
			elseif (strpos($header,'To:') === 0)
				$to_header=$header ;
				
		$from	= str_replace('|','=7C',$this->quotedPrintable($from_header)) ;
		$to		= str_replace('|','=7C',$this->quotedPrintable($to_header)) ;
		$subject= str_replace('|','=7C',$this->quotedPrintable($subject_header)) ; 
		
		$body	= $this->simpleBodyCanonicalization($body) ;
		
		$bodyLength	= strlen($body) ; // Length of body (in case MTA adds something afterwards)
		$binaryHash = base64_encode(pack("H*", sha1($body))) ; // Base64 of packed binary SHA-1 hash of body
		$identity =($this->dkimDefaultIdentity  == '')? '' : " i=$this->dkimDefaultIdentity ;" ;
		
		$dkim = "DKIM-Signature: v=1; a=$algorithm; q=$queryMethod; l=$bodyLength; s=$this->dkimSelector;\r\n".
			"\tt=$timestamp; c=$canonicalization;\r\n".
			"\th=From:To:Subject;\r\n".
			"\td=$this->dkimDomain;$identity\r\n".
			"\tz=$from\r\n".
			"\t|$to\r\n".
			"\t|$subject;\r\n".
			"\tbh=$binaryHash;\r\n".
			"\tb=";
		
		$to_be_signed = $this->relaxedHeaderCanonicalization("$from_header\r\n$to_header\r\n$subject_header\r\n$dkim") ;
		$signedDkim   = $this->signDkim($to_be_signed) ;
		
		return "X-DKIM: dkim.$this->dkimDomain\r\n".$dkim.$signedDkim."\r\n" ;
	}
}

?>