<?php

namespace Drupal\webform_product\Plugin\WebformHandler;

use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\profile\Entity\Profile;
use Drupal\token\Token;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformException;
use Drupal\webform\WebformSubmissionConditionsValidatorInterface;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\WebformTokenManagerInterface;
use Drupal\webform_product\Controller\WebformProductController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Webform submission Commerce Product handler.
 *
 * @WebformHandler(
 *   id = "webform_product",
 *   label = @Translation("Webform to Commerce Product"),
 *   category = @Translation("Commerce"),
 *   description = @Translation("Save submission as a product."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_REQUIRED,
 * )
 */
class WebformProductWebformHandler extends WebformHandlerBase {

  use MessengerTrait;

  // Commerce values.
  const COMMERCE_STORE = 'store';
  const COMMERCE_ORDER_TYPE = 'order_type';
  const COMMERCE_ORDER_ITEM_TYPE = 'order_item_type';
  const COMMERCE_ORDER_ITEM_TITLE = 'order_item_title';
  const COMMERCE_CHECKOUT_STEP = 'checkout_step';
  const COMMERCE_GATEWAY = 'payment_gateway';
  const COMMERCE_METHOD = 'payment_method';

  // Commerce order data.
  const ORDER_PRICE = 'payment_price';

  // Mapped field names.
  const FIELD_STATUS = 'field_payment_status';
  const FIELD_ORDER_ID = 'field_order_id';
  const FIELD_ORDER_URL = 'field_order_url';
  const FIELD_TOTAL_PRICE = 'field_total_price';
  const FIELD_LINK_ORDER_ORIGIN = 'field_link_order_origin';

  // Default values.
  const DEFAULT_ORDER_TYPE = 'webform';
  const DEFAULT_ORDER_ITEM_TYPE = 'webform';
  const DEFAULT_CHECKOUT_STEP = 'payment';
  const DEFAULT_ORDER_ITEM_TITLE = '[webform_submission:source-entity:title]';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The token service.
   *
   * @var \Drupal\token\Token
   */
  protected $token;


  /**
   * The token manager.
   *
   * @var \Drupal\webform\WebformTokenManagerInterface
   */
  protected $tokenManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerChannelFactoryInterface $logger_factory, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, WebformSubmissionConditionsValidatorInterface $conditions_validator, Token $token, WebformTokenManagerInterface $token_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger_factory, $config_factory, $entity_type_manager, $conditions_validator);
    $this->entityTypeManager = $entity_type_manager;
    $this->token = $token;
    $this->tokenManager = $token_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory'),
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('webform_submission.conditions_validator'),
      $container->get('token'),
      $container->get('webform.token_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      self::COMMERCE_STORE => NULL,
      self::COMMERCE_ORDER_TYPE => self::DEFAULT_ORDER_TYPE,
      self::COMMERCE_ORDER_ITEM_TITLE => self::DEFAULT_ORDER_ITEM_TITLE,
      self::COMMERCE_ORDER_ITEM_TYPE => self::DEFAULT_ORDER_ITEM_TYPE,
      'route' => 'commerce_checkout.form',
      self::COMMERCE_CHECKOUT_STEP => self::DEFAULT_CHECKOUT_STEP,
      self::COMMERCE_GATEWAY => NULL,
      self::COMMERCE_METHOD => NULL,
      self::ORDER_PRICE => NULL,
      self::FIELD_STATUS => NULL,
      self::FIELD_ORDER_ID => NULL,
      self::FIELD_ORDER_URL => NULL,
      self::FIELD_TOTAL_PRICE => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @todo Create debug mode setting.
   * @todo Create field mapping for Billing information (name, address & mail).
   * @todo Create more route choices.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $configuration = $this->getConfiguration();
    $settings = $configuration['settings'];

    $form['commerce'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Commerce'),
    ];
    $form['commerce'][self::COMMERCE_STORE] = [
      '#type' => 'select',
      '#title' => $this->t('Store'),
      '#options' => $this->getEntityOptions('commerce_store'),
      '#default_value' => $settings[self::COMMERCE_STORE],
      '#required' => TRUE,
    ];
    $form['commerce'][self::COMMERCE_ORDER_TYPE] = [
      '#type' => 'select',
      '#title' => $this->t('Order type'),
      '#options' => $this->getEntityOptions('commerce_order_type'),
      '#default_value' => $settings[self::COMMERCE_ORDER_TYPE],
      '#required' => TRUE,
    ];
    $form['commerce'][self::COMMERCE_ORDER_ITEM_TYPE] = [
      '#type' => 'select',
      '#title' => $this->t('Order item type'),
      '#options' => $this->getEntityOptions('commerce_order_item_type'),
      '#default_value' => $settings[self::COMMERCE_ORDER_ITEM_TYPE],
      '#required' => TRUE,
    ];
    $form['commerce'][self::COMMERCE_ORDER_ITEM_TITLE] = [
      '#type' => 'textfield',
      '#title' => $this->t('Order item title'),
      '#description' => $this->t('Default %default.', ['%default' => self::DEFAULT_ORDER_ITEM_TITLE]),
      '#default_value' => $settings[self::COMMERCE_ORDER_ITEM_TITLE],
      '#required' => TRUE,
    ];
    $form['commerce'][self::COMMERCE_GATEWAY] = [
      '#type' => 'select',
      '#title' => $this->t('Payment provider'),
      '#options' => $this->getEntityOptions('commerce_payment_gateway', [
        'status' => TRUE,
      ]),
      '#default_value' => $settings[self::COMMERCE_GATEWAY],
      '#required' => TRUE,
    ];

    $token_types = ['webform', 'webform_submission'];
    // Show webform role tokens if they have been specified.
    if (!empty($roles_element_options)) {
      $token_types[] = 'webform_role';
    }
    $form['commerce']['token_tree_link'] = $this->tokenManager->buildTreeLink(
      $token_types,
      $this->t('Use [webform_submission:values:ELEMENT_KEY:raw] to get plain text values.')
    );

    $form['order_data'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Data to create order'),
    ];
    $form['order_data']['info'] = [
      '#markup' => '<p>' . $this->t('Use this price field as the price of a single order item. Leave it empty to use individual webform elements with a Price field, where one order item is created per form element.') . '</p>',
    ];

    $field_types = ['number', 'numeric', 'textfield', 'webform_computed_twig'];
    $form['order_data'][self::ORDER_PRICE] = [
      '#type' => 'select',
      '#title' => $this->t('Total price'),
      '#options' => $this->getElementsSelectOptions($field_types),
      '#default_value' => $settings[self::ORDER_PRICE],
      '#empty_value' => '',
      '#required' => FALSE,
      '#description' => $this->t('Field types allowed: @types.', ['@types' => implode(', ', $field_types)]),
    ];

    $form['field_mapping'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Field mapping'),
    ];

    $field_types = ['textfield'];
    $form['field_mapping'][self::FIELD_STATUS] = [
      '#type' => 'select',
      '#title' => $this->t('Payment status'),
      '#options' => $this->getElementsSelectOptions($field_types),
      '#default_value' => $settings[self::FIELD_STATUS],
      '#empty_value' => '',
      '#required' => TRUE,
      '#description' => $this->t('Field types allowed: @types.', ['@types' => implode(', ', $field_types)]),
    ];

    $field_types = ['number', 'numeric', 'textfield'];
    $form['field_mapping'][self::FIELD_ORDER_ID] = [
      '#type' => 'select',
      '#title' => $this->t('Order ID'),
      '#options' => $this->getElementsSelectOptions($field_types),
      '#default_value' => $settings[self::FIELD_ORDER_ID],
      '#empty_value' => '',
      '#required' => TRUE,
      '#description' => $this->t('Field types allowed: @types.', ['@types' => implode(', ', $field_types)]),
    ];

    $field_types = ['url'];
    $form['field_mapping'][self::FIELD_ORDER_URL] = [
      '#type' => 'select',
      '#title' => $this->t('Order URL'),
      '#options' => $this->getElementsSelectOptions($field_types),
      '#default_value' => $settings[self::FIELD_ORDER_URL],
      '#empty_value' => '',
      '#required' => TRUE,
      '#description' => $this->t('Field types allowed: @types.', ['@types' => implode(', ', $field_types)]),
    ];

    $field_types = ['number', 'numeric', 'textfield'];
    $form['field_mapping'][self::FIELD_TOTAL_PRICE] = [
      '#type' => 'select',
      '#title' => $this->t('Total price'),
      '#options' => $this->getElementsSelectOptions($field_types),
      '#default_value' => $settings[self::FIELD_TOTAL_PRICE],
      '#empty_value' => '',
      '#required' => FALSE,
      '#description' => $this->t('Field types allowed: @types.', ['@types' => implode(', ', $field_types)]) . '<br />' . $this->t('Use this if you want to safe the total order amount to a specific field.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @todo Set mapped webform order and payment field permissions to 'view-only'.
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    parent::applyFormStateToConfiguration($form_state);

    $values = $form_state->getValues();

    foreach ($values['commerce'] as $key => $value) {
      $this->configuration[$key] = $value;
    }

    foreach ($values['order_data'] as $key => $value) {
      $this->configuration[$key] = $value;
    }

    foreach ($values['order_result'] as $key => $value) {
      $this->configuration[$key] = $value;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    if ($update == TRUE) {
      return;
    }

    try {
      $orderItems = $this->getOrderItems($webform_submission);
      if (empty($orderItems)) {
        return;
      }

      /** @var \Drupal\commerce_cart\CartProviderInterface $cartProvider */
      $cartProvider = \Drupal::service('commerce_cart.cart_provider');
      /** @var \Drupal\commerce_order\Entity\OrderInterface $cartOrder */
      $cartOrder = $this->getCart($cartProvider, $this->getStore(), TRUE);

      // Fill the Cart.
      foreach ($orderItems as $orderItem) {
        $orderItem->save();
        $cartOrder->addItem($orderItem);
      }
      $cartOrder->save();
      $cartOrder = $this->entityTypeManager->getStorage('commerce_order')->load($cartOrder->id());

      // Save the Cart (Order) with Submission data.
      $this->setOrderCheckoutProcess($cartOrder);
      $this->setOrderLinkReference($cartOrder, $webform_submission);
      $this->setOrderCustomer($cartOrder);

      // Save the submission with Cart data.
      $this->setSubmissionTotalPrice($webform_submission, $cartOrder);
      WebformProductController::setSubmissionOrderStatus($webform_submission, WebformProductController::PAYMENT_STATUS_INITIALIZED);
      $this->setSubmissionOrderReference($webform_submission, $cartOrder);
      $webform_submission->set('in_draft', TRUE);
      $webform_submission->resave();

      // Protect order from adding new products.
      $cartOrder->lock();

      $cartOrder->save();

      // Reload the order.
      $cartOrder = $this->entityTypeManager->getStorage('commerce_order')->load($cartOrder->id());

      $this->redirectToCheckout($cartOrder);
    }
    catch (\Exception $e) {
      $this->loggerFactory->get($this->pluginId)->error($e->getMessage());
    }
  }

  /**
   * Get option list of Entities.
   *
   * @param string $entity_type
   *   The entity type to load.
   * @param array $properties
   *   The loaded entity condtions.
   *
   * @return array
   *   List with ids as key and label as value.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function getEntityOptions($entity_type, array $properties = []) {
    $options = [];

    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface[] $payment_gateways */
    $entities = $this->entityTypeManager
      ->getStorage($entity_type)
      ->loadByProperties($properties);

    foreach ($entities as $entity) {
      $options[$entity->id()] = $entity->label();
    }

    return $options;
  }

  /**
   * Gather all Order Items from the webform Submission.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webformSubmission
   *   The webform submission.
   *
   * @return \Drupal\commerce_order\Entity\OrderItemInterface[]
   *   List of Order Items.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function getOrderItems(WebformSubmissionInterface $webformSubmission) {
    $price_fields = $this->getWebform()->getThirdPartySettings($this->pluginId);

    // No prices, no Order.
    if (!$price_fields) {
      return [];
    }

    $payment_status = $this->getSavedPaymentStatus($webformSubmission);

    // Create only an order for new webform submissions.
    if ($payment_status != WebformProductController::PAYMENT_STATUS_NULL) {
      return [];
    }

    /** @var \Drupal\commerce_store\Entity\StoreInterface $store */
    $store = $this->getStore();
    $currencyCode = $store->getDefaultCurrency()->getCurrencyCode();

    $orderItems = [];
    $configuration = $this->getConfiguration();
    $settings = $configuration['settings'];

    // @todo Make this also available for multiple elements.
    $order_item_title = $this->tokenManager->replace($settings[self::COMMERCE_ORDER_ITEM_TITLE], $webformSubmission, [
      'webform' => $this->getWebform(),
    ]);

    if ($this->useElementBasedOrder()) {
      // Create Order Item for each:
      // - element option with a price.
      // - element with a top price.
      foreach ($webformSubmission->getData() as $key => $value) {
        if (empty($price_fields[$key])) {
          continue;
        }

        // Element with 'top'.
        if (!empty($price_fields[$key]['top'])) {
          $orderItems[] = OrderItem::create([
            'type' => $this->configuration[self::COMMERCE_ORDER_ITEM_TYPE],
            'title' => $order_item_title,
            'quantity' => 1,
            'unit_price' => [
              'number' => $price_fields[$key]['top'],
              'currency_code' => $currencyCode,
            ],
          ]);
        }

        if (!empty($price_fields[$key]['options'])) {
          // Fix for when value is not an array.
          if (!is_array($value)) {
            $value_to_validate = [$value];
          }
          else {
            $value_to_validate = $value;
          }

          $options = array_keys($price_fields[$key]['options']);
          $price_options = array_intersect($value_to_validate, $options);
          $has_other = $this->getWebform()->getElement($key);

          // Other values.
          if (isset($has_other['#other_type']) && $has_other['#other__type'] == 'number' && empty($price_options)) {
            $orderItems[] = OrderItem::create([
              'type' => $this->configuration[self::COMMERCE_ORDER_ITEM_TYPE],
              'title' => $order_item_title,
              'quantity' => 1,
              'unit_price' => [
                'number' => $value,
                'currency_code' => $currencyCode,
              ],
            ]);
          }
          else {
            // Option elements with price as option (checkboxes or radios).
            foreach ($price_options as $option) {
              $orderItems[] = OrderItem::create([
                'type' => $this->configuration[self::COMMERCE_ORDER_ITEM_TYPE],
                'title' => $order_item_title,
                'quantity' => 1,
                'unit_price' => [
                  'number' => $price_fields[$key]['options'][$option],
                  'currency_code' => $currencyCode,
                ],
              ]);
            }
          }
        }
      }
    }
    else {
      $orderItems = [];
      $price = $this->formatPrice($webformSubmission->getElementData($settings[self::ORDER_PRICE]));
      if ($price > 0) {
        $orderItems[] = OrderItem::create([
          'type' => $this->configuration[self::COMMERCE_ORDER_ITEM_TYPE],
          'title' => $order_item_title,
          'quantity' => 1,
          'unit_price' => [
            'number' => $price,
            'currency_code' => $currencyCode,
          ],
        ]);
      }
    }

    return $orderItems;
  }

  /**
   * Determine if Element Based order must be used.
   *
   * The commerce order is either created with one order item per priced field
   * or with one item based on a single field value. The latter is usually a
   * calculated value or the result of a (if/else) condition.
   *
   * @return bool
   *   Returns true if element based orders are used.
   */
  private function useElementBasedOrder() {
    return empty($this->configuration[self::ORDER_PRICE]);
  }

  /**
   * Get webform elements selectors as options.
   *
   * @param array $types
   *   List of types to filter.
   *   - Leave empty skip filtering of types.
   *
   * @see \Drupal\webform\Entity\Webform::getElementsSelectorOptions()
   *
   * @return array
   *   Webform elements selectors as options.
   */
  private function getElementsSelectOptions(array $types = []) {
    $options = [];
    $elements = $this->getWebform()->getElementsInitializedAndFlattened();
    foreach ($elements as $key => $element) {
      // Skip element if not in given 'types' array.
      if ($types && !in_array($element['#type'], $types)) {
        continue;
      }

      $options[$key] = $element['#title'];
    }
    return $options;
  }

  /**
   * Get the payment status of the submission.
   *
   * - Nothing if there isn't any payment at all.
   * - Initilized for started, but not completed payments.
   * - Canceled for payments canceled by the user.
   * - Exception for payments canceled by the provider.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webformSubmission
   *   The webform submission.
   *
   * @return string
   *   The status of the payment.
   *
   * @see \Drupal\webform_product\Plugin\WebformHandler\WebformProductWebformHandler::PAYMENT_STATUS_NULL;
   * @see \Drupal\webform_product\Plugin\WebformHandler\WebformProductWebformHandler::PAYMENT_STATUS_INITIALIZED;\
   * @see \Drupal\webform_product\Plugin\WebformHandler\WebformProductWebformHandler::PAYMENT_STATUS_CANCELED;
   * @see \Drupal\webform_product\Plugin\WebformHandler\WebformProductWebformHandler::PAYMENT_STATUS_COMPLETED;
   * @see \Drupal\webform_product\Plugin\WebformHandler\WebformProductWebformHandler::PAYMENT_STATUS_EXCEPTION;
   */
  private function getSavedPaymentStatus(WebformSubmissionInterface $webformSubmission) {
    $value = $webformSubmission->getElementData($this->configuration[self::FIELD_STATUS]);

    return $value;
  }

  /**
   * Set total price of the Order in the Submission.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webformSubmission
   *   The webform Submission.
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The Order.
   */
  protected function setSubmissionTotalPrice(WebformSubmissionInterface $webformSubmission, OrderInterface $order) {
    // Save Total price of order.
    if ($this->configuration[self::FIELD_TOTAL_PRICE]) {
      $total = $order->getTotalPrice()->getNumber();
      $webformSubmission->setElementData($this->configuration[self::FIELD_TOTAL_PRICE], $total);
    }
  }

  /**
   * Set the Order reference in the webform Submission.
   *
   * @param \Drupal\webform\WebformSubmissionInterface $webformSubmission
   *   The webform Submission.
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The Order.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function setSubmissionOrderReference(WebformSubmissionInterface $webformSubmission, OrderInterface $order) {
    // Save order id to the webform for back reference.
    if ($this->configuration[self::FIELD_ORDER_ID]) {
      $webformSubmission
        ->setElementData($this->configuration[self::FIELD_ORDER_ID], $order->id());
    }
    if ($this->configuration[self::FIELD_ORDER_URL]) {
      $order_url = $order->toUrl()->toString();
      $webformSubmission
        ->setElementData($this->configuration[self::FIELD_ORDER_URL], $order_url);
    }
  }

  /**
   * Redirect to the configured checkout step in the Checkout Flow.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The Order.
   */
  protected function redirectToCheckout(OrderInterface $order) {
    // Redirect to checkout process.
    $response = new RedirectResponse(Url::fromRoute($this->configuration['route'], [
      'commerce_order' => $order->id(),
      'step' => $this->configuration[self::COMMERCE_CHECKOUT_STEP],
    ])->toString());

    $request = \Drupal::request();
    // Save the session.
    $request->getSession()->save();
    $response->prepare($request);
    // Trigger kernel events.
    \Drupal::service('kernel')->terminate($request, $response);

    $response->send();
    exit();
  }

  /**
   * Set Customer data for Order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The Order.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *
   * @todo Create full commerce profile for order with address and mail info.
   */
  protected function setOrderCustomer(OrderInterface $order) {
    $billing_profile = Profile::create([
      'uid' => 0,
      'type' => 'customer',
    ]);
    $billing_profile->save();

    // Add profile information.
    $order->setBillingProfile($billing_profile);
  }

  /**
   * Save back reference to the webform as link.
   *
   * Order info can't be referenced, if the referenced entity doesn't have the
   * same lifespan.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The Order.
   * @param \Drupal\webform\WebformSubmissionInterface $webformSubmission
   *   The webform submission.
   *
   * @todo Make field FIELD_LINK_ORDER_ORIGIN configurable.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function setOrderLinkReference(OrderInterface $order, WebformSubmissionInterface $webformSubmission) {
    if ($order->hasField(self::FIELD_LINK_ORDER_ORIGIN)) {
      $uri = $webformSubmission->toUrl()->toUriString();
      $order->set(self::FIELD_LINK_ORDER_ORIGIN, $uri);
    }
  }

  /**
   * Set Checkout Process variables for Order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The Order.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function setOrderCheckoutProcess(OrderInterface $order) {
    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $payment_gateway */
    $payment_gateway = $this->entityTypeManager->getStorage('commerce_payment_gateway')->load($this->configuration[self::COMMERCE_GATEWAY]);

    if (!$payment_gateway) {
      $this->loggerFactory->get($this->pluginId)->error(t('Failed to get a Payment Gateway'));
      return;
    }

    $payment_method = empty($this->configuration[self::COMMERCE_METHOD]) ? NULL : $this->configuration[self::COMMERCE_METHOD];

    // Save additional info to the order to speedup the checkout progress.
    $order
      ->set(self::COMMERCE_CHECKOUT_STEP, $this->configuration[self::COMMERCE_CHECKOUT_STEP])
      ->set(self::COMMERCE_GATEWAY, $payment_gateway->id())
      ->set(self::COMMERCE_METHOD, $payment_method);
  }

  /**
   * Get a Cart (Order) for the current user.
   *
   * Can be a new or existing cart.
   *
   * @param \Drupal\commerce_cart\CartProviderInterface $cartProvider
   *   The Cart Provider.
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   The Store.
   * @param bool $remove_existing_items
   *   Flag to remove existing items from the Cart.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface|null
   *   Cart of current user.
   */
  protected function getCart(CartProviderInterface $cartProvider, StoreInterface $store, $remove_existing_items = TRUE) {
    $order_type = $this->configuration[self::COMMERCE_ORDER_TYPE];

    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $cartProvider->getCart($order_type, $store) ?: $cartProvider->createCart($order_type, $store);

    if (!$order) {
      $this->loggerFactory->get($this->pluginId)->error(t('Failed to get a Cart Order'));
      return NULL;
    }

    if ($remove_existing_items && $order->hasItems()) {
      foreach ($order->getItems() as $item) {
        $order->removeItem($item);
      }
    }

    return $order;
  }

  /**
   * Get the selected store.
   *
   * @return \Drupal\commerce_store\Entity\StoreInterface
   *   The Store.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function getStore() {
    /** @var \Drupal\commerce_store\Entity\StoreInterface $store */
    $store = $this->entityTypeManager->getStorage('commerce_store')
      ->load($this->configuration[self::COMMERCE_STORE]);

    if (!$store) {
      $this->loggerFactory->get($this->pluginId)->error(t('Failed to get a Store'));
      return NULL;
    }

    return $store;
  }

  /**
   * Format the price value.
   *
   * We allow various field types as price input. This converts them to a float
   * value.
   *
   * @param mixed $value
   *   Raw price value.
   *
   * @return float
   *   Converted value.
   */
  private function formatPrice($value) {
    // Convert Computed Twig.
    if ($value instanceof MarkupInterface) {
      $value = (string) $value;
      $value = preg_replace('/[\n\r\t]/', '', $value);
    }
    // Convert text.
    $value = (string) $value;
    $value = trim($value);
    $value = str_replace(',', '.', str_replace('.', '', $value));
    $value = empty($value) ? '0' : $value;
    if (!is_numeric($value)) {
      throw new WebformException($this->t('Can not make price from %value.', ['%value' => $value]));
    }

    return $value;
  }

}
