<?php declare(strict_types=1);

namespace EventCandy\Sets\Migration\Test;

use Doctrine\DBAL\Connection;
use EventCandy\Sets\Migration\Migration1597850880Sets;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class Migration1611740369ExampleDescriptionTest extends TestCase
{
    use KernelTestBehaviour;

    public function testNoChanges(): void
    {

        /** @var Connection $conn */
        $conn = $this->getContainer()->get(Connection::class);
        $expectedSchema = $conn->fetchAssociative('SHOW CREATE TABLE `ec_product_product`')['Create Table'];

        $migration = new Migration1597850880Sets();
        $migration->update($conn);

        $actualSchema = $conn->fetchAssociative('SHOW CREATE TABLE `ec_product_product`')['Create Table'];
        static::assertSame($expectedSchema, $actualSchema, 'Schema changed!. Run init again to have clean state');
    }

    public function testNoTable(): void
    {
        /** @var Connection $conn */
        $conn = $this->getContainer()->get(Connection::class);
        $conn->executeStatement('DROP TABLE `ec_product_product`');

        $migration = new Migration1597850880Sets();
        $migration->update($conn);
        $exists = $conn->fetchOne('SELECT COUNT(*) FROM `ec_product_product`') !== false;

        static::assertTrue($exists);
    }
}
