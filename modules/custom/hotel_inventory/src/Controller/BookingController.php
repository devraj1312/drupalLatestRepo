<?php

// namespace Drupal\hotel_inventory\Controller;

// use Drupal\Core\Controller\ControllerBase;
// use Drupal\user\Entity\User;
// use Drupal\node\Entity\Node;

// class BookingController extends ControllerBase {

//   public function hotelBookings() {

//     $current_user = \Drupal::currentUser();
//     $user = User::load($current_user->id());

//     // Check assigned hotel
//     if (!$user->hasField('field_assigned_hotel') || $user->get('field_assigned_hotel')->isEmpty()) {
//       return [
//         '#markup' => '<p>No hotel assigned to this manager.</p>',
//       ];
//     }

//     $hotel_id = $user->get('field_assigned_hotel')->target_id;
//     $hotel = Node::load($hotel_id);

//     // Fetch hotel bookings
//     $booking_ids = \Drupal::entityQuery('node')
//       ->condition('type', 'hotel_booking')
//       ->condition('field_hotel.target_id', $hotel_id)
//       ->sort('created', 'DESC')
//       ->accessCheck(TRUE)
//       ->execute();

//     if (empty($booking_ids)) {
//       return [
//         '#markup' => '<p>No bookings found for this hotel.</p>',
//       ];
//     }

//     $bookings = Node::loadMultiple($booking_ids);

//     $rows = [];
//     $count = 1;

//     foreach ($bookings as $booking) {

//       $user_id = $booking->get('field_hotel_booking_user')->target_id;
//       $customer = User::load($user_id);
//       $customer_name = $customer ? $customer->getDisplayName() : 'N/A';

//       $rows[] = [
//         'data' => [
//           $count,
//           $booking->get('field_hotel_booking_id')->value ?? 'N/A',
//           $hotel ? $hotel->getTitle() : 'N/A',
//           $customer_name,
//           $booking->get('field_room_type')->value ?? 'N/A',
//           $booking->get('field_rooms_count')->value ?? '0',
//           $booking->get('field_adults_count')->value ?? '0',
//           $booking->get('field_children_count')->value ?? '0',
//           $booking->get('field_check_in_date')->value ?? 'N/A',
//           $booking->get('field_check_out_date')->value ?? 'N/A',
//           $booking->get('field_hotel_booking_status')->value ?? 'Pending',
//           '₹' . ($booking->get('field_hotel_booking_amount')->value ?? '0'),
//         ],
//       ];

//       $count++;
//     }

//     $build['booking_table'] = [
//       '#type' => 'table',
//       '#header' => [
//         'S.No',
//         'Booking ID',
//         'Hotel Name',
//         'Customer Name',
//         'Room Type',
//         'Rooms',
//         'Adults',
//         'Children',
//         'Check In',
//         'Check Out',
//         'Status',
//         'Amount',
//       ],
//       '#rows' => $rows,
//       '#attributes' => [
//         'class' => ['hotel-booking-table'],
//       ],
//       '#empty' => $this->t('No bookings found.'),
//     ];

//     return $build;
//   }

// }