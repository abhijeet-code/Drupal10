<?php

namespace Drupal\event_registration\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Mail\MailManagerInterface;

/**
 * Event Registration Form.
 */
class RegistrationForm extends FormBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * Constructs a new RegistrationForm.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   */
  public function __construct(Connection $database, MailManagerInterface $mail_manager) {
    $this->database = $database;
    $this->mailManager = $mail_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('plugin.manager.mail')
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

    $categories = $this->getEventCategories();
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
      $dates = $this->getEventDates($selected_category);
    }
    
    // Explicitly set dates empty if no category or dates found
    if (empty($dates)) {
        $dates = []; 
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
      // Hide if no dates available (optional UX improvement, but keeping it simple)
      '#disabled' => empty($dates),
    ];

    $form['event_name_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'event-name-wrapper'],
    ];

    $events = [];
    if ($selected_category && $selected_date) {
      $events = $this->getEventNames($selected_category, $selected_date);
    }
    
     if (empty($events)) {
        $events = []; 
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
    // 1. Validate special characters in text fields
    $text_fields = ['full_name', 'college_name', 'department'];
    foreach ($text_fields as $field) {
      $value = $form_state->getValue($field);
      if (!preg_match('/^[a-zA-Z0-9\s]+$/', $value)) {
        $form_state->setErrorByName($field, $this->t('@field contains speial characters which are not allowed.', ['@field' => $form[$field]['#title']]));
      }
    }

    // 2. Validate Email Format (Drupal's #type => email does this, but custom check can be added)
    // Implicitly handled by #type email

    // 3. Prevent duplicate registrations (Email + Event Date)
    $email = $form_state->getValue('email');
    $event_id = $form_state->getValue('event_name'); // This gets the event ID as key

    // Need to fetch event date to check duplicates by Email + Date
    // Actually requirement says "Prevent duplicate registrations using: Email + Event Date"
    // So if I register for Event A on Jan 1, I cannot register for Event B on Jan 1?
    // Or is it Email + specific Event?
    // "Event Name (dropdown menu)... lists only the events... using Ajax" implies we are picking an ID.
    // Let's assume Email + Event Date is the strict rule.
    $event_date = $form_state->getValue('event_date');

    // We need to check if this email has already registered for ANY event on this date.
    // Query event_registrations joined with event_config
    
    // NOTE: The 'event_date' value in form_state is the date string because that's what we put in options?
    // Wait, getEventDates returns [date => date]. So value is date.
    
    $query = $this->database->select('event_registration_data', 'd');
    $query->join('event_registration_config', 'c', 'd.event_id = c.id');
    $query->fields('d', ['id']);
    $query->condition('d.email', $email);
    $query->condition('c.event_date', $event_date);
    $result = $query->execute()->fetchField();

    if ($result) {
      $form_state->setErrorByName('email', $this->t('You have already registered for an event on @date.', ['@date' => $event_date]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $event_id = $values['event_name'];
    
    // Get event details for email
    $event_name = $form['event_name_wrapper']['event_name']['#options'][$event_id];
    
    try {
      $this->database->insert('event_registration_data')
        ->fields([
          'event_id' => $event_id,
          'full_name' => $values['full_name'],
          'email' => $values['email'],
          'college_name' => $values['college_name'],
          'department' => $values['department'],
          'event_category' => $values['event_category'],
          'created' => \Drupal::time()->getRequestTime(),
        ])
        ->execute();

      $this->messenger()->addStatus($this->t('Registration successful!'));
      
      // Send Emails
      $this->sendRegistrationEmails($values, $event_name);

    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Registration failed. Please try again.'));
    }
  }

  /**
   * Helper to send emails.
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
    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $send = TRUE;

    // Send to User
    $result = $this->mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);
    
    // Send to Admin if enabled
    $config = \Drupal::config('event_registration.settings');
    if ($config->get('enable_admin_notifications')) {
        $admin_email = $config->get('admin_email');
        if ($admin_email) {
            $key_admin = 'admin_notification';
            $params['message'] = $this->t("New Registration:\nName: @name\nEvent: @event\nDate: @date", [
                '@name' => $values['full_name'],
                '@event' => $event_name,
                '@date' => $values['event_date']
            ]);
            $this->mailManager->mail($module, $key_admin, $admin_email, $langcode, $params, NULL, $send);
        }
    }
  }

  /**
   * Helper to get categories.
   */
  protected function getEventCategories() {
    // We fetch categories that have active configurations within the date range?
    // Requirement says: "Event Registration Form (this form should be available between the start and end date mentioned in the event config page)"
    // So distinct categories from valid events.
    $today = date('Y-m-d');
    $query = $this->database->select('event_registration_config', 'c');
    $query->fields('c', ['event_category']);
    $query->condition('registration_start_date', $today, '<=');
    $query->condition('registration_end_date', $today, '>=');
    $query->distinct();
    $result = $query->execute()->fetchAllKeyed(0, 0);
    return $result;
  }

  /**
   * Helper to get dates for a category.
   */
  protected function getEventDates($category) {
    $today = date('Y-m-d');
    $query = $this->database->select('event_registration_config', 'c');
    $query->fields('c', ['event_date']);
    $query->condition('event_category', $category);
    $query->condition('registration_start_date', $today, '<=');
    $query->condition('registration_end_date', $today, '>=');
    $query->distinct();
    $result = $query->execute()->fetchAllKeyed(0, 0);
    return $result;
  }

  /**
   * Helper to get events for a category and date.
   */
  protected function getEventNames($category, $date) {
    $today = date('Y-m-d');
    $query = $this->database->select('event_registration_config', 'c');
    $query->fields('c', ['id', 'event_name']);
    $query->condition('event_category', $category);
    $query->condition('event_date', $date);
     $query->condition('registration_start_date', $today, '<=');
    $query->condition('registration_end_date', $today, '>=');
    $result = $query->execute()->fetchAllKeyed();
    return $result;
  }

}
