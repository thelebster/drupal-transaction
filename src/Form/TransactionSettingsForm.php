<?php

namespace Drupal\transaction\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Transaction module settings form.
 */
class TransactionSettingsForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The route builder.
   *
   * @var \Drupal\Core\Routing\RouteBuilderInterface
   */
  protected $routeBuilder;

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Routing\RouteBuilderInterface $route_builder
   *   The route builder.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The translation manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RouteBuilderInterface $route_builder, TranslationInterface $string_translation) {
    $this->entityTypeManager = $entity_type_manager;
    $this->routeBuilder = $route_builder;
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('router.builder'),
      $container->get('string_translation')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'transaction_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'transaction.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    /** @var \Drupal\Core\Config\ImmutableConfig $config */
    $config = $this->config('transaction.settings');

    // Local task on target entity settings.
    $form['tabs'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Transaction tabs'),
      '#description' => $this->t('A local task (tab) will be added to the target entities of the selected transaction types with the transaction list of the entity.'),
      '#options' => [],
      '#default_value' => $config->get('tabs'),
    ];

    /** @var \Drupal\transaction\TransactionTypeInterface $transaction_type */
    foreach ($this->entityTypeManager->getStorage('transaction_type')->loadMultiple() as $transaction_type) {
      /** @var \Drupal\Core\Entity\EntityTypeInterface $target_entity_type */
      $target_entity_type = $this->entityTypeManager->getDefinition($transaction_type->getTargetEntityTypeId());

      // The transaction type checkbox entry.
      $option_id = $transaction_type->id() . '-' . $target_entity_type->id();
      $form['tabs']['#options'][$option_id] = $transaction_type->label();

      if (!empty($bundles = $transaction_type->getBundles())) {
        // Compose bundle labels.
        if ($target_bundle_id = $target_entity_type->getBundleEntityType()) {
          $target_bundle_storage = $this->entityTypeManager->getStorage($target_bundle_id);
          foreach ($bundles as $key => $bundle) {
            $bundles[$key] = $target_bundle_storage->load($bundle)->label();
          }
          asort($bundles);
        }
        $form['tabs'][$option_id]['#description'] = $this->formatPlural(count($bundles),
          'Will be added to %type of type %bundles.',
          'Will be added to %type of types %bundles.', [
            '%type' => $target_entity_type->getLabel(),
            '%bundles' => implode(', ', $bundles),
          ]
        );
      }
      else {
        $form['tabs'][$option_id]['#description'] = $this->t('Will be added to any %type entities.', ['%type' => $target_entity_type->getLabel()]);
      }
    }

    if (empty($form['tabs']['#options'])) {
      $form['tabs']['#field_suffix'] = $this->t('No transaction types available. <a href=":link">Add a transaction type</a>.', [
        ':link' => Url::fromRoute('transaction.transaction_type_creation')->toString(),
      ]);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('transaction.settings');
    $tabs = [];
    foreach ($form_state->getValue('tabs') as $tab) {
      if (!empty($tab) && is_string($tab)) {
        $tabs[] = $tab;
      }
    }

    $config->set('tabs', $tabs)->save();

    // A link template will be added to the target entity type definitions.
    $this->entityTypeManager->clearCachedDefinitions();
    // A route per transaction type and target entity will be added.
    $this->routeBuilder->rebuild();

    parent::submitForm($form, $form_state);
  }

}
