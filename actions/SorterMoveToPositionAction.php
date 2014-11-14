<?php

/**
 * SorterMoveToPositionAction class file.
 *
 * @author		Krivtsov Artur (wartur) <gwartur@gmail.com> | Made in Russia
 * @copyright	Krivtsov Artur © 2014
 * @link		https://github.com/wartur/yii-sorter-behavior
 * @license		New BSD license
 */
Yii::import('sorter.components.SorterAbstractMoveAction', true);

/**
 * SorterMoveToPositionAction
 * 
 * @author Krivtsov Artur (wartur) <gwartur@gmail.com> | Made in Russia
 * @since v1.0.0
 */
class SorterMoveToPositionAction extends SorterAbstractMoveAction {

	/**
	 * 
	 * @param CActiveRecord $model
	 */
	public function transactionRun(CActiveRecord $model) {
		/* @var $model SorterActiveRecordBehavior */
		if ($this->getIsDirectionUp()) {
			$model->sorterMoveToPositionBefore($this->getParam(true));
		} else {
			$model->sorterMoveToPositionAfter($this->getParam(true));
		}
	}

}
