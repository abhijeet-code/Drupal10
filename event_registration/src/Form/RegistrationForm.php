<?php

namespace Drupal\event_registration\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\event_registration\Service\EventRegistrationService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Event Registration Form.
 */
class RegistrationForm extends FormBase {

  /**
   * The event registration service.
   *
   * @var \Drupal\event_registration\Service\EventRegistrationService
   */
  protected $eventService;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a new RegistrationForm.
   *
   * @param \Drupal\event_registration\Service\EventRegistrationService $event_service
   *   The event registration service.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(
    EventRegistrationService $event_service,
    MailManagerInterface $mail_manager,
    TimeInterface $time,
    AccountProxyInterface $current_user
  ) {
    $this->eventService = $event_service;
    $this->mailManager = $mail_manager;
    $this->time = $time;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('event_registration.service'),
      $container->get('plugin.manager.mail'),
      $container->get('datetime.time'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'event_registration_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#prefix'] = '<div id="registration-form-wrapper">';
    $form['#suffix'] = '</div>';

    $form['full_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Full Name'),
      '#required' => TRUE,
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email Address'),
      '#required' => TRUE,
    ];

    $form['college_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('College Name'),
      '#required' => TRUE,
    ];

    $form['department'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Department'),
      '#required' => TRUE,
    ];

    $categories = $this->eventService->getEventCategories();
    $selected_category = $form_state->getValue('event_category');

    $form['event_category'] = [
      '#type' => 'select',
      '#title' => $this->t('Category of the event'),
      '#options' => $categories,
      '#empty_option' => $this->t('- Select Category -'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updateEventDate',
        'wrapper' => 'event-date-wrapper',
      ],
    ];

    $form['event_date_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'event-date-wrapper'],
    ];

    $dates = [];
    if ($selected_category) {
      $dates = $this->eventService->getEventDates($selected_category);
    }

    $selected_date = $form_state->getValue('event_date');

    $form['event_date_wrapper']['event_date'] = [
      '#type' => 'select',
      '#title' => $this->t('Event Date'),
      '#options' => $dates,
      '#empty_option' => $this->t('- Select Date -'),
      '#required' => TRUE,
      '#validated' => TRUE,
      '#ajax' => [
        'callback' => '::updateEventName',
        'wrapper' => 'event-name-wrapper',
      ],
      '#disabled' => empty($dates),
    ];

    $form['event_name_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'event-name-wrapper'],
    ];

    $events = [];
    if ($selected_category && $selected_date) {
      $events = $this->eventService->getEventNames($selected_category, $selected_date);
    }

    $form['event_name_wrapper']['event_name'] = [
      '#type' => 'select',
      '#title' => $this->t('Event Name'),
      '#options' => $events,
      '#empty_option' => $this->t('- Select Event -'),
      '#required' => TRUE,
      '#validated' => TRUE,
      '#disabled' => empty($events),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Register'),
    ];

    return $form;
  }

  /**
   * AJAX callback for event date.
   */
  public function updateEventDate(array &$form, FormStateInterface $form_state) {
    return $form['event_date_wrapper'];
  }

  /**
   * AJAX callback for event name.
   */
  public function updateEventName(array &$form, FormStateInterface $form_state) {
    return $form['event_name_wrapper'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $text_fields = ['full_name', 'college_name', 'department'];
    foreach ($text_fields as $field) {
      $value = $form_state->getValue($field);
      if (!preg_match('/^[a-zA-Z0-9\s]+$/', $value)) {
        $form_state->setErrorByName($field, $this->t('@field contains special characters which are not allowed.', ['@field' => $form[$field]['#title']]));
      }
    }

    $email = $form_state->getValue('email');
    $event_date = $form_state->getValue('event_date');

    if ($this->eventService->isDuplicateRegistration($email, $event_date)) {
      $form_state->setErrorByName('email', $this->t('You have already registered for an event on @date.', ['@date' => $event_date]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $event_id = $values['event_name'];

    $event_name = $form['event_name_wrapper']['event_name']['#options'][$event_id];

    $data = [
      'event_id' => $event_id,
      'full_name' => $values['full_name'],
      'email' => $values['email'],
      'college_name' => $values['college_name'],
      'department' => $values['department'],
      'event_category' => $values['event_category'],
      'created' => $this->time->getRequestTime(),
    ];

    $result = $this->eventService->saveRegistration($data);

    if ($result) {
      $this->messenger()->addStatus($this->t('Registration successful!'));
      $this->sendRegistrationEmails($values, $event_name);
    }
    else {
      $this->messenger()->addError($this->t('Registration failed. Please try again.'));
    }
  }

  /**
   * Sends registration emails.
   */
  protected function sendRegistrationEmails($values, $event_name) {
    $module = 'event_registration';
    $key = 'registration_confirmation';
    $to = $values['email'];
    $params['event_name'] = $event_name;
    $params['message'] = $this->t("Hello @name,\n\nYou have successfully registered for @event on @date.\n\nCategory: @cat", [
      '@name' => $values['full_name'],
      '@event' => $event_name,
      '@date' => $values['event_date'],
      '@cat' => $values['event_category'],
    ]);
    $langcode = $this->currentUser->getPreferredLangcode();
    $send = TRUE;

    $this->mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);

    $config = $this->eventService->getSettings();
    if ($config->get('enable_admin_notifications')) {
      $admin_email = $config->get('admin_email');
      if ($admin_email) {
        $key_admin = 'admin_notification';
        $params['message'] = $this->t("New Registration:\nName: @name\nEvent: @event\nDate: @date", [
          '@name' => $values['full_name'],
          '@event' => $event_name,
          '@date' => $values['event_date'],
        ]);
        $this->mailManager->mail($module, $key_admin, $admin_email, $langcode, $params, NULL, $send);
      }
    }
  }

}
