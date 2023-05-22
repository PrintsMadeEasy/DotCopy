// EmailSender.cpp: Implementierung der Klasse EmailSender.
//
//////////////////////////////////////////////////////////////////////

#include "stdafx.h"
#include "EmailSender.h"
#include "SMTPMine.h"
#include <stdio.h>
#include <direct.h>

#include "md5.h"

#include "SMTPServerThread.h"

#include "BlowFish.h"
#include "DKIM.h"


#include "GZipHelper.h"


#ifdef _DEBUG
#undef THIS_FILE
static char THIS_FILE[]=__FILE__;
#define new DEBUG_NEW
#endif

//////////////////////////////////////////////////////////////////////
// Konstruktion/Destruktion
//////////////////////////////////////////////////////////////////////


extern char g_szConfigDomain[128];
// extern char g_szConfigSenderEmail[128];
extern char g_szConfigXMLHost[128];
extern char g_szConfigXMLFile[128];
extern char g_szConfigFailMailHost[128];
extern char g_szConfigFailMailScript[128];
// extern char g_szConfigSenderName[128];
extern char g_szConfigMailHost[128];
extern char g_szConfigMailUser[128];
extern char g_szConfigMailPass[128];
extern char g_szConfigFilePath[128];
extern int  g_nConfigSendType;
extern int  g_nConfigSubmitMailMail;
extern int g_nConfigRunLog;


extern char g_szDKIMSelector[128];
extern int g_nDKIMOn;
extern int g_nConfigRunSMTPServer;


EmailSender::EmailSender()
{
	sFilePath = g_szConfigFilePath;
}


EmailSender::~EmailSender()
{

}

void SenderLog(char *cText)
{
	if(g_nConfigRunLog==1)
	{
		FILE * pFile;
		if(pFile = fopen (CString(g_szConfigFilePath)+"sentemailslog.txt","a"))
		{
			fputs (CTime::GetCurrentTime().Format("20%y/%m/%d  %H:%M:%S ")+CString(cText),pFile); 
			fclose (pFile);

			TRACE(cText);
		}
	}
}


void EmailSender::ErrorLog(char *cText)
{
	FILE * pFile;
	if(pFile = fopen (CString(g_szConfigFilePath)+"ErrorLog.txt","a"))
	{
		fputs (CTime::GetCurrentTime().Format("20%y/%m/%d  %H:%M:%S ")+CString(cText)+CString("\n"),pFile); 
		fclose (pFile);

		TRACE(cText);
	}
}


CString EmailSender::GetDkimHeader(CString sHeader, CString sBody, CString sSubject, CString sDomain, CString sSelector)
{
	// dkimswitch
	
	if(1==1)
	{

	CDKIM dkim;
	return dkim.AddDkimToHeaders(sSelector,sDomain,sHeader,sBody);
	
	}
	else
	{
	

	CString dest="/test/vc-dkim-test.php";
	CString HostName="www.asynx-planetarium.com";

	CString Base64Response = "";

	CBase64 base64; 
	
	base64.Encode(sSubject);	CString sDKIM_SubjectBase64 = base64.EncodedMessage();
	base64.Encode(sBody);		CString sDKIM_BodyBase64	= base64.EncodedMessage();
	base64.Encode(sHeader);		CString sDKIM_HeaderBase64	= base64.EncodedMessage();
	base64.Encode(sDomain);		CString sDKIM_DomainBase64	= base64.EncodedMessage();
	base64.Encode(sSelector);	CString sDKIM_SelectorBase64 = base64.EncodedMessage();

	CString Data="s="+sDKIM_SelectorBase64+"&d="+sDKIM_DomainBase64+"&header="+sDKIM_HeaderBase64+"&subject="+sDKIM_SubjectBase64+"&body="+sDKIM_BodyBase64;

	char cContentLength[100];
	
	CInternetSession iSession("DKIM-OUTSOURCED");
	CString verb="POST";
	CString retHeader;

	CString header;
	header="Content-Type: application/x-www-form-urlencoded\r\n";
	sprintf(cContentLength,"Content-Length: %d \r\n",Data.GetLength()); header+=cContentLength;
	header+="Host: ";
	header+=HostName;
	header+="\r\n";

	CHttpConnection *hSession=NULL; CHttpFile *hFile=NULL;

	hSession=iSession.GetHttpConnection(HostName, 0, 80, 0, 0);

	if (hSession)
	{
		hFile=hSession->OpenRequest(verb, dest, 0, 1, 0, "HTTP/1.1", INTERNET_FLAG_RELOAD|INTERNET_FLAG_NO_CACHE_WRITE );
		if (hFile)
		{
			hFile->AddRequestHeaders(header);
			if (Data.GetLength()!=0)
			{
				hFile->SendRequestEx(Data.GetLength());
				hFile->WriteString(Data);
				hFile->EndRequest();
			}
			else
			{
				hFile->SendRequest();
			}

			hFile->QueryInfo(HTTP_QUERY_STATUS_CODE , retHeader, 0);
			
			if (retHeader=="200")
			{
				CString buff;

				while (hFile->ReadString(buff))
				{
					Base64Response+=buff;
					Base64Response+="\n";
					buff.Empty();
				}
			}
		}
	}
	if (hFile)
	{
		hFile->Close();
		delete(hFile);
	}
	if (hSession)
	{
		hSession->Close();
		delete (hSession);
	}

	base64.Decode(Base64Response);
	return base64.DecodedMessage();
	
	}
}



