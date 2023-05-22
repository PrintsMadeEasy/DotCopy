using System;
using System.Xml;
using System.Web;
using System.Collections;


namespace PME_Link
{

	/// <summary>
	/// API to the server.  Can change status, get artwork, invoices, etc.
	/// </summary>
	public class ServerAPI
	{
		private string apiURL;
		private string apiCommand;
		private string userName;
		private string passwd;
		private string projNum;
		private string prodctNum;
		private string note;
		private string statusChar;
		private string statusCharChange;
		private string orderNum;
		private bool errorFlag;
		private string errorText;
		private string curTag;
		private string invcURL;
		private string promoComnd;
		private string promoArtworkURL;
		private ApplicationSettings appSettings;

		// Holds data returned from the API
		private string apiResult;
		private string apiMessage;
		private Hashtable apiProjectHash;
		private Hashtable apiRackHash;
		private Hashtable apiShipments;
		private string currentShipmentID;
		private string currentShipmentProjectID;
		private string promoCmdSrvDelg;
		private string shippingInstructions;


		public ServerAPI( ApplicationSettings appSet )
		{
			this.userName = appSet.UserName;
			this.passwd = appSet.Password;
			this.apiURL = appSet.APIurl;
			this.appSettings = appSet;
			this.invcURL = "";
			this.promoArtworkURL = "";
			this.promoComnd = "";
		}

