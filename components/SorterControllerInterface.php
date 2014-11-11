<?php

/**
 * SorterControllerInterface interface file.
 *
 * @author Krivtsov Artur (wartur) <gwartur@gmail.com> | Made in Russia
 * @copyright Krivtsov Artur © 2014
 * @link https://github.com/wartur/yii-sorter-behavior
 * @license New BSD license
 */

/**
 * Controller interface for work with Sorter Actions.
 * You can use this interface or SorterControllerBehavior
 * 
 * @author Krivtsov Artur (wartur) <gwartur@gmail.com> | Made in Russia
 * @since v1.0.0
 */
interface SorterControllerInterface {

	/**
	 * Loading work model for current controller
	 * @param mixed $pk
	 */
	public function loadModel($pk);

//	// A simple implementation of the method.
//	// You can implement this interface and this method in the controller to increase the speed of operation
//	public function loadModel($pk) {
//		$model = TYPE_THIS_YOUR_MODEL_NAME::model()->findByPk($pk);
//		if (empty($model)) {
//			throw new CHttpException('Not Find', 404);
//		}
//		return $model;
//	}
}
