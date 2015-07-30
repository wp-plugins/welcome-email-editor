<?php
/*
Plugin Name: SB Welcome Email Editor
Plugin URI: http://www.sean-barton.co.uk
Description: Allows you to change the content, layout and even add an attachment for many of the inbuilt Wordpress emails. Simple!
Version: 4.3
Author: Sean Barton
Author URI: http://www.sean-barton.co.uk
*/

$sb_we_file = trailingslashit(str_replace('\\', '/', __FILE__));
$sb_we_dir = trailingslashit(str_replace('\\', '/', dirname(__FILE__)));
$sb_we_home = trailingslashit(str_replace('\\', '/', get_bloginfo('wpurl')));
$sb_we_active = true;

define('SB_WE_PRODUCT_NAME', 'SB Welcome Email');
define('SB_WE_PLUGIN_DIR_PATH', $sb_we_dir);
define('SB_WE_PLUGIN_DIR_URL', trailingslashit(str_replace(str_replace('\\', '/', ABSPATH), $sb_we_home, $sb_we_dir)));
define('SB_WE_PLUGIN_DIRNAME', str_replace('/plugins/','',strstr(SB_WE_PLUGIN_DIR_URL, '/plugins/')));

$sb_we_admin_start = '<div id="poststuff" class="wrap"><h2>' . SB_WE_PRODUCT_NAME . '</h2>';
$sb_we_admin_end = '</div>';

function sb_we_loaded() {

	add_action('init', 'sb_we_init');
	add_action('admin_menu', 'sb_we_admin_page');

	if( $settings = get_option('sb_we_settings') ) {	// prevent warning on $settings use when first enabled
	}

	add_filter('wpmu_welcome_user_notification', 'sb_we_mu_new_user_notification', 10, 3 );

	global $sb_we_active;

	if (is_admin() && !empty($_REQUEST['_wp_http_referer'])) {
		if (!$sb_we_active) {
			$msg = '<div class="error"><p>' . SB_WE_PRODUCT_NAME . ' can not function because another plugin is conflicting. Please disable other plugins until this message disappears to fix the problem.</p></div>';
			add_action('admin_notices', create_function( '', 'echo \'' . $msg . '\';' ));
		}

		foreach ($_REQUEST as $key=>$value) {
			if (substr($key, 0, 6) == 'sb_we_') {
				if (substr($key, 0, 13) == 'sb_we_resend_') {
					if ($user_id = substr($key, 13)) {
						sb_we_send_new_user_notification($user_id, true);
						wp_redirect(admin_url('users.php'));
					}
				}
			}
		}
	}
}

function sb_we_lost_password_title($content) {
	$settings = get_option('sb_we_settings');

	if ($settings->password_reminder_subject) {
		if ( is_multisite() ) $blogname = $GLOBALS['current_site']->site_name;
		else $blogname = esc_html(get_option('blogname'), ENT_QUOTES);

		$content = $settings->password_reminder_subject;
		$content = str_replace('[blog_name]', $blogname, $content);
	}

	return $content;
}

function sb_we_lost_password_message($message, $key) {
	global $wpdb;

	$settings = get_option('sb_we_settings');

	if (trim($settings->password_reminder_body)) {
		if ($user_login = $wpdb->get_var($wpdb->prepare("SELECT user_login FROM $wpdb->users WHERE user_activation_key = %s", $key))) {
			$site_url = site_url();

			if ( is_multisite() ) $blogname = $GLOBALS['current_site']->site_name;
			else $blogname = esc_html(get_option('blogname'), ENT_QUOTES);

			$reset_url = trailingslashit(site_url()) . "wp-login.php?action=rp&key=$key&login=" . rawurlencode($user_login);
			$message = $settings->password_reminder_body; //'Someone requested that the password be reset for the following account: [site_url]' . "\n\n" . 'Username: [user_login]' . "\n\n" . 'If this was a mistake, just ignore this email and nothing will happen.' . "\n\n" . 'To reset your password, visit the following address: [reset_url]';

			$message = str_replace('[user_login]', $user_login, $message);
			$message = str_replace('[blog_name]', $blogname, $message);
			$message = str_replace('[site_url]', $site_url, $message);
			$message = str_replace('[reset_url]', $reset_url, $message);
		}
	}

	return $message;
}

