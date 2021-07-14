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
 * マルチサイトだったときに、その子サイトのIDとパスを取得
 */
function children_sites_data_array() {
	if ( is_multisite() ) {
		// マルチサイトの各サイト情報を取得
		$site_obj = get_sites();

		// 配列を宣言
		$site_array = array();

		// 各サイトの情報を個別に取得し、配列に代入
		foreach ( $site_obj as $site ) {
			$site_array[ $site->blog_id ] = $site->path;
		}

		// 取得した配列の中から親サイトの情報を削除
		unset( $site_array['1'] );

		return $site_array;
	}
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
		case 'day':
		case 'week':
		case 'month':
			$start_date   = time() - ( 1 * $units[ $range ] );
			$transient_id = MGNGA_PLUGIN_DOMAIN . '_' . $range;
			break;
		default:
			$start_date   = time() - ( (int) $config['period_num'] * $units[ $config['period_unit'] ] );
			$transient_id = MGNGA_PLUGIN_DOMAIN;
			break;
	}

	$rs      = GA_Access::report( date( 'Y-m-d', $start_date ), date( 'Y-m-d', $end_date ) );
	$reports = array_shift( $rs );
	if ( ! $reports instanceof Report ) {
		$r = get_transient( $transient_id . '_long' );
		error_log( 'GoogleAnalyticsのレポート取得に失敗しました。' );
		return count( $r ) > 0 ? $r : array();
	}

	$id_ranking = array();
	foreach ( $reports->getData()->getRows() as $report ) {
		$path  = $report->getDimensions()[0];
		$count = $report->getMetrics()[0]->values[0];

		$post_id = url_to_postid( $path );

		if ( 0 === $post_id ) {
			$post_id = mgnga_url_to_postid( $path );
		}

		if ( 0 === $post_id ) {
			continue;
		}

		$id_ranking[] = array(
			'id'    => $post_id,
			'count' => $count,
		);
	}

	if ( count( $id_ranking ) < 1 ) {
		return get_transient( $transient_id . '_long' );
	}

	delete_transient( $transient_id );
	delete_transient( $transient_id . '_long' );
	set_transient( $transient_id, $id_ranking, intval( (int) $config['expiration_num'] * $units[ $config['expiration_unit'] ] ) );
	set_transient( $transient_id . '_long', $id_ranking, YEAR_IN_SECONDS );
	return $id_ranking;
}

/**
 * GoogleAnalyticsReportを取得しランキングの記事 ID とその記事のブログ ID をトランジェント内に保持する
 * // アクションフック `mgnga_cron_task_hook` で利用
 *
 * @param string $range 取得するランキングの期間
 *
 * @return array|false 成功した場合はランキング情報の配列. [ [ 'post_id' => '投稿ID', 'blog_id' => 'ブログID' ], [], [] ..... ] の形式で返される。
 */
