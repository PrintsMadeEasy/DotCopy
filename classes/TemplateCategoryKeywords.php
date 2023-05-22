<?


// When browsing templates, people should be able to provide a category name, and have that translate into a bunch of keywords in our search engine.
class TemplateCategoryKeywords {

	private $categoryNames = array();

	function __construct(){

			$this->categoryNames["RealEstate"] = array("realtor", "estate", "mortgage", "insurance", "real", "home");
			$this->categoryNames["Home"] = array("home", "architect", "chimney", "housekeeping", "handyman", "interior", "lumber", "pool", "carpet", "electrician", "electric", "flooring", "floor", "garden", "landscape", "landscaping", "heat", "air", "a/c", "locksmith", "plubmer", "plumbing", "roof", "contracting", "contractor", "tile", "pest", "paving", "pave", "kitchen", "bath", "window", "door", "woodwork", "carpenter", "blinds", "shades", "drywall", "inspection", "marble", "granite");
			$this->categoryNames["Automotive"] = array("auto", "automobile", "car", "transmission", "mechanic", "motorcycle", "tire", "muffler");
			$this->categoryNames["Beauty"] = array("salon", "spa", "manicure", "nail", "cosmetic", "massage", "tan", "tanning", "flower", "yoga");
			$this->categoryNames["Business"] = array("accounting", "accountant", "insurance", "lawyer", "bail", "bonds", "secretarial", "secretary", "typing", "bookkeeping", "messenger", "computer", "office", "sign", "print", "storage");
			$this->categoryNames["Health"] = array("physician", "dentist", "medical", "chiropractor", "vitamin", "health", "pilates", "gym", "yoga", "fitness", "trainer", "teeth", "veterinarian");
			$this->categoryNames["Personal"] = array("housekeeping", "personal", "trainer", "child", "childcare", "music", "tutor", "tattoo", "computer", "laundry", "clean", "storage");
			$this->categoryNames["Transportation"] = array("taxi", "limousine", "truck", "moving", "move", "travel", "aviation", "car", "plane", "airplane", "jet");
			$this->categoryNames["Wedding"] = array("party", "cater", "wedding", "limousine", "entertainment", "invitations", "invites", "save-the-date", "date", "photography", "photographer", "church", "churches", "travel");
			$this->categoryNames["Technology"] = array("computer", "technician", "network", "telephone", "internet", "telephone", "graphics");
			$this->categoryNames["Apparel"] = array("clothing", "clothes", "shoe", "handbag", "boutique", "jewelry", "tailor", "seamstress");
			$this->categoryNames["Food"] = array("restaurant", "bar", "cocktail", "bartender", "donuts", "food", "beverage", "vending", "donut", "bakery", "chocolate", "deli", "market", "seafood", "coffee");
			$this->categoryNames["Creative"] = array("music", "paint", "ceramic", "sculpture", "handicraft", "literature", "dance");
			$this->categoryNames["Nature"] = array("nature", "flower", "water", "climbing", "hiking", "lake", "mountain");
			$this->categoryNames["Animals"] = array("dog", "cat", "bird", "pet", "animal");
			$this->categoryNames["Modern"] = array("modern", "internet", "network", "technology");
			$this->categoryNames["Patriotic"] = array("flag", "america", "american", "patriotic");
			$this->categoryNames["Holiday"] = array("holiday", "christmas", "halloween", "thanksgiving", "hanukkah");
			$this->categoryNames["Sports"] = array("basketball", "baseball", "bowling", "tennis", "soccer", "football", "golf");
			$this->categoryNames["Hobbies"] = array("music", "art", "literature", "aviation", "dance", "train", "puzzle", "game", "travel", "craft");
			$this->categoryNames["Popular"] = array("popular");
	}



	function checkIfCategoryExists($catName){
		
		if(!array_key_exists($catName, $this->categoryNames))
			return false;
		else
			return true;
	}
	
	function getKeywordsFromCategoryName($catName){
		
		if(!$this->checkIfCategoryExists($catName))
			return "";
			
		return implode(" ", $this->categoryNames[$catName]);
		
	}

}






?>
