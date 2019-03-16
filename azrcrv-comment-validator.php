<?php
/**
 * ------------------------------------------------------------------------------
 * Plugin Name: Comment Validator
 * Description: Checks comment to ensure they are longer than the minimum, shorter than the maximum and also allows comments to be forced into moderation based on length.
 * Version: 1.0.0
 * Author: azurecurve
 * Author URI: https://development.azurecurve.co.uk/classicpress-plugins/
 * Plugin URI: https://development.azurecurve.co.uk/classicpress-plugins/comment-validator
 * Text Domain: comment-validator
 * Domain Path: /languages
 * ------------------------------------------------------------------------------
 * This is free software released under the terms of the General Public License,
 * version 2, or later. It is distributed WITHOUT ANY WARRANTY; without even the
 * implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. Full
 * text of the license is available at https://www.gnu.org/licenses/gpl-2.0.html.
 * ------------------------------------------------------------------------------
 */

// include plugin menu
require_once(dirname( __FILE__).'/pluginmenu/menu.php');

// Prevent direct access.
if (!defined('ABSPATH')){
	die();
}

/**
 * Setup actions, filters and shortcodes.
 *
 * @since 1.0.0
 *
 */
// add actions
add_action('admin_menu', 'azrcrv_cv_create_admin_menu');
add_action('admin_post_azrcrv_cv_save_options', 'azrcrv_cv_save_options');
add_action('network_admin_menu', 'azrcrv_cv_create_network_admin_menu');
add_action('network_admin_edit_azrcrv_cv_save_network_options', 'azrcrv_cv_save_network_options');

// add filters
add_filter('plugin_action_links', 'azrcrv_cv_add_plugin_action_link', 10, 2);
add_filter('preprocess_comment' , 'azrcrv_cv_validate_comment', 20);

// register activation hook
register_activation_hook(__FILE__, 'azrcrv_cv_set_default_options');

/**
 * Set default options for plugin.
 *
 * @since 1.0.0
 *
 */
function azrcrv_cv_set_default_options($networkwide){
	
	$new_options = array(
				'min_length' => 10,
				'max_length' => 500,
				'mod_length' => 250,
				'prevent_unreg_using_reg_name' => 1,
				'use_network' => 1
			);
	
	// set defaults for multi-site
	if (function_exists('is_multisite') && is_multisite()){
		// check if it is a network activation - if so, run the activation function for each blog id
		if ($networkwide){
			global $wpdb;

			$blog_ids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
			$original_blog_id = get_current_blog_id();

			foreach ($blog_ids as $blog_id){
				switch_to_blog($blog_id);

				if (get_option('azrcrv-cv') === false){
					if (get_option('azc_cv_options') === false){
						add_option('azrcrv-cv', $new_options);
					}else{
						add_option('azrcrv-cv', get_option('azc_cv_options'));
					}
				}
			}

			switch_to_blog($original_blog_id);
		}else{
			if (get_option('azrcrv-cv') === false){
				if (get_option('azc_cv_options') === false){
					add_option('azrcrv-cv', $new_options);
				}else{
					add_option('azrcrv-cv', get_option('azc_cv_options'));
				}
			}
		}
		if (get_site_option('azrcrv-cv') === false){
				if (get_option('azc_cv_options') === false){
					add_option('azrcrv-cv', $new_options);
				}else{
					add_option('azrcrv-cv', get_option('azc_cv_options'));
				}
		}
	}
	//set defaults for single site
	else{
		if (get_option('azrcrv-cv') === false){
				if (get_option('azc_cv_options') === false){
					add_option('azrcrv-cv', $new_options);
				}else{
					add_option('azrcrv-cv', get_option('azc_cv_options'));
				}
		}
	}
}

/**
 * Add Comment Validator action link on plugins page.
 *
 * @since 1.0.0
 *
 */
function azrcrv_cv_add_plugin_action_link($links, $file){
	static $this_plugin;

	if (!$this_plugin){
		$this_plugin = plugin_basename(__FILE__);
	}

	if ($file == $this_plugin){
		$settings_link = '<a href="'.get_bloginfo('wpurl').'/wp-admin/admin.php?page=azrcrv-cv">'.esc_html__('Settings' ,'comment-validator').'</a>';
		array_unshift($links, $settings_link);
	}

	return $links;
}

/**
 * Add to menu.
 *
 * @since 1.0.0
 *
 */
