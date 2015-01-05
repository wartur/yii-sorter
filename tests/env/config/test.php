<?php

$main = require(dirname(__FILE__).'/main.php');

$basePath = realpath(dirname(__FILE__).'/../..');

Yii::setPathOfAlias('sorter', $basePath . '/protected/extensions/wartur/yii-sorter');

return CMap::mergeArray(
	$main,
	array(
		'basePath'=>$basePath,
		'components'=>array(
			'fixture'=>array(
				'class'=>'system.test.CDbFixtureManager',
				'basePath' => $basePath . '/protected/tests/fixtures'
			),
			'db'=>array(
				'connectionString' => 'mysql:host=localhost;dbname=xxxx',
				'username' => 'xxxx',
				'password' => 'yyyy',
			),
		),
	)
);