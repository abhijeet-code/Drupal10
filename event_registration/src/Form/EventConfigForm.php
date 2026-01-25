<?php

namespace Drupal\event_registration\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Event Configuration Form.
 */
class EventConfigForm extends FormBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new EventConfigForm.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'event_registration_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['event_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Event Name'),
      '#required' => TRUE,
    ];

    $form['event_category'] = [
      '#type' => 'select',
      '#title' => $this->t('Category of the event'),
      '#options' => [
        'Online Workshop' => $this->t('Online Workshop'),
        'Hackathon' => $this->t('Hackathon'),
        'Conference' => $this->t('Conference'),
        'One-day Workshop' => $this->t('One-day Workshop'),
      ],
      '#required' => TRUE,
    ];

    $form['registration_start_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Event Registration Start Date'),
      '#required' => TRUE,
    ];

    $form['registration_end_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Event Registration End Date'),
      '#required' => TRUE,
    ];

    $form['event_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Event Date'),
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Event'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $start_date = $form_state->getValue('registration_start_date');
    $end_date = $form_state->getValue('registration_end_date');

    if ($end_date < $start_date) {
      $form_state->setErrorByName('registration_end_date', $this->t('Registration end date must be after the start date.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      $this->database->insert('event_registration_config')
        ->fields([
          'event_name' => $form_state->getValue('event_name'),
          'event_category' => $form_state->getValue('event_category'),
          'registration_start_date' => $form_state->getValue('registration_start_date'),
          'registration_end_date' => $form_state->getValue('registration_end_date'),
          'event_date' => $form_state->getValue('event_date'),
        ])
        ->execute();

      $this->messenger()->addStatus($this->t('Event configuration saved successfully.'));
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Unable to save event configuration. Error: @error', ['@error' => $e->getMessage()]));
    }
  }

}
