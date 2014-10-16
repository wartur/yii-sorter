<?php

/**
 * Sorter class file.
 *
 * @author Krivtsov Artur (wartur) <gwartur@gmail.com> | Made in Russia
 * @copyright Krivtsov Artur © 2014
 * @link https://github.com/wartur/yii-sorter-behavior
 * @license New BSD license
 */

/**
 * Sorter application component
 * 
 * @author Krivtsov Artur (wartur) <gwartur@gmail.com> | Made in Russia
 * @since v1.0.0
 */
class Sorter extends CApplicationComponent {

	/**
	 * 
	 */
	const FLASH_HIGHLIGHT_NAME = 'sorterFlashlight';

	/**
	 *
	 * @var type 
	 */
	public $flashHighlightClass = 'sorterFlashHighlight';

	/**
	 *
	 * @var type 
	 */
	public $flashHighlightTime = 4000;

	/**
	 *
	 * @var type 
	 */
	public $flashHighlightBackground = '#ffff99';

	/**
	 *
	 * @var boolean
	 */
	public $useFlashHighlight = false;

	/**
	 *
	 * @var type 
	 */
	private $scriptIsRegister = false;

	/**
	 *
	 * @var type 
	 */
	private $casheFlash = [];

	public function init() {
		parent::init();
	}

	/**
	 * 
	 * @param type $class
	 * @param type $id
	 */
	public function setFlashHighlight($class, $id) {
		if ($this->useFlashHighlight) {
			$user = Yii::app()->user;
			/* @var $user CWebUser */
			$flash = $user->getFlash(self::FLASH_HIGHLIGHT_NAME . $class, array());
			$flash[] = $id;
			$user->setFlash(self::FLASH_HIGHLIGHT_NAME . $class, $flash);
		}
	}

	/**
	 * 
	 * @param type $class
	 * @return type
	 */
	public function getFlashHighlight($class) {
		if (!isset($this->casheFlash[$class])) {
			$this->casheFlash[$class] = Yii::app()->user->getFlash(self::FLASH_HIGHLIGHT_NAME . $class, array());
		}

		return $this->casheFlash[$class];
	}

	/**
	 * 
	 * @param type $model
	 * @return boolean
	 */
	public function isFlashHighlightModel($model) {
		// проверяем есть ли в флешах данная модель
		if (in_array($model->getPrimaryKey(), $this->getFlashHighlight(get_class($model)))) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * 
	 * @param type $model
	 * @param type $srcArrayExpressionResult
	 * @return type
	 */
	public function fhGridRowHtmlOptionsExpression($model, $srcArrayExpressionResult = array()) {
		if ($this->useFlashHighlight && $this->isFlashHighlightModel($model)) {
			if (is_array($srcArrayExpressionResult)) {
				if (empty($srcArrayExpressionResult['class'])) {
					$srcArrayExpressionResult['class'] = $this->flashHighlightClass;
				} else {
					$srcArrayExpressionResult['class'] .= ' ' . $this->flashHighlightClass;
				}
			} else {
				$srcArrayExpressionResult = array(
					'class' => $this->flashHighlightClass
				);
			}
		}

		return $srcArrayExpressionResult;
	}

	/**
	 * 
	 * @param type $fullCallback
	 * @return type
	 */
	public function fhGridAfterUpdateCode($fullCallback = false) {
		if ($this->useFlashHighlight === false) {
			return $fullCallback ? null : ' '; // get noop function not register script
		}

		Yii::app()->clientScript->registerCoreScript('jquery.ui');

		$js = "jQuery('.{$this->flashHighlightClass}').effect('highlight', {color:'{$this->flashHighlightBackground}'}, {$this->flashHighlightTime})";

		return $fullCallback ? "js:function(){{$js}} " : " ;{$js}; ";
	}

	/**
	 * 
	 */
	public function registerClientScript() {
		if ($this->scriptIsRegister === false) {
			$am = Yii::app()->assetManager; /* @var $am CAssetManager */
			$cs = Yii::app()->clientScript; /* @var $cs CClientScript */

			$publicPath = $am->publish(Yii::getPathOfAlias('sorter.assets'));

			$cs->registerCoreScript('jquery.ui');
			$cs->registerScriptFile($publicPath . "/sorter.js");

			$this->scriptIsRegister = true;
		}
	}

}
