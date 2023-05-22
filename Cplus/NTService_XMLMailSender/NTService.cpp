/********************************************************************/
/*																	*/
/*  NTService.cpp													*/
/*																	*/
/*  Implementation of the CNTService class.							*/
/*  MFC class to enable an application to run like a service		*/
/*																	*/
/*  Programmed by Pablo van der Meer								*/
/*  Copyright Pablo Software Solutions 2003							*/
/*	http://www.pablovandermeer.nl									*/
/*																	*/
/*  Last updated: 24 february 2003									*/
/*																	*/
/********************************************************************/

#include "stdafx.h"
//#include "resource.h"
//#include <stdio.h>

#include "messages.h"
#include "NTService.h"


static CNTService * g_pTheService = NULL;



/********************************************************************/
/*																	*/
/* Function name: CNTService::CNTService							*/		
/* Description  : Constructor										*/
/*																	*/
/********************************************************************/
CNTService::CNTService(LPCTSTR lpszServiceName, LPCTSTR lpszDisplayName)
	: m_lpszServiceName(lpszServiceName)
	, m_lpszDisplayName(lpszDisplayName ? lpszDisplayName : lpszServiceName)
{
	m_hStatusHandle = 0;
	
	g_pTheService = this;
	
	// SERVICE_STATUS members that rarely change
	m_ServiceStatus.dwServiceType = SERVICE_WIN32_OWN_PROCESS;
	m_ServiceStatus.dwServiceSpecificExitCode = 0;
}


/********************************************************************/
/*																	*/
/* Function name: CNTService::~CNTService							*/
/* Description  : Destructor										*/
/*																	*/
/********************************************************************/
CNTService::~CNTService() 
{
	g_pTheService = NULL;
}

BEGIN_MESSAGE_MAP(CNTService, CWnd)
	//{{AFX_MSG_MAP(CNTService)
	//}}AFX_MSG_MAP
END_MESSAGE_MAP()


/********************************************************************/
/*																	*/
/* Function name: ExecuteService									*/
/* Description  : Checks the parameterlist and executes the			*/
/*			      appropriate function.								*/
/*																	*/
/********************************************************************/
BOOL CNTService::ExecuteService() 
{
	LPSTR lpCmdLine = GetCommandLine();
    
	TCHAR szTokens[] = _T("-/");

    LPCTSTR lpszToken = FindOneOf(lpCmdLine, szTokens);
    while (lpszToken != NULL)
    {
		switch(lpszToken[0]) 
		{
			case 'i':	
				// install the service
				return InstallService();
			case 'u':	
				// uninstall the service
				return UninstallService();
			case 's':	
				// start the service
				return StartService();
			case 'e':	
				// stop the service
				return StopService();
		}
        lpszToken = FindOneOf(lpszToken, szTokens);
    }
	return StartDispatcher();
}


/********************************************************************/
/*																	*/
/* Function name: StartDispatcher									*/
/* Description  : This function connects the main thread of this	*/
/*				  service process to the service control manager.	*/
/*																	*/
/********************************************************************/
BOOL CNTService::StartDispatcher() 
{
	SERVICE_TABLE_ENTRY st[] =
    {
        { LPTSTR(m_lpszServiceName), (LPSERVICE_MAIN_FUNCTION)ServiceMain },
        { NULL, NULL }
    };

	BOOL bResult = ::StartServiceCtrlDispatcher(st);
	if(!bResult) 
	{
		LPVOID lpMsgBuf;
		FormatMessage( 
			FORMAT_MESSAGE_ALLOCATE_BUFFER | 
			FORMAT_MESSAGE_FROM_SYSTEM | 
			FORMAT_MESSAGE_IGNORE_INSERTS,
			NULL,
			GetLastError(),
			MAKELANGID(LANG_NEUTRAL, SUBLANG_DEFAULT), // Default language
			(LPTSTR) &lpMsgBuf,
			0,
			NULL 
		);
		// process any inserts in lpMsgBuf.
        LogEvent((LPCTSTR)lpMsgBuf);
		// free the buffer.
		LocalFree(lpMsgBuf);
	}
	return bResult;
}


/********************************************************************/
/*																	*/
/* Function name: IsInstalled										*/
/* Description  : Check if service is installed						*/
/*																	*/
/********************************************************************/
BOOL CNTService::IsInstalled()
{
    BOOL bResult = FALSE;

    SC_HANDLE hServiceManager = ::OpenSCManager(NULL, NULL, SC_MANAGER_ALL_ACCESS);

    if (hServiceManager != NULL)
    {
        SC_HANDLE hService = ::OpenService(hServiceManager, m_lpszServiceName, SERVICE_QUERY_CONFIG);
        if (hService != NULL)
        {
            bResult = TRUE;
            ::CloseServiceHandle(hService);
        }
        ::CloseServiceHandle(hServiceManager);
    }
    return bResult;
}


