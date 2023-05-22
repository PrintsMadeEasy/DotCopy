import com.pme.GeneralFunctions;


// This class provides a way to Add name value pairs into a Hash table. 
// You can Add any type of Objects into the hash table, not just strings.
// Provides an itterator so that you can get them back in the same order that you put them in.
// Can find out if a Key exists within the hash table and also search for values within the array.

class com.pme.Hash {

	
	// Keep the Names and the Values in Parallel arrays.
	private var keysArr:Array;
	private var valuesArr:Array;
	
	private var indexCounter:Number;
	

	// Constructor
	public function Hash(){
	
		this.clearHash();
		this.resetPointer();
		
	}
	
	
	public function clearHash(){
		this.keysArr = new Array();
		this.valuesArr = new Array();
	}
	
	// Call this when we want to start iterating from the Start of the array.
	public function resetPointer(){
		this.indexCounter = 0;
	}
	
	
	// Add a name/value pairs into the Hash table.
	// The Name (parameter #1) can not be left blank, it can not exceed 50 characters, and it can not have line breaks.
	// The value can be any type of an object.
	// If the key value already exists within the Hash Table, then it will just override the value.
	public function addEntry(keyStr:String, valueParam:Object):Void {
	
		keyStr = GeneralFunctions.trim(keyStr);
		
		if(keyStr.length == 0){
			trace("Error in method Hash.addEntry:  The Name parameter can not be blank.");
			return;
		}
		
		if(keyStr.length > 50){
			trace("Error in method Hash.addEntry:  The Name parameter may not contain more than 50 characters.");
			return;
		}
		
		if(keyStr.indexOf(chr(13)) != -1 || keyStr.indexOf(chr(10)) != -1){
			trace("Error in method Hash.addEntry:  The Name Parameter (" + keyStr +") may not contain Line Breaks.");
			return;
		}
		
	
		// If the name already exists within our names array... then just override the value
		// otherwise, add a new entries to the parallel arrays.
		for(var i:Number = 0; i < this.keysArr.length; i++){
		
			if(this.keysArr[i] == keyStr){
				this.valuesArr[i] = valueParam;
				return;
			}
		}
		
		
		this.keysArr.push(keyStr);
		this.valuesArr.push(valueParam);
		
	}
	

	
	// Advances the pointer.  You need to call this before initially calling the method valueFromKey
	public function nextKey():Boolean {

		this.indexCounter++;

		if(this.indexCounter > this.keysArr.length)
			return false;
		else
			return true;
	
	}
	
	// Returns NULL if pointer is out of range.
	// You should call the method nextKey() before calling currentKey or currentValue... 
	// ... However if you don't this method will sense that and start off on the first key anyway.
	// After that it will not advance to the next key.  you will have to use nextKey() from then on.
	public function currentKey():String {
	
		if(this.indexCounter == 0)
			this.indexCounter++;
		
		if(this.indexCounter > this.keysArr.length)
			return null;
	
		return this.keysArr[(this.indexCounter - 1)];
	
	}
	
	// Returns NULL if pointer is out of range.
	// You should call the method nextKey() before calling currentKey or currentValue... 
	// ... However if you don't this method will sense that and start off on the first key anyway.
	// After that it will not advance to the next key.  you will have to use nextKey() from then on.
	public function currentValue():Object {
	
		if(this.indexCounter == 0)
			this.indexCounter++;
		
		if(this.indexCounter > this.keysArr.length)
			return null;
	
		return this.valuesArr[(this.indexCounter - 1)];
	
	}
	
	
	
	// Returns the Value that is associated with the given key.
	// If the key does not exist within the array returns NULL
	public function valueFromKey(keyStr:String):Object {
	
		for(var i:Number = 0; i < this.keysArr.length; i++){
			
			if(this.keysArr[i] == keyStr)
				return this.valuesArr[i];
		}
		
		return null;
	}
	
	
	// Pass in a number and it will return the Key at the given position.
	// Value is Zero based.
	// If no value exists that that position the method will return null.
	public function getKeyFromPosition(pos:Number):String {
		
		if(pos < 0 || (pos + 1) > this.keysArr.length)
			return null;
		else
			return this.keysArr[pos];
	}
	
	
	// Pass in a number and it will return the Value at the given position.
	// Value is Zero based.
	// If no value exists that that position the method will return null.
	public function getValueFromPosition(pos:Number):Object {
		
		if(pos < 0 || (pos + 1) > this.keysArr.length)
			return null;
		else
			return this.valuesArr[pos];
	}
	
	
	// Pass in a Key, this method will return the position that it is at. (Zero Based)
	// If the key is not found, the method will return NULL.
	public function getPositionFromKey(keyStr:String):Number {
	
		for(var i:Number = 0; i < this.keysArr.length; i++){

			if(this.keysArr[i] == keyStr)
				return i;
		}
		
		return null;
	}
	
	
	// Returns the Size of the Hash table.

	public function sizeOf():Number {
		
		return this.keysArr.length;
		
	}
	
	
	// Returns True or false depending on whether the given key exists within the Hash Table
	public function keyExists(keyStr:String):Boolean {
	
		for(var i:Number = 0; i < this.keysArr.length; i++){
			
			if(this.keysArr[i] == keyStr)
				return true;
		}
		
		return false;
	}
	
	// Pass in one of the values that you want to search within the hash talble.
	// returns True if it can find the string, Object, etc.
	public function inArray(valueStr:Object):Boolean {
	
		for(var i:Number = 0; i < this.valuesArr.length; i++){
			
			if(typeof(this.valuesArr[i]) == typeof(valueStr)){
				if(this.valuesArr[i].toString() == valueStr.toString())
					return true;
			}		
		}
		
		return false;
	
	}
	
	// Removes the given key and its value... and reorganized the internal paralllel arrays.
	// Will not complain if the key does not exist.
	public function unsetKey(keyStr:String):Void {
	
		var keyIndexFound:Number = -1;
		
		for(var i:Number = 0; i < this.keysArr.length; i++){
			if(this.keysArr[i] == keyStr)
				keyIndexFound = i;
		}
		
		if(keyIndexFound == -1)
			return;
			
		// Fill up Temporary Arrays, keeping in mind to skip over the index to be unset.
		var tempKeysArr = new Array();
		var tempValuesArr = new Array();
		
		var tempArrayCounter:Number = 0;
		
		for(var i:Number = 0; i < this.keysArr.length; i++){
			
			if(i == keyIndexFound)
				continue;
			
			tempKeysArr[tempArrayCounter] = this.keysArr[i];
			tempValuesArr[tempArrayCounter] = this.valuesArr[i];
			
			tempArrayCounter++;
		}
		
		// Assign the temp arrays back into our member variables.
		this.clearHash();
		
		this.keysArr = tempKeysArr;
		this.valuesArr = tempValuesArr;
		
	}
	

	// Override the toString method.
	function toString():String {
		
		var retStr:String = "";
		
		var keyStr:String;
		
		while(this.nextKey()){
		
			if(retStr.length != 0)
				retStr += ", ";
			
			retStr += this.currentKey() + "=>" + this.valueFromKey(this.currentKey());
		}
		
		return retStr;
	}
	


}

