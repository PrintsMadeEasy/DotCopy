



//------------------------------   Product Class -------------------------------------


// Product Class
// Do not call create new classes directly with this.
// Call the Product Loader method getProductObj(ProdID)
class Product
{

	public var productID:Number;
	public var productTitle:String;
	public var productTitleExt:String;
	public var productName:String;
	public var numberOfArtworkSides:Number;
	public var artworkWidthInches:Number;
	public var artworkHeightInches:Number;
	public var artworkBleedPicas:Number;
	public var unitWeight:Number;
	public var basePrice:Number;
	public var initialSubtotal:Number;
	public var variableDataFlag:Boolean;
	public var mailingServicesFlag:Boolean;
	public var thumbnailBackgroundFlag:Boolean;
	public var thumbnailCopyIconFlag:Boolean;
	public var thumbnailWidthApprox:Number;
	public var thumbnailHeightApprox:Number;
	public var productImportance:Number;
	public var compatibleProductIDsArr:Array;
	public var options:Array;
	
	
	// Quantity Breaks in the Format "20^0.20|100^0.40|500^0.60";
	public var quantityBreaksBase:String;
	public var quantityBreaksSubtotal:String;
	
	
	// This will hold the default choices and quantity.
	// It will also be used to hold option/choice selection if someone wants to check prices without going through a Project Object.
	public var selectedOptionsObj:ProductSelectedOptions;
	
	// Cache the results of calculations we know won't change once they have been filled in.
	public var quantitySelectionArrCached:Array;  
	


	// Constructor
	public function Product()
	{
		
		this.productID = 0;
		this.productTitle = "Undefined Product Name";
		this.productTitleExt = "";
		this.productName = "Undefined Product Name";
		this.numberOfArtworkSides = 0;
		this.artworkWidthInches  = 0;
		this.artworkHeightInches = 0;
		this.artworkBleedPicas = 0;
		this.unitWeight = 0;
		this.basePrice = 0;
		this.initialSubtotal = 0;
		this.variableDataFlag = false;
		this.mailingServicesFlag = false;
		this.thumbnailBackgroundFlag = false;
		this.thumbnailCopyIconFlag = false;
		this.thumbnailWidthApprox = 0;
		this.thumbnailHeightApprox = 0;
		this.productImportance = 0;
		
		this.quantityBreaksBase = "";
		this.quantityBreaksSubtotal = ""
		
		this.selectedOptionsObj = new ProductSelectedOptions();
		
		this.compatibleProductIDsArr = new Array();
		this.options = new Array();
		this.quantitySelectionArrCached = new Array();
		
	}


	public function getProductTitle():String
	{
		return this.productTitle;
	}
	public function getProductTitleExt():String
	{
		return this.productTitleExt;
	}
	public function getProductName():String
	{
		return this.productName;
	}
	public function getArtworkWidthInches():Number
	{
		return this.artworkWidthInches;
	}
	public function getArtworkHeightInches():Number
	{
		return this.artworkHeightInches;
	}
	public function getArtworkBleedPicas():Number
	{
		return this.artworkBleedPicas;
	}
	public function getPossibleArtworkSides():Number
	{
		return this.numberOfArtworkSides;
	}
	public function getProductID():Number
	{
		return this.productID;
	}
	public function isVariableData():Boolean
	{
		return this.variableDataFlag;
	}
	public function hasMailingServices():Boolean
	{
		return this.mailingServicesFlag;
	}
	public function getThumbnailWidthApprox():Number
	{
		return this.thumbnailWidthApprox;
	}
	public function getThumbnailHeightApprox():Number
	{
		return this.thumbnailHeightApprox;
	}


	// This is an indication of whether we should Write a Title above the artwork thumbnail.
	// Otherwise the Thumbnail background should generally contain the Thumbnail image inside.
	public function hasThumnailBackground():Boolean
	{
		return this.thumbnailBackgroundFlag;
	}

