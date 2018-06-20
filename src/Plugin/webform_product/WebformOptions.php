<?php

namespace Drupal\webform_product\Plugin\webform_product;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform_product\WebFormProductFormHelper;

/**
 * @PluginID("webform_options")
 */
class WebformOptions {

  public static function process(&$element, FormStateInterface $form_state) {

    //@todo fix this when Price* webform elements are working.
//    // Check for price_* elements, skip the check for Option definitions.
//    if (method_exists($form_state->getFormObject(), 'getElement')) {
//      $element_info = $form_state->getFormObject()->getElement();
//
//      // Only change the form of price_* webform elements.
//      if (strpos($element_info['#type'], 'price_', 0) === FALSE) {
//        return $element;
//      }
//    }
//    else {
//      return $element;
//    }

    $element['options']['#element']['price'] = [
      '#type' => 'textfield',
      '#title' => t('Price'),
      '#title_display' => 'invisible',
      '#placeholder' => '99.99',
      '#maxlength' => 20,
    ];
    if ($prices = WebFormProductFormHelper::getSetting($form_state, 'options')) {
      foreach ($element['options']['#default_value'] as $delta => $row) {
        if (isset($prices[$row['value']])) {
          $element['options']['#default_value'][$delta]['price'] = $prices[$row['value']];
        }
      }
    }

    // WebFormOptions::convertValuesToOptions() destroys our values so do
    // something about that.
    array_unshift($element['#element_validate'], [get_class(), 'convertToSettings']);
    return $element;
  }

  public static function convertToSettings($element, FormStateInterface $form_state) {
    foreach ($element['options']['#value'] as $value) {
      if (!empty($value['price'])) {
        $option_value = $value['value'];
        $option_text = $value['text'];

        // Populate empty option value or option text.
        if ($option_value === '') {
          $option_value = $option_text;
        }
        $prices[$option_value] = $value['price'];
      }
      if (!empty($prices)) {
        WebFormProductFormHelper::setSetting($form_state, 'options', $prices);
      }
    }
  }

}
