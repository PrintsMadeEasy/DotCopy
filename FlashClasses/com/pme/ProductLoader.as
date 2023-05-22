

// ProductLoader Class
// Use this to fetch new Product Objects.  It may download information from the server, or it may already have the Product in Cache.
class ProductLoader
{

	private var productIDsQueued:Array;
	private var cachedProductObjectsArr:Array;

	// The following 2 arrays are used if you call the method attachProductLoadedEvent
	private var productLoadingEventsFunctionRefArr:Array;
	

	// A parallel array to let us know what Function References belong to what Product IDs
	private var productLoadingEventsProductsIDsArr:Array; 

	// To prevent multi threading issues if multiple Product definitions are downloading simultaneously.
	private var parsingInProgressFlag:Boolean;

	// Will hold XML responses in a Queue, if there is another XML file being parsed.
	public var parsingXMLresponseQueue:Array;

	// Holds a Single instance to an object of this class. 
	private static var _instanceSingleton:ProductLoader = null;
	
	private var developmentMode:Boolean;
	
	private var basePath:String;
	

	

	// Constructor is private for Singleton
	private function ProductLoader()
	{
		this.productIDsQueued = new Array();
		this.cachedProductObjectsArr = new Array();
		this.productLoadingEventsFunctionRefArr = new Array();
		this.productLoadingEventsProductsIDsArr = new Array(); 
		this.parsingXMLresponseQueue = new Array();
		this.parsingInProgressFlag = false;
		
		// Start out with a relative path
		this.basePath = "";
		
		this.developmentMode = false;
	}
	
	
	// Singleton
	public static function getInstance():ProductLoader {
	
		if(ProductLoader._instanceSingleton == null)
			ProductLoader._instanceSingleton = new ProductLoader();
			
		return ProductLoader._instanceSingleton;
	}

	public function setBasePathForAPI(basePathStr){
		this.basePath = basePathStr;
	}


	// Pass in a product ID and a "function reference" into this function. 
	// No need to call the method loadProductID() if you use this method, it will load it for you. 
	// After the product has finished loading, it will call the function reference (with no parameters). 
	public function attachProductLoadedEvent(productID, functionReference):Void
	{

		// Parallel Arrays
		this.productLoadingEventsProductsIDsArr.push(productID);
		this.productLoadingEventsFunctionRefArr.push(functionReference);


		// We may call the same Product IDs with differnt event attachment.
		// If the communication happens really quickly, then the the subsequent calls may not get fired after the XML gets parsed.
		// If the Product has already been downloaded and initialized... just fire the event attachment immediately.
		if(this.checkIfProductInitialized(productID))
		{
			this.privateMethod_ProductLoadingComplete(productID);
		}
		else{
			this.loadProductID(productID);
		}

	}

	// Subscript to this error event to get notified when a Product has been loaded from the Server and initialized.
	private function privateMethod_ProductLoadingComplete(productID):Void
	{

		// Find out which indexes of function References are subscribed to this Product ID.
		// Call the function reference when we find a match.
		for(var i=0; i<this.productLoadingEventsProductsIDsArr.length; i++)
		{
			if(this.productLoadingEventsProductsIDsArr[i] == productID)
			{
				// Make sure that the same event is not called Twice.
				// Because of ASYNC behavior... this could happen if the same Product ID was used in different places.
				this.productLoadingEventsProductsIDsArr[i] = 0;
				this.productLoadingEventsFunctionRefArr[i].call(this.productLoadingEventsFunctionRefArr[i]);
			}
		}


	}