/********************************************************************/
/*																	*/
/* Function name: InstallService									*/
/* Description  : Called if the "-i" parameter is passed on the		*/
/*				  commandline.										*/
/*																	*/
/********************************************************************/
BOOL CNTService::InstallService() 
{
	if (IsInstalled())
        return TRUE;

    SC_HANDLE hServiceManager = ::OpenSCManager(NULL, NULL, SC_MANAGER_ALL_ACCESS);
    if (hServiceManager == NULL)
    {
        ::MessageBox(NULL, "Couldn't open service manager", m_lpszServiceName, MB_OK | MB_ICONSTOP);
        return FALSE;
    }

    // Get the executable file path
    TCHAR szFilePath[_MAX_PATH];
    ::GetModuleFileName(NULL, szFilePath, _MAX_PATH);

    SC_HANDLE hService = ::CreateService(
								hServiceManager, 
								m_lpszServiceName, 
								m_lpszDisplayName,
								SERVICE_ALL_ACCESS, 
								SERVICE_WIN32_OWN_PROCESS,
								SERVICE_AUTO_START, 
								SERVICE_ERROR_NORMAL,
								szFilePath, 
								NULL, 
								NULL, 
								NULL, 
								NULL, 
								NULL);

    if (hService == NULL)
    {
        ::CloseServiceHandle(hServiceManager);
        ::MessageBox(NULL, "Couldn't create service", m_lpszServiceName, MB_OK | MB_ICONSTOP);
        return FALSE;
    }

    // add registry entries to support event logging
    char szKey[256];
    HKEY hKey = NULL;
    lstrcpy(szKey, "SYSTEM\\CurrentControlSet\\Services\\EventLog\\Application\\");
    lstrcat(szKey, m_lpszServiceName);
    if (::RegCreateKey(HKEY_LOCAL_MACHINE, szKey, &hKey) != ERROR_SUCCESS) 
	{
        ::CloseServiceHandle(hService);
        ::CloseServiceHandle(hServiceManager);
        return FALSE;
    }

    // add file name to 'EventMessageFile' subkey
    ::RegSetValueEx(hKey, "EventMessageFile",
                    0, 
					REG_EXPAND_SZ, 
					(CONST BYTE*)szFilePath, 
					lstrlen(szFilePath) + 1);     

    // set supported types
    DWORD dwData = EVENTLOG_ERROR_TYPE | EVENTLOG_WARNING_TYPE | EVENTLOG_INFORMATION_TYPE;
    ::RegSetValueEx(hKey,
                    "TypesSupported",
                    0,
                    REG_DWORD,
                    (CONST BYTE*)&dwData,
                     sizeof(DWORD));
    
	::RegCloseKey(hKey);

    ::CloseServiceHandle(hService);
    ::CloseServiceHandle(hServiceManager);
    
//	::MessageBox(NULL, "Service succesfully installed", m_lpszServiceName, MB_OK | MB_ICONINFORMATION);
    return TRUE;
}


/********************************************************************/
/*																	*/
/* Function name: UninstallService									*/
/* Description  : Uninstall service from system's service-table.	*/
/*																	*/
/********************************************************************/
BOOL CNTService::UninstallService() 
{
	if (!IsInstalled())
        return TRUE;

    SC_HANDLE hServiceManager = ::OpenSCManager(NULL, NULL, SC_MANAGER_ALL_ACCESS);

    if (hServiceManager == NULL)
    {
        ::MessageBox(NULL, "Couldn't open service manager", m_lpszServiceName, MB_OK | MB_ICONSTOP);
        return FALSE;
    }

    SC_HANDLE hService = ::OpenService(hServiceManager, m_lpszServiceName, SERVICE_STOP | DELETE);

    if (hService == NULL)
    {
        ::CloseServiceHandle(hServiceManager);
        ::MessageBox(NULL, "Couldn't open service", m_lpszServiceName, MB_OK | MB_ICONSTOP);
        return FALSE;
    }
    
	SERVICE_STATUS status;
    ::ControlService(hService, SERVICE_CONTROL_STOP, &status);

    BOOL bDelete = ::DeleteService(hService);
    ::CloseServiceHandle(hService);
    ::CloseServiceHandle(hServiceManager);

	// delete registry entries
    char szKey[256];
    HKEY hKey = NULL;
    lstrcpy(szKey, "SYSTEM\\CurrentControlSet\\Services\\EventLog\\Application\\");
    lstrcat(szKey, m_lpszServiceName);
    if (::RegOpenKey(HKEY_LOCAL_MACHINE, szKey, &hKey) == ERROR_SUCCESS) 
	{
		::RegDeleteKey(hKey, "EventMessageFile");
		::RegDeleteKey(hKey, "TypesSupported");
		::RegCloseKey(hKey);
    }

    if (bDelete)
	{
		::MessageBox(NULL, "Service succesfully uninstalled", m_lpszServiceName, MB_OK | MB_ICONINFORMATION);
        return TRUE;
	}
    ::MessageBox(NULL, "Service could not be deleted", m_lpszServiceName, MB_OK | MB_ICONSTOP);
	return FALSE;
}


