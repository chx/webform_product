<?php

namespace Drupal\webform_product\Plugin\webform_product;

use Drupal\Component\Annotation\PluginID;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;
use Drupal\webform_product\WebFormProductFormHelper;

/**
 * @PluginID("webform_multiple")
 */
class WebformMultiple {

  public static function process(&$element, FormStateInterface $form_state, &$complete_form) {
    $element['#weight_index'] = array_search('weight', array_keys($element['items']['#header'])) + 1;
    array_splice($element['items']['#header'], $element['#weight_index'], 0, t('Product'));
    foreach (Element::children($element['items']) as $delta) {
      if (isset($element['items'][$delta]['value'])) {
        $element['items'][$delta]['text']['#process'][] = [get_class(), 'createUrl'];
        array_splice($element['items'][$delta], $element['#weight_index'], 0, [['#markup' => '&nbsp;']]);
      }
    }
    return $element;
  }

  public static function createUrl(&$element, FormStateInterface $form_state, &$complete_form) {
    $root = array_splice($element['#array_parents'], 0, -1);
    $optionValue = NestedArray::getValue($complete_form, array_merge($root, ['value', '#value']));
    if ($optionValue && !empty($element['#value'])) {
      /** @var \Drupal\webform_ui\Form\WebformUiElementFormInterface $formObject */
      $formObject = $form_state->getFormObject();
      $key = $formObject->getKey();
      $webformId = $formObject->getWebform()->id();
      $skuPrefix = "$webformId:$key:";
      if (isset($complete_form['#webform_product_links']["$skuPrefix$optionValue"])) {
        $link = $complete_form['#webform_product_links']["$skuPrefix$optionValue"];
      }
      else {
        $link = [
          '#type' => 'link',
          '#url' => WebFormProductFormHelper::getUrl($skuPrefix . $optionValue, $element['#value']),
          '#title' => t('Create product'),
        ];
      }
      NestedArray::setValue($complete_form, array_merge($root, [0]), $link);
    }
    return $element;
  }
}
