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
 * It's functionality validation
 * work with on the fly normalisation
 */
class SorterActiveRecordBehaviorFuncExtreemTest extends CDbTestCase {

	/**
	 * @var string model name
	 */
	private static $className = 'SortestExtreemSmall';

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
		$schemaPath = Yii::getPathOfAlias('sorter.tests.env.schema');
		$createTableSql = file_get_contents($schemaPath . '/sortest.sql');
		Yii::app()->db->createCommand($createTableSql)->execute();

		// import models
		Yii::import('sorter.tests.env.models.*');
	}

	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 */
	protected function setUp() {
		
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
	 * @covers SorterActiveRecordBehavior::normalizeSortFieldOnthefly
	 * @covers SorterActiveRecordBehavior::normalizeSortFieldExtreme
	 */
	public function testStdOnthefly() {
		// load fixture
		$testdataPath = Yii::getPathOfAlias('sorter.tests.env.testdata');
		$createTableSql = file_get_contents($testdataPath . '/SortestSmallOnthefly.sql');
		Yii::app()->db->createCommand($createTableSql)->execute();
		
		// select all order by sort in array 
		$expected = Yii::app()->db->createCommand('SELECT id FROM sortest WHERE 1 ORDER BY sort ASC')->queryColumn();
		
		// add new element in db
		$model = $this->createModel();
		$model->name = 'insert';
		$model->sorterMoveToEnd(true);
		$this->assertTrue($model->save());
		
		$this->assertEquals(array_merge($expected, array($model->id)), Yii::app()->db->createCommand('SELECT id FROM sortest WHERE 1 ORDER BY sort ASC')->queryColumn());
		
		// all record not equal
		$this->assertNotEquals(126, $this->loadModel(1)->owner->sort);
		$this->assertNotEquals(128, $this->loadModel(2)->owner->sort);
		$this->assertNotEquals(300, $this->loadModel(88)->owner->sort);
		$this->assertNotEquals(509, $this->loadModel(159)->owner->sort);
		$this->assertNotEquals(510, $this->loadModel(160)->owner->sort);
	}
	
	/**
	 * @covers SorterActiveRecordBehavior::normalizeSortFieldOnthefly
	 * @covers SorterActiveRecordBehavior::normalizeSortFieldExtreme
	 */
	public function testStdOntheflyExtreem() {
		// load fixture
		$testdataPath = Yii::getPathOfAlias('sorter.tests.env.testdata');
		$createTableSql = file_get_contents($testdataPath . '/SortestSmallOntheflyExtreem.sql');
		Yii::app()->db->createCommand($createTableSql)->execute();
		
		// select all order by sort in array 
		$expected = Yii::app()->db->createCommand('SELECT id FROM sortest WHERE 1 ORDER BY sort ASC')->queryColumn();
		
		// add new element in db
		$model = $this->createModel();
		$model->name = 'insert';
		$model->sorterMoveToEnd(true);
		$this->assertTrue($model->save());
		
		$this->assertEquals(array_merge($expected, array($model->id)), Yii::app()->db->createCommand('SELECT id FROM sortest WHERE 1 ORDER BY sort ASC')->queryColumn());
		
		// all record not equal
		$this->assertNotEquals(2, $this->loadModel(1)->owner->sort);
		$this->assertNotEquals(4, $this->loadModel(2)->owner->sort);
		$this->assertNotEquals(280, $this->loadModel(140)->owner->sort);
		$this->assertNotEquals(509, $this->loadModel(255)->owner->sort);
		$this->assertNotEquals(510, $this->loadModel(256)->owner->sort);
	}
	
	/**
	 * @covers SorterActiveRecordBehavior::normalizeSortFieldOnthefly
	 * @covers SorterActiveRecordBehavior::normalizeSortFieldExtreme
	 */
	public function testStdRegularExtreem() {
		// load fixture
		$testdataPath = Yii::getPathOfAlias('sorter.tests.env.testdata');
		$createTableSql = file_get_contents($testdataPath . '/SortestSmallRegularExtreem.sql');
		Yii::app()->db->createCommand($createTableSql)->execute();
		
		// select all order by sort in array 
		$expected = Yii::app()->db->createCommand('SELECT id FROM sortest WHERE 1 ORDER BY sort ASC')->queryColumn();
		
		// add new element in db
		$this->createModel()->sorterNormalizeSortFieldRegular();
		
		$this->assertEquals($expected, Yii::app()->db->createCommand('SELECT id FROM sortest WHERE 1 ORDER BY sort ASC')->queryColumn());
	}
}