	// Call this anytime you want to create a new Product Object
	// You must check to see if the product has finished loading before atempting to fetch it (unless you get an Event letting you know it is ready).
	public function loadProductID(productID):Void
	{

		// Don't try to repeatadly load a Product ID
		for(var i=0; i<this.productIDsQueued.length; i++)
		{
			if(this.productIDsQueued[i] == productID)
				return;
		}
		
		this.productIDsQueued.push(productID);	


		var apiURL:String = this.basePath + "api_dot.php?api_version=1.1&command=get_product_definition&product_id=" + productID;


		var xmlLoader:XML = new XML();

		// If we are in development mode, then create our own XML file rather than downloading it.
		if(this.developmentMode){
			xmlLoader.parseXML(this.getDevelopmentModeXML());
		}

		// To scope the callback.
		var instance:ProductLoader = this;
		
		xmlLoader.onLoad = function (success:Boolean):Void {
				
			// To prevent possible collissions, from multi-threaded ProductID calls, we queue up Response XML text if there is a "parsing" in process.  Only parse one product at a time.
			// Push the XML loader reference into the array.
			if(instance.parsingInProgressFlag){
				instance.parsingXMLresponseQueue.push(this);
			}
			else{
				instance.parsingInProgressFlag = true;
				instance.private_parseXMLresponse(this);
			}
		}

		trace("API URL:\n" + apiURL);
		
		
		// If we are in development mode, then create our own XML file rather than downloading it so we don't have to wait for a communication event to finish.
		if(this.developmentMode)
			xmlLoader.onLoad(true);
		else
			xmlLoader.load(apiURL);

	}


	public function onErrorEvent(statusCode, statusDesc):Void
	{
		trace("Remove this Error message and Replace with your own event handler:  \nError Code from API: " + statusCode + " " + statusDesc);

	}


