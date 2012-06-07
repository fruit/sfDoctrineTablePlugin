<?php

  if ( ! isset($_SERVER['SYMFONY']))
  {
    throw new RuntimeException('Could not find symfony core libraries.');
  }

  require_once $_SERVER['SYMFONY'] . '/autoload/sfCoreAutoload.class.php';
  sfCoreAutoload::register();

  class ProjectConfiguration extends sfProjectConfiguration
  {

    public function setup ()
    {
      sfConfig::set('sf_test_dir', dirname(__FILE__) . '/../../../../test');

      $this->setPluginPath('sfDoctrineTablePlugin', dirname(__FILE__) . '/../../../..');

      $this->enablePlugins(array(
        'sfDoctrinePlugin',
        'sfDoctrineTablePlugin',
        'sfDoctrineGuardPlugin',
      ));
    }

    public function configureDoctrine (Doctrine_Manager $manager)
    {
//      $manager->setAttribute(Doctrine_Core::ATTR_TABLE_CLASS, 'Doctrine_Table_Example');
//      $manager->setAttribute(Doctrine_Core::ATTR_TABLE_CLASS, 'Doctrine_Table_Scoped');
    }
  }
