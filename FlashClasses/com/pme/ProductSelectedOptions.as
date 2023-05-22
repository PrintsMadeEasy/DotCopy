



// This should be copied into Every Project so they may hold their Option/Choices/Quantity values Separately.
// Can also be used for calculating Prices before a Project has been created.
class ProductSelectedOptions
{

	// Holds an array of ProductOptionAndSelection objects.
	public var optionsAndSelectionsArr:Array;
	public var quantitySelected:Number;


	// Constructor
	public function ProductSelectedOptions()
	{
		this.quantitySelected = 0;
		this.optionsAndSelectionsArr = new Array();
	}


	public function getSelectedQuantity():Number
	{
		return this.quantitySelected;
	}

	public function setQuantity(quantity):Void
	{
		this.quantitySelected = parseInt(quantity);
	}



	// Returns an Array of Option Names
	public function getOptionNames():Array
	{
		var retArr = new Array();

		for(var i:Number = 0; i<this.optionsAndSelectionsArr.length; i++)
			retArr.push(this.optionsAndSelectionsArr[i].optionName);

		return retArr;
	}


	// Returns the Choice selected, Null if the Option Name does not exist.
	public function getChoiceSelected(optName):String
	{

		for(var i:Number = 0; i< this.optionsAndSelectionsArr.length; i++)
		{
			if(this.optionsAndSelectionsArr[i].optionName == optName)
				return this.optionsAndSelectionsArr[i].choiceName;
		}

		return null;
	}

	// Sets the Choice for the Given Option Name.
	// If the Option Name does not currently exist... it will add the Option and Selected Choice
	public function setOptionChoice(optName, choiceName):Void
	{

		for(var i:Number = 0; i< this.optionsAndSelectionsArr.length; i++)
		{

			if(this.optionsAndSelectionsArr[i].optionName == optName)
			{	
				 this.optionsAndSelectionsArr[i].choiceName = choiceName;
				 return;
			}
		}

		var newOptSelectObj = new ProductOptionAndSelection(optName, choiceName);

		// Create a new Option and Selected Choice
		this.optionsAndSelectionsArr.push(newOptSelectObj);
	}

	// Parse an Options Description String that is separated by commas and Dashes.
	public function parseOptionsDescription(optDesc):Void
	{

		// Clean the slate before parsing the Option Description.
		this.optionsAndSelectionsArr = new Array();

		// Commas separate each Option from one another.
		var optArr = optDesc.split(", ");

		for(var i:Number = 0; i< optArr.length; i++)
		{
			if(optArr[i] == "")
				continue;

			// Now extract the Option and Choice name
			// They are split on the First Dash... there could be remaining dashes in the Choice Name... but there are never dashes in the Option Name.
			var optChcPartsArr = optArr[i].split(" - ");

			if(optChcPartsArr.length < 2)
			{
				trace("Error parsing the Option/Choices. The format is invalid: " + optDesc);
				continue;
			}

			var thisOptionName = optChcPartsArr[0];
			var thisChoiceName = optChcPartsArr[1];

			// If there were dashes in the Choice Name... the glue the remaining pieces back together and put the dashes back in.
			for(var j=2; j<optChcPartsArr.length; j++)
				thisChoiceName += " - " + optChcPartsArr[j];


			// Save the Option/Choice Selection
			this.setOptionChoice(thisOptionName, thisChoiceName);
		}
	}



	// Works the opposite to parseOptionsDescription.
	// Will take all of the Options & Selected Choiced and turn them into a string formated by commas and dashes
	public function getOptionAndChoicesStr():String
	{
		var retStr = "";

		var optionNamesArr = this.getOptionNames();

		for(var i:Number = 0; i< optionNamesArr.length; i++)
		{
			if(retStr != "")
				retStr += ", ";

			retStr += optionNamesArr[i] + " - " + this.getChoiceSelected(optionNamesArr[i]);

		}

		return retStr;
	}

}

