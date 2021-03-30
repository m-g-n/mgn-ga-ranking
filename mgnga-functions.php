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
	$reports = GA_Access::report( '2021-02-01', '2021-02-28' )[0];
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
	set_transient( MGNGA_PLUGIN_DOMAIN, $id_ranking, intval( DAY_IN_SECONDS ) );
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
