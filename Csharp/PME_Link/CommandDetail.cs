using System;
using System.Collections;
using IDAutomation.Windows.Forms.LinearBarCode;
using System.IO;
using System.Net;

namespace PME_Link
{
	public class CommandDetail
	{
		private string cmdDesc;
		private CommandQueue cmdQueue;
		private MessageLog msgLog;
		private CommandType cmdType;  // Will be used to hold a value from the CommandType enumeration
		private string invceURL;
		private string promo1ArtworkURL;
		private ApplicationSettings appSettings;
		private string projectID;
		private string orderID;
		private string prdctID;
		private string statusChar;
		private string statusCharChange;
		private Barcode brCod;
		private string invoicePathLocal;
		private string promo1artworkPDFPathLocal;

		private Form1 form1Obj;



		public CommandDetail( CommandQueue CmdQueueObj, MessageLog MsgLogObj, ApplicationSettings appSet,  Barcode barCodeObj, Form1 formObjectRef )
		{
			this.cmdQueue = CmdQueueObj;
			this.msgLog = MsgLogObj;
			this.cmdDesc = "Error, Command Description has not been set yet.";
			this.appSettings = appSet;
			this.projectID = "";
			this.orderID = "";
			this.prdctID = "";
			this.brCod = barCodeObj;
			this.statusChar = "";
			this.statusCharChange = "";
			this.invoicePathLocal = System.Environment.CurrentDirectory + "\\invoice.pdf";
			this.promo1artworkPDFPathLocal = System.Environment.CurrentDirectory + "\\Promo1.pdf";
			this.form1Obj = formObjectRef;
		}

