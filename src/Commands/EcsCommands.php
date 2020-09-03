<?php

namespace EventCandy\Sets\Commands;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Content;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EcsCommands extends Command
{
    protected static $defaultName = 'ec:utils';

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * CreateDemoData constructor.
     * @param EntityRepositoryInterface $productRepository
     */
    public function __construct(EntityRepositoryInterface $productRepository)
    {
        parent::__construct();
        $this->productRepository = $productRepository;
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
        $output->writeln('Tinker...');
        $criteria = new Criteria();
        $criteria->addAssociation('sets');

        /** @var EntitySearchResult $result */
        $result = $this->productRepository->search($criteria, Context::createDefaultContext());

        $result->jsonSerialize();
        $output->writeln(var_export($result));
    }
}
