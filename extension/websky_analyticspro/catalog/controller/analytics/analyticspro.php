<?php
namespace Opencart\Catalog\Controller\Extension\WebskyAnalyticspro\Analytics;

class Analyticspro extends \Opencart\System\Engine\Controller {
	public function index(): string {
		$measurement_id = trim((string)$this->config->get('analytics_analyticspro_measurement_id'));

		if (!preg_match('/^G-[A-Z0-9]{6,}$/i', $measurement_id)) {
			return '';
		}

		return $this->load->view('extension/websky_analyticspro/analytics/analyticspro', [
			'measurement_id' => strtoupper($measurement_id)
		]);
	}
}

