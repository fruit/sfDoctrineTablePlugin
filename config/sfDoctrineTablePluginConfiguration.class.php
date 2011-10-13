<?php

  /*
   * This file is part of the sfDoctrineTablePlugin package.
   * (c) 2011 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  /**
   * sfDoctrineTablePlugin configuration.
   *
   * @package     sfDoctrineTablePlugin
   * @subpackage  config
   * @author      Ilya Sabelnikov <fruit.dev@gmail.com>
   */
  class sfDoctrineTablePluginConfiguration extends sfPluginConfiguration
  {
    /**
     * @see sfPluginConfiguration
     */
    public function initialize ()
    {
      $manager = Doctrine_Manager::getInstance();

      $manager->setAttribute(
        Doctrine_Core::ATTR_TABLE_CLASS,
        sfConfig::get('app_sf_doctrine_table_plugin_custom_table_class')
      );
    }
  }