DWORD WINAPI SendEmailToTheServerThread(LPVOID pParam)
{
try{

	EmailMessage * emailmsg =(EmailMessage*) pParam; EmailMessage emailsave;

	strcpy(emailsave.jobid,emailmsg->jobid);
	strcpy(emailsave.trackid,emailmsg->trackid);

	strcpy(emailsave.senderemail,emailmsg->senderemail);
	strcpy(emailsave.namereceiver, emailmsg->namereceiver);
	strcpy(emailsave.sendername,emailmsg->sendername);
	strcpy(emailsave.mailhost,emailmsg->mailhost);
	strcpy(emailsave.mailuser,emailmsg->mailuser);
	strcpy(emailsave.mailpw,emailmsg->mailpw);
	strcpy(emailsave.emailreceiver,emailmsg->emailreceiver);


/// Test 
//	strcpy(emailsave.emailreceiver,"christian_nuesch@yahoo.com");
///	strcpy(emailsave.emailreceiver,"christiannuesch@aol.com");	
///
	
	strcpy(emailsave.subject,emailmsg->subject);
	emailsave.bPlain=emailmsg->bPlain;

	CString sTextBody = emailmsg->textbody;

	char cTextPlainHTML[20];
	if(emailsave.bPlain==TRUE) { strcpy(cTextPlainHTML,"");} else {strcpy(cTextPlainHTML,"text/plain");}

	WSADATA wsa;
	WSAStartup(MAKEWORD(2,0),&wsa);

	CSmtp mail;
	CSmtpMessage msg;
	CSmtpMessageBody body(NULL,cTextPlainHTML,"us-ascii",encodeGuess);

	msg.Subject = _T(emailsave.subject);

	msg.Sender.Name    = _T(emailsave.sendername);
	msg.Sender.Address = _T(emailsave.senderemail);

	msg.Recipient.Name = _T(emailsave.namereceiver);
	msg.Recipient.Address = _T(emailsave.emailreceiver);

	int nType = 0;

	if(emailsave.bPlain==FALSE)
	{
		nType = 0;
		body = _T(sTextBody);
	}
	else
	{
		nType = 1;

		int nPos = sTextBody.Find("--=_",0);
		CString sBoundry = sTextBody.Mid(nPos+4,32);

		CString sBoundryNew; sBoundryNew.Format("%s%s%%s_93065_91362_%8.8X",emailsave.namereceiver,emailsave.senderemail,emailsave.subject,GetTickCount());
	
		unsigned char pBuf[200];
		unsigned long uRead = 0;
    
		uRead=sBoundryNew.GetLength();
		memcpy(pBuf,sBoundryNew,uRead);
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

		sBoundryNew = sMD5; sBoundryNew.MakeLower();

		sTextBody.Replace(sBoundry,sBoundryNew);

		//////////

		


		SYSTEMTIME Timestamp; // Timestamp of the message

		TIME_ZONE_INFORMATION tzi;
		DWORD dwRet;
		long Offset; 
		int GMTOffset; // GMT timezone offset value

		// Get local time and timezone offset 
		GetLocalTime(&Timestamp); 
		GMTOffset = 0; 
		dwRet = GetTimeZoneInformation(&tzi); 
		Offset = tzi.Bias; 
		if (dwRet == TIME_ZONE_ID_STANDARD) Offset += tzi.StandardBias; 
		if (dwRet == TIME_ZONE_ID_DAYLIGHT) Offset += tzi.DaylightBias; 
		GMTOffset = -((Offset / 60) * 100 + (Offset % 60)); 

		TCHAR szTime[64];
		TCHAR szDate[64];
		TCHAR szDateOut[1024];
		GetDateFormat(MAKELCID(LANG_ENGLISH, SORT_DEFAULT),0,&Timestamp,_T("ddd, d MMM yyyy"),szDate,64); 
		GetTimeFormat(MAKELCID(LANG_ENGLISH, SORT_DEFAULT),0,&Timestamp,_T("H:mm:ss"),szTime,64); 
		wsprintf(szDateOut,_T("%s %s %c%4.4d"),szDate,szTime,(GMTOffset>0)?'+':'-',abs(GMTOffset)); 

		/////////

		char cFrom[200]; char cGF[2]; cGF[0]=34; cGF[1]=0;

		
		strcpy(cFrom,emailsave.senderemail);

		if(strcmp(emailsave.sendername,"")!=0)
		{
			sprintf(cFrom,"%s <%s>",emailsave.sendername,emailsave.senderemail);
		}

		// Turn off DKIM if no Selector defined
		if(strlen(g_szDKIMSelector)==0)
		{
			g_nDKIMOn = 0;
		}

		CString sDKIMPlaceHolder = "";
		if(g_nDKIMOn==1)
		{
			sDKIMPlaceHolder += _T("[[DKIM-PLACEHOLDER]]\r\n");
		}

		CString sHeader = _T("Date: "+CString(szDateOut) + "\r\nSubject: "+CString(emailsave.subject)+"\r\nTo: "+CString(emailsave.emailreceiver)+"\r\nFrom: "+CString(cFrom)+"\r\n" + sDKIMPlaceHolder + "MIME-Version: 1.0\r\n");
		
		sHeader += _T("Content-Type: multipart/related;\r\n	boundary="+CString(cGF)+"=_"+sBoundryNew+CString(cGF)+"\r\n\r\n");


		if(sTextBody.Find("Content-Transfer-Encoding: base64",0)==-1)
		{
			sTextBody.Replace("multipart/related;","multipart/alternative;");
		}

		if(sHeader.Find("Content-Transfer-Encoding: base64",0)==-1)
		{
			sHeader.Replace("multipart/related;","multipart/alternative;");
		}

		if(g_nDKIMOn==1)
		{
			EmailSender es; 
			sHeader.Replace("[[DKIM-PLACEHOLDER]]", es.GetDkimHeader(sHeader, sTextBody, emailsave.subject, g_szConfigDomain ,g_szDKIMSelector));						
		}

		CString sBody = sHeader + sTextBody;
		
		CString sDate = CTime::GetCurrentTime().Format("20%y-%m-%d");
		CString sFilename; sFilename.Format("%semailsentlog\\%s\\%s_beforeSending.txt",g_szConfigFilePath,sDate,emailsave.emailreceiver);
		CFileException e;
		CFile* pFileW = new CFile();
		if(pFileW->Open(sFilename, CFile::modeWrite | CFile::modeCreate, &e)) 
		{
			pFileW->Write(sBody,sBody.GetLength());
			pFileW->Close();
		}   delete pFileW;


		body  = sBody;
	}

	msg.Message.Add(body);

	mail.m_strUser = _T(emailsave.mailuser);
	mail.m_strPass = _T(emailsave.mailpw);
	
	if(g_nConfigSendType == 1)
	{
		strcpy(emailsave.mailhost, emailsave.emailreceiver);
		mail.m_strUser = _T("");
		mail.m_strPass = _T("");
	}

	int nError = 599; // If not sent => 599

	if (mail.SMTPConnect(_T(emailsave.mailhost)))
	{
		nError = 0;
		nError = mail.SendMessage(msg,nType);
		Sleep(300);
		mail.Close();
	}


	{char cText[3000]; sprintf(cText,"%d %s\n",nError,emailsave.emailreceiver); SenderLog(cText);}


	if(nError>500) 
	{
		if(g_nConfigSubmitMailMail==1)
		{
			// This is just to call the script that counts the errors

			char cURL[500]; sprintf(cURL,"%s%s?email=%s&error=%d&id=%s",g_szConfigFailMailHost,g_szConfigFailMailScript,emailsave.emailreceiver,nError,emailsave.trackid);
		
			char buffer[1000]; memset(buffer,0,1000);
			CInternetSession session; UINT nBytesRead = 0; CStdioFile* pFile1 = NULL; 

			try 
			{
				pFile1 = session.OpenURL(cURL, 0, INTERNET_FLAG_TRANSFER_BINARY | INTERNET_FLAG_KEEP_CONNECTION | INTERNET_FLAG_DONT_CACHE);
				nBytesRead = pFile1->ReadHuge(buffer, 1000);
			}
			catch(CInternetException* e) 
			{
				e->Delete();
			} 

			if(pFile1) {delete pFile1;}
		}
	}

	WSACleanup();

	} catch (...) {EmailSender es; es.ErrorLog("Error in EmailSender::SendEmailToTheServerThread");}

	return 0;

}


CString EmailSender::SendSingleEmail(CString sEmail, CString sSubject, CString sBody)
{
	EmailMessage emailmsg;

	strcpy(emailmsg.emailreceiver,sEmail);
	strcpy(emailmsg.namereceiver,"Testusername");
	strcpy(emailmsg.textbody, sBody);
	strcpy(emailmsg.subject, sSubject);

	strcpy(emailmsg.sendername,"Christian Nuesch");
	strcpy(emailmsg.senderemail,"christian@asynx.com");
	
	strcpy(emailmsg.mailhost,g_szConfigMailHost);
	strcpy(emailmsg.mailuser,g_szConfigMailUser);	
	strcpy(emailmsg.mailpw,g_szConfigMailPass);

	emailmsg.bPlain=TRUE; 

	HANDLE hEmailThread; DWORD EmailThreadID; 
	
	hEmailThread = CreateThread ( NULL, 0, SendEmailToTheServerThread, (LPVOID) &emailmsg, 0, &EmailThreadID);
	
	Sleep(80);

	return "";
}


