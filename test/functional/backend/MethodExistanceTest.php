<?php

  /*
   * This file is part of the sfDoctrineTablePlugin package.
   * (c) 2012 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  include_once realpath(__DIR__ . '/../../bootstrap/functional.php');
  include_once sfConfig::get('sf_symfony_lib_dir') . '/vendor/lime/lime.php';

  $t = new lime_test();

  $t->diag('Executing: ./symfony doctrine:build-table --depth=3 --no-confirmation --env=test');

  $task = new sfDoctrineBuildTableTask(new sfEventDispatcher(), new sfFormatter());

  $returnCode = $task->run(array(), array('depth' => 3, 'no-confirmation' => true, 'env' => 'test'));

  if (0 != $returnCode)
  {
    $t->fail(sprintf("Failed to run task. Return code is %s", $returnCode));
    return;
  }

  $cultureTable = CultureTable::getInstance();
  $cityTable = CityTable::getInstance();
  $postTable = PostTable::getInstance();
  $sfGuardUser = sfGuardUserTable::getInstance();

  $tests = array(
    array(
      'model' => 'sfGuardUser',
      'methods' => array(
        array(
          'method' => 'withInnerJoinOnUserViaSfGuardUserGroup',
          'args' => array($sfGuardUser->createQuery()),
        ),
        array(
          'method' => 'withInnerJoinOnSfGuardGroupPermissionViaSfGuardUserPermissionAndPermission',
          'args' => array($sfGuardUser->createQuery()),
        ),
        array(
          'method' => 'withLeftJoinOnSfGuardGroupPermissionViaPermissions',
          'args' => array($sfGuardUser->createQuery()),
        ),
        array(
          'method' => 'withInnerJoinOnGroupViaGroupsAndSfGuardGroupPermission',
          'args' => array($sfGuardUser->createQuery()),
        ),
        array(
          'method' => 'orWhereSaltIn',
          'args' => array($sfGuardUser->createQuery(), array('md5', 'sha1')),
        ),
        array(
          'method' => 'andWhereSaltIn',
          'args' => array($sfGuardUser->createQuery(), array('md5', 'sha1')),
        ),
        array(
          'method' => 'andWhereSalt',
          'args' => array($sfGuardUser->createQuery(), 'md5'),
        ),
        array(
          'method' => 'orWhereSalt',
          'args' => array($sfGuardUser->createQuery(), 'sha1'),
        ),
      ),
    ),
    array(
      'model' => 'Post',
      'methods' => array(
        array(
          'method' => 'addSelectChildPostsCountAsJoin',
          'args' => array($postTable->createQuery()),
        ),
        array(
          'method' => 'addSelectChildPostsCountAsSubSelect',
          'args' => array($postTable->createQuery()),
        ),
        array(
          'method' => 'addSelectImagesCountAsJoin',
          'args' => array($postTable->createQuery()),
        ),
        array(
          'method' => 'addSelectImagesCountAsSubSelect',
          'args' => array($postTable->createQuery()),
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
    array(
      'model' => 'City',
      'methods' => array(
        array(
          'method' => 'withInnerJoinOnCountry',
          'args' => array($cityTable->createQuery()),
        ),
        array(
          'method' => 'withLeftJoinOnCapitalViaCountry',
          'args' => array($cityTable->createQuery()),
        ),
        array(
          'method' => 'withLeftJoinOnCapitalOfTheCountryViaCountryAndCapital',
          'args' => array($cityTable->createQuery()),
        ),
      ),
    ),
  );


  foreach ($tests as $test)
  {
    list($modelName, $methods) = array_values($test);

    $t->diag(sprintf('Checking method existance for model "%s"', $modelName));

    $table = Doctrine::getTable($modelName);

    foreach ($methods as $methodVariables)
    {
      list($method, $args) = array_values($methodVariables);

      try
      {
        call_user_func_array(array($table, $method), $args);

        $t->pass(sprintf('Method "%s" exists', $method));
      }
      catch (Doctrine_Table_Exception $e)
      {
        $t->fail($e->getMessage());
      }
    }
  }
