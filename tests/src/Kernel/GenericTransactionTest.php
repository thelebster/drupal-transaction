<?php

namespace Drupal\Tests\transaction\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\transaction\Entity\Transaction;
use Drupal\transaction\Entity\TransactionOperation;
use Drupal\transaction\Entity\TransactionType;
use Drupal\user\Entity\User;

/**
 * Tests the generic transactor.
 *
 * @group transaction
 */
class GenericTransactionTest extends KernelTransactionTestBase {

  /**
   * A generic transaction to work with.
   *
   * @var \Drupal\transaction\TransactionInterface
   */
  protected $transaction;

  /**
   * A log message.
   *
   * @var string
   */
  protected $logMessage = 'Log message';

  /**
   * A transaction operation.
   *
   * @var \Drupal\transaction\TransactionOperationInterface
   */
  protected $transactionOperation;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->prepareGenericTransactionType();
    $this->prepareGenericTransactionOperation();
    $this->prepareGenericTransaction();
  }

  /**
   * Creates a generic transaction type.
   */
  protected function prepareGenericTransactionType() {
    TransactionType::create([
      'id' => 'test_generic',
      'label' => 'Test generic',
      'target_entity_type' => 'entity_test',
      'transactor' => [
        'id' => 'transaction_generic',
        'settings' => [
          'last_transaction' => 'test_last_transaction',
          'log_message' => 'test_log_message',
        ],
      ],
    ])->save();

    // Adds the test log message field.
    FieldConfig::create([
      'field_name' => 'test_log_message',
      'entity_type' => 'transaction',
      'bundle' => 'test_generic',
    ])->save();
  }

  /**
   * Creates a transaction operation to work with.
   */
  protected function prepareGenericTransactionOperation() {
    $this->transactionOperation = TransactionOperation::create([
      'id' => 'test_operation',
      'transaction_type' => 'test_generic',
      'description' => '[transaction:type] #[transaction:id]',
      'details' => [
        'Executed by UID: [transaction:executor:target_id]',
        'Transaction UUID: [transaction:uuid]',
      ],
    ]);
    $this->transactionOperation->save();
  }

  /**
   * Creates a generic transaction to work with.
   */
  protected function prepareGenericTransaction() {
    $this->transaction = Transaction::create([
      'type' => 'test_generic',
      'target_entity' => $this->targetEntity,
      'test_log_message' => $this->logMessage,
    ]);
  }

  /**
   * Tests generic transaction creation.
   */
  public function testCreateGenericTransaction() {
    $transaction = $this->transaction;

    // Checks status for new non-executed transaction.
    $this->assertEquals($this->targetEntity, $transaction->getTargetEntity());
    $this->assertEquals('Unsaved transaction (pending)', $transaction->getDescription());
    $this->assertEquals([$this->logMessage], $transaction->getDetails());
    $this->assertNull($transaction->getExecutionTime());
    $this->assertNull($transaction->getExecutor());
    $this->assertFalse($transaction->getResultCode());
    $this->assertFalse($transaction->getResultMessage());

    $transaction->save();
    $this->assertEquals('Transaction 1 (pending)', $transaction->getDescription());
  }

  /**
   * Tests generic transaction execution.
   */
  public function testExecuteGenericTransaction() {
    $transaction = $this->transaction;

    $this->assertTrue($transaction->execute());
    // Checks the transaction status after its execution.
    $this->assertEquals('Transaction 1', $transaction->getDescription());
    $this->assertNotNull($transaction->getExecutionTime());
    $this->assertEquals(User::getAnonymousUser()->id(), $transaction->getExecutorId());
    $this->assertGreaterThan(0, $transaction->getResultCode());
    $this->assertEquals('Transaction executed successfully.', $transaction->getResultMessage());
  }

  /**
   * Tests generic transaction execution.
   */
  public function testGenericTransactionOperation() {
    $transaction = $this->transaction;

    // Sets an operation to the transaction and executes it.
    $transaction->setOperation($this->transactionOperation)->execute();
    // Checks if the transaction description and details are composed from their
    // templates in the operation.
    $this->assertEquals('Test generic #1', $transaction->getDescription());
    $expected_details = [
      $this->logMessage,
      'Executed by UID: ' . $transaction->getExecutorId(),
      'Transaction UUID: ' . $transaction->uuid(),
    ];
    $this->assertEquals($expected_details, $transaction->getDetails());
  }

}
