###### This could take Quite a While !!!!!  Make sure that the server has fresh batteries.



# Delete the Cache from the Month Reports
`rm -f /home/printsma/ReportCaching/MonthReport*`


# Runs the Month Summary Back to the beggining of 2003
LAST_YEAR=0
i=1
while test $LAST_YEAR != 2002
do
	
	XDAYS_AGO_YEAR=`set \`date --date "${i} days ago"\`; echo ${6}`
	XDAYS_AGO_DAY=`set \`date --date "${i} days ago"\`; echo ${3}`
	XDAYS_AGO_MONTH=`set \`date --date "${i} days ago" "+%m"\`; echo ${1}`

	echo "Month Report For ${XDAYS_AGO_MONTH}/${XDAYS_AGO_YEAR} "

	XMONTHS_AGO_MONTH_REPORT_URL="http://www.printsmadeeasy.com/ad_report_month.php?month="${XDAYS_AGO_MONTH}"&year="${XDAYS_AGO_YEAR}"&doNotCacheReport=true&CacheReportOnBehalfOfUserID=2"

	lynx -connect_timeout=3000 -dump ${XMONTHS_AGO_MONTH_REPORT_URL}
	
	LAST_YEAR=$XDAYS_AGO_YEAR

	i=`expr $i + 29`
done






# Delete the Cache from the Marketing Reports
`rm -f /home/printsma/ReportCaching/MarketingOrders*`


# Run the Marketing Report for every day going back to the beggining of 2003
LAST_YEAR=0
i=2
while test $LAST_YEAR != 2002
do
	
	XDAYS_AGO_YEAR=`set \`date --date "${i} days ago"\`; echo ${6}`
	XDAYS_AGO_DAY=`set \`date --date "${i} days ago"\`; echo ${3}`
	XDAYS_AGO_MONTH=`set \`date --date "${i} days ago" "+%m"\`; echo ${1}`

	echo "Marketing Report from ${i} days ago"

	XDAYS_AGO_MARKETING_REPORT_URL="lynx -dump http://www.printsmadeeasy.com/ad_marketing_report.php?view=orders&doNotCacheReport=true&productlimit=0&PeriodType=DATERANGE&DateRangeStartMonth="${XDAYS_AGO_MONTH}"&DateRangeStartDay="${XDAYS_AGO_DAY}"&DateRangeStartYear="${XDAYS_AGO_YEAR}"&DateRangeEndMonth="${XDAYS_AGO_MONTH}"&DateRangeEndDay="${XDAYS_AGO_DAY}"&DateRangeEndYear="${XDAYS_AGO_YEAR}"&CacheReportOnBehalfOfUserID=2"

	lynx -connect_timeout=3000 -dump ${XDAYS_AGO_MARKETING_REPORT_URL}
	
	LAST_YEAR=$XDAYS_AGO_YEAR

	i=`expr $i + 1`
done





