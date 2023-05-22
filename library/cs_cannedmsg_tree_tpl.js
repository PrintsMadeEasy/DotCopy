/*
	Feel free to use your custom icons for the tree. Make sure they are all of the same size.
	If you don't use some keys you can just remove them from this config
*/

function expiresdate() {
	var exp = new Date();
	exp.setTime(exp.getTime() + (1/*years*/ * 365/*days*/ * 24/*hours*/ * 60/*minutes*/ * 60/*seconds*/ *1000/*milliseconds*/)); // ~1 year	
	return exp.toGMTString();
}

var TREE_TPL = {

	// general
	'target':'_self',	// name of the frame links will be opened in
							// other possible values are:
							// _blank, _parent, _search, _self and _top

	//aditional info to save with cookies (selected & opened)
	//'cookie_ext':'expires='+expiresdate()+';',

	// icons - root
	'icon_48':'images/tree/base.gif',   // root icon normal
	'icon_52':'images/tree/base.gif',   // root icon selected
	'icon_56':'images/tree/base.gif',   // root icon opened
	'icon_60':'images/tree/base.gif',   // root icon selected opened

	// icons - node
	'icon_16':'images/tree/folder.gif', // node icon normal
	'icon_20':'images/tree/folderopen_selected.gif', // node icon selected
	'icon_24':'images/tree/folderopen.gif', // node icon opened
	'icon_28':'images/tree/folderopen_selected.gif', // node icon selected opened

	'icon_80':'images/tree/folder.gif', // mouseovered node icon normal

	// icons - leaf
	'icon_0':'images/tree/page.gif', // leaf icon normal
	'icon_4':'images/tree/pageopen.gif', // leaf icon selected

	// icons - junctions
	'icon_2':'images/tree/joinbottom.gif', // junction for leaf
	'icon_3':'images/tree/join.gif',       // junction for last leaf
	'icon_18':'images/tree/plusbottom.gif', // junction for closed node
	'icon_19':'images/tree/plus.gif',       // junctioin for last closed node
	'icon_26':'images/tree/minusbottom.gif',// junction for opened node
	'icon_27':'images/tree/minus.gif',      // junctioin for last opended node

	// icons - misc
	'icon_e':'images/tree/empty.gif', // empty image
	'icon_l':'images/tree/line.gif',  // vertical line

	// styles - root
	'style_48':'mout', // normal root caption style
	'style_52':'mout', // selected root catption style
	'style_56':'mout', // opened root catption style
	'style_60':'mout', // selected opened root catption style
	'style_112':'mover', // mouseovered normal root caption style
	'style_116':'mover', // mouseovered selected root catption style
	'style_120':'mover', // mouseovered opened root catption style
	'style_124':'mover', // mouseovered selected opened root catption style

	// styles - node
	'style_16':'mout', // normal node caption style
	'style_20':'mout', // selected node catption style
	'style_24':'mout', // opened node catption style
	'style_28':'mout', // selected opened node catption style
	'style_80':'mover', // mouseovered normal node caption style
	'style_84':'mover', // mouseovered selected node catption style
	'style_88':'mover', // mouseovered opened node catption style
	'style_92':'mover', // mouseovered selected opened node catption style

	// styles - leaf
	'style_0':'mout', // normal leaf caption style
	'style_4':'mout', // selected leaf catption style
	'style_64':'mover', // mouseovered normal leaf caption style
	'style_68':'mover', // mouseovered selected leaf catption style


	'onItemSelect': 'onItemSelectHandler',    //Run this function everytime that a node is selected

	'b_solid': 'true'  //Make sure that the entire tree gets initialized... since it is not huge.


	// make sure there is no comma after the last key-value pair


};

