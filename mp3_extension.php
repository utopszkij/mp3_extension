<?php
/**
*
* Plugin Name: mp3_extension
* Plugin URI: http://www.github.com/utopszkij/mp3_extension
* Description: Kiegészítés woocommerce -hez, mp3 információk kezelése
* Version: 0.0.0
* Author: RoBIT Bt, Fogler Tibor
* Author URI: http://www.github.com/utopszkij
*
* szükséges további plugin: Advanced customer field
* a plugin létrehoz/használ egy ACF groupt az audo media formátumú post-okhoz rendelve: "mp3_extension"
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

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-plugin-name-deactivator.php
 */
function deactivate_mp3_extension() {
}
register_activation_hook( __FILE__, 'activate_mp3_extension' );
register_deactivation_hook( __FILE__, 'deactivate_mp3_extension' );


/**
 * init plugin, load js és shortcode definiciók
 */ 
add_action('init','mp3ext_init');
function mp3ext_init() {
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
	
	/*
	 * $res empty at this step
	 * $action 'plugin_information'
	 * $args stdClass Object ( [slug] => woocommerce [is_ssl] => [fields] => Array ( [banners] => 1 [reviews] => 1 [downloaded] => [active_installs] => 1 ) [per_page] => 24 [locale] => en_US )
	 */
	function mp3ext_plugin_info( $res, $action, $args ){
		// do nothing if this is not about getting plugin information
		if( 'plugin_information' !== $action ) {
			return false;
		}
		$plugin_slug = 'mp3_extension'; // we are going to use it in many places in this function
		if( $plugin_slug !== $args->slug ) {
			return false;
		}
		if( false == $remote = get_transient( 'mp3ext_' . $plugin_slug ) ) {
			$remote = wp_remote_get( 'https://raw.githubusercontent.com/utopszkij/mp3_extension/master/newver-info.json', array(
				'timeout' => 10,
				'headers' => array(
					'Accept' => 'application/json'
				) )
			);
			if ( ! is_wp_error( $remote ) && isset( $remote['response']['code'] ) && $remote['response']['code'] == 200 && ! empty( $remote['body'] ) ) {
				set_transient( 'misha_update_' . $plugin_slug, $remote, 43200 ); // 12 hours cache
			}
		}
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
		if ( empty($transient->checked ) ) {
	            return $transient;
        }
		if( false == $remote = get_transient( 'mp3ext_mp3_extension' ) ) {
			$remote = wp_remote_get( 'https://raw.githubusercontent.com/utopszkij/mp3_extension/master/newver-info.json', array(
				'timeout' => 10,
				'headers' => array(
					'Accept' => 'application/json'
				) )
			);
			if ( !is_wp_error( $remote ) && isset( $remote['response']['code'] ) && $remote['response']['code'] == 200 && !empty( $remote['body'] ) ) {
				set_transient( 'mp3ext_mp3_extension', $remote, 43200 ); // 12 hours cache
			}
		}
		if( $remote ) {
			$remote = json_decode( $remote['body'] );
			if( $remote && version_compare( '0.0', $remote->new_version, '<' ) && 
				version_compare($remote->info->requires, get_bloginfo('version'), '<' ) ) {
				$res = new stdClass();
				$res->slug = 'mp3_extension';
				$res->plugin = 'mp3_extension/mp3_extension.php'; // it could be just YOUR_PLUGIN_SLUG.php if your plugin doesn't have its own directory
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

	/**
	* verzió szám kinyerése a fő php forrásfájlból
	* @param array $lines
	* @return string
	*/
	function getVersionFromFile(array $lines) {
		$result = '';
		foreach ($lines as $line) {
			if (strpos($line,'* Version:') === 0) {
				$result = trim(str_replace('* Version:','',$line));
				if (substr($result,1,1) == '.') {
					$result = ' '.$result;			
				}
			}		
		}
		return $result;
	}
	
	// a legfrissebb verzió lekérése a github -ról
	$w = file_get_contents('https://raw.githubusercontent.com/utopszkij/mp3_extension/master/mp3_extension.php');
	$lines = explode("\n",$w);
	$new_version = getVersionFromFile($lines);	

	// telepített verzió elérése
	$lines = file(__FILE__);
	$act_version = getVersionFromFile($lines);	
		
	// ha van új verzió akkor figyelmeztető üzenet
	if ($new_version > $act_version) {
		echo '<div style="background-color:orange; color:black; padding:10px;">
		mp3_extension plugin új verzió érhető el!<br>
		Elérhető verzió:'.$new_version.'<br />
		Telepített verzió:'.$act_version.'<br />
		<a href="https://github.com/utopszkij/mp3_extension">Frissitési utmutató</a>
		<br />
		mp3_extension plugin new version is exists!<br>
		New version:'.$new_version.'<br />
		Actual version:'.$act_version.'<br />
		<a href="https://github.com/utopszkij/mp3_extension">upgrade info</a>
		</div>
		';			
	}	

}

/**
 * plugin admin oldal Beállítások menüpont
 */
function mp3ext_admin() {
    global $mp3ext;
 	//  $mp3ext->admin();
 	echo '<h2>mp3 extension</h2>
 	<p>kibővített mp3 info kezelés a wp admin oldalon</p>
 	<p> </p>
 	<p><a href="https://github.com/utopszkij/mp3_extension">plugin web oldal</a></p>
 	<p> </p>
 	<p>extended mp3 info managment in wp admin site</p>
 	<p> </p>
 	<p><a href="https://github.com/utopszkij/mp3_extension">plugin web site</a></p>
 	<p> </p>
 	';
}

?>