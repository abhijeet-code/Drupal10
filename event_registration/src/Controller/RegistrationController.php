<?php

namespace Drupal\event_registration\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Core\Render\Markup;
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

    // Get event names if a date is selected
    $event_names = [];
    if ($selected_date) {
      $event_names = $this->eventService->getEventNamesByDate($selected_date);
    }

    $current_path = Url::fromRoute('event_registration.admin_list')->toString();
    $export_url = Url::fromRoute('event_registration.export_csv', [], ['query' => $request->query->all()])->toString();

    // Use inline_template to render raw HTML
    $build['filters'] = [
      '#type' => 'inline_template',
      '#template' => '
        <form method="get" action="{{ current_path }}" id="filter-form">
          <div style="display: flex; flex-wrap: wrap; align-items: flex-end; gap: 20px; background: #fff; padding: 20px; border: 1px solid #dedede; border-radius: 4px; margin-bottom: 25px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
            <div style="display: flex; flex-direction: column;">
              <label for="event_date" style="font-weight: bold; margin-bottom: 5px; color: #333;">{{ date_label }}</label>
              <select name="event_date" id="event_date" style="padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; min-width: 160px; height: 38px;" onchange="document.getElementById(\'event_name\').value=\'\'; this.form.submit();">
                <option value="">{{ all_dates }}</option>
                {% for date in event_dates %}
                  <option value="{{ date }}"{% if date == selected_date %} selected{% endif %}>{{ date }}</option>
                {% endfor %}
              </select>
            </div>
            <div style="display: flex; flex-direction: column;">
              <label for="event_name" style="font-weight: bold; margin-bottom: 5px; color: #333;">{{ name_label }}</label>
              <select name="event_name" id="event_name" style="padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; min-width: 160px; height: 38px;" onchange="this.form.submit();">
                <option value="">{{ all_events }}</option>
                {% for id, name in event_names %}
                  <option value="{{ id }}"{% if id == selected_event %} selected{% endif %}>{{ name }}</option>
                {% endfor %}
              </select>
            </div>
            <div style="display: flex; gap: 10px;">
              <button type="submit" class="button button--primary" style="height: 38px; padding: 0 20px; font-weight: bold; cursor: pointer;">{{ filter_label }}</button>
              <a href="{{ export_url }}" class="button" style="height: 38px; padding: 0 20px; line-height: 36px; text-decoration: none; border: 1px solid #ccc; background: #f5f5f5; color: #333; font-weight: bold; border-radius: 4px; box-sizing: border-box; display: inline-block;">{{ export_label }}</a>
            </div>
          </div>
        </form>',
      '#context' => [
        'current_path' => $current_path,
        'date_label' => $this->t('Event Date'),
        'name_label' => $this->t('Event Name'),
        'all_dates' => $this->t('- All Dates -'),
        'all_events' => $this->t('- All Events -'),
        'filter_label' => $this->t('Filter'),
        'export_label' => $this->t('Export as CSV'),
        'export_url' => $export_url,
        'event_dates' => $event_dates,
        'event_names' => $event_names,
        'selected_date' => $selected_date,
        'selected_event' => $selected_event,
      ],
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
