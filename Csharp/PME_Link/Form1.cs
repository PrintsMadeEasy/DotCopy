using System;
using System.Drawing;
using System.Collections;
using System.ComponentModel;
using System.Windows.Forms;
using System.Text.RegularExpressions;
using System.Data;
using IDAutomation.Windows.Forms.LinearBarCode;


namespace PME_Link
{
	/// <summary>
	/// Summary description for Form1.
	/// </summary>
	public class Form1 : System.Windows.Forms.Form
	{
		private System.Windows.Forms.TabControl tabControl1;
		private System.Windows.Forms.TabPage Production;
		private System.Windows.Forms.TabPage Information;
		private System.Windows.Forms.TabPage Labels;
		private System.Windows.Forms.TabPage Status;
		private MessageLog msgLogProduction;
		private MessageLog msgLogInformation;
		private MessageLog msgLogReprint;
		private MessageLog msgLogStatus;
		private AxSHDocVw.AxWebBrowser browserReprint;
		private AxSHDocVw.AxWebBrowser browserStatus;
		private AxSHDocVw.AxWebBrowser browserProduction;
		private AxSHDocVw.AxWebBrowser browserInformation;
		private string AppPath;
		private System.Windows.Forms.MainMenu mainMenu1;
		private System.Windows.Forms.MenuItem menuItem1;
		private System.Windows.Forms.MenuItem menuItem3;
		private ServerSetup setupForm;
		private CommandQueue CmdQueue;
		private System.Windows.Forms.Label commandDesc;
		private System.Windows.Forms.PictureBox signalLight;
		private System.Windows.Forms.TextBox projectNumberBox;
		private System.Windows.Forms.Label label1;
		private System.Windows.Forms.RadioButton reprintShipping;
		private System.Windows.Forms.RadioButton reprintInvoice;
		private System.Windows.Forms.RadioButton statusBoxedPrinted;
		private System.Windows.Forms.RadioButton statusPrintedDefective;
		private System.Windows.Forms.RadioButton statusPrintedArtwork;
		private string statusRadioSelected;
		private string reprintRadioSelected;
		private System.Windows.Forms.MenuItem menuItem2;
		private System.Windows.Forms.MenuItem menuItem4;
		private IDAutomation.Windows.Forms.LinearBarCode.Barcode brCod;
		private System.Windows.Forms.Button printBtn;
		private string projectIDinUse;
		private string lastPromoArtworkDelegateCommand;
		private System.Windows.Forms.RadioButton statusReturnedPackage;
		private System.Windows.Forms.RadioButton statusQueuedPrinted;
		private System.Windows.Forms.RadioButton statusProofedPrinted;
		private System.Windows.Forms.RadioButton reprintPromo1;
		
		private SerialNET.Port port;

		private ApplicationSettings appSettingObj;


		/// <summary>
		/// Required designer variable.
		/// </summary>
		private System.ComponentModel.Container components = null;


		public Form1()
		{
			//
			// Required for Windows Form Designer support
			//
			InitializeComponent();



			this.AppPath = System.IO.Path.GetDirectoryName( 
				System.Reflection.Assembly.GetExecutingAssembly().GetName().CodeBase );

			this.appSettingObj = new ApplicationSettings();

			// Make the setup dialog a child of this form
			this.setupForm = new ServerSetup();
			this.AddOwnedForm(this.setupForm);

			// Start off with the 1st radio buttons selected
			this.reprintShipping.Checked = true;
			this.statusPrintedDefective.Checked = true;
			this.statusRadioSelected = "PrintedToDefective";
			this.reprintRadioSelected = "ShippingLabel";

			// This button will be shown when the invoice and shipping ID are ready for printing
			this.HidePrintButton();

			this.msgLogProduction = new MessageLog(AppPath, "log_production.htm", browserProduction, this.setupForm.appSetting, this);
			this.msgLogInformation = new MessageLog(AppPath, "log_information.htm", browserInformation, this.setupForm.appSetting, this);
			this.msgLogReprint = new MessageLog(AppPath, "log_reprint.htm", browserReprint, this.setupForm.appSetting, this);
			this.msgLogStatus = new MessageLog(AppPath, "log_statuschange.htm", browserStatus, this.setupForm.appSetting, this);
			
			this.CmdQueue = new CommandQueue();

			this.CmdQueue.OnQueueChange += new PME_Link.CommandQueue.QueueChangedDelegate(CmdQueue_OnQueueChange);


			// If we have defined a command for the Promotional Artwork #1 (in the future we may have many promotional Artwork products)
			// Then label the radio button on the may form.
			if(this.appSettingObj.promoCommand_1 != "")
					this.reprintPromo1.Text = this.appSettingObj.promoCommand_1;


			// I bought a license key for the Serial Port class from http://franson.com/serialtools
			SerialNET.License license = new SerialNET.License();
			license.LicenseKey = "fxwrfPfifuQt7egYDxrWBlGvs4hde6n3f33m";

			this.port = new SerialNET.Port();

			

			// If set to null, then events will be called in a separate thread (multi-threading)
			// If set to a form (this) events will be called in GUI thread (single threaded)
			this.port.Parent = this;
			



		}


		/// <summary>
		/// Clean up any resources being used.
		/// </summary>
		protected override void Dispose( bool disposing )
		{
			if( disposing )
			{
				this.port.Dispose();

				if (components != null) 
				{
					components.Dispose();
				}
			}
			base.Dispose( disposing );
		}

