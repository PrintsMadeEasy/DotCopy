class com.pme.GeneralFunctions {

	//Search and Replace function for any string.  It replaces all occurences
	public static function stringReplace (origStr:String, searchStr:String, replaceStr:String):String {
		var tempStr:String = "";
		var startIndex:Number = 0;
		var searchIndex:Number;
		
		if (searchStr == "")
			return origStr;

		if (origStr.indexOf(searchStr) != -1){
			while ((searchIndex = origStr.indexOf(searchStr,startIndex)) != -1){
				tempStr +=origStr.substring(startIndex,searchIndex);
				tempStr +=replaceStr;
				startIndex =searchIndex +searchStr.length;
			}
			return tempStr +origStr.substring(startIndex);
		}
		else 
    			return origStr;

	}
	
	// Useful if you need to pass a message to Javascript through getURL
	// It will escape quotations, apostrophes, and backslashes with backslashes
	public static function escapeString(origStr:String):String {
	
		origStr = GeneralFunctions.stringReplace(origStr, '\\', '\\\\');
		origStr = GeneralFunctions.stringReplace(origStr, '"', '\\"');
		origStr = GeneralFunctions.stringReplace(origStr, "'", "\\'");
		
		return origStr;
	}
	
	
	
	// Trim a string.  Get rid of spaces and newline characters at the beggining and end of the string.
	public static function trim(txt_str:String):String {
	
		// Get rid of empties at the beggining.
		while (txt_str.charAt(0) == " " || txt_str.charAt(0) == chr(13) || txt_str.charAt(0) == chr(10)) 
			txt_str = txt_str.substring(1, txt_str.length);

		// Get rid of empties at the end.
		while (txt_str.charAt(txt_str.length-1) == " " || txt_str.charAt(txt_str.length-1) == chr(13) || txt_str.charAt(txt_str.length-1) == chr(10)) 
			txt_str = txt_str.substring(0, txt_str.length-1);

		return txt_str;
	}
	
	

	// Returns true if it finds the needle within the Haystack (which is an array).
	public static function inArray(needle:Object, haystackArr:Array):Boolean {
	
		for(var i:Number = 0; i < haystackArr.length; i++){
			
			if(haystackArr[i] == needle)
				return true;
		}
		
		return false;
	
	}
	
	public static function formatDecimals(num, digits) {
		//if no decimal places needed, we're done
		if (digits <= 0) {
			return Math.round(num);
		}
		//Math floor the number to specified decimal places
		//e.g. 12.3456 to 3 digits (12.346) -> mult. by 1000, round, div. by 1000
		var tenToPower = Math.pow(10, digits);
		var cropped = String(Math.floor(num * tenToPower) / tenToPower);
		//add decimal point if missing
		if (cropped.indexOf(".") == -1) {
			cropped += ".0";  //e.g. 5 -> 5.0 (at least one zero is needed)
		}

		//finally, force correct number of zeroes; add some if necessary
		var halves = cropped.split("."); //grab numbers to the right of the decimal
		//compare digits in right half of string to digits wanted
		var zerosNeeded = digits - halves[1].length; //number of zeros to add
		for (var i=1; i <= zerosNeeded; i++) {
			cropped += "0";
		}
		return(cropped);
	}
	

	
}