<?php
namespace Opencart\Catalog\Controller\Extension\WebskyAnalyticspro\Event;

class Analyticspro extends \Opencart\System\Engine\Controller {
	private function record(array $payload): void {
		$this->load->model('extension/websky_analyticspro/analytics/analyticspro');
		$this->model_extension_websky_analyticspro_analytics_analyticspro->record($payload);
	}

	public function headerAfter(string &$route, array &$args, string &$output): void {
		if (!(int)$this->config->get('analytics_analyticspro_status')) return;
		$script = $this->load->controller('extension/websky_analyticspro/analytics/analyticspro');
		if ($script && strpos($output, 'websky_analytics_session') === false) $output .= $script;
	}

	public function cartAddAfter(string &$route, array &$args, mixed &$output): void {
		$this->record(['event_name' => 'add_to_cart', 'product_id' => (int)($args[0] ?? 0), 'quantity' => (int)($args[1] ?? 1)]);
	}

	public function cartRemoveAfter(string &$route, array &$args, mixed &$output): void {
		$this->record(['event_name' => 'remove_from_cart', 'product_id' => (int)($args[0] ?? 0), 'quantity' => (int)($args[1] ?? 1)]);
	}

	public function checkoutBefore(string &$route, array &$args): void {
		$this->record(['event_name' => 'checkout_start']);
	}

	public function successBefore(string &$route, array &$args): void {
		$order_id = (int)($this->session->data['order_id'] ?? 0);
		if (!$order_id) return;
		$this->load->model('checkout/order');
		$order = $this->model_checkout_order->getOrder($order_id);
		if (!$order) return;
		$products = $this->model_checkout_order->getProducts($order_id);
		foreach ($products as $product) {
			$this->record(['event_name' => 'purchase', 'order_id' => $order_id, 'product_id' => (int)$product['product_id'], 'quantity' => (int)$product['quantity'], 'value' => (float)$product['total'], 'currency' => $order['currency_code'] ?? '']);
		}
	}
}
