<?php
/*
Plugin Name: Ninja Thumbnails
Plugin URI: http://plugins.webnist.net/
Description: When access to thumbnails that change had not created thumbnail images to regenerate.
Version: 0.7.2.0
Author: Webnist
Author URI: http://webni.st
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: ninja-thumbnails
Domain Path: /languages/
*/

if ( !class_exists('NinjaThumbAdmin') )
	require_once(dirname(__FILE__).'/includes/class-admin-menu.php');

class NinjaThumb {
	const TEXT_DOMAIN = 'ninja-thumbnails';
	const PLUGIN_NAME = 'ninja-thumbnails';

	private $plugin_basename;
	private $plugin_dir_path;
	private $plugin_dir_url;

	public function __construct() {
		$this->plugin_basename = self::plugin_basename();
		$this->plugin_dir_path = self::plugin_dir_path();
		$this->plugin_dir_url  = self::plugin_dir_url();

		load_plugin_textdomain(self::TEXT_DOMAIN, false, dirname($this->plugin_basename) . '/languages/');

		register_activation_hook( __FILE__, array( &$this, 'add_options' ) );

		if ( get_option( 'ninja_onmitsu' ) )
			add_action( 'begin_fetch_post_thumbnail_html', array( &$this, 'ninja_onmitsu' ), 10, 3 );

		if ( is_admin() ) {
			add_filter( 'manage_posts_columns', array( &$this, 'manage_posts_columns' ) );
			add_action( 'admin_print_styles-edit.php', array( &$this, 'add_menu_order_column_styles' ) );
		}
	}

	static public function plugin_basename() {
		return plugin_basename(__FILE__);
	}

	static public function plugin_dir_path() {
		return WP_PLUGIN_DIR . '/' . plugin_dir_path( self::plugin_basename() );
	}

	static public function plugin_dir_url() {
		return plugin_dir_url( self::plugin_basename() );
	}

	public function add_options() {
		if ( !get_option( 'ninja_execution_date' ) ) {
			$get_date = self::get_ninja_thumbnails_modified();
			update_option( 'ninja_execution_date', $get_date );
		}

		if ( !get_option( 'ninja_onmitsu' ) )
			update_option( 'ninja_onmitsu', false );

		if ( !get_option( 'ninja_size' ) )
			update_option( 'ninja_size', false );
	}

	// カラムを追加
	public function manage_posts_columns( $posts_columns ) {
		$new_columns = array();
		foreach ( $posts_columns as $column_name => $column_display_name ) {
			if ( $column_name == 'title' ) {
				$new_columns['thumbnail'] = __('Thumbnail');
				add_action( 'manage_posts_custom_column', array( &$this, 'manage_posts_custom_column' ), 10, 2 );
			}
			$new_columns[$column_name] = $column_display_name;
		}
		return $new_columns;
	}

	// 追加したカラムの中身
	public function manage_posts_custom_column($column_name, $post_id) {

		// アイキャッチ
		if ( $column_name == 'thumbnail') {
			$thum = ( get_the_post_thumbnail( $post_id, array(50,50), 'thumbnail' ) ) ? get_the_post_thumbnail( $post_id, array(50,50), 'thumbnail' ) : __('None') ;
			echo $thum;
		}
	}

	// 追加したカラムのスタイルシート
	public function add_menu_order_column_styles() {
		if ( 'post' == get_post_type() ) { ?>
			<style type="text/css" charset="utf-8">
				.fixed .column-thumbnail {
					width: 7%;
				}
			</style>
		<?php }
	}

	public function get_ninja_thumbnails_modified() {
		$args = array(
			'posts_per_page' => 1,
			'post_type'      => 'attachment',
			'post_mime_type' => array(
				'image/jpeg',
				'image/png',
				'image/gif',
			),
			'orderby'        => 'modified',
		);
		$get_posts = get_posts( $args );
		$value = $get_posts[0]->post_modified_gmt;
		return $value;
	}

