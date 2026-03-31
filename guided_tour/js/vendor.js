/**
 * @file
 * Vendor bundle for guided_tour.
 *
 * Imports driver.js and exposes it on window.driver.js.driver
 * to maintain compatibility with the detector in guided_tour.js:
 *
 *   window.driver?.js?.driver  ← factory function
 */

import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';

window.driver = window.driver || {};
window.driver.js = { driver };