		#region Windows Form Designer generated code
		/// <summary>
		/// Required method for Designer support - do not modify
		/// the contents of this method with the code editor.
		/// </summary>
		private void InitializeComponent()
		{
			System.Resources.ResourceManager resources = new System.Resources.ResourceManager(typeof(Form1));
			this.tabControl1 = new System.Windows.Forms.TabControl();
			this.Production = new System.Windows.Forms.TabPage();
			this.browserProduction = new AxSHDocVw.AxWebBrowser();
			this.Information = new System.Windows.Forms.TabPage();
			this.browserInformation = new AxSHDocVw.AxWebBrowser();
			this.Labels = new System.Windows.Forms.TabPage();
			this.reprintPromo1 = new System.Windows.Forms.RadioButton();
			this.reprintInvoice = new System.Windows.Forms.RadioButton();
			this.reprintShipping = new System.Windows.Forms.RadioButton();
			this.browserReprint = new AxSHDocVw.AxWebBrowser();
			this.Status = new System.Windows.Forms.TabPage();
			this.statusProofedPrinted = new System.Windows.Forms.RadioButton();
			this.statusReturnedPackage = new System.Windows.Forms.RadioButton();
			this.statusQueuedPrinted = new System.Windows.Forms.RadioButton();
			this.statusBoxedPrinted = new System.Windows.Forms.RadioButton();
			this.statusPrintedArtwork = new System.Windows.Forms.RadioButton();
			this.statusPrintedDefective = new System.Windows.Forms.RadioButton();
			this.browserStatus = new AxSHDocVw.AxWebBrowser();
			this.mainMenu1 = new System.Windows.Forms.MainMenu();
			this.menuItem2 = new System.Windows.Forms.MenuItem();
			this.menuItem4 = new System.Windows.Forms.MenuItem();
			this.menuItem1 = new System.Windows.Forms.MenuItem();
			this.menuItem3 = new System.Windows.Forms.MenuItem();
			this.commandDesc = new System.Windows.Forms.Label();
			this.signalLight = new System.Windows.Forms.PictureBox();
			this.projectNumberBox = new System.Windows.Forms.TextBox();
			this.label1 = new System.Windows.Forms.Label();
			this.brCod = new IDAutomation.Windows.Forms.LinearBarCode.Barcode();
			this.printBtn = new System.Windows.Forms.Button();
			this.tabControl1.SuspendLayout();
			this.Production.SuspendLayout();
			((System.ComponentModel.ISupportInitialize)(this.browserProduction)).BeginInit();
			this.Information.SuspendLayout();
			((System.ComponentModel.ISupportInitialize)(this.browserInformation)).BeginInit();
			this.Labels.SuspendLayout();
			((System.ComponentModel.ISupportInitialize)(this.browserReprint)).BeginInit();
			this.Status.SuspendLayout();
			((System.ComponentModel.ISupportInitialize)(this.browserStatus)).BeginInit();
			this.SuspendLayout();
			// 
			// tabControl1
			// 
			this.tabControl1.Controls.Add(this.Production);
			this.tabControl1.Controls.Add(this.Information);
			this.tabControl1.Controls.Add(this.Labels);
			this.tabControl1.Controls.Add(this.Status);
			this.tabControl1.ItemSize = new System.Drawing.Size(100, 45);
			this.tabControl1.Location = new System.Drawing.Point(20, 42);
			this.tabControl1.Name = "tabControl1";
			this.tabControl1.SelectedIndex = 0;
			this.tabControl1.Size = new System.Drawing.Size(676, 534);
			this.tabControl1.TabIndex = 0;
			this.tabControl1.SelectedIndexChanged += new System.EventHandler(this.tabControl1_SelectedIndexChanged);
			// 
			// Production
			// 
			this.Production.Controls.Add(this.browserProduction);
			this.Production.Location = new System.Drawing.Point(4, 49);
			this.Production.Name = "Production";
			this.Production.Size = new System.Drawing.Size(668, 481);
			this.Production.TabIndex = 0;
			this.Production.Text = "     Production     ";
			// 
			// browserProduction
			// 
			this.browserProduction.ContainingControl = this;
			this.browserProduction.Enabled = true;
			this.browserProduction.Location = new System.Drawing.Point(13, 14);
			this.browserProduction.OcxState = ((System.Windows.Forms.AxHost.State)(resources.GetObject("browserProduction.OcxState")));
			this.browserProduction.Size = new System.Drawing.Size(643, 450);
			this.browserProduction.TabIndex = 0;
			this.browserProduction.Enter += new System.EventHandler(this.msglog_production_Enter);
			// 
			// Information
			// 
			this.Information.Controls.Add(this.browserInformation);
			this.Information.Location = new System.Drawing.Point(4, 49);
			this.Information.Name = "Information";
			this.Information.Size = new System.Drawing.Size(668, 481);
			this.Information.TabIndex = 1;
			this.Information.Text = "     Get Information     ";
			// 
			// browserInformation
			// 
			this.browserInformation.ContainingControl = this;
			this.browserInformation.Enabled = true;
			this.browserInformation.Location = new System.Drawing.Point(13, 15);
			this.browserInformation.OcxState = ((System.Windows.Forms.AxHost.State)(resources.GetObject("browserInformation.OcxState")));
			this.browserInformation.Size = new System.Drawing.Size(643, 457);
			this.browserInformation.TabIndex = 1;
			// 
			// Labels
			// 
			this.Labels.Controls.Add(this.reprintPromo1);
			this.Labels.Controls.Add(this.reprintInvoice);
			this.Labels.Controls.Add(this.reprintShipping);
			this.Labels.Controls.Add(this.browserReprint);
			this.Labels.Location = new System.Drawing.Point(4, 49);
			this.Labels.Name = "Labels";
			this.Labels.Size = new System.Drawing.Size(668, 481);
			this.Labels.TabIndex = 2;
			this.Labels.Text = "     Re-print Labels     ";
			// 
			// reprintPromo1
			// 
			this.reprintPromo1.Location = new System.Drawing.Point(264, 16);
			this.reprintPromo1.Name = "reprintPromo1";
			this.reprintPromo1.Size = new System.Drawing.Size(128, 21);
			this.reprintPromo1.TabIndex = 5;
			this.reprintPromo1.Text = "Promotional Artwork";
			this.reprintPromo1.CheckedChanged += new System.EventHandler(this.reprintPromo1_CheckedChanged);
			// 
			// reprintInvoice
			// 
			this.reprintInvoice.Location = new System.Drawing.Point(167, 14);
			this.reprintInvoice.Name = "reprintInvoice";
			this.reprintInvoice.Size = new System.Drawing.Size(100, 21);
			this.reprintInvoice.TabIndex = 4;
			this.reprintInvoice.Text = "Invoice";
			this.reprintInvoice.CheckedChanged += new System.EventHandler(this.reprintInvoice_CheckedChanged);
			// 
			// reprintShipping
			// 
			this.reprintShipping.Location = new System.Drawing.Point(33, 14);
			this.reprintShipping.Name = "reprintShipping";
			this.reprintShipping.Size = new System.Drawing.Size(111, 21);
			this.reprintShipping.TabIndex = 3;
			this.reprintShipping.Text = "Shipment Label";
			this.reprintShipping.CheckedChanged += new System.EventHandler(this.reprintShipping_CheckedChanged);
			// 
			// browserReprint
			// 
			this.browserReprint.ContainingControl = this;
			this.browserReprint.Enabled = true;
			this.browserReprint.Location = new System.Drawing.Point(13, 49);
			this.browserReprint.OcxState = ((System.Windows.Forms.AxHost.State)(resources.GetObject("browserReprint.OcxState")));
			this.browserReprint.Size = new System.Drawing.Size(643, 415);
			this.browserReprint.TabIndex = 1;
			// 
			// Status
			// 
			this.Status.Controls.Add(this.statusProofedPrinted);
			this.Status.Controls.Add(this.statusReturnedPackage);
			this.Status.Controls.Add(this.statusQueuedPrinted);
			this.Status.Controls.Add(this.statusBoxedPrinted);
			this.Status.Controls.Add(this.statusPrintedArtwork);
			this.Status.Controls.Add(this.statusPrintedDefective);
			this.Status.Controls.Add(this.browserStatus);
			this.Status.Location = new System.Drawing.Point(4, 49);
			this.Status.Name = "Status";
			this.Status.Size = new System.Drawing.Size(668, 481);
			this.Status.TabIndex = 3;
			this.Status.Text = "     Change Status     ";
			// 
			// statusProofedPrinted
			// 
			this.statusProofedPrinted.Location = new System.Drawing.Point(424, 8);
			this.statusProofedPrinted.Name = "statusProofedPrinted";
			this.statusProofedPrinted.Size = new System.Drawing.Size(96, 32);
			this.statusProofedPrinted.TabIndex = 7;
			this.statusProofedPrinted.Text = "Proofed to Printed";
			this.statusProofedPrinted.CheckedChanged += new System.EventHandler(this.statusProofedPrinted_CheckedChanged);
			// 
			// statusReturnedPackage
			// 
			this.statusReturnedPackage.Location = new System.Drawing.Point(528, 16);
			this.statusReturnedPackage.Name = "statusReturnedPackage";
			this.statusReturnedPackage.Size = new System.Drawing.Size(120, 21);
			this.statusReturnedPackage.TabIndex = 5;
			this.statusReturnedPackage.Text = "Returned Package";
			this.statusReturnedPackage.CheckedChanged += new System.EventHandler(this.statusReturnedPackage_CheckedChanged);
			// 
			// statusQueuedPrinted
			// 
			this.statusQueuedPrinted.Location = new System.Drawing.Point(328, 8);
			this.statusQueuedPrinted.Name = "statusQueuedPrinted";
			this.statusQueuedPrinted.Size = new System.Drawing.Size(96, 32);
			this.statusQueuedPrinted.TabIndex = 6;
			this.statusQueuedPrinted.Text = "Queued to Printed";
			this.statusQueuedPrinted.CheckedChanged += new System.EventHandler(this.statusQueuedPrinted_CheckedChanged);
			// 
			// statusBoxedPrinted
			// 
			this.statusBoxedPrinted.Location = new System.Drawing.Point(232, 8);
			this.statusBoxedPrinted.Name = "statusBoxedPrinted";
			this.statusBoxedPrinted.Size = new System.Drawing.Size(88, 32);
			this.statusBoxedPrinted.TabIndex = 2;
			this.statusBoxedPrinted.Text = "Boxed to Printed";
			this.statusBoxedPrinted.CheckedChanged += new System.EventHandler(this.statusBoxedPrinted_CheckedChanged);
			// 
			// statusPrintedArtwork
			// 
			this.statusPrintedArtwork.Location = new System.Drawing.Point(120, 8);
			this.statusPrintedArtwork.Name = "statusPrintedArtwork";
			this.statusPrintedArtwork.Size = new System.Drawing.Size(88, 32);
			this.statusPrintedArtwork.TabIndex = 4;
			this.statusPrintedArtwork.Text = "Printed to Art Problem";
			this.statusPrintedArtwork.CheckedChanged += new System.EventHandler(this.statusPrintedArtwork_CheckedChanged);
			// 
			// statusPrintedDefective
			// 
			this.statusPrintedDefective.Location = new System.Drawing.Point(13, 8);
			this.statusPrintedDefective.Name = "statusPrintedDefective";
			this.statusPrintedDefective.Size = new System.Drawing.Size(107, 32);
			this.statusPrintedDefective.TabIndex = 3;
			this.statusPrintedDefective.Text = "Printed to Defective";
			this.statusPrintedDefective.CheckedChanged += new System.EventHandler(this.statusPrintedDefective_CheckedChanged);
			// 
			// browserStatus
			// 
			this.browserStatus.ContainingControl = this;
			this.browserStatus.Enabled = true;
			this.browserStatus.Location = new System.Drawing.Point(13, 49);
			this.browserStatus.OcxState = ((System.Windows.Forms.AxHost.State)(resources.GetObject("browserStatus.OcxState")));
			this.browserStatus.Size = new System.Drawing.Size(643, 415);
			this.browserStatus.TabIndex = 1;
			// 
			// mainMenu1
			// 
			this.mainMenu1.MenuItems.AddRange(new System.Windows.Forms.MenuItem[] {
																					  this.menuItem2,
																					  this.menuItem1});
			// 
			// menuItem2
			// 
			this.menuItem2.Index = 0;
			this.menuItem2.MenuItems.AddRange(new System.Windows.Forms.MenuItem[] {
																					  this.menuItem4});
			this.menuItem2.Text = "File";
			// 
			// menuItem4
			// 
			this.menuItem4.Index = 0;
			this.menuItem4.Text = "Exit";
			this.menuItem4.Click += new System.EventHandler(this.menuItem4_Click_1);
			// 
			// menuItem1
			// 
			this.menuItem1.Index = 1;
			this.menuItem1.MenuItems.AddRange(new System.Windows.Forms.MenuItem[] {
																					  this.menuItem3});
			this.menuItem1.Text = "Settings";
			// 
			// menuItem3
			// 
			this.menuItem3.Index = 0;
			this.menuItem3.Text = "Setup";
			this.menuItem3.Click += new System.EventHandler(this.menuItem3_Click);
			// 
			// commandDesc
			// 
			this.commandDesc.Location = new System.Drawing.Point(47, 14);
			this.commandDesc.Name = "commandDesc";
			this.commandDesc.Size = new System.Drawing.Size(140, 14);
			this.commandDesc.TabIndex = 6;
			this.commandDesc.Text = "Ready";
			// 
			// signalLight
			// 
			this.signalLight.BackColor = System.Drawing.Color.ForestGreen;
			this.signalLight.Location = new System.Drawing.Point(27, 14);
			this.signalLight.Name = "signalLight";
			this.signalLight.Size = new System.Drawing.Size(13, 14);
			this.signalLight.TabIndex = 7;
			this.signalLight.TabStop = false;
			// 
			// projectNumberBox
			// 
			this.projectNumberBox.Location = new System.Drawing.Point(580, 14);
			this.projectNumberBox.Name = "projectNumberBox";
			this.projectNumberBox.Size = new System.Drawing.Size(107, 20);
			this.projectNumberBox.TabIndex = 28;
			this.projectNumberBox.Text = "";
			this.projectNumberBox.KeyPress += new System.Windows.Forms.KeyPressEventHandler(this.projectNumberBox_KeyPress);
			// 
			// label1
			// 
			this.label1.Location = new System.Drawing.Point(520, 14);
			this.label1.Name = "label1";
			this.label1.Size = new System.Drawing.Size(60, 21);
			this.label1.TabIndex = 29;
			this.label1.Text = "Project ID:";
			// 
			// brCod
			// 
			this.brCod.ApplyTilde = false;
			this.brCod.BackColor = System.Drawing.Color.White;
			this.brCod.BarHeightCM = "1.000";
			this.brCod.CaptionAbove = "";
			this.brCod.CaptionBelow = "";
			this.brCod.CaptionBottomAlignment = System.Drawing.StringAlignment.Center;
			this.brCod.CaptionBottomColor = System.Drawing.Color.Black;
			this.brCod.CaptionBottomSpace = "0.30";
			this.brCod.CaptionFont = new System.Drawing.Font("Times New Roman", 10F, System.Drawing.FontStyle.Regular, System.Drawing.GraphicsUnit.Pixel);
			this.brCod.CaptionTopAlignment = System.Drawing.StringAlignment.Center;
			this.brCod.CaptionTopColor = System.Drawing.Color.Black;
			this.brCod.CaptionTopSpace = "0.30";
			this.brCod.CheckCharacter = true;
			this.brCod.CheckCharacterInText = false;
			this.brCod.CODABARStartChar = "A";
			this.brCod.CODABARStopChar = "B";
			this.brCod.Code128Set = IDAutomation.Windows.Forms.LinearBarCode.Barcode.Code128CharacterSets.Auto;
			this.brCod.DataToEncode = "12345678";
			this.brCod.FitControlToBarcode = true;
			this.brCod.ForeColor = System.Drawing.Color.Black;
			this.brCod.LeftMarginCM = "0.200";
			this.brCod.Location = new System.Drawing.Point(433, -14);
			this.brCod.Name = "brCod";
			this.brCod.NarrowToWideRatio = "2.0";
			this.brCod.PostnetHeightShort = "0.1270";
			this.brCod.PostnetHeightTall = "0.3226";
			this.brCod.PostnetSpacing = "0.066";
			this.brCod.Resolution = IDAutomation.Windows.Forms.LinearBarCode.Barcode.Resolutions.Printer;
			this.brCod.ResolutionCustomDPI = "-3.00";
			this.brCod.ResolutionPrinterToUse = "";
			this.brCod.RotationAngle = IDAutomation.Windows.Forms.LinearBarCode.Barcode.RotationAngles.Zero_Degrees;
			this.brCod.ShowText = true;
			this.brCod.ShowTextLocation = IDAutomation.Windows.Forms.LinearBarCode.Barcode.HRTextPositions.Bottom;
			this.brCod.Size = new System.Drawing.Size(94, 65);
			this.brCod.SuppSeparationCM = "0.500";
			this.brCod.SymbologyID = IDAutomation.Windows.Forms.LinearBarCode.Barcode.Symbologies.Code128;
			this.brCod.TabIndex = 30;
			this.brCod.TextFontColor = System.Drawing.Color.Black;
			this.brCod.TextMarginCM = "0.100";
			this.brCod.TopMarginCM = "0.200";
			this.brCod.UPCESystem = "1";
			this.brCod.Visible = false;
			this.brCod.XDimensionCM = "0.0300";
			this.brCod.XDimensionMILS = "11.8000";
			// 
			// printBtn
			// 
			this.printBtn.BackColor = System.Drawing.Color.LightCoral;
			this.printBtn.Location = new System.Drawing.Point(287, 7);
			this.printBtn.Name = "printBtn";
			this.printBtn.Size = new System.Drawing.Size(146, 28);
			this.printBtn.TabIndex = 31;
			this.printBtn.Text = "Print Invoice - Hit ( F10 )";
			this.printBtn.Click += new System.EventHandler(this.printBtn_Click);
			// 
			// Form1
			// 
			this.AutoScaleBaseSize = new System.Drawing.Size(5, 13);
			this.BackColor = System.Drawing.SystemColors.Info;
			this.ClientSize = new System.Drawing.Size(710, 597);
			this.Controls.Add(this.printBtn);
			this.Controls.Add(this.brCod);
			this.Controls.Add(this.projectNumberBox);
			this.Controls.Add(this.label1);
			this.Controls.Add(this.signalLight);
			this.Controls.Add(this.commandDesc);
			this.Controls.Add(this.tabControl1);
			this.FormBorderStyle = System.Windows.Forms.FormBorderStyle.Fixed3D;
			this.Icon = ((System.Drawing.Icon)(resources.GetObject("$this.Icon")));
			this.Menu = this.mainMenu1;
			this.Name = "Form1";
			this.Text = "PME Link";
			this.Paint += new System.Windows.Forms.PaintEventHandler(this.Form1_Paint);
			this.tabControl1.ResumeLayout(false);
			this.Production.ResumeLayout(false);
			((System.ComponentModel.ISupportInitialize)(this.browserProduction)).EndInit();
			this.Information.ResumeLayout(false);
			((System.ComponentModel.ISupportInitialize)(this.browserInformation)).EndInit();
			this.Labels.ResumeLayout(false);
			((System.ComponentModel.ISupportInitialize)(this.browserReprint)).EndInit();
			this.Status.ResumeLayout(false);
			((System.ComponentModel.ISupportInitialize)(this.browserStatus)).EndInit();
			this.ResumeLayout(false);

		}
		#endregion

