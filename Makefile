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
	INTEGRATION=true php vendor/bin/phpunit tests/integration --process-isolation

qb-password:
	@echo "Extracting qBittorrent password from container logs..."
	@docker logs torrent-qbittorrent 2>&1 | grep -oP 'temporary password for the admin user is: \K\S+' || echo "Container not running or password not found"

qb-setup:
	$(eval QB_PASS := $(shell docker logs torrent-qbittorrent 2>&1 | grep -oP 'temporary password for the admin user is: \K\S+'))
	@if [ -z "$(QB_PASS)" ]; then echo "qBittorrent not running or password not found"; exit 1; fi
	@echo "Logging in with temporary password..."
	@curl -s -c /tmp/qb_cookies -b /tmp/qb_cookies \
		-d "username=admin&password=$(QB_PASS)" \
		http://localhost:8080/api/v2/auth/login > /dev/null
	@echo "Setting new password to 'adminadmin'..."
	@curl -s -b /tmp/qb_cookies \
		-d "newpass=adminadmin" \
		http://localhost:8080/api/v2/user/changePassword > /dev/null
	@echo "Done. Set QBITTORRENT_PASSWORD=adminadmin before running integration tests."

setup:
	mkdir -p data/qbittorrent/config data/qbittorrent/downloads
	mkdir -p data/transmission/config data/transmission/downloads
	mkdir -p data/rtorrent/data data/rtorrent/passwd
	mkdir -p data/deluge/config data/deluge/downloads
	mkdir -p data/rqbit/db data/rqbit/cache data/rqbit/downloads
	mkdir -p data/aria2/config data/aria2/downloads

clean:
	rm -rf data
