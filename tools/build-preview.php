<?php
/**
 * Генератор статического превью интерфейса (демо-данные, без WordPress).
 */

require __DIR__ . '/wp-stubs.php';

$base = dirname( __DIR__ ) . '/';

define( 'WPPA_VERSION', '1.0.0' );
define( 'WPPA_FILE', $base . 'wp-personal-account.php' );
define( 'WPPA_PATH', $base );
define( 'WPPA_URL', 'https://example.com/plugins/wp-personal-account/' );

require $base . 'includes/class-wppa-plugin.php';
require $base . 'includes/class-wppa-template.php';
require $base . 'includes/class-wppa-notices.php';
require $base . 'includes/class-wppa-account.php';
require $base . 'includes/class-wppa-profile.php';
require $base . 'includes/class-wppa-orders.php';
require $base . 'includes/class-wppa-addresses.php';

/* -------------------------------------------------------------------------
 * Заглушки WooCommerce с демо-данными
 * ---------------------------------------------------------------------- */

class WooCommerce {}

class Demo_Date {
	public function date_i18n( $f ) { return '28.10.2026'; }
}

class Demo_Item {
	private $name;
	private $qty;
	private $total;
	public function __construct( $name, $qty, $total ) {
		$this->name = $name; $this->qty = $qty; $this->total = $total;
	}
	public function get_product() { return null; }
	public function get_name() { return $this->name; }
	public function get_quantity() { return $this->qty; }
	public function get_total() { return $this->total; }
	public function get_formatted_meta_data( $sep = ' ' ) { return array(); }
}

class WC_Order {
	private $id;
	private $number;
	private $status;
	private $total;
	public function __construct( $id, $number, $status, $total ) {
		$this->id = $id; $this->number = $number; $this->status = $status; $this->total = $total;
	}
	public function get_customer_id() { return 7; }
	public function get_id() { return $this->id; }
	public function get_order_number() { return $this->number; }
	public function get_date_created() { return new Demo_Date(); }
	public function get_status() { return $this->status; }
	public function get_formatted_order_total() { return '<span class="woocommerce-Price-amount">' . number_format( $this->total, 0, ',', ' ' ) . '&nbsp;₽</span>'; }
	public function needs_payment() { return in_array( $this->status, array( 'pending' ), true ); }
	public function get_checkout_payment_url() { return '#pay'; }
	public function get_payment_method_title() { return 'Банковская карта'; }
	public function get_currency() { return 'RUB'; }
	public function get_items() {
		return array(
			new Demo_Item( 'Кофе в зёрнах Ethiopia 1 кг', 2, 3980 ),
			new Demo_Item( 'Кружка керамическая 350 мл', 1, 1290 ),
		);
	}
	public function get_order_item_totals() {
		return array(
			array( 'label' => 'Товары:', 'value' => number_format( 5270, 0, ',', ' ' ) . '&nbsp;₽' ),
			array( 'label' => 'Доставка:', 'value' => 'Бесплатно' ),
			array( 'label' => 'Итого:', 'value' => '<strong>' . number_format( 5270, 0, ',', ' ' ) . '&nbsp;₽</strong>' ),
		);
	}
	public function get_formatted_billing_address() { return 'Иван Петров<br>ул. Ленина, 12, кв. 5<br>Красноярск, 660000'; }
	public function get_formatted_shipping_address() { return ''; }
}

function wc_get_orders( $args ) {
	$orders = new stdClass();
	$orders->orders = array(
		new WC_Order( 1042, '1042', 'processing', 5270 ),
		new WC_Order( 1031, '1031', 'completed', 2490 ),
		new WC_Order( 1017, '1017', 'pending', 890 ),
	);
	$orders->total         = 3;
	$orders->max_num_pages = 1;
	return $orders;
}
function wc_get_order_status_name( $s ) {
	$map = array( 'processing' => 'В обработке', 'completed' => 'Выполнен', 'pending' => 'Ожидает оплаты' );
	return isset( $map[ $s ] ) ? $map[ $s ] : $s;
}
function wc_price( $amount, $args = array() ) { return number_format( (float) $amount, 0, ',', ' ' ) . '&nbsp;₽'; }
function wc_format_datetime( $date ) { return '28.10.2026'; }
function WC() { return (object) array( 'customer' => null, 'countries' => null ); }