void EmailSender::GetRFCTime(TCHAR * szDateOut)
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

} catch (...) {ErrorLog("EmailSender::GetRFCTime");}}	



void EmailSender::GetHeaderInfo(char * sender, char * filebuffer, int nBufferLen, int &nDeliveryDateSendOut, CStringArray &sAReceiverEmail, CStringArray &sAReceiverName, CString &sReceiverEmail, CString &sReceiverName)
{try{

	int nLineNr=0; char cHeaderLines[62][3000]; char cEmailHeader[10000];

	try {

		if(nBufferLen>5000) {nBufferLen=5000;} memcpy(cEmailHeader,filebuffer,nBufferLen);
		int nSavePos=-1; char cLine[3000];

		for(WORD x=0; x<nBufferLen; x++)	
		{
			if((filebuffer[x]==10) || (x==nBufferLen-1) )
			{
				memcpy(cLine,filebuffer+nSavePos+1,x-nSavePos-1);
				cLine[x-nSavePos-2] = 0;
				strcpy(cHeaderLines[nLineNr],cLine);
				nLineNr++; if(nLineNr>=60) { x = nBufferLen+100;}
				nSavePos=x;
			}
		}

	} catch (...) {ErrorLog("Error in EmailSender::GetHeaderInfo 1");}

	

	try {

		// Search for keywords in HeaderLines. 
		int nDeliveryDateSendOut = 0; char *pDelivery; 
		for(int d=0; d<nLineNr; d++)
		{
			pDelivery = strstr(cHeaderLines[d], "Delivery-date: ");	
			if((pDelivery - cHeaderLines[d])>-1)
			{
				nDeliveryDateSendOut = 1;
			}
		}
		
	} catch (...) {ErrorLog("Error in EmailSender::GetHeaderInfo 2");}


	int nIsInTo = 0; CString sReceivers;

	try {

		for(int l=0; l<nLineNr; l++)
		{
			CString sLine = cHeaderLines[l];
			
			if(sLine.Find("To: ",0)>-1)
			{
				nIsInTo = 1;
				sReceivers = sLine.Mid(4,sLine.GetLength());
				TRACE("1Receivers*%s*\n",sReceivers);
			}
			else
			{
				if(nIsInTo==1)
				{	
					int bOK = 0;
					
					if(sLine.GetLength()>2)
					{
						if(sLine.Mid(0,1)==" ")
						{
							bOK = 1;
						}
					}

					if((sLine.Find("@",0)>-1) && (bOK==1))
					{
						sReceivers += sLine;

						TRACE("2Receivers*%s*\n",sReceivers);
					}
					else
					{
						nIsInTo = 0;
						l = nLineNr;
					}
				}
			}
		}
	} catch (...) {ErrorLog("Error in EmailSender::GetHeaderInfo 3");}


	
	try {
		
		sReceivers = sReceivers + ",";

		int nLastPos = 0;
		for(int s=0; s<sReceivers.GetLength(); s++)
		{
			if(sReceivers.Mid(s,1)==",")
			{
				CString sEmail = sReceivers.Mid(nLastPos,s-nLastPos); sEmail.TrimLeft(" "); sEmail.TrimRight(" ");
				
				int nPos3 = sEmail.Find("<",0);

				if(nPos3>-1)
				{
					sReceiverName = sEmail.Mid(0,nPos3);
					sReceiverEmail = sEmail.Mid(nPos3+1,200);
					sReceiverEmail.Replace(">","");
				}
				else
				{
					sReceiverEmail = sEmail;
				}

				sReceiverEmail.Replace(" ","");
				sReceiverName.Replace(" ","");

				if(sReceiverEmail.Find(CString(g_szConfigDomain),0)>-1)
				{
					// Don't send it to local email account !!!
				}
				else
				{
					sAReceiverEmail.Add(sReceiverEmail);
					sAReceiverName.Add(sReceiverName);
				}

				nLastPos = s+1;
			}
		}	
	} catch (...) {ErrorLog("Error in EmailSender::GetHeaderInfo 4");}



  	CString sBuffer = cEmailHeader; 
	char c10[2]; c10[0]=10; c10[1]=0;
	CString sFrom = "", sFromEmail, sFromName; 
	
	try {
		int nPos1 = sBuffer.Find("From: ",0);
		int nPos2 = sBuffer.Find(c10,nPos1);

		if((nPos1>-1) && (nPos2>-1))
		{
			sFrom = sBuffer.Mid(nPos1+6,nPos2-nPos1-7);
		
			int nPos3 = sFrom.Find("<",0);

			if(nPos3>-1)
			{
				sFromName = sFrom.Mid(0,nPos3);
				sFromName.TrimRight(" ");
				sFromEmail = sFrom.Mid(nPos3+1,200);
				sFromEmail.Replace(">","");
			}
			else
			{
				sFromEmail = sFrom;
			}
		}

		sFromEmail.Replace(" ","");
		sFromName.Replace(" ","");
		
  		strcpy(sender,sFromEmail);

	} catch (...) {ErrorLog("Error in EmailSender::GetHeaderInfo 5");}
	


} catch (...) {ErrorLog("Error in EmailSender::GetHeaderInfo");}}