	// Let's us know if there is a JPEG image uploaded to indicate a "copy" was madeof a project.
	public function hasThumnailCopyIcon():Boolean
	{
		return this.thumbnailCopyIconFlag;
	}


	public function getProductImportance():Number
	{
		return this.productImportance;
	}


	public function getCompatibleProductIDsArr():Array
	{
		return this.compatibleProductIDsArr;
	}




	// Returns an array of Product Option Names
	public function getOptionNames():Array
	{

		var retArr = new Array();

		for(var i:Number = 0; i<this.options.length; i++)
			retArr.push(this.options[i].optionName);

		return retArr;
	}


	// Returns true if there are no choices under the Option.
	// This shouldn't happen, but an administrator could add an option... and then forget to add choices.
	// Also return TRUE if the option name does not exist.
	public function checkIfOptionIsEmpty(optName):Boolean
	{
		for(var i:Number = 0; i<this.options.length; i++)
		{
			if(optName == this.options[i].optionName)
			{
				if(this.options[i].choices.length == 0)
					return true;
				else
					return false;
			}
		}
		return true;
	}

	// Returns true if there is only 1 choice under the Option.
	public function checkIfOptionHasSingleChoice(optName):Boolean
	{
		for(var i:Number = 0; i<this.options.length; i++)
		{
			if(optName == this.options[i].optionName)
			{
				if(this.options[i].choices.length == 1)
					return true;
				else
					return false;
			}
		}
		trace("Error in method checkIfOptionHasSingleChoice. The Option Name does not exist.");
	}


	// Returns true if hte Option Name and the Choice exist on this product.
	public function checkIfOptionNameExist(optName):Boolean
	{
		for(var i:Number = 0; i<this.options.length; i++)
		{
			if(optName == this.options[i].optionName)
				return true;
		}
		return false;
	}

	// Returns true if hte Option Name and the Choice exist on this product.
	public function checkIfOptionAndChoiceExist(optName, chcName):Boolean
	{
		for(var i:Number = 0; i<this.options.length; i++)
		{
			if(optName == this.options[i].optionName)
			{
				for(var x in this.options[i].choices)
				{
					if(chcName == this.options[i].choices[x].choiceName)
						return true;
				}
			}
		}
		return false;
	}


	// Gives you a Description of the Option Name.
	// Make sure that the Option Name exists before calling this method.
	public function getOptionDescription(optName)
	{
		for(var i:Number = 0; i<this.options.length; i++)
		{
			if(optName == this.options[i].optionName)
				return this.options[i].optionDescription
		}

		trace("Error in method Product.getOptionDescription.\nThe Option Names does not exist: " + optName);
	}

	// Provides an alias of Option Name.
	// Make sure that the Option Name exists before calling this method.
	public function getOptionAlias(optName):String
	{
		for(var i:Number = 0; i<this.options.length; i++)
		{
			if(optName == this.options[i].optionName)
				return this.options[i].optionAlias
		}

		trace("Error in method Product.getOptionAlias.\nThe Option Names does not exist: " + optName);
	}

	// Returns True or False depending on whether the Option Description is in HTML format or not.
	// Make sure that the Option Name exists before calling this method.
	public function isOptionDescriptionHTMLformat(optName):Boolean
	{
		for(var i:Number = 0; i<this.options.length; i++)
		{
			if(optName == this.options[i].optionName)
				return this.options[i].optionDescriptionIsHTML
		}

		trace("Error in method Product.isOptionDescriptionHTMLformat.\nThe Option Names does not exist: " + optName);
	}


	// Returns True or False depending on whether the Option is changed by Administrators
	// Make sure that the Option Name exists before calling this method.
	public function doesOptionAffectNumOfArtworkSides(optName):Boolean
	{
		for(var i:Number = 0; i<this.options.length; i++)
		{
			if(optName == this.options[i].optionName)
				return this.options[i].affectsArtworkSidesFlag
		}

		trace("Error in method Product.doesOptionAffectNumOfArtworkSides.\nThe Option Names does not exist: " + optName);
	}

