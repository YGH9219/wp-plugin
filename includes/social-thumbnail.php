<?php
/**
 * Branded social and Google Article thumbnails generated from featured images.
 */

defined( 'ABSPATH' ) || exit;

define( 'PERSONAL_CTA_SOCIAL_THUMBNAIL_OPTION', 'personal_cta_social_thumbnail' );
define( 'PERSONAL_CTA_SOCIAL_THUMBNAIL_META', '_personal_cta_social_thumbnail' );

/** Returns the saved settings with safe defaults. */
function personal_cta_social_thumbnail_settings() {
	$settings = get_option( PERSONAL_CTA_SOCIAL_THUMBNAIL_OPTION, array() );

	return wp_parse_args(
		is_array( $settings ) ? $settings : array(),
		array(
			'enabled'       => true,
			'logo_id'       => 0,
			'logo_position' => 'top-right',
			'border_color'  => '#2563eb',
		)
	);
}

/** Sanitizes the social-thumbnail settings. */
function personal_cta_social_thumbnail_sanitize_settings( $input ) {
	$input    = is_array( $input ) ? $input : array();
	$logo_id  = isset( $input['logo_id'] ) ? absint( $input['logo_id'] ) : 0;
	$position = isset( $input['logo_position'] ) ? sanitize_key( $input['logo_position'] ) : 'top-right';
	$color    = isset( $input['border_color'] ) ? sanitize_hex_color( $input['border_color'] ) : '';

	if ( $logo_id && ! wp_attachment_is_image( $logo_id ) ) {
		$logo_id = 0;
	}

	return array(
		'enabled'       => ! empty( $input['enabled'] ),
		'logo_id'       => $logo_id,
		'logo_position' => in_array( $position, array( 'top-left', 'top-right' ), true ) ? $position : 'top-right',
		'border_color'  => $color ? $color : '#2563eb',
	);
}

/** Registers the option. */
function personal_cta_social_thumbnail_register_settings() {
	register_setting(
		'personal_cta_social_thumbnail',
		PERSONAL_CTA_SOCIAL_THUMBNAIL_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'personal_cta_social_thumbnail_sanitize_settings',
			'default'           => array(),
		)
	);
}
add_action( 'admin_init', 'personal_cta_social_thumbnail_register_settings' );

/** Adds the settings page. */
function personal_cta_social_thumbnail_add_settings_page() {
	add_options_page(
		'소셜 썸네일',
		'소셜 썸네일',
		'manage_options',
		'personal-cta-social-thumbnail',
		'personal_cta_social_thumbnail_render_settings_page'
	);
}
add_action( 'admin_menu', 'personal_cta_social_thumbnail_add_settings_page' );

/** Loads WordPress's native media picker only on this settings page. */
function personal_cta_social_thumbnail_admin_assets( $hook ) {
	if ( 'settings_page_personal-cta-social-thumbnail' === $hook ) {
		wp_enqueue_media();
	}
}
add_action( 'admin_enqueue_scripts', 'personal_cta_social_thumbnail_admin_assets' );

/** Returns the custom logo, falling back to the theme's Site Logo. */
function personal_cta_social_thumbnail_logo_id( $settings = null ) {
	$settings = is_array( $settings ) ? $settings : personal_cta_social_thumbnail_settings();
	$logo_id  = isset( $settings['logo_id'] ) ? absint( $settings['logo_id'] ) : 0;

	if ( $logo_id && wp_attachment_is_image( $logo_id ) ) {
		return $logo_id;
	}

	$site_logo = absint( get_theme_mod( 'custom_logo' ) );

	return $site_logo && wp_attachment_is_image( $site_logo ) ? $site_logo : 0;
}

