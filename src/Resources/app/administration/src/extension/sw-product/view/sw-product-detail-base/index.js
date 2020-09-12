import template from './sw-product-detail-base.html.twig';
import Criteria from 'src/core/data-new/criteria.data';

const { Component } = Shopware;

Component.override('sw-product-detail-base', {
    template,

    computed: {
        productRepository() {
            return this.repositoryFactory.create('product');
        },


        productColumns() {
            return [
                {
                    property: 'name',
                    label: 'Name',
                    dataIndex: 'name',
                    routerLink: 'sw.product.detail',
                    sortable: false }
                // }, {
                //     property: 'manufacturer.name',
                //     label: this.$tc('sw-category.base.products.columnManufacturerLabel'),
                //     routerLink: 'sw.manufacturer.detail',
                //     sortable: false
                // }
            ];
        },
    },

    methods: {

    }

});
