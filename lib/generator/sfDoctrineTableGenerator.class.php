<?php

  /*
   * This file is part of the sfDoctrineTablePlugin package.
   * (c) 2011 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  /**
   * Doctrine form generator.
   *
   * This class generates a Doctrine tables.
   *
   * @package    symfony
   * @subpackage generator
   * @author     Ilya Sabelnikov <fruit.dev@gmail.com>
   */
  class sfDoctrineTableGenerator extends sfGenerator
  {
    /**
     * Array of all the loaded models
     *
     * @var array
     */
    protected $models = array();

    /**
     * Array of all plugin models
     *
     * @var array
     */
    protected $pluginModels = array();

    /**
     * @var string
     */
    protected $modelName = null;

    protected $modelBuilder = '';

    protected $methodDocs = array();

    protected $callableDocs = array();

    protected $modelLevels = 0;

    protected $modelAliases = array();

    protected $build = array();

    protected $generateCustomPHPDoc = array();


    public $params = array();

    /**
     * @var Doctrine_Table
     */
    protected $table = null;

    /**
     * Initializes the current sfGenerator instance.
     *
     * @param sfGeneratorManager A sfGeneratorManager instance
     */
    public function initialize(sfGeneratorManager $generatorManager)
    {
      parent::initialize($generatorManager);

      $this->getPluginModels();
      $this->setGeneratorClass('sfDoctrineTable');

      $this->builderOptions = $this->getDoctrinePluginConfiguration()->getModelBuilderOptions();

      $this->pluginPaths = $this->generatorManager->getConfiguration()->getAllPluginPaths();
    }

    /**
     *
     * @return sfDoctrinePluginConfiguration
     */
    protected function getDoctrinePluginConfiguration ()
    {
      return $this->generatorManager->getConfiguration()->getPluginConfiguration('sfDoctrinePlugin');
    }

    /**
     * Generates classes and templates in cache.
     *
     * @param array The parameters
     *
     * @return string The data to put in configuration cache
     */
    public function generate($params = array())
    {
      $this->params = array_merge(
        array(
          'depth'     => 2,
          'uninstall' => false,
          'minified'  => false,
        ),
        $params
      );

      // create a form class for every Doctrine class
      foreach ($this->loadModels() as $model)
      {
        $this->modelName  = $model;
        $this->table      = Doctrine_Core::getTable($this->modelName);
        $this->tableName  = $this->table->getTableName();

        $this->generateCustomPHPDoc = array();

        if ($this->params['uninstall'] || $this->isTableGenerationDisabled())
        {
          #print "Model {$model} table generation is disabled\n";
          $this->uninstallTable();

          continue;
        }

        $baseDir = sfConfig::get('sf_lib_dir') . '/model/doctrine';

        $isPluginModel = $this->isPluginModel($model);

        if ($isPluginModel)
        {
          $pluginName = $this->getPluginNameForModel($model);
          $baseDir .= '/' . $pluginName;
        }

        $baseTableLocation = "{$baseDir}/{$this->builderOptions['baseClassesDirectory']}/Base{$this->modelName}Table{$this->builderOptions['suffix']}";


        $this->methodDocs = array();
        $this->callableDocs = array();

        $this->buildRelationPhpdocs($model, $this->params['depth']);

        $data = $this->evalTemplate('sfDoctrineTableGeneratedTemplate.php');

        file_put_contents($baseTableLocation, $data);

        $this->installTable();
      }
    }

    /**
     * @see sfDoctrineFormGenerator
     */
    public function getPluginModels()
    {
      if (! $this->pluginModels)
      {
        $plugins     = $this->generatorManager->getConfiguration()->getPlugins();
        $pluginPaths = $this->generatorManager->getConfiguration()->getAllPluginPaths();

        foreach ($pluginPaths as $pluginName => $path)
        {
          if (! in_array($pluginName, $plugins))
          {
            continue;
          }

          foreach (sfFinder::type('file')->name('*.php')->in($path.'/lib/model/doctrine') as $path)
          {
            $info = pathinfo($path);
            $e = explode('.', $info['filename']);
            $modelName = substr($e[0], 6, strlen($e[0]));

            if (class_exists($e[0]) && class_exists($modelName))
            {
              $parent = new ReflectionClass('Doctrine_Record');
              $reflection = new ReflectionClass($modelName);
              if ($reflection->isSubClassOf($parent))
              {
                $this->pluginModels[$modelName] = $pluginName;

                if ($reflection->isInstantiable())
                {
                  $generators = Doctrine_Core::getTable($modelName)->getGenerators();
                  foreach ($generators as $generator)
                  {
                    $this->pluginModels[$generator->getOption('className')] = $pluginName;
                  }
                }
              }
            }
          }
        }
      }

      return $this->pluginModels;
    }

    /**
     * Check to see if a model is part of a plugin
     *
     * @param string $modelName
     *
     * @return boolean
     */
    public function isPluginModel($modelName)
    {
      return isset($this->pluginModels[$modelName]);
    }

    /**
     * Get the name of the plugin a model belongs to
     *
     * @param string $modelName
     *
     * @return string Plugin name
     */
    public function getPluginNameForModel($modelName)
    {
      if ($this->isPluginModel($modelName))
      {
        return $this->pluginModels[$modelName];
      }
      else
      {
        return false;
      }
    }

    /**
     * @see sfDoctrineFormGenerator
     */
    public function getColumns()
    {
      $parentModel = $this->getParentModel();
      $parentColumns = $parentModel ? array_keys(Doctrine_Core::getTable($parentModel)->getColumns()) : array();

      $columns = array();
      foreach (array_diff(array_keys($this->table->getColumns()), $parentColumns) as $name)
      {
        $columns[] = new sfDoctrineColumn($name, $this->table);
      }

      return $columns;
    }

    /**
     * Loads all Doctrine builders.
     *
     * @return array
     */
    protected function loadModels()
    {
      Doctrine_Core::loadModels($this->generatorManager->getConfiguration()->getModelDirs());
      $models = Doctrine_Core::getLoadedModels();
      $models =  Doctrine_Core::initializeModels($models);
      $models = Doctrine_Core::filterInvalidModels($models);
      $this->models = $this->filterModels($models);

      return $this->models;
    }

    /**
     * Filter out models that have disabled generation of form classes
     *
     * @return array $models Array of models to generate forms for
     */
    protected function filterModels($models)
    {
      foreach ($models as $key => $model)
      {
        /**
         * Skip Translation tables
         */
        if (Doctrine_Core::getTable($model)->isGenerator())
        {
          unset($models[$key]);

          continue;
        }
      }

      return $models;
    }

    protected function isTableGenerationDisabled ()
    {
      $symfonyOptions = (array) $this->table->getOption('symfony');

      return isset($symfonyOptions['table']) && ! $symfonyOptions['table'];
    }

    /**
     * @see sfDoctrineFormGenerator
     */
    public function getParentModel()
    {
      $baseClasses = array(
        'Doctrine_Record',
        'sfDoctrineRecord',
      );

      if (isset($this->builderOptions['baseClassName']))
      {
        $baseClasses[] = $this->builderOptions['baseClassName'];
      }

      // find the first non-abstract parent
      $model = $this->modelName;
      while ($model = get_parent_class($model))
      {
        if (in_array($model, $baseClasses))
        {
          break;
        }

        $r = new ReflectionClass($model);
        if (!$r->isAbstract())
        {
          return $r->getName();
        }
      }
    }

    /**
     * Get the name of the form class to extend based on the inheritance of the model
     *
     * @return string
     */
    public function getFormClassToExtend()
    {
      $baseClasses = array(
        'Doctrine_Record',
        'sfDoctrineRecord',
      );

      if (isset($this->builderOptions['baseClassName']))
      {
        $baseClasses[] = $this->builderOptions['baseClassName'];
      }

      $model = $this->modelName;

      while ($parent = get_parent_class($model))
      {
        if (in_array($parent, $baseClasses))
        {
          break;
        }

        $r = new ReflectionClass($parent);

        if (! $r->isAbstract())
        {
          return "{$parent}Table";
        }

        $model = $parent;
      }

      return sfConfig::get('app_sf_doctrine_table_plugin_custom_table_class');
    }

    protected function inline ($params)
    {
      $string = '';

      foreach ($params as $k => $v)
      {
        $string .= ($string == '' ? '' : ',') . "{$k}={$v}";
      }

      return $string;
    }

    protected function buildRelationPhpdocs ($model, $depth, $viaModel = '', $aliasFrom = '^', $alias = '')
    {
      $viaModelMethodPart = '';

      if (! empty($viaModel))
      {
        $viaModelMethodPart = sprintf('Via%s', $viaModel);
      }
      else
      {
        $this->modelBuilder = $model;
        $this->modelLevels = $depth;
        $this->build = array();
      }

      if (! empty($alias))
      {
        $alias .= '_';
      }

      $levelAliases = array();

      /* @var $relation Doctrine_Relation */

      $table = Doctrine_Core::getTable($model);

      $relations = $table->getRelations();

      foreach ($relations as $relationAlias => $relation)
      {
        $methodPart = sfInflector::camelize($relation->getAlias());

        $firstChars = 1;

        /**
         * do not dublicate alias inside joins
         */
        do
        {
          $relationName = ucfirst($relation->getAlias());

          $relationName[$firstChars - 1] = strtoupper($relationName[$firstChars - 1]);

          if (! $relation->isOneToOne() && $relationAlias != 'Translation')
          {
            $relationName .= 'S';
          }

          $aliasOn = $alias . strtolower(preg_replace('/[a-z]/', '', $relationName));

          $firstChars ++;
        }
        while (array_key_exists($aliasOn, $levelAliases));

        $levelAliases[$aliasOn] = true;

        if ($table->hasTemplate('I18n'))
        {
          $this->callableDocs[$m] = $this->inline(array(
            'm' => $m = sprintf('withLeftJoinOnTranslation%s', $viaModelMethodPart),
            'o' => $aliasOn,
            'f' => $aliasFrom,
            'ra' => $relation->getAlias(),
            'c' => 'buildLeftI18n',
          ));

          $this->callableDocs[$m] = $this->inline(array(
            'm' => $m = sprintf('withInnerJoinOnTranslation%s', $viaModelMethodPart),
            'o' => $aliasOn,
            'f' => $aliasFrom,
            'ra' => $relation->getAlias(),
            'c' => 'buildInnerI18n',
          ));

          $relationPath = ltrim(str_replace('And', '.', substr($viaModelMethodPart, 3)) . '.Translation', '.');

          $this->methodDocs['translation_joins']["withLeftJoinOnTranslation{$viaModelMethodPart}"] = array(
            'aliasOn' => $aliasOn,
            'joinType' => 'LEFT',
            'relationPath' => $relationPath,
          );

          $this->methodDocs['translation_joins']["withInnerJoinOnTranslation{$viaModelMethodPart}"] = array(
            'aliasOn' => $aliasOn,
            'joinType' => 'INNER',
            'relationPath' => $relationPath,
          );

          if ($relationAlias == 'Translation')
          {
            $aliasOn .= 's';
            $methodPart .= 's';
          }
        }
        elseif (
            ! $relation->isOneToOne()
          &&
            ($this->modelLevels == $depth)
          &&
            null == $relation->offsetGet('refTable')
        )
        {
          $getDql = array(
            'o' => "{$aliasOn}_cnt",
            'f' => '^',
            'ra' => $relation->getAlias(),
            'rf' => $relation->getForeign(),
            'rl' => $relation->getLocal(),
            'rc' => $relation->getClass(),
            'ca' => sprintf('%s_count', sfInflector::tableize($relation->getAlias())),
          );

          if ($relation->getTable()->hasTemplate('SoftDelete'))
          {
            $getDql['s'] = $relation->getTable()->getTemplate('SoftDelete')->getOption('name');
          }

          /**
           * Joins
           */
          $this->callableDocs[$m] = $this->inline(
            array_merge(
              array(
                'm' => $m = "addSelect{$methodPart}CountAsJoin",
                'c' => 'buildAddSelectCountAsJoin',
              ),
              $getDql
            )
          );

          $this->methodDocs['add_counts_join'][$m] = array(
            'relationAlias'   => $getDql['o'],
            'countFieldName'  => $getDql['ca'],
            'relationName'    => $getDql['ra'],
            'relationColumn'  => $getDql['rf'],
          );

          /**
           * Sub-Select
           */
          $this->callableDocs[$m] = $this->inline(
            array_merge(
              array(
                'm' => $m = "addSelect{$methodPart}CountAsSubSelect",
                'c' => 'buildAddSelectCountAsSubSelect',
              ),
              $getDql
            )
          );

          $this->methodDocs['add_counts_subselect'][$m] = array(
            'relationAlias'   => $getDql['o'],
            'countFieldName'  => $getDql['ca'],
            'relationName'    => $getDql['ra'],
            'relationColumn'  => $getDql['rf'],
          );
        }

        $this->callableDocs[$m] = $this->inline(array(
          'm' => $m = "withLeftJoinOn{$methodPart}{$viaModelMethodPart}",
          'o' => $aliasOn,
          'f' => $aliasFrom,
          'ra' => $relation->getAlias(),
          'c' => 'buildLeft',
        ));

        $this->callableDocs[$m] = $this->inline(array(
          'm' => $m = "withInnerJoinOn{$methodPart}{$viaModelMethodPart}",
          'o' => $aliasOn,
          'f' => $aliasFrom,
          'ra' => $relation->getAlias(),
          'c' => 'buildInner',
        ));

        $relationPath = ltrim(str_replace('And', '.', substr($viaModelMethodPart, 3)) . ".{$methodPart}", '.');

        $this->methodDocs['joins']["withLeftJoinOn{$methodPart}{$viaModelMethodPart}"] = array(
          'aliasOn'       => $aliasOn,
          'relationPath'  => $relationPath,
          'joinType'      => 'LEFT',
        );

        $this->methodDocs['joins']["withInnerJoinOn{$methodPart}{$viaModelMethodPart}"] = array(
          'aliasOn'       => $aliasOn,
          'relationPath'  => $relationPath,
          'joinType'      => 'INNER',
        );

        # do not generate a ciclic joins
        if ($this->modelBuilder === $relation->getClass())
        {
          continue;
        }

        # do not generate joins further than Translation table
        if ('Translation' == $relationName)
        {
          continue;
        }

        if (0 != $depth)
        {
          -- $depth;

          $this->buildRelationPhpdocs(
            $relation->getTable()->getClassnameToReturn(),
            $depth,
            empty($viaModel)
              ? $methodPart
              : sprintf('%sAnd%s', $viaModel, $methodPart),
            $aliasOn,
            $aliasOn
          );

          ++ $depth;
        }
      }
    }

    /**
     * Register method by pattern for each table column
     *
     * @param string $pattern
     * @param string $buildMethod
     * @return array
     */
    public function getPHPDocByPattern ($pattern, $buildMethod = null)
    {
      $columns = $this->table->getColumnNames();

      if (null !== $buildMethod)
      {
        foreach ($columns as $columnName)
        {
          $m = sprintf($pattern, sfInflector::camelize($columnName));

          $this->callableDocs[$m] = $this->inline(array(
            'm' => $m,
            'n' => $columnName,
            'c' => $buildMethod,
          ));

          $this->generateCustomPHPDoc[$m] = true;
        }
      }

      if ($this->isMinified())
      {
        return array();
      }

      return array_combine(
        $columns,
        array_map(
          function($columnName) use ($pattern, $columns) {
            return sprintf($pattern, sfInflector::camelize($columnName));
          },
          $columns
        )
      );
    }

    public function getCallableDocs ()
    {
      $result = array();

      foreach ($this->generateCustomPHPDoc as $method => $isUsed)
      {
        $result[$method] = $this->callableDocs[$method];
      }

      return $result;
    }

    public function getPHPDocByCategory ($category)
    {
      if (! isset($this->methodDocs[$category]))
      {
        return array();
      }

      foreach ($this->methodDocs[$category] as $method => $params)
      {
        $this->generateCustomPHPDoc[$method] = true;
      }

      if ($this->isMinified())
      {
        return array();
      }

      return $this->methodDocs[$category];
    }

    public function getCollectionClass ()
    {
      return Doctrine_Manager::getInstance()->getAttribute(Doctrine::ATTR_COLLECTION_CLASS);
    }

    public function isMinified ()
    {
      return isset($this->params['minified']) && $this->params['minified'];
    }

    protected function uninstallTable ()
    {
      /**
       * @todo when package is set
       */
      $baseDir = sfConfig::get('sf_lib_dir') . '/model/doctrine';

      if (! $this->isPluginModel($this->modelName))
      {
        /**
         * 1 file to update
         *
         *   #1 - lib/model/doctrine/MyModuleTable.class.php
         */

        $tableLocation = "{$baseDir}/{$this->modelName}Table{$this->builderOptions['suffix']}";

        if (! is_file($tableLocation))
        {
          #print "File {$tableLocation} does not exists";
          return;
        }

        $tableContent = file_get_contents($tableLocation);

        $count = null;

        /**
         * @todo What if previous "extends" class was not Doctrine_Table?
         * (could be in the real world)
         */
        $tableContent = preg_replace(
          "/class(\s+){$this->modelName}Table(\s+)extends(\s+)Base{$this->modelName}Table/ms",
          "class\\1{$this->modelName}Table\\2extends\\3Doctrine_Table",
          $tableContent, 1, $count
        );

        if ($count)
        {
          #print sprintf("Restoring previos %sTable extends clause.\n", $this->modelName);

          file_put_contents($tableLocation, $tableContent);
        }
      }
      else
      {
        /**
         * There are two files to update
         */

        $pluginName = $this->getPluginNameForModel($this->modelName);

        /**
         * File #1 - plugins table
         *
         * Text to replace: class PluginsfGuardUserTable extends Doctrine_Table_Scoped
         *            with: class PluginsfGuardUserTable extends Doctrine_Table
         */
        $pluginTableLocation = sfConfig::get('sf_plugins_dir') . "/{$pluginName}/lib/model/doctrine/{$this->builderOptions['packagesPrefix']}{$this->modelName}Table{$this->builderOptions['suffix']}";

        if (is_file($pluginTableLocation))
        {
          $customTableClass = sfConfig::get('app_sf_doctrine_table_plugin_custom_table_class');

          $pluginTableContent = file_get_contents($pluginTableLocation);

          $count = null;

          $pluginTableContent = preg_replace(
            "/class(\s+){$this->builderOptions['packagesPrefix']}{$this->modelName}Table(\s+)extends(\s+){$customTableClass}/ms",
            "class\\1{$this->builderOptions['packagesPrefix']}{$this->modelName}Table\\2extends\\3Doctrine_Table",
            $pluginTableContent, 1, $count
          );

          if ($count)
          {
            #print sprintf("Restoring previos %sTable extends clause. (PACKAGE/plugin table)\n", $this->modelName);

            file_put_contents($pluginTableLocation, $pluginTableContent);
          }
        }
        else
        {
          #print "file {$pluginTableLocation} not found\n";
        }

        /**
         * File #2 - default table class
         *
         * Text to replace: "class sfGuardUserTable extends BasesfGuardUserTable"
         *            with: "class sfGuardUserTable extends PluginsfGuardUserTable"
         */
        $defaultTableLocation = "{$baseDir}/{$pluginName}/{$this->modelName}Table{$this->builderOptions['suffix']}";
        $defaultTableContent = file_get_contents($defaultTableLocation);

        if (is_file($defaultTableLocation))
        {
          $count = null;

          /**
           * @todo What if previous "extends" class was not Doctrine_Table?
           * (could be in the real world)
           */
          $defaultTableContent = preg_replace(
            "/class(\s+){$this->modelName}Table(\s+)extends(\s+)Base{$this->modelName}Table/ms",
            "class\\1{$this->modelName}Table\\2extends\\3{$this->builderOptions['packagesPrefix']}{$this->modelName}Table",
            $defaultTableContent, 1, $count
          );

          if ($count)
          {
            #print "Restoring previos {$this->modelName}Table extends clause. (PACKAGE/default table)\n";

            file_put_contents($defaultTableLocation, $defaultTableContent);
          }
        }
        else
        {
          #print "file not found {$defaultTableLocation}\n";
        }

      }

      /**
       * if file "base-table" was created before, remove it
       */

      $baseTableLocation = sprintf(
        '%s%s/%s/Base%sTable%s',
        $baseDir,
        $this->isPluginModel($this->modelName)
          ? '/' . $this->getPluginNameForModel($this->modelName)
          : '',
        $this->builderOptions['baseClassesDirectory'],
        $this->modelName,
        $this->builderOptions['suffix']
      );

      if (is_file($baseTableLocation))
      {
        #print "Removing previosly created base table class for model {$this->modelName}.\n";

        unlink($baseTableLocation);
      }
    }

    protected function installTable ()
    {
      $baseDir = sfConfig::get('sf_lib_dir') . '/model/doctrine';

      if (! $this->isPluginModel($this->modelName))
      {
        /**
         * @todo There is only 1 file we should change
         *
         *  1. lib/model/doctrine/%model_name%Table.class.php
         */
        $tableLocation = "{$baseDir}/{$this->modelName}Table{$this->builderOptions['suffix']}";

        if (is_file($tableLocation))
        {
          $tableClassContent = file_get_contents($tableLocation);

          /**
           * replace invalid PHPDoc with correct
           * from:
           *    @return object PostTable
           * to:
           *    @return PostTable
           */

          if (preg_match("/@return\s+object\s+{$this->modelName}Table\s/ms", $tableClassContent))
          {
            $tableClassContent = preg_replace("/@return(\s+)object(\s+){$this->modelName}Table/ms", "@return\\1{$this->modelName}Table", $tableClassContent, 1, $count);
          }

          if (! preg_match("/class\s+{$this->modelName}Table\s+extends\s+Base{$this->modelName}Table/ms", $tableClassContent))
          {
            $count = null;

            /**
             * Keep code formatting
             */
            $tableClassContent = preg_replace(
              "/class(\s+){$this->modelName}Table(\s+)extends(\s+)\w+/ms",
              "class\\1{$this->modelName}Table\\2extends\\3Base{$this->modelName}Table",
              $tableClassContent, 1, $count
            );

            if (! $count)
            {
              #print sprintf("Something happened wrong in %s (NON-PACKAGE class)\n", $tableLocation);
            }
          }

          file_put_contents($tableLocation, $tableClassContent);
        }
      }
      else
      {
        /**
         * @todo There are 2 files we need to update
         *
         * 1. plugins/%plugin_name%/lib/model/doctrine/plugin/Plugin%model_name%Table.class.php
         * 2. lib/model/doctrine/%model_name%Table.class.php
         *
         *
         *   File #1 contains class "Doctrine_Table",
         *   it should be replaced with sfConfig::get('app_sf_doctrine_table_plugin_custom_table_class')
         *
         *   File #2 contains something like: "class sfGuardUserTable extends PluginsfGuardUserTable"
         *   It should be replaced with:
         *      "class sfGuardUserTable extends BasesfGuardUserTable"
         *
         */

        $pluginName = $this->getPluginNameForModel($this->modelName);

        /**
         * File #1
         */
        $pluginTableLocation = sfConfig::get('sf_plugins_dir') . "/{$pluginName}/lib/model/doctrine/{$this->builderOptions['packagesPrefix']}{$this->modelName}Table{$this->builderOptions['suffix']}";

        if (is_file($pluginTableLocation))
        {
          $pluginTableContent = file_get_contents($pluginTableLocation);

          /**
           * Hard to find extends class - it could be custom, or Doctrine_Table
           */

          $defaultTableClass = sfConfig::get('app_sf_doctrine_table_plugin_custom_table_class');
          /**
           * Keep code formatting
           *
           * And I know, it's not good to change plugins files :)
           */
          $count = null;

          $pluginTableContent = preg_replace(
            "/class(\s+){$this->builderOptions['packagesPrefix']}{$this->modelName}Table(\s+)extends(\s+)\w+/ms",
            "class\\1{$this->builderOptions['packagesPrefix']}{$this->modelName}Table\\2extends\\3{$defaultTableClass}",
            $pluginTableContent, 1, $count
          );

          if (! $count)
          {
            #print sprintf("Something happened wrong in %s (PACKAGE class)\n", $pluginTableLocation);
          }
          else
          {
            file_put_contents($pluginTableLocation, $pluginTableContent);
            #print "+update {$pluginTableLocation}\n";
          }
        }

        /**
         * File #2
         */

        $baseTableLocation = "{$baseDir}/{$pluginName}/{$this->modelName}Table{$this->builderOptions['suffix']}";
        if (is_file($baseTableLocation))
        {
          $baseTableContent = file_get_contents($baseTableLocation);

          /**
           * replace invalid PHPDoc with correct
           * from:
           *    @return object sfGuardUserTable
           * to:
           *    @return sfGuardUserTable
           */

          if (preg_match("/\@return\s+object\s+{$this->modelName}Table/ms", $baseTableContent))
          {
            $baseTableContent = preg_replace("/\@return(\s+)object(\s+){$this->modelName}Table/ms", "@return\\1{$this->modelName}Table", $baseTableContent, 1, $count);
          }

          if (! preg_match("/class\s+{$this->modelName}Table\s+extends\s+Base{$this->modelName}Table/ms", $baseTableContent))
          {
            $count = null;

            /**
             * Keep code formatting
             */
            $baseTableContent = preg_replace(
              "/class(\s+){$this->modelName}Table(\s+)extends(\s+){$this->builderOptions['packagesPrefix']}{$this->modelName}Table/ms",
              "class\\1{$this->modelName}Table\\2extends\\3Base{$this->modelName}Table",
              $baseTableContent, 1, $count
            );

            if (! $count)
            {
              #print sprintf("Something happened wrong in %s (PACKAGE class for default table model)\n", $baseTableLocation);
            }
          }

          file_put_contents($baseTableLocation, $baseTableContent);
        }
      }
    }
  }
