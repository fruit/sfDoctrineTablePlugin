<?php

  /*
   * This file is part of the sfDoctrineTablePlugin package.
   * (c) 2011 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  /**
   * @author Ilya Sabelnikov <fruit.dev@gmail.com>
   */
  class Doctrine_Table_Example extends Doctrine_Table_Scoped
  {
    /**
     * Initialize new Doctrine_Query instance or clones if $q is passed
     *
     * @param Doctrine_Query $q optional
     * @return Doctrine_Query
     */
    protected function createQueryIfNull (Doctrine_Query $q = null)
    {
      return null === $q
        ? $this->createQuery($this->suggestAlias())
        : clone $q;
    }

    /**
     * Creates alias based on table component name
     *
     * @return string
     */
    protected function suggestAlias ()
    {
      preg_match_all('/[A-Z]/', $this->getComponentName(), $m);

      return strtolower(implode('', $m[0]));
    }

    /**
     * Adds all PostTable columns to the query
     *
     * @param Doctrine_Query $q
     * @return Doctrine_Table_Example
     */
    public function addSelectTableColumns (Doctrine_Query $q)
    {
      $q->addSelect("{$q->getRootAlias()}.*");

      return $this;
    }

    /**
     * Default getQuery method to work directly with Doctrine_Query object
     *
     * @param Doctrine_Query $q
     * @return Doctrine_Query
     */
    public function getQuery (Doctrine_Query $q = null)
    {
      $q = $this->createQueryIfNull($q);

      $this->addSelectTableColumns($q);

      return $q;
    }

    protected function buildFindOneByColumn (array $params, Doctrine_Query $q, $value, $hydrationMode = null)
    {
      $q
        ->limit(1)
        ->setHydrationMode($hydrationMode)
        ->where("{$q->getRootAlias()}.{$params['n']} != ?", $value);

      return $q->execute();
    }
  }