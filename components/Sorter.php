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
 * Application component that provides illumination records in GridView
 * 
 * Using highlight in CGridView:
 * <pre>
 * 	$this->widget('zii.widgets.grid.CGridView', array(
 * 		'dataProvider' => $model->search(),
 * 		'rowHtmlOptionsExpression' => 'Yii::app()->sorter->fhGridRowHtmlOptionsExpression($data)',
 * 		'afterAjaxUpdate' => Yii::app()->sorter->fhGridAfterUpdateCode(true),
 * 		'columns' => array('id'),
 * 	));
 * </pre>
 * 
 * @author Krivtsov Artur (wartur) <gwartur@gmail.com> | Made in Russia
 * @since v1.0.0
 */
class Sorter extends CApplicationComponent {

	/**
	 * Name prefix flash storage
	 */
	const FLASH_HIGHLIGHT_NAME = 'sorterFlashlight';

	/**
	 * @var string the name of the css class that represents the highlight of the table
	 */
	public $flashHighlightCssClass = 'sorterFlashHighlight';

	/**
	 * @var int backlight time table rows in milliseconds
	 */
	public $flashHighlightTime = 4000;

	/**
	 * @var string the illumination color
	 */
	public $flashHighlightBackground = '#ffff99';

	/**
	 * @var boolean enable or disable the system illumination table rows
	 */
	public $useFlashHighlight = false;
	
	/**
	 * @var boolean enable or disable LOCK TABLES for work with DB
	 * It is need to use if you are using MyISAM or MEMORY tables
	 */
	public $useLockTable = false;

	/**
	 * @var array cache backlight units
	 */
	private $casheFlash = array();

	/**
	 * Initializes the application component.
	 * This method is required by {@link IApplicationComponent} and is invoked by application.
	 * If you override this method, make sure to call the parent implementation
	 * so that the application component can be marked as initialized.
	 */
	public function init() {
		parent::init();
	}

	/**
	 * Set the class identifier and the record you wish to highlight
	 * the next time the page is loaded or updating CGridView
	 * @param string $class name of the class inherited from CActiveRecord
	 * @param int $id record identifier (primary key)
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
	 * Get a record you wish to highlight
	 * @param string $class name of the class inherited from CActiveRecord
	 * @return int record identifier (primary key)
	 */
	public function getFlashHighlight($class) {
		if (!isset($this->casheFlash[$class])) {
			$this->casheFlash[$class] = Yii::app()->user->getFlash(self::FLASH_HIGHLIGHT_NAME . $class, array());
		}

		return $this->casheFlash[$class];
	}

	/**
	 * Checks whether the model parameters passed to the illuminated entry
	 * @param CActiveRecord $model model to check the condition of illumination
	 * @return boolean true, if the model is the one that you want to highlight
	 */
	public function isFlashHighlightModel($model) {
		if (in_array($model->getPrimaryKey(), $this->getFlashHighlight(get_class($model)))) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * The method returns a CGridView::rowHtmlOptionsExpression options for highlighting lines
	 * 
	 * Using in CGridView:
	 * <pre>
	 * 	$this->widget('zii.widgets.grid.CGridView', array(
	 * 		'dataProvider' => $model->search(),
	 * 		'rowHtmlOptionsExpression' => 'Yii::app()->sorter->fhGridRowHtmlOptionsExpression($data)',
	 * 		'afterAjaxUpdate' => Yii::app()->sorter->fhGridAfterUpdateCode(true),
	 * 		'columns' => array('id'),
	 * 	));
	 * </pre>
	 * 
	 * You might to use srcArrayExpressionResult, to add a custom class
	 * to a table row or perform other rowHtmlOptionsExpression
	 * <pre>
	 * 	// add some class 
	 * 	$this->widget('zii.widgets.grid.CGridView', array(
	 * 		'dataProvider' => $model->search(),
	 * 		'rowHtmlOptionsExpression' => 'Yii::app()->sorter->fhGridRowHtmlOptionsExpression($data, array("class" => "myclass"))',
	 * 		'afterAjaxUpdate' => Yii::app()->sorter->fhGridAfterUpdateCode(true),
	 * 		'columns' => array('id'),
	 * 	));
	 * 
	 * 	// add other expression
	 * 	$this->widget('zii.widgets.grid.CGridView', array(
	 * 		'dataProvider' => $model->search(),
	 * 		'rowHtmlOptionsExpression' => 'Yii::app()->sorter->fhGridRowHtmlOptionsExpression($data, $data->myOtherExpression())',
	 * 		'afterAjaxUpdate' => Yii::app()->sorter->fhGridAfterUpdateCode(true),
	 * 		'columns' => array('id'),
	 * 	));
	 * </pre>
	 * 
	 * @param CActiveRecord $model model to check the condition of illumination
	 * @param array $srcArrayExpressionResult the result of the original expression
	 * @return array expression result
	 */
	public function fhGridRowHtmlOptionsExpression($model, $srcArrayExpressionResult = array()) {
		if ($this->useFlashHighlight && $this->isFlashHighlightModel($model)) {
			if (is_array($srcArrayExpressionResult)) {
				if (empty($srcArrayExpressionResult['class'])) {
					$srcArrayExpressionResult['class'] = $this->flashHighlightCssClass;
				} else {
					$srcArrayExpressionResult['class'] .= ' ' . $this->flashHighlightCssClass;
				}
			} else {
				$srcArrayExpressionResult = array(
					'class' => $this->flashHighlightCssClass
				);
			}
		}

		return $srcArrayExpressionResult;
	}

	/**
	 * The method returns a CGridView::afterAjaxUpdate JavaScript for highlighting lines
	 * 
	 * Using in CGridView:
	 * <pre>
	 * // Heads up! you must pass "true" parameter, if you use this method standalone
	 * 	$this->widget('zii.widgets.grid.CGridView', array(
	 * 		'dataProvider' => $model->search(),
	 * 		'rowHtmlOptionsExpression' => 'Yii::app()->sorter->fhGridRowHtmlOptionsExpression($data)',
	 * 		'afterAjaxUpdate' => Yii::app()->sorter->fhGridAfterUpdateCode(true),
	 * 		'columns' => array('id'),
	 * 	));
	 * </pre>
	 * 
	 * You might to use the method as part of your callback
	 * <pre>
	 * 	$this->widget('zii.widgets.grid.CGridView', array(
	 * 		'dataProvider' => $model->search(),
	 * 		'rowHtmlOptionsExpression' => 'Yii::app()->sorter->fhGridRowHtmlOptionsExpression($data)',
	 * 		'afterAjaxUpdate' => 'js:function(){console.log("myCallback") ' . Yii::app()->sorter->fhGridAfterUpdateCode() . '}',
	 * 		'columns' => array('id'),
	 * 	));
	 * </pre>
	 * 
	 * @param boolean $fullCallback true if it's standalone callback
	 * @return string JavaScript code which is executed afterAjaxUpdate
	 */
	public function fhGridAfterUpdateCode($fullCallback = false) {
		if ($this->useFlashHighlight === false) {
			return $fullCallback ? null : ' '; // get noop function not register script
		}

		Yii::app()->clientScript->registerCoreScript('jquery.ui');

		$js = "jQuery('.{$this->flashHighlightCssClass}').effect('highlight', {color:'{$this->flashHighlightBackground}'}, {$this->flashHighlightTime})";

		return $fullCallback ? "js:function(){{$js}} " : " ;{$js}; ";
	}

}
