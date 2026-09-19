<?php
namespace Opencart\Admin\Controller\Extension\WebskyAnalyticspro\Analytics;

class Analyticspro extends \Opencart\System\Engine\Controller {
	private const SETTING_CODE = 'analytics_analyticspro';

	public function index(): void {
		$this->load->language('extension/websky_analyticspro/analytics/analyticspro');

		$this->document->setTitle($this->language->get('heading_title'));

		$store_id = isset($this->request->get['store_id']) ? (int)$this->request->get['store_id'] : 0;

		$data = $this->language->all();
		$data['breadcrumbs'] = [
			[
				'text' => $this->language->get('text_home'),
				'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
			],
			[
				'text' => $this->language->get('text_extension'),
				'href' => $this->url->link('extension/analytics', 'user_token=' . $this->session->data['user_token'])
			],
			[
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('extension/websky_analyticspro/analytics/analyticspro', 'user_token=' . $this->session->data['user_token'] . '&store_id=' . $store_id)
			]
		];

		$data['save'] = $this->url->link('extension/websky_analyticspro/analytics/analyticspro.save', 'user_token=' . $this->session->data['user_token'] . '&store_id=' . $store_id);
		$data['back'] = $this->url->link('extension/analytics', 'user_token=' . $this->session->data['user_token']);

		$this->load->model('setting/setting');

		$data['analytics_analyticspro_measurement_id'] = $this->model_setting_setting->getValue(self::SETTING_CODE . '_measurement_id', $store_id);
		$data['analytics_analyticspro_status'] = (int)$this->model_setting_setting->getValue(self::SETTING_CODE . '_status', $store_id);
		$data['store_id'] = $store_id;

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/websky_analyticspro/analytics/analyticspro', $data));
	}

	public function save(): void {
		$this->load->language('extension/websky_analyticspro/analytics/analyticspro');

		$json = [];
		$store_id = isset($this->request->get['store_id']) ? (int)$this->request->get['store_id'] : 0;

		if (!$this->user->hasPermission('modify', 'extension/websky_analyticspro/analytics/analyticspro')) {
			$json['error'] = $this->language->get('error_permission');
		}

		$measurement_id = trim((string)($this->request->post['analytics_analyticspro_measurement_id'] ?? ''));

		if ($measurement_id !== '' && !preg_match('/^G-[A-Z0-9]{6,}$/i', $measurement_id)) {
			$json['error'] = $this->language->get('error_measurement_id');
		}

		if (!$json) {
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting(self::SETTING_CODE, [
				self::SETTING_CODE . '_measurement_id' => strtoupper($measurement_id),
				self::SETTING_CODE . '_status' => !empty($this->request->post['analytics_analyticspro_status']) ? 1 : 0
			], $store_id);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function install(): void {
		$this->load->model('setting/setting');

		if ($this->model_setting_setting->getValue(self::SETTING_CODE . '_status') === '') {
			$this->model_setting_setting->editSetting(self::SETTING_CODE, [
				self::SETTING_CODE . '_measurement_id' => '',
				self::SETTING_CODE . '_status' => 0
			]);
		}
	}

	public function uninstall(): void {
		// The analytics manager removes this extension's settings by its code.
	}
}

