// stdafx.h : include file for standard system include files,
//  or project specific include files that are used frequently, but
//      are changed infrequently


#if !defined(AFX_STDAFX_H__B7C54BCB_A555_11D0_8996_00AA00B92B2E__INCLUDED_)
#define AFX_STDAFX_H__B7C54BCB_A555_11D0_8996_00AA00B92B2E__INCLUDED_

#if _MSC_VER >= 1000
#pragma once
#endif // _MSC_VER >= 1000


#define VC_EXTRALEAN		// Exclude rarely-used stuff from Windows headers

#include "Specstrings.h"

#include <afxwin.h>         // MFC core and standard components
#include <afxext.h>         // MFC extensions

#include <afxmt.h>
#include <winsvc.h>

LPCTSTR FindOneOf(LPCTSTR p1, LPCTSTR p2);

#include <AfxInet.h>

#include <winsock2.h>
#pragma comment(lib, "ws2_32.lib")

#define XML_MAX_SIZE 500000

//{{AFX_INSERT_LOCATION}}
// Microsoft Developer Studio will insert additional declarations immediately before the previous line.

#endif // !defined(AFX_STDAFX_H__B7C54BCB_A555_11D0_8996_00AA00B92B2E__INCLUDED_)

