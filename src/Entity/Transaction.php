<?php

namespace Drupal\transaction\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\transaction\TransactionInterface;
use Drupal\user\UserInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\transaction\InvalidTransactionStateException;

/**
 * Provides the transaction content entity.
 *
 * @ContentEntityType(
 *   id = "transaction",
 *   label = @Translation("Transaction"),
 *   label_singular = @Translation("transaction"),
 *   label_plural = @Translation("transactions"),
 *   label_count = @PluralTranslation(
 *     singular = "@count transaction",
 *     plural = "@count transaction",
 *   ),
 *   admin_permission = "administer transactions",
 *   bundle_label = @Translation("Transaction type"),
 *   handlers = {
 *     "list_builder" = "Drupal\transaction\TransactionListBuilder",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "access" = "Drupal\transaction\TransactionAccessControlHandler",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "default" = "Drupal\transaction\Form\TransactionForm",
 *       "add" = "Drupal\transaction\Form\TransactionForm",
 *       "edit" = "Drupal\transaction\Form\TransactionForm",
 *       "delete" = "Drupal\transaction\Form\TransactionDeleteForm",
 *       "execute" = "Drupal\transaction\Form\TransactionExecuteForm"
 *     },
 *     "route_provider" = {
 *       "default" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *     "transactor" = "Drupal\transaction\TransactorHandler",
 *   },
 *   base_table = "transaction",
 *   data_table = "transaction_field_data",
 *   translatable = FALSE,
 *   entity_keys = {
 *     "id" = "id",
 *     "bundle" = "type",
 *     "uuid" = "uuid",
 *     "uid" = "uid",
 *   },
 *   bundle_entity_type = "transaction_type",
 *   field_ui_base_route = "entity.transaction_type.edit_form",
 *   permission_granularity = "bundle",
 *   links = {
 *     "canonical" = "/transaction/{transaction}",
 *     "add-form" = "/transaction/add/{transaction_type}/{target_entity_type}/{target_entity}",
 *     "edit-form" = "/transaction/{transaction}/edit",
 *     "delete-form" = "/transaction/{transaction}/delete",
 *     "execute-form" = "/transaction/{transaction}/execute",
 *     "collection" = "/transaction/{transaction_type}/{target_entity_type}/{target_entity}",
 *   }
 * )
 */
