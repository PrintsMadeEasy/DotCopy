<?


#-- For info on these properties you will need to understand a little about font creation
#-- http://pfaedit.sourceforge.net/overview.html

#-- To determine the Font Height you would add the ascent to the descent
#-- In order to calculate the actual pixel height, you need rationalize it compared to the units of measurment.

#-- As an example.  If the "unts_per_em" were 1000. and the Font Height was 1000, then a 12pt. font would be 12 pixels high.
#--		    		If the "unts_per_em" were 800. and the Font Height was 1000, then a 12pt. font would be greater than 12 pixels.
#--		    		If the "unts_per_em" were 1000. and the Font Height was 800, then a 12pt. font would be smaller than 12 pixels.

#-- dont forget that the glyph (or character) may not necesarily use the entire font height.


// This function is useful for depreciated fonts or removing the bold/italic cabilities
// We switched to a better way of generating fonts and there is some leftover artwork files having attributes that are not possible on the fonts
function PossiblySubstitueFont($FontName){

	if($FontName == "Eurasiabi")
		return "Eurasiai";
	else if($FontName == "Deckerb")
		return "Decker";	
	else if($FontName == "Karatb")
		return "Karat";
	else if($FontName == "ParkAvenuei")
		return "ParkAvenue";
	else if($FontName == "ParkAvenueb")
		return "ParkAvenue";
	else if($FontName == "Annifontb")
		return "Annifont";
	else if($FontName == "Timeless")
		return "Times";
	else if($FontName == "Amazingb")
		return "Amazing";
	else if($FontName == "Baramondi")
		return "Baramond";
	else if($FontName == "GoodTimesb")
		return "GoodTimes";
	else if($FontName == "Splashb")
		return "Splash";
	else if($FontName == "Venusb")
		return "Venus";
	else if($FontName == "Aborcrestb")
		return "Aborcrest";
	else if($FontName == "Baramondb")
		return "Baramond";
	else if($FontName == "Funhouseb")
		return "Funhouse";	
	else if($FontName == "ChopinScriptb")
		return "ChopinScript";	
	else if($FontName == "Timelessb")
		return "Times";	
	else if($FontName == "Adventureb")
		return "Adventure";	
	else if($FontName == "FinalFrontierb")
		return "FinalFrontier";	
	else if($FontName == "Perspectiveb")
		return "Perspective";	
	else if($FontName == "Englishb")
		return "English";	
	else if($FontName == "Grasshopperb")
		return "Grasshopper";	
	else if($FontName == "Ghostmeatb")
		return "Ghostmeat";	
	else if($FontName == "HollywoodHillsb")
		return "HollywoodHills";
	else if($FontName == "Apologyb")
		return "Apology";
	else if($FontName == "Happyb")
		return "Happy";
	else if($FontName == "KidKosmicb")
		return "KidKosmic";
	else
		return $FontName;
		
		
		
}
global $FntDef;

$FntDef["Baramond"]["ascent"] = 1972;
$FntDef["Baramond"]["descent"] = 549;
$FntDef["Baramond"]["linesp"] = 0;
$FntDef["Baramond"]["unts_per_em"] = 2048;

$FntDef["Eurasia"]["ascent"] = 2093;
$FntDef["Eurasia"]["descent"] = 483;
$FntDef["Eurasia"]["linesp"] = 0;
$FntDef["Eurasia"]["unts_per_em"] = 2048;

$FntDef["Eurasiab"]["ascent"] = 2124;
$FntDef["Eurasiab"]["descent"] = 515;
$FntDef["Eurasiab"]["linesp"] = 0;
$FntDef["Eurasiab"]["unts_per_em"] = 2048;

$FntDef["Eurasiai"]["ascent"] = 2093;
$FntDef["Eurasiai"]["descent"] = 483;
$FntDef["Eurasiai"]["linesp"] = 0;
$FntDef["Eurasiai"]["unts_per_em"] = 2048;

$FntDef["Decker"]["ascent"] = 1972;
$FntDef["Decker"]["descent"] = 483;
$FntDef["Decker"]["linesp"] = 0;
$FntDef["Decker"]["unts_per_em"] = 2048;

$FntDef["Aborcrest"]["ascent"] = 2013;
$FntDef["Aborcrest"]["descent"] = 595;
$FntDef["Aborcrest"]["linesp"] = 0;
$FntDef["Aborcrest"]["unts_per_em"] = 2048;

$FntDef["Karat"]["ascent"] = 931;
$FntDef["Karat"]["descent"] = 226;
$FntDef["Karat"]["linesp"] = 0;
$FntDef["Karat"]["unts_per_em"] = 1000;

$FntDef["Funhouse"]["ascent"] = 909;
$FntDef["Funhouse"]["descent"] = 265;
$FntDef["Funhouse"]["linesp"] = 0;
$FntDef["Funhouse"]["unts_per_em"] = 1000;

$FntDef["NationalFirst"]["ascent"] = 751;
$FntDef["NationalFirst"]["descent"] = 226;
$FntDef["NationalFirst"]["linesp"] = 0;
$FntDef["NationalFirst"]["unts_per_em"] = 1000;

$FntDef["Perspective"]["ascent"] = 1890;
$FntDef["Perspective"]["descent"] = 532;
$FntDef["Perspective"]["linesp"] = 0;
$FntDef["Perspective"]["unts_per_em"] = 2048;

$FntDef["FinalFrontier"]["ascent"] = 1651;
$FntDef["FinalFrontier"]["descent"] = 436;
$FntDef["FinalFrontier"]["linesp"] = 0;
$FntDef["FinalFrontier"]["unts_per_em"] = 2048;

$FntDef["AmericaSans"]["ascent"] = 999;
$FntDef["AmericaSans"]["descent"] = 269;
$FntDef["AmericaSans"]["linesp"] = 9;
$FntDef["AmericaSans"]["unts_per_em"] = 1000;

$FntDef["ImpressedMetal"]["ascent"] = 1909;
$FntDef["ImpressedMetal"]["descent"] = 431;
$FntDef["ImpressedMetal"]["linesp"] = 0;
$FntDef["ImpressedMetal"]["unts_per_em"] = 2444;

$FntDef["Amazing"]["ascent"] = 1999;
$FntDef["Amazing"]["descent"] = 588;
$FntDef["Amazing"]["linesp"] = 0;
$FntDef["Amazing"]["unts_per_em"] = 2048;

$FntDef["Apology"]["ascent"] = 1661;
$FntDef["Apology"]["descent"] = 650;
$FntDef["Apology"]["linesp"] = 0;
$FntDef["Apology"]["unts_per_em"] = 2295;

$FntDef["ChopinScript"]["ascent"] = 930;
$FntDef["ChopinScript"]["descent"] = 426;
$FntDef["ChopinScript"]["linesp"] = 0;
$FntDef["ChopinScript"]["unts_per_em"] = 1000;

