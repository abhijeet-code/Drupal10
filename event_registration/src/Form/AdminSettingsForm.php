<?php

namespace Drupal\event_registration\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Admin Settings Form.
 */
class AdminSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'event_registration.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'event_registration_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('event_registration.settings');

    $form['enable_admin_notifications'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Admin Notifications'),
      '#default_value' => $config->get('enable_admin_notifications'),
      '#description' => $this->t('If checked, the admin email address below will receive notifications for new registrations.'),
    ];

    $form['admin_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Admin Notification Email Address'),
      '#default_value' => $config->get('admin_email'),
      '#description' => $this->t('The email address to receive notifications.'),
      '#states' => [
        'visible' => [
          ':input[name="enable_admin_notifications"]' => ['checked' => TRUE],
        ],
        'required' => [
            ':input[name="enable_admin_notifications"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('event_registration.settings')
      ->set('enable_admin_notifications', $form_state->getValue('enable_admin_notifications'))
      ->set('admin_email', $form_state->getValue('admin_email'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
