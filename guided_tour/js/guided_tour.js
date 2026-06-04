/**
 * @file
 * Initializes Driver.js. Supports:
 * - Automatic startup (autoPlay) or on-demand only (replay button)
 * - #guided-tour-replay-btn button to relaunch the tour at any time
 * - Web Components (waitForWC via customElements.whenDefined)
 * - Shadow DOM piercing with "hostSelector >> shadowSelector" syntax
 * - Dismissal cookie with option to clear it from the button
 * - 👻 Dynamic creation of ghost elements for Shadow DOM support
 */

(function (Drupal, drupalSettings, once) {

  'use strict';

  let activeTour = null;
  let activeTourFinishReason = null;

  // 👻 ── Ghost Manager (NUEVO) ─────────────────────────────────────────────
  let activeGhosts = [];
  let ghostListenersBound = false;

  /**
   * Recalculates the coordinates of all active ghost elements.
   */
  function updateGhosts() {
    if (!activeGhosts.length) return;

    activeGhosts.forEach(({ shadowEl, ghostEl }) => {
      const rect = shadowEl.getBoundingClientRect();
      if (rect.width === 0 && rect.height === 0) return;

      ghostEl.style.top = (rect.top + window.scrollY) + 'px';
      ghostEl.style.left = (rect.left + window.scrollX) + 'px';
      ghostEl.style.width = rect.width + 'px';
      ghostEl.style.height = rect.height + 'px';
    });
  }

  /**
   * Creates a transparent ghost element in the Light DOM that mirrors the Shadow DOM one.
   */
  function createGhostFor(shadowEl) {
    const ghostEl = document.createElement('div');
    ghostEl.className = 'guided-tour-ghost';
    ghostEl.style.position = 'absolute';
    ghostEl.style.pointerEvents = 'none';
    ghostEl.style.zIndex = '-1';

    document.body.appendChild(ghostEl);
    activeGhosts.push({ shadowEl, ghostEl });

    if (!ghostListenersBound) {
      window.addEventListener('resize', updateGhosts);
      window.addEventListener('scroll', updateGhosts, { passive: true });
      ghostListenersBound = true;
    }

    updateGhosts();
    return ghostEl;
  }

  /**
   * Removes the ghost elements and window listeners.
   */
  function cleanupGhosts() {
    activeGhosts.forEach(({ ghostEl }) => ghostEl.remove());
    activeGhosts = [];

    if (ghostListenersBound) {
      window.removeEventListener('resize', updateGhosts);
      window.removeEventListener('scroll', updateGhosts);
      ghostListenersBound = false;
    }
  }

  // ── Shadow DOM helpers ──────────────────────────────────────────────────

  function isShadowSelector(selector) {
    return typeof selector === 'string' && selector.includes(' >> ');
  }

  function queryShadow(selector) {
    if (!selector) return null;

    if (!isShadowSelector(selector)) {
      return document.querySelector(selector);
    }

    const parts = selector.split(' >> ').map(s => s.trim());
    let context = document;

    for (let i = 0; i < parts.length; i++) {
      if (!context) return null;

      const el = context.querySelector(parts[i]);
      if (!el) return null;

      if (i === parts.length - 1) return el;

      if (!el.shadowRoot) {
        console.warn(`[GuidedTour] The element "${parts[i]}" does not have a shadowRoot. Cannot pierce.`);
        return null;
      }
      context = el.shadowRoot;
    }

    return null;
  }

  function waitForElement(selector, timeout = 5000) {
    return new Promise((resolve) => {
      const el = queryShadow(selector);
      if (el) {
        resolve(el);
        return;
      }

      if (isShadowSelector(selector)) {
        const interval = setInterval(() => {
          const found = queryShadow(selector);
          if (found) {
            clearInterval(interval);
            resolve(found);
          }
        }, 100);
        setTimeout(() => {
          clearInterval(interval);
          resolve(queryShadow(selector));
        }, timeout);
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

  // ── Driver.js factory ───────────────────────────────────────────────────

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

  // ── Cookie helpers ──────────────────────────────────────────────────────

  function setCookie(name, value, days) {
    const exp = new Date(Date.now() + days * 864e5).toUTCString();
    document.cookie = `${name}=${encodeURIComponent(value)}; expires=${exp}; path=/; SameSite=Lax`;
  }

  function getEffectiveCookieName(baseName) {
    const uid = drupalSettings.user && parseInt(drupalSettings.user.uid, 10) > 0
      ? parseInt(drupalSettings.user.uid, 10)
      : 0;
    // Anónimos (uid=0) usan el nombre base compartido por browser.
    // Autenticados añaden su UID → cookie personal.
    return uid > 0 ? baseName + '_u' + uid : baseName;
  }

  function hasCookie(name) {
    return document.cookie.split(';').some(function (c) {
      return c.trim().startsWith(name + '=');
    });
  }

  function deleteCookie(name) {
    document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/; SameSite=Lax`;
  }

  // ── Driver.js cleanup ───────────────────────────────────────────────────

  function cleanupDriver() {
    document.querySelectorAll('.driver-popover').forEach((el) => el.remove());
    document.querySelectorAll('.driver-overlay').forEach((el) => el.remove());
    document.querySelectorAll('.driver-active-element').forEach((el) => {
      el.classList.remove('driver-active-element');
    });
    document.body.classList.remove('driver-active', 'driver-fade', 'driver-simple');
    cleanupGhosts();
  }

  // ── Web Components ──────────────────────────────────────────────────────

  async function waitForWebComponents(steps) {
    const wcRegex = /^[a-z][\w-]*-[\w-]+/;
    const promises = steps
      .map((step) => step.attachTo && step.attachTo.element)
      .filter(Boolean)
      .map((selector) => {
        const hostSelector = isShadowSelector(selector)
          ? selector.split(' >> ')[0].trim()
          : selector;

        if (wcRegex.test(hostSelector)) {
          return customElements.whenDefined(hostSelector.replace(/[.#\[\]].*/, ''));
        }
        const match = hostSelector.match(wcRegex);
        return match ? customElements.whenDefined(match[0]) : Promise.resolve();
      });

    await Promise.allSettled(promises);
  }

  // ── Popover helpers ─────────────────────────────────────────────────────

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
      },{ context: 'guided_tour' })}
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
        previousButton.textContent = backButton.text || Drupal.t('Previous',{},{ context: 'guided_tour' });
        previousButton.style.display = '';
      }
      else {
        previousButton.style.display = 'none';
      }
    }

    if (nextButton) {
      if (nextConfigButton) {
        nextButton.textContent = nextConfigButton.text || (meta.isLast ? Drupal.t('Finish',{},{ context: 'guided_tour' }) : Drupal.t('Next',{},{ context: 'guided_tour' }));
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

      const cancelText = (cancelButton && cancelButton.text) || Drupal.t('Skip tour',{},{ context: 'guided_tour' });
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

  // ── Build steps ─────────────────────────────────────────────────────────

  function buildSteps(config) {
    const total = config.steps.length;

    return config.steps.map((stepDef, index) => {
      const buttons = stepDef.buttons || [];
      const showButtons = ['close'];

      if (getStepButton(buttons, 'back')) showButtons.push('previous');
      if (getStepButton(buttons, 'next')) showButtons.push('next');

      const step = {
        popover: {
          title: stepDef.title || '',
          description: stepDef.text || '',
          side: normalizeSide(stepDef.attachTo && stepDef.attachTo.on),
          align: 'center',
          showButtons,
          popoverClass: 'guided-tour__popover',
          progressText: Drupal.t('Step @current of @total', {
            '@current': index + 1,
            '@total': total,
          },{ context: 'guided_tour' }),
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
        const selector = stepDef.attachTo.element;

        if (isShadowSelector(selector)) {
          const resolvedEl = queryShadow(selector);
          if (resolvedEl) {
            step.element = createGhostFor(resolvedEl);
          }
          else {
            console.warn(`[GuidedTour] Shadow selector not resolved: "${selector}"`);
            step.element = selector.split(' >> ')[0].trim();
          }
        }
        else {
          step.element = selector;
        }
      }

      return step;
    });
  }

  // ── Events ──────────────────────────────────────────────────────────────

  function dispatchTourEvent(config, eventName) {
    document.dispatchEvent(new CustomEvent(`guidedTour:${eventName}`, {
      detail: {
        tourId: config.tourId,
        role: config.userRole,
      },
    }));
  }

  // ── Create tour ─────────────────────────────────────────────────────────

  function createTour(config) {
    const factory = getDriverFactory();
    if (!factory) return null;

    const smoothScroll = !config.options ||
      !config.options.defaultStepOptions ||
      config.options.defaultStepOptions.scrollTo !== false;

    activeTourFinishReason = null;

    const driverObj = factory({
      animate: true,
      allowClose: false,
      overlayOpacity: config.options && config.options.useModalOverlay === false ? 0 : 0.6,
      showProgress: false,
      smoothScroll,
      stagePadding: 8,
      stageRadius: 8,
      popoverOffset: 34,
      prevBtnText: Drupal.t('Previous', {}, { context: 'guided_tour' }),
      nextBtnText: Drupal.t('Next', {}, { context: 'guided_tour' }),
      steps: buildSteps(config),
      onHighlightStarted(element) {
        document.body.classList.add('guided-tour--transitioning');
        if (element) {
          element.scrollIntoView({ behavior: 'instant', block: 'center' });
        }
      },
      onHighlighted(element, step) {
        if (step && step.element && typeof step.element === 'string' && !document.querySelector(step.element)) {
          console.warn(`[GuidedTour] Element not found: "${step.element}"`);
        }

        updateGhosts();
        updatePopover(step);

        requestAnimationFrame(() => {
          requestAnimationFrame(() => {
            if (activeTour) {
              activeTour.refresh();
            }

            document.body.classList.remove('guided-tour--transitioning');

            const popover = document.querySelector('.driver-popover');
            if (popover) {
              popover.style.transition = 'opacity 0.15s ease';
              popover.style.opacity = '1';

              popover.addEventListener('transitionend', () => {
                popover.style.transition = '';
                popover.style.opacity = '';
              }, { once: true });
            }
          });
        });
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

  // ── Replay button ───────────────────────────────────────────────────────

  function showReplayButton(config, onReplay) {
    const wrapper = document.querySelector('.guided-tour-trigger');
    const btn = document.getElementById('guided-tour-replay-btn');

    if (!wrapper || !btn) return;

    wrapper.style.display = '';
    wrapper.removeAttribute('aria-hidden');

    btn.addEventListener('click', () => {
      deleteCookie(config.cookieName);
      onReplay();
    });
  }

  // ── Drupal behavior ─────────────────────────────────────────────────────

  Drupal.behaviors.guidedTour = {
    attach(context, settings) {
      once('guided-tour-init', 'html', context).forEach(async () => {
        const config = settings.guidedTour;
        const driverFactory = getDriverFactory();

        if (!config || !driverFactory) return;

        // Enriquecer config con el cookie name efectivo (incluye UID si autenticado)
        const enrichedConfig = {
          ...config,
          cookieName: getEffectiveCookieName(config.cookieName),
        };

        const startTour = async () => {
          if (activeTour) {
            activeTourFinishReason = 'restart';
            activeTour.destroy();
            activeTour = null;
          }

          cleanupDriver();

          if (enrichedConfig.waitForWC) {
            await waitForWebComponents(enrichedConfig.steps);
          }

          const firstSelector = enrichedConfig.steps[0]?.attachTo?.element;
          if (firstSelector) {
            await waitForElement(firstSelector);
          }

          // createTour usa enrichedConfig → onDestroyed escribe la cookie correcta
          activeTour = createTour(enrichedConfig);
          if (!activeTour) return;

          setTimeout(() => activeTour.drive(), 300);
        };

        try {
          const isDismissed = enrichedConfig.cookieDays > 0 && hasCookie(enrichedConfig.cookieName);
          if (!isDismissed) {
            await startTour();
          }
        }
        catch (e) {
          console.error('[GuidedTour] Error al iniciar tour:', e);
        }
        finally {
          showReplayButton(enrichedConfig, startTour);
        }
      });
    },
  };

})(Drupal, drupalSettings, once);