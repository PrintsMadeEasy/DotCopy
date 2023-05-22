
char g_szConfigDomain[128];
char g_szConfigXMLHost[128];
char g_szConfigXMLFile[128];
char g_szConfigFailMailHost[128];
char g_szConfigFailMailScript[128];
char g_szConfigMailHost[128];
char g_szConfigMailUser[128];
char g_szConfigMailPass[128];
char g_szConfigFilePath[128];
char g_szSpamFilePath[128];
char g_szDataDirectory[128];
int g_nConfigDownloadTimeSecs;
char g_szConfigEhlo[128];
char g_szBindIP[20];
int g_nConfigRunSMTPServer;
int g_nConfigRunPop3Server;
int g_nConfigRunLog;
int g_nConfigSendType;
int g_nConfigSubmitMailMail;
int g_nConfigKeepSpam;

char g_szDKIMSelector[128];
int g_nDKIMOn;

CStringArray g_denyEmail;
CStringArray g_denyIP;


int nSmtpSplitCount;
int nPop3SplitCount;
int n1SplitCount;
int n2SplitCount;


void SplitString(CString sLine, CString &sKey, CString &sValue)
{

	int nGZPos = sLine.Find("=",0);
	int nSKPos = sLine.Find(";",nGZPos);

	sKey   = "";
	sValue = "";
	
	if(nGZPos>-1)
	{
		sKey = sLine.Mid(0,nGZPos); sKey.MakeLower();
		sValue = sLine.Mid(nGZPos+1,nSKPos-nGZPos-1);
	}
}


void LoadConfig()
{	
	strcpy(g_szDataDirectory,"");

	TCHAR str[MAX_PATH];
	GetModuleFileName(NULL, str, MAX_PATH);
	CString strDir(str);
	strcpy(g_szConfigFilePath,strDir.Left(strDir.ReverseFind(_T('\\')))+"\\");

	char * filebuffer = NULL; 
	DWORD nFileSize = 0;
	CFileException er; CFile* pFileRead = new CFile();
	if(pFileRead->Open(CString(g_szConfigFilePath)+"config.txt", CFile::modeRead , &er)) 
	{
		DWORD nLen = pFileRead->GetLength();
		filebuffer = new char[nLen+10];
		nFileSize = pFileRead->Read(filebuffer,nLen);
		pFileRead->Close();
		filebuffer[nLen]=0;
	} 
	   
	if(pFileRead) {delete pFileRead;}
	
	if(nFileSize>0)
	{
		WORD nLen = strlen(filebuffer);
		CString sKey, sValue;
		char headerline[250];

		int nSavePos=-1; int nStringCount = 0;
		for(WORD x=0; x<nLen; x++)	
		{
			if((filebuffer[x]==10) || (x==nLen-1) )
			{
				memcpy(headerline,filebuffer+nSavePos+1,x-nSavePos-1);
				headerline[x-nSavePos-2]=0;

				CString sHeaderLine = CString(headerline);
		
				SplitString(sHeaderLine, sKey, sValue);

				if(sKey=="domain")			{strcpy(g_szConfigDomain,sValue);}
			
				if(sKey=="xmlhost")			{strcpy(g_szConfigXMLHost,sValue);}
				if(sKey=="xmlfile")			{strcpy(g_szConfigXMLFile,sValue);}
					
				if(sKey=="failmailhost")	{strcpy(g_szConfigFailMailHost,sValue);}
				if(sKey=="failmailscript")	{strcpy(g_szConfigFailMailScript,sValue);}
			
				if(sKey=="mailhost")		{strcpy(g_szConfigMailHost,sValue);}
				if(sKey=="mailuser")		{strcpy(g_szConfigMailUser,sValue);}
				if(sKey=="mailpass")		{strcpy(g_szConfigMailPass,sValue);}
				if(sKey=="ehlo")			{strcpy(g_szConfigEhlo,sValue);}

				if(sKey=="bindip")			{strcpy(g_szBindIP,sValue);}
				if(sKey=="spam")			{strcpy(g_szSpamFilePath,sValue);}  // spam.bat in Spamassassin directory add "C:\\SA\\spamassassin.exe  < %1 >  %2"

				if(sKey=="dkimselector")	{strcpy(g_szDKIMSelector,sValue);}

				if(sKey=="datadir")			{strcpy(g_szDataDirectory,sValue);}


				if(sKey=="downloadtime")	
				{		
					g_nConfigDownloadTimeSecs = -1; // default -1 ==> no download, disables function

					if(sValue.GetLength()==8)
					{
						if( (sValue.Mid(2,1)==":") && (sValue.Mid(5,1)==":") )
						{
							g_nConfigDownloadTimeSecs = atoi(sValue.Mid(0,2))*3600+atoi(sValue.Mid(3,2))*60+atoi(sValue.Mid(6,2));	
						}
					}
				}

				if(sKey=="pop3server") 
				{
					g_nConfigRunPop3Server=0; sValue.MakeUpper();
					if(sValue=="ON") {g_nConfigRunPop3Server=1;}
				}

				if(sKey=="smtpserver") 
				{
					g_nConfigRunSMTPServer=0; sValue.MakeUpper();
					if(sValue=="ON") {g_nConfigRunSMTPServer=1;}
				}

				if(sKey=="submitfailmail") 
				{
					g_nConfigSubmitMailMail=0; sValue.MakeUpper();
					if(sValue=="ON") {g_nConfigSubmitMailMail=1;}
				}
				
				if(sKey=="log") 
				{
					g_nConfigRunLog=0; sValue.MakeUpper();
					if(sValue=="ON") {g_nConfigRunLog=1;}
				}


	
				if(sKey=="keepspam") 
				{
					g_nConfigKeepSpam=0; sValue.MakeUpper();
					if(sValue=="ON") {g_nConfigKeepSpam=1;}
				}



				if(sKey=="dkim") 
				{
					g_nDKIMOn=0; sValue.MakeUpper();
					if(sValue=="ON") {g_nDKIMOn=1;}
				}

				if(sKey=="sendtype") 
				{
					g_nConfigSendType=0; sValue.MakeUpper();
					if(sValue=="DIRECT") {g_nConfigSendType=1;}
				}






			
				nSavePos=x;
			}
		}
	}
	else
	{

	}

	delete [] filebuffer;
}


