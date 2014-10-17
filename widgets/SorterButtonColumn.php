<?php

/**
 * SorterButtonColumn class file.
 *
 * @author Krivtsov Artur (wartur) <gwartur@gmail.com> | Made in Russia
 * @copyright Krivtsov Artur © 2014
 * @link https://github.com/wartur/yii-sorter-behavior
 * @license New BSD license
 */
Yii::import('zii.widgets.grid.CGridColumn');

/**
 * SorterButtonColumn
 * 
 * @author Krivtsov Artur (wartur) <gwartur@gmail.com> | Made in Russia
 * @since v1.0.0
 */
class SorterButtonColumn extends CGridColumn {

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
	 * @var string 
	 */
	public $onErrorMoveJsExpression = null;

	/**
	 * @var array 
	 */
	public $buttons = array();

	/**
	 *
	 * @var type 
	 */
	public $eventClass = 'sorterMoveButtons';

	/**
	 * @var string the template that is used to render the content in each data cell.
	 * These default tokens are recognized: {begin}, {upNumber}, {up}, {down}, {downNumber}, {end}. If the {@link buttons} property
	 * defines additional buttons, their IDs are also recognized here. For example, if a button named 'preview'
	 * is declared in {@link buttons}, we can use the token '{preview}' here to specify where to display the button.
	 */
	public $template = '{begin} / {upNumber} / {up} / {down} / {downNumber} / {end}';

	public function init() {
		$this->sorter = Yii::app()->sorter;

		// only head for optimize css
		$this->headerHtmlOptions = CMap::mergeArray(array('style' => 'width: 170px;'), $this->headerHtmlOptions);

		// set default buttons
		if (empty($this->buttons)) {
			$this->buttons = array(
				'begin' => array(
					'label' => 'B',
					'moveMethod' => 'sorterMoveToEdge',
					'moveDirection' => 'up',
				),
				'upNumber' => array(
					'label' => "Un{$this->numberUp}",
					'moveMethod' => 'sorterMoveNumber',
					'moveDirection' => 'up',
					'moveParam' => $this->numberUp,
				),
				'up' => array(
					'label' => 'U',
					'moveMethod' => 'sorterMoveOne',
					'moveDirection' => 'up',
				),
				'down' => array(
					'label' => 'D',
					'moveMethod' => 'sorterMoveOne',
					'moveDirection' => 'down',
				),
				'downNumber' => array(
					'label' => "Dn{$this->numberDown}",
					'moveMethod' => 'sorterMoveNumber',
					'moveDirection' => 'down',
					'moveParam' => $this->numberDown,
				),
				'end' => array(
					'label' => 'E',
					'moveMethod' => 'sorterMoveToEdge',
					'moveDirection' => 'down',
				),
			);
		}

		// prepare buttons and optimize render of buttons
		foreach ($this->buttons as $id => &$button) {
			if (strpos($this->template, '{' . $id . '}') === false) {
				unset($this->buttons[$id]);
			} else {
				if (empty($button['htmlOptions'])) {
					$button['htmlOptions'] = array(
						'class' => $this->eventClass
					);
				} elseif (empty($button['htmlOptions']['class'])) {
					$button['htmlOptions']['class'] = $this->eventClass;
				} else {
					$button['htmlOptions']['class'] .= ' ' . $this->eventClass;
				}
			}
		}

		// add csrf
		if (Yii::app()->request->enableCsrfValidation) {
			$csrfTokenName = Yii::app()->request->csrfTokenName;
			$csrfToken = Yii::app()->request->csrfToken;
			$csrf = "\n\t\tdata:{ '$csrfTokenName':'$csrfToken' },";
		} else {
			$csrf = '';
		}

		$onErrorMoveMessage = isset($this->onErrorMoveMessage) ? $this->onErrorMoveMessage : Yii::t('SorterButtonColumn', 'Move error');

		// register universal js
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
			return '{$onErrorMoveMessage}';
		}
	});
	return false;
}
EOD;
		$function = CJavaScript::encode($js);
		$class = preg_replace('/\s+/', '.', $this->eventClass);
		$jqueryJs = "jQuery(document).on('click','#{$this->grid->id} a.{$class}',$function);";
		Yii::app()->getClientScript()->registerScript(__CLASS__ . '#' . $this->id, $jqueryJs);
	}

	/**
	 * Renders the data cell content.
	 * This method renders the view, update and delete buttons in the data cell.
	 * @param integer $row the row number (zero-based)
	 * @param mixed $data the data associated with the row
	 */
	protected function renderDataCellContent($row, $data) {
		$tr = array();
		ob_start();
		foreach ($this->buttons as $id => $button) {
			$htmlOptions = is_array($button['htmlOptions']) ? $button['htmlOptions'] : array();

			$params = array(
				'id' => $data->getPrimaryKey(),
				'd' => $button['moveDirection']
			);

			if (isset($button['moveParam'])) {
				$params['p'] = $button['moveParam'];
			}

			echo CHtml::link($button['label'], Yii::app()->controller->createUrl($button['moveMethod'], $params), $htmlOptions);

			$tr['{' . $id . '}'] = ob_get_contents();
			ob_clean();
		}
		ob_end_clean();
		echo strtr($this->template, $tr);
	}

}
