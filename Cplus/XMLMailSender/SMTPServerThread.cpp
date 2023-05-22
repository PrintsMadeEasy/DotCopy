#include "stdafx.h"
#include "SMTPServerThread.h"
#include <direct.h>

#include "md5.h"

#include "SMTPMine.h"


#include "shlwapi.h"
#pragma comment(lib,"shlwapi.lib")

extern char g_szConfigFilePath[128];
extern char g_szConfigDomain[128];
extern int g_nConfigRunLog;
extern int g_nConfigKeepSpam;
extern char g_szBindIP[20];
extern char g_szSpamFilePath[128];
extern char g_szDataDirectory[128];


extern CStringArray g_denyEmail;
extern CStringArray g_denyIP;

extern int nSmtpSplitCount;
extern int n1SplitCount;
extern int n2SplitCount;



char g_szDirectoryPath[128];


#define MAXSESSIONTRACK 100000

int nSessions = 0;
int nSessionNo = 0;


#define SESSIONTRACK 

#ifdef SESSIONTRACK
	int nSessionOnOff[MAXSESSIONTRACK];
	DWORD dwThreadIdOnOff[MAXSESSIONTRACK]; 
	void * nThreadOnOff[MAXSESSIONTRACK]; 
	CTime tTimeOnOff[MAXSESSIONTRACK];
#endif


CSMTPThread::CSMTPThread()
{

}


CSMTPThread::~CSMTPThread()
{

}


void SplitLog(char *cFilename)
{
	DWORD nFileSize = 0;
	CFileException er; CFile* pFileRead = new CFile();
	if(pFileRead->Open(CString(g_szConfigFilePath)+CString(cFilename)+".txt", CFile::modeRead , &er)) 
	{
		nFileSize = pFileRead->GetLength();
		pFileRead->Close();
	}   if(pFileRead) {delete pFileRead;}

	if(nFileSize>10000000) // ca. 10MB
	{
		CString sNewFilename;
		sNewFilename.Format("%s\\logs",g_szConfigFilePath); mkdir(sNewFilename);
		sNewFilename.Format("%s\\logs\\%s-%s%s", g_szConfigFilePath,cFilename,CTime::GetCurrentTime().Format("%y%m%d%H%M%S"),".txt");
		rename(CString(g_szConfigFilePath)+CString(cFilename)+".txt", sNewFilename); 
	}
}


void CSMTPThread::Log(char *cText)
{
try{
	if(g_nConfigRunLog==1)
	{
		nSmtpSplitCount++;
		if(nSmtpSplitCount > 1000) // Check it every 5000 Lines
		{
			SplitLog("smtpserverlog");
			nSmtpSplitCount = 0;
		}

		FILE * pFile;
		if(pFile = fopen (CString(g_szConfigFilePath)+"smtpserverlog.txt","a"))
		{
			fputs (CTime::GetCurrentTime().Format("20%y/%m/%d  %H:%M:%S ")+CString(cText),pFile); 
			fclose (pFile);
			TRACE(cText);
		}
	}
} catch (...) {ErrorLog("Error in CSMTPThread::Log");}}


void CSMTPThread::ErrorLog(char *cText)
{
	FILE * pFile;
	if(pFile = fopen (CString(g_szConfigFilePath)+"errorlog.txt","a"))
	{
		fputs (CTime::GetCurrentTime().Format("20%y/%m/%d  %H:%M:%S ")+CString(cText),pFile); 
		fclose (pFile);

		TRACE(cText);
	}
}


void Log2(int nSessionNumber, char * cIP, char *cText)
{
try{
	if(g_nConfigRunLog==1)
	{
		
		nSmtpSplitCount++;
		if(nSmtpSplitCount > 1000) // Check it every 5000 Lines
		{
			SplitLog("smtpserverlog");
			nSmtpSplitCount = 0;
		}

		FILE * pFile;
		if(pFile = fopen (CString(g_szConfigFilePath)+"smtpserverlog.txt","a"))
		{
			char cLog[600]; if(strlen(cText)>460) cText[460] = 0; // Limit to 480

			sprintf(cLog,"%15s %2d <%d> %s %s\n",cIP,nSessions,nSessionNumber,CTime::GetCurrentTime().Format("20%y/%m/%d  %H:%M:%S "),cText);
			fputs (cLog,pFile); 
			fclose (pFile);
			TRACE(cLog);
		}
	}
} catch (...) {;}}


void LogRcpt(char * cIP, char *cText)
{
try{
	
	n1SplitCount++;
	if(n1SplitCount > 1000) 
	{
		SplitLog("rcpt-log");
		n1SplitCount = 0;
	}

	FILE * pFile;
	if(pFile = fopen (CString(g_szConfigFilePath)+"rcpt-log.txt","a"))
	{
		char cLog[600]; 
		sprintf(cLog,"%15s;%s;%s;\n",cIP,CTime::GetCurrentTime().Format("20%y/%m/%d  %H:%M:%S"),cText);
		fputs (cLog,pFile); 
		fclose (pFile);
	}
	
} catch (...) {;}}


void LogMailfrom(char * cIP, char *cText)
{
try{
	
	n2SplitCount++;
	if(n2SplitCount > 1000) 
	{
		SplitLog("mailfrom-log");
		n2SplitCount = 0;
	}

	FILE * pFile;
	if(pFile = fopen (CString(g_szConfigFilePath)+"mailfrom-log.txt","a"))
	{
		char cLog[600]; 
		sprintf(cLog,"%15s;%s;%s;\n",cIP,CTime::GetCurrentTime().Format("20%y/%m/%d  %H:%M:%S"),cText);
		fputs (cLog,pFile); 
		fclose (pFile);
	}
	
} catch (...) {;}}



DWORD WINAPI SMTPConnectionThread(void *param)
{
	ThreadParamater *nParam = (ThreadParamater*) param; ThreadParamater saveParameter;
	saveParameter.nSocket = nParam->nSocket;
	saveParameter.nSessionNo = nParam->nSessionNo;
	strcpy(saveParameter.cSessionIP,nParam->cSessionIP);

	TRACE("New Session : %d\n",saveParameter.nSessionNo);

	CMailSession *pSession = new CMailSession((SOCKET)saveParameter.nSocket,saveParameter.cSessionIP,saveParameter.nSessionNo);
	
	int len; char buf[2050]; 
		
	pSession->SendResponse(220);	

	nSessions++; 
	
#ifdef SESSIONTRACK
	nSessionOnOff[saveParameter.nSessionNo]=1;
#endif

	char logbuf[2500];
	sprintf(logbuf,"Start Session %d",saveParameter.nSessionNo);
	Log2(saveParameter.nSessionNo, saveParameter.cSessionIP, logbuf);


	// Init 1/5/2011
	pSession->m_nAuthSuccessful = -1;


	while(true)
	{
		bool bOK = false; memset(buf,0,2050);

		len = recv(pSession->m_socConnection,buf,sizeof(buf),0);

		if(len==-1) { strcpy(buf,"*CLOSE CONNECTION*");} sprintf(logbuf,"%s",buf,len); 
	
		if(len>1)
		{
			for(int c=len-2; c<len; c++)
			{
				if(logbuf[c]==10) {logbuf[c]=124;}
				if(logbuf[c]==13) {logbuf[c]=124;}
			}
		}

		Log2(saveParameter.nSessionNo, saveParameter.cSessionIP, logbuf);

		int nCode = 0;

		if(len==-1) // No Answer
		{
			bOK = true;
		}
		else
		{
			nCode = pSession->ProcessCMD(buf,len);
		}

		if(nCode==221) { bOK = true;}

		// Special pop3 user/pass check closes after checking
		if((nCode==586) || (nCode==587)) 
		{ 
			bOK = true;
		}
		
		if(bOK==true)
		{
			TRACE("Connection thread closing...\n");

			nSessions--; 
					
#ifdef SESSIONTRACK  	

			if(nSessionOnOff[saveParameter.nSessionNo]==1) {nSessionOnOff[saveParameter.nSessionNo]=2;}
			
			CString sText = "";
			if((nCode!=586) && (nCode!=587))
			{
				CString sTemp; sText.Format("Close Session: %d  /  Open ",saveParameter.nSessionNo);
				for(int x=0; x<MAXSESSIONTRACK; x++)
				{
					if(nSessionOnOff[x]==1) {
					
						TRACE("Offen %d\n",x);
						sTemp.Format("%d ",x);
						sText += sTemp;
					}
				}
			}
			else
			{
				sText.Format("Close Session: %d",saveParameter.nSessionNo);
			}

			Log2(saveParameter.nSessionNo, saveParameter.cSessionIP, sText.GetBuffer(500));
			
#else
			CString sText = "", sTemp; sText.Format("Close Session: %d",saveParameter.nSessionNo);
			Log2(saveParameter.nSessionNo, saveParameter.cSessionIP, sText.GetBuffer(500));
#endif	


		



			TRACE("CLOSE SESSION %d ",saveParameter.nSessionNo); 

			if(pSession) delete pSession;

			return 0;
		}
	}	

	if(pSession) delete pSession;

	return -1;
}




