/**
 * @file
 * Vendor bundle para guided_tour.
 *
 * Importa driver.js y lo expone en window.driver.js.driver
 * para mantener compatibilidad con el detector en guided_tour.js:
 *
 *   window.driver?.js?.driver  ← factory function
 */

import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';

window.driver = window.driver || {};
window.driver.js = { driver };