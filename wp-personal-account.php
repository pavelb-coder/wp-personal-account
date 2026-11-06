<?php
/**
 * Plugin Name:       WP Personal Account
 * Plugin URI:        https://github.com/pavelb-coder/wp-personal-account
 * Description:       Личный кабинет пользователя для WordPress: профиль с персональными данными, пароль, смена email, команды с наставниками, вход и регистрация — на шорткодах, виджете и блоке Gutenberg.
 * Version:           3.50.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            pavelb-coder
 * Author URI:        https://github.com/pavelb-coder
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-personal-account
 * Domain Path:       /languages
 *
 * @package WP_Personal_Account
 */

defined( 'ABSPATH' ) || exit;

define( 'WPPA_VERSION', '3.50.0' );
define( 'WPPA_FILE', __FILE__ );
define( 'WPPA_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPPA_URL', plugin_dir_url( __FILE__ ) );

require_once WPPA_PATH . 'includes/class-wppa-time.php';
require_once WPPA_PATH . 'includes/class-wppa-plugin.php';
require_once WPPA_PATH . 'includes/class-wppa-theme.php';
require_once WPPA_PATH . 'includes/class-wppa-settings.php';
require_once WPPA_PATH . 'includes/class-wppa-admin.php';
require_once WPPA_PATH . 'includes/class-wppa-template.php';
require_once WPPA_PATH . 'includes/class-wppa-notices.php';
require_once WPPA_PATH . 'includes/class-wppa-fields.php';
require_once WPPA_PATH . 'includes/class-wppa-password.php';
require_once WPPA_PATH . 'includes/class-wppa-link-button.php';
require_once WPPA_PATH . 'includes/class-wppa-export.php';
require_once WPPA_PATH . 'includes/class-wppa-export-folder.php';
require_once WPPA_PATH . 'includes/class-wppa-smb.php';
require_once WPPA_PATH . 'includes/class-wppa-olympiad.php';
require_once WPPA_PATH . 'includes/class-wppa-reset.php';
require_once WPPA_PATH . 'includes/class-wppa-account.php';
require_once WPPA_PATH . 'includes/class-wppa-auth.php';
require_once WPPA_PATH . 'includes/class-wppa-profile.php';
require_once WPPA_PATH . 'includes/class-wppa-teams.php';
require_once WPPA_PATH . 'includes/class-wppa-curator-files.php';
require_once WPPA_PATH . 'includes/class-wppa-participants.php';
require_once WPPA_PATH . 'includes/class-wppa-certificates.php';
require_once WPPA_PATH . 'includes/class-wppa-certificate-export.php';
require_once WPPA_PATH . 'includes/class-wppa-team-files-export.php';
require_once WPPA_PATH . 'includes/class-wppa-mailing.php';
require_once WPPA_PATH . 'includes/class-wppa-mailing-files.php';
require_once WPPA_PATH . 'includes/class-wppa-mailing-certificates.php';
require_once WPPA_PATH . 'includes/class-wppa-builder.php';
require_once WPPA_PATH . 'includes/class-wppa-widget.php';
require_once WPPA_PATH . 'includes/class-wppa-blocks.php';
require_once WPPA_PATH . 'includes/class-wppa-shortcodes.php';

/**
 * Возвращает основной экземпляр плагина.
 *
 * @return WPPA_Plugin
 */
function wppa() {
	return WPPA_Plugin::instance();
}

WPPA_Plugin::init();

register_activation_hook( __FILE__, array( 'WPPA_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WPPA_Plugin', 'deactivate' ) );
