<?php

/**
 * SorterControllerBehavior class file.
 *
 * @author Krivtsov Artur (wartur) <gwartur@gmail.com> | Made in Russia
 * @copyright Krivtsov Artur © 2014
 * @link https://github.com/wartur/yii-sorter-behavior
 * @license New BSD license
 */

/**
 * Controller behavior for sorting actions.
 * Please read SorterControllerInterface for interesting information
 * 
 * @author Krivtsov Artur (wartur) <gwartur@gmail.com> | Made in Russia
 * @since v1.0.0
 */
class SorterControllerBehavior extends CBehavior implements SorterControllerInterface {

	/**
	 * @var string the class for load
	 */
	public $className = null;

	/**
	 * @var boolean
	 */
	public $throwHttpEcxeption = true;

	/**
	 * @var integer
	 */
	public $httpEcxeptionCode = 404;

	/**
	 * @var string
	 */
	public $notFindText = null;

	/**
	 * Loading model
	 * @param int $pk record id
	 * @return CActiveRecord|null active record or null if not find
	 * @throws CHttpException throw exception if throwHttpEcxeption = true
	 */
	public function loadModel($pk) {
		$className = $this->className;

		$model = $className::model()->findByPk($pk);
		if (empty($model) && $this->throwHttpEcxeption) {
			throw new CHttpException(empty($this->notFindText) ? Yii::t('SorterControllerBehavior', 'Record not find') : $this->notFindText, $this->httpEcxeptionCode);
		}

		return $model;
	}

}
