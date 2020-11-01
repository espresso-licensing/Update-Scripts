<?php
/**
 * WP Updatr Plugin Update Script
 * Include this script in your plugin and initialize the class in your plugin's root file. 
 *
 * new EspressoLicenseControl( $customer_api_key, $product_key );
 *
 * Version 1.0.0
 * 
 * Some of this code has been borrowed from Paid Memberships Pro
 * https://github.com/strangerstudios/paid-memberships-pro/
 */
class EspressoLicenseControl{

	function __construct( $api_key, $product_key ){

		$this->api_key = $api_key;
		$this->product_key = $product_key;
		$this->license_server = 'https://app.espressolicensing.com/wp-json/espresso-licensing/v1/';

		add_action( 'init', array( $this, 'setup_updates' ) );
		add_action( 'admin_init', array( $this, 'updating_plugins' ) );
	}

	function setup_updates(){

		add_filter( 'plugins_api', array( $this, 'plugins_api' ), 10, 3 );
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'espresso_licensing_update_plugins_filter' ) );
		add_filter( 'http_request_args', array( $this, 'http_request_args' ), 10, 2 );
		add_action( 'update_option_espresso_licensing_license_key', array( $this, 'update_plugin_cache' ), 10, 2 );

	}

	/**
	 * Setup plugin updaters
	 *
	 * @since  1.8.5
	 */
	function plugins_api( $api, $action = '', $args = null ) {

		// Not even looking for plugin information? Or not given slug?
		if ( 'plugin_information' != $action || empty( $args->slug ) ) {
			return $api;
		}

		// get addon information
		$addon = $this->verify_product( $args->slug );

		// no addons?
		if ( empty( $addon ) ) {
			return $api;
		}

		// handled by wordpress.org?
		// if ( empty( $addon['License'] ) || $addon['License'] == 'wordpress.org' ) {
		// 	return $api;
		// }

		// Create a new stdClass object and populate it with our plugin information.
		$api = $this->plugin_api_object( $addon );
		return $api;
	}

	function verify_product( $slug = '' ){

		$args = array(
			'api_key' => $this->api_key,
			'product_key' => $this->product_key,
			'slug' => $slug
		);

		$request = wp_remote_post( $this->license_server.'verify-product-key/', array( 'body' => $args ) );
		
// var_dump($request);
		$response = json_decode( wp_remote_retrieve_body( $request ) );

		$addon = array();
		// var_dump($response);
		if( !is_wp_error( $response ) ){

			if( intval( $response->status ) === 1 ){

				$addon['Name'] = isset( $response->Name ) ? $response->Name : '';
				$addon['Slug'] = isset( $response->Slug ) ? $response->Slug : '';
				$addon['plugin'] = isset( $response->plugin ) ? $response->plugin : '';
				$addon['Version'] = isset( $response->Version ) ? $response->Version : '';
				$addon['Author'] = isset( $response->Author ) ? $response->Author : '';
				$addon['AuthorURI'] = isset( $response->AuthorURI ) ? $response->AuthorURI : '';
				$addon['Requires'] = isset( $response->Requires ) ? $response->Requires : '';
				$addon['Tested'] = isset( $response->Tested ) ? $response->Tested : '';
				$addon['LastUpdated'] = isset( $response->LastUpdated ) ? $response->LastUpdated : '';
				$addon['URI'] = isset( $response->URI ) ? $response->URI : '';
				$addon['Download'] = isset( $response->Download ) ? $response->Download : '';
				$addon['Download'] = isset( $response->Download ) ? $response->Download : '';

				return $addon;
			}
		}

		return false;

	}

	/**
	 * Convert the format from the espresso_licensing_getAddons function to that needed for plugins_api
	 *
	 * @since  1.8.5
	 */
	function plugin_api_object( $addon ) {
		$api                        = new stdClass();

		var_dump($addon);
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

		//carry on here
		// get license key if one is available
		$key = get_option( 'espresso_licensing_license_key', '' );
		//temp
		$api->download_link = add_query_arg( 'key', $key, $api->download_link );

		if ( ! empty( $key ) && ! empty( $api->download_link ) ) {
			$api->download_link = add_query_arg( 'key', $key, $api->download_link );
		}
		if ( ! empty( $key ) && ! empty( $api->package ) ) {
			$api->package = add_query_arg( 'key', $key, $api->package );
		}
		if ( empty( $api->upgrade_notice ) 
			&& ! $this->verify_product() 
		) {
			$api->upgrade_notice = __( 'Important: This plugin requires a valid espresso Plus license key to update.', 'espresso' );
		}

		return $api;
	}

	/**
	 * Detect when trying to update a espresso Plus plugin without a valid license key.
	 *
	 * @since 1.9
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
			var_dump($plugins);
			// look for addons
			$plus_addons = array();
			$plus_plugins = array();
			foreach ( $plugins as $plugin ) {
				$slug = str_replace( '.php', '', basename( $plugin ) );
				$addon = $this->get_product_by_slug( $slug );
				if ( ! empty( $addon ) && $addon['License'] == 'plus' ) {
					$plus_addons[] = $addon['Name'];
					$plus_plugins[] = $plugin;
				}
			}
			unset( $plugin );

			// if Plus addons found, check license key
			if ( ! empty( $plus_plugins ) 

				// && ! is_valid_license( null, 'plus' ) 
			) {
				// show error
				$msg = __( 'You must have a <a href="https://www.espressolicensing.com/pricing/?utm_source=wp-admin&utm_pluginlink=bulkupdate">valid espresso Plus License Key</a> to update espresso Plus add ons. The following plugins will not be updated:', 'espresso' );
				echo '<div class="error"><p>' . $msg . ' <strong>' . implode( ', ', $plus_addons ) . '</strong></p></div>';
			}

			// can exit out of this function now
			return;
		}

		// upgrading just one or plugin via an update.php link
		if ( basename( $_SERVER['SCRIPT_NAME'] ) == 'update.php' && ! empty( $_REQUEST['action'] ) && $_REQUEST['action'] == 'upgrade-plugin' && ! empty( $_REQUEST['plugin'] ) ) {
			// figure out which plugin we are updating
			$plugin = urldecode( trim( $_REQUEST['plugin'] ) );

			$slug = str_replace( '.php', '', basename( $plugin ) );
			$addon = $this->get_product_by_slug( $slug );
			if ( ! empty( $addon ) && ! $this->verify_product( ) ) {
				require_once( ABSPATH . 'wp-admin/admin-header.php' );

				echo '<div class="wrap"><h2>' . __( 'Update Plugin' ) . '</h2>';

				$msg = __( 'You must have a <a href="https://www.espressolicensing.com/pricing/?utm_source=wp-admin&utm_pluginlink=addon_update">valid espresso Plus License Key</a> to update espresso Plus add ons.', 'espresso' );
				echo '<div class="error"><p>' . $msg . '</p></div>';

				echo '<p><a href="' . admin_url( 'admin.php?page=espresso-addons' ) . '" target="_parent">' . __( 'Return to the espresso Add Ons page', 'espresso' ) . '</a></p>';

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
			$addon = $this->get_product_by_slug( $slug );
			if ( ! empty( $addon ) && ! is_valid_license( null, 'plus' ) ) {
				$msg = __( 'You must enter a valid espresso Plus License Key under Settings > espresso License to update this add on.', 'espresso' );
				echo '<div class="error"><p>' . $msg . '</p></div>';

				// can exit WP now
				exit;
			}
		}

		/*
	        TODO:
			* Check for espresso Plug plugins
			* If a plus plugin is found, check the espresso license key
			* If the key is missing or invalid, throw an error
			* Show appropriate footer and exit... maybe do something else to keep plugin update from happening
		*/
	}

	/**
	 * Infuse plugin update details when WordPress runs its update checker.
	 *
	 * @since 1.8.5
	 *
	 * @param object $value  The WordPress update object.
	 * @return object $value Amended WordPress update object on success, default if object is empty.
	 */
	function espresso_licensing_update_plugins_filter( $value ) {

		// If no update object exists, return early.
		if ( empty( $value ) ) {
			return $value;
		}

		// get addon information
		$addon = $this->verify_product();
	var_dump($addon);
		// no addons?
		if ( empty( $addon ) ) {
			return $value;
		}

		// check addons
		// foreach ( $addons as $addon ) {
			// skip wordpress.org plugins
			if ( empty( $addon['License'] ) || $addon['License'] == 'wordpress.org' ) {
				// continue;
			}

			// get data for plugin
			$plugin_file = $addon['Slug'] . '/' . $addon['Slug'] . '.php';
			$plugin_file_abs = WP_PLUGIN_DIR . '/' . $plugin_file;

			// couldn't find plugin, skip
			if ( ! file_exists( $plugin_file_abs ) ) {
				// continue;
			} else {
				$plugin_data = get_plugin_data( $plugin_file_abs, false, true );
			}
var_dump($plugin_data);
// exit();
var_dump( $plugin_data['Version']);
			// compare versions
			if (
			 // ! empty( $addon['License'] ) && 
				version_compare( $plugin_data['Version'], $addon['Version'], '<' ) ) {
				var_dump('aaa');
				$value->response[ $plugin_file ] = $this->plugin_api_object( $addon );
				$value->response[ $plugin_file ]->new_version = $addon['Version'];
			} else {
				var_dump('bbb');
				$value->no_update[ $plugin_file ] = $this->plugin_api_object( $addon );
			}
		// }

		// Return the update object.
		return $value;
	}

	/**
	 * Find a espresso addon by slug.
	 *
	 * @since 1.8.5
	 *
	 * @param object $slug  The identifying slug for the addon (typically the directory name)
	 * @return object $addon containing plugin information or false if not found
	 */
	function get_product_by_slug( $slug ) {
		$addons = $this->get_addons();

		if ( empty( $addons ) ) {
			return false;
		}

		foreach ( $addons as $addon ) {
			if ( $addon['Slug'] == $slug ) {
				return $addon;
			}
		}

		return false;
	}

	/**
	 * Get addon information from espresso server.
	 *
	 * @since  1.8.5
	 */
	function get_addons() {
		// check if forcing a pull from the server
		$addons = get_option( 'espresso_licensing_addons', array() );
		$addons_timestamp = get_option( 'espresso_licensing_addons_timestamp', 0 );
		var_dump('getting');
		// if no addons locally, we need to hit the server
		if ( 
			// empty( $addons ) || 
			! empty( $_REQUEST['force-check'] ) || current_time( 'timestamp' ) > $addons_timestamp + 86400 ) {
			var_dump('here yo');
			/**
			 * Filter to change the timeout for this wp_remote_get() request.
			 *
			 * @since 1.8.5.1
			 *
			 * @param int $timeout The number of seconds before the request times out
			 */
			$timeout = apply_filters( 'espresso_licensing_get_addons_timeout', 5 );

			// get em
			$remote_addons = $this->verify_product();
	// var_dump($remote_addons);
			// make sure we have at least an array to pass back
			if ( empty( $addons ) ) {
				$addons = array();
			}

			// test response
			// if ( is_wp_error( $remote_addons ) ) {
			// 	// error
			// 	// espresso_licensing_setMessage( 'Could not connect to the espresso License Server to update addon information. Try again later.', 'error' );
			// } elseif ( ! empty( $remote_addons ) 
			// 	// && $remote_addons['response']['code'] == 200 
			// ) {
				// update addons in cache
				// $addons = json_decode( wp_remote_retrieve_body( $remote_addons ), true );
				// var_dump('xxx');
				// var_dump($addons);
				delete_option( 'espresso_licensing_addons' );
				add_option( 'espresso_licensing_addons', $addons, null, 'no' );
			// }

			// save timestamp of last update
			delete_option( 'espresso_licensing_addons_timestamp' );
			add_option( 'espresso_licensing_addons_timestamp', current_time( 'timestamp' ), null, 'no' );
		}

		return $addons;
	}

	/**
	 * Disables SSL verification to prevent download package failures.
	 *
	 * @since 1.8.5
	 *
	 * @param array  $args  Array of request args.
	 * @param string $url  The URL to be pinged.
	 * @return array $args Amended array of request args.
	 */
	function http_request_args( $args, $url ) {
		// If this is an SSL request and we are performing an upgrade routine, disable SSL verification.
		if ( strpos( $url, 'https://' ) !== false && strpos( $url, $this->license_server ) !== false && strpos( $url, 'download' ) !== false ) {
			$args['sslverify'] = false;
		}

		return $args;
	}

	/**
	 * Force update of plugin update data when the espresso License key is updated
	 *
	 * @since 1.8
	 *
	 * @param array  $args  Array of request args.
	 * @param string $url  The URL to be pinged.
	 * @return array $args Amended array of request args.
	 */
	function update_plugin_cache( $old_value, $value ) {
		delete_option( 'espresso_licensing_addons_timestamp' );
		delete_site_transient( 'update_themes' );
	}

	function is_valid_license( $thing = null, $license = 'plus' ){

		$args = array(
			'api_key' => $this->api_key
		);

		$request = wp_remote_post( $this->license_server.'validate_api/', array( 'body' => $args ) );

		$response = json_decode( wp_remote_retrieve_body( $request ) );

		if( !is_wp_error( $response ) ){
			if( intval( $response->status ) === 1 ){
				var_dump($response);
			}
		}

		return false;

	}

}