void LoadDenyfile()
{	
	TCHAR str[MAX_PATH];
	GetModuleFileName(NULL, str, MAX_PATH);
	CString strDir(str);
	strcpy(g_szConfigFilePath,strDir.Left(strDir.ReverseFind(_T('\\')))+"\\");

	char * filebuffer = NULL; 
	DWORD nFileSize = 0;
	CFileException er; CFile* pFileRead = new CFile();
	if(pFileRead->Open(CString(g_szConfigFilePath)+"deny.txt", CFile::modeRead , &er)) 
	{
		DWORD nLen = pFileRead->GetLength();
		filebuffer = new char[nLen+10];
		nFileSize = pFileRead->Read(filebuffer,nLen);
		pFileRead->Close();
		filebuffer[nLen]=0;
	} 
	   
	if(pFileRead) {delete pFileRead;}
	
	if(nFileSize>0)
	{
		WORD nLen = strlen(filebuffer);
		CString sKey, sValue;
		char headerline[250];

		int nSavePos=-1; int nStringCount = 0;
		for(WORD x=0; x<nLen; x++)	
		{
			if((filebuffer[x]==10) || (x==nLen-1) )
			{
				memcpy(headerline,filebuffer+nSavePos+1,x-nSavePos-1);
				headerline[x-nSavePos-2]=0;

				CString sHeaderLine = CString(headerline);
		
				SplitString(sHeaderLine, sKey, sValue);

				if(sKey=="ip")			
				{
					g_denyIP.Add(sValue);
				}
				if(sKey=="email")		
				{
					sValue.MakeLower();
					g_denyEmail.Add(sValue);

					TRACE("%s\n",sValue);
				}

				nSavePos=x;
			}
		}
	}

	delete [] filebuffer;
}





int ReadIteration()
{
	char cIter[100]; DWORD nLen = 0;
	CFileException e;
	CFile* pFile = new CFile();
	if(pFile->Open(CString(g_szConfigFilePath)+"iteration.txt", CFile::modeRead , &e)) 
	{
		nLen = pFile->GetLength();
		pFile->Read(cIter,nLen);
		pFile->Close();
	}   delete pFile;
		cIter[nLen]=0;

	int nIteration = atoi(cIter) * 60000;
	if(nIteration<60000)   { nIteration = 60000  ;}
	if(nIteration>3600000) { nIteration = 3600000;}

	return nIteration;
}