void CSMTPThread::AcceptSmtpConnections(SOCKET server_soc)
{
	try
	{

	SOCKET soc_client;
	char cLog[1000];

	sprintf(cLog,"SMTP Server is ready and listening to TCP port %d ...\n",SMTP_PORT); Log(cLog);

	while(true)
	{
		sockaddr nm;

		int len = sizeof(sockaddr);

		TRACE("Waiting for incoming connection...\n");

	

		if(INVALID_SOCKET==(soc_client=accept(server_soc,&nm,&len)))
		{
			sprintf(cLog,"Error: Invalid Socket returned by accept(): %d\n",WSAGetLastError()); Log(cLog);
		}
		else
		{
			TRACE("Accepted new connection. Now creating session thread...\n");
		}	

				
		// Get IP
		BYTE nNM[10]; memcpy(nNM,&nm,8); sprintf(cThreadIP,"%d.%d.%d.%d",nNM[4],nNM[5],nNM[6],nNM[7]);
			
		// Deny blocked IP's		
		bool bIPBlocked = false;
		for(int i=0; i<g_denyIP.GetSize(); i++)
		{
			if(strcmp(g_denyIP.ElementAt(i),cThreadIP)==0)
			{
				sprintf(cLog,"Denied IP %s\n",cThreadIP); Log(cLog);
				bIPBlocked = true; 
			}
		}
	
		if(bIPBlocked == true)
			continue;

		nSessionNo++;


#ifdef SESSIONTRACK  

		if(nSessionNo>MAXSESSIONTRACK-10)
		{
			nSessionNo = 0;

			for(int x=0; x<MAXSESSIONTRACK; x++)
			{
				nSessionOnOff[x]=0;
			}
		}
#endif

		DWORD dwThreadId; 
		HANDLE hThread; 

		ThreadParamater nPara;
		nPara.nSocket = (void*)soc_client;
		nPara.nSessionNo = nSessionNo;
		strcpy(nPara.cSessionIP,cThreadIP);	

		hThread = CreateThread( 
			NULL,                        // default security attributes 
			0,                           // use default stack size  
			SMTPConnectionThread,        // thread function 
			(LPVOID)&nPara,            // argument to thread function 
			0,                           // use default creation flags 
			&dwThreadId);                // returns the thread identifier 

		Sleep(100);
		
		if(hThread == NULL) 
		{
			Log( "CreateThread failed." ); 
		}
	
#ifdef SESSIONTRACK  

		CTime tNow = CTime::GetCurrentTime();

		dwThreadIdOnOff[nSessionNo] = dwThreadId; 
		nThreadOnOff[nSessionNo] = hThread; 
		tTimeOnOff[nSessionNo] = tNow;

		/*
		for(int x=0; x<MAXSESSIONTRACK; x++)
		{
			if(nSessionOnOff[x]==1)
			{
				CTimeSpan tSpan = tNow - tTimeOnOff[x];
				int nSeconds = tSpan.GetTotalSeconds();

				if(nSeconds>600)
				{
					nSessionOnOff[x] = 3;
					CString sText; sText.Format("*************   KILLED Process of Session %d\n",x);
					Log(sText.GetBuffer(500)); 
					// TerminateProcess(nThreadOnOff[x],0); // Doesnt work, must add UserPrivileges for that
				}
			}
		}
		*/
#endif
		

	}

} catch (...) {ErrorLog("Error in CSMTPThread::AcceptSmtpConnections");}}



int CSMTPThread::StartSMTPServer()
{

#ifdef SESSIONTRACK  
	for(int x=0; x<MAXSESSIONTRACK; x++) { nSessionOnOff[x]=0;}
#endif

	CString sIncomingDir;
	sIncomingDir.Format("%sincoming",g_szConfigFilePath);
	mkdir(sIncomingDir);

	WORD wVersionRequested;
	WSADATA wsaData;
	int err; char cLog[1000];

	wVersionRequested = MAKEWORD( 2, 2 );

	err = WSAStartup( wVersionRequested, &wsaData );

	if ( err != 0 ) 
	{
		sprintf(cLog,"Error in  initializing. Quitting with error code: %d\n",WSAGetLastError()); Log(cLog);
		Sleep(5000);
		exit(WSAGetLastError());
	}

	SOCKET soc=socket(PF_INET, SOCK_STREAM, 0) ;

	if(soc==INVALID_SOCKET)
	{
		sprintf(cLog,"Error: Invalid socket. Quitting with error code: %d\n",WSAGetLastError()); Log(cLog);
		Sleep(5000);
		exit(WSAGetLastError());
	}

	SOCKADDR_IN soc_addr;

	char cHost[200];

	strcpy(cHost,"localhost");

	if(strlen(g_szBindIP)>6)
	{
		strcpy(cHost,g_szBindIP);
	}

	LPHOSTENT lpHost = gethostbyname(cHost);

	soc_addr.sin_family=AF_INET;
	soc_addr.sin_port=htons(SMTP_PORT);
	soc_addr.sin_addr=*(LPIN_ADDR)(lpHost->h_addr_list[0]);

	if(bind(soc,(const struct sockaddr*)&soc_addr,sizeof(soc_addr)))
	{
		sprintf(cLog,"Error: Can not bind socket. Another server running? Quitting with error code: %d\n",WSAGetLastError()); Log(cLog);
		Sleep(5000);
		exit(WSAGetLastError());
	}

	if(SOCKET_ERROR==listen(soc,SOMAXCONN))
	{
		sprintf(cLog,"Error: Can not listen to socket. Quitting with error code: %d\n",WSAGetLastError()); Log(cLog);
		Sleep(5000);
		exit(WSAGetLastError());
	}

	char direct[300];
	
	strcpy(direct,g_szConfigFilePath);
	strcpy(g_szDirectoryPath, direct);

	sprintf(cLog,"Active directory path %s\n",g_szDirectoryPath); Log(cLog);

	AcceptSmtpConnections(soc);

	Log("You should not see this message. It is an abnormal condition. Terminating...");
	return 0;
}


DWORD WINAPI SMTPServerThread(LPVOID pParam)
{
	CSMTPThread smtpserver;
	smtpserver.StartSMTPServer();
	return 0;
}

void CSMTPThread::StartSMTPServerThread()
{
	HANDLE hPop3Thread; DWORD Pop3ThreadID; 
	hPop3Thread = CreateThread ( NULL, 0, SMTPServerThread, NULL , 0, &Pop3ThreadID);
}


/////////////////////  CMAILSESSON /////////////////////////////////////////////////////////////////


void CMailSession::GetSmtpMxHost(char * strDomain, char * strMailHost, char * cIP)
{
try 
{
	DNS_RECORD* ppQueryResultsSet = NULL;

	strMailHost[0] = 0;

	DNS_STATUS statusDNS = ::DnsQuery( strDomain, DNS_TYPE_MX, DNS_QUERY_STANDARD, NULL, &ppQueryResultsSet, NULL );
	
	if(statusDNS == ERROR_SUCCESS)
	{
		strcpy(strMailHost,ppQueryResultsSet->Data.MX.pNameExchange);

		DnsRecordListFree(ppQueryResultsSet,DnsFreeFlat);
	
		struct sockaddr_in SocketAddress;
		struct hostent     *pHost        = 0;

		pHost = ::gethostbyname(strMailHost);
		
		if(pHost)
		{
			char aszIPAddresses[10][16]; 
			for(int iCnt = 0; ((pHost->h_addr_list[iCnt]) && (iCnt < 10)); ++iCnt)
			{
			  memcpy(&SocketAddress.sin_addr, pHost->h_addr_list[0], pHost->h_length);
			  strcpy(aszIPAddresses[iCnt], inet_ntoa(SocketAddress.sin_addr));
			}
			if(iCnt>0)
			{
				strcpy(cIP,aszIPAddresses[0]);
			}
		}
	}

} catch (...) {ErrorLog("Error in CMailSession::GetSmtpMxHost");}
}



