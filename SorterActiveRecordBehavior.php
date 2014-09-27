<?php

/**
 * SorterActiveRecordBehavior class file.
 *
 * @author Krivtsov Artur (wartur) <gwartur@gmail.com> | Made in Russia
 * @copyright Krivtsov Artur © 2014
 * @link https://github.com/wartur/yii-sorter-behavior
 * @license New BSD license
 */

/**
 * Behavior for custom sorting
 * 
 * @author Krivtsov Artur (wartur) <gwartur@gmail.com> | Made in Russia
 * @since v1.0.0
 * @link https://github.com/wartur/yii-sorter-behavior/blob/master/ALGORITHM.md
 */
class SorterActiveRecordBehavior extends CActiveRecordBehavior {

	/**
	 * New record will be inserted to begin
	 */
	const DEFAULT_INSERT_TO_BEGIN = false;

	/**
	 * New record will be inserted to end
	 */
	const DEFAULT_INSERT_TO_END = true;

	/**
	 * The algorithm will generate the maximum number of requests and save the maximum amount of discharge space
	 * @todo v1.2.1
	 */
	const DISCHARGE_SAFE_POLITIC_MAXSAFE = true;

	/**
	 * The algorithm will generate the small number of requests and save the small amount of discharge space
	 * @todo v1.2.1
	 */
	const DISCHARGE_SAFE_POLITIC_FASTSPEED = false;

	/**
	 * @var string class sort field
	 * Internally used as `ORDER BY sort ASC`
	 */
	public $sortField = 'sort';

	/**
	 * @var string class group field
	 * If use this field then the algorithm does all calculation in diapason of each group field
	 * It is believed that there is a unique key in the fields groupField and sortField
	 * 
	 * @todo 1.2.0
	 */
	public $groupField = null;

	/**
	 * @var boolean position for insert new record
	 */
	public $defaultInsertToEnd = self::DEFAULT_INSERT_TO_END;

	/**
	 * @var boolean politic for safe discharge space
	 * @todo 1.2.1
	 */
	public $dischargeSafePolitic = self::DISCHARGE_SAFE_POLITIC_MAXSAFE;

	/**
	 * @var int bit size for sort field
	 * on x86 php max is 30 (signed int).
	 * on x64 php max is 62 (signed bigint)
	 * ALGORITHM.md
	 */
	public $sortFieldBitSize = 30;

	/**
	 * @var int gap bit-size when insert new record
	 * See ALGORITHM.md
	 */
	public $dischargeSpaceBitSize = 15;

	/**
	 * @var int gap bit-size. Used in normalization method.
	 * Min local discharge space until run deep table search
	 * See ALGORITHM.md
	 */
	public $minLocalDischargeSpaceBitSize = 4;

	/**
	 * @var int package size for optimize app server load.
	 * 
	 * See ALGORITHM.md
	 */
	public $packageSize = 200;

	/**
	 * Swap current record with $model record
	 * @param CActiveRecord $model
	 * @throws CException save exception
	 */
	public function sorterSwappWith(CActiveRecord $model) {
		$sortOwner = $this->owner->{$this->sortField};

		$this->owner->{$this->sortField} = 0;
		if (!$this->owner->save()) {
			throw new CException(Yii::t('SorterActiveRecordBehavior', 'sortField = 0 not save correctly. Error data: $this->owner => {errors}', array('{errors}' => CVarDumper::dumpAsString($this->owner->getErrors()))));
		}

		$this->owner->{$this->sortField} = $model->{$this->sortField};
		$model->{$this->sortField} = $sortOwner;

		if (!($model->save() && $this->owner->save())) {
			throw new CException(Yii::t('SorterActiveRecordBehavior', 'swapp not save correctly. Error data: $this->owner => {errorsOwner}, $model => {errorsModel}', array('{errorsOwner}' => CVarDumper::dumpAsString($this->owner->getErrors()), '{errorsModel}' => CVarDumper::dumpAsString($model->getErrors()))));
		}
	}

	/**
	 * Move up this record relatively sortField ASC
	 * 
	 * it's swapp algorithm. Some RDBM not support swapp (like MySQL).
	 * We do swapp through app server
	 */
	public function sorterCurrentMoveUp() {
		if ($this->owner->getIsNewRecord()) {
			throw new CException(Yii::t('SorterActiveRecordBehavior', 'sorterCurrentMoveUp not support when it is new record'));
		}

		$upModel = $this->owner->model()->find(array(
			'condition' => "t.{$this->sortField} < :sort",
			'order' => "t.{$this->sortField} DESC",
			'params' => array('sort' => $this->owner->{$this->sortField}),
		));

		if (isset($upModel)) {
			$this->sorterSwappWith($upModel);
		}
	}

