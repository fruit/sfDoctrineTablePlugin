<?php

  /*
   * This file is part of the sfDoctrineTablePlugin package.
   * (c) 2012 Ilya Sabelnikov <fruit.dev@gmail.com>
   *
   * For the full copyright and license information, please view the LICENSE
   * file that was distributed with this source code.
   */

  /**
   * Create table classes for the current model.
   *
   * @package    symfony
   * @subpackage task
   * @author     Ilya Sabelnikov <fruit.dev@gmail.com>
   */
  class sfDoctrineBuildTableTask extends sfDoctrineBaseTask
  {
    const
      RETURN_SUCCESS              = 0,
      RETURN_INVALID_DEPTH        = 1,
      RETURN_MODEL_NOT_FOUND      = 2,
      RETURN_TABLE_NOT_FOUND      = 3,
      RETURN_GENERATOR_NOT_FOUND  = 4,
      RETURN_TABLE_INHERITANCE    = 5;

    /**
     * @see sfTask
     */
    protected function configure()
    {
      $this->addOptions(array(
        new sfCommandOption('application', null, sfCommandOption::PARAMETER_OPTIONAL,
          'The application name', true
        ),
        new sfCommandOption('env', null, sfCommandOption::PARAMETER_REQUIRED,
          'The environment', 'dev'
        ),
        new sfCommandOption('depth', null, sfCommandOption::PARAMETER_OPTIONAL,
          'How deeply to build join methods', 3
        ),
        new sfCommandOption('generator-class', null, sfCommandOption::PARAMETER_REQUIRED,
          'The generator class', 'sfDoctrineTableGenerator'
        ),
        new sfCommandOption('minified', 'm', sfCommandOption::PARAMETER_NONE,
          'Minifies the base tables by cleaning out from the unused PHPDoc\'s'
        ),
        new sfCommandOption('no-phpdoc', 'n', sfCommandOption::PARAMETER_NONE,
          'Whether to remove all @method comments from file'
        ),
        new sfCommandOption('uninstall', null, sfCommandOption::PARAMETER_NONE,
          'Cleans generated base model tables, and reverts its content to default'
        ),
        new sfCommandOption('no-confirmation', 'f', sfCommandOption::PARAMETER_NONE,
          'Whether to force modifing the generated model classes within lib/model/doctrine'
        ),
      ));

      $this->addArguments(array(
        new sfCommandArgument(
          'name',
          sfCommandArgument::OPTIONAL | sfCommandArgument::IS_ARRAY,
          "Model name(-s), if nothing is passed, all models will be used."
        ),
      ));

      $this->namespace = 'doctrine';
      $this->name = 'build-table';
      $this->briefDescription = 'Creates base table classes for the current table';

      $this->detailedDescription = <<<EOF
The [{$this->namespace}:{$this->name}|INFO] task creates table classes from the schema:

  [./symfony {$this->namespace}:{$this->name}|INFO]

The task reads the schema information project's schema YAML files in [config/doctrine/*.yml|COMMENT]
and each enabled plugin-level schema YAML files [plugins/%plugin%/config/doctrine/*.yml|COMMENT].

This task MODIFIES custom classes in [lib/model/doctrine|COMMENT].
And also it replaces files in [lib/model/doctrine/base|COMMENT].

To increase/decrease joins depth use option [--depth|COMMENT]:

    [./symfony {$this->namespace}:{$this->name} --env=%YOUR_ENV% --depth=2|INFO]

To remove all @method hints from the base tables pass the [--no-phpdoc|COMMENT]/[-n|COMMENT] flag:

    [./symfony {$this->namespace}:{$this->name} --env=%YOUR_ENV% --no-phpdoc|INFO]

To minify generated base table size for production use the [--minified|COMMENT]/[-m|COMMENT] flag
combining with [--no-phpdoc|COMMENT]/[-n|COMMENT] flag:

    [./symfony {$this->namespace}:{$this->name} --env=%YOUR_ENV% --minified --no-phpdoc|INFO]

When you deside to stop using plugin, uninstall it by passing [--uninstall|COMMENT] flag:

    [./symfony {$this->namespace}:{$this->name} --env=%YOUR_ENV% --uninstall|INFO]

Sometimes it's necessary to skip confirmation dialogs automatically with positive answer.
For such cases use the [--no-confirmation|COMMENT]/[-f|COMMENT] flag:

    [./symfony {$this->namespace}:{$this->name} --env=%YOUR_ENV% --depth=2 --no-confirmation|INFO]

EOF;
    }

    protected function execute ($arguments = array(), $options = array())
    {
      $manager = Doctrine_Manager::getInstance();

      $currentTableClass = $manager->getAttribute(Doctrine_Core::ATTR_TABLE_CLASS);
      $defaultTableClass = sfDoctrineTableGenerator::DEFAULT_TABLE_CLASS;

      if ($currentTableClass !== $defaultTableClass)
      {
        if (! class_exists($currentTableClass))
        {
          $this->logBlock(sprintf('Class "%s" not found.', $currentTableClass), 'ERROR');

          return self::RETURN_TABLE_NOT_FOUND;
        }

        if ('Doctrine_Table' === $currentTableClass)
        {
          $manager->setAttribute(Doctrine_Core::ATTR_TABLE_CLASS, $defaultTableClass);
        }
        else
        {
          if (! is_subclass_of($currentTableClass, $defaultTableClass))
          {
            $this->logBlock(sprintf(
              'The current doctrine table class "%s" has not "%s" class as one of its parents',
              $currentTableClass, $defaultTableClass
            ), 'ERROR');

            return self::RETURN_TABLE_INHERITANCE;
          }

          $manager->setAttribute(Doctrine_Core::ATTR_TABLE_CLASS, $currentTableClass);
        }
      }

      $this->logSection('doctrine', 'generating generic table classes');

      if (0 >= (int) $options['depth'])
      {
        $this->logBlock('Value --depth is a number from 1 to N', 'ERROR');

        return self::RETURN_INVALID_DEPTH;
      }

      if (! class_exists($options['generator-class']))
      {
        $this->logBlock(
          sprintf(
            'Generator class "%s" not found.',
            $options['generator-class']
          ),
          'ERROR'
        );

        return self::RETURN_GENERATOR_NOT_FOUND;
      }

      $databaseManager = new sfDatabaseManager($this->configuration);

      if (! empty ($arguments['name']))
      {
        foreach ($arguments['name'] as $modelName)
        {
          Doctrine_Core::modelsAutoload($modelName);

          if (class_exists($modelName))
          {
            continue;
          }

          $this->logBlock(
            sprintf(
              'Model name "%s" is not registered in current connection',
              $modelName
            ),
            'ERROR'
          );

          return self::RETURN_MODEL_NOT_FOUND;
        }
      }

      if (
        ! $options['no-confirmation']
        &&
        ! $this->askConfirmation(array_merge(
          array(
            'This command will modify lib/model/doctrine table classes.',
            'If you are not sure, use VCS or make this folder backup.',
            '',
            'Are you sure you want to proceed? (y/N)'
          )
        ), 'QUESTION_LARGE', false)
      )
      {
        $this->logSection('doctrine', 'task aborted');

        return self::RETURN_SUCCESS;
      }

      $generatorManager = new sfGeneratorManager($this->configuration);
      $generatorManager->generate($options['generator-class'], array(
        'env'         => (string) $options['env'],
        'depth'       => ((int) $options['depth']) - 1,
        'no-phpdoc'   => (bool) $options['no-phpdoc'],
        'uninstall'   => (bool) $options['uninstall'],
        'minify'      => (bool) $options['minified'],
        'models'      => $arguments['name'],
      ));

      $properties = array();
      $iniFile = sfConfig::get('sf_config_dir') . '/properties.ini';

      if (is_file($iniFile))
      {
        $properties = parse_ini_file($iniFile, true);
      }

      $constants = array(
        'PROJECT_NAME' => isset($properties['symfony']['name'])
          ? $properties['symfony']['name']
          : 'symfony',
        'AUTHOR_NAME'  => isset($properties['symfony']['author'])
          ? $properties['symfony']['author']
          : 'Your name here',
      );

      $sfDoctrinePlugin = $this
        ->configuration
        ->getPluginConfiguration('sfDoctrinePlugin')
      ;

      $builderOptions = $sfDoctrinePlugin->getModelBuilderOptions();

      if (! empty($arguments['name']))
      {
        $baseTableFilenames = array_map(
          function($modelName) use ($builderOptions) {
            return "Base{$modelName}Table{$builderOptions['suffix']}";
          },
          $arguments['name']
        );
      }
      else
      {
        $baseTableFilenames = "Base*Table{$builderOptions['suffix']}";
      }

      $doctrineCliConfig = $sfDoctrinePlugin->getCliConfig();

      $files = sfFinder::type('file')
        ->name($baseTableFilenames)
        ->in($doctrineCliConfig['models_path'])
      ;

      $this->getFilesystem()->replaceTokens($files, '##', '##', $constants);

      $this->reloadAutoload();

      return self::RETURN_SUCCESS;
    }
  }