void CMailSession::ErrorLog(char *cText)
{
	try{
	FILE * pFile;
	if(pFile = fopen (CString(g_szConfigFilePath)+"ErrorLog.txt","a"))
	{
		fputs (CTime::GetCurrentTime().Format("20%y/%m/%d  %H:%M:%S ")+CString(cText)+"\n",pFile); 
		fclose (pFile);
		TRACE(cText);
	}
	} catch (...) {;}
}


int CMailSession::ProcessCMD(char *buf, int len)
{try{
	
	if(m_nStatus==SMTP_STATUS_DATA)
	{
		if(len<5) { m_nEmptyDataBuffers++; } else {m_nEmptyDataBuffers = 0;}

		if(m_nEmptyDataBuffers>100) // Spammers sometimes send infinite empty data as DOS Attack
		{
			Log2(nSessionNo,cSessionIP,"Too many Empty Data Buffers in a row !!!!!");
			return SendResponse(221); 
		}

		if(m_nDataCount>100000000) // > 100MB file attachment maximum
		{
			Log2(nSessionNo,cSessionIP,"Too much Data !!!!!");
			return SendResponse(221);
		}		
	}	

	if(m_nAuthLoginStatus==2)
	{
		return ProcessAuthReadUser(buf,len);
	}
	if(m_nAuthLoginStatus==4)
	{
		return ProcessAuthReadPass(buf,len);
	}

	if(m_nStatus==SMTP_STATUS_DATA)
	{
		return ProcessDATA(buf,len);
	}
	else if(_strnicmp(buf,"HELO",4)==0)
	{
		return ProcessHELO(buf, len);
	}
	else if(_strnicmp(buf,"AUTH PLAIN",10)==0)
	{
		return ProcessAUTHPLAIN(buf, len);
	}
	else if(_strnicmp(buf,"AUTH LOGIN",10)==0)
	{
		return ProcessAUTHLOGIN(buf, len);
	}
	else if(_strnicmp(buf,"EHLO",4)==0)
	{
		return ProcessHELO(buf, len);
	}
	else if(_strnicmp(buf,"MAIL",4)==0)
	{
		return ProcessMAIL(buf, len);
	}
	else if(_strnicmp(buf,"RCPT",4)==0)
	{
		return ProcessRCPT(buf, len);
	}
	else if(_strnicmp(buf,"DATA",4)==0)
	{
		return ProcessDATA(buf,len);
	}
	else if(_strnicmp(buf,"RSET",4)==0)
	{
		return ProcessRSET(buf,len);
	}
	else if(_strnicmp(buf,"QUIT",4)==0)
	{
		return ProcessQUIT(buf,len);
	}
	else 
		return ProcessNotImplemented(false);

} catch (...) {ErrorLog("Error in CMailSession::ProcessCMD");}}


int CMailSession::CheckAuthPlainLogin(char * cEncodedString)
{
	int nResult = 0;

try{

	CBase64 base64;
	LPCSTR pszTemp;
	base64.Decode(cEncodedString);
    pszTemp = base64.DecodedMessage();
	DWORD nDecodedSize = base64.GetDecodedSize();
	CString sDecoded = "";

	for(int x=0; x<nDecodedSize; x++)
	{
		int ascii = (int)pszTemp[x];
		char cZahl[2]; cZahl[0]=ascii; cZahl[1]=0; 
		if(ascii==0) {strcpy(cZahl,"*");}
		sDecoded += cZahl;
	}

	int nState = 0, nSavePos = 0; CString sUserLogin, sPass;
	for(x=0; x<nDecodedSize; x++)
	{
		if(sDecoded.Mid(x,1)=="*")
		{
			nState++;
			CString sValue = sDecoded.Mid(nSavePos+1,x-nSavePos-1);
			if(nState==2) {sUserLogin = sValue;}
			if(nState==3) {sPass = sValue;}
			nSavePos = x;
		}
	}

	int nAdd = sUserLogin.Find("@",0);
	CString sDomain = sUserLogin.Mid(nAdd+1,200);
	CString sUserName = sUserLogin.Mid(0,nAdd);
		
	char lpPwdFile[300];
	
	sprintf(lpPwdFile,"%s\\%s\\%s\\%s.pwd",g_szConfigFilePath,sDomain,sUserName,sPass);

	if(PathFileExists(lpPwdFile))
	{
		nResult = 1;
	}

} catch (...) {ErrorLog("Error in CMailSession::CheckAuthLogin");}

	return nResult;
}


int CMailSession::ProcessAUTHPLAIN(char *buf, int len)
{	
try{
	    
		buf += 11;
		
		char cAuth[200]; strcpy(cAuth,buf);
		
		m_nAuthSuccessful = CheckAuthPlainLogin(cAuth);

} catch (...) {ErrorLog("Error in CMailSession::ProcessAUTH");}



// Turned OFF !
m_nAuthSuccessful = 0;


	if(m_nAuthSuccessful==1)
		return SendResponse(235);
	else
		return SendResponse(535); 
}


int CMailSession::ProcessAUTHLOGIN(char *buf, int len)
{	
	try{
		m_nAuthLoginStatus = 2;
} catch (...) {ErrorLog("Error in CMailSession::ProcessAUTH");}
return SendResponse(3341);	
}


int CMailSession::ProcessAuthReadUser(char *buf, int len)
{	
	try{
		memset(cAuthUser,0,200);
		memcpy(cAuthUser,buf,len);
		m_nAuthLoginStatus = 4;
		
} catch (...) {ErrorLog("Error in CMailSession::ProcessAUTH");}

return SendResponse(3342);
	
}


int CMailSession::ProcessAuthReadPass(char *buf, int len)
{	
	try{
		memset(cAuthPass,0,200);
		memcpy(cAuthPass,buf,len);		
		m_nAuthLoginStatus = 0;

		CString sUser, sPass;

		{
			char cAuthUserDecoded[200];
			CBase64 base64; DWORD nDecodedSize;
			base64.Decode(cAuthUser);
			nDecodedSize  = base64.GetDecodedSize();
			memcpy(cAuthUserDecoded,base64.DecodedMessage(),nDecodedSize);
			cAuthUserDecoded[nDecodedSize]=0;
			sUser = cAuthUserDecoded;
		}

		{
			char cAuthPassDecoded[200];
			CBase64 base64; DWORD nDecodedSize;
			base64.Decode(cAuthPass);
			nDecodedSize = base64.GetDecodedSize();
			memcpy(cAuthPassDecoded,base64.DecodedMessage(),nDecodedSize);
			cAuthPassDecoded[nDecodedSize]=0;
			sPass = cAuthPassDecoded;
		}
		


		// Security problem: If we have a * in the filename, it will match the PathFileExists ! Brian's good idea Jan 5th 2011
		sPass.Replace("*"," Wildcard ");


		char cText[200];

		sprintf(cText,"Auth User=%s Pass=%s\n",sUser,sPass);


		m_nAuthSuccessful = 0;

		int nAdd = sUser.Find("@",0);
		CString sDomain = sUser.Mid(nAdd+1,200);
		CString sUserName = sUser.Mid(0,nAdd);
			
		char lpPwdFile[300];
		
		sprintf(lpPwdFile,"%s\\%s\\%s\\%s.pwd",g_szConfigFilePath,sDomain,sUserName,sPass);

		if(PathFileExists(lpPwdFile))
		{
			m_nAuthSuccessful = 1;
		}
		



//////// LOG ///////////


	if(m_nAuthSuccessful==1)
	{
		char cLog[500]; sprintf(cLog,"Auth Needed: domain=%s user=%s pass=%s",sDomain,sUserName,sPass); Log2(nSessionNo,cSessionIP,cLog);
	}
	else
	{
		char cLog[500]; sprintf(cLog,"Auth Denied: domain=%s user=%s pass=%s",sDomain,sUserName,sPass); Log2(nSessionNo,cSessionIP,cLog);		
	}

////////////////////////



} catch (...) {ErrorLog("Error in CMailSession::ProcessAUTH");}

	if(m_nAuthSuccessful==1)
		return SendResponse(235); // Auth needed
	else
		return SendResponse(535); // Auth failed
}


