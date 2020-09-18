<?php declare(strict_types=1);

namespace EventCandy\Sets;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class CustomFieldService
{
    /**
     * @var EntityRepositoryInterface
     */
    private $customFieldSetRepository;

    /**
     * @var Context
     */
    private $context;

    public function __construct(EntityRepositoryInterface $customFieldSetRepository)
    {
        $this->customFieldSetRepository = $customFieldSetRepository;
        $this->context = Context::createDefaultContext();
    }

    public function createCustomFields()
    {

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'ec_set'));

        /** @var IdSearchResult $ids */
        $ids = $this->customFieldSetRepository->searchIds($criteria, $this->context);
        if (!empty($ids->getIds())) {
            return;
        }
        $this->customFieldSetRepository->create( [
            [
                'name' => 'ec_set',
                'global' => true,
                'config' => [
                    'label' => [
                        'en-GB' => 'Set Products',
                        'de-DE' => 'Stücklisten'
                    ]
                ],
                'customFields' => [
                    [
                        'name' => 'ec_is_set',
                        'type' => CustomFieldTypes::BOOL,
                        'config' => [
                            'type' => 'checkbox',
                            'componentName' => 'sw-field',
                            'customFieldType' => 'checkbox',
                            'customFieldPosition' => 1,
                            'label' => [
                                'en-GB' => 'Is this product a Set Product?',
                                'de-DE' => 'Ist diese Produkt ein Stücklistenartikel?',
                            ]
                        ],
                        'active' => true
                    ],
                ],
                'relations' => [
                    [
                        'entityName' => 'product'
                    ]
                ]
            ]
        ], $this->context);
    }

    public function deleteCustomFields()
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', 'ec_set'));

        /** @var IdSearchResult $ids */
        $ids = $this->customFieldSetRepository->searchIds($criteria, $this->context);

        if (empty($ids->getIds())) {
            return;
        }
        $this->customFieldSetRepository->delete([['id' => $ids->getIds()[0]]], $this->context);
    }

}
