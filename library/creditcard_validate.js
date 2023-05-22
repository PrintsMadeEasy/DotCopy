
function allDigits(str)
{
	return inValidCharSet(str,"0123456789");
}

function inValidCharSet(str,charset)
{
	var result = true;

	for (var i=0;i<str.length;i++)
		if (charset.indexOf(str.substr(i,1))<0)
		{
			result = false;
			break;
		}

	return result;
}



function isValidCreditCardNumber(ccNum, ccType)
{
	var result = false;

	//strip out dashes or blank spaces
	ccNum = ccNum.replace(/\s/g, "");
	ccNum = ccNum.replace(/-/g, "");

  	if (ccNum.length>0)
 	{
		if (LuhnCheck(ccNum) && validateCCNum(ccType,ccNum))
		{
			result = true;
		}
	}

	return result;
}

function LuhnCheck(str)
{
  var result = true;

  var sum = 0;
  var mul = 1;
  var strLen = str.length;

  for (i = 0; i < strLen; i++)
  {
    var digit = str.substring(strLen-i-1,strLen-i);
    var tproduct = parseInt(digit ,10)*mul;
    if (tproduct >= 10)
      sum += (tproduct % 10) + 1;
    else
      sum += tproduct;
    if (mul == 1)
      mul++;
    else
      mul--;
  }
  if ((sum % 10) != 0)
    result = false;

  return result;
}




function validateCCNum(cardType,cardNum)
{
	var result = false;
	cardType = cardType.toUpperCase();

	var cardLen = cardNum.length;
	var firstdig = cardNum.substring(0,1);
	var seconddig = cardNum.substring(1,2);
	var first4digs = cardNum.substring(0,4);

	switch (cardType)
	{
		case "VISA":
			result = ((cardLen == 16) || (cardLen == 13)) && (firstdig == "4");
			break;
		case "AMEX":
			var validNums = "47";
			result = (cardLen == 15) && (firstdig == "3") && (validNums.indexOf(seconddig)>=0);
			break;
		case "MASTERCARD":
			var validNums = "12345";
			result = (cardLen == 16) && (firstdig == "5") && (validNums.indexOf(seconddig)>=0);
			break;
		case "DISCOVER":
			result = (cardLen == 16) && (first4digs == "6011");
			break;
		case "DINERS":
			var validNums = "068";
			result = (cardLen == 14) && (firstdig == "3") && (validNums.indexOf(seconddig)>=0);
			break;
	}
	return result;
}

