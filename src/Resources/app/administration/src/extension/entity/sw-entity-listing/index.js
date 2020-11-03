import template from './sw-entity-listing.html.twig'
const {Component} = Shopware;

Component.override('sw-entity-listing', {
    template,

    methods: {
        createdComponent() {
            this.$super('createdComponent');

            if (this.items) {
                this.applyResult(this.items);
            }
        },
    }
});
