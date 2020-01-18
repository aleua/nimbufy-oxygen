<?php

/*
Plugin Name: Nimbufy for Oxygen
Author: Gagan S Goraya
Author URI: https://gagangoraya.com
Description: Nimbufy for Oxygen lets you generate Oxygen layout out of sections on any web page. BETA. USE AT YOUR OWN RISK.
Version: 1.0 Beta 1
*/

class NimbufyOxygen {
	const VERSION = '1.0b1';
	const PREFIX = 'yetowohai';
	const NONCE_ACTION = self::PREFIX.'_nonce';
	const API_URL = 'https://server.nimbufy.com/v1/';
	const SERVICE_URL = self::API_URL.'ui';
	
	const TITLE = 'Nimbufy';

	static function init() {

			// dashboard interface
			add_action( 'admin_menu', array(__CLASS__, "admin_menu") );
			add_action( 'admin_post_'.self::PREFIX.'_login', array(__CLASS__, "login"));

			add_action( 'init', array(__CLASS__, "logout"));
		
			// oxygen builder interface
			add_action("oxygen_basics_components_containers", array(__CLASS__, "component_button")  );
			add_action("wp_enqueue_scripts", array(__CLASS__, "scripts"));
			add_action("wp_footer", array(__CLASS__, "footer"));
			add_action("wp_ajax_yetowohai_get_component", array(__CLASS__, "get_component"));
		
	}

	static function admin_menu() {
		$page_hook_suffix = add_menu_page( self::TITLE, self::TITLE, 'publish_posts', self::PREFIX, array( __CLASS__, 'dashboard' ), 'dashicons-admin-generic');
		//add_action( "admin_print_scripts-{$page_hook_suffix}", array( __CLASS__ , 'admin_page_scripts' ));
	}

	static function getIDToken() {
		$idToken = get_transient(self::PREFIX.'_id_token');
		
		if(!$idToken) {
			
			$idToken = self::refreshTokens();
		}

		return $idToken;
	}

	static function refreshTokens() {
		$refreshToken = get_transient(self::PREFIX.'_refresh_token');
		
		$idToken = false;
		if($refreshToken) {
			
			// process authentication 
			$response = wp_remote_post(
            self::API_URL.'auth',
	            array(
	                'body' => json_encode(array(
	                	'action' => 'refresh',
	                    'refreshtoken'   => $refreshToken
	                )),
	                //'timeout' => 30
	            )
	        );

			$body = json_decode($response['body']);

			if($body->AuthenticationResult) {
				self::store_tokens($body->AuthenticationResult);
			}
			$idToken = get_transient(self::PREFIX.'_id_token');
		}

		if($idToken) {
			return $idToken;
		}
		else {
			// redirect to login screen
			self::redirect_to_login();
		}
	}


	static function redirect_to_login() {
		wp_send_json(array(
			'errorMessage' => 'Authorization failed. Authenticate at WP Dashboard -> '.self::TITLE
		));
		die(0);
	}

	static function logout() {

		if(isset($_GET['page']) && $_GET['page'] === self::PREFIX &&
			isset($_GET['action']) && $_GET['action'] === self::PREFIX.'_logout') {
			
			check_admin_referer(self::NONCE_ACTION);
			delete_transient(self::PREFIX.'_access_token');
			delete_transient(self::PREFIX.'_id_token');
			delete_transient(self::PREFIX.'_refresh_token');

			wp_redirect(add_query_arg(array('page' => self::PREFIX), admin_url('admin.php')));
			exit();
		}

	}

	static function login() {
		check_admin_referer(self::NONCE_ACTION);
		
		$username = isset($_POST['username']) ? sanitize_text_field($_POST['username']) : false;
		$password = isset($_POST['password']) ? sanitize_text_field($_POST['password']) : false;

		if($username === false || $password === false) {
			die(0);
		}

		$response = wp_remote_post(
            self::API_URL.'auth',
            array(
            	//'headers' => array('Content-Type' => 'application/json; charset=utf-8'),
                'body' => json_encode(array(
                	'action' => 'login',
                    'username'   => $username,
                    'password'     => $password
                )),
                //'timeout' => 30
            )
        );

		$body = json_decode($response['body']);

		$stored = false;
		if($body->AuthenticationResult) {
			self::store_tokens($body->AuthenticationResult);
			$stored = true;
		}

        wp_redirect(add_query_arg(array('page' => self::PREFIX), admin_url('admin.php')));
        exit();
		
	}

	static function store_tokens($authResult) {

		if($authResult->AccessToken) {
			set_transient(
				self::PREFIX.'_access_token', 
				$authResult->AccessToken,
				($authResult->ExpiresIn ? intval($authResult->ExpiresIn): 3600)-200
			);
		}
		if($authResult->IdToken) {
			set_transient(
				self::PREFIX.'_id_token', 
				$authResult->IdToken,
				($authResult->ExpiresIn ? intval($authResult->ExpiresIn): 3600)-200
			);
		}
		if($authResult->RefreshToken) {
			set_transient(
				self::PREFIX.'_refresh_token', 
				$authResult->RefreshToken,
				3600*24*29
			);
		}

	}

