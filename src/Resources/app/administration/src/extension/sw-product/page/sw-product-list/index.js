import template from './sw-product-list.html.twig'
const {Component} = Shopware;

//ToDo: nach update auf 6.4... calculated Available Stock hinzufügen
Component.override('sw-product-list', {
    template,
});