<?php

namespace Drupal\event_registration\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Service for Event Registration business logic.
 */
class EventRegistrationService {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new EventRegistrationService.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(Connection $database, ConfigFactoryInterface $config_factory) {
    $this->database = $database;
    $this->configFactory = $config_factory;
  }

  /**
   * Gets event categories available for registration.
   *
   * @return array
   *   An array of category names keyed by category name.
   */
  public function getEventCategories() {
    $today = date('Y-m-d');
    $query = $this->database->select('event_registration_config', 'c');
    $query->fields('c', ['event_category']);
    $query->condition('registration_start_date', $today, '<=');
    $query->condition('registration_end_date', $today, '>=');
    $query->distinct();
    return $query->execute()->fetchAllKeyed(0, 0);
  }

  /**
   * Gets event dates for a category.
   *
   * @param string $category
   *   The event category.
   *
   * @return array
   *   An array of event dates keyed by date.
   */
  public function getEventDates($category) {
    $today = date('Y-m-d');
    $query = $this->database->select('event_registration_config', 'c');
    $query->fields('c', ['event_date']);
    $query->condition('event_category', $category);
    $query->condition('registration_start_date', $today, '<=');
    $query->condition('registration_end_date', $today, '>=');
    $query->distinct();
    return $query->execute()->fetchAllKeyed(0, 0);
  }

  /**
   * Gets event names for a category and date.
   *
   * @param string $category
   *   The event category.
   * @param string $date
   *   The event date.
   *
   * @return array
   *   An array of event names keyed by event ID.
   */
  public function getEventNames($category, $date) {
    $today = date('Y-m-d');
    $query = $this->database->select('event_registration_config', 'c');
    $query->fields('c', ['id', 'event_name']);
    $query->condition('event_category', $category);
    $query->condition('event_date', $date);
    $query->condition('registration_start_date', $today, '<=');
    $query->condition('registration_end_date', $today, '>=');
    return $query->execute()->fetchAllKeyed();
  }

  /**
   * Checks if a duplicate registration exists.
   *
   * @param string $email
   *   The user's email.
   * @param string $event_date
   *   The event date.
   *
   * @return bool
   *   TRUE if a duplicate exists, FALSE otherwise.
   */
  public function isDuplicateRegistration($email, $event_date) {
    $query = $this->database->select('event_registration_data', 'd');
    $query->join('event_registration_config', 'c', 'd.event_id = c.id');
    $query->fields('d', ['id']);
    $query->condition('d.email', $email);
    $query->condition('c.event_date', $event_date);
    $result = $query->execute()->fetchField();
    return (bool) $result;
  }

  /**
   * Saves a new registration.
   *
   * @param array $data
   *   The registration data.
   *
   * @return int|bool
   *   The new registration ID on success, FALSE on failure.
   */
  public function saveRegistration(array $data) {
    try {
      return $this->database->insert('event_registration_data')
        ->fields($data)
        ->execute();
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * Gets all event dates (for admin listing).
   *
   * @return array
   *   An array of event dates.
   */
  public function getAllEventDates() {
    $query = $this->database->select('event_registration_config', 'c');
    $query->fields('c', ['event_date']);
    $query->distinct();
    $query->orderBy('event_date', 'DESC');
    return $query->execute()->fetchAllKeyed(0, 0);
  }

  /**
   * Gets event names by date (for admin listing).
   *
   * @param string $date
   *   The event date.
   *
   * @return array
   *   An array of event names keyed by ID.
   */
  public function getEventNamesByDate($date) {
    $query = $this->database->select('event_registration_config', 'c');
    $query->fields('c', ['id', 'event_name']);
    $query->condition('event_date', $date);
    return $query->execute()->fetchAllKeyed();
  }

  /**
   * Gets participant count.
   *
   * @param string $date
   *   Optional event date filter.
   * @param string $event_id
   *   Optional event ID filter.
   *
   * @return int
   *   The count of participants.
   */
  public function getParticipantCount($date = '', $event_id = '') {
    $query = $this->database->select('event_registration_data', 'd');
    $query->join('event_registration_config', 'c', 'd.event_id = c.id');

    if ($date) {
      $query->condition('c.event_date', $date);
    }
    if ($event_id) {
      $query->condition('d.event_id', $event_id);
    }

    return $query->countQuery()->execute()->fetchField();
  }

  /**
   * Gets registrations.
   *
   * @param string $date
   *   Optional event date filter.
   * @param string $event_id
   *   Optional event ID filter.
   *
   * @return array
   *   An array of registration objects.
   */
  public function getRegistrations($date = '', $event_id = '') {
    $query = $this->database->select('event_registration_data', 'd');
    $query->join('event_registration_config', 'c', 'd.event_id = c.id');
    $query->fields('d', ['full_name', 'email', 'college_name', 'department', 'event_category', 'created']);
    $query->addField('c', 'event_date');
    $query->addField('c', 'event_name');

    if ($date) {
      $query->condition('c.event_date', $date);
    }
    if ($event_id) {
      $query->condition('d.event_id', $event_id);
    }

    $query->orderBy('d.created', 'DESC');

    return $query->execute()->fetchAll();
  }

  /**
   * Gets module settings.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The module settings config.
   */
  public function getSettings() {
    return $this->configFactory->get('event_registration.settings');
  }

}
