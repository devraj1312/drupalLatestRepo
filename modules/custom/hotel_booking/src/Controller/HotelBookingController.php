<?php

namespace Drupal\hotel_booking\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use Drupal\Core\Database\Database;

class HotelBookingController extends ControllerBase {

  public function hotelbook(Request $request) {

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
        return new JsonResponse([
          'message' => 'User not found'
        ], 404);
      }

      // Data from React
      $hotel_id = $data['hotel_id'] ?? '';
      $room_type = $data['room_type'] ?? '';
      $check_in = $data['check_in_date'] ?? '';
      $check_out = $data['check_out_date'] ?? '';
      $adults = $data['adults_count'] ?? 0;
      $children = $data['children_count'] ?? 0;
      $rooms = (int) ($data['rooms_count'] ?? 0);
      $amount = $data['total_amount'] ?? 0;

      // Validation
      if (!$hotel_id || !$room_type || !$check_in || !$check_out || !$rooms) {
        return new JsonResponse([
          'message' => 'All required fields missing'
        ], 400);
      }

      // Date validation
      if (strtotime($check_out) <= strtotime($check_in)) {
        return new JsonResponse([
          'message' => 'Check-out date must be greater than check-in date'
        ], 400);
      }

      // Hotel check
      $hotel = Node::load($hotel_id);

      if (!$hotel) {
        return new JsonResponse([
          'message' => 'Hotel not found'
        ], 404);
      }

      $hotel_name = $hotel->getTitle();

      // Room node find (Hotel + Room Type)
      $room_query = \Drupal::entityQuery('node')
        ->accessCheck(FALSE)
        ->condition('type', 'rooms')
        ->condition('field_hotel_reference', $hotel_id)
        ->condition('title', $room_type);

      $room_ids = $room_query->execute();

      if (empty($room_ids)) {
        return new JsonResponse([
          'status' => 'error',
          'message' => 'Selected room type not found'
        ], 404);
      }

      $room_id = reset($room_ids);
      $room_node = Node::load($room_id);

      // Total room inventory
      $total_rooms = (int) $room_node->get('field_total_rooms')->value;

      // Existing overlapping bookings
      $booking_query = \Drupal::entityQuery('node')
        ->accessCheck(FALSE)
        ->condition('type', 'hotel_booking')
        ->condition('field_hotel', $hotel_id)
        ->condition('field_room_type', $room_type)
        ->condition('field_hotel_booking_status', 'cancelled', '!=');

      $booking_ids = $booking_query->execute();

      $overlap_booked_rooms = 0;

      $new_checkin = strtotime($check_in);
      $new_checkout = strtotime($check_out);

      foreach ($booking_ids as $booking_id) {
        $booking = Node::load($booking_id);

        $existing_checkin = strtotime($booking->get('field_check_in_date')->value);
        $existing_checkout = strtotime($booking->get('field_check_out_date')->value);

        // Date overlap check
        if ($existing_checkin < $new_checkout && $existing_checkout > $new_checkin) {
          $overlap_booked_rooms += (int) $booking->get('field_rooms_count')->value;
        }
      }

      // Remaining rooms (date-wise logic only)
      $remaining_rooms = $total_rooms - $overlap_booked_rooms;

      // Availability validation
      if ($rooms > $remaining_rooms) {
        return new JsonResponse([
          'status' => 'error',
          'message' => 'Only ' . $remaining_rooms . ' rooms available for selected dates'
        ], 400);
      }

      // Create booking only
      $node = Node::create([
        'type' => 'hotel_booking',
        'uid' => $uid,
        'title' => 'Hotel Booking - ' . $hotel_name,
        'field_hotel' => [
          'target_id' => $hotel_id,
        ],
        'field_hotel_booking_user' => [
          'target_id' => $uid,
        ],
        'field_room_type' => $room_type,
        'field_check_in_date' => [
          'value' => $check_in,
        ],
        'field_check_out_date' => [
          'value' => $check_out,
        ],
        'field_adults_count' => $adults,
        'field_children_count' => $children,
        'field_rooms_count' => $rooms,
        'field_hotel_booking_amount' => $amount,
        'field_hotel_booking_status' => [
          'value' => 'pending',
        ],
        'status' => 1,
      ]);

      $node->save();

      $hotel_booking_id = $node->get('field_hotel_booking_id')->value;

      return new JsonResponse([
        'status' => 'success',
        'booking_id' => $hotel_booking_id,
        'message' => 'Hotel booked successfully'
      ]);

    } catch (\Exception $e) {
      return new JsonResponse([
        'status' => 'error',
        'message' => 'Server error',
        'error' => $e->getMessage()
      ], 500);
    }
  }

  public function getUserHotelBookings(Request $request) {

    try {
      $jwtService = \Drupal::service('jwt_auth.jwt_service');
      $decoded = $jwtService->verifyToken($request);

        if ($decoded instanceof JsonResponse) {
          return $decoded;
        }

      $uid = $decoded->uid;

      // Current user ki hotel bookings fetch
      $query = \Drupal::entityQuery('node')
      ->condition('type', 'hotel_booking')
      ->condition('uid', $uid)
      ->sort('created', 'DESC')
      ->accessCheck(FALSE);

      $nids = $query->execute();

      $bookings = [];

      foreach ($nids as $nid) {
        $node = Node::load($nid);

        // Referenced hotel fetch
        $hotel_id = $node->get('field_hotel')->target_id;
        $hotel = Node::load($hotel_id);

        $hotel_name = $hotel ? $hotel->getTitle() : 'Hotel';

        $check_in = $node->get('field_check_in_date')->value;
        $check_out = $node->get('field_check_out_date')->value;

        $bookings[] = [
          'id' => $node->get('field_hotel_booking_id')->value,
          'type' => 'hotel',
          'name' => $hotel_name,

          // Hotel details section
          'details' =>
          $node->get('field_room_type')->value .
          ' | Rooms: ' .
          $node->get('field_rooms_count')->value,

          // Check-in as main date
          'date' => date('Y-m-d', strtotime($check_in)),

          'price' =>
          $node->get('field_hotel_booking_amount')->value ?? 0,

          'status' =>
          ucfirst($node->get('field_hotel_booking_status')->value),

          // Extra fields if needed later
          'check_in' => $check_in,
          'check_out' => $check_out,
          'adults' => $node->get('field_adults_count')->value,
          'children' => $node->get('field_children_count')->value,
        ];
      }
      return new JsonResponse($bookings);
    } catch (\Exception $e) {
        return new JsonResponse([
        'error' => $e->getMessage()
        ], 500);
    }
  }

  public function cancelHotelBooking($booking_id) {

    try {

      $nids = \Drupal::entityQuery('node')
        ->condition('field_hotel_booking_id', $booking_id)
        ->condition('type', 'hotel_booking')
        ->accessCheck(FALSE)
        ->execute();

      if (empty($nids)) {

        return new JsonResponse([
          'status' => 'error',
          'message' => 'Hotel booking not found'
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

      // ✅ Update booking status
      $node->set('field_hotel_booking_status', 'cancelled');

      $node->save();

      return new JsonResponse([
        'status' => 'success',
        'message' => 'Hotel booking cancelled successfully'
      ]);

    } catch (\Exception $e) {

      \Drupal::logger('hotel_booking')->error($e->getMessage());

      return new JsonResponse([
        'status' => 'error',
        'message' => 'Server error',
        'error' => $e->getMessage()
      ], 500);
    }
  }
}