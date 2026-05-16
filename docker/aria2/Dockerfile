FROM alpine:3.19

RUN apk add --no-cache aria2 su-exec

EXPOSE 6800
EXPOSE 6889

COPY docker/aria2/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

VOLUME /config
VOLUME /downloads

ENV RPC_SECRET=
ENV RPC_PORT=6800
ENV LISTEN_PORT=6889
ENV PUID=1000
ENV PGID=1000

ENTRYPOINT ["/entrypoint.sh"]