int CMailSession::ProcessHELO(char *buf, int len)
{try{

	TRACE("Received HELO\n");

	buf+=5;

	m_nHeloCount++;

	CString sHelo = buf;



	// Check pop3 user/pass special way
	int nHeloLen = sHelo.GetLength();
	if(nHeloLen>36)
	{
		if(sHelo.Mid(0,4) == "d8a6")
		{
			bool bOK = false;
			CString sCode = sHelo.Mid(4,32);
			CString sUser = sHelo.Mid(36,nHeloLen-36); sUser.Replace("\r",""); sUser.Replace("\n",""); sUser.MakeLower();
			char domain_user_path[300]; sprintf(domain_user_path,"%s\\%s\\",GetDomainPathUsers(),sUser);
			if(PathFileExists(domain_user_path))
			{		
				WIN32_FIND_DATA FindFileData; HANDLE hFind = INVALID_HANDLE_VALUE;
				char DirSpec[500]; sprintf(DirSpec,"%s*.pwd",domain_user_path);
				hFind = FindFirstFile(DirSpec, &FindFileData);
				if (hFind != INVALID_HANDLE_VALUE) 
				{
					CString sPass =  FindFileData.cFileName; sPass.MakeLower(); sPass.Replace(".pwd","");
					char cMD5[500]; sprintf(cMD5,"%s%sHpe*56J@gd6",sUser,sPass); GetMD5(cMD5);
					if(strcmp(cMD5,sCode.GetBuffer(32))==0) { bOK = true;}
					FindClose(hFind);
				}
			}
			if(bOK) 
				return SendResponse(586); // user/pass match
			else
				return SendResponse(587);
		}
	}



	if( (strlen(g_szBindIP)>6) && (sHelo.Find(g_szBindIP,0)>-1) )
	{
		return SendResponse(221); // We dont answer calls with our IP in HELO, spammer from Taiwan uses it.
	}

	try{

		if(m_nHeloCount>10) // Spammers send thousands of Helo, 100% CPU and crash
		{
			return SendResponse(221); // Adios Amigos
		}

	} catch (...) {ErrorLog("Error in CMailSession::ProcessHELO.1");}

	try{
		m_nStatus=SMTP_STATUS_HELO;
		m_FromAddress.SetAddress("");
		m_nRcptCount=0;

	} catch (...) {ErrorLog("Error in CMailSession::ProcessHELO.2");}

} catch (...) {ErrorLog("Error in CMailSession::ProcessHELO");}

	
	return SendResponse(2501); // Turn off AUTH PLAIN for now 
	//return SendResponse(250);
}


int CMailSession::ProcessRCPT(char *buf, int len)
{
	char address[MAX_ADDRESS_LENGTH+5];
	char user[MAX_USER_LENGTH+5];
	char tdom[MAX_DOMAIN_LENGTH+5];
	char szUserPath[MAX_PATH+1];
	char *st,*en, *domain=tdom;

	long int alen; 

	memset(address,0,sizeof(address));

	st=strchr(buf,'<');
	en=strchr(buf,'>');
	st++;

	alen=en-st;
	strncpy(address,st,alen);

	domain=strchr(address,'@');
	domain+=1;

	memset(user,0,sizeof(user));
	strncpy(user,address,strlen(address)-strlen(domain)-1);

	LogRcpt(cSessionIP,address);
	
	char cEmail[100]; memset(cEmail,0,100);

	memcpy(cEmail,buf+8,len-8);

	
	CString sRcptEmail = cEmail; sRcptEmail.MakeLower();
	CString sDomain = g_szConfigDomain; sDomain.MakeLower();

	int nFoundLocal = sRcptEmail.Find("@"+sDomain,0);


	// Do not allow RCPT to blocked emails
	for(int d=0; d < g_denyEmail.GetSize(); d++)
	{
		if(sRcptEmail.Find(g_denyEmail.ElementAt(d),0)>-1)
		{
			char cLog[500]; sprintf(cLog,"*** Blocked Email RCPT: %s",g_denyEmail.ElementAt(d)); Log2(nSessionNo,cSessionIP,cLog); // Spammers
			return SendResponse(221);
		}
	}


//	char cLog[500]; sprintf(cLog,"Rcpt %s FL=%d",sRcptEmail,nFoundLocal); Log2(nSessionNo,cSessionIP,cLog); 


	int nGoAhead = 0;

	if(m_nAuthSuccessful==1)
	{
		nGoAhead = 1;
	}

	if(nFoundLocal>-1)
	{
		nGoAhead = 1;
	}

	if(nGoAhead==1)
	{		
		if(m_nStatus!=SMTP_STATUS_HELO)
		{
			//503 Bad Command
			return SendResponse(503);
		}

		if(m_nRcptCount>=MAX_RCPT_ALLOWED)
		{
			//552 Requested mail action aborted: exceeded storage allocation
			return SendResponse(221); // Adios
		}
	}
	else
	{
		return SendResponse(551); // Closes connection after that
	}

	// Get user for new account
	if(m_nAddNewAccountStatus == 1)
	{
		strcpy(newAccountUser,user);
		m_nAddNewAccountStatus = 2;
	}


	CString sDomainLower       = domain;           sDomainLower.MakeLower();
	CString sConfigDomainLower = g_szConfigDomain; sConfigDomainLower.MakeLower();

	if(sDomainLower != sConfigDomainLower)
	{
		m_nIsOutboundEmail = 1;
		m_nRcptCount++;
	}
	else
	{
		// Email is for this Domain, find mailbox
	
		char domain_path[300];
		sprintf(domain_path,"%s",GetDomainPathUsers());

		if(m_nAddNewAccountStatus==0)
		{
			if(PathFileExists(domain_path))
			{
				sprintf(szUserPath,"%s\\%s",domain_path,user);
			
				if(!PathFileExists(szUserPath))
				{
					return SendResponse(551);
				}
			}
			else
			{
				return SendResponse(551);
			}
		}
		else
		{
			sprintf(szUserPath,"%s\\%s",domain_path,"newaccount");
		}

		m_ToAddress[m_nRcptCount].SetMBoxPath(szUserPath);
		m_ToAddress[m_nRcptCount].SetAddress(address);
		m_nRcptCount++;
	}

	return SendResponse(250);
}


int CMailSession::ProcessMAIL(char *buf, int len)
{
try{
	char address[MAX_ADDRESS_LENGTH+5];
	char *st,*en; int startPos = -1;
	long int alen;

	bool bInvalidEmail = false;

	if(m_nStatus!=SMTP_STATUS_HELO)
	{
		return SendResponse(503);
	}

	CString sAddress = "";

	try{

		memset(address,0,sizeof(address));

		st=strchr(buf,'<');
		en=strchr(buf,'>');

		if((st==NULL) || (en==NULL) )
		{
			char cLog[500]; sprintf(cLog,"*** Invalid Email format: %s",buf); Log2(nSessionNo,cSessionIP,cLog); // Spammers like  "MAIL FROM:borisjones@gmail.com" 
			return SendResponse(501);
		}

		st++;
		alen=en-st;
		strncpy(address,st,alen);

		sAddress = address; sAddress.MakeLower();
		
		// End Session for Spammers
		for(int d=0; d < g_denyEmail.GetSize(); d++)
		{
			if(sAddress.Find(g_denyEmail.ElementAt(d),0)>-1)
			{
				char cLog[500]; sprintf(cLog,"*** Blocked Email: %s",g_denyEmail.ElementAt(d)); Log2(nSessionNo,cSessionIP,cLog); // Spammers
				return SendResponse(221);
			}
		}

	// Fixes this problem, service closes after such a long spam request
	// MAIL FROM:<notification+24398@{%RND_CHR%}{%RND_CHR%}{%RND_CHR%}{%RND_CHR%}{%RND_CHR%}{%RND_CHR%}.com>||
	// 195.24.215.163 20 <3684> 2010/07/12  14:29:11  *** MX *** {%RND_CHR%}{%RND_CHR%}{%RND_CHR%}{%RND_CHR%}{%RND_CHR%}{%RND_CHR%}.com =  (h@Ø.)

		if((sAddress.Find("rnd_chr",0)>-1) || (sAddress.GetLength()>75))
		{
			len=0;
			return SendResponse(501);
		}

		if(strlen(address)==0) 
		{
			strcpy(address,"nullsender@nullsender");
			m_nNullsender=1;
		}

		if(!CMailAddress::AddressValid(address))
		{
			return SendResponse(501);
		}
		
	

	} catch (...) {ErrorLog("Error in CMailSession::ProcessMAIL-Part1");}
	

	char *domain;
	
	try{

		domain=strchr(address,'@');
		domain+=1;
		
		char mxHost[1000],cHostIP[20];
		
		try{

		GetSmtpMxHost(domain,mxHost,cHostIP);
			
		} catch (...) {ErrorLog("Error in CMailSession::ProcessMAIL-MX-Host");}

		char cLog[500]; sprintf(cLog,"*** MX *** %s = %s (%s)",domain,mxHost,cHostIP);
		Log2(nSessionNo,cSessionIP,cLog); 

		LogMailfrom(cSessionIP,address); 

	} catch (...) {ErrorLog("Error in CMailSession::ProcessMAIL-MX");}


	m_FromAddress.SetAddress(address);

	// Look for mailmaster
	CString sMailMasterEmail; sMailMasterEmail.Format("mailmaster@%s",g_szConfigDomain); sMailMasterEmail.MakeLower();
	if(sAddress==sMailMasterEmail)
	{
		m_nAddNewAccountStatus = 1;
	}

} catch (...) {ErrorLog("Error in CMailSession::ProcessMAIL");}

return SendResponse(250);

}

