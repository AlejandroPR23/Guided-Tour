<?php

declare(strict_types=1);

namespace Drupal\guided_tour\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Interface para la entidad GuidedTour.
 */
interface GuidedTourInterface extends ConfigEntityInterface {

  /**
   * Retorna las rutas para las que este tour es aplicable.
   */
  public function getRoutes(): array;

  /**
   * Retorna los parámetros de ruta para los que este tour es aplicable.
   */
  public function getRouteParams(): array;

  /**
   * Retorna los roles para los que este tour es aplicable.
   */
  public function getRoles(): array;

  /**
   * Retorna el número de días que se almacenará la cookie para este tour.
   */
  public function getCookieDays(): int;

  /**
   * Retorna si el tour debe esperar a que el WC esté listo antes de iniciarse.
   */
  public function isWaitForWc(): bool;

  /**
   * Retorna las opciones de configuración para este tour.
   */
  public function getOptions(): array;

  /**
   * Retorna los pasos definidos para este tour.
   */
  public function getSteps(): array;

}