/** Renders the settings page. */
function personal_cta_social_thumbnail_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings = personal_cta_social_thumbnail_settings();
	$logo_id  = personal_cta_social_thumbnail_logo_id( $settings );
	$custom   = ! empty( $settings['logo_id'] );
	?>
	<div class="wrap">
		<h1>소셜 썸네일</h1>
		<?php settings_errors( 'personal_cta_social_thumbnail' ); ?>
		<?php if ( ! class_exists( 'Imagick' ) && ! function_exists( 'imagecreatetruecolor' ) ) : ?>
			<div class="notice notice-error"><p>서버에 GD 또는 Imagick 이미지 확장이 없어 썸네일을 만들 수 없습니다.</p></div>
		<?php endif; ?>
		<p>대표이미지를 원본 그대로 보존하면서 소셜용 1200×630과 Google용 16:9·4:3·1:1 JPG를 만듭니다.</p>
		<form action="options.php" method="post">
			<?php settings_fields( 'personal_cta_social_thumbnail' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">자동 생성</th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( PERSONAL_CTA_SOCIAL_THUMBNAIL_OPTION ); ?>[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>>
							글의 대표이미지를 저장할 때 소셜 썸네일 만들기
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">썸네일 로고</th>
					<td>
						<input id="pct-social-logo-id" type="hidden" name="<?php echo esc_attr( PERSONAL_CTA_SOCIAL_THUMBNAIL_OPTION ); ?>[logo_id]" value="<?php echo esc_attr( (int) $settings['logo_id'] ); ?>">
						<div id="pct-social-logo-preview" style="margin-bottom:10px;min-height:40px">
							<?php
							if ( $logo_id ) {
								echo wp_get_attachment_image( $logo_id, 'medium', false, array( 'style' => 'display:block;max-width:320px;max-height:80px;width:auto;height:auto' ) );
							} else {
								echo '<em>사이트 로고가 설정되지 않았습니다.</em>';
							}
							?>
						</div>
						<button type="button" class="button" id="pct-social-select-logo">다른 로고 선택</button>
						<button type="button" class="button" id="pct-social-use-site-logo">사이트 로고 사용</button>
						<p class="description"><?php echo esc_html( $custom ? '현재 별도 로고를 사용합니다. 투명 PNG를 권장합니다.' : '기본값: 사이트 아이덴티티에 등록된 로고. 투명 PNG를 권장합니다.' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="pct-social-logo-position">로고 위치</label></th>
					<td>
						<select id="pct-social-logo-position" name="<?php echo esc_attr( PERSONAL_CTA_SOCIAL_THUMBNAIL_OPTION ); ?>[logo_position]">
							<option value="top-right" <?php selected( 'top-right', $settings['logo_position'] ); ?>>우측 상단</option>
							<option value="top-left" <?php selected( 'top-left', $settings['logo_position'] ); ?>>좌측 상단</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="pct-social-border-color">테두리 색상</label></th>
					<td><input id="pct-social-border-color" type="color" name="<?php echo esc_attr( PERSONAL_CTA_SOCIAL_THUMBNAIL_OPTION ); ?>[border_color]" value="<?php echo esc_attr( $settings['border_color'] ); ?>"></td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<p class="description">기존 글은 대표이미지를 다시 선택하거나 글을 업데이트하면 생성됩니다. Rank Math 소셜 이미지를 직접 지정한 경우에는 직접 지정한 이미지를 우선하고, Article 스키마에는 Google용 세 비율을 연결합니다.</p>
	</div>
	<script>
	jQuery(function ($) {
		var frame;
		$('#pct-social-select-logo').on('click', function () {
			if (!frame) {
				frame = wp.media({ title: '썸네일 로고 선택', button: { text: '이 로고 사용' }, library: { type: 'image' }, multiple: false });
				frame.on('select', function () {
					var image = frame.state().get('selection').first().toJSON();
					$('#pct-social-logo-id').val(image.id);
					$('#pct-social-logo-preview').html($('<img>', { src: image.sizes && image.sizes.medium ? image.sizes.medium.url : image.url, css: { display: 'block', maxWidth: '320px', maxHeight: '80px', width: 'auto', height: 'auto' } }));
				});
			}
			frame.open();
		});
		$('#pct-social-use-site-logo').on('click', function () {
			$('#pct-social-logo-id').val('0');
			$('#pct-social-logo-preview').html('<em>저장하면 사이트 로고를 사용합니다.</em>');
		});
	});
	</script>
	<?php
}

/** Calculates contain dimensions without cropping. */
function personal_cta_social_thumbnail_contain( $source_width, $source_height, $maximum_width, $maximum_height ) {
	$source_width  = max( 1, (int) $source_width );
	$source_height = max( 1, (int) $source_height );
	$scale         = min( $maximum_width / $source_width, $maximum_height / $source_height );

	return array(
		max( 1, (int) round( $source_width * $scale ) ),
		max( 1, (int) round( $source_height * $scale ) ),
	);
}

/** Returns the four output ratios used by social cards and Google Article images. */
function personal_cta_social_thumbnail_variants() {
	return array(
		'social' => array( 1200, 630 ),
		'16x9'   => array( 1200, 675 ),
		'4x3'    => array( 1200, 900 ),
		'1x1'    => array( 1200, 1200 ),
	);
}

/** Converts a six-digit hex color to RGB. */
function personal_cta_social_thumbnail_rgb( $hex ) {
	$hex = sanitize_hex_color( $hex );
	$hex = $hex ? ltrim( $hex, '#' ) : '2563eb';

	return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
}

/** Loads one raster image through GD. */
function personal_cta_social_thumbnail_gd_load( $path ) {
	if ( ! function_exists( 'imagecreatefromstring' ) || ! is_readable( $path ) || filesize( $path ) > 25 * MB_IN_BYTES ) {
		return false;
	}

	$data = file_get_contents( $path );

	return false === $data ? false : @imagecreatefromstring( $data );
}

/** Generates the JPG with GD. */
function personal_cta_social_thumbnail_render_gd( $source_path, $logo_path, $target_path, $settings, $width = 1200, $height = 630 ) {
	if ( ! function_exists( 'imagecreatetruecolor' ) || ! function_exists( 'imagejpeg' ) ) {
		return new WP_Error( 'pct_social_no_gd', 'GD 이미지 처리를 사용할 수 없습니다.' );
	}

	$source = personal_cta_social_thumbnail_gd_load( $source_path );
	if ( ! $source ) {
		return new WP_Error( 'pct_social_source', '대표이미지를 읽을 수 없습니다.' );
	}

	$canvas = imagecreatetruecolor( $width, $height );
	if ( ! $canvas ) {
		imagedestroy( $source );
		return new WP_Error( 'pct_social_canvas', '썸네일 캔버스를 만들 수 없습니다.' );
	}

	imagealphablending( $canvas, true );
	$background = imagecolorallocate( $canvas, 248, 250, 252 );
	$border_rgb = personal_cta_social_thumbnail_rgb( $settings['border_color'] );
	$border     = imagecolorallocate( $canvas, $border_rgb[0], $border_rgb[1], $border_rgb[2] );
	imagefill( $canvas, 0, 0, $background );
	imagesetthickness( $canvas, 6 );
	imagerectangle( $canvas, 18, 18, $width - 19, $height - 19, $border );

	list( $draw_width, $draw_height ) = personal_cta_social_thumbnail_contain( imagesx( $source ), imagesy( $source ), $width - 84, $height - 84 );
	$draw_x = (int) round( ( $width - $draw_width ) / 2 );
	$draw_y = (int) round( ( $height - $draw_height ) / 2 );
	imagecopyresampled( $canvas, $source, $draw_x, $draw_y, 0, 0, $draw_width, $draw_height, imagesx( $source ), imagesy( $source ) );
	imagedestroy( $source );

	$logo = $logo_path ? personal_cta_social_thumbnail_gd_load( $logo_path ) : false;
	if ( $logo ) {
		list( $logo_width, $logo_height ) = personal_cta_social_thumbnail_contain( imagesx( $logo ), imagesy( $logo ), 260, 58 );
		$logo_x = 'top-left' === $settings['logo_position'] ? 60 : $width - 60 - $logo_width;
		$logo_y = 60;
		imagecopyresampled( $canvas, $logo, $logo_x, $logo_y, 0, 0, $logo_width, $logo_height, imagesx( $logo ), imagesy( $logo ) );
		imagedestroy( $logo );
	}

	$result = imagejpeg( $canvas, $target_path, 85 );
	imagedestroy( $canvas );

	return $result ? true : new WP_Error( 'pct_social_write', 'JPG 파일을 저장할 수 없습니다.' );
}

/** Generates the JPG with Imagick. */
function personal_cta_social_thumbnail_render_imagick( $source_path, $logo_path, $target_path, $settings, $width = 1200, $height = 630 ) {
	if ( ! class_exists( 'Imagick' ) ) {
		return new WP_Error( 'pct_social_no_imagick', 'Imagick 이미지 처리를 사용할 수 없습니다.' );
	}

	try {
		$source = new Imagick( $source_path );
		$source->setIteratorIndex( 0 );
		list( $draw_width, $draw_height ) = personal_cta_social_thumbnail_contain( $source->getImageWidth(), $source->getImageHeight(), $width - 84, $height - 84 );
		$source->resizeImage( $draw_width, $draw_height, Imagick::FILTER_LANCZOS, 1 );

		$canvas = new Imagick();
		$canvas->newImage( $width, $height, new ImagickPixel( '#f8fafc' ), 'jpg' );
		$draw = new ImagickDraw();
		$draw->setFillColor( 'none' );
		$draw->setStrokeColor( $settings['border_color'] );
		$draw->setStrokeWidth( 6 );
		$draw->rectangle( 18, 18, $width - 19, $height - 19 );
		$canvas->drawImage( $draw );
		$canvas->compositeImage( $source, Imagick::COMPOSITE_OVER, (int) round( ( $width - $draw_width ) / 2 ), (int) round( ( $height - $draw_height ) / 2 ) );
		$draw->clear();
		$source->clear();

		if ( $logo_path ) {
			$logo = new Imagick( $logo_path );
			$logo->setIteratorIndex( 0 );
			list( $logo_width, $logo_height ) = personal_cta_social_thumbnail_contain( $logo->getImageWidth(), $logo->getImageHeight(), 260, 58 );
			$logo->resizeImage( $logo_width, $logo_height, Imagick::FILTER_LANCZOS, 1 );
			$logo_x = 'top-left' === $settings['logo_position'] ? 60 : $width - 60 - $logo_width;
			$canvas->compositeImage( $logo, Imagick::COMPOSITE_OVER, $logo_x, 60 );
			$logo->clear();
		}

		$canvas->setImageFormat( 'jpeg' );
		$canvas->setImageCompressionQuality( 85 );
		$canvas->stripImage();
		$result = $canvas->writeImage( $target_path );
		$canvas->clear();

		return $result ? true : new WP_Error( 'pct_social_write', 'JPG 파일을 저장할 수 없습니다.' );
	} catch ( Exception $error ) {
		return new WP_Error( 'pct_social_imagick', 'Imagick으로 이미지를 처리할 수 없습니다.' );
	}
}

/** Uses the first server image engine that can successfully render the card. */
function personal_cta_social_thumbnail_render( $source_path, $logo_path, $target_path, $settings, $width = 1200, $height = 630 ) {
	$result = personal_cta_social_thumbnail_render_imagick( $source_path, $logo_path, $target_path, $settings, $width, $height );
	if ( true === $result ) {
		return true;
	}

	return personal_cta_social_thumbnail_render_gd( $source_path, $logo_path, $target_path, $settings, $width, $height );
}

/** Builds a stable cache key for one featured-image/settings combination. */
function personal_cta_social_thumbnail_hash( $post_id, $source_path, $logo_id, $logo_path, $settings ) {
	return sha1(
		implode(
			'|',
			array(
				(int) $post_id,
				(string) $source_path,
				(string) @filemtime( $source_path ),
				(string) @filesize( $source_path ),
				(int) $logo_id,
				$logo_path ? (string) @filemtime( $logo_path ) : '',
				wp_json_encode( $settings ),
				'layout-v3-four-ratios',
			)
		)
	);
}

/** Confirms that all four generated files are still readable. */
function personal_cta_social_thumbnail_variants_ready( $data ) {
	if ( ! is_array( $data ) || empty( $data['variants'] ) || ! is_array( $data['variants'] ) ) {
		return false;
	}

	foreach ( personal_cta_social_thumbnail_variants() as $name => $size ) {
		$variant = $data['variants'][ $name ] ?? array();
		if ( empty( $variant['file'] ) || empty( $variant['url'] ) || ! is_readable( $variant['file'] ) ) {
			return false;
		}
	}

	return true;
}

/** Generates or reuses the social JPG for a post. */
function personal_cta_social_thumbnail_generate( $post_id ) {
	$post_id  = absint( $post_id );
	$settings = personal_cta_social_thumbnail_settings();

	if ( ! $post_id || empty( $settings['enabled'] ) || 'post' !== get_post_type( $post_id ) ) {
		return new WP_Error( 'pct_social_disabled', '소셜 썸네일 생성 대상이 아닙니다.' );
	}

	$thumbnail_id = get_post_thumbnail_id( $post_id );
	$source_path  = $thumbnail_id ? get_attached_file( $thumbnail_id ) : '';
	if ( ! $source_path || ! is_readable( $source_path ) ) {
		return new WP_Error( 'pct_social_no_source', '읽을 수 있는 대표이미지가 없습니다.' );
	}

	$size = function_exists( 'wp_getimagesize' ) ? wp_getimagesize( $source_path ) : getimagesize( $source_path );
	if ( ! is_array( $size ) || empty( $size[0] ) || empty( $size[1] ) || (int) $size[0] * (int) $size[1] > 50000000 ) {
		return new WP_Error( 'pct_social_dimensions', '대표이미지 크기를 처리할 수 없습니다.' );
	}

	$logo_id   = personal_cta_social_thumbnail_logo_id( $settings );
	$logo_path = $logo_id ? get_attached_file( $logo_id ) : '';
	$logo_path = $logo_path && is_readable( $logo_path ) ? $logo_path : '';
	$hash      = personal_cta_social_thumbnail_hash( $post_id, $source_path, $logo_id, $logo_path, $settings );
	$current   = get_post_meta( $post_id, PERSONAL_CTA_SOCIAL_THUMBNAIL_META, true );

	if ( is_array( $current ) && $hash === ( $current['hash'] ?? '' ) && personal_cta_social_thumbnail_variants_ready( $current ) ) {
		return $current['url'];
	}

	$uploads = wp_upload_dir();
	if ( ! empty( $uploads['error'] ) ) {
		return new WP_Error( 'pct_social_uploads', $uploads['error'] );
	}

	$directory = trailingslashit( $uploads['basedir'] ) . 'personal-cta-social';
	if ( ! wp_mkdir_p( $directory ) ) {
		return new WP_Error( 'pct_social_directory', '소셜 썸네일 폴더를 만들 수 없습니다.' );
	}

	$pending = array();
	foreach ( personal_cta_social_thumbnail_variants() as $name => $dimensions ) {
		$filename = 'post-' . $post_id . '-' . substr( $hash, 0, 12 ) . '-' . $name . '.jpg';
		$file     = trailingslashit( $directory ) . $filename;
		$temp     = trailingslashit( $directory ) . '.' . wp_generate_uuid4() . '.jpg';
		$result   = personal_cta_social_thumbnail_render( $source_path, $logo_path, $temp, $settings, $dimensions[0], $dimensions[1] );

		if ( is_wp_error( $result ) ) {
			if ( file_exists( $temp ) ) {
				wp_delete_file( $temp );
			}
			foreach ( $pending as $generated ) {
				if ( file_exists( $generated['temp'] ) ) {
					wp_delete_file( $generated['temp'] );
				}
			}
			update_post_meta( $post_id, '_personal_cta_social_thumbnail_error', $result->get_error_message() );
			return $result;
		}

		$pending[ $name ] = array(
			'temp'     => $temp,
			'file'     => $file,
			'filename' => $filename,
			'width'    => $dimensions[0],
			'height'   => $dimensions[1],
		);
	}

	$outputs = array();
	foreach ( $pending as $name => $generated ) {
		if ( is_readable( $generated['file'] ) ) {
			wp_delete_file( $generated['temp'] );
		} elseif ( ! @rename( $generated['temp'], $generated['file'] ) ) {
			foreach ( $pending as $remaining ) {
				if ( file_exists( $remaining['temp'] ) ) {
					wp_delete_file( $remaining['temp'] );
				}
			}
			$error = new WP_Error( 'pct_social_move', '완성된 썸네일 파일을 저장할 수 없습니다.' );
			update_post_meta( $post_id, '_personal_cta_social_thumbnail_error', $error->get_error_message() );
			return $error;
		}

		$outputs[ $name ] = array(
			'file'   => $generated['file'],
			'url'    => esc_url_raw( trailingslashit( $uploads['baseurl'] ) . 'personal-cta-social/' . rawurlencode( $generated['filename'] ) ),
			'width'  => $generated['width'],
			'height' => $generated['height'],
		);
	}

	$social = $outputs['social'];
	$data   = array(
		'hash'      => $hash,
		'file'      => $social['file'],
		'url'       => $social['url'],
		'source_id' => (int) $thumbnail_id,
		'logo_id'   => (int) $logo_id,
		'width'     => $social['width'],
		'height'    => $social['height'],
		'variants'  => $outputs,
	);
	update_post_meta( $post_id, PERSONAL_CTA_SOCIAL_THUMBNAIL_META, $data );
	delete_post_meta( $post_id, '_personal_cta_social_thumbnail_error' );

	return $data['url'];
}

/** Generates after ordinary post saves. */
function personal_cta_social_thumbnail_on_save( $post_id, $post ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || 'auto-draft' === $post->post_status ) {
		return;
	}

	personal_cta_social_thumbnail_generate( $post_id );
}
add_action( 'save_post_post', 'personal_cta_social_thumbnail_on_save', 100, 2 );

