$ = require 'jquery'

module.exports =

##*
# Progress bar in the status bar
##
class ProgressBar
    text: []

    initialize: () ->
        @span = document.createElement("span")
        @span.className = "inline-block text-subtle"
        @span.innerHTML = "Indexing.."
        @span.innerHTML = @text

        @progress = document.createElement("progress")

        @container = document.createElement("div")
        @container.className = "inline-block"

        @subcontainer = document.createElement("div")
        @subcontainer.className = "block"
        @container.appendChild(@subcontainer)

        @subcontainer.appendChild(@progress)
        @subcontainer.appendChild(@span)

    setText: (@text) ->
        if @span
            @span.innerHTML = @text

    show: ->
        $(@container).show()

    hide: ->
        $(@container).hide()

    attach: (statusBarSevice) ->
        if not @span
            @initialize()

        @tile = statusBarSevice.addRightTile(item: @container, priority: 19)

    detach: ->
        @tile.destroy()