		/// <summary>
		/// The main entry point for the application.
		/// </summary>
		[STAThread]
		static void Main() 
		{
			Application.Run(new Form1());
		}

		static public void ShowError(string ErrorMessage)
		{
			MessageBox.Show(ErrorMessage, "An error has occured.");
		}

		// We want to let the main thread now when we have received a command from the server
		// ... that a promotional product should be printed.
		public void setLastPromoArtworkDelegateCom (string promoDelegate)
		{
			this.lastPromoArtworkDelegateCommand = promoDelegate;
		}

		public void ShowPrintButton(string projectIDtoPrint)
		{
			this.projectIDinUse = projectIDtoPrint;
			this.printBtn.Visible = true;
		}

		public void HidePrintButton()
		{
			this.projectIDinUse = "0";
			this.printBtn.Visible = false;

		}
		
		// Will refresh the form completely... we may want to do this before printing something
		// That way we can see what is going on with the form before the printer "hangs" to process the printing
		public void RefreshForm()
		{
			
			this.Invalidate(true);
		}


		private void msglog_production_Enter(object sender, System.EventArgs e)
		{

		}

		private void CmdQueue_OnQueueChange( bool queueIsEmpty )
		{
			if(queueIsEmpty)
			{
				this.commandDesc.Text = "Ready";
				this.signalLight.BackColor = System.Drawing.Color.ForestGreen;
				this.commandDesc.Refresh();
				this.signalLight.Refresh();
			}
			else
			{
				this.commandDesc.Text = this.CmdQueue.CurrentCommandDescription();
				this.signalLight.BackColor = System.Drawing.Color.Firebrick;
				this.commandDesc.Refresh();
				this.signalLight.Refresh();
			}
		}



