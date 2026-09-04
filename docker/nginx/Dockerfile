FROM nginx:1.20.1-alpine

# Copy all templates to a temporary folder
COPY templates/*.template /etc/nginx/templates/

# Use a simpler shell script
CMD ["sh", "-c", "CONF_SOURCE=/etc/nginx/templates/${NGINX_CONF_TYPE}.conf.template; if [ ! -f \"$CONF_SOURCE\" ]; then CONF_SOURCE=/etc/nginx/templates/default.conf.template; fi; envsubst '${NGINX_PORT} ${NGINX_HOST} ${NGINX_CERT_CRT} ${NGINX_CERT_KEY}' < $CONF_SOURCE > /etc/nginx/conf.d/default.conf && nginx -g 'daemon off;'"]