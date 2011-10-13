<?php

  /*
   * This file is part of the sfDoctrineTablePlugin package.
   * (c) 2011 Ilya Sabelnikov <fruit.dev@gmail.com>
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
    /**
     * @see sfTask
     */
    protected function configure()
    {
      $this->addOptions(array(
        new sfCommandOption('application', null, sfCommandOption::PARAMETER_OPTIONAL, 'The application name', true),
        new sfCommandOption('env', null, sfCommandOption::PARAMETER_REQUIRED, 'The environment', 'dev'),
        new sfCommandOption('depth', null, sfCommandOption::PARAMETER_OPTIONAL, 'How deeply to build join methods', 3),
        new sfCommandOption('minified', null, sfCommandOption::PARAMETER_NONE, 'Whether to remove @method comments from file'),
        new sfCommandOption('uninstall', null, sfCommandOption::PARAMETER_NONE, 'Cleans generated base model tables, and reverts its content to default'),
        new sfCommandOption('generator-class', null, sfCommandOption::PARAMETER_REQUIRED, 'The generator class', 'sfDoctrineTableGenerator'),
        new sfCommandOption('no-confirmation', null, sfCommandOption::PARAMETER_NONE, 'Whether to force modifing the generated model classes within lib/model/doctrine')
      ));

      $this->namespace = 'doctrine';
      $this->name = 'build-table';
      $this->briefDescription = 'Creates base table classes for the current table';

      $this->detailedDescription = <<<EOF
The [{$this->namespace}:{$this->name}|INFO] task creates table classes from the schema:

  [./symfony {$this->namespace}:{$this->name}|INFO]

The task read the schema information in [config/doctrine/*.yml|COMMENT]
from the project and all enabled plugins.

This task MODIFIES custom classes in [lib/model/doctrine|COMMENT].
And also it replaces files in [lib/model/doctrine/base|COMMENT].

To increase/decrease joins depth use option [--depth|COMMENT]:

    [./symfony {$this->namespace}:{$this->name} --env=%YOUR_ENV% --depth=2|COMMENT]

To minify generated base table size on production use flag [--minified|COMMENT]:

    [./symfony {$this->namespace}:{$this->name} --env=prod --minified|COMMENT]

When you deside to stop using plugin, uninstall it by running first:

    [./symfony {$this->namespace}:{$this->name} --env=%YOUR_ENV% --uninstall|COMMENT]

EOF;
    }

    protected function execute ($arguments = array(), $options = array())
    {
      $this->logSection('doctrine', 'generating generic table classes');

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

        return 1;
      }

      if (0 >= $options['depth'])
      {
        $this->logBlock('Value --depth is a number from 1 to N', 'ERROR');
        return false;
      }

      if (! class_exists($options['generator-class']))
      {
        $this->logBlock(sprintf('Generator class "%s" not found.', $options['generator-class']), 'ERROR');
        return false;
      }

      $databaseManager = new sfDatabaseManager($this->configuration);

      $generatorManager = new sfGeneratorManager($this->configuration);
      $generatorManager->generate($options['generator-class'], array(
        'env'       => (string) $options['env'],
        'depth'      => ((int) $options['depth']) - 1,
        'minified'  => (bool) $options['minified'],
        'uninstall' => (bool) $options['uninstall'],
      ));

      $properties = array();
      $iniFile = sfConfig::get('sf_config_dir').DIRECTORY_SEPARATOR.'properties.ini';

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

      $builderOptions = $this
        ->configuration
        ->getPluginConfiguration('sfDoctrinePlugin')
        ->getModelBuilderOptions()
      ;

      $finder = sfFinder::type('file')->name("Base*Table{$builderOptions['suffix']}");

      $doctrineCliConfig = $this->configuration->getPluginConfiguration('sfDoctrinePlugin')->getCliConfig();
      $files = $finder->in($doctrineCliConfig['models_path']);

      $this->getFilesystem()->replaceTokens($files, '##', '##', $constants);

      $this->reloadAutoload();
    }
  }
