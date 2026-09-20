<?php
namespace Opencart\Admin\Controller\Extension\WebskyAnalyticspro\Analytics;

class Analyticspro extends \Opencart\System\Engine\Controller {
	private const SETTING_CODE = 'analytics_analyticspro';

	public function index(): void {
		$this->load->language('extension/websky_analyticspro/analytics/analyticspro');
		$this->document->setTitle($this->language->get('heading_title'));
		$store_id = (int)($this->request->get['store_id'] ?? 0);
		$days = max(1, min(90, (int)($this->request->get['days'] ?? 30)));
		$to = date('Y-m-d H:i:s'); $from = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));
		$this->load->model('setting/setting');
		$this->load->model('extension/websky_analyticspro/analytics/analyticspro');
		$data = $this->language->all();
		$data['breadcrumbs'] = [
			['text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])],
			['text' => $this->language->get('text_extension'), 'href' => $this->url->link('extension/analytics', 'user_token=' . $this->session->data['user_token'])],
			['text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/websky_analyticspro/analytics/analyticspro', 'user_token=' . $this->session->data['user_token'] . '&store_id=' . $store_id)]
		];
		$data['save'] = $this->url->link('extension/websky_analyticspro/analytics/analyticspro.save', 'user_token=' . $this->session->data['user_token'] . '&store_id=' . $store_id);
		$data['export'] = $this->url->link('extension/websky_analyticspro/analytics/analyticspro.export', 'user_token=' . $this->session->data['user_token'] . '&store_id=' . $store_id . '&days=' . $days);
		$data['back'] = $this->url->link('extension/analytics', 'user_token=' . $this->session->data['user_token']);
		$data['store_id'] = $store_id; $data['days'] = $days;
		$data['analytics_analyticspro_measurement_id'] = $this->model_setting_setting->getValue(self::SETTING_CODE . '_measurement_id', $store_id);
		$data['analytics_analyticspro_api_secret'] = $this->model_setting_setting->getValue(self::SETTING_CODE . '_api_secret', $store_id);
		$data['analytics_analyticspro_status'] = (int)$this->model_setting_setting->getValue(self::SETTING_CODE . '_status', $store_id);
		$data['analytics_analyticspro_store_events'] = (int)$this->model_setting_setting->getValue(self::SETTING_CODE . '_store_events', $store_id);
		$data['summary'] = $this->model_extension_websky_analyticspro_analytics_analyticspro->getSummary($store_id, $from, $to);
		$data['products'] = $this->model_extension_websky_analyticspro_analytics_analyticspro->getTopProducts($store_id, $from, $to);
		$data['sources'] = $this->model_extension_websky_analyticspro_analytics_analyticspro->getTrafficSources($store_id, $from, $to);
		$data['pages'] = $this->model_extension_websky_analyticspro_analytics_analyticspro->getTopPages($store_id, $from, $to);
		$data['journeys'] = $this->model_extension_websky_analyticspro_analytics_analyticspro->getJourneys($store_id, $from, $to);
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		$this->response->setOutput($this->load->view('extension/websky_analyticspro/analytics/analyticspro', $data));
	}

	public function save(): void {
		$this->load->language('extension/websky_analyticspro/analytics/analyticspro');
		$json = [];
		$store_id = (int)($this->request->get['store_id'] ?? 0);
		if (!$this->user->hasPermission('modify', 'extension/websky_analyticspro/analytics/analyticspro')) $json['error'] = $this->language->get('error_permission');
		$measurement_id = strtoupper(trim((string)($this->request->post[self::SETTING_CODE . '_measurement_id'] ?? '')));
		if ($measurement_id !== '' && !preg_match('/^G-[A-Z0-9]{6,}$/', $measurement_id)) $json['error'] = $this->language->get('error_measurement_id');
		if (!$json) {
			$this->load->model('setting/setting');
			$this->model_setting_setting->editSetting(self::SETTING_CODE, [self::SETTING_CODE . '_measurement_id' => $measurement_id, self::SETTING_CODE . '_api_secret' => trim((string)($this->request->post[self::SETTING_CODE . '_api_secret'] ?? '')), self::SETTING_CODE . '_status' => !empty($this->request->post[self::SETTING_CODE . '_status']) ? 1 : 0, self::SETTING_CODE . '_store_events' => !empty($this->request->post[self::SETTING_CODE . '_store_events']) ? 1 : 0], $store_id);
			$json['success'] = $this->language->get('text_success');
		}
		$this->response->addHeader('Content-Type: application/json'); $this->response->setOutput(json_encode($json));
	}

	public function export(): void {
		$store_id = (int)($this->request->get['store_id'] ?? 0); $days = max(1, min(90, (int)($this->request->get['days'] ?? 30)));
		$this->load->model('extension/websky_analyticspro/analytics/analyticspro');
		$rows = $this->model_extension_websky_analyticspro_analytics_analyticspro->exportEvents($store_id, date('Y-m-d H:i:s', strtotime('-' . $days . ' days')), date('Y-m-d H:i:s'));
		$this->response->addHeader('Content-Type: text/csv; charset=utf-8'); $this->response->addHeader('Content-Disposition: attachment; filename="websky-analytics-events.csv"');
		$out = fopen('php://temp', 'w+'); fputcsv($out, ['event_id','event_name','visitor_id','session_id','customer_id','page_url','source','medium','campaign','product_id','order_id','quantity','value','currency','date_added']); foreach ($rows as $row) fputcsv($out, $row); rewind($out); $this->response->setOutput(stream_get_contents($out)); fclose($out);
	}

	public function install(): void {
		$this->load->model('extension/websky_analyticspro/analytics/analyticspro'); $this->model_extension_websky_analyticspro_analytics_analyticspro->install();
		$this->load->model('setting/setting'); if ($this->model_setting_setting->getValue(self::SETTING_CODE . '_store_events') === '') $this->model_setting_setting->editSetting(self::SETTING_CODE, [self::SETTING_CODE . '_measurement_id' => '', self::SETTING_CODE . '_api_secret' => '', self::SETTING_CODE . '_status' => 0, self::SETTING_CODE . '_store_events' => 1]);
		$this->load->model('setting/event');
		$events = [
			['code'=>'websky_analyticspro_header','description'=>'WebSky Analytics Pro storefront tracker','trigger'=>'catalog/view/common/header/after','action'=>'extension/websky_analyticspro/event/analyticspro.headerAfter'],
			['code'=>'websky_analyticspro_cart_add','description'=>'WebSky Analytics Pro cart add','trigger'=>'catalog/model/checkout/cart/add/after','action'=>'extension/websky_analyticspro/event/analyticspro.cartAddAfter'],
			['code'=>'websky_analyticspro_cart_remove','description'=>'WebSky Analytics Pro cart remove','trigger'=>'catalog/model/checkout/cart/remove/after','action'=>'extension/websky_analyticspro/event/analyticspro.cartRemoveAfter'],
			['code'=>'websky_analyticspro_checkout','description'=>'WebSky Analytics Pro checkout','trigger'=>'catalog/controller/checkout/checkout/before','action'=>'extension/websky_analyticspro/event/analyticspro.checkoutBefore'],
			['code'=>'websky_analyticspro_purchase','description'=>'WebSky Analytics Pro purchase','trigger'=>'catalog/controller/checkout/success/before','action'=>'extension/websky_analyticspro/event/analyticspro.successBefore']
		];
		foreach ($events as $event) { $event['status'] = 1; $event['sort_order'] = 0; $this->model_setting_event->deleteEventByCode($event['code']); $this->model_setting_event->addEvent($event); }
	}

	public function uninstall(): void {
		$this->load->model('setting/event'); foreach (['websky_analyticspro_header','websky_analyticspro_cart_add','websky_analyticspro_cart_remove','websky_analyticspro_checkout','websky_analyticspro_purchase'] as $code) $this->model_setting_event->deleteEventByCode($code);
		$this->load->model('extension/websky_analyticspro/analytics/analyticspro'); $this->model_extension_websky_analyticspro_analytics_analyticspro->uninstall();
	}
}
