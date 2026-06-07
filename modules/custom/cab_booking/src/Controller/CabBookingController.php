<?php

namespace Drupal\cab_booking\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Drupal\Core\Database\Database;

class CabBookingController extends ControllerBase {

  public function book(Request $request) {

    try {
      $data = json_decode($request->getContent(), TRUE);

      $jwtService = \Drupal::service('jwt_auth.jwt_service');
      $decoded = $jwtService->verifyToken($request);

      if ($decoded instanceof JsonResponse) {
        return $decoded;
      }

      $uid = $decoded->uid;

      $user = User::load($uid);

      if (!$user) {
        return new JsonResponse(['message' => 'User not found'], 404);
      }

      // 📥 Data from React
      $cab_name = $data['cab_name'] ?? '';
      $pickup = $data['pickup'] ?? '';
      $drop = $data['drop'] ?? '';
      $date_time = $data['date_time'] ?? '';

      if (!$cab_name || !$pickup || !$drop || !$date_time) {
        return new JsonResponse(['message' => 'All fields required'], 400);
      }

      // 📝 Create node
      $node = Node::create([
        'type' => 'cab_booking',
        'uid' => $uid,
        'title' => 'Cab Booking - ' . $cab_name,
        'field_cab_name' => $cab_name,
        'field_pickup' => $pickup,
        'field_drop' => $drop,
        'field_date_time' => date('Y-m-d\TH:i:s', strtotime($date_time)),
        'field_user' => [
            'target_id' => $uid,
        ],
        'field_status' => [
          'value' => 'pending',
        ],
        'status' => 1,
      ]);

      $node->save();

      $booking_id = $node->get('field_booking_id')->value;

      return new JsonResponse([
        'message' => 'Cab booked successfully',
        'status' => 'success',
        'booking_id' => $booking_id,
      ]);

    } catch (\Exception $e) {
      return new JsonResponse([
        'error' => $e->getMessage()
      ], 500);
    }
  }

  public function getUserBookings(Request $request) {

    try {

      $jwtService = \Drupal::service('jwt_auth.jwt_service');
      $decoded = $jwtService->verifyToken($request);

      if ($decoded instanceof JsonResponse) {
        return $decoded;
      }

      $uid = $decoded->uid;

      // 🔥 Get bookings of this user
      $query = \Drupal::entityQuery('node')
        ->condition('type', 'cab_booking')
        ->condition('uid', $uid)
        // ->condition('field_user.target_id', $uid)
        ->sort('created', 'DESC')
        ->accessCheck(FALSE);

      $nids = $query->execute();

      $bookings = [];

      foreach ($nids as $nid) {
        $node = Node::load($nid);

        $date = $node->get('field_date_time')->value;

        $ist_date = \Drupal::service('date.formatter')->format(
          strtotime($date),
          'custom',
          'Y-m-d\TH:i:s',
          'Asia/Kolkata'
        );

        $bookings[] = [
          'id' => $node->get('field_booking_id')->value,
          'type' => 'cab',
          'name' => $node->get('field_cab_name')->value,
          'details' => $node->get('field_pickup')->value . ' → ' . $node->get('field_drop')->value,
          // 'date' => date('Y-m-d', strtotime($node->get('field_date_time')->value)),
          // 'date' => $node->get('field_date_time')->value,
          'date' => $ist_date,
          'price' => $node->get('field_ride_price')->value ?? 0,
          'status' => ucfirst($node->get('field_status')->value),
        ];
      }

      return new JsonResponse($bookings);

    } catch (\Exception $e) {
      return new JsonResponse([
        'error' => $e->getMessage()
      ], 500);
    }
  }

  public function cancelCabBooking($booking_id) {

    try {

      $nids = \Drupal::entityQuery('node')
        ->condition('field_booking_id.value', $booking_id)
        ->accessCheck(FALSE)
        ->execute();

      if (empty($nids)) {
        return new JsonResponse([
          'status' => 'error',
          'message' => 'Booking not found'
        ], 404);
      }

      $nid = reset($nids);
      $node = \Drupal\node\Entity\Node::load($nid);

      if (!$node) {
        return new JsonResponse([
          'status' => 'error',
          'message' => 'Node not found'
        ], 404);
      }

      // ✅ FINAL FIX
      $node->set('field_status', 'cancelled');

      $node->save();

      return new JsonResponse([
        'status' => 'success',
        'message' => 'Booking cancelled successfully'
      ]);

    } catch (\Exception $e) {

      \Drupal::logger('cab_booking')->error($e->getMessage());

      return new JsonResponse([
        'status' => 'error',
        'message' => 'Server error',
        'error' => $e->getMessage()
      ], 500);
    }
  }

}