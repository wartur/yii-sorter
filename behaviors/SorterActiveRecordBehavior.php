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
 * Поведение поставляет работу с пользовательской сортировкой.
 * Поведение работает на основе алгоритма с разряженными массивами, тем самым
 * среднестатистическая вставка производится на один запрос на запись. Так же
 * для уменьшения деградации разряженного массива производятся несколько запросов
 * на чтение для определения специальных ситуаций и оптимизации их.
 * 
 * Поддержка параметра: $onlySet (только установить новое значение, но не забисывать в БД)
 * Некоторые методы из-за особенности реализации системы защиты от деградации
 * разояженного массива не могут быть использованы вместе с параметром $onlySet:
 * - sorterMove*
 * - sorterMoveNumber*
 * Другие методы допускают работу с $onlySet, но в некоторых случаях это приводит
 * к увеличению скорости деградации массива. Все описания вы найдете непосредственно
 * у каждого метода:
 * - sorterMoveTo*
 * - sorterMoveToModel*
 * - sorterMoveToPosition*
 * В идеале данный параметр используется только для первоначальной установки в моделе,
 * для последующей работы другой бизнес логики, но не для постоянного использования.
 * Вы можете сразуже выделить место для какой-то записи, далее заполнить оставшиеся поля
 * модели и сохранить её с использованием вашей бизнес логике.
 * 
 * Некоторые методы работают сразуже после создания класса,
 * а некоторые нет. Это происходит из-за невозможности определить начальную позицию.
 * Не работают сразу после создания:
 * - sorterMove*
 * - sorterMoveNumber*
 * 
 * Работают сразу после создания:
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
	 * New record will be inserted to begin
	 */
	const DEFAULT_INSERT_TO_BEGIN = false;

	/**
	 * New record will be inserted to end
	 */
	const DEFAULT_INSERT_TO_END = true;

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
	 * Поле для управления сортировкой записей
	 * Например `ORDER BY sort ASC`
	 */
	public $sortField = 'sort';

	/**
	 * @var string class group field
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
	 * @var boolean update politic
	 * @todo 1.2.1
	 */
	public $updatePolitic = self::UPDATE_POLITIC_USEMODEL;

	/**
	 * @var int битовый размер поля сортировки деленное на 2
	 * это число в натуральном размере является половиной поддерживаемого диапразона
	 * поля сортировки, от 1 до (1 << (sortFieldBitSize + 1)) - 1. Это означает, что первый элемент
	 * вставки будет равен 1 << sortFieldBitSize
	 * Вы можете выбрать это число любым, но с учетом правила
	 * sortFieldBitSize > freeSortSpaceBitSize >= minLocalFreeSortSpaceBitSize > 1
	 * 
	 * Это настройка параметров алгоритма для конроля поля сортировки.
	 * Помтите, правильные параметры настройки алгоритма напрямую влияют на производительность.
	 * Eсли не знаете как это работает, не изменяйте это, большинству систем достаточно
	 * настроек по умолчанию. Настройки по умолчанию полволяют контролировать
	 * без потери производительности около 100500 записей =)
	 * 
	 * Стандартные настройки sortFieldBitSize/freeSortSpaceBitSize/minLocalFreeSortSpaceBitSize
	 * on x86 php max is 30 (signed int).
	 * 30/15/4
	 * 
	 * on x64 php max is 62 (signed bigint)
	 * 62/31/6
	 * 
	 * @link https://github.com/wartur/yii-sorter-behavior/blob/master/ALGORITHM.md подробнее об алгоритме работы
	 */
	public $sortFieldBitSize = 30;

	/**
	 * @var int размер свободного пространства между записями по умолчанию (в битах)
	 * @link https://github.com/wartur/yii-sorter-behavior/blob/master/ALGORITHM.md подробнее об алгоритме работы
	 * 
	 * also see $sortFieldBitSize
	 */
	public $freeSortSpaceBitSize = 15;

	/**
	 * @var int минимальный размер свободного пространства между записями
	 * @link https://github.com/wartur/yii-sorter-behavior/blob/master/ALGORITHM.md подробнее об алгоритме работы
	 * 
	 * also see $sortFieldBitSize
	 */
	public $minLocalFreeSortSpaceBitSize = 4;

	/**
	 * @var int размер пакета записей для обработки, используется для ускорения
	 * работы базы данных. Чем это число больше, тем больше записей за раз
	 * загружается из базы данных. Если размер пакета был равен 1, то считается
	 * что пакетная обработка отключена
	 * 
	 * See ALGORITHM.md
	 */
	public $packageSize = 200;

	/**
	 * Поменять местами текущую запись и запись указанную в $model
	 * Своппирование происходит в 3 запроса записи, через переменную
	 * 
	 * В идеале, это можно сделать с помощью одного запроса своппирования в RDBM,
	 * но некоторые базы данных (MySQL) не поддерживают запроса своппирования
	 * 
	 * Данный метод позволяет справляться с повышенной деградацией при перестановки
	 * 2-х соседних записей
	 * 
	 * @param CActiveRecord $model модель с поведением SorterActiveRecordBehavior
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
	 * Переместить текущую запись на одну позицию вверх
	 * It's alias of self::sorterMove(true)
	 */
	public function sorterMoveUp() {
		$this->sorterMove(true);
	}

	/**
	 * Переместить текущую запись на одну позицию вниз
	 * It's alias of self::sorterMove(false)
	 */
	public function sorterMoveDown() {
		$this->sorterMove(false);
	}

	/**
	 * Переместить текущую запись на одну позицию
	 * Использует алгоритм своппирования. Деградации разряженного массива не происходит
	 * @param boolean $up направление перемещения. true - вверх, false - вниз
	 * @throws SorterOperationExeption для новой записи невозможно определить начальную позицию
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
	 * Переместить текущую запись на несколько позиций наверх
	 * It's alias of sorterMoveNumber(true, $number)
	 * 
	 * @param int $number количество позиций на которую требуется передвинуть
	 */
	public function sorterMoveNumberUp($number) {
		$this->sorterMoveNumber(true, $number);
	}

	/**
	 * Переместить текущую запись на несколько позиций вниз
	 * It's alias of sorterMoveNumber(false, $number)
	 * 
	 * @param int $number количество позиций на которую требуется передвинуть
	 */
	public function sorterMoveNumberDown($number) {
		$this->sorterMoveNumber(false, $number);
	}

	/**
	 * Переместить текущую запись на несколько позиций
	 * @param boolean $up направление перемещения. true - наверх, false - вниз
	 * @param int $number количество позиций перемещения
	 * @throws SorterOperationExeption для новой записи невозможно определить начальную позицию
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
			// берем 2 записи из будущего окружения позиции
			$condition = new CDbCriteria();
			$condition->addCondition("t.{$this->sortField} " . ($up ? '<= :sort' : '>= :sort'));
			$condition->order = "t.{$this->sortField} " . ($up ? 'DESC' : 'ASC');
			$condition->offset = $number;
			$condition->limit = 2;
			$condition->params['sort'] = $this->owner->{$this->sortField};

			$upModels = $this->owner->model()->findAll($condition);

			$count = count($upModels);
			if ($count == 0) { // если не найдено записей, то это граница списка
				$this->sorterMoveTo($up);
			} elseif ($count == 1) {
				// если найдена она запись, то это граница списка. Воспользуемся оптимизацией
				if ($up) {
					$this->moveToBeginFast($upModels[0]->{$this->sortField});
				} else {
					$this->moveToEndFast($upModels[0]->{$this->sortField});
				}
			} elseif ($count == 2) {
				// если найдено 2 записи, то это произвольное место в списке
				$this->moveBetween($upModels[0]->{$this->sortField}, $upModels[1]->{$this->sortField});
			}
		}
	}

	/**
	 * Переместить текущую запись в начало списка
	 * it's alias of sorterMoveTo(true, $onlySet)
	 * 
	 * @param boolean $onlySet только установить значение, не сохранять в БД
	 * Важно! независимо от параметра $onlySet будут произведены всевозможные
	 * действия для того, что бы выделить пространство для вставки этого значения,
	 * включая нормализацию на лету и экстремальную нормализацию со всеми записями
	 * в БД. Так же при использовании этого параметра не будет работать система
	 * свопа позиций. Вместо нее будет использован метод moveToBeginFast/moveToEndFast
	 */
	public function sorterMoveToBegin($onlySet = false) {
		$this->sorterMoveTo(true, $onlySet);
	}

	/**
	 * Переместить текущую запись в конец списка.
	 * it's alias of sorterMoveTo(false, $onlySet)
	 * 
	 * @param boolean $onlySet только установить значение, не сохранять в БД
	 * Важно! независимо от параметра $onlySet будут произведены всевозможные
	 * действия для того, что бы выделить пространство для вставки этого значения,
	 * включая нормализацию на лету и экстремальную нормализацию со всеми записями
	 * в БД. Так же при использовании этого параметра не будет работать система
	 * свопа позиций. Вместо нее будет использован метод moveToBeginFast/moveToEndFast
	 * Данный метод c параметром $onlySet=true рекомендуется использовать только при вставке новых записей!
	 */
	public function sorterMoveToEnd($onlySet = false) {
		$this->sorterMoveTo(false, $onlySet);
	}

	/**
	 * Переместить текущую запись на границу списка.
	 * 
	 * @param boolean $begin направление перемещение, true - в начало, false в конец
	 * @param boolean $onlySet только установить значение, не сохранять в БД
	 * Важно! независимо от параметра $onlySet будут произведены всевозможные
	 * действия для того, что бы выделить пространство для вставки этого значения,
	 * включая нормализацию на лету и экстремальную нормализацию со всеми записями
	 * в БД. Так же при использовании этого параметра не будет работать система
	 * свопа позиций. Вместо нее будет использован метод moveToBeginFast/moveToEndFast
	 * Данный метод c параметром $onlySet=true рекомендуется использовать только при вставке новых записей!
	 */
	public function sorterMoveTo($begin, $onlySet = false) {
		$records = $this->owner->model()->findAll(array(
			'limit' => 2,
			'order' => "t.{$this->sortField} " . ($begin ? 'ASC' : 'DESC'),
		));

		if (empty($records)) {
			$this->insertFirst($onlySet);
		} elseif (isset($records[1]) && $records[1]->getPrimaryKey() == $this->owner->getPrimaryKey()) {
			// при вставкеы новой записи этот код никогда не выполнится, так как getPrimaryKey == null
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
	 * Переместить текущую запись перед указанной моделью.
	 * it's alias of sorterMoveToModel(true, $pk, $onlySet)
	 * 
	 * @param int|CActiveRecord $pk модель или уникальный идентификатор записи
	 * @param boolean $onlySet только установить значение, не сохранять в БД
	 * Важно! независимо от параметра $onlySet будут произведены всевозможные
	 * действия для того, что бы выделить пространство для вставки этого значения,
	 * включая нормализацию на лету и экстремальную нормализацию со всеми записями
	 * в БД. Так же при использовании этого параметра не будет работать система
	 * свопа позиций. Вместо свопа будут использованы методы moveToBeginFast/moveToEndFast,
	 * а так же moveBetween - имеющий самый высокий деградационный эффект.
	 * Данный метод c параметром $onlySet=true рекомендуется использовать только при вставке новых записей!
	 */
	public function sorterMoveToModelBefore($pk, $onlySet = false) {
		$this->sorterMoveToModel(true, $pk, $onlySet);
	}

	/**
	 * Переместить текущую запись после указанной модели.
	 * it's alias of sorterMoveToModel(false, $pk, $onlySet)
	 * 
	 * @param int|CActiveRecord $pk модель или уникальный идентификатор записи
	 * @param boolean $onlySet только установить значение, не сохранять в БД
	 * Важно! независимо от параметра $onlySet будут произведены всевозможные
	 * действия для того, что бы выделить пространство для вставки этого значения,
	 * включая нормализацию на лету и экстремальную нормализацию со всеми записями
	 * в БД. Так же при использовании этого параметра не будет работать система
	 * свопа позиций. Вместо свопа будут использованы методы moveToBeginFast/moveToEndFast,
	 * а так же moveBetween - имеющий самый высокий деградационный эффект.
	 * Данный метод c параметром $onlySet=true рекомендуется использовать только при вставке новых записей!
	 */
	public function sorterMoveToModelAfter($pk, $onlySet = false) {
		$this->sorterMoveToModel(false, $pk, $onlySet);
	}

	/**
	 * Переместить текущую запись перед|после указанной модели
	 * 
	 * @param boolean $before место для вставки, true - перед записью, false - после записи
	 * @param int|CActiveRecord $pk модель или уникальный идентификатор записи
	 * @param boolean $onlySet только установить значение, не сохранять в БД
	 * Важно! независимо от параметра $onlySet будут произведены всевозможные
	 * действия для того, что бы выделить пространство для вставки этого значения,
	 * включая нормализацию на лету и экстремальную нормализацию со всеми записями
	 * в БД. Так же при использовании этого параметра не будет работать система
	 * свопа позиций. Вместо свопа будут использованы методы moveToBeginFast/moveToEndFast,
	 * а так же moveBetween - имеющий самый высокий деградационный эффект.
	 * Данный метод c параметром $onlySet=true рекомендуется использовать только при вставке новых записей!
	 * @throws SorterKeyNotFindExeption модель-позиция перемещения не найдена
	 */
	public function sorterMoveToModel($before, $pk, $onlySet = false) {
		// оптимизация при внутреннем вызове метода
		if ($pk instanceof CActiveRecord) {
			$movePlaceAfterModel = $pk; // не загружаем модель

			if ($this->owner->getPrimaryKey() == $movePlaceAfterModel->getPrimaryKey()) {
				return null; // это та же запись, действий не требуется
			}
		} else {
			if ($this->owner->getPrimaryKey() == $pk) {
				return null; // это та же запись, действий не требуется
			}

			$movePlaceAfterModel = $this->owner->model()->findByPk($pk);
			if (empty($movePlaceAfterModel)) {
				throw new SorterKeyNotFindExeption(Yii::t('SorterActiveRecordBehavior', 'pk({pk}) not find in db', array('{pk}' => $pk)));
			}
		}

		// загружаем модель на одну позицию выше модели перемещения
		$beforeModel = $this->owner->model()->find(array(
			'condition' => "t.{$this->sortField} " . ($before ? '> :sort' : '< :sort'),
			'order' => "t.{$this->sortField} " . ($before ? 'ASC' : 'DESC'),
			'params' => array(
				'sort' => $movePlaceAfterModel->{$this->sortField}
			)
		));

		if (isset($beforeModel) && $beforeModel->getPrimaryKey() == $this->owner->getPrimaryKey()) {
			if ($onlySet) {
				// загрузим еще на одну модель выше
				$beforeModelBefore = $this->owner->model()->find(array(
					'condition' => "t.{$this->sortField} " . ($before ? '> :sort' : '< :sort'),
					'order' => "t.{$this->sortField} " . ($before ? 'ASC' : 'DESC'),
					'params' => array(
						'sort' => $beforeModel->{$this->sortField}
					)
				));

				if (empty($beforeModelBefore)) {
					if ($before) {
						$this->moveToBeginFast($beforeModel->{$this->sortField}, $onlySet);
					} else {
						$this->moveToEndFast($beforeModel->{$this->sortField}, $onlySet);
					}
				} else {
					// очень неоптимально!!!
					$this->moveBetween($beforeModel->{$this->sortField}, $beforeModelBefore->{$this->sortField}, $onlySet);
				}
			} else {
				$this->sorterSwappWith($movePlaceAfterModel);
			}
		} else {
			// загружаем модель на 2 позиции ниже модели перемещения
			$afterPlaceModels = $this->owner->model()->findAll(array(
				'condition' => "t.{$this->sortField} " . ($before ? '< :sort' : '> :sort'),
				'order' => "t.{$this->sortField} " . ($before ? 'DESC' : 'ASC'),
				'limit' => 2,
				'params' => array(
					'sort' => $movePlaceAfterModel->{$this->sortField}
				)
			));

			if (empty($afterPlaceModels)) { // не найдено ни одной записи - это конец списка
				if ($before) {
					$this->moveToBeginFast($movePlaceAfterModel->{$this->sortField}, $onlySet);
				} else {
					$this->moveToEndFast($movePlaceAfterModel->{$this->sortField}, $onlySet);
				}
			} elseif (isset($afterPlaceModels[0]) && $afterPlaceModels[0]->getPrimaryKey() == $this->owner->getPrimaryKey()) {
				// noop, мы уже тут
			} elseif (isset($afterPlaceModels[1]) && $afterPlaceModels[1]->getPrimaryKey() == $this->owner->getPrimaryKey()) {
				if ($onlySet) {
					$this->moveBetween($afterPlaceModels[0]->{$this->sortField}, $movePlaceAfterModel->{$this->sortField}, $onlySet);
				} else {
					$this->sorterSwappWith($afterPlaceModels[0]);
				}
			} else {
				// вставка в произвольное место
				$this->moveBetween($afterPlaceModels[0]->{$this->sortField}, $movePlaceAfterModel->{$this->sortField}, $onlySet);
			}
		}
	}

	/**
	 * Переместить текущую запись перед указанной позицией в списке
	 * 
	 * @param int $position позиция для перемещения от начала списка
	 * @param boolean $onlySet только установить значение, не сохранять в БД
	 * Важно! независимо от параметра $onlySet будут произведены всевозможные
	 * действия для того, что бы выделить пространство для вставки этого значения,
	 * включая нормализацию на лету и экстремальную нормализацию со всеми записями
	 * в БД. Так же при использовании этого параметра не будет работать система
	 * свопа позиций. Вместо свопа будут использованы методы moveToBeginFast/moveToEndFast,
	 * а так же moveBetween - имеющий самый высокий деградационный эффект.
	 * Данный метод c параметром $onlySet=true рекомендуется использовать только при вставке новых записей!
	 */
	public function sorterMoveToPositionBefore($position, $onlySet = false) {
		$this->sorterMoveToPosition(true, $position, $onlySet);
	}

	/**
	 * Переместить текущую запись после указанной позицией в списке
	 * 
	 * @param int $position позиция для перемещения от начала списка
	 * @param boolean $onlySet только установить значение, не сохранять в БД
	 * Важно! независимо от параметра $onlySet будут произведены всевозможные
	 * действия для того, что бы выделить пространство для вставки этого значения,
	 * включая нормализацию на лету и экстремальную нормализацию со всеми записями
	 * в БД. Так же при использовании этого параметра не будет работать система
	 * свопа позиций. Вместо свопа будут использованы методы moveToBeginFast/moveToEndFast,
	 * а так же moveBetween - имеющий самый высокий деградационный эффект.
	 * Данный метод c параметром $onlySet=true рекомендуется использовать только при вставке новых записей!
	 */
	public function sorterMoveToPositionAfter($position, $onlySet = false) {
		$this->sorterMoveToPosition(false, $position, $onlySet);
	}

	/**
	 * Переместить текущую запись перед|после указанной позиции в списке
	 * 
	 * @param boolean $before место для вставки, true - перед позицией, false - после позиции
	 * @param int $position позиция для перемещения от начала списка
	 * @param boolean $onlySet только установить значение, не сохранять в БД
	 * Важно! независимо от параметра $onlySet будут произведены всевозможные
	 * действия для того, что бы выделить пространство для вставки этого значения,
	 * включая нормализацию на лету и экстремальную нормализацию со всеми записями
	 * в БД. Так же при использовании этого параметра не будет работать система
	 * свопа позиций. Вместо свопа будут использованы методы moveToBeginFast/moveToEndFast,
	 * а так же moveBetween - имеющий самый высокий деградационный эффект.
	 * Данный метод c параметром $onlySet=true рекомендуется использовать только при вставке новых записей!
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
				// находим позицию.
				$this->sorterMoveToModel($before, $model, $onlySet);
			}
		}
	}

	/**
	 * Порядок указанного списка записей. При смене порядка деградации
	 * разряженного массива происходить не будет
	 * 
	 * @todo 1.1.0 будет имплементировано при имплементации jQuery виджета сортировки
	 * 
	 * @param array $idsAfter массив идентификаторов моделей или массив моделей отсортированный в требуемом порядке
	 * @throws CException Not Implemented Exception
	 */
	public function sorterChangeIdsOrderTo(array $idsAfter) {
		throw new CException(Yii::t('SorterActiveRecordBehavior', 'Not Implemented Exception'));
	}

	/**
	 * Инверсировать список. Инверсия происходит без деградации разряженного массива
	 * с помощью операции вычитания текущих значений из максимума
	 * 
	 * @todo 1.1.0 будет имплементировано при имплементации jQuery виджета сортировки
	 * 
	 * @throws CException Not Implemented Exception
	 */
	public function sorterInverseAll() {
		// берем 1<<30 и вычитаем из него текущее значение, получаем инверсию
		//UPDATE ALL 1<<30 - $this->owner->{$this->sorterField};
		throw new CException(Yii::t('SorterActiveRecordBehavior', 'Not Implemented Exception'));
	}

	/**
	 * Регулярная нормализация разряженного массива
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
	 * Получение очередного значения для вставки новой записи
	 * @return int очередное значение поле сортировки в зависимости от настроек модели
	 */
	public function sorterSetNextInsertSortValue() {
		$this->sorterMoveTo(!$this->defaultInsertToEnd, true);

		return $this->owner->{$this->sortField};
	}

	/**
	 * Частичная нормализация для возможности вставки очередной записи
	 * в конфликтное место списка
	 * @param int $sortFieldA конфликтное значение 1
	 * @param int $sortFieldB конфликтное значение 2 (обычно отличается на 1 от "значения 1")
	 * @return int новое значение которое возможно вставить без конфликта
	 * @throws SorterOperationExeption "значение 1" не может быть равно "значению 2"
	 */
	private function normalizeSortFieldOnthefly($sortFieldA, $sortFieldB) {
		if ($sortFieldA == $sortFieldB) {
			throw new SorterOperationExeption(Yii::t('SorterActiveRecordBehavior', 'normalizeSortFieldOnthefly :: $sortFieldA({sortFieldA}) and $sortFieldB({sortFieldB}) cant be equal', array('{sortFieldA}' => $sortFieldA, '{sortFieldB}' => $sortFieldB)));
		}

		// упорядочиваем A и B
		if ($sortFieldA < $sortFieldB) {
			$upSortFieldValue = $sortFieldA;
			$downSortFieldValue = $sortFieldB;
		} else {
			$upSortFieldValue = $sortFieldB;
			$downSortFieldValue = $sortFieldA;
		}

		// ======= алгоритм поиска пространства для нормализации =======
		$upDownCountCache = null;  // кеш количества записей от границы
		$doubleSearchMultiplier = 1; // множитель просмотра пространства
		$usingMin = $usingMax = false; // флаги сигнализирующие посмотр до конца нижнего или верннего списка
		do {
			// запрос выше по списку
			if ($usingMin === false) { // если мы достигли границы далее искать не требуется
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
			if ($usingMax === false) { // если мы достигли границы далее искать не требуется
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
				// полная нормализация - частный случай регулярной нормализации
				$elementCount = $this->owner->model()->count();
			} elseif ($usingMin && !$usingMax) {
				// начало списка
				if ($upDownCountCache === null) {
					$upDownCountCache = $this->owner->model()->count(array(
						'condition' => "t.{$this->sortField} <= :upSortFieldValue",
						'params' => array('upSortFieldValue' => $upSortFieldValue)
					));
				}

				// -1 означает, что мы не считаем пограничную запись
				$elementCount = $upDownCountCache + $this->freeSortSpaceBitSize * $doubleSearchMultiplier - 1;
			} elseif (!$usingMin && $usingMax) {
				// конец списка
				if ($upDownCountCache === null) {
					$upDownCountCache = $this->owner->model()->count(array(
						'condition' => "t.{$this->sortField} >= :downSortFieldValue",
						'params' => array('downSortFieldValue' => $downSortFieldValue)
					));
				}

				// -1 означает, что мы не считаем пограничную запись
				$elementCount = $upDownCountCache + $this->freeSortSpaceBitSize * $doubleSearchMultiplier - 1;
			} else {
				// стандартный поиск пространства
				$elementCount = $this->freeSortSpaceBitSize * 2 * $doubleSearchMultiplier - 2; // -2 пограничных
			}

			// если текущая запись новая или она не включена в найденный диапазон, то добавить к количеству
			if ($this->owner->isNewRecord || !($beforeSortFieldValue < $this->owner->{$this->sortField} && $this->owner->{$this->sortField} < $afterSortFieldValue)) {
				++$elementCount;
			}

			// произвести поиск ЛМР
			if ($usingMin && $usingMax) {
				$newFreeSortSpaceBitSizeNatural = $this->findNewFreeSortSpaceBitSizeByCount($this->freeSortSpaceBitSize, $this->minLocalFreeSortSpaceBitSize, $elementCount);
			} else {
				$newFreeSortSpaceBitSizeNatural = $this->findNewFreeSortSpaceBitSizeByDiff($this->freeSortSpaceBitSize, $this->minLocalFreeSortSpaceBitSize, $elementCount, $beforeSortFieldValue, $afterSortFieldValue);
			}

			// если пространства не найдено, удваиваем ширину просмотра
			if ($newFreeSortSpaceBitSizeNatural === null) {
				if ($doubleSearchMultiplier == PHP_INT_MAX) { // конец разрядности PHP
					break;
				}

				// если мы достигли предела точности числа, выжнем из целого числа все (+1)
				$doubleSearchMultiplier = $doubleSearchMultiplier == (PHP_INT_MAX >> 1) + 1 ? PHP_INT_MAX : $doubleSearchMultiplier << 1;
			}

			// пока не найден новая ЛМР или не просмотрена вся таблица
		} while ($newFreeSortSpaceBitSizeNatural === null && !($usingMin && $usingMax));

		// не найдено новой ЛМР после просмотра всей таблицы
		if ($newFreeSortSpaceBitSizeNatural === null) {
			if (!($usingMin && $usingMax)) { // если по каким-то причинам не найдено границы, то явно запросим из БД
				$elementCount = $this->owner->model()->count() + 1;  // +1 новый
			}

			return $this->normalizeSortFieldExtreme($elementCount, $upSortFieldValue);
		} else {
			// ======= поиск первого значения с которого производить вставку при нормализации =======
			if ($usingMin && !$usingMax) {
				// это элемент смещения сверху
				$startSortValue = $afterSortFieldValue - $newFreeSortSpaceBitSizeNatural * $elementCount;
			} elseif (!$usingMin && $usingMax) {
				// это элемент смещения снизу
				$startSortValue = $beforeSortFieldValue + $newFreeSortSpaceBitSizeNatural;
			} else {
				// это центрально-взвешенный элемент
				$startSortValue = $this->centralWeightFirstElement($elementCount, $newFreeSortSpaceBitSizeNatural, $beforeSortFieldValue, $afterSortFieldValue);
			}

			// ======= распределяем пространство =======
			return $this->distributeNewFreeSortSpaceBitSize($newFreeSortSpaceBitSizeNatural, $startSortValue, $beforeSortFieldValue, $afterSortFieldValue, $upSortFieldValue);
		}
	}

	/**
	 * Универсальный алгоритм распределения записей в разряженном массиве
	 * 
	 * @param int $newFreeSortSpaceBitSizeNatural пространство между записями в списке
	 * @param int $startSortValue стартовое значение
	 * @param int $beforeSortFieldValue верхняя граница распределения
	 * @param int $afterSortFieldValue нижняя граница распределения
	 * @param int $upSortFieldValue верхняя конфликтная запись (используется для определения нового значения вставки кофликтной записи)
	 * @return int новое значение которое возможно вставить без конфликта
	 * @throws SorterSaveErrorExeption неудачное сохранение модели по каким-либо причинам
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
				// здесь усложненное вычисление, но обычно их количество меньше
				foreach ($models as $entry) {
					$newFromSearch = $entry->{$this->sortField};

					// skip owner record
					if ($this->owner->getPrimaryKey() != $entry->getPrimaryKey()) {
						$entry->{$this->sortField} = $currentSortNatural;
						if (!$entry->save()) {
							throw new SorterSaveErrorExeption(Yii::t('SorterActiveRecordBehavior', 'Error data: $entry =>' . CVarDumper::dumpAsString($entry->getErrors())));
						}

						$currentSortNatural += $newFreeSortSpaceBitSizeNatural;
						// если мы видим кофликтную запись, оставляе для нее место и сохраняем значение для результата
						if ($upSortFieldValue == -$newFromSearch) {
							$result = $currentSortNatural;
							$currentSortNatural += $newFreeSortSpaceBitSizeNatural;
						}
					}
				}
			} else {
				// здесь упрощенное вычисление, но их количество больше
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
	 * Экстремальная нормализация пространства
	 * Является самой длительной операцией
	 * 
	 * @param int $elementCount количество записей для распределения
	 * @param int $upSortFieldValue верхняя конфликтная запись (используется для определения нового значения вставки кофликтной записи)
	 * @return int новое значение которое возможно вставить без конфликта
	 * @throws SorterOutOfFreeSortSpaceExeption предел заполнения, более невозможно перераспределить пространство
	 */
	private function normalizeSortFieldExtreme($elementCount, $upSortFieldValue = null) {
		Yii::log(Yii::t('SorterActiveRecordBehavior', 'Extreme normalisation situation. Check table({table})', array('{table}' => $this->owner->tableName())), CLogger::LEVEL_WARNING);

		// поиск локальной разряженности, при эстримальном распределении мы ищем вплоть до 0 (полной деградации)
		$newFreeSortSpaceBitSizeNatural = $this->findNewFreeSortSpaceBitSizeByCount($this->freeSortSpaceBitSize, 0, $elementCount);

		if ($newFreeSortSpaceBitSizeNatural === null) { // fulled degradation
			throw new SorterOutOfFreeSortSpaceExeption(Yii::t('SorterActiveRecordBehavior', 'Out of free sort space. Need reconfigure system in table({table})', array('{table}' => $this->owner->tableName())));
		}

		$maxSortField = $this->mathMaxSortField();

		// это центрально-взвешенный элемент
		$startSortValue = $this->centralWeightFirstElement($elementCount, $newFreeSortSpaceBitSizeNatural, 0, $maxSortField);

		return $this->distributeNewFreeSortSpaceBitSize($newFreeSortSpaceBitSizeNatural, $startSortValue, 0, $maxSortField, $upSortFieldValue);
	}

	/**
	 * Быстрое перемещение записи в начало списка без дополнительных проверок
	 * @param int $min достоверно определенное минимальное значение в списке
	 * Важно! это должно быть именно начальное значение списка, которое эквивалентно
	 * запросу SELECT MIN(sort). Люболе другое значение может привести к непредсказуемым
	 * результатом работы метода.
	 * @param boolean $onlySet только установить значение, не сохранять в БД
	 * Важно! независимо от параметра $onlySet будут произведены всевозможные
	 * действия для того, что бы выделить пространство для вставки этого значения,
	 * включая нормализацию на лету и экстремальную нормализацию со всеми записями
	 * в БД.
	 * @throws SorterSaveErrorExeption неудачное сохранение модели по каким-либо причинам
	 */
	private function moveToBeginFast($min, $onlySet = false) {
		$beginSortValue = $min - (1 << $this->freeSortSpaceBitSize);

		// проверка конца разряженного пространства
		if ($beginSortValue <= 0) {
			// Мы находимся у конца списка. Эта ситуация может быть предвестником экстремальной нормализации
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
	 * Быстрое перемещение записи в конец списка без дополнительных проверок
	 * @param int $max достоверно определенное максимальное значение в списке
	 * Важно! это должно быть именно конечное значение списка, которое эквивалентно
	 * запросу SELECT MAX(sort). Люболе другое значение может привести к непредсказуемым
	 * результатом работы метода.
	 * @param boolean $onlySet только установить значение, не сохранять в БД
	 * Важно! независимо от параметра $onlySet будут произведены всевозможные
	 * действия для того, что бы выделить пространство для вставки этого значения,
	 * включая нормализацию на лету и экстремальную нормализацию со всеми записями
	 * в БД.
	 * @throws SorterSaveErrorExeption неудачное сохранение модели по каким-либо причинам
	 */
	private function moveToEndFast($max, $onlySet = false) {
		$freeSortSpaceBitSizeNatural = 1 << $this->freeSortSpaceBitSize;
		$maxSortValue = $this->mathMaxSortField();

		if ($maxSortValue - $max <= $freeSortSpaceBitSizeNatural) {
			// Мы находимся у конца списка. Эта ситуация может быть предвестником экстремальной нормализации
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
	 * Переместить запись между указанными значениями. Параметры "знечение 1" и "значение 2"
	 * ДОЛЖНЫ быть идущие одни за другим в любой последовательности. Передача
	 * значений которые идут непоследовательно может повлеч к непредсказуемым результатам
	 * @param int $betweenA значение 1
	 * @param int $betweenB значение 2 (идущая следующая/пред "записью 1")
	 * @param boolean $onlySet только установить значение, не сохранять в БД
	 * Важно! независимо от параметра $onlySet будут произведены всевозможные
	 * действия для того, что бы выделить пространство для вставки этого значения,
	 * включая нормализацию на лету и экстремальную нормализацию со всеми записями
	 * в БД.
	 * @throws SorterSaveErrorExeption неудачное сохранение модели по каким-либо причинам
	 */
	private function moveBetween($betweenA, $betweenB, $onlySet = false) {

		// higher boolean magic to preserve accuracy =) (подсчет среднего арифметического)
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
	 * Вставить первуз запись в базу данных. Первая запись равна половиной диапазона значений поля сортировки
	 * @param type $onlySet только установить значение, не сохранять в БД
	 * @throws SorterSaveErrorExeption неудачное сохранение модели по каким-либо причинам
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
	 * Математический подсчет максимального значения поля сортировки
	 * с сохранением точности типа int
	 * @return int максимальное значение поля сортировки
	 */
	private function mathMaxSortField() {
		$sortFieldBitSizeNatural = 1 << $this->sortFieldBitSize;
		return $sortFieldBitSizeNatural - 1 + $sortFieldBitSizeNatural;
	}

	/**
	 * Получение имени первичного ключа
	 * @return string|array строка поля первичного ключа либо массив составного ключа
	 */
	private function primaryKeyName() {
		return $this->owner->getMetaData()->tableSchema->primaryKey;
	}

	/**
	 * Поиск новой мощьности разряженности по всему диапазону записей
	 * Оптимизированый метод специально для полной (регулярной/экстримальной) нормализации
	 * @param int $currentFreeSortSpaceBitSize текущая ЛМР в битовом представлении
	 * @param int $minFreeSortSpaceBitSize минимальная допустимая ЛМР в битовом представлении
	 * @param int $elementCount количество записей в таблице
	 * @return int|null значение найденой ЛМР допустимой для работы в указанных условиях, либо null если не найдено
	 */
	private function findNewFreeSortSpaceBitSizeByCount($currentFreeSortSpaceBitSize, $minFreeSortSpaceBitSize, $elementCount) {
		// поиск: алгоритм битовой разницы
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
	 * Поиск новой мощьности разряженности по указанному диапазону записей
	 * Метод используется в нормализации на лету
	 * 
	 * @param int $currentFreeSortSpaceBitSize текущая ЛМР в битовом представлении
	 * @param int $minFreeSortSpaceBitSize минимальная допустимая ЛМР в битовом представлении
	 * @param int $elementCount количество записей в указанном диапазоне
	 * @param int $beforeSortFieldValue верхняя граница в списке
	 * @param int $afterSortFieldValue нижняя граница в списке
	 * @return int|null значение найденой ЛМР допустимой для работы в указанных условиях, либо null если не найдено
	 */
	private function findNewFreeSortSpaceBitSizeByDiff($currentFreeSortSpaceBitSize, $minFreeSortSpaceBitSize, $elementCount, $beforeSortFieldValue, $afterSortFieldValue) {
		$currentDiff = $afterSortFieldValue - $beforeSortFieldValue;

		// поиск: алгоритм сравнений натуральных расстояний
		$newFreeSortSpaceBitSizeNatural = null;
		for ($bitSpaceSize = $currentFreeSortSpaceBitSize; $bitSpaceSize >= $minFreeSortSpaceBitSize; --$bitSpaceSize) { // от большего шага к меньшему
			$naturalSpaceSize = 1 << $bitSpaceSize;

			$localDiff = $elementCount * $naturalSpaceSize;
			if (!is_int($localDiff)) { // контроль точности, не производим рассчет
				continue;
			}

			// если места хватило
			if ($localDiff + $naturalSpaceSize <= $currentDiff) {
				$newFreeSortSpaceBitSizeNatural = $naturalSpaceSize;
				break;
			}
		}

		return $newFreeSortSpaceBitSizeNatural;
	}

	/**
	 * Поиск центрально-взвешенного значения первой записи. От этого значения
	 * далее происходит распределение других записей по очередности которая была ранее
	 * @param int $elementCount количество записей в указанном диапазоне
	 * @param int $newFreeSortSpaceBitSizeNatural новая ЛМР в натуральном представлении
	 * @param int $beforeSortFieldValue верхняя граница в списке
	 * @param int $afterSortFieldValue нижняя граница в списке
	 * @return int значение первой записи для дальнейшего распределения
	 */
	private function centralWeightFirstElement($elementCount, $newFreeSortSpaceBitSizeNatural, $beforeSortFieldValue, $afterSortFieldValue) {
		if ($beforeSortFieldValue == 0 && $afterSortFieldValue == $this->mathMaxSortField()) {
			// центрально взвешенный при реглуярной/экстремальной нормализации
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
	 * Валидация правильности настройки бихевиора
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

			if (!is_string($this->primaryKeyName())) {
				throw new CException(Yii::t('SorterActiveRecordBehavior', 'Библиотека не умеет работать составным первичным ключем'));
			}
		}
	}

}
