// Pop3Thread.h: Schnittstelle für die Klasse CPop3Thread.
//
//////////////////////////////////////////////////////////////////////

#if !defined(AFX_POP3THREAD_H__0FF9E037_8B5D_42DC_9E19_89B15EA2F7F7__INCLUDED_)
#define AFX_POP3THREAD_H__0FF9E037_8B5D_42DC_9E19_89B15EA2F7F7__INCLUDED_

#if _MSC_VER > 1000
#pragma once
#endif // _MSC_VER > 1000

#define APP_TITLE "Pop3 Client"
#define APP_VERSION ""

#ifndef MAX_PATH
#define MAX_PATH 256
#endif


#define SMTP_DATA_TERMINATOR "\r\n.\r\n"


#define POP3_USER_MAIL_DIRECTORY_NAME "mbox"
#define POP3_MSG_SEARCH_WILDCARD "*.eml"

#define POP3_PORT 110
#define POP3_STATE_AUTHORIZATION 1
#define POP3_STATE_TRANSACTION 2
#define POP3_STATE_UPDATE 4
#define POP3_DEFAULT_NEGATIVE_RESPONSE 0
#define POP3_DEFAULT_AFFERMATIVE_RESPONSE 1
#define POP3_WELCOME_RESPONSE 2
#define POP3_STAT_RESPONSE 16
#define POP3_MSG_STATUS_UNDEFINED 0
#define POP3_MSG_STATUS_NEW 1
#define POP3_MSG_STATUS_READ 2
#define POP3_MSG_STATUS_REPLIED 4
#define POP3_MSG_STATUS_DELETED 8
#define POP3_MSG_STATUS_CUSTOM 16

class CPop3Thread : public CObject  
{
public:
	CPop3Thread();
	virtual ~CPop3Thread();

	void ErrorLog(char *cText);
	void SplitPop3Log(char *cFilename);


	void Log(char * cText);
	void AcceptConnections(SOCKET server_soc);
	void StartPOP3Thread();
	void StartPOP3();
};


struct Pop3ThreadParameter {

	void* nSocket;
	char cSessionIP[20];
};




class CPop3Message
{
	char m_szMessagePath[MAX_PATH];
	int m_nStatus;
	DWORD m_dwSize;

public:
	CPop3Message *m_pNextMessage;


	CPop3Message(int nStatus=POP3_MSG_STATUS_UNDEFINED,DWORD nSize=0, char *szMessagepath="")
	{
		m_nStatus=nStatus;
		m_dwSize=nSize;
		strcpy(m_szMessagePath,szMessagepath);
		m_pNextMessage=NULL;
	}

	void SetParams(int nStatus=POP3_MSG_STATUS_UNDEFINED,DWORD nSize=0, char *szMessagepath="")
	{
		m_nStatus=nStatus;
		m_dwSize=nSize;
		strcpy(m_szMessagePath,szMessagepath);
	}

	void SetParams(CPop3Message *pMsg)
	{
		m_nStatus=pMsg->GetStatus();
		m_dwSize=pMsg->GetSize();
		strcpy(m_szMessagePath,pMsg->GetPath());
	}

	void Delete(){m_nStatus|=POP3_MSG_STATUS_DELETED;}

	void Reset(){m_nStatus&= ~POP3_MSG_STATUS_DELETED;}
	DWORD GetSize(){return m_dwSize;}
	int GetStatus(){return m_nStatus;}
	char *GetPath(){return m_szMessagePath;}
};


class CPop3Session
{

	int m_nProcessCount;

	int Log(char *cText, char * cCallingProcedure);


	CPop3Message *m_pPop3MessageHead, *m_pPop3MessageList;

	unsigned int m_nState;
	unsigned int m_nLastMsg;
	char m_szUserHome[MAX_PATH];
	char m_szUserName[MAX_PATH];
	char m_szPassword[MAX_PATH];
	int m_nTotalMailCount, m_dwTotalMailSize;

public:
	SOCKET m_socConnection;
	char cSessionIP[25];

	CPop3Session(SOCKET client_soc, char * cIP);
	virtual ~CPop3Session(void);

	int ProcessCMD(char *buf, int len);
	int SendResponse(int nResponseType, char *msg="");
	int SendResponse(char *buf);

protected:
	int ProcessUSER(char* buf, int len);
	int ProcessPASS(char* buf, int len);
	int ProcessQUIT(char* buf, int len);
	int ProcessRETR(char* buf, int len);
	int ProcessUIDL(char* buf, int len);
	int ProcessDELE(char* buf, int len);
	int ProcessNOOP(char* buf, int len);
	int ProcessLAST(char* buf, int len);
	int ProcessRSET(char* buf, int len);
	int ProcessSTAT(char* buf, int len);
	int ProcessLIST(char* buf, int len);
	bool Login(char* szName, char* szPassword);

public:
	bool LockMailDrop(void);
	void UpdateMails(void);
	bool SetHomePath(char *szUserName);
	int SendMessageFile(char* szFilePath);
	int ProcessRPOP(char* buf, int len);
	int ProcessTOP(char* buf, int len);
};

#endif // !defined(AFX_POP3THREAD_H__0FF9E037_8B5D_42DC_9E19_89B15EA2F7F7__INCLUDED_)
