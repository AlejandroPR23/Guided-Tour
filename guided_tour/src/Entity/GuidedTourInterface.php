<?php

declare(strict_types=1);

namespace Drupal\guided_tour\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Interface for the GuidedTour entity.
 */
interface GuidedTourInterface extends ConfigEntityInterface {

  /**
   * Returns the routes for which this tour is applicable.
   */
  public function getRoutes(): array;

  /**
   * Returns the route parameters for which this tour is applicable.
   */
  public function getRouteParams(): array;

  /**
 * Returns the bundle filter for which this tour is applicable.
 *
 * @return array
 *   Array with keys 'entity_type' and 'bundle', or empty if no filter is set.
 */
  public function getBundleFilter(): array;

  /**
   * Returns the roles for which this tour is applicable.
   */
  public function getRoles(): array;

  /**
   * Returns the number of days for which the cookie will be stored for this tour.
   */
  public function getCookieDays(): int;

  /**
   * Returns whether the tour should wait for the WC to be ready before starting.
   */
  public function isWaitForWc(): bool;

  /**
   * Returns the configuration options for this tour.
   */
  public function getOptions(): array;

  /**
   * Returns the steps defined for this tour.
   */
  public function getSteps(): array;

}