	/**
	 * Move down this record relatively sortField ASC
	 * 
	 * it's swapp algorithm. Some RDBM not support swapp (like MySQL).
	 * We do swapp through app server
	 */
	public function sorterCurrentMoveDown() {
		if ($this->owner->getIsNewRecord()) {
			throw new CException(Yii::t('SorterActiveRecordBehavior', 'sorterCurrentMoveDown not support when it is new record'));
		}

		$downModel = $this->owner->model()->find(array(
			'condition' => "t.{$this->sortField} > :sort",
			'order' => "t.{$this->sortField} ASC",
			'params' => array('sort' => $this->owner->{$this->sortField}),
		));

		if (isset($downModel)) {
			$this->sorterSwappWith($downModel);
		}
	}

	/**
	 * Move to begin. Public implementation
	 */
	public function sorterCurrentMoveToBegin($onlySet = false) {
		$records = $this->owner->model()->findAll(array(
			'limit' => 2,
			'order' => "t.{$this->sortField} ASC",
		));

		if (empty($records)) {
			$this->insertFirst($onlySet);
		} else {
			if (isset($records[1]) && $records[1]->getPrimaryKey() == $this->owner->getPrimaryKey()) {
				// если это операция вставки новой записи, то она никогда не произойдет так как getPrimaryKey => null
				$this->sorterSwappWith($records[0]); // swap
			} else {
				$this->moveToBeginFast($records[0]->{$this->sortField}, $onlySet); // standart move
			}
		}
	}

	/**
	 * Move to end. Public implementation
	 */
	public function sorterCurrentMoveToEnd($onlySet = false) {
		$records = $this->owner->model()->findAll(array(
			'limit' => 2,
			'order' => "t.{$this->sortField} DESC",
		));

		if (empty($records)) {
			$this->insertFirst($onlySet);
		} else {
			if (isset($records[1]) && $records[1]->getPrimaryKey() == $this->owner->getPrimaryKey()) {
				// если это операция вставки новой записи, то она никогда не произойдет так как getPrimaryKey => null
				$this->sorterSwappWith($records[0]); // swap
			} else {
				$this->moveToEndFast($records[0]->{$this->sortField}, $onlySet); // standart move
			}
		}
	}

	/**
	 * Move current record up on count of position relatively sortField ASC
	 * @param int $number count of record to move
	 */
	public function sorterCurrentMoveUpNumber($number) {
		if ($this->owner->getIsNewRecord()) {
			throw new CException(Yii::t('SorterActiveRecordBehavior', 'sorterCurrentMoveUpNumber not support when it is new record'));
		}

		if ($number < 0) { // process negative
			$this->sorterCurrentMoveDownNumber(-$number);
		} elseif ($number == 1) { // optimize. swapp it
			$this->sorterCurrentMoveUp();
		} elseif ($number > 1) { // move at several records
			$condition = new CDbCriteria();
			$condition->addCondition("t.{$this->sortField} <= :sort");
			$condition->order = "t.{$this->sortField} DESC";
			$condition->offset = $number;
			$condition->limit = 2;
			$condition->params['sort'] = $this->owner->{$this->sortField};

			$upModels = $this->owner->model()->findAll($condition);

			$count = count($upModels);
			if ($count == 0) { // 0, to move first
				$this->sorterCurrentMoveToBegin();
			} elseif ($count == 1) { // 1, to move first
				$this->moveToBeginFast($upModels[0]->{$this->sortField});
			} elseif ($count == 2) {
				$this->moveBetween($upModels[0]->{$this->sortField}, $upModels[1]->{$this->sortField});
			}
		}
	}

	/**
	 * Move current record down on count of position relatively sortField ASC
	 * @param int $number count of record to move
	 */
	public function sorterCurrentMoveDownNumber($number) {
		if ($this->owner->getIsNewRecord()) {
			throw new CException(Yii::t('SorterActiveRecordBehavior', 'sorterCurrentMoveDownNumber not support when it is new record'));
		}

		if ($number < 0) { // process negative
			$this->sorterCurrentMoveUpNumber(-$number);
		} elseif ($number == 1) { // optimize. swapp it
			$this->sorterCurrentMoveDown();
		} elseif ($number > 1) { // move at several records
			$condition = new CDbCriteria();
			$condition->addCondition("t.{$this->sortField} >= :sort");
			$condition->order = "t.{$this->sortField} ASC";
			$condition->offset = $number;
			$condition->limit = 2;
			$condition->params['sort'] = $this->owner->{$this->sortField};

			$downModels = $this->owner->model()->findAll($condition);

			$count = count($downModels);
			if ($count == 0) { // 0, to move first
				$this->sorterCurrentMoveToEnd();
			} elseif ($count == 1) { // 1, to move first
				$this->moveToEndFast($downModels[0]->{$this->sortField});
			} elseif ($count == 2) {
				$this->moveBetween($downModels[0]->{$this->sortField}, $downModels[1]->{$this->sortField});
			}
		}
	}

