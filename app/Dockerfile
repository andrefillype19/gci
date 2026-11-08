FROM dmstr/php-yii2:latest-alpine-nginx

WORKDIR /app

COPY trunk/ /app/trunk/

COPY yii/ /app/yii/

RUN ln -sf trunk web \
    && chown -R www-data:www-data /app/trunk/assets \
    && chown -R www-data:www-data /app/trunk/protected/runtime \
    && chmod -R 755 /app/trunk/assets \
    && chmod -R 755 /app/trunk/protected/runtime \
    && chown -R www-data:www-data /app/trunk/protected/uploads \
    && chmod -R 755 /app/trunk/protected/uploads

RUN echo "error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT" > /usr/local/etc/php/conf.d/zz-legacy-yii.ini

EXPOSE 80 443