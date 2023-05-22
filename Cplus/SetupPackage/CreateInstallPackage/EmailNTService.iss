; SEE THE DOCUMENTATION FOR DETAILS ON CREATING .ISS SCRIPT FILES!

[Setup]
AppName=PME Mailer
AppVerName=PMEMailer v1.00
DefaultDirName={pf}\PMEMailer-100
DefaultGroupName=PMEMailer
UninstallDisplayIcon={app}\PMEMailer.exe
Compression=lzma
SolidCompression=yes
LicenseFile="licence.txt"
AppCopyright=(c) 2009 PrintsMadeEasy.com
OutputBaseFileName="PMEMailerSetup"

[Messages]
PasswordLabel1=Please enter the Password.

[Files]
Source: "A-Mail.exe"; DestDir: "{app}"
Source: "licence.txt"; DestDir: "{app}"
Source: "Readme.txt"; DestDir: "{app}"; Flags: isreadme
Source: "InstallService.bat"; DestDir: "{app}"
Source: "Uninstall.bat"; DestDir: "{app}"
Source: "StartService.bat"; DestDir: "{app}"
Source: "StopService.bat"; DestDir: "{app}"
Source: "config.txt"; DestDir: "{app}"
Source: "emailposition.txt"; DestDir: "{app}"
Source: "iteration.txt"; DestDir: "{app}"
Source: "pop3log.txt"; DestDir: "{app}"
Source: "smtpserverlog.txt"; DestDir: "{app}"
[Run]
Filename: "{app}\InstallService.bat";  Flags: skipifsilent
