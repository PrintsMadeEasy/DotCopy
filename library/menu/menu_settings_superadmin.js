
//  QuickMenu Pro, Copyright (c) 1998 - 2003, OpenCube Inc. - http://www.opencube.com
//
//
//  QuickMenu Pro is Compatible With....
//
//      IE4, IE5.x, IE6 (Win 95, 98, ME, 2000, NT, XP)
//      IE4, IE5.x, &up (Mac)
//      IE4 & up (other platforms)
//      NS4.x (All Platforms)
//      NS5/6.x (All Platforms)
//      NS7 (All Platforms)
//      Opera 5,6,7 (All Platforms)
//      Mozilla 0.6 & up (All Platforms)
//      Konqueror 2.2 & up (Linux)
//      Espial Escape 4.x & up (All Platforms)
//      Ice Browser 5.x & up (All Platforms)
//      Safari 1.0 (Mac only browser)
//      Degrades gracefully in older browsers
//
//
//  To customize QuickMenu Pro open this file in a simple text
//  editor (Notepad or similar). Modify and add parameters (all
//  customizable parameters start with 'dqm__'), save this file,
//  and open 'sample.htm' in a browser to view your menu. View
//  the source for sample.htm for information on connecting
//  sub menus to HTML images or build your page around the
//  included sample.htm file.
//
//  QuickMenu conditionally loads the necessary JavaScript
//  files (.js) depending on the browser and platform the user
//  is viewing the menu on. The total file size for each
//  browser / platform scenario is no larger than 12K.
//
//  This sample data file contains comments and help information
//  to assist in the initial customization of your drop down
//  menu. If you base your implementation on this documented template
//  we recommend the removal of the comments before using on the web, as
//  to optimize the overall file size and load time of the menu for
//  the end user.  With the comments removed this sample data files
//  size may be reduced by as much as 50%. Note: To simplify comment
//  removal there is a uncommented version of this sample template
//  offered in the 'samples' folder.
//
//
//  NOTE: Parameters prefixed with '//' are commented out,
//        delete the '//' to activate the parameter.
//
//        Commenting out required parameters will cause errors.
//
//        Text values, except TRUE and FALSE statements, must be
//        enclosed by double quotes (").
//
//        Each parameter value should appear on its own line.
//
//        This data file may also be placed within your HTML page
//        by enclosing between JavaScript tags.
//
//        Due to browser limitations, DHTML menus will not appear
//        on top of Flash objects (unless the flash objects 'wmode'
//        parameter is set to transparent, however this may be buggy),
//        across frames, or over certain form field elements. A hide
//        and show workaround for form fields is included with this menu
//        (see the FAQ for additional information).



/*-------------------------------------------
Colors, Borders, Dividers, and more...
--------------------------------------------*/


	dqm__sub_menu_width = 170      		//default sub menu widths
	dqm__sub_xy = "0,0"            		//default sub x,y coordinates - defined relative
						//to the top-left corner of parent image or sub menu


	dqm__urltarget = "_self"		//default URL target: _self, _parent, _new, or "my frame name"

	dqm__border_width = 2
	dqm__divider_height = 1

	dqm__border_color = "#336699"		//Hex color or 'transparent'
	dqm__menu_bgcolor = "#E6E6E6"		//Hex color or 'transparent'
	dqm__hl_bgcolor = "#FFFFFF"

	dqm__mouse_off_delay = 150		//defined in milliseconds (activated after mouse stops)
	dqm__nn4_mouse_off_delay = 500		//defined in milliseconds (activated after leaving sub)


/*-------------------------------------------
Font settings and margins
--------------------------------------------*/


    //Font settings

	dqm__textcolor = "#333333"
	dqm__fontfamily = "Arial"		//Any available system font
	dqm__fontsize = 11			//Defined with pixel sizing
	dqm__fontsize_ie4 = 11			//Defined with point sizing
	dqm__textdecoration = "normal"		//set to: 'normal', or 'underline'
	dqm__fontweight = "normal"		//set to: 'normal', or 'bold'
	dqm__fontstyle = "normal"		//set to: 'normal', or 'italic'


    //Rollover font settings

	dqm__hl_textcolor = "#000000"
	dqm__hl_textdecoration = "normal"	//set to: 'normal', or 'underline'



    //Margins and text alignment

	dqm__text_alignment = "right"		//set to: 'left', 'center' or 'right'
	dqm__margin_top = 2
	dqm__margin_bottom = 3
	dqm__margin_left = 5
	dqm__margin_right = 4




