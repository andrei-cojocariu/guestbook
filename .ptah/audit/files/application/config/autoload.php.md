---
path: application/config/autoload.php
part_of:
  - message-persistence
  - message-submission
  - timeline-rendering
used_by:
  - application/models/Guestbook_messages.php
  - application/controllers/Guestbook.php
  - application/views/guestbook_homepage.php
touches: []
---

# File: application/config/autoload.php

CodeIgniter global bootstrap load list. Runs on **every** request before the
controller, populating the CI singleton with shared resources.

## Responsibilities

- `application/config/autoload.php:61` — `$autoload['libraries'] = array('database');`
  opens a DB connection on every request and attaches `$this->db` to the singleton.
- `application/config/autoload.php:92` — `$autoload['helper'] = array('url');`
  provides `site_url()` / `base_url()` used throughout the views.
- `packages`, `drivers`, `model`, `config`, `language` autoloads are all empty.

## Notes / debt

- **Undocumented coupling** — the global `database` autoload is *why*
  `Guestbook_messages` can call `$this->db` even though its constructor omits
  `parent::__construct()` (`#model-ctor`, BUG-3). The model does not load the DB
  itself; it inherits the connection from this global. Remove or refactor this
  autoload and the broken constructor stops working.
- Every request connects to MySQL even when the response needs no data — no lazy
  connection. Relevant to the persistence-boundary seam (`#active-record-coupling`,
  STR-1): the port must own connection lifetime.

## Blast radius

Changing this list affects every request. Dropping `database` breaks the model
(and the whole app) until a repository port owns the connection; dropping `url`
breaks `site_url()`/`base_url()` in every view (logo, form action, asset paths).