		public void FireCommand()
		{
			if(this.projectID == "")
			{
				Form1.ShowError( "The Project ID has not been set yet" );
				return;
			}

			// Setup communication with the Server's API.
			// The API might not get used with every command type.  Just create it anyway to keep code shorter
			ServerAPI srvAPI = new ServerAPI(this.appSettings);
			srvAPI.projectNumber = this.projectID; 

			PrinterTask printTsk = new PrinterTask( this.appSettings, this.brCod );
			CommandDetail CmdDt = new CommandDetail(this.cmdQueue, this.msgLog, this.appSettings, this.brCod, this.form1Obj );
			CommandDetail CmdDt2 = new CommandDetail(this.cmdQueue, this.msgLog, this.appSettings, this.brCod, this.form1Obj );
			CommandDetail CmdDt3 = new CommandDetail(this.cmdQueue, this.msgLog, this.appSettings, this.brCod, this.form1Obj );

			Hashtable ProjInfo;
			Hashtable RackInfo;

			// Make sure that the Print Invoice button is always hidden
			this.form1Obj.HidePrintButton();

			switch( this.cmdType )
			{
					// This may give us sucess or failure, and any message that may go with either
				case CommandType.ProductionScan:

					string comPortMsg = this.form1Obj.checkForMessagesOnComPorts();
					if(comPortMsg != null)
					{
						this.msgLog.DisplayError( comPortMsg, this.projectID );
						return;
					}


					srvAPI.command = "production_scan";
					srvAPI.FireRequest();

					// Get a hashtable back from the API object
					ProjInfo = srvAPI.projectInfo;
					RackInfo = srvAPI.rackInfo;

					if( srvAPI.error )
					{
						this.msgLog.DisplayError( srvAPI.errorDescription, this.projectID );
						return;
					}

					int boxesOnRackFromOrder = int.Parse( RackInfo["boxes_on_rack_from_order_in_product_group"].ToString() );
					int boxesForProject = int.Parse( RackInfo["boxes_for_project"].ToString() );
					int projectsInOrder = int.Parse( RackInfo["projects_in_order_in_product_group"].ToString() );

					this.msgLog.CreateBookMark();

					this.msgLog.ShowOrderNumber( ProjInfo["order_id"].ToString(), ProjInfo["id"].ToString() );
					this.msgLog.ShowOrderCount( int.Parse( RackInfo["projects_in_order_in_product_group"].ToString() ) );
					this.msgLog.ShowBoxCount( int.Parse( RackInfo["boxes_for_project"].ToString() ),
						int.Parse( RackInfo["boxes_for_order_in_product_group"].ToString() ));
					this.msgLog.ShowProductionNote( ProjInfo["notes"].ToString() );

					this.msgLog.ShowShippingInstructions( srvAPI.GetShippingInstructions() );
					


					// With every new production scan reset the last "Promotional Artwork Delegate" command.
					this.form1Obj.setLastPromoArtworkDelegateCom(srvAPI.promoCommandDelegateFromServer);


					// Find out what the results are from the production scan
					if ( srvAPI.message == "READY_TO_SHIP_COMBINED" )
					{

						if(ProjInfo["ship_country_code"].ToString() != "US")
							this.msgLog.ShowInternationalShipment();

						string msg = "<div align='center'><br>The order is now ready for shipment.  Gather the other <font style='font-size:24px;'><b>" + boxesOnRackFromOrder;
						
						if( boxesOnRackFromOrder  == 1 )
							msg += " box ";
						else
							msg += " boxes ";
						msg += " </b></font> on the rack.<br>&nbsp;<br>";

						this.msgLog.WriteToLog( msg );

						this.msgLog.ShowRackPosition( RackInfo["rack_text"].ToString(), RackInfo["row_text"].ToString(), 
							RackInfo["column_text"].ToString(), MessageLog.RackColor.Orange );

						this.msgLog.WriteToLog( "</div>" );



						this.form1Obj.ShowPrintButton(this.projectID);

						// ---- Decided not to print project labels for right now
						// Setup a new command in the queue to print the project label
						// CmdDt.projectNumber = this.projectID;
						// CmdDt.command = CommandDetail.CommandType.PrintProjectLabel;
						// this.cmdQueue.AddCommand(CmdDt);

					}
					else if ( srvAPI.message == "PUT_ON_RACK" )
					{
						this.msgLog.WriteToLog( "<br><div align='center'>Put on rack.<br><font style='font-size:3px;'>&nbsp;</font><br>" );

						this.msgLog.ShowRackPosition( RackInfo["rack_text"].ToString(), RackInfo["row_text"].ToString(), 
							RackInfo["column_text"].ToString(), MessageLog.RackColor.Blue  );

						// Find out how many boxes should be on the rack currently, including this project
						if( boxesOnRackFromOrder == 1 )
							this.msgLog.WriteToLog( "<font style='font-size:3px;'>&nbsp;</font><br>Total of 1 box on the rack for this order, so far. (includes this project)" );
						else
							this.msgLog.WriteToLog( "<font style='font-size:3px;'>&nbsp;</font><br>Total of " +  boxesOnRackFromOrder +
								" boxes on the rack for this order, so far. (includes this project)</div>" );




						// ---- Decided not to print project labels for right now
						// Setup a new command in the queue to print the project label
						// CmdDt.projectNumber = this.projectID;
						// CmdDt.command = CommandDetail.CommandType.PrintProjectLabel;
						// this.cmdQueue.AddCommand(CmdDt);

					}
					else if ( srvAPI.message == "READY_TO_SHIP_SOLO" )
					{
						if(ProjInfo["ship_country_code"].ToString() != "US")
							this.msgLog.ShowInternationalShipment();

						this.msgLog.WriteToLog( "<br><font size='5'><b>Ready to ship solo.</b></font>" ); 


						this.form1Obj.ShowPrintButton(this.projectID);

					}
					else
					{
						Form1.ShowError( "Illegal API Production Command was sent: \n" +  srvAPI.message );
						return;
					}

					this.PossiblyPrintPromoInfoToMsgLog(srvAPI.promoCommandDelegateFromServer);


					// Clear the page off and jump the browser down to the last scan.
					this.msgLog.ClearPage();
					this.msgLog.DisplayLogBuffer();

					break;
				case CommandType.InformationScan:

					srvAPI.command = "get_project_info";
					srvAPI.FireRequest();

					if( srvAPI.error )
					{
						this.msgLog.DisplayError( srvAPI.errorDescription, this.projectID );
						return;
					}

					// Get a hashtable back from the API object
					ProjInfo = srvAPI.projectInfo;
					RackInfo = srvAPI.rackInfo;

					this.msgLog.CreateBookMark();
					this.msgLog.ShowOrderNumber( ProjInfo["order_id"].ToString(), ProjInfo["id"].ToString() );
					this.msgLog.ShowShippingMethod( ProjInfo["ship_country_code"].ToString(), ProjInfo["ship_carrier"].ToString(), ProjInfo["ship_method"].ToString(), ProjInfo["ship_method_description"].ToString(), ProjInfo["ship_priority"].ToString() );
					this.msgLog.ShowDescription( ProjInfo["order_description"].ToString(), ProjInfo["options_description"].ToString() );
					
					// If the box has already been finsished... or shipped.  Then we don't want to show any box counts.
					// The rackControl class doesn't work backwards with projects that have been completed
					if( ProjInfo["status"].ToString() != "F" )
					{
						this.msgLog.ShowOrderCount( int.Parse( RackInfo["projects_in_order_in_product_group"].ToString() ) );
						this.msgLog.ShowBoxCount( int.Parse( RackInfo["boxes_for_project"].ToString() ),
						int.Parse( RackInfo["boxes_for_order_in_product_group"].ToString() ));
					}
					this.msgLog.ShowStatus( ProjInfo["status_description"].ToString() );
					this.msgLog.ShowProductionNote( ProjInfo["notes"].ToString() );

					this.msgLog.ShowShippingInstructions( srvAPI.GetShippingInstructions() );

					if(ProjInfo["ship_country_code"].ToString() != "US")
							this.msgLog.ShowInternationalShipment();

					if( RackInfo["project_is_on_rack"].ToString() == "yes" )
					{
						this.msgLog.WriteToLog( "<div align='center'><font style='font-size:8px;'>&nbsp;</font><br>This project belongs on the rack.<br><font style='font-size:4px;'>&nbsp;</font><br>" );
						this.msgLog.ShowRackPosition( RackInfo["rack_text"].ToString(), RackInfo["row_text"].ToString(), 
							RackInfo["column_text"].ToString(), MessageLog.RackColor.Green  );

						// Find out how many boxes should be on the rack currently, including this project
						int boxesOnRack = int.Parse( RackInfo["boxes_on_rack_from_order_in_product_group"].ToString() );
						if( boxesOnRack == 1 )
							this.msgLog.WriteToLog( "This should be the only box on the rack, for right now." );
						else
							this.msgLog.WriteToLog( "<font face='arial'>Right now there should be " +  RackInfo["boxes_on_rack_from_order_in_product_group"].ToString() +
								" boxes on the rack, including this project.</div>" );
					}

					if( RackInfo["project_is_on_rack"].ToString() == "no" && ProjInfo["status"].ToString() == "B" )
					{
						this.msgLog.WriteToLog( "<font face='arial'><br>This project should be inside of an outer shipping container and ready for shipment.  If this project has been separated from its outer container, then Re-Print the shipping label and hunt for the matching shipping ID.</div>" );
					}

					this.msgLog.ClearPage();
					this.msgLog.DisplayLogBuffer();

					break;
				case CommandType.StatusBoxedToPrinted:
					
					srvAPI.status = "B";
					srvAPI.statusChange = "T";
					srvAPI.command = "change_project_status";
					srvAPI.FireRequest();

					if( srvAPI.error )
					{
						this.msgLog.DisplayError( srvAPI.errorDescription, this.projectID );
						return;
					}

					// Now try to remove the project from the rack.
					// It won't hurt to call his function if the project is not on the rack
					srvAPI.command = "remove_from_rack";
					srvAPI.FireRequest();

					if( srvAPI.error )
					{
						this.msgLog.DisplayError( srvAPI.errorDescription, this.projectID );
						return;
					}

					this.msgLog.CreateBookMark();
					this.msgLog.ShowOrderNumber( "", this.projectID );
					this.msgLog.WriteToLog("<font style='font-family:Arial; font-size:18px; color:#000066'>Status changed back to printed.<font>");
					this.msgLog.ClearPage();
					this.msgLog.DisplayLogBuffer();

					break;
				case CommandType.StatusQueuedToPrinted:
					
					srvAPI.status = "Q";
					srvAPI.statusChange = "T";
					srvAPI.command = "change_project_status";
					srvAPI.FireRequest();

					if( srvAPI.error )
					{
						this.msgLog.DisplayError( srvAPI.errorDescription, this.projectID );
						return;
					}

					this.msgLog.CreateBookMark();
					this.msgLog.ShowOrderNumber( "", this.projectID );
					this.msgLog.WriteToLog("<font style='font-family:Arial; font-size:18px; color:#006600'>Status changed to &quot;Printed&quot;.<font>");
					this.msgLog.ClearPage();
					this.msgLog.DisplayLogBuffer();

					break;
				case CommandType.StatusProofedToPrinted:
					
					srvAPI.status = "P";
					srvAPI.statusChange = "T";
					srvAPI.command = "change_project_status";
					srvAPI.FireRequest();

					if( srvAPI.error )
					{
						this.msgLog.DisplayError( srvAPI.errorDescription, this.projectID );
						return;
					}

					this.msgLog.CreateBookMark();
					this.msgLog.ShowOrderNumber( "", this.projectID );
					this.msgLog.WriteToLog("<font style='font-family:Arial; font-size:18px; color:#006600'>Status changed to &quot;Printed&quot;.<font>");
					this.msgLog.ClearPage();
					this.msgLog.DisplayLogBuffer();

					break;
				case CommandType.StatusPrintedToArtworkProblem:
					
					srvAPI.status = "T";
					srvAPI.statusChange = "A";
					srvAPI.command = "change_project_status";
					srvAPI.FireRequest();

					if( srvAPI.error )
					{
						this.msgLog.DisplayError( srvAPI.errorDescription, this.projectID );
						return;
					}

					this.msgLog.CreateBookMark();
					this.msgLog.ShowOrderNumber( "", this.projectID );
					this.msgLog.WriteToLog("<font style='font-family:Arial; font-size:18px; color:#006600'>Status changed to &quot;Artwork Problem&quot;.<font>");
					this.msgLog.ClearPage();
					this.msgLog.DisplayLogBuffer();

					break;
				case CommandType.StatusPrintedToDefective:
					
					srvAPI.status = "T";
					srvAPI.statusChange = "D";
					srvAPI.command = "change_project_status";
					srvAPI.FireRequest();

					if( srvAPI.error )
					{
						this.msgLog.DisplayError( srvAPI.errorDescription, this.projectID );
						return;
					}

					this.msgLog.CreateBookMark();
					this.msgLog.ShowOrderNumber( "", this.projectID );
					this.msgLog.WriteToLog("<font style='font-family:Arial; font-size:18px; color:#660000'>Status changed to &quot;Defective&quot;.<font>");
					this.msgLog.ClearPage();
					this.msgLog.DisplayLogBuffer();

					break;
				case CommandType.StatusReturnedPackage:
				
					srvAPI.command = "returned_package";
					srvAPI.FireRequest();

					if( srvAPI.error )
					{
						this.msgLog.DisplayError( srvAPI.errorDescription, this.projectID );
						return;
					}

					this.msgLog.CreateBookMark();
					this.msgLog.ShowOrderNumber( "", this.projectID );
					this.msgLog.WriteToLog("<font style='font-family:Arial; font-size:18px; color:#660000'>This Order has been marked as Returned.<font>");
					this.msgLog.ClearPage();
					this.msgLog.DisplayLogBuffer();

					break;
				case CommandType.FetchAndPrintInvoice:

					srvAPI.command = "get_invoice";
					srvAPI.FireRequest();

					if( srvAPI.error )
					{
						this.msgLog.DisplayError( srvAPI.errorDescription, this.projectID );
						return;
					}

					this.invceURL = srvAPI.invoiceURL;

					// Setup a new command to download the invoice that was just generated on the server
					CmdDt.projectNumber = this.projectID;
					CmdDt.invoiceURL = this.invceURL;
					CmdDt.command = CommandDetail.CommandType.DownloadInvoice;
					this.cmdQueue.AddCommand(CmdDt);

					break;

				case CommandType.FetchAndPrintPromoArtwork1:

					// Make sure that we have defined a Command for PromoArtwork1 in our settings.
					if(this.appSettings.promoCommand_1 == "")
					{
						Form1.ShowError( "Can not print the Promo Artwork #1 because a valid Promo Command has not been set.");
						return;
					}
					if(!this.appSettings.promoActive_1)
					{
						this.msgLog.DisplayError("Can not print the Promo Artwork #1 you have De-Activated it. \n Go into \"Setting\" and re-enable it if you wish.", this.projectID);
						return;
					}

					srvAPI.promoCommand = this.appSettings.promoCommand_1;
					srvAPI.command = "get_promo_artwork";
					srvAPI.FireRequest();

					if( srvAPI.error )
					{
						this.msgLog.DisplayError( srvAPI.errorDescription, this.projectID );
						return;
					}

					this.promo1ArtworkURL = srvAPI.promoArtURL;

					// Setup a new command to download the invoice that was just generated on the server
					CmdDt.projectNumber = this.projectID;
					CmdDt.promoArtURL1 = this.promo1ArtworkURL;
					CmdDt.command = CommandDetail.CommandType.DownloadPromoArtwork1;
					this.cmdQueue.AddCommand(CmdDt);

					break;

				case CommandType.DownloadInvoice:

					// Make sure to completely get rid of the old invoice.  We don't want to get it mixed with another order somehow
					File.Delete( this.invoicePathLocal );
		

					// Download the invoice in PDF format from the web and save it to "invoice.pdf" in our local directory.
					try
					{
						WebClient Client = new WebClient ();
						Client.DownloadFile( this.invceURL, this.invoicePathLocal );
					}
					catch ( System.Exception e )
					{
						Form1.ShowError( "Invoice could not be downloaded.  Check that you have not lost your internet connection. " + e.Message );
						return;
					}

					if( ! File.Exists( this.invoicePathLocal ))
					{
						Form1.ShowError( "Invoice could not be downloaded.  Check that you have not lost your internet connection." );
						return;
					}
					else
					{
						// Print the invoice
						CmdDt.projectNumber = this.projectID;
						CmdDt.command = CommandDetail.CommandType.PrintInvoice;
						this.cmdQueue.AddCommand(CmdDt);
					}

					
					break;
				case CommandType.DownloadPromoArtwork1:

					// Make sure to completely get rid of the old Promotional artwork.  We don't want to get it mixed with another order somehow
					File.Delete( this.promo1artworkPDFPathLocal );
		

					// Download the Promotional Artwork in PDF format from the web and save it to the specified file in our local directory.
					try
					{
						WebClient Client = new WebClient ();
						Client.DownloadFile( this.promo1ArtworkURL, this.promo1artworkPDFPathLocal );
					}
					catch ( System.Exception e )
					{
						Form1.ShowError( "Promotional Artwork #1 could not be downloaded.  Check that you have not lost your internet connection. " + e.Message );
						return;
					}

					if( ! File.Exists( this.promo1artworkPDFPathLocal ))
					{
						Form1.ShowError( "Promotional Artwork #1 could not be downloaded.  Check that you have not lost your internet connection." );
						return;
					}
					else
					{
						// Print the Promo Artwork now
						CmdDt.projectNumber = this.projectID;
						CmdDt.command = CommandDetail.CommandType.PrintPromoArtwork1;
						this.cmdQueue.AddCommand(CmdDt);
					}

					
					break;
				case CommandType.PrintInvoice:

					printTsk.PrintType = PrinterTask.PrintTaskEnum.Invoice;
					printTsk.SendToPrinter();

					break;
				case CommandType.PrintPromoArtwork1:

					printTsk.PrintType = PrinterTask.PrintTaskEnum.Promo1;
					printTsk.SendToPrinter();

					break;
				case CommandType.PrintProjectLabel:

					srvAPI.command = "get_project_info";
					srvAPI.FireRequest();

					if( srvAPI.error )
					{
						this.msgLog.DisplayError( srvAPI.errorDescription, this.projectID );
						return;
					}

					// Get a hashtable back from the API object
					ProjInfo = srvAPI.projectInfo;
					RackInfo = srvAPI.rackInfo;

					printTsk.ProjectNumber = this.projectID;
					printTsk.OrderNumber = ProjInfo["order_id"].ToString();
					printTsk.BoxesInProject = RackInfo["boxes_for_project"].ToString();
					printTsk.BoxesInOrder = RackInfo["boxes_for_order_in_product_group"].ToString();
					printTsk.PrintType = PrinterTask.PrintTaskEnum.ProjectLabel;
					printTsk.SendToPrinter();
					
					break;
				case CommandType.ReprintProjectLabel:

					// Show the order number that we are reprinting
					this.msgLog.CreateBookMark();
					this.msgLog.ShowOrderNumber( this.orderID, this.projectID );
					this.msgLog.WriteToLog("<font style='font-family:Arial; font-size:18px; color:#000066'>Re-printing project label.<font>");
					this.msgLog.ClearPage();
					this.msgLog.DisplayLogBuffer();

					// Setup a new command in the queue.  It will report an errors if they are found.  Silent otherwise
					CmdDt.projectNumber = this.projectID;
					CmdDt.command = CommandDetail.CommandType.PrintProjectLabel;
					this.cmdQueue.AddCommand(CmdDt);

					break;
				case CommandType.ReprintInvoice:

					// Show the order number that we are reprinting
					this.msgLog.CreateBookMark();
					this.msgLog.ShowOrderNumber( this.orderID, this.projectID );
					this.msgLog.WriteToLog("<font style='font-family:Arial; font-size:18px; color:#660000'>Re-printing Invoice.<font>");
					this.msgLog.ClearPage();
					this.msgLog.DisplayLogBuffer();

					// Setup a new command in the queue.
					CmdDt.projectNumber = this.projectID;
					CmdDt.command = CommandDetail.CommandType.FetchAndPrintInvoice;
					this.cmdQueue.AddCommand(CmdDt);

					break;
				case CommandType.ReprintPromoArtwork1:

					// Show the order number that we are reprinting
					this.msgLog.CreateBookMark();
					this.msgLog.ShowOrderNumber( this.orderID, this.projectID );
					this.msgLog.WriteToLog("<font style='font-family:Arial; font-size:18px; color:#660066'>Re-printing " + this.appSettings.promoCommand_1 + ".<font>");
					this.msgLog.ClearPage();
					this.msgLog.DisplayLogBuffer();

					// Setup a new command in the queue.
					CmdDt.projectNumber = this.projectID;
					CmdDt.command = CommandDetail.CommandType.FetchAndPrintPromoArtwork1;
					this.cmdQueue.AddCommand(CmdDt);

					break;
				case CommandType.ReprintShippingLabel:

					// Show the order number that we are reprinting
					this.msgLog.CreateBookMark();
					this.msgLog.ShowOrderNumber( this.orderID, this.projectID );
					this.msgLog.WriteToLog("<font style='font-family:Arial; font-size:18px; color:#006600'>Re-printing shipping label.<font>");
					this.msgLog.ClearPage();
					this.msgLog.DisplayLogBuffer();

					// Setup a new command in the queue.  It will report an errors if they are found.  Silent otherwise
					CmdDt.projectNumber = this.projectID;
					CmdDt.command = CommandDetail.CommandType.PrintShippingLabel;
					this.cmdQueue.AddCommand(CmdDt);

					break;
				case CommandType.PrintShippingLabel:

					// We need either a project ID or an Order ID.  We don't need both
					if(this.orderID == "" && this.projectID == "" )
					{
						Form1.ShowError( "Neither the OrderID or the project ID have been set before calling the ShippingLabel function" );
						return;
					}

					// Now get any shipments with this order from the API
					srvAPI.command = "get_shipments_for_order";
					srvAPI.orderNumber = this.orderID;
					srvAPI.projectNumber = this.projectID;
					srvAPI.productNumber = this.prdctID;
					srvAPI.FireRequest();


					if( srvAPI.error )
					{
						this.msgLog.DisplayError( srvAPI.errorDescription, this.projectID );
						return;
					}

					// Get a hashtable back from the API object
					ProjInfo = srvAPI.projectInfo;
					RackInfo = srvAPI.rackInfo;

					// There may be a few shipments with this order. 
					// There could be multiple shipments for 1 project... if it is a really large project
					// Or there could be many projects that are split between shipping boxes
					ArrayList shipIDs = srvAPI.GetShipmentIDs();

					foreach( string shpID in shipIDs )
					{
						// Get details about the shipment itself
						ShipmentDetail shipObj = srvAPI.GetShipmentDetail( shpID );

						// Set all of the properties for the Printer Task so it will have the information it needs to print
						printTsk.ProjectNumber = this.projectID;
						printTsk.OrderNumber = shipObj.OrderNumber;
						printTsk.ShippingCarrier = shipObj.ShippingCarrier;
						printTsk.ShippingAlertMessage = shipObj.ShippingAlertMessage;
						printTsk.ShippingID = shpID;
						printTsk.ShipmentWeight = shipObj.Weight;
						printTsk.PrintType = PrinterTask.PrintTaskEnum.ShippingLabel;
						printTsk.ShipCountry = shipObj.ShipCountryCode;
						printTsk.SendToPrinter();
					}

					break;
				default:
					Form1.ShowError( "Illegal Command Type was set" );
					break;
			}
		}


