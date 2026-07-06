# syntax=docker/dockerfile:1
#
# Phase 2 — Modernize (PTAH MIG-04, hop H3 — projects/guestbook2/migration/
# ROADMAP.md in the Ptah ledger). The Phase-1 cryogenic freeze (php:5.6.40,
# tsk-002) served its purpose: the characterization net recorded frozen
# behavior and now guards every hop. Runtime pinned to PHP 8.1.32 — the
# highest PHP CodeIgniter 3.1.13 supports; a bridge held only until the CI4
# port (MIG-07..09). Exact tags, never floating.
FROM php:8.1.32-apache

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

# --- PCOV: native line-coverage driver for the ratcheted coverage gate
# (infra/qa-gates.yml in the Ptah ledger). Replaces the CI-only Xdebug 2.5.5
# QA image the frozen runtime needed. Disabled by default so the serving
# runtime pays zero overhead; the coverage run enables it via
# `php -d pcov.enabled=1`.
RUN pecl install pcov-1.0.11 \
    && docker-php-ext-enable pcov \
    && echo 'pcov.enabled=0' > /usr/local/etc/php/conf.d/docker-php-ext-pcov-default.ini

# --- Composer 2.x (MIG-03): Composer 1 is EOL and its Packagist metadata
# protocol is dead (DEBT-7). Pinned, never floating.
COPY --from=composer:2.8.9 /usr/bin/composer /usr/bin/composer

# --- PHPUnit: pinned as a container-level phar rather than a Composer
# dependency (rejection-1 TAC 3 remediation) — sidesteps DEBT-7's broken
# require-dev manifest entirely. 9.6 is the PHPUnit series spanning the
# PHP 7.4 -> 8.1 hops (H2-H3); it moves again with the CI4 port (MIG-09).
RUN curl -L https://phar.phpunit.de/phpunit-9.6.13.phar -o /usr/local/bin/phpunit \
    && chmod +x /usr/local/bin/phpunit

WORKDIR /var/www/html
COPY . /var/www/html

# DEBT-7 retired (MIG-03): the dead require-dev block (invalid
# `mikey179/vfsStream` casing, Composer-1-only phpunit constraint) is gone
# from composer.json — Composer 2 validates names strictly and would refuse
# the whole manifest. The manifest declares the php platform floor only;
# PHPUnit stays a pinned phar, exposed at vendor/bin for hooks.test.
RUN composer install --no-interaction --no-dev --no-progress \
    && mkdir -p vendor/bin \
    && cp /usr/local/bin/phpunit vendor/bin/phpunit \
    && chmod +x vendor/bin/phpunit \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80
