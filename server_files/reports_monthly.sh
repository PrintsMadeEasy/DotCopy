
LAST_MONTH_YEAR=`set \`date --date "31 days ago"\`; echo ${6}`
LAST_MONTH_MONTH=`set \`date --date "31 days ago" "+%m"\`; echo ${1}`

MONTHS_2_YEAR=`set \`date --date "62 days ago"\`; echo ${6}`
MONTHS_2_MONTH=`set \`date --date "62 days ago" "+%m"\`; echo ${1}`

echo "Last Month Report"

LAST_MONTH_REPORT_URL="http://www.printsmadeeasy.com/ad_report_month.php?month="${LAST_MONTH_MONTH}"&year="${LAST_MONTH_YEAR}"&doNotCacheReport=true&CacheReportOnBehalfOfUserID=2"
lynx -connect_timeout=3000 -dump ${LAST_MONTH_REPORT_URL}

echo "2 Months Ago Month Report"

MONTHS_2_REPORT_URL="http://www.printsmadeeasy.com/ad_report_month.php?month="${MONTHS_2_MONTH}"&year="${MONTHS_2_YEAR}"&doNotCacheReport=true&CacheReportOnBehalfOfUserID=2"
lynx -connect_timeout=3000 -dump ${MONTHS_2_REPORT_URL}


