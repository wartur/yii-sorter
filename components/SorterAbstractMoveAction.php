<?php

/**
 * SorterAbstractMoveAction class file.
 *
 * @author Krivtsov Artur (wartur) <gwartur@gmail.com> | Made in Russia
 * @copyright Krivtsov Artur © 2014
 * @link https://github.com/wartur/yii-sorter-behavior
 * @license New BSD license
 */

/**
 * SorterAbstractMoveAction
 * 
 * @author Krivtsov Artur (wartur) <gwartur@gmail.com> | Made in Russia
 * @since v1.0.0
 */
abstract class SorterAbstractMoveAction extends CAction {

	/**
	 * 
	 */
	const DIRECTION_UP = 'up';

	/**
	 * 
	 */
	const DIRECTION_DOWN = 'down';

	/**
	 * 
	 */
	const DIRECTION = 'd';

	/**
	 * 
	 */
	const PARAM = 'p';

	/**
	 * 
	 */
	const FLASH_HIGHLIGHT_PREFIX = 'sorterFlash';

	/**
	 *
	 * @var boolean
	 */
	public $useFlashHighlight = true;

	/**
	 * 
	 */
	abstract public function transactionRun(CActiveRecord $model);

	/**
	 * 
	 * @throws CHttpException
	 * @throws CException
	 */
	public function run($id) {
		if (!Yii::app()->request->isPostRequest) {
			throw new CHttpException(400, Yii::t('SorterAbstractMoveAction', 'Bad request. Can be only POST'));
		}

		$transaction = Yii::app()->db->beginTransaction();
		try {
			$model = $this->controller->loadModel($id);
			/* @var $model CActiveRecord */

			$this->transactionRun($model);

			$sorter = Yii::app()->sorter;
			/* @var $sorter Sorter */
			if ($sorter->useFlashHighlight && !$model->isNewRecord) {
				$sorter->setFlashHighlight(get_class($model), $model->getPrimaryKey());
			}

			$transaction->commit();
		} catch (Exception $e) {
			$transaction->rollback();

			if (YII_DEBUG) {
				$error = $e->getMessage();
			} else {
				$error = Yii::t('SorterAbstractMoveAction', "We know about this, don't worry!");
				Yii::log($e->getMessage(), CLogger::LEVEL_WARNING);
			}

			throw new CException(Yii::t('SorterAbstractMoveAction', 'Transaction error. {error}', array('{error}' => $error)));
		}
	}

	/**
	 * 
	 * @return integer
	 */
	public function getParam($post = false, $convertToInt = true) {
		$source = $post === false ? $_GET : $_POST;

		if (isset($source[self::PARAM])) {
			return $convertToInt ? (int) $source[self::PARAM] : $source[self::PARAM];
		} else {
			throw new CHttpException(400, Yii::t('sorter', 'Bad param. param is empty use source({source})', array('source' => $post === false ? 'GET' : 'POST')));
		}
	}

	/**
	 * 
	 * @return integer
	 */
	public function getDirection() {
		if (isset($_GET[self::DIRECTION]) && $_GET[self::DIRECTION] == self::DIRECTION_UP || $_GET[self::DIRECTION] == self::DIRECTION_DOWN) {
			return $_GET[self::DIRECTION];
		} else {
			throw new CHttpException(400, Yii::t('sorter', 'Bad param. direction({direction}), support only ({up}) and ({down})', array('direction' => empty($_GET[self::DIRECTION]) ? '@empty' : $_GET[self::DIRECTION], 'up' => self::DIRECTION_UP, 'down' => self::DIRECTION_DOWN)));
		}
	}

	/**
	 * 
	 * @return boolean
	 */
	public function getIsDirectionUp() {
		return $this->getDirection() === self::DIRECTION_UP;
	}

}
