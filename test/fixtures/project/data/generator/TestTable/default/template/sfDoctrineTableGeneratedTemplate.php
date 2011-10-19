[?php

  /**
   * @author Jake Green <jake.green@revolver.film>
   *
<?php foreach ($this->getPHPDocByPattern('findOnlyOneByColumn%s', 'buildFindOneByColumn') as $column => $method): ?>
   * @method mixed <?php print $method ?>() <?php print $method ?>(string $value) Find one "<?php print $column ?>" row by value
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
     * @return string
     */
    protected static function getGenericTableName ()
    {
      return __CLASS__;
    }
  }