$FntDef["AdineKimberg"]["ascent"] = 673;
$FntDef["AdineKimberg"]["descent"] = 301;
$FntDef["AdineKimberg"]["linesp"] = 150;
$FntDef["AdineKimberg"]["unts_per_em"] = 1000;

$FntDef["English"]["ascent"] = 837;
$FntDef["English"]["descent"] = 255;
$FntDef["English"]["linesp"] = 150;
$FntDef["English"]["unts_per_em"] = 1000;

$FntDef["ParkAvenue"]["ascent"] = 875;
$FntDef["ParkAvenue"]["descent"] = 357;
$FntDef["ParkAvenue"]["linesp"] = 150;
$FntDef["ParkAvenue"]["unts_per_em"] = 1000;

$FntDef["Adventure"]["ascent"] = 1499;
$FntDef["Adventure"]["descent"] = 188;
$FntDef["Adventure"]["linesp"] = 0;
$FntDef["Adventure"]["unts_per_em"] = 1425;

$FntDef["Beware"]["ascent"] = 938;
$FntDef["Beware"]["descent"] = 1;
$FntDef["Beware"]["linesp"] = 0;
$FntDef["Beware"]["unts_per_em"] = 1000;

$FntDef["GoodTimes"]["ascent"] = 742;
$FntDef["GoodTimes"]["descent"] = 228;
$FntDef["GoodTimes"]["linesp"] = 0;
$FntDef["GoodTimes"]["unts_per_em"] = 1000;

$FntDef["Venus"]["ascent"] = 742;
$FntDef["Venus"]["descent"] = 228;
$FntDef["Venus"]["linesp"] = 0;
$FntDef["Venus"]["unts_per_em"] = 1000;

$FntDef["Alexis"]["ascent"] = 293;
$FntDef["Alexis"]["descent"] = 81;
$FntDef["Alexis"]["linesp"] = 9;
$FntDef["Alexis"]["unts_per_em"] = 700;

$FntDef["Vibroce"]["ascent"] = 742;
$FntDef["Vibroce"]["descent"] = 293;
$FntDef["Vibroce"]["linesp"] = 0;
$FntDef["Vibroce"]["unts_per_em"] = 1000;

$FntDef["Vibroceb"]["ascent"] = 777;
$FntDef["Vibroceb"]["descent"] = 308;
$FntDef["Vibroceb"]["linesp"] = 0;
$FntDef["Vibroceb"]["unts_per_em"] = 1000;

$FntDef["Vibrocei"]["ascent"] = 742;
$FntDef["Vibrocei"]["descent"] = 293;
$FntDef["Vibrocei"]["linesp"] = 0;
$FntDef["Vibrocei"]["unts_per_em"] = 1000;

$FntDef["Vibrocebi"]["ascent"] = 777;
$FntDef["Vibrocebi"]["descent"] = 308;
$FntDef["Vibrocebi"]["linesp"] = 0;
$FntDef["Vibrocebi"]["unts_per_em"] = 1000;

$FntDef["BasicFont"]["ascent"] = 804;
$FntDef["BasicFont"]["descent"] = 196;
$FntDef["BasicFont"]["linesp"] = 0;
$FntDef["BasicFont"]["unts_per_em"] = 1000;

$FntDef["Annifont"]["ascent"] = 1295;
$FntDef["Annifont"]["descent"] = 461;
$FntDef["Annifont"]["linesp"] = 0;
$FntDef["Annifont"]["unts_per_em"] = 1000;

$FntDef["Arabolical"]["ascent"] = 994;
$FntDef["Arabolical"]["descent"] = 260;
$FntDef["Arabolical"]["linesp"] = 0;
$FntDef["Arabolical"]["unts_per_em"] = 1000;

$FntDef["ChowFun"]["ascent"] = 1100;
$FntDef["ChowFun"]["descent"] = 379;
$FntDef["ChowFun"]["linesp"] = 0;
$FntDef["ChowFun"]["unts_per_em"] = 1000;

$FntDef["Dadhand"]["ascent"] = 963;
$FntDef["Dadhand"]["descent"] = 403;
$FntDef["Dadhand"]["linesp"] = 0;
$FntDef["Dadhand"]["unts_per_em"] = 1000;

$FntDef["KidKosmic"]["ascent"] = 1187;
$FntDef["KidKosmic"]["descent"] = -23;
$FntDef["KidKosmic"]["linesp"] = 0;
$FntDef["KidKosmic"]["unts_per_em"] = 1024;

$FntDef["Bajsporr"]["ascent"] = 912;
$FntDef["Bajsporr"]["descent"] = 420;
$FntDef["Bajsporr"]["linesp"] = 0;
$FntDef["Bajsporr"]["unts_per_em"] = 1000;

$FntDef["Butterfly"]["ascent"] = 818;
$FntDef["Butterfly"]["descent"] = 351;
$FntDef["Butterfly"]["linesp"] = 0;
$FntDef["Butterfly"]["unts_per_em"] = 1000;

$FntDef["Platsch"]["ascent"] = 2166;
$FntDef["Platsch"]["descent"] = 614;
$FntDef["Platsch"]["linesp"] = 0;
$FntDef["Platsch"]["unts_per_em"] = 2048;

$FntDef["Riddleprint"]["ascent"] = 1865;
$FntDef["Riddleprint"]["descent"] = 766;
$FntDef["Riddleprint"]["linesp"] = 87;
$FntDef["Riddleprint"]["unts_per_em"] = 2047;

$FntDef["Runoff"]["ascent"] = 972;
$FntDef["Runoff"]["descent"] = 304;
$FntDef["Runoff"]["linesp"] = 9;
$FntDef["Runoff"]["unts_per_em"] = 1000;

$FntDef["Splash"]["ascent"] = 917;
$FntDef["Splash"]["descent"] = 296;
$FntDef["Splash"]["linesp"] = 0;
$FntDef["Splash"]["unts_per_em"] = 1000;

$FntDef["Flubber"]["ascent"] = 810;
$FntDef["Flubber"]["descent"] = 214;
$FntDef["Flubber"]["linesp"] = 0;
$FntDef["Flubber"]["unts_per_em"] = 1000;

$FntDef["Grinched"]["ascent"] = 1854;
$FntDef["Grinched"]["descent"] = 434;
$FntDef["Grinched"]["linesp"] = 67;
$FntDef["Grinched"]["unts_per_em"] = 2048;

$FntDef["MondoRedondo"]["ascent"] = 700;
$FntDef["MondoRedondo"]["descent"] = 281;
$FntDef["MondoRedondo"]["linesp"] = 0;
$FntDef["MondoRedondo"]["unts_per_em"] = 1000;