	// Returns True or False depending on whether the Option is changed by Administrators
	// Make sure that the Option Name exists before calling this method.
	public function isOptionForAdministrators(optName):Boolean
	{
		for(var i:Number = 0; i<this.options.length; i++)
		{
			if(optName == this.options[i].optionName)
				return this.options[i].adminOptionFlag
		}

		trace("Error in method Product.isOptionForAdministrators.\nThe Option Names does not exist: " + optName);
	}




	// Returns an array of Choice Names belonging to the Given Option
	public function getChoiceNamesForOption(optName):Array
	{
		for(var i:Number = 0; i<this.options.length; i++)
		{
			if(optName == this.options[i].optionName)
					return this.options[i].getChoiceNames();
		}

		trace("Error in method Product.getChoiceNamesForOption.\nThe Option Names does not exist: " + optName);
	}



	// Returns True or False depending on whether the Choice Description is in HTML format or not.
	// Make sure that the Option Name AND the Choice name must exist before calling this method.
	public function isChoiceDescriptionHTMLformat(optName, chcName):Boolean
	{
		for(var i:Number = 0; i<this.options.length; i++)
		{
			if(optName == this.options[i].optionName)
			{
				for(var x in this.options[i].choices)
				{
					if(chcName == this.options[i].choices[x].choiceName)
						return this.options[i].choices[x].choiceDescriptionIsHTML;
				}
			}
		}

		trace("Error in method Product.isChoiceDescriptionHTMLformat.\nThe Option Names does not exist: " + optName + ".\nOr the Choice Name does not exist: " + chcName);
	}


	// Returns the full description of the Choice, belonging to the given option.
	public function getChoiceDescription(optName, chcName):String
	{
		for(var i:Number = 0; i<this.options.length; i++)
		{
			if(optName == this.options[i].optionName)
			{
				for(var x in this.options[i].choices)
				{
					if(chcName == this.options[i].choices[x].choiceName)
						return this.options[i].choices[x].choiceDescription;
				}
			}
		}

		trace("Error in method Product.getChoiceDescription.\nThe Option Names does not exist: " + optName + ".\nOr the Choice Name does not exist: " + chcName);
	}


	// Returns the Choice Name Alias belonging to the given option.
	public function getChoiceAlias(optName, chcName):String
	{
		for(var i:Number = 0; i<this.options.length; i++)
		{
			if(optName == this.options[i].optionName)
			{
				for(var x in this.options[i].choices)
				{
					if(chcName == this.options[i].choices[x].choiceName)
						return this.options[i].choices[x].choiceAlias;
				}
			}
		}

		trace("Error in method Product.getChoiceAlias.\nThe Option Names does not exist: " + optName + ".\nOr the Choice Name does not exist: " + chcName);
	}



	// Returns True or False depending on whether the Choice Description is in HTML format or not.
	// Make sure that the Option Name AND the Choice name must exist before calling this method.
	public function isChoiceIsHidden(optName, chcName):Boolean
	{
		for(var i:Number = 0; i<this.options.length; i++)
		{
			if(optName == this.options[i].optionName)
			{
				for(var x in this.options[i].choices)
				{
					if(chcName == this.options[i].choices[x].choiceName)
						return this.options[i].choices[x].choiceIsHiddenFlag;
				}
			}
		}

		trace("Error in method Product.isChoiceIsHidden.\nThe Option Names does not exist: " + optName + ".\nOr the Choice Name does not exist: " + chcName);
	}


