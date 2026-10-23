<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bbaiat_can_edit( $id ) {
	return current_user_can( 'upload_files' ) && current_user_can( 'edit_post', $id );
}

function bbaiat_enqueue() {
	if ( ! is_admin() || ! current_user_can( 'upload_files' ) || wp_script_is( 'bbaiat-admin', 'enqueued' ) ) {
		return;
	}
	$url = plugin_dir_url( BBAIAT_FILE );
	wp_enqueue_style( 'bbaiat-admin', $url . 'assets/admin.css', array(), BBAIAT_VERSION );
	wp_enqueue_script( 'bbaiat-admin', $url . 'assets/admin.js', array( 'jquery' ), BBAIAT_VERSION, true );
	wp_localize_script( 'bbaiat-admin', 'bbaiatAdmin', array(
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce' => wp_create_nonce( 'bbaiat_image' ),
		'i18n' => array(
			'generating' => __( 'Generating alt text...', 'bb-ai-alt-text' ),
			'review' => __( 'Review the suggestion, then choose Use alt text.', 'bb-ai-alt-text' ),
			'saving' => __( 'Saving alt text...', 'bb-ai-alt-text' ),
			'saved' => __( 'Alt text saved.', 'bb-ai-alt-text' ),
			'error' => __( 'The request failed. Please try again.', 'bb-ai-alt-text' ),
			'timeout' => __( 'The request timed out. Wait a moment before trying again.', 'bb-ai-alt-text' ),
			'localChange' => __( 'The alt field changed while you were reviewing. Generate again to avoid replacing newer edits.', 'bb-ai-alt-text' ),
		),
	) );
}
add_action( 'wp_enqueue_media', 'bbaiat_enqueue' );
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( 'upload.php' === $hook || ( 'post.php' === $hook && 'attachment' === get_post_type() ) ) {
		bbaiat_enqueue();
	}
} );

function bbaiat_widget( $id ) {
	ob_start();
	?>
	<div class="bbaiat-widget" data-id="<?php echo (int) $id; ?>">
		<button type="button" class="button bbaiat-generate"><?php esc_html_e( 'Generate alt text', 'bb-ai-alt-text' ); ?></button>
		<p class="bbaiat-status" role="status" aria-live="polite"></p>
		<div class="bbaiat-review" hidden>
			<label class="bbaiat-label"><?php esc_html_e( 'Suggested alt text', 'bb-ai-alt-text' ); ?>
				<textarea class="bbaiat-suggestion" rows="3" maxlength="1000"></textarea>
			</label>
			<p class="bbaiat-hint"><?php esc_html_e( 'Check accuracy and page context before saving. This replaces the image\'s Media Library alt text.', 'bb-ai-alt-text' ); ?></p>
			<button type="button" class="button button-primary bbaiat-use"><?php esc_html_e( 'Use alt text', 'bb-ai-alt-text' ); ?></button>
			<button type="button" class="button-link bbaiat-dismiss"><?php esc_html_e( 'Dismiss', 'bb-ai-alt-text' ); ?></button>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

add_filter( 'attachment_fields_to_edit', function ( $fields, $post ) {
	if ( wp_attachment_is_image( $post->ID ) && bbaiat_can_edit( $post->ID ) ) {
		$fields['bbaiat_generate'] = array(
			'label' => __( 'AI alt text', 'bb-ai-alt-text' ),
			'input' => 'html', 'html' => bbaiat_widget( $post->ID ),
		);
	}
	return $fields;
}, 20, 2 );

add_filter( 'manage_media_columns', function ( $columns ) {
	$columns['bbaiat_alt'] = __( 'AI alt text', 'bb-ai-alt-text' );
	return $columns;
} );
add_action( 'manage_media_custom_column', function ( $column, $id ) {
	if ( 'bbaiat_alt' !== $column || ! wp_attachment_is_image( $id ) ) {
		return;
	}
	$alt = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
	echo '<p class="bbaiat-current">' . ( '' !== $alt ? esc_html( $alt ) : '<em>' . esc_html__( 'No alt text', 'bb-ai-alt-text' ) . '</em>' ) . '</p>';
	if ( bbaiat_can_edit( $id ) ) {
		echo bbaiat_widget( $id ); // All dynamic content is escaped in bbaiat_widget().
	}
}, 10, 2 );

/** Validate both endpoints independently; a nonce is not an authorization check. */
function bbaiat_request_id() {
	if ( ! check_ajax_referer( 'bbaiat_image', 'nonce', false ) ) {
		wp_send_json_error( array( 'message' => 'Your session expired. Reload the page and try again.' ), 403 );
	}
	$id = isset( $_POST['id'] ) && is_scalar( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	if ( ! $id || 'attachment' !== get_post_type( $id ) || ! wp_attachment_is_image( $id ) ) {
		wp_send_json_error( array( 'message' => 'Choose a valid image attachment.' ), 400 );
	}
	if ( ! bbaiat_can_edit( $id ) ) {
		wp_send_json_error( array( 'message' => 'You do not have permission to edit this image.' ), 403 );
	}
	return $id;
}

add_action( 'wp_ajax_bbaiat_generate', function () {
	$id = bbaiat_request_id();
	// An atomic option prevents duplicate paid requests for the same attachment.
	$lock = 'bbaiat_lock_' . $id;
	$expires = (int) get_option( $lock, 0 );
	if ( $expires && $expires < time() ) {
		delete_option( $lock );
	}
	if ( ! add_option( $lock, time() + 90, '', false ) ) {
		wp_send_json_error( array( 'message' => 'This image is already being analyzed. Please wait a moment.' ), 409 );
	}
	$original = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
	try {
		$result = bbaiat_generate( $id );
	} finally {
		delete_option( $lock );
	}
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
	}
	wp_send_json_success( array( 'suggestion' => $result, 'original' => $original ) );
} );

add_action( 'wp_ajax_bbaiat_save', function () {
	$id = bbaiat_request_id();
	if ( ! isset( $_POST['alt'], $_POST['original'] ) || ! is_string( $_POST['alt'] ) || ! is_string( $_POST['original'] ) ) {
		wp_send_json_error( array( 'message' => 'The alt text or original value is missing.' ), 400 );
	}
	$alt = sanitize_text_field( wp_unslash( $_POST['alt'] ) );
	if ( strlen( $alt ) > 2000 ) {
		wp_send_json_error( array( 'message' => 'Please shorten the alt text before saving.' ), 400 );
	}
	$original = wp_unslash( $_POST['original'] );
	$current = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
	if ( $current !== $original ) {
		wp_send_json_error( array( 'message' => 'The saved alt text changed while you were reviewing. Reload the image and generate again to preserve newer edits.' ), 409 );
	}
	// Use the previous value as an additional guard when the field is nonempty.
	if ( $current !== $alt ) {
		update_post_meta( $id, '_wp_attachment_image_alt', wp_slash( $alt ), $current );
	}
	if ( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) !== $alt ) {
		wp_send_json_error( array( 'message' => 'The alt text could not be saved. Reload the image and try again.' ), 409 );
	}
	wp_send_json_success( array( 'alt' => $alt ) );
} );
