<?php

namespace Drupal\config_rewrite\EventSubscriber;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\DrupalKernelInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ModuleEvents;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Component\Utility\NestedArray;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Class ConfigRewriteInstallSubscriber.
 *
 * @package Drupal\config_rewrite
 */
class ConfigRewriteInstallSubscriber implements EventSubscriberInterface {

  /**
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * @var \Drupal\Core\DrupalKernelInterface
   */
  protected $kernel;

  /**
   * ConfigRewriteInstallSubscriber constructor.
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   * @param \Drupal\Core\DrupalKernelInterface $kernel
   */
  public function __construct(ModuleHandler $module_handler, ConfigFactory $config_factory, LoggerChannelFactoryInterface $logger_factory, DrupalKernelInterface $kernel) {
    $this->moduleHandler = $module_handler;
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger_factory;
    $this->kernel = $kernel;
  }

  /**
   * {@inheritdoc}
   */
  static function getSubscribedEvents() {
    $events[ModuleEvents::MODULE_INSTALLED][] = ['module_install_config_rewrite'];
    return $events;
  }

  /**
   * @param \Symfony\Component\EventDispatcher\Event $event
   */
  public function module_install_config_rewrite(Event $event) {
    // Check that the module is enabled.
    $module = $event->getModule();
    if ($this->moduleExists($module)) {
      $this->rewriteModuleConfig($this->moduleHandler->getModule($module));
    }
  }

  /**
   * @param $module
   * @return bool
   */
  public function moduleExists($module) {
    if (!$this->moduleHandler->moduleExists($module)) {
      $container = $this->kernel->getContainer();
      $this->moduleHandler = $container->get('module_handler');
    }
    return $this->moduleHandler->moduleExists($module);
  }

  /**
   * @param \Drupal\Core\Extension\Extension $module
   */
  public function rewriteModuleConfig(Extension $module) {
    $rewrite_dir = $module->getPath() . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'rewrite';
    if (file_exists($rewrite_dir) && $files = file_scan_directory($rewrite_dir, '/^.*\.yml$/i')) {
      foreach ($files as $file) {
        $rewrite = Yaml::parse(file_get_contents($rewrite_dir . DIRECTORY_SEPARATOR . $file->name . '.yml'));
        $config = $this->configFactory->getEditable($file->name);
        $rewrite = $this->rewriteConfig($config->getRawData(), $rewrite);
        $result = ($config->setData($rewrite)->save() ? 'rewritten' : 'not rewritten');
        $replace = ['@config' => $file->name, '@result' => $result, '@module' => $module->getName()];
        $message = t('@config @result by @module', $replace);
        $this->loggerFactory->get('config_rewrite')->info($message);
      }
    }
  }

  /**
   * @param array $config
   * @param array $rewrite
   * @return array
   */
  public function rewriteConfig(Array $config, Array $rewrite) {
    if (isset($rewrite['config_rewrite']) && $rewrite['config_rewrite'] == 'replace') {
      return $rewrite;
    }
    return NestedArray::mergeDeep($config, $rewrite);
  }

}