void EmailSender::SendLocalEmails(char * cFilename)
{try{

	int nIncreaseDKIMBuffer = 0;
	if(g_nDKIMOn==1)
	{
		nIncreaseDKIMBuffer = 10000;
	}

	char cPathFile[200];

	sprintf(cPathFile,"%soutgoing\\%s",g_szConfigFilePath,cFilename);

	char * filebuffer = NULL; 
	DWORD nFileSize = 0;

	CFileException er; CFile* pFileRead = new CFile(); DWORD nLen=0;

	if(pFileRead->Open(cPathFile, CFile::modeRead , &er)) 
	{
		nLen = pFileRead->GetLength();
		filebuffer = new char[nLen+500+nIncreaseDKIMBuffer];
		memset(filebuffer,0,nLen+500+nIncreaseDKIMBuffer);
		nFileSize = pFileRead->ReadHuge(filebuffer,nLen);
		pFileRead->Close();
	}   delete pFileRead;
	

	remove(cPathFile);


	char sender[100]; int nDeliveryDateSendOut = 0;
	CStringArray sAReceiverEmail, sAReceiverName; CString sReceiverEmail, sReceiverName; 
	
	GetHeaderInfo(sender,filebuffer,nLen,nDeliveryDateSendOut,sAReceiverEmail,sAReceiverName,sReceiverEmail,sReceiverName);

	// DKIM
	if(g_nDKIMOn==1)
	{
		CString sFileBuffer = filebuffer;
		int nPos = sFileBuffer.Find("\r\n\r\n",0);
		
		if(nPos>-1)
		{
			CString sHeader = sFileBuffer.Mid(0,nPos)+"\r\n"; // "\r\n" so that the "To: " can be found
	
			int nSPos = sHeader.Find("Subject: ",0);

			if(nSPos>-1)
			{
				int nSPosEnd = sHeader.Find("\n",nSPos);

				if(nSPosEnd>-1)
				{
					CString sSubject = sHeader.Mid(nSPos+9, nSPosEnd-nSPos-10); 

					CString sBody = sFileBuffer.Mid(nPos+4,sFileBuffer.GetLength()-nPos-4); sBody.Replace("\r\n.\r\n","");

					char cDKIM[10000]; memset(cDKIM,0,10000);
					strcpy(cDKIM, GetDkimHeader(sHeader, sBody, sSubject ,g_szConfigDomain ,g_szDKIMSelector));
				
					if(strlen(cDKIM)>0)
					{
						char *ptr; 
						ptr = strstr(filebuffer, "\r\nMIME-Version: "); 
						if(!ptr) { ptr = strstr(filebuffer, "\r\nDate: "); }
						int nPosDKIM = ptr - filebuffer; 
						if(nPosDKIM<0) { nPosDKIM = 0;}
						
						int nDKIMLength = strlen(cDKIM);

						if(nDKIMLength>200)
						{
							memcpy(filebuffer + nPosDKIM +  nDKIMLength + 2 ,filebuffer + nPosDKIM, strlen(filebuffer) - nPosDKIM); 
							memcpy(filebuffer + nPosDKIM + 2, cDKIM, nDKIMLength);
							nLen = nLen + nDKIMLength;
						}
					}
					else
					{
						// Log ?
					}
				}
			}
		}
	}


	if(nDeliveryDateSendOut==0)
	{
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
		nLen = nLen + nInsertLength;
	}


	// Checking and fixing if Terminator is missing.
	char cTerminatorCheck[10];
	memcpy(cTerminatorCheck,filebuffer+nLen-5,6);

	if(strcmp(cTerminatorCheck,"\r\n.\r\n")!=0)
	{
		memcpy(filebuffer+nLen,"\r\n.\r\n",5);
		nLen = nLen + 5;
	}
	
	////////// Send it ////////////////

	WSADATA wsa;
	WSAStartup(MAKEWORD(2,0),&wsa);

	CSmtp mail; char receiver[100];

	for(int e=0; e<sAReceiverEmail.GetSize(); e++)
	{	
		strcpy(receiver,sAReceiverEmail.ElementAt(e)); 

		int nError = 599;

		if(mail.SMTPConnect(_T(receiver))) // Send direct, Host = Email !
		{
			nError = mail.SendDataRaw(filebuffer, sender, receiver);
			mail.Close();
		}

		if(nError==0) 
		{
			char cPathFileSent[200]; sprintf(cPathFileSent,"%soutgoing\\sent\\%s_%s",g_szConfigFilePath,receiver,cFilename);

			CFileException e;
			CFile* pFile = new CFile();
			if(pFile->Open(cPathFileSent, CFile::modeWrite | CFile::modeCreate, &e)) 
			{
				pFile->Write(filebuffer,nLen);
				pFile->Close();
			}	delete pFile;
		}
		else
		{
			char cPathFile1[200]; 
				
			sprintf(cPathFile1,"%soutgoing\\1\\%s_Sent_%s_%s",g_szConfigFilePath,receiver,CTime::GetCurrentTime().Format("20%y%m%d%H%M%S"),cFilename);
				
			CFileException e;
			CFile* pFile = new CFile();
			if(pFile->Open(cPathFile1, CFile::modeWrite | CFile::modeCreate, &e)) 
			{
				pFile->Write(filebuffer,nLen);
				pFile->Close();
			}	delete pFile;
		}
	}

	delete [] filebuffer;

	WSACleanup();

	Sleep(80);

} catch (...) {ErrorLog("Error in EmailSender::SendLocalEmails");}}


int EmailSender::SendAllLocalEmails()
{try {

	WIN32_FIND_DATA FindFileData;
	HANDLE hFind = INVALID_HANDLE_VALUE;

	char DirSpec[200];

	sprintf(DirSpec,"%soutgoing\\*.eml",g_szConfigFilePath);

	hFind = FindFirstFile(DirSpec, &FindFileData);

	if (hFind == INVALID_HANDLE_VALUE) 
	{
		return (-1);
	} 
	else 
	{
		SendLocalEmails(FindFileData.cFileName);

		while (FindNextFile(hFind, &FindFileData) != 0) 
		{
			SendLocalEmails(FindFileData.cFileName);  
		}

		FindClose(hFind);
	}

} catch (...) {ErrorLog("Error in EmailSender::SendAllLocalEmails");}

	return 1;
}


DWORD WINAPI SendAllLocalEmailsThread(LPVOID pParam)
{
	EmailSender es;
	es.SendAllLocalEmails();
	return 0;
}


void EmailSender::StartSendingAllLocalEmails()
{
	HANDLE hThread; DWORD ThreadID; 	
	hThread = CreateThread ( NULL, 0, SendAllLocalEmailsThread, NULL, 0, &ThreadID);
}