	public function private_parseXMLresponse(xmlResponseObj):Void
	{
	

		// Build a Product Object and fill it from data returned by the server.
		var newProduct = new Product();
	
	
		// For tracking our Sub Nodes into arrays
		var optionIndex:Number = -1;
		var choiceIndex:Number = -1;
	
		// We are looking to get back a node from the server that says <result>OK</result>... if it says Error then look for a corresponding error message.
		var xmlSuccessfulTransfer:String = "UNKNOWN";
		var errorMessage:String = "Unknown Error Occured!";
		var apiVersion:String = "0.0";

		var rootNode:XMLNode;

		rootNode = xmlResponseObj.firstChild;


		// Our first tag could be <?xml version="1.0" ?>
		// If we don't find our server response tag on the first node... try its neigbor.
		if(rootNode.nodeName.toUpperCase() != "RESPONSE")
			rootNode = xmlResponseObj.firstChild.nextSibling;

		// If we still could not find the server response then something may be wrong.
		if(rootNode.nodeName.toUpperCase() != "RESPONSE"){
			trace("Server Communication Error:  A ServerResponse tag was not found.");
			this.onErrorEvent("5101", "There was a communication error with the server.  A ServerResponse tag was not found.");
		}


		for (var aNode:XMLNode = rootNode.firstChild; aNode != null; aNode = aNode.nextSibling) {

			var xmlNodeName:String = aNode.nodeName.toUpperCase();
			var xmlNodeValue:String = aNode.firstChild ? String(aNode.firstChild.nodeValue) : "";

			switch (xmlNodeName) {	

			// RESULT and ERROR_MESSAGE are common to all XML communications.
			case "RESULT" :
				xmlSuccessfulTransfer = xmlNodeValue; break;
			case "ERROR_MESSAGE" :
				errorMessage = xmlNodeValue; break;
			case "API_VERSION" :
				apiVersion = xmlNodeValue; break;
			case "PRODUCT_DEFINITION" :
			
				for (var productDefNode:XMLNode = aNode.firstChild; productDefNode != null; productDefNode = productDefNode.nextSibling) {
				
					var productDefNodeName:String = productDefNode.nodeName.toUpperCase();
					var productDefNodeValue:String = productDefNode.firstChild ? String(productDefNode.firstChild.nodeValue) : "";

					switch (productDefNodeName) {	

					// Product Specific Variables.
					case "PRODUCT_ID" :
						newProduct.productID = parseInt(productDefNodeValue); trace("ProductIDthatIsParsing: " + productDefNodeValue); break;
					case "PRODUCT_TITLE" :
						newProduct.productTitle = productDefNodeValue; break;
					case "PRODUCT_TITLE_EXT" :
						newProduct.productTitleExt = productDefNodeValue; break;
					case "PRODUCT_NAME" :
						newProduct.productName = productDefNodeValue; break;
					case "ARTWORK_SIDES_POSSIBLE_COUNT" :
						newProduct.numberOfArtworkSides = parseInt(productDefNodeValue); break;
					case "ARTWORK_WIDTH_INCHES" :
						newProduct.artworkWidthInches = parseFloat(productDefNodeValue); break;
					case "ARTWORK_HEIGHT_INCHES" :
						newProduct.artworkHeightInches = parseFloat(productDefNodeValue); break;
					case "ARTWORK_BLEED_PICAS" :
						newProduct.artworkBleedPicas = parseFloat(productDefNodeValue); break;
					case "UNIT_WEIGHT" :
						newProduct.unitWeight = parseFloat(productDefNodeValue); break;
					case "BASE_PRICE" :
						newProduct.basePrice = parseFloat(productDefNodeValue); break;
					case "INITIAL_SUBTOTAL" :
						newProduct.initialSubtotal = parseFloat(productDefNodeValue); break;
					case "MAIN_QUANTITY_PRICE_BREAKS_BASE" :
						newProduct.quantityBreaksBase = productDefNodeValue; break;
					case "MAIN_QUANTITY_PRICE_BREAKS_SUBTOTAL" :
						newProduct.quantityBreaksSubtotal = productDefNodeValue; break;
					case "THUMBNAIL_WIDTH_APPROXIMATE" :
						newProduct.thumbnailWidthApprox = parseInt(productDefNodeValue); break;
					case "THUMBNAIL_HEIGHT_APPROXIMATE" :
						newProduct.thumbnailHeightApprox = parseInt(productDefNodeValue); break;
					case "VARIABLE_DATA" :
						newProduct.variableDataFlag = (productDefNodeValue == "yes" ? true: false); break;
					case "MAILING_SERVICES" :
						newProduct.mailingServicesFlag = (productDefNodeValue == "yes" ? true: false); break;
					case "THUMBNAIL_BACKGROUND_EXISTS" :
						newProduct.thumbnailBackgroundFlag = (productDefNodeValue == "yes" ? true: false); break;
					case "THUMBNAIL_COPY_ICON_EXISTS" :
						newProduct.thumbnailCopyIconFlag = (productDefNodeValue == "yes" ? true: false); break;
					case "PRODUCT_IMPORTANCE" :
						newProduct.productImportance = parseInt(productDefNodeValue); break;
					case "COMPATIBLE_PRODUCT_IDS" :
						newProduct.compatibleProductIDsArr = productDefNodeValue.split("|"); break;
					case "DEFAULT_QUANTITY" :
						newProduct.selectedOptionsObj.setQuantity(productDefNodeValue); break;


					// When we come across a new opening "option" tag... increment our varialbe... we can expect more data to come.
					case "OPTIONS" :

						// Loop Through all of the <option> tags (inside of the <options> container.
						for (var optionContainerNode:XMLNode = productDefNode.firstChild; optionContainerNode != null; optionContainerNode = optionContainerNode.nextSibling) {

							// Just in case white space takes up a node, this will skip it.
							var xmlOptionNodeContainerName:String = optionContainerNode.nodeName.toUpperCase();
							if(xmlOptionNodeContainerName != "OPTION")
								continue;

							optionIndex++;
							choiceIndex = -1; // We will have new choices with this Option... so reset the Choice counter.
							newProduct.options[optionIndex] = new ProductOption();


							// Loop through all of the children Nodes, containing information about the Option.
							for (var optionNode:XMLNode = optionContainerNode.firstChild; optionNode != null; optionNode = optionNode.nextSibling) {

								var optionDetailNodeName:String = optionNode.nodeName.toUpperCase();
								var optionDetailNodeValue:String = optionNode.firstChild ? String(optionNode.firstChild.nodeValue) : "";

								switch (optionDetailNodeName) {

								case "OPTION_NAME" :
									newProduct.options[optionIndex].optionName = optionDetailNodeValue; break;
								case "OPTION_ALIAS" :
									newProduct.options[optionIndex].optionAlias = optionDetailNodeValue; break;
								case "OPTION_DESCRIPTION" :
									newProduct.options[optionIndex].optionDescription = optionDetailNodeValue; break;
								case "OPTION_DESCRIPTION_IS_HTML" :
									newProduct.options[optionIndex].optionDescriptionIsHTML = (optionDetailNodeValue == "yes" ? true : false); break;
								case "OPTION_AFFECTS_ARTWORK_SIDES" :
									newProduct.options[optionIndex].affectsArtworkSidesFlag = (optionDetailNodeValue == "yes" ? true : false); break;
								case "OPTION_IS_ADMIN" :
									newProduct.options[optionIndex].adminOptionFlag = (optionDetailNodeValue == "yes" ? true : false); break;


								// When we come across a new opening "choice" tag... increment our variable... we can expect more data to come.
								case "CHOICES" :

									// Loop Through all of the <choice> tags (inside of the <choices> container.
									for (var choiceContainerNode:XMLNode = optionNode.firstChild; choiceContainerNode != null; choiceContainerNode = choiceContainerNode.nextSibling) {

										// Just in case white space takes up a node, this will skip it.
										var xmlChoiceNodeContainerName:String = choiceContainerNode.nodeName.toUpperCase();
										if(xmlChoiceNodeContainerName != "CHOICE")
											continue;

										choiceIndex++;
										newProduct.options[optionIndex].choices[choiceIndex] = new ProductOptionChoice();

										// Loop through all of the children Nodes, containing information about the Option.
										for (var choiceNode:XMLNode = choiceContainerNode.firstChild; choiceNode != null; choiceNode = choiceNode.nextSibling) {

											var choiceDetailNodeName:String = choiceNode.nodeName.toUpperCase();
											var choiceDetailNodeValue:String = choiceNode.firstChild ? String(choiceNode.firstChild.nodeValue) : "";

											switch (choiceDetailNodeName) {

											case "CHOICE_NAME" :
												newProduct.options[optionIndex].choices[choiceIndex].choiceName = choiceDetailNodeValue; break;
											case "CHOICE_ALIAS" :
												newProduct.options[optionIndex].choices[choiceIndex].choiceAlias = choiceDetailNodeValue; break;
											case "CHOICE_DESCRIPTION" :
												newProduct.options[optionIndex].choices[choiceIndex].choiceDescription = choiceDetailNodeValue; break;
											case "CHOICE_DESCRIPTION_IS_HTML" :
												newProduct.options[optionIndex].choices[choiceIndex].choiceDescriptionIsHTML = (choiceDetailNodeValue == "yes" ? true : false); break;
											case "CHOICE_IS_HIDDEN" :
												newProduct.options[optionIndex].choices[choiceIndex].choiceIsHiddenFlag = (choiceDetailNodeValue == "yes" ? true : false); break;
											case "CHANGE_ARTWORK_SIDES" :
												newProduct.options[optionIndex].choices[choiceIndex].changeArtworkSides = parseInt(choiceDetailNodeValue); break;
											case "CHOICE_BASE_PRICE_CHANGE" :
												newProduct.options[optionIndex].choices[choiceIndex].baseChange = parseFloat(choiceDetailNodeValue); break;
											case "CHOICE_SUBTOTAL_CHANGE" :
												newProduct.options[optionIndex].choices[choiceIndex].subtotalChange = parseFloat(choiceDetailNodeValue); break;
											case "CHOICE_BASE_WEIGHT_CHANGE" :
												newProduct.options[optionIndex].choices[choiceIndex].choice_base_weight_change = parseFloat(choiceDetailNodeValue); break;
											case "CHOICE_PROJECT_WEIGHT_CHANGE" :
												newProduct.options[optionIndex].choices[choiceIndex].projectWeightChange = parseFloat(choiceDetailNodeValue); break;
											case "CHOICE_QUANTITY_PRICE_BREAKS_BASE" :
												newProduct.options[optionIndex].choices[choiceIndex].quantityBreaksBase = choiceDetailNodeValue; break;
											case "CHOICE_QUANTITY_PRICE_BREAKS_SUBTOTAL" :
												newProduct.options[optionIndex].choices[choiceIndex].quantityBreaksSubtotal = choiceDetailNodeValue; break;
											case "DEFAULT_CHOICE" :
												if(choiceDetailNodeValue == "yes")
													newProduct.selectedOptionsObj.setOptionChoice(newProduct.options[optionIndex].optionName, newProduct.options[optionIndex].choices[choiceIndex].choiceName);
												break;


											} // Break ouf of the Choice Switch

										} // End For loop of Choice Noces
									} // End For Lopp of Choice Container Tags
									break;
								} // Break out of the Options Switch

							} // End For Loop of Options Nodes
						} // End For Loop of Option Container Tags
						break;
					} // Break out of the Products Definition Switch
					
				} // Break out of the Products Definition Loop

			} // Break out of Root-Level Switch

		} // End For Loop for Product Detail Nodes
	
	
	

		// Find out if the document was not what we expected to receive.
		// If Not, try to get an Error message and send that through the error event.
		if(xmlSuccessfulTransfer != "OK")
		{
			if(errorMessage == "")
				this.onErrorEvent("5101", "Unknown Error Parsing XML Document");
			else
				this.onErrorEvent("5102", errorMessage);

			return;
		}



		this.cachedProductObjectsArr[newProduct.getProductID()] = newProduct;
		this.privateMethod_ProductLoadingComplete(newProduct.getProductID());


		// Now that we are done parsing one Product Object (thread safe).
		// Find out if there are any other XML files in the Queue.
		if(this.parsingXMLresponseQueue.length > 0){
			var nextXMLfileToParse:Object = this.parsingXMLresponseQueue.pop();
			this.private_parseXMLresponse(nextXMLfileToParse);
		}

		this.parsingInProgressFlag = false;

	}







