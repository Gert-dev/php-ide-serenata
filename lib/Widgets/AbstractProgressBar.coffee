$ = require 'jquery'

module.exports =

##*
# Abstract base class for progress bars.
##
class AbstractProgressBar
    ###*
     * The label to show.
    ###
    label: []

    ###*
     * The label HTML element.
    ###
    labelElement: null

    ###*
     * The root HTML element of the progress bar.
    ###
    element: null

    ###*
     * Initializes the progress bar, setting up its DOM structure.
    ###
    initialize: () ->
        @labelElement = document.createElement("span")
        @labelElement.className = "text-subtle"
        @labelElement.innerHTML = @label

        @progress = document.createElement("progress")

        @element = document.createElement("div")
        @element.className = "php-integrator-progress-bar"
        @element.appendChild(@progress)
        @element.appendChild(@labelElement)

    ###*
     * Sets the text to show in the label.
     *
     * @param {string} label
    ###
    setLabel: (@label) ->
        if @labelElement
            @labelElement.innerHTML = @label

    ###*
     * Shows the element.
    ###
    show: ->
        $(@element).show()

    ###*
     * Hides the element.
    ###
    hide: ->
        $(@element).hide()

    ###*
     * Attaches to the specified element or using the specified object.
     *
     * @param {mixed} object
    ###
    attach: (object) ->
        throw new Error("This method is absract and must be implemented!")

    ###*
     * Detaches from the previously attached element.
    ###
    detach: ->
        throw new Error("This method is absract and must be implemented!")
