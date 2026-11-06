#!/usr/bin/env bash
#
# Отправка коммитов в GitHub.
#
# Токен не хранится ни в файле, ни в конфиге git: он берётся из переменной
# окружения GH_TOKEN и подставляется только в URL одноразового remote.
#
#   GH_TOKEN=ghp_xxx ./tools/push-github.sh
#
set -euo pipefail

repo="pavelb-coder/wp-personal-account"
branch="${1:-main}"

: "${GH_TOKEN:?Нужен токен: GH_TOKEN=ghp_xxx ./tools/push-github.sh}"

git remote remove wppa-push 2>/dev/null || true
git remote add wppa-push "https://${GH_TOKEN}@github.com/${repo}.git"

tag="v$(grep -m1 '^ \* Version:' wp-personal-account.php | sed 's/.*Version: *//' | tr -d ' \r')"

echo "→ отправляю ${branch} в ${repo}"
git push wppa-push "${branch}:${branch}"

# Тег версии — чтобы на GitHub было видно, какая версия актуальна.
if git rev-parse -q --verify "refs/tags/${tag}" >/dev/null; then
	echo "→ отправляю тег ${tag}"
	git push wppa-push "refs/tags/${tag}"
fi

git remote remove wppa-push
echo "✓ готово: https://github.com/${repo}/commits/${branch}"
