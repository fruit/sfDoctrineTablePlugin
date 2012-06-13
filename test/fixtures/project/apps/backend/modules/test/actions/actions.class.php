<?php

  /**
   * test actions.
   *
   * @package    sfDoctrineTablePlugin
   * @subpackage еуые
   * @author     Ilya Sabelnikov <fruit.dev@gmail.com>
   * @version    SVN: $Id: actions.class.php 23810 2009-11-12 11:07:44Z Kris.Wallsmith $
   */
  class еуыеActions extends sfActions
  {

    /**
     * Executes index action
     *
     * @param sfRequest $request A request object
     */
    public function executeIndex (sfWebRequest $request)
    {
      $this->forward('default', 'module');
    }

    /**
     * Handling call the action List
     *
     * @var $request sfWebRequest
     */
    public function executeCultureList (sfWebRequest $request)
    {
      $q = CultureTable::getInstance()->getQuery();

      CultureTable::getInstance()
        ->withInnerJoinOnTranslation($q)
        ->withInnerJoinOnPosts($q)
        ->addSelectPostsCountAsSubSelect($q)
      ;

      $this->cultures = $q->execute();
    }

    /**
     * Handling call the action PostList
     *
     * @var $request sfWebRequest
   */
    public function executePostList (sfWebRequest $request)
    {
      $q = PostTable::getInstance()->getQuery();

      PostTable::getInstance()
        ->withInnerJoinOnSection($q)
        ->andWhereCultureIdIn($q, array(5, 12))
        ->orWhereIsEnabled($q, 1)
        ->withLeftJoinOnReferenced($q)
        ->withInnerJoinOnCultureViaReferenced($q)
      ;

      $this->posts = $q->execute();
    }

  }
