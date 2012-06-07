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

  $t->diag('Executing: ./symfony doctrine:build-table --depth=3 --env=test --no-phpdoc --no-confirmation');
  $returnCode = $task->run(array(), array('depth' => 3, 'env' => 'test', 'no-phpdoc' => true, 'no-confirmation' => true));

  if (0 != $returnCode)
  {
    $t->fail(sprintf("Failed to run task. Return code is %s", $returnCode));
    return;
  }

  $t->diag('Checking tables');

  foreach ($tests as $path => $models)
  {
    foreach ($models as $className => $isEnabledTables)
    {
      if (! $isEnabledTables)
      {
        continue;
      }

      $table = Doctrine::getTable($className);

      $columnsCount = count($table->getColumnNames());

      $baseTableContent = file_get_contents("{$path}/base/Base{$className}Table.class.php");

      $matches = array();
      preg_match_all('/@method\s+.+/', $baseTableContent, $matches);

      $t->is(count($matches[0]), 0, '@method is disabled');

      $matches = array();
      // findOne, findOneBy - magically supported by Doctrine itself
      preg_match_all('/@c\(m=(andWhere|andWhereIn|orWhere|orWhereIn)\w+,n=\w+,c=\w+\)/', $baseTableContent, $matches);

      $t->is(count($matches[0]), $methodCount = $columnsCount * 4, sprintf('@c Column method types in sum = %d', $methodCount));
    }
  }
