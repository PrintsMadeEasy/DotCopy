#-- Will calculate payments on the last day of the month and on the 15th
#-- There is not a good way for the cron to detect the last day of the month, so fire this script every night.

TOMORROW=`date -d tomorrow '+%d'`

if [ $TOMORROW == "01" ] || [ $TOMORROW == "16" ]
then
  echo Preparing Payment
  `lynx -dump http://www.PrintsMadeEasy.com/paypal_masspay_prepare.php?domainID=1`
fi
