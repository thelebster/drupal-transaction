<?php

namespace Drupal\transaction\Plugin\Field\FieldType;

use Drupal\Core\Field\Plugin\Field\FieldType\StringItem;

/**
 * Transaction description field, variant of the 'string' field type.
 *
 * @FieldType(
 *   id = "transaction_description",
 *   label = @Translation("Transaction description"),
 *   description = @Translation("A human-readable description of an transaction."),
 *   default_widget = "string_textfield",
 *   default_formatter = "string",
 * )
 */
class TransactionDescriptionItem extends StringItem {

  /**
   * Whether or not the value has been calculated.
   *
   * @var bool
   */
  protected $isCalculated = FALSE;

  /**
   * {@inheritdoc}
   */
  public function __get($name) {
    $this->ensureCalculated();
    return parent::__get($name);
  }
  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $this->ensureCalculated();
    return parent::isEmpty();
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $this->ensureCalculated();
    return parent::getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function setValue($values, $notify = TRUE) {
    // This is a calculated read-only field.
    return;
  }

  /**
   * Calculates the value of the field and sets it.
   */
  protected function ensureCalculated() {
    if (!$this->isCalculated) {
      /** @var \Drupal\transaction\TransactionInterface $entity */
      $entity = $this->getEntity();
      if (!$entity->isNew()) {
        parent::setValue([
          'value' => $entity->getDescription(TRUE),
        ]);
      }
      $this->isCalculated = TRUE;
    }
  }

}
