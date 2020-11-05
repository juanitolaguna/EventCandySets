import template from './sw-product-detail-base.html.twig';
import Criteria from 'src/core/data-new/criteria.data';

const {Component, Context} = Shopware;
Component.override('sw-product-detail-base', {
    template,

    data() {
        return {
            belongsToSet: false,
            belongsToSetList: null,
            setAvailableStock: 0
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
                    sortable: true,
                },
                {
                    property: 'quantity',
                    label: 'Quantity',
                    sortable: false,
                    inlineEdit: 'number'
                },
                {
                    property: 'product.availableStock',
                    label: 'Available Stock',
                    sortable: false
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
        createdComponent() {
            this.$super('createdComponent');
            this.$on('set-available-stock', this.onSetAvailableStock);
        },
        onSetAvailableStock(min) {
            this.setAvailableStock = Math.floor(min.product.availableStock / min.quantity);
        },
        productBelongsToSet() {
            const criteria = new Criteria();
            // When I checked in computed this.product.id the method threw an exception.
            // I trigerred the search() with an undefined Id.
            criteria.addFilter(Criteria.equals('productId', this.product.id));
            criteria.addAssociation('setProduct');

            this.setProductRepository.search(criteria, Context.api).then((result) => {
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
