<?

class ColorLib {
	#-- Parameter 1 is the Color Object to pass in.  That will hold the information for the return values ---#
	#-- Parameter 2 is a boolean value.  Indicating whether the color values should be in Hex or decimal format. --#
	static function getRgbValues($colorCode, $hexYes){
	
		#-- If it is a string.. check if it is in Hex format or not.  Then convert string to integer.
		if(is_string($colorCode)){
	
			if(substr($colorCode,0,2) == "0x"){
				$colorValue = intval($colorCode, 16);
			}
			else if(substr($colorCode,0,1) == "#"){
				$colorValue = intval(substr($colorCode,1), 16);
			}
			else{
				$colorValue = intval($colorCode);
			}
		}
		else{
			$colorValue = $colorCode;
		}
	
		if($colorValue > intval("0xFFFFFF", 16)){
			throw new Exception("Color code is out of range: " . $colorValue . ". Max value permitted is: " . intval("0xFFFFFF", 16));
		}
	
		##-- This will ensure the values are 6 digits... EX  "0000FF"  instead of just "FF"
		$zeros = "000000";
		$rgbString = substr($zeros, 0, 6 - strlen (dechex($colorValue))) . dechex($colorValue);
	
		#-- Create a new color object to hold the values --#
		$colorObj = new RgbColorCodeContainer();
	
		if($hexYes){
			$colorObj->red = substr($rgbString,0,2);
			$colorObj->green = substr($rgbString,2,2);
			$colorObj->blue = substr($rgbString,4,2);
		}
		else{
			$colorObj->red = intval(substr($rgbString,0,2),16);
			$colorObj->green = intval(substr($rgbString,2,2),16);
			$colorObj->blue = intval(substr($rgbString,4,2),16);
		}
	
		return $colorObj;
	}
	
	// You can pass in a color code dec or hex format, it will return the decimal color value.
	static function getDecimalValueOfColorCode($colorCode){
		
		$colorCodeObj = ColorLib::getRgbValues($colorCode, true);
		$hexValue = $colorCodeObj->red . "" . $colorCodeObj->green . "" . $colorCodeObj->blue;
		
		return intval($hexValue, 16);
	}
}

##-- Define a class so that we can neatly get a Return of data from our GetRGBvalues function --##
class RgbColorCodeContainer {
	public $red;
	public $green;
	public $blue;
}


