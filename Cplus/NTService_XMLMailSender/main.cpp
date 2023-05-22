/****************************************************************/
/*																*/
/*  MAIN.CPP													*/
/*																*/
/*  Implementation of the main() which is the entry point of 	*/
/*  of the application.											*/
/*																*/
/*  Programmed by Pablo van der Meer							*/
/*  Copyright Pablo Software Solutions 2003						*/
/*																*/
/*  Last updated: 24th February 2002							*/
/*																*/
/****************************************************************/

#include "stdafx.h"
#include "MyService.h"
#include "..\\XMLMailSender\\config2.inc"

extern char g_szConfigDomainServiceName[128];

CWinApp theApp;

#define VERSION "             Version 1.0              "

char cServiceName[100];

int main() 
{
	// try to initialize MFC
	if (!AfxWinInit(::GetModuleHandle(NULL), NULL, ::GetCommandLine(), 0))
	{
		return 1;
	}

	LoadConfigDomain();

	strcpy(cServiceName,g_szConfigDomainServiceName); // Name Service as domain, for multiple domainservices on the same server

	// startup service
	CMyService service;
	
	BOOL bResult = service.ExecuteService();

	// clean up
	AfxWinTerm();

	return bResult; 
}

