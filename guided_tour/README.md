# Guided Tour

Provides an interactive guided tour experience for Drupal sites using
[Driver.js](https://driverjs.com/). Allows administrators to create
step-by-step tours to onboard users and highlight key features of the
interface.

## Table of contents

- [Requirements](#requirements)
- [Recommended modules](#recommended-modules)
- [Installation](#installation)
- [Configuration](#configuration)
- [Troubleshooting](#troubleshooting)
- [Maintainers](#maintainers)

## Requirements

- Drupal core: ^9 || ^10 || ^11
- [Driver.js](https://driverjs.com/) library (v1.4.x) — MIT License

## Recommended modules

- [Libraries API](https://www.drupal.org/project/libraries): Useful for
  managing the Driver.js local installation.
- [Advanced Help](https://www.drupal.org/project/advanced_help): Displays
  module help in the admin section.

## Installation

### Via Composer (recommended)

```bash
composer require drupal/guided_tour
```

Driver.js will be installed automatically into `/libraries/driver.js/`.

### Manual download (alternative)

1. Download Driver.js v1.4.x from
   [github.com/kamranahmedse/driver.js](https://github.com/kamranahmedse/driver.js/releases).
2. Place the files in `/libraries/driver.js/`.
3. Verify the following files exist:
   - `/libraries/driver.js/dist/driver.js.iife.js`
   - `/libraries/driver.js/dist/driver.css`

### Enable the module

```bash
drush en guided_tour && drush cr
```

Or navigate to **Administration > Extend** and enable **Guided Tour**.

## Configuration

Go to **Administration > Configuration > User Interface > Guided Tour**
and click **Add tour**. Fill in the following fields:

### General settings

- **Tour name** *(required)*: Internal identifier for the tour.
- **Enabled**: Toggle to activate or deactivate the tour without
  deleting it.

### Routes and conditions

- **Drupal routes**: One route per line. Use the internal Drupal route
  name (e.g. `entity.node.canonical`, `<front>`). Leave empty to match
  all routes.
- **Route parameters**: One parameter per line in `key: value` format
  (e.g. `node: 2707`). Use this to target a specific node, view, or
  form. Leave empty to ignore parameters.
- **Roles that see the tour**: Select which roles will see this tour.
  Leave all unchecked to display the tour to all roles.

### Behavior

- **Dismissal days**: Number of days before the tour reappears for a
  user. The tour is marked as seen when the user either completes all
  steps or clicks the dismiss/skip button. After the specified number
  of days, the tour will appear again. Use `0` to always show the tour
  on every visit (no cookie is stored). Example: `365` means the tour
  will not reappear for one year after the user completes or skips it.
- **Wait for Web Components**: Enable this option if your tour targets
  elements that are Web Components (Lit/Storybook). The tour will wait
  for them to upgrade before starting.
- **Dark overlay**: Displays a semi-transparent dark overlay behind the
  active highlighted element.

### Tour steps (YAML format)

Define the tour steps in YAML. Each step supports the following fields:

```yaml
- id: step-1
  title: 'Title of the first step'
  text: 'Brief description of what the user sees here.'
  attachTo:
    element: '[data-tour="my-component"]'
    on: bottom
  buttons:
    - text: 'Next'
      type: next
    - text: 'Skip tour'
      type: cancel

- id: step-2
  title: 'Second step'
  text: 'More information about this section.'
  attachTo:
    element: '[data-tour="other-component"]'
    on: right
  buttons:
    - text: 'Back'
      type: back
    - text: 'Next'
      type: next
```

**Supported fields per step:**

| Field              | Required | Description                                          |
|--------------------|----------|------------------------------------------------------|
| `id`               | ✓        | Unique identifier for the step                       |
| `title`            | ✓        | Step heading displayed to the user                   |
| `text`             | ✓        | Step body description                                |
| `attachTo.element` | ✗        | CSS selector of the highlighted element              |
| `attachTo.on`      | ✗        | Popover position: `top`, `bottom`, `left`, `right`   |
| `buttons`          | ✗        | Action buttons. Types: `next`, `back`, `cancel`      |

> **Tip:** To target Web Components or Lit elements, add a
> `data-tour="my-element"` attribute to the element and reference it
> in `attachTo.element` as `[data-tour="my-element"]`.

### Adding the tour trigger button

The module provides a block with a configurable button to manually
trigger the guided tour. To add it:

1. Go to **Administration > Structure > Block layout**.
2. Choose the region where the button should appear and click
   **Place block**.
3. Search for **Guided Tour Button** and click **Place block**.
4. Fill in the block settings:
   - **Title**: Block title displayed to the user.
   - **Button text**: Label shown on the button
     (e.g. `Do you need help? View tour`).
   - **Visual style**: Choose the button appearance:
     - `Floating button (FAB)`: Fixed floating action button always
       visible on screen.
5. Click **Save block**.

> **Tip:** Place the block in a visible region such as the sidebar or
> footer so users can easily find and relaunch the tour at any time.

## Troubleshooting

**The tour does not appear.**
- Confirm the user role has the **Access guided tour** permission.
- Check the library status at
  **Administration > Reports > Status report**.
- Clear the cache: `drush cr`.

**Styles are broken or missing.**
- Verify `/libraries/driver.js/dist/driver.css` exists.
- Run `drush cr` to rebuild the asset cache.

## Maintainers

- [Alejandro Pérez](https://www.drupal.org/u/alejandropr23)
