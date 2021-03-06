YII-SORTER ([Русская версия](https://github.com/wartur/yii-sorter/blob/master/README.ru.md))
============================================================================================

Extension for Yii to work with an ordered list of user controlled. When using this extension do not forget to use table locks or transactions.

DEMO: http://yii-sorter.wartur.ru

[Description of the algorithm (RUS)](https://github.com/wartur/yii-sorter/blob/master/ALGORITHM.ru.md)

## Release 1.0.0 for Yii first and last. Further development [to be carried out on Yii2](https://github.com/wartur/yii2-sorter)

###### From the author
> This extension was created for fun and to meet their vile,
> vulgar and useless in practice, perfectionism, I am aware
> that this problem can be solved optimally less and much
> less time consuming. I tried to create the most convenient,
> fast and stable mechanism for solving simple enough and often
> enough in their practice tasks

(Sorry for my english. I'm using google translate)

Abstract
--------
Extension allows you to work with an ordered list generated by the user.
Extension provides the highest speed of the database.
To insert a record in any place ordered list requires a one write request to the database.

Examples of use: display order of articles on the page, the widgets on the screen.
Due to the algorithm used, this extension is useful for working
with very large data sets that can not fit in memory.

The extension is as a component of the application
behavior for CActiveRecord and a set of ready-made widgets and actions to work with him.

Component is used to support functional lighting records
as a link between the display and CActiveRecord

Working with records occurs in full accordance with the concept
of ActiveRecord. Extension does not use direct calls to the database
to record. So you can not worry about the performance of
beforeSave, afterSave. All your events over the records will
work without errors, including those that have been changed indirectly.

The algorithm is tested with the default settings. It has been tested in
some extreme conditions and settings other than the default settings.
Code coverage of more than 98% (actually 92% - 6% do not cover non-critical
section of code that is required only when the project development
and helps the developer). If you are using transactions or lock tables,
you can do not worry about data integrity.

IMPORTANT: all operations using this extension shall be made through the
transaction (ISOLATION LEVEL SERIALIZABLE) / lock. Otherwise, there is a
possibility of destruction of the database. All actions of this expansion
include transactions in cases when the table does not support transactions
in the component you want to specify that you want to use a table lock.

Connecting to the expansion project
-----------------------------------
1) [Download the latest release](https://github.com/wartur/yii-sorter/releases)

2) Unpack yii-sorter in the directory ext.wartur.yii-sorter

3) Add a new alias path to the top of the configuration file (default: config / main.php)
```php
Yii::setPathOfAlias('sorter', 'protected/extensions/wartur/yii-sorter');
```

4) Add a new component of the application configuration file.
Minimum configuration:
```php
'components'=>array(
	'sorter' => array(
		'class' => 'sorter.components.Sorter',
	),
	// ....
)
```

5) Add behavior to a model in which you want to use an ordered list.
Minimum configuration:
```php
public function behaviors() {
	return array_merge(parent::behaviors(), array(
		'SorterActiveRecordBehavior' => array(
			'class' => 'sorter.behaviors.SorterActiveRecordBehavior',
		)
	));
}
```

6) Check that your model satisfies the formula given in the [sorter.tests.env.schema](https://github.com/wartur/yii-sorter/blob/master/tests/env/schema/sortest.sql).
Remember to work the behavior required field SIGNED INT sort with a unique key.
More you can read in [API reference](https://github.com/wartur/yii-sorter/blob/master/behaviors/SorterActiveRecordBehavior.php)

Working with Widget Ready
-------------------------
Extention the available basic set of widgets. Using the current API, you can write
your widget will do the rest for you behavior. You do not need to know how it will
work at a low level, it is important to understand that it will work very quickly and correctly.

1) Add to the table CGridView column with a simple interface management positions.
Minimum configuration:
```php
<?php
$this->widget('zii.widgets.grid.CGridView', array(
	'dataProvider' => $model->search(),
	'columns' => array(
		'id',
		array(
			'class' => 'sorter.widgets.SorterButtonColumn',
		),
	)
));
?>
```

2) Add to the table column to move the current line before a certain position.
Minimum configuration:
```php
<?php
$this->widget('zii.widgets.grid.CGridView', array(
	'dataProvider' => $model->search(),
	'columns' => array(
		'id',
		array(
			'class' => 'sorter.widgets.SorterDropDownColumn',
		),
	)
));
?>
```