function sb_we_send_new_user_notification($user_id) {
	$return = false;

	if (!$plaintext_pass = get_user_meta($user_id, 'sb_we_plaintext_pass', true)) { //depracated but leaving the functionlality in in case anyone wants to hook into it later
		$plaintext_pass = '[Your Password Here]';
	}

	if (wp_new_user_notification($user_id, $plaintext_pass)) {
		$return = 'Welcome email sent.';
	}

	return $return;
}

function sb_we_mu_new_user_notification($user_id, $password, $meta='') {
	return wp_new_user_notification($user_id, $password);
}

function sb_we_priority_load_plugin() {
	$wp_path_to_this_file = preg_replace('/(.*)plugins\/(.*)$/', WP_PLUGIN_DIR."/$2", __FILE__);
	$this_plugin = plugin_basename(trim($wp_path_to_this_file));
	$active_plugins = get_option('active_plugins');
	$this_plugin_key = array_search($this_plugin, $active_plugins);
	
	if ($this_plugin_key) { // if it's 0 it's the first plugin already, no need to continue
		array_splice($active_plugins, $this_plugin_key, 1);
		array_unshift($active_plugins, $this_plugin);
		update_option('active_plugins', $active_plugins);
	}
	
}

function sb_we_init() {
	
	sb_we_priority_load_plugin();
	
	if (!$sb_we_settings = get_option('sb_we_settings')) {
		$blog_name = get_option('blogname');

		$sb_we_settings = new stdClass();
		$sb_we_settings->user_subject = '[[blog_name]] Your username and password';
		$sb_we_settings->user_body = 'Username: [user_login]<br />Password: [user_password]<br />[login_url]';
		$sb_we_settings->admin_subject = '[[blog_name]] New User Registration';
		$sb_we_settings->admin_body = 'New user registration on your blog ' . $blog_name . '<br /><br />Username: [user_login]<br />Email: [user_email]';
		$sb_we_settings->admin_notify_user_id = 1;
		$sb_we_settings->header_from_name = '';
		$sb_we_settings->header_from_email = '[admin_email]';
		$sb_we_settings->header_reply_to = '[admin_email]';
		$sb_we_settings->header_send_as = 'html';
		$sb_we_settings->header_additional = '';
		$sb_we_settings->set_global_headers = 1;
		$sb_we_settings->we_attachment_url = '';
		$sb_we_settings->password_reminder_subject = '[[blog_name]] Forgot Password';
		$sb_we_settings->password_reminder_body = 'Someone requested that the password be reset for the following account: [site_url]<br /><br />Username: [user_login]<br /><br />If this was a mistake, just ignore this email and nothing will happen.<br /><br />To reset your password, visit the following address: [reset_url]';

		add_option('sb_we_settings', $sb_we_settings);
	}

}

function sb_we_set_email_filter_headers($reset=false) {
	if ($reset) {
		remove_filter('wp_mail_from', 'sb_we_get_from_email', 1, 1);
		remove_filter('wp_mail_from_name', 'sb_we_get_from_name', 1, 1);
		remove_filter('wp_mail_content_type', create_function('$i', 'return "text/html";'), 1, 1);
		remove_filter('wp_mail_charset', 'sb_we_get_charset', 1, 1);
		
		do_action('sb_we_email_headers_reset');
	} else {
		$sb_we_settings = get_option('sb_we_settings');
	
		if ($from_email = $sb_we_settings->header_from_email) {
			add_filter('wp_mail_from', 'sb_we_get_from_email', 1, 1);
	
			if ($from_name = $sb_we_settings->header_from_name) {
				add_filter('wp_mail_from_name', 'sb_we_get_from_name', 1, 1);
			}
		}
		if ($send_as = $sb_we_settings->header_send_as) {
			if ($send_as == 'html') {
				add_filter('wp_mail_content_type', create_function('$i', 'return "text/html";'), 1, 1);
				add_filter('wp_mail_charset', 'sb_we_get_charset', 1, 1);
			}
		}
		
		do_action('sb_we_email_headers');
	}
}