void EmailSender::SendUndeliverableLocalEmails(char * cFilename)
{try{


	bool bSendIt = false; CString sSentDate = "";
	try{
	// Check if it's already time to resend
	CString sFilename = cFilename;
	int nSentPos = sFilename.Find("_Sent_",0);
	if((nSentPos>-1) && (sFilename.GetLength()>nSentPos+20))
	{
		CString sTS = sFilename.Mid(nSentPos+6,14);
		
		CTime tSent = CTime(atoi(sTS.Mid(0,4)),atoi(sTS.Mid(4,2)),atoi(sTS.Mid(6,2)),atoi(sTS.Mid(8,2)),atoi(sTS.Mid(10,2)),atoi(sTS.Mid(12,2)));

		sSentDate = tSent.Format("20%y/%m/%d  %H:%M:%S");

		CTime tNow = CTime::GetCurrentTime();
		CTimeSpan tTime = tNow - tSent;
		int nSecondsSinceSent = tTime.GetTotalSeconds();

		if(nSecondsSinceSent>3600) // Try to resent after 60 Minutes
		{
			bSendIt = true;	
		}
	}
	} catch (...) {ErrorLog("Error in EmailSender::SendLocalEmails 1");}
	

	
	if(bSendIt==true)
	{
		char cPathFile[200];
		char * filebuffer = NULL; 
		DWORD nFileSize = 0;
		CFileException er; CFile* pFileRead = new CFile(); DWORD nLen=0;
		char sender[100]; int nDeliveryDateSendOut = 0;
		CStringArray sAReceiverEmail, sAReceiverName; CString sReceiverEmail, sReceiverName; 

		try {
		
			sprintf(cPathFile,"%soutgoing\\1\\%s",g_szConfigFilePath,cFilename);

			if(pFileRead->Open(cPathFile, CFile::modeRead , &er)) 
			{
				nLen = pFileRead->GetLength();
				filebuffer = new char[nLen];
				memset(filebuffer,0,nLen);
				nFileSize = pFileRead->ReadHuge(filebuffer,nLen);
				pFileRead->Close();
			}   delete pFileRead;
				
			remove(cPathFile);

		} catch (...) {ErrorLog("Error in EmailSender::SendLocalEmails 2");}
	


		GetHeaderInfo(sender,filebuffer,nLen,nDeliveryDateSendOut,sAReceiverEmail,sAReceiverName,sReceiverEmail,sReceiverName);

		////////// Send it ////////////////

		try {

		WSADATA wsa;
		WSAStartup(MAKEWORD(2,0),&wsa);

		CSmtp mail; char receiver[100];

		for(int e=0; e<sAReceiverEmail.GetSize(); e++)
		{	
			int nError = 599;

			try {

				strcpy(receiver,sAReceiverEmail.ElementAt(e)); 

				if(mail.SMTPConnect(_T(receiver))) // Send direct, Host = Email !
				{
					nError = mail.SendDataRaw(filebuffer, sender, receiver);
					mail.Close();
				}

			} catch (...) {ErrorLog("Error in EmailSender::SendLocalEmails 3.1");}

			if(nError==0) 
			{
				try {

					char cPathFileSent[200]; sprintf(cPathFileSent,"%soutgoing\\sent\\%s_%s",g_szConfigFilePath,receiver,cFilename);

					CFileException e;
					CFile* pFile = new CFile();
					if(pFile->Open(cPathFileSent, CFile::modeWrite | CFile::modeCreate, &e)) 
					{
						pFile->Write(filebuffer,nLen);
						pFile->Close();
					}	delete pFile;

				} catch (...) {ErrorLog("Error in EmailSender::SendLocalEmails 3.2");}
			}
			else
			{
				// Handle feedback of undeliverable mail

				 //msg[393347]

				CString sMsgId = ""; char server[100]; CString sUser = "";

				try {

					int nLenLimited = nLen;
					if(nLenLimited>10000) {nLenLimited=10000;}
					CString sFileBuffer = filebuffer;
					int nPos = sFileBuffer.Find("Subject: ",0);
					nPos = sFileBuffer.Find("msg[",nPos);
					int nPos2 = sFileBuffer.Find("]",nPos);
					
					
					if((nPos>-1) && (nPos2>-1) && ((nPos2-nPos)<20))
					{
						sMsgId = sFileBuffer.Mid(nPos,nPos2-nPos+1);
					}
				
					sprintf(server,"postmaster@%s",g_szConfigDomain);
		
					sUser = sender;
					int nUserPos = sUser.Find("@",0);
					if(nUserPos>-1)
					{
						sUser = sUser.Mid(0,nUserPos);
					}

				} catch (...) {ErrorLog("Error in EmailSender::SendLocalEmails 3.3");}

				
				try {

					CString sPathUserMbox; TCHAR szDate[255]; char subject[500]; char body[6000]; char message[50000];

					sPathUserMbox.Format("%s%s\\%s\\mbox\\%s",g_szConfigFilePath,g_szConfigDomain,sUser,cFilename);

					GetRFCTime(szDate); 

					sprintf(subject,"Undeliverable Message to: %s %s",receiver,sMsgId);
					sprintf(body,"The Message sent at %s and resent at %s could not be delivered. SMTP %d.",sSentDate,CTime::GetCurrentTime().Format("20%y/%m/%d  %H:%M:%S"),nError);
					sprintf(message,"Return-path: <%s>\r\nEnvelope-to: %s\r\nDate: %s\r\nMIME-Version: 1.0\r\nFrom: %s\r\nSubject: %s\r\nTo: %s\r\n\r\n%s\r\n.\r\n",server,sender,szDate,sender,subject,sender,body);
		
					{ // Copy message to mailbox
					CFileException e; CFile* pFile = new CFile();
					if(pFile->Open(sPathUserMbox, CFile::modeWrite | CFile::modeCreate, &e)) 
					{
						pFile->Write(message,strlen(message));
						pFile->Close();
					}	delete pFile;
					}

					char cPathFile1[200]; 
					
					sprintf(cPathFile1,"%soutgoing\\Undeliverable",g_szConfigFilePath);
					mkdir(cPathFile1);
					
					{
					sprintf(cPathFile1,"%soutgoing\\Undeliverable\\%s_%s",g_szConfigFilePath,receiver,cFilename);	
					CFileException e; CFile* pFile = new CFile();
					if(pFile->Open(cPathFile1, CFile::modeWrite | CFile::modeCreate, &e)) 
					{
						pFile->Write(filebuffer,nLen);
						pFile->Close();
					}	delete pFile;
					}

				} catch (...) {ErrorLog("Error in EmailSender::SendLocalEmails 3.4");}
			}
		}

		try {

			delete [] filebuffer;

		} catch (...) {ErrorLog("Error in EmailSender::SendLocalEmails 3.5");}

		WSACleanup();

		} catch (...) {ErrorLog("Error in EmailSender::SendLocalEmails Block 3");}
	
		Sleep(80);
	}

} catch (...) {ErrorLog("Error in EmailSender::SendLocalEmails");}}


int EmailSender::ResendUndeliverableLocalEmails()
{try {

	if(g_nConfigRunSMTPServer==1) // Only run it when SMTP Server is activated !
	{

		WIN32_FIND_DATA FindFileData;
		HANDLE hFind = INVALID_HANDLE_VALUE;

		char DirSpec[200];

		sprintf(DirSpec,"%soutgoing\\1\\*.eml",g_szConfigFilePath);

		hFind = FindFirstFile(DirSpec, &FindFileData);

		if (hFind == INVALID_HANDLE_VALUE) 
		{
			return (-1);
		} 
		else 
		{
			SendUndeliverableLocalEmails(FindFileData.cFileName);

			while (FindNextFile(hFind, &FindFileData) != 0) 
			{
				SendUndeliverableLocalEmails(FindFileData.cFileName);  
			}

			FindClose(hFind);
		}
	}

} catch (...) {ErrorLog("Error in EmailSender::ResendUndeliverableLocalEmails");}

	return 1;
}


DWORD WINAPI ResendUndeliverableLocalEmailsThread(LPVOID pParam)
{
	EmailSender es;
	es.ResendUndeliverableLocalEmails();
	return 0;
}


void EmailSender::StartSendUndeliverableLocalEmails()
{

	HANDLE hThread; DWORD ThreadID; 	
	hThread = CreateThread ( NULL, 0, ResendUndeliverableLocalEmailsThread, NULL, 0, &ThreadID);

}



CString EmailSender::SendSingleEmailTest()
{
	EmailMessage emailmsg;

	CString sBody = "";

	{
		char cPos[300000]; DWORD nLen = 0;
		CFileException e;
		CFile* pFile = new CFile();
		if(pFile->Open(/*sFilePath+*/"email2.txt", CFile::modeRead , &e)) 
		{
			nLen = pFile->GetLength();
			pFile->Read(cPos,nLen);
			pFile->Close();
		}   delete pFile;

		cPos[nLen]=0;
		sBody = cPos;
	}


	strcpy(emailmsg.emailreceiver,"christian@printsmadeeasy.com");
	strcpy(emailmsg.namereceiver,"Testusername");
	strcpy(emailmsg.textbody, sBody);
	strcpy(emailmsg.subject, "Hallo");
	strcpy(emailmsg.sendername,"Christian");
	strcpy(emailmsg.senderemail,"christian@asynx.com");
	strcpy(emailmsg.mailhost,"");
	strcpy(emailmsg.mailuser,"");	
	strcpy(emailmsg.mailpw,"");

	emailmsg.bPlain=TRUE; 

	HANDLE hEmailThread; DWORD EmailThreadID; 
	
	hEmailThread = CreateThread ( NULL, 0, SendEmailToTheServerThread, (LPVOID) &emailmsg, 0, &EmailThreadID);
	
	Sleep(80);

	return "";
}


