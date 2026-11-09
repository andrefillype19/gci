FROM dmstr/php-yii2:latest-alpine-nginx

WORKDIR /app

COPY trunk/ /app/trunk/
COPY yii/ /app/yii/

RUN mkdir -p /app/trunk/assets \
             /app/trunk/protected/runtime \
             /app/trunk/protected/uploads \
    && ln -sf trunk web \
    && chown -R www-data:www-data /app/trunk/assets \
                                  /app/trunk/protected/runtime \
                                  /app/trunk/protected/uploads \
    && chmod -R 777 /app/trunk/assets \
                    /app/trunk/protected/runtime \
                    /app/trunk/protected/uploads

RUN echo "error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT" > /usr/local/etc/php/conf.d/zz-legacy-yii.ini

EXPOSE 80 443