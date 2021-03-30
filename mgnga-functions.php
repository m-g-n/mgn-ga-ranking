<?php
/**
 * 処理関数
 *
 * @package mgn-ga-ranking
 * @since 2021.03.30
 */

use Google_Service_AnalyticsReporting_report as Report;
use MGNGA\GAAccessRanking\GA_Access;

function mgnga_set_ranking() {
	$settings = get_option( 'mgnga_ranking_settings', true );

	// TODO: 設定がちゃんとされているかの確認

	$units = [
		'day'   => DAY_IN_SECONDS,
		'week'  => WEEK_IN_SECONDS,
		'month' => 30 * DAY_IN_SECONDS,
		'year'  => YEAR_IN_SECONDS,
	];

	$end_date = time();
	$start_date = time() - ( (int)$settings['period_num'] * $units[ $settings['period_unit'] ] );

	$reports = GA_Access::report( date( 'Y-m-d', $start_date ), date( 'Y-m-d', $end_date ) )[0];
	if ( ! $reports instanceof Report ) {
		error_log( 'GoogleAnalyticsのレポート取得に失敗しました。' );
		exit();
	}

	$id_ranking = [];
	foreach ( $reports->getData()->getRows() as $report ) {
		$path = $report->getDimensions()[0];
		$count = $report->getMetrics()[0]->values[0];

		$post_id = url_to_postid( $path );

		if ( 0 === $post_id ) {
			continue;
		}

		$id_ranking[] = [
			'id'    => $post_id,
			'count' => $count,
		];
	}

	delete_transient( MGNGA_PLUGIN_DOMAIN );
	set_transient( MGNGA_PLUGIN_DOMAIN, $id_ranking, intval( (int)$settings['expiration_num'] * $units[ $settings['expiration_unit'] ] ) );
	return $id_ranking;
}

function mgnga_get_ranking() {
	$ids = get_transient( MGNGA_PLUGIN_DOMAIN );

	if ( $ids !== false ) {
		return $ids;
	} else {
		return mgnga_set_ranking();
	}
}

function mgnga_ranking_id() {
	return array_column( mgnga_get_ranking(), 'id' );
}
