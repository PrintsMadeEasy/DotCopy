<?php

require_once("library/Boot_Session.php");


set_time_limit(5000);




$dbCmd = new DbCmd();


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

$domainIDsArr = array(Domain::getDomainID("PrintsMadeEasy.com"));

$customerWorthObj = new CustomerWorth($domainIDsArr);
$customerWorthObj->setBalanceAdjustmentsInclusionFlag(true);
$customerWorthObj->setShippingHandlingProfitInclusionFlag(true);


$customerWorthObj->setEndPeriodDaysFromAcqDate(180);



$customerWorthObj->setNewCustomerAcquisitionPeriod(mktime(0,0,0,2,9,2009), mktime(0,0,0,2,23,2009));




$customerWorthObj->setCouponCode("M3121A");


print "Coupon Code <u>M3121A</u><br>";
print "New Customers: " . $customerWorthObj->getUserCountInAcqSpan() . "<br>";
print "Avg Customer Worth: " . $customerWorthObj->getAverageProfitByEndPeriod() . "<br>";
print "Avg Discount on First Order: " . $customerWorthObj->getAverageDiscountOnFirstOrder() . "<br>";				
print "Total Discounts from First Orders: " . $customerWorthObj->getDiscountTotalOnFirstOrders() . "<br>";	

print "<br><br><br>";




$customerWorthObj->setCouponCode("3Z285Y");

print "Coupon Code <u>3Z285Y</u><br>";
print "New Customers: " . $customerWorthObj->getUserCountInAcqSpan() . "<br>";
print "Avg Customer Worth: " . $customerWorthObj->getAverageProfitByEndPeriod() . "<br>";
print "Avg Discount on First Order: " . $customerWorthObj->getAverageDiscountOnFirstOrder() . "<br>";				
print "Total Discounts from First Orders: " . $customerWorthObj->getDiscountTotalOnFirstOrders() . "<br>";	





