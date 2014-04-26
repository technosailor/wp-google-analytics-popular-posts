<?php
/*
Plugin Name: WP Google Analytics Popular Posts
Author: Aaron Brazell
Author URI: http://technosailor.com
Description: Uses Google Analytics to determine popular posts
Version: 1.0-alpha
Text Domain: wp-google-analytics-popular-posts
*/

class WP_GA_PP {

	public $saved;
	public $errors;

	public $opts;

	public $google_auth_url;
	public $google_token_url;
	public $rt;
	public $client_id;
	protected $client_secret;
	public $redirect;
	public $redirect_token;
	public $scope;
	public $state;
	public $access_type;

	private $auth_code;

	public function __construct() {
		$this->saved = false;
		$this->errors = false;

		$this->client_id = WP_GA_PP_Settings::get_client_id();
		$this->client_secret = WP_GA_PP_Settings::get_client_secret();

		$this->google_auth_url = 'https://accounts.google.com/o/oauth2/auth';
		$this->google_token_url = 'https://accounts.google.com/o/oauth2/token';
		$this->rt = 'code';
		$this->redirect = admin_url( '/options-general.php?page=ga-popular-posts&wp-ga-oauth-callback') ;
		$this->scope = 'https://www.googleapis.com/auth/analytics';
		$this->scope = 'https://www.googleapis.com/auth/analytics';
		$this->state = time();
		$this->access_type = 'offline';

		$this->auth_code = $this->get_auth_code();


		$this->hooks();
	}

	public function hooks() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_notices', array( $this, 'notices' ) );
		add_action( 'admin_init', array( $this, '_save_settings' ) );
		add_action( 'admin_init', array( $this, 'handle_oauth_callback' ) );
	}

	public function admin_menu() {
		add_options_page( __( 'GA Popular Posts', 'wp-google-analytics-popular-posts' ), __( 'GA Popular Posts', 'wp-google-analytics-popular-posts' ), 'administrator', 'ga-popular-posts', array( $this, 'admin_html' ) );
	}

	public function admin_html() {

		$nonce = wp_create_nonce( 'wp_ga_pp_settings_nonce' );
		?>
		<form action="" method="post">
		<div class="wrap">
			<h2><?php _e( 'Google Analytics Popular Posts Settings', 'wp-google-analytics-popular-posts' ) ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php _e( 'Client ID', 'wp-google-analytics-popular-posts' ) ?></th>
					<td><input class="regular-text" type="text" name="google_client_id" value="<?php echo $this->client_id ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><?php _e( 'Client Secret', 'wp-google-analytics-popular-posts' ) ?></th>
					<td><input class="regular-text" type="password" name="google_client_secret" value="<?php echo $this->client_secret ?>" /></td>
				</tr>
			</table>
			<input type="hidden" name="wp_ga_pp_settings_nonce" value="<?php echo $nonce ?>" />
			<input type="submit" name="submit" class="button button-secondary" />

		</div>
		</form>
		<?php

		if( $this->client_id && $this->client_secret ) {
			$url = $this->google_auth_url . $this->build_auth_qs();
			?>
			<h2><?php _e( 'Authorize WordPress to Access your Google Analytics', 'wp-google-analytics-popular-posts' ) ?></h2>
			<a href="<?php echo $url ?>"><?php _e( 'Authorize', 'wp-google-analytics-popular-posts' ) ?></a>
			<?php
		}
	}

	public function get_auth_code() { 
		if( get_transient( 'ga_auth_code' ) ) {
			$this->access_token = get_transient( 'ga_auth_code' );
			return get_transient( 'ga_auth_code' );
		}
		else {
			return false;
		}
	}

	public function handle_oauth_callback() {
		if( !isset( $_GET['wp-ga-oauth-callback'] ) )
			return false;
		if( isset( $_GET['wp-ga-oauth-callback'] ) ) {
			if( isset( $_GET['code'] ) ) {
				set_transient( 'ga_auth_code', $_GET['code'], 0 ); 
				$this->auth_code = $_GET['code'];
				$args = array(
					'code' => $_GET['code'], 
					'client_id' => $this->client_id,
					'client_secret' => $this->client_secret,
					'redirect_uri' => $this->redirect,
					'grant_type' => 'authorization_code'
				);

				$response = wp_remote_post( $this->google_token_url, array( 'body' => $args ) );
				$json = wp_remote_retrieve_body( $response );
				$token = json_decode( $json );
				set_transient( 'ga_access_token', $token->access_token, $token->expires_in );
			}
			wp_safe_redirect( admin_url( '/options-general.php?page=ga-popular-posts' ) );
		}
	}

	public function build_auth_qs() {
		$qs = '?';
		$qs .= 'scope=' . $this->scope;
		$qs .= '&state=' . $this->state;
		$qs .= '&redirect_uri=' . urlencode( $this->redirect );
		$qs .= '&response_type=' . $this->rt;
		$qs .= '&client_id=' . $this->client_id;
		return $qs;
	}

	public function _save_settings() {
		$data = array();
		if( !isset( $_POST['wp_ga_pp_settings_nonce'] ) || !wp_verify_nonce( $_POST['wp_ga_pp_settings_nonce'], 'wp_ga_pp_settings_nonce' ) )
			return false;

		if( isset( $_POST['google_client_id'] ) ) {
			$data['client_id'] = $_POST['google_client_id'];
		}
		if( isset( $_POST['google_client_secret'] ) ) {
			$data['client_secret'] = $_POST['google_client_secret'];
		}
		if( update_option( 'wp_ga_pp_settings', $data ) ) {
			$this->saved = array( __( 'Settings Saved', 'wp-google-analytics-popular-posts' ) );
		}
		else {
			$this->errors = array( __( 'Settings not saved', 'wp-google-analytics-popular-posts' ) );
		}
	}

	public function notices() {
		if( is_array( $this->errors ) && !empty( $this->errors ) ) {
			echo '<div class="error">';
				echo '<ul>';
				foreach( $this->errors as $e ) {
					echo sprintf( '<li>%s</li>', $e );
				}
				echo '</ul>';
			echo '</div>';
		}
		if( is_array( $this->saved ) && !empty( $this->saved ) ) {
			echo '<div class="updated">';
				echo '<ul>';
				foreach( $this->saved as $m ) {
					echo sprintf( '<li>%s</li>', $m );
				}
				echo '</ul>';
			echo '</div>';
		}
	}
}

$wpgapp =  new WP_GA_PP;

class WP_GA_PP_Settings {

	static function get_client_id() {
		$settings = get_option( 'wp_ga_pp_settings' );
		if( isset( $settings['client_id'] ) && $settings['client_id'] != '' ) {
			return $settings['client_id'];
		}

		return false;
	}

	static function get_client_secret() {
		$settings = get_option( 'wp_ga_pp_settings' );
		if( isset( $settings['client_secret'] ) && $settings['client_secret'] != '' ) {
			return $settings['client_secret'];
		}

		return false;
	}
}