function sb_we_get_from_email() {
	$sb_we_settings = get_option('sb_we_settings');
	$admin_email = get_option('admin_email');
	return str_replace('[admin_email]', $admin_email, $sb_we_settings->header_from_email);
}

function sb_we_get_from_name() {
	$sb_we_settings = get_option('sb_we_settings');
	$admin_email = get_option('admin_email');
	return str_replace('[admin_email]', $admin_email, $sb_we_settings->header_from_name);
}

function sb_we_get_charset() {
	if (!$charset = get_bloginfo('charset')) {
		$charset = 'iso-8859-1';
	}

	return $charset;
}

add_action('ws_plugin__s2member_after_email_config_release', 'sb_we_set_global_from_details');

function sb_we_set_global_from_details() {
	$settings = get_option('sb_we_settings');
	add_filter('wp_mail_from', 'sb_we_get_from_email', 1, 100);
	add_filter('wp_mail_from_name', 'sb_we_get_from_name', 1, 100);
}

function sb_we_process_phpmailer_from_info(&$phpmailer) {
	$phpmailer->From = sb_we_get_from_email();
	$phpmailer->FromName = sb_we_get_from_name();
}
//add_action('phpmailer_init', 'sb_we_process_phpmailer_from_info',1); //disabled this as it was overkill and disrupting other email plugins

