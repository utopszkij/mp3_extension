<?php
/**
* Plugin Name: mp3_extension
* Plugin URI: http://www.github.com/utopszkij/mp3_extension
* Description: Kiegészítés woocommerce -hez, mp3 információk kezelése
* Version: 1.0 ß-test
* Author: RoBIT Bt, Fogler Tibor
* Author URI: http://www.github.com/utopszkij
*
* szükséges további plugin: Advanced customer field
* a plugin létrehoz/használ egy ACF groupt az audo media formátumú post-okhoz rendelve: "mp3_extension"
*/

global $mp3ext;
include_once __DIR__.'/class.mp3_extension.php';
/**
 * init plugin, load js és shortcode definiciók
 */ 
add_action('init','mp3ext_init');
function mp3ext_init(){
	
    global $mp3ext;
    $mp3ext = new Mp3ExtController();

	add_action('admin_menu', 'mp3ext_plugin_create_menu');
	function mp3ext_plugin_create_menu() {
	    add_options_page("mp3 extension WordPress bővítmény", "mp3ext wordPress bővítmény", 1,
	        "mp3ext_admin", "mp3ext_admin");
	} 
	
	add_action( 'edit_form_top', 'mp3ext_1',1,1 );
	function mp3ext_1($post) {
		global $mp3ext;
		if (($post->post_type == 'attachment') & ($post->post_mime_type == 'audio/mpeg')) {
			$filePath = $mp3ext->getFilePath($post);
			$mp3ext->process($post->ID, $filePath);
		}	
	}
	
	add_filter('upload_mimes', 'mp3ext_myme_types', 1, 1);
	function mp3ext_myme_types($mime_types){
		if (!isset($mime_types['mp3']))	$mime_types['mp3'] = 'image/mp3'; 
    	return $mime_types;
	}
	
}


add_action('admin_init','mp3ext_admin_init');
function mp3ext_admin_init(){
	if ((!is_plugin_active('advanced-custom-fields/acf.php')) &
	    (is_plugin_active('mp3_extension/mp3_extension.php'))) {
		echo '<h2 style="background-color:red; color:white; font-weight:bold; padding:10px">
			mp3 extension error! "Advanced Custom fields" Plugin not activated, please activate it!</h2>';	
	}
	function mp3_extension_upgrademe()	{
		return 'http://github.com/downloads/utopszkij/mp3_extension/newver_info.json';
	}	
}

/**
 * plugin admin oldal Beállítások menüpont
 */
function mp3ext_admin() {
    global $mp3ext;
 	//  $mp3ext->admin();
 	echo 'mp3 extension';
}

?>