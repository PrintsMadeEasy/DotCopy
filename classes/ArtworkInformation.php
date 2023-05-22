<?


class ArtworkInformation{

	private $parser;  //So this object has reference to the XPAT xml proccesor
	private $curTag;
	private $attributes;
	private $_XMLfile;
	private $_SideCounter;
	private $_LayerCounter;
	private $_ColorDefinitionCounter;
	private $_ColorPaletteCounter;

	public $SideItemsArray;


	function __construct($XMLfile){
		
		// This was quite a difficult problem to fix.
		// You can not call utf8 encode() twice on the same string with unicode characters or it jumbles stuff
		// So be careful... you made need to do a decode in some places (but never utf-8 decode) more than once on the same string.  This is the only place that you should find the encode function.
		$XMLfile = utf8_encode($XMLfile);

		// This is header is needed for the Xpat processor to parse the file... along with the utf-8 encoding.
		$XMLfile = preg_replace("/^\s*<\?xml version=\"1\.0\"\s*\?>/", "<?xml version=\"1.0\" encoding=\"ISO-8859-1\" ?>", $XMLfile);
		
		$this->_SideCounter = -1;  //We want the array to be 0 based.  It will increment this variable anytime a side is found (before processing)
		$this->_LayerCounter = -1;
		$this->_ColorDefinitionCounter = -1;
		$this->_ColorPaletteCounter = -1;

		$this->SideItemsArray = array();  //Typically has 1-2 elements.. One for front-side, one for backside... each element will contain objects describing the artfile.


		$this->_XMLfile = $XMLfile;
		$this->parseXMLdoc();

	}


	// Static methods

	// Pass in an Artwork File XML file and it will be filtered for bad things inside.
	// Makes sure the Document is well-formed before saving to the DB. 
	// Pass in an Artwork XML file and it will return an Artwork XML file.
	function FilterArtworkXMLfile($ArtworkFile){

		// Filter the Artwork file for various items and ensure integrity.
		$ArtworkInfoObj = new ArtworkInformation($ArtworkFile);

		for($i=0; $i<sizeof($ArtworkInfoObj->SideItemsArray); $i++) {
			for($j=0; $j<sizeof($ArtworkInfoObj->SideItemsArray[$i]->layers); $j++){

				// Removing Layers with Shadows could cause other layers to be deleted ahead of schedule.
				// So make sure the layer is actually there before going any further.
				if(!isset($ArtworkInfoObj->SideItemsArray[$i]->layers[$j]))
					continue;

				if($ArtworkInfoObj->SideItemsArray[$i]->layers[$j]->LayerType == "text"){

					// Make sure that a font size can not go under 4;
					if($ArtworkInfoObj->SideItemsArray[$i]->layers[$j]->LayerDetailsObj->size < 4)
						$ArtworkInfoObj->SideItemsArray[$i]->layers[$j]->LayerDetailsObj->size = 4;

					// There is no reason to keep the default Text layer if they haven't done any editing.
					// In can also be confusing if thre are hidden layers on a black background
					if(preg_match("/New Text Layer!br!Double-click/", $ArtworkInfoObj->SideItemsArray[$i]->layers[$j]->LayerDetailsObj->message))
						$ArtworkInfoObj->RemoveLayerFromArtworkObj($i, $ArtworkInfoObj->SideItemsArray[$i]->layers[$j]->level);
				}
				else if($ArtworkInfoObj->SideItemsArray[$i]->layers[$j]->LayerType == "graphic"){

					// Make sure that the Layer Width and Height of an image can never go under 2
					if($ArtworkInfoObj->SideItemsArray[$i]->layers[$j]->LayerDetailsObj->width < 2)
						$ArtworkInfoObj->SideItemsArray[$i]->layers[$j]->LayerDetailsObj->width = 2;
					if($ArtworkInfoObj->SideItemsArray[$i]->layers[$j]->LayerDetailsObj->height < 2)
						$ArtworkInfoObj->SideItemsArray[$i]->layers[$j]->LayerDetailsObj->height = 2;
				}
				else if($ArtworkInfoObj->SideItemsArray[$i]->layers[$j]->LayerType != "deleted"){
					throw new Exception("Illegal Layer Type in function ArtworkInformation::FilterArtworkXMLfile");
				}
			}
		}

		// Return a Clean XML doc.
		return $ArtworkInfoObj->GetXMLdoc();
	}


	// Pass in a font name... will return true if it is some time of barcode.
	// We may want to know this since barcodes should not be scaled during artwork transfers or whatever.
	function CheckIfFontIsBarcode($fontName){

		return in_array($fontName, array("Postnet", "Planet", "BarCode128"));
	}


	function parseXMLdoc(){

		// Define Event driven functions for XPAT processor. 
		$this->parser = xml_parser_create();
		xml_set_object($this->parser, $this);
		xml_set_element_handler($this->parser, "startElement", "endElement");
		xml_set_character_data_handler($this->parser, "characterData");

		// Parse the XML document.  This will call our functions that we just set handlers for above during the processing. 
		if (!xml_parse($this->parser, $this->_XMLfile)) {
			die(sprintf("XML error: %s at line %d", xml_error_string(xml_get_error_code($this->parser)), xml_get_current_line_number($this->parser)));
		}

		xml_parser_free($this->parser);
	}

