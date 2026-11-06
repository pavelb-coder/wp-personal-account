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

echo "→ отправляю ${branch} в ${repo}"
git push wppa-push "${branch}:${branch}"

git remote remove wppa-push
echo "✓ готово: https://github.com/${repo}/commits/${branch}"
