<?php

namespace Drupal\Tests\transaction\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Base class for kernel tests of the Transaction module.
 */
abstract class KernelTransactionTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'system',
    'user',
    'filter',
    'text',
    'field',
    'dynamic_entity_reference',
    'token',
    'transaction',
    'entity_test',
  ];

  /**
   * The target entity.
   *
   * @var \Drupal\entity_test\Entity\EntityTest
   */
  protected $targetEntity;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('transaction');
    $this->installEntitySchema('user');

    $this->installConfig(['system', 'user', 'transaction']);

    $this->prepareTargetEntityLastTransactionField();
    $this->prepareTransactionLogMessageField();

    // Grant the administrative transaction permissions.
    Role::load(RoleInterface::ANONYMOUS_ID)
      ->grantPermission('administer transaction types')
      ->grantPermission('administer transactions')
      ->save();

    // Creates the target entity and saves it in order to get an entity ID and
    // be able to be referenced.
    $this->targetEntity = EntityTest::create(['name' => 'Target entity test']);
    $this->targetEntity->save();
  }

  /**
   * Creates an entity reference field to the latest executed transaction.
   */
  protected function prepareTargetEntityLastTransactionField() {
    // Entity reference field to the last executed transaction.
    FieldStorageConfig::create([
      'field_name' => 'test_last_transaction',
      'type' => 'entity_reference',
      'entity_type' => 'entity_test',
      'settings' => [
        'target_type' => 'transaction',
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => 'test_last_transaction',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ])->save();
  }

  /**
   * Adds a log message text field to the transaction entity type.
   */
  protected function prepareTransactionLogMessageField() {
    // Log message field in the transaction entity.
    FieldStorageConfig::create([
      'field_name' => 'test_log_message',
      'type' => 'string',
      'entity_type' => 'transaction',
    ])->save();
  }

}