		public void FireRequest()
		{
			// Start off with an error, let it prove otherwise
			this.errorFlag = true;
			this.errorText = "XML communication has not been intialized.";
			this.apiResult = "";
			this.apiMessage = "";
			this.curTag = "";
			this.apiProjectHash = new Hashtable();
			this.apiRackHash = new Hashtable();
			this.apiShipments = new Hashtable();
			this.currentShipmentID = "";
			this.currentShipmentProjectID = "";
			this.note = "";
			this.promoCmdSrvDelg = "";
			this.shippingInstructions = "";

			try
			{

				XmlTextReader reader = new XmlTextReader(this.GetAPIurl());

				string contents = "";
				string carrot_str = "";

				while (reader.Read()) 
				{

					// When we find a new "start tag"... add to the heirarchy (separated by carrots)
					// When we find a new "end tag" ... strip off the last tag, from the last carrot
					if( reader.NodeType == System.Xml.XmlNodeType.Element )
					{
						this.curTag += "^" + reader.Name;
					}
					if( reader.NodeType == System.Xml.XmlNodeType.EndElement )
					{
						carrot_str += this.curTag + "\n";
						int carrotPos = this.curTag.LastIndexOf("^");
						this.curTag = this.curTag.Substring( 0, carrotPos );
					}
					// End Tag Control


					// Look For tags that may contain attributes
					if (reader.NodeType == System.Xml.XmlNodeType.Element)
					{
						switch( this.curTag )
						{
							case "^server_response^project":
								if( reader.HasAttributes )
								{
									reader.MoveToAttribute(0);
									this.apiProjectHash.Add( "id", reader.Value );
								}
								break;
							case "^server_response^project^status":
								if( reader.HasAttributes )
								{
									reader.MoveToAttribute(0);
									this.apiProjectHash.Add( "status_description", reader.Value );
								}
								break;

							case "^server_response^shipment":
								if( reader.HasAttributes )
								{
									reader.MoveToAttribute(0);

									this.currentShipmentID = reader.Value;
									ShipmentDetail shipmentObj = new ShipmentDetail();

									shipmentObj.ShipmentID = this.currentShipmentID;

									// add a new shipmentDetail class to the shipment hash, using the shipment ID as the
									this.apiShipments.Add( this.currentShipmentID, shipmentObj );

								}
								else
								{
									Form1.ShowError( "Error with the shipment ID tag." );
									return;
								}
								break;
							case "^server_response^shipment^project":
								if( reader.HasAttributes )
								{
									reader.MoveToAttribute(0);

									this.currentShipmentProjectID = reader.Value;

									// Add the project ID to the current shipment detail
									ShipmentDetail shipObj = (ShipmentDetail) this.apiShipments[this.currentShipmentID];
									shipObj.AddProjectNumberToShipment( this.currentShipmentProjectID );
								}
								break;
							default:
								break;
						}
					}

					// Gather the text from inside all of the tags
					if (reader.NodeType == System.Xml.XmlNodeType.Text)
					{
						contents += reader.Value + "\n";

						switch( this.curTag )
						{
							// This may give us sucess or failure, and any message that may go with either
							case "^server_response^result":
								this.apiResult = reader.Value;
								break;
							case "^server_response^message":
								this.apiMessage = reader.Value;
								break;
							case "^server_response^promo_command_delegate":
								this.promoCmdSrvDelg = reader.Value;
								break;


							// For Shipment Details
							case "^server_response^shipment^package_weight":
								ShipmentDetail shipObj = (ShipmentDetail) this.apiShipments[this.currentShipmentID];
								shipObj.Weight = reader.Value;
								break;
							case "^server_response^shipment^order_id":
								ShipmentDetail shipObj2 = (ShipmentDetail) this.apiShipments[this.currentShipmentID];
								shipObj2.OrderNumber = reader.Value;
								break;
							case "^server_response^shipment^shipping_method_code":
								ShipmentDetail shipObj3 = (ShipmentDetail) this.apiShipments[this.currentShipmentID];
								shipObj3.ShippingMethodCode = reader.Value;
								break;
							case "^server_response^shipment^shipping_method_desc":
								ShipmentDetail shipObj4 = (ShipmentDetail) this.apiShipments[this.currentShipmentID];
								shipObj4.ShippingMethodDesc = reader.Value;
								break;
							case "^server_response^shipment^shipping_carrier":
								ShipmentDetail shipObj5 = (ShipmentDetail) this.apiShipments[this.currentShipmentID];
								shipObj5.ShippingCarrier = reader.Value;
								break;
							case "^server_response^shipment^shipping_alert_message":
								ShipmentDetail shipObj6 = (ShipmentDetail) this.apiShipments[this.currentShipmentID];
								shipObj6.ShippingAlertMessage = reader.Value;
								break;
							case "^server_response^shipment^ship_to^country":
								ShipmentDetail shipObj7 = (ShipmentDetail) this.apiShipments[this.currentShipmentID];
								shipObj7.ShipCountryCode = reader.Value;
								break;

							// For invoices
							case "^server_response^invoice_url":
								this.invcURL = reader.Value;
								break;

							// For Promotional Artwork URL's
							case "^server_response^promo_artwork_url":
								this.promoArtworkURL = reader.Value;
								break;

							// Shipping Instructions
							case "^server_response^shipping_instructions":
								this.shippingInstructions = reader.Value;
								break;
								
								// For Project details
							case "^server_response^project^product_id":
								this.apiProjectHash.Add( "product_id", reader.Value );
								break;
							case "^server_response^project^quantity":
								this.apiProjectHash.Add( "quantity", reader.Value );
								break;
							case "^server_response^project^status":
								this.apiProjectHash.Add( "status", reader.Value );
								break;
							case "^server_response^project^order_id":
								this.apiProjectHash.Add( "order_id", reader.Value );
								break;
							case "^server_response^project^ship_carrier":
								this.apiProjectHash.Add( "ship_carrier", reader.Value );
								break;
							case "^server_response^project^ship_method":
								this.apiProjectHash.Add( "ship_method", reader.Value );
								break;
							case "^server_response^project^ship_method_description":
								this.apiProjectHash.Add( "ship_method_description", reader.Value );
								break;
							case "^server_response^project^ship_priority":
								this.apiProjectHash.Add( "ship_priority", reader.Value );
								break;
							case "^server_response^project^date_ordered":
								this.apiProjectHash.Add( "date_ordered", reader.Value );
								break;
							case "^server_response^project^notes":
								this.apiProjectHash.Add( "notes", reader.Value );
								break;
							case "^server_response^project^order_description":
								this.apiProjectHash.Add( "order_description", reader.Value );
								break;
							case "^server_response^project^options_description":
								this.apiProjectHash.Add( "options_description", reader.Value );
								break;
							case "^server_response^project^ship_country_code":
								this.apiProjectHash.Add( "ship_country_code", reader.Value );
								break;


								// For Rack information
							case "^server_response^rack_control^projects_in_order_in_product_group":
								this.apiRackHash.Add( "projects_in_order_in_product_group", reader.Value );
								break;
							case "^server_response^rack_control^project_is_on_rack":
								this.apiRackHash.Add( "project_is_on_rack", reader.Value );
								break;
							case "^server_response^rack_control^projects_on_rack_from_order":
								this.apiRackHash.Add( "projects_on_rack_from_order", reader.Value );
								break;
							case "^server_response^rack_control^boxes_on_rack_from_order_in_product_group":
								this.apiRackHash.Add( "boxes_on_rack_from_order_in_product_group", reader.Value );
								break;
							case "^server_response^rack_control^boxes_for_project":
								this.apiRackHash.Add( "boxes_for_project", reader.Value );
								break;
							case "^server_response^rack_control^boxes_for_order_in_product_group":
								this.apiRackHash.Add( "boxes_for_order_in_product_group", reader.Value );
								break;
							case "^server_response^rack_control^location^rack_number":
								this.apiRackHash.Add( "rack_number", reader.Value );
								break;
							case "^server_response^rack_control^location^rack_text":
								this.apiRackHash.Add( "rack_text", reader.Value );
								break;
							case "^server_response^rack_control^location^row_number":
								this.apiRackHash.Add( "row_number", reader.Value );
								break;
							case "^server_response^rack_control^location^row_text":
								this.apiRackHash.Add( "row_text", reader.Value );
								break;
							case "^server_response^rack_control^location^column_number":
								this.apiRackHash.Add( "column_number", reader.Value );
								break;
							case "^server_response^rack_control^location^column_text":
								this.apiRackHash.Add( "column_text", reader.Value );
								break;
							default:
								break;
						}
					}
				}

				// We should always get back a tag that says the result is OK, no matter what API command is issued
				// Otherwise we know an error occured.
				if( this.apiResult == "OK" )
				{
					this.errorFlag = false;
					this.errorText = "";
				}
				else
				{
					if( this.apiMessage != "" )
						this.errorText = this.apiMessage;
				}

			}
			catch ( System.Exception e )
			{
				this.errorFlag = true;
				this.errorText = "A communication error has occured.  Please ensure that you have not lost your internet connection.<br>" + e.Message;
			}
		}


