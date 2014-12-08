For running tests
==================

It is believed that the extension is in the following directory (used aliases and relative paths):
ext.wartur.yii-sorter

- Add the settings in the config/main.php as described ext.wartur.yii-sorter/README.md
(hereinafter referred to believe that there was an alias "sorter")

- Create a directory
/test/unit/extentions/wartur

- Create a symbolic link
sorter.tests.unit >>> application/test/unit/extentions/wartur/yii-sorter
ln -s /path/to/sorter__/tests/unut /path/to/project/tests__/unit/extentions/wartur
If you have a Window, use junction