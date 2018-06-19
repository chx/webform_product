<?php

namespace Drupal\webform_product;

use Drupal\commerce_order\Entity\OrderItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionInterface;

class WebFormProductFormHelper {

  public static function processElementForm(&$element, FormStateInterface $form_state, &$complete_form) {
    $element_info = $form_state->getFormObject()->getElement();

    // Only change the form of price_* webform elements.
    if (strpos($element_info['#type'], 'price_', 0) === FALSE) {
      return $element;
    }

    $element['price'] = [
      '#type' => 'textfield',
      '#title' => t('Price'),
      '#placeholder' => '99.99',
      '#maxlength' => 20,
      '#default_value' => static::getSetting($form_state, 'top'),
      '#element_validate' => [[get_class(), 'saveTopPrice']],
    ];
    return $element;
  }

  public static function saveTopPrice($element, FormStateInterface $form_state) {
    static::setSetting($form_state, 'top', $element['#value']);
  }

  public static function getSetting(FormStateInterface $form_state, $settingKey) {
    $webform = self::getWebformInFormState($form_state);

    if ($webform) {
      $formObject = $form_state->getFormObject();
      $setting = $webform->getThirdPartySetting('webform_product', $formObject->getKey());
    }

    return isset($setting[$settingKey]) ? $setting[$settingKey] : NULL;
  }

  public static function setSetting(FormStateInterface $form_state, $settingKey, $value) {
    $webform = self::getWebformInFormState($form_state);

    if ($webform) {
      $formObject = $form_state->getFormObject();
      $elementKey = $formObject->getKey();
      $setting = $webform->getThirdPartySetting('webform_product', $elementKey);
      $setting[$settingKey] = $value;
      $webform->setThirdPartySetting('webform_product', $elementKey, $setting);
    }
  }

  public static function submissionToCart(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\webform\WebformSubmissionInterface $submission */
    $submission = $form_state->getFormObject()->getEntity();
    if (!$submission instanceof WebformSubmissionInterface) {
      return;
    }

    $webform = $submission->getWebform();
    if (!$prices = $webform->getThirdPartySettings('webform_product')) {
      return;
    }

    /** @var \Drupal\commerce_store\Entity\StoreInterface $store */
    $store = \Drupal::service('commerce_store.current_store')->getStore();
    $currencyCode = $store->getDefaultCurrency()->getCurrencyCode();
    /** @var \Drupal\commerce_cart\CartProviderInterface $cartProvider */
    $cartProvider = \Drupal::service('commerce_cart.cart_provider');
    /** @var \Drupal\commerce_order\Entity\OrderInterface $cartOrder */
    $cartOrder = $cartProvider->getCart('default', $store) ?: $cartProvider->createCart('default', $store);
    $elements = $webform->getElementsInitializedAndFlattened();
    $prices = $webform->getThirdPartySettings('webform_product');
    foreach ($submission->getData() as $key => $value) {
      if (isset($prices[$key])) {
        if (!empty($prices[$key]['top'])) {
          $orderItem = OrderItem::create([
            'type' => 'webform',
            'title' => $elements[$key]['#title'],
            'unit_price' => ['number' => $prices[$key]['top'], 'currency_code' => $currencyCode]
          ]);
          $orderItem->save();
          $cartOrder->addItem($orderItem);
        }
        if (!empty($prices[$key]['options'])) {
          // Fix for when value is not an array.
          if (!is_array($value)) {
            $value = [$value];
          }

          foreach (array_intersect($value, array_keys($prices[$key]['options'])) as $option) {
            $orderItem = OrderItem::create([
              'type' => 'webform',
              'title' => $elements[$key]['#options'][$option],
              'unit_price' => ['number' => $prices[$key]['options'][$option], 'currency_code' => $currencyCode]
            ]);
            $orderItem->save();
            $cartOrder->addItem($orderItem);
          }
        }
      }
    }
    $cartOrder->save();
    $form_state->setRedirect('commerce_cart.page');
  }

  private static function getWebformInFormState(FormStateInterface $form_state) {
    /** @var \Drupal\webform_ui\Form\WebformUiElementEditForm $formObject */
    $formObject = $form_state->getFormObject();

    $webformObject = method_exists($formObject, 'getWebform') ? $formObject->getWebform() : NULL;

    return ($webformObject instanceof WebformInterface) ? $webformObject : NULL;
  }
}