		// Get a URL for issuing to the API depending on what properties have been set.
		private string GetAPIurl()
		{
			string commandURL = this.apiURL + "?username=" + this.userName + "&password=" + this.passwd + "&command=" + this.apiCommand;

			if( this.apiCommand == "get_project_info" )
				commandURL += "&project_number=" + this.projNum;
			else if( this.apiCommand == "set_production_note" )
				commandURL += "&project_number=" + this.projNum + "&production_note=" + HttpUtility.UrlEncode(this.note);
			else if( this.apiCommand == "get_promo_artwork" )
				commandURL += "&project_number=" + this.projNum + "&promo_command=" + HttpUtility.UrlEncode(this.promoComnd);
			else if( this.apiCommand == "returned_package" )
				commandURL += "&project_number=" + this.projNum;
			else if( this.apiCommand == "change_project_status" )
				commandURL += "&project_number=" + this.projNum + "&oldstatus=" + this.statusChar + "&status=" + this.statusCharChange;
			else if( this.apiCommand == "get_invoice" )
			{
				// Find out from our application settings if we are supposed to print a background to the invoice
				string noInvoiceBack;
				if( this.appSettings.showInvoiceBackground )
					noInvoiceBack = "";
				else
					noInvoiceBack = "true";

				commandURL += "&project_number=" + this.projNum + "&order_number=" + this.orderNum + "&noborders=" + noInvoiceBack;
			}
			else if( this.apiCommand == "get_shipments_for_order" )
				commandURL += "&order_number=" + this.orderNum + "&productid=" + this.prodctNum + "&project_number=" + this.projNum;
			else if( this.apiCommand == "production_scan" )
				commandURL += "&project_number=" + this.projNum;
			else if( this.apiCommand == "remove_from_rack" )
				commandURL += "&project_number=" + this.projNum;
			else
			{
				Form1.ShowError("The API command has not been defined: " + this.apiCommand );
				commandURL = "an error has occured";
			}

			return commandURL;
		}

		// Returns all shipment ID's that have the given project associated with it
		public ArrayList GetShipmentIDsForProject( string projNum )
		{
			ArrayList retArr = new ArrayList();

			ICollection shipIDkeys = this.apiShipments.Keys;
			foreach ( string shipKey in shipIDkeys )
			{
				ShipmentDetail shipObj = (ShipmentDetail) this.apiShipments[shipKey];
				if( shipObj.CheckIfProjectIsInShipment( projNum ) )
					retArr.Add( shipObj.ShipmentID );
			}

			return retArr;
		}