/********************************************************************/
/*																	*/
/* Function name: StartService										*/
/* Description  : Start the service.								*/
/*																	*/
/********************************************************************/
BOOL CNTService::StartService() 
{
	BOOL bResult = FALSE;

	SC_HANDLE hServiceManager = ::OpenSCManager(NULL, NULL, SC_MANAGER_ALL_ACCESS);
	if(hServiceManager) 
	{
		SC_HANDLE hService = ::OpenService(hServiceManager, m_lpszServiceName, SERVICE_ALL_ACCESS);

		if(hService) 
		{
			// start the service
			if(::StartService(hService, 0, 0)) 
			{
				Sleep(1000);
				// check service status
				while(::QueryServiceStatus(hService, &m_ServiceStatus)) 
				{
					// already started ?
					if(m_ServiceStatus.dwCurrentState == SERVICE_START_PENDING) 
					{
						Sleep(1000);
					} 
					else
						break;
				}

				if(m_ServiceStatus.dwCurrentState == SERVICE_RUNNING)
				{
					bResult = TRUE;
				}
			} 
			::CloseServiceHandle(hService);
		} 
        ::CloseServiceHandle(hServiceManager);
    } 
	return bResult;
}


/********************************************************************/
/*																	*/
/* Function name: StopService										*/
/* Description  : Stop a running service.							*/
/*																	*/
/********************************************************************/
BOOL CNTService::StopService() 
{
	BOOL bResult = FALSE;

	SC_HANDLE hServiceManager = ::OpenSCManager(NULL, NULL, SC_MANAGER_ALL_ACCESS);
	if(hServiceManager) 
	{
		SC_HANDLE hService = ::OpenService(hServiceManager, m_lpszServiceName, SERVICE_ALL_ACCESS);

		if(hService) 
		{
			// stop the service
			if(::ControlService(hService, SERVICE_CONTROL_STOP, &m_ServiceStatus)) 
			{
				::Sleep(1000);
				// check service status
				while(::QueryServiceStatus(hService, &m_ServiceStatus)) 
				{
					// already stopped ?
					if(m_ServiceStatus.dwCurrentState == SERVICE_STOP_PENDING) 
					{
						::Sleep(1000);
					} 
					else
						break;
				}

				if(m_ServiceStatus.dwCurrentState == SERVICE_STOPPED)
				{
					bResult = TRUE;
				}
			}

			::CloseServiceHandle(hService);
		} 
        ::CloseServiceHandle(hServiceManager);
    } 
	return bResult;
}


/********************************************************************/
/*																	*/
/* Function name: Run												*/
/* Description  : Do nothing by default.							*/
/*																	*/
/********************************************************************/
void CNTService::Run()
{
    LogEvent("Service started");
}


/********************************************************************/
/*																	*/
/* Function name: Stop												*/
/* Description  : Do nothing by default.							*/
/*																	*/
/********************************************************************/
void CNTService::Stop()
{
    LogEvent("Service stopped");
}


/********************************************************************/
/*																	*/
/* Function name: Pause												*/
/* Description  : Do nothing by default.							*/
/*																	*/
/********************************************************************/
void CNTService::Pause() 
{
    LogEvent("Service paused");
}


/********************************************************************/
/*																	*/
/* Function name: Continue											*/
/* Description  : Do nothing by default.							*/
/*																	*/
/********************************************************************/
void CNTService::Continue() 
{
    LogEvent("Service continued");
}


/********************************************************************/
/*																	*/
/* Function name: Shutdown											*/
/* Description  : Do nothing by default								*/
/*																	*/
/********************************************************************/
void CNTService::Shutdown() 
{
    LogEvent("Service shutdown");
}