$FntDef["Happy"]["ascent"] = 1081;
$FntDef["Happy"]["descent"] = 171;
$FntDef["Happy"]["linesp"] = 0;
$FntDef["Happy"]["unts_per_em"] = 1000;

$FntDef["HollywoodHills"]["ascent"] = 770;
$FntDef["HollywoodHills"]["descent"] = 120;
$FntDef["HollywoodHills"]["linesp"] = 0;
$FntDef["HollywoodHills"]["unts_per_em"] = 1000;

$FntDef["PantsPatrol"]["ascent"] = 767;
$FntDef["PantsPatrol"]["descent"] = 222;
$FntDef["PantsPatrol"]["linesp"] = 0;
$FntDef["PantsPatrol"]["unts_per_em"] = 1000;

$FntDef["Ghostmeat"]["ascent"] = 750;
$FntDef["Ghostmeat"]["descent"] = 141;
$FntDef["Ghostmeat"]["linesp"] = 0;
$FntDef["Ghostmeat"]["unts_per_em"] = 1000;

$FntDef["Caveman"]["ascent"] = 818;
$FntDef["Caveman"]["descent"] = 397;
$FntDef["Caveman"]["linesp"] = 0;
$FntDef["Caveman"]["unts_per_em"] = 1000;

$FntDef["HotDog"]["ascent"] = 1802;
$FntDef["HotDog"]["descent"] = 61;
$FntDef["HotDog"]["linesp"] = 0;
$FntDef["HotDog"]["unts_per_em"] = 2048;

$FntDef["Grasshopper"]["ascent"] = 984;
$FntDef["Grasshopper"]["descent"] = 304;
$FntDef["Grasshopper"]["linesp"] = 0;
$FntDef["Grasshopper"]["unts_per_em"] = 1000;

$FntDef["Uncey"]["ascent"] = 1877;
$FntDef["Uncey"]["descent"] = 519;
$FntDef["Uncey"]["linesp"] = 0;
$FntDef["Uncey"]["unts_per_em"] = 2322;

$FntDef["Gargoyles"]["ascent"] = 795;
$FntDef["Gargoyles"]["descent"] = 200;
$FntDef["Gargoyles"]["linesp"] = 0;
$FntDef["Gargoyles"]["unts_per_em"] = 1000;

$FntDef["Y2kill"]["ascent"] = 926;
$FntDef["Y2kill"]["descent"] = 430;
$FntDef["Y2kill"]["linesp"] = 0;
$FntDef["Y2kill"]["unts_per_em"] = 1000;

$FntDef["Distortia"]["ascent"] = 855;
$FntDef["Distortia"]["descent"] = 204;
$FntDef["Distortia"]["linesp"] = 0;
$FntDef["Distortia"]["unts_per_em"] = 1000;

$FntDef["1942"]["ascent"] = 812;
$FntDef["1942"]["descent"] = 197;
$FntDef["1942"]["linesp"] = 23;
$FntDef["1942"]["unts_per_em"] = 1000;

$FntDef["3Prong"]["ascent"] = 852;
$FntDef["3Prong"]["descent"] = 164;
$FntDef["3Prong"]["linesp"] = 0;
$FntDef["3Prong"]["unts_per_em"] = 1000;

$FntDef["8PinMatrix"]["ascent"] = 996;
$FntDef["8PinMatrix"]["descent"] = 113;
$FntDef["8PinMatrix"]["linesp"] = 0;
$FntDef["8PinMatrix"]["unts_per_em"] = 1000;

$FntDef["Acidic"]["ascent"] = 800;
$FntDef["Acidic"]["descent"] = 223;
$FntDef["Acidic"]["linesp"] = 0;
$FntDef["Acidic"]["unts_per_em"] = 940;

$FntDef["Ajile"]["ascent"] = 889;
$FntDef["Ajile"]["descent"] = 314;
$FntDef["Ajile"]["linesp"] = 75;
$FntDef["Ajile"]["unts_per_em"] = 1000;

$FntDef["AScratch"]["ascent"] = 809;
$FntDef["AScratch"]["descent"] = 365;
$FntDef["AScratch"]["linesp"] = 0;
$FntDef["AScratch"]["unts_per_em"] = 1000;

$FntDef["Baltar"]["ascent"] = 1512;
$FntDef["Baltar"]["descent"] = 327;
$FntDef["Baltar"]["linesp"] = 0;
$FntDef["Baltar"]["unts_per_em"] = 2048;

$FntDef["Bamboo"]["ascent"] = 1118;
$FntDef["Bamboo"]["descent"] = 121;
$FntDef["Bamboo"]["linesp"] = 0;
$FntDef["Bamboo"]["unts_per_em"] = 1000;

$FntDef["BauhausSketch"]["ascent"] = 600;
$FntDef["BauhausSketch"]["descent"] = 185;
$FntDef["BauhausSketch"]["linesp"] = 0;
$FntDef["BauhausSketch"]["unts_per_em"] = 1000;

$FntDef["BeachmanScript"]["ascent"] = 1311;
$FntDef["BeachmanScript"]["descent"] = 629;
$FntDef["BeachmanScript"]["linesp"] = 0;
$FntDef["BeachmanScript"]["unts_per_em"] = 2048;

$FntDef["BeeBopp"]["ascent"] = 4289;
$FntDef["BeeBopp"]["descent"] = 856;
$FntDef["BeeBopp"]["linesp"] = 0;
$FntDef["BeeBopp"]["unts_per_em"] = 4096;

$FntDef["Bitchin"]["ascent"] = 2115;
$FntDef["Bitchin"]["descent"] = -53;
$FntDef["Bitchin"]["linesp"] = 0;
$FntDef["Bitchin"]["unts_per_em"] = 2048;

$FntDef["BlockUp"]["ascent"] = 1000;
$FntDef["BlockUp"]["descent"] = 205;
$FntDef["BlockUp"]["linesp"] = 0;
$FntDef["BlockUp"]["unts_per_em"] = 1000;

$FntDef["Blocky"]["ascent"] = 1727;
$FntDef["Blocky"]["descent"] = 360;
$FntDef["Blocky"]["linesp"] = 0;
$FntDef["Blocky"]["unts_per_em"] = 2048;

$FntDef["Bois"]["ascent"] = 888;
$FntDef["Bois"]["descent"] = 222;
$FntDef["Bois"]["linesp"] = 0;
$FntDef["Bois"]["unts_per_em"] = 1050;

$FntDef["BrianCary"]["ascent"] = 800;
$FntDef["BrianCary"]["descent"] = 434;
$FntDef["BrianCary"]["linesp"] = 0;
$FntDef["BrianCary"]["unts_per_em"] = 1000;

$FntDef["CandyCane"]["ascent"] = 830;
$FntDef["CandyCane"]["descent"] = 221;
$FntDef["CandyCane"]["linesp"] = 0;
$FntDef["CandyCane"]["unts_per_em"] = 1000;

