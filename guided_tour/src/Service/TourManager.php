<?php

declare(strict_types=1);

namespace Drupal\guided_tour\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\guided_tour\Entity\GuidedTourInterface;

/**
 * Resuelve qué tour mostrar para una ruta y usuario dados.
 *
 * Lee desde Config Entities.
 *
 * (editables desde la UI de admin).
 */
class TourManager {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Retorna la entidad GuidedTour que aplica, o NULL si ninguna.
   */
  public function getTourForRouteAndUser(string $route_name, AccountInterface $account): ?GuidedTourInterface {
    $role    = $this->getPrimaryRole($account);
    $storage = $this->entityTypeManager->getStorage('guided_tour');

    /** @var \Drupal\guided_tour\Entity\GuidedTourInterface[] $tours */
    $tours = $storage->loadByProperties(['status' => TRUE]);

    foreach ($tours as $tour) {
      // ── Filtro de ruta ────────────────────────────────────────────────
      $routes = $tour->getRoutes();
      if (!empty($routes) && !in_array($route_name, $routes, TRUE)) {
        continue;
      }

      // ── Filtro de route_params ────────────────────────────────────────
      foreach ($tour->getRouteParams() as $param => $value) {
        $actual = $this->routeMatch->getRawParameter($param);
        if ((string) $actual !== (string) $value) {
          continue 2;
        }
      }

      // ── Filtro de rol ─────────────────────────────────────────────────
      $allowed = $tour->getRoles();
      if (!empty($allowed) && !in_array($role, $allowed, TRUE)) {
        continue;
      }

      return $tour;
    }

    return NULL;
  }

  /**
   * Determina el rol "principal" del usuario,
   *
   * Para usarlo en la lógica de tours.
   *
   * Si tiene varios roles, se asigna el que esté más arriba.
   */
  protected function getPrimaryRole(AccountInterface $account): string {
    if ($account->isAnonymous()) {
      return 'anonymous';
    }
    $priority = ['administrator', 'editor', 'content_manager', 'authenticated'];
    foreach ($priority as $role) {
      if (in_array($role, $account->getRoles(), TRUE)) {
        return $role;
      }
    }
    return 'authenticated';
  }

}
