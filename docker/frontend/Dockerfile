# Збірка: docker build -f docker/frontend/Dockerfile -t <user>/win-casino-frontend .
FROM nginx:1.27-alpine
COPY docker/frontend/nginx.conf /etc/nginx/conf.d/default.conf
COPY --chmod=755 docker/frontend/40-config-js.sh /docker-entrypoint.d/40-config-js.sh
COPY *.html casino-api.js /usr/share/nginx/html/
COPY *.jpg *.png *.mp3 *.mp4 /usr/share/nginx/html/
EXPOSE 80
HEALTHCHECK --interval=15s --timeout=3s CMD wget -qO- http://127.0.0.1/ >/dev/null || exit 1
