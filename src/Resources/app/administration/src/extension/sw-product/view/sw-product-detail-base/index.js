import template from './sw-product-detail-base.html.twig';
import Criteria from 'src/core/data-new/criteria.data';

const {Component} = Shopware;

Component.override('sw-product-detail-base', {
    template,

    computed: {

        isSetActive() {
            const hasCFProperty = this.product.hasOwnProperty('customFields');
            if (hasCFProperty) {
                const hasSetProperty = (this.product.customFields !== null) && this.product.customFields.hasOwnProperty('ec_is_set');
                if (hasSetProperty) {
                    return this.product.customFields['ec_is_set'];
                }
                return false;
            }
            return false;
        },

        setProductRepository() {
            return this.repositoryFactory.create('ec_product_product');
        },

        productColumns() {
            return [
                {
                    property: 'product.name',
                    label: 'Name',
                    dataIndex: 'name',
                    sortable: false
                }
                , {
                    property: 'quantity',
                    label: 'Quantity',
                    sortable: false,
                    inlineEdit: 'number'
                }
            ];
        },
    },

    methods: {}

});
