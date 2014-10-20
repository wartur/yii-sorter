<?php

/**
 * SorterDropDownColumn class file.
 *
 * @author Krivtsov Artur (wartur) <gwartur@gmail.com> | Made in Russia
 * @copyright Krivtsov Artur © 2014
 * @link https://github.com/wartur/yii-sorter-behavior
 * @license New BSD license
 */
Yii::import('zii.widgets.grid.CGridColumn', true);
Yii::import('sorter.components.SorterAbstractMoveAction');

/**
 * Dropdown column for simple work with SorterActiveRecordBehavior
 * 
 * @author Krivtsov Artur (wartur) <gwartur@gmail.com> | Made in Russia
 * @since v1.0.0
 */
class SorterDropDownColumn extends CGridColumn {

	const ALGO_MOVE_TO_MODEL = 'sorterMoveToModel';
	const ALGO_MOVE_TO_POSITION = 'sorterMoveToPosition';

	/**
	 * @var integer алгоритм работы
	 */
	public $algo = self::ALGO_MOVE_TO_POSITION;

	/**
	 * @var integer
	 */
	public $direction = 'up';

	/**
	 * @var array
	 * Default: array((0), 1, 2, 3, 4, 5, 6, 7, 8, 9);
	 */
	public $sortValues = null;

	/**
	 * @var string
	 */
	public $cssDropdownClassPart = null;

	/**
	 * @var type 
	 */
	public $emptyText = null;

	/**
	 * @var string 
	 */
	public $onErrorMoveJsExpression = null;

	/**
	 *
	 * @var type 
	 */
	public $packToLink = true;

	/**
	 * @var type 
	 */
	protected $renderClass = null;

	/**
	 * @var type 
	 */
	protected $topDivRenderId = null;


	/**
	 * @var type 
	 */
	protected $dropDownRenderId = null;

	public function init() {
		if ($this->sortValues === null) {
			if ($this->algo == self::ALGO_MOVE_TO_MODEL) {
				throw new CException(Yii::t('SorterDropDownColumn', 'sortValues is reqired if select algo == ({algo})', array('{algo}' => self::ALGO_MOVE_TO_MODEL)));
			} else {
				if ($this->direction == SorterAbstractMoveAction::DIRECTION_UP) {
					$combine = array(1, 2, 3, 4, 5, 6, 7, 8, 9);
				} else {
					$combine = array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9);
				}
				$this->sortValues = array_combine($combine, $combine);
			}
		}

		// only head for optimize css
		$this->headerHtmlOptions = CMap::mergeArray(array('style' => 'width: 160px;'), $this->headerHtmlOptions);

		if (empty($this->cssDropdownClassPart)) {
			$this->cssDropdownClassPart = 'moveDropdown';
		}

		// set csrf
		if (Yii::app()->request->enableCsrfValidation) {
			$csrfTokenName = Yii::app()->request->csrfTokenName;
			$csrfToken = Yii::app()->request->csrfToken;
			$csrf = ", '$csrfTokenName':'$csrfToken'";
		} else {
			$csrf = '';
		}

		$paramConst = SorterAbstractMoveAction::PARAM;
		$dataParams = "\n\t\tdata:{ '{$paramConst}': $(this).val() {$csrf} },";

		$onErrorMoveMessage = isset($this->onErrorMoveMessage) ? $this->onErrorMoveMessage : Yii::t('SorterButtonColumn', 'Move error');

		$jsOnChange = <<<EOD
function() {
	jQuery('#{$this->grid->id}').yiiGridView('update', {
		type: 'POST',
		url: $(this).data('url'),$dataParams
		success: function(data) {
			jQuery('#{$this->grid->id}').yiiGridView('update');
			return false;
		},
		error: function(XHR) {
			return '{$onErrorMoveMessage}';
		}
	});
	$(this).attr('disabled', 'disabled');
	return false;
}
EOD;