		public enum CommandType
		{
			ProductionScan = 1,
			InformationScan = 2,
			FetchAndPrintInvoice = 3,
			DownloadInvoice = 4,
			PrintInvoice = 5,
			PrintProjectLabel = 6,
			PrintShippingLabel = 7,
			ReprintProjectLabel = 8,
			ReprintShippingLabel = 9,
			ReprintInvoice = 10,
			StatusBoxedToPrinted = 11,
			StatusPrintedToDefective = 12,
			StatusPrintedToArtworkProblem = 13,
			StatusReturnedPackage = 14,
			StatusQueuedToPrinted = 15,
			StatusProofedToPrinted = 16,
			PrintPromoArtwork1 = 17,
			FetchAndPrintPromoArtwork1 = 18,
			DownloadPromoArtwork1 = 19,
			ReprintPromoArtwork1 = 20,
		}

		public CommandType command
		{
			set
			{
				this.cmdType = value;

				// Create a verbal description to go with each command
				switch( this.cmdType )
				{
					case CommandType.ProductionScan:
						this.cmdDesc = "Talking to Server";
						break;
					case CommandType.InformationScan:
						this.cmdDesc = "Talking to Server";
						break;
					case CommandType.FetchAndPrintInvoice:
						this.cmdDesc = "Generating Invoice";
						break;
					case CommandType.DownloadInvoice:
						this.cmdDesc = "Downloading Invoice";
						break;
					case CommandType.PrintInvoice:
						this.cmdDesc = "Printing Invoice";
						break;
					case CommandType.ReprintInvoice:
						this.cmdDesc = "Re-Printing Invoice";
						break;
					case CommandType.PrintProjectLabel:
						this.cmdDesc = "Printing Project Label";
						break;
					case CommandType.ReprintProjectLabel:
						this.cmdDesc = "Re-Printing Project Label";
						break;
					case CommandType.PrintShippingLabel:
						this.cmdDesc = "Printing Shipping Label";
						break;
					case CommandType.ReprintShippingLabel:
						this.cmdDesc = "Re-Printing Shipping Label";
						break;
					case CommandType.StatusBoxedToPrinted:
						this.cmdDesc = "Changing Status";
						break;
					case CommandType.StatusQueuedToPrinted:
						this.cmdDesc = "Changing Status";
						break;
					case CommandType.StatusProofedToPrinted:
						this.cmdDesc = "Changing Status";
						break;
					case CommandType.StatusPrintedToArtworkProblem:
						this.cmdDesc = "Changing Status";
						break;
					case CommandType.StatusPrintedToDefective:
						this.cmdDesc = "Changing Status";
						break;
					case CommandType.StatusReturnedPackage:
						this.cmdDesc = "Changing Status";
						break;
					case CommandType.ReprintPromoArtwork1:
						this.cmdDesc = "Reprinting Promotional Art #1";
						break;
					case CommandType.PrintPromoArtwork1:
						this.cmdDesc = "Printing Promotional Art #1";
						break;
					case CommandType.FetchAndPrintPromoArtwork1:
						this.cmdDesc = "Printing Promotional Art #1";
						break;
					case CommandType.DownloadPromoArtwork1:
						this.cmdDesc = "Downloading Promotional Art #1";
						break;
					default:
						Form1.ShowError( "Illegal Command Type was set within CommandType" );
						break;
				}
			}
		}


