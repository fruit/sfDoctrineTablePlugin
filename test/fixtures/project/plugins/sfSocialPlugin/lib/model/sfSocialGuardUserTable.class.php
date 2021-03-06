<?php

  /**
   * This class has been auto-generated by the Doctrine ORM Framework
   */
  class sfSocialGuardUserTable extends PluginsfGuardUserTable
  {

    /**
     * search users
     * @param  string  $name name to search
     * @param  integer $page current page
     * @param  integer $n    max per page
     * @return sfPager
     */
    public function search ($name, $page = 1, $n = 10)
    {
      $q = $this->createQuery('u')
        ->where('u.username like ?', '%' . $name . '%')
        ->orderBy('u.username');
      $pager = new sfDoctrinePager('sfGuardUser', $n);
      $pager->setQuery($q);
      $pager->setPage($page);
      $pager->init();

      return $pager;
    }

    /**
     * find many objects by their id, since Doctrine now doesn't do it :-|
     * @param  array               $ids
     * @return Doctrine_Collection
     */
    public function findMany ($ids)
    {
      return $this->createQuery('u')
          ->whereIn('u.id', $ids)
          ->execute();
    }

  }