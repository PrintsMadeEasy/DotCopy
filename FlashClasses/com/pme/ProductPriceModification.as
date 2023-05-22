
class ProductPriceModification
{

	// Pass in a Quantity to get a Price Modification for.
	// Expects to get a string in the Format like "20^0.10|100^0.20|500^0.30";
	// Price Groups separated by Pipe Symbols... and the quantity Amount is separated from the Price change with carrot.
	static function getLastPriceModification(quantityToCheck, quantityBreaksStr):Number
	{

		quantityToCheck = parseInt(quantityToCheck);

		if(quantityBreaksStr == "" || quantityToCheck==0)
			return 0;

		var breaksArr:Array = new Array();
		breaksArr = quantityBreaksStr.split("|");

		var lastMatchedAmount:Number = 0;
		for(var breakCounter:Number=0; breakCounter<breaksArr.length; breakCounter++)
		{

			var quanPriceArr:Array = new Array();
			quanPriceArr = breaksArr[breakCounter].split("^");

			if(quanPriceArr.length != 2)
			{
				trace("Problem in function ProductPriceModification.getLastPriceModification for the string: " + quantityBreaksStr);
				return;
			}

			var quanChk:Number = parseInt(quanPriceArr[0]);


			var quanPrcChng:Number = parseFloat(quanPriceArr[1]);
			

			// Only use the last match.
			if(quanChk <= quantityToCheck)
				lastMatchedAmount = quanPrcChng;
		}

		return lastMatchedAmount;
	}
}

