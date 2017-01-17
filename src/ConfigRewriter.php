<?php

namespace Drupal\config_rewrite;

use Drupal\Component\Utility\NestedArray;
use Drupal\config_rewrite\ConfigRewriterInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Provides methods to rewrite configuration.
 */
class ConfigRewriter implements ConfigRewriterInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * A logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs a new ConfigRewriter.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   A logger channel.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ModuleHandlerInterface $module_handler, LoggerChannelInterface $logger) {
    $this->configFactory = $config_factory;
    $this->moduleHandler = $module_handler;
    $this->logger = $logger;
  }

  /**
   * Rewrites configuration for a given module.
   *
   * @param $module
   *   The name of a module (without the .module extension).
   */
  public function rewriteModuleConfig($module) {
    // Load the module extension.
    $extension = $this->moduleHandler->getModule($module);

    // Config rewrites are stored in 'modulename/config/rewrite'.
    $rewrite_dir = $extension->getPath() . DIRECTORY_SEPARATOR . ConfigRewriterInterface::CONFIG_REWRITE_DIRECTORY;

    // Scan the rewrite directory for rewrites.
    if (file_exists($rewrite_dir) && $files = $this->fileScanDirectory($rewrite_dir, '/^.*\.yml$/i')) {
      foreach ($files as $file) {
        // Parse the rewrites and retrieve the original config.
        $rewrite = Yaml::parse(file_get_contents($rewrite_dir . DIRECTORY_SEPARATOR . $file->name . '.yml'));
        $original_config = $this->configFactory->getEditable($file->name);

        // Rewrite and save the config.
        $rewrite = $this->rewriteConfig($original_config->getRawData(), $rewrite);

        // Unset 'config_rewrite' key before saving rewritten values.
        if (isset($rewrite['config_rewrite'])) {
          unset($rewrite['config_rewrite']);
        }

        // Save the rewritten configuration data.
        $result = $original_config->setData($rewrite)->save() ? 'rewritten' : 'not rewritten';

        // Log a message indicating whether the config was rewritten or not.
        $this->logger->notice('@config @result by @module.', ['@config' => $file->name, '@result' => $result, '@module' => $extension->getName()]);
      }
    }
  }

  /**
   * Returns rewritten configuration.
   *
   * @param array $original_config
   *   The original configuration array to rewrite.
   * @param array $rewrite
   *   An array of configuration rewrites.
   *
   * @return array
   *   The rewritten config.
   */
  public function rewriteConfig($original_config, $rewrite) {
    if (isset($rewrite['config_rewrite']) && $rewrite['config_rewrite'] == 'replace') {
      return $rewrite;
    }
    return NestedArray::mergeDeep($original_config, $rewrite);
  }

  /**
   * Wraps file_scan_directory().
   *
   * @param $dir
   *   The base directory or URI to scan, without trailing slash.
   * @param $mask
   *   The preg_match() regular expression for files to be included.
   * @param $options
   *   An associative array of additional options.
   *
   * @return
   *   An associative array (keyed on the chosen key) of objects with 'uri',
   *   'filename', and 'name' properties corresponding to the matched files.
   */
  protected function fileScanDirectory($dir, $mask, $options = array()) {
    return file_scan_directory($dir, $mask, $options);
  }

}