void EmailSender::DownloadXML()
{try{

	WSADATA wsa;
	WSAStartup(MAKEWORD(2,0),&wsa);

	char cURL[2000];
	sprintf(cURL,"http://%s%s",g_szConfigXMLHost,g_szConfigXMLFile);

	char * buffer; 
	CInternetSession session; 
	UINT nBytesRead = 0;
	CStdioFile* pFile1 = NULL; 


	try {
		pFile1 = session.OpenURL(cURL, 0, INTERNET_FLAG_TRANSFER_BINARY | INTERNET_FLAG_KEEP_CONNECTION | INTERNET_FLAG_DONT_CACHE);
		int nFilesize = 5000000;
		buffer = new char[nFilesize+10]; memset(buffer,0,nFilesize);
		nBytesRead = pFile1->ReadHuge(buffer, nFilesize);
	}
	catch(CInternetException* e) {
		e->Delete();
	} 
	
	if(pFile1) delete pFile1;

	WSACleanup();

	// Files saven
	{
	CFileException e;
	CFile* pFileW = new CFile();
	if(pFileW->Open(sFilePath+"emailjob.dat", CFile::modeWrite | CFile::modeCreate, &e)) 
	{
		pFileW->Write(buffer,nBytesRead);
		pFileW->Close();
	}   delete pFileW;
	}

	delete [] buffer;

	{
	CFileException e;
	CFile* pFileW = new CFile();
	if(pFileW->Open(sFilePath+"emailposition.txt", CFile::modeWrite | CFile::modeCreate, &e)) 
	{
		pFileW->Write("0",1);
		pFileW->Close();
	}   delete pFileW;
	}

} catch (...) {ErrorLog("Error in EmailSender::DownloadXML");}}



CString EmailSender::GetParseValue(CString sXML, CString sXMLUpper, int nStartPos, CString sField)
{
	CString sValue = "";

try{

	sField.MakeUpper();
	
	int nPos = sXMLUpper.Find("<"+sField+">",nStartPos);
	int nEnd = sXMLUpper.Find("</",nPos);

	if(nPos>-1)
	{
		sValue =  sXML.Mid(nPos+sField.GetLength()+2,nEnd-nPos-sField.GetLength()-2);
	}

} catch (...) {ErrorLog("Error in EmailSender::GetParseValue");}

	return sValue;
}


CString EmailSender::GenerateMessage(EmailTextVar &etv, int nPosition, int nStartPos)
{
	CString sMessageContents = "";
	
	try{


	///// Log BeforeProcessing file ////
		
	{
		CString sDate = CTime::GetCurrentTime().Format("20%y-%m-%d");

		CString sBeforeFilename;

		sBeforeFilename.Format("%semailsentlog\\%s\\%s_beforeProcessing.txt",g_szConfigFilePath,sDate,etv.sAEmail.ElementAt(nPosition));

		CFileException e;
		CFile* pFileW = new CFile();
		if(pFileW->Open(sBeforeFilename, CFile::modeWrite | CFile::modeCreate, &e)) 
		{
			pFileW->Write(etv.sMessageContents,etv.sMessageContents.GetLength());
			pFileW->Close();
		}   delete pFileW;
	}

	///////////////////////////////////


	int nHtmlEnd = etv.sMessageContents.Find("Content-Transfer-Encoding: base64",0); // Cut off message part from picture part for parsing process

	CString sHtml1 = "",sHtml2 = "";

	if(nHtmlEnd>-1)
	{
		sHtml1 = etv.sMessageContents.Mid(0,nHtmlEnd);
		sHtml2 = etv.sMessageContents.Mid(nHtmlEnd, etv.sMessageContents.GetLength());
	}
	else
	{
		sHtml1 = etv.sMessageContents;
		sHtml2 = "";
	}

	// Restore "{TRACK}" that has been broken down into 76 char/line within it
	int nBStart = 0; bool bRun = TRUE; int nDL=0;
	while(bRun==TRUE)
	{
		int nBPos1 = sHtml1.Find("{",nBStart);
		
		bRun = FALSE; nDL++;
	
		if(nBPos1>-1)
		{
			bRun = TRUE; nBStart += 2;

			int nBPos2 = sHtml1.Find("}",nBPos1);
			if(nBPos2>-1)
			{
				if(nDL>50) { bRun = FALSE;} // Prevent endless loops, limit it to 50 max.
		
				if((nBPos2-nBPos1)<30) // Max 30 chars in {TRACK}
				{
					CString sValue = sHtml1.Mid(nBPos1,nBPos2-nBPos1+1);
					if(sValue.Find("=",0)>-1)
					{
						CString sValue2=sValue;
						char cCR[3];cCR[0]=13;cCR[1]=10;cCR[2]=0; 
						sValue2.Replace("="+CString(cCR),"");
						sHtml1.Replace(sValue,sValue2);
					}
					nBStart = nBPos2;
				}
			}
		}
	}


	try{
	
	char c0D0A[3];c0D0A[0]=13;c0D0A[1]=10;c0D0A[2]=0;
	sHtml1.Replace(c0D0A+CString("{ORDERCODE}"),"{ORDERCODE}"); // Globally remove <CR> before {ORDERCODE} link
 

	bool bFoundOrderCode = true; int nMaxLoops=0;

	while(bFoundOrderCode)
	{
		CString sRedirLink = "/";

		int nOrderLinkPos = sHtml1.Find("{ORDERCODE}",0);

		bFoundOrderCode = false;

		if(nOrderLinkPos>-1)
		{
			bFoundOrderCode = true;

			int nOrderLinkStart = nOrderLinkPos+11;

			int nOrderLinkEnd  = nOrderLinkStart;
			int nOrderLinkEnd1 = sHtml1.Find("'" ,nOrderLinkStart);
			int nOrderLinkEnd2 = sHtml1.Find("\"",nOrderLinkStart);
			
			if((nOrderLinkEnd1==-1) && (nOrderLinkEnd2==-1))
			{
				sRedirLink = "/";
			}
			else
			{
				if((nOrderLinkEnd1==-1) && (nOrderLinkEnd2>-1)) { nOrderLinkEnd = nOrderLinkEnd2;}
				if((nOrderLinkEnd2==-1) && (nOrderLinkEnd1>-1)) { nOrderLinkEnd = nOrderLinkEnd1;}
				if((nOrderLinkEnd1>-1)  && (nOrderLinkEnd1<nOrderLinkEnd2)) { nOrderLinkEnd = nOrderLinkEnd1;}
				if((nOrderLinkEnd2>-1)  && (nOrderLinkEnd2<nOrderLinkEnd1)) { nOrderLinkEnd = nOrderLinkEnd2;}

				sRedirLink = sHtml1.Mid(nOrderLinkStart,nOrderLinkEnd-nOrderLinkStart);

				if((sRedirLink=="") || (sRedirLink.GetLength()>150)) { sRedirLink = "/";}
			}	
			
			CString sHtml1A = sHtml1.Mid(0,nOrderLinkEnd);
			CString sHtml1B = sHtml1.Mid(nOrderLinkEnd,sHtml1.GetLength()-nOrderLinkEnd);

			CString sString = CString(etv.sAEmail.ElementAt(nPosition)+"|"+etv.sATrackID.ElementAt(nPosition)+"|"+sRedirLink);
			CBase64 base64; base64.Encode(sString);
		
			CString sOrderCode  = base64.EncodedMessage();

			sOrderCode.Replace("=","=3D"); // Problem with ==">

			if(sRedirLink == "/")
			{
				sHtml1A.Replace("{ORDERCODE}",sOrderCode);
			}
			else
			{
				sHtml1A.Replace("{ORDERCODE}"+sRedirLink,sOrderCode);
			}

			sHtml1 = sHtml1A + sHtml1B;
		}

		nMaxLoops++; if(nMaxLoops>100) {bFoundOrderCode = false;}

	}} catch (...) {ErrorLog("Error in EmailSender::GenerateMessage Replace OrderCode");}


	sHtml1.Replace("{JOB_ID}",				etv.sJobID);
	sHtml1.Replace("{UNSUBSCRIBE_EMAIL}",	etv.sAEmail.ElementAt(nPosition));
	sHtml1.Replace("{EMAIL}",				etv.sAEmail.ElementAt(nPosition));
	sHtml1.Replace("{TRACK_ID}",			etv.sATrackID.ElementAt(nPosition));

	CString sMarker1="@#@", sMarker2="#@#";

	sHtml1.Replace("{PERSON_ADDRESS}",		sMarker1 + etv.sAAddress.ElementAt(nPosition)	+ sMarker2);
	sHtml1.Replace("{PERSON_NAME}",			sMarker1 + etv.sAName.ElementAt(nPosition)   	+ sMarker2);
	sHtml1.Replace("{PERSON_TITLE}",		sMarker1 + etv.sATitle.ElementAt(nPosition)	    + sMarker2);
	sHtml1.Replace("{PERSON_SICCODE}",		sMarker1 + etv.sASICCode.ElementAt(nPosition)	+ sMarker2);
	sHtml1.Replace("{PERSON_COMPANY}",		sMarker1 + etv.sACompany.ElementAt(nPosition)	+ sMarker2);
	sHtml1.Replace("{PERSON_INDUSTRY}",		sMarker1 + etv.sAIndustry.ElementAt(nPosition)	+ sMarker2);
	sHtml1.Replace("{PERSON_PHONE}",		sMarker1 + etv.sAPhone.ElementAt(nPosition)		+ sMarker2);
	sHtml1.Replace("{PERSON_CITY}",			sMarker1 + etv.sACity.ElementAt(nPosition)		+ sMarker2);
	sHtml1.Replace("{PERSON_STATE}",		sMarker1 + etv.sAState.ElementAt(nPosition)		+ sMarker2);
	sHtml1.Replace("{PERSON_ZIP}",			sMarker1 + etv.sAZip.ElementAt(nPosition)		+ sMarker2);
	sHtml1.Replace("{PERSON_COUNTRY}",		sMarker1 + etv.sACountry.ElementAt(nPosition)	+ sMarker2);


	//Removes Line with empty {TRACK} 
	if(sHtml1.Find(sMarker1+sMarker2)>-1)
	{
		int nMessageSize = sHtml1.GetLength() + 10;

		char * cMessage; 
		cMessage = (char*)malloc(nMessageSize); memset(cMessage,0,nMessageSize); 
		strcpy(cMessage,sHtml1); 
		
		CString sMessage = "";

		WORD nLen = strlen(cMessage); int nSavePos=-1; char cLine[2000]; memset(cLine,0,2000);

		for(WORD x=0; x<nLen; x++)	
		{
			if((cMessage[x]==10) || (x==nLen-1) )
			{
				memcpy(cLine,cMessage+nSavePos+1,x-nSavePos-1);
				cLine[x-nSavePos-1]=0;
				CString sLine = cLine;

				if(sLine.Find(sMarker1+sMarker2,0)==-1)
				{
					sMessage += sLine;
				}

				nSavePos=x;
			}
		}

		delete [] cMessage;
		
		sHtml1 = sMessage;
	}

	sMessageContents = sHtml1 + sHtml2;
	
	sMessageContents.Replace(sMarker1,""); sMessageContents.Replace(sMarker2,"");


} catch (...) {ErrorLog("Error in EmailSender::GenerateMessage");}

	return sMessageContents;
}


