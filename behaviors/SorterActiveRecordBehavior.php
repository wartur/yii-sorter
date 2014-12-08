<?php

/**
 * SorterActiveRecordBehavior class file.
 *
 * @author Krivtsov Artur (wartur) <gwartur@gmail.com> | Made in Russia
 * @copyright Krivtsov Artur © 2014
 * @link https://github.com/wartur/yii-sorter-behavior
 * @license New BSD license
 */
Yii::import('sorter.behaviors.exceptions.*');

/**
 * Behavior for custom sorting
 * 
 * Behavior allows you to work with a custom sorting
 * Behavior-based algorithm works with sparse arrays, thereby
 * average insert made one write request. To reduce the degradation
 * discharged array algorithm produces multiple requests
 * read to determine the specific situations and optimize it.
 * 
 * === Important information!
 * == Support parameter: $onlySet (only set a new sortField, but not write to the database)
 * Some methods of particular realization of the system of protection against
 * degradation discharged array can not be used in conjunction with the $onlySet:
 * - sorterMove*
 * - sorterMoveNumber*
 * Other methods can operate with $onlySet, but in some cases this leads to
 * an increase in degradation rate of the array.
 * All the descriptions you can find directly from each method:
 * - sorterMoveTo*
 * - sorterMoveToModel*
 * - sorterMoveToPosition*
 * Ideally, this parameter is used only for the initial set in the model for subsequent
 * work other business logic, but not for continuous use.
 * You can immediately allocate space for any of the entries,
 * then fill the rest of the field model and keep it with your business logic.
 * 
 * == Some methods work immediately after the class, and some do not.
 * This is due to the inability to define the initial position.
 * Methods do not work immediately after its creation:
 * - sorterMove*
 * - sorterMoveNumber*
 * 
 * The method works immediately after creation:
 * - sorterMoveTo*
 * - sorterMoveToModel*
 * - sorterMoveToPosition*
 * 
 * Using in CActiveRecord
 * <pre>
 * 	public function behaviors() {
 * 		return array_merge(parent::behaviors(), array(
 * 			'SorterActiveRecordBehavior' => array(
 * 				'class' => 'sorter.behaviors.SorterActiveRecordBehavior',
 * 			)
 * 		));
 * 	}
 * </pre>
 * 
 * @author Krivtsov Artur (wartur) <gwartur@gmail.com> | Made in Russia
 * @since v1.0.0
 * @link https://github.com/wartur/yii-sorter-behavior/blob/master/ALGORITHM.md
 */
class SorterActiveRecordBehavior extends CActiveRecordBehavior {

	/**
	 * The new record will be inserted into the top of the list
	 */
	const DEFAULT_INSERT_TO_BEGIN = false;

	/**
	 * The new record will be inserted into the end of the list
	 */
	const DEFAULT_INSERT_TO_END = true;

	/**
	 * > Policy update records
	 * Update using model
	 * @todo v1.2.1
	 */
	const UPDATE_POLICY_USEMODEL = true;

	/**
	 * > Policy update records
	 * Update using pure sql queries
	 * @todo v1.2.1
	 */
	const UPDATE_POLICY_PURESQL = false;

	/**
	 * @var string Field for sorting records management
	 * Example `ORDER BY sort ASC`
	 */
	public $sortField = 'sort';

	/**
	 * @var array Field for grouping records management
	 * If use this field then the algorithm does all calculation in diapason of each group field
	 * It is believed that it is a unique key in the fields groupField and sortField
	 * 
	 * @todo 1.2.0
	 */
	public $groupField = null;

	/**
	 * @var boolean position for insert new record
	 */
	public $defaultInsertToEnd = self::DEFAULT_INSERT_TO_END;

	/**
	 * @var boolean policy update records
	 * @todo 1.2.1
	 */
	public $updatePolicy = self::UPDATE_POLICY_USEMODEL;

	/**
	 * @var int bit size sort fields divided by 2
	 * This number is in the natural size is half of the supported range sort fields
	 * from 1 to (1 << (sortFieldBitSize + 1)) - 1. This means that the first
	 * insert element is equalto 1 << sortFieldBitSize
	 * You can select any number of this, but with the rules
	 * sortFieldBitSize > freeSortSpaceBitSize >= minLocalFreeSortSpaceBitSize > 1
	 * 
	 * This setting algorithm parameters to control the sort field.
	 * Remember, the correct settings of the algorithm directly affect performance.
	 * IF YOU ARE do not know how it works, do not change this,
	 * most systems is quite default settings. The default settings allow you
	 * to control without sacrificing performance about 100500 entries and more =)
	 * 
	 * Standard settings:
	 * sortFieldBitSize/freeSortSpaceBitSize/minLocalFreeSortSpaceBitSize
	 * 
	 * on x86 php max is 30 (signed int).
	 * 30/15/4
	 * 
	 * on x64 php max is 62 (signed bigint)
	 * 62/31/6
	 * 
	 * @link https://github.com/wartur/yii-sorter-behavior/blob/master/ALGORITHM.md More details about the algorithm works
	 */
	public $sortFieldBitSize = 30;

	/**
	 * @var int the amount of space between entries by default (in bits)
	 * @link https://github.com/wartur/yii-sorter-behavior/blob/master/ALGORITHM.md More details about the algorithm works
	 * 
	 * also see $sortFieldBitSize
	 */
	public $freeSortSpaceBitSize = 15;

	/**
	 * @var int the minimum amount of space between entries
	 * @link https://github.com/wartur/yii-sorter-behavior/blob/master/ALGORITHM.md подробнее об алгоритме работы
	 * 
	 * also see $sortFieldBitSize
	 */
	public $minLocalFreeSortSpaceBitSize = 4;

	/**
	 * @var int packet size records for processing,
	 * is used to speed up the database. More than this number,
	 * the more records at a time is loaded from the database.
	 * If the packet size is equal to 1,
	 * it is assumed that the batch processing is disabled
	 * 
	 * See ALGORITHM.md
	 */
	public $packageSize = 200;

