<?php

namespace Drupal\webform_product;

use Drupal\Component\Annotation\PluginID;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Class WebformProductPluginManager.
 *
 * @package Drupal\webform_product
 */
class WebformProductPluginManager extends DefaultPluginManager {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cacheBackend, ModuleHandlerInterface $module_handler, $plugin_interface = NULL, $plugin_definition_annotation_name = 'Drupal\Component\Annotation\Plugin', $additional_annotation_namespaces = []) {
    parent::__construct('Plugin/webform_product', $namespaces, $module_handler, NULL, PluginID::class);
    $this->setCacheBackend($cacheBackend, 'webform_product');
  }

}