	// check to see if the Product has finished loading... otherwise set an interval to try again.
	public function checkIfProductInitialized(productID):Boolean
	{

		if(typeof this.cachedProductObjectsArr[productID] == 'undefined' || this.cachedProductObjectsArr[productID] == null)
			return false
		else
			return true;
	}

	public function getProductObj(productID):Product
	{

		if(!this.checkIfProductInitialized(productID))
		{
			trace("Error in method getProductObj. You can't call this method until the Object has finished loading.");
			return null;
		}

		return this.cachedProductObjectsArr[productID];

	}
	
	
	private function getDevelopmentModeXML():String
	{
	
		//return '<?xml version="1.0" ?><response><result>OK</result><api_version>1.1</api_version><error_message></error_message><product_definition><product_id>84</product_id><product_title>Business Cards</product_title><product_title_ext></product_title_ext><product_name>Business Cards</product_name><unit_weight>0.0036</unit_weight><base_price>0</base_price><initial_subtotal>0</initial_subtotal><variable_data>no</variable_data><mailing_services>no</mailing_services><artwork_width_inches>3.5</artwork_width_inches><artwork_height_inches>2</artwork_height_inches><artwork_bleed_picas>4</artwork_bleed_picas><artwork_sides_possible_count>2</artwork_sides_possible_count><thumbnail_background_exists>yes</thumbnail_background_exists><thumbnail_copy_icon_exists>yes</thumbnail_copy_icon_exists><product_importance>80</product_importance><compatible_product_ids>78|84|82</compatible_product_ids><thumbnail_width_approximate>260</thumbnail_width_approximate><thumbnail_height_approximate>167</thumbnail_height_approximate><main_quantity_price_breaks_base>20^0|100^0|300^0|500^0|1000^0|2000^0|3000^0|5000^0</main_quantity_price_breaks_base><main_quantity_price_breaks_subtotal>20^2.99|100^14.99|300^34.99|500^44.99|1000^59.99|2000^79.99|3000^99.99|5000^135.99</main_quantity_price_breaks_subtotal><default_quantity>100</default_quantity><product_switches></product_switches><options><option><option_name>Card Stock</option_name><option_alias>Card Stock</option_alias><option_description>&lt;font color=&#039;#660000&#039;&gt;&lt;b&gt;What type of Card Stock to you want?&lt;/b&gt;&lt;/font&gt;&amp;nbsp;&amp;nbsp;&amp;nbsp;&amp;nbsp;&lt;a href=&#039;javascript:Card_Stock_Details();&#039; class=&#039;BlueRedLink&#039;&gt;More Info&lt;/a&gt;</option_description><option_description_is_html>yes</option_description_is_html><option_affects_artwork_sides>no</option_affects_artwork_sides><option_is_admin>no</option_is_admin><choices><choice><choice_name>Glossy</choice_name><choice_alias>Glossy</choice_alias><choice_description>&lt;b&gt;Glossy&lt;/b&gt;: Has a Shiny UV Coating</choice_description><choice_description_is_html>yes</choice_description_is_html><choice_is_hidden>no</choice_is_hidden><change_artwork_sides>0</change_artwork_sides><choice_base_price_change>0</choice_base_price_change><choice_subtotal_change>0</choice_subtotal_change><choice_base_weight_change>0</choice_base_weight_change><choice_project_weight_change>0</choice_project_weight_change><choice_quantity_price_breaks_base>20^0.05|100^0.02|300^0.01|500^0.008|1000^0.005</choice_quantity_price_breaks_base><choice_quantity_price_breaks_subtotal>20^0|100^0|300^0|500^0|1000^0</choice_quantity_price_breaks_subtotal><default_choice>yes</default_choice></choice><choice><choice_name>Standard</choice_name><choice_alias>Standard</choice_alias><choice_description>&lt;b&gt;Standard&lt;/b&gt;: Has a smooth texture with a professional feel.</choice_description><choice_description_is_html>yes</choice_description_is_html><choice_is_hidden>no</choice_is_hidden><change_artwork_sides>0</change_artwork_sides><choice_base_price_change>0</choice_base_price_change><choice_subtotal_change>0</choice_subtotal_change><choice_base_weight_change>0</choice_base_weight_change><choice_project_weight_change>0</choice_project_weight_change><choice_quantity_price_breaks_base></choice_quantity_price_breaks_base><choice_quantity_price_breaks_subtotal></choice_quantity_price_breaks_subtotal><default_choice>no</default_choice></choice></choices></option><option><option_name>Assistance</option_name><option_alias>Assistance</option_alias><option_description>Customer Assistance</option_description><option_description_is_html>no</option_description_is_html><option_affects_artwork_sides>no</option_affects_artwork_sides><option_is_admin>yes</option_is_admin><choices><choice><choice_name>None</choice_name><choice_alias>None</choice_alias><choice_description>None</choice_description><choice_description_is_html>no</choice_description_is_html><choice_is_hidden>yes</choice_is_hidden><change_artwork_sides>0</change_artwork_sides><choice_base_price_change>0</choice_base_price_change><choice_subtotal_change>0</choice_subtotal_change><choice_base_weight_change>0</choice_base_weight_change><choice_project_weight_change>0</choice_project_weight_change><choice_quantity_price_breaks_base></choice_quantity_price_breaks_base><choice_quantity_price_breaks_subtotal></choice_quantity_price_breaks_subtotal><default_choice>yes</default_choice></choice><choice><choice_name>Artwork Adjustment</choice_name><choice_alias>Artwork Adjustment</choice_alias><choice_description>Artwork Adjustment</choice_description><choice_description_is_html>no</choice_description_is_html><choice_is_hidden>no</choice_is_hidden><change_artwork_sides>0</change_artwork_sides><choice_base_price_change>0</choice_base_price_change><choice_subtotal_change>7</choice_subtotal_change><choice_base_weight_change>0</choice_base_weight_change><choice_project_weight_change>0</choice_project_weight_change><choice_quantity_price_breaks_base></choice_quantity_price_breaks_base><choice_quantity_price_breaks_subtotal></choice_quantity_price_breaks_subtotal><default_choice>no</default_choice></choice><choice><choice_name>Photoshop Adjustment</choice_name><choice_alias>Photoshop Adjustment</choice_alias><choice_description>Photoshop Adjustment</choice_description><choice_description_is_html>no</choice_description_is_html><choice_is_hidden>no</choice_is_hidden><change_artwork_sides>0</change_artwork_sides><choice_base_price_change>0</choice_base_price_change><choice_subtotal_change>15</choice_subtotal_change><choice_base_weight_change>0</choice_base_weight_change><choice_project_weight_change>0</choice_project_weight_change><choice_quantity_price_breaks_base></choice_quantity_price_breaks_base><choice_quantity_price_breaks_subtotal></choice_quantity_price_breaks_subtotal><default_choice>no</default_choice></choice><choice><choice_name>Custom Design</choice_name><choice_alias>Custom Design</choice_alias><choice_description>Custom Design</choice_description><choice_description_is_html>no</choice_description_is_html><choice_is_hidden>no</choice_is_hidden><change_artwork_sides>0</change_artwork_sides><choice_base_price_change>0</choice_base_price_change><choice_subtotal_change>45</choice_subtotal_change><choice_base_weight_change>0</choice_base_weight_change><choice_project_weight_change>0</choice_project_weight_change><choice_quantity_price_breaks_base></choice_quantity_price_breaks_base><choice_quantity_price_breaks_subtotal></choice_quantity_price_breaks_subtotal><default_choice>no</default_choice></choice><choice><choice_name>Artwork Rebuild</choice_name><choice_alias>Artwork Rebuild</choice_alias><choice_description>Artwork Rebuild</choice_description><choice_description_is_html>no</choice_description_is_html><choice_is_hidden>no</choice_is_hidden><change_artwork_sides>0</change_artwork_sides><choice_base_price_change>0</choice_base_price_change><choice_subtotal_change>30</choice_subtotal_change><choice_base_weight_change>0</choice_base_weight_change><choice_project_weight_change>0</choice_project_weight_change><choice_quantity_price_breaks_base></choice_quantity_price_breaks_base><choice_quantity_price_breaks_subtotal></choice_quantity_price_breaks_subtotal><default_choice>no</default_choice></choice></choices></option><option><option_name>Style</option_name><option_alias>Style</option_alias><option_description>Do you want information printed on the Back?</option_description><option_description_is_html>no</option_description_is_html><option_affects_artwork_sides>yes</option_affects_artwork_sides><option_is_admin>no</option_is_admin><choices><choice><choice_name>Single-Sided</choice_name><choice_alias>Single-Sided</choice_alias><choice_description>Single-Sided</choice_description><choice_description_is_html>no</choice_description_is_html><choice_is_hidden>no</choice_is_hidden><change_artwork_sides>1</change_artwork_sides><choice_base_price_change>0</choice_base_price_change><choice_subtotal_change>0</choice_subtotal_change><choice_base_weight_change>0</choice_base_weight_change><choice_project_weight_change>0</choice_project_weight_change><choice_quantity_price_breaks_base></choice_quantity_price_breaks_base><choice_quantity_price_breaks_subtotal></choice_quantity_price_breaks_subtotal><default_choice>yes</default_choice></choice><choice><choice_name>Double-Sided</choice_name><choice_alias>Double-Sided</choice_alias><choice_description>Double Sided</choice_description><choice_description_is_html>no</choice_description_is_html><choice_is_hidden>no</choice_is_hidden><change_artwork_sides>2</change_artwork_sides><choice_base_price_change>0</choice_base_price_change><choice_subtotal_change>0</choice_subtotal_change><choice_base_weight_change>0</choice_base_weight_change><choice_project_weight_change>0</choice_project_weight_change><choice_quantity_price_breaks_base>20^0|100^0|300^0|500^0|1000^0|2000^0|3000^0|5000^0</choice_quantity_price_breaks_base><choice_quantity_price_breaks_subtotal>20^1|100^3|300^7|500^8|1000^15|2000^25|3000^36|5000^50</choice_quantity_price_breaks_subtotal><default_choice>no</default_choice></choice></choices></option></options></product_definition></response>		<choice_description_is_html>no</choice_description_is_html>				<choice_is_hidden>no</choice_is_hidden>				<change_artwork_sides>0</change_artwork_sides>				<choice_base_price_change>0</choice_base_price_change>				<choice_subtotal_change>30</choice_subtotal_change>				<choice_base_weight_change>0</choice_base_weight_change>				<choice_project_weight_change>0</choice_project_weight_change>				<choice_quantity_price_breaks_base></choice_quantity_price_breaks_base>					<choice_quantity_price_breaks_subtotal></choice_quantity_price_breaks_subtotal>					<default_choice>no</default_choice></choice></choices>			</option>			<option>				<option_name>Style</option_name>			<option_alias>Style</option_alias>			<option_description>Do you want information printed on the Back?</option_description>			<option_description_is_html>no</option_description_is_html>			<option_affects_artwork_sides>yes</option_affects_artwork_sides>			<option_is_admin>no</option_is_admin>			<choices>			<choice>				<choice_name>Single-Sided</choice_name>				<choice_alias>Single-Sided</choice_alias>				<choice_description>Single-Sided</choice_description>				<choice_description_is_html>no</choice_description_is_html>				<choice_is_hidden>no</choice_is_hidden>				<change_artwork_sides>1</change_artwork_sides>				<choice_base_price_change>0</choice_base_price_change>				<choice_subtotal_change>0</choice_subtotal_change>				<choice_base_weight_change>0</choice_base_weight_change>				<choice_project_weight_change>0</choice_project_weight_change>				<choice_quantity_price_breaks_base></choice_quantity_price_breaks_base>					<choice_quantity_price_breaks_subtotal></choice_quantity_price_breaks_subtotal>					<default_choice>yes</default_choice></choice><choice>				<choice_name>Double-Sided</choice_name>				<choice_alias>Double-Sided</choice_alias>				<choice_description>Double Sided</choice_description>				<choice_description_is_html>no</choice_description_is_html>				<choice_is_hidden>no</choice_is_hidden>				<change_artwork_sides>2</change_artwork_sides>				<choice_base_price_change>0</choice_base_price_change>				<choice_subtotal_change>0</choice_subtotal_change>				<choice_base_weight_change>0</choice_base_weight_change>				<choice_project_weight_change>0</choice_project_weight_change>				<choice_quantity_price_breaks_base>20^0|100^0|300^0|500^0|1000^0|2000^0|3000^0|5000^0</choice_quantity_price_breaks_base>					<choice_quantity_price_breaks_subtotal>20^1|100^3|300^7|500^8|1000^15|2000^25|3000^36|5000^50</choice_quantity_price_breaks_subtotal>					<default_choice>no</default_choice></choice></choices>			</option>			</options>	</product_definition>	</response>';
		return "";
	}


}
