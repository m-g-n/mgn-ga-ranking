<?php
/**
 * 処理関数
 *
 * @package mgn-ga-ranking
 * @since 2021.03.30
 */

use Google_Service_AnalyticsReporting_report as Report;
use MGNGA\GAAccessRanking\GA_Access;


/**
 * CRON のスケジュールに独自のスケジュールを追加
 *
 * @return array $schedules CRON のスケジュールの配列
 */
function mgnga_cron_add_halfaday( $schedules ) {
	$schedules['halfaday'] = array(
		'interval' => DAY_IN_SECONDS / 2,
		'display'  => __( 'Once every half a day' ),
	);
	return $schedules;
}
add_filter( 'cron_schedules', 'mgnga_cron_add_halfaday' );

/**
 * プラグインが有効化されたときに実行される関数を登録
 *
 * スケジュールに登録されているアクションの実行時間が取得できなかったら、一定の期間で実行されるアクションを登録
 */
register_activation_hook( __FILE__, 'mgnga_activation' );
function mgnga_activation() {
	if ( ! wp_next_scheduled( 'mgnga_cron_task_hook' ) ) {
		wp_schedule_event( time(), 'halfaday', 'mgnga_cron_task_hook' );
	}
}
add_action( 'wp', 'mgnga_activation' );

// 登録された `mgnga_cron_task_hook` で `mgnga_set_ranking` を実行
add_action( 'mgnga_cron_task_hook', 'mgnga_set_ranking' );

/**
 * プラグインが停止されたときに実行される関数を登録
 *
 * 登録された `mgnga_cron_task_hook` のイベントのスケジュールを削除
 */
register_deactivation_hook( __FILE__, 'mgnga_deactivation' );
function mgnga_deactivation() {
	wp_clear_scheduled_hook( 'mgnga_cron_task_hook' );
}

/**
 * GoogleAnalyticsReportを取得しランキング情報をトランジェント内に保持する
 * // アクションフック `mgnga_cron_task_hook` で利用
 *
 * @param string $range 取得するランキングの期間
 *
 * @return array|false 成功した場合はランキング情報の配列. [ [ 'id' => '投稿ID', 'count' => '表示回数' ], [], [] ..... ] の形式で返される。
 */
function mgnga_set_ranking( $range = 'custom' ) {
	$config = mgnga_get_config();

	if ( false === $config && 'custom' === $range ) {
		error_log( '正しく設定が登録されていません。' );
		return false;
	}

	$units = mgnga_get_time_unit();

	$end_date = time();
	switch ( $range ) {
		case 'day' :
		case 'week' :
		case 'month' :
			$start_date = time() - ( 1 * $units[ $range ] );
			$transient_id = MGNGA_PLUGIN_DOMAIN . '_' . $range;
			break;
		default :
			$start_date = time() - ( (int)$config['period_num'] * $units[ $config['period_unit'] ] );
			$transient_id = MGNGA_PLUGIN_DOMAIN;
			break;
	}

	$rs = GA_Access::report( date( 'Y-m-d', $start_date ), date( 'Y-m-d', $end_date ) );
	$reports = array_shift( $rs );
	if ( ! $reports instanceof Report ) {
		$r = get_transient( $transient_id . '_long' );
		error_log( 'GoogleAnalyticsのレポート取得に失敗しました。' );
		return count( $r ) > 0 ? $r : [];
	}

	$id_ranking = [];
	foreach ( $reports->getData()->getRows() as $report ) {
		$path = $report->getDimensions()[0];
		$count = $report->getMetrics()[0]->values[0];

		$post_id = url_to_postid( $path );

		if ( 0 === $post_id ) {
			$post_id = mgnga_url_to_postid( $path );
		}

		if ( 0 === $post_id ) {
			continue;
		}

		$id_ranking[] = [
			'id'    => $post_id,
			'count' => $count,
		];
	}

	if ( count( $id_ranking ) < 1 ) {
		return get_transient( $transient_id . '_long' );
	}

	delete_transient( $transient_id );
	delete_transient( $transient_id . '_long' );
	set_transient( $transient_id, $id_ranking, intval( (int)$config['expiration_num'] * $units[ $config['expiration_unit'] ] ) );
	set_transient( $transient_id . '_long', $id_ranking, YEAR_IN_SECONDS );
	return $id_ranking;
}

/**
 * ランキング情報を取得する
 *
 * トランジェントの有効期限内の場合は、トランジェントの情報を使用する
 *
 * @param string $range 取得するランキングの期間
 *
 * @return array|false 成功した場合はランキング情報の配列. [ [ 'id' => '投稿ID', 'count' => '表示回数' ], [], [] ..... ] の形式で返される。
 */
function mgnga_get_ranking( $range = 'custom' ) {
	switch ( $range ) {
		case 'day' :
		case 'week' :
		case 'month' :
			$transient_id = MGNGA_PLUGIN_DOMAIN . '_' . $range;
			break;
		default :
			$transient_id = MGNGA_PLUGIN_DOMAIN;
			break;
	}

	$ids = get_transient( $transient_id );

	if ( $ids !== false ) {
		return $ids;
	} else {
		return mgnga_set_ranking( $range );
	}
}

/**
 * ランキングのID配列を取得する
 *
 * @return array mgnga_get_ranking()で取得した情報の'id'のみの配列
 */
function mgnga_ranking_id( $range = 'custom' ) {
	return array_column( mgnga_get_ranking( $range ), 'id' );
}

