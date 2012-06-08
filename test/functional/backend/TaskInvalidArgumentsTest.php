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

  // Clean existing generated classes models & tables
  // do not use $this->dispatcher|$this->formatter - this will print out task output
  $task = new sfDoctrineBuildTableTask(new sfEventDispatcher(), new sfFormatter());

  $t->diag('Executing: ./symfony doctrine:build-table --depth=0 --env=test --no-confirmation');
  $returnCode = $task->run(array(), array('depth' => 0, 'env' => 'test', 'no-confirmation' => true));
  $t->is($returnCode, sfDoctrineBuildTableTask::RETURN_INVALID_DEPTH, 'Depth can\'t be zero 0');

  $t->diag('Executing: ./symfony doctrine:build-table --depth=2 --generator-class=TestTableGenerator --env=test --no-confirmation');
  $returnCode = $task->run(array(), array('depth' => 2, 'env' => 'test', 'no-confirmation' => true,  'generator-class' => 'FakeTableGenerator'));
  $t->is($returnCode, sfDoctrineBuildTableTask::RETURN_GENERATOR_NOT_FOUND, 'Generator class exist');

  $t->diag('Executing: ./symfony doctrine:build-table Culture Vehicle --depth=1 --env=test --no-confirmation');
  $returnCode = $task->run(array('Culture', 'Vehicle'), array('depth' => 1, 'env' => 'test', 'no-confirmation' => true));
  $t->is($returnCode, sfDoctrineBuildTableTask::RETURN_MODEL_NOT_FOUND, 'Vehicle model isn\'t defined in schema YML');

  /**
   * @todo add 2 tests to check remaining RETURN_* codes
   */