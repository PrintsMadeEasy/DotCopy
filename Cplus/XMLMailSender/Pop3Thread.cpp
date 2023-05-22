// Pop3Thread.cpp: Implementierung der Klasse CPop3Thread.
//
//////////////////////////////////////////////////////////////////////

#include "stdafx.h"
#include "Pop3Thread.h"
#include <direct.h>

#ifdef _DEBUG
#undef THIS_FILE
static char THIS_FILE[]=__FILE__;
#define new DEBUG_NEW
#endif

#pragma warning (disable:4786)

#include <shlwapi.h>
#pragma comment(lib, "shlwapi.lib")

char g_szDomainPath[400];

extern char g_szConfigDomain[128];
extern char g_szConfigFilePath[128];
extern int  g_nConfigRunLog;
extern char g_szDataDirectory[128];
extern char g_szBindIP[20];

extern int nPop3SplitCount;

//////////////////////////////////////////////////////////////////////
// Konstruktion/Destruktion
//////////////////////////////////////////////////////////////////////

CPop3Thread::CPop3Thread()
{

}


CPop3Thread::~CPop3Thread()
{

}

void CPop3Thread::SplitPop3Log(char *cFilename)
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

void CPop3Thread::Log(char *cText)
{
	if(g_nConfigRunLog==1)
	{
		nPop3SplitCount++;
		if(nPop3SplitCount > 1000) // Check it every 5000 Lines
		{
			SplitPop3Log("pop3log");
			nPop3SplitCount = 0;
		}

		FILE * pFile;
		if(pFile = fopen (CString(g_szConfigFilePath)+"pop3log.txt","a"))
		{
			fputs (CTime::GetCurrentTime().Format("20%y/%m/%d  %H:%M:%S ")+CString(cText),pFile); 
			fclose (pFile);

			TRACE(cText);
		}
	}
}

void CPop3Thread::ErrorLog(char *cText)
{
	FILE * pFile;
	if(pFile = fopen (CString(g_szConfigFilePath)+"ErrorLog.txt","a"))
	{
		fputs (CTime::GetCurrentTime().Format("20%y/%m/%d  %H:%M:%S ")+CString(cText),pFile); 
		fclose (pFile);

		TRACE(cText);
	}
}


DWORD WINAPI ConnectionThread(void *param)
{try{
	int len;
	char buf[2050]; memset(buf,0,2050);

	Pop3ThreadParameter *nParam = (Pop3ThreadParameter*) param;


	// 	strcpy(cSessionIP,nParam->cSessionIP);




	// 	CPop3Session *pSession = new CPop3Session((SOCKET)param);
	CPop3Session *pSession = new CPop3Session((SOCKET)nParam->nSocket, nParam->cSessionIP);




	pSession->SendResponse(POP3_WELCOME_RESPONSE);

	while(len=recv(pSession->m_socConnection,buf,sizeof(buf),0))
	{
		if(-1==pSession->ProcessCMD(buf,len))
		{
			CPop3Thread pop3;
			pop3.Log("Connection thread closing...\n");

			if(pSession) {delete pSession;}
			
			return 0;
		}
		
		memset(buf,0,2050);

	}	

	if(pSession) {delete pSession;}



} catch (...) {CPop3Thread pop3; pop3.ErrorLog("Error in ProcessCMD");}

	return -1;
}


void CPop3Thread::AcceptConnections(SOCKET server_soc)
{try{

	SOCKET soc_client;
	char cLog[1000];

	sprintf(cLog,"POP3 Server is ready and listening to TCP port %d ...\n",POP3_PORT); Log(cLog);

	while(true)
	{
		sockaddr nm;
		int len=sizeof(sockaddr);

		Log("Waiting for incoming connection...\n");

		if(INVALID_SOCKET==(soc_client=accept(server_soc,&nm,&len)))
		{
			sprintf(cLog,"Error: Invalid Soceket returned by accept(): %d\n",WSAGetLastError()); Log(cLog);
		}
		else
		{
			Log("Accepted new connection. Now creating session thread...\n");
		}	


					
		// Get IP
		BYTE nNM[10]; char cThreadIP[25]; memcpy(nNM,&nm,8); sprintf(cThreadIP,"%d.%d.%d.%d",nNM[4],nNM[5],nNM[6],nNM[7]);




		Pop3ThreadParameter nPara;

		nPara.nSocket = (void*)soc_client;
		strcpy(nPara.cSessionIP,cThreadIP);	



		DWORD dwThreadId, dwThrdParam = 1; 
		HANDLE hThread; 

		hThread = CreateThread( 
			NULL,                        // default security attributes 
			0,                           // use default stack size  
			ConnectionThread,            // thread function 
			(LPVOID)&nPara,    //(void *)soc_client,			// (void *)pSession,            // argument to thread function 
			0,                           // use default creation flags 
			&dwThreadId);                // returns the thread identifier 


		Sleep(100);

		if(hThread == NULL) 
		{
			Log( "CreateThread failed." ); 
		}
	}

} catch (...) {ErrorLog("Error in AcceptConnections");}}


