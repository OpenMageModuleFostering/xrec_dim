<?php
$installer = $this;

$installer->startSetup();

$installer->run(
	sprintf("DROP TABLE IF EXISTS `%s`",
		$installer->getTable('dim_payments')
	)
);

$installer->run("
	DELETE FROM `{$installer->getTable('core_config_data')}` where `path` LIKE 'payment/xrec%';
	DELETE FROM `{$installer->getTable('core_resource')}` where `code` = 'dim_setup';"
);

$installer->endSetup();