if (!function_exists('wp_new_user_notification')) {
	function wp_new_user_notification($user_id, $plaintext_pass = '') {
		global $sb_we_home, $current_site;;
		
		if (@$sb_we_settings->set_global_headers) {
			sb_we_set_email_filter_headers();
		}

		if ($user = new WP_User($user_id)) {
			$settings = get_option('sb_we_settings');

			update_user_meta($user_id, 'sb_we_last_sent', time());

			$blog_name = get_option('blogname');
			if (is_multisite()) {
				$blog_name = $current_site->site_name;
			}

			$admin_email = get_option('admin_email');

			$user_login = stripslashes($user->user_login);
			$user_email = stripslashes($user->user_email);

			$user_subject = apply_filters('sb_we_user_subject_template', $settings->user_subject);
			$user_message = apply_filters('sb_we_user_body_template', $settings->user_body);

			$admin_subject = apply_filters('sb_we_admin_subject_template', $settings->admin_subject);
			$admin_message = apply_filters('sb_we_admin_body_template', $settings->admin_body);

			$first_name = $user->first_name;
			$last_name = $user->last_name;

			//Headers
			$headers = '';
			if ($reply_to = $settings->header_reply_to) {
				$headers .= 'Reply-To: ' . $reply_to . "\r\n";
			}

			if ($from_email = $settings->header_from_email) {
				$from_email = str_replace('[admin_email]', $admin_email, $from_email);
				add_filter('wp_mail_from', 'sb_we_get_from_email', 1, 100);

				if ($from_name = $settings->header_from_name) {
					add_filter('wp_mail_from_name', 'sb_we_get_from_name', 1, 100);
					$headers .= 'From: ' . $from_name . ' <' . $from_email . ">\r\n";
				} else {
					$headers .= 'From: ' . $from_email . "\r\n";
				}
			}
			if ($send_as = $settings->header_send_as) {
				if ($send_as == 'html') {
					if (!$charset = get_bloginfo('charset')) {
						$charset = 'iso-8859-1';
					}
					$headers .= 'Content-type: text/html; charset=' . $charset . "\r\n";

					add_filter('wp_mail_content_type', create_function('$i', 'return "text/html";'), 1, 100);
					add_filter('wp_mail_charset', 'sb_we_get_charset', 1, 100);
				}
			}

			if ($additional = $settings->header_additional) {
				$headers .= $additional;
			}

			$headers = str_replace('[admin_email]', $admin_email, $headers);
			$headers = str_replace('[blog_name]', $blog_name, $headers);
			$headers = str_replace('[site_url]', $sb_we_home, $headers);
			
			$headers = apply_filters('sb_we_email_headers', $headers, $settings);
			//End Headers

			//Don't notify if the admin object doesn't exist;
			if ($settings->admin_notify_user_id) {
				//Allows single or multiple admins to be notified. Admin ID 1 OR 1,3,2,5,6,etc...
				$admins = explode(',', $settings->admin_notify_user_id);
				
				$date = date(get_option('date_format'));
				$time = date(get_option('time_format'));

				if (!is_array($admins)) {
					$admins = array($admins);
				}

				global $wpdb;
				$sql = 'SELECT meta_key, meta_value
					FROM ' . $wpdb->usermeta . '
					WHERE user_ID = ' . $user_id;
				$custom_fields = array();
				if ($meta_items = $wpdb->get_results($sql)) {
					foreach ($meta_items as $i=>$meta_item) {
						$custom_fields[$meta_item->meta_key] = $meta_item->meta_value;
					}
				}

				$admin_message = str_replace('[blog_name]', $blog_name, $admin_message);
				$admin_message = str_replace('[admin_email]', $admin_email, $admin_message);
				$admin_message = str_replace('[site_url]', $sb_we_home, $admin_message);
				$admin_message = str_replace('[login_url]', $sb_we_home . 'wp-login.php', $admin_message);
				$admin_message = str_replace('[user_email]', $user_email, $admin_message);
				$admin_message = str_replace('[user_login]', $user_login, $admin_message);
				$admin_message = str_replace('[first_name]', $first_name, $admin_message);
				$admin_message = str_replace('[last_name]', $last_name, $admin_message);
				$admin_message = str_replace('[user_id]', $user_id, $admin_message);
				$admin_message = str_replace('[plaintext_password]', $plaintext_pass, $admin_message);
				$admin_message = str_replace('[user_password]', $plaintext_pass, $admin_message);
				$admin_message = str_replace('[custom_fields]', '<pre>' . print_r($custom_fields, true) . '</pre>', $admin_message);
				$admin_message = str_replace('[date]', $date, $admin_message);
				$admin_message = str_replace('[time]', $time, $admin_message);
				
				if (strpos($admin_message, '[bp_custom_fields]')) {
					$admin_message = str_replace('[bp_custom_fields]', '<pre>' . print_r(sb_we_get_bp_custom_fields($user_id), true) . '</pre>', $admin_message);
				}

				$admin_subject = str_replace('[blog_name]', $blog_name, $admin_subject);
				$admin_subject = str_replace('[site_url]', $sb_we_home, $admin_subject);
				$admin_subject = str_replace('[first_name]', $first_name, $admin_subject);
				$admin_subject = str_replace('[last_name]', $last_name, $admin_subject);
				$admin_subject = str_replace('[user_email]', $user_email, $admin_subject);
				$admin_subject = str_replace('[user_login]', $user_login, $admin_subject);
				$admin_subject = str_replace('[user_id]', $user_id, $admin_subject);
				$admin_subject = str_replace('[date]', $date, $admin_subject);
				$admin_subject = str_replace('[time]', $time, $admin_subject);
				
				$admin_message = apply_filters('sb_we_email_admin_message', $admin_message, $settings);
				$admin_subject = apply_filters('sb_we_email_admin_subject', $admin_subject, $settings);
				
				$admins = apply_filters('sb_we_email_admins', $admins);

				foreach ($admins as $admin_id) {
					if ($admin = new WP_User($admin_id)) {
						wp_mail($admin->user_email, $admin_subject, $admin_message, $headers);
					}
				}
			}

			if ($plaintext_pass) {
				$user_message = str_replace('[admin_email]', $admin_email, $user_message);
				$user_message = str_replace('[site_url]', $sb_we_home, $user_message);
				$user_message = str_replace('[login_url]', $sb_we_home . 'wp-login.php', $user_message);
				$user_message = str_replace('[user_email]', $user_email, $user_message);
				$user_message = str_replace('[user_login]', $user_login, $user_message);
				$user_message = str_replace('[last_name]', $last_name, $user_message);
				$user_message = str_replace('[first_name]', $first_name, $user_message);
				$user_message = str_replace('[user_id]', $user_id, $user_message);
				$user_message = str_replace('[plaintext_password]', $plaintext_pass, $user_message);
				$user_message = str_replace('[user_password]', $plaintext_pass, $user_message);
				$user_message = str_replace('[blog_name]', $blog_name, $user_message);
				$user_message = str_replace('[date]', $date, $user_message);
				$user_message = str_replace('[time]', $time, $user_message);

				$user_subject = str_replace('[blog_name]', $blog_name, $user_subject);
				$user_subject = str_replace('[site_url]', $sb_we_home, $user_subject);
				$user_subject = str_replace('[user_email]', $user_email, $user_subject);
				$user_subject = str_replace('[last_name]', $last_name, $user_subject);
				$user_subject = str_replace('[first_name]', $first_name, $user_subject);
				$user_subject = str_replace('[user_login]', $user_login, $user_subject);
				$user_subject = str_replace('[user_id]', $user_id, $user_subject);
				$user_subject = str_replace('[date]', $date, $user_subject);
				$user_subject = str_replace('[time]', $time, $user_subject);
				
				$user_subject = apply_filters('sb_we_email_subject', $user_subject, $settings);
				$user_message = apply_filters('sb_we_email_message', $user_message, $settings);
				
				$attachment = false;
				if (trim($settings->we_attachment_url)) {
					$attachment = str_replace(trailingslashit(site_url()), trailingslashit($_SERVER['DOCUMENT_ROOT']), $settings->we_attachment_url);
				}
				
				wp_mail($user_email, $user_subject, $user_message, $headers, $attachment);
			}
		}
		
		if (@$sb_we_settings->set_global_headers) {
			sb_we_set_email_filter_headers(true);
		}

		return true;
	}
} else {
	$sb_we_active = false;
}