class Transaction extends ContentEntityBase implements TransactionInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    // Generic base fields.
    $fields = parent::baseFieldDefinitions($entity_type);

    // Creation timestamp.
    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the transaction was created.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('view', [
        'type' => 'timestamp',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE);

    // User ID (transaction author).
    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of the author.'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDefaultValueCallback('Drupal\transaction\Entity\Transaction::getCurrentUserId')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE);

    // The target_entity. Override by the transaction type entity to adjust
    // the target entity type.
    // @see \Drupal\transaction\Entity\TransactionType::postSave()
    $fields['target_entity'] = BaseFieldDefinition::create('entity_reference')
      ->setDisplayOptions('view', [
        'type' => 'entity_reference_label',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Operation code field.
    $fields['operation'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Operation'))
      ->setDescription(t('An internal operation code defined by the transactor plugin that identifies the particular kind of transaction.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', EntityTypeInterface::ID_MAX_LENGTH);

    // Execution timestamp.
    $fields['executed'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Executed on'))
      ->setDescription(t('The time that the transaction was executed.'))
      ->setRequired(FALSE)
      ->setDisplayOptions('view', [
        'type' => 'timestamp',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // The user that executes the transaction.
    $fields['executor'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Executed by'))
      ->setDescription(t('The user ID of the user that executed the transaction.'))
      ->setRequired(FALSE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDisplayOptions('view', [
        'type' => 'entity_reference_label',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Properties.
    $fields['properties'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Properties'))
      ->setDescription(t('A name-value map managed by the transactor plugin.'));

    // Description (computed).
    $fields['description'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Description'))
      ->setDescription(t('A human-readable description of the transaction.'))
      ->setComputed(TRUE)
      ->setClass('\Drupal\transaction\TransactionDescriptionItemList');

    // Details (computed).
    $fields['details'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Details'))
      ->setDescription(t('A list of details of the transaction.'))
      ->setComputed(TRUE)
      ->setClass('\Drupal\transaction\TransactionDetailsItemList');

    return $fields;
  }

  /**
   * Default value callback for 'uid' base field definition.
   *
   * @see ::baseFieldDefinitions()
   *
   * @return array
   *   An array of default values.
   */
  public static function getCurrentUserId() {
    return [\Drupal::currentUser()->id()];
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return strip_tags($this->getDescription());
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('uid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('uid', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->getEntityKey('uid');
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getExecutionTime() {
    return $this->get('executed')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setExecutionTime($timestamp) {
    $this->set('executed', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getProperty($key) {
    $properties = $this->getProperties();
    return isset($properties[$key]) ? $properties[$key] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setProperty($key, $value = NULL) {
    $item = $this->get('properties')->first() ?: $this->get('properties')->appendItem();

    $map = $item->getValue();
    if ($value === NULL) {
      unset($map[$key]);
    }
    else {
      $map[$key] = $value;
    }

    $item->setValue($map);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getProperties() {
    $properties = $this->get('properties');
    return $properties->isEmpty() ? [] : $properties->first()->toArray();
  }

  /**
   * {@inheritdoc}
   */
  public function execute($save = TRUE, UserInterface $user = NULL) {
    return $this->transactorHandler()->doExecute($this, $save, $user);
  }

  /**
   * {@inheritdoc}
   */
  public function getExecutorId() {
    return $this->get('executor')->isEmpty() ? FALSE : $this->get('executor')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getExecutor() {
    return $this->get('executor')->isEmpty() ? NULL : $this->get('executor')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setExecutor(UserInterface $user) {
    $this->get('executor')->setValue($user->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getType() {
    return $this->bundle();
  }

  /**
   * {@inheritdoc}
   */
  public function getOperation() {
    return $this->get('operation')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setOperation($operation) {
    $this->set('operation', $operation);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPrevious() {
    if ($this->isPending()) {
      throw new InvalidTransactionStateException();
    }

    return $this->transactorHandler()->getPreviousTransaction($this);
  }

  /**
   * {@inheritdoc}
   */
  public function getNext() {
    if ($this->isPending()) {
      throw new InvalidTransactionStateException();
    }

    return $this->transactorHandler()->getNextTransaction($this);
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetEntity() {
    return $this->get('target_entity')->isEmpty() ? NULL : $this->get('target_entity')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetEntityId() {
    return $this->get('target_entity')->isEmpty() ? FALSE : $this->get('target_entity')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setTargetEntity(ContentEntityInterface $entity) {
    $this->set('target_entity', $entity->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription($reset = FALSE) {
    if ($reset || $this->get('description')->isEmpty()) {
      return $this->transactorHandler()->composeDescription($this);
    }

    return $this->get('description')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getDetails($reset = FALSE) {
    if ($reset) {
      return $this->transactorHandler()->composeDetails($this);
    }

    return $this->get('details')->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function isPending() {
    return $this->getExecutionTime() === NULL;
  }

  /**
   * Gets the transactor handler.
   *
   * @return \Drupal\transaction\TransactorHandlerInterface
   *   The transactor entity handler.
   */
  protected function transactorHandler() {
    return $this->entityTypeManager()->getHandler($this->getEntityTypeId(), 'transactor');
  }

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel) {
    $uri_route_parameters = parent::urlRouteParameters($rel);
    if ($rel === 'collection' || $rel == 'add-form') {
      $uri_route_parameters['transaction_type'] = $this->getType();
      $uri_route_parameters['target_entity_type'] = $this->get('type')->entity->getTargetEntityTypeId();
      // Transactions with empty target entity field is inconsistent. Returning
      // 0 to avoid exceptions. URL will end in a page not found.
      $uri_route_parameters['target_entity'] = $this->getTargetEntityId() ?: 0;
    }

    return $uri_route_parameters;
  }

}
