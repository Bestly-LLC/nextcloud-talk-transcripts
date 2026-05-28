# Talk Transcripts — Nextcloud app build/release helpers.
# Run `make package` to produce a tar.gz ready for apps.nextcloud.com upload.

app_name := talk_transcripts
build_dir := $(CURDIR)/build
release_dir := $(build_dir)/release
appstore_dir := $(build_dir)/appstore
source_dir := $(CURDIR)
version := $(shell xmllint --xpath 'string(/info/version)' appinfo/info.xml 2>/dev/null || echo 0.0.0)

# Files/dirs that ship in the released app
release_includes := \
	appinfo \
	lib \
	templates \
	js \
	css \
	img \
	translations \
	COPYING \
	LICENSE \
	README.md \
	CHANGELOG.md

.PHONY: all clean composer lint test package appstore

all: lint test

# ---- clean ----
clean:
	rm -rf $(build_dir)
	rm -rf vendor
	rm -f composer.lock

# ---- deps ----
composer:
	composer install --no-dev --optimize-autoloader

# ---- lint / test ----
lint:
	@echo ">> PHP lint"
	@find lib appinfo templates -name '*.php' -print0 | xargs -0 -n1 php -l > /dev/null

test:
	@if [ -d tests ]; then \
		vendor/bin/phpunit --colors=always tests; \
	else \
		echo ">> no tests/ yet — skipping"; \
	fi

# ---- package (apps.nextcloud.com format) ----
# Produces build/release/talk_transcripts-<version>.tar.gz
# The archive's top-level dir must be the app id (talk_transcripts), per app store rules.
package: clean composer
	mkdir -p $(release_dir)/$(app_name)
	@for item in $(release_includes); do \
		if [ -e "$$item" ]; then \
			cp -R "$$item" $(release_dir)/$(app_name)/; \
		fi; \
	done
	cd $(release_dir) && tar -czf $(app_name)-$(version).tar.gz $(app_name)
	@echo ">> built $(release_dir)/$(app_name)-$(version).tar.gz"

# alias
appstore: package