	static function login_form() {
		// if logged in, show logout option
		
		$refreshToken = get_transient(self::PREFIX.'_refresh_token');
		?>
		<div class="wrap">
			<h2><?php echo self::TITLE;?></h2>
		<?php
		if($refreshToken) {
			?>
			<a href="<?php echo wp_nonce_url(
					add_query_arg(
						array('page' => self::PREFIX, 'action' => self::PREFIX.'_logout'), 
						admin_url('admin.php')
					),
					self::NONCE_ACTION
					);?>">Logout</a>
			<?php
			
		} else {
		?>
		
			<h3>Log in to use the service</h3>
			<form class="yetowohai_login" action="admin-post.php?action=<?php echo self::PREFIX.'_login';?>" method="post">
				<?php
					wp_nonce_field(self::NONCE_ACTION);
				?>
				<input type="text" name="username" id="username" placeholder="email or username" />
				<input type="password" name="password" id="password" placeholder="password" />
				<button id="login">Login</button>
			</form>
			<p><a href="https://server.nimbufy.com/v1/ui?action=forgot" target="_blank">Forgot your password?</a> | <a href="https://server.nimbufy.com/v1/ui?action=signup" target="_blank">Sign up</a> | <a href="https://server.nimbufy.com/v1/ui" target="_blank">Manage Account</a></p>
		

		<?php }?>

		</div>

		<?php
	}

	static function dashboard() {

		self::login_form();
		
	}

	// static function admin_page_scripts() {
	// 	wp_enqueue_script('yetowohai-admin', plugins_url('js/admin.js', __FILE__));
	// }

	static function get_component() {

		check_ajax_referer(self::NONCE_ACTION);
		
		$targeturl = isset($_POST['yetowohai_url']) ? sanitize_text_field($_POST['yetowohai_url']) : false;
		$selector = isset($_POST['yetowohai_selector']) ? sanitize_text_field($_POST['yetowohai_selector']) : false;

		if($targeturl === false || $selector === false) {
			die();
		}

		$idToken = self::getIDToken();

		$response = wp_remote_post(
            'http://localhost:3000',//self::API_URL.'main',//
            array(
            	// 'headers' => array(
            	// 	'Auth' => $idToken,
            	// 	'Content-Type' => 'application/json'
            	// ),
             //    'body' => json_encode(array(
             //        'targeturl'   => $targeturl,
             //        'selector'     => $selector
             //    )),
                'body' => array( // this is for local testing
                    'targeturl'	=>	$targeturl,
                    'selector'	=>	$selector
                ),
                'timeout' => 60
            )

        );

		if(isset($response['response']) && $response['response']['code'] === 401 ) {
			 self::redirect_to_login();
		}

        // if authentication failure
        	// self::redirect_to_login();

		
		header('Content-Type: application/json');
		
		echo $response['body'];
		die();
	}

	static function footer() {
		if ( !defined("SHOW_CT_BUILDER") || defined("OXYGEN_IFRAME") ) {
			return;
		}
		
		?>
		<div id="yetowohai" style="display: none">
			<svg class="oxygen-close-icon" ng-click="iframeScope.yetowohaiclose();"><use xlink:href="#oxy-icon-cross"></use></svg>
			<form>
				
				<?php
				wp_nonce_field(self::NONCE_ACTION);
				?>
				<input type="hidden" name="action" id="action" value="yetowohai_get_component" />
				<div class="oxygen-control-row">
					<div class="oxygen-control-wrapper">
						<label class="oxygen-control-label">Target URL</label>
						<div class="oxygen-measure-box">
							<input type="text" name="yetowohai_url" id="yetowohai_url" placeholder="target site url" value="https://themes.muffingroup.com/betheme/" />
						</div>
					</div>
				</div>
				<div class="oxygen-control-row">
					<div class="oxygen-control-wrapper">
						<label class="oxygen-control-label">Query Selector</label>
						<div class="oxygen-measure-box">
							<input type="text" name="yetowohai_selector" id="yetowohai_selector" placeholder="#ID or .classname" value=".mcb-item-623ec056f" />
						</div>
					</div>
				</div>
				<button class="oxygen-sidebar-advanced-subtab">Insert</button>
				
			</form>

			<div class="yetowohai_status" style="display:none">
				<span class="progress" style="display:none">Processing...</span>
				<span class="complete" style="display:none">Completed</span>
				<span class="fail" style="display:none">Failed</span>
				<div class="description">
					
				</div>
			</div>
		</div>

		<?php
	}

	static function scripts() {
		if(!defined("SHOW_CT_BUILDER")) {
			return;
		}

		if ( defined("OXYGEN_IFRAME") ) {
			wp_register_script('yetowohai-script', plugins_url('js/script.js', __FILE__), array('ct-angular-main'), self::VERSION);
			wp_localize_script('yetowohai-script', 'yetowohai', array(
				'title'=> self::TITLE,
				'slug' => self::PREFIX
			));
			wp_enqueue_script('yetowohai-script');
		}

		wp_enqueue_style('yetowohai-style', plugins_url('css/style.css', __FILE__), array(), self::VERSION);
	}

	static function component_button() {
		//$icon = str_replace(" ", "", (strtolower($this->options['name']))); ?>

		<div class='oxygen-add-section-element'
			data-searchid="<?php echo strtolower( preg_replace('/\s+/', '_', 'yetowohai' ) ) ?>"
			ng-click="iframeScope.yetowohai()">

			<img src='<?php echo CT_FW_URI; ?>/toolbar/UI/oxygen-icons/add-icons/small-generic.svg' />
			<img src='<?php echo CT_FW_URI; ?>/toolbar/UI/oxygen-icons/add-icons/small-generic.svg' />
			<?php echo self::TITLE;?>
		</div>
		<?php
	}
}

NimbufyOxygen::init();