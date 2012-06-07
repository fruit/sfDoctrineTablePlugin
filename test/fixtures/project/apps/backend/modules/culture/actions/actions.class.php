<?php

  /**
   * culture actions.
   *
   * @package    sfDoctrineTablePlugin
   * @subpackage culture
   * @author     Ilya Sabelnikov <fruit.dev@gmail.com>
   * @version    SVN: $Id: actions.class.php 23810 2009-11-12 11:07:44Z Kris.Wallsmith $
   */
  class cultureActions extends sfActions
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
    public function executeList (sfWebRequest $request)
    {
      $q = CultureTable::getInstance()->getQuery();

      CultureTable::getInstance()
        ->withInnerJoinOnTranslation($q)
        ->withInnerJoinOnPosts($q)
        ->addSelectPostsCountAsSubSelect($q)
      ;

      $this->cultures = $q->execute();
    }

  }
