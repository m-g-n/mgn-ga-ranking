<?php
/**
 * GoogleAnalyticsアクセスクラス
 *
 * @package mgn-ga-ranking
 * @since 2021.03.16
 */

namespace MGNGA\GAAccessRanking;

use Google;
use Google_Client as Client;
use Google_Service_AnalyticsReporting_DateRange as DataRange;
use Google_Service_AnalyticsReporting_Metric as Metric;
use Google_Service_AnalyticsReporting_Dimension as Dimension;
use Google_Service_AnalyticsReporting_OrderBy as OrderBy;
use Google_Service_AnalyticsReporting_ReportRequest as Request;
use Google_Service_AnalyticsReporting as Analytics;
use Google_Service_AnalyticsReporting_GetReportsRequest as GetReport;

require_once MGNGA_PLUGIN_DIR . '/vendor/autoload.php';

class GA_Access {
	private $startDate;
	private $endDate;
	private $settings;
	private $key;
	private $client;
	private $dateRenge;
	private $pageViews;
	private $dimension;
	private $orderBy;
	private $request;

	public function __construct( $startDate, $endDate ) {
		$this->startDate = $startDate;
		$this->endDate = $endDate;
		$this->setSettings();
		$this->setKey();
	}

	/**
	 * 設定取得
	 */
	private function setSettings() {
		$this->settings = get_option( 'mgnga_ranking_settings', true );
	}

	/**
	 * キー取得
	 */
	private function setKey() {
		$this->key = $this->settings['service_account'] ?? null;
	}

	/**
	 * クライアント設定
	 *
	 * @return bool 設定が正しく行われたか
	 */
	private function setClient() {
		$this->client = new Client();
		$this->client->setApplicationName( MGNGA_PLUGIN_DOMAIN );
		try {
			$this->client->setAuthConfig( $this->key );
		} catch ( Google\Exception $e ) {
			error_log( print_r( $e, true ) );

			return false;
		}

		$this->client->setScopes( [ 'https://www.googleapis.com/auth/analytics.readonly' ] );

		return true;
	}

	/**
	 * 取得データの期間設定
	 *
	 * @return $this 自身を返す（設定時にメソッドチェーンのように記述できるようにする）
	 */
	private function setDateRange() {
		$this->dateRenge = new DataRange();
		$this->dateRenge->setStartDate( $this->startDate );
		$this->dateRenge->setEndDate( $this->endDate );

		return $this;
	}

	/**
	 * Metric設定
	 *
	 * ページビューを取得してくるように設定している
	 *
	 * @return $this 自身を返す（設定時にメソッドチェーンのように記述できるようにする）
	 */
	private function setPageViews() {
		$this->pageViews = new Metric();
		$this->pageViews->setExpression( 'ga:pageviews' );

		return $this;
	}

	/**
	 * Dimension設定
	 *
	 * ページパスの情報を返すよう設定している.
	 *
	 * @return $this 自身を返す（設定時にメソッドチェーンのように記述できるようにする）
	 */
	private function setDimension() {
		$this->dimension = new Dimension();
		$this->dimension->setName( 'ga:pagePath' );

		return $this;
	}

	/**
	 * 並び順設定
	 *
	 * @return $this 自身を返す（設定時にメソッドチェーンのように記述できるようにする）
	 */
	private function setOrderBy() {
		$this->orderBy = new OrderBy();
		$this->orderBy->setFieldName( 'ga:pageviews' );
		$this->orderBy->setOrderType( 'VALUE' );
		$this->orderBy->setSortOrder( 'DESCENDING' );

		return $this;
	}

	/**
	 * レポートリクエスト設定
	 *
	 * @return $this 自身を返す（設定時にメソッドチェーンのように記述できるようにする）
	 */
	private function setReportingRequest() {
		$this->setDateRange()->setPageViews()->setDimension()->setOrderBy();

		$this->request = new Request();
		$this->request->setViewId( $this->settings['view_id'] );
		$this->request->setDateRanges( $this->dateRenge );
		$this->request->setMetrics( [ $this->pageViews ] );
		$this->request->setDimensions( $this->dimension );
		$this->request->setOrderBys( $this->orderBy );

		return $this;
	}

	/**
	 * レポート取得処理
	 *
	 * @return array|Google_Service_AnalyticsReporting_Report[] 成功した場合は取得したレポート情報を返す.失敗した場合は空の配列を返す
	 */
	private function fetchReport() {
		if ( ! $this->setClient() ) {
			return [];
		}

		$this->setReportingRequest();

		$analytics = new Analytics( $this->client );

		$body = new GetReport();
		$body->setReportRequests( [ $this->request ] );
		try {
			$reports = $analytics->reports->batchGet( $body );
		} catch ( Google\Exception $e ) {
			error_log( print_r( $e, true ) );

			return [];
		}

		return $reports->getReports();
	}

	/**
	 * レポート取得処理(静的)
	 *
	 * インスタンスを作成せずレポートを取得する
	 *
	 * @return array|Google_Service_AnalyticsReporting_Report[] 成功した場合は取得したレポート情報を返す.失敗した場合は空の配列を返す
	 */
	static function report( $startDate, $endDate ) {
		$instance = new self( $startDate, $endDate );
		return $instance->fetchReport();
	}
}
