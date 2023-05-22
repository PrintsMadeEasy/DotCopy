
CURRENT_YEAR=`set \`date\`; echo ${6}`
CURRENT_DAY=`set \`date\`; echo ${3}`
CURRENT_MONTH=`set \`date "+%m"\`; echo ${1}`


THIS_MONTH_REPORT_URL="http://www.printsmadeeasy.com/ad_report_month.php?month="${CURRENT_MONTH}"&year="${CURRENT_YEAR}"&doNotCacheReport=true&CacheReportOnBehalfOfUserID=2"

lynx -connect_timeout=3000 -dump ${THIS_MONTH_REPORT_URL}
