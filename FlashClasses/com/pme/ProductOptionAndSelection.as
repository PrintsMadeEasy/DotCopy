// ---- Option and Selection Class
// ---- A container class to avoid using hashes

class ProductOptionAndSelection {

	public var optionName:String;
	public var choiceName:String;

	// Constructer fills up both the Option and Choic name in public vars.
	public function ProductOptionAndSelection(optName, selChoice)
	{
		this.optionName = optName;
		this.choiceName = selChoice
	}

}