<?php

namespace Drupal\transaction\Plugin\Transaction;

use Drupal\transaction\TransactionInterface;

/**
 * Transactor for accounting type transactions.
 *
 * @Transactor(
 *   id = "transaction_balance",
 *   title = @Translation("Balance"),
 *   description = @Translation("Transactor for accounting type transactions."),
 *   transaction_fields = {
 *     {
 *       "name" = "amount",
 *       "type" = "decimal",
 *       "title" = @Translation("Amount"),
 *       "description" = @Translation("A numeric field with the amount of the transaction."),
 *       "required" = TRUE,
 *       "list" = TRUE,
 *     },
 *     {
 *       "name" = "balance",
 *       "type" = "decimal",
 *       "title" = @Translation("Balance"),
 *       "description" = @Translation("A numeric field to store the current balance."),
 *       "required" = TRUE,
 *       "list" = TRUE,
 *     },
 *     {
 *       "name" = "log_message",
 *       "type" = "string",
 *       "title" = @Translation("Description"),
 *       "description" = @Translation("A text field to store a description for the transaction."),
 *       "required" = FALSE,
 *     },
 *     {
 *       "name" = "details",
 *       "type" = "string",
 *       "title" = @Translation("Details"),
 *       "description" = @Translation("A text field with additional details about the transaction."),
 *       "required" = FALSE,
 *     },
 *   },
 *   target_entity_fields = {
 *     {
 *       "name" = "last_transaction",
 *       "type" = "entity_reference",
 *       "title" = @Translation("Last transaction"),
 *       "description" = @Translation("A reference field in the target entity type to update with a reference to the last executed transaction of this type."),
 *       "required" = FALSE,
 *     },
 *     {
 *       "name" = "target_balance",
 *       "type" = "decimal",
 *       "title" = @Translation("Balance"),
 *       "description" = @Translation("A numeric field to update with the current balance."),
 *       "required" = FALSE,
 *     },
 *   },
 * )
 */
class BalanceTransactor extends GenericTransactor {

  /**
   * {@inheritdoc}
   */
  public function validateTransaction(TransactionInterface $transaction) {
    // @todo check required fields and values
    return parent::validateTransaction($transaction);
  }

  /**
   * {@inheritdoc}
   */
  public function executeTransaction(TransactionInterface $transaction, TransactionInterface $last_executed = NULL) {
    if (!parent::executeTransaction($transaction, $last_executed)) {
      return FALSE;
    }

    /** @var \Drupal\transaction\TransactionTypeInterface $transaction_type */
    $transaction_type = $transaction->get('type')->entity;
    $settings = $transaction_type->getPluginSettings();

    // Current balance from the last executed transaction. The current transaction
    // balance will take as the initial balance.
    $balance = $last_executed ? $last_executed->get($settings['balance'])->value : $transaction->get($settings['balance'])->value;
    // Transaction amount.
    $amount = $transaction->get($settings['amount'])->value;
    // Set result into transaction balance.
    $result = $balance + $amount;
    $transaction->get($settings['balance'])->setValue($result);

    // Reflect balance on the target entity.
    $target_entity = $transaction->getTargetEntity();
    if (isset($settings['target_balance'])
      && $target_entity->hasField($settings['target_balance'])) {
      $target_entity->get($settings['target_balance'])->setValue($result);
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getTransactionDescription(TransactionInterface $transaction, $langcode = NULL) {
    // @todo if transaction description field defined and no empty value on field, return that, else, the default
    return parent::getTransactionDescription($transaction, $langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function getTransactionDetails(TransactionInterface $transaction, $langcode = NULL) {
    // @todo if additional details field defined and no empty value on field, add values
    $details = [];
    return $details + parent::getTransactionDetails($transaction, $langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function getExecutionIndications(TransactionInterface $transaction, $langcode = NULL) {
    // @todo Show the balance result after this transaction.
    return parent::getExecutionIndications($transaction, $langcode);
  }

}
