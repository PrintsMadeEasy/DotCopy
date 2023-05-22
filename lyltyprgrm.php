<?

require_once("library/Boot_Session.php");

$t = new Templatex();
$t->set_file("origPage", "lyltyprgrm-template.html");

$loyaltyObj = new LoyaltyProgram(Domain::getDomainIDfromURL());


VisitorPath::addRecord("Super Saver Program");

$passiveAuthObj = Authenticate::getPassiveAuthObject();
if($passiveAuthObj->CheckIfLoggedIn()){
	$t->set_var("MONTLY_FEE", $loyaltyObj->getMontlyFee($passiveAuthObj->GetUserID()));
	
	if(!LoyaltyProgram::displayLoyalityOptionForVisitor($passiveAuthObj->GetUserID()))
		WebUtil::print404Error();
}
else{
	$t->set_var("MONTLY_FEE", $loyaltyObj->getMontlyFee());
	
	if(!LoyaltyProgram::displayLoyalityOptionForVisitor())
		WebUtil::print404Error();
}
	

$t->pparse("OUT","origPage");