function sb_we_get_bp_custom_fields($user_id) {
	global $wpdb;

	$sql = 'SELECT f.name, d.value
		FROM
			' . $wpdb->prefix . 'bp_xprofile_fields f
			JOIN ' . $wpdb->prefix . 'bp_xprofile_data d ON (d.field_id = f.id)
		WHERE d.user_id = ' . $user_id;

	$array = $wpdb->get_results($sql);
	$assoc_array = array();

	foreach($array as $key=>$value) {
		$assoc_array[$value->name] = $value->value;
	}
	
	$assoc_array = apply_filters('sb_we_custom_fields', $assoc_array);

	return $assoc_array;
}

function sb_we_update_settings() {
	$old_settings = get_option('sb_we_settings');

	$settings = new stdClass();
	if ($post_settings = sb_we_post('settings')) {
		foreach ($post_settings as $key=>$value) {
			$settings->$key = stripcslashes($value);
		}

		if (update_option('sb_we_settings', $settings)) {
			sb_we_display_message(__('Settings have been successfully saved', 'sb_we'));
		}
	}
}

function sb_we_display_message($msg, $error=false, $return=false) {
    $class = 'updated fade';

    if ($error) {
        $class = 'error';
    }

    $html = '<div id="message" class="' . $class . '" style="margin-top: 5px; padding: 7px;">' . $msg . '</div>';

    if ($return) {
            return $html;
    } else {
            echo $html;
    }
}

