<?php

namespace Drupal\event_registration\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\event_registration\Service\EventRegistrationService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for the admin registration listing.
 */
class RegistrationController extends ControllerBase {

  /**
   * The event registration service.
   *
   * @var \Drupal\event_registration\Service\EventRegistrationService
   */
  protected $eventService;

  /**
   * Constructs a new RegistrationController.
   *
   * @param \Drupal\event_registration\Service\EventRegistrationService $event_service
   *   The event registration service.
   */
  public function __construct(EventRegistrationService $event_service) {
    $this->eventService = $event_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('event_registration.service')
    );
  }

  /**
   * Builds the admin listing page.
   */
  public function content(Request $request) {
    $build = [];

    $event_dates = $this->eventService->getAllEventDates();
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
      $event_names = $this->eventService->getEventNamesByDate($selected_date);
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
      '#url' => Url::fromRoute('event_registration.export_csv', [], ['query' => $request->query->all()]),
      '#attributes' => ['class' => ['button']],
    ];

    $count = $this->eventService->getParticipantCount($selected_date, $selected_event);
    $build['count'] = [
      '#markup' => '<p><strong>' . $this->t('Total Participants: @count', ['@count' => $count]) . '</strong></p>',
    ];

    $registrations = $this->eventService->getRegistrations($selected_date, $selected_event);

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

    $registrations = $this->eventService->getRegistrations($selected_date, $selected_event);

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

}
