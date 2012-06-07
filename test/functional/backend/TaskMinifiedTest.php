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
      // plugin model checks
      'sfGuardPermission'     => array(),
      'sfGuardUserPermission' => array(),
      'sfGuardUser'           => array(),
    ),
    $libDir => array(
      // default model checks
      'Post'            => array(
        'withInnerJoinOnSection',
        'andWhereCultureIdIn',
        'orWhereIsEnabled',
        'withLeftJoinOnReferenced',
        'withInnerJoinOnCultureViaReferenced',
      ),
      'Section'         => array(),
      'Culture'         => array(
        'withInnerJoinOnTranslation',
        'withInnerJoinOnPosts',
        'addSelectPostsCountAsSubSelect',
      ),
      'PostMedia'       => array(),
      'PostMediaImage'  => array(),
    ),
  );

  $t->diag('Executing: ./symfony doctrine:build-table --depth=2 --env=test --minified --no-confirmation');
  $returnCode = $task->run(array(), array('depth' => 2, 'env' => 'test', 'minified' => true, 'no-confirmation' => true));

  if (0 != $returnCode)
  {
    $t->fail(sprintf("Failed to run task. Return code is %s", $returnCode));
    return;
  }

  foreach ($tests as $path => $models)
  {
    foreach ($models as $className => $usedMethods)
    {
      $countUsed = count($usedMethods);
      if (0 == $countUsed)
      {
        continue;
      }

      $baseTableContent = file_get_contents("{$path}/base/Base{$className}Table.class.php");

      $matches = array();
      preg_match_all('/@method\s+.+/', $baseTableContent, $matches);

      if ($countUsed > count($matches[0]))
      {
        $t->fail('Number of used @method PHPDoc\'s is greater than generated');
        continue;
      }

      $matches = array();
      preg_match_all('/@c\(.+/', $baseTableContent, $matches);

      if ($countUsed > count($matches[0]))
      {
        $t->fail('Number of used @c PHPDoc\'s is greater than generated');
        continue;
      }

      foreach ($usedMethods as $index => $usedMethodName)
      {
        if (false === strpos($baseTableContent, $usedMethodName))
        {
          $t->fail(sprintf('Method %s not found', $usedMethodName));
        }
        else
        {
          $t->pass(sprintf('Method %s exists', $usedMethodName));
        }
      }
    }
  }

  $t->diag('Executing: ./symfony doctrine:build-table --depth=2 --env=test --no-phpdoc --minified --no-confirmation');
  $returnCode = $task->run(array(), array('depth' => 2, 'env' => 'test', 'minified' => true, 'no-phpdoc' => true, 'no-confirmation' => true));

  if (0 != $returnCode)
  {
    $t->fail(sprintf("Failed to run task. Return code is %s", $returnCode));
    return;
  }

  foreach ($tests as $path => $models)
  {
    foreach ($models as $className => $usedMethods)
    {
      $countUsed = count($usedMethods);
      if (0 == $countUsed)
      {
        continue;
      }

      $baseTableContent = file_get_contents("{$path}/base/Base{$className}Table.class.php");

      $matches = array();
      preg_match_all('/@method\s+.+/', $baseTableContent, $matches);

      if (0 != count($matches[0]))
      {
        $t->fail('Number of used @method != 0 PHPDoc\'s is greater than generated');
        continue;
      }

      $matches = array();
      preg_match_all('/@c\(.+/', $baseTableContent, $matches);

      if ($countUsed > count($matches[0]))
      {
        $t->fail('Number of used @c PHPDoc\'s is greater than generated');
        continue;
      }

      foreach ($usedMethods as $index => $usedMethodName)
      {
        if (false === strpos($baseTableContent, $usedMethodName))
        {
          $t->fail(sprintf('Method %s not found', $usedMethodName));
        }
        else
        {
          $t->pass(sprintf('Method %s exists', $usedMethodName));
        }
      }
    }
  }