CString EmailSender::GenerateSubject(EmailTextVar &etv, int nPosition)
{

CString sSubject = etv.sSubject;
	
try{

	sSubject.Replace("{PERSON_ADDRESS}",	etv.sAAddress.ElementAt(nPosition));
	sSubject.Replace("{PERSON_NAME}",		etv.sAName.ElementAt(nPosition));
	sSubject.Replace("{PERSON_TITLE}",		etv.sATitle.ElementAt(nPosition));
	sSubject.Replace("{PERSON_COMPANY}",	etv.sACompany.ElementAt(nPosition));
	sSubject.Replace("{PERSON_INDUSTRY}",	etv.sAIndustry.ElementAt(nPosition));
	sSubject.Replace("{PERSON_PHONE}",		etv.sAPhone.ElementAt(nPosition));
	sSubject.Replace("{PERSON_CITY}",		etv.sACity.ElementAt(nPosition));
	sSubject.Replace("{PERSON_STATE}",		etv.sAState.ElementAt(nPosition));
	sSubject.Replace("{PERSON_ZIP}",		etv.sAZip.ElementAt(nPosition));
	sSubject.Replace("{PERSON_COUNTRY}",	etv.sACountry.ElementAt(nPosition));
	sSubject.Replace("{PERSON_SICCODE}",	etv.sASICCode.ElementAt(nPosition));

} catch (...) {ErrorLog("Error in EmailSender::GenerateSubject");}

	return sSubject;
}


CString EmailSender::DecryptBlowFish(CString sBase64Encrypted)
{
	CString sXML = ""; // szDataOut;

try{
		
	CBase64 base64;
	base64.Decode(sBase64Encrypted);
	DWORD nDecodedSize = base64.GetDecodedSize();

	CGZIP2A plain((unsigned char*)base64.DecodedMessage(),nDecodedSize);  // decompressing

	char *pplain=plain.psz;    // psz is plain data pointer
	int  aLen=plain.Length;    // Length is length of unzipped data.

	{	
		CFileException e;
		CFile* pFileW = new CFile();
		if(pFileW->Open(sFilePath+"dataoutXMLOut.txt", CFile::modeWrite | CFile::modeCreate, &e)) 
		{
			pFileW->Write(plain.psz,aLen);
			pFileW->Close();
		}   delete pFileW;
	}

	sXML = plain.psz; 

} catch (...) {ErrorLog("Error in EmailSender::DecryptBlowFish");}

	return sXML;
}		




