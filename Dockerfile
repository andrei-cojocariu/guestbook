# syntax=docker/dockerfile:1
#
# Phase 2 — Modernize (PTAH MIG-02, hop H2 — projects/guestbook2/migration/
# ROADMAP.md in the Ptah ledger). The Phase-1 cryogenic freeze (php:5.6.40,
# tsk-002) served its purpose: the characterization net recorded frozen
# behavior and now guards every hop. Runtime pinned to PHP 7.4.33 (final 7.4)
# on CodeIgniter 3.1.13 — exact tags, never floating.
FROM php:7.4.33-apache

# --- PHP extensions the product actually requires ---------------------------
# mysqli   -> application/config/database.php:82 'dbdriver' => 'mysqli'
# mbstring -> CodeIgniter 3.x core string/multibyte handling (needs libonig
#             to compile on PHP >= 7.4 images)
# opcache  -> the Modernize performance lever (absent in the freeze)
RUN apt-get update && apt-get install -y --no-install-recommends libonig-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install mysqli mbstring opcache

# --- Apache: mod_rewrite, pinned per tsk-002 TAC -----------------------------
RUN a2enmod rewrite

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
