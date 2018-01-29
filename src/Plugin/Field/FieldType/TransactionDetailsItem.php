<?php

namespace Drupal\transaction\Plugin\Field\FieldType;

use Drupal\Core\Field\Plugin\Field\FieldType\StringItem;

/**
 * Transaction details field, variant of the 'string' field type.
 *
 * @FieldType(
 *   id = "transaction_details",
 *   label = @Translation("Transaction details"),
 *   description = @Translation("Additional details of a transaction."),
 *   default_widget = "string_textfield",
 *   default_formatter = "string",
 * )
 */
class TransactionDetailsItem extends StringItem {

}
