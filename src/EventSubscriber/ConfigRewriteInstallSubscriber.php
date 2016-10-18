<?php

namespace Drupal\config_rewrite\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Yaml\Yaml;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Extension\ModuleEvents;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

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
   * ConfigRewriteInstallSubscriber constructor.
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   */
  public function __construct(ModuleHandler $module_handler, ConfigFactory $config_factory, LoggerChannelFactoryInterface $logger_factory) {
    $this->moduleHandler = $module_handler;
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger_factory;
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
    $module = $this->moduleHandler->getModule($event->getModule());
    $rewrite_dir = $module->getPath() . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'rewrite';
    if (file_exists($rewrite_dir) && $files = file_scan_directory($rewrite_dir, '/^.*\.yml$/i')) {
      foreach ($files as $file) {
        $rewrite_file = file_get_contents($rewrite_dir . DIRECTORY_SEPARATOR . $file->name . '.yml');
        $config_rewrite = Yaml::parse($rewrite_file);
        $config = $this->configFactory->getEditable($file->name);
        $rewrite = NestedArray::mergeDeep($config->getRawData(), $config_rewrite);
        $config->setData($rewrite)->save();
        $msg = t('@config rewritten by @module', ['@config' => $file->name, '@module' => $module->getName()]);
        $this->loggerFactory->get('config_rewrite')->notice($msg);
      }
    }
  }

}
