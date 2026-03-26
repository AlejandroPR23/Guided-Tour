/**
 * @file
 * Inicializa Driver.js. Soporta:
 *   - Arranque automático (autoPlay) o solo bajo demanda (botón replay)
 *   - Botón #guided-tour-replay-btn para relanzar el tour en cualquier momento
 *   - Web Components (waitForWC via customElements.whenDefined)
 *   - Cookie de dismissal con opción de borrarla desde el botón
 */

(function (Drupal, drupalSettings, once) {

  'use strict';

  let activeTour = null;
  let activeTourFinishReason = null;

  function getDriverFactory() {
    if (window.driver && window.driver.js && typeof window.driver.js.driver === 'function') {
      return window.driver.js.driver;
    }

    if (window.driver && typeof window.driver.driver === 'function') {
      return window.driver.driver;
    }

    if (typeof window.driver === 'function') {
      return window.driver;
    }

    return null;
  }

  function setCookie(name, value, days) {
    const exp = new Date(Date.now() + days * 864e5).toUTCString();
    document.cookie = `${name}=${encodeURIComponent(value)}; expires=${exp}; path=/; SameSite=Lax`;
  }

  function deleteCookie(name) {
    document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; SameSite=Lax`;
  }

  function cleanupDriver() {
    document.querySelectorAll('.driver-popover').forEach((el) => el.remove());
    document.querySelectorAll('.driver-overlay').forEach((el) => el.remove());
    document.querySelectorAll('.driver-active-element').forEach((el) => {
      el.classList.remove('driver-active-element');
    });
    document.body.classList.remove('driver-active', 'driver-fade', 'driver-simple');
  }

  function waitForElement(selector, timeout = 5000) {
    return new Promise((resolve) => {
      const el = document.querySelector(selector);
      if (el) {
        resolve(el);
        return;
      }

      const observer = new MutationObserver(() => {
        const found = document.querySelector(selector);
        if (found) {
          observer.disconnect();
          resolve(found);
        }
      });

      observer.observe(document.body, { childList: true, subtree: true });
      setTimeout(() => {
        observer.disconnect();
        resolve(document.querySelector(selector));
      }, timeout);
    });
  }

  async function waitForWebComponents(steps) {
    const wcRegex = /^[a-z][\w-]*-[\w-]+/;
    const promises = steps
      .map((step) => step.attachTo && step.attachTo.element)
      .filter(Boolean)
      .map((selector) => {
        if (wcRegex.test(selector)) {
          return customElements.whenDefined(selector);
        }

        const match = selector.match(wcRegex);
        return match ? customElements.whenDefined(match[0]) : Promise.resolve();
      });

    await Promise.allSettled(promises);
  }

  function normalizeSide(side) {
    const allowed = ['top', 'right', 'bottom', 'left'];
    return allowed.includes(side) ? side : 'bottom';
  }

  function renderProgressDots(total, currentIndex) {
    return `
      <div class="guided-tour__progress-dots">
        ${Array.from({ length: total }, (_, i) => {
      const cls = i === currentIndex
        ? 'guided-tour__progress-dot guided-tour__progress-dot--active'
        : i < currentIndex
          ? 'guided-tour__progress-dot guided-tour__progress-dot--past'
          : 'guided-tour__progress-dot';
      return `<div class="${cls}"></div>`;
    }).join('')}
      </div>
    `;
  }

  function getStepButton(buttons, type) {
    return (buttons || []).find((button) => button.type === type) || null;
  }

  function updatePopover(step) {
    const popover = document.querySelector('.driver-popover.guided-tour__popover');
    if (!popover || !step || !step.guidedTourMeta) {
      return;
    }

    const meta = step.guidedTourMeta;
    const title = popover.querySelector('.driver-popover-title');
    const description = popover.querySelector('.driver-popover-description');
    const footer = popover.querySelector('.driver-popover-footer');
    const closeButton = popover.querySelector('.driver-popover-close-btn');
    const previousButton = popover.querySelector('.driver-popover-prev-btn');
    const nextButton = popover.querySelector('.driver-popover-next-btn');

    if (title && !popover.querySelector('.guided-tour__step-indicator')) {
      const indicator = document.createElement('div');
      indicator.className = 'guided-tour__step-indicator';
      indicator.innerHTML = `
        <div class="guided-tour__step-indicator-dot"></div>
        <span class="guided-tour__step-indicator-text">
          ${Drupal.t('Step @current of @total', {
        '@current': meta.index + 1,
        '@total': meta.total,
      })}
        </span>
      `;
      title.insertAdjacentElement('beforebegin', indicator);
    }

    if (description) {
      description.innerHTML = `
        <p class="guided-tour__text">${meta.text || ''}</p>
        ${renderProgressDots(meta.total, meta.index)}
      `;
    }

    const backButton = getStepButton(meta.buttons, 'back');
    const nextConfigButton = getStepButton(meta.buttons, 'next');
    const cancelButton = getStepButton(meta.buttons, 'cancel');

    if (previousButton) {
      if (backButton) {
        previousButton.textContent = backButton.text || Drupal.t('Anterior');
        previousButton.style.display = '';
      }
      else {
        previousButton.style.display = 'none';
      }
    }

    if (nextButton) {
      if (nextConfigButton) {
        nextButton.textContent = nextConfigButton.text || (meta.isLast ? Drupal.t('Finalizar') : Drupal.t('Siguiente'));
        nextButton.style.display = '';
      }
      else {
        nextButton.style.display = 'none';
      }
    }

    if (closeButton && !closeButton.dataset.guidedTourBound) {
      closeButton.dataset.guidedTourBound = 'true';
      closeButton.addEventListener('click', () => {
        activeTourFinishReason = 'cancel';
      });
    }

    if (nextButton) {
      nextButton.onclick = () => {
        if (meta.isLast) {
          activeTourFinishReason = 'complete';
        }

        if (activeTour) {
          activeTour.moveNext();
        }
      };
    }

    if (previousButton) {
      previousButton.onclick = () => {
        if (activeTour) {
          activeTour.movePrevious();
        }
      };
    }

    if (footer) {
      const existingCancel = footer.querySelector('.guided-tour__footer-cancel');
      if (existingCancel) {
        existingCancel.remove();
      }

      const cancelText = (cancelButton && cancelButton.text) || Drupal.t('Omitir tour');
      const cancelActionButton = document.createElement('button');
      cancelActionButton.type = 'button';
      cancelActionButton.className = 'guided-tour__footer-cancel';
      cancelActionButton.textContent = cancelText;
      cancelActionButton.addEventListener('click', () => {
        activeTourFinishReason = 'cancel';
        if (activeTour) {
          activeTour.destroy();
        }
      });
      footer.insertAdjacentElement('afterbegin', cancelActionButton);
    }
  }

  function buildSteps(config) {
    const total = config.steps.length;

    return config.steps.map((stepDef, index) => {
      const buttons = stepDef.buttons || [];
      const showButtons = ['close'];

      if (getStepButton(buttons, 'back')) {
        showButtons.push('previous');
      }

      if (getStepButton(buttons, 'next')) {
        showButtons.push('next');
      }

      const step = {
        popover: {
          title: stepDef.title || '',
          description: stepDef.text || '',
          side: normalizeSide(stepDef.attachTo && stepDef.attachTo.on),
          align: 'center',
          showButtons,
          popoverClass: 'guided-tour__popover',
          progressText: Drupal.t('Paso @current de @total', {
            '@current': index + 1,
            '@total': total,
          }),
        },
        guidedTourMeta: {
          id: stepDef.id,
          index,
          total,
          text: stepDef.text,
          buttons: stepDef.buttons || [],
          isLast: index === total - 1,
        },
      };

      if (stepDef.attachTo && stepDef.attachTo.element) {
        step.element = stepDef.attachTo.element;
      }

      return step;
    });
  }

  function dispatchTourEvent(config, eventName) {
    document.dispatchEvent(new CustomEvent(`guidedTour:${eventName}`, {
      detail: {
        tourId: config.tourId,
        role: config.userRole,
      },
    }));
  }

  function createTour(config) {
    const factory = getDriverFactory();
    if (!factory) {
      return null;
    }

    const smoothScroll = !config.options || !config.options.defaultStepOptions || config.options.defaultStepOptions.scrollTo !== false;
    activeTourFinishReason = null;
    let driverObj = null;

    driverObj = factory({
      animate: true,
      allowClose: false,
      overlayOpacity: config.options && config.options.useModalOverlay === false ? 0 : 0.6,
      showProgress: false,
      smoothScroll,
      stagePadding: 4,
      stageRadius: 8,
      popoverOffset: 12,
      steps: buildSteps(config),
      onHighlighted(element, step) {
        if (step && step.element && !document.querySelector(step.element)) {
          console.warn(`[GuidedTour] Elemento no encontrado: "${step.element}"`);
        }

        updatePopover(step);
      },
      onDestroyed() {
        const reason = activeTourFinishReason || 'cancel';
        activeTour = null;
        cleanupDriver();

        if (reason === 'restart') {
          activeTourFinishReason = null;
          return;
        }

        if (config.cookieDays > 0) {
          setCookie(config.cookieName, '1', config.cookieDays);
        }

        dispatchTourEvent(config, reason === 'complete' ? 'complete' : 'cancel');
        activeTourFinishReason = null;
      },
    });

    return driverObj;
  }

  function showReplayButton(config, onReplay) {
    const wrapper = document.querySelector('.guided-tour-trigger');
    const btn = document.getElementById('guided-tour-replay-btn');

    if (!wrapper || !btn) {
      return;
    }

    wrapper.style.display = '';
    wrapper.removeAttribute('aria-hidden');

    btn.addEventListener('click', () => {
      deleteCookie(config.cookieName);
      onReplay();
    });
  }

  Drupal.behaviors.guidedTour = {
    attach(context, settings) {
      once('guided-tour-init', 'html', context).forEach(async () => {
        const config = settings.guidedTour;
        const driverFactory = getDriverFactory();

        if (!config || !driverFactory) {
          return;
        }

        const startTour = async () => {
          if (activeTour) {
            activeTourFinishReason = 'restart';
            activeTour.destroy();
            activeTour = null;
          }

          cleanupDriver();

          if (config.waitForWC) {
            await waitForWebComponents(config.steps);
          }

          const firstSelector = config.steps[0] && config.steps[0].attachTo && config.steps[0].attachTo.element;
          if (firstSelector) {
            await waitForElement(firstSelector);
          }

          activeTour = createTour(config);
          if (!activeTour) {
            return;
          }

          setTimeout(() => {
            activeTour.drive();
          }, 300);
        };

        try {
          if (config.autoPlay) {
            await startTour();
          }
        }
        catch (e) {
          console.error('[GuidedTour] Error al iniciar tour:', e);
        }
        finally {
          showReplayButton(config, startTour);
        }
      });
    },
  };

})(Drupal, drupalSettings, once);