void CPop3Thread::StartPOP3()
{try{

	WORD wVersionRequested;
	WSADATA wsaData;
	int err; 
	char cLog[1000];

	wVersionRequested = MAKEWORD( 2, 2 );

	char direct[300], g_szDirectoryPath[300];

	strcpy(direct, g_szConfigFilePath);
	strcpy(g_szDirectoryPath, direct);

	if(!PathFileExists(g_szDirectoryPath))
	{
		Log("Active directory path not found\n");
		// return FALSE;
	}


//  Use optional DataPath if set
	if(strcmp(g_szDataDirectory,"")!=0) // Path without \\ at the end
	{
		strcpy(g_szDomainPath,g_szDataDirectory);
	}
	else
	{
		sprintf(g_szDomainPath,"%s%s",g_szConfigFilePath,g_szConfigDomain);
	}

	if(!PathFileExists(g_szDomainPath))
	{
		sprintf(cLog,"Domain not found on Active Directory: There should be a directory->%s Create it and try again.\n", g_szDomainPath); Log(cLog);
		// return FALSE;
	}

	err = WSAStartup( wVersionRequested, &wsaData );

	if ( err != 0 ) {
		sprintf(cLog,"Error in  initializing. Quiting with error code: %d\n",WSAGetLastError()); Log(cLog);
		Sleep(5000);
		exit(WSAGetLastError());
	}

	SOCKET soc=socket(PF_INET, SOCK_STREAM, 0) ;

	if(soc==INVALID_SOCKET)
	{
		sprintf(cLog,"Error: Invalid socket. Quiting with error code: %d\n",WSAGetLastError()); Log(cLog);
		Sleep(5000);
		exit(WSAGetLastError());
	}

	SOCKADDR_IN soc_addr;

	soc_addr.sin_family=AF_INET;
	soc_addr.sin_port=htons(POP3_PORT);

	if(strlen(g_szBindIP)>6)
	{
		LPHOSTENT lpHost=gethostbyname(g_szBindIP);
		soc_addr.sin_addr=*(LPIN_ADDR)(lpHost->h_addr_list[0]);
	}
	else
	{
		LPHOSTENT lpHost=gethostbyname("localhost");		
		soc_addr.sin_addr=*(LPIN_ADDR)(lpHost->h_addr_list[0]);
	}

	if(bind(soc,(const struct sockaddr*)&soc_addr,sizeof(soc_addr)))
	{
		if(g_nConfigRunLog==1)
		{
			char cText[200]; sprintf(cText,"POP3: ERROR BINDING IP '%s' !!\n\n",g_szBindIP);
			Log(cText);
		}

		sprintf(cLog,"Error: Can not bind socket. Another server running? Quitting with error code: %d\n",WSAGetLastError()); Log(cLog);
		Sleep(5000);
		exit(WSAGetLastError());
	}

	if(SOCKET_ERROR==listen(soc,SOMAXCONN))
	{
		sprintf(cLog,"Error: Can not listen to socket. Quitting with error code: %d\n",WSAGetLastError());  Log(cLog);
		Sleep(5000);
		exit(WSAGetLastError());
	}

	AcceptConnections(soc);

	Log("You should not see this message. It is an abnormal condition. Terminating...");

} catch (...) {ErrorLog("Error in StartPOP3");}}