		public void PossiblyPrintPromoInfoToMsgLog(string promoDelegateFromTheServer)
		{

			// We may have received a command from the server telling us that we should print a certain type of Promotional material to put in the container.
			if(promoDelegateFromTheServer != "")
			{
				// Find out if the command from the server matches a Command name that this Client Application is willing handle.
				// Right now we have only configured one Promotional printer... but in the future this application could be setup to handle many.
				if(this.appSettings.promoCommand_1 == promoDelegateFromTheServer)
				{
					// The computer may have been configured to Disable printing the current promotional item.
					if(this.appSettings.promoActive_1)
						this.msgLog.WriteToLog( "<br><br><font size='2' color='#9933cc'><b>Promotional Item Printing: <font color='#000000'>" + promoDelegateFromTheServer + "</font></b></font>" ); 
					else
						this.msgLog.WriteToLog( "<br><br><font size='2' color='#cc99cc'><b>Promotional Item De-activated: <font color='#999999'>" + promoDelegateFromTheServer + "</font></b></font>" ); 
				}
				else
				{
					this.msgLog.WriteToLog( "<br><br><font size='4' color='#CC0000'><b>A promotional command from the server has not been configured for this computer: <font color='#000000'>" + promoDelegateFromTheServer + "</font></b></font>" ); 
				}
			}

		}


		public string invoiceURL
		{
			set
			{
				this.invceURL = value;
			}
		}
		public string promoArtURL1
		{
			set
			{
				this.promo1ArtworkURL = value;
			}
		}
		public string projectNumber
		{
			set
			{
				this.projectID = value;
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
		public string orderNumber
		{
			set
			{
				this.orderID = value;
			}
		}
		public string productNumber
		{
			set
			{
				this.prdctID = value;
			}
		}
		public string commandDescription
		{
			get
			{
				return this.cmdDesc;
			}
		}
	}
}
