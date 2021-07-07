<?php

namespace EventCandy\Sets\Commands;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Shopware\Core\Content\Content;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class EcsCommands
 * @package EventCandy\Sets\Commands
 *
 * bin/console ec:utils --tinker=true | less
 */
class EcsCommands extends Command
{
    protected static $defaultName = 'ec:utils';

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var Connection
     */
    private $connection;


    /**
     * CreateDemoData constructor.
     * @param EntityRepositoryInterface $productRepository
     * @param Connection $connection
     */
    public function __construct(EntityRepositoryInterface $productRepository, Connection $connection)
    {
        parent::__construct();
        $this->productRepository = $productRepository;
        $this->connection = $connection;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Plugin Utils & Automation')
            ->addOption(
                'tinker',
                't',
                InputOption::VALUE_OPTIONAL,
                'Tinker around with code',
                '0');

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('tinker')) {
            $this->tinker($input, $output);
        }
    }

    private function tinker(InputInterface $input, OutputInterface $output)
    {
//        $output->writeln('Tinker...');
//        $criteria = new Criteria();
//        $criteria->addAssociation('products');
//
//        /** @var EntitySearchResult $result */
//        $result = $this->productRepository->search($criteria, Context::createDefaultContext());
//
//        $result->jsonSerialize();
//        $output->writeln(var_export($result));

//        $sql = "select product_id from ec_order_line_item_product where order_id = unhex('F92F8E69B8F247129E6265DF4AA557FB');";
//
//        $this->connection->setFetchMode(FetchMode::ASSOCIATIVE);
//        $rows = $this->connection->fetchAll(
//            $sql
//        );
        $orderId = 'AFC13F908F8E4EB38B9908E76D04DFD6';

        $sql = "select product_id, quantity from ec_order_line_item_product where order_id = :id and order_version_id = :versionId;";
        $rows =  $this->connection->fetchAll(
            $sql,
            [
                'id' => Uuid::fromHexToBytes($orderId),
                'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)
            ]
        );

        //$rows = array_column($rows, 'product_id');
//        $rows = array_filter(array_keys(array_flip($rows)));
//        $rows = array_map(function($row) {
//            return Uuid::fromBytesToHex($row);
//        }, $rows);

        $normal = [
            '6661796371e442949d9a8f8595fbf712',
            '3f4d16c9646a45b59b2d9751fe10eae6',
            'b4d100433dd641aa96e909337cbbf800',
            'a1fa7d50dace44a0806f86327c1a5734'
        ];




        $output->writeln(print_r($rows, true));
//        $output->writeln(print_r(Uuid::fromHexToBytesList($normal), true));
//        $output->writeln(print_r(Uuid::fromHexToBytesList($rows), true));
    }
}