DWORD WINAPI StartPop3Thread(LPVOID pParam)
{try{

	CPop3Thread sPop3;
	sPop3.StartPOP3();


} catch (...) {CPop3Thread pop3; pop3.ErrorLog("Error in StartPop3Thread");}
	
	return 0;

}


void CPop3Thread::StartPOP3Thread()
{try{

	HANDLE hPop3Thread; DWORD Pop3ThreadID; 
	hPop3Thread = CreateThread ( NULL, 0, StartPop3Thread, NULL , 0, &Pop3ThreadID);
	Sleep(100);

} catch (...) {CPop3Thread pop3; pop3.ErrorLog("Error in StartPOP3Thread");}}


///////////////////   CPOP3SESSION CLASS IMPLEMENTATION /////////////////////////////////////////////////////////



int CPop3Session::Log(char *cText, char * cCallingProcedure)
{

	m_nProcessCount++;
	if(m_nProcessCount>500)
	{
		return ProcessQUIT("",0);
	}

	if(g_nConfigRunLog==1)
	{
		FILE * pFile; char cTextFormat[1000];
		if(pFile = fopen (CString(g_szConfigFilePath)+"pop3log.txt","a"))
		{
			sprintf(cTextFormat,"%s  %16s  (%14s) %s \n", CTime::GetCurrentTime().Format("20%y/%m/%d  %H:%M:%S"),cSessionIP,cCallingProcedure,cText);

			fputs (cTextFormat,pFile); 
			fclose (pFile);

			TRACE(cText);
		}
	}

	return 0;
}


CPop3Session::CPop3Session(SOCKET client_soc, char * cIP)
{try{

	m_nState=POP3_STATE_AUTHORIZATION;
	m_socConnection=client_soc;
	m_szUserName[0]=0;
	m_szUserHome[0]=0;
	m_pPop3MessageHead=NULL;
	m_pPop3MessageList=NULL;
	m_nLastMsg=0;
	m_nProcessCount=0;

	strcpy(cSessionIP, cIP);


} catch (...) {CPop3Thread pop3; pop3.ErrorLog("Error in CPop3Session");}}


CPop3Session::~CPop3Session(void)
{try{

	if(m_pPop3MessageHead)
	{
		delete(m_pPop3MessageList);
	}

} catch (...) {CPop3Thread pop3; pop3.ErrorLog("Error in ~CPop3Session");}}


int CPop3Session::ProcessCMD(char *buf, int len)
{try{


	m_nProcessCount++;
	if(m_nProcessCount>500)
	{
		return ProcessQUIT(buf, len);
	}



	if(_strnicmp(buf,"USER",4)==0)
	{
		return ProcessUSER(buf, len);
	}
	else if(_strnicmp(buf,"PASS",4)==0)
	{
		return ProcessPASS(buf, len);
	}
	else if(_strnicmp(buf,"QUIT",4)==0)
	{
		return ProcessQUIT(buf, len);
	}
	else if(_strnicmp(buf,"STAT",4)==0)
	{
		return ProcessSTAT(buf, len);
	}
	else if(_strnicmp(buf,"LIST",4)==0)
	{
		return ProcessLIST(buf, len);
	}
	else if(_strnicmp(buf,"UIDL",4)==0)
	{
		return ProcessUIDL(buf, len);
	}
	else if(_strnicmp(buf,"RETR",4)==0)
	{
		return ProcessRETR(buf, len);
	}	
	else if(_strnicmp(buf,"DELE",4)==0)
	{
		return ProcessDELE(buf, len);
	}
	else if(_strnicmp(buf,"NOOP",4)==0)
	{
		return ProcessNOOP(buf, len);
	}
	else if(_strnicmp(buf,"LAST",4)==0)
	{
		return ProcessLAST(buf, len);
	}
	else if(_strnicmp(buf,"RSET",4)==0)
	{
		return ProcessRSET(buf, len);
	}
	else if(_strnicmp(buf,"RPOP",4)==0)
	{
		return ProcessRSET(buf, len);
	}
	else if(_strnicmp(buf,"TOP",4)==0)
	{
		return ProcessRSET(buf, len);
	}
	
	return SendResponse(POP3_DEFAULT_NEGATIVE_RESPONSE);


} catch (...) {CPop3Thread pop3; pop3.ErrorLog("Error in CPop3Session::ProcessCMD");}

	return 0;
}


