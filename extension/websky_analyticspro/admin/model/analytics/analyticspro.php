<?php
namespace Opencart\Admin\Model\Extension\WebskyAnalyticspro\Analytics;

class Analyticspro extends \Opencart\System\Engine\Model {
	private const EVENT_TABLE = 'websky_analytics_event';
	private const SESSION_TABLE = 'websky_analytics_session';
	private const CUSTOMER_TABLE = 'websky_analytics_customer';
	private const PRODUCT_TABLE = 'websky_analytics_product';
	private const CAMPAIGN_TABLE = 'websky_analytics_campaign';

	public function install(): void {
		$charset = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . self::EVENT_TABLE . "` (
			`event_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			`store_id` INT NOT NULL DEFAULT 0,
			`session_id` CHAR(64) NOT NULL DEFAULT '',
			`visitor_id` CHAR(64) NOT NULL DEFAULT '',
			`customer_id` INT NOT NULL DEFAULT 0,
			`event_name` VARCHAR(64) NOT NULL,
			`page_url` VARCHAR(1024) NOT NULL DEFAULT '',
			`page_title` VARCHAR(255) NOT NULL DEFAULT '',
			`referrer` VARCHAR(1024) NOT NULL DEFAULT '',
			`source` VARCHAR(128) NOT NULL DEFAULT 'direct',
			`medium` VARCHAR(128) NOT NULL DEFAULT '',
			`campaign` VARCHAR(255) NOT NULL DEFAULT '',
			`product_id` INT NOT NULL DEFAULT 0,
			`order_id` INT NOT NULL DEFAULT 0,
			`quantity` INT NOT NULL DEFAULT 0,
			`value` DECIMAL(15,4) NOT NULL DEFAULT 0,
			`currency` VARCHAR(8) NOT NULL DEFAULT '',
			`data_json` MEDIUMTEXT NULL,
			`date_added` DATETIME NOT NULL,
			PRIMARY KEY (`event_id`), KEY `event_name_date` (`event_name`,`date_added`), KEY `session_date` (`session_id`,`date_added`), KEY `product_event` (`product_id`,`event_name`), KEY `order_event` (`order_id`,`event_name`), KEY `source_date` (`source`,`date_added`)
		) $charset");
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . self::SESSION_TABLE . "` (
			`session_id` CHAR(64) NOT NULL,
			`store_id` INT NOT NULL DEFAULT 0,
			`visitor_id` CHAR(64) NOT NULL DEFAULT '',
			`customer_id` INT NOT NULL DEFAULT 0,
			`landing_page` VARCHAR(1024) NOT NULL DEFAULT '',
			`source` VARCHAR(128) NOT NULL DEFAULT 'direct',
			`medium` VARCHAR(128) NOT NULL DEFAULT '',
			`campaign` VARCHAR(255) NOT NULL DEFAULT '',
			`pageviews` INT NOT NULL DEFAULT 0,
			`cart_adds` INT NOT NULL DEFAULT 0,
			`checkout_started` TINYINT(1) NOT NULL DEFAULT 0,
			`purchased` TINYINT(1) NOT NULL DEFAULT 0,
			`revenue` DECIMAL(15,4) NOT NULL DEFAULT 0,
			`started_at` DATETIME NOT NULL,
			`last_seen` DATETIME NOT NULL,
			PRIMARY KEY (`session_id`), KEY `session_date` (`started_at`), KEY `visitor_id` (`visitor_id`), KEY `purchased` (`purchased`)
		) $charset");
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . self::CUSTOMER_TABLE . "` (
			`visitor_id` CHAR(64) NOT NULL,
			`customer_id` INT NOT NULL DEFAULT 0,
			`first_session_id` CHAR(64) NOT NULL DEFAULT '',
			`first_source` VARCHAR(128) NOT NULL DEFAULT 'direct',
			`first_seen` DATETIME NOT NULL,
			`last_seen` DATETIME NOT NULL,
			`session_count` INT NOT NULL DEFAULT 0,
			`order_count` INT NOT NULL DEFAULT 0,
			`revenue` DECIMAL(15,4) NOT NULL DEFAULT 0,
			PRIMARY KEY (`visitor_id`), KEY `customer_id` (`customer_id`), KEY `last_seen` (`last_seen`)
		) $charset");
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . self::PRODUCT_TABLE . "` (
			`product_id` INT NOT NULL,
			`views` BIGINT UNSIGNED NOT NULL DEFAULT 0,
			`cart_adds` BIGINT UNSIGNED NOT NULL DEFAULT 0,
			`orders` BIGINT UNSIGNED NOT NULL DEFAULT 0,
			`revenue` DECIMAL(15,4) NOT NULL DEFAULT 0,
			`date_modified` DATETIME NOT NULL,
			PRIMARY KEY (`product_id`)
		) $charset");
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . self::CAMPAIGN_TABLE . "` (
			`campaign_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			`source` VARCHAR(128) NOT NULL DEFAULT '',
			`medium` VARCHAR(128) NOT NULL DEFAULT '',
			`campaign` VARCHAR(255) NOT NULL DEFAULT '',
			`sessions` BIGINT UNSIGNED NOT NULL DEFAULT 0,
			`orders` BIGINT UNSIGNED NOT NULL DEFAULT 0,
			`revenue` DECIMAL(15,4) NOT NULL DEFAULT 0,
			`date_modified` DATETIME NOT NULL,
			PRIMARY KEY (`campaign_id`), UNIQUE KEY `campaign_key` (`source`,`medium`,`campaign`)
		) $charset");
	}

	public function uninstall(): void {
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . self::CAMPAIGN_TABLE . "`, `" . DB_PREFIX . self::PRODUCT_TABLE . "`, `" . DB_PREFIX . self::CUSTOMER_TABLE . "`, `" . DB_PREFIX . self::SESSION_TABLE . "`, `" . DB_PREFIX . self::EVENT_TABLE . "`");
	}

	public function getSummary(int $store_id, string $from, string $to): array {
		$event = DB_PREFIX . self::EVENT_TABLE;
		$session = DB_PREFIX . self::SESSION_TABLE;
		$where = "store_id = '" . (int)$store_id . "' AND date_added >= '" . $this->db->escape($from) . "' AND date_added < '" . $this->db->escape($to) . "'";
		$summary = [];
		$q = $this->db->query("SELECT COUNT(DISTINCT visitor_id) AS visitors, COUNT(DISTINCT session_id) AS sessions, SUM(event_name = 'purchase') AS orders, COALESCE(SUM(CASE WHEN event_name = 'purchase' THEN value ELSE 0 END),0) AS revenue, SUM(event_name = 'page_view') AS page_views, SUM(event_name = 'add_to_cart') AS cart_adds FROM `" . $event . "` WHERE $where");
		$summary = $q->row ?: [];
		$summary['new_users'] = (int)$this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . self::CUSTOMER_TABLE . "` WHERE first_seen >= '" . $this->db->escape($from) . "' AND first_seen < '" . $this->db->escape($to) . "'")->row['total'];
		$summary['returning_users'] = max(0, (int)$summary['visitors'] - (int)$summary['new_users']);
		$summary['conversion_rate'] = (int)$summary['sessions'] > 0 ? round(((int)$summary['orders'] / (int)$summary['sessions']) * 100, 2) : 0;
		return $summary;
	}

	public function getTopProducts(int $store_id, string $from, string $to, int $limit = 10): array {
		$limit = max(1, min(100, $limit));
		$q = $this->db->query("SELECT e.product_id, COALESCE(pd.name, CONCAT('#', e.product_id)) AS name, SUM(e.event_name = 'product_view') AS views, SUM(e.event_name = 'add_to_cart') AS cart_adds, SUM(e.event_name = 'purchase') AS orders, COALESCE(SUM(CASE WHEN e.event_name = 'purchase' THEN e.value ELSE 0 END),0) AS revenue FROM `" . DB_PREFIX . self::EVENT_TABLE . "` e LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (pd.product_id=e.product_id AND pd.language_id='" . (int)$this->config->get('config_language_id') . "') WHERE e.store_id='" . (int)$store_id . "' AND e.date_added >= '" . $this->db->escape($from) . "' AND e.date_added < '" . $this->db->escape($to) . "' AND e.product_id > 0 GROUP BY e.product_id, pd.name ORDER BY views DESC LIMIT " . $limit);
		return $q->rows;
	}

	public function getTrafficSources(int $store_id, string $from, string $to, int $limit = 20): array {
		$limit = max(1, min(100, $limit));
		$q = $this->db->query("SELECT source, medium, campaign, COUNT(DISTINCT session_id) AS sessions, SUM(event_name='purchase') AS orders, COALESCE(SUM(CASE WHEN event_name='purchase' THEN value ELSE 0 END),0) AS revenue FROM `" . DB_PREFIX . self::EVENT_TABLE . "` WHERE store_id='" . (int)$store_id . "' AND date_added >= '" . $this->db->escape($from) . "' AND date_added < '" . $this->db->escape($to) . "' GROUP BY source, medium, campaign ORDER BY sessions DESC LIMIT " . $limit);
		return $q->rows;
	}

	public function getTopPages(int $store_id, string $from, string $to, int $limit = 20): array {
		$limit = max(1, min(100, $limit));
		$q = $this->db->query("SELECT page_url, MAX(page_title) AS page_title, COUNT(*) AS views, COUNT(DISTINCT session_id) AS sessions FROM `" . DB_PREFIX . self::EVENT_TABLE . "` WHERE store_id='" . (int)$store_id . "' AND event_name='page_view' AND date_added >= '" . $this->db->escape($from) . "' AND date_added < '" . $this->db->escape($to) . "' GROUP BY page_url ORDER BY views DESC LIMIT " . $limit);
		return $q->rows;
	}

	public function getJourneys(int $store_id, string $from, string $to, int $limit = 20): array {
		$limit = max(1, min(100, $limit));
		$q = $this->db->query("SELECT session_id, visitor_id, source, landing_page, pageviews, cart_adds, checkout_started, purchased, revenue, started_at, last_seen FROM `" . DB_PREFIX . self::SESSION_TABLE . "` WHERE store_id='" . (int)$store_id . "' AND started_at >= '" . $this->db->escape($from) . "' AND started_at < '" . $this->db->escape($to) . "' ORDER BY last_seen DESC LIMIT " . $limit);
		return $q->rows;
	}

	public function exportEvents(int $store_id, string $from, string $to): array {
		$q = $this->db->query("SELECT event_id, event_name, visitor_id, session_id, customer_id, page_url, source, medium, campaign, product_id, order_id, quantity, value, currency, date_added FROM `" . DB_PREFIX . self::EVENT_TABLE . "` WHERE store_id='" . (int)$store_id . "' AND date_added >= '" . $this->db->escape($from) . "' AND date_added < '" . $this->db->escape($to) . "' ORDER BY event_id DESC LIMIT 50000");
		return $q->rows;
	}
}