	// Returns a number that tells you how many Artwork Sides there will be with the choice selected.
	public function choiceChangesNumberOfArtworkSidesTo(optName, chcName):Number
	{
		for(var i:Number = 0; i<this.options.length; i++)
		{
			if(optName == this.options[i].optionName)
			{
				for(var x in this.options[i].choices)
				{
					if(chcName == this.options[i].choices[x].choiceName)
						return this.options[i].choices[x].changeArtworkSides;
				}
			}
		}

		trace("Error in method Product.choiceChangesNumberOfArtworkSidesTo.\nThe Option Names does not exist: " + optName + ".\nOr the Choice Name does not exist: " + chcName);
	}





	// Returns an array of Quantities that exist within the Root Quantity Price Breaks.
	// If this is a Variable Data Product... or no Quantity Breaks have been defined, then this method will return an empty array.
	public function getQuantityChoicesArr():Array
	{

		if(this.variableDataFlag || this.quantityBreaksSubtotal == "")
			return new Array();


		// Find out if we have already calculated the results for this Product.
		if(this.quantitySelectionArrCached.length != 0)
			return this.quantitySelectionArrCached;

		var retArr = new Array()	

		var breaksArr = new Array();
		breaksArr = this.quantityBreaksSubtotal.split("|");

		for(var breakCounter=0; breakCounter<breaksArr.length; breakCounter++)
		{

			var quanPriceArr = new Array();
			quanPriceArr = breaksArr[breakCounter].split("^");

			if(quanPriceArr.length != 2)
			{
				trace("Problem in Product.getQuantityChoicesArr the Price Modifiation String is not in the proper format.: " + this.quantityBreaksSubtotal);
				return new Array(0);
			}

			retArr[breakCounter] = quanPriceArr[0];
		}


		// Cache the Results.
		this.quantitySelectionArrCached = retArr;

		return retArr;
	}


	// Weight is returned with 2 decimal places.  It is up to you to "Ceil" that result to the next highest integer if you want.
	// Gets the Subtotal for the Product.
	// Will take a ProductSelectedOptions parameter to use that quantity/option/choice selection.
	// .. the Option/Choices (which can have an affect on weight).
	// This is useful for Project Classes to get the Weight based upon settings stored in the Project.
	public function getTotalWeight():Number
	{

		return this.getTotalWeightOverride(this.selectedOptionsObj);


	}

	// Weight is returned with 2 decimal places.  It is up to you to "Ceil" that result to the next highest integer if you want.
	// Gets the Weight for the Product based upon the Quantity and Choice specified in the selectedOptionsObj object.
	// The Product Object can hold Quantity and Option/Choice selections independently from Project Objects
	public function getTotalWeightOverride(selectedOptionsObj):Number
	{

		if(!(selectedOptionsObj instanceof ProductSelectedOptions))
		{
			trace("Error in Product.getTotalWeight. The parameter must be an Object of ProductSelectedOptions");
			return;
		}

		var totalUnitWeight:Number = this.unitWeight;
		var projectWeightChanges:Number = 0;

		for(var i:Number = 0; i<this.options.length; i++)
		{
			// Skip Empty Options
			if(this.options[i].choices.length == 0)
				continue;

			var selectedChoiceName = selectedOptionsObj.getChoiceSelected(this.options[i].optionName);

			if(selectedChoiceName == null)
			{
				trace("Error in Product.Weight. The option Name was not found within the selection: " + this.options[i].optionName);
				return;
			}

			// Select the right choice... and make sure all of the other choices are not selected.
			for(var x:Number = 0; x<this.options[i].choices.length; x++)
			{
				if(this.options[i].choices[x].choiceName == selectedChoiceName)
				{
					totalUnitWeight += this.options[i].choices[x].getBaseWeightChange();
					projectWeightChanges += this.options[i].choices[x].getProjectWeightChange();
					break;
				}
			}
		}

		return totalUnitWeight * selectedOptionsObj.getSelectedQuantity() + projectWeightChanges;
	}



	// Gets the Subtotal for the Product.
	// The Product Object can hold Quantity and Option/Choice selections independently from Project Objects
	public function getSubtotal():Number
	{

		return this.getSubtotalOverride(this.selectedOptionsObj);

	}