	/**
	 * Swap the current record and the record specified in the $ model.
	 * Swapping occurs in 3 write request, the variable
	 * 
	 * Note:
	 * Ideally, this can be done with a single query swapping in RDBM,
	 * but some databases (MySQL) do not support the request svoppirovaniya
	 * 
	 * This method makes it possible to cope with the increased degradation
	 * under permutation of 2 adjacent records
	 * 
	 * @param CActiveRecord $model behavioral model SorterActiveRecordBehavior
	 */
	public function sorterSwappWith(CActiveRecord $model) {
		$sortOwner = $this->owner->{$this->sortField};

		$this->owner->{$this->sortField} = 0;
		if (!$this->owner->save()) {
			throw new SorterSaveErrorExeption(Yii::t('SorterActiveRecordBehavior', 'sortField = 0 not save correctly. Error data: $this->owner => {errors}', array('{errors}' => CVarDumper::dumpAsString($this->owner->getErrors()))));
		}

		$this->owner->{$this->sortField} = $model->{$this->sortField};
		$model->{$this->sortField} = $sortOwner;

		if (!($model->save() && $this->owner->save())) {
			throw new SorterSaveErrorExeption(Yii::t('SorterActiveRecordBehavior', 'swapp not save correctly. Error data: $this->owner => {errorsOwner}, $model => {errorsModel}', array('{errorsOwner}' => CVarDumper::dumpAsString($this->owner->getErrors()), '{errorsModel}' => CVarDumper::dumpAsString($model->getErrors()))));
		}
	}

	/**
	 * Move the current entry up one position
	 * It's alias of self::sorterMove(true)
	 */
	public function sorterMoveUp() {
		$this->sorterMove(true);
	}

	/**
	 * Move the current record down one position
	 * It's alias of self::sorterMove(false)
	 */
	public function sorterMoveDown() {
		$this->sorterMove(false);
	}

	/**
	 * Move the current record by one position. Uses an algorithm swapping.
	 * Degradation discharged array does not occur.
	 * @param boolean $up direction of travel. true - up, false - down
	 * @throws SorterOperationExeption A new record is impossible to determine the starting position
	 */
	public function sorterMove($up) {
		if ($this->owner->getIsNewRecord()) {
			throw new SorterOperationExeption(Yii::t('SorterActiveRecordBehavior', 'sorterMoveUp sorterMoveDown not support when it is new record'));
		}

		$upModel = $this->owner->model()->find(array(
			'condition' => "t.{$this->sortField} " . ($up ? '< :sort' : '> :sort'),
			'order' => "t.{$this->sortField} " . ($up ? 'DESC' : 'ASC'),
			'params' => array('sort' => $this->owner->{$this->sortField}),
		));

		if (isset($upModel)) {
			$this->sorterSwappWith($upModel);
		}
	}

	/**
	 * Move the current record for a few top positions
	 * It's alias of sorterMoveNumber(true, $number)
	 * 
	 * @param int $number the number of positions to which you want to move
	 */
	public function sorterMoveNumberUp($number) {
		$this->sorterMoveNumber(true, $number);
	}

	/**
	 * Move the current record for a few down positions
	 * It's alias of sorterMoveNumber(false, $number)
	 * 
	 * @param int $number the number of positions to which you want to move
	 */
	public function sorterMoveNumberDown($number) {
		$this->sorterMoveNumber(false, $number);
	}

	/**
	 * Move the current record at multiple positions
	 * @param boolean $up direction of travel. true - upstairs, false - down
	 * @param int $number number of positions move
	 * @throws SorterOperationExeption A new record is impossible to determine the starting position
	 */
	public function sorterMoveNumber($up, $number) {
		if ($this->owner->getIsNewRecord()) {
			throw new SorterOperationExeption(Yii::t('SorterActiveRecordBehavior', 'sorterMoveUpNumber sorterMoveDownNumber not support when it is new record'));
		}

		if ($number < 0) { // process negative
			$this->sorterMoveNumber(!$up, -$number);
		} elseif ($number == 1) {
			$this->sorterMove($up);
		} elseif ($number > 1) {
			// take 2 entries from the environment of the future position
			$condition = new CDbCriteria();
			$condition->addCondition("t.{$this->sortField} " . ($up ? '<= :sort' : '>= :sort'));
			$condition->order = "t.{$this->sortField} " . ($up ? 'DESC' : 'ASC');
			$condition->offset = $number;
			$condition->limit = 2;
			$condition->params['sort'] = $this->owner->{$this->sortField};

			$upModels = $this->owner->model()->findAll($condition);

			$count = count($upModels);
			if ($count == 0) { // If no records, it is the boundary list
				$this->sorterMoveTo($up);
			} elseif ($count == 1) {
				// If we found one record, it is the border of the list. We use optimization
				if ($up) {
					$this->moveToBeginFast($upModels[0]->{$this->sortField});
				} else {
					$this->moveToEndFast($upModels[0]->{$this->sortField});
				}
			} elseif ($count == 2) {
				// If we found 2 records, then an arbitrary position in the list
				$this->moveBetween($upModels[0]->{$this->sortField}, $upModels[1]->{$this->sortField});
			}
		}
	}

	/**
	 * Move the current record in the top of the list
	 * it's alias of sorterMoveTo(true, $onlySet)
	 * 
	 * @param boolean $onlySet only set, not stored in the database
	 * Important! regardless of the parameter $onlySet will be made every
	 * possible action to ensure that would allocate space
	 * for inserting this value, including the normalization on the fly
	 * and extreme normalization with all records in the database.
	 * Also, if you use this option will not work the system swap positions.
	 * Instead, the method will be used moveToBeginFast / moveToEndFast
	 * This method with a parameter $ onlySet = true should only be used when inserting new records!
	 */
	public function sorterMoveToBegin($onlySet = false) {
		$this->sorterMoveTo(true, $onlySet);
	}

	/**
	 * Move the current record in the end of the list
	 * it's alias of sorterMoveTo(false, $onlySet)
	 * 
	 * @param boolean $onlySet only set, not stored in the database
	 * Important! regardless of the parameter $onlySet will be made every
	 * possible action to ensure that would allocate space
	 * for inserting this value, including the normalization on the fly
	 * and extreme normalization with all records in the database.
	 * Also, if you use this option will not work the system swap positions.
	 * Instead, the method will be used moveToBeginFast / moveToEndFast
	 * This method with a parameter $ onlySet = true should only be used when inserting new records!
	 */
	public function sorterMoveToEnd($onlySet = false) {
		$this->sorterMoveTo(false, $onlySet);
	}