		private void menuItem3_Click(object sender, System.EventArgs e)
		{
			this.setupForm.ShowDialog();
		}

		private void menuItem4_Click(object sender, System.EventArgs e)
		{
			menuItem4.Checked = !menuItem4.Checked;
		}




		private void projectNumberBox_KeyPress(object sender, System.Windows.Forms.KeyPressEventArgs e)
		{
			// We are interested in trapping the "Enter Key".  return otherwise
			if(e.KeyChar != (char)13)
				return;
		
			// Make sure the project number sticks to the format of "P334343";
			Regex patternMatch = new Regex(@"^(P|p)\d+$");
			
			if( !patternMatch.IsMatch( projectNumberBox.Text ))
			{
				Form1.ShowError( "Please enter a valid project number. EX: P342343" );
				return;
			}

			// Get rid of the P, so we just have the raw ProjectID
			string projNum = projectNumberBox.Text.Substring(1);

			// Wipe it out for the next scan
			projectNumberBox.Text = "";

			
			// Create a new Command Detail Object, depending on which tab is slected.
			int tabSelected = this.tabControl1.SelectedIndex;

			if( tabSelected == 0 )
			{
				CommandDetail CmdDt = new CommandDetail(this.CmdQueue, this.msgLogProduction, this.setupForm.appSetting, this.brCod, this);
				CmdDt.command = CommandDetail.CommandType.ProductionScan;
				CmdDt.projectNumber = projNum;
				this.CmdQueue.AddCommand(CmdDt);
			}
			else if( tabSelected == 1 )
			{
				CommandDetail CmdDt = new CommandDetail(this.CmdQueue, this.msgLogInformation, this.setupForm.appSetting, this.brCod, this);
				CmdDt.command = CommandDetail.CommandType.InformationScan;
				CmdDt.projectNumber = projNum;
				this.CmdQueue.AddCommand(CmdDt);
			}
			else if( tabSelected == 2 )
			{
				CommandDetail CmdDt = new CommandDetail(this.CmdQueue, this.msgLogReprint, this.setupForm.appSetting, this.brCod, this);
				CmdDt.projectNumber = projNum;
				
				if( this.reprintRadioSelected == "ShippingLabel" )
					CmdDt.command = CommandDetail.CommandType.ReprintShippingLabel;
				else if( this.reprintRadioSelected == "Invoice" )
					CmdDt.command = CommandDetail.CommandType.ReprintInvoice;
				else if( this.reprintRadioSelected == "PromoArtwork1" )
					CmdDt.command = CommandDetail.CommandType.ReprintPromoArtwork1;
				else
				{
					Form1.ShowError("Undefined radio selection: " + this.reprintRadioSelected);
					return;
				}

				this.CmdQueue.AddCommand(CmdDt);
			}
			else if( tabSelected == 3 )
			{
				CommandDetail CmdDt = new CommandDetail(this.CmdQueue, this.msgLogStatus, this.setupForm.appSetting, this.brCod, this);
				CmdDt.projectNumber = projNum;
				
				if( this.statusRadioSelected == "PrintedToDefective" )
					CmdDt.command = CommandDetail.CommandType.StatusPrintedToDefective;
				else if( this.statusRadioSelected == "PrintedToArtwork" )
					CmdDt.command = CommandDetail.CommandType.StatusPrintedToArtworkProblem;
				else if( this.statusRadioSelected == "BoxedToPrinted" )
					CmdDt.command = CommandDetail.CommandType.StatusBoxedToPrinted;
				else if( this.statusRadioSelected == "QueuedToPrinted" )
					CmdDt.command = CommandDetail.CommandType.StatusQueuedToPrinted;
				else if( this.statusRadioSelected == "ProofedToPrinted" )
					CmdDt.command = CommandDetail.CommandType.StatusProofedToPrinted;
				else if( this.statusRadioSelected == "ReturnedPackage" )
					CmdDt.command = CommandDetail.CommandType.StatusReturnedPackage;
				else
				{
					Form1.ShowError("Undefined radio selection: " + this.reprintRadioSelected);
					return;
				}

				this.CmdQueue.AddCommand(CmdDt);
			}
			else
			{
				Form1.ShowError( "This tab has not been configured for sending commands." );
				return;
			}



		}

