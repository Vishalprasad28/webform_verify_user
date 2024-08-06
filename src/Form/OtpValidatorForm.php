<?php

namespace Drupal\webform_verify_user\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a BCF OTP validator form.
 */
class OtpValidatorForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'webform_verify_user_otp_validator';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#prefix'] = '<div id="otp-form">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'webform_verify_user/verify_otp';
    $form['otp'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Enter your OTP'),
      '#required' => TRUE,
      '#pattern' => '[0-9]{5}',
      '#attributes' => [
        'id' => 'otp-field',
      ],
      '#maxlength' => 5,
      '#description' => $this->t('Please enter the numeric OTP sent to your email.'),
    ];
    $form['error_box'] = [
      '#prefix' => '<div id="otp-field-error">',
      '#suffix' => '</div>',
    ];
    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['verify'] = [
      '#type' => 'button',
      '#value' => $this->t('Verify'),
      '#attributes' => [
        'id' => 'verify-otp-btn',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

}
