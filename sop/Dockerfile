# Ricoman R&D — Product Pipeline (productsolutions.ricoman.com)
# Static PWA served by nginx. Self-contained: everything it needs is in this folder.
FROM nginx:1.27-alpine
COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY . /usr/share/nginx/html
EXPOSE 80
