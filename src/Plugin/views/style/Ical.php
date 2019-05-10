<?php

namespace Drupal\views_ical\Plugin\views\style;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Drupal\Core\Url;
use Eluceo\iCal\Component\Event;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * Entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityFieldManagerInterface $entity_field_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['date_field'] = ['default' => NULL];
    $options['summary_field'] = ['default' => NULL];
    $options['location_field'] = ['default' => NULL];
    $options['description_field'] = ['default' => NULL];

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

    $form['description_field'] = array(
      '#type' => 'select',
      '#title' => $this->t('DESCRIPTION field'),
      '#options' => $field_options,
      '#default_value' => $this->options['description_field'],
      '#description' => $this->t('You may optionally include a DESCRIPTION component for each event in the iCal output. Choose which text field you would like to be output as the DESCRIPTION.'),
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
    /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $field_storage_definitions */
    $field_storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions($this->view->field[$this->options['date_field']]->definition['entity_type']);
    $date_field_definition = $field_storage_definitions[$this->view->field[$this->options['date_field']]->definition['field_name']];
    /** @var string $date_field_type */
    $date_field_type = $date_field_definition->getType();

    $events = [];
    $user_timezone = \drupal_get_user_timezone();

    // Make sure the events are made as per the configuration in view.
    /** @var string $timezone_override */
    $timezone_override = $this->view->field[$this->options['date_field']]->options['settings']['timezone_override'];
    if ($timezone_override) {
      $timezone = new \DateTimeZone($timezone_override);
    }
    else {
      $timezone = new \DateTimeZone($user_timezone);
    }

    foreach ($this->view->result as $row_index => $row) {
      // Use date_recur's API to generate the events.
      // Recursive events will be automatically handled here.
      if ($date_field_type === 'date_recur') {
        $this->addDateRecurEvent($events, $row->_entity, $timezone);
      }
      else {
        $this->addEvent($events, $row->_entity, $timezone);
      }
    }

    $build = [
      '#theme' => $this->themeFunctions(),
      '#view' => $this->view,
      '#options' => $this->options,
      '#rows' => $events,
    ];
    unset($this->view->row_index);
    return $build;
  }

  /**
   * Creates an event with default data.
   *
   * Event summary, location and description are set as defaults.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to be used for default data.
   *
   * @return \Eluceo\iCal\Component\Event
   *   A new event.
   */
  protected function createDefaultEvent(ContentEntityInterface $entity): Event {
    $event = new Event();

    if ($this->options['summary_field']) {
      /** @var \Drupal\Core\Field\FieldItemInterface $summary */
      $summary = $entity->{$this->options['summary_field']}->first();
      $event->setSummary($summary->getValue()['value']);
    }

    if ($this->options['location_field']) {
      /** @var \Drupal\Core\Field\FieldItemInterface $location */
      $location = $entity->{$this->options['location_field']}->first();
      $event->setLocation($location->getValue()['value']);
    }

    if ($this->options['description_field']) {
      /** @var \Drupal\Core\Field\FieldItemInterface $description */
      $description = $entity->{$this->options['description_field']}->first();
      $event->setDescription(\strip_tags($description->getValue()['value']));
    }

    $event->setUseTimezone(TRUE);

    return $event;
  }

  /**
   * Adds an event.
   *
   * This is used when the date_field type is `datetime` or `daterange`.
   *
   * @param \Eluceo\iCal\Component\Event[] $events
   *   Set of events where the new event will be added.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to be used for creating the event.
   * @param \DateTimeZone $timezone
   *   Timezone data to be specified to the event.
   *
   * @throws \Exception Throws exception if it fails to parse the datetime data from entity.
   */
  protected function addEvent(array &$events, ContentEntityInterface $entity, \DateTimeZone $timezone): void {
    $utc_timezone = new \DateTimeZone('UTC');

    foreach ($entity->get($this->options['date_field'])->getValue() as $date_entry) {
      $event = $this->createDefaultEvent($entity);

      $start_datetime = new \DateTime($date_entry['value'], $utc_timezone);
      $start_datetime->setTimezone($timezone);
      $event->setDtStart($start_datetime);

      if (!empty($date_entry['end_value'])) {
        $end_datetime = new \DateTime($date_entry['end_value'], $utc_timezone);
        $end_datetime->setTimezone($timezone);
        $event->setDtEnd($end_datetime);
      }

      $events[] = $event;
    }
  }

  /**
   * Adds an event.
   *
   * This is used when the date_field type is `date_recur`.
   *
   * @param \Eluceo\iCal\Component\Event[] $events
   *   Set of events where the new event will be added.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to be used for creating the event.
   * @param \DateTimeZone $timezone
   *   Timezone data to be specified to the event.
   */
  protected function addDateRecurEvent(array &$events, ContentEntityInterface $entity, \DateTimeZone $timezone): void {
    /** @var \Drupal\date_recur\Plugin\Field\FieldType\DateRecurItem[] $field_items */
    $field_items = $entity->{$this->options['date_field']};

    foreach ($field_items as $index => $item) {
      /** @var \Drupal\date_recur\DateRange[] $occurrences */
      $occurrences = $item->getHelper()->getOccurrences();

      foreach ($occurrences as $occurrence) {
        $event = $this->createDefaultEvent($entity);

        /** @var \DateTime $start_datetime */
        $start_datetime = $occurrence->getStart();
        $start_datetime->setTimezone($timezone);
        $event->setDtStart($start_datetime);

        /** @var \DateTime $end_datetime */
        $end_datetime = $occurrence->getEnd();
        $end_datetime->setTimezone($timezone);
        $event->setDtEnd($end_datetime);

        $events[] = $event;
      }
    }
  }

}
