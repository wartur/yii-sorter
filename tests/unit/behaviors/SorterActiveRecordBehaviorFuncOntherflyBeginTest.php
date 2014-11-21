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
class SorterActiveRecordBehaviorFuncOntherflyBeginTest extends CDbTestCase {

	/**
	 * @var string model name
	 */
	private static $className = 'SortestOntheflyBeginStd';

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
	 * @covers SorterActiveRecordBehavior::normalizeSortFieldOnthefly
	 * @covers SorterActiveRecordBehavior::findNewFreeSortSpaceBitSizeByDiff
	 * @covers SorterActiveRecordBehavior::centralWeightFirstElement
	 */
	public function testOntheflyNormalisation() {
		$srcBefore = Yii::app()->db->createCommand('SELECT id FROM sortest WHERE sort <= 1071972351 ORDER BY sort ASC')->queryColumn();
		$srcAfter = Yii::app()->db->createCommand('SELECT id FROM sortest WHERE sort > 1071972351 ORDER BY sort ASC')->queryColumn();

		$model = $this->createModel();
		$model->name = 'insert';
		$model->sorterMoveBefore(10, true);
		$this->assertTrue($model->save());

		// compare order
		$this->assertEquals(array_merge($srcBefore, array($model->id), $srcAfter), Yii::app()->db->createCommand('SELECT id FROM sortest WHERE 1 ORDER BY sort ASC')->queryColumn());
		$this->assertEquals(1071972352, $this->loadModel(10)->owner->sort); // is end side normalisation sort not changd
		$this->assertNotEquals(1071972351, $this->loadModel(128)->owner->sort); // sort must changed after normalisation
	}

}
