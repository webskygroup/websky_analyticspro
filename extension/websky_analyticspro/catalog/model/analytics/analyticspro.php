<?php
namespace Opencart\Catalog\Model\Extension\WebskyAnalyticspro\Analytics;

class Analyticspro extends \Opencart\System\Engine\Model {
	private const EVENT_TABLE = 'websky_analytics_event';
	private const SESSION_TABLE = 'websky_analytics_session';
	private const CUSTOMER_TABLE = 'websky_analytics_customer';
	private const PRODUCT_TABLE = 'websky_analytics_product';
	private const CAMPAIGN_TABLE = 'websky_analytics_campaign';

	public function record(array $payload): bool {
		if (!(int)$this->config->get('analytics_analyticspro_store_events')) return false;
		$allowed = ['page_view','product_view','add_to_cart','remove_from_cart','checkout_start','checkout_step','purchase','search','login','signup'];
		$name = preg_replace('/[^a-z0-9_]/', '', strtolower((string)($payload['event_name'] ?? 'page_view')));
		if (!in_array($name, $allowed, true)) return false;
		$session_id = $this->token($payload['session_id'] ?? '', 64);
		$visitor_id = $this->token($payload['visitor_id'] ?? '', 64);
		if (!$session_id) $session_id = $this->token((string)($this->request->cookie['websky_analytics_session'] ?? ''), 64);
		if (!$visitor_id) $visitor_id = $this->token((string)($this->request->cookie['websky_analytics_visitor'] ?? ''), 64);
		if (!$session_id) $session_id = hash('sha256', uniqid('session_', true));
		if (!$visitor_id) $visitor_id = hash('sha256', uniqid('visitor_', true));
		$customer_id = (int)$this->customer->getId();
		$store_id = (int)$this->config->get('config_store_id');
		$url = substr((string)($payload['page_url'] ?? ($this->request->server['HTTP_REFERER'] ?? '')), 0, 1024);
		$referrer = substr((string)($payload['referrer'] ?? ($this->request->server['HTTP_REFERER'] ?? '')), 0, 1024);
		$source = $this->source((string)($payload['source'] ?? ''), $referrer);
		$medium = substr((string)($payload['medium'] ?? ''), 0, 128);
		$campaign = substr((string)($payload['campaign'] ?? ''), 0, 255);
		$product_id = (int)($payload['product_id'] ?? 0);
		$order_id = (int)($payload['order_id'] ?? 0);
		$value = (float)($payload['value'] ?? 0);
		$quantity = (int)($payload['quantity'] ?? 0);
		$currency = substr((string)($payload['currency'] ?? $this->config->get('config_currency')), 0, 8);
		$data = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$now = date('Y-m-d H:i:s');
		$this->db->query("INSERT INTO `" . DB_PREFIX . self::EVENT_TABLE . "` SET store_id='" . $store_id . "', session_id='" . $this->db->escape($session_id) . "', visitor_id='" . $this->db->escape($visitor_id) . "', customer_id='" . $customer_id . "', event_name='" . $this->db->escape($name) . "', page_url='" . $this->db->escape($url) . "', page_title='" . $this->db->escape(substr((string)($payload['page_title'] ?? ''), 0, 255)) . "', referrer='" . $this->db->escape($referrer) . "', source='" . $this->db->escape($source) . "', medium='" . $this->db->escape($medium) . "', campaign='" . $this->db->escape($campaign) . "', product_id='" . $product_id . "', order_id='" . $order_id . "', quantity='" . $quantity . "', value='" . $value . "', currency='" . $this->db->escape($currency) . "', data_json='" . $this->db->escape((string)$data) . "', date_added='" . $now . "'");
		$this->db->query("INSERT INTO `" . DB_PREFIX . self::SESSION_TABLE . "` SET session_id='" . $this->db->escape($session_id) . "', store_id='" . $store_id . "', visitor_id='" . $this->db->escape($visitor_id) . "', customer_id='" . $customer_id . "', landing_page='" . $this->db->escape($url) . "', source='" . $this->db->escape($source) . "', medium='" . $this->db->escape($medium) . "', campaign='" . $this->db->escape($campaign) . "', pageviews='" . ($name === 'page_view' ? 1 : 0) . "', cart_adds='" . ($name === 'add_to_cart' ? 1 : 0) . "', checkout_started='" . ($name === 'checkout_start' ? 1 : 0) . "', purchased='" . ($name === 'purchase' ? 1 : 0) . "', revenue='" . $value . "', started_at='" . $now . "', last_seen='" . $now . "' ON DUPLICATE KEY UPDATE customer_id=VALUES(customer_id), pageviews=pageviews+VALUES(pageviews), cart_adds=cart_adds+VALUES(cart_adds), checkout_started=GREATEST(checkout_started, VALUES(checkout_started)), purchased=GREATEST(purchased, VALUES(purchased)), revenue=revenue+VALUES(revenue), last_seen='" . $now . "'");
		$this->db->query("INSERT INTO `" . DB_PREFIX . self::CUSTOMER_TABLE . "` SET visitor_id='" . $this->db->escape($visitor_id) . "', customer_id='" . $customer_id . "', first_session_id='" . $this->db->escape($session_id) . "', first_source='" . $this->db->escape($source) . "', first_seen='" . $now . "', last_seen='" . $now . "', session_count=1, order_count='" . ($name === 'purchase' ? 1 : 0) . "', revenue='" . $value . "' ON DUPLICATE KEY UPDATE customer_id=VALUES(customer_id), last_seen='" . $now . "', order_count=order_count+VALUES(order_count), revenue=revenue+VALUES(revenue)");
		if ($product_id > 0 && in_array($name, ['product_view','add_to_cart','purchase'], true)) {
			$view = $name === 'product_view' ? 1 : 0; $cart = $name === 'add_to_cart' ? 1 : 0; $order = $name === 'purchase' ? max(1, $quantity) : 0;
			$this->db->query("INSERT INTO `" . DB_PREFIX . self::PRODUCT_TABLE . "` SET product_id='" . $product_id . "', views='" . $view . "', cart_adds='" . $cart . "', orders='" . $order . "', revenue='" . $value . "', date_modified='" . $now . "' ON DUPLICATE KEY UPDATE views=views+VALUES(views), cart_adds=cart_adds+VALUES(cart_adds), orders=orders+VALUES(orders), revenue=revenue+VALUES(revenue), date_modified='" . $now . "'");
		}
		$this->db->query("INSERT INTO `" . DB_PREFIX . self::CAMPAIGN_TABLE . "` SET source='" . $this->db->escape($source) . "', medium='" . $this->db->escape($medium) . "', campaign='" . $this->db->escape($campaign) . "', sessions=1, orders='" . ($name === 'purchase' ? 1 : 0) . "', revenue='" . $value . "', date_modified='" . $now . "' ON DUPLICATE KEY UPDATE orders=orders+VALUES(orders), revenue=revenue+VALUES(revenue), date_modified='" . $now . "'");
		setcookie('websky_analytics_session', $session_id, time() + 1800, '/', '', !empty($this->request->server['HTTPS']), true);
		setcookie('websky_analytics_visitor', $visitor_id, time() + 31536000, '/', '', !empty($this->request->server['HTTPS']), true);
		return true;
	}

	private function token(string $value, int $length): string {
		$value = preg_replace('/[^a-f0-9]/i', '', $value);
		return strlen($value) >= 16 ? substr($value, 0, $length) : '';
	}

	private function source(string $source, string $referrer): string {
		if ($source !== '') return substr($source, 0, 128);
		$host = parse_url($referrer, PHP_URL_HOST);
		if (!$host) return 'direct';
		$host = strtolower((string)$host);
		foreach (['google' => 'google', 'instagram' => 'instagram', 'telegram' => 'telegram', 'bing' => 'bing', 'facebook' => 'facebook'] as $needle => $name) if (strpos($host, $needle) !== false) return $name;
		$store = parse_url((string)$this->config->get('config_url'), PHP_URL_HOST);
		return $store && $host === strtolower((string)$store) ? 'internal' : 'referral';
	}
}
