FROM alpine:3.22

RUN apk add --no-cache unfs3 rpcbind

COPY entrypoint.sh /usr/local/bin/file-server-entrypoint
    
RUN sed -i 's/\r$//' /usr/local/bin/file-server-entrypoint \
    && chmod 755 /usr/local/bin/file-server-entrypoint \
    && mkdir -p /export/uploads /export/sessions

VOLUME ["/export/uploads", "/export/sessions"]

EXPOSE 2049/tcp

ENTRYPOINT ["/usr/local/bin/file-server-entrypoint"]
