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
class SorterActiveRecordBehaviorFullFillTest extends CDbTestCase {

	/**
	 * @var string model name
	 */
	private static $className = 'SortestFullFill';

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
		$testdataPath = Yii::getPathOfAlias('sorter.tests.env.schema');
		$createTableSql = file_get_contents($testdataPath . '/sortest.sql');
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
		Yii::app()->db->createCommand('TRUNCATE TABLE sortest')->execute();
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
	public function testExtreemFill() {
		try {
			$i = 1;
			while (true) {	// cycle until an exception
				$model = $this->createModel();
				$model->name = $i;
				$this->assertTrue($model->save());
				++$i;
			}
		} catch (SorterOutOfFreeSortSpaceExeption $ex) {
			// mast to be COUNT == 510
			$this->assertEquals(62, Yii::app()->db->createCommand('SELECT COUNT(*) FROM sortest WHERE 1')->queryScalar());
			
			// compare array more faster and informative. generate its.
			$expectedArray = array();
			for($i = 1; $i <= 62; ++$i) {
				$expectedArray[] = array(
					'id' => $i,
					'name' => $i,
					'sort' => $i,
				);
			}
			
			// select all. assert order 1 to 62
			$resultArray = Yii::app()->db->createCommand('SELECT id, name, sort FROM sortest WHERE 1 ORDER BY sort ASC')->queryAll();
			$this->assertEquals($expectedArray, $resultArray);
		}
	}
}
