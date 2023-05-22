<?

require_once("../library/Boot_Session.php");
/*
$inputArr[] = "aaaa";
$inputArr[] = "aaaa";
$inputArr[] = "aaaa";
$inputArr[] = "aaaa";
$inputArr[] = "aaaa";
$inputArr[] = "aaaa";
$inputArr[] = "aaaa";
$inputArr[] = "aaaa";
$inputArr[] = "aaaa";
$inputArr[] = "aaaa";
$inputArr[] = "aaaa";
$inputArr[] = "aaaa";
$inputArr[] = "bbbbbbbb";
$inputArr[] = "bbbbbbbb";
$inputArr[] = "bbbbbbbb";
$inputArr[] = "bbbbbbbb";
$inputArr[] = "bbbbbbbb";
$inputArr[] = "bbbbbbbb";
$inputArr[] = "ggggggggggggggggggggg";
$inputArr[] = "ggggggggggggggggggggg";
$inputArr[] = "ggggggggggggggggggggg";
$inputArr[] = "ggggggggggggggggggggg";
$inputArr[] = "ggggggggggggggggggggg";
$inputArr[] = "b";
$inputArr[] = "c";
$inputArr[] = "d";
$inputArr[] = "d";
$inputArr[] = "e";
$inputArr[] = "f";
$inputArr[] = "f";
$inputArr[] = "g";
$inputArr[] = "h";
$inputArr[] = "h";
$inputArr[] = "j";
$inputArr[] = "k";
$inputArr[] = "k";
$inputArr[] = "k";
$inputArr[] = "k";
$inputArr[] = "l";
$inputArr[] = "m";
$inputArr[] = "n";
$inputArr[] = "o";
$inputArr[] = "o";
$inputArr[] = "o";
$inputArr[] = "o";
$inputArr[] = "p";
$inputArr[] = "q";
$inputArr[] = "q";
$inputArr[] = "q";
$inputArr[] = "q";
$inputArr[] = "q";
$inputArr[] = "q";
$inputArr[] = "q";
$inputArr[] = "q";
$inputArr[] = "r";
$inputArr[] = "s";
$inputArr[] = "t";
$inputArr[] = "t";
$inputArr[] = "t";
$inputArr[] = "t";
$inputArr[] = "u";
$inputArr[] = "v";
$inputArr[] = "w";
$inputArr[] = "x";
$inputArr[] = "x";
$inputArr[] = "y";
$inputArr[] = "y";
$inputArr[] = "ddddddddddddd";
$inputArr[] = "z";
$inputArr[] = "z";
*/


$inputArr[] = "aaaa";
$inputArr[] = "aaaa";
$inputArr[] = "aaaa";
$inputArr[] = "aaaa";
$inputArr[] = "aaaa";
$inputArr[] = "bbbbbbbb";
$inputArr[] = "bbbbbbbb";
$inputArr[] = "bbbbbbbb";
$inputArr[] = "bbbbbbbb";
$inputArr[] = "bbbbbbbb";
$inputArr[] = "bbbbbbbb";
$inputArr[] = "ggggggggggggggggggggg";
$inputArr[] = "ggggggggggggggggggggg";
$inputArr[] = "ggggggggggggggggggggg";
$inputArr[] = "y";
$inputArr[] = "y";
$inputArr[] = "ddddddddddddd";
$inputArr[] = "ddddddddddddd";
$inputArr[] = "z";
$inputArr[] = "z";



$dataArr[] = "=X=aaaa";
$dataArr[] = "=X=aaaa";
$dataArr[] = "=X=aaaa";
$dataArr[] = "=X=aaaa";
$dataArr[] = "=X=aaaa";
$dataArr[] = "=X=bbbbbbbb";
$dataArr[] = "=X=bbbbbbbb";
$dataArr[] = "=X=bbbbbbbb";
$dataArr[] = "=X=bbbbbbbb";
$dataArr[] = "=X=bbbbbbbb";
$dataArr[] = "=X=bbbbbbbb";
$dataArr[] = "=X=ggggggggggggggggggggg";
$dataArr[] = "=X=ggggggggggggggggggggg";
$dataArr[] = "=X=ggggggggggggggggggggg";
$dataArr[] = "=X=y";
$dataArr[] = "=X=y";
$dataArr[] = "=X=ddddddddddddd";
$dataArr[] = "=X=ddddddddddddd";
$dataArr[] = "=X=z";
$dataArr[] = "=X=z";



// If we had a list of 100...
// On the first iteration within the While() loop... the newSlotPosition would be 0,10,20,30,40,50,60,70,80,90 ... within each iteration of the For() loop
// On the next iteration of the While () loop... the newSlotPosition would try to set a value an increments of 5, such as 0,5,10,15,20,etc.... within each iteration of the For() loop...
// ... however half of the spots would be taken up (10,20,30,etc)... so in effect the second iteration of the while() loop would end up inserting at 5,15,25,35,45,55,65,75,85,95
// The spacing itveral would then get cut in half again... down to 2.5 (or ceiled up to 3)... then it would get cut in half again (ceiled to 2).
// On the last iteration of the While() loop the spacing interval would be 1... so it will try to fill up every single position... within each iteration of the For() loop.


$listLength = sizeof($inputArr);
print $listLength . " Entries <hr>";


$deClusteredArr = WebUtil::arrayDecluster($inputArr, $dataArr);

print "De cluster test<hr>";
foreach($deClusteredArr as $thisSortedResult){

	print $thisSortedResult . "<br>";
}