/** Regenerates immediately when the featured-image ID changes. */
function personal_cta_social_thumbnail_on_meta_change( $meta_id, $post_id, $meta_key ) {
	if ( '_thumbnail_id' === $meta_key && 'post' === get_post_type( $post_id ) ) {
		personal_cta_social_thumbnail_generate( $post_id );
	}
}
add_action( 'added_post_meta', 'personal_cta_social_thumbnail_on_meta_change', 20, 3 );
add_action( 'updated_post_meta', 'personal_cta_social_thumbnail_on_meta_change', 20, 3 );

/** Detects a post-level Rank Math image that must remain higher priority. */
function personal_cta_social_thumbnail_has_manual_rank_math_image( $post_id, $network ) {
	$keys = 'twitter' === $network
		? array( 'rank_math_twitter_image', 'rank_math_twitter_image_id', 'rank_math_facebook_image', 'rank_math_facebook_image_id' )
		: array( 'rank_math_facebook_image', 'rank_math_facebook_image_id' );

	foreach ( $keys as $key ) {
		if ( '' !== trim( (string) get_post_meta( $post_id, $key, true ) ) ) {
			return true;
		}
	}

	return false;
}

/** Returns current generated data only while it matches the featured image. */
function personal_cta_social_thumbnail_current_data( $post_id ) {
	$data = get_post_meta( $post_id, PERSONAL_CTA_SOCIAL_THUMBNAIL_META, true );

	if ( ! is_array( $data ) || empty( $data['url'] ) || empty( $data['file'] ) || ! is_readable( $data['file'] ) || (int) get_post_thumbnail_id( $post_id ) !== (int) ( $data['source_id'] ?? 0 ) ) {
		return array();
	}

	return $data;
}

