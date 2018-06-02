/*
 * decaffeinate suggestions:
 * DS102: Remove unnecessary code created because of implicit returns
 * Full docs: https://github.com/decaffeinate/decaffeinate/blob/master/docs/suggestions.md
 */
let View;
const {$, $$, SelectListView} = require('atom-space-pen-views');

const MultiSelectionView = require('../Utility/MultiSelectionView');

module.exports =

//#*
// An extension on SelectListView from atom-space-pen-views that allows multiple selections.
//#
(View = class View extends MultiSelectionView {
    /**
     * @inheritdoc
    */
    createWidgets() {
        const checkboxBar = $$(function() {
            return this.div({class: 'checkbox-bar settings-view'}, () => {
                return this.div({class: 'controls'}, () => {
                    return this.div({class: 'block text-line'}, () => {
                        return this.label({class: 'icon icon-info'}, 'Tip: The order in which items are selected determines the order of the output.');
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

        return super.createWidgets();
    }

    /**
     * @inheritdoc
    */
    invokeOnDidConfirm() {
        if (this.onDidConfirm) {
            return this.onDidConfirm(this.selectedItems, this.getMetadata());
        }
    }
});