/**
 * カスタム投稿タイプなど、標準のurl_to_postidでは取得できない投稿IDを取得する
 *
 * via Simple GA Ranking
 *
 * @link http://simple-ga-ranking.org/ja/
 * @param $url
 *
 * @return int
 */
function mgnga_url_to_postid($url)
{
	global $wp_rewrite;

	$url = apply_filters('url_to_postid', $url);

	// First, check to see if there is a 'p=N' or 'page_id=N' to match against
	if ( preg_match('#[?&](p|page_id|attachment_id)=(\d+)#', $url, $values) )	{
		$id = absint($values[2]);
		if ( $id )
			return $id;
	}

	// Check to see if we are using rewrite rules
	$rewrite = $wp_rewrite->wp_rewrite_rules();

	// Not using rewrite rules, and 'p=N' and 'page_id=N' methods failed, so we're out of options
	if ( empty($rewrite) )
		return 0;

	// Get rid of the #anchor
	$url_split = explode('#', $url);
	$url = $url_split[0];

	// Get rid of URL ?query=string
	$url_split = explode('?', $url);
	$url = $url_split[0];

	// Add 'www.' if it is absent and should be there
	if ( false !== strpos(home_url(), '://www.') && false === strpos($url, '://www.') )
		$url = str_replace('://', '://www.', $url);

	// Strip 'www.' if it is present and shouldn't be
	if ( false === strpos(home_url(), '://www.') )
		$url = str_replace('://www.', '://', $url);

	// Strip 'index.php/' if we're not using path info permalinks
	if ( !$wp_rewrite->using_index_permalinks() )
		$url = str_replace('index.php/', '', $url);

	if ( false !== strpos($url, home_url()) ) {
		// Chop off http://domain.com
		$url = str_replace(home_url(), '', $url);
	} else {
		// Chop off /path/to/blog
		$home_path = parse_url(home_url());
		$home_path = isset( $home_path['path'] ) ? $home_path['path'] : '' ;
		$url = str_replace($home_path, '', $url);
	}

	// Trim leading and lagging slashes
	$url = trim($url, '/');

	$request = $url;
	// Look for matches.
	$request_match = $request;
	foreach ( (array)$rewrite as $match => $query) {
		// If the requesting file is the anchor of the match, prepend it
		// to the path info.
		if ( !empty($url) && ($url != $request) && (strpos($match, $url) === 0) )
			$request_match = $url . '/' . $request;

		if ( preg_match("!^$match!", $request_match, $matches) ) {
			// Got a match.
			// Trim the query of everything up to the '?'.
			$query = preg_replace("!^.+\?!", '', $query);

			// Substitute the substring matches into the query.
			$query = addslashes(WP_MatchesMapRegex::apply($query, $matches));

			// Filter out non-public query vars
			global $wp;
			parse_str($query, $query_vars);
			$query = array();
			foreach ( (array) $query_vars as $key => $value ) {
				if ( in_array($key, $wp->public_query_vars) )
					$query[$key] = $value;
			}

			// Taken from class-wp.php
			foreach ( $GLOBALS['wp_post_types'] as $post_type => $t )
				if ( $t->query_var )
					$post_type_query_vars[$t->query_var] = $post_type;

			foreach ( $wp->public_query_vars as $wpvar ) {
				if ( isset( $wp->extra_query_vars[$wpvar] ) )
					$query[$wpvar] = $wp->extra_query_vars[$wpvar];
				elseif ( isset( $_POST[$wpvar] ) )
					$query[$wpvar] = $_POST[$wpvar];
				elseif ( isset( $_GET[$wpvar] ) )
					$query[$wpvar] = $_GET[$wpvar];
				elseif ( isset( $query_vars[$wpvar] ) )
					$query[$wpvar] = $query_vars[$wpvar];

				if ( !empty( $query[$wpvar] ) ) {
					if ( ! is_array( $query[$wpvar] ) ) {
						$query[$wpvar] = (string) $query[$wpvar];
					} else {
						foreach ( $query[$wpvar] as $vkey => $v ) {
							if ( !is_object( $v ) ) {
								$query[$wpvar][$vkey] = (string) $v;
							}
						}
					}

					if ( isset($post_type_query_vars[$wpvar] ) ) {
						$query['post_type'] = $post_type_query_vars[$wpvar];
						$query['name'] = $query[$wpvar];
					}
				}
			}

			// Do the query
			$query = new WP_Query($query);
			if ( !empty($query->posts) && $query->is_singular )
				return $query->post->ID;
			else
				return 0;
		}
	}
	return 0;
}

/**
 * 設定項目の時間単位を実数に変換するための配列を返す
 *
 * @return array
 */
function mgnga_get_time_unit() {
	return [
		'day'   => DAY_IN_SECONDS,
		'week'  => WEEK_IN_SECONDS,
		'month' => 30 * DAY_IN_SECONDS,
		'year'  => YEAR_IN_SECONDS,
	];

}

/**
 * コンフィグが正しく設定されているかのチェック
 *
 * @param $config
 *
 * @return bool
 */
function mgnga_check_config( $config ) {
	if ( ! isset( $config['service_account'] ) || ! is_array( $config['service_account'] ) ) { return false; }

	if ( ! isset( $config['view_id'] ) || ! is_numeric( $config['view_id'] ) ) { return false; }

	return true;
}

/**
 * コンフィグを取得する
 *
 * @return false|mixed|void
 */
function mgnga_get_config() {
	$config = get_option( 'mgnga_ranking_settings', true );

	return mgnga_check_config( $config ) ? $config : false;
}
