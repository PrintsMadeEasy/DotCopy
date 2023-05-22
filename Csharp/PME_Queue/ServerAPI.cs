using System;
using System.Xml;
using System.Web;
using System.Net;
using System.Collections;
using System.Text;
using System.IO;
using System.Text.RegularExpressions;

namespace PME_Queue
{
	/// <summary>
	/// Summary description for ServerAPI.
	/// </summary>
	public class ServerAPI
	{
		private string apiURL;
		private string apiCommand;
		private string userName;
		private string passwd;
		private string projectCap;
		private string maxPages;
		private bool errorFlag;
		private string errorText;
		private string curTag;

		private ApplicationSettings appSettings;
		private int batchRequestCounter;

		// Holds data returned from the API
		private string apiResult;
		private string apiMessage;
		private ProjectCountRequest[] apiProjectCountRequestArr;
		private Hashtable apiProjectCountReturnHash;
		private Hashtable apiPageCountReturnHash;
		private string projectListPipeSeparated;
		private string projectCnt;
		private string statusChar;
		private string statusCharChange;
		private string fNamePrefix;
		private string pdfProfile;
		private string filenamePDF;
		private string percentComplete;
		private string pageCount;
		private string downloadURL;
		private string prodID;

		// Sends out an event everytime a command notifies us of a progress update... such as a percentage value
		// ... when generating large artwork files.
		public delegate void ProgressUpdateDelegate( string progressDesc );
		public event ProgressUpdateDelegate OnProgressChange;

		public ServerAPI( ApplicationSettings appSet )
		{
			this.userName = appSet.UserName;
			this.passwd = appSet.Password;
			this.apiURL = appSet.APIurl;
			this.projectCap = appSet.ProjectCap;
			this.maxPages = appSet.PagesMax;
			this.appSettings = appSet;
			this.projectListPipeSeparated = "";
			this.statusChar = "";
			this.statusCharChange = "";
			this.fNamePrefix = "";
			this.pdfProfile = "";
			this.filenamePDF = "";
			this.percentComplete = "";
			this.pageCount = "";
			this.downloadURL = "";
			this.prodID = "";
			
			// Arrays of information that correspond to a Project Count call.  The Index is the BatchID.
			this.apiProjectCountReturnHash = new Hashtable();
			this.apiPageCountReturnHash = new Hashtable();

			this.ClearProjectCountRequest();
		}
		public void ClearProjectCountRequest()
		{

			this.apiProjectCountRequestArr = new ProjectCountRequest[2000];
			this.batchRequestCounter = 0;
		}
		// Keep adding ProjectCountRequests structures to our array.
		// When we contact the server through the API we may want counts on many batches (all in 1 request).
		public void addBatchCountRequest(ProjectCountRequest pCntReq)
		{
			this.apiProjectCountRequestArr[this.batchRequestCounter] = pCntReq;
			this.batchRequestCounter++;


		}
		
		// Call this method to fire an event of a progress update... like a percentage of arwtwork generated on the server
		private void ProgressChanged(string progressDescription)
		{
			if( OnProgressChange != null )
				OnProgressChange( progressDescription );			
		}

