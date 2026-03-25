/**
 * @file guided_tour.js
 *
 * Inicializa Shepherd.js. Soporta:
 *   - Arranque automático (autoPlay) o solo bajo demanda (botón replay)
 *   - Botón #guided-tour-replay-btn para relanzar el tour en cualquier momento
 *   - Web Components (waitForWC via customElements.whenDefined)
 *   - Cookie de dismissal con opción de borrarla desde el botón
 */

(function (Drupal, drupalSettings, once) {

  'use strict';

  // ── Utilidades de cookie ───────────────────────────────────────────────────
  let activeTour = null;
  function setCookie(name, value, days) {
    const exp = new Date(Date.now() + days * 864e5).toUTCString();
    document.cookie = `${name}=${encodeURIComponent(value)}; expires=${exp}; path=/; SameSite=Lax`;
  }

  function cleanupShepherd() {
    // Overlay SVG
    document.querySelectorAll('.shepherd-modal-overlay-container').forEach(el => el.remove());
    // Tooltips/steps que puedan haber quedado
    document.querySelectorAll('.shepherd-element').forEach(el => el.remove());
    // Clase que Shepherd añade al body
    document.body.classList.remove('shepherd-has-active-tour');
    // Atributo data que Shepherd pone en el body
    document.body.removeAttribute('data-shepherd-active-tour');
  }

  function deleteCookie(name) {
    document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; SameSite=Lax`;
  }

  // ── Esperar elementos en el DOM ────────────────────────────────────────────

  function waitForElement(selector, timeout = 5000) {
    return new Promise((resolve) => {
      const el = document.querySelector(selector);
      if (el) return resolve(el);

      const observer = new MutationObserver(() => {
        const found = document.querySelector(selector);
        if (found) { observer.disconnect(); resolve(found); }
      });
      observer.observe(document.body, { childList: true, subtree: true });
      setTimeout(() => { observer.disconnect(); resolve(document.querySelector(selector)); }, timeout);
    });
  }

  // ── Esperar Web Components ─────────────────────────────────────────────────

  async function waitForWebComponents(steps) {
    const wcRegex = /^[a-z][\w-]*-[\w-]+/;
    const promises = steps
      .map(s => s.attachTo?.element)
      .filter(Boolean)
      .map(selector => {
        if (wcRegex.test(selector)) return customElements.whenDefined(selector);
        const match = selector.match(wcRegex);
        return match ? customElements.whenDefined(match[0]) : Promise.resolve();
      });
    await Promise.allSettled(promises);
  }

  // ── Construir pasos de Shepherd ────────────────────────────────────────────

  function buildSteps(config, tour) {
    const total = config.steps.length;

    return config.steps.map((stepDef, index) => {
      const isLast = index === total - 1;

      // ── Botones ──────────────────────────────────────────────────────────
      const buttons = (stepDef.buttons || []).map(btn => {
        const map = {
          next: { text: btn.text, action: tour.next.bind(tour), classes: 'guided-tour__btn guided-tour__btn--primary' },
          back: { text: btn.text, action: tour.back.bind(tour), classes: 'guided-tour__btn guided-tour__btn--secondary' },
          cancel: { text: btn.text, action: tour.cancel.bind(tour), classes: 'guided-tour__btn guided-tour__btn--ghost' },
        };
        return map[btn.type] ?? { text: btn.text, action: tour.next.bind(tour) };
      });

      // ── Progress dots ─────────────────────────────────────────────────────
      const dotsHtml = `
      <div class="shepherd-progress-dots">
        ${Array.from({ length: total }, (_, i) => {
        const cls = i === index
          ? 'shepherd-progress-dots__dot shepherd-progress-dots__dot--active'
          : i < index
            ? 'shepherd-progress-dots__dot shepherd-progress-dots__dot--past'
            : 'shepherd-progress-dots__dot';
        return `<div class="${cls}"></div>`;
      }).join('')}
      </div>`;

      // ── Cuerpo del step ───────────────────────────────────────────────────
      const textHtml = `<p class="guided-tour__text">${stepDef.text}</p>${dotsHtml}`;

      return {
        id: stepDef.id,
        title: stepDef.title,
        text: textHtml,
        attachTo: stepDef.attachTo ?? {},
        buttons,
        classes: isLast
          ? 'guided-tour__step guided-tour__step--last'
          : 'guided-tour__step',
        scrollTo: { behavior: 'smooth', block: 'center' },
        cancelIcon: { enabled: true },
        when: {
          show() {
            // ── Inyectar dot animado + "Paso X de N" en el header ──────────
            const header = this.el?.querySelector('.shepherd-header');
            if (header && !header.querySelector('.shepherd-step-indicator')) {
              const indicator = document.createElement('div');
              indicator.className = 'shepherd-step-indicator';
              indicator.innerHTML = `
              <div class="shepherd-step-indicator__dot"></div>
              <span class="shepherd-step-indicator__text">
                ${Drupal.t('Paso @current de @total', {
                '@current': index + 1,
                '@total': total,
              })}
              </span>`;
              // Insertar antes del botón X (cancelIcon)
              header.prepend(indicator);
            }

            // ── Validar elemento al que se adjunta ─────────────────────────
            if (stepDef.attachTo?.element && !document.querySelector(stepDef.attachTo.element)) {
              console.warn(`[GuidedTour] Elemento no encontrado: "${stepDef.attachTo.element}"`);
            }
          },
        },
      };
    });
  }

  // ── Crear y configurar la instancia de Shepherd ───────────────────────────

  function createTour(config) {
    const tour = new Shepherd.Tour({
      useModalOverlay: config.options?.useModalOverlay ?? true,
      defaultStepOptions: {
        cancelIcon: { enabled: true },
        classes: 'guided-tour__step',
        scrollTo: { behavior: 'smooth', block: 'center' },
        ...(config.options?.defaultStepOptions ?? {}),
      },
    });

    buildSteps(config, tour).forEach(step => tour.addStep(step));

    // Al completar o cancelar: guardar cookie y disparar evento.
    const onFinish = (eventName) => {
      if (config.cookieDays > 0) {
        setCookie(config.cookieName, '1', config.cookieDays);
      }
      activeTour = null;

      // Eliminar overlay huérfano que Shepherd deja en el DOM
      document.querySelectorAll('.shepherd-modal-overlay-container').forEach(el => el.remove());

      document.dispatchEvent(new CustomEvent(`guidedTour:${eventName}`, {
        detail: { tourId: config.tourId, role: config.userRole },
      }));
    };

    tour.on('complete', () => onFinish('complete'));
    tour.on('cancel', () => onFinish('cancel'));

    return tour;
  }

  // ── Mostrar el botón de replay ─────────────────────────────────────────────

  function showReplayButton(config, onReplay) {
    const wrapper = document.querySelector('.guided-tour-trigger');
    const btn = document.getElementById('guided-tour-replay-btn');

    if (!wrapper || !btn) return;

    // Hacer visible el bloque (estaba oculto por defecto).
    wrapper.style.display = '';
    wrapper.removeAttribute('aria-hidden');

    // Al pulsar: borrar la cookie, recrear el tour y arrancarlo.
    btn.addEventListener('click', () => {
      deleteCookie(config.cookieName);
      onReplay();
    });
  }

  // ── Behavior principal ─────────────────────────────────────────────────────

  Drupal.behaviors.guidedTour = {
    attach(context, settings) {
      once('guided-tour-init', 'html', context).forEach(async () => {
        const config = settings.guidedTour;
        if (!config || typeof Shepherd === 'undefined') return;

        // Preparar función de inicio (reutilizable para replay).
        let activeTour = null;  // ← fuera del behavior, en el scope del IIFE

        const startTour = async () => {
          // Destruir instancia anterior si existe
          if (activeTour) {
            activeTour.cancel();
            activeTour = null;
            // Limpiar overlay de la instancia anterior también aquí
            document.querySelectorAll('.shepherd-modal-overlay-container').forEach(el => el.remove());
          }

          cleanupShepherd();

          if (config.waitForWC) {
            await waitForWebComponents(config.steps);
          }
          const firstSelector = config.steps[0]?.attachTo?.element;
          if (firstSelector) {
            await waitForElement(firstSelector);
          }

          activeTour = createTour(config);

          // Limpiar referencia al terminar
          activeTour.on('complete', () => { activeTour = null; });
          activeTour.on('cancel', () => { activeTour = null; });

          setTimeout(() => activeTour.start(), 300);
        };
        // Arranque automático solo si autoPlay === true.
        try {
          if (config.autoPlay) {
            await startTour();
          }
        } catch (e) {
          console.error('[GuidedTour] Error al iniciar tour:', e);
        } finally {
          showReplayButton(config, startTour);
        }
        // El botón de replay SIEMPRE aparece si hay tour configurado.
        showReplayButton(config, startTour);
      });
    },
  };

})(Drupal, drupalSettings, once);
