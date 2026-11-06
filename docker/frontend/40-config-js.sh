#!/bin/sh
# Генерує config.js зі змінних середовища під час старту контейнера:
# один і той самий образ працює на dev/staging/prod з різним API.
set -eu
cat > /usr/share/nginx/html/config.js <<JS
window.APP_CONFIG = {
  API_BASE: "${API_BASE_URL:-http://localhost:8000/api}",
  API_VERSION: "${API_VERSION:-v1}"
};
JS
