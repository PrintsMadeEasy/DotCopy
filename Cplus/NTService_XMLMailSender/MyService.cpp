/****************************************************************/
/*																*/
/*  MyService.cpp												*/
/*																*/
/*  Implementation of the CMyService.							*/
/*																*/
/*  Programmed by Pablo van der Meer							*/
/*  Copyright Pablo Software Solutions 2003						*/
/*	http://www.pablovandermeer.nl								*/
/*																*/
/*  Last updated: 24th February 2003							*/
/*																*/
/****************************************************************/

#include "stdafx.h"
#include "MyService.h"

#include "..\\XMLMailSender\\EmailSender.h"
#include "..\\XMLMailSender\\SMTPMine.h"
#include "..\\XMLMailSender\\Pop3Thread.h"
#include "..\\XMLMailSender\\SMTPServerThread.h"
#include "..\\XMLMailSender\\config.inc"

#define WM_STOP_SERVICE WM_USER+1

extern char cServiceName[100];

extern int nSmtpSplitCount;
extern int nPop3SplitCount;
extern int n1SplitCount;
extern int n2SplitCount;


BEGIN_MESSAGE_MAP(CMyService, CNTService)
	//{{AFX_MSG_MAP(CMyService)
	ON_WM_TIMER()
	//}}AFX_MSG_MAP
	ON_MESSAGE(WM_STOP_SERVICE, OnStopService)
END_MESSAGE_MAP()


CMyService::CMyService() : CNTService(cServiceName, cServiceName)
{
	m_hStop = NULL;
}

/********************************************************************/
/*																	*/
/* Function name : Run												*/		
/* Description   : Main loop of this application.					*/
/*				   We need to peek for messages and dispatch them	*/
/*				   otherwise this application will not function.	*/
/*																	*/
/********************************************************************/
void CMyService::Run() 
{
	// mutex will be automatically deleted when process ends. 
	HANDLE hMutex = CreateMutex(NULL, TRUE, cServiceName);
	
	BOOL bAlreadyRunning = (GetLastError() == ERROR_ALREADY_EXISTS); 
	if (bAlreadyRunning) 
	{
		if (hMutex) 
		{
			CloseHandle(hMutex);
		}
		return; 
	}

	// report to the SCM that we're about to start
	SetServiceStatus(SERVICE_START_PENDING);

	m_hStop = ::CreateEvent(0, TRUE, FALSE, 0);

	// create Service Notification Sink (to be enable message handling)
	m_hWnd = NULL;
	if (!CreateEx(0, AfxRegisterWndClass(0), _T("My Service Notification Sink"),
		WS_OVERLAPPED, 0, 0, 0, 0, NULL, NULL))
	{
		AfxThrowResourceException();
	}

	SetServiceStatus(SERVICE_RUNNING);

	// Start
	SetTimer(1312,200, NULL);
	
	DWORD dwResult;
	MSG msg;
	
	// Pump messages while waiting for event
	while (1)
	{
		// wait for event or message, if it's a message, process it and return to waiting state
		dwResult = MsgWaitForMultipleObjects(1, &m_hStop, FALSE, 10, QS_ALLINPUT);
		if (dwResult == WAIT_OBJECT_0)
		{
			break;
		}   
		else
		if (dwResult == WAIT_OBJECT_0 + 1)
		{
			// process window messages
			while (PeekMessage(&msg, NULL, NULL, NULL, PM_REMOVE))
			{
				TranslateMessage(&msg);
				DispatchMessage(&msg);
			}
		}  
	}

	KillTimer(1);

	// Destroy Service Notification Sink
	DestroyWindow();
	
	if(m_hStop)
		::CloseHandle(m_hStop);

	if (hMutex) 
	{
		CloseHandle(hMutex);
	}
}


