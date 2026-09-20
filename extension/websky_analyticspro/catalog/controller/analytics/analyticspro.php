<?php
namespace Opencart\Catalog\Controller\Extension\WebskyAnalyticspro\Analytics;

class Analyticspro extends \Opencart\System\Engine\Controller {
	public function index(): string {
		if (!(int)$this->config->get('analytics_analyticspro_status')) return '';
		$measurement_id = trim((string)$this->config->get('analytics_analyticspro_measurement_id'));
		return $this->load->view('extension/websky_analyticspro/analytics/analyticspro', [
			'measurement_id' => preg_match('/^G-[A-Z0-9]{6,}$/i', $measurement_id) ? strtoupper($measurement_id) : '',
			'collect_url' => $this->url->link('extension/websky_analyticspro/analytics/analyticspro.collect')
		]);
	}

	public function collect(): void {
		$this->load->model('extension/websky_analyticspro/analytics/analyticspro');
		$raw = file_get_contents('php://input');
		$payload = json_decode((string)$raw, true);
		if (!is_array($payload)) $payload = $this->request->post;
		if (strlen((string)$raw) > 32768) {
			$this->response->addHeader('HTTP/1.1 413 Request Entity Too Large');
			$this->response->setOutput(json_encode(['error' => 'payload_too_large']));
			return;
		}
		$ok = $this->model_extension_websky_analyticspro_analytics_analyticspro->record($payload);
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode(['ok' => $ok]));
	}
}
