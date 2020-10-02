<?php
class CuStaticContentsSchema extends CakeSchema {

	public $file = 'cu_static_contents.php';

	public function before($event = array()) {
		return true;
	}

	public function after($event = array()) {
	}

	public $cu_static_contents = array(
		'id' => array('type' => 'integer', 'null' => false, 'default' => null, 'unsigned' => false, 'key' => 'primary'),
		'name' => array('type' => 'string', 'null' => true, 'default' => null),
		'plugin' => ['type' => 'string', 'null' => true, 'default' => null],
		'type' => ['type' => 'string', 'null' => true, 'default' => null],
		'content_id' => ['type' => 'integer', 'null' => true, 'default' => null, 'unsigned' => false],
		'entity_id' => ['type' => 'integer', 'null' => true, 'default' => null, 'unsigned' => false],
		'url' => ['type' => 'text', 'null' => true, 'default' => null],
		'site_id' => ['type' => 'integer', 'null' => true, 'default' => 0, 'unsigned' => false],
		'controller' => ['type' => 'text', 'null' => true, 'default' => null],
		'action' => ['type' => 'text', 'null' => true, 'default' => null],
		'meta' => ['type' => 'text', 'null' => true, 'default' => null],
		'created' => array('type' => 'datetime', 'null' => true, 'default' => null),
		'modified' => array('type' => 'datetime', 'null' => true, 'default' => null),
		'indexes' => array(
			'PRIMARY' => array('column' => 'id', 'unique' => 1)
		),
	);

}
