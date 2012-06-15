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
     * @var sfLogger
     */
    protected $logger;

    /**
     * List of files to remove after install/uninstall in case all goes well
     *
     * @var array
     */
    protected $tempFiles = array();

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
        ->getConfiguration()
        ->getPluginConfiguration('sfDoctrinePlugin')
        ->getModelBuilderOptions()
      ;

      $this->pluginPaths = $this
        ->getConfiguration()
        ->getAllPluginPaths()
      ;

      $this->logger = sfContext::getInstance()->getLogger();
    }

    /**
     * @return sfLogger
     */
    protected function getLogger ()
    {
      return $this->logger;
    }

    /**
     * @return sfApplicationConfiguration
     */
    protected function getConfiguration()
    {
      return $this->generatorManager->getConfiguration();
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
        array('depth' => 2, 'uninstall' => false, 'no-phpdoc' => false, 'minify' => false, 'models' => array()),
        $params
      );

      if ($this->params['minify'])
      {
        $this->methodsInUse = $this->findUsedMethodsInProject();
      }

      $allModels = $this->loadModels();

      $models = (0 == count($this->params['models'])) ? $allModels : array_intersect($allModels, $this->params['models']);

      // create a form class for every Doctrine class
      foreach ($models as $model)
      {
        if (in_array($model, sfConfig::get('app_sfDoctrineTablePlugin_exclude_virtual_models')))
        {
          $this->getLogger()->debug(sprintf('Virtual model "%s" has been excluded from generating base table', $model));
          continue;
        }

        $this->tempFiles  = array(); // empty list of files to remove
        $this->modelName  = $model;
        $this->table      = Doctrine_Core::getTable($this->modelName);

        $this->generateCustomPHPDoc = array();

        if ($this->params['uninstall'] || $this->isTableGenerationDisabled())
        {
          $this->getLogger()->debug(sprintf('%s: Uninstalling model "%s"', __CLASS__, $model));

          try
          {
            $this->uninstallTable();
          }
          catch (Exception $e)
          {
            $this->restoreFilesFromBackup();

            continue;
          }

          $this->removeBackupFiles();

          continue;
        }

        $this->getLogger()->debug(sprintf('%s: Generating base tables for model "%s"', __CLASS__, $model));

        $isPluginModel = $this->isPluginModel($model);

        $baseDir = sfConfig::get('sf_lib_dir') . '/model/doctrine';
        if ($isPluginModel)
        {
          $baseDir .= "/{$this->getPluginNameForModel($model)}";
        }

        $baseTableLocation = "{$baseDir}/{$this->builderOptions['baseClassesDirectory']}/"
                           . "Base{$this->modelName}Table"
                           . "{$this->builderOptions['suffix']}";

        if (is_file($baseTableLocation) && ! is_writable($baseTableLocation))
        {
          throw new Exception(sprintf('Base table "%s" is not writeable', $baseTableLocation));
        }

        if (! is_writable(dirname($baseTableLocation)))
        {
          throw new Exception(sprintf('Dirname "%s" is not writeable', dirname($baseTableLocation)));
        }

        $this->methodDocs = array();
        $this->callableDocs = array();

        $this->buildRelationPhpdocs($model, $this->params['depth']);

        $this->createBackupFile($baseTableLocation);

        try
        {
          if (false === file_put_contents($baseTableLocation, $this->evalTemplate('sfDoctrineTableGeneratedTemplate.php')))
          {
            throw new Exception(sprintf('Failed to put content into "%s"', $baseTableLocation));
          }

          $this->getLogger()->debug(sprintf('%s: Updating file: "%s"', __CLASS__, $baseTableLocation));

          $this->installTable();
        }
        catch (Exception $e)
        {
          $this->getLogger()->warning(sprintf('%s: Caught an exception. Reverting back modified files...', __CLASS__));

          $this->restoreFilesFromBackup();

          throw $e;
        }

        $this->removeBackupFiles();
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
     * @return string|bool Plugin name
     */
    public function getPluginNameForModel($modelName)
    {
      if ($this->isPluginModel($modelName))
      {
        return $this->pluginModels[$modelName];
      }

      return false;
    }

    /**
     * Loads all Doctrine builders.
     *
     * @return array
     */
    protected function loadModels ()
    {
      Doctrine_Core::loadModels($this->getConfiguration()->getModelDirs());
      $models = Doctrine_Core::getLoadedModels();
      $models = Doctrine_Core::initializeModels($models);
      $models = Doctrine_Core::filterInvalidModels($models);

      return $this->filterGeneratedModels($models);
    }

    /**
     * Filter out models that have disabled generation of form classes
     *
     * @return array $models Array of models to generate forms for
     */
    protected function filterGeneratedModels($models)
    {
      foreach ($models as $key => $model)
      {
        // Skip Translation tables
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
     * @return string|null  Find parent model in case it has inheritance
     *                      (e.i. simple, concrete and column aggregation)
     */
    public function getParentModel()
    {
      $baseClasses = array('Doctrine_Record', 'sfDoctrineRecord');

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

      return null;
    }

    /**
     * Get the name of the form class to extend based on the inheritance of the model
     *
     * @return string
     */
    public function getTableToExtendFrom()
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

        // not one of virtual models (e.g. sfSocialGuardUser)
        if (! in_array($parent, sfConfig::get('app_sfDoctrineTablePlugin_exclude_virtual_models')))
        {
          if (! $r->isAbstract())
          {
            return "{$parent}Table";
          }
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
        $string .= (empty($string) ? '' : ',') . $k . '=' . $v;
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

        // do not dublicate alias inside joins
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

        // Do not use $table->hasTemplate('I18n') to check whether it's time
        // to generate translation joins - produces invalid aliases when has
        // many i18n-relations. Hard to catch.
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

          // Joins
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

          // Sub-Select
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

          $this->callableDocs[$m] = $this->inline(
            array('m' => $m, 'n' => $columnName, 'c' => $buildMethod)
          );

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
     * @return array  An array of methods to render as @c annotation
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

      if (! $this->isPluginModel($this->modelName))
      {
        // 1 file to update
        // (E.g. lib/model/doctrine/MyModuleTable.class.php)
        $tableLocation = "{$baseDir}/{$this->modelName}Table{$this->builderOptions['suffix']}";

        if (! is_file($tableLocation) || ! is_readable($tableLocation) || ! is_writable($tableLocation))
        {
          throw new Exception(sprintf('File "%s" is missing or un-readable or un-writable', $tableLocation));
        }

        $tableContent = file_get_contents($tableLocation);

        if (false === $tableContent)
        {
          throw new Exception(sprintf('Failed to get file "%s" contents', $tableLocation));
        }

        // If previously "extends" class was not Doctrine_Table?
        // Leave Doctrine_Table, in README is described uninstallation process
        // (build-models should be executed)
        $count = 0;
        $tableContent = preg_replace(
          "/class(\s+){$this->modelName}Table(\s+)extends(\s+)Base{$this->modelName}Table/ms",
          "class\\1{$this->modelName}Table\\2extends\\3{$this->getClassNameToExtendFromAfterUninstalling()}",
          $tableContent, 1, $count
        );

        if ($count)
        {
          $this->createBackupFile($tableLocation);

          if (false === file_put_contents($tableLocation, $tableContent))
          {
            throw new Exception(sprintf('Failed to put contents into "%s"', $tableLocation));
          }

          $this->getLogger()->debug(sprintf('%s: Updating file: "%s"', __CLASS__, $tableLocation));
        }

      }
      else
      {
        // There are two files to update
        $pluginName = $this->getPluginNameForModel($this->modelName);

        // File #1 - plugins table
        //    Text to replace: class PluginsfGuardUserTable extends BasesfGuardUserTable
        //               with: class PluginsfGuardUserTable extends Doctrine_Core::ATTR_TABLE_CLASS

        // (E.g `pwd`/plugins/sfDoctineGuardPlugin/lib/model/doctrine/PluginMyModuleTable.class.php)
        $pluginTableLocation = sfConfig::get('sf_plugins_dir')
                             . "/{$pluginName}/lib/model/doctrine/"
                             . $this->builderOptions['packagesPrefix']
                             . "{$this->modelName}Table"
                             . $this->builderOptions['suffix']
        ;

        if (! is_file($pluginTableLocation) || ! is_readable($pluginTableLocation) || ! is_writable($pluginTableLocation))
        {
          throw new Exception(sprintf('File "%s" is missing or un-readable or un-writable', $pluginTableLocation));
        }

        $pluginTableContent = file_get_contents($pluginTableLocation);

        if (false === $pluginTableContent)
        {
          throw new Exception(sprintf('Failed to get file "%s" contents', $pluginTableLocation));
        }

        $count = 0;
        $pluginTableContent = preg_replace(
          "/class(\s+){$this->builderOptions['packagesPrefix']}{$this->modelName}Table(\s+)extends(\s+)\w+/ms",
          "class\\1{$this->builderOptions['packagesPrefix']}{$this->modelName}Table\\2extends\\3{$this->getClassNameToExtendFromAfterUninstalling()}",
          $pluginTableContent, 1, $count
        );

        if ($count)
        {
          $this->createBackupFile($pluginTableLocation);

          if (false === file_put_contents($pluginTableLocation, $pluginTableContent))
          {
            throw new Exception(sprintf('Failed to put content into "%s"', $pluginTableLocation));
          }

          $this->getLogger()->debug(sprintf('%s: Updating file: "%s"', __CLASS__, $pluginTableLocation));
        }
      }

      $pluginFolder = $this->isPluginModel($this->modelName)
        ? '/' . $this->getPluginNameForModel($this->modelName)
        : '';

      // if file "base-table" was created before, remove it
      $baseTableLocation = "{$baseDir}{$pluginFolder}/"
                         . "{$this->builderOptions['baseClassesDirectory']}/"
                         . "Base{$this->modelName}Table"
                         . "{$this->builderOptions['suffix']}"
      ;

      if (! is_file($baseTableLocation))
      {
        $this->getLogger()->debug(sprintf('%s: Updating file: "%s"', __CLASS__, $baseTableLocation));
        // Base table can be already removed, or model with "table: false"
        return;
      }

      if (! is_writable($baseTableLocation))
      {
        throw new Exception(sprintf('Base table file "%s" is not writable', $baseTableLocation));
      }

      unlink($baseTableLocation);
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
        // There is 1 file we should change
        //      1. lib/model/doctrine/%model_name%Table.class.php
        $tableLocation = "{$baseDir}/{$this->modelName}Table{$this->builderOptions['suffix']}";

        if (! is_file($tableLocation) || ! is_readable($tableLocation) || !is_writable($tableLocation))
        {
          throw new Exception(sprintf('File "%s" is missing or un-readable or un-writable', $tableLocation));
        }

        $tableClassContent = file_get_contents($tableLocation);

        if (false === $tableClassContent)
        {
          throw new Exception(sprintf('Failed to get file "%s" contents', $tableLocation));
        }

        // replace invalid PHPDoc with correct
        $countReturn = 0;
        $tableClassContent = preg_replace(
          "/@return(\s+)object(\s+){$this->modelName}Table/ms",
          "@return\\1{$this->modelName}Table",
          $tableClassContent, 1, $countReturn
        );

        // Keep code formatting
        $countExtends = 0;
        $tableClassContent = preg_replace(
          "/class(\s+){$this->modelName}Table(\s+)extends(\s+)\w+/ms",
          "class\\1{$this->modelName}Table\\2extends\\3Base{$this->modelName}Table",
          $tableClassContent, 1, $countExtends
        );

        if ($countReturn || $countExtends)
        {
          $this->createBackupFile($tableLocation);

          if (false === file_put_contents($tableLocation, $tableClassContent))
          {
            throw new Exception(sprintf('Failed to put content into "%s"', $tableLocation));
          }

          $this->getLogger()->debug(sprintf('%s: Updating file: "%s"', __CLASS__, $tableLocation));
        }
      }
      else // Model comes from plugin/ directory
      {
        // There are 2 files we need to update
        //     1. plugins/%plugin_name%/lib/model/doctrine/plugin/Plugin%model_name%Table.class.php
        //     2. lib/model/doctrine/%model_name%Table.class.php
        //
        //   File #1 contains class "Doctrine_Table", e.g. PluginsfGuardUserTabe extends Doctrine_Table
        //   it should be replaced with BasesfGuardUserTable
        //
        //   File #2 contains invalid PHPDoc in @return annotation
        $pluginName = $this->getPluginNameForModel($this->modelName);

        // File #1
        $pluginTableLocation =
          sfConfig::get('sf_plugins_dir') .
          "/{$pluginName}/lib/model/doctrine" .
          "/{$this->builderOptions['packagesPrefix']}{$this->modelName}" .
          "Table{$this->builderOptions['suffix']}";

        if (! is_file($pluginTableLocation) || ! is_readable($pluginTableLocation) || ! is_writable($pluginTableLocation))
        {
          throw new Exception(sprintf('File "%s" is missing or un-readable or un-writable', $pluginTableLocation));
        }

        $pluginTableContent = file_get_contents($pluginTableLocation);

        if (false === $pluginTableContent)
        {
          throw new Exception(sprintf('Failed to get file "%s" contents', $pluginTableLocation));
        }

        // Keep code formatting, and I know, it's not good to change plugins files ;>
        $count = 0;
        $pluginTableContent = preg_replace(
          "/class(\s+){$this->builderOptions['packagesPrefix']}{$this->modelName}Table(\s+)extends(\s+)\w+/ms",
          "class\\1{$this->builderOptions['packagesPrefix']}{$this->modelName}Table\\2extends\\3Base{$this->modelName}Table",
          $pluginTableContent, 1, $count
        );

        if ($count)
        {
          $this->createBackupFile($pluginTableLocation);

          if (false === file_put_contents($pluginTableLocation, $pluginTableContent))
          {
            throw new Exception(sprintf('Failed to put content into "%s"', $pluginTableLocation));
          }

          $this->getLogger()->debug(sprintf('%s: Updating file: "%s"', __CLASS__, $pluginTableLocation));
        }

        // File #2
        $baseTableLocation = "{$baseDir}/{$pluginName}/{$this->modelName}Table{$this->builderOptions['suffix']}";

        if (! is_file($baseTableLocation) || ! is_readable($baseTableLocation) || ! is_writable($baseTableLocation))
        {
          throw new Exception(sprintf('File "%s" is missing or un-readable or un-writable', $baseTableLocation));
        }

        $baseTableContent = file_get_contents($baseTableLocation);

        // replace invalid PHPDoc with correct
        $count = 0;
        $baseTableContent = preg_replace(
          "/\@return(\s+)object(\s+){$this->modelName}Table/ms",
          "@return\\1{$this->modelName}Table",
          $baseTableContent, 1, $count
        );

        if ($count)
        {
          $this->createBackupFile($baseTableLocation);

          if (false === file_put_contents($baseTableLocation, $baseTableContent))
          {
            throw new Exception(sprintf('Failed to put content into "%s"', $baseTableLocation));
          }

          $this->getLogger()->debug(sprintf('%s: Updating file: "%s"', __CLASS__, $baseTableLocation));
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
      $parentInheritedModelName = $this->getParentModel();

      if (null === $parentInheritedModelName)
      {
        return Doctrine_Manager::getInstance()->getAttribute(Doctrine_Core::ATTR_TABLE_CLASS);
      }

      return "{$parentInheritedModelName}Table";
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
        ->prune(sfConfig::get('app_sfDoctrineTablePlugin_finder_prune_folders'))
        ->discard(sfConfig::get('app_sfDoctrineTablePlugin_finder_discard_folders'))
        ->name(sfConfig::get('app_sfDoctrineTablePlugin_finder_name'))
        ->not_name(sfConfig::get('app_sfDoctrineTablePlugin_finder_not_name'))
        ->in(sfConfig::get('app_sfDoctrineTablePlugin_finder_search_in'))
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

    protected function createBackupFile ($originalFile)
    {
      if (! is_file($originalFile))
      {
        $this->tempFiles[$originalFile] = null;

        return;
      }

      if (! is_readable($originalFile))
      {
        throw new Exception(sprintf('Can\'t create file "%s" backup. File is non-readable.', $originalFile));
      }

      $backupFile = "{$originalFile}.bkp";

      if (is_file($backupFile))
      {
        if (! unlink($backupFile))
        {
          throw new Exception(sprintf('Can\'t remove old backup file "%s"', $backupFile));
        }
      }

      if (! copy($originalFile, $backupFile))
      {
        throw new Exception(sprintf('Can\'t copy file "%s" to "%s"', $originalFile, $backupFile));
      }

      $this->tempFiles[$originalFile] = $backupFile;

      $this->getLogger()->debug(sprintf('%s: Created file "%s" backup', __CLASS__, $originalFile));

      return;
    }

    protected function restoreFilesFromBackup ()
    {
      foreach ($this->tempFiles as $originalFile => $backupFile)
      {
        // previous version of file never existed
        if (null === $backupFile)
        {
          if (unlink($originalFile))
          {
            $this->getLogger()->debug(sprintf('%s: Removed file "%s" that was not existed before', __CLASS__, $originalFile));
          }
          else
          {
            $this->getLogger()->err(sprintf('%s: Failed to removed file "%s" that was not existed before', __CLASS__, $originalFile));
          }

          continue;
        }

        if (is_file($originalFile) && ! unlink($originalFile))
        {
          $this->getLogger()->err(sprintf('%s: Failed to remove file "%s"', __CLASS__, $originalFile));

          continue;
        }

        if (! rename($backupFile, $originalFile))
        {
          $this->getLogger()->err(sprintf('%s: Failed to restore file "%s" back to previous version', __CLASS__, $originalFile));
          continue;
        }

        $this->getLogger()->debug(sprintf('%s: Restoring file "%s" from backup', __CLASS__, $originalFile));
      }
    }

    /**
     * Deletes backup files for current model.
     * In case backup file does not exists - removes original file because
     * before installation such file never existed
     *
     * @return void
     */
    protected function removeBackupFiles ()
    {
      foreach ($this->tempFiles as $originalFile => $backupFile)
      {
        // previous version of file never existed
        if (null === $backupFile)
        {
          continue;
        }

        if (! unlink($backupFile))
        {
          $this->getLogger()->err(sprintf('%s: Failed to unlink backup file "%s"', __CLASS__, $backupFile));
          continue;
        }

        $this->getLogger()->debug(sprintf('%s: Removed backup file "%s"', __CLASS__, $backupFile));
      }
    }
  }
