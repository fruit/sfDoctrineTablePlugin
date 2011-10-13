<?php

  /*
   * This file is part of the sfDoctrineTablePlugin package.
   * (c) 2011 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  include_once realpath(__DIR__ . '/../../bootstrap/functional.php');
  include_once sfConfig::get('sf_symfony_lib_dir') . '/vendor/lime/lime.php';

  $t = new lime_test();

  $t->diag('Executing: ./symfony doctrine:build-table --depth=3 --no-confirmation --env=test');

  $task = new sfDoctrineBuildTableTask(new sfEventDispatcher(), new sfFormatter());
  $task->run(array(), array('depth' => 3, 'no-confirmation' => true, 'env' => 'test'));

  $cultureTable = CultureTable::getInstance();

  $tests = array(
    array(
      'model' => 'sfGuardUser',
      'methods' => array(
        array(
          'method' => 'withInnerJoinOnUserViaSfGuardUserGroup',
          'args' => array($cultureTable->createQuery()),
        ),
        array(
          'method' => 'withInnerJoinOnSfGuardGroupPermissionViaSfGuardUserPermissionAndPermission',
          'args' => array($cultureTable->createQuery()),
        ),
        array(
          'method' => 'withLeftJoinOnSfGuardGroupPermissionViaPermissions',
          'args' => array($cultureTable->createQuery()),
        ),
        array(
          'method' => 'withInnerJoinOnGroupViaGroupsAndSfGuardGroupPermission',
          'args' => array($cultureTable->createQuery()),
        ),
        array(
          'method' => 'orWhereSaltIn',
          'args' => array($cultureTable->createQuery(), array('md5', 'sha1')),
        ),
        array(
          'method' => 'andWhereSaltIn',
          'args' => array($cultureTable->createQuery(), array('md5', 'sha1')),
        ),
        array(
          'method' => 'andWhereSalt',
          'args' => array($cultureTable->createQuery(), 'md5'),
        ),
        array(
          'method' => 'orWhereSalt',
          'args' => array($cultureTable->createQuery(), 'sha1'),
        ),
      ),
    ),
    array(
      'model' => 'Post',
      'methods' => array(
        array(
          'method' => 'addSelectChildPostsCountAsJoin',
          'args' => array($cultureTable->createQuery()),
        ),
        array(
          'method' => 'addSelectChildPostsCountAsSubSelect',
          'args' => array($cultureTable->createQuery()),
        ),
        array(
          'method' => 'addSelectImagesCountAsJoin',
          'args' => array($cultureTable->createQuery()),
        ),
        array(
          'method' => 'addSelectImagesCountAsSubSelect',
          'args' => array($cultureTable->createQuery()),
        ),
      ),
    ),
    array(
      'model' => 'Culture',
      'methods' => array(
        array(
          'method' => 'andWhereId',
          'args' => array($cultureTable->createQuery(), 1),
        ),
        array(
          'method' => 'orWhereId',
          'args' => array($cultureTable->createQuery(), 1),
        ),
        array(
          'method' => 'orWhereIdIn',
          'args' => array($cultureTable->createQuery(), array(1, 2, 3)),
        ),
        array(
          'method' => 'withLeftJoinOnPosts',
          'args' => array($cultureTable->createQuery()),
        ),
        array(
          'method' => 'withInnerJoinOnPosts',
          'args' => array($cultureTable->createQuery()),
        ),
        array(
          'method' => 'withLeftJoinOnParentPostsViaPostsAndReferenced',
          'args' => array($cultureTable->createQuery()),
        ),
        array(
          'method' => 'withInnerJoinOnParentViaPostsAndPostReference',
          'args' => array($cultureTable->createQuery()),
        ),
        array(
          'method' => 'withLeftJoinOnPostViaPostsAndParentPosts',
          'args' => array($cultureTable->createQuery()),
        ),
        array(
          'method' => 'withLeftJoinOnChildViaPostsAndChildPosts',
          'args' => array($cultureTable->createQuery()),
        ),
        array(
          'method' => 'withLeftJoinOnTranslations',
          'args' => array($cultureTable->createQuery(), 'en'),
        ),
        array(
          'method' => 'withInnerJoinOnTranslations',
          'args' => array($cultureTable->createQuery(), 'en'),
        ),
        array(
          'method' => 'withInnerJoinOnTranslation',
          'args' => array($cultureTable->createQuery(), 'en'),
        ),
        array(
          'method' => 'withLeftJoinOnTranslation',
          'args' => array($cultureTable->createQuery(), 'en'),
        ),
      ),
    ),
  );


  foreach ($tests as $test)
  {
    list($modelName, $methods) = array_values($test);

    $t->diag(sprintf('Checking method existance for model "%s"', $modelName));

    $cultureTable = Doctrine::getTable($modelName);

    foreach ($methods as $methodVariables)
    {
      list($method, $args) = array_values($methodVariables);

      try
      {
        call_user_func_array(array($cultureTable, $method), $args);

        $t->pass(sprintf('Method "%s" exists', $method));
      }
      catch (Doctrine_Table_Exception $e)
      {
        $t->fail($e->getMessage());
      }
    }
  }