$FntDef["Carolingia"]["ascent"] = 793;
$FntDef["Carolingia"]["descent"] = 382;
$FntDef["Carolingia"]["linesp"] = 0;
$FntDef["Carolingia"]["unts_per_em"] = 1000;

$FntDef["CarpalTunnel"]["ascent"] = 814;
$FntDef["CarpalTunnel"]["descent"] = 216;
$FntDef["CarpalTunnel"]["linesp"] = 0;
$FntDef["CarpalTunnel"]["unts_per_em"] = 1000;

$FntDef["CharmingFont"]["ascent"] = 1866;
$FntDef["CharmingFont"]["descent"] = 692;
$FntDef["CharmingFont"]["linesp"] = 0;
$FntDef["CharmingFont"]["unts_per_em"] = 2048;

$FntDef["ChinaTown"]["ascent"] = 1074;
$FntDef["ChinaTown"]["descent"] = 511;
$FntDef["ChinaTown"]["linesp"] = 30;
$FntDef["ChinaTown"]["unts_per_em"] = 1000;

$FntDef["ChineseTakeaway"]["ascent"] = 805;
$FntDef["ChineseTakeaway"]["descent"] = 200;
$FntDef["ChineseTakeaway"]["linesp"] = 0;
$FntDef["ChineseTakeaway"]["unts_per_em"] = 1000;

$FntDef["CircuitScraping"]["ascent"] = 800;
$FntDef["CircuitScraping"]["descent"] = 98;
$FntDef["CircuitScraping"]["linesp"] = 0;
$FntDef["CircuitScraping"]["unts_per_em"] = 1000;

$FntDef["CityOf"]["ascent"] = 1200;
$FntDef["CityOf"]["descent"] = 195;
$FntDef["CityOf"]["linesp"] = 0;
$FntDef["CityOf"]["unts_per_em"] = 1400;

$FntDef["Cowboys"]["ascent"] = 922;
$FntDef["Cowboys"]["descent"] = 211;
$FntDef["Cowboys"]["linesp"] = 9;
$FntDef["Cowboys"]["unts_per_em"] = 1000;

$FntDef["Creaminal"]["ascent"] = 944;
$FntDef["Creaminal"]["descent"] = -23;
$FntDef["Creaminal"]["linesp"] = 79;
$FntDef["Creaminal"]["unts_per_em"] = 1000;

$FntDef["DarkCrystal"]["ascent"] = 905;
$FntDef["DarkCrystal"]["descent"] = 288;
$FntDef["DarkCrystal"]["linesp"] = 33;
$FntDef["DarkCrystal"]["unts_per_em"] = 1000;

$FntDef["Davis"]["ascent"] = 799;
$FntDef["Davis"]["descent"] = 184;
$FntDef["Davis"]["linesp"] = 0;
$FntDef["Davis"]["unts_per_em"] = 1000;

$FntDef["DietDrCreep"]["ascent"] = 800;
$FntDef["DietDrCreep"]["descent"] = 86;
$FntDef["DietDrCreep"]["linesp"] = 0;
$FntDef["DietDrCreep"]["unts_per_em"] = 1000;

$FntDef["Dot2Dot"]["ascent"] = 966;
$FntDef["Dot2Dot"]["descent"] = 136;
$FntDef["Dot2Dot"]["linesp"] = 0;
$FntDef["Dot2Dot"]["unts_per_em"] = 1000;

$FntDef["DriftType"]["ascent"] = 1708;
$FntDef["DriftType"]["descent"] = 266;
$FntDef["DriftType"]["linesp"] = 0;
$FntDef["DriftType"]["unts_per_em"] = 2048;

$FntDef["EagleGT"]["ascent"] = 2189;
$FntDef["EagleGT"]["descent"] = 358;
$FntDef["EagleGT"]["linesp"] = 0;
$FntDef["EagleGT"]["unts_per_em"] = 2048;

$FntDef["Fairytale"]["ascent"] = 858;
$FntDef["Fairytale"]["descent"] = 305;
$FntDef["Fairytale"]["linesp"] = 77;
$FntDef["Fairytale"]["unts_per_em"] = 1000;

$FntDef["FarEast"]["ascent"] = 1395;
$FntDef["FarEast"]["descent"] = 9;
$FntDef["FarEast"]["linesp"] = 0;
$FntDef["FarEast"]["unts_per_em"] = 2048;

$FntDef["AachenBold"]["ascent"] = 1220;
$FntDef["AachenBold"]["descent"] = 268;
$FntDef["AachenBold"]["linesp"] = 150;
$FntDef["AachenBold"]["unts_per_em"] = 1000;

$FntDef["FattyBombatty"]["ascent"] = 998;
$FntDef["FattyBombatty"]["descent"] = 136;
$FntDef["FattyBombatty"]["linesp"] = 30;
$FntDef["FattyBombatty"]["unts_per_em"] = 1000;

$FntDef["Flores"]["ascent"] = 1500;
$FntDef["Flores"]["descent"] = 179;
$FntDef["Flores"]["linesp"] = 0;
$FntDef["Flores"]["unts_per_em"] = 1000;

$FntDef["FlyingPenguin"]["ascent"] = 1265;
$FntDef["FlyingPenguin"]["descent"] = 160;
$FntDef["FlyingPenguin"]["linesp"] = 32;
$FntDef["FlyingPenguin"]["unts_per_em"] = 1077;

$FntDef["FortunaDot"]["ascent"] = 1012;
$FntDef["FortunaDot"]["descent"] = 253;
$FntDef["FortunaDot"]["linesp"] = 0;
$FntDef["FortunaDot"]["unts_per_em"] = 1000;

$FntDef["Gessele"]["ascent"] = 1104;
$FntDef["Gessele"]["descent"] = 672;
$FntDef["Gessele"]["linesp"] = 0;
$FntDef["Gessele"]["unts_per_em"] = 1700;

$FntDef["Glimstick"]["ascent"] = 1902;
$FntDef["Glimstick"]["descent"] = 502;
$FntDef["Glimstick"]["linesp"] = 0;
$FntDef["Glimstick"]["unts_per_em"] = 2048;

$FntDef["Godzilla"]["ascent"] = 806;
$FntDef["Godzilla"]["descent"] = 191;
$FntDef["Godzilla"]["linesp"] = 10;
$FntDef["Godzilla"]["unts_per_em"] = 1000;

$FntDef["Graffiti"]["ascent"] = 1067;
$FntDef["Graffiti"]["descent"] = 578;
$FntDef["Graffiti"]["linesp"] = 0;
$FntDef["Graffiti"]["unts_per_em"] = 1000;

