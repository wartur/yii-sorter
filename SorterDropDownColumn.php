<?php

/**
 * SorterDropDownColumn class file.
 *
 * @author Krivtsov Artur (wartur) <gwartur@gmail.com> | Made in Russia
 * @copyright Krivtsov Artur © 2014
 * @link https://github.com/wartur/yii-sorter-behavior
 * @license New BSD license
 */
Yii::import('zii.widgets.grid.CGridColumn');

/**
 * Dropdown column for simple work with SorterActiveRecordBehavior
 * 
 * @author Krivtsov Artur (wartur) <gwartur@gmail.com> | Made in Russia
 * @since v1.0.0
 */
class SorterDropDownColumn extends CGridColumn {

	const ALGO_MOVE_TO_MODEL = 'moveToModel';
	const ALGO_MOVE_TO_POSITION = 'moveToPosition';

	/**
	 * @var integer алгоритм работы
	 */
	public $algo = self::ALGO_MOVE_TO_POSITION;
	
	/**
	 * @var integer
	 */
	public $direction = SorterAbstractMoveAction::DIRECTION_UP;

	/**
	 * @var array
	 * Default: array(1, 2, 3, 4, 5, 6, 7, 8, 9);
	 */
	public $sortValues = array();

	/**
	 * @var string
	 */
	public $cssDropdownClass = null;

	public function init() {
		if (empty($this->sortValues)) {
			if($this->algo == self::ALGO_MOVE_TO_MODEL) {
				throw new CException(Yii::t('SorterDropDownColumn', 'sortValues is reqired if select algo == ({algo})', array('{algo}' => self::ALGO_MOVE_TO_MODEL)));
			} else {
				$this->sortValues = array(1, 2, 3, 4, 5, 6, 7, 8, 9);
			}
		}

		// only head for optimize css
		$this->headerHtmlOptions = CMap::mergeArray(array('style' => 'width: 120px;'), $this->headerHtmlOptions);

		if (empty($this->cssDropdownClass)) {
			$this->cssDropdownClass = 'moveDropdown';
		}

		// set csrf
		if (Yii::app()->request->enableCsrfValidation) {
			$csrfTokenName = Yii::app()->request->csrfTokenName;
			$csrfToken = Yii::app()->request->csrfToken;
			$csrf = "\n\t\tdata:{ '$csrfTokenName':'$csrfToken' },";
		} else {
			$csrf = '';
		}
		
		$paramConst = SorterAbstractMoveAction::PARAM;
		$jsOnChange = <<<EOD
function() {
	jQuery('#{$this->grid->id}').yiiGridView('update', {
		type: 'POST',
		url: {$this->grid->controller->createUrl($this->algo, array(SorterAbstractMoveAction::DIRECTION => $this->direction))},$csrf
		data: {{$paramConst}: $(this).val()}
		success: function(data) {
			jQuery('#{$this->grid->id}').yiiGridView('update');
			return false;
		},
		error: function(XHR) {
			return 'Произошла ошибка';
		}
	});
	return false;
}
EOD;

		$function = CJavaScript::encode($jsOnChange);
		$class = preg_replace('/\s+/', '.', $this->cssDropdownClass);
		$jqueryJs = "jQuery(document).on('change','#{$this->grid->id} select.{$class}',$function);";

		// инициализировать выпадающий список
		Yii::app()->getClientScript()->registerScript(__CLASS__ . '#' . $this->id, $jqueryJs);
	}

	protected function renderDropDown($row, $data) {
		//CHtml::dropDownList($name, $select, $data)
	}

	protected function renderDataCellContent($row, $data) {
		
	}

}