function sb_we_settings() {
	if (sb_we_post('submit')) {
		sb_we_update_settings();
	}

	if (sb_we_post('test_send')) {
		global $current_user;
		get_currentuserinfo();

		wp_new_user_notification($current_user->ID, '[User password will appear here]');
		sb_we_display_message('Test email sent to "' . $current_user->user_email . '"');
	}

	$html = '';
	$settings = get_option('sb_we_settings');

	$page_options = array(
	'general_settings_label'=>array(
		'title'=>'General Settings'
		, 'type'=>'label'
		, 'style'=>'width: 500px;'
		, 'description'=>'These settings effect all of this plugin and, in some cases, all of your site.'
	)
	, 'settings[header_from_email]'=>array(
		'title'=>'From Email Address'
		, 'type'=>'text'
		, 'style'=>'width: 500px;'
		, 'description'=>'Global option change the from email address for all site emails'
	)
	, 'settings[header_from_name]'=>array(
		'title'=>'From Name'
		, 'type'=>'text'
		, 'style'=>'width: 500px;'
		, 'description'=>'Global option change the from name for all site emails'
	)
	, 'settings[header_send_as]'=>array(
		'title'=>'Send Email As'
		, 'type'=>'select'
		, 'style'=>'width: 100px;'
		, 'options'=>array(
			'text'=>'TEXT'
			, 'html'=>'HTML'
		)
		, 'description'=>'Send email as Text or HTML (Remember to remove html from text emails).'
	)
	, 'settings[set_global_headers]'=>array(
		'title'=>'Set Global Email Headers'
		, 'type'=>'yes_no'
		, 'style'=>'margin-left: 10px;'
		, 'description'=>'This is one of those "hit it with a hammer" type functions to set to yes when you might be having issues with the from name and address setting. Or setting it to no as and when another plugin is being effected by Welcome Email Editor\'s existence.'
	)
	,'welcome_email_settings_label'=>array(
		'title'=>'Welcome Email Settings'
		, 'type'=>'label'
		, 'style'=>'width: 500px;'
		, 'description'=>'These settings are for the email sent to the new user on their signup.'
	)
	,'settings[user_subject]'=>array(
		'title'=>'User Email Subject'
		, 'type'=>'text'
		, 'style'=>'width: 500px;'
		, 'description'=>'Subject line for the welcome email sent to the user.'
	)
	, 'settings[user_body]'=>array(
		'title'=>'User Email Body'
		, 'type'=>'textarea'
		, 'style'=>'width: 650px; height: 500px;'
		, 'description'=>'Body content for the welcome email sent to the user.'
	)
	, 'settings[we_attachment_url]'=>array(
		'title'=>'Attachment URL'
		, 'type'=>'text'
		, 'style'=>'width: 500px;'
		, 'description'=>'If you want the welcome email to have an attachment then put the URL here. The file MUST be on THIS server in a web servable directory. If you don\'t understand this then use the WordPress media uploader and paste the FULL URL into this box and it will do the rest.'
	)
	, 'settings[header_additional]'=>array(
		'title'=>'Additional Email Headers'
		, 'type'=>'textarea'
		, 'style'=>'width: 550px; height: 200px;'
		, 'description'=>'Optional field for advanced users to add more headers. Dont\'t forget to separate headers with \r\n.'
	)
	, 'settings[header_reply_to]'=>array(
		'title'=>'Reply To Email Address'
		, 'type'=>'text'
		, 'style'=>'width: 500px;'
		, 'description'=>'Optional Header sent to change the reply to address for new user notification.'
	)
	,'welcome_email_admin_settings_label'=>array(
		'title'=>'Welcome Email Admin Notification Settings'
		, 'type'=>'label'
		, 'style'=>'width: 500px;'
		, 'description'=>'These settings are for the email sent to the admin on a new user signup.'
	)
	, 'settings[admin_subject]'=>array(
		'title'=>'Admin Email Subject'
		, 'type'=>'text'
		, 'style'=>'width: 500px;'
		, 'description'=>'Subject Line for the email sent to the admin user(s).'
	)
	, 'settings[admin_body]'=>array(
		'title'=>'Admin Email Body'
		, 'type'=>'textarea'
		, 'style'=>'width: 650px; height: 300px;'
		, 'description'=>'Body content for the email sent to the admin user(s).'
	)
	, 'settings[admin_notify_user_id]'=>array(
		'title'=>'Send Admin Email To...'
		, 'type'=>'text'
		, 'style'=>'width: 500px;'
		, 'description'=>'This allows you to type in the User IDs of the people who you want the admin notification to be sent to. 1 is admin normally but just add more separating by commas (eg: 1,2,3,4).'
	)
	,'forgot_password_settings_label'=>array(
		'title'=>'User Forgot Password Email Settings'
		, 'type'=>'label'
		, 'style'=>'width: 500px;'
		, 'description'=>'These settings are for the email sent to the user when they use the inbuilt Wordpress forgot password functionality.'
	)
	,'settings[password_reminder_subject]'=>array(
		'title'=>'Forgot Password Email Subject'
		, 'type'=>'text'
		, 'style'=>'width: 500px;'
		, 'description'=>'Subject line for the forgot password email that a user can send to themselves using the login screen. Use [blogname] where appropriate.'
	)
	, 'settings[password_reminder_body]'=>array(
		'title'=>'Forgot Password Message'
		, 'type'=>'textarea'
		, 'style'=>'width: 650px; height: 500px;'
		, 'description'=>'Content for the forgot password email that the user can send to themselves via the login screen. Use [blog_name], [site_url], [reset_url] and [user_login] where appropriate. Note to use HTML in this box only if you have set the send mode to HTML. If not text will be used and any HTML ignored.'
	)
	, 'submit'=>array(
		'title'=>''
		, 'type'=>'submit'
		, 'value'=>'Update Settings'
	)
	, 'test_send'=>array(
		'title'=>''
		, 'type'=>'submit'
		, 'value'=>'Test Emails (Save first, will send to current user)'
	)
	);
	
	$page_options = apply_filters('sb_we_settings_fields', $page_options);

	$html .= '<div style="margin-bottom: 10px;">' . __('This page allows you to update the Wordpress welcome email and add headers to make it less likely to fall into spam. You can edit the templates for both the admin and user emails and assign admin members to receive the notifications. Use the following hooks in any of the boxes below: [site_url], [login_url], [user_email], [user_login], [plaintext_password], [blog_name], [admin_email], [user_id], [custom_fields], [first_name], [last_name], [date], [time], [bp_custom_fields] (buddypress custom fields .. admin only)', 'sb_we') . '</div>';
	$html .= sb_we_start_box('Settings');

	$html .= '<form method="POST">';
	$html .= '<table class="widefat">';

	$i = 0;
	foreach ($page_options as $name=>$options) {
		$options['type'] = (isset($options['type']) ? $options['type']:'');
		$options['description'] = (isset($options['description']) ? $options['description']:'');
		$options['class'] = (isset($options['class']) ? $options['class']:false);
		$options['style'] = (isset($options['style']) ? $options['style']:false);
		$options['rows'] = (isset($options['rows']) ? $options['rows']:false);
		$options['cols'] = (isset($options['cols']) ? $options['cols']:false);


		if ($options['type'] == 'submit') {
			$value = $options['value'];
		} else {
			$tmp_name = str_replace('settings[', '', $name);
			$tmp_name = str_replace(']', '', $tmp_name);
			$value = stripslashes(sb_we_post($tmp_name, isset($settings->$tmp_name) ? $settings->$tmp_name : '' ));
		}
		$title = (isset($options['title']) ? $options['title']:false);
		if ($options['type'] == 'label') {
			$title = '<strong>' . $title . '</strong>';
		}

		$html .= '	<tr class="' . ($i%2 ? 'alternate':'') . '">
					<th style="vertical-align: top;">
						' . $title . '
						' . ($options['description'] && $options['type'] != 'label' ? '<div style="font-size: 10px; color: gray;">' . $options['description'] . '</div>':'') . '
					</th>
					<td style="' . ($options['type'] == 'submit' ? 'text-align: right;':'') . '">';



		switch ($options['type']) {
			case 'label':
				$html .= $options['description'];
				break;
			case 'text':
				$html .= sb_we_get_text($name, $value, $options['class'], $options['style']);
				break;
			case 'yes_no':
				$html .= sb_we_get_yes_no($name, $value, $options['class'], $options['style']);
				break;
			case 'textarea':
				$html .= sb_we_get_textarea($name, $value, $options['class'], $options['style'], $options['rows'], $options['cols']);
				break;
			case 'select':
				$html .= sb_we_get_select($name, $options['options'], $value, $options['class'], $options['style']);
				break;
			case 'submit':
				$html .= sb_we_get_submit($name, $value, $options['class'], $options['style']);
				break;
		}

		$html .= '		</td>
				</tr>';

		$i++;
	}

	$html .= '</table>';
	$html .= '</form>';

	$html .= sb_we_end_box();;

	return $html;
}