	/**
	 * Replace current record after pk relatively sortField ASC
	 * @param mixed $pk insert after this pk
	 */
	public function sorterCurrentMoveAfter($pk, $onlySet = false) {

		// cross method optimisation
		if ($pk instanceof CActiveRecord) {
			$movePlaceAfterModel = $pk;

			if ($this->owner->getPrimaryKey() == $movePlaceAfterModel->getPrimaryKey()) { // not need replace after own.
				return null;
			}
		} else {
			if ($this->owner->getPrimaryKey() == $pk) { // not need replace after own.
				return null;
			}

			$movePlaceAfterModel = $this->owner->model()->findByPk($pk);
			if (empty($movePlaceAfterModel)) {
				throw new SorterKeyNotFindExeption(Yii::t('SorterActiveRecordBehavior', 'pk({pk}) not find in db', array('{pk}' => $pk)));
			}
		}

		$beforeModel = $this->owner->model()->find(array(
			'condition' => "t.{$this->sortField} < :sort",
			'order' => "t.{$this->sortField} DESC",
			'params' => array(
				'sort' => $movePlaceAfterModel->{$this->sortField}
			)
		));

		if (isset($beforeModel) && $beforeModel->getPrimaryKey() == $this->owner->getPrimaryKey()) {
			$this->sorterSwappWith($movePlaceAfterModel);
		} else {
			$afterPlaceModels = $this->owner->model()->findAll(array(
				'condition' => "t.{$this->sortField} > :sort",
				'order' => "t.{$this->sortField} ASC",
				'limit' => 2,
				'params' => array(
					'sort' => $movePlaceAfterModel->{$this->sortField}
				)
			));

			if (empty($afterPlaceModels)) { // nothing not find - is end
				$this->moveToEndFast($movePlaceAfterModel->{$this->sortField}, $onlySet);
			} elseif (isset($afterPlaceModels[0]) && $afterPlaceModels[0]->getPrimaryKey() == $this->owner->getPrimaryKey()) {
				// do nothing... allready this
			} elseif (isset($afterPlaceModels[1]) && $afterPlaceModels[1]->getPrimaryKey() == $this->owner->getPrimaryKey()) {
				// is next after this
				$this->sorterSwappWith($afterPlaceModels[0]);
			} else {
				// between $afterPlaceModels[0] && $movePlaceAfterModel
				$this->moveBetween($afterPlaceModels[0]->{$this->sortField}, $movePlaceAfterModel->{$this->sortField}, $onlySet);
			}
		}
	}

	/**
	 * Replace current record before pk relatively sortField ASC
	 * @param mixed $pk insert before this pk
	 */
	public function sorterCurrentMoveBefore($pk, $onlySet = false) {

		// cross method optimisation
		if ($pk instanceof CActiveRecord) {
			$movePlaceBeforeModel = $pk;

			if ($this->owner->getPrimaryKey() == $movePlaceBeforeModel->getPrimaryKey()) { // not need replace after own.
				return null;
			}
		} else {
			if ($this->owner->getPrimaryKey() == $pk) { // not need replace after own.
				return null;
			}

			$movePlaceBeforeModel = $this->owner->model()->findByPk($pk);
			if (empty($movePlaceBeforeModel)) {
				throw new SorterKeyNotFindExeption(Yii::t('SorterActiveRecordBehavior', 'pk({pk}) not find in db', array('{pk}' => $pk)));
			}
		}

		$afterModel = $this->owner->model()->find(array(
			'condition' => "t.{$this->sortField} > :sort",
			'order' => "t.{$this->sortField} ASC",
			'params' => array(
				'sort' => $movePlaceBeforeModel->{$this->sortField}
			)
		));

		if (isset($afterModel) && $afterModel->getPrimaryKey() == $this->owner->getPrimaryKey()) {
			$this->sorterSwappWith($movePlaceBeforeModel);
		} else {
			$beforePlaceModels = $this->owner->model()->findAll(array(
				'condition' => "t.{$this->sortField} < :sort",
				'order' => "t.{$this->sortField} DESC",
				'limit' => 2,
				'params' => array(
					'sort' => $movePlaceBeforeModel->{$this->sortField}
				)
			));

			if (empty($beforePlaceModels)) { // nothing not find - is end
				$this->moveToBeginFast($movePlaceBeforeModel->{$this->sortField}, $onlySet);
			} elseif (isset($beforePlaceModels[0]) && $beforePlaceModels[0]->getPrimaryKey() == $this->owner->getPrimaryKey()) {
				// do nothing... allready this
			} elseif (isset($beforePlaceModels[1]) && $beforePlaceModels[1]->getPrimaryKey() == $this->owner->getPrimaryKey()) {
				// is next after this
				$this->sorterSwappWith($beforePlaceModels[0]);
			} else {
				// between $afterPlaceModels[0] && $movePlaceAfterModel
				$this->moveBetween($beforePlaceModels[0]->{$this->sortField}, $movePlaceBeforeModel->{$this->sortField}, $onlySet);
			}
		}
	}

