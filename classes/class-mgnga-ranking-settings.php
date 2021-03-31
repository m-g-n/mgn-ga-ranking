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

	/**
	 * 設定ページ登録
	 */
	public function add_admin_menu() {
		add_options_page( 'mgn GA Ranking', 'mgn GA Ranking', 'manage_options', $this->option_page, [ $this, 'options_page_html' ] );
	}

	/**
	 * 設定項目登録
	 */
	public function add_setting_init() {
		register_setting( 'mgnga_ranking_settings_group', 'mgnga_ranking_settings', [ 'sanitize_callback' => [ $this, 'settings_sanitize' ] ] );

		add_settings_section( 'mgnga_ranking_setting_section', __( '基本設定' ), [ $this, 'setting_section_cb' ], $this->option_page );

		add_settings_field( 'mgnga_service_account_json', __( 'GoogleAPI サービスアカウントJson' ), [ $this, 'service_account' ], $this->option_page, 'mgnga_ranking_setting_section' );

		add_settings_field( 'mgnga_analytics_view_id', __( 'Google Analytics ビューID' ), [ $this, 'view_id' ], $this->option_page, 'mgnga_ranking_setting_section' );

		add_settings_field( 'mgnga_period', __( '計測期間' ), [ $this, 'period' ], $this->option_page, 'mgnga_ranking_setting_section' );

		add_settings_field( 'mgnga_expiration', __( '有効期限' ), [ $this, 'expiration' ], $this->option_page, 'mgnga_ranking_setting_section' );

		add_settings_section( 'mgnga_ranking_reget_section', __( '再取得' ), [ $this, 'setting_section_cb' ], 'mgnga_reget' );
	}

	/**
	 * 設定情報サニタイズ処理
	 *
	 * @param $inputs 入力された情報
	 *
	 * @return false|mixed|void
	 */
	public function settings_sanitize( $inputs ) {
		if ( isset( $_POST['reget'] ) ) {
			$r = mgnga_set_ranking();
			if ( $r ) {
				add_settings_error( 'mgnga_reget_id', 'mgnga_view_reget_info', '情報の再取得を行いました。', 'info' );
			} else {
				add_settings_error( 'mgnga_reget_id', 'mgnga_view_reget_info', '情報の再取得に失敗しました。' );
			}
			return $this->settings;
		}

		if ( isset( $inputs['service_account'] ) && is_string( $inputs['service_account'] ) ) {
			$service_account = json_decode( $inputs['service_account'], true );
			if ( is_null( $service_account ) && strlen( $inputs['service_account'] ) > 0 ) {
				$inputs['service_account'] = $this->settings['service_account'];
				$errors['service_account'] = true;
				add_settings_error( 'mgnga_service_account_json', 'mgnga_service_account_json_error', 'GoogleAPIサービスアカウントが正しいJSON形式で入力されませんでした。' );
			} else {
				$inputs['service_account'] = $service_account;
			}
		}

		if ( isset( $inputs['view_id'] ) ) {
			if ( ! is_numeric( $inputs['view_id'] ) && strlen( $inputs['view_id'] ) > 0 ) {
				$inputs['view_id'] = $this->settings['view_id'];
				$errors['view_id'] = true;
				add_settings_error( 'mgnga_view_id', 'mgnga_view_id_error', 'ビューIDは数値で入力する必要があります。' );
			}
		}

		if ( isset( $inputs['period_num'] ) ) {
			if ( ! is_numeric( $inputs['period_num'] ) ) {
				$inputs['period_num'] = $this->settings['period_num'] ?? 1;
				$errors['period_num'] = true;
				add_settings_error( 'mgnga_period_num', 'mgnga_period_num_error', '計測期間は数値で入力する必要があります。' );
			}
		}

		if ( isset( $inputs['period_unit'] ) ) {
			if ( ! in_array( $inputs['period_unit'], array_keys( mgnga_get_time_unit() ), true ) ) {
				$inputs['period_unit'] = $this->settings['period_unit'] ?? 'week';
				$errors['period_unit'] = true;
				add_settings_error( 'mgnga_period_unit', 'mgnga_period_unit_error', '計測期間の単位が不正です。' );
			}
		}

		if ( isset( $inputs['expiration_num'] ) ) {
			if ( ! is_numeric( $inputs['expiration_num'] ) ) {
				$inputs['expiration_num'] = $this->settings['expiration_num'] ?? 1;
				$errors['expiration_num'] = true;
				add_settings_error( 'mgnga_expiration_num', 'mgnga_expiration_num_error', '有効期限は数値で入力する必要があります。' );
			}
		}

		if ( isset( $inputs['expiration_unit'] ) ) {
			if ( ! in_array( $inputs['expiration_unit'], array_keys( mgnga_get_time_unit() ), true ) ) {
				$inputs['expiration_unit'] = $this->settings['expiration_unit'] ?? 'week';
				$errors['expiration_unit'] = true;
				add_settings_error( 'mgnga_expiration_unit', 'mgnga_expiration_unit_error', '有効期限の単位が不正です。' );
			}
		}

		return $inputs;
	}

	/**
	 * 設定セクションHTML出力
	 *
	 * @param $args
	 */
	public function setting_section_cb( $args ) {
	}

	/**
	 * サービスアカウント設定項目HTML
	 */
	public function service_account() {
?>
<textarea type="text" name="mgnga_ranking_settings[service_account]" style="width: 50%; height: 500px;"><?php echo isset( $this->settings['service_account'] ) ? esc_attr( json_encode( $this->settings['service_account'] ), JSON_PRETTY_PRINT ) : ''; ?></textarea>
<?php
	}

	/**
	 * ビューID設定項目HTML
	 */
	public function view_id() {
?>
<input type="text" name="mgnga_ranking_settings[view_id]" id="mgnga_view_id" value="<?php echo $this->settings['view_id'] ?? ''; ?>">
<?php
	}

	/**
	 * 計測期間設定項目HTML
	 */
	public function period() {
		$unit = $this->settings['period_unit'] ?? 'week';
?>
<input type="text" name="mgnga_ranking_settings[period_num]" value="<?php echo $this->settings['period_num'] ?? '1'; ?>">
<select name="mgnga_ranking_settings[period_unit]">
	<option value="day"<?php selected( $unit, 'day' ); ?>>日</option>
	<option value="week"<?php selected( $unit, 'week' ); ?>>週間</option>
	<option value="month"<?php selected( $unit, 'month' ); ?>>ヶ月(30日単位)</option>
	<option value="year"<?php selected( $unit, 'year' ); ?>>年</option>
</select>
<?php
	}

	/**
	 * 情報有効期限設定項目HTML
	 */
	public function expiration() {
		$unit = $this->settings['expiration_unit'] ?? 'day';
?>
<input type="text" name="mgnga_ranking_settings[expiration_num]" value="<?php echo $this->settings['expiration_num'] ?? '1'; ?>">
<select name="mgnga_ranking_settings[expiration_unit]">
	<option value="day"<?php selected( $unit, 'day' ); ?>>日</option>
	<option value="week"<?php selected( $unit, 'week' ); ?>>週間</option>
	<option value="month"<?php selected( $unit, 'month' ); ?>>ヶ月(30日単位)</option>
	<option value="year"<?php selected( $unit, 'year' ); ?>>年</option>
</select>
<?php
	}

	/**
	 * オプションページのHTML出力
	 */
	public function options_page_html() {
?>
<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	<form action="<?php echo admin_url( 'options.php' ); ?>" method="post">
		<?php settings_fields( 'mgnga_ranking_settings_group' ); ?>
		<?php do_settings_sections( $this->option_page ); ?>
		<?php submit_button( __( 'Save Settings' ) ); ?>

		<?php do_settings_sections( 'mgnga_reget' ); ?>
		<?php submit_button( __( '再取得' ), 'primary', 'reget' ); ?>
	</form>
</div
<?php
	}
}
