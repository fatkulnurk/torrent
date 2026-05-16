.PHONY: up down test test-unit test-integration qb-password qb-setup logs setup clean

up:
	docker compose up -d

down:
	docker compose down

logs:
	docker compose logs -f

test:
	php vendor/bin/phpunit

test-unit:
	php vendor/bin/phpunit tests/Data tests/Exceptions tests/Providers tests/TorrentClientManagerTest.php

test-integration:
	QB_PASS=$$(docker logs torrent-qbittorrent 2>&1 | grep -oP 'temporary password is provided for this session: \K\S+') && \
	INTEGRATION=true QBITTORRENT_PASSWORD=$$QB_PASS php vendor/bin/phpunit tests/integration

qb-password:
	@echo "Extracting qBittorrent password from container logs..."
	@docker logs torrent-qbittorrent 2>&1 | grep -oP 'temporary password is provided for this session: \K\S+' || echo "Container not running or password not found"

qb-setup:
	@echo "qBittorrent 5.x uses a random session password that changes on restart."
	@echo "The password is automatically extracted when running 'make test-integration'."
	@echo "Run 'make qb-password' to view the current temporary password."

setup:
	mkdir -p data/qbittorrent/config data/qbittorrent/downloads
	mkdir -p data/transmission/config data/transmission/downloads
	mkdir -p data/rtorrent/data data/rtorrent/passwd
	mkdir -p data/deluge/config data/deluge/downloads
	mkdir -p data/rqbit/db data/rqbit/cache data/rqbit/downloads
	mkdir -p data/aria2/config data/aria2/downloads

clean:
	rm -rf data