$FntDef["GraffitiTreat"]["ascent"] = 852;
$FntDef["GraffitiTreat"]["descent"] = 439;
$FntDef["GraffitiTreat"]["linesp"] = 0;
$FntDef["GraffitiTreat"]["unts_per_em"] = 1000;

$FntDef["Hancock"]["ascent"] = 922;
$FntDef["Hancock"]["descent"] = 410;
$FntDef["Hancock"]["linesp"] = 150;
$FntDef["Hancock"]["unts_per_em"] = 1000;

$FntDef["HansHand"]["ascent"] = 1117;
$FntDef["HansHand"]["descent"] = 647;
$FntDef["HansHand"]["linesp"] = 0;
$FntDef["HansHand"]["unts_per_em"] = 1000;

$FntDef["Hathor"]["ascent"] = 800;
$FntDef["Hathor"]["descent"] = 200;
$FntDef["Hathor"]["linesp"] = 0;
$FntDef["Hathor"]["unts_per_em"] = 1000;

$FntDef["HighSpeed"]["ascent"] = 1009;
$FntDef["HighSpeed"]["descent"] = 198;
$FntDef["HighSpeed"]["linesp"] = 0;
$FntDef["HighSpeed"]["unts_per_em"] = 1000;

$FntDef["HobbyHorse"]["ascent"] = 888;
$FntDef["HobbyHorse"]["descent"] = 255;
$FntDef["HobbyHorse"]["linesp"] = 150;
$FntDef["HobbyHorse"]["unts_per_em"] = 1000;

$FntDef["HolidayHardcore"]["ascent"] = 800;
$FntDef["HolidayHardcore"]["descent"] = 197;
$FntDef["HolidayHardcore"]["linesp"] = 0;
$FntDef["HolidayHardcore"]["unts_per_em"] = 1000;

$FntDef["HookedOnBooze"]["ascent"] = 785;
$FntDef["HookedOnBooze"]["descent"] = 120;
$FntDef["HookedOnBooze"]["linesp"] = 0;
$FntDef["HookedOnBooze"]["unts_per_em"] = 1000;

$FntDef["HotRod"]["ascent"] = 2879;
$FntDef["HotRod"]["descent"] = 756;
$FntDef["HotRod"]["linesp"] = 0;
$FntDef["HotRod"]["unts_per_em"] = 2700;

$FntDef["Hyacinth"]["ascent"] = 817;
$FntDef["Hyacinth"]["descent"] = 238;
$FntDef["Hyacinth"]["linesp"] = 0;
$FntDef["Hyacinth"]["unts_per_em"] = 1000;

$FntDef["Hypmotizin"]["ascent"] = 898;
$FntDef["Hypmotizin"]["descent"] = 212;
$FntDef["Hypmotizin"]["linesp"] = 0;
$FntDef["Hypmotizin"]["unts_per_em"] = 1000;

$FntDef["Incantation"]["ascent"] = 935;
$FntDef["Incantation"]["descent"] = 139;
$FntDef["Incantation"]["linesp"] = 0;
$FntDef["Incantation"]["unts_per_em"] = 1000;

$FntDef["Interdimensional"]["ascent"] = 1044;
$FntDef["Interdimensional"]["descent"] = 299;
$FntDef["Interdimensional"]["linesp"] = 0;
$FntDef["Interdimensional"]["unts_per_em"] = 1000;

$FntDef["Inthacity"]["ascent"] = 855;
$FntDef["Inthacity"]["descent"] = 192;
$FntDef["Inthacity"]["linesp"] = 0;
$FntDef["Inthacity"]["unts_per_em"] = 1000;

$FntDef["IronMaiden"]["ascent"] = 1650;
$FntDef["IronMaiden"]["descent"] = 416;
$FntDef["IronMaiden"]["linesp"] = 0;
$FntDef["IronMaiden"]["unts_per_em"] = 2048;

$FntDef["ItalianCursive"]["ascent"] = 2157;
$FntDef["ItalianCursive"]["descent"] = 843;
$FntDef["ItalianCursive"]["linesp"] = 0;
$FntDef["ItalianCursive"]["unts_per_em"] = 2048;

$FntDef["Japan"]["ascent"] = 3281;
$FntDef["Japan"]["descent"] = 868;
$FntDef["Japan"]["linesp"] = 0;
$FntDef["Japan"]["unts_per_em"] = 4096;

$FntDef["Jasper"]["ascent"] = 731;
$FntDef["Jasper"]["descent"] = 273;
$FntDef["Jasper"]["linesp"] = 9;
$FntDef["Jasper"]["unts_per_em"] = 1000;

$FntDef["Jessescript"]["ascent"] = 824;
$FntDef["Jessescript"]["descent"] = 471;
$FntDef["Jessescript"]["linesp"] = 0;
$FntDef["Jessescript"]["unts_per_em"] = 720;

$FntDef["JurassicPark"]["ascent"] = 835;
$FntDef["JurassicPark"]["descent"] = 105;
$FntDef["JurassicPark"]["linesp"] = 150;
$FntDef["JurassicPark"]["unts_per_em"] = 1000;

$FntDef["Karate"]["ascent"] = 1942;
$FntDef["Karate"]["descent"] = 406;
$FntDef["Karate"]["linesp"] = 0;
$FntDef["Karate"]["unts_per_em"] = 2048;

$FntDef["KellyAnnGothic"]["ascent"] = 968;
$FntDef["KellyAnnGothic"]["descent"] = 390;
$FntDef["KellyAnnGothic"]["linesp"] = 0;
$FntDef["KellyAnnGothic"]["unts_per_em"] = 1000;

$FntDef["Killigraphy"]["ascent"] = 1538;
$FntDef["Killigraphy"]["descent"] = 1166;
$FntDef["Killigraphy"]["linesp"] = 0;
$FntDef["Killigraphy"]["unts_per_em"] = 1000;

$FntDef["KingArthur"]["ascent"] = 970;
$FntDef["KingArthur"]["descent"] = 396;
$FntDef["KingArthur"]["linesp"] = 20;
$FntDef["KingArthur"]["unts_per_em"] = 1000;

$FntDef["LinearCurve"]["ascent"] = 800;
$FntDef["LinearCurve"]["descent"] = 200;
$FntDef["LinearCurve"]["linesp"] = 0;
$FntDef["LinearCurve"]["unts_per_em"] = 1000;

$FntDef["Liquidism"]["ascent"] = 794;
$FntDef["Liquidism"]["descent"] = 193;
$FntDef["Liquidism"]["linesp"] = 0;
$FntDef["Liquidism"]["unts_per_em"] = 1000;

$FntDef["Lunaurora"]["ascent"] = 844;
$FntDef["Lunaurora"]["descent"] = 247;
$FntDef["Lunaurora"]["linesp"] = 0;
$FntDef["Lunaurora"]["unts_per_em"] = 1000;

