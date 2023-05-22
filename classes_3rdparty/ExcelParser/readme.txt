							Excel Parser Professional

INSTALLATION
------------

To install and use this script you need only installed and properly configured
PHP. Script was tested on PHP vesion >= 4.3.x.


DESCRIPTION
-----------

This parser can import data from Excel file versions from Excel 5.0 to
Excel 2000 and XP. Only string and numbers will be imported. String can be in
compressed unicode (8-bits per character) or uncompressed unicode (16-bits per
character) formats.

NEW OBJECT CREATION
-------------------

requires 'excelparser.php';

$excel = new ExcelFileParser( $logfilename, $logtype );

$logfilename - optional parameter (filename for debug logging). Default value - empty string, no logging.
$logtype - log severity. Default value ABC_NO_LOG - disable logging.
			Log type can be one of the following:
				ABC_CRITICAL
				ABC_ERROR
				ABC_ALERT
				ABC_WARNING
				ABC_NOTICE
				ABC_INFO
				ABC_DEBUG
				ABC_TRACE
				ABC_VAR_DUMP
				ABC_NO_LOG
			Bitwise combination of these flags is not allowed.
			You can use only one of them.

Two methods are used for processing Excel data:
	ParseFromFile( $filename )
		This function is optimized for memory usage, but the script takes more times
		to be completely executed and to parse the selected data. Use with large files.
		
	ParseFromString( $contents )
		This function is not optimized for memory usage, but the script takes less times
		to be completely executed and to parse the selected data. Use when parsing speed
		is critical (PHP memory limit can be exceed for huge files and script will terminate!).		

Examples:
$error_code = $excel->ParseFromFile($filename)

$fd = fopen( $filename, 'rb');
$content = fread ($fd, filesize ($name));
fclose($fd);
$error_code = $excel->ParseFromString($content);
unset( $content, $fd );

Errors codes:
	0 - no errors
	1 - file read error
	2 - file is too small to be an Excel file
	3 - Excel file head read error
	4 - file read error
	5 - not Excel file or Excel version earlier than Excel 5.0
	6 - corrupted file
	7 - data not found
	8 - unknown file version

Note: Error 7 is displayed when no Excel data is found in OLE2 file format (like MS Word) 

OBJECT FIELDS
--------------

int $excel->biff_version
Excel file BIFF version (7 = Excel 5-7, 8 = 2000, 10 = XP) 

array $excel->worksheet
An array, containing lists data 

array $excel->format 
An array, containing data about the styles used to format cell 

TABLES
------

$worksheet_number = Excel worksheet number - to view the quantity of accessible worksheets, 
use count($excel->worksheet['name'])

boolean $excel->worksheet['unicode'][$worksheet_number]
If the data is saved in uncompressed unicode, then ithis field has TRUE value, FALSE is used for compressed unicode 


string $excel->worksheet['name'][$worksheet_number]
Worksheet name in compressed or uncompressed unicode 

int $excel->worksheet['data'][$worksheet_number]['biff_version']
BIFF list version 

COLUMNS 
-------

array $exc->worksheet['data'][$worksheet_number]['cell']
An array containing the cells data for the chosen worksheet. 

$row = row number (begins from 0)
$col = column number (begins from 0)
In Excel the columns have names as 'A', 'B', 'C', etc 

int $exc->worksheet['data'][$worksheet_number]['max_row']
Maximum row number (! but not maximum row quantity!). For example, the list has data in rows 1,3,8 - maximum value 8, quantity of rows- 3 

int $exc->worksheet['data'][$worksheet_number]['max_col']
Maximum column number (the same as for rows, see supra) 

int $exc->worksheet['data'][$worksheet_number]['cell'][$row][$col]['type']
The data type, contained in the cell: 

0 - string
1 - integer
2 - float
3 - date

mixed $exc->worksheet['data'][$worksheet_number]['cell'][$row][$col]['data']
if data type is 0, cell contains string index in SST 
if data type is 1, cell contains integer 
if data type is 2, cell contains float 
if data type is 3, cell contains date in Excel presentation 

int $exc->worksheet['data'][$worksheet_number]['cell'][$row][$col]['font'] -
contains the index of font used in the cell


FONTS
-----

The fonts that are used in workbook are stored in $excel->fonts array.
They look in the following way:


$font = $excel->fonts[$index];

$font['size'] - font size in points
$font['italic']   - is font italic true/false
$font['strikeout'] is font strikeout true/false
$font['bold']      is font bold true/false   
$font['script']  -  may have the following constant values:
		 XF_SCRIPT_NONE - normal font.
		 XF_SCRIPT_SUPERSCRIPT - SUPERSCRIPT inscription is set.
		 XF_SCRIPT_SUBSCRIPT - SUPERSCRIPT inscription is set. 
	    
$font['underline']  - may have the following constant values:
		 XF_UNDERLINE_NONE - normal font.
		 XF_UNDERLINE_SINGLE - single underlining is set.
		 XF_UNDERLINE_DOUBLE - double underlining is set.
 		 XF_UNDERLINE_SINGLE_ACCOUNTING - underline single accounting is set.
		 XF_UNDERLINE_DOUBLE_ACCOUNTING - underline double accounting is set.
$font['name']    - fonts name.


Shared String Table (SST): 
--------------------------
All string data is saved in this table to optimize the memory use of computer. The cells do not contain the strings itself, but the strings indexs in this table. 

The access to the indexs is performed by: 


if( $excel->worksheet['data'][$worksheet_number]['cell'][$row][$col]['type'] == 0 )
{
$ind = $exc->worksheet['data'][$worksheet_number]['cell'][$row][$col]['data'];
}

array $excel->sst
An array, containing strings 

boolean $excel->sst['unicode'][$ind]
Logical value, showing whether the data is presented in uncompressed 
(TRUE) or compressed (FALSE) unicode 

string $excel->sst['data'][$ind] 
String data 

DATE
----

The date is saved in Excel format - (the number of days starting from year 1900)
and might be converted in timestamp 

$excel->xls2tstamp($xlsdate)

You may work with returned data using common unix timestamp 
and format it with standart toolset for date in PHP. 
Because on the Windows platform the minimum timestamp value
(null second) is 1,1,1970 this function processes correctly only
the following dates.

If you need to process the earlier data, then use the function:

$ret = $excel->getDateArray($xlsdate);

where:

$ret['day']   = day of month.
$ret['month'] = month.
$ret['year']  = year.

DEBUG
------ 
In order to make parser to write a logfile - the class must be created with the following parameters:
ExcelFileParser('logfile.txt',LOG_LEVEL);

LOG_LEVEL may have the following values:
 
ABC_CRITICAL
ABC_ERROR   
ABC_ALERT
ABC_WARNING
ABC_NOTICE
ABC_INFO
ABC_DEBUG
ABC_TRACE
ABC_VAR_DUMP 

The lower the level, the more messages will be put in log by parser.

With ABC_NO_LOG - no logging will performed.


EXAMPLE
-------

 See sample.php file for working example.


THINGS MISSED IN THIS RELEASE
-----------------------------

There is some restrictions in parser. In this version hidden flag of columns,
rows and worksheets are not processed. Parser not understanding some cell formats
for now. Author plans to add this things in future versions.


BUGS
----
Waiting for your comments :)