function azrcrv_cv_create_admin_menu(){
	//global $admin_page_hooks;
	
	add_submenu_page("azrcrv-plugin-menu"
						,esc_html__("Comment Validator Settings", "comment-validator")
						,esc_html__("Comment Validator", "comment-validator")
						,'manage_options'
						,'azrcrv-cv'
						,'azrcrv_cv_display_options');
}

/**
 * Display Settings page.
 *
 * @since 1.0.0
 *
 */
function azrcrv_cv_display_options(){
	if (!current_user_can('manage_options')){
        wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'comment-validator'));
    }
	
	// Retrieve plugin configuration options from database
	$options = get_option('azrcrv-cv');
	?>
	<div id="azrcrv-cv-general" class="wrap">
		<fieldset>
			<h2><?php echo esc_html(get_admin_page_title()); ?></h2>
			
			<?php if(isset($_GET['settings-updated'])){ ?>
				<div class="notice notice-success is-dismissible">
					<p><strong><?php esc_html_e('Settings have been saved.', 'comment-validator'); ?></strong></p>
				</div>
			<?php } ?>
			
			<form method="post" action="admin-post.php">
				
				<input type="hidden" name="action" value="azrcrv_cv_save_options" />
				<input name="page_options" type="hidden" value="min_length,max_length,mod_length,use_network" />
				
				<!-- Adding security through hidden referrer field -->
				<?php wp_nonce_field('azrcrv-cv', 'azrcrv-cv-nonce'); ?>
				<table class="form-table">
				
				<?php
				if (!function_exists('is_multisite') && is_multisite()){
				?>
				<tr><th scope="row"><?php esc_html_e('Use Network Settings', 'comment-validator'); ?></th><td>
					<fieldset><legend class="screen-reader-text"><span><?php esc_html_e("Use Network Settings", "comment-validator"); ?></span></legend>
					<label for="use_network"><input name="use_network" type="checkbox" id="use_network" value="1" <?php checked('1', $options['use_network']); ?> /><?php esc_html_e('Settings below will be ignored in preference of network settings.', 'comment validator'); ?></label>
					</fieldset>
				</td></tr>
				<?php
				}
				?>
				
				<tr><th scope="row"><?php esc_html_e('Prevent unreg user using name of registered user?', 'comment-validator'); ?></th><td>
					<fieldset><legend class="screen-reader-text"><span><?php esc_html_e("Prevent unreg user using name of registered user", "comment-validator"); ?></span></legend>
					<label for="prevent_unreg_using_reg_name"><input name="prevent_unreg_using_reg_name" type="checkbox" id="prevent_unreg_using_reg_name" value="1" <?php checked('1', $options['prevent_unreg_using_reg_name']); ?> /><?php esc_html_e('Prevents unregistered user using name of registered user..', 'comment validator'); ?></label>
					</fieldset>
				</td></tr>
				
				<tr><th scope="row"><label for="min_length"><?php esc_html_e('Minimum Length', 'comment-validator'); ?></label></th><td>
					<input type="text" name="min_length" value="<?php echo esc_html(stripslashes($options['min_length'])); ?>" class="small-text" />
					<p class="description"><?php esc_html_e('Minimum comment length; set to 0 for no minimum.', 'comment-validator'); ?></p>
				</td></tr>
				
				<tr><th scope="row"><label for="max_length"><?php esc_html_e('Maximum Length', 'comment-validator'); ?></label></th><td>
					<input type="text" name="max_length" value="<?php echo esc_html(stripslashes($options['max_length'])); ?>" class="small-text" />
					<p class="description"><?php esc_html_e('Maximum comment length; set to 0 for no maximum.', 'comment-validator'); ?></p>
				</td></tr>
				
				<tr><th scope="row"><label for="mod_length"><?php esc_html_e('Moderation Length', 'comment-validator'); ?></label></th><td>
					<input type="text" name="mod_length" value="<?php echo esc_html(stripslashes($options['mod_length'])); ?>" class="small-text" />
					<p class="description"><?php esc_html_e('Moderation comment length; set to 0 for no moderation.', 'comment-validator'); ?></p>
				</td></tr>
				
				</table>
				<input type="submit" value="Save Changes" class="button-primary"/>
			</form>
		</fieldset>
	</div>
	<?php
}

/**
 * Save settings.
 *
 * @since 1.0.0
 *
 */
