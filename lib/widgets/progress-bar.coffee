$ = require 'jquery'

module.exports =

##*
# Progress bar in the status bar
##
class ProgressBar
    text: []

    initialize: () ->
        @span = document.createElement("span")
        @span.className = "text-subtle"
        @span.innerHTML = @text

        @progress = document.createElement("progress")

        @container = document.createElement("div")
        @container.className = "php-integrator-progress-bar"
        @container.appendChild(@progress)
        @container.appendChild(@span)

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

        @tile = statusBarSevice.addLeftTile(item: @container, priority: 999999)

    detach: ->
        @tile.destroy()
