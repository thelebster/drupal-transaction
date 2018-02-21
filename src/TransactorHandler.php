<?php

namespace Drupal\transaction;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Utility\Token;
use Drupal\transaction\Event\TransactionExecutionEvent;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Component\Datetime\Time;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

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
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Creates a new TransactorHandler object.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $transaction_storage
   *   The transaction entity type storage.
   * @param \Drupal\Component\Datetime\Time $time_service
   *   The time service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(EntityStorageInterface $transaction_storage, Time $time_service, AccountInterface $current_user, Token $token, EventDispatcherInterface $event_dispatcher) {
    $this->transactionStorage = $transaction_storage;
    $this->timeService = $time_service;
    $this->currentUser = $current_user;
    $this->token = $token;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('datetime.time'),
      $container->get('current_user'),
      $container->get('token'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function doValidate(TransactionInterface $transaction) {
    $last_executed = $this->getLastExecutedTransaction($transaction->getTypeId(), $transaction->getTargetEntityId(), $transaction->getType()->getTargetEntityTypeId());
    return $this->transactorPlugin($transaction)->validateTransaction($transaction, $last_executed);
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

    $last_executed = $this->getLastExecutedTransaction($transaction->getTypeId(), $transaction->getTargetEntityId(), $transaction->getType()->getTargetEntityTypeId());
    if ($result_code = $this->transactorPlugin($transaction)->executeTransaction($transaction, $last_executed)) {
      $transaction->setExecutionTime($this->timeService->getRequestTime());
      $transaction->setResultCode($result_code);

      if (!$executor
        && $this->currentUser
        && $this->currentUser->id()) {
        $executor = User::load($this->currentUser->id());
      }
      $transaction->setExecutor($executor ? : User::getAnonymousUser());

      // Launch the transaction execution event.
      $this->eventDispatcher->dispatch(TransactionExecutionEvent::EVENT_NAME, new TransactionExecutionEvent($transaction));

      // Save the transaction and the updated target entity.
      if ($save
        && $transaction->save()
        && $transaction->getProperty(TransactionInterface::PROPERTY_TARGET_ENTITY_UPDATED)
        && $target_entity = $transaction->getTargetEntity()) {
        $target_entity->save();
      }

      return $result_code;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function composeResultMessage(TransactionInterface $transaction, $langcode = NULL) {
    if ($transaction->isPending()) {
      throw new InvalidTransactionStateException('The execution result message can not be composed for a pending execution transaction.');
    }

    return $this->transactorPlugin($transaction)->getResultMessage($transaction, $langcode);
  }

  /**
   * {@inheritdoc}
   */
  public function composeDescription(TransactionInterface $transaction, $langcode = NULL) {
    if ($operation = $transaction->getOperation()) {
      // Description from operation template.
      $token_options = ['clear' => TRUE];
      if ($langcode) {
        $token_options['langcode'] = $langcode;
      }

      $target_entity = $transaction->getTargetEntity();
      $target_entity_type_id = $target_entity->getEntityTypeId();
      $token_data = [
        'transaction' => $transaction,
        TransactorHandler::getTokenContextFromEntityTypeId($target_entity_type_id) => $target_entity,
      ];

      $description = PlainTextOutput::renderFromHtml($this->token->replace($operation->getDescription(), $token_data, $token_options));
    }
    else {
      // Default description from the transactor.
      $description = $this->transactorPlugin($transaction)->getTransactionDescription($transaction, $langcode);
    }

    return $description;
  }

  /**
   * {@inheritdoc}
   */
  public function composeDetails(TransactionInterface $transaction, $langcode = NULL) {
    // Details from transactor.
    $details = $this->transactorPlugin($transaction)->getTransactionDetails($transaction, $langcode);

    // Details from operation details template.
    if ($operation = $transaction->getOperation()) {
      $token_options = ['clear' => TRUE];
      if ($langcode) {
        $token_options['langcode'] = $langcode;
      }

      $target_entity = $transaction->getTargetEntity();
      $target_entity_type_id = $target_entity->getEntityTypeId();
      $token_data = [
        'transaction' => $transaction,
        TransactorHandler::getTokenContextFromEntityTypeId($target_entity_type_id) => $target_entity,
      ];

      foreach ($operation->getDetails() as $detail) {
        $details[] = PlainTextOutput::renderFromHtml($this->token->replace($detail, $token_data, $token_options));
      }
    }

    return $details;
  }

  /**
   * {@inheritdoc}
   */
  public function getPreviousTransaction(TransactionInterface $transaction) {
    if ($transaction->isPending()) {
      throw new InvalidTransactionStateException('Cannot get the previously executed transaction to one that is pending execution.');
    }

    $result = $this->transactionStorage->getQuery()
      ->condition('type', $transaction->getTypeId())
      ->condition('target_entity.target_id', $transaction->getTargetEntityId())
      ->condition('target_entity.target_type', $transaction->getType()->getTargetEntityTypeId())
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
      ->condition('type', $transaction->getTypeId())
      ->condition('target_entity.target_id', $transaction->getTargetEntityId())
      ->condition('target_entity.target_type', $transaction->getType()->getTargetEntityTypeId())
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

  /**
   * Guess the token context for a entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return string
   *   The token context for the given entity type ID.
   */
  public static function getTokenContextFromEntityTypeId($entity_type_id) {
    switch ($entity_type_id) {
      case 'taxonomy_term':
        // Taxonomy term token type doesn't match the entity type's machine
        // name.
        $context = 'term';
        break;

      case 'taxonomy_vocabulary' :
        // Taxonomy vocabulary token type doesn't match the entity type's
        // machine name.
        $context = 'vocabulary';
        break;

      default :
        $context = $entity_type_id;
        break;
    }

    return $context;
  }

  /**
   * Gets the last executed transaction for a given type and target entity.
   *
   * @param string $transaction_type
   *   The transaction type.
   * @param string $target_entity_type
   *   The type of the target entity.
   * @param string $target_entity_id
   *   The ID of the target entity.
   *
   * @return NULL|\Drupal\transaction\TransactionInterface
   *   The last executed transaction, NULL if not found.
   */
  protected function getLastExecutedTransaction($transaction_type, $target_entity_type, $target_entity_id) {
    // Search the last executed transaction of the same type and with the same
    // target.
    $result = $this->transactionStorage->getQuery()
      ->condition('type', $transaction_type)
      ->condition('target_entity.target_type', $target_entity_type)
      ->condition('target_entity.target_id', $target_entity_id)
      ->exists('executed')
      ->range(0, 1)
      ->sort('executed', 'DESC')
      ->execute();

    return count($result) ? $this->transactionStorage->load(array_pop($result)) : NULL;
  }

}
