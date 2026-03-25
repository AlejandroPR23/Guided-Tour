<?php

declare(strict_types=1);

namespace Drupal\guided_tour\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Config Entity para tours guiados.
 *
 * @ConfigEntityType(
 *   id = "guided_tour",
 *   label = @Translation("Tour guiado"),
 *   label_collection = @Translation("Tours guiados"),
 *   label_singular = @Translation("tour guiado"),
 *   label_plural = @Translation("tours guiados"),
 *   label_count = @PluralTranslation(
 *     singular = "@count tour guiado",
 *     plural = "@count tours guiados",
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
 *   }
 * )
 */
class GuidedTour extends ConfigEntityBase implements GuidedTourInterface {
  /**
   * ID del tour, machine name.
   *
   * @var array Rutas de Drupal donde aplica el tour */
  protected array $routes = [];

  /**
   * Parametros de ruta para identificar páginas específicas (ej: node/2707)
   *
   * @var array Parmetros de ruta (ej: ['node' > '2707']) */
  protected array $route_params = [];

  /**
   * Roles que ven el tour.
   *
   * @var array Roles que ven el tour Vaco  todos */
  protected array $roles = [];

  /**
   * Número de días que se almacenará la cookie para este tour.
   *
   * @var int das que se recuerda el dismissal via cookie */
  protected int $cookie_days = 365;

  /**
   * Esperar a que los Web Components hagan upgrade antes de iniciar el tour.
   *
   * @var bool Esperar a que los Web Components hagan upgrade */
  protected bool $wait_for_wc = TRUE;

  /**
   * Opciones globales de Shepherd (useModalOverlay, etc)
   *
   * @var array Opciones globales de Shepherd (useModalOverlay, etc) */
  protected array $options = ['useModalOverlay' => TRUE];

  /**
   * Pasos del tour.
   *
   * @var array Pasos del tour */
  protected array $steps = [];

  /**
   * ── Getters ────────────────────────────────────────────────────────────────
   */
  public function getRoutes(): array {
    return $this->routes;
  }

  /**
   * Retorna los parámetros de ruta para los que este tour es aplicable.
   */
  public function getRouteParams(): array {
    return $this->route_params;
  }

  /**
   * Retorna los roles a los que se les mostrará este tour.
   */
  public function getRoles(): array {
    return $this->roles;
  }

  /**
   * Retorna el número de días que se almacenará la cookie para este tour.
   */
  public function getCookieDays(): int {
    return $this->cookie_days;
  }

  /**
   * Retorna si se debe esperar a que los Web Components hagan upgrade.
   */
  public function isWaitForWc(): bool {
    return $this->wait_for_wc;
  }

  /**
   * Retorna las opciones globales de Shepherd para este tour.
   */
  public function getOptions(): array {
    return $this->options;
  }

  /**
   * Retorna los pasos del tour.
   */
  public function getSteps(): array {
    return $this->steps;
  }

}
