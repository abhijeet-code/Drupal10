<?php

namespace Drupal\event_registration\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;

/**
 * Controller for the admin registration listing.
 */
class RegistrationController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new RegistrationController.
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
   * Builds the admin listing page.
   */
  public function content(Request $request) {
    $build = [];

    $event_dates = $this->getEventDates();
    $selected_date = $request->query->get('event_date', '');
    $selected_event = $request->query->get('event_name', '');

    $build['filters'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'filter-wrapper'],
    ];

    $build['filters']['event_date'] = [
      '#type' => 'select',
      '#title' => $this->t('Event Date'),
      '#options' => ['' => $this->t('- All Dates -')] + $event_dates,
      '#default_value' => $selected_date,
      '#attributes' => [
        'id' => 'filter-event-date',
        'onchange' => 'this.form.submit()',
      ],
    ];

    $event_names = [];
    if ($selected_date) {
      $event_names = $this->getEventNamesByDate($selected_date);
    }

    $build['filters']['event_name'] = [
      '#type' => 'select',
      '#title' => $this->t('Event Name'),
      '#options' => ['' => $this->t('- All Events -')] + $event_names,
      '#default_value' => $selected_event,
      '#attributes' => [
        'id' => 'filter-event-name',
        'onchange' => 'this.form.submit()',
      ],
    ];

    $build['filters']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
    ];

    $build['filters']['export'] = [
      '#type' => 'link',
      '#title' => $this->t('Export as CSV'),
      '#url' => \Drupal\Core\Url::fromRoute('event_registration.export_csv', [], ['query' => $request->query->all()]),
      '#attributes' => ['class' => ['button']],
    ];

    // Get participant count
    $count = $this->getParticipantCount($selected_date, $selected_event);
    $build['count'] = [
      '#markup' => '<p><strong>' . $this->t('Total Participants: @count', ['@count' => $count]) . '</strong></p>',
    ];

    // Build registrations table
    $registrations = $this->getRegistrations($selected_date, $selected_event);

    $header = [
      $this->t('Name'),
      $this->t('Email'),
      $this->t('Event Date'),
      $this->t('College Name'),
      $this->t('Department'),
      $this->t('Submission Date'),
    ];

    $rows = [];
    foreach ($registrations as $reg) {
      $rows[] = [
        $reg->full_name,
        $reg->email,
        $reg->event_date,
        $reg->college_name,
        $reg->department,
        date('Y-m-d H:i:s', $reg->created),
      ];
    }

    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No registrations found.'),
      '#attributes' => ['id' => 'registrations-table'],
    ];

    return $build;
  }

  /**
   * Exports registrations as CSV.
   */
  public function exportCsv(Request $request) {
    $selected_date = $request->query->get('event_date', '');
    $selected_event = $request->query->get('event_name', '');

    $registrations = $this->getRegistrations($selected_date, $selected_event);

    $response = new StreamedResponse(function () use ($registrations) {
      $handle = fopen('php://output', 'w');

      fputcsv($handle, ['Name', 'Email', 'Event Date', 'Event Name', 'Category', 'College Name', 'Department', 'Submission Date']);

      foreach ($registrations as $reg) {
        fputcsv($handle, [
          $reg->full_name,
          $reg->email,
          $reg->event_date,
          $reg->event_name,
          $reg->event_category,
          $reg->college_name,
          $reg->department,
          date('Y-m-d H:i:s', $reg->created),
        ]);
      }

      fclose($handle);
    });

    $response->headers->set('Content-Type', 'text/csv');
    $response->headers->set('Content-Disposition', 'attachment; filename="registrations.csv"');

    return $response;
  }

  /**
   * Helper to get all event dates.
   */
  protected function getEventDates() {
    $query = $this->database->select('event_registration_config', 'c');
    $query->fields('c', ['event_date']);
    $query->distinct();
    $query->orderBy('event_date', 'DESC');
    return $query->execute()->fetchAllKeyed(0, 0);
  }

  /**
   * Helper to get event names by date.
   */
  protected function getEventNamesByDate($date) {
    $query = $this->database->select('event_registration_config', 'c');
    $query->fields('c', ['id', 'event_name']);
    $query->condition('event_date', $date);
    return $query->execute()->fetchAllKeyed();
  }

  /**
   * Helper to get participant count.
   */
  protected function getParticipantCount($date = '', $event_id = '') {
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
   * Helper to get registrations.
   */
  protected function getRegistrations($date = '', $event_id = '') {
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

}