int CPop3Session::SendResponse(char *buf)
{try{

	int len=(int)strlen(buf);
	TRACE("Direct Sending: %s",buf);
	send(m_socConnection,buf,len,0);

} catch (...) {CPop3Thread pop3; pop3.ErrorLog("Error in CPop3Session::SendResponse");}

	return 0;
}



int CPop3Session::SendResponse(int nResponseType, char *msg)
{try{

	char buf[100];
	int len;

	if(nResponseType==POP3_DEFAULT_AFFERMATIVE_RESPONSE)
	{
		if(strlen(msg))
			sprintf(buf,"+OK %s\r\n",msg);
		else
			sprintf(buf,"+OK %s\r\n","Action performed");
	}
	else if(nResponseType==POP3_DEFAULT_NEGATIVE_RESPONSE)
		sprintf(buf,"-ERR %s\r\n","An error occured");
	else if(nResponseType==POP3_WELCOME_RESPONSE)
		sprintf(buf,"+OK %s %s POP3 Server ready on %s\r\n",APP_TITLE, APP_VERSION, g_szConfigDomain);
	else if(nResponseType==POP3_STAT_RESPONSE)
		sprintf(buf,"+OK %d %ld\r\n",m_nTotalMailCount, m_dwTotalMailSize);

	len=(int)strlen(buf);

	TRACE("Sending: %s",buf);
	send(m_socConnection,buf,len,0);

} catch (...) {CPop3Thread pop3; pop3.ErrorLog("Error in CPop3Session::SendResponse");}

	return nResponseType;
}



int CPop3Session::ProcessUSER(char* buf, int len)
{try{

	TRACE("ProcessUSER\n");
	buf[len-2]=0;	buf+=5;
	
	TRACE("User= [%s]\n",buf);
	strcpy(m_szUserName,buf);

	// Cut @domain.com from username
	CString sName = m_szUserName;
	int nAddPos = sName.Find("@",0);
	if(nAddPos>-1)
	{
		sName = sName.Mid(0,nAddPos);
		strcpy(m_szUserName,sName);
	}

	char cLog[1000]; 

	//  Use optional DataPath if set
	if(strcmp(g_szDataDirectory,"")!=0) // Path without \\ at the end
	{
		strcpy(g_szDomainPath,g_szDataDirectory);
	}
	else
	{
		sprintf(g_szDomainPath,"%s%s",g_szConfigFilePath,g_szConfigDomain);
	}

	sprintf(m_szUserHome,"%s\\%s",g_szDomainPath,m_szUserName);

	if(!PathFileExists(m_szUserHome))
	{
		TRACE("User %s's Home '%s' not found\n",m_szUserName, m_szUserHome);
		sprintf(cLog,"User=%s NOT FOUND",m_szUserName); Log(cLog,"ProcessUser");
		
		return SendResponse(POP3_DEFAULT_NEGATIVE_RESPONSE);
	}

	sprintf(cLog,"User=%s OK",m_szUserName); Log(cLog,"ProcessUser");

	TRACE("OK User %s Home %s\n",m_szUserName, m_szUserHome);

	if(m_nState!=POP3_STATE_AUTHORIZATION)
	{
		return SendResponse(POP3_DEFAULT_NEGATIVE_RESPONSE);
	}

} catch (...) {CPop3Thread pop3; pop3.ErrorLog("Error in CPop3Session::ProcessUSER");}

	return SendResponse(POP3_DEFAULT_AFFERMATIVE_RESPONSE);
}


int CPop3Session::ProcessPASS(char* buf, int len)
{try{

	TRACE("ProcessPASS\n");
	buf[len-2]=0;	buf+=5;
	if(buf[len-2]==10) buf[len-2]=0;

	TRACE("Password= [%s]\n",buf);

	strcpy(m_szPassword,buf);

	if(m_nState!=POP3_STATE_AUTHORIZATION || strlen(m_szUserName)<1)
	{
		return SendResponse(POP3_DEFAULT_NEGATIVE_RESPONSE);
	}

	if(Login(m_szUserName, m_szPassword))
		return SendResponse(POP3_DEFAULT_AFFERMATIVE_RESPONSE);
	else
		return SendResponse(POP3_DEFAULT_NEGATIVE_RESPONSE);

} catch (...) {CPop3Thread pop3; pop3.ErrorLog("Error in CPop3Session::ProcessPASS");}

	return 0;
}