function azrcrv_cv_save_options(){
	// Check that user has proper security level
	echo 'here';
	if (!current_user_can('manage_options')){
		wp_die(esc_html__('You do not have permissions to perform this action', 'comment-validator'));
	}
	// Check that nonce field created in configuration form is present
	if (! empty($_POST) && check_admin_referer('azrcrv-cv', 'azrcrv-cv-nonce')){
	
		// Retrieve original plugin options array
		$options = get_option('azrcrv-cv');
		
		$option_name = 'prevent_unreg_using_reg_name';
		if (isset($_POST[$option_name])){
			$options[$option_name] = 1;
		}else{
			$options[$option_name] = 0;
		}
		
		$option_name = 'min_length';
		if (isset($_POST[$option_name])){
			$options[$option_name] = sanitize_text_field(intval($_POST[$option_name]));
		}
		
		$option_name = 'max_length';
		if (isset($_POST[$option_name])){
			$options[$option_name] = sanitize_text_field(intval($_POST[$option_name]));
		}
		
		$option_name = 'mod_length';
		if (isset($_POST[$option_name])){
			$options[$option_name] = sanitize_text_field(intval($_POST[$option_name]));
		}
		
		$option_name = 'use_network';
		if (function_exists('is_multisite') && is_multisite()){
			if (isset($_POST[$option_name])){
				$options[$option_name] = 1;
			}else{
				$options[$option_name] = 0;
			}
		}else{
			$options[$option_name] = 0;
		}
		
		// Store updated options array to database
		update_option('azrcrv-cv', $options);
		
		// Redirect the page to the configuration form that was processed
		wp_redirect(add_query_arg('page', 'azrcrv-cv&settings-updated', admin_url('admin.php')));
		exit;
	}
}

/**
 * Add to Network menu.
 *
 * @since 1.0.0
 *
 */
function azrcrv_cv_create_network_admin_menu(){
	if (function_exists('is_multisite') && is_multisite()){
		add_submenu_page(
					'settings.php'
					,esc_html__("Comment Validator Settings", "comment-validator")
					,esc_html__("Comment Validator", "comment-validator")
					,'manage_network_options'
					,'azrcrv-cv'
					,'azrcrv_cv_network_settings'
					);
	}
}

/**
 * Display network settings.
 *
 * @since 1.0.0
 *
 */
function azrcrv_cv_network_settings(){
	if(!current_user_can('manage_network_options')) wp_die(esc_html__('You do not have permissions to perform this action', 'azurecurve-comment-validator'));
	$options = get_site_option('azrcrv-cv');

	?>
	<div id="azrcrv-cv-general" class="wrap">
		<fieldset>
			<h2><?php echo esc_html(get_admin_page_title()); ?></h2>
			<form method="post" action="admin-post.php">
				<input type="hidden" name="action" value="azrcrv_cv_save_network_options" />
				<input name="page_options" type="hidden" value="smallest, largest, number" />
				
				<!-- Adding security through hidden referrer field -->
				<?php wp_nonce_field('azrcrv-cv', 'azrcrv-cv-nonce'); ?>
				<table class="form-table">
				
				<tr><th scope="row"><?php esc_html_e('Prevent unreg user using name of registered user?', 'comment-validator'); ?></th><td>
					<fieldset><legend class="screen-reader-text"><span><?php esc_html_e("Prevent unreg user using name of registered user", "comment-validator"); ?></span></legend>
					<label for="prevent_unreg_using_reg_name"><input name="prevent_unreg_using_reg_name" type="checkbox" id="prevent_unreg_using_reg_name" value="1" <?php checked('1', $options['prevent_unreg_using_reg_name']); ?> /><?php esc_html_e('Prevents unregistered user using name of registered user..', 'comment validator'); ?></label>
					</fieldset>
				</td></tr>
				
				<tr><th scope="row"><label for="min_length"><?php esc_html_e('Minimum Length', 'azurecurve-comment-validator'); ?></label></th><td>
					<input type="text" name="min_length" value="<?php echo esc_html(stripslashes($options['min_length'])); ?>" class="small-text" />
					<p class="description"><?php esc_html_e('Minimum comment length; set to 0 for no minimum', 'azurecurve-comment-validator'); ?></p>
				</td></tr>
				
				<tr><th scope="row"><label for="max_length"><?php esc_html_e('Maximum Length', 'azurecurve-comment-validator'); ?></label></th><td>
					<input type="text" name="max_length" value="<?php echo esc_html(stripslashes($options['max_length'])); ?>" class="small-text" />
					<p class="description"><?php esc_html_e('Maximum comment length; set to 0 for no maximum', 'azurecurve-comment-validator'); ?></p>
				</td></tr>
				
				<tr><th scope="row"><label for="mod_length"><?php esc_html_e('Moderation Length', 'azurecurve-comment-validator'); ?></label></th><td>
					<input type="text" name="mod_length" value="<?php echo esc_html(stripslashes($options['mod_length'])); ?>" class="small-text" />
					<p class="description"><?php esc_html_e('Moderation comment length; set to 0 for no moderation', 'azurecurve-comment-validator'); ?></p>
				</td></tr>
				
				</table>
				<input type="submit" value="Save Changes" class="button-primary"/>
			</form>
		</fieldset>
	</div>
	<?php
}