		// Returns all shipment ID's from the last API call
		public ArrayList GetShipmentIDs()
		{
			ArrayList retArr = new ArrayList();

			ICollection shipIDkeys = this.apiShipments.Keys;
			foreach ( string shipKey in shipIDkeys )
					retArr.Add( shipKey );

			return retArr;
		}

		// Returns all shipment ID's from the last API call
		public ShipmentDetail GetShipmentDetail( string shpID )
		{
			if( this.apiShipments.ContainsKey(shpID) )
				return (ShipmentDetail) this.apiShipments[shpID];
			else
			{
				Form1.ShowError( "Shipment ID does not exist." );
				return new ShipmentDetail();
			}
		}

		public string GetShippingInstructions ()
		{
			return this.shippingInstructions;
		}

		#region Properties

		public bool error
		{
			get
			{
				return this.errorFlag;
			}
		}
		public string errorDescription
		{
			get
			{
				return this.errorText;
			}
		}
		public string invoiceURL
		{
			get
			{
				return this.invcURL;
			}
		}

		public string promoArtURL
		{
			get
			{
				return this.promoArtworkURL;
			}
		}

		public string promoCommandDelegateFromServer
		{
			get
			{

				return this.promoCmdSrvDelg;
			}
		}

		public string message
		{
			get
			{
				return this.apiMessage;
			}
		}
		
		public Hashtable projectInfo
		{
			get
			{
				if(!this.apiProjectHash.ContainsKey("notes"))
					this.apiProjectHash.Add("notes", "");

				return this.apiProjectHash;
			}
		}
		public Hashtable rackInfo
		{
			get
			{
				return this.apiRackHash;
			}
		}

		public string command
		{
			get
			{
				return this.apiCommand;
			}
			set
			{
				this.apiCommand = value;
			}
		}
		public string projectNumber
		{
			get
			{
				return this.projNum;
			}
			set
			{
				this.projNum = value;
			}
		}
		public string promoCommand
		{
			get
			{
				return this.promoComnd;
			}
			set
			{
				this.promoComnd = value;
			}
		}
		public string productNumber
		{
			get
			{
				return this.prodctNum;
			}
			set
			{
				this.prodctNum = value;
			}
		}
		public string orderNumber
		{
			get
			{
				return this.orderNum;
			}
			set
			{
				this.orderNum = value;
			}
		}
		public string productionNote
		{
			get
			{
				return this.note;
			}
			set
			{
				this.note = value;
			}
		}
		public string status
		{
			set
			{
				this.statusChar = value;
			}
		}
		public string statusChange
		{
			set
			{
				this.statusCharChange = value;
			}
		}

		#endregion
	}

	public class ShipmentDetail
	{
		private string packageWeight;
		private string shipID;
		private string orderID;
		private string shipCountry;
		private string carrier;
		private string methodCode;
		private string methodDesc;
		private string alertMessage;
		private ArrayList projectNumbers;

		public ShipmentDetail()
		{
			this.projectNumbers = new ArrayList();
			this.packageWeight = "";
			this.shipID = "";
		}
		public void AddProjectNumberToShipment( string projNum )
		{
			this.projectNumbers.Add( projNum );
		}
		public bool CheckIfProjectIsInShipment( string projNum )
		{
			return this.projectNumbers.Contains( projNum );
		}
		public string Weight
		{
			get
			{
				return this.packageWeight;
			}
			set
			{
				this.packageWeight = value;
			}
		}
		public string ShipmentID
		{
			get
			{
				return this.shipID;
			}
			set
			{
				this.shipID = value;
			}
		}
		public string OrderNumber
		{
			get
			{
				return this.orderID;
			}
			set
			{
				this.orderID = value;
			}
		}
		public string ShippingCarrier
		{
			get
			{
				return this.carrier;
			}
			set
			{
				this.carrier = value;
			}
		}
		public string ShippingMethodCode
		{
			get
			{
				return this.methodCode;
			}
			set
			{
				this.methodCode = value;
			}
		}
		public string ShippingMethodDesc
		{
			get
			{
				return this.methodDesc;
			}
			set
			{
				this.methodDesc = value;
			}
		}
		public string ShippingAlertMessage
		{
			get
			{
				return this.alertMessage;
			}
			set
			{
				this.alertMessage = value;
			}
		}
		public string ShipCountryCode
		{
			get
			{
				return this.shipCountry;
			}
			set
			{
				this.shipCountry = value;
			}
		}
	}

}