int CPop3Session::ProcessQUIT(char* buf, int len)
{try{

	TRACE("ProcessQUIT\n");
	if(m_nState==POP3_STATE_TRANSACTION)
		m_nState=POP3_STATE_UPDATE;
	
	SendResponse(POP3_DEFAULT_AFFERMATIVE_RESPONSE,"Goodbye");

	UpdateMails();

} catch (...) {CPop3Thread pop3; pop3.ErrorLog("Error in CPop3Session::ProcessQUIT");}

	return -1;
}


bool CPop3Session::Login(char* szName, char* szPassword)
{try{

	char lpPwdFile[300], lpUserHome[300];
	
	sprintf(lpPwdFile,"%s\\%s\\%s.pwd",g_szDomainPath,szName,szPassword);

	TRACE("Pwd file: %s\n",lpPwdFile);

	if(PathFileExists(lpPwdFile))
	
	if(strlen(szName) && strlen(szPassword))
	{
		TRACE("Password ok\n");
		Log("Password OK","Login");
		m_nState=POP3_STATE_TRANSACTION;

		sprintf(lpUserHome,"%s\\%s",g_szDomainPath,szName);
		SetHomePath(lpUserHome);

		LockMailDrop();
		return true;
	}

} catch (...) {CPop3Thread pop3; pop3.ErrorLog("Error in CPop3Session::Login");}

	return false;
}


int CPop3Session::ProcessSTAT(char* buf, int len)
{try{

	TRACE("ProcessSTAT\n");
	Log("Process STAT","ProcessSTAT");
	if(m_nState!=POP3_STATE_TRANSACTION)
	{
		return SendResponse(POP3_DEFAULT_NEGATIVE_RESPONSE);
	}

	m_nLastMsg=1;

} catch (...) {CPop3Thread pop3; pop3.ErrorLog("Error in CPop3Session::ProcessSTAT");}

	return SendResponse(POP3_STAT_RESPONSE);
}


int CPop3Session::ProcessLIST(char* buf, int len)
{try{

	buf[len]=0;

	if(m_nState!=POP3_STATE_TRANSACTION)
	{
		return SendResponse(POP3_DEFAULT_NEGATIVE_RESPONSE);
	}

	char cLog[1000]; char response[1000];
	
	sprintf(cLog,"Process LIST %s",buf); Log(cLog,"ProcessLIST");

	SendResponse("+OK \r\n");


	for(int i=0; i < m_nTotalMailCount; i++)
	{
		
		sprintf(response, "%d %d\r\n",i+1, m_pPop3MessageList[i].GetSize());

		TRACE(response); Log(response,"ProcessLIST");
		
		SendResponse(response);
	}

	SendResponse(".\r\n");


} catch (...) {CPop3Thread pop3; pop3.ErrorLog("Error in CPop3Session::ProcessLIST");}

	return 0;
}




int CPop3Session::ProcessUIDL(char* buf, int len)
{try{

	buf[len]=0; buf+=4;

	if(m_nState!=POP3_STATE_TRANSACTION)
	{
		return SendResponse(POP3_DEFAULT_NEGATIVE_RESPONSE);
	}

	char cLog[1000]; char resp[100];
	
	sprintf(cLog,"Process UIDL %s",buf); Log(cLog,"ProcessLIST");

	int nUIDLPosition = atoi(buf);

	CString sEmailFilePath ="";

	if(nUIDLPosition==0) // "UIDL" lists all
	{
		for(int i=0; i < m_nTotalMailCount; i++)
		{
			if(i==0) 
			{
				SendResponse("+OK\r\n");
			}

			sEmailFilePath = m_pPop3MessageList[i].GetPath();
			CString sUID = sEmailFilePath.Mid(sEmailFilePath.GetLength()-36,32); sUID.MakeUpper();// Use MD5 filename as UID
			sUID.MakeUpper();
			sprintf(resp, "%d UID-%s\r\n",i+1, sUID);
			TRACE(resp); Log(resp,"ProcessUIDL");
			SendResponse(resp);
		}

		SendResponse(".\r\n");
	}

	if(nUIDLPosition>0) // "UIDL 1"only lists the asked number
	{
		sEmailFilePath = m_pPop3MessageList[nUIDLPosition-1].GetPath();
		CString sUID = sEmailFilePath.Mid(sEmailFilePath.GetLength()-36,32); sUID.MakeUpper();
		sprintf(resp, "+OK %d UID-%s\r\n", nUIDLPosition, sUID);  // Use MD5 filename as UID
		SendResponse(resp);
	}


} catch (...) {CPop3Thread pop3; pop3.ErrorLog("Error in CPop3Session::ProcessLIST");}

	return 0;
}



