#if !defined(AFX_MYSERVICE_H__97D9A26A_A0B4_11D3_8FB3_009027ACF691__INCLUDED_)
#define AFX_MYSERVICE_H__97D9A26A_A0B4_11D3_8FB3_009027ACF691__INCLUDED_

#if _MSC_VER >= 1000
#pragma once
#endif // _MSC_VER >= 1000

#include "stdafx.h"
#include "NTService.h"

class CMyService : public CNTService
{
	public:	// construction
		CMyService();

	public:	// overridables
		virtual void Run();
		virtual void Stop();

	private:
		HANDLE	m_hStop;

	// Generated message map functions
	//{{AFX_MSG(CMyService)
	afx_msg void OnTimer(UINT nIDEvent);
	//}}AFX_MSG
	LRESULT OnStopService(WPARAM wParam, LPARAM lParam);
	DECLARE_MESSAGE_MAP()
};

#endif // !defined(AFX_MYSERVICE_H__97D9A26A_A0B4_11D3_8FB3_009027ACF691__INCLUDED_)
