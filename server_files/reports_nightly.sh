
CURRENT_YEAR=`set \`date\`; echo ${6}`
CURRENT_DAY=`set \`date\`; echo ${3}`
CURRENT_MONTH=`set \`date "+%m"\`; echo ${1}`


# Running the Report for Yesteday is different than running it for "1 days ago" because the HTML rendered will have the Drop down selected for "Yesterday" instead of a date range.
YESTERDAY_MARKETING_REPORT_URL="http://www.printsmadeeasy.com/ad_marketing_report.php?doNotCacheReport=true&view=orders&PeriodType=TIMEFRAME&TimeFrame=YESTERDAY&productlimit=0&CacheReportOnBehalfOfUserID=2"

echo "Running Yesterday Marketing Report"
lynx -connect_timeout=3000 -dump ${YESTERDAY_MARKETING_REPORT_URL}



# Run the Marketing Report for 15 days behind yesterday.
i=2
while test $i != 18
do

	
	XDAYS_AGO_YEAR=`set \`date --date "${i} days ago"\`; echo ${6}`
	XDAYS_AGO_DAY=`set \`date --date "${i} days ago"\`; echo ${3}`
	XDAYS_AGO_MONTH=`set \`date --date "${i} days ago" "+%m"\`; echo ${1}`

	echo "Marketing Report from ${i} days ago"

	XDAYS_AGO_MARKETING_REPORT_URL="lynx -dump http://www.printsmadeeasy.com/ad_marketing_report.php?view=orders&doNotCacheReport=true&productlimit=0&PeriodType=DATERANGE&DateRangeStartMonth="${XDAYS_AGO_MONTH}"&DateRangeStartDay="${XDAYS_AGO_DAY}"&DateRangeStartYear="${XDAYS_AGO_YEAR}"&DateRangeEndMonth="${XDAYS_AGO_MONTH}"&DateRangeEndDay="${XDAYS_AGO_DAY}"&DateRangeEndYear="${XDAYS_AGO_YEAR}"&CacheReportOnBehalfOfUserID=2"

	lynx -connect_timeout=3000 -dump ${XDAYS_AGO_MARKETING_REPORT_URL}

	i=`expr $i + 1`
done



// Run the Month report with balance adjustments
THIS_MONTH_REPORT_URL="http://www.printsmadeeasy.com/ad_report_month.php?month="${CURRENT_MONTH}"&year="${CURRENT_YEAR}"&doNotCacheReport=true&CacheReportOnBehalfOfUserID=2&showadjustments=true"

lynx -connect_timeout=3000 -dump ${THIS_MONTH_REPORT_URL}