3) Widgets to work with the controller you want to add to controller
actions and behavior. The behavior of the controller communicates with
the model. Full set of actions are complete implimentatsiey API behavior
```php
public function actions() {
	return array_merge(parent::actions(), array(
		'sorterMoveNumber' => 'sorter.actions.SorterMoveNumberAction',
		'sorterMoveOne' => 'sorter.actions.SorterMoveOneAction',
		'sorterMoveToEdge' => 'sorter.actions.SorterMoveToEdgeAction',
		'sorterMoveToModel' => 'sorter.actions.SorterMoveToModelAction',
		'sorterMoveToPosition' => 'sorter.actions.SorterMoveToPositionAction',
	));
}

public function behaviors() {
	return array_merge(parent::behaviors(), array(
		'SorterControllerBehavior' => array(
			'class' => 'sorter.behaviors.SorterControllerBehavior',
			'className' => 'Sortest',
		),
	));
}
```
Learn more about these widgets you can read in the API reference
directory [sorter.widgets](https://github.com/wartur/yii-sorter/tree/master/widgets),
they have additional settings

Work with backlight flash
-------------------------
To connect the backlight is required in the configuration file
in the component configuration option to add useFlashHighligh.
This setting specifies the component that you want to store
the data on the operations of the models for the subsequent
decision of the illumination
```php
'components'=>array(
	'sorter' => array(
		'class' => 'sorter.components.Sorter',
		'useFlashHighligh' => true
	),
	// ....
)
```

Illumination is made as helper methods for setting CGvidView.
Minimum configuration:
```php
<?php
$this->widget('zii.widgets.grid.CGridView', array(
	'dataProvider' => $model->search(),
	'rowHtmlOptionsExpression' => 'Yii::app()->sorter->fhGridRowHtmlOptionsExpression($data)',
	'afterAjaxUpdate' => Yii::app()->sorter->fhGridAfterUpdateCode(true),
	'columns' => array(
		'id',
		array(
			'class' => 'sorter.widgets.SorterButtonColumn',
		),
	)
));
?>
```
At helpers Sorter::fhGridRowHtmlOptionsExpression and Sorter::fhGridAfterUpdateCode
have additional options of customization, more in the [API reference](https://github.com/wartur/yii-sorter/blob/master/components/Sorter.php) of methods

Using the advanced settings helper component Sorter, you can instead use
the flash built into the allocation of selected yii. To do this,
change the parameter to use Sorter::$flashHighlightCssClass='selected'.
In this case afterAjaxUpdate set is not required.

Ensuring integrity in the tables do not support transactions
-----------------------------------------------------------------
Actions are implemented using transactions with maximum security.
In some cases it is impossible to execute the transaction.
Because of this, we have to use the blocking of the entire table.
In the ready to implement all actions already, you only need to add
a configuration file to the configuration component appropriate setting:
```php
'components'=>array(
	'sorter' => array(
		'class' => 'sorter.components.Sorter',
		'useLockTable' => true
	),
	// ....
)
```

Debug information. For fans of the engine compartment
-----------------------------------------------------
You can add this column and see how to move bits or modify the actual values of the field sort =)
```php
'columns' => array(
	array(
		'type' => 'raw',
		'name' => 'sort',
		'value' => '"(".str_pad(decbin($data->sort), 32, "0", STR_PAD_LEFT).")<br>".$data->sort'
	),
	// ...
)
```

Good luck!
