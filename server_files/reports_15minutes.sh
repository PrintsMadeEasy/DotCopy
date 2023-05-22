TODAY_MARKETING_REPORT_URL="http://www.printsmadeeasy.com/ad_marketing_report.php?view=orders&doNotCacheReport=true&PeriodType=TIMEFRAME&TimeFrame=TODAY&productlimit=0&CacheReportOnBehalfOfUserID=2"

lynx -connect_timeout=3000 -dump ${TODAY_MARKETING_REPORT_URL}
