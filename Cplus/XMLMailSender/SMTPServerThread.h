#ifndef _MAIL_SESSION_INCLUDED_
#define _MAIL_SESSION_INCLUDED_

#define SMTP_APP_TITLE "Send Mail"
#define SMTP_APP_VERSION ""

// SMTP Defines
#define SMTP_PORT 25

#define SMTP_DATA_TERMINATOR "\r\n.\r\n"

#define SMTP_STATUS_INIT 1
#define SMTP_STATUS_HELO 2
#define SMTP_STATUS_DATA 16
#define SMTP_STATUS_DATA_END 32

#define SMTP_CMD_HELO "HELO"
#define SMTP_CMD_EHLO "EHLO"
#define SMTP_CMD_MAIL "MAIL"
#define SMTP_CMD_RCPT "RCPT"
#define SMTP_CMD_DATA "DATA"
#define SMTP_CMD_RSET "RSET"
#define SMTP_CMD_NOOP "NOOP"
#define SMTP_CMD_QUIT "QUIT"

#define SMTP_CMD_VRFY "VRFY"
#define SMTP_CMD_EXPN "EXPN"

// SMTP (RFC 821) DEFINED VALUES
#define MAX_USER_LENGTH 64 
#define MAX_DOMAIN_LENGTH 64
#define MAX_ADDRESS_LENGTH 256
#define MAX_CMD_LENGTH 512
#define MAX_RCPT_ALLOWED 5


class CSMTPThread
{
public:
	CSMTPThread();
	virtual ~CSMTPThread();
	int StartSMTPServer();
	void StartSMTPServerThread();

	char cThreadIP[20];

	void Log(char *cText);
	void ErrorLog(char *cText);

private:

	void AcceptSmtpConnections(SOCKET server_soc);
};


class CMailAddress
{
	char m_szUser[MAX_USER_LENGTH+5];
	char m_szDomain[MAX_DOMAIN_LENGTH+5];

	char m_szAddress[MAX_ADDRESS_LENGTH+2];
	char m_szMBoxPath[300];

public:

	CMailAddress(char szAddress[]="")
	{
		strcpy(m_szAddress,szAddress);
	}
	
	void SetMBoxPath(char *path)
	{
		strcpy(m_szMBoxPath, path);
		printf("MBox=%s\n",m_szMBoxPath);
	}

	bool SetAddress(char szAddress[])
	{
		char *domain=m_szDomain;
		
		if(!AddressValid(szAddress)) return false;
		strcpy(m_szAddress,szAddress);
		
		domain=strchr(m_szAddress,'@');
		domain+=1;
		strcpy(m_szDomain,domain);

		memset(m_szUser,0,sizeof(m_szUser));
		strncpy(m_szUser,m_szAddress,strlen(m_szAddress)-strlen(domain)-1);
		return true;
	}

	char* GetMBoxPath()
	{
		return m_szMBoxPath;
	}

	char* GetAddress(){return m_szAddress;}
	char* GetDomain(){return m_szDomain;}
	char* GetUser(){return m_szUser;}

	static bool AddressValid(char *szAddress)
	{
		return (strlen(szAddress)>2 && strchr(szAddress,'@'));

	}
};


struct ThreadParamater {

	void* nSocket;
	int nSessionNo;
	char cSessionIP[20];
};




class CMailSession
{
public:

	CString GetDomainPathUsers();

	int IsReturnMail(char * filename);

	void GetSmtpMxHost(char * strDomain, char * strMailHost, char * cIP);

	int CMailSession::CheckAuthPlainLogin(char * cEncodedString);
	int ProcessAUTHPLAIN(char *buf, int len);


	int m_nAuthLoginStatus;
	int ProcessAUTHLOGIN(char *buf, int len);
	char cAuthUser[200], cAuthPass[200];

	int ProcessAuthReadUser(char *buf, int len);
	int ProcessAuthReadPass(char *buf, int len);


	char cSessionIP[20];
	int nSessionNo;


	void AddNewAccount(char * filename);
	bool CreateNewAccount(char * username, char * password);
	bool DeleteAccount(char * username, char * password);

	int m_nAddNewAccountStatus;
	char newAccountUser[100];


	int m_nIsOutboundEmail;
	int m_nDeliveryDate;


	void GetMD5(char * cMD5Source);

	CString ExecuteExternalFile(CString csExeName, CString csArguments); // http://www.codeproject.com/KB/cpp/9505Yamaha_1.aspx

	char m_szFileName[MAX_PATH+1];

	void GetRFCTime(TCHAR * szDateOut);


	void ErrorLog(char *cText);

	void *m_pszData;
	int data_len;

	HANDLE m_hMsgFile;
	int m_nFileOpenClosed;


	unsigned int m_nStatus;
	int m_nRcptCount, m_nHeloCount, m_nRsetCount;
	int m_nEmptyDataBuffers;
	int m_nDataCount;
	int m_nCommandNot502;
	int m_nUserNotLocal551;
	int m_nAuthSuccessful;

	int m_nNullsender;


	char cDataBuffer[5010]; int m_nDataBufferPos;


	
	
	CMailAddress m_FromAddress, m_ToAddress[MAX_RCPT_ALLOWED+1];

	SOCKET m_socConnection;

	CMailSession(SOCKET client_soc,char * cIP,int nSessionNumber)
	{
		strcpy(cSessionIP,cIP);
		strcpy(newAccountUser,"");

		memset(cDataBuffer,0,5010); m_nDataBufferPos = 0;

		nSessionNo = nSessionNumber;

		m_nStatus=SMTP_STATUS_INIT;
		m_socConnection=client_soc;
		m_pszData=NULL;
		data_len=0;
		m_nRcptCount=0;
		m_nEmptyDataBuffers = 0;
		m_nHeloCount = 0;
		m_nDataCount = 0;
		m_nAuthLoginStatus = 0;
		m_nRsetCount = 0;
		m_nCommandNot502 = 0;
		m_nUserNotLocal551 = 0;
		m_nFileOpenClosed=0;
		m_nAddNewAccountStatus=0;
		m_nNullsender = 0;
		m_nIsOutboundEmail = 0;
		m_nDeliveryDate = 0;


		

	}



private:
	int ProcessHELO(char *buf, int len);
	int ProcessRCPT(char *buf, int len);
	int ProcessMAIL(char *buf, int len);
	int ProcessRSET(char *buf, int len);
	int ProcessNOOP(char *buf, int len);
	int ProcessQUIT(char *buf, int len);
	int ProcessDATA(char *buf, int len);
	int ProcessNotImplemented(bool bParam=false);

public:

	int ProcessCMD(char *buf, int len);
	int SendResponse(int nResponseType);
	int ProcessDATAEnd(void);
	bool CreateNewMessage(char *m_szFileNameIn);
};


#endif //_MAIL_SESSION_INCLUDED_