	/**
	 * 
	 * @param integer $position
	 */
	public function sorterCurrentMoveToPositionBefore($position, $onlySet = false) {
		if ($position < 2) {
			$this->sorterCurrentMoveToBegin();
		} else {
			$model = $this->owner->model()->find(array(
				'order' => "t.{$this->sortField} ASC",
				'offset' => $position - 1,
			));

			if (empty($model)) {
				$this->sorterCurrentMoveToEnd($onlySet);
			} else {
				// находим позицию.
				// делаем moveBefore
				$this->sorterCurrentMoveBefore($model->getPrimaryKey(), $onlySet);
			}
		}
	}

	/**
	 * 
	 * @param integer $position
	 */
	public function sorterCurrentMoveToPositionAfter($position, $onlySet = false) {
		if ($position < 1) {
			$this->sorterCurrentMoveToBegin();
		} else {
			$model = $this->owner->model()->find(array(
				'order' => "t.{$this->sortField} ASC",
				'offset' => $position - 1,
			));

			if (empty($model)) {
				// позиция не найдена. Вставляем в конец.
				$this->sorterCurrentMoveToEnd($onlySet);
			} else {
				// находим позицию.
				// делаем moveBefore
				$this->sorterCurrentMoveAfter($model->getPrimaryKey(), $onlySet);
			}
		}
	}

	/**
	 * Change order by $idsAfter order set
	 * Using in jQuery sortable widget
	 * Algorithm not reduce dischargeSpace
	 * 
	 * @todo 1.1.0 implements sortable model
	 * 
	 * @param array $idsAfter new order ids
	 * @throws CException
	 */
	public function sorterChangeIdsOrderTo(array $idsAfter) {
		throw new CException(Yii::t('SorterActiveRecordBehavior', 'Not Implemented Exception'));
	}

	/**
	 * Physical inverse order in table
	 * 
	 * @todo 1.1.0 implements sortable model
	 */
	public function sorterInverseAll() {
		// берем 1<<30 и вычитаем из него текущее значение, получаем инверсию
		//UPDATE ALL 1<<30 - $this->owner->{$this->sorterField};
	}

	/**
	 * Normalize fort field - regular operation
	 * @param integer $insertAfterSortValue after normalisation insert current record after this record id
	 */
	public function sorterNormalizeSortFieldRegular($upSortFieldValue = null, $downSortFieldValue = null) {
		
		if(isset($upSortFieldValue) && isset($downSortFieldValue)) {
			// найти эти 2 записи. по одной в каждую сторону и сохранить айдишники
			$modelUpOld = $this->owner->model()->find(array(
				'condition' => "t.{$this->sortField} <= :upSortFieldValue",
				'order' => "t.{$this->sortField} ASC",
				'params' => compact('upSortFieldValue')
			));
			
			$modelDownOld = $this->owner->model()->find(array(
				'condition' => "t.{$this->sortField} >= :downSortFieldValue",
				'order' => "t.{$this->sortField} ASC",
				'params' => compact('downSortFieldValue')
			));
			
			$afterInsertSortId = empty($modelUpOld) ? 0 : $modelDownOld->getPrimaryKey();
		} else {
			$afterInsertSortId = null;
		}
		
		// default 15 bit
		//32767 + 32767 + 1 = 65535;
		$dischargeSpaceBitSizeNatural = 1 << $this->dischargeSpaceBitSize;
		$maxNamberOfRecord = $dischargeSpaceBitSizeNatural - 1 + $dischargeSpaceBitSizeNatural;
		$currentRecordCount = $this->owner->model()->count();

		// if current is new значит приделать к количеству
		if (isset($upSortFieldValue) && isset($downSortFieldValue) && $this->owner->getIsNewRecord()) {
			++$currentRecordCount;
		}

		if ($maxNamberOfRecord < $currentRecordCount) {
			$this->normalizeSortFieldExtreme($currentRecordCount, $afterInsertSortId);
		} else {
			$this->distributeNewDischargeSpaceBitSize($this->dischargeSpaceBitSize, $currentRecordCount, $afterInsertSortId);
		}

		// загружаем еще раз 2 записи по их айдишникам
		// возвращаем новый миддл между ними
		if(isset($upSortFieldValue) && isset($downSortFieldValue)) {
			if(empty($modelUpOld)) {
				$newUpSortFieldValue = 0;
			} else {
				$newUpSortFieldValue = $this->owner->model()->findByPk($modelUpOld->getPrimaryKey())->{$this->sortField};
			}
			
			if(empty($modelDownOld)) {
				$sortFieldBitSizeNatural = 1 << $this->sortFieldBitSize;
				$newDownSortFieldValue = $sortFieldBitSizeNatural - 1 + $sortFieldBitSizeNatural;
			} else {
				$newDownSortFieldValue = $this->owner->model()->findByPk($modelDownOld->getPrimaryKey())->{$this->sortField};
			}
			
			return ($newUpSortFieldValue >> 1) + ($newUpSortFieldValue >> 1) + ($newUpSortFieldValue & $newUpSortFieldValue & 1);
		} else {
			return true;
		}
	}

