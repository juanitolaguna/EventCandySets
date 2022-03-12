<?php

declare(strict_types=1);

namespace EventCandy\Sets\Commands;

use EventCandy\Sets\Core\Content\Product\DataAbstractionLayer\StockUpdater;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateStockCommand extends Command
{
    protected static $defaultName = 'ecs:stock-update';

    private StockUpdater $stockUpdater;

    private EntityRepository $productRepository;

    public function __construct(StockUpdater $stockUpdater, EntityRepository $productRepository)
    {
        parent::__construct();
        $this->stockUpdater = $stockUpdater;
        $this->productRepository = $productRepository;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = Context::createDefaultContext();
        $ids = $this->productRepository->searchIds(new Criteria(), $context)->getIds();

        $output->writeln(sprintf("Update All Products: %s", count($ids)));

        $this->stockUpdater->update($ids, $context);
        return Command::SUCCESS;
    }

}