int CPop3Session::ProcessRETR(char* buf, int len)
{try{

	buf+=4; buf[4]='0'; buf[len-2]=0;
	int msg_id=atol(buf);

	char cText[1000];

	if(m_nState!=POP3_STATE_TRANSACTION)
	{
		sprintf(cText,"Negative Response"); TRACE(cText); Log(cText,"ProcessRETR1");
		return SendResponse(POP3_DEFAULT_NEGATIVE_RESPONSE);
	}

	if(msg_id>m_nTotalMailCount) 
	{
		sprintf(cText,"-ERR Invalid message number (id>count)"); TRACE(cText); Log(cText,"ProcessRETR2");
		return SendResponse("-ERR Invalid message number\r\n");
	}

	if(m_nLastMsg<(unsigned int)msg_id) m_nLastMsg=msg_id;

	char resp[25];
	sprintf(resp,"+OK %d octets\r\n",m_pPop3MessageList[msg_id-1].GetSize());

	Log(resp,"ProcessRETR");

	SendResponse(resp);
	SendMessageFile(m_pPop3MessageList[msg_id-1].GetPath());
	SendResponse("\r\n.\r\n");

} catch (...) {CPop3Thread pop3; pop3.ErrorLog("Error in CPop3Session::ProcessRETR");}

	return 0;
}



int CPop3Session::ProcessDELE(char* buf, int len)
{try{

	buf+=4; buf[4]='0'; buf[len-2]=0;
	int msg_id=atol(buf);

	char cText[1000]; 
	
	sprintf(cText,"ProcessDELE %d\n",msg_id); TRACE(cText); Log(cText,"ProcessDELE1");

	if(m_nState!=POP3_STATE_TRANSACTION || msg_id>m_nTotalMailCount)
	{
		sprintf(cText,"ProcessDELE: Negative Response"); TRACE(cText); Log(cText,"ProcessDELE2");
		return SendResponse(POP3_DEFAULT_NEGATIVE_RESPONSE);
	}

	m_pPop3MessageList[msg_id-1].Delete();



} catch (...) {CPop3Thread pop3; pop3.ErrorLog("Error in CPop3Session::ProcessDELE");}

	return SendResponse(POP3_DEFAULT_AFFERMATIVE_RESPONSE);
}



int CPop3Session::ProcessNOOP(char* buf, int len)
{try{

	TRACE("ProcessNOOP\n");

} catch (...) {CPop3Thread pop3; pop3.ErrorLog("Error in CPop3Session::ProcessNOOP");}

	return SendResponse(POP3_DEFAULT_NEGATIVE_RESPONSE);
}

int CPop3Session::ProcessLAST(char* buf, int len)
{
	char resp[25];
	
	try{

	if(m_nState!=POP3_STATE_TRANSACTION)
	{
		return SendResponse(POP3_DEFAULT_NEGATIVE_RESPONSE);
	}

	TRACE("ProcessLAST\n");


	sprintf(resp, "+OK %d\r\n",m_nLastMsg);

} catch (...) {CPop3Thread pop3; pop3.ErrorLog("Error in CPop3Session::ProcessLAST");}

	return SendResponse(resp);
}