	public function getSelectedQuantity():Number
	{
		return this.selectedOptionsObj.getSelectedQuantity();
	}


	// Returns the Choice selected,...  trace(s an Error if the Option Name does not exist.
	public function getChoiceSelected(optName):String
	{
		var choiceSelected = this.selectedOptionsObj.getChoiceSelected(optName);

		if(choiceSelected == null){
			trace("Error in Product.getChoiceSelected(). The Option Name does not exist: " + optName)
			return null;
		}

		return choiceSelected;
	}


	// Gets the Subtotal for the Product.
	// Will take a ProductSelectedOptions parameter to use that quantity/option/choice selection.
	// This is useful for Project Classes to get the Subtotal based upon a certain selection.
	public function getSubtotalOverride(selectedOptionsObj):Number
	{

		if(!(selectedOptionsObj instanceof ProductSelectedOptions))
		{
			trace("Error in Product.getSubtotal. The parameter must be an Object of ProductSelectedOptions");
			return;
		}

		var totalBaseChanges:Number = this.basePrice;
		var totalSubtotalChanges:Number = this.initialSubtotal;


		// Add any Quantity Price Breaks into the root product.
		totalBaseChanges += ProductPriceModification.getLastPriceModification(selectedOptionsObj.getSelectedQuantity(), this.quantityBreaksBase);
		totalSubtotalChanges += ProductPriceModification.getLastPriceModification(selectedOptionsObj.getSelectedQuantity(), this.quantityBreaksSubtotal);

		for(var i:Number = 0; i<this.options.length; i++){

			// Skip Empty Options
			if(this.options[i].choices.length == 0)
				continue;

			var selectedChoiceName = selectedOptionsObj.getChoiceSelected(this.options[i].optionName);

			if(selectedChoiceName == null)
			{
				trace("Error in Product.getSubtotal. The option Name was not found within the selection: " + this.options[i].optionName);
				return;
			}

			// Select the right choice... and make sure all of the other choices are not selected.
			for(var x:Number = 0; x<this.options[i].choices.length; x++)
			{

				if(this.options[i].choices[x].choiceName == selectedChoiceName)
				{
					totalBaseChanges += this.options[i].choices[x].getBasePriceChange(selectedOptionsObj.getSelectedQuantity());
					totalSubtotalChanges += this.options[i].choices[x].getSubtotalPriceChange(selectedOptionsObj.getSelectedQuantity());
					break;
				}
			}
		}
		

		// Find out if any invalid options/choices were set.
		// We want to trace( our developers that they made a mistake or that the Product Options in the DB have changed.
		var allOptionsAndChoicesFoundFlag = true;

		var optionNamesArr = selectedOptionsObj.getOptionNames();
		for(var i:Number = 0; i<optionNamesArr.length; i++){

			var thisOptionName = optionNamesArr[i];
			var thisChoiceName = selectedOptionsObj.getChoiceSelected(thisOptionName);

			var thisOptionAndChoiceFound = false;

			for(var x:Number = 0; x<this.options.length; x++){

				if(thisOptionAndChoiceFound)
					continue;

				if(this.options[x].optionName == thisOptionName)
				{
					for(var j:Number = 0; j<this.options[x].choices.length; j++)
					{
						if(this.options[x].choices[j].choiceName == thisChoiceName)
						{	
							thisOptionAndChoiceFound = true;
							break;
						}
					}
				}
			}

			if(!thisOptionAndChoiceFound)
				allOptionsAndChoicesFoundFlag = false;
		}

		// Rather than trace( an Error, just return N/A
		if(!allOptionsAndChoicesFoundFlag)
		{
			trace("Not all of the Options were found");
			return 0;
		}

		return totalSubtotalChanges + totalBaseChanges * selectedOptionsObj.getSelectedQuantity();
	}




}


