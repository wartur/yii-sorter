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
	const DIRECTION_UP = 0;

	/**
	 * 
	 */
	const DIRECTION_DOWN = 1;

	/**
	 * 
	 */
	const DIRECTION = 'o';

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

			$this->transactionRun($model);

			if ($this->useFlashHighlight) {
				//Yii::app()->user->setFlash(self::FLASH_HIGHLIGHT_PREFIX . '.' . $id);
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
	public function getParam($post = false) {
		if ($post) {
			return isset($_POST[self::PARAM]) ? (int) $_POST[self::PARAM] : 1;
		} else {
			return isset($_GET[self::PARAM]) ? (int) $_GET[self::PARAM] : 1;
		}
	}

	/**
	 * 
	 * @return integer
	 */
	public function getDirection() {
		return isset($_GET[self::DIRECTION]) ? (int) $_GET[self::DIRECTION] : self::DIRECTION_UP; // The default move up
	}

	/**
	 * 
	 * @return boolean
	 */
	public function getIsDirectionUp() {
		return $this->getDirection() === self::DIRECTION_UP;
	}

	public function registerJsFlashHighlight() {
		$am = Yii::app()->assetManager; /* @var $am CAssetManager */
		$cs = Yii::app()->clienScript; /* @var $cs CClientScript */

		$cs->registerCoreScript('jquery.ui');

		$am->publish($path);
	}

}