	function startElement($parser, $name, $attrs) {

		$this->attributes = $attrs;
		$this->curTag .= "^$name";
		
		
		// Look for Parent tags that should create an Object for one of its children (to have its elements parsed next).
		
		$side_Key = "^CONTENT^SIDE";
		$layer_Key = "^CONTENT^SIDE^LAYER";
		$text_Key = "^CONTENT^SIDE^LAYER^TEXT";
		$graphic_Key = "^CONTENT^SIDE^LAYER^GRAPHIC";
		$side_markerimage_Key = "^CONTENT^SIDE^MARKER_IMAGE";
		$side_maskimage_Key = "^CONTENT^SIDE^MASK_IMAGE";


		if ($this->curTag == $side_Key) {

			$this->_SideCounter++;
			$this->SideItemsArray[$this->_SideCounter] = new SideItem();

			// Set the layer counter to 1 since we just created a new side.  We want the layers array to be 0 based.  Set to -1 so it will get incremented to 0 when the first layer is found.
			$this->_LayerCounter = -1;
			$this->_ColorDefinitionCounter = -1;
			$this->_ColorPaletteCounter = -1;
		}
		else if($this->curTag == $layer_Key){
			$this->_LayerCounter++;
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter] = new LayerItem();
		}
		else if($this->curTag == $text_Key){
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj = new TextItem();
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerType = "text";
		}
		else if($this->curTag == $graphic_Key){
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj = new GraphicItem();
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerType = "graphic";
		}
		else if($this->curTag == $side_markerimage_Key){
			$this->SideItemsArray[$this->_SideCounter]->markerimage = new MarkerImage();
		}
		else if($this->curTag == $side_maskimage_Key){
			$this->SideItemsArray[$this->_SideCounter]->maskimage = new MaskImage();
		}


	}

	function endElement($parser, $name) {

		$caret_pos = strrpos($this->curTag,'^');
		$this->curTag = substr($this->curTag, 0, $caret_pos);

	}

	function characterData($parser, $data) {

		

		// Define all possible tag structures with a carrot separating each tag name.


		$side_description_Key = "^CONTENT^SIDE^DESCRIPTION";
		$side_initialzoom_Key = "^CONTENT^SIDE^INITIALZOOM";
		$side_rotatecanvas_Key = "^CONTENT^SIDE^ROTATECANVAS";
		$side_contentwidth_Key = "^CONTENT^SIDE^CONTENTWIDTH";
		$side_contentheight_Key = "^CONTENT^SIDE^CONTENTHEIGHT";
		$side_backgroundimage_Key = "^CONTENT^SIDE^BACKGROUNDIMAGE";
		$side_background_x_Key = "^CONTENT^SIDE^BACKGROUND_X";
		$side_background_y_Key = "^CONTENT^SIDE^BACKGROUND_Y";
		$side_background_width_Key = "^CONTENT^SIDE^BACKGROUND_WIDTH";
		$side_background_height_Key = "^CONTENT^SIDE^BACKGROUND_HEIGHT";
		$side_background_color_Key = "^CONTENT^SIDE^BACKGROUND_COLOR";
		$side_folds_horizontal_Key = "^CONTENT^SIDE^FOLDS_HORIZ";
		$side_folds_vertical_Key = "^CONTENT^SIDE^FOLDS_VERT";
		$side_scale_Key = "^CONTENT^SIDE^SCALE";
		$side_dpi_Key = "^CONTENT^SIDE^DPI";
		
		$side_markerimageID_Key = "^CONTENT^SIDE^MARKER_IMAGE^IMAGEID";
		$side_markerimage_x_Key = "^CONTENT^SIDE^MARKER_IMAGE^X_COORDINATE";
		$side_markerimage_y_Key = "^CONTENT^SIDE^MARKER_IMAGE^Y_COORDINATE";
		$side_markerimage_width_Key = "^CONTENT^SIDE^MARKER_IMAGE^WIDTH";
		$side_markerimage_height_Key = "^CONTENT^SIDE^MARKER_IMAGE^HEIGHT";
		
		$side_maskimageID_Key = "^CONTENT^SIDE^MASK_IMAGE^IMAGEID";
		$side_maskimage_x_Key = "^CONTENT^SIDE^MASK_IMAGE^X_COORDINATE";
		$side_maskimage_y_Key = "^CONTENT^SIDE^MASK_IMAGE^Y_COORDINATE";
		$side_maskimage_width_Key = "^CONTENT^SIDE^MASK_IMAGE^WIDTH";
		$side_maskimage_height_Key = "^CONTENT^SIDE^MASK_IMAGE^HEIGHT";
		
		$side_show_boundary_Key = "^CONTENT^SIDE^SHOW_BOUNDARY";
		
		$color_definition_Key = "^CONTENT^SIDE^COLOR_DEFINITIONS^COLOR";
		$color_palette_Key = "^CONTENT^SIDE^COLOR_PALETTE^COLOR";

		$layer_level_Key = "^CONTENT^SIDE^LAYER^LEVEL";
		$layer_x_coordinate_Key = "^CONTENT^SIDE^LAYER^X_COORDINATE";
		$layer_y_coordinate_Key = "^CONTENT^SIDE^LAYER^Y_COORDINATE";
		$layer_rotation_Key = "^CONTENT^SIDE^LAYER^ROTATION";

		$text_font_Key = "^CONTENT^SIDE^LAYER^TEXT^FONT";
		$text_size_Key = "^CONTENT^SIDE^LAYER^TEXT^SIZE";
		$text_bold_Key = "^CONTENT^SIDE^LAYER^TEXT^BOLD";
		$text_italics_Key = "^CONTENT^SIDE^LAYER^TEXT^ITALICS";
		$text_underline_Key = "^CONTENT^SIDE^LAYER^TEXT^UNDERLINE";
		$text_align_Key = "^CONTENT^SIDE^LAYER^TEXT^ALIGN";
		$text_message_Key = "^CONTENT^SIDE^LAYER^TEXT^MESSAGE";
		$text_color_Key = "^CONTENT^SIDE^LAYER^TEXT^COLOR";
		$text_field_name = "^CONTENT^SIDE^LAYER^TEXT^FIELD_NAME";
		$text_field_order = "^CONTENT^SIDE^LAYER^TEXT^FIELD_ORDER";
		$text_shadow_level_link_Key = "^CONTENT^SIDE^LAYER^TEXT^SHADOW_LEVEL_LINK";
		$text_shadow_distance_Key = "^CONTENT^SIDE^LAYER^TEXT^SHADOW_DISTANCE";
		$text_shadow_angle_Key = "^CONTENT^SIDE^LAYER^TEXT^SHADOW_ANGLE";

		$text_perm_posX_Key = "^CONTENT^SIDE^LAYER^TEXT^PERMISSIONS^POSITION_X_LOCKED";
		$text_perm_posY_Key = "^CONTENT^SIDE^LAYER^TEXT^PERMISSIONS^POSITION_Y_LOCKED";
		$text_perm_size_Key = "^CONTENT^SIDE^LAYER^TEXT^PERMISSIONS^SIZE_LOCKED";
		$text_perm_delete_Key = "^CONTENT^SIDE^LAYER^TEXT^PERMISSIONS^DELETION_LOCKED";
		$text_perm_color_Key = "^CONTENT^SIDE^LAYER^TEXT^PERMISSIONS^COLOR_LOCKED";
		$text_perm_font_Key = "^CONTENT^SIDE^LAYER^TEXT^PERMISSIONS^FONT_LOCKED";
		$text_perm_align_Key = "^CONTENT^SIDE^LAYER^TEXT^PERMISSIONS^ALIGNMENT_LOCKED";
		$text_perm_rotate_Key = "^CONTENT^SIDE^LAYER^TEXT^PERMISSIONS^ROTATION_LOCKED";
		$text_perm_data_Key = "^CONTENT^SIDE^LAYER^TEXT^PERMISSIONS^DATA_LOCKED";
		$text_perm_select_Key = "^CONTENT^SIDE^LAYER^TEXT^PERMISSIONS^NOT_SELECTABLE";
		$text_perm_trans_Key = "^CONTENT^SIDE^LAYER^TEXT^PERMISSIONS^NOT_TRANSFERABLE";

		$graphic_perm_posX_Key = "^CONTENT^SIDE^LAYER^GRAPHIC^PERMISSIONS^POSITION_X_LOCKED";
		$graphic_perm_posY_Key = "^CONTENT^SIDE^LAYER^GRAPHIC^PERMISSIONS^POSITION_Y_LOCKED";
		$graphic_perm_size_Key = "^CONTENT^SIDE^LAYER^GRAPHIC^PERMISSIONS^SIZE_LOCKED";
		$graphic_perm_delete_Key = "^CONTENT^SIDE^LAYER^GRAPHIC^PERMISSIONS^DELETION_LOCKED";
		$graphic_perm_rotate_Key = "^CONTENT^SIDE^LAYER^GRAPHIC^PERMISSIONS^ROTATION_LOCKED";
		$graphic_perm_select_Key = "^CONTENT^SIDE^LAYER^GRAPHIC^PERMISSIONS^NOT_SELECTABLE";
		$graphic_perm_onTop_Key = "^CONTENT^SIDE^LAYER^GRAPHIC^PERMISSIONS^ALWAYS_ON_TOP";
		$graphic_perm_trans_Key = "^CONTENT^SIDE^LAYER^GRAPHIC^PERMISSIONS^NOT_TRANSFERABLE";

		$graphic_width_Key = "^CONTENT^SIDE^LAYER^GRAPHIC^WIDTH";
		$graphic_height_Key = "^CONTENT^SIDE^LAYER^GRAPHIC^HEIGHT";
		$graphic_originalheight_Key = "^CONTENT^SIDE^LAYER^GRAPHIC^ORIGINALHEIGHT";
		$graphic_originalwidth_Key = "^CONTENT^SIDE^LAYER^GRAPHIC^ORIGINALWIDTH";
		$graphic_imageid_Key = "^CONTENT^SIDE^LAYER^GRAPHIC^IMAGEID";
		$graphic_VectorImageId_Key = "^CONTENT^SIDE^LAYER^GRAPHIC^VECTORIMAGEID";
		




		if ($this->curTag == $side_description_Key) {
			$this->SideItemsArray[$this->_SideCounter]->description = $data;
		}
		else if ($this->curTag == $side_initialzoom_Key) {
			$this->SideItemsArray[$this->_SideCounter]->initialzoom = $data;
		}
		else if ($this->curTag == $side_rotatecanvas_Key) {
			$this->SideItemsArray[$this->_SideCounter]->rotatecanvas = $data;
		}
		else if ($this->curTag == $side_contentwidth_Key) {
			$this->SideItemsArray[$this->_SideCounter]->contentwidth = $data;
		}
		else if ($this->curTag == $side_contentheight_Key) {
			$this->SideItemsArray[$this->_SideCounter]->contentheight = $data;
		}
		else if ($this->curTag == $side_backgroundimage_Key) {
			$this->SideItemsArray[$this->_SideCounter]->backgroundimage = $data;
		}
		else if ($this->curTag == $side_background_x_Key) {
			$this->SideItemsArray[$this->_SideCounter]->background_x = $data;
		}
		else if ($this->curTag == $side_background_y_Key) {
			$this->SideItemsArray[$this->_SideCounter]->background_y = $data;
		}
		else if ($this->curTag == $side_background_width_Key) {
			$this->SideItemsArray[$this->_SideCounter]->background_width = $data;
		}
		else if ($this->curTag == $side_background_height_Key) {
			$this->SideItemsArray[$this->_SideCounter]->background_height = $data;
		}
		else if ($this->curTag == $side_markerimageID_Key) {
			$this->SideItemsArray[$this->_SideCounter]->markerimage->imageid = $data;
		}
		else if ($this->curTag == $side_markerimage_x_Key) {
			$this->SideItemsArray[$this->_SideCounter]->markerimage->x_coordinate = $data;
		}
		else if ($this->curTag == $side_markerimage_y_Key) {
			$this->SideItemsArray[$this->_SideCounter]->markerimage->y_coordinate = $data;
		}
		else if ($this->curTag == $side_markerimage_width_Key) {
			$this->SideItemsArray[$this->_SideCounter]->markerimage->width = $data;
		}
		else if ($this->curTag == $side_markerimage_height_Key) {
			$this->SideItemsArray[$this->_SideCounter]->markerimage->height = $data;
		}
		else if ($this->curTag == $side_maskimageID_Key) {
			$this->SideItemsArray[$this->_SideCounter]->maskimage->imageid = $data;
		}
		else if ($this->curTag == $side_maskimage_x_Key) {
			$this->SideItemsArray[$this->_SideCounter]->maskimage->x_coordinate = $data;
		}
		else if ($this->curTag == $side_maskimage_y_Key) {
			$this->SideItemsArray[$this->_SideCounter]->maskimage->y_coordinate = $data;
		}
		else if ($this->curTag == $side_maskimage_width_Key) {
			$this->SideItemsArray[$this->_SideCounter]->maskimage->width = $data;
		}
		else if ($this->curTag == $side_maskimage_height_Key) {
			$this->SideItemsArray[$this->_SideCounter]->maskimage->height = $data;
		}
		else if ($this->curTag == $side_show_boundary_Key) {
			$this->SideItemsArray[$this->_SideCounter]->show_boundary = (strtoupper($data) == "NO" ? false : true);
		}
		else if ($this->curTag == $side_folds_horizontal_Key) {
			$this->SideItemsArray[$this->_SideCounter]->folds_horizontal = $data;
		}
		else if ($this->curTag == $side_folds_vertical_Key) {
			$this->SideItemsArray[$this->_SideCounter]->folds_vertical = $data;
		}
		else if ($this->curTag == $side_scale_Key) {
			$this->SideItemsArray[$this->_SideCounter]->scale = $data;
		}
		else if ($this->curTag == $side_dpi_Key) {
			$this->SideItemsArray[$this->_SideCounter]->dpi = $data;
		}
		else if ($this->curTag == $side_background_color_Key) {
			$this->SideItemsArray[$this->_SideCounter]->background_color = $data;
		}
		else if ($this->curTag == $color_palette_Key) {

			$this->_ColorPaletteCounter++;
			$this->SideItemsArray[$this->_SideCounter]->color_palette_entries[$this->_ColorPaletteCounter] = new ColorPaletteItem();
			
			// The ID attribute should always be set... but just in case. 
			if(!isset($this->attributes["COLORCODE"]))
				$colorCodeValue = "0";
			else
				$colorCodeValue = $this->attributes["COLORCODE"];
				
			// If a hex code like #FF0000 is set in the XML file, convert it to decimal.
			$colorCodeValue = ColorLib::getDecimalValueOfColorCode($colorCodeValue);
			
			$this->SideItemsArray[$this->_SideCounter]->color_palette_entries[$this->_ColorPaletteCounter]->color_description = $data;
			$this->SideItemsArray[$this->_SideCounter]->color_palette_entries[$this->_ColorPaletteCounter]->colorcode = $colorCodeValue;
		}
		else if ($this->curTag == $color_definition_Key) {

			$this->_ColorDefinitionCounter++;
			$this->SideItemsArray[$this->_SideCounter]->color_definitions[$this->_ColorDefinitionCounter] = new ColorItem();
			
			// The ID attribute should always be set... but just in case. 
			if(!isset($this->attributes["ID"]))
				$colorCodeID = 0;
			else
				$colorCodeID = $this->attributes["ID"];
				
			$colorCodeValue = $data;
			
			// If a hex code like #FF0000 is set in the XML file, convert it to decimal.
			$colorCodeValue = ColorLib::getDecimalValueOfColorCode($colorCodeValue);
			
			$this->SideItemsArray[$this->_SideCounter]->color_definitions[$this->_ColorDefinitionCounter]->colorcode = $colorCodeValue;
			$this->SideItemsArray[$this->_SideCounter]->color_definitions[$this->_ColorDefinitionCounter]->id = $colorCodeID;
		}
		else if ($this->curTag == $layer_level_Key) {
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->level = $data;
		}
		else if ($this->curTag == $layer_x_coordinate_Key) {
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->x_coordinate = $data;
		}
		else if ($this->curTag == $layer_y_coordinate_Key) {
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->y_coordinate = $data;
		}
		else if ($this->curTag == $layer_rotation_Key) {
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->rotation = $data;
		}
		else if ($this->curTag == $text_font_Key) {
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj->font = $data;

			// See the the assigment below to know why I initialized this member varialbe to Blank here.
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj->message = "";
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj->field_name = "";
		}
		else if ($this->curTag == $text_message_Key) {
			// You will notice that I used ".=" here.. this is because the cdata handler function parses HTML entities individually.
			// This key may be called several times during the same message if there are HTML character codes inside.
			// Even if an Artwork is utf-8 filtered ahead of time... it is still required here to keep character errors from showing up on PDF generation.
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj->message .= utf8_decode($data);
		}
		else if ($this->curTag == $text_field_name) {
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj->field_name = $data;
		}
		else if ($this->curTag == $text_field_order) {
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj->field_order = $data;
		}
		else if ($this->curTag == $text_size_Key) {
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj->size = $data;
		}
		else if ($this->curTag == $text_bold_Key) {
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj->bold = $data;
		}
		else if ($this->curTag == $text_italics_Key) {
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj->italics = $data;
		}
		else if ($this->curTag == $text_underline_Key) {
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj->underline = $data;
		}
		else if ($this->curTag == $text_align_Key) {
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj->align = $data;
		}
		else if ($this->curTag == $text_color_Key) {
			
			$colorValue = $data;
			
			// If there are not any color definitions... then make sure that the color code is in Decimal format.
			if(empty($this->SideItemsArray[$this->_SideCounter]->color_definitions))
				$colorValue = ColorLib::getDecimalValueOfColorCode($colorValue);
			
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj->color = $colorValue;
		}
		else if ($this->curTag == $text_shadow_level_link_Key) {
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj->shadow_level_link = $data;
		}
		else if ($this->curTag == $text_shadow_distance_Key) {
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj->shadow_distance = $data;
		}
		else if ($this->curTag == $text_shadow_angle_Key) {
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj->shadow_angle = $data;
		}
		else if ($this->curTag == $text_perm_posX_Key){
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj->permissions->position_x_locked = (strtoupper($data) == "YES" ? true : false);
		}
		else if ($this->curTag == $text_perm_posY_Key){
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj->permissions->position_y_locked = (strtoupper($data) == "YES" ? true : false);
		}
		else if ($this->curTag == $text_perm_size_Key){
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj->permissions->size_locked = (strtoupper($data) == "YES" ? true : false);
		}
		else if ($this->curTag == $text_perm_delete_Key){
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj->permissions->deletion_locked = (strtoupper($data) == "YES" ? true : false);
		}
		else if ($this->curTag == $text_perm_color_Key){
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj->permissions->color_locked = (strtoupper($data) == "YES" ? true : false);
		}
		else if ($this->curTag == $text_perm_font_Key){
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj->permissions->font_locked = (strtoupper($data) == "YES" ? true : false);
		}
		else if ($this->curTag == $text_perm_align_Key){
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj->permissions->alignment_locked = (strtoupper($data) == "YES" ? true : false);
		}
		else if ($this->curTag == $text_perm_rotate_Key){
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj->permissions->rotation_locked = (strtoupper($data) == "YES" ? true : false);
		}
		else if ($this->curTag == $text_perm_data_Key){
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj->permissions->data_locked = (strtoupper($data) == "YES" ? true : false);
		}
		else if ($this->curTag == $text_perm_select_Key){
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj->permissions->not_selectable = (strtoupper($data) == "YES" ? true : false);
		}
		else if ($this->curTag == $text_perm_trans_Key){
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj->permissions->not_transferable = (strtoupper($data) == "YES" ? true : false);
		}
		else if ($this->curTag == $graphic_width_Key) {
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj->width = round($data);
		}
		else if ($this->curTag == $graphic_height_Key) {
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj->height = round($data);
		}
		else if ($this->curTag == $graphic_originalheight_Key) {
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj->originalheight = $data;
		}
		else if ($this->curTag == $graphic_originalwidth_Key) {
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj->originalwidth = $data;
		}
		else if ($this->curTag == $graphic_imageid_Key) {
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj->imageid = $data;
		}
		else if ($this->curTag == $graphic_VectorImageId_Key) {
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj->vector_image_id = $data;
		}
		else if ($this->curTag == $graphic_perm_posX_Key){
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj->permissions->position_x_locked = (strtoupper($data) == "YES" ? true : false);
		}
		else if ($this->curTag == $graphic_perm_posY_Key){
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj->permissions->position_y_locked = (strtoupper($data) == "YES" ? true : false);
		}
		else if ($this->curTag == $graphic_perm_size_Key){
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj->permissions->size_locked = (strtoupper($data) == "YES" ? true : false);
		}
		else if ($this->curTag == $graphic_perm_delete_Key){
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj->permissions->deletion_locked = (strtoupper($data) == "YES" ? true : false);
		}
		else if ($this->curTag == $graphic_perm_rotate_Key){
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj->permissions->rotation_locked = (strtoupper($data) == "YES" ? true : false);
		}
		else if ($this->curTag == $graphic_perm_select_Key){
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj->permissions->not_selectable = (strtoupper($data) == "YES" ? true : false);
		}
		else if ($this->curTag == $graphic_perm_trans_Key){
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj->permissions->not_transferable = (strtoupper($data) == "YES" ? true : false);
		}
		else if ($this->curTag == $graphic_perm_onTop_Key){
			$this->SideItemsArray[$this->_SideCounter]->layers[$this->_LayerCounter]->LayerDetailsObj->permissions->always_on_top = (strtoupper($data) == "YES" ? true : false);
		}
		

	}
	
	// Build the XML file and return it
	function GetXMLdoc(){

		$retXML = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\" ?>\n";
		$retXML .= "<content>\n";
		
		$sideCounter = 0;
		
		foreach($this->SideItemsArray as $ThisSideObj){
		
			$retXML .= "<side>\n\n";
		
			$retXML .= "<description>" . $ThisSideObj->description . "</description>\n";
			$retXML .= "<initialzoom>" . $ThisSideObj->initialzoom . "</initialzoom>\n";
			$retXML .= "<rotatecanvas>" . $ThisSideObj->rotatecanvas . "</rotatecanvas>\n";
			$retXML .= "<contentwidth>" . $ThisSideObj->contentwidth . "</contentwidth>\n";
			$retXML .= "<contentheight>" . $ThisSideObj->contentheight . "</contentheight>\n";
			$retXML .= "<backgroundimage>" . $ThisSideObj->backgroundimage . "</backgroundimage>\n";
			$retXML .= "<background_x>" . $ThisSideObj->background_x . "</background_x>\n";
			$retXML .= "<background_y>" . $ThisSideObj->background_y . "</background_y>\n";
			$retXML .= "<background_width>" . $ThisSideObj->background_width . "</background_width>\n";
			$retXML .= "<background_height>" . $ThisSideObj->background_height . "</background_height>\n";
			$retXML .= "<background_color>" . $ThisSideObj->background_color . "</background_color>\n";
			$retXML .= "<scale>" . $ThisSideObj->scale . "</scale>\n";
			$retXML .= "<folds_horiz>" . $ThisSideObj->folds_horizontal . "</folds_horiz>\n";
			$retXML .= "<folds_vert>" . $ThisSideObj->folds_vertical . "</folds_vert>\n";
			$retXML .= "<dpi>" . $ThisSideObj->dpi . "</dpi>\n";
			
			if($ThisSideObj->show_boundary)
				$retXML .= "<show_boundary>yes</show_boundary>\n";
			else
				$retXML .= "<show_boundary>no</show_boundary>\n";
				
			$retXML .= "<side_permissions></side_permissions>\n";

			$retXML .= "<color_palette>\n"; 
			foreach($ThisSideObj->color_palette_entries as $thisColorPalleteEntry)
				$retXML .= "<color colorcode=\"". $thisColorPalleteEntry->colorcode ."\">" . htmlspecialchars(utf8_decode($thisColorPalleteEntry->color_description)) . "</color>\n";
			$retXML .= "</color_palette>\n";
			
			$retXML .= "<color_definitions>\n"; 
			foreach($ThisSideObj->color_definitions as $thisColorNode)
				$retXML .= "<color id=\"". $thisColorNode->id ."\">" . $thisColorNode->colorcode . "</color>\n";
			$retXML .= "</color_definitions>\n";


			// Don't show a marker image element unless we have information for it.  (in other words don't show an empty tag).
			if($ThisSideObj->markerimage){
				$retXML .= "<marker_image>\n";
				$retXML .= "<imageid>" . $ThisSideObj->markerimage->imageid . "</imageid>\n";
				$retXML .= "<x_coordinate>" . $ThisSideObj->markerimage->x_coordinate . "</x_coordinate>\n";
				$retXML .= "<y_coordinate>" . $ThisSideObj->markerimage->y_coordinate . "</y_coordinate>\n";
				$retXML .= "<width>" . $ThisSideObj->markerimage->width . "</width>\n";
				$retXML .= "<height>" . $ThisSideObj->markerimage->height . "</height>\n";
				$retXML .= "</marker_image>\n";
			}
			
			// Don't show a mask image element unless we have information for it.  (in other words don't show an empty tag).
			if($ThisSideObj->maskimage){
				$retXML .= "<mask_image>\n";
				$retXML .= "<imageid>" . $ThisSideObj->maskimage->imageid . "</imageid>\n";
				$retXML .= "<x_coordinate>" . $ThisSideObj->maskimage->x_coordinate . "</x_coordinate>\n";
				$retXML .= "<y_coordinate>" . $ThisSideObj->maskimage->y_coordinate . "</y_coordinate>\n";
				$retXML .= "<width>" . $ThisSideObj->maskimage->width . "</width>\n";
				$retXML .= "<height>" . $ThisSideObj->maskimage->height . "</height>\n";
				$retXML .= "</mask_image>\n";
			}

			$layerCounter = 0;
			
			
			foreach($ThisSideObj->layers as $ThisLayerObj){
				
				// Skip Layers that may have been removed
				if($ThisLayerObj->LayerType == "deleted")
					continue;

				$retXML .= "\n<layer>\n";
								
				
				// If the layer has a permission set to "Always on Top", then we want to double-check that it is (if not, then bring it to the top).
				if($ThisLayerObj->LayerType == "graphic" && $ThisLayerObj->LayerDetailsObj->permissions->always_on_top){

					$highestLevelInGroup = $this->GetHighestLevelInArtworkGroup($sideCounter, $ThisLayerObj->LayerType, $ThisLayerObj->LayerDetailsObj->permissions->checkForAtLeast1Permission(), true);

					if(($highestLevelInGroup - 1) > $ThisLayerObj->level){
						$ThisLayerObj->level = $highestLevelInGroup;
						$this->SideItemsArray[$sideCounter]->layers[$layerCounter]->level = $ThisLayerObj->level;
					}
				}
				
				// Text Layers with permissions on them need to be bubbled up to the top to keep flash application from caching layer objects after permissions have changed.
				if($ThisLayerObj->LayerType == "text" && $ThisLayerObj->LayerDetailsObj->permissions->checkForAtLeast1Permission()){
					
					$highestLevelInGroup = $this->GetHighestLevelInArtworkGroup($sideCounter, $ThisLayerObj->LayerType, $ThisLayerObj->LayerDetailsObj->permissions->checkForAtLeast1Permission(), true);

					if(($highestLevelInGroup - 1) > $ThisLayerObj->level){
						$ThisLayerObj->level = $highestLevelInGroup;
						$this->SideItemsArray[$sideCounter]->layers[$layerCounter]->level = $ThisLayerObj->level;
					}
				
				}
				
				

				$retXML .= "<level>" . $ThisLayerObj->level . "</level>\n";
				$retXML .= "<x_coordinate>" . $ThisLayerObj->x_coordinate . "</x_coordinate>\n";
				$retXML .= "<y_coordinate>" . $ThisLayerObj->y_coordinate . "</y_coordinate>\n";
				$retXML .= "<rotation>" . $ThisLayerObj->rotation . "</rotation>\n";

				if($ThisLayerObj->LayerType == "graphic"){
					$retXML .= "<graphic>";
					$retXML .= "<width>" . $ThisLayerObj->LayerDetailsObj->width . "</width>\n";
					$retXML .= "<height>" . $ThisLayerObj->LayerDetailsObj->height . "</height>\n";
					$retXML .= "<originalheight>" . $ThisLayerObj->LayerDetailsObj->originalheight . "</originalheight>\n";
					$retXML .= "<originalwidth>" . $ThisLayerObj->LayerDetailsObj->originalwidth . "</originalwidth>\n";
					$retXML .= "<imageid>" . $ThisLayerObj->LayerDetailsObj->imageid . "</imageid>\n";
					$retXML .= "<VectorImageId>" . $ThisLayerObj->LayerDetailsObj->vector_image_id . "</VectorImageId>\n";
					


					// Don't include a set of permission Tags if there are no permissions set... to save a bit of file space
					if($ThisLayerObj->LayerDetailsObj->permissions->checkForAtLeast1Permission()){

						$graphicPermissionsObj = $ThisLayerObj->LayerDetailsObj->permissions;

						$retXML .= "\n<permissions>\n";
						$retXML .= "<position_x_locked>" . ($graphicPermissionsObj->position_x_locked ? "yes" : "no") . "</position_x_locked>\n";
						$retXML .= "<position_y_locked>" . ($graphicPermissionsObj->position_y_locked ? "yes" : "no") . "</position_y_locked>\n";
						$retXML .= "<size_locked>" . ($graphicPermissionsObj->size_locked ? "yes" : "no") . "</size_locked>\n";
						$retXML .= "<deletion_locked>" . ($graphicPermissionsObj->deletion_locked ? "yes" : "no") . "</deletion_locked>\n";							
						$retXML .= "<rotation_locked>" . ($graphicPermissionsObj->rotation_locked ? "yes" : "no") . "</rotation_locked>\n";
						$retXML .= "<not_selectable>" . ($graphicPermissionsObj->not_selectable ? "yes" : "no") . "</not_selectable>\n";
						$retXML .= "<not_transferable>" . ($graphicPermissionsObj->not_transferable ? "yes" : "no") . "</not_transferable>\n";
						$retXML .= "<always_on_top>" . ($graphicPermissionsObj->always_on_top ? "yes" : "no") . "</always_on_top>\n";
						$retXML .= "</permissions>\n\n";					
					}


					$retXML .= "</graphic>\n";
				}
				else if($ThisLayerObj->LayerType == "text"){
					$retXML .= "<text>\n";
					$retXML .= "<font>" . $ThisLayerObj->LayerDetailsObj->font . "</font>\n";
					$retXML .= "<field_name>" . $ThisLayerObj->LayerDetailsObj->field_name . "</field_name>\n";
					
					// Don't include a set of permission Tags if there are no permissions set... to save a bit of file space
					if($ThisLayerObj->LayerDetailsObj->permissions->checkForAtLeast1Permission()){

						$textPermissionsObj = $ThisLayerObj->LayerDetailsObj->permissions;

						$retXML .= "\n<permissions>\n";
						$retXML .= "<position_x_locked>" . ($textPermissionsObj->position_x_locked ? "yes" : "no") . "</position_x_locked>\n";
						$retXML .= "<position_y_locked>" . ($textPermissionsObj->position_y_locked ? "yes" : "no") . "</position_y_locked>\n";
						$retXML .= "<size_locked>" . ($textPermissionsObj->size_locked ? "yes" : "no") . "</size_locked>\n";
						$retXML .= "<deletion_locked>" . ($textPermissionsObj->deletion_locked ? "yes" : "no") . "</deletion_locked>\n";
						$retXML .= "<color_locked>" . ($textPermissionsObj->color_locked ? "yes" : "no") . "</color_locked>\n";
						$retXML .= "<font_locked>" . ($textPermissionsObj->font_locked ? "yes" : "no") . "</font_locked>\n";
						$retXML .= "<alignment_locked>" . ($textPermissionsObj->alignment_locked ? "yes" : "no") . "</alignment_locked>\n";
						$retXML .= "<rotation_locked>" . ($textPermissionsObj->rotation_locked ? "yes" : "no") . "</rotation_locked>\n";
						$retXML .= "<data_locked>" . ($textPermissionsObj->data_locked ? "yes" : "no") . "</data_locked>\n";
						$retXML .= "<not_transferable>" . ($textPermissionsObj->not_transferable ? "yes" : "no") . "</not_transferable>\n";
						$retXML .= "<not_selectable>" . ($textPermissionsObj->not_selectable ? "yes" : "no") . "</not_selectable>\n";
						$retXML .= "</permissions>\n\n";
					}
					
					
					$retXML .= "<size>" . $ThisLayerObj->LayerDetailsObj->size . "</size>\n";
					$retXML .= "<bold>" . $ThisLayerObj->LayerDetailsObj->bold . "</bold>\n";
					$retXML .= "<italics>" . $ThisLayerObj->LayerDetailsObj->italics . "</italics>\n";
					$retXML .= "<underline>" . $ThisLayerObj->LayerDetailsObj->underline . "</underline>\n";
					$retXML .= "<field_order>" . $ThisLayerObj->LayerDetailsObj->field_order . "</field_order>\n";
					$retXML .= "<shadow_level_link>" . $ThisLayerObj->LayerDetailsObj->shadow_level_link . "</shadow_level_link>\n";
					$retXML .= "<shadow_distance>" . $ThisLayerObj->LayerDetailsObj->shadow_distance . "</shadow_distance>\n";
					$retXML .= "<shadow_angle>" . $ThisLayerObj->LayerDetailsObj->shadow_angle . "</shadow_angle>\n";
					$retXML .= "<align>" . $ThisLayerObj->LayerDetailsObj->align . "</align>\n";
					
					// utf8_decode() was needed because some symbols were getting messed up ... like htmlspecialchars(utf8_decode("©"))  works... by htmlspecialchars("©") puts out a double byte like ... Â©
					// Be Careful never to call utf8_decode or encode twice on the same string.  So we decode it putting it back into XML... but have to encode the entire XML file in the constructor before parsing for the Xpat proccessor to parse.
					$retXML .= "<message>" . htmlspecialchars(utf8_decode($ThisLayerObj->LayerDetailsObj->message)) . "</message>\n";
					$retXML .= "<color>" . $ThisLayerObj->LayerDetailsObj->color . "</color>\n";
					$retXML .= "</text>\n";
				}
				else{
					print "Error with Layer type";
					exit;
				}

				$retXML .= "</layer>\n";
				
				$layerCounter++;
			}
			
			$retXML .= "</side>\n\n\n\n\n";
			
			$sideCounter++;
		}
		
		$retXML .= "</content>\n";
		
		return $retXML;
	
	
	}
	



	// Returns the highest layer level type for the group... and ads 1.
	// Text with "Permissions" them need to have the layer levels changed to keep our flash application from caching layer objects (with old permissions).
	// Pass  (boolean flag) to see if you want the highest level to include layers which have the "Always on Top" permission set to True
	function GetHighestLevelInArtworkGroup($SideNumber, $LayerType, $atLeast1permissionsFlag = false, $includeAlwaysOnTopLayersFlag = false){

		//The Default level for text is always mush higher than images.  That is because Text always sits on top.


		if($LayerType == "graphic"){
			$maxLevel = 200;
		}
		else if($LayerType == "text" && $atLeast1permissionsFlag){
			$maxLevel = 12005;
		}
		else if($LayerType == "text"){
			$maxLevel = 10005;
		}
		else{
			print "Invalid Layer Type";
			exit;
		}

		if(isset($this->SideItemsArray[$SideNumber])){


			for($i=0; $i<sizeof($this->SideItemsArray[$SideNumber]->layers); $i++){
			
				// Skip Layers that may have already been removed.
				if($this->SideItemsArray[$SideNumber]->layers[$i]->LayerType == "deleted")
					continue;
				
				// If the layer we are looping on now has Permissions set to always have it on top... but we don't want to include that in our counting... then skip this layer.
				if(!$includeAlwaysOnTopLayersFlag && $this->SideItemsArray[$SideNumber]->layers[$i]->LayerDetailsObj->permissions->always_on_top)
					continue;
				
				// Keep the layers with text permissions and the ones without permissions on different levels.
				if(!$atLeast1permissionsFlag && $this->SideItemsArray[$SideNumber]->layers[$i]->LayerDetailsObj->permissions->checkForAtLeast1Permission())
					continue;
				
				if($this->SideItemsArray[$SideNumber]->layers[$i]->LayerType == $LayerType){
					if($this->SideItemsArray[$SideNumber]->layers[$i]->level >= $maxLevel)
						$maxLevel = $this->SideItemsArray[$SideNumber]->layers[$i]->level + 1;
				}
			}
		}

		return $maxLevel;
	}
	
	
	// Will add a new graphic layer to the given side.
	// It does not verify the ImageID, so make sure you know it is correct.
	// Graphic layer will always be recorded at the next highest layer level.
	// Returns the Layer number that was created.
	function AddGraphicToArtwork($SideNumber, $ImageID, $vectorImageID, $xcoord, $ycoord, $rotation, $width, $height){
	
		$NewLevel = $this->GetHighestLevelInArtworkGroup($SideNumber, "graphic");
	
		// Get the next highest layer number
		// Because the array is 0 based... the size function will always put us at the next number
		$LayerNumber = sizeof($this->SideItemsArray[$SideNumber]->layers);
		
		$this->SideItemsArray[$SideNumber]->layers[$LayerNumber] = new LayerItem();
		
		$this->SideItemsArray[$SideNumber]->layers[$LayerNumber]->level = $NewLevel;
		$this->SideItemsArray[$SideNumber]->layers[$LayerNumber]->x_coordinate = $xcoord;
		$this->SideItemsArray[$SideNumber]->layers[$LayerNumber]->y_coordinate = $ycoord;
		$this->SideItemsArray[$SideNumber]->layers[$LayerNumber]->rotation = $rotation;
		$this->SideItemsArray[$SideNumber]->layers[$LayerNumber]->LayerType = "graphic";
		
		$this->SideItemsArray[$SideNumber]->layers[$LayerNumber]->LayerDetailsObj = new GraphicItem();
		$this->SideItemsArray[$SideNumber]->layers[$LayerNumber]->LayerDetailsObj->width = $width;
		$this->SideItemsArray[$SideNumber]->layers[$LayerNumber]->LayerDetailsObj->height = $height;
		$this->SideItemsArray[$SideNumber]->layers[$LayerNumber]->LayerDetailsObj->originalheight = $height;
		$this->SideItemsArray[$SideNumber]->layers[$LayerNumber]->LayerDetailsObj->originalwidth = $width;
		$this->SideItemsArray[$SideNumber]->layers[$LayerNumber]->LayerDetailsObj->imageid = $ImageID;
		$this->SideItemsArray[$SideNumber]->layers[$LayerNumber]->LayerDetailsObj->vector_image_id = $vectorImageID;

		return $LayerNumber;
	}
	

	// Pass in a Layer Object... the function copy over any attributes such as size/color/font/etc...
	// The target layer is the SideNumber and particular layer level... Does not change the content of the layer. 
	function TransferAttributes($SideNumber, $LayerLevel, $NewLayerObj){
	
	
		$LayerID = $this->GetLayerID($SideNumber, $LayerLevel);
		if($LayerID <> -1){		
			$this->SideItemsArray[$SideNumber]->layers[$LayerID]->x_coordinate = $NewLayerObj->x_coordinate;
			$this->SideItemsArray[$SideNumber]->layers[$LayerID]->y_coordinate = $NewLayerObj->y_coordinate;
			$this->SideItemsArray[$SideNumber]->layers[$LayerID]->rotation = $NewLayerObj->rotation;



			if($NewLayerObj->LayerType == "graphic"){
				$this->SideItemsArray[$SideNumber]->layers[$LayerID]->LayerDetailsObj->width = $NewLayerObj->LayerDetailsObj->width;
				$this->SideItemsArray[$SideNumber]->layers[$LayerID]->LayerDetailsObj->height = $NewLayerObj->LayerDetailsObj->height;
			}
			else if($NewLayerObj->LayerType == "text"){
				$this->SideItemsArray[$SideNumber]->layers[$LayerID]->LayerDetailsObj->font = $NewLayerObj->LayerDetailsObj->font;
				$this->SideItemsArray[$SideNumber]->layers[$LayerID]->LayerDetailsObj->size = $NewLayerObj->LayerDetailsObj->size;
				$this->SideItemsArray[$SideNumber]->layers[$LayerID]->LayerDetailsObj->bold = $NewLayerObj->LayerDetailsObj->bold;
				$this->SideItemsArray[$SideNumber]->layers[$LayerID]->LayerDetailsObj->italics = $NewLayerObj->LayerDetailsObj->italics;
				$this->SideItemsArray[$SideNumber]->layers[$LayerID]->LayerDetailsObj->underline = $NewLayerObj->LayerDetailsObj->underline;
				$this->SideItemsArray[$SideNumber]->layers[$LayerID]->LayerDetailsObj->align = $NewLayerObj->LayerDetailsObj->align;
				$this->SideItemsArray[$SideNumber]->layers[$LayerID]->LayerDetailsObj->color = $NewLayerObj->LayerDetailsObj->color;
				$this->SideItemsArray[$SideNumber]->layers[$LayerID]->LayerDetailsObj->field_name = $NewLayerObj->LayerDetailsObj->field_name;
				$this->SideItemsArray[$SideNumber]->layers[$LayerID]->LayerDetailsObj->field_order = $NewLayerObj->LayerDetailsObj->field_order;
				$this->SideItemsArray[$SideNumber]->layers[$LayerID]->LayerDetailsObj->shadow_distance = $NewLayerObj->LayerDetailsObj->shadow_distance;
				$this->SideItemsArray[$SideNumber]->layers[$LayerID]->LayerDetailsObj->shadow_angle = $NewLayerObj->LayerDetailsObj->shadow_angle;
			
			
				// Find out if this layer has a shadow associated with it, in which case transfer the attributes to that as well
				if($this->CheckIfTextLayerHasShadow($SideNumber, $LayerLevel)){
				
					$shadowLayerLevel = $this->GetLayerLevelOfShadowLayer($SideNumber, $LayerLevel);
					$ShadowLayerID = $this->GetLayerID($SideNumber, $shadowLayerLevel);

					$shadowCoordinatesHash = $this->GetCoordinatesForShadowLayer($SideNumber, $LayerLevel);
					$this->SideItemsArray[$SideNumber]->layers[$ShadowLayerID]->x_coordinate = $shadowCoordinatesHash["x"];
					$this->SideItemsArray[$SideNumber]->layers[$ShadowLayerID]->y_coordinate = $shadowCoordinatesHash["y"];
					$this->SideItemsArray[$SideNumber]->layers[$ShadowLayerID]->rotation = $NewLayerObj->rotation;

					
					// For the text attributes 
					// We do not want to transfer the color attribute over, the shadow color is controlled independently
					$this->SideItemsArray[$SideNumber]->layers[$ShadowLayerID]->LayerDetailsObj->font = $NewLayerObj->LayerDetailsObj->font;
					$this->SideItemsArray[$SideNumber]->layers[$ShadowLayerID]->LayerDetailsObj->size = $NewLayerObj->LayerDetailsObj->size;
					$this->SideItemsArray[$SideNumber]->layers[$ShadowLayerID]->LayerDetailsObj->bold = $NewLayerObj->LayerDetailsObj->bold;
					$this->SideItemsArray[$SideNumber]->layers[$ShadowLayerID]->LayerDetailsObj->italics = $NewLayerObj->LayerDetailsObj->italics;
					$this->SideItemsArray[$SideNumber]->layers[$ShadowLayerID]->LayerDetailsObj->underline = $NewLayerObj->LayerDetailsObj->underline;
					$this->SideItemsArray[$SideNumber]->layers[$ShadowLayerID]->LayerDetailsObj->align = $NewLayerObj->LayerDetailsObj->align;
				}
			
			}
		}	
	}
	
	// This function will return an array with an X & Y coordinate for where a cooresponding Shadow layer should be positioned
	// You must be sure that the LayerLevel you are passing into this function has a shadow that is associated with it.  
	function GetCoordinatesForShadowLayer($SideNumber, $LayerLevel){
	
		if(!$this->CheckIfTextLayerHasShadow($SideNumber, $LayerLevel))
			throw new Exception("Error in function call GetCoordinatesForShadowLayer.  The layer must have a shadow.");
		
		$layerObj = $this->GetLayerObject($SideNumber, $LayerLevel);
		
		$xCoord = $layerObj->x_coordinate;
		$yCoord = $layerObj->y_coordinate;
		$layerRotation = $layerObj->rotation;
		$shadowDistance = $layerObj->LayerDetailsObj->shadow_distance;
		$shadowAngle = $layerObj->LayerDetailsObj->shadow_angle;
	
		$retArr["y"] = $yCoord + $shadowDistance * sin(pi()/180 * ($layerRotation + $shadowAngle));
		$retArr["x"] = $xCoord + $shadowDistance * cos(pi()/180 * ($layerRotation + $shadowAngle));
		
		return $retArr;
	}


	
	// Will apply a given attribute to a particular layer and Side number
	function ChangeAttribute($SideNumber, $LayerLevel, $AttributeType, $AttributeValue){

		$LayerID = $this->GetLayerID($SideNumber, $LayerLevel);
		if($LayerID <> -1){
		
			#-- Specify which commands belong to layers... and which commands belong to Text objects
			$LayerAttributes = array("x_coordinate", "y_coordinate", "rotation");
			$TextObjectAttributes = array("font", "size", "bold", "italics", "underline", "align", "field_name", "field_order");

			if($AttributeType == "color"){
			
				//The color format should come in with a # prefix and a hexidecimal value
				$colorInHex = preg_replace("/#/", "", $AttributeValue); 
				
				//We need to store the color code as an integer within the XML file to keep things consistant
				$ColorInIntegerFormat = intval($colorInHex, 16);
				$this->SideItemsArray[$SideNumber]->layers[$LayerID]->LayerDetailsObj->color = $ColorInIntegerFormat;
			}
			else if(in_array($AttributeType, $LayerAttributes)){
				eval('$this->SideItemsArray[' . $SideNumber. ']->layers[' . $LayerID . ']->' . $AttributeType . ' = $AttributeValue;' );
			}
			else if(in_array($AttributeType, $TextObjectAttributes)){
				eval('$this->SideItemsArray[' . $SideNumber. ']->layers[' . $LayerID . ']->LayerDetailsObj->' . $AttributeType . ' = $AttributeValue;' );
			}
			else{
				print "Illegal Attribute Type";
				exit;
			}
		

		}
	
	
	
	}
	
	
	// If there are color definitions on the side, this method will return the colors that the user has selected.
	// If there are not color definitions, this method will return an empty array.
	// If there is a "limited color palette", this method will return the color descriptions, otherwise it will return the color codes in hex format.
	function GetColorDescriptions($SideNumber){
	
		if(!isset($this->SideItemsArray[$SideNumber]))
			throw new Exception("The side does not exist.");
			
		$ThisSideObj = $this->SideItemsArray[$SideNumber];
		
		$retArr = array();
		
		foreach($ThisSideObj->color_definitions as $thisColorNode){
			
			$colorCodeDecimal = $thisColorNode->colorcode;
			
			// If there are not color definitions, then use the HEX code.
			if(empty($ThisSideObj->color_palette_entries)){
				$colorLibObj = new ColorLib();
				$rgbValues = $colorLibObj->getRgbValues($colorCodeDecimal, true);
				$retArr[] = "#" . $rgbValues->red . $rgbValues->green . $rgbValues->blue;
			}
			else{
				$colorDescription = "Unknown";
				
				foreach($ThisSideObj->color_palette_entries as $thisColorPalleteEntry){
					if($thisColorPalleteEntry->colorcode == $colorCodeDecimal){
						$colorDescription = utf8_decode($thisColorPalleteEntry->color_description);
						break;
					}
				}
				
				$retArr[] = $colorDescription;
			}
		}
		
		return $retArr;
	}
	
	// Remove all Graphics from the side... or remove all Text Layers.
	function RemoveLayerTypeFromSide($SideNumber, $layerTypeToRemove){
	
		if(!isset($this->SideItemsArray[$SideNumber]))
			return;

		for($i=0; $i<sizeof($this->SideItemsArray[$SideNumber]->layers); $i++){
			
			if($this->SideItemsArray[$SideNumber]->layers[$i]->LayerType == $layerTypeToRemove)
				$this->RemoveLayerFromArtworkObj($SideNumber, $this->SideItemsArray[$SideNumber]->layers[$i]->level);
		}
			
		$this->unsetDeletedLayers();
	}
	



	// Pass in a layer level.  It will try to remove the layer if it is found.
	function RemoveLayerFromArtworkObj($SideNumber, $LayerLevel){

		$LayerID = $this->GetLayerID($SideNumber, $LayerLevel);
		if($LayerID == -1)
			return;
			
		$layerObj = $this->GetLayerObject($SideNumber, $LayerLevel);

		// If a Text Layer has a shadow, then the shadow should get removed too.
		if($layerObj->LayerType == "text" && $this->CheckIfTextLayerHasShadow($SideNumber, $LayerLevel)){
			$shadowLayerLevel = $this->GetLayerLevelOfShadowLayer($SideNumber, $LayerLevel);
			$ShadowLayerID = $this->GetLayerID($SideNumber, $shadowLayerLevel);
			if($ShadowLayerID != -1)
				$this->SideItemsArray[$SideNumber]->layers[$ShadowLayerID]->LayerType = "deleted";
		}

		// Do not unset() the element in the array because it could reorganize the indexes of the Array
		// There may be multiple loops running over layers checking/removing/adding layers... and we don't want to interfere.
		$this->SideItemsArray[$SideNumber]->layers[$LayerID]->LayerType = "deleted";
	}

	// Will add a layer object... it will assign the next highest layer level available.  Kind of like an auto increment
	// returns the new Layer Level
	function AddLayerObjectToSide($SideNumber, $LayerObj){
		
		$LayerObjCopy = unserialize(serialize($LayerObj));

		$NewLayerLevel = $this->GetHighestLevelInArtworkGroup($SideNumber, $LayerObjCopy->LayerType, $LayerObjCopy->LayerDetailsObj->permissions->checkForAtLeast1Permission());

		$LayerObjCopy->level = $NewLayerLevel;

		if(isset($this->SideItemsArray[$SideNumber]))
			array_push($this->SideItemsArray[$SideNumber]->layers, $LayerObjCopy);
		
		return $NewLayerLevel;

	}
	
	
	// Will return true or false whether the given Layer Level is a shadow to another Text layer
	// In which case we may not want to show that text layer on the Clipboard, etc.
	function CheckIfTextLayerIsShadowToAnotherLayer($SideNumber, $LayerLevel){
	
		if(!isset($this->SideItemsArray[$SideNumber]))
			throw new Exception("The side number was not found in the method call CheckIfTextLayerIsShadowToAnotherLayer: $SideNumber");


		for($i=0; $i<sizeof($this->SideItemsArray[$SideNumber]->layers); $i++){

			if($this->SideItemsArray[$SideNumber]->layers[$i]->LayerType == "text"){
				if($this->SideItemsArray[$SideNumber]->layers[$i]->LayerDetailsObj->shadow_level_link == $LayerLevel)
					return true;
			}
		}
		
		return false;
	}
	
	// If you make a change to a text layer... you should call this method to make sure that shadow layer (if any) gets the new text.
	// It will also make sure that the font names match the parent.
	function makeShadowTextMatchParent($SideNumber, $parentLayerLevel){
		
		$parentLayerObj = $this->GetLayerObject($SideNumber, $parentLayerLevel);
		if($parentLayerObj->LayerType != "text")
			throw new Exception("Can only call this method on Text Layers");
		
		if($this->CheckIfTextLayerHasShadow($SideNumber, $parentLayerLevel)){
			$shadowLayerLevel = $this->GetLayerLevelOfShadowLayer($SideNumber, $parentLayerLevel);
			$shadowLayerIndex = $this->GetLayerID($SideNumber, $shadowLayerLevel);
			
			$this->SideItemsArray[$SideNumber]->layers[$shadowLayerIndex]->LayerDetailsObj->message = $parentLayerObj->LayerDetailsObj->message;
			$this->SideItemsArray[$SideNumber]->layers[$shadowLayerIndex]->LayerDetailsObj->font = $parentLayerObj->LayerDetailsObj->font;
		}
	}
	
	// Returns true or false depending on whether the Text Layer has a shadow layer associated with it.
	function CheckIfTextLayerHasShadow($SideNumber, $LayerLevel){
	
		$layerNumber = $this->GetLayerID($SideNumber, $LayerLevel);
		if($layerNumber == -1)
			throw new Exception("The given layer Level does not exist in the method call to CheckIfTextLayerHasShadow: $LayerLevel");
	
		$LayerObj = $this->SideItemsArray[$SideNumber]->layers[$layerNumber];
		
		if($LayerObj->LayerType != "text")
			throw new Exception("Error in method CheckIfTextLayerHasShadow.  The layer level must belong to a text layer: $LayerLevel");

		if($LayerObj->LayerDetailsObj->shadow_level_link != "")
			return true;
		else
			return false;
	}
	
	
	// Returns the layer level of a Shadow Layer.... that belongs to the given Layer Level.
	// If the given layer level does not exist then the method will fail critically... so make sure to fist check it with the method CheckIfTextLayerHasShadow
	function GetLayerLevelOfShadowLayer($SideNumber, $LayerLevel){
	
		if(!$this->CheckIfTextLayerHasShadow($SideNumber, $LayerLevel))
			throw new Exception("Error in method call GetLayerLevelOfShadowLayer... The layer level does not have a shadow associated to it: $LayerLevel");
	
		$thisLayerObj = $this->GetLayerObject($SideNumber, $LayerLevel);
	
		return $thisLayerObj->LayerDetailsObj->shadow_level_link;
	}

	// Returns the layer Hash KEY.. or ID of a layer if the Layer Level and is found on the Side number
	// Returns -1 if it is not found.
	function GetLayerID($SideNumber, $LayerLevel){
		
		if(isset($this->SideItemsArray[$SideNumber])){
			for($i=0; $i<sizeof($this->SideItemsArray[$SideNumber]->layers); $i++){
			
				// Skip Layers that may have already been removed.
				if($this->SideItemsArray[$SideNumber]->layers[$i]->LayerType == "deleted")
					continue;
				
				if($LayerLevel == $this->SideItemsArray[$SideNumber]->layers[$i]->level)
					return $i;
			}
		}
		return -1;
	
	}
	
	// Returns the Layer object associated with the given LayerLevel
	// fails with an error if it is not found.
	function GetLayerObject($SideNumber, $LayerLevel){

		if(!isset($this->SideItemsArray[$SideNumber]))
			throw new Exception("The side number does not exist in the method GetLayerObject");


		for($i=0; $i<sizeof($this->SideItemsArray[$SideNumber]->layers); $i++){
		
			// Skip Layers that may have already been removed.
			if($this->SideItemsArray[$SideNumber]->layers[$i]->LayerType == "deleted")
				continue;

			if($this->SideItemsArray[$SideNumber]->layers[$i]->level == $LayerLevel){
				$layerObj = $this->SideItemsArray[$SideNumber]->layers[$i];
				$layerObjCopy = unserialize(serialize($layerObj));
				return $layerObjCopy;
			}
		}
		
		throw new Exception("The layer level was not found in the method call GetLayerObject");
	}
	
	
	// Pass in the Layer level of a text layer or a Graphic

	// Rerturn true if the Layer is outside of the canvas area
	// Images must have all parts of the image outside.
	// Text layers are a little more tricky since there is not a good way to estimate the bounding box of true type fonts.
	// .... for text layers, if the registration point falls outside of the canvas area we return TRUE.  There could be parts of that layer inside of the canvas though (so don't trust it 100%).
	function CheckIfLayerIsOutsideOfCanvas($SideNumber, $LayerLevel){

		if(!isset($this->SideItemsArray[$SideNumber]))
			throw new Exception("The side number does not exist in the method CheckIfLayerIsOutsideOfCanvas");

		$layerObj = $this->GetLayerObject($SideNumber, $LayerLevel);
		
		$canvasWidth = $xBoundary = $this->SideItemsArray[$SideNumber]->contentwidth;
		$canvasHeight = $xBoundary = $this->SideItemsArray[$SideNumber]->contentheight;
				
		if($layerObj->LayerType == "text"){
		
			// Add a little extra buffer room for text layers... This it is not an exact science.
			$canvasWidth += $layerObj->LayerDetailsObj->size * 0.9;
			$canvasHeight += $layerObj->LayerDetailsObj->size * 0.9;
			
			// The registration point for the canvas is in the very center
			$xBoundary = $canvasWidth / 2;
			$yBoundary = $canvasHeight / 2;
			
			if($layerObj->x_coordinate < -($xBoundary) || $layerObj->x_coordinate > $xBoundary)
				return true;
				
			if($layerObj->y_coordinate < -($yBoundary) || $layerObj->y_coordinate > $yBoundary)
				return true;
		
		}
		else if($layerObj->LayerType == "graphic"){
		
			// The registration point for the canvas is in the very center
			$xBoundary = $canvasWidth / 2;
			$yBoundary = $canvasHeight / 2;
			
			$halfImageWidth = $layerObj->LayerDetailsObj->width / 2;
			$halfImageHeight = $layerObj->LayerDetailsObj->height / 2;
			
			if(abs($layerObj->rotation) == 90 || abs($layerObj->rotation) == 270){
				$temp = $halfImageWidth;
				$halfImageWidth = $halfImageHeight;
				$halfImageHeight = $temp;
			}
			
			// The registration for the image is in the center of the image
			// So to determine if it is outside of the canvas we need to factor in how wide and tall it is.
			if(($layerObj->x_coordinate + $halfImageWidth) < -($xBoundary) || ($layerObj->x_coordinate - $halfImageWidth) > $xBoundary)
				return true;
			if(($layerObj->y_coordinate + $halfImageHeight) < -($yBoundary) || ($layerObj->y_coordinate - $halfImageHeight) > $yBoundary)
				return true;
		}
		else
			throw new Exception("Illegal Layer type in method CheckIfLayerIsOutsideOfCanvas");
	
		return false;
	}
	
	
	// Allows you to change the layer Level.  Pass in the old one and then the new one.
	// It will fail if the NewLevel is already occupied... so make sure to check that first.
	// It will also fail if the old level does not exist.
	function ChangeLayerLevel($SideNumber, $oldLevel, $newLevel){
	
		if(!isset($this->SideItemsArray[$SideNumber]))
			throw new Exception("The side number does not exist in the method ChangeLayerLevel");
			
		$layerIndex = $this->GetLayerID($SideNumber, $oldLevel);
		
		if($layerIndex < 0)
			throw new Exception("Error in Method ChangeLayerLevel.  The OldLevel does not exist.");
			
		if(!$this->CheckIfLayerLevelAvailable($SideNumber, $newLevel))
			throw new Exception("Error in Method ChangeLayerLevel.  The new level is not available.");
		
		$this->SideItemsArray[$SideNumber]->layers[$layerIndex]->level = $newLevel;
	}
	
	
	// Returns true if the Layer level is not yet occupied on the given side.
	function CheckIfLayerLevelAvailable($SideNumber, $LayerLevel){

		if(!isset($this->SideItemsArray[$SideNumber]))
			throw new Exception("The side number does not exist in the method CheckIfLayerLevelAvailable");

		for($i=0; $i<sizeof($this->SideItemsArray[$SideNumber]->layers); $i++){
			
			// Don't count Layers that have been marked for deletion.
			if($this->SideItemsArray[$SideNumber]->layers[$i]->LayerType == "deleted")
				continue;
			
			if($LayerLevel == $this->SideItemsArray[$SideNumber]->layers[$i]->level)
				return false;
		}

		return true;
	}
	
	
	
	// Make sure that the layer level belongs to a Graphic or it will exit with an error
	// Returns True if the Given Image covers the Background Completely
	function CheckIfImageCoversBackground($SideNumber, $LayerLevel){
	

		if(!isset($this->SideItemsArray[$SideNumber]))
			throw new Exception("The side number does not exist in the method CheckIfLayerIsOutsideOfCanvas");

		$layerObj = $this->GetLayerObject($SideNumber, $LayerLevel);

		if($layerObj->LayerType != "graphic")
			throw new Exception("The Layer Level must belong to a graphic in the method CheckIfImageCoversBackground");

		$canvasWidth = $this->SideItemsArray[$SideNumber]->contentwidth;
		$canvasHeight = $this->SideItemsArray[$SideNumber]->contentheight;

		$ImageWidth = $layerObj->LayerDetailsObj->width;
		$ImageHeight = $layerObj->LayerDetailsObj->height;

		if(abs($layerObj->rotation) == 90 || abs($layerObj->rotation) == 270){
			$temp = $ImageWidth;
			$ImageWidth = $ImageHeight;
			$ImageHeight = $temp;
		}
		
		// The registration point for the canvas is in the very center
		$pastLeftBoundary = ($layerObj->x_coordinate - $ImageWidth/2 <= -($canvasWidth/2)) ? true : false;
		$pastRightBoundary = ($layerObj->x_coordinate + $ImageWidth/2 >= $canvasWidth/2) ? true : false;
		$pastTopBoundary =  ($layerObj->y_coordinate - $ImageHeight/2 <= -($canvasHeight/2)) ? true : false;
		$pastBottomBoundary =  ($layerObj->y_coordinate + $ImageHeight/2 >= $canvasHeight/2) ? true : false;
		
		if($pastLeftBoundary && $pastRightBoundary && $pastTopBoundary && $pastBottomBoundary)
			return true;
		else
			return false;

	}
	
	
	// This function is helpful to determine if we should automatically rotate the Canvas area.
	// Will Snap to the nearest 90 degrees.
	// If there are no text layers... then it will default to 0.
	function getAverageRotationOfTextLayers($SideNumber){
	
		if(!isset($this->SideItemsArray[$SideNumber]))
			throw new Exception("The side number does not exist in the method getAverageRotationOfTextLayers: $SideNumber");
			
		$totalTextLayers = 0;
		$sumOfRotations = 0;
		
		for($i=0; $i<sizeof($this->SideItemsArray[$SideNumber]->layers); $i++){
			
			// Don't count Layers that have been marked for deletion.
			if($this->SideItemsArray[$SideNumber]->layers[$i]->LayerType != "text")
				continue;
			
			$thisLayerRotation = $this->SideItemsArray[$SideNumber]->layers[$i]->rotation;
			
			// Snap to the nearest 90 at every layer level.
			$sumOfRotations += round($thisLayerRotation / 90) * 90;
			$totalTextLayers++;
		}
		
		if(empty($totalTextLayers))
			return 0;
		else
			return intval(round($sumOfRotations / $totalTextLayers / 90) * 90);
	}
	
	
	// Returns the ImaqeIDs from a Side.
	// Does Not return Vector Image IDs
	// Does not include Background Images.
	function getRasterImageIDsFromSide($SideNumber){
	
		if(!isset($this->SideItemsArray[$SideNumber]))
			throw new Exception("The side number does not exist in the method getRasterImageIDsFromSide: $SideNumber");
			
		$imageIDarr = array();
		
		for($i=0; $i<sizeof($this->SideItemsArray[$SideNumber]->layers); $i++){
			
			if($this->SideItemsArray[$SideNumber]->layers[$i]->LayerType != "graphic")
				continue;
				
			$imageIDarr[] = $this->SideItemsArray[$SideNumber]->layers[$i]->LayerDetailsObj->imageid;
		}
		
		return $imageIDarr;
	}



	// Reorganizes the Layer Objects in there array based upon Layer Level.
	function orderLayersByLayerLevel($SideNumber){

		if(!isset($this->SideItemsArray[$SideNumber]))
			throw new Exception("The side number does not exist in the method GetLayerObject: $SideNumber");

		$this->SideItemsArray[$SideNumber]->orderLayersByLayerLevel();

	}
	
	function orderLayersByLayerLevelOnAllSides(){
	
		for($i=0; $i< sizeof($this->SideItemsArray); $i++){
			if(isset($this->SideItemsArray[$i]))
				$this->SideItemsArray[$i]->orderLayersByLayerLevel();
		}

	}
	
	
	// Callign the method RemoveLayerFromArtworkObj does not actually remove the layer... it just marks it as deleted.
	// Calling this method afterwards will actually unset the layer(s) marked as deleted from the Object.
	// Be careful because it can re-organize the indexes of the Layer Array.
	function unsetDeletedLayers(){
	
		$this->orderLayersByLayerLevelOnAllSides();
	
	}

	// Returns true if only images are found... NO text  AND there are No Vector Images like PDF or EPS files.
	// Returns false if some text of substance is found..  Blank spaces don't count
	// This is useful because sometimes you can anticipate the quality will be bad if they are uploading Black rasterized text on a business card for example.
	function checkForEmptyTextOnNonVectorArtwork(){

		// Now loop through all sides and all layers within each side
		for($i=0; $i< sizeof($this->SideItemsArray); $i++){
			foreach($this->SideItemsArray[$i]->layers as $LayerObj){
	
				if($LayerObj->LayerType == "graphic" && !empty($LayerObj->LayerDetailsObj->vector_image_id)){
					return false;
				}
	
				if($LayerObj->LayerType == "text"){
					if(!preg_match("/^\s*$/", $LayerObj->LayerDetailsObj->message))
						return false;
				}
			}
		}
		
		return true;
	}
}



