

class ProductOption
{

	public var optionName:String;
	public var optionAlias:String;
	public var optionDescription:String;
	public var optionDescriptionIsHTML:Boolean;
	public var affectsArtworkSidesFlag:Boolean;
	public var adminOptionFlag:Boolean;
	public var choices:Array;
	


	// Constructor
	public function ProductOption()
	{
		this.optionName = "Option Name Undefined";
		this.optionAlias = "Option Alias Undefined.";
		this.optionDescription = "Option Description Undefined.";
		this.optionDescriptionIsHTML = false;
		this.affectsArtworkSidesFlag = false;
		this.adminOptionFlag = false;
		
		this.choices = new Array();
	}

	// Returns an Array of Choice Names within this options.
	public function getChoiceNames():Array
	{	
		var retArr = new Array();

		for(var i:Number = 0; i<this.choices.length; i++)
			retArr.push(this.choices[i].choiceName);

		return retArr;
	}


	// Returns a Choice Object matching the ChoiceName
	// Return NULL if the choice name does not exist.
	public function getChoiceObj(chcName):String
	{	
		var retArr = new Array();

		for(var choiceCounter in this.choices)
		{
			if(chcName == this.choices[choiceCounter].choiceName)
				return choices[choiceCounter];
		}

		return null;
	}

}