	/**
	 * Normalize fort field on thr fly
	 * See ALGORITHM.md
	 * 
	 * @param integer $conflictSortValue conflict sort value for resolve
	 * @param integer $doubleSearchMultiplier double search level. Default no double search
	 * @return integer free space sortValue for insert 
	 * @throws CException
	 */
	protected function sorterNormalizeSortFieldOnthefly($sortFieldA, $sortFieldB, $doubleSearchMultiplier = 1) {
		if ($doubleSearchMultiplier < 1) {
			throw new CException(Yii::t('SorterActiveRecordBehavior', '$doubleSearchMultiply({doubleSearchMultiply}) must to be >= 1', array('{doubleSearchMultiply}' => $doubleSearchMultiplier)));
		}

		if ($sortFieldA == $sortFieldB) {
			throw new CException(Yii::t('SorterActiveRecordBehavior', '$sortFieldA({sortFieldA}) and $sortFieldB({sortFieldB}) cant be equal', array('{sortFieldA}' => $sortFieldA, '{sortFieldB}' => $sortFieldB)));
		}

		if ($sortFieldA < $sortFieldB) {
			$upSortFieldValue = $sortFieldA;
			$downSortFieldValue = $sortFieldB;
		} else {
			$upSortFieldValue = $sortFieldB;
			$downSortFieldValue = $sortFieldA;
		}

		$usingMin = false;
		$beforeSortFieldValue = $this->owner->dbConnection->createCommand()
				->select($this->sortField)
				->from($this->owner->tableName())
				->where("{$this->sortField} <= :upRecord")
				->order("{$this->sortField} DESC")
				->limit(1, $this->dischargeSpaceBitSize * $doubleSearchMultiplier)
				->queryScalar(compact('upRecord'));
		if (empty($beforeSortFieldValue)) {
			$usingMin = true;
			$beforeSortFieldValue = $this->owner->getDbConnection()->createCommand()
					->select("MIN({$this->sortField})")
					->from($this->owner->tableName())
					->queryScalar();
			if (empty($beforeSortFieldValue)) { // 0 or false is bad. stop work!!!
				throw new CException(Yii::t('SorterActiveRecordBehavior', 'Unexpected $beforeSortFieldValue({beforeSortFieldValue}). Must be >= 1', array('{beforeSortFieldValue}' => $beforeSortFieldValue)));
			}
		}

		$usingMax = false;
		$afterSortFieldValue = $this->owner->dbConnection->createCommand()
				->select($this->sortField)
				->from($this->owner->tableName())
				->where("{$this->sortField} >= :downRecord")
				->order("{$this->sortField} ASC")
				->limit(1, $this->dischargeSpaceBitSize * $doubleSearchMultiplier)
				->queryScalar(compact('downRecord'));
		if (empty($afterSortFieldValue)) {
			$usingMax = true;
			$afterSortFieldValue = $this->owner->getDbConnection()->createCommand()
					->select("MAX({$this->sortField})")
					->from($this->owner->tableName())
					->queryScalar();
			if (empty($afterSortFieldValue)) { // 0 or false is bad. stop work!!!
				throw new CException(Yii::t('SorterActiveRecordBehavior', 'Unexpected $afterSortFieldValue({afterSortFieldValue}). Must be >= 1', array('{afterSortFieldValue}' => $afterSortFieldValue)));
			}
		}

		$currentDiff = $afterSortFieldValue - $beforeSortFieldValue;

		// find local discharge space bit size
		$findDiff = $findBitSpaceSize = null;
		for ($bitSpaceSize = $this->dischargeSpaceBitSize - 1; $bitSpaceSize >= $this->minLocalDischargeSpaceBitSize; --$bitSpaceSize) { // от меньшего шага к большему
			$naturalSpaceSize = 1 << $bitSpaceSize;

			$localSearchDiffSize = $this->dischargeSpaceBitSize * $doubleSearchMultiplier * 2 * $naturalSpaceSize + $naturalSpaceSize;

			if ($localSearchDiffSize < $currentDiff) {
				$findDiff = $localSearchDiffSize;
				$findBitSpaceSize = $bitSpaceSize;
				break;
			}
		}

		// not find local space. It's too large degradation, need deep search
		if ($findDiff === null) {
			if ($usingMin && $usingMax) { // is maximum deep search
				return $this->sorterNormalizeSortFieldRegular($upSortFieldValue, $downSortFieldValue);
			} else {
				// next deep search level
				return $this->sorterNormalizeSortFieldOnthefly($upSortFieldValue, $downSortFieldValue, $doubleSearchMultiplier + 1);
			}
		} else { // normalize procedure
			$newElementPlaceSortValue = ($beforeSortFieldValue + $afterSortFieldValue) >> 1;

			// set sort field to negative for selected records, for unique conflict
			$this->owner->model()->updateAll(array($this->sortField => new CDbExpression("-t.{$this->sortField}")), array(
				"t.{$this->sortField} >= :beforeSortFieldValue AND t.{$this->sortField} <= :afterSortFieldValue",
				'params' => compact('beforeSortFieldValue', 'afterSortFieldValue'),
			));

			// set all vars as negative. order will negative too.
			$beforeSortFieldValueNegative = -$beforeSortFieldValue;
			$afterSortFieldValueNegative = -$afterSortFieldValue;
			$upRecordNegative = -$upSortFieldValue + 1;
			$downRecordNegative = -$downSortFieldValue - 1;
			$naturalFindBitSpaceSize = 1 << $findBitSpaceSize;

			$condition = new CDbCriteria();
			$condition->limit = $this->packageSize;

			// *** PACKAGED UPDATE ALGORITHM FOR UP RECORD *** ///
			$condition->addCondition(":upRecordNegative < t.{$this->sortField} AND t.{$this->sortField} <= :beforeSortFieldValueNegative");
			$condition->order = "t.{$this->sortField} ASC";
			$condition->params = compact('upRecordNegative', 'beforeSortFieldValueNegative');

			$newElementPlaceSortValueCopyUp = $newElementPlaceSortValue;
			do { // package
				$models = $this->owner->model()->findAll($condition);

				$lastUpRecordNegative = null;
				foreach ($models as $entry) { // update
					$newElementPlaceSortValueCopyUp += $naturalFindBitSpaceSize;
					$lastUpRecordNegative = $entry->{$this->sortField};
					$entry->{$this->sortField} = $newElementPlaceSortValueCopyUp;
					if (!$entry->save()) {
						throw new CException(Yii::t('SorterActiveRecordBehavior', 'sorterNormalizeSortFieldEmergency model save error. Error data: $entry =>' . CVarDumper::dumpAsString($entry->getErrors())));
					}
				}

				// next package
				if (isset($lastUpRecordNegative)) {
					$condition->params['upRecordNegative'] = $lastUpRecordNegative; // is more faster then $condition->offset += $this->pacakgeSize
				} else {
					break; // if !isset then count($models) = 0
				}
			} while (count($models) == $this->packageSize);

			// *** PACKAGED UPDATE ALGORITHM FOR DOWN RECORD*** ///
			$condition->addCondition(":downRecordNegative > t.{$this->sortField} AND t.{$this->sortField} >= :afterSortFieldValueNegative");
			$condition->order = "t.{$this->sortField} DESC";
			$condition->params = compact('downRecordNegative', 'afterSortFieldValueNegative');

			$newElementPlaceSortValueCopyDown = $newElementPlaceSortValue;
			do { // package
				$models = $this->owner->model()->findAll($condition);

				$lastDownRecordNegative = null;
				foreach ($models as $entry) { // update
					$newElementPlaceSortValueCopyDown -= $naturalFindBitSpaceSize;
					$lastDownRecordNegative = $entry->{$this->sortField};
					$entry->{$this->sortField} = $newElementPlaceSortValueCopyDown;
					if (!$entry->save()) {
						throw new CException(Yii::t('SorterActiveRecordBehavior', 'sorterNormalizeSortFieldEmergency model save error. Error data: $entry =>' . CVarDumper::dumpAsString($entry->getErrors())));
					}
				}

				// next package
				if (isset($lastDownRecordNegative)) {
					$condition->params['downRecordNegative'] = $lastDownRecordNegative; // is more faster then $condition->offset += $this->pacakgeSize
				} else {
					break; // if !isset then count($models) = 0
				}
			} while (count($models) == $this->packageSize); // if count less, then is end

			return $newElementPlaceSortValue;
		}
	}

