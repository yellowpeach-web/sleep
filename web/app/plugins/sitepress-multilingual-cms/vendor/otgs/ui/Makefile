.PHONY: help list
.PHONY: precommit
.PHONY: dupes comp jest phpunit test dev prod
.PHONY: setup test

setup:: vendor/autoload.php
setup:: package-lock.json
setup:: githooks

test:: jest
test:: phpunit

precommit:: validate-composer
precommit:: validate-npm
precommit:: dupes
precommit:: compatibility

# precommit

dupes: vendor/autoload.php
	./.make/check-duplicates.sh

compatibility: vendor/autoload.php
	./.make/check-compatibility.sh

validate-composer: composer.lock
	./.make/check-composer.sh

validate-npm: package-lock.json
	./.make/check-npm.sh

# Dependency managers

## Composer
composer.lock: composer.json
	composer install
	touch $@

composer.json:
	composer init -q

vendor/autoload.php: composer.lock
	composer install
	touch $@

## Yarn
package-lock.json: package.json
	npm ci
	touch $@

package.json: 
	npm init -y

# Setup

githooks:
	find .git/hooks -type l -exec rm {} \;
	find .githooks -type f -exec ln -sf ../../{} .git/hooks/ \;

# Tests

jest: package-lock.json
	npm run test

phpunit: vendor/autoload.php
	vendor/bin/phpunit --fail-on-warning

# Build

dev prod: package-lock.json
	npm run build:$@