/********************************************************************/
/*																	*/
/* Function name: ServiceMain										*/
/* Description  : This function is the entry point for the service.	*/
/*																	*/
/********************************************************************/
void WINAPI CNTService::ServiceMain(DWORD /* dwArgc */, LPTSTR* /* lpszArgv */) 
{
	// register service control handler
	g_pTheService->m_hStatusHandle = RegisterServiceCtrlHandler(g_pTheService->m_lpszServiceName, CNTService::Handler);

	if(g_pTheService->m_hStatusHandle == NULL)
	{
        g_pTheService->LogEvent("Handler not installed");
        return;
	}
	
	// report the status to service control manager
	g_pTheService->SetServiceStatus(SERVICE_START_PENDING);
	
	g_pTheService->m_ServiceStatus.dwWin32ExitCode = S_OK;
	g_pTheService->m_ServiceStatus.dwCheckPoint = 0;
	g_pTheService->m_ServiceStatus.dwWaitHint = 0;
		
	// When the Run function returns, the service has stopped.		
	g_pTheService->Run();

	g_pTheService->SetServiceStatus(SERVICE_STOPPED);
}


/********************************************************************/
/*																	*/
/* Function name: Handler											*/
/* Description  : Process events from Service Manager.				*/
/*																	*/
/********************************************************************/
void WINAPI CNTService::Handler(DWORD dwControl)
{
	AFX_MANAGE_STATE(AfxGetStaticModuleState());

	switch(dwControl)
	{
		case SERVICE_CONTROL_STOP:
			g_pTheService->m_ServiceStatus.dwCurrentState = SERVICE_STOP_PENDING;
			g_pTheService->Stop();
			break;

		case SERVICE_CONTROL_PAUSE:
			g_pTheService->m_ServiceStatus.dwCurrentState = SERVICE_PAUSE_PENDING;
			g_pTheService->Pause();
			break;

		case SERVICE_CONTROL_CONTINUE:
			g_pTheService->m_ServiceStatus.dwCurrentState = SERVICE_CONTINUE_PENDING;
			g_pTheService->Continue();
			break;

		case SERVICE_CONTROL_INTERROGATE:
			// Update the service status.
			g_pTheService->SetServiceStatus(g_pTheService->m_ServiceStatus.dwCurrentState);
			break;

		case SERVICE_CONTROL_SHUTDOWN:
			g_pTheService->Shutdown();
			break;

		default:
			g_pTheService->LogEvent("Bad service request");
			break;
    }
}


/********************************************************************/
/*																	*/
/* Function name: SetServiceStatus									*/
/* Description  : Report status to the service-control-manager.		*/
/*																	*/
/********************************************************************/
BOOL CNTService::SetServiceStatus(DWORD dwState, DWORD dwWaitHint) 
{
	BOOL bResult = TRUE;

    if(dwState == SERVICE_START_PENDING)
	{
        m_ServiceStatus.dwControlsAccepted = 0;
	}
    else
	{
        m_ServiceStatus.dwControlsAccepted = SERVICE_ACCEPT_STOP;
	}
    
	m_ServiceStatus.dwCurrentState = dwState;
    m_ServiceStatus.dwWin32ExitCode = NO_ERROR;
    m_ServiceStatus.dwWaitHint = dwWaitHint;

    // Report the status to service control manager.
    if (!(bResult = ::SetServiceStatus(m_hStatusHandle, &m_ServiceStatus))) 
	{
        LogEvent("SetServiceStatus() failed");
    }
	else
	{
		switch(dwState)
		{
			case SERVICE_RUNNING:
				LogEvent("Service started");
				break;
			case SERVICE_STOPPED:
				LogEvent("Service stopped");
				break;
			default:
				break;
		}
	}
    return bResult;
}


/********************************************************************/
/*																	*/
/* Function name: LogEvent											*/
/* Description  : Write event to Windows Event log.					*/
/*																	*/
/********************************************************************/
void CNTService::LogEvent(LPCTSTR pFormat, ...)
{
    char szMsg[256];
    HANDLE  hEventSource;
    LPTSTR  lpszStrings[1];
    va_list pArg;

    va_start(pArg, pFormat);
    _vstprintf(szMsg, pFormat, pArg);
    va_end(pArg);

    lpszStrings[0] = szMsg;

	// Get a handle to use with ReportEvent().
    hEventSource = RegisterEventSource(NULL, m_lpszServiceName);
    if (hEventSource != NULL)
    {
		// Write to event log.
        ReportEvent(hEventSource, EVENTLOG_INFORMATION_TYPE, 0, MSG_INFORMATION, NULL, 1, 0, (LPCTSTR*) &lpszStrings[0], NULL);
        DeregisterEventSource(hEventSource);
	}
}