	/**
	 * Move the current record on the border of the list.
	 * 
	 * @param boolean $begin direction of movement, true - in the beginning, false to the end
	 * @param boolean $onlySet only set, not stored in the database
	 * Important! regardless of the parameter $onlySet will be made every
	 * possible action to ensure that would allocate space
	 * for inserting this value, including the normalization on the fly
	 * and extreme normalization with all records in the database.
	 * Also, if you use this option will not work the system swap positions.
	 * Instead, the method will be used moveToBeginFast / moveToEndFast
	 * This method with a parameter $ onlySet = true should only be used when inserting new records!
	 */
	public function sorterMoveTo($begin, $onlySet = false) {
		$records = $this->owner->model()->findAll(array(
			'limit' => 2,
			'order' => "t.{$this->sortField} " . ($begin ? 'ASC' : 'DESC'),
		));

		if (empty($records)) {
			$this->insertFirst($onlySet);
		} elseif (isset($records[1]) && $records[1]->getPrimaryKey() == $this->owner->getPrimaryKey()) {
			// vstavkey at a new record this code will never be executed because getPrimaryKey == null
			if ($onlySet) {
				if ($begin) {
					$this->moveToBeginFast($records[0]->{$this->sortField}, $onlySet);
				} else {
					$this->moveToEndFast($records[0]->{$this->sortField}, $onlySet);
				}
			} else {
				$this->sorterSwappWith($records[0]);
			}
		} elseif ($records[0]->getPrimaryKey() == $this->owner->getPrimaryKey()) {
			// noop
		} else {
			if ($begin) {
				$this->moveToBeginFast($records[0]->{$this->sortField}, $onlySet);
			} else {
				$this->moveToEndFast($records[0]->{$this->sortField}, $onlySet);
			}
		}
	}

	/**
	 * Move the current record before the specified model.
	 * it's alias of sorterMoveToModel(true, $pk, $onlySet)
	 * 
	 * @param int|CActiveRecord $pk model or a unique identifier of a record
	 * @param boolean $onlySet only set, not stored in the database
	 * Important! regardless of the parameter $onlySet will be made every
	 * possible action to ensure that would allocate space
	 * for inserting this value, including the normalization on the fly
	 * and extreme normalization with all records in the database.
	 * Also, if you use this option will not work the system swap positions.
	 * Instead, the method will be used moveToBeginFast / moveToEndFast,
	 * and move Between - having the highest effect of degradation.
	 * This method with a parameter $ onlySet = true should only be used when inserting new records!
	 */
	public function sorterMoveToModelBefore($pk, $onlySet = false) {
		$this->sorterMoveToModel(true, $pk, $onlySet);
	}

	/**
	 * Move the current record after specified model
	 * it's alias of sorterMoveToModel(false, $pk, $onlySet)
	 * 
	 * @param int|CActiveRecord $pk model or a unique identifier of a record
	 * @param boolean $onlySet only set, not stored in the database
	 * Important! regardless of the parameter $onlySet will be made every
	 * possible action to ensure that would allocate space
	 * for inserting this value, including the normalization on the fly
	 * and extreme normalization with all records in the database.
	 * Also, if you use this option will not work the system swap positions.
	 * Instead, the method will be used moveToBeginFast / moveToEndFast,
	 * and move Between - having the highest effect of degradation.
	 * This method with a parameter $ onlySet = true should only be used when inserting new records!
	 */
	public function sorterMoveToModelAfter($pk, $onlySet = false) {
		$this->sorterMoveToModel(false, $pk, $onlySet);
	}

	/**
	 * Move the current record before|after specified model
	 * 
	 * @param boolean $before place to insert, true - before recording, false - after recording
	 * @param int|CActiveRecord $pk model or a unique identifier of a record
	 * @param boolean $onlySet only set, not stored in the database
	 * Important! regardless of the parameter $onlySet will be made every
	 * possible action to ensure that would allocate space
	 * for inserting this value, including the normalization on the fly
	 * and extreme normalization with all records in the database.
	 * Also, if you use this option will not work the system swap positions.
	 * Instead, the method will be used moveToBeginFast / moveToEndFast,
	 * and move Between - having the highest effect of degradation.
	 * This method with a parameter $ onlySet = true should only be used when inserting new records!
	 * @throws SorterKeyNotFindExeption Model-position movement is not found
	 */
	public function sorterMoveToModel($before, $pk, $onlySet = false) {
		// optimization at an internal method call
		if ($pk instanceof CActiveRecord) {
			$movePlaceAfterModel = $pk; // model download is not required

			if ($this->owner->getPrimaryKey() == $movePlaceAfterModel->getPrimaryKey()) {
				return null; // this is the same record, action is required
			}
		} else {
			if ($this->owner->getPrimaryKey() == $pk) {
				return null; // this is the same record, action is required
			}

			$movePlaceAfterModel = $this->owner->model()->findByPk($pk);
			if (empty($movePlaceAfterModel)) {
				throw new SorterKeyNotFindExeption(Yii::t('SorterActiveRecordBehavior', 'pk({pk}) not find in db', array('{pk}' => $pk)));
			}
		}

		if ($onlySet) {
			// load record 2 positions below the model move
			$afterPlaceModels = $this->owner->model()->findAll(array(
				'condition' => "t.{$this->sortField} " . ($before ? '< :sort' : '> :sort'),
				'order' => "t.{$this->sortField} " . ($before ? 'DESC' : 'ASC'),
				'limit' => 2,
				'params' => array(
					'sort' => $movePlaceAfterModel->{$this->sortField}
				)
			));

			if (empty($afterPlaceModels)) {
				if ($before) {
					$this->moveToBeginFast($movePlaceAfterModel->{$this->sortField}, $onlySet);
				} else {
					$this->moveToEndFast($movePlaceAfterModel->{$this->sortField}, $onlySet);
				}
			} else {
				// very suboptimally!!!
				$this->moveBetween($afterPlaceModels[0]->{$this->sortField}, $movePlaceAfterModel->{$this->sortField}, $onlySet);
			}
		} else {
			// load model one position above the model move
			$beforeModel = $this->owner->model()->find(array(
				'condition' => "t.{$this->sortField} " . ($before ? '> :sort' : '< :sort'),
				'order' => "t.{$this->sortField} " . ($before ? 'ASC' : 'DESC'),
				'params' => array(
					'sort' => $movePlaceAfterModel->{$this->sortField}
				)
			));

			if (isset($beforeModel) && $beforeModel->getPrimaryKey() == $this->owner->getPrimaryKey()) {
				$this->sorterSwappWith($movePlaceAfterModel);
			} else {
				// load record 2 positions below the model move
				$afterPlaceModels = $this->owner->model()->findAll(array(
					'condition' => "t.{$this->sortField} " . ($before ? '< :sort' : '> :sort'),
					'order' => "t.{$this->sortField} " . ($before ? 'DESC' : 'ASC'),
					'limit' => 2,
					'params' => array(
						'sort' => $movePlaceAfterModel->{$this->sortField}
					)
				));

				if (empty($afterPlaceModels)) { // not found any record - it's the end of the list
					if ($before) {
						$this->moveToBeginFast($movePlaceAfterModel->{$this->sortField}, $onlySet);
					} else {
						$this->moveToEndFast($movePlaceAfterModel->{$this->sortField}, $onlySet);
					}
				} elseif (isset($afterPlaceModels[0]) && $afterPlaceModels[0]->getPrimaryKey() == $this->owner->getPrimaryKey()) {
					// noop, we have here
				} elseif (isset($afterPlaceModels[1]) && $afterPlaceModels[1]->getPrimaryKey() == $this->owner->getPrimaryKey()) {
					$this->sorterSwappWith($afterPlaceModels[0]);
				} else {
					// inserting in an arbitrary position
					$this->moveBetween($afterPlaceModels[0]->{$this->sortField}, $movePlaceAfterModel->{$this->sortField}, $onlySet);
				}
			}
		}
	}

