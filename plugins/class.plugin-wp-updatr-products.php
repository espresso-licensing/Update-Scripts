<?php
/**
 * WP Updatr Plugin Update Script
 * Include this script in your plugin and initialize the class in your plugin's root file. 
 *
 * new WPUpdatrPlugins( $customer_api_key, $product_key );
 *
 * Version 1.0.0
 * 
 * Some of this code has been borrowed from Paid Memberships Pro
 * https://github.com/strangerstudios/paid-memberships-pro/
 */

namespace WPUpdatrPlugins;

class WPUpdatrPlugins{

	function __construct( $license_key, $product_key ){

		$this->api_key = $license_key;
		$this->product_key = $product_key;
		$this->license_server = 'https://app.wpupdatr.com/wp-json/wp-updatr/v1/';

		add_action( 'init', array( $this, 'setup_updates' ) );
		add_action( 'admin_init', array( $this, 'updating_plugins' ) );
	}

	function setup_updates(){

		add_filter( 'plugins_api', array( $this, 'plugins_api' ), 10, 3 );
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'update_plugins_filter' ) );
		add_filter( 'http_request_args', array( $this, 'http_request_args' ), 10, 2 );

	}

	/**
	 * Setup plugin updaters
	 */
	function plugins_api( $api, $action = '', $args = null ) {

		// Not even looking for plugin information? Or not given slug?
		if ( 'plugin_information' != $action || empty( $args->slug ) ) {
			return $api;
		}

		//Comment out while testing
		// $this->update_plugin_cache();

		// get addon information
		$addon = $this->verify_license();

		// no response?
		if ( empty( $addon ) ) {
			return $api;
		}

		// Create a new stdClass object and populate it with our plugin information.
		$api = $this->build_plugin_api_object( $addon );

		return $api;
	
	}
	/**
	 * Ping the WP Updatr License Server
	 */
	function verify_license(){

		$wpupdatr_plugin = get_option( 'wpupdatr_plugins_object', array() );

		$wpupdatr_cache = get_option( 'wpupdatr_plugins_timestamp' );

		if( !empty( $_REQUEST['force-check'] ) || current_time( 'timestamp' ) > $wpupdatr_cache + 86400 ){
			
			$args = array(
				'license_key' => $this->api_key,
				'product_key' => $this->product_key,
			);

			$request = wp_remote_post( $this->license_server.'verify-license/', array( 'body' => $args ) );
			
			$response = json_decode( wp_remote_retrieve_body( $request ) );
			
			if( !$response ){
				return;
			}

			$product = array();

			if( !is_wp_error( $response ) && is_object( $response ) ){

				if( intval( $response->status ) === 1 ){

					$product['Name'] = isset( $response->Name ) ? $response->Name : '';
					$product['Slug'] = isset( $response->Slug ) ? $response->Slug : '';
					$product['plugin'] = isset( $response->plugin ) ? $response->plugin : '';
					$product['Version'] = isset( $response->Version ) ? $response->Version : '';
					$product['Author'] = isset( $response->Author ) ? $response->Author : '';
					$product['AuthorURI'] = isset( $response->AuthorURI ) ? $response->AuthorURI : '';
					$product['Requires'] = isset( $response->Requires ) ? $response->Requires : '';
					$product['Tested'] = isset( $response->Tested ) ? $response->Tested : '';
					$product['LastUpdated'] = isset( $response->LastUpdated ) ? $response->LastUpdated : '';
					$product['URI'] = isset( $response->URI ) ? $response->URI : '';
					$product['Download'] = isset( $response->Download ) ? $response->Download : '';
					$product['Description'] = ( !empty( $response->Description ) ) ? $response->Description : "";
					$product['Installation'] = ( !empty( $response->Installation ) ) ? $response->Installation : "";
					$product['FAQ'] = ( !empty( $response->FAQ ) ) ? $response->FAQ : "";
					$product['Changelog'] = ( !empty( $response->Changelog ) ) ? $response->Changelog : "";

					update_option( 'wpupdatr_plugins_object', $product );

					return $product;

				} else {
					$product['Slug'] = isset( $response->Slug ) ? $response->Slug : '';
					$product['status'] = 0;

					return $product;
				}

			}

			return false;

		} else {

			update_option( 'wpupdatr_plugins_timestamp', current_time( 'timestamp' ) );

			return $wpupdatr_plugin;			

		}		

		return false;

	}

	/**
	 * Convert the format from the espresso_licensing_getAddons function to that needed for plugins_api
	 */
	function build_plugin_api_object( $addon ) {
		$api                        = new \stdClass();

		// var_dump($addon);
		if ( empty( $addon ) ) {
			return $api;
		}

		// add info
		$api->name                  = isset( $addon['Name'] ) ? $addon['Name'] : '';
		$api->slug                  = isset( $addon['Slug'] ) ? $addon['Slug'] : '';
		$api->plugin                = isset( $addon['plugin'] ) ? $addon['plugin'] : '';
		$api->version               = isset( $addon['Version'] ) ? $addon['Version'] : '';
		$api->author                = isset( $addon['Author'] ) ? $addon['Author'] : '';
		$api->author_profile        = isset( $addon['AuthorURI'] ) ? $addon['AuthorURI'] : '';
		$api->requires              = isset( $addon['Requires'] ) ? $addon['Requires'] : '';
		$api->tested                = isset( $addon['Tested'] ) ? $addon['Tested'] : '';
		$api->last_updated          = isset( $addon['LastUpdated'] ) ? $addon['LastUpdated'] : '';
		$api->homepage              = isset( $addon['URI'] ) ? $addon['URI'] : '';
		$api->download_link         = isset( $addon['Download'] ) ? $addon['Download'] : '';
		$api->package               = isset( $addon['Download'] ) ? $addon['Download'] : '';

		// add sections
		$api->sections['description'] = ( !empty( $addon['Description'] ) ) ? $addon['Description'] : "";
		$api->sections['installation'] = ( !empty( $addon['Installation'] ) ) ? $addon['Installation'] : "";
		$api->sections['faq'] = ( !empty( $addon['FAQ'] ) ) ? $addon['FAQ'] : "";
		$api->sections['changelog'] = ( !empty( $addon['Changelog'] ) ) ? $addon['Changelog'] : "";

		$api->icons = array( '1x' => ( !empty( $addon['icon'] ) ) ? $addon['icon'] : "" );
		$api->banners = array( 'low' => ( !empty( $addon['banner'] ) ) ? $addon['banner'] : "" );
		
		//carry on here
		$api->download_link = add_query_arg( 'key', $this->api_key, $api->download_link );

		if ( ! empty( $key ) && ! empty( $api->download_link ) ) {
			$api->download_link = add_query_arg( 'key', $this->api_key, $api->download_link );
		}
		if ( ! empty( $key ) && ! empty( $api->package ) ) {
			$api->package = add_query_arg( 'key', $this->api_key, $api->package );
		}
		if ( empty( $api->upgrade_notice ) 
			&& ! $this->verify_license() 
		) {
			$api->upgrade_notice = __( 'Important: This plugin requires a valid license key', 'wp-updatr' );
		}

		return $api;
	}

	/**
	 * Detect when trying to update a WP Updatr plugin without a valid license key.
	 */
	function updating_plugins() {
		// if user can't edit plugins, then WP will catch this later
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		// updating one or more plugins via Dashboard -> Upgrade
		if ( basename( $_SERVER['SCRIPT_NAME'] ) == 'update.php' && ! empty( $_REQUEST['action'] ) && $_REQUEST['action'] == 'update-selected' && ! empty( $_REQUEST['plugins'] ) ) {
			// figure out which plugin we are updating
			$plugins = explode( ',', stripslashes( $_GET['plugins'] ) );
			$plugins = array_map( 'urldecode', $plugins );
			
			// look for addons
			$wpupdatr_plugins = array();

			$product = $this->verify_license();

			foreach ( $plugins as $plugin ) {
				$slug = str_replace( '.php', '', basename( $plugin ) );				
				if ( $product['status'] == 0 && !empty( $product['Slug'] ) && $product['Slug'] == $slug ) {
					$wpupdatr_plugins[] = $plugin;
				}
			}			

			// if WP Updatr Plugins found, check license key
			if ( ! empty( $wpupdatr_plugins ) ) {
				// show error
				$msg = __( 'You must have a valid license key to update this plugin. The following plugin will not be updated:', 'wp-updatr' );

				echo '<div class="error"><p>' . $msg . ' <strong>' . implode( ', ', $wpupdatr_plugins ) . '</strong></p></div>';
			}

			// can exit out of this function now
			return;
		}

		// upgrading just one or plugin via an update.php link
		if ( basename( $_SERVER['SCRIPT_NAME'] ) == 'update.php' && ! empty( $_REQUEST['action'] ) && $_REQUEST['action'] == 'upgrade-plugin' && ! empty( $_REQUEST['plugin'] ) ) {
			// figure out which plugin we are updating
			$plugin = urldecode( trim( $_REQUEST['plugin'] ) );

			$product = $this->verify_license();

			$slug = str_replace( '.php', '', basename( $plugin ) );
			
			if ( $product['status'] == 0 && !empty( $product['Slug'] ) && $product['Slug'] == $slug ) {

				require_once( ABSPATH . 'wp-admin/admin-header.php' );

				echo '<div class="wrap"><h2>' . __( 'Update Plugin' ) . '</h2>';

				$msg = __( 'You must have a valid license key to update this plugin.', 'espresso' );
				
				echo '<div class="error"><p>' . $msg . '</p></div>';				

				echo '</div>';

				include( ABSPATH . 'wp-admin/admin-footer.php' );

				// can exit WP now
				exit;
			}
		}

		// updating via AJAX on the plugins page
		if ( basename( $_SERVER['SCRIPT_NAME'] ) == 'admin-ajax.php' && ! empty( $_REQUEST['action'] ) && $_REQUEST['action'] == 'update-plugin' && ! empty( $_REQUEST['plugin'] ) ) {
			// figure out which plugin we are updating
			$plugin = urldecode( trim( $_REQUEST['plugin'] ) );

			$slug = str_replace( '.php', '', basename( $plugin ) );
			
			$product = $this->verify_license();

			if ( $product['status'] == 0 && !empty( $product['Slug'] ) && $product['Slug'] == $slug ) {

				$msg = __( 'You must have a valid license key to update this plugin.', 'espresso' );
				echo '<div class="error"><p>' . $msg . '</p></div>';

				// can exit WP now
				exit;
			}
		}

	}

	/**
	 * Infuse plugin update details when WordPress runs its update checker.
	 */
	function update_plugins_filter( $value ) {

		// If no update object exists, return early.
		if ( empty( $value ) ) {
			return $value;
		}

		$wpupdatr_plugin = $this->verify_license();

		if ( empty( $wpupdatr_plugin ) ) {
			return $value;
		}

		$plugin_file = $wpupdatr_plugin['Slug'] . '/' . $wpupdatr_plugin['Slug'] . '.php';

		$plugin_file_abs = WP_PLUGIN_DIR . '/' . $plugin_file;

		// couldn't find plugin, skip
		if ( ! file_exists( $plugin_file_abs ) ) {
			return $value;
		} else {
			$plugin_data = get_plugin_data( $plugin_file_abs, false, true );
		}

		// compare versions
		if ( version_compare( $plugin_data['Version'], $wpupdatr_plugin['Version'], '<' ) ) {
			$value->response[ $plugin_file ] = $this->build_plugin_api_object( $wpupdatr_plugin );
			$value->response[ $plugin_file ]->new_version = $wpupdatr_plugin['Version'];
		} else {
			$value->no_update[ $plugin_file ] = $this->build_plugin_api_object( $wpupdatr_plugin );
		}

		// Return the update object.
		return $value;

	}	

	/**
	 * Disables SSL verification to prevent download package failures.
	 */
	function http_request_args( $args, $url ) {
		// If this is an SSL request and we are performing an upgrade routine, disable SSL verification.
		if ( strpos( $url, 'https://' ) !== false && strpos( $url, $this->license_server ) !== false && strpos( $url, 'download' ) !== false ) {
			$args['sslverify'] = false;
		}

		return $args;
	}

	/**
	 * Force update of plugin update data when a WP Updatr License key is updated
	 */
	function update_plugin_cache(){

		delete_option( 'wpupdatr_plugins_timestamp' );
		delete_site_transient( 'update_themes' );

	}	

}