		$class = preg_replace('/\s+/', '.', $this->cssDropdownClassPart);
		$this->renderClass = "{$class}_{$this->id}";
		$this->dropDownRenderId = "dropDown_{$this->id}";
		$resultJs = "jQuery(document).on('change','#{$this->grid->id} select.{$this->renderClass}',$jsOnChange);";

		if ($this->packToLink) {
			$resultJs .= "\n";
			$this->topDivRenderId = "topDiv_{$class}_{$this->id}";

			$resultJs .= <<<EOD
jQuery(document).on('mousedown','#{$this->grid->id} a.{$this->renderClass}',function() {
	_select = $($('#{$this->topDivRenderId}').html()).attr('data-url', $(this).attr("href"));
	$(this).after(_select);
	$(this).hide();
	_select.simulate('mousedown');
	return false;
});\n
EOD;
			
			$resultJs .= <<<EOD
jQuery(document).on('focusout','#{$this->grid->id} select.{$this->renderClass}',function(){
	$(this).parent().find('a.{$this->renderClass}').show();
	$(this).remove();
	return false;
});
EOD;
			// зарегистриуем либу для поддержки автовыпадения селекта
			$am = Yii::app()->assetManager; /* @var $am CAssetManager */
			$cs = Yii::app()->clientScript; /* @var $cs CClientScript */

			$path = $am->publish(Yii::getPathOfAlias('sorter.assets') . "/jquery.simulate.js");
			$cs->registerScriptFile($path);
		}
		
		// инициализировать выпадающий список
		Yii::app()->getClientScript()->registerScript(__CLASS__ . '#' . $this->id, $resultJs);
	}
	
	protected function renderHeaderCellContent() {
		parent::renderHeaderCellContent();
		
		if($this->packToLink) {
			// расположим динамические данные в хедар
			$dropDownHtml = CHtml::dropDownList($this->dropDownRenderId, null, $this->sortValues, array(
						'class' => $this->renderClass,
						'empty' => $this->getRealEmptyText(),
			));
			echo CHtml::tag('div', array('style' => 'display: none;', 'id' => $this->topDivRenderId), $dropDownHtml);
		}
	}

	protected function renderDataCellContent($row, $data) {
		if ($this->packToLink) {
			// тут вывести линк с доп данными которые далее будут резолвиться в селект
			echo CHtml::link($this->getRealEmptyText(), Yii::app()->controller->createUrl($this->algo, array('id' => $data->getPrimaryKey(), 'd' => $this->direction)), array(
				'class' => $this->renderClass
			));
		} else {
			echo CHtml::dropDownList("{$this->dropDownRenderId}_{$row}", null, $this->sortValues, array(
				'class' => $this->renderClass,
				'empty' => $this->getRealEmptyText(),
				'data-url' => Yii::app()->controller->createUrl($this->algo, array('id' => $data->getPrimaryKey(), 'd' => $this->direction))
			));
		}
	}

	public function getRealEmptyText() {
		$result = null;

		if ($this->algo == self::ALGO_MOVE_TO_MODEL) {
			if ($this->direction == 'up') {
				$result = isset($this->emptyText) ? $this->emptyText : Yii::t('SorterDropDownColumn', '(move before model)');
			} else {
				$result = isset($this->emptyText) ? $this->emptyText : Yii::t('SorterDropDownColumn', '(move after model)');
			}
		} else if ($this->algo == self::ALGO_MOVE_TO_POSITION) {
			if ($this->direction == 'up') {
				$result = isset($this->emptyText) ? $this->emptyText : Yii::t('SorterDropDownColumn', '(move before position)');
			} else {
				$result = isset($this->emptyText) ? $this->emptyText : Yii::t('SorterDropDownColumn', '(move after position)');
			}
		} else {
			throw new CException(Yii::t('SorterDropDownColumn', 'Unexpected algo == ({algo})', array('{algo}' => $this->algo)));
		}

		return $result;
	}

}
