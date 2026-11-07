#!/usr/bin/env bash
#
# Сборка релиза плагина в формате версии X.Y.Z.
#
# Использование:
#   ./tools/release.sh 1.0.0            # выставить версию 1.0.0 и собрать ZIP
#   ./tools/release.sh 1.1.0 ~/dist     # своя папка для архива
#   ./tools/release.sh --check          # только проверить консистентность версий
#
# Версия должна строго соответствовать MAJOR.MINOR.PATCH (semver):
#   MAJOR — несовместимые изменения (ломаем API шаблонов/фильтров),
#   MINOR — новая функциональность без поломок,
#   PATCH — исправления и правки стилей.
#
set -euo pipefail

SLUG="wp-personal-account"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MAIN_FILE="$ROOT/$SLUG.php"
README_FILE="$ROOT/readme.txt"
VERSION_RE='^[0-9]+\.[0-9]+\.[0-9]+$'

die() { printf '\033[31m✗ %s\033[0m\n' "$1" >&2; exit 1; }
ok()  { printf '\033[32m✓ %s\033[0m\n' "$1"; }

header_version() { grep -m1 '^ \* Version:' "$MAIN_FILE" | sed 's/.*Version:[[:space:]]*//'; }
const_version()  { grep -m1 "define( 'WPPA_VERSION'" "$MAIN_FILE" | sed "s/.*'WPPA_VERSION', '\([^']*\)'.*/\1/"; }
stable_tag()     { grep -m1 '^Stable tag:' "$README_FILE" | sed 's/Stable tag:[[:space:]]*//'; }

# Проверяет три точки версии. Отчёт — в stderr, итоговая версия — в stdout.
check() {
	local h c s
	h="$(header_version)"; c="$(const_version)"; s="$(stable_tag)"

	printf '  заголовок плагина : %s\n' "$h" >&2
	printf '  WPPA_VERSION      : %s\n' "$c" >&2
	printf '  readme.txt        : %s\n' "$s" >&2

	[[ "$h" =~ $VERSION_RE ]] || die "версия в заголовке не в формате X.Y.Z: '$h'"
	[[ "$c" =~ $VERSION_RE ]] || die "WPPA_VERSION не в формате X.Y.Z: '$c'"
	[[ "$s" =~ $VERSION_RE ]] || die "Stable tag не в формате X.Y.Z: '$s'"
	[[ "$h" == "$c" && "$c" == "$s" ]] || die "версии расходятся: $h / $c / $s"

	printf '%s' "$h"
}

if [[ "${1:-}" == "--check" ]]; then
	echo "→ проверка версий"
	V="$(check)"
	ok "версия консистентна: $V (формат X.Y.Z)"
	exit 0
fi

VERSION="${1:-}"
[[ -n "$VERSION" ]] || die "укажите версию: ./tools/release.sh 1.0.0"
[[ "$VERSION" =~ $VERSION_RE ]] || die "неверный формат версии '$VERSION' — нужно X.Y.Z, например 1.2.0"

OUT_DIR="${2:-$(dirname "$ROOT")}"
mkdir -p "$OUT_DIR"

echo "→ выставляю версию $VERSION"
# Заголовок плагина.
perl -0pi -e "s/^( \* Version:\s*).*$/\${1}$VERSION/m" "$MAIN_FILE"
# Константа.
perl -0pi -e "s/define\( 'WPPA_VERSION', '[^']*' \)/define( 'WPPA_VERSION', '$VERSION' )/" "$MAIN_FILE"
# Stable tag в readme.txt.
perl -0pi -e "s/^Stable tag:.*$/Stable tag: $VERSION/m" "$README_FILE"

CURRENT="$(check 2>/dev/null)"
ok "версии синхронизированы: $CURRENT"

# Синтаксис PHP, если доступен интерпретатор.
if command -v php >/dev/null 2>&1; then
	echo "→ проверка синтаксиса PHP"
	while IFS= read -r -d '' file; do
		php -l "$file" >/dev/null || die "синтаксическая ошибка: $file"
	done < <(find "$ROOT" -name '*.php' -not -path '*/.git/*' -print0)
	ok "синтаксис в порядке"
	for test_file in smoke-test.php admin-smoke-test.php chat-test.php; do
		[[ -f "$ROOT/tools/$test_file" ]] || continue
		php "$ROOT/tools/$test_file" >/dev/null && ok "тест $test_file пройден" || die "тест $test_file упал"
	done
else
	echo "· php не найден — пропускаю проверку синтаксиса"
fi

# Проверка JavaScript новых блоков, если доступен Node.js.
if command -v node >/dev/null 2>&1 && [[ -f "$ROOT/tools/button-block-editor-test.js" ]]; then
	node "$ROOT/tools/button-block-editor-test.js" >/dev/null && ok "тест редактора кнопок пройден" || die "тест редактора кнопок упал"
fi

if command -v node >/dev/null 2>&1 && [[ -f "$ROOT/tools/chat-js-test.js" ]]; then
	node "$ROOT/tools/chat-js-test.js" >/dev/null && ok "тест интерфейса чата пройден" || die "тест интерфейса чата упал"
fi

if command -v node >/dev/null 2>&1 && [[ -f "$ROOT/tools/unread-js-test.js" ]]; then
	node "$ROOT/tools/unread-js-test.js" >/dev/null && ok "тест индикатора сообщений пройден" || die "тест индикатора сообщений упал"
fi

if command -v python3 >/dev/null 2>&1 && [[ -f "$ROOT/tools/unread-sql-test.py" ]]; then
	python3 "$ROOT/tools/unread-sql-test.py" >/dev/null && ok "тест SQL индикатора пройден" || die "тест SQL индикатора упал"
fi

# Сборка архива: только то, что нужно на сайте.
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT
mkdir -p "$STAGE/$SLUG"

(
	cd "$ROOT"
	tar -c \
		--exclude='.git' \
		--exclude='tools' \
		--exclude='preview*.html' \
		--exclude='phpcs.xml.dist' \
		--exclude='.editorconfig' \
		--exclude='.gitignore' \
		--exclude='*.zip' \
		--exclude='.DS_Store' \
		. | tar -x -C "$STAGE/$SLUG"
)

ZIP_NAME="$SLUG-$VERSION.zip"
rm -f "$OUT_DIR/$ZIP_NAME"
(
	cd "$STAGE"
	zip -r -q -X "$OUT_DIR/$ZIP_NAME" "$SLUG" -x '*.DS_Store'
)

FILES="$(unzip -Z1 "$OUT_DIR/$ZIP_NAME" 2>/dev/null | grep -vc '/$' || true)"
ok "собран $OUT_DIR/$ZIP_NAME ($FILES файлов, $(du -h "$OUT_DIR/$ZIP_NAME" | cut -f1))"
