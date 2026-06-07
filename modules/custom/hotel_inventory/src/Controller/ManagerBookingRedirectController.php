<?php

namespace Drupal\hotel_inventory\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ManagerBookingRedirectController extends ControllerBase {

  public function redirectToHotelBookings() {

    $current_user = \Drupal::currentUser();
    $user = User::load($current_user->id());

    if (!$user->hasField('field_assigned_hotel') || $user->get('field_assigned_hotel')->isEmpty()) {
      return [
        '#markup' => '<p>No hotel assigned.</p>',
      ];
    }

    $hotel_id = $user->get('field_assigned_hotel')->target_id;

    return new RedirectResponse('/manager/bookings/' . $hotel_id);
  }

}