// Define Classes that we will use to store all of the XML information into.
class TextItem {
	public $font;
	public $size;
	public $bold;
	public $italics;
	public $underline;
	public $align;
	public $message;
	public $shadow_level_link;
	public $shadow_distance;
	public $shadow_angle;
	public $color;
	public $field_name;
	public $field_order;
	public $permissions;
	
	// constructor
	function TextItem(){
		$this->permissions = new TextPermissions();
	}
}


class TextPermissions {
	public $position_x_locked;
	public $position_y_locked;
	public $size_locked;
	public $deletion_locked;
	public $color_locked;
	public $font_locked;
	public $alignment_locked;
	public $rotation_locked;
	public $data_locked;
	public $not_selectable;
	public $always_on_top; // We don't really worry about keeping text layers on top... but initilialize the variable here just for polymorphism sake (relating to graphic layers).
	public $not_transferable;


	// Returns TRUE if there is at least 1 permission that has its flag set to true.
	// Otherwise there is no point in creating so many empty XML tags if there are no permissions set to true.
	function checkForAtLeast1Permission(){
	
		$retFlag = ($this->position_x_locked || $this->position_y_locked || $this->size_locked || 
				$this->deletion_locked || $this->color_locked || $this->font_locked || 
				$this->alignment_locked || $this->rotation_locked || $this->data_locked || $this->not_selectable || $this->not_transferable );
		return $retFlag;
	}
}

