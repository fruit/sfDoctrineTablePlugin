<?php

  /*
   * This file is part of the sfDoctrineTablePlugin package.
   * (c) 2012 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

if (!isset($_SERVER['SYMFONY']))
{
  throw new RuntimeException('Could not find symfony core libraries.');
}

include_once $_SERVER['SYMFONY'].'/autoload/sfCoreAutoload.class.php';
sfCoreAutoload::register();

$configuration = new sfProjectConfiguration(dirname(__FILE__).'/../fixtures/project');
include_once $configuration->getSymfonyLibDir().'/vendor/lime/lime.php';

function sfDoctrineTablePlugin_autoload_again($class)
{
  $autoload = sfSimpleAutoload::getInstance();
  $autoload->reload();
  return $autoload->autoload($class);
}
spl_autoload_register('sfDoctrineTablePlugin_autoload_again');

$config = "{$_SERVER['SYMFONY']}/plugins/sfDoctrinePlugin/config/sfDoctrinePluginConfiguration.class.php";

if (is_file($config))
{
  include_once $config;

  $sfDoctrinePlugin_configuration = new sfDoctrinePluginConfiguration(
    $configuration, dirname(__FILE__).'/../..', 'sfDoctrinePlugin'
  );
}

$config = dirname(__FILE__).'/../../config/sfDoctrineTablePluginConfiguration.class.php';

if (is_file($config))
{
  include_once $config;

  $plugin_configuration = new sfDoctrineTablePluginConfiguration(
    $configuration, dirname(__FILE__).'/../..', 'sfDoctrineTablePlugin'
  );
}
else
{
  $plugin_configuration = new sfPluginConfigurationGeneric($configuration, dirname(__FILE__).'/../..', 'sfDoctrineTablePlugin');
}
