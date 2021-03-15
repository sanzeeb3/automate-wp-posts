<?php
/**
 * Plugin Name: Automate WP Posts
 * Description: Send post data to online automation tools with Webhooks
 * Version: 1.0.0
 * Author: Sanjeev Aryal
 * Author URI: http://www.sanjeebaryal.com.np
 * Text Domain: automate-wp-posts
 *
 * @package    Automate WP Posts
 * @author     Sanjeev Aryal
 * @since      1.0.0
 * @license    GPL-3.0+
 */

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

add_action( 'admin_menu', 'awp_register_setting_menu' );
add_action( 'admin_init', 'awp_save_settings' );
add_action( 'publish_post', 'awp_publish_post', 10, 2 );

/**
 * Add Automate WP Posts Submenu
 *
 * @since  1.0.0
 */
function awp_register_setting_menu() {
	add_options_page( 'Automate WP Posts', 'Automate WP Posts', 'manage_options', 'automate-wp-posts', 'awp_settings_page' );
}

/**
 * Settings page for Automate WP Posts
 *
 * @since 1.0.0
 */
function awp_settings_page() {

	$webhook_url = get_option( 'awp_webhook_url' );
	?>
		<h2 class="wp-heading-inline"><?php esc_html_e( 'Automate WP Posts Settings', 'automate-wp-posts' ); ?></h2>
		<form method="post">
			<table class="form-table">
					<tr valign="top">
						   <th scope="row"><?php echo esc_html__( 'Webhook URL', 'automate-wp-posts' ); ?></th>
							<td><input style="width:35%" type="url" name="webhook_url" value ="<?php echo esc_url( $webhook_url ); ?>" class="automate-wp-posts-webhook-url" /><br/>
							</td>
					</tr>
					<?php do_action( 'automate_wp_posts_settings' ); ?>
					<?php wp_nonce_field( 'automate_wp_posts_settings', 'automate_wp_posts_settings_nonce' ); ?>

			</table>
				<?php submit_button(); ?>
		</form>
	<?php
}

/**
 * Save Settings.
 *
 * @since 1.0.0
 */
function awp_save_settings() {

	if ( isset( $_POST['automate_wp_posts_settings_nonce'] ) ) {
		if ( ! wp_verify_nonce( $_POST['automate_wp_posts_settings_nonce'], 'automate_wp_posts_settings' )
			) {
			   print 'Nonce Failed!';
			   exit;
		} else {
			$webhook_url = isset( $_POST['webhook_url'] ) ? esc_url_raw( $_POST['webhook_url'] ) : '';
			$message     = esc_html__( 'Done!', 'automate-wp-posts' );
			$class       = 'notice-success';

			update_option( 'awp_webhook_url', $webhook_url );

			if ( filter_var( $webhook_url, FILTER_VALIDATE_URL ) === false ) {
				$message = esc_html__( 'Not a valid webhook URL.' );
				$class   = 'error';
			}

			add_action(
				'admin_notices',
				function() use ( $message, $class ) {
					?>
					<div class="notice <?php echo $class; ?> is-dismissible">
						<p><?php echo $message; ?></p>
					</div>
					<?php
				}
			);
		}
	}
}

/**
 * Send data to webhooks.
 *
 * @param array $data posts data to send to webhooks.
 *
 * @since  1.0.0
 */
function awp_send_data_to_automate( array $data ) {

	$webhook_url = get_option( 'awp_webhook_url' );

	if ( empty( $webhook_url ) ) {
		return;
	}

	$headers = array( 'Accept: application/json', 'Content-Type: application/json' );
	$args    = apply_filters(
		'automate_wp_posts_arguments',
		array(
			'method'  => 'POST',
			'headers' => $headers,
			'body'    => wp_json_encode( $data ),
		)
	);

	$result = wp_remote_post( esc_url_raw( $webhook_url ), $args );

	if ( is_wp_error( $result ) ) {
		error_log( print_r( $result->get_error_message(), true ) );
	}

	do_action( 'automate_wp_posts_data_sent', $result, $webhook_url );
}

/**
 * Prepare data to send to webhooks on WP Post Update.
 *
 * @param  $id Post ID.
 * @param  $post Post object.
 *
 * @since 1.0.0
 */
function awp_publish_post( $id, $post ) {

	$author = $post->post_author;
	$data   = apply_filters(
		'automate_wp_posts_data',
		array(
			esc_html__( 'Post ID', 'automate-wp-posts' )        => $id,
			esc_html__( 'Author Display Name', 'automate-wp-posts' ) => get_the_author_meta( 'display_name', $author ),
			esc_html__( 'Author Email Address', 'automate-wp-posts' ) => get_the_author_meta( 'user_email', $author ),
			esc_html__( 'Post Title', 'automate-wp-posts' )     => $post->post_title,
			esc_html__( 'Post Content', 'automate-wp-posts' )   => $post->post_content,
			esc_html__( 'Post Permalink', 'automate-wp-posts' ) => get_permalink( $id ),
		)
	);

	awp_send_data_to_automate( $data );
}
