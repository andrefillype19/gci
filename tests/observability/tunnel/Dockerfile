FROM alpine:3.22

RUN apk add --no-cache openssh-client

COPY entrypoint.sh /entrypoint.sh
RUN sed -i 's/\r$//' /entrypoint.sh && chmod 755 /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
