


class ProductOptionChoice
{

	// Define Properties of this class.
	public var choiceName:String;
	public var choiceAlias:String;
	public var choiceDescription:String;
	public var choiceDescriptionIsHTML:Boolean;
	public var choiceIsHiddenFlag:Boolean
	public var changeArtworkSides:Number;
	public var baseChange:Number;
	public var subtotalChange:Number;
	public var baseWeightChange:Number;
	public var projectWeightChange:Number;
	
	
	// Prices for each vendor are separated by @ symbols as parrallel Array to Vendor Names.
	public var vendorBaseChanges:String;
	public var vendorSubtotalChanges:String;
	
	
	// Quantity Breaks in the Format "20^0.20|100^0.40|500^0.60";
	public var quantityBreaksBase:String;
	public var quantityBreaksSubtotal:String;
	
	
	// Multiple Vendors are separated by @ symbols as a parralell array to Product Vendor Names.
	// Quantity Breaks in the Format "20^0.20@0.23@0.25|100^0.40@0.43@0.45|500^0.50@0.53@0.55";
	public var vendorQuantityBreaksBase:String;
	public var vendorQuantityBreaksSubtotal:String;
	


	// Contstructor
	public function ProductOptionChoice()
	{
		this.choiceName = "Choice Name Undefined";
		this.choiceAlias = "Choice Alias Undefined";
		this.choiceDescription = "Choice Description Undefined";
		this.choiceDescriptionIsHTML = false;
		this.choiceIsHiddenFlag = false;
		this.changeArtworkSides = 0;
		this.baseChange = 0;
		this.subtotalChange = 0;
		this.baseWeightChange = 0;
		this.projectWeightChange = 0;
	}


	// It will return the Base Price for the given choice
	// Base Price Changes include the Regular Price Changes, in addition to any Quantity Price Breaks on the Option/Choice
	public function getBasePriceChange(quantityToCheck):Number
	{
		return ProductPriceModification.getLastPriceModification(quantityToCheck, this.quantityBreaksBase) + this.baseChange;
	}
	// It will return the Subtotal Change for the given choice based upon the Quantity 
	public function getSubtotalPriceChange(quantityToCheck):Number
	{
		return ProductPriceModification.getLastPriceModification(quantityToCheck, this.quantityBreaksSubtotal) + this.subtotalChange;
	}


	// It will return the Vendor Base Price for the given choice
	// Base Price Changes include the Regular Price Changes, in addition to any Quantity Price Breaks on the Option/Choice
	public function getVendorBasePriceChange(quantityToCheck, vendorNumber):Number
	{
		vendorNumber = parseInt(vendorNumber);

		var vendorBaseChangesArr = this.vendorBaseChanges.split("@");
		if(vendorNumber < 1 || vendorNumber > vendorBaseChangesArr.length)
		{
			trace("Error in method ProductOptionChoice.getVendorBasePriceChange. The Vendor Number is out of range: " + vendorNumber);
			return null;
		}

		return ProductPriceModification.getLastPriceModification(quantityToCheck, this.vendorQuantityBreaksBase, vendorNumber) + parseFloat(vendorBaseChangesArr[(vendorNumber -1)]);
	}

	// It will return the Subtotal Change for the given choice based upon the Quantity 
	public function getVendorSubtotalPriceChange(quantityToCheck, vendorNumber):Number
	{
		vendorNumber = parseInt(vendorNumber);

		var vendorSubtotalChangesArr = this.vendorSubtotalChanges.split("@");
		if(vendorNumber < 1 || vendorNumber > vendorSubtotalChangesArr.length)
		{
			trace("Error in method ProductOptionChoice.getVendorSubtotalPriceChange. The Vendor Number is out of range: " + vendorNumber);
			return null;
		}

		return ProductPriceModification.getLastPriceModification(quantityToCheck, this.vendorQuantityBreaksSubtotal, vendorNumber) + parseFloat(vendorSubtotalChangesArr[(vendorNumber -1)]);
	}

	// Returns the weight change of the thoice
	public function getBaseWeightChange():Number
	{
		return this.baseWeightChange;
	}

	public function getProjectWeightChange():Number
	{
		return this.projectWeightChange;
	}


}
