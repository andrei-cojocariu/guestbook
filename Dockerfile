# syntax=docker/dockerfile:1
#
# Phase 1 — Cryogenic Freeze (tsk-002).
#
# Pins the EXACT obsolete runtime this CodeIgniter 3.1.5 monolith already runs
# on today — no upgrades, no floating tags. Evidence: composer.json declares
# "php": ">=5.3.7"; system/core/CodeIgniter.php:58 pins CI_VERSION '3.1.5'
# (2017, EOL). This image exists solely so the test-engineer-worker can run
# black-box characterization tests (tsk-003) against frozen, reproducible
# legacy behavior — it is a baseline artifact, not a target to grow into.
#
# Phase 2 (LTS modernization of this runtime) is explicitly NOT this task:
# .ptah/audit/system.md ("No framework replacement ... a runtime/framework
# migration is out of this design's scope") and .ptah/audit/legacy_debt.md
# (DEBT-4, "explicitly out of scope / deferred") both defer it to a future,
# separately-scheduled initiative. Do not bump the base image tag here.
FROM php:5.6.40-apache

# --- PHP extensions the product actually requires (system.md evidence) -----
# mysqli   -> application/config/database.php:82 'dbdriver' => 'mysqli'
# mbstring -> CodeIgniter 3.x core string/multibyte handling
RUN docker-php-ext-install mysqli mbstring

# --- Apache: mod_rewrite, pinned per tsk-002 TAC -----------------------------
RUN a2enmod rewrite

# --- Apache env passthrough for CI_ENV (tsk-004 infra fix) ------------------
# index.php:56 resolves ENVIRONMENT from $_SERVER['CI_ENV'], not getenv().
# Under the Apache/mod_php SAPI, getenv('...') reads the process environment
# directly (confirmed working for DB_HOSTNAME et al.), but $_SERVER is NOT
# repopulated from that same OS environment per-request -- Apache keeps a
# separate "subprocess environment" table that mod_php merges into $_SERVER,
# and it stays empty unless a variable is explicitly named via
# SetEnv/PassEnv. Without this, CI_ENV=production set at the container level
# (docker-compose `environment:`/`docker compose run -e CI_ENV=...`) never
# reaches $_SERVER['CI_ENV'] for a real HTTP request, ENVIRONMENT silently
# falls back to 'development', and db_debug leaks SQL/connection detail in
# production -- a genuine deploy-path gap surfaced live by tsk-004's
# EnvSecretManagementTest (application/tests/config/EnvSecretManagementTest.php,
# TEST B). Explicitly whitelisting CI_ENV via PassEnv closes it without
# touching index.php (product code, software-developer-worker's territory).
RUN echo "PassEnv CI_ENV" > /etc/apache2/conf-enabled/ci-env-passenv.conf

# --- mysqli socket path, so the hardcoded 'localhost' hostname in
# application/config/database.php:78 actually finds mysqld ------------------
# mysqli/mysqlnd treat the literal string "localhost" as "connect via local
# Unix socket" (never TCP), and mysqlnd's compiled-in default socket path is
# /tmp/mysql.sock — NOT where the official `mysql:5.7` image (or a real
# same-host install) places its socket (/var/run/mysqld/mysqld.sock, shared
# with `db` via a compose volume). Point PHP at the real path explicitly
# rather than relying on a fragile /tmp/mysql.sock symlink.
RUN { \
        echo 'mysqli.default_socket=/var/run/mysqld/mysqld.sock'; \
        echo 'pdo_mysql.default_socket=/var/run/mysqld/mysqld.sock'; \
    } > /usr/local/etc/php/conf.d/docker-php-mysql-socket.ini

# --- date.timezone: pin explicitly, don't leave it implicit -----------------
# Nothing in system.md/composer.json/config.php records the original host's
# timezone, so an unset date.timezone silently falls back to UTC *with* a
# visible PHP warning on every date() call — and guestbook_components/
# timeline.php:23-24 calls date() on every page render (that's BUG-1, the
# characterized "always shows current time" bug — see legacy_debt.md). Left
# unset, that warning is rendered inline in the page HTML (SEC-5: db_debug /
# error display is on by default), which would corrupt the byte-for-byte
# characterization baseline tsk-003 records with an artifact of THIS
# container's default config, not of the frozen application. Pin UTC
# explicitly and silence the warning; flag for human confirmation if the
# original deployment actually ran a different zone.
RUN echo 'date.timezone=UTC' > /usr/local/etc/php/conf.d/docker-php-timezone.ini

# --- Composer 1.x: the last major series supporting PHP 5.6. Composer 2.3+
# requires PHP >= 7.2.5 and cannot run against this frozen runtime. Pinned to
# the final Composer 1.x release (1.10.26) rather than a floating tag.
COPY --from=composer:1.10.26 /usr/bin/composer /usr/bin/composer

# --- PHPUnit: pinned PHP-5.6-compatible release, installed as a container-
# level binary rather than a Composer dependency (rejection-1 TAC 3
# remediation). This deliberately sidesteps DEBT-7 (composer.json's
# require-dev is unresolvable on every Composer major — dead Composer-1
# Packagist protocol + invalid `mikey179/vfsStream` casing): PHPUnit's
# runnability inside the frozen image no longer depends on that broken
# manifest resolving at all. 5.7.27 is the last PHPUnit 5.x release (final
# PHPUnit series supporting PHP 5.6).
RUN curl -L https://phar.phpunit.de/phpunit-5.7.27.phar -o /usr/local/bin/phpunit \
    && chmod +x /usr/local/bin/phpunit

WORKDIR /var/www/html
COPY . /var/www/html

# composer.json's "require" declares only the "php" platform floor today (no
# real app-level packages), and require-dev (mikey179/vfsStream, invalid
# casing; phpunit/phpunit, now superseded by the pinned phar above) remains
# genuinely unresolvable on Packagist's dead Composer-1 metadata protocol —
# see .ptah/audit/legacy_debt.md DEBT-7. The committed `composer.lock`
# (generated once via Composer 2 in a modern-PHP stage, with --no-dev so the
# broken require-dev entries were never resolved) is what makes this step
# succeed now: Composer 1 installs strictly from the lock (zero real
# packages) instead of re-solving require-dev from scratch. The PENDING
# fallback below is kept only as a last-resort safety net if the lock is
# ever removed/invalidated — it must not silently mask a *new* composer
# failure unrelated to DEBT-7.
RUN set -eu; \
    if ! composer install --no-interaction --no-dev --no-progress > /tmp/composer-install.log 2>&1; then \
        if grep -q "could not be found in any version" /tmp/composer-install.log; then \
            echo "composer install: require-dev (mikey179/vfsStream, phpunit/phpunit) is unresolvable — dead Composer-1 Packagist protocol + invalid package casing (see .ptah/audit/legacy_debt.md). require has no real packages to install. Treating as PENDING, not a build failure."; \
        else \
            cat /tmp/composer-install.log; exit 1; \
        fi; \
    fi \
    && mkdir -p vendor/bin \
    && cp /usr/local/bin/phpunit vendor/bin/phpunit \
    && chmod +x vendor/bin/phpunit \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80