		private void tabControl1_SelectedIndexChanged(object sender, System.EventArgs e)
		{
			projectNumberBox.Focus();
			projectNumberBox.Text = "";

		
		}

		private void Form1_Paint(object sender, System.Windows.Forms.PaintEventArgs e)
		{
			// Draw a box around the status
			Graphics dc = this.CreateGraphics();
			this.Show();
			Pen BluePen = new Pen(Color.Black, 1);
			dc.DrawRectangle(BluePen, 25,12,205,23);

			projectNumberBox.Focus();
		}

		private void statusPrintedDefective_CheckedChanged(object sender, System.EventArgs e)
		{
			this.statusRadioSelected = "PrintedToDefective";
			projectNumberBox.Focus();
		}

		private void statusPrintedArtwork_CheckedChanged(object sender, System.EventArgs e)
		{
			this.statusRadioSelected = "PrintedToArtwork";
			projectNumberBox.Focus();
		}

		private void statusBoxedPrinted_CheckedChanged(object sender, System.EventArgs e)
		{
			this.statusRadioSelected = "BoxedToPrinted";
			projectNumberBox.Focus();
		}
		private void statusQueuedPrinted_CheckedChanged(object sender, System.EventArgs e)
		{
			this.statusRadioSelected = "QueuedToPrinted";
			projectNumberBox.Focus();
		}
		private void statusProofedPrinted_CheckedChanged(object sender, System.EventArgs e)
		{
			this.statusRadioSelected = "ProofedToPrinted";
			projectNumberBox.Focus();
		}
		private void statusReturnedPackage_CheckedChanged(object sender, System.EventArgs e)
		{
			this.statusRadioSelected = "ReturnedPackage";
			projectNumberBox.Focus();
		}


