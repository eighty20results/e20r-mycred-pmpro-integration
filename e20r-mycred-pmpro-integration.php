<?php
/*
Plugin Name: Eighty/20 Results - Integrate myCred and Paid Memberships Pro
Plugin URI: https://eighty20results.com/wordpress-plugins/e20r-mycred-pmpro-integration/
Description: Assign myCred points to certain PMPro member actions/activities
Version: 1.2.2
Author: Eighty / 20 Results by Wicked Strong Chicks, LLC <thomas@eighty20results.com>
Author URI: https://eighty20results.com/thomas-sjolshagen/
Text Domain: e20r-mycred-pmpro-integration
Domain Path: /languages
License:

	Copyright 2016 - Eighty / 20 Results by Wicked Strong Chicks, LLC (thomas@eighty20results.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

*/

defined( 'ABSPATH' ) || die( __( 'Cannot access plugin sources directly', 'e20r-mycred-pmpro-integration' ) );
define( 'E20R_NPF_VER', '1.2.2' );

class e20rMyCredPmproIntegration {

	/**
	 * @var e20rMyCredPmproIntegration $instance The class instance
	 */
	static $instance = null;

	/**
	 * @var string $option_name The name to use in the WordPress options table
	 */
	private $option_name;

	/**
	 * @var array $options Array of levels with setup fee values.
	 */
	private $options;

	/**
	 * @var e20rUtils   Instance of the utilities class
	 */
	private $util;

	/**
	 * @var string $license_name Name of the license we need/are managing
	 */
	private $license_name = 'e20r-mycred-pmpro-integration';

	/**
	 * @var string $license_descr - The description of the license
	 */
	private $license_descr = null;

