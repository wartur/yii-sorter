<?php

/**
 * SorterButtonColumn class file.
 *
 * @author Krivtsov Artur (wartur) <gwartur@gmail.com> | Made in Russia
 * @copyright Krivtsov Artur © 2014
 * @link https://github.com/wartur/yii-sorter-behavior
 * @license New BSD license
 */
Yii::import('zii.widgets.grid.CButtonColumn', true);
Yii::import('sorter.components.SorterAbstractMoveAction');

/**
 * SorterButtonColumn
 * 
 * @author Krivtsov Artur (wartur) <gwartur@gmail.com> | Made in Russia
 * @since v1.0.0
 */
class SorterButtonColumn extends CButtonColumn {

	/**
	 *
	 * @var Sorter
	 */
	public $sorter = null;

	/**
	 * @var integer
	 */
	public $numberUp = 5;

	/**
	 * @var integer
	 */
	public $numberDown = 5;

	/**
	 * @var string the template that is used to render the content in each data cell.
	 * These default tokens are recognized: {view}, {update} and {delete}. If the {@link buttons} property
	 * defines additional buttons, their IDs are also recognized here. For example, if a button named 'preview'
	 * is declared in {@link buttons}, we can use the token '{preview}' here to specify where to display the button.
	 */
	public $template = '{begin} / {upNumber} / {up} / {down} / {downNumber} / {end}';

	public function init() {
		$this->sorter = Yii::app()->sorter;

		// only head for optimize css
		$this->headerHtmlOptions = CMap::mergeArray(array('style' => 'width: 170px;'), $this->headerHtmlOptions);

		// add csrf
		if (Yii::app()->request->enableCsrfValidation) {
			$csrfTokenName = Yii::app()->request->csrfTokenName;
			$csrfToken = Yii::app()->request->csrfToken;
			$csrf = "\n\t\tdata:{ '$csrfTokenName':'$csrfToken' },";
		} else {
			$csrf = '';
		}

		// create universal js
		$js = <<<EOD
js:function() {
	jQuery('#{$this->grid->id}').yiiGridView('update', {
		type: 'POST',
		url: jQuery(this).attr('href'),$csrf
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

		if (empty($this->buttons)) {
			$this->buttons = array(
				'begin' => array(
					'label' => 'B',
					'url' => 'Yii::app()->controller->createUrl("sorterMoveToEdge", array("id" => $data->primaryKey, SorterAbstractMoveAction::DIRECTION => SorterAbstractMoveAction::DIRECTION_UP))',
					'click' => $js,
					'options' => array(
						'class' => 'moveToBegin'
					)
				),
				'upNumber' => array(
					'label' => "Un{$this->numberUp}",
					'url' => 'Yii::app()->controller->createUrl("sorterMoveNumber", array("id" => $data->primaryKey, SorterAbstractMoveAction::DIRECTION => SorterAbstractMoveAction::DIRECTION_UP, SorterAbstractMoveAction::PARAM => $this->numberUp))',
					'click' => $js,
					'options' => array(
						'class' => 'moveUpNumber'
					)
				),
				'up' => array(
					'label' => 'U',
					'url' => 'Yii::app()->controller->createUrl("sorterMoveOne", array("id" => $data->primaryKey, SorterAbstractMoveAction::DIRECTION => SorterAbstractMoveAction::DIRECTION_UP))',
					'click' => $js,
					'options' => array(
						'class' => 'moveUp'
					)
				),
				'down' => array(
					'label' => 'D',
					'url' => 'Yii::app()->controller->createUrl("sorterMoveOne", array("id" => $data->primaryKey, SorterAbstractMoveAction::DIRECTION => SorterAbstractMoveAction::DIRECTION_DOWN))',
					'click' => $js,
					'options' => array(
						'class' => 'moveDown'
					)
				),
				'downNumber' => array(
					'label' => "Dn{$this->numberDown}",
					'url' => 'Yii::app()->controller->createUrl("sorterMoveNumber", array("id" => $data->primaryKey, SorterAbstractMoveAction::DIRECTION => SorterAbstractMoveAction::DIRECTION_DOWN, SorterAbstractMoveAction::PARAM => $this->numberDown))',
					'click' => $js,
					'options' => array(
						'class' => 'moveDownNumber'
					)
				),
				'end' => array(
					'label' => 'E',
					'url' => 'Yii::app()->controller->createUrl("sorterMoveToEdge", array("id" => $data->primaryKey, SorterAbstractMoveAction::DIRECTION => SorterAbstractMoveAction::DIRECTION_DOWN))',
					'click' => $js,
					'options' => array(
						'class' => 'moveToEnd'
					)
				),
			);
		}

		// it's optimizing js registration
		foreach ($this->buttons as $id => $button) {
			if (strpos($this->template, '{' . $id . '}') === false) {
				unset($this->buttons[$id]);
			}
		}

		$this->registerClientScript();
	}

}