int CPop3Session::ProcessRSET(char* buf, int len)
{try{

	char cText[1000];

	TRACE("ProcessRSET\n"); Log("Start ProcessRSET","ProcessRSET1");

	if(m_nState!=POP3_STATE_TRANSACTION)
	{
		return SendResponse(POP3_DEFAULT_NEGATIVE_RESPONSE);
	}

	for(int i=0; i < m_nTotalMailCount; i++)
	{
		m_pPop3MessageList[i].Reset();
		sprintf(cText,"ProcessRSET: Message %d: %ld %s\n",i+1,m_pPop3MessageList[i].GetSize(), m_pPop3MessageList[i].GetPath());
		TRACE(cText); Log(cText,"ProcessRSET2");
	}

} catch (...) {CPop3Thread pop3; pop3.ErrorLog("Error in CPop3Session::ProcessRSET");}

	return SendResponse(POP3_DEFAULT_NEGATIVE_RESPONSE);
}

void CPop3Session::UpdateMails(void)
{try{

	char cText[1000];

	sprintf(cText,"Start UpdateMails");TRACE(cText); Log(cText,"UpdateMails");



	TRACE("Updating mails\n");

	if(m_nState!=POP3_STATE_UPDATE)
	{
		sprintf(cText,"Called update but state is not POP3_STATE_UPDATE (%d)\n",POP3_STATE_UPDATE);TRACE(cText); Log(cText,"UpdateMails");	
		return;
	}

	sprintf(cText,"Before deleting UpdateMails");TRACE(cText); Log(cText,"UpdateMails");

	for(int i=0; i < m_nTotalMailCount; i++)
	{
		if(m_pPop3MessageList[i].GetStatus()& POP3_MSG_STATUS_DELETED)
		{
			sprintf(cText,"UpdateMails: Delete file %s\n",m_pPop3MessageList[i].GetPath());
			TRACE(cText); Log(cText,"UpdateMails");

			DeleteFile(m_pPop3MessageList[i].GetPath());
		}
		else
		{
			sprintf(cText,"File not deleted: %s Status = %d \n",m_pPop3MessageList[i].GetPath(), m_pPop3MessageList[i].GetStatus());
			TRACE(cText); Log(cText,"UpdateMails");
		}

		sprintf(cText,"Processed Message was %d: %ld %s\n",i+1,m_pPop3MessageList[i].GetSize(), m_pPop3MessageList[i].GetPath());TRACE(cText); Log(cText,"UpdateMails");
	}

	sprintf(cText,"End UpdateMails");TRACE(cText); Log(cText,"UpdateMails");

} catch (...) {CPop3Thread pop3; pop3.ErrorLog("Error in CPop3Session::UpdateMails");}
}

bool CPop3Session::SetHomePath(char *lpPath)
{try{
	
	strcpy(m_szUserHome,lpPath);

} catch (...) {CPop3Thread pop3; pop3.ErrorLog("Error in CPop3Session::SetHomePath");}
	
	return true;
}

int CPop3Session::SendMessageFile(char* szFilePath)
{
	DWORD lenRead, len;

try{

	HANDLE findH, fileH;
	WIN32_FIND_DATA findData;
	char *buf;

	fileH = CreateFile(szFilePath, GENERIC_READ, FILE_SHARE_READ, NULL,
		OPEN_EXISTING, FILE_ATTRIBUTE_NORMAL, NULL);
	if (fileH == INVALID_HANDLE_VALUE)
		return NULL;
	findH = FindFirstFile(szFilePath, &findData);
	if (findH == INVALID_HANDLE_VALUE)
	{
		CloseHandle(fileH);
		return NULL;
	}
	len = findData.nFileSizeLow;
	buf = (char *)malloc(len+5);
	if (buf != NULL)
	{
		ReadFile(fileH, buf, len, &lenRead, NULL);
		if (len != lenRead)
		{
			free(buf);
			buf = NULL;
			TRACE("Read error (len!=readlen) file %s\n",szFilePath);
			return 0;
		}
	}
	else
	{
		TRACE("Can not open file %s\n",szFilePath);
		return 0;
	}

	FindClose(findH);
	CloseHandle(fileH);


	// Remove the last 3 chars of data buffer, no _._ at the end !!! Nov 16.2010
	if(strstr(buf,SMTP_DATA_TERMINATOR))
	{
		len = len - 3;	
	}

	TRACE("Sending: %s\n",szFilePath);
	send(m_socConnection,buf,len,0);

	if(buf) {delete buf;}

} catch (...) {CPop3Thread pop3; pop3.ErrorLog("Error in CPop3Session::SendMessageFile");}

	return len;
}