		private void reprintShipping_CheckedChanged(object sender, System.EventArgs e)
		{
			this.reprintRadioSelected = "ShippingLabel";
			projectNumberBox.Focus();
		}

		private void reprintInvoice_CheckedChanged(object sender, System.EventArgs e)
		{
			this.reprintRadioSelected = "Invoice";
			projectNumberBox.Focus();
		}

		private void menuItem4_Click_1(object sender, System.EventArgs e)
		{
			Application.Exit();
		}

		private void printBtn_Click(object sender, System.EventArgs e)
		{
			this.printInvoiceAndShippingLabel();

		}

		private void printInvoiceAndShippingLabel()
		{
			if(this.projectIDinUse == "0")
				Form1.ShowError("An unknown error occured.  The project ID in use is set to 0.");

			// This variable may get errased within one of the multi-threaded calls below
			// we want to protect it so we can issue it laster on through another function call.
			string currentProjectIDinUse = this.projectIDinUse;

			CommandDetail CmdDt1 = new CommandDetail(this.CmdQueue, this.msgLogProduction, this.setupForm.appSetting, this.brCod, this);
			CommandDetail CmdDt2 = new CommandDetail(this.CmdQueue, this.msgLogProduction, this.setupForm.appSetting, this.brCod, this);


			// Setup a new command to print the shipping label
			CmdDt1.projectNumber = this.projectIDinUse;
			CmdDt1.command = CommandDetail.CommandType.PrintShippingLabel;
			
			// Setup a new command to print the invoice
			CmdDt2.projectNumber = this.projectIDinUse;
			CmdDt2.command = CommandDetail.CommandType.FetchAndPrintInvoice;

			// Make sure focus is always back on the input box and ready for a new barcode scan
			this.projectNumberBox.Focus();

			// Add the commands to our queue
			this.CmdQueue.AddCommand(CmdDt1);
			this.CmdQueue.AddCommand(CmdDt2);

			// Possibly print a promotional Artwork to go with the order
			this.PossiblyPrintPromoArtwork(this.lastPromoArtworkDelegateCommand, currentProjectIDinUse);

		}

