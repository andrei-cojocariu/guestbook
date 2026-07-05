# Design System — Guestbook

Status: **Pending** (draft baseline, extracted from the existing eaKroko/Bootstrap
theme in `sass/` + `css/` and the two live views under
`application/views/guestbook_components/`). Every entry below is a description of
the *de-facto* system already in the tree, not a new design decision — it needs
human/CTO sign-off the same way `standards.md` rules do before it is treated as a
binding gate. Until sign-off, treat this as the reference the ux-engineer-worker
judges diffs against, and update it in place when a task legitimately extends the
system.

## Stack

Bootstrap 3 (`css/bootstrap.min.css`) + the "eaKroko" admin-dashboard theme
(`sass/*.scss` compiled to `css/style.css` / `css/themes.css`), loaded globally via
`application/views/template/css.php` / `template/js.php`. The guestbook is a single
public-facing page (`guestbook_homepage.php`) built from an admin-dashboard
component library — most of the theme's plugin surface (icheck, datatables,
ckeditor, colorpicker, etc., see `js/plugins/*`) is unused dead weight on this page;
noted, not this task's scope to prune.

## Tokens

### Color (`sass/colors.scss`)

| Token | Value | Observed use |
| :--- | :--- | :--- |
| `$blue` | `#368ee0` | primary accent — timeline `.icon` background, `.search-form` submit |
| `$darkBlue` | `#204e81` | secondary accent |
| `$green` | `#339933` | success / positive state color name (`.icon.green` used in timeline despite `$blue` value — see Finding below) |
| `$red` / `$lightRed` | `#E51400` / `#e63a3a` | danger / error |
| `$grey` / `$lightGrey` | `#333333` / `#666666` | text |
| `$grey-3` | `#eee` | hairline borders, `.line`, `.date` background |
| `$grey-4` | `#999` | muted text |
| `$dark` / `$light` / `$bg` | `#2a2a2a` / `#fff` / `#eee` | surface tokens |

No numeric type scale, spacing scale, radius scale, or shadow scale is centrally
declared as SCSS variables — spacing/radius are hand-authored per component (see
DRY finding below). This is a gap in the current system; **draft recommendation**:
extract a spacing scale from the observed values (`5px, 10px, 15px, 30px` recur
across `box.scss`, `forms.scss`, `timeline.scss`) the next time a component is
touched, rather than adding another bespoke value.

### Breakpoints (`sass/media-queries.scss`)

Single custom mixin `breakpoint($width)` → `max-width` media queries, values used
ad hoc per rule (`1250px`, `1200px`, and further breakpoints not enumerated here).
No named breakpoint tier (sm/md/lg) — inherits Bootstrap 3 grid breakpoints
(`col-sm-*` used in `form.php`) for layout, but custom component CSS uses raw
pixel breakpoints independently. Flag as design debt if a future change adds
another one-off pixel breakpoint instead of reusing an existing one.

## Component inventory (canonical, as observed)

- **Box** (`.box.box-color.box-bordered` + `.box-title` + `.box-content`) —
  card/panel container. Used by the timeline (`timeline.php`). Canonical for any
  "titled panel" — reuse before inventing a bespoke bordered `<div>`.
- **Form field** (`.form-group` > `.input-group` > `.input-group-addon` (icon) +
  `form_input()`/`form_textarea()` + inline `form_error()`) — `form.php`. Labeling
  is **placeholder-only** (no `<label for>`) — see a11y finding, logged to
  `legacy_debt.md`.
- **Button** — Bootstrap `.btn.btn-primary` (`form_submit`) — no bespoke button
  styles observed in this change's surface; the canonical is Bootstrap's.
- **Alert** — `.alert.alert-success` / `.alert.alert-danger.alert-dismissable`
  with a `.close` `×` dismiss button — `form.php`. Canonical for success/error
  feedback after the POST.
- **Timeline list** (`.timeline > li > .timeline-content` with `.left`
  icon+date and `.activity` user+message) — `timeline.php` /
  `sass/page-elements/timeline.scss`. Canonical for the "activity feed" pattern;
  only one instance in the codebase today, so no duplication yet — see DRY note.

## States coverage (as observed, not yet complete)

| State | Present? | Where |
| :--- | :--- | :--- |
| Success | Yes | `form.php` `.alert-success` |
| Error (validation) | Yes | `form.php` `.alert-danger` + per-field `has-error` + `form_error()` |
| Error (persistence failure) | **No** — see `legacy_debt.md` BUG-2 (silent success banner on failed insert) | — |
| Empty (no messages yet) | Minimal — `guestbook_homepage.php` renders a bare `<br>`, no message | `guestbook_homepage.php` |
| Loading | N/A (full page POST/redirect, no async UI) | — |
| Hover | Yes | `.timeline > li:hover` background tint |
| Focus (visible) | Not verified — relies on unmodified Bootstrap 3 default focus ring; no custom `:focus` override observed on timeline/form elements beyond `.search-form input` (unrelated component) | — |
| Disabled | Not observed on the submit button | `form.php` |

## Reconciliation log

- 2026-07-05 — baseline drafted while reviewing `decouple/tsk-005`
  (output-encoding boundary). No component/token additions required by that
  diff — it only wraps three existing echoed fields in `html_escape()`, no
  markup/style change. No update to canonical components was needed.
- 2026-07-05 — reviewed `chore/tsk-006` (enable native CSRF). Diff is
  `csrf_protection = FALSE -> TRUE` in `config.php` only; `form.php` is
  untouched — CI3's `form_open()` already auto-emits a `type="hidden"` CSRF
  input into the existing `.form-validate` form (verified against
  `system/helpers/form_helper.php:101-134`). A hidden input is not
  focusable/rendered, so it changes no token, spacing, component, state, or
  tab order. No design-system update required; no new design debt from this
  task. Pre-existing form a11y/state debt (`UX-1`..`UX-4` in
  `legacy_debt.md`) is unaffected and unchanged by this diff.
