<?php

  /*
   * This file is part of the sfDoctrineTablePlugin package.
   * (c) 2012 Ilya Sabelnikov <fruit.dev@gmail.com>
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
    const DEFAULT_TABLE_CLASS = 'Doctrine_Table_Scoped';

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
     * Current model name
     *
     * @var string
     */
    protected $modelName = null;

    /**
     * Current table instance
     *
     * @var Doctrine_Table
     */
    protected $table = null;

    /**
     * List of methods that should appeared in generated base table
     *
     * @var array
     */
    protected $methodDocs = array();

    /**
     * List of inline @c attributes that holds callable method metadata
     *
     * @var array
     */
    protected $callableDocs = array();

    /**
     * List of methods that is generated based on pattern (e.g. "andWhere%sIn")
     *
     * @var array
     */
    protected $generateCustomPHPDoc = array();

    /**
     * Params passed to the generator
     *
     * @var array
     */
    protected $params = array();

    /**
     * List of methods is used in project
     *
     * @var array
     */
    protected $methodsInUse = array();

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

      $this->builderOptions = $this
        ->getDoctrinePluginConfiguration()
        ->getModelBuilderOptions()
      ;

      $this->pluginPaths = $this
        ->getConfiguration()
        ->getAllPluginPaths()
      ;
    }

    /**
     * @return sfApplicationConfiguration
     */
    protected function getConfiguration()
    {
      return $this->generatorManager->getConfiguration();
    }

    /**
     *
     * @return sfDoctrinePluginConfiguration
     */
    protected function getDoctrinePluginConfiguration ()
    {
      return $this
        ->getConfiguration()
        ->getPluginConfiguration('sfDoctrinePlugin')
      ;
    }

    /**
     * Generates classes and templates in cache.
     *
     * @param array The parameters
     *
     * @return string The data to put in configuration cache
     */
    public function generate ($params = array())
    {
      $this->params = array_merge(
        array(
          'depth'       => 2,
          'uninstall'   => false,
          'no-phpdoc'   => false,
          'minify'      => false,
          'models'      => array(),
        ),
        $params
      );

      if ($this->params['minify'])
      {
        $this->methodsInUse = $this->findUsedMethodsInProject();
      }

      $models = $this->params['models'] ?: $this->loadModels();

      // create a form class for every Doctrine class
      foreach ($models as $model)
      {
        $this->modelName  = $model;
        $this->table      = Doctrine_Core::getTable($this->modelName);

        $this->generateCustomPHPDoc = array();

        if ($this->params['uninstall'] || $this->isTableGenerationDisabled())
        {
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

        $baseTableLocation
          = "{$baseDir}/{$this->builderOptions['baseClassesDirectory']}/" .
            "Base{$this->modelName}Table{$this->builderOptions['suffix']}";


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
        $plugins     = $this->getConfiguration()->getPlugins();
        $pluginPaths = $this->getConfiguration()->getAllPluginPaths();

        foreach ($pluginPaths as $pluginName => $path)
        {
          if (! in_array($pluginName, $plugins))
          {
            continue;
          }

          $files = sfFinder::type('file')
            ->name('*.php')
            ->in("{$path}/lib/model/doctrine")
          ;

          foreach ($files as $path)
          {
            $info = pathinfo($path);
            $e = explode('.', $info['filename']);
            $modelName = substr($e[0], 6, strlen($e[0]));

            if (! class_exists($e[0]) || ! class_exists($modelName))
            {
              continue;
            }

            $parent = new ReflectionClass('Doctrine_Record');
            $reflection = new ReflectionClass($modelName);
            if (! $reflection->isSubClassOf($parent))
            {
              continue;
            }

            $this->pluginModels[$modelName] = $pluginName;

            if (! $reflection->isInstantiable())
            {
              continue;
            }

            $generators = Doctrine_Core::getTable($modelName)->getGenerators();
            foreach ($generators as $generator)
            {
              $this->pluginModels[$generator->getOption('className')] = $pluginName;
            }
          }
        }
      }

      return $this->pluginModels;
    }

    /**
     * Checks for a model is a part of plugin
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
     * @return string|bool Plugin name
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
      $parentColumns = $parentModel
        ? array_keys(Doctrine_Core::getTable($parentModel)->getColumns())
        : array();

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
      Doctrine_Core::loadModels($this->getConfiguration()->getModelDirs());
      $models = Doctrine_Core::getLoadedModels();
      $models = Doctrine_Core::initializeModels($models);
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

    /**
     * Looks for base table generation is enabled/disabled
     *
     * @return bool
     */
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
        if (! $r->isAbstract())
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
    public function getTableToExtendFrom()
    {
      $pluginName = $this->getPluginNameForModel($this->modelName);

      /**
       * Plugin model base tables should be extended by it's own base table
       */
      if ($pluginName)
      {
        return "{$this->builderOptions['packagesPrefix']}{$this->modelName}Table";
      }

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

      return Doctrine_Manager::getInstance()->getAttribute(Doctrine_Core::ATTR_TABLE_CLASS);
    }

    /**
     * Transforms array to a line representation
     *
     * @param array $params Assoc array of parameters
     * @return string
     */
    protected function inline ($params)
    {
      $string = '';

      foreach ($params as $k => $v)
      {
        $string .= ($string == '' ? '' : ',') . "{$k}={$v}";
      }

      return $string;
    }

    /**
     * Recursively creates JOINs methods based on model relations hierarchy
     *
     * @param string  $model        Model name to look for relations
     * @param int     $depth        Current depth (from greates to lowest)
     * @param string  $viaModel     Part of method name of join path
     * @param array   $builtJoins   List of already built joins - used for
     *                              escape from cicles and duplicates
     * @param string  $aliasFrom    Alias of model name to create joins from
     * @param string  $alias        New joined model alias name
     *
     * @return null
     */
    protected function buildRelationPhpdocs ($model, $depth, $viaModel = '',
                                             $builtJoins = array(), $aliasFrom = '^',
                                             $alias = '')
    {
      $viaModelMethodPart = '';

      if (! empty($viaModel))
      {
        $viaModelMethodPart = sprintf('Via%s', $viaModel);
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

        $position = 1;

        /**
         * do not dublicate alias inside joins
         */
        do
        {
          $tmpName = ucfirst($relation->getAlias());

          $tmpName[$position - 1] = strtoupper($tmpName[$position - 1]);

          if (! $relation->isOneToOne() && $relationAlias != 'Translation')
          {
            $tmpName .= 'S';
          }

          $aliasOn = $alias . strtolower(preg_replace('/[a-z]/', '', $tmpName));

          $position ++;
        }
        while (array_key_exists($aliasOn, $levelAliases));

        $levelAliases[$aliasOn] = true;

        /**
         * Do not use $table->hasTemplate('I18n') to check whether it's time
         * to generate translation joins - produces invalid aliases when has
         * many i18n-relations. Hard to catch.
         */
        if ('Translation' == $relation->getAlias())
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

          $relationPath = ltrim(
            str_replace('And', '.', substr($viaModelMethodPart, 3)) . '.Translation',
            '.'
          );

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
            ('^' == $aliasFrom)
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

        $relationPath = ltrim(
          str_replace('And', '.', substr($viaModelMethodPart, 3)) . ".{$methodPart}",
          '.'
        );

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

        $localKey = "{$model}-{$relation->getLocal()}";
        $foreignKey = "{$relation->getClass()}-{$relation->getForeign()}";

        // check new relation was never used before
        // relation could be from any direction (forwards or backwards entering)
        if (
           (
              isset($builtJoins[$localKey])
            &&
              $builtJoins[$localKey] == $foreignKey
           )
         ||
           (
              isset($builtJoins[$foreignKey])
            &&
              $builtJoins[$foreignKey] == $localKey
           )
        )
        {
          continue;
        }

        // do not generate joins further if that is non-physical table
        // (e.g. Translation)
        if ($relation->getTable()->isGenerator())
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
            array_merge($builtJoins, array($localKey => $foreignKey)),
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

      if ($this->isNoPhpDoc())
      {
        return array();
      }

      $listOfMethods = array_combine(
        array_map(
          function($columnName) use ($pattern, $columns) {
            return sprintf($pattern, sfInflector::camelize($columnName));
          },
          $columns
        ),
        $columns
      );

      if ($this->isMinify())
      {
        $listOfMethods = array_intersect_key($listOfMethods, $this->methodsInUse);
      }

      return array_flip($listOfMethods);
    }

    /**
     * List of methods and its parameters that was generated based on pattern
     *
     * @return array
     */
    public function getCallableDocs ()
    {
      $result = array();

      if ($this->params['minify'])
      {
        $this->generateCustomPHPDoc = array_intersect_key(
          $this->generateCustomPHPDoc, $this->methodsInUse
        );
      }

      foreach ($this->generateCustomPHPDoc as $method => $isUsed)
      {
        $result[$method] = $this->callableDocs[$method];
      }

      return $result;
    }

    /**
     * List of generated methods by category
     *
     * @param string $category
     * @return array
     */
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

      if ($this->isNoPhpDoc())
      {
        return array();
      }

      if ($this->isMinify())
      {
        return array_intersect_key($this->methodDocs[$category], $this->methodsInUse);
      }

      return $this->methodDocs[$category];
    }

    /**
     * Current project Doctrine_Collection class (may be extended)
     *
     * @return string
     */
    public function getCollectionClass ()
    {
      return Doctrine_Manager::getInstance()->getAttribute(Doctrine::ATTR_COLLECTION_CLASS);
    }

    /**
     * Whether generator is configured with parameter "no-phpdoc"
     *
     * @return bool
     */
    public function isNoPhpDoc ()
    {
      return $this->params['no-phpdoc'];
    }

    /**
     * Whether generated files should be maxismaly  of unused methods in project.
     *
     * @return bool
     */
    public function isMinify ()
    {
      return $this->params['minify'];
    }

    /**
     * Process base table uninstallation
     *
     * @return null
     */
    protected function uninstallTable ()
    {
      $baseDir = sfConfig::get('sf_lib_dir') . '/model/doctrine';
      $customTableClass = Doctrine_Manager::getInstance()->getAttribute(Doctrine_Core::ATTR_TABLE_CLASS);

      if (! $this->isPluginModel($this->modelName))
      {
        /**
         * 1 file to update
         *
         *   #1 - lib/model/doctrine/MyModuleTable.class.php
         */

        $tableLocation = "{$baseDir}/{$this->modelName}Table{$this->builderOptions['suffix']}";

        if (! is_file($tableLocation) || ! is_writable($tableLocation))
        {
          return;
        }

        $tableContent = file_get_contents($tableLocation);

        $count = null;

        /**
         * If previously "extends" class was not Doctrine_Table?
         * Leave Doctrine_Table, in README is described uninstallation process
         * (build-models should be executed)
         */
        $tableContent = preg_replace(
          "/class(\s+){$this->modelName}Table(\s+)extends(\s+)Base{$this->modelName}Table/ms",
          "class\\1{$this->modelName}Table\\2extends\\3{$this->getClassNameToExtendFromAfterUninstalling()}",
          $tableContent, 1, $count
        );

        if ($count)
        {
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

        if (is_file($pluginTableLocation) && is_writable($pluginTableLocation))
        {
          $pluginTableContent = file_get_contents($pluginTableLocation);

          $count = null;

          $pluginTableContent = preg_replace(
            "/class(\s+){$this->builderOptions['packagesPrefix']}{$this->modelName}Table(\s+)extends(\s+){$customTableClass}/ms",
            "class\\1{$this->builderOptions['packagesPrefix']}{$this->modelName}Table\\2extends\\3{$this->getClassNameToExtendFromAfterUninstalling()}",
            $pluginTableContent, 1, $count
          );

          if ($count)
          {
            file_put_contents($pluginTableLocation, $pluginTableContent);
          }
        }

        /**
         * File #2 - default table class
         *
         * Text to replace: "class sfGuardUserTable extends BasesfGuardUserTable"
         *            with: "class sfGuardUserTable extends PluginsfGuardUserTable"
         */
        $defaultTableLocation =
          "{$baseDir}/{$pluginName}/{$this->modelName}" .
          "Table{$this->builderOptions['suffix']}";

        $defaultTableContent = file_get_contents($defaultTableLocation);

        if (is_file($defaultTableLocation) && is_writable($defaultTableLocation))
        {
          $count = null;

          $defaultTableContent = preg_replace(
            "/class(\s+){$this->modelName}Table(\s+)extends(\s+)Base{$this->modelName}Table/ms",
            "class\\1{$this->modelName}Table\\2extends\\3{$this->builderOptions['packagesPrefix']}{$this->modelName}Table",
            $defaultTableContent, 1, $count
          );

          if ($count)
          {
            file_put_contents($defaultTableLocation, $defaultTableContent);
          }
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
        unlink($baseTableLocation);
      }
    }

    /**
     * Process base table installation
     *
     * @return null
     */
    protected function installTable ()
    {
      $baseDir = sfConfig::get('sf_lib_dir') . '/model/doctrine';

      if (! $this->isPluginModel($this->modelName))
      {
        /**
         * There is only 1 file we should change
         *
         *  1. lib/model/doctrine/%model_name%Table.class.php
         */
        $tableLocation = "{$baseDir}/{$this->modelName}Table{$this->builderOptions['suffix']}";

        if (is_file($tableLocation) && is_writable($tableLocation))
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
          }

          file_put_contents($tableLocation, $tableClassContent);
        }
      }
      else
      {
        /**
         * There are 2 files we need to update
         *
         * 1. plugins/%plugin_name%/lib/model/doctrine/plugin/Plugin%model_name%Table.class.php
         * 2. lib/model/doctrine/%model_name%Table.class.php
         *
         *
         *   File #1 contains class "Doctrine_Table",
         *   it should be replaced with Doctrine_Manager::getInstance()->getAttribute(Doctrine_Core::ATTR_TABLE_CLASS)
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
        $pluginTableLocation =
          sfConfig::get('sf_plugins_dir') .
          "/{$pluginName}/lib/model/doctrine/" .
          "{$this->builderOptions['packagesPrefix']}{$this->modelName}" .
          "Table{$this->builderOptions['suffix']}";

        if (is_file($pluginTableLocation) && is_writable($pluginTableLocation))
        {
          $pluginTableContent = file_get_contents($pluginTableLocation);

          /**
           * Hard to find extends class - it could be custom, or Doctrine_Table
           */

          $defaultTableClass = Doctrine_Manager::getInstance()->getAttribute(Doctrine_Core::ATTR_TABLE_CLASS);

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

          if ($count)
          {
            file_put_contents($pluginTableLocation, $pluginTableContent);
          }
        }

        /**
         * File #2
         */
        $baseTableLocation = "{$baseDir}/{$pluginName}/{$this->modelName}Table{$this->builderOptions['suffix']}";
        if (is_file($baseTableLocation) && is_writable($baseTableLocation))
        {
          $baseTableContent = file_get_contents($baseTableLocation);

          /**
           * replace invalid PHPDoc with correct
           * from:
           *    [at]return object sfGuardUserTable
           * to:
           *    [at]return sfGuardUserTable
           */

          if (preg_match("/\@return\s+object\s+{$this->modelName}Table/ms", $baseTableContent))
          {
            $baseTableContent = preg_replace(
              "/\@return(\s+)object(\s+){$this->modelName}Table/ms",
              "@return\\1{$this->modelName}Table",
              $baseTableContent,
              1,
              $count
            );
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
          }

          file_put_contents($baseTableLocation, $baseTableContent);
        }
      }
    }

    /**
     * Return right class name to extend when uninstalling base tables
     *
     * Uninstalling table class could be other than default class.
     * It happens when model has custom inheritance or project uses own
     * Doctrine_Table class.
     *
     * @return string
     */
    protected function getClassNameToExtendFromAfterUninstalling ()
    {
      if (false !== ($pluginName = $this->getPluginNameForModel($this->modelName)))
      {
        return 'Doctrine_Table';
      }

      if (null === ($parentInheritedModelName = $this->getParentModel()))
      {
        $inheritanceClass = 'Doctrine_Table';
      }
      else
      {
        $inheritanceClass = "{$parentInheritedModelName}Table";
      }

      return $inheritanceClass;
    }

    /**
     * Search through entire project and look for used methods generated in base tables
     *
     * @return array
     */
    protected function findUsedMethodsInProject ()
    {
      $files = sfFinder::type('file')
        ->ignore_version_control()
        ->prune(sfConfig::get('app_sf_doctrine_table_plugin_finder_prune_folders'))
        ->discard(sfConfig::get('app_sf_doctrine_table_plugin_finder_discard_folders'))
        ->name(sfConfig::get('app_sf_doctrine_table_plugin_finder_name'))
        ->not_name(sfConfig::get('app_sf_doctrine_table_plugin_finder_not_name'))
        ->in(sfConfig::get('app_sf_doctrine_table_plugin_finder_search_in'))
      ;

      $re = '/
              [^\w] # First character should be non-literal
              (
                  with(?:Inner|Left)JoinOn\w+   # Collect JOINs methods
                |
                  (?:or|and)Where\w+            # Collect AND,OR methods
                |
                  addSelect\w+                  # Collect COUNT methods
              )
              (?:|[^\w]) # Last character can be EOL or non-literal
            /xi'; // I midifier - methods in PHP are case-insensitive

      $methodsInUse = array();
      foreach ($files as $file)
      {
        if (! is_readable($file))
        {
          continue;
        }

        $matches = array();
        if (! preg_match_all($re, file_get_contents($file), $matches))
        {
          continue;
        }

        $methodsInUse = array_merge($methodsInUse, array_flip($matches[1]));
      }

      return $methodsInUse;
    }
  }
