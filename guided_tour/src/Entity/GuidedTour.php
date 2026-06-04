<?php

declare(strict_types=1);

namespace Drupal\guided_tour\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Config Entity for guided tours.
 *
 * @ConfigEntityType(
 *   id = "guided_tour",
 *   label = @Translation("Guided Tour"),
 *   label_collection = @Translation("Guided Tours"),
 *   label_singular = @Translation("guided tour"),
 *   label_plural = @Translation("guided tours"),
 *   label_count = @PluralTranslation(
 *     singular = "@count guided tour",
 *     plural = "@count guided tours",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\guided_tour\GuidedTourListBuilder",
 *     "form" = {
 *       "add"    = "Drupal\guided_tour\Form\GuidedTourForm",
 *       "edit"   = "Drupal\guided_tour\Form\GuidedTourForm",
 *       "delete" = "Drupal\guided_tour\Form\GuidedTourDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *    "config_translation" = {
 *       "mapper" = "Drupal\config_translation\ConfigEntityMapper",
 *     },
 *   },
 *   config_prefix = "tour",
 *   admin_permission = "administer guided tours",
 *   entity_keys = {
 *     "id"     = "id",
 *     "label"  = "label",
 *     "status" = "status",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "status",
 *     "routes",
 *     "route_params",
 *     "bundle_filter",
 *     "roles",
 *     "cookie_days",
 *     "wait_for_wc",
 *     "options",
 *     "steps",
 *   },
 *   links = {
 *     "collection"    = "/admin/config/user-interface/guided-tour",
 *     "add-form"      = "/admin/config/user-interface/guided-tour/add",
 *     "edit-form"     = "/admin/config/user-interface/guided-tour/{guided_tour}/edit",
 *     "delete-form"   = "/admin/config/user-interface/guided-tour/{guided_tour}/delete",
 *     "drupal:config-translation-overview" = "/admin/config/user-interface/guided-tour/{guided_tour}/translate",
 *     "drupal:config-translation-add"      = "/admin/config/user-interface/guided-tour/{guided_tour}/translate/{langcode}/add",
 *     "drupal:config-translation-edit"     = "/admin/config/user-interface/guided-tour/{guided_tour}/translate/{langcode}/edit",
 *     "drupal:config-translation-delete"   = "/admin/config/user-interface/guided-tour/{guided_tour}/translate/{langcode}/delete",
 *   }
 * )
 */
class GuidedTour extends ConfigEntityBase implements GuidedTourInterface {
  /**
   * Tour ID, machine name.
   *
   * @var array Drupal routes where the tour applies
   */
  protected array $routes = [];

  /**
   * Route parameters to identify specific pages (e.g. node/2707).
   *
   * @var array Route parameters (eg ['node' > '2707'])
   */
  protected array $route_params = [];

  /**
   * Aditional filter to specify the bundle for which this tour applies (e.g. content type).
   *
   * @var array
   */
  protected array $bundle_filter = [];

  /**
   * Roles that see the tour.
   *
   * @var array Roles that see the tour Empty  everyone
   */
  protected array $roles = [];

  /**
   * Number of days the cookie for this tour will be stored.
   *
   * @var int Days the dismissal is remembered via cookie
   */
  protected int $cookie_days = 365;

  /**
   * Wait for Web Components to upgrade before starting the tour.
   *
   * @var bool Wait for Web Components to upgrade
   */
  protected bool $wait_for_wc = TRUE;

  /**
   * Global Driver.js options (useModalOverlay, etc.)
   *
   * @var array Global Driverjs options (useModalOverlay, etc)
   */
  protected array $options = ['useModalOverlay' => TRUE];

  /**
   * Tour steps.
   *
   * @var array Tour steps
   */
  protected array $steps = [];

  /**
   * {@inheritdoc}
   */
  public function getRoutes(): array {
    return $this->routes;
  }

  /**
   * Returns the route parameters for which this tour is applicable.
   */
  public function getRouteParams(): array {
    return $this->route_params;
  }

  /**
   * Returns the roles for which this tour is applicable.
   */
  public function getRoles(): array {
    return $this->roles;
  }

  /**
   * Returns the number of days for which the cookie will be stored for this tour.
   */
  public function getCookieDays(): int {
    return $this->cookie_days;
  }

  /**
   * Returns whether the tour should wait for the WC to be ready before starting.
   */
  public function isWaitForWc(): bool {
    return $this->wait_for_wc;
  }

  /**
   * Returns the configuration options for this tour.
   */
  public function getOptions(): array {
    return $this->options;
  }

  /**
   * Returns the tour steps.
   */
  public function getSteps(): array {
    return $this->steps;
  }

  /**
   * {@inheritdoc}
   */
  public function getBundleFilter(): array {
    return $this->bundle_filter;
  }

}
