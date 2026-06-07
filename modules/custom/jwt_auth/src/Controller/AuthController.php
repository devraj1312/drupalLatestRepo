<?php

namespace Drupal\jwt_auth\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Controller\ControllerBase;
use Drupal\user\Entity\User;

class AuthController extends ControllerBase {

  public function login(Request $request) {

    try {
      $data = json_decode($request->getContent(), TRUE);

      $mobile = $data['mobile'] ?? '';
      $password = $data['password'] ?? '';

      // ❌ VALIDATION
      if (!$mobile || !$password) {
        return new JsonResponse([
          'status' => 'error',
          'message' => 'Mobile & Password required'
        ], 400);
      }

      // 🔍 USER CHECK
      $query = \Drupal::entityQuery('user')
        ->condition('field_mobile_number.value', $mobile)
        ->accessCheck(FALSE);

      $uids = $query->execute();

      if (empty($uids)) {
        return new JsonResponse([
          'status' => 'not_found',
          'message' => 'User not found'
        ], 404);
      }

      $uid = reset($uids);
      $user = User::load($uid);

      // ❌ PASSWORD CHECK
      if (!\Drupal::service('password')->check($password, $user->getPassword())) {
        return new JsonResponse([
          'status' => 'invalid_password',
          'message' => 'Password Incorrect'
        ], 401);
      }

      // 🔥 SERVICE USE (IMPORTANT)
      $jwtService = \Drupal::service('jwt_auth.jwt_service');
      $jwt = $jwtService->generateToken($user);

      return new JsonResponse([
        'status' => 'success',
        'token' => $jwt,
        'user' => [
          'id' => $user->id(),
          'name' => $user->get('field_full_name')->value
        ]
      ], 200);

    } catch (\Exception $e) {
      return new JsonResponse([
        'status' => 'error',
        'message' => 'Server error'
      ], 500);
    }
  }
}