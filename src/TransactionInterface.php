<?php

namespace Drupal\transaction;

use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\UserInterface;

/**
 * The interface for transaction entities.
 */
interface TransactionInterface extends ContentEntityInterface, EntityOwnerInterface {

  /**
   * Transaction executed state.
   */
  const EXECUTED = 1;

  /**
   * Transaction pending state.
   */
  const PENDING = 0;

  /**
   * Returns the transaction type.
   *
   * @return string
   *   The transaction type.
   */
  public function getType();

  /**
   * Gets the transaction creation timestamp.
   *
   * @return int
   *   The creation timestamp.
   */
  public function getCreatedTime();

  /**
   * Sets the transaction creation timestamp.
   *
   * @param int $timestamp
   *   The subscription creation timestamp.
   *
   * @return \Drupal\transaction\TransactionInterface
   *   The called transaction entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Get the previous same-type transaction in order of execution.
   *
   * @return \Drupal\transaction\TransactionInterface
   *   The previously executed transaction. NULL if this is the first one.
   *
   * @throws \Drupal\transaction\InvalidTransactionStateException
   *   If the transaction was not executed yet.
   */
  public function getPrevious();

  /**
   * Get the next same-type transaction in order of execution.
   *
   * @return \Drupal\transaction\TransactionInterface
   *   The previously executed transaction. NULL if this is the last executed.
   *
   * @throws \Drupal\transaction\InvalidTransactionStateException
   *   If the transaction was not executed yet.
   */
  public function getNext();

  /**
   * Get the transaction target entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The transaction's target entity.
   */
  public function getTargetEntity();

  /**
   * Get the transaction target entity ID.
   *
   * @return int
   *   The transaction's target entity ID.
   */
  public function getTargetEntityId();

  /**
   * Sets the transaction's target entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The target content entity.
   *
   * @return \Drupal\transaction\TransactionInterface
   *   The called transaction entity.
   *
   * @throws \InvalidArgumentException
   *   If the type of the given entity is not valid for the transaction type.
   */
  public function setTargetEntity(ContentEntityInterface $entity);

  /**
   * Gets the operation code.
   *
   * @return string
   *   The operation code for this transaction.
   */
  public function getOperation();

  /**
   * Sets the operation code.
   *
   * @param string $operation
   *   The operation code to set.
   *
   * @return \Drupal\transaction\TransactionInterface
   *   The called transaction.
   *
   * @throws \InvalidArgumentException
   *   If the operation code has an invalid format.
   */
  public function setOperation($operation);

  /**
   * Returns the transaction description.
   *
   * Transactors compose the description based on the transaction data.
   *
   * @param bool $reset
   *   Forces to recompose the transaction description.
   *
   * @return string
   *   The transaction description.
   */
  public function getDescription($reset = FALSE);

  /**
   * Returns the transaction details.
   *
   * Transactors compose datails based on the transaction data.
   *
   * @param bool $reset
   *   Forces to recompose the transaction details.
   *
   * @return string[]
   *   An array with details.
   */
  public function getDetails($reset = FALSE);

  /**
   * Returns a property value.
   *
   * @param string $key
   *   Property key to get.
   *
   * @return string
   *   The value of the given key, NULL if not set.
   */
  public function getProperty($key);

  /**
   * Sets a property value.
   *
   * @param string $key
   *   Property key.
   * @param string $value
   *   Value to store. NULL will delete the property.
   *
   * @return \Drupal\transaction\TransactionInterface
   *   The called transaction.
   */
  public function setProperty($key, $value = NULL);

  /**
   * Get a keyed array with all the transaction properties.
   *
   * @return array
   *   Array with all defined properties. Empty array if no one defined.
   */
  public function getProperties();

  /**
   * Executes the transaction.
   *
   * The transaction execution plugins may block the execution.
   * @see \Drupal\transaction\TransactorPluginInterface::validateTransaction()
   *
   * @param bool $save
   *   (optional) Save the transaction after succeeded execution.
   * @param \Drupal\User\UserInterface $executor
   *   (optional) The user that executes the transaction. The current user by
   *   default.
   *
   * @return bool
   *   TRUE if the transaction execution was done, FALSE otherwise.
   *
   * @throws \Drupal\transaction\InvalidTransactionStateException
   *   If the transaction is already executed.
   */
  public function execute($save = TRUE, UserInterface $executor = NULL);

  /**
   * Gets the transaction execution timestamp.
   *
   * @return int
   *   The execution timestamp. NULL if transaction was no executed.
   */
  public function getExecutionTime();

  /**
   * Sets the transaction execution timestamp.
   *
   * @param int $timestamp
   *   The execution timestamp.
   *
   * @return \Drupal\transaction\TransactionInterface
   *   The called transaction.
   */
  public function setExecutionTime($timestamp);

  /**
   * Gets the ID of the user that executed the transaction.
   *
   * @return int
   *   The user ID of the executor. FALSE if transaction was no executed.
   */
  public function getExecutorId();

  /**
   * Gets the user that executed the transaction.
   *
   * @return \Drupal\User\UserInterface
   *   The executor user. NULL if transaction was no executed.
   */
  public function getExecutor();

  /**
   * Sets the user that executed the transaction.
   *
   * @param \Drupal\User\UserInterface $user
   *   The executor user.
   *
   * @return \Drupal\transaction\TransactionInterface
   *   The called transaction.
   */
  public function setExecutor(UserInterface $user);

  /**
   * Indicates if the transaction is pending execution.
   *
   * @return bool
   *   TRUE on pending execution, FALSE if execution was done successfully.
   */
  public function isPending();

}