/*-------------------------------------------
Bullet and Icon image library - Unlimited bullet
or icon images may be defined below and then associated
with any sub menu items within the 'Sub Menu Structure
and Text' section of this data file.
--------------------------------------------*/


    //Relative positioned icon images (flow with sub item text)

	dqm__icon_image0 = "library/menu/images/bullet_1.gif"
	dqm__icon_rollover0 = "library/menu/images/bullet_2.gif"
	dqm__icon_image_wh0 = "14,10"


	dqm__icon_image1 = "library/menu/images/bulletCat_1.gif"
	dqm__icon_rollover1 = "library/menu/images/bulletCat_2.gif"
	dqm__icon_image_wh1 = "13,8"


    //Absolute positioned icon images (coordinate poitioned)

	//dqm__2nd_icon_image0 = "images/navbar/arrow.gif"
	//dqm__2nd_icon_rollover0 = "images/navbar/arrow.gif"
	//dqm__2nd_icon_image_wh0 = "13,10"
	//dqm__2nd_icon_image_xy0 = "0,4"



/*---------------------------------------------
Optional Status Bar Text
-----------------------------------------------*/

	dqm__show_urls_statusbar = false

	//dqm__status_text0 = "Sample text - Main Menu Item 0"
	//dqm__status_text1 = "Sample text - Main Menu Item 1"

	//dqm__status_text1_0 = "Sample text - Main Menu Item 1, Sub Item 0"
	//dqm__status_text1_0 = "Sample text - Main Menu Item 1, Sub Item 1"




/*-------------------------------------------
Internet Explorer Transition Effects
--------------------------------------------*/


    //Options include - none | fade | pixelate |iris | slide | gradientwipe | checkerboard | radialwipe | randombars | randomdissolve |stretch

	dqm__sub_menu_effect = "fade"
	dqm__sub_item_effect = "fade"


    //Define the effect duration in seconds below.

	dqm__sub_menu_effect_duration = .4
	dqm__sub_item_effect_duration = .4


    //Specific settings for various transitions.

	dqm__effect_pixelate_maxsqare = 25
	dqm__effect_iris_irisstyle = "CIRCLE"		//CROSS, CIRCLE, PLUS, SQUARE, or STAR
	dqm__effect_checkerboard_squaresx = 14
	dqm__effect_checkerboard_squaresY = 14
	dqm__effect_checkerboard_direction = "RIGHT"	//UP, DOWN, LEFT, RIGHT


    //Opacity and drop shadows.

	dqm__sub_menu_opacity = 100			//1 to 100
	dqm__dropshadow_color = "none"			//Hex color value or 'none'
	dqm__dropshadow_offx = 5			//drop shadow width
	dqm__dropshadow_offy = 5			//drop shadow height



/*-------------------------------------------
Browser Bug fixes and Workarounds
--------------------------------------------*/


    //Mac offset fixes, adjust until sub menus position correctly.

	dqm__os9_ie5mac_offset_X = 10
	dqm__os9_ie5mac_offset_Y = 15

	dqm__osx_ie5mac_offset_X = 10
	dqm__osx_ie5mac_offset_Y = 15

	dqm__ie4mac_offset_X = -8
	dqm__ie4mac_offset_Y = -50


    //Netscape 4 resize bug workaround.

	dqm__nn4_reaload_after_resize = true
	dqm__nn4_resize_prompt_user = false
	dqm__nn4_resize_prompt_message = "To reinitialize the navigation menu please click the 'Reload' button."


    //Opera 5 & 6, set to true if the menu is the only item on the HTML page.

	dqm__use_opera_div_detect_fix = true


    //Pre-defined sub menu item heights for the Espial Escape browser.

	dqm__escape_item_height = 20
	dqm__escape_item_height0_0 = 70
	dqm__escape_item_height0_1 = 70


/*---------------------------------------------
Exposed menu events
----------------------------------------------*/


    //Reference additional onload statements here.

	//dqm__onload_code = "alert('custom function - onload')"


    //The 'X' indicates the index number of the sub menu group or item.
    //The 'X_X' indicates the index number of the sub menu item.

	dqm__showmenu_codeX = "status = 'custom show menu function call - menu0'"
	dqm__hidemenu_codeX = "status = 'custom hide menu function call - menu0'"
	dqm__clickitem_codeX_X = "alert('custom Function - Menu Item 0_0')"



