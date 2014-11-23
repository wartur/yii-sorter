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
 * Abstract action movement records
 * This class checks for the presence of parameters in GET | POST,
 * receives the value creates a transaction and invokes the immediate
 * implementation of the action (transactionRun)
 * 
 * @author Krivtsov Artur (wartur) <gwartur@gmail.com> | Made in Russia
 * @since v1.0.0
 */
abstract class SorterAbstractMoveAction extends CAction {

	/**
	 * Direction parameter :: to up
	 */
	const DIRECTION_UP = 'up';

	/**
	 * Direction parameter :: to down
	 */
	const DIRECTION_DOWN = 'down';

	/**
	 * Direction parameter from GET|POST
	 */
	const DIRECTION = 'd';

	/**
	 * Parameter of additional parameter of the action from GET|POST
	 */
	const PARAM = 'p';

	/**
	 * The method in which the action is moving directly
	 */
	abstract public function transactionRun(CActiveRecord $model);

	/**
	 * Inherited from CAction. The method takes parameters,
	 * validates and creates the transaction and performs the specified command
	 * @param string $id record identifier (primary key), a required parameter GET ['id']
	 * @throws CHttpException 400 bad request, if is not POST request
	 * @throws CException 500 error if something went wrong
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
	 * Get an additional parameter movement recording
	 * @param boolean $post if true, it means taking from POST or GET taken from
	 * @param boolean $convertToInt whether the results are needed to convert to a particular type
	 * @return boolean|string the result of obtaining the parameter script
	 * @throws CHttpException 400 bad request, if the parameter was not passed
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
	 * Get the parameter direction of travel records
	 * @return string up - moving upward, down - move down
	 * @throws CHttpException 400 bad request, if the parameter was not passed
	 */
	public function getDirection() {
		if (isset($_GET[self::DIRECTION]) && $_GET[self::DIRECTION] == self::DIRECTION_UP || $_GET[self::DIRECTION] == self::DIRECTION_DOWN) {
			return $_GET[self::DIRECTION];
		} else {
			throw new CHttpException(400, Yii::t('sorter', 'Bad param. direction({direction}), support only ({up}) and ({down})', array('direction' => empty($_GET[self::DIRECTION]) ? '@empty' : $_GET[self::DIRECTION], 'up' => self::DIRECTION_UP, 'down' => self::DIRECTION_DOWN)));
		}
	}

	/**
	 * Get moving direction recording
	 * @return boolean true - the direction of travel up, false - down
	 */
	public function getIsDirectionUp() {
		return $this->getDirection() === self::DIRECTION_UP;
	}

}
