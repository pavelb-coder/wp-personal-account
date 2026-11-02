# WP Personal Account

Плагин личного кабинета для WordPress: профиль, пароль, заказы и адреса WooCommerce, вход и регистрация — на шорткодах, без зависимостей и сборки.

## Что внутри

| Раздел | Шорткод / файл | WooCommerce |
| --- | --- | --- |
| Личный кабинет (вкладки) | `[wppa_account]` | опционально |
| Форма входа | `[wppa_login]` | нет |
| Форма регистрации | `[wppa_register]` | нет |
| Заказы | вкладка `orders` | обязателен |
| Адреса | вкладка `addresses` | обязателен |

Вкладки: **Обзор** (счётчики, последний заказ, быстрые действия), **Профиль**, **Заказы** (список + детали + оплата), **Адреса** (плательщик/доставка), **Безопасность** (пароль, смена email с подтверждением, выход).

## Установка

```bash
git clone https://github.com/pavelb-coder/wp-personal-account.git
ln -s "$(pwd)/wp-personal-account" /path/to/wp-content/plugins/wp-personal-account
```

Либо скачать папку как ZIP и загрузить через «Плагины → Добавить новый → Загрузить плагин».

После активации плагин сам создаёт страницу «Личный кабинет» с шорткодом `[wppa_account]` и назначает её в настройках (**Настройки → Личный кабинет**).

## Структура

```
wp-personal-account.php      точка входа, константы, подключение классов
includes/
  class-wppa-plugin.php      хуки, ассеты, роутинг POST/GET, активация
  class-wppa-settings.php    страница настроек (страница кабинета, вкладки, цвет, CSS)
  class-wppa-template.php    загрузчик шаблонов + переопределение в теме
  class-wppa-notices.php     уведомления (коды сообщений в URL)
  class-wppa-account.php     выбор вкладки, экран для гостей
  class-wppa-auth.php        вход (лимит попыток) и регистрация
  class-wppa-profile.php     профиль, пароль, смена email с токеном
  class-wppa-orders.php      заказы WooCommerce
  class-wppa-addresses.php   адреса WooCommerce
  class-wppa-shortcodes.php  регистрация шорткодов
templates/                   разметка (переопределяется в теме)
assets/css/account.css       стили на CSS-переменных
assets/js/account.js         пароли, подтверждение выхода, защита от двойной отправки
tools/                       локальные проверки без WordPress
preview.html                 статическое превью интерфейса
```

## Переопределение шаблонов

Скопируйте нужный файл из `templates/` в `wp-content/themes/ваша-тема/wp-personal-account/` — плагин подхватит его автоматически (`WPPA_Template::locate()`).

Фильтры для доработок:

```php
add_filter( 'wppa_tabs', function ( $tabs ) {
	$tabs['support'] = 'Поддержка';
	return $tabs;
} );

add_filter( 'wppa_orders_query_args', function ( $args ) {
	$args['limit'] = 20;
	return $args;
} );

add_filter( 'wppa_notices', function ( $messages ) {
	$messages['profile_saved'] = array( 'success', 'Данные сохранены.' );
	return $messages;
} );
```

## Безопасность

* Nonce на каждой форме (`check_admin_referer`), проверка `is_user_logged_in()`.
* Экранирование вывода: `esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`.
* Санитизация ввода: `sanitize_text_field`, `sanitize_email`, `sanitize_textarea_field`, `absint`.
* Заказы и адреса доступны только владельцу: `get_customer_id()` сверяется с текущим пользователем.
* Смена email — только по ссылке с токеном (`wp_hash_password` + срок 24 часа).
* Лимит попыток входа: 5 за 15 минут на IP (transient).
* Пароли никогда не логируются и не санитизируются (передаются как есть).

## Версионирование

Версия плагина — строго `X.Y.Z` (semver) и хранится в трёх местах, которые обязаны совпадать:

| Место | Строка |
| --- | --- |
| `wp-personal-account.php` | `* Version: 1.0.0` |
| `wp-personal-account.php` | `define( 'WPPA_VERSION', '1.0.0' )` |
| `readme.txt` | `Stable tag: 1.0.0` |

Правила инкремента:

* **X (major)** — ломающие изменения: переименование фильтров, удаление шаблонов, изменение структуры настроек.
* **Y (minor)** — новая функциональность без поломок: вкладки, шорткоды, интеграции.
* **Z (patch)** — багфиксы, стили, тексты.

### Сборка релиза

```bash
./tools/release.sh --check      # проверить, что все три версии совпадают и в формате X.Y.Z
./tools/release.sh 1.1.0        # выставить версию и собрать ../wp-personal-account-1.1.0.zip
./tools/release.sh 1.1.0 ~/dist # архив в свою папку
```

Скрипт сам синхронизирует все три точки версии, прогоняет `php -l` по всем файлам и дымовой тест шаблонов, а затем собирает архив `wp-personal-account-X.Y.Z.zip` без служебных файлов (`.git`, `tools/`, `preview.html`, `phpcs.xml.dist`).

## Локальные проверки (без WordPress)

В `tools/` есть заглушки WP API, чтобы прогонять шаблоны и собирать превью:

```bash
php tools/smoke-test.php     # рендер всех шаблонов, ловит ошибки/варнинги
php tools/build-preview.php  # пересобрать preview.html
```

## Лицензия

GPL-2.0-or-later.
