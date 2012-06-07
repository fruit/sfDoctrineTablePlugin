<?php

  /*
   * This file is part of the sfDoctrineTablePlugin package.
   * (c) 2012 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  /**
   * Extended Doctrine_Table class to redirect __call method based on
   * generated base table class parameters.
   *
   * @author Ilya Sabelnikov <fruit.dev@gmail.com>
   * @package Doctrine
   * @subpackage Table
   */
  class Doctrine_Table_Scoped extends Doctrine_Table
  {
    /**
     * Reads generated base table doc and finds called method to apply additional
     * params to run the method forward
     *
     * @param string  $method     method that was called
     * @param array   $arguments  arguments that was passed with call
     *
     * @return mixed
     */
    public function __call($method, $arguments)
    {
      if ('getGenericTableName' == $method)
      {
        throw new LogicException(
          'Inheritence order is invalid. Please install base tables first.'
        );
      }

      // Late static bindings in action
      $generatedBaseTableClass = new ReflectionClass(static::getGenericTableName());

      $phpdoc = $generatedBaseTableClass->getDocComment();

      $searchKey = "m={$method},";
      $pos = stripos($phpdoc, $searchKey);

      if (false === $pos)
      {
        return parent::__call($method, $arguments);
      }

      // calculation where in PHPDoc is located desired method
      $break = strpos($phpdoc, PHP_EOL, $pos);
      $searchKeyLenght = strlen($searchKey);
      $stringParams = substr(
        $phpdoc, $pos + $searchKeyLenght, $break - ($pos + $searchKeyLenght) - 1
      );

      // create handy array to work with parsed parameters
      $params = array();
      foreach (explode(',', $stringParams) as $row)
      {
        list($key, $value) = explode('=', $row);
        $params[$key] = $value;
      }

      // $params["c"] is a string representation of method to call
      // with parsed params
      return call_user_func_array(
        array($this, $params['c']),             // callable
        array_merge(array($params), $arguments) // arguments
      );
    }

    /**
     * Add specific JOIN on Translation
     *
     * @param array           $params     list of parameters
     * @param string          $joinType   join type: innerJoin/leftJoin
     * @param Doctrine_Query  $q          query to apply condition
     * @param null|string     $culture    optional culture lang to use in WITH clause
     *
     * @throws InvalidArgumentException
     *
     * @return Doctrine_Table_Scoped
     */
    protected function buildJoinI18n (array $params, $joinType, Doctrine_Query $q, $culture = null)
    {
      $culture = null === $culture
        ? sfContext::getInstance()->getUser()->getCulture()
        : $culture;

      $params['f'] = $params['f'] == '^' ? $q->getRootAlias() : $params['f'];

      if (! is_string($culture))
      {
        throw new InvalidArgumentException('Invalid variable $culture value');
      }

      $q
        ->{$joinType}(
          sprintf(
            "{$params['f']}.{$params['ra']} {$params['o']} WITH {$params['o']}.lang = %s",
            $q->getConnection()->quote($culture)
          )
        );

      return $this;
    }

    /**
     * Adds INNER JOIN on Translation table
     *
     * @param array           $params   list of parameters
     * @param Doctrine_Query  $q        query to apply condition
     * @param null|string     $culture  optional culture lang to use in WITH clause
     *
     * @return Doctrine_Table_Scoped
     */
    protected function buildInnerI18n (array $params, Doctrine_Query $q, $culture = null)
    {
      return $this->buildJoinI18n($params, 'innerJoin', $q, $culture);
    }

    /**
     * Adds LEFT JOIN on Translation table
     *
     * @param array           $params   list of parameters
     * @param Doctrine_Query  $q        query to apply condition
     * @param null|string     $culture  optional culture lang to use in WITH clause
     *
     * @return Doctrine_Table_Scoped
     */
    protected function buildLeftI18n (array $params, Doctrine_Query $q, $culture = null)
    {
      return $this->buildJoinI18n($params, 'leftJoin', $q, $culture);
    }

    /**
     * Adds specific JOIN to the query
     *
     * @param array           $params     list of parameters
     * @param string          $joinType   join type: innerJoin/leftJoin
     * @param Doctrine_Query  $q          query to apply condition
     * @param string          $with       optional expression to add to the JOIN into WITH
     * @param array           $args       optional list of arguments used in WITH expression
     *
     * @return Doctrine_Table_Scoped
     */
    protected function buildJoin (array $params, $joinType, Doctrine_Query $q, $with = null, $args = array())
    {
      $params['f'] = $params['f'] == '^' ? $q->getRootAlias() : $params['f'];

      $q->$joinType(
        "{$params['f']}.{$params['ra']} {$params['o']}" .
          (! $with ? '' : " WITH {$with}"),
        $args
      );

      return $this;
    }

    /**
     * Adds INNER JOIN clause to the query
     *
     * @param array           $params   list of parameters
     * @param Doctrine_Query  $q        query to apply condition
     * @param string          $with     optional expression to add to the JOIN into WITH
     * @param array           $args     optional list of arguments used in WITH expression
     *
     * @return Doctrine_Table_Scoped
     */
    protected function buildInner (array $params, Doctrine_Query $q, $with = null, $args = array())
    {
      return $this->buildJoin($params, 'innerJoin', $q, $with, $args);
    }

    /**
     * Adds LEFT JOIN clause to the query
     *
     * @param array           $params   list of parameters
     * @param Doctrine_Query  $q        query to apply condition
     * @param string          $with     optional expression to add to the JOIN into WITH
     * @param array           $args     optional list of arguments used in WITH expression
     *
     * @return Doctrine_Table_Scoped
     */
    protected function buildLeft (array $params, Doctrine_Query $q, $with = null, $args = array())
    {
      return $this->buildJoin($params, 'leftJoin', $q, $with, $args);
    }

    /**
     * Adds AND expression to the WHERE clause with params taken from parsed PHPDoc
     *
     * @param array           $params   list of parameters
     * @param Doctrine_Query  $q        query to apply condition
     * @param scalar          $value    argument used in expression
     *
     * @return Doctrine_Table_Scoped
     */
    protected function buildAndWhere (array $params, Doctrine_Query $q, $value)
    {
      $value = is_bool($value) ? $q->getConnection()->convertBooleans($value) : $value;

      $q->andWhere("{$q->getRootAlias()}.{$params['n']} = ?", $value);

      return $this;
    }

    /**
     * Adds AND IN expression to the WHERE clause with params taken from parsed PHPDoc
     *
     * @param array           $params   list of parameters
     * @param Doctrine_Query  $q        query to apply condition
     * @param array           $values   a list of arguments passed to condition
     * @param boolean         $not      optional condition whether to insert NOT to the clause
     *
     * @return Doctrine_Table_Scoped
     */
    protected function buildAndWhereIn (array $params, Doctrine_Query $q, array $values, $not = false)
    {
      $q->andWhereIn("{$q->getRootAlias()}.{$params['n']}", $values, $not);

      return $this;
    }

    /**
     * Adds OR expression the WHERE clause
     *
     * @param array           $params   list of parameters
     * @param Doctrine_Query  $q        query to apply condition
     * @param scalar          $value    argument used in expression
     *
     * @return Doctrine_Table_Scoped
     */
    protected function buildOrWhere (array $params, Doctrine_Query $q, $value)
    {
      $value = is_bool($value)
        ? $q->getConnection()->convertBooleans($value)
        : $value;

      $q->orWhere("{$q->getRootAlias()}.{$params['n']} = ?", $value);

      return $this;
    }

    /**
     * Adds OR IN expression to the WHERE clause with params taken from parsed PHPDoc
     *
     * @param array           $params   list of parameters
     * @param Doctrine_Query  $q        query to apply condition
     * @param array           $values   optional list of arguments passed to condition
     * @param boolean         $not      optional condition whether to insert NOT to the clause
     *
     * @return Doctrine_Table_Scoped
     */
    protected function buildOrWhereIn (array $params, Doctrine_Query $q, array $values, $not = false)
    {
      $q->orWhereIn("{$q->getRootAlias()}.{$params['n']}", $values, $not);

      return $this;
    }

    /**
     * Adds COUNT expression as JOIN on table
     *
     * @param array           $params   list of parameters
     * @param Doctrine_Query  $q        query to apply condition
     * @param string          $with     optional additional WITH expression to add to the JOIN
     * @param array           $args     optional list of arguments used in WITH expression
     *
     * @throws LogicException
     *
     * @return Doctrine_Table_Scoped
     */
    protected function buildAddSelectCountAsJoin (array $params, Doctrine_Query $q, $with = null, array $args = array())
    {
      if (0 < count($q->getDqlPart('groupby')))
      {
        throw new LogicException(sprintf(
          'You could not mix many GROUPBY when retrieving COUNT as a JOIN'
        ));
      }

      $this->buildLeft($params, $q, $with, $args);

      $q
        ->addSelect("COUNT({$params['o']}.{$params['rf']}) as {$params['ca']}")
        ->addGroupBy("{$params['o']}.{$params['rf']}")
      ;

      // In case table with SoftDelete behavior
      if (isset($params['s']))
      {
        $q->addWhere("{$params['o']}.{$params['s']} IS NULL");
      }

      return $this;
    }

    /**
     * Builds sub-query witch selects COUNT from a specific table
     *
     * @param array           $params     list of parameters
     * @param Doctrine_Query  $q          query to apply condition
     * @param Closure         $callback   optional anonymous function will pass subquery to it as first argument
     *
     * @return Doctrine_Query
     */
    protected function buildGetCountDqlAsSubSelect (array $params, Doctrine_Query $q, Closure $callback = null)
    {
      $queryClass = $q->getConnection()->getAttribute(Doctrine::ATTR_QUERY_CLASS);

      $subQuery = new $queryClass($q->getConnection());
      $subQuery->isSubquery(true);

      $subQuery
        ->addFrom("{$params['rc']} {$params['o']}")
        ->addSelect("COUNT({$params['o']}.{$params['rf']})")
        ->addWhere("{$q->getRootAlias()}.{$params['rl']} = {$params['o']}.{$params['rf']}")
      ;

      // In case table with SoftDelete behavior
      if (isset($params['s']))
      {
        $subQuery->addWhere("{$params['o']}.{$params['s']} IS NULL");
      }

      /**
       * Useful when user wants to add groupBy or another where conditions
       */
      if (null !== $callback)
      {
        $callback($subQuery);
      }

      return $subQuery;
    }

    /**
     * Adds COUNT expression as sub-query
     *
     * @param array           $params     list of parameters
     * @param Doctrine_Query  $q          query to apply condition
     * @param Closure         $callback   optional anonymous function will pass subquery to it as first argument
     * @param string          $alias      optional alias to name sub-query in AS clause
     *
     * @return Doctrine_Table_Scoped
     */
    protected function buildAddSelectCountAsSubselect (array $params, Doctrine_Query $q, Closure $callback = null, $alias = null)
    {
      $subQuery = $this->buildGetCountDqlAsSubselect($params, $q, $callback);

      $alias = is_string($alias) ? $alias : $params['ca'];

      $q->addSelect("({$subQuery->getDql()}) AS {$alias}");

      return $this;
    }
  }
