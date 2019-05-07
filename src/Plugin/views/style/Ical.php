<?php

namespace Drupal\views_ical\Plugin\views\style;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Drupal\Core\Url;

/**
 * Style plugin to render an iCal feed.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "ical",
 *   title = @Translation("iCal Feed"),
 *   help = @Translation("Display the results as an iCal feed."),
 *   theme = "views_view_ical",
 *   display_types = {"feed"}
 * )
 */
class Ical extends StylePluginBase {
  protected $usesFields = TRUE;
  protected $usesGrouping = FALSE;
  protected $usesRowPlugin = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['date_field'] = ['default' => NULL];
    $options['summary_field'] = ['default' => NULL];
    $options['location_field'] = ['default' => NULL];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    /** @var array $field_options */
    $field_options = $this->displayHandler->getFieldLabels();

    $form['date_field'] = array(
      '#type' => 'select',
      '#title' => $this->t('Date field'),
      '#options' => $field_options,
      '#default_value' => $this->options['date_field'],
      '#description' => $this->t('Please identify the field to use as the iCal date for each item in this view.'),
      '#required' => TRUE,
    );

    $form['summary_field'] = array(
      '#type' => 'select',
      '#title' => $this->t('SUMMARY field'),
      '#options' => $field_options,
      '#default_value' => $this->options['summary_field'],
      '#description' => $this->t('You may optionally change the SUMMARY component for each event in the iCal output. Choose which text field you would like to be output as the SUMMARY.'),
    );

    $form['location_field'] = array(
      '#type' => 'select',
      '#title' => $this->t('LOCATION field'),
      '#options' => $field_options,
      '#default_value' => $this->options['location_field'],
      '#description' => $this->t('You may optionally include a LOCATION component for each event in the iCal output. Choose which text field you would like to be output as the LOCATION.'),
    );
  }

  public function attachTo(array &$build, $display_id, Url $feed_url, $title) {
    $url_options = [];
    $input = $this->view->getExposedInput();
    if ($input) {
      $url_options['query'] = $input;
    }
    $url_options['absolute'] = TRUE;

    $url = $feed_url->setOptions($url_options)->toString();

    $this->view->feedIcons[] = [];

    // Attach a link to the iCal feed, which is an alternate representation.
    $build['#attached']['html_head_link'][][] = [
      'rel' => 'alternate',
      'type' => 'application/calendar',
      'href' => $url,
      'title' => $title,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    if (empty($this->view->rowPlugin)) {
      trigger_error('Drupal\views_ical\Plugin\views\style\Ical: Missing row plugin', E_WARNING);
      return [];
    }
    $rows = [];

    foreach ($this->view->result as $row_index => $row) {
      $this->view->row_index = $row_index;
      $rows[] = $this->view->rowPlugin->render($row);
    }

    $build = [
      '#theme' => $this->themeFunctions(),
      '#view' => $this->view,
      '#options' => $this->options,
      '#rows' => $rows,
    ];
    unset($this->view->row_index);
    return $build;
  }

}