/********************************************************************/
/*																	*/
/* Function name : Stop												*/		
/* Description   : Stop the service  by setting m_hStop event.		*/
/*																	*/
/********************************************************************/
void CMyService::Stop() 
{
	// post message to this class, to prevent hatch table problems
	PostMessage(WM_STOP_SERVICE);
}


/********************************************************************/
/*																	*/
/* Function name : OnStopService									*/		
/* Description   : Stop service (prevent hatch table problems...)	*/
/*																	*/
/********************************************************************/
LRESULT CMyService::OnStopService(WPARAM wParam, LPARAM lParam)
{
	// report to the SCM that we're about to stop
	SetServiceStatus(SERVICE_STOP_PENDING, 5000);

	if(m_hStop)
		::SetEvent(m_hStop);
	
	return 0L;
}


/********************************************************************/
/*																	*/
/* Function name : OnTimer											*/
/* Description   : Handle timer event								*/
/*																	*/
/********************************************************************/

void CMyService::OnTimer(UINT nIDEvent) 
{
	if(nIDEvent==1312)
	{
		KillTimer(1312);

		LoadConfig();
		LoadDenyfile();

		nSmtpSplitCount = 0;
		nPop3SplitCount = 0;
		n1SplitCount = 0;
		n2SplitCount = 0;

		SetTimer(27090,5000,NULL);
	
		SetTimer(2502,10000,NULL);  // Send outgoing emails
		SetTimer(2503,300100,NULL);  // Try to resend emails, check for new every 5 minutes 


		if(g_nConfigDownloadTimeSecs>-1) // Don't download it: Turned off in config.txt 
		{
			SetTimer(1234,800,NULL); 
		}

		if(g_nConfigRunSMTPServer==1)
		{
			CSMTPThread smtpserver;
			smtpserver.StartSMTPServerThread();
		}

		if(g_nConfigRunPop3Server==1)
		{
			CPop3Thread pop3;
			pop3.StartPOP3Thread();
		}
	}

	if(nIDEvent==1234)
	{		
		CTime tNow = CTime::GetCurrentTime(); int nNow = tNow.GetHour()*3600+tNow.GetMinute()*60+tNow.GetSecond();

		if( (nNow>=g_nConfigDownloadTimeSecs) && (nNow<=(g_nConfigDownloadTimeSecs+5)) ) // Eventwindow of 5secs possible
		{			
			KillTimer(2709);

			if(g_nConfigRunLog==1) { CString sText; sText.Format("Start Download"); FILE * pFile; if(pFile = fopen (CString(g_szConfigFilePath)+"timerlog.txt","a")) { fputs (CTime::GetCurrentTime().Format("20%y/%m/%d  %H:%M:%S ")+sText+"\n",pFile); fclose (pFile); }}

			Sleep(10000); // Block for 10secs, so we have no double download !

			EmailSender sendEmail;
			sendEmail.DownloadXML();
			
			if(g_nConfigRunLog==1) { CString sText; sText.Format("End Download"); FILE * pFile; if(pFile = fopen (CString(g_szConfigFilePath)+"timerlog.txt","a")) { fputs (CTime::GetCurrentTime().Format("20%y/%m/%d  %H:%M:%S ")+sText+"\n",pFile); fclose (pFile); }}

			sendEmail.ReadXML();
			
			SetTimer(2709,ReadIteration(),NULL);
		}
	}

	// First Start
	if(nIDEvent==27090)
	{		
		KillTimer(27090);
		EmailSender sendEmail;
		sendEmail.ReadXML();

		SetTimer(2709,ReadIteration(),NULL);
	}


	if(nIDEvent==2709)
	{		
		EmailSender sendEmail;
		sendEmail.ReadXML();
	}

	if(nIDEvent==2502)
	{		
		EmailSender sendEmail;
		sendEmail.StartSendingAllLocalEmails();
	}
	
	if(nIDEvent==2503)
	{		
		EmailSender sendEmail;
		sendEmail.StartSendUndeliverableLocalEmails();
	}

	CNTService::OnTimer(nIDEvent);
}