	/**
	 * Move the current record before the specified position in the list
	 * 
	 * @param int $position position to move from the front of
	 * @param boolean $onlySet only set, not stored in the database
	 * Important! regardless of the parameter $onlySet will be made every
	 * possible action to ensure that would allocate space
	 * for inserting this value, including the normalization on the fly
	 * and extreme normalization with all records in the database.
	 * Also, if you use this option will not work the system swap positions.
	 * Instead, the method will be used moveToBeginFast / moveToEndFast,
	 * and move Between - having the highest effect of degradation.
	 * This method with a parameter $ onlySet = true should only be used when inserting new records!
	 */
	public function sorterMoveToPositionBefore($position, $onlySet = false) {
		$this->sorterMoveToPosition(true, $position, $onlySet);
	}

	/**
	 * Move the current record after the specified position in the list
	 * 
	 * @param int $position position to move from the front of
	 * @param boolean $onlySet only set, not stored in the database
	 * Important! regardless of the parameter $onlySet will be made every
	 * possible action to ensure that would allocate space
	 * for inserting this value, including the normalization on the fly
	 * and extreme normalization with all records in the database.
	 * Also, if you use this option will not work the system swap positions.
	 * Instead, the method will be used moveToBeginFast / moveToEndFast,
	 * and move Between - having the highest effect of degradation.
	 * This method with a parameter $ onlySet = true should only be used when inserting new records!
	 */
	public function sorterMoveToPositionAfter($position, $onlySet = false) {
		$this->sorterMoveToPosition(false, $position, $onlySet);
	}

	/**
	 * Move the current record before|after the specified position in the list
	 * 
	 * @param boolean $before place to insert, true - to the position, false - after position
	 * @param int $position position to move from the front of
	 * @param boolean $onlySet only set, not stored in the database
	 * Important! regardless of the parameter $onlySet will be made every
	 * possible action to ensure that would allocate space
	 * for inserting this value, including the normalization on the fly
	 * and extreme normalization with all records in the database.
	 * Also, if you use this option will not work the system swap positions.
	 * Instead, the method will be used moveToBeginFast / moveToEndFast,
	 * and move Between - having the highest effect of degradation.
	 * This method with a parameter $ onlySet = true should only be used when inserting new records!
	 */
	public function sorterMoveToPosition($before, $position, $onlySet = false) {
		if ($position <= ($before ? 1 : 0)) {
			$this->sorterMoveTo(true, $onlySet);
		} else {
			$model = $this->owner->model()->find(array(
				'order' => "t.{$this->sortField} ASC",
				'offset' => $position - 1,
			));

			if (empty($model)) {
				$this->sorterMoveTo(false, $onlySet);
			} else {
				// find the position.
				$this->sorterMoveToModel($before, $model, $onlySet);
			}
		}
	}

	/**
	 * The order of this list of entries.
	 * If you change the order of degradation sparse array will not occur
	 * 
	 * @todo 1.1.0 will implement when implementing jQuery widget sorting
	 * 
	 * @param array $idsAfter Identifier array or an array of models are sorted in the order
	 * @throws CException Not Implemented Exception
	 */
	public function sorterChangeIdsOrderTo(array $idsAfter) {
		throw new CException(Yii::t('SorterActiveRecordBehavior', 'Not Implemented Exception'));
	}

	/**
	 * Reverse list. Inversion occurs without degradation sparse array
	 * using subtraction current values of the maximum
	 * 
	 * @todo 1.1.0 will implement when implementing jQuery widget sorting
	 * 
	 * @throws CException Not Implemented Exception
	 */
	public function sorterInverseAll() {
		// take 1 << 30 and subtracted from the current value, we obtain an inversion
		//UPDATE ALL 1<<30 - $this->owner->{$this->sorterField};
		throw new CException(Yii::t('SorterActiveRecordBehavior', 'Not Implemented Exception'));
	}

	/**
	 * Regular normalization discharged array
	 */
	public function sorterNormalizeSortFieldRegular() {
		$elementCount = $this->owner->model()->count();
		if ($elementCount > 0) {
			$newFreeSortSpaceBitSizeNatural = $this->findNewFreeSortSpaceBitSizeByCount($this->freeSortSpaceBitSize, $this->freeSortSpaceBitSize, $elementCount);
			if ($newFreeSortSpaceBitSizeNatural === null) {
				$this->normalizeSortFieldExtreme($elementCount);
			} else {
				$maxSortField = $this->mathMaxSortField();
				$startSortValue = $this->centralWeightFirstElement($elementCount, $newFreeSortSpaceBitSizeNatural, 0, $maxSortField);
				$this->distributeNewFreeSortSpaceBitSize($newFreeSortSpaceBitSizeNatural, $startSortValue, 0, $maxSortField);
			}
		}
	}

	/**
	 * Obtaining the next value to insert a new record
	 * @return int another sort field value depending on your model
	 */
	public function sorterSetNextInsertSortValue() {
		$this->sorterMoveTo(!$this->defaultInsertToEnd, true);

		return $this->owner->{$this->sortField};
	}

