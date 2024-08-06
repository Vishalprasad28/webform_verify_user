<?php

namespace Drupal\webform_verify_user\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns responses for BCF OTP validator routes.
 */
class ValidateOtp extends ControllerBase {

  /**
   * Builds the response.
   */
  public function build(Request $request) {
    if ($request->getContentType() === 'json') {
      $otp = $request->getContent();
      $session = $request->getSession();
      $time = $session->get('time');
      $timestamp = $request->server->get('REQUEST_TIME');
      if (($timestamp - $time) <= $session->get('timeout')) {
        $sent_otp = $session->get('otp');
        if (isset($otp) && is_numeric($otp) && (int)$otp == $sent_otp) {
          $status = TRUE;
          $message = 'Otp has been verified';
        }
        else {
          $status = FALSE;
          $message = 'Invalid otp entered';
        }
      }
      else {
        $status = FALSE;
        $message = 'Timeout';
      }
      $response = [
        'status' => $status,
        'message' => $message,
      ];
      $session->set('otp_verified', $status);

      return new JsonResponse($response);
    }
    else {
      return $this->redirect('<front>');
    }
  }

}