function mgnga_set_ranking_multisite_data( $range = 'custom' ) {
	$config = mgnga_get_config();
	if ( false === $config && 'custom' === $range ) {
		error_log( '正しく設定が登録されていません。' );
		return false;
	}
	$units    = mgnga_get_time_unit();
	$end_date = time();
	switch ( $range ) {
		case 'day':
		case 'week':
		case 'month':
			$start_date    = time() - ( 1 * $units[ $range ] );
			$transient_multisite_data = MGNGA_PLUGIN_DOMAIN . '_multisite_data_' . $range;
			break;
		default:
			$start_date    = time() - ( (int) $config['period_num'] * $units[ $config['period_unit'] ] );
			$transient_multisite_data = MGNGA_PLUGIN_DOMAIN . '_multisite_data';
			break;
	}

	$rs      = GA_Access::report( date( 'Y-m-d', $start_date ), date( 'Y-m-d', $end_date ) );
	$reports = array_shift( $rs );
	if ( ! $reports instanceof Report ) {
		$r = get_transient( $transient_multisite_data . '_long' );
		error_log( 'GoogleAnalyticsのレポート取得に失敗しました。' );
		return count( $r ) > 0 ? $r : array();
	}

	// 配列を宣言
	$multi_ranking = array();

	// GA からデータを取得
	foreach ( $reports->getData()->getRows() as $report ) {
		// 記事のパスを取得
		$path = $report->getDimensions()[0];

		// マルチサイトだったら
		if ( is_multisite() ) {
			// マルチサイトでランキングに掲載しないデータを除外

			// note: ここからは表示する記事のパーマリンクに `/article/` が含まれる前提でハードコードされていることに注意

			// path に `/article/` を含まない場合、URL が画像ページだと思われる場合、および2ページ目以降の場合は除外
			if ( empty( preg_match( '%/article/%', $path ) ) || ! empty( preg_match( '%/article/[^/]+?/[^/]+?%', $path ) ) || ! empty( preg_match( '%\?page\=%', $path ) ) ) {
				continue;
			}

			// マルチサイトの子サイトの情報の配列を変数に代入
			$children_sites_data_array = children_sites_data_array();

			// マルチサイトの子サイトの投稿記事のパスに一致しなかったら除外
			// `/article/` の前に文字列があったら（マルチサイトの子サイトだったら）
			if ( ! empty( preg_match( '%/(\w+)\/article/%', $path ) ) ) {
				// その文字列を取得
				preg_match( '%/(\w+)\/article/%', $path, $prev_match );
				// `/article` より前の文字列をパスに変換
				$url_path = '/' . $prev_match[1] . '/';

				// 子サイトの情報の配列と比較して、その記事のブログ ID を取得
				$blog_id = array_search( $url_path, $children_sites_data_array );
			// `/article/` の前に文字列がなかったら（旧サイトのデータか、あるいは親サイトのデータ）除外
			} else {
				continue;
			}

			// `/article/` の直後に文字列があったら
			if ( ! empty( preg_match( '%/article\/(\w+)/%', $path ) ) ) {
				// その文字列を取得
				preg_match( '%/article\/(\w+)/%', $path, $next_match );

				// `article/` 以降の文字列が（文字列(string)型の）数値だったら
				if ( preg_match( '/^[0-9]+$/', $next_match[1] ) ) {
					// $find_id に代入
					$find_id = (int) $next_match[1];
				// `article/` 以降の文字列が数値ではなかったら（固定ページ・カテゴリーページなど）除外
				} else {
					continue;
				}
			}
		// シングルサイトなら
		} else {
			// パスから記事 ID を取得
			$find_id = url_to_postid( $path );
			$blog_id = null;
		}

		$multi_ranking[] = array(
			'post_id' => $find_id,
			'blog_id' => $blog_id,
		);
	}

	// if ( count( $multi_ranking ) < 1 ) {
	// return get_transient( $transient_id . '_long' );
	// }

	// delete_transient( $transient_multisite_data );
	// delete_transient( $transient_multisite_data . '_long' );
	// set_transient( $transient_multisite_data, $multi_ranking, intval( (int) $config['expiration_num'] * $units[ $config['expiration_unit'] ] ) );
	// set_transient( $transient_multisite_data . '_long', $multi_ranking, YEAR_IN_SECONDS );

	var_dump( $multi_ranking );
	// return $multi_ranking;
}

add_shortcode( 'ranking_url', 'mgnga_set_ranking_multisite_data' );






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
		case 'day':
		case 'week':
		case 'month':
			$transient_id = MGNGA_PLUGIN_DOMAIN . '_' . $range;
			break;
		default:
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
 * ランキング記事のURLを取得する
 *
 * トランジェントの有効期限内の場合は、トランジェントの情報を使用する
 *
 * @param string $range 取得するランキングの期間
 *
 * @return array|false 成功した場合はランキング情報の配列. [ [ 'id' => '投稿ID', 'path' => '記事URL' ], [], [] ..... ] の形式で返される。
 *//*
function mgnga_get_ranking_url( $range = 'custom' ) {
	switch ( $range ) {
		case 'day':
		case 'week':
		case 'month':
			$transient_url = MGNGA_PLUGIN_DOMAIN . '_url_' . $range;
			break;
		default:
			$transient_url = MGNGA_PLUGIN_DOMAIN . '_url';
			break;
	}

	$urls = get_transient( $transient_url );

	if ( $urls !== false ) {
		return $urls;
	} else {
		return mgnga_set_ranking_url( $range );
	}
}

/**
 * ランキングのID配列を取得する
 *
 * @return array mgnga_get_ranking()で取得した情報の'id'のみの配列
 */
