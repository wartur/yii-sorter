<?php

/**
 * SorterMoveToModelAction class file.
 *
 * @author Krivtsov Artur (wartur) <gwartur@gmail.com> | Made in Russia
 * @copyright Krivtsov Artur © 2014
 * @link https://github.com/wartur/yii-sorter-behavior
 * @license New BSD license
 */

/**
 * SorterMoveToModelAction
 * 
 * @author Krivtsov Artur (wartur) <gwartur@gmail.com> | Made in Russia
 * @since v1.0.0
 */
class SorterMoveToModelAction extends CAction {

	/**
	 * 
	 * @param CActiveRecord $model
	 */
	public function transactionRun(CActiveRecord $model) {
		/* @var $model SorterActiveRecordBehavior */
		if ($this->getIsMoveUp()) {
			$model->sorterCurrentMoveBefore($this->getNumberParam());
		} else {
			$model->sorterCurrentMoveAfter($this->getNumberParam());
		}
	}

}