void EmailSender::ReadXML()
{try{

	CString sXML(""), sPosition = "DONE";

	{
		char cPos[1000]; DWORD nLen = 0;
		CFileException e;
		CFile* pFile = new CFile();
		if(pFile->Open(sFilePath+"emailposition.txt", CFile::modeRead , &e)) 
		{
			nLen = pFile->GetLength();
			pFile->Read(cPos,nLen);
			pFile->Close();
		}   delete pFile;

		cPos[nLen]=0;
		sPosition = cPos;
	}

	if(sPosition != "DONE")
	{
		char cText[XML_MAX_SIZE]; DWORD nLen = 0; bool bFileExists = false;
		CFileException e;
		CFile* pFile = new CFile();
		if(pFile->Open(sFilePath+"emailjob.dat", CFile::modeRead , &e)) 
		{
			nLen = pFile->GetLength(); if(nLen>(XML_MAX_SIZE-10)) {nLen=(XML_MAX_SIZE-10);}
			pFile->Read(cText,nLen);
			pFile->Close();
			bFileExists = true;
		}   delete pFile;


		if(bFileExists == true)
		{
			cText[nLen] = 0;

			sXML = DecryptBlowFish(cText);

			CString sXMLUpper = sXML; sXMLUpper.MakeUpper();

			/////////// Create Directory for email sent log ////////////////
		
			CString sSendLogPath; CString sDate = CTime::GetCurrentTime().Format("20%y-%m-%d");

			sSendLogPath.Format("%semailsentlog",g_szConfigFilePath);
			mkdir(sSendLogPath);

			sSendLogPath.Format("%semailsentlog\\%s",g_szConfigFilePath,sDate);
			mkdir(sSendLogPath);

			////////////////////////////////////////////////////////////////

			EmailTextVar etv;

			int emailJobsPos = sXMLUpper.Find("<EMAILJOBS>",0);

			int nAmountToSendEachCronIteration = atoi(GetParseValue(sXML,sXMLUpper,emailJobsPos,"amountToSendEachCronIteration"));
			if (nAmountToSendEachCronIteration < 1) {nAmountToSendEachCronIteration = 1;}
			
			int nCronPeriodMinutes = atoi(GetParseValue(sXML,sXMLUpper,emailJobsPos,"cronPeriodMinutes"));

			{
				CString sCPM; sCPM.Format("%d",nCronPeriodMinutes);	
				CFileException e;
				CFile* pFileW = new CFile();
				if(pFileW->Open(sFilePath+"iteration.txt", CFile::modeWrite | CFile::modeCreate, &e)) 
				{
					pFileW->Write(sCPM,sCPM.GetLength());
					pFileW->Close();
				}   delete pFileW;
			}
			
			
			int nWaitTimeBetweenEmail = ((double(nCronPeriodMinutes*60)/double(nAmountToSendEachCronIteration*1.2))*1000)-100;
			if(nWaitTimeBetweenEmail<500) {nWaitTimeBetweenEmail=500;}

			int jobPos = sXMLUpper.Find("<JOB>",emailJobsPos);
			int messagePos = sXMLUpper.Find("<MESSAGE>",jobPos);

			CString sMessageFormat   = GetParseValue(sXML,sXMLUpper,messagePos,"messageFormat");
			
			CBase64 base64;
			
			base64.Decode(GetParseValue(sXML,sXMLUpper,messagePos,"messageSubject"));
			etv.sSubject = base64.DecodedMessage();

			base64.Decode(GetParseValue(sXML,sXMLUpper,messagePos,"messageContents"));
			etv.sMessageContents = base64.DecodedMessage();
		
			etv.sJobID = GetParseValue(sXML,sXMLUpper,messagePos,"jobID");
			
			etv.sSenderName  = GetParseValue(sXML,sXMLUpper,messagePos,"fromName");
			etv.sSenderEmail = GetParseValue(sXML,sXMLUpper,messagePos,"fromEmail");


			int emailListPos = sXMLUpper.Find("<EMAILLIST>",emailJobsPos);
			int nEmailCount = atoi(GetParseValue(sXML,sXMLUpper,messagePos,"emailCount"));



			int personPos = emailListPos;

			CString sEmailPosition="";

			int nStartEmail = atoi(sPosition); 

			int nEndEmail = nAmountToSendEachCronIteration + nStartEmail;
			
			if(sPosition=="DONE") 
			{
				nEmailCount = 0;
			}
			else
			{
				if(nEndEmail<nStartEmail) { nStartEmail = nEmailCount; sEmailPosition = "DONE";}
			}

			if(nEndEmail>nEmailCount) { nEndEmail = nEmailCount;}

			
			sEmailPosition.Format("%d",nEndEmail);
			
			if(nEndEmail==nEmailCount) 
			{ 
				sEmailPosition = "DONE";
			}

			for(int x=0; x<nEmailCount; x++)
			{
				personPos = sXMLUpper.Find("<PERSON>",personPos);

				int nPosition = atoi(GetParseValue(sXML,sXMLUpper,personPos,"Position"));
				
				if((x>=nStartEmail) && (x<nEndEmail))
				{
					etv.sATrackID.Add(GetParseValue(sXML,sXMLUpper,personPos,"ID"));
					etv.sAName.Add(GetParseValue(sXML,sXMLUpper,personPos,"Name"));
					etv.sATitle.Add(GetParseValue(sXML,sXMLUpper,personPos,"Title"));
					etv.sASICCode.Add(GetParseValue(sXML,sXMLUpper,personPos,"SICCode"));
					etv.sACompany.Add(GetParseValue(sXML,sXMLUpper,personPos,"Company"));
					etv.sAIndustry.Add(GetParseValue(sXML,sXMLUpper,personPos,"Industry"));
					etv.sAEmail.Add(GetParseValue(sXML,sXMLUpper,personPos,"Email"));
					etv.sAPhone.Add(GetParseValue(sXML,sXMLUpper,personPos,"Phone"));
					etv.sAAddress.Add(GetParseValue(sXML,sXMLUpper,personPos,"Address"));
					etv.sACity.Add(GetParseValue(sXML,sXMLUpper,personPos,"City"));
					etv.sAState.Add(GetParseValue(sXML,sXMLUpper,personPos,"State"));
					etv.sAZip.Add(GetParseValue(sXML,sXMLUpper,personPos,"Zip"));
					etv.sACountry.Add(GetParseValue(sXML,sXMLUpper,personPos,"Country"));
				}
				personPos += 20;
			}


			{
			CFileException e;
			CFile* pFileW = new CFile();
			if(pFileW->Open(sFilePath+"emailposition.txt", CFile::modeWrite | CFile::modeCreate, &e)) 
			{
				pFileW->Write(sEmailPosition,sEmailPosition.GetLength());
				pFileW->Close();
			}   delete pFileW;
			}



			for(int s=0; s<etv.sAEmail.GetSize(); s++)
			{
				EmailMessage emailmsg;

				strcpy(emailmsg.textbody,GenerateMessage(etv,s,nStartEmail));

				strcpy(emailmsg.subject,GenerateSubject(etv,s));
				strcpy(emailmsg.emailreceiver,etv.sAEmail.ElementAt(s));
				strcpy(emailmsg.namereceiver, etv.sAName.ElementAt(s));	
				strcpy(emailmsg.trackid, etv.sATrackID.ElementAt(s));	

				strcpy(emailmsg.sendername,etv.sSenderName);
				strcpy(emailmsg.senderemail,etv.sSenderEmail);

				strcpy(emailmsg.mailhost,g_szConfigMailHost);
				strcpy(emailmsg.mailuser,g_szConfigMailUser);	
				strcpy(emailmsg.mailpw,g_szConfigMailPass);
				strcpy(emailmsg.jobid, etv.sJobID);	
			
				emailmsg.bPlain=FALSE; 

				if(sMessageFormat=="HTML")
				{			
					emailmsg.bPlain=TRUE; 
				}

				HANDLE hEmailThread; DWORD EmailThreadID; 
				hEmailThread = CreateThread ( NULL, 0, SendEmailToTheServerThread, (LPVOID) &emailmsg, 0, &EmailThreadID);
				Sleep(80);

		
				Sleep(nWaitTimeBetweenEmail);
			}

		}

	}

} catch (...) {ErrorLog("Error in EmailSender::ReadXML");}}


void EmailSender::DecodeBase64Test()
{
	CString sBase64Subject =  "SGVsbG8gdGhpcyBpcyB0aGUgTmV3c2xldHRlciBmcm9tIENocmlzdGlhbiBtYWRlIHdpdGggUE1FIEFkbWlu";
	CBase64 b64;
	b64.Decode(sBase64Subject); //((const PBYTE)szMessage, lstrlenA(szMessage));
	CString sMessage = b64.DecodedMessage();
}
