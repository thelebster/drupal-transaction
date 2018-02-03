<?php

namespace Drupal\transaction;

use Drupal\Core\Session\AccountInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Component\Datetime\Time;

/**
 * Transactor entity handler.
 */
class TransactorHandler implements TransactorHandlerInterface {

  /**
   * The transaction entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $transactionStorage;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\Time
   */
  protected $timeService;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Creates a new TransactorHandler object.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $transaction_storage
   *   The transaction entity type storage.
   * @param \Drupal\Component\Datetime\Time $time_service
   *   The time service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(EntityStorageInterface $transaction_storage, Time $time_service, AccountInterface $current_user) {
    $this->transactionStorage = $transaction_storage;
    $this->timeService = $time_service;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('datetime.time'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function doValidate(TransactionInterface $transaction) {
    return $this->transactorPlugin($transaction)->validateTransaction($transaction);
  }

  /**
   * {@inheritdoc}
   */
  public function doExecute(TransactionInterface $transaction, $save = TRUE, UserInterface $executor = NULL) {
    if (!$transaction->isPending()) {
      throw new InvalidTransactionStateException('Cannot execute an already executed transaction.');
    }

    if (!$this->doValidate($transaction)) {
      return FALSE;
    }

    // Search the last executed transaction of the same type and with the same
    // target.
    $result = $this->transactionStorage->getQuery()
      ->condition('type', $transaction->getType())
      ->condition('target_entity', $transaction->getTargetEntityId())
      ->exists('executed')
      ->range(0, 1)
      ->sort('executed', 'DESC')
      ->execute();
    $last_executed = count($result) ? $this->transactionStorage->load(array_pop($result)) : NULL;

    if ($this->transactorPlugin($transaction)->executeTransaction($transaction, $last_executed)) {
      $transaction->setExecutionTime($this->timeService->getRequestTime());

      if (!$executor
        && $this->currentUser
        && $this->currentUser->id()) {
        $executor = User::load($this->currentUser->id());
      }
      $transaction->setExecutor($executor ? : User::getAnonymousUser());

      if ($save
        && $transaction->save()
        && $transaction->getTargetEntity()) {
        $transaction->getTargetEntity()->save();
      }
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function composeDescription(TransactionInterface $transaction, $langcode = NULL) {
    return $this->transactorPlugin($transaction)->getTransactionDescription($transaction, $langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function composeDetails(TransactionInterface $transaction, $langcode = NULL) {
    return $this->transactorPlugin($transaction)->getTransactionDetails($transaction, $langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function getPreviousTransaction(TransactionInterface $transaction) {
    if ($transaction->isPending()) {
      throw new InvalidTransactionStateException('Cannot get the previously executed transaction to one that is pending execution.');
    }

    $result = $this->transactionStorage->getQuery()
      ->condition('type', $transaction->getType())
      ->condition('target_entity', $transaction->getTargetEntityId())
      ->exists('executed')
      ->condition('executed', $transaction->getExecutionTime(), '<')
      ->range(0, 1)
      ->sort('executed', 'DESC')
      ->execute();

    return count($result)
      ? $this->transactionStorage->load(array_pop($result))
      : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getNextTransaction(TransactionInterface $transaction) {
    if ($transaction->isPending()) {
      throw new InvalidTransactionStateException('Cannot get the next executed transaction to one that is pending execution.');
    }

    $result = $this->transactionStorage->getQuery()
      ->condition('type', $transaction->getType())
      ->condition('target_entity', $transaction->getTargetEntityId())
      ->exists('executed')
      ->condition('executed', $transaction->getExecutionTime(), '>')
      ->range(0, 1)
      ->sort('executed')
      ->execute();

    return count($result)
      ? $this->transactionStorage->load(array_pop($result))
      : NULL;
  }

  /**
   * Gets the transactor plugin for a given transaction entity.
   *
   * @param \Drupal\transaction\TransactionInterface $transaction
   *   The transaction.
   *
   * @return \Drupal\transaction\TransactorPluginInterface
   *   The transactor plugin for the given transaction;
   */
  protected function transactorPlugin(TransactionInterface $transaction) {
    return $transaction->get('type')->entity->getPlugin();
  }

}
