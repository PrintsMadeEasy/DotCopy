<?php


class VCDKIM {
		
	private $privateKey;	
	private $dkimDomain;
	private $dkimSelector;	
	private $dkimDefaultIdentity;
	
	function __construct($domain,$selector) {
		
	
		$this->dkimDomain = $domain ; 
		$this->dkimDefaultIdentity = "@".$domain; // Optional: Default identity 
		$this->dkimSelector = $selector; 
		
		$selector = strtoupper($selector);	



// Don't move key data with tab!
		if($selector=="B24")
		{
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

		if($selector=="BEACH")
		{
		$this->privateKey = 
"-----BEGIN RSA PRIVATE KEY-----
MIICXgIBAAKBgQC1e9yZlcpd/pUDbhm0SiIy0jGgdPsIvwaEb6E4KKoeZub6pV1X
uZI+O16Z0ZuhJI7pVFV3yVcyHhDZdjVcEfudsBPcAmo5YiyPwP+G0BI3ti9lXASE
Do3ZkC3+PJb6LhmbzbXSrb/oimYPk1tAR5hNigR+nI21DURTaoN/xrBIIQIDAQAB
AoGBAI5GZjD5n0aE+OlRfVE79QeGhWVXkB3RNBjLMsbGCmf/IAFLdpv5XU7wWD+a
dbmk4WzGsqJP883UiD0TUM23Q1uPl7Xw4p175TCEFdWjRZsq+tgo4h7BwLFE/0Rc
Dlt6r4arB0D6UgaA8cb4qtCI7yObY8kG3bIuNAlOxyflC69JAkEA4LFBGDpA6b36
m7nIlyafdkMCFy5Pc8nkgX8JjeeRWEPPZtGECmCHN+xmRaNIYn0OctgNr/y07yN9
4Tg9WSos/wJBAM7FXTdgUhYZtzDcQtsPdTVmYMJMlfJfpp8g6a8ZpHuS7JZWVzhw
d3XZ0UW8yRveIFCbYiHbhrC6PkSj7Ygu6t8CQGLAJG7Ec3EHSNQWI72igOTV8F5F
wS+PZLkxHv7Z7jwPmWCD5nc1E1iVsiEa8R4v/iClKebVtqN/QrywHe5JJfsCQQCr
YYrRy1Q+XTIpnWcMitNrX1/zq+bc7cr9Ohp2t5pNkonmUcoZTZ62X8PFOaS3JHVE
WoYL6hjJgpT576WBquGhAkEAsrBLRHkuzuB2E1yg/5mw7p4ylJNHB9N9NSEgIIgZ
nwt+6Hpsjv/znLRiLhFEnbIb5ZmF3i/RDK2xojrhmGvNQQ==
-----END RSA PRIVATE KEY-----";
		}

		if($selector=="WEST")
		{
		$this->privateKey = 
"-----BEGIN RSA PRIVATE KEY-----
MIICWwIBAAKBgQDNtA3hbnSClvXUay/6UeenawvVgp+aJJGkCvwdsAcqNOBJe+Er
PcwJEBHG7OD9OzccV+j1TpE70C22Roi2QiV/6hSLjkiy46hDxyGyqxn/y2hKZgF3
ICerwzdpRONtxLtOp0FmjtxgijgnToW/I5XDFxnjV/IcXXXpFeG3hH+pTwIDAQAB
AoGAJEsPY+XkIqJV70uWJHlNAQnvBZXNaRnopGPXxbkoGndH109HFCUMGdE/AbUL
oUJQX+zWymk5UK9TUWSyfE3BZbYU7jdvGrx8EKmUXicUQ6cAWhEhUkRYn/Ld2ZX5
F4s90iyDdOCIePtdao3farvIpIeyOh8bUHs8nDEuFsLmdrkCQQD1I8XvdeaC7HM3
TmmtFDpsmKmVeTd2BPLnUmYvSaaOhoD4TtP5J275Z1/5OCdHZVyMALcf3jBCrMWg
SbBIY2y1AkEA1tEEZnLuILAwKxyYX/sgtl99W8JsN3/QAY73tWOirI5v+zRXNOeh
wY7e8yMHCmh+zQNkM+JAC7TkuG1SgncEcwJBALt2KNaPPcDfGtivcSa3clo7gGva
76uj6zE0lQoSc3lIqHW6qmU9X6MAB6eo5ni1rckuftuy6QsD3nlOAK3KwoUCPwQy
EOPvWrdIuagd5tv5C6qEMu6X3YU3+dgN8siYKZU1Mvq1Cv79hytAnxoglQKfB9r5
NfvNb3LLFayEdhgWOwJAcCTrsmEbq9U/TG3RE38gX1D13d6x/KKK16frrQQZVZWv
gKBQhvS27/P/2hnrG1TEGZ61NHhu1VEmKDf4832HGA==
-----END RSA PRIVATE KEY-----";
		}

		if($selector=="EAGLE")
		{
		$this->privateKey = 
"-----BEGIN RSA PRIVATE KEY-----
MIICXAIBAAKBgQC5qEsBGKjeMwheMyva2G5RgsRsbfF7h2up9EVJwGZbRr/7SylI
uhGg2xcc8iDMn8o+MPcdB7krWvesN0wokFn/XDm+YgR9H1k0itlLkOyVR+qffmcn
1RJ2pB7b5ne8l8UcVnl6Jc7Vmi0PROPGEtFhrNBdGRU5Wil76OE7TrteuwIDAQAB
AoGBAKH8aXzKZESC6FEwepoWbqKl4vXsM18hd9mwrGe9/FC1eToriRjQaCMeJZt2
0xFWdeIvXNyyaWiflStokAVwdWQuZY1yMjM7DWH2qJ8SLhvQOpKyL5skAlZyjz88
nOj7dc0d2P1qTl38zIl0a8B5U9qpLxSeB7m+6lFr4IcQ62HRAkEA9o6bXTCEo5cp
e4SXd3ai7ZFDHRIkzbdYCNGSm6soT573tbsijNsNpWFx0k5sbRnxgDn6JUe7ncBo
Kd5Ir9DxIwJBAMDEltzXRvkoBT+gUkqDuPTEtyg8Qu5LxA4ZK5ns2MvePVxI+eRt
PJKbkQ0KFGxlWitjhisEIlNyVqwPgGRtEYkCQDYl4ZcYxbiLxS98UiuJYYTdJykm
R/Dp+CqPpCwN7d92oR5HR/I5VYjhmra+RG+9h91KXlZ7p4egrv+q8rmyIJMCQHoD
dSIvuSK37CqLxcqYeZekc/IpwoumtV/fGrQBMHBKKTiikFm/stlxUmyYdrjtphdU
lXXg1gFPnACohzIJv1ECQCLkaloIYftx9MHxuILppAHxyud/4zfCZj7gbClyUFRu
tbfJSOf5Fiv6W1WdbyBAvoExZXASKg3HO47wBjLWKSg=
-----END RSA PRIVATE KEY-----";
		}

		if($selector=="SEA")
		{
		$this->privateKey = 
"-----BEGIN RSA PRIVATE KEY-----
MIICXQIBAAKBgQDY/d8xwZy0Mtnwrzxe4XwQy7Z4jn9x88m6ChUmZNJPW86+JPxE
SG3r2mSvxIG/t80z4MAJHaRodpSbJBvbNzGEzHX9iR6B6rx91uz9+u4IVS1yE+MX
QRTiddo/b0zlPC/OTBL7tu6MAiMJTz4i15UesbsnLRHWOCD4trahSM22MwIDAQAB
AoGAIln2ZoMXErPmKqMjNIYPxPzq8yTj6h9E9S25cW2Omb+X+CQUx56LwEW/oM/E
Fpy7YJYY8Jh/uYXkOrc5rbeMIAY5+BOu4PyowGY2YThJxHoFjc3KEbdVlpwRvrYM
nd5sDgwWm7bi1LV82OOFeFH0Sa2hCRaMh0xIU15B87Me3OkCQQDtWIct5XbUmYw+
TXkjOFuwcWBupO4kICTRgcdxnWkZy4nzINYy33o8x/lBGkjDGZN0+utS3i1ogRyI
hTBKu2wXAkEA6gvP+8HsCEzp+Pu3tJK9pknUUgSn9K3tAEynquTPFvgbL79lQ1kt
crTYO2J0m3AtjK3oUGcvGGyR631RL1uMRQJAV5+eUGhtpXGGoB20Aje1Sf+hbVfA
f1/Kl/pEqoJFoftN04+k5KUymKvvLoTIphaUJNTZ+f8CXpmD8jbRrFVjQwJBAN0t
2JmFA/g8J4jC6TLe0hcKAnqYJ9lVXHpB9tnLbeG5CogvChWBey/Bs+869hPHCWS/
HKKPQLSGNcgkH8rvazkCQQDRBxXN3/bp5FEk2/0HqzDM7m1K7MuwhMEEq4BS8um/
+/ZNgNzRGH0Yql2s+i6GVWZe/OwZp1E1YzI+w1DvfpJK
-----END RSA PRIVATE KEY-----";
		}

		if($selector=="WEB")
		{
		$this->privateKey = 
"-----BEGIN RSA PRIVATE KEY-----
MIICWwIBAAKBgQC4wUZBWqGJKbXiHjHkuLRBpztrnGwfz2dEwoZCk1bxSMwynXu8
Wno8f2URF0ti4WNdm1Am1RPvMtnYLlT1Nd0QcvEDtl8SLSquT+YcJyaGCbC43l3e
4Pc9+My78989BNSCJbwV6tfvH6Lo1Uc4tDnM8p/oPUIL1oe/5X21yuoEgQIDAQAB
AoGAA7uD29Yk5Ux+bC8H+wLwQVNLlAT4+juKbo0vgTDQ1NcPqQYdddSuG4LHW+0I
jNrY0w9MMzyixnZUiFWHSdzotl+SNXLfqOu8tzWphB0Gy+Jc29vTaxRdNCaogNRU
NCq26OjGml6SnidwbWeVBCoSbIZaoLVugNEM9Azx172z9AECQQDdOnfo+LKh+hJQ
Pg42i2gRlEB+ImoRXG3wr9NS1nj3JfWQEisO7TjxCs/74ePqyqiDbUbx6a4yzSjC
cmDb1ZoRAkEA1cs7KHLJnjV0GEPw1PlMJl9T5KA2NN4AApKREoa8h0lN/7c7ERdt
21b3lzADwTMWXMy4fDj+mI8Q+CPyzFXTcQJAZ1TO+1dmgHfApBBILTvyMPvRH9lN
N6y3gUtu5mtc9vuY9mE2EXPGO/gz60+4WEuuaCzbjVT706i2GBS6nxPnMQJAV0Dx
bOmkJYCVWA3qbVEtZf/D4mwMk3kDMgmVUaVRrjkZr0KdxbT1Le6Jb9e1wJTUDJ20
sWYlaigBefRZ9FEW0QJAancFZu+6Swll5j6RKkOQtFKru7DAOqeeAel1Jgv5AEhN
pXHcl4aNg1iFE1iIN6Clh9IcbPgYu0AUk3Edt5or6A==
-----END RSA PRIVATE KEY-----";
		}

		if($selector=="ONE")
		{
		$this->privateKey = 
"-----BEGIN RSA PRIVATE KEY-----
MIICWwIBAAKBgQC2syybUbh+Hb7GXzkFRMSedjOfqGWRNa0Eyc51EWYUUIh88Yg0
D+I04bRXAnMxRh46xOEmQxOt0sUDCp6iIbdoDAx8emyJrO3iF+m9I3ip3y59IA5j
jfcX4DijvJ7rYeG2XbJOLKFdX5MMdDrJAX3gYCTPjzL8JcDsFXq0a+BKnwIDAQAB
AoGAJr/h2h9/DeCpMdHIekGXojRXxqkkwaOsyrMywsmp6O0bxcREqyYjSCwG3915
KR5CExzm1AKuDdQCTR7XfnPAPklKFDLQt7+gsYN4Al4Zrtn8gq2X1wuswrR9hug1
1/gJfKNOctfaO7W3cxEZP5XekzlIy0l5pKvxrR9Fif+9FgECQQDlX2MYGqQfEUso
ued0KOdtxSzEsrLF6OoUeF6cqOtLLZERSqzHXHr8OAhXn2ZQXt8w0QcIoVTC2ud2
5svumWnBAkEAy+i+YuRzbzULjKnL/syvS46m4RhFhzrtBIuucIqDjacFKgSnPymP
qJ60GNgTg43IY3wR27aYn47+yDm2vT0MXwJAOEHfAih2nJAXSRPfquPlb0zvIAdc
RaJM11x2iCH+I+A3NnCEVBlgqL/te+BCre+2+jgqa3l2WpxqLQWKeyjxgQJAUFKm
bJ5BOpVSr15TlVNb2g+ffRvqh5KWuyuq03o8yBf62Mpsd10P0gRyPTcguLmpLkc5
YatUA8Z4ZrcVXQYnUQJAL8tIiYWBlRH1frcxenePlkRh/12MSULuNJ9gsDLJfdnN
ZxRIOka87dIn9v46UZJX8gbcaNiFXxsHYdr44xchVg==
-----END RSA PRIVATE KEY-----";
		}

		if($selector=="USA")
		{
		$this->privateKey = 
"-----BEGIN RSA PRIVATE KEY-----
MIICXAIBAAKBgQDcZEcjGfdZjyKsdw7NcdU2rp/My0ei6VLbPrkWV1by9eDwSTyv
wJgBqRnyW/Lk0PLh3ArU3sMw/krMQpb/qAsLAGRoG+qk7JGMLMroiyuyAjYX8aT2
eXTBr6tPlhX7X8ZdANwt34r8MOBIFdFjjZXAq1vssFrSPUbM8WV7Hbd7OwIDAQAB
AoGAWlcAkfLi4WM641cqSiyPKYsLFfd9tdnOjPB5Dh9fFNiVC+n5ZlGb/ZJDgIUQ
W5sK9GouRnPJrxuNrYzeOI25eFYr7n/NJJas2a2riNz5xTiXPZj6W5QaW1sjKztY
9uxu8ghn/NBGVecfeX83Bnc85xVCulxYWoVtdpHIBm5eSIECQQD1OxbBrD2BnlBq
5TqJzBrutlz9IHl2wP//npZRWPRtpfObqZgetW7El2shXgg2JlLk2f9xbUPxbNfi
fMu0kfcFAkEA5hHx8iWpZtHWuIv2zkUxDNLrvnd8HELHu/Tgns8mTbTIApr6dVSn
rB54VgcMOavS1/LCaPv5qFNc5g9Vskm9PwJBAInclRd95/n1cUoW4gjTeJSYasBW
wFIVgBVJJ0JGGuuFbuUku4MQBlx4r15LyZv/gXxsXWF7xsVzpg4KkE5L/K0CQBO2
L+OORIBRtDLlkwTDOtudaqNL+280bYZ2CZSxrNd1iLloa9MHqMH/blH4kpySUyM7
Ylq6U/6O/eOcJrx6wuMCQD3qe50FQq5FFVH1fbky6v3wEHdR48HGV24N+F3L5K0U
LlbYjrLcgHTKtgAqAonMyHweafCoKDPnXAwr3djyjmM=
-----END RSA PRIVATE KEY-----";
		}


		if($selector=="ALPHA")
		{
		$this->privateKey = 
"-----BEGIN RSA PRIVATE KEY-----
MIICWwIBAAKBgQDVorCeRs3H4qx+gdsEg1m04wlOGVyWeen2OvYVoNWwmMGI6o+7
1pMePyFUWOSI+wpHzpXF4PF9/yO2fHNRaQxsE9poe82G188/yATsG9/afWjc7J52
tQ4dQJ5yaWO7jmirBntuCnPFX8e2Ra3osD1T7YIggH8Ox/yRlgFIVq48IQIDAQAB
AoGAGE00aBzHxcgcNRvSbTX/21rEMTUjWh6uJYtZeOZdyIPn6Ao+pXBoNdWalfyy
qn5cEgUG9oZ7EgkW5+hKOeWIOwGzSUtMA+RQE3XW7GgIHj2FNvYjPHsfdLri3V3f
4BTJ6GD+eZCblIJ3V5O9dTsyDUDKS5R6acfch2K5sNQjbwECQQD/D0wAck4fxv0M
9wZp/K6OppfryXTq1NNQxlGyBQW8VweVg4gzSv9olDJLGcbnkmkOdGf8xfkm3kDL
qJPoDx4RAkEA1mxM6iiq4tH6WJ/djtfdV52c5mJK0UP5MXsPNlB1/sd1q5c2mpRj
jCLcBatHFpu5W/7oZDMeIteInhSXP+BtEQJAZnbmuWcyK2HtVsAGO53fIj+a2IZe
Cdjl65VATJvn6fmsekwU80Y1xPWEHteEKJOQ0NXC0LFXnl26+hYHFTq9gQJAE9zu
dxaTVfWrokAU7yGSEIa6PSFH2wDX+bxzmU100Mg7X0zfswwh+J5WEXRfXnnIfvwr
HPUbSpD6x+ISbMlmcQJAQU5Uyudj+XCyNypkrbBnNW4alvPGnTEajr0uLi+Z3L56
6cel5R9rg7INv/EApEKuICyMdFcCve1Cwr1lhl1/Bw==
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
		$timestamp		= "1292429808"; 
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
		
		return $dkim.$signedDkim;


	//	return $body; // $to_be_signed;

	}


	
	public function signOnlyDKIM($to_be_signed) {
			
		return $this->signDkim($to_be_signed) ;
	}

	public function sha1DKIM($body) {
			
		return base64_encode(pack("H*", sha1($body))) ;
	}


	public function simpleBody($body) {
			
		return $this->simpleBodyCanonicalization($body);
	}

	public function relaxedHeader($to_be_signed) {
			
		return $this->relaxedHeaderCanonicalization($to_be_signed);
	}



	public function QP($body) {
			
		return $this->quotedPrintable($body);
	}



}

?>