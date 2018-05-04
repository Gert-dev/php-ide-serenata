{$, $$, SelectListView} = require 'atom-space-pen-views'

MultiSelectionView = require '../Utility/MultiSelectionView.coffee'

module.exports =

##*
# An extension on SelectListView from atom-space-pen-views that allows multiple selections.
##
class View extends MultiSelectionView
    ###*
     * @inheritdoc
    ###
    createWidgets: () ->
        checkboxBar = $$ ->
            @div class: 'checkbox-bar settings-view', =>
                @div class: 'controls', =>
                    @div class: 'block text-line', =>
                        @label class: 'icon icon-info', 'Tip: The order in which items are selected determines the order of the output.'

        checkboxBar.appendTo(this)

        # Ensure that button clicks are actually handled.
        @on 'mousedown', ({target}) =>
            return false if $(target).hasClass('checkbox-input')
            return false if $(target).hasClass('checkbox-label-text')

        super()

    ###*
     * @inheritdoc
    ###
    invokeOnDidConfirm: () ->
        if @onDidConfirm
           @onDidConfirm(@selectedItems, @getMetadata())