	/**
	 * Partial normalization to allow insertion of the next record in conflict roster spot
	 * @param int $sortFieldA conflicting value 1
	 * @param int $sortFieldB conflicting value 2 (typically differs by one from "value 1")
	 * @return int the new value is possible to insert without conflict
	 * @throws SorterOperationExeption "value 1" can not be equal to the "value 2"
	 */
	private function normalizeSortFieldOnthefly($sortFieldA, $sortFieldB) {
		if ($sortFieldA == $sortFieldB) {
			throw new SorterOperationExeption(Yii::t('SorterActiveRecordBehavior', 'normalizeSortFieldOnthefly :: $sortFieldA({sortFieldA}) and $sortFieldB({sortFieldB}) cant be equal', array('{sortFieldA}' => $sortFieldA, '{sortFieldB}' => $sortFieldB)));
		}

		// order the A and B
		if ($sortFieldA < $sortFieldB) {
			$upSortFieldValue = $sortFieldA;
			$downSortFieldValue = $sortFieldB;
		} else {
			$upSortFieldValue = $sortFieldB;
			$downSortFieldValue = $sortFieldA;
		}

		// ======= space search algorithm to normalize =======
		$upDownCountCache = null; // Cache number of records from the border
		$doubleSearchMultiplier = 1; // factor viewing space
		$usingMin = $usingMax = false; // flags signaling that he was to see the end of the lower or upper limit of the list
		do {
			// request higher on the list
			if ($usingMin === false) { // if we reached the border continue to seek not required
				$beforeSortFieldValue = $this->owner->dbConnection->createCommand()
						->select($this->sortField)
						->from($this->owner->tableName())
						->where("{$this->sortField} <= :upSortFieldValue")
						->order("{$this->sortField} DESC")
						->limit(1, $this->freeSortSpaceBitSize * $doubleSearchMultiplier - 1)
						->queryScalar(array('upSortFieldValue' => $upSortFieldValue));
				if (empty($beforeSortFieldValue)) {
					$usingMin = true;
					$beforeSortFieldValue = 0;
				}
			}

			// request the list below
			if ($usingMax === false) { // if we reached the border continue to seek not required
				$afterSortFieldValue = $this->owner->dbConnection->createCommand()
						->select($this->sortField)
						->from($this->owner->tableName())
						->where("{$this->sortField} >= :downSortFieldValue")
						->order("{$this->sortField} ASC")
						->limit(1, $this->freeSortSpaceBitSize * $doubleSearchMultiplier - 1)
						->queryScalar(array('downSortFieldValue' => $downSortFieldValue));
				if (empty($afterSortFieldValue)) {
					$usingMax = true;
					$afterSortFieldValue = $this->mathMaxSortField();
				}
			}

			if ($usingMin && $usingMax) {
				// full normalization - a special case of regular normalization
				$elementCount = $this->owner->model()->count();
			} elseif ($usingMin && !$usingMax) {
				// top of the list
				if ($upDownCountCache === null) {
					$upDownCountCache = $this->owner->model()->count(array(
						'condition' => "t.{$this->sortField} <= :upSortFieldValue",
						'params' => array('upSortFieldValue' => $upSortFieldValue)
					));
				}

				// -1 Means that we do not consider the border record
				$elementCount = $upDownCountCache + $this->freeSortSpaceBitSize * $doubleSearchMultiplier - 1;
			} elseif (!$usingMin && $usingMax) {
				// end of list
				if ($upDownCountCache === null) {
					$upDownCountCache = $this->owner->model()->count(array(
						'condition' => "t.{$this->sortField} >= :downSortFieldValue",
						'params' => array('downSortFieldValue' => $downSortFieldValue)
					));
				}

				// -1 Means that we do not consider the border record
				$elementCount = $upDownCountCache + $this->freeSortSpaceBitSize * $doubleSearchMultiplier - 1;
			} else {
				// standard search space
				$elementCount = $this->freeSortSpaceBitSize * 2 * $doubleSearchMultiplier - 2; // -2 bordered
			}

			// if the current record is new or it is not included in the specified range, then add it to the current number
			if ($this->owner->isNewRecord || !($beforeSortFieldValue < $this->owner->{$this->sortField} && $this->owner->{$this->sortField} < $afterSortFieldValue)) {
				++$elementCount;
			}

			// search for local discharge of the array (ЛМР*)
			if ($usingMin && $usingMax) {
				$newFreeSortSpaceBitSizeNatural = $this->findNewFreeSortSpaceBitSizeByCount($this->freeSortSpaceBitSize, $this->minLocalFreeSortSpaceBitSize, $elementCount);
			} else {
				$newFreeSortSpaceBitSizeNatural = $this->findNewFreeSortSpaceBitSizeByDiff($this->freeSortSpaceBitSize, $this->minLocalFreeSortSpaceBitSize, $elementCount, $beforeSortFieldValue, $afterSortFieldValue);
			}

			// if space is not found, double the width of the view
			if ($newFreeSortSpaceBitSizeNatural === null) {
				if ($doubleSearchMultiplier == PHP_INT_MAX) { // конец разрядности PHP
					break;
				}

				// if we have reached the limit of accuracy, squeeze out all the integer (1)
				$doubleSearchMultiplier = $doubleSearchMultiplier == (PHP_INT_MAX >> 1) + 1 ? PHP_INT_MAX : $doubleSearchMultiplier << 1;
			}

			// has not yet found a new discharge of the array (ЛМР*) or viewed by the entire table
		} while ($newFreeSortSpaceBitSizeNatural === null && !($usingMin && $usingMax));

		// found new discharge of the array (ЛМР*) after watching the entire table
		if ($newFreeSortSpaceBitSizeNatural === null) {
			if (!($usingMin && $usingMax)) { // If for some reason found the border, it is clearly a query from the database
				$elementCount = $this->owner->model()->count() + 1;  // +1 new
			}

			return $this->normalizeSortFieldExtreme($elementCount, $upSortFieldValue);
		} else {
			// ======= search a first value being used to insert in the normalization =======
			if ($usingMin && !$usingMax) {
				// is offset from the top element
				$startSortValue = $afterSortFieldValue - $newFreeSortSpaceBitSizeNatural * $elementCount;
			} elseif (!$usingMin && $usingMax) {
				// a biasing member below it
				$startSortValue = $beforeSortFieldValue + $newFreeSortSpaceBitSizeNatural;
			} else {
				// this center-weighted element
				$startSortValue = $this->centralWeightFirstElement($elementCount, $newFreeSortSpaceBitSizeNatural, $beforeSortFieldValue, $afterSortFieldValue);
			}

			// ======= distribute space =======
			return $this->distributeNewFreeSortSpaceBitSize($newFreeSortSpaceBitSizeNatural, $startSortValue, $beforeSortFieldValue, $afterSortFieldValue, $upSortFieldValue);
		}
	}

