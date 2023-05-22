#if !defined(AFX_NTSERVICE_H__877EDF90_B49E_44B3_8B1A_480454DDC42F__INCLUDED_)
#define AFX_NTSERVICE_H__877EDF90_B49E_44B3_8B1A_480454DDC42F__INCLUDED_

#if _MSC_VER > 1000
#pragma once
#endif // _MSC_VER > 1000

class CNTService : public CWnd
{
	protected:
		LPCTSTR					m_lpszServiceName;
		LPCTSTR					m_lpszDisplayName;
		SERVICE_STATUS			m_ServiceStatus;
		SERVICE_STATUS_HANDLE	m_hStatusHandle;

	public:
		CNTService(LPCTSTR lpszServiceName, LPCTSTR lpszDisplayName = NULL);
		virtual ~CNTService();

	private:
		CNTService(const CNTService &);
		CNTService & operator=(const CNTService &);

	public:		// overridables
		virtual void Run();
		virtual void Stop();
		virtual void Pause();
		virtual void Continue();
		virtual void Shutdown();

		virtual BOOL StartDispatcher();
		virtual BOOL InstallService();
		virtual BOOL UninstallService();
		virtual BOOL StartService();
		virtual BOOL StopService();
		virtual BOOL ExecuteService();

	// ClassWizard generated virtual function overrides
	//{{AFX_VIRTUAL(CNTService)
	//}}AFX_VIRTUAL

	protected:	// implementation
		BOOL IsInstalled();

	public:
		BOOL SetServiceStatus(DWORD dwState, DWORD dwWaitHint = 3000);

	public:
		static void WINAPI ServiceMain(DWORD dwArgc, LPTSTR* lpszArgv);
		static void WINAPI Handler(DWORD dwControl);

		// logging
		void LogEvent(LPCTSTR pFormat, ...);

	// Generated message map functions
protected:
	//{{AFX_MSG(CNTService)
		// NOTE - the ClassWizard will add and remove member functions here.
	//}}AFX_MSG
	DECLARE_MESSAGE_MAP()
};

/////////////////////////////////////////////////////////////////////////////

//{{AFX_INSERT_LOCATION}}
// Microsoft Visual C++ will insert additional declarations immediately before the previous line.

#endif // !defined(AFX_NTSERVICE_H__877EDF90_B49E_44B3_8B1A_480454DDC42F__INCLUDED_)