	function ninja_onmitsu( $post_id, $post_thumbnail_id, $size ) {
		if ( is_admin() )
			return;

		$execution_date = strtotime( get_option( 'ninja_execution_date' ) );
		$time           = date_i18n( 'H:i:s', $execution_date );
		if ( $time == '00:00:00' ) {
			$date           = date_i18n( 'Y-m-d', $execution_date );
			$date           = $date . ' 23:59:59';
			$execution_date = strtotime( $date );
		}

		$thumb               = get_post( $post_thumbnail_id );
		$thumb_modified_date = date_i18n( 'U', strtotime( $thumb->post_modified ) );
		$thumb_path          = get_attached_file( $post_thumbnail_id );

		if ( $execution_date < $thumb_modified_date || !file_exists( $thumb_path ) )
			return;

		global $_wp_additional_image_sizes;

		$gmdate = gmdate( 'Y-m-d H:i:s' );
		$date   = date_i18n( 'Y-m-d H:i:s' );
		$args   = array(
			'ID'                => $post_thumbnail_id,
			'post_modified'     => $date,
			'post_modified_gmt' => $gmdate,
		);
		wp_update_post( $args );

		$thumb_info     = pathinfo( $thumb_path );
		$thumb_dir      = $thumb_info['dirname'];
		$thumb_basename = $thumb_info['basename'];
		$thumb_exte     = $thumb_info['extension'];
		$thumb_name     = $thumb_info['filename'];
		$mime_type      = get_post_mime_type( $post_thumbnail_id );
		$thumb_data     = wp_get_attachment_metadata( $post_thumbnail_id );
		$dir            = opendir( $thumb_dir );
		if ( $size_list = get_option( 'ninja_size' ) ) {
			foreach ( $size_list as $size ) {
				$sizes[] = $thumb_data['sizes'][$size];
			}
			$files = $size_list;
		} else {
			$sizes = array( $size => $thumb_data['sizes'][$size] );
			$files = array( $size );
		}
		foreach ( $sizes as $key => $value ) {
			$file = $thumb_dir . '/' . $value['file'];
			unlink( $file );
		}
		$sizes = array();
		foreach ( $files as $s ) {
			$sizes[$s] = array( 'width' => '', 'height' => '', 'crop' => false );
			if ( isset( $_wp_additional_image_sizes[$s]['width'] ) )
				$sizes[$s]['width'] = intval( $_wp_additional_image_sizes[$s]['width'] );
			else
				$sizes[$s]['width'] = get_option( "{$s}_size_w" );
			if ( isset( $_wp_additional_image_sizes[$s]['height'] ) )
				$sizes[$s]['height'] = intval( $_wp_additional_image_sizes[$s]['height'] );
			else
				$sizes[$s]['height'] = get_option( "{$s}_size_h" );
			if ( isset( $_wp_additional_image_sizes[$s]['crop'] ) )
				$sizes[$s]['crop'] = intval( $_wp_additional_image_sizes[$s]['crop'] );
			else
				$sizes[$s]['crop'] = get_option( "{$s}_crop" );

			$width  = (int) $sizes[$s]['width'];
			$height = (int) $sizes[$s]['height'];
			if ( $crop = $sizes[$s]['crop'] )
				$crop = true;

			$image = wp_get_image_editor( $thumb_path );
			if ( is_wp_error($image) )
				return false;
			$image->resize( $width, $height, $crop );
			$image->set_quality( 100 );
			$file_name = $image->generate_filename();
			$image->save( $file_name );

			$file          = basename($file_name);
			$get_resize    = $image->get_size();
			$resize_width  = $get_resize['width'];
			$resize_height = $get_resize['height'];

			$thumb_data['sizes'][$s] = array(
				'file'      => $file,
				'width'     => $resize_width,
				'height'    => $resize_height,
				'mime-type' => $mime_type,
			);
		}
		wp_update_attachment_metadata( $post_thumbnail_id, $thumb_data );
	}

} // end of class

new NinjaThumb();
new NinjaThumbAdmin();