$FntDef["MarqueeMoon"]["ascent"] = 1837;
$FntDef["MarqueeMoon"]["descent"] = 423;
$FntDef["MarqueeMoon"]["linesp"] = 0;
$FntDef["MarqueeMoon"]["unts_per_em"] = 2048;

$FntDef["MedicationNeeded"]["ascent"] = 1638;
$FntDef["MedicationNeeded"]["descent"] = 211;
$FntDef["MedicationNeeded"]["linesp"] = 0;
$FntDef["MedicationNeeded"]["unts_per_em"] = 2048;

$FntDef["Merced"]["ascent"] = 959;
$FntDef["Merced"]["descent"] = 303;
$FntDef["Merced"]["linesp"] = 150;
$FntDef["Merced"]["unts_per_em"] = 1000;

$FntDef["Molecular"]["ascent"] = 834;
$FntDef["Molecular"]["descent"] = 401;
$FntDef["Molecular"]["linesp"] = 0;
$FntDef["Molecular"]["unts_per_em"] = 1000;

$FntDef["Monstroula"]["ascent"] = 2703;
$FntDef["Monstroula"]["descent"] = 875;
$FntDef["Monstroula"]["linesp"] = 0;
$FntDef["Monstroula"]["unts_per_em"] = 2700;

$FntDef["Neon"]["ascent"] = 800;
$FntDef["Neon"]["descent"] = 2;
$FntDef["Neon"]["linesp"] = 0;
$FntDef["Neon"]["unts_per_em"] = 1000;

$FntDef["Neurochrome"]["ascent"] = 857;
$FntDef["Neurochrome"]["descent"] = 314;
$FntDef["Neurochrome"]["linesp"] = 0;
$FntDef["Neurochrome"]["unts_per_em"] = 1000;

$FntDef["PlanetBenson"]["ascent"] = 840;
$FntDef["PlanetBenson"]["descent"] = 548;
$FntDef["PlanetBenson"]["linesp"] = 0;
$FntDef["PlanetBenson"]["unts_per_em"] = 1000;

$FntDef["PostOffice"]["ascent"] = 929;
$FntDef["PostOffice"]["descent"] = 419;
$FntDef["PostOffice"]["linesp"] = 150;
$FntDef["PostOffice"]["unts_per_em"] = 1000;

$FntDef["Pushkin"]["ascent"] = 936;
$FntDef["Pushkin"]["descent"] = 748;
$FntDef["Pushkin"]["linesp"] = 7;
$FntDef["Pushkin"]["unts_per_em"] = 1000;

$FntDef["QuiqleyWiggly"]["ascent"] = 843;
$FntDef["QuiqleyWiggly"]["descent"] = 218;
$FntDef["QuiqleyWiggly"]["linesp"] = 150;
$FntDef["QuiqleyWiggly"]["unts_per_em"] = 1000;

$FntDef["Roddy"]["ascent"] = 2103;
$FntDef["Roddy"]["descent"] = 457;
$FntDef["Roddy"]["linesp"] = 63;
$FntDef["Roddy"]["unts_per_em"] = 2048;

$FntDef["Ruritania"]["ascent"] = 1281;
$FntDef["Ruritania"]["descent"] = 441;
$FntDef["Ruritania"]["linesp"] = 0;
$FntDef["Ruritania"]["unts_per_em"] = 1000;

$FntDef["SouciSans"]["ascent"] = 817;
$FntDef["SouciSans"]["descent"] = 257;
$FntDef["SouciSans"]["linesp"] = 150;
$FntDef["SouciSans"]["unts_per_em"] = 1000;

$FntDef["SpaceAge"]["ascent"] = 800;
$FntDef["SpaceAge"]["descent"] = 50;
$FntDef["SpaceAge"]["linesp"] = 25;
$FntDef["SpaceAge"]["unts_per_em"] = 1000;

$FntDef["Strontium90"]["ascent"] = 900;
$FntDef["Strontium90"]["descent"] = 0;
$FntDef["Strontium90"]["linesp"] = 0;
$FntDef["Strontium90"]["unts_per_em"] = 1000;

$FntDef["Sumdumgoi"]["ascent"] = 1208;
$FntDef["Sumdumgoi"]["descent"] = 390;
$FntDef["Sumdumgoi"]["linesp"] = 0;
$FntDef["Sumdumgoi"]["unts_per_em"] = 1000;

$FntDef["Teletype"]["ascent"] = 884;
$FntDef["Teletype"]["descent"] = 200;
$FntDef["Teletype"]["linesp"] = 0;
$FntDef["Teletype"]["unts_per_em"] = 1000;

$FntDef["TouristTrap"]["ascent"] = 1009;
$FntDef["TouristTrap"]["descent"] = 189;
$FntDef["TouristTrap"]["linesp"] = 0;
$FntDef["TouristTrap"]["unts_per_em"] = 1000;

$FntDef["Trumania"]["ascent"] = 923;
$FntDef["Trumania"]["descent"] = 210;
$FntDef["Trumania"]["linesp"] = 0;
$FntDef["Trumania"]["unts_per_em"] = 1000;

$FntDef["TypicalWriter"]["ascent"] = 819;
$FntDef["TypicalWriter"]["descent"] = 204;
$FntDef["TypicalWriter"]["linesp"] = 97;
$FntDef["TypicalWriter"]["unts_per_em"] = 1000;

$FntDef["UnitedStates"]["ascent"] = 1400;
$FntDef["UnitedStates"]["descent"] = 104;
$FntDef["UnitedStates"]["linesp"] = 0;
$FntDef["UnitedStates"]["unts_per_em"] = 1600;

$FntDef["VikingStencil"]["ascent"] = 1854;
$FntDef["VikingStencil"]["descent"] = 434;
$FntDef["VikingStencil"]["linesp"] = 67;
$FntDef["VikingStencil"]["unts_per_em"] = 2048;

$FntDef["WestSide"]["ascent"] = 852;
$FntDef["WestSide"]["descent"] = 189;
$FntDef["WestSide"]["linesp"] = 0;
$FntDef["WestSide"]["unts_per_em"] = 1000;

$FntDef["WetPet"]["ascent"] = 1559;
$FntDef["WetPet"]["descent"] = 353;
$FntDef["WetPet"]["linesp"] = 0;
$FntDef["WetPet"]["unts_per_em"] = 2048;

$FntDef["WhiteBold"]["ascent"] = 818;
$FntDef["WhiteBold"]["descent"] = 206;
$FntDef["WhiteBold"]["linesp"] = 0;
$FntDef["WhiteBold"]["unts_per_em"] = 1000;

$FntDef["WoodPlank"]["ascent"] = 917;
$FntDef["WoodPlank"]["descent"] = 229;
$FntDef["WoodPlank"]["linesp"] = 100;
$FntDef["WoodPlank"]["unts_per_em"] = 1000;