function sb_we_printr($array=false) {
    if (!$array) {
        $array = $_POST;
    }

    echo '<pre>';
    print_r($array);
    echo '</pre>';
}

function sb_we_get_textarea($name, $value, $class=false, $style=false, $rows=false, $cols=false) {
	$rows = ($rows ? ' rows="' . $rows . '"':'');
	$cols = ($cols ? ' cols="' . $cols . '"':'');
	$style = ($style ? ' style="' . $style . '"':'');
	$class = ($class ? ' class="' . $class . '"':'');

	return '<textarea name="' . $name . '" ' . $rows . $cols . $style . $class . '>' . esc_html($value, true) . '</textarea>';
}

function sb_we_get_select($name, $options, $value, $class=false, $style=false) {
	$style = ($style ? ' style="' . $style . '"':'');
	$class = ($class ? ' class="' . $class . '"':'');

	$html = '<select name="' . $name . '" ' . $class . $style . '>';
	if (is_array($options)) {
		foreach ($options as $val=>$label) {
			$html .= '<option value="' . $val . '" ' . ($val == $value ? 'selected="selected"':'') . '>' . $label . '</option>';
		}
	}
	$html .= '</select>';

	return $html;
}

function sb_we_get_input($name, $type=false, $value=false, $class=false, $style=false, $attributes=false) {
	$style = ($style ? ' style="' . $style . '"':'');
	$class = ($class ? ' class="' . $class . '"':'');
	$value = 'value="' . esc_html($value, true) . '"';
	$type = ($type ? ' type="' . $type . '"':'');

	return '<input name="' . $name . '" ' . $value . $type . $style . $class . ' ' . $attributes . ' />';
}

