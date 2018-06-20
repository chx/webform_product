<?php

namespace Drupal\webform_product;

use Drupal\commerce_order\Entity\OrderItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class WebFormProductFormHelper {

  public static function processElementForm(&$element, FormStateInterface $form_state, &$complete_form) {
    $element_info = $form_state->getFormObject()->getElement();

    //@todo fix this when Price* webform elements are working.
//    // Only change the form of price_* webform elements.
//    if (strpos($element_info['#type'], 'price_', 0) === FALSE) {
//      return $element;
//    }

    $element['price'] = [
      '#type' => 'textfield',
      '#title' => t('Price'),
      '#placeholder' => '99.99',
      '#maxlength' => 20,
      '#default_value' => static::getSetting($form_state, 'top'),
      '#element_validate' => [[get_class(), 'saveTopPrice']],
      '#description' => t('Use this to add an extra order item to the order, this can be used as a supplement with the options or single without the prices of the options.'),
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

  private static function getWebformInFormState(FormStateInterface $form_state) {
    /** @var \Drupal\webform_ui\Form\WebformUiElementEditForm $formObject */
    $formObject = $form_state->getFormObject();

    $webformObject = method_exists($formObject, 'getWebform') ? $formObject->getWebform() : NULL;

    return ($webformObject instanceof WebformInterface) ? $webformObject : NULL;
  }

}
