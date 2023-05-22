
char g_szConfigDomainServiceName[128];

void SplitStringDomain(CString sLine, CString &sKey, CString &sValue)
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


void LoadConfigDomain()
{	
	char g_szConfigFilePath[200];

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
		char headerline[250], servername[200], domainname[200];
		int nServerName = 0;
	
		int nSavePos=-1; int nStringCount = 0;
		for(WORD x=0; x<nLen; x++)	
		{
			if((filebuffer[x]==10) || (x==nLen-1) )
			{
				memcpy(headerline,filebuffer+nSavePos+1,x-nSavePos-1);
				headerline[x-nSavePos-2]=0;

				CString sHeaderLine = CString(headerline);
		
				SplitStringDomain(sHeaderLine, sKey, sValue);

				if(sKey=="servicename") 
				{
					nServerName = 1;
					strcpy(servername,sValue);		
				}

				if(sKey=="domain") 
				{
					strcpy(domainname,sValue);		
				}

				nSavePos=x;
			}
		}

		sprintf(g_szConfigDomainServiceName,"A-Mail-%s",domainname);

		if(nServerName==1)
		{
			sprintf(g_szConfigDomainServiceName,"A-Mail-%s",servername);
		}

	}
	else
	{

	}


	delete [] filebuffer;
}

