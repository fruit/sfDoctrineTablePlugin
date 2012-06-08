[?php

  /**
   * Base<?php print $this->modelName ?>Table
   *
   * This class was auto-generated by the doctrine:build-table task
   *
   * DO NOT EDIT IT, EXTEND IT!
   *
   * @package    ##PROJECT_NAME##
   * @subpackage table
   * @author     ##AUTHOR_NAME##
   * @version    v<?php print sfDoctrineTablePluginConfiguration::VERSION . PHP_EOL ?>
   *
<?php foreach ($this->getPHPDocByPattern('findBy%s') as $column => $method): ?>
   * @method <?php print $this->getCollectionClass() ?>|array <?php print $method ?>() <?php print $method ?>(scalar $value, int $hydrationMode = null) Finds records by field "<?php print $column ?>"
<?php endforeach; ?>
<?php foreach ($this->getPHPDocByPattern('findOneBy%s') as $column => $method): ?>
   * @method <?php print $this->modelName ?>|array|bool <?php print $method ?>() <?php print $method ?>(scalar $value, int $hydrationMode = null) Finds one record by field "<?php print $column ?>"
<?php endforeach; ?>
<?php foreach ($this->getPHPDocByPattern('andWhere%s', 'buildAndWhere') as $column => $method): ?>
   * @method <?php print $this->modelName ?>Table <?php print $method ?>() <?php print $method ?>(Doctrine_Query $q, scalar $value) Adds "AND '<?php print $column ?>' = ?" expression to the WHERE clause
<?php endforeach; ?>
<?php foreach ($this->getPHPDocByPattern('andWhere%sIn', 'buildAndWhereIn') as $column => $method): ?>
   * @method <?php print $this->modelName ?>Table <?php print $method ?>() <?php print $method ?>(Doctrine_Query $q, array $value, bool $not = false) Adds "AND '<?php print $column ?>' IN (?)" expression to the WHERE clause
<?php endforeach; ?>
<?php foreach ($this->getPHPDocByPattern('orWhere%s', 'buildOrWhere') as $column => $method): ?>
   * @method <?php print $this->modelName ?>Table <?php print $method ?>() <?php print $method ?>(Doctrine_Query $q, scalar $value) Adds "OR '<?php print $column ?>' = ?" expression to the WHERE clause
<?php endforeach; ?>
<?php foreach ($this->getPHPDocByPattern('orWhere%sIn', 'buildOrWhereIn') as $column => $method): ?>
   * @method <?php print $this->modelName ?>Table <?php print $method ?>() <?php print $method ?>(Doctrine_Query $q, array $value, bool $not = false) Adds "OR '<?php print $column ?>' IN (?)" expression to the WHERE clause
<?php endforeach; ?>
<?php foreach ($this->getPHPDocByCategory('add_counts_join') as $method => $options): ?>
   * @method <?php print $this->modelName ?>Table <?php print $method ?>() <?php print $method ?>(Doctrine_Query $q, string $with = null, $params = array()) Adds "COUNT('<?php print $options['relationAlias'] ?>.<?php print $options['relationColumn'] ?>') as <?php print $options['countFieldName'] ?>" expression to the SELECT clause as result of LEFT JOIN on "<?php print $options['relationName'] ?> <?php print $options['relationAlias'] ?>" and GROUP BY '<?php print $options['relationAlias'] ?>.<?php print $options['relationColumn'] ?>'
<?php endforeach; ?>
<?php foreach ($this->getPHPDocByCategory('add_counts_subselect') as $method => $options): ?>
   * @method <?php print $this->modelName ?>Table <?php print $method ?>() <?php print $method ?>(Doctrine_Query $q, Closure $callback = null, string $alias = null) Adds column "<?php print $options['countFieldName'] ?>" as a sub-query expression to select COUNT('<?php print $options['relationAlias'] ?>.<?php print $options['relationColumn'] ?>') from "<?php print $options['relationName'] ?> <?php print $options['relationAlias'] ?>"
<?php endforeach; ?>
<?php foreach ($this->getPHPDocByCategory('translation_joins') as $method => $options): ?>
   * @method <?php print $this->modelName ?>Table <?php print $method ?>() <?php print $method ?>(Doctrine_Query $q, string $culture = null) Adds <?php print $options['joinType'] ?> JOIN on "<?php print $options['relationPath'] ?> <?php print $options['aliasOn'] ?>"
<?php endforeach; ?>
<?php foreach ($this->getPHPDocByCategory('joins') as $method => $options): ?>
   * @method <?php print $this->modelName ?>Table <?php print $method ?>() <?php print $method ?>(Doctrine_Query $q, string $with = null, $params = array()) Adds <?php print $options['joinType'] ?> JOIN on "<?php print $options['relationPath'] ?> <?php print $options['aliasOn'] ?>"
<?php endforeach; ?>
   *
<?php foreach ($this->getCallableDocs() as $inlineOptions): ?>
   * @c(<?php print $inlineOptions ?>)
<?php endforeach; ?>
   *
   */
  abstract class Base<?php print $this->modelName ?>Table extends <?php print $this->getTableToExtendFrom() . PHP_EOL ?>
  {
    /**
     * @return string Base table class name used for late-static-bindings purposes
     */
    protected static function getGenericTableName ()
    {
      return __CLASS__;
    }
  }