/*---------------------------------------------
Specific Sub Menu Settings
----------------------------------------------*/


    //The following settings may be defined for specific sub menu groups.
    //The 'X' represents the index number of the sub menu group.

	dqm__border_widthX = 10;
	dqm__divider_heightX = 5;
	dqm__border_colorX = "#0000ff";
	dqm__menu_bgcolorX = "#ff0000"
	dqm__hl_bgcolorX = "#00ff00"
	dqm__hl_textcolorX = "#ff0000"
	dqm__text_alignmentX = "left"


	dqm__menu_bgcolor1_1_1 = "#ff0000"


/*

	dqm__border_width1_1 = 2;
	dqm__divider_height1_1 = 0;
	dqm__border_color1_1 = "#6666cc";
	dqm__menu_bgcolor1_1 = "#EEEEEE"
	dqm__hl_bgcolor1_1 = "#99ffff"
	dqm__hl_textcolor1_1 = "#333333"
	dqm__text_alignment1_1 = "left"
*/


    //The following settings may be defined for specific sub menu items.
    //The 'X_X' represents the index number of the sub menu item.

	dqm__hl_subdescX_X = "custom highlight text"
	dqm__urltargetX_X = "_new"
	dqm__divider_heightX = 5;




/**********************************************************************************************
**********************************************************************************************

                           Main Menu Rollover Images and Links

**********************************************************************************************
**********************************************************************************************/



    //Main Menu Item 0

	//dqm__rollover_image0 = "sample_images/quickmenu_hl.gif"
	//dqm__rollover_wh0 = "75,22"
	dqm__url0 = "#";