/**
 * Save network settings.
 *
 * @since 1.0.0
 *
 */
function azrcrv_cv_save_network_options(){     
	if(!current_user_can('manage_network_options')) wp_die(esc_html__('You do not have permissions to perform this action', 'azurecurve-comment-validator'));
	if (! empty($_POST) && check_admin_referer('azrcrv-cv', 'azrcrv-cv-nonce')){
		// Retrieve original plugin options array
		$options = get_site_option('azrcrv-cv');
		
		$option_name = 'prevent_unreg_using_reg_name';
		if (isset($_POST[$option_name])){
			$options[$option_name] = 1;
		}else{
			$options[$option_name] = 0;
		}
		
		$option_name = 'min_length';
		if (isset($_POST[$option_name])){
			$options[$option_name] = sanitize_text_field(intval($_POST[$option_name]));
		}
		
		$option_name = 'max_length';
		if (isset($_POST[$option_name])){
			$options[$option_name] = sanitize_text_field(intval($_POST[$option_name]));
		}
		
		$option_name = 'mod_length';
		if (isset($_POST[$option_name])){
			$options[$option_name] = sanitize_text_field(intval($_POST[$option_name]));
		}
		
		update_site_option('azrcrv-cv', $options);

		wp_redirect(network_admin_url('settings.php?page=azrcrv-cv&settings-updated'));
		exit;  
	}
}

/**
 * Validate Comment.
 *
 * @since 1.0.0
 *
 */
function azrcrv_cv_validate_comment($commentdata){
	$options = get_option('azrcrv-cv');
	if ($options['use_network'] == 1){
		$options = get_site_option('azrcrv-cv');
	}
	
	if ($options['prevent_unreg_using_reg_name'] == 1){
		if (!is_user_logged_in()){
			global $wpdb;
			
			$sql =  "select COUNT(ID) FROM $wpdb->users where user_login = '%s' OR user_nicename = %s OR display_name = %s";
			
			$is_used = $wpdb->get_var($wpdb->prepare($sql, $commentdata['comment_author'], $commentdata['comment_author'], $commentdata['comment_author']));
			
			if ($is_used > 0){
				$error = new WP_Error('not_found', '<p><p>'.__('This name is reserved.' , 'comment-validator').'</p></p><p><a href="javascript:history.back()">&laquo; '.__('Back', 'comment-validator').'</a></p>', array('response' => '200'));
				if(is_wp_error($error)){
					wp_die($error, '', $error->get_error_data());
				}
			}
		}
	}
	
	if (strlen($commentdata['comment_content']) < $options['min_length']){
		$error = new WP_Error('not_found', __('This comment is shorter than the minimum allowed size.' , 'comment-validator'), array('response' => '200'));
		if(is_wp_error($error)){
			wp_die($error, '', $error->get_error_data());
		}
	}elseif (strlen($commentdata['comment_content']) > $options['max_length'] && $options['max_length'] > 0){
		$error = new WP_Error('not_found', __('This comment is longer than the maximum allowed size.', 'comment-validator'), array('response' => '200'));
		if(is_wp_error($error)){
			wp_die($error, '', $error->get_error_data());
		}
	}elseif (strlen($commentdata['comment_content']) > $options['mod_length'] && $options['mod_length'] > 0){
		add_filter('pre_comment_approved', 'azrcrv_cv_return_validated_comment', '99', 2);
	}
    return $commentdata;
}

/**
 * Return Validated Comment.
 *
 * @since 1.0.0
 *
 */
function azrcrv_cv_return_validated_comment($approved, $commentdata){
	if ('spam' != $approved) return 0;
	else return $approved;
}

?>