/** Returns the generated URL when the current singular post may use it. */
function personal_cta_social_thumbnail_rank_math_image( $url, $network ) {
	$settings = personal_cta_social_thumbnail_settings();
	if ( empty( $settings['enabled'] ) || ! is_singular( 'post' ) ) {
		return $url;
	}

	$post_id = get_queried_object_id();
	if ( ! $post_id || personal_cta_social_thumbnail_has_manual_rank_math_image( $post_id, $network ) ) {
		return $url;
	}

	$data = personal_cta_social_thumbnail_current_data( $post_id );
	if ( empty( $data ) ) {
		return $url;
	}

	return $data['url'];
}

/** Rank Math Facebook/Threads image filter. */
function personal_cta_social_thumbnail_rank_math_facebook( $url ) {
	return personal_cta_social_thumbnail_rank_math_image( $url, 'facebook' );
}
add_filter( 'rank_math/opengraph/facebook/image', 'personal_cta_social_thumbnail_rank_math_facebook', 20 );

/** Rank Math X/Twitter image filter. */
function personal_cta_social_thumbnail_rank_math_twitter( $url ) {
	return personal_cta_social_thumbnail_rank_math_image( $url, 'twitter' );
}
add_filter( 'rank_math/opengraph/twitter/image', 'personal_cta_social_thumbnail_rank_math_twitter', 20 );

/** Supplies Google's recommended 1:1, 4:3, and 16:9 Article images to Rank Math. */
function personal_cta_social_thumbnail_rank_math_article( $entity ) {
	$settings = personal_cta_social_thumbnail_settings();
	if ( empty( $settings['enabled'] ) || ! is_singular( 'post' ) ) {
		return $entity;
	}

	$data = personal_cta_social_thumbnail_current_data( get_queried_object_id() );
	if ( ! personal_cta_social_thumbnail_variants_ready( $data ) ) {
		return $entity;
	}

	$entity['image'] = array(
		$data['variants']['1x1']['url'],
		$data['variants']['4x3']['url'],
		$data['variants']['16x9']['url'],
	);

	return $entity;
}
add_filter( 'rank_math/snippet/rich_snippet_article_entity', 'personal_cta_social_thumbnail_rank_math_article', 20 );