		public void FireRequest()
		{
			// Start off with an error, let it prove otherwise
			this.errorFlag = true;
			this.errorText = "XML communication has not been intialized.";
			this.apiResult = "";
			this.apiMessage = "";
			this.curTag = "";
	
			
			// If we are requesting a project count... make sure to clear out any previous results first
			if(this.apiCommand == "get_project_count")
			{
				this.apiProjectCountReturnHash.Clear();
				this.apiPageCountReturnHash.Clear();
			}

			// If we are downloading artwork... set the progress to 1% initially... just to show some action right off the bat
			// It will continue to update the status as the XML file is being read in as well
			if(this.apiCommand == "generate_pdf_doc_from_projectlist")
				this.ProgressChanged( "1% Complete");

	
			try
			{
				if(this.apiURL.ToString() == "")
				{
					Form1.ShowError("You Forget to configure the API URL under 'Settings'");
				}

				HttpWebRequest HttpWReq = 
					(HttpWebRequest)WebRequest.Create(this.apiURL);

				string postString = this.GetAPIurl();

				HttpWReq.Method = "POST";
				HttpWReq.ContentLength = postString.Length;
				HttpWReq.ContentType = "application/x-www-form-urlencoded";        

				// Now write the Post Data to the server.
				StreamWriter myWriter = new StreamWriter(HttpWReq.GetRequestStream());
				try
				{
					myWriter.Write(postString);
				}
				catch (Exception e) 
				{
					Form1.ShowError("An error occured posting data to the API: " + e.Message);
				}
				finally 
				{
					myWriter.Close();
				}

				HttpWebResponse HttpWResp = (HttpWebResponse)HttpWReq.GetResponse();

				Stream ReceiveStream = HttpWResp.GetResponseStream();

				Encoding encode = System.Text.Encoding.GetEncoding("utf-8");

				// Pipe the stream to a higher level stream reader with the required encoding format. 
				StreamReader readStream = new StreamReader( ReceiveStream, encode );
				Char[] read = new Char[256];

				// Keep adding to our xml string that will later be parsed.
				string totalXMLfile = "";

				// Read 256 charcters at a time.    
				int count = readStream.Read( read, 0, 256 );

				while (count > 0) 
				{
					String str = new String(read, 0, count);
					totalXMLfile += str;
					count = readStream.Read(read, 0, 256);

					// If we are generating a lot of artworks then it may take a long time.
					// We want to listen in on the XML that is streaming in...
					// The API will return progress nodes showing the percentage of completion... the webserver will flush output everytime a new project is generated
					if(this.apiCommand == "generate_pdf_doc_from_projectlist")
					{
						Match m = Regex.Match(str, @"<percent>(\d+)</percent>");
						if(m.Success)
						{
							Group g = m.Groups[1];
							//Form1.ShowError("Percent: " + g.Value);
							this.ProgressChanged(g.Value + "% Complete");

						}
					}
				}

				readStream.Close();
				HttpWResp.Close();



				// Now that we have downloaded the XML from our API... we want to parse it
				// True that we could have passed the PME API URL directly to the constructor of XmlTextReader
				// ... however that would not allow us to get the buffer line by line as it is flushed from the web server so that we can show real-time progress from the API
				StringReader strR = new StringReader(totalXMLfile);
				XmlTextReader reader = new XmlTextReader(strR);

				string contents = "";
				string carrot_str = "";
				string currentBatchID = "";

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
							case "^server_response^batch_count":
								if( reader.HasAttributes )
								{
									reader.MoveToAttribute(0);

									// The attribute is parsed before the value is... 
									// Just set the BatchID in memory now... it will be used later as the key to the hash when we come across the next tag
									currentBatchID = reader.Value;
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

							// For PDF file generation
							case "^server_response^filename":
								this.filenamePDF = reader.Value; 
								break;
							case "^server_response^download_url":
								this.downloadURL = reader.Value; 
								break;
							case "^server_response^progress^percent":
								this.percentComplete = reader.Value; 
								break;
							case "^server_response^progress^pages":
								this.pageCount = reader.Value; 
								break;

							// For Project Counts
							case "^server_response^batch_count":
								this.apiProjectCountReturnHash.Add( currentBatchID, reader.Value ); 
								break;
							case "^server_response^project_list":
								this.projectListPipeSeparated = reader.Value; 
								break;
							case "^server_response^project_count":
								this.projectCnt = reader.Value; 
								break;
							case "^server_response^impressions_count":
								this.apiPageCountReturnHash.Add( currentBatchID, reader.Value ); 
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

				reader.Close();

			}
			catch ( System.Exception e )
			{
				this.errorFlag = true;
				this.errorText = "A communication error has occured.  Please ensure that you have not lost your internet connection.<br>" + e.Message;
			}
		}


		// Get a URL for issuing to the API depending on what properties have been set.
		// Does not inlcude the API name. Just the string that follows after the question mark (if it were a GET method).  This is the POST data. 
		public string GetAPIurl()
		{
			string commandURL = "username=" + this.userName + "&password=" + this.passwd + "&command=" + this.apiCommand;

			if( this.apiCommand == "get_project_count" )
			{
				// Pipe delimited list of all batch IDs that we are requesting 
				// The BatchID has no significance on the server.  It is only for out internal records, and it should be unique
				string batchIDlist = ""; 

				// Build a URL request string for all of the batches that we are requesting counts on
				for(int i=0; i < this.batchRequestCounter; i++)
				{
	
					string thisBatchID = this.apiProjectCountRequestArr[i].batchID;
					batchIDlist += thisBatchID + "|";

					commandURL += "&sts_"+ thisBatchID +"=" + this.apiProjectCountRequestArr[i].statusOnServer;
					commandURL += "&pID_"+ thisBatchID +"=" + this.apiProjectCountRequestArr[i].productID;
					commandURL += "&Opt_"+ thisBatchID +"=" + HttpUtility.UrlEncode(this.apiProjectCountRequestArr[i].projectOptions);
					commandURL += "&Shp_"+ thisBatchID +"=" + this.apiProjectCountRequestArr[i].shippingMethods;
					commandURL += "&prf_"+ thisBatchID +"=" + this.apiProjectCountRequestArr[i].pdfProfile;
					commandURL += "&mxPrQ_"+ thisBatchID +"=" + this.apiProjectCountRequestArr[i].maxProjectQuantity;
					commandURL += "&mnPrQ_"+ thisBatchID +"=" + this.apiProjectCountRequestArr[i].minProjectQuantity;

					// Translate a bool value into 'yes' or 'no' for the API
					if(this.apiProjectCountRequestArr[i].dueToday)
						commandURL += "&DT_"+ thisBatchID +"=yes";
					else
						commandURL += "&DT_"+ thisBatchID +"=no";

					// Translate a bool value for Urgent into a Priority Character 'U'rgent
									// Otherwise don't specify a priority, so all projects regardless of the priority character will be picked up
					if(this.apiProjectCountRequestArr[i].urgent)
						commandURL += "&Pri_"+ thisBatchID +"=U";
					else
						commandURL += "&Pri_"+ thisBatchID +"=";
				}

				commandURL += "&batch_id_list=" + batchIDlist;

				
			}
			else if( this.apiCommand == "get_project_list" )
			{
				if(this.batchRequestCounter != 1)
					Form1.ShowError("There must be 1 and only 1 ProjectCount object set if you want to request a ProjectList from the server");

				commandURL += "&status=" + this.apiProjectCountRequestArr[0].statusOnServer;
				commandURL += "&productid=" + this.apiProjectCountRequestArr[0].productID;
				commandURL += "&options=" + HttpUtility.UrlEncode(this.apiProjectCountRequestArr[0].projectOptions);
				commandURL += "&shippingmethods=" + this.apiProjectCountRequestArr[0].shippingMethods;
				commandURL += "&pdf_profile=" + this.apiProjectCountRequestArr[0].pdfProfile;
				commandURL += "&maxProjectQuantity=" + this.apiProjectCountRequestArr[0].maxProjectQuantity;
				commandURL += "&minProjectQuantity=" + this.apiProjectCountRequestArr[0].minProjectQuantity;

				// If we have a Project Cap and MaxPages setting initialized for our Particular Batch... then use that over the global settings.
				if(this.apiProjectCountRequestArr[0].projectCap == "" || this.apiProjectCountRequestArr[0].projectCap == "0")
					commandURL += "&projectcap=" + this.projectCap;
				else
					commandURL += "&projectcap=" + this.apiProjectCountRequestArr[0].projectCap;
				if(this.apiProjectCountRequestArr[0].maxPages == "" || this.apiProjectCountRequestArr[0].maxPages == "0")
					commandURL += "&maxpages=" + this.maxPages;
				else
					commandURL += "&maxpages=" + this.apiProjectCountRequestArr[0].maxPages;
				

				// Translate a bool value into 'yes' or 'no' for the API
				if(this.apiProjectCountRequestArr[0].dueToday)
					commandURL += "&duetoday=yes";
				else
					commandURL += "&duetoday=no";

				// Translate a bool value for Urgent into a Priority Character 'U'rgent
				// Otherwise don't specify a priority, so all projects regardless of the priority character will be picked up
				if(this.apiProjectCountRequestArr[0].urgent)
					commandURL += "&priority=U";
				else
					commandURL += "&priority=";
			}
			else if( this.apiCommand == "change_project_status_bulk" )
			{
				if(this.projectListPipeSeparated == "")
					Form1.ShowError("The project list was not set before calling the command change_project_status_bulk ");

				commandURL += "&status=" + this.statusCharChange;
				commandURL += "&oldstatus=" + this.statusChar;
				commandURL += "&project_list=" + this.projectListPipeSeparated;
			}
			else if( this.apiCommand == "generate_pdf_doc_from_projectlist" )
			{
				if(this.projectListPipeSeparated == "")
					Form1.ShowError("The project list was not set before calling the command change_project_status_bulk ");

				commandURL += "&pdf_profile_name=" + this.pdfProfileName;
				commandURL += "&filename_prefix=" + this.fileNamePrefix;
				commandURL += "&productid=" + this.prodID;
				commandURL += "&project_list=" + this.projectListPipeSeparated;

			}
			else
			{
				Form1.ShowError("The API command has not been defined: " + this.apiCommand );
				commandURL = "an error has occured";
			}

			return commandURL;
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
		public string message
		{
			get
			{
				return this.apiMessage;
			}
		}
		
		// Returns a hash that correspends with a API call to get_project_count.
		// The Index to the Hash is the BatchID and the value is the amount of projects for the batch.
		public Hashtable projectCountHash
		{
			get
			{
				return this.apiProjectCountReturnHash;
			}
		}

		// Returns a hash that correspends with a API call to get_project_count.
		// The Index to the Hash is the BatchID and the value is the amount of page impressions required to print all of the projects.
		public Hashtable pageCountHash
		{
			get
			{
				return this.apiPageCountReturnHash;
			}
		}
		public string projectCount
		{
			get
			{
				return this.projectCnt;
			}
		}
		public string projectList
		{
			get
			{
				return this.projectListPipeSeparated;
			}
			set
			{
				this.projectListPipeSeparated = value;
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
		public string fileNamePrefix
		{
			get
			{
				return this.fNamePrefix;
			}
			set
			{
				this.fNamePrefix = value;
			}
		}
		public string pdfProfileName
		{
			get
			{
				return this.pdfProfile;
			}
			set
			{
				this.pdfProfile = value;
			}
		}
		public string fileNameOfPDFdoc
		{
			get
			{
				return this.filenamePDF;
			}
		}
		public string pageCountInPDF
		{
			get
			{
				return this.pageCount;
			}
		}
		public string downloadURLforPDF
		{
			get
			{
				return this.downloadURL;
			}
		}
		public string productID
		{
			get
			{
				return this.prodID;
			}
			set
			{
				this.prodID = value;
			}
		}
		

		#endregion
	}

	// Contains all of the fields necessary to make a request to the server for a batch Count
	public class ProjectCountRequest
	{
		public string batchID;
		public string statusOnServer;
		public string productID;
		public string projectOptions;
		public string shippingMethods;
		public string pdfProfile;
		public bool dueToday;
		public bool urgent;
		public string projectCap;
		public string maxPages;
		public string minProjectQuantity;
		public string maxProjectQuantity;

		public ProjectCountRequest()
		{
			this.batchID = "";
			this.statusOnServer = "";
			this.productID = "";
			this.projectOptions = "";
			this.shippingMethods = "";
			this.pdfProfile = "";
			this.dueToday = false;
			this.urgent = false;
			this.projectCap = "";
			this.maxPages = "";
			this.minProjectQuantity = "";
			this.maxProjectQuantity = "";
		}
	}

}
