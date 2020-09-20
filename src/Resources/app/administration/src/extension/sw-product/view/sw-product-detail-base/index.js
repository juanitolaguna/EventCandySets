import template from './sw-product-detail-base.html.twig';
import Criteria from 'src/core/data-new/criteria.data';

const {Component, Context} = Shopware;
// const {Criteria, EntityCollection} = Shopware.Data;

Component.override('sw-product-detail-base', {
    template,

    data() {
        return {
            belongsToSet: false,
            belongsToSetList: null
        }
    },

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
                    sortable: false,
                },
                {
                    property: 'product.price[0].gross',
                    label: 'Price (brutto)',
                    sortable: false,
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

    watch: {
        product() {
            this.productBelongsToSet();
        }
    },

    methods: {
        productBelongsToSet() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('productId', this.product.id));
            criteria.addAssociation('setProduct');

            console.log('productId: ' + this.product.id);

            this.setProductRepository.search(criteria, Context.api).then((result) => {
                console.log('result.total: ' + result.total)
                if (result.total > 0) {
                    this.belongsToSetList = result;
                    this.belongsToSet = true;
                } else {
                    this.belongsToSet = false;
                }
            });
        },
    }

});
