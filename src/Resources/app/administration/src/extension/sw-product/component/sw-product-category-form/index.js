import template from './sw-product-category-form.html.twig'

const {Component} = Shopware;
Component.override('sw-product-category-form', {
    template,
    props: {
        belongsToSet: false
    }
});