// Демо-данные адресов.
$GLOBALS['wppa_test']['meta'][7] = array(
	'billing_first_name'  => 'Иван',
	'billing_last_name'   => 'Петров',
	'billing_city'        => 'Красноярск',
	'billing_postcode'    => '660000',
	'billing_address_1'   => 'ул. Ленина, 12, кв. 5',
	'billing_country'     => 'RU',
	'billing_phone'       => '+7 913 000-00-00',
	'shipping_first_name' => 'Иван',
	'shipping_city'       => 'Красноярск',
	'shipping_address_1'  => 'ул. Ленина, 12, кв. 5',
	'shipping_country'    => 'RU',
	'_wppa_pending_email' => '',
);

$user = new WP_User();
$tabs = WPPA_Template::all_tabs();

function demo_section( $title, $note, $html ) {
	return '<section class="demo-block"><div class="demo-block__head"><h2>' . htmlspecialchars( $title ) . '</h2>'
		. ( $note ? '<p>' . htmlspecialchars( $note ) . '</p>' : '' ) . '</div>' . $html . '</section>';
}

$sections = '';

$sections .= demo_section(
	'Кабинет целиком (активна вкладка «Обзор»)',
	'Шапка с аватаром и кнопкой выхода, навигация по вкладкам, контент активной вкладки',
	WPPA_Template::get( 'account', array( 'user' => $user, 'tabs' => $tabs, 'current_tab' => 'dashboard' ) )
);

$sections .= demo_section(
	'Заказы',
	'Вкладка orders: таблица заказов WooCommerce со статусами, суммами и пагинацией',
	WPPA_Template::get( 'tab-orders', array( 'user' => $user, 'tabs' => $tabs ) )
);

$sections .= demo_section(
	'Детали заказа',
	'Просмотр состава заказа, итогов и адреса',
	WPPA_Template::get( 'tab-order', array( 'order' => new WC_Order( 1042, '1042', 'processing', 5270 ) ) )
);

$sections .= demo_section(
	'Профиль',
	'Вкладка profile: имя, фамилия, отображаемое имя, «о себе»',
	WPPA_Template::get( 'tab-profile', array( 'user' => $user, 'tabs' => $tabs ) ) . WPPA_Template::get( 'notice', array( 'type' => 'success', 'text' => 'Профиль обновлён.' ) )
);

$sections .= demo_section(
	'Безопасность',
	'Вкладка security: смена пароля, смена email с подтверждением, выход',
	WPPA_Template::get( 'tab-security', array( 'user' => $user, 'tabs' => $tabs ) )
);

$sections .= demo_section(
	'Вход и регистрация',
	'Экран для гостей: формы [wppa_login] и [wppa_register]',
	'<div class="demo-two">'
		. '<div>' . WPPA_Template::get( 'login', array( 'redirect' => '', 'title' => '' ) ) . '</div>'
		. '<div>' . WPPA_Template::get( 'register', array( 'title' => '' ) ) . '</div>'
		. '</div>'
);

$css = file_get_contents( $base . 'assets/css/account.css' );

$html = '<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>WP Personal Account — превью интерфейса</title>
<style>
' . $css . '
body {
	margin: 0;
	padding: 32px 20px 64px;
	background: #eef2f7;
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
	color: #0f172a;
}
.demo-top { max-width: 960px; margin: 0 auto 28px; }
.demo-top h1 { margin: 0 0 8px; font-size: 1.6rem; }
.demo-top p { margin: 0; color: #64748b; }
.demo-block { max-width: 960px; margin: 0 auto 40px; }
.demo-block__head { margin-bottom: 16px; }
.demo-block__head h2 { margin: 0 0 4px; font-size: 1.1rem; text-transform: uppercase; letter-spacing: .06em; color: #475569; }
.demo-block__head p { margin: 0; font-size: .88rem; color: #7c8798; }
.demo-block > .wppa, .demo-block > .demo-two { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 22px; box-shadow: 0 10px 30px rgba(15,23,42,.06); }
.demo-two { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 28px; }
</style>
</head>
<body>
<div class="demo-top">
	<h1>Личный кабинет — превью интерфейса</h1>
	<p>Демонстрация шаблонов плагина WP Personal Account на тестовых данных (WooCommerce-заказы и адреса — пример).</p>
</div>
' . $sections . '
</body>
</html>';

file_put_contents( $base . 'preview.html', $html );
echo "preview.html: " . strlen( $html ) . " байт\n";
