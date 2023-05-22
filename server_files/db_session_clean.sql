
DELETE FROM projectssession
 WHERE TO_DAYS(NOW()) - TO_DAYS(DateLastModified) > 2
;

DELETE FROM shoppingcart
 WHERE TO_DAYS(NOW()) - TO_DAYS(DateLastModified) > 2
;
