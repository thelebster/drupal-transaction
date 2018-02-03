<?php

namespace Drupal\transaction;

use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\user\UserInterface;

/**
 * Defines an interface for transactor handler.
 *
 * The transactor handler controls the transaction execution.
 */
interface TransactorHandlerInterface extends EntityHandlerInterface {

  /**
   * Validates a transaction for its execution.
   *
   * @param \Drupal\transaction\TransactionInterface $transaction
   *   The transaction to execute.
   *
   * @return bool
   *   TRUE if transaction is in proper state to be executed, FALSE otherwise.
   */
  public function doValidate(TransactionInterface $transaction);

  /**
   * Executes a transaction.
   *
   * @param \Drupal\transaction\TransactionInterface $transaction
   *   The transaction to execute.
   * @param bool $save
   *   Save the transaction after succeeded execution.
   * @param \Drupal\User\UserInterface $executor
   *   (optional) The user that executes the transaction. The current user by
   *   default.
   *
   * @return bool
   *   TRUE if transaction was executed successful, FALSE otherwise.
   *
   * @throws \Drupal\transaction\InvalidTransactionStateException
   *   If the transaction is already executed.
   */
  public function doExecute(TransactionInterface $transaction, $save = TRUE, UserInterface $executor = NULL);

  /**
   * Compose a human readable description for the given transaction.
   *
   * @param \Drupal\transaction\TransactionInterface $transaction
   *   The transaction to describe.
   * @param string $langcode
   *   (optional) For which language the transaction description should be
   *   composed, defaults to the current content language.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Translatable markup with the composed description.
   */
  public function composeDescription(TransactionInterface $transaction, $langcode = NULL);

  /**
   * Compose human readable details for the given transaction.
   *
   * @param \Drupal\transaction\TransactionInterface $transaction
   *   The transaction to detail.
   * @param string $langcode
   *   (optional) For which language the transaction details should be
   *   composed, defaults to the current content language.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup[]
   *   An array of translatable markup objects representing each one a line
   *   detailing the transaction. Empty array if no details were composed.
   */
  public function composeDetails(TransactionInterface $transaction, $langcode = NULL);

  /**
   * Get the previous same-type transaction in order of execution.
   *
   * @param \Drupal\transaction\TransactionInterface $transaction
   *   The transaction from which to get the previous.
   *
   * @return \Drupal\transaction\TransactionInterface
   *   The previously executed transaction. NULL if this is the first one.
   *
   * @throws \Drupal\transaction\InvalidTransactionStateException
   *   If the transaction is no executed.
   */
  public function getPreviousTransaction(TransactionInterface $transaction);

  /**
   * Get the next same-type transaction in order of execution.
   *
   * @param \Drupal\transaction\TransactionInterface $transaction
   *   The transaction from which to get the next.
   *
   * @return \Drupal\transaction\TransactionInterface
   *   The previously executed transaction. NULL if this is the last executed.
   *
   * @throws \Drupal\transaction\InvalidTransactionStateException
   *   If the transaction is no executed.
   */
  public function getNextTransaction(TransactionInterface $transaction);

}