	/**
	 * Universal distribution algorithm records in sparse array
	 * 
	 * @param int $newFreeSortSpaceBitSizeNatural space between entries in the list
	 * @param int $startSortValue starting value
	 * @param int $beforeSortFieldValue the upper limit of the distribution
	 * @param int $afterSortFieldValue the lower limit of the distribution
	 * @param int $upSortFieldValue Top conflict record (used to determine the new value of the insertion of the conflict entries)
	 * @return int the new value is possible to insert without conflict
	 * @throws SorterSaveErrorExeption unsuccessful preservation model for any reason
	 */
	private function distributeNewFreeSortSpaceBitSize($newFreeSortSpaceBitSizeNatural, $startSortValue, $beforeSortFieldValue, $afterSortFieldValue, $upSortFieldValue = null) {
		$afterSortFieldValueExtend = $afterSortFieldValue == $this->mathMaxSortField() ? PHP_INT_MAX : $afterSortFieldValue;

		$this->owner->model()->updateAll(array("$this->sortField" => new CDbExpression("-{$this->sortField}")), array(
			'condition' => ":beforeSortFieldValue < {$this->sortField} AND {$this->sortField} < :afterSortFieldValue",
			'order' => "{$this->sortField} ASC",
			'params' => array(
				'beforeSortFieldValue' => $beforeSortFieldValue,
				'afterSortFieldValue' => $afterSortFieldValueExtend
			)
		));

		// produce standard computation element insertion after. DESC since we did negative !!!
		$condition = new CDbCriteria();
		$condition->addCondition(":newFromSearch < t.{$this->sortField} AND t.{$this->sortField} < :maxSortField");
		$condition->order = "t.{$this->sortField} DESC";
		$condition->limit = $this->packageSize;
		$condition->params = array(
			'newFromSearch' => -$afterSortFieldValueExtend,
			'maxSortField' => -$beforeSortFieldValue
		);

		// if the insert to the top, then leave the place
		$currentSortNatural = $startSortValue;
		if ($beforeSortFieldValue === 0 && isset($upSortFieldValue) && $upSortFieldValue == 0) {
			$result = $currentSortNatural;
			$currentSortNatural += $newFreeSortSpaceBitSizeNatural;
		} else {
			$result = null;
		}

		do {
			// Batch processing on $this->packageSize records at a time
			$models = $this->owner->model()->findAll($condition);

			$newFromSearch = null;

			if (isset($upSortFieldValue)) { // statically deploy code
				// Complicated calculation here, but they are usually smaller than the number of
				foreach ($models as $entry) {
					$newFromSearch = $entry->{$this->sortField};

					// skip owner record
					if ($this->owner->getPrimaryKey() != $entry->getPrimaryKey()) {
						$entry->{$this->sortField} = $currentSortNatural;
						if (!$entry->save()) {
							throw new SorterSaveErrorExeption(Yii::t('SorterActiveRecordBehavior', 'Error data: $entry =>' . CVarDumper::dumpAsString($entry->getErrors())));
						}

						$currentSortNatural += $newFreeSortSpaceBitSizeNatural;
						// if we see a conflicting account, reserve a place for it and stores the value for the result
						if ($upSortFieldValue == -$newFromSearch) {
							$result = $currentSortNatural;
							$currentSortNatural += $newFreeSortSpaceBitSizeNatural;
						}
					}
				}
			} else {
				// Simplified calculation here, but there's more
				foreach ($models as $entry) {
					$entry->{$this->sortField} = $currentSortNatural;
					if (!$entry->save()) {
						throw new SorterSaveErrorExeption(Yii::t('SorterActiveRecordBehavior', 'Error data: $entry =>' . CVarDumper::dumpAsString($entry->getErrors())));
					}

					$currentSortNatural += $newFreeSortSpaceBitSizeNatural;
				}
			}

			// next package
		} while (count($models) == $this->packageSize);

		return $result;
	}

	/**
	 * Extreme normalization space
	 * Is the most time-consuming operation
	 * When properly configured system this situation should never occur.
	 * The system usually starts to swear in the log long before starting use this method
	 * 
	 * @param int $elementCount the number of records for distribution
	 * @param int $upSortFieldValue Top conflict record (used to determine the new value of the insertion of the conflict entries)
	 * @return int the new value is possible to insert without conflict
	 * @throws SorterOutOfFreeSortSpaceExeption limit filling is no longer possible to reallocate space
	 */
	private function normalizeSortFieldExtreme($elementCount, $upSortFieldValue = null) {
		Yii::log(Yii::t('SorterActiveRecordBehavior', 'Extreme normalisation situation. Check table({table})', array('{table}' => $this->owner->tableName())), CLogger::LEVEL_WARNING);

		// local search of discharge, at the extreme distribution we are looking up to 0 (complete degradation)
		$newFreeSortSpaceBitSizeNatural = $this->findNewFreeSortSpaceBitSizeByCount($this->freeSortSpaceBitSize, 0, $elementCount);

		if ($newFreeSortSpaceBitSizeNatural === null) { // fulled degradation
			throw new SorterOutOfFreeSortSpaceExeption(Yii::t('SorterActiveRecordBehavior', 'Out of free sort space. Need reconfigure system in table({table})', array('{table}' => $this->owner->tableName())));
		}

		$maxSortField = $this->mathMaxSortField();

		// this center-weighted element
		$startSortValue = $this->centralWeightFirstElement($elementCount, $newFreeSortSpaceBitSizeNatural, 0, $maxSortField);

		return $this->distributeNewFreeSortSpaceBitSize($newFreeSortSpaceBitSizeNatural, $startSortValue, 0, $maxSortField, $upSortFieldValue);
	}