	/**
	 * Calculate, set and return next insert sort value
	 * @return integer next sort value
	 */
	public function sorterSetNextInsertSortValue() {
		if ($this->defaultInsertToEnd) {
			$this->sorterCurrentMoveToEnd(true);
		} else {
			$this->sorterCurrentMoveToBegin(true);
		}

		return $this->owner->{$this->sortField};
	}

	/**
	 * Normalize fort field - extreme situation... Auhtung!
	 */
	protected function normalizeSortFieldExtreme($currentRecordCount, $afterInsertSortId) {
		Yii::log(Yii::t('SorterActiveRecordBehavior', 'Extreme normalisation situation. Check table ({table})', array('{rable}' => $this->owner->tableName())), CLogger::LEVEL_WARNING);

		$sortFieldNaturalSizeMax = 1 << $this->sortFieldBitSize;
		$findBitSpaceSize = null;

		// find local discharge space bit size in global scope
		// for extreme sutuation min local dischaged space less then minLocalDischargeSpaceBitSize
		for ($bitSpaceSize = $this->dischargeSpaceBitSize - 1; $bitSpaceSize >= 1; --$bitSpaceSize) { // от меньшего шага к большему
			if ($sortFieldNaturalSizeMax > ((1 << $bitSpaceSize) * $currentRecordCount)) {
				$findBitSpaceSize = $bitSpaceSize;
				break;
			}
		}

		if ($findBitSpaceSize === null) {
			// кончилась мощность
			throw new SorterOutOfDischargeSpaceExeption(Yii::t('SorterActiveRecordBehavior', 'Out of discharge space. Need reconfigure system in table({table})', array('{rable}' => $this->owner->tableName())));
		}

		$this->distributeNewDischargeSpaceBitSize($findBitSpaceSize, $currentRecordCount, $afterInsertSortId);
	}