class GraphicItem {
	public $width;
	public $height;
	public $originalheight;
	public $originalwidth;
	public $imageid;
	public $vector_image_id;
	public $permissions;
	
	// constructor

	function GraphicItem(){
		$this->permissions = new GraphicPermissions();
	}
}


class GraphicPermissions {
	public $position_x_locked;
	public $position_y_locked;
	public $size_locked;
	public $deletion_locked;
	public $rotation_locked;
	public $not_selectable;
	public $not_transferable;
	public $always_on_top;


	// Returns TRUE if there is at least 1 permission that has its flag set to true.
	// Otherwise there is no point in creating so many empty XML tags if there are no permissions set to true.
	function checkForAtLeast1Permission(){
	
		$retFlag = ($this->position_x_locked || $this->position_y_locked || $this->size_locked || 
				$this->deletion_locked || $this->rotation_locked || $this->not_selectable  || $this->always_on_top || $this->not_transferable );

		return $retFlag;
	}
}

// Marker Images usually used in place of Bleed/Safe lines
// It may be used for irregular shapes (othen than a rectangle canvas) like a double sided envelope
// The Marker Image should always sit on top of everything else and not be movable.
class MarkerImage {
	public $width;
	public $height;
	public $x_coordinate;
	public $y_coordinate;
	public $imageid;
	public $imagepath; // The Image Path can be used to override an ImageID.  It should be a path to an image on the local disk. 
}