	/**
	 * Quickly move to the top of the list of entries without further checks
	 * @param int $min reliable determination of the minimum value in the list
	 * Important! it should be exactly the initial value of the list, which is
	 * equivalent to request SELECT MIN(sort).
	 * Any other value may lead to unpredictable results of the method.
	 * @param boolean $onlySet only set, not stored in the database
	 * Important! regardless of the parameter $onlySet will be made all possible steps
	 * to ensure that would allocate space for inserting this value, including
	 * the normalization on the fly and extreme normalization with all records in the database.
	 * @throws SorterSaveErrorExeption unsuccessful preservation model for any reason
	 */
	private function moveToBeginFast($min, $onlySet = false) {
		$beginSortValue = $min - (1 << $this->freeSortSpaceBitSize);

		// check the end of the discharged space
		if ($beginSortValue <= 0) {
			// We are at the end of the list. This situation may be a harbinger of extreme normalization
			Yii::log(Yii::t('SorterActiveRecordBehavior', 'PreExtreme situation. Check the table({table})', array('{table}' => $this->owner->tableName())), CLogger::LEVEL_WARNING);

			$this->moveBetween(0, $min, $onlySet);
		} else {
			$this->owner->{$this->sortField} = $beginSortValue;

			if ($onlySet === false) {
				if (!$this->owner->save()) {
					throw new SorterSaveErrorExeption(Yii::t('SorterActiveRecordBehavior', 'moveToBeginFast save error. Error data: $this->owner =>' . CVarDumper::dumpAsString($this->owner->getErrors())));
				}
			}
		}
	}

	/**
	 * The rapid movement of the recording to the end of the list without further checks
	 * @param int $min reliable determination of the minimum value in the list
	 * Important! it should be exactly the initial value of the list, which is
	 * equivalent to request SELECT MAX(sort).
	 * Any other value may lead to unpredictable results of the method.
	 * @param boolean $onlySet only set, not stored in the database
	 * Important! regardless of the parameter $onlySet will be made all possible steps
	 * to ensure that would allocate space for inserting this value, including
	 * the normalization on the fly and extreme normalization with all records in the database.
	 * @throws SorterSaveErrorExeption unsuccessful preservation model for any reason
	 */
	private function moveToEndFast($max, $onlySet = false) {
		$freeSortSpaceBitSizeNatural = 1 << $this->freeSortSpaceBitSize;
		$maxSortValue = $this->mathMaxSortField();

		if ($maxSortValue - $max <= $freeSortSpaceBitSizeNatural) {
			// We are at the end of the list. This situation may be a harbinger of extreme normalization
			Yii::log(Yii::t('SorterActiveRecordBehavior', 'Preview Extreem situation. Check the table ({table})', array('{table}' => $this->owner->tableName())), CLogger::LEVEL_WARNING);

			$this->moveBetween($max, $maxSortValue, $onlySet);
		} else {
			$this->owner->{$this->sortField} = $max + $freeSortSpaceBitSizeNatural;

			if ($onlySet === false) {
				if (!$this->owner->save()) {
					throw new SorterSaveErrorExeption(Yii::t('SorterActiveRecordBehavior', 'moveToEndFast save error. Error data: $this->owner =>' . CVarDumper::dumpAsString($this->owner->getErrors())));
				}
			}
		}
	}

	/**
	 * Move entry between these values. Options "value 1" and "value 2"
	 * MUST be running one after the other in any sequence.
	 * Transfer values that are inconsistent may lead to unpredictable results
	 * @param int $betweenA record 1
	 * @param int $betweenB record 2 (running next/prev "record 1")
	 * @param boolean $onlySet only set, not stored in the database
	 * Important! regardless of the parameter $onlySet will be made all possible steps
	 * to ensure that would allocate space for inserting this value, including
	 * the normalization on the fly and extreme normalization with all records in the database.
	 * @throws SorterSaveErrorExeption unsuccessful preservation model for any reason
	 */
	private function moveBetween($betweenA, $betweenB, $onlySet = false) {

		// higher boolean magic to preserve accuracy =) (calculation of the arithmetic mean)
		$middle = ($betweenA >> 1) + ($betweenB >> 1) + ($betweenA & $betweenB & 1);
		if ($middle == $betweenA || $middle == $betweenB) {
			$this->owner->{$this->sortField} = $this->normalizeSortFieldOnthefly($betweenA, $betweenB);
		} else {
			$this->owner->{$this->sortField} = $middle;
		}

		if ($onlySet === false) {
			if (!$this->owner->save()) {
				throw new SorterSaveErrorExeption(Yii::t('SorterActiveRecordBehavior', 'moveBetween save error. Error data: $this->owner =>' . CVarDumper::dumpAsString($this->owner->getErrors())));
			}
		}
	}

	/**
	 * Insert the first record in the database. The first entry is equal to half of the range of values of sort fields
	 * @param type $onlySet only set, not stored in the database
	 * @throws SorterSaveErrorExeption unsuccessful preservation model for any reason
	 */
	private function insertFirst($onlySet) {
		$this->owner->{$this->sortField} = 1 << ($this->sortFieldBitSize); // divide to 2. Take center of int

		if (!$onlySet) {
			if (!$this->owner->save()) {
				throw new SorterSaveErrorExeption(Yii::t('SorterActiveRecordBehavior', 'sorterInsertFirst save error. Error data: $this->owner =>' . CVarDumper::dumpAsString($this->owner->getErrors())));
			}
		}
	}

	/**
	 * Mathematical calculation maximum field
	 * sorting maintaining the accuracy of type int
	 * @return int the maximum value of the sort
	 */
	private function mathMaxSortField() {
		$sortFieldBitSizeNatural = 1 << $this->sortFieldBitSize;
		return $sortFieldBitSizeNatural - 1 + $sortFieldBitSizeNatural;
	}

	/**
	 * Getting the name of the primary key
	 * @return string|array line primary key field or an array of composite key
	 */
	private function primaryKeyName() {
		return $this->owner->getMetaData()->tableSchema->primaryKey;
	}

	/**
	 * Search for new discharge of the array (ЛМР*) at the range of records
	 * Optimized specifically for the full method (regular/extreme) normalization
	 * @param int $currentFreeSortSpaceBitSize current discharge of the array (ЛМР*) in binary representation
	 * @param int $minFreeSortSpaceBitSize the minimum allowable discharge of the array (ЛМР*) in binary representation
	 * @param int $elementCount the number of entries in the table
	 * @return int|null value found discharge of the array (ЛМР*) allowed to work under these conditions, or null if not found
	 */
	private function findNewFreeSortSpaceBitSizeByCount($currentFreeSortSpaceBitSize, $minFreeSortSpaceBitSize, $elementCount) {
		// search algorithm is a bit difference
		$findBitSpaceSize = null;
		if ($elementCount < $this->mathMaxSortField()) {
			for ($bitSpaceSize = $currentFreeSortSpaceBitSize; $bitSpaceSize >= $minFreeSortSpaceBitSize; --$bitSpaceSize) { // от большего шага к меньшему
				$realSpaceNatural = 1 << ($this->sortFieldBitSize - $bitSpaceSize);
				$maxNamberOfRecord = $realSpaceNatural - 1 + $realSpaceNatural;

				if ($maxNamberOfRecord >= $elementCount) {
					$findBitSpaceSize = $bitSpaceSize;
					break;
				}
			}
		}

		return isset($findBitSpaceSize) ? 1 << $findBitSpaceSize : null;
	}