int CMailSession::ProcessRSET(char *buf, int len)
{try{

	char cText[1000];

	m_nRsetCount++;

	if(m_nRsetCount>3) 
	{
		Sleep(500+m_nRsetCount*100);	// Spammers send thousands of Rset, 100% CPU and crash
	}

	if(m_nRsetCount>20) // Spammers send thousands of Rset, 100% CPU and crash
	{
		sprintf(cText,"Closed RSET\n"); Log2(nSessionNo,cSessionIP,cText); TRACE(cText);
		return SendResponse(221); 
	}
	else
	{
		sprintf(cText,"Received RSET\n"); Log2(nSessionNo,cSessionIP,cText); TRACE(cText);
	}

	m_nRcptCount=0;
	m_FromAddress.SetAddress("");
	m_nStatus=SMTP_STATUS_HELO;

	strcpy(m_szFileName,"");

} catch (...) {ErrorLog("Error in CMailSession::ProcessRSET");}

	return SendResponse(220);
}

int CMailSession::ProcessNOOP(char *buf, int len)
{try{

	TRACE("Received NOOP\n");

} catch (...) {ErrorLog("Error in CMailSession::ProcessNOOP");}

	return SendResponse(220);
}

int CMailSession::ProcessQUIT(char *buf, int len)
{try{
	
} catch (...) {ErrorLog("Error in CMailSession::ProcessQUIT");}

	return SendResponse(221);
}

void CMailSession::GetRFCTime(TCHAR * szDateOut)
{try{

	SYSTEMTIME Timestamp; 
	TIME_ZONE_INFORMATION tzi;
	DWORD dwRet;
	long Offset; 
	int GMTOffset;
	GetLocalTime(&Timestamp); 
	GMTOffset = 0; 
	dwRet = GetTimeZoneInformation(&tzi); 
	Offset = tzi.Bias; 
	if (dwRet == TIME_ZONE_ID_STANDARD) Offset += tzi.StandardBias; 
	if (dwRet == TIME_ZONE_ID_DAYLIGHT) Offset += tzi.DaylightBias; 
	GMTOffset = -((Offset / 60) * 100 + (Offset % 60)); 
	TCHAR szTime[64];
	TCHAR szDate[64];
	GetDateFormat(MAKELCID(LANG_ENGLISH, SORT_DEFAULT),0,&Timestamp,_T("ddd, d MMM yyyy"),szDate,64); 
	GetTimeFormat(MAKELCID(LANG_ENGLISH, SORT_DEFAULT),0,&Timestamp,_T("H:mm:ss"),szTime,64); 
	wsprintf(szDateOut,_T("%s %s %c%4.4d"),szDate,szTime,(GMTOffset>0)?'+':'-',abs(GMTOffset)); 

} catch (...) {ErrorLog("Error in CMailSession::GetRFCTime");}}		


int CMailSession::ProcessDATA(char *buf, int len)
{try{

	DWORD dwIn=len, dwOut;


	if(m_nStatus!=SMTP_STATUS_DATA)
	{	
		for(int i=0;;i++)
		{
			char cMD5Source[500];
			TCHAR szDateOut[1024];
			GetRFCTime(szDateOut);
			sprintf(cMD5Source,"%s_%s_%d_%d",szDateOut,m_ToAddress[0].GetAddress(),i,rand());
			GetMD5(cMD5Source);

			sprintf(m_szFileName,"%sincoming\\%s.eml",g_szConfigFilePath,cMD5Source);

			if(!PathFileExists(m_szFileName))
				break;
		}

		CreateNewMessage(m_szFileName);

		if(strcmp(m_ToAddress[0].GetDomain(), g_szConfigDomain) == 0)
		{
			TCHAR szDateOut[1024]; GetRFCTime(szDateOut);

			char To_Address[1000]; sprintf(To_Address,"%s",m_ToAddress[0].GetAddress());

			char cPreHeader[5120];
			
			sprintf(cPreHeader,"Return-path: <%s>\r\nEnvelope-to: %s\r\nDelivery-date: %s\r\n", m_FromAddress.GetAddress(),To_Address, szDateOut);

			unsigned long nPreHeaderIn = strlen(cPreHeader);
			
			unsigned long nPreHeaderOut = 0;
			
			WriteFile(m_hMsgFile,cPreHeader,nPreHeaderIn,&nPreHeaderOut,NULL);

			int nPreHeaderLength = strlen(cPreHeader); if(nPreHeaderLength>5000) {nPreHeaderLength=5000;}
			memcpy(cDataBuffer,cPreHeader,nPreHeaderLength); m_nDataBufferPos += nPreHeaderLength;
		}
	
		m_nStatus=SMTP_STATUS_DATA;
		return SendResponse(354);
	}


	// Maintain temp. max. 5k data buffer
	if(m_nDataBufferPos<5000)
	{
		int nSize = len;
		if((m_nDataBufferPos+len) > 5000)
		{
			nSize = 5000 - m_nDataBufferPos;
		}
		memcpy(cDataBuffer+m_nDataBufferPos,buf,nSize); m_nDataBufferPos += nSize;
	}

	// Search for keywords in DataBuffer, one hit sets the flag
	if(m_nDeliveryDate == 0)
	{
		char *pDelivery; 
		pDelivery = strstr(cDataBuffer, "Delivery-date: ");	
		if((pDelivery - cDataBuffer)>-1)
		{
			m_nDeliveryDate = 1;
		}
	}

	// Problem with 0 Byte files
	if(m_nStatus==SMTP_STATUS_HELO||SMTP_STATUS_DATA) 
	{
		if(strcmp(m_ToAddress[0].GetDomain(), g_szConfigDomain) == 0)
		{
			// Remove the last 3 chars of data buffer, no _._ at the end !!!! But only for mails for pop3	
			if(strstr(buf,SMTP_DATA_TERMINATOR))
			{
				dwIn = dwIn - 3;	
			}
		}

		WriteFile(m_hMsgFile,buf,dwIn, &dwOut,NULL); m_nDataCount+=len;
	}

	// end after .<cr> , some mailserver end communication with this different terminator

	if((buf[0]==46) && (len==3))
	{
		if( (buf[1]==13) && (buf[2]=10))
		{
			// char cText[200]; sprintf(cText,"QUIT after .<13><10> -> len=%d",len); Log2(nSessionNo,cSessionIP,cText);
			m_nStatus=SMTP_STATUS_DATA_END;
			return ProcessDATAEnd();
		}
	}

	//good client should send term in separate line
	if(strstr(buf,SMTP_DATA_TERMINATOR))
	{
		TRACE("Data End\n");
		m_nStatus=SMTP_STATUS_DATA_END;

		return ProcessDATAEnd();
	}



} catch (...) {ErrorLog("Error in CMailSession::ProcessDATA");}

	return 220;

}


