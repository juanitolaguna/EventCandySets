<?php

namespace EventCandy\Sets\Commands;

use Doctrine\DBAL\Connection;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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
 *
 * Delete All Orders
 * - bin/console ecs:utils --delete-orders=true
 * in bash
 *
 * find . -name packlist*.pdf -delete
 * find . -name credit_note*.pdf -delete
 * find . -name delivery_note*.pdf -delete
 * find . -name invoice*.pdf -delete
 * find . -name storno*.pdf -delete
 */
class EcsCommands extends Command
{
    protected static $defaultName = 'ecs:utils';

    /**
     * @var Connection
     */
    private $connection;


    /**
     * @var EntityRepositoryInterface
     */
    private $mediaRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $documentRepository;

    /**
     * @var FilesystemInterface
     */
    private $filesystemPrivate;

    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    /**
     * EcsCommands constructor.
     * @param Connection $connection
     * @param EntityRepositoryInterface $mediaRepository
     * @param EntityRepositoryInterface $orderRepository
     * @param EntityRepositoryInterface $documentRepository
     * @param FilesystemInterface $filesystemPrivate
     * @param UrlGeneratorInterface $urlGenerator
     */
    public function __construct(
        Connection $connection,
        EntityRepositoryInterface $mediaRepository,
        EntityRepositoryInterface $orderRepository,
        EntityRepositoryInterface $documentRepository,
        FilesystemInterface $filesystemPrivate,
        UrlGeneratorInterface $urlGenerator
    )
    {
        parent::__construct();
        $this->connection = $connection;
        $this->mediaRepository = $mediaRepository;
        $this->orderRepository = $orderRepository;
        $this->documentRepository = $documentRepository;
        $this->filesystemPrivate = $filesystemPrivate;
        $this->urlGenerator = $urlGenerator;
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
                '0')
            ->setDescription('Plugin Utils & Automation')
            ->addOption(
                'delete-orders',
                'd',
                InputOption::VALUE_OPTIONAL,
                'Delete All Orders (inkl Documents)',
                false);

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('tinker')) {
            $this->tinker($input, $output);
        }

        if ($input->getOption('delete-orders')) {
            $this->deleteOrders($input, $output);
        }

        return 0;
    }

    private function deleteOrders(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Delete Orders & Documents');

        $sql = "select
                	*
                from (
                	select
                		d.id as documentId,
                		m.id as mediaId,
                		m.file_name,
                		d.referenced_document_id as referencedDocument
                	from
                		`order` o
                		inner join `document` as d on d.order_id = o.id
                			and d.order_version_id = o.version_id
                	left join `media` as m on m.id = d.document_media_file_id
                		and d.referenced_document_id is not null
                	union all
                	select
                		d.id as documentId,
                		m.id as mediaId,
                		m.file_name,
                		d.referenced_document_id as referencedDocument
                	from
                		`order` o
                	inner join `document` as d on d.order_id = o.id
                		and d.order_version_id = o.version_id
                	left join `media` as m on m.id = d.document_media_file_id
                		and d.referenced_document_id is null) as all_tables
                order by
	                referencedDocument desc;";

        $result = $this->connection->fetchAll($sql);


        $documents = array_map(function ($keys) {
            return [
                'id' => Uuid::fromBytesToHex($keys['documentId']),
            ];
        }, $result);

        $this->documentRepository->delete($documents, Context::createDefaultContext());

        $medias = array_map(function ($keys) {
            if ($keys['mediaId'] == null) return null;
            return Uuid::fromBytesToHex($keys['mediaId']);
        }, $result);


        $medias = array_values(array_filter($medias));
        $filesToDelete = [];
        if (!empty($medias)) {
            $mediaCriteria = new Criteria($medias);
            $toDelete = $this->mediaRepository->search($mediaCriteria, Context::createDefaultContext());

            foreach ($toDelete as $mediaEntity) {
                if (!$mediaEntity->hasFile()) {
                    continue;
                }
                $filesToDelete[] = $this->urlGenerator->getRelativeMediaUrl($mediaEntity);
            }

            foreach ($filesToDelete as $file) {
                try {
                    $this->filesystemPrivate->delete($file);
                } catch (FileNotFoundException $e) {
                    //ignore file is already deleted
                }
            }

            $deleteMedia = "delete from `media` where media.id in (:mediaIds);";
            $this->connection->executeStatement($deleteMedia,
                ['mediaIds' => $medias],
                ['mediaIds' => Connection::PARAM_STR_ARRAY]
            );
        }


        $orderSql = "select
                    	id,
                    	version_id
                    from
                    	`order`
                    where
                    	version_id <> :versionId;";

        $orderResult = $this->connection->fetchAll($orderSql, [
            'versionId' => Defaults::LIVE_VERSION
        ]);

        $orders = array_map(function ($keys) {
            return [
                'id' => Uuid::fromBytesToHex($keys['id']),
                'versionId' => Uuid::fromBytesToHex($keys['version_id'])
            ];
        }, $orderResult);
        $this->orderRepository->delete($orders, Context::createDefaultContext());


        $output->writeln(print_r($filesToDelete, true));
        $output->writeln(print_r($medias, true));
        $output->writeln(print_r($documents, true));
    }

    private function tinker(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Write some code here...');
    }


}