		public void PossiblyPrintPromoArtwork(string promoDelegateFromTheServer, string projectNum)
		{

			// We may have received a command from the server telling us that we should print a certain type of Promotional material
			if(promoDelegateFromTheServer != "")
			{
				// Find out if the command from the server matches a Command name that this Client Application is willing handle.
				// Right now we have only configured one Promotional printer... but in the future this application could be setup to handle many.
				if(this.setupForm.appSetting.promoCommand_1 == promoDelegateFromTheServer && this.setupForm.appSetting.promoActive_1)
				{
					CommandDetail CmdDt2 = new CommandDetail(this.CmdQueue, this.msgLogProduction, this.setupForm.appSetting, this.brCod, this);

					CmdDt2.projectNumber = projectNum;
					CmdDt2.command = CommandDetail.CommandType.FetchAndPrintPromoArtwork1;
					this.CmdQueue.AddCommand(CmdDt2);
				}
			}

		}



		// We want to trap the F10 key... in case they are trying to print the invoice and shipping ID
		protected override bool ProcessCmdKey(ref Message msg, Keys keyData)
		{
			const int WM_KEYDOWN = 0x100;
			const int WM_SYSKEYDOWN = 0x104;

			if ((msg.Msg == WM_KEYDOWN) || (msg.Msg == WM_SYSKEYDOWN))
			{
				switch(keyData)
				{
					case Keys.F10:

						// OK so they pressed the F10 key.  Now lets make sure that we have a Project ID to print
						// If it is "0" then there is no project ID in memory and the button should not be displayed
						if(this.projectIDinUse != "0")
						{
							this.printInvoiceAndShippingLabel();

							// The F10 key will normally bring up the Menu settings.
							// We don't want to do that or we will lose focus on our input box
							return true;
						}

						break;
				}
			}
			return base.ProcessCmdKey(ref msg,keyData);
		}
	