/**********************************************************************************************
**********************************************************************************************

                              Sub Menu Structure and Text

**********************************************************************************************
**********************************************************************************************/



    //Sub Menu 0

	dqm__sub_xy0 = "-126,43"
	dqm__sub_menu_width0 = 120

	dqm__subdesc0_0 = "X"
	dqm__subdesc0_1 = "X"
	dqm__subdesc0_2 = "My History "
	dqm__subdesc0_3 = "X"
	dqm__subdesc0_4 = "X"
	dqm__subdesc0_5 = "X"
	dqm__subdesc0_6 = "X"
	
	dqm__subdesc0_7 = "Maintenance "
	
	dqm__subdesc0_8 = "Edit  " 
	dqm__subdesc0_9 = "Tools "
	dqm__subdesc0_10 = "Search "
	dqm__subdesc0_11 = "Configure "
	dqm__subdesc0_12 = "Reports "



	dqm__icon_index0_0 = 0
	dqm__icon_index0_1 = 0
	dqm__icon_index0_2 = 0
	dqm__icon_index0_3 = 0
	dqm__icon_index0_4 = 0
	dqm__icon_index0_5 = 0
	dqm__icon_index0_6 = 0
	dqm__icon_index0_7 = 1
	dqm__icon_index0_8 = 1
	dqm__icon_index0_9 = 1
	dqm__icon_index0_10 = 1
	dqm__icon_index0_11 = 1
	dqm__icon_index0_12 = 1


	//dqm__url0_0 = "ad_cs_home.php"
	//dqm__url0_1 = "ad_schedule.php"
	dqm__url0_2 = "ad_pagevisits.php"
	//dqm__url0_3 = "ad_coupons_mgmt.php"
	//dqm__url0_4 = "ad_templates.php"
	//dqm__url0_5 = "ad_billing_offline.php"
	//dqm__url0_6 = "ad_creditcard_errors.php"
	//dqm__url0_7 = "ad_sales_management.php"
	//dqm__url0_8 = "ad_contentCategoryList.php"
	

    //Sub Menu 0_7

	dqm__sub_xy0_7 = "-230,2"
	dqm__sub_menu_width0_7 = 130

	dqm__subdesc0_7_0 = "Sales Reps "
	dqm__subdesc0_7_1 = "Corporate Billing "
	dqm__subdesc0_7_2 = "Schedule "
	dqm__subdesc0_7_3 = "Customer Service "
	dqm__subdesc0_7_4 = "Testimonials "

	dqm__icon_index0_7_0 = 0
	dqm__icon_index0_7_1 = 0
	dqm__icon_index0_7_2 = 0
	dqm__icon_index0_7_3 = 0
	dqm__icon_index0_7_4 = 0

	dqm__url0_7_0 = "ad_sales_management.php"
	dqm__url0_7_1 = "ad_billing_offline.php"
	dqm__url0_7_2 = "ad_schedule.php"
	dqm__url0_7_3 = "ad_cs_home.php"
	dqm__url0_7_4 = "ad_customer_testimonials.php"
	



    //Sub Menu 0_8

	dqm__sub_xy0_8 = "-230,2"
	dqm__sub_menu_width0_8 = 130

	dqm__subdesc0_8_0 = "Coupons "
	dqm__subdesc0_8_1 = "Content System "
	dqm__subdesc0_8_2 = "Templates "
	dqm__subdesc0_8_3 = "Email Marketing "
	

	dqm__icon_index0_8_0 = 0
	dqm__icon_index0_8_1 = 0
	dqm__icon_index0_8_2 = 0
	dqm__icon_index0_8_3 = 0

	dqm__url0_8_0 = "ad_coupons_mgmt.php"
	dqm__url0_8_1 = "ad_contentCategoryList.php"
	dqm__url0_8_2 = "ad_templates.php"
  dqm__url0_8_3 = "ad_emailNotifyMessageList.php"


    //Sub Menu 0_9

	dqm__sub_xy0_9 = "-230,2"
	dqm__sub_menu_width0_9 = 130

	dqm__subdesc0_9_0 = "Gang Runs "
	dqm__subdesc0_9_1 = "Mass Project Import "

	dqm__icon_index0_9_0 = 0
	dqm__icon_index0_9_1 = 0

	dqm__url0_9_0 = "ad_gangQueue.php"
	dqm__url0_9_1 = "ad_tools_massimport.php"




    //Sub Menu 0_10

	dqm__sub_xy0_10 = "-230,2"
	dqm__sub_menu_width0_10 = 130

	dqm__subdesc0_10_0 = "Search Orders "
	dqm__subdesc0_10_1 = "Search Users "
	dqm__subdesc0_10_2 = "Search Artworks "
	dqm__subdesc0_10_3 = "Credit Card Errors "

	dqm__icon_index0_10_0 = 0
	dqm__icon_index0_10_1 = 0
	dqm__icon_index0_10_2 = 0
	dqm__icon_index0_10_3 = 0

	dqm__url0_10_0 = "ad_orders_search.php"
	dqm__url0_10_1 = "ad_users_search.php"
	dqm__url0_10_2 = "ad_artwork_search.php"
	dqm__url0_10_3 = "ad_creditcard_errors.php"





    //Sub Menu 0_11

	dqm__sub_xy0_11 = "-230,2"
	dqm__sub_menu_width0_11 = 130

	dqm__subdesc0_11_0 = "Products "
	dqm__subdesc0_11_1 = "Production "
	dqm__subdesc0_11_2 = "Shipping "
	dqm__subdesc0_11_3 = "Super PDF Profiles "
	dqm__subdesc0_11_4 = "Email Logins "
	dqm__subdesc0_11_5 = "Domain Addresses "
	dqm__subdesc0_11_6 = "URL Rewrites "

	dqm__icon_index0_11_0 = 0
	dqm__icon_index0_11_1 = 0
	dqm__icon_index0_11_2 = 0
	dqm__icon_index0_11_3 = 0
    dqm__icon_index0_11_4 = 0
	dqm__icon_index0_11_5 = 0
	dqm__icon_index0_11_6 = 0


	dqm__url0_11_0 = "ad_product_setup.php"
	dqm__url0_11_1 = "ad_production_setup.php"
	dqm__url0_11_2 = "ad_shippingChoices.php"
	dqm__url0_11_3 = "ad_superpdfprofiles.php"
	dqm__url0_11_4 = "ad_domainEmailEdit.php"
	dqm__url0_11_5 = "ad_domainAddressEdit.php"
	dqm__url0_11_6 = "ad_urlrewritesEdit.php"
	
    //Sub Menu 0_12

	dqm__sub_xy0_12 = "-230,2"
	dqm__sub_menu_width0_12 = 130

	dqm__subdesc0_12_0 = "Month Report "
	dqm__subdesc0_12_1 = "Marketing Report "
	dqm__subdesc0_12_2 = "Visitor Paths "

	dqm__icon_index0_12_0 = 0
	dqm__icon_index0_12_1 = 0
	dqm__icon_index0_12_2 = 0

	dqm__url0_12_0 = "ad_report_month.php"
	dqm__url0_12_1 = "ad_marketing_report.php"
	dqm__url0_12_2 = "ad_visitorPaths.php"



