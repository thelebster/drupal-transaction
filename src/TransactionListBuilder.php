<?php

namespace Drupal\transaction;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Url;

/**
 * Provides a entity list page for transactions.
 */
class TransactionListBuilder extends EntityListBuilder {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The type of the transactions in the collection.
   *
   * @var \Drupal\transaction\TransactionTypeInterface
   */
  protected $transactionType;

  /**
   * The target entity of the transactions in collection.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $targetEntity;

  /**
   * Extra fields to list from plugin.
   *
   * @var array
   *   Field titles keyed by id.
   */
  protected $extraFields = [];

  /**
   * Constructs a new TransactionListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\transaction\TransactorPluginManagerInterface $transactor_manager
   *   The transactor plugin manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Symfony\Component\HttpFoundation\Request $current_request
   *   The currently active request object.
   * @param \Drupal\Core\Routing\RouteMatchInterface $current_route_match
   *   The current route match.
   */
  public function __construct(EntityTypeInterface $entity_type, TransactorPluginManagerInterface $transactor_manager, EntityTypeManagerInterface $entity_type_manager, DateFormatterInterface $date_formatter, Request $current_request, RouteMatchInterface $current_route_match) {
    try {
      parent::__construct($entity_type, $entity_type_manager->getStorage('transaction'));
      $this->dateFormatter = $date_formatter;

      $current_route = $current_route_match->getRouteObject();

      // This list builder can be targeted by multiple routes. When some
      // argument are not present in the request, we try to get from the route
      // options.
      /** @see \Drupal\transaction\Routing\RouteSubscriber */
      if (!($this->transactionType = $current_request->get('transaction_type'))
        && ($transaction_type_id = $current_route->getOption('_transaction_transaction_type_id'))) {
        $this->transactionType = $entity_type_manager->getStorage('transaction_type')->load($transaction_type_id);
      }

      if (!($this->targetEntity = $current_request->get('target_entity'))
        && ($target_entity_type_id = $current_route->getOption('_transaction_target_entity_type_id'))) {
        $this->targetEntity = $current_request->get($target_entity_type_id);
      }

      // Set transactor fields.
      if ($plugin_info = $transactor_manager->getTransactor($this->transactionType->getPluginId())) {
        foreach ($plugin_info['transaction_fields'] as $transactor_field_info) {
          if (!empty($transactor_field_info['list'])) {
            $this->extraFields[$transactor_field_info['name']] = $transactor_field_info['title'];
          }
        }
      }

    }
    catch (InvalidPluginDefinitionException $e) {
      // The transaction or transaction type storage is not present?
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('plugin.manager.transaction.transactor'),
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds() {
    $query = $this->getStorage()->getQuery()
      ->sort('executed', 'DESC')
      ->sort('created', 'DESC');

    if ($this->transactionType) {
      $query->condition('type', $this->transactionType->id());
    }

    if ($this->targetEntity) {
      $query->condition('target_entity', $this->targetEntity->id());
    }

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }

    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
      'description' => $this->t('Description'),
      'creation_date' => [
        'data' => $this->t('Created'),
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      ],
      'author' => [
        'data' => $this->t('Author'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'execution_date' => $this->t('Executed'),
      'executor' => [
        'data' => $this->t('Executor'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
    ];

    // Add transactor fields.
    foreach ($this->extraFields as $field_name => $field_title) {
      $header['field_' . $field_name] = $field_title;
    }

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\transaction\TransactionInterface $entity */
    $row = [];

    $row['description'] = [
      'data' => [
        '#type' => 'link',
        '#title' => $entity->label(),
        '#url' => $entity->toUrl(),
      ],
    ];
    $row['creation_date'] = $this->dateFormatter->format($entity->getCreatedTime(), 'short');
    $row['author'] = [
      'data' => [
        '#theme' => 'username',
        '#account' => $entity->getOwner(),
      ],
    ];
    $row['execution_date'] = $entity->isPending()
      ? []
      : $this->dateFormatter->format($entity->getExecutionTime(), 'short');
    $row['executor'] = $entity->isPending()
      ? []
      : [
          'data' => [
          '#theme' => 'username',
          '#account' => $entity->getExecutor(),
        ],
      ];

    // Extra field values.
    $plugin_settings = $this->transactionType->getPluginSettings();
    foreach (array_keys($this->extraFields) as $field_name) {
      $row['field_' . $field_name]['data'] = $entity->get($plugin_settings[$field_name])->view('list');
    }

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   *
   * Builds the entity listing as renderable array for table.html.twig.
   *
   * @todo Add a link to add a new item to the #empty text.
   */
  public function render() {
    $build = parent::render();
    $build['table']['#empty'] = $this->t('No @type transactions found for @target. <a href=":url">Create one</a>.', [
      '@type' => $this->transactionType->label(),
      '@target' => $this->targetEntity->label(),
      ':url' => Url::fromRoute('entity.transaction.add_form', [
        'transaction_type' => $this->transactionType->id(),
        'target_entity_type' => $this->targetEntity->getEntityTypeId(),
        'target_entity' => $this->targetEntity->id(),
      ])->toString(),
    ]);

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    // Add the execute operation.
    if ($entity->access('execute') && $entity->hasLinkTemplate('execute-form')) {
      $operations['execute'] = array(
        'title' => $this->t('Execute'),
        'weight' => 20,
        'url' => $entity->toUrl('execute-form'),
      );
    }

    return $operations;
  }

}
