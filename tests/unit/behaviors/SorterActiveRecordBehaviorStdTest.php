<?php

/**
 * SorterActiveRecordBehaviorStdTest class file.
 *
 * @author		Krivtsov Artur (wartur) <gwartur@gmail.com> http://wartur.ru | Made in Russia
 * @copyright	Krivtsov Artur © 2014
 * @link		https://github.com/wartur/yii-sorter
 * @license		https://github.com/wartur/yii-sorter/blob/master/LICENSE
 */

/**
 * SorterActiveRecordBehaviorStdTest
 * 
 * Generated by PHPUnit_SkeletonGenerator on 2014-11-10 at 11:23:59.
 */
class SorterActiveRecordBehaviorStdTest extends CDbTestCase {

	/**
	 * @var string model name
	 */
	private static $className = 'SortestStd';

	// =========================================================================
	// fixturesschema

	/**
	 * This method is called before the first test of this test class is run.
	 *
	 * @since Method available since Release 3.4.0
	 */
	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		// loading the database schema
		$testdataPath = Yii::getPathOfAlias('sorter.tests.env.testdata');
		$createTableSql = file_get_contents($testdataPath . '/' . self::$className . '.sql');
		Yii::app()->db->createCommand($createTableSql)->execute();

		// import models
		Yii::import('sorter.tests.env.models.*');
	}

	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 */
	protected function setUp() {
		// load fixture
		$testdataPath = Yii::getPathOfAlias('sorter.tests.env.testdata');
		$createTableSql = file_get_contents($testdataPath . '/' . self::$className . '.sql');
		Yii::app()->db->createCommand($createTableSql)->execute();
	}

	/**
	 * Tears down the fixture, for example, closes a network connection.
	 * This method is called after a test is executed.
	 */
	protected function tearDown() {
		parent::tearDown();
	}

	/**
	 * This method is called after the last test of this test class is run.
	 *
	 * @since Method available since Release 3.4.0
	 */
	public static function tearDownAfterClass() {
		parent::tearDownAfterClass();
	}

	// =========================================================================
	// tools

	/**
	 * fast extract id from CAcriveRecord array
	 * @param CAcriveRecord[] $param active records array
	 * @return array ids
	 */
	private static function extractIds(array $param) {
		$result = array();
		foreach ($param as $entry) {
			$result[] = $entry->id;
		}
		return $result;
	}

	/**
	 * @param int $id
	 * @return SorterActiveRecordBehavior
	 */
	public static function loadModel($id) {
		$className = self::$className;
		return $className::model()->findByPk($id);
	}

	/**
	 * @param array $conditions
	 * @return SorterActiveRecordBehavior
	 */
	public static function loadModelByConditions(array $conditions) {
		$className = self::$className;
		return $className::model()->find($conditions);
	}

	/**
	 * @return SorterActiveRecordBehavior
	 */
	public static function createModel() {
		return new self::$className();
	}

	// =========================================================================
	// tests

	/**
	 * @covers SorterActiveRecordBehavior::sorterSwappWith
	 */
	public function testSorterSwappWith() {
		// swap himself =)
		$modelHimself1 = $this->loadModel(1);
		$modelHimself2 = $this->loadModel(1);
		$modelHimself1->sorterSwappWith($modelHimself2);
		$this->assertEquals(1071677440, $modelHimself1->owner->sort);
		$this->assertEquals(1071677440, $modelHimself2->owner->sort);

		// swap neighbor
		$modelNeighbor1 = $this->loadModel(1);
		$modelNeighbor2 = $this->loadModel(2);
		$modelNeighbor1->sorterSwappWith($modelNeighbor2);
		$this->assertEquals(1071710208, $modelNeighbor1->owner->sort);
		$this->assertEquals(1071677440, $modelNeighbor2->owner->sort);

		// swap neighbor redo
		$modelNeighbor2->sorterSwappWith($modelNeighbor1);
		$this->assertEquals(1071677440, $modelNeighbor1->owner->sort);
		$this->assertEquals(1071710208, $modelNeighbor2->owner->sort);

		// swap other
		$modelOther1 = $this->loadModel(1);
		$modelOther2 = $this->loadModel(3);
		$modelOther1->sorterSwappWith($modelOther2);
		$this->assertEquals(1071742976, $modelOther1->owner->sort);
		$this->assertEquals(1071677440, $modelOther2->owner->sort);
	}

	/**
	 * Test moveUp if is new record
	 * @covers SorterActiveRecordBehavior::sorterSwappWith
	 * @expectedException SorterSaveErrorExeption
	 */
	public function testSorterSwappWithExceptionSaveFirst() {
		$model = $this->loadModel(1);
		$modelSwap = $this->loadModel(2);
		$model->owner->name = null;  // valudate error
		$model->sorterSwappWith($modelSwap);
	}

	/**
	 * Test moveUp if is new record
	 * @covers SorterActiveRecordBehavior::sorterSwappWith
	 * @expectedException SorterSaveErrorExeption
	 */
	public function testSorterSwappWithExceptionSaveSecond() {
		$model = $this->loadModel(1);
		$modelSwap = $this->loadModel(2);
		$modelSwap->owner->name = null; // valudate error in swapped model
		$model->sorterSwappWith($modelSwap);
	}

	/**
	 * @covers SorterActiveRecordBehavior::sorterMoveUp
	 */
	public function testSorterMoveUp() {
		$model = $this->loadModel(1);
		$this->assertEquals(1071677440, $model->owner->sort);
		$model->sorterMoveUp();
		$this->assertEquals(1071677440, $model->owner->sort);

		$modelSwapTest = $this->loadModel(2);
		$this->assertEquals(1071710208, $modelSwapTest->owner->sort);
		$modelSwapTest->sorterMoveUp();
		$this->assertEquals(1071677440, $modelSwapTest->owner->sort);
	}

	/**
	 * Test moveUp if is new record
	 * @covers SorterActiveRecordBehavior::sorterMoveUp
	 * @expectedException SorterOperationExeption
	 * @expectedExceptionMessage sorterCurrentMoveUp not support when it is new record
	 */
	public function testSorterMoveUpException() {
		$model = $this->createModel();
		$model->sorterMoveUp();
	}

	/**
	 * @covers SorterActiveRecordBehavior::sorterMoveDown
	 */
	public function testSorterMoveDown() {
		$model = $this->loadModel(127);
		$this->assertEquals(1075806208, $model->owner->sort);
		$model->sorterMoveDown();
		$this->assertEquals(1075806208, $model->owner->sort);

		$modelSwapTest = $this->loadModel(126);
		$this->assertEquals(1075773440, $modelSwapTest->owner->sort);
		$modelSwapTest->sorterMoveDown();
		$this->assertEquals(1075806208, $modelSwapTest->owner->sort);
	}

	/**
	 * Test moveDown if is new record
	 * @covers SorterActiveRecordBehavior::sorterMoveDown
	 * @expectedException SorterOperationExeption
	 * @expectedExceptionMessage sorterCurrentMoveDown not support when it is new record
	 */
	public function testSorterMoveDownException() {
		$model = $this->createModel();
		$model->sorterMoveDown();
	}

	/**
	 * @covers SorterActiveRecordBehavior::sorterMoveToBegin
	 * @covers SorterActiveRecordBehavior::normalizeSortFieldOnthefly
	 * @covers SorterActiveRecordBehavior::sorterSwappWith
	 * @covers SorterActiveRecordBehavior::insertFirst
	 */
	public function testSorterMoveToBegin() {
		$model = $this->loadModel(64);
		$this->assertEquals(1073741824, $model->owner->sort);
		$model->sorterMoveToBegin();
		$this->assertEquals(1071644672, $model->owner->sort); // by 32768
		//
		// it's end of free space. devide on 2
		$insert = $this->createModel();
		$insert->owner->sort = 32;
		$insert->owner->name = 'end of space';
		$this->assertTrue($insert->save());

		$modelGoToEndOfSpace = $this->loadModel(65);
		$this->assertEquals(1073774592, $modelGoToEndOfSpace->owner->sort);
		$modelGoToEndOfSpace->sorterMoveToBegin();
		$this->assertEquals(16, $modelGoToEndOfSpace->owner->sort);	// by 16

		$modelGoToEndOfSpace2 = $this->loadModel(66);
		$this->assertEquals(1073807360, $modelGoToEndOfSpace2->owner->sort);
		$modelGoToEndOfSpace2->sorterMoveToBegin();
		$this->assertEquals(8, $modelGoToEndOfSpace2->owner->sort);	// by 8

		// it's end of space test and noirmalisation
		$insertToEnd = $this->createModel();
		$insertToEnd->owner->sort = 1;
		$insertToEnd->owner->name = 'out of space';
		$this->assertTrue($insertToEnd->save());

		$modelGoToOutOfSpace = $this->loadModel(67);
		$this->assertEquals(1073840128, $modelGoToOutOfSpace->owner->sort); // src
		$modelGoToOutOfSpace->sorterMoveToBegin();
		$this->assertEquals(1071480832, $modelGoToOutOfSpace->owner->sort); // after insert
		//
		// normalisation test
		$modelInsertToEnd = $this->loadModel($insertToEnd->id);
		$this->assertEquals(1071513600, $modelInsertToEnd->owner->sort); // +32768
		$model66 = $this->loadModel(66);
		$this->assertEquals(1071546368, $model66->owner->sort); // +32768
		$model65 = $this->loadModel(65);
		$this->assertEquals(1071579136, $model65->owner->sort); // +32768
		$modelInsert = $this->loadModel($insert->id);
		$this->assertEquals(1071611904, $modelInsert->owner->sort);  // +32768
		$model64 = $this->loadModel(64);
		$this->assertEquals(1071644672, $model64->owner->sort); // +32768
		$model1 = $this->loadModel(1);
		$this->assertEquals(1071677440, $model1->owner->sort); // +32768 and ......
		//
		// delete all
		SortestStd::model()->deleteAll();

		// it's insert first tests
		$modelOnlySet = $this->createModel();
		$modelOnlySet->owner->name = 'onlyset1';
		$modelOnlySet->sorterMoveToBegin();
		$this->assertTrue($modelOnlySet->save());
		$this->assertEquals(1073741824, $modelOnlySet->owner->sort);

		// it's only set tests
		$modelOnlySet2 = $this->createModel();
		$modelOnlySet2->name = 'onlyset2';
		$modelOnlySet2->sorterMoveToBegin(true);
		$this->assertEquals(1073709056, $modelOnlySet2->owner->sort); // HEADS UP!!! it's before save
		$this->assertTrue($modelOnlySet2->save());

		// it's test for swap optimisation in moveToBegin method
		$modelOnlySet->sorterMoveToBegin();
		$this->assertEquals(1073709056, $modelOnlySet->owner->sort);
		$modelOnlySet2After = $this->loadModel($modelOnlySet2->id);
		$this->assertEquals(1073741824, $modelOnlySet2After->owner->sort);

		// it's test for moveToBegin optimisation if this record is fitst
		$modelOnlySet->sorterMoveToBegin();
		$this->assertEquals(1073709056, $modelOnlySet->owner->sort);
	}

	/**
	 * Test insertFirst exception (private method)
	 * @covers SorterActiveRecordBehavior::insertFirst
	 * @expectedException SorterSaveErrorExeption
	 */
	public function testSorterInsertFirstException() {
		SortestStd::model()->deleteAll();

		// it's insert first tests
		$model = $this->createModel();
		$model->owner->name = null;
		$model->sorterMoveToBegin();
	}

	/**
	 * @covers SorterActiveRecordBehavior::sorterMoveToEnd
	 * @covers SorterActiveRecordBehavior::normalizeSortFieldOnthefly
	 * @covers SorterActiveRecordBehavior::sorterSwappWith
	 * @covers SorterActiveRecordBehavior::insertFirst
	 * @todo   Implement testSorterMoveToEnd().
	 */
	public function testSorterMoveToEnd() {
		$model = $this->loadModel(64);
		$this->assertEquals(1073741824, $model->owner->sort);
		$model->sorterMoveToEnd();
		$this->assertEquals(1075838976, $model->owner->sort); // by 32768
		//
		// it's end of free space. devide on 2
		$insert = $this->createModel();
		$insert->owner->sort = 2147483615;
		$insert->owner->name = 'end of space';
		$this->assertTrue($insert->save());

		$modelGoToEndOfSpace = $this->loadModel(65);
		$this->assertEquals(1073774592, $modelGoToEndOfSpace->owner->sort);
		$modelGoToEndOfSpace->sorterMoveToEnd();
		$this->assertEquals(2147483631, $modelGoToEndOfSpace->owner->sort);	// by 16

		$modelGoToEndOfSpace2 = $this->loadModel(66);
		$this->assertEquals(1073807360, $modelGoToEndOfSpace2->owner->sort);
		$modelGoToEndOfSpace2->sorterMoveToEnd();
		$this->assertEquals(2147483639, $modelGoToEndOfSpace2->owner->sort); // by 8

		// it's end of space test and noirmalisation
		$insertToEnd = $this->createModel();
		$insertToEnd->owner->sort = 2147483646;
		$insertToEnd->owner->name = 'out of space';
		$this->assertTrue($insertToEnd->save());

		/*
		$modelGoToOutOfSpace = $this->loadModel(67);
		$this->assertEquals(1073840128, $modelGoToOutOfSpace->owner->sort); // src
		$modelGoToOutOfSpace->sorterMoveToEnd();
		$this->assertEquals(1071480832, $modelGoToOutOfSpace->owner->sort); // after insert
		//
		// normalisation test
		$modelInsertToEnd = $this->loadModel($insertToEnd->id);
		$this->assertEquals(1071513600, $modelInsertToEnd->owner->sort); // +32768
		$model66 = $this->loadModel(66);
		$this->assertEquals(1071546368, $model66->owner->sort); // +32768
		$model65 = $this->loadModel(65);
		$this->assertEquals(1071579136, $model65->owner->sort); // +32768
		$modelInsert = $this->loadModel($insert->id);
		$this->assertEquals(1071611904, $modelInsert->owner->sort);  // +32768
		$model64 = $this->loadModel(64);
		$this->assertEquals(1071644672, $model64->owner->sort); // +32768
		$model1 = $this->loadModel(1);
		$this->assertEquals(1071677440, $model1->owner->sort); // +32768 and ......
		//
		// delete all
		SortestStd::model()->deleteAll();

		// it's insert first tests
		$modelOnlySet = $this->createModel();
		$modelOnlySet->owner->name = 'onlyset1';
		$modelOnlySet->sorterMoveToEnd();
		$this->assertTrue($modelOnlySet->save());
		$this->assertEquals(1073741824, $modelOnlySet->owner->sort);

		// it's only set tests
		$modelOnlySet2 = $this->createModel();
		$modelOnlySet2->name = 'onlyset2';
		$modelOnlySet2->sorterMoveToEnd(true);
		$this->assertEquals(1073709056, $modelOnlySet2->owner->sort); // HEADS UP!!! it's before save
		$this->assertTrue($modelOnlySet2->save());

		// it's test for swap optimisation in moveToBegin method
		$modelOnlySet->sorterMoveToEnd();
		$this->assertEquals(1073709056, $modelOnlySet->owner->sort);
		$modelOnlySet2After = $this->loadModel($modelOnlySet2->id);
		$this->assertEquals(1073741824, $modelOnlySet2After->owner->sort);

		// it's test for moveToBegin optimisation if this record is fitst
		$modelOnlySet->sorterMoveToEnd();
		$this->assertEquals(1073709056, $modelOnlySet->owner->sort);
		 */
		
		// end mod2 round test
	}

	/**
	 * @covers SorterActiveRecordBehavior::sorterMoveUpNumber
	 * @todo   Implement testSorterMoveUpNumber().
	 */
	public function testSorterMoveUpNumber() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
				'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers SorterActiveRecordBehavior::sorterMoveDownNumber
	 * @todo   Implement testSorterMoveDownNumber().
	 */
	public function testSorterMoveDownNumber() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
				'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers SorterActiveRecordBehavior::sorterMoveAfter
	 * @todo   Implement testSorterMoveAfter().
	 */
	public function testSorterMoveAfter() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
				'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers SorterActiveRecordBehavior::sorterMoveBefore
	 * @todo   Implement testSorterMoveBefore().
	 */
	public function testSorterMoveBefore() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
				'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers SorterActiveRecordBehavior::sorterMoveToPositionBefore
	 * @todo   Implement testSorterMoveToPositionBefore().
	 */
	public function testSorterMoveToPositionBefore() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
				'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers SorterActiveRecordBehavior::sorterMoveToPositionAfter
	 * @todo   Implement testSorterMoveToPositionAfter().
	 */
	public function testSorterMoveToPositionAfter() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
				'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers SorterActiveRecordBehavior::sorterChangeIdsOrderTo
	 * @expectedException CException
	 * @expectedExceptionMessage Not Implemented Exception
	 */
	public function testSorterChangeIdsOrderTo() {
		$model = $this->createModel();
		$model->sorterChangeIdsOrderTo(array());
	}

	/**
	 * @covers SorterActiveRecordBehavior::sorterInverseAll
	 * @expectedException CException
	 * @expectedExceptionMessage Not Implemented Exception
	 */
	public function testSorterInverseAll() {
		$model = $this->createModel();
		$model->sorterInverseAll();
	}

	/**
	 * @covers SorterActiveRecordBehavior::sorterNormalizeSortFieldRegular
	 * @todo   Implement testSorterNormalizeSortFieldRegular().
	 */
	public function testSorterNormalizeSortFieldRegular() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
				'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers SorterActiveRecordBehavior::sorterSetNextInsertSortValue
	 * @todo   Implement testSorterSetNextInsertSortValue().
	 */
	public function testSorterSetNextInsertSortValue() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
				'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers SorterActiveRecordBehavior::beforeValidate
	 * @todo   Implement testBeforeValidate().
	 */
	public function testBeforeValidate() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
				'This test has not been implemented yet.'
		);
	}

	/**
	 * @covers SorterActiveRecordBehavior::afterConstruct
	 * @todo   Implement testAfterConstruct().
	 */
	public function testAfterConstruct() {
		// Remove the following lines when you implement this test.
		$this->markTestIncomplete(
				'This test has not been implemented yet.'
		);
	}

}
