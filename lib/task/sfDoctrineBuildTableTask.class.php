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
      RETURN_TABLE_INHERITANCE    = 5,
      RETURN_GENERATE_EXCEPTION   = 6;

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
        new sfCommandOption('generator-class', null, sfCommandOption::PARAMETER_REQUIRED,
          'The generator class', 'sfDoctrineTableGenerator'
        ),
        new sfCommandOption('depth', 'd', sfCommandOption::PARAMETER_REQUIRED,
          'How deeply to build join methods', 3
        ),
        new sfCommandOption('minified', 'm', sfCommandOption::PARAMETER_NONE,
          'Minifies the base tables by cleaning out from the unused PHPDoc\'s'
        ),
        new sfCommandOption('no-phpdoc', 'n', sfCommandOption::PARAMETER_NONE,
          'Whether to remove all @method comments from file'
        ),
        new sfCommandOption('uninstall', 'u', sfCommandOption::PARAMETER_NONE,
          'Cleans generated base model tables, and reverts its content to default'
        ),
        new sfCommandOption('no-confirmation', 'f', sfCommandOption::PARAMETER_NONE,
          'Whether to force modifing the generated model classes within lib/model/doctrine'
        ),
      ));

      $this->addArguments(array(
        new sfCommandArgument(
          'model',
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

The task reads the schema information project's schema YAML files
in [config/doctrine/*.yml|COMMENT] and each enabled plugin-level schema YAML
files [plugins/%plugin%/config/doctrine/*.yml|COMMENT].

This task MODIFIES custom classes in [lib/model/doctrine|COMMENT].
And also it replaces files in [lib/model/doctrine/base|COMMENT].

To increase/decrease joins depth use option [--depth|COMMENT]:

  [./symfony {$this->namespace}:{$this->name} --depth=2|INFO]

To remove all @method hints from the base tables pass the [--no-phpdoc|COMMENT]/[-n|COMMENT] flag:

  [./symfony {$this->namespace}:{$this->name} --no-phpdoc|INFO]

Use the [--minified|COMMENT]/[-m|COMMENT] combining with [--no-phpdoc|COMMENT]/[-n|COMMENT] flag
to minify generated base table size on production environment:

  [./symfony {$this->namespace}:{$this->name} --minified --no-phpdoc|INFO]

When you deside to stop using plugin, uninstall it by passing [--uninstall|COMMENT]/[-u|COMMENT] flag:

  [./symfony {$this->namespace}:{$this->name} --uninstall|INFO]

Sometimes it's necessary to skip confirmation dialogs automatically with
positive answer. For such cases use the [--no-confirmation|COMMENT]/[-f|COMMENT] flag:

  [./symfony {$this->namespace}:{$this->name} --depth=2 --no-confirmation|INFO]

To enable custom generator use the [--generator-class|COMMENT] option:

  [./symfony {$this->namespace}:{$this->name} --generator-class=MyDoctrineTableGenerator|INFO]
EOF;
    }

    protected function execute ($arguments = array(), $options = array())
    {
      if (! sfContext::hasInstance())
      {
        sfContext::createInstance($this->configuration);
      }

      $manager = Doctrine_Manager::getInstance();

      $currentTableClass = $manager->getAttribute(Doctrine_Core::ATTR_TABLE_CLASS);
      $defaultTableClass = sfDoctrineTableGenerator::DEFAULT_TABLE_CLASS;

      if ($currentTableClass !== $defaultTableClass)
      {
        if (! class_exists($currentTableClass))
        {
          $this->logBlock(sprintf('Doctrine table class "%s" not found.', $currentTableClass), 'ERROR');

          return self::RETURN_TABLE_NOT_FOUND;
        }

        if ('Doctrine_Table' === $currentTableClass)
        {
          if (! $options['uninstall'])
          {
            $manager->setAttribute(Doctrine_Core::ATTR_TABLE_CLASS, $defaultTableClass);
          }
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

      $this->logSection('doctrine', 'generating base table classes');

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

      new sfDatabaseManager($this->configuration);

      if (! empty ($arguments['model']))
      {
        foreach ($arguments['model'] as $modelName)
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

      $askQuestion = array(
        'This command will modify lib/model/doctrine table classes.',
        'If you are not sure, use VCS or make this folder backup.', '',
        'Are you sure you want to proceed? (y/N)'
      );

      if (
          ! $options['no-confirmation']
        &&
          ! $this->askConfirmation($askQuestion, 'QUESTION_LARGE', false)
      )
      {
        $this->logSection('doctrine', 'task aborted');

        return self::RETURN_SUCCESS;
      }

      try
      {
        $generatorManager = new sfGeneratorManager($this->configuration);
        $generatorManager->generate($options['generator-class'], array(
          'env'         => (string) $options['env'],
          'depth'       => ((int) $options['depth']) - 1,
          'no-phpdoc'   => (bool) $options['no-phpdoc'],
          'uninstall'   => (bool) $options['uninstall'],
          'minify'      => (bool) $options['minified'],
          'models'      => $arguments['model'],
        ));
      }
      catch (Exception $e)
      {
        $this->logBlock(sprintf('Got exception "%s" at %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()), 'ERROR');

        return self::RETURN_GENERATE_EXCEPTION;
      }

      $properties = array();
      $iniFile = sfConfig::get('sf_config_dir') . '/properties.ini';

      if (is_file($iniFile) && is_readable($iniFile))
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

      if (! empty($arguments['model']))
      {
        $baseTableFilenames = array_map(
          function ($modelName) use ($builderOptions) {
            return "Base{$modelName}Table{$builderOptions['suffix']}";
          },
          $arguments['model']
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