int CPop3Session::ProcessRPOP(char* buf, int len)
{try{

	TRACE("ProcessRPOP\n");

} catch (...) {CPop3Thread pop3; pop3.ErrorLog("Error in CPop3Session::ProcessRPOP");}

	return SendResponse(POP3_DEFAULT_NEGATIVE_RESPONSE);
}

int CPop3Session::ProcessTOP(char* buf, int len)
{try{

	TRACE("ProcessTOP\n");

} catch (...) {CPop3Thread pop3; pop3.ErrorLog("Error in CPop3Session::ProcessTOP");}

	return SendResponse(POP3_DEFAULT_NEGATIVE_RESPONSE);
}

bool CPop3Session::LockMailDrop(void)
{try{

	TRACE("Locking maildrop\n");

	WIN32_FIND_DATA FileData; 
	HANDLE hSearch; 

	BOOL bFinished = FALSE; 

	m_dwTotalMailSize=0;
	m_nTotalMailCount=0;

	char szSearchPath[MAX_PATH];

	sprintf(szSearchPath, "%s\\%s\\%s",m_szUserHome,POP3_USER_MAIL_DIRECTORY_NAME,POP3_MSG_SEARCH_WILDCARD);

	TRACE("Search Path: %s", szSearchPath);
	hSearch = FindFirstFile(szSearchPath, &FileData); 

	if (hSearch == INVALID_HANDLE_VALUE) 
	{ 
		TRACE("No mail message files found.\n"); 
		return true;
	} 

	CPop3Message *pHead=NULL, *pNewMsg=NULL; char cText[1000]; int nCount = 0;

	while (true) 
	{
		DWORD dwSize=FileData.nFileSizeLow; nCount++;

		m_nTotalMailCount++;
		m_dwTotalMailSize+=FileData.nFileSizeLow;
		char msgPath[300];

		sprintf(msgPath,"%s\\%s\\%s",m_szUserHome,POP3_USER_MAIL_DIRECTORY_NAME,FileData.cFileName);

		pNewMsg = new CPop3Message(dwSize,POP3_MSG_STATUS_NEW,msgPath);

		pNewMsg->m_pNextMessage = pHead;
		pHead = pNewMsg;

		sprintf(cText,"Message %d: %s\n",m_nTotalMailCount, FileData.cFileName);
		TRACE(cText); Log(cText,"LockMailDrop0");

		if (!FindNextFile(hSearch, &FileData)) 
			break;

		if(nCount>1000)
			break;
	}

	FindClose(hSearch);

	sprintf(cText,"TotalMailCount %d TotalMailSize %d\n", m_nTotalMailCount, m_dwTotalMailSize); TRACE(cText); Log(cText,"LockMailDrop1");

	if(m_nTotalMailCount)
	{
		m_pPop3MessageList=new CPop3Message[m_nTotalMailCount];
		if(!m_pPop3MessageList) return false;

		for(int i=0; i < m_nTotalMailCount; i++)
		{
			pNewMsg=pHead;
			sprintf(cText,"SetParams[%d/%d] %d | %d | %s\n",i,m_nTotalMailCount,pNewMsg->GetSize(), pNewMsg->GetStatus(), pNewMsg->GetPath()); Log(cText,"LockMailDrop2");
			m_pPop3MessageList[i].SetParams(pNewMsg->GetSize(), pNewMsg->GetStatus(), pNewMsg->GetPath());
			pHead=pHead->m_pNextMessage;		
		}

		sprintf(cText,"Total %d messages of %ld octates found\n",m_nTotalMailCount, m_dwTotalMailSize);

		TRACE(cText); Log(cText,"LockMailDrop3");	


		for(i=0; i < m_nTotalMailCount; i++)
		{
			sprintf(cText,"Message %d: %ld %s\n",i+1,m_pPop3MessageList[i].GetSize(), m_pPop3MessageList[i].GetPath());
			TRACE(cText); Log(cText,"LockMailDrop4");
		}
	}

	if(pNewMsg) {delete pNewMsg;}
	if(pHead) {delete pHead;}

} catch (...) {CPop3Thread pop3; pop3.ErrorLog("Error in CPop3Session::MailDrop");}

	return true;
}