# Переводы

Текстовый домен: `wp-personal-account`. Все строки обёрнуты в функции локализации WordPress.

## Сборка .pot

WP-CLI:

```bash
wp i18n make-pot . languages/wp-personal-account.pot --domain=wp-personal-account --exclude=tools,preview.html
```

## Сборка .mo из .po

```bash
wp i18n make-mo languages/
```

Файлы переводов кладутся в эту папку: `wp-personal-account-ru_RU.po` / `.mo`.
Плагин загружает их через `load_plugin_textdomain()` на хуке `init`.
