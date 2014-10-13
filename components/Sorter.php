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
 * Sorter application component.
 */
class Sorter extends CApplicationComponent {

	public function init() {
		// register path of alias for internal usage
		Yii::setPathOfAlias('sorter', realpath(__DIR__ . '/..'));

		parent::init();
	}

}