	protected function distributeNewDischargeSpaceBitSize($newDischargeSpaceBitSize, $currentRecordCount, $afterInsertSortId) {
		$this->owner->model()->updateAll(array("$this->sortField" => new CDbExpression("-{$this->sortField}")));

		$newDischargeSpaceBitSizeNatural = 1 << $newDischargeSpaceBitSize;

		$halfCount = $currentRecordCount >> 1;
		$firstElOffset = $newDischargeSpaceBitSizeNatural * $halfCount;

		// get first sort
		$sortFieldBitSizeNatural = 1 << $this->sortFieldBitSize;
		$currentSortNatural = $sortFieldBitSizeNatural - $firstElOffset;

		$condition = new CDbCriteria();
		$condition->addCondition(":newFromSearch > t.{$this->sortField} AND t.{$this->sortField} > :maxSortField");
		$condition->order = "t.{$this->sortField} DESC";
		$condition->limit = $this->packageSize;
		$condition->params = array(
			'newFromSearch' => 0,
			'maxSortField' => -($sortFieldBitSizeNatural - 1 + $sortFieldBitSizeNatural)
		);

		do {
			$models = $this->owner->model()->findAll($condition);

			$newFromSearch = null;

			foreach ($models as $entry) {
				$newFromSearch = $entry->{$this->sortField};
				
				// save one query
				if (isset($afterInsertSortId) && $entry->getPrimaryKey() == $this->owner->getPrimaryKey()) {
					continue;
				}
				
				$entry->{$this->sortField} = $currentSortNatural;
				$currentSortNatural += $newDischargeSpaceBitSizeNatural;
				if(!$entry->save()) {
					throw new CException(Yii::t('SorterActiveRecordBehavior', 'distributeNewDischargeSpaceBitSize save error. Error data: $entry =>' . CVarDumper::dumpAsString($entry->getErrors())));
				}
				
				// if it part of on the fly algorithm
				if (isset($afterInsertSortId) && $entry->getPrimaryKey() == $afterInsertSortId->getPrimaryKey()) {
					$currentSortNatural += $newDischargeSpaceBitSizeNatural;
				}
			}

			// next package
			if (isset($newFromSearch)) {
				$condition->params['newFromSearch'] = $newFromSearch; // is more faster then $condition->offset += $this->pacakgeSize
			} else {
				break; // if !isset then count($models) = 0
			}
		} while (count($models) == $this->packageSize);
	}

