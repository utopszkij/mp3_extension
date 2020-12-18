<?php
/*
Plugin Name: mp3_extension
Plugin URI: http://www.github.com/utopszkij/mp3_extension
Description: Kiegészítés woocommerce -hez, mp3 információk kezelése
Version: 1.0.0
Author: RoBIT Bt, Fogler Tibor
Author URI: http://www.github.com/utopszkij

szükséges további plugin: Advanced customer field
a plugin létrehoz/használ egy ACF groupt az audo media formátumú post-okhoz rendelve: "mp3_extension"
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
global $mp3ext;
include_once __DIR__.'/class.mp3_extension.php';

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-plugin-name-activator.php
 */
function activate_mp3_extension() {
}
register_activation_hook( __FILE__, 'activate_mp3_extension' );

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-plugin-name-deactivator.php
 */
function deactivate_mp3_extension() {
}
register_deactivation_hook( __FILE__, 'deactivate_mp3_extension' );


/**
 * init plugin, load js és shortcode definiciók
 */ 
add_action('init','mp3ext_init');
function mp3ext_init() {
    global $mp3ext;
    $mp3ext = new Mp3ExtController();
	
	// set plugin setup
	function mp3ext_register_options_page() {
	  add_options_page('mp3_extension', 'mp3 extended info', 'manage_options', 'mp3_extension', 'mp3ext_admin');
	}
	add_action('admin_menu', 'mp3ext_register_options_page');	
	
	function mp3ext_1($post) {
		global $mp3ext;
		if (($post->post_type == 'attachment') & ($post->post_mime_type == 'audio/mpeg')) {
			$filePath = $mp3ext->getFilePath($post);
			$mp3ext->process($post->ID, $filePath);
		}	
	}
	add_action( 'edit_form_top', 'mp3ext_1',1,1 );
	
	/*
	 * verzió infók lekérése a github -ról	
	 * $res empty at this step
	 * $action 'plugin_information'
	 * $args stdClass Object ( [slug] => woocommerce [is_ssl] => [fields] => Array ( [banners] => 1 [reviews] => 1 [downloaded] => [active_installs] => 1 ) [per_page] => 24 [locale] => en_US )
	 */
	function mp3ext_plugin_info( $res, $action, $args ){
		global $mp3ext;
		// do nothing if this is not about getting plugin information
		if( 'plugin_information' !== $action ) {
			return false;
		}
		$plugin_slug = $mp3ext->pluginName; 
		if( $plugin_slug !== $args->slug ) {
			return false;
		}
		$remote = $mp3ext->getFromGithub();
		
		if( ! is_wp_error( $remote ) && isset( $remote['response']['code'] ) && $remote['response']['code'] == 200 && ! empty( $remote['body'] ) ) {
			$remote = json_decode( $remote['body'] );
			$res = new stdClass();
			$res->name = $remote->info->name;
			$res->slug = $plugin_slug;
			$res->version = $remote->new_version;
			$res->tested = $remote->info->tested;
			$res->requires = $remote->info->requires;
			$res->author = $remote->info->author;
			$res->author_profile = $remote->info->author_profile;
			$res->download_link = $remote->package;
			$res->trunk = $remote->package;
			$res->requires_php = '5.3';
			$res->last_updated = $remote->info->last_updated;
			$res->sections = array(
				'description' => $remote->info->sections->description,
				'installation' => $remote->info->sections->installation,
				'changelog' => $remote->info->sections->changelog
			);
			if( !empty( $remote->info->sections->screenshots ) ) {
				$res->sections['screenshots'] = $remote->info->sections->screenshots;
			}
			return $res;
		}
		return false;
	}
	add_filter('plugins_api', 'mp3ext_plugin_info', 20, 3);

	/**
	* plugin auto updater hook
	*/	
	function mp3ext_push_update( $transient ){
		global $mp3ext;
		if ( empty($transient->checked ) ) {
	            return $transient;
        }
		$remote = $mp3ext->getFromGithub();
		if( $remote ) {
			$remote = json_decode( $remote['body'] );
			$pluginData = get_plugin_data( __FILE__ );
			$actVersion = $pluginData['Version'];			
			if( $remote && version_compare( $actVersion, $remote->new_version, '<' ) && 
				version_compare($remote->info->requires, get_bloginfo('version'), '<' ) ) {
				$res = new stdClass();
				$res->slug = $mp3ext->pluginName;
				$res->plugin = $mp3ext->pluginName.'/'.$mp3ext->pluginName.'.php'; // it could be just YOUR_PLUGIN_SLUG.php if your plugin doesn't have its own directory
				$res->new_version = $remote->new_version;
				$res->tested = $remote->info->tested;
				$res->package = $remote->package;
	           	$transient->response[$res->plugin] = $res;
	        }
		}
        return $transient;
	}
	add_filter('site_transient_update_plugins', 'mp3ext_push_update' );
	
}

add_action('admin_init','mp3ext_admin_init');
function mp3ext_admin_init(){
	if ((!is_plugin_active('advanced-custom-fields/acf.php')) &
	    (is_plugin_active('mp3_extension/mp3_extension.php'))) {
		echo '<h2 style="background-color:red; color:white; font-weight:bold; padding:10px">
			mp3 extension error! "Advanced Custom fields" Plugin not activated, please activate it!</h2>';	
	}

}

/**
 * plugin admin oldal Beállítások menüpont
 */
function mp3ext_admin() {
    global $mp3ext;
 	$mp3ext->admin();
}

?>