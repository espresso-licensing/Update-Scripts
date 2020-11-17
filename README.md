
# WP Updatr - Update Scripts

WP Updatr allows you to offer automatic updates to your customers when using premium WordPress plugins and themes (or for any that aren't hosted on WordPress.org really).

## Using WP Updatr

This update script requires you to have registered an account on [WP Updatr](https://wpupdatr.com/#pricing). This services allows you to offer updates without having to serve them off of your own server. It's also been made possible to release updates in under a minute - It's SUPER Easy!

## Usage - Plugins

Include the `class.plugin-wp-updatr.php` file at the top of your plugin's core file.

```php
if( !class_exists('WPUpdatrPlugins') ){
  require_once plugin_dir_path( __FILE__ ).'class.plugin-wp-updatr.php';
}
```
Instantiate the WPUpdatrLicenseControl Class with your customer's `$license_key` and `$product_key`. Make use of namespacing to ensure that no conflicts arise between your plugin and others. Change `myPluginAlias` to something unique to your plugin. 
```php
use WPUpdatrPlugins as myPluginAlias;
new myPluginAlias\WPUpdatrPlugins( $license_key, $product_key );
```
## License Key

`$license_key` - This key is created when a customer purchases or proceeds through the checkout process of your chosen integration (Woocommerce, Paid Memberships Pro, Easy Digital Downloads).

You will need to build in a 'Settings' or 'API Key' field in your plugin to store this value, where your customer can enter in their API key. 

The easiest steps would be to save it in an `update_option` so that you initialize the code as follows: 

```php
use WPUpdatrPlugins as myPluginAlias;
new myPluginAlias\WPUpdatrPlugins( get_option( 'myplugin_api_key' ), $product_key );
```
## Product Key

`$product_key` - This key is created when you create a product in the WP Updatr [Dashboard](https://app.wpupdatr.com/dashboard/) on the [Products Page](https://app.wpupdatr.com/products/).

This value will be hard coded and should not be changed once your plugin is released to your customers. This key is prefixed with `ELP-` and will look similar to this: 

```php
use WPUpdatrPlugins as myPluginAlias;
new myPluginAlias\WPUpdatrPlugins( get_option( 'myplugin_api_key' ), 'ELP-' );
```

## Verify Your Setup

Run a test purchase through your website to obtain a customer API key for yourself. Include that in your plugin's API key field and set your product API key. 

To verify if your API key is valid and paired to the correct product, run the following: 

```php
use WPUpdatrPlugins as myPluginAlias;
$license = new myPluginAlias\WPUpdatrPlugins( get_option( 'myplugin_api_key`, 'ELP-' );

$object = $license->verify_license();

var_dump($object);
```
`$object` returns an object on success. `null` on error.

## WP Updatr Plans

WP Updatr offers a variety of plans to suit the needs of all businesses.

<table>
	<tr>
		<th>Starter</th>
		<th>Plus</th>
		<th>Professional</th>
	</tr>
  	<tr>
  		<td>Up to 200 Licenses</td>
  		<td>Up to 1 000 Licenses</td>
  		<td>1 000+ Licenses</td>
  	</tr>
  	<tr>
  		<td>Unlimited Products</td>
  		<td>Unlimited Products</td>
  		<td>Unlimited Products</td>
  	</tr>
  	<tr>
  		<td>Unlimited Updates</td>
  		<td>Unlimited Updates</td>
  		<td>Unlimited Updates</td>
  	</tr>
  	<tr>
  		<td>Comprehensive Reporting</td>
  		<td>Comprehensive Reporting</td>
  		<td>Comprehensive Reporting</td>
  	</tr>  
</table>

[Pricing & More Information](https://wpupdatr.com/#pricing)

## Support & Contributing

For queries related to the WP Updatr service, please [get in touch with us](https://wpupdatr.com/support/) directly. 

For any issues or questions related to the update scripts, feel free to [open an issue](https://github.com/wp-updatr/update-scripts/issues) and we'll address it ASAP.