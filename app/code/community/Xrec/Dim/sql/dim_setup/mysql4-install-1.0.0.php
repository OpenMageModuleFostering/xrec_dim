<?php
$installer = $this;
$installer->startSetup();

$installer->run(
	sprintf("CREATE TABLE IF NOT EXISTS `%s` (
		`order_id` int(11) NOT NULL,
		`method` varchar(3) NOT NULL,
		`transaction_id` varchar(32) NOT NULL,
		`sequence_type` varchar(15) NOT NULL,
		`bank_status` varchar(20) NOT NULL,
		`created_at` datetime NOT NULL,
        `updated_at` datetime DEFAULT NULL
		) ENGINE=InnoDB DEFAULT CHARSET=utf8;",
		$installer->getTable('dim_payments')
	)
);

if(strlen(Mage::getStoreConfig("payment/xrec/merchantID")) == 0)
{
	$installer->run(
			sprintf("INSERT INTO `%s` (`severity`, `date_added`, `title`, `description`, `url`, `is_read`, `is_remove`) 
				VALUES ('4', '%s', 'Ga naar System -> Configuration -> Betaalmethodes om uw ING digitaal Incassomachtigen gegevens in te vullen om onze betaalmethode(s) te gebruiken',
				'Uw Digitaal Incassomachtigen instellingen moeten ingesteld worden. Als u dit niet doet dan kunnen uw klanten geen gebruik maken van de betaalmethode(s).',
				'http://www.xrec.nl/', '0', '0');",
				$installer->getTable('adminnotification_inbox'),
				date("Y/m/d H:i:s", time())
			)
		);
}

$installer->endSetup();
