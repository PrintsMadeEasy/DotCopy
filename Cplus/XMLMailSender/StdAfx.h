#if !defined(AFX_STDAFX_H__73EEC3B1_13B9_4633_803D_AB39C9BE422C__INCLUDED_)
#define AFX_STDAFX_H__73EEC3B1_13B9_4633_803D_AB39C9BE422C__INCLUDED_

#if _MSC_VER > 1000
#pragma once
#endif // _MSC_VER > 1000

#define VC_EXTRALEAN		// Selten verwendete Teile der Windows-Header nicht einbinden

#include "Specstrings.h"
/*
  Dnsapi und windns.h are included in the Windows SDK

  Installation here:

  http://www.microsoft.com/downloads/details.aspx?FamilyId=A55B6B43-E24F-4EA3-A93E-40C0EC4F68E5&displaylang=en

  In den Directories (Extras->Optionen-Verzeichnisse)

  Include: C:\PROGRAM FILES\MICROSOFT SDKS\WINDOWS\V6.1\INCLUDE
  Include: C:\PROGRAM FILES\MICROSOFT VISUAL STUDIO 9.0\VC\INCLUDE
  Lib    : C:\PROGRAM FILES\MICROSOFT SDKS\WINDOWS\V6.1\LIB	


  If you get the "__in" error:

  Add in StdAfx.h after :#define VC_EXTRALEAN
 
  #include "Specstrings.h"

*/

#define XML_MAX_SIZE 500000
#define DYNAMEMORY
#define ZLIB_PRESENT

#include <afxwin.h>         // MFC-Kern- und -Standardkomponenten
#include <afxext.h>         // MFC-Erweiterungen
#include <afxdtctl.h>		// MFC-Unterstützung für allgemeine Steuerelemente von Internet Explorer 4
#ifndef _AFX_NO_AFXCMN_SUPPORT
#include <afxcmn.h>			// MFC-Unterstützung für gängige Windows-Steuerelemente
#endif // _AFX_NO_AFXCMN_SUPPORT

#include <winsock2.h>

#include <AfxInet.h>

//{{AFX_INSERT_LOCATION}}
// Microsoft Visual C++ fügt unmittelbar vor der vorhergehenden Zeile zusätzliche Deklarationen ein.

#endif // !defined(AFX_STDAFX_H__73EEC3B1_13B9_4633_803D_AB39C9BE422C__INCLUDED_)
