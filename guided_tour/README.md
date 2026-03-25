# Guided Tour para Drupal 11
## Dos enfoques: Custom Shepherd.js vs Tour contrib 2.x

---

## ENFOQUE A — Módulo custom con Shepherd.js (RECOMENDADO)

### Instalación

```bash
# 1. Copiar el módulo a tu proyecto
cp -r guided_tour /path/to/drupal/web/modules/custom/

# 2. Habilitar
drush pm:enable guided_tour
drush cr
```

### Añadir data-tour a tus Web Components (Storybook/Tailwind)

En tus Web Components, añade el atributo `data-tour` en el template del componente.
Shepherd lo usa como selector CSS: `[data-tour="nombre"]`.

**Ejemplo en un LitElement / Web Component:**
```javascript
// mi-header.component.js
render() {
  return html`
    <header data-tour="main-header" class="...clases tailwind...">
      ...
    </header>
  `;
}
```

**Ejemplo en un componente Storybook:**
```html
<!-- En el template HTML del story -->
<app-header data-tour="main-header"></app-header>
<nav-menu data-tour="main-nav"></nav-menu>
<search-bar data-tour="search-bar"></search-bar>
```

### Crear un nuevo tour

Crea un archivo YAML en `config/install/` o impórtalo con Drush:

```yaml
# guided_tour.tour.mi_tour.yml
id: mi_tour
label: 'Mi tour personalizado'
status: true
routes:
  - '<front>'
roles:
  - anonymous
  - authenticated
cookie_days: 365
wait_for_wc: true
options:
  useModalOverlay: true
steps:
  -
    id: paso-1
    attachTo:
      element: '[data-tour="mi-componente"]'
      on: bottom
    title: 'Título del paso'
    text: 'Descripción del paso.'
    buttons:
      - { text: 'Siguiente', type: next }
      - { text: 'Omitir', type: cancel, secondary: true }
```

```bash
drush config:import --partial --source=modules/custom/guided_tour/config/install
drush cr
```

### Limpiar cookie para re-ver el tour (desarrollo)

```javascript
// En la consola del navegador:
document.cookie.split(';').forEach(c => {
  if (c.includes('guided_tour_')) {
    document.cookie = c.split('=')[0] + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
  }
});
location.reload();
```

### Escuchar eventos del tour desde tus WC

```javascript
// En cualquier Web Component o JS del sitio:
document.addEventListener('guidedTour:complete', (e) => {
  console.log('Tour completado:', e.detail.tourId, 'por rol:', e.detail.role);
  // Aquí puedes, por ejemplo, hacer tracking con GA4 o Matomo.
  gtag('event', 'tour_complete', { tour_id: e.detail.tourId });
});

document.addEventListener('guidedTour:cancel', (e) => {
  console.log('Tour cancelado:', e.detail);
});
```

---

## ENFOQUE B — Tour contrib 2.x

### Instalación

```bash
composer require drupal/tour
drush pm:enable tour
drush cr
```

### Usar la UI gráfica

1. Ir a **Administración > Configuración > Interfaz de usuario > Tours**
   (`/admin/config/user-interface/tour`)
2. Crear un tour con el botón "Add tour"
3. Definir la ruta donde aparece
4. Añadir pasos con selector CSS
5. Guardar — aparecerá el botón "Tour" en el toolbar de esa ruta

### Crear tour via YAML (alternativa sin UI)

Ver archivo `tour_contrib_guide.yml` en este directorio.

```bash
# Importar config YAML
drush config:import --partial --source=config/install
```

### Limitaciones importantes para tu caso

| Limitación | Impacto en tu proyecto |
|---|---|
| Solo usuarios autenticados | ❌ No sirve para anónimos |
| Requiere toolbar de Drupal | ❌ Puede no estar en el front-end |
| No espera Web Components | ⚠️ Los selectores pueden fallar |
| Shepherd.js versión fija | ⚠️ Puede quedar desactualizado |
| CSS difícil de personalizar | ⚠️ Conflictos con Tailwind |

---

## Tabla comparativa final

| Criterio                  | Custom Shepherd.js | Tour contrib 2.x |
|---------------------------|:-----------------:|:----------------:|
| Usuarios anónimos         | ✅                 | ❌               |
| Roles específicos         | ✅ Total           | ⚠️ Limitado      |
| Web Components (waitForWC)| ✅                 | ❌               |
| Compatible con Tailwind   | ✅ (prefijo CSS)   | ⚠️               |
| UI gráfica                | ❌ (YAML)          | ✅               |
| Sin desarrollo            | ❌                 | ✅               |
| Shepherd.js actualizado   | ✅                 | ⚠️               |
| Eventos custom JS         | ✅                 | ❌               |
| Cookie de dismissal       | ✅ PHP+JS          | ⚠️ Solo JS       |

**Recomendación para tu proyecto:** Módulo custom (Enfoque A).
Tour contrib es viable únicamente si los tours son exclusivamente
para administradores/editores autenticados con acceso al toolbar.
