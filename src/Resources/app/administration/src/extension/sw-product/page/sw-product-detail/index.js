const {Component} = Shopware;

Component.override('sw-product-detail', {
    computed: {
        productCriteria() {
            const criteria = this.$super('productCriteria');
            // ToDo: Add criteria if is not Set
            criteria.addAssociation('products');
            return criteria;
        }
    },

    methods: {
        createdComponent() {
            this.$super('createdComponent');
            console.log('sw-product-detail: createdCompoment');
            console.log(this.productCriteria);
        }
    }


});
