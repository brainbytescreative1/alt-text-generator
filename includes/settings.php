<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bbaiat_settings() {
	$saved = get_option( 'bbaiat_settings', array() );
	return wp_parse_args( is_array( $saved ) ? $saved : array(), array(
		'model' => 'gpt-5.4-mini', 'language' => 'English',
	) );
}

function bbaiat_api_key() {
	if ( defined( 'BBAIAT_OPENAI_API_KEY' ) ) {
		return trim( (string) BBAIAT_OPENAI_API_KEY );
	}
	$key = getenv( 'BBAIAT_OPENAI_API_KEY' );
	return $key ? trim( $key ) : (string) get_option( 'bbaiat_api_key', '' );
}

function bbaiat_external_key() {
	return defined( 'BBAIAT_OPENAI_API_KEY' ) || (bool) getenv( 'BBAIAT_OPENAI_API_KEY' );
}

add_action( 'admin_menu', function () {
	add_options_page( 'AI Alt Text', 'AI Alt Text', 'manage_options', 'bb-ai-alt-text', 'bbaiat_settings_page' );
} );

add_filter( 'plugin_action_links_' . plugin_basename( BBAIAT_FILE ), function ( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=bb-ai-alt-text' ) ) . '">' . esc_html__( 'Settings', 'bb-ai-alt-text' ) . '</a>' );
	return $links;
} );

add_action( 'admin_post_bbaiat_settings', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You cannot change these settings.', 'bb-ai-alt-text' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'bbaiat_settings' );
	$settings = bbaiat_settings();
	$model = isset( $_POST['model'] ) && is_string( $_POST['model'] ) ? sanitize_text_field( wp_unslash( $_POST['model'] ) ) : '';
	if ( in_array( $model, array( 'gpt-5.4-mini', 'gpt-5-mini' ), true ) ) {
		$settings['model'] = $model;
	}
	$language = isset( $_POST['language'] ) && is_string( $_POST['language'] ) ? sanitize_text_field( wp_unslash( $_POST['language'] ) ) : '';
	$settings['language'] = $language ? substr( $language, 0, 80 ) : 'English';
	update_option( 'bbaiat_settings', $settings, false );
	if ( ! bbaiat_external_key() ) {
		if ( ! empty( $_POST['remove_key'] ) ) {
			delete_option( 'bbaiat_api_key' );
		} elseif ( isset( $_POST['api_key'] ) && is_string( $_POST['api_key'] ) && '' !== trim( $_POST['api_key'] ) ) {
			$key = trim( wp_unslash( $_POST['api_key'] ) );
			// Keys are ASCII bearer credentials, never returned to the browser.
			if ( preg_match( '/\Ask-[A-Za-z0-9_-]{10,500}\z/', $key ) ) {
				update_option( 'bbaiat_api_key', $key, false );
			} else {
				wp_safe_redirect( admin_url( 'options-general.php?page=bb-ai-alt-text&invalid_key=1' ) );
				exit;
			}
		}
	}
	wp_safe_redirect( admin_url( 'options-general.php?page=bb-ai-alt-text&saved=1' ) );
	exit;
} );

function bbaiat_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$settings = bbaiat_settings();
	?>
	<div class="wrap">
		<h1>AI Alt Text <small style="font-size:13px;font-weight:400">Prototype 0.1.0</small></h1>
		<?php if ( isset( $_GET['saved'] ) ) : ?>
			<div class="notice notice-success"><p>Settings saved.</p></div>
		<?php endif; ?>
		<?php if ( isset( $_GET['invalid_key'] ) ) : ?>
			<div class="notice notice-error"><p>The API key format was not recognized. Paste the full key from OpenAI.</p></div>
		<?php endif; ?>
		<p>Open an image in <a href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>">Media Library</a>, or select one while adding an image to a page. Click <strong>Generate alt text</strong>, review the suggestion, then click <strong>Use alt text</strong>.</p>
		<p>Each generation sends a resized copy of that image to OpenAI and uses your paid API account. Nothing is sent automatically on upload. Review the result for accuracy and page context; decorative images may need empty alt text.</p>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="bbaiat_settings">
			<?php wp_nonce_field( 'bbaiat_settings' ); ?>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="bbaiat-key">OpenAI API key</label></th><td>
				<?php if ( bbaiat_external_key() ) : ?>
					<p>A key is configured in the server environment or wp-config.php.</p>
				<?php else : ?>
					<input id="bbaiat-key" name="api_key" type="password" class="regular-text" autocomplete="new-password" value="" placeholder="<?php echo bbaiat_api_key() ? 'Key saved; leave blank to keep it' : 'sk-...'; ?>">
					<p class="description">The key is stored in your WordPress database and is never displayed again. Prefer server configuration for production. <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener noreferrer">Create an API key</a>.</p>
					<?php if ( bbaiat_api_key() ) : ?><p><label><input type="checkbox" name="remove_key" value="1"> Remove saved key</label></p><?php endif; ?>
				<?php endif; ?>
				</td></tr>
				<tr><th scope="row"><label for="bbaiat-model">Model</label></th><td>
					<select id="bbaiat-model" name="model">
					<?php foreach ( array( 'gpt-5.4-mini', 'gpt-5-mini' ) as $model ) : ?>
						<option value="<?php echo esc_attr( $model ); ?>" <?php selected( $settings['model'], $model ); ?>><?php echo esc_html( $model ); ?></option>
					<?php endforeach; ?>
					</select><p class="description">Your API project must have access to the selected model.</p>
				</td></tr>
				<tr><th scope="row"><label for="bbaiat-language">Alt text language</label></th><td><input id="bbaiat-language" name="language" class="regular-text" maxlength="80" value="<?php echo esc_attr( $settings['language'] ); ?>"></td></tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<h2>What this prototype supports</h2>
		<p>One image at a time, JPEG, PNG, and WebP stored in this site's uploads directory. Your host needs a working WordPress image editor (GD or Imagick). Cloud-only media, SVG, GIF, and AVIF are not supported in this version.</p>
		<p>Only the Media Library alt text changes. Filenames, image titles, captions, and descriptions stay as they are. Existing page blocks may contain their own alt text; reselect the image or update that block separately.</p>
		<p>Users need permission to upload files and edit the image to generate suggestions. Administrators control the API settings.</p>
	</div>
	<?php
}
