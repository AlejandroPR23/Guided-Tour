# Guided Tour

Provides guided tours using [Driver.js](https://driverjs.com) for Drupal 10 and 11.
Tours work for **anonymous and authenticated users**, support **role-based targeting**,
and are fully compatible with **Web Components** (Storybook / Lit / Tailwind).

---

## Features

- Role-based tours (anonymous, editor, administrator, or any custom role)
- Compatible with Web Components — waits for custom elements to upgrade before starting
- Cookie-based dismissal (configurable days per tour)
- Replay button block (primary button, link, or FAB styles)
- YAML-based tour configuration (importable via `drush config:import`)
- Admin UI to create and manage tours without code
- Custom JS events (`guidedTour:complete`, `guidedTour:cancel`) for analytics integration
- Compatible with Tailwind CSS (scoped CSS variables, no conflicts)

---

## Requirements

- Drupal 10.x or 11.x
- PHP 8.1+
- Composer
- The [asset-packagist](https://asset-packagist.org) repository configured in your project

---

## Installation

### Step 1 — Configure asset-packagist in your project root

Open your **project root** `composer.json` (not this module's) and add two blocks.

#### 1a. Add the asset-packagist repository

```json
"repositories": [
    {
        "type": "composer",
        "url": "https://asset-packagist.org"
    }
]
```

#### 1b. Add the installer path for npm assets

Inside `"extra"` → `"installer-paths"`, add this entry so Driver.js lands in `web/libraries/`:

```json
"extra": {
    "installer-types": ["npm-asset", "bower-asset"],
    "installer-paths": {
        "web/libraries/{$name}": [
            "type:npm-asset",
            "type:bower-asset"
        ]
    }
}
```

> If you already have `"installer-paths"` configured, just add the `"web/libraries/{$name}"` entry — do not duplicate the whole block.

#### 1c. Require the oomphinc/composer-installers-extender plugin

This plugin is needed for Composer to know how to handle `npm-asset` packages:

```bash
composer require oomphinc/composer-installers-extender
```

---

### Step 2 — Require the module

```bash
composer require drupal/guided_tour
```

Composer will automatically download Driver.js into `web/libraries/driver.js/`.

---

### Step 3 — Enable the module

```bash
drush pm:enable guided_tour
drush cr
```

---

## Verifying the installation

After running Composer, confirm Driver.js was downloaded:

```bash
ls web/libraries/driver.js/dist/
# Expected: driver.js.iife.js  driver.css  (plus other files)
```

If the folder is missing, run:

```bash
composer install
```

---

## Creating tours

### Option A — Admin UI

1. Go to **Administration > Configuration > User Interface > Guided Tours**
   (`/admin/config/user-interface/guided-tour`)
2. Click **Add guided tour**
3. Fill in the label, routes, roles, and steps
4. Save

### Option B — YAML configuration files

Create a file in `config/install/` of your custom module:

```yaml
# my_module/config/install/guided_tour.tour.my_tour.yml
id: my_tour
label: 'My tour'
status: true
routes:
  - '<front>'
roles:
  - anonymous
cookie_days: 365
wait_for_wc: true
options:
  useModalOverlay: true
steps:
  -
    id: step-welcome
    attachTo:
      element: '[data-tour="main-header"]'
      on: bottom
    title: 'Welcome!'
    text: 'This is the homepage.'
    buttons:
      - { text: 'Next', type: next }
      - { text: 'Skip', type: cancel, secondary: true }
  -
    id: step-nav
    attachTo:
      element: '[data-tour="main-nav"]'
      on: bottom
    title: 'Navigation'
    text: 'Use this menu to browse the site.'
    buttons:
      - { text: 'Back', type: back }
      - { text: 'Got it!', type: next }
```

Import it:

```bash
drush config:import --partial --source=modules/custom/my_module/config/install
drush cr
```

---

## Adding `data-tour` attributes to your components

Shepherd uses CSS selectors to attach tour steps to elements.
Add `data-tour` attributes to any element you want to highlight:

```html
<header data-tour="main-header">...</header>
<nav data-tour="main-nav">...</nav>
<input data-tour="search-bar" />
```

For **Web Components** (LitElement, etc.):

```javascript
render() {
  return html`
    <header data-tour="main-header" class="...">
      ...
    </header>
  `;
}
```

---

## Replay button block

Place the **Guided Tour Button** block in any theme region:

1. Go to **Structure > Block layout**
2. Click **Place block** in the desired region
3. Search for "Guided Tour Button"
4. Configure label, style (`button`, `link`, or `fab`), and icon
5. Save

The block is hidden automatically on pages without an active tour.

---

## JavaScript events

Listen to tour lifecycle events from any JS file or Web Component:

```javascript
document.addEventListener('guidedTour:complete', (e) => {
  console.log('Tour completed:', e.detail.tourId, 'role:', e.detail.role);
  // Example: send to GA4
  gtag('event', 'tour_complete', { tour_id: e.detail.tourId });
});

document.addEventListener('guidedTour:cancel', (e) => {
  console.log('Tour cancelled:', e.detail);
});
```

---

## Clearing the dismissal cookie (development)

To re-trigger a tour that has already been dismissed:

```javascript
// Paste in browser console:
document.cookie.split(';').forEach(c => {
  if (c.includes('guided_tour_')) {
    document.cookie = c.split('=')[0] + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
  }
});
location.reload();
```

---

## Configuration reference

| Key            | Type    | Default | Description                                              |
|----------------|---------|---------|----------------------------------------------------------|
| `routes`       | list    | `[]`    | Drupal route names where the tour appears. Empty = all.  |
| `route_params` | map     | `{}`    | Filter by specific route parameters (e.g. `node: 42`).  |
| `roles`        | list    | `[]`    | Roles that see the tour. Empty = all roles.              |
| `cookie_days`  | integer | `365`   | Days to remember dismissal. `0` = never remember.       |
| `wait_for_wc`  | boolean | `true`  | Wait for Web Components to upgrade before starting.      |
| `options`      | map     | —       | Driver.js global options (e.g. `useModalOverlay: true`). |
| `steps`        | list    | `[]`    | Tour steps (see YAML example above).                     |

### Step keys

| Key        | Required | Description                                                     |
|------------|----------|-----------------------------------------------------------------|
| `id`       | Yes      | Unique step identifier.                                         |
| `title`    | Yes      | Step popover title (translatable).                              |
| `text`     | Yes      | Step popover body text (translatable).                          |
| `attachTo` | No       | `element` (CSS selector) and `on` (top/right/bottom/left).     |
| `buttons`  | No       | List of buttons with `text` and `type` (next/back/cancel).     |

---

## Comparison with Tour contrib

| Feature                    | Guided Tour (this module) | Tour contrib 2.x  |
|----------------------------|:-------------------------:|:-----------------:|
| Anonymous users            | ✅                        | ❌                |
| Role targeting             | ✅ Full                   | ⚠️ Limited        |
| Web Components support     | ✅                        | ❌                |
| Tailwind CSS compatible    | ✅                        | ⚠️                |
| Admin UI                   | ✅                        | ✅                |
| Custom JS events           | ✅                        | ❌                |
| Cookie-based dismissal     | ✅ PHP + JS               | ⚠️ JS only        |
| Driver.js up to date       | ✅                        | ⚠️                |
| Requires Drupal toolbar    | ❌ Not required           | ✅ Required       |

---

## Troubleshooting

**Driver.js files not found (`web/libraries/driver.js/` is empty)**
Run `composer install` and verify that `oomphinc/composer-installers-extender` is installed
and the `installer-paths` configuration includes `"type:npm-asset"`.

**Tour does not appear**
- Verify the tour is enabled in the admin UI.
- Check the route name matches exactly (use `drush route` to list routes).
- Check the role matches the current user.
- Open DevTools → Application → Cookies and confirm no `guided_tour_dismissed_*` cookie exists.

**Tour starts before Web Components render**
Set `wait_for_wc: true` in the tour YAML and ensure your components use standard
Custom Elements v1 (`customElements.define()`).

---

## Maintainers

- [Your Drupal.org username](https://www.drupal.org/u/your-username)