	/**
	 * e20rMyCredPmproIntegration constructor.
	 */
	public function __construct() {

		$this->option_name   = strtolower( get_class( $this ) );
		$this->license_descr = __( "myCred Integration for PMPro", "e20r-mycred-pmpro-integration" );

		/**
		 * add_action( 'init', array( $this, 'registerLicense' ) );
		 *
		 * add_filter( 'upgrader_pre_download', array( $this, 'checkLicense' ), 10, 3 );
		 * add_action( 'http_api_curl', array( $this, 'force_tls_12' ) );
		 */

		add_action( 'plugins_loaded', array( $this, 'loadClasses' ) );
		add_action( 'pmpro_membership_level_after_other_settings', array( $this, 'addToLevelDef' ) );
		add_action( 'pmpro_save_membership_level', array( $this, 'saveLevelSettings' ) );
		add_action( 'pmpro_delete_membership_level', array( $this, 'deleteLevelSettings' ) );

		add_action( 'pmpro_subscription_payment_completed', array( $this, 'subscriptionPaymentComplete' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		if ( WP_DEBUG ) {
			add_action( 'wp', array( $this, 'runTestUpdateBilling' ) );
		}

		if ( empty( $this->settings ) ) {
			$this->settings = get_option( 'e20r_mycpmp', $this->defaultSettings() );
		}

		add_action( 'pmpro_checkout_before_change_membership_level', array( $this, 'checkoutConfirmed'), 10, 2 );
	}

	public function checkLicense( $reply, $package, $upgrader ) {

		return e20rLicense::isLicenseActive( $this->license_name );
	}

	public function registerLicense() {

		e20rLicense::registerLicense( $this->license_name, $this->license_descr );
	}

	public function loadClasses() {

		$this->util    = e20rUtils::get_instance();
		$this->license = e20rLicense::get_instance();

		if ( is_admin() ) {
			add_action( 'admin_notices', array( $this->util, 'display_notice' ) );
		}
	}

	/**
	 * Retrieve and initiate the class instance
	 *
	 * @return e20rMyCredPmproIntegration
	 */
	public static function get_instance() {

		if ( is_null( self::$instance ) ) {
			self::$instance = new self;
		}

		$class = self::$instance;

		return $class;
	}

	/**
	 * Load CSS and JS for the admin page(s).
	 */
	public function enqueue_admin_scripts() {

		wp_enqueue_style( 'e20r-mycred-pmpro-integration', plugins_url( 'css/e20r-mycred-pmpro-integration.css', __FILE__ ), null, E20R_NPF_VER );
	}

	/**
	 * Load the required translation file for the add-on
	 */
	public function loadTranslation() {

		$locale = apply_filters( "plugin_locale", get_locale(), "e20r-mycred-pmpro-integration" );
		$mo     = "e20r-mycred-pmpro-integration-{$locale}.mo";

		// Paths to local (plugin) and global (WP) language files
		$local_mo  = plugin_dir_path( __FILE__ ) . "/languages/{$mo}";
		$global_mo = WP_LANG_DIR . "/e20r-mycred-pmpro-integration/{$mo}";

		// Load global version first
		load_textdomain( "e20r-mycred-pmpro-integration", $global_mo );

		// Load local version second
		load_textdomain( "e20r-mycred-pmpro-integration", $local_mo );
	}

	/**
	 * @return array
	 */
	private function defaultSettings() {

		return array(
			'default' => array(
				'reference_text'   => apply_filters( 'e20r-mycred-pmpro-default-reference', __( "Renewal payment received", 'e20r-mycred-pmpro-integration' ) ),
				'renewal_points'   => apply_filters( 'e20r-mycred-pmpro-default-score', 0 ),
				'renewal_notice'   => apply_filters( 'e20r-mycred-pmpro-default-notice', null ),
				'point_type'       => apply_filters( 'e20r-mycred-pmpro-default-type', 'mycred_default_total' ),
				'max_level_points' => apply_filters( 'e20r-mycred-pmpro-default-max-level-points', 0 )
			),
		);
	}

	/**
	 * @param $key
	 * @param string $level_id
	 *
	 * @return null
	 */
	public function getSetting( $key, $level_id = 'default' ) {

		if ( empty( $this->settings ) ) {
			$this->settings = get_option( 'e20r_mycpmp', $this->defaultSettings() );
		}

		$defaults = $this->defaultSettings();
        $value = isset( $this->settings[ $level_id ][ $key ] ) ? $this->settings[ $level_id ][ $key ] : $defaults['default'][ $key ];

        if (WP_DEBUG) {
            error_log("{$key} setting returned for {$level_id}: {$value}");
        }
		return $value;
	}

	public function saveLevelSettings( $level_id ) {

		foreach ( $_REQUEST as $key => $value ) {

			if ( false !== strpos( $key, 'e20r-mycpmp_' ) ) {

				$rk_arr = explode( '_', $key );

				$real_key = str_replace( '-', '_', $rk_arr[ ( count( $rk_arr ) - 1 ) ] );
				$this->saveSetting( $real_key, sanitize_text_field( stripslashes_deep( $_REQUEST[ $key ] ) ), $level_id );
			}
		}
	}

	/**
	 * @param null $key
	 * @param null $value
	 * @param string $level_id
	 */
	public function saveSetting( $key = null, $value = null, $level_id = 'default' ) {

		// Configure default setting(s).
		if ( empty( $this->settings ) ) {

			$this->settings = $this->defaultSettings();
		}

		// Append the settings array for the level ID if it doesn't exits
		if ( ! isset( $this->settings[ $level_id ] ) || ( isset( $this->settings[ $level_id ] ) && ! is_array( $this->settings[ $level_id ] ) ) ) {
			$this->settings[ $level_id ] = array();
		}

		// Assign the key/value pair
		$this->settings[ $level_id ][ $key ] = $value;

		update_option( 'e20r_mycpmp', $this->settings, false );

		$test = get_option( 'e20r_mycpmp' );

		if ( $test[ $level_id ][ $key ] != $this->settings[ $level_id ][ $key ] ) {
			$this->util->set_notice( sprintf( __( "Unable to save myCred settings settings for %s", "e20r-mycred-pmpro-integration" ), $key ), "error" );
		}
	}

	public function addToLevelDef() {

		$level_id         = $this->util->_get_variable( 'edit', 'default' );
		$reference        = $this->getSetting( 'reference_text', $level_id );
		$renewal_notice   = $this->getSetting( 'renewal_notice', $level_id );
		$type_key         = $this->getSetting( 'point_type', $level_id );
		$max_level_points = $this->getSetting( 'max_level_points', $level_id );
		$text_size        = apply_filters( 'e20r-mycred-pmpro-textfield-size', 50 );
		$max_balance      = apply_filters( 'e20r-mycred-pmpro-max-balance', 999999 );

		$plural   = "Points";
		$singular = "Point";

		if ( function_exists( 'mycred' ) ) {
			$mycred   = mycred();
			$plural   = $mycred->plural();
			$singular = $mycred->singular();
		}

		?>
        <hr/>
        <h3 class="e20r-mycred-header"><?php _e( "Configure myCred", "e20r-mycred-pmpro-integration" ); ?></h3>
        <table class="form-table e20r-mycred-settings">
            <tbody class="e20r-settings-body">
            <tr class="e20r-settings-row">
                <th class="e20r-settings-cell">
                    <label for="e20r-mycpmp_reference-text"><?php _e( "Description", "e20r-mycred-pmpro-integration" ); ?></label>
                </th>
                <td class="e20r-settings-cell">
                    <input type="text"
                           value="<?php esc_attr_e( $reference ); ?>"
                           name="e20r-mycpmp_reference-text" id="e20r-mycpmp_reference-text">
                </td>
            </tr>
            <tr class="e20r-settings-row">
                <th class="e20r-settings-cell">
                    <label for="e20r-mycpmp_renewal-points"><?php printf( __( "Renewal %s", "e20r-mycred-pmpro-integration" ), strtolower( $plural ) ); ?></label>
                </th>
                <td class="e20r-settings-cell">
                    <input type="number"
                           value="<?php esc_attr_e( $this->getSetting( 'renewal_points', $level_id ) ); ?>"
                           name="e20r-mycpmp_renewal-points" id="e20r-mycpmp_renewal-points">
                    <small><?php printf( __( "Number of %s to award when payment renews.", "e20r-mycred-pmpro-integration" ), strtolower( $plural ) ); ?></small>
                </td>
            </tr>
            <tr class="e20r-settings-row">
                <th class="e20r-settings-cell">
                    <label for="e20r-mycpmp_max-level-points"><?php printf( __( "%s balance (max)", "e20r-mycred-pmpro-integration" ), $singular ); ?></label>
                </th>
                <td class="e20r-settings-cell">
                    <input type="number" size="20" name="e20r-mycpmp_max-level-points"
                           id="e20r-mycpmp_max-level-points"
                           value="<?php echo !empty( $max_level_points ) ? esc_attr( $max_level_points ) : 0; ?>">
                    <small><?php printf( __( "Use %d to grant 'unlimited' points. Use '0' to signify that the membership level isn't capped", "e20r-mycred-pmpro-integration" ), $max_balance ); ?></small>

                </td>
            </tr>

            <tr class="e20r-settings-row">
                <th class="e20r-settings-cell">
                    <label for="e20r-mycpmp_renewal-notice"><?php _e( "Renewal Notice (if enabled)", "e20r-mycred-pmpro-integration" ); ?></label>
                </th>
                <td class="e20r-settings-cell">
                    <input type="text" size="<?php esc_attr_e( $text_size ); ?>" name="e20r-mycpmp_renewal-notice"
                           id="e20r-mycpmp_renewal-notice" value="<?php esc_attr_e( $renewal_notice ); ?>">
                </td>
            </tr>
            <tr class="e20r-settings-row">
                <th class="e20r-settings-cell">
                    <label for="e20r-mycpmp_point-type"><?php printf( __( "%s type (meta key)", "e20r-mycred-pmpro-integration" ), $singular ); ?></label>
                </th>
                <td class="e20r-settings-cell">
                    <input type="text" size="20" name="e20r-mycpmp_point-type"
                           id="e20r-mycpmp_point-type"
                           value="<?php echo ! empty( $type_key ) ? esc_attr( $type_key ) : null; ?>">
                    <small><?php _e( "Leave blank if you want to use the default value (mycred_default_total)", "e20r-mycred-pmpro-integration" ); ?></small>
                </td>
            </tr>
            </tbody>
        </table>
		<?php
	}

	/**
     * Assign myCred Points per the level configuration when the payment gateway notifies us
     * of a successful recurring payment.
     *
	 * @param $order
	 *
	 * @return bool|null
	 */
	public function subscriptionPaymentComplete( $order ) {

		// myCred plugin missing/not loaded
		if ( ! function_exists( 'mycred' ) ) {
			error_log( "MyCred Plugin not loaded/active" );

			return null;
		}

		if ( ! function_exists( 'pmpro_getLevel' ) ) {
			error_log( "Paid Memberships Pro Plugin not loaded/active" );

			return null;
		}

		// No membership info found..
		if ( ! isset( $order->membership_id ) || empty( $order->membership_id ) ) {
			error_log( "No Membership level ID included in order" );

			return null;
		}

		// No user to process for...
		if ( ! isset( $order->user_id ) || empty( $order->user_id ) ) {
			error_log( "No user ID included in order" );

			return null;
		}

		$score = $this->getSetting( 'renewal_points', $order->membership_id );

		// No score defined
		if ( empty( $score ) ) {
			error_log( "No score defined for this level" );

			return null;
		}

		$point_type = $this->getSetting( 'point_type', $order->membership_id );
		$mycred     = mycred( $point_type );

		$level = pmpro_getLevel( $order->membership_id );

		// Add the points to the mycred account for the user and add a notification message.
		if ( ! empty( $level->id ) || false === $mycred->exclude_user( $order->user_id ) ) {

			$point_description = $this->getSetting( 'reference_text', $order->membership_id );
			$max_level_balance = $this->getSetting( 'max_level_points', $order->membership_id );

			$current_balance = $mycred->get_users_balance( $order->user_id );

			$level_reference = apply_filters( 'e20r-mycred-pmpro-default_type', 'Points for Renewal' );
			$max_balance     = apply_filters( 'e20r-mycred-pmpro-max-balance', '999999' );

			$new_balance = $current_balance + $score;

			// Keep the
			if ( ! empty( $max_level_balance ) && ( $new_balance > $max_level_balance ) ) {
				$score += ( $max_level_balance - $new_balance );
			} else if ( ! empty( $max_level_balance ) && $max_level_balance == $max_balance ) {
				$score += ( $max_balance - $current_balance );
			}

			if ( WP_DEBUG ) {
				error_log( "Adding {$score} points to user {$order->user_id}" );
			}

			if ( ! empty( $score ) && true === $mycred->add_creds(
					$level_reference,
					$order->user_id,
					$score,
					$point_description,
					date_i18n( 'Y-m-d', current_time( 'timestamp' ) ),
					$point_type )
			) {

				if ( ( $score > 0 ) && ($current_balance + $score <= $max_level_balance) && function_exists( 'mycred_add_new_notice' ) ) {

					// Level warrants unlimited points
					if ( $max_level_balance == $max_balance ) {

						$points_message =
							apply_filters(
								'e20r-mycred-pmpro-renewal-notice',
								sprintf(
									__( "Your payment for the %s membership level earned you unlimited %s",
										"e20r-mycred-pmpro-integration" ),
									$level->name, strtolower( $mycred->plural() ) ),
								$level,
								$score
							);

					} else {
						$points_message =
							apply_filters(
								'e20r-mycred-pmpro-renewal-notice',
								sprintf(
									__( "Your payment for the %s membership level earned you %s %s",
										"e20r-mycred-pmpro-integration" ),
									$level->name, $score, strtolower( $mycred->plural() ) ),
								$level,
								$score
							);
					}

					mycred_add_new_notice( array( 'user_id' => $order->user_id, 'message' => $points_message ) );
				}
			} else {
				if ( WP_DEBUG ) {
					error_log( "Unable to add {$score} points for {$order->user_id}" );
				}
			}
		}

		return true;
	}

	/**
     * Wrapper for the checkout process (when the checkout - payment - is confirmed).
     *
	 * @param int $user_id
	 * @param MemberOrder $order
     *
	 */
	public function checkoutConfirmed( $user_id, $order ) {

	    $order->user_id = $user_id;
        $this->subscriptionPaymentComplete( $order );
    }

	/**
	 * Function used to test the environment (requires WP_DEBUG to be true).
	 */
	public function runTestUpdateBilling() {

		if ( WP_DEBUG ) {
			error_log( "e20rMCPMP: Run test environment?" );

			$run_test = $this->util->_get_variable( 'e20rmcpmpi_test', false );

			if ( false !== $run_test ) {

				$order = new MemberOrder();
				$order->getLastMemberOrder( 1, 'success' );

				if ( WP_DEBUG && ! empty( $order->membership_id ) ) {
					error_log( "Loaded order {$order->id} for {$order->user_id}" );
				} else {
					error_log( 'Order info: ' . print_r( $order, true ) );
				}

				do_action( 'pmpro_subscription_payment_completed', $order );
			}
		}
	}

	/**
	 * Autoloader class for the plugin.
	 *
	 * @param string $class_name Name of the class being attempted loaded.
	 */
	public function __class_loader( $class_name ) {

		$classes = array(
			strtolower( get_class( $this ) ),
			'e20rutils',
			'e20rlicense'
		);

		$plugin_classes = $classes;

		if ( in_array( strtolower( $class_name ), $plugin_classes ) && ! class_exists( $class_name ) ) {

			$name = strtolower( $class_name );

			$filename     = dirname( __FILE__ ) . "/classes/class.{$name}.php";
			$utils_file   = dirname( __FILE__ ) . "/utilities/class.{$name}.php";
			$license_file = dirname( __FILE__ ) . "/license/class.{$name}.php";

			if ( file_exists( $filename ) ) {
				require_once $filename;
			}

			if ( file_exists( $utils_file ) ) {
				require_once $utils_file;
			}

			if ( file_exists( $license_file ) ) {
				require_once $license_file;
			}
		}
	} // End of autoloader method
}

spl_autoload_register( array( e20rMyCredPmproIntegration::get_instance(), '__class_loader' ) );
add_action( 'plugins_loaded', 'e20rMyCredPmproIntegration::get_instance' );


if ( ! class_exists( '\\PucFactory' ) ) {
	require_once( plugin_dir_path( __FILE__ ) . 'plugin-updates/plugin-update-checker.php' );
}

// TODO: Fix paths for updateChecker
$plugin_updates = \PucFactory::buildUpdateChecker(
	'https://eighty20results.com/protected-content/e20r-mycred-pmpro-integration/metadata.json',
	__FILE__,
	'e20r-mycred-pmpro-integration'
);
