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


  # Clean existing generated classes models & tables
  # do not use $this->dispatcher|$this->formatter - this will print out task output
  $task = new sfDoctrineBuildTableTask(new sfEventDispatcher(), new sfFormatter());


  $t->diag('Executing: ./symfony doctrine:build-table --uninstall --env=test --no-confirmation');
  $task->run(array(), array('uninstall' => true, 'env' => 'test', 'no-confirmation' => true));


  /**
   * Uninstalled model table should be instanceof Doctrine_Table
   */
  $tests = array(
    "{$libDir}/sfDoctrineGuardPlugin" => array(
      'sfGuardGroup', 'sfGuardGroupPermission', 'sfGuardUserGroup', 'sfGuardUser',
    ),
    $libDir => array('Post', 'Section', 'Culture'),
  );

  foreach ($tests as $path => $models)
  {
    foreach ($models as $className)
    {
      $t->ok(
        ! preg_match(
          "/class\s{$className}Table\sextends\sBase{$className}Table/",
            file_get_contents("{$path}/{$className}Table.class.php")
        ),
        sprintf('Class "%sTable" is not instance of "Base%sTable"', $className, $className)
      );
    }
  }


  /**
   * application, env, depth, minified, uninstall, generator-class, no-confirmation
   */

  $t->diag('Executing: ./symfony doctrine:build-table --depth=1 --env=test --no-confirmation');
  $task->run(array(), array('depth' => 1, 'env' => 'test', 'no-confirmation' => true));

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
    ),
  );

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


      # findBy, findOneBy, addWhere, whereIn, orWhere, orWhereIn / 6 column method types

      $baseTableContent = file_get_contents("{$path}/base/Base{$className}Table.class.php");

      $matches = array();
      preg_match_all('/@method\s+(?:[\w\|]+)+\s+(findBy|findOneBy|andWhere|andWhereIn|orWhere|orWhereIn)\w+\(\)\s+\w+\(.+\)\s+.+/', $baseTableContent, $matches);

      $methodCount = $columnsCount * 6;

      $t->is(count($matches[0]), $methodCount, sprintf('%s: @method Column method types in sum 6 * %d = %d', $className, $columnsCount, $methodCount));


      $matches = array();
      # findOne, findOneBy - magically supported by Doctine itself
      preg_match_all('/@c\(m=(andWhere|andWhereIn|orWhere|orWhereIn)\w+,n=\w+,c=\w+\)/', $baseTableContent, $matches);

      $methodCount = $columnsCount * 4;

      $t->is(count($matches[0]), $methodCount, sprintf('%s: @c Column method types in sum 4 * %d = %d', $className, $columnsCount, $methodCount));
    }
  }

  $t->diag('Executing: ./symfony doctrine:build-table --depth=3 --env=test --minified --no-confirmation');
  $task->run(array(), array('depth' => 3, 'env' => 'test', 'minified' => true, 'no-confirmation' => true));


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
      # findOne, findOneBy - magically supported by Doctine itself
      preg_match_all('/@c\(m=(andWhere|andWhereIn|orWhere|orWhereIn)\w+,n=\w+,c=\w+\)/', $baseTableContent, $matches);

      $t->is(count($matches[0]), $methodCount = $columnsCount * 4, sprintf('@c Column method types in sum = %d', $methodCount));
    }
  }



  $t->diag('Executing: ./symfony doctrine:build-table --depth=1 --env=test --generator-class=TestTableGenerator --no-confirmation');
  $task->run(array(), array('depth' => 1, 'env' => 'test', 'generator-class' => 'TestTableGenerator', 'no-confirmation' => true));

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

      $t->is(count($matches[0]), $columnsCount, '@method is disabled');

      $matches = array();
      # findOne, findOneBy - magically supported by Doctine itself
      preg_match_all('/@c\(m=findOnlyOneByColumn\w+,n=\w+,c=\w+\)/', $baseTableContent, $matches);

      $t->is(count($matches[0]), $methodCount = $columnsCount, sprintf('@c Column method types in sum = %d', $methodCount));
    }
  }

  # reset to default files
  $t->diag('Executing: ./symfony doctrine:build-table --depth=3 --env=test --no-confirmation');
  $task->run(array(), array('depth' => 3, 'env' => 'test', 'no-confirmation' => true));