	protected function moveToBeginFast($min, $onlySet = false) {
		$this->owner->{$this->sortField} = $min - (1 << $this->dischargeSpaceBitSize);

		// check out of bits
		if ($this->owner->{$this->sortField} <= 0) {
			// End place for insert to begin. It's Extreme warning situation
			Yii::log(Yii::t('SorterActiveRecordBehavior', 'PreExtreme situation. Check table({table})', array('{rable}' => $this->owner->tableName())), CLogger::LEVEL_WARNING);

			// move to between start and last record
			$this->moveBetween($min, 0, $onlySet);
		} else {
			if ($onlySet === false) {
				if (!$this->owner->save()) {
					throw new CException(Yii::t('SorterActiveRecordBehavior', 'moveToBeginFast save error. Error data: $this->owner =>' . CVarDumper::dumpAsString($this->owner->getErrors())));
				}
			}
		}
	}

	protected function moveToEndFast($max, $onlySet = false) {
		$dischargeSpaceBitSizeNatural = 1 << $this->dischargeSpaceBitSize;
		$sortFieldBitSizeNatural = 1 << $this->sortFieldBitSize;
		$maxSortValue = $sortFieldBitSizeNatural - 1 + $sortFieldBitSizeNatural;
		if (gettype($maxSortValue) != 'integer') { // check out of bits
			throw new CException(Yii::t('SorterActiveRecordBehavior', 'Precision error $maxSortValue({maxSortValue}) is {type}. Expect integer', array('{data}' => $maxSortValue, '{type}' => gettype($maxSortValue))));
		}

		if ($maxSortValue - $max <= $dischargeSpaceBitSizeNatural) {
			// End place for insert to end. It's Extreme warning situation
			Yii::log(Yii::t('SorterActiveRecordBehavior', 'Preview Extreem situation. Check table ({table})', array('{rable}' => $this->owner->tableName())), CLogger::LEVEL_WARNING);

			// move to between end and last record
			$this->moveBetween($max, $maxSortValue, $onlySet);
		} else {
			$this->owner->{$this->sortField} = $max + $dischargeSpaceBitSizeNatural;

			if ($onlySet === false) {
				if (!$this->owner->save()) {
					throw new CException(Yii::t('SorterActiveRecordBehavior', 'moveToEndFast save error. Error data: $this->owner =>' . CVarDumper::dumpAsString($this->owner->getErrors())));
				}
			}
		}
	}

	/**
	 * Move current record to middle of 2 record
	 * @param CActiveRecord|integer $recordA
	 * @param CActiveRecord|integer $recordB
	 * @throws CException
	 */
	protected function moveBetween($betweenA, $betweenB, $onlySet = false) {

		// higher boolean magic to preserve accuracy =)
		$middle = ($betweenA >> 1) + ($betweenB >> 1) + ($betweenA & $betweenB & 1);
		if ($middle == $betweenA || $middle == $betweenB) {
			$this->owner->{$this->sortField} = $this->sorterNormalizeSortFieldOnthefly($betweenA, $betweenB);
		} else {
			$this->owner->{$this->sortField} = $middle;
		}

		if ($onlySet === false) {
			if (!$this->owner->save()) {
				throw new CException(Yii::t('SorterActiveRecordBehavior', 'moveBetween save error. Error data: $this->owner =>' . CVarDumper::dumpAsString($this->owner->getErrors())));
			}
		}
	}

	/**
	 * Insert first record.
	 * @param type $onlySet
	 * @throws CException
	 */
	protected function insertFirst($onlySet) {
		$this->owner->{$this->sortField} = 1 << ($this->sortFieldBitSize); // divide to 2. Take center of int

		if (!$onlySet) {
			if (!$this->owner->save()) {
				throw new CException(Yii::t('SorterActiveRecordBehavior', 'sorterInsertFirst save error. Error data: $this->owner =>' . CVarDumper::dumpAsString($this->owner->getErrors())));
			}
		}
	}

	/**
	 * Before validate event
	 * @param type $event
	 */
	public function beforeValidate($event) {
		parent::beforeValidate($event);

		if ($this->owner->getIsNewRecord()) {
			$this->sorterSetNextInsertSortValue();
		}
	}

}

/**
 * Exception throwing if model gived in params nod find
 */
class SorterKeyNotFindExeption extends CException {
	
}

/**
 * Exception throwing if working model not save
 */
class SorterSaveErrorExeption extends CException {
	
}

/**
 * Exception throwing sorter operation can't to be execute
 */
class SorterOperationExeption extends CException {
	
}

/**
 * 
 */
class SorterOutOfDischargeSpaceExeption extends CException {
	
}
