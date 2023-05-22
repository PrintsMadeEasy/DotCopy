import com.pme.GeneralFunctions;


// This class provides a way to Add name value pairs into an object that can be used for building a URL.
// You can pass in a Script name with path.
// Provieds a Method to get back a URL from Script and Name/Value pairs (that is URL encoded).

class com.pme.UrlBuilder {

	
	// We are keeping the Names and the Values in Parallel arrays.
	private var namesArr:Array = new Array();
	private var valuesArr:Array = new Array();
	
	// Holds the Path Name and Script.
	private var urlString:String;
	
	private var noCacheFlag:Boolean = true;
	
	
	// Constructor
	public function UrlBuilder(){
	
		// Nothing for now.
	}
	
	
	public function clearNameValuePairs(){
		this.namesArr = new Array();
		this.valuesArr = new Array();
	}
	
	// Add a name/value pairs for desitnation URL
	// The Name (parameter #1) can not be left blank and it can not contain any symbols that need to be encoded (like &=?.)
	public function addNameValuePair(nameStr:String, valueParam:Object):Void {
	
		nameStr = GeneralFunctions.trim(nameStr);
		var valueStr:String = GeneralFunctions.trim(valueParam.toString());
		
		if(nameStr.length == 0){
			trace("Error in function addNameValuePair:  The Name parameter can not be blank.");
			return;
		}
		
		if(nameStr.indexOf("?") != -1 || nameStr.indexOf(" ") != -1 || nameStr.indexOf("&") != -1 || nameStr.indexOf(".") != -1 || nameStr.indexOf("=") != -1 || nameStr.indexOf("+") != -1){
			trace("Error in function addNameValuePair:  The Name Parameter (" + nameStr +") may not contain any special characters like question marks, ampersands, spaces... anything that would need to be encoded.");
			return;
		}
		
	
		// If the name already exists within our names array... then just override the value
		// otherwise, add a new entries to the parallel arrays.
		for(var i:Number = 0; i < this.namesArr.length; i++){
		
			if(this.namesArr[i] == nameStr){
				this.valuesArr[i] = valueStr;
				return;
			}
		}
		
		
		this.namesArr.push(nameStr);
		this.valuesArr.push(valueStr);
		
	}
	
	// Pass in TRUE to have the object append a timestamp to the end of the URL (to make sure that the URL does not get cached).
	public function noCaching(flag:Boolean){
		this.noCacheFlag = flag;
	}
	
	
	public function setURL(pathStr:String):Void {
	
		pathStr = GeneralFunctions.trim(pathStr);
		
		if(pathStr.length == 0){
			trace("Error in function setURL:  The parameter can not be blank.");
			return;
		}
		
		if(pathStr.indexOf("?") != -1){
			trace("Error in function setURL:  The url should not contain a Question Mark.  This will automatically be added for you before the Name/Value pairs.");
			return;
		}
		
		this.urlString = pathStr;
	}
	
	// Will get the URL with Name value pairs.  Everything will be URL encoded.
	public function getURLwithNameValuePairs():String {
	
		// If we want to make sure that the URL is always unique, then add the current timestamp to our name/value pairs.
		if(this.noCacheFlag){
			var dateObj:Date = new Date();
			this.addNameValuePair("nocache", dateObj.valueOf().toString() + dateObj.getMilliseconds().toString());
		}
		
		if(this.urlString.length == 0){
			trace("Error in function getURLwithNameValuePairs:  The URL has not been set yet.");
			return;
		}
		
		var parametersStr:String = "";
		
		for(var i:Number = 0; i < this.namesArr.length; i++){
			
			if(parametersStr.length != 0)
				parametersStr += "&";

			parametersStr += this.namesArr[i] + "=" + escape(this.valuesArr[i]);
		}
		
		
		// If there are no parameters then there is no need to use a Question Mark in the URL
		if(parametersStr.length == 0)
			return this.urlString;
		else
			return this.urlString + "?" + parametersStr;
			

		
	}
	
	
	

}

