/* global atom */

/*
 * decaffeinate suggestions:
 * DS102: Remove unnecessary code created because of implicit returns
 * DS207: Consider shorter variations of null checks
 * Full docs: https://github.com/decaffeinate/decaffeinate/blob/master/docs/suggestions.md
 */
const {$, $$, SelectListView} = require('atom-space-pen-views');

module.exports =

/**
 * An extension on SelectListView from atom-space-pen-views that allows multiple selections.
 */
class MultiSelectionView extends SelectListView {
    /**
     * Constructor.
     *
     * @param {Callback} onDidConfirm
     * @param {Callback} onDidCancel
     */
    constructor(onDidConfirm, onDidCancel = null) {
        super();

        /**
         * The callback to invoke when the user confirms his selections.
         */
        this.onDidConfirm = onDidConfirm;

        /**
         * The callback to invoke when the user cancels the view.
         */
        this.onDidCancel = onDidCancel;

        /**
         * Metadata to pass to the callbacks.
         */
        this.metadata = null;

        /**
         * The message to display when there are no results.
         */
        this.emptyMessage = null;

        /**
         * Items that are currently selected.
         */
        this.selectedItems = [];
    }

    /**
     * @inheritdoc
     */
    initialize() {
        super.initialize();

        this.addClass('php-ide-serenata-refactoring-multi-selection-view');
        this.list.addClass('mark-active');

        if (this.panel == null) {
            this.panel = atom.workspace.addModalPanel({ item: this, visible: false });
        }

        return this.createWidgets();
    }

    /**
     * Destroys the view and cleans up.
     */
    destroy() {
        this.panel.destroy();
        this.panel = null;
    }

    /**
     * Creates additional for the view.
     */
    createWidgets() {
        const checkboxBar = $$(function() {
            return this.div({class: 'checkbox-bar settings-view'}, () => {
                return this.div({class: 'controls'}, () => {
                    return this.div({class: 'block text-line'}, () => {
                        return this.label(
                            { class: 'icon icon-info' },
                            'Tip: The order in which items are selected determines the order of the output.'
                        );
                    });
                });
            });
        });

        checkboxBar.appendTo(this);

        // Ensure that button clicks are actually handled.
        this.on('mousedown', ({target}) => {
            if ($(target).hasClass('checkbox-input')) { return false; }
            if ($(target).hasClass('checkbox-label-text')) { return false; }
        });

        const cancelButtonText = this.getCancelButtonText();
        const confirmButtonText = this.getConfirmButtonText();

        const buttonBar = $$(function() {
            return this.div({class: 'button-bar'}, () => {
                this.button({
                    class: 'btn btn-error   inline-block-tight pull-left  icon icon-circle-slash button--cancel'
                }, cancelButtonText);
                this.button({
                    class: 'btn btn-success inline-block-tight pull-right icon icon-gear   button--confirm'
                }, confirmButtonText);
                return this.div({ class: 'clear-float' });
            });
        });

        buttonBar.appendTo(this);

        this.on('click', 'button', event => {
            if ($(event.target).hasClass('button--confirm')) { this.confirmedByButton(); }
            if ($(event.target).hasClass('button--cancel')) { return this.cancel(); }
        });

        this.on('keydown', event => {
            // Shift + Return
            if ((event.keyCode === 13) && (event.shiftKey === true)) {
                return this.confirmedByButton();
            }
        });

        // Ensure that button clicks are actually handled.
        return this.on('mousedown', ({target}) => {
            if ($(target).hasClass('btn')) { return false; }
        });
    }

    /**
     * @inheritdoc
     */
    viewForItem(item) {
        const classes = ['list-item'];

        if (item.className) {
            classes.push(item.className);
        }

        if (item.isSelected) {
            classes.push('active');
        }

        const className = classes.join(' ');
        const displayText = item.name;

        return `\
<li class="${className}">${displayText}</li>\
`;
    }

    /**
     * @inheritdoc
     */
    getFilterKey() {
        return 'name';
    }

    /**
     * Retrieves the text to display on the cancel button.
     *
     * @return {string}
     */
    getCancelButtonText() {
        return 'Cancel';
    }

    /**
     * Retrieves the text to display on the confirmation button.
     *
     * @return {string}
     */
    getConfirmButtonText() {
        return 'Generate';
    }

    /**
     * Retrieves the message that is displayed when there are no results.
     *
     * @return {string}
     */
    getEmptyMessage() {
        if (this.emptyMessage != null) {
            return this.emptyMessage;
        }

        return super.getEmptyMessage();
    }

    /**
     * Sets the message that is displayed when there are no results.
     *
     * @param {string} emptyMessage
     */
    setEmptyMessage(emptyMessage) {
        this.emptyMessage = emptyMessage;
    }

    /**
     * Retrieves the metadata to pass to the callbacks.
     *
     * @return {Object|null}
     */
    getMetadata() {
        return this.metadata;
    }

    /**
     * Sets the metadata to pass to the callbacks.
     *
     * @param {Object|null} metadata
     */
    setMetadata(metadata) {
        this.metadata = metadata;
    }

    /**
     * @inheritdoc
     */
    setItems(items) {
        let i = 0;

        for (const item of items) {
            item.index = i++;
        }

        super.setItems(items);

        this.selectedItems = [];
    }

    /**
     * @inheritdoc
     */
    confirmed(item) {
        let index;
        item.isSelected = !item.isSelected;

        if (item.isSelected) {
            this.selectedItems.push(item);

        } else {
            index = this.selectedItems.indexOf(item);

            if (index >= 0) {
                this.selectedItems.splice(index, 1);
            }
        }

        const selectedItem = this.getSelectedItem();
        index = selectedItem ? selectedItem.index : 0;

        this.populateList();

        return this.selectItemView(this.list.find(`li:nth(${index})`));
    }

    /**
     * Invoked when the user confirms his selections by pressing the confirmation button.
     */
    confirmedByButton() {
        this.invokeOnDidConfirm();
        this.restoreFocus();
        return this.panel.hide();
    }

    /**
     * Invokes the on did confirm handler with the correct arguments (if it is set).
     */
    invokeOnDidConfirm() {
        if (this.onDidConfirm) {
            return this.onDidConfirm(this.selectedItems, this.getMetadata());
        }
    }

    /**
     * @inheritdoc
     */
    cancelled() {
        if (this.onDidCancel) {
            this.onDidCancel(this.getMetadata());
        }

        this.restoreFocus();
        return this.panel.hide();
    }

    /**
     * Presents the view to the user.
     */
    present() {
        this.panel.show();
        return this.focusFilterEditor();
    }
};
