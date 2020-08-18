<?php

$this->Plugin->initDb('CuStatic');

// 初期データ更新
$CuStaticConfig = ClassRegistry::init('CuStaticConfig');
$data['exportPath'] = WWW_ROOT . 'html' . DS;
$CuStaticConfig->saveKeyValue($data);
