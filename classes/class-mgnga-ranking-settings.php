<?php
/**
 * 基本処理クラス
 *
 * @package mgn-ga-ranking
 * @since 1.0.0
 */

namespace MGNGA;

class GA_Access_Ranking_Settings {
	private $settings;
	private $option_page = 'mgnga_ranking';
	private $errors = [];

	public function __construct() {
		$this->settings = get_option( 'mgnga_ranking_settings', true );

		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'add_setting_init' ] );
	}

	public function add_admin_menu() {
		add_options_page( 'mgn GA Ranking', 'mgn GA Ranking', 'manage_options', $this->option_page, [ $this, 'options_page_html' ] );
	}

	public function add_setting_init() {
		register_setting( 'mgnga_ranking_settings_group', 'mgnga_ranking_settings', [ 'sanitize_callback' => [ $this, 'settings_sanitize' ] ] );

		add_settings_section( 'mgnga_ranking_setting_section', __( '基本設定' ), [ $this, 'setting_section_cb' ], $this->option_page );

		add_settings_field( 'mgnga_service_account_json', __( 'GoogleAPI サービスアカウントJson' ), [ $this, 'service_account' ], $this->option_page, 'mgnga_ranking_setting_section' );

		add_settings_field( 'mgnga_analytics_view_id', __( 'Google Analytics ビューID' ), [ $this, 'view_id' ], $this->option_page, 'mgnga_ranking_setting_section' );

		add_settings_field( 'mgnga_period', __( '計測期間' ), [ $this, 'period' ], $this->option_page, 'mgnga_ranking_setting_section' );

		add_settings_field( 'mgnga_expiration', __( '有効期限' ), [ $this, 'expiration' ], $this->option_page, 'mgnga_ranking_setting_section' );
	}

	public function settings_sanitize( $inputs ) {
		if ( isset( $inputs['service_account'] ) ) {
			$service_account = json_decode( $inputs['service_account'], true );
			if ( is_null( $service_account ) ) {
				$inputs['service_account'] = $this->settings['service_account'];
				$errors['service_account'] = true;
				add_settings_error( 'mgnga_service_account_json', 'mgnga_service_account_json_error', 'GoogleAPIサービスアカウントが正しいJSON形式で入力されませんでした。' );
			} else {
				$inputs['service_account'] = $service_account;
			}
		}

		if ( isset( $inputs['view_id'] ) ) {
			if ( ! is_numeric( $inputs['view_id'] ) ) {
				$inputs['view_id'] = $this->settings['view_id'];
				$errors['view_id'] = true;
				add_settings_error( 'mgnga_view_id', 'mgnga_view_id_error', 'ビューIDは数値で入力する必要があります。' );
			}
		}
		return $inputs;
	}

	public function setting_section_cb( $args ) {
	}

	public function service_account() {
?>
<textarea type="text" name="mgnga_ranking_settings[service_account]" style="width: 50%; height: 500px;"><?php echo isset( $this->settings['service_account'] ) ? esc_attr( json_encode( $this->settings['service_account'] ), JSON_PRETTY_PRINT ) : ''; ?></textarea>
<?php
	}

	public function view_id() {
?>
<input type="text" name="mgnga_ranking_settings[view_id]" id="mgnga_view_id" value="<?php echo $this->settings['view_id'] ?? ''; ?>">
<?php
	}

	public function period() {
		$unit = $this->settings['period_unit'] ?? 'day';
?>
<input type="text" name="mgnga_ranking_settings[period_num]" value="<?php echo $this->settings['period_num'] ?? ''; ?>">
<select name="mgnga_ranking_settings[period_unit]">
	<option value="day"<?php selected( $unit, 'day' ); ?>>日</option>
	<option value="week"<?php selected( $unit, 'week' ); ?>>週間</option>
	<option value="month"<?php selected( $unit, 'month' ); ?>>ヶ月(30日単位)</option>
	<option value="year"<?php selected( $unit, 'year' ); ?>>年</option>
</select>
<?php
	}

	public function expiration() {
		$unit = $this->settings['expiration_unit'] ?? 'day';
?>
<input type="text" name="mgnga_ranking_settings[expiration_num]" value="<?php echo $this->settings['expiration_num'] ?? ''; ?>">
<select name="mgnga_ranking_settings[expiration_unit]">
	<option value="day"<?php selected( $unit, 'day' ); ?>>日</option>
	<option value="week"<?php selected( $unit, 'week' ); ?>>週間</option>
	<option value="month"<?php selected( $unit, 'month' ); ?>>ヶ月(30日単位)</option>
	<option value="year"<?php selected( $unit, 'year' ); ?>>年</option>
</select>
<?php
	}

	public function options_page_html() {
?>
<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	<form action="<?php echo admin_url( 'options.php' ); ?>" method="post">
		<?php settings_fields( 'mgnga_ranking_settings_group' ); ?>
		<?php do_settings_sections( $this->option_page ); ?>
		<?php submit_button( __( 'Save Settings' ) ); ?>
	</form>
</div
<?php
	}
}
