<?php

namespace Drupal\webform_product;

use Drupal\commerce_order\Entity\OrderItem;
use Drupal\Core\Form\FormStateInterface;

class WebFormProductFormHelper {

  public static function processElementForm(&$element, FormStateInterface $form_state, &$complete_form) {
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
    /** @var \Drupal\webform_ui\Form\WebformUiElementEditForm $formObject */
    $formObject = $form_state->getFormObject();
    $setting = $formObject->getWebform()->getThirdPartySetting('webform_product', $formObject->getKey());
    return isset($setting[$settingKey]) ? $setting[$settingKey] : NULL;
  }

  public static function setSetting(FormStateInterface $form_state, $settingKey, $value) {
    /** @var \Drupal\webform_ui\Form\WebformUiElementEditForm $formObject */
    $formObject = $form_state->getFormObject();
    $webform = $formObject->getWebform();
    $elementKey = $formObject->getKey();
    $setting = $webform->getThirdPartySetting('webform_product', $elementKey);
    $setting[$settingKey] = $value;
    $webform->setThirdPartySetting('webform_product', $elementKey, $setting);
  }

  public static function submissionToCart(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\webform\WebformSubmissionInterface $submission */
    $submission = $form_state->getFormObject()->getEntity();
    $webform = $submission->getWebform();
    if (!$prices = $webform->getThirdPartySettings('webform_product')) {
      return;
    }
    $store = \Drupal::service('commerce_store.store_context')->getStore();
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
            'unit_price' => ['number' => $prices[$key]['top']]
          ]);
          $orderItem->save();
          $cartOrder->addItem($orderItem);
        }
        if (!empty($prices[$key]['options'])) {
          foreach (array_keys(array_intersect_key($value, $prices[$key]['options'])) as $option) {
            $orderItem = OrderItem::create([
              'type' => 'webform',
              'title' => $elements[$key]['#options'][$option],
              'unit_price' => ['number' => $prices[$key]['options'][$option]]
            ]);
            $orderItem->save();
            $cartOrder->addItem($orderItem);
          }
        }
      }
    }
    $form_state->setRedirect('commerce_cart.page');
  }

}

