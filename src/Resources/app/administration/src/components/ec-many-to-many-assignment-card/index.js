import template from './ec-many-to-many-assignment-card.html.twig';
import './ec-many-to-many-assignment-card.scss';

const {Component} = Shopware;
const {debounce, get} = Shopware.Utils;
const {Criteria, EntityCollection} = Shopware.Data;

/**
 * @public
 * @status ready
 * @example-type code-only
 * @component-example
 * This component currently works only for current plugin use case.
 * Must be refactored to make component reusable.
 * Common use Case. ManyToMany Relation with additional info.
 *
 * Original component does not handle compound primary key. Neither This.
 * So add an Id col to the ManyToMany Join Tabke entity.
 * The Component uses the product.products collection.
 * but needs to use the product_product repository.
 *
 * assignmentRepository works for search.
 * Created from product.extension.products it has a special route: /product/{id}/extensions/products
 * assignmentRepository works for assign.
 *
 * assignmentRepository worked also for paginateGrid,
 * but I changed the repository in this method to be able to load additional information of the relation.(quantity).
 * Same happens to the deletion operation because the product_product.id does not match the selectedIds
 * which are just product id's triggered by the event. Hence the filter operation:
 * gridRepository.filter(e => e.productId === e.selectedIds);
 *
 * #rev: there is an assign operation, but no unassign. Since it is based on route you will need
 *
 *
 *
 *
 *
 */
