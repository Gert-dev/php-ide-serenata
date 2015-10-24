AbstractProgressBar = require './AbstractProgressBar'

module.exports =

##*
# Progress bar that attaches itself to a status bar using a status bar service.
##
class StatusBarProgressBar extends AbstractProgressBar
    ###*
     * The tile present in the status bar.
    ###
    tile: null

    ###*
     * @inheritdoc
    ###
    attach: (statusBarSevice) ->
        if not @labelElement
            @initialize()

        @tile = statusBarSevice.addRightTile(item: @element, priority: 999999)

    ###*
     * @inheritdoc
    ###
    detach: ->
        @tile.destroy()
