<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20210712090959 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create postcodes table';
    }

	public function up(Schema $schema): void
	{
		$table = $schema->createTable('postcodes');

		$table->addColumn('postcode', 'string', ['length' => 9]);
		$table->addColumn('latitude', 'float');
		$table->addColumn('longitude', 'float');
		$table->addColumn('terminated', 'date', ['notnull' => false]);

		$table->setPrimaryKey(['postcode']);
	}

	public function down(Schema $schema): void
	{
		$schema->dropTable('postcodes');
	}
}