int CMailSession::ProcessNotImplemented(bool bParam)
{try{
	if (bParam) 
	{
		return SendResponse(504);
	}
	else return SendResponse(502);

} catch (...) {ErrorLog("Error in CMailSession::ProcessNotImplemented");}}


int CMailSession::SendResponse(int nResponseType)
{try{

	char buf[100];
	int len;
	if(nResponseType==220)
		sprintf(buf,"220 %s Welcome to %s %s \r\n",g_szConfigDomain,SMTP_APP_TITLE, SMTP_APP_VERSION);
	
	else if(nResponseType==221)
	{
		strcpy(buf,"221 Service closing transmission channel\r\n");	
	
		if(m_nFileOpenClosed==1)
		{
			CloseHandle(m_hMsgFile); 
			char cText[200]; sprintf(cText,"Before disconnect close file %s",m_szFileName); Log2(nSessionNo,cSessionIP,cText);
		}
	}

	else if (nResponseType==250) 
		strcpy(buf,"250 OK\r\n");

	else if (nResponseType==235) 
		strcpy(buf,"235 Authentication succeeded\r\n");

	else if (nResponseType==2501) 
		strcpy(buf,"250 AUTH LOGIN\r\n"); 


	else if (nResponseType==535) 
		strcpy(buf,"535 authorization failed\r\n");

	else if (nResponseType==3341) 
		strcpy(buf,"334 VXNlcm5hbWU6\r\n");

	else if (nResponseType==3342) 
		strcpy(buf,"334 UGFzc3dvcmQ6\r\n");



	else if (nResponseType==586) 
		strcpy(buf,"586  Command not implemented\r\n");
	else if (nResponseType==587) 
		strcpy(buf,"587  Command not implemented\r\n");


	else if (nResponseType==354)
		strcpy(buf,"354 Start mail input; end with <CRLF>.<CRLF>\r\n");
	else if(nResponseType==501)
		strcpy(buf,"501 Syntax error in parameters or arguments\r\n");		
	else if(nResponseType==502) {
		
		m_nCommandNot502++;

		if(m_nCommandNot502>20)
		{
			nResponseType=221;
			strcpy(buf,"221 Service closing transmission channel\r\n");	
		}
		else
		{
			strcpy(buf,"502 Command not implemented\r\n");	
			Sleep(1000+67*m_nCommandNot502);
		}
	}
	else if(nResponseType==503)
		strcpy(buf,"503 Bad sequence of commands\r\n");		
	else if(nResponseType==550)
		strcpy(buf,"550 No such user\r\n");

	else if(nResponseType==551) {

		m_nUserNotLocal551++;
		
		if(m_nUserNotLocal551>5)
		{
			nResponseType=221;
			strcpy(buf,"221 Service closing transmission channel\r\n");	
		}
		else
		{
			strcpy(buf,"551 User not local\r\n"); Sleep(800+500*m_nUserNotLocal551);	
		}
	}
	else
		sprintf(buf,"%d No description\r\n",nResponseType);

	len=(int)strlen(buf);

	TRACE("Sending: %s",buf);


	send(m_socConnection,buf,len,0);

} catch (...) {ErrorLog("Error in CMailSession::SendResponse");}

return nResponseType;

}


CString CMailSession::ExecuteExternalFile(CString csExeName, CString csArguments)
{
  CString csExecute;
  csExecute=csExeName + " " + csArguments;
  
  SECURITY_ATTRIBUTES secattr; 
  ZeroMemory(&secattr,sizeof(secattr));
  secattr.nLength = sizeof(secattr);
  secattr.bInheritHandle = TRUE;

  HANDLE rPipe, wPipe;

  CreatePipe(&rPipe,&wPipe,&secattr,0);

  STARTUPINFO sInfo; 
  ZeroMemory(&sInfo,sizeof(sInfo));
  PROCESS_INFORMATION pInfo; 
  ZeroMemory(&pInfo,sizeof(pInfo));
  sInfo.cb=sizeof(sInfo);
  sInfo.dwFlags=STARTF_USESTDHANDLES;
  sInfo.hStdInput=NULL; 
  sInfo.hStdOutput=wPipe; 
  sInfo.hStdError=wPipe;
  char command[1024]; strcpy(command,csExecute.GetBuffer(csExecute.GetLength()));

  CreateProcess(0, command,0,0,TRUE,NORMAL_PRIORITY_CLASS|CREATE_NO_WINDOW,0,0,&sInfo,&pInfo);
  CloseHandle(wPipe);

  char buf[100];
  DWORD reDword; 
  CString m_csOutput,csTemp;
  BOOL res;
  do
  {
     res=::ReadFile(rPipe,buf,100,&reDword,0);
     csTemp=buf;
     m_csOutput+=csTemp.Left(reDword);

  } while(res);

  return m_csOutput;
}


void CMailSession::GetMD5(char * cMD5Source)
{
	unsigned char pBuf[200];
	unsigned long uRead = 0;

	uRead=strlen(cMD5Source);
	memcpy(pBuf,cMD5Source,uRead);
	MD5_CTX m_PlainText;
	MD5Init(&m_PlainText, 0);
	MD5Update(&m_PlainText, pBuf, uRead);
	MD5Final(&m_PlainText);

	CString sMD5="", sTemp="";    
	for(int i = 0; i < 16; i++)
	{
		sTemp.Format("%02X", m_PlainText.digest[i]);
		sMD5+=sTemp;
	}

	sMD5.MakeLower();

	strcpy(cMD5Source,sMD5);
}


