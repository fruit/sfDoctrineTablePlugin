<?php

  class TestTableGenerator extends sfDoctrineTableGenerator
  {
    public function initialize(sfGeneratorManager $generatorManager)
    {
      parent::initialize($generatorManager);

      $this->setGeneratorClass('TestTable');
    }
  }