$FntDef["Zebra"]["ascent"] = 807;
$FntDef["Zebra"]["descent"] = 50;
$FntDef["Zebra"]["linesp"] = 0;
$FntDef["Zebra"]["unts_per_em"] = 1000;

$FntDef["Zirkon"]["ascent"] = 868;
$FntDef["Zirkon"]["descent"] = 219;
$FntDef["Zirkon"]["linesp"] = 75;
$FntDef["Zirkon"]["unts_per_em"] = 1000;

$FntDef["Zoom"]["ascent"] = 820;
$FntDef["Zoom"]["descent"] = 213;
$FntDef["Zoom"]["linesp"] = 0;
$FntDef["Zoom"]["unts_per_em"] = 988;


$FntDef["Square"]["ascent"] = 2028;
$FntDef["Square"]["descent"] = 483;
$FntDef["Square"]["linesp"] = 0;
$FntDef["Square"]["unts_per_em"] = 2048;

$FntDef["SquareBold"]["ascent"] = 1972;
$FntDef["SquareBold"]["descent"] = 483;
$FntDef["SquareBold"]["linesp"] = 0;
$FntDef["SquareBold"]["unts_per_em"] = 2048;

$FntDef["C128S"]["ascent"] = 898;
$FntDef["C128S"]["descent"] = 100;
$FntDef["C128S"]["linesp"] = 0;
$FntDef["C128S"]["unts_per_em"] = 1000;

$FntDef["C128M"]["ascent"] = 1498;
$FntDef["C128M"]["descent"] = 100;
$FntDef["C128M"]["linesp"] = 0;
$FntDef["C128M"]["unts_per_em"] = 1000;

$FntDef["Vectora"]["ascent"] = 942;
$FntDef["Vectora"]["descent"] = 250;
$FntDef["Vectora"]["linesp"] = 73;
$FntDef["Vectora"]["unts_per_em"] = 1000;

$FntDef["Vectorab"]["ascent"] = 962;
$FntDef["Vectorab"]["descent"] = 250;
$FntDef["Vectorab"]["linesp"] = 73;
$FntDef["Vectorab"]["unts_per_em"] = 1000;

$FntDef["Vectorai"]["ascent"] = 925;
$FntDef["Vectorai"]["descent"] = 250;
$FntDef["Vectorai"]["linesp"] = 0;
$FntDef["Vectorai"]["unts_per_em"] = 1000;

$FntDef["Vectorabi"]["ascent"] = 951;
$FntDef["Vectorabi"]["descent"] = 250;
$FntDef["Vectorabi"]["linesp"] = 73;
$FntDef["Vectorabi"]["unts_per_em"] = 1000;

$FntDef["Lydian"]["ascent"] = 1972;
$FntDef["Lydian"]["descent"] = 514;
$FntDef["Lydian"]["linesp"] = 0;
$FntDef["Lydian"]["unts_per_em"] = 2048;

$FntDef["Lydianb"]["ascent"] = 1972;
$FntDef["Lydianb"]["descent"] = 514;
$FntDef["Lydianb"]["linesp"] = 0;
$FntDef["Lydianb"]["unts_per_em"] = 2048;

$FntDef["Lydiani"]["ascent"] = 1972;
$FntDef["Lydiani"]["descent"] = 512;
$FntDef["Lydiani"]["linesp"] = 0;
$FntDef["Lydiani"]["unts_per_em"] = 2048;

$FntDef["Lydianbi"]["ascent"] = 1972;
$FntDef["Lydianbi"]["descent"] = 502;
$FntDef["Lydianbi"]["linesp"] = 0;
$FntDef["Lydianbi"]["unts_per_em"] = 2048;

$FntDef["Times"]["ascent"] = 899;
$FntDef["Times"]["descent"] = 218;
$FntDef["Times"]["linesp"] = 26;
$FntDef["Times"]["unts_per_em"] = 1000;

$FntDef["Timesb"]["ascent"] = 930;
$FntDef["Timesb"]["descent"] = 218;
$FntDef["Timesb"]["linesp"] = 27;
$FntDef["Timesb"]["unts_per_em"] = 1000;

$FntDef["Timesi"]["ascent"] = 883;
$FntDef["Timesi"]["descent"] = 217;
$FntDef["Timesi"]["linesp"] = 25;
$FntDef["Timesi"]["unts_per_em"] = 1000;

$FntDef["Timesbi"]["ascent"] = 921;
$FntDef["Timesbi"]["descent"] = 218;
$FntDef["Timesbi"]["linesp"] = 27;
$FntDef["Timesbi"]["unts_per_em"] = 1000;

$FntDef["SerpentineBoldOblique"]["ascent"] = 898;
$FntDef["SerpentineBoldOblique"]["descent"] = 233;
$FntDef["SerpentineBoldOblique"]["linesp"] = 61;
$FntDef["SerpentineBoldOblique"]["unts_per_em"] = 1000;

$FntDef["Snell"]["ascent"] = 1830;
$FntDef["Snell"]["descent"] = 663;
$FntDef["Snell"]["linesp"] = 446;
$FntDef["Snell"]["unts_per_em"] = 2048;

$FntDef["Snellb"]["ascent"] = 1927;
$FntDef["Snellb"]["descent"] = 663;
$FntDef["Snellb"]["linesp"] = 553;
$FntDef["Snellb"]["unts_per_em"] = 2048;

$FntDef["Present"]["ascent"] = 851;
$FntDef["Present"]["descent"] = 286;
$FntDef["Present"]["linesp"] = 21;
$FntDef["Present"]["unts_per_em"] = 1000;

$FntDef["Presentb"]["ascent"] = 1689;
$FntDef["Presentb"]["descent"] = 508;
$FntDef["Presentb"]["linesp"] = 0;
$FntDef["Presentb"]["unts_per_em"] = 2048;

$FntDef["Palatino"]["ascent"] = 927;
$FntDef["Palatino"]["descent"] = 283;
$FntDef["Palatino"]["linesp"] = 0;
$FntDef["Palatino"]["unts_per_em"] = 1000;

$FntDef["Palatinob"]["ascent"] = 924;
$FntDef["Palatinob"]["descent"] = 266;
$FntDef["Palatinob"]["linesp"] = 0;
$FntDef["Palatinob"]["unts_per_em"] = 1000;

$FntDef["Palatinoi"]["ascent"] = 918;
$FntDef["Palatinoi"]["descent"] = 276;
$FntDef["Palatinoi"]["linesp"] = 0;
$FntDef["Palatinoi"]["unts_per_em"] = 1000;

$FntDef["Palatinobi"]["ascent"] = 926;
$FntDef["Palatinobi"]["descent"] = 271;
$FntDef["Palatinobi"]["linesp"] = 0;
$FntDef["Palatinobi"]["unts_per_em"] = 1000;

