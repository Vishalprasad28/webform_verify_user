<?php

namespace Drupal\webform_verify_user\Plugin\WebformHandler;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Webform validate handler.
 *
 * @WebformHandler(
 *   id = "validate_user_by_otp",
 *   label = @Translation("Validate User by OTP"),
 *   category = @Translation("Settings"),
 *   description = @Translation("Validate User By OTP Validation."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 * )
 */
class ValidateUserByOtp extends WebformHandlerBase {

  /**
   * The token manager.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $request;

  /**
   * The Current user account object.
   * 
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The Current user account object.
   * 
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The Current user account object.
   * 
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration,
    $plugin_id, $plugin_definition);
    $instance->request = $container->get('request_stack');
    $instance->currentUser = $container->get('current_user');
    $instance->formBuilder = $container->get('form_builder');
    $instance->mailManager = $container->get('plugin.manager.mail');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'roles' => [],
      'field_name' => '',
      'has_email_field' => 0,
      'otp_timeout' => 300,
      'debug' =>  0,
      'format' => 'basic_html',
      'mail_body' => '',
      'subject' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
    foreach ($roles as $role) {
      $roles[$role->id()] = $role->label();
    }

    $form['roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Roles to Verify Via OTP'),
      '#options' => $roles,
      '#default_value' => $this->configuration['roles'],
      '#required' => TRUE,
      'administrator' => [
        '#disabled' => TRUE,
      ],
    ];
    
    $form['has_email_field'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Does this webform has email field?'),
      '#description' => $this->t("If not checked then  user's email id will be used for sending emails"),
      '#default_value' => $this->configuration['has_email_field'],
    ];

    $form['otp_timeout'] = [
      '#type' => 'number',
      '#required' => TRUE,
      '#title' => $this->t('Enter the OTP Timeout duration'),
      '#description' => $this->t('Enter the OTP Timeout duration in Seconds'),
      '#default_value' => $this->configuration['otp_timeout'],
      '#min' => 300,
      '#max' => 1800,
    ];

    $form['debug'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Debug'),
      '#description' => $this->t('Enable Debugging to debug the form with OTP validation, OTPs will be logged if enabled.'),
      '#default_value' => $this->configuration['debug'],
    ];

    $form['field_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Enter the machine name of the field'),
      '#desciption' => $this->t('Enter the machine name of the field to fetch the email from, make sure this is a required field in your form.'),
      '#default_value' => $this->configuration['field_name'],
      '#states' => [
        'required' => [
          ':input[name="settings[has_email_field]"]' => ['checked' => TRUE],
        ],
        'visible' => [
          ':input[name="settings[has_email_field]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['mail_body'] = [
      '#type' => 'text_format',
      '#format' => $this->configuration['format'],
      '#title' => $this->t('Banner Description'),
      '#description' => $this->t('Add the mail body, use [OTP] token at the place where you have to substitute the OTP'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['mail_body'],
    ];

    $form['mail_subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject of the mail'),
      '#required' => TRUE,
      '#description' => $this->t('Add the Mail Subject'),
      '#default_value' => $this->configuration['subject'],
    ];

    return $this->setSettingsParents($form);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['roles'] = array_filter($form_state->getValue('roles'));
    $this->configuration['has_email_field'] = $form_state->getValue('has_email_field');
    $this->configuration['field_name'] = $form_state->getValue('field_name');
    $this->configuration['otp_timeout'] = $form_state->getValue('otp_timeout');
    $this->configuration['debug'] = $form_state->getValue('debug');
    $this->configuration['subject'] = $form_state->getValue('mail_subject');
    $this->configuration['format'] = $form_state->getValue('mail_body')['format'];
    $this->configuration['mail_body'] = $form_state->getValue('mail_body')['value'];
  }

  public function getSummary() {
    $settings = $this->getSettings();
    unset($settings['roles']);
    unset($settings['mail_body']);
    unset($settings['format']);
    unset($settings['subject']);
    $markup = '';
    foreach ($settings as $setting => $value) {
      $markup = $markup . '<div><b>' . ucwords(str_replace('_', ' ', $setting)) . '</b>: ' . $value . '</div>';
    }

    return [
      '#markup' => $markup,
    ] + parent::getSummary();
  }

  /**
   * {@inheritdoc}
   */
  public function alterForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    $user = $this->currentUser;
    if ($this->webform->getElement($this->configuration['field_name']) && 
    $this->webform->getElement($this->configuration['field_name'])['#type'] == 'email') {
      $form['#attached']['library'][] = 'webform_verify_user/verify_otp';
      $form['#attached']['drupalSettings'] = [
        'form_id' => $form['#webform_id'],
      ];
      $roles = array_keys($this->configuration['roles']);
      if (array_intersect($user->getRoles(), $roles)) {
        $form['actions']['verify_user'] = [
          '#prefix' => '<div id="send-otp-btn">',
          '#suffix' => '</div>',
          '#type' => 'button',
          '#value' => $this->t('Verify Yourself'),
          '#submit' => [$this, 'verifyUser'],
          '#suffix' => '<div id="otp-verification-message"></div>',
          '#attributes' => [
            'class' => ['verify-btn'],
          ],
          '#ajax' => [
            'callback' => [$this, 'sendOtp'],
            'event' => 'click',
            'progress' => [
              'type' => 'throbber',
            ],
          ],
        ];
        $form['#validate'][] = [$this, 'verifyUser'];
        
      }
    }

  }

  /**
   * Function to verify User email and send OTP.
   * 
   * @param array $form
   *   Takes the instance of form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Takes the Instance of Form State.
   * 
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Returns the Ajax response.
   */
  public function sendOtp(array &$form, FormStateInterface $form_state) {
    $user = $this->currentUser;
    $roles = array_keys($this->configuration['roles']);
    $email = $form_state->getValue($this->configuration['field_name']);
    $response = new AjaxResponse();
    $error = $form_state->getErrors()[$this->configuration['field_name']];
    $this->messenger()->deleteAll();
    if (!$this->request->getSession()->get('otp_verified')) {
      if (array_intersect($user->getRoles(), $roles)) {
        if (isset($error)) {
          $response->addCommand(new HtmlCommand("#otp-verification-message", '<div class="otp-error">' . $error . ' <i class="fa-solid fa-circle-xmark"></i>' . '</div>'));

          return $response;
        }
        elseif (!$this->request->getSession()->get('otp') || 
        $this->request->getCurrentRequest()->server->get('REQUEST_TIME') - $this->request->getSession()->get('time') > $this->configuration['otp_timeout']) {
          $otp = random_int(10000, 90000);
          $session = $this->request->getSession();
          $data['subject'] = $this->configuration['subject'];
          $data['body'] = str_replace('[OTP]', $otp, $this->configuration['mail_body']);
          $data['otp'] = $otp;
          $timestamp = $this->request->getCurrentRequest()->server->get('REQUEST_TIME');
    
          $message = $this->sendOtpMail($otp, $data, $email, $timestamp, $session);

          if (!$message) {
            $response->addCommand(new HtmlCommand("#otp-verification-message", '<div class="otp-error">There was an error sending mail.</div>'));

            return $response;
          }
        }
        else {
          $message = "An OTP has already been sent to {$email}";
        }
        $otp_form = $this->formBuilder->getForm('Drupal\webform_verify_user\Form\OtpValidatorForm');
        $otp_form['#attributes'] = [
          'class' => [$form['#webform_id']],
        ];
        $otp_form['#prefix'] = $message;
        $dialogForm['#attached']['library'][] = 'core/drupal.dialog.ajax';
        $rendered_form = \Drupal::service('renderer')->render($otp_form);
        $dialogForm['#markup'] = $rendered_form;
        $response->addCommand(new HtmlCommand("#otp-verification-message", ''));
        $response->addCommand(new OpenModalDialogCommand('Message', $dialogForm, ['width' => '600']));

        return $response;
      }
    }
    elseif ($this->request->getSession()->get('email') && $email != $this->request->getSession()->get('email')) {
      $message = "You can't change your email after verification kindly Reverify";
      $response->addCommand(new HtmlCommand("#otp-verification-message", '<div class="otp-error">' . $message . ' <i class="fa-solid fa-circle-xmark"></i>' . '</div>'));
      $this->resetUserSession();
      return $response;
    }
    else {
      $response->addCommand(new HtmlCommand("#otp-verification-message", '<div class="otp-message">You Are Verified <i class="fa-solid fa-circle-check"></i> </div>'));
      
      $this->messenger()->deleteAll();
      return $response;
    }
    
  }

  /**
   * Function to verify the user verification upon form submit.
   * 
   * @param array $form
   *   Takes the instance of form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Takes the Instance of Form State.
   */
  public function verifyUser(array &$form, FormStateInterface $form_state) {
    $email = $form_state->getValue($this->configuration['field_name']);
    if ($this->request->getSession()->get('otp_verified') && $email != $this->request->getSession()->get('email')) {
      $form_state->setErrorByName("otp", $this->t("You Can't change your email after verification"));
      $this->resetUserSession();
    }
    elseif (!$this->request->getSession()->get('otp_verified')) {
      $form_state->setErrorByName("otp", $this->t('OTP Verification is required'));
    }
  }
  
  /**
   * Function to send mail to the user.
   * 
   * @param int $otp
   *   Takes the OTP to be mailed.
   * @param array $data
   *   Data array containing mailing informations.
   * @param int $timestamp
   *   The timestamp when the otp was requested.
   * @param \Symfony\Component\HttpFoundation\Session\SessionInterface $session
   *   The Session Instance to set the data.
   * 
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|bool
   *   Returns the Success message or False upon failure.
   */
  protected function sendOtpMail(int $otp, array $data, string $email, int $timestamp, SessionInterface $session) {
    try {
      $this->mailManager->mail("webform_verify_user", "otp_validation", $email, 'en', $data, NULL, TRUE);
      $session->set('otp', $otp);
      $session->set('time', $timestamp);
      $session->set('otp_verified', FALSE);
      $session->set('email', $email);
      $session->set('timeout', $this->configuration['otp_timeout']);
      $message = $this->t('An OTP has been sent to your email @email', ['@email' => $email]);

      if ($this->configuration['debug']) {
        $this->loggerFactory->get('webform_verify_user')->notice(
          $this->t('A new OTP requested: @otp at time @time',
          [
            '@otp' => $otp,
            '@time' => $this->request->getCurrentRequest()->server->get('REQUEST_TIME'),
          ]
          )
        );
      }

      return $message;
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('webform_verify_user')->notice($e->getMessage());
      return FALSE;
    }
  }

  /**
   * Function to reset the otp data stored in Session.
   */
  protected function resetUserSession() {
    $session = $this->request->getSession();
    $session->remove('otp');
    $session->remove('time');
    $session->remove('email');
    $session->remove('timeout');
    $session->remove('otp_verified');
  }

}
