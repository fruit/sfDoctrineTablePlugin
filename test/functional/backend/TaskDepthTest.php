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


  $t->diag('Executing: ./symfony doctrine:build-table --depth=1 --env=test --no-confirmation');
  $returnCode = $task->run(array(), array('depth' => 1, 'env' => 'test', 'no-confirmation' => true));

  if (sfDoctrineBuildTableTask::RETURN_SUCCESS != $returnCode)
  {
    $t->fail(sprintf("Failed to run task. Return code is %s", $returnCode));
    return;
  }

  $tests = array(
    "{$libDir}/sfDoctrineGuardPlugin" => array(
      'sfGuardGroup' => false,
      'sfGuardGroupPermission' => false,
      'sfGuardUserGroup' => false,
      'sfGuardUser' => true,
    ),
    $libDir => array(
      'Post' => true,
      'Section' => true,
      'Culture' => true,
      'PostMediaImage' => true,
    ),
  );

  $t->diag('Checking models');

  foreach ($tests as $path => $models)
  {
    foreach ($models as $className => $isEnabledTables)
    {
      $t->is(
        (bool) preg_match(
          "/class\s{$className}Table\sextends\sBase{$className}Table/",
          file_get_contents("{$path}/{$className}Table.class.php")
        ),
        $isEnabledTables,
        sprintf('Class "%sTable" %s instance of "Base%sTable"', $className, $isEnabledTables ? 'IS' : 'IS NOT', $className)
      );

      if (! $isEnabledTables)
      {
        continue;
      }

      $table = Doctrine::getTable($className);

      $columnsCount = count($table->getColumnNames());


      // findBy, findOneBy, addWhere, whereIn, orWhere, orWhereIn / 6 column method types

      $baseTableContent = file_get_contents("{$path}/base/Base{$className}Table.class.php");

      $matches = array();
      preg_match_all('/@method\s+(?:[\w\|]+)+\s+(findBy|findOneBy|andWhere|andWhereIn|orWhere|orWhereIn)\w+\(\)\s+\w+\(.+\)\s+.+/', $baseTableContent, $matches);

      $methodCount = $columnsCount * 6;

      $t->is(count($matches[0]), $methodCount, sprintf('%s: @method Column method types in sum 6 * %d = %d', $className, $columnsCount, $methodCount));


      $matches = array();
      // findOne, findOneBy - magically supported by Doctrine itself
      preg_match_all('/@c\(m=(andWhere|andWhereIn|orWhere|orWhereIn)\w+,n=\w+,c=\w+\)/', $baseTableContent, $matches);

      $methodCount = $columnsCount * 4;

      $t->is(count($matches[0]), $methodCount, sprintf('%s: @c Column method types in sum 4 * %d = %d', $className, $columnsCount, $methodCount));
    }
  }

  /**
   * Base table "extends X" checks (previously task params)
   */
  $tests = array(
    "{$libDir}/sfDoctrineGuardPlugin" => array(
      // plugin model checks
      'sfGuardPermission'     => 'PluginsfGuardPermissionTable',
      'sfGuardUserPermission' => 'PluginsfGuardUserPermissionTable',
      'sfGuardUser'           => 'PluginsfGuardUserTable',
    ),
    $libDir => array(
      // default model checks
      'Post'            => 'Doctrine_Table_Example',
      'Section'         => 'Doctrine_Table_Example',
      'Culture'         => 'Doctrine_Table_Example',
      'PostMedia'       => 'Doctrine_Table_Example',
      // inheritance check
      'PostMediaImage'  => 'PostMediaTable',
    ),
  );

  foreach ($tests as $path => $models)
  {
    $t->diag(sprintf('Entering %s', $path));

    foreach ($models as $model => $extendsFrom)
    {
      $t->ok(
        preg_match(
          "/class\sBase{$model}Table\sextends\s{$extendsFrom}/",
          file_get_contents("{$path}/base/Base{$model}Table.class.php")
        ),
        "Class \"Base{$model}Table\" has extend class \"{$extendsFrom}\""
      );
    }
  }