function sb_we_get_text($name, $value=false, $class=false, $style=false) {
	return sb_we_get_input($name, 'text', $value, $class, $style);
}

function sb_we_get_yes_no($name, $value=false, $class=false, $style=false) {
	$return = '';

	$return .= 'Yes: ' . sb_we_get_input($name, 'radio', 1, $class, $style, ($value == 1 ? 'checked="checked"':'')) . '<br />';
	$return .= 'No: ' . sb_we_get_input($name, 'radio', 0, $class, $style, ($value == 1 ? '':'checked="checked"'));

	return $return;
}

function sb_we_get_submit($name, $value=false, $class=false, $style=false) {
	if (strpos($class, 'button') === false) {
		$class .= 'button';
	}

	return sb_we_get_input($name, 'submit', $value, $class, $style);
}

function sb_we_start_box($title , $return=true){
	$html = '	<div class="postbox" style="margin: 5px 0px; min-width: 0px !important;">
					<h3>' . __($title, 'sb_we') . '</h3>
					<div class="inside">';

	if ($return) {
		return $html;
	} else {
		echo $html;
	}
}

function sb_we_end_box($return=true) {
	$html = '</div>
		</div>';

	if ($return) {
		return $html;
	} else {
		echo $html;
	}
}

function sb_we_admin_page() {
	$admin_page = 'sb_we_settings';
	$func = 'sb_we_admin_loader';
	$access_level = 'manage_options';

	add_submenu_page('options-general.php', SB_WE_PRODUCT_NAME, SB_WE_PRODUCT_NAME, $access_level, $admin_page, $func);

}

function sb_we_admin_loader() {
	global $sb_we_admin_start, $sb_we_admin_end;

	echo $sb_we_admin_start;
	echo sb_we_settings();
	echo $sb_we_admin_end;
}

function sb_we_post($key, $default='', $escape=false, $strip_tags=false) {
	return sb_we_get_superglobal($_POST, $key, $default, $escape, $strip_tags);
}

function sb_we_session($key, $default='', $escape=false, $strip_tags=false) {
	return sb_we_get_superglobal($_SESSION, $key, $default, $escape, $strip_tags);
}

function sb_we_get($key, $default='', $escape=false, $strip_tags=false) {
	return sb_we_get_superglobal($_GET, $key, $default, $escape, $strip_tags);
}

function sb_we_request($key, $default='', $escape=false, $strip_tags=false) {
	return sb_we_get_superglobal($_REQUEST, $key, $default, $escape, $strip_tags);
}

function sb_we_get_superglobal($array, $key, $default='', $escape=false, $strip_tags=false) {

	if (isset($array[$key])) {
		$default = $array[$key];

		if ($escape) {
			$default = mysql_real_escape_string($default);
		}

		if ($strip_tags) {
			$default = strip_tags($default);
		}
	}

	return $default;
}

add_action('plugins_loaded', 'sb_we_loaded');
?>