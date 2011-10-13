# sfDoctrineTablePlugin

The ``sfDoctrineTablePlugin`` generates feature packed base tables to each model.
Base table contains PHPDocs of available pre-generated ``WHERE``, ``COUNT`` and ``JOIN``s
considering table relations and its depth. List of new available methods are accessed through
the PHPDoc tag @method and are suitable for IDE users only
(prefect implementation in [NetBeans 7.1](http://netbeans.org/downloads/index.html "NetBeans Download page"))

# Table of contents

 1. <a href="#desc">Description</a>
 1. <a href="#screenshot">Screenshots</a>
 1. <a href="#install">Installation</a>
 1. <a href="#uninstall">Uninstalling</a>
 1. <a href="#setup">Setup</a>
   1. <a href="#h5_1">Check plugin is enabled</a>
   1. <a href="#h5_2">Configure (optionally)</a>
   1. <a href="#h5_3">Execute task</a>
     1. <a href="#h5_3_1">Usage</a>
     1. <a href="#h5_3_2">Build base tables</a>
     1. <a href="#h5_3_3">Customize JOIN's deepness</a>
     1. <a href="#h5_3_4">Optimize tables for production</a>
   1. <a href="#h5_4">Turning off base table generation for specific models</a>
 1. <a href="#how">How it works</a>
 1. <a href="#problem">Known problem</a>
 1. <a href="#bench">Benchmarks</a>
 1. <a href="#tdd">TDD</a>

# 1. <a id="desc">Description</a>

Plugin helps you to skip routine job by writing left/inner joins on related
tables. It gives you ability to use pre-generated methods with IDE code-completion
to speed-up your coding. Also, you could add your owns methods to the generator's
template by extending it.

# 2. <a id="screenshot">Screenshots</a>

[![Auto-completion in NetBeans 7.1 Pic1](https://lh4.googleusercontent.com/-itKB-UZxEGY/TpNJMJ48API/AAAAAAAABeE/KLMlg-nMjX0/s400/Screenshot-SymfonyPluginDevelopment%252520-%252520NetBeans%252520IDE%252520Dev%252520201110070600-1.png "Click to zoom-in")](https://lh3.googleusercontent.com/-itKB-UZxEGY/TpNJMJ48API/AAAAAAAABeo/yvblyYlYSZE/s1152/Screenshot-SymfonyPluginDevelopment%2B-%2BNetBeans%2BIDE%2BDev%2B201110070600-1.png "Preview")
[![Auto-completion in NetBeans 7.1 Pic2](https://lh3.googleusercontent.com/-IYOodci9R0M/TpNMExT8c2I/AAAAAAAABeU/CGejbeMYcOo/s400/Screenshot-SymfonyPluginDevelopment%252520-%252520NetBeans%252520IDE%252520Dev%252520201110070600-2.png "Click to zoom-in")](https://lh3.googleusercontent.com/-IYOodci9R0M/TpNMExT8c2I/AAAAAAAABeU/CGejbeMYcOo/s1268/Screenshot-SymfonyPluginDevelopment%252520-%252520NetBeans%252520IDE%252520Dev%252520201110070600-2.png "Preview")

# 3. <a id="install">Installation</a>

 * As symfony plugin

  * Installing

            ./symfony plugin:install sfDoctrineTablePlugin

  * Upgrading

            cd plugins/sfDoctrineTablePlugin
            git pull origin master
            cd ../..

 * As GIT submodule (in general for plugin-developers - contains test suit)

  * Installation

            $ git submodule add git://github.com/fruit/sfDoctrineTablePlugin.git plugins/sfDoctrineTablePlugin
            $ git submodule init plugins/sfDoctrineTablePlugin

  * Upgrading

            $ cd plugins/sfDoctrineTablePlugin
            $ git pull origin master
            $ cd ../..

# 4. <a id="uninstall">Uninstalling</a>

  Unusual uninstalling process! First of all you should rollback your base table class
  inheritance and remove generated base table classes for models. All that you can
  make by executing:

    ./symfony doctrine:build-table --uninstall

  In case, you had your own ``Doctrine_Table`` class (e.g. ``Doctrine_Table_Advanced``), you need replace inherited
  class from ``Doctrine_Table_Scoped`` back to ``Doctrine_Table_Advanced``.

  And after, usual uninstalling process:

    ./symfony plugin:uninstall sfDoctrineTablePlugin

  Then, build your models, to be sure, all is O.K.

    ./symfony doctrine:build-model

# 5. <a id="setup">Setup</a>

## 5.1. <a id="">Check plugin is enabled</a>

    [php]
    <?php

    class ProjectConfiguration extends sfProjectConfiguration
    {
      public function setup ()
      {
        // … other plugins
        $this->enablePlugins('sfDoctrineTablePlugin');
      }
    }

## 5.2. <a id="">Configure (optionally)</a>

    all:
      sf_doctrine_table_plugin:

        # Extended by plugin class Doctrine_Table
        # (default: Doctrine_Table_Scoped)
        custom_table_class:   Doctrine_Table_Scoped

  Use case: You have extended by yourself class ``Doctrine_Table`` and name it
  ``Doctrine_Table_Advanced``, thus plugin configuration should looks like:

    all:
      sf_doctrine_table_plugin:
        custom_table_class: Doctrine_Table_Advanced

  Also, class ``Doctrine_Table_Advanced`` should be extended from ``Doctrine_Table_Scoped`` - that's all

    [php]
    <?php

    class Doctrine_Table_Advanced extends Doctrine_Table_Scoped
    {
      // ...
    }

## 5.3. <a id="h5_3">Execute task</a>

### 5.3.1 <a id="h5_3_1">Usage</a>

    ./symfony doctrine:build-table [--application[="..."]] [--env="..."] [--depth[="..."]] [--minified] [--uninstall] [--generator-class="..."] [--no-confirmation]

  For full task details, please refer to the task help block:

    ./symfony help doctrine:build-table

### 5.3.2 <a id="h5_3_2">Build base tables</a>

  Run this task each time you update the schema.yml and rebuild models:

    ./symfony doctrine:build-table

### 5.3.3 <a id="h5_3_3">Customize JOIN's deepness</a>

  By default JOINs deepness is 3 (superfluously enough), but you can adjust it by passing flag ``--depth``:

    ./symfony doctrine:build-table --depth=4

### 5.3.4 <a id="h5_3_4">Optimize tables for production</a>

  When you deploy your code to production you need to minimize generated base
  table class file size by passing flag ``--minified`` (e.i. base tables without @method hints):

    ./symfony doctrine:build-table --env=prod --minified

  And **remember** to enable APC! Without it, this will work pretty slowly. And sure, cache is working right!

  Check for ``apc.num_files_hint`` is greater than yours project's PHP file count:

    find ./ -type f -name "*.php" | wc -l

  Check for ``apc.max_file_size`` is greater than your project's worst PHP file:

    find ./ -type f -name "*.php" -size +1M

  Check for ``apc.shm_size`` is greater than all PHP files size:

    find ./ -type f -name "*.php" -ls | awk '{total += $7} END {print total}'

## 5.4. <a id="h5_4">Turning off base table generation for specific models</a>

  By default task ``doctrine:build-model`` will generate base tables for each
  existing model, unless you disable it. To disable it you need to add option
  ``table: false`` to the specific model schema.yml:

    Book:
      options:
        symfony: { table: false }

  Then rebuild models:

    ./symfony doctrine:build-model

  And generate updated base tables:

    ./symfony doctrine:build-table

  There are some nuances to know. When you disable model(-s), which base table(-s) was generated before,
  task ``doctrine:build-table`` will uninstall disabled base table automatically.

# 6. <a id="how">How it works</a>

  All is very tricky. Each available method for code-completion does not contains code at all.
  That is - no extra code, smallest file size. Things are done by implementing ``PHPDoc`` directive @method.

  Here is code sample of generated base table for model Post - file ``BasePostTable.class.php``
  preview on [http://pastie.org/private/wh5wn0ntvfkuoizjwka](http://pastie.org/private/wh5wn0ntvfkuoizjwka "Preview")

  As you could observe, additionally PHPDoc contains ``@c`` directives:

    * ...
    * @c(m=withLeftJoinOnSection,o=s,f=^,ra=Section,c=buildLeft)
    * @c(m=withInnerJoinOnSection,o=s,f=^,ra=Section,c=buildInner)
    * ...
    * @c(m=withLeftJoinOnPostMediaImageViaImagesAndTranslations,o=is_ts_pmi,f=is_ts,ra=PostMediaImage,c=buildLeft)
    * @c(m=withInnerJoinOnPostMediaImageViaImagesAndTranslations,o=is_ts_pmi,f=is_ts,ra=PostMediaImage,c=buildInner)
    * ...

  This information helps to build requested method on the fly by implementing magic method ``__call``.
  This works pretty fast even base table class size is about 300kb.

# 7. <a id="problem">Known problem</a>

  Joined table aliases may change when existing relation is removed or new relations are added
before existing one.
  This happens due to aliases are generated based on component name.

  For example model owns 2 relations Company and Category:

    [yaml]
    Article:
      relations:
        Company:
          class: Company
          local: article_id
        Category:
          class: Category
          local: category_id

  Assume we need to join both tables Company and Category from the table Article.

    [php]

    $q = ArticeTable::getInstance()->createQuery('a');
    ArticeTable::getInstance()
      ->withInnerJoinOnCompany($q)
      ->withInnerJoinOnCategory($q)
    ;

    $q->select('a.*, c.title, ca.slug')->execute();

  All relations starts with "C", this mean that joined Company table maps to "c"
  and Category maps to the "ca" (due to "c" is used).

  After some database refactorings, relation "Company" was removed. And you have
  re-built your models and base tables again.

  Now, when you update below above code by removing all things related to Company:

    [php]

    $q = ArticeTable::getInstance()->createQuery('a');
    ArticeTable::getInstance()
      ->withInnerJoinOnCategory($q)
    ;

    $q->select('a.*, ca.slug')->execute();

  Code will be still invalid, due to new generated alias for table Category now maps to the "c".
  So, to fix code sample, you need to replace "ca.slug" with "c.slug".

    [php]

    $q->select('a.*, c.slug')->execute();

  If anybody could help me to elegantly solve this issue - I will be pleasantly thankful.

# 8. <a id="bench">Benchmarks</a>

  Given below statistical numbers are generated for production environment
  with ``--minified`` flag and enabled APC:

  Here is time cost to initialize new table instance (e.g. ``Doctrine::getTable('MyTable')``)
  with generated base table and without.
  As you could notice, this it pretty large table with 26 relations and 19 columns.
  It demonstrates that even big table load time is slower than 0.00142 ms comparing
  to a default initialization time.

  All the more so it's just first time you initialize any table, all following
  table initializations will be comparatively slower than 0.0001 ms

    +----------------------------------------------------------------------+
    |              Time required to initialize table instance              |
    +----------+--------+--------+---------------+-------------+-----------+
    | Relation | Column | Depth  |    Default    | With plugin | Slower on |
    |   count  |  count | level  |     (ms)      |     (ms)    |   (ms)    |
    +----------+--------+--------+---------------+-------------+-----------+
    |    26    |   19   |   3    |       0.01411 |     0.01553 |   0.00142 |
    +----------+--------+--------+---------------+-------------+-----------+

  Given below table demonstrates how many time is spent to analyze PHPDoc.
  Referencing to table data, generated base table will add additionally ~ 0.0003 ms
  to each magic method call.

    +-------------------------------------------------------------------------+
    |          Time required to add new Doctrine Query parts                  |
    +----------------------+------------+-----------+-------------+-----------+
    |        Method        |   Method   |  Default  | With plugin | Slower on |
    |         name         | complexity |   (ms)    |    (ms)     |    (ms)   |
    +----------------------+------------+-----------+-------------+-----------+
    |      orWhereId       |    low     |   0.00003 |     0.00035 |   0.00032 |
    +----------------------+------------+-----------+-------------+-----------+
    | withLeftJoinOnGroups |    high    |   0.00410 |     0.00435 |   0.00025 |
    +----------------------+------------+-----------+-------------+-----------+

  Things to remember:

  * Minified classes uses less space on disk, but does not affects the table initialization time.
  * Depth level with enabled APC does not affects the table initialization time (difference less than 0.001s).
  * The more generation depth, table relations and columns count, the more file size.

# 9. <a id="tdd">TDD</a>

  Tested basic functionality.

    [plain]

    [sfDoctrineTable] functional/backend/BuildTableTaskTest..............ok
    [sfDoctrineTable] functional/backend/MethodExistanceTest.............ok
    [sfDoctrineTable] functional/backend/MethodWhereTest.................ok
     All tests successful.
     Files=3, Tests=102