int CMailSession::ProcessDATAEnd(void)
{try{

	m_nStatus = SMTP_STATUS_HELO;
	
	char cText[500];

	CloseHandle(m_hMsgFile); m_nFileOpenClosed = 2;

	sprintf(cText,"Close file %s",m_szFileName); Log2(nSessionNo,cSessionIP,cText);


	if(m_nAddNewAccountStatus==2)
	{
		AddNewAccount(m_szFileName);
		DeleteFile(m_szFileName); strcpy(m_szFileName,"");	
		return SendResponse(250);
	}


	if(m_nNullsender==1)
	{
		if(IsReturnMail(m_szFileName)==1)
		{
			char cMD5Source[500];
			TCHAR szDateOut[1024];
			GetRFCTime(szDateOut);
			sprintf(cMD5Source,"%s_%d",szDateOut,rand());
			GetMD5(cMD5Source);
		
			char returned_file_name[200];
			sprintf(returned_file_name,"%s\\returnedmail\\mbox\\%s.eml",GetDomainPathUsers(),cMD5Source);
		
			if(!CopyFile(m_szFileName,returned_file_name,TRUE))
			{
				sprintf(cText,"ERROR copy file %s to %s",m_szFileName,returned_file_name); Log2(nSessionNo,cSessionIP,cText);
			}
			else
			{
				sprintf(cText,"Copy file %s to %s",m_szFileName,returned_file_name); Log2(nSessionNo,cSessionIP,cText);
			}

			DeleteFile(m_szFileName); strcpy(m_szFileName,"");	
			return SendResponse(250);
		}
	}


	// If we dont't have a Delivery-date, we insert it. Works efficient with huge files.
	if(m_nDeliveryDate==0)
	{
		try
		{
			char * filebuffer = NULL; 

			DWORD nFileSize = 0; DWORD nLen=0;
			CFileException er; CFile* pFileRead = new CFile(); 
			if(pFileRead->Open(m_szFileName, CFile::modeRead , &er)) 
			{
				nLen = pFileRead->GetLength();
				filebuffer = new char[nLen+500];
				nFileSize = pFileRead->ReadHuge(filebuffer,nLen);
				pFileRead->Close();
			}   delete pFileRead;

			filebuffer[nFileSize]=0; 

			char *ptr; int nPos = 0;

			// Try to place it before this headerline
			ptr = strstr(filebuffer, "\r\nReceived: ");
			if(!ptr) { ptr = strstr(filebuffer, "\r\nDate: "); }
			if(!ptr) { ptr = strstr(filebuffer, "\r\nMIME-Version: "); }
			if(!ptr) { ptr = strstr(filebuffer, "\r\nEnvelope-to: "); }
			
			nPos = ptr - filebuffer; 

			char cInsert[255]; 	TCHAR szDate[255]; GetRFCTime(szDate); 

			if(nPos<0) 
			{
				nPos=0;
				sprintf(cInsert,"Delivery-date: %s\r\n", szDate);
			}
			else
			{
				sprintf(cInsert,"\r\nDelivery-date: %s", szDate); 
			}
			
			// Move memory by insert length at the found position and insert the new part
			int nInsertLength = strlen(cInsert);
			memcpy(filebuffer + nInsertLength + nPos,filebuffer + nPos,strlen(filebuffer) - nPos); 
			memcpy(filebuffer + nPos, cInsert, nInsertLength);
			nFileSize = nFileSize + nInsertLength;
			filebuffer[nFileSize] = 0;

			// Write it back
			CFileException ew;
			CFile* pFileWrite = new CFile();
			if(pFileWrite->Open(m_szFileName, CFile::modeWrite | CFile::modeCreate, &ew)) 
			{
				pFileWrite->WriteHuge(filebuffer,nFileSize);
				pFileWrite->Close();
			}	delete pFileWrite;
		
			delete [] filebuffer;

			sprintf(cText,"Added %s",cInsert); Log2(nSessionNo,cSessionIP,cText);

		} catch (...) {ErrorLog("Error in CMailSession::ProcessDATAEnd DelDate ");}
	}
	
	///////////////


	char msg_file_name[500]; int nRelayEmail = 0;

	for(int i=0;i<m_nRcptCount;i++)
	{
		int nSpamAssassin = 0;

		try{
	
			if(strlen(g_szSpamFilePath)>3) 
			{
				if(m_nDataCount<200000) // Limit it to about 200kB max..
				{
					nSpamAssassin = 1; 	// http://sourceforge.net/projects/sawin32/files/SpamAssassin%20for%20Win32/SpamAssassin%20for%20Win32%20v3.2.3.5/SpamAssassin-3.2.3.5-win32.zip/download
				}
			}

		} catch (...) {ErrorLog("Error in CMailSession::ProcessDATAEnd 010 ");}

	
		if(m_nIsOutboundEmail==1) // Relay Messages
		{
			try
			{
				nSpamAssassin = 0; // No Spam check for our emails

				if(nRelayEmail==0) 	// Copy Relay messages only once
				{
					nRelayEmail = 1;
						
					char cMD5Source[500];

					TCHAR szDateOut[1024];
					GetRFCTime(szDateOut);
					sprintf(cMD5Source,"%s_%s_%d",m_FromAddress.GetAddress(),szDateOut,rand());
					GetMD5(cMD5Source);

					sprintf(msg_file_name,"%soutgoing\\%s.eml",g_szConfigFilePath,cMD5Source);
				
					// copy original email to archive folder
					char archive_file_name[200];
					sprintf(archive_file_name,"%soriginals\\out_%s.eml",g_szConfigFilePath,cMD5Source);
					

					if(!CopyFile(m_szFileName,archive_file_name,TRUE))
					{
						sprintf(cText,"ERROR copy file %s to %s",m_szFileName,archive_file_name); Log2(nSessionNo,cSessionIP,cText);
					}
					else
					{
						sprintf(cText,"Copy file %s to %s",m_szFileName,archive_file_name); Log2(nSessionNo,cSessionIP,cText);
					}


					if(!CopyFile(m_szFileName,msg_file_name,TRUE))
					{
						sprintf(cText,"ERROR copy file %s to %s",m_szFileName,msg_file_name); Log2(nSessionNo,cSessionIP,cText);
					}
					else
					{
						sprintf(cText,"Copy file %s to %s",m_szFileName,msg_file_name); Log2(nSessionNo,cSessionIP,cText);
					}
				}

			} catch (...) {ErrorLog("Error in CMailSession::ProcessDATAEnd 020 ");}
		}
		else // Internal Emails
		{
			try{

				char cMD5Source[500];

				TCHAR szDateOut[1024];
				GetRFCTime(szDateOut);

				sprintf(cMD5Source,"%s_%d",szDateOut,rand());
			
				GetMD5(cMD5Source);
			
				if(nSpamAssassin==1)
				{
					sprintf(msg_file_name,"%s\\mbox\\%s.sam",m_ToAddress[i].GetMBoxPath(),cMD5Source);
				}
				else
				{
					sprintf(msg_file_name,"%s\\mbox\\%s.eml",m_ToAddress[i].GetMBoxPath(),cMD5Source);
				}


				// copy original email to archive folder
				char archive_file_name[200];
				sprintf(archive_file_name,"%soriginals\\%s.eml",g_szConfigFilePath,cMD5Source);
				

				if(!CopyFile(m_szFileName,archive_file_name,TRUE))
				{
					sprintf(cText,"ERROR copy file %s to %s",m_szFileName,archive_file_name); Log2(nSessionNo,cSessionIP,cText);
				}
				else
				{
					sprintf(cText,"Copy file %s to %s",m_szFileName,archive_file_name); Log2(nSessionNo,cSessionIP,cText);
				}

			
				if(!CopyFile(m_szFileName,msg_file_name,TRUE))
				{
					sprintf(cText,"ERROR copy file %s to %s",m_szFileName,msg_file_name); Log2(nSessionNo,cSessionIP,cText);
				}
				else
				{
					sprintf(cText,"Copy file %s to %s",m_szFileName,msg_file_name); Log2(nSessionNo,cSessionIP,cText);
				}



			} catch (...) {ErrorLog("Error in CMailSession::ProcessDATAEnd 030 ");}
		}
		

		if(nSpamAssassin==1)
		{	
			try{


			char cParameters[200]; char cPathFile[500]; 

			CString sPath = msg_file_name; sPath.Replace("Program Files","progra~1");

			sprintf(cParameters,"%s %s0", sPath, sPath);

			ExecuteExternalFile(g_szSpamFilePath,cParameters);
			
			sprintf(cPathFile,"%s0",msg_file_name);

			char * filebuffer = NULL; 
			
			DWORD nFileSize = 0; DWORD nLen=0;


			try
			{

			CFileException er; CFile* pFileRead = new CFile(); 

			if(pFileRead->Open(cPathFile, CFile::modeRead , &er)) 
			{
				nLen = pFileRead->GetLength();
		
				if(nLen>3000) {nLen=3000;}

				filebuffer = new char[nLen+10];
				nFileSize = pFileRead->Read(filebuffer,nLen);
				pFileRead->Close();
			}   
			
			delete pFileRead;
		
			} catch (...) {ErrorLog("Error in CMailSession::ProcessDATAEnd 043 ");}
		
			
			filebuffer[nLen] = 0;

			CString sHeader = filebuffer; if(filebuffer) { delete [] filebuffer;}

			// Check Spam

			int nSpamCheckResult = 0;

			char cSpamkey[100];
			strcpy(cSpamkey,"X-Spam-Status: Yes");
			int nSpamPos = sHeader.Find(cSpamkey,0);

			if(nSpamPos>-1) 
			{
				nSpamCheckResult = 1;
			
				/*
					The Spamcheck limit is set at the user_prefs file. -> required_score 12.1 
					
					The user_prefs file is on several locations. It's a mess, here are the paths that work:

					Config file on Proxy Servers:

					C:\Windows\System32\config\systemprofile\.spamassassin\ 

					Its NOT at C:\Users\Administrator\.spamassassin\  

					On XP Notebook, this path works:
					
					C:\Documents and Settings\Christian\.spamassassin\ 
					
					Others say:

					C:\Documents and Settings\LocalService\.spamassassin\
				*/
			}

			if(nSpamCheckResult==0)
			{
				// No spam
				CString sNewFilePath = cPathFile;
		
				sNewFilePath.Replace(".sam0",".eml");
				
				if(!CopyFile(cPathFile,sNewFilePath,TRUE))
				{
					sprintf(cText,"ERROR copy file %s to %s",cPathFile,sNewFilePath); Log2(nSessionNo,cSessionIP,cText);
				}
				else
				{
					sprintf(cText,"Copy file %s to %s",cPathFile,sNewFilePath); Log2(nSessionNo,cSessionIP,cText);
				}
			}
			else
			{
				// Copy it to the spam folder

				if(g_nConfigKeepSpam==1)
				{
					char cMD5Source[500], cFilename[500], cSpamPathFile[500];

					TCHAR szDateOut[1024]; GetRFCTime(szDateOut);
					sprintf(cMD5Source,"%s_%s",m_FromAddress.GetAddress(),szDateOut);

					GetMD5(cMD5Source);

					sprintf(cFilename,"%s.eml",cMD5Source);
					sprintf(cSpamPathFile,"%sspam\\%s",g_szConfigFilePath,cFilename);

					if(!CopyFile(cPathFile,cSpamPathFile,TRUE))
					{
						sprintf(cText,"ERROR copy file %s to %s",cPathFile,cSpamPathFile); Log2(nSessionNo,cSessionIP,cText);
					}
					else
					{
						sprintf(cText,"Copy file %s to %s",cPathFile,cSpamPathFile); Log2(nSessionNo,cSessionIP,cText);
					}
				}
			}

			try{

				DeleteFile(cPathFile); //.sam0
				DeleteFile(msg_file_name); //.sam

			} catch (...) {ErrorLog("Error in CMailSession::ProcessDATAEnd 0461 ");}


			} catch (...) {ErrorLog("Error in CMailSession::ProcessDATAEnd 0481 ");}

		}
	}



	




	try{
		DeleteFile(m_szFileName);
		strcpy(m_szFileName,"");
	} catch (...) {ErrorLog("Error in CMailSession::ProcessDATAEnd 050 ");}

} catch (...) {ErrorLog("Error in CMailSession::ProcessDATAEnd");}

	return SendResponse(250);
}


