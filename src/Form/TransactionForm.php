<?php

namespace Drupal\transaction\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\transaction\TransactionTypeInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Form controller for the transaction entity.
 */
class TransactionForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ContentEntityInterface $target_entity = NULL) {
    /** @var \Drupal\transaction\TransactionInterface $transaction */
    $transaction = $this->entity;

    $form = parent::buildForm($form, $form_state);

    // Set the target entity.
    if ($target_entity) {
      $transaction->setTargetEntity($target_entity);
    }

    // Grouping status & authoring in tabs.
    $form['advanced'] = [
      '#type' => 'vertical_tabs',
      '#weight' => 99,
    ];

    $form['transaction_authoring'] = [
      '#type' => 'details',
      '#title' => $this->t('Transaction authoring'),
      '#open' => TRUE,
      '#group' => 'advanced',
    ];

    $form['uid']['#group'] = 'transaction_authoring';
    $form['created']['#group'] = 'transaction_authoring';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $msg_args = [
      '@type' => $this->entity->get('type')->entity->label(),
      '%description' => $this->entity->label(),
    ];

    drupal_set_message(parent::save($form, $form_state) == SAVED_NEW
      ? $this->t('New transaction of type @type has been created.', $msg_args)
      : $this->t('Transaction %description updated.', $msg_args));

    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
  }

}