$FntDef["Optima"]["ascent"] = 896;
$FntDef["Optima"]["descent"] = 276;
$FntDef["Optima"]["linesp"] = 24;
$FntDef["Optima"]["unts_per_em"] = 1000;

$FntDef["Optimab"]["ascent"] = 921;
$FntDef["Optimab"]["descent"] = 271;
$FntDef["Optimab"]["linesp"] = 77;
$FntDef["Optimab"]["unts_per_em"] = 1000;

$FntDef["Optimai"]["ascent"] = 911;
$FntDef["Optimai"]["descent"] = 269;
$FntDef["Optimai"]["linesp"] = 24;
$FntDef["Optimai"]["unts_per_em"] = 1000;

$FntDef["Optimabi"]["ascent"] = 931;
$FntDef["Optimabi"]["descent"] = 264;
$FntDef["Optimabi"]["linesp"] = 0;
$FntDef["Optimabi"]["unts_per_em"] = 1000;

$FntDef["OptimaBlack"]["ascent"] = 961;
$FntDef["OptimaBlack"]["descent"] = 265;
$FntDef["OptimaBlack"]["linesp"] = 26;
$FntDef["OptimaBlack"]["unts_per_em"] = 1000;

$FntDef["Choc"]["ascent"] = 1802;
$FntDef["Choc"]["descent"] = 540;
$FntDef["Choc"]["linesp"] = 0;
$FntDef["Choc"]["unts_per_em"] = 2048;

$FntDef["FineHand"]["ascent"] = 2488;
$FntDef["FineHand"]["descent"] = 1451;
$FntDef["FineHand"]["linesp"] = 0;
$FntDef["FineHand"]["unts_per_em"] = 2048;

$FntDef["BrushScript"]["ascent"] = 882;
$FntDef["BrushScript"]["descent"] = 281;
$FntDef["BrushScript"]["linesp"] = 0;
$FntDef["BrushScript"]["unts_per_em"] = 1000;

$FntDef["Commercial"]["ascent"] = 938;
$FntDef["Commercial"]["descent"] = 236;
$FntDef["Commercial"]["linesp"] = 0;
$FntDef["Commercial"]["unts_per_em"] = 1000;

$FntDef["Commercialb"]["ascent"] = 943;
$FntDef["Commercialb"]["descent"] = 225;
$FntDef["Commercialb"]["linesp"] = 0;
$FntDef["Commercialb"]["unts_per_em"] = 1000;

$FntDef["Commerciali"]["ascent"] = 938;
$FntDef["Commerciali"]["descent"] = 236;
$FntDef["Commerciali"]["linesp"] = 0;
$FntDef["Commerciali"]["unts_per_em"] = 1000;

$FntDef["Commercialbi"]["ascent"] = 943;
$FntDef["Commercialbi"]["descent"] = 225;
$FntDef["Commercialbi"]["linesp"] = 0;
$FntDef["Commercialbi"]["unts_per_em"] = 1000;

$FntDef["CommercialSolid"]["ascent"] = 956;
$FntDef["CommercialSolid"]["descent"] = 234;
$FntDef["CommercialSolid"]["linesp"] = 0;
$FntDef["CommercialSolid"]["unts_per_em"] = 1000;

$FntDef["Postnet"]["ascent"] = 1652;
$FntDef["Postnet"]["descent"] = 0;
$FntDef["Postnet"]["linesp"] = 63;
$FntDef["Postnet"]["unts_per_em"] = 1000;

$FntDef["Planet"]["ascent"] = 926;
$FntDef["Planet"]["descent"] = 200;
$FntDef["Planet"]["linesp"] = 35;
$FntDef["Planet"]["unts_per_em"] = 1000;

$FntDef["PostnetNoParity"]["ascent"] = 1652;
$FntDef["PostnetNoParity"]["descent"] = 0;
$FntDef["PostnetNoParity"]["linesp"] = 63;
$FntDef["PostnetNoParity"]["unts_per_em"] = 1000;

$FntDef["PlanetNoParity"]["ascent"] = 926;
$FntDef["PlanetNoParity"]["descent"] = 200;
$FntDef["PlanetNoParity"]["linesp"] = 35;
$FntDef["PlanetNoParity"]["unts_per_em"] = 1000;

$FntDef["BarCode128"]["ascent"] = 1498;
$FntDef["BarCode128"]["descent"] = 100;
$FntDef["BarCode128"]["linesp"] = 0;
$FntDef["BarCode128"]["unts_per_em"] = 1000;

$FntDef["AllCaps"]["ascent"] = 938;
$FntDef["AllCaps"]["descent"] = 236;
$FntDef["AllCaps"]["linesp"] = 100;
$FntDef["AllCaps"]["unts_per_em"] = 1000;

$FntDef["BankGothicLight"]["ascent"] = 1653;
$FntDef["BankGothicLight"]["descent"] = 483;
$FntDef["BankGothicLight"]["linesp"] = 410;
$FntDef["BankGothicLight"]["unts_per_em"] = 2048;

$FntDef["BankGothicMedium"]["ascent"] = 1661;
$FntDef["BankGothicMedium"]["descent"] = 483;
$FntDef["BankGothicMedium"]["linesp"] = 483;
$FntDef["BankGothicMedium"]["unts_per_em"] = 2048;

$FntDef["Trajan"]["ascent"] = 1976;
$FntDef["Trajan"]["descent"] = 516;
$FntDef["Trajan"]["linesp"] = 0;
$FntDef["Trajan"]["unts_per_em"] = 2048;

$FntDef["Trajanb"]["ascent"] = 2018;
$FntDef["Trajanb"]["descent"] = 534;
$FntDef["Trajanb"]["linesp"] = 0;
$FntDef["Trajanb"]["unts_per_em"] = 2048;

$FntDef["TrajanBold"]["ascent"] = 2018;
$FntDef["TrajanBold"]["descent"] = 534;
$FntDef["TrajanBold"]["linesp"] = 0;
$FntDef["TrajanBold"]["unts_per_em"] = 2048;

$FntDef["HandOfSean"]["ascent"] = 2652;
$FntDef["HandOfSean"]["descent"] = 1010;
$FntDef["HandOfSean"]["linesp"] = 0;
$FntDef["HandOfSean"]["unts_per_em"] = 2048;

$FntDef["Code39"]["ascent"] = 1798;
$FntDef["Code39"]["descent"] = 0;
$FntDef["Code39"]["linesp"] = 250;
$FntDef["Code39"]["unts_per_em"] = 2048;

$FntDef["Komikax"]["ascent"] = 1380;
$FntDef["Komikax"]["descent"] = 255;
$FntDef["Komikax"]["linesp"] = 30;
$FntDef["Komikax"]["unts_per_em"] = 1000;


?>