	/**
	 * Search for new discharge of the array (ЛМР*) for the specified range of records
	 * The method is used for normalization on the fly
	 * 
	 * @param int $currentFreeSortSpaceBitSize current discharge of the array (ЛМР*) in binary representation
	 * @param int $minFreeSortSpaceBitSize the minimum allowable discharge of the array (ЛМР*) in binary representation
	 * @param int $elementCount the number of entries in the table
	 * @param int $beforeSortFieldValue the upper limit on the list
	 * @param int $afterSortFieldValue lower limit on the list
	 * @return int|null value found discharge of the array (ЛМР*) allowed to work under these conditions, or null if not found
	 */
	private function findNewFreeSortSpaceBitSizeByDiff($currentFreeSortSpaceBitSize, $minFreeSortSpaceBitSize, $elementCount, $beforeSortFieldValue, $afterSortFieldValue) {
		$currentDiff = $afterSortFieldValue - $beforeSortFieldValue;

		// search algorithm for comparing natural distances
		$newFreeSortSpaceBitSizeNatural = null;
		for ($bitSpaceSize = $currentFreeSortSpaceBitSize; $bitSpaceSize >= $minFreeSortSpaceBitSize; --$bitSpaceSize) { // от большего шага к меньшему
			$naturalSpaceSize = 1 << $bitSpaceSize;

			$localDiff = $elementCount * $naturalSpaceSize;
			if (!is_int($localDiff)) { // accuracy control, does not compute
				continue;
			}

			// if space enough
			if ($localDiff + $naturalSpaceSize <= $currentDiff) {
				$newFreeSortSpaceBitSizeNatural = $naturalSpaceSize;
				break;
			}
		}

		return $newFreeSortSpaceBitSizeNatural;
	}

	/**
	 * Search center-weighted value of the first record.Further, from this value
	 * is the distribution of other entries with the claims that have been previously
	 * @param int $elementCount the number of records in the specified range
	 * @param int $newFreeSortSpaceBitSizeNatural new discharge of the array (ЛМР*) in decimal
	 * @param int $beforeSortFieldValue the upper limit on the list
	 * @param int $afterSortFieldValue lower limit on the list
	 * @return int the value of the first record for further distribution
	 */
	private function centralWeightFirstElement($elementCount, $newFreeSortSpaceBitSizeNatural, $beforeSortFieldValue, $afterSortFieldValue) {
		if ($beforeSortFieldValue == 0 && $afterSortFieldValue == $this->mathMaxSortField()) {
			// center weighted with regular/extreme normalization
			$halfCount = $elementCount >> 1;
			$firstElementOffset = $newFreeSortSpaceBitSizeNatural * $halfCount;
			$middle = 1 << $this->sortFieldBitSize;
			return $middle - $firstElementOffset;
		} else {
			// center weighted with the normalization on the fly
			$currentDiff = $afterSortFieldValue - $beforeSortFieldValue;
			$elementSpace = $newFreeSortSpaceBitSizeNatural * $elementCount;
			$firstElementOffset = $currentDiff - $elementSpace;
			return $beforeSortFieldValue + $firstElementOffset;
		}
	}

	/**
	 * Before validate event
	 * @param type $event
	 */
	public function beforeValidate($event) {
		parent::beforeValidate($event);

		if ($this->owner->getIsNewRecord() && empty($this->owner->{$this->sortField})) {
			$this->sorterSetNextInsertSortValue();
		}
	}

	/**
	 * Validation of the setting is correct behavior
	 * @param CEvent $event
	 */
	public function afterConstruct($event) {
		parent::afterConstruct($event);

		// Only in debug mode. On prodakshene is a waste of electricity
		if (YII_DEBUG) {
			if (PHP_INT_SIZE == 4 && $this->sortFieldBitSize > 30) {
				throw new CException(Yii::t('SorterActiveRecordBehavior', 'The specified bit offset exceeds the capabilities of 32-bit architecture'));
			}

			if (PHP_INT_SIZE == 8 && $this->sortFieldBitSize > 62) {
				throw new CException(Yii::t('SorterActiveRecordBehavior', 'The specified bit offset exceeds the capabilities of 64-bit architecture'));
			}

			if ($this->sortFieldBitSize < 2) {
				throw new CException(Yii::t('SorterActiveRecordBehavior', 'Bit depth sortFieldBitSize({sortFieldBitSize}) must be greater than or equal to 3', array('{sortFieldBitSize}' => $this->sortFieldBitSize)));
			}

			if ($this->freeSortSpaceBitSize < 1 || $this->freeSortSpaceBitSize >= $this->sortFieldBitSize) {
				throw new CException(Yii::t('SorterActiveRecordBehavior', 'Incorrect setting of the field freeSortSpaceBitSize({freeSortSpaceBitSize}), must be greater than zero and less sortFieldBitSize({sortFieldBitSize})', array('{freeSortSpaceBitSize}' => $this->freeSortSpaceBitSize, '{sortFieldBitSize}' => $this->sortFieldBitSize)));
			}

			if ($this->minLocalFreeSortSpaceBitSize < 1 || $this->minLocalFreeSortSpaceBitSize > $this->freeSortSpaceBitSize) {
				throw new CException(Yii::t('SorterActiveRecordBehavior', 'Incorrect setting of the field minLocalFreeSortSpaceBitSize({minLocalFreeSortSpaceBitSize}), must be greater than zero and less than or equal to freeSortSpaceBitSize({freeSortSpaceBitSize})', array('{minLocalFreeSortSpaceBitSize}' => $this->minLocalFreeSortSpaceBitSize, '{freeSortSpaceBitSize}' => $this->freeSortSpaceBitSize)));
			}

			if (!is_string($this->primaryKeyName())) {
				throw new CException(Yii::t('SorterActiveRecordBehavior', 'The library does not know how to work a composite primary key'));
			}
		}
	}

}
