<?php

  /*
   * This file is part of the sfDoctrineTablePlugin package.
   * (c) 2012 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  include_once realpath(dirname(__FILE__) . '/../../bootstrap/functional.php');
  include_once sfConfig::get('sf_symfony_lib_dir') . '/vendor/lime/lime.php';

  $t = new lime_test();

  $task = new sfDoctrineBuildTableTask(new sfEventDispatcher(), new sfFormatter());
  $t->diag('Executing: ./symfony doctrine:build-table --depth=3 --no-confirmation --env=test');
  $returnCode = $task->run(array(), array('depth' => 3, 'no-confirmation' => true, 'env' => 'test'));

  if (0 != $returnCode)
  {
    $t->fail(sprintf("Failed to run task. Return code is %s", $returnCode));
    return;
  }


  class Test_Doctrine_Query extends Doctrine_Query
  {
    public function getWhereParams ()
    {
      return $this->_params['where'];
    }
  }



  Doctrine_Manager::getInstance()->setAttribute(Doctrine_Core::ATTR_QUERY_CLASS, 'Test_Doctrine_Query');
  Doctrine_Manager::getInstance()->setAttribute(Doctrine_Core::ATTR_USE_DQL_CALLBACKS, true);

  $cultureTable = CultureTable::getInstance();
  $postTable = PostTable::getInstance();

  PostTable::getInstance()->createQuery()->delete()->execute();
  CultureTable::getInstance()->createQuery()->delete()->execute();
  SectionTable::getInstance()->createQuery()->delete()->execute();

  $dbh = Doctrine_Manager::getInstance()->getConnection('doctrine')->getDbh();
  $dbh->exec('DELETE FROM `culture_translation`; DELETE FROM `section_translation`;');

  Doctrine_Core::loadData(__DIR__ . '/../../fixtures/data');

  $q = $cultureTable->createQuery('c');
  $cultureTable->andWhereIsVisible($q, true)->andWhereCode($q, 'fr');
  $t->is($dql = $q->getDql(), " FROM Culture c WHERE c.is_visible = ? AND c.code = ?", $dql);
  $t->is($q->getWhereParams(), array(1, 'fr'));
  $t->is($q->count(), 1);

  $q = $cultureTable->createQuery('c');
  $cultureTable->orWhereIsVisible($q, false)->orWhereCode($q, 'fr');
  $t->is($dql = $q->getDql(), " FROM Culture c WHERE c.is_visible = ? OR c.code = ?", $dql);
  $t->is($q->getWhereParams(), array(0, 'fr'));
  $t->is($q->count(), 3);

  $q = $cultureTable->createQuery('c');
  $cultureTable->andWhereCodeIn($q, array('fr', 'en', 'de'))->orWhereCode($q, 'af');
  $t->is($dql = $q->getDql(), " FROM Culture c WHERE c.code IN (?, ?, ?) OR c.code = ?", $dql);
  $t->is($q->getWhereParams(), array('fr', 'en', 'de', 'af'));
  $t->is($q->count(), 4);

  $q = $cultureTable->createQuery('c');
  $cultureTable->orWhereCodeIn($q, array('en_US'))->orWhereCodeIn($q, array('en_ZA'));
  $t->is($dql = $q->getDql(), " FROM Culture c WHERE c.code IN (?) OR c.code IN (?)", $dql);
  $t->is($q->getWhereParams(), array('en_US', 'en_ZA'));
  $t->is($q->count(), 2);

  $t->is($cultureTable->findByCode('fr')->count(), 1);
  $t->is($cultureTable->findByIsVisible(1)->count(), 5);

  $t->is(count($cultureTable->findByCode('fr', Doctrine_Core::HYDRATE_ARRAY)), 1);
  $t->is(count($cultureTable->findByIsVisible(1, Doctrine_Core::HYDRATE_ARRAY)), 5);

  $t->isnt($cultureTable->findOneByCode('fr'), false);


  /**
   * Joins
   */

  // single Inner join with select all

  $q = $postTable->createQuery('p')->select('id');

  $q->addSelect('s.*');
  $postTable
    ->withInnerJoinOnSection($q/*, true*/)
    ->andWhereTitle($q, 'Parsing XML documents with CSS selectors')
  ;

  $t->is(
    $dql = $q->getDql(),
    'SELECT id, s.* FROM Post p INNER JOIN p.Section s WHERE p.title = ?', $dql
  );

  $r = $q->limit(1)->fetchOne(array(), Doctrine_Core::HYDRATE_ARRAY);

  $t->ok(isset($r['Section']), 'Section is part of SELECT clause');

  // single Inner join without select all

  $q = $postTable->createQuery('p')->select('id');

  $postTable
    ->withInnerJoinOnSection($q)
    ->andWhereTitle($q, 'Parsing XML documents with CSS selectors')
  ;

  $t->is(
    $dql = $q->getDql(),
    'SELECT id FROM Post p INNER JOIN p.Section s WHERE p.title = ?', $dql
  );

  $r = $q->limit(1)->fetchOne(array(), Doctrine_Core::HYDRATE_ARRAY);

  $t->ok(! isset($r['Section']), 'Section is not a part of SELECT clause');

  // double 1-level Inner joins without select all

  $postTable = PostTable::getInstance();

  $q = $postTable->createQuery('p');

  $postTable
    ->withInnerJoinOnSection($q)
    ->withInnerJoinOnCulture($q)
    ->andWhereTitle($q, 'Parsing XML documents with CSS selectors')
  ;

  $q->select('p.id, p.title, s.is_visible as is_section_active, c.name as culture_name');

  $t->is(
    $dql = $q->getDql(),
    'SELECT p.id, p.title, s.is_visible as is_section_active, c.name as culture_name ' .
    'FROM Post p ' .
      'INNER JOIN p.Section s ' .
      'INNER JOIN p.Culture c ' .
    'WHERE p.title = ?', $dql
  );

  $post = $q->limit(1)->fetchOne(array(), Doctrine_Core::HYDRATE_ARRAY);

  unset($post['id']);

  $snapshot = array(
    'title' => 'Parsing XML documents with CSS selectors',
    'is_section_active' => '1',
    'culture_name' => 'English',
    'is_section_active' => '1',
    'culture_name' => 'English',
  );

  $t->is_deeply($post, $snapshot, 'Retrieved snapshot matches');

  // double 1 level Left joins without select all

  $postTable = PostTable::getInstance();

  $q = $postTable->createQuery('p');

  $q->select('p.id, p.title, s.*, c.*');
  $postTable
    ->withLeftJoinOnSection($q)
    ->withLeftJoinOnCulture($q)
    ->andWhereTitle($q, 'README of Symfony sfCacheTaggingPlugin behavior (part 1)')
  ;

  $t->is(
    $dql = $q->getDql(),
    'SELECT p.id, p.title, s.*, c.* ' .
    'FROM Post p ' .
      'LEFT JOIN p.Section s ' .
      'LEFT JOIN p.Culture c ' .
    'WHERE p.title = ?', $dql
  );
  $post = $q->limit(1)->fetchOne(array(), Doctrine_Core::HYDRATE_ARRAY);
  $t->ok(null === $post['Section'], 'Section sub-array is null');
  $t->ok(is_array($post['Culture']), 'Culure sub-array not empty');


  // 2-level joins with translation table

  $q = $postTable->createQuery('p')->select('p.*');
  $postTable
    ->withInnerJoinOnCulture($q)
    ->withInnerJoinOnTranslationViaCulture($q)
    ->andWhereId($q, 4)
  ;

  $t->is(
    $dql = $q->getDql(),
    'SELECT p.* ' .
    'FROM Post p ' .
      'INNER JOIN p.Culture c ' .
      'INNER JOIN c.Translation c_t WITH c_t.lang = \'en\' ' .
    'WHERE p.id = ?', $dql
  );

  // 2-level joins with all translation rows

  $q = $postTable->createQuery('p')->select('p.*');
  $postTable
    ->withInnerJoinOnCulture($q)
    /* !NOTICE added "s" to "Translation" to select all translation */
    ->withInnerJoinOnTranslationsViaCulture($q)
    ->andWhereId($q, 4)
  ;

  $t->is(
    $dql = $q->getDql(),
    'SELECT p.* ' .
    'FROM Post p ' .
      'INNER JOIN p.Culture c ' .
      'INNER JOIN c.Translation c_ts ' .
    'WHERE p.id = ?',
    $dql
  );

  // more difficult joins

  $q = sfGuardUserTable::getInstance()->createQuery('u');

  $q->select('u.*');

  sfGuardUserTable::getInstance()
    ->withInnerJoinOnGroups($q)
    ->withLeftJoinOnPermissionsViaGroups($q)
    ->withInnerJoinOnSfGuardGroupPermissionViaGroupsAndPermissions($q)
  ;

  $t->is(
    $dql = $q->getDql(),
    'SELECT u.* ' .
    'FROM sfGuardUser u ' .
      'INNER JOIN u.Groups gs ' .  // gs = [g]roups, [s] - one-2-many (plural)
      'LEFT JOIN gs.Permissions gs_ps ' . // gs_ps = [g]roups 1:N, [p]ermissions 1:N (s)
      'INNER JOIN gs_ps.sfGuardGroupPermission gs_ps_sggps', // sggps = [S]f[G]uard[G]roup[P]ermission + 1:N (s)
    $dql
  );

  $q = sfGuardUserTable::getInstance()->createQuery('u');

  $q->select('u.*');

  sfGuardUserTable::getInstance()
    ->withLeftJoinOnSfGuardUserGroup($q)
    ->withLeftJoinOnGroupViaSfGuardUserGroup($q)
    ->withLeftJoinOnSfGuardGroupPermissionViaSfGuardUserGroupAndGroup($q)
  ;

  $t->is(
    $dql = $q->getDql(),
    'SELECT u.* ' .
    'FROM sfGuardUser u ' .
      'LEFT JOIN u.sfGuardUserGroup sgugs ' .
      'LEFT JOIN sgugs.Group sgugs_g ' .
      'LEFT JOIN sgugs_g.sfGuardGroupPermission sgugs_g_sggps',
    $dql
  );


  // COUNT as JOIN

  // without additional condition
  $q = sfGuardUserTable::getInstance()->createQuery('u');
  $q->select('u.*');
  sfGuardUserTable::getInstance()->addSelectSfGuardUserGroupCountAsJoin($q);

  $t->is(
    $dql = $q->getDql(),
    'SELECT u.*, COUNT(sgugs_cnt.user_id) as sf_guard_user_group_count ' .
    'FROM sfGuardUser u LEFT JOIN u.sfGuardUserGroup sgugs_cnt ' .
    'GROUP BY sgugs_cnt.user_id',
    $dql
  );

  // with additional condition
  $q = sfGuardUserTable::getInstance()->createQuery('u');
  $q->select('u.*');
  sfGuardUserTable::getInstance()->addSelectSfGuardUserGroupCountAsJoin(
    $q,
    'sgugs_cnt.group_id != ? AND sgugs_cnt.created_at + 0 != ?',
    array(2, 0)
  );

  $t->is(
    $dql = $q->getDql(),
    'SELECT u.*, COUNT(sgugs_cnt.user_id) as sf_guard_user_group_count ' .
    'FROM sfGuardUser u LEFT JOIN u.sfGuardUserGroup sgugs_cnt ' .
      'WITH sgugs_cnt.group_id != ? AND sgugs_cnt.created_at + 0 != ? ' .
    'GROUP BY sgugs_cnt.user_id',
    $dql
  );


  // count from table with SoftDelete behavior

  $q = $postTable->createQuery('p')->select('p.*');
  $postTable->addSelectImagesCountAsJoin($q);

  $t->is(
    $dql = $q->getDql(),
    'SELECT p.*, COUNT(is_cnt.post_id) as images_count ' .
    'FROM Post p LEFT JOIN p.Images is_cnt ' .
    'WHERE is_cnt.removed_at IS NULL ' .
    'GROUP BY is_cnt.post_id',
    $dql
  );

  // trying to make JOIN with pre-added GROUP_BY

  try
  {
    $q = $postTable->createQuery('p')->select('p.*');
    $q->addGroupBy('p.title ASC');

    $postTable->addSelectImagesCountAsJoin($q);

    $t->fail('Exception not thrown');
  }
  catch (LogicException $e)
  {
    $t->pass(sprintf('Cached "LogicException" exception with message: %s', $e->getMessage()));
  }



  /**
   * COUNT as Sub-select
   */

  // without additional condition
  $q = sfGuardUserTable::getInstance()->createQuery('u');
  $q->select('u.id, u.username');
  sfGuardUserTable::getInstance()
    ->addSelectSfGuardUserGroupCountAsSubSelect($q)
    ->addSelectSfGuardUserPermissionCountAsSubSelect($q)
  ;

  $t->is(
    $dql = $q->getDql(),
    'SELECT u.id, u.username, ' .
      '(SELECT COUNT(sgugs_cnt.user_id) ' .
      'FROM sfGuardUserGroup sgugs_cnt ' .
      'WHERE u.id = sgugs_cnt.user_id) AS sf_guard_user_group_count, ' .
      '(SELECT COUNT(sgups_cnt.user_id) ' .
      'FROM sfGuardUserPermission sgups_cnt ' .
      'WHERE u.id = sgups_cnt.user_id) AS sf_guard_user_permission_count ' .
    'FROM sfGuardUser u',
    $dql
  );

  // with additional condition
  $q = sfGuardUserTable::getInstance()->createQuery('u');
  $q->select('u.id, u.username');
  sfGuardUserTable::getInstance()
    ->withInnerJoinOnForgotPassword($q, 'fp.ip_address = ?', '198.81.129.125')
    ->addSelectSfGuardUserGroupCountAsSubSelect($q, function(Doctrine_Query $subQuery) {
      $subQuery->addWhere('sgugs_cnt.group_id > ?', 10);
    })
    ->addSelectSfGuardUserPermissionCountAsSubSelect($q, function(Doctrine_Query $subQuery) {
      $subQuery->addWhere('sgups_cnt.permission_id < ?', 10);
    })
    ->addSelectSfGuardUserPermissionCountAsSubSelect($q, function(Doctrine_Query $subQuery) {
      $subQuery->groupBy('sgups_cnt.permission_id');
    }, 'more_then_count')
  ;
  $q->addWhere('u.id != ?', array(8));

  $t->is(
    $dql = $q->getDql(),
    'SELECT ' .
      'u.id, u.username, ' .
      '(' .
        'SELECT COUNT(sgugs_cnt.user_id) ' .
        'FROM sfGuardUserGroup sgugs_cnt ' .
        'WHERE u.id = sgugs_cnt.user_id AND sgugs_cnt.group_id > ?' .
      ') AS sf_guard_user_group_count, ' .

      '(' .
        'SELECT COUNT(sgups_cnt.user_id) ' .
        'FROM sfGuardUserPermission sgups_cnt ' .
        'WHERE u.id = sgups_cnt.user_id AND sgups_cnt.permission_id < ?' .
      ') AS sf_guard_user_permission_count, ' .

      '(' .
        'SELECT COUNT(sgups_cnt.user_id) ' .
        'FROM sfGuardUserPermission sgups_cnt ' .
        'WHERE u.id = sgups_cnt.user_id ' .
        'GROUP BY sgups_cnt.permission_id' .
      ') AS more_then_count ' .

    'FROM sfGuardUser u INNER JOIN u.ForgotPassword fp WITH fp.ip_address = ? ' .
    'WHERE u.id != ?',
    $dql
  );


  // count from table with SoftDelete behavior

  $q = $postTable->createQuery('p')->select('p.*');
  $postTable->addSelectImagesCountAsSubSelect($q);
  $t->is(
    $dql = $q->getDql(),
    'SELECT p.*, ' .
      '(SELECT COUNT(is_cnt.post_id) ' .
      'FROM PostMediaImage is_cnt ' .
      'WHERE p.id = is_cnt.post_id AND is_cnt.removed_at IS NULL) AS images_count ' .
    'FROM Post p',
    $dql
  );