		private void reprintPromo1_CheckedChanged(object sender, System.EventArgs e)
		{
			this.reprintRadioSelected = "PromoArtwork1";
			projectNumberBox.Focus();
		}



		// Pass in a COMport # as a string like "COM1", 
		// Pass in the duration in milliseconds you want to listen for and it will return any data that it captured during that period.
		// buffer size if the amount of data that HAS TO fill up the buffer... if it can not fill up the buffer within the specified time then this method will return blank.
		// It will return whatever has filled in the buffer first... and discard any bytes received afterwards.
		// If you start listening at the wrong time you can split the frame-rate... Therefore if you are expecting 3 characters to come at a time a 100 millisecond intervals.
		// ... you should set the buffer size to 6 characters and listen for 220 milliseconds to be sure that you got the full chunk.  Use delimeters within the string to identify start/breaks in commands.
		public string listenToPort(string comPort, int durationMiliseconds, int bufferSize)
		{
			// clean this variable everytime we are about to start capturing more data.
			string serialPortDataReceived = "";

			if(comPort.Length != 4)
				return "Error, the Com Port setting must contain 4 characters... the last of which is a digit.";

			int comNumber = Convert.ToInt32(comPort.Substring((comPort.Length - 1), 1));
		
			try
			{
			
				this.port.ComPort = comNumber;
				this.port.Timeout = durationMiliseconds;
				this.port.ByteSize = 8;
				this.port.Parity = SerialNET.Parity.No;
				this.port.BaudRate = 9600;
				this.port.StopBits = SerialNET.StopBits.One;
				this.port.Handshake = SerialNET.Handshake.None;
				this.port.DTR = false;
				this.port.Enabled = true;

				serialPortDataReceived = this.port.Read(bufferSize, durationMiliseconds);

				if(serialPortDataReceived == null)
					serialPortDataReceived = "";

			}
			catch(Exception ex)
			{
				Form1.ShowError("Com Port could not be opened.\nMaybe you selected the wrong port number under the Promotional Artwork settings?\nError Message:" + ex.Message);
			}

			this.port.Enabled = false;

			return serialPortDataReceived;


		}

		// Will return a string if there is a message coming from a Com Port... otherwise returns null.
		// No news is good news for thid method.
		// Translates Character codes from the Com Port into English based on our Application Settings.
		// Returns the first translation matched... so organize translations codes in the App Settings based on priority.
		// Strings to save in the AppSettings are name value pairs separated by Pipe Symbols and Equals signs... 
		// ... and example might look like ........     NOTHING=No Response From Machine|P34=OK|Err=Pen machine is returning an Error code.
		public string checkForMessagesOnComPorts()
		{
			// Refresh application settings... it is possible that it could have changed if some saved new parameters under the "Setup Form".
			// We are not "invalidating" the parameters stored in Form1.appSettingsObj when new parameters are saved... so just load them from the Registry on each function call.
			this.appSettingObj = new ApplicationSettings();

			if(this.appSettingObj.promoComPortNumber_1 == "None" || !this.appSettingObj.promoActive_1)
				return null;

			string dataFromPort = this.listenToPort(this.appSettingObj.promoComPortNumber_1, 220, 6);
			
			//Com Port Translations are separated between Pipe Symbols and Equals signs.
			string comPortTranslationsText = this.appSettingObj.promoComTranslations_1;

			// a Default message, in case we can't find a match on our Com Port Translations within the Setup Screen.
			string retMsg = "A Pattern match was not found for Promotional Artwork #1.  Maybe De-Activate the PromotionalArtwork ComPort in the 'Settings'.  ";
			retMsg += "Or you can try put something like NOTHING=OK in the Com Port Translation.  There must always be a match coming back from a com port. ";
			retMsg += "The following data from the Com Port was captured... " + (dataFromPort == null ? "NULL" : dataFromPort);

			// Split upon Pipe symbols.
			string [] translationArr = Regex.Split(comPortTranslationsText, "\\|");

			foreach (string translatePart in translationArr) 
			{

				string [] translateComponents = Regex.Split(translatePart, "=");

				if(translateComponents.Length != 2)
					continue;

				// Nothing is a special keyword.   It means that if this is set in our App Settings and we don't hear anything from the port... then return the corresponding message.
				if(translateComponents[0].ToUpper() == "NOTHING" && translateComponents[1].ToUpper() != "OK" && dataFromPort == "")
				{
					retMsg = translateComponents[1];
					break;
				}
				// We have to specify in our port translation if it is OK to receive Nothing from the com Port.
				else if(translateComponents[0].ToUpper() == "NOTHING" && translateComponents[1].ToUpper() == "OK" && dataFromPort == "")
				{
					retMsg = null;
				}
				// A special keyword "OK" after the match (in our appsettings), which is after the equal sign... means that if we find a match on the Name portion (before the equals sign)
				// That this method will not return a message... will return null.   No news is good news in this method.
				// However, it will not break the loop... this method will still look for messages coming from other com ports.
				else if(translateComponents[1].ToUpper() == "OK" && dataFromPort.ToUpper().IndexOf(translateComponents[0].ToUpper()) != -1)
				{
					retMsg = null;
				}
				else if(dataFromPort != "" && dataFromPort.ToUpper().IndexOf(translateComponents[0].ToUpper()) != -1)
				{
					retMsg = translateComponents[1];
					break;
				}
			}

			return retMsg;

		}









	}

}