function mgnga_ranking_id( $range = 'custom' ) {
	if ( false !== mgnga_get_ranking( $range ) ) {
		return array_column( mgnga_get_ranking( $range ), 'id' );
	} else {
		return array();
	}
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
function mgnga_url_to_postid( $url ) {
	global $wp_rewrite;

	$url = apply_filters( 'url_to_postid', $url );

	// First, check to see if there is a 'p=N' or 'page_id=N' to match against
	if ( preg_match( '#[?&](p|page_id|attachment_id)=(\d+)#', $url, $values ) ) {
		$id = absint( $values[2] );
		if ( $id ) {
			return $id;
		}
	}

	// Check to see if we are using rewrite rules
	$rewrite = $wp_rewrite->wp_rewrite_rules();

	// Not using rewrite rules, and 'p=N' and 'page_id=N' methods failed, so we're out of options
	if ( empty( $rewrite ) ) {
		return 0;
	}

	// Get rid of the #anchor
	$url_split = explode( '#', $url );
	$url       = $url_split[0];

	// Get rid of URL ?query=string
	$url_split = explode( '?', $url );
	$url       = $url_split[0];

	// Add 'www.' if it is absent and should be there
	if ( false !== strpos( home_url(), '://www.' ) && false === strpos( $url, '://www.' ) ) {
		$url = str_replace( '://', '://www.', $url );
	}

	// Strip 'www.' if it is present and shouldn't be
	if ( false === strpos( home_url(), '://www.' ) ) {
		$url = str_replace( '://www.', '://', $url );
	}

	// Strip 'index.php/' if we're not using path info permalinks
	if ( ! $wp_rewrite->using_index_permalinks() ) {
		$url = str_replace( 'index.php/', '', $url );
	}

	if ( false !== strpos( $url, home_url() ) ) {
		// Chop off http://domain.com
		$url = str_replace( home_url(), '', $url );
	} else {
		// Chop off /path/to/blog
		$home_path = parse_url( home_url() );
		$home_path = isset( $home_path['path'] ) ? $home_path['path'] : '';
		$url       = str_replace( $home_path, '', $url );
	}

	// Trim leading and lagging slashes
	$url = trim( $url, '/' );

	$request = $url;
	// Look for matches.
	$request_match = $request;
	foreach ( (array) $rewrite as $match => $query ) {
		// If the requesting file is the anchor of the match, prepend it
		// to the path info.
		if ( ! empty( $url ) && ( $url != $request ) && ( strpos( $match, $url ) === 0 ) ) {
			$request_match = $url . '/' . $request;
		}

		if ( preg_match( "!^$match!", $request_match, $matches ) ) {
			// Got a match.
			// Trim the query of everything up to the '?'.
			$query = preg_replace( '!^.+\?!', '', $query );

			// Substitute the substring matches into the query.
			$query = addslashes( WP_MatchesMapRegex::apply( $query, $matches ) );

			// Filter out non-public query vars
			global $wp;
			parse_str( $query, $query_vars );
			$query = array();
			foreach ( (array) $query_vars as $key => $value ) {
				if ( in_array( $key, $wp->public_query_vars ) ) {
					$query[ $key ] = $value;
				}
			}

			// Taken from class-wp.php
			foreach ( $GLOBALS['wp_post_types'] as $post_type => $t ) {
				if ( $t->query_var ) {
					$post_type_query_vars[ $t->query_var ] = $post_type;
				}
			}

			foreach ( $wp->public_query_vars as $wpvar ) {
				if ( isset( $wp->extra_query_vars[ $wpvar ] ) ) {
					$query[ $wpvar ] = $wp->extra_query_vars[ $wpvar ];
				} elseif ( isset( $_POST[ $wpvar ] ) ) {
					$query[ $wpvar ] = $_POST[ $wpvar ];
				} elseif ( isset( $_GET[ $wpvar ] ) ) {
					$query[ $wpvar ] = $_GET[ $wpvar ];
				} elseif ( isset( $query_vars[ $wpvar ] ) ) {
					$query[ $wpvar ] = $query_vars[ $wpvar ];
				}

				if ( ! empty( $query[ $wpvar ] ) ) {
					if ( ! is_array( $query[ $wpvar ] ) ) {
						$query[ $wpvar ] = (string) $query[ $wpvar ];
					} else {
						foreach ( $query[ $wpvar ] as $vkey => $v ) {
							if ( ! is_object( $v ) ) {
								$query[ $wpvar ][ $vkey ] = (string) $v;
							}
						}
					}

					if ( isset( $post_type_query_vars[ $wpvar ] ) ) {
						$query['post_type'] = $post_type_query_vars[ $wpvar ];
						$query['name']      = $query[ $wpvar ];
					}
				}
			}

			// Do the query
			$query = new WP_Query( $query );
			if ( ! empty( $query->posts ) && $query->is_singular ) {
				return $query->post->ID;
			} else {
				return 0;
			}
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
	return array(
		'day'   => DAY_IN_SECONDS,
		'week'  => WEEK_IN_SECONDS,
		'month' => 30 * DAY_IN_SECONDS,
		'year'  => YEAR_IN_SECONDS,
	);

}

/**
 * コンフィグが正しく設定されているかのチェック
 *
 * @param $config
 *
 * @return bool
 */
function mgnga_check_config( $config ) {
	if ( ! isset( $config['service_account'] ) || ! is_array( $config['service_account'] ) ) {
		return false; }

	if ( ! isset( $config['view_id'] ) || ! is_numeric( $config['view_id'] ) ) {
		return false; }

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
