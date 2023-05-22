using System;
using IDAutomation.Windows.Forms.LinearBarCode;
using System.Drawing;
using System.Drawing.Printing;
using System.Collections;

namespace PME_Link
{
	/// <summary>
	/// Summary description for PrinterTask.
	/// </summary>
	public class PrinterTask
	{
		private ApplicationSettings appSet;
		private PrintTaskEnum prntType;
		private string shipID;
		private string projNum;
		private string ordNum;
		private string carrier;
		private string shipWeight;
		private string boxesProject;
		private string boxesOrder;
		private string shipCountryCode;
		private string shipAlertMessage;
		private Barcode brCod;
		int boxCountProject;
		int boxCountCurrent;

		public PrinterTask( ApplicationSettings appSettings,  Barcode barCodeObj )
		{
			this.appSet = appSettings;
			this.brCod = barCodeObj;
			this.boxesProject = "0";
			this.boxesOrder = "";
			this.shipAlertMessage = "";
		}

		public enum PrintTaskEnum
		{
			ProjectLabel = 1,
			ShippingLabel = 2,
			Invoice = 3,
			Promo1 = 4,
		}



		public void SendToPrinter()
		{
			switch( this.prntType )
			{
				case PrintTaskEnum.ProjectLabel:
				{
					if( this.appSet.labelPrinterName == "" )
					{
						Form1.ShowError( "The label Printer has not been configured yet." );
						return;
					}

					// We want to print a label for each box in the project
					this.boxCountProject = int.Parse(this.boxesProject);
					this.boxCountCurrent = 0;

					this.brCod.DataToEncode = "P" + this.projNum;

					// Send to printer
					PrintDocument prndoc = new PrintDocument(); 
					// Give the document a title.  This is what displays in the Printers Control Panel item
					prndoc.DocumentName = "Printing Project Barcode";

					prndoc.PrinterSettings.PrinterName = this.appSet.labelPrinterName;

					// Add an event handler that acutally does the printing for each page
					prndoc.PrintPage += new System.Drawing.Printing.PrintPageEventHandler(this.PrintDocumentLabels );
					prndoc.Print();

			
					break;
				}
				case PrintTaskEnum.ShippingLabel:
				{
					if( this.appSet.shippingPrinterName == "" )
					{
						Form1.ShowError( "The Shipping Printer has not been configured yet." );
						return;
					}

					this.brCod.DataToEncode = this.shipID;

					// Send to printer
					PrintDocument prndoc = new PrintDocument(); 
					// Give the document a title.  This is what displays in the Printers Control Panel item
					prndoc.DocumentName = "Printing a Shipping Barcode";

					prndoc.PrinterSettings.PrinterName = this.appSet.shippingPrinterName;

					// Add an event handler that acutally does the printing for each page
					prndoc.PrintPage += new System.Drawing.Printing.PrintPageEventHandler( this.PrintShippingLabels );
					prndoc.Print();


					break;
				}
				case PrintTaskEnum.Invoice:
				{
					if( this.appSet.invoicePrinterName == "" )
					{
						Form1.ShowError( "The invoice Printer has not been configured yet." );
						return;
					}

					// This class uses the PDF printiing tool supplied by "pdf-tools.com"
					// You will have to register the DLL on the system before this will work.
					// The DLL will automatically be exported when the Setup file is created.
					PRINTEROCXLib.PDFPrinterClass printer = new PRINTEROCXLib.PDFPrinterClass();

					if(! printer.OpenPrinter( this.appSet.invoicePrinterName ) )
					{
						Form1.ShowError( "The invoice printer is invalid." );
						return;
					}

					if(! printer.Open("invoice.pdf", "") )
					{
						Form1.ShowError( "The invoice file does not exist or it is corrupted." );
						return;
					}

					bool result;
					printer.BeginDocument("Invoice");
					printer.PrintPage(1);
					printer.EndDocument();
					result = printer.Close();
					result = printer.ClosePrinter();

					break;
				}
				case PrintTaskEnum.Promo1:
				{
					if( this.appSet.promoPrinterName_1 == "" )
					{
						Form1.ShowError( "The Promotional Printer #1 has not been configured yet." );
						return;
					}

					// This class uses the PDF printiing tool supplied by "pdf-tools.com"
					// You will have to register the DLL on the system before this will work.
					// The DLL will automatically be exported when the Setup file is created.
					PRINTEROCXLib.PDFPrinterClass printer = new PRINTEROCXLib.PDFPrinterClass();

					if(! printer.OpenPrinter( this.appSet.promoPrinterName_1 ) )
					{
						Form1.ShowError( "The Promotional Printer #1 is invalid." );
						return;
					}

					if(! printer.Open("Promo1.pdf", "") )
					{
						Form1.ShowError( "The Promotional Artwork #1 file does not exist or it is corrupted." );
						return;
					}

					bool result;
					printer.BeginDocument("Promo1");
					printer.PrintPage(1);
					printer.EndDocument();
					result = printer.Close();
					result = printer.ClosePrinter();

					break;
				}
				default:
				{
					Form1.ShowError( "The Print Task has not been defined yet." );
					return;
		
				}
			}
			
		}
		private void PrintDocumentLabels(object sender, PrintPageEventArgs ppea )
		{
			// Print multiple labels if there are multiple boxes for this project
			this.boxCountCurrent++;
			if(this.boxCountCurrent >= this.boxCountProject)
				ppea.HasMorePages = false;
			else
				ppea.HasMorePages = true;

			Graphics grfx = ppea.Graphics;
			System.Drawing.Imaging.Metafile myImage; 
			
			string boxDesc = this.boxCountCurrent.ToString() + " of " + this.boxesProject + " of " + this.boxesOrder;
			System.Drawing.Font fontStyle = new System.Drawing.Font("Times New Roman", 9F, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, ((System.Byte)(0)));
			grfx.DrawString(boxDesc, fontStyle, Brushes.Black, 10, 10);

			// Set the size and DPI of the barcode based upon our application settings
			this.brCod.Resolution = IDAutomation.Windows.Forms.LinearBarCode.Barcode.Resolutions.Custom;
			this.brCod.ResolutionCustomDPI = this.appSet.BarcodeDPI;
			this.brCod.BarHeightCM = this.appSet.BarcodeHeight;
			this.brCod.XDimensionCM = this.appSet.BarcodeWidth;

			myImage = this.brCod.Picture;
			grfx.DrawImage(myImage, 10, 22);

			return;
		}
		private void PrintShippingLabels(object sender, PrintPageEventArgs ppea )
		{
			Graphics grfx = ppea.Graphics;
			System.Drawing.Imaging.Metafile myImage; 

			// this.shipWeight = this.shipWeight += " lbs."
			string labelDesc = "O-" + this.ordNum;
			System.Drawing.Font fontStyle = new System.Drawing.Font("Times New Roman", 9F, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Point, ((System.Byte)(0)));
			grfx.DrawString(labelDesc, fontStyle, Brushes.Black, 10, 9);


			// If this is being shipped through the Post Office... then show a little mark.
			if(this.shipAlertMessage != "")
			{
				grfx.DrawString(this.shipAlertMessage, fontStyle, Brushes.Black, 80, 14);
			}
		
			// Set the size and DPI of the barcode based upon our application settings
			this.brCod.Resolution = IDAutomation.Windows.Forms.LinearBarCode.Barcode.Resolutions.Custom;
			this.brCod.ResolutionCustomDPI = this.appSet.BarcodeDPI;
			this.brCod.BarHeightCM = this.appSet.BarcodeHeight;
			this.brCod.XDimensionCM = this.appSet.BarcodeWidth;

			myImage = this.brCod.Picture;
			grfx.DrawImage(myImage, 10, 26);

			return;
		}
		public PrintTaskEnum PrintType
		{
			set
			{
				this.prntType = value;
			}
		}
		public string ShippingID
		{
			set
			{
				this.shipID = value;
			}
		}
		public string ProjectNumber
		{
			set
			{
				this.projNum = value;
			}
		}
		public string OrderNumber
		{
			set
			{
				this.ordNum = value;
			}
		}
		public string ShippingCarrier
		{
			set
			{
				this.carrier = value;
			}
		}
		public string ShippingAlertMessage
		{
			set
			{
				this.shipAlertMessage = value;
			
			}
		}
		public string ShipmentWeight
		{
			set
			{
				this.shipWeight = value;
			}
		}
			
		public string BoxesInProject
		{
			set
			{
				this.boxesProject = value;
			}
		}
		public string BoxesInOrder
		{
			set
			{
				this.boxesOrder = value;
			}
		}
		public string ShipCountry
		{
			set
			{
				this.shipCountryCode = value;
			}
		}

		
	}
}