Component.register('ec-many-to-many-assignment-card', {
    template,
    inheritAttrs: false,

    model: {
        property: 'entityCollection',
        event: 'change'
    },

    inject: ['repositoryFactory'],

    props: {
        columns: {
            type: Array,
            required: true
        },

        entityCollection: {
            type: Array,
            required: true
        },

        localMode: {
            type: Boolean,
            required: true
        },

        active: {
            type: Boolean,
            required: true
        },

        entityId: {
            type: String,
            required: true
        },

        gridRepository: {
            type: Object,
            required: false
        },

        resultLimit: {
            type: Number,
            required: false,
            default: 25
        },

        criteria: {
            type: Object,
            required: false,
            default() {
                return new Criteria(1, this.resultLimit);
            }
        },

        highlightSearchTerm: {
            type: Boolean,
            required: false,
            default: true
        },

        labelProperty: {
            type: String,
            required: false,
            default: 'name'
        },

        placeholder: {
            type: String,
            required: false,
            default() {
                if (this.localMode) {
                    return 'Save your Entity first, than add Associations';
                } else {
                    return `Add your ${this.entityCollection.entity} associations here...`
                }
            }
        },

        searchableFields: {
            type: Array,
            required: false,
            default() {
                return [];
            }
        }
    },

    data() {
        return {
            gridCriteria: null,
            searchCriteria: null,
            isLoadingResults: false,
            isLoadingGrid: false,
            selectedIds: [],
            resultCollection: null,
            gridData: [],
            searchTerm: '',
            totalAssigned: 0,
            loadingGridState: false
        };
    },

    computed: {
        context() {
            return this.entityCollection.context;
        },

        languageId() {
            return this.context.languageId;
        },

        assignmentRepository() {
            return this.repositoryFactory.create(
                this.entityCollection.entity,
                this.entityCollection.source
            );
        },

        searchRepository() {
            return this.repositoryFactory.create(
                this.entityCollection.entity
            );
        },

        page: {
            get() {
                return this.gridCriteria.page;
            },
            set(page) {
                this.gridCriteria.page = page;
            }
        },

        limit: {
            get() {
                return this.gridCriteria.limit;
            },
            set(limit) {
                this.gridCriteria.page = limit;
            }
        },

        total() {
            return this.localMode ? this.entityCollection.length : this.gridData.total || 0;
        },

        focusEl() {
            return this.$refs.searchInput;
        },

        originalFilters() {
            return this.criteria.filters;
        }
    },
    watch: {
        criteria: {
            immediate: true,
            handler() {
                this.selectedIds = this.entityCollection.getIds();
                this.gridCriteria = Criteria.fromCriteria(this.criteria);
                this.searchCriteria = Criteria.fromCriteria(this.criteria);
                if (!this.localMode) {
                    this.paginateGrid();
                }
            }
        },

        entityCollection() {
            this.selectedIds = this.entityCollection.getIds();

            if (!this.localMode) {

                this.paginateGrid();
                return;
            }
            this.gridData = this.entityCollection;
        },

        languageId() {
            if (!this.localMode) {
                this.paginateGrid();
            }
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initData();
        },

        initData() {
            this.page = 1;
            if (!this.localMode) {
                this.selectedIds = this.entityCollection.getIds();
                return;
            }
            this.gridData = this.entityCollection;
        },

        onInlineEdit(item) {
            this.gridRepository.save(item, this.context);
        },

        onSearchTermChange(input) {
            this.searchTerm = input.target.value || null;

            this.debouncedSearch();
        },

        debouncedSearch: debounce(function debouncedSearch() {
            this.resetSearchCriteria();
            this.searchCriteria.term = this.searchTerm || null;

            this.addContainsFilter(this.searchCriteria);

            this.searchItems().then((searchResult) => {
                this.resultCollection = searchResult;
            });
        }, 500),

        onSelectExpanded() {
            this.resetSearchCriteria();
            this.focusEl.select();

            // push
            this.searchItems().then((searchResult) => {
                this.resultCollection = searchResult;
            });
        },

        paginateResult() {
            if (this.resultCollection.length >= this.resultCollection.total) {
                return;
            }

            this.searchCriteria.page += 1;

            this.searchItems().then((searchResult) => {
                this.resultCollection.push(...searchResult);
            });
        },

        searchItems() {
            return this.searchRepository.search(this.searchCriteria, this.context).then((result) => {

                if (!this.localMode) {
                    const criteria = new Criteria(1, this.searchCriteria.limit);
                    //Exclude self...(?) does'n affect selection list.
                    result = result.filter(e => e.id !== this.entityId);
                    criteria.setIds(result.getIds());
                    this.assignmentRepository.searchIds(criteria, this.context).then(({data}) => {
                        data.forEach((id) => {
                            //id is not in selected Id's
                            if (!this.isSelected({id})) {
                                this.selectedIds.push(id);
                            }
                        });
                    });
                }

                return result;
            });
        },

        onItemSelect(item) {
            if (this.isSelected(item)) {
                const gridData = this.gridData.filter((e) => {
                    return e.productId === item.id;
                });
                this.removeItem(gridData[0]);
                return;
            }

            // if (this.localMode) {
            //     const newCollection = EntityCollection.fromCollection(this.entityCollection);
            //     newCollection.push(item);
            //
            //     this.selectedIds = newCollection.getIds();
            //     this.gridData = newCollection;
            //
            //     this.$emit('change', newCollection);
            //     return;
            // }

            this.assignmentRepository.assign(item.id, this.context).then(() => {
                this.selectedIds.push(item.id);

                // make dropdown removal work instantly. Which is based on grid data.
                this.paginateGrid();
            });
        },

        removeItem(item) {
            // if (this.localMode) {
            //     const newCollection = this.entityCollection.filter((selected) => {
            //         return selected.id !== item.id;
            //     });
            //
            //     this.selectedIds = newCollection.getIds();
            //     this.gridData = newCollection;
            //
            //     this.$emit('change', newCollection);
            //     return Promise.resolve();
            // }

            return this.gridRepository.delete(item.id, this.context).then(() => {
                this.selectedIds = this.selectedIds.filter((selectedId) => {
                    /** TODO: refactorable??? */
                    return selectedId !== item.productId;
                });
            });
        },

        isSelected(item) {
            return this.selectedIds.some((selectedId) => {
                return item.id === selectedId;
            });
        },

        resetActiveItem() {
            this.$refs.swSelectResultList.setActiveItemIndex(0);
        },

        onSelectCollapsed() {
            this.resultCollection = null;
            this.focusEl.blur();

            if (!this.localMode) {
                this.paginateGrid();
            }
        },

        resetSearchCriteria() {
            this.searchCriteria.page = 1;
            this.searchCriteria.term = this.searchTerm || null;
            this.searchCriteria.limit = this.resultLimit;

            this.addContainsFilter(this.searchCriteria);
        },

        getKey(object, keyPath, defaultValue) {
            return get(object, keyPath, defaultValue);
        },

        paginateGrid({page, limit} = this.gridCriteria) {
            this.gridCriteria.page = page;
            this.gridCriteria.limit = limit;
            this.setGridFilter();
            this.isLoadingGrid = true;

            // ToDo: refactor(?)
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('setProductId', this.entityId));
            criteria.addFilter(Criteria.equalsAny('productId', this.selectedIds));
            criteria.addAssociation('product');


            this.gridRepository['repoName'] = 'gridRepository';

            if (this.selectedIds.length) {
                this.gridRepository.search(criteria, this.context).then((assignments) => {
                    this.gridData = assignments;
                    this.isLoadingGrid = false;
                });
            }
        },

        setGridFilter() {
            this.gridCriteria.term = this.searchTerm || null;
            this.addContainsFilter(this.gridCriteria);
        },

        addContainsFilter(criteria) {
            if (criteria.term === null) {
                criteria.filters = [...this.originalFilters];
                return;
            }

            if (this.searchableFields.length > 0) {
                const containsFilter = this.searchableFields.map((field) => {
                    return Criteria.contains(field, criteria.term);
                });

                criteria.filters = [
                    ...this.criteria.filters,
                    Criteria.multi(
                        'OR',
                        containsFilter
                    )
                ];
                criteria.term = null;
            }
        },

        removeFromGrid(item) {
            this.removeItem(item).then(() => {
                if (!this.localMode) {
                    this.paginateGrid();
                }
            });
        }
    }
});
