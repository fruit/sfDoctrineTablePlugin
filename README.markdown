# sfDoctrineTablePlugin

The ``sfDoctrineTablePlugin`` generates feature packed base tables for models.
Base table contains PHPDocs of available pre-generated ``WHERE``, ``COUNT``
and ``JOIN`` methods, considering table relations and its depth. List of pre-generated PHPDoc
methods are accessed through the tag @method and are suitable for IDE
users only (perfect implementation in [NetBeans 7.2](http://netbeans.org/downloads/index.html "NetBeans Download page"))

# Table of contents

 1. <a href="#desc">Description</a>
 1. <a href="#screenshot">Screenshot</a>
 1. <a href="#install">Installation</a>
 1. <a href="#uninstall">Uninstallation</a>
 1. <a href="#setup">Setup</a>
 1. <a href="#task">Task</a>
 1. <a href="#how">How it works</a>
 1. <a href="#extend">Extending the generator</a>
 1. <a href="#problem">Known problem</a>
 1. <a href="#tdd">TDD</a>
 1. <a href="#misc">Misc</a>

# 1. <a id="desc">Description</a>

Plugin helps you not to keep in mind table relation aliases and escape from the
constructing left and/or inner joins.
It gives you ability to use pre-generated methods with IDE code-completion
to speed-up your coding. Also, you can easy add your owns methods to the generator's
template by extending it (see <a href="#extend">8. Extending the generator</a>).

# 2. <a id="screenshot">Screenshot</a>

[![Auto-completion in NetBeans 7.1 Pic1](https://lh6.googleusercontent.com/-V4Pap4aTKBs/T9C2lqajhgI/AAAAAAAABh8/lD-umgg5vDw/w696-h563-k/Tooltip_001.png "Click to zoom-in")](https://lh6.googleusercontent.com/-V4Pap4aTKBs/T9C2lqajhgI/AAAAAAAABh8/lD-umgg5vDw/s925/Tooltip_001.png "Preview")

# 3. <a id="install">Installation</a>

**As symfony plugin**

_Installing_

    ./symfony plugin:install sfDoctrineTablePlugin

_Upgrading_

    cd plugins/sfDoctrineTablePlugin
    git pull origin master
    cd ../..

**As GIT submodule** (in general for plugin-developers - contains test suit)

_Installation_

    git submodule add git://github.com/fruit/sfDoctrineTablePlugin.git plugins/sfDoctrineTablePlugin
    git submodule init plugins/sfDoctrineTablePlugin

_Upgrading_

    cd plugins/sfDoctrineTablePlugin
    git pull origin master
    cd ../..

# 4. <a id="uninstall">Uninstallation</a>

  Unusual uninstallation process! First of all you should rollback your base table
  class inheritance and remove generated base table classes for models. All that
  you can make by executing:

    ./symfony doctrine:build-table --uninstall

  In case, you had your own-custom table class (e.g. ``My_Doctrine_Table``),
  you need to revert back it inherited by ``Doctrine_Table``.

  Then usual uninstallation process:

    ./symfony plugin:uninstall sfDoctrineTablePlugin

  Then, re-build your models, to be sure, all is O.K.

    ./symfony doctrine:build-model

# 5. <a id="setup">Setup</a>

## 5.1. <a id="h5_1">Check plugin is enabled</a>

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

## 5.2. <a id="h5_2">Configure</a>

### 5.2.1 <a id="h5_2_1">Plugin</a>

  In case you have your own-custom Doctrine_Table class (e.g. ``My_Doctrine_Table``), then you
  need to make it inherited from class ``Doctrine_Table_Scoped``, not from ``Doctrine_Table`` class.
  This can be done inside ``config/ProjectConfiguration.class.php``:

    [php]
    <?php

    class ProjectConfiguration extends sfProjectConfiguration
    {
      // ...

      public function configureDoctrine (Doctrine_Manager $manager)
      {
        $manager->setAttribute(Doctrine_Core::ATTR_TABLE_CLASS, 'My_Doctrine_Table');
      }
    }

  Here is the default plugin configuration. All configuration options are used to
  find all PHP files where you keep a business logic.

    [yaml]
    ---
    all:
      sfDoctrineTablePlugin:

        # Model names which does not have physical models
        # but just extends an existing (e.g. sfSocialPlugin has model sfSocialGuardUser)
        exclude_virtual_models: []

        # Given below finder_* options used to find which methods
        # are used in your project and further remove then in production environment
        finder_search_in: [%SF_APPS_DIR%, %SF_LIB_DIR%]   # List of directories where business logic are located
        finder_prune_folders: [base, vendor]              # List of folders to prune
        finder_discard_folders: []                        # List of folders to discard
        finder_name: ["*.php"]                            # List of filenames to add
        finder_not_name: []                               # List of filenames to skip

### 5.2.1 <a id="h5_2_2">Model</a>

  By default base tables will be generated to all models and to all enabled
  plugins that contains schema files. Occasionally, you won't use
  all models to query its data, some of them will be used to save data. In
  such cases is reasonable to disable them from generating it. How to do that
  please refer to <a href="#h6_5">6.5. Turning off base table generation for
  specific models</a>.

  According to my own experience, the most profit you will get in case you disable
  automatic relation detection (``detect_relations: false``) and setup only important
  to you relations by hand. Advantages to the solutions are clear generated method
  names and small file size (APC will be thankful to you).

  Here is small ``schema.yml`` example:

    [yaml]
    ---
    detect_relations: false

    Country:
      columns:
        id: { type: integer(4), primary: true, autoincrement: true }
        capital_city_id: { type: integer(4) }
        title: string(255)
      relations:
        Capital:
          class: City
          local: capital_city_id
          foreign: id
          type: one
          foreignType: one
          foreignAlias: CapitalOfTheCountry

    City:
      columns:
        id: { type: integer(4), primary: true, autoincrement: true }
        country_id: { type: integer(4) }
        title: string(255)
      relations:
        Country:
          foreignAlias: Cities


  After base table are generated, you can see following methods beside other methods:

    [php]
    <?php

    $q = CityTable::getInstance()->createQuery('ci');
    CityTable::getInstance()
      ->withInnerJoinOnCountry($q)
      ->withLeftJoinOnCapitalViaCountry($q)
      ->withLeftJoinOnCapitalOfTheCountryViaCountryAndCapital($q);

  The generated SQL (``$q->getSqlQuery()``) will looks like:

    [sql]
    SELECT
      c.id AS c__id, c.country_id AS c__country_id, c.title AS c__title,
      c2.id AS c2__id, c2.capital_city_id AS c2__capital_city_id, c2.title AS c2__title,
      c3.id AS c3__id, c3.country_id AS c3__country_id, c3.title AS c3__title,
      c4.id AS c4__id, c4.capital_city_id AS c4__capital_city_id, c4.title AS c4__title
    FROM city c
      INNER JOIN country c2 ON c.country_id = c2.id
      LEFT JOIN city c3 ON c2.capital_city_id = c3.id
      LEFT JOIN country c4 ON c3.id = c4.capital_city_id

  And here is DQL (``$q->getDql()``) will looks like:

    [sql]
    FROM City ci
      INNER JOIN ci.Country c
      LEFT JOIN c.Capital c_c
      LEFT JOIN c_c.CapitalOfTheCountry c_c_cotc


# 6. <a id="task">Task</a>

## 6.1 <a id="h6_1">Usage</a>

    ./symfony doctrine:build-table [name1] ... [nameN] \
        [--application[="..."]] \
        [--env="..."] \
        [--generator-class="..."] \
        [-d|--depth="..."] \
        [-m|--minified] \
        [-n|--no-phpdoc] \
        [-u|--uninstall] \
        [-f|--no-confirmation]

  For full task details, please refer to the task help block:

    ./symfony help doctrine:build-table

## 6.2 <a id="h6_2">Build base tables</a>

  Run this task each time you update the schema.yml and rebuild models:

    ./symfony doctrine:build-table

## 6.3 <a id="h6_3">Customize JOIN's deepness</a>

  By default JOINs deepness is 3 (superfluously enough), but you can adjust it
  by passing flag ``--depth``. The level of depth does not affects on speed
  in production environment (see <a id="#h6_4">Optimize tables for production</a>):

    ./symfony doctrine:build-table --depth=4

## 6.4 <a id="h6_4">Optimize tables for production</a>

  When you deploy your code to production you need to minimize generated base
  table class file size by passing flag ``--no-phpdoc`` (e.i. base tables without
  @method hints) and ``--minified`` (e.i. do not generate methods, that aren't used in project).

    ./symfony doctrine:build-table --env=prod --minified --no-phpdoc

## 6.5. <a id="h6_5">Turning off base table generation for specific models</a>

  By default task ``doctrine:build-model`` will generate base tables for each
  existing model, unless you disable it. To disable it you need to add option
  ``table: false`` to the specific model schema.yml:

    [yaml]
    ---
    Book:
      options:
        symfony: { table: false }

  Then rebuild models:

    ./symfony doctrine:build-model

  And generate updated base tables:

    ./symfony doctrine:build-table

  There are some nuances to know. When you disable model(-s), which base table(-s) was generated before,
  task ``doctrine:build-table`` will uninstall disabled base table automatically.

## 6.6. <a id="h6_6">Base tables generation for a specific models</a>

  Now you can pass manually a list of models you would like to generate base tables

    ./symfony doctrine:build-table City Country

  The same principle to uninstall a specific models:

    ./symfony doctrine:build-table --uninstall City Country

# 7. <a id="how">How it works</a>

  All is very tricky. Each available method for code-completion does not contains code at all.
  That is - no extra code, smallest file size. Things are done by implementing ``PHPDoc`` directive @method.

  Here is code sample of generated base table for model City - file ``BaseCityTable.class.php``
  preview on [http://pastie.org/private/gvwhdvyyakiofbtskuog3w](http://pastie.org/private/gvwhdvyyakiofbtskuog3w "Preview")

  As you can observe, file contains additional ``@c`` directives:

    [php]
    <?php

    /**
     * ...
     * @c(m=withLeftJoinOnSection,o=s,f=^,ra=Section,c=buildLeft)
     * @c(m=withInnerJoinOnSection,o=s,f=^,ra=Section,c=buildInner)
     * ...
     * @c(m=withLeftJoinOnPostMediaImageViaImagesAndTranslations,o=is_ts_pmi,f=is_ts,ra=PostMediaImage,c=buildLeft)
     * @c(m=withInnerJoinOnPostMediaImageViaImagesAndTranslations,o=is_ts_pmi,f=is_ts,ra=PostMediaImage,c=buildInner)
     * ...
     **/

  This information helps to build requested method on the fly by implementing magic
  method ``__call``. Parsing PHPDoc on the fly is fast (&lt; 0.003 sec) even the
  base table is not minified and contains @method hints (about 700kb).

  Minified base tables (see <a id="#h6_4">Optimize tables for production</a>) are much smaller (about &lt; 4kb) and
  parsing is much faster (&lt; 0.0001 sec).

# 8. <a id="extend">Extending the generator</a>

  Copy default generator skeleton folder to your project:

    cp -a plugins/sfDoctrineTablePlugin/data/generator/ data/.

  Create new generator class (e.g. ``MyDoctrineTableGenerator``) by extending it from ``sfDoctrineTableGenerator``.
  And use it when you run ``doctrine:build-table`` task by passing ``--generator-class`` option:

    ./symfony doctrine:build-table --depth=2 --generator-class=MyDoctrineTableGenerator

  That's all.

# 9. <a id="problem">Known problem</a>

  Joined table aliases may change when existing relation is removed or new relations are added
before existing one.
  This happens due to aliases are generated based on component name.

  For example model owns 2 relations Company and Category:

    [yaml]
    ---
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
    <?php

    $q = ArticeTable::getInstance()->createQuery('a');
    ArticeTable::getInstance()
      ->withInnerJoinOnCompany($q)
      ->withInnerJoinOnCategory($q)
    ;

    $q->select('a.*, c.title, ca.slug')->execute();

  All relations starts with "C", this mean that joined Company table maps to "c"
  and Category maps to the "ca" (due to "c" is used).

  You have made a database refactoring and relation "Company" was removed.
  Next step is to fix the query given above by removing all things related to a "Company":

    [php]
    <?php

    $q = ArticeTable::getInstance()->createQuery('a');
    ArticeTable::getInstance()
      ->withInnerJoinOnCategory($q)
    ;

    $q->select('a.*, ca.slug')->execute();

  Code will be still erroneous, because the new generated alias for table Category maps to the letter "c".
  So, to fix code sample, you need to replace "ca.slug" with "c.slug".

    [php]
    <?php

    $q->select('a.*, c.slug')->execute();

  If anybody can help me to elegantly solve this issue - I will be pleasantly thankful.

# 10. <a id="tdd">TDD</a>

  Tested basic functionality.

    [plain]

    [sfDoctrineTable] functional/backend/MethodExistanceTest.............ok
    [sfDoctrineTable] functional/backend/MethodWhereTest.................ok
    [sfDoctrineTable] functional/backend/TaskDepthTest...................ok
    [sfDoctrineTable] functional/backend/TaskGeneratorTest...............ok
    [sfDoctrineTable] functional/backend/TaskInvalidArgumentsTest........ok
    [sfDoctrineTable] functional/backend/TaskMinifiedTest................ok
    [sfDoctrineTable] functional/backend/TaskNoPhpDocTest................ok
    [sfDoctrineTable] functional/backend/TaskUninstallTest...............ok
     All tests successful.
     Files=8, Tests=149

# 11. <a id="misc">Misc</a>

## Requirements

  * PHP >= 5.3.* (because of [Late Static Bindings](http://php.net/manual/en/language.oop5.late-static-bindings.php "About Late Static Bindings on php.net"))

## Contacts ##

  * @: Ilya Sabelnikov `` <fruit dot dev at gmail dot com> ``
  * skype: ilya_roll