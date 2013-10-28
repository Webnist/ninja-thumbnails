<?php
class NinjaThumbAdmin {
	const OPTION_PAGE  = 'ninja-thumb';
	const OPTION_GROUP = 'ninja-thumb';

	private $plugin_basename;
	private $plugin_dir_path;
	private $plugin_dir_url;

	public function __construct() {
		$this->plugin_basename      = NinjaThumb::plugin_basename();
		$this->plugin_dir_path      = NinjaThumb::plugin_dir_path();
		$this->plugin_dir_url       = NinjaThumb::plugin_dir_url();
		$this->ninja_onmitsu        = (int) get_option( 'ninja_onmitsu' );
		$this->ninja_execution_date = get_option( 'ninja_execution_date' );

		add_action( 'admin_menu', array( &$this, 'admin_menu' ) );
		add_action( 'admin_init', array( &$this, 'add_general_custom_fields' ) );
		add_filter( 'admin_init', array( &$this, 'add_custom_whitelist_options_fields' ) );
		add_action( 'admin_enqueue_scripts', array( &$this, 'admin_enqueue_scripts' ) );
	}

	public function admin_menu() {
		add_menu_page( __( 'Ninja Thumbnails', NinjaThumb::TEXT_DOMAIN ), __( 'Ninja Thumbnails', NinjaThumb::TEXT_DOMAIN ), 'edit_users', self::OPTION_PAGE, array( &$this, 'add_admin_edit_page' ), $this->plugin_dir_url . '/admin/images/menu.png' );
	}

	public function add_admin_edit_page() {
		$title = __( 'Set Ninja Thumbnails', NinjaThumb::TEXT_DOMAIN ); ?>
		<div class="wrap">
			<?php screen_icon(); ?>
			<h2><?php echo esc_html( $title ); ?></h2>
			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION_GROUP ); ?>
				<?php do_settings_sections( self::OPTION_PAGE ); ?>
				<input type="hidden" name="refresh">
				<table class="form-table">
					<?php do_settings_fields( self::OPTION_PAGE, 'default' ); ?>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
	<?php }

	public function add_general_custom_fields() {
		global $add_settings_field;
		add_settings_field( 'ninja_onmitsu', __( 'Onmitsu execution.', NinjaThumb::TEXT_DOMAIN ), array( &$this, 'ninja_check_box' ), self::OPTION_PAGE, 'default', array( 'name' => 'ninja_onmitsu', 'value' => $this->ninja_onmitsu, 'note' => __( 'Enabling', NinjaThumb::TEXT_DOMAIN ) ) );
		add_settings_field( 'ninja_execution_date', __( 'Execution Date', NinjaThumb::TEXT_DOMAIN ), array( &$this, 'ninja_text_field' ), self::OPTION_PAGE, 'default', array( 'name' => 'ninja_execution_date', 'value' => $this->ninja_execution_date ) );
	}

	public function ninja_check_box( $args ) {
		extract( $args );
		$output = '<label><input type="checkbox" name="' . $args['name'] .'" id="' . $args['name'] .'" value="1"' . checked( 1, $args['value'], false ). ' /> ' . esc_html__( $args['note'], NinjaThumb::TEXT_DOMAIN ) . '</label>' ."\n";
		echo $output;
	}

	public function ninja_text_field( $args ) {
		extract( $args );
		$output = '<label><input type="text" name="' . $args['name'] .'" id="' . $args['name'] .'" value="' . $args['value'] .'" /></label>' ."\n";
		$output .= '<label><input type="checkbox" name="ninja_thumbnails_modified" id="ninja_thumbnails_modified" value="1" /> ' . esc_html__( 'Set the last update date', NinjaThumb::TEXT_DOMAIN ) . '</label>' ."\n";
		echo $output;
	}

	public function add_custom_whitelist_options_fields() {
		register_setting( self::OPTION_PAGE, 'ninja_onmitsu', 'intval' );
		register_setting( self::OPTION_PAGE, 'ninja_execution_date', array( &$this, 'add_time' ) );
		register_setting( self::OPTION_PAGE, 'ninja_thumbnails_modified', array( &$this, 'add_ninja_thumbnails_modified' ) );
	}

	public function add_time( $input ) {
		if ( $input == date_i18n( 'Y-m-d' ) ) {
			$time = date_i18n( 'H:i:s' );
			$input = $input . ' ' . $time;
		}
		return $input;
	}

	public function add_ninja_thumbnails_modified( $input ) {
		if ( !$input )
			return $input;

		$date = strtotime( NinjaThumb::get_ninja_thumbnails_modified() ) + 360;
		$date = date_i18n( 'Y-m-d H:i:s', $date );
		update_option( 'ninja_execution_date', $date );
	}

	public function admin_enqueue_scripts( $hook ) {
		if ( $hook != 'toplevel_page_ninja-thumb')
			return;

		wp_enqueue_script( 'admin-ninja-thumbnails-script', $this->plugin_dir_url . '/admin/js/ninja-thumbnails.js', array('jquery-ui-datepicker') );

		wp_enqueue_style( 'admin-ninja-thumbnails-style', $this->plugin_dir_url . '/admin/css/ninja-thumbnails.css' );
		wp_enqueue_style( 'admin-ninja-thumbnails-jquery-ui-style', $this->plugin_dir_url . '/admin/css/ui-lightness/jquery-ui-1.9.2.custom.min.css' );
	}

}