// Mask Images usually accompany Marker Images
// The Mask image is usually a white rectangle with the irregular shape cut out in the center (transparent)
// This will lay on top of everything (except the Marker Image).  It is a way to crop off everything around the irregular shape boundary.
// Mask images are not seen in the editing tool, but are used when generating a PDF document, etc.
class MaskImage {
	public $width;
	public $height;
	public $x_coordinate;
	public $y_coordinate;
	public $imageid;
}

class LayerItem {
	public $level;
	public $x_coordinate;
	public $y_coordinate;
	public $rotation;
	public $LayerType; 	// Can only be "text", "graphic", or "deleted"

	public $LayerDetailsObj;
}


class SideItem {
	public $description;
	public $initialzoom;
	public $rotatecanvas;
	public $contentwidth;
	public $contentheight;
	public $backgroundimage;
	public $background_x;
	public $background_y;
	public $background_width;
	public $background_height;
	public $background_color;
	public $show_boundary;  // If we have a marker image then we may not want the editing tool to draw its default rectangular boundary
	public $markerimage;
	public $maskimage;
	public $folds_horizontal;
	public $folds_vertical;
	public $scale;
	public $dpi;

	public $color_palette_entries = array();
	public $color_definitions = array();
	
	public $layers = array();


	// This is the contructor
	// I added this variable to the XML file recently... so it may be missing from a lot of XML templates....
	// Set the default value if the node isnt present within the XML file
	function SideItem(){

		$this->rotatecanvas = 0;
		$this->show_boundary = true;
	}
  
 
 

	// Pass in a SideObject for an Artwork... It will return an array of layer objects sorted by Layer ID
	// The key to the array is just an auto increment
	function orderLayersByLayerLevel(){

		$sortedLayerCounter = 0;
		$LayersSorted = array();

		for($i=0; $i<sizeof($this->layers); $i++){

			// Skip Layers that may have already been removed.
			if($this->layers[$i]->LayerType == "deleted")
				continue;

			// Place all of the layer objects into a 2D array.
			// We are going to sort this array based on the "Layer Level"
			$LayersSorted[$sortedLayerCounter][0] = $this->layers[$i]->level;
			$LayersSorted[$sortedLayerCounter][1] = $this->layers[$i];

			$sortedLayerCounter++;

		}

		// This function will sort 2-D array based on the "Layer Level"
		WebUtil::array_qsort2($LayersSorted, 0);

		// Wipe out the Layers Collection on this side... because we are going to re-insert the layers in a new order
		$this->layers = array();

		foreach($LayersSorted as $thisLayerDetail)
			$this->layers[] = $thisLayerDetail[1];

	}


}

class ColorItem {
	public $id;
	public $colorcode;
}
class ColorPaletteItem {
	public $colorcode;
	public $color_description;
}




?>
