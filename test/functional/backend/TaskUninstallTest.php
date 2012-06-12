<?php

  /*
   * This file is part of the sfDoctrineTablePlugin package.
   * (c) 2011 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  include_once realpath(dirname(__FILE__) . '/../../bootstrap/functional.php');
  include_once sfConfig::get('sf_symfony_lib_dir') . '/vendor/lime/lime.php';

  $t = new lime_test();

  $libDir = __DIR__ . '/../../fixtures/project/lib/model/doctrine';


  // Clean existing generated classes models & tables
  // do not use $this->dispatcher|$this->formatter - this will print out task output
  $task = new sfDoctrineBuildTableTask(new sfEventDispatcher(), new sfFormatter());


  $t->diag('Executing: ./symfony doctrine:build-table --uninstall --env=test --no-confirmation');
  $returnCode = $task->run(array(), array('uninstall' => true, 'env' => 'test', 'no-confirmation' => true));

  if (sfDoctrineBuildTableTask::RETURN_SUCCESS != $returnCode)
  {
    $t->fail(sprintf("Failed to run task. Return code is %s", $returnCode));
    return;
  }


  $t->diag('Checking models');

  $tests = array(
    "{$libDir}/sfDoctrineGuardPlugin" => array(
      'sfGuardGroup'            => 'PluginsfGuardGroupTable',
      'sfGuardGroupPermission'  => 'PluginsfGuardGroupPermissionTable',
      'sfGuardUserGroup'        => 'PluginsfGuardUserGroupTable',
      'sfGuardUser'             => 'PluginsfGuardUserTable',
    ),
    $libDir => array(
      'Post'            => 'Doctrine_Table_Example',
      'Section'         => 'Doctrine_Table_Example',
      'Culture'         => 'Doctrine_Table_Example',
      'PostMedia'       => 'Doctrine_Table_Example',
      'PostMediaImage'  => 'PostMediaTable',
    ),
  );

  foreach ($tests as $path => $models)
  {
    $t->diag(sprintf('Entering %s', $path));

    foreach ($models as $modelName => $extendsFrom)
    {
      $t->ok(
        preg_match(
          "/class\s{$modelName}Table\sextends\s{$extendsFrom}/",
          file_get_contents("{$path}/{$modelName}Table.class.php")
        ),
        sprintf('Class "%sTable" extends from "%s"', $modelName, $extendsFrom)
      );
    }
  }

  $t->diag('Checking plugin tables');

  $tests = array(
    sfConfig::get('sf_plugins_dir') . '/sfDoctrineGuardPlugin/lib/model/doctrine' => array(
      'sfGuardGroup'            => 'Doctrine_Table',
      'sfGuardGroupPermission'  => 'Doctrine_Table',
      'sfGuardUserGroup'        => 'Doctrine_Table',
      'sfGuardUser'             => 'Doctrine_Table',
    ),
  );

  foreach ($tests as $path => $models)
  {
    $t->diag(sprintf('Entering %s', $path));

    foreach ($models as $modelName => $extendsFrom)
    {
      $t->ok(
        preg_match(
          "/class\sPlugin{$modelName}Table\sextends\s{$extendsFrom}/",
          file_get_contents("{$path}/Plugin{$modelName}Table.class.php")
        ),
        sprintf('Class "%sTable" extends from "%s"', $modelName, $extendsFrom)
      );
    }
  }