bool CMailSession::CreateNewMessage(char * m_szFileNameIn)
{try{

	m_hMsgFile = CreateFile(m_szFileNameIn, GENERIC_WRITE, FILE_SHARE_READ, NULL, CREATE_ALWAYS, FILE_ATTRIBUTE_NORMAL, NULL);

	char cText[200]; sprintf(cText,"Create file %s",m_szFileNameIn); Log2(nSessionNo,cSessionIP,cText);

	m_nFileOpenClosed = 1;

} catch (...) {ErrorLog("Error in CMailSession::CreateNewMessage");}

return (m_hMsgFile!=NULL);
}


int CMailSession::IsReturnMail(char * filename)
{	
	int nOK=0; 

	try {
		
		char filebuffer[5020]; 
		DWORD nLen=0;
		CFileException er; CFile* pFileRead = new CFile(); 
		if(pFileRead->Open(filename, CFile::modeRead , &er)) 
		{
			nLen = pFileRead->GetLength();
			if(nLen>5000) {nLen=5000;}
			DWORD nFileSize = pFileRead->Read(filebuffer,nLen);
			pFileRead->Close();
		}   
		delete pFileRead;
		
		filebuffer[nLen] = 0; CString sContent = filebuffer; sContent.MakeLower();

		int nMailFailCount = 0;

		if(sContent.Find("this is a permanent error",0)>-1) {nMailFailCount++;}
		if(sContent.Find("mailer-daemon@",0)>-1) {nMailFailCount++;}
		if(sContent.Find("postmaster@",0)>-1) {nMailFailCount++;}
		if(sContent.Find("mail delivery system",0)>-1) {nMailFailCount++;}
		if(sContent.Find("for further assistance, please send mail to",0)>-1) {nMailFailCount++;}
		if(sContent.Find("this message was created automatically by",0)>-1) {nMailFailCount++;}
		if(sContent.Find("is an automatically generated",0)>-1) {nMailFailCount++;}
		if(sContent.Find("delivery status notification",0)>-1) {nMailFailCount++;}
		if(sContent.Find("undeliverable:",0)>-1) {nMailFailCount++;}


		
		if(nMailFailCount>0)
		{
			nOK=1;
		}	

} catch (...) {ErrorLog("Error in CMailSession::IsReturnMail");}

return nOK;
}




void CMailSession::AddNewAccount(char * filename)
{try{
		CString sSalt = "*b[h+6]f,g%a)c@cb7#";

		char filebuffer[5020]; 
		DWORD nFileSize = 0; DWORD nLen=0;
		CFileException er; CFile* pFileRead = new CFile(); 
		if(pFileRead->Open(filename, CFile::modeRead , &er)) 
		{
			nLen = pFileRead->GetLength();
			if(nLen>5000) {nLen=5000;}
			nFileSize = pFileRead->Read(filebuffer,nLen);
			pFileRead->Close();
		}   
		delete pFileRead;
		
		filebuffer[nLen] = 0; CString sHeader = filebuffer; 

		int nAuthStringPos = sHeader.Find("gjki",0);

		if(nAuthStringPos>-1)
		{
			CString sGJKI = sHeader.Mid(nAuthStringPos+4,25);

			char newAccountEmail[100], cMD5Source[200]; 
			
			sprintf(newAccountEmail,"%s@%s",newAccountUser,g_szConfigDomain);

			sprintf(cMD5Source,"%s%s%s",sGJKI,sSalt,newAccountEmail); GetMD5(cMD5Source);
			CString sVK9ECalc = cMD5Source;
			int nAuthStringPos2 = sHeader.Find("vk9e",0);
			if(nAuthStringPos2>-1)
			{
				CString sVK9E = sHeader.Mid(nAuthStringPos2+4,32);
	
				if(sVK9ECalc==sVK9E) // Will only match if the sender knows "salt"
				{
					// Password: Use submitted password located between "zb9f5" and "kxp7mj"
					int nAuthStringPos4 = sHeader.Find("zb9f5",0);
					if(nAuthStringPos4>-1)
					{
						CString sZB9F = sHeader.Mid(nAuthStringPos4+5,32);
						int nAuthStringPos5 = sZB9F.Find("kxp7mj",0);
						if(nAuthStringPos5>3)
						{
							char password[100]; strcpy(password,sZB9F.Mid(0,nAuthStringPos5));
							CreateNewAccount(newAccountUser,password);
						}
					}
				}
			}

			m_nAddNewAccountStatus = 3;
		}
		

} catch (...) {ErrorLog("Error in CMailSession::AddNewAccount");}
}



bool CMailSession::CreateNewAccount(char * username, char * password)
{
	bool bOK = false; char cText[200]; 

	try {

		CString sDomainPathUserMbox; sDomainPathUserMbox.Format("%s\\%s\\mbox",GetDomainPathUsers(),username);
		CString sDomainPathUser; sDomainPathUser.Format("%s\\%s",GetDomainPathUsers(),username);

		// Delete eventual existing old password
		WIN32_FIND_DATA FindFileData; HANDLE hFind = INVALID_HANDLE_VALUE;
		char DirSpec[500]; sprintf(DirSpec,"%s\\*.pwd",sDomainPathUser);
		hFind = FindFirstFile(DirSpec, &FindFileData);
		if (hFind == INVALID_HANDLE_VALUE) 
		{} else 
		{
			sprintf(DirSpec,"%s\\%s",sDomainPathUser,FindFileData.cFileName);
			remove(DirSpec);
			
			FindClose(hFind);
		}

		if(PathFileExists(sDomainPathUserMbox)) // If it already exists, don't create a new one
		{		
			sprintf(cText,"\nChanged password of user account %s@%s\n",GetDomainPathUsers(),sDomainPathUserMbox,username,g_szConfigDomain); Log2(nSessionNo,cSessionIP,cText);
		}
		else
		{
			mkdir(GetDomainPathUsers());
			mkdir(sDomainPathUser);
			mkdir(sDomainPathUserMbox);

			sprintf(cText,"\nCreate new user account %s@%s\n",GetDomainPathUsers(),sDomainPathUserMbox,username,g_szConfigDomain); Log2(nSessionNo,cSessionIP,cText);
		}

		CString sDomainPathUserPassword;
		sDomainPathUserPassword.Format("%s\\%s\\%s.pwd",GetDomainPathUsers(),username,password);
	
		FILE * pFile;
		if(pFile = fopen (sDomainPathUserPassword,"w"))
		{
			fputs ("",pFile); 
			fclose (pFile);
		}

		bOK = true;

	} catch (...) {ErrorLog("Error in CMailSession::CreateNewAccount");}

	return bOK;
}


CString CMailSession::GetDomainPathUsers()
{
	char cDomainPathUsers[128];

	if(strcmp(g_szDataDirectory,"")!=0) // Path without \\ at the end
	{
		strcpy(cDomainPathUsers,g_szDataDirectory);
	}
	else
	{
		sprintf(cDomainPathUsers,"%s%s",g_szConfigFilePath,g_szConfigDomain);
	}
	return cDomainPathUsers;
}


bool CMailSession::DeleteAccount(char * username, char * password)
{
	bool bOK = false;
	

	try {


	} catch (...) {ErrorLog("Error in CMailSession::DeleteAccount");}


	return bOK;
}
