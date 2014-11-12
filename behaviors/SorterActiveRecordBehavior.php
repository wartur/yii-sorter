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
	 * The algorithm will generate the maximum number of requests and save the maximum amount of free sort space
	 * @todo v1.2.1
	 */
	const FREESORTSPACE_SAFE_POLITIC_MAXSAFE = true;

	/**
	 * The algorithm will generate the small number of requests and save the small amount of free sort space
	 * @todo v1.2.1
	 */
	const FREESORTSPACE_SAFE_POLITIC_FASTSPEED = false;

	/**
	 * > Политика обновления записей
	 * Обновлять используя модели
	 * @todo v1.2.1
	 */
	const UPDATE_POLITIC_USEMODEL = true;

	/**
	 * > Политика обновления записей
	 * Обновлять используя sql запросы
	 * @todo v1.2.1
	 */
	const UPDATE_POLITIC_PURESQL = false;

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
	 * @var boolean politic for safe free space
	 * @todo 1.2.1
	 */
	public $freeSortSpaceSafePolitic = self::FREESORTSPACE_SAFE_POLITIC_MAXSAFE;

	/**
	 * @var boolean update politic
	 * @todo 1.2.1
	 */
	public $updatePolitic = self::UPDATE_POLITIC_USEMODEL;

	/**
	 * @var int bit size for sort field
	 * on x86 php max is 30 (signed int).
	 * on x64 php max is 62 (signed bigint)
	 * ALGORITHM.md
	 */
	public $sortFieldBitSize = 30;

	/**
	 * @var int
	 * See ALGORITHM.md
	 */
	public $freeSortSpaceBitSize = 15;

	/**
	 * @var int
	 * See ALGORITHM.md
	 */
	public $minLocalFreeSortSpaceBitSize = 4;

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
	public function sorterMathMaxSortField() {
		$sortFieldBitSizeNatural = 1 << $this->sortFieldBitSize;
		return $sortFieldBitSizeNatural - 1 + $sortFieldBitSizeNatural;
	}

	public function sorterPrimaryKeyName() {
		return $this->owner->getMetaData()->tableSchema->primaryKey;
	}

	public function sorterRealSpaceNatural() {
		return 1 << ($this->sortFieldBitSize - $this->freeSortSpaceBitSize);
	}

	public function sorterMaxCountOfRecord() {
		$realSpaceNatural = $this->sorterRealSpaceNatural();
		return $realSpaceNatural - 1 + $realSpaceNatural;
	}

	public function sorterFindNewFreeSortSpaceBitSizeByCount($currentFreeSortSpaceBitSize, $minFreeSortSpaceBitSize, $elementCount) {
		// поиск черел алгоритм битовой разницы предел
		$findBitSpaceSize = null;

		if ($elementCount < $this->sorterMathMaxSortField()) {
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

	public function sorterFindNewFreeSortSpaceBitSizeByDiff($currentFreeSortSpaceBitSize, $minFreeSortSpaceBitSize, $elementCount, $beforeSortFieldValue, $afterSortFieldValue) {
		$currentDiff = $afterSortFieldValue - $beforeSortFieldValue;

		// поиск черел алгоритм сравнений натуральных расстояний
		$newFreeSortSpaceBitSizeNatural = null;

		for ($bitSpaceSize = $currentFreeSortSpaceBitSize; $bitSpaceSize >= $minFreeSortSpaceBitSize; --$bitSpaceSize) { // от большего шага к меньшему
			$naturalSpaceSize = 1 << $bitSpaceSize;

			$localDiff = $elementCount * $naturalSpaceSize;
			if (!is_int($localDiff)) { // контроль точности, если вышли за предел точности берем максимум PHP
				$localDiff = PHP_INT_MAX;
			}

			// если места хватило
			if ($localDiff + $naturalSpaceSize <= $currentDiff) {
				$newFreeSortSpaceBitSizeNatural = $naturalSpaceSize;
				break;
			}
		}

		return $newFreeSortSpaceBitSizeNatural;
	}

	public function sorterCentralWeightFirstElement($elementCount, $newFreeSortSpaceBitSizeNatural, $beforeSortFieldValue, $afterSortFieldValue) {
		if ($beforeSortFieldValue == 0 && $afterSortFieldValue == $this->sorterMathMaxSortField()) {
			// центрально взвешенный при реглуярной/экстренной нормализации
			$halfCount = $elementCount >> 1;
			$firstElementOffset = $newFreeSortSpaceBitSizeNatural * $halfCount;
			$middle = 1 << $this->sortFieldBitSize;
			return $middle - $firstElementOffset;
		} else {
			// центрально взвешенный при нормализации на лету
			$currentDiff = $afterSortFieldValue - $beforeSortFieldValue;
			$elementSpace = $newFreeSortSpaceBitSizeNatural * $elementCount;
			$firstElementOffset = $currentDiff - $elementSpace;
			return $beforeSortFieldValue + $firstElementOffset;
		}
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
	 * Algorithm not reduce freeSortSpace
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
		$elementCount = $this->owner->model()->count();
		if ($elementCount > 0) {
			$newFreeSortSpaceBitSizeNatural = $this->sorterFindNewFreeSortSpaceBitSizeByCount($this->freeSortSpaceBitSize, $this->freeSortSpaceBitSize, $elementCount);

			if ($newFreeSortSpaceBitSizeNatural === null) {
				$this->normalizeSortFieldExtreme($elementCount);
			} else {

				$maxSortField = $this->sorterMathMaxSortField();

				// это центрально-взвешенный элемент
				$startSortValue = $this->sorterCentralWeightFirstElement($elementCount, $newFreeSortSpaceBitSizeNatural, 0, $maxSortField);

				$this->distributeNewFreeSortSpaceBitSize($newFreeSortSpaceBitSizeNatural, $startSortValue, 0, $maxSortField);
			}
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
						->limit(1, $this->freeSortSpaceBitSize * $doubleSearchMultiplier - 1)
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
						->limit(1, $this->freeSortSpaceBitSize * $doubleSearchMultiplier - 1)
						->queryScalar(array('downSortFieldValue' => $downSortFieldValue));
				if (empty($afterSortFieldValue)) {
					$usingMax = true;
					$afterSortFieldValue = $this->sorterMathMaxSortField();
				}
			}

			if ($usingMin && $usingMax) {
				// полная нормализация (частный случай регулярной нормализации)
				$elementCount = $this->owner->model()->count();
			} elseif ($usingMin && !$usingMax) {
				// начало списка
				if ($upDownCountCache === null) {
					$upDownCountCache = $this->owner->model()->count(array(
						'condition' => "t.{$this->sortField} <= :upSortFieldValue",
						'params' => array('upSortFieldValue' => $upSortFieldValue)
					));
				}

				$elementCount = $upDownCountCache + $this->freeSortSpaceBitSize * $doubleSearchMultiplier - 1;  // -1 пограничный
			} elseif (!$usingMin && $usingMax) {
				// конец списка
				if ($upDownCountCache === null) {
					$upDownCountCache = $this->owner->model()->count(array(
						'condition' => "t.{$this->sortField} >= :downSortFieldValue",
						'params' => array('downSortFieldValue' => $downSortFieldValue)
					));
				}

				$elementCount = $upDownCountCache + $this->freeSortSpaceBitSize * $doubleSearchMultiplier - 1; // -1 пограничный
			} else {
				// стандартный поиск пространства
				$elementCount = $this->freeSortSpaceBitSize * 2 * $doubleSearchMultiplier - 2; // -2 пограничных
			}

			// если текущий элемент новый или текущий элемент не включен в диапазон, то добавить еще его в количеству
			if ($this->owner->isNewRecord || !($beforeSortFieldValue < $this->owner->{$this->sortField} && $this->owner->{$this->sortField} < $afterSortFieldValue)) {
				++$elementCount;
			}

			// произвести поиск локальной мощьности разряженности
			if ($usingMin && $usingMax) {
				$newFreeSortSpaceBitSizeNatural = $this->sorterFindNewFreeSortSpaceBitSizeByCount($this->freeSortSpaceBitSize, $this->minLocalFreeSortSpaceBitSize, $elementCount);
			} else {
				$newFreeSortSpaceBitSizeNatural = $this->sorterFindNewFreeSortSpaceBitSizeByDiff($this->freeSortSpaceBitSize, $this->minLocalFreeSortSpaceBitSize, $elementCount, $beforeSortFieldValue, $afterSortFieldValue);
			}

			// если пространства не найдено, удваиваем ширину просмотра
			if ($newFreeSortSpaceBitSizeNatural === null) {
				if ($doubleSearchMultiplier == PHP_INT_MAX) { // конец разрядности PHP
					break;
				}

				$doubleSearchMultiplier = $doubleSearchMultiplier == (PHP_INT_MAX >> 1) + 1 ? PHP_INT_MAX : $doubleSearchMultiplier << 1;
			}
			// пока не найден новая ЛМР или это не предел просмотра траблицы
		} while ($newFreeSortSpaceBitSizeNatural === null && !($usingMin && $usingMax));

		// не найдено новой ЛМР
		if ($newFreeSortSpaceBitSizeNatural === null) {
			if (!($usingMin && $usingMax)) { // if $doubleSearchMultiplier == PHP_INT_MAX and !($usingMin && $usingMax)
				$elementCount = $this->owner->model()->count() + 1;  // +1 новый
			}

			return $this->normalizeSortFieldExtreme($elementCount, $upSortFieldValue);
		} else {
			// ======= поиск первого элемента с которого производить вставку при нормализации =======
			if ($usingMin && !$usingMax) {
				// это элемент смещения сверху
				$startSortValue = $afterSortFieldValue - $newFreeSortSpaceBitSizeNatural * $elementCount;
			} elseif (!$usingMin && $usingMax) {
				// это элемент смещения снизу
				$startSortValue = $beforeSortFieldValue + $newFreeSortSpaceBitSizeNatural;
			} else {
				// это центрально-взвешенный элемент
				$startSortValue = $this->sorterCentralWeightFirstElement($elementCount, $newFreeSortSpaceBitSizeNatural, $beforeSortFieldValue, $afterSortFieldValue);
			}

			// ======= распределяем пространство =======  (по сути это универсальный алгоритм, надо вынести в отдельный метод)
			return $this->distributeNewFreeSortSpaceBitSize($newFreeSortSpaceBitSizeNatural, $startSortValue, $beforeSortFieldValue, $afterSortFieldValue, $upSortFieldValue);
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

	protected function distributeNewFreeSortSpaceBitSize($newFreeSortSpaceBitSizeNatural, $startSortValue, $beforeSortFieldValue, $afterSortFieldValue, $upSortFieldValue = null) {
		$afterSortFieldValueExtend = $afterSortFieldValue == $this->sorterMathMaxSortField() ? PHP_INT_MAX : $afterSortFieldValue;

		$this->owner->model()->updateAll(array("$this->sortField" => new CDbExpression("-{$this->sortField}")), array(
			'condition' => ":beforeSortFieldValue < {$this->sortField} AND {$this->sortField} < :afterSortFieldValue",
			'order' => "{$this->sortField} ASC",
			'params' => array(
				'beforeSortFieldValue' => $beforeSortFieldValue,
				'afterSortFieldValue' => $afterSortFieldValueExtend
			)
		));

		// произвести стандартное вычисление элемента вставки after. DESC так как мы сделал негатив!!!
		$condition = new CDbCriteria();
		$condition->addCondition(":newFromSearch < t.{$this->sortField} AND t.{$this->sortField} < :maxSortField");
		$condition->order = "t.{$this->sortField} DESC";
		$condition->limit = $this->packageSize;
		$condition->params = array(
			'newFromSearch' => -$afterSortFieldValueExtend,
			'maxSortField' => -$beforeSortFieldValue
		);

		// если вставка в начало, то оставляем место
		$currentSortNatural = $startSortValue;
		if ($beforeSortFieldValue === 0 && isset($upSortFieldValue) && $upSortFieldValue == 0) {
			$result = $currentSortNatural;
			$currentSortNatural += $newFreeSortSpaceBitSizeNatural;
		} else {
			$result = null;
		}

		do {
			// пакетная обработка по $this->packageSize записей за раз
			$models = $this->owner->model()->findAll($condition);

			$newFromSearch = null;

			if (isset($upSortFieldValue)) { // статически развернем код
				foreach ($models as $entry) {

					$newFromSearch = $entry->{$this->sortField};

					// skip owner element
					if ($this->owner->getPrimaryKey() != $entry->getPrimaryKey()) {
						$entry->{$this->sortField} = $currentSortNatural;
						if (!$entry->save()) {
							throw new SorterSaveErrorExeption(Yii::t('SorterActiveRecordBehavior', 'Error data: $entry =>' . CVarDumper::dumpAsString($entry->getErrors())));
						}

						// if it part of on the fly algorithm + skip palce for 
						$currentSortNatural += $newFreeSortSpaceBitSizeNatural;
						if ($upSortFieldValue == -$newFromSearch) {
							$result = $currentSortNatural;
							$currentSortNatural += $newFreeSortSpaceBitSizeNatural;
						}
					}
				}
			} else {
				foreach ($models as $entry) {

					$newFromSearch = $entry->{$this->sortField};

					$entry->{$this->sortField} = $currentSortNatural;
					if (!$entry->save()) {
						throw new SorterSaveErrorExeption(Yii::t('SorterActiveRecordBehavior', 'Error data: $entry =>' . CVarDumper::dumpAsString($entry->getErrors())));
					}

					$currentSortNatural += $newFreeSortSpaceBitSizeNatural;
				}
			}

			// next package
			$condition->params['newFromSearch'] = $newFromSearch; // is more faster then $condition->offset += $this->pacakgeSize
		} while (count($models) == $this->packageSize || $newFromSearch === null);

		return $result;
	}

	/**
	 * Normalize fort field - extreme situation... Auhtung!
	 */
	protected function normalizeSortFieldExtreme($elementCount, $afterInsertSortId = null) {
		Yii::log(Yii::t('SorterActiveRecordBehavior', 'Extreme normalisation situation. Check table({table})', array('{table}' => $this->owner->tableName())), CLogger::LEVEL_WARNING);

		// find local free sort space bit size in global scope
		// for extreme sutuation min local free sort space less then minLocalFreeSortSpaceBitSize
		$newFreeSortSpaceBitSizeNatural = $this->sorterFindNewFreeSortSpaceBitSizeByCount($this->freeSortSpaceBitSize, 0, $elementCount);

		if ($newFreeSortSpaceBitSizeNatural === null) { // fulled degradation
			throw new SorterOutOfFreeSortSpaceExeption(Yii::t('SorterActiveRecordBehavior', 'Out of free sort space. Need reconfigure system in table({table})', array('{table}' => $this->owner->tableName())));
		}

		$maxSortField = $this->sorterMathMaxSortField();

		// это центрально-взвешенный элемент
		$startSortValue = $this->sorterCentralWeightFirstElement($elementCount, $newFreeSortSpaceBitSizeNatural, 0, $maxSortField);

		return $this->distributeNewFreeSortSpaceBitSize($newFreeSortSpaceBitSizeNatural, $startSortValue, 0, $maxSortField, $afterInsertSortId);
	}

	protected function moveToBeginFast($min, $onlySet = false) {
		$beginSortValue = $min - (1 << $this->freeSortSpaceBitSize);

		// check out of bits
		if ($beginSortValue <= 0) {
			// End place for insert to begin. It's Extreme warning situation
			Yii::log(Yii::t('SorterActiveRecordBehavior', 'PreExtreme situation. Check table({table})', array('{table}' => $this->owner->tableName())), CLogger::LEVEL_WARNING);

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
		$freeSortSpaceBitSizeNatural = 1 << $this->freeSortSpaceBitSize;
		$maxSortValue = $this->sorterMathMaxSortField();

		if ($maxSortValue - $max <= $freeSortSpaceBitSizeNatural) {
			// End place for insert to end. It's Extreme warning situation
			Yii::log(Yii::t('SorterActiveRecordBehavior', 'Preview Extreem situation. Check table ({table})', array('{table}' => $this->owner->tableName())), CLogger::LEVEL_WARNING);

			// move to between end and last record
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
				throw new CException(Yii::t('SorterActiveRecordBehavior', 'Указанное битное смещение превышает возможности 32 битной арихтектуры'));
			}

			if (PHP_INT_SIZE == 8 && $this->sortFieldBitSize > 62) {
				throw new CException(Yii::t('SorterActiveRecordBehavior', 'Указанное битное смещение превышает возможности 64 битной арихтектуры'));
			}

			if ($this->sortFieldBitSize < 2) {
				throw new CException(Yii::t('SorterActiveRecordBehavior', 'Разрядность sortFieldBitSize({sortFieldBitSize}) должна быть больше или рвно 3', array('{sortFieldBitSize}' => $this->sortFieldBitSize)));
			}

			if ($this->freeSortSpaceBitSize < 1 || $this->freeSortSpaceBitSize >= $this->sortFieldBitSize) {
				throw new CException(Yii::t('SorterActiveRecordBehavior', 'Неверная настройка поля freeSortSpaceBitSize({freeSortSpaceBitSize}), должно быть больше ноля и меньше sortFieldBitSize({sortFieldBitSize})', array('{freeSortSpaceBitSize}' => $this->freeSortSpaceBitSize, '{sortFieldBitSize}' => $this->sortFieldBitSize)));
			}

			if ($this->minLocalFreeSortSpaceBitSize < 1 || $this->minLocalFreeSortSpaceBitSize > $this->freeSortSpaceBitSize) {
				throw new CException(Yii::t('SorterActiveRecordBehavior', 'Неверная настройка поля minLocalFreeSortSpaceBitSize({minLocalFreeSortSpaceBitSize}), должно быть больше ноля и меньше или равно freeSortSpaceBitSize({freeSortSpaceBitSize})', array('{minLocalFreeSortSpaceBitSize}' => $this->minLocalFreeSortSpaceBitSize, '{freeSortSpaceBitSize}' => $this->freeSortSpaceBitSize)));
			}

			if (!is_string($this->sorterPrimaryKeyName())) {
				throw new CException(Yii::t('SorterActiveRecordBehavior', 'Библиотека не умеет работать составным первичным ключем'));
			}
		}
	}

}
