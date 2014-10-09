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
	 * 
	 * @return type
	 */
	public function sorterDbMaxSortField($withoutOwner = false) {
		$command = $this->owner->getDbConnection()->createCommand()
				->select("MAX({$this->sortField})")
				->from($this->owner->tableName());

		if ($withoutOwner !== false) {
			$command->where("{$this->sorterPrimaryKeyName()} != :pk", array('pk' => $this->owner->getPrimaryKey()));
		}

		return $command->queryScalar();
	}

	/**
	 * 
	 * @return type
	 */
	public function sorterDbMinSortField($withoutOwner = false) {
		$command = $this->owner->getDbConnection()->createCommand()
				->select("MIN({$this->sortField})")
				->from($this->owner->tableName());

		if ($withoutOwner !== false) {
			$command->where("{$this->sorterPrimaryKeyName()} != :pk", array('pk' => $this->owner->getPrimaryKey()));
		}

		return $command->queryScalar();
	}

	/**
	 * 
	 * @return type
	 */
	public function sorterMathMaxSortField() {
		$sortFieldBitSizeNatural = 1 << $this->sortFieldBitSize;
		return $sortFieldBitSizeNatural - 1 + $sortFieldBitSizeNatural;
	}

	public function sorterPrimaryKeyName() {
		return $this->owner->getMetaData()->tableSchema->primaryKey;
	}

	public function sorterRealSpaceNatural() {
		return 1 << ($this->sortFieldBitSize - $this->dischargeSpaceBitSize);
	}

	public function sorterMaxCountOfRecord() {
		$realSpaceNatural = $this->sorterRealSpaceNatural();
		return $realSpaceNatural - 1 + $realSpaceNatural;
	}

	/**
	 * Swap current record with $model record
	 * @param CActiveRecord $model
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
	 * Move up this record relatively sortField ASC
	 * 
	 * it's swapp algorithm. Some RDBM not support swapp (like MySQL).
	 * We do swapp through app server
	 */
	public function sorterCurrentMoveUp() {
		if ($this->owner->getIsNewRecord()) {
			throw new SorterOperationExeption(Yii::t('SorterActiveRecordBehavior', 'sorterCurrentMoveUp not support when it is new record'));
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
			throw new SorterOperationExeption(Yii::t('SorterActiveRecordBehavior', 'sorterCurrentMoveDown not support when it is new record'));
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
		} elseif (isset($records[1]) && $records[1]->getPrimaryKey() == $this->owner->getPrimaryKey()) {
			// если это операция вставки новой записи, то она никогда не произойдет так как getPrimaryKey => null
			$this->sorterSwappWith($records[0]); // swap
		} elseif (isset($records[0]) && $records[0]->getPrimaryKey() == $this->owner->getPrimaryKey()) {
			// noop
		} else {
			$this->moveToBeginFast($records[0]->{$this->sortField}, $onlySet); // standart move
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
		} elseif (isset($records[1]) && $records[1]->getPrimaryKey() == $this->owner->getPrimaryKey()) {
			// если это операция вставки новой записи, то она никогда не произойдет так как getPrimaryKey => null
			$this->sorterSwappWith($records[0]); // swap
		} elseif (isset($records[0]) && $records[0]->getPrimaryKey() == $this->owner->getPrimaryKey()) {
			// noop
		} else {
			$this->moveToEndFast($records[0]->{$this->sortField}, $onlySet); // standart move
		}
	}

	/**
	 * Move current record up on count of position relatively sortField ASC
	 * @param int $number count of record to move
	 */
	public function sorterCurrentMoveUpNumber($number) {
		if ($this->owner->getIsNewRecord()) {
			throw new SorterOperationExeption(Yii::t('SorterActiveRecordBehavior', 'sorterCurrentMoveUpNumber not support when it is new record'));
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
			throw new SorterOperationExeption(Yii::t('SorterActiveRecordBehavior', 'sorterCurrentMoveDownNumber not support when it is new record'));
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
	public function sorterNormalizeSortFieldRegular() {

		// default 15 bit
		//32767 - 1 + 32767 = 65535;
		$realSpaceNatural = 1 << ($this->sortFieldBitSize - $this->dischargeSpaceBitSize);
		$maxNamberOfRecord = $realSpaceNatural - 1 + $realSpaceNatural;
		$currentRecordCount = $this->owner->model()->count();

		// if current is new значит приделать к количеству
		if (isset($upSortFieldValue) && isset($downSortFieldValue) && $this->owner->getIsNewRecord()) {
			++$currentRecordCount;
		}

		if ($maxNamberOfRecord < $currentRecordCount) {
			$newDischargeSpaceBitSize = $this->normalizeSortFieldExtreme($currentRecordCount, $afterInsertSortId);
		} else {
			$this->distributeNewDischargeSpaceBitSize($this->dischargeSpaceBitSize, $currentRecordCount);
		}
	}

	/**
	 * Нормализация на лету
	 * 
	 * Алгоритм v3
	 * - поиск свободного пространства для нормализации в цикле
	 * $doubleSearchMultiplier является квадратичным множителем, а не линейным
	 * - универсальный алгоритм распределения (для всех методов)
	 * 
	 * @param type $sortFieldA
	 * @param type $sortFieldB
	 */
	protected function normalizeSortFieldOnthefly($sortFieldA, $sortFieldB) {
		if ($sortFieldA == $sortFieldB) {
			throw new SorterOperationExeption(Yii::t('SorterActiveRecordBehavior', 'normalizeSortFieldOnthefly :: $sortFieldA({sortFieldA}) and $sortFieldB({sortFieldB}) cant be equal', array('{sortFieldA}' => $sortFieldA, '{sortFieldB}' => $sortFieldB)));
		}

		if ($sortFieldA < $sortFieldB) {
			$upSortFieldValue = $sortFieldA;
			$downSortFieldValue = $sortFieldB;
		} else {
			$upSortFieldValue = $sortFieldB;
			$downSortFieldValue = $sortFieldA;
		}

		// ======= алгоритм поиска пространства для нормализации =======
		$upDownCountCache = null;  // кеш количества записей от границы
		$doubleSearchMultiplier = 1; // множетель поиска пространства
		$usingMin = $usingMax = false;
		do {
			// запрос выше по списку
			if ($usingMin === false) { // экомония обращения к БД: если находим границу, далее поиск производить не нужно
				$beforeSortFieldValue = $this->owner->dbConnection->createCommand()
						->select($this->sortField)
						->from($this->owner->tableName())
						->where("{$this->sortField} <= :upSortFieldValue")
						->order("{$this->sortField} DESC")
						->limit(1, $this->dischargeSpaceBitSize * $doubleSearchMultiplier - 1)
						->queryScalar(array('upSortFieldValue' => $upSortFieldValue));
				if (empty($beforeSortFieldValue)) {
					$usingMin = true;
					$beforeSortFieldValue = 0;
				}
			}

			// запрос ниже по списку
			if ($usingMax === false) { // экомония обращения к БД: если находим границу, далее поиск производить не нужно
				$afterSortFieldValue = $this->owner->dbConnection->createCommand()
						->select($this->sortField)
						->from($this->owner->tableName())
						->where("{$this->sortField} >= :downSortFieldValue")
						->order("{$this->sortField} ASC")
						->limit(1, $this->dischargeSpaceBitSize * $doubleSearchMultiplier - 1)
						->queryScalar(array('downSortFieldValue' => $downSortFieldValue));
				if (empty($afterSortFieldValue)) {
					$usingMax = true;
					$afterSortFieldValue = $this->sorterMathMaxSortField();
				}
			}

			// получение пространства доступного для нормализации
			$currentDiff = $afterSortFieldValue - $beforeSortFieldValue;

			if ($usingMin && $usingMax) {
				// полная нормализация (частный случай регулярной нормализации)
				$elementCount = $this->owner->model()->count() + 1;  // +1 новый
			} elseif ($usingMin && !$usingMax) {
				// начало списка
				if ($upDownCountCache === null) {
					$upDownCountCache = $this->owner->model()->count(array(
						'condition' => "t.{$this->sortField} <= :upSortFieldValue",
						'params' => array('upSortFieldValue' => $upSortFieldValue)
					));
				}

				$elementCount = $upDownCountCache + $this->dischargeSpaceBitSize * $doubleSearchMultiplier;  // -1 пограничный +1 новый
			} elseif (!$usingMin && $usingMax) {
				// конец списка
				if ($upDownCountCache === null) {
					$upDownCountCache = $this->owner->model()->count(array(
						'condition' => "t.{$this->sortField} >= :downSortFieldValue",
						'params' => array('downSortFieldValue' => $downSortFieldValue)
					));
				}

				$elementCount = $upDownCountCache + $this->dischargeSpaceBitSize * $doubleSearchMultiplier; // -1 пограничный +1 новый
			} else {
				// стандартный поиск пространства
				$elementCount = $this->dischargeSpaceBitSize * 2 * $doubleSearchMultiplier - 1; // -2 пограничных +1 новый
			}

			// микрооптимзиация пространства: не считаем элемент, который находится внутри нормализируемого пространства
			if (!$this->owner->isNewRecord && $beforeSortFieldValue < $this->owner->{$this->sortField} && $this->owner->{$this->sortField} < $afterSortFieldValue) {
				--$elementCount;
			}

			// произвести поиск локальной мощьности разряженности
			$newDischargeSpaceBitSizeNatural = null;
			for ($bitSpaceSize = $this->dischargeSpaceBitSize - 1; $bitSpaceSize >= $this->minLocalDischargeSpaceBitSize; --$bitSpaceSize) { // от меньшего шага к большему
				$naturalSpaceSize = 1 << $bitSpaceSize;

				$localDiff = $elementCount * $naturalSpaceSize;

				// если места хватило
				if ($localDiff < $currentDiff) {
					$newDischargeSpaceBitSizeNatural = $naturalSpaceSize;
					break;
				}
			}

			// если пространства не найдено, удваиваем ширину просмотра
			if ($newDischargeSpaceBitSizeNatural === null) {
				if ($doubleSearchMultiplier == PHP_INT_MAX) { // конец разрядности PHP
					break;
				}

				$doubleSearchMultiplier = $doubleSearchMultiplier == (PHP_INT_MAX >> 1) + 1 ? PHP_INT_MAX : $doubleSearchMultiplier << 1;
			}
			// пока не найден новая ЛМР или это не предел просмотра траблицы
		} while ($newDischargeSpaceBitSizeNatural === null || !($usingMin && $usingMax));

		// не найдено новой ЛМР
		if ($newDischargeSpaceBitSizeNatural === null) {
			if(!($usingMin && $usingMax)) {
				$elementCount = $this->owner->model()->count() + 1;  // +1 новый
			}
			
			return $this->normalizeSortFieldExtreme($elementCount, $upSortFieldValue);
		} else {
			// ======= поиск первого элемента с которого производить вставку при нормализации =======
			if ($usingMin && !$usingMax) {
				// это элемент смещения сверху
				$firstElement = $newDischargeSpaceBitSizeNatural * $elementCount;
			} elseif (!$usingMin && $usingMax) {
				// это элемент смещения снизу
				$firstElement = $beforeSortFieldValue + $newDischargeSpaceBitSizeNatural;
			} else {
				// это центрально-взвешенный элемент
				$halfCount = $elementCount >> 1;
				$firstElementOffset = $newDischargeSpaceBitSizeNatural * $halfCount;

				$middle = ($beforeSortFieldValue >> 1) + ($afterSortFieldValue >> 1) + ($beforeSortFieldValue & $afterSortFieldValue & 1);

				// get first sort
				$firstElement = $middle - $firstElementOffset;
			}

			// ======= распределяем пространство =======  (по сути это универсальный алгоритм, надо вынести в отдельный метод)
			// преобразование в негатив
			$this->owner->model()->updateAll(array("$this->sortField" => new CDbExpression("-{$this->sortField}")), array(
				'condition' => ":beforeSortFieldValue < {$this->sortField} AND {$this->sortField} < :afterSortFieldValue",
				'order' => "{$this->sortField} ASC",
				'params' => array(
					'beforeSortFieldValue' => $beforeSortFieldValue,
					'afterSortFieldValue' => $afterSortFieldValue
				)
			));

			// произвести стандартное вычисление элемента вставки after. DESC так как мы сделал негатив!!!
			$condition = new CDbCriteria();
			$condition->addCondition(":newFromSearch < t.{$this->sortField} AND t.{$this->sortField} < :maxSortField");
			$condition->order = "t.{$this->sortField} DESC";
			$condition->limit = $this->packageSize;
			$condition->params = array(
				'newFromSearch' => -$afterSortFieldValue,
				'maxSortField' => -$beforeSortFieldValue
			);

			// если вставка в начало, то оставляем место
			$currentSortNatural = $firstElement;
			if ($beforeSortFieldValue == 0) {
				$result = $currentSortNatural;
				$currentSortNatural += $newDischargeSpaceBitSizeNatural;
			}

			do {
				// пакетная обработка по $this->packageSize записей за раз
				$models = $this->owner->model()->findAll($condition);

				$newFromSearch = null;

				foreach ($models as $entry) {

					$newFromSearch = $entry->{$this->sortField};

					$entry->{$this->sortField} = $currentSortNatural;
					if (!$entry->save()) {
						throw new SorterSaveErrorExeption(Yii::t('SorterActiveRecordBehavior', 'Error data: $entry =>' . CVarDumper::dumpAsString($entry->getErrors())));
					}

					// if it part of on the fly algorithm + skip palce for 
					$currentSortNatural += $newDischargeSpaceBitSizeNatural;
					if ($upSortFieldValue == -$newFromSearch) {
						$result = $currentSortNatural;
						$currentSortNatural += $newDischargeSpaceBitSizeNatural;
					}
				}

				// next package
				if (isset($newFromSearch)) {
					$condition->params['newFromSearch'] = $newFromSearch; // is more faster then $condition->offset += $this->pacakgeSize
				}
			} while (count($models) == $this->packageSize || $newFromSearch === null);

			return $result;
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
		Yii::log(Yii::t('SorterActiveRecordBehavior', 'Extreme normalisation situation. Check table ({table})', array('{table}' => $this->owner->tableName())), CLogger::LEVEL_WARNING);

		$findBitSpaceSize = null;

		// find local discharge space bit size in global scope
		// for extreme sutuation min local dischaged space less then minLocalDischargeSpaceBitSize
		for ($bitSpaceSize = $this->dischargeSpaceBitSize - 1; $bitSpaceSize >= 1; --$bitSpaceSize) { // от меньшего шага к большему
			$realSpaceNatural = 1 << ($this->sortFieldBitSize - $bitSpaceSize);
			$maxNamberOfRecord = $realSpaceNatural - 1 + $realSpaceNatural;

			if ($maxNamberOfRecord > $currentRecordCount) {
				$findBitSpaceSize = $bitSpaceSize;
				break;
			}
		}

		if ($findBitSpaceSize === null) { // fulled degradation
			throw new SorterOutOfDischargeSpaceExeption(Yii::t('SorterActiveRecordBehavior', 'Out of discharge space. Need reconfigure system in table({table})', array('{rable}' => $this->owner->tableName())));
		}

		$this->distributeNewDischargeSpaceBitSize($findBitSpaceSize, $currentRecordCount, $afterInsertSortId);

		return $findBitSpaceSize;
	}

	protected function distributeNewDischargeSpaceBitSize($newDischargeSpaceBitSize, $currentRecordCount, $afterInsertSortId = null) {
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
			'maxSortField' => - PHP_INT_MAX - 1
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
				if (!$entry->save()) {
					throw new SorterSaveErrorExeption(Yii::t('SorterActiveRecordBehavior', 'distributeNewDischargeSpaceBitSize save error. Error data: $entry =>' . CVarDumper::dumpAsString($entry->getErrors())));
				}

				// if it part of on the fly algorithm
				$currentSortNatural += $newDischargeSpaceBitSizeNatural;
				if (isset($afterInsertSortId) && $entry->getPrimaryKey() == $afterInsertSortId) {
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
		$beginSortValue = $min - (1 << $this->dischargeSpaceBitSize);

		// check out of bits
		if ($beginSortValue <= 0) {
			// End place for insert to begin. It's Extreme warning situation
			Yii::log(Yii::t('SorterActiveRecordBehavior', 'PreExtreme situation. Check table({table})', array('{rable}' => $this->owner->tableName())), CLogger::LEVEL_WARNING);

			// move to between start and last record
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

	protected function moveToEndFast($max, $onlySet = false) {
		$dischargeSpaceBitSizeNatural = 1 << $this->dischargeSpaceBitSize;
		$maxSortValue = $this->sorterMathMaxSortField();
		if (gettype($maxSortValue) != 'integer') { // check out of bits
			throw new SorterOperationExeption(Yii::t('SorterActiveRecordBehavior', 'Precision error $maxSortValue({maxSortValue}) is {type}. Expect integer', array('{data}' => $maxSortValue, '{type}' => gettype($maxSortValue))));
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
					throw new SorterSaveErrorExeption(Yii::t('SorterActiveRecordBehavior', 'moveToEndFast save error. Error data: $this->owner =>' . CVarDumper::dumpAsString($this->owner->getErrors())));
				}
			}
		}
	}

	/**
	 * Move current record to middle of 2 record
	 * @param CActiveRecord|integer $recordA
	 * @param CActiveRecord|integer $recordB
	 */
	protected function moveBetween($betweenA, $betweenB, $onlySet = false) {

		// higher boolean magic to preserve accuracy =)
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
	 * Insert first record.
	 * @param type $onlySet
	 */
	protected function insertFirst($onlySet) {
		$this->owner->{$this->sortField} = 1 << ($this->sortFieldBitSize); // divide to 2. Take center of int

		if (!$onlySet) {
			if (!$this->owner->save()) {
				throw new SorterSaveErrorExeption(Yii::t('SorterActiveRecordBehavior', 'sorterInsertFirst save error. Error data: $this->owner =>' . CVarDumper::dumpAsString($this->owner->getErrors())));
			}
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
	 * Валидации на правильность настройки бихевиора
	 * @param CEvent $event
	 */
	public function afterConstruct($event) {
		parent::afterConstruct($event);

		// Только в режиме отладки. На продакшене это бессмысленная трата электичества
		if (YII_DEBUG) {
			if (PHP_INT_SIZE == 4 && $this->sortFieldBitSize > 30) {
				// TODO: указанная битное смещение превышает возможности 32 битной арихтектуры
			}

			if (PHP_INT_SIZE == 8 && $this->sortFieldBitSize > 62) {
				// TODO: указанная битное смещение превышает возможности 64 битной арихтектуры
			}

			if ($this->sortFieldBitSize < 0) {
				// TODO: разрядность не может быть отрицательной
			}

			if ($this->dischargeSpaceBitSize < 0 || $this->dischargeSpaceBitSize >= $this->sortFieldBitSize) {
				// TODO: неверная настройка поля dischargeSpaceBitSize, должно быть больше ноля и меньше sortFieldBitSize
			}

			if ($this->minLocalDischargeSpaceBitSize < 0 || $this->minLocalDischargeSpaceBitSize > $this->dischargeSpaceBitSize) {
				// TODO: неверная настройка поля minLocalDischargeSpaceBitSize, должно быть больше ноля и меньше dischargeSpaceBitSize
			}

			// проверка типа первичного ключа. Библиотека не умеет работать с составными первичными ключами
			if (!is_string($this->sorterPrimaryKeyName())) {
				// TODO: библиотека не умеет работатьс составным первичным ключем
			}
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
