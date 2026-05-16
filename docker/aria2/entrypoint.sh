#!/bin/sh

set -e

cat > /config/aria2.conf << EOF
dir=/downloads
input-file=/config/aria2.session
save-session=/config/aria2.session
save-session-interval=60
continue=true

enable-rpc=true
rpc-allow-origin-all=true
rpc-listen-all=true
rpc-listen-port=${RPC_PORT}
rpc-secret=${RPC_SECRET}

listen-port=${LISTEN_PORT}
enable-dht=true
enable-peer-exchange=true
peer-id-prefix=-TR2940-
user-agent=Transmission/2.94
seed-ratio=0
bt-enable-lpd=true
enable-dht6=false
max-concurrent-downloads=5
max-connection-per-server=5
min-split-size=20M
split=5
disable-ipv6=true
EOF

chown -R "${PUID}:${PGID}" /config /downloads

exec su-exec "${PUID}:${PGID}" aria2c --conf-path=/config/aria2.conf
