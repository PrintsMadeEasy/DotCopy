; CLW-Datei enthält Informationen für den MFC-Klassen-Assistenten

[General Info]
Version=1
LastClass=CEmailGeneratorDlg
LastTemplate=CDialog
NewFileInclude1=#include "stdafx.h"
NewFileInclude2=#include "EmailGenerator.h"

ClassCount=4
Class1=CEmailGeneratorApp
Class2=CEmailGeneratorDlg
Class3=CAboutDlg

ResourceCount=3
Resource1=IDD_ABOUTBOX
Resource2=IDR_MAINFRAME
Resource3=IDD_EMAILGENERATOR_DIALOG

[CLS:CEmailGeneratorApp]
Type=0
HeaderFile=EmailGenerator.h
ImplementationFile=EmailGenerator.cpp
Filter=N

[CLS:CEmailGeneratorDlg]
Type=0
HeaderFile=EmailGeneratorDlg.h
ImplementationFile=EmailGeneratorDlg.cpp
Filter=D
BaseClass=CDialog
VirtualFilter=dWC

[CLS:CAboutDlg]
Type=0
HeaderFile=EmailGeneratorDlg.h
ImplementationFile=EmailGeneratorDlg.cpp
Filter=D

[DLG:IDD_ABOUTBOX]
Type=1
Class=CAboutDlg
ControlCount=4
Control1=IDC_STATIC,static,1342177283
Control2=IDC_STATIC,static,1342308480
Control3=IDC_STATIC,static,1342308352
Control4=IDOK,button,1342373889

[DLG:IDD_EMAILGENERATOR_DIALOG]
Type=1
Class=CEmailGeneratorDlg
ControlCount=3
Control1=IDOK,button,1342242817
Control2=IDCANCEL,button,1342242816
Control